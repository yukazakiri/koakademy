import AdminLayout from "@/components/administrators/admin-layout";
import { WeeklySchedule, type WeeklyScheduleEvent } from "@/components/administrators/classes/weekly-schedule";
import { Button } from "@/components/ui/button";
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from "@/components/ui/dropdown-menu";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { index as classesIndex, create as createClass, edit as editClass, show as showClass } from "@/routes/administrators/classes";
import { index as plannerIndex, publish } from "@/routes/administrators/scheduling-analytics";
import { store as storeSessions } from "@/routes/administrators/scheduling-analytics/classes/schedules";
import { generate } from "@/routes/administrators/scheduling-analytics/offerings";
import { destroy as destroySession, update as updateSession } from "@/routes/administrators/scheduling-analytics/schedules";
import { search as searchStudents, schedule as studentSchedule } from "@/routes/administrators/scheduling-analytics/students";
import { Head, Link, router } from "@inertiajs/react";
import axios from "axios";
import { ArrowLeft, MoreHorizontal, Plus, Search } from "lucide-react";
import { useEffect, useMemo, useRef, useState } from "react";
import { toast } from "sonner";
import GenerateOfferingsDialog, { type GenerateOfferingsPayload } from "./components/generate-offerings-dialog";
import ScheduleExportDialog from "./components/schedule-export-dialog";
import SchedulingFiltersBar, { emptyPlannerFilters, type PlannerFilters } from "./components/scheduling-filters-bar";
import { scheduledHours } from "./components/scheduling-readiness";
import SchedulingReviewDialog from "./components/scheduling-review-dialog";
import SchedulingSessionDialog, { type SessionValues } from "./components/scheduling-session-dialog";
import type {
    ClassScheduleData,
    ScheduleConflict,
    ScheduleUpdateResponse,
    SchedulingAnalyticsProps,
    StudentSchedule,
    StudentSearchResult,
} from "./components/scheduling-types";
import { matchesPlannerGroup } from "./components/scheduling-view";

function requestError(error: unknown): Error {
    if (axios.isAxiosError(error)) {
        const errors = error.response?.data?.errors;
        const first =
            errors &&
            Object.values(errors)
                .flat()
                .find((message) => typeof message === "string");
        return new Error(String(first || error.response?.data?.message || "Unable to save changes. Please try again."));
    }
    return error instanceof Error ? error : new Error("Unable to save changes. Please try again.");
}

export default function SchedulingAnalytics({
    user,
    schedule_data,
    stats,
    term_context,
    filters,
    creation_options,
    defaults,
}: SchedulingAnalyticsProps) {
    const [classes, setClasses] = useState(schedule_data);
    const [conflicts, setConflicts] = useState(stats.schedule_conflicts);
    const [selection, setSelection] = useState<{ classId: number; sessionId?: number } | null>(null);
    const [viewFilters, setViewFilters] = useState<PlannerFilters>({
        ...emptyPlannerFilters,
        course: filters.current_filters.course || "all",
        year: filters.current_filters.year_level || "all",
        section: filters.current_filters.section || "all",
    });
    const [search, setSearch] = useState("");
    const [needsTimesOnly, setNeedsTimesOnly] = useState(false);
    const [reviewOpen, setReviewOpen] = useState(false);
    const [conflictsOpen, setConflictsOpen] = useState(false);
    const [generateOpen, setGenerateOpen] = useState(false);
    const [exportOpen, setExportOpen] = useState(false);
    const [publishing, setPublishing] = useState(false);
    const [studentQuery, setStudentQuery] = useState("");
    const [students, setStudents] = useState<StudentSearchResult[]>([]);
    const [activeStudent, setActiveStudent] = useState<StudentSchedule | null>(null);
    const [loadingStudent, setLoadingStudent] = useState(false);
    const [searchingStudent, setSearchingStudent] = useState(false);
    const studentRequest = useRef(0);
    const schoolYear = term_context?.school_year ?? defaults.school_year;
    const semester = term_context?.semester ?? defaults.semester;

    useEffect(() => setClasses(schedule_data), [schedule_data]);
    useEffect(() => setConflicts(stats.schedule_conflicts), [stats.schedule_conflicts]);
    useEffect(() => {
        const controller = new AbortController();
        const query = studentQuery.trim();
        setStudents([]);
        setSearchingStudent(false);
        if (query.length < 2) return;
        const timer = setTimeout(async () => {
            setSearchingStudent(true);
            try {
                const response = await axios.get(searchStudents.url({ query: { query } }), { signal: controller.signal });
                if (!controller.signal.aborted) setStudents(response.data.students ?? []);
            } catch (error) {
                if (!controller.signal.aborted) toast.error(requestError(error).message);
            } finally {
                if (!controller.signal.aborted) setSearchingStudent(false);
            }
        }, 300);
        return () => {
            controller.abort();
            clearTimeout(timer);
        };
    }, [studentQuery]);

    const selectStudent = async (student: StudentSearchResult) => {
        const request = ++studentRequest.current;
        setStudentQuery("");
        setStudents([]);
        setLoadingStudent(true);
        try {
            const response = await axios.get<StudentSchedule>(studentSchedule.url(student.id));
            if (request === studentRequest.current) setActiveStudent(response.data);
        } catch (error) {
            if (request === studentRequest.current) toast.error(requestError(error).message);
        } finally {
            if (request === studentRequest.current) setLoadingStudent(false);
        }
    };
    const clearStudent = () => {
        studentRequest.current++;
        setActiveStudent(null);
        setLoadingStudent(false);
    };
    const matchesProgramAndYear = (item: ClassScheduleData) => matchesPlannerGroup(item, viewFilters.course, viewFilters.year);
    const sections = [...new Set(classes.filter(matchesProgramAndYear).map((item) => item.section))].sort();
    const visibleClasses = classes.filter(
        (item) =>
            matchesProgramAndYear(item) &&
            (viewFilters.section === "all" || item.section === viewFilters.section) &&
            (viewFilters.room === "all" || item.schedules.some((session) => String(session.room_id) === viewFilters.room)) &&
            (viewFilters.faculty === "all" || item.faculty_id === viewFilters.faculty) &&
            (!activeStudent || activeStudent.schedule.some((entry) => entry.id === item.id)),
    );
    const needsTimes = (item: ClassScheduleData) => scheduledHours(item.schedules) < (item.required_hours ?? 3) || item.schedules.length === 0;
    const classList = visibleClasses.filter(
        (item) =>
            (!needsTimesOnly || needsTimes(item)) &&
            `${item.subject_code} ${item.subject_title} ${item.section} ${item.faculty_name ?? ""}`
                .toLowerCase()
                .includes(search.trim().toLowerCase()),
    );
    const draftCount = classes.reduce((count, item) => count + item.schedules.filter((session) => session.status === "draft").length, 0);
    const selectedClass = classes.find((item) => item.id === selection?.classId);
    const selectedSession = selectedClass?.schedules.find((session) => session.id === selection?.sessionId);
    const conflictIds = useMemo(
        () => new Set(conflicts.flatMap((conflict) => [conflict.schedule_1_id, conflict.schedule_2_id]).filter((id) => id !== undefined)),
        [conflicts],
    );
    const events: WeeklyScheduleEvent[] = visibleClasses.flatMap((item) =>
        item.schedules
            .filter((session) => viewFilters.room === "all" || String(session.room_id) === viewFilters.room)
            .map((session, index) => ({
                id: `${item.id}:${session.id ?? index}`,
                day: session.day_of_week,
                start: session.start_time,
                end: session.end_time,
                title: `${item.subject_code}${viewFilters.section === "all" ? ` · ${item.section}` : ""}`,
                location: session.room ?? "Room to assign",
                online: item.delivery_mode === "online",
                conflict: session.id !== undefined && conflictIds.has(session.id),
            })),
    );

    const saveSession = async (values: SessionValues) => {
        if (!selectedClass) throw new Error("Class no longer available. Refresh the page and try again.");
        try {
            if (selectedSession?.id !== undefined) {
                const response = await axios.patch<ScheduleUpdateResponse>(updateSession.url(selectedSession.id), {
                    ...values,
                    day_of_week: values.days[0],
                });
                setClasses((previous) =>
                    previous.map((item) => ({
                        ...item,
                        schedules: item.schedules.map((session) =>
                            session.id === selectedSession.id ? { ...session, ...response.data.schedule } : session,
                        ),
                    })),
                );
                setConflicts(response.data.conflicts ?? []);
            } else {
                const response = await axios.post<{ class: ClassScheduleData; conflicts: ScheduleConflict[] }>(storeSessions.url(selectedClass.id), {
                    ...values,
                    status: "draft",
                });
                setClasses((previous) => previous.map((item) => (item.id === selectedClass.id ? response.data.class : item)));
                setConflicts(response.data.conflicts ?? []);
            }
            toast.success(selectedSession ? "Session updated." : "Class times added.");
        } catch (error) {
            throw requestError(error);
        }
    };
    const removeSession = async () => {
        if (selectedSession?.id === undefined) return;
        try {
            const response = await axios.delete<{ conflicts: ScheduleConflict[] }>(destroySession.url(selectedSession.id));
            setClasses((previous) =>
                previous.map((item) => ({ ...item, schedules: item.schedules.filter((session) => session.id !== selectedSession.id) })),
            );
            setConflicts(response.data.conflicts ?? []);
            toast.success("Session removed.");
        } catch (error) {
            throw requestError(error);
        }
    };
    const generateClasses = async (payload: GenerateOfferingsPayload) => {
        try {
            const response = await axios.post<{ created_count: number; skipped_count: number; subjects_found: number; classes: ClassScheduleData[] }>(
                generate.url(),
                payload,
            );
            setClasses((previous) => [...previous, ...(response.data.classes ?? [])]);
            if (!response.data.subjects_found) toast.info("No subjects found for this program, year, and semester.");
            else toast.success(`${response.data.created_count} classes added. ${response.data.skipped_count} already existed.`);
        } catch (error) {
            toast.error(requestError(error).message);
            throw error;
        }
    };
    const publishTerm = async () => {
        setPublishing(true);
        try {
            const response = await axios.post<{ published_count: number; conflicts: ScheduleConflict[] }>(publish.url(), {
                school_year: schoolYear,
                semester,
            });
            setClasses((previous) =>
                previous.map((item) => ({ ...item, schedules: item.schedules.map((session) => ({ ...session, status: "published" })) })),
            );
            setConflicts(response.data.conflicts ?? []);
            setReviewOpen(false);
            toast.success(`${response.data.published_count} sessions published.`);
        } catch (error) {
            if (axios.isAxiosError(error) && Array.isArray(error.response?.data?.conflicts)) setConflicts(error.response.data.conflicts);
            toast.error(requestError(error).message);
        } finally {
            setPublishing(false);
        }
    };

    return (
        <AdminLayout user={user} title="Schedule Planner">
            <Head title="Schedule planner" />
            <div className="mx-auto w-full max-w-screen-2xl space-y-6">
                <header className="space-y-4">
                    <Link href={classesIndex()} className="text-muted-foreground hover:text-foreground inline-flex items-center gap-2 text-sm">
                        <ArrowLeft className="size-4" aria-hidden="true" />
                        Classes
                    </Link>
                    <div className="flex flex-wrap items-start justify-between gap-4">
                        <div className="space-y-2">
                            <h1 className="text-2xl font-semibold tracking-tight">Schedule planner</h1>
                            <p className="text-muted-foreground max-w-2xl text-sm text-pretty">
                                Choose a section, add class times, then check the week.
                            </p>
                        </div>
                        <div className="flex items-center gap-2">
                            <Button onClick={() => setReviewOpen(true)} disabled={publishing}>
                                Review schedule
                            </Button>
                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <Button variant="outline" size="icon" aria-label="More planner actions">
                                        <MoreHorizontal className="size-4" />
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end">
                                    <DropdownMenuItem onClick={() => setGenerateOpen(true)}>Add classes from curriculum</DropdownMenuItem>
                                    <DropdownMenuItem asChild>
                                        <Link href={createClass()}>Create a class</Link>
                                    </DropdownMenuItem>
                                    <DropdownMenuItem onClick={() => setExportOpen(true)}>Export schedule PDF</DropdownMenuItem>
                                    <DropdownMenuItem onClick={() => router.reload({ only: ["schedule_data", "stats", "filters", "term_context"] })}>
                                        Refresh schedule
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </div>
                    </div>
                    <div className="flex flex-wrap items-center gap-3">
                        <Label htmlFor="planner-school-year" className="text-muted-foreground text-sm">
                            Term
                        </Label>
                        {term_context ? (
                            <>
                                <Select
                                    value={schoolYear}
                                    onValueChange={(value) =>
                                        router.get(plannerIndex.url(), { school_year: value, semester }, { preserveState: false })
                                    }
                                >
                                    <SelectTrigger id="planner-school-year" className="w-40">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {term_context.available_school_years.map((year) => (
                                            <SelectItem key={year} value={year}>
                                                {year}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <Select
                                    value={semester}
                                    onValueChange={(value) =>
                                        router.get(plannerIndex.url(), { school_year: schoolYear, semester: value }, { preserveState: false })
                                    }
                                >
                                    <SelectTrigger aria-label="Semester" className="w-40">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {term_context.available_semesters.map((item) => (
                                            <SelectItem key={item.value} value={item.value}>
                                                {item.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </>
                        ) : (
                            <span className="text-sm">
                                {schoolYear} · Semester {semester}
                            </span>
                        )}
                        {draftCount > 0 && <span className="text-muted-foreground text-sm">{draftCount} draft sessions</span>}
                    </div>
                </header>
                <SchedulingFiltersBar
                    value={viewFilters}
                    onChange={setViewFilters}
                    options={filters}
                    sections={sections}
                    studentQuery={studentQuery}
                    onStudentQueryChange={setStudentQuery}
                    students={students}
                    onSelectStudent={(student) => void selectStudent(student)}
                    loadingStudent={loadingStudent || searchingStudent}
                    activeStudentName={activeStudent?.student.name}
                    onClearStudent={clearStudent}
                />
                <div className="text-muted-foreground flex flex-wrap items-center justify-between gap-2 text-sm">
                    <p>
                        {visibleClasses.length} classes · {visibleClasses.filter(needsTimes).length} need times
                    </p>
                    {conflicts.length > 0 && (
                        <Button variant="ghost" className="text-destructive" onClick={() => setConflictsOpen(true)}>
                            {conflicts.length} schedule conflicts · Review
                        </Button>
                    )}
                </div>
                <div className="grid min-w-0 items-start gap-6 xl:grid-cols-[20rem_minmax(0,1fr)]">
                    <section aria-labelledby="planner-classes-heading" className="bg-card min-w-0 rounded-xl border">
                        <div className="space-y-4 p-4">
                            <div className="flex items-center justify-between gap-2">
                                <h2 id="planner-classes-heading" className="font-semibold">
                                    Classes
                                </h2>
                                <label className="text-muted-foreground flex items-center gap-2 text-xs">
                                    <input
                                        type="checkbox"
                                        checked={needsTimesOnly}
                                        onChange={(event) => setNeedsTimesOnly(event.target.checked)}
                                        className="accent-primary size-4"
                                    />
                                    Needs times only
                                </label>
                            </div>
                            <div className="relative">
                                <Search className="text-muted-foreground pointer-events-none absolute top-3 left-3 size-4" aria-hidden="true" />
                                <Input
                                    aria-label="Find a class"
                                    placeholder="Find a class"
                                    className="pl-10"
                                    value={search}
                                    onChange={(event) => setSearch(event.target.value)}
                                />
                            </div>
                        </div>
                        <div className="max-h-72 divide-y overflow-y-auto xl:max-h-[40rem]">
                            {classList.map((item) => (
                                <div key={item.id} className="space-y-3 p-4">
                                    <div className="space-y-1">
                                        <Link href={showClass(item.id)} className="text-sm font-semibold hover:underline">
                                            {item.subject_code} · {item.section}
                                        </Link>
                                        <p className="text-muted-foreground line-clamp-2 text-sm">{item.subject_title}</p>
                                    </div>
                                    <p className="text-muted-foreground text-xs">
                                        {item.delivery_mode === "online" ? "Online" : "Face to face"} ·{" "}
                                        {Number(scheduledHours(item.schedules).toFixed(2))}/{item.required_hours ?? 3} hours scheduled
                                    </p>
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <span className="text-muted-foreground min-w-0 flex-1 truncate text-xs">
                                            {item.faculty_name ?? "Instructor to assign"}
                                        </span>
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            aria-label={`Add times for ${item.subject_code}, section ${item.section}`}
                                            onClick={() => setSelection({ classId: item.id })}
                                        >
                                            <Plus className="size-4" aria-hidden="true" />
                                            Add times
                                        </Button>
                                    </div>
                                </div>
                            ))}
                            {!classList.length && (
                                <div className="space-y-3 p-6 text-sm">
                                    <p className="text-muted-foreground">
                                        {classes.length ? "No classes match these filters." : "Add classes to start planning this term."}
                                    </p>
                                    {!classes.length && (
                                        <Button variant="outline" onClick={() => setGenerateOpen(true)}>
                                            Add from curriculum
                                        </Button>
                                    )}
                                </div>
                            )}
                        </div>
                    </section>
                    <section aria-labelledby="planner-week-heading" className="bg-card min-w-0 overflow-hidden rounded-xl border">
                        <div className="space-y-1 p-4">
                            <h2 id="planner-week-heading" className="font-semibold">
                                Weekly schedule
                            </h2>
                            <p className="text-muted-foreground text-sm">Select a session to change its day, time, or room.</p>
                        </div>
                        <WeeklySchedule
                            events={events}
                            emptyMessage="No class times yet. Choose Add times beside a class to begin."
                            onSelect={(event) => {
                                const [classId, sessionId] = event.id.split(":").map(Number);
                                if (classes.find((item) => item.id === classId)?.schedules.some((session) => session.id === sessionId))
                                    setSelection({ classId, sessionId });
                            }}
                        />
                    </section>
                </div>
            </div>
            {selectedClass && (
                <SchedulingSessionDialog
                    key={`${selectedClass.id}:${selection?.sessionId ?? "new"}`}
                    classItem={selectedClass}
                    session={selectedSession}
                    rooms={creation_options.rooms}
                    onClose={() => setSelection(null)}
                    onSave={saveSession}
                    onDelete={removeSession}
                />
            )}
            <SchedulingReviewDialog
                key={`${reviewOpen}-${draftCount}`}
                open={reviewOpen}
                onOpenChange={setReviewOpen}
                classes={classes}
                conflictCount={conflicts.length}
                draftCount={draftCount}
                termLabel={`${schoolYear} · ${term_context?.semester_label ?? `Semester ${semester}`}`}
                isPublishing={publishing}
                onPublish={publishTerm}
                onResolveConflicts={() => {
                    setReviewOpen(false);
                    setConflictsOpen(true);
                }}
            />
            <Dialog open={conflictsOpen} onOpenChange={setConflictsOpen}>
                <DialogContent className="max-h-[90dvh] overflow-y-auto sm:max-w-xl">
                    <DialogHeader>
                        <DialogTitle>Schedule conflicts</DialogTitle>
                        <DialogDescription>These sessions overlap in this term. Open a class to adjust its times or room.</DialogDescription>
                    </DialogHeader>
                    <div className="space-y-3">
                        {conflicts.map((conflict, index) => (
                            <div key={index} className="space-y-2 rounded-lg border p-4 text-sm">
                                <p className="text-destructive font-medium">
                                    {conflict.conflict_type === "section"
                                        ? "Same section"
                                        : conflict.conflict_type === "faculty"
                                          ? "Same instructor"
                                          : "Same room"}{" "}
                                    · {conflict.day}
                                </p>
                                <p className="text-muted-foreground">{conflict.time}</p>
                                <div className="flex flex-wrap gap-2">
                                    {[conflict.class_1, conflict.class_2].map((item, i) => (
                                        <Button
                                            key={i}
                                            variant="outline"
                                            size="sm"
                                            disabled={!item.id}
                                            onClick={() => {
                                                if (item.id) router.visit(editClass.url(item.id));
                                            }}
                                        >
                                            {item.subject_code} · {item.section}
                                        </Button>
                                    ))}
                                </div>
                            </div>
                        ))}
                        {!conflicts.length && <p className="text-sm">No schedule conflicts detected.</p>}
                    </div>
                </DialogContent>
            </Dialog>
            <GenerateOfferingsDialog
                open={generateOpen}
                onOpenChange={setGenerateOpen}
                schoolYear={schoolYear}
                semester={semester}
                courses={creation_options.courses}
                shsStrands={creation_options.shs_strands}
                rooms={creation_options.rooms}
                onGenerate={generateClasses}
            />
            <ScheduleExportDialog
                open={exportOpen}
                onOpenChange={setExportOpen}
                courses={filters.available_courses}
                initialCourseId={viewFilters.course === "all" ? null : Number(viewFilters.course)}
            />
        </AdminLayout>
    );
}
