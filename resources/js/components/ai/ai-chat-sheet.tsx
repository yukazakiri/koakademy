import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from "@/components/ui/sheet";
import { Textarea } from "@/components/ui/textarea";
import { cn } from "@/lib/utils";
import {
    AlertCircle,
    Bot,
    Calculator,
    CheckCircle2,
    GraduationCap,
    HelpCircle,
    Loader2,
    RotateCcw,
    Send,
    ShieldCheck,
    Sparkles,
    User,
} from "lucide-react";
import * as React from "react";

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

    const activeMeta = AGENT_METAS[selectedAgent];
    const ActiveIcon = activeMeta.icon;

    // Auto-scroll on new message chunks
    React.useEffect(() => {
        if (scrollAreaRef.current) {
            scrollAreaRef.current.scrollTop = scrollAreaRef.current.scrollHeight;
        }
    }, [messages, isLoading]);

    const handleKeyDown = (e: React.KeyboardEvent<HTMLTextAreaElement>) => {
        if (e.key === "Enter" && !e.shiftKey) {
            e.preventDefault();
            if (input.trim() && !isLoading) {
                sendPrompt(input);
            }
        }
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
                                    {activeMeta.description} Powered by multi-provider intelligence with strict human approval gates.
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

                    {isLoading && (
                        <div className="flex items-center gap-2 text-xs text-muted-foreground pt-1">
                            <Loader2 className="size-3.5 animate-spin text-primary" />
                            <span>{activeMeta.name} is thinking & evaluating tools...</span>
                        </div>
                    )}
                </div>

                {/* Input Area */}
                <div className="p-3 border-t bg-background/95 backdrop-blur space-y-2">
                    <div className="relative">
                        <Textarea
                            placeholder={`Ask ${activeMeta.name}... (Enter to send, Shift+Enter for newline)`}
                            value={input}
                            onChange={(e) => setInput(e.target.value)}
                            onKeyDown={handleKeyDown}
                            disabled={isLoading}
                            rows={2}
                            className="text-xs resize-none pr-12 font-normal"
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

                    <div className="flex items-center justify-between text-[11px] text-muted-foreground px-1">
                        <span>Protected by institutional governance</span>
                        <span>Shift + Enter for new line</span>
                    </div>
                </div>
            </SheetContent>
        </Sheet>
    );
}
