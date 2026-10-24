import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { Module } from "node:module";
import { test } from "node:test";
import { fileURLToPath } from "node:url";
import ts from "typescript";

let currentHooks;
const react = {
    useState(initial) {
        const hooks = currentHooks;
        const index = hooks.cursor++;
        if (!(index in hooks.slots)) {
            hooks.slots[index] = typeof initial === "function" ? initial() : initial;
        }
        return [
            hooks.slots[index],
            (value) => {
                hooks.slots[index] = typeof value === "function" ? value(hooks.slots[index]) : value;
            },
        ];
    },
    useRef(initial) {
        const hooks = currentHooks;
        const index = hooks.cursor++;
        if (!(index in hooks.slots)) {
            hooks.slots[index] = { current: initial };
        }
        return hooks.slots[index];
    },
    useCallback(fn, deps) {
        const hooks = currentHooks;
        const index = hooks.cursor++;
        const prev = hooks.slots[index];
        if (prev && deps && deps.every((d, i) => Object.is(d, prev.deps[i]))) {
            return prev.fn;
        }
        hooks.slots[index] = { fn, deps };
        return fn;
    },
    useEffect(fn, deps) {
        const hooks = currentHooks;
        const index = hooks.cursor++;
        const prev = hooks.slots[index];
        if (!prev || !deps || deps.some((d, i) => !Object.is(d, prev.deps[i]))) {
            if (prev?.cleanup) prev.cleanup();
            const cleanup = fn();
            hooks.slots[index] = { deps, cleanup };
        }
    },
};

const toasts = [];
globalThis.document = {
    querySelector: () => ({ content: "csrf_test_token" }),
};
if (!globalThis.URL.createObjectURL) {
    globalThis.URL.createObjectURL = () => "blob:test-preview";
    globalThis.URL.revokeObjectURL = () => {};
}

function loadHookModule() {
    const url = new URL("../../resources/js/components/ai/use-ai-chat.ts", import.meta.url);
    const filename = fileURLToPath(url);
    const { outputText, diagnostics } = ts.transpileModule(readFileSync(url, "utf8"), {
        fileName: filename,
        reportDiagnostics: true,
        compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2022 },
    });
    assert.deepEqual(diagnostics, []);
    const mod = new Module(filename);
    mod.require = (specifier) => {
        if (specifier === "react") return react;
        if (specifier === "sonner") {
            return {
                toast: {
                    info: (msg) => toasts.push({ kind: "info", msg }),
                    error: (msg) => toasts.push({ kind: "error", msg }),
                    success: (msg) => toasts.push({ kind: "success", msg }),
                },
            };
        }
        if (specifier === "@/actions/App/Http/Controllers/AiChatController") {
            return {
                chat: { url: () => "/ai/chat" },
                messages: { url: (id) => `/ai/conversations/${id}/messages` },
            };
        }
        if (specifier === "@/actions/App/Http/Controllers/AdministratorAiController") {
            return {
                showConversation: { url: (id) => `/administrators/ai/conversations/${id}` },
            };
        }
        throw new Error(`Unexpected specifier: ${specifier}`);
    };
    mod._compile(outputText, filename);
    return mod.exports;
}

const { useAiChat: runAiChatHook, chatPageContext } = loadHookModule();

function createHookRunner(initialProps) {
    const hooks = { slots: [], cursor: 0 };
    let props = initialProps;
    const render = (nextProps) => {
        if (nextProps) props = { ...props, ...nextProps };
        currentHooks = hooks;
        hooks.cursor = 0;
        try {
            return runAiChatHook(props);
        } finally {
            currentHooks = undefined;
        }
    };
    return { render };
}

function sseResponse(events, { includeDone = true } = {}) {
    const encoder = new TextEncoder();
    const lines = events.map((ev) => `data: ${typeof ev === "string" ? ev : JSON.stringify(ev)}\n\n`);
    if (includeDone) lines.push("data: [DONE]\n\n");
    let idx = 0;
    return new Response(
        new ReadableStream({
            pull(controller) {
                if (idx < lines.length) {
                    controller.enqueue(encoder.encode(lines[idx++]));
                } else {
                    controller.close();
                }
            },
        }),
        { status: 200, headers: { "Content-Type": "text/event-stream" } },
    );
}

test("chatPageContext strips query strings and fragments while rejecting non-path URLs", () => {
    assert.deepEqual(chatPageContext("/administrators/students/42?tab=ledger#top"), {
        page: "/administrators/students/42",
    });
    assert.equal(chatPageContext("//evil.example/path"), undefined);
});

test("regenerateAssistant sends replay_message_id and replaces history with server branch without duplicating user message", async () => {
    const requests = [];
    const conv1 = "01966000-0000-7000-8000-000000000001";
    const conv2 = "01966000-0000-7000-8000-000000000002";
    const userMsg1 = "01966000-0000-7000-8000-000000000011";
    const asstMsg1 = "01966000-0000-7000-8000-000000000012";
    const userMsg2 = "01966000-0000-7000-8000-000000000021";
    const asstMsg2 = "01966000-0000-7000-8000-000000000022";

    globalThis.fetch = async (url, init) => {
        const body = JSON.parse(init.body);
        requests.push({ url, body });
        if (!body.replay_message_id) {
            return sseResponse([
                { type: "text-delta", delta: "First answer" },
                {
                    type: "conversation",
                    conversationId: conv1,
                    title: "Audit",
                    userMessageId: userMsg1,
                    assistantMessageId: asstMsg1,
                    run: {
                        request_id: body.request_id,
                        status: "completed",
                        agent: "admin_executive",
                        provider: "openai",
                        model: "gpt-4o",
                        options: { context: { page: "/administrators/students/7" } },
                    },
                    messages: [
                        { id: userMsg1, role: "user", content: "Audit student 7" },
                        {
                            id: asstMsg1,
                            role: "assistant",
                            content: "First answer",
                            run: {
                                request_id: body.request_id,
                                status: "completed",
                                agent: "admin_executive",
                                provider: "openai",
                                model: "gpt-4o",
                                options: { context: { page: "/administrators/students/7" } },
                            },
                        },
                    ],
                },
            ]);
        }

        return sseResponse([
            { type: "text-delta", delta: "Regenerated answer" },
            {
                type: "conversation",
                conversationId: conv2,
                title: "Audit (regenerated)",
                userMessageId: userMsg2,
                assistantMessageId: asstMsg2,
                run: {
                    request_id: body.request_id,
                    status: "completed",
                    agent: "admin_executive",
                    provider: "openai",
                    model: "gpt-4o",
                },
                messages: [
                    { id: userMsg2, role: "user", content: "Audit student 7" },
                    { id: asstMsg2, role: "assistant", content: "Regenerated answer" },
                ],
            },
        ]);
    };

    const { render } = createHookRunner({
        agent: "admin_executive",
        endpoint: "/administrators/ai/chat",
        context: { page: "/administrators/students/7?secret=1" },
    });

    let api = render();
    await api.sendPrompt("Audit student 7", [], { provider: "openai", model: "gpt-4o" });
    api = render();

    assert.equal(api.conversationId, conv1);
    assert.equal(api.messages.length, 2);
    assert.equal(requests[0].body.context.page, "/administrators/students/7");

    await api.regenerateAssistant(asstMsg1);
    api = render();

    assert.equal(requests.length, 2);
    assert.equal(requests[1].body.replay_message_id, asstMsg1);
    assert.equal(requests[1].body.conversation_id, conv1);
    assert.equal(api.conversationId, conv2);
    assert.equal(api.messages.filter((m) => m.role === "user").length, 1);
    assert.equal(api.messages[1].content, "Regenerated answer");
});

test("multi-action approvals collect all decisions and preserve originating run agent and model across tab changes", async () => {
    const requests = [];
    const convId = "01966000-0000-7000-8000-000000000050";
    const userMsg = "01966000-0000-7000-8000-000000000051";
    const asstMsg = "01966000-0000-7000-8000-000000000052";
    const contMsg = "01966000-0000-7000-8000-000000000053";

    globalThis.fetch = async (url, init) => {
        const body = JSON.parse(init.body);
        requests.push({ url, body });
        return sseResponse([
            { type: "text-delta", delta: "Completed approved actions." },
            {
                type: "conversation",
                conversationId: convId,
                userMessageId: userMsg,
                assistantMessageId: contMsg,
                run: {
                    request_id: body.request_id,
                    status: "completed",
                    agent: "registrar_auditor",
                    provider: "anthropic",
                    model: "claude-sonnet",
                },
                messages: [
                    { id: userMsg, role: "user", content: "Transfer and update student" },
                    { id: contMsg, role: "assistant", content: "Completed approved actions." },
                ],
            },
        ]);
    };

    const { render } = createHookRunner({
        agent: "registrar_auditor",
        endpoint: "/administrators/ai/chat",
        initialConversationId: convId,
        initialMessages: [
            { id: userMsg, role: "user", content: "Transfer and update student" },
            {
                id: asstMsg,
                role: "assistant",
                content: "",
                pendingApprovals: [
                    { id: "call_1", tool: "ManageStudentTool", reason: "Update student" },
                    { id: "call_2", tool: "ManageClassEnrollmentTool", reason: "Transfer section" },
                ],
                run: {
                    request_id: "01966000-0000-7000-8000-000000000099",
                    status: "paused",
                    agent: "registrar_auditor",
                    provider: "anthropic",
                    model: "claude-sonnet",
                    options: { thinking: true, search: false, auto_approve: false, context: { page: "/administrators/students/9" } },
                },
            },
        ],
    });

    // Switch active agent tab in UI before approving
    let api = render({ agent: "bursar_finance" });

    // Approving only the first action must NOT fire a network request yet
    await api.submitDecision("call_1", "approve");
    api = render();
    assert.equal(requests.length, 0);
    assert.deepEqual(api.approvalDecisions.call_1, { action: "approve" });

    // Rejecting the second action completes the batch and sends both decisions with the originating agent/model
    await api.submitDecision("call_2", "reject", "Keep current section");
    api = render();
    assert.equal(requests.length, 1);
    assert.equal(requests[0].body.agent, "registrar_auditor");
    assert.equal(requests[0].body.provider, "anthropic");
    assert.equal(requests[0].body.model, "claude-sonnet");
    assert.deepEqual(requests[0].body.decisions, {
        call_1: { action: "approve" },
        call_2: { action: "reject", result: "Keep current section" },
    });
});

test("retryLastRequest preserves file-only uploads and reuses request_id when outcome is unknown", async () => {
    const requests = [];
    let attemptCount = 0;

    globalThis.fetch = async (url, init) => {
        attemptCount++;
        requests.push({ url, body: init.body });
        if (attemptCount === 1) {
            return new Response(JSON.stringify({ message: "Gateway timeout" }), { status: 502 });
        }
        return sseResponse([{ type: "text-delta", delta: "Parsed workbook successfully." }]);
    };

    const { render } = createHookRunner({
        agent: "admin_executive",
        endpoint: "/administrators/ai/chat",
    });

    const file = new File(["col1,col2\n1,2"], "roster.csv", { type: "text/csv" });
    let api = render();
    await api.sendPrompt("", [file], { model: "gpt-4o" });
    api = render();

    assert.ok(api.lastError);
    assert.equal(requests.length, 1);
    const firstForm = requests[0].body;
    assert.ok(firstForm instanceof FormData);
    const firstRequestId = firstForm.get("request_id");
    assert.equal(firstForm.getAll("attachments[]").length, 1);

    await api.retryLastRequest();
    api = render();

    assert.equal(requests.length, 2);
    const secondForm = requests[1].body;
    assert.equal(secondForm.get("request_id"), firstRequestId);
    assert.equal(secondForm.getAll("attachments[]").length, 1);
    assert.equal(api.lastError, null);
});

test("clearChat during streaming invalidates the in-flight request so late conversation events cannot repopulate state", async () => {
    let releaseStream;
    const convA = "01966000-0000-7000-8000-000000000088";

    globalThis.fetch = async () =>
        new Response(
            new ReadableStream({
                start(controller) {
                    releaseStream = () => {
                        const encoder = new TextEncoder();
                        controller.enqueue(
                            encoder.encode(
                                `data: ${JSON.stringify({ type: "conversation", conversationId: convA, title: "Stale" })}\n\ndata: [DONE]\n\n`,
                            ),
                        );
                        controller.close();
                    };
                },
            }),
            { status: 200, headers: { "Content-Type": "text/event-stream" } },
        );

    const { render } = createHookRunner({
        agent: "admin_executive",
        endpoint: "/administrators/ai/chat",
    });

    let api = render();
    const pending = api.sendPrompt("Slow query");
    api = render();
    assert.equal(api.isLoading, true);

    api.clearChat();
    api = render();
    assert.equal(api.isLoading, false);
    assert.equal(api.conversationId, undefined);
    assert.equal(api.messages.length, 0);

    releaseStream();
    await pending;
    api = render();
    assert.equal(api.conversationId, undefined);
    assert.equal(api.messages.length, 0);
});
