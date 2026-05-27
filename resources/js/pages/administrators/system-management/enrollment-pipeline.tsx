import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Checkbox } from "@/components/ui/checkbox";
import { Combobox, type ComboboxOption } from "@/components/ui/combobox";
import {
    ContextMenu,
    ContextMenuContent,
    ContextMenuItem,
    ContextMenuLabel,
    ContextMenuSeparator,
    ContextMenuSub,
    ContextMenuSubContent,
    ContextMenuSubTrigger,
    ContextMenuTrigger,
} from "@/components/ui/context-menu";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Separator } from "@/components/ui/separator";
import { Switch } from "@/components/ui/switch";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { useForm } from "@inertiajs/react";
import {
    ArrowRight,
    BarChart3,
    Bell,
    BookOpenCheck,
    Calculator,
    CheckCircle2,
    CircleDollarSign,
    CreditCard,
    Filter,
    GitBranch,
    GraduationCap,
    Loader2,
    LockKeyhole,
    Mail,
    MousePointerClick,
    Pencil,
    Plus,
    Save,
    Scissors,
    Settings2,
    ShieldCheck,
    Sparkles,
    Target,
    Trash2,
    UserCheck,
    Users,
    Workflow,
} from "lucide-react";
import { useCallback, useEffect, useMemo, useState } from "react";
import ReactFlow, { addEdge, Background, BackgroundVariant, Controls, Handle, MarkerType, Position, useEdgesState, useNodesState, type Connection, type Edge, type Node, type NodeProps } from "reactflow";
import "reactflow/dist/style.css";

import { submitSystemForm } from "./form-submit";
import SystemManagementLayout from "./layout";
import type {
    ActionCondition,
    ActionConditionType,
    EnrollmentPipelineActionType,
    EnrollmentPipelineNodeAction,
    EnrollmentPipelineNodeActionType,
    EnrollmentPipelineNodeCondition,
    EnrollmentPipelineStep,
    EnrollmentPipelineStepAction,
    EnrollmentStatMetric,
    EnrollmentStatsCard,
    SystemManagementPageProps,
} from "./types";

interface EnrollmentPipelineFormData {
    schema_version: number;
    submitted_label: string;
    enrollment_courses: number[];
    entry_step_key: string;
    completion_step_key: string;
    steps: EnrollmentPipelineStep[];
    automation: {
        auto_create_student_enrollment: boolean;
        auto_assign_subjects: boolean;
        default_new_applicant_to_first_year: boolean;
    };
    enrollment_stats: {
        cards: EnrollmentStatsCard[];
    };
}

const colorOptions = ["yellow", "blue", "green", "emerald", "teal", "gray", "amber", "red", "indigo", "orange"] as const;

const colorHex: Record<(typeof colorOptions)[number], string> = {
    yellow: "#facc15",
    blue: "#3b82f6",
    green: "#22c55e",
    emerald: "#10b981",
    teal: "#14b8a6",
    gray: "#6b7280",
    amber: "#f59e0b",
    red: "#ef4444",
    indigo: "#6366f1",
    orange: "#f97316",
};

const actionOptions: Array<{
    value: EnrollmentPipelineActionType;
    label: string;
    description: string;
    icon: typeof CheckCircle2;
}> = [
    {
        value: "standard",
        label: "Move to this status",
        description: "Use this for review steps that only need approval and a status change.",
        icon: CheckCircle2,
    },
    {
        value: "department_verification",
        label: "Run department verification",
        description: "Executes the department verification flow, including the existing status update and notifications.",
        icon: BookOpenCheck,
    },
    {
        value: "cashier_verification",
        label: "Require cashier/payment verification",
        description: "Routes this step through the payment verification flow instead of a simple approval button.",
        icon: CircleDollarSign,
    },
];

const nodeActionOptions: Array<{ value: EnrollmentPipelineNodeActionType; label: string; description: string; icon: typeof CheckCircle2 }> = [
    { value: "change_status", label: "Change status", description: "Move the enrollment into this node status.", icon: GitBranch },
    { value: "send_email", label: "Send email", description: "Send a simple email to the student.", icon: Mail },
    { value: "send_notification", label: "Send notification", description: "Notify super admins and optionally the student.", icon: Bell },
    { value: "send_department_verification_notification", label: "Notify department verified", description: "Send the department verification database and student email notifications.", icon: Bell },
    { value: "create_subject_enrollments", label: "Create subject enrollments", description: "Create subject enrollment rows from the submitted subject payload.", icon: BookOpenCheck },
    { value: "create_additional_fees", label: "Create additional fees", description: "Create enrollment fee rows from the submitted additional-fee payload.", icon: CreditCard },
    { value: "department_verification", label: "Department verification", description: "Run the existing department verification flow.", icon: ShieldCheck },
    { value: "cashier_verification", label: "Cashier verification", description: "Run the existing cashier payment verification flow.", icon: CircleDollarSign },
    { value: "manual_cashier_verification", label: "Manual cashier verification", description: "Mark tuition verified without creating receipt transactions.", icon: CircleDollarSign },
    { value: "sync_student_enrolled_status", label: "Sync enrolled status", description: "Mark the student status as enrolled.", icon: UserCheck },
    { value: "auto_enroll_classes", label: "Auto-enroll classes", description: "Enroll the student in available classes.", icon: GraduationCap },
    { value: "calculate_tuition", label: "Calculate tuition", description: "Create tuition from configured or submitted tuition data.", icon: Calculator },
    { value: "apply_cashier_payment", label: "Apply cashier payment", description: "Apply receipt settlement amounts to the tuition balance.", icon: Calculator },
    { value: "update_student_academic_year", label: "Update student year", description: "Copy the enrollment academic year onto the student record.", icon: GraduationCap },
    { value: "link_first_year_student_account", label: "Link first-year account", description: "Attach a first-year student's account to their student profile.", icon: UserCheck },
    { value: "send_student_migrated_notification", label: "Notify migrated student", description: "Send the student migration email notification.", icon: Mail },
    { value: "send_super_admin_enrollment_notification", label: "Notify admins enrolled", description: "Send super admin enrollment completion notification.", icon: Bell },
    { value: "update_tuition", label: "Update tuition", description: "Update existing tuition attributes from action config.", icon: Pencil },
    { value: "create_payment_transactions", label: "Create transactions", description: "Create cashier receipt and separate-fee transactions.", icon: CreditCard },
];

const conditionTypeOptions: Array<{ value: ActionConditionType; label: string }> = [
    // Academic
    { value: "total_units", label: "Total enrolled units" },
    { value: "subject_count", label: "Subject count" },
    { value: "year_level", label: "Year level" },
    { value: "semester", label: "Current semester" },
    { value: "gpa", label: "Grade point average" },
    { value: "failed_subjects", label: "Failed subjects" },
    // Financial
    { value: "has_balance", label: "Has outstanding balance" },
    { value: "balance_amount", label: "Balance amount" },
    { value: "has_paid_full", label: "Has paid in full" },
    { value: "has_paid_partial", label: "Has partial payment" },
    { value: "amount_paid", label: "Amount paid" },
    { value: "has_scholarship", label: "Has scholarship" },
    // Status
    { value: "is_first_year", label: "Is first year" },
    { value: "is_transferee", label: "Is transferee" },
    { value: "is_regular", label: "Is regular student" },
    { value: "is_new_student", label: "Is new student" },
    { value: "has_incomplete_grades", label: "Has incomplete grades" },
    { value: "has_prerequisites", label: "Prerequisites completed" },
    // Demographics
    { value: "age", label: "Student age" },
    { value: "gender", label: "Gender" },
    { value: "course", label: "Course code" },
];

const booleanConditionTypes = new Set<ActionConditionType>([
    "has_balance", "has_paid_full", "has_paid_partial", "has_scholarship",
    "is_first_year", "is_transferee", "is_regular", "is_new_student",
    "has_incomplete_grades", "has_prerequisites",
]);

const textConditionTypes = new Set<ActionConditionType>(["gender", "course"]);

const conditionOperatorOptions: Array<{ value: string; label: string }> = [
    { value: "eq", label: "=" },
    { value: "neq", label: "≠" },
    { value: "gt", label: ">" },
    { value: "gte", label: "≥" },
    { value: "lt", label: "<" },
    { value: "lte", label: "≤" },
];

const defaultActionCondition = (): ActionCondition => ({
    type: "total_units",
    operator: "gte",
    value: 0,
    enabled: true,
});

const normalizeActionConditionValue = (condition: ActionCondition): ActionCondition["value"] => {
    if (booleanConditionTypes.has(condition.type)) {
        return Boolean(condition.value);
    }

    if (textConditionTypes.has(condition.type)) {
        return String(condition.value ?? "");
    }

    return Number(condition.value) || 0;
};

const actionConditionValueSummary = (condition: ActionCondition): string => {
    if (booleanConditionTypes.has(condition.type)) {
        return condition.value ? "Yes" : "No";
    }

    return String(condition.value ?? "");
};

const conditionOperatorLabel = (condition: ActionCondition): string => {
    if (booleanConditionTypes.has(condition.type)) {
        return "is";
    }

    return conditionOperatorOptions.find((option) => option.value === condition.operator)?.label ?? condition.operator;
};

const getConditionLabel = (type: ActionConditionType): string => conditionTypeOptions.find((option) => option.value === type)?.label || type;

const profileFieldOptions = [
    { value: "first_name", label: "First name" },
    { value: "last_name", label: "Last name" },
    { value: "email", label: "Email" },
    { value: "phone", label: "Phone" },
    { value: "birth_date", label: "Birth date" },
    { value: "gender", label: "Gender" },
    { value: "address", label: "Address" },
    { value: "course_id", label: "Course" },
];

const statsMetricOptions: Array<{ value: EnrollmentStatMetric; label: string }> = [
    { value: "total_records", label: "Total Records" },
    { value: "active_records", label: "Active Records" },
    { value: "trashed_records", label: "Deleted Records" },
    { value: "status_count", label: "Status Count" },
    { value: "paid_count", label: "Fully Paid Count" },
];

const slugify = (value: string, fallback: string): string => {
    const slug = value
        .trim()
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, "_")
        .replace(/^_+|_+$/g, "");

    return slug.length > 0 ? slug : fallback;
};

const actionsForActionType = (actionType: EnrollmentPipelineActionType): EnrollmentPipelineStepAction[] => {
    if (actionType === "department_verification") {
        return ["department_verification"];
    }

    if (actionType === "cashier_verification") {
        return ["cashier_verification"];
    }

    return ["advance_status"];
};

const nodeActionsForActionType = (actionType: EnrollmentPipelineActionType): EnrollmentPipelineNodeAction[] => {
    const types: EnrollmentPipelineNodeActionType[] =
        actionType === "department_verification"
            ? ["change_status", "send_department_verification_notification"]
            : actionType === "cashier_verification"
              ? [
                    "create_payment_transactions",
                    "apply_cashier_payment",
                    "update_student_academic_year",
                    "auto_enroll_classes",
                    "link_first_year_student_account",
                    "send_student_migrated_notification",
                    "change_status",
                    "sync_student_enrolled_status",
                    "send_super_admin_enrollment_notification",
                ]
              : ["change_status"];

    return types.map((type, index) => ({
        key: type === "change_status" ? "advance_status" : type,
        type,
        enabled: true,
        order: index + 1,
        config: {},
        halt_on_failure: true,
    }));
};

const normalizeNodeActions = (step: EnrollmentPipelineStep, actionType: EnrollmentPipelineActionType): EnrollmentPipelineNodeAction[] => {
    if (step.node_actions?.length) {
        return [...step.node_actions].sort((left, right) => left.order - right.order);
    }

    return nodeActionsForActionType(actionType);
};

const normalizeNodeConditions = (step: EnrollmentPipelineStep): EnrollmentPipelineNodeCondition[] =>
    step.node_conditions?.length ? [...step.node_conditions].sort((left, right) => left.order - right.order) : [];

const normalizeActionType = (step: EnrollmentPipelineStep): EnrollmentPipelineActionType => {
    if (step.actions?.includes("cashier_verification")) {
        return "cashier_verification";
    }

    if (step.actions?.includes("department_verification")) {
        return "department_verification";
    }

    return step.action_type || "standard";
};

const createDefaultStep = (index: number): EnrollmentPipelineStep => ({
    key: `step_${index}`,
    status: `step_${index}`,
    label: `Step ${index}`,
    color: "indigo",
    allowed_roles: [],
    action_type: "standard",
    actions: ["advance_status"],
    node_actions: nodeActionsForActionType("standard"),
    node_conditions: [],
    next_step_key: null,
    next_step_keys: [],
});

interface CanvasNodePosition {
    x: number;
    y: number;
}

const canvasNodeWidth = 360;
const canvasNodeHeight = 112;

const getCanvasNodeId = (step: EnrollmentPipelineStep, index: number): string => `${step.key || "step"}-${index}`;

const defaultCanvasPosition = (index: number): CanvasNodePosition => ({
    x: 80 + (index % 2) * 420,
    y: 72 + Math.floor(index / 2) * 180,
});

interface PipelineNodeData {
    step: EnrollmentPipelineStep;
    index: number;
    isEntry: boolean;
    isCompletion: boolean;
    actionLabel: string;
    onEdit: () => void;
    onDuplicate: () => void;
    onDelete: () => void;
    onDisconnect: () => void;
    onSetEntry: () => void;
    onSetCompletion: () => void;
    onConnectTo: (targetIndex: number) => void;
    onAddNodeAction: () => void;
    onRemoveNodeAction: (actionIndex: number) => void;
    onUpdateNodeAction: (actionIndex: number, updater: (action: EnrollmentPipelineNodeAction) => EnrollmentPipelineNodeAction) => void;
    availableTargets: Array<{ index: number; label: string }>;
}

interface ActionNodeData {
    action: EnrollmentPipelineNodeAction;
    actionIndex: number;
    stepIndex: number;
    stepStatus: string;
    onEdit: () => void;
    onUpdate: (updater: (action: EnrollmentPipelineNodeAction) => EnrollmentPipelineNodeAction) => void;
    onRemove: () => void;
}

function ActionNode({ data, selected }: NodeProps<ActionNodeData>) {
    const { action, actionIndex, stepStatus, onEdit, onUpdate, onRemove } = data;
    const option = nodeActionOptions.find((o) => o.value === action.type);
    const Icon = option?.icon;
    const conditions = action.conditions || [];
    const enabledConditions = conditions.filter((condition) => condition.enabled !== false);

    return (
        <>
            <Handle type="target" position={Position.Top} className="!bg-emerald-500 !w-2.5 !h-2.5" />
            <div
                className={`bg-card relative flex w-[280px] flex-col rounded-xl border text-left shadow-sm transition ${
                    selected ? "border-emerald-500 ring-emerald-500/20 ring-2" : "hover:border-emerald-500/40"
                }`}
                style={{ cursor: "default" }}
                onDoubleClick={(event) => {
                    event.stopPropagation();
                    onEdit();
                }}
            >
                <div className="flex items-center gap-2 px-3 py-2">
                    <span className="flex h-6 w-6 shrink-0 items-center justify-center rounded-md bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                        {Icon ? <Icon className="h-3 w-3" /> : <span className="text-[10px] font-bold">{actionIndex + 1}</span>}
                    </span>
                    <span className="flex-1 min-w-0">
                        <span className="block truncate text-xs font-medium">{option?.label || action.type}</span>
                    </span>
                    <button
                        type="button"
                        onClick={(event) => {
                            event.stopPropagation();
                            onEdit();
                        }}
                        className="text-muted-foreground hover:text-foreground text-[10px]"
                    >
                        Edit
                    </button>
                    <button
                        type="button"
                        onClick={(e) => { e.stopPropagation(); onRemove(); }}
                        className="text-muted-foreground hover:text-destructive text-[10px]"
                    >
                        ×
                    </button>
                </div>

                <div className="border-t px-3 py-2 space-y-2">
                    {action.type === "change_status" && (
                        <p className="text-muted-foreground text-[10px]">
                            Sets status to{" "}
                            <span className="text-foreground font-mono">{typeof action.config?.status === "string" ? action.config.status : stepStatus}</span>
                        </p>
                    )}
                    {action.type === "send_email" && (
                        <p className="text-muted-foreground text-[10px]">Sends notification email to the student. Template configured in Settings.</p>
                    )}
                    {action.type === "send_notification" && (
                        <p className="text-muted-foreground text-[10px]">Notifies super admins and optionally the student about the status change.</p>
                    )}
                    {action.type === "send_department_verification_notification" && (
                        <p className="text-muted-foreground text-[10px]">Sends department verification notifications to admins and the student.</p>
                    )}
                    {action.type === "create_subject_enrollments" && (
                        <p className="text-muted-foreground text-[10px]">Creates subject enrollments from the request payload.</p>
                    )}
                    {action.type === "create_additional_fees" && (
                        <p className="text-muted-foreground text-[10px]">Creates additional fee records from the request payload.</p>
                    )}
                    {action.type === "department_verification" && (
                        <p className="text-muted-foreground text-[10px]">Runs the full department verification flow with status update and notifications.</p>
                    )}
                    {action.type === "cashier_verification" && (
                        <p className="text-muted-foreground text-[10px]">Routes through cashier payment verification flow.</p>
                    )}
                    {action.type === "manual_cashier_verification" && (
                        <p className="text-muted-foreground text-[10px]">Marks tuition verified without creating receipt transactions.</p>
                    )}
                    {action.type === "sync_student_enrolled_status" && (
                        <p className="text-muted-foreground text-[10px]">Marks the student's overall enrollment status as enrolled.</p>
                    )}
                    {action.type === "auto_enroll_classes" && (
                        <p className="text-muted-foreground text-[10px]">Auto-enrolls the student in available classes for their course and period.</p>
                    )}
                    {action.type === "calculate_tuition" && (
                        <p className="text-muted-foreground text-[10px]">Creates tuition record from configured or submitted tuition data.</p>
                    )}
                    {action.type === "apply_cashier_payment" && (
                        <p className="text-muted-foreground text-[10px]">Updates tuition balance from cashier settlements.</p>
                    )}
                    {action.type === "update_student_academic_year" && (
                        <p className="text-muted-foreground text-[10px]">Copies enrollment year onto the student record.</p>
                    )}
                    {action.type === "link_first_year_student_account" && (
                        <p className="text-muted-foreground text-[10px]">Links a matching account when the student is first year.</p>
                    )}
                    {action.type === "send_student_migrated_notification" && (
                        <p className="text-muted-foreground text-[10px]">Emails the student that they can use the student portal.</p>
                    )}
                    {action.type === "send_super_admin_enrollment_notification" && (
                        <p className="text-muted-foreground text-[10px]">Notifies super admins and links to the assessment.</p>
                    )}
                    {action.type === "update_tuition" && (
                        <p className="text-muted-foreground text-[10px]">Updates existing tuition record with new attribute values.</p>
                    )}
                    {action.type === "create_payment_transactions" && (
                        <p className="text-muted-foreground text-[10px]">Creates payment transaction records for verified cashier payments.</p>
                    )}

                    <div className="border-t pt-2 mt-1">
                        <div className="mb-1.5 flex items-center justify-between">
                            <span className="text-muted-foreground flex items-center gap-1 text-[10px] font-medium">
                                <Filter className="h-3 w-3" /> Conditions
                            </span>
                            <span className="text-muted-foreground text-[10px]">{action.condition_logic === "any" ? "Any" : "All"}</span>
                        </div>
                        {enabledConditions.length === 0 && (
                            <p className="text-muted-foreground text-[9px] italic">No conditions — action always runs.</p>
                        )}
                        {enabledConditions.slice(0, 2).map((condition, conditionIndex) => (
                            <div key={conditionIndex} className="bg-muted/30 mb-1 truncate rounded-md border px-2 py-1 text-[9px]">
                                {getConditionLabel(condition.type)} {conditionOperatorLabel(condition)} {actionConditionValueSummary(condition)}
                            </div>
                        ))}
                        {enabledConditions.length > 2 && (
                            <p className="text-muted-foreground text-[9px]">+{enabledConditions.length - 2} more condition(s)</p>
                        )}
                    </div>

                    <div className="flex items-center gap-2">
                        <Switch
                            checked={action.enabled ?? true}
                            onCheckedChange={(enabled) => onUpdate((a) => ({ ...a, enabled }))}
                        />
                        <Label className="text-[10px]">Enabled</Label>
                    </div>
                </div>
            </div>
            <Handle type="source" position={Position.Bottom} className="!bg-emerald-500 !w-2.5 !h-2.5" />
        </>
    );
}

function PipelineNode({ data, selected }: NodeProps<PipelineNodeData>) {
    const { step, index, isEntry, isCompletion, onEdit, onDuplicate, onDelete, onDisconnect, onSetEntry, onSetCompletion, onConnectTo, onAddNodeAction, onRemoveNodeAction, onUpdateNodeAction, availableTargets } = data;
    const actions = step.node_actions || [];

    return (
        <>
            <Handle id="input-left" type="target" position={Position.Left} className="!bg-primary !h-3 !w-3" style={{ top: 44 }} />
            <Handle id="input-top" type="target" position={Position.Top} className="!bg-primary !h-2.5 !w-2.5" style={{ left: 72 }} />
            <ContextMenu>
                <ContextMenuTrigger asChild>
                    <div
                        className={`bg-card relative flex flex-col w-full rounded-2xl border text-left shadow-sm transition ${
                            selected
                                ? "border-primary ring-primary/20 ring-2"
                                : "hover:border-primary/40 hover:shadow-md"
                        }`}
                        style={{ width: canvasNodeWidth, cursor: "default" }}
                    >
                        <span className="flex items-start gap-3 p-4">
                            <span
                                className="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-sm font-semibold text-white shadow-sm"
                                style={{ backgroundColor: colorHex[step.color as keyof typeof colorHex] ?? colorHex.gray }}
                            >
                                {index + 1}
                            </span>
                            <span className="min-w-0 flex-1 space-y-1.5">
                                <span className="flex flex-wrap items-center gap-2">
                                    <span className="font-medium">{step.label || `Step ${index + 1}`}</span>
                                    {isEntry && <Badge>Entry</Badge>}
                                    {isCompletion && <Badge variant="secondary">Completion</Badge>}
                                </span>
                                <span className="text-muted-foreground block truncate font-mono text-xs">{step.status || "missing_status"}</span>
                            </span>
                        </span>

                        <div className="border-t px-3 py-2 flex items-center justify-between">
                            <span className="text-muted-foreground text-[11px]">
                                {actions.length > 0
                                    ? `${actions.length} action${actions.length !== 1 ? "s" : ""} attached`
                                    : "No actions"}
                            </span>
                            <button
                                type="button"
                                onPointerDown={(e) => {
                                    e.stopPropagation();
                                    e.preventDefault();
                                    onAddNodeAction();
                                }}
                                className="text-muted-foreground hover:text-foreground inline-flex h-5 items-center gap-1 rounded-md border border-dashed px-1.5 text-[10px] transition"
                            >
                                + Action
                            </button>
                        </div>
                        <span className="text-muted-foreground absolute top-1/2 -right-10 hidden -translate-y-1/2 text-[9px] font-medium xl:block">
                            next
                        </span>
                        <span className="text-muted-foreground absolute right-8 -bottom-5 hidden text-[9px] font-medium xl:block">
                            actions
                        </span>
                    </div>
                </ContextMenuTrigger>
                <ContextMenuContent className="w-56">
                    <ContextMenuLabel>{step.label || `Step ${index + 1}`}</ContextMenuLabel>
                    <ContextMenuSeparator />
                    <ContextMenuItem onSelect={onEdit}>
                        <Pencil className="h-4 w-4" /> Edit node
                    </ContextMenuItem>
                    <ContextMenuItem onSelect={onAddNodeAction}>
                        <Plus className="h-4 w-4" /> Add action
                    </ContextMenuItem>
                    <ContextMenuItem onSelect={onDuplicate}>
                        <Plus className="h-4 w-4" /> Duplicate
                    </ContextMenuItem>
                    <ContextMenuSub>
                        <ContextMenuSubTrigger>
                            <ArrowRight className="h-4 w-4" /> Connect to
                        </ContextMenuSubTrigger>
                        <ContextMenuSubContent className="w-56">
                            {availableTargets
                                .filter((target) => target.index !== index)
                                .map((target) => (
                                    <ContextMenuItem key={target.index} onSelect={() => onConnectTo(target.index)}>
                                        {target.label}
                                    </ContextMenuItem>
                                ))}
                        </ContextMenuSubContent>
                    </ContextMenuSub>
                    <ContextMenuItem onSelect={onDisconnect} disabled={!step.next_step_key && !(step.next_step_keys?.length)}>
                        <Scissors className="h-4 w-4" /> Disconnect
                    </ContextMenuItem>
                    <ContextMenuSeparator />
                    <ContextMenuItem onSelect={onSetEntry}>Set as entry</ContextMenuItem>
                    <ContextMenuItem onSelect={onSetCompletion}>Set as completion</ContextMenuItem>
                    <ContextMenuSeparator />
                    <ContextMenuItem variant="destructive" onSelect={onDelete}>
                        <Trash2 className="h-4 w-4" /> Delete
                    </ContextMenuItem>
                </ContextMenuContent>
            </ContextMenu>
            <Handle id="next-main" type="source" position={Position.Right} className="!bg-primary !h-3 !w-3" style={{ top: 44 }} />
            <Handle id="next-top" type="source" position={Position.Right} className="!bg-primary !h-2.5 !w-2.5" style={{ top: 24 }} />
            <Handle id="next-bottom" type="source" position={Position.Right} className="!bg-primary !h-2.5 !w-2.5" style={{ top: 84 }} />
            <Handle id="action-out" type="source" position={Position.Bottom} className="!h-2.5 !w-2.5 !bg-emerald-500" style={{ left: 96 }} />
        </>
    );
}

export default function SystemManagementEnrollmentPipelinePage({
    user,
    general_settings,
    enrollment_pipeline,
    enrollment_stats,
    available_roles,
    available_enrollment_courses,
    access,
}: SystemManagementPageProps) {
    const initialSteps = enrollment_pipeline?.steps || [];
    const availableEnrollmentCourseIds = new Set((available_enrollment_courses ?? []).map((course) => course.id));

    const pipelineForm = useForm<EnrollmentPipelineFormData>({
        schema_version: enrollment_pipeline?.schema_version || 2,
        submitted_label: enrollment_pipeline?.submitted_label || "Submitted",
        enrollment_courses: (general_settings.enrollment_courses ?? [])
            .map((courseId) => Number(courseId))
            .filter((courseId) => Number.isInteger(courseId) && courseId > 0)
            .filter((courseId) => availableEnrollmentCourseIds.has(courseId)),
        entry_step_key: enrollment_pipeline?.entry_step_key || initialSteps[0]?.key || "",
        completion_step_key: enrollment_pipeline?.completion_step_key || initialSteps[initialSteps.length - 1]?.key || "",
        steps: initialSteps.map((step, index) => {
            const actionType = normalizeActionType(step);

            return {
                key: step.key || `step_${index + 1}`,
                status: step.status,
                label: step.label,
                color: step.color || "indigo",
                allowed_roles: step.allowed_roles || [],
                action_type: actionType,
                actions: step.actions?.length ? step.actions : actionsForActionType(actionType),
                node_actions: normalizeNodeActions(step, actionType),
                node_conditions: normalizeNodeConditions(step),
                next_step_key: step.next_step_key ?? initialSteps[index + 1]?.key ?? null,
                next_step_keys: Array.from(
                    new Set(
                        [
                            ...(Array.isArray(step.next_step_keys) ? step.next_step_keys : []),
                            step.next_step_key ?? initialSteps[index + 1]?.key ?? null,
                        ].filter((key): key is string => typeof key === "string" && key.length > 0),
                    ),
                ),
            };
        }),
        automation: {
            auto_create_student_enrollment: enrollment_pipeline?.automation?.auto_create_student_enrollment ?? false,
            auto_assign_subjects: enrollment_pipeline?.automation?.auto_assign_subjects ?? false,
            default_new_applicant_to_first_year: enrollment_pipeline?.automation?.default_new_applicant_to_first_year ?? true,
        },
        enrollment_stats: {
            cards: enrollment_stats?.cards || [],
        },
    });

    const [selectedStepIndex, setSelectedStepIndex] = useState<number | null>(initialSteps.length > 0 ? 0 : null);
    const [selectedRoleByStep, setSelectedRoleByStep] = useState<Record<number, string>>({});
    const [enrollmentCourseSearch, setEnrollmentCourseSearch] = useState("");
    const [nodes, setNodes, onNodesChange] = useNodesState([]);
    const [edges, setEdges, onEdgesChange] = useEdgesState([]);
    const [nodeEditorOpen, setNodeEditorOpen] = useState(false);
    const [editorTab, setEditorTab] = useState("basic");
    const [actionPickerOpen, setActionPickerOpen] = useState(false);
    const [actionPickerStepIndex, setActionPickerStepIndex] = useState<number | null>(null);
    const [actionEditorOpen, setActionEditorOpen] = useState(false);
    const [editingAction, setEditingAction] = useState<{ stepIndex: number; actionIndex: number } | null>(null);

    const STORAGE_KEY = "enrollment-pipeline-node-positions";

    const [savedPositions, setSavedPositions] = useState<Record<string, CanvasNodePosition>>(() => {
        try {
            const stored = localStorage.getItem(STORAGE_KEY);

            return stored ? JSON.parse(stored) : {};
        } catch {
            return {};
        }
    });

    const formErrors = pipelineForm.errors as Record<string, string | undefined>;
    const selectedStep = selectedStepIndex !== null ? pipelineForm.data.steps[selectedStepIndex] : undefined;
    const selectedAction =
        editingAction !== null ? pipelineForm.data.steps[editingAction.stepIndex]?.node_actions?.[editingAction.actionIndex] : undefined;
    const selectedActionStep = editingAction !== null ? pipelineForm.data.steps[editingAction.stepIndex] : undefined;
    const selectedNextSteps = selectedStep
        ? pipelineForm.data.steps.filter((step) => {
              const nextKeys = selectedStep.next_step_keys?.length ? selectedStep.next_step_keys : selectedStep.next_step_key ? [selectedStep.next_step_key] : [];

              return nextKeys.includes(step.key);
          })
        : [];
    const selectedIncomingSteps = selectedStep
        ? pipelineForm.data.steps.filter((step) => {
              const nextKeys = step.next_step_keys?.length ? step.next_step_keys : step.next_step_key ? [step.next_step_key] : [];

              return nextKeys.includes(selectedStep.key);
          })
        : [];
    const entryStep = pipelineForm.data.steps.find((step) => step.key === pipelineForm.data.entry_step_key) ?? pipelineForm.data.steps[0];
    const completionStep =
        pipelineForm.data.steps.find((step) => step.key === pipelineForm.data.completion_step_key) ??
        pipelineForm.data.steps[pipelineForm.data.steps.length - 1];

    const nodeTypes = useMemo(() => ({ pipelineNode: PipelineNode, actionNode: ActionNode }), []);

    const stepNodes = useMemo(
        () => {
            const nodes: Node[] = [];

            pipelineForm.data.steps.forEach((step, index) => {
                const stepId = getCanvasNodeId(step, index);
                const action = actionOptions.find((option) => option.value === step.action_type) ?? actionOptions[0];

                // Pipeline step node
                const stepPosition = savedPositions[stepId] || defaultCanvasPosition(index);

                nodes.push({
                    id: stepId,
                    type: "pipelineNode" as const,
                    position: stepPosition,
                    data: {
                        step,
                        index,
                        isEntry: pipelineForm.data.entry_step_key === step.key,
                        isCompletion: pipelineForm.data.completion_step_key === step.key,
                        actionLabel: action.label,
                        onEdit: () => {
                            setSelectedStepIndex(index);
                            setEditorTab("basic");
                            setNodeEditorOpen(true);
                        },
                        onDuplicate: () => duplicatePipelineStep(index),
                        onDelete: () => removePipelineStep(index),
                        onDisconnect: () => disconnectStep(index),
                        onSetEntry: () => pipelineForm.setData("entry_step_key", step.key),
                        onSetCompletion: () => pipelineForm.setData("completion_step_key", step.key),
                        onConnectTo: (targetIndex: number) => connectStepToNext(index, targetIndex),
                        onAddNodeAction: () => {
                            setActionPickerStepIndex(index);
                            setActionPickerOpen(true);
                        },
                        onRemoveNodeAction: (actionIndex: number) => removeNodeAction(index, actionIndex),
                        onUpdateNodeAction: (actionIndex: number, updater: (action: EnrollmentPipelineNodeAction) => EnrollmentPipelineNodeAction) =>
                            updateNodeAction(index, actionIndex, updater),
                        availableTargets: pipelineForm.data.steps.map((s, i) => ({
                            index: i,
                            label: s.label || s.status || `Step ${i + 1}`,
                        })),
                    },
                    selected: selectedStepIndex === index,
                });

                // Action nodes for this step
                (step.node_actions || []).forEach((nodeAction, actionIndex) => {
                    const actionId = `${stepId}-action-${actionIndex}`;
                    const stepPos = savedPositions[stepId] || defaultCanvasPosition(index);

                    nodes.push({
                        id: actionId,
                        type: "actionNode" as const,
                        position: savedPositions[actionId] || {
                            x: stepPos.x + 20,
                            y: stepPos.y + 150 + actionIndex * 80,
                        },
                        data: {
                            action: nodeAction,
                            actionIndex,
                            stepIndex: index,
                            stepStatus: step.status,
                            onEdit: () => openActionEditor(index, actionIndex),
                            onUpdate: (updater: (action: EnrollmentPipelineNodeAction) => EnrollmentPipelineNodeAction) =>
                                updateNodeAction(index, actionIndex, updater),
                            onRemove: () => removeNodeAction(index, actionIndex),
                        },
                    });
                });
            });

            return nodes;
        },
        [pipelineForm.data.steps, pipelineForm.data.entry_step_key, pipelineForm.data.completion_step_key, selectedStepIndex, savedPositions],
    );

    const stepEdges = useMemo(() => {
        const result: Edge[] = [];

        pipelineForm.data.steps.forEach((step, index) => {
            const stepId = getCanvasNodeId(step, index);

            const nextStepKeys = step.next_step_keys?.length ? step.next_step_keys : step.next_step_key ? [step.next_step_key] : [];

            nextStepKeys.forEach((nextStepKey, connectionIndex) => {
                const targetIndex = pipelineForm.data.steps.findIndex((s) => s.key === nextStepKey);
                if (targetIndex !== -1) {
                    const targetId = getCanvasNodeId(pipelineForm.data.steps[targetIndex], targetIndex);
                    result.push({
                        id: `${stepId}->${targetId}-${connectionIndex}`,
                        source: stepId,
                        sourceHandle: connectionIndex % 3 === 1 ? "next-top" : connectionIndex % 3 === 2 ? "next-bottom" : "next-main",
                        target: targetId,
                        targetHandle: "input-left",
                        type: "smoothstep",
                        animated: true,
                        markerEnd: { type: MarkerType.ArrowClosed },
                        style: { stroke: "var(--primary)", strokeWidth: 2, strokeDasharray: "6 6" },
                    });
                }
            });

            // Step to action edges (connect step to first action, and actions in sequence)
            const actions = step.node_actions || [];
            if (actions.length > 0) {
                // Step node → first action node
                result.push({
                    id: `${stepId}->${stepId}-action-0`,
                    source: stepId,
                    sourceHandle: "action-out",
                    target: `${stepId}-action-0`,
                    type: "smoothstep",
                    style: { stroke: "#22c55e", strokeWidth: 1.5 },
                });

                // Action → action edges
                for (let i = 1; i < actions.length; i++) {
                    result.push({
                        id: `${stepId}-action-${i - 1}->${stepId}-action-${i}`,
                        source: `${stepId}-action-${i - 1}`,
                        target: `${stepId}-action-${i}`,
                        type: "smoothstep",
                        style: { stroke: "#22c55e", strokeWidth: 1.5 },
                    });
                }
            }
        });

        return result;
    }, [pipelineForm.data.steps]);

    // Sync step data into ReactFlow state, preserving existing node positions and data
    useEffect(() => {
        setNodes((currentNodes) => {
            const currentById = new Map(currentNodes.map((n) => [n.id, n]));
            const positionById = new Map(currentNodes.map((n) => [n.id, n.position]));
            const stepPositions = new Map<string, { x: number; y: number }>();

            for (const node of currentNodes) {
                if (node.type === "pipelineNode") {
                    stepPositions.set(node.id, node.position);
                }
            }

            return stepNodes.map((node) => {
                const existing = currentById.get(node.id);

                if (node.type === "actionNode") {
                    const parentId = node.id.replace(/-action-\d+$/, "");
                    const parentPos = stepPositions.get(parentId);
                    const actionIndex = parseInt(node.id.split("-action-")[1] || "0", 10);

                    const position = existing?.position ?? positionById.get(node.id) ?? (
                        parentPos ? {
                            x: parentPos.x + 20,
                            y: parentPos.y + 150 + actionIndex * 80,
                        } : node.position
                    );

                    return {
                        ...node,
                        position,
                        data: existing ? { ...existing.data, ...node.data } : node.data,
                    };
                }

                return {
                    ...node,
                    position: positionById.get(node.id) ?? node.position,
                    data: existing ? { ...existing.data, ...node.data } : node.data,
                };
            });
        });

        setEdges(stepEdges);
    }, [stepNodes, stepEdges, setNodes, setEdges]);

    const handleNodeDragStop = useCallback(
        (_event: React.MouseEvent, node: Node) => {
            setSavedPositions((prev) => {
                const next = { ...prev, [node.id]: node.position };
                localStorage.setItem(STORAGE_KEY, JSON.stringify(next));

                return next;
            });
        },
        [],
    );

    const connectStepToNext = (sourceIndex: number, targetIndex: number) => {
        if (sourceIndex === targetIndex) {
            return;
        }

        const targetStep = pipelineForm.data.steps[targetIndex];
        if (!targetStep) {
            return;
        }

        pipelineForm.setData(
            "steps",
            pipelineForm.data.steps.map((step, index) => {
                if (index !== sourceIndex) {
                    return step;
                }

                const nextStepKeys = step.next_step_keys?.length ? step.next_step_keys : step.next_step_key ? [step.next_step_key] : [];
                const mergedNextStepKeys = nextStepKeys.includes(targetStep.key) ? nextStepKeys : [...nextStepKeys, targetStep.key];

                return {
                    ...step,
                    next_step_key: mergedNextStepKeys[0] ?? null,
                    next_step_keys: mergedNextStepKeys,
                };
            }),
        );
    };

    const handleConnect = useCallback(
        (connection: Connection) => {
            if (connection.sourceHandle === "action-out") {
                return;
            }

            const sourceIndex = pipelineForm.data.steps.findIndex((step, i) => getCanvasNodeId(step, i) === connection.source);
            const targetIndex = pipelineForm.data.steps.findIndex((step, i) => getCanvasNodeId(step, i) === connection.target);

            if (sourceIndex !== -1 && targetIndex !== -1 && sourceIndex !== targetIndex) {
                setEdges((current) =>
                    addEdge(
                        { ...connection, type: "smoothstep", markerEnd: { type: MarkerType.ArrowClosed }, animated: true },
                        current,
                    ),
                );
                connectStepToNext(sourceIndex, targetIndex);
            }
        },
        [connectStepToNext, pipelineForm.data.steps, setEdges],
    );

    const roleComboboxOptions: ComboboxOption[] = useMemo(
        () =>
            available_roles.map((roleName) => ({
                value: roleName,
                label: roleName,
                searchText: roleName,
            })),
        [available_roles],
    );

    const filteredEnrollmentCourses = useMemo(() => {
        const search = enrollmentCourseSearch.trim().toLowerCase();

        if (search.length === 0) {
            return available_enrollment_courses ?? [];
        }

        return (available_enrollment_courses ?? []).filter((course) => `${course.code} ${course.title}`.toLowerCase().includes(search));
    }, [available_enrollment_courses, enrollmentCourseSearch]);

    const selectedEnrollmentCourses = useMemo(() => {
        const selectedIds = new Set(pipelineForm.data.enrollment_courses.map((courseId) => Number(courseId)));

        return (available_enrollment_courses ?? []).filter((course) => selectedIds.has(course.id));
    }, [available_enrollment_courses, pipelineForm.data.enrollment_courses]);

    const updatePipelineStep = (index: number, updater: (step: EnrollmentPipelineStep) => EnrollmentPipelineStep) => {
        const currentStep = pipelineForm.data.steps[index];
        if (!currentStep) {
            return;
        }

        const updatedStep = updater(currentStep);
        const steps = pipelineForm.data.steps.map((step, stepIndex) => (stepIndex === index ? updatedStep : step));

        pipelineForm.setData("steps", steps);

        if (updatedStep.key !== currentStep.key) {
            if (pipelineForm.data.entry_step_key === currentStep.key) {
                pipelineForm.setData("entry_step_key", updatedStep.key);
            }

            if (pipelineForm.data.completion_step_key === currentStep.key) {
                pipelineForm.setData("completion_step_key", updatedStep.key);
            }
        }
    };

    const setStepField = (index: number, field: keyof EnrollmentPipelineStep, value: string | string[]) => {
        updatePipelineStep(index, (step) => ({
            ...step,
            [field]: value,
        }));
    };

    const setStepAction = (index: number, actionType: EnrollmentPipelineActionType) => {
        updatePipelineStep(index, (step) => ({
            ...step,
            action_type: actionType,
            actions: actionsForActionType(actionType),
            node_actions: nodeActionsForActionType(actionType),
        }));
    };

    const updateNodeAction = (stepIndex: number, actionIndex: number, updater: (action: EnrollmentPipelineNodeAction) => EnrollmentPipelineNodeAction) => {
        updatePipelineStep(stepIndex, (step) => ({
            ...step,
            node_actions: (step.node_actions || []).map((action, index) => (index === actionIndex ? updater(action) : action)),
        }));
    };

    const openActionEditor = (stepIndex: number, actionIndex: number) => {
        setEditingAction({ stepIndex, actionIndex });
        setActionEditorOpen(true);
    };

    const addActionCondition = (stepIndex: number, actionIndex: number) => {
        updateNodeAction(stepIndex, actionIndex, (action) => ({
            ...action,
            conditions: [...(action.conditions || []), defaultActionCondition()],
        }));
    };

    const updateActionCondition = (
        stepIndex: number,
        actionIndex: number,
        conditionIndex: number,
        updater: (condition: ActionCondition) => ActionCondition,
    ) => {
        updateNodeAction(stepIndex, actionIndex, (action) => ({
            ...action,
            conditions: (action.conditions || []).map((condition, index) => (index === conditionIndex ? updater(condition) : condition)),
        }));
    };

    const removeActionCondition = (stepIndex: number, actionIndex: number, conditionIndex: number) => {
        updateNodeAction(stepIndex, actionIndex, (action) => ({
            ...action,
            conditions: (action.conditions || []).filter((_, index) => index !== conditionIndex),
        }));
    };

    const addNodeAction = (stepIndex: number, actionType?: EnrollmentPipelineNodeActionType) => {
        updatePipelineStep(stepIndex, (step) => ({
            ...step,
            node_actions: [
                ...(step.node_actions || []),
                {
                    key: `action_${(step.node_actions || []).length + 1}`,
                    type: actionType || "change_status",
                    enabled: true,
                    order: (step.node_actions || []).length + 1,
                    config: {},
                    halt_on_failure: true,
                },
            ],
        }));
    };

    const removeNodeAction = (stepIndex: number, actionIndex: number) => {
        updatePipelineStep(stepIndex, (step) => ({
            ...step,
            node_actions: (step.node_actions || []).filter((_, index) => index !== actionIndex),
        }));
    };

    const toggleCompleteProfileCondition = (stepIndex: number, enabled: boolean) => {
        updatePipelineStep(stepIndex, (step) => {
            const conditions = step.node_conditions || [];

            if (!enabled) {
                return {
                    ...step,
                    node_conditions: conditions.filter((condition) => condition.type !== "complete_student_profile"),
                };
            }

            if (conditions.some((condition) => condition.type === "complete_student_profile")) {
                return step;
            }

            return {
                ...step,
                node_conditions: [
                    ...conditions,
                    {
                        key: "complete_student_profile",
                        type: "complete_student_profile",
                        enabled: true,
                        order: conditions.length + 1,
                        config: { required_fields: profileFieldOptions.map((field) => field.value) },
                        message: "Complete the student profile before continuing.",
                    },
                ],
            };
        });
    };

    const toggleProfileRequiredField = (stepIndex: number, field: string, checked: boolean) => {
        updatePipelineStep(stepIndex, (step) => ({
            ...step,
            node_conditions: (step.node_conditions || []).map((condition) => {
                if (condition.type !== "complete_student_profile") {
                    return condition;
                }

                const fields = condition.config.required_fields || [];
                const required_fields = checked ? [...new Set([...fields, field])] : fields.filter((currentField) => currentField !== field);

                return {
                    ...condition,
                    config: {
                        ...condition.config,
                        required_fields,
                    },
                };
            }),
        }));
    };

    const addPipelineStep = () => {
        const nextIndex = pipelineForm.data.steps.length + 1;
        const step = createDefaultStep(nextIndex);
        const previousStep = pipelineForm.data.steps[pipelineForm.data.steps.length - 1];
        const steps = previousStep
            ? pipelineForm.data.steps.map((currentStep, index) =>
                  index === pipelineForm.data.steps.length - 1
                      ? {
                            ...currentStep,
                            next_step_key: currentStep.next_step_key ?? step.key,
                            next_step_keys: Array.from(new Set([...(currentStep.next_step_keys || []), step.key])),
                        }
                      : currentStep,
              )
            : pipelineForm.data.steps;
        const nextSteps = [...steps, step];


        pipelineForm.setData("steps", nextSteps);
        pipelineForm.setData("completion_step_key", step.key);

        if (!pipelineForm.data.entry_step_key) {
            pipelineForm.setData("entry_step_key", step.key);
        }

        setSelectedStepIndex(nextSteps.length - 1);
    };

    const removePipelineStep = (index: number) => {
        const removedStep = pipelineForm.data.steps[index];
        const steps = pipelineForm.data.steps
            .filter((_, stepIndex) => stepIndex !== index)
            .map((step) => {
                const nextStepKeys = (step.next_step_keys || []).filter((nextStepKey) => nextStepKey !== removedStep?.key);

                return {
                    ...step,
                    next_step_key: step.next_step_key === removedStep?.key ? (nextStepKeys[0] ?? null) : step.next_step_key,
                    next_step_keys: nextStepKeys,
                };
            });

        pipelineForm.setData("steps", steps);

        if (removedStep?.key === pipelineForm.data.entry_step_key) {
            pipelineForm.setData("entry_step_key", steps[0]?.key || "");
        }

        if (removedStep?.key === pipelineForm.data.completion_step_key) {
            pipelineForm.setData("completion_step_key", steps[steps.length - 1]?.key || "");
        }

        if (selectedStepIndex === index) {
            setSelectedStepIndex(steps.length > 0 ? Math.max(0, index - 1) : null);
        } else if (selectedStepIndex !== null && selectedStepIndex > index) {
            setSelectedStepIndex(selectedStepIndex - 1);
        }
    };

    const movePipelineStep = (index: number, direction: "up" | "down") => {
        const targetIndex = direction === "up" ? index - 1 : index + 1;
        if (targetIndex < 0 || targetIndex >= pipelineForm.data.steps.length) {
            return;
        }

        const reordered = [...pipelineForm.data.steps];
        const [moved] = reordered.splice(index, 1);
        reordered.splice(targetIndex, 0, moved);
        pipelineForm.setData("steps", reordered);

        if (selectedStepIndex === index) {
            setSelectedStepIndex(targetIndex);
        } else if (selectedStepIndex === targetIndex) {
            setSelectedStepIndex(index);
        }
    };

    const disconnectStep = (sourceIndex: number) => {
        pipelineForm.setData(
            "steps",
            pipelineForm.data.steps.map((step, index) => (index === sourceIndex ? { ...step, next_step_key: null, next_step_keys: [] } : step)),
        );
    };

    const organizeCanvas = () => {
        const visited = new Set<string>();
        const orderedIndexes: number[] = [];
        let currentStep: EnrollmentPipelineStep | undefined =
            pipelineForm.data.steps.find((step) => step.key === pipelineForm.data.entry_step_key) ?? pipelineForm.data.steps[0];

        while (currentStep && !visited.has(currentStep.key)) {
            const currentIndex = pipelineForm.data.steps.findIndex((step) => step.key === currentStep?.key);
            if (currentIndex === -1) {
                break;
            }

            visited.add(currentStep.key);
            orderedIndexes.push(currentIndex);
            const nextStepKey = currentStep.next_step_keys?.[0] ?? currentStep.next_step_key;
            currentStep = pipelineForm.data.steps.find((step) => step.key === nextStepKey);
        }

        pipelineForm.data.steps.forEach((step, index) => {
            if (!visited.has(step.key)) {
                orderedIndexes.push(index);
            }
        });

        setNodes((current) =>
            current.map((node) => {
                const visualIndex = orderedIndexes.findIndex(
                    (si) => getCanvasNodeId(pipelineForm.data.steps[si], si) === node.id,
                );

                if (visualIndex !== -1) {
                    return { ...node, position: defaultCanvasPosition(visualIndex) };
                }

                return node;
            }),
        );
    };

    const duplicatePipelineStep = (index: number) => {
        const step = pipelineForm.data.steps[index];
        if (!step) {
            return;
        }

        const nextIndex = pipelineForm.data.steps.length + 1;
        const duplicatedStep: EnrollmentPipelineStep = {
            ...step,
            key: `${step.key}_copy_${nextIndex}`,
            status: `${step.status}_copy_${nextIndex}`,
            label: `${step.label} Copy`,
            node_actions: (step.node_actions || []).map((action) => ({ ...action, config: { ...action.config } })),
            node_conditions: (step.node_conditions || []).map((condition) => ({
                ...condition,
                config: { ...condition.config, required_fields: [...(condition.config.required_fields || [])] },
            })),
            next_step_key: null,
            next_step_keys: [],
        };
        const nextSteps = [...pipelineForm.data.steps, duplicatedStep];

        pipelineForm.setData("steps", nextSteps);
        setSelectedStepIndex(nextSteps.length - 1);
    };

    const addRoleToStep = (index: number) => {
        const selectedRole = selectedRoleByStep[index];
        if (!selectedRole) {
            return;
        }

        const roles = pipelineForm.data.steps[index]?.allowed_roles || [];
        if (roles.includes(selectedRole)) {
            return;
        }

        setStepField(index, "allowed_roles", [...roles, selectedRole]);
        setSelectedRoleByStep((current) => ({ ...current, [index]: "" }));
    };

    const removeRoleFromStep = (index: number, roleName: string) => {
        const roles = pipelineForm.data.steps[index]?.allowed_roles || [];
        setStepField(
            index,
            "allowed_roles",
            roles.filter((role) => role !== roleName),
        );
    };

    const toggleEnrollmentCourse = (courseId: number, checked: boolean) => {
        const normalizedIds = pipelineForm.data.enrollment_courses.map((id) => Number(id));

        if (checked && !normalizedIds.includes(courseId)) {
            pipelineForm.setData("enrollment_courses", [...normalizedIds, courseId]);

            return;
        }

        if (!checked) {
            pipelineForm.setData(
                "enrollment_courses",
                normalizedIds.filter((selectedId) => selectedId !== courseId),
            );
        }
    };

    const selectAllEnrollmentCourses = () => {
        pipelineForm.setData(
            "enrollment_courses",
            (available_enrollment_courses ?? []).map((course) => course.id),
        );
    };

    const updateStatsCard = (index: number, field: keyof EnrollmentStatsCard, value: string | string[]) => {
        const cards = pipelineForm.data.enrollment_stats.cards.map((card, cardIndex) => {
            if (cardIndex !== index) {
                return card;
            }

            return {
                ...card,
                [field]: value,
            };
        });

        pipelineForm.setData("enrollment_stats", { cards });
    };

    const addStatsCard = () => {
        const nextIndex = pipelineForm.data.enrollment_stats.cards.length + 1;
        pipelineForm.setData("enrollment_stats", {
            cards: [
                ...pipelineForm.data.enrollment_stats.cards,
                {
                    key: `stat_${nextIndex}`,
                    label: `Metric ${nextIndex}`,
                    metric: "total_records",
                    statuses: [],
                    color: "blue",
                },
            ],
        });
    };

    const removeStatsCard = (index: number) => {
        pipelineForm.setData("enrollment_stats", {
            cards: pipelineForm.data.enrollment_stats.cards.filter((_, cardIndex) => cardIndex !== index),
        });
    };

    const toggleStatsCardStatus = (index: number, statusValue: string) => {
        const statuses = pipelineForm.data.enrollment_stats.cards[index]?.statuses || [];
        const nextStatuses = statuses.includes(statusValue) ? statuses.filter((status) => status !== statusValue) : [...statuses, statusValue];

        updateStatsCard(index, "statuses", nextStatuses);
    };

    const submit = () => {
        submitSystemForm({
            form: pipelineForm,
            routeName: "administrators.system-management.enrollment-pipeline.update",
            successMessage: "Enrollment pipeline updated successfully.",
            errorMessage: "Failed to update enrollment pipeline.",
        });
    };

    const editorTabs = [
        { id: "basic", label: "Basic Info" },
        { id: "actions", label: "Actions" },
        { id: "conditions", label: "Conditions" },
        { id: "connections", label: "Connections" },
        { id: "permissions", label: "Permissions" },
        { id: "advanced", label: "Advanced" },
    ];

    return (
        <SystemManagementLayout
            user={user}
            access={access}
            activeSection="pipeline"
            heading="Enrollment Pipeline"
            description="Create the approval path new applicants follow, and choose what each approval step executes."
        >
            <div className="space-y-6">
                <Card className="border-primary/15 bg-primary/5 overflow-hidden shadow-none">
                    <CardContent className="flex flex-col gap-5 p-6 lg:flex-row lg:items-center lg:justify-between">
                        <div className="space-y-2">
                            <div className="text-primary flex items-center gap-2 text-sm font-medium">
                                <Sparkles className="h-4 w-4" /> Guided setup
                            </div>
                            <div>
                                <h2 className="text-2xl font-semibold tracking-tight">Build a focused enrollment approval workflow</h2>
                                <p className="text-muted-foreground mt-1 max-w-3xl text-sm">
                                    Existing status codes are kept intact. New steps can be added, reordered, and assigned an action without changing
                                    the rest of enrollment management.
                                </p>
                            </div>
                        </div>
                        <Button onClick={submit} disabled={pipelineForm.processing} size="lg" className="shrink-0 shadow-sm">
                            {pipelineForm.processing ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <Save className="mr-2 h-4 w-4" />}
                            Save Pipeline
                        </Button>
                    </CardContent>
                </Card>

                <div className="grid gap-4 md:grid-cols-3">
                    <PipelineSummaryCard icon={Workflow} label="Workflow steps" value={pipelineForm.data.steps.length.toString()} />
                    <PipelineSummaryCard
                        icon={Users}
                        label="Visible courses"
                        value={selectedEnrollmentCourses.length > 0 ? selectedEnrollmentCourses.length.toString() : "All"}
                    />
                    <PipelineSummaryCard icon={CheckCircle2} label="Completion" value={completionStep?.label || "Not set"} />
                </div>

                <Tabs defaultValue="workflow" className="space-y-6">
                    <TabsList className="grid w-full max-w-3xl grid-cols-4">
                        <TabsTrigger value="workflow" className="gap-2">
                            <Workflow className="h-4 w-4" /> Workflow
                        </TabsTrigger>
                        <TabsTrigger value="basics" className="gap-2">
                            <Settings2 className="h-4 w-4" /> Basics
                        </TabsTrigger>
                        <TabsTrigger value="automation" className="gap-2">
                            <GraduationCap className="h-4 w-4" /> Automation
                        </TabsTrigger>
                        <TabsTrigger value="analytics" className="gap-2">
                            <BarChart3 className="h-4 w-4" /> Analytics
                        </TabsTrigger>
                    </TabsList>

                    <TabsContent value="workflow" className="space-y-6 outline-none">
                        <Card className="shadow-none">
                            <CardHeader>
                                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                    <div>
                                        <div className="flex items-center gap-2">
                                            <Workflow className="text-primary h-5 w-5" />
                                            <CardTitle>Approval flow</CardTitle>
                                        </div>
                                        <CardDescription>
                                            Drag nodes around the canvas, click to edit, and connect the next step by dragging between node handles.
                                        </CardDescription>
                                    </div>
                                    <div className="flex gap-2">
                                        <Button type="button" variant="outline" onClick={organizeCanvas} size="sm">
                                            <Target className="mr-2 h-4 w-4" /> Organize
                                        </Button>
                                        <Button type="button" onClick={addPipelineStep} size="sm">
                                            <Plus className="mr-2 h-4 w-4" /> Add step
                                        </Button>
                                    </div>
                                </div>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="bg-muted/30 text-muted-foreground flex flex-col gap-3 rounded-lg border px-3 py-2 text-xs sm:flex-row sm:items-center sm:justify-between">
                                    <div className="flex items-center gap-2">
                                        <MousePointerClick className="h-4 w-4" />
                                        Drag nodes, click to select, right-click for options, or drag node handles to connect the workflow.
                                    </div>
                                </div>

                                {pipelineForm.data.steps.length === 0 ? (
                                    <div className="rounded-xl border border-dashed p-8 text-center">
                                        <Workflow className="text-muted-foreground mx-auto mb-3 h-8 w-8" />
                                        <p className="font-medium">No approval steps yet</p>
                                        <p className="text-muted-foreground mt-1 text-sm">Add your first step to start the pipeline.</p>
                                    </div>
                                ) : (
                                    <div className="react-flow-dark bg-muted/30 h-[720px] overflow-hidden rounded-2xl border">
                                        <ReactFlow
                                            nodes={nodes}
                                            edges={edges}
                                            onNodesChange={onNodesChange}
                                            onEdgesChange={onEdgesChange}
                                            onNodeClick={(_, node) => {
                                                const stepIndex = typeof node.data.index === "number" ? node.data.index : node.data.stepIndex;

                                                if (typeof stepIndex === "number") {
                                                    setSelectedStepIndex(stepIndex);
                                                }
                                            }}
                                            onPaneClick={() => setSelectedStepIndex(null)}
                                            onConnect={handleConnect}
                                            onNodeDragStop={handleNodeDragStop}
                                            nodeTypes={nodeTypes}
                                            fitView
                                            fitViewOptions={{ padding: 0.2 }}
                                            minZoom={0.2}
                                            maxZoom={4}
                                        >
                                            <Background variant={BackgroundVariant.Dots} gap={28} size={1} color="var(--border)" />
                                            <Controls />
                                        </ReactFlow>
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </TabsContent>

                    <TabsContent value="basics" className="space-y-6 outline-none">
                        <Card className="shadow-none">
                            <CardHeader>
                                <div className="flex items-center gap-2">
                                    <Settings2 className="text-primary h-5 w-5" />
                                    <CardTitle>Basic enrollment settings</CardTitle>
                                </div>
                                <CardDescription>Configure only the applicant-facing entry settings when you need them.</CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-5">
                                <div className="grid gap-4 md:grid-cols-2">
                                    <div className="space-y-2">
                                        <Label htmlFor="submitted_label_tab">Initial submitted label</Label>
                                        <Input
                                            id="submitted_label_tab"
                                            value={pipelineForm.data.submitted_label}
                                            onChange={(event) => pipelineForm.setData("submitted_label", event.target.value)}
                                            placeholder="Submitted"
                                        />
                                        <FieldError message={formErrors.submitted_label} />
                                    </div>
                                    <div className="space-y-2">
                                        <Label>Entry and completion</Label>
                                        <div className="bg-muted/30 rounded-md border px-3 py-2 text-sm">
                                            <span className="font-medium">{entryStep?.label || "No entry step"}</span>
                                            <span className="text-muted-foreground"> → </span>
                                            <span className="font-medium">{completionStep?.label || "No completion step"}</span>
                                        </div>
                                        <p className="text-muted-foreground text-xs">Set these from the selected workflow node.</p>
                                    </div>
                                </div>

                                <Separator />

                                <div className="space-y-3">
                                    <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                                        <div className="space-y-1">
                                            <Label htmlFor="course_search_tab">Courses shown to new applicants</Label>
                                            <p className="text-muted-foreground text-xs">Leave empty to show every active course.</p>
                                        </div>
                                        <div className="flex gap-2">
                                            <Button type="button" variant="outline" size="sm" onClick={selectAllEnrollmentCourses}>
                                                Select all
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => pipelineForm.setData("enrollment_courses", [])}
                                            >
                                                Clear
                                            </Button>
                                        </div>
                                    </div>
                                    <Input
                                        id="course_search_tab"
                                        value={enrollmentCourseSearch}
                                        onChange={(event) => setEnrollmentCourseSearch(event.target.value)}
                                        placeholder="Search by code or course title..."
                                    />
                                    <div className="max-h-72 space-y-1 overflow-y-auto rounded-lg border p-2">
                                        {filteredEnrollmentCourses.length === 0 ? (
                                            <p className="text-muted-foreground py-6 text-center text-sm">No matching courses found.</p>
                                        ) : (
                                            filteredEnrollmentCourses.map((course) => (
                                                <label
                                                    key={course.id}
                                                    className="hover:bg-muted/60 flex cursor-pointer items-center gap-3 rounded-md px-2 py-2"
                                                >
                                                    <Checkbox
                                                        checked={pipelineForm.data.enrollment_courses.includes(course.id)}
                                                        onCheckedChange={(value) => toggleEnrollmentCourse(course.id, value === true)}
                                                    />
                                                    <span className="min-w-0">
                                                        <span className="block truncate text-sm font-medium">{course.title}</span>
                                                        <span className="text-muted-foreground block font-mono text-xs">{course.code}</span>
                                                    </span>
                                                </label>
                                            ))
                                        )}
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </TabsContent>

                    <TabsContent value="automation" className="space-y-6 outline-none">
                        <Card className="shadow-none">
                            <CardHeader>
                                <div className="flex items-center gap-2">
                                    <GraduationCap className="text-primary h-5 w-5" />
                                    <CardTitle>Applicant automation</CardTitle>
                                </div>
                                <CardDescription>Optional actions that run when a new applicant is submitted.</CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                <AutomationSwitch
                                    id="default_new_applicant_to_first_year_tab"
                                    title="Treat new applicants as 1st year automatically"
                                    description="Required before auto-creating enrollment records or assigning subjects."
                                    checked={pipelineForm.data.automation.default_new_applicant_to_first_year}
                                    onCheckedChange={(enabled) =>
                                        pipelineForm.setData("automation", {
                                            ...pipelineForm.data.automation,
                                            default_new_applicant_to_first_year: enabled,
                                            auto_create_student_enrollment: enabled
                                                ? pipelineForm.data.automation.auto_create_student_enrollment
                                                : false,
                                            auto_assign_subjects: enabled ? pipelineForm.data.automation.auto_assign_subjects : false,
                                        })
                                    }
                                />
                                <AutomationSwitch
                                    id="auto_create_student_enrollment_tab"
                                    title="Auto-create Student Enrollment record"
                                    description="Creates an enrollment record immediately for every new applicant."
                                    checked={pipelineForm.data.automation.auto_create_student_enrollment}
                                    disabled={!pipelineForm.data.automation.default_new_applicant_to_first_year}
                                    onCheckedChange={(enabled) =>
                                        pipelineForm.setData("automation", {
                                            ...pipelineForm.data.automation,
                                            auto_create_student_enrollment: enabled,
                                            auto_assign_subjects: enabled ? pipelineForm.data.automation.auto_assign_subjects : false,
                                        })
                                    }
                                />
                                <AutomationSwitch
                                    id="auto_assign_subjects_tab"
                                    title="Auto-assign available subjects/classes"
                                    description="Uses the applicant course and active academic period to assign available classes."
                                    checked={pipelineForm.data.automation.auto_assign_subjects}
                                    disabled={
                                        !pipelineForm.data.automation.default_new_applicant_to_first_year ||
                                        !pipelineForm.data.automation.auto_create_student_enrollment
                                    }
                                    onCheckedChange={(enabled) =>
                                        pipelineForm.setData("automation", {
                                            ...pipelineForm.data.automation,
                                            auto_assign_subjects: enabled,
                                        })
                                    }
                                />
                            </CardContent>
                        </Card>
                    </TabsContent>

                    <TabsContent value="analytics" className="space-y-6 outline-none">
                        <Card className="shadow-none">
                            <CardHeader>
                                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                    <div>
                                        <CardTitle>Enrollment analytics cards</CardTitle>
                                        <CardDescription>Configure the summary cards shown in enrollment dashboards.</CardDescription>
                                    </div>
                                    <Button type="button" onClick={addStatsCard}>
                                        <Plus className="mr-2 h-4 w-4" /> Add card
                                    </Button>
                                </div>
                            </CardHeader>
                            <CardContent>
                                {pipelineForm.data.enrollment_stats.cards.length === 0 ? (
                                    <div className="rounded-xl border border-dashed p-10 text-center">
                                        <BarChart3 className="text-muted-foreground mx-auto mb-3 h-9 w-9" />
                                        <p className="font-medium">No analytics cards configured</p>
                                        <p className="text-muted-foreground mt-1 text-sm">Add a card to show totals or counts by pipeline status.</p>
                                    </div>
                                ) : (
                                    <div className="grid gap-4 lg:grid-cols-2">
                                        {pipelineForm.data.enrollment_stats.cards.map((card, index) => (
                                            <Card key={`${card.key}-${index}`} className="shadow-none">
                                                <CardHeader className="pb-3">
                                                    <div className="flex items-start justify-between gap-3">
                                                        <div className="flex-1 space-y-2">
                                                            <Input
                                                                value={card.label}
                                                                onChange={(event) => updateStatsCard(index, "label", event.target.value)}
                                                                placeholder="Card title"
                                                                className="text-base font-semibold"
                                                            />
                                                            <FieldError message={formErrors[`enrollment_stats.cards.${index}.label`]} />
                                                        </div>
                                                        <Button
                                                            type="button"
                                                            variant="ghost"
                                                            size="icon"
                                                            className="text-destructive hover:bg-destructive/10 hover:text-destructive"
                                                            onClick={() => removeStatsCard(index)}
                                                        >
                                                            <Trash2 className="h-4 w-4" />
                                                        </Button>
                                                    </div>
                                                </CardHeader>
                                                <CardContent className="space-y-4">
                                                    <div className="grid gap-4 sm:grid-cols-3">
                                                        <div className="space-y-2 sm:col-span-2">
                                                            <Label>Metric</Label>
                                                            <Select
                                                                value={card.metric}
                                                                onValueChange={(value) => updateStatsCard(index, "metric", value)}
                                                            >
                                                                <SelectTrigger>
                                                                    <SelectValue />
                                                                </SelectTrigger>
                                                                <SelectContent>
                                                                    {statsMetricOptions.map((metric) => (
                                                                        <SelectItem key={metric.value} value={metric.value}>
                                                                            {metric.label}
                                                                        </SelectItem>
                                                                    ))}
                                                                </SelectContent>
                                                            </Select>
                                                        </div>
                                                        <div className="space-y-2">
                                                            <Label>Color</Label>
                                                            <Select
                                                                value={card.color}
                                                                onValueChange={(value) => updateStatsCard(index, "color", value)}
                                                            >
                                                                <SelectTrigger>
                                                                    <SelectValue />
                                                                </SelectTrigger>
                                                                <SelectContent>
                                                                    {colorOptions.map((color) => (
                                                                        <SelectItem key={color} value={color} className="capitalize">
                                                                            {color}
                                                                        </SelectItem>
                                                                    ))}
                                                                </SelectContent>
                                                            </Select>
                                                        </div>
                                                    </div>
                                                    <div className="space-y-2">
                                                        <Label>Card key</Label>
                                                        <Input
                                                            value={card.key}
                                                            onChange={(event) =>
                                                                updateStatsCard(index, "key", slugify(event.target.value, `stat_${index + 1}`))
                                                            }
                                                            className="font-mono"
                                                        />
                                                    </div>
                                                    {card.metric === "status_count" && (
                                                        <div className="space-y-2">
                                                            <Label>Count these statuses</Label>
                                                            <div className="flex flex-wrap gap-2">
                                                                {pipelineForm.data.steps.map((step, stepIndex) => {
                                                                    const selected = card.statuses.includes(step.status);

                                                                    return (
                                                                        <button
                                                                            key={`${step.status}-${stepIndex}`}
                                                                            type="button"
                                                                            onClick={() => toggleStatsCardStatus(index, step.status)}
                                                                            className={`rounded-md border px-3 py-1.5 text-xs transition ${
                                                                                selected
                                                                                    ? "border-primary bg-primary text-primary-foreground"
                                                                                    : "bg-background hover:bg-muted"
                                                                            }`}
                                                                        >
                                                                            {step.label || step.status}
                                                                        </button>
                                                                    );
                                                                })}
                                                            </div>
                                                        </div>
                                                    )}
                                                </CardContent>
                                            </Card>
                                        ))}
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </TabsContent>
                </Tabs>

                <Dialog open={nodeEditorOpen} onOpenChange={setNodeEditorOpen}>
                    <DialogContent className="min-w-[80rem] h-[92vh] p-0 overflow-hidden flex flex-col">
                        <DialogHeader className="px-6 pt-6 pb-2 shrink-0">
                            <DialogTitle>Edit Node</DialogTitle>
                            {selectedStep && (
                                <DialogDescription>
                                    Editing {selectedStep.label || `Step ${(selectedStepIndex ?? 0) + 1}`}
                                </DialogDescription>
                            )}
                        </DialogHeader>
                        {selectedStep && selectedStepIndex !== null && (
                            <div className="grid grid-cols-[200px_1fr] flex-1 overflow-hidden">
                                <div className="border-r p-4 space-y-1 bg-muted/20 overflow-y-auto">
                                    {editorTabs.map((tab) => (
                                        <button
                                            key={tab.id}
                                            type="button"
                                            onClick={() => setEditorTab(tab.id)}
                                            className={`w-full text-left rounded-md px-3 py-2 text-sm transition ${
                                                editorTab === tab.id
                                                    ? "bg-primary text-primary-foreground font-medium"
                                                    : "hover:bg-muted"
                                            }`}
                                        >
                                            {tab.label}
                                        </button>
                                    ))}
                                </div>
                                <div className="overflow-y-auto p-6 space-y-6">
                                    {editorTab === "basic" && (
                                        <>
                                            <div className="bg-muted/20 rounded-2xl border p-4">
                                                <div className="flex items-start gap-3">
                                                    <div
                                                        className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl text-sm font-semibold text-white shadow-sm"
                                                        style={{
                                                            backgroundColor: colorHex[selectedStep.color as keyof typeof colorHex] ?? colorHex.gray,
                                                        }}
                                                    >
                                                        {Number(selectedStepIndex) + 1}
                                                    </div>
                                                    <div className="min-w-0 flex-1">
                                                        <div className="flex flex-wrap items-center gap-2">
                                                            <p className="truncate font-semibold">
                                                                {selectedStep.label || `Step ${Number(selectedStepIndex) + 1}`}
                                                            </p>
                                                            {pipelineForm.data.entry_step_key === selectedStep.key && <Badge>Entry</Badge>}
                                                            {pipelineForm.data.completion_step_key === selectedStep.key && (
                                                                <Badge variant="secondary">Completion</Badge>
                                                            )}
                                                        </div>
                                                        <p className="text-muted-foreground mt-1 truncate font-mono text-xs">{selectedStep.status}</p>
                                                        <div className="mt-3 grid gap-2 text-xs">
                                                            <div className="bg-background rounded-lg border px-3 py-2">
                                                                <span className="text-muted-foreground">Next:</span>{" "}
                                                                <span className="font-medium">
                                                                    {selectedNextSteps.length > 0
                                                                        ? selectedNextSteps.map((step) => step.label).join(", ")
                                                                        : "Not connected"}
                                                                </span>
                                                            </div>
                                                            <div className="bg-background rounded-lg border px-3 py-2">
                                                                <span className="text-muted-foreground">Incoming:</span>{" "}
                                                                <span className="font-medium">
                                                                    {selectedIncomingSteps.length > 0
                                                                        ? selectedIncomingSteps.map((step) => step.label).join(", ")
                                                                        : "None"}
                                                                </span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div className="grid gap-4 md:grid-cols-2">
                                                <div className="space-y-2 md:col-span-2">
                                                    <Label>Step name</Label>
                                                    <Input
                                                        value={selectedStep.label}
                                                        onChange={(event) =>
                                                            selectedStepIndex !== null && setStepField(selectedStepIndex, "label", event.target.value)
                                                        }
                                                        placeholder="Department Review"
                                                    />
                                                    <FieldError message={formErrors[`steps.${selectedStepIndex}.label`]} />
                                                </div>
                                                <div className="space-y-2">
                                                    <Label>Badge color</Label>
                                                    <Select
                                                        value={selectedStep.color}
                                                        onValueChange={(value) =>
                                                            selectedStepIndex !== null && setStepField(selectedStepIndex, "color", value)
                                                        }
                                                    >
                                                        <SelectTrigger>
                                                            <SelectValue />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            {colorOptions.map((color) => (
                                                                <SelectItem key={color} value={color}>
                                                                    <span className="flex items-center gap-2 capitalize">
                                                                        <span
                                                                            className="h-3 w-3 rounded-full"
                                                                            style={{ backgroundColor: colorHex[color] }}
                                                                        />
                                                                        {color}
                                                                    </span>
                                                                </SelectItem>
                                                            ))}
                                                        </SelectContent>
                                                    </Select>
                                                </div>
                                                <div className="space-y-2">
                                                    <Label>Internal status code</Label>
                                                    <Input
                                                        value={selectedStep.status}
                                                        onChange={(event) =>
                                                            selectedStepIndex !== null &&
                                                            setStepField(selectedStepIndex, "status", slugify(event.target.value, "step_status"))
                                                        }
                                                        className="font-mono"
                                                        placeholder="department_review"
                                                    />
                                                    <p className="text-muted-foreground text-xs">
                                                        Avoid changing this for active statuses unless you are intentionally migrating records.
                                                    </p>
                                                    <FieldError message={formErrors[`steps.${selectedStepIndex}.status`]} />
                                                </div>
                                            </div>

                                            <div className="space-y-3">
                                                <div>
                                                    <Label className="text-base font-semibold">Legacy action shortcut</Label>
                                                    <p className="text-muted-foreground mt-1 text-sm">
                                                        Pick a preset to quickly replace this node&apos;s structured actions.
                                                    </p>
                                                </div>
                                                <div className="grid gap-3">
                                                    {actionOptions.map((option) => {
                                                        const Icon = option.icon;
                                                        const selected = selectedStep.action_type === option.value;

                                                        return (
                                                            <button
                                                                key={option.value}
                                                                type="button"
                                                                onClick={() =>
                                                                    selectedStepIndex !== null && setStepAction(selectedStepIndex, option.value)
                                                                }
                                                                className={`rounded-xl border p-4 text-left transition ${
                                                                    selected
                                                                        ? "border-primary bg-primary/5 ring-primary/20 ring-2"
                                                                        : "hover:border-primary/40 hover:bg-muted/40"
                                                                }`}
                                                            >
                                                                <span className="flex gap-3">
                                                                    <span className="bg-primary/10 text-primary mt-0.5 rounded-lg p-2">
                                                                        <Icon className="h-4 w-4" />
                                                                    </span>
                                                                    <span>
                                                                        <span className="block font-medium">{option.label}</span>
                                                                        <span className="text-muted-foreground mt-1 block text-sm">
                                                                            {option.description}
                                                                        </span>
                                                                    </span>
                                                                </span>
                                                            </button>
                                                        );
                                                    })}
                                                </div>
                                            </div>
                                        </>
                                    )}

                                    {editorTab === "actions" && (
                                        <div className="bg-muted/20 space-y-4 rounded-xl border p-4">
                                            <div className="flex items-start justify-between gap-3">
                                                <div>
                                                    <Label className="text-base font-semibold">Node actions</Label>
                                                    <p className="text-muted-foreground mt-1 text-sm">
                                                        These run in order after this node&apos;s conditions pass.
                                                    </p>
                                                </div>
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={() => selectedStepIndex !== null && addNodeAction(selectedStepIndex)}
                                                >
                                                    <Plus className="mr-2 h-4 w-4" /> Add
                                                </Button>
                                            </div>

                                            {(selectedStep.node_actions || []).length > 0 ? (
                                                <div className="space-y-3">
                                                    {(selectedStep.node_actions || []).map((action, actionIndex) => (
                                                        <div key={`${action.type}-${actionIndex}`} className="bg-background space-y-3 rounded-lg border p-3">
                                                            <div className="flex items-start justify-between gap-3">
                                                                <div className="grid flex-1 gap-3 sm:grid-cols-[1fr_90px]">
                                                                    <div className="space-y-2">
                                                                        <Label>Action</Label>
                                                                        <Select
                                                                            value={action.type}
                                                                            onValueChange={(value) =>
                                                                                selectedStepIndex !== null &&
                                                                                updateNodeAction(selectedStepIndex, actionIndex, (currentAction) => ({
                                                                                    ...currentAction,
                                                                                    key: value,
                                                                                    type: value as EnrollmentPipelineNodeActionType,
                                                                                }))
                                                                            }
                                                                        >
                                                                            <SelectTrigger>
                                                                                <SelectValue />
                                                                            </SelectTrigger>
                                                                            <SelectContent>
                                                                                {nodeActionOptions.map((option) => (
                                                                                    <SelectItem key={option.value} value={option.value}>
                                                                                        {option.label}
                                                                                    </SelectItem>
                                                                                ))}
                                                                            </SelectContent>
                                                                        </Select>
                                                                    </div>
                                                                    <div className="space-y-2">
                                                                        <Label>Order</Label>
                                                                        <Input
                                                                            type="number"
                                                                            min={1}
                                                                            value={action.order}
                                                                            onChange={(event) =>
                                                                                selectedStepIndex !== null &&
                                                                                updateNodeAction(selectedStepIndex, actionIndex, (currentAction) => ({
                                                                                    ...currentAction,
                                                                                    order: Number(event.target.value) || 1,
                                                                                }))
                                                                            }
                                                                        />
                                                                    </div>
                                                                </div>
                                                                <Button
                                                                    type="button"
                                                                    variant="ghost"
                                                                    size="icon"
                                                                    className="text-destructive hover:bg-destructive/10 hover:text-destructive"
                                                                    onClick={() => selectedStepIndex !== null && removeNodeAction(selectedStepIndex, actionIndex)}
                                                                >
                                                                    <Trash2 className="h-4 w-4" />
                                                                </Button>
                                                            </div>
                                                            <p className="text-muted-foreground text-xs">
                                                                {nodeActionOptions.find((option) => option.value === action.type)?.description}
                                                            </p>
                                                            <div className="grid gap-3 sm:grid-cols-2">
                                                                <label className="flex items-center gap-2 text-sm">
                                                                    <Switch
                                                                        checked={action.enabled ?? true}
                                                                        onCheckedChange={(enabled) =>
                                                                            selectedStepIndex !== null &&
                                                                            updateNodeAction(selectedStepIndex, actionIndex, (currentAction) => ({
                                                                                ...currentAction,
                                                                                enabled,
                                                                            }))
                                                                        }
                                                                    />
                                                                    Enabled
                                                                </label>
                                                                <label className="flex items-center gap-2 text-sm">
                                                                    <Switch
                                                                        checked={action.halt_on_failure ?? true}
                                                                        onCheckedChange={(halt_on_failure) =>
                                                                            selectedStepIndex !== null &&
                                                                            updateNodeAction(selectedStepIndex, actionIndex, (currentAction) => ({
                                                                                ...currentAction,
                                                                                halt_on_failure,
                                                                            }))
                                                                        }
                                                                    />
                                                                    Stop if this fails
                                                                </label>
                                                            </div>

                                                            <div className="flex items-center justify-between gap-3 border-t pt-3">
                                                                <div className="min-w-0">
                                                                    <span className="text-muted-foreground flex items-center gap-1 text-xs font-medium">
                                                                        <Filter className="h-3 w-3" /> Logic
                                                                    </span>
                                                                    <p className="text-muted-foreground mt-1 truncate text-xs">
                                                                        {(action.conditions || []).length > 0
                                                                            ? `${action.condition_logic === "any" ? "Any" : "All"} of ${(action.conditions || []).length} condition(s)`
                                                                            : "Runs without extra conditions"}
                                                                    </p>
                                                                </div>
                                                                <Button
                                                                    type="button"
                                                                    variant="outline"
                                                                    size="sm"
                                                                    onClick={() => selectedStepIndex !== null && openActionEditor(selectedStepIndex, actionIndex)}
                                                                >
                                                                    <Pencil className="mr-2 h-3 w-3" /> Edit in modal
                                                                </Button>
                                                            </div>
                                                        </div>
                                                    ))}
                                                </div>
                                            ) : (
                                                <p className="text-muted-foreground bg-background rounded-md border border-dashed p-3 text-sm">
                                                    No node actions configured. Add one before saving this workflow.
                                                </p>
                                            )}
                                        </div>
                                    )}

                                    {editorTab === "conditions" && (
                                        <div className="bg-muted/20 space-y-4 rounded-xl border p-4">
                                            <div className="flex items-start justify-between gap-3">
                                                <div>
                                                    <Label className="text-base font-semibold">Built-in conditions</Label>
                                                    <p className="text-muted-foreground mt-1 text-sm">
                                                        Conditions must pass before the node actions run.
                                                    </p>
                                                </div>
                                                <Switch
                                                    checked={(selectedStep.node_conditions || []).some(
                                                        (condition) => condition.type === "complete_student_profile",
                                                    )}
                                                    onCheckedChange={(enabled) =>
                                                        selectedStepIndex !== null && toggleCompleteProfileCondition(selectedStepIndex, enabled)
                                                    }
                                                />
                                            </div>

                                            {(selectedStep.node_conditions || []).some((condition) => condition.type === "complete_student_profile") ? (
                                                <div className="bg-background space-y-3 rounded-lg border p-3">
                                                    <div>
                                                        <p className="font-medium">Require complete student profile</p>
                                                        <p className="text-muted-foreground mt-1 text-xs">
                                                            The next node will not proceed until these student fields are filled.
                                                        </p>
                                                    </div>
                                                    <div className="grid gap-2 sm:grid-cols-2">
                                                        {profileFieldOptions.map((field) => {
                                                            const condition = (selectedStep.node_conditions || []).find(
                                                                (currentCondition) => currentCondition.type === "complete_student_profile",
                                                            );
                                                            const checked = condition?.config.required_fields?.includes(field.value) ?? false;

                                                            return (
                                                                <label key={field.value} className="flex items-center gap-2 rounded-md border px-3 py-2 text-sm">
                                                                    <Checkbox
                                                                        checked={checked}
                                                                        onCheckedChange={(value) =>
                                                                            selectedStepIndex !== null &&
                                                                            toggleProfileRequiredField(selectedStepIndex, field.value, value === true)
                                                                        }
                                                                    />
                                                                    {field.label}
                                                                </label>
                                                            );
                                                        })}
                                                    </div>
                                                </div>
                                            ) : (
                                                <p className="text-muted-foreground bg-background rounded-md border border-dashed p-3 text-sm">
                                                    No blocking conditions configured for this node.
                                                </p>
                                            )}
                                        </div>
                                    )}

                                    {editorTab === "connections" && (
                                        <div className="bg-muted/20 space-y-3 rounded-xl border p-4">
                                            <div className="flex items-start gap-2">
                                                <ArrowRight className="text-primary mt-0.5 h-4 w-4" />
                                                <div className="flex-1 space-y-3">
                                                    <div>
                                                        <Label className="text-base font-semibold">Connect next node</Label>
                                                        <p className="text-muted-foreground mt-1 text-sm">
                                                            Choose which step comes after this node. This updates the saved approval sequence.
                                                        </p>
                                                    </div>
                                                    <div className="grid gap-2">
                                                        <Select
                                                            value=""
                                                            onValueChange={(value) =>
                                                                selectedStepIndex !== null && connectStepToNext(selectedStepIndex, Number(value))
                                                            }
                                                        >
                                                            <SelectTrigger>
                                                                <SelectValue placeholder="Connect to another step..." />
                                                            </SelectTrigger>
                                                            <SelectContent>
                                                                {pipelineForm.data.steps.map((step, index) => {
                                                                    if (index === selectedStepIndex) {
                                                                        return null;
                                                                    }

                                                                    return (
                                                                        <SelectItem key={`${step.key}-${index}`} value={String(index)}>
                                                                            {step.label || step.status || `Step ${index + 1}`}
                                                                        </SelectItem>
                                                                    );
                                                                })}
                                                            </SelectContent>
                                                        </Select>
                                                        <Button
                                                            type="button"
                                                            variant="outline"
                                                            onClick={() => selectedStepIndex !== null && disconnectStep(selectedStepIndex)}
                                                            disabled={!selectedStep.next_step_key && !(selectedStep.next_step_keys?.length)}
                                                        >
                                                            <Scissors className="mr-2 h-4 w-4" /> Disconnect
                                                        </Button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    )}

                                    {editorTab === "permissions" && (
                                        <div className="bg-muted/20 space-y-3 rounded-xl border p-4">
                                            <div className="flex items-start gap-2">
                                                <LockKeyhole className="text-primary mt-0.5 h-4 w-4" />
                                                <div>
                                                    <Label className="text-base font-semibold">Approver roles</Label>
                                                    <p className="text-muted-foreground mt-1 text-sm">
                                                        Leave empty to allow any staff member with enrollment access. Super admins always have
                                                        access.
                                                    </p>
                                                </div>
                                            </div>
                                            <div className="flex flex-col gap-2 sm:flex-row">
                                                <Combobox
                                                    options={roleComboboxOptions}
                                                    value={selectedRoleByStep[Number(selectedStepIndex)] || ""}
                                                    onValueChange={(value) =>
                                                        setSelectedRoleByStep((current) => ({ ...current, [Number(selectedStepIndex)]: value }))
                                                    }
                                                    placeholder="Select role..."
                                                    searchPlaceholder="Search roles..."
                                                    emptyText="No role found."
                                                />
                                                <Button
                                                    type="button"
                                                    variant="secondary"
                                                    onClick={() => selectedStepIndex !== null && addRoleToStep(selectedStepIndex)}
                                                    disabled={!selectedRoleByStep[Number(selectedStepIndex)]}
                                                >
                                                    <Plus className="mr-2 h-4 w-4" /> Add role
                                                </Button>
                                            </div>
                                            {selectedStep.allowed_roles.length > 0 ? (
                                                <div className="flex flex-wrap gap-2">
                                                    {selectedStep.allowed_roles.map((roleName) => (
                                                        <Badge key={roleName} variant="secondary" className="gap-2">
                                                            {roleName}
                                                            <button
                                                                type="button"
                                                                onClick={() =>
                                                                    selectedStepIndex !== null && removeRoleFromStep(selectedStepIndex, roleName)
                                                                }
                                                                className="text-muted-foreground hover:text-destructive"
                                                                aria-label={`Remove ${roleName}`}
                                                            >
                                                                <Trash2 className="h-3 w-3" />
                                                            </button>
                                                        </Badge>
                                                    ))}
                                                </div>
                                            ) : (
                                                <p className="text-muted-foreground bg-background rounded-md border border-dashed p-3 text-sm">
                                                    No role restrictions configured for this step.
                                                </p>
                                            )}
                                        </div>
                                    )}

                                    {editorTab === "advanced" && (
                                        <div className="space-y-6">
                                            <div className="grid gap-3 sm:grid-cols-2">
                                                <Button
                                                    type="button"
                                                    variant={pipelineForm.data.entry_step_key === selectedStep.key ? "default" : "outline"}
                                                    onClick={() => pipelineForm.setData("entry_step_key", selectedStep.key)}
                                                >
                                                    Set as entry
                                                </Button>
                                                <Button
                                                    type="button"
                                                    variant={pipelineForm.data.completion_step_key === selectedStep.key ? "default" : "outline"}
                                                    onClick={() => pipelineForm.setData("completion_step_key", selectedStep.key)}
                                                >
                                                    Set as completion
                                                </Button>
                                            </div>

                                            <details className="rounded-lg border p-4">
                                                <summary className="cursor-pointer text-sm font-medium">Advanced: stable step ID</summary>
                                                <div className="mt-3 space-y-2">
                                                    <Label>Step ID</Label>
                                                    <Input
                                                        value={selectedStep.key}
                                                        onChange={(event) =>
                                                            selectedStepIndex !== null &&
                                                            setStepField(selectedStepIndex, "key", slugify(event.target.value, "step"))
                                                        }
                                                        className="font-mono"
                                                    />
                                                    <p className="text-muted-foreground text-xs">
                                                        This links entry/completion markers and legacy aliases. Keep it stable for existing workflows.
                                                    </p>
                                                    <FieldError message={formErrors[`steps.${selectedStepIndex}.key`]} />
                                                </div>
                                            </details>
                                        </div>
                                    )}
                                </div>
                            </div>
                        )}
                    </DialogContent>
                </Dialog>

                <Dialog open={actionEditorOpen} onOpenChange={setActionEditorOpen}>
                    <DialogContent className="max-h-[90vh] max-w-4xl overflow-y-auto">
                        <DialogHeader>
                            <DialogTitle>Edit action logic</DialogTitle>
                            <DialogDescription>
                                Configure the selected action and choose when it should run.
                            </DialogDescription>
                        </DialogHeader>

                        {selectedAction && selectedActionStep && editingAction && (
                            <div className="space-y-6">
                                <div className="grid gap-4 md:grid-cols-[1fr_120px]">
                                    <div className="space-y-2">
                                        <Label>Action</Label>
                                        <Select
                                            value={selectedAction.type}
                                            onValueChange={(value) =>
                                                updateNodeAction(editingAction.stepIndex, editingAction.actionIndex, (action) => ({
                                                    ...action,
                                                    key: value,
                                                    type: value as EnrollmentPipelineNodeActionType,
                                                }))
                                            }
                                        >
                                            <SelectTrigger>
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {nodeActionOptions.map((option) => (
                                                    <SelectItem key={option.value} value={option.value}>
                                                        {option.label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <p className="text-muted-foreground text-xs">
                                            {nodeActionOptions.find((option) => option.value === selectedAction.type)?.description}
                                        </p>
                                    </div>
                                    <div className="space-y-2">
                                        <Label>Order</Label>
                                        <Input
                                            type="number"
                                            min={1}
                                            value={selectedAction.order}
                                            onChange={(event) =>
                                                updateNodeAction(editingAction.stepIndex, editingAction.actionIndex, (action) => ({
                                                    ...action,
                                                    order: Number(event.target.value) || 1,
                                                }))
                                            }
                                        />
                                    </div>
                                </div>

                                {selectedAction.type === "change_status" && (
                                    <div className="space-y-2 rounded-lg border p-4">
                                        <Label>Set enrollee status to</Label>
                                        <Input
                                            value={typeof selectedAction.config?.status === "string" ? selectedAction.config.status : selectedActionStep.status}
                                            onChange={(event) =>
                                                updateNodeAction(editingAction.stepIndex, editingAction.actionIndex, (action) => ({
                                                    ...action,
                                                    config: { ...action.config, status: event.target.value },
                                                }))
                                            }
                                            placeholder={selectedActionStep.status}
                                        />
                                    </div>
                                )}

                                <div className="grid gap-3 sm:grid-cols-2">
                                    <label className="flex items-center gap-2 rounded-lg border p-3 text-sm">
                                        <Switch
                                            checked={selectedAction.enabled ?? true}
                                            onCheckedChange={(enabled) =>
                                                updateNodeAction(editingAction.stepIndex, editingAction.actionIndex, (action) => ({ ...action, enabled }))
                                            }
                                        />
                                        Enabled
                                    </label>
                                    <label className="flex items-center gap-2 rounded-lg border p-3 text-sm">
                                        <Switch
                                            checked={selectedAction.halt_on_failure ?? true}
                                            onCheckedChange={(halt_on_failure) =>
                                                updateNodeAction(editingAction.stepIndex, editingAction.actionIndex, (action) => ({
                                                    ...action,
                                                    halt_on_failure,
                                                }))
                                            }
                                        />
                                        Stop workflow if this action fails
                                    </label>
                                </div>

                                <div className="space-y-4 rounded-xl border p-4">
                                    <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                        <div>
                                            <Label className="text-base font-semibold">Run conditions</Label>
                                            <p className="text-muted-foreground mt-1 text-sm">
                                                Use these checks to branch action behavior without crowding the canvas.
                                            </p>
                                        </div>
                                        <div className="flex gap-2">
                                            <Select
                                                value={selectedAction.condition_logic || "all"}
                                                onValueChange={(value) =>
                                                    updateNodeAction(editingAction.stepIndex, editingAction.actionIndex, (action) => ({
                                                        ...action,
                                                        condition_logic: value as "all" | "any",
                                                    }))
                                                }
                                            >
                                                <SelectTrigger className="w-[150px]">
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="all">Match all</SelectItem>
                                                    <SelectItem value="any">Match any</SelectItem>
                                                </SelectContent>
                                            </Select>
                                            <Button type="button" variant="outline" onClick={() => addActionCondition(editingAction.stepIndex, editingAction.actionIndex)}>
                                                <Plus className="mr-2 h-4 w-4" /> Add condition
                                            </Button>
                                        </div>
                                    </div>

                                    {(selectedAction.conditions || []).length === 0 ? (
                                        <div className="rounded-lg border border-dashed p-5 text-center">
                                            <Filter className="text-muted-foreground mx-auto mb-2 h-5 w-5" />
                                            <p className="text-sm font-medium">This action always runs</p>
                                            <p className="text-muted-foreground mt-1 text-xs">Add conditions when only some enrollments should run this action.</p>
                                        </div>
                                    ) : (
                                        <div className="space-y-3">
                                            {(selectedAction.conditions || []).map((condition, conditionIndex) => {
                                                const isBool = booleanConditionTypes.has(condition.type);
                                                const isText = textConditionTypes.has(condition.type);

                                                return (
                                                    <div key={conditionIndex} className="grid gap-3 rounded-lg border bg-muted/20 p-3 lg:grid-cols-[150px_1fr_120px_180px_40px]">
                                                        <label className="flex items-center gap-2 text-sm">
                                                            <Switch
                                                                checked={condition.enabled ?? true}
                                                                onCheckedChange={(enabled) =>
                                                                    updateActionCondition(
                                                                        editingAction.stepIndex,
                                                                        editingAction.actionIndex,
                                                                        conditionIndex,
                                                                        (currentCondition) => ({ ...currentCondition, enabled }),
                                                                    )
                                                                }
                                                            />
                                                            Enabled
                                                        </label>
                                                        <div className="space-y-1">
                                                            <Label className="text-xs">Field</Label>
                                                            <Select
                                                                value={condition.type}
                                                                onValueChange={(value) =>
                                                                    updateActionCondition(
                                                                        editingAction.stepIndex,
                                                                        editingAction.actionIndex,
                                                                        conditionIndex,
                                                                        (currentCondition) => {
                                                                            const nextCondition = {
                                                                                ...currentCondition,
                                                                                type: value as ActionConditionType,
                                                                                operator: textConditionTypes.has(value as ActionConditionType) ? "eq" : currentCondition.operator,
                                                                            };

                                                                            return {
                                                                                ...nextCondition,
                                                                                value: normalizeActionConditionValue(nextCondition),
                                                                            };
                                                                        },
                                                                    )
                                                                }
                                                            >
                                                                <SelectTrigger>
                                                                    <SelectValue />
                                                                </SelectTrigger>
                                                                <SelectContent className="max-h-72">
                                                                    {conditionTypeOptions.map((option) => (
                                                                        <SelectItem key={option.value} value={option.value}>
                                                                            {option.label}
                                                                        </SelectItem>
                                                                    ))}
                                                                </SelectContent>
                                                            </Select>
                                                        </div>
                                                        <div className="space-y-1">
                                                            <Label className="text-xs">Operator</Label>
                                                            <Select
                                                                value={isBool ? "eq" : isText ? (condition.operator === "neq" ? "neq" : "eq") : condition.operator}
                                                                disabled={isBool}
                                                                onValueChange={(value) =>
                                                                    updateActionCondition(
                                                                        editingAction.stepIndex,
                                                                        editingAction.actionIndex,
                                                                        conditionIndex,
                                                                        (currentCondition) => ({ ...currentCondition, operator: value as ActionCondition["operator"] }),
                                                                    )
                                                                }
                                                            >
                                                                <SelectTrigger>
                                                                    <SelectValue />
                                                                </SelectTrigger>
                                                                <SelectContent>
                                                                    {isText ? (
                                                                        <>
                                                                            <SelectItem value="eq">Equals</SelectItem>
                                                                            <SelectItem value="neq">Does not equal</SelectItem>
                                                                        </>
                                                                    ) : (
                                                                        conditionOperatorOptions.map((option) => (
                                                                            <SelectItem key={option.value} value={option.value}>
                                                                                {option.label}
                                                                            </SelectItem>
                                                                        ))
                                                                    )}
                                                                </SelectContent>
                                                            </Select>
                                                        </div>
                                                        <div className="space-y-1">
                                                            <Label className="text-xs">Value</Label>
                                                            {isBool ? (
                                                                <Select
                                                                    value={condition.value ? "1" : "0"}
                                                                    onValueChange={(value) =>
                                                                        updateActionCondition(
                                                                            editingAction.stepIndex,
                                                                            editingAction.actionIndex,
                                                                            conditionIndex,
                                                                            (currentCondition) => ({
                                                                                ...currentCondition,
                                                                                operator: "eq",
                                                                                value: value === "1",
                                                                            }),
                                                                        )
                                                                    }
                                                                >
                                                                    <SelectTrigger>
                                                                        <SelectValue />
                                                                    </SelectTrigger>
                                                                    <SelectContent>
                                                                        <SelectItem value="1">Yes</SelectItem>
                                                                        <SelectItem value="0">No</SelectItem>
                                                                    </SelectContent>
                                                                </Select>
                                                            ) : (
                                                                <Input
                                                                    type={isText ? "text" : "number"}
                                                                    value={String(condition.value ?? "")}
                                                                    onChange={(event) =>
                                                                        updateActionCondition(
                                                                            editingAction.stepIndex,
                                                                            editingAction.actionIndex,
                                                                            conditionIndex,
                                                                            (currentCondition) => ({
                                                                                ...currentCondition,
                                                                                value: isText ? event.target.value : Number(event.target.value) || 0,
                                                                            }),
                                                                        )
                                                                    }
                                                                />
                                                            )}
                                                        </div>
                                                        <Button
                                                            type="button"
                                                            variant="ghost"
                                                            size="icon"
                                                            className="self-end text-destructive hover:bg-destructive/10 hover:text-destructive"
                                                            onClick={() => removeActionCondition(editingAction.stepIndex, editingAction.actionIndex, conditionIndex)}
                                                        >
                                                            <Trash2 className="h-4 w-4" />
                                                        </Button>
                                                    </div>
                                                );
                                            })}
                                        </div>
                                    )}
                                </div>
                            </div>
                        )}

                        <DialogFooter>
                            <Button type="button" onClick={() => setActionEditorOpen(false)}>
                                Done
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>

                <Dialog open={actionPickerOpen} onOpenChange={setActionPickerOpen}>
                    <DialogContent className="max-w-lg">
                        <DialogHeader>
                            <DialogTitle>Add action</DialogTitle>
                            <DialogDescription>
                                Choose a built-in action to attach to this step.
                            </DialogDescription>
                        </DialogHeader>
                        <div className="space-y-2 py-2">
                            {nodeActionOptions.map((option) => {
                                const OptionIcon = option.icon;

                                return (
                                    <button
                                        key={option.value}
                                        type="button"
                                        onClick={() => {
                                            if (actionPickerStepIndex !== null) {
                                                addNodeAction(actionPickerStepIndex, option.value);
                                            }
                                            setActionPickerOpen(false);
                                            setActionPickerStepIndex(null);
                                        }}
                                        className="w-full text-left rounded-xl border p-4 transition hover:border-primary/40 hover:bg-muted/40"
                                    >
                                        <span className="flex gap-3">
                                            <span className="bg-primary/10 text-primary mt-0.5 rounded-lg p-2">
                                                <OptionIcon className="h-4 w-4" />
                                            </span>
                                            <span>
                                                <span className="block font-medium">{option.label}</span>
                                                <span className="text-muted-foreground mt-1 block text-sm">
                                                    {option.description}
                                                </span>
                                            </span>
                                        </span>
                                    </button>
                                );
                            })}
                        </div>
                    </DialogContent>
                </Dialog>
            </div>
        </SystemManagementLayout>
    );
}

function PipelineSummaryCard({ icon: Icon, label, value }: { icon: typeof Workflow; label: string; value: string }) {
    return (
        <Card className="shadow-none">
            <CardContent className="flex items-center gap-3 p-4">
                <div className="bg-primary/10 text-primary rounded-lg p-2">
                    <Icon className="h-5 w-5" />
                </div>
                <div className="min-w-0">
                    <p className="text-muted-foreground text-xs font-medium tracking-wide uppercase">{label}</p>
                    <p className="truncate text-lg font-semibold">{value}</p>
                </div>
            </CardContent>
        </Card>
    );
}

function AutomationSwitch({
    id,
    title,
    description,
    checked,
    disabled = false,
    onCheckedChange,
}: {
    id: string;
    title: string;
    description: string;
    checked: boolean;
    disabled?: boolean;
    onCheckedChange: (checked: boolean) => void;
}) {
    return (
        <div className="flex items-start gap-3 rounded-lg border p-3">
            <Switch id={id} checked={checked} disabled={disabled} onCheckedChange={(value) => onCheckedChange(value === true)} />
            <div className="space-y-1">
                <Label htmlFor={id} className="cursor-pointer font-medium">
                    {title}
                </Label>
                <p className="text-muted-foreground text-xs">{description}</p>
            </div>
        </div>
    );
}

function FieldError({ message }: { message?: string }) {
    if (!message) {
        return null;
    }

    return <p className="text-destructive text-xs">{message}</p>;
}
