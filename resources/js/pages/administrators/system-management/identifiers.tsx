import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Separator } from "@/components/ui/separator";
import { useForm } from "@inertiajs/react";
import { Check, Hash, Info, Loader2, Save, Sparkles, UserCheck, Users } from "lucide-react";
import type { FormEvent } from "react";
import { toast } from "sonner";
import { route } from "ziggy-js";

import SystemManagementLayout from "./layout";
import type { IdSequenceConfig, SystemManagementPageProps } from "./types";

interface SequenceFormValues {
    start_number: number;
    next_number: number;
    increment_by: number;
    padding: number | null;
}

interface IdentifierSequenceForm {
    student: SequenceFormValues;
    staff: SequenceFormValues;
}

function sequenceToForm(sequence: IdSequenceConfig): SequenceFormValues {
    return {
        start_number: sequence.start_number,
        next_number: sequence.next_number,
        increment_by: sequence.increment_by,
        padding: sequence.padding,
    };
}

function formatPreview(values: SequenceFormValues): string {
    const raw = String(values.next_number || 0);

    if (!values.padding || values.padding < 1) {
        return raw;
    }

    return raw.padStart(values.padding, "0");
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

interface SequenceCardProps {
    title: string;
    description: string;
    icon: typeof Hash;
    badgeLabel: string;
    values: SequenceFormValues;
    errors: Partial<Record<keyof SequenceFormValues, string>>;
    disabled: boolean;
    onChange: <K extends keyof SequenceFormValues>(field: K, value: SequenceFormValues[K]) => void;
}

function SequenceCard({ title, description, icon: Icon, badgeLabel, values, errors, disabled, onChange }: SequenceCardProps) {
    const preview = formatPreview(values);

    return (
        <Card className="border-border/60 bg-card/70 shadow-xs backdrop-blur-xs flex flex-col justify-between">
            <CardHeader className="pb-4 border-b border-border/40">
                <div className="flex items-start justify-between gap-4">
                    <div className="flex items-start gap-3">
                        <div className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary mt-0.5">
                            <Icon className="size-5" />
                        </div>
                        <div>
                            <div className="flex items-center gap-2">
                                <CardTitle className="text-base font-semibold">{title}</CardTitle>
                                <Badge variant="outline" className="text-[10px] font-mono border-border/60">
                                    {badgeLabel}
                                </Badge>
                            </div>
                            <CardDescription className="text-xs mt-0.5 max-w-md">{description}</CardDescription>
                        </div>
                    </div>

                    <div className="hidden sm:flex flex-col items-end shrink-0">
                        <span className="text-[10px] uppercase font-mono tracking-wider text-muted-foreground">Next ID Preview</span>
                        <span className="text-base font-mono font-bold tracking-tight text-foreground bg-muted/60 px-2.5 py-0.5 rounded-lg border border-border/50 mt-1">
                            {preview}
                        </span>
                    </div>
                </div>
            </CardHeader>

            <CardContent className="pt-5 space-y-5">
                {/* Mobile Preview */}
                <div className="sm:hidden flex items-center justify-between rounded-lg bg-muted/40 p-2.5 border border-border/50">
                    <span className="text-xs text-muted-foreground font-medium">Next Generated ID</span>
                    <span className="text-sm font-mono font-bold text-foreground">{preview}</span>
                </div>

                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="space-y-1.5">
                        <Label className="text-xs font-semibold text-foreground">Starting Number</Label>
                        <Input
                            type="number"
                            min={1}
                            value={values.start_number}
                            disabled={disabled}
                            onChange={(event) => onChange("start_number", parseNumericValue(event.target.value))}
                            className="font-mono text-sm"
                        />
                        {errors.start_number ? <p className="text-destructive text-xs">{errors.start_number}</p> : null}
                    </div>

                    <div className="space-y-1.5">
                        <Label className="text-xs font-semibold text-foreground">Next Number</Label>
                        <Input
                            type="number"
                            min={1}
                            value={values.next_number}
                            disabled={disabled}
                            onChange={(event) => onChange("next_number", parseNumericValue(event.target.value))}
                            className="font-mono text-sm"
                        />
                        {errors.next_number ? <p className="text-destructive text-xs">{errors.next_number}</p> : null}
                    </div>

                    <div className="space-y-1.5">
                        <Label className="text-xs font-semibold text-foreground">Increment By</Label>
                        <Input
                            type="number"
                            min={1}
                            value={values.increment_by}
                            disabled={disabled}
                            onChange={(event) => onChange("increment_by", parseNumericValue(event.target.value))}
                            className="font-mono text-sm"
                        />
                        {errors.increment_by ? <p className="text-destructive text-xs">{errors.increment_by}</p> : null}
                    </div>

                    <div className="space-y-1.5">
                        <Label className="text-xs font-semibold text-foreground">Padding Digits (Zeros)</Label>
                        <Input
                            type="number"
                            min={1}
                            max={12}
                            value={values.padding ?? ""}
                            disabled={disabled}
                            placeholder="No padding (e.g. 20261001)"
                            onChange={(event) => onChange("padding", parseOptionalNumericValue(event.target.value))}
                            className="font-mono text-sm"
                        />
                        {errors.padding ? <p className="text-destructive text-xs">{errors.padding}</p> : null}
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}

export default function SystemManagementIdentifiersPage({ user, access, id_sequences }: SystemManagementPageProps) {
    const canUpdate = access.sections.identifiers?.can_update ?? false;
    const form = useForm<IdentifierSequenceForm>({
        student: sequenceToForm(id_sequences.student),
        staff: sequenceToForm(id_sequences.staff),
    });

    const sequenceErrors = (key: "student" | "staff"): Partial<Record<keyof SequenceFormValues, string>> => ({
        start_number: form.errors[`${key}.start_number` as keyof typeof form.errors],
        next_number: form.errors[`${key}.next_number` as keyof typeof form.errors],
        increment_by: form.errors[`${key}.increment_by` as keyof typeof form.errors],
        padding: form.errors[`${key}.padding` as keyof typeof form.errors],
    });

    const setSequenceValue = <K extends keyof SequenceFormValues>(sequence: "student" | "staff", field: K, value: SequenceFormValues[K]): void => {
        form.setData(sequence, {
            ...form.data[sequence],
            [field]: value,
        });
    };

    const submit = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();

        form.put(route("administrators.system-management.identifiers.update"), {
            preserveScroll: true,
            onSuccess: () => toast.success("Identifier sequences updated successfully."),
            onError: () => toast.error("Unable to update identifier sequences."),
        });
    };

    return (
        <SystemManagementLayout
            user={user}
            access={access}
            activeSection="identifiers"
            heading="Student & Staff IDs"
            description="Manage sequential numeric ID generation for student enrollments and institution staff."
        >
            <form onSubmit={submit} className="space-y-6">
                <Alert className="border-border/60 bg-muted/30">
                    <Info className="h-4 w-4 text-primary" />
                    <AlertTitle className="text-sm font-semibold">Strict numeric sequences</AlertTitle>
                    <AlertDescription className="text-xs text-muted-foreground">
                        Student IDs are numeric integers for automated barcode, RFID, and database compatibility. Senior High School records use standard national LRN format.
                    </AlertDescription>
                </Alert>

                <div className="grid gap-6 xl:grid-cols-2">
                    <SequenceCard
                        title="Student ID Sequence"
                        description="Used when registering college, vocational, and technical student admissions."
                        icon={Hash}
                        badgeLabel="Students"
                        values={form.data.student}
                        errors={sequenceErrors("student")}
                        disabled={!canUpdate || form.processing}
                        onChange={(field, value) => setSequenceValue("student", field, value)}
                    />

                    <SequenceCard
                        title="Shared Staff Sequence"
                        description="Allocated for faculty members and administrative personnel records."
                        icon={Users}
                        badgeLabel="Employees & Faculty"
                        values={form.data.staff}
                        errors={sequenceErrors("staff")}
                        disabled={!canUpdate || form.processing}
                        onChange={(field, value) => setSequenceValue("staff", field, value)}
                    />
                </div>

                <div className="flex flex-col gap-3 rounded-xl border border-border/60 bg-card/60 p-4 sm:flex-row sm:items-center sm:justify-between">
                    <p className="text-xs text-muted-foreground">
                        Sequences increment atomically upon verified record insertion. Previewing IDs does not consume numbers.
                    </p>
                    <Button type="submit" disabled={!canUpdate || form.processing} className="h-9 gap-2 shrink-0">
                        {form.processing ? <Loader2 className="h-4 w-4 animate-spin" /> : <Save className="h-4 w-4" />}
                        <span>Save Sequence Settings</span>
                    </Button>
                </div>
            </form>
        </SystemManagementLayout>
    );
}
