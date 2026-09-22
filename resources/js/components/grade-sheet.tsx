import { useMemo, useState } from "react";

import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Switch } from "@/components/ui/switch";
import type { GradingComponentPayload, GradingConfigPayload } from "@/pages/administrators/system-management/types";
import { router } from "@inertiajs/react";
import { Calculator, Save, Send } from "lucide-react";
import { toast } from "sonner";

type StudentRow = {
    id: number | string;
    name: string;
    studentId: string;
    grades: {
        prelim?: number | null;
        midterm?: number | null;
        final?: number | null;
        average?: number | null;
        components?: Record<string, number | null>;
    };
};

type GradeRow = {
    enrollmentId: number | string;
    name: string;
    studentId: string;
    components: Record<string, string>;
    average: string;
};

interface GradeSheetProps {
    classId: number | string;
    students: StudentRow[];
    autoAverageDefault?: boolean;
    gradingPolicy?: GradingConfigPayload;
}

const fallbackComponents: GradingComponentPayload[] = [
    { id: "prelim", key: "prelim", label: "Prelim", weight: 30, required: true, sort_order: 0 },
    { id: "midterm", key: "midterm", label: "Midterm", weight: 30, required: true, sort_order: 1 },
    { id: "final", key: "final", label: "Final", weight: 40, required: true, sort_order: 2 },
];

export function GradeSheet({ classId, students, autoAverageDefault = true, gradingPolicy }: GradeSheetProps) {
    const components = useMemo(
        () =>
            gradingPolicy?.components?.length
                ? [...gradingPolicy.components].sort((left, right) => left.sort_order - right.sort_order)
                : fallbackComponents,
        [gradingPolicy?.components],
    );
    const [autoAverageEnabled, setAutoAverageEnabled] = useState(autoAverageDefault);
    const [isSavingGrades, setIsSavingGrades] = useState(false);

    const initialRows = useMemo<GradeRow[]>(
        () =>
            students.map((student) => {
                const legacy: Record<string, number | null | undefined> = {
                    prelim: student.grades.prelim,
                    midterm: student.grades.midterm,
                    final: student.grades.final,
                };
                const source = student.grades.components ?? legacy;
                const gradeComponents = Object.fromEntries(
                    components.map((component) => [component.key, source[component.key] == null ? "" : String(source[component.key])]),
                );

                return {
                    enrollmentId: student.id,
                    name: student.name,
                    studentId: student.studentId,
                    components: gradeComponents,
                    average: student.grades.average == null ? "" : String(student.grades.average),
                };
            }),
        [components, students],
    );
    const [gradeRows, setGradeRows] = useState(initialRows);

    const numericRange =
        gradingPolicy?.input_type === "numeric" ? { min: gradingPolicy.numeric_min, max: gradingPolicy.numeric_max } : { min: 0, max: 100 };
    const label = gradingPolicy?.name ?? "Default grading policy";

    const calculateAverage = (componentsForStudent: Record<string, string>): string => {
        let total = 0;
        for (const component of components) {
            const raw = componentsForStudent[component.key];
            if (raw === "" && component.required) return "";
            if (raw === "") continue;
            const score = Number(raw);
            if (!Number.isFinite(score)) return "";
            total += score * (component.weight / 100);
        }

        return total.toFixed(gradingPolicy?.decimal_places ?? 2);
    };

    const updateComponent = (enrollmentId: number | string, key: string, value: string) => {
        setGradeRows((previous) =>
            previous.map((row) => {
                if (row.enrollmentId !== enrollmentId) return row;
                const componentValues = { ...row.components, [key]: value };
                return { ...row, components: componentValues, average: autoAverageEnabled ? calculateAverage(componentValues) : row.average };
            }),
        );
    };

    const saveGrades = () => {
        setIsSavingGrades(true);
        router.put(
            `/faculty/classes/${classId}/grades`,
            {
                grades: gradeRows.map((row) => ({
                    enrollment_id: row.enrollmentId,
                    components: Object.fromEntries(Object.entries(row.components).map(([key, value]) => [key, value === "" ? null : Number(value)])),
                })),
            },
            {
                preserveScroll: true,
                onSuccess: () => toast.success("Grades saved"),
                onError: () => toast.error("Unable to save grades"),
                onFinish: () => setIsSavingGrades(false),
            },
        );
    };

    const submitGrades = () => {
        router.post(
            `/faculty/classes/${classId}/grades/submit`,
            {},
            { preserveScroll: true, onSuccess: () => toast.success("Grades submitted"), onError: () => toast.error("Unable to submit grades") },
        );
    };

    return (
        <Card>
            <CardHeader className="bg-muted/20 gap-4 border-b sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <CardTitle className="flex items-center gap-2">
                        <Calculator className="size-5" /> Gradebook
                    </CardTitle>
                    <CardDescription>{label}. Weighted from the active school policy, not a fixed 30 / 30 / 40 formula.</CardDescription>
                </div>
                <div className="flex flex-wrap items-center gap-3">
                    <div className="flex items-center gap-2">
                        <Label htmlFor="auto-average" className="text-sm">
                            Auto-calculate
                        </Label>
                        <Switch id="auto-average" checked={autoAverageEnabled} onCheckedChange={setAutoAverageEnabled} />
                    </div>
                    <Button variant="outline" onClick={submitGrades} className="gap-2">
                        <Send className="size-4" /> Submit
                    </Button>
                    <Button onClick={saveGrades} disabled={isSavingGrades} className="gap-2">
                        <Save className="size-4" /> {isSavingGrades ? "Saving" : "Save grades"}
                    </Button>
                </div>
            </CardHeader>
            <CardContent className="pt-6">
                <div className="mb-4 flex flex-wrap gap-2">
                    {components.map((component) => (
                        <Badge key={component.key} variant="outline">
                            {component.label} {component.weight}%{component.required ? "" : " · optional"}
                        </Badge>
                    ))}
                    <Badge variant="secondary">
                        Range {numericRange.min}–{numericRange.max}
                    </Badge>
                </div>
                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full min-w-[760px] text-sm">
                        <thead className="bg-muted/50">
                            <tr>
                                <th className="px-4 py-3 text-left font-medium">Student</th>
                                {components.map((component) => (
                                    <th key={component.key} className="px-3 py-3 text-center font-medium">
                                        {component.label}
                                        <span className="text-muted-foreground ml-1 text-xs">{component.weight}%</span>
                                    </th>
                                ))}
                                <th className="px-4 py-3 text-center font-medium">Calculated</th>
                            </tr>
                        </thead>
                        <tbody>
                            {gradeRows.map((row) => (
                                <tr key={row.enrollmentId} className="border-t">
                                    <td className="px-4 py-3">
                                        <div className="font-medium">{row.name}</div>
                                        <div className="text-muted-foreground text-xs">{row.studentId}</div>
                                    </td>
                                    {components.map((component) => (
                                        <td key={component.key} className="px-3 py-2">
                                            <Input
                                                type="number"
                                                min={numericRange.min}
                                                max={numericRange.max}
                                                step="any"
                                                value={row.components[component.key] ?? ""}
                                                onChange={(event) => updateComponent(row.enrollmentId, component.key, event.target.value)}
                                                aria-label={`${component.label} grade for ${row.name}`}
                                            />
                                        </td>
                                    ))}
                                    <td className="px-4 py-3 text-center">
                                        <span className="font-mono font-semibold">{row.average || "—"}</span>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>
    );
}
