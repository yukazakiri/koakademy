import tailwindcss from "@tailwindcss/vite";
import react from "@vitejs/plugin-react";
import assert from "node:assert/strict";
import { EventEmitter, once } from "node:events";
import { existsSync, mkdirSync } from "node:fs";
import path from "node:path";
import process from "node:process";
import { after, before, test } from "node:test";
import { fileURLToPath } from "node:url";
import puppeteer from "puppeteer";
import { createServer } from "vite";

const root = fileURLToPath(new URL("../../", import.meta.url));
const executablePath =
    process.env.PUPPETEER_EXECUTABLE_PATH ??
    ["/usr/bin/chromium", "/usr/bin/chromium-browser", "/usr/bin/google-chrome", "/Applications/Google Chrome.app/Contents/MacOS/Google Chrome"].find(
        existsSync,
    );
let server;
let browser;
let baseUrl;
const previewResponses = new EventEmitter();

// This harness exercises the real React editor, using the already-installed Vite,
// Puppeteer and Chromium. PHP feature tests cover real Blade rendering and RBAC.
before(
    async () => {
        assert.ok(executablePath, "Set PUPPETEER_EXECUTABLE_PATH to an installed Chromium browser. No browser download is required.");
        server = await createServer({
            configFile: false,
            root,
            appType: "custom",
            plugins: [tailwindcss(), react()],
            resolve: { alias: { "@": path.join(root, "resources/js") }, dedupe: ["react", "react-dom"] },
            server: { host: "127.0.0.1", port: 0 },
        });
        server.middlewares.use(async (request, response, next) => {
            if (request.url?.startsWith("/email-template-canvas-fixture")) {
                const html = await server.transformIndexHtml(
                    request.url,
                    '<!doctype html><html><head><meta name="viewport" content="width=device-width, initial-scale=1" /></head><body><div id="root"></div><script type="module" src="/tests/Fixtures/email-template-canvas.tsx"></script></body></html>',
                );
                response.setHeader("Content-Type", "text/html");
                response.end(html);
                return;
            }
            if (request.url === "/email-canvas-preview-fixture" && request.method === "POST") {
                let body = "";
                for await (const chunk of request) body += chunk;
                const { configuration } = JSON.parse(body);
                response.setHeader("Content-Type", "application/json");
                if (configuration.subject === "Preview error") {
                    response.statusCode = 422;
                    response.end(JSON.stringify({ errors: { "configuration.subject": ["This preview could not be validated."] } }));
                    return;
                }
                const tokens = { student_name: "Santos, Maria", status: "Interview Scheduled" };
                const interpolate = (copy) => copy.replace(/\{\{ ([a-z_]+) \}\}/g, (match, key) => tokens[key] ?? match);
                const escape = (copy) =>
                    copy.replace(/[&<>"']/g, (character) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" })[character]);
                const subject = interpolate(configuration.subject);
                const intro = configuration.custom_body || "First paragraph for {{ student_name }}.\n\nSecond paragraph with next steps.";
                const html = `<!doctype html><html><body><h1>${escape(subject)}</h1><p>Dear Santos, Maria,</p><p>${escape(interpolate(intro)).replaceAll("\n", "<br>")}</p><p>System-rendered sample details</p><script>window.previewScriptRan = true</script></body></html>`;
                const send = () => {
                    response.end(JSON.stringify({ subject, html }));
                    previewResponses.emit(configuration.subject);
                };
                if (configuration.subject === "Slow preview") setTimeout(send, 800);
                else send();
                return;
            }
            next();
        });
        await server.listen();
        baseUrl = `http://127.0.0.1:${server.httpServer.address().port}`;
        browser = await puppeteer.launch({
            executablePath,
            headless: true,
            args: ["--no-sandbox", "--disable-dev-shm-usage"],
        });
    },
    { timeout: 120_000 },
);

after(async () => {
    await browser?.close();
    await server?.close();
});

async function withEditor(run, { query = "", width = 1600, height = 1100, reducedMotion = false } = {}) {
    const context = await browser.createBrowserContext();
    const page = await context.newPage();
    const errors = [];
    page.on("pageerror", (error) => errors.push(error.message));
    await page.setViewport({ width, height });
    if (reducedMotion) await page.emulateMediaFeatures([{ name: "prefers-reduced-motion", value: "reduce" }]);
    await page.setRequestInterception(true);
    page.on("request", (request) => {
        // No external fonts, analytics or other outbound traffic in browser tests.
        if (request.url().startsWith(baseUrl) || request.url().startsWith("data:") || request.url().startsWith("about:")) request.continue();
        else request.abort();
    });
    try {
        await page.goto(`${baseUrl}/email-template-canvas-fixture${query}`, { waitUntil: "networkidle0" });
        await page.waitForSelector("[data-email-template-editor]");
        await run(page);
        assert.deepEqual(errors, [], "The canvas must not produce uncaught JavaScript errors");
    } finally {
        await context.close();
    }
}

async function drag(page, fromSelector, toSelector, destination = "center") {
    const source = await page.$(fromSelector);
    const target = await page.$(toSelector);
    assert.ok(source, `Drag source is present: ${fromSelector}`);
    assert.ok(target, `Drop target is present: ${toSelector}`);
    const from = await source.boundingBox();
    const to = await target.boundingBox();
    assert.ok(from && to, "Drag/drop elements must be visible");
    await page.mouse.move(from.x + from.width / 2, from.y + from.height / 2);
    await page.mouse.down();
    await page.mouse.move(from.x + from.width / 2 + 12, from.y + from.height / 2, { steps: 3 });
    await page.waitForSelector("[data-email-drag-overlay]");
    await page.mouse.move(to.x + to.width / 2, destination === "bottom" ? to.y + to.height - 10 : to.y + to.height / 2, { steps: 16 });
    await page.mouse.up();
    await page.waitForSelector("[data-email-drag-overlay]", { hidden: true });
}

async function saveConfiguration(page) {
    const count = await page.evaluate(() => window.emailCanvasSaved.length);
    // dnd-kit briefly suppresses the mouseup click after a drag. A locator waits
    // for a stable button, as a person does before clicking another control.
    await page.locator("button ::-p-text(Save template)").click();
    await page.waitForFunction((previousCount) => window.emailCanvasSaved.length > previousCount, {}, count);
    return page.evaluate(() => window.emailCanvasSaved.at(-1));
}

async function replaceInput(page, selector, value) {
    await page.click(selector, { count: 3 });
    const modifier = process.platform === "darwin" ? "Meta" : "Control";
    await page.keyboard.down(modifier);
    await page.keyboard.press("a");
    await page.keyboard.up(modifier);
    await page.keyboard.press("Backspace");
    await page.type(selector, value);
}

test("adds and reorders real paragraphs by pointer drag, then saves plain intro prose", { timeout: 120_000 }, async () =>
    withEditor(async (page) => {
        await drag(page, '[aria-label="Drag paragraph 1 to reorder"]', '[data-email-paragraph="paragraph-2"]');
        await drag(page, '[aria-label="Add a paragraph. Drag into the message or click to add."]', "[data-email-intro]", "bottom");
        await page.waitForSelector('[aria-label="Paragraph 3 text"]');
        await page.type('[aria-label="Paragraph 3 text"]', "Third paragraph added on the canvas.");
        assert.equal(
            await page.$$eval("[data-email-paragraph]", (elements) => elements.length),
            3,
            "Dragging a library item must not also trigger its click-to-add action",
        );
        const configuration = await saveConfiguration(page);
        assert.equal(
            configuration.custom_body,
            "Second paragraph with next steps.\n\nFirst paragraph for {{ student_name }}.\n\nThird paragraph added on the canvas.",
        );
        assert.equal(configuration.notification, "status_update");
        assert.equal("blocks" in configuration, false);
        assert.equal("html" in configuration, false);
    }),
);

test("reorders using the keyboard drag handle and provides move/undo/redo alternatives", { timeout: 60_000 }, async () =>
    withEditor(async (page) => {
        await page.focus('[aria-label="Drag paragraph 1 to reorder"]');
        await page.keyboard.press("Space");
        await page.waitForSelector("[data-email-drag-overlay]");
        await page.keyboard.press("ArrowDown");
        await page.waitForFunction(
            () =>
                Array.from(document.querySelectorAll("[aria-live]")).some((element) =>
                    element.textContent?.includes("moved over droppable area paragraph-2"),
                ),
            { timeout: 5000 },
        );
        await page.keyboard.press("Space");
        await page.waitForSelector("[data-email-drag-overlay]", { hidden: true });
        assert.match((await saveConfiguration(page)).custom_body, /^Second paragraph/);
        await page.click('[aria-label="Undo email edit"]');
        assert.match((await saveConfiguration(page)).custom_body, /^First paragraph/);
        await page.click('[aria-label="Redo email edit"]');
        assert.match((await saveConfiguration(page)).custom_body, /^Second paragraph/);
        await page.click('[aria-label="Move paragraph 2 up"]');
        assert.match((await saveConfiguration(page)).custom_body, /^First paragraph/);
        await page.click('[aria-label="Duplicate paragraph 1"]');
        assert.equal(await page.$$eval("[data-email-paragraph]", (elements) => elements.length), 3);
        await page.click('[aria-label="Remove paragraph 2"]');
        assert.equal(await page.$$eval("[data-email-paragraph]", (elements) => elements.length), 2);
    }),
);

test("leaves paragraph order unchanged when a keyboard pickup is dropped without movement", { timeout: 60_000 }, async () =>
    withEditor(async (page) => {
        await page.focus('[aria-label="Drag paragraph 1 to reorder"]');
        await page.keyboard.press("Space");
        await page.waitForSelector("[data-email-drag-overlay]");
        await page.keyboard.press("Space");
        await page.waitForSelector("[data-email-drag-overlay]", { hidden: true });
        assert.match((await saveConfiguration(page)).custom_body, /^First paragraph/);
        assert.ok(await page.$("::-p-text(No unsaved changes)"));
    }),
);

test("preserves an inherited intro on preview and subject-only saves until the copy is edited", { timeout: 60_000 }, async () =>
    withEditor(
        async (page) => {
            await replaceInput(page, 'input[data-email-copy-target="subject"]', "Subject-only edit");
            assert.equal((await saveConfiguration(page)).custom_body, "");
            const request = page.waitForRequest((item) => item.url().endsWith("/email-canvas-preview-fixture"));
            await page.click("button ::-p-text(Recipient preview)");
            assert.equal(JSON.parse((await request).postData()).configuration.custom_body, "");
            await page.click("button ::-p-text(Design canvas)");
            await page.click('[aria-label="Edit paragraph 1"]');
            await page.type('[aria-label="Paragraph 1 text"]', "Edited intro. ");
            assert.match((await saveConfiguration(page)).custom_body, /Edited intro/);
        },
        { query: "?builtin" },
    ),
);

test("drops personalization into the intended field and inserts clicked tokens at the caret", { timeout: 60_000 }, async () =>
    withEditor(async (page) => {
        await drag(page, '[aria-label="Insert Student name token"]', 'input[data-email-copy-target="subject"]');
        assert.equal((await saveConfiguration(page)).subject, "Application: {{ status }} {{ student_name }}");
        await replaceInput(page, 'input[data-email-copy-target="subject"]', "Hello world!");
        await page.$eval('input[data-email-copy-target="subject"]', (input) => {
            input.focus();
            input.setSelectionRange(6, 11);
        });
        await page.click('[aria-label="Insert Student name token"]');
        assert.equal((await saveConfiguration(page)).subject, "Hello {{ student_name }}!");
        await page.click('[aria-label="Edit paragraph 1"]');
        await page.$eval('[aria-label="Paragraph 1 text"]', (input) => input.setSelectionRange(input.value.length, input.value.length));
        await page.click('[aria-label="Insert School year token"]');
        assert.match((await saveConfiguration(page)).custom_body, /^First paragraph for \{\{ student_name \}\}\. \{\{ school_year \}\}/);
    }),
);

test("renders a debounced, sandboxed recipient preview and rejects stale preview responses", { timeout: 60_000 }, async () =>
    withEditor(async (page) => {
        await page.click("button ::-p-text(Recipient preview)");
        await page.waitForSelector('iframe[title="Recipient email preview"]');
        assert.equal(await page.$eval("iframe", (frame) => frame.getAttribute("sandbox")), "");
        const frame = page.frames().find((candidate) => candidate.url() === "about:srcdoc");
        assert.ok(frame);
        assert.equal(await frame.evaluate(() => window.previewScriptRan), undefined, "Preview HTML must not execute scripts");
        const slowResponse = once(previewResponses, "Slow preview");
        const slowRequest = page.waitForRequest(
            (request) => request.url().endsWith("/email-canvas-preview-fixture") && request.postData()?.includes("Slow preview"),
        );
        await replaceInput(page, 'input[data-email-copy-target="subject"]', "Slow preview");
        await slowRequest;
        await replaceInput(page, 'input[data-email-copy-target="subject"]', "Fast preview");
        await page.waitForFunction(() => document.querySelector("iframe")?.srcdoc.includes("<h1>Fast preview</h1>"));
        await slowResponse;
        assert.equal(await page.$eval("iframe", (element) => element.srcdoc.includes("Slow preview")), false);
    }),
);

test("shows preview validation failures without losing edited copy", { timeout: 60_000 }, async () =>
    withEditor(async (page) => {
        await replaceInput(page, 'input[data-email-copy-target="subject"]', "Preview error");
        await page.click("button ::-p-text(Recipient preview)");
        await page.waitForSelector('[role="alert"]');
        assert.match(await page.$eval('[role="alert"]', (element) => element.textContent), /could not be validated/);
        assert.equal(await page.$eval('input[data-email-copy-target="subject"]', (input) => input.value), "Preview error");
    }),
);

test("assessment exposes only real editable fields and keeps data and attachments protected", { timeout: 60_000 }, async () =>
    withEditor(
        async (page) => {
            assert.equal(await page.$('input[data-email-copy-target="headline"]'), null);
            assert.equal(await page.$('input[placeholder="/student"]'), null);
            assert.ok(await page.$('[aria-label="System-managed portal action"]'));
            assert.equal(await page.$$eval("[data-email-paragraph]", (elements) => elements.length), 2);
            assert.match(await page.$eval('[aria-label="Editable email canvas"]', (element) => element.textContent), /Dear Santos, Maria,/);
            assert.doesNotMatch(await page.$eval('[aria-label="Editable email canvas"]', (element) => element.textContent), /Hello Santos/);
            assert.ok(await page.$("::-p-text(Representative attachment)"));
        },
        { query: "?template=assessment" },
    ),
);

test("read-only users can preview but cannot change copy, drag blocks, or save", { timeout: 60_000 }, async () =>
    withEditor(
        async (page) => {
            assert.equal(await page.$eval('input[data-email-copy-target="subject"]', (input) => input.disabled), true);
            assert.equal(await page.$('[aria-label="Drag paragraph 1 to reorder"]'), null);
            assert.equal(await page.$eval('[aria-label="Insert Student name token"]', (button) => button.disabled), true);
            assert.equal(await page.$eval("button ::-p-text(Save template)", (button) => button.closest("button").disabled), true);
            await page.click("button ::-p-text(Recipient preview)");
            await page.waitForSelector("iframe");
            assert.deepEqual(await page.evaluate(() => window.emailCanvasSaved), []);
        },
        { query: "?readonly" },
    ),
);

for (const theme of ["light", "dark"]) {
    test(`keeps the canvas usable at 375px in ${theme} mode with reduced motion`, { timeout: 60_000 }, async () =>
        withEditor(
            async (page) => {
                assert.equal(
                    await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth),
                    true,
                    "The page must not overflow horizontally",
                );
                const library = await page.$eval('[aria-controls$="-library-content"]', (button) => button.getAttribute("aria-expanded"));
                assert.equal(library, "false", "Mobile opens on the email instead of a long palette");
                await page.click('[aria-label="Mobile email preview"]');
                await page.click('[aria-label="Edit paragraph 1"]');
                await page.waitForSelector('[aria-label="Paragraph 1 text"]');
                await page.type('[aria-label="Paragraph 1 text"]', " Mobile edit.");
                assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth), true);
                if (process.env.DELTA_SCRATCH_DIR) {
                    mkdirSync(process.env.DELTA_SCRATCH_DIR, { recursive: true });
                    await page.screenshot({ path: path.join(process.env.DELTA_SCRATCH_DIR, `email-template-canvas-mobile-${theme}.png`) });
                }
            },
            { query: `?theme=${theme}`, width: 375, height: 900, reducedMotion: true },
        ),
    );
}

test("shows a desktop email canvas with working reset, dirty state, and discard confirmation", { timeout: 60_000 }, async () =>
    withEditor(async (page) => {
        if (process.env.DELTA_SCRATCH_DIR) {
            await page.screenshot({ path: path.join(process.env.DELTA_SCRATCH_DIR, "email-template-canvas-desktop.png") });
        }
        await replaceInput(page, 'input[data-email-copy-target="subject"]', "Changed subject");
        await page.click("button ::-p-text(Reset)");
        assert.equal((await saveConfiguration(page)).subject, "Application: {{ status }}");
        await page.click('[aria-label="Undo email edit"]');
        assert.equal((await saveConfiguration(page)).subject, "Changed subject");
        await page.click("button ::-p-text(Discard & back)");
        await page.waitForSelector('[role="alertdialog"]');
        assert.equal(await page.evaluate(() => window.emailCanvasDiscarded), 0);
        await page.click("button ::-p-text(Keep editing)");
        await page.waitForSelector('[role="alertdialog"]', { hidden: true });
        assert.equal(await page.$('[role="alertdialog"]'), null);
        await page.click("button ::-p-text(Discard & back)");
        await page.click("button ::-p-text(Discard & leave)");
        assert.equal(await page.evaluate(() => window.emailCanvasDiscarded), 1);
    }),
);
