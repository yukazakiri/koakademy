import { Badge } from "@/components/reui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Switch } from "@/components/ui/switch";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { update } from "@/routes/administrators/system-management/identifiers";
import type { StudentCategory } from "@/types/student-configuration";
import { useForm } from "@inertiajs/react";
import { Loader2, Save } from "lucide-react";
import type { FormEvent } from "react";
import { toast } from "sonner";
import SystemManagementLayout from "./layout";
import type { IdSequenceConfig, SystemManagementPageProps } from "./types";

type SequenceForm = Omit<IdSequenceConfig, "preview" | "type_previews" | "key" | "label"> & {
    expected_next_number: number;
    expected_type_next_numbers?: Record<string, number>;
};
interface IdentifierForm {
    student: SequenceForm;
    staff: SequenceForm;
}

function toForm(sequence: IdSequenceConfig): SequenceForm {
    return {
        start_number: sequence.start_number,
        next_number: sequence.next_number,
        increment_by: sequence.increment_by,
        padding: sequence.padding,
        display_label: sequence.display_label,
        assignment_mode: sequence.assignment_mode,
        character_set: sequence.character_set,
        counter_scope: sequence.counter_scope,
        legacy_format: sequence.legacy_format,
        prefix_mode: sequence.prefix_mode,
        prefix_value: sequence.prefix_value,
        enforce_prefix: sequence.enforce_prefix,
        enforce_length: sequence.enforce_length,
        exact_length: sequence.exact_length,
        min_length: sequence.min_length,
        max_length: sequence.max_length,
        type_prefixes: { college: "", tesda: "", dhrt: "", shs: "", ...sequence.type_prefixes },
        type_next_numbers: sequence.type_next_numbers,
        expected_next_number: sequence.next_number,
        expected_type_next_numbers: sequence.type_next_numbers,
    };
}

function preview(sequence: SequenceForm, type: StudentCategory, year: string): string {
    const counter = sequence.counter_scope === "by_type" ? (sequence.type_next_numbers?.[type] ?? sequence.start_number) : sequence.next_number;
    const number = String(counter).padStart(sequence.padding ?? 0, "0");
    const prefix =
        sequence.prefix_mode === "static"
            ? (sequence.prefix_value ?? "")
            : sequence.prefix_mode === "year"
              ? year
              : sequence.prefix_mode === "by_type"
                ? (sequence.type_prefixes?.[type] ?? "")
                : "";
    if (!sequence.legacy_format) return prefix + number;
    const combined = prefix && !number.startsWith(prefix) ? prefix + number : number;
    return String(Number(/^\d+$/.test(combined) && Number(combined) <= 2147483647 ? combined : number));
}

function NumberField({
    id,
    label,
    value,
    onChange,
    disabled,
    optional = false,
    min = 1,
    max = 999999999999,
}: {
    id: string;
    label: string;
    value: number | null;
    onChange: (value: number | null) => void;
    disabled: boolean;
    optional?: boolean;
    min?: number;
    max?: number;
}) {
    return (
        <div className="flex flex-col gap-2">
            <Label htmlFor={id}>{label}</Label>
            <Input
                id={id}
                type="number"
                min={min}
                max={max}
                value={value ?? ""}
                disabled={disabled}
                className="font-mono tabular-nums"
                onChange={(event) => onChange(optional && event.target.value === "" ? null : Number(event.target.value))}
            />
        </div>
    );
}

export default function IdentifiersPage({
    user,
    access,
    id_sequences,
    student_configuration,
    identifier_year,
}: SystemManagementPageProps & { identifier_year: string }) {
    const form = useForm<IdentifierForm>({ student: toForm(id_sequences.student), staff: toForm(id_sequences.staff) });
    const disabled = !access.sections.identifiers?.can_update || form.processing;
    const student = form.data.student;
    const setStudent = <K extends keyof SequenceForm>(key: K, value: SequenceForm[K]) => form.setData("student", { ...student, [key]: value });
    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.put(update.url(), {
            preserveScroll: true,
            onSuccess: (page) => {
                const saved = page.props.id_sequences as typeof id_sequences;
                form.setData({ student: toForm(saved.student), staff: toForm(saved.staff) });
                toast.success("Identifier settings saved. Existing numbers are unchanged.");
            },
            onError: () => toast.error("Review the identifier settings errors."),
        });
    };

    return (
        <SystemManagementLayout
            user={user}
            access={access}
            activeSection="identifiers"
            heading="Student & Staff Identifiers"
            description="School-specific numbering and validation. Previews do not reserve a number; automatic numbers are allocated on save."
        >
            <form onSubmit={submit} className="flex flex-col gap-6">
                {id_sequences.student.preview_error && (
                    <p className="text-destructive text-sm" role="alert">
                        {id_sequences.student.preview_error}
                    </p>
                )}
                {Object.keys(form.errors).length > 0 && (
                    <Card>
                        <CardContent role="alert" className="flex flex-col gap-2">
                            {Object.entries(form.errors).map(([key, error]) => (
                                <p key={key} className="text-destructive text-sm">
                                    {key}: {error}
                                </p>
                            ))}
                        </CardContent>
                    </Card>
                )}
                <Tabs defaultValue="students" className="flex flex-col gap-6">
                    <TabsList>
                        <TabsTrigger value="students">Student identifiers</TabsTrigger>
                        <TabsTrigger value="staff">Staff & faculty</TabsTrigger>
                    </TabsList>
                    <TabsContent value="students" className="flex flex-col gap-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>Next number previews</CardTitle>
                                <CardDescription>
                                    Illustrative formatting for the enabled categories. Occupied numbers are skipped when issuing.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="flex flex-wrap gap-4">
                                {student_configuration.enabled_types.map((type) => {
                                    const external = student_configuration.external_identifier;
                                    const usesExternal = external.enabled && external.use_as_student_id && external.student_types.includes(type);
                                    return (
                                        <div key={type} className="flex items-center gap-2">
                                            <span className="text-muted-foreground text-sm">{student_configuration.type_labels[type]}</span>
                                            <Badge variant="info-light" className="font-mono tabular-nums">
                                                {usesExternal ? external.label : preview(student, type, identifier_year)}
                                            </Badge>
                                        </div>
                                    );
                                })}
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader>
                                <CardTitle>Assignment policy</CardTitle>
                                <CardDescription>
                                    Student numbers are text, not internal record keys. Letters and leading zeros are preserved.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-4 md:grid-cols-2">
                                <div className="flex flex-col gap-2">
                                    <Label htmlFor="id-label">Display label</Label>
                                    <Input
                                        id="id-label"
                                        value={student.display_label}
                                        disabled={disabled}
                                        maxLength={80}
                                        onChange={(event) => setStudent("display_label", event.target.value)}
                                    />
                                </div>
                                <div className="flex flex-col gap-2">
                                    <Label htmlFor="assignment-mode">Assignment</Label>
                                    <Select
                                        value={student.assignment_mode}
                                        disabled={disabled}
                                        onValueChange={(value) => setStudent("assignment_mode", value as SequenceForm["assignment_mode"])}
                                    >
                                        <SelectTrigger id="assignment-mode">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="automatic">Automatic only</SelectItem>
                                            <SelectItem value="manual">Manual only</SelectItem>
                                            <SelectItem value="hybrid">Automatic with manual override</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="flex flex-col gap-2">
                                    <Label htmlFor="character-set">Allowed characters</Label>
                                    <Select
                                        value={student.character_set}
                                        disabled={disabled}
                                        onValueChange={(value) => setStudent("character_set", value as SequenceForm["character_set"])}
                                    >
                                        <SelectTrigger id="character-set">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="numeric">Digits only</SelectItem>
                                            <SelectItem value="alphanumeric">Letters, digits, hyphens, underscores</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="flex flex-col gap-2">
                                    <Label htmlFor="counter-scope">Counter sharing</Label>
                                    <Select
                                        value={student.counter_scope}
                                        disabled={disabled}
                                        onValueChange={(value) => setStudent("counter_scope", value as SequenceForm["counter_scope"])}
                                    >
                                        <SelectTrigger id="counter-scope">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="shared">Shared across categories</SelectItem>
                                            <SelectItem value="by_type">Separate counter per category</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <Label className="flex items-center gap-3 md:col-span-2">
                                    <Switch
                                        checked={student.legacy_format}
                                        disabled={disabled}
                                        onCheckedChange={(value) => setStudent("legacy_format", value)}
                                    />
                                    Preserve legacy numeric formatting
                                </Label>
                                <p className="text-muted-foreground text-xs md:col-span-2">
                                    Legacy formatting removes leading zeros and avoids repeating a numeric prefix. Disable it for formats such as
                                    STU-000001. This affects future numbers only.
                                </p>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader>
                                <CardTitle>Sequence & prefix</CardTitle>
                                <CardDescription>
                                    Padding counts counter digits; validation length counts the complete identifier. Separate counters require
                                    distinct category prefixes.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-4 md:grid-cols-2">
                                <NumberField
                                    id="student-start"
                                    label="Starting counter"
                                    value={student.start_number}
                                    onChange={(value) => setStudent("start_number", value ?? 1)}
                                    disabled={disabled}
                                />
                                {student.counter_scope === "shared" && (
                                    <NumberField
                                        id="student-next"
                                        label="Next counter"
                                        value={student.next_number}
                                        onChange={(value) => setStudent("next_number", value ?? 1)}
                                        disabled={disabled}
                                    />
                                )}
                                <NumberField
                                    id="student-step"
                                    label="Increment"
                                    value={student.increment_by}
                                    onChange={(value) => setStudent("increment_by", value ?? 1)}
                                    max={1000}
                                    disabled={disabled}
                                />
                                <NumberField
                                    id="student-padding"
                                    label="Counter padding"
                                    value={student.padding}
                                    onChange={(value) => setStudent("padding", value)}
                                    optional
                                    min={0}
                                    max={12}
                                    disabled={disabled}
                                />
                                <div className="flex flex-col gap-2">
                                    <Label htmlFor="prefix-mode">Prefix mode</Label>
                                    <Select
                                        value={student.prefix_mode}
                                        disabled={disabled}
                                        onValueChange={(value) => setStudent("prefix_mode", value as SequenceForm["prefix_mode"])}
                                    >
                                        <SelectTrigger id="prefix-mode">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="none">No prefix</SelectItem>
                                            <SelectItem value="static">Custom prefix</SelectItem>
                                            <SelectItem value="year">Calendar year (application timezone)</SelectItem>
                                            <SelectItem value="by_type">By category</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                {student.prefix_mode === "static" && (
                                    <div className="flex flex-col gap-2">
                                        <Label htmlFor="prefix">Custom prefix</Label>
                                        <Input
                                            id="prefix"
                                            value={student.prefix_value ?? ""}
                                            disabled={disabled}
                                            maxLength={10}
                                            placeholder="STU-"
                                            onChange={(event) => setStudent("prefix_value", event.target.value)}
                                        />
                                    </div>
                                )}
                                {student.prefix_mode === "by_type" &&
                                    student_configuration.enabled_types.map((type) => (
                                        <div key={type} className="flex flex-col gap-2">
                                            <Label htmlFor={`prefix-${type}`}>{student_configuration.type_labels[type]} prefix</Label>
                                            <Input
                                                id={`prefix-${type}`}
                                                value={student.type_prefixes?.[type] ?? ""}
                                                disabled={disabled}
                                                maxLength={10}
                                                onChange={(event) =>
                                                    setStudent("type_prefixes", {
                                                        college: "",
                                                        shs: "",
                                                        tesda: "",
                                                        dhrt: "",
                                                        ...student.type_prefixes,
                                                        [type]: event.target.value,
                                                    })
                                                }
                                            />
                                        </div>
                                    ))}
                                {student.counter_scope === "by_type" &&
                                    student_configuration.enabled_types.map((type) => (
                                        <NumberField
                                            key={type}
                                            id={`next-${type}`}
                                            label={`${student_configuration.type_labels[type]} next counter`}
                                            value={student.type_next_numbers?.[type] ?? student.start_number}
                                            disabled={disabled}
                                            onChange={(value) =>
                                                setStudent("type_next_numbers", { ...student.type_next_numbers, [type]: value ?? 1 })
                                            }
                                        />
                                    ))}
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader>
                                <CardTitle>Validation</CardTitle>
                                <CardDescription>Unchanged legacy numbers remain editable even when the policy changes.</CardDescription>
                            </CardHeader>
                            <CardContent className="flex flex-col gap-4">
                                <Label className="flex items-center gap-3">
                                    <Switch
                                        checked={student.enforce_prefix ?? false}
                                        disabled={disabled}
                                        onCheckedChange={(value) => setStudent("enforce_prefix", value)}
                                    />
                                    Require the configured prefix on manually entered numbers
                                </Label>
                                <Label className="flex items-center gap-3">
                                    <Switch
                                        checked={student.enforce_length ?? false}
                                        disabled={disabled}
                                        onCheckedChange={(value) => setStudent("enforce_length", value)}
                                    />
                                    Require an exact character count
                                </Label>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    {student.enforce_length ? (
                                        <NumberField
                                            id="exact-length"
                                            label="Exact characters (including prefix)"
                                            value={student.exact_length ?? 6}
                                            max={64}
                                            disabled={disabled}
                                            onChange={(value) => setStudent("exact_length", value)}
                                        />
                                    ) : (
                                        <>
                                            <NumberField
                                                id="minimum-length"
                                                label="Minimum characters"
                                                value={student.min_length ?? 1}
                                                max={64}
                                                disabled={disabled}
                                                onChange={(value) => setStudent("min_length", value)}
                                            />
                                            <NumberField
                                                id="maximum-length"
                                                label="Maximum characters"
                                                value={student.max_length ?? 64}
                                                max={64}
                                                disabled={disabled}
                                                onChange={(value) => setStudent("max_length", value)}
                                            />
                                        </>
                                    )}
                                </div>
                            </CardContent>
                        </Card>
                    </TabsContent>
                    <TabsContent value="staff">
                        <Card>
                            <CardHeader>
                                <CardTitle>Staff & faculty sequence</CardTitle>
                                <CardDescription>
                                    Next preview: {String(form.data.staff.next_number).padStart(form.data.staff.padding ?? 0, "0")}. This sequence is
                                    independent of student numbers and shared across the installation.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-4 md:grid-cols-2">
                                {(["start_number", "next_number", "increment_by", "padding"] as const).map((key) => (
                                    <NumberField
                                        key={key}
                                        id={`staff-${key}`}
                                        label={
                                            {
                                                start_number: "Starting counter",
                                                next_number: "Next counter",
                                                increment_by: "Increment",
                                                padding: "Padding",
                                            }[key]
                                        }
                                        value={form.data.staff[key]}
                                        optional={key === "padding"}
                                        min={key === "padding" ? 0 : 1}
                                        max={key === "padding" ? 12 : key === "increment_by" ? 1000 : 2147483647}
                                        disabled={disabled}
                                        onChange={(value) => form.setData("staff", { ...form.data.staff, [key]: value })}
                                    />
                                ))}
                            </CardContent>
                        </Card>
                    </TabsContent>
                </Tabs>
                <p className="text-muted-foreground text-xs">
                    Saving never renumbers students. A stale counter edit is rejected rather than overwriting numbers issued by another operator.
                </p>
                <Button type="submit" disabled={disabled}>
                    {form.processing ? <Loader2 className="size-4 animate-spin" /> : <Save className="size-4" />}Save identifier settings
                </Button>
            </form>
        </SystemManagementLayout>
    );
}
