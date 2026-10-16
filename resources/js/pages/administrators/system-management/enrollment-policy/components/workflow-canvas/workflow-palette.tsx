import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import {
    Ban,
    CheckCircle2,
    Coins,
    CreditCard,
    FileCheck,
    FileText,
    GraduationCap,
    Layers,
    Mail,
    Plus,
    UserCheck,
    Users,
    X,
    XCircle,
    Zap,
} from "lucide-react";
import React from "react";
import type { Action } from "../../types";

export type PaletteTemplate = {
    type: string;
    label: string;
    category: "milestone" | "terminal" | "subaction";
    description: string;
    icon: typeof Layers;
    defaultStatus: string;
    outcome?: "completed" | "rejected" | "cancelled";
    iconColor: string;
    defaultActions?: Action[];
};

export const PALETTE_TEMPLATES: PaletteTemplate[] = [
    {
        type: "intake_screening",
        label: "Applicant Intake & Screening",
        category: "milestone",
        description: "Initial application receipt and personal background evaluation.",
        icon: Users,
        defaultStatus: "Under Review",
        iconColor: "text-blue-500",
        defaultActions: [
            {
                key: "assign_subjects",
                handler: "enrollment.assign_subjects",
                configuration: { allow_cross_program_subjects: false },
            },
            {
                key: "assign_additional_fees",
                handler: "enrollment.assign_additional_fees",
                configuration: {},
            },
            {
                key: "reserve_selected_classes",
                handler: "enrollment.assign_classes",
                configuration: { mode: "runtime_payload", optional: true },
            },
            {
                key: "calculate_tuition",
                handler: "enrollment.calculate_tuition",
                configuration: {},
            },
        ],
    },
    {
        type: "document_clearance",
        label: "Registrar Document Verification",
        category: "milestone",
        description: "Validate Form 137, PSA certificate, and Good Moral documents.",
        icon: FileCheck,
        defaultStatus: "Documents Submitted",
        iconColor: "text-emerald-500",
        defaultActions: [
            {
                key: "document_status",
                handler: "enrollment.change_status",
                configuration: { status: "Documents Submitted" },
            },
        ],
    },
    {
        type: "academic_evaluation",
        label: "Dean / Academic Clearance",
        category: "milestone",
        description: "Check prerequisite curriculum, GPA standing, and program fit.",
        icon: UserCheck,
        defaultStatus: "Academic Review",
        iconColor: "text-indigo-500",
        defaultActions: [
            {
                key: "academic_status",
                handler: "enrollment.verify_academic",
                configuration: { status: "Verified by Dept Head" },
            },
            {
                key: "notify_academic",
                handler: "enrollment.notify",
                configuration: { notification: "academic_verified" },
            },
        ],
    },
    {
        type: "notification_dispatch",
        label: "Applicant Email Notification",
        category: "milestone",
        description: "Dispatches an automated email confirmation to the applicant.",
        icon: Mail,
        defaultStatus: "Notification Dispatched",
        iconColor: "text-sky-500",
        defaultActions: [
            {
                key: "notify_student",
                handler: "enrollment.notify",
                configuration: { notification: "status_update" },
            },
        ],
    },
    {
        type: "section_assignment",
        label: "Section & Class Scheduling",
        category: "milestone",
        description: "Assign block section, class timetable, and cohort group.",
        icon: Layers,
        defaultStatus: "Section Assigned",
        iconColor: "text-violet-500",
        defaultActions: [
            {
                key: "assign_classes",
                handler: "enrollment.assign_classes",
                configuration: { mode: "first_available", optional: true },
            },
        ],
    },
    {
        type: "billing_assessment",
        label: "Tuition & Payment Clearance",
        category: "milestone",
        description: "Assess tuition matrix, apply discounts, and verify down payment.",
        icon: CreditCard,
        defaultStatus: "Payment Pending",
        iconColor: "text-amber-500",
        defaultActions: [
            {
                key: "payment_status",
                handler: "enrollment.verify_payment",
                configuration: {
                    receipt_mode: "required",
                    record_transaction: true,
                    allow_no_receipt: false,
                },
            },
            {
                key: "generate_assessment",
                handler: "enrollment.generate_assessment",
                configuration: { create_new_file: false },
            },
        ],
    },
    {
        type: "custom_approval",
        label: "Custom Approval Step",
        category: "milestone",
        description: "Generic staff signoff with customizable rules and actions.",
        icon: Zap,
        defaultStatus: "Pending Approval",
        iconColor: "text-sky-500",
        defaultActions: [
            {
                key: "step_status",
                handler: "enrollment.change_status",
                configuration: { status: "Under Review" },
            },
        ],
    },
    /* Attachable Sub-Action Nodes */
    {
        type: "action_notify",
        label: "Confirmation Email",
        category: "subaction",
        description: "Attachable sub-action: sends an automated email confirmation to the student.",
        icon: Mail,
        defaultStatus: "Email Dispatched",
        iconColor: "text-sky-500",
        defaultActions: [
            {
                key: "notify_student",
                handler: "enrollment.notify",
                configuration: { notification: "academic_verified" },
            },
        ],
    },
    {
        type: "action_tuition",
        label: "Calculate Tuition Ledger",
        category: "subaction",
        description: "Attachable sub-action: computes tuition, modular units, and discounts.",
        icon: Coins,
        defaultStatus: "Tuition Calculated",
        iconColor: "text-amber-500",
        defaultActions: [
            {
                key: "calculate_tuition",
                handler: "enrollment.calculate_tuition",
                configuration: {},
            },
        ],
    },
    {
        type: "action_assessment_pdf",
        label: "Generate Assessment PDF",
        category: "subaction",
        description: "Attachable sub-action: generates and stores official assessment document.",
        icon: FileText,
        defaultStatus: "Assessment Generated",
        iconColor: "text-blue-500",
        defaultActions: [
            {
                key: "generate_assessment",
                handler: "enrollment.generate_assessment",
                configuration: { create_new_file: false },
            },
        ],
    },
    {
        type: "action_sync_student",
        label: "Sync Student Account",
        category: "subaction",
        description: "Attachable sub-action: marks student status as enrolled and links account.",
        icon: UserCheck,
        defaultStatus: "Student Synchronized",
        iconColor: "text-teal-500",
        defaultActions: [
            {
                key: "sync_student",
                handler: "enrollment.sync_student",
                configuration: {
                    attribute: "status",
                    value: "enrolled",
                    sync_account: true,
                },
            },
        ],
    },
    {
        type: "action_assign_classes",
        label: "Assign Classes",
        category: "subaction",
        description: "Attachable sub-action: automatically allocates open class schedule slots.",
        icon: GraduationCap,
        defaultStatus: "Classes Assigned",
        iconColor: "text-violet-500",
        defaultActions: [
            {
                key: "assign_classes",
                handler: "enrollment.assign_classes",
                configuration: { mode: "first_available", optional: true },
            },
        ],
    },
    {
        type: "terminal_completed",
        label: "Enrolled & Matriculated",
        category: "terminal",
        description: "Successful journey completion. Student is formally matriculated.",
        icon: CheckCircle2,
        defaultStatus: "Enrolled",
        outcome: "completed",
        iconColor: "text-emerald-600 dark:text-emerald-400",
        defaultActions: [
            {
                key: "payment_status",
                handler: "enrollment.verify_payment",
                configuration: {
                    receipt_mode: "required",
                    record_transaction: true,
                    allow_no_receipt: false,
                },
            },
            {
                key: "assign_remaining_classes",
                handler: "enrollment.assign_classes",
                configuration: { mode: "first_available", optional: true },
            },
            {
                key: "sync_student",
                handler: "enrollment.sync_student",
                configuration: {
                    attribute: "status",
                    value: "enrolled",
                    sync_account: true,
                },
            },
            {
                key: "complete_outcome",
                handler: "enrollment.set_outcome",
                configuration: { outcome: "completed" },
            },
            {
                key: "generate_assessment",
                handler: "enrollment.generate_assessment",
                configuration: { create_new_file: false },
            },
            {
                key: "notify_student",
                handler: "enrollment.notify",
                configuration: { notification: "assessment" },
            },
        ],
    },
    {
        type: "terminal_rejected",
        label: "Application Denied",
        category: "terminal",
        description: "Student does not meet admission or clearance standards.",
        icon: XCircle,
        defaultStatus: "Rejected",
        outcome: "rejected",
        iconColor: "text-rose-600 dark:text-rose-400",
        defaultActions: [
            {
                key: "sync_student",
                handler: "enrollment.sync_student",
                configuration: {
                    attribute: "status",
                    value: "rejected",
                    sync_account: false,
                },
            },
            {
                key: "reject_outcome",
                handler: "enrollment.set_outcome",
                configuration: { outcome: "rejected" },
            },
        ],
    },
    {
        type: "terminal_cancelled",
        label: "Application Cancelled",
        category: "terminal",
        description: "Applicant withdraws or enrollment window expires.",
        icon: Ban,
        defaultStatus: "Cancelled",
        outcome: "cancelled",
        iconColor: "text-amber-600 dark:text-amber-400",
        defaultActions: [
            {
                key: "sync_student",
                handler: "enrollment.sync_student",
                configuration: {
                    attribute: "status",
                    value: "cancelled",
                    sync_account: false,
                },
            },
            {
                key: "cancel_outcome",
                handler: "enrollment.set_outcome",
                configuration: { outcome: "cancelled" },
            },
        ],
    },
];

export function WorkflowPalette({ onAddTemplate, onClose }: { onAddTemplate: (template: PaletteTemplate) => void; onClose?: () => void }) {
    const onDragStart = (event: React.DragEvent, template: PaletteTemplate) => {
        event.dataTransfer.setData("application/reactflow-template", template.type);
        event.dataTransfer.effectAllowed = "move";
    };

    const milestones = PALETTE_TEMPLATES.filter((t) => t.category === "milestone");
    const subactions = PALETTE_TEMPLATES.filter((t) => t.category === "subaction");
    const terminals = PALETTE_TEMPLATES.filter((t) => t.category === "terminal");

    return (
        <aside className="border-border/70 bg-card/95 absolute top-3 bottom-3 left-3 z-30 flex w-72 flex-col rounded-2xl border shadow-xl backdrop-blur-md select-none">
            <div className="border-border/40 flex items-center justify-between border-b p-3.5">
                <div className="flex items-center gap-2">
                    <span className="text-foreground text-xs font-bold tracking-wider uppercase">Step Catalog</span>
                    <Badge variant="outline" className="border-primary/40 text-primary h-4 px-1.5 text-[9px]">
                        Drag & Drop
                    </Badge>
                </div>
                {onClose && (
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="text-muted-foreground hover:text-foreground size-6"
                        onClick={onClose}
                        title="Close step catalog"
                    >
                        <X className="size-3.5" />
                    </Button>
                )}
            </div>

            <div className="border-border/30 bg-muted/20 text-muted-foreground border-b p-2 px-3 py-2 text-[11px] leading-tight">
                Drag any block onto the canvas or click <Plus className="inline size-3" /> to add.
            </div>

            <div className="flex-1 space-y-4 overflow-y-auto p-3">
                {/* Milestones */}
                <div className="space-y-1.5">
                    <span className="text-muted-foreground px-1 text-[10px] font-semibold tracking-wider uppercase">Journey Milestones</span>
                    <div className="space-y-1.5">
                        {milestones.map((template) => {
                            const Icon = template.icon;
                            return (
                                <div
                                    key={template.type}
                                    draggable
                                    onDragStart={(e) => onDragStart(e, template)}
                                    className={cn(
                                        "group border-border/60 bg-background/80 relative flex items-start gap-2.5 rounded-xl border p-2 text-left transition-colors",
                                        "hover:border-primary/60 hover:bg-muted/40 cursor-grab hover:shadow-xs active:cursor-grabbing",
                                    )}
                                >
                                    <div className="bg-muted/60 border-border/40 mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-lg border">
                                        <Icon className={cn("size-3.5", template.iconColor)} />
                                    </div>

                                    <div className="min-w-0 flex-1">
                                        <span className="text-foreground block truncate text-xs font-semibold">{template.label}</span>
                                        <p className="text-muted-foreground mt-0.5 line-clamp-1 text-[10px] leading-tight">{template.description}</p>
                                    </div>

                                    <button
                                        type="button"
                                        onClick={() => onAddTemplate(template)}
                                        className="text-muted-foreground hover:text-foreground hover:bg-muted rounded p-1 transition-colors"
                                        aria-label={`Add ${template.label}`}
                                        title="Click to add to canvas"
                                    >
                                        <Plus className="size-3.5" />
                                    </button>
                                </div>
                            );
                        })}
                    </div>
                </div>

                {/* Attachable Sub-Actions */}
                <div className="border-border/40 space-y-1.5 border-t pt-2">
                    <div className="flex items-center justify-between px-1">
                        <span className="text-muted-foreground text-[10px] font-semibold tracking-wider uppercase">Attachable Sub-Actions</span>
                        <Badge variant="secondary" className="h-3.5 px-1 font-mono text-[8px]">
                            Step Hooks
                        </Badge>
                    </div>
                    <div className="space-y-1.5">
                        {subactions.map((template) => {
                            const Icon = template.icon;
                            return (
                                <div
                                    key={template.type}
                                    draggable
                                    onDragStart={(e) => onDragStart(e, template)}
                                    className={cn(
                                        "group border-border/60 bg-background/80 relative flex items-start gap-2.5 rounded-xl border p-2 text-left transition-colors",
                                        "hover:border-primary/60 hover:bg-muted/40 cursor-grab hover:shadow-xs active:cursor-grabbing",
                                    )}
                                >
                                    <div className="bg-muted/60 border-border/40 mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-lg border">
                                        <Icon className={cn("size-3.5", template.iconColor)} />
                                    </div>

                                    <div className="min-w-0 flex-1">
                                        <span className="text-foreground block truncate text-xs font-semibold">{template.label}</span>
                                        <p className="text-muted-foreground mt-0.5 line-clamp-1 text-[10px] leading-tight">{template.description}</p>
                                    </div>

                                    <button
                                        type="button"
                                        onClick={() => onAddTemplate(template)}
                                        className="text-muted-foreground hover:text-foreground hover:bg-muted rounded p-1 transition-colors"
                                        aria-label={`Add ${template.label}`}
                                        title="Attach sub-action to selected step"
                                    >
                                        <Plus className="size-3.5" />
                                    </button>
                                </div>
                            );
                        })}
                    </div>
                </div>

                {/* Terminals */}
                <div className="border-border/40 space-y-1.5 border-t pt-2">
                    <span className="text-muted-foreground px-1 text-[10px] font-semibold tracking-wider uppercase">Outcomes & Endpoints</span>
                    <div className="space-y-1.5">
                        {terminals.map((template) => {
                            const Icon = template.icon;
                            return (
                                <div
                                    key={template.type}
                                    draggable
                                    onDragStart={(e) => onDragStart(e, template)}
                                    className={cn(
                                        "group border-border/60 bg-background/80 relative flex items-start gap-2.5 rounded-xl border p-2 text-left transition-colors",
                                        "hover:border-primary/60 hover:bg-muted/40 cursor-grab hover:shadow-xs active:cursor-grabbing",
                                    )}
                                >
                                    <div className="bg-muted/60 border-border/40 mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-lg border">
                                        <Icon className={cn("size-3.5", template.iconColor)} />
                                    </div>

                                    <div className="min-w-0 flex-1">
                                        <span className="text-foreground block truncate text-xs font-semibold">{template.label}</span>
                                        <p className="text-muted-foreground mt-0.5 line-clamp-1 text-[10px] leading-tight">{template.description}</p>
                                    </div>

                                    <button
                                        type="button"
                                        onClick={() => onAddTemplate(template)}
                                        className="text-muted-foreground hover:text-foreground hover:bg-muted rounded p-1 transition-colors"
                                        aria-label={`Add ${template.label}`}
                                        title="Click to add to canvas"
                                    >
                                        <Plus className="size-3.5" />
                                    </button>
                                </div>
                            );
                        })}
                    </div>
                </div>
            </div>
        </aside>
    );
}
