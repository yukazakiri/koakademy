import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { router } from "@inertiajs/react";
import { Check, Loader2, Pencil, X } from "lucide-react";
import { useCallback, useState } from "react";
import { toast } from "sonner";

import type { StudentDetail } from "../types";

declare const route: (name: string, params?: Record<string, unknown> | string | number | Array<string | number>) => string;

const YEAR_LEVELS = [
    { value: "1", label: "1st Year" },
    { value: "2", label: "2nd Year" },
    { value: "3", label: "3rd Year" },
    { value: "4", label: "4th Year" },
];

const STATUSES = [
    { value: "applicant", label: "Applicant" },
    { value: "enrolled", label: "Enrolled" },
    { value: "on_leave", label: "On Leave" },
    { value: "withdrawn", label: "Withdrawn" },
    { value: "dropped", label: "Dropped Out" },
    { value: "graduated", label: "Graduated" },
    { value: "transferred", label: "Transferred" },
];

function formatYearLevel(value: number | null): string {
    return YEAR_LEVELS.find((level) => level.value === String(value))?.label ?? "—";
}

type PendingField = "academic_year" | "status";

type InlineStudent = Pick<StudentDetail, "id" | "academic_year" | "academic_year_value" | "status">;

interface StudentInlineEditProps {
    student: InlineStudent;
}

/**
 * Inline editors for the two fields that change most often during registration:
 * year level and status.
 *
 * Each commit hits the lightweight quick-update endpoint and repaints
 * optimistically, then reconciles with a partial reload of only the `student`
 * prop so the authoritative values land without rebuilding the whole page.
 */
export function StudentInlineEdit({ student }: StudentInlineEditProps) {
    const [editing, setEditing] = useState<PendingField | null>(null);
    const [saving, setSaving] = useState<PendingField | null>(null);
    const [draft, setDraft] = useState("");

    const commit = useCallback(
        (field: PendingField, value: string) => {
            if (saving !== null || value === "") return;

            const current = field === "academic_year" ? String(student.academic_year_value ?? "") : String(student.status ?? "");
            if (value === current) {
                setEditing(null);
                return;
            }

            setEditing(null);
            setSaving(field);

            const optimistic = (props: { student: StudentDetail }): { student: StudentDetail } => ({
                ...props,
                student:
                    field === "academic_year"
                        ? {
                              ...props.student,
                              academic_year_value: Number(value),
                              academic_year: formatYearLevel(Number(value)),
                          }
                        : { ...props.student, status: value },
            });

            router.optimistic(optimistic).patch(
                route("administrators.students.quick-update", student.id),
                { [field]: value },
                {
                    preserveScroll: true,
                    preserveState: true,
                    async: true,
                    onSuccess: async () => {
                        // Reconcile with the server's authoritative values.
                        // reload() already preserves scroll and state.
                        await router.reload({ only: ["student"] });
                        toast.success(`${field === "academic_year" ? "Year level" : "Status"} updated`);
                    },
                    onError: (errors) => {
                        const message = Object.values(errors)[0];
                        toast.error(typeof message === "string" ? message : "Failed to update student");
                    },
                    onFinish: () => {
                        setSaving(null);
                    },
                },
            );
        },
        [saving, student],
    );

    return (
        <>
            <InlineField
                label="Year Level"
                displayValue={formatYearLevel(student.academic_year_value)}
                isEditing={editing === "academic_year"}
                isSaving={saving === "academic_year"}
                options={YEAR_LEVELS}
                draft={draft}
                setDraft={setDraft}
                onStart={() => {
                    setDraft(String(student.academic_year_value ?? ""));
                    setEditing("academic_year");
                }}
                onCancel={() => setEditing(null)}
                onCommit={(value) => commit("academic_year", value)}
            />

            <InlineField
                label="Status"
                displayValue={STATUSES.find((s) => s.value === student.status)?.label ?? student.status ?? "—"}
                isEditing={editing === "status"}
                isSaving={saving === "status"}
                options={STATUSES}
                draft={draft}
                setDraft={setDraft}
                onStart={() => {
                    setDraft(String(student.status ?? ""));
                    setEditing("status");
                }}
                onCancel={() => setEditing(null)}
                onCommit={(value) => commit("status", value)}
            />
        </>
    );
}

interface InlineFieldProps {
    label: string;
    displayValue: string;
    isEditing: boolean;
    isSaving: boolean;
    options: Array<{ value: string; label: string }>;
    draft: string;
    setDraft: (value: string) => void;
    onStart: () => void;
    onCancel: () => void;
    onCommit: (value: string) => void;
}

function InlineField({ label, displayValue, isEditing, isSaving, options, draft, setDraft, onStart, onCancel, onCommit }: InlineFieldProps) {
    if (isEditing) {
        return (
            <div className="flex flex-col gap-1">
                <span className="text-muted-foreground text-xs font-medium tracking-wider uppercase">{label}</span>
                <div className="flex items-center gap-1">
                    <Select value={draft} onValueChange={setDraft}>
                        <SelectTrigger size="sm" className="h-8 flex-1" autoFocus>
                            <SelectValue placeholder={`Select ${label.toLowerCase()}`} />
                        </SelectTrigger>
                        <SelectContent>
                            {options.map((option) => (
                                <SelectItem key={option.value} value={option.value}>
                                    {option.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <button
                        type="button"
                        aria-label={`Save ${label}`}
                        onClick={() => onCommit(draft)}
                        className="text-primary hover:bg-primary/10 inline-flex h-8 w-8 items-center justify-center rounded-md transition-colors"
                    >
                        <Check className="h-4 w-4" />
                    </button>
                    <button
                        type="button"
                        aria-label={`Cancel ${label} edit`}
                        onClick={onCancel}
                        className="text-muted-foreground hover:bg-muted inline-flex h-8 w-8 items-center justify-center rounded-md transition-colors"
                    >
                        <X className="h-4 w-4" />
                    </button>
                </div>
            </div>
        );
    }

    return (
        <div className="flex flex-col gap-1">
            <span className="text-muted-foreground text-xs font-medium tracking-wider uppercase">{label}</span>
            <div className="group flex items-center gap-1.5">
                <span className="text-sm font-semibold">{displayValue}</span>
                {isSaving ? (
                    <Loader2 className="text-muted-foreground h-3.5 w-3.5 animate-spin" aria-label="Saving" />
                ) : (
                    <button
                        type="button"
                        aria-label={`Edit ${label}`}
                        onClick={onStart}
                        className="text-muted-foreground hover:text-foreground inline-flex h-5 w-5 items-center justify-center rounded opacity-0 transition-opacity group-hover:opacity-100 focus-visible:opacity-100"
                    >
                        <Pencil className="h-3 w-3" />
                    </button>
                )}
            </div>
        </div>
    );
}
