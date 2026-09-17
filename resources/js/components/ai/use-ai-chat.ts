import { chat } from "@/actions/App/Http/Controllers/AiChatController";
import * as React from "react";
import { toast } from "sonner";
import type { PendingToolApproval } from "./approval-card";

export type AgentRoleKey =
    | "admin_executive"
    | "student_advisor"
    | "faculty_copilot"
    | "registrar_auditor"
    | "bursar_finance"
    | "campus_support";

export interface ChatAttachment {
    name: string;
    size: number;
    type: string;
    previewUrl?: string;
}

export interface ChatMessage {
    id: string;
    role: "user" | "assistant" | "system";
    content: string;
    attachments?: ChatAttachment[];
    pendingApprovals?: PendingToolApproval[];
    createdAt?: Date;
}

export interface UseAiChatOptions {
    agent: AgentRoleKey;
    endpoint?: string;
    initialConversationId?: string;
    onFinish?: (message: ChatMessage) => void;
    onError?: (error: Error) => void;
}

export function useAiChat({ agent, endpoint, initialConversationId, onFinish, onError }: UseAiChatOptions) {
    const [messages, setMessages] = React.useState<ChatMessage[]>([]);
    const [input, setInput] = React.useState("");
    const [isLoading, setIsLoading] = React.useState(false);
    const [conversationId, setConversationId] = React.useState<string | undefined>(initialConversationId);

    const abortControllerRef = React.useRef<AbortController | null>(null);

    const targetUrl = endpoint || chat.url();

    const appendMessage = React.useCallback((msg: Omit<ChatMessage, "id">) => {
        const id = "msg_" + Math.random().toString(36).substring(2, 9);
        const fullMessage: ChatMessage = { ...msg, id, createdAt: new Date() };
        setMessages((prev) => [...prev, fullMessage]);
        return fullMessage;
    }, []);

    const sendPrompt = React.useCallback(
        async (content: string, files?: File[]) => {
            if ((!content.trim() && (!files || files.length === 0)) || isLoading) return;

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

            try {
                let requestOptions: RequestInit;

                if (files && files.length > 0) {
                    const formData = new FormData();
                    formData.append("agent", agent);
                    formData.append("message", content.trim() || "Please inspect and analyze the attached file(s).");
                    if (conversationId) {
                        formData.append("conversation_id", conversationId);
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
                    requestOptions = {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/json",
                            Accept: "text/event-stream, text/plain",
                            "X-Requested-With": "XMLHttpRequest",
                            "X-CSRF-TOKEN": csrfToken,
                        },
                        body: JSON.stringify({
                            agent,
                            message: content.trim(),
                            conversation_id: conversationId,
                        }),
                        signal: controller.signal,
                    };
                }

                const response = await fetch(targetUrl, requestOptions);

                if (!response.ok) {
                    const errJson = await response.json().catch(() => ({}));
                    throw new Error(errJson.message || `Server responded with ${response.status}`);
                }

                if (!response.body) {
                    throw new Error("No response body received from chat stream.");
                }

                const reader = response.body.getReader();
                const decoder = new TextDecoder();
                let accumulatedText = "";
                const pendingApprovals: PendingToolApproval[] = [];

                while (true) {
                    const { value, done } = await reader.read();
                    if (done) break;

                    const chunk = decoder.decode(value, { stream: true });
                    const lines = chunk.split("\n");

                    for (const line of lines) {
                        const trimmed = line.trim();
                        if (!trimmed) continue;

                        // Vercel AI SDK text part: 0:"text content"
                        if (trimmed.startsWith("0:")) {
                            try {
                                const textPart = JSON.parse(trimmed.slice(2));
                                accumulatedText += textPart;
                            } catch {
                                accumulatedText += trimmed.slice(2);
                            }
                        }
                        // Tool approval request event in protocol
                        else if (trimmed.startsWith("a:") || trimmed.includes("tool_approval")) {
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
                        // Plain SSE data: or raw text chunk fallback
                        else if (trimmed.startsWith("data: ")) {
                            accumulatedText += trimmed.slice(6);
                        } else if (!trimmed.startsWith("d:") && !trimmed.startsWith("e:")) {
                            accumulatedText += trimmed;
                        }

                        // Update current assistant message in real time
                        setMessages((prev) =>
                            prev.map((m) =>
                                m.id === assistantId
                                    ? {
                                          ...m,
                                          content: accumulatedText,
                                          pendingApprovals:
                                              pendingApprovals.length > 0 ? [...pendingApprovals] : undefined,
                                      }
                                    : m
                            )
                        );
                    }
                }

                const finalMessage: ChatMessage = {
                    id: assistantId,
                    role: "assistant",
                    content: accumulatedText,
                    pendingApprovals: pendingApprovals.length > 0 ? pendingApprovals : undefined,
                };

                onFinish?.(finalMessage);
            } catch (err: any) {
                if (err.name === "AbortError") return;

                toast.error(err.message || "Failed to communicate with AI agent.");
                onError?.(err);

                setMessages((prev) =>
                    prev.map((m) =>
                        m.id === assistantId
                            ? {
                                  ...m,
                                  content: "⚠️ An error occurred while generating a response. Please try again.",
                              }
                            : m
                    )
                );
            } finally {
                setIsLoading(false);
                abortControllerRef.current = null;
            }
        },
        [agent, targetUrl, conversationId, isLoading, appendMessage, onFinish, onError]
    );

    const submitDecision = React.useCallback(
        async (callId: string, action: "approve" | "reject", result?: string) => {
            setIsLoading(true);

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
                        "X-CSRF-TOKEN":
                            (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || "",
                    },
                    body: JSON.stringify({
                        agent,
                        decisions,
                        conversation_id: conversationId,
                    }),
                });

                if (!response.ok) {
                    throw new Error(`Decision submission failed (${response.status})`);
                }

                toast.success(action === "approve" ? "Approved and executed." : "Tool execution rejected.");

                // Clear the pending approval from message state
                setMessages((prev) =>
                    prev.map((m) => ({
                        ...m,
                        pendingApprovals: m.pendingApprovals?.filter((a) => a.id !== callId),
                    }))
                );
            } catch (err: any) {
                toast.error(err.message || "Failed to submit approval decision.");
            } finally {
                setIsLoading(false);
            }
        },
        [agent, targetUrl, conversationId]
    );

    const stop = React.useCallback(() => {
        if (abortControllerRef.current) {
            abortControllerRef.current.abort();
            abortControllerRef.current = null;
            setIsLoading(false);
        }
    }, []);

    const clearChat = React.useCallback(() => {
        setMessages([]);
        setConversationId(undefined);
    }, []);

    return {
        messages,
        input,
        setInput,
        isLoading,
        conversationId,
        sendPrompt,
        submitDecision,
        stop,
        clearChat,
    };
}
