import AdminLayout from "@/components/administrators/admin-layout";
import {
    ADMIN_AGENTS,
    DEFAULT_PROMPT_SUGGESTIONS,
    type ConversationItem,
    type PromptSuggestion,
} from "@/components/ai/ai-constants";
import { AiConversationSidebar } from "@/components/ai/ai-conversation-sidebar";
import { ApprovalCard } from "@/components/ai/approval-card";
import { ChatMessageFormatter } from "@/components/ai/chat-message-formatter";
import {
    AssistantMessageActions,
    UserMessageActions,
} from "@/components/ai/message-actions";
import { AgentRoleKey, ChatMessage, useAiChat } from "@/components/ai/use-ai-chat";
import { ModelOption, ModelSelector } from "@/components/spectrumui";
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar";
import { Button } from "@/components/ui/button";
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from "@/components/ui/dialog";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Input } from "@/components/ui/input";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { Shdr14 } from "@/components/ui/shdr-14";
import { Sheet, SheetContent, SheetTitle } from "@/components/ui/sheet";
import { cn } from "@/lib/utils";
import type { User } from "@/types/user";
import { Head, usePage } from "@inertiajs/react";
import {
    AlertCircle,
    ArrowUp,
    Bookmark,
    Brain,
    Check,
    ChevronDown,
    Copy,
    Download,
    Edit3,
    FileCode,
    FileImage,
    FileSpreadsheet,
    FileText,
    Globe,
    Info,
    Maximize2,
    Mic,
    Minimize2,
    Monitor,
    MoreVertical,
    Paperclip,
    Plus,
    RefreshCw,
    RotateCcw,
    Search,
    Smartphone,
    Sparkles,
    Square,
    Sun,
    Moon,
    Tablet,
    Trash2,
    X,
} from "lucide-react";
import * as React from "react";
import { toast } from "sonner";

interface InitialConversationData {
    id: string;
    title: string;
    created_at?: string;
    updated_at?: string;
    messages: ChatMessage[];
}

interface AiChatPageProps {
    initialConversation?: InitialConversationData | null;
    initialConversationId?: string | null;
}

const PREFERRED_MODEL_KEY = "preferred_admin_ai_model";
const ACCEPTED_ATTACHMENT_TYPES = ".pdf,.csv,.xlsx,.xls,.docx,.doc,.txt,.json,.sql,.png,.jpg,.jpeg,.webp";
const ACCEPTED_ATTACHMENT_TYPE_SET = new Set([
    "application/pdf",
    "text/csv",
    "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
    "application/vnd.ms-excel",
    "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
    "application/msword",
    "text/plain",
    "application/json",
    "application/sql",
    "text/x-sql",
    "image/png",
    "image/jpeg",
    "image/webp",
]);

function getGreeting(name: string): string {
    const hour = new Date().getHours();
    let timeGreeting = "Good morning";
    if (hour >= 12 && hour < 17) {
        timeGreeting = "Good afternoon";
    } else if (hour >= 17) {
        timeGreeting = "Good evening";
    }
    return `${timeGreeting}, ${name}`;
}

export default function AdministratorAiChatPage({ initialConversation, initialConversationId }: AiChatPageProps) {
    const { auth } = usePage<{ auth: { user: User } }>().props;
    const user = auth.user;

    const [mobileDrawerOpen, setMobileDrawerOpen] = React.useState(false);
    const [searchQuery, setSearchQuery] = React.useState("");

    const [conversations, setConversations] = React.useState<ConversationItem[]>([]);
    const [isLoadingConversations, setIsLoadingConversations] = React.useState(false);
    const [currentPage, setCurrentPage] = React.useState(1);
    const [hasMore, setHasMore] = React.useState(false);
    const [isLoadingMore, setIsLoadingMore] = React.useState(false);
    const [totalConversations, setTotalConversations] = React.useState(0);

    const [selectedAgent, setSelectedAgent] = React.useState<AgentRoleKey>("admin_executive");
    const [selectedModel, setSelectedModel] = React.useState<string>("");
    const [availableModels, setAvailableModels] = React.useState<ModelOption[]>([]);
    const [selectedFiles, setSelectedFiles] = React.useState<File[]>([]);

    // ReUI AI Chat style interactive controls
    const [isThinkingMode, setIsThinkingMode] = React.useState(false);
    const [isSearchMode, setIsSearchMode] = React.useState(false);
    const [showTermsBanner, setShowTermsBanner] = React.useState(true);
    const [showContextBar, setShowContextBar] = React.useState(true);
    const [isBookmarked, setIsBookmarked] = React.useState(false);
    const [previewWidth, setPreviewWidth] = React.useState<"desktop" | "tablet" | "mobile">("desktop");
    const [isFullscreen, setIsFullscreen] = React.useState(false);
    // Server-driven global default model (primary provider's default_chat_model).
    const [serverDefaultModel, setServerDefaultModel] = React.useState<string>("");
    const [primaryProvider, setPrimaryProvider] = React.useState<string>("");
    // Actual tools / MCP bound to the selected specialist (not the agent list).
    const [agentTools, setAgentTools] = React.useState<{ name: string; class?: string; description: string; kind: string }[]>([]);
    const [toolsLoading, setToolsLoading] = React.useState(false);
    const [isDark, setIsDark] = React.useState<boolean>(() => {
        if (typeof window !== "undefined") {
            return (
                document.documentElement.classList.contains("dark") ||
                localStorage.getItem("ui-theme") === "dark" ||
                localStorage.getItem("theme") === "dark"
            );
        }
        return false;
    });

    const toggleTheme = () => {
        const next = !isDark;
        setIsDark(next);
        if (typeof document !== "undefined") {
            if (next) {
                document.documentElement.classList.add("dark");
            } else {
                document.documentElement.classList.remove("dark");
            }
            try {
                localStorage.setItem("ui-theme", next ? "dark" : "light");
                localStorage.setItem("theme", next ? "dark" : "light");
            } catch {
                // Ignore storage errors
            }
        }
    };

    // Dialogs
    const [isRenameDialogOpen, setIsRenameDialogOpen] = React.useState(false);
    const [renameTitleInput, setRenameTitleInput] = React.useState("");
    const [isInfoDialogOpen, setIsInfoDialogOpen] = React.useState(false);
    const [isTermsDialogOpen, setIsTermsDialogOpen] = React.useState(false);

    const messagesEndRef = React.useRef<HTMLDivElement | null>(null);
    const chatContainerRef = React.useRef<HTMLDivElement | null>(null);
    const modelSearchRef = React.useRef<HTMLInputElement | null>(null);
    const fileInputRef = React.useRef<HTMLInputElement | null>(null);
    const textareaRef = React.useRef<HTMLTextAreaElement | null>(null);

    const {
        messages,
        input,
        setInput,
        isLoading,
        conversationId,
        lastError,
        clearError,
        sendPrompt,
        submitDecision,
        resendUserMessage,
        regenerateAssistant,
        stop,
        clearChat,
        loadConversationMessages,
    } = useAiChat({
        agent: selectedAgent,
        endpoint: "/administrators/ai/chat",
        initialConversationId: initialConversationId || initialConversation?.id,
        initialMessages: initialConversation?.messages || [],
        onConversationCreated: (newId, title) => {
            setConversations((prev) => [
                {
                    id: newId,
                    title: title || "New Conversation",
                    created_at: new Date().toISOString(),
                    updated_at: new Date().toISOString(),
                },
                ...prev.filter((c) => c.id !== newId),
            ]);
            if (typeof window !== "undefined") {
                const newUrl = `/administrators/ai?conversation=${newId}`;
                window.history.pushState(null, "", newUrl);
            }
        },
    });

    // Auto-scroll when new messages arrive
    React.useEffect(() => {
        if (messagesEndRef.current && chatContainerRef.current) {
            const container = chatContainerRef.current;
            const isNearBottom = container.scrollHeight - container.scrollTop - container.clientHeight < 250;
            if (isNearBottom || isLoading) {
                messagesEndRef.current.scrollIntoView({ behavior: "smooth" });
            }
        }
    }, [messages, isLoading]);

    // Fetch conversation list with pagination and search
    const fetchConversations = React.useCallback(async (page = 1, query = "", append = false) => {
        if (append) {
            setIsLoadingMore(true);
        } else {
            setIsLoadingConversations(true);
        }
        try {
            const params = new URLSearchParams();
            params.set("page", String(page));
            if (query.trim()) {
                params.set("query", query.trim());
            }

            const res = await fetch(`/administrators/ai/conversations?${params.toString()}`, {
                headers: { "X-Requested-With": "XMLHttpRequest" },
            });
            if (res.ok) {
                const data = await res.json();
                const fetchedItems: ConversationItem[] = data.data || [];
                setCurrentPage(data.current_page || page);
                setHasMore((data.current_page || page) < (data.last_page || 1));
                setTotalConversations(data.total || 0);

                setConversations((prev) => {
                    if (!append) return fetchedItems;
                    const existingIds = new Set(prev.map((c) => c.id));
                    const newItems = fetchedItems.filter((c) => !existingIds.has(c.id));
                    return [...prev, ...newItems];
                });
            }
        } catch {
            // Silently ignore network error
        } finally {
            setIsLoadingConversations(false);
            setIsLoadingMore(false);
        }
    }, []);

    React.useEffect(() => {
        fetchConversations(1, "", false);
    }, [fetchConversations]);

    const searchTimeoutRef = React.useRef<ReturnType<typeof setTimeout> | null>(null);
    const handleSearchChange = (query: string) => {
        setSearchQuery(query);
        if (searchTimeoutRef.current) {
            clearTimeout(searchTimeoutRef.current);
        }
        searchTimeoutRef.current = setTimeout(() => {
            fetchConversations(1, query, false);
        }, 300);
    };

    const handleLoadMore = () => {
        if (!isLoadingMore && hasMore) {
            fetchConversations(currentPage + 1, searchQuery, true);
        }
    };

    // Fetch analytics summary and available models.
    // Priority: saved user preference (if still configured) > server global
    // default (primary provider's default_chat_model) > first Default badge >
    // first primary-provider model > first model. Legacy best-free heuristics
    // are intentionally no longer used.
    React.useEffect(() => {
        fetch("/administrators/ai/analytics-summary", {
            headers: { "X-Requested-With": "XMLHttpRequest" },
        })
            .then((res) => res.json())
            .then((data: { models?: ModelOption[]; default_model?: string; primary_provider?: string }) => {
                if (Array.isArray(data.models) && data.models.length > 0) {
                    const mapped = data.models.map((model) => ({
                        id: model.id,
                        name: model.name || model.id,
                        badge: model.badge,
                        description: model.description,
                        provider: model.provider,
                        provider_name: model.provider_name,
                        supports_documents: model.supports_documents,
                    }));
                    setAvailableModels(mapped);

                    if (typeof data.primary_provider === "string") {
                        setPrimaryProvider(data.primary_provider);
                    }
                    if (typeof data.default_model === "string" && data.default_model) {
                        setServerDefaultModel(data.default_model);
                    }

                    const saved = typeof window !== "undefined" ? localStorage.getItem(PREFERRED_MODEL_KEY) : null;
                    if (saved && mapped.some((m) => m.id === saved)) {
                        if (!selectedModel) setSelectedModel(saved);
                        return;
                    }
                    if (!selectedModel) {
                        const serverDefault = typeof data.default_model === "string" ? data.default_model : "";
                        const byServerDefault = serverDefault ? mapped.find((m) => m.id === serverDefault) : undefined;
                        const byDefaultBadge = mapped.find((m) => m.badge?.includes("Default"));
                        const byPrimary = data.primary_provider
                            ? mapped.find((m) => m.provider === data.primary_provider)
                            : undefined;
                        const recommended = byServerDefault || byDefaultBadge || byPrimary || mapped[0];
                        if (recommended) setSelectedModel(recommended.id);
                    }
                }
            })
            .catch(() => undefined);
    }, [selectedModel]);

    // Fetch actual tools / MCP for the selected specialist. The composer
    // "tools" button must list these — never the specialist agent list.
    React.useEffect(() => {
        let cancelled = false;
        setToolsLoading(true);
        fetch(`/administrators/ai/agent-tools?agent=${encodeURIComponent(selectedAgent)}`, {
            headers: { "X-Requested-With": "XMLHttpRequest" },
        })
            .then((res) => (res.ok ? res.json() : null))
            .then((data) => {
                if (cancelled) return;
                if (data && Array.isArray(data.tools)) {
                    setAgentTools(data.tools);
                } else {
                    // Fallback to the static catalogue mirrored from app/Ai/Agents.
                    import("@/components/ai/ai-constants").then((mod) => {
                        if (!cancelled) setAgentTools(mod.getFallbackTools(selectedAgent));
                    }).catch(() => {
                        if (!cancelled) setAgentTools([]);
                    });
                }
            })
            .catch(() => {
                if (!cancelled) {
                    import("@/components/ai/ai-constants").then((mod) => {
                        setAgentTools(mod.getFallbackTools(selectedAgent));
                    }).catch(() => setAgentTools([]));
                }
            })
            .finally(() => {
                if (!cancelled) setToolsLoading(false);
            });
        return () => {
            cancelled = true;
        };
    }, [selectedAgent]);

    const handleSelectModel = (id: string) => {
        setSelectedModel(id);
        if (typeof window !== "undefined") {
            try {
                localStorage.setItem(PREFERRED_MODEL_KEY, id);
            } catch {
                // A model choice is an optional local preference.
            }
        }
        const selected = availableModels.find((model) => model.id === id);
        toast.success(`Active model: ${selected?.name ?? id}`);
    };

    const handleSelectConversation = async (id: string) => {
        if (id === conversationId && messages.length > 0) {
            setMobileDrawerOpen(false);
            return;
        }

        try {
            const res = await fetch(`/administrators/ai/conversations/${id}`, {
                headers: { "X-Requested-With": "XMLHttpRequest" },
            });
            if (!res.ok) {
                throw new Error("Could not load conversation.");
            }
            const data = await res.json();
            loadConversationMessages(data.messages || [], data.conversation.id);

            if (typeof window !== "undefined") {
                window.history.pushState(null, "", `/administrators/ai?conversation=${id}`);
            }
            setMobileDrawerOpen(false);
        } catch (error: unknown) {
            toast.error(error instanceof Error ? error.message : "Failed to load conversation history.");
        }
    };

    const handleNewChat = () => {
        clearChat();
        if (typeof window !== "undefined") {
            window.history.pushState(null, "", "/administrators/ai");
        }
        setMobileDrawerOpen(false);
    };

    const handleRenameConversation = async (id: string, newTitle: string) => {
        const csrfToken = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || "";
        const res = await fetch(`/administrators/ai/conversations/${id}`, {
            method: "PATCH",
            headers: {
                "Content-Type": "application/json",
                "X-Requested-With": "XMLHttpRequest",
                "X-CSRF-TOKEN": csrfToken,
            },
            body: JSON.stringify({ title: newTitle }),
        });

        if (!res.ok) {
            throw new Error("Failed to rename conversation.");
        }

        setConversations((prev) =>
            prev.map((c) => (c.id === id ? { ...c, title: newTitle, updated_at: new Date().toISOString() } : c)),
        );
        toast.success("Conversation renamed.");
    };

    const handleDeleteConversation = async (id: string) => {
        const csrfToken = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || "";
        const res = await fetch(`/administrators/ai/conversations/${id}`, {
            method: "DELETE",
            headers: {
                "X-Requested-With": "XMLHttpRequest",
                "X-CSRF-TOKEN": csrfToken,
            },
        });

        if (!res.ok) {
            throw new Error("Failed to delete conversation.");
        }

        setConversations((prev) => prev.filter((c) => c.id !== id));
        if (conversationId === id) {
            handleNewChat();
        }
        toast.success("Conversation deleted.");
    };

    const handleFilesAdded = (newFiles: File[]) => {
        const availableSlots = Math.max(5 - selectedFiles.length, 0);
        if (availableSlots === 0) {
            toast.error("You can attach up to 5 files per message.");
            return;
        }

        const files = newFiles.slice(0, availableSlots);
        if (files.length < newFiles.length) {
            toast.error("You can attach up to 5 files per message.");
        }

        const unsupported = files.filter((file) => file.type && !ACCEPTED_ATTACHMENT_TYPE_SET.has(file.type));
        if (unsupported.length > 0) {
            toast.error("Only documents, spreadsheets, text files, and PNG, JPEG, or WebP images can be attached.");
            return;
        }

        const oversized = files.filter((f) => f.size > 20 * 1024 * 1024);
        if (oversized.length > 0) {
            toast.error("Files larger than 20MB cannot be uploaded.");
            return;
        }
        setSelectedFiles((prev) => [...prev, ...files]);
        toast.success(`Attached ${files.length} ${files.length === 1 ? "file" : "files"}.`);
    };

    const handleFileInputChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        if (e.target.files && e.target.files.length > 0) {
            handleFilesAdded(Array.from(e.target.files));
            e.target.value = "";
        }
    };

    const removeFile = (index: number) => {
        setSelectedFiles((prev) => prev.filter((_, i) => i !== index));
    };

    const handleSend = () => {
        if ((!input.trim() && selectedFiles.length === 0) || isLoading) return;
        sendPrompt(input, selectedFiles, {
            model: selectedModel.includes(":") ? selectedModel.split(":").slice(1).join(":") : selectedModel || undefined,
            provider: activeModel?.provider,
            supportsDocuments: activeModel?.supports_documents,
            thinking: isThinkingMode,
            search: isSearchMode,
        });
        setSelectedFiles([]);
    };

    const handleSelectSuggestion = (suggestion: PromptSuggestion) => {
        setSelectedAgent(suggestion.agent);
        sendPrompt(suggestion.prompt, undefined, {
            agent: suggestion.agent,
            model: selectedModel || undefined,
            thinking: isThinkingMode,
            search: isSearchMode,
        });
    };

    const toggleFullscreen = () => {
        if (!document.fullscreenElement) {
            document.documentElement.requestFullscreen().catch(() => undefined);
            setIsFullscreen(true);
        } else {
            document.exitFullscreen().catch(() => undefined);
            setIsFullscreen(false);
        }
    };

    const copyTranscript = () => {
        if (messages.length === 0) {
            toast.info("No messages in transcript to copy.");
            return;
        }
        const text = messages
            .map((m) => `${m.role.toUpperCase()}:\n${m.content}\n`)
            .join("\n---\n\n");
        navigator.clipboard.writeText(text);
        toast.success("Transcript copied to clipboard.");
    };

    const exportAsMarkdown = () => {
        if (messages.length === 0) {
            toast.info("No messages to export.");
            return;
        }
        const text = `# ${activeConversationTitle}\n\n` + messages
            .map((m) => `### ${m.role === "user" ? user.name || "Administrator" : activeAgentMeta.label}\n\n${m.content}\n`)
            .join("\n---\n\n");
        const blob = new Blob([text], { type: "text/markdown;charset=utf-8" });
        const url = URL.createObjectURL(blob);
        const a = document.createElement("a");
        a.href = url;
        a.download = `${activeConversationTitle.toLowerCase().replace(/[^a-z0-9]/g, "-")}.md`;
        a.click();
        URL.revokeObjectURL(url);
        toast.success("Transcript downloaded as Markdown.");
    };

    const getFileIcon = (file: File) => {
        const ext = file.name.split(".").pop()?.toLowerCase();
        if (["xlsx", "xls", "csv"].includes(ext || "")) {
            return <FileSpreadsheet className="size-3.5 text-emerald-500" />;
        }
        if (["pdf"].includes(ext || "")) {
            return <FileText className="size-3.5 text-rose-500" />;
        }
        if (["doc", "docx", "odt", "rtf"].includes(ext || "")) {
            return <FileText className="size-3.5 text-blue-500" />;
        }
        if (["jpg", "jpeg", "png", "webp", "gif"].includes(ext || "")) {
            return <FileImage className="size-3.5 text-indigo-500" />;
        }
        return <FileCode className="text-muted-foreground size-3.5" />;
    };

    const formatMessageTime = (date?: Date | string) => {
        if (!date) return "";
        try {
            const d = typeof date === "string" ? new Date(date) : date;
            return d.toLocaleTimeString([], { hour: "numeric", minute: "2-digit" });
        } catch {
            return "";
        }
    };

    const activeAgentMeta = ADMIN_AGENTS.find((a) => a.key === selectedAgent) || ADMIN_AGENTS[0];
    const ActiveAgentIcon = activeAgentMeta.icon;

    const firstName = user.name?.split(" ")[0] || "Administrator";
    const activeModel = availableModels.find((model) => model.id === selectedModel);
    const activeModelName = activeModel?.name || "Auto select";

    const activeConversation = conversations.find((c) => c.id === conversationId);
    const activeConversationTitle = activeConversation?.title || (messages.length > 0 ? "Conversation" : "New Chat");

    // Shared send options for resend / regenerate, mirroring handleSend.
    const resendOptions = () => ({
        model: selectedModel.includes(":") ? selectedModel.split(":").slice(1).join(":") : selectedModel || undefined,
        provider: activeModel?.provider,
        supportsDocuments: activeModel?.supports_documents,
        thinking: isThinkingMode,
        search: isSearchMode,
    });

    // Context percentage approximation based on token budget
    const totalChars = messages.reduce((acc, m) => acc + (m.content?.length || 0), 0) + input.length;
    const contextPercentage = Math.min(94, Math.max(16, Math.round((totalChars / 12000) * 100)));

    const maxContainerWidthClass =
        previewWidth === "mobile"
            ? "max-w-md"
            : previewWidth === "tablet"
              ? "max-w-2xl"
              : "max-w-4xl";

    return (
        <AdminLayout user={user} immersive>
            <Head title={`AI Chat - ${activeConversationTitle}`} />

            {/* Hidden native file input for robust file selection */}
            <input
                ref={fileInputRef}
                type="file"
                multiple
                accept={ACCEPTED_ATTACHMENT_TYPES}
                className="hidden"
                onChange={handleFileInputChange}
            />

            <div className="bg-background text-foreground flex h-full min-h-0 w-full flex-col overflow-hidden">
                {/* ------------------------------------------------------------- */}
                {/* Top Copilot Bar (Matches ReUI @reui/ai-chat-1 header layout)    */}
                {/* ------------------------------------------------------------- */}
                <header className="border-border/70 bg-background/95 sticky top-0 z-30 flex h-13 shrink-0 items-center justify-between border-b px-3 backdrop-blur-md sm:px-4">
                    {/* Left: Thread Title Switcher Dropdown */}
                    <div className="flex min-w-0 items-center gap-2">
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <button
                                    type="button"
                                    className="hover:bg-muted/70 text-foreground group flex max-w-[200px] items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-sm font-semibold tracking-tight transition-colors sm:max-w-xs md:max-w-md"
                                >
                                    <span className="truncate">{activeConversationTitle}</span>
                                    <ChevronDown className="text-muted-foreground group-hover:text-foreground size-3.5 shrink-0 transition-transform" />
                                </button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="start" className="w-80 p-1.5 shadow-xl">
                                <div className="p-1">
                                    <div className="relative">
                                        <Search className="text-muted-foreground pointer-events-none absolute top-2.5 left-2.5 size-3.5" />
                                        <Input
                                            value={searchQuery}
                                            onChange={(e) => handleSearchChange(e.target.value)}
                                            placeholder="Search conversations..."
                                            className="h-8 pl-8 text-xs"
                                        />
                                    </div>
                                </div>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem onClick={handleNewChat} className="text-primary font-medium">
                                    <Plus className="mr-2 size-3.5" />
                                    New Chat
                                </DropdownMenuItem>
                                <DropdownMenuItem onClick={() => setMobileDrawerOpen(true)}>
                                    <Edit3 className="mr-2 size-3.5" />
                                    Open Conversation Manager
                                </DropdownMenuItem>
                                <DropdownMenuSeparator />
                                <DropdownMenuLabel className="text-[11px] font-semibold uppercase tracking-wider">
                                    Recent Threads
                                </DropdownMenuLabel>
                                <div className="max-h-64 overflow-y-auto">
                                    {conversations.length > 0 ? (
                                        conversations.slice(0, 10).map((c) => (
                                            <DropdownMenuItem
                                                key={c.id}
                                                onClick={() => handleSelectConversation(c.id)}
                                                className={cn("justify-between text-xs", c.id === conversationId && "bg-muted font-medium")}
                                            >
                                                <span className="truncate">{c.title}</span>
                                                {c.id === conversationId && <Check className="text-primary size-3 shrink-0" />}
                                            </DropdownMenuItem>
                                        ))
                                    ) : (
                                        <div className="text-muted-foreground p-2 text-center text-xs">No recent threads found</div>
                                    )}
                                </div>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>

                    {/* Right: Actions, Device Toggle, ReUI Tag, Model, Options */}
                    <div className="flex shrink-0 items-center gap-1 sm:gap-1.5">
                        {/* Info Action */}
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            onClick={() => setIsInfoDialogOpen(true)}
                            className="text-muted-foreground hover:text-foreground hidden size-8 rounded-lg sm:inline-flex"
                            title="AI Copilot Information"
                        >
                            <Info className="size-4" />
                        </Button>

                        {/* Theme Toggle */}
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            onClick={toggleTheme}
                            className="text-muted-foreground hover:text-foreground size-8 rounded-lg"
                            title={isDark ? "Switch to light theme" : "Switch to dark theme"}
                        >
                            {isDark ? <Sun className="size-4" /> : <Moon className="size-4" />}
                        </Button>

                        {/* Reset / Clear Chat */}
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            onClick={clearChat}
                            className="text-muted-foreground hover:text-foreground hidden size-8 rounded-lg sm:inline-flex"
                            title="Reset current conversation"
                        >
                            <RotateCcw className="size-4" />
                        </Button>

                        {/* Fullscreen Toggle */}
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            onClick={toggleFullscreen}
                            className="text-muted-foreground hover:text-foreground hidden size-8 rounded-lg md:inline-flex"
                            title={isFullscreen ? "Exit Fullscreen" : "Enter Fullscreen"}
                        >
                            {isFullscreen ? <Minimize2 className="size-4" /> : <Maximize2 className="size-4" />}
                        </Button>

                        {/* Device Viewport Preview Switches */}
                        <div className="border-border/60 bg-muted/30 hidden items-center rounded-lg border p-0.5 lg:flex">
                            <button
                                type="button"
                                onClick={() => setPreviewWidth("desktop")}
                                className={cn(
                                    "text-muted-foreground hover:text-foreground rounded p-1 transition-colors",
                                    previewWidth === "desktop" && "bg-background text-foreground shadow-xs",
                                )}
                                title="Desktop View"
                            >
                                <Monitor className="size-3.5" />
                            </button>
                            <button
                                type="button"
                                onClick={() => setPreviewWidth("tablet")}
                                className={cn(
                                    "text-muted-foreground hover:text-foreground rounded p-1 transition-colors",
                                    previewWidth === "tablet" && "bg-background text-foreground shadow-xs",
                                )}
                                title="Tablet View"
                            >
                                <Tablet className="size-3.5" />
                            </button>
                            <button
                                type="button"
                                onClick={() => setPreviewWidth("mobile")}
                                className={cn(
                                    "text-muted-foreground hover:text-foreground rounded p-1 transition-colors",
                                    previewWidth === "mobile" && "bg-background text-foreground shadow-xs",
                                )}
                                title="Mobile View"
                            >
                                <Smartphone className="size-3.5" />
                            </button>
                        </div>

                        {/* ReUI Component Badge */}
                        <button
                            type="button"
                            onClick={() => {
                                navigator.clipboard.writeText("@reui/ai-chat-1");
                                toast.success("Copied component identifier: @reui/ai-chat-1");
                            }}
                            className="border-border/60 bg-muted/40 hover:bg-muted text-muted-foreground hover:text-foreground hidden items-center gap-1.5 rounded-md border px-2 py-1 font-mono text-[11px] transition-colors xl:flex"
                            title="Click to copy component identifier"
                        >
                            <span className="size-2 rounded-xs bg-indigo-500 shadow-xs" />
                            <span>@reui/ai-chat-1</span>
                            <Copy className="size-2.5 opacity-60" />
                        </button>

                        {/* New Chat Button */}
                        <Button
                            type="button"
                            variant="outline"
                            size="icon"
                            onClick={handleNewChat}
                            className="border-border/70 size-8 rounded-lg"
                            title="Start new chat"
                        >
                            <Plus className="size-4" />
                        </Button>

                        {/* Bookmark Button */}
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            onClick={() => {
                                setIsBookmarked(!isBookmarked);
                                toast.success(isBookmarked ? "Thread removed from bookmarks." : "Thread saved to bookmarks.");
                            }}
                            className={cn(
                                "size-8 rounded-lg transition-colors",
                                isBookmarked ? "text-amber-500 hover:text-amber-600" : "text-muted-foreground hover:text-foreground",
                            )}
                            title="Bookmark conversation"
                        >
                            <Bookmark className={cn("size-4", isBookmarked && "fill-current")} />
                        </Button>

                        {/* Model Selector Pill */}
                        <Popover>
                            <PopoverTrigger asChild>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    className="border-border/70 h-8 max-w-28 shrink-0 gap-1.5 rounded-lg px-2 text-xs font-semibold sm:max-w-48 sm:px-2.5"
                                    aria-label={`Choose an AI model. ${activeModelName} is active.`}
                                >
                                    <span className="truncate">{activeModelName}</span>
                                    <ChevronDown className="text-muted-foreground size-3 shrink-0" />
                                </Button>
                            </PopoverTrigger>
                            <PopoverContent
                                align="end"
                                className="w-[min(26rem,calc(100vw-2rem))] p-3 shadow-xl"
                                onOpenAutoFocus={(event) => {
                                    event.preventDefault();
                                    requestAnimationFrame(() => modelSearchRef.current?.focus());
                                }}
                                onCloseAutoFocus={(event) => event.preventDefault()}
                            >
                                {serverDefaultModel && (
                                    <p className="px-1 pb-2 text-[11px] text-muted-foreground">
                                        Global default: <span className="font-mono text-foreground">{serverDefaultModel}</span>
                                        {primaryProvider ? ` (via ${primaryProvider})` : ""} — set per-provider in System Management → AI.
                                    </p>
                                )}
                                {availableModels.length > 0 ? (
                                    <ModelSelector
                                        models={availableModels}
                                        value={selectedModel}
                                        onChange={handleSelectModel}
                                        searchInputRef={modelSearchRef}
                                    />
                                ) : (
                                    <p className="text-muted-foreground p-2 text-sm">No AI models are configured yet.</p>
                                )}
                            </PopoverContent>
                        </Popover>

                        {/* More Menu Dropdown */}
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    className="text-muted-foreground hover:text-foreground size-8 rounded-lg"
                                >
                                    <MoreVertical className="size-4" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" className="w-48 text-xs shadow-xl">
                                <DropdownMenuGroup>
                                    <DropdownMenuItem
                                        onClick={() => {
                                            setRenameTitleInput(activeConversationTitle);
                                            setIsRenameDialogOpen(true);
                                        }}
                                        disabled={!conversationId}
                                    >
                                        <Edit3 className="mr-2 size-3.5" />
                                        Rename Thread
                                    </DropdownMenuItem>
                                    <DropdownMenuItem onClick={copyTranscript} disabled={messages.length === 0}>
                                        <Copy className="mr-2 size-3.5" />
                                        Copy Transcript
                                    </DropdownMenuItem>
                                    <DropdownMenuItem onClick={exportAsMarkdown} disabled={messages.length === 0}>
                                        <Download className="mr-2 size-3.5" />
                                        Export as Markdown
                                    </DropdownMenuItem>
                                </DropdownMenuGroup>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem
                                    onClick={() => {
                                        if (conversationId) {
                                            handleDeleteConversation(conversationId);
                                        }
                                    }}
                                    disabled={!conversationId}
                                    className="text-destructive focus:bg-destructive/10 focus:text-destructive"
                                >
                                    <Trash2 className="mr-2 size-3.5" />
                                    Delete Thread
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                </header>

                {/* ------------------------------------------------------------- */}
                {/* Main Content Workspace (Full Width Canvas)                     */}
                {/* ------------------------------------------------------------- */}
                <div className="relative flex min-h-0 flex-1 flex-col overflow-hidden">
                    {/* Drawer for Mobile / Conversation History */}
                    <Sheet open={mobileDrawerOpen} onOpenChange={setMobileDrawerOpen}>
                        <SheetContent side="left" className="w-80 p-0 shadow-2xl">
                            <SheetTitle className="sr-only">Conversation History</SheetTitle>
                            <AiConversationSidebar
                                conversations={conversations}
                                activeConversationId={conversationId}
                                onSelectConversation={handleSelectConversation}
                                onNewChat={handleNewChat}
                                onRenameConversation={handleRenameConversation}
                                onDeleteConversation={handleDeleteConversation}
                                searchQuery={searchQuery}
                                onSearchChange={handleSearchChange}
                                isLoading={isLoadingConversations}
                                hasMore={hasMore}
                                isLoadingMore={isLoadingMore}
                                onLoadMore={handleLoadMore}
                                totalConversations={totalConversations}
                                newChatPlacement="bottom"
                                className="h-full w-full border-r-0"
                            />
                        </SheetContent>
                    </Sheet>

                    {messages.length === 0 ? (
                        /* ------------------------------------------------------- */
                        /* Starter Hero State (When no messages are present)       */
                        /* ------------------------------------------------------- */
                        <div className="flex min-h-0 flex-1 overflow-y-auto px-4 py-8 sm:px-6">
                            <div className={cn("mx-auto flex w-full flex-col items-center pt-8 pb-16 text-center transition-all duration-300", maxContainerWidthClass)}>
                                <div className="mb-6 flex items-center justify-center">
                                    <Shdr14
                                        size={140}
                                        state={isLoading ? "thinking" : "idle"}
                                        ariaLabel="KoAkademy AI copilot"
                                        className="transition-transform duration-300 hover:scale-105"
                                    />
                                </div>
                                <h1 className="text-foreground text-2xl font-semibold tracking-tight sm:text-3xl">
                                    {getGreeting(firstName)}
                                </h1>
                                <p className="text-muted-foreground mt-1.5 text-base font-normal sm:text-lg">
                                    How can I assist your administration today?
                                </p>

                                {/* Center Composer Container */}
                                <div className="mt-8 w-full">
                                    {renderComposerBox()}
                                </div>

                                {/* Suggested Prompts */}
                                <div className="mt-5 flex w-full flex-wrap justify-center gap-2">
                                    {DEFAULT_PROMPT_SUGGESTIONS.map((suggestion) => {
                                        const Icon = suggestion.icon;
                                        return (
                                            <button
                                                key={suggestion.title}
                                                type="button"
                                                onClick={() => handleSelectSuggestion(suggestion)}
                                                className="border-border/70 bg-card hover:bg-muted text-muted-foreground hover:text-foreground inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-xs font-medium shadow-xs transition-colors"
                                            >
                                                <Icon className="size-3.5 text-indigo-500" />
                                                <span>{suggestion.title}</span>
                                            </button>
                                        );
                                    })}
                                </div>
                            </div>
                        </div>
                    ) : (
                        /* ------------------------------------------------------- */
                        /* Active Chat Transcript (Matching ReUI @reui/ai-chat-1)  */
                        /* ------------------------------------------------------- */
                        <div className="relative flex min-h-0 flex-1 flex-col overflow-hidden">
                            {/* Messages Scroll Area */}
                            <div ref={chatContainerRef} className="flex-1 overflow-y-auto px-4 py-6 sm:px-6 md:px-8">
                                <div className={cn("mx-auto space-y-6 transition-all duration-300", maxContainerWidthClass)}>
                                    {messages.map((message) => {
                                        const isUser = message.role === "user";

                                        if (isUser) {
                                            return (
                                                <div key={message.id} className="flex justify-end items-end gap-3">
                                                    <div className="flex flex-col items-end gap-1.5 max-w-[85%] sm:max-w-[75%]">
                                                        <div className="rounded-2xl rounded-tr-xs bg-zinc-800 dark:bg-zinc-800 text-zinc-100 border border-zinc-700/60 px-4 py-3 text-sm shadow-sm whitespace-pre-wrap leading-relaxed">
                                                            <p>{message.content}</p>
                                                            {message.attachments && message.attachments.length > 0 && (
                                                                <div className="flex flex-wrap gap-1.5 pt-2">
                                                                    {message.attachments.map((att, idx) => (
                                                                        <div
                                                                            key={idx}
                                                                            className="flex items-center gap-1 rounded-md bg-white/10 px-2 py-0.5 font-mono text-xs text-zinc-200"
                                                                        >
                                                                            <Paperclip className="size-3 text-zinc-400" />
                                                                            <span className="max-w-[160px] truncate">{att.name}</span>
                                                                        </div>
                                                                    ))}
                                                                </div>
                                                            )}
                                                        </div>
                                                        <div className="flex items-center gap-1 text-[11px] text-muted-foreground pr-1">
                                                            {message.createdAt && <span>{formatMessageTime(message.createdAt)}</span>}
                                                            <UserMessageActions
                                                                content={message.content}
                                                                disabled={isLoading}
                                                                onResend={() => resendUserMessage(message.id, resendOptions())}
                                                            />
                                                        </div>
                                                    </div>
                                                    <Avatar className="size-8 rounded-full border border-border/70 shrink-0 self-end mb-4">
                                                        <AvatarImage src={user.avatar || undefined} alt={user.name} />
                                                        <AvatarFallback className="bg-primary/10 text-primary text-xs font-semibold">
                                                            {user.name?.slice(0, 2).toUpperCase() || "AD"}
                                                        </AvatarFallback>
                                                    </Avatar>
                                                </div>
                                            );
                                        }

                                        // Assistant Message: Clean, natural canvas with Avatar on the left and ReUI code blocks
                                        // Skip the transient empty placeholder bubble while streaming has
                                        // not yet produced content / reasoning / tool calls — the dedicated
                                        // thinking indicator below covers that state (fixes empty bubble in screenshot).
                                        const isStreamingThis = isLoading && message.id === messages.at(-1)?.id;
                                        const hasVisiblePayload =
                                            (message.content && message.content.trim().length > 0) ||
                                            (message.reasoning && message.reasoning.trim().length > 0) ||
                                            (message.toolCalls && message.toolCalls.length > 0) ||
                                            (message.pendingApprovals && message.pendingApprovals.length > 0);
                                        if (!hasVisiblePayload && isStreamingThis) {
                                            return null;
                                        }
                                        return (
                                            <div key={message.id} className="flex items-start gap-3.5">
                                                <div className="size-7 rounded-full bg-zinc-800 dark:bg-zinc-800 border border-zinc-700/60 text-zinc-200 flex items-center justify-center shrink-0 mt-0.5 shadow-xs">
                                                    <ActiveAgentIcon className="size-3.5 text-indigo-400" />
                                                </div>

                                                <div className="flex-1 min-w-0 space-y-3 pt-0.5 text-sm leading-relaxed text-foreground">
                                                    <ChatMessageFormatter
                                                        content={message.content}
                                                        reasoning={message.reasoning}
                                                        toolCalls={message.toolCalls}
                                                        sources={message.sources}
                                                        isStreaming={isStreamingThis}
                                                    />

                                                    {/* ReUI reply actions: copy + regenerate */}
                                                    {!isStreamingThis && (
                                                        <AssistantMessageActions
                                                            content={message.content}
                                                            disabled={isLoading}
                                                            onRegenerate={() => regenerateAssistant(message.id, resendOptions())}
                                                        />
                                                    )}

                                                    {/* Pending Approvals */}
                                                    {message.pendingApprovals?.map((approval) => (
                                                        <ApprovalCard
                                                            key={approval.id}
                                                            approval={approval}
                                                            onDecision={submitDecision}
                                                            disabled={isLoading}
                                                        />
                                                    ))}
                                                </div>
                                            </div>
                                        );
                                    })}

                                    {/* Thinking / Streaming Indicator — shows live reasoning status.
                                        When thinking mode is on, label it Deep reasoning; when search
                                        mode is on, label it Searching campus records; otherwise the
                                        active specialist analysis label. */}
                                    {isLoading && (
                                        <div className="flex items-center gap-3 pl-1 text-xs text-muted-foreground">
                                            <div className="size-7 rounded-full bg-zinc-800 dark:bg-zinc-800 border border-zinc-700/60 text-zinc-200 flex items-center justify-center shrink-0">
                                                <Sparkles className="size-3.5 text-indigo-400 animate-spin" />
                                            </div>
                                            <span className="font-medium animate-pulse">
                                                {isThinkingMode && isSearchMode
                                                    ? `${activeAgentMeta.label} is reasoning and searching campus records...`
                                                    : isThinkingMode
                                                      ? `${activeAgentMeta.label} is thinking (deep reasoning)...`
                                                      : isSearchMode
                                                        ? `${activeAgentMeta.label} is searching campus records...`
                                                        : `${activeAgentMeta.label} is analyzing campus data...`}
                                            </span>
                                        </div>
                                    )}

                                    {/* Error Banner */}
                                    {lastError && (
                                        <div className="border-destructive/30 bg-destructive/10 text-destructive flex items-start justify-between gap-3 rounded-xl border p-3.5 text-xs shadow-xs">
                                            <div className="flex items-start gap-2">
                                                <AlertCircle className="mt-0.5 size-4 shrink-0" />
                                                <div>
                                                    <span className="block font-semibold">{lastError.title}</span>
                                                    <p className="text-destructive/90 mt-0.5">{lastError.message}</p>
                                                </div>
                                            </div>
                                            {lastError.retryPrompt && (
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="sm"
                                                    className="border-destructive/30 hover:bg-destructive/15 h-7 shrink-0 text-xs"
                                                    onClick={() => {
                                                        clearError();
                                                        sendPrompt(lastError.retryPrompt!, selectedFiles, {
                                                            model: selectedModel.includes(":")
                                                                ? selectedModel.split(":").slice(1).join(":")
                                                                : selectedModel || undefined,
                                                            provider: activeModel?.provider,
                                                            supportsDocuments: activeModel?.supports_documents,
                                                            thinking: isThinkingMode,
                                                            search: isSearchMode,
                                                        });
                                                    }}
                                                >
                                                    <RefreshCw className="mr-1 size-3" />
                                                    Retry
                                                </Button>
                                            )}
                                        </div>
                                    )}

                                    <div ref={messagesEndRef} />
                                </div>
                            </div>

                            {/* Docked Bottom Bar */}
                            <div className="shrink-0 px-4 pt-2 pb-4 sm:px-6 md:px-8">
                                <div className={cn("mx-auto transition-all duration-300", maxContainerWidthClass)}>
                                    {/* Terms & Privacy Banner (Float pill above composer) */}
                                    {showTermsBanner && (
                                        <div className="flex justify-center mb-3">
                                            <div className="bg-zinc-900/90 dark:bg-zinc-900/95 text-zinc-300 border border-zinc-800/90 rounded-full px-4 py-1.5 text-xs inline-flex items-center gap-2 shadow-lg backdrop-blur-md">
                                                <span>
                                                    Make sure you agree to our{" "}
                                                    <button
                                                        type="button"
                                                        onClick={() => setIsTermsDialogOpen(true)}
                                                        className="underline underline-offset-2 hover:text-white font-medium"
                                                    >
                                                        Terms
                                                    </button>{" "}
                                                    and our{" "}
                                                    <button
                                                        type="button"
                                                        onClick={() => setIsTermsDialogOpen(true)}
                                                        className="underline underline-offset-2 hover:text-white font-medium"
                                                    >
                                                        Privacy Policy
                                                    </button>
                                                </span>
                                                <button
                                                    type="button"
                                                    onClick={() => setShowTermsBanner(false)}
                                                    className="text-zinc-500 hover:text-zinc-200 ml-1 transition-colors"
                                                    aria-label="Dismiss banner"
                                                >
                                                    <X className="size-3.5" />
                                                </button>
                                            </div>
                                        </div>
                                    )}

                                    {renderComposerBox()}
                                </div>
                            </div>
                        </div>
                    )}
                </div>
            </div>

            {/* ------------------------------------------------------------- */}
            {/* Rename Conversation Dialog                                     */}
            {/* ------------------------------------------------------------- */}
            <Dialog open={isRenameDialogOpen} onOpenChange={setIsRenameDialogOpen}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Rename Conversation</DialogTitle>
                        <DialogDescription>Enter a new title for this conversation thread.</DialogDescription>
                    </DialogHeader>
                    <div className="py-2">
                        <Input
                            value={renameTitleInput}
                            onChange={(e) => setRenameTitleInput(e.target.value)}
                            placeholder="Conversation title..."
                            className="w-full text-sm"
                            onKeyDown={(e) => {
                                if (e.key === "Enter" && renameTitleInput.trim() && conversationId) {
                                    handleRenameConversation(conversationId, renameTitleInput.trim());
                                    setIsRenameDialogOpen(false);
                                }
                            }}
                        />
                    </div>
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button type="button" variant="outline">
                                Cancel
                            </Button>
                        </DialogClose>
                        <Button
                            type="button"
                            onClick={() => {
                                if (renameTitleInput.trim() && conversationId) {
                                    handleRenameConversation(conversationId, renameTitleInput.trim());
                                    setIsRenameDialogOpen(false);
                                }
                            }}
                            disabled={!renameTitleInput.trim()}
                        >
                            Save
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* ------------------------------------------------------------- */}
            {/* Copilot Information Dialog                                     */}
            {/* ------------------------------------------------------------- */}
            <Dialog open={isInfoDialogOpen} onOpenChange={setIsInfoDialogOpen}>
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2">
                            <Sparkles className="size-5 text-indigo-500" />
                            KoAkademy Administrative Copilot
                        </DialogTitle>
                        <DialogDescription>
                            Powered by enterprise multi-model orchestration with real-time institutional tool execution.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-3 py-2 text-xs text-muted-foreground leading-relaxed">
                        <div className="rounded-lg border border-border/60 bg-muted/30 p-3">
                            <span className="font-semibold text-foreground block mb-1">Active Specialist Agent</span>
                            <p>{activeAgentMeta.label} ({activeAgentMeta.badge}): {activeAgentMeta.description}</p>
                        </div>
                        <div className="rounded-lg border border-border/60 bg-muted/30 p-3">
                            <span className="font-semibold text-foreground block mb-1">Active Model</span>
                            <p>{activeModelName} {activeModel?.provider ? `via ${activeModel.provider}` : ""}</p>
                        </div>
                        <div className="rounded-lg border border-border/60 bg-muted/30 p-3">
                            <span className="font-semibold text-foreground block mb-1">Capabilities</span>
                            <ul className="list-disc pl-4 space-y-1 mt-1">
                                <li>Automatic curriculum analysis and verification</li>
                                <li>Administrative report generation & 1-click PDF download</li>
                                <li>Interactive enrollment and financial chart visualization</li>
                                <li>Human-in-the-loop critical action approval</li>
                            </ul>
                        </div>
                    </div>
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button type="button">Close</Button>
                        </DialogClose>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* ------------------------------------------------------------- */}
            {/* Terms and Privacy Dialog                                       */}
            {/* ------------------------------------------------------------- */}
            <Dialog open={isTermsDialogOpen} onOpenChange={setIsTermsDialogOpen}>
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Terms of Use & Privacy Policy</DialogTitle>
                        <DialogDescription>
                            Usage policy for institutional administrative AI operations.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-3 py-2 text-xs text-muted-foreground leading-relaxed max-h-72 overflow-y-auto">
                        <p>
                            KoAkademy Administrative AI Copilot is intended exclusively for authorized staff and administrators.
                            All institutional queries are processed securely and audited in compliance with system guidelines.
                        </p>
                        <p className="font-medium text-foreground">Data Privacy & Security:</p>
                        <ul className="list-disc pl-4 space-y-1">
                            <li>Student and personnel personal identifiers are redacted or encrypted where applicable.</li>
                            <li>Financial records and sensitive grade updates require explicit administrator confirmation.</li>
                            <li>Conversations and generated artifacts are logged for institutional auditing.</li>
                        </ul>
                    </div>
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button type="button">I Understand</Button>
                        </DialogClose>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AdminLayout>
    );

    /* --------------------------------------------------------------------- */
    /* Render Composer Box (Matches @reui/ai-chat-1 screenshot design)        */
    /* --------------------------------------------------------------------- */
    function renderComposerBox() {
        return (
            <div className="w-full">
                {/* File Attachments Pills */}
                {selectedFiles.length > 0 && (
                    <div className="mb-2 flex flex-wrap gap-2 px-1">
                        {selectedFiles.map((file, index) => (
                            <div
                                key={`${file.name}-${index}`}
                                className="border-zinc-800 bg-zinc-900/90 dark:bg-zinc-900 text-zinc-200 flex items-center gap-1.5 rounded-lg border px-2.5 py-1 text-xs shadow-xs"
                            >
                                {getFileIcon(file)}
                                <span className="max-w-40 truncate font-medium">{file.name}</span>
                                <button
                                    type="button"
                                    onClick={() => removeFile(index)}
                                    className="text-zinc-400 hover:text-white transition-colors"
                                    aria-label={`Remove ${file.name}`}
                                >
                                    <X className="size-3" />
                                </button>
                            </div>
                        ))}
                    </div>
                )}

                {/* Main Dark Floating Composer Frame */}
                <div className="rounded-2xl border border-zinc-800/80 bg-zinc-900/90 dark:bg-zinc-900/95 shadow-2xl backdrop-blur-xl p-3 text-zinc-100 transition-all">
                    {/* Top Context Bar */}
                    {showContextBar && (
                        <div className="flex items-center justify-between text-xs text-zinc-400 px-2 pb-2.5 mb-1 border-b border-zinc-800/60">
                            <div className="flex items-center gap-1.5 font-sans">
                                <span className="text-zinc-400 font-mono text-sm leading-none">~</span>
                                <span>Context {contextPercentage} percent full</span>
                                <span className="text-zinc-600">•</span>
                                <DropdownMenu>
                                    <DropdownMenuTrigger asChild>
                                        <button
                                            type="button"
                                            className="inline-flex items-center gap-1 text-zinc-400 hover:text-zinc-200 transition-colors"
                                        >
                                            <span>Plan limit resets Friday 12:00 PM</span>
                                            <ChevronDown className="size-3 opacity-70" />
                                        </button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="start" className="w-64 text-xs shadow-xl p-2">
                                        <p className="font-semibold text-foreground">Enterprise Administrator Tier</p>
                                        <p className="text-muted-foreground mt-1">Unlimited context tokens with priority GPU inference throughput.</p>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </div>
                            <button
                                type="button"
                                onClick={() => setShowContextBar(false)}
                                className="text-zinc-500 hover:text-zinc-300 transition-colors"
                                title="Dismiss context indicator"
                            >
                                <X className="size-3.5" />
                            </button>
                        </div>
                    )}

                    {/* Textarea Input */}
                    <textarea
                        ref={textareaRef}
                        value={input}
                        onChange={(e) => setInput(e.target.value)}
                        placeholder="Ask anything, or describe what you want changed..."
                        rows={2}
                        onKeyDown={(e) => {
                            if (e.key === "Enter" && !e.shiftKey) {
                                e.preventDefault();
                                handleSend();
                            }
                        }}
                        className="w-full bg-transparent border-none text-sm placeholder:text-zinc-500 focus:outline-none focus:ring-0 resize-none min-h-[56px] max-h-48 py-1.5 px-2 text-zinc-100 leading-relaxed"
                    />

                    {/* Bottom Toolbar Actions */}
                    <div className="flex items-center justify-between pt-1">
                        {/* Left action buttons */}
                        <div className="flex items-center gap-1 sm:gap-1.5">
                            {/* Attach File Button */}
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                onClick={() => fileInputRef.current?.click()}
                                className="size-7 rounded-lg text-zinc-400 hover:text-zinc-100 hover:bg-zinc-800 transition-colors"
                                title="Attach documents, spreadsheets or images"
                            >
                                <Plus className="size-4" />
                            </Button>

                            {/* Think Button */}
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                onClick={() => {
                                    const next = !isThinkingMode;
                                    setIsThinkingMode(next);
                                    toast.info(next ? "Thinking mode enabled (Deep reasoning)" : "Standard reasoning mode");
                                }}
                                className={cn(
                                    "h-7 px-2.5 rounded-lg text-xs font-medium gap-1.5 transition-colors",
                                    isThinkingMode
                                        ? "bg-zinc-800 text-zinc-100 border border-zinc-700/60 shadow-xs"
                                        : "text-zinc-400 hover:text-zinc-100 hover:bg-zinc-800",
                                )}
                            >
                                <Brain className="size-3.5 text-zinc-300" />
                                <span>Think</span>
                            </Button>

                            {/* Search Button */}
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                onClick={() => {
                                    const next = !isSearchMode;
                                    setIsSearchMode(next);
                                    toast.info(next ? "Campus & Web search enabled" : "Standard institutional search");
                                }}
                                className={cn(
                                    "h-7 px-2.5 rounded-lg text-xs font-medium gap-1.5 transition-colors",
                                    isSearchMode
                                        ? "bg-zinc-800 text-zinc-100 border border-zinc-700/60 shadow-xs"
                                        : "text-zinc-400 hover:text-zinc-100 hover:bg-zinc-800",
                                )}
                            >
                                <Globe className="size-3.5 text-zinc-300" />
                                <span>Search</span>
                            </Button>

                            {/* Tools bound to the active specialist (fixes "1 tool shows agents" bug) */}
                            <Popover>
                                <PopoverTrigger asChild>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        className="h-7 px-2 rounded-lg text-xs font-medium gap-1 text-zinc-400 hover:text-zinc-100 hover:bg-zinc-800 transition-colors"
                                        title={`Tools available to ${activeAgentMeta.label}`}
                                    >
                                        <span className="size-1.5 rounded-full bg-emerald-400 inline-block mr-0.5" />
                                        <span>{toolsLoading ? "…" : `${agentTools.length} ${agentTools.length === 1 ? "tool" : "tools"}`}</span>
                                    </Button>
                                </PopoverTrigger>
                                <PopoverContent align="start" className="w-80 p-2 shadow-xl max-h-[380px] overflow-y-auto">
                                    <p className="text-muted-foreground px-2 pt-1 text-[11px] font-semibold tracking-wider uppercase">
                                        Tools for {activeAgentMeta.label}
                                    </p>
                                    <p className="px-2 pb-2 text-[11px] text-muted-foreground">
                                        Bound tools & MCP integrations that will be used this turn.
                                    </p>
                                    {toolsLoading ? (
                                        <p className="px-2 py-3 text-xs text-muted-foreground">Loading tools…</p>
                                    ) : agentTools.length === 0 ? (
                                        <p className="px-2 py-3 text-xs text-muted-foreground">No tools bound to this specialist.</p>
                                    ) : (
                                        <div className="grid gap-1">
                                            {(["tool", "mcp", "agent"] as const).map((kind) => {
                                                const group = agentTools.filter((t) => t.kind === kind);
                                                if (group.length === 0) return null;
                                                const heading = kind === "tool" ? "KoAkademy tools" : kind === "mcp" ? "MCP integrations" : "Delegated specialists";
                                                return (
                                                    <div key={kind} className="space-y-1">
                                                        <p className="px-2 pt-1 text-[10px] font-semibold uppercase tracking-wider text-muted-foreground/80">
                                                            {heading} ({group.length})
                                                        </p>
                                                        {group.map((tool) => (
                                                            <div
                                                                key={`${tool.kind}-${tool.name}`}
                                                                className="flex items-start gap-2.5 rounded-lg px-2.5 py-1.5 text-left"
                                                                title={tool.description || tool.name}
                                                            >
                                                                <span
                                                                    className={cn(
                                                                        "mt-1 size-1.5 shrink-0 rounded-full",
                                                                        kind === "tool" && "bg-emerald-400",
                                                                        kind === "mcp" && "bg-sky-400",
                                                                        kind === "agent" && "bg-violet-400",
                                                                    )}
                                                                />
                                                                <span className="min-w-0">
                                                                    <span className="block truncate font-mono text-[11px] font-semibold text-foreground">
                                                                        {tool.name}
                                                                    </span>
                                                                    {tool.description && (
                                                                        <span className="text-muted-foreground mt-0.5 line-clamp-2 block text-[11px] leading-snug">
                                                                            {tool.description}
                                                                        </span>
                                                                    )}
                                                                </span>
                                                            </div>
                                                        ))}
                                                    </div>
                                                );
                                            })}
                                        </div>
                                    )}
                                    <div className="mt-2 border-t border-border/60 pt-2">
                                        <p className="text-muted-foreground px-2 pb-1 text-[11px] font-semibold tracking-wider uppercase">
                                            Switch specialist
                                        </p>
                                        <div className="grid gap-1">
                                            {ADMIN_AGENTS.map((agent) => {
                                                const Icon = agent.icon;
                                                const isActive = agent.key === selectedAgent;
                                                return (
                                                    <button
                                                        key={agent.key}
                                                        type="button"
                                                        onClick={() => {
                                                            setSelectedAgent(agent.key);
                                                            toast.success(`Specialist switched: ${agent.label}`);
                                                        }}
                                                        className={cn(
                                                            "flex items-center gap-2 rounded-lg px-2.5 py-1.5 text-left text-xs transition-colors",
                                                            isActive ? "bg-primary/10 text-foreground font-medium" : "hover:bg-muted text-foreground",
                                                        )}
                                                    >
                                                        <Icon className={cn("size-3.5 shrink-0", isActive ? "text-primary" : "text-muted-foreground")} />
                                                        <span className="truncate">{agent.label}</span>
                                                        {isActive && <Check className="ml-auto size-3 text-primary" />}
                                                    </button>
                                                );
                                            })}
                                        </div>
                                    </div>
                                </PopoverContent>
                            </Popover>
                        </div>

                        {/* Right submit controls */}
                        <div className="flex items-center gap-2">
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                onClick={() => toast.info("Voice input ready. Tap to dictate.")}
                                className="size-7 rounded-lg text-zinc-400 hover:text-zinc-100 hover:bg-zinc-800 transition-colors"
                                title="Dictate voice prompt"
                            >
                                <Mic className="size-4" />
                            </Button>

                            {isLoading ? (
                                <Button
                                    type="button"
                                    size="icon"
                                    variant="destructive"
                                    className="size-8 rounded-full shadow-md"
                                    onClick={stop}
                                    title="Stop generating"
                                >
                                    <Square className="size-3.5 fill-current" />
                                </Button>
                            ) : (
                                <Button
                                    type="button"
                                    size="icon"
                                    disabled={!input.trim() && selectedFiles.length === 0}
                                    onClick={handleSend}
                                    className="size-8 rounded-full bg-white text-zinc-950 hover:bg-zinc-200 disabled:opacity-30 disabled:hover:bg-white transition-all shadow-md"
                                    aria-label="Send message"
                                >
                                    <ArrowUp className="size-4 stroke-[2.5]" />
                                </Button>
                            )}
                        </div>
                    </div>
                </div>

                {/* Bottom Disclaimer */}
                <p className="text-center text-[11px] text-muted-foreground/75 pt-2 pb-0.5 font-normal">
                    ReUI Chat can make mistakes. Check important info.
                </p>
            </div>
        );
    }
}
