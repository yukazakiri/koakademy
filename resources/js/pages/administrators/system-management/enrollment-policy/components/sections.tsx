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
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from "@/components/ui/tooltip";
import { cn } from "@/lib/utils";
import type { FormDataConvertible } from "@inertiajs/core";
import { router, useForm } from "@inertiajs/react";
import axios from "axios";
import {
    AlertTriangle,
    ArrowRight,
    BookOpen,
    Check,
    CheckCircle2,
    Clock,
    CreditCard,
    Download,
    FileCheck,
    FileText,
    FileUp,
    GitBranch,
    History,
    Layers3,
    Loader2,
    Plus,
    Receipt,
    RotateCcw,
    ShieldCheck,
    Sparkles,
    Trash2,
    UserCheck,
    Users,
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

/* =========================================================================
   STAGE 1: SCOPE SECTION (Template, Coverage, and Versions)
   ========================================================================= */

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
    const isGlobal = scopeEntries.length === 0;

    return (
        <section className="space-y-6">
            {/* Header Description & Export Backup */}
            <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between border-b border-border/40 pb-4">
                <div>
                    <h2 className="text-xl font-bold tracking-tight text-foreground">Stage 1: Template, Coverage & Versions</h2>
                    <p className="mt-1 text-xs text-muted-foreground max-w-2xl leading-relaxed">
                        Define which student cohorts this blueprint governs. Scoped blueprints inherit the global baseline and store only intentional variations.
                    </p>
                </div>
                <Button asChild variant="outline" size="sm" className="h-8.5 gap-1.5 text-xs self-start sm:self-center bg-background">
                    <a href={exportMethod.url([policy.id, version.id])}>
                        <Download className="size-3.5" />
                        <span>Export Backup</span>
                    </a>
                </Button>
            </div>

            {/* Scope Coverage Card */}
            <Card className="border-border/60 bg-card/70 shadow-xs backdrop-blur-xs">
                <CardHeader className="pb-3 border-b border-border/40">
                    <div className="flex items-center gap-3">
                        <div className="flex size-9 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                            <Layers3 className="size-4.5" />
                        </div>
                        <div>
                            <CardTitle className="text-sm font-semibold">Cohort Coverage Scope</CardTitle>
                            <CardDescription className="text-xs">
                                Strict ancestor inheritance applies. The simulation engine resolves the student-specific combination.
                            </CardDescription>
                        </div>
                    </div>
                </CardHeader>

                <CardContent className="pt-4 space-y-4">
                    <p className="text-sm font-medium text-foreground">
                        {isGlobal
                            ? "This is the global foundation blueprint governing every admission workflow that lacks a more specific override."
                            : `This scoped blueprint applies when ${scopeEntries.map(([key, value]) => `${key.replaceAll("_", " ")} is ${value}`).join(" and ")}.`}
                    </p>

                    <div className="flex flex-wrap gap-2">
                        {isGlobal ? (
                            <Badge variant="outline" className="h-6 px-2.5 text-xs border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300">
                                Global Base Policy (Root)
                            </Badge>
                        ) : (
                            scopeEntries.map(([key, value]) => (
                                <Badge key={key} variant="outline" className="h-6 px-2.5 text-xs font-mono border-border/60 bg-muted/40">
                                    {key.replaceAll("_", " ")}: <strong className="ml-1 text-foreground">{value}</strong>
                                </Badge>
                            ))
                        )}
                    </div>
                </CardContent>
            </Card>

            {/* Inherited Foundation Cascade (for scoped blueprints) */}
            {!isGlobal && (
                <Card className="border-border/60 bg-card/70 shadow-xs backdrop-blur-xs">
                    <CardHeader className="pb-3 border-b border-border/40">
                        <CardTitle className="text-sm font-semibold">Inherited Ancestor Baseline</CardTitle>
                        <CardDescription className="text-xs">
                            Values cascade from broader published blueprints down to this specific cohort.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="pt-4">
                        {loading ? (
                            <div className="flex h-16 items-center gap-2 text-xs text-muted-foreground font-medium">
                                <Loader2 className="size-4 animate-spin text-primary" />
                                <span>Resolving inherited layer tree...</span>
                            </div>
                        ) : inheritance.layers.length ? (
                            <ol className="space-y-2">
                                {inheritance.layers.map((layer, index) => (
                                    <li
                                        key={layer.version_id}
                                        className="flex items-center gap-3 rounded-lg border border-border/50 bg-background/50 p-2.5 text-xs"
                                    >
                                        <span className="flex size-6 shrink-0 items-center justify-center rounded-md bg-muted font-mono font-semibold text-[11px]">
                                            {index + 1}
                                        </span>
                                        <div className="min-w-0 flex-1">
                                            <p className="font-semibold text-foreground truncate">{layer.policy_name ?? "Published Policy"}</p>
                                            <p className="text-[11px] text-muted-foreground font-mono">v{layer.version ?? layer.version_id}</p>
                                        </div>
                                        <Badge variant="outline" className="text-[10px] font-normal border-border/60">
                                            Inherited
                                        </Badge>
                                    </li>
                                ))}
                            </ol>
                        ) : (
                            <Alert className="border-amber-500/30 bg-amber-500/5">
                                <AlertTriangle className="size-4 text-amber-600" />
                                <AlertTitle className="text-xs font-semibold">No published ancestor detected</AlertTitle>
                                <AlertDescription className="text-[11px] text-muted-foreground">
                                    Publish a global foundation blueprint first so this scoped blueprint has fallback values.
                                </AlertDescription>
                            </Alert>
                        )}
                    </CardContent>
                </Card>
            )}

            {/* Version History Table */}
            <Card className="border-border/60 bg-card/70 shadow-xs backdrop-blur-xs">
                <CardHeader className="pb-3 border-b border-border/40">
                    <div className="flex items-center gap-2">
                        <History className="size-4 text-primary" />
                        <CardTitle className="text-sm font-semibold">Version History & Rollback Points</CardTitle>
                    </div>
                    <CardDescription className="text-xs">
                        Published versions are immutable audited snapshots. Rolling back modifies only future applicant enrollments.
                    </CardDescription>
                </CardHeader>
                <CardContent className="pt-4 space-y-2">
                    {policy.versions.map((item) => {
                        const isActive = policy.active_version_id === item.id;
                        const isCurrentDraft = item.id === version.id;

                        return (
                            <div
                                key={item.id}
                                className={cn(
                                    "flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 rounded-xl border p-3 text-xs transition-colors",
                                    isActive
                                        ? "border-primary/40 bg-primary/5"
                                        : "border-border/50 bg-background/50 hover:bg-muted/30",
                                )}
                            >
                                <div className="min-w-0">
                                    <div className="flex items-center gap-2">
                                        <span className="font-semibold font-mono text-sm text-foreground">v{item.version}</span>
                                        <Badge
                                            variant="outline"
                                            className={cn(
                                                "text-[10px] h-4.5",
                                                isActive
                                                    ? "border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300"
                                                    : item.state === "published"
                                                      ? "border-sky-500/30 bg-sky-500/10 text-sky-700 dark:text-sky-300"
                                                      : "border-border/60 text-muted-foreground",
                                            )}
                                        >
                                            {isActive ? "Active (Live)" : item.state.toUpperCase()}
                                        </Badge>
                                        {isCurrentDraft && !isActive && (
                                            <Badge variant="outline" className="text-[10px] h-4.5 border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-300">
                                                Editing Now
                                            </Badge>
                                        )}
                                    </div>
                                    <p className="mt-1 text-muted-foreground text-[11px] line-clamp-1">
                                        {item.change_notes || "No revision notes recorded."}
                                    </p>
                                </div>

                                {item.state === "published" && !isActive && (
                                    <AlertDialog>
                                        <AlertDialogTrigger asChild>
                                            <Button variant="outline" size="sm" className="h-7 text-xs shrink-0 self-start sm:self-center">
                                                <RotateCcw className="size-3 mr-1" />
                                                Rollback to v{item.version}
                                            </Button>
                                        </AlertDialogTrigger>
                                        <AlertDialogContent>
                                            <AlertDialogHeader>
                                                <AlertDialogTitle className="text-base font-semibold">
                                                    Rollback to Blueprint Version {item.version}?
                                                </AlertDialogTitle>
                                                <AlertDialogDescription className="text-xs">
                                                    This will switch future applicant enrollments to use version {item.version}. Existing students remain pinned to their current version.
                                                </AlertDialogDescription>
                                            </AlertDialogHeader>
                                            <AlertDialogFooter>
                                                <AlertDialogCancel className="text-xs h-8">Cancel</AlertDialogCancel>
                                                <AlertDialogAction
                                                    className="text-xs h-8"
                                                    onClick={() => router.post(rollback.url([policy.id, item.id]))}
                                                >
                                                    Confirm Rollback
                                                </AlertDialogAction>
                                            </AlertDialogFooter>
                                        </AlertDialogContent>
                                    </AlertDialog>
                                )}
                            </div>
                        );
                    })}
                </CardContent>
            </Card>

            <Alert className="border-border/60 bg-muted/20">
                <ShieldCheck className="size-4 text-primary" />
                <AlertTitle className="text-xs font-semibold">Future-enrollment isolation guarantee</AlertTitle>
                <AlertDescription className="text-[11px] text-muted-foreground">
                    Modifications to this blueprint will never alter or corrupt active student transcripts, invoices, or records.
                </AlertDescription>
            </Alert>
        </section>
    );
}

/* =========================================================================
   STAGE 2: ELIGIBILITY & AVAILABILITY SECTION
   ========================================================================= */

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
        <section className="space-y-8">
            <div className="border-b border-border/40 pb-4">
                <h2 className="text-xl font-bold tracking-tight text-foreground">Stage 2: Availability & Eligibility Gates</h2>
                <p className="mt-1 text-xs text-muted-foreground max-w-2xl leading-relaxed">
                    Set up two sequential validation gates: first verify date windows and channel access, then enforce academic standing, capacity, and clearance rules.
                </p>
            </div>

            {/* Gate 1: Channel & Period Availability */}
            <div className="rounded-2xl border border-border/60 bg-card/65 p-4 sm:p-5 shadow-xs">
                <div className="flex items-center gap-2 mb-4">
                    <Clock className="size-4 text-sky-500" />
                    <span className="text-xs font-bold uppercase tracking-wider text-foreground">Gate 1: Timing & Channel Window</span>
                </div>
                <RuleEditor
                    title="When is enrollment available?"
                    description="Control submission date windows and admission channels. Evaluated before academic checks."
                    category="availability"
                    effectiveRules={effective.rules}
                    localRules={(local.rules ?? []).filter((rule) => registry.rules[rule.handler]?.category === "availability")}
                    registry={registry.rules}
                    options={options}
                    sourceMap={inheritance.source_map}
                    onChange={(rules) => updateRules("availability", rules)}
                    onHelp={onHelp}
                />
            </div>

            {/* Gate 2: Student Standing & Clearance */}
            <div className="rounded-2xl border border-border/60 bg-card/65 p-4 sm:p-5 shadow-xs">
                <div className="flex items-center gap-2 mb-4">
                    <UserCheck className="size-4 text-emerald-500" />
                    <span className="text-xs font-bold uppercase tracking-wider text-foreground">Gate 2: Student Eligibility Checks</span>
                </div>
                <RuleEditor
                    title="Who is eligible to enroll?"
                    description="Configure academic prerequisites, outstanding balance thresholds, seat capacity, and program restrictions."
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
        </section>
    );
}

/* =========================================================================
   STAGE 3: REQUIRED DOCUMENTS SECTION
   ========================================================================= */

const COMMON_DOCUMENT_PRESETS = [
    { label: "Form 138 / High School Report Card", description: "Official senior high school report card showing final academic grades." },
    { label: "PSA Authenticated Birth Certificate", description: "Clear scanned copy of PSA-issued birth certificate." },
    { label: "Certificate of Good Moral Character", description: "Issued by the school principal or guidance counselor." },
    { label: "Official Transcript of Records (TOR)", description: "Complete transcript from previous college or university." },
    { label: "Certificate of Transfer Credential (Honorable Dismissal)", description: "Official clearance document for transferees." },
    { label: "Medical Health Clearance", description: "Physical examination certificate from clinic or university doctor." },
    { label: "2x2 Formal ID Picture", description: "White background, recent portrait photo in uniform or business attire." },
];

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

    const addDocument = (label = "New Document", description = "") => {
        const key = `document_${Date.now()}`;
        const enforcementStep = effective.workflow.steps.find((step) => !step.entry)?.key ?? effective.workflow.steps[0]?.key ?? null;
        onChange({
            ...local,
            requirements: [
                ...localRequirements,
                { key, label, description, required: true, enabled: true, enforcement_step: enforcementStep },
            ],
        });
    };

    return (
        <section className="space-y-6">
            {/* Header */}
            <div className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between border-b border-border/40 pb-4">
                <div>
                    <h2 className="text-xl font-bold tracking-tight text-foreground">Stage 3: Required Documents & Credentials</h2>
                    <p className="mt-1 text-xs text-muted-foreground max-w-2xl leading-relaxed">
                        Specify required records students must upload. Choose the workflow approval stage where missing documents block progress.
                    </p>
                </div>
                <HelpButton
                    onClick={() =>
                        onHelp({
                            title: "Required documents",
                            whatItDoes: "Lists credentials and files applicants must upload during the admissions process.",
                            impact: "Mandatory documents block progression at the chosen enforcement milestone. Optional items collect records without stalling.",
                            example: "Require Form 138 before registrar review, and allow medical certificates to be submitted conditionally.",
                            docsAnchor: "availability-eligibility-documents",
                        })
                    }
                />
            </div>

            {/* Quick Document Presets Strip */}
            <div className="rounded-xl border border-border/50 bg-muted/20 p-3.5 space-y-2">
                <span className="text-[11px] font-semibold text-muted-foreground uppercase tracking-wider">
                    Quick Presets (Click to add)
                </span>
                <div className="flex flex-wrap gap-1.5">
                    {COMMON_DOCUMENT_PRESETS.map((preset) => (
                        <button
                            key={preset.label}
                            type="button"
                            onClick={() => addDocument(preset.label, preset.description)}
                            className="inline-flex items-center gap-1.5 rounded-lg border border-border/60 bg-background/80 px-2.5 py-1 text-xs font-medium text-muted-foreground hover:border-primary/40 hover:text-foreground transition-colors shadow-2xs"
                        >
                            <Plus className="size-3 text-primary" />
                            <span>{preset.label}</span>
                        </button>
                    ))}
                </div>
            </div>

            {/* Document Requirements Cards */}
            <div className="space-y-3">
                {effective.requirements.map((requirement, index) => {
                    const source = sectionSource(inheritance.source_map, "requirements", requirement.key);
                    const overridden = localRequirements.some((item) => item.key === requirement.key);

                    return (
                        <article
                            key={requirement.key}
                            className="rounded-xl border border-border/60 bg-card/75 p-4 shadow-xs backdrop-blur-xs space-y-3"
                        >
                            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 border-b border-border/40 pb-2.5">
                                <div className="flex items-center gap-2">
                                    <div className="flex size-6 items-center justify-center rounded-md bg-primary/10 text-primary font-mono text-xs font-semibold">
                                        {index + 1}
                                    </div>
                                    <span className="text-xs font-semibold text-foreground">{requirement.label || "Untitled Document"}</span>
                                    {source && !overridden ? (
                                        <Badge variant="outline" className="text-[10px] h-4.5 border-border/60">
                                            Inherited
                                        </Badge>
                                    ) : overridden && source ? (
                                        <Badge variant="outline" className="text-[10px] h-4.5 border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-300">
                                            Overridden
                                        </Badge>
                                    ) : null}
                                </div>

                                <div className="flex items-center gap-3">
                                    <label className="flex items-center gap-1.5 text-xs font-medium text-foreground cursor-pointer">
                                        <Checkbox
                                            checked={requirement.required ?? true}
                                            onCheckedChange={(checked) =>
                                                setRequirement(requirement.key, (current) => ({ ...current, required: checked === true }))
                                            }
                                        />
                                        <span>Mandatory</span>
                                    </label>

                                    {source ? (
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant="ghost"
                                            className="h-7 text-xs text-muted-foreground"
                                            disabled={!overridden}
                                            onClick={() =>
                                                onChange({ ...local, requirements: localRequirements.filter((item) => item.key !== requirement.key) })
                                            }
                                        >
                                            <RotateCcw className="size-3 mr-1" />
                                            Reset
                                        </Button>
                                    ) : (
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant="ghost"
                                            className="h-7 text-xs text-destructive hover:bg-destructive/10"
                                            onClick={() =>
                                                onChange({ ...local, requirements: localRequirements.filter((item) => item.key !== requirement.key) })
                                            }
                                        >
                                            <Trash2 className="size-3 mr-1" />
                                            Remove
                                        </Button>
                                    )}
                                </div>
                            </div>

                            <div className="grid gap-3 sm:grid-cols-3">
                                <div className="space-y-1.5">
                                    <Label className="text-[11px] font-semibold text-muted-foreground uppercase tracking-wider">
                                        Document Name
                                    </Label>
                                    <Input
                                        className="h-8.5 text-xs"
                                        value={requirement.label}
                                        onChange={(e) => setRequirement(requirement.key, (cur) => ({ ...cur, label: e.target.value }))}
                                        placeholder="e.g. PSA Birth Certificate"
                                    />
                                </div>

                                <div className="space-y-1.5">
                                    <Label className="text-[11px] font-semibold text-muted-foreground uppercase tracking-wider">
                                        Enforce Before Workflow Step
                                    </Label>
                                    <OptionSelect
                                        value={requirement.enforcement_step ?? ""}
                                        onChange={(enforcementStep) =>
                                            setRequirement(requirement.key, (cur) => ({
                                                ...cur,
                                                enforcement_step: enforcementStep || null,
                                            }))
                                        }
                                        options={effective.workflow.steps.map((step) => ({ value: step.key, label: step.label }))}
                                        placeholder="Collect without blocking"
                                    />
                                </div>

                                <div className="space-y-1.5">
                                    <Label className="text-[11px] font-semibold text-muted-foreground uppercase tracking-wider">
                                        Instructions for Applicant
                                    </Label>
                                    <Input
                                        className="h-8.5 text-xs"
                                        value={requirement.description ?? ""}
                                        onChange={(e) => setRequirement(requirement.key, (cur) => ({ ...cur, description: e.target.value }))}
                                        placeholder="e.g. Original copy or certified true copy"
                                    />
                                </div>
                            </div>
                        </article>
                    );
                })}

                {effective.requirements.length === 0 && (
                    <div className="rounded-xl border border-dashed border-border/70 p-8 text-center text-xs text-muted-foreground">
                        No document requirements registered. Click a preset above or &quot;Add Custom Document&quot;.
                    </div>
                )}
            </div>

            <Button
                type="button"
                variant="outline"
                onClick={() => addDocument()}
                className="h-9 gap-1.5 text-xs border-dashed bg-background/60"
            >
                <Plus className="size-3.5" />
                <span>Add Custom Document</span>
            </Button>
        </section>
    );
}

/* =========================================================================
   STAGE 4: SUBJECTS & CLASS ASSIGNMENT SECTION
   ========================================================================= */

export function AssignmentSection({ effective, local, registry, options, inheritance, onChange, onHelp }: StrategySectionProps) {
    return (
        <section className="space-y-6">
            <div className="border-b border-border/40 pb-4">
                <h2 className="text-xl font-bold tracking-tight text-foreground">Stage 4: Subjects & Class Assignment Strategy</h2>
                <p className="mt-1 text-xs text-muted-foreground max-w-2xl leading-relaxed">
                    Select the strategy that determines how course subjects and class sections are tagged for applicants. Automated strategies verify seat capacity transactionally.
                </p>
            </div>

            <StrategyEditor
                title="Subject & Class Selection Engine"
                description="Choose between advisor/staff manual block assignment or automated transactional seat allocation."
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

/* =========================================================================
   STAGE 5: TUITION & BILLING SECTION
   ========================================================================= */

export function BillingSection({ effective, local, registry, options, inheritance, onChange, onHelp }: StrategySectionProps) {
    const inherited = sectionSource(inheritance.source_map, "billing");
    const ensureBilling = () => local.billing ?? copy(effective.billing);

    return (
        <section className="space-y-6">
            <div className="border-b border-border/40 pb-4">
                <h2 className="text-xl font-bold tracking-tight text-foreground">Stage 5: Tuition & Billing Configuration</h2>
                <p className="mt-1 text-xs text-muted-foreground max-w-2xl leading-relaxed">
                    Select the assessment strategy, lecture/lab unit rates, modular multipliers, allowed payment channels, and official eReceipt issuance rules.
                </p>
            </div>

            <StrategyEditor
                title="Assessment & Fee Computation Strategy"
                description="Controls unit lecture rates, laboratory fees, modular multipliers, and minimum initial payment required before enrollment completion."
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

            <div className="grid gap-5 md:grid-cols-2">
                {/* Allowed Payment Methods */}
                <Card className="border-border/60 bg-card/70 shadow-xs backdrop-blur-xs">
                    <CardHeader className="pb-3 border-b border-border/40">
                        <div className="flex items-center gap-2">
                            <CreditCard className="size-4 text-primary" />
                            <CardTitle className="text-sm font-semibold">Accepted Payment Channels</CardTitle>
                        </div>
                        <CardDescription className="text-xs">
                            Only approved payment channels satisfy payment gates in this blueprint.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="pt-4">
                        <MultiSelect
                            options={options.payment_methods ?? []}
                            selected={effective.billing.allowed_payment_methods ?? []}
                            onChange={(allowedPaymentMethods) =>
                                onChange({ ...local, billing: { ...ensureBilling(), allowed_payment_methods: allowedPaymentMethods } })
                            }
                            placeholder="Choose accepted payment channels"
                        />
                    </CardContent>
                </Card>

                {/* Official Receipt Policy */}
                <Card className="border-border/60 bg-card/70 shadow-xs backdrop-blur-xs">
                    <CardHeader className="pb-3 border-b border-border/40">
                        <div className="flex items-center gap-2">
                            <Receipt className="size-4 text-primary" />
                            <CardTitle className="text-sm font-semibold">Official Receipt Policy</CardTitle>
                        </div>
                        <CardDescription className="text-xs">
                            Define whether transactions require an official eReceipt number before advancing.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="pt-4">
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
                                { value: "required", label: "Official Receipt mandatory (eReceipt generated)" },
                                { value: "optional", label: "Receipt optional (Permit conditional advancement)" },
                                { value: "none", label: "Authorized zero-receipt pathway (Audited)" },
                            ]}
                            placeholder="Choose receipt policy"
                            allowEmpty={false}
                        />
                    </CardContent>
                </Card>
            </div>
        </section>
    );
}

/* =========================================================================
   STAGE 6: WORKFLOW & NOTIFICATIONS SECTION
   ========================================================================= */

export function WorkflowSection({ effective, local, registry, options, inheritance, onChange }: Omit<StrategySectionProps, "onHelp">) {
    const workflowSource = sectionSource(inheritance.source_map, "workflow");
    const notificationsSource = sectionSource(inheritance.source_map, "notifications");

    return (
        <section className="space-y-8">
            <div className="border-b border-border/40 pb-4">
                <h2 className="text-xl font-bold tracking-tight text-foreground">Stage 6: Approval Workflow & Notifications</h2>
                <p className="mt-1 text-xs text-muted-foreground max-w-2xl leading-relaxed">
                    Map out the applicant&apos;s step-by-step approval journey across registrar, finance, and dean review, then configure real-time notification dispatches.
                </p>
            </div>

            {/* Workflow Pipeline */}
            <div className="space-y-4">
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
            </div>

            {/* Notification Triggers */}
            <div className="space-y-4 rounded-2xl border border-border/60 bg-card/65 p-4 sm:p-5 shadow-xs">
                <div>
                    <h3 className="text-sm font-semibold text-foreground uppercase tracking-wider">
                        Automated Notification Dispatches
                    </h3>
                    <p className="mt-0.5 text-xs text-muted-foreground">
                        Trigger emails, in-app updates, or SMS whenever an enrollment changes status.
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

                <div className="space-y-2.5">
                    {effective.notifications.map((notification, index) => (
                        <div
                            key={notification.key}
                            className="grid gap-3 rounded-xl border border-border/50 bg-background/50 p-3 sm:grid-cols-[1fr_1fr_auto] sm:items-end"
                        >
                            <div className="space-y-1.5">
                                <Label className="text-[11px] font-semibold text-muted-foreground uppercase tracking-wider">
                                    Trigger Event Milestone
                                </Label>
                                <OptionSelect
                                    value={notification.event}
                                    onChange={(event) => {
                                        const next = copy(local.notifications ?? effective.notifications);
                                        next[index] = { ...next[index], event, key: `notify_${event}_${index + 1}` };
                                        onChange({ ...local, notifications: next });
                                    }}
                                    options={[
                                        { value: "any_transition", label: "Every approval transition" },
                                        ...effective.workflow.steps.map((step) => ({ value: step.key, label: step.label })),
                                    ]}
                                    placeholder="Choose an event"
                                    allowEmpty={false}
                                />
                            </div>

                            <div className="space-y-1.5">
                                <Label className="text-[11px] font-semibold text-muted-foreground uppercase tracking-wider">
                                    Dispatch Channel
                                </Label>
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
                                className="size-8.5 text-muted-foreground hover:text-destructive hover:bg-destructive/10"
                                onClick={() => {
                                    const next = copy(local.notifications ?? effective.notifications);
                                    next.splice(index, 1);
                                    onChange({ ...local, notifications: next });
                                }}
                                aria-label="Remove notification"
                            >
                                <Trash2 className="size-3.5" />
                            </Button>
                        </div>
                    ))}

                    {effective.notifications.length === 0 && (
                        <div className="rounded-xl border border-dashed border-border/70 p-6 text-center text-xs text-muted-foreground">
                            No notifications configured.
                        </div>
                    )}
                </div>

                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    className="h-8 gap-1.5 text-xs bg-background"
                    onClick={() => {
                        const next = copy(local.notifications ?? effective.notifications);
                        next.push({
                            key: `notify_${Date.now()}`,
                            event: "any_transition",
                            channel: options.notification_channels?.[0]?.value ?? "mail",
                            enabled: true,
                        });
                        onChange({ ...local, notifications: next });
                    }}
                >
                    <Plus className="size-3" />
                    <span>Add Notification Trigger</span>
                </Button>
            </div>
        </section>
    );
}

/* =========================================================================
   STAGE 7: TEST, PUBLISH & ACTIVATE SECTION
   ========================================================================= */

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
        school_id: options.schools?.[0]?.value ?? "",
        student_type: options.student_types?.[0]?.value ?? "",
        course_id: options.programs?.[0]?.value ?? "",
        school_year: "2026 - 2027",
        semester: options.periods?.[0]?.value ?? "1",
        channel: "portal",
    });

    const runSimulation = async () => {
        setSimulating(true);
        try {
            const payload = form.data.student_enrollment_id
                ? { student_enrollment_id: Number(form.data.student_enrollment_id) }
                : {
                      school_id: form.data.school_id ? Number(form.data.school_id) : undefined,
                      student_type: form.data.student_type,
                      course_id: form.data.course_id ? Number(form.data.course_id) : undefined,
                      school_year: form.data.school_year,
                      semester: form.data.semester ? Number(form.data.semester) : undefined,
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
        { label: "Draft changes saved", ready: !dirty },
        { label: "Simulation validation executed", ready: Boolean(simulationResult?.checksum) },
        { label: "Zero blocking validation errors", ready: Boolean(simulationResult?.checksum) && !simulationResult?.blockers?.length },
        { label: "Revision note documented (≥ 5 chars)", ready: notes.trim().length >= 5 },
    ];

    const readyToPublish = readiness.every((item) => item.ready) && version.state === "draft";
    const isGlobal = Object.keys(policy.scope).length === 0;
    const canActivate = !rollout.active && rollout.ready && isGlobal && policy.active_version_id === version.id && Boolean(simulationResult?.checksum);

    return (
        <section className="space-y-6">
            <div className="border-b border-border/40 pb-4">
                <h2 className="text-xl font-bold tracking-tight text-foreground">Stage 7: Simulation, Publishing & Activation</h2>
                <p className="mt-1 text-xs text-muted-foreground max-w-2xl leading-relaxed">
                    Test the resolved blueprint against a representative applicant, verify fee computation and clearances, and publish as an immutable version.
                </p>
            </div>

            {dirty && (
                <Alert className="border-amber-500/30 bg-amber-500/5">
                    <AlertTriangle className="size-4 text-amber-600" />
                    <AlertTitle className="text-xs font-semibold">Save draft changes before simulation</AlertTitle>
                    <AlertDescription className="text-[11px] text-muted-foreground">
                        The simulation engine runs against the persisted draft to ensure what you test matches what will be published.
                    </AlertDescription>
                </Alert>
            )}

            {/* Two-Column Testing Sandbox */}
            <div className="grid gap-5 xl:grid-cols-[21rem_minmax(0,1fr)]">
                {/* Left: Test Student Builder */}
                <Card className="border-border/60 bg-card/70 shadow-xs backdrop-blur-xs h-fit">
                    <CardHeader className="pb-3 border-b border-border/40">
                        <CardTitle className="text-sm font-semibold">Representative Test Applicant</CardTitle>
                        <CardDescription className="text-xs">
                            Simulate with an existing student record or configure a mock applicant profile.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="pt-4 space-y-3.5">
                        <div className="space-y-1.5">
                            <Label className="text-[11px] font-semibold text-muted-foreground uppercase tracking-wider">
                                Existing Enrollment ID (Optional)
                            </Label>
                            <Input
                                className="h-8.5 font-mono text-xs"
                                inputMode="numeric"
                                value={form.data.student_enrollment_id}
                                onChange={(e) => form.setData("student_enrollment_id", e.target.value)}
                                placeholder="e.g. 1042"
                            />
                        </div>

                        {!form.data.student_enrollment_id && (
                            <>
                                <div className="space-y-1.5">
                                    <Label className="text-[11px] font-semibold text-muted-foreground uppercase tracking-wider">School Campus</Label>
                                    <OptionSelect
                                        value={form.data.school_id}
                                        onChange={(value) => form.setData("school_id", value)}
                                        options={options.schools}
                                        placeholder="Choose school"
                                        allowEmpty={false}
                                    />
                                </div>
                                <div className="space-y-1.5">
                                    <Label className="text-[11px] font-semibold text-muted-foreground uppercase tracking-wider">Student Classification</Label>
                                    <OptionSelect
                                        value={form.data.student_type}
                                        onChange={(value) => form.setData("student_type", value)}
                                        options={options.student_types}
                                        placeholder="Choose student type"
                                        allowEmpty={false}
                                    />
                                </div>
                                <div className="space-y-1.5">
                                    <Label className="text-[11px] font-semibold text-muted-foreground uppercase tracking-wider">Academic Program</Label>
                                    <OptionSelect
                                        value={form.data.course_id}
                                        onChange={(value) => form.setData("course_id", value)}
                                        options={options.programs}
                                        placeholder="Choose program"
                                        allowEmpty={false}
                                    />
                                </div>
                                <div className="grid grid-cols-2 gap-2">
                                    <div className="space-y-1.5">
                                        <Label className="text-[11px] font-semibold text-muted-foreground uppercase tracking-wider">Academic Year</Label>
                                        <Input
                                            className="h-8.5 font-mono text-xs"
                                            value={form.data.school_year}
                                            onChange={(e) => form.setData("school_year", e.target.value)}
                                            placeholder="2026 - 2027"
                                        />
                                    </div>
                                    <div className="space-y-1.5">
                                        <Label className="text-[11px] font-semibold text-muted-foreground uppercase tracking-wider">Semester</Label>
                                        <OptionSelect
                                            value={form.data.semester}
                                            onChange={(value) => form.setData("semester", value)}
                                            options={options.periods ?? [{ value: "1", label: "1st" }, { value: "2", label: "2nd" }]}
                                            placeholder="Term"
                                            allowEmpty={false}
                                        />
                                    </div>
                                </div>
                            </>
                        )}

                        <Button
                            className="h-9 w-full gap-1.5 text-xs shadow-xs"
                            disabled={dirty || simulating}
                            onClick={runSimulation}
                        >
                            {simulating ? <Loader2 className="size-3.5 animate-spin" /> : <Sparkles className="size-3.5" />}
                            <span>{simulating ? "Simulating Journey..." : "Run Journey Simulation"}</span>
                        </Button>
                    </CardContent>
                </Card>

                {/* Right: Simulation Report Engine */}
                <SimulationReport result={simulationResult} onFix={onFix} />
            </div>

            {/* Publication Readiness Checklist */}
            <Card className="border-border/60 bg-card/70 shadow-xs backdrop-blur-xs">
                <CardHeader className="pb-3 border-b border-border/40">
                    <CardTitle className="text-sm font-semibold">Publication Readiness Verification</CardTitle>
                    <CardDescription className="text-xs">
                        Checklist gates must be satisfied prior to version release.
                    </CardDescription>
                </CardHeader>
                <CardContent className="pt-4 space-y-4">
                    <div className="grid gap-2 sm:grid-cols-2">
                        {readiness.map((item) => (
                            <div
                                key={item.label}
                                className={cn(
                                    "flex items-center gap-2.5 rounded-lg border p-2.5 text-xs font-medium",
                                    item.ready
                                        ? "border-emerald-500/30 bg-emerald-500/5 text-emerald-950 dark:text-emerald-300"
                                        : "border-border/50 bg-background/50 text-muted-foreground",
                                )}
                            >
                                <span
                                    className={cn(
                                        "flex size-5 shrink-0 items-center justify-center rounded-md",
                                        item.ready ? "bg-emerald-500 text-white" : "bg-muted text-muted-foreground",
                                    )}
                                >
                                    {item.ready ? <Check className="size-3" /> : <span className="size-1.5 rounded-full bg-current opacity-40" />}
                                </span>
                                <span>{item.label}</span>
                            </div>
                        ))}
                    </div>

                    <div className="space-y-1.5">
                        <Label htmlFor="policy-change-notes" className="text-xs font-semibold">
                            Revision Change Notes (Permanent audit record)
                        </Label>
                        <Textarea
                            id="policy-change-notes"
                            value={notes}
                            onChange={(e) => setNotes(e.target.value)}
                            placeholder="e.g. Updated course fee multipliers and enforced high school report card clearance prior to cashier review."
                            rows={2}
                            className="text-xs resize-none"
                        />
                    </div>

                    <div className="flex flex-wrap gap-2.5 pt-2">
                        <Button
                            className="h-9 gap-1.5 text-xs shadow-xs"
                            disabled={!canUpdate || !readyToPublish}
                            onClick={() =>
                                router.post(
                                    publish.url([policy.id, version.id]),
                                    { simulation_checksum: simulationResult?.checksum },
                                    { preserveScroll: true },
                                )
                            }
                        >
                            <GitBranch className="size-3.5" />
                            <span>Publish Version v{version.version}</span>
                        </Button>

                        {canActivate && (
                            <AlertDialog>
                                <AlertDialogTrigger asChild>
                                    <Button variant="outline" className="h-9 gap-1.5 text-xs bg-background">
                                        <ShieldCheck className="size-3.5 text-emerald-500" />
                                        <span>Activate Engine for Future Admissions</span>
                                    </Button>
                                </AlertDialogTrigger>
                                <AlertDialogContent>
                                    <AlertDialogHeader>
                                        <AlertDialogTitle className="text-base font-semibold">Activate Dynamic Policy Engine?</AlertDialogTitle>
                                        <AlertDialogDescription className="text-xs">
                                            Future applicant registrations will immediately route through published policy blueprints. Existing enrollments retain their current snapshots.
                                        </AlertDialogDescription>
                                    </AlertDialogHeader>
                                    <AlertDialogFooter>
                                        <AlertDialogCancel className="text-xs h-8">Keep Legacy Active</AlertDialogCancel>
                                        <AlertDialogAction
                                            className="text-xs h-8"
                                            onClick={() =>
                                                router.post(activate.url(), {
                                                    confirmation: "activate enrollment policies",
                                                    simulation_checksum: simulationResult?.checksum,
                                                })
                                            }
                                        >
                                            Activate Engine
                                        </AlertDialogAction>
                                    </AlertDialogFooter>
                                </AlertDialogContent>
                            </AlertDialog>
                        )}
                    </div>
                </CardContent>
            </Card>

            {/* Advanced JSON Migration Details */}
            <details className="rounded-xl border border-border/60 bg-card/50 text-xs">
                <summary className="flex cursor-pointer items-center justify-between p-3.5 font-semibold text-muted-foreground hover:text-foreground">
                    <span>Advanced backup and technical details</span>
                    <span className="text-[10px] font-mono uppercase bg-muted/60 px-2 py-0.5 rounded">Technical</span>
                </summary>
                <div className="space-y-3.5 border-t border-border/40 p-4">
                    <p className="text-muted-foreground text-xs leading-relaxed">
                        JSON is never edited here. Upload a trusted KoAkademy backup, review its plain-language summary, and import it as a separate draft.
                    </p>
                    <Input type="file" accept="application/json,.json" onChange={(event) => readImport(event.target.files?.[0], setImportPreview)} className="h-8.5 text-xs" />
                    {importPreview && (
                        <div className="rounded-lg border border-border/50 bg-muted/30 p-3 space-y-2">
                            <p className="font-semibold text-foreground">{importPreview.name}</p>
                            <p className="text-[11px] text-muted-foreground">
                                {importPreview.configuration.rules?.length ?? 0} rules • {importPreview.configuration.requirements?.length ?? 0} documents • {importPreview.configuration.workflow?.steps.length ?? 0} approval steps
                            </p>
                            <Button
                                size="sm"
                                onClick={() =>
                                    router.post(importMethod.url(), {
                                        name: importPreview.name,
                                        configuration: importPreview.configuration as unknown as FormDataConvertible,
                                    })
                                }
                                className="h-8 gap-1.5 text-xs"
                            >
                                <FileUp className="size-3.5" />
                                <span>Import as New Working Draft</span>
                            </Button>
                        </div>
                    )}
                </div>
            </details>
        </section>
    );
}

/* =========================================================================
   HELPER COMPONENTS: StrategyEditor & AtomicInheritanceBanner
   ========================================================================= */

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
        <div className="space-y-4">
            <div className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <div className="flex flex-wrap items-center gap-2">
                        <h3 className="text-sm font-semibold text-foreground">{title}</h3>
                        {source && !localValue && (
                            <Badge variant="outline" className="text-[10px] font-normal border-border/60">
                                Inherited from {source.policy_name}
                            </Badge>
                        )}
                    </div>
                    <p className="mt-0.5 text-xs text-muted-foreground">{description}</p>
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

            {/* Strategy Options Cards */}
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
                                "flex flex-col justify-between rounded-xl border p-3.5 text-left transition-all outline-none",
                                active
                                    ? "border-primary bg-primary/5 shadow-xs ring-1 ring-primary/20"
                                    : "border-border/60 bg-card/60 hover:bg-muted/40 hover:border-border",
                            )}
                        >
                            <div className="flex items-start justify-between gap-2">
                                <span className="text-xs font-semibold text-foreground">{item.label}</span>
                                {active && <CheckCircle2 className="size-4 text-primary shrink-0" />}
                            </div>
                            <span className="mt-2 block text-[11px] leading-relaxed text-muted-foreground line-clamp-2">
                                {item.operator_schema?.description ?? "Configure this behavioral strategy."}
                            </span>
                        </button>
                    );
                })}
            </div>

            {/* Selected Strategy Configuration Fields */}
            <Card className="border-border/60 bg-card/75 shadow-xs backdrop-blur-xs">
                <CardHeader className="pb-3 border-b border-border/40">
                    <CardTitle className="text-sm font-semibold">{selected?.label ?? value.strategy}</CardTitle>
                    <CardDescription className="text-xs">{help.what_it_does}</CardDescription>
                </CardHeader>
                <CardContent className="pt-4">
                    <SchemaFields
                        item={selected}
                        value={value.configuration}
                        options={options}
                        onChange={(configuration) => onChange({ strategy: value.strategy, configuration })}
                    />
                </CardContent>
            </Card>

            {source && (
                <Button type="button" variant="ghost" size="sm" className="h-8 text-xs text-muted-foreground" disabled={!localValue} onClick={onReset}>
                    <RotateCcw className="size-3 mr-1.5" />
                    <span>Reset to inherited {title.toLowerCase()}</span>
                </Button>
            )}
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
        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 rounded-xl border border-border/50 bg-muted/25 p-3.5 text-xs">
            <div>
                <p className="font-semibold text-foreground">
                    {overridden ? `Custom override active for ${label}` : `Inheriting ${label} from ${sourceName}`}
                </p>
                <p className="text-[11px] text-muted-foreground mt-0.5">
                    {overridden
                        ? "This blueprint manages its own isolated configuration."
                        : "Inherited definitions stay in sync with the parent baseline."}
                </p>
            </div>
            <Button
                type="button"
                variant="outline"
                size="sm"
                className="h-7.5 text-xs bg-background shrink-0"
                onClick={overridden ? onReset : onOverride}
            >
                {overridden ? <RotateCcw className="size-3 mr-1" /> : <ArrowRight className="size-3 mr-1" />}
                <span>{overridden ? "Use Parent Baseline" : "Override for Cohort"}</span>
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
