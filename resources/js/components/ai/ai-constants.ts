import { BarChart3, Calculator, Calendar, GraduationCap, HelpCircle, ReceiptText, ShieldCheck, Sparkles } from "lucide-react";
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
        title: "Room & Class Timetable",
        description: "Check schedule and room availability for classes or faculty",
        prompt: "Check the schedule and room availability for classrooms this week, and list any active bookings.",
        agent: "admin_executive",
        icon: Calendar,
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

export interface AgentToolInfo {
    name: string;
    class?: string;
    description: string;
    kind: "tool" | "mcp" | "agent";
}

/**
 * Fallback static catalogue of tools actually bound to each administrator
 * specialist. Mirrors app/Ai/Agents/*.php tools() so the composer "tools"
 * popover shows tools/MCP that will be used — not the list of agents.
 *
 * The full-page chat prefers the live `/administrators/ai/agent-tools`
 * endpoint (which also includes enabled external MCP servers) and only uses
 * this map when the endpoint is unreachable.
 */
export const AGENT_TOOLS_FALLBACK: Record<AgentRoleKey, AgentToolInfo[]> = {
    admin_executive: [
        { name: "query-campus-analytics-tool", description: "Live enrollment, demographics, clearance, finance, and faculty counts.", kind: "tool" },
        { name: "generate-analytics-chart-tool", description: "Builds ring / bar / line / gauge chart artifacts for the transcript.", kind: "tool" },
        { name: "generate-administrative-document-tool", description: "Generates official PDF / CSV / Markdown documents for download.", kind: "tool" },
        { name: "get-class-enrollments-tool", description: "Class-section roster with student number, name, and status.", kind: "tool" },
        { name: "get-class-grades-tool", description: "Passing rates and performance for a class section.", kind: "tool" },
        { name: "get-class-attendance-summary-tool", description: "Attendance and absenteeism summary for a class.", kind: "tool" },
        { name: "get-faculty-assigned-classes-tool", description: "Teaching load and assigned classes for a faculty member.", kind: "tool" },
        { name: "lookup-class-schedules-tool", description: "Find class sections and meeting schedules.", kind: "tool" },
        { name: "lookup-room-availability-tool", description: "Classroom availability and bookings.", kind: "tool" },
        { name: "search-students-tool", description: "Directory search by name, program, status, and term filters.", kind: "tool" },
        { name: "query-timetable-schedule-tool", description: "Timetable meetings for classes, rooms, students, or faculty.", kind: "tool" },
        { name: "manage-student-tool", description: "Create / update student profiles (requires confirmation).", kind: "tool" },
        { name: "manage-curriculum-subject-tool", description: "Single curriculum subject CRUD.", kind: "tool" },
        { name: "manage-class-schedule-tool", description: "Class, schedule, and instructor / room assignment mutations.", kind: "tool" },
        { name: "manage-room-tool", description: "Classroom and facility management.", kind: "tool" },
        { name: "get-student-profile-tool", description: "Authoritative student background and clearance standing (MCP).", kind: "mcp" },
        { name: "get-course-curriculum-tool", description: "Degree program curriculum by year and semester (MCP).", kind: "mcp" },
        { name: "get-statement-of-account-tool", description: "Auditable tuition assessment and balance (MCP).", kind: "mcp" },
        { name: "get-enrollment-status-tool", description: "Single enrollment workflow state (MCP).", kind: "mcp" },
        { name: "list-pending-enrollments-tool", description: "Queue awaiting review / verification / cashier approval (MCP).", kind: "mcp" },
        { name: "registrar_auditor", description: "Delegated registrar audits and clearance checks.", kind: "agent" },
        { name: "bursar_finance", description: "Delegated ledger and Statement of Account work.", kind: "agent" },
        { name: "campus_support", description: "Delegated handbook and policy checks.", kind: "agent" },
    ],
    registrar_auditor: [
        { name: "audit-student-profile-import-tool", description: "Catches malformed LRNs and duplicates in import batches.", kind: "tool" },
        { name: "simulate-policy-impact-tool", description: "Simulates enrollment policy rules.", kind: "tool" },
        { name: "audit-graduation-clearance-tool", description: "Outstanding obligations across departments.", kind: "tool" },
        { name: "batch-update-clearance-tool", description: "Clear holds in batch (requires confirmation).", kind: "tool" },
        { name: "analyze-transcript-document-tool", description: "Evaluates prior-school transcripts (TOR).", kind: "tool" },
        { name: "search-students-tool", description: "Resolve named students or cohorts (MCP).", kind: "mcp" },
        { name: "get-student-profile-tool", description: "Authoritative student record verification (MCP).", kind: "mcp" },
        { name: "list-pending-enrollments-tool", description: "Queue awaiting administrative review (MCP).", kind: "mcp" },
        { name: "get-enrollment-status-tool", description: "Single enrollment workflow state (MCP).", kind: "mcp" },
        { name: "get-enrollment-audit-trail-tool", description: "Recorded transitions on one enrollment (MCP).", kind: "mcp" },
        { name: "get-course-curriculum-tool", description: "Program requirements for clearance verdicts (MCP).", kind: "mcp" },
    ],
    bursar_finance: [
        { name: "explain-statement-of-account-tool", description: "Breaks down tuition, lab fees, and payments.", kind: "tool" },
        { name: "validate-adjustment-spreadsheet-tool", description: "Catches negative entries and duplicate adjustment rows.", kind: "tool" },
        { name: "simulate-scholarship-adjustment-tool", description: "Projects net scholarship discounts.", kind: "tool" },
        { name: "apply-tuition-adjustment-batch-tool", description: "Commits ledger modifications (requires approval).", kind: "tool" },
        { name: "get-statement-of-account-tool", description: "Actual assessment record for audit-defensible figures (MCP).", kind: "mcp" },
        { name: "get-student-profile-tool", description: "Confirm whose account is discussed (MCP).", kind: "mcp" },
        { name: "get-enrollment-status-tool", description: "Confirm enrollment the assessment belongs to (MCP).", kind: "mcp" },
        { name: "search-students-tool", description: "Resolve student by name when only a name is given (MCP).", kind: "mcp" },
    ],
    campus_support: [
        { name: "campus-knowledge-search-tool", description: "Search handbooks, policies, and academic calendars.", kind: "tool" },
        { name: "create-help-ticket-tool", description: "Files a support ticket for escalation.", kind: "tool" },
        { name: "lookup-ticket-status-tool", description: "Checks ticket progress.", kind: "tool" },
        { name: "escalate-to-department-tool", description: "Escalates to the responsible department.", kind: "tool" },
        { name: "get-my-context-tool", description: "Current school, academic period, and account scope (MCP).", kind: "mcp" },
        { name: "get-school-details-tool", description: "Departments, programs, and official contacts (MCP).", kind: "mcp" },
        { name: "get-student-schedule-tool", description: "Schedule for the entitled requester only (MCP).", kind: "mcp" },
    ],
    student_advisor: [
        { name: "search-students-tool", description: "Student directory lookup (MCP).", kind: "mcp" },
        { name: "get-course-curriculum-tool", description: "Program curriculum by year and semester (MCP).", kind: "mcp" },
    ],
    faculty_copilot: [
        { name: "generate-rubric-tool", description: "Rubric formulation for assignments.", kind: "tool" },
        { name: "detect-at-risk-students-tool", description: "Academic risk signals.", kind: "tool" },
    ],
};

export function getFallbackTools(agent: AgentRoleKey): AgentToolInfo[] {
    return AGENT_TOOLS_FALLBACK[agent] ?? [];
}

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
