import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Separator } from "@/components/ui/separator";
import { useForm } from "@inertiajs/react";
import { Hash, Info, Save, Users } from "lucide-react";
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
    values: SequenceFormValues;
    errors: Partial<Record<keyof SequenceFormValues, string>>;
    disabled: boolean;
    onChange: <K extends keyof SequenceFormValues>(field: K, value: SequenceFormValues[K]) => void;
}

function SequenceCard({ title, description, icon: Icon, values, errors, disabled, onChange }: SequenceCardProps) {
    return (
        <Card>
            <CardHeader>
                <div className="flex items-start justify-between gap-4">
                    <div className="space-y-1">
                        <CardTitle className="flex items-center gap-2">
                            <Icon className="text-primary h-5 w-5" />
                            {title}
                        </CardTitle>
                        <CardDescription>{description}</CardDescription>
                    </div>
                    <Badge variant="outline" className="font-mono">
                        Preview {formatPreview(values)}
                    </Badge>
                </div>
            </CardHeader>
            <CardContent className="grid gap-5 md:grid-cols-2">
                <div className="space-y-2">
                    <Label>Starting number</Label>
                    <Input
                        type="number"
                        min={1}
                        value={values.start_number}
                        disabled={disabled}
                        onChange={(event) => onChange("start_number", parseNumericValue(event.target.value))}
                    />
                    {errors.start_number ? <p className="text-destructive text-sm">{errors.start_number}</p> : null}
                </div>

                <div className="space-y-2">
                    <Label>Next number</Label>
                    <Input
                        type="number"
                        min={1}
                        value={values.next_number}
                        disabled={disabled}
                        onChange={(event) => onChange("next_number", parseNumericValue(event.target.value))}
                    />
                    {errors.next_number ? <p className="text-destructive text-sm">{errors.next_number}</p> : null}
                </div>

                <div className="space-y-2">
                    <Label>Increment by</Label>
                    <Input
                        type="number"
                        min={1}
                        value={values.increment_by}
                        disabled={disabled}
                        onChange={(event) => onChange("increment_by", parseNumericValue(event.target.value))}
                    />
                    {errors.increment_by ? <p className="text-destructive text-sm">{errors.increment_by}</p> : null}
                </div>

                <div className="space-y-2">
                    <Label>Padding digits</Label>
                    <Input
                        type="number"
                        min={1}
                        max={12}
                        value={values.padding ?? ""}
                        disabled={disabled}
                        placeholder="No padding"
                        onChange={(event) => onChange("padding", parseOptionalNumericValue(event.target.value))}
                    />
                    {errors.padding ? <p className="text-destructive text-sm">{errors.padding}</p> : null}
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
            description="Configure numeric student IDs separately from the shared faculty and employee staff sequence."
        >
            <form onSubmit={submit} className="space-y-6">
                <Alert>
                    <Info className="h-4 w-4" />
                    <AlertTitle>Numeric identifiers only</AlertTitle>
                    <AlertDescription>
                        Student IDs remain numeric for compatibility. Faculty IDs and future employee IDs share the staff sequence starting at 800000.
                    </AlertDescription>
                </Alert>

                <div className="grid gap-6 xl:grid-cols-2">
                    <SequenceCard
                        title="Student IDs"
                        description="Used when creating college, TESDA, and DHRT student records. Senior High records continue using LRN."
                        icon={Hash}
                        values={form.data.student}
                        errors={sequenceErrors("student")}
                        disabled={!canUpdate || form.processing}
                        onChange={(field, value) => setSequenceValue("student", field, value)}
                    />

                    <SequenceCard
                        title="Shared staff IDs"
                        description="Used by faculty now and reserved for employee IDs so both record types share one numeric counter."
                        icon={Users}
                        values={form.data.staff}
                        errors={sequenceErrors("staff")}
                        disabled={!canUpdate || form.processing}
                        onChange={(field, value) => setSequenceValue("staff", field, value)}
                    />
                </div>

                <Separator />

                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <p className="text-muted-foreground text-sm">
                        The next number is consumed only when a record is created, not when a create form previews an ID.
                    </p>
                    <Button type="submit" disabled={!canUpdate || form.processing} className="gap-2">
                        <Save className="h-4 w-4" />
                        {form.processing ? "Saving..." : "Save ID sequences"}
                    </Button>
                </div>
            </form>
        </SystemManagementLayout>
    );
}
