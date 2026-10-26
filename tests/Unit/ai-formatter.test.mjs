import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { Module } from "node:module";
import { test } from "node:test";
import { fileURLToPath } from "node:url";
import ts from "typescript";

// Execute the real TSX source. Only UI dependencies, hooks, and browser I/O are mocked.
let currentHooks;
const react = {
    createElement: (type, props, ...children) => ({ type, props: { ...props, children } }),
    useState(initial) {
        const hooks = currentHooks;
        const index = hooks.cursor++;
        if (!(index in hooks.slots)) hooks.slots[index] = typeof initial === "function" ? initial() : initial;
        return [hooks.slots[index], (value) => {
            hooks.slots[index] = typeof value === "function" ? value(hooks.slots[index]) : value;
        }];
    },
    useMemo(factory, dependencies) {
        const index = currentHooks.cursor++;
        const previous = currentHooks.slots[index];
        if (previous && dependencies.every((value, i) => Object.is(value, previous.dependencies[i]))) return previous.value;
        const value = factory();
        currentHooks.slots[index] = { dependencies, value };
        return value;
    },
};
const notices = [];
const cache = new Map();
function loadSource(name) {
    if (cache.has(name)) return cache.get(name);
    const url = new URL(`../../resources/js/components/ai/${name}.tsx`, import.meta.url);
    const filename = fileURLToPath(url);
    const { outputText, diagnostics } = ts.transpileModule(readFileSync(url, "utf8"), {
        fileName: filename,
        reportDiagnostics: true,
        compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2022, jsx: ts.JsxEmit.React },
    });
    assert.deepEqual(diagnostics, []);
    const sourceModule = new Module(filename);
    sourceModule.require = (specifier) => {
        if (specifier === "react") return react;
        if (specifier === "sonner") return { toast: {
            success: (message) => notices.push({ kind: "success", message }),
            error: (message) => notices.push({ kind: "error", message }),
        } };
        if (specifier === "@/lib/utils") return { cn: (...classes) => classes.filter(Boolean).join(" ") };
        if (specifier.startsWith("./")) return loadSource(specifier.slice(2));
        if (specifier === "lucide-react" || specifier === "recharts" || specifier.startsWith("@/components/")) {
            return new Proxy({}, { get: (_, key) => String(key) });
        }
        throw new Error(`Unexpected dependency: ${specifier}`);
    };
    cache.set(name, sourceModule.exports);
    sourceModule._compile(outputText, filename);
    cache.set(name, sourceModule.exports);
    return sourceModule.exports;
}
const { ChatMessageFormatter } = loadSource("chat-message-formatter");
const { AnalyticsChartRenderer, parseChartArtifact } = loadSource("analytics-chart-renderer");
const { DocumentDownloadCard, parseDocumentArtifact } = loadSource("document-download-card");
const { InteractiveTable } = loadSource("interactive-table");

function renderer(component) {
    const hooks = { slots: [], cursor: 0 };
    return (props) => {
        currentHooks = hooks;
        hooks.cursor = 0;
        try { return component(props); } finally { currentHooks = undefined; }
    };
}
function nodes(tree, predicate) {
    if (Array.isArray(tree)) return tree.flatMap((child) => nodes(child, predicate));
    if (!tree || typeof tree !== "object") return [];
    return [...(predicate(tree) ? [tree] : []), ...nodes(tree.props?.children, predicate)];
}
function text(tree) {
    if (Array.isArray(tree)) return tree.map(text).join("");
    if (!tree || typeof tree === "boolean") return "";
    return typeof tree === "object" ? text(tree.props?.children) : String(tree);
}
const chart = { _type: "chart_artifact", chart_type: "bar", title: "Enrollment", data: [{ label: "BSIT", value: 12 }] };
const documentArtifact = {
    _type: "document_artifact", document_id: "doc_123", title: "Enrollment report", filename: "Enrollment_report.pdf",
    format: "pdf", content: "# Enrollment report\n\nPrepared from institutional records.",
};
const tool = (id, output) => ({ id, toolName: "GenerateArtifactTool", state: "output-available", output });
const fence = (artifact) => `\`\`\`json\n${JSON.stringify(artifact)}\n\`\`\``;
const artifacts = (tree) => nodes(tree, ({ type }) => type === AnalyticsChartRenderer || type === DocumentDownloadCard);

function browser(t, responses = []) {
    notices.length = 0;
    const downloads = [];
    const blobs = [];
    const requests = [];
    const revoked = [];
    const document = {
        body: { appendChild() {}, removeChild() {} },
        querySelector: () => ({ content: "test-csrf" }),
        createElement: () => ({ click() { downloads.push({ filename: this.download, href: this.href }); } }),
    };
    const url = {
        createObjectURL(blob) { blobs.push(blob); return `blob:artifact-${blobs.length}`; },
        revokeObjectURL(value) { revoked.push(value); },
    };
    const replacements = {
        document, window: { document, URL: url }, URL: url,
        fetch: async (target, init) => {
            requests.push({ target, init });
            assert.ok(responses.length, `Unexpected request to ${target}`);
            const response = responses.shift();
            if (response instanceof Error) throw response;
            return response;
        },
    };
    for (const [key, value] of Object.entries(replacements)) {
        const original = Object.getOwnPropertyDescriptor(globalThis, key);
        Object.defineProperty(globalThis, key, { value, configurable: true, writable: true });
        t.after(() => original ? Object.defineProperty(globalThis, key, original) : delete globalThis[key]);
    }
    return { downloads, blobs, requests, revoked };
}
const response = (body, status = 200, type = "text/plain") => new Response(body, { status, headers: { "Content-Type": type } });
const downloadButton = (tree) => nodes(tree, (node) => typeof node.props?.onClick === "function" && text(node) === "Download")[0];

test("tool artifacts survive unchanged-content rerenders and unrelated tool-array updates", () => {
    const render = renderer(ChatMessageFormatter);
    const original = [tool("document", documentArtifact), tool("chart", JSON.stringify(chart))];
    for (let update = 0; update < 5; update++) {
        const tree = render({ content: "Your results are ready.", toolCalls: [...original, tool(`unrelated-${update}`, { count: update })] });
        assert.equal(artifacts(tree).length, 2);
        assert.deepEqual(artifacts(tree).map((node) => node.type), [DocumentDownloadCard, AnalyticsChartRenderer]);
    }
    assert.deepEqual(original[0].output, documentArtifact);
});

test("deduplicates content and tool copies while retaining distinct charts with the same title", () => {
    const render = renderer(ChatMessageFormatter);
    const changedChart = { ...chart, data: [{ label: "BSIT", value: 24 }] };
    const content = [fence(documentArtifact), fence(documentArtifact), fence(chart), fence(chart)].join("\n");
    for (let update = 0; update < 3; update++) {
        const tree = render({ content, toolCalls: [tool("doc", documentArtifact), tool("chart", chart), tool("changed", changedChart), tool(`copy-${update}`, changedChart)] });
        assert.equal(artifacts(tree).length, 3);
        assert.deepEqual(artifacts(tree).filter((node) => node.type === AnalyticsChartRenderer).map((node) => node.props.chart.data[0].value), [12, 24]);
    }
});

test("deduplicates unfenced JSON artifacts against tool output", () => {
    for (const artifact of [chart, documentArtifact]) {
        const tree = renderer(ChatMessageFormatter)({ content: JSON.stringify(artifact), toolCalls: [tool("copy", artifact)] });
        assert.equal(artifacts(tree).length, 1);
    }
});

test("malformed fenced artifacts stay visible as code and never become tool cards", () => {
    for (const artifact of [null, [], { ...chart, data: {} }, { ...chart, data: [null] }, { ...chart, data: [{ label: {}, value: 1 }] }, { ...documentArtifact, title: {} }, { ...documentArtifact, format: {} }]) {
        const tree = renderer(ChatMessageFormatter)({ content: fence(artifact), toolCalls: [tool("bad", artifact)] });
        assert.equal(artifacts(tree).length, 0);
        assert.equal(nodes(tree, ({ type }) => type === "CodeBlock").length, 1);
    }
});

test("validates chart cells and normalizes numeric server values without modifying input", () => {
    const input = { ...chart, data: [{ label: "BSIT", value: "12.5", color: null }] };
    assert.deepEqual(parseChartArtifact(input).data, [{ label: "BSIT", value: 12.5, color: undefined }]);
    assert.equal(input.data[0].value, "12.5");
    for (const value of [null, false, "", " ", "twelve", Infinity, NaN]) {
        assert.equal(parseChartArtifact({ ...chart, data: [{ label: "BSIT", value }] }), null);
    }
    for (const input of [{ ...chart, title: {} }, { ...chart, chart_type: "scatter" }, { ...chart, metric_unit: [] }, { ...chart, description: {} }]) {
        assert.equal(parseChartArtifact(input), null);
    }
    assert.equal(renderer(AnalyticsChartRenderer)({ chart: { ...chart, data: {} } }), null);
    assert.notEqual(renderer(AnalyticsChartRenderer)({ chart }), null);
});

test("validates document display and download fields before rendering", () => {
    assert.equal(parseDocumentArtifact({ ...documentArtifact, format: "PDF" }).format, "pdf");
    for (const input of [null, [], { ...documentArtifact, document_id: " " }, { ...documentArtifact, filename: false }, { ...documentArtifact, format: "docx" }, { ...documentArtifact, content: {} }, { ...documentArtifact, download_url: {} }, { ...documentArtifact, file_size: 10 }]) {
        assert.equal(parseDocumentArtifact(input), null);
        assert.equal(renderer(DocumentDownloadCard)({ document: input }), null);
    }
});

test("table CSV download escapes header and cell quotes, commas, newlines, and Unicode", async (t) => {
    const io = browser(t);
    const tree = renderer(InteractiveTable)({ headers: ['Course "name", year', "Notes"], rows: [['BSIT, "A"', "First\r\nSecond\n第三"]] });
    nodes(tree, (node) => node.props?.title === "Export CSV")[0].props.onClick();
    assert.equal(await io.blobs[0].text(), '"Course ""name"", year","Notes"\r\n"BSIT, ""A""","First\r\nSecond\n第三"');
    assert.equal(io.downloads.length, 1);
    assert.deepEqual(io.revoked, [io.downloads[0].href]);
});

test("chart CSV download uses the same escaping for labels", async (t) => {
    const io = browser(t);
    const tree = renderer(AnalyticsChartRenderer)({ chart: { ...chart, data: [{ label: 'BSIT, "A"\n2026', value: 12 }] } });
    nodes(tree, (node) => node.props?.title === "Export data as CSV")[0].props.onClick();
    assert.equal(await io.blobs[0].text(), '"Label","Value","Percentage"\r\n"BSIT, ""A""\n2026","12","100.00%"');
});

test("failed PDF downloads never save local Markdown with a PDF extension", async (t) => {
    const io = browser(t, [response("expired", 404), response("failed", 500)]);
    const tree = renderer(DocumentDownloadCard)({ document: documentArtifact });
    await downloadButton(tree).props.onClick();
    assert.equal(io.requests.length, 2);
    assert.equal(io.downloads.length, 0);
    assert.equal(io.blobs.length, 0);
    assert.match(notices.at(-1).message, /PDF generation failed/);
    assert.equal(notices.at(-1).kind, "error");
});

test("HTTP 200 Markdown PDF fallbacks are rejected from both download endpoints", async (t) => {
    const io = browser(t, [response("# fallback", 200, "text/markdown"), response("# fallback", 200, "application/pdf")]);
    await downloadButton(renderer(DocumentDownloadCard)({ document: documentArtifact })).props.onClick();
    assert.equal(io.downloads.length, 0);
    assert.equal(io.requests[1].init.method, "POST");
    assert.equal(notices.at(-1).kind, "error");
});

test("regenerates a PDF after a network failure and saves actual PDF bytes", async (t) => {
    const io = browser(t, [new Error("offline"), response("%PDF-1.7\nreport bytes", 200, "application/pdf")]);
    await downloadButton(renderer(DocumentDownloadCard)({ document: documentArtifact })).props.onClick();
    assert.equal(io.downloads[0].filename, "Enrollment_report.pdf");
    assert.match(await io.blobs[0].text(), /^%PDF-/);
    assert.equal(io.requests[1].target, "/administrators/ai/export-document");
    assert.deepEqual(JSON.parse(io.requests[1].init.body), { title: documentArtifact.title, format: "pdf", content: documentArtifact.content });
    assert.equal(io.requests[1].init.headers["X-CSRF-TOKEN"], "test-csrf");
    assert.equal(notices.at(-1).kind, "success");
});

test("downloads a valid cached PDF directly without regenerating it", async (t) => {
    const io = browser(t, [response("%PDF-1.7\nreport bytes", 200, "application/pdf")]);
    await downloadButton(renderer(DocumentDownloadCard)({ document: { ...documentArtifact, download_url: "/ai/download-document/doc_123" } })).props.onClick();
    assert.equal(io.requests.length, 1);
    assert.equal(io.requests[0].target, "/ai/download-document/doc_123");
    assert.equal(io.downloads.length, 1);
});

for (const format of ["csv", "markdown"]) {
    test(`${format} can fall back to a correctly named local text download`, async (t) => {
        const io = browser(t, [response("expired", 404), new Error("unavailable")]);
        await downloadButton(renderer(DocumentDownloadCard)({ document: { ...documentArtifact, format } })).props.onClick();
        assert.equal(io.downloads[0].filename, `Enrollment_report.${format === "csv" ? "csv" : "md"}`);
        assert.equal(await io.blobs[0].text(), documentArtifact.content);
        assert.match(io.blobs[0].type, format === "csv" ? /^text\/csv/ : /^text\/markdown/);
    });
}

test("on-demand message PDF export also rejects a Markdown success response", async (t) => {
    const io = browser(t, [response("# fallback", 200, "text/markdown")]);
    const tree = renderer(ChatMessageFormatter)({ content: documentArtifact.content });
    const exportButton = nodes(tree, (node) => typeof node.props?.onClick === "function" && text(node).includes("Download as PDF"))[0];
    await exportButton.props.onClick();
    assert.equal(io.downloads.length, 0);
    assert.equal(notices.at(-1).kind, "error");
});
