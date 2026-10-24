import { Alert, AlertDescription } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import { index, show, store, update } from "@/routes/administrators/classes";
import { Link } from "@inertiajs/react";
import { ArrowLeft, ArrowRight, Check, CheckCheck, Save } from "lucide-react";
import { useEffect, useMemo, useRef, useState, type ReactNode } from "react";
import { toast } from "sonner";
import {
    buildClassDefaults,
    buildClassStorePayload,
    buildSubjectCodeFromSubjectOptions,
    deliveryModeLabel,
    isClassDraft,
    meetingDeliverySummary,
    normalizeClassDelivery,
    type ClassDefaultsInput,
    type ClassDraft,
    type ClassFormData,
    type Classification,
} from "../lib/class-defaults";
import { BasicsStep } from "./steps/basics-step";
import { PolicyStep } from "./steps/policy-step";
import { ReviewStep } from "./steps/review-step";
import { ScheduleStep } from "./steps/schedule-step";
import { TeachingStep } from "./steps/teaching-step";
import { useClassForm } from "./use-class-form";

type ClassWizardProps = ClassDefaultsInput & {
    mode?: "create" | "edit";
    classId?: number;
    initialData?: ClassFormData;
    onCreated?: () => void;
};

const draftKey = "koakademy:class-draft";
const sections = [
    { title: "Class details", description: "Curriculum, section, and capacity", context: "Define the class and connect it to the right curriculum." },
    {
        title: "Teaching and schedule",
        description: "Faculty, rooms, and weekly sessions",
        context: "Assign the teaching team and build a recurring timetable. Sessions can be added later.",
    },
    { title: "Classroom settings", description: "Features and appearance", context: "Choose the tools and preferences available to this class." },
    {
        title: "Review and save",
        description: "Check the complete class record",
        context: "Review the details before saving. You can return to any section to make changes.",
    },
];

function sectionForError(key: string): number {
    if (key.startsWith("settings")) return 2;
    if (key.startsWith("schedules") || key === "faculty_id" || key === "room_id" || key === "delivery_mode") return 1;
    return 0;
}

export function ClassWizard({ semester, school_year, options, mode = "create", classId, initialData, onCreated }: ClassWizardProps): ReactNode {
    const defaults = useMemo(() => ({ semester, school_year, options }), [semester, school_year, options]);
    const {
        form,
        subjectCodeTouched,
        setSubjectCodeTouched,
        collegeSubjects,
        collegeSubjectsLoading,
        loadedCourseKey,
        collegeSubjectsError,
        loadCollegeSubjects,
        step1Valid,
        step2Valid,
    } = useClassForm(defaults, initialData);
    const [currentSection, setCurrentSection] = useState(0);
    const [validationError, setValidationError] = useState<string | null>(null);
    const [savedDraft, setSavedDraft] = useState<ClassDraft | null>(null);
    const [draftReady, setDraftReady] = useState(false);
    const [draftStatus, setDraftStatus] = useState("Drafts save on this device");
    const contentHeading = useRef<HTMLHeadingElement>(null);
    const isEditing = mode === "edit";
    const subjectCode = form.data.classification === "college" ? form.data.subject_code : form.data.subject_code_shs;
    const selectedFaculty = options.faculties.find((faculty) => String(faculty.id) === String(form.data.faculty_id));
    const selectedRoom = options.rooms.find((room) => Number(room.id) === form.data.room_id);

    const navigateTo = (section: number): void => {
        setCurrentSection(section);
        setValidationError(null);
        requestAnimationFrame(() => contentHeading.current?.focus());
    };

    const applyDataChange = (changes: Partial<ClassFormData>): void => {
        form.setData((data) => normalizeClassDelivery({ ...data, ...changes }));
        setValidationError(null);
    };

    const saveDraft = (announce = true): void => {
        try {
            localStorage.setItem(draftKey, JSON.stringify({ ...form.data, settings: { ...form.data.settings, banner_image: null } }));
            setDraftStatus("Draft saved on this device");
            if (announce) toast.success("Draft saved on this device.");
        } catch {
            setDraftStatus("Draft could not be saved on this device");
            if (announce) toast.error("Draft could not be saved. Your changes are still in this form.");
        }
    };

    const handleClassificationChange = (classification: Classification): void => {
        setSubjectCodeTouched(false);
        applyDataChange(
            classification === "college"
                ? { classification, shs_track_id: null, shs_strand_id: null, subject_code_shs: "", grade_level: "Grade 11" }
                : { classification, course_codes: [], subject_ids: [], subject_code: "", academic_year: 1 },
        );
    };

    useEffect(() => {
        if (!isEditing) {
            try {
                const stored = localStorage.getItem(draftKey);
                const parsed: unknown = stored ? JSON.parse(stored) : null;
                if (isClassDraft(parsed)) setSavedDraft(parsed);
            } catch {
                setDraftStatus("Saved draft could not be read");
            }
        }
        setDraftReady(true);
    }, []);

    useEffect(() => {
        if (form.data.classification !== "college") return;
        if (form.data.course_codes.length === 0) {
            if (form.data.subject_ids.length > 0 || (!subjectCodeTouched && form.data.subject_code !== "")) {
                applyDataChange({ subject_ids: [], ...(!subjectCodeTouched ? { subject_code: "" } : {}) });
            }
            return;
        }
        void loadCollegeSubjects(form.data.course_codes);
    }, [form.data.classification, form.data.course_codes.join(",")]);

    useEffect(() => {
        if (form.data.classification !== "college" || loadedCourseKey !== form.data.course_codes.join(",") || collegeSubjectsLoading) return;
        const kept = form.data.subject_ids.filter((id) => collegeSubjects.some((subject) => subject.id === id));
        const selected = collegeSubjects.filter((subject) => kept.includes(subject.id));
        const years = Array.from(new Set(selected.map((subject) => subject.academic_year).filter((year): year is number => year !== null))).sort(
            (a, b) => a - b,
        );
        const changes: Partial<ClassFormData> = {};
        if (kept.length !== form.data.subject_ids.length) {
            changes.subject_ids = kept;
            if (!subjectCodeTouched) changes.subject_code = buildSubjectCodeFromSubjectOptions(kept, collegeSubjects);
        }
        if (
            selected.length > 0 &&
            selected.every((subject) => subject.academic_year !== null) &&
            years.length === 1 &&
            form.data.academic_year !== years[0]
        )
            changes.academic_year = years[0];
        else if (years.length > 1 && !years.includes(form.data.academic_year)) changes.academic_year = years[0];
        if (Object.keys(changes).length > 0) applyDataChange(changes);
    }, [
        collegeSubjects,
        loadedCourseKey,
        collegeSubjectsLoading,
        form.data.academic_year,
        form.data.classification,
        form.data.subject_ids,
        form.data.course_codes,
    ]);

    useEffect(() => {
        if (isEditing || !draftReady || savedDraft) return;
        const timeout = window.setTimeout(() => saveDraft(false), 1000);
        return () => window.clearTimeout(timeout);
    }, [form.data, isEditing, draftReady, savedDraft]);

    const validateSection = (): boolean => {
        const result = currentSection === 0 ? step1Valid : currentSection === 1 ? step2Valid : true;
        if (result === true) return true;
        setValidationError(result.message);
        return false;
    };

    const submit = (): void => {
        const failed =
            step1Valid !== true
                ? { section: 0, message: step1Valid.message }
                : step2Valid !== true
                  ? { section: 1, message: step2Valid.message }
                  : null;
        if (failed) {
            navigateTo(failed.section);
            setValidationError(failed.message);
            return;
        }
        const requestOptions = {
            forceFormData: form.data.settings.banner_image !== null,
            preserveScroll: true,
            onStart: () => setValidationError(null),
            onSuccess: () => {
                if (!isEditing) {
                    try {
                        localStorage.removeItem(draftKey);
                    } catch {
                        /* Saving the class does not depend on browser storage. */
                    }
                }
                onCreated?.();
            },
            onError: (errors: Record<string, string>) => {
                const firstKey = Object.keys(errors)[0];
                if (firstKey) navigateTo(sectionForError(firstKey));
                toast.error("Class could not be saved", { description: Object.values(errors)[0] ?? "Check the highlighted fields and try again." });
            },
        };
        form.transform(() => buildClassStorePayload(form.data));
        if (isEditing && classId) form.patch(update.url(classId), requestOptions);
        else form.post(store.url(), requestOptions);
    };

    return (
        <div className="space-y-6">
            {savedDraft ? (
                <div className="bg-card flex flex-col gap-4 rounded-xl border p-4 sm:flex-row sm:items-center sm:justify-between" role="status">
                    <div>
                        <p className="text-sm font-medium">Continue a saved draft</p>
                        <p className="text-muted-foreground mt-1 text-sm">
                            A class draft is available on this device. Banner images need to be selected again.
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <Button type="button" variant="outline" className="h-11 px-3" onClick={() => setSavedDraft(null)}>
                            Start fresh
                        </Button>
                        <Button
                            type="button"
                            className="h-11 px-3"
                            onClick={() => {
                                form.setData(
                                    normalizeClassDelivery({
                                        ...buildClassDefaults(defaults),
                                        ...savedDraft,
                                        settings: { ...buildClassDefaults(defaults).settings, ...savedDraft.settings, banner_image: null },
                                    }),
                                );
                                setSubjectCodeTouched(Boolean(savedDraft.subject_code));
                                setSavedDraft(null);
                                toast.success("Draft restored.");
                            }}
                        >
                            Restore draft
                        </Button>
                    </div>
                </div>
            ) : null}

            <div className="grid items-start gap-6 lg:grid-cols-[14rem_minmax(0,1fr)] xl:gap-8">
                <aside className="space-y-6 lg:sticky lg:top-6">
                    <nav aria-label="Class form sections" className="bg-card rounded-xl border p-3">
                        <p className="px-3 py-2 text-sm font-semibold">Class setup</p>
                        <ol className="grid gap-2 sm:grid-cols-2 lg:grid-cols-1">
                            {sections.map((section, sectionIndex) => (
                                <li key={section.title}>
                                    <button
                                        type="button"
                                        aria-current={currentSection === sectionIndex ? "step" : undefined}
                                        disabled={form.processing}
                                        onClick={() => navigateTo(sectionIndex)}
                                        className={cn(
                                            "focus-visible:ring-ring/50 flex w-full cursor-pointer items-start gap-3 rounded-lg p-3 text-left outline-none focus-visible:ring-3 disabled:opacity-50",
                                            currentSection === sectionIndex
                                                ? "bg-primary/10 text-primary"
                                                : "text-muted-foreground hover:bg-muted hover:text-foreground",
                                        )}
                                    >
                                        <span
                                            className={cn(
                                                "mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-md border text-sm tabular-nums",
                                                currentSection === sectionIndex && "border-primary/30",
                                            )}
                                            aria-hidden="true"
                                        >
                                            {sectionIndex + 1}
                                        </span>
                                        <span className="space-y-1">
                                            <span className="block text-sm font-semibold">{section.title}</span>
                                            <span className="text-muted-foreground block text-sm leading-5">{section.description}</span>
                                        </span>
                                    </button>
                                </li>
                            ))}
                        </ol>
                    </nav>
                    <section aria-label="Class summary" className="bg-card hidden space-y-4 rounded-xl border p-4 lg:block">
                        <p className="text-sm font-semibold">At a glance</p>
                        <div>
                            <p className="text-base font-semibold break-words">{subjectCode || "Untitled class"}</p>
                            <p className="text-muted-foreground mt-1 text-sm">
                                {form.data.classification === "college" ? "College" : "Senior high school"} · Section {form.data.section}
                            </p>
                        </div>
                        <dl className="space-y-4 text-sm">
                            <div>
                                <dt className="text-muted-foreground">Academic period</dt>
                                <dd className="mt-1">
                                    {school_year}
                                    <br />
                                    {options.semesters.find((term) => term.value === semester)?.label ?? semester}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground">Enrollment capacity</dt>
                                <dd className="mt-1">{form.data.maximum_slots} students</dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground">Faculty</dt>
                                <dd className="mt-1">{selectedFaculty?.label ?? "Unassigned"}</dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground">Delivery mode</dt>
                                <dd className="mt-1">{deliveryModeLabel(meetingDeliverySummary(form.data.schedules, form.data.delivery_mode))}</dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground">Default room</dt>
                                <dd className="mt-1">
                                    {form.data.delivery_mode === "online" ? "No default room" : (selectedRoom?.label ?? "Unassigned")}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground">Weekly schedule</dt>
                                <dd className="mt-1">
                                    {form.data.schedules.length ? `${form.data.schedules.length} recurring sessions` : "To be scheduled"}
                                </dd>
                            </div>
                        </dl>
                    </section>
                </aside>

                <form
                    className="min-w-0 space-y-6"
                    onSubmit={(event) => {
                        event.preventDefault();
                        if (currentSection === 3) submit();
                        else if (validateSection()) navigateTo(currentSection + 1);
                    }}
                    noValidate
                >
                    <div className="space-y-2">
                        <p className="text-muted-foreground text-sm font-medium">
                            Section {currentSection + 1} of {sections.length}
                        </p>
                        <h2 ref={contentHeading} tabIndex={-1} className="text-2xl font-semibold tracking-tight outline-none">
                            {sections[currentSection].title}
                        </h2>
                        <p className="text-muted-foreground max-w-3xl text-base text-pretty">{sections[currentSection].context}</p>
                    </div>
                    {validationError || Object.keys(form.errors).length > 0 ? (
                        <Alert variant="destructive" role="alert">
                            <AlertDescription>
                                <p className="font-medium">Check the class details</p>
                                {validationError ? <p className="mt-2">{validationError}</p> : null}
                                {Object.entries(form.errors).map(([key, error]) => (
                                    <button
                                        key={key}
                                        type="button"
                                        className="mt-2 block cursor-pointer text-left underline underline-offset-4"
                                        onClick={() => navigateTo(sectionForError(key))}
                                    >
                                        {error}
                                    </button>
                                ))}
                            </AlertDescription>
                        </Alert>
                    ) : null}
                    {collegeSubjectsError && currentSection === 0 ? (
                        <Alert variant="destructive">
                            <AlertDescription>
                                {collegeSubjectsError}
                                <Button
                                    type="button"
                                    variant="outline"
                                    className="ml-3 h-11 px-3"
                                    onClick={() => void loadCollegeSubjects(form.data.course_codes)}
                                >
                                    Try again
                                </Button>
                            </AlertDescription>
                        </Alert>
                    ) : null}
                    <fieldset
                        disabled={form.processing}
                        className="min-w-0 space-y-6 disabled:opacity-70 [&_[data-slot=select-trigger]]:min-h-11 [&_[role=combobox]]:min-h-11 [&_input:not([type=color]):not([type=radio])]:min-h-11"
                    >
                        {currentSection === 0 ? (
                            <BasicsStep
                                data={form.data}
                                options={options}
                                collegeSubjects={collegeSubjects}
                                subjectCodeTouched={subjectCodeTouched}
                                subjectsLoading={collegeSubjectsLoading}
                                onClassificationChange={handleClassificationChange}
                                onDataChange={applyDataChange}
                                onSubjectCodeTouched={() => setSubjectCodeTouched(true)}
                            />
                        ) : null}
                        {currentSection === 1 ? (
                            <>
                                <TeachingStep data={form.data} options={options} onDataChange={applyDataChange} />
                                <ScheduleStep data={form.data} options={options} onDataChange={applyDataChange} />
                            </>
                        ) : null}
                        {currentSection === 2 ? <PolicyStep data={form.data} onDataChange={applyDataChange} /> : null}
                        {currentSection === 3 ? (
                            <ReviewStep data={form.data} options={options} collegeSubjects={collegeSubjects} onJumpToStep={navigateTo} />
                        ) : null}
                    </fieldset>
                    <footer className="bg-card flex flex-col gap-4 rounded-xl border p-4 sm:p-6">
                        <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <div className="flex flex-wrap gap-2">
                                {currentSection > 0 ? (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        className="h-11 px-3"
                                        disabled={form.processing}
                                        onClick={() => navigateTo(currentSection - 1)}
                                    >
                                        <ArrowLeft className="size-4" aria-hidden="true" />
                                        Back
                                    </Button>
                                ) : (
                                    <Button
                                        variant="outline"
                                        className="h-11 px-3"
                                        nativeButton={false}
                                        render={<Link href={isEditing && classId ? show.url(classId) : index.url()} />}
                                    >
                                        Cancel
                                    </Button>
                                )}
                                {!isEditing ? (
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        className="h-11 px-3"
                                        disabled={form.processing}
                                        onClick={() => saveDraft()}
                                    >
                                        <Save className="size-4" aria-hidden="true" />
                                        Save draft
                                    </Button>
                                ) : null}
                            </div>
                            <Button type="submit" className="h-11 px-4 text-base font-semibold" disabled={form.processing}>
                                {currentSection === 3 ? (
                                    <>
                                        <CheckCheck className="size-4" aria-hidden="true" />
                                        {form.processing ? "Saving class…" : isEditing ? "Save changes" : "Create class"}
                                    </>
                                ) : (
                                    <>
                                        Continue to {sections[currentSection + 1].title.toLowerCase()}
                                        <ArrowRight className="size-4" aria-hidden="true" />
                                    </>
                                )}
                            </Button>
                        </div>
                        <p className="text-muted-foreground flex items-start gap-2 text-sm" aria-live="polite">
                            <Check className="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                            {isEditing
                                ? "Changes are applied only when you choose Save changes."
                                : `${draftStatus}. The class is created after your final review.`}
                        </p>
                    </footer>
                </form>
            </div>
        </div>
    );
}
