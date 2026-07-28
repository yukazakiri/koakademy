import {
    activate,
    exportMethod,
    importMethod,
    publish,
    rollback,
    simulate,
} from "@/actions/App/Http/Controllers/AdministratorEnrollmentPolicyController";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from "@/components/ui/alert-dialog";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { MultiSelect } from "@/components/ui/multi-select";
import { Textarea } from "@/components/ui/textarea";
import { cn } from "@/lib/utils";
import type { FormDataConvertible } from "@inertiajs/core";
import { router, useForm } from "@inertiajs/react";
import axios from "axios";
import {
    AlertTriangle,
    ArrowRight,
    Check,
    CheckCircle2,
    Download,
    FileUp,
    GitBranch,
    History,
    Layers3,
    Loader2,
    Plus,
    RotateCcw,
    ShieldCheck,
    Sparkles,
    Trash2,
} from "lucide-react";
import { useState } from "react";
import { copy, operatorDefaults, schemaHelp, sectionSource } from "../configuration";
import type {
    BlueprintStepId,
    Configuration,
    EffectiveConfiguration,
    HelpTopic,
    InheritanceResponse,
    JsonObject,
    Option,
    Policy,
    PolicyVersion,
    RegistryItem,
    RegistryManifest,
    Rollout,
    Simulation,
} from "../types";
import { HelpButton } from "./help-drawer";
import { RuleEditor } from "./rule-editor";
import { OptionSelect, SchemaFields } from "./schema-fields";
import { SimulationReport } from "./simulation-report";
import { WorkflowEditor } from "./workflow-editor";

export function ScopeSection({
    policy,
    version,
    inheritance,
    loading,
    documentationUrl,
}: {
    policy: Policy;
    version: PolicyVersion;
    inheritance: InheritanceResponse;
    loading: boolean;
    documentationUrl: string;
}) {
    const scopeEntries = Object.entries(policy.scope);

    return (
        <section className="space-y-6">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div className="max-w-2xl">
                    <h2 className="text-2xl font-semibold tracking-tight text-balance">Template, coverage, and versions</h2>
                    <p className="text-muted-foreground mt-2 text-sm leading-6 text-pretty">
                        Confirm who this blueprint covers. Scoped blueprints inherit the global foundation and store only intentional differences.
                    </p>
                </div>
                <Button asChild variant="outline" className="h-11">
                    <a href={exportMethod.url([policy.id, version.id])}>
                        <Download className="size-4" /> Download backup
                    </a>
                </Button>
            </div>

            <Card className="border-primary/20 overflow-hidden">
                <div className="bg-primary h-1" />
                <CardHeader>
                    <div className="flex items-start gap-3">
                        <div className="bg-primary/10 text-primary flex size-10 shrink-0 items-center justify-center rounded-xl">
                            <Layers3 className="size-5" />
                        </div>
                        <div>
                            <CardTitle className="text-base">Who this blueprint covers</CardTitle>
                            <CardDescription className="mt-1">
                                Strict ancestor inheritance is shown here. Run simulation for the final student-specific result.
                            </CardDescription>
                        </div>
                    </div>
                </CardHeader>
                <CardContent className="space-y-4">
                    <p className="text-lg leading-7 font-medium text-pretty">
                        {scopeEntries.length === 0
                            ? "This is the global foundation for every future enrollment using the policy engine."
                            : `This blueprint applies when ${scopeEntries.map(([key, value]) => `${key.replaceAll("_", " ")} is ${value}`).join(" and ")}.`}
                    </p>
                    <div className="flex flex-wrap gap-2">
                        {scopeEntries.length ? (
                            scopeEntries.map(([key, value]) => (
                                <Badge key={key} variant="secondary" className="h-8 px-3 font-normal">
                                    {key.replaceAll("_", " ")}: {value}
                                </Badge>
                            ))
                        ) : (
                            <Badge className="h-8 px-3">Global base policy</Badge>
                        )}
                    </div>
                </CardContent>
            </Card>

            {scopeEntries.length ? (
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Inherited foundation</CardTitle>
                        <CardDescription>Values flow from these broader published blueprints, from least to most specific.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        {loading ? (
                            <div className="text-muted-foreground flex h-20 items-center gap-2 text-sm">
                                <Loader2 className="size-4 animate-spin" /> Loading inherited settings…
                            </div>
                        ) : inheritance.layers.length ? (
                            <ol className="space-y-2">
                                {inheritance.layers.map((layer, index) => (
                                    <li key={layer.version_id} className="flex items-center gap-3 rounded-xl border p-3">
                                        <span className="bg-muted flex size-7 items-center justify-center rounded-lg text-xs font-semibold">
                                            {index + 1}
                                        </span>
                                        <div className="min-w-0 flex-1">
                                            <p className="truncate text-sm font-medium">{layer.policy_name ?? "Published blueprint"}</p>
                                            <p className="text-muted-foreground text-xs">Version {layer.version ?? layer.version_id}</p>
                                        </div>
                                        <Badge variant="outline">Inherited</Badge>
                                    </li>
                                ))}
                            </ol>
                        ) : (
                            <Alert>
                                <AlertTriangle className="size-4" />
                                <AlertTitle>No published ancestor found</AlertTitle>
                                <AlertDescription>Publish a global blueprint before relying on inherited values.</AlertDescription>
                            </Alert>
                        )}
                    </CardContent>
                </Card>
            ) : null}

            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2 text-base">
                        <History className="size-4" /> Version history
                    </CardTitle>
                    <CardDescription>
                        Published versions are permanent records. Rollback only changes the version used by future enrollments.
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-2">
                    {policy.versions.map((item) => (
                        <div key={item.id} className="flex flex-col gap-3 rounded-xl border p-4 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p className="text-sm font-semibold">Version {item.version}</p>
                                <p className="text-muted-foreground mt-1 text-xs leading-5">{item.change_notes || "No change note was provided."}</p>
                            </div>
                            <div className="flex items-center gap-2">
                                <Badge variant={policy.active_version_id === item.id ? "default" : "outline"}>
                                    {policy.active_version_id === item.id ? "Used for future" : item.state}
                                </Badge>
                                {item.state === "published" && item.id !== policy.active_version_id ? (
                                    <AlertDialog>
                                        <AlertDialogTrigger asChild>
                                            <Button variant="ghost" size="sm" className="h-10">
                                                Use this version
                                            </Button>
                                        </AlertDialogTrigger>
                                        <AlertDialogContent>
                                            <AlertDialogHeader>
                                                <AlertDialogTitle>Use version {item.version} for future enrollments?</AlertDialogTitle>
                                                <AlertDialogDescription>
                                                    Existing enrollments stay pinned to their original version. Only new matching enrollments will use
                                                    this rollback.
                                                </AlertDialogDescription>
                                            </AlertDialogHeader>
                                            <AlertDialogFooter>
                                                <AlertDialogCancel>Cancel</AlertDialogCancel>
                                                <AlertDialogAction
                                                    onClick={() => router.post(rollback.url([policy.id, item.id]), { confirmation: "rollback" })}
                                                >
                                                    Use for future enrollments
                                                </AlertDialogAction>
                                            </AlertDialogFooter>
                                        </AlertDialogContent>
                                    </AlertDialog>
                                ) : null}
                            </div>
                        </div>
                    ))}
                </CardContent>
            </Card>

            <Alert>
                <ShieldCheck className="size-4" />
                <AlertTitle>Existing enrollments never change automatically</AlertTitle>
                <AlertDescription>
                    Publishing, activating, or rolling back a blueprint affects future enrollments only. Active policy enrollments keep their pinned
                    snapshot.
                    <a
                        className="ml-1 underline underline-offset-4"
                        href={`${documentationUrl.replace(/\/$/, "")}/enrollment-policies/scopes-inheritance/`}
                        target="_blank"
                        rel="noreferrer"
                    >
                        Learn about inheritance
                    </a>
                </AlertDescription>
            </Alert>
        </section>
    );
}

export function EligibilitySection({
    effective,
    local,
    registry,
    options,
    inheritance,
    onChange,
    onHelp,
}: {
    effective: EffectiveConfiguration;
    local: Configuration;
    registry: RegistryManifest;
    options: Record<string, Option[]>;
    inheritance: InheritanceResponse;
    onChange: (configuration: Configuration) => void;
    onHelp: (topic: HelpTopic) => void;
}) {
    const updateRules = (category: "availability" | "eligibility", rules: EffectiveConfiguration["rules"]) => {
        const other = (local.rules ?? []).filter((rule) => {
            const itemCategory = registry.rules[rule.handler]?.category === "availability" ? "availability" : "eligibility";
            return itemCategory !== category;
        });
        onChange({ ...local, rules: [...other, ...rules] });
    };

    return (
        <div className="space-y-10">
            <RuleEditor
                title="When is enrollment available?"
                description="Control the channels and dates that allow a student to begin. These checks run before academic or financial eligibility."
                category="availability"
                effectiveRules={effective.rules}
                localRules={(local.rules ?? []).filter((rule) => registry.rules[rule.handler]?.category === "availability")}
                registry={registry.rules}
                options={options}
                sourceMap={inheritance.source_map}
                onChange={(rules) => updateRules("availability", rules)}
                onHelp={onHelp}
            />
            <div className="border-t" />
            <RuleEditor
                title="Who is eligible to enroll?"
                description="Add the academic, balance, capacity, school, program, and student-type checks that should protect this enrollment path."
                category="eligibility"
                effectiveRules={effective.rules}
                localRules={(local.rules ?? []).filter((rule) => registry.rules[rule.handler]?.category !== "availability")}
                registry={registry.rules}
                options={options}
                sourceMap={inheritance.source_map}
                onChange={(rules) => updateRules("eligibility", rules)}
                onHelp={onHelp}
            />
        </div>
    );
}

export function DocumentsSection({
    effective,
    local,
    inheritance,
    onChange,
    onHelp,
}: {
    effective: EffectiveConfiguration;
    local: Configuration;
    inheritance: InheritanceResponse;
    onChange: (configuration: Configuration) => void;
    onHelp: (topic: HelpTopic) => void;
}) {
    const localRequirements = local.requirements ?? [];
    const setRequirement = (
        key: string,
        mutate: (value: EffectiveConfiguration["requirements"][number]) => EffectiveConfiguration["requirements"][number],
    ) => {
        const effectiveRequirement = effective.requirements.find((item) => item.key === key);
        if (!effectiveRequirement) return;
        const current = localRequirements.find((item) => item.key === key) ?? effectiveRequirement;
        onChange({ ...local, requirements: [...localRequirements.filter((item) => item.key !== key), mutate(copy(current))] });
    };

    return (
        <section className="space-y-5">
            <div className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                <div className="max-w-2xl">
                    <h2 className="text-2xl font-semibold tracking-tight text-balance">Required documents</h2>
                    <p className="text-muted-foreground mt-2 text-sm leading-6 text-pretty">
                        Tell students what to submit and make the instructions specific enough to avoid back-and-forth.
                    </p>
                </div>
                <HelpButton
                    onClick={() =>
                        onHelp({
                            title: "Required documents",
                            whatItDoes: "Lists the records students must provide before their enrollment can proceed.",
                            impact: "Required items appear as blockers when they are missing. Optional items remain visible without blocking progress.",
                            example: "Require a Form 138 for new first-year students and describe whether an original or certified copy is accepted.",
                            docsAnchor: "availability-eligibility-documents",
                        })
                    }
                />
            </div>

            <div className="space-y-3">
                {effective.requirements.map((requirement, index) => {
                    const source = sectionSource(inheritance.source_map, "requirements", requirement.key);
                    const overridden = localRequirements.some((item) => item.key === requirement.key);
                    return (
                        <article key={requirement.key} className="bg-background rounded-2xl border p-4 shadow-sm">
                            <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.5fr)_minmax(0,1fr)_auto]">
                                <div className="space-y-2">
                                    <div className="flex items-center gap-2">
                                        <Label htmlFor={`document-${requirement.key}`}>Document {index + 1}</Label>
                                        {source && !overridden ? <Badge variant="outline">Inherited</Badge> : null}
                                    </div>
                                    <Input
                                        id={`document-${requirement.key}`}
                                        className="h-11"
                                        value={requirement.label}
                                        onChange={(event) =>
                                            setRequirement(requirement.key, (current) => ({ ...current, label: event.target.value }))
                                        }
                                        placeholder="Document name"
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label>Enforce before step</Label>
                                    <OptionSelect
                                        value={requirement.enforcement_step ?? ""}
                                        onChange={(enforcementStep) =>
                                            setRequirement(requirement.key, (current) => ({
                                                ...current,
                                                enforcement_step: enforcementStep || null,
                                            }))
                                        }
                                        options={effective.workflow.steps.map((step) => ({ value: step.key, label: step.label }))}
                                        placeholder="Collect without blocking"
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label>Instructions shown to students</Label>
                                    <Input
                                        className="h-11"
                                        value={requirement.description ?? ""}
                                        onChange={(event) =>
                                            setRequirement(requirement.key, (current) => ({ ...current, description: event.target.value }))
                                        }
                                        placeholder="Example: Upload a clear PDF of every page"
                                    />
                                </div>
                                <div className="flex items-end gap-2">
                                    <label className="flex h-11 items-center gap-2 rounded-lg border px-3 text-sm">
                                        <Checkbox
                                            checked={requirement.required ?? true}
                                            onCheckedChange={(checked) =>
                                                setRequirement(requirement.key, (current) => ({ ...current, required: checked === true }))
                                            }
                                        />
                                        Required
                                    </label>
                                    {source ? (
                                        <Button
                                            type="button"
                                            size="icon"
                                            variant="ghost"
                                            className="size-11"
                                            disabled={!overridden}
                                            onClick={() =>
                                                onChange({ ...local, requirements: localRequirements.filter((item) => item.key !== requirement.key) })
                                            }
                                        >
                                            <RotateCcw className="size-4" /> <span className="sr-only">Use inherited value</span>
                                        </Button>
                                    ) : (
                                        <Button
                                            type="button"
                                            size="icon"
                                            variant="ghost"
                                            className="text-destructive size-11"
                                            onClick={() =>
                                                onChange({ ...local, requirements: localRequirements.filter((item) => item.key !== requirement.key) })
                                            }
                                        >
                                            <Trash2 className="size-4" /> <span className="sr-only">Remove document</span>
                                        </Button>
                                    )}
                                </div>
                            </div>
                        </article>
                    );
                })}
            </div>

            <Button
                type="button"
                variant="outline"
                className="h-11 border-dashed"
                onClick={() => {
                    const key = `document_${Date.now()}`;
                    const enforcementStep = effective.workflow.steps.find((step) => !step.entry)?.key ?? effective.workflow.steps[0]?.key ?? null;
                    onChange({
                        ...local,
                        requirements: [
                            ...localRequirements,
                            { key, label: "New document", description: "", required: true, enabled: true, enforcement_step: enforcementStep },
                        ],
                    });
                }}
            >
                <Plus className="size-4" /> Add a document
            </Button>
        </section>
    );
}

export function AssignmentSection({ effective, local, registry, options, inheritance, onChange, onHelp }: StrategySectionProps) {
    return (
        <section className="space-y-8">
            <StrategyEditor
                title="How are subjects and classes selected?"
                description="Choose one safe assignment approach. Automatic class selection checks available seats transactionally when enrollment runs."
                value={effective.assignment}
                localValue={local.assignment}
                registry={registry.assignment_strategies}
                options={options}
                source={sectionSource(inheritance.source_map, "assignment")}
                onChange={(assignment) => onChange({ ...local, assignment })}
                onReset={() => {
                    const next = { ...local };
                    delete next.assignment;
                    onChange(next);
                }}
                onHelp={onHelp}
            />
        </section>
    );
}

export function BillingSection({ effective, local, registry, options, inheritance, onChange, onHelp }: StrategySectionProps) {
    const inherited = sectionSource(inheritance.source_map, "billing");
    const ensureBilling = () => local.billing ?? copy(effective.billing);

    return (
        <section className="space-y-8">
            <StrategyEditor
                title="How are tuition and payment requirements calculated?"
                description="Select the validated fee strategy, discount behavior, and minimum payment gate used before completion."
                value={effective.billing}
                localValue={local.billing}
                registry={registry.billing_strategies}
                options={options}
                source={inherited}
                onChange={(billing) =>
                    onChange({ ...local, billing: { ...billing, allowed_payment_methods: ensureBilling().allowed_payment_methods } })
                }
                onReset={() => {
                    const next = { ...local };
                    delete next.billing;
                    onChange(next);
                }}
                onHelp={onHelp}
            />
            <Card>
                <CardHeader>
                    <CardTitle className="text-base">Accepted payment methods</CardTitle>
                    <CardDescription>Only these methods may satisfy a payment gate for this blueprint.</CardDescription>
                </CardHeader>
                <CardContent>
                    <MultiSelect
                        options={options.payment_methods ?? []}
                        selected={effective.billing.allowed_payment_methods ?? []}
                        onChange={(allowedPaymentMethods) =>
                            onChange({ ...local, billing: { ...ensureBilling(), allowed_payment_methods: allowedPaymentMethods } })
                        }
                        placeholder="Choose accepted payment methods"
                    />
                </CardContent>
            </Card>
            <Card>
                <CardHeader>
                    <CardTitle className="text-base">Receipt policy</CardTitle>
                    <CardDescription>Choose the default receipt behavior. No-receipt transitions still require an audited reason.</CardDescription>
                </CardHeader>
                <CardContent>
                    <OptionSelect
                        value={String(effective.billing.configuration.receipt_mode ?? "required")}
                        onChange={(receiptMode) =>
                            onChange({
                                ...local,
                                billing: {
                                    ...ensureBilling(),
                                    configuration: {
                                        ...ensureBilling().configuration,
                                        receipt_mode: receiptMode as "required" | "optional" | "none",
                                    },
                                },
                            })
                        }
                        options={[
                            { value: "required", label: "Receipt required" },
                            { value: "optional", label: "Receipt optional" },
                            { value: "none", label: "Authorized no-receipt path" },
                        ]}
                        placeholder="Choose receipt policy"
                        allowEmpty={false}
                    />
                </CardContent>
            </Card>
        </section>
    );
}

export function WorkflowSection({ effective, local, registry, options, inheritance, onChange }: Omit<StrategySectionProps, "onHelp">) {
    const workflowSource = sectionSource(inheritance.source_map, "workflow");
    const notificationsSource = sectionSource(inheritance.source_map, "notifications");

    return (
        <section className="space-y-10">
            <AtomicInheritanceBanner
                label="approval workflow"
                sourceName={workflowSource?.policy_name}
                overridden={Boolean(local.workflow)}
                onOverride={() => onChange({ ...local, workflow: copy(effective.workflow) })}
                onReset={() => {
                    const next = { ...local };
                    delete next.workflow;
                    onChange(next);
                }}
            />
            <WorkflowEditor
                steps={effective.workflow.steps}
                actions={registry.actions}
                rules={registry.rules}
                options={options}
                onChange={(steps) => onChange({ ...local, workflow: { steps } })}
            />

            <div className="border-t" />
            <div className="space-y-5">
                <div>
                    <h2 className="text-2xl font-semibold tracking-tight text-balance">Notifications</h2>
                    <p className="text-muted-foreground mt-2 text-sm leading-6 text-pretty">
                        Decide when students or staff receive an update. Delivery is queued after the enrollment change commits successfully.
                    </p>
                </div>
                <AtomicInheritanceBanner
                    label="notifications"
                    sourceName={notificationsSource?.policy_name}
                    overridden={Boolean(local.notifications)}
                    onOverride={() => onChange({ ...local, notifications: copy(effective.notifications) })}
                    onReset={() => {
                        const next = { ...local };
                        delete next.notifications;
                        onChange(next);
                    }}
                />
                <div className="space-y-3">
                    {effective.notifications.map((notification, index) => (
                        <div key={notification.key} className="grid gap-3 rounded-xl border p-4 sm:grid-cols-[1fr_1fr_auto]">
                            <div className="space-y-2">
                                <Label>Send after</Label>
                                <OptionSelect
                                    value={notification.event}
                                    onChange={(event) => {
                                        const next = copy(local.notifications ?? effective.notifications);
                                        next[index] = { ...next[index], event, key: `notify_${event}_${index + 1}` };
                                        onChange({ ...local, notifications: next });
                                    }}
                                    options={[
                                        { value: "any_transition", label: "Every approval change" },
                                        ...effective.workflow.steps.map((step) => ({ value: step.key, label: step.label })),
                                    ]}
                                    placeholder="Choose an event"
                                    allowEmpty={false}
                                />
                            </div>
                            <div className="space-y-2">
                                <Label>Delivery channel</Label>
                                <OptionSelect
                                    value={notification.channel}
                                    onChange={(channel) => {
                                        const next = copy(local.notifications ?? effective.notifications);
                                        next[index] = { ...next[index], channel };
                                        onChange({ ...local, notifications: next });
                                    }}
                                    options={options.notification_channels ?? []}
                                    placeholder="Choose a channel"
                                    allowEmpty={false}
                                />
                            </div>
                            <Button
                                type="button"
                                size="icon"
                                variant="ghost"
                                className="text-destructive size-11 self-end"
                                onClick={() => {
                                    const next = copy(local.notifications ?? effective.notifications);
                                    next.splice(index, 1);
                                    onChange({ ...local, notifications: next });
                                }}
                            >
                                <Trash2 className="size-4" /> <span className="sr-only">Remove notification</span>
                            </Button>
                        </div>
                    ))}
                </div>
                <Button
                    type="button"
                    variant="outline"
                    className="h-11 border-dashed"
                    onClick={() =>
                        onChange({
                            ...local,
                            notifications: [
                                ...copy(local.notifications ?? effective.notifications),
                                {
                                    key: `notification_${Date.now()}`,
                                    event: effective.workflow.steps.find((step) => step.terminal)?.key ?? "any_transition",
                                    channel: options.notification_channels?.[0]?.value ?? "mail",
                                    enabled: true,
                                },
                            ],
                        })
                    }
                >
                    <Plus className="size-4" /> Add notification
                </Button>
            </div>
        </section>
    );
}

export function PublishSection({
    policy,
    version,
    options,
    rollout,
    dirty,
    notes,
    setNotes,
    simulationResult,
    setSimulationResult,
    canUpdate,
    onFix,
}: {
    policy: Policy;
    version: PolicyVersion;
    options: Record<string, Option[]>;
    rollout: Rollout;
    dirty: boolean;
    notes: string;
    setNotes: (notes: string) => void;
    simulationResult: Simulation | null;
    setSimulationResult: (result: Simulation | null) => void;
    canUpdate: boolean;
    onFix: (section: BlueprintStepId) => void;
}) {
    const [simulating, setSimulating] = useState(false);
    const [importPreview, setImportPreview] = useState<{ name: string; configuration: Configuration } | null>(null);
    const form = useForm({
        student_enrollment_id: "",
        school_id: String(policy.scope_values.school_id ?? options.schools?.[0]?.value ?? ""),
        student_type: String(policy.scope_values.student_type ?? options.student_types?.[0]?.value ?? "college"),
        course_id: String(policy.scope_values.course_id ?? options.programs?.[0]?.value ?? ""),
        school_year: String(policy.scope_values.school_year ?? ""),
        semester: String(policy.scope_values.semester ?? "1"),
        year_level: "1",
        channel: "administrator",
    });

    const runSimulation = async () => {
        if (dirty) return;
        setSimulating(true);
        try {
            const payload = form.data.student_enrollment_id
                ? { student_enrollment_id: Number(form.data.student_enrollment_id), channel: form.data.channel }
                : {
                      school_id: Number(form.data.school_id),
                      student_type: form.data.student_type,
                      course_id: Number(form.data.course_id),
                      school_year: form.data.school_year,
                      semester: Number(form.data.semester),
                      year_level: Number(form.data.year_level),
                      channel: form.data.channel,
                      facts: {},
                  };
            const response = await axios.post(simulate.url([policy.id, version.id]), payload);
            setSimulationResult(response.data as Simulation);
        } catch (error) {
            setSimulationResult({ error: axios.isAxiosError(error) ? (error.response?.data ?? error.message) : "Simulation failed" });
        } finally {
            setSimulating(false);
        }
    };

    const readiness = [
        { label: "All changes are saved", ready: !dirty },
        { label: "Policy validation completed", ready: Boolean(simulationResult?.checksum) },
        { label: "Representative simulation has no blockers", ready: Boolean(simulationResult?.checksum) && !simulationResult?.blockers?.length },
        { label: "Change note explains the future impact", ready: notes.trim().length >= 5 },
    ];
    const readyToPublish = readiness.every((item) => item.ready) && version.state === "draft";
    const global = Object.keys(policy.scope).length === 0;
    const canActivate = !rollout.active && rollout.ready && global && policy.active_version_id === version.id && Boolean(simulationResult?.checksum);

    return (
        <section className="space-y-6">
            <div className="max-w-2xl">
                <h2 className="text-2xl font-semibold tracking-tight text-balance">Test, publish, and activate</h2>
                <p className="text-muted-foreground mt-2 text-sm leading-6 text-pretty">
                    Walk a representative student through the resolved blueprint before making this version available to future enrollments.
                </p>
            </div>

            {dirty ? (
                <Alert className="border-amber-500/30 bg-amber-500/5">
                    <AlertTriangle className="size-4" />
                    <AlertTitle>Save this draft before simulation</AlertTitle>
                    <AlertDescription>
                        The server tests the exact saved publication candidate so simulation and publishing cannot drift apart.
                    </AlertDescription>
                </Alert>
            ) : null}

            <div className="grid gap-5 xl:grid-cols-[22rem_minmax(0,1fr)]">
                <Card className="h-fit">
                    <CardHeader>
                        <CardTitle className="text-base">Representative student</CardTitle>
                        <CardDescription>Use an existing enrollment ID or describe a realistic sample.</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="space-y-2">
                            <Label>
                                Existing enrollment ID <span className="text-muted-foreground font-normal">(optional)</span>
                            </Label>
                            <Input
                                className="h-11"
                                inputMode="numeric"
                                value={form.data.student_enrollment_id}
                                onChange={(event) => form.setData("student_enrollment_id", event.target.value)}
                                placeholder="Example: 1042"
                            />
                        </div>
                        {!form.data.student_enrollment_id ? (
                            <>
                                <div className="space-y-2">
                                    <Label>School</Label>
                                    <OptionSelect
                                        value={form.data.school_id}
                                        onChange={(value) => form.setData("school_id", value)}
                                        options={options.schools}
                                        placeholder="Choose school"
                                        allowEmpty={false}
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label>Student type</Label>
                                    <OptionSelect
                                        value={form.data.student_type}
                                        onChange={(value) => form.setData("student_type", value)}
                                        options={options.student_types}
                                        placeholder="Choose student type"
                                        allowEmpty={false}
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label>Program</Label>
                                    <OptionSelect
                                        value={form.data.course_id}
                                        onChange={(value) => form.setData("course_id", value)}
                                        options={options.programs}
                                        placeholder="Choose program"
                                        allowEmpty={false}
                                    />
                                </div>
                                <div className="grid grid-cols-2 gap-3">
                                    <div className="space-y-2">
                                        <Label>School year</Label>
                                        <Input
                                            className="h-11"
                                            value={form.data.school_year}
                                            onChange={(event) => form.setData("school_year", event.target.value)}
                                            placeholder="2026 - 2027"
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label>Semester</Label>
                                        <OptionSelect
                                            value={form.data.semester}
                                            onChange={(value) => form.setData("semester", value)}
                                            options={
                                                options.periods ?? [
                                                    { value: "1", label: "1st" },
                                                    { value: "2", label: "2nd" },
                                                ]
                                            }
                                            placeholder="Choose term"
                                            allowEmpty={false}
                                        />
                                    </div>
                                </div>
                            </>
                        ) : null}
                        <Button className="h-11 w-full" disabled={dirty || simulating} onClick={runSimulation}>
                            {simulating ? <Loader2 className="size-4 animate-spin" /> : <Sparkles className="size-4" />}
                            {simulating ? "Testing student journey…" : "Run student journey"}
                        </Button>
                    </CardContent>
                </Card>

                <SimulationReport result={simulationResult} onFix={onFix} />
            </div>

            <Card>
                <CardHeader>
                    <CardTitle className="text-base">Publication readiness</CardTitle>
                    <CardDescription>Every item must be complete. Publication and rollout activation are separate safety decisions.</CardDescription>
                </CardHeader>
                <CardContent className="space-y-5">
                    <div className="grid gap-2 sm:grid-cols-2">
                        {readiness.map((item) => (
                            <div key={item.label} className="flex items-center gap-3 rounded-xl border p-3">
                                <span
                                    className={cn(
                                        "flex size-7 shrink-0 items-center justify-center rounded-lg",
                                        item.ready ? "bg-emerald-500/10 text-emerald-700 dark:text-emerald-300" : "bg-muted text-muted-foreground",
                                    )}
                                >
                                    {item.ready ? <Check className="size-4" /> : <span className="size-2 rounded-full bg-current opacity-40" />}
                                </span>
                                <span className="text-sm font-medium">{item.label}</span>
                            </div>
                        ))}
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="policy-change-notes">What changed, and why?</Label>
                        <Textarea
                            id="policy-change-notes"
                            value={notes}
                            onChange={(event) => setNotes(event.target.value)}
                            placeholder="Example: Require registrar approval before cashier review for first-year College students."
                        />
                        <p className="text-muted-foreground text-xs">This note becomes part of the permanent version history.</p>
                    </div>

                    <Alert className="border-blue-500/25 bg-blue-500/5">
                        <ShieldCheck className="size-4" />
                        <AlertTitle>Future-enrollment impact only</AlertTitle>
                        <AlertDescription>
                            Publishing makes this version available for future matching enrollments. It does not activate the engine and never changes
                            an existing enrollment.
                        </AlertDescription>
                    </Alert>

                    <div className="flex flex-wrap gap-3">
                        <Button
                            className="h-11"
                            disabled={!canUpdate || !readyToPublish}
                            onClick={() =>
                                router.post(
                                    publish.url([policy.id, version.id]),
                                    { simulation_checksum: simulationResult?.checksum },
                                    { preserveScroll: true },
                                )
                            }
                        >
                            <GitBranch className="size-4" /> Publish version {version.version}
                        </Button>

                        {canActivate ? (
                            <AlertDialog>
                                <AlertDialogTrigger asChild>
                                    <Button variant="outline" className="h-11">
                                        <ShieldCheck className="size-4" /> Activate for future enrollments
                                    </Button>
                                </AlertDialogTrigger>
                                <AlertDialogContent>
                                    <AlertDialogHeader>
                                        <AlertDialogTitle>Activate enrollment policies?</AlertDialogTitle>
                                        <AlertDialogDescription>
                                            New enrollments will use the active policy engine. Existing legacy and policy enrollments keep their
                                            current runtime and pinned behavior.
                                        </AlertDialogDescription>
                                    </AlertDialogHeader>
                                    <AlertDialogFooter>
                                        <AlertDialogCancel>Keep legacy active</AlertDialogCancel>
                                        <AlertDialogAction
                                            onClick={() =>
                                                router.post(activate.url(), {
                                                    confirmation: "activate enrollment policies",
                                                    simulation_checksum: simulationResult?.checksum,
                                                })
                                            }
                                        >
                                            Activate for future enrollments
                                        </AlertDialogAction>
                                    </AlertDialogFooter>
                                </AlertDialogContent>
                            </AlertDialog>
                        ) : null}
                    </div>
                </CardContent>
            </Card>

            <details className="bg-card rounded-2xl border">
                <summary className="flex min-h-12 cursor-pointer items-center gap-2 px-5 py-4 font-semibold">
                    Advanced backup and technical details
                </summary>
                <div className="space-y-5 border-t p-5">
                    <p className="text-muted-foreground max-w-2xl text-sm leading-6">
                        JSON is never edited here. Upload a trusted KoAkademy backup, review its plain-language summary, and import it as a separate
                        draft.
                    </p>
                    <Input type="file" accept="application/json,.json" onChange={(event) => readImport(event.target.files?.[0], setImportPreview)} />
                    {importPreview ? (
                        <div className="bg-muted/50 rounded-xl p-4">
                            <p className="font-semibold">{importPreview.name}</p>
                            <p className="text-muted-foreground mt-1 text-sm">
                                {importPreview.configuration.rules?.length ?? 0} checks · {importPreview.configuration.requirements?.length ?? 0}{" "}
                                documents · {importPreview.configuration.workflow?.steps.length ?? 0} approval steps
                            </p>
                            <Button
                                className="mt-4 h-10"
                                size="sm"
                                onClick={() =>
                                    router.post(importMethod.url(), {
                                        name: importPreview.name,
                                        configuration: importPreview.configuration as unknown as FormDataConvertible,
                                    })
                                }
                            >
                                <FileUp className="size-4" /> Import as a new draft
                            </Button>
                        </div>
                    ) : null}
                </div>
            </details>
        </section>
    );
}

type StrategySectionProps = {
    effective: EffectiveConfiguration;
    local: Configuration;
    registry: RegistryManifest;
    options: Record<string, Option[]>;
    inheritance: InheritanceResponse;
    onChange: (configuration: Configuration) => void;
    onHelp: (topic: HelpTopic) => void;
};

function StrategyEditor({
    title,
    description,
    value,
    localValue,
    registry,
    options,
    source,
    onChange,
    onReset,
    onHelp,
}: {
    title: string;
    description: string;
    value: { strategy: string; configuration: JsonObject };
    localValue?: { strategy: string; configuration: JsonObject };
    registry: Record<string, RegistryItem>;
    options: Record<string, Option[]>;
    source?: InheritanceResponse["layers"][number];
    onChange: (value: { strategy: string; configuration: JsonObject }) => void;
    onReset: () => void;
    onHelp: (topic: HelpTopic) => void;
}) {
    const selected = registry[value.strategy];
    const help = schemaHelp(selected);

    return (
        <div className="space-y-5">
            <div className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                <div className="max-w-2xl">
                    <div className="flex flex-wrap items-center gap-2">
                        <h2 className="text-2xl font-semibold tracking-tight text-balance">{title}</h2>
                        {source && !localValue ? <Badge variant="outline">Inherited from {source.policy_name}</Badge> : null}
                    </div>
                    <p className="text-muted-foreground mt-2 text-sm leading-6 text-pretty">{description}</p>
                </div>
                <HelpButton
                    onClick={() =>
                        onHelp({
                            title: selected?.label ?? title,
                            whatItDoes: help.what_it_does ?? description,
                            impact: help.impact,
                            example: help.example,
                            docsAnchor: help.docs_anchor,
                        })
                    }
                />
            </div>

            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                {Object.values(registry).map((item) => {
                    const active = value.strategy === item.key;
                    return (
                        <button
                            key={item.key}
                            type="button"
                            aria-pressed={active}
                            onClick={() => onChange({ strategy: item.key, configuration: operatorDefaults(item) })}
                            className={cn(
                                "min-h-28 rounded-xl border p-4 text-left transition-[border-color,background-color,box-shadow,scale] duration-150 active:scale-[0.98] motion-reduce:transition-none",
                                active ? "border-primary bg-primary/5 shadow-sm" : "hover:border-primary/40 hover:bg-muted/30",
                            )}
                        >
                            <span className="flex items-start justify-between gap-2 font-semibold">
                                {item.label}
                                {active ? <CheckCircle2 className="text-primary size-5" /> : null}
                            </span>
                            <span className="text-muted-foreground mt-2 block text-sm leading-5 text-pretty">
                                {item.operator_schema?.description ?? "Configure this enrollment behavior."}
                            </span>
                        </button>
                    );
                })}
            </div>

            <Card>
                <CardHeader>
                    <CardTitle className="text-base">{selected?.label ?? value.strategy}</CardTitle>
                    <CardDescription>{help.what_it_does}</CardDescription>
                </CardHeader>
                <CardContent>
                    <SchemaFields
                        item={selected}
                        value={value.configuration}
                        options={options}
                        onChange={(configuration) => onChange({ strategy: value.strategy, configuration })}
                    />
                </CardContent>
            </Card>

            {source ? (
                <Button type="button" variant="ghost" className="h-10" disabled={!localValue} onClick={onReset}>
                    <RotateCcw className="size-4" /> Use inherited {title.toLowerCase()}
                </Button>
            ) : null}
        </div>
    );
}

function AtomicInheritanceBanner({
    label,
    sourceName,
    overridden,
    onOverride,
    onReset,
}: {
    label: string;
    sourceName?: string;
    overridden: boolean;
    onOverride: () => void;
    onReset: () => void;
}) {
    if (!sourceName) return null;

    return (
        <div className="bg-muted/25 flex flex-col gap-3 rounded-xl border p-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p className="text-sm font-semibold">{overridden ? `This blueprint overrides its ${label}` : `Using ${label} from ${sourceName}`}</p>
                <p className="text-muted-foreground mt-1 text-xs leading-5">
                    {overridden
                        ? "This complete section is owned by the current blueprint."
                        : "Inherited sections remain linked until you intentionally override them."}
                </p>
            </div>
            <Button type="button" variant="outline" className="h-10 shrink-0" onClick={overridden ? onReset : onOverride}>
                {overridden ? <RotateCcw className="size-4" /> : <ArrowRight className="size-4" />}
                {overridden ? "Use inherited value" : "Override here"}
            </Button>
        </div>
    );
}

function readImport(file: File | undefined, setPreview: (value: { name: string; configuration: Configuration } | null) => void) {
    if (!file) return;
    const reader = new FileReader();
    reader.onload = () => {
        try {
            const parsed = JSON.parse(String(reader.result)) as { name?: string; configuration?: Configuration };
            if (!parsed.configuration || parsed.configuration.schema_version !== 1) throw new Error("Invalid enrollment policy backup");
            setPreview({ name: parsed.name ?? file.name.replace(/\.json$/i, ""), configuration: parsed.configuration });
        } catch {
            setPreview(null);
        }
    };
    reader.readAsText(file);
}
