import { useEffect, useMemo, useRef, useState } from "react";

import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from "@/components/ui/alert-dialog";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Switch } from "@/components/ui/switch";
import type { GradingComponentPayload, GradingConfigPayload } from "@/pages/administrators/system-management/types";
import { router, usePage } from "@inertiajs/react";
import { Calculator, Download, Lock, Save, Search, Send, Upload } from "lucide-react";
import { toast } from "sonner";

type StudentRow = {
    id: number | string;
    name: string;
    studentId?: string;
    student_id?: string;
    is_grades_finalized?: boolean;
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
    isFinalized: boolean;
    components: Record<string, string>;
    average: string;
    symbol: string;
};

interface GradesFinalizedSummary {
    total: number;
    finalized: number;
    all_finalized: boolean;
    any_finalized?: boolean;
}

interface GradeSheetProps {
    classId: number | string;
    students: StudentRow[];
    autoAverageDefault?: boolean;
    gradingPolicy?: GradingConfigPayload;
    gradesFinalized?: GradesFinalizedSummary;
}

const fallbackComponents: GradingComponentPayload[] = [
    { id: "prelim", key: "prelim", label: "Prelim", weight: 30, required: true, sort_order: 0 },
    { id: "midterm", key: "midterm", label: "Midterm", weight: 30, required: true, sort_order: 1 },
    { id: "final", key: "final", label: "Final", weight: 40, required: true, sort_order: 2 },
];

function escapeCsvCell(raw: string): string {
    const trimmedLeading = raw.replace(/^[\s\t\r\n]+/, "");
    const safe = trimmedLeading.length > 0 && ["=", "+", "-", "@"].includes(trimmedLeading[0]) ? `'${raw}` : raw;
    if (/[",\r\n]/.test(safe)) {
        return `"${safe.replace(/"/g, '""')}"`;
    }
    return safe;
}

function parseCsvRows(text: string): string[][] {
    const cleaned = text.replace(/^\uFEFF/, "");
    const rows: string[][] = [];
    let currentRow: string[] = [];
    let currentField = "";
    let inQuotes = false;

    for (let i = 0; i < cleaned.length; i++) {
        const char = cleaned[i];
        const nextChar = cleaned[i + 1];

        if (inQuotes) {
            if (char === '"' && nextChar === '"') {
                currentField += '"';
                i++;
            } else if (char === '"') {
                inQuotes = false;
            } else {
                currentField += char;
            }
        } else if (char === '"') {
            inQuotes = true;
        } else if (char === ",") {
            currentRow.push(currentField.trim());
            currentField = "";
        } else if (char === "\r" && nextChar === "\n") {
            currentRow.push(currentField.trim());
            rows.push(currentRow);
            currentRow = [];
            currentField = "";
            i++;
        } else if (char === "\n" || char === "\r") {
            currentRow.push(currentField.trim());
            rows.push(currentRow);
            currentRow = [];
            currentField = "";
        } else {
            currentField += char;
        }
    }

    if (currentField.length > 0 || currentRow.length > 0) {
        currentRow.push(currentField.trim());
        rows.push(currentRow);
    }

    return rows.filter((row) => row.some((cell) => cell !== ""));
}

export function GradeSheet({ classId, students, autoAverageDefault = true, gradingPolicy, gradesFinalized }: GradeSheetProps) {
    const pageProps = usePage<{
        students?: Array<{ id: number | string; is_grades_finalized?: boolean; grades?: { symbol?: string | null } }>;
        gradesFinalized?: GradesFinalizedSummary;
    }>().props;

    const pageFinalizedById = useMemo(() => {
        const map = new Map<string, { finalized: boolean; symbol?: string | null }>();
        for (const item of pageProps.students ?? []) {
            map.set(String(item.id), {
                finalized: Boolean(item.is_grades_finalized),
                symbol: item.grades?.symbol,
            });
        }
        return map;
    }, [pageProps.students]);

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
    const [searchQuery, setSearchQuery] = useState("");
    const fileInputRef = useRef<HTMLInputElement>(null);

    const initialRows = useMemo<GradeRow[]>(
        () =>
            students.map((student) => {
                const legacy: Record<string, number | null | undefined> = {
                    prelim: student.grades.prelim,
                    midterm: student.grades.midterm,
                    final: student.grades.final,
                };
                const source = student.grades.components && Object.keys(student.grades.components).length > 0 ? student.grades.components : legacy;
                const gradeComponents = Object.fromEntries(
                    components.map((component) => [component.key, source[component.key] == null ? "" : String(source[component.key])]),
                );
                const pageMeta = pageFinalizedById.get(String(student.id));
                const isFinalized =
                    student.is_grades_finalized !== undefined ? Boolean(student.is_grades_finalized) : Boolean(pageMeta?.finalized);

                return {
                    enrollmentId: student.id,
                    name: student.name,
                    studentId: student.studentId ?? student.student_id ?? "",
                    isFinalized,
                    components: gradeComponents,
                    average: student.grades.average == null ? "" : String(student.grades.average),
                    symbol: student.grades.symbol ?? pageMeta?.symbol ?? "",
                };
            }),
        [components, pageFinalizedById, students],
    );

    const [gradeRows, setGradeRows] = useState(initialRows);
    const [isDirty, setIsDirty] = useState(false);
    const [showSubmitDialog, setShowSubmitDialog] = useState(false);
    const [isSubmittingGrades, setIsSubmittingGrades] = useState(false);

    useEffect(() => {
        setGradeRows(initialRows);
        setIsDirty(false);
    }, [initialRows]);

    const resolvedFinalizedSummary = useMemo<GradesFinalizedSummary>(() => {
        const direct = gradesFinalized ?? pageProps.gradesFinalized;
        const finalizedCount = gradeRows.filter((row) => row.isFinalized).length;
        const total = gradeRows.length;
        if (direct) {
            return {
                total: direct.total,
                finalized: direct.finalized,
                all_finalized: direct.all_finalized,
                any_finalized: direct.any_finalized ?? direct.finalized > 0,
            };
        }
        return {
            total,
            finalized: finalizedCount,
            all_finalized: total > 0 && finalizedCount === total,
            any_finalized: finalizedCount > 0,
        };
    }, [gradeRows, gradesFinalized, pageProps.gradesFinalized]);

    const allFinalized = resolvedFinalizedSummary.all_finalized;
    const anyFinalized = Boolean(resolvedFinalizedSummary.any_finalized || resolvedFinalizedSummary.finalized > 0);

    const numericRange =
        gradingPolicy?.input_type === "numeric" ? { min: gradingPolicy.numeric_min, max: gradingPolicy.numeric_max } : { min: 0, max: 100 };
    const label = gradingPolicy?.name ?? "Default grading policy";

    const gradedCount = useMemo(() => {
        return gradeRows.filter((row) => {
            if (isSymbolic) {
                return row.symbol.trim() !== "";
            }
            if (row.average.trim() !== "") {
                return true;
            }
            const requiredComponents = components.filter((c) => c.required);
            if (requiredComponents.length > 0) {
                return requiredComponents.every((c) => (row.components[c.key] ?? "").trim() !== "");
            }
            return components.some((c) => (row.components[c.key] ?? "").trim() !== "");
        }).length;
    }, [components, gradeRows, isSymbolic]);

    const visibleRows = useMemo(() => {
        const query = searchQuery.trim().toLowerCase();
        if (!query) return gradeRows;
        return gradeRows.filter((row) => row.name.toLowerCase().includes(query) || row.studentId.toLowerCase().includes(query));
    }, [gradeRows, searchQuery]);

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
        setIsDirty(true);
        setGradeRows((previous) =>
            previous.map((row) => {
                if (row.enrollmentId !== enrollmentId || row.isFinalized) return row;
                const componentValues = { ...row.components, [key]: value };
                return { ...row, components: componentValues, average: autoAverageEnabled ? calculateAverage(componentValues) : row.average };
            }),
        );
    };

    const updateSymbol = (enrollmentId: number | string, symbol: string) => {
        setIsDirty(true);
        setGradeRows((previous) =>
            previous.map((row) => (row.enrollmentId === enrollmentId && !row.isFinalized ? { ...row, symbol } : row)),
        );
    };

    const handleExportCsv = () => {
        const lines: string[] = [];
        if (isSymbolic) {
            lines.push(["student_id", "student_name", "symbol"].join(","));
            for (const row of gradeRows) {
                lines.push([escapeCsvCell(row.studentId), escapeCsvCell(row.name), escapeCsvCell(row.symbol)].join(","));
            }
        } else {
            const componentKeys = components.map((c) => c.key);
            const isDefaultTriplet =
                componentKeys.length === 3 &&
                componentKeys.includes("prelim") &&
                componentKeys.includes("midterm") &&
                (componentKeys.includes("final") || componentKeys.includes("finals"));
            const headerKeys = isDefaultTriplet ? ["prelim", "midterm", "final"] : componentKeys;
            lines.push(["student_id", "student_name", ...headerKeys, "average"].join(","));

            for (const row of gradeRows) {
                const values = headerKeys.map((key) => {
                    const value = row.components[key] ?? (key === "final" ? row.components["finals"] : "") ?? "";
                    return escapeCsvCell(value);
                });
                lines.push([escapeCsvCell(row.studentId), escapeCsvCell(row.name), ...values, escapeCsvCell(row.average)].join(","));
            }
        }

        const blob = new Blob([lines.join("\n")], { type: "text/csv;charset=utf-8;" });
        const url = URL.createObjectURL(blob);
        const anchor = document.createElement("a");
        anchor.href = url;
        anchor.download = `gradesheet-class-${classId}.csv`;
        document.body.appendChild(anchor);
        anchor.click();
        document.body.removeChild(anchor);
        URL.revokeObjectURL(url);
        toast.success("CSV gradebook exported");
    };

    const handleImportCsvFile = async (event: React.ChangeEvent<HTMLInputElement>) => {
        const file = event.target.files?.[0];
        event.target.value = "";
        if (!file) return;

        try {
            const text = await file.text();
            const parsed = parseCsvRows(text);
            if (parsed.length < 2) {
                toast.error("CSV must include a header row and at least one student row.");
                return;
            }

            const headers = parsed[0].map((h) => h.trim().toLowerCase());
            const studentIdIdx = headers.findIndex((h) => ["student_id", "student id", "lrn", "id"].includes(h));
            if (studentIdIdx === -1) {
                toast.error("CSV is missing a required student_id column.");
                return;
            }

            const rowIndexByStudentId = new Map<string, number>();
            gradeRows.forEach((row, index) => {
                const key = row.studentId.trim().toLowerCase();
                if (key !== "") {
                    rowIndexByStudentId.set(key, index);
                }
            });

            const updatedRows = gradeRows.map((row) => ({ ...row, components: { ...row.components } }));
            let imported = 0;
            let skipped = 0;

            for (const csvRow of parsed.slice(1)) {
                const rawId = (csvRow[studentIdIdx] ?? "").replace(/^'/, "").trim().toLowerCase();
                const targetIndex = rowIndexByStudentId.get(rawId);

                if (targetIndex === undefined) {
                    skipped++;
                    continue;
                }

                const targetRow = updatedRows[targetIndex];
                if (targetRow.isFinalized) {
                    skipped++;
                    continue;
                }

                if (isSymbolic) {
                    const symbolIdx = headers.findIndex((h) => ["symbol", "grade", "grade_symbol"].includes(h));
                    const rawSymbol = symbolIdx >= 0 ? (csvRow[symbolIdx] ?? "").trim() : "";
                    if (rawSymbol === "") {
                        skipped++;
                        continue;
                    }
                    if (availableSymbols.length > 0 && !availableSymbols.some((sym) => sym.toLowerCase() === rawSymbol.toLowerCase())) {
                        skipped++;
                        continue;
                    }
                    const matchedSymbol = availableSymbols.find((sym) => sym.toLowerCase() === rawSymbol.toLowerCase()) ?? rawSymbol;
                    targetRow.symbol = matchedSymbol;
                    imported++;
                    continue;
                }

                let rowValid = true;
                let rowHasAnyScore = false;
                const nextComponents = { ...targetRow.components };

                for (const component of components) {
                    const aliases =
                        component.key === "final"
                            ? ["final", "finals"]
                            : component.key === "finals"
                              ? ["finals", "final"]
                              : [component.key.toLowerCase(), component.label.toLowerCase()];
                    const colIdx = headers.findIndex((h) => aliases.includes(h));
                    if (colIdx === -1) continue;

                    const rawCell = (csvRow[colIdx] ?? "").trim();
                    if (rawCell === "") continue;

                    const num = Number(rawCell);
                    if (!Number.isFinite(num) || num < numericRange.min || num > numericRange.max) {
                        rowValid = false;
                        break;
                    }
                    nextComponents[component.key] = String(num);
                    rowHasAnyScore = true;
                }

                const avgIdx = headers.findIndex((h) => ["average", "total_average", "final_average"].includes(h));
                let importedAvg: string | null = null;
                if (rowValid && avgIdx >= 0) {
                    const rawAvg = (csvRow[avgIdx] ?? "").trim();
                    if (rawAvg !== "") {
                        const avgNum = Number(rawAvg);
                        if (!Number.isFinite(avgNum) || avgNum < numericRange.min || avgNum > numericRange.max) {
                            rowValid = false;
                        } else {
                            importedAvg = String(avgNum);
                            rowHasAnyScore = true;
                        }
                    }
                }

                if (!rowValid || !rowHasAnyScore) {
                    skipped++;
                    continue;
                }

                targetRow.components = nextComponents;
                targetRow.average = autoAverageEnabled ? calculateAverage(nextComponents) || importedAvg || "" : (importedAvg ?? targetRow.average);
                imported++;
            }

            if (imported > 0) {
                setGradeRows(updatedRows);
                setIsDirty(true);
                toast.success(`${imported} rows imported, ${skipped} skipped. Review and click Save grades.`);
            } else {
                toast.warning(`0 rows imported, ${skipped} skipped. Check student IDs and grade bounds (${numericRange.min}–${numericRange.max}).`);
            }
        } catch {
            toast.error("Unable to parse CSV file.");
        }
    };

    const buildGradePayload = () => ({
        grades: gradeRows
            .filter((row) => !row.isFinalized)
            .map((row) => ({
                enrollment_id: row.enrollmentId,
                symbol: row.symbol || null,
                components: Object.fromEntries(Object.entries(row.components).map(([key, value]) => [key, value === "" ? null : Number(value)])),
                average: row.average === "" ? null : Number(row.average),
            })),
    });

    const saveGrades = (onSaved?: () => void) => {
        if (allFinalized) {
            toast.error("All grades in this class are finalized and locked.");
            return;
        }
        setIsSavingGrades(true);
        router.put(`/faculty/classes/${classId}/grades`, buildGradePayload(), {
            preserveScroll: true,
            onSuccess: (page) => {
                const flash = (page.props as { flash?: { error?: string; success?: string } })?.flash;
                if (flash?.error) {
                    toast.error(flash.error);
                    return;
                }
                setIsDirty(false);
                toast.success(flash?.success || "Grades saved");
                onSaved?.();
            },
            onError: () => toast.error("Unable to save grades"),
            onFinish: () => setIsSavingGrades(false),
        });
    };

    const confirmAndSubmitGrades = () => {
        const executeSubmit = () => {
            setIsSubmittingGrades(true);
            router.post(
                `/faculty/classes/${classId}/grades/submit`,
                {},
                {
                    preserveScroll: true,
                    onSuccess: () => {
                        setShowSubmitDialog(false);
                        toast.success("Grades finalized and submitted");
                    },
                    onError: () => toast.error("Unable to submit grades"),
                    onFinish: () => setIsSubmittingGrades(false),
                },
            );
        };

        if (isDirty && !allFinalized) {
            saveGrades(executeSubmit);
        } else {
            executeSubmit();
        }
    };

    return (
        <Card>
            <CardHeader className="bg-muted/20 gap-4 border-b sm:flex-row sm:items-start sm:justify-between">
                <div className="space-y-1">
                    <CardTitle className="flex flex-wrap items-center gap-2">
                        <Calculator className="size-5" /> Gradebook
                        <Badge variant="secondary" className="font-mono text-xs tabular-nums">
                            {gradedCount} / {gradeRows.length} graded
                        </Badge>
                        {allFinalized ? (
                            <Badge variant="outline" className="gap-1 border-emerald-500/40 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300">
                                <Lock className="size-3" />
                                Finalized · Locked
                            </Badge>
                        ) : anyFinalized ? (
                            <Badge variant="outline" className="gap-1 border-amber-500/40 bg-amber-500/10 text-amber-700 dark:text-amber-300">
                                <Lock className="size-3" />
                                {resolvedFinalizedSummary.finalized} / {resolvedFinalizedSummary.total} Finalized · Locked
                            </Badge>
                        ) : null}
                        {isDirty && !allFinalized && (
                            <Badge variant="outline" className="border-amber-500/40 bg-amber-500/10 text-amber-700 dark:text-amber-300">
                                Unsaved changes
                            </Badge>
                        )}
                    </CardTitle>
                    <CardDescription>{label}. Weighted from the active school policy, not a fixed 30 / 30 / 40 formula.</CardDescription>
                    {anyFinalized && (
                        <p className="text-muted-foreground flex items-center gap-1.5 pt-0.5 text-xs">
                            <Lock className="size-3.5 shrink-0" />
                            Need a correction? Contact the Registrar to unlock finalized rows.
                        </p>
                    )}
                </div>
                <div className="flex flex-wrap items-center gap-2">
                    {!isSymbolic && (
                        <div className="mr-1 flex items-center gap-2">
                            <Label htmlFor="auto-average" className="text-sm">
                                Auto-calculate
                            </Label>
                            <Switch
                                id="auto-average"
                                checked={autoAverageEnabled}
                                onCheckedChange={setAutoAverageEnabled}
                                disabled={allFinalized}
                            />
                        </div>
                    )}
                    <input
                        ref={fileInputRef}
                        type="file"
                        accept=".csv,text/csv"
                        onChange={handleImportCsvFile}
                        className="hidden"
                        aria-label="Import grades CSV"
                    />
                    <Button variant="outline" size="sm" onClick={handleExportCsv} disabled={gradeRows.length === 0} className="gap-1.5">
                        <Download className="size-4" /> Export CSV
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => fileInputRef.current?.click()}
                        disabled={allFinalized || gradeRows.length === 0 || isSavingGrades || isSubmittingGrades}
                        className="gap-1.5"
                    >
                        <Upload className="size-4" /> Import CSV
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => setShowSubmitDialog(true)}
                        disabled={allFinalized || gradeRows.length === 0 || isSavingGrades || isSubmittingGrades}
                        className="gap-1.5"
                    >
                        <Send className="size-4" /> Submit &amp; Finalize
                    </Button>
                    <Button
                        size="sm"
                        onClick={() => saveGrades()}
                        disabled={allFinalized || gradeRows.length === 0 || isSavingGrades || isSubmittingGrades}
                        className="gap-1.5"
                    >
                        <Save className="size-4" /> {isSavingGrades ? "Saving" : "Save grades"}
                    </Button>
                </div>
            </CardHeader>

            <AlertDialog open={showSubmitDialog} onOpenChange={setShowSubmitDialog}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle className="flex items-center gap-2">
                            <Lock className="size-4 text-amber-500" />
                            Finalize and submit grades?
                        </AlertDialogTitle>
                        <AlertDialogDescription>
                            {isDirty
                                ? "Your unsaved grade edits will be saved first, then all evaluated grades for this class will be locked and submitted to the Registrar."
                                : "Submitted grades will be locked from further editing and queued for Registrar release."}
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel disabled={isSavingGrades || isSubmittingGrades}>Cancel</AlertDialogCancel>
                        <AlertDialogAction
                            onClick={(event) => {
                                event.preventDefault();
                                confirmAndSubmitGrades();
                            }}
                            disabled={isSavingGrades || isSubmittingGrades}
                        >
                            {isSavingGrades || isSubmittingGrades ? "Submitting..." : "Confirm & Finalize"}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>

            <CardContent className="pt-6">
                <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex flex-wrap gap-2">
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
                    <div className="relative w-full sm:w-64">
                        <Search className="text-muted-foreground absolute top-2.5 left-3 size-4" />
                        <Input
                            value={searchQuery}
                            onChange={(event) => setSearchQuery(event.target.value)}
                            placeholder="Search student name or ID..."
                            className="pl-9"
                            aria-label="Search student"
                        />
                    </div>
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
                            {visibleRows.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={isSymbolic ? 2 : components.length + 2}
                                        className="text-muted-foreground px-4 py-8 text-center text-sm"
                                    >
                                        {gradeRows.length === 0
                                            ? "No enrolled students in this class yet."
                                            : "No students match your search query."}
                                    </td>
                                </tr>
                            ) : (
                                visibleRows.map((row) => (
                                    <tr key={row.enrollmentId} className={`border-t ${row.isFinalized ? "bg-muted/20" : ""}`}>
                                        <td className="px-4 py-3">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <span className="font-medium">{row.name}</span>
                                                {row.isFinalized && (
                                                    <Badge
                                                        variant="outline"
                                                        className="gap-1 border-emerald-500/40 bg-emerald-500/10 text-[10px] text-emerald-700 dark:text-emerald-300"
                                                    >
                                                        <Lock className="size-2.5" />
                                                        Finalized · Locked
                                                    </Badge>
                                                )}
                                            </div>
                                            <div className="text-muted-foreground text-xs">{row.studentId}</div>
                                        </td>
                                        {isSymbolic ? (
                                            <td className="px-4 py-2 text-center">
                                                {availableSymbols.length > 0 ? (
                                                    <select
                                                        className="bg-background border-input ring-offset-background placeholder:text-muted-foreground focus:ring-ring mx-auto flex h-9 w-full max-w-[180px] rounded-md border px-3 py-1 text-sm shadow-xs focus:ring-2 focus:outline-hidden disabled:cursor-not-allowed disabled:opacity-60"
                                                        value={row.symbol}
                                                        disabled={row.isFinalized}
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
                                                        disabled={row.isFinalized}
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
                                                            disabled={row.isFinalized}
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
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>
    );
}
