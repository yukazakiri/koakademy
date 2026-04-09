import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Combobox, type ComboboxOption } from "@/components/ui/combobox";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Separator } from "@/components/ui/separator";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { useForm } from "@inertiajs/react";
import { AnimatePresence, motion } from "framer-motion";
import { LayoutDashboard, Loader2, MoveDown, MoveUp, PieChart, Plus, Save, Settings, Split, Trash2, Workflow } from "lucide-react";
import { useMemo, useState } from "react";

import { submitSystemForm } from "./form-submit";
import SystemManagementLayout from "./layout";
import type {
    EnrollmentPipelineActionType,
    EnrollmentPipelineStep,
    EnrollmentStatMetric,
    EnrollmentStatsCard,
    SystemManagementPageProps,
} from "./types";

interface EnrollmentPipelineFormData {
    submitted_label: string;
    entry_step_key: string;
    completion_step_key: string;
    steps: EnrollmentPipelineStep[];
    enrollment_stats: {
        cards: EnrollmentStatsCard[];
    };
}

const colorOptions = ["yellow", "blue", "green", "emerald", "teal", "gray", "amber", "red", "indigo", "orange"] as const;

const stepTypeOptions: Array<{ value: EnrollmentPipelineActionType; label: string }> = [
    { value: "standard", label: "Standard Step" },
    { value: "department_verification", label: "Verification Step" },
    { value: "cashier_verification", label: "Payment Verification Step" },
];

const statsMetricOptions: Array<{ value: EnrollmentStatMetric; label: string }> = [
    { value: "total_records", label: "Total Records" },
    { value: "active_records", label: "Active Records" },
    { value: "trashed_records", label: "Deleted Records" },
    { value: "status_count", label: "Status Count" },
    { value: "paid_count", label: "Fully Paid Count" },
];

export default function SystemManagementEnrollmentPipelinePage({
    user,
    enrollment_pipeline,
    enrollment_stats,
    available_roles,
    access,
}: SystemManagementPageProps) {
    const initialSteps = enrollment_pipeline?.steps || [];

    const pipelineForm = useForm<EnrollmentPipelineFormData>({
        submitted_label: enrollment_pipeline?.submitted_label || "Submitted",
        entry_step_key: enrollment_pipeline?.entry_step_key || initialSteps[0]?.key || "",
        completion_step_key: enrollment_pipeline?.completion_step_key || initialSteps[initialSteps.length - 1]?.key || "",
        steps: initialSteps.map((step) => ({
            key: step.key,
            status: step.status,
            label: step.label,
            color: step.color || "indigo",
            allowed_roles: step.allowed_roles || [],
            action_type: step.action_type || "standard",
        })),
        enrollment_stats: {
            cards: enrollment_stats?.cards || [],
        },
    });

    const [selectedStepIndex, setSelectedStepIndex] = useState<number | null>(initialSteps.length > 0 ? 0 : null);
    const [selectedRoleByStep, setSelectedRoleByStep] = useState<Record<number, string>>({});

    const roleComboboxOptions: ComboboxOption[] = useMemo(
        () =>
            available_roles.map((roleName) => ({
                value: roleName,
                label: roleName,
                searchText: roleName,
            })),
        [available_roles],
    );

    const updatePipelineStep = (index: number, field: keyof EnrollmentPipelineStep, value: string | string[]) => {
        const steps = [...pipelineForm.data.steps];
        if (!steps[index]) {
            return;
        }

        if (field === "allowed_roles") {
            steps[index].allowed_roles = value as string[];
        } else if (field === "action_type") {
            steps[index].action_type = value as EnrollmentPipelineActionType;
        } else {
            steps[index][field] = value as never;
        }

        pipelineForm.setData("steps", steps);
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

        updatePipelineStep(index, "allowed_roles", [...roles, selectedRole]);
        setSelectedRoleByStep((current) => ({ ...current, [index]: "" }));
    };

    const removeRoleFromStep = (index: number, roleName: string) => {
        const roles = pipelineForm.data.steps[index]?.allowed_roles || [];
        updatePipelineStep(
            index,
            "allowed_roles",
            roles.filter((role) => role !== roleName),
        );
    };

    const addPipelineStep = () => {
        const nextIndex = pipelineForm.data.steps.length + 1;
        pipelineForm.setData("steps", [
            ...pipelineForm.data.steps,
            {
                key: `step_${nextIndex}`,
                status: "",
                label: `New Step ${nextIndex}`,
                color: "indigo",
                allowed_roles: [],
                action_type: "standard",
            },
        ]);
        setSelectedStepIndex(pipelineForm.data.steps.length);
    };

    const removePipelineStep = (index: number) => {
        const steps = pipelineForm.data.steps.filter((_, stepIndex) => stepIndex !== index);
        pipelineForm.setData("steps", steps);

        if (pipelineForm.data.entry_step_key === pipelineForm.data.steps[index]?.key) {
            pipelineForm.setData("entry_step_key", steps[0]?.key || "");
        }
        if (pipelineForm.data.completion_step_key === pipelineForm.data.steps[index]?.key) {
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

    const updateStatsCard = (index: number, field: keyof EnrollmentStatsCard, value: string | string[]) => {
        const cards = [...pipelineForm.data.enrollment_stats.cards];
        if (!cards[index]) {
            return;
        }

        if (field === "statuses") {
            cards[index].statuses = value as string[];
        } else if (field === "metric") {
            cards[index].metric = value as EnrollmentStatMetric;
        } else {
            cards[index][field] = value as never;
        }

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

    return (
        <SystemManagementLayout
            user={user}
            access={access}
            activeSection="pipeline"
            heading="Enrollment Pipeline"
            description="Define workflow steps, role gates, and enrollment dashboard metric cards."
        >
            <div className="mb-6 flex items-center justify-end">
                <Button
                    onClick={() =>
                        submitSystemForm({
                            form: pipelineForm,
                            routeName: "administrators.system-management.enrollment-pipeline.update",
                            successMessage: "Enrollment pipeline updated successfully.",
                            errorMessage: "Failed to update enrollment pipeline.",
                        })
                    }
                    disabled={pipelineForm.processing}
                    size="lg"
                    className="shadow-sm transition-all hover:shadow-md"
                >
                    {pipelineForm.processing ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <Save className="mr-2 h-4 w-4" />}
                    Save Pipeline
                </Button>
            </div>

            <Tabs defaultValue="workflow" className="w-full space-y-6">
                <div className="flex w-full items-center justify-between border-b pb-0">
                    <TabsList className="h-auto gap-6 bg-transparent p-0">
                        <TabsTrigger
                            value="workflow"
                            className="text-muted-foreground data-[state=active]:border-primary data-[state=active]:text-foreground flex items-center gap-2 rounded-none border-b-2 border-transparent px-2 py-3 font-medium transition-all data-[state=active]:shadow-none"
                        >
                            <Workflow className="h-4 w-4" />
                            Pipeline Steps
                        </TabsTrigger>
                        <TabsTrigger
                            value="analytics"
                            className="text-muted-foreground data-[state=active]:border-primary data-[state=active]:text-foreground flex items-center gap-2 rounded-none border-b-2 border-transparent px-2 py-3 font-medium transition-all data-[state=active]:shadow-none"
                        >
                            <PieChart className="h-4 w-4" />
                            Analytics Cards
                        </TabsTrigger>
                    </TabsList>
                </div>

                <TabsContent value="workflow" className="animate-in fade-in-50 space-y-6 duration-500 outline-none">
                    <Card className="border-primary/10 bg-primary/5 shadow-none">
                        <CardHeader className="pb-4">
                            <div className="flex items-center gap-2">
                                <Settings className="text-primary h-5 w-5" />
                                <CardTitle className="text-lg">General Settings</CardTitle>
                            </div>
                            <CardDescription>Set the starting and ending points for the enrollment process.</CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-6 md:grid-cols-3">
                            <div className="space-y-2">
                                <Label htmlFor="submitted_label">"Submitted" Status Name</Label>
                                <Input
                                    id="submitted_label"
                                    value={pipelineForm.data.submitted_label}
                                    onChange={(event) => pipelineForm.setData("submitted_label", event.target.value)}
                                    className="bg-background"
                                />
                                <p className="text-muted-foreground text-[10px]">The status name before processing begins.</p>
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="entry_step_key">Starting Step</Label>
                                <Select
                                    value={pipelineForm.data.entry_step_key}
                                    onValueChange={(value) => pipelineForm.setData("entry_step_key", value)}
                                >
                                    <SelectTrigger id="entry_step_key" className="bg-background">
                                        <SelectValue placeholder="Select step" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {pipelineForm.data.steps.map((step, index) => (
                                            <SelectItem key={`entry-${step.key || index}`} value={step.key || `step_${index + 1}`}>
                                                {step.label || step.status || `Step ${index + 1}`}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <p className="text-muted-foreground text-[10px]">The first step an applicant lands on.</p>
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="completion_step_key">Final Completion Step</Label>
                                <Select
                                    value={pipelineForm.data.completion_step_key}
                                    onValueChange={(value) => pipelineForm.setData("completion_step_key", value)}
                                >
                                    <SelectTrigger id="completion_step_key" className="bg-background">
                                        <SelectValue placeholder="Select step" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {pipelineForm.data.steps.map((step, index) => (
                                            <SelectItem key={`comp-${step.key || index}`} value={step.key || `step_${index + 1}`}>
                                                {step.label || step.status || `Step ${index + 1}`}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <p className="text-muted-foreground text-[10px]">The step when enrollment is fully completed.</p>
                            </div>
                        </CardContent>
                    </Card>

                    <div className="flex flex-col items-start gap-8 lg:flex-row">
                        {/* Visual Master Pipeline */}
                        <div className="flex w-full shrink-0 flex-col gap-5 lg:w-[40%] xl:w-1/3">
                            <div className="flex items-center justify-between">
                                <h3 className="flex items-center gap-2 text-lg font-semibold">
                                    <Split className="text-muted-foreground h-5 w-5" /> Visual Flow
                                </h3>
                                <Button variant="outline" size="sm" onClick={() => addPipelineStep()} className="h-9">
                                    <Plus className="mr-2 h-4 w-4" />
                                    Add Step
                                </Button>
                            </div>

                            <div className="relative space-y-3 pb-8 pl-5">
                                {/* Timeline vertical line */}
                                <div className="bg-border/80 absolute top-6 bottom-4 left-[35px] w-[2px] rounded-full" />

                                <AnimatePresence mode="popLayout">
                                    {pipelineForm.data.steps.map((step, index) => {
                                        const isSelected = selectedStepIndex === index;
                                        const isEntry = pipelineForm.data.entry_step_key === step.key;
                                        const isCompletion = pipelineForm.data.completion_step_key === step.key;

                                        return (
                                            <motion.div
                                                layout
                                                initial={{ opacity: 0, y: 15 }}
                                                animate={{ opacity: 1, y: 0 }}
                                                exit={{ opacity: 0, scale: 0.95 }}
                                                transition={{ duration: 0.2 }}
                                                key={`${step.key}-${index}`}
                                                className="group relative z-10 flex items-center gap-4"
                                            >
                                                <div
                                                    className={`flex h-8 w-8 shrink-0 items-center justify-center rounded-full border-2 text-sm font-bold transition-all ${
                                                        isSelected
                                                            ? "bg-primary border-primary text-primary-foreground z-20 scale-110 shadow-md"
                                                            : "bg-background border-border text-muted-foreground group-hover:border-primary/50 group-hover:text-primary z-10"
                                                    }`}
                                                >
                                                    {index + 1}
                                                </div>
                                                <button
                                                    type="button"
                                                    onClick={() => setSelectedStepIndex(index)}
                                                    className={`flex-1 rounded-xl border p-3.5 text-left transition-all ${
                                                        isSelected
                                                            ? "ring-primary border-primary bg-primary/5 shadow-sm ring-2"
                                                            : "border-border bg-card hover:border-primary/40 hover:bg-accent/50 hover:shadow-sm"
                                                    } focus-visible:ring-primary/50 focus-visible:ring-2 focus-visible:outline-none`}
                                                >
                                                    <div className="mb-1 flex items-start justify-between gap-2">
                                                        <span className="line-clamp-2 text-sm leading-tight font-medium">
                                                            {step.label || `Step ${index + 1}`}
                                                        </span>
                                                        <div className="flex shrink-0 flex-col gap-1">
                                                            {isEntry && (
                                                                <Badge variant="default" className="px-1.5 py-0 text-[10px] leading-tight">
                                                                    Entry
                                                                </Badge>
                                                            )}
                                                            {isCompletion && (
                                                                <Badge
                                                                    variant="secondary"
                                                                    className="bg-muted-foreground text-primary-foreground px-1.5 py-0 text-[10px] leading-tight"
                                                                >
                                                                    End
                                                                </Badge>
                                                            )}
                                                        </div>
                                                    </div>
                                                    <div className="text-muted-foreground mt-1 flex items-center gap-1.5 truncate text-xs">
                                                        {step.status || "No Status Defined"}
                                                    </div>
                                                </button>
                                            </motion.div>
                                        );
                                    })}
                                </AnimatePresence>
                                {pipelineForm.data.steps.length === 0 && (
                                    <div className="text-muted-foreground bg-muted/40 ml-12 rounded-xl border-2 border-dashed p-8 text-center text-sm">
                                        <Workflow className="text-muted-foreground/50 mx-auto mb-3 h-8 w-8" />
                                        <p>No steps defined.</p>
                                        <p className="mt-1">Add a step to begin generating your visual pipeline.</p>
                                    </div>
                                )}
                            </div>
                        </div>

                        {/* Detail Editor Form */}
                        <div className="w-full flex-1 lg:w-[60%] xl:w-2/3">
                            <AnimatePresence mode="wait">
                                {selectedStepIndex !== null && pipelineForm.data.steps[selectedStepIndex] ? (
                                    <motion.div
                                        key={`editor-${selectedStepIndex}`}
                                        initial={{ opacity: 0, x: 20 }}
                                        animate={{ opacity: 1, x: 0 }}
                                        exit={{ opacity: 0, x: -20 }}
                                        transition={{ duration: 0.2 }}
                                    >
                                        <Card className="relative overflow-hidden border shadow-md">
                                            <div className="bg-primary absolute top-0 left-0 h-full w-1.5" />
                                            <CardHeader className="bg-muted/20 border-b pb-4">
                                                <div className="flex flex-wrap items-center justify-between gap-4">
                                                    <div>
                                                        <div className="mb-1 flex items-center gap-2">
                                                            <div className="bg-primary/10 text-primary flex h-6 w-6 items-center justify-center rounded-md font-mono text-xs font-bold">
                                                                {selectedStepIndex + 1}
                                                            </div>
                                                            <CardTitle className="text-lg">Step Details</CardTitle>
                                                        </div>
                                                        <CardDescription>
                                                            Define what happens during{" "}
                                                            <strong className="text-foreground">
                                                                {pipelineForm.data.steps[selectedStepIndex].label || `Step ${selectedStepIndex + 1}`}
                                                            </strong>
                                                            .
                                                        </CardDescription>
                                                    </div>
                                                    <div className="bg-background flex items-center gap-1 rounded-md border p-1">
                                                        <Button
                                                            type="button"
                                                            size="icon"
                                                            variant="ghost"
                                                            className="text-muted-foreground hover:text-foreground h-8 w-8"
                                                            onClick={() => movePipelineStep(selectedStepIndex, "up")}
                                                            disabled={selectedStepIndex === 0}
                                                            title="Move Step Up"
                                                        >
                                                            <MoveUp className="h-4 w-4" />
                                                        </Button>
                                                        <Button
                                                            type="button"
                                                            size="icon"
                                                            variant="ghost"
                                                            className="text-muted-foreground hover:text-foreground h-8 w-8"
                                                            onClick={() => movePipelineStep(selectedStepIndex, "down")}
                                                            disabled={selectedStepIndex === pipelineForm.data.steps.length - 1}
                                                            title="Move Step Down"
                                                        >
                                                            <MoveDown className="h-4 w-4" />
                                                        </Button>
                                                        <div className="bg-border mx-1 h-5 w-px" />
                                                        <Button
                                                            type="button"
                                                            size="icon"
                                                            variant="ghost"
                                                            className="text-destructive hover:bg-destructive/10 hover:text-destructive h-8 w-8"
                                                            onClick={() => removePipelineStep(selectedStepIndex)}
                                                            title="Delete Step"
                                                        >
                                                            <Trash2 className="h-4 w-4" />
                                                        </Button>
                                                    </div>
                                                </div>
                                            </CardHeader>
                                            <CardContent className="space-y-6 pt-6">
                                                <div className="grid gap-5 md:grid-cols-2">
                                                    <div className="space-y-2">
                                                        <Label className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">
                                                            Internal Step ID
                                                        </Label>
                                                        <Input
                                                            value={pipelineForm.data.steps[selectedStepIndex].key}
                                                            onChange={(event) => updatePipelineStep(selectedStepIndex, "key", event.target.value)}
                                                            className="font-mono text-sm"
                                                            placeholder="e.g. step_1"
                                                        />
                                                    </div>
                                                    <div className="space-y-2">
                                                        <Label className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">
                                                            Badge Color
                                                        </Label>
                                                        <Select
                                                            value={pipelineForm.data.steps[selectedStepIndex].color}
                                                            onValueChange={(value) => updatePipelineStep(selectedStepIndex, "color", value)}
                                                        >
                                                            <SelectTrigger>
                                                                <SelectValue />
                                                            </SelectTrigger>
                                                            <SelectContent>
                                                                {colorOptions.map((color) => (
                                                                    <SelectItem key={color} value={color}>
                                                                        <div className="flex items-center gap-2 capitalize">
                                                                            <div
                                                                                className={`h-3 w-3 rounded-full bg-${color}-500`}
                                                                                style={{
                                                                                    backgroundColor:
                                                                                        color === "yellow"
                                                                                            ? "#facc15"
                                                                                            : color === "red"
                                                                                              ? "#ef4444"
                                                                                              : color === "blue"
                                                                                                ? "#3b82f6"
                                                                                                : color === "green"
                                                                                                  ? "#22c55e"
                                                                                                  : color === "indigo"
                                                                                                    ? "#6366f1"
                                                                                                    : "currentColor",
                                                                                }}
                                                                            />
                                                                            {color}
                                                                        </div>
                                                                    </SelectItem>
                                                                ))}
                                                            </SelectContent>
                                                        </Select>
                                                    </div>
                                                    <div className="space-y-2 md:col-span-2">
                                                        <Label className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">
                                                            Applicant-Facing Name
                                                        </Label>
                                                        <Input
                                                            value={pipelineForm.data.steps[selectedStepIndex].label}
                                                            onChange={(event) => updatePipelineStep(selectedStepIndex, "label", event.target.value)}
                                                            placeholder="e.g. Awaiting Verification"
                                                        />
                                                        <p className="text-muted-foreground mt-1 text-[10px]">What the applicant sees.</p>
                                                    </div>
                                                    <div className="space-y-2 md:col-span-2">
                                                        <Label className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">
                                                            Internal Status Code
                                                        </Label>
                                                        <Input
                                                            value={pipelineForm.data.steps[selectedStepIndex].status}
                                                            onChange={(event) => updatePipelineStep(selectedStepIndex, "status", event.target.value)}
                                                            placeholder="e.g. pending_verification"
                                                            className="font-mono text-sm"
                                                        />
                                                        <p className="text-muted-foreground mt-1 text-[10px]">
                                                            How the system tracks this status in the database.
                                                        </p>
                                                    </div>
                                                    <div className="space-y-2 md:col-span-2">
                                                        <Label className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">
                                                            Special Behavior
                                                        </Label>
                                                        <Select
                                                            value={pipelineForm.data.steps[selectedStepIndex].action_type}
                                                            onValueChange={(value) =>
                                                                updatePipelineStep(
                                                                    selectedStepIndex,
                                                                    "action_type",
                                                                    value as EnrollmentPipelineActionType,
                                                                )
                                                            }
                                                        >
                                                            <SelectTrigger>
                                                                <SelectValue />
                                                            </SelectTrigger>
                                                            <SelectContent>
                                                                {stepTypeOptions.map((option) => (
                                                                    <SelectItem key={option.value} value={option.value}>
                                                                        {option.label}
                                                                    </SelectItem>
                                                                ))}
                                                            </SelectContent>
                                                        </Select>
                                                        <p className="text-muted-foreground mt-1 text-[10px]">
                                                            Determines if this step requires specific system actions (like cashier verification).
                                                        </p>
                                                    </div>
                                                </div>

                                                <Separator />

                                                <div className="bg-muted/30 hover:border-border space-y-4 rounded-xl border border-dashed p-5 transition-colors">
                                                    <div>
                                                        <Label className="text-foreground flex items-center gap-2 text-base font-semibold">
                                                            Staff Permissions
                                                        </Label>
                                                        <p className="text-muted-foreground mt-1 mb-4 text-sm">
                                                            Only these staff roles can approve or modify applications in this step.
                                                        </p>
                                                    </div>

                                                    <div className="flex flex-col items-stretch gap-2 sm:flex-row sm:items-center">
                                                        <div className="bg-background flex-1 rounded-md shadow-sm">
                                                            <Combobox
                                                                options={roleComboboxOptions}
                                                                value={selectedRoleByStep[selectedStepIndex] || ""}
                                                                onValueChange={(value) =>
                                                                    setSelectedRoleByStep((current) => ({ ...current, [selectedStepIndex]: value }))
                                                                }
                                                                placeholder="Select role to add..."
                                                                searchPlaceholder="Search roles..."
                                                                emptyText="No role found."
                                                            />
                                                        </div>
                                                        <Button
                                                            type="button"
                                                            variant="secondary"
                                                            onClick={() => addRoleToStep(selectedStepIndex)}
                                                            disabled={!selectedRoleByStep[selectedStepIndex]}
                                                            className="shadow-sm"
                                                        >
                                                            <Plus className="mr-1.5 h-4 w-4" />
                                                            Add Role
                                                        </Button>
                                                    </div>

                                                    <div className="mt-4">
                                                        {pipelineForm.data.steps[selectedStepIndex].allowed_roles.length === 0 ? (
                                                            <div className="border-primary/50 bg-background text-muted-foreground rounded-r-md border-l-2 py-1.5 pl-3 text-sm">
                                                                <span className="text-foreground font-medium">Public Access:</span> No role
                                                                restrictions applied.
                                                            </div>
                                                        ) : (
                                                            <div className="flex flex-wrap gap-2 pt-1">
                                                                <AnimatePresence>
                                                                    {pipelineForm.data.steps[selectedStepIndex].allowed_roles.map((roleName) => (
                                                                        <motion.div
                                                                            key={roleName}
                                                                            initial={{ opacity: 0, scale: 0.8 }}
                                                                            animate={{ opacity: 1, scale: 1 }}
                                                                            exit={{ opacity: 0, scale: 0.8 }}
                                                                            transition={{ duration: 0.15 }}
                                                                        >
                                                                            <Badge
                                                                                variant="secondary"
                                                                                className="bg-background hover:bg-background gap-2 border py-1.5 pr-1.5 pl-3 text-sm shadow-sm"
                                                                            >
                                                                                {roleName}
                                                                                <button
                                                                                    type="button"
                                                                                    onClick={() => removeRoleFromStep(selectedStepIndex, roleName)}
                                                                                    className="text-muted-foreground hover:text-destructive hover:bg-destructive/10 rounded-md p-1 transition-colors"
                                                                                    title="Remove Role"
                                                                                >
                                                                                    <Trash2 className="h-3.5 w-3.5" />
                                                                                </button>
                                                                            </Badge>
                                                                        </motion.div>
                                                                    ))}
                                                                </AnimatePresence>
                                                            </div>
                                                        )}
                                                    </div>
                                                </div>
                                            </CardContent>
                                        </Card>
                                    </motion.div>
                                ) : (
                                    <motion.div
                                        key="empty-editor"
                                        initial={{ opacity: 0 }}
                                        animate={{ opacity: 1 }}
                                        className="bg-muted/10 text-muted-foreground flex h-full min-h-[400px] items-center justify-center rounded-xl border-2 border-dashed p-8"
                                    >
                                        <div className="max-w-sm text-center">
                                            <div className="bg-muted mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full border shadow-sm">
                                                <LayoutDashboard className="text-muted-foreground/60 h-8 w-8" />
                                            </div>
                                            <p className="text-foreground text-lg font-medium">Select a Step to Edit</p>
                                            <p className="text-muted-foreground/80 mt-2 text-sm">
                                                Click any step line from the visual flow on the left to change its name, color, and staff permissions,
                                                or add a new step.
                                            </p>
                                        </div>
                                    </motion.div>
                                )}
                            </AnimatePresence>
                        </div>
                    </div>
                </TabsContent>

                <TabsContent value="analytics" className="animate-in fade-in-50 space-y-6 duration-500 outline-none">
                    <div className="flex items-center justify-between border-b pb-4">
                        <div>
                            <h3 className="text-xl font-semibold">Enrollment Stats Cards</h3>
                            <p className="text-muted-foreground mt-1 text-sm">
                                Cards from this configuration appear in enrollment analytics widgets.
                            </p>
                        </div>
                        <Button type="button" onClick={addStatsCard} className="h-9 shadow-sm">
                            <Plus className="mr-2 h-4 w-4" />
                            Add Metric Card
                        </Button>
                    </div>

                    {pipelineForm.data.enrollment_stats.cards.length === 0 ? (
                        <div className="text-muted-foreground bg-muted/10 flex min-h-[300px] flex-col items-center justify-center rounded-xl border-2 border-dashed p-16">
                            <PieChart className="text-muted-foreground/40 mb-5 h-12 w-12" />
                            <p className="text-foreground text-lg font-medium">No metrics cards configured</p>
                            <p className="text-muted-foreground/80 mt-2 max-w-sm text-center text-sm">
                                Add cards to display insightful metrics and visual reports at a glance on your admin dashboard.
                            </p>
                            <Button variant="outline" onClick={addStatsCard} className="mt-6">
                                Create First Card
                            </Button>
                        </div>
                    ) : (
                        <div className="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
                            <AnimatePresence mode="popLayout">
                                {pipelineForm.data.enrollment_stats.cards.map((card, index) => (
                                    <motion.div
                                        layout
                                        initial={{ opacity: 0, scale: 0.95 }}
                                        animate={{ opacity: 1, scale: 1 }}
                                        exit={{ opacity: 0, scale: 0.95 }}
                                        transition={{ duration: 0.2 }}
                                        key={`${card.key}-${index}`}
                                    >
                                        <Card className="group relative flex h-full flex-col overflow-hidden border shadow-sm transition-all hover:shadow-md">
                                            <div
                                                className="absolute top-0 left-0 h-1 w-full"
                                                style={{
                                                    backgroundColor:
                                                        card.color === "yellow"
                                                            ? "#facc15"
                                                            : card.color === "red"
                                                              ? "#ef4444"
                                                              : card.color === "blue"
                                                                ? "#3b82f6"
                                                                : card.color === "emerald"
                                                                  ? "#10b981"
                                                                  : card.color === "green"
                                                                    ? "#22c55e"
                                                                    : card.color === "indigo"
                                                                      ? "#6366f1"
                                                                      : card.color === "teal"
                                                                        ? "#14b8a6"
                                                                        : card.color === "amber"
                                                                          ? "#f59e0b"
                                                                          : card.color === "orange"
                                                                            ? "#f97316"
                                                                            : "#6b7280",
                                                }}
                                            />
                                            <CardHeader className="pt-5 pb-3">
                                                <div className="flex items-start justify-between gap-4">
                                                    <div className="w-full space-y-1.5">
                                                        <Input
                                                            value={card.label}
                                                            onChange={(event) => updateStatsCard(index, "label", event.target.value)}
                                                            placeholder="Card Title"
                                                            className="hover:border-border focus:border-border -ml-1 h-9 border-transparent bg-transparent px-1 text-lg font-semibold shadow-none transition-colors"
                                                        />
                                                        <div className="text-muted-foreground flex items-center gap-2 px-1 text-xs">
                                                            <span className="bg-muted/60 rounded border px-1.5 py-0.5 font-mono text-[10px]">
                                                                {card.key || "no-key"}
                                                            </span>
                                                        </div>
                                                    </div>
                                                    <Button
                                                        type="button"
                                                        size="icon"
                                                        variant="ghost"
                                                        onClick={() => removeStatsCard(index)}
                                                        className="text-muted-foreground hover:text-destructive hover:bg-destructive/10 shrink-0 opacity-0 transition-opacity group-hover:opacity-100"
                                                        title="Delete Card"
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                    </Button>
                                                </div>
                                            </CardHeader>
                                            <CardContent className="flex-1 space-y-5 pt-2">
                                                <div className="grid grid-cols-2 gap-4">
                                                    <div className="space-y-1.5">
                                                        <Label className="text-muted-foreground text-xs font-semibold tracking-wide uppercase">
                                                            Metric Type
                                                        </Label>
                                                        <Select
                                                            value={card.metric}
                                                            onValueChange={(value) => updateStatsCard(index, "metric", value)}
                                                        >
                                                            <SelectTrigger className="bg-background h-9 text-sm shadow-sm">
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
                                                    <div className="mt-auto space-y-1.5 align-bottom">
                                                        <Label className="text-muted-foreground invisible hidden text-xs font-semibold tracking-wide uppercase lg:block">
                                                            _
                                                        </Label>
                                                        <div className="group/color bg-background focus-within:ring-ring relative flex h-9 items-center rounded-md border px-3 shadow-sm focus-within:ring-2 focus-within:ring-offset-2">
                                                            <div
                                                                className="border-border/50 mr-2 h-3 w-3 shrink-0 rounded-full border"
                                                                style={{
                                                                    backgroundColor:
                                                                        card.color === "yellow"
                                                                            ? "#facc15"
                                                                            : card.color === "red"
                                                                              ? "#ef4444"
                                                                              : card.color === "blue"
                                                                                ? "#3b82f6"
                                                                                : card.color === "emerald"
                                                                                  ? "#10b981"
                                                                                  : card.color === "green"
                                                                                    ? "#22c55e"
                                                                                    : card.color === "indigo"
                                                                                      ? "#6366f1"
                                                                                      : card.color === "teal"
                                                                                        ? "#14b8a6"
                                                                                        : card.color === "amber"
                                                                                          ? "#f59e0b"
                                                                                          : card.color === "orange"
                                                                                            ? "#f97316"
                                                                                            : "#6b7280",
                                                                }}
                                                            />
                                                            <Select
                                                                value={card.color}
                                                                onValueChange={(value) => updateStatsCard(index, "color", value)}
                                                            >
                                                                <SelectTrigger className="h-full w-full flex-1 border-0 p-0 text-sm capitalize shadow-none focus:ring-0">
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
                                                    <div className="col-span-2 space-y-1.5">
                                                        <Label className="text-muted-foreground text-xs font-semibold tracking-wide uppercase">
                                                            Card Unique Key
                                                        </Label>
                                                        <Input
                                                            value={card.key}
                                                            onChange={(event) => updateStatsCard(index, "key", event.target.value)}
                                                            className="bg-background h-9 font-mono text-sm shadow-sm"
                                                            placeholder="e.g. total_enrolled"
                                                        />
                                                    </div>
                                                </div>

                                                {card.metric === "status_count" && (
                                                    <div className="mt-2 space-y-2.5 border-t pt-4">
                                                        <Label className="text-muted-foreground flex items-center gap-1.5 text-xs font-semibold tracking-wide uppercase">
                                                            Tracked Pipeline Statuses
                                                        </Label>
                                                        <div className="flex flex-wrap gap-2">
                                                            {pipelineForm.data.steps.length === 0 ? (
                                                                <p className="text-muted-foreground bg-muted/50 w-full rounded-md border border-dashed p-2 text-xs italic">
                                                                    No pipeline steps defined.
                                                                </p>
                                                            ) : (
                                                                pipelineForm.data.steps.map((step, stepIndex) => {
                                                                    const statusLabel = step.status || step.label || `Step ${stepIndex + 1}`;
                                                                    const isSelected = card.statuses.includes(step.status);
                                                                    return (
                                                                        <button
                                                                            key={`status-${statusLabel}-${stepIndex}`}
                                                                            type="button"
                                                                            className={`rounded-md border px-2.5 py-1.5 text-xs transition-all ${
                                                                                isSelected
                                                                                    ? `bg-primary text-primary-foreground border-primary font-medium shadow-sm`
                                                                                    : `bg-background text-muted-foreground hover:bg-muted hover:text-foreground`
                                                                            }`}
                                                                            onClick={() => toggleStatsCardStatus(index, step.status)}
                                                                        >
                                                                            {statusLabel}
                                                                        </button>
                                                                    );
                                                                })
                                                            )}
                                                        </div>
                                                    </div>
                                                )}
                                            </CardContent>
                                        </Card>
                                    </motion.div>
                                ))}
                            </AnimatePresence>
                        </div>
                    )}
                </TabsContent>
            </Tabs>
        </SystemManagementLayout>
    );
}
