import { Button } from "@/components/ui/button";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { apply, approve, stage } from "@/routes/administrators/ai/curriculum-imports";
import * as React from "react";
import { toast } from "sonner";

type ImportRow = {
    sheet: string;
    row: number;
    code: string;
    title: string;
    units: number | null;
    lab_raw: string;
    lec_raw: string;
    prerequisites_raw: string;
    hours_per_week: string;
    year: number;
    semester: number;
    issues: string[];
};

type Draft = {
    id: string;
    filename: string;
    title: string;
    status: string;
    rows: ImportRow[];
    warnings: string[];
    candidates: { id: number; code: string; title: string; exact_title: boolean }[];
    departments: { id: number; name: string }[];
    course_types: { id: number; name: string }[];
    existing_subjects: { code: string; course_id: number | null }[];
    course_id: number | null;
    selection?: {
        mode: "new" | "existing";
        course_id?: number;
        code?: string;
        title?: string;
        department_id?: number;
        course_type_id?: number;
        curriculum_kind?: "program" | "tesda_qualification";
        duration_hours?: number;
        duration_years?: number;
        internship_hours?: number;
        bundled_qualifications?: string[];
        advanced_topics?: string;
        rows: ReviewRow[];
    } | null;
};

type ReviewRow = {
    skip: boolean;
    code: string;
    title: string;
    units: number;
    lecture: number;
    laboratory: number;
    hours_confirmed: boolean;
    prerequisites: string;
};

function errorMessage(error: unknown): string {
    if (error && typeof error === "object" && "message" in error) return String(error.message);
    return "Unable to process this import.";
}

async function post(url: string, body: BodyInit): Promise<Record<string, unknown>> {
    const token = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? "";
    const response = await fetch(url, {
        method: "POST",
        headers: {
            Accept: "application/json",
            "X-Requested-With": "XMLHttpRequest",
            "X-CSRF-TOKEN": token,
            ...(typeof body === "string" ? { "Content-Type": "application/json" } : {}),
        },
        body,
    });
    const result = await response.json();
    if (response.status === 419) throw new Error("Your session expired. Refresh the page and try again.");
    if (!response.ok)
        throw new Error(
            Object.values(result.errors ?? {})
                .flat()
                .join(" ") ||
                result.message ||
                "Import request failed.",
        );
    return result;
}

export function CurriculumImportReview({
    file,
    onClose,
    onStaged,
    onApplied,
}: {
    file: File | null;
    onClose: () => void;
    onStaged: (id: string, title: string) => void;
    onApplied: (courseId: number) => void;
}) {
    const [draft, setDraft] = React.useState<Draft | null>(null);
    const [rows, setRows] = React.useState<ReviewRow[]>([]);
    const [mode, setMode] = React.useState<"new" | "existing">("new");
    const [courseId, setCourseId] = React.useState("");
    const [code, setCode] = React.useState("");
    const [title, setTitle] = React.useState("");
    const [departmentId, setDepartmentId] = React.useState("");
    const [courseTypeId, setCourseTypeId] = React.useState("");
    const [kind, setKind] = React.useState<"program" | "tesda_qualification">("program");
    const [durationHours, setDurationHours] = React.useState("");
    const [durationYears, setDurationYears] = React.useState("");
    const [internshipHours, setInternshipHours] = React.useState("");
    const [qualifications, setQualifications] = React.useState("");
    const [advancedTopics, setAdvancedTopics] = React.useState("");
    const [working, setWorking] = React.useState(false);
    const [approved, setApproved] = React.useState(false);
    const [stagingError, setStagingError] = React.useState<string | null>(null);
    const matched = (code: string) => draft?.existing_subjects.find((item) => item.code.toUpperCase() === code.trim().toUpperCase());
    const createdCount = rows.filter((row) => !row.skip && !matched(row.code)).length;
    const updatedCount = rows.filter((row) => !row.skip && mode === "existing" && matched(row.code)?.course_id === Number(courseId)).length;
    const conflictCount = rows.filter(
        (row) => !row.skip && matched(row.code) && (mode !== "existing" || matched(row.code)?.course_id !== Number(courseId)),
    ).length;
    const onStagedRef = React.useRef(onStaged);
    onStagedRef.current = onStaged;

    React.useEffect(() => {
        if (!file) return;
        let active = true;
        setDraft(null);
        setApproved(false);
        setStagingError(null);
        setWorking(true);
        const form = new FormData();
        form.append("file", file);
        post(stage.url(), form)
            .then((result) => {
                if (!active) return;
                const parsed = result as Draft;
                setDraft(parsed);
                setTitle(parsed.title);
                setMode("new");
                setCourseId("");
                setCode("");
                setDepartmentId("");
                setCourseTypeId("");
                setKind(/\b(?:PQF\s*Level\s*5|Diploma)\b/i.test(parsed.title) ? "tesda_qualification" : "program");
                setDurationHours("");
                setDurationYears("");
                setInternshipHours(
                    parsed.rows
                        .find((row) => /internship\s*\((\d+)\s*hours?\)/i.test(row.title))
                        ?.title.match(/internship\s*\((\d+)\s*hours?\)/i)?.[1] ?? "",
                );
                setQualifications("");
                setAdvancedTopics("");
                if (parsed.selection) {
                    setMode(parsed.selection.mode);
                    setCourseId(String(parsed.selection.course_id ?? ""));
                    setCode(parsed.selection.code ?? "");
                    setTitle(parsed.selection.title ?? parsed.title);
                    setDepartmentId(String(parsed.selection.department_id ?? ""));
                    setCourseTypeId(String(parsed.selection.course_type_id ?? ""));
                    setKind(parsed.selection.curriculum_kind ?? "program");
                    setDurationHours(String(parsed.selection.duration_hours ?? ""));
                    setDurationYears(String(parsed.selection.duration_years ?? ""));
                    setInternshipHours(String(parsed.selection.internship_hours ?? ""));
                    setQualifications((parsed.selection.bundled_qualifications ?? []).join(", "));
                    setAdvancedTopics(parsed.selection.advanced_topics ?? "");
                    setRows(parsed.selection.rows.map((row) => ({ ...row, hours_confirmed: row.hours_confirmed ?? false })));
                    setApproved(parsed.status === "approved");
                    onStagedRef.current(parsed.id, parsed.title);
                    return;
                }
                const exact = parsed.candidates.filter((candidate) => candidate.exact_title);
                if (exact.length === 1) {
                    setMode("existing");
                    setCourseId(String(exact[0].id));
                }
                setRows(
                    parsed.rows.map((row) => ({
                        skip: row.units === null || /(?:internship|capstone)/i.test(row.title),
                        code: row.code,
                        title: row.title,
                        units: row.units ?? 0,
                        lecture: 0,
                        laboratory: 0,
                        hours_confirmed: false,
                        prerequisites: row.prerequisites_raw,
                    })),
                );
                onStagedRef.current(parsed.id, parsed.title);
            })
            .catch((error) => {
                if (active) {
                    setStagingError(errorMessage(error));
                    toast.error(errorMessage(error));
                }
            })
            .finally(() => active && setWorking(false));
        return () => {
            active = false;
        };
    }, [file]);

    function updateRow(index: number, patch: Partial<ReviewRow>) {
        setRows((current) => current.map((row, at) => (at === index ? { ...row, ...patch } : row)));
        setApproved(false);
    }

    async function confirm() {
        if (!draft) return;
        setWorking(true);
        try {
            const target =
                mode === "existing"
                    ? { mode, course_id: Number(courseId) }
                    : {
                          mode,
                          code,
                          title,
                          department_id: Number(departmentId),
                          course_type_id: Number(courseTypeId),
                          curriculum_kind: kind,
                          ...(kind === "tesda_qualification"
                              ? {
                                    duration_hours: durationHours ? Number(durationHours) : null,
                                    duration_years: durationYears ? Number(durationYears) : null,
                                    internship_hours: internshipHours ? Number(internshipHours) : null,
                                    bundled_qualifications: qualifications
                                        .split(/[,\n]+/)
                                        .map((value) => value.trim())
                                        .filter(Boolean),
                                    advanced_topics: advancedTopics,
                                }
                              : {}),
                      };
            await post(approve.url({ import: draft.id }), JSON.stringify({ ...target, rows }));
            setApproved(true);
            toast.success("Draft approved. Apply it to write the reviewed records.");
        } catch (error) {
            toast.error(errorMessage(error));
        } finally {
            setWorking(false);
        }
    }

    async function commit() {
        if (!draft) return;
        setWorking(true);
        try {
            const result = await post(apply.url({ import: draft.id }), JSON.stringify({}));
            toast.success(
                result.already_applied
                    ? "This curriculum import was already applied."
                    : `Curriculum saved: ${result.created ?? 0} created, ${result.updated ?? 0} updated.`,
            );
            onApplied(Number(result.course_id));
            onClose();
        } catch (error) {
            toast.error(errorMessage(error));
        } finally {
            setWorking(false);
        }
    }

    return (
        <Dialog
            open={Boolean(file)}
            onOpenChange={(open) => {
                if (!open && !working) onClose();
            }}
        >
            <DialogContent className="flex max-h-[90vh] max-w-[min(72rem,calc(100vw-2rem))] flex-col gap-4 overflow-hidden">
                <DialogHeader>
                    <DialogTitle>Review curriculum import</DialogTitle>
                    <DialogDescription>
                        {draft ? `${draft.filename} · ${draft.rows.length} subject rows · no records changed yet` : "Reading workbook…"}
                    </DialogDescription>
                </DialogHeader>
                {stagingError && (
                    <p role="alert" className="text-destructive text-sm">
                        {stagingError}
                    </p>
                )}
                {draft && (
                    <div className="min-h-0 space-y-4 overflow-y-auto pr-2">
                        <div className="bg-muted/50 rounded-lg p-3 text-sm">
                            <p className="font-medium">{draft.title || "Program title needs review"}</p>
                            <p className="text-muted-foreground mt-1">
                                This spreadsheet does not reliably define lecture and laboratory hours. Enter verified hours per subject. Resolve
                                duplicate codes and prerequisites before approval.
                            </p>
                            <p className="mt-2 text-xs font-medium tabular-nums">
                                {createdCount} to create · {updatedCount} to update · {conflictCount} code conflicts ·{" "}
                                {rows.filter((row) => row.skip).length} skipped
                            </p>
                            {draft.warnings.map((warning) => (
                                <p key={warning} className="text-destructive mt-1">
                                    {warning}
                                </p>
                            ))}
                        </div>
                        <fieldset className="grid gap-3 sm:grid-cols-2">
                            <legend className="mb-2 text-sm font-medium">Target program</legend>
                            <label className="text-sm">
                                <input
                                    type="radio"
                                    checked={mode === "existing"}
                                    onChange={() => {
                                        setMode("existing");
                                        setApproved(false);
                                    }}
                                />{" "}
                                Update existing
                            </label>
                            <label className="text-sm">
                                <input
                                    type="radio"
                                    checked={mode === "new"}
                                    onChange={() => {
                                        setMode("new");
                                        setApproved(false);
                                    }}
                                />{" "}
                                Create new
                            </label>
                            {mode === "existing" ? (
                                <select
                                    aria-label="Existing program"
                                    value={courseId}
                                    onChange={(event) => {
                                        setCourseId(event.target.value);
                                        setApproved(false);
                                    }}
                                    className="border-input bg-background col-span-full rounded-md border p-2 text-sm"
                                >
                                    <option value="">Select program</option>
                                    {draft.candidates.map((candidate) => (
                                        <option key={candidate.id} value={candidate.id}>
                                            {candidate.exact_title ? "Suggested · " : ""}
                                            {candidate.code} — {candidate.title}
                                        </option>
                                    ))}
                                </select>
                            ) : (
                                <div className="col-span-full grid gap-2 sm:grid-cols-2">
                                    <input
                                        aria-label="New program code"
                                        placeholder="Program code"
                                        value={code}
                                        onChange={(e) => {
                                            setCode(e.target.value);
                                            setApproved(false);
                                        }}
                                        className="border-input bg-background rounded-md border p-2 text-sm"
                                    />
                                    <input
                                        aria-label="New program title"
                                        placeholder="Program title"
                                        value={title}
                                        onChange={(e) => {
                                            setTitle(e.target.value);
                                            setApproved(false);
                                        }}
                                        className="border-input bg-background rounded-md border p-2 text-sm"
                                    />
                                    <select
                                        aria-label="Department"
                                        value={departmentId}
                                        onChange={(e) => {
                                            setDepartmentId(e.target.value);
                                            setApproved(false);
                                        }}
                                        className="border-input bg-background rounded-md border p-2 text-sm"
                                    >
                                        <option value="">Select department</option>
                                        {draft.departments.map((department) => (
                                            <option key={department.id} value={department.id}>
                                                {department.name}
                                            </option>
                                        ))}
                                    </select>
                                    <select
                                        aria-label="Course type"
                                        value={courseTypeId}
                                        onChange={(e) => {
                                            setCourseTypeId(e.target.value);
                                            setApproved(false);
                                        }}
                                        className="border-input bg-background rounded-md border p-2 text-sm"
                                    >
                                        <option value="">Select course type</option>
                                        {draft.course_types.map((type) => (
                                            <option key={type.id} value={type.id}>
                                                {type.name}
                                            </option>
                                        ))}
                                    </select>
                                    <select
                                        aria-label="Program category"
                                        value={kind}
                                        onChange={(e) => {
                                            setKind(e.target.value as "program" | "tesda_qualification");
                                            setApproved(false);
                                        }}
                                        className="border-input bg-background rounded-md border p-2 text-sm"
                                    >
                                        <option value="program">Academic program</option>
                                        <option value="tesda_qualification">TESDA diploma</option>
                                    </select>
                                    {kind === "tesda_qualification" && (
                                        <>
                                            <input
                                                type="number"
                                                aria-label="Program duration hours"
                                                placeholder="Duration (hours)"
                                                value={durationHours}
                                                onChange={(e) => {
                                                    setDurationHours(e.target.value);
                                                    setApproved(false);
                                                }}
                                                className="border-input bg-background rounded-md border p-2 text-sm"
                                            />
                                            <input
                                                type="number"
                                                step="0.5"
                                                aria-label="Program duration years"
                                                placeholder="Duration (years)"
                                                value={durationYears}
                                                onChange={(e) => {
                                                    setDurationYears(e.target.value);
                                                    setApproved(false);
                                                }}
                                                className="border-input bg-background rounded-md border p-2 text-sm"
                                            />
                                            <input
                                                type="number"
                                                aria-label="Internship hours"
                                                placeholder="Internship hours"
                                                value={internshipHours}
                                                onChange={(e) => {
                                                    setInternshipHours(e.target.value);
                                                    setApproved(false);
                                                }}
                                                className="border-input bg-background rounded-md border p-2 text-sm"
                                            />
                                            <input
                                                aria-label="Bundled qualifications"
                                                placeholder="Bundled qualifications (comma separated)"
                                                value={qualifications}
                                                onChange={(e) => {
                                                    setQualifications(e.target.value);
                                                    setApproved(false);
                                                }}
                                                className="border-input bg-background rounded-md border p-2 text-sm"
                                            />
                                            <textarea
                                                aria-label="Advanced topics"
                                                placeholder="Advanced topics"
                                                value={advancedTopics}
                                                onChange={(e) => {
                                                    setAdvancedTopics(e.target.value);
                                                    setApproved(false);
                                                }}
                                                className="border-input bg-background col-span-full rounded-md border p-2 text-sm"
                                            />
                                        </>
                                    )}
                                </div>
                            )}
                        </fieldset>
                        <div className="overflow-x-auto rounded-lg border">
                            <table className="w-full min-w-[1050px] text-left text-xs">
                                <thead className="bg-muted/60">
                                    <tr>
                                        {[
                                            "Use",
                                            "Source",
                                            "Code",
                                            "Title",
                                            "Units",
                                            "Lecture",
                                            "Lab",
                                            "Hours checked",
                                            "Prerequisites",
                                            "Source details",
                                        ].map((head) => (
                                            <th key={head} className="p-2 font-medium">
                                                {head}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody>
                                    {draft.rows.map((raw, index) => (
                                        <tr key={`${raw.sheet}-${raw.row}`} className="border-t align-top">
                                            <td className="p-2">
                                                <input
                                                    type="checkbox"
                                                    aria-label={`Include row ${raw.row}`}
                                                    checked={!rows[index]?.skip}
                                                    onChange={(e) => updateRow(index, { skip: !e.target.checked })}
                                                />
                                            </td>
                                            <td className="p-2 whitespace-nowrap">
                                                {raw.sheet}!{raw.row}
                                            </td>
                                            <td className="p-2">
                                                <input
                                                    aria-label={`Code row ${raw.row}`}
                                                    className="border-input bg-background w-28 rounded border p-1"
                                                    value={rows[index]?.code ?? ""}
                                                    onChange={(e) => updateRow(index, { code: e.target.value })}
                                                />
                                            </td>
                                            <td className="p-2">
                                                <input
                                                    aria-label={`Title row ${raw.row}`}
                                                    className="border-input bg-background w-52 rounded border p-1"
                                                    value={rows[index]?.title ?? ""}
                                                    onChange={(e) => updateRow(index, { title: e.target.value })}
                                                />
                                            </td>
                                            {(["units", "lecture", "laboratory"] as const).map((field) => (
                                                <td key={field} className="p-2">
                                                    <input
                                                        type="number"
                                                        min={0}
                                                        max={field === "units" ? 12 : 40}
                                                        aria-label={`${field} row ${raw.row}`}
                                                        className="border-input bg-background w-16 rounded border p-1 tabular-nums"
                                                        value={rows[index]?.[field] ?? 0}
                                                        onChange={(e) => updateRow(index, { [field]: Number(e.target.value) })}
                                                    />
                                                </td>
                                            ))}
                                            <td className="p-2">
                                                <input
                                                    type="checkbox"
                                                    aria-label={`Hours confirmed row ${raw.row}`}
                                                    checked={rows[index]?.hours_confirmed ?? false}
                                                    onChange={(e) => updateRow(index, { hours_confirmed: e.target.checked })}
                                                />
                                            </td>
                                            <td className="p-2">
                                                <input
                                                    aria-label={`Prerequisites row ${raw.row}`}
                                                    className="border-input bg-background w-32 rounded border p-1"
                                                    value={rows[index]?.prerequisites ?? ""}
                                                    onChange={(e) => updateRow(index, { prerequisites: e.target.value })}
                                                />
                                            </td>
                                            <td className="text-muted-foreground p-2">
                                                Lab {raw.lab_raw || "—"} · Lec {raw.lec_raw || "—"} · Hrs/week {raw.hours_per_week || "—"}
                                                {raw.issues.map((issue) => (
                                                    <span key={issue} className="text-destructive block">
                                                        {issue}
                                                    </span>
                                                ))}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}
                <DialogFooter>
                    <Button variant="outline" onClick={onClose} disabled={working}>
                        Close
                    </Button>
                    <Button onClick={confirm} disabled={working || !draft || approved}>
                        {working ? "Processing…" : "Approve reviewed draft"}
                    </Button>
                    <Button onClick={commit} disabled={working || !approved}>
                        Apply to curriculum
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
