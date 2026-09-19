import {
    ChainOfThought,
    ChainOfThoughtContent,
    ChainOfThoughtItem,
    ChainOfThoughtStep,
    ChainOfThoughtTrigger,
    FileUpload,
    FileUploadContent,
    FileUploadTrigger,
    Loader,
    Message,
    MessageAvatar,
    MessageContent,
    PromptInput,
    PromptInputAction,
    PromptInputActions,
    PromptInputTextarea,
} from "@/components/prompt-kit";
import { ChatEmptyState, ErrorState, ModelOption, ModelSelector } from "@/components/spectrumui";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from "@/components/ui/popover";
import { cn } from "@/lib/utils";
import { User } from "@/types/user";
import {
    Bot,
    Calculator,
    ChevronDown,
    Cpu,
    FileSpreadsheet,
    FileText,
    FileType,
    HelpCircle,
    Image as ImageIcon,
    Maximize2,
    Minimize2,
    Paperclip,
    RotateCcw,
    Send,
    ShieldCheck,
    Sparkles,
    UploadCloud,
    User as UserIcon,
    X,
} from "lucide-react";
import * as React from "react";
import { toast } from "sonner";

import { ApprovalCard } from "./approval-card";
import { ChatMessageFormatter } from "./chat-message-formatter";
import { AgentRoleKey, useAiChat } from "./use-ai-chat";

interface AdminAiFloatingWidgetProps {
    user: User;
}

const ADMIN_AGENTS: {
    key: AgentRoleKey;
    label: string;
    description: string;
    icon: React.ElementType;
    badge: string;
}[] = [
    {
        key: "admin_executive",
        label: "Executive & Analytics",
        description: "Campus analytics, data charts, and official document formulation.",
        icon: Sparkles,
        badge: "Executive",
    },
    {
        key: "registrar_auditor",
        label: "Registrar Auditor",
        description: "Admissions spreadsheet audits, graduation clearance, and policy simulation.",
        icon: ShieldCheck,
        badge: "Records",
    },
    {
        key: "bursar_finance",
        label: "Bursar & Finance",
        description: "Statement of Account breakdown, tuition adjustments, and scholarship calculations.",
        icon: Calculator,
        badge: "Ledger",
    },
    {
        key: "campus_support",
        label: "Campus Support",
        description: "Institutional policies, student handbooks, and ticket escalation.",
        icon: HelpCircle,
        badge: "24/7",
    },
];

export function AdminAiFloatingWidget({ user }: AdminAiFloatingWidgetProps) {
    const [isOpen, setIsOpen] = React.useState(false);
    const [isExpanded, setIsExpanded] = React.useState(false);
    const [selectedAgent, setSelectedAgent] = React.useState<AgentRoleKey>("admin_executive");

    // Dynamic model options from configured providers
    const [availableModels, setAvailableModels] = React.useState<ModelOption[]>([]);
    const [selectedModel, setSelectedModel] = React.useState<string>("");
    const [modelPopoverOpen, setModelPopoverOpen] = React.useState(false);

    // File staging state
    const [selectedFiles, setSelectedFiles] = React.useState<File[]>([]);

    // KPI quick summary for administrator empty state
    const [kpis, setKpis] = React.useState<{ label: string; value: string | number; change: string }[]>([]);
    const [quickPrompts, setQuickPrompts] = React.useState<string[]>([
        "Show me the list of students enrolled in CS101",
        "Generate a visual bar chart of student enrollment by department",
        "Audit student graduation clearance and identify pending holds",
        "Draft an official institutional memo regarding enrollment guidelines in PDF format",
    ]);

    const scrollAreaRef = React.useRef<HTMLDivElement>(null);

    const {
        messages,
        input,
        setInput,
        isLoading,
        lastError,
        lastPrompt,
        clearError,
        sendPrompt,
        submitDecision,
        clearChat,
        stop,
    } = useAiChat({
        agent: selectedAgent,
        endpoint: "/administrators/ai/chat",
    });

    // Auto-scroll on new message chunks
    React.useEffect(() => {
        if (scrollAreaRef.current) {
            scrollAreaRef.current.scrollTop = scrollAreaRef.current.scrollHeight;
        }
    }, [messages, isLoading, lastError]);

    // Fetch quick KPIs and available models once when opened
    React.useEffect(() => {
        if (isOpen && kpis.length === 0) {
            fetch("/administrators/ai/analytics-summary", {
                headers: { "X-Requested-With": "XMLHttpRequest" },
            })
                .then((res) => res.json())
                .then((data) => {
                    if (data.kpis) setKpis(data.kpis);
                    if (data.quick_prompts) setQuickPrompts(data.quick_prompts);
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
                        if (!selectedModel) {
                            setSelectedModel(mapped[0].id);
                        }
                    }
                })
                .catch(() => {
                    // Silently keep defaults on network error
                });
        }
    }, [isOpen, kpis.length, selectedModel]);

    const activeMeta = ADMIN_AGENTS.find((a) => a.key === selectedAgent) || ADMIN_AGENTS[0];
    const ActiveIcon = activeMeta.icon;

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

    const getFileIcon = (file: File) => {
        const ext = file.name.split(".").pop()?.toLowerCase();
        if (["xlsx", "xls", "csv"].includes(ext || "")) {
            return <FileSpreadsheet className="size-3 text-emerald-500" />;
        }
        if (["png", "jpg", "jpeg", "webp", "gif"].includes(ext || "") || file.type.startsWith("image/")) {
            return <ImageIcon className="size-3 text-indigo-500" />;
        }
        if (["pdf"].includes(ext || "")) {
            return <FileText className="size-3 text-rose-500" />;
        }
        return <FileType className="size-3 text-sky-500" />;
    };

    const activeModelName = React.useMemo(() => {
        const found = availableModels.find((m) => m.id === selectedModel);
        return found?.name || selectedModel || "Default Model";
    }, [availableModels, selectedModel]);

    return (
        <div className="fixed bottom-6 right-6 z-50 select-none">
            {/* 1. Closed State: Floating Action Button (FAB) */}
            {!isOpen && (
                <button
                    type="button"
                    onClick={() => setIsOpen(true)}
                    className="relative size-14 rounded-full bg-gradient-to-tr from-indigo-600 via-indigo-500 to-violet-500 text-white shadow-2xl flex items-center justify-center hover:scale-105 active:scale-95 transition-all duration-200 ring-4 ring-indigo-500/20 hover:ring-indigo-500/40 cursor-pointer group"
                    title="Open Administrative AI Copilot"
                >
                    <Sparkles className="size-6 text-white group-hover:rotate-12 transition-transform duration-300" />

                    {/* Online status indicator */}
                    <span className="absolute top-1 right-1 flex size-3">
                        <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75" />
                        <span className="relative inline-flex rounded-full size-3 bg-emerald-500 border-2 border-white dark:border-gray-900" />
                    </span>
                </button>
            )}

            {/* 2. Open State: Floating Chat Modal */}
            {isOpen && (
                <div
                    className={cn(
                        "rounded-2xl border border-border/80 bg-background/95 backdrop-blur-xl shadow-2xl flex flex-col overflow-hidden transition-all duration-200 animate-in fade-in slide-in-from-bottom-5",
                        isExpanded
                            ? "w-[95vw] sm:w-[700px] h-[86vh]"
                            : "w-[92vw] sm:w-[480px] md:w-[520px] h-[660px] max-h-[86vh]"
                    )}
                >
                    {/* Header */}
                    <div className="p-3.5 border-b bg-muted/25 flex flex-col gap-2.5">
                        <div className="flex items-center justify-between">
                            <div className="flex items-center gap-2">
                                <div className="p-1.5 rounded-lg bg-primary/10 border border-primary/20 text-primary">
                                    <Sparkles className="size-4" />
                                </div>
                                <div>
                                    <h3 className="text-sm font-semibold flex items-center gap-1.5 text-foreground leading-none">
                                        Administrative AI Copilot
                                        <Badge variant="outline" className="text-[10px] font-mono py-0">
                                            Admin
                                        </Badge>
                                    </h3>
                                    <p className="text-[11px] text-muted-foreground mt-0.5 line-clamp-1">
                                        Analytics, interactive charts, Excel/doc inspection & reports
                                    </p>
                                </div>
                            </div>

                            <div className="flex items-center gap-1">
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    className="size-7 text-muted-foreground hover:text-foreground"
                                    onClick={clearChat}
                                    title="Clear conversation"
                                >
                                    <RotateCcw className="size-3.5" />
                                </Button>

                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    className="size-7 text-muted-foreground hover:text-foreground hidden sm:inline-flex"
                                    onClick={() => setIsExpanded(!isExpanded)}
                                    title={isExpanded ? "Collapse" : "Expand"}
                                >
                                    {isExpanded ? <Minimize2 className="size-3.5" /> : <Maximize2 className="size-3.5" />}
                                </Button>

                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    className="size-7 text-muted-foreground hover:text-foreground"
                                    onClick={() => setIsOpen(false)}
                                    title="Close Assistant"
                                >
                                    <X className="size-4" />
                                </Button>
                            </div>
                        </div>

                        {/* Specialist Agent Selector Pills */}
                        <div className="flex items-center gap-1.5 overflow-x-auto pb-0.5 no-scrollbar">
                            {ADMIN_AGENTS.map((agent) => {
                                const isSelected = selectedAgent === agent.key;
                                const Icon = agent.icon;
                                return (
                                    <button
                                        key={agent.key}
                                        type="button"
                                        onClick={() => setSelectedAgent(agent.key)}
                                        className={cn(
                                            "flex items-center gap-1.5 px-2.5 py-1 rounded-md text-[11px] font-medium border transition-colors shrink-0",
                                            isSelected
                                                ? "bg-primary text-primary-foreground border-primary"
                                                : "bg-background hover:bg-muted text-muted-foreground border-border"
                                        )}
                                    >
                                        <Icon className="size-3" />
                                        {agent.label}
                                    </button>
                                );
                            })}
                        </div>
                    </div>

                    {/* Chat Messages Body */}
                    <div ref={scrollAreaRef} className="flex-1 overflow-y-auto p-4 space-y-4">
                        {messages.length === 0 ? (
                            <div className="h-full flex flex-col justify-between py-2 space-y-4">
                                {/* Spectrum UI ChatEmptyState Component */}
                                <div className="flex justify-center pt-2">
                                    <ChatEmptyState
                                        title={activeMeta.label}
                                        subtitle={`${activeMeta.description} Upload spreadsheets to analyze, plot interactive charts, or download formal reports.`}
                                        prompts={quickPrompts.map((p, idx) => ({
                                            id: `p_${idx}`,
                                            label: p,
                                            prompt: p,
                                        }))}
                                        onSelectPrompt={(p) => sendPrompt(p.prompt || p.label)}
                                        variant="Centered"
                                    />
                                </div>

                                {/* Live KPI Summary Cards */}
                                {kpis.length > 0 && (
                                    <div className="grid grid-cols-2 gap-2 p-2 rounded-xl bg-muted/20 border">
                                        {kpis.map((kpi, i) => (
                                            <div key={i} className="p-2 rounded-lg bg-background border border-border/60">
                                                <span className="text-[10px] text-muted-foreground">{kpi.label}</span>
                                                <div className="text-sm font-bold font-mono text-foreground flex items-center justify-between">
                                                    <span>{kpi.value.toLocaleString()}</span>
                                                    <span className="text-[10px] text-emerald-500 font-normal">{kpi.change}</span>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </div>
                        ) : (
                            messages.map((msg) => {
                                const isUser = msg.role === "user";
                                return (
                                    <Message
                                        key={msg.id}
                                        className={cn("gap-2.5 text-xs", isUser ? "justify-end flex-row-reverse" : "justify-start")}
                                    >
                                        {!isUser ? (
                                            <div className="size-7 rounded-full border bg-primary/10 border-primary/20 text-primary flex items-center justify-center shrink-0 mt-0.5">
                                                <Bot className="size-4" />
                                            </div>
                                        ) : (
                                            <div className="size-7 rounded-full bg-primary/15 border border-primary/25 text-primary flex items-center justify-center shrink-0 mt-0.5">
                                                <UserIcon className="size-4" />
                                            </div>
                                        )}

                                        <div className="flex flex-col gap-1 max-w-[88%]">
                                            <MessageContent
                                                className={cn(
                                                    "rounded-2xl px-3.5 py-2.5 space-y-2 prose-none",
                                                    isUser
                                                        ? "bg-primary text-primary-foreground rounded-tr-sm"
                                                        : "bg-card border border-border/70 rounded-tl-sm text-foreground"
                                                )}
                                            >
                                                {/* Render user attachments */}
                                                {msg.attachments && msg.attachments.length > 0 && (
                                                    <div className="flex flex-wrap gap-1.5 pb-1">
                                                        {msg.attachments.map((att, i) => (
                                                            <div
                                                                key={i}
                                                                className="flex items-center gap-1 px-2 py-0.5 rounded-md bg-primary-foreground/15 text-[11px] font-mono"
                                                            >
                                                                <Paperclip className="size-3" />
                                                                <span className="truncate max-w-[150px]">{att.name}</span>
                                                                <span className="opacity-70">({Math.round(att.size / 1024)} KB)</span>
                                                            </div>
                                                        ))}
                                                    </div>
                                                )}

                                                {isUser ? (
                                                    <p className="whitespace-pre-wrap text-sm leading-relaxed">{msg.content}</p>
                                                ) : (
                                                    <ChatMessageFormatter
                                                        content={msg.content}
                                                        reasoning={msg.reasoning}
                                                        toolCalls={msg.toolCalls}
                                                        sources={msg.sources}
                                                    />
                                                )}

                                                {/* Approvals */}
                                                {msg.pendingApprovals?.map((approval) => (
                                                    <ApprovalCard
                                                        key={approval.id}
                                                        approval={approval}
                                                        onDecision={submitDecision}
                                                        disabled={isLoading}
                                                    />
                                                ))}
                                            </MessageContent>
                                        </div>
                                    </Message>
                                );
                            })
                        )}

                        {/* Spectrum UI ErrorState with 1-Click Retry */}
                        {lastError && (
                            <div className="pt-2 flex justify-start">
                                <ErrorState
                                    title={lastError.title}
                                    message={lastError.message}
                                    retryLabel="Retry Request"
                                    onRetry={() => {
                                        clearError();
                                        if (lastPrompt) {
                                            sendPrompt(lastPrompt, selectedFiles, { model: selectedModel });
                                        }
                                    }}
                                    variant="Card"
                                />
                            </div>
                        )}

                        {/* Prompt-Kit ChainOfThought and Loader (non-sliding, stable progress) */}
                        {isLoading && (
                            <div className="pt-1 px-1">
                                <ChainOfThought className="p-2.5 border border-border/70 bg-muted/20 rounded-xl space-y-1">
                                    <ChainOfThoughtStep defaultOpen={true}>
                                        <ChainOfThoughtTrigger
                                            leftIcon={<Loader variant="dots" size="sm" className="text-primary" />}
                                            className="text-xs font-medium text-foreground hover:text-primary"
                                        >
                                            <span>{activeMeta.label} is analyzing data & formulating response...</span>
                                        </ChainOfThoughtTrigger>
                                        <ChainOfThoughtContent className="text-xs text-muted-foreground pt-1">
                                            <ChainOfThoughtItem>
                                                Evaluating institutional models, analyzing query context, and executing tools.
                                            </ChainOfThoughtItem>
                                        </ChainOfThoughtContent>
                                    </ChainOfThoughtStep>
                                </ChainOfThought>
                            </div>
                        )}
                    </div>

                    {/* Staged File Upload Chips */}
                    {selectedFiles.length > 0 && (
                        <div className="px-3 py-1.5 border-t bg-muted/20 flex flex-wrap gap-1.5">
                            {selectedFiles.map((file, i) => (
                                <div
                                    key={i}
                                    className="flex items-center gap-1.5 px-2 py-1 rounded-md bg-background border text-[11px] font-mono shadow-xs"
                                >
                                    {getFileIcon(file)}
                                    <span className="max-w-[140px] truncate">{file.name}</span>
                                    <button
                                        type="button"
                                        onClick={() => removeFile(i)}
                                        className="text-muted-foreground hover:text-destructive"
                                    >
                                        <X className="size-3" />
                                    </button>
                                </div>
                            ))}
                        </div>
                    )}

                    {/* Prompt-Kit Input with Drop-In File Upload */}
                    <FileUpload
                        onFilesAdded={handleFilesAdded}
                        multiple={true}
                        accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.csv,.txt,.md,.json"
                        disabled={isLoading}
                    >
                        <FileUploadContent>
                            <div className="flex flex-col items-center gap-2 p-6 rounded-2xl border-2 border-dashed border-primary bg-background/95 shadow-2xl text-center">
                                <UploadCloud className="size-10 text-primary animate-bounce" />
                                <p className="text-sm font-semibold">Drop files here to attach</p>
                                <p className="text-xs text-muted-foreground">Excel, CSV, PDF, Word, Images supported</p>
                            </div>
                        </FileUploadContent>

                        <div className="p-3 border-t bg-background/95 backdrop-blur space-y-2">
                            <PromptInput
                                value={input}
                                onValueChange={setInput}
                                onSubmit={handleSend}
                                isLoading={isLoading}
                                disabled={isLoading}
                                className="rounded-2xl border border-border/80 bg-card dark:bg-[#121215] shadow-xs focus-within:ring-2 focus-within:ring-primary/20 focus-within:border-primary transition-all p-0 overflow-hidden"
                            >
                                {/* Top Toolbar: Searchable Model Selector */}
                                <div className="flex items-center justify-between px-3 pt-2.5 pb-1.5 text-xs border-b border-border/40 bg-muted/20">
                                    <div className="flex items-center gap-1.5">
                                        {availableModels.length > 0 ? (
                                            <Popover open={modelPopoverOpen} onOpenChange={setModelPopoverOpen}>
                                                <PopoverTrigger asChild>
                                                    <button
                                                        type="button"
                                                        className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg border border-border/70 bg-background hover:bg-muted text-foreground text-[11.5px] font-medium transition-colors shadow-2xs"
                                                        title="Select AI Model"
                                                    >
                                                        <Cpu className="size-3.5 text-indigo-500" />
                                                        <span className="truncate max-w-[210px] font-mono">{activeModelName}</span>
                                                        <ChevronDown className="size-3 text-muted-foreground" />
                                                    </button>
                                                </PopoverTrigger>
                                                <PopoverContent className="w-[360px] p-3 shadow-2xl rounded-2xl border border-border/80 bg-background" align="start">
                                                    <div className="space-y-2">
                                                        <div className="flex items-center justify-between pb-1.5 border-b">
                                                            <span className="text-xs font-semibold text-foreground">Configured AI Models</span>
                                                            <span className="text-[10.5px] text-muted-foreground font-mono">
                                                                {availableModels.length} available
                                                            </span>
                                                        </div>

                                                        <ModelSelector
                                                            models={availableModels}
                                                            value={selectedModel}
                                                            onChange={(id) => {
                                                                setSelectedModel(id);
                                                                setModelPopoverOpen(false);
                                                                toast.success(`Active model: ${id}`);
                                                            }}
                                                            variant="List"
                                                            searchable={true}
                                                        />
                                                    </div>
                                                </PopoverContent>
                                            </Popover>
                                        ) : (
                                            <a
                                                href="/administrators/system-management/ai"
                                                className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg border border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-400 text-[11px] font-medium hover:bg-amber-500/15 transition-colors"
                                            >
                                                <Cpu className="size-3 text-amber-500" />
                                                <span>Configure API Key &rarr;</span>
                                            </a>
                                        )}
                                    </div>

                                    <span className="text-[11px] text-muted-foreground font-mono opacity-80">
                                        {activeMeta.badge}
                                    </span>
                                </div>

                                {/* PromptInput Textarea */}
                                <PromptInputTextarea
                                    placeholder={`Ask ${activeMeta.label} or drop files here...`}
                                    className="w-full px-3.5 py-2.5 text-sm leading-relaxed text-foreground dark:text-neutral-100 bg-transparent border-0 resize-none outline-none placeholder:text-muted-foreground/60 dark:placeholder:text-neutral-500 min-h-[64px] max-h-[160px] font-sans"
                                />

                                {/* PromptInput Actions Toolbar */}
                                <PromptInputActions className="flex items-center justify-between px-3 pb-2.5 pt-1">
                                    <div className="flex items-center gap-1">
                                        <FileUploadTrigger asChild>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                disabled={isLoading}
                                                className="h-8 px-2 text-xs gap-1.5 text-muted-foreground hover:text-foreground rounded-lg"
                                                title="Attach Excel (.xlsx, .csv), PDF, Word, or Images"
                                            >
                                                <Paperclip className="size-3.5" />
                                                <span className="hidden sm:inline text-[11.5px]">Attach</span>
                                            </Button>
                                        </FileUploadTrigger>
                                    </div>

                                    <div className="flex items-center gap-2">
                                        <span className="text-[10.5px] text-muted-foreground/70 hidden sm:inline">
                                            Enter ↵ to send
                                        </span>

                                        <PromptInputAction tooltip={isLoading ? "Generating..." : "Send prompt"}>
                                            <Button
                                                type="button"
                                                size="sm"
                                                onClick={handleSend}
                                                disabled={(!input.trim() && selectedFiles.length === 0) || isLoading}
                                                className="h-8 px-3.5 text-xs font-semibold gap-1.5 bg-primary text-primary-foreground hover:bg-primary/90 rounded-xl shadow-xs transition-transform active:scale-95"
                                            >
                                                {isLoading ? <Loader variant="circular" size="sm" /> : <Send className="size-3.5" />}
                                                <span>Send</span>
                                            </Button>
                                        </PromptInputAction>
                                    </div>
                                </PromptInputActions>
                            </PromptInput>

                            <div className="flex items-center justify-between text-[10px] text-muted-foreground px-1">
                                <span>Drop files anywhere to upload &bull; Markdown & charts supported</span>
                                <span>Shift + Enter for new line</span>
                            </div>
                        </div>
                    </FileUpload>
                </div>
            )}
        </div>
    );
}
