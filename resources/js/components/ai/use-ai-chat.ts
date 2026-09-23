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
    onFinish?: (message: ChatMessage) => void;
    onError?: (error: Error) => void;
    onConversationCreated?: (conversationId: string, title?: string) => void;
}

export interface PromptOptions {
    agent?: AgentRoleKey;
    model?: string;
    provider?: string;
}

export function useAiChat({
    agent,
    endpoint,
    initialConversationId,
    initialMessages = [],
    onFinish,
    onError,
    onConversationCreated,
}: UseAiChatOptions) {
    const [messages, setMessages] = React.useState<ChatMessage[]>(initialMessages);
    const [input, setInput] = React.useState("");
    const [isLoading, setIsLoading] = React.useState(false);
    const [conversationId, setConversationId] = React.useState<string | undefined>(initialConversationId);
    const [lastError, setLastError] = React.useState<{ title: string; message: string; retryPrompt?: string } | null>(null);
    const [lastPrompt, setLastPrompt] = React.useState<string>("");

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
        async (response: Response, assistantId: string, retryPromptText?: string) => {
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
                        if (dataPayload === "[DONE]") continue;

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
                                const errMsg = parsed.errorText || parsed.message || "An error occurred with the AI provider.";
                                setLastError({
                                    title: "AI Generation Error",
                                    message: errMsg,
                                    retryPrompt: retryPromptText,
                                });
                                if (!accumulatedText.trim()) {
                                    accumulatedText = `⚠️ ${errMsg}`;
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

            setLastError(null);
            setLastPrompt(content);

            const chatAttachments: ChatAttachment[] = (files || []).map((file) => ({
                name: file.name,
                size: file.size,
                type: file.type,
                previewUrl: file.type.startsWith("image/") ? URL.createObjectURL(file) : undefined,
            }));

            appendMessage({
                role: "user",
                content: content.trim() || (files && files.length > 0 ? "Please analyze the attached file(s)." : ""),
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
                    formData.append("message", content.trim() || "Please inspect and analyze the attached file(s).");
                    if (conversationId) {
                        formData.append("conversation_id", conversationId);
                    }
                    if (options?.model) {
                        formData.append("model", options.model);
                    }
                    if (options?.provider) {
                        formData.append("provider", options.provider);
                    }
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
                    const bodyPayload: Record<string, unknown> = {
                        agent: targetAgent,
                        message: content.trim(),
                        conversation_id: conversationId,
                    };
                    if (options?.model) {
                        bodyPayload.model = options.model;
                    }
                    if (options?.provider) {
                        bodyPayload.provider = options.provider;
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
                    const errorObj = {
                        title: `AI Error (${response.status})`,
                        message: errorMsg,
                        retryPrompt: content,
                    };
                    setLastError(errorObj);
                    throw new Error(errorMsg);
                }

                await readStream(response, assistantId, content);
            } catch (err: unknown) {
                if (err instanceof Error && err.name === "AbortError") return;

                const message = err instanceof Error ? err.message : "Failed to communicate with AI agent.";
                toast.error(message);
                onError?.(err instanceof Error ? err : new Error(message));

                setMessages((prev) =>
                    prev.map((m) =>
                        m.id === assistantId
                            ? {
                                  ...m,
                                  content: m.content || `⚠️ Generation failed: ${message}`,
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
        [agent, targetUrl, conversationId, isLoading, appendMessage, readStream, onError],
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
                    await readStream(response, continuationId);
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
        [agent, targetUrl, conversationId, messages, readStream],
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
    }, []);

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
        stop,
        clearChat,
        loadConversationMessages,
    };
}
