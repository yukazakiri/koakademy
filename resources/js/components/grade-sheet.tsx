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
    studentId?: string;
    student_id?: string;
    grades: {
        prelim?: number | null;
        midterm?: number | null;
        final?: number | null;
        average?: number | null;
        symbol?: string | null;
        components?: Record<string, number | null>;
    };
};

type GradeRow = {
    enrollmentId: number | string;
    name: string;
    studentId: string;
    components: Record<string, string>;
    average: string;
    symbol: string;
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
    const isSymbolic = gradingPolicy?.input_type === "symbol";
    const availableSymbols = useMemo(
        () => gradingPolicy?.bands?.map((b) => b.symbol).filter((s): s is string => Boolean(s)) ?? [],
        [gradingPolicy?.bands],
    );

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
                    studentId: student.studentId ?? student.student_id ?? "",
                    components: gradeComponents,
                    average: student.grades.average == null ? "" : String(student.grades.average),
                    symbol: student.grades.symbol ?? "",
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

    const updateSymbol = (enrollmentId: number | string, symbol: string) => {
        setGradeRows((previous) => previous.map((row) => (row.enrollmentId === enrollmentId ? { ...row, symbol } : row)));
    };

    const saveGrades = () => {
        setIsSavingGrades(true);
        router.put(
            `/faculty/classes/${classId}/grades`,
            {
                grades: gradeRows.map((row) => ({
                    enrollment_id: row.enrollmentId,
                    symbol: row.symbol || null,
                    components: Object.fromEntries(Object.entries(row.components).map(([key, value]) => [key, value === "" ? null : Number(value)])),
                    average: row.average === "" ? null : Number(row.average),
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
                    {!isSymbolic && (
                        <div className="flex items-center gap-2">
                            <Label htmlFor="auto-average" className="text-sm">
                                Auto-calculate
                            </Label>
                            <Switch id="auto-average" checked={autoAverageEnabled} onCheckedChange={setAutoAverageEnabled} />
                        </div>
                    )}
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
                    {isSymbolic ? (
                        <>
                            <Badge variant="outline">Symbolic Grading</Badge>
                            {availableSymbols.length > 0 && <Badge variant="secondary">Symbols: {availableSymbols.join(", ")}</Badge>}
                        </>
                    ) : (
                        <>
                            {components.map((component) => (
                                <Badge key={component.key} variant="outline">
                                    {component.label} {component.weight}%{component.required ? "" : " · optional"}
                                </Badge>
                            ))}
                            <Badge variant="secondary">
                                Range {numericRange.min}–{numericRange.max}
                            </Badge>
                        </>
                    )}
                </div>
                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full min-w-[760px] text-sm">
                        <thead className="bg-muted/50">
                            <tr>
                                <th className="px-4 py-3 text-left font-medium">Student</th>
                                {isSymbolic ? (
                                    <th className="px-4 py-3 text-center font-medium">Grade Symbol</th>
                                ) : (
                                    <>
                                        {components.map((component) => (
                                            <th key={component.key} className="px-3 py-3 text-center font-medium">
                                                {component.label}
                                                <span className="text-muted-foreground ml-1 text-xs">{component.weight}%</span>
                                            </th>
                                        ))}
                                        <th className="px-4 py-3 text-center font-medium">Calculated</th>
                                    </>
                                )}
                            </tr>
                        </thead>
                        <tbody>
                            {gradeRows.map((row) => (
                                <tr key={row.enrollmentId} className="border-t">
                                    <td className="px-4 py-3">
                                        <div className="font-medium">{row.name}</div>
                                        <div className="text-muted-foreground text-xs">{row.studentId}</div>
                                    </td>
                                    {isSymbolic ? (
                                        <td className="px-4 py-2 text-center">
                                            {availableSymbols.length > 0 ? (
                                                <select
                                                    className="bg-background border-input ring-offset-background placeholder:text-muted-foreground focus:ring-ring mx-auto flex h-9 w-full max-w-[180px] rounded-md border px-3 py-1 text-sm shadow-xs focus:ring-2 focus:outline-hidden"
                                                    value={row.symbol}
                                                    onChange={(event) => updateSymbol(row.enrollmentId, event.target.value)}
                                                    aria-label={`Symbol grade for ${row.name}`}
                                                >
                                                    <option value="">Select symbol...</option>
                                                    {availableSymbols.map((sym) => (
                                                        <option key={sym} value={sym}>
                                                            {sym}
                                                        </option>
                                                    ))}
                                                </select>
                                            ) : (
                                                <Input
                                                    className="mx-auto max-w-[180px] text-center"
                                                    value={row.symbol}
                                                    onChange={(event) => updateSymbol(row.enrollmentId, event.target.value)}
                                                    placeholder="Grade symbol"
                                                    aria-label={`Symbol grade for ${row.name}`}
                                                />
                                            )}
                                        </td>
                                    ) : (
                                        <>
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
                                        </>
                                    )}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>
    );
}
