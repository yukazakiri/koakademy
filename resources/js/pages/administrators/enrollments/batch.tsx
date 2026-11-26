import {
    preview as batchPreviewRoute,
    store as batchStoreRoute,
} from "@/actions/App/Http/Controllers/AdministratorBatchEnrollmentController";
import AdminLayout from "@/components/administrators/admin-layout";
import { Alert, AlertDescription, AlertTitle } from "@/components/reui/alert";
import { Badge } from "@/components/reui/badge";
import { Frame, FrameDescription, FrameHeader, FramePanel, FrameTitle } from "@/components/reui/frame";
import { Button, buttonVariants } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Switch } from "@/components/ui/switch";
import { Textarea } from "@/components/ui/textarea";
import { cn } from "@/lib/utils";
import type { User } from "@/types/user";
import { Head, Link, usePage } from "@inertiajs/react";
import axios from "axios";
import {
    AlertTriangle,
    ArrowLeft,
    ArrowRight,
    BookOpen,
    CheckCircle2,
    Layers,
    Loader2,
    Search,
    ShieldAlert,
    Sparkles,
    Users,
    XCircle,
} from "lucide-react";
import { useMemo, useState } from "react";
import { toast } from "sonner";
import { route } from "ziggy-js";
import { formatMoney } from "./enrollment-utils";
import type { Branding, EnrollmentPeriodSummary } from "./types";

interface BatchPageProps {
    user: User;
    courses: Array<{ id: number; code: string; title: string | null }>;
    settings: {
        currentSemester: number;
        currentSchoolYear: string;
        availableSemesters: Array<{ value: number; label: string }>;
    };
    enrollment_period?: EnrollmentPeriodSummary;
}

interface PreviewStudent {
    id: number;
    student_number: string | number;
    full_name: string;
    email: string | null;
    academic_year: number | null;
    status: string;
    active_holds_count: number;
    blocking_holds_count: number;
    hold_offices: string[];
}

interface PreviewSubject {
    id: number;
    code: string;
    title: string;
    lecture_units: number;
    laboratory_units: number;
    total_units: number;
    lecture_fee: number;
    laboratory_fee: number;
    available_classes_count: number;
}

interface PreviewSection {
    section: string;
    matched_subjects_count: number;
    total_subjects_count: number;
    classes_count: number;
    min_available_slots: number | null;
    has_full_class: boolean;
}

interface BatchPreviewPayload {
    course: { id: number; code: string; title: string | null; miscellaneous_fee: number };
    term: { school_year: string; semester: number };
    students: PreviewStudent[];
    subjects: PreviewSubject[];
    available_sections: PreviewSection[];
    estimated_tuition_per_student: number;
    late_fee_applied: number;
    enrollment_period: EnrollmentPeriodSummary;
}

interface BatchResultRow {
    student_id: number;
    student_name: string;
    status: "enrolled" | "skipped" | "failed";
    enrollment_id: number | null;
    assigned_classes_count: number;
    unassigned_classes_count: number;
    message: string;
}

interface BatchExecutionResult {
    enrolled_count: number;
    skipped_count: number;
    failed_count: number;
    results: BatchResultRow[];
}

const YEAR_LEVELS = [
    { value: "1", label: "1st Year" },
    { value: "2", label: "2nd Year" },
    { value: "3", label: "3rd Year" },
    { value: "4", label: "4th Year" },
    { value: "5", label: "5th Year" },
];

export default function AdministratorBatchEnrollmentPage({ user, courses, settings, enrollment_period: initialPeriod }: BatchPageProps) {
    const { props } = usePage<{ branding?: Branding }>();
    const currency = props.branding?.currency || "PHP";

    const [step, setStep] = useState<1 | 2 | 3>(1);
    const [courseId, setCourseId] = useState<string>(courses[0] ? String(courses[0].id) : "");
    const [sourceYearLevel, setSourceYearLevel] = useState<string>("1");
    const [targetYearLevel, setTargetYearLevel] = useState<string>("2");
    const [targetSemester, setTargetSemester] = useState<string>(String(settings.currentSemester || 1));

    const [loadingPreview, setLoadingPreview] = useState(false);
    const [preview, setPreview] = useState<BatchPreviewPayload | null>(null);

    // Step 2 selections
    const [studentSearch, setStudentSearch] = useState("");
    const [selectedStudentIds, setSelectedStudentIds] = useState<number[]>([]);
    const [selectedSubjectIds, setSelectedSubjectIds] = useState<number[]>([]);
    const [blockSection, setBlockSection] = useState<string>("auto");
    const [autoAssignClasses, setAutoAssignClasses] = useState(true);
    const [promoteYearLevel, setPromoteYearLevel] = useState(true);
    const [forceOverload, setForceOverload] = useState(false);
    const [overrideWindow, setOverrideWindow] = useState(false);
    const [overrideHolds, setOverrideHolds] = useState(false);
    const [overrideHoldsReason, setOverrideHoldsReason] = useState("");

    const [submitting, setSubmitting] = useState(false);
    const [executionResult, setExecutionResult] = useState<BatchExecutionResult | null>(null);

    const activePeriod = preview?.enrollment_period ?? initialPeriod;

    const loadPreview = async () => {
        if (!courseId) {
            toast.error("Choose a course/program first.");
            return;
        }
        setLoadingPreview(true);
        try {
            const response = await axios.get<BatchPreviewPayload>(batchPreviewRoute.url(), {
                params: {
                    course_id: Number(courseId),
                    source_year_level: Number(sourceYearLevel),
                    target_year_level: Number(targetYearLevel),
                    target_semester: Number(targetSemester),
                    school_year: settings.currentSchoolYear,
                },
            });
            const data = response.data;
            setPreview(data);
            setSelectedStudentIds(data.students.filter((s) => s.blocking_holds_count === 0).map((s) => s.id));
            setSelectedSubjectIds(data.subjects.map((s) => s.id));
            setBlockSection(data.available_sections[0]?.section ?? "auto");
            setExecutionResult(null);
            setStep(2);
        } catch {
            toast.error("Failed to load cohort candidates and curriculum block.");
        } finally {
            setLoadingPreview(false);
        }
    };

    const filteredStudents = useMemo(() => {
        if (!preview) return [];
        const needle = studentSearch.trim().toLowerCase();
        if (!needle) return preview.students;
        return preview.students.filter(
            (s) =>
                s.full_name.toLowerCase().includes(needle) ||
                String(s.student_number).toLowerCase().includes(needle) ||
                (s.email ?? "").toLowerCase().includes(needle),
        );
    }, [preview, studentSearch]);

    const selectedBlockedCount = useMemo(() => {
        if (!preview) return 0;
        const selectedSet = new Set(selectedStudentIds);
        return preview.students.filter((s) => selectedSet.has(s.id) && s.blocking_holds_count > 0).length;
    }, [preview, selectedStudentIds]);

    const selectedUnitsTotal = useMemo(() => {
        if (!preview) return 0;
        const selectedSet = new Set(selectedSubjectIds);
        return preview.subjects.filter((s) => selectedSet.has(s.id)).reduce((sum, s) => sum + s.total_units, 0);
    }, [preview, selectedSubjectIds]);

    const toggleStudent = (id: number) => {
        setSelectedStudentIds((prev) => (prev.includes(id) ? prev.filter((item) => item !== id) : [...prev, id]));
    };

    const toggleSubject = (id: number) => {
        setSelectedSubjectIds((prev) => (prev.includes(id) ? prev.filter((item) => item !== id) : [...prev, id]));
    };

    const handleExecuteBatch = async () => {
        if (!preview || selectedStudentIds.length === 0 || selectedSubjectIds.length === 0) {
            toast.error("Select at least one student and one curriculum subject.");
            return;
        }
        if (selectedBlockedCount > 0 && overrideHolds && overrideHoldsReason.trim().length < 5) {
            toast.error("Enter an audit justification to override student holds.");
            return;
        }

        setSubmitting(true);
        try {
            const response = await axios.post<BatchExecutionResult>(batchStoreRoute.url(), {
                course_id: preview.course.id,
                target_year_level: Number(targetYearLevel),
                target_semester: Number(targetSemester),
                school_year: preview.term.school_year,
                student_ids: selectedStudentIds,
                subject_ids: selectedSubjectIds,
                block_section: blockSection === "auto" ? null : blockSection,
                auto_assign_classes: autoAssignClasses,
                force_overload: forceOverload,
                promote_student_year_level: promoteYearLevel,
                override_window: overrideWindow,
                override_holds: overrideHolds,
                override_holds_reason: overrideHolds ? overrideHoldsReason.trim() : null,
            });
            setExecutionResult(response.data);
            setStep(3);
            toast.success(`Batch enrollment completed: ${response.data.enrolled_count} enrolled.`);
        } catch (error) {
            if (axios.isAxiosError(error) && typeof error.response?.data?.message === "string") {
                toast.error(error.response.data.message);
            } else {
                toast.error("Batch enrollment failed.");
            }
        } finally {
            setSubmitting(false);
        }
    };

    return (
        <AdminLayout user={user} title="Batch Cohort Enrollment">
            <Head title="Administrators • Batch Cohort Enrollment" />

            <div className="space-y-4 pb-10">
                <header className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-2.5">
                        <Link
                            href={route("administrators.enrollments.index")}
                            className={buttonVariants({ variant: "ghost", size: "icon-sm", className: "size-8" })}
                            title="Back to Enrollments"
                        >
                            <ArrowLeft className="size-4" />
                        </Link>
                        <div>
                            <div className="flex flex-wrap items-center gap-2">
                                <h1 className="text-2xl font-semibold tracking-[-0.025em]">Batch / Block-Section Enrollment</h1>
                                <Badge variant="outline" radius="full" size="lg" className="text-muted-foreground font-normal">
                                    {settings.currentSchoolYear} · Sem {targetSemester}
                                </Badge>
                            </div>
                            <p className="text-muted-foreground text-xs">
                                Roll continuing student cohorts forward into the next term and auto-assign their curriculum block and section.
                            </p>
                        </div>
                    </div>

                    {/* Minimalist 3-step indicator */}
                    <div className="border-border/80 bg-muted/40 inline-flex items-center gap-1 rounded-lg border p-1 text-xs">
                        {[
                            { num: 1 as const, label: "1. Cohort" },
                            { num: 2 as const, label: "2. Block & Students" },
                            { num: 3 as const, label: "3. Results" },
                        ].map((s) => (
                            <button
                                key={s.num}
                                type="button"
                                disabled={s.num === 2 && !preview}
                                onClick={() => {
                                    if (s.num === 1 || (s.num === 2 && preview) || (s.num === 3 && executionResult)) {
                                        setStep(s.num);
                                    }
                                }}
                                className={cn(
                                    "rounded-md px-2.5 py-1 font-medium transition-colors disabled:opacity-40",
                                    step === s.num ? "bg-background text-foreground shadow-xs" : "text-muted-foreground hover:text-foreground",
                                )}
                            >
                                {s.label}
                            </button>
                        ))}
                    </div>
                </header>

                {activePeriod?.configured && (!activePeriod.can_enroll || activePeriod.is_late) && (
                    <Alert variant={!activePeriod.can_enroll ? "destructive" : "warning"}>
                        <AlertTriangle className="size-4" />
                        <AlertTitle>{activePeriod.phase_label}</AlertTitle>
                        <AlertDescription className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <span className="text-xs">{activePeriod.summary_text}</span>
                            {!activePeriod.can_enroll && activePeriod.enforce_windows && (
                                <label className="flex items-center gap-2 text-xs font-medium">
                                    <Switch checked={overrideWindow} onCheckedChange={setOverrideWindow} />
                                    Override closed window
                                </label>
                            )}
                        </AlertDescription>
                    </Alert>
                )}

                {/* STEP 1: COHORT & TARGET TERM */}
                {step === 1 && (
                    <Frame spacing="sm">
                        <FrameHeader>
                            <FrameTitle className="text-sm font-semibold">Select Cohort &amp; Target Curriculum Block</FrameTitle>
                            <FrameDescription className="text-xs">
                                Choose the program, source year level of continuing students, and the target curriculum block to roll them into.
                            </FrameDescription>
                        </FrameHeader>
                        <FramePanel className="space-y-4">
                            <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
                                <div className="space-y-1.5 md:col-span-2">
                                    <Label className="text-xs">Program / Course</Label>
                                    <Select value={courseId} onValueChange={setCourseId}>
                                        <SelectTrigger className="h-9 text-xs">
                                            <SelectValue placeholder="Choose program" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {courses.map((course) => (
                                                <SelectItem key={course.id} value={String(course.id)}>
                                                    {course.code} — {course.title ?? "Untitled Program"}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div className="space-y-1.5">
                                    <Label className="text-xs">Current / Source Year Level</Label>
                                    <Select
                                        value={sourceYearLevel}
                                        onValueChange={(v) => {
                                            setSourceYearLevel(v);
                                            const next = Math.min(5, Number(v) + (Number(targetSemester) === 1 ? 1 : 0));
                                            setTargetYearLevel(String(next));
                                        }}
                                    >
                                        <SelectTrigger className="h-9 text-xs">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {YEAR_LEVELS.map((yl) => (
                                                <SelectItem key={yl.value} value={yl.value}>
                                                    {yl.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div className="space-y-1.5">
                                    <Label className="text-xs">Target Year Level</Label>
                                    <Select value={targetYearLevel} onValueChange={setTargetYearLevel}>
                                        <SelectTrigger className="h-9 text-xs">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {YEAR_LEVELS.map((yl) => (
                                                <SelectItem key={yl.value} value={yl.value}>
                                                    {yl.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>

                            <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                                <div className="space-y-1.5">
                                    <Label className="text-xs">Target Semester</Label>
                                    <Select value={targetSemester} onValueChange={setTargetSemester}>
                                        <SelectTrigger className="h-9 text-xs">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="1">1st Semester</SelectItem>
                                            <SelectItem value="2">2nd Semester</SelectItem>
                                            <SelectItem value="3">Summer / Term 3</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="space-y-1.5 md:col-span-2">
                                    <Label className="text-xs">Target School Year</Label>
                                    <Input value={settings.currentSchoolYear} readOnly className="bg-muted/40 h-9 text-xs" />
                                </div>
                            </div>

                            <div className="flex justify-end pt-2">
                                <Button size="sm" className="gap-1.5" disabled={loadingPreview || !courseId} onClick={() => void loadPreview()}>
                                    {loadingPreview ? <Loader2 className="size-3.5 animate-spin" /> : <Sparkles className="size-3.5" />}
                                    Load cohort preview
                                    <ArrowRight className="size-3.5" />
                                </Button>
                            </div>
                        </FramePanel>
                    </Frame>
                )}

                {/* STEP 2: REVIEW STUDENTS, CURRICULUM BLOCK & SECTION */}
                {step === 2 && preview && (
                    <div className="grid grid-cols-1 gap-4 lg:grid-cols-12">
                        {/* Left: Eligible Continuing Students */}
                        <div className="space-y-4 lg:col-span-7">
                            <Frame spacing="sm">
                                <FrameHeader className="flex-row items-center justify-between gap-2">
                                    <div>
                                        <FrameTitle className="flex items-center gap-1.5 text-sm font-semibold">
                                            <Users className="size-4" />
                                            Eligible Students ({selectedStudentIds.length}/{preview.students.length})
                                        </FrameTitle>
                                        <FrameDescription className="text-xs">
                                            Students in {preview.course.code} (Year {sourceYearLevel}) not yet enrolled in {preview.term.school_year}{" "}
                                            Sem {preview.term.semester}.
                                        </FrameDescription>
                                    </div>
                                    <div className="flex items-center gap-1.5">
                                        <Button
                                            type="button"
                                            size="xs"
                                            variant="outline"
                                            onClick={() =>
                                                setSelectedStudentIds(
                                                    preview.students.filter((s) => s.blocking_holds_count === 0).map((s) => s.id),
                                                )
                                            }
                                        >
                                            Clear holds only
                                        </Button>
                                        <Button
                                            type="button"
                                            size="xs"
                                            variant="ghost"
                                            onClick={() =>
                                                setSelectedStudentIds(
                                                    selectedStudentIds.length === preview.students.length ? [] : preview.students.map((s) => s.id),
                                                )
                                            }
                                        >
                                            {selectedStudentIds.length === preview.students.length ? "Clear all" : "Select all"}
                                        </Button>
                                    </div>
                                </FrameHeader>
                                <FramePanel className="space-y-3 p-3">
                                    <div className="relative">
                                        <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2" />
                                        <Input
                                            value={studentSearch}
                                            onChange={(e) => setStudentSearch(e.target.value)}
                                            placeholder="Filter cohort students..."
                                            className="h-8 pl-8 text-xs"
                                        />
                                    </div>

                                    {filteredStudents.length === 0 ? (
                                        <div className="py-10 text-center">
                                            <p className="text-sm font-medium">No eligible continuing students found</p>
                                            <p className="text-muted-foreground text-xs">
                                                All students in this year level may already be enrolled for this term.
                                            </p>
                                        </div>
                                    ) : (
                                        <div className="max-h-[420px] divide-y overflow-y-auto rounded-md border">
                                            {filteredStudents.map((student) => {
                                                const checked = selectedStudentIds.includes(student.id);
                                                return (
                                                    <label
                                                        key={student.id}
                                                        className={cn(
                                                            "hover:bg-muted/40 flex cursor-pointer items-center justify-between gap-3 px-3 py-2.5 text-xs",
                                                            checked && "bg-primary/[0.03]",
                                                        )}
                                                    >
                                                        <div className="flex min-w-0 items-center gap-2.5">
                                                            <Checkbox checked={checked} onCheckedChange={() => toggleStudent(student.id)} />
                                                            <div className="min-w-0">
                                                                <p className="truncate font-semibold">{student.full_name}</p>
                                                                <p className="text-muted-foreground font-mono text-[11px]">
                                                                    ID {student.student_number}
                                                                    {student.email ? ` · ${student.email}` : ""}
                                                                </p>
                                                            </div>
                                                        </div>
                                                        <div className="flex shrink-0 items-center gap-1.5">
                                                            {student.blocking_holds_count > 0 ? (
                                                                <Badge variant="destructive-light" size="xs" radius="full" className="gap-1">
                                                                    <ShieldAlert className="size-3" />
                                                                    {student.blocking_holds_count} Hold
                                                                </Badge>
                                                            ) : (
                                                                <Badge variant="success-light" size="xs" radius="full">
                                                                    Cleared
                                                                </Badge>
                                                            )}
                                                        </div>
                                                    </label>
                                                );
                                            })}
                                        </div>
                                    )}
                                </FramePanel>
                            </Frame>
                        </div>

                        {/* Right: Curriculum Block, Section & Execution Options */}
                        <div className="space-y-4 lg:col-span-5">
                            <Frame spacing="sm">
                                <FrameHeader>
                                    <FrameTitle className="flex items-center gap-1.5 text-sm font-semibold">
                                        <BookOpen className="size-4" />
                                        Curriculum Block ({selectedSubjectIds.length} subjects · {selectedUnitsTotal} u)
                                    </FrameTitle>
                                    <FrameDescription className="text-xs">
                                        Year {targetYearLevel} · Sem {targetSemester} subjects · Est.{" "}
                                        {formatMoney(preview.estimated_tuition_per_student, currency)}/student
                                    </FrameDescription>
                                </FrameHeader>
                                <FramePanel className="space-y-3 p-3">
                                    {preview.subjects.length === 0 ? (
                                        <p className="text-muted-foreground py-4 text-center text-xs">
                                            No curriculum subjects found for Year {targetYearLevel}, Semester {targetSemester}.
                                        </p>
                                    ) : (
                                        <div className="max-h-56 divide-y overflow-y-auto rounded-md border">
                                            {preview.subjects.map((subject) => (
                                                <label
                                                    key={subject.id}
                                                    className="hover:bg-muted/40 flex cursor-pointer items-center justify-between gap-2 px-3 py-2 text-xs"
                                                >
                                                    <div className="flex min-w-0 items-center gap-2">
                                                        <Checkbox
                                                            checked={selectedSubjectIds.includes(subject.id)}
                                                            onCheckedChange={() => toggleSubject(subject.id)}
                                                        />
                                                        <div className="min-w-0">
                                                            <p className="font-semibold">{subject.code}</p>
                                                            <p className="text-muted-foreground truncate text-[11px]">{subject.title}</p>
                                                        </div>
                                                    </div>
                                                    <div className="flex shrink-0 items-center gap-1.5">
                                                        <Badge variant="outline" size="xs">
                                                            {subject.total_units} u
                                                        </Badge>
                                                        <Badge
                                                            variant={subject.available_classes_count > 0 ? "success-light" : "warning-light"}
                                                            size="xs"
                                                        >
                                                            {subject.available_classes_count} cls
                                                        </Badge>
                                                    </div>
                                                </label>
                                            ))}
                                        </div>
                                    )}

                                    <div className="space-y-1.5 pt-1">
                                        <Label className="text-xs">Target Block Section</Label>
                                        <Select value={blockSection} onValueChange={setBlockSection}>
                                            <SelectTrigger className="h-8 text-xs">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="auto">Auto-balance across open sections</SelectItem>
                                                {preview.available_sections.map((sec) => (
                                                    <SelectItem key={sec.section} value={sec.section}>
                                                        Section {sec.section} ({sec.matched_subjects_count}/{sec.total_subjects_count} subj
                                                        {sec.min_available_slots !== null ? ` · min ${sec.min_available_slots} slots` : ""})
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>

                                    <div className="space-y-2 border-t pt-2.5 text-xs">
                                        <div className="flex items-center justify-between">
                                            <span>Auto-assign matching classes</span>
                                            <Switch checked={autoAssignClasses} onCheckedChange={setAutoAssignClasses} />
                                        </div>
                                        <div className="flex items-center justify-between">
                                            <span>Promote student year level to Year {targetYearLevel}</span>
                                            <Switch checked={promoteYearLevel} onCheckedChange={setPromoteYearLevel} />
                                        </div>
                                        <div className="flex items-center justify-between">
                                            <span>Allow section seat overload</span>
                                            <Switch checked={forceOverload} onCheckedChange={setForceOverload} />
                                        </div>
                                    </div>

                                    {selectedBlockedCount > 0 && (
                                        <div className="border-destructive/40 bg-destructive/5 space-y-2 rounded-lg border p-3">
                                            <div className="flex items-center justify-between gap-2 text-xs">
                                                <span className="text-destructive font-semibold">
                                                    {selectedBlockedCount} selected student(s) have blocking holds
                                                </span>
                                                <Switch checked={overrideHolds} onCheckedChange={setOverrideHolds} />
                                            </div>
                                            {overrideHolds && (
                                                <Textarea
                                                    rows={2}
                                                    value={overrideHoldsReason}
                                                    onChange={(e) => setOverrideHoldsReason(e.target.value)}
                                                    placeholder="Audit justification for overriding blocking holds..."
                                                    className="bg-background text-xs"
                                                />
                                            )}
                                        </div>
                                    )}

                                    <div className="flex items-center justify-between gap-2 pt-2">
                                        <Button type="button" variant="outline" size="sm" onClick={() => setStep(1)}>
                                            Back
                                        </Button>
                                        <Button
                                            type="button"
                                            size="sm"
                                            className="gap-1.5"
                                            disabled={submitting || selectedStudentIds.length === 0 || selectedSubjectIds.length === 0}
                                            onClick={() => void handleExecuteBatch()}
                                        >
                                            {submitting ? <Loader2 className="size-3.5 animate-spin" /> : <Layers className="size-3.5" />}
                                            Enroll {selectedStudentIds.length} student{selectedStudentIds.length === 1 ? "" : "s"}
                                        </Button>
                                    </div>
                                </FramePanel>
                            </Frame>
                        </div>
                    </div>
                )}

                {/* STEP 3: EXECUTION RESULTS */}
                {step === 3 && executionResult && (
                    <div className="space-y-4">
                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                            <Frame spacing="xs">
                                <FramePanel className="flex items-center justify-between p-4">
                                    <div>
                                        <p className="text-muted-foreground text-xs">Successfully Enrolled</p>
                                        <p className="text-2xl font-semibold text-emerald-600 tabular-nums dark:text-emerald-400">
                                            {executionResult.enrolled_count}
                                        </p>
                                    </div>
                                    <CheckCircle2 className="size-6 text-emerald-500" />
                                </FramePanel>
                            </Frame>
                            <Frame spacing="xs">
                                <FramePanel className="flex items-center justify-between p-4">
                                    <div>
                                        <p className="text-muted-foreground text-xs">Skipped</p>
                                        <p className="text-2xl font-semibold text-amber-600 tabular-nums dark:text-amber-400">
                                            {executionResult.skipped_count}
                                        </p>
                                    </div>
                                    <AlertTriangle className="size-6 text-amber-500" />
                                </FramePanel>
                            </Frame>
                            <Frame spacing="xs">
                                <FramePanel className="flex items-center justify-between p-4">
                                    <div>
                                        <p className="text-muted-foreground text-xs">Blocked / Failed</p>
                                        <p className="text-destructive text-2xl font-semibold tabular-nums">{executionResult.failed_count}</p>
                                    </div>
                                    <XCircle className="text-destructive size-6" />
                                </FramePanel>
                            </Frame>
                        </div>

                        <Frame spacing="sm">
                            <FrameHeader className="flex-row items-center justify-between">
                                <FrameTitle className="text-sm font-semibold">Cohort Enrollment Log</FrameTitle>
                                <div className="flex items-center gap-2">
                                    <Button type="button" size="xs" variant="outline" onClick={() => setStep(1)}>
                                        Run another cohort
                                    </Button>
                                    <Link
                                        href={route("administrators.enrollments.index")}
                                        className={buttonVariants({ size: "xs" })}
                                    >
                                        View enrollments
                                    </Link>
                                </div>
                            </FrameHeader>
                            <FramePanel className="divide-y p-0">
                                {executionResult.results.map((item) => (
                                    <div key={item.student_id} className="flex items-center justify-between gap-3 px-4 py-3 text-xs">
                                        <div className="min-w-0">
                                            <p className="font-semibold">{item.student_name}</p>
                                            <p className="text-muted-foreground text-[11px]">{item.message}</p>
                                        </div>
                                        <div className="flex shrink-0 items-center gap-2">
                                            <Badge
                                                variant={
                                                    item.status === "enrolled"
                                                        ? "success-light"
                                                        : item.status === "skipped"
                                                          ? "warning-light"
                                                          : "destructive-light"
                                                }
                                                radius="full"
                                                size="sm"
                                                className="capitalize"
                                            >
                                                {item.status}
                                            </Badge>
                                            {item.enrollment_id && (
                                                <Link
                                                    href={route("administrators.enrollments.show", item.enrollment_id)}
                                                    className={buttonVariants({ variant: "ghost", size: "xs", className: "h-7 text-xs" })}
                                                >
                                                    Open
                                                </Link>
                                            )}
                                        </div>
                                    </div>
                                ))}
                            </FramePanel>
                        </Frame>
                    </div>
                )}
            </div>
        </AdminLayout>
    );
}
