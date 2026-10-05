import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Separator } from "@/components/ui/separator";
import { Switch } from "@/components/ui/switch";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { useForm } from "@inertiajs/react";
import {
    Calendar,
    Check,
    CheckCircle2,
    GraduationCap,
    Hash,
    HelpCircle,
    Info,
    Layers,
    Loader2,
    Save,
    ShieldAlert,
    Sliders,
    Sparkles,
    UserCheck,
    Users,
} from "lucide-react";
import type { FormEvent } from "react";
import { toast } from "sonner";
import { route } from "ziggy-js";

import SystemManagementLayout from "./layout";
import type { IdSequenceConfig, StudentTypePrefixes, SystemManagementPageProps } from "./types";

interface StudentSequenceFormValues {
    start_number: number;
    next_number: number;
    increment_by: number;
    padding: number | null;
    prefix_mode: "none" | "static" | "year" | "by_type";
    prefix_value: string;
    enforce_prefix: boolean;
    enforce_length: boolean;
    exact_length: number | null;
    min_length: number;
    max_length: number;
    type_prefixes: StudentTypePrefixes;
}

interface StaffSequenceFormValues {
    start_number: number;
    next_number: number;
    increment_by: number;
    padding: number | null;
}

interface IdentifierSequenceForm {
    student: StudentSequenceFormValues;
    staff: StaffSequenceFormValues;
}

function studentSequenceToForm(sequence: IdSequenceConfig): StudentSequenceFormValues {
    return {
        start_number: sequence.start_number,
        next_number: sequence.next_number,
        increment_by: sequence.increment_by,
        padding: sequence.padding,
        prefix_mode: sequence.prefix_mode ?? "none",
        prefix_value: sequence.prefix_value ?? "",
        enforce_prefix: Boolean(sequence.enforce_prefix),
        enforce_length: Boolean(sequence.enforce_length),
        exact_length: sequence.exact_length ?? 6,
        min_length: sequence.min_length ?? 4,
        max_length: sequence.max_length ?? 12,
        type_prefixes: sequence.type_prefixes ?? {
            college: "2",
            tesda: "2",
            dhrt: "2",
            shs: "3",
        },
    };
}

function staffSequenceToForm(sequence: IdSequenceConfig): StaffSequenceFormValues {
    return {
        start_number: sequence.start_number,
        next_number: sequence.next_number,
        increment_by: sequence.increment_by,
        padding: sequence.padding,
    };
}

function parseNumericValue(value: string): number {
    return Number.parseInt(value || "0", 10);
}

function parseOptionalNumericValue(value: string): number | null {
    if (value.trim() === "") {
        return null;
    }

    return parseNumericValue(value);
}

function calculateStudentPreview(
    values: StudentSequenceFormValues,
    type: "college" | "tesda" | "dhrt" | "shs" = "college"
): string {
    const rawNumber = String(values.next_number || 0);
    const padded = values.padding && values.padding > 0 ? rawNumber.padStart(values.padding, "0") : rawNumber;

    let prefix = "";
    if (values.prefix_mode === "static") {
        prefix = values.prefix_value.trim();
    } else if (values.prefix_mode === "year") {
        prefix = String(new Date().getFullYear());
    } else if (values.prefix_mode === "by_type") {
        prefix = values.type_prefixes[type] ?? "2";
    }

    if (prefix && !padded.startsWith(prefix)) {
        return `${prefix}${padded}`;
    }

    return padded;
}

function formatStaffPreview(values: StaffSequenceFormValues): string {
    const raw = String(values.next_number || 0);

    if (!values.padding || values.padding < 1) {
        return raw;
    }

    return raw.padStart(values.padding, "0");
}

export default function SystemManagementIdentifiersPage({ user, access, id_sequences }: SystemManagementPageProps) {
    const canUpdate = access.sections.identifiers?.can_update ?? false;
    const form = useForm<IdentifierSequenceForm>({
        student: studentSequenceToForm(id_sequences.student),
        staff: staffSequenceToForm(id_sequences.staff),
    });

    const setStudentField = <K extends keyof StudentSequenceFormValues>(
        field: K,
        value: StudentSequenceFormValues[K]
    ): void => {
        form.setData("student", {
            ...form.data.student,
            [field]: value,
        });
    };

    const setTypePrefix = (type: keyof StudentTypePrefixes, value: string): void => {
        form.setData("student", {
            ...form.data.student,
            type_prefixes: {
                ...form.data.student.type_prefixes,
                [type]: value,
            },
        });
    };

    const setStaffField = <K extends keyof StaffSequenceFormValues>(
        field: K,
        value: StaffSequenceFormValues[K]
    ): void => {
        form.setData("staff", {
            ...form.data.staff,
            [field]: value,
        });
    };

    const submit = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();

        form.put(route("administrators.system-management.identifiers.update"), {
            preserveScroll: true,
            onSuccess: () => toast.success("Identifier sequence settings updated successfully."),
            onError: () => toast.error("Unable to update identifier sequences. Please check errors."),
        });
    };

    const collegePreview = calculateStudentPreview(form.data.student, "college");
    const tesdaPreview = calculateStudentPreview(form.data.student, "tesda");
    const dhrtPreview = calculateStudentPreview(form.data.student, "dhrt");
    const staffPreview = formatStaffPreview(form.data.staff);

    return (
        <SystemManagementLayout
            user={user}
            access={access}
            activeSection="identifiers"
            heading="Student & Staff Identifiers"
            description="Configure customizable numbering sequences, prefixes, and validation policies for students and personnel."
        >
            <form onSubmit={submit} className="space-y-6">
                <Alert className="border-border/60 bg-muted/30">
                    <Info className="h-4 w-4 text-primary" />
                    <AlertTitle className="text-sm font-semibold">Configurable Student Numbering System</AlertTitle>
                    <AlertDescription className="text-xs text-muted-foreground mt-0.5">
                        Student IDs are sequential numeric identifiers used for automated records, RFID cards, and registration.
                        You can customize starting values, prefix modes (year, type, or custom), and choose whether to enforce strict digit lengths or allow flexible numbers.
                    </AlertDescription>
                </Alert>

                <Tabs defaultValue="students" className="space-y-6">
                    <TabsList className="grid w-full max-w-md grid-cols-2 bg-muted/60 p-1">
                        <TabsTrigger value="students" className="gap-2 text-xs font-semibold">
                            <GraduationCap className="size-4" />
                            <span>Student Identifiers</span>
                        </TabsTrigger>
                        <TabsTrigger value="staff" className="gap-2 text-xs font-semibold">
                            <Users className="size-4" />
                            <span>Staff & Faculty</span>
                        </TabsTrigger>
                    </TabsList>

                    {/* ================= STUDENT ID CONFIGURATION ================= */}
                    <TabsContent value="students" className="space-y-6 focus-visible:outline-none">
                        {/* Live Preview Bar */}
                        <div className="rounded-2xl border border-primary/20 bg-primary/5 p-4 sm:p-5">
                            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                                <div className="space-y-1">
                                    <div className="flex items-center gap-2">
                                        <Sparkles className="size-4 text-primary" />
                                        <span className="text-xs font-semibold uppercase tracking-wider text-primary">
                                            Live Generation Simulator
                                        </span>
                                    </div>
                                    <p className="text-xs text-muted-foreground">
                                        Calculated preview for next issued student admission records based on current settings.
                                    </p>
                                </div>
                                <div className="flex flex-wrap items-center gap-3">
                                    <div className="flex items-center gap-2 rounded-xl border border-border/60 bg-card/80 px-3 py-1.5 shadow-2xs">
                                        <span className="text-[11px] font-medium text-muted-foreground">College:</span>
                                        <Badge variant="secondary" className="font-mono text-xs font-bold text-foreground">
                                            {collegePreview}
                                        </Badge>
                                    </div>
                                    <div className="flex items-center gap-2 rounded-xl border border-border/60 bg-card/80 px-3 py-1.5 shadow-2xs">
                                        <span className="text-[11px] font-medium text-muted-foreground">TESDA:</span>
                                        <Badge variant="secondary" className="font-mono text-xs font-bold text-foreground">
                                            {tesdaPreview}
                                        </Badge>
                                    </div>
                                    <div className="flex items-center gap-2 rounded-xl border border-border/60 bg-card/80 px-3 py-1.5 shadow-2xs">
                                        <span className="text-[11px] font-medium text-muted-foreground">DHRT:</span>
                                        <Badge variant="secondary" className="font-mono text-xs font-bold text-foreground">
                                            {dhrtPreview}
                                        </Badge>
                                    </div>
                                    <div className="flex items-center gap-2 rounded-xl border border-border/60 bg-card/80 px-3 py-1.5 shadow-2xs">
                                        <span className="text-[11px] font-medium text-muted-foreground">SHS:</span>
                                        <Badge variant="outline" className="font-mono text-[11px] text-muted-foreground">
                                            12-digit LRN
                                        </Badge>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div className="grid gap-6 lg:grid-cols-2">
                            {/* Card 1: Sequential Counter Configuration */}
                            <Card className="border-border/60 bg-card/70 shadow-xs backdrop-blur-xs flex flex-col justify-between">
                                <CardHeader className="pb-4 border-b border-border/40">
                                    <div className="flex items-start gap-3">
                                        <div className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary mt-0.5">
                                            <Hash className="size-5" />
                                        </div>
                                        <div>
                                            <CardTitle className="text-base font-semibold">Sequential Numbering</CardTitle>
                                            <CardDescription className="text-xs mt-0.5">
                                                Control base starting number, current sequence pointer, and zero padding.
                                            </CardDescription>
                                        </div>
                                    </div>
                                </CardHeader>
                                <CardContent className="pt-5 space-y-4">
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <div className="space-y-1.5">
                                            <Label className="text-xs font-semibold text-foreground">Starting Number</Label>
                                            <Input
                                                type="number"
                                                min={1}
                                                max={2147483647}
                                                value={form.data.student.start_number}
                                                disabled={!canUpdate || form.processing}
                                                onChange={(e) => setStudentField("start_number", parseNumericValue(e.target.value))}
                                                className="font-mono text-sm"
                                            />
                                            {form.errors["student.start_number"] && (
                                                <p className="text-destructive text-xs">{form.errors["student.start_number"]}</p>
                                            )}
                                            <p className="text-[11px] text-muted-foreground">Initial baseline number (e.g. 200000 or 1).</p>
                                        </div>

                                        <div className="space-y-1.5">
                                            <Label className="text-xs font-semibold text-foreground">Next Number to Issue</Label>
                                            <Input
                                                type="number"
                                                min={1}
                                                max={2147483647}
                                                value={form.data.student.next_number}
                                                disabled={!canUpdate || form.processing}
                                                onChange={(e) => setStudentField("next_number", parseNumericValue(e.target.value))}
                                                className="font-mono text-sm"
                                            />
                                            {form.errors["student.next_number"] && (
                                                <p className="text-destructive text-xs">{form.errors["student.next_number"]}</p>
                                            )}
                                            <p className="text-[11px] text-muted-foreground">The exact number assigned on next registration.</p>
                                        </div>

                                        <div className="space-y-1.5">
                                            <Label className="text-xs font-semibold text-foreground">Increment Step</Label>
                                            <Input
                                                type="number"
                                                min={1}
                                                max={1000}
                                                value={form.data.student.increment_by}
                                                disabled={!canUpdate || form.processing}
                                                onChange={(e) => setStudentField("increment_by", parseNumericValue(e.target.value))}
                                                className="font-mono text-sm"
                                            />
                                            {form.errors["student.increment_by"] && (
                                                <p className="text-destructive text-xs">{form.errors["student.increment_by"]}</p>
                                            )}
                                            <p className="text-[11px] text-muted-foreground">Step count per student created (default 1).</p>
                                        </div>

                                        <div className="space-y-1.5">
                                            <Label className="text-xs font-semibold text-foreground">Padding Digits (Zeros)</Label>
                                            <Input
                                                type="number"
                                                min={0}
                                                max={12}
                                                value={form.data.student.padding ?? ""}
                                                disabled={!canUpdate || form.processing}
                                                placeholder="e.g. 6 or empty"
                                                onChange={(e) => setStudentField("padding", parseOptionalNumericValue(e.target.value))}
                                                className="font-mono text-sm"
                                            />
                                            {form.errors["student.padding"] && (
                                                <p className="text-destructive text-xs">{form.errors["student.padding"]}</p>
                                            )}
                                            <p className="text-[11px] text-muted-foreground">
                                                Zero-pad numbers (e.g. 6 pads &apos;1&apos; to &apos;000001&apos;).
                                            </p>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>

                            {/* Card 2: Prefix & Formatting Rules */}
                            <Card className="border-border/60 bg-card/70 shadow-xs backdrop-blur-xs flex flex-col justify-between">
                                <CardHeader className="pb-4 border-b border-border/40">
                                    <div className="flex items-start gap-3">
                                        <div className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary mt-0.5">
                                            <Sliders className="size-5" />
                                        </div>
                                        <div>
                                            <CardTitle className="text-base font-semibold">Prefix & Formatting Rules</CardTitle>
                                            <CardDescription className="text-xs mt-0.5">
                                                Choose how identifiers are prefixed (calendar year, custom static prefix, or per student program).
                                            </CardDescription>
                                        </div>
                                    </div>
                                </CardHeader>
                                <CardContent className="pt-5 space-y-5">
                                    <div className="space-y-2">
                                        <Label className="text-xs font-semibold text-foreground">Prefix Generation Mode</Label>
                                        <div className="grid grid-cols-2 gap-2 sm:grid-cols-4">
                                            {[
                                                { id: "none", label: "No Prefix", desc: "Pure sequence" },
                                                { id: "static", label: "Static Custom", desc: "Fixed string" },
                                                { id: "year", label: "Calendar Year", desc: `${new Date().getFullYear()} + seq` },
                                                { id: "by_type", label: "By Student Type", desc: "Type specific" },
                                            ].map((mode) => {
                                                const isSelected = form.data.student.prefix_mode === mode.id;
                                                return (
                                                    <button
                                                        key={mode.id}
                                                        type="button"
                                                        onClick={() =>
                                                            setStudentField(
                                                                "prefix_mode",
                                                                mode.id as "none" | "static" | "year" | "by_type"
                                                            )
                                                        }
                                                        disabled={!canUpdate || form.processing}
                                                        className={`flex flex-col items-start rounded-xl border p-2.5 text-left transition-all ${
                                                            isSelected
                                                                ? "border-primary bg-primary/10 text-foreground ring-1 ring-primary/40"
                                                                : "border-border/60 bg-card hover:bg-muted/40 text-muted-foreground"
                                                        }`}
                                                    >
                                                        <span className="text-xs font-semibold text-foreground">{mode.label}</span>
                                                        <span className="text-[10px] text-muted-foreground mt-0.5">{mode.desc}</span>
                                                    </button>
                                                );
                                            })}
                                        </div>
                                    </div>

                                    {/* Static custom prefix input */}
                                    {form.data.student.prefix_mode === "static" && (
                                        <div className="space-y-1.5 rounded-xl border border-border/50 bg-muted/20 p-3">
                                            <Label className="text-xs font-semibold text-foreground">Static Prefix String</Label>
                                            <Input
                                                value={form.data.student.prefix_value}
                                                disabled={!canUpdate || form.processing}
                                                placeholder="e.g. 2, 26, or STU"
                                                maxLength={10}
                                                onChange={(e) => setStudentField("prefix_value", e.target.value)}
                                                className="font-mono text-sm max-w-xs"
                                            />
                                            <p className="text-[11px] text-muted-foreground">
                                                Prepended to all generated IDs (e.g. prefix &apos;26&apos; + &apos;0001&apos; = 260001).
                                            </p>
                                        </div>
                                    )}

                                    {/* Per Student Type prefixes */}
                                    {form.data.student.prefix_mode === "by_type" && (
                                        <div className="space-y-3 rounded-xl border border-border/50 bg-muted/20 p-3">
                                            <Label className="text-xs font-semibold text-foreground">Prefix per Student Program</Label>
                                            <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                                                <div className="space-y-1">
                                                    <span className="text-[11px] font-medium text-muted-foreground">College</span>
                                                    <Input
                                                        value={form.data.student.type_prefixes.college}
                                                        disabled={!canUpdate || form.processing}
                                                        maxLength={6}
                                                        onChange={(e) => setTypePrefix("college", e.target.value)}
                                                        className="font-mono text-xs"
                                                    />
                                                </div>
                                                <div className="space-y-1">
                                                    <span className="text-[11px] font-medium text-muted-foreground">TESDA</span>
                                                    <Input
                                                        value={form.data.student.type_prefixes.tesda}
                                                        disabled={!canUpdate || form.processing}
                                                        maxLength={6}
                                                        onChange={(e) => setTypePrefix("tesda", e.target.value)}
                                                        className="font-mono text-xs"
                                                    />
                                                </div>
                                                <div className="space-y-1">
                                                    <span className="text-[11px] font-medium text-muted-foreground">DHRT</span>
                                                    <Input
                                                        value={form.data.student.type_prefixes.dhrt}
                                                        disabled={!canUpdate || form.processing}
                                                        maxLength={6}
                                                        onChange={(e) => setTypePrefix("dhrt", e.target.value)}
                                                        className="font-mono text-xs"
                                                    />
                                                </div>
                                                <div className="space-y-1">
                                                    <span className="text-[11px] font-medium text-muted-foreground">SHS Fallback</span>
                                                    <Input
                                                        value={form.data.student.type_prefixes.shs}
                                                        disabled={!canUpdate || form.processing}
                                                        maxLength={6}
                                                        onChange={(e) => setTypePrefix("shs", e.target.value)}
                                                        className="font-mono text-xs"
                                                    />
                                                </div>
                                            </div>
                                        </div>
                                    )}

                                    {/* Enforce Prefix Switch */}
                                    <div className="flex items-center justify-between rounded-xl border border-border/60 bg-card p-3">
                                        <div className="space-y-0.5 pr-4">
                                            <Label className="text-xs font-semibold text-foreground cursor-pointer">
                                                Strict Prefix Enforcement
                                            </Label>
                                            <p className="text-[11px] text-muted-foreground">
                                                Require student forms to validate that manually entered IDs start with the configured prefix.
                                            </p>
                                        </div>
                                        <Switch
                                            checked={form.data.student.enforce_prefix}
                                            disabled={!canUpdate || form.processing}
                                            onCheckedChange={(checked) => setStudentField("enforce_prefix", checked)}
                                        />
                                    </div>
                                </CardContent>
                            </Card>

                            {/* Card 3: Length & Validation Policy */}
                            <Card className="border-border/60 bg-card/70 shadow-xs backdrop-blur-xs flex flex-col justify-between lg:col-span-2">
                                <CardHeader className="pb-4 border-b border-border/40">
                                    <div className="flex items-start gap-3">
                                        <div className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary mt-0.5">
                                            <ShieldAlert className="size-5" />
                                        </div>
                                        <div>
                                            <CardTitle className="text-base font-semibold">Digit Length & Validation Policy</CardTitle>
                                            <CardDescription className="text-xs mt-0.5">
                                                Configure whether to enforce a rigid fixed digit length (e.g. exactly 6 digits) or allow flexible length ranges (4-12 digits).
                                            </CardDescription>
                                        </div>
                                    </div>
                                </CardHeader>
                                <CardContent className="pt-5 space-y-4">
                                    <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between rounded-xl border border-border/60 bg-card p-3">
                                        <div className="space-y-0.5 pr-4">
                                            <Label className="text-xs font-semibold text-foreground cursor-pointer">
                                                Enforce Fixed Digit Count
                                            </Label>
                                            <p className="text-[11px] text-muted-foreground">
                                                When enabled, registrations require exact digits (e.g. 6 or 8). When disabled, flexible ranges allow IDs like 7 or 8 digits without failing validation.
                                            </p>
                                        </div>
                                        <Switch
                                            checked={form.data.student.enforce_length}
                                            disabled={!canUpdate || form.processing}
                                            onCheckedChange={(checked) => setStudentField("enforce_length", checked)}
                                        />
                                    </div>

                                    {form.data.student.enforce_length ? (
                                        <div className="space-y-1.5 max-w-xs">
                                            <Label className="text-xs font-semibold text-foreground">Exact Required Digits</Label>
                                            <Input
                                                type="number"
                                                min={3}
                                                max={12}
                                                value={form.data.student.exact_length ?? 6}
                                                disabled={!canUpdate || form.processing}
                                                onChange={(e) => setStudentField("exact_length", parseNumericValue(e.target.value))}
                                                className="font-mono text-sm"
                                            />
                                            <p className="text-[11px] text-muted-foreground">
                                                Every non-SHS student ID must contain exactly this number of digits.
                                            </p>
                                        </div>
                                    ) : (
                                        <div className="grid gap-4 sm:grid-cols-2 max-w-md">
                                            <div className="space-y-1.5">
                                                <Label className="text-xs font-semibold text-foreground">Minimum Digits Allowed</Label>
                                                <Input
                                                    type="number"
                                                    min={1}
                                                    max={12}
                                                    value={form.data.student.min_length}
                                                    disabled={!canUpdate || form.processing}
                                                    onChange={(e) => setStudentField("min_length", parseNumericValue(e.target.value))}
                                                    className="font-mono text-sm"
                                                />
                                            </div>
                                            <div className="space-y-1.5">
                                                <Label className="text-xs font-semibold text-foreground">Maximum Digits Allowed</Label>
                                                <Input
                                                    type="number"
                                                    min={form.data.student.min_length}
                                                    max={12}
                                                    value={form.data.student.max_length}
                                                    disabled={!canUpdate || form.processing}
                                                    onChange={(e) => setStudentField("max_length", parseNumericValue(e.target.value))}
                                                    className="font-mono text-sm"
                                                />
                                            </div>
                                        </div>
                                    )}
                                </CardContent>
                            </Card>
                        </div>
                    </TabsContent>

                    {/* ================= STAFF ID CONFIGURATION ================= */}
                    <TabsContent value="staff" className="space-y-6 focus-visible:outline-none">
                        <Card className="border-border/60 bg-card/70 shadow-xs backdrop-blur-xs">
                            <CardHeader className="pb-4 border-b border-border/40">
                                <div className="flex items-start justify-between gap-4">
                                    <div className="flex items-start gap-3">
                                        <div className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary mt-0.5">
                                            <Users className="size-5" />
                                        </div>
                                        <div>
                                            <div className="flex items-center gap-2">
                                                <CardTitle className="text-base font-semibold">Shared Staff & Faculty Sequence</CardTitle>
                                                <Badge variant="outline" className="text-[10px] font-mono border-border/60">
                                                    Employees
                                                </Badge>
                                            </div>
                                            <CardDescription className="text-xs mt-0.5 max-w-md">
                                                Sequential numeric identifier allocated when onboarding faculty members and administrative personnel.
                                            </CardDescription>
                                        </div>
                                    </div>

                                    <div className="hidden sm:flex flex-col items-end shrink-0">
                                        <span className="text-[10px] uppercase font-mono tracking-wider text-muted-foreground">
                                            Next Staff ID Preview
                                        </span>
                                        <span className="text-base font-mono font-bold tracking-tight text-foreground bg-muted/60 px-2.5 py-0.5 rounded-lg border border-border/50 mt-1">
                                            {staffPreview}
                                        </span>
                                    </div>
                                </div>
                            </CardHeader>
                            <CardContent className="pt-5 space-y-4">
                                <div className="sm:hidden flex items-center justify-between rounded-lg bg-muted/40 p-2.5 border border-border/50">
                                    <span className="text-xs text-muted-foreground font-medium">Next Generated ID</span>
                                    <span className="text-sm font-mono font-bold text-foreground">{staffPreview}</span>
                                </div>

                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="space-y-1.5">
                                        <Label className="text-xs font-semibold text-foreground">Starting Number</Label>
                                        <Input
                                            type="number"
                                            min={1}
                                            value={form.data.staff.start_number}
                                            disabled={!canUpdate || form.processing}
                                            onChange={(e) => setStaffField("start_number", parseNumericValue(e.target.value))}
                                            className="font-mono text-sm"
                                        />
                                        {form.errors["staff.start_number"] && (
                                            <p className="text-destructive text-xs">{form.errors["staff.start_number"]}</p>
                                        )}
                                    </div>

                                    <div className="space-y-1.5">
                                        <Label className="text-xs font-semibold text-foreground">Next Number</Label>
                                        <Input
                                            type="number"
                                            min={1}
                                            value={form.data.staff.next_number}
                                            disabled={!canUpdate || form.processing}
                                            onChange={(e) => setStaffField("next_number", parseNumericValue(e.target.value))}
                                            className="font-mono text-sm"
                                        />
                                        {form.errors["staff.next_number"] && (
                                            <p className="text-destructive text-xs">{form.errors["staff.next_number"]}</p>
                                        )}
                                    </div>

                                    <div className="space-y-1.5">
                                        <Label className="text-xs font-semibold text-foreground">Increment By</Label>
                                        <Input
                                            type="number"
                                            min={1}
                                            max={1000}
                                            value={form.data.staff.increment_by}
                                            disabled={!canUpdate || form.processing}
                                            onChange={(e) => setStaffField("increment_by", parseNumericValue(e.target.value))}
                                            className="font-mono text-sm"
                                        />
                                        {form.errors["staff.increment_by"] && (
                                            <p className="text-destructive text-xs">{form.errors["staff.increment_by"]}</p>
                                        )}
                                    </div>

                                    <div className="space-y-1.5">
                                        <Label className="text-xs font-semibold text-foreground">Padding Digits (Zeros)</Label>
                                        <Input
                                            type="number"
                                            min={0}
                                            max={12}
                                            value={form.data.staff.padding ?? ""}
                                            disabled={!canUpdate || form.processing}
                                            placeholder="No padding"
                                            onChange={(e) => setStaffField("padding", parseOptionalNumericValue(e.target.value))}
                                            className="font-mono text-sm"
                                        />
                                        {form.errors["staff.padding"] && (
                                            <p className="text-destructive text-xs">{form.errors["staff.padding"]}</p>
                                        )}
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </TabsContent>
                </Tabs>

                {/* Sticky Action Footer */}
                <div className="flex flex-col gap-3 rounded-2xl border border-border/60 bg-card/60 p-4 sm:flex-row sm:items-center sm:justify-between shadow-xs">
                    <p className="text-xs text-muted-foreground">
                        Sequences increment atomically upon record creation. Previewing next numbers does not consume sequence values.
                    </p>
                    <Button type="submit" disabled={!canUpdate || form.processing} className="h-9 gap-2 shrink-0">
                        {form.processing ? <Loader2 className="h-4 w-4 animate-spin" /> : <Save className="h-4 w-4" />}
                        <span>Save Identifier Settings</span>
                    </Button>
                </div>
            </form>
        </SystemManagementLayout>
    );
}
