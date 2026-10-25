"use client";

import {
    analyticsSummary,
    chat as adminAiChat,
    index as adminAiIndex,
} from "@/actions/App/Http/Controllers/AdministratorAiController";

import { ThinkingShimmer } from "@/components/agents/loading-states/thinking-shimmer";
import { Badge } from "@/components/reui/badge";
import {
    Frame,
    FrameFooter,
    FrameHeader,
    FramePanel,
} from "@/components/reui/frame";
import { ModelOption, ModelSelector } from "@/components/spectrumui";
import { Bubble, BubbleContent, BubbleGroup } from "@/components/ui/bubble";
import { Button } from "@/components/ui/button";
import {
    InputGroup,
    InputGroupAddon,
    InputGroupButton,
    InputGroupTextarea,
} from "@/components/ui/input-group";
import {
    Message,
    MessageAvatar,
    MessageContent,
    MessageFooter,
    MessageGroup,
} from "@/components/ui/message";
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from "@/components/ui/popover";
import { TooltipProvider } from "@/components/ui/tooltip";
import { cn } from "@/lib/utils";
import type { FeatureFlags } from "@/types/feature-flags";
import type { User } from "@/types/user";
import { router, usePage } from "@inertiajs/react";
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
    const userId = user?.id ? String(user.id) : "guest";
    const preferredModelKey = `koakademy_ai_preferred_model_${userId}`;
    const widgetOpenKey = `koakademy_ai_widget_open_${userId}`;
    const widgetExpandedKey = `koakademy_ai_widget_expanded_${userId}`;
    const widgetAgentKey = `koakademy_ai_widget_agent_${userId}`;
    const widgetPersistenceKey = `koakademy_ai_widget_${userId}`;
    const page = usePage<{ featureFlags?: FeatureFlags }>();
    const { featureFlags } = page.props;
    const pageUrl = page.url;

    const enabledAgents = React.useMemo(() => {
        const aiFlags = featureFlags?.ai;

        if (!aiFlags) {
            return ADMIN_AGENTS;
        }

        if (aiFlags.any === false) {
            return [];
        }

        return ADMIN_AGENTS.filter(
            (agent) => aiFlags.agents?.[agent.key] !== false,
        );
    }, [featureFlags?.ai]);

    const [isOpen, setIsOpen] = React.useState<boolean>(() => {
        if (typeof window !== "undefined") {
            try {
                return sessionStorage.getItem(widgetOpenKey) === "true";
            } catch {
                return false;
            }
        }
        return false;
    });
    const [isExpanded, setIsExpanded] = React.useState<boolean>(() => {
        if (typeof window !== "undefined") {
            try {
                return sessionStorage.getItem(widgetExpandedKey) === "true";
            } catch {
                return false;
            }
        }
        return false;
    });
    const [selectedAgent, setSelectedAgent] = React.useState<AgentRoleKey>(() => {
        if (typeof window !== "undefined") {
            try {
                const saved = sessionStorage.getItem(widgetAgentKey) as AgentRoleKey;
                if (saved && enabledAgents.some((a) => a.key === saved)) {
                    return saved;
                }
            } catch {
                // Ignore storage errors
            }
        }
        return enabledAgents[0]?.key ?? "admin_executive";
    });

    const handleOpenChange = React.useCallback(
        (open: boolean) => {
            setIsOpen(open);
            if (typeof window !== "undefined") {
                try {
                    sessionStorage.setItem(widgetOpenKey, String(open));
                } catch {
                    // Ignore storage errors
                }
            }
        },
        [widgetOpenKey],
    );

    const handleExpandedChange = React.useCallback(
        (expanded: boolean) => {
            setIsExpanded(expanded);
            if (typeof window !== "undefined") {
                try {
                    sessionStorage.setItem(widgetExpandedKey, String(expanded));
                } catch {
                    // Ignore storage errors
                }
            }
        },
        [widgetExpandedKey],
    );

    const handleAgentChange = React.useCallback(
        (agentKey: AgentRoleKey) => {
            setSelectedAgent(agentKey);
            if (typeof window !== "undefined") {
                try {
                    sessionStorage.setItem(widgetAgentKey, agentKey);
                } catch {
                    // Ignore storage errors
                }
            }
        },
        [widgetAgentKey],
    );

    React.useEffect(() => {
        if (
            enabledAgents.length > 0 &&
            !enabledAgents.some((agent) => agent.key === selectedAgent)
        ) {
            handleAgentChange(enabledAgents[0].key);
        }
    }, [enabledAgents, handleAgentChange, selectedAgent]);

    // Dynamic model options from configured providers
    const [availableModels, setAvailableModels] = React.useState<ModelOption[]>([]);
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
        sendPrompt,
        retryLastRequest,
        approvalDecisions,
        approvalsLocked,
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
        context: { page: pageUrl },
        endpoint: adminAiChat.url(),
        persistenceKey: widgetPersistenceKey,
    });

    // Auto-scroll on new message chunks
    React.useEffect(() => {
        if (scrollAreaRef.current) {
            scrollAreaRef.current.scrollTop = scrollAreaRef.current.scrollHeight;
        }
    }, [messages, isLoading, lastError]);

    // Handle user model selection with localStorage persistence
    const handleModelChange = React.useCallback(
        (id: string) => {
            setSelectedModel(id);
            if (typeof window !== "undefined") {
                try {
                    localStorage.setItem(preferredModelKey, id);
                } catch {
                    // Ignore storage quotas
                }
            }
            setModelPopoverOpen(false);
            toast.success(`Active model: ${id}`);
        },
        [preferredModelKey],
    );

    // Fetch available models once when opened
    React.useEffect(() => {
        if (isOpen && availableModels.length === 0) {
            fetch(analyticsSummary.url(), {
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
                            }),
                        );
                        setAvailableModels(mapped);

                        const saved =
                            typeof window !== "undefined"
                                ? localStorage.getItem(preferredModelKey)
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
                                m.badge?.includes("Default"),
                            );
                            const byPrimary =
                                typeof data.primary_provider === "string"
                                    ? mapped.find(
                                          (m) =>
                                              m.provider ===
                                              data.primary_provider,
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
    }, [isOpen, availableModels.length, preferredModelKey, selectedModel]);

    const activeMeta =
        enabledAgents.find((a) => a.key === selectedAgent) ||
        enabledAgents[0] ||
        ADMIN_AGENTS[0];
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
            `Attached ${fileList.length} ${fileList.length === 1 ? "file" : "files"}.`,
        );
    };

    const removeFile = (index: number) => {
        setSelectedFiles((prev) => prev.filter((_, i) => i !== index));
    };

    const selectedModelOptions = React.useCallback(
        () => ({
            model: selectedModel.includes(":")
                ? selectedModel.split(":").slice(1).join(":")
                : selectedModel || undefined,
            provider: availableModels.find((m) => m.id === selectedModel)?.provider,
        }),
        [availableModels, selectedModel],
    );

    const handleSend = () => {
        if ((!input.trim() && selectedFiles.length === 0) || isLoading) return;
        sendPrompt(input, selectedFiles, selectedModelOptions());
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
        sendPrompt(prompt, [], selectedModelOptions());
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
        if (!found) return selectedModel || "Auto Best";
        const cleanName = found.name.replace(
            /^(?:no-think\/|dva\/|oc\/|cx\/|cxa\/|agy\/|zed-hosted\/)+/,
            "",
        );
        return cleanName.length > 20
            ? cleanName.slice(0, 18) + "…"
            : cleanName;
    }, [availableModels, selectedModel]);

    if (enabledAgents.length === 0) {
        return null;
    }

    return (
        <TooltipProvider>
            <div className="fixed right-6 bottom-6 z-50 select-none">
                {/* 1. Closed State: Floating Action Button */}
                {!isOpen && (
                    <button
                        type="button"
                        onClick={() => handleOpenChange(true)}
                        className="group relative flex size-14 cursor-pointer items-center justify-center rounded-2xl border border-border bg-card text-card-foreground shadow-2xl ring-4 ring-primary/20 transition-all duration-200 hover:scale-105 hover:ring-primary/40 active:scale-95 overflow-hidden"
                        title="Open Administrative AI Copilot"
                    >
                        <AiCircleLogo
                            size={42}
                            useOrb={true}
                            className="pointer-events-none transition-transform duration-300 group-hover:scale-110"
                        />

                        {/* Online status indicator */}
                        <span className="absolute top-1.5 right-1.5 flex size-2.5">
                            <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75" />
                            <span className="relative inline-flex size-2.5 rounded-full border-2 border-card bg-emerald-500 shadow-xs" />
                        </span>
                    </button>
                )}

                {/* 2. Open State: ReUI Frame-based Floating Chat Modal */}
                {isOpen && (
                    <Frame
                        variant="default"
                        dense={true}
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
                            "relative flex flex-col overflow-hidden rounded-3xl border border-border/80 bg-card/95 text-card-foreground shadow-2xl backdrop-blur-2xl transition-all duration-200 animate-in fade-in zoom-in-95",
                            isExpanded
                                ? "h-[88vh] w-[95vw] sm:w-[760px]"
                                : "h-[680px] max-h-[88vh] w-[92vw] sm:w-[500px] md:w-[540px]",
                        )}
                    >
                        {/* Drag and Drop Overlay */}
                        {isDragging && (
                            <div className="absolute inset-0 z-50 flex flex-col items-center justify-center gap-3 bg-background/90 p-6 backdrop-blur-md">
                                <div className="flex size-14 items-center justify-center rounded-2xl border-2 border-dashed border-primary bg-primary/10">
                                    <UploadCloud className="size-8 text-primary animate-bounce" />
                                </div>
                                <p className="text-sm font-semibold text-foreground">
                                    Drop files to attach
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    Excel (.xlsx, .csv), PDF, Word, Images
                                </p>
                            </div>
                        )}

                        <FramePanel fit={false} className="flex h-full flex-col p-0 border-0 bg-transparent rounded-none shadow-none">
                            {/* ReUI Frame Header */}
                            <FrameHeader className="border-b border-border/70 bg-muted/30 px-4 pt-3.5 pb-2.5">
                                <div className="flex items-center justify-between">
                                    <div className="flex items-center gap-2.5">
                                        <div className="flex size-9 items-center justify-center rounded-xl border border-border bg-muted/60 overflow-hidden shadow-xs">
                                            <AiCircleLogo size={32} useOrb={true} className="pointer-events-none" />
                                        </div>
                                        <div>
                                            <div className="flex items-center gap-2">
                                                <h3 className="text-sm font-bold tracking-tight text-foreground">
                                                    Administrative Copilot
                                                </h3>
                                                <Badge
                                                    variant="success-light"
                                                    size="xs"
                                                    radius="full"
                                                    className="gap-1 font-mono text-[10px]"
                                                >
                                                    <span className="size-1.5 rounded-full bg-emerald-500 animate-pulse" />
                                                    Live
                                                </Badge>
                                            </div>
                                            <p className="mt-0.5 line-clamp-1 text-[11px] text-muted-foreground">
                                                Campus analytics, document drafting & academic audits
                                            </p>
                                        </div>
                                    </div>

                                    {/* Header Action Controls */}
                                    <div className="flex items-center gap-0.5 text-muted-foreground">
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            className="size-7.5 rounded-lg text-muted-foreground hover:bg-muted hover:text-foreground"
                                            onClick={() => {
                                                clearChat();
                                                setSelectedFiles([]);
                                                toast.success("New conversation started. Previous transcript cleared.");
                                            }}
                                            title="New chat / Clear transcript"
                                        >
                                            <RotateCcw className="size-3.5" />
                                        </Button>

                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            className="size-7.5 rounded-lg text-muted-foreground hover:bg-muted hover:text-foreground"
                                            onClick={() => {
                                                handleOpenChange(false);
                                                const targetUrl = adminAiIndex.url(
                                                    conversationId
                                                        ? { query: { conversation: conversationId } }
                                                        : undefined,
                                                );
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
                                            className="hidden size-7.5 rounded-lg text-muted-foreground hover:bg-muted hover:text-foreground sm:inline-flex"
                                            onClick={() => handleExpandedChange(!isExpanded)}
                                            title={isExpanded ? "Collapse view" : "Expand view"}
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
                                            className="size-7.5 rounded-lg text-muted-foreground hover:bg-muted hover:text-foreground"
                                            onClick={() => handleOpenChange(false)}
                                            title="Close widget"
                                        >
                                            <X className="size-4" />
                                        </Button>
                                    </div>
                                </div>

                                {/* Specialist Agent Tabs */}
                                <div className="no-scrollbar flex items-center gap-1.5 overflow-x-auto pt-2.5 pb-0.5">
                                    {enabledAgents.map((agent) => {
                                        const isSelected = selectedAgent === agent.key;
                                        const Icon = agent.icon;
                                        return (
                                            <button
                                                key={agent.key}
                                                type="button"
                                                onClick={() => handleAgentChange(agent.key)}
                                                className={cn(
                                                    "flex shrink-0 cursor-pointer items-center gap-1.5 rounded-xl px-3 py-1.5 text-xs font-medium transition-colors",
                                                    isSelected
                                                        ? "bg-primary text-primary-foreground font-semibold shadow-xs"
                                                        : "border border-border/70 bg-muted/40 text-muted-foreground hover:border-border hover:bg-muted/70 hover:text-foreground",
                                                )}
                                            >
                                                <Icon className="size-3.5" />
                                                <span>{agent.label}</span>
                                            </button>
                                        );
                                    })}
                                </div>
                            </FrameHeader>

                            {/* Chat Messages Body */}
                            <div
                                ref={scrollAreaRef}
                                className="flex-1 space-y-4 overflow-y-auto p-4"
                            >
                                {messages.length === 0 ? (
                                    /* ReUI Empty State */
                                    <div className="flex h-full flex-col items-center justify-center px-2 py-6 text-center">
                                        <div className="mx-auto mb-3 flex size-12 items-center justify-center rounded-2xl border border-border bg-muted/50 text-foreground shadow-xs">
                                            <ActiveIcon className="size-6 text-foreground" />
                                        </div>

                                        <h4 className="text-base font-bold tracking-tight text-foreground sm:text-lg">
                                            {activeMeta.label}
                                        </h4>

                                        <p className="mt-1.5 mb-6 max-w-sm text-xs leading-relaxed text-muted-foreground sm:max-w-md">
                                            {activeMeta.description}
                                        </p>

                                        {/* Suggestion Pills */}
                                        <div className="w-full max-w-md space-y-2">
                                            {activeMeta.suggestions.map((suggestion, idx) => (
                                                <button
                                                    key={idx}
                                                    type="button"
                                                    onClick={() => handleSuggestionClick(suggestion)}
                                                    className="w-full cursor-pointer rounded-full border border-border/80 bg-muted/30 px-4 py-2 text-center text-xs font-medium text-foreground/80 shadow-xs transition-colors hover:border-border hover:bg-muted hover:text-foreground active:scale-[0.99]"
                                                >
                                                    {suggestion}
                                                </button>
                                            ))}
                                        </div>
                                    </div>
                                ) : (
                                    <MessageGroup className="gap-4">
                                        {messages.map((msg) => {
                                            const isUser = msg.role === "user";
                                            return (
                                                <Message
                                                    key={msg.id}
                                                    align={isUser ? "end" : "start"}
                                                    className="gap-2.5"
                                                >
                                                    {/* Avatar */}
                                                    <MessageAvatar
                                                        className={cn(
                                                            "size-7 rounded-xl border text-xs overflow-hidden flex items-center justify-center shrink-0",
                                                            isUser
                                                                ? "border-primary/30 bg-primary/10 text-primary"
                                                                : "border-border bg-card text-foreground",
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
                                                            "max-w-[85%] min-w-0",
                                                            isUser ? "items-end" : "items-start w-full",
                                                        )}
                                                    >
                                                        {/* Attached Files on Message */}
                                                        {msg.attachments && msg.attachments.length > 0 && (
                                                            <div
                                                                className={cn(
                                                                    "flex flex-wrap gap-1.5 pb-1",
                                                                    isUser ? "justify-end" : "justify-start",
                                                                )}
                                                            >
                                                                {msg.attachments.map((att, i) => (
                                                                    <div
                                                                        key={i}
                                                                        className="flex items-center gap-1.5 rounded-lg border border-border bg-muted/60 px-2.5 py-1 font-mono text-[11px] text-foreground/90 shadow-xs"
                                                                    >
                                                                        <Paperclip className="size-3 text-muted-foreground" />
                                                                        <span className="max-w-[140px] truncate">
                                                                            {att.name}
                                                                        </span>
                                                                        <span className="text-[10px] text-muted-foreground">
                                                                            ({Math.round(att.size / 1024)} KB)
                                                                        </span>
                                                                    </div>
                                                                ))}
                                                            </div>
                                                        )}

                                                        {/* Message Bubble */}
                                                        <BubbleGroup className="w-full min-w-0">
                                                            <Bubble
                                                                variant={isUser ? "default" : "ghost"}
                                                                align={isUser ? "end" : "start"}
                                                                className={cn(isUser ? "" : "w-full max-w-full p-0")}
                                                            >
                                                                <BubbleContent className={isUser ? "" : "p-0 border-0 bg-transparent w-full min-w-0 text-foreground"}>
                                                                    {isUser ? (
                                                                        <p className="whitespace-pre-wrap text-sm leading-relaxed font-medium">
                                                                            {msg.content}
                                                                        </p>
                                                                    ) : (
                                                                        <div className="w-full space-y-2.5 min-w-0">
                                                                            <ChatMessageFormatter
                                                                                content={msg.content}
                                                                                reasoning={msg.reasoning}
                                                                                toolCalls={msg.toolCalls}
                                                                                sources={msg.sources}
                                                                                isStreaming={
                                                                                    isLoading &&
                                                                                    msg.id === messages.at(-1)?.id
                                                                                }
                                                                            />

                                                                            {/* Approvals Gate Panel */}
                                                                            {msg.pendingApprovals && msg.pendingApprovals.length > 0 && (
                                                                                <div className="space-y-2 my-2.5">
                                                                                    {msg.pendingApprovals.length > 1 && (
                                                                                        <div className="flex items-center justify-between p-2.5 rounded-xl border border-warning/30 bg-warning/10 text-xs">
                                                                                            <span className="font-semibold text-warning-foreground flex items-center gap-1.5">
                                                                                                <ShieldCheck className="size-4 text-warning" />
                                                                                                {msg.pendingApprovals.length} Actions Awaiting Confirmation
                                                                                            </span>
                                                                                            <div className="flex items-center gap-1.5">
                                                                                                <Button
                                                                                                    size="sm"
                                                                                                    variant="outline"
                                                                                                    className="h-7 text-xs border-warning/40 text-destructive hover:bg-destructive/10"
                                                                                                    disabled={isLoading || approvalsLocked}
                                                                                                    onClick={() => submitAllDecisions("reject")}
                                                                                                >
                                                                                                    Reject All
                                                                                                </Button>
                                                                                                <Button
                                                                                                    size="sm"
                                                                                                    className="h-7 text-xs bg-success hover:bg-success/90 text-white font-medium"
                                                                                                    disabled={isLoading || approvalsLocked}
                                                                                                    onClick={() => submitAllDecisions("approve")}
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
                                                                                            onApproveAll={
                                                                                                msg.pendingApprovals!.length > 1
                                                                                                    ? () => submitAllDecisions("approve")
                                                                                                    : undefined
                                                                                            }
                                                                                            disabled={isLoading || approvalsLocked}
                                                                                            decision={approvalDecisions[approval.id]?.action}
                                                                                        />
                                                                                    ))}
                                                                                </div>
                                                                            )}
                                                                        </div>
                                                                    )}
                                                                </BubbleContent>
                                                            </Bubble>
                                                        </BubbleGroup>

                                                        {/* Message Footer Actions */}
                                                        <MessageFooter className="gap-1.5 px-0.5 text-[11px] text-muted-foreground">
                                                            {isUser ? (
                                                                <UserMessageActions
                                                                    content={msg.content}
                                                                    disabled={isLoading}
                                                                    onResend={() =>
                                                                        resendUserMessage(
                                                                            msg.id,
                                                                            selectedModelOptions(),
                                                                        )
                                                                    }
                                                                />
                                                            ) : (
                                                                !(
                                                                    isLoading &&
                                                                    msg.id === messages.at(-1)?.id
                                                                ) && (
                                                                    <AssistantMessageActions
                                                                        content={msg.content}
                                                                        disabled={isLoading}
                                                                        onRegenerate={() =>
                                                                            regenerateAssistant(
                                                                                msg.id,
                                                                                selectedModelOptions(),
                                                                            )
                                                                        }
                                                                    />
                                                                )
                                                            )}
                                                        </MessageFooter>
                                                    </MessageContent>
                                                </Message>
                                            );
                                        })}
                                    </MessageGroup>
                                )}

                                {/* Live Shimmer Indicator when loading without initial tokens */}
                                {isLoading && messages.at(-1)?.role === "user" && (
                                    <div className="flex items-center gap-2.5 px-1 py-1 text-xs text-muted-foreground">
                                        <div className="flex size-6 items-center justify-center rounded-lg border border-primary/30 bg-primary/10 text-primary">
                                            <AiCircleLogo className="size-3.5 animate-spin" />
                                        </div>
                                        <ThinkingShimmer duration={1.6}>
                                            {activeMeta.label} is analyzing campus records…
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
                                                onClick={retryLastRequest}
                                                disabled={isLoading || lastError.retryable === false}
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
                                <div className="flex flex-wrap gap-1.5 border-t border-border bg-muted/40 px-3 py-2">
                                    {selectedFiles.map((file, i) => (
                                        <div
                                            key={i}
                                            className="flex items-center gap-1.5 rounded-lg border border-border bg-card px-2.5 py-1 font-mono text-[11px] text-foreground shadow-xs"
                                        >
                                            {getFileIcon(file)}
                                            <span className="max-w-[140px] truncate">
                                                {file.name}
                                            </span>
                                            <button
                                                type="button"
                                                onClick={() => removeFile(i)}
                                                className="ml-0.5 cursor-pointer text-muted-foreground transition-colors hover:text-destructive"
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

                            {/* ReUI Frame Footer & InputGroup Composer */}
                            <FrameFooter className="p-3 border-t border-border/70 bg-muted/20">
                                <InputGroup className="flex-col h-auto rounded-2xl border-input bg-background/90 p-1.5 shadow-sm transition-colors focus-within:border-ring focus-within:ring-2 focus-within:ring-ring/20">
                                    {/* Top Composer Bar: Model Selector Popover & Agent Badge */}
                                    <InputGroupAddon align="block-start" className="flex items-center justify-between border-b border-border/50 px-2 pt-1 pb-1.5">
                                        {availableModels.length > 0 ? (
                                            <Popover
                                                open={modelPopoverOpen}
                                                onOpenChange={setModelPopoverOpen}
                                            >
                                                <PopoverTrigger asChild>
                                                    <InputGroupButton
                                                        size="xs"
                                                        variant="ghost"
                                                        className="h-6 gap-1.5 border border-border/70 bg-muted/40 text-foreground hover:bg-muted font-normal"
                                                        title="Select AI Model"
                                                    >
                                                        <Cpu className="size-3 text-warning" />
                                                        <span className="max-w-[160px] truncate font-mono text-[11px]">
                                                            {activeModelName}
                                                        </span>
                                                        <ChevronDown className="size-3 text-muted-foreground" />
                                                    </InputGroupButton>
                                                </PopoverTrigger>
                                                <PopoverContent
                                                    className="w-[340px] rounded-2xl border border-border bg-popover p-3 shadow-2xl sm:w-[380px]"
                                                    align="start"
                                                >
                                                    <div className="space-y-2">
                                                        <div className="flex items-center justify-between border-b border-border pb-1.5">
                                                            <span className="text-xs font-semibold text-foreground">
                                                                Select Model
                                                            </span>
                                                            <span className="font-mono text-[10.5px] text-muted-foreground">
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
                                                className="inline-flex items-center gap-1.5 rounded-lg border border-border bg-muted/40 px-2 py-0.5 text-[11px] font-medium text-foreground transition-colors hover:bg-muted"
                                            >
                                                <Cpu className="size-3 text-warning" />
                                                <span>Configure API Key &rarr;</span>
                                            </a>
                                        )}

                                        <Badge variant="outline" size="xs" radius="full" className="font-mono text-[10px] text-muted-foreground">
                                            {activeMeta.badge}
                                        </Badge>
                                    </InputGroupAddon>

                                    {/* Textarea Input Control */}
                                    <InputGroupTextarea
                                        ref={textareaRef}
                                        value={input}
                                        onChange={(e) => setInput(e.target.value)}
                                        onKeyDown={handleKeyDown}
                                        placeholder={`Ask ${activeMeta.label} or drop files here...`}
                                        rows={2}
                                        disabled={isLoading}
                                        className="min-h-[58px] max-h-[140px] px-2 py-1.5 text-sm leading-relaxed text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-0"
                                    />

                                    {/* Bottom Composer Bar: Attach + Auto-Approve + Enter to send + Send button */}
                                    <InputGroupAddon align="block-end" className="flex items-center justify-between px-2 pt-1.5 pb-1">
                                        <div className="flex items-center gap-1.5">
                                            <InputGroupButton
                                                type="button"
                                                size="xs"
                                                variant="ghost"
                                                onClick={() => fileInputRef.current?.click()}
                                                disabled={isLoading}
                                                className="gap-1 text-muted-foreground hover:text-foreground hover:bg-muted"
                                                title="Attach Excel, CSV, PDF, Word, or Images"
                                            >
                                                <Paperclip className="size-3.5" />
                                                <span className="text-xs">Attach</span>
                                            </InputGroupButton>

                                            <InputGroupButton
                                                type="button"
                                                size="xs"
                                                variant={autoApprove ? "outline" : "ghost"}
                                                onClick={() => {
                                                    const next = !autoApprove;
                                                    setAutoApprove(next);
                                                    toast.info(
                                                        next
                                                            ? "Auto-approve enabled for sensitive actions."
                                                            : "Auto-approve disabled. Confirmation required.",
                                                    );
                                                }}
                                                className={cn(
                                                    "gap-1 text-xs",
                                                    autoApprove
                                                        ? "border-success/40 bg-success/10 text-success hover:bg-success/15"
                                                        : "text-muted-foreground hover:text-foreground hover:bg-muted",
                                                )}
                                                title={
                                                    autoApprove
                                                        ? "Sensitive operations execute without approval prompt"
                                                        : "Click to auto-approve sensitive operations"
                                                }
                                            >
                                                <ShieldCheck
                                                    className={cn(
                                                        "size-3.5",
                                                        autoApprove ? "text-success" : "text-muted-foreground",
                                                    )}
                                                />
                                                <span>
                                                    {autoApprove ? "Auto-Approve ON" : "Auto-Approve"}
                                                </span>
                                            </InputGroupButton>
                                        </div>

                                        <div className="flex items-center gap-2.5">
                                            <span className="hidden text-[11px] text-muted-foreground sm:inline">
                                                Enter ↵ to send
                                            </span>

                                            {isLoading ? (
                                                <InputGroupButton
                                                    type="button"
                                                    size="xs"
                                                    variant="destructive"
                                                    onClick={stop}
                                                    className="h-7 gap-1 px-3 text-xs font-semibold shadow-xs active:scale-95"
                                                >
                                                    <Square className="size-3 fill-current" />
                                                    <span>Stop</span>
                                                </InputGroupButton>
                                            ) : (
                                                <InputGroupButton
                                                    type="button"
                                                    size="xs"
                                                    variant="default"
                                                    onClick={handleSend}
                                                    disabled={
                                                        (!input.trim() && selectedFiles.length === 0) || isLoading
                                                    }
                                                    className="h-7 gap-1 px-3 text-xs font-semibold shadow-xs disabled:cursor-not-allowed disabled:opacity-40 active:scale-95"
                                                >
                                                    <Send className="size-3" />
                                                    <span>Send</span>
                                                </InputGroupButton>
                                            )}
                                        </div>
                                    </InputGroupAddon>
                                </InputGroup>
                            </FrameFooter>
                        </FramePanel>
                    </Frame>
                )}
            </div>
        </TooltipProvider>
    );
}

export default AdminAiFloatingWidget;