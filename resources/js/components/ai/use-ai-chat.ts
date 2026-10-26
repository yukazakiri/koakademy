import { chat, messages as showPortalConversation } from "@/actions/App/Http/Controllers/AiChatController";
import { showConversation } from "@/actions/App/Http/Controllers/AdministratorAiController";
import * as React from "react";
import { toast } from "sonner";
import type { PendingToolApproval } from "./approval-card";

export type AgentRoleKey = "admin_executive" | "student_advisor" | "faculty_copilot" | "registrar_auditor" | "bursar_finance" | "campus_support";

export interface ChatAttachment {
    name: string;
    size: number;
    type: string;
    previewUrl?: string;
}

export interface ToolInvocation {
    id: string;
    toolName: string;
    state: "input-streaming" | "input-available" | "output-available" | "output-error";
    input?: Record<string, unknown>;
    output?: Record<string, unknown> | string;
    errorText?: string;
}

export interface CitationSource { title: string; url: string }
export interface PromptOptions {
    agent?: AgentRoleKey;
    model?: string;
    provider?: string;
    supportsDocuments?: boolean;
    thinking?: boolean;
    search?: boolean;
    autoApprove?: boolean;
    context?: { page: string };
}
export interface ChatRun {
    id?: string;
    request_id: string;
    status: "running" | "paused" | "completed" | "failed";
    agent: AgentRoleKey;
    provider?: string;
    model?: string;
    options?: { thinking?: boolean; search?: boolean; auto_approve?: boolean; context?: { page: string } };
}
export interface ChatMessage {
    id: string;
    role: "user" | "assistant" | "system";
    content: string;
    reasoning?: string;
    toolCalls?: ToolInvocation[];
    sources?: CitationSource[];
    attachments?: ChatAttachment[];
    pendingApprovals?: PendingToolApproval[];
    createdAt?: Date;
    run?: ChatRun;
    requestOptions?: PromptOptions;
}
export interface UseAiChatOptions {
    agent: AgentRoleKey;
    endpoint?: string;
    initialConversationId?: string;
    initialMessages?: ChatMessage[];
    persistenceKey?: string;
    context?: { page: string };
    onFinish?: (message: ChatMessage) => void;
    onError?: (error: Error) => void;
    onConversationCreated?: (conversationId: string, title?: string) => void;
}
export interface ApprovalDecision { action: "approve" | "reject"; result?: string }
type ChatError = { title: string; message: string; retryable?: boolean };
interface RequestSnapshot {
    readonly requestId: string;
    readonly conversationId?: string;
    readonly replayMessageId?: string;
    readonly content: string;
    readonly files: readonly File[];
    readonly options: Readonly<PromptOptions>;
    readonly decisions?: Readonly<Record<string, ApprovalDecision>>;
}
interface Attempt {
    snapshot: RequestSnapshot;
    userId?: string;
    assistantId: string;
    conversationId?: string;
    run?: ChatRun;
    canonical?: ChatMessage;
}
interface Operation { generation: number; controller: AbortController; attempt?: Attempt }

const persistedId = (id?: string): id is string => !!id && /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i.test(id);
const localId = () => `local_${crypto.randomUUID()}`;

/** Route paths only: queries and fragments can contain credentials or unrelated private filters. */
export function chatPageContext(url?: string): { page: string } | undefined {
    const path = (url || (typeof window !== "undefined" ? window.location.pathname : "")).split(/[?#]/, 1)[0];
    return path.startsWith("/") && !path.startsWith("//") ? { page: path } : undefined;
}
function optionsFor(message: ChatMessage): PromptOptions | undefined {
    if (!message.run) return message.requestOptions;
    const { agent, provider, model, options } = message.run;
    return { agent, provider, model, thinking: options?.thinking, search: options?.search, autoApprove: options?.auto_approve, context: options?.context };
}
function snapshotOf(snapshot: RequestSnapshot): RequestSnapshot {
    const context = chatPageContext(snapshot.options.context?.page);
    return Object.freeze({
        ...snapshot,
        files: Object.freeze([...snapshot.files]),
        options: Object.freeze({ ...snapshot.options, context: context ? Object.freeze(context) : undefined }),
        decisions: snapshot.decisions ? Object.freeze(Object.fromEntries(Object.entries(snapshot.decisions).map(([id, decision]) => [id, Object.freeze({ ...decision })]))) : undefined,
    });
}

export function useAiChat({ agent, endpoint, initialConversationId, initialMessages = [], persistenceKey, context, onFinish, onError, onConversationCreated }: UseAiChatOptions) {
    const [messagesState, setMessagesState] = React.useState<ChatMessage[]>(() => {
        try {
            const saved = persistenceKey && typeof window !== "undefined" ? sessionStorage.getItem(persistenceKey) : null;
            const parsed = saved ? JSON.parse(saved) : null;
            return Array.isArray(parsed) ? parsed : initialMessages;
        } catch { return initialMessages; }
    });
    const [conversationState, setConversationState] = React.useState<string | undefined>(() => {
        try { return (persistenceKey && typeof window !== "undefined" ? sessionStorage.getItem(`${persistenceKey}_conv_id`) : null) || initialConversationId; }
        catch { return initialConversationId; }
    });
    const [input, setInput] = React.useState("");
    const [isLoading, setIsLoading] = React.useState(false);
    const [autoApprove, setAutoApproveState] = React.useState(() => {
        try { return !!persistenceKey && typeof window !== "undefined" && sessionStorage.getItem(`${persistenceKey}_auto_approve`) === "true"; }
        catch { return false; }
    });
    const [lastError, setLastError] = React.useState<ChatError | null>(null);
    const [lastPrompt, setLastPrompt] = React.useState("");
    const [approvalDecisions, setApprovalDecisions] = React.useState<Record<string, ApprovalDecision>>({});
    const [approvalsLocked, setApprovalsLocked] = React.useState(false);
    const messagesRef = React.useRef(messagesState);
    const conversationRef = React.useRef(conversationState);
    const generationRef = React.useRef(0);
    const activeRef = React.useRef<Operation | null>(null);
    const failedRef = React.useRef<Attempt | null>(null);
    const decisionsRef = React.useRef<Record<string, ApprovalDecision>>({});
    const previewsRef = React.useRef(new Set<string>());
    const callbacks = React.useRef({ onFinish, onError, onConversationCreated });
    callbacks.current = { onFinish, onError, onConversationCreated };
    const targetUrl = endpoint || chat.url();

    // Mirror synchronously so two callbacks before React renders still see the latest owner/history.
    const setMessages = React.useCallback((value: React.SetStateAction<ChatMessage[]>) => {
        const next = typeof value === "function" ? value(messagesRef.current) : value;
        messagesRef.current = next;
        setMessagesState(next);
    }, []);
    const setConversationId = React.useCallback((value: React.SetStateAction<string | undefined>) => {
        const next = typeof value === "function" ? value(conversationRef.current) : value;
        conversationRef.current = next;
        setConversationState(next);
    }, []);
    const owns = React.useCallback((op: Operation) => activeRef.current === op && generationRef.current === op.generation && !op.controller.signal.aborted, []);
    const invalidate = React.useCallback(() => {
        generationRef.current++;
        activeRef.current?.controller.abort();
        activeRef.current = null;
        setIsLoading(false);
    }, []);
    const releaseFiles = React.useCallback(() => {
        failedRef.current = null;
        previewsRef.current.forEach((url) => URL.revokeObjectURL(url));
        previewsRef.current.clear();
    }, []);
    const resetDecisions = React.useCallback(() => {
        decisionsRef.current = {};
        setApprovalDecisions({});
    }, []);
    const begin = React.useCallback((attempt?: Attempt) => {
        invalidate();
        const op = { generation: generationRef.current, controller: new AbortController(), attempt };
        activeRef.current = op;
        setIsLoading(true);
        return op;
    }, [invalidate]);
    const finish = React.useCallback((op: Operation) => {
        if (!owns(op)) return;
        activeRef.current = null;
        setIsLoading(false);
    }, [owns]);

    React.useEffect(() => {
        if (!persistenceKey || typeof window === "undefined") return;
        try {
            sessionStorage.setItem(persistenceKey, JSON.stringify(messagesState));
            if (conversationState) sessionStorage.setItem(`${persistenceKey}_conv_id`, conversationState);
            else sessionStorage.removeItem(`${persistenceKey}_conv_id`);
        } catch { /* Optional browser persistence. Files are held only in memory until success/new/load/unmount. */ }
    }, [messagesState, conversationState, persistenceKey]);
    React.useEffect(() => () => { invalidate(); releaseFiles(); }, [invalidate, releaseFiles]);
    const previousAgent = React.useRef(agent);
    React.useEffect(() => {
        if (previousAgent.current !== agent) invalidate();
        previousAgent.current = agent;
    }, [agent, invalidate]);
    const previousInitial = React.useRef(initialConversationId);
    React.useEffect(() => {
        if (previousInitial.current !== initialConversationId) {
            invalidate(); releaseFiles(); resetDecisions();
            setConversationId(initialConversationId);
            setMessages(initialMessages);
            setLastError(null); setApprovalsLocked(false);
        }
        previousInitial.current = initialConversationId;
    }, [initialConversationId, initialMessages, invalidate, releaseFiles, resetDecisions, setConversationId, setMessages]);

    const applySnapshot = React.useCallback((data: { messages?: ChatMessage[]; conversationId?: string; conversation?: { id: string }; userMessageId?: string; assistantMessageId?: string; run?: ChatRun; title?: string }, op: Operation) => {
        if (!owns(op)) return;
        const attempt = op.attempt;
        const id = data.conversationId || data.conversation?.id || attempt?.conversationId;
        const oldUserId = attempt?.userId;
        const oldAssistantId = attempt?.assistantId;
        if (attempt) {
            attempt.conversationId = id;
            attempt.userId = data.userMessageId || attempt.userId;
            attempt.assistantId = data.assistantMessageId || attempt.assistantId;
            if (data.run) attempt.run = data.run;
        }
        if (id) setConversationId(id);
        if (Array.isArray(data.messages)) {
            const canonical = data.messages.map((message) => ({ ...message, requestOptions: optionsFor(message) || (message.id === attempt?.assistantId ? attempt?.snapshot.options : undefined) }));
            if (attempt) {
                // Failed duplicate requests may return a snapshot before explicit message IDs are available.
                const owned = canonical.filter((m) => m.run?.request_id === attempt.snapshot.requestId);
                const user = owned.find((m) => m.role === "user");
                const assistant = owned.filter((m) => m.role === "assistant").at(-1);
                if (user) attempt.userId = user.id;
                if (assistant) { attempt.assistantId = assistant.id; attempt.canonical = assistant; }
                else attempt.canonical = canonical.find((m) => m.id === attempt.assistantId);
            }
            setMessages(canonical);
        } else if (attempt) {
            setMessages((prev) => prev.map((m) => m.id === oldUserId ? { ...m, id: attempt.userId! } : m.id === oldAssistantId ? { ...m, id: attempt.assistantId, run: attempt.run } : m));
        }
    }, [owns, setConversationId, setMessages]);

    const conversationUrl = React.useCallback(
        (id: string) => (targetUrl === chat.url() ? showPortalConversation.url(id) : showConversation.url(id)),
        [targetUrl],
    );

    const reconcile = React.useCallback(async (op: Operation) => {
        const id = op.attempt?.conversationId || conversationRef.current;
        if (!id || !owns(op)) return false;
        try {
            const response = await fetch(conversationUrl(id), { headers: { "X-Requested-With": "XMLHttpRequest" }, signal: op.controller.signal });
            if (!response.ok) return false;
            const data = await response.json();
            if (!owns(op)) return false;
            // A latest run belonging to a different request cannot establish this request's execution status.
            const run = data.run?.request_id === op.attempt?.snapshot.requestId ? data.run : undefined;
            applySnapshot({ ...data, run }, op);
            const ownMessage = data.messages?.find((m: ChatMessage) => m.run?.request_id === op.attempt?.snapshot.requestId);
            if (ownMessage?.run && op.attempt) op.attempt.run = ownMessage.run;
            setApprovalsLocked(false);
            resetDecisions();
            return true;
        } catch { return false; }
    }, [owns, applySnapshot, resetDecisions, conversationUrl]);

    const readStream = React.useCallback(async (response: Response, op: Operation) => {
        if (!response.body || !op.attempt) throw new Error("No response body received from chat stream.");
        const attempt = op.attempt;
        const reader = response.body.getReader();
        const decoder = new TextDecoder();
        let content = "", reasoning = "", buffer = "", done = false, streamError = "";
        const tools = new Map<string, ToolInvocation>();
        const approvals = new Map<string, PendingToolApproval>();
        const sources: CitationSource[] = [];
        const update = () => {
            if (!owns(op)) return;
            setMessages((prev) => prev.map((m) => m.id === attempt.assistantId ? { ...m, content, reasoning: reasoning || undefined, toolCalls: tools.size ? [...tools.values()] : undefined, sources: sources.length ? [...sources] : undefined, pendingApprovals: approvals.size ? [...approvals.values()] : undefined, requestOptions: attempt.snapshot.options, run: attempt.run } : m));
        };
        const consume = (line: string) => {
            if (!owns(op)) return;
            const trimmed = line.trim();
            if (trimmed.startsWith("data:")) {
                const payload = trimmed.slice(5).trim();
                if (payload === "[DONE]") { done = true; return; }
                let parsed;
                try { parsed = JSON.parse(payload); } catch { content += payload; update(); return; }
                const type = parsed.type?.replaceAll("_", "-");
                if (type === "conversation") {
                    applySnapshot(parsed, op);
                    if (attempt.canonical) {
                        content = attempt.canonical.content || content;
                        reasoning = attempt.canonical.reasoning || reasoning;
                        tools.clear(); attempt.canonical.toolCalls?.forEach((t) => tools.set(t.id, t));
                        approvals.clear(); attempt.canonical.pendingApprovals?.forEach((a) => approvals.set(a.id, a));
                    }
                    if (owns(op) && attempt.conversationId) callbacks.current.onConversationCreated?.(attempt.conversationId, parsed.title);
                    return;
                }
                if (type === "text-delta") content += parsed.delta ?? parsed.text ?? "";
                else if (type === "reasoning-delta") reasoning += parsed.delta ?? parsed.text ?? "";
                else if (type === "tool-call") {
                    const id = parsed.toolCallId || parsed.id;
                    if (id) tools.set(id, { id, toolName: parsed.toolName || parsed.name || "Tool", state: "input-available", input: parsed.input || parsed.arguments });
                } else if (type === "tool-result") {
                    const id = parsed.toolCallId || parsed.id;
                    if (id) tools.set(id, { ...tools.get(id), id, toolName: tools.get(id)?.toolName || parsed.toolName || "Tool", state: parsed.successful === false ? "output-error" : "output-available", output: parsed.output, errorText: parsed.error });
                } else if (type === "citation" && parsed.url && !sources.some((s) => s.url === parsed.url)) sources.push({ title: parsed.title || parsed.url, url: parsed.url });
                else if (type === "tool-approval-request") {
                    const id = parsed.approvalId || parsed.toolCallId || parsed.id;
                    if (id) approvals.set(id, { id, tool: parsed.tool || parsed.toolName || "Tool Execution", reason: parsed.reason, arguments: parsed.arguments });
                } else if (type === "error") streamError = parsed.errorText || parsed.message || "An error occurred with the AI provider.";
                else if (type === "run" && parsed.run) attempt.run = parsed.run;
                update();
            } else if (trimmed.startsWith("0:")) {
                try { content += JSON.parse(trimmed.slice(2)); } catch { content += trimmed.slice(2); }
                update();
            } else if (trimmed.startsWith("a:")) {
                try { const parsed = JSON.parse(trimmed.slice(2)); if (parsed.approval?.id) approvals.set(parsed.approval.id, parsed.approval); } catch { /* Ignore protocol metadata. */ }
                update();
            }
        };
        try {
            while (owns(op)) {
                const chunk = await reader.read();
                if (!owns(op)) { void reader.cancel().catch(() => {}); return; }
                if (chunk.done) break;
                buffer += decoder.decode(chunk.value, { stream: true });
                const lines = buffer.split("\n"); buffer = lines.pop() || "";
                lines.forEach(consume);
            }
            buffer += decoder.decode();
            if (buffer.trim()) consume(buffer);
        } finally { reader.releaseLock(); }
        if (!owns(op)) return;
        if (!done && !streamError) streamError = "The connection ended before the response completed. Check the saved result before retrying.";
        if (!content.trim() && !tools.size && !approvals.size && !streamError && attempt.run?.status !== "completed") streamError = "The assistant stopped without producing a response.";
        if (!content.trim() && !tools.size && !approvals.size && !streamError) streamError = "The assistant stopped without producing a response.";
        if (streamError && !content.trim()) content = `⚠️ ${streamError}`;
        update(); // Commit synthesized EOF/empty errors as well as streamed content.
        if (streamError) throw new Error(streamError);
        const finalMessage = messagesRef.current.find((m) => m.id === attempt.assistantId);
        if (finalMessage) callbacks.current.onFinish?.(finalMessage);
    }, [owns, setMessages, applySnapshot]);

    const execute = React.useCallback(async (attempt: Attempt) => {
        if (activeRef.current) return;
        const op = begin(attempt);
        failedRef.current = attempt;
        setLastError(null);
        const snapshot = attempt.snapshot;
        if (snapshot.decisions) setApprovalsLocked(true);
        const payload: Record<string, unknown> = {
            request_id: snapshot.requestId, conversation_id: snapshot.conversationId,
            replay_message_id: snapshot.replayMessageId, agent: snapshot.options.agent,
            provider: snapshot.options.provider, model: snapshot.options.model,
            thinking: snapshot.options.thinking, search: snapshot.options.search,
            auto_approve: snapshot.options.autoApprove, context: snapshot.options.context,
            ...(snapshot.decisions ? { decisions: snapshot.decisions } : { message: snapshot.content }),
        };
        const headers: Record<string, string> = { Accept: "text/event-stream, text/plain", "X-Requested-With": "XMLHttpRequest", "X-CSRF-TOKEN": (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || "" };
        let body: BodyInit;
        if (snapshot.files.length) {
            const form = new FormData();
            Object.entries(payload).forEach(([key, value]) => {
                if (value === undefined) return;
                if (key === "context") form.append("context[page]", snapshot.options.context!.page);
                else form.append(key, typeof value === "boolean" ? (value ? "1" : "0") : String(value));
            });
            snapshot.files.forEach((file) => form.append("attachments[]", file));
            body = form;
        } else { headers["Content-Type"] = "application/json"; body = JSON.stringify(payload); }
        try {
            const response = await fetch(targetUrl, { method: "POST", headers, body, signal: op.controller.signal });
            if (!owns(op)) return;
            if (!response.ok) {
                const data = await response.json().catch(() => ({}));
                if (!owns(op)) return;
                if (data.messages || data.run) applySnapshot(data, op);
                throw new Error(data.message || `Server error (${response.status})`);
            }
            await readStream(response, op);
            if (!owns(op)) return;
            failedRef.current = null;
            // Preview URLs remain valid for the visible transcript; File objects are released here.
            resetDecisions(); setApprovalsLocked(false);
        } catch (error) {
            if (!owns(op)) return;
            const reconciled = await reconcile(op);
            if (!owns(op)) return;
            const message = error instanceof Error ? error.message : "Failed to communicate with AI agent.";
            const knownFinished = attempt.run?.status === "completed" || attempt.run?.status === "paused";
            if (knownFinished) {
                failedRef.current = null;
                setLastError({ title: "Saved result recovered", message: "The stream was interrupted. The saved conversation has been restored.", retryable: false });
            } else {
                const decisionFailure = !!snapshot.decisions;
                const retryable = !decisionFailure || !attempt.run || attempt.run.status === "running";
                setLastError({ title: "AI Generation Error", message: decisionFailure ? `${message} ${reconciled ? "Saved tool state has been refreshed." : "Tool execution status is unknown. Retry checks the same request without repeating it."}` : message, retryable });
                if (decisionFailure && !reconciled) setApprovalsLocked(true);
            }
            setMessages((prev) => prev.map((m) => m.id === attempt.assistantId && !m.content.trim() && !m.pendingApprovals?.length ? { ...m, content: `⚠️ ${message}` } : m));
            callbacks.current.onError?.(error instanceof Error ? error : new Error(message));
        } finally { finish(op); }
    }, [targetUrl, begin, owns, applySnapshot, readStream, reconcile, finish, resetDecisions, setMessages]);

    const sendPrompt = React.useCallback(async (content: string, files?: File[], options?: PromptOptions) => {
        if (activeRef.current || (!content.trim() && !files?.length)) return;
        releaseFiles(); resetDecisions(); setApprovalsLocked(false);
        const requestOptions = { ...options, agent: options?.agent || agent, autoApprove: options?.autoApprove ?? autoApprove, context: chatPageContext(options?.context?.page || context?.page) };
        const snapshot = snapshotOf({ requestId: crypto.randomUUID(), conversationId: conversationRef.current, content: content.trim() || "Please analyze the attached file(s).", files: files || [], options: requestOptions });
        const userId = localId(), assistantId = localId();
        const attachments = snapshot.files.map((file) => {
            const previewUrl = file.type.startsWith("image/") || file.type === "application/pdf" ? URL.createObjectURL(file) : undefined;
            if (previewUrl) previewsRef.current.add(previewUrl);
            return { name: file.name, size: file.size, type: file.type, previewUrl };
        });
        setMessages((prev) => [...prev, { id: userId, role: "user", content: snapshot.content, attachments, requestOptions, createdAt: new Date() }, { id: assistantId, role: "assistant", content: "", requestOptions }]);
        setInput(""); setLastPrompt(snapshot.content);
        await execute({ snapshot, userId, assistantId, conversationId: snapshot.conversationId });
    }, [agent, autoApprove, context?.page, releaseFiles, resetDecisions, setMessages, execute]);

    const replay = React.useCallback(async (messageId: string, options?: PromptOptions) => {
        if (activeRef.current) return;
        const transcript = messagesRef.current;
        const index = transcript.findIndex((m) => m.id === messageId);
        const target = transcript[index];
        if (!target || !persistedId(target.id) || !conversationRef.current) { toast.info("Wait for the saved message before replaying this turn."); return; }
        const user = target.role === "user" ? target : transcript.slice(0, index).reverse().find((m) => m.role === "user");
        if (!user) return;
        releaseFiles(); resetDecisions(); setApprovalsLocked(false);
        const origin = optionsFor(target) || optionsFor(user);
        const snapshot = snapshotOf({ requestId: crypto.randomUUID(), conversationId: conversationRef.current, replayMessageId: target.id, content: user.content, files: [], options: { ...origin, ...options, agent: options?.agent || origin?.agent || agent, autoApprove: options?.autoApprove ?? origin?.autoApprove ?? autoApprove, context: origin?.context || chatPageContext(context?.page) } });
        const assistantId = localId();
        // The server chooses the branch boundary/content/attachments. Keep saved history until it returns.
        setMessages((prev) => [...prev, { id: assistantId, role: "assistant", content: "", requestOptions: snapshot.options }]);
        await execute({ snapshot, userId: user.id, assistantId, conversationId: snapshot.conversationId });
    }, [agent, autoApprove, context?.page, releaseFiles, resetDecisions, setMessages, execute]);
    const resendUserMessage = React.useCallback(async (id: string, options?: PromptOptions) => {
        if (messagesRef.current.find((m) => m.id === id)?.role === "user") await replay(id, options);
    }, [replay]);
    const regenerateAssistant = React.useCallback(async (id: string, options?: PromptOptions) => {
        if (messagesRef.current.find((m) => m.id === id)?.role === "assistant") await replay(id, options);
    }, [replay]);

    const retryLastRequest = React.useCallback(async () => {
        const failed = failedRef.current;
        if (!failed || activeRef.current) return;
        if (!failed.snapshot.decisions && failed.run?.status === "failed" && persistedId(failed.userId) && failed.conversationId) {
            // A known failed attempt can fork at the canonical user; unknown outcomes keep the dedup ID.
            const snapshot = snapshotOf({ ...failed.snapshot, requestId: crypto.randomUUID(), conversationId: failed.conversationId, replayMessageId: failed.userId, files: [] });
            const assistantId = localId();
            setMessages((prev) => [...prev, { id: assistantId, role: "assistant", content: "", requestOptions: snapshot.options }]);
            await execute({ snapshot, userId: failed.userId, assistantId, conversationId: failed.conversationId });
        } else await execute({ ...failed, run: undefined, canonical: undefined });
    }, [execute, setMessages]);

    const submitBatch = React.useCallback(async (target: ChatMessage, decisions: Record<string, ApprovalDecision>) => {
        if (activeRef.current || approvalsLocked || !conversationRef.current) return;
        const options = optionsFor(target);
        if (!options?.agent) { setLastError({ title: "Reload approval details", message: "The originating run settings are missing. Reload this conversation before deciding.", retryable: false }); return; }
        resetDecisions();
        const snapshot = snapshotOf({ requestId: crypto.randomUUID(), conversationId: conversationRef.current, content: "", files: [], options, decisions });
        const assistantId = localId();
        setMessages((prev) => [...prev, { id: assistantId, role: "assistant", content: "", requestOptions: options }]);
        await execute({ snapshot, assistantId, conversationId: snapshot.conversationId });
    }, [approvalsLocked, execute, resetDecisions, setMessages]);
    const submitDecision = React.useCallback(async (id: string, action: "approve" | "reject", result?: string) => {
        if (activeRef.current || approvalsLocked) return;
        const target = messagesRef.current.slice().reverse().find((m) => m.pendingApprovals?.some((a) => a.id === id));
        if (!target) return;
        const selected = { ...decisionsRef.current, [id]: { action, ...(result ? { result } : {}) } };
        decisionsRef.current = selected; setApprovalDecisions(selected);
        const pending = target.pendingApprovals!;
        if (pending.every((a) => selected[a.id])) await submitBatch(target, Object.fromEntries(pending.map((a) => [a.id, selected[a.id]])));
    }, [approvalsLocked, submitBatch]);
    const submitAllDecisions = React.useCallback(async (action: "approve" | "reject") => {
        const target = messagesRef.current.slice().reverse().find((m) => m.pendingApprovals?.length);
        if (target) await submitBatch(target, Object.fromEntries(target.pendingApprovals!.map((a) => [a.id, { action }])));
    }, [submitBatch]);

    const stop = React.useCallback(() => {
        const active = activeRef.current;
        if (active?.attempt) {
            failedRef.current = active.attempt;
            setLastError({ title: "Response stopped", message: "The server may still finish this request. Retry checks the same request without repeating it.", retryable: true });
            if (active.attempt.snapshot.decisions) setApprovalsLocked(true);
        }
        invalidate();
    }, [invalidate]);
    const clearChat = React.useCallback(() => {
        invalidate(); releaseFiles(); resetDecisions();
        setMessages([]); setConversationId(undefined); setLastError(null); setLastPrompt(""); setInput(""); setApprovalsLocked(false);
    }, [invalidate, releaseFiles, resetDecisions, setMessages, setConversationId]);
    const loadConversationMessages = React.useCallback((loaded: ChatMessage[], id?: string) => {
        invalidate(); releaseFiles(); resetDecisions();
        setMessages(loaded); setConversationId(id); setLastError(null); setLastPrompt(""); setApprovalsLocked(false);
    }, [invalidate, releaseFiles, resetDecisions, setMessages, setConversationId]);
    const loadConversation = React.useCallback(async (id: string) => {
        const op = begin();
        releaseFiles(); resetDecisions(); setLastError(null); setApprovalsLocked(true);
        try {
            const response = await fetch(conversationUrl(id), { headers: { "X-Requested-With": "XMLHttpRequest" }, signal: op.controller.signal });
            if (!owns(op)) return false;
            if (!response.ok) throw new Error("Could not load conversation.");
            const data = await response.json();
            if (!owns(op)) return false;
            applySnapshot(data, op); setApprovalsLocked(false);
            return true;
        } catch (error) {
            if (owns(op)) { setLastError({ title: "History unavailable", message: error instanceof Error ? error.message : "Could not load conversation.", retryable: false }); setApprovalsLocked(false); }
            return false;
        } finally { finish(op); }
    }, [begin, releaseFiles, resetDecisions, owns, applySnapshot, finish, conversationUrl]);
    const setAutoApprove = React.useCallback((value: boolean) => {
        setAutoApproveState(value);
        try { if (persistenceKey) sessionStorage.setItem(`${persistenceKey}_auto_approve`, String(value)); } catch { /* Optional preference. */ }
    }, [persistenceKey]);
    const clearError = React.useCallback(() => setLastError(null), []);

    return { messages: messagesState, setMessages, input, setInput, isLoading, conversationId: conversationState, setConversationId, lastError, lastPrompt, clearError, sendPrompt, retryLastRequest, submitDecision, submitAllDecisions, approvalDecisions, approvalsLocked, autoApprove, setAutoApprove, resendUserMessage, regenerateAssistant, stop, clearChat, loadConversationMessages, loadConversation };
}
