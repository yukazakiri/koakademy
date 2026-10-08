"use client";

import {
    Message,
    MessageAvatar,
    MessageContent,
    MessageFooter,
} from "@/components/agents/message";
import { ThinkingShimmer } from "@/components/agents/loading-states/thinking-shimmer";
import { ModelOption, ModelSelector } from "@/components/spectrumui";
import { Button } from "@/components/ui/button";
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from "@/components/ui/popover";
import { TooltipProvider } from "@/components/ui/tooltip";
import { cn } from "@/lib/utils";
import type { User } from "@/types/user";
import { router } from "@inertiajs/react";
import {
    Calculator,
    ChevronDown,
    Cpu,
    ExternalLink,
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
    Square,
    UploadCloud,
    X,
} from "lucide-react";
import * as React from "react";
import { toast } from "sonner";

import { AiCircleLogo } from "./ai-circle-logo";
import { ApprovalCard } from "./approval-card";
import { ChatMessageFormatter } from "./chat-message-formatter";
import { AssistantMessageActions, UserMessageActions } from "./message-actions";
import { type AgentRoleKey, useAiChat } from "./use-ai-chat";

const PREFERRED_MODEL_KEY = "koakademy_ai_preferred_model";
const WIDGET_OPEN_KEY = "koakademy_ai_widget_open";
const WIDGET_EXPANDED_KEY = "koakademy_ai_widget_expanded";
const WIDGET_AGENT_KEY = "koakademy_ai_widget_agent";

interface AdminAiFloatingWidgetProps {
    user: User;
}

const ADMIN_AGENTS: {
    key: AgentRoleKey;
    label: string;
    description: string;
    icon: React.ElementType;
    badge: string;
    suggestions: string[];
}[] = [
    {
        key: "admin_executive",
        label: "Executive & Analytics",
        description:
            "Campus analytics, data charts, and official document formulation. Upload spreadsheets to analyze, plot interactive charts, or download formal reports.",
        icon: AiCircleLogo,
        badge: "Executive",
        suggestions: [
            "Analyze enrollment demographics and plot a bar chart",
            "Audit student clearance holds across campus departments",
            "Generate an executive tuition revenue and billing brief",
        ],
    },
    {
        key: "registrar_auditor",
        label: "Registrar Auditor",
        description:
            "Admissions spreadsheet audits, graduation clearance, and policy simulation. Verify degree checklists, prerequisite chains, and transcript accuracy.",
        icon: ShieldCheck,
        badge: "Records",
        suggestions: [
            "Audit student graduation clearance and identify pending holds",
            "Run a prerequisite and curriculum audit for CS department",
            "Simulate academic retention rate across student cohorts",
        ],
    },
    {
        key: "bursar_finance",
        label: "Bursar & Finance",
        description:
            "Statement of Account breakdown, tuition adjustments, and scholarship calculations. Audit student ledgers, billing schedules, and account balances.",
        icon: Calculator,
        badge: "Ledger",
        suggestions: [
            "Break down student account balances and generate financial brief",
            "Audit unpaid tuition adjustments and scholarship disbursements",
            "Generate a revenue collection report by academic term",
        ],
    },
    {
        key: "campus_support",
        label: "Campus Support",
        description:
            "Institutional policies, student handbooks, and ticket escalation. Lookup operating procedures, official circulars, and departmental contacts.",
        icon: HelpCircle,
        badge: "24/7",
        suggestions: [
            "Summarize campus policy on late enrollment and grading appeals",
            "Draft an official student circular for academic year schedule",
            "Check standard operating procedure for student clearance dispute",
        ],
    },
];

export function AdminAiFloatingWidget({ user }: AdminAiFloatingWidgetProps) {
    const [isOpen, setIsOpen] = React.useState<boolean>(() => {
        if (typeof window !== "undefined") {
            try {
                return sessionStorage.getItem(WIDGET_OPEN_KEY) === "true";
            } catch {
                return false;
            }
        }
        return false;
    });
    const [isExpanded, setIsExpanded] = React.useState<boolean>(() => {
        if (typeof window !== "undefined") {
            try {
                return sessionStorage.getItem(WIDGET_EXPANDED_KEY) === "true";
            } catch {
                return false;
            }
        }
        return false;
    });
    const [selectedAgent, setSelectedAgent] = React.useState<AgentRoleKey>(() => {
        if (typeof window !== "undefined") {
            try {
                const saved = sessionStorage.getItem(WIDGET_AGENT_KEY) as AgentRoleKey;
                if (saved && ADMIN_AGENTS.some((a) => a.key === saved)) {
                    return saved;
                }
            } catch {
                // Ignore storage errors
            }
        }
        return "admin_executive";
    });

    const handleOpenChange = React.useCallback((open: boolean) => {
        setIsOpen(open);
        if (typeof window !== "undefined") {
            try {
                sessionStorage.setItem(WIDGET_OPEN_KEY, String(open));
            } catch {
                // Ignore storage errors
            }
        }
    }, []);

    const handleExpandedChange = React.useCallback((expanded: boolean) => {
        setIsExpanded(expanded);
        if (typeof window !== "undefined") {
            try {
                sessionStorage.setItem(WIDGET_EXPANDED_KEY, String(expanded));
            } catch {
                // Ignore storage errors
            }
        }
    }, []);

    const handleAgentChange = React.useCallback((agentKey: AgentRoleKey) => {
        setSelectedAgent(agentKey);
        if (typeof window !== "undefined") {
            try {
                sessionStorage.setItem(WIDGET_AGENT_KEY, agentKey);
            } catch {
                // Ignore storage errors
            }
        }
    }, []);

    // Dynamic model options from configured providers
    const [availableModels, setAvailableModels] = React.useState<ModelOption[]>(
        []
    );
    const [selectedModel, setSelectedModel] = React.useState<string>("");
    const [modelPopoverOpen, setModelPopoverOpen] = React.useState(false);

    // File staging state
    const [selectedFiles, setSelectedFiles] = React.useState<File[]>([]);
    const [isDragging, setIsDragging] = React.useState(false);

    const scrollAreaRef = React.useRef<HTMLDivElement>(null);
    const fileInputRef = React.useRef<HTMLInputElement>(null);
    const textareaRef = React.useRef<HTMLTextAreaElement>(null);

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
        submitAllDecisions,
        autoApprove,
        setAutoApprove,
        resendUserMessage,
        regenerateAssistant,
        clearChat,
        stop,
    } = useAiChat({
        agent: selectedAgent,
        endpoint: "/administrators/ai/chat",
        persistenceKey: "koakademy_ai_widget",
    });

    // Auto-scroll on new message chunks
    React.useEffect(() => {
        if (scrollAreaRef.current) {
            scrollAreaRef.current.scrollTop = scrollAreaRef.current.scrollHeight;
        }
    }, [messages, isLoading, lastError]);

    // Handle user model selection with localStorage persistence
    const handleModelChange = React.useCallback((id: string) => {
        setSelectedModel(id);
        if (typeof window !== "undefined") {
            try {
                localStorage.setItem(PREFERRED_MODEL_KEY, id);
            } catch {
                // Ignore storage quotas
            }
        }
        setModelPopoverOpen(false);
        toast.success(`Active model: ${id}`);
    }, []);

    // Fetch available models once when opened
    React.useEffect(() => {
        if (isOpen && availableModels.length === 0) {
            fetch("/administrators/ai/analytics-summary", {
                headers: { "X-Requested-With": "XMLHttpRequest" },
            })
                .then((res) => res.json())
                .then((data) => {
                    if (Array.isArray(data.models) && data.models.length > 0) {
                        const mapped: ModelOption[] = data.models.map(
                            (m: Record<string, unknown>) => ({
                                id: String(m.id || ""),
                                name: String(m.name || m.id || ""),
                                badge: typeof m.badge === "string" ? m.badge : undefined,
                                description: typeof m.description === "string" ? m.description : undefined,
                                provider: typeof m.provider === "string" ? m.provider : undefined,
                                provider_name: typeof m.provider_name === "string" ? m.provider_name : undefined,
                            })
                        );
                        setAvailableModels(mapped);

                        const saved =
                            typeof window !== "undefined"
                                ? localStorage.getItem(PREFERRED_MODEL_KEY)
                                : null;
                        if (saved && mapped.some((m) => m.id === saved)) {
                            setSelectedModel(saved);
                        } else if (!selectedModel) {
                            const serverDefault =
                                typeof data.default_model === "string"
                                    ? data.default_model
                                    : "";
                            const byServerDefault = serverDefault
                                ? mapped.find((m) => m.id === serverDefault)
                                : undefined;
                            const byDefaultBadge = mapped.find((m) =>
                                m.badge?.includes("Default")
                            );
                            const byPrimary =
                                typeof data.primary_provider === "string"
                                    ? mapped.find(
                                          (m) =>
                                              m.provider ===
                                              data.primary_provider
                                      )
                                    : undefined;
                            const recommended =
                                byServerDefault ||
                                byDefaultBadge ||
                                byPrimary ||
                                mapped[0];
                            if (recommended) setSelectedModel(recommended.id);
                        }
                    }
                })
                .catch(() => {
                    // Silently keep defaults on network error
                });
        }
    }, [isOpen, availableModels.length, selectedModel]);

    const activeMeta =
        ADMIN_AGENTS.find((a) => a.key === selectedAgent) || ADMIN_AGENTS[0];
    const ActiveIcon = activeMeta.icon;

    const handleFilesAdded = (filesToAdd: FileList | File[]) => {
        const fileList = Array.from(filesToAdd);
        const oversized = fileList.filter((f) => f.size > 20 * 1024 * 1024);
        if (oversized.length > 0) {
            toast.error("Files larger than 20MB cannot be uploaded.");
            return;
        }

        setSelectedFiles((prev) => [...prev, ...fileList]);
        toast.success(
            `Attached ${fileList.length} ${fileList.length === 1 ? "file" : "files"}.`
        );
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
        setInput("");
        if (textareaRef.current) {
            textareaRef.current.style.height = "auto";
        }
    };

    const handleKeyDown = (e: React.KeyboardEvent<HTMLTextAreaElement>) => {
        if (e.key === "Enter" && !e.shiftKey) {
            e.preventDefault();
            handleSend();
        }
    };

    const handleSuggestionClick = (prompt: string) => {
        sendPrompt(prompt, [], {
            model: selectedModel || undefined,
        });
    };

    const getFileIcon = (file: File) => {
        const ext = file.name.split(".").pop()?.toLowerCase();
        if (["xlsx", "xls", "csv"].includes(ext || "")) {
            return <FileSpreadsheet className="size-3.5 text-emerald-500" />;
        }
        if (
            ["png", "jpg", "jpeg", "webp", "gif"].includes(ext || "") ||
            file.type.startsWith("image/")
        ) {
            return <ImageIcon className="size-3.5 text-indigo-500" />;
        }
        if (["pdf"].includes(ext || "")) {
            return <FileText className="size-3.5 text-rose-500" />;
        }
        return <FileType className="size-3.5 text-sky-500" />;
    };

    const activeModelName = React.useMemo(() => {
        const found = availableModels.find((m) => m.id === selectedModel);
        if (!found) return selectedModel || "Auto Best Free";
        const cleanName = found.name.replace(
            /^(?:no-think\/|dva\/|oc\/|cx\/|cxa\/|agy\/|zed-hosted\/)+/,
            ""
        );
        return cleanName.length > 20
            ? cleanName.slice(0, 18) + "…"
            : cleanName;
    }, [availableModels, selectedModel]);

    return (
        <TooltipProvider>
            <div className="fixed right-6 bottom-6 z-50 select-none">
                {/* 1. Closed State: Floating Action Button (FAB) */}
                {!isOpen && (
                    <button
                        type="button"
                        onClick={() => handleOpenChange(true)}
                        className="group relative flex size-14 cursor-pointer items-center justify-center rounded-2xl bg-neutral-950 border border-neutral-800 text-white shadow-2xl ring-4 ring-indigo-500/20 transition-all duration-200 hover:scale-105 hover:ring-indigo-500/40 active:scale-95 overflow-hidden"
                        title="Open Administrative AI Copilot"
                    >
                        <AiCircleLogo size={42} useOrb={true} className="pointer-events-none transition-transform duration-300 group-hover:scale-110" />

                        {/* Online status indicator */}
                        <span className="absolute top-1 right-1 flex size-3">
                            <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75" />
                            <span className="relative inline-flex size-3 rounded-full border-2 border-white bg-emerald-500 shadow-xs dark:border-gray-900" />
                        </span>
                    </button>
                )}

                {/* 2. Open State: Floating Chat Modal */}
                {isOpen && (
                    <div
                        onDragOver={(e) => {
                            e.preventDefault();
                            setIsDragging(true);
                        }}
                        onDragLeave={(e) => {
                            e.preventDefault();
                            setIsDragging(false);
                        }}
                        onDrop={(e) => {
                            e.preventDefault();
                            setIsDragging(false);
                            if (e.dataTransfer.files?.length) {
                                handleFilesAdded(e.dataTransfer.files);
                            }
                        }}
                        className={cn(
                            "relative flex flex-col overflow-hidden rounded-3xl border border-neutral-800/90 bg-[#121215]/95 text-foreground shadow-2xl backdrop-blur-2xl transition-all duration-200 animate-in fade-in zoom-in-95",
                            isExpanded
                                ? "h-[88vh] w-[95vw] sm:w-[760px]"
                                : "h-[680px] max-h-[88vh] w-[92vw] sm:w-[500px] md:w-[540px]"
                        )}
                    >
                        {/* Drag and Drop Overlay */}
                        {isDragging && (
                            <div className="absolute inset-0 z-50 flex flex-col items-center justify-center gap-3 bg-[#121215]/95 p-6 backdrop-blur-md">
                                <div className="flex size-14 items-center justify-center rounded-2xl border-2 border-dashed border-primary bg-primary/10">
                                    <UploadCloud className="size-8 text-primary animate-bounce" />
                                </div>
                                <p className="text-sm font-semibold text-white">
                                    Drop files to attach
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    Excel (.xlsx, .csv), PDF, Word, Images
                                </p>
                            </div>
                        )}

                        {/* Header Matching Screenshot */}
                        <div className="flex flex-col border-b border-neutral-800/80 bg-neutral-900/40 px-4 pt-3.5 pb-2.5">
                            <div className="flex items-center justify-between">
                                <div className="flex items-center gap-2.5">
                                    <div className="flex size-9 items-center justify-center rounded-xl bg-neutral-900 border border-neutral-800 overflow-hidden shadow-md">
                                        <AiCircleLogo size={32} useOrb={true} className="pointer-events-none" />
                                    </div>
                                    <div>
                                        <div className="flex items-center gap-2">
                                            <h3 className="text-sm font-bold tracking-tight text-white">
                                                Administrative Copilot
                                            </h3>
                                            <span className="flex items-center gap-1 rounded-full border border-emerald-500/30 bg-emerald-500/15 px-2 py-0.5 font-mono text-[10px] font-semibold text-emerald-400">
                                                <span className="size-1.5 rounded-full bg-emerald-500" />
                                                Live
                                            </span>
                                        </div>
                                        <p className="mt-0.5 line-clamp-1 text-[11px] text-muted-foreground">
                                            Campus analytics, document drafting
                                            & academic audits
                                        </p>
                                    </div>
                                </div>

                                <div className="flex items-center gap-0.5 text-muted-foreground">
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        className="size-7.5 rounded-lg text-muted-foreground hover:bg-neutral-800 hover:text-white"
                                        onClick={() => {
                                            clearChat();
                                            setSelectedFiles([]);
                                            toast.success(
                                                "New chat started. Previous transcript cleared."
                                            );
                                        }}
                                        title="New chat / Clear conversation"
                                    >
                                        <RotateCcw className="size-3.5" />
                                    </Button>

                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        className="size-7.5 rounded-lg text-muted-foreground hover:bg-neutral-800 hover:text-white"
                                        onClick={() => {
                                            handleOpenChange(false);
                                            const targetUrl = conversationId
                                                ? `/administrators/ai?conversation=${conversationId}`
                                                : "/administrators/ai";
                                            router.visit(targetUrl);
                                        }}
                                        title="Open in full page AI Chat"
                                    >
                                        <ExternalLink className="size-3.5" />
                                    </Button>

                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        className="hidden size-7.5 rounded-lg text-muted-foreground hover:bg-neutral-800 hover:text-white sm:inline-flex"
                                        onClick={() =>
                                            handleExpandedChange(!isExpanded)
                                        }
                                        title={
                                            isExpanded
                                                ? "Collapse view"
                                                : "Expand view"
                                        }
                                    >
                                        {isExpanded ? (
                                            <Minimize2 className="size-3.5" />
                                        ) : (
                                            <Maximize2 className="size-3.5" />
                                        )}
                                    </Button>

                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        className="size-7.5 rounded-lg text-muted-foreground hover:bg-neutral-800 hover:text-white"
                                        onClick={() => handleOpenChange(false)}
                                        title="Close widget"
                                    >
                                        <X className="size-4" />
                                    </Button>
                                </div>
                            </div>

                            {/* Specialist Tabs Matching Screenshot */}
                            <div className="no-scrollbar flex items-center gap-1.5 overflow-x-auto pt-2.5 pb-0.5">
                                {ADMIN_AGENTS.map((agent) => {
                                    const isSelected =
                                        selectedAgent === agent.key;
                                    const Icon = agent.icon;
                                    return (
                                        <button
                                            key={agent.key}
                                            type="button"
                                            onClick={() =>
                                                handleAgentChange(agent.key)
                                            }
                                            className={cn(
                                                "flex shrink-0 cursor-pointer items-center gap-1.5 rounded-xl px-3 py-1.5 text-xs font-medium transition-all",
                                                isSelected
                                                    ? "bg-[#9333ea] font-semibold text-white shadow-md shadow-purple-500/20"
                                                    : "border border-neutral-800/80 bg-neutral-900/60 text-neutral-400 hover:border-neutral-700 hover:bg-neutral-800/70 hover:text-neutral-200"
                                            )}
                                        >
                                            <Icon className="size-3.5" />
                                            <span>{agent.label}</span>
                                        </button>
                                    );
                                })}
                            </div>
                        </div>

                        {/* Chat Messages Body */}
                        <div
                            ref={scrollAreaRef}
                            className="flex-1 space-y-4 overflow-y-auto p-4"
                        >
                            {messages.length === 0 ? (
                                /* Empty State Centered Card Matching Screenshot */
                                <div className="flex h-full flex-col items-center justify-center px-2 py-4 text-center">
                                    <div className="mx-auto mb-3 flex size-12 items-center justify-center rounded-2xl border border-neutral-800 bg-neutral-900/80 text-neutral-300 shadow-inner">
                                        <ActiveIcon className="size-6 text-neutral-300" />
                                    </div>

                                    <h4 className="text-base font-bold tracking-tight text-white sm:text-lg">
                                        {activeMeta.label}
                                    </h4>

                                    <p className="mt-1.5 mb-6 max-w-sm text-xs leading-relaxed text-muted-foreground sm:max-w-md">
                                        {activeMeta.description}
                                    </p>

                                    {/* Vertical Stacked Suggestion Pills */}
                                    <div className="w-full max-w-md space-y-2">
                                        {activeMeta.suggestions.map(
                                            (suggestion, idx) => (
                                                <button
                                                    key={idx}
                                                    type="button"
                                                    onClick={() =>
                                                        handleSuggestionClick(
                                                            suggestion
                                                        )
                                                    }
                                                    className="w-full cursor-pointer rounded-full border border-neutral-800/90 bg-neutral-900/70 px-4 py-2 text-center text-xs font-medium text-neutral-300 shadow-xs transition-colors hover:border-neutral-700 hover:bg-neutral-800/90 hover:text-white active:scale-[0.99]"
                                                >
                                                    {suggestion}
                                                </button>
                                            )
                                        )}
                                    </div>
                                </div>
                            ) : (
                                messages.map((msg) => {
                                    const isUser = msg.role === "user";
                                    return (
                                        <Message
                                            key={msg.id}
                                            from={
                                                isUser ? "user" : "assistant"
                                            }
                                            animateIn={true}
                                            className="gap-2.5"
                                        >
                                            {/* Avatar */}
                                            <MessageAvatar
                                                className={cn(
                                                    "size-7 rounded-xl border text-xs",
                                                    isUser
                                                        ? "border-primary/30 bg-primary/20 text-primary"
                                                        : "border-indigo-500/30 bg-neutral-950 text-indigo-400 overflow-hidden flex items-center justify-center"
                                                )}
                                            >
                                                {isUser ? (
                                                    user.avatar ? (
                                                        <img
                                                            src={user.avatar}
                                                            alt={user.name || "User"}
                                                            className="size-full rounded-xl object-cover"
                                                        />
                                                    ) : (
                                                        <span className="font-semibold uppercase text-[10px]">
                                                            {user.name?.slice(0, 2).toUpperCase() || "AD"}
                                                        </span>
                                                    )
                                                ) : (
                                                    <AiCircleLogo className="size-4" />
                                                )}
                                            </MessageAvatar>

                                            <MessageContent
                                                className={cn(
                                                    "max-w-[85%]",
                                                    isUser
                                                        ? "items-end"
                                                        : "items-start"
                                                )}
                                            >
                                                {/* File Attachments */}
                                                {msg.attachments &&
                                                    msg.attachments.length >
                                                        0 && (
                                                        <div
                                                            className={cn(
                                                                "flex flex-wrap gap-1.5 pb-1",
                                                                isUser
                                                                    ? "justify-end"
                                                                    : "justify-start"
                                                            )}
                                                        >
                                                            {msg.attachments.map(
                                                                (att, i) => (
                                                                    <div
                                                                        key={i}
                                                                        className="flex items-center gap-1.5 rounded-lg border border-neutral-800 bg-neutral-900/90 px-2.5 py-1 font-mono text-[11px] text-neutral-200"
                                                                    >
                                                                        <Paperclip className="size-3 text-neutral-400" />
                                                                        <span className="max-w-[140px] truncate">
                                                                            {
                                                                                att.name
                                                                            }
                                                                        </span>
                                                                        <span className="text-[10px] text-muted-foreground">
                                                                            (
                                                                            {Math.round(
                                                                                att.size /
                                                                                    1024
                                                                            )}{" "}
                                                                            KB)
                                                                        </span>
                                                                    </div>
                                                                )
                                                            )}
                                                        </div>
                                                    )}

                                                {/* Minimalist Message Content */}
                                                {isUser ? (
                                                    <div className="w-full flex justify-end">
                                                        <div className="max-w-[88%] text-left">
                                                            <p className="whitespace-pre-wrap text-sm leading-relaxed text-neutral-100 font-medium">
                                                                {msg.content}
                                                            </p>
                                                        </div>
                                                    </div>
                                                ) : (
                                                    <div className="w-full space-y-2.5 min-w-0">
                                                        <ChatMessageFormatter
                                                            content={
                                                                msg.content
                                                            }
                                                            reasoning={
                                                                msg.reasoning
                                                            }
                                                            toolCalls={
                                                                msg.toolCalls
                                                            }
                                                            sources={
                                                                msg.sources
                                                            }
                                                            isStreaming={
                                                                isLoading &&
                                                                msg.id ===
                                                                    messages.at(
                                                                        -1
                                                                    )?.id
                                                            }
                                                        />

                                                        {/* Approvals */}
                                                        {msg.pendingApprovals && msg.pendingApprovals.length > 0 && (
                                                            <div className="space-y-2 my-2">
                                                                {msg.pendingApprovals.length > 1 && (
                                                                    <div className="flex items-center justify-between p-2.5 rounded-xl border border-amber-500/30 bg-amber-500/10 text-xs">
                                                                        <span className="font-semibold text-amber-400 flex items-center gap-1.5">
                                                                            <ShieldCheck className="size-4 text-amber-400" />
                                                                            {msg.pendingApprovals.length} Actions Awaiting Confirmation
                                                                        </span>
                                                                        <div className="flex items-center gap-1.5">
                                                                            <Button
                                                                                size="sm"
                                                                                variant="outline"
                                                                                className="h-7 text-xs border-amber-500/40 text-amber-300 hover:bg-amber-500/15"
                                                                                onClick={() => submitAllDecisions("reject")}
                                                                                disabled={isLoading}
                                                                            >
                                                                                Reject All
                                                                            </Button>
                                                                            <Button
                                                                                size="sm"
                                                                                className="h-7 text-xs bg-emerald-600 hover:bg-emerald-700 text-white font-medium"
                                                                                onClick={() => submitAllDecisions("approve")}
                                                                                disabled={isLoading}
                                                                            >
                                                                                Approve All
                                                                            </Button>
                                                                        </div>
                                                                    </div>
                                                                )}
                                                                {msg.pendingApprovals.map((approval) => (
                                                                    <ApprovalCard
                                                                        key={approval.id}
                                                                        approval={approval}
                                                                        onDecision={submitDecision}
                                                                        onApproveAll={msg.pendingApprovals!.length > 1 ? () => submitAllDecisions("approve") : undefined}
                                                                        disabled={isLoading}
                                                                    />
                                                                ))}
                                                            </div>
                                                        )}
                                                    </div>
                                                )}

                                                {/* Message Footer Actions */}
                                                <MessageFooter className="gap-1.5 px-0.5 text-[11px] text-muted-foreground">
                                                    {isUser ? (
                                                        <UserMessageActions
                                                            content={
                                                                msg.content
                                                            }
                                                            disabled={isLoading}
                                                            onResend={() =>
                                                                resendUserMessage(
                                                                    msg.id,
                                                                    {
                                                                        model:
                                                                            selectedModel ||
                                                                            undefined,
                                                                    }
                                                                )
                                                            }
                                                        />
                                                    ) : (
                                                        !(
                                                            isLoading &&
                                                            msg.id ===
                                                                messages.at(-1)
                                                                    ?.id
                                                        ) && (
                                                            <AssistantMessageActions
                                                                content={
                                                                    msg.content
                                                                }
                                                                disabled={
                                                                    isLoading
                                                                }
                                                                onRegenerate={() =>
                                                                    regenerateAssistant(
                                                                        msg.id,
                                                                        {
                                                                            model:
                                                                                selectedModel ||
                                                                                undefined,
                                                                        }
                                                                    )
                                                                }
                                                            />
                                                        )
                                                    )}
                                                </MessageFooter>
                                            </MessageContent>
                                        </Message>
                                    );
                                })
                            )}

                            {/* Live Shimmer Indicator when loading without initial tokens */}
                            {isLoading &&
                                messages.at(-1)?.role === "user" && (
                                    <div className="flex items-center gap-2.5 px-1 py-1 text-xs text-muted-foreground">
                                        <div className="flex size-6 items-center justify-center rounded-lg border border-indigo-500/30 bg-indigo-500/10 text-indigo-400">
                                            <AiCircleLogo className="size-3.5 animate-spin" />
                                        </div>
                                        <ThinkingShimmer duration={1.6}>
                                            {activeMeta.label} is analyzing
                                            campus records…
                                        </ThinkingShimmer>
                                    </div>
                                )}

                            {/* Error Banner with retry */}
                            {lastError && (
                                <div className="space-y-2 rounded-2xl border border-destructive/30 bg-destructive/10 p-3 text-xs text-destructive">
                                    <div className="font-semibold">
                                        {lastError.title}
                                    </div>
                                    <div className="text-destructive/90">
                                        {lastError.message}
                                    </div>
                                    <div className="flex items-center gap-2 pt-1">
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant="outline"
                                            onClick={() => {
                                                clearError();
                                                if (lastPrompt) {
                                                    sendPrompt(
                                                        lastPrompt,
                                                        selectedFiles,
                                                        {
                                                            model: selectedModel,
                                                        }
                                                    );
                                                }
                                            }}
                                            className="h-7 rounded-lg border-destructive/40 text-xs text-destructive hover:bg-destructive/15"
                                        >
                                            Retry
                                        </Button>
                                    </div>
                                </div>
                            )}
                        </div>

                        {/* Staged File Upload Chips */}
                        {selectedFiles.length > 0 && (
                            <div className="flex flex-wrap gap-1.5 border-t border-neutral-800/80 bg-neutral-900/60 px-3 py-2">
                                {selectedFiles.map((file, i) => (
                                    <div
                                        key={i}
                                        className="flex items-center gap-1.5 rounded-lg border border-neutral-700/80 bg-neutral-800/90 px-2.5 py-1 font-mono text-[11px] text-neutral-200 shadow-xs"
                                    >
                                        {getFileIcon(file)}
                                        <span className="max-w-[140px] truncate">
                                            {file.name}
                                        </span>
                                        <button
                                            type="button"
                                            onClick={() => removeFile(i)}
                                            className="ml-0.5 cursor-pointer text-neutral-400 transition-colors hover:text-rose-400"
                                        >
                                            <X className="size-3" />
                                        </button>
                                    </div>
                                ))}
                            </div>
                        )}

                        {/* Hidden Native File Input */}
                        <input
                            ref={fileInputRef}
                            type="file"
                            multiple
                            accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.csv,.txt,.md,.json"
                            className="hidden"
                            onChange={(e) => {
                                if (e.target.files) {
                                    handleFilesAdded(e.target.files);
                                }
                            }}
                        />

                        {/* Composer Box Matching Screenshot */}
                        <div className="border-t border-neutral-800/80 bg-neutral-900/20 p-3">
                            <div className="rounded-2xl border border-neutral-800/90 bg-neutral-950/80 p-2.5 shadow-xl transition-all focus-within:border-neutral-700">
                                {/* Top Composer Bar: Model Selector / Configure API Key & Specialist Badge */}
                                <div className="mb-1 flex items-center justify-between pb-1.5">
                                    {availableModels.length > 0 ? (
                                        <Popover
                                            open={modelPopoverOpen}
                                            onOpenChange={setModelPopoverOpen}
                                        >
                                            <PopoverTrigger asChild>
                                                <button
                                                    type="button"
                                                    className="inline-flex cursor-pointer items-center gap-1.5 rounded-lg border border-amber-500/30 bg-amber-500/10 px-2.5 py-1 text-[11px] font-medium text-amber-400 transition-colors hover:bg-amber-500/20"
                                                    title="Select AI Model"
                                                >
                                                    <Cpu className="size-3 text-amber-400" />
                                                    <span className="max-w-[180px] truncate font-mono">
                                                        {activeModelName}
                                                    </span>
                                                    <ChevronDown className="size-3 text-amber-400/70" />
                                                </button>
                                            </PopoverTrigger>
                                            <PopoverContent
                                                className="w-[340px] rounded-2xl border border-neutral-800 bg-[#121215] p-3 shadow-2xl sm:w-[380px]"
                                                align="start"
                                            >
                                                <div className="space-y-2">
                                                    <div className="flex items-center justify-between border-b border-neutral-800 pb-1.5">
                                                        <span className="text-xs font-semibold text-white">
                                                            Select Model
                                                        </span>
                                                        <span className="font-mono text-[10.5px] text-muted-foreground">
                                                            {
                                                                availableModels.length
                                                            }{" "}
                                                            models
                                                        </span>
                                                    </div>
                                                    <ModelSelector
                                                        models={availableModels}
                                                        value={selectedModel}
                                                        onChange={
                                                            handleModelChange
                                                        }
                                                        variant="List"
                                                        searchable={true}
                                                    />
                                                </div>
                                            </PopoverContent>
                                        </Popover>
                                    ) : (
                                        <a
                                            href="/administrators/system-management/ai"
                                            className="inline-flex items-center gap-1.5 rounded-lg border border-amber-500/30 bg-amber-500/10 px-2.5 py-1 text-[11px] font-medium text-amber-400 transition-colors hover:bg-amber-500/20"
                                        >
                                            <Cpu className="size-3 text-amber-400" />
                                            <span>Configure API Key &rarr;</span>
                                        </a>
                                    )}

                                    <span className="font-mono text-xs text-muted-foreground">
                                        {activeMeta.badge}
                                    </span>
                                </div>

                                {/* Textarea Input */}
                                <textarea
                                    ref={textareaRef}
                                    value={input}
                                    onChange={(e) => setInput(e.target.value)}
                                    onKeyDown={handleKeyDown}
                                    placeholder={`Ask ${activeMeta.label} or drop files here...`}
                                    rows={2}
                                    disabled={isLoading}
                                    className="min-h-[58px] max-h-[140px] w-full resize-none border-0 bg-transparent px-1 py-1.5 text-sm leading-relaxed text-white placeholder:text-neutral-500 focus:outline-none focus:ring-0"
                                />

                                {/* Bottom Composer Bar: Attach + Auto-Approve + Enter to send + Send button */}
                                <div className="flex items-center justify-between pt-1">
                                    <div className="flex items-center gap-1.5">
                                        <button
                                            type="button"
                                            onClick={() =>
                                                fileInputRef.current?.click()
                                            }
                                            disabled={isLoading}
                                            className="inline-flex cursor-pointer items-center gap-1.5 rounded-lg px-2 py-1 text-xs text-neutral-400 transition-colors hover:bg-neutral-900 hover:text-white"
                                            title="Attach Excel, CSV, PDF, Word, or Images"
                                        >
                                            <Paperclip className="size-3.5" />
                                            <span>Attach</span>
                                        </button>

                                        <button
                                            type="button"
                                            onClick={() => {
                                                const next = !autoApprove;
                                                setAutoApprove(next);
                                                toast.info(
                                                    next
                                                        ? "Auto-approve enabled for sensitive actions."
                                                        : "Auto-approve disabled. Confirmation required."
                                                );
                                            }}
                                            className={cn(
                                                "inline-flex cursor-pointer items-center gap-1 rounded-lg px-2 py-1 text-[11px] font-medium transition-colors",
                                                autoApprove
                                                    ? "border border-emerald-500/40 bg-emerald-500/15 text-emerald-300"
                                                    : "border border-neutral-800 text-neutral-400 hover:border-neutral-700 hover:text-neutral-200"
                                            )}
                                            title={
                                                autoApprove
                                                    ? "Sensitive operations execute without approval prompt"
                                                    : "Click to auto-approve sensitive operations"
                                            }
                                        >
                                            <ShieldCheck
                                                className={cn(
                                                    "size-3",
                                                    autoApprove
                                                        ? "text-emerald-400"
                                                        : "text-neutral-400"
                                                )}
                                            />
                                            <span>
                                                {autoApprove
                                                    ? "Auto-Approve ON"
                                                    : "Auto-Approve"}
                                            </span>
                                        </button>
                                    </div>

                                    <div className="flex items-center gap-2.5">
                                        <span className="hidden text-[11px] text-neutral-500 sm:inline">
                                            Enter ↵ to send
                                        </span>

                                        {isLoading ? (
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="destructive"
                                                onClick={stop}
                                                className="h-8 gap-1.5 rounded-xl px-3.5 text-xs font-semibold shadow-md active:scale-95"
                                            >
                                                <Square className="size-3.5 fill-current" />
                                                <span>Stop</span>
                                            </Button>
                                        ) : (
                                            <button
                                                type="button"
                                                onClick={handleSend}
                                                disabled={
                                                    (!input.trim() &&
                                                        selectedFiles.length ===
                                                            0) ||
                                                    isLoading
                                                }
                                                className="inline-flex cursor-pointer items-center gap-1.5 rounded-xl bg-[#9333ea] px-4 py-1.5 text-xs font-semibold text-white shadow-md shadow-purple-500/20 transition-all hover:bg-[#a855f7] disabled:cursor-not-allowed disabled:opacity-40 active:scale-95"
                                            >
                                                <Send className="size-3.5" />
                                                <span>Send</span>
                                            </button>
                                        )}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                )}
            </div>
        </TooltipProvider>
    );
}

export default AdminAiFloatingWidget;
