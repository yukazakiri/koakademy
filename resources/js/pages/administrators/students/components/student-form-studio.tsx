import { CustomStudentFields } from "@/components/administrators/custom-student-fields";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Separator } from "@/components/ui/separator";
import { Textarea } from "@/components/ui/textarea";
import { useFeatureFlags } from "@/hooks/use-feature-flags";
import { cn } from "@/lib/utils";
import { Link, useForm } from "@inertiajs/react";
import axios from "axios";
import {
    AlertCircle,
    ArrowLeft,
    ArrowRight,
    BookOpen,
    Camera,
    Check,
    CheckCircle2,
    ClipboardPlus,
    Copy,
    FileCheck2,
    Flag,
    GraduationCap,
    Loader2,
    PenTool,
    RefreshCw,
    RotateCcw,
    Save,
    UploadCloud,
    User as UserIcon,
    Users,
} from "lucide-react";
import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import { toast } from "sonner";
import type { DocumentSlot, IdentifierPolicy, SchoolProfile, StudentConfiguration } from "../shared-types";
import { CreateSignatureDialog } from "./create-signature-dialog";
import { StudentPortalAccountCard, type StudentPortalAccountSummary } from "./student-portal-account-card";

declare let route: (name: string, params?: Record<string, unknown> | string | number) => string;

export interface CourseGroupOption {
    label: string;
    pathway: "tesda_qualification" | "program";
    items: Array<{
        value: number;
        label: string;
        is_active: boolean;
        curriculum_kind?: string | null;
    }>;
}

export interface StudentFormOptions {
    student_configuration: StudentConfiguration;
    school_profile?: SchoolProfile;
    document_checklists?: Record<string, DocumentSlot[]>;
    configuration_scope?: string;
    identifier_policy: IdentifierPolicy;
    types: Array<{ value: string; label: string }>;
    statuses: Array<{ value: string; label: string }>;
    scholarship_types: Array<{ value: string; label: string }>;
    employment_statuses: Array<{ value: string; label: string }>;
    attrition_categories: Array<{ value: string; label: string }>;
    courses: CourseGroupOption[];
    shs_strands: Array<{ value: number; label: string }>;
    religions: Array<{ value: string; label: string }>;
    regions: Array<{ value: string; label: string }>;
    income_modes: Array<{ value: string; label: string; brackets: Array<{ value: string; label: string }> }>;
    default_income_mode: string;
}

interface StudentFormStudioProps {
    mode: "create" | "edit";
    options: StudentFormOptions;
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    student?: Record<string, any> | null;
    portalAccount?: StudentPortalAccountSummary | null;
    studentAssets?: {
        picture_1x1_url?: string | null;
        signature_url?: string | null;
    };
}

interface StepSpec {
    id: "identity" | "contacts" | "profile" | "reporting" | "documents";
    title: string;
    subtitle: string;
    icon: React.ReactNode;
    fields: string[];
}

const RELATIONSHIP_OPTIONS = [
    "Mother",
    "Father",
    "Guardian",
    "Sibling",
    "Spouse",
    "Grandparent",
    "Aunt / Uncle",
    "Relative",
    "Other",
];

function computeAge(birthDate: string): string {
    if (!birthDate) return "";
    const dob = new Date(birthDate);
    if (Number.isNaN(dob.getTime())) return "";
    const today = new Date();
    let age = today.getFullYear() - dob.getFullYear();
    const m = today.getMonth() - dob.getMonth();
    if (m < 0 || (m === 0 && today.getDate() < dob.getDate())) age--;
    return age >= 0 ? String(age) : "";
}

export function StudentFormStudio({
    mode,
    options,
    student,
    portalAccount,
    studentAssets,
}: StudentFormStudioProps) {
    const flags = useFeatureFlags();
    const config = options.student_configuration;
    const schoolProfile = options.school_profile;
    const policy = options.identifier_policy;
    const isEdit = mode === "edit" && Boolean(student);

    const draftStorageKey = `koakademy:student-form-draft:${options.configuration_scope || "default"}`;

    const defaultType = isEdit
        ? String(student?.student_type || config.default_type || "college")
        : String(config.default_type || config.enabled_types[0] || "college");

    const initialCustomFields: Record<string, string> = {};
    const storedCustom = (student?.profile_details?.custom_fields ?? {}) as Record<string, unknown>;
    for (const f of config.custom_fields ?? []) {
        if (f.active) {
            initialCustomFields[f.key] = storedCustom[f.key] !== undefined && storedCustom[f.key] !== null ? String(storedCustom[f.key]) : "";
        }
    }

    const parentInfo = student?.student_parent_info ?? student?.studentParentInfo ?? {};
    const contactInfo = student?.student_contacts_info ?? student?.studentContactsInfo ?? {};
    const educationInfo = student?.student_education_info ?? student?.studentEducationInfo ?? {};
    const personalInfo = student?.personal_info ?? student?.personalInfo ?? {};

    const initialFormData = {
        student_type: defaultType,
        status: isEdit ? String(student?.status || "enrolled") : "enrolled",
        identifier_assignment: policy.assignment_mode === "manual" ? "manual" : "automatic",
        student_id: isEdit ? String(student?.student_id ?? "") : "",
        lrn: isEdit ? String(student?.lrn ?? "") : "",
        first_name: isEdit ? String(student?.first_name ?? "") : "",
        middle_name: isEdit ? String(student?.middle_name ?? "") : "",
        last_name: isEdit ? String(student?.last_name ?? "") : "",
        suffix: isEdit ? String(student?.suffix ?? "") : "",
        gender: isEdit ? String(student?.gender ?? "") : String(config.defaults.gender ?? ""),
        birth_date: isEdit && student?.birth_date ? String(student.birth_date).slice(0, 10) : "",
        email: isEdit ? String(student?.email ?? "") : "",
        phone: isEdit ? String(student?.phone ?? "") : "",
        course_id: isEdit && student?.course_id ? String(student.course_id) : "",
        shs_strand_id: isEdit && student?.shs_strand_id ? String(student.shs_strand_id) : "",
        academic_year: isEdit && student?.academic_year
            ? String(student.academic_year)
            : String(config.year_levels?.[defaultType]?.[0]?.value ?? (defaultType === "shs" ? 11 : 1)),
        current_address: isEdit ? String(personalInfo?.current_adress ?? personalInfo?.current_address ?? student?.address ?? "") : "",
        permanent_address: isEdit ? String(personalInfo?.permanent_address ?? "") : "",
        birthplace: isEdit ? String(personalInfo?.birthplace ?? "") : "",
        civil_status: isEdit ? String(student?.civil_status ?? "single") : "single",
        nationality: isEdit ? String(student?.nationality ?? "") : String(config.defaults.nationality ?? ""),
        citizenship: isEdit ? String(personalInfo?.citizenship ?? student?.nationality ?? "") : String(config.defaults.citizenship ?? ""),
        religion: isEdit ? String(student?.religion ?? "") : "",
        height: isEdit && personalInfo?.height != null ? String(personalInfo.height) : "",
        weight: isEdit && personalInfo?.weight != null ? String(personalInfo.weight) : "",
        // Guardian / Emergency & Parents
        emergency_contact_name: isEdit ? String(contactInfo?.emergency_contact_name ?? parentInfo?.guardian_name ?? student?.emergency_contact ?? "") : "",
        emergency_contact_phone: isEdit ? String(contactInfo?.emergency_contact_phone ?? parentInfo?.guardian_contact ?? "") : "",
        emergency_contact_relationship: isEdit ? String(contactInfo?.emergency_contact_relationship ?? parentInfo?.guardian_relationship ?? "") : "",
        emergency_contact_address: isEdit ? String(contactInfo?.emergency_contact_address ?? parentInfo?.family_address ?? "") : "",
        guardian_name: isEdit ? String(parentInfo?.guardian_name ?? contactInfo?.emergency_contact_name ?? "") : "",
        guardian_contact: isEdit ? String(parentInfo?.guardian_contact ?? contactInfo?.emergency_contact_phone ?? "") : "",
        guardian_relationship: isEdit ? String(parentInfo?.guardian_relationship ?? contactInfo?.emergency_contact_relationship ?? "") : "",
        guardian_email: isEdit ? String(parentInfo?.guardian_email ?? "") : "",
        fathers_name: isEdit ? String(parentInfo?.father_name ?? parentInfo?.fathers_name ?? "") : "",
        father_occupation: isEdit ? String(parentInfo?.father_occupation ?? "") : "",
        father_contact: isEdit ? String(parentInfo?.father_contact ?? "") : "",
        father_email: isEdit ? String(parentInfo?.father_email ?? "") : "",
        mothers_name: isEdit ? String(parentInfo?.mother_name ?? parentInfo?.mothers_name ?? "") : "",
        mother_occupation: isEdit ? String(parentInfo?.mother_occupation ?? "") : "",
        mother_contact: isEdit ? String(parentInfo?.mother_contact ?? "") : "",
        mother_email: isEdit ? String(parentInfo?.mother_email ?? "") : "",
        family_address: isEdit ? String(parentInfo?.family_address ?? "") : "",
        facebook_contact: isEdit ? String(contactInfo?.facebook_contact ?? "") : "",
        // Prior Education
        elementary_school: isEdit ? String(educationInfo?.elementary_school ?? "") : "",
        elementary_graduate_year: isEdit ? String(educationInfo?.elementary_graduate_year ?? "") : "",
        elementary_school_address: isEdit ? String(educationInfo?.elementary_school_address ?? "") : "",
        junior_high_school_name: isEdit ? String(educationInfo?.junior_high_school_name ?? educationInfo?.high_school ?? "") : "",
        junior_high_graduation_year: isEdit ? String(educationInfo?.junior_high_graduation_year ?? "") : "",
        junior_high_school_address: isEdit ? String(educationInfo?.junior_high_school_address ?? "") : "",
        senior_high_name: isEdit ? String(educationInfo?.senior_high_name ?? educationInfo?.senior_high_school ?? "") : "",
        senior_high_graduate_year: isEdit ? String(educationInfo?.senior_high_graduate_year ?? "") : "",
        senior_high_address: isEdit ? String(educationInfo?.senior_high_address ?? "") : "",
        college_school: isEdit ? String(educationInfo?.college_school ?? "") : "",
        college_course: isEdit ? String(educationInfo?.college_course ?? "") : "",
        college_year_graduated: isEdit ? String(educationInfo?.college_year_graduated ?? "") : "",
        vocational_school: isEdit ? String(educationInfo?.vocational_school ?? "") : "",
        vocational_course: isEdit ? String(educationInfo?.vocational_course ?? "") : "",
        vocational_year_graduated: isEdit ? String(educationInfo?.vocational_year_graduated ?? "") : "",
        // Philippine Reporting & Scholarships
        region_of_origin: isEdit ? String(student?.region_of_origin ?? "") : "",
        province_of_origin: isEdit ? String(student?.province_of_origin ?? "") : "",
        city_of_origin: isEdit ? String(student?.city_of_origin ?? "") : "",
        ethnicity: isEdit ? String(student?.ethnicity ?? "") : "",
        is_indigenous_person: Boolean(student?.is_indigenous_person ?? false),
        indigenous_group: isEdit ? String(student?.indigenous_group ?? "") : "",
        is_pwd: Boolean(student?.is_pwd ?? false),
        pwd_type: isEdit ? String(student?.pwd_type ?? "") : "",
        is_solo_parent: Boolean(student?.is_solo_parent ?? false),
        is_solo_parent_dependent: Boolean(student?.is_solo_parent_dependent ?? false),
        is_senior_citizen: Boolean(student?.is_senior_citizen ?? false),
        is_magna_carta: Boolean(student?.is_magna_carta ?? false),
        is_underprivileged: Boolean(student?.is_underprivileged ?? false),
        is_first_generation: Boolean(student?.is_first_generation ?? false),
        income_bracket_mode: isEdit ? String(student?.income_bracket_mode ?? options.default_income_mode ?? "annual") : String(options.default_income_mode ?? "annual"),
        use_same_parent_income: Boolean(student?.use_same_parent_income ?? true),
        family_income_bracket: isEdit ? String(student?.family_income_bracket ?? "") : "",
        father_income_bracket: isEdit ? String(student?.father_income_bracket ?? "") : "",
        mother_income_bracket: isEdit ? String(student?.mother_income_bracket ?? "") : "",
        scholarship_type: isEdit ? String(student?.scholarship_type ?? "none") : "none",
        scholarship_details: isEdit ? String(student?.scholarship_details ?? "") : "",
        // Conditional Statuses
        employment_status: isEdit ? String(student?.employment_status ?? "not_applicable") : "not_applicable",
        employer_name: isEdit ? String(student?.employer_name ?? "") : "",
        job_position: isEdit ? String(student?.job_position ?? "") : "",
        employment_date: isEdit && student?.employment_date ? String(student.employment_date).slice(0, 10) : "",
        employed_by_institution: Boolean(student?.employed_by_institution ?? false),
        withdrawal_date: isEdit && student?.withdrawal_date ? String(student.withdrawal_date).slice(0, 10) : "",
        withdrawal_reason: isEdit ? String(student?.withdrawal_reason ?? "") : "",
        attrition_category: isEdit ? String(student?.attrition_category ?? "") : "",
        dropout_date: isEdit && student?.dropout_date ? String(student.dropout_date).slice(0, 10) : "",
        remarks: isEdit ? String(student?.remarks ?? "") : "",
        // Portal & Assets
        provision_portal_account: false,
        send_activation_link: true,
        picture_1x1: null as File | null,
        signature: null as File | null,
        documents: {} as Record<string, File>,
        custom_fields: initialCustomFields,
        submit_action: "view",
        stay: false,
    };

    const form = useForm(initialFormData);
    const { data, setData, errors, processing, isDirty } = form;

    const [activeStepIndex, setActiveStepIndex] = useState(0);
    const [previewId, setPreviewId] = useState<string>("");
    const [loadingIdPreview, setLoadingIdPreview] = useState(false);
    const [photoPreviewUrl, setPhotoPreviewUrl] = useState<string | null>(studentAssets?.picture_1x1_url ?? null);
    const [signaturePreviewUrl, setSignaturePreviewUrl] = useState<string | null>(studentAssets?.signature_url ?? null);
    const [signatureDialogOpen, setSignatureDialogOpen] = useState(false);
    const [savedDraftBanner, setSavedDraftBanner] = useState<Record<string, unknown> | null>(null);
    const photoInputRef = useRef<HTMLInputElement | null>(null);

    const isShs = data.student_type === "shs";
    const isTesda = data.student_type === "tesda";
    const isBasicEdGuardianRequired = Boolean(
        schoolProfile?.guardian_required_types?.includes(data.student_type) || isShs,
    );

    const externalEnabled = Boolean(
        config.external_identifier.enabled && config.external_identifier.student_types.includes(data.student_type),
    );
    const usesExternalAsId = Boolean(externalEnabled && config.external_identifier.use_as_student_id);
    const externalLabel = config.external_identifier.label || "External ID";
    const idLabel = policy.display_label || "Student ID";

    const fieldVisible = (key: string) => config.fields?.[key]?.visible ?? true;
    const fieldRequired = (key: string) => Boolean(config.fields?.[key]?.visible && config.fields?.[key]?.required);
    const fieldLabel = (key: string, fallback: string) => config.fields?.[key]?.label || fallback;

    // Dynamically compile steps based on School Configuration!
    const steps = useMemo<StepSpec[]>(() => {
        const list: StepSpec[] = [
            {
                id: "identity",
                title: isShs ? "Learner & Strand" : isTesda ? "Trainee & Qualification" : "Identity & Placement",
                subtitle: "Core record, ID policy & academic placement",
                icon: <UserIcon className="size-4" />,
                fields: ["student_type", "status", "student_id", "lrn", "first_name", "middle_name", "last_name", "gender", "birth_date", "course_id", "shs_strand_id", "academic_year", "email", "phone"],
            },
            {
                id: "contacts",
                title: isBasicEdGuardianRequired ? "Parent & Guardian" : "Address & Contacts",
                subtitle: isBasicEdGuardianRequired ? "Required parent/guardian & home address" : "Addresses, emergency contact & family",
                icon: <Users className="size-4" />,
                fields: ["current_address", "permanent_address", "emergency_contact_name", "emergency_contact_phone", "guardian_name", "guardian_contact", "fathers_name", "mothers_name"],
            },
            {
                id: "profile",
                title: "Education & Profile",
                subtitle: "Prior schooling, personal details & custom fields",
                icon: <BookOpen className="size-4" />,
                fields: ["birthplace", "civil_status", "nationality", "citizenship", "religion", "elementary_school", "junior_high_school_name", "senior_high_name", "college_school", "vocational_school", "remarks"],
            },
        ];

        if (config.philippine_reporting) {
            list.push({
                id: "reporting",
                title: "Reporting & Grants",
                subtitle: "Regional origin, demographics, income & scholarship",
                icon: <Flag className="size-4" />,
                fields: ["region_of_origin", "province_of_origin", "city_of_origin", "scholarship_type", "family_income_bracket"],
            });
        }

        list.push({
            id: "documents",
            title: "Assets & Documents",
            subtitle: "Photo, signature & initial required checklist files",
            icon: <FileCheck2 className="size-4" />,
            fields: ["picture_1x1", "signature", "documents"],
        });

        return list;
    }, [config.philippine_reporting, isShs, isTesda, isBasicEdGuardianRequired]);

    const currentStep = steps[Math.min(activeStepIndex, steps.length - 1)];

    // Year levels for selected student type
    const yearLevels = useMemo(() => {
        const list = config.year_levels?.[data.student_type];
        if (list && list.length > 0) return list;
        return isShs
            ? [
                  { value: 11, label: "Grade 11" },
                  { value: 12, label: "Grade 12" },
              ]
            : [
                  { value: 1, label: "1st Year" },
                  { value: 2, label: "2nd Year" },
                  { value: 3, label: "3rd Year" },
                  { value: 4, label: "4th Year" },
              ];
    }, [config.year_levels, data.student_type, isShs]);

    // Filter course groups by pathway (TESDA vs College/Program)
    const filteredCourseGroups = useMemo(() => {
        if (isTesda) {
            const tesda = options.courses.filter((g) => g.pathway === "tesda_qualification");
            return tesda.length > 0 ? tesda : options.courses;
        }
        const programs = options.courses.filter((g) => g.pathway === "program");
        return programs.length > 0 ? programs : options.courses;
    }, [options.courses, isTesda]);

    const checklistSlots = useMemo(() => {
        return options.document_checklists?.[data.student_type] ?? [];
    }, [options.document_checklists, data.student_type]);

    const activeIncomeBrackets = useMemo(() => {
        const modeObj = options.income_modes.find((m) => m.value === data.income_bracket_mode) ?? options.income_modes[0];
        return modeObj?.brackets ?? [];
    }, [options.income_modes, data.income_bracket_mode]);

    // Sync year level when switching student_type in create mode
    useEffect(() => {
        if (!yearLevels.some((lvl) => String(lvl.value) === String(data.academic_year))) {
            setData("academic_year", String(yearLevels[0]?.value ?? (isShs ? 11 : 1)));
        }
    }, [yearLevels, data.academic_year, isShs, setData]);

    // Fetch live preview of next automatic Student ID in Create mode
    const fetchNextIdPreview = useCallback(async (studentType: string) => {
        if (isEdit || policy.assignment_mode === "manual") return;
        setLoadingIdPreview(true);
        try {
            const res = await axios.get("/administrators/students/generate-id", {
                params: { student_type: studentType },
                headers: { Accept: "application/json" },
            });
            setPreviewId(String(res.data?.student_id ?? res.data?.id ?? ""));
        } catch {
            setPreviewId("");
        } finally {
            setLoadingIdPreview(false);
        }
    }, [isEdit, policy.assignment_mode]);

    useEffect(() => {
        if (!isEdit && !usesExternalAsId && data.identifier_assignment === "automatic") {
            void fetchNextIdPreview(data.student_type);
        }
    }, [isEdit, usesExternalAsId, data.identifier_assignment, data.student_type, fetchNextIdPreview]);

    // Reliable local draft recovery banner in Create mode
    useEffect(() => {
        if (isEdit || typeof window === "undefined") return;
        try {
            const raw = window.localStorage.getItem(draftStorageKey);
            if (raw) {
                const parsed = JSON.parse(raw) as Record<string, unknown>;
                if (parsed && (parsed.first_name || parsed.last_name || parsed.email)) {
                    setSavedDraftBanner(parsed);
                }
            }
        } catch {
            // ignore
        }
    }, [isEdit, draftStorageKey]);

    useEffect(() => {
        if (isEdit || !isDirty || typeof window === "undefined") return;
        const timer = setTimeout(() => {
            try {
                const { picture_1x1, signature, documents, ...serializable } = data;
                void picture_1x1;
                void signature;
                void documents;
                window.localStorage.setItem(draftStorageKey, JSON.stringify(serializable));
            } catch {
                // ignore
            }
        }, 900);
        return () => clearTimeout(timer);
    }, [isEdit, isDirty, data, draftStorageKey]);

    // Jump to first step containing validation errors on submit failure
    const errorStepCounts = useMemo(() => {
        const errorKeys = Object.keys(errors);
        const map: Record<string, number> = {};
        for (const step of steps) {
            map[step.id] = errorKeys.filter(
                (key) => step.fields.some((f) => key === f || key.startsWith(`${f}.`)) || (step.id === "profile" && key.startsWith("custom_fields.")),
            ).length;
        }
        return map;
    }, [errors, steps]);

    const handleSubmit = (submitAction: "view" | "create_another" | "create_enrollment" | "stay" = "view") => {
        if (isEdit && student) {
            form.transform((payload) => ({
                ...payload,
                _method: "put",
                stay: submitAction === "stay",
                guardian_name: payload.guardian_name || payload.emergency_contact_name,
                guardian_contact: payload.guardian_contact || payload.emergency_contact_phone,
                guardian_relationship: payload.guardian_relationship || payload.emergency_contact_relationship,
            }));
            form.post(route("administrators.students.update", student.id), {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: () => {
                    toast.success("Student record updated.");
                },
                onError: (errs) => {
                    toast.error(Object.values(errs)[0] || "Please review highlighted fields.");
                    const firstBadStep = steps.findIndex((step) =>
                        Object.keys(errs).some(
                            (k) => step.fields.some((f) => k === f || k.startsWith(`${f}.`)) || (step.id === "profile" && k.startsWith("custom_fields.")),
                        ),
                    );
                    if (firstBadStep >= 0) setActiveStepIndex(firstBadStep);
                },
            });
            return;
        }

        form.transform((payload) => ({
            ...payload,
            submit_action: submitAction === "stay" ? "view" : submitAction,
            guardian_name: payload.guardian_name || payload.emergency_contact_name,
            guardian_contact: payload.guardian_contact || payload.emergency_contact_phone,
            guardian_relationship: payload.guardian_relationship || payload.emergency_contact_relationship,
        }));

        form.post(route("administrators.students.store"), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                try {
                    window.localStorage.removeItem(draftStorageKey);
                } catch {
                    // ignore
                }
                if (submitAction === "create_another") {
                    form.reset();
                    setActiveStepIndex(0);
                    setPhotoPreviewUrl(null);
                    setSignaturePreviewUrl(null);
                    toast.success("Student saved! Ready for the next student.");
                }
            },
            onError: (errs) => {
                toast.error(Object.values(errs)[0] || "Please review highlighted fields.");
                const firstBadStep = steps.findIndex((step) =>
                    Object.keys(errs).some(
                        (k) => step.fields.some((f) => k === f || k.startsWith(`${f}.`)) || (step.id === "profile" && k.startsWith("custom_fields.")),
                    ),
                );
                if (firstBadStep >= 0) setActiveStepIndex(firstBadStep);
            },
        });
    };

    // Keyboard shortcut Ctrl/Cmd + S to save
    useEffect(() => {
        const onKeyDown = (e: KeyboardEvent) => {
            if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === "s") {
                e.preventDefault();
                if (!processing) handleSubmit(isEdit ? "stay" : "view");
            }
        };
        window.addEventListener("keydown", onKeyDown);
        return () => window.removeEventListener("keydown", onKeyDown);
    });

    const computedAge = computeAge(data.birth_date);

    return (
        <div className="space-y-5 pb-24">
            {/* Draft Restore Banner (Create mode) */}
            {!isEdit && savedDraftBanner && (
                <Alert className="border-primary/30 bg-primary/5 flex flex-col items-start justify-between gap-3 sm:flex-row sm:items-center">
                    <div className="flex items-start gap-2.5">
                        <RotateCcw className="text-primary mt-0.5 size-4 shrink-0" />
                        <div>
                            <AlertTitle className="text-xs font-semibold">Unsaved draft found</AlertTitle>
                            <AlertDescription className="text-xs">
                                Resume your draft for{" "}
                                <strong>
                                    {String(savedDraftBanner.first_name || "")} {String(savedDraftBanner.last_name || "")}
                                </strong>
                                ?
                            </AlertDescription>
                        </div>
                    </div>
                    <div className="flex shrink-0 items-center gap-2">
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            className="h-7 text-xs"
                            onClick={() => {
                                window.localStorage.removeItem(draftStorageKey);
                                setSavedDraftBanner(null);
                            }}
                        >
                            Discard
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            className="h-7 text-xs"
                            onClick={() => {
                                setData((prev) => ({ ...prev, ...savedDraftBanner }));
                                setSavedDraftBanner(null);
                                toast.success("Draft restored.");
                            }}
                        >
                            Restore Draft
                        </Button>
                    </div>
                </Alert>
            )}

            {/* Dynamic School-Adaptive Stepper Navigation */}
            <div className="border-border/70 bg-card rounded-xl border p-3">
                <div className="grid grid-cols-2 gap-2 md:grid-cols-4 lg:grid-cols-5">
                    {steps.map((step, idx) => {
                        const isActive = idx === activeStepIndex;
                        const isCompleted = !isEdit && idx < activeStepIndex;
                        const errCount = errorStepCounts[step.id] ?? 0;

                        return (
                            <button
                                key={step.id}
                                type="button"
                                onClick={() => setActiveStepIndex(idx)}
                                className={cn(
                                    "flex items-center gap-2.5 rounded-lg border px-3 py-2.5 text-left transition-all",
                                    isActive
                                        ? "border-primary bg-primary/8 text-foreground shadow-2xs"
                                        : errCount > 0
                                          ? "border-destructive/50 bg-destructive/5 text-foreground"
                                          : "border-transparent hover:bg-muted/60 text-muted-foreground hover:text-foreground",
                                )}
                            >
                                <span
                                    className={cn(
                                        "flex size-7 shrink-0 items-center justify-center rounded-full text-xs font-semibold",
                                        errCount > 0
                                            ? "bg-destructive text-destructive-foreground"
                                            : isActive
                                              ? "bg-primary text-primary-foreground"
                                              : isCompleted
                                                ? "bg-emerald-500/15 text-emerald-700 dark:text-emerald-300"
                                                : "bg-muted text-muted-foreground",
                                    )}
                                >
                                    {errCount > 0 ? errCount : isCompleted ? <Check className="size-3.5" /> : idx + 1}
                                </span>
                                <div className="min-w-0">
                                    <p className="truncate text-xs font-semibold">{step.title}</p>
                                    <p className="text-muted-foreground hidden truncate text-[10px] xl:block">{step.subtitle}</p>
                                </div>
                            </button>
                        );
                    })}
                </div>
            </div>

            {/* Form Body + Right Context Rail */}
            <div className="grid grid-cols-1 items-start gap-5 xl:grid-cols-[minmax(0,1fr)_320px]">
                <div className="min-w-0 space-y-5">
                    {/* STEP 1: IDENTITY & PLACEMENT */}
                    {currentStep.id === "identity" && (
                        <Card className="border-border/70">
                            <CardHeader className="pb-4">
                                <CardTitle className="text-base">{currentStep.title}</CardTitle>
                                <CardDescription className="text-xs">{currentStep.subtitle}</CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-5">
                                {/* Category & Status */}
                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    {options.types.length > 1 && (
                                        <div className="space-y-1.5">
                                            <Label className="text-xs font-medium">
                                                Student Category <span className="text-destructive">*</span>
                                            </Label>
                                            <div className="flex flex-wrap gap-1.5">
                                                {options.types.map((t) => (
                                                    <button
                                                        key={t.value}
                                                        type="button"
                                                        onClick={() => setData("student_type", t.value)}
                                                        className={cn(
                                                            "rounded-lg border px-3 py-1.5 text-xs font-medium transition-colors",
                                                            data.student_type === t.value
                                                                ? "border-primary bg-primary text-primary-foreground"
                                                                : "border-border bg-background hover:bg-muted",
                                                        )}
                                                    >
                                                        {t.label}
                                                    </button>
                                                ))}
                                            </div>
                                            {errors.student_type && <p className="text-destructive text-xs">{errors.student_type}</p>}
                                        </div>
                                    )}

                                    <div className="space-y-1.5">
                                        <Label className="text-xs font-medium">
                                            Record Status <span className="text-destructive">*</span>
                                        </Label>
                                        <Select value={data.status} onValueChange={(v) => setData("status", v)}>
                                            <SelectTrigger className="h-9 text-xs">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {options.statuses.map((s) => (
                                                    <SelectItem key={s.value} value={s.value}>
                                                        {s.label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        {errors.status && <p className="text-destructive text-xs">{errors.status}</p>}
                                    </div>
                                </div>

                                {/* Identifier Policy & External ID */}
                                <div className="bg-muted/25 border-border/70 grid grid-cols-1 gap-4 rounded-xl border p-4 sm:grid-cols-2">
                                    {!usesExternalAsId && (
                                        <div className="space-y-1.5">
                                            <div className="flex items-center justify-between">
                                                <Label htmlFor="sf-student-id" className="text-xs font-medium">
                                                    {idLabel}
                                                    {(isEdit || data.identifier_assignment === "manual") && (
                                                        <span className="text-destructive"> *</span>
                                                    )}
                                                </Label>
                                                {!isEdit && policy.assignment_mode === "hybrid" && (
                                                    <div className="flex items-center gap-1 text-[11px]">
                                                        <button
                                                            type="button"
                                                            onClick={() => setData("identifier_assignment", "automatic")}
                                                            className={cn(
                                                                "rounded px-2 py-0.5",
                                                                data.identifier_assignment === "automatic"
                                                                    ? "bg-primary text-primary-foreground"
                                                                    : "text-muted-foreground hover:bg-muted",
                                                            )}
                                                        >
                                                            Auto
                                                        </button>
                                                        <button
                                                            type="button"
                                                            onClick={() => setData("identifier_assignment", "manual")}
                                                            className={cn(
                                                                "rounded px-2 py-0.5",
                                                                data.identifier_assignment === "manual"
                                                                    ? "bg-primary text-primary-foreground"
                                                                    : "text-muted-foreground hover:bg-muted",
                                                            )}
                                                        >
                                                            Manual
                                                        </button>
                                                    </div>
                                                )}
                                            </div>
                                            {!isEdit && data.identifier_assignment === "automatic" ? (
                                                <div className="flex items-center gap-2">
                                                    <Input
                                                        readOnly
                                                        value={loadingIdPreview ? "Generating preview..." : previewId || "Assigned on save"}
                                                        className="bg-background h-9 font-mono text-xs"
                                                    />
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        size="icon"
                                                        className="size-9 shrink-0"
                                                        onClick={() => void fetchNextIdPreview(data.student_type)}
                                                        title="Refresh ID preview"
                                                    >
                                                        <RefreshCw className={cn("size-3.5", loadingIdPreview && "animate-spin")} />
                                                    </Button>
                                                </div>
                                            ) : (
                                                <Input
                                                    id="sf-student-id"
                                                    value={data.student_id}
                                                    onChange={(e) => setData("student_id", e.target.value)}
                                                    placeholder={`Enter ${idLabel}`}
                                                    className="bg-background h-9 font-mono text-xs"
                                                />
                                            )}
                                            {errors.student_id && <p className="text-destructive text-xs">{errors.student_id}</p>}
                                        </div>
                                    )}

                                    {externalEnabled && (
                                        <div className="space-y-1.5">
                                            <Label htmlFor="sf-lrn" className="text-xs font-medium">
                                                {externalLabel}
                                                {(config.external_identifier.required || usesExternalAsId) && (
                                                    <span className="text-destructive"> *</span>
                                                )}
                                            </Label>
                                            <Input
                                                id="sf-lrn"
                                                value={data.lrn}
                                                onChange={(e) => setData("lrn", e.target.value)}
                                                placeholder={`Enter ${externalLabel}`}
                                                className="bg-background h-9 font-mono text-xs"
                                            />
                                            {config.external_identifier.help_text && (
                                                <p className="text-muted-foreground text-[11px]">{config.external_identifier.help_text}</p>
                                            )}
                                            {errors.lrn && <p className="text-destructive text-xs">{errors.lrn}</p>}
                                        </div>
                                    )}
                                </div>

                                {/* Core Legal Name */}
                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-12">
                                    <div className="space-y-1.5 sm:col-span-4">
                                        <Label htmlFor="sf-first-name" className="text-xs font-medium">
                                            First Name <span className="text-destructive">*</span>
                                        </Label>
                                        <Input
                                            id="sf-first-name"
                                            value={data.first_name}
                                            onChange={(e) => setData("first_name", e.target.value)}
                                            className="h-9 text-xs"
                                        />
                                        {errors.first_name && <p className="text-destructive text-xs">{errors.first_name}</p>}
                                    </div>
                                    <div className="space-y-1.5 sm:col-span-3">
                                        <Label htmlFor="sf-middle-name" className="text-xs font-medium">
                                            Middle Name
                                        </Label>
                                        <Input
                                            id="sf-middle-name"
                                            value={data.middle_name}
                                            onChange={(e) => setData("middle_name", e.target.value)}
                                            className="h-9 text-xs"
                                        />
                                        {errors.middle_name && <p className="text-destructive text-xs">{errors.middle_name}</p>}
                                    </div>
                                    <div className="space-y-1.5 sm:col-span-3">
                                        <Label htmlFor="sf-last-name" className="text-xs font-medium">
                                            Last Name <span className="text-destructive">*</span>
                                        </Label>
                                        <Input
                                            id="sf-last-name"
                                            value={data.last_name}
                                            onChange={(e) => setData("last_name", e.target.value)}
                                            className="h-9 text-xs"
                                        />
                                        {errors.last_name && <p className="text-destructive text-xs">{errors.last_name}</p>}
                                    </div>
                                    <div className="space-y-1.5 sm:col-span-2">
                                        <Label htmlFor="sf-suffix" className="text-xs font-medium">
                                            Suffix
                                        </Label>
                                        <Input
                                            id="sf-suffix"
                                            value={data.suffix}
                                            onChange={(e) => setData("suffix", e.target.value)}
                                            placeholder="Jr., III"
                                            className="h-9 text-xs"
                                        />
                                    </div>
                                </div>

                                {/* Gender, DOB, Age, Email, Phone */}
                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-12">
                                    <div className="space-y-1.5 sm:col-span-4">
                                        <Label className="text-xs font-medium">
                                            Gender <span className="text-destructive">*</span>
                                        </Label>
                                        <Select value={data.gender} onValueChange={(v) => setData("gender", v)}>
                                            <SelectTrigger className="h-9 text-xs">
                                                <SelectValue placeholder="Select gender" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="male">Male</SelectItem>
                                                <SelectItem value="female">Female</SelectItem>
                                                <SelectItem value="other">Other</SelectItem>
                                                <SelectItem value="prefer_not_to_say">Prefer not to say</SelectItem>
                                            </SelectContent>
                                        </Select>
                                        {errors.gender && <p className="text-destructive text-xs">{errors.gender}</p>}
                                    </div>
                                    <div className="space-y-1.5 sm:col-span-5">
                                        <Label htmlFor="sf-birth-date" className="text-xs font-medium">
                                            Birth Date <span className="text-destructive">*</span>
                                        </Label>
                                        <Input
                                            id="sf-birth-date"
                                            type="date"
                                            value={data.birth_date}
                                            onChange={(e) => setData("birth_date", e.target.value)}
                                            className="h-9 text-xs"
                                        />
                                        {errors.birth_date && <p className="text-destructive text-xs">{errors.birth_date}</p>}
                                    </div>
                                    <div className="space-y-1.5 sm:col-span-3">
                                        <Label className="text-xs font-medium">Age</Label>
                                        <Input readOnly value={computedAge ? `${computedAge} yrs` : "—"} className="bg-muted/40 h-9 text-xs" />
                                    </div>
                                </div>

                                {(fieldVisible("email") || fieldVisible("phone")) && (
                                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                        {fieldVisible("email") && (
                                            <div className="space-y-1.5">
                                                <Label htmlFor="sf-email" className="text-xs font-medium">
                                                    {fieldLabel("email", "Email Address")}
                                                    {fieldRequired("email") && <span className="text-destructive"> *</span>}
                                                </Label>
                                                <Input
                                                    id="sf-email"
                                                    type="email"
                                                    value={data.email}
                                                    onChange={(e) => setData("email", e.target.value)}
                                                    placeholder="student@school.edu"
                                                    className="h-9 text-xs"
                                                />
                                                {errors.email && <p className="text-destructive text-xs">{errors.email}</p>}
                                            </div>
                                        )}
                                        {fieldVisible("phone") && (
                                            <div className="space-y-1.5">
                                                <Label htmlFor="sf-phone" className="text-xs font-medium">
                                                    {fieldLabel("phone", "Phone Number")}
                                                    {fieldRequired("phone") && <span className="text-destructive"> *</span>}
                                                </Label>
                                                <Input
                                                    id="sf-phone"
                                                    value={data.phone}
                                                    onChange={(e) => setData("phone", e.target.value)}
                                                    placeholder="+63 9XX XXX XXXX"
                                                    className="h-9 text-xs"
                                                />
                                                {errors.phone && <p className="text-destructive text-xs">{errors.phone}</p>}
                                            </div>
                                        )}
                                    </div>
                                )}

                                <Separator />

                                {/* Academic Placement */}
                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                    <div className="space-y-1.5 sm:col-span-2">
                                        <Label className="text-xs font-medium">
                                            {isShs ? "Senior High Strand" : isTesda ? "TESDA Qualification" : "Program / Course"}{" "}
                                            <span className="text-destructive">*</span>
                                        </Label>
                                        {isShs ? (
                                            <Select value={data.shs_strand_id} onValueChange={(v) => setData("shs_strand_id", v)}>
                                                <SelectTrigger className="h-9 text-xs">
                                                    <SelectValue placeholder="Choose SHS strand" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {options.shs_strands.map((s) => (
                                                        <SelectItem key={s.value} value={String(s.value)}>
                                                            {s.label}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        ) : (
                                            <Select value={data.course_id} onValueChange={(v) => setData("course_id", v)}>
                                                <SelectTrigger className="h-9 text-xs">
                                                    <SelectValue placeholder="Choose program" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {filteredCourseGroups.flatMap((group) =>
                                                        group.items.map((item) => (
                                                            <SelectItem key={item.value} value={String(item.value)}>
                                                                {item.label}
                                                            </SelectItem>
                                                        )),
                                                    )}
                                                </SelectContent>
                                            </Select>
                                        )}
                                        {(errors.course_id || errors.shs_strand_id) && (
                                            <p className="text-destructive text-xs">{errors.course_id || errors.shs_strand_id}</p>
                                        )}
                                    </div>

                                    <div className="space-y-1.5">
                                        <Label className="text-xs font-medium">
                                            {isShs ? "Grade Level" : "Academic Level"} <span className="text-destructive">*</span>
                                        </Label>
                                        <Select value={data.academic_year} onValueChange={(v) => setData("academic_year", v)}>
                                            <SelectTrigger className="h-9 text-xs">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {yearLevels.map((lvl) => (
                                                    <SelectItem key={lvl.value} value={String(lvl.value)}>
                                                        {lvl.label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        {errors.academic_year && <p className="text-destructive text-xs">{errors.academic_year}</p>}
                                    </div>
                                </div>

                                {/* Optional Portal Provisioning during Create */}
                                {!isEdit && fieldVisible("email") && (
                                    <div className="border-border/70 bg-muted/20 space-y-2 rounded-lg border p-3.5">
                                        <label className="flex items-center gap-2 text-xs font-medium">
                                            <Checkbox
                                                checked={data.provision_portal_account}
                                                onCheckedChange={(checked) => setData("provision_portal_account", Boolean(checked))}
                                            />
                                            <span>Auto-provision Student Portal account on save (uses Email above)</span>
                                        </label>
                                        {data.provision_portal_account && (
                                            <label className="text-muted-foreground flex items-center gap-2 pl-6 text-[11px]">
                                                <Checkbox
                                                    checked={data.send_activation_link}
                                                    onCheckedChange={(checked) => setData("send_activation_link", Boolean(checked))}
                                                />
                                                <span>Send password setup &amp; welcome email immediately</span>
                                            </label>
                                        )}
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    )}

                    {/* STEP 2: CONTACTS, ADDRESS & GUARDIAN */}
                    {currentStep.id === "contacts" && (
                        <Card className="border-border/70">
                            <CardHeader className="pb-4">
                                <div className="flex items-center justify-between gap-2">
                                    <div>
                                        <CardTitle className="text-base">{currentStep.title}</CardTitle>
                                        <CardDescription className="text-xs">{currentStep.subtitle}</CardDescription>
                                    </div>
                                    {isBasicEdGuardianRequired && (
                                        <Badge variant="secondary" className="text-[11px]">
                                            Guardian Recommended for Basic Ed
                                        </Badge>
                                    )}
                                </div>
                            </CardHeader>
                            <CardContent className="space-y-5">
                                {(fieldVisible("current_address") || fieldVisible("permanent_address")) && (
                                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                        {fieldVisible("current_address") && (
                                            <div className="space-y-1.5">
                                                <div className="flex items-center justify-between">
                                                    <Label htmlFor="sf-curr-addr" className="text-xs font-medium">
                                                        {fieldLabel("current_address", "Current Address")}
                                                        {fieldRequired("current_address") && <span className="text-destructive"> *</span>}
                                                    </Label>
                                                    {fieldVisible("permanent_address") && data.permanent_address && (
                                                        <button
                                                            type="button"
                                                            onClick={() => setData("current_address", data.permanent_address)}
                                                            className="text-primary inline-flex items-center gap-1 text-[11px] hover:underline"
                                                        >
                                                            <Copy className="size-3" />
                                                            Same as permanent
                                                        </button>
                                                    )}
                                                </div>
                                                <Textarea
                                                    id="sf-curr-addr"
                                                    value={data.current_address}
                                                    onChange={(e) => setData("current_address", e.target.value)}
                                                    rows={2}
                                                    className="text-xs"
                                                />
                                                {errors.current_address && <p className="text-destructive text-xs">{errors.current_address}</p>}
                                            </div>
                                        )}
                                        {fieldVisible("permanent_address") && (
                                            <div className="space-y-1.5">
                                                <Label htmlFor="sf-perm-addr" className="text-xs font-medium">
                                                    {fieldLabel("permanent_address", "Permanent Address")}
                                                    {fieldRequired("permanent_address") && <span className="text-destructive"> *</span>}
                                                </Label>
                                                <Textarea
                                                    id="sf-perm-addr"
                                                    value={data.permanent_address}
                                                    onChange={(e) => setData("permanent_address", e.target.value)}
                                                    rows={2}
                                                    className="text-xs"
                                                />
                                                {errors.permanent_address && <p className="text-destructive text-xs">{errors.permanent_address}</p>}
                                            </div>
                                        )}
                                    </div>
                                )}

                                <Separator />

                                {/* Unified Guardian / Emergency Contact (single source of truth!) */}
                                <div className="space-y-3">
                                    <h3 className="text-xs font-semibold tracking-wide uppercase">
                                        Primary Guardian / Emergency Contact
                                    </h3>
                                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                        <div className="space-y-1.5">
                                            <Label htmlFor="sf-guard-name" className="text-xs font-medium">
                                                Full Name
                                            </Label>
                                            <Input
                                                id="sf-guard-name"
                                                value={data.emergency_contact_name}
                                                onChange={(e) => {
                                                    setData("emergency_contact_name", e.target.value);
                                                    setData("guardian_name", e.target.value);
                                                }}
                                                className="h-9 text-xs"
                                            />
                                        </div>
                                        <div className="space-y-1.5">
                                            <Label className="text-xs font-medium">Relationship</Label>
                                            <Select
                                                value={data.emergency_contact_relationship || "__none__"}
                                                onValueChange={(v) => {
                                                    const val = v === "__none__" ? "" : v;
                                                    setData("emergency_contact_relationship", val);
                                                    setData("guardian_relationship", val);
                                                }}
                                            >
                                                <SelectTrigger className="h-9 text-xs">
                                                    <SelectValue placeholder="Select relationship" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="__none__">Select relationship</SelectItem>
                                                    {RELATIONSHIP_OPTIONS.map((rel) => (
                                                        <SelectItem key={rel} value={rel}>
                                                            {rel}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        </div>
                                        <div className="space-y-1.5">
                                            <Label htmlFor="sf-guard-phone" className="text-xs font-medium">
                                                Contact Phone
                                            </Label>
                                            <Input
                                                id="sf-guard-phone"
                                                value={data.emergency_contact_phone}
                                                onChange={(e) => {
                                                    setData("emergency_contact_phone", e.target.value);
                                                    setData("guardian_contact", e.target.value);
                                                }}
                                                className="h-9 text-xs"
                                            />
                                        </div>
                                        <div className="space-y-1.5">
                                            <Label htmlFor="sf-guard-email" className="text-xs font-medium">
                                                Guardian Email
                                            </Label>
                                            <Input
                                                id="sf-guard-email"
                                                type="email"
                                                value={data.guardian_email}
                                                onChange={(e) => setData("guardian_email", e.target.value)}
                                                className="h-9 text-xs"
                                            />
                                        </div>
                                        <div className="space-y-1.5 sm:col-span-2">
                                            <Label htmlFor="sf-guard-addr" className="text-xs font-medium">
                                                Guardian / Family Address
                                            </Label>
                                            <Input
                                                id="sf-guard-addr"
                                                value={data.emergency_contact_address}
                                                onChange={(e) => {
                                                    setData("emergency_contact_address", e.target.value);
                                                    setData("family_address", e.target.value);
                                                }}
                                                className="h-9 text-xs"
                                            />
                                        </div>
                                    </div>
                                </div>

                                <Separator />

                                {/* Parents */}
                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <div className="space-y-3 rounded-lg border p-3.5">
                                        <p className="text-xs font-semibold">Father&apos;s Details</p>
                                        <Input
                                            placeholder="Father's full name"
                                            value={data.fathers_name}
                                            onChange={(e) => setData("fathers_name", e.target.value)}
                                            className="h-8 text-xs"
                                        />
                                        <Input
                                            placeholder="Occupation"
                                            value={data.father_occupation}
                                            onChange={(e) => setData("father_occupation", e.target.value)}
                                            className="h-8 text-xs"
                                        />
                                        <Input
                                            placeholder="Contact phone"
                                            value={data.father_contact}
                                            onChange={(e) => setData("father_contact", e.target.value)}
                                            className="h-8 text-xs"
                                        />
                                    </div>
                                    <div className="space-y-3 rounded-lg border p-3.5">
                                        <p className="text-xs font-semibold">Mother&apos;s Details</p>
                                        <Input
                                            placeholder="Mother's full name"
                                            value={data.mothers_name}
                                            onChange={(e) => setData("mothers_name", e.target.value)}
                                            className="h-8 text-xs"
                                        />
                                        <Input
                                            placeholder="Occupation"
                                            value={data.mother_occupation}
                                            onChange={(e) => setData("mother_occupation", e.target.value)}
                                            className="h-8 text-xs"
                                        />
                                        <Input
                                            placeholder="Contact phone"
                                            value={data.mother_contact}
                                            onChange={(e) => setData("mother_contact", e.target.value)}
                                            className="h-8 text-xs"
                                        />
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    )}

                    {/* STEP 3: EDUCATION, PERSONAL & CUSTOM FIELDS */}
                    {currentStep.id === "profile" && (
                        <div className="space-y-5">
                            <Card className="border-border/70">
                                <CardHeader className="pb-4">
                                    <CardTitle className="text-base">Personal &amp; Background Details</CardTitle>
                                    <CardDescription className="text-xs">
                                        Only fields enabled in your school&apos;s Student Configuration are displayed.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-5">
                                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                        {fieldVisible("civil_status") && (
                                            <div className="space-y-1.5">
                                                <Label className="text-xs font-medium">{fieldLabel("civil_status", "Civil Status")}</Label>
                                                <Select value={data.civil_status || "single"} onValueChange={(v) => setData("civil_status", v)}>
                                                    <SelectTrigger className="h-9 text-xs">
                                                        <SelectValue />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem value="single">Single</SelectItem>
                                                        <SelectItem value="married">Married</SelectItem>
                                                        <SelectItem value="widowed">Widowed</SelectItem>
                                                        <SelectItem value="separated">Separated</SelectItem>
                                                    </SelectContent>
                                                </Select>
                                            </div>
                                        )}
                                        {fieldVisible("nationality") && (
                                            <div className="space-y-1.5">
                                                <Label className="text-xs font-medium">{fieldLabel("nationality", "Nationality")}</Label>
                                                <Input
                                                    value={data.nationality}
                                                    onChange={(e) => setData("nationality", e.target.value)}
                                                    className="h-9 text-xs"
                                                />
                                            </div>
                                        )}
                                        {fieldVisible("citizenship") && (
                                            <div className="space-y-1.5">
                                                <Label className="text-xs font-medium">{fieldLabel("citizenship", "Citizenship")}</Label>
                                                <Input
                                                    value={data.citizenship}
                                                    onChange={(e) => setData("citizenship", e.target.value)}
                                                    className="h-9 text-xs"
                                                />
                                            </div>
                                        )}
                                        {fieldVisible("birthplace") && (
                                            <div className="space-y-1.5">
                                                <Label className="text-xs font-medium">{fieldLabel("birthplace", "Birthplace")}</Label>
                                                <Input
                                                    value={data.birthplace}
                                                    onChange={(e) => setData("birthplace", e.target.value)}
                                                    className="h-9 text-xs"
                                                />
                                            </div>
                                        )}
                                        {fieldVisible("religion") && (
                                            <div className="space-y-1.5">
                                                <Label className="text-xs font-medium">{fieldLabel("religion", "Religion")}</Label>
                                                <Input
                                                    value={data.religion}
                                                    onChange={(e) => setData("religion", e.target.value)}
                                                    className="h-9 text-xs"
                                                />
                                            </div>
                                        )}
                                    </div>

                                    <Separator />

                                    {/* Prior Schooling adapted by student_type */}
                                    <div className="space-y-3">
                                        <h3 className="text-xs font-semibold tracking-wide uppercase">Prior Academic History</h3>
                                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                            {isShs ? (
                                                <>
                                                    <div className="space-y-2 rounded-lg border p-3">
                                                        <p className="text-xs font-semibold">Junior High School</p>
                                                        <Input
                                                            placeholder="School name"
                                                            value={data.junior_high_school_name}
                                                            onChange={(e) => setData("junior_high_school_name", e.target.value)}
                                                            className="h-8 text-xs"
                                                        />
                                                        <Input
                                                            placeholder="Year graduated (e.g. 2024)"
                                                            value={data.junior_high_graduation_year}
                                                            onChange={(e) => setData("junior_high_graduation_year", e.target.value)}
                                                            className="h-8 text-xs"
                                                        />
                                                    </div>
                                                    <div className="space-y-2 rounded-lg border p-3">
                                                        <p className="text-xs font-semibold">Elementary School</p>
                                                        <Input
                                                            placeholder="School name"
                                                            value={data.elementary_school}
                                                            onChange={(e) => setData("elementary_school", e.target.value)}
                                                            className="h-8 text-xs"
                                                        />
                                                        <Input
                                                            placeholder="Year graduated"
                                                            value={data.elementary_graduate_year}
                                                            onChange={(e) => setData("elementary_graduate_year", e.target.value)}
                                                            className="h-8 text-xs"
                                                        />
                                                    </div>
                                                </>
                                            ) : (
                                                <>
                                                    <div className="space-y-2 rounded-lg border p-3">
                                                        <p className="text-xs font-semibold">Senior High / Secondary School</p>
                                                        <Input
                                                            placeholder="School name"
                                                            value={data.senior_high_name}
                                                            onChange={(e) => setData("senior_high_name", e.target.value)}
                                                            className="h-8 text-xs"
                                                        />
                                                        <Input
                                                            placeholder="Year graduated"
                                                            value={data.senior_high_graduate_year}
                                                            onChange={(e) => setData("senior_high_graduate_year", e.target.value)}
                                                            className="h-8 text-xs"
                                                        />
                                                    </div>
                                                    <div className="space-y-2 rounded-lg border p-3">
                                                        <p className="text-xs font-semibold">Prior College / Vocational (Transferees)</p>
                                                        <Input
                                                            placeholder="Previous institution"
                                                            value={data.college_school}
                                                            onChange={(e) => setData("college_school", e.target.value)}
                                                            className="h-8 text-xs"
                                                        />
                                                        <Input
                                                            placeholder="Previous program / course"
                                                            value={data.college_course}
                                                            onChange={(e) => setData("college_course", e.target.value)}
                                                            className="h-8 text-xs"
                                                        />
                                                    </div>
                                                </>
                                            )}
                                        </div>
                                    </div>

                                    {/* Conditional Graduate Employment */}
                                    {data.status === "graduated" && (
                                        <div className="border-border/70 bg-muted/20 space-y-3 rounded-xl border p-4">
                                            <h3 className="flex items-center gap-1.5 text-xs font-semibold">
                                                <GraduationCap className="size-3.5" />
                                                Graduate Employment Tracking
                                            </h3>
                                            <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                                                <Select
                                                    value={data.employment_status}
                                                    onValueChange={(v) => setData("employment_status", v)}
                                                >
                                                    <SelectTrigger className="h-8 text-xs">
                                                        <SelectValue />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        {options.employment_statuses.map((opt) => (
                                                            <SelectItem key={opt.value} value={opt.value}>
                                                                {opt.label}
                                                            </SelectItem>
                                                        ))}
                                                    </SelectContent>
                                                </Select>
                                                <Input
                                                    placeholder="Employer name"
                                                    value={data.employer_name}
                                                    onChange={(e) => setData("employer_name", e.target.value)}
                                                    className="h-8 text-xs"
                                                />
                                                <Input
                                                    placeholder="Job position"
                                                    value={data.job_position}
                                                    onChange={(e) => setData("job_position", e.target.value)}
                                                    className="h-8 text-xs"
                                                />
                                            </div>
                                        </div>
                                    )}

                                    {/* Conditional Withdrawal / Dropout */}
                                    {(data.status === "withdrawn" || data.status === "dropped") && (
                                        <div className="space-y-3 rounded-xl border border-amber-500/35 bg-amber-500/5 p-4">
                                            <h3 className="text-xs font-semibold">Attrition / Exit Details</h3>
                                            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                                <Input
                                                    type="date"
                                                    value={data.withdrawal_date}
                                                    onChange={(e) => setData("withdrawal_date", e.target.value)}
                                                    className="h-8 text-xs"
                                                />
                                                <Select
                                                    value={data.attrition_category || "__none__"}
                                                    onValueChange={(v) => setData("attrition_category", v === "__none__" ? "" : v)}
                                                >
                                                    <SelectTrigger className="h-8 text-xs">
                                                        <SelectValue placeholder="Attrition category" />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem value="__none__">None</SelectItem>
                                                        {options.attrition_categories.map((c) => (
                                                            <SelectItem key={c.value} value={c.value}>
                                                                {c.label}
                                                            </SelectItem>
                                                        ))}
                                                    </SelectContent>
                                                </Select>
                                                <Textarea
                                                    placeholder="Reason for withdrawal or drop..."
                                                    value={data.withdrawal_reason}
                                                    onChange={(e) => setData("withdrawal_reason", e.target.value)}
                                                    rows={2}
                                                    className="text-xs sm:col-span-2"
                                                />
                                            </div>
                                        </div>
                                    )}

                                    <div className="space-y-1.5">
                                        <Label htmlFor="sf-remarks" className="text-xs font-medium">
                                            Registrar Remarks
                                        </Label>
                                        <Textarea
                                            id="sf-remarks"
                                            value={data.remarks}
                                            onChange={(e) => setData("remarks", e.target.value)}
                                            placeholder="Internal administrative notes..."
                                            rows={2}
                                            className="text-xs"
                                        />
                                    </div>
                                </CardContent>
                            </Card>

                            {/* Institution Custom Fields */}
                            <CustomStudentFields
                                fields={config.custom_fields ?? []}
                                values={data.custom_fields}
                                errors={errors as Record<string, string | undefined>}
                                editing={isEdit}
                                onChange={(key, value) =>
                                    setData("custom_fields", {
                                        ...data.custom_fields,
                                        [key]: value,
                                    })
                                }
                            />
                        </div>
                    )}

                    {/* STEP 4: REPORTING & GRANTS (ONLY WHEN PHILIPPINE REPORTING ENABLED) */}
                    {currentStep.id === "reporting" && (
                        <Card className="border-border/70">
                            <CardHeader className="pb-4">
                                <CardTitle className="text-base">Reporting Demographics &amp; Scholarships</CardTitle>
                                <CardDescription className="text-xs">
                                    Enabled by your school&apos;s Philippine reporting profile (CHED / DepEd / TESDA).
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-5">
                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                    <div className="space-y-1.5">
                                        <Label className="text-xs font-medium">Region of Origin</Label>
                                        <Select
                                            value={data.region_of_origin || "__none__"}
                                            onValueChange={(v) => setData("region_of_origin", v === "__none__" ? "" : v)}
                                        >
                                            <SelectTrigger className="h-9 text-xs">
                                                <SelectValue placeholder="Select region" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="__none__">None</SelectItem>
                                                {options.regions.map((r) => (
                                                    <SelectItem key={r.value} value={r.value}>
                                                        {r.label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div className="space-y-1.5">
                                        <Label className="text-xs font-medium">Province</Label>
                                        <Input
                                            value={data.province_of_origin}
                                            onChange={(e) => setData("province_of_origin", e.target.value)}
                                            className="h-9 text-xs"
                                        />
                                    </div>
                                    <div className="space-y-1.5">
                                        <Label className="text-xs font-medium">City / Municipality</Label>
                                        <Input
                                            value={data.city_of_origin}
                                            onChange={(e) => setData("city_of_origin", e.target.value)}
                                            className="h-9 text-xs"
                                        />
                                    </div>
                                </div>

                                <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
                                    {(
                                        [
                                            ["is_indigenous_person", "Indigenous Person (IP)"],
                                            ["is_pwd", "Person with Disability (PWD)"],
                                            ["is_solo_parent", "Solo Parent"],
                                            ["is_solo_parent_dependent", "Child of Solo Parent"],
                                            ["is_first_generation", "First-Gen College Student"],
                                            ["is_underprivileged", "Underprivileged / 4Ps"],
                                        ] as const
                                    ).map(([field, label]) => (
                                        <label
                                            key={field}
                                            className="border-border/70 flex items-center gap-2 rounded-lg border px-3 py-2 text-xs"
                                        >
                                            <Checkbox
                                                checked={Boolean(data[field])}
                                                onCheckedChange={(checked) => setData(field, Boolean(checked))}
                                            />
                                            <span>{label}</span>
                                        </label>
                                    ))}
                                </div>

                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <div className="space-y-1.5">
                                        <Label className="text-xs font-medium">Scholarship Program</Label>
                                        <Select value={data.scholarship_type} onValueChange={(v) => setData("scholarship_type", v)}>
                                            <SelectTrigger className="h-9 text-xs">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {options.scholarship_types.map((s) => (
                                                    <SelectItem key={s.value} value={s.value}>
                                                        {s.label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div className="space-y-1.5">
                                        <Label className="text-xs font-medium">Family Income Bracket</Label>
                                        <Select
                                            value={data.family_income_bracket || "__none__"}
                                            onValueChange={(v) => setData("family_income_bracket", v === "__none__" ? "" : v)}
                                        >
                                            <SelectTrigger className="h-9 text-xs">
                                                <SelectValue placeholder="Select income bracket" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="__none__">Not specified</SelectItem>
                                                {activeIncomeBrackets.map((b) => (
                                                    <SelectItem key={b.value} value={b.value}>
                                                        {b.label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    )}

                    {/* STEP 5: ASSETS & INITIAL DOCUMENTS */}
                    {currentStep.id === "documents" && (
                        <div className="space-y-5">
                            <Card className="border-border/70">
                                <CardHeader className="pb-4">
                                    <CardTitle className="text-base">ID Photo &amp; Signature</CardTitle>
                                    <CardDescription className="text-xs">
                                        Unified capture experience across both Create and Edit.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="grid grid-cols-1 gap-5 sm:grid-cols-2">
                                    {/* 1x1 Photo */}
                                    <div className="border-border/70 flex flex-col items-center justify-center rounded-xl border border-dashed p-5 text-center">
                                        <input
                                            ref={photoInputRef}
                                            type="file"
                                            accept=".jpg,.jpeg,.png,.webp"
                                            className="hidden"
                                            onChange={(e) => {
                                                const file = e.target.files?.[0];
                                                if (!file) return;
                                                setData("picture_1x1", file);
                                                setPhotoPreviewUrl(URL.createObjectURL(file));
                                            }}
                                        />
                                        {photoPreviewUrl ? (
                                            <img
                                                src={photoPreviewUrl}
                                                alt="Student 1x1 Preview"
                                                className="border-border mb-3 size-24 rounded-full border object-cover"
                                            />
                                        ) : (
                                            <div className="bg-muted mb-3 flex size-24 items-center justify-center rounded-full">
                                                <Camera className="text-muted-foreground size-7" />
                                            </div>
                                        )}
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            className="h-8 text-xs"
                                            onClick={() => photoInputRef.current?.click()}
                                        >
                                            {photoPreviewUrl ? "Replace 1x1 Photo" : "Upload 1x1 Photo"}
                                        </Button>
                                        {errors.picture_1x1 && <p className="text-destructive mt-1 text-xs">{errors.picture_1x1}</p>}
                                    </div>

                                    {/* Signature Pad */}
                                    {flags.studentSignaturePad && (
                                        <div className="border-border/70 flex flex-col items-center justify-center rounded-xl border border-dashed p-5 text-center">
                                            {signaturePreviewUrl ? (
                                                <img
                                                    src={signaturePreviewUrl}
                                                    alt="Student Signature"
                                                    className="mb-3 h-20 max-w-full rounded bg-white object-contain p-1.5"
                                                />
                                            ) : (
                                                <div className="bg-muted mb-3 flex h-20 w-full items-center justify-center rounded-lg">
                                                    <PenTool className="text-muted-foreground size-6" />
                                                </div>
                                            )}
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                className="h-8 gap-1.5 text-xs"
                                                onClick={() => setSignatureDialogOpen(true)}
                                            >
                                                <PenTool className="size-3.5" />
                                                {signaturePreviewUrl ? "Update Signature" : "Draw or Upload Signature"}
                                            </Button>
                                            {errors.signature && <p className="text-destructive mt-1 text-xs">{errors.signature}</p>}
                                        </div>
                                    )}
                                </CardContent>
                            </Card>

                            {/* Initial Required Document Attachments */}
                            {checklistSlots.filter((s) => s.key !== "picture_1x1").length > 0 && (
                                <Card className="border-border/70">
                                    <CardHeader className="pb-3">
                                        <CardTitle className="text-base">Attach Required Documents (Optional Now)</CardTitle>
                                        <CardDescription className="text-xs">
                                            Files attached here are saved and auto-verified immediately into the student&apos;s Document Studio.
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                        {checklistSlots
                                            .filter((s) => s.key !== "picture_1x1")
                                            .map((slot) => {
                                                const attached = data.documents[slot.key];
                                                return (
                                                    <div
                                                        key={slot.key}
                                                        className="border-border/70 flex items-center justify-between gap-2 rounded-xl border p-3"
                                                    >
                                                        <div className="min-w-0">
                                                            <div className="flex items-center gap-1.5">
                                                                <span className="truncate text-xs font-semibold">{slot.label}</span>
                                                                {slot.required && (
                                                                    <Badge variant="secondary" className="text-[10px]">
                                                                        Required
                                                                    </Badge>
                                                                )}
                                                            </div>
                                                            <p className="text-muted-foreground truncate text-[11px]">
                                                                {attached ? attached.name : "No file selected"}
                                                            </p>
                                                        </div>
                                                        <label className="border-border hover:bg-muted inline-flex h-8 shrink-0 cursor-pointer items-center gap-1.5 rounded-md border px-2.5 text-xs font-medium">
                                                            <UploadCloud className="size-3.5" />
                                                            {attached ? "Change" : "Attach"}
                                                            <input
                                                                type="file"
                                                                className="hidden"
                                                                accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx"
                                                                onChange={(e) => {
                                                                    const file = e.target.files?.[0];
                                                                    if (!file) return;
                                                                    setData("documents", {
                                                                        ...data.documents,
                                                                        [slot.key]: file,
                                                                    });
                                                                }}
                                                            />
                                                        </label>
                                                    </div>
                                                );
                                            })}
                                    </CardContent>
                                </Card>
                            )}
                        </div>
                    )}
                </div>

                {/* Right Context Rail */}
                <aside className="space-y-4 xl:sticky xl:top-4">
                    <Card className="border-border/70">
                        <CardHeader className="pb-3">
                            <CardTitle className="text-sm font-semibold">Live Record Summary</CardTitle>
                            <CardDescription className="text-xs">
                                {schoolProfile?.school_level_label || "School"} · {config.type_labels?.[data.student_type] || data.student_type}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-2.5 text-xs">
                            <div className="flex items-center justify-between">
                                <span className="text-muted-foreground">Name</span>
                                <span className="max-w-[170px] truncate font-medium">
                                    {[data.first_name, data.last_name].filter(Boolean).join(" ") || "—"}
                                </span>
                            </div>
                            <div className="flex items-center justify-between">
                                <span className="text-muted-foreground">{idLabel}</span>
                                <span className="font-mono font-medium">
                                    {usesExternalAsId
                                        ? data.lrn || "Uses External ID"
                                        : data.identifier_assignment === "automatic" && !isEdit
                                          ? previewId || "Auto"
                                          : data.student_id || "—"}
                                </span>
                            </div>
                            <div className="flex items-center justify-between">
                                <span className="text-muted-foreground">Status</span>
                                <Badge variant="outline" className="text-[10px] capitalize">
                                    {data.status.replace("_", " ")}
                                </Badge>
                            </div>
                            <div className="flex items-center justify-between">
                                <span className="text-muted-foreground">Level</span>
                                <span className="font-medium">
                                    {yearLevels.find((l) => String(l.value) === String(data.academic_year))?.label || data.academic_year}
                                </span>
                            </div>
                            {Object.keys(errors).length > 0 && (
                                <div className="border-destructive/40 bg-destructive/10 text-destructive mt-2 flex items-start gap-2 rounded-lg border p-2.5 text-[11px]">
                                    <AlertCircle className="mt-0.5 size-3.5 shrink-0" />
                                    <span>{Object.keys(errors).length} field(s) need attention before saving.</span>
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {isEdit && student && (
                        <>
                            <StudentPortalAccountCard
                                studentId={Number(student.id)}
                                studentEmail={data.email || student.email}
                                portalAccount={portalAccount}
                                compact
                            />
                            <Button
                                render={<Link href={`/administrators/students/${student.id}/documents`} />}
                                nativeButton={false}
                                variant="outline"
                                size="sm"
                                className="h-9 w-full gap-1.5 text-xs"
                            >
                                <FileCheck2 className="size-3.5" />
                                Open Document Studio
                            </Button>
                        </>
                    )}
                </aside>
            </div>

            {/* Sticky Bottom Action Bar */}
            <div className="border-border/80 bg-background/95 fixed right-0 bottom-0 left-0 z-30 border-t px-4 py-3 backdrop-blur lg:left-[260px]">
                <div className="mx-auto flex max-w-[1440px] flex-wrap items-center justify-between gap-2">
                    <div className="flex items-center gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            disabled={activeStepIndex === 0}
                            onClick={() => setActiveStepIndex((i) => Math.max(0, i - 1))}
                            className="h-8 gap-1.5 text-xs"
                        >
                            <ArrowLeft className="size-3.5" />
                            Previous
                        </Button>
                        {activeStepIndex < steps.length - 1 && (
                            <Button
                                type="button"
                                variant="secondary"
                                size="sm"
                                onClick={() => setActiveStepIndex((i) => Math.min(steps.length - 1, i + 1))}
                                className="h-8 gap-1.5 text-xs"
                            >
                                Next: {steps[activeStepIndex + 1]?.title}
                                <ArrowRight className="size-3.5" />
                            </Button>
                        )}
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        {isEdit ? (
                            <>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    disabled={processing}
                                    onClick={() => handleSubmit("stay")}
                                    className="h-8 gap-1.5 text-xs"
                                >
                                    Save &amp; Stay
                                </Button>
                                <Button
                                    type="button"
                                    size="sm"
                                    disabled={processing}
                                    onClick={() => handleSubmit("view")}
                                    className="h-8 gap-1.5 text-xs"
                                >
                                    {processing ? <Loader2 className="size-3.5 animate-spin" /> : <Save className="size-3.5" />}
                                    Save Changes
                                </Button>
                            </>
                        ) : (
                            <>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    disabled={processing}
                                    onClick={() => handleSubmit("create_another")}
                                    className="h-8 gap-1.5 text-xs"
                                >
                                    Save &amp; Add Another
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    disabled={processing}
                                    onClick={() => handleSubmit("create_enrollment")}
                                    className="h-8 gap-1.5 text-xs"
                                >
                                    <ClipboardPlus className="size-3.5" />
                                    Save &amp; Enroll
                                </Button>
                                <Button
                                    type="button"
                                    size="sm"
                                    disabled={processing}
                                    onClick={() => handleSubmit("view")}
                                    className="h-8 gap-1.5 text-xs"
                                >
                                    {processing ? <Loader2 className="size-3.5 animate-spin" /> : <CheckCircle2 className="size-3.5" />}
                                    Create Student
                                </Button>
                            </>
                        )}
                    </div>
                </div>
            </div>

            {/* Unified Signature Drawing / Upload Modal */}
            <CreateSignatureDialog
                open={signatureDialogOpen}
                onOpenChange={setSignatureDialogOpen}
                onSave={(file, previewUrl) => {
                    setData("signature", file);
                    setSignaturePreviewUrl(previewUrl);
                }}
            />
        </div>
    );
}
