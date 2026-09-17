import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Textarea } from "@/components/ui/textarea";
import { cn } from "@/lib/utils";
import { User } from "@/types/user";
import {
    BarChart3,
    Bot,
    Calculator,
    Check,
    FileText,
    GraduationCap,
    HelpCircle,
    Loader2,
    Maximize2,
    Minimize2,
    RotateCcw,
    Send,
    ShieldCheck,
    Sparkles,
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

    // KPI quick summary for administrator empty state
    const [kpis, setKpis] = React.useState<{ label: string; value: string | number; change: string }[]>([]);
    const [quickPrompts, setQuickPrompts] = React.useState<string[]>([
        "Generate a visual bar chart of student enrollment by department",
        "Audit student graduation clearance and identify pending holds",
        "Summarize tuition fee collections and outstanding receivables",
        "Draft an official institutional memo regarding enrollment guidelines in PDF format",
    ]);

    const scrollAreaRef = React.useRef<HTMLDivElement>(null);

    const {
        messages,
        input,
        setInput,
        isLoading,
        sendPrompt,
        submitDecision,
        clearChat,
    } = useAiChat({
        agent: selectedAgent,
    });

    // Auto-scroll on new message chunks
    React.useEffect(() => {
        if (scrollAreaRef.current) {
            scrollAreaRef.current.scrollTop = scrollAreaRef.current.scrollHeight;
        }
    }, [messages, isLoading]);

    // Fetch quick KPIs once when opened
    React.useEffect(() => {
        if (isOpen && kpis.length === 0) {
            fetch("/administrators/ai/analytics-summary", {
                headers: { "X-Requested-With": "XMLHttpRequest" },
            })
                .then((res) => res.json())
                .then((data) => {
                    if (data.kpis) setKpis(data.kpis);
                    if (data.quick_prompts) setQuickPrompts(data.quick_prompts);
                })
                .catch(() => {
                    // Silently keep default quick prompts on network error
                });
        }
    }, [isOpen, kpis.length]);

    const activeMeta = ADMIN_AGENTS.find((a) => a.key === selectedAgent) || ADMIN_AGENTS[0];
    const ActiveIcon = activeMeta.icon;

    const handleKeyDown = (e: React.KeyboardEvent<HTMLTextAreaElement>) => {
        if (e.key === "Enter" && !e.shiftKey) {
            e.preventDefault();
            if (input.trim() && !isLoading) {
                sendPrompt(input);
            }
        }
    };

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
                            ? "w-[95vw] sm:w-[680px] h-[85vh]"
                            : "w-[92vw] sm:w-[460px] md:w-[500px] h-[640px] max-h-[85vh]"
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
                                        Analytics, charts, and downloadable document generation
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

                        {/* Specialist Agent Pills */}
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
                                <div className="text-center space-y-2 p-2">
                                    <div className="inline-flex p-2.5 rounded-2xl bg-primary/10 border border-primary/20 text-primary mb-1">
                                        <ActiveIcon className="size-7" />
                                    </div>
                                    <h4 className="text-sm font-semibold text-foreground">
                                        {activeMeta.label}
                                    </h4>
                                    <p className="text-xs text-muted-foreground max-w-sm mx-auto">
                                        {activeMeta.description} Ask for data summaries, interactive charts, or official PDF/CSV downloads.
                                    </p>
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

                                {/* Quick Action Prompts */}
                                <div className="space-y-1.5">
                                    <span className="text-[10px] uppercase font-semibold text-muted-foreground tracking-wider block px-1">
                                        Suggested Administrative Actions
                                    </span>
                                    <div className="grid grid-cols-1 gap-1.5">
                                        {quickPrompts.map((prompt, i) => (
                                            <button
                                                key={i}
                                                type="button"
                                                onClick={() => sendPrompt(prompt)}
                                                disabled={isLoading}
                                                className="text-left text-xs p-2 rounded-lg border border-border/70 bg-card hover:bg-accent hover:text-accent-foreground transition-colors line-clamp-1"
                                            >
                                                {prompt}
                                            </button>
                                        ))}
                                    </div>
                                </div>
                            </div>
                        ) : (
                            messages.map((msg) => {
                                const isUser = msg.role === "user";
                                return (
                                    <div
                                        key={msg.id}
                                        className={cn("flex gap-2.5 text-xs leading-relaxed", isUser ? "justify-end" : "justify-start")}
                                    >
                                        {!isUser && (
                                            <div className="size-6.5 rounded-full border bg-primary/10 border-primary/20 text-primary flex items-center justify-center shrink-0 mt-0.5">
                                                <Bot className="size-3.5" />
                                            </div>
                                        )}

                                        <div
                                            className={cn(
                                                "max-w-[88%] rounded-2xl px-3.5 py-2.5 space-y-2",
                                                isUser
                                                    ? "bg-primary text-primary-foreground rounded-tr-sm"
                                                    : "bg-muted/40 border border-border/70 rounded-tl-sm text-foreground"
                                            )}
                                        >
                                            {isUser ? (
                                                <p className="whitespace-pre-wrap">{msg.content}</p>
                                            ) : (
                                                <ChatMessageFormatter content={msg.content} />
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
                                        </div>

                                        {isUser && (
                                            <div className="size-6.5 rounded-full bg-primary/10 border border-primary/20 flex items-center justify-center shrink-0 mt-0.5">
                                                <UserIcon className="size-3.5 text-primary" />
                                            </div>
                                        )}
                                    </div>
                                );
                            })
                        )}

                        {isLoading && (
                            <div className="flex items-center gap-2 text-xs text-muted-foreground pt-1 px-1">
                                <Loader2 className="size-3.5 animate-spin text-primary" />
                                <span>{activeMeta.label} is evaluating analytics & preparing response...</span>
                            </div>
                        )}
                    </div>

                    {/* Input Footer */}
                    <div className="p-3 border-t bg-background/95 backdrop-blur space-y-2">
                        <div className="relative">
                            <Textarea
                                placeholder={`Ask ${activeMeta.label}... (Enter to send, Shift+Enter for newline)`}
                                value={input}
                                onChange={(e) => setInput(e.target.value)}
                                onKeyDown={handleKeyDown}
                                disabled={isLoading}
                                rows={2}
                                className="text-xs resize-none pr-10 font-normal"
                            />
                            <Button
                                type="button"
                                size="icon"
                                onClick={() => sendPrompt(input)}
                                disabled={!input.trim() || isLoading}
                                className="absolute right-2 bottom-2 size-7"
                            >
                                {isLoading ? <Loader2 className="size-3.5 animate-spin" /> : <Send className="size-3.5" />}
                            </Button>
                        </div>

                        <div className="flex items-center justify-between text-[10px] text-muted-foreground px-1">
                            <span>Supports interactive charts, tables & PDF downloads</span>
                            <span>Shift + Enter for new line</span>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}
