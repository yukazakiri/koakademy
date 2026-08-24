import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { ScrollArea } from "@/components/ui/scroll-area";
import { confirm, store } from "@/routes/administrators/registrar/analytics/student-profile-imports";
import { router } from "@inertiajs/react";
import axios from "axios";
import { AlertTriangle, CheckCircle2, ChevronDown, FileSpreadsheet, FileUp, Loader2, ShieldCheck, UserRoundCheck, XCircle } from "lucide-react";
import { useMemo, useState } from "react";
import { toast } from "sonner";

type ImportChange = {
    key: string;
    label: string;
    group: string;
    target: "student" | "enrollment";
    old: unknown;
    new: unknown;
};

type ImportStudent = {
    id: number;
    student_id: number | null;
    student_number: string | null;
    student_name: string;
    course_code: string | null;
    year_level: number | null;
    intake_category: string | null;
    excel_row: number;
    status: "ready" | "invalid" | "applied" | "skipped";
    changes: ImportChange[];
    errors: string[];
    warnings: string[];
    result: { message?: string } | null;
};

type ImportPreview = {
    id: string;
    status: "review" | "completed";
    filename: string;
    summary: {
        ready_students: number;
        invalid_students: number;
        applied_students: number;
        skipped_students: number;
        changed_fields: number;
        new_freshmen: number;
        continuing_first_year: number;
        unclassified_first_year: number;
    };
    students: ImportStudent[];
};

type Filter = "all" | "ready" | "invalid" | "new_freshman" | "continuing_first_year" | "unclassified";

const displayValue = (value: unknown): string => {
    if (value === null || value === undefined || value === "") return "Empty";
    if (typeof value === "boolean") return value ? "Yes" : "No";

    return String(value).replaceAll("_", " ");
};

const errorMessage = (error: unknown): string => {
    if (!axios.isAxiosError(error)) return "The workbook could not be processed.";

    const data = error.response?.data as { message?: string; errors?: Record<string, string[]> } | undefined;
    const validationMessage = data?.errors ? Object.values(data.errors).flat()[0] : null;

    return validationMessage ?? data?.message ?? "The workbook could not be processed.";
};

function IntakeBadge({ student }: { student: ImportStudent }) {
    if (student.year_level !== 1) {
        return <Badge variant="outline">Year {student.year_level ?? "—"}</Badge>;
    }

    if (student.intake_category === "new_freshman") {
        return <Badge className="border-emerald-500/25 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300">New freshman</Badge>;
    }

    if (student.intake_category === "continuing_first_year") {
        return <Badge className="border-sky-500/25 bg-sky-500/10 text-sky-700 dark:text-sky-300">Continuing first-year</Badge>;
    }

    return (
        <Badge variant="outline" className="border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-300">
            First-year · unclassified
        </Badge>
    );
}

function StudentStatus({ status }: { status: ImportStudent["status"] }) {
    const styles = {
        ready: "border-sky-500/30 bg-sky-500/10 text-sky-700 dark:text-sky-300",
        invalid: "border-rose-500/30 bg-rose-500/10 text-rose-700 dark:text-rose-300",
        applied: "border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300",
        skipped: "border-muted-foreground/20 bg-muted text-muted-foreground",
    };

    return (
        <Badge variant="outline" className={styles[status]}>
            {status === "ready" ? "Ready" : status === "invalid" ? "Needs attention" : status === "applied" ? "Updated" : "Skipped"}
        </Badge>
    );
}

export function RegistrarStudentProfileImportDialog() {
    const [open, setOpen] = useState(false);
    const [file, setFile] = useState<File | null>(null);
    const [preview, setPreview] = useState<ImportPreview | null>(null);
    const [selectedStudentIds, setSelectedStudentIds] = useState<number[]>([]);
    const [filter, setFilter] = useState<Filter>("all");
    const [uploading, setUploading] = useState(false);
    const [confirming, setConfirming] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const shownStudents = useMemo(() => {
        if (!preview) return [];

        return preview.students.filter((student) => {
            if (filter === "all") return true;
            if (filter === "ready" || filter === "invalid") return student.status === filter;
            if (filter === "unclassified") return student.year_level === 1 && !student.intake_category;

            return student.intake_category === filter;
        });
    }, [filter, preview]);
    const visibleReadyIds = shownStudents
        .filter((student) => student.status === "ready" && student.student_id !== null)
        .map((student) => student.student_id!);
    const allVisibleSelected = visibleReadyIds.length > 0 && visibleReadyIds.every((id) => selectedStudentIds.includes(id));
    const isBusy = uploading || confirming;

    const reset = () => {
        setFile(null);
        setPreview(null);
        setSelectedStudentIds([]);
        setFilter("all");
        setError(null);
    };

    const changeOpen = (nextOpen: boolean) => {
        if (isBusy) return;
        if (nextOpen && !open) reset();
        setOpen(nextOpen);
    };

    const stageWorkbook = async () => {
        if (!file) {
            setError("Choose the edited .xlsx registrar export first.");
            return;
        }

        const data = new FormData();
        data.append("file", file);
        setUploading(true);
        setError(null);

        try {
            const response = await axios.post<{ import: ImportPreview }>(store.url(), data, {
                headers: { Accept: "application/json" },
            });
            setPreview(response.data.import);
            setSelectedStudentIds(
                response.data.import.students
                    .filter((student) => student.status === "ready" && student.student_id !== null)
                    .map((student) => student.student_id!),
            );
        } catch (requestError) {
            setError(errorMessage(requestError));
        } finally {
            setUploading(false);
        }
    };

    const confirmUpdates = async () => {
        if (!preview || selectedStudentIds.length === 0) return;

        setConfirming(true);
        setError(null);

        try {
            const response = await axios.post<{ import: ImportPreview }>(confirm.url(preview.id), {
                student_ids: selectedStudentIds,
            });
            setPreview(response.data.import);
            toast.success(`${response.data.import.summary.applied_students} student record(s) updated.`);
            router.reload({ only: ["analytics", "quality", "generatedAt"] });
        } catch (requestError) {
            setError(errorMessage(requestError));
        } finally {
            setConfirming(false);
        }
    };

    const toggleStudent = (studentId: number, checked: boolean) => {
        setSelectedStudentIds((current) => (checked ? [...new Set([...current, studentId])] : current.filter((id) => id !== studentId)));
    };

    const toggleVisible = (checked: boolean) => {
        setSelectedStudentIds((current) =>
            checked ? [...new Set([...current, ...visibleReadyIds])] : current.filter((id) => !visibleReadyIds.includes(id)),
        );
    };

    return (
        <Dialog open={open} onOpenChange={changeOpen}>
            <DialogTrigger asChild>
                <Button variant="outline" size="sm">
                    <FileUp className="size-4" /> Import updates
                </Button>
            </DialogTrigger>
            <DialogContent className="flex max-h-[92dvh] max-w-[calc(100%-1rem)] flex-col gap-0 overflow-hidden p-0 sm:max-w-6xl">
                <DialogHeader className="border-border/70 border-b px-6 py-5 pr-12">
                    <div className="flex items-center gap-2.5">
                        <span className="flex size-9 items-center justify-center rounded-lg border border-emerald-500/20 bg-emerald-500/10 text-emerald-600">
                            <FileSpreadsheet className="size-4" />
                        </span>
                        <div>
                            <DialogTitle>{preview?.status === "completed" ? "Student records updated" : "Import student information"}</DialogTitle>
                            <DialogDescription className="mt-1">
                                {preview
                                    ? `${preview.filename} · signed registrar workbook`
                                    : "Upload an edited Enrollment Details sheet, review every change, then confirm the valid records."}
                            </DialogDescription>
                        </div>
                    </div>
                </DialogHeader>

                {!preview ? (
                    <div className="grid min-h-96 place-items-center p-6 sm:p-10">
                        <div className="w-full max-w-xl space-y-5">
                            <label className="border-border bg-muted/20 hover:bg-muted/35 flex cursor-pointer flex-col items-center gap-3 rounded-xl border border-dashed px-6 py-10 text-center transition-colors">
                                <span className="bg-background flex size-12 items-center justify-center rounded-xl border shadow-xs">
                                    <FileUp className="text-muted-foreground size-5" />
                                </span>
                                <span className="font-medium">Choose the completed registrar workbook</span>
                                <span className="text-muted-foreground max-w-md text-xs leading-5">
                                    Only app-generated .xlsx exports up to 10 MB are accepted. Empty cells are ignored and never erase existing data.
                                </span>
                                <Input
                                    type="file"
                                    accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                                    className="max-w-sm cursor-pointer"
                                    onChange={(event) => setFile(event.target.files?.[0] ?? null)}
                                />
                            </label>
                            <Alert className="border-sky-500/20 bg-sky-500/5">
                                <ShieldCheck />
                                <AlertTitle>Protected bulk update</AlertTitle>
                                <AlertDescription>
                                    Student identities and original values are signed. Changed or stale records are blocked while valid students can
                                    still be selected and updated.
                                </AlertDescription>
                            </Alert>
                            {error ? (
                                <Alert variant="destructive">
                                    <AlertTriangle />
                                    <AlertTitle>Workbook not accepted</AlertTitle>
                                    <AlertDescription>{error}</AlertDescription>
                                </Alert>
                            ) : null}
                        </div>
                    </div>
                ) : (
                    <>
                        <div className="border-border/70 grid grid-cols-2 border-b sm:grid-cols-4">
                            {[
                                [
                                    preview.status === "completed" ? "Updated" : "Ready",
                                    preview.status === "completed" ? preview.summary.applied_students : preview.summary.ready_students,
                                    "text-emerald-600",
                                ],
                                ["Needs attention", preview.summary.invalid_students, "text-rose-600"],
                                ["Field changes", preview.summary.changed_fields, "text-sky-600"],
                                ["Unclassified first-years", preview.summary.unclassified_first_year, "text-amber-600"],
                            ].map(([title, value, tone]) => (
                                <div
                                    key={String(title)}
                                    className="border-border/70 border-r px-5 py-4 last:border-r-0 even:border-r-0 sm:last:border-r-0 sm:even:border-r"
                                >
                                    <p className="text-muted-foreground text-[11px] font-medium">{title}</p>
                                    <p className={`mt-1 text-2xl font-semibold tracking-tight tabular-nums ${tone}`}>{value}</p>
                                </div>
                            ))}
                        </div>

                        <div className="border-border/70 flex flex-wrap items-center gap-2 border-b px-5 py-3">
                            {(
                                [
                                    ["all", "All"],
                                    ["ready", "Ready"],
                                    ["invalid", "Needs attention"],
                                    ["new_freshman", `New freshmen (${preview.summary.new_freshmen})`],
                                    ["continuing_first_year", `Continuing first-years (${preview.summary.continuing_first_year})`],
                                    ["unclassified", `Unclassified first-years (${preview.summary.unclassified_first_year})`],
                                ] as const
                            ).map(([value, title]) => (
                                <Button key={value} variant={filter === value ? "secondary" : "ghost"} size="xs" onClick={() => setFilter(value)}>
                                    {title}
                                </Button>
                            ))}
                        </div>

                        <ScrollArea className="min-h-0 flex-1">
                            <div className="min-w-[820px]">
                                <div className="bg-muted/45 text-muted-foreground sticky top-0 z-10 grid grid-cols-[44px_1.25fr_0.65fr_0.75fr_0.6fr_44px] items-center border-b px-4 py-2.5 text-[10px] font-semibold tracking-wide uppercase">
                                    <Checkbox
                                        aria-label="Select all visible valid students"
                                        checked={allVisibleSelected}
                                        disabled={preview.status === "completed" || visibleReadyIds.length === 0}
                                        onCheckedChange={(checked) => toggleVisible(checked === true)}
                                    />
                                    <span>Student</span>
                                    <span>Course</span>
                                    <span>First-year status</span>
                                    <span>Changes</span>
                                    <span />
                                </div>
                                {shownStudents.map((student) => (
                                    <details key={student.id} className="group border-border/70 border-b last:border-b-0">
                                        <summary className="hover:bg-muted/25 grid cursor-pointer list-none grid-cols-[44px_1.25fr_0.65fr_0.75fr_0.6fr_44px] items-center px-4 py-3.5 transition-colors">
                                            <span onClick={(event) => event.preventDefault()}>
                                                <Checkbox
                                                    aria-label={`Select ${student.student_name}`}
                                                    checked={student.student_id !== null && selectedStudentIds.includes(student.student_id)}
                                                    disabled={
                                                        preview.status === "completed" || student.status !== "ready" || student.student_id === null
                                                    }
                                                    onCheckedChange={(checked) =>
                                                        student.student_id !== null && toggleStudent(student.student_id, checked === true)
                                                    }
                                                />
                                            </span>
                                            <span className="min-w-0">
                                                <span className="block truncate text-sm font-medium">{student.student_name}</span>
                                                <span className="text-muted-foreground mt-0.5 block font-mono text-[10px]">
                                                    {student.student_number ?? "No student number"} · Excel row {student.excel_row}
                                                </span>
                                            </span>
                                            <span>
                                                <Badge variant="outline" className="font-mono text-[10px]">
                                                    {student.course_code ?? "—"}
                                                </Badge>
                                            </span>
                                            <span>
                                                <IntakeBadge student={student} />
                                            </span>
                                            <span className="flex items-center gap-2">
                                                <StudentStatus status={student.status} />
                                                <span className="text-muted-foreground text-xs tabular-nums">{student.changes.length}</span>
                                            </span>
                                            <ChevronDown className="text-muted-foreground size-4 transition-transform group-open:rotate-180" />
                                        </summary>
                                        <div className="bg-muted/15 border-border/70 border-t px-16 py-4">
                                            {student.errors.length > 0 ? (
                                                <div className="mb-3 space-y-1 text-xs text-rose-600">
                                                    {student.errors.map((message) => (
                                                        <p key={message} className="flex gap-2">
                                                            <XCircle className="mt-0.5 size-3.5 shrink-0" /> {message}
                                                        </p>
                                                    ))}
                                                </div>
                                            ) : null}
                                            {student.warnings.map((message) => (
                                                <p key={message} className="mb-3 flex gap-2 text-xs text-amber-600">
                                                    <AlertTriangle className="mt-0.5 size-3.5 shrink-0" /> {message}
                                                </p>
                                            ))}
                                            {student.changes.length > 0 ? (
                                                <div className="grid gap-2 lg:grid-cols-2">
                                                    {student.changes.map((change) => (
                                                        <div key={`${change.target}:${change.key}`} className="bg-background rounded-lg border p-3">
                                                            <p className="text-muted-foreground text-[10px] font-semibold tracking-wide uppercase">
                                                                {change.group} · {change.label}
                                                            </p>
                                                            <div className="mt-2 grid grid-cols-[1fr_16px_1fr] items-center gap-2 text-xs">
                                                                <span className="text-muted-foreground truncate line-through">
                                                                    {displayValue(change.old)}
                                                                </span>
                                                                <span aria-hidden>→</span>
                                                                <span className="truncate font-medium text-emerald-700 dark:text-emerald-300">
                                                                    {displayValue(change.new)}
                                                                </span>
                                                            </div>
                                                        </div>
                                                    ))}
                                                </div>
                                            ) : (
                                                <p className="text-muted-foreground text-xs">No applicable changes for this student.</p>
                                            )}
                                        </div>
                                    </details>
                                ))}
                                {shownStudents.length === 0 ? (
                                    <div className="text-muted-foreground grid min-h-52 place-items-center text-sm">
                                        No students match this filter.
                                    </div>
                                ) : null}
                            </div>
                        </ScrollArea>

                        {error ? (
                            <Alert variant="destructive" className="rounded-none border-x-0 border-b-0">
                                <AlertTriangle />
                                <AlertTitle>Update not completed</AlertTitle>
                                <AlertDescription>{error}</AlertDescription>
                            </Alert>
                        ) : null}
                    </>
                )}

                <DialogFooter className="border-border/70 border-t px-6 py-4">
                    {preview?.status === "review" && preview.summary.ready_students > selectedStudentIds.length ? (
                        <span className="text-muted-foreground mr-auto self-center text-xs">
                            {preview.summary.ready_students - selectedStudentIds.length} ready student(s) not selected will be skipped.
                        </span>
                    ) : null}
                    <Button variant="ghost" disabled={isBusy} onClick={() => changeOpen(false)}>
                        {preview?.status === "completed" ? "Done" : "Cancel"}
                    </Button>
                    {!preview ? (
                        <Button disabled={!file || uploading} onClick={stageWorkbook}>
                            {uploading ? <Loader2 className="size-4 animate-spin" /> : <ShieldCheck className="size-4" />}
                            Review workbook
                        </Button>
                    ) : preview.status === "review" ? (
                        <Button disabled={confirming || selectedStudentIds.length === 0} onClick={confirmUpdates}>
                            {confirming ? <Loader2 className="size-4 animate-spin" /> : <UserRoundCheck className="size-4" />}
                            Confirm {selectedStudentIds.length} student{selectedStudentIds.length === 1 ? "" : "s"}
                        </Button>
                    ) : (
                        <span className="flex items-center gap-2 self-center text-sm font-medium text-emerald-600">
                            <CheckCircle2 className="size-4" /> Confirmation complete
                        </span>
                    )}
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
