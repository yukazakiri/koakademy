import { ErrorState, ModelOption, ModelSelector } from "@/components/spectrumui";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import {
    InputGroup,
    InputGroupAddon,
    InputGroupButton,
    InputGroupTextarea,
} from "@/components/ui/input-group";
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
    AlertCircle,
    Bot,
    Calculator,
    CheckCircle2,
    ChevronDown,
    Cpu,
    FileSpreadsheet,
    FileText,
    FileType,
    GraduationCap,
    HelpCircle,
    Image as ImageIcon,
    Loader2,
    Paperclip,
    RotateCcw,
    Send,
    ShieldCheck,
    Sparkles,
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
    const fileInputRef = React.useRef<HTMLInputElement>(null);
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
    } = useAiChat({
        agent: selectedAgent,
    });

    const activeMeta = AGENT_METAS[selectedAgent];
    const ActiveIcon = activeMeta.icon;

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
                        }));
                        setAvailableModels(mapped);
                        if (!selectedModel) {
                            setSelectedModel(mapped[0].id);
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

    const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        if (!e.target.files) return;
        const newFiles = Array.from(e.target.files);

        // Check 20MB limit
        const oversized = newFiles.filter((f) => f.size > 20 * 1024 * 1024);
        if (oversized.length > 0) {
            toast.error("Files larger than 20MB cannot be uploaded.");
            return;
        }

        setSelectedFiles((prev) => [...prev, ...newFiles]);
        if (fileInputRef.current) fileInputRef.current.value = "";
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

    const handleKeyDown = (e: React.KeyboardEvent<HTMLTextAreaElement>) => {
        if (e.key === "Enter" && !e.shiftKey) {
            e.preventDefault();
            handleSend();
        }
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
                                    className={cn("flex gap-3 text-xs leading-relaxed", isUser ? "justify-end" : "justify-start")}
                                >
                                    {!isUser && (
                                        <div className="size-7 rounded-full border bg-muted flex items-center justify-center shrink-0 mt-0.5">
                                            <Bot className="size-3.5 text-primary" />
                                        </div>
                                    )}

                                    <div
                                        className={cn(
                                            "max-w-[85%] rounded-2xl px-3.5 py-2.5 space-y-2",
                                            isUser
                                                ? "bg-primary text-primary-foreground rounded-tr-sm"
                                                : "bg-muted/40 border border-border/60 rounded-tl-sm text-foreground"
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
                                            <p className="whitespace-pre-wrap">{msg.content}</p>
                                        ) : (
                                            <ChatMessageFormatter content={msg.content} />
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

                                    {isUser && (
                                        <div className="size-7 rounded-full bg-primary/10 border border-primary/20 flex items-center justify-center shrink-0 mt-0.5">
                                            <User className="size-3.5 text-primary" />
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

                        {isLoading && (
                            <div className="flex items-center gap-2 text-xs text-muted-foreground pt-1">
                                <Loader2 className="size-3.5 animate-spin text-primary" />
                                <span>{activeMeta.name} is thinking & evaluating tools...</span>
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

                    {/* Input Area with InputGroup */}
                    <div className="p-3 border-t bg-background/95 backdrop-blur space-y-2">
                        <InputGroup className="min-h-[92px] rounded-xl border border-input focus-within:border-primary focus-within:ring-2 focus-within:ring-primary/20 transition-all bg-background">
                            {/* Top Addon: Model Selector */}
                            <InputGroupAddon align="block-start" className="justify-between border-b border-border/40 pb-1.5 pt-1 px-2.5">
                                <div className="flex items-center gap-1.5">
                                    <Popover open={modelPopoverOpen} onOpenChange={setModelPopoverOpen}>
                                        <PopoverTrigger asChild>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                className="h-6 px-2 text-[11px] font-mono text-muted-foreground hover:text-foreground gap-1 bg-muted/30 hover:bg-muted/60 rounded-md"
                                                title="Select AI Model"
                                            >
                                                <Cpu className="size-3 text-indigo-500" />
                                                <span className="truncate max-w-[160px]">{activeModelName}</span>
                                                <ChevronDown className="size-3 opacity-60" />
                                            </Button>
                                        </PopoverTrigger>
                                        <PopoverContent className="w-80 p-3 shadow-xl rounded-xl" align="start">
                                            <div className="space-y-2">
                                                <div className="flex items-center justify-between border-b pb-1.5">
                                                    <span className="text-xs font-semibold text-foreground">Select AI Model</span>
                                                    <span className="text-[10px] text-muted-foreground font-mono">
                                                        {availableModels.length} available
                                                    </span>
                                                </div>

                                                {availableModels.length > 0 ? (
                                                    <ModelSelector
                                                        models={availableModels}
                                                        value={selectedModel}
                                                        onChange={(id) => {
                                                            setSelectedModel(id);
                                                            setModelPopoverOpen(false);
                                                            toast.success(`Active model: ${id}`);
                                                        }}
                                                        variant="List"
                                                    />
                                                ) : (
                                                    <div className="space-y-1.5 py-2">
                                                        <span className="text-xs text-muted-foreground block">
                                                            Enter model identifier:
                                                        </span>
                                                        <input
                                                            type="text"
                                                            placeholder="e.g. gpt-4o or mistral-7b"
                                                            value={selectedModel}
                                                            onChange={(e) => setSelectedModel(e.target.value)}
                                                            className="w-full text-xs font-mono px-2.5 py-1.5 rounded-lg border bg-background"
                                                        />
                                                    </div>
                                                )}
                                            </div>
                                        </PopoverContent>
                                    </Popover>
                                </div>

                                <div className="flex items-center gap-1.5 text-[10px] text-muted-foreground">
                                    <span className="hidden sm:inline">Press Enter to send</span>
                                </div>
                            </InputGroupAddon>
                            <InputGroupTextarea
                                placeholder={`Ask ${activeMeta.name} or attach files... (Enter to send)`}
                                value={input}
                                onChange={(e) => setInput(e.target.value)}
                                onKeyDown={handleKeyDown}
                                disabled={isLoading}
                                rows={2}
                                className="text-xs py-2 px-3 leading-relaxed placeholder:text-muted-foreground/70"
                            />

                            <InputGroupAddon align="block-end" className="justify-between pt-1 pb-1.5 px-2 border-t border-border/40">
                                <div className="flex items-center gap-1">
                                    <input
                                        ref={fileInputRef}
                                        type="file"
                                        multiple
                                        accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.csv,.txt,.md,.json"
                                        onChange={handleFileChange}
                                        className="hidden"
                                    />

                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        onClick={() => fileInputRef.current?.click()}
                                        disabled={isLoading}
                                        className="size-7 text-muted-foreground hover:text-foreground"
                                        title="Attach Excel, PDF, documents or images"
                                    >
                                        <Paperclip className="size-3.5" />
                                    </Button>
                                </div>

                                <InputGroupButton
                                    type="button"
                                    size="xs"
                                    variant="default"
                                    onClick={handleSend}
                                    disabled={(!input.trim() && selectedFiles.length === 0) || isLoading}
                                    className="h-7 px-3 text-xs gap-1.5 bg-primary text-primary-foreground hover:bg-primary/90 rounded-lg shadow-xs"
                                >
                                    {isLoading ? <Loader2 className="size-3 animate-spin" /> : <Send className="size-3" />}
                                    <span>Send</span>
                                </InputGroupButton>
                            </InputGroupAddon>
                        </InputGroup>

                        <div className="flex items-center justify-between text-[11px] text-muted-foreground px-1">
                            <span>Supports Excel (.xlsx, .csv), PDF, Docs & Images</span>
                            <span>Shift + Enter for new line</span>
                        </div>
                    </div>
            </SheetContent>
        </Sheet>
    );
}
