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
    AgentRoleKey,
    ChatMessage,
    useAiChat,
} from "@/components/ai/use-ai-chat";
import AdminLayout from "@/components/administrators/admin-layout";
import {
    FileUpload,
    FileUploadContent,
    FileUploadTrigger,
    Loader,
    PromptInput,
    PromptInputAction,
    PromptInputActions,
    PromptInputTextarea,
} from "@/components/prompt-kit";
import { ErrorState, ModelOption, ModelSelector } from "@/components/spectrumui";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import {
    Sheet,
    SheetContent,
    SheetTrigger,
} from "@/components/ui/sheet";
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from "@/components/ui/tooltip";
import { cn } from "@/lib/utils";
import type { User } from "@/types/user";
import { Head, usePage } from "@inertiajs/react";
import {
    AlertCircle,
    ArrowUp,
    FileCode,
    FileImage,
    FileSpreadsheet,
    FileText,
    Menu,
    PanelLeftClose,
    PanelLeftOpen,
    Paperclip,
    Plus,
    RefreshCw,
    Sparkles,
    Square,
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

const PREFERRED_MODEL_KEY = "koakademy_preferred_ai_model";

function getGreeting(name: string): string {
    const hour = new Date().getHours();
    let timeGreeting = "Good day";
    if (hour < 12) timeGreeting = "Good morning";
    else if (hour < 18) timeGreeting = "Good afternoon";
    else timeGreeting = "Good evening";

    return `${timeGreeting}, ${name}`;
}

export default function AdministratorAiChatPage({
    initialConversation,
    initialConversationId,
}: AiChatPageProps) {
    const { auth } = usePage<{ auth: { user: User } }>().props;
    const user = auth.user;

    const [sidebarOpen, setSidebarOpen] = React.useState(true);
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

    const messagesEndRef = React.useRef<HTMLDivElement | null>(null);
    const chatContainerRef = React.useRef<HTMLDivElement | null>(null);

    const {
        messages,
        input,
        setInput,
        isLoading,
        conversationId,
        lastError,
        lastPrompt,
        clearError,
        sendPrompt,
        submitDecision,
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

    // Fetch analytics summary and available models
    React.useEffect(() => {
        fetch("/administrators/ai/analytics-summary", {
            headers: { "X-Requested-With": "XMLHttpRequest" },
        })
            .then((res) => res.json())
            .then((data) => {
                if (Array.isArray(data.models) && data.models.length > 0) {
                    const mapped: ModelOption[] = data.models.map((m: any) => ({
                        id: m.id,
                        name: m.name || m.id,
                        badge: m.badge,
                        description: m.description,
                        provider: m.provider,
                        provider_name: m.provider_name,
                    }));
                    setAvailableModels(mapped);

                    const saved = typeof window !== "undefined" ? localStorage.getItem(PREFERRED_MODEL_KEY) : null;
                    if (saved && mapped.some((m) => m.id === saved)) {
                        setSelectedModel(saved);
                    } else if (!selectedModel) {
                        const recommended =
                            mapped.find(
                                (m) =>
                                    (m.badge?.includes("Default") ||
                                        m.badge?.includes("Recommended") ||
                                        m.id.includes("best-free") ||
                                        m.id.includes("best-chat")) &&
                                    !m.id.includes("claude-opus-4-6-thinking-high")
                            ) || mapped[0];
                        setSelectedModel(recommended.id);
                    }
                }
            })
            .catch(() => {});
    }, [selectedModel]);

    const handleSelectModel = (id: string) => {
        setSelectedModel(id);
        if (typeof window !== "undefined") {
            try {
                localStorage.setItem(PREFERRED_MODEL_KEY, id);
            } catch {}
        }
        toast.success(`Active model: ${id}`);
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
        } catch (err: any) {
            toast.error(err.message || "Failed to load conversation history.");
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
            prev.map((c) => (c.id === id ? { ...c, title: newTitle, updated_at: new Date().toISOString() } : c))
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
        const oversized = newFiles.filter((f) => f.size > 20 * 1024 * 1024);
        if (oversized.length > 0) {
            toast.error("Files larger than 20MB cannot be uploaded.");
            return;
        }
        setSelectedFiles((prev) => [...prev, ...newFiles]);
        toast.success(`Attached ${newFiles.length} ${newFiles.length === 1 ? "file" : "files"}.`);
    };

    const removeFile = (index: number) => {
        setSelectedFiles((prev) => prev.filter((_, i) => i !== index));
    };

    const handleSend = () => {
        if ((!input.trim() && selectedFiles.length === 0) || isLoading) return;
        sendPrompt(input, selectedFiles, {
            model: selectedModel || undefined,
        });
        setSelectedFiles([]);
    };

    const handleSelectSuggestion = (suggestion: PromptSuggestion) => {
        setSelectedAgent(suggestion.agent);
        sendPrompt(suggestion.prompt, undefined, {
            agent: suggestion.agent,
            model: selectedModel || undefined,
        });
    };

    const getFileIcon = (file: File) => {
        const ext = file.name.split(".").pop()?.toLowerCase();
        if (["xlsx", "xls", "csv"].includes(ext || "")) {
            return <FileSpreadsheet className="size-3.5 text-emerald-500" />;
        }
        if (["pdf"].includes(ext || "")) {
            return <FileText className="size-3.5 text-rose-500" />;
        }
        if (["jpg", "jpeg", "png", "webp"].includes(ext || "")) {
            return <FileImage className="size-3.5 text-indigo-500" />;
        }
        return <FileCode className="size-3.5 text-muted-foreground" />;
    };

    const activeAgentMeta = ADMIN_AGENTS.find((a) => a.key === selectedAgent) || ADMIN_AGENTS[0];
    const ActiveAgentIcon = activeAgentMeta.icon;

    const firstName = user.name?.split(" ")[0] || "Administrator";

    return (
        <AdminLayout user={user} title="AI Chat">
            <Head title="AI Chat - Administrator Copilot" />

            <TooltipProvider>
                <div className="flex h-[calc(100vh-8.5rem)] min-h-[580px] w-full rounded-2xl border border-border/70 bg-background/95 backdrop-blur-md overflow-hidden shadow-sm">
                    {/* Desktop Conversation Sidebar */}
                    <div
                        className={cn(
                            "hidden md:block transition-all duration-300 ease-in-out shrink-0 overflow-hidden",
                            sidebarOpen ? "w-64" : "w-0 border-r-0"
                        )}
                    >
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
                            className="w-64"
                        />
                    </div>

                    {/* Mobile Drawer */}
                    <Sheet open={mobileDrawerOpen} onOpenChange={setMobileDrawerOpen}>
                        <SheetContent side="left" className="p-0 w-72">
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
                                className="w-full h-full border-r-0"
                            />
                        </SheetContent>
                    </Sheet>

                    {/* Main Chat Workspace */}
                    <div className="flex flex-1 flex-col min-w-0 bg-background overflow-hidden relative">
                        {/* Workspace Top Bar */}
                        <header className="px-4 py-2.5 border-b border-border/80 flex items-center justify-between gap-2 bg-muted/20 shrink-0">
                            <div className="flex items-center gap-2 min-w-0">
                                {/* Mobile Drawer Trigger */}
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    className="md:hidden size-8 text-muted-foreground"
                                    onClick={() => setMobileDrawerOpen(true)}
                                >
                                    <Menu className="size-4" />
                                </Button>

                                {/* Desktop Sidebar Toggle */}
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    className="hidden md:inline-flex size-8 text-muted-foreground hover:text-foreground"
                                    onClick={() => setSidebarOpen(!sidebarOpen)}
                                    title={sidebarOpen ? "Collapse chats" : "Expand chats"}
                                >
                                    {sidebarOpen ? <PanelLeftClose className="size-4" /> : <PanelLeftOpen className="size-4" />}
                                </Button>

                                {/* Agent Selector Pills */}
                                <div className="flex items-center gap-1 overflow-x-auto no-scrollbar py-0.5">
                                    {ADMIN_AGENTS.map((agent) => {
                                        const isSelected = selectedAgent === agent.key;
                                        const Icon = agent.icon;
                                        return (
                                            <button
                                                key={agent.key}
                                                type="button"
                                                onClick={() => setSelectedAgent(agent.key)}
                                                className={cn(
                                                    "flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-medium border transition-all shrink-0 cursor-pointer",
                                                    isSelected
                                                        ? "bg-primary text-primary-foreground border-primary shadow-xs font-semibold"
                                                        : "bg-background/80 hover:bg-muted text-muted-foreground border-border/70"
                                                )}
                                            >
                                                <Icon className="size-3.5" />
                                                <span className="hidden sm:inline">{agent.label}</span>
                                                <span className="sm:hidden">{agent.badge}</span>
                                            </button>
                                        );
                                    })}
                                </div>
                            </div>

                            <div className="flex items-center gap-2 shrink-0">
                                {/* Model Selector */}
                                <ModelSelector
                                    models={availableModels}
                                    selectedModel={selectedModel}
                                    onSelectModel={handleSelectModel}
                                />

                                {/* Clear / Reset button */}
                                {messages.length > 0 && (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={handleNewChat}
                                        className="gap-1.5 h-8 text-xs font-medium text-muted-foreground hover:text-foreground hidden sm:inline-flex"
                                    >
                                        <Plus className="size-3.5" />
                                        <span>New chat</span>
                                    </Button>
                                )}
                            </div>
                        </header>

                        {/* Chat Messages Thread / Welcome Screen */}
                        <div
                            ref={chatContainerRef}
                            className="flex-1 overflow-y-auto px-4 py-6 md:px-8 space-y-6"
                        >
                            {messages.length === 0 ? (
                                /* Welcome / Empty State Screen (Matches User's Reference) */
                                <div className="max-w-2xl mx-auto flex flex-col items-center justify-center min-h-[420px] text-center px-4 py-8 animate-in fade-in zoom-in-95 duration-200">
                                    {/* AI Aura Avatar */}
                                    <div className="relative mb-5 flex items-center justify-center">
                                        <div className="absolute -inset-2 rounded-full bg-gradient-to-tr from-indigo-500/20 via-purple-500/20 to-pink-500/20 blur-xl animate-pulse" />
                                        <div className="relative size-16 rounded-2xl bg-gradient-to-tr from-indigo-600 via-indigo-500 to-violet-500 text-white shadow-xl flex items-center justify-center ring-4 ring-indigo-500/10">
                                            <Sparkles className="size-8 text-white drop-shadow-sm" />
                                        </div>
                                    </div>

                                    {/* Greeting Header */}
                                    <h2 className="text-2xl sm:text-3xl font-bold tracking-tight text-foreground">
                                        {getGreeting(firstName)}
                                    </h2>
                                    <p className="mt-2 text-sm sm:text-base text-muted-foreground max-w-md">
                                        How can I assist you with campus intelligence, policy formulation, or audits today?
                                    </p>

                                    {/* Suggestion Prompt Cards */}
                                    <div className="mt-8 grid grid-cols-1 sm:grid-cols-2 gap-3 w-full text-left">
                                        {DEFAULT_PROMPT_SUGGESTIONS.map((suggestion) => {
                                            const Icon = suggestion.icon;
                                            return (
                                                <button
                                                    key={suggestion.title}
                                                    type="button"
                                                    onClick={() => handleSelectSuggestion(suggestion)}
                                                    className="group p-3.5 rounded-xl border border-border/70 bg-card/60 hover:bg-accent/40 hover:border-primary/40 transition-all duration-150 cursor-pointer shadow-xs hover:shadow-sm"
                                                >
                                                    <div className="flex items-start gap-2.5">
                                                        <div className="size-8 rounded-lg bg-primary/10 text-primary flex items-center justify-center shrink-0 group-hover:scale-105 transition-transform">
                                                            <Icon className="size-4" />
                                                        </div>
                                                        <div className="min-w-0">
                                                            <h4 className="text-xs font-semibold text-foreground group-hover:text-primary transition-colors">
                                                                {suggestion.title}
                                                            </h4>
                                                            <p className="text-[11.5px] text-muted-foreground mt-0.5 line-clamp-2">
                                                                {suggestion.description}
                                                            </p>
                                                        </div>
                                                    </div>
                                                </button>
                                            );
                                        })}
                                    </div>
                                </div>
                            ) : (
                                /* Active Messages List */
                                <div className="max-w-3xl mx-auto space-y-6">
                                    {messages.map((message) => {
                                        const isUser = message.role === "user";
                                        return (
                                            <div
                                                key={message.id}
                                                className={cn(
                                                    "flex gap-3",
                                                    isUser ? "justify-end" : "justify-start"
                                                )}
                                            >
                                                {!isUser && (
                                                    <div className="size-8 rounded-xl bg-gradient-to-tr from-indigo-600 to-violet-500 text-white flex items-center justify-center shadow-xs shrink-0 mt-0.5">
                                                        <ActiveAgentIcon className="size-4" />
                                                    </div>
                                                )}

                                                <div
                                                    className={cn(
                                                        "rounded-2xl px-4 py-3 max-w-[85%] text-sm",
                                                        isUser
                                                            ? "bg-primary text-primary-foreground shadow-xs rounded-tr-sm"
                                                            : "bg-muted/40 dark:bg-muted/20 border border-border/70 rounded-tl-sm w-full"
                                                    )}
                                                >
                                                    {isUser ? (
                                                        <div className="space-y-2">
                                                            <p className="whitespace-pre-wrap">{message.content}</p>
                                                            {message.attachments && message.attachments.length > 0 && (
                                                                <div className="flex flex-wrap gap-1.5 pt-1">
                                                                    {message.attachments.map((att, idx) => (
                                                                        <div
                                                                            key={idx}
                                                                            className="flex items-center gap-1 px-2 py-0.5 rounded-md bg-white/20 text-xs font-mono"
                                                                        >
                                                                            <Paperclip className="size-3" />
                                                                            <span className="truncate max-w-[150px]">{att.name}</span>
                                                                        </div>
                                                                    ))}
                                                                </div>
                                                            )}
                                                        </div>
                                                    ) : (
                                                        <div className="space-y-3">
                                                            <ChatMessageFormatter
                                                                content={message.content}
                                                                toolCalls={message.toolCalls}
                                                                sources={message.sources}
                                                            />

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
                                                    )}
                                                </div>
                                            </div>
                                        );
                                    })}

                                    {/* Streaming / Loading Indicator */}
                                    {isLoading && (
                                        <div className="flex gap-3 items-center text-muted-foreground text-xs pl-1 animate-pulse">
                                            <div className="size-7 rounded-lg bg-primary/10 text-primary flex items-center justify-center">
                                                <Loader className="size-3.5" />
                                            </div>
                                            <span>{activeAgentMeta.label} is analyzing campus data...</span>
                                        </div>
                                    )}

                                    {/* Error Banner */}
                                    {lastError && (
                                        <div className="p-3.5 rounded-xl border border-destructive/30 bg-destructive/10 text-destructive flex items-start justify-between gap-3 text-xs">
                                            <div className="flex items-start gap-2">
                                                <AlertCircle className="size-4 shrink-0 mt-0.5" />
                                                <div>
                                                    <span className="font-semibold block">{lastError.title}</span>
                                                    <p className="text-destructive/90 mt-0.5">{lastError.message}</p>
                                                </div>
                                            </div>
                                            {lastError.retryPrompt && (
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="sm"
                                                    className="h-7 text-xs border-destructive/30 hover:bg-destructive/15 shrink-0"
                                                    onClick={() => {
                                                        clearError();
                                                        sendPrompt(lastError.retryPrompt!, selectedFiles, {
                                                            model: selectedModel || undefined,
                                                        });
                                                    }}
                                                >
                                                    <RefreshCw className="size-3 mr-1" />
                                                    Retry
                                                </Button>
                                            )}
                                        </div>
                                    )}

                                    <div ref={messagesEndRef} />
                                </div>
                            )}
                        </div>

                        {/* Anchored Bottom Composer */}
                        <div className="p-4 border-t border-border/80 bg-background/80 backdrop-blur-md shrink-0">
                            <div className="max-w-3xl mx-auto space-y-2">
                                {/* Staged Attachments Preview */}
                                {selectedFiles.length > 0 && (
                                    <div className="flex flex-wrap gap-2 pb-1">
                                        {selectedFiles.map((file, idx) => (
                                            <div
                                                key={idx}
                                                className="flex items-center gap-1.5 px-2.5 py-1 rounded-lg border border-border bg-muted/60 text-xs shadow-xs"
                                            >
                                                {getFileIcon(file)}
                                                <span className="truncate max-w-[160px] font-medium text-foreground">
                                                    {file.name}
                                                </span>
                                                <span className="text-[10px] text-muted-foreground font-mono">
                                                    {(file.size / 1024).toFixed(0)}KB
                                                </span>
                                                <button
                                                    type="button"
                                                    onClick={() => removeFile(idx)}
                                                    className="text-muted-foreground hover:text-foreground ml-1"
                                                >
                                                    <X className="size-3" />
                                                </button>
                                            </div>
                                        ))}
                                    </div>
                                )}

                                <PromptInput
                                    value={input}
                                    onValueChange={setInput}
                                    isLoading={isLoading}
                                    onSubmit={handleSend}
                                    className="border-border/80 shadow-xs focus-within:border-primary/50 focus-within:ring-1 focus-within:ring-primary/20 rounded-xl"
                                >
                                    <PromptInputTextarea
                                        placeholder={`Message ${activeAgentMeta.label}...`}
                                        className="text-sm min-h-[46px] max-h-36 py-2.5"
                                        onKeyDown={(e) => {
                                            if (e.key === "Enter" && !e.shiftKey) {
                                                e.preventDefault();
                                                handleSend();
                                            }
                                        }}
                                    />

                                    <PromptInputActions className="px-2 pb-2 pt-1 flex items-center justify-between">
                                        <div className="flex items-center gap-1">
                                            {/* File upload trigger */}
                                            <FileUpload
                                                onFilesAdded={handleFilesAdded}
                                                maxFiles={5}
                                                acceptedTypes={[
                                                    "application/pdf",
                                                    "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
                                                    "application/vnd.ms-excel",
                                                    "text/csv",
                                                    "text/plain",
                                                    "image/jpeg",
                                                    "image/png",
                                                    "image/webp",
                                                ]}
                                            >
                                                <FileUploadTrigger asChild>
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="icon"
                                                        className="size-8 text-muted-foreground hover:text-foreground rounded-lg"
                                                        title="Attach documents or spreadsheets (max 20MB)"
                                                    >
                                                        <Paperclip className="size-4" />
                                                    </Button>
                                                </FileUploadTrigger>
                                                <FileUploadContent />
                                            </FileUpload>
                                        </div>

                                        <div className="flex items-center gap-1.5">
                                            {isLoading ? (
                                                <Button
                                                    type="button"
                                                    size="icon"
                                                    variant="destructive"
                                                    className="size-8 rounded-lg shadow-xs"
                                                    onClick={stop}
                                                    title="Stop generating"
                                                >
                                                    <Square className="size-3.5 fill-current" />
                                                </Button>
                                            ) : (
                                                <PromptInputAction tooltip="Send message">
                                                    <Button
                                                        type="button"
                                                        size="icon"
                                                        className="size-8 rounded-lg shadow-xs bg-primary text-primary-foreground hover:bg-primary/90"
                                                        onClick={handleSend}
                                                        disabled={!input.trim() && selectedFiles.length === 0}
                                                    >
                                                        <ArrowUp className="size-4" />
                                                    </Button>
                                                </PromptInputAction>
                                            )}
                                        </div>
                                    </PromptInputActions>
                                </PromptInput>
                            </div>
                        </div>
                    </div>
                </div>
            </TooltipProvider>
        </AdminLayout>
    );
}
