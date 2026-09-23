import {
    BarChart3,
    Calculator,
    GraduationCap,
    HelpCircle,
    ReceiptText,
    ShieldCheck,
    Sparkles,
} from "lucide-react";
import * as React from "react";
import type { AgentRoleKey } from "./use-ai-chat";

export interface AdminAgentOption {
    key: AgentRoleKey;
    label: string;
    description: string;
    icon: React.ElementType;
    badge: string;
}

export const ADMIN_AGENTS: AdminAgentOption[] = [
    {
        key: "admin_executive",
        label: "Executive & Analytics",
        description: "Campus analytics, visual charts, and institutional intelligence.",
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

export interface PromptSuggestion {
    title: string;
    description: string;
    prompt: string;
    agent: AgentRoleKey;
    icon: React.ElementType;
}

export const DEFAULT_PROMPT_SUGGESTIONS: PromptSuggestion[] = [
    {
        title: "Campus Demographics",
        description: "Break down current student population by gender and program",
        prompt: "Generate an executive breakdown and a ring chart visualizing our current student population by gender and department.",
        agent: "admin_executive",
        icon: BarChart3,
    },
    {
        title: "Graduation Clearance",
        description: "Audit un-cleared students and identify outstanding holds",
        prompt: "Check student clearance records and summarize how many students have pending clearances and their top hold reasons.",
        agent: "registrar_auditor",
        icon: GraduationCap,
    },
    {
        title: "Tuition Efficiency",
        description: "Analyze tuition collections and payment completion status",
        prompt: "Analyze our tuition collection efficiency for the current academic term and summarize total collections vs outstanding balances.",
        agent: "bursar_finance",
        icon: ReceiptText,
    },
    {
        title: "Administrative Memo",
        description: "Draft an official policy announcement in PDF format",
        prompt: "Draft an administrative memorandum in PDF format regarding upcoming enrollment deadlines and academic guidelines.",
        agent: "admin_executive",
        icon: Sparkles,
    },
];

export interface ConversationItem {
    id: string;
    title: string;
    created_at?: string;
    updated_at?: string;
}

export interface ConversationGroup {
    label: string;
    items: ConversationItem[];
}

export function groupConversationsByDate(conversations: ConversationItem[]): ConversationGroup[] {
    const today: ConversationItem[] = [];
    const yesterday: ConversationItem[] = [];
    const last7Days: ConversationItem[] = [];
    const older: ConversationItem[] = [];

    const now = new Date();
    const todayStart = new Date(now.getFullYear(), now.getMonth(), now.getDate()).getTime();
    const yesterdayStart = todayStart - 86400000;
    const last7DaysStart = todayStart - 7 * 86400000;

    conversations.forEach((conv) => {
        const timeStr = conv.updated_at || conv.created_at;
        const time = timeStr ? new Date(timeStr).getTime() : 0;
        if (time >= todayStart) {
            today.push(conv);
        } else if (time >= yesterdayStart) {
            yesterday.push(conv);
        } else if (time >= last7DaysStart) {
            last7Days.push(conv);
        } else {
            older.push(conv);
        }
    });

    return [
        { label: "Today", items: today },
        { label: "Yesterday", items: yesterday },
        { label: "Previous 7 Days", items: last7Days },
        { label: "Older", items: older },
    ].filter((group) => group.items.length > 0);
}
