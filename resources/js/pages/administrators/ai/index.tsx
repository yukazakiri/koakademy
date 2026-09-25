import AdminLayout from "@/components/administrators/admin-layout";
import { ADMIN_AGENTS, DEFAULT_PROMPT_SUGGESTIONS, type ConversationItem, type PromptSuggestion } from "@/components/ai/ai-constants";
import { AiConversationSidebar } from "@/components/ai/ai-conversation-sidebar";
import { ApprovalCard } from "@/components/ai/approval-card";
import { ChatMessageFormatter } from "@/components/ai/chat-message-formatter";
import { CurriculumImportReview } from "@/components/ai/curriculum-import-review";
import { AgentRoleKey, ChatMessage, useAiChat } from "@/components/ai/use-ai-chat";
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
import { ModelOption, ModelSelector } from "@/components/spectrumui";
import { Button } from "@/components/ui/button";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { Shdr14 } from "@/components/ui/shdr-14";
import { Sheet, SheetContent, SheetTitle } from "@/components/ui/sheet";
import { cn } from "@/lib/utils";
import type { User } from "@/types/user";
import { Head, usePage } from "@inertiajs/react";
import {
    AlertCircle,
    ArrowUp,
    ChevronDown,
    FileCode,
    FileImage,
    FileSpreadsheet,
    FileText,
    Globe2,
    Menu,
    Mic,
    Paperclip,
    RefreshCw,
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
const ACCEPTED_ATTACHMENT_TYPES = [
    "application/pdf",
    "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
    "application/vnd.ms-excel",
    "text/csv",
    "text/plain",
    "image/jpeg",
    "image/png",
    "image/webp",
].join(",");
const ACCEPTED_ATTACHMENT_TYPE_SET = new Set(ACCEPTED_ATTACHMENT_TYPES.split(","));

function getGreeting(name: string): string {
    const hour = new Date().getHours();
    let timeGreeting = "Good day";
    if (hour < 12) timeGreeting = "Good morning";
    else if (hour < 18) timeGreeting = "Good afternoon";
    else timeGreeting = "Good evening";

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
    const [curriculumFile, setCurriculumFile] = React.useState<File | null>(null);

    const messagesEndRef = React.useRef<HTMLDivElement | null>(null);
    const chatContainerRef = React.useRef<HTMLDivElement | null>(null);

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
            .then((data: { models?: ModelOption[] }) => {
                if (Array.isArray(data.models) && data.models.length > 0) {
                    const mapped = data.models.map((model) => ({
                        id: model.id,
                        name: model.name || model.id,
                        badge: model.badge,
                        description: model.description,
                        provider: model.provider,
                        provider_name: model.provider_name,
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
                                    !m.id.includes("claude-opus-4-6-thinking-high"),
                            ) || mapped[0];
                        setSelectedModel(recommended.id);
                    }
                }
            })
            .catch(() => undefined);
    }, [selectedModel]);

    const handleSelectModel = (id: string) => {
        setSelectedModel(id);
        if (typeof window !== "undefined") {
            try {
                localStorage.setItem(PREFERRED_MODEL_KEY, id);
            } catch {
                // A model choice is an optional local preference.
            }
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

        setConversations((prev) => prev.map((c) => (c.id === id ? { ...c, title: newTitle, updated_at: new Date().toISOString() } : c)));
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
        return <FileCode className="text-muted-foreground size-3.5" />;
    };

    const activeAgentMeta = ADMIN_AGENTS.find((a) => a.key === selectedAgent) || ADMIN_AGENTS[0];
    const ActiveAgentIcon = activeAgentMeta.icon;

    const firstName = user.name?.split(" ")[0] || "Administrator";
    const activeModelName = availableModels.find((model) => model.id === selectedModel)?.name || "Auto select";

    const renderComposer = (className?: string) => (
        <div className={cn("w-full", className)}>
            {selectedFiles.length > 0 && (
                <div className="mb-2 flex flex-wrap gap-2 px-1">
                    {selectedFiles.map((file, index) => (
                        <div
                            key={`${file.name}-${index}`}
                            className="border-border bg-muted/50 flex items-center gap-1.5 rounded-lg border px-2.5 py-1 text-xs shadow-xs"
                        >
                            {getFileIcon(file)}
                            <span className="text-foreground max-w-40 truncate font-medium">{file.name}</span>
                            <button
                                type="button"
                                onClick={() => removeFile(index)}
                                className="text-muted-foreground hover:text-foreground transition-colors"
                                aria-label={`Remove ${file.name}`}
                            >
                                <X className="size-3" />
                            </button>
                        </div>
                    ))}
                </div>
            )}
            {selectedFiles.some((file) => /\.xlsx$/i.test(file.name)) && !isLoading && (
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    className="mb-2"
                    onClick={() => {
                        const workbook = selectedFiles.find((file) => /\.xlsx$/i.test(file.name));
                        if (workbook) {
                            setCurriculumFile(workbook);
                            setSelectedFiles((current) => current.filter((file) => file !== workbook));
                        }
                    }}
                >
                    <FileSpreadsheet className="size-4" /> Review as curriculum import
                </Button>
            )}

            <div className="bg-muted/70 dark:bg-muted/35 rounded-[1.5rem] p-1.5">
                <div className="text-muted-foreground px-3 pt-1.5 text-[11px]">
                    KoAkademy Copilot can analyze institution data and draft operational documents.
                </div>
                <PromptInput
                    value={input}
                    onValueChange={setInput}
                    isLoading={isLoading}
                    onSubmit={handleSend}
                    className="border-border/70 bg-background focus-within:border-primary/40 focus-within:ring-primary/10 mt-1.5 rounded-[1.2rem] p-0 shadow-sm transition-shadow focus-within:ring-2"
                >
                    <PromptInputTextarea
                        placeholder="Ask me anything..."
                        className="text-foreground placeholder:text-muted-foreground/80 max-h-40 min-h-[66px] px-4 pt-3 text-sm"
                    />

                    <PromptInputActions className="flex items-center justify-between gap-2 px-2.5 pt-1 pb-2.5">
                        <div className="flex min-w-0 flex-1 items-center gap-1.5 overflow-hidden">
                            <FileUpload onFilesAdded={handleFilesAdded} accept={ACCEPTED_ATTACHMENT_TYPES}>
                                <FileUploadTrigger asChild>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        className="text-muted-foreground hover:text-foreground size-8 rounded-full"
                                        title="Attach documents or spreadsheets"
                                        aria-label="Attach documents or spreadsheets"
                                    >
                                        <Paperclip className="size-4" />
                                    </Button>
                                </FileUploadTrigger>
                                <FileUploadContent />
                            </FileUpload>

                            <Popover>
                                <PopoverTrigger asChild>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        className="h-8 max-w-32 min-w-0 shrink gap-1.5 rounded-lg px-2.5 text-xs font-medium sm:max-w-52"
                                        aria-label={`Choose an AI model. ${activeModelName} is active.`}
                                    >
                                        <Globe2 className="size-3.5 shrink-0" />
                                        <span className="truncate">{activeModelName}</span>
                                        <ChevronDown className="text-muted-foreground size-3 shrink-0" />
                                    </Button>
                                </PopoverTrigger>
                                <PopoverContent align="start" className="w-[min(26rem,calc(100vw-2rem))] p-3">
                                    {availableModels.length > 0 ? (
                                        <ModelSelector models={availableModels} value={selectedModel} onChange={handleSelectModel} />
                                    ) : (
                                        <p className="text-muted-foreground p-2 text-sm">No AI models are configured yet.</p>
                                    )}
                                </PopoverContent>
                            </Popover>

                            <Popover>
                                <PopoverTrigger asChild>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        className="h-8 shrink-0 gap-1.5 rounded-lg px-2.5 text-xs font-medium"
                                        aria-label={`Choose a specialist. ${activeAgentMeta.label} is active.`}
                                    >
                                        <ActiveAgentIcon className="size-3.5 shrink-0" />
                                        <span className="hidden sm:inline">{activeAgentMeta.badge}</span>
                                        <ChevronDown className="text-muted-foreground size-3 shrink-0" />
                                    </Button>
                                </PopoverTrigger>
                                <PopoverContent align="start" className="w-72 p-2">
                                    <p className="text-muted-foreground px-2 pt-1 pb-2 text-[11px] font-medium tracking-wide uppercase">Specialist</p>
                                    <div className="grid gap-1">
                                        {ADMIN_AGENTS.map((agent) => {
                                            const Icon = agent.icon;
                                            const isActive = agent.key === selectedAgent;

                                            return (
                                                <button
                                                    key={agent.key}
                                                    type="button"
                                                    onClick={() => setSelectedAgent(agent.key)}
                                                    className={cn(
                                                        "flex items-start gap-2.5 rounded-lg px-2.5 py-2 text-left transition-colors",
                                                        isActive ? "bg-primary/10 text-foreground" : "hover:bg-muted text-foreground",
                                                    )}
                                                >
                                                    <Icon
                                                        className={cn("mt-0.5 size-4 shrink-0", isActive ? "text-primary" : "text-muted-foreground")}
                                                    />
                                                    <span className="min-w-0">
                                                        <span className="block text-xs font-semibold">{agent.label}</span>
                                                        <span className="text-muted-foreground mt-0.5 block text-[11px] leading-snug">
                                                            {agent.description}
                                                        </span>
                                                    </span>
                                                </button>
                                            );
                                        })}
                                    </div>
                                </PopoverContent>
                            </Popover>
                        </div>

                        <div className="flex shrink-0 items-center gap-1.5">
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                disabled
                                className="text-muted-foreground size-8 rounded-full"
                                title="Voice input is not available yet"
                                aria-label="Voice input is not available yet"
                            >
                                <Mic className="size-4" />
                            </Button>
                            {isLoading ? (
                                <Button
                                    type="button"
                                    size="icon"
                                    variant="destructive"
                                    className="size-8 rounded-full shadow-xs"
                                    onClick={stop}
                                    title="Stop generating"
                                    aria-label="Stop generating"
                                >
                                    <Square className="size-3.5 fill-current" />
                                </Button>
                            ) : (
                                <PromptInputAction tooltip="Send message">
                                    <Button
                                        type="button"
                                        size="icon"
                                        className="bg-foreground text-background hover:bg-foreground/85 size-8 rounded-full shadow-xs"
                                        onClick={handleSend}
                                        disabled={!input.trim() && selectedFiles.length === 0}
                                        aria-label="Send message"
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
    );

    return (
        <AdminLayout user={user} immersive>
            <Head title="AI Chat - Administrator Copilot" />
            <CurriculumImportReview
                file={curriculumFile}
                onClose={() => setCurriculumFile(null)}
                onStaged={(id, title) => {
                    sendPrompt(
                        `I staged a curriculum workbook for ${title} (import ID: ${id}). Use InspectCurriculumImportTool to describe the proposed program, subjects and problems. Do not modify records; I will review and confirm them in the import panel.`,
                        [],
                        { agent: "admin_executive", model: selectedModel || undefined },
                    );
                }}
                onApplied={(courseId) => {
                    sendPrompt(
                        `The approved curriculum import has been applied to program ID ${courseId}. Use GetCourseCurriculumTool to summarize its subjects.`,
                        [],
                        { agent: "admin_executive", model: selectedModel || undefined },
                    );
                }}
            />

            <div className="border-border/70 bg-background flex h-full min-h-0 w-full overflow-hidden border">
                <div className="hidden h-full w-[18.5rem] shrink-0 md:block">
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
                        className="w-full"
                    />
                </div>

                <Sheet open={mobileDrawerOpen} onOpenChange={setMobileDrawerOpen}>
                    <SheetContent side="left" className="w-72 p-0">
                        <SheetTitle className="sr-only">Conversation history</SheetTitle>
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

                <main className="bg-background relative flex min-w-0 flex-1 flex-col overflow-hidden">
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="absolute top-3 left-3 z-10 size-9 rounded-lg md:hidden"
                        onClick={() => setMobileDrawerOpen(true)}
                        title="Open chats"
                        aria-label="Open chats"
                    >
                        <Menu className="size-4" />
                    </Button>

                    {messages.length === 0 ? (
                        <div className="flex min-h-0 flex-1 overflow-y-auto px-5 py-16 sm:px-8">
                            <div className="mx-auto flex w-full max-w-[54rem] flex-col items-center pt-20 pb-24 text-center">
                                <div className="mb-12 flex items-center justify-center">
                                    <Shdr14
                                        size={180}
                                        state={isLoading ? "thinking" : "idle"}
                                        ariaLabel="KoAkademy AI orb"
                                        className="transition-transform duration-300 hover:scale-105"
                                    />
                                </div>
                                <h1 className="text-foreground text-3xl font-medium tracking-tight sm:text-4xl">{getGreeting(firstName)}</h1>
                                <p className="text-foreground mt-2 text-3xl font-medium tracking-tight sm:text-4xl">
                                    How can I{" "}
                                    <span className="bg-gradient-to-r from-violet-400 via-purple-400 to-indigo-300 bg-clip-text text-transparent">
                                        assist you today?
                                    </span>
                                </p>
                                <div className="mt-12 w-full">{renderComposer()}</div>
                                <div className="mt-4 flex w-full flex-wrap gap-2">
                                    {DEFAULT_PROMPT_SUGGESTIONS.map((suggestion) => {
                                        const Icon = suggestion.icon;
                                        return (
                                            <button
                                                key={suggestion.title}
                                                type="button"
                                                onClick={() => handleSelectSuggestion(suggestion)}
                                                className="border-border bg-background text-foreground hover:bg-muted inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-xs font-medium transition-colors"
                                            >
                                                <Icon className="size-3.5" />
                                                {suggestion.title}
                                            </button>
                                        );
                                    })}
                                </div>
                            </div>
                        </div>
                    ) : (
                        <>
                            <div ref={chatContainerRef} className="flex-1 overflow-y-auto px-4 py-8 md:px-8">
                                <div className="mx-auto max-w-3xl space-y-6">
                                    {messages.map((message) => {
                                        const isUser = message.role === "user";
                                        return (
                                            <div key={message.id} className={cn("flex gap-3", isUser ? "justify-end" : "justify-start")}>
                                                {!isUser && (
                                                    <div className="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-xl bg-gradient-to-tr from-indigo-600 to-violet-500 text-white shadow-xs">
                                                        <ActiveAgentIcon className="size-4" />
                                                    </div>
                                                )}

                                                <div
                                                    className={cn(
                                                        "max-w-[85%] rounded-2xl px-4 py-3 text-sm",
                                                        isUser
                                                            ? "bg-primary text-primary-foreground rounded-tr-sm shadow-xs"
                                                            : "bg-muted/40 dark:bg-muted/20 border-border/70 w-full rounded-tl-sm border",
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
                                                                            className="flex items-center gap-1 rounded-md bg-white/20 px-2 py-0.5 font-mono text-xs"
                                                                        >
                                                                            <Paperclip className="size-3" />
                                                                            <span className="max-w-[150px] truncate">{att.name}</span>
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
                                        <div className="text-muted-foreground flex animate-pulse items-center gap-3 pl-1 text-xs">
                                            <div className="bg-primary/10 text-primary flex size-7 items-center justify-center rounded-lg">
                                                <Loader className="size-3.5" />
                                            </div>
                                            <span>{activeAgentMeta.label} is analyzing campus data...</span>
                                        </div>
                                    )}

                                    {/* Error Banner */}
                                    {lastError && (
                                        <div className="border-destructive/30 bg-destructive/10 text-destructive flex items-start justify-between gap-3 rounded-xl border p-3.5 text-xs">
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
                                                            model: selectedModel || undefined,
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
                            <div className="border-border/70 bg-background shrink-0 border-t px-4 py-4 md:px-8">
                                {renderComposer("mx-auto max-w-3xl")}
                            </div>
                        </>
                    )}
                </main>
            </div>
        </AdminLayout>
    );
}
