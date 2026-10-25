import { Alert, AlertDescription } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import { index, show, store, update } from "@/routes/administrators/classes";
import { Link } from "@inertiajs/react";
import { ArrowLeft, ArrowRight, BookOpen, CalendarDays, Check, CheckCheck, RotateCcw, Save, Sliders, Sparkles } from "lucide-react";
import { useEffect, useMemo, useRef, useState, type ReactNode } from "react";
import { toast } from "sonner";
import {
    buildClassDefaults,
    buildClassStorePayload,
    buildSubjectCodeFromSubjectOptions,
    isClassDraft,
    normalizeClassDelivery,
    type ClassDefaultsInput,
    type ClassDraft,
    type ClassFormData,
    type Classification,
} from "../lib/class-defaults";
import { ClassPreviewCard } from "./class-preview-card";
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

    const isEditing = mode === "edit";
    const [currentSection, setCurrentSection] = useState(0);
    const [validationError, setValidationError] = useState<string | null>(null);
    const [savedDraft, setSavedDraft] = useState<ClassDraft | null>(null);
    const [draftReady, setDraftReady] = useState(false);
    const [draftStatus, setDraftStatus] = useState("Draft saved locally");
    const contentHeading = useRef<HTMLHeadingElement>(null);

    // 3 steps during creation (NO class settings!). 4 steps during edit if settings exist.
    const sections = useMemo(() => {
        if (isEditing) {
            return [
                {
                    id: "details",
                    title: "Class details",
                    subtitle: "Curriculum & capacity",
                    context: "Define the academic level, curriculum, section, and maximum enrollment capacity.",
                    icon: BookOpen,
                },
                {
                    id: "schedule",
                    title: "Teaching & schedule",
                    subtitle: "Faculty, rooms & sessions",
                    context: "Assign the instructor, default delivery mode, and weekly timetable.",
                    icon: CalendarDays,
                },
                {
                    id: "settings",
                    title: "Classroom settings",
                    subtitle: "Features & appearance",
                    context: "Manage classroom tools, theme, and student feature preferences.",
                    icon: Sliders,
                },
                {
                    id: "review",
                    title: "Review & save",
                    subtitle: "Confirm changes",
                    context: "Review all details before saving changes to this class.",
                    icon: CheckCheck,
                },
            ];
        }

        return [
            {
                id: "details",
                title: "Class details",
                subtitle: "Curriculum & capacity",
                context: "Define the academic level, curriculum, section, and maximum enrollment capacity.",
                icon: BookOpen,
            },
            {
                id: "schedule",
                title: "Teaching & schedule",
                subtitle: "Faculty, rooms & sessions",
                context: "Assign the instructor, default delivery mode, and weekly timetable.",
                icon: CalendarDays,
            },
            {
                id: "review",
                title: "Review & publish",
                subtitle: "Confirm & create",
                context: "Review all details before publishing this new class offering.",
                icon: CheckCheck,
            },
        ];
    }, [isEditing]);

    const reviewStepIndex = sections.length - 1;

    const sectionForError = (key: string): number => {
        if (isEditing && key.startsWith("settings")) return 2;
        if (key.startsWith("schedules") || key === "faculty_id" || key === "room_id" || key === "delivery_mode") return 1;
        return 0;
    };

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
            setDraftStatus("Draft saved locally");
            if (announce) toast.success("Class draft saved on this device.");
        } catch {
            setDraftStatus("Draft could not be saved");
            if (announce) toast.error("Draft could not be saved to local storage.");
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
    }, [isEditing]);

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
        if (currentSection === 0) {
            if (step1Valid !== true) {
                setValidationError(step1Valid.message);
                toast.error(step1Valid.message);
                return false;
            }
        } else if (currentSection === 1) {
            if (step2Valid !== true) {
                setValidationError(step2Valid.message);
                toast.error(step2Valid.message);
                return false;
            }
        }

        setValidationError(null);
        return true;
    };

    const submit = (): void => {
        if (step1Valid !== true) {
            navigateTo(0);
            setValidationError(step1Valid.message);
            toast.error(step1Valid.message);
            return;
        }
        if (step2Valid !== true) {
            navigateTo(1);
            setValidationError(step2Valid.message);
            toast.error(step2Valid.message);
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
                        /* Ignore storage cleanup errors */
                    }
                }
                onCreated?.();
            },
            onError: (errors: Record<string, string>) => {
                const firstKey = Object.keys(errors)[0];
                if (firstKey) navigateTo(sectionForError(firstKey));
                toast.error("Class could not be saved", {
                    description: Object.values(errors)[0] ?? "Check highlighted fields and try again.",
                });
            },
        };

        form.transform(() => buildClassStorePayload(form.data));

        if (isEditing && classId) {
            form.patch(update.url(classId), requestOptions);
        } else {
            form.post(store.url(), requestOptions);
        }
    };

    return (
        <div className="space-y-6">
            {/* Draft Notice if draft available */}
            {savedDraft ? (
                <div
                    className="border-primary/30 bg-primary/[0.03] flex flex-col gap-3 rounded-2xl border p-4 sm:flex-row sm:items-center sm:justify-between"
                    role="status"
                >
                    <div className="flex items-center gap-3">
                        <div className="bg-primary/10 text-primary flex size-9 shrink-0 items-center justify-center rounded-xl">
                            <RotateCcw className="size-4.5" />
                        </div>
                        <div>
                            <p className="text-foreground text-sm font-semibold">Restore saved draft</p>
                            <p className="text-muted-foreground text-xs">
                                An unsaved draft is stored on this device. Would you like to resume where you left off?
                            </p>
                        </div>
                    </div>
                    <div className="flex items-center gap-2 self-end sm:self-auto">
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            className="text-xs"
                            onClick={() => {
                                setSavedDraft(null);
                                try {
                                    localStorage.removeItem(draftKey);
                                } catch {
                                    /* Ignore */
                                }
                            }}
                        >
                            Discard draft
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            className="gap-1.5 text-xs font-semibold"
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
                            <Sparkles className="size-3.5" />
                            Resume draft
                        </Button>
                    </div>
                </div>
            ) : null}

            {/* Modern Top Horizontal Stepper */}
            <nav aria-label="Class creation progress" className="bg-card rounded-2xl border p-2 shadow-2xs sm:p-3">
                <ol className="grid grid-cols-3 gap-2">
                    {sections.map((section, index) => {
                        const IconComponent = section.icon;
                        const isCurrent = currentSection === index;
                        const isComplete = currentSection > index;

                        return (
                            <li key={section.id} className="relative">
                                <button
                                    type="button"
                                    disabled={form.processing}
                                    onClick={() => navigateTo(index)}
                                    className={cn(
                                        "group flex w-full cursor-pointer flex-col gap-2 rounded-xl p-3 text-left transition-all outline-none",
                                        "hover:bg-muted/50",
                                        "focus-visible:ring-ring focus-visible:ring-2",
                                        isCurrent && "bg-primary/[0.08] ring-primary/25 shadow-2xs ring-1",
                                    )}
                                >
                                    <div className="flex items-center justify-between gap-2">
                                        <div className="flex min-w-0 items-center gap-2.5">
                                            <div
                                                className={cn(
                                                    "flex size-8 shrink-0 items-center justify-center rounded-lg border text-xs font-semibold transition-all",
                                                    isCurrent && "border-primary bg-primary text-primary-foreground shadow-2xs",
                                                    isComplete && "border-primary/30 bg-primary/10 text-primary",
                                                    !isCurrent && !isComplete && "border-border bg-muted/60 text-muted-foreground",
                                                )}
                                            >
                                                {isComplete ? <Check className="size-4 stroke-[2.5]" /> : <IconComponent className="size-4" />}
                                            </div>

                                            <div className="min-w-0">
                                                <span className="text-muted-foreground block text-[10px] font-bold tracking-wider uppercase">
                                                    Step {index + 1}
                                                </span>
                                                <span
                                                    className={cn(
                                                        "block truncate text-sm font-semibold",
                                                        isCurrent ? "text-foreground" : "text-muted-foreground group-hover:text-foreground",
                                                    )}
                                                >
                                                    {section.title}
                                                </span>
                                            </div>
                                        </div>

                                        {isCurrent ? (
                                            <span className="bg-primary hidden size-2 shrink-0 animate-pulse rounded-full sm:block" />
                                        ) : null}
                                    </div>

                                    {/* Progress track under each step */}
                                    <div className="bg-muted/60 h-1 w-full overflow-hidden rounded-full">
                                        <div
                                            className={cn(
                                                "h-full transition-all duration-300",
                                                isCurrent && "bg-primary w-full",
                                                isComplete && "bg-primary/70 w-full",
                                                !isCurrent && !isComplete && "w-0",
                                            )}
                                        />
                                    </div>
                                </button>
                            </li>
                        );
                    })}
                </ol>
            </nav>

            {/* Wizard Two-Column Layout: Form on Left + Live Visualizer on Right */}
            <div className="grid items-start gap-6 lg:grid-cols-[minmax(0,1.3fr)_380px] xl:grid-cols-[minmax(0,1.4fr)_420px]">
                {/* Form Column */}
                <form
                    className="min-w-0 space-y-6"
                    onSubmit={(event) => {
                        event.preventDefault();
                        if (currentSection === reviewStepIndex) submit();
                        else if (validateSection()) navigateTo(currentSection + 1);
                    }}
                    noValidate
                >
                    {/* Active Step Header */}
                    <div className="space-y-1.5">
                        <div className="flex items-center gap-2">
                            <Badge variant="outline" className="px-2 py-0.5 text-xs font-semibold">
                                Step {currentSection + 1} of {sections.length}
                            </Badge>
                            <span className="text-muted-foreground text-xs font-medium">{sections[currentSection].subtitle}</span>
                        </div>
                        <h2 ref={contentHeading} tabIndex={-1} className="text-foreground text-2xl font-bold tracking-tight outline-none sm:text-3xl">
                            {sections[currentSection].title}
                        </h2>
                        <p className="text-muted-foreground text-sm text-pretty">{sections[currentSection].context}</p>
                    </div>

                    {/* Alerts and errors */}
                    {validationError || Object.keys(form.errors).length > 0 ? (
                        <Alert variant="destructive" role="alert" className="rounded-xl">
                            <AlertDescription>
                                <p className="text-sm font-semibold">Please check the following:</p>
                                {validationError ? <p className="mt-1 text-xs">{validationError}</p> : null}
                                {Object.entries(form.errors).map(([key, error]) => (
                                    <button
                                        key={key}
                                        type="button"
                                        className="mt-1 block cursor-pointer text-left text-xs underline underline-offset-4"
                                        onClick={() => navigateTo(sectionForError(key))}
                                    >
                                        {error}
                                    </button>
                                ))}
                            </AlertDescription>
                        </Alert>
                    ) : null}

                    {collegeSubjectsError && currentSection === 0 ? (
                        <Alert variant="destructive" className="rounded-xl">
                            <AlertDescription className="flex items-center justify-between text-xs">
                                <span>{collegeSubjectsError}</span>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    className="ml-3 h-8 px-2.5 text-xs"
                                    onClick={() => void loadCollegeSubjects(form.data.course_codes)}
                                >
                                    Retry
                                </Button>
                            </AlertDescription>
                        </Alert>
                    ) : null}

                    {/* Active Step Fields */}
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
                            <div className="space-y-6">
                                <TeachingStep data={form.data} options={options} onDataChange={applyDataChange} />
                                <ScheduleStep data={form.data} options={options} onDataChange={applyDataChange} />
                            </div>
                        ) : null}

                        {/* Settings step only if in edit mode and step 2 */}
                        {isEditing && currentSection === 2 ? <PolicyStep data={form.data} onDataChange={applyDataChange} /> : null}

                        {currentSection === reviewStepIndex ? (
                            <ReviewStep
                                data={form.data}
                                options={options}
                                collegeSubjects={collegeSubjects}
                                onJumpToStep={navigateTo}
                                isEditing={isEditing}
                            />
                        ) : null}
                    </fieldset>

                    {/* Bottom Navigation Toolbar */}
                    <footer className="bg-card flex flex-col gap-4 rounded-2xl border p-4 shadow-xs sm:p-5">
                        <div className="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div className="flex items-center gap-2">
                                {currentSection > 0 ? (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        className="h-11 gap-1.5 rounded-xl px-4 text-xs font-semibold"
                                        disabled={form.processing}
                                        onClick={() => navigateTo(currentSection - 1)}
                                    >
                                        <ArrowLeft className="size-3.5" aria-hidden="true" />
                                        Back
                                    </Button>
                                ) : (
                                    <Button
                                        variant="outline"
                                        className="h-11 rounded-xl px-4 text-xs font-semibold"
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
                                        className="text-muted-foreground hover:text-foreground h-11 gap-1.5 rounded-xl px-3 text-xs"
                                        disabled={form.processing}
                                        onClick={() => saveDraft()}
                                    >
                                        <Save className="size-3.5" aria-hidden="true" />
                                        Save draft
                                    </Button>
                                ) : null}
                            </div>

                            <Button type="submit" className="h-11 gap-2 rounded-xl px-5 text-sm font-semibold shadow-xs" disabled={form.processing}>
                                {currentSection === reviewStepIndex ? (
                                    <>
                                        <CheckCheck className="size-4" aria-hidden="true" />
                                        {form.processing ? "Publishing class…" : isEditing ? "Save changes" : "Create class"}
                                    </>
                                ) : (
                                    <>
                                        Continue to {sections[currentSection + 1]?.title}
                                        <ArrowRight className="size-4" aria-hidden="true" />
                                    </>
                                )}
                            </Button>
                        </div>

                        <div className="text-muted-foreground flex items-center justify-between border-t pt-3 text-xs">
                            <span className="flex items-center gap-1.5">
                                <span className="size-1.5 rounded-full bg-emerald-500" />
                                {!isEditing ? draftStatus : "Changes saved upon final confirmation"}
                            </span>
                            <span>
                                Step {currentSection + 1} of {sections.length}
                            </span>
                        </div>
                    </footer>
                </form>

                {/* Right Column: Visualizer Live Class Preview */}
                <div className="space-y-4 lg:sticky lg:top-6 lg:self-start">
                    <ClassPreviewCard
                        data={form.data}
                        options={options}
                        collegeSubjects={collegeSubjects}
                        semester={semester}
                        schoolYear={school_year}
                    />
                </div>
            </div>
        </div>
    );
}
