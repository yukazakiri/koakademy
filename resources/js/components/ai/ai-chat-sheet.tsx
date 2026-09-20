import {
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
import { ErrorState, ModelOption, ModelSelector } from "@/components/spectrumui";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from "@/components/ui/popover";
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from "@/components/ui/sheet";
import { cn } from "@/lib/utils";
import {
    Bot,
    Calculator,
    ChevronDown,
    Cpu,
    FileSpreadsheet,
    FileText,
    FileType,
    GraduationCap,
    HelpCircle,
    Image as ImageIcon,
    Paperclip,
    RotateCcw,
    Send,
    ShieldCheck,
    Sparkles,
    UploadCloud,
    User,
    X,
} from "lucide-react";
import * as React from "react";
import { toast } from "sonner";

import { ApprovalCard } from "./approval-card";
import { ChatMessageFormatter } from "./chat-message-formatter";
import { AgentRoleKey, useAiChat } from "./use-ai-chat";

interface AiChatSheetProps {
    defaultAgent?: AgentRoleKey;
    trigger?: React.ReactNode;
    open?: boolean;
    onOpenChange?: (open: boolean) => void;
}

const AGENT_METAS: Record<
    AgentRoleKey,
    {
        name: string;
        badge: string;
        description: string;
        icon: React.ElementType;
        accent: string;
    }
> = {
    admin_executive: {
        name: "Admin Executive",
        badge: "Executive",
        description: "Campus analytics, visual charts, and official document formulation.",
        icon: Sparkles,
        accent: "text-purple-500",
    },
    student_advisor: {
        name: "Academic Advisor",
        badge: "Students",
        description: "Course selection, prerequisite verification, and schedule planning.",
        icon: GraduationCap,
        accent: "text-indigo-500",
    },
    faculty_copilot: {
        name: "Faculty Copilot",
        badge: "Academic Staff",
        description: "Rubric formulations, assignment grading drafts, and student risk intervention.",
        icon: Sparkles,
        accent: "text-emerald-500",
    },
    registrar_auditor: {
        name: "Registrar Auditor",
        badge: "Records",
        description: "Admissions spreadsheet audits, graduation clearance, and policy simulation.",
        icon: ShieldCheck,
        accent: "text-sky-500",
    },
    bursar_finance: {
        name: "Bursar & Finance",
        badge: "Ledger",
        description: "Statement of Account breakdown, tuition adjustments, and scholarship calculations.",
        icon: Calculator,
        accent: "text-amber-500",
    },
    campus_support: {
        name: "Campus Support",
        badge: "24/7",
        description: "Institutional policy answers, handbook search, and support ticket creation.",
        icon: HelpCircle,
        accent: "text-teal-500",
    },
};

const PREFERRED_MODEL_KEY = "koakademy_ai_preferred_model";

export function AiChatSheet({
    defaultAgent = "campus_support",
    trigger,
    open,
    onOpenChange,
}: AiChatSheetProps) {
    const [selectedAgent, setSelectedAgent] = React.useState<AgentRoleKey>(defaultAgent);
    const [availableModels, setAvailableModels] = React.useState<ModelOption[]>([]);
    const [selectedModel, setSelectedModel] = React.useState<string>("");
    const [modelPopoverOpen, setModelPopoverOpen] = React.useState(false);
    const [selectedFiles, setSelectedFiles] = React.useState<File[]>([]);
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
    });

    const activeMeta = AGENT_METAS[selectedAgent];
    const ActiveIcon = activeMeta.icon;

    const handleModelChange = React.useCallback((id: string) => {
        setSelectedModel(id);
        if (typeof window !== "undefined") {
            try {
                localStorage.setItem(PREFERRED_MODEL_KEY, id);
            } catch {
                // Ignore storage error
            }
        }
        setModelPopoverOpen(false);
        toast.success(`Active model: ${id}`);
    }, []);

    // Auto-scroll on new message chunks
    React.useEffect(() => {
        if (scrollAreaRef.current) {
            scrollAreaRef.current.scrollTop = scrollAreaRef.current.scrollHeight;
        }
    }, [messages, isLoading, lastError]);

    // Fetch available model options when sheet is opened
    React.useEffect(() => {
        if (open && availableModels.length === 0) {
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
                                        (m.badge?.includes("Default") || m.badge?.includes("Recommended") || m.id.includes("best-free") || m.id.includes("best-chat")) &&
                                        !m.id.includes("claude-opus-4-6-thinking-high")
                                ) || mapped[0];
                            setSelectedModel(recommended.id);
                        }
                    }
                })
                .catch(() => {});
        }
    }, [open, availableModels.length, selectedModel]);

    const activeModelName = React.useMemo(() => {
        const found = availableModels.find((m) => m.id === selectedModel);
        return found?.name || selectedModel || "Default Model";
    }, [availableModels, selectedModel]);

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

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            {trigger && <SheetTrigger asChild>{trigger}</SheetTrigger>}

            <SheetContent
                side="right"
                className="w-full sm:max-w-xl p-0 flex flex-col h-full border-l bg-background"
            >
                {/* Header */}
                <SheetHeader className="p-4 border-b space-y-2 bg-muted/20">
                    <div className="flex items-center justify-between">
                        <div className="flex items-center gap-2">
                            <div className={cn("p-1.5 rounded-lg border bg-background", activeMeta.accent)}>
                                <ActiveIcon className="size-5" />
                            </div>
                            <div>
                                <SheetTitle className="text-base font-semibold flex items-center gap-2">
                                    {activeMeta.name}
                                    <Badge variant="outline" className="text-[10px] font-normal">
                                        {activeMeta.badge}
                                    </Badge>
                                </SheetTitle>
                                <SheetDescription className="text-xs line-clamp-1">
                                    {activeMeta.description}
                                </SheetDescription>
                            </div>
                        </div>

                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            className="size-8 text-muted-foreground hover:text-foreground"
                            onClick={clearChat}
                            title="Clear conversation"
                        >
                            <RotateCcw className="size-4" />
                        </Button>
                    </div>

                    {/* Agent Role Pills */}
                    <div className="flex items-center gap-1.5 overflow-x-auto pb-1 pt-1 no-scrollbar">
                        {(Object.keys(AGENT_METAS) as AgentRoleKey[]).map((key) => {
                            const isSelected = selectedAgent === key;
                            const Icon = AGENT_METAS[key].icon;
                            return (
                                <button
                                    key={key}
                                    type="button"
                                    onClick={() => setSelectedAgent(key)}
                                    className={cn(
                                        "flex items-center gap-1.5 px-2.5 py-1 rounded-md text-[11px] font-medium border transition-colors shrink-0",
                                        isSelected
                                            ? "bg-primary text-primary-foreground border-primary"
                                            : "bg-background hover:bg-muted text-muted-foreground border-border"
                                    )}
                                >
                                    <Icon className="size-3" />
                                    {AGENT_METAS[key].name}
                                </button>
                            );
                        })}
                    </div>
                </SheetHeader>

                {/* Message Scroll Area */}
                <div ref={scrollAreaRef} className="flex-1 overflow-y-auto p-4 space-y-4">
                    {messages.length === 0 ? (
                        <div className="h-full flex flex-col items-center justify-center text-center p-6 text-muted-foreground space-y-3">
                            <div className={cn("p-3 rounded-2xl bg-muted/50 border", activeMeta.accent)}>
                                <ActiveIcon className="size-8" />
                            </div>
                            <div className="space-y-1">
                                <h4 className="text-sm font-semibold text-foreground">
                                    How can the {activeMeta.name} help?
                                </h4>
                                <p className="text-xs max-w-sm">
                                    {activeMeta.description} Upload documents or spreadsheets to analyze, formulate rubrics, or query institutional data.
                                </p>
                            </div>
                        </div>
                    ) : (
                        messages.map((msg) => {
                            const isUser = msg.role === "user";
                            return (
                                <div
                                    key={msg.id}
                                    className={cn(
                                        "flex items-start gap-2.5 w-full text-xs animate-in fade-in-50 duration-150",
                                        isUser ? "justify-end" : "justify-start"
                                    )}
                                >
                                    {!isUser && (
                                        <div className="size-7 rounded-xl border bg-primary/10 border-primary/20 text-primary flex items-center justify-center shrink-0 mt-0.5 shadow-2xs">
                                            <Bot className="size-3.5" />
                                        </div>
                                    )}

                                    <div
                                        className={cn(
                                            "flex flex-col gap-1 max-w-[85%]",
                                            isUser ? "items-end" : "items-start"
                                        )}
                                    >
                                        {/* Render user attachments */}
                                        {msg.attachments && msg.attachments.length > 0 && (
                                            <div className={cn("flex flex-wrap gap-1.5 pb-1", isUser ? "justify-end" : "justify-start")}>
                                                {msg.attachments.map((att, i) => (
                                                    <div
                                                        key={i}
                                                        className="flex items-center gap-1 px-2 py-0.5 rounded-md bg-muted border border-border/60 text-[11px] font-mono shadow-2xs"
                                                    >
                                                        <Paperclip className="size-3 text-muted-foreground" />
                                                        <span className="truncate max-w-[150px] text-foreground">{att.name}</span>
                                                        <span className="text-[10px] text-muted-foreground">({Math.round(att.size / 1024)} KB)</span>
                                                    </div>
                                                ))}
                                            </div>
                                        )}

                                        <div
                                            className={cn(
                                                "px-4 py-2.5 rounded-2xl text-sm leading-relaxed transition-all",
                                                isUser
                                                    ? "bg-primary text-primary-foreground rounded-tr-xs shadow-xs font-sans text-left"
                                                    : "bg-muted/50 border border-border/70 rounded-tl-xs text-foreground shadow-2xs space-y-2"
                                            )}
                                        >
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

                                            {/* Pending Approvals within Assistant Message */}
                                            {msg.pendingApprovals?.map((approval) => (
                                                <ApprovalCard
                                                    key={approval.id}
                                                    approval={approval}
                                                    onDecision={submitDecision}
                                                    disabled={isLoading}
                                                />
                                            ))}
                                        </div>
                                    </div>

                                    {isUser && (
                                        <div className="size-7 rounded-xl bg-primary/15 border border-primary/25 text-primary flex items-center justify-center shrink-0 mt-0.5 shadow-2xs">
                                            <User className="size-3.5" />
                                        </div>
                                    )}
                                </div>
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
                                        sendPrompt(lastPrompt, selectedFiles);
                                    }
                                }}
                                variant="Card"
                            />
                        </div>
                    )}
                </div>

                {/* Staged File Upload Chips */}
                {selectedFiles.length > 0 && (
                    <div className="px-4 py-2 border-t bg-muted/20 flex flex-wrap gap-1.5">
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
                                                    <span className="truncate max-w-[200px] font-mono">{activeModelName}</span>
                                                    <ChevronDown className="size-3 text-muted-foreground" />
                                                </button>
                                            </PopoverTrigger>
                                            <PopoverContent className="w-[380px] sm:w-[420px] p-3.5 shadow-2xl rounded-2xl border border-border/80 bg-background" align="start">
                                                <div className="space-y-2">
                                                    <div className="flex items-center justify-between pb-1.5 border-b">
                                                        <span className="text-xs font-semibold text-foreground">Configured AI Models</span>
                                                        <span className="text-[10.5px] text-muted-foreground font-mono">
                                                            {availableModels.length} models
                                                        </span>
                                                    </div>

                                                    <ModelSelector
                                                        models={availableModels}
                                                        value={selectedModel}
                                                        onChange={handleModelChange}
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
                                placeholder={`Ask ${activeMeta.name} or drop files here...`}
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
            </SheetContent>
        </Sheet>
    );
}
