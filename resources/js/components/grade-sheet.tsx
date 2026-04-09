import { useMemo, useState } from "react";

import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Label } from "@/components/ui/label";
import { Switch } from "@/components/ui/switch";
import { router } from "@inertiajs/react";
import { toast } from "sonner";

type GradeTermKey = "prelim" | "midterm" | "final";

type StudentRow = {
    id: number | string;
    name: string;
    studentId: string;
    grades: {
        prelim?: number | null;
        midterm?: number | null;
        final?: number | null;
        average?: number | null;
    };
};

type GradeSheetProps = {
    classId: number;
    students: StudentRow[];
    autoAverageDefault: boolean;
};

const gradeColumns: GradeTermKey[] = ["prelim", "midterm", "final"];

const gradeTone = (value: number | "" | null) => {
    if (value === "" || value === null || Number.isNaN(Number(value))) {
        return { text: "text-muted-foreground", bg: "bg-transparent" };
    }

    const num = Number(value);
    if (num < 75) return { text: "text-red-600", bg: "bg-red-500/10" };
    if (num < 80) return { text: "text-amber-600", bg: "bg-amber-500/10" };
    if (num < 86) return { text: "text-amber-700", bg: "bg-amber-600/10" };
    if (num < 91) return { text: "text-emerald-600", bg: "bg-emerald-500/10" };
    if (num < 96) return { text: "text-emerald-700", bg: "bg-emerald-600/10" };
    return { text: "text-blue-700", bg: "bg-blue-500/10" };
};

export function GradeSheet({ classId, students, autoAverageDefault }: GradeSheetProps) {
    const [autoAverageEnabled, setAutoAverageEnabled] = useState<boolean>(autoAverageDefault);
    const [isSavingGrades, setIsSavingGrades] = useState(false);
    const [gradeRows, setGradeRows] = useState(() =>
        students.map((student) => ({
            enrollmentId: student.id,
            name: student.name,
            studentId: student.studentId,
            prelim: student.grades.prelim ?? "",
            midterm: student.grades.midterm ?? "",
            final: student.grades.final ?? "",
            average: student.grades.average ?? "",
        })),
    );

    const computeAverage = (row: (typeof gradeRows)[number]) => {
        const prelim = row.prelim === "" ? null : Number(row.prelim);
        const midterm = row.midterm === "" ? null : Number(row.midterm);
        const final = row.final === "" ? null : Number(row.final);

        if (prelim === null || midterm === null || final === null) {
            return "";
        }

        return Math.round((prelim * 0.3 + midterm * 0.3 + final * 0.4) * 100) / 100;
    };

    const handleGradeChange = (index: number, field: GradeTermKey | "average", value: string) => {
        setGradeRows((prev) => {
            const next = [...prev];
            const sanitized = value === "" ? "" : Math.min(100, Math.max(0, Number(value)));
            next[index] = { ...next[index], [field]: sanitized };

            if (autoAverageEnabled && (field === "prelim" || field === "midterm" || field === "final")) {
                next[index].average = computeAverage(next[index]);
            }

            return next;
        });
    };

    const handleCellInput = (event: React.FormEvent<HTMLDivElement>, rowIndex: number, field: GradeTermKey | "average") => {
        const raw = event.currentTarget.innerText.replace(/[^\d.]/g, "");
        handleGradeChange(rowIndex, field, raw);
    };

    const allCompleteByTerm = useMemo(() => {
        const base = { prelim: true, midterm: true, finals: true };

        for (const row of gradeRows) {
            if (row.prelim === "") {
                base.prelim = false;
            }
            if (row.midterm === "") {
                base.midterm = false;
            }
            if (row.prelim === "" || row.midterm === "" || row.final === "") {
                base.finals = false;
            }
        }

        return base;
    }, [gradeRows]);

    const rowCompleteness = useMemo(
        () =>
            gradeRows.map((row) => ({
                prelim: row.prelim !== "",
                midterm: row.midterm !== "",
                finals: row.prelim !== "" && row.midterm !== "" && row.final !== "",
            })),
        [gradeRows],
    );

    const handleArrowKeyNav = (event: React.KeyboardEvent<HTMLElement>, rowIndex: number, columnKey: GradeTermKey | "average") => {
        const directions: Record<string, { rowDelta: number; colDelta: number }> = {
            ArrowUp: { rowDelta: -1, colDelta: 0 },
            ArrowDown: { rowDelta: 1, colDelta: 0 },
            ArrowLeft: { rowDelta: 0, colDelta: -1 },
            ArrowRight: { rowDelta: 0, colDelta: 1 },
        };

        const direction = directions[event.key];
        if (!direction) return;

        event.preventDefault();

        const colOrder: Array<GradeTermKey | "average"> = ["prelim", "midterm", "final", "average"];
        const currentColIndex = colOrder.indexOf(columnKey);
        const targetColIndex = currentColIndex + direction.colDelta;
        const targetRowIndex = rowIndex + direction.rowDelta;

        const targetCol = colOrder[targetColIndex];
        if (targetCol === undefined || targetRowIndex < 0 || targetRowIndex >= gradeRows.length) return;

        const next = document.querySelector<HTMLElement>(`[data-cell="${targetRowIndex}-${targetCol}"]`);
        next?.focus();
        if (next instanceof HTMLDivElement) {
            const range = document.createRange();
            range.selectNodeContents(next);
            const sel = window.getSelection();
            sel?.removeAllRanges();
            sel?.addRange(range);
        }
    };

    const handleSaveGrades = () => {
        if (isSavingGrades) return;
        setIsSavingGrades(true);

        router.put(
            `/faculty/classes/${classId}/grades`,
            {
                grades: gradeRows.map((row) => ({
                    enrollment_id: row.enrollmentId,
                    prelim: row.prelim === "" ? null : Number(row.prelim),
                    midterm: row.midterm === "" ? null : Number(row.midterm),
                    final: row.final === "" ? null : Number(row.final),
                    average: row.average === "" ? null : Number(row.average),
                })),
            },
            {
                preserveState: true,
                onSuccess: () => {
                    toast.success("Grades saved with color-coded indicators");
                },
                onError: () => {
                    toast.error("Unable to save grades");
                },
                onFinish: () => setIsSavingGrades(false),
            },
        );
    };

    const handleSubmitTerm = (term: "prelim" | "midterm" | "finals") => {
        router.post(
            `/faculty/classes/${classId}/grades/submit`,
            { term },
            {
                preserveState: true,
                onSuccess: () => toast.success(`${term === "finals" ? "Finals" : term.charAt(0).toUpperCase() + term.slice(1)} submitted`),
                onError: () => toast.error("Unable to submit grades"),
            },
        );
    };

    return (
        <Card className="border-border/70 bg-card/90 shadow-sm">
            <CardHeader className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <CardTitle>Grades</CardTitle>
                    <CardDescription>Edit grades with quick keyboard workflow</CardDescription>
                </div>
                <div className="flex items-center gap-3">
                    <Label className="text-muted-foreground text-xs tracking-[0.3em] uppercase">Auto average</Label>
                    <Switch
                        checked={autoAverageEnabled}
                        onCheckedChange={(checked) => {
                            setAutoAverageEnabled(checked);
                            if (checked) {
                                setGradeRows((prev) =>
                                    prev.map((row) => ({
                                        ...row,
                                        average: computeAverage(row),
                                    })),
                                );
                            }
                        }}
                    />
                </div>
            </CardHeader>
            <CardContent className="space-y-4">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div className="text-muted-foreground flex flex-wrap gap-2 text-xs">
                        <Badge variant="outline" className="rounded-full">
                            Prelim complete: {allCompleteByTerm.prelim ? "Yes" : "No"}
                        </Badge>
                        <Badge variant="outline" className="rounded-full">
                            Midterm complete: {allCompleteByTerm.midterm ? "Yes" : "No"}
                        </Badge>
                        <Badge variant="outline" className="rounded-full">
                            Finals complete: {allCompleteByTerm.finals ? "Yes" : "No"}
                        </Badge>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button variant="outline" size="sm" className="rounded-full" onClick={handleSaveGrades} disabled={isSavingGrades}>
                            {isSavingGrades ? "Saving..." : "Save grades"}
                        </Button>
                        <Button
                            size="sm"
                            className="rounded-full"
                            variant="secondary"
                            disabled={!allCompleteByTerm.prelim}
                            onClick={() => handleSubmitTerm("prelim")}
                        >
                            Submit Prelim
                        </Button>
                        <Button
                            size="sm"
                            className="rounded-full"
                            variant="secondary"
                            disabled={!allCompleteByTerm.midterm}
                            onClick={() => handleSubmitTerm("midterm")}
                        >
                            Submit Midterm
                        </Button>
                        <Button size="sm" className="rounded-full" disabled={!allCompleteByTerm.finals} onClick={() => handleSubmitTerm("finals")}>
                            Submit Finals
                        </Button>
                    </div>
                </div>

                <div className="border-border/60 overflow-x-auto rounded-lg border">
                    <table className="w-full min-w-[900px] border-separate border-spacing-0 text-sm">
                        <thead>
                            <tr className="bg-muted/40 text-muted-foreground text-xs tracking-[0.25em] uppercase">
                                <th className="px-4 py-2 text-left">Student</th>
                                <th className="px-4 py-2 text-left">ID</th>
                                <th className="px-4 py-2 text-center">Prelim</th>
                                <th className="px-4 py-2 text-center">Midterm</th>
                                <th className="px-4 py-2 text-center">Finals</th>
                                <th className="px-4 py-2 text-center">Average</th>
                                <th className="px-4 py-2 text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            {gradeRows.map((row, index) => {
                                const status = rowCompleteness[index];
                                const avgTone = gradeTone(row.average === "" ? "" : Number(row.average));
                                return (
                                    <tr key={row.enrollmentId} className="border-border/40 hover:bg-muted/20 border-t transition-colors">
                                        <td className="text-foreground px-4 py-3 font-medium">{row.name}</td>
                                        <td className="text-muted-foreground px-4 py-3 font-mono text-xs">{row.studentId}</td>
                                        {gradeColumns.map((field) => {
                                            const tone = gradeTone(row[field] === "" ? "" : Number(row[field]));
                                            return (
                                                <td key={field} className="px-3 py-2 text-center align-middle">
                                                    <div
                                                        role="textbox"
                                                        aria-label={`${field} grade for ${row.name}`}
                                                        contentEditable
                                                        suppressContentEditableWarning
                                                        data-cell={`${index}-${field}`}
                                                        onKeyDown={(event) => handleArrowKeyNav(event, index, field)}
                                                        onInput={(event) => handleCellInput(event, index, field)}
                                                        className={`focus:border-primary/40 focus:bg-primary/5 focus:ring-primary/20 h-9 w-20 rounded-md border border-transparent bg-transparent px-2 text-center text-sm font-semibold outline-none focus:ring-2 ${tone.text} ${tone.bg}`}
                                                    >
                                                        {row[field] ?? ""}
                                                    </div>
                                                </td>
                                            );
                                        })}
                                        <td className="px-3 py-2 text-center align-middle">
                                            <div
                                                role="textbox"
                                                aria-label={`average for ${row.name}`}
                                                contentEditable={!autoAverageEnabled}
                                                suppressContentEditableWarning
                                                data-cell={`${index}-average`}
                                                onKeyDown={(event) => handleArrowKeyNav(event, index, "average")}
                                                onInput={(event) => {
                                                    if (autoAverageEnabled) return;
                                                    handleCellInput(event, index, "average");
                                                }}
                                                className={`focus:border-primary/40 focus:bg-primary/5 focus:ring-primary/20 disabled:text-muted-foreground h-9 w-20 rounded-md border border-transparent bg-transparent px-2 text-center text-sm font-semibold outline-none focus:ring-2 ${avgTone.text} ${avgTone.bg}`}
                                            >
                                                {row.average ?? ""}
                                            </div>
                                        </td>
                                        <td className="text-muted-foreground px-4 py-3 text-center text-xs">
                                            <div className="flex items-center justify-center gap-1">
                                                <Badge variant={status.prelim ? "secondary" : "outline"} className="rounded-full px-2 py-0">
                                                    P
                                                </Badge>
                                                <Badge variant={status.midterm ? "secondary" : "outline"} className="rounded-full px-2 py-0">
                                                    M
                                                </Badge>
                                                <Badge variant={status.finals ? "secondary" : "outline"} className="rounded-full px-2 py-0">
                                                    F
                                                </Badge>
                                            </div>
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>
    );
}
