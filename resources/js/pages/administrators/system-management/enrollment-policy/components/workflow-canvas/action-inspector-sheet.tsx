import { Badge } from "@/components/reui/badge";
import { IconTile } from "@/components/reui/icon-tile";
import { Button } from "@/components/ui/button";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from "@/components/ui/sheet";
import {
    ArrowDown,
    ArrowUp,
    BookOpen,
    Coins,
    CreditCard,
    FileText,
    Flag,
    GraduationCap,
    Mail,
    Receipt,
    Sliders,
    Sparkles,
    Trash2,
    UserCheck,
    Zap,
} from "lucide-react";

import type { Action, Option, RegistryItem, WorkflowStep } from "../../types";
import { SchemaFields } from "../schema-fields";

const actionMeta: Record<string, { label: string; icon: typeof Zap; color: string; desc: string }> = {
    "enrollment.notify": {
        label: "Confirmation Email",
        icon: Mail,
        color: "text-sky-500",
        desc: "Queues email on stage entry, after preceding actions succeed and the transaction commits.",
    },
    "enrollment.calculate_tuition": {
        label: "Calculate Tuition Ledger",
        icon: Coins,
        color: "text-amber-500",
        desc: "Computes total tuition, modular units, lab fees, and applies student discounts.",
    },
    "enrollment.assign_subjects": {
        label: "Assign Curriculum Subjects",
        icon: BookOpen,
        color: "text-indigo-500",
        desc: "Materializes required subjects from program curriculum onto the enrollment.",
    },
    "enrollment.assign_classes": {
        label: "Assign Class Schedule Slots",
        icon: GraduationCap,
        color: "text-violet-500",
        desc: "Allocates open class blocks and reserves student seats.",
    },
    "enrollment.assign_additional_fees": {
        label: "Attach Additional Fees",
        icon: Receipt,
        color: "text-orange-500",
        desc: "Attaches extra submitted charges (e.g. ID card, laboratory kit).",
    },
    "enrollment.verify_academic": {
        label: "Academic Clearance Action",
        icon: UserCheck,
        color: "text-emerald-500",
        desc: "Marks academic clearance approval in coordinator database.",
    },
    "enrollment.verify_payment": {
        label: "Payment Verification Action",
        icon: CreditCard,
        color: "text-emerald-500",
        desc: "Validates payment settlement and records financial transaction.",
    },
    "enrollment.sync_student": {
        label: "Synchronize Student Account",
        icon: UserCheck,
        color: "text-teal-500",
        desc: "Marks student status as formally enrolled and syncs user credentials.",
    },
    "enrollment.set_outcome": {
        label: "Final Journey Outcome",
        icon: Flag,
        color: "text-rose-500",
        desc: "Sets the official terminal outcome on student record.",
    },
    "enrollment.generate_assessment": {
        label: "Generate Assessment Certificate",
        icon: FileText,
        color: "text-blue-500",
        desc: "Generates official certificate of matriculation PDF.",
    },
};

export function ActionInspectorSheet({
    open,
    onOpenChange,
    step,
    actionIndex,
    actions,
    options,
    onUpdateAction,
    onDeleteAction,
    onReorderAction,
    onEditEmailTemplate,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    step: WorkflowStep | null;
    actionIndex: number | null;
    actions: Record<string, RegistryItem>;
    options: Record<string, Option[]>;
    onUpdateAction: (parentStepKey: string, actionIndex: number, action: Action) => void;
    onDeleteAction: (parentStepKey: string, actionIndex: number) => void;
    onReorderAction: (parentStepKey: string, fromIndex: number, toIndex: number) => void;
    /** Opens the full-page email template editor for a step's notify action. */
    onEditEmailTemplate?: (stepKey: string) => void;
}) {
    if (!step || actionIndex === null || actionIndex < 0 || !step.actions[actionIndex]) {
        return null;
    }

    const action = step.actions[actionIndex];
    const totalActions = step.actions.length;
    const isFirst = actionIndex === 0;
    const isLast = actionIndex === totalActions - 1;

    const meta = actionMeta[action.handler] ?? {
        label: actions[action.handler]?.label ?? action.handler,
        icon: Zap,
        color: "text-primary",
        desc: "Automated enrollment action triggered on entering this stage.",
    };
    const Icon = meta.icon;

    const cfg = (action.configuration ?? {}) as Record<string, unknown>;
    const notificationType = String(cfg.notification ?? "assessment");

    const handleConfigChange = (newConfig: Record<string, unknown>) => {
        onUpdateAction(step.key, actionIndex, {
            ...action,
            configuration: newConfig,
        });
    };

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent side="right" className="w-[420px] overflow-y-auto p-0 sm:max-w-[460px]">
                {/* Header with ReUI IconTile */}
                <SheetHeader className="border-border/40 bg-card/75 border-b p-5">
                    <div className="flex items-start gap-3">
                        <IconTile variant="soft" size="default" className={meta.color}>
                            <Icon className="size-5" />
                        </IconTile>

                        <div className="min-w-0 flex-1">
                            <div className="flex flex-wrap items-center gap-1.5">
                                <SheetTitle className="text-foreground text-base font-bold">{meta.label}</SheetTitle>
                                <Badge variant="primary-light" size="sm" className="font-mono text-[9px]">
                                    #{actionIndex + 1} OF {totalActions}
                                </Badge>
                            </div>
                            <SheetDescription className="text-muted-foreground mt-0.5 text-xs">
                                Attached to: <span className="text-foreground font-semibold">{step.label}</span>
                            </SheetDescription>
                        </div>
                    </div>
                </SheetHeader>

                <div className="space-y-6 p-5 text-xs">
                    {/* Execution Sequence & Reorder Banner */}
                    <div className="border-border/60 bg-muted/20 space-y-2 rounded-xl border p-3.5">
                        <div className="flex items-center justify-between">
                            <div>
                                <span className="text-foreground block text-[11px] font-semibold tracking-wider uppercase">Execution Sequence</span>
                                <p className="text-muted-foreground mt-0.5 text-[10px]">
                                    {isFirst
                                        ? "Runs first on entering this stage. Entry stage runs on submission."
                                        : `Runs #${actionIndex + 1} on stage entry, after preceding actions succeed.`}
                                </p>
                            </div>

                            {totalActions > 1 && (
                                <div className="bg-background border-border/60 flex items-center rounded-lg border p-0.5 shadow-2xs">
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        disabled={isFirst}
                                        className="h-6.5 gap-1 px-2 text-xs disabled:opacity-30"
                                        onClick={() => onReorderAction(step.key, actionIndex, actionIndex - 1)}
                                        title="Move earlier in execution pipeline"
                                    >
                                        <ArrowUp className="size-3" /> Earlier
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        disabled={isLast}
                                        className="h-6.5 gap-1 px-2 text-xs disabled:opacity-30"
                                        onClick={() => onReorderAction(step.key, actionIndex, actionIndex + 1)}
                                        title="Move later in execution pipeline"
                                    >
                                        <ArrowDown className="size-3" /> Later
                                    </Button>
                                </div>
                            )}
                        </div>
                    </div>

                    {/* Specialized Email Configuration (if enrollment.notify) */}
                    {action.handler === "enrollment.notify" ? (
                        <div className="space-y-3.5 rounded-xl border border-sky-500/30 bg-sky-500/5 p-4">
                            <div className="flex items-center gap-2">
                                <Mail className="size-4 text-sky-500" />
                                <Label className="text-foreground text-xs font-semibold">Confirmation Email Dispatch</Label>
                            </div>

                            <div className="space-y-1.5">
                                <Label className="text-muted-foreground text-[11px] font-medium">Select Email Template</Label>
                                <Select value={notificationType} onValueChange={(val) => handleConfigChange({ ...cfg, notification: val })}>
                                    <SelectTrigger className="bg-background h-8.5 text-xs">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="assessment" className="text-xs">
                                            Official Enrollment & Assessment Certificate (with PDF)
                                        </SelectItem>
                                        <SelectItem value="academic_verified" className="text-xs">
                                            Academic Clearance Notice (Subjects & Fees breakdown)
                                        </SelectItem>
                                        <SelectItem value="status_update" className="text-xs">
                                            Status Progression Notice (General update)
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            {/* Template Summary Card */}
                            <div className="bg-background/90 border-border/60 space-y-2 rounded-lg border p-3 text-[11px]">
                                <div className="flex items-center justify-between">
                                    <span className="text-foreground font-semibold">Active Template Content</span>
                                    {Boolean(cfg.subject || cfg.custom_body) && (
                                        <span className="flex items-center gap-1 text-[10px] font-medium text-emerald-600 dark:text-emerald-400">
                                            <Sparkles className="size-3" /> Customized
                                        </span>
                                    )}
                                </div>
                                <div className="text-muted-foreground space-y-1">
                                    <p className="truncate font-mono text-[10px]">
                                        <strong className="text-foreground">Subject:</strong> {String(cfg.subject || "Default template subject")}
                                    </p>
                                    {Boolean(cfg.headline) && (
                                        <p className="truncate text-[10px]">
                                            <strong className="text-foreground">Headline:</strong> {String(cfg.headline)}
                                        </p>
                                    )}
                                </div>
                            </div>

                            {/* Primary Button to Open the Full-Page Editor */}
                            <Button
                                type="button"
                                variant="default"
                                size="sm"
                                className="h-9 w-full gap-2 text-xs font-semibold shadow-sm"
                                onClick={() => onEditEmailTemplate?.(step.key)}
                            >
                                <Sliders className="size-3.5" /> Open Email Template Editor
                            </Button>

                            <p className="text-muted-foreground bg-background/80 border-border/50 rounded-lg border p-2.5 text-[11px] leading-relaxed">
                                {notificationType === "assessment" &&
                                    "Dispatches the official assessment confirmation email to the student with their student number, portal access instructions, and the official assessment certificate PDF attached."}
                                {notificationType === "academic_verified" &&
                                    "Dispatches an academic clearance email with verified curriculum subject units, tuition balance estimate, and instructions for cashier settlement."}
                                {notificationType === "status_update" &&
                                    `Notifies the applicant that their enrollment status has updated to: ${step.status}.`}
                                {notificationType === "custom" &&
                                    "Dispatches a bespoke custom notification customized with your tailored subject, headline, and message body."}
                            </p>
                        </div>
                    ) : (
                        /* Standard Schema Fields Configuration */
                        <div className="border-border/60 bg-muted/15 space-y-3 rounded-xl border p-4">
                            <div>
                                <Label className="text-foreground text-xs font-semibold">Action Parameters</Label>
                                <p className="text-muted-foreground mt-0.5 text-[10px]">{meta.desc}</p>
                            </div>

                            <div className="border-border/40 border-t pt-2">
                                <SchemaFields
                                    item={actions[action.handler]}
                                    value={action.configuration}
                                    options={options}
                                    onChange={handleConfigChange}
                                />
                            </div>
                        </div>
                    )}

                    {/* Technical Identifier */}
                    <div className="border-border/50 bg-background/50 space-y-1 rounded-xl border p-3">
                        <span className="text-muted-foreground font-mono text-[10px] tracking-wider uppercase">Handler Key</span>
                        <p className="text-foreground font-mono text-xs font-semibold">{action.handler}</p>
                    </div>

                    {/* Detach Action Button */}
                    <div className="border-border/40 border-t pt-4">
                        <Button
                            type="button"
                            variant="destructive"
                            size="sm"
                            className="h-8.5 w-full gap-1.5 text-xs"
                            onClick={() => {
                                onDeleteAction(step.key, actionIndex);
                                onOpenChange(false);
                            }}
                        >
                            <Trash2 className="size-3.5" /> Detach from {step.label}
                        </Button>
                    </div>
                </div>
            </SheetContent>
        </Sheet>
    );
}
