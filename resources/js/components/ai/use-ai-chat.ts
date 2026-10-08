import { chat } from "@/actions/App/Http/Controllers/AiChatController";
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

export interface CitationSource {
    title: string;
    url: string;
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
}

export interface UseAiChatOptions {
    agent: AgentRoleKey;
    endpoint?: string;
    initialConversationId?: string;
    initialMessages?: ChatMessage[];
    persistenceKey?: string;
    onFinish?: (message: ChatMessage) => void;
    onError?: (error: Error) => void;
    onConversationCreated?: (conversationId: string, title?: string) => void;
}

export interface PromptOptions {
    agent?: AgentRoleKey;
    model?: string;
    provider?: string;
    supportsDocuments?: boolean;
    thinking?: boolean;
    search?: boolean;
    autoApprove?: boolean;
}

export function useAiChat({
    agent,
    endpoint,
    initialConversationId,
    initialMessages = [],
    persistenceKey,
    onFinish,
    onError,
    onConversationCreated,
}: UseAiChatOptions) {
    const [messages, setMessages] = React.useState<ChatMessage[]>(() => {
        if (persistenceKey && typeof window !== "undefined") {
            try {
                const saved = sessionStorage.getItem(persistenceKey);
                if (saved) {
                    const parsed = JSON.parse(saved);
                    if (Array.isArray(parsed) && parsed.length > 0) {
                        return parsed;
                    }
                }
            } catch {
                // Ignore parsing errors
            }
        }
        return initialMessages;
    });
    const [input, setInput] = React.useState("");
    const [isLoading, setIsLoading] = React.useState(false);
    const [conversationId, setConversationId] = React.useState<string | undefined>(() => {
        if (persistenceKey && typeof window !== "undefined") {
            try {
                const savedId = sessionStorage.getItem(`${persistenceKey}_conv_id`);
                if (savedId) return savedId;
            } catch {
                // Ignore storage errors
            }
        }
        return initialConversationId;
    });
    const [autoApprove, setAutoApproveState] = React.useState<boolean>(() => {
        if (persistenceKey && typeof window !== "undefined") {
            try {
                return sessionStorage.getItem(`${persistenceKey}_auto_approve`) === "true";
            } catch {
                return false;
            }
        }
        return false;
    });
    const [lastError, setLastError] = React.useState<{ title: string; message: string; retryPrompt?: string } | null>(null);
    const [lastPrompt, setLastPrompt] = React.useState<string>("");

    const setAutoApprove = React.useCallback(
        (val: boolean) => {
            setAutoApproveState(val);
            if (persistenceKey && typeof window !== "undefined") {
                try {
                    sessionStorage.setItem(`${persistenceKey}_auto_approve`, String(val));
                } catch {
                    // Ignore storage errors
                }
            }
        },
        [persistenceKey]
    );

    // Save messages and conversationId whenever updated if persistenceKey provided
    React.useEffect(() => {
        if (!persistenceKey || typeof window === "undefined") return;
        try {
            if (messages.length > 0) {
                sessionStorage.setItem(persistenceKey, JSON.stringify(messages));
            }
            if (conversationId) {
                sessionStorage.setItem(`${persistenceKey}_conv_id`, conversationId);
            }
        } catch {
            // Ignore storage quotas
        }
    }, [messages, conversationId, persistenceKey]);

    const abortControllerRef = React.useRef<AbortController | null>(null);
    const isSendingRef = React.useRef(false);

    const targetUrl = endpoint || chat.url();

    React.useEffect(() => {
        if (initialConversationId !== undefined && initialConversationId !== conversationId) {
            setConversationId(initialConversationId);
        }
    }, [initialConversationId]);

    React.useEffect(() => {
        if (initialMessages.length > 0 && messages.length === 0) {
            setMessages(initialMessages);
        }
    }, [initialMessages]);

    const appendMessage = React.useCallback((msg: Omit<ChatMessage, "id">) => {
        const id = "msg_" + Math.random().toString(36).substring(2, 9);
        const fullMessage: ChatMessage = { ...msg, id, createdAt: new Date() };
        setMessages((prev) => [...prev, fullMessage]);
        return fullMessage;
    }, []);

    const readStream = React.useCallback(
        async (response: Response, assistantId: string, retryPromptText?: string, documentRetryPrompt?: string) => {
            if (!response.body) {
                throw new Error("No response body received from chat stream.");
            }

            const reader = response.body.getReader();
            const decoder = new TextDecoder();
            let accumulatedText = "";
            let accumulatedReasoning = "";
            let lineBuffer = "";
            const pendingApprovals: PendingToolApproval[] = [];
            const toolInvocations: Map<string, ToolInvocation> = new Map();
            const citations: CitationSource[] = [];
            let sawDone = false;
            let sawStreamError = false;

            while (true) {
                const { value, done } = await reader.read();
                if (done) break;

                lineBuffer += decoder.decode(value, { stream: true });
                const lines = lineBuffer.split("\n");
                lineBuffer = lines.pop() ?? "";

                for (const line of lines) {
                    const trimmed = line.trim();
                    if (!trimmed) continue;

                    if (trimmed.startsWith("data: ")) {
                        const dataPayload = trimmed.slice(6).trim();
                        if (dataPayload === "[DONE]") {
                            sawDone = true;
                            continue;
                        }

                        try {
                            const parsed = JSON.parse(dataPayload);
                            if (parsed.type === "text-delta" || parsed.type === "text_delta") {
                                accumulatedText += parsed.delta ?? parsed.text ?? "";
                            } else if (parsed.type === "reasoning-delta" || parsed.type === "reasoning_delta") {
                                accumulatedReasoning += parsed.delta ?? parsed.text ?? "";
                            } else if (parsed.type === "tool-call" || parsed.type === "tool_call") {
                                const callId = parsed.toolCallId || parsed.id;
                                if (callId) {
                                    toolInvocations.set(callId, {
                                        id: callId,
                                        toolName: parsed.toolName || parsed.name || "Tool",
                                        state: "input-available",
                                        input: parsed.input || parsed.arguments,
                                    });
                                }
                            } else if (parsed.type === "tool-result" || parsed.type === "tool_result") {
                                const callId = parsed.toolCallId || parsed.id;
                                if (callId) {
                                    const existing = toolInvocations.get(callId);
                                    if (existing) {
                                        existing.state = parsed.successful ? "output-available" : "output-error";
                                        existing.output = parsed.output;
                                        existing.errorText = parsed.error;
                                    } else {
                                        toolInvocations.set(callId, {
                                            id: callId,
                                            toolName: parsed.toolName || "Tool",
                                            state: parsed.successful ? "output-available" : "output-error",
                                            output: parsed.output,
                                            errorText: parsed.error,
                                        });
                                    }
                                }
                            } else if (parsed.type === "citation") {
                                if (parsed.url && !citations.some((c) => c.url === parsed.url)) {
                                    citations.push({
                                        title: parsed.title || parsed.url,
                                        url: parsed.url,
                                    });
                                }
                            } else if (parsed.type === "conversation") {
                                const newConvId = parsed.conversationId || parsed.id;
                                if (newConvId) {
                                    setConversationId(newConvId);
                                    onConversationCreated?.(newConvId, parsed.title);
                                }
                            } else if (parsed.type === "error") {
                                sawStreamError = true;
                                const errMsg = parsed.errorText || parsed.message || "An error occurred with the AI provider.";
                                const documentCapabilityError = /does not support document attachments|only image attachments are supported/i.test(
                                    errMsg,
                                );
                                setLastError({
                                    title: documentCapabilityError ? "Selected model cannot read document files" : "AI Generation Error",
                                    message: documentCapabilityError
                                        ? "This provider accepts image attachments only. Your request remains available; choose a model with document support or attach text exports/page images, then retry."
                                        : errMsg,
                                    retryPrompt: documentCapabilityError ? documentRetryPrompt : retryPromptText,
                                });
                                if (!accumulatedText.trim()) {
                                    accumulatedText = documentCapabilityError
                                        ? "⚠️ This provider accepts image attachments only. Select a model with document support or attach text exports/page images, then retry."
                                        : `⚠️ ${errMsg}`;
                                }
                            } else if (parsed.type === "tool-approval-request" || parsed.type === "tool_approval_request") {
                                pendingApprovals.push({
                                    id: parsed.approvalId || parsed.toolCallId || parsed.id,
                                    tool: parsed.tool || "Tool Execution",
                                    reason: parsed.reason,
                                    arguments: parsed.arguments,
                                });
                            }
                        } catch {
                            // Plain text fallback
                            accumulatedText += dataPayload;
                        }
                    } else if (trimmed.startsWith("0:")) {
                        try {
                            accumulatedText += JSON.parse(trimmed.slice(2));
                        } catch {
                            accumulatedText += trimmed.slice(2);
                        }
                    } else if (trimmed.startsWith("a:") || trimmed.includes("tool_approval")) {
                        try {
                            const raw = trimmed.startsWith("a:") ? trimmed.slice(2) : trimmed;
                            const parsed = JSON.parse(raw);
                            if (parsed?.approval) {
                                pendingApprovals.push(parsed.approval);
                            }
                        } catch {
                            // Silent fallback on non-json stream parts
                        }
                    }

                    // Update current assistant message in real time
                    setMessages((prev) =>
                        prev.map((m) =>
                            m.id === assistantId
                                ? {
                                      ...m,
                                      content: accumulatedText,
                                      reasoning: accumulatedReasoning || undefined,
                                      toolCalls: toolInvocations.size > 0 ? Array.from(toolInvocations.values()) : undefined,
                                      sources: citations.length > 0 ? [...citations] : undefined,
                                      pendingApprovals: pendingApprovals.length > 0 ? [...pendingApprovals] : undefined,
                                  }
                                : m,
                        ),
                    );
                }
            }

            // A dropped connection (proxy timeout, server kill) ends the reader
            // without [DONE] and without an error event — previously the chat
            // just stopped on a blank bubble. Surface it as a retryable error.
            if (!sawDone && !sawStreamError) {
                sawStreamError = true;
                const interruptMsg =
                    "The connection to the AI service was interrupted before the response completed. Your request is preserved — retry the request.";
                setLastError({
                    title: "AI Generation Error",
                    message: interruptMsg,
                    retryPrompt: retryPromptText,
                });
                if (!accumulatedText.trim()) {
                    accumulatedText = `⚠️ ${interruptMsg}`;
                }
            }

            // An empty turn with no text, tools, or approvals is never a valid
            // silent stop — ensure the error banner and a visible message exist.
            if (
                !accumulatedText.trim() &&
                toolInvocations.size === 0 &&
                pendingApprovals.length === 0 &&
                !sawStreamError
            ) {
                sawStreamError = true;
                const emptyMsg =
                    "The assistant stopped without producing a response. Your request is preserved — retry the request.";
                setLastError({
                    title: "AI Generation Error",
                    message: emptyMsg,
                    retryPrompt: retryPromptText,
                });
                accumulatedText = `⚠️ ${emptyMsg}`;
            }

            const finalMessage: ChatMessage = {
                id: assistantId,
                role: "assistant",
                content: accumulatedText,
                reasoning: accumulatedReasoning || undefined,
                toolCalls: toolInvocations.size > 0 ? Array.from(toolInvocations.values()) : undefined,
                sources: citations.length > 0 ? [...citations] : undefined,
                pendingApprovals: pendingApprovals.length > 0 ? pendingApprovals : undefined,
            };

            onFinish?.(finalMessage);
            return finalMessage;
        },
        [onFinish, onConversationCreated],
    );

    const sendPrompt = React.useCallback(
        async (content: string, files?: File[], options?: PromptOptions) => {
            if ((!content.trim() && (!files || files.length === 0)) || isLoading || isSendingRef.current) return;
            isSendingRef.current = true;

            const userMessage = content.trim() || (files?.length ? "Please analyze the attached file(s)." : "");
            const originalRequest = content.trim();
            const documentRetryPrompt = `${content.trim() ? `Original request: ${content.trim()}\n\n` : ""}My file is attached, but the selected model provider rejected document attachments. Use the extracted workbook/text content already present in this conversation to answer or continue. If visual OCR is needed, ask me to attach page images or switch to a document-capable model.`;

            setLastError(null);
            setLastPrompt(originalRequest);

            const chatAttachments: ChatAttachment[] = (files || []).map((file) => ({
                name: file.name,
                size: file.size,
                type: file.type,
                previewUrl: ["image/jpeg", "image/png", "image/webp", "image/gif"].includes(file.type) ? URL.createObjectURL(file) : undefined,
            }));

            appendMessage({
                role: "user",
                content: userMessage,
                attachments: chatAttachments.length > 0 ? chatAttachments : undefined,
            });

            setInput("");
            setIsLoading(true);

            // Create temporary assistant message placeholder
            const assistantId = "msg_" + Math.random().toString(36).substring(2, 9);
            setMessages((prev) => [
                ...prev,
                {
                    id: assistantId,
                    role: "assistant",
                    content: "",
                },
            ]);

            const controller = new AbortController();
            abortControllerRef.current = controller;

            const csrfToken = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || "";

            const targetAgent = options?.agent || agent;

            try {
                let requestOptions: RequestInit;

                if (files && files.length > 0) {
                    const formData = new FormData();
                    formData.append("agent", targetAgent);
                    formData.append("message", userMessage);
                    if (conversationId) {
                        formData.append("conversation_id", conversationId);
                    }
                    if (options?.model) {
                        formData.append("model", options.model);
                    }
                    if (options?.provider) {
                        formData.append("provider", options.provider);
                    }
                    if (typeof options?.thinking === "boolean") {
                        formData.append("thinking", options.thinking ? "1" : "0");
                    }
                    if (typeof options?.search === "boolean") {
                        formData.append("search", options.search ? "1" : "0");
                    }
                    const isAutoApproveActive = typeof options?.autoApprove === "boolean" ? options.autoApprove : autoApprove;
                    formData.append("auto_approve", isAutoApproveActive ? "1" : "0");
                    files.forEach((file) => {
                        formData.append("attachments[]", file);
                    });

                    requestOptions = {
                        method: "POST",
                        headers: {
                            Accept: "text/event-stream, text/plain",
                            "X-Requested-With": "XMLHttpRequest",
                            "X-CSRF-TOKEN": csrfToken,
                        },
                        body: formData,
                        signal: controller.signal,
                    };
                } else {
                    const isAutoApproveActive = typeof options?.autoApprove === "boolean" ? options.autoApprove : autoApprove;
                    const bodyPayload: Record<string, unknown> = {
                        agent: targetAgent,
                        message: userMessage,
                        conversation_id: conversationId,
                        auto_approve: Boolean(isAutoApproveActive),
                    };
                    if (options?.model) {
                        bodyPayload.model = options.model;
                    }
                    if (options?.provider) {
                        bodyPayload.provider = options.provider;
                    }
                    if (typeof options?.thinking === "boolean") {
                        bodyPayload.thinking = options.thinking;
                    }
                    if (typeof options?.search === "boolean") {
                        bodyPayload.search = options.search;
                    }

                    requestOptions = {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/json",
                            Accept: "text/event-stream, text/plain",
                            "X-Requested-With": "XMLHttpRequest",
                            "X-CSRF-TOKEN": csrfToken,
                        },
                        body: JSON.stringify(bodyPayload),
                        signal: controller.signal,
                    };
                }

                const response = await fetch(targetUrl, requestOptions);

                if (!response.ok) {
                    const errJson = await response.json().catch(() => ({}));
                    const errorMsg = errJson.message || `Server error (${response.status})`;
                    const documentCapabilityError = /does not support document attachments|only image attachments are supported/i.test(errorMsg);
                    const errorObj = {
                        title: documentCapabilityError ? "Selected model cannot read document files" : `AI Error (${response.status})`,
                        message: documentCapabilityError
                            ? "This provider accepts image attachments only. Your request is unchanged; switch to a document-capable model or attach text exports/page images, then retry."
                            : errorMsg,
                        retryPrompt: documentCapabilityError ? documentRetryPrompt : userMessage || documentRetryPrompt,
                    };
                    setLastError(errorObj);
                    throw new Error(errorMsg);
                }

                await readStream(response, assistantId, userMessage, documentRetryPrompt);
            } catch (err: unknown) {
                if (err instanceof Error && err.name === "AbortError") return;

                const message = err instanceof Error ? err.message : "Failed to communicate with AI agent.";
                const documentCapabilityError = /does not support document attachments|only image attachments are supported/i.test(message);
                // Stream/network failures previously only toasted, leaving no
                // Retry affordance and sometimes a blank bubble. Always set a
                // retryable error so the banner + retry button appear.
                if (!documentCapabilityError) {
                    setLastError({
                        title: "AI Generation Error",
                        message,
                        retryPrompt: userMessage || documentRetryPrompt,
                    });
                }
                toast.error(message);
                onError?.(err instanceof Error ? err : new Error(message));

                setMessages((prev) =>
                    prev.map((m) =>
                        m.id === assistantId
                            ? {
                                  ...m,
                                  content:
                                      m.content ||
                                      (documentCapabilityError
                                          ? "⚠️ This provider only accepts image attachments. Your uploaded files and prompt are preserved; switch to a document-capable model or attach page images, then retry."
                                          : `⚠️ Generation failed: ${message}`),
                              }
                            : m,
                    ),
                );
            } finally {
                setIsLoading(false);
                isSendingRef.current = false;
                abortControllerRef.current = null;
            }
        },
        [agent, targetUrl, conversationId, isLoading, autoApprove, appendMessage, readStream, onError],
    );

    /**
     * Resend a previously sent user message: truncate the transcript back to
     * it, then send its content again as a fresh turn. Attachments cannot be
     * reconstructed from history, so only the text is resent.
     */
    const resendUserMessage = React.useCallback(
        async (messageId: string, options?: PromptOptions) => {
            if (isLoading || isSendingRef.current) return;
            const idx = messages.findIndex((m) => m.id === messageId);
            if (idx < 0) return;
            const target = messages[idx];
            if (target.role !== "user" || !target.content.trim()) return;
            if (target.attachments && target.attachments.length > 0) {
                toast.info("Resending text only — attachments are not resent.");
            }
            setMessages((prev) => prev.slice(0, idx));
            setLastError(null);
            setLastPrompt(target.content);
            await sendPrompt(target.content, undefined, {
                ...(options ?? {}),
                agent: options?.agent ?? agent,
            });
        },
        [agent, isLoading, messages, sendPrompt]
    );

    /**
     * Regenerate an assistant reply: find the nearest preceding user message,
     * truncate the transcript back to it, and send it again.
     */
    const regenerateAssistant = React.useCallback(
        async (messageId: string, options?: PromptOptions) => {
            if (isLoading || isSendingRef.current) return;
            const idx = messages.findIndex((m) => m.id === messageId);
            if (idx < 0 || messages[idx].role !== "assistant") return;
            let userIdx = -1;
            for (let i = idx - 1; i >= 0; i--) {
                if (messages[i].role === "user") {
                    userIdx = i;
                    break;
                }
            }
            if (userIdx < 0) {
                toast.info("No earlier message to regenerate from.");
                return;
            }
            await resendUserMessage(messages[userIdx].id, options);
        },
        [isLoading, messages, resendUserMessage]
    );

    const submitDecision = React.useCallback(
        async (callId: string, action: "approve" | "reject", result?: string) => {
            if (isLoading || isSendingRef.current) return;
            isSendingRef.current = true;
            setIsLoading(true);

            // Find assistant message holding the pending approval
            const targetAssistantMessage = messages
                .slice()
                .reverse()
                .find((m) => m.role === "assistant" && m.pendingApprovals?.some((a) => a.id === callId));
            const targetApproval = targetAssistantMessage?.pendingApprovals?.find((a) => a.id === callId);

            // Optimistically update message approvals
            setMessages((prev) =>
                prev.map((m) => ({
                    ...m,
                    pendingApprovals: m.pendingApprovals?.filter((a) => a.id !== callId),
                })),
            );

            const controller = new AbortController();
            abortControllerRef.current = controller;

            try {
                const decisions = {
                    [callId]: {
                        action,
                        result,
                    },
                };

                const response = await fetch(targetUrl, {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        Accept: "text/event-stream, text/plain",
                        "X-Requested-With": "XMLHttpRequest",
                        "X-CSRF-TOKEN": (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || "",
                    },
                    body: JSON.stringify({
                        agent,
                        decisions,
                        auto_approve: autoApprove,
                        conversation_id: conversationId,
                    }),
                    signal: controller.signal,
                });

                if (!response.ok) {
                    throw new Error(`Decision submission failed (${response.status})`);
                }

                toast.success(action === "approve" ? "Approved. Executing tool..." : "Tool execution rejected.");

                const contentType = response.headers.get("content-type") || "";
                if (contentType.includes("text/event-stream") || contentType.includes("text/plain")) {
                    // Create a new assistant message placeholder for the post-decision continuation turn,
                    // preserving the prior assistant message and its tool call details.
                    const continuationId = "msg_" + Math.random().toString(36).substring(2, 9);
                    setMessages((prev) => [
                        ...prev,
                        {
                            id: continuationId,
                            role: "assistant",
                            content: "",
                        },
                    ]);
                    // Forward the last user prompt so a failed continuation turn
                    // still offers a working Retry affordance instead of an
                    // error that promises retry with no button.
                    await readStream(response, continuationId, lastPrompt || undefined);
                }
            } catch (err: unknown) {
                // Restore approval on failure
                if (targetAssistantMessage && targetApproval) {
                    setMessages((prev) =>
                        prev.map((m) =>
                            m.id === targetAssistantMessage.id
                                ? {
                                      ...m,
                                      pendingApprovals: m.pendingApprovals
                                          ? [...m.pendingApprovals.filter((a) => a.id !== callId), targetApproval]
                                          : [targetApproval],
                                  }
                                : m,
                        ),
                    );
                }
                if (err instanceof Error && err.name === "AbortError") return;
                const message = err instanceof Error ? err.message : "Failed to submit approval decision.";
                toast.error(message);
            } finally {
                setIsLoading(false);
                isSendingRef.current = false;
                abortControllerRef.current = null;
            }
        },
        [agent, targetUrl, conversationId, messages, lastPrompt, autoApprove, readStream],
    );

    const stop = React.useCallback(() => {
        isSendingRef.current = false;
        if (abortControllerRef.current) {
            abortControllerRef.current.abort();
            abortControllerRef.current = null;
            setIsLoading(false);
        }
    }, []);

    const clearChat = React.useCallback(() => {
        isSendingRef.current = false;
        setMessages([]);
        setConversationId(undefined);
        setLastError(null);
        if (persistenceKey && typeof window !== "undefined") {
            try {
                sessionStorage.removeItem(persistenceKey);
                sessionStorage.removeItem(`${persistenceKey}_conv_id`);
                sessionStorage.removeItem(`${persistenceKey}_auto_approve`);
            } catch {
                // Ignore storage errors
            }
        }
    }, [persistenceKey]);

    const submitAllDecisions = React.useCallback(
        async (action: "approve" | "reject") => {
            if (isLoading || isSendingRef.current) return;

            const allPendingApprovals: PendingToolApproval[] = [];
            messages.forEach((m) => {
                if (m.role === "assistant" && m.pendingApprovals) {
                    allPendingApprovals.push(...m.pendingApprovals);
                }
            });

            if (allPendingApprovals.length === 0) return;

            // Preserve pending approvals for rollback if batch submission fails
            const previousApprovals = new Map(
                messages
                    .filter((m) => m.role === "assistant" && m.pendingApprovals && m.pendingApprovals.length > 0)
                    .map((m) => [m.id, m.pendingApprovals!])
            );

            isSendingRef.current = true;
            setIsLoading(true);

            setMessages((prev) =>
                prev.map((m) => ({
                    ...m,
                    pendingApprovals: undefined,
                })),
            );

            const controller = new AbortController();
            abortControllerRef.current = controller;

            try {
                const decisions: Record<string, { action: string }> = {};
                allPendingApprovals.forEach((a) => {
                    decisions[a.id] = { action };
                });

                const response = await fetch(targetUrl, {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        Accept: "text/event-stream, text/plain",
                        "X-Requested-With": "XMLHttpRequest",
                        "X-CSRF-TOKEN": (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || "",
                    },
                    body: JSON.stringify({
                        agent,
                        decisions,
                        auto_approve: autoApprove,
                        conversation_id: conversationId,
                    }),
                    signal: controller.signal,
                });

                if (!response.ok) {
                    throw new Error(`Decision submission failed (${response.status})`);
                }

                toast.success(action === "approve" ? "All actions approved. Continuing execution..." : "All pending actions rejected.");

                const contentType = response.headers.get("content-type") || "";
                if (contentType.includes("text/event-stream") || contentType.includes("text/plain")) {
                    const continuationId = "msg_" + Math.random().toString(36).substring(2, 9);
                    setMessages((prev) => [
                        ...prev,
                        {
                            id: continuationId,
                            role: "assistant",
                            content: "",
                        },
                    ]);
                    await readStream(response, continuationId, lastPrompt || undefined);
                }
            } catch (err: unknown) {
                // Restore approvals on failure so user can retry
                setMessages((prev) =>
                    prev.map((m) =>
                        previousApprovals.has(m.id)
                            ? { ...m, pendingApprovals: previousApprovals.get(m.id) }
                            : m
                    )
                );

                if (err instanceof Error && err.name === "AbortError") return;
                const message = err instanceof Error ? err.message : "Failed to submit approval decisions.";
                toast.error(message);
            } finally {
                setIsLoading(false);
                isSendingRef.current = false;
                abortControllerRef.current = null;
            }
        },
        [isLoading, messages, targetUrl, agent, autoApprove, conversationId, readStream, lastPrompt]
    );

    const clearError = React.useCallback(() => {
        setLastError(null);
    }, []);

    const loadConversationMessages = React.useCallback((loadedMessages: ChatMessage[], newConversationId?: string) => {
        setMessages(loadedMessages);
        if (newConversationId !== undefined) {
            setConversationId(newConversationId);
        }
        setLastError(null);
    }, []);

    return {
        messages,
        setMessages,
        input,
        setInput,
        isLoading,
        conversationId,
        setConversationId,
        lastError,
        lastPrompt,
        clearError,
        sendPrompt,
        submitDecision,
        submitAllDecisions,
        autoApprove,
        setAutoApprove,
        resendUserMessage,
        regenerateAssistant,
        stop,
        clearChat,
        loadConversationMessages,
    };
}
