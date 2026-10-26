import { Alert, AlertDescription } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { ScrollArea } from "@/components/ui/scroll-area";
import { cn } from "@/lib/utils";
import { update as updateClass } from "@/routes/administrators/classes";
import { subjects as subjectOptionsRoute } from "@/routes/administrators/classes/options";
import { router, useForm } from "@inertiajs/react";
import { ArrowLeft, ArrowRight, BookOpen, Calendar, CalendarDays, Check, CheckCheck, Loader2, Sliders, Sparkles } from "lucide-react";
import { useEffect, useMemo, useRef, useState, type ReactNode } from "react";
import { toast } from "sonner";
import { ClassPreviewCard } from "../forms/class-preview-card";
import { BasicsStep } from "../forms/steps/basics-step";
import { PolicyStep } from "../forms/steps/policy-step";
import { ScheduleStep } from "../forms/steps/schedule-step";
import { TeachingStep } from "../forms/steps/teaching-step";
import {
    buildClassDefaults,
    buildClassStorePayload,
    deliveryModeLabel,
    meetingDeliverySummary,
    normalizeClassDelivery,
    step1IsValid,
    step2IsValid,
    type ClassFormData,
    type ClassFormOptions,
    type Classification,
    type ClassSchedule,
    type ClassSettings,
    type DeliveryMode,
    type EntityOption,
    type SubjectOption,
} from "../lib/class-defaults";

type SelectedClass = {
    id: number;
    record_title: string;
    classification: "college" | "shs" | string;
    delivery_mode?: DeliveryMode;
    delivery_summary?: "face_to_face" | "online" | "mixed";
    subject_code: string;
    subject_title: string;
    section: string;
    school_year: string;
    semester: string | number;
    academic_year: number | null;
    grade_level: string | null;
    shs_track: EntityOption | null;
    shs_strand: EntityOption | null;
    faculty: (EntityOption & { email?: string | null }) | null;
    room: EntityOption | null;
    course_codes: string[];
    course_abbreviations: string[] | null;
    course_ids: number[];
    subject_ids: number[];
    subjects: { id: number; code: string; title: string }[];
    maximum_slots: number;
    students_count: number;
    settings: {
        background_color?: string;
        accent_color?: string;
        banner_image?: string | null;
        theme?: string;
        enable_announcements?: boolean;
        enable_grade_visibility?: boolean;
        enable_attendance_tracking?: boolean;
        allow_late_submissions?: boolean;
        enable_discussion_board?: boolean;
        notify_students_on_schedule_changes?: boolean;
        custom?: Record<string, string>;
    };
    schedules: ClassSchedule[];
};

export type EditClassDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    selectedClass: SelectedClass | null;
    isLoading?: boolean;
    options: ClassFormOptions;
    defaults: { semester: string; school_year: string };
    initialTab?: "details" | "schedule" | "settings";
    onSuccess?: () => void;
};

const steps = [
    {
        id: "details",
        title: "Class details",
        subtitle: "Curriculum & capacity",
        icon: BookOpen,
        context: "Update academic level, curriculum placement, section, and maximum capacity.",
    },
    {
        id: "schedule",
        title: "Teaching & schedule",
        subtitle: "Faculty, rooms & meetings",
        icon: CalendarDays,
        context: "Configure the instructor assignment, delivery mode, and weekly schedule slots.",
    },
    {
        id: "settings",
        title: "Classroom settings",
        subtitle: "Appearance & features",
        icon: Sliders,
        context: "Customize classroom preferences, theme, and student feature toggles.",
    },
];

function normalizeSemester(value: string | number): "1" | "2" | "summer" {
    const str = String(value).toLowerCase();
    if (str === "1" || str === "2" || str === "summer") return str as "1" | "2" | "summer";
    return "1";
}

export function EditClassDialog({
    open,
    onOpenChange,
    selectedClass,
    isLoading = false,
    options,
    defaults,
    initialTab = "details",
    onSuccess,
}: EditClassDialogProps): ReactNode {
    const [currentStep, setCurrentStep] = useState(0);

    useEffect(() => {
        if (!open) return;
        if (initialTab === "schedule") setCurrentStep(1);
        else if (initialTab === "settings") setCurrentStep(2);
        else setCurrentStep(0);
    }, [open, initialTab]);
    const [subjectCodeTouched, setSubjectCodeTouched] = useState(false);
    const [collegeSubjects, setCollegeSubjects] = useState<SubjectOption[]>([]);
    const [collegeSubjectsLoading, setCollegeSubjectsLoading] = useState(false);
    const [collegeSubjectsError, setCollegeSubjectsError] = useState<string | null>(null);
    const [validationError, setValidationError] = useState<string | null>(null);
    const scrollContainerRef = useRef<HTMLDivElement>(null);

    const normalizedOptions: ClassFormOptions = useMemo(
        () => ({
            ...options,
            academic_years: options.academic_years ?? [
                { value: "1", label: "1st Year" },
                { value: "2", label: "2nd Year" },
                { value: "3", label: "3rd Year" },
                { value: "4", label: "4th Year" },
            ],
        }),
        [options],
    );

    const form = useForm<ClassFormData>(
        buildClassDefaults({
            semester: normalizeSemester(defaults.semester),
            school_year: defaults.school_year,
            options: normalizedOptions,
        }),
    );

    // Populate form data whenever a new class is loaded into the modal
    useEffect(() => {
        if (!open || !selectedClass) return;

        form.setData(
            normalizeClassDelivery({
                classification: (selectedClass.classification as Classification) ?? "college",
                course_codes: selectedClass.course_ids ?? [],
                subject_ids: selectedClass.subject_ids ?? [],
                subject_code: selectedClass.subject_code ?? "",
                academic_year: selectedClass.academic_year ?? 1,
                shs_track_id: selectedClass.shs_track ? Number(selectedClass.shs_track.id) : null,
                shs_strand_id: selectedClass.shs_strand ? Number(selectedClass.shs_strand.id) : null,
                subject_code_shs: selectedClass.classification === "shs" ? selectedClass.subject_code : "",
                grade_level: selectedClass.grade_level ?? "Grade 11",
                faculty_id: selectedClass.faculty ? String(selectedClass.faculty.id) : null,
                semester: normalizeSemester(selectedClass.semester),
                school_year: selectedClass.school_year,
                section: selectedClass.section,
                delivery_mode: selectedClass.delivery_mode ?? "face_to_face",
                room_id: selectedClass.room ? Number(selectedClass.room.id) : null,
                maximum_slots: selectedClass.maximum_slots ?? 40,
                schedules: (selectedClass.schedules ?? []).map((s) => ({
                    day_of_week: s.day_of_week,
                    start_time: s.start_time,
                    end_time: s.end_time,
                    delivery_mode: s.delivery_mode,
                    room_id: s.room_id,
                    component_type: s.component_type,
                    pattern_group_id: s.pattern_group_id,
                })),
                settings: {
                    background_color: selectedClass.settings?.background_color ?? "#ffffff",
                    accent_color: selectedClass.settings?.accent_color ?? "#3b82f6",
                    theme: (selectedClass.settings?.theme as ClassSettings["theme"]) ?? "default",
                    enable_announcements: selectedClass.settings?.enable_announcements ?? true,
                    enable_grade_visibility: selectedClass.settings?.enable_grade_visibility ?? true,
                    enable_attendance_tracking: selectedClass.settings?.enable_attendance_tracking ?? false,
                    allow_late_submissions: selectedClass.settings?.allow_late_submissions ?? false,
                    enable_discussion_board: selectedClass.settings?.enable_discussion_board ?? false,
                    notify_students_on_schedule_changes: selectedClass.settings?.notify_students_on_schedule_changes ?? true,
                    custom: selectedClass.settings?.custom ?? {},
                    banner_image: null,
                },
            }),
        );

        setSubjectCodeTouched(Boolean(selectedClass.subject_code));
        setValidationError(null);
        setCurrentStep(0);
        void loadCollegeSubjects(selectedClass.course_ids ?? []);
    }, [open, selectedClass]);

    const loadCollegeSubjects = async (courseIds: number[]) => {
        setCollegeSubjectsError(null);
        if (courseIds.length === 0) {
            setCollegeSubjects([]);
            return;
        }

        setCollegeSubjectsLoading(true);
        try {
            const response = await fetch(subjectOptionsRoute.url({ query: { course_ids: courseIds } }), {
                headers: { Accept: "application/json" },
            });
            if (!response.ok) throw new Error("Failed to load subjects");
            const data = (await response.json()) as { data: SubjectOption[] };
            setCollegeSubjects(data.data ?? []);
        } catch {
            setCollegeSubjectsError("Could not load subjects for the selected programs.");
        } finally {
            setCollegeSubjectsLoading(false);
        }
    };

    const applyDataChange = (changes: Partial<ClassFormData>): void => {
        form.setData((data) => normalizeClassDelivery({ ...data, ...changes }));
        setValidationError(null);
    };

    const handleClassificationChange = (classification: Classification): void => {
        setSubjectCodeTouched(false);
        applyDataChange(
            classification === "college"
                ? { classification, shs_track_id: null, shs_strand_id: null, subject_code_shs: "", grade_level: "Grade 11" }
                : { classification, course_codes: [], subject_ids: [], subject_code: "", academic_year: 1 },
        );
    };

    const step1Valid = useMemo(() => step1IsValid(form.data, normalizedOptions), [form.data, normalizedOptions]);
    const step2Valid = useMemo(() => step2IsValid(form.data), [form.data]);

    const navigateTo = (stepIndex: number): void => {
        setCurrentStep(stepIndex);
        setValidationError(null);
        scrollContainerRef.current?.scrollTo({ top: 0, behavior: "smooth" });
    };

    const validateStep = (indexToValidate: number): boolean => {
        if (indexToValidate === 0) {
            if (step1Valid !== true) {
                setValidationError(step1Valid.message);
                toast.error(step1Valid.message);
                return false;
            }
        } else if (indexToValidate === 1) {
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
        if (!selectedClass) return;

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

        form.transform(() => buildClassStorePayload(form.data));

        form.patch(updateClass.url(selectedClass.id), {
            preserveScroll: true,
            forceFormData: form.data.settings.banner_image !== null,
            onStart: () => setValidationError(null),
            onSuccess: () => {
                toast.success("Class updated successfully.");
                onOpenChange(false);
                onSuccess?.();
                router.reload({ only: ["classes", "selected_class"] });
            },
            onError: (errors: Record<string, string>) => {
                const firstKey = Object.keys(errors)[0];
                if (firstKey?.startsWith("settings")) navigateTo(2);
                else if (firstKey?.startsWith("schedules") || firstKey === "faculty_id" || firstKey === "room_id" || firstKey === "delivery_mode") {
                    navigateTo(1);
                } else {
                    navigateTo(0);
                }
                toast.error("Could not save class changes", {
                    description: Object.values(errors)[0] ?? "Check highlighted fields.",
                });
            },
        });
    };

    const termLabel = normalizedOptions.semesters.find((t) => t.value === form.data.semester)?.label ?? form.data.semester;
    const deliverySummary = meetingDeliverySummary(form.data.schedules, form.data.delivery_mode);

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="bg-card flex h-[92vh] w-full flex-col overflow-hidden rounded-2xl border p-0 shadow-2xl sm:max-w-4xl md:max-w-5xl lg:max-w-6xl xl:max-w-7xl">
                {/* Modern Dialog Header */}
                <DialogHeader className="bg-muted/20 border-b px-6 py-4.5">
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div className="min-w-0 space-y-1">
                            <div className="flex items-center gap-2">
                                <span className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">
                                    Academic Operations / Classes
                                </span>
                                <span className="text-muted-foreground/60 text-xs">·</span>
                                <Badge variant="outline" className="font-mono text-[11px] font-medium">
                                    Section {form.data.section}
                                </Badge>
                            </div>

                            <DialogTitle className="text-foreground truncate text-xl font-bold tracking-tight">
                                {selectedClass ? `Edit ${selectedClass.record_title}` : "Edit class"}
                            </DialogTitle>

                            <DialogDescription className="text-muted-foreground text-xs">
                                Update curriculum placement, instructor assignment, and weekly schedule.
                            </DialogDescription>
                        </div>

                        <div className="flex flex-wrap items-center gap-2 self-start sm:self-center">
                            <Badge variant="outline" className="bg-background/80 gap-1.5 px-2.5 py-1 text-xs font-semibold">
                                <Calendar className="text-primary size-3.5" />
                                {form.data.school_year} · {termLabel}
                            </Badge>

                            <Badge variant="secondary" className="text-xs font-medium capitalize">
                                {deliveryModeLabel(deliverySummary)}
                            </Badge>
                        </div>
                    </div>
                </DialogHeader>

                {/* Loading State Skeleton */}
                {isLoading || !selectedClass ? (
                    <div className="text-muted-foreground flex flex-1 flex-col items-center justify-center gap-3 p-12">
                        <Loader2 className="text-primary size-8 animate-spin" />
                        <p className="text-sm font-medium">Loading class details...</p>
                    </div>
                ) : (
                    <>
                        {/* Horizontal Stepper Bar */}
                        <div className="bg-background/80 border-b px-6 py-2.5 backdrop-blur">
                            <ol className="grid grid-cols-3 gap-2">
                                {steps.map((step, index) => {
                                    const IconComponent = step.icon;
                                    const isCurrent = currentStep === index;
                                    const isComplete = currentStep > index;

                                    return (
                                        <li key={step.id}>
                                            <button
                                                type="button"
                                                disabled={form.processing}
                                                onClick={() => navigateTo(index)}
                                                className={cn(
                                                    "group flex w-full cursor-pointer items-center justify-between gap-2 rounded-xl p-2.5 text-left transition-all outline-none",
                                                    "hover:bg-muted/50",
                                                    "focus-visible:ring-ring focus-visible:ring-2",
                                                    isCurrent && "bg-primary/[0.08] ring-primary/25 shadow-2xs ring-1",
                                                )}
                                            >
                                                <div className="flex min-w-0 items-center gap-2.5">
                                                    <div
                                                        className={cn(
                                                            "flex size-7 shrink-0 items-center justify-center rounded-lg border text-xs font-semibold transition-all",
                                                            isCurrent && "border-primary bg-primary text-primary-foreground shadow-2xs",
                                                            isComplete && "border-primary/30 bg-primary/10 text-primary",
                                                            !isCurrent && !isComplete && "border-border bg-muted/60 text-muted-foreground",
                                                        )}
                                                    >
                                                        {isComplete ? (
                                                            <Check className="size-3.5 stroke-[2.5]" />
                                                        ) : (
                                                            <IconComponent className="size-3.5" />
                                                        )}
                                                    </div>

                                                    <div className="min-w-0">
                                                        <span className="text-muted-foreground block text-[10px] font-bold tracking-wider uppercase">
                                                            Step {index + 1}
                                                        </span>
                                                        <span
                                                            className={cn(
                                                                "block truncate text-xs font-semibold",
                                                                isCurrent ? "text-foreground" : "text-muted-foreground group-hover:text-foreground",
                                                            )}
                                                        >
                                                            {step.title}
                                                        </span>
                                                    </div>
                                                </div>

                                                {isCurrent ? (
                                                    <span className="bg-primary hidden size-1.5 shrink-0 animate-pulse rounded-full sm:block" />
                                                ) : null}
                                            </button>
                                        </li>
                                    );
                                })}
                            </ol>
                        </div>

                        {/* Split Body: Form Left + Visualizer Right */}
                        <div className="grid min-h-0 flex-1 overflow-hidden lg:grid-cols-[minmax(0,1.3fr)_380px] xl:grid-cols-[minmax(0,1.4fr)_420px]">
                            {/* Left Scrollable Form Area */}
                            <ScrollArea className="h-full">
                                <div ref={scrollContainerRef} className="space-y-6 p-6">
                                    {/* Active Step Header Context */}
                                    <div className="space-y-1">
                                        <div className="flex items-center gap-2">
                                            <Badge variant="outline" className="text-xs font-semibold">
                                                Step {currentStep + 1} of {steps.length}
                                            </Badge>
                                            <span className="text-muted-foreground text-xs font-medium">{steps[currentStep].subtitle}</span>
                                        </div>
                                        <h3 className="text-foreground text-xl font-bold tracking-tight">{steps[currentStep].title}</h3>
                                        <p className="text-muted-foreground text-xs text-pretty">{steps[currentStep].context}</p>
                                    </div>

                                    {/* Validation Errors Alert */}
                                    {validationError || Object.keys(form.errors).length > 0 ? (
                                        <Alert variant="destructive" className="rounded-xl">
                                            <AlertDescription className="space-y-1 text-xs">
                                                <p className="text-sm font-semibold">Please verify the following fields:</p>
                                                {validationError ? <p>{validationError}</p> : null}
                                                {Object.entries(form.errors).map(([key, err]) => (
                                                    <p key={key} className="text-destructive block cursor-pointer underline">
                                                        {err}
                                                    </p>
                                                ))}
                                            </AlertDescription>
                                        </Alert>
                                    ) : null}

                                    {collegeSubjectsError && currentStep === 0 ? (
                                        <Alert variant="destructive" className="rounded-xl">
                                            <AlertDescription className="flex items-center justify-between text-xs">
                                                <span>{collegeSubjectsError}</span>
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="sm"
                                                    className="h-7 px-2 text-xs"
                                                    onClick={() => void loadCollegeSubjects(form.data.course_codes)}
                                                >
                                                    Retry
                                                </Button>
                                            </AlertDescription>
                                        </Alert>
                                    ) : null}

                                    {/* Step Content */}
                                    <fieldset
                                        disabled={form.processing}
                                        className="space-y-6 disabled:opacity-70 [&_[data-slot=select-trigger]]:min-h-11 [&_[role=combobox]]:min-h-11 [&_input:not([type=color]):not([type=radio])]:min-h-11"
                                    >
                                        {currentStep === 0 ? (
                                            <BasicsStep
                                                data={form.data}
                                                options={normalizedOptions}
                                                collegeSubjects={collegeSubjects}
                                                subjectCodeTouched={subjectCodeTouched}
                                                subjectsLoading={collegeSubjectsLoading}
                                                onClassificationChange={handleClassificationChange}
                                                onDataChange={applyDataChange}
                                                onSubjectCodeTouched={() => setSubjectCodeTouched(true)}
                                            />
                                        ) : null}

                                        {currentStep === 1 ? (
                                            <div className="space-y-6">
                                                <TeachingStep data={form.data} options={normalizedOptions} onDataChange={applyDataChange} />
                                                <ScheduleStep data={form.data} options={normalizedOptions} onDataChange={applyDataChange} />
                                            </div>
                                        ) : null}

                                        {currentStep === 2 ? (
                                            <div className="space-y-6">
                                                <div className="bg-muted/20 text-muted-foreground flex items-start gap-2.5 rounded-xl border border-dashed p-3.5 text-xs">
                                                    <Sparkles className="text-primary mt-0.5 size-4 shrink-0" />
                                                    <p className="text-pretty">
                                                        Faculty instructors can also manage these classroom settings, theme colours, and tool
                                                        permissions from their personal faculty portal.
                                                    </p>
                                                </div>
                                                <PolicyStep data={form.data} onDataChange={applyDataChange} />
                                            </div>
                                        ) : null}
                                    </fieldset>
                                </div>
                            </ScrollArea>

                            {/* Right Column: Visualizer Live Class Preview */}
                            <div className="bg-muted/10 hidden overflow-y-auto border-l p-6 lg:block">
                                <div className="sticky top-0">
                                    <ClassPreviewCard
                                        data={form.data}
                                        options={normalizedOptions}
                                        collegeSubjects={collegeSubjects}
                                        semester={form.data.semester}
                                        schoolYear={form.data.school_year}
                                    />
                                </div>
                            </div>
                        </div>

                        {/* Dialog Footer Actions */}
                        <DialogFooter className="bg-muted/20 flex flex-col-reverse gap-3 border-t px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
                            <div className="flex items-center gap-2">
                                {currentStep > 0 ? (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        className="h-10 gap-1.5 rounded-xl px-3.5 text-xs font-semibold"
                                        disabled={form.processing}
                                        onClick={() => navigateTo(currentStep - 1)}
                                    >
                                        <ArrowLeft className="size-3.5" />
                                        Back
                                    </Button>
                                ) : (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        className="h-10 rounded-xl px-3.5 text-xs font-semibold"
                                        onClick={() => onOpenChange(false)}
                                    >
                                        Cancel
                                    </Button>
                                )}
                            </div>

                            <div className="flex items-center gap-2">
                                {currentStep < steps.length - 1 ? (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        className="h-10 gap-1.5 rounded-xl px-4 text-xs font-semibold"
                                        disabled={form.processing}
                                        onClick={() => navigateTo(currentStep + 1)}
                                    >
                                        Next: {steps[currentStep + 1].title}
                                        <ArrowRight className="size-3.5" />
                                    </Button>
                                ) : null}

                                <Button
                                    type="button"
                                    size="sm"
                                    className="h-10 gap-1.5 rounded-xl px-5 text-xs font-semibold shadow-xs"
                                    disabled={form.processing}
                                    onClick={submit}
                                >
                                    {form.processing ? (
                                        <>
                                            <Loader2 className="size-3.5 animate-spin" />
                                            Saving changes…
                                        </>
                                    ) : (
                                        <>
                                            <CheckCheck className="size-3.5" />
                                            Save changes
                                        </>
                                    )}
                                </Button>
                            </div>
                        </DialogFooter>
                    </>
                )}
            </DialogContent>
        </Dialog>
    );
}
