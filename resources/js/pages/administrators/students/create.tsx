import AdminLayout from "@/components/administrators/admin-layout";
import type { BadgeProps } from "@/components/reui/badge";
import { Badge as BadgeOptional } from "@/components/reui/badge";
import { AutocompleteFieldInput } from "@/components/ui/autocomplete-field-input";
import { AutocompleteInput } from "@/components/ui/autocomplete-input";
import { Badge } from "@/components/ui/badge";
import { Button, buttonVariants } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Checkbox } from "@/components/ui/checkbox";
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from "@/components/ui/dropdown-menu";
import { Field, FieldDescription, FieldLabel } from "@/components/ui/field";
import { Input } from "@/components/ui/input";
import { InputGroup, InputGroupAddon, InputGroupInput } from "@/components/ui/input-group";
import { Label } from "@/components/ui/label";
import { PhoneInput } from "@/components/ui/phone-input";
import { SchoolAutocompleteInput, type SchoolOption } from "@/components/ui/school-autocomplete-input";
import { Select, SelectContent, SelectGroup, SelectItem, SelectLabel, SelectSeparator, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from "@/components/ui/tooltip";
import { PHILIPPINE_CITIES_MUNICIPALITIES, PHILIPPINE_PROVINCES, PHILIPPINE_REGIONS } from "@/data/philippine-geography";
import { useFeatureFlags } from "@/hooks/use-feature-flags";
import { cn } from "@/lib/utils";
import type { User } from "@/types/user";
import { Head, Link, useForm, usePage } from "@inertiajs/react";
import {
    AlertCircle,
    ArrowLeft,
    Banknote,
    BookOpen,
    Briefcase,
    Calendar,
    Camera,
    ChevronDown,
    Copy,
    Download,
    Eye,
    FilePlus2,
    GraduationCap,
    Hash,
    HelpCircleIcon,
    ImageUp,
    Loader2,
    Mail,
    MapPin,
    PenLine,
    Phone,
    RefreshCw,
    Save,
    School,
    User as UserIcon,
    UserPlus,
} from "lucide-react";
import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import { toast } from "sonner";

import { CreateSignatureDialog } from "./components/create-signature-dialog";
declare const route: (name: string, params?: Record<string, unknown>) => string;

interface Option {
    value: string | number;
    label: string;
    is_active?: boolean;
    curriculum_kind?: string;
    qualification_level?: string | null;
    duration_hours?: number | null;
    tesda_program_type?: string | null;
    duration_years?: number | string | null;
    internship_hours?: number | null;
    bundled_qualifications?: string[] | null;
}

interface CourseGroup {
    label: string;
    pathway?: string;
    items: ReadonlyArray<Option>;
}

interface IncomeBracketOption {
    value: string;
    label: string;
}

interface IncomeModeOption {
    value: string;
    label: string;
    brackets: IncomeBracketOption[];
}

interface CreateStudentProps {
    user: User;
    options: {
        types: Option[];
        statuses: Option[];
        scholarship_types: Option[];
        employment_statuses: Option[];
        attrition_categories: Option[];
        courses: CourseGroup[];
        shs_strands: Option[];
        religions: Option[];
        regions: Option[];
        income_modes: IncomeModeOption[];
        default_income_mode: string;
    };
}

interface StudentCreateForm {
    student_type: string;
    student_id: string;
    lrn: string;
    status: string;
    first_name: string;
    last_name: string;
    middle_name: string;
    suffix: string;
    gender: string;
    birth_date: string;
    age: string;
    email: string;
    picture_1x1: File | null;
    signature: File | null;
    phone: string;
    civil_status: string;
    nationality: string;
    religion: string;
    course_id: string;
    shs_strand_id: string;
    academic_year: string;
    remarks: string;
    personal_contact: string;
    emergency_contact_name: string;
    emergency_contact_phone: string;
    emergency_contact_address: string;
    emergency_contact_relationship: string;
    facebook_contact: string;
    twitter: string;
    instagram: string;
    linkedin: string;
    fathers_name: string;
    father_occupation: string;
    father_contact: string;
    father_email: string;
    mothers_name: string;
    mother_occupation: string;
    mother_contact: string;
    mother_email: string;
    guardian_name: string;
    guardian_relationship: string;
    guardian_contact: string;
    guardian_email: string;
    family_address: string;
    current_address: string;
    permanent_address: string;
    birthplace: string;
    citizenship: string;
    weight: string;
    height: string;
    elementary_school: string;
    elementary_graduate_year: string;
    elementary_school_address: string;
    junior_high_school_name: string;
    junior_high_graduation_year: string;
    junior_high_school_address: string;
    senior_high_name: string;
    senior_high_graduate_year: string;
    senior_high_address: string;
    college_school: string;
    college_course: string;
    college_year_graduated: string;
    vocational_school: string;
    vocational_course: string;
    vocational_year_graduated: string;
    ethnicity: string;
    region_of_origin: string;
    province_of_origin: string;
    city_of_origin: string;
    is_indigenous_person: boolean;
    indigenous_group: string;
    is_pwd: boolean;
    pwd_type: string;
    is_solo_parent: boolean;
    is_senior_citizen: boolean;
    is_magna_carta: boolean;
    is_underprivileged: boolean;
    is_first_generation: boolean;
    income_bracket_mode: string;
    use_same_parent_income: boolean;
    family_income_bracket: string;
    father_income_bracket: string;
    mother_income_bracket: string;
    scholarship_type: string;
    scholarship_details: string;
    employment_status: string;
    employer_name: string;
    job_position: string;
    employment_date: string;
    employed_by_institution: boolean;
    withdrawal_date: string;
    withdrawal_reason: string;
    attrition_category: string;
    dropout_date: string;
    submit_action: string;
}

const CIVIL_STATUS_OPTIONS = [
    { value: "single", label: "Single" },
    { value: "married", label: "Married" },
    { value: "widowed", label: "Widowed" },
    { value: "separated", label: "Separated" },
    { value: "annulled", label: "Annulled" },
];

const NATIONALITY_OPTIONS = [
    { value: "filipino", label: "Filipino" },
    { value: "american", label: "American" },
    { value: "chinese", label: "Chinese" },
    { value: "indian", label: "Indian" },
    { value: "korean", label: "Korean" },
    { value: "japanese", label: "Japanese" },
    { value: "other", label: "Other" },
];

const RELATIONSHIP_OPTIONS = [
    { value: "mother", label: "Mother" },
    { value: "father", label: "Father" },
    { value: "sibling", label: "Sibling" },
    { value: "spouse", label: "Spouse" },
    { value: "grandparent", label: "Grandparent" },
    { value: "aunt", label: "Aunt" },
    { value: "uncle", label: "Uncle" },
    { value: "cousin", label: "Cousin" },
    { value: "legal_guardian", label: "Legal Guardian" },
    { value: "other", label: "Other" },
];

const STATUS_VARIANT_MAP: Record<string, BadgeProps["variant"]> = {
    applicant: "warning-outline",
    enrolled: "success-outline",
    on_leave: "warning-outline",
    withdrawn: "destructive-outline",
    dropped: "destructive-outline",
    graduated: "primary-outline",
    transferred: "focus-outline",
};

function getStatusVariant(statusValue: string): BadgeProps["variant"] {
    return STATUS_VARIANT_MAP[statusValue] ?? "outline";
}

const BLANK_FORM: StudentCreateForm = {
    student_type: "college",
    student_id: "",
    lrn: "",
    status: "enrolled",
    first_name: "",
    last_name: "",
    middle_name: "",
    suffix: "",
    gender: "male",
    birth_date: "",
    age: "",
    email: "",
    picture_1x1: null,
    signature: null,
    phone: "",
    civil_status: "single",
    nationality: "filipino",
    religion: "",
    course_id: "",
    shs_strand_id: "",
    academic_year: "1",
    remarks: "",
    personal_contact: "",
    emergency_contact_name: "",
    emergency_contact_phone: "",
    emergency_contact_address: "",
    emergency_contact_relationship: "",
    facebook_contact: "",
    twitter: "",
    instagram: "",
    linkedin: "",
    fathers_name: "",
    father_occupation: "",
    father_contact: "",
    father_email: "",
    mothers_name: "",
    mother_occupation: "",
    mother_contact: "",
    mother_email: "",
    guardian_name: "",
    guardian_relationship: "",
    guardian_contact: "",
    guardian_email: "",
    family_address: "",
    current_address: "",
    permanent_address: "",
    birthplace: "",
    citizenship: "filipino",
    weight: "",
    height: "",
    elementary_school: "",
    elementary_graduate_year: "",
    elementary_school_address: "",
    junior_high_school_name: "",
    junior_high_graduation_year: "",
    junior_high_school_address: "",
    senior_high_name: "",
    senior_high_graduate_year: "",
    senior_high_address: "",
    college_school: "",
    college_course: "",
    college_year_graduated: "",
    vocational_school: "",
    vocational_course: "",
    vocational_year_graduated: "",
    ethnicity: "",
    region_of_origin: "",
    province_of_origin: "",
    city_of_origin: "",
    is_indigenous_person: false,
    indigenous_group: "",
    is_pwd: false,
    pwd_type: "",
    is_solo_parent: false,
    is_senior_citizen: false,
    is_magna_carta: false,
    is_underprivileged: false,
    is_first_generation: false,
    income_bracket_mode: "annual",
    use_same_parent_income: true,
    family_income_bracket: "",
    father_income_bracket: "",
    mother_income_bracket: "",
    scholarship_type: "none",
    scholarship_details: "",
    employment_status: "not_applicable",
    employer_name: "",
    job_position: "",
    employment_date: "",
    employed_by_institution: false,
    withdrawal_date: "",
    withdrawal_reason: "",
    attrition_category: "",
    dropout_date: "",
    submit_action: "view",
};

const STUDENT_CREATE_DRAFT_KEY = "koakademy:student-create-draft";

type SectionId = "record" | "required" | "contact" | "family" | "reporting" | "assets";

interface StudentCreateSection {
    id: SectionId;
    label: string;
    description: string;
    icon: typeof UserIcon;
    fields: (keyof StudentCreateForm)[];
    requiredFields: (keyof StudentCreateForm)[];
}

type StudentCreateDraft = Omit<StudentCreateForm, "picture_1x1" | "signature">;

function buildDraftPayload(data: StudentCreateForm): StudentCreateDraft {
    const { picture_1x1, signature, ...draft } = data;

    return draft;
}

function isStudentCreateDraft(value: unknown): value is Partial<StudentCreateDraft> {
    return typeof value === "object" && value !== null && "student_type" in value;
}

function hasMeaningfulDraftData(data: StudentCreateForm): boolean {
    const meaningfulFields: (keyof StudentCreateForm)[] = [
        "lrn",
        "first_name",
        "last_name",
        "middle_name",
        "suffix",
        "birth_date",
        "email",
        "phone",
        "course_id",
        "shs_strand_id",
        "current_address",
        "permanent_address",
        "emergency_contact_name",
        "emergency_contact_phone",
        "facebook_contact",
        "fathers_name",
        "mothers_name",
        "remarks",
    ];

    return meaningfulFields.some((field) => {
        const value = data[field];

        return value !== null && value !== "" && value !== false;
    });
}

function formatPersonName(value: string): string {
    return value
        .trim()
        .replace(/\s+/g, " ")
        .toLocaleLowerCase()
        .replace(/(^|[\s'-])([a-z])/g, (_match, separator: string, character: string) => `${separator}${character.toLocaleUpperCase()}`);
}

export default function AdministratorStudentCreate({ user, options }: CreateStudentProps) {
    const [previewId, setPreviewId] = useState<number | null>(null);
    const [isGeneratingId, setIsGeneratingId] = useState(false);
    const [idGenerationError, setIdGenerationError] = useState<string | null>(null);

    const { data, setData, post, processing, errors, transform, progress } = useForm<StudentCreateForm>({
        ...BLANK_FORM,
        income_bracket_mode: options.default_income_mode || BLANK_FORM.income_bracket_mode,
    });
    const { branding } = usePage().props as { branding?: { defaultCountryCode?: string } };

    const formRef = useRef<HTMLFormElement>(null);
    const submitActionRef = useRef("view");
    const restoredDraftRef = useRef(false);
    const pendingDraftRestoreRef = useRef(false);

    const flags = useFeatureFlags();
    const pictureInputRef = useRef<HTMLInputElement>(null);
    const [profilePreview, setProfilePreview] = useState<string | null>(null);
    const [signaturePreview, setSignaturePreview] = useState<string | null>(null);
    const [isDragOverPicture, setIsDragOverPicture] = useState(false);
    const [isSignatureDialogOpen, setIsSignatureDialogOpen] = useState(false);
    const [submitErrorSectionId, setSubmitErrorSectionId] = useState<SectionId | null>(null);

    const handlePictureFile = useCallback(
        (file: File) => {
            if (!file.type.startsWith("image/")) {
                toast.error("Please select a valid image file.");
                return;
            }
            if (file.size > 5 * 1024 * 1024) {
                toast.error("Image must be smaller than 5MB.");
                return;
            }
            setData("picture_1x1", file);
            setProfilePreview(URL.createObjectURL(file));
        },
        [setData],
    );

    const handlePictureChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (!file) return;
        handlePictureFile(file);
    };

    const handlePictureDragOver = (e: React.DragEvent) => {
        e.preventDefault();
        setIsDragOverPicture(true);
    };
    const handlePictureDragLeave = (e: React.DragEvent) => {
        e.preventDefault();
        setIsDragOverPicture(false);
    };
    const handlePictureDrop = (e: React.DragEvent) => {
        e.preventDefault();
        setIsDragOverPicture(false);
        const file = e.dataTransfer.files[0];
        if (!file) return;
        handlePictureFile(file);
    };

    const clearPicture = () => {
        setData("picture_1x1", null);
        if (profilePreview) {
            URL.revokeObjectURL(profilePreview);
        }
        setProfilePreview(null);
        if (pictureInputRef.current) pictureInputRef.current.value = "";
    };

    const clearSignature = () => {
        setData("signature", null);
        if (signaturePreview) {
            URL.revokeObjectURL(signaturePreview);
        }
        setSignaturePreview(null);
    };

    const getDownloadFilename = (prefix: string) => {
        const id = data.student_id || "student";
        return `${id}-${prefix}.jpg`;
    };

    const downloadPreview = (previewUrl: string, filename: string) => {
        const link = document.createElement("a");
        link.href = previewUrl;
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    };

    const handleSignatureSave = (file: File, previewUrl: string) => {
        if (signaturePreview) {
            URL.revokeObjectURL(signaturePreview);
        }
        setData("signature", file);
        setSignaturePreview(previewUrl);
    };

    useEffect(() => {
        const storedDraft = window.localStorage.getItem(STUDENT_CREATE_DRAFT_KEY);

        if (!storedDraft) {
            return;
        }

        pendingDraftRestoreRef.current = true;

        toast("Restore saved student draft?", {
            description: "A student creation draft is available on this device.",
            action: {
                label: "Restore",
                onClick: () => {
                    let parsed: unknown;

                    try {
                        parsed = JSON.parse(storedDraft) as unknown;
                    } catch {
                        pendingDraftRestoreRef.current = false;
                        window.localStorage.removeItem(STUDENT_CREATE_DRAFT_KEY);
                        toast.error("Saved draft could not be restored.");
                        return;
                    }

                    if (!isStudentCreateDraft(parsed)) {
                        pendingDraftRestoreRef.current = false;
                        window.localStorage.removeItem(STUDENT_CREATE_DRAFT_KEY);
                        toast.error("Saved draft could not be restored.");
                        return;
                    }

                    pendingDraftRestoreRef.current = false;
                    restoredDraftRef.current = true;
                    setData({
                        ...BLANK_FORM,
                        income_bracket_mode: options.default_income_mode || BLANK_FORM.income_bracket_mode,
                        ...parsed,
                        picture_1x1: null,
                        signature: null,
                    });
                    toast.success("Student draft restored.");
                },
            },
            cancel: {
                label: "Discard",
                onClick: () => {
                    pendingDraftRestoreRef.current = false;
                    window.localStorage.removeItem(STUDENT_CREATE_DRAFT_KEY);
                },
            },
        });
    }, [options.default_income_mode, setData]);

    useEffect(() => {
        const timeout = window.setTimeout(
            () => {
                if (pendingDraftRestoreRef.current) {
                    return;
                }

                if (!hasMeaningfulDraftData(data)) {
                    window.localStorage.removeItem(STUDENT_CREATE_DRAFT_KEY);
                    return;
                }

                window.localStorage.setItem(STUDENT_CREATE_DRAFT_KEY, JSON.stringify(buildDraftPayload(data)));
            },
            restoredDraftRef.current ? 250 : 1000,
        );

        restoredDraftRef.current = false;

        return () => window.clearTimeout(timeout);
    }, [data]);

    useEffect(() => {
        const errorKeys = Object.keys(errors);
        if (errorKeys.length > 0) {
            const firstKey = errorKeys[0];
            const element = document.getElementById(firstKey);
            if (element) {
                element.scrollIntoView({ behavior: "smooth", block: "center" });
                element.focus();
            }
        }
    }, [errors]);

    const isSHS = data.student_type === "shs";
    const isTESDA = data.student_type === "tesda";
    const isGraduated = data.status === "graduated";
    const isWithdrawn = data.status === "withdrawn" || data.status === "dropped";
    const showEmployment =
        isGraduated &&
        data.employment_status !== "not_applicable" &&
        data.employment_status !== "unemployed" &&
        data.employment_status !== "further_study";

    const [collapsedSections, setCollapsedSections] = useState<Record<string, boolean>>({
        family: false,
        reporting: false,
        contact: false,
    });

    const toggleSection = (key: string) => {
        setCollapsedSections((prev) => ({ ...prev, [key]: !prev[key] }));
    };

    // Keyboard shortcut: Ctrl+Enter to submit
    useEffect(() => {
        const handler = (e: KeyboardEvent) => {
            if ((e.ctrlKey || e.metaKey) && e.key === "Enter") {
                e.preventDefault();
                formRef.current?.requestSubmit();
            }
        };
        window.addEventListener("keydown", handler);
        return () => window.removeEventListener("keydown", handler);
    }, []);

    // Count filled required fields for progress
    const requiredFields: (keyof StudentCreateForm)[] = [
        "student_type",
        "status",
        "first_name",
        "last_name",
        "gender",
        "birth_date",
        "academic_year",
        ...(isSHS ? (["lrn", "shs_strand_id"] as const) : (["student_id", "course_id"] as const)),
    ];
    const filledRequired = requiredFields.filter((f) => {
        const val = data[f];
        return val !== "" && val !== false;
    }).length;
    const progressPercent = Math.round((filledRequired / requiredFields.length) * 100);
    const hasServerErrors = Object.keys(errors).length > 0;

    const sections = useMemo<StudentCreateSection[]>(
        () => [
            {
                id: "record",
                label: "Record",
                description: isSHS ? "Type, status, and LRN" : "Type, status, and ID",
                icon: UserIcon,
                fields: ["student_type", "status", isSHS ? "lrn" : "student_id"],
                requiredFields: ["student_type", "status", isSHS ? "lrn" : "student_id"],
            },
            {
                id: "required",
                label: "Core Info",
                description: "Identity and academic placement",
                icon: GraduationCap,
                fields: [
                    "first_name",
                    "middle_name",
                    "last_name",
                    "suffix",
                    "gender",
                    "birth_date",
                    "age",
                    "email",
                    "phone",
                    "current_address",
                    "permanent_address",
                    isSHS ? "shs_strand_id" : "course_id",
                    "academic_year",
                ],
                requiredFields: ["first_name", "last_name", "gender", "birth_date", isSHS ? "shs_strand_id" : "course_id", "academic_year"],
            },
            {
                id: "contact",
                label: "Contacts",
                description: "Guardian, parent, and social contacts",
                icon: Phone,
                fields: [
                    "personal_contact",
                    "facebook_contact",
                    "twitter",
                    "instagram",
                    "linkedin",
                    "emergency_contact_name",
                    "emergency_contact_phone",
                    "emergency_contact_address",
                    "emergency_contact_relationship",
                    "father_occupation",
                    "father_contact",
                    "father_email",
                    "mother_occupation",
                    "mother_contact",
                    "mother_email",
                    "guardian_email",
                    "family_address",
                ],
                requiredFields: [],
            },
            {
                id: "family",
                label: "Background",
                description: "Family, personal, and education",
                icon: School,
                fields: [
                    "fathers_name",
                    "mothers_name",
                    "birthplace",
                    "civil_status",
                    "nationality",
                    "citizenship",
                    "religion",
                    "height",
                    "weight",
                    "elementary_school",
                    "elementary_graduate_year",
                    "elementary_school_address",
                    "junior_high_school_name",
                    "junior_high_graduation_year",
                    "junior_high_school_address",
                    "senior_high_name",
                    "senior_high_graduate_year",
                    "senior_high_address",
                    "college_school",
                    "college_course",
                    "college_year_graduated",
                    "vocational_school",
                    "vocational_course",
                    "vocational_year_graduated",
                ],
                requiredFields: [],
            },
            {
                id: "reporting",
                label: "Reporting",
                description: "Compliance, income, status details",
                icon: Banknote,
                fields: [
                    "region_of_origin",
                    "ethnicity",
                    "province_of_origin",
                    "city_of_origin",
                    "is_indigenous_person",
                    "indigenous_group",
                    "is_pwd",
                    "pwd_type",
                    "is_solo_parent",
                    "is_senior_citizen",
                    "is_magna_carta",
                    "is_underprivileged",
                    "is_first_generation",
                    "income_bracket_mode",
                    "use_same_parent_income",
                    "family_income_bracket",
                    "father_income_bracket",
                    "mother_income_bracket",
                    "scholarship_type",
                    "scholarship_details",
                    "employment_status",
                    "employer_name",
                    "job_position",
                    "employment_date",
                    "employed_by_institution",
                    "attrition_category",
                    "withdrawal_date",
                    "dropout_date",
                    "withdrawal_reason",
                    "remarks",
                ],
                requiredFields: [],
            },
            {
                id: "assets",
                label: "Assets",
                description: "Profile photo and signature",
                icon: Camera,
                fields: ["picture_1x1", "signature"],
                requiredFields: [],
            },
        ],
        [isSHS],
    );

    const sectionByField = useMemo(() => {
        const mapped = new Map<keyof StudentCreateForm, SectionId>();

        sections.forEach((section) => {
            section.fields.forEach((field) => mapped.set(field, section.id));
        });

        return mapped;
    }, [sections]);

    const scrollToSection = (sectionId: SectionId) => {
        setCollapsedSections((current) => ({ ...current, [sectionId]: false }));
        document.getElementById(`student-section-${sectionId}`)?.scrollIntoView({ behavior: "smooth", block: "start" });
    };

    const fieldError = (field: keyof StudentCreateForm) => (errors[field] ? <p className="text-destructive text-sm">{errors[field]}</p> : null);

    const setSelectData =
        <K extends keyof StudentCreateForm>(field: K) =>
        (value: string | null) => {
            if (value === null) {
                return;
            }

            setData(field, value as never);
        };

    useEffect(() => {
        const firstErrorKey = Object.keys(errors)[0] as keyof StudentCreateForm | undefined;

        if (!firstErrorKey) {
            setSubmitErrorSectionId(null);
            return;
        }

        const sectionId = sectionByField.get(firstErrorKey) ?? "required";

        setSubmitErrorSectionId(sectionId);
        setCollapsedSections((current) => ({ ...current, [sectionId]: false }));
    }, [errors, sectionByField]);

    const selectedIncomeMode = useMemo(() => {
        return options.income_modes.find((mode) => mode.value === data.income_bracket_mode) ?? options.income_modes[0] ?? null;
    }, [options.income_modes, data.income_bracket_mode]);

    const visibleCourseGroups = useMemo(
        () => options.courses.filter((group) => (isTESDA ? group.pathway === "tesda_qualification" : group.pathway !== "tesda_qualification")),
        [isTESDA, options.courses],
    );
    const flatCourses = useMemo<ReadonlyArray<Option>>(() => visibleCourseGroups.flatMap((group) => group.items), [visibleCourseGroups]);

    const activeIncomeBrackets = selectedIncomeMode?.brackets ?? [];

    const selectedRegion = useMemo(
        () => PHILIPPINE_REGIONS.find((region) => region.value === data.region_of_origin || region.label === data.region_of_origin) ?? null,
        [data.region_of_origin],
    );

    const provinceOptions = useMemo(
        () => (selectedRegion ? PHILIPPINE_PROVINCES.filter((province) => province.regionCode === selectedRegion.code) : []),
        [selectedRegion],
    );

    const selectedProvince = useMemo(
        () => provinceOptions.find((province) => province.name === data.province_of_origin) ?? null,
        [data.province_of_origin, provinceOptions],
    );

    const cityOptions = useMemo(() => {
        if (selectedProvince) {
            return PHILIPPINE_CITIES_MUNICIPALITIES.filter((city) => city.provinceCode === selectedProvince.code);
        }

        if (selectedRegion && provinceOptions.length === 0) {
            return PHILIPPINE_CITIES_MUNICIPALITIES.filter((city) => city.regionCode === selectedRegion.code && city.provinceCode === null);
        }

        return [];
    }, [provinceOptions.length, selectedProvince, selectedRegion]);

    const handleRegionChange = (value: string | null) => {
        if (value === null) {
            return;
        }

        setData("region_of_origin", value);
        setData("province_of_origin", "");
        setData("city_of_origin", "");
    };

    const handleProvinceChange = (value: string | null) => {
        if (value === null) {
            return;
        }

        setData("province_of_origin", value);
        setData("city_of_origin", "");
    };

    const fillSchoolAddress =
        (field: "elementary_school_address" | "junior_high_school_address" | "senior_high_address") => (option: SchoolOption) => {
            if (option.address) {
                setData(field, option.address);
            }
        };

    useEffect(() => {
        setData("family_income_bracket", "");
        setData("father_income_bracket", "");
        setData("mother_income_bracket", "");
    }, [data.income_bracket_mode]);

    useEffect(() => {
        if (data.use_same_parent_income) {
            setData("father_income_bracket", "");
            setData("mother_income_bracket", "");
            return;
        }

        setData("family_income_bracket", "");
    }, [data.use_same_parent_income]);

    const yearLevelOptions = isSHS
        ? [
              { value: "11", label: "Grade 11" },
              { value: "12", label: "Grade 12" },
          ]
        : isTESDA
          ? [
                { value: "1", label: "Training Level 1" },
                { value: "2", label: "Training Level 2" },
                { value: "3", label: "Training Level 3" },
                { value: "4", label: "Training Level 4" },
                { value: "5", label: "Training Level 5" },
            ]
          : [
              { value: "1", label: "1st Year" },
              { value: "2", label: "2nd Year" },
              { value: "3", label: "3rd Year" },
              { value: "4", label: "4th Year" },
              { value: "5", label: "Graduate" },
          ];

    const fetchGeneratedId = useCallback(async () => {
        if (isSHS) {
            setPreviewId(null);
            setIdGenerationError(null);
            return;
        }

        setIsGeneratingId(true);
        setIdGenerationError(null);

        try {
            const response = await fetch(route("administrators.students.generate-id", { type: data.student_type }));

            if (!response.ok) {
                setIdGenerationError("Unable to generate an ID");
                return;
            }

            const result = (await response.json()) as { id?: number };

            if (result.id) {
                setPreviewId(result.id);
                setData("student_id", result.id.toString());
                return;
            }

            setIdGenerationError("No ID available");
        } catch {
            setIdGenerationError("Unable to generate an ID");
        } finally {
            setIsGeneratingId(false);
        }
    }, [data.student_type, isSHS, setData]);

    useEffect(() => {
        if (!isSHS) {
            void fetchGeneratedId();
        }
    }, [fetchGeneratedId, isSHS]);

    useEffect(() => {
        if (isSHS) {
            setData("course_id", "");
            setData("student_id", "");

            if (data.academic_year !== "11" && data.academic_year !== "12") {
                setData("academic_year", "11");
            }

            return;
        }

        setData("lrn", "");
        setData("shs_strand_id", "");
        setData("course_id", "");

        if (data.academic_year === "11" || data.academic_year === "12") {
            setData("academic_year", "1");
        }
    }, [data.student_type]);

    useEffect(() => {
        if (!data.birth_date) {
            setData("age", "");
            return;
        }

        const birthDate = new Date(data.birth_date);
        const today = new Date();
        let age = today.getFullYear() - birthDate.getFullYear();
        const monthDifference = today.getMonth() - birthDate.getMonth();

        if (monthDifference < 0 || (monthDifference === 0 && today.getDate() < birthDate.getDate())) {
            age--;
        }

        setData("age", age.toString());
    }, [data.birth_date]);

    // Sync personal_contact with phone
    useEffect(() => {
        setData("personal_contact", data.phone);
    }, [data.phone]);

    // Keep applicant guardian details aligned with the visible guardian contact fields.
    useEffect(() => {
        setData("guardian_name", data.emergency_contact_name);
    }, [data.emergency_contact_name]);

    useEffect(() => {
        setData("guardian_contact", data.emergency_contact_phone);
    }, [data.emergency_contact_phone]);

    useEffect(() => {
        setData("guardian_relationship", data.emergency_contact_relationship);
    }, [data.emergency_contact_relationship]);

    const submitWithAction = (action: string) => {
        submitActionRef.current = action;
        formRef.current?.requestSubmit();
    };

    const saveDraft = () => {
        pendingDraftRestoreRef.current = false;
        window.localStorage.setItem(STUDENT_CREATE_DRAFT_KEY, JSON.stringify(buildDraftPayload(data)));
        toast.success("Student draft saved.");
    };

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        transform((formData) => ({
            ...formData,
            student_id: !isSHS && !formData.student_id && previewId ? previewId.toString() : formData.student_id,
            academic_year: parseInt(formData.academic_year, 10),
            course_id: formData.course_id ? parseInt(formData.course_id, 10) : "",
            shs_strand_id: formData.shs_strand_id ? parseInt(formData.shs_strand_id, 10) : "",
            weight: formData.weight ? parseFloat(formData.weight) : "",
            height: formData.height ? parseFloat(formData.height) : "",
            age: formData.age ? parseInt(formData.age, 10) : "",
            submit_action: submitActionRef.current,
        }));

        post(route("administrators.students.store"), {
            forceFormData: true,
            onStart: () => {
                setSubmitErrorSectionId(null);
            },
            onSuccess: () => {
                window.localStorage.removeItem(STUDENT_CREATE_DRAFT_KEY);
                toast.success("Student created successfully");
            },
            onError: (formErrors) => {
                toast.error("Failed to create student", {
                    description: Object.values(formErrors)[0] || "Please check the form for errors.",
                });
            },
        });
    };

    return (
        <AdminLayout user={user} title="Create Student">
            <Head title="Administrators - Create Student" />

            <TooltipProvider>
                <form ref={formRef} onSubmit={submit} className="mx-auto max-w-6xl space-y-4 pb-10">
                    <div className="flex flex-col gap-3 border-b pb-4 sm:flex-row sm:items-center sm:justify-between">
                        <div className="min-w-0">
                            <div className="flex flex-wrap items-center gap-2">
                                <h1 className="text-xl font-semibold tracking-tight sm:text-2xl">Create Student</h1>
                                <Badge variant="secondary">{isSHS ? "Senior High" : isTESDA ? "TESDA Training" : "College / Program"}</Badge>
                                {!isSHS && data.student_id && <Badge variant="outline">ID {data.student_id}</Badge>}
                                {hasServerErrors && (
                                    <Badge variant="destructive" className="gap-1">
                                        <AlertCircle className="h-3.5 w-3.5" />
                                        Needs review
                                    </Badge>
                                )}
                            </div>
                            <p className="text-muted-foreground mt-1 text-sm">
                                Enter the core record first, then let school history and reporting selectors reuse existing data.
                            </p>
                        </div>
                        <Link href={route("administrators.students.index")} className={buttonVariants({ variant: "outline", className: "gap-2" })}>
                            <ArrowLeft className="h-4 w-4" />
                            Back
                        </Link>
                    </div>

                    <Card id="student-section-record" className="scroll-mt-36">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-lg">
                                <UserIcon className="text-primary h-5 w-5" />
                                Student Record
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-5 md:grid-cols-2 xl:grid-cols-[minmax(0,2fr)_minmax(180px,0.7fr)_minmax(220px,0.9fr)]">
                            <div className="space-y-2 md:col-span-2 xl:col-span-1">
                                <div className="flex items-center gap-2">
                                    <Label>
                                        Student Type <span className="text-destructive">*</span>
                                    </Label>
                                    <Tooltip>
                                        <TooltipTrigger className="inline-flex items-center">
                                            <HelpCircleIcon className="text-muted-foreground size-3.5" />
                                        </TooltipTrigger>
                                        <TooltipContent>
                                            Choose the education level this student belongs to. Affects available fields and validation rules.
                                        </TooltipContent>
                                    </Tooltip>
                                </div>
                                <div className="flex flex-wrap gap-2">
                                    {options.types.map((type) => (
                                        <Button
                                            key={type.value}
                                            type="button"
                                            variant={data.student_type === type.value ? "default" : "outline"}
                                            onClick={() => setData("student_type", type.value.toString())}
                                            className="h-auto min-h-9 flex-1 basis-[9.5rem] px-3 py-2 text-center leading-snug whitespace-normal"
                                        >
                                            {type.label}
                                        </Button>
                                    ))}
                                </div>
                                {fieldError("student_type")}
                            </div>

                            <Field>
                                <FieldLabel htmlFor="status">
                                    Status <span className="text-destructive">*</span>
                                </FieldLabel>
                                <Select value={data.status} onValueChange={setSelectData("status")}>
                                    <SelectTrigger id="status">
                                        <SelectValue>
                                            {(selectedValue: string) => {
                                                const statusObj = options.statuses.find((s) => s.value.toString() === selectedValue);
                                                if (!statusObj) {
                                                    return <span className="text-muted-foreground">Select status</span>;
                                                }
                                                return (
                                                    <BadgeOptional variant={getStatusVariant(statusObj.value.toString())}>
                                                        {statusObj.label}
                                                    </BadgeOptional>
                                                );
                                            }}
                                        </SelectValue>
                                    </SelectTrigger>
                                    <SelectContent alignItemWithTrigger={false}>
                                        {options.statuses.map((status) => (
                                            <SelectItem key={status.value} value={status.value.toString()}>
                                                <BadgeOptional variant={getStatusVariant(status.value.toString())}>{status.label}</BadgeOptional>
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {fieldError("status")}
                            </Field>

                            {!isSHS ? (
                                <Field>
                                    <div className="flex items-center gap-2">
                                        <FieldLabel htmlFor="student_id">
                                            <Hash className="mr-1 inline h-3.5 w-3.5" />
                                            Student ID <span className="text-destructive">*</span>
                                        </FieldLabel>
                                        <Tooltip>
                                            <TooltipTrigger className="inline-flex items-center">
                                                <HelpCircleIcon className="text-muted-foreground size-3.5" />
                                            </TooltipTrigger>
                                            <TooltipContent>
                                                Unique numeric identifier for the student. Click the refresh icon to auto-generate the next available
                                                ID.
                                            </TooltipContent>
                                        </Tooltip>
                                    </div>
                                    <div className="flex gap-2">
                                        <Input
                                            id="student_id"
                                            value={data.student_id}
                                            onChange={(event) => setData("student_id", event.target.value)}
                                            placeholder="6-digit ID"
                                            className="font-mono"
                                        />
                                        <Button type="button" variant="outline" size="icon" onClick={fetchGeneratedId} disabled={isGeneratingId}>
                                            <RefreshCw className={cn("h-4 w-4", isGeneratingId && "animate-spin")} />
                                        </Button>
                                    </div>
                                    {idGenerationError && <p className="text-destructive text-sm">{idGenerationError}</p>}
                                    {fieldError("student_id")}
                                </Field>
                            ) : (
                                <Field>
                                    <div className="flex items-center gap-2">
                                        <FieldLabel htmlFor="lrn">
                                            <Hash className="mr-1 inline h-3.5 w-3.5" />
                                            LRN <span className="text-destructive">*</span>
                                        </FieldLabel>
                                        <Tooltip>
                                            <TooltipTrigger className="inline-flex items-center">
                                                <HelpCircleIcon className="text-muted-foreground size-3.5" />
                                            </TooltipTrigger>
                                            <TooltipContent>Learner Reference Number assigned by the Department of Education.</TooltipContent>
                                        </Tooltip>
                                    </div>
                                    <Input
                                        id="lrn"
                                        value={data.lrn}
                                        onChange={(event) => setData("lrn", event.target.value)}
                                        placeholder="Learner Reference Number"
                                        className="font-mono"
                                    />
                                    {fieldError("lrn")}
                                </Field>
                            )}
                        </CardContent>
                    </Card>

                    <div className="grid gap-6 xl:grid-cols-[minmax(0,2fr)_minmax(320px,1fr)]">
                        <div className="space-y-6">
                            <Card id="student-section-required" className="scroll-mt-36">
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2 text-lg">
                                        <GraduationCap className="text-primary h-5 w-5" />
                                        Required Information
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="grid gap-5 md:grid-cols-2">
                                    <div className="grid gap-4 md:col-span-2 md:grid-cols-4">
                                        <Field>
                                            <FieldLabel htmlFor="first_name">
                                                First Name <span className="text-destructive">*</span>
                                            </FieldLabel>
                                            <Input
                                                id="first_name"
                                                value={data.first_name}
                                                onChange={(event) => setData("first_name", event.target.value)}
                                                onBlur={(event) => setData("first_name", formatPersonName(event.target.value))}
                                            />
                                            {fieldError("first_name")}
                                        </Field>
                                        <Field>
                                            <div className="flex items-center justify-between gap-2">
                                                <FieldLabel htmlFor="middle_name">Middle Name</FieldLabel>
                                                <BadgeOptional variant="warning-outline" size="sm">
                                                    Optional
                                                </BadgeOptional>
                                            </div>
                                            <Input
                                                id="middle_name"
                                                value={data.middle_name}
                                                onChange={(event) => setData("middle_name", event.target.value)}
                                                onBlur={(event) => setData("middle_name", formatPersonName(event.target.value))}
                                            />
                                        </Field>
                                        <Field>
                                            <FieldLabel htmlFor="last_name">
                                                Last Name <span className="text-destructive">*</span>
                                            </FieldLabel>
                                            <Input
                                                id="last_name"
                                                value={data.last_name}
                                                onChange={(event) => setData("last_name", event.target.value)}
                                                onBlur={(event) => setData("last_name", formatPersonName(event.target.value))}
                                            />
                                            {fieldError("last_name")}
                                        </Field>
                                        <Field>
                                            <div className="flex items-center justify-between gap-2">
                                                <FieldLabel htmlFor="suffix">Suffix</FieldLabel>
                                                <BadgeOptional variant="warning-outline" size="sm">
                                                    Optional
                                                </BadgeOptional>
                                            </div>
                                            <Input
                                                id="suffix"
                                                value={data.suffix}
                                                onChange={(event) => setData("suffix", event.target.value.toUpperCase())}
                                            />
                                        </Field>
                                    </div>

                                    <Field>
                                        <FieldLabel htmlFor="gender">
                                            Gender <span className="text-destructive">*</span>
                                        </FieldLabel>
                                        <Select value={data.gender} onValueChange={setSelectData("gender")}>
                                            <SelectTrigger id="gender">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="male">Male</SelectItem>
                                                <SelectItem value="female">Female</SelectItem>
                                            </SelectContent>
                                        </Select>
                                        {fieldError("gender")}
                                    </Field>

                                    <Field>
                                        <div className="flex items-center gap-2">
                                            <FieldLabel htmlFor="birth_date">
                                                <Calendar className="mr-1 inline h-3.5 w-3.5" />
                                                Birth Date <span className="text-destructive">*</span>
                                            </FieldLabel>
                                            <Tooltip>
                                                <TooltipTrigger className="inline-flex items-center">
                                                    <HelpCircleIcon className="text-muted-foreground size-3.5" />
                                                </TooltipTrigger>
                                                <TooltipContent>Date of birth. Used to compute age and verify enrollment eligibility.</TooltipContent>
                                            </Tooltip>
                                        </div>
                                        <Input
                                            id="birth_date"
                                            type="date"
                                            value={data.birth_date}
                                            onChange={(event) => setData("birth_date", event.target.value)}
                                            max={new Date().toISOString().split("T")[0]}
                                        />
                                        {fieldError("birth_date")}
                                    </Field>

                                    <Field>
                                        <div className="flex items-center justify-between gap-2">
                                            <FieldLabel htmlFor="age">Age</FieldLabel>
                                            <BadgeOptional variant="info-outline" size="sm">
                                                Auto
                                            </BadgeOptional>
                                        </div>
                                        <Input id="age" value={data.age} readOnly className="bg-muted" />
                                    </Field>

                                    <Field>
                                        <div className="flex items-center justify-between gap-2">
                                            <FieldLabel htmlFor="email">Email</FieldLabel>
                                            <BadgeOptional variant="warning-outline" size="sm">
                                                Optional
                                            </BadgeOptional>
                                        </div>
                                        <InputGroup>
                                            <InputGroupInput
                                                id="email"
                                                type="email"
                                                placeholder="student@example.com"
                                                value={data.email}
                                                onChange={(event) => setData("email", event.target.value)}
                                            />
                                            <InputGroupAddon align="inline-end">
                                                <Mail className="h-4 w-4" />
                                            </InputGroupAddon>
                                        </InputGroup>
                                        {fieldError("email")}
                                    </Field>

                                    <Field>
                                        <div className="flex items-center justify-between gap-2">
                                            <FieldLabel htmlFor="phone">
                                                <Phone className="mr-1 inline h-3.5 w-3.5" />
                                                Phone
                                            </FieldLabel>
                                            <BadgeOptional variant="warning-outline" size="sm">
                                                Optional
                                            </BadgeOptional>
                                        </div>
                                        <PhoneInput
                                            id="phone"
                                            value={data.phone}
                                            onChange={(value) => setData("phone", value)}
                                            defaultCountryCode={branding?.defaultCountryCode}
                                        />
                                    </Field>

                                    <div className="space-y-2 md:col-span-2">
                                        <div className="flex items-center justify-between">
                                            <div className="flex items-center gap-2">
                                                <Label htmlFor="current_address" className="flex items-center gap-1.5">
                                                    <MapPin className="h-3.5 w-3.5" />
                                                    Current Address
                                                </Label>
                                                <Tooltip>
                                                    <TooltipTrigger className="inline-flex items-center">
                                                        <HelpCircleIcon className="text-muted-foreground size-3.5" />
                                                    </TooltipTrigger>
                                                    <TooltipContent>
                                                        Where the student currently resides. May differ from the permanent home address.
                                                    </TooltipContent>
                                                </Tooltip>
                                            </div>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => setData("current_address", data.permanent_address)}
                                                disabled={!data.permanent_address}
                                                className="h-auto px-2 py-1 text-xs"
                                            >
                                                <Copy className="mr-1 h-3 w-3" />
                                                Same as Permanent
                                            </Button>
                                        </div>
                                        <Textarea
                                            id="current_address"
                                            value={data.current_address}
                                            onChange={(event) => setData("current_address", event.target.value)}
                                            rows={3}
                                        />
                                    </div>
                                    <div className="space-y-2 md:col-span-2">
                                        <div className="flex items-center justify-between">
                                            <Label htmlFor="permanent_address" className="flex items-center gap-1.5">
                                                <MapPin className="h-3.5 w-3.5" />
                                                Permanent Address
                                            </Label>
                                            <BadgeOptional variant="warning-outline" size="sm">
                                                Optional
                                            </BadgeOptional>
                                        </div>
                                        <Textarea
                                            id="permanent_address"
                                            value={data.permanent_address}
                                            onChange={(event) => setData("permanent_address", event.target.value)}
                                            rows={3}
                                        />
                                    </div>

                                    {!isSHS ? (
                                        <Field>
                                            <div className="flex items-center gap-2">
                                                <FieldLabel htmlFor="course_id">
                                                    <BookOpen className="mr-1 inline h-3.5 w-3.5" />
                                                    {isTESDA ? "TESDA Qualification" : "Course / Program"} <span className="text-destructive">*</span>
                                                </FieldLabel>
                                                <Tooltip>
                                                    <TooltipTrigger className="inline-flex items-center">
                                                        <HelpCircleIcon className="text-muted-foreground size-3.5" />
                                                    </TooltipTrigger>
                                                    <TooltipContent>
                                                        {isTESDA
                                                            ? "The TESDA qualification the student is enrolled in."
                                                            : "The degree program the student is enrolled in. Courses are grouped by department."}
                                                    </TooltipContent>
                                                </Tooltip>
                                            </div>
                                            <Select items={flatCourses} value={data.course_id} onValueChange={setSelectData("course_id")}>
                                                <SelectTrigger id="course_id" className="w-full">
                                                    <SelectValue placeholder="Select course" />
                                                </SelectTrigger>
                                                <SelectContent alignItemWithTrigger={false} className="w-(--anchor-width) max-w-2xl min-w-md">
                                                    {visibleCourseGroups.map((group, groupIndex) => (
                                                        <SelectGroup key={group.label}>
                                                            <SelectLabel>{group.label}</SelectLabel>
                                                            {group.items.map((course) => (
                                                                <SelectItem
                                                                    key={course.value}
                                                                    value={course.value.toString()}
                                                                    disabled={course.is_active === false}
                                                                >
                                                                    {course.label}
                                                                    {course.tesda_program_type === "diploma" && (
                                                                        <span className="text-muted-foreground ml-1">
                                                                            · Institutional Diploma
                                                                            {course.duration_years ? ` · ${course.duration_years} year(s)` : ""}
                                                                            {course.internship_hours ? ` · ${course.internship_hours} OJT h` : ""}
                                                                        </span>
                                                                    )}
                                                                </SelectItem>
                                                            ))}
                                                            {groupIndex < options.courses.length - 1 && <SelectSeparator />}
                                                        </SelectGroup>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                            {fieldError("course_id")}
                                        </Field>
                                    ) : (
                                        <Field>
                                            <div className="flex items-center gap-2">
                                                <FieldLabel htmlFor="shs_strand_id">
                                                    <BookOpen className="mr-1 inline h-3.5 w-3.5" />
                                                    SHS Strand <span className="text-destructive">*</span>
                                                </FieldLabel>
                                                <Tooltip>
                                                    <TooltipTrigger className="inline-flex items-center">
                                                        <HelpCircleIcon className="text-muted-foreground size-3.5" />
                                                    </TooltipTrigger>
                                                    <TooltipContent>
                                                        The senior high school track and strand the student is enrolled in.
                                                    </TooltipContent>
                                                </Tooltip>
                                            </div>
                                            <Select value={data.shs_strand_id} onValueChange={setSelectData("shs_strand_id")}>
                                                <SelectTrigger id="shs_strand_id">
                                                    <SelectValue placeholder="Select strand" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {options.shs_strands.map((strand) => (
                                                        <SelectItem key={strand.value} value={strand.value.toString()}>
                                                            {strand.label}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                            {fieldError("shs_strand_id")}
                                        </Field>
                                    )}

                                    <Field>
                                        <FieldLabel htmlFor="academic_year">
                                            {isSHS ? "Grade Level" : "Year Level"} <span className="text-destructive">*</span>
                                        </FieldLabel>
                                        <Select value={data.academic_year} onValueChange={setSelectData("academic_year")}>
                                            <SelectTrigger id="academic_year">
                                                <SelectValue placeholder="Select level" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {yearLevelOptions.map((option) => (
                                                    <SelectItem key={option.value} value={option.value}>
                                                        {option.label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        {fieldError("academic_year")}
                                    </Field>
                                </CardContent>
                            </Card>

                            <Card id="student-section-family" className="scroll-mt-36">
                                <CardHeader className="cursor-pointer select-none" onClick={() => toggleSection("family")}>
                                    <div className="flex items-center justify-between">
                                        <CardTitle className="flex items-center gap-2 text-lg">
                                            <School className="text-primary h-5 w-5" />
                                            Family, Personal, and Education
                                        </CardTitle>
                                        <ChevronDown
                                            className={cn(
                                                "text-muted-foreground h-5 w-5 transition-transform duration-200",
                                                collapsedSections.family && "-rotate-90",
                                            )}
                                        />
                                    </div>
                                </CardHeader>
                                {!collapsedSections.family && (
                                    <CardContent className="grid gap-5 md:grid-cols-2">
                                        <Field>
                                            <div className="flex items-center justify-between gap-2">
                                                <FieldLabel htmlFor="fathers_name">Father's Name</FieldLabel>
                                                <BadgeOptional variant="warning-outline" size="sm">
                                                    Optional
                                                </BadgeOptional>
                                            </div>
                                            <AutocompleteInput
                                                id="fathers_name"
                                                value={data.fathers_name}
                                                onChange={(value: string) => setData("fathers_name", value)}
                                                onBlur={(value) => setData("fathers_name", formatPersonName(value))}
                                                fieldName="fathers_name"
                                            />
                                        </Field>
                                        <Field>
                                            <div className="flex items-center justify-between gap-2">
                                                <FieldLabel htmlFor="mothers_name">Mother's Name</FieldLabel>
                                                <BadgeOptional variant="warning-outline" size="sm">
                                                    Optional
                                                </BadgeOptional>
                                            </div>
                                            <AutocompleteInput
                                                id="mothers_name"
                                                value={data.mothers_name}
                                                onChange={(value: string) => setData("mothers_name", value)}
                                                onBlur={(value) => setData("mothers_name", formatPersonName(value))}
                                                fieldName="mothers_name"
                                            />
                                        </Field>
                                        <Field>
                                            <div className="flex items-center justify-between gap-2">
                                                <FieldLabel htmlFor="birthplace">Birthplace</FieldLabel>
                                                <BadgeOptional variant="warning-outline" size="sm">
                                                    Optional
                                                </BadgeOptional>
                                            </div>
                                            <AutocompleteFieldInput
                                                id="birthplace"
                                                value={data.birthplace}
                                                onChange={(value: string) => setData("birthplace", value)}
                                                fieldName="birthplace"
                                                placeholder="Type or pick a birthplace"
                                            />
                                        </Field>
                                        <Field>
                                            <div className="flex items-center justify-between gap-2">
                                                <FieldLabel htmlFor="civil_status">Civil Status</FieldLabel>
                                                <BadgeOptional variant="warning-outline" size="sm">
                                                    Optional
                                                </BadgeOptional>
                                            </div>
                                            <Select value={data.civil_status} onValueChange={setSelectData("civil_status")}>
                                                <SelectTrigger id="civil_status">
                                                    <SelectValue placeholder="Select status" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {CIVIL_STATUS_OPTIONS.map((status) => (
                                                        <SelectItem key={status.value} value={status.value}>
                                                            {status.label}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        </Field>
                                        <Field>
                                            <div className="flex items-center justify-between gap-2">
                                                <FieldLabel htmlFor="nationality">Nationality</FieldLabel>
                                                <BadgeOptional variant="warning-outline" size="sm">
                                                    Optional
                                                </BadgeOptional>
                                            </div>
                                            <Select
                                                value={data.nationality}
                                                onValueChange={(value) => {
                                                    if (value === null) {
                                                        return;
                                                    }

                                                    setData("nationality", value);
                                                    setData("citizenship", value);
                                                }}
                                            >
                                                <SelectTrigger id="nationality">
                                                    <SelectValue placeholder="Select nationality" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {NATIONALITY_OPTIONS.map((nationality) => (
                                                        <SelectItem key={nationality.value} value={nationality.value}>
                                                            {nationality.label}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        </Field>
                                        <Field>
                                            <div className="flex items-center justify-between gap-2">
                                                <FieldLabel htmlFor="citizenship">Citizenship</FieldLabel>
                                                <BadgeOptional variant="warning-outline" size="sm">
                                                    Optional
                                                </BadgeOptional>
                                            </div>
                                            <Input
                                                id="citizenship"
                                                value={data.citizenship}
                                                onChange={(event) => setData("citizenship", event.target.value)}
                                            />
                                        </Field>
                                        <Field>
                                            <div className="flex items-center justify-between gap-2">
                                                <FieldLabel htmlFor="religion">Religion</FieldLabel>
                                                <BadgeOptional variant="warning-outline" size="sm">
                                                    Optional
                                                </BadgeOptional>
                                            </div>
                                            <AutocompleteFieldInput
                                                id="religion"
                                                value={data.religion}
                                                onChange={(value: string) => setData("religion", value)}
                                                fieldName="religion"
                                                placeholder="Type or pick a religion"
                                            />
                                        </Field>
                                        <Field>
                                            <div className="flex items-center justify-between gap-2">
                                                <FieldLabel htmlFor="height">Height</FieldLabel>
                                                <BadgeOptional variant="warning-outline" size="sm">
                                                    Optional
                                                </BadgeOptional>
                                            </div>
                                            <Input
                                                id="height"
                                                value={data.height}
                                                onChange={(event) => setData("height", event.target.value)}
                                                placeholder="e.g. 170"
                                            />
                                        </Field>
                                        <Field>
                                            <div className="flex items-center justify-between gap-2">
                                                <FieldLabel htmlFor="weight">Weight</FieldLabel>
                                                <BadgeOptional variant="warning-outline" size="sm">
                                                    Optional
                                                </BadgeOptional>
                                            </div>
                                            <Input
                                                id="weight"
                                                value={data.weight}
                                                onChange={(event) => setData("weight", event.target.value)}
                                                placeholder="e.g. 60"
                                            />
                                        </Field>

                                        <div className="grid gap-4 border-t pt-5 md:col-span-2 md:grid-cols-3">
                                            <Field>
                                                <div className="flex items-center justify-between gap-2">
                                                    <FieldLabel htmlFor="elementary_school">Elementary School</FieldLabel>
                                                    <BadgeOptional variant="warning-outline" size="sm">
                                                        Optional
                                                    </BadgeOptional>
                                                </div>
                                                <SchoolAutocompleteInput
                                                    id="elementary_school"
                                                    value={data.elementary_school}
                                                    onChange={(value: string) => setData("elementary_school", value)}
                                                    fieldName="elementary_school"
                                                    onSelectOption={fillSchoolAddress("elementary_school_address")}
                                                    placeholder="Type or pick a school"
                                                />
                                            </Field>
                                            <Field>
                                                <div className="flex items-center justify-between gap-2">
                                                    <FieldLabel htmlFor="elementary_graduate_year">Elementary Year</FieldLabel>
                                                    <BadgeOptional variant="warning-outline" size="sm">
                                                        Optional
                                                    </BadgeOptional>
                                                </div>
                                                <Input
                                                    id="elementary_graduate_year"
                                                    type="number"
                                                    min={1900}
                                                    max={new Date().getFullYear()}
                                                    value={data.elementary_graduate_year}
                                                    onChange={(event) => setData("elementary_graduate_year", event.target.value)}
                                                />
                                            </Field>
                                            <Field>
                                                <div className="flex items-center justify-between gap-2">
                                                    <FieldLabel htmlFor="elementary_school_address">Elementary Address</FieldLabel>
                                                    <BadgeOptional variant="warning-outline" size="sm">
                                                        Optional
                                                    </BadgeOptional>
                                                </div>
                                                <AutocompleteFieldInput
                                                    id="elementary_school_address"
                                                    value={data.elementary_school_address}
                                                    onChange={(value: string) => setData("elementary_school_address", value)}
                                                    fieldName="elementary_school_address"
                                                    placeholder="Type or pick a school address"
                                                />
                                            </Field>
                                            <Field>
                                                <div className="flex items-center justify-between gap-2">
                                                    <FieldLabel htmlFor="junior_high_school_name">Junior High School</FieldLabel>
                                                    <BadgeOptional variant="warning-outline" size="sm">
                                                        Optional
                                                    </BadgeOptional>
                                                </div>
                                                <SchoolAutocompleteInput
                                                    id="junior_high_school_name"
                                                    value={data.junior_high_school_name}
                                                    onChange={(value: string) => setData("junior_high_school_name", value)}
                                                    fieldName="junior_high_school_name"
                                                    onSelectOption={fillSchoolAddress("junior_high_school_address")}
                                                    placeholder="Type or pick a school"
                                                />
                                            </Field>
                                            <Field>
                                                <div className="flex items-center justify-between gap-2">
                                                    <FieldLabel htmlFor="junior_high_graduation_year">Junior High Year</FieldLabel>
                                                    <BadgeOptional variant="warning-outline" size="sm">
                                                        Optional
                                                    </BadgeOptional>
                                                </div>
                                                <Input
                                                    id="junior_high_graduation_year"
                                                    type="number"
                                                    min={1900}
                                                    max={new Date().getFullYear()}
                                                    value={data.junior_high_graduation_year}
                                                    onChange={(event) => setData("junior_high_graduation_year", event.target.value)}
                                                />
                                            </Field>
                                            <Field>
                                                <div className="flex items-center justify-between gap-2">
                                                    <FieldLabel htmlFor="junior_high_school_address">Junior High Address</FieldLabel>
                                                    <BadgeOptional variant="warning-outline" size="sm">
                                                        Optional
                                                    </BadgeOptional>
                                                </div>
                                                <AutocompleteFieldInput
                                                    id="junior_high_school_address"
                                                    value={data.junior_high_school_address}
                                                    onChange={(value: string) => setData("junior_high_school_address", value)}
                                                    fieldName="junior_high_school_address"
                                                    placeholder="Type or pick a school address"
                                                />
                                            </Field>
                                            <Field>
                                                <div className="flex items-center justify-between gap-2">
                                                    <FieldLabel htmlFor="senior_high_name">Senior High School</FieldLabel>
                                                    <BadgeOptional variant="warning-outline" size="sm">
                                                        Optional
                                                    </BadgeOptional>
                                                </div>
                                                <SchoolAutocompleteInput
                                                    id="senior_high_name"
                                                    value={data.senior_high_name}
                                                    onChange={(value: string) => setData("senior_high_name", value)}
                                                    fieldName="senior_high_name"
                                                    onSelectOption={fillSchoolAddress("senior_high_address")}
                                                    placeholder="Type or pick a school"
                                                />
                                            </Field>
                                            <Field>
                                                <div className="flex items-center justify-between gap-2">
                                                    <FieldLabel htmlFor="senior_high_graduate_year">Senior High Year</FieldLabel>
                                                    <BadgeOptional variant="warning-outline" size="sm">
                                                        Optional
                                                    </BadgeOptional>
                                                </div>
                                                <Input
                                                    id="senior_high_graduate_year"
                                                    type="number"
                                                    min={1900}
                                                    max={new Date().getFullYear()}
                                                    value={data.senior_high_graduate_year}
                                                    onChange={(event) => setData("senior_high_graduate_year", event.target.value)}
                                                />
                                            </Field>
                                            <Field>
                                                <div className="flex items-center justify-between gap-2">
                                                    <FieldLabel htmlFor="senior_high_address">Senior High Address</FieldLabel>
                                                    <BadgeOptional variant="warning-outline" size="sm">
                                                        Optional
                                                    </BadgeOptional>
                                                </div>
                                                <AutocompleteFieldInput
                                                    id="senior_high_address"
                                                    value={data.senior_high_address}
                                                    onChange={(value: string) => setData("senior_high_address", value)}
                                                    fieldName="senior_high_address"
                                                    placeholder="Type or pick a school address"
                                                />
                                            </Field>
                                            <Field>
                                                <div className="flex items-center justify-between gap-2">
                                                    <FieldLabel htmlFor="college_school">College School (if transferee)</FieldLabel>
                                                    <BadgeOptional variant="warning-outline" size="sm">
                                                        Optional
                                                    </BadgeOptional>
                                                </div>
                                                <SchoolAutocompleteInput
                                                    id="college_school"
                                                    value={data.college_school}
                                                    onChange={(value: string) => setData("college_school", value)}
                                                    fieldName="college_school"
                                                    placeholder="Type or pick a school"
                                                />
                                            </Field>
                                            <Field>
                                                <div className="flex items-center justify-between gap-2">
                                                    <FieldLabel htmlFor="college_course">College Course</FieldLabel>
                                                    <BadgeOptional variant="warning-outline" size="sm">
                                                        Optional
                                                    </BadgeOptional>
                                                </div>
                                                <AutocompleteFieldInput
                                                    id="college_course"
                                                    value={data.college_course}
                                                    onChange={(value: string) => setData("college_course", value)}
                                                    fieldName="college_course"
                                                    placeholder="Type or pick a course"
                                                />
                                            </Field>
                                            <Field>
                                                <div className="flex items-center justify-between gap-2">
                                                    <FieldLabel htmlFor="college_year_graduated">College Year Graduated</FieldLabel>
                                                    <BadgeOptional variant="warning-outline" size="sm">
                                                        Optional
                                                    </BadgeOptional>
                                                </div>
                                                <Input
                                                    id="college_year_graduated"
                                                    type="number"
                                                    min={1900}
                                                    max={new Date().getFullYear()}
                                                    value={data.college_year_graduated}
                                                    onChange={(event) => setData("college_year_graduated", event.target.value)}
                                                />
                                            </Field>
                                            <Field>
                                                <div className="flex items-center justify-between gap-2">
                                                    <FieldLabel htmlFor="vocational_school">Vocational School</FieldLabel>
                                                    <BadgeOptional variant="warning-outline" size="sm">
                                                        Optional
                                                    </BadgeOptional>
                                                </div>
                                                <SchoolAutocompleteInput
                                                    id="vocational_school"
                                                    value={data.vocational_school}
                                                    onChange={(value: string) => setData("vocational_school", value)}
                                                    fieldName="vocational_school"
                                                    placeholder="Type or pick a school"
                                                />
                                            </Field>
                                            <Field>
                                                <div className="flex items-center justify-between gap-2">
                                                    <FieldLabel htmlFor="vocational_course">Vocational Course</FieldLabel>
                                                    <BadgeOptional variant="warning-outline" size="sm">
                                                        Optional
                                                    </BadgeOptional>
                                                </div>
                                                <AutocompleteFieldInput
                                                    id="vocational_course"
                                                    value={data.vocational_course}
                                                    onChange={(value: string) => setData("vocational_course", value)}
                                                    fieldName="vocational_course"
                                                    placeholder="Type or pick a course"
                                                />
                                            </Field>
                                            <Field>
                                                <div className="flex items-center justify-between gap-2">
                                                    <FieldLabel htmlFor="vocational_year_graduated">Vocational Year Graduated</FieldLabel>
                                                    <BadgeOptional variant="warning-outline" size="sm">
                                                        Optional
                                                    </BadgeOptional>
                                                </div>
                                                <Input
                                                    id="vocational_year_graduated"
                                                    type="number"
                                                    min={1900}
                                                    max={new Date().getFullYear()}
                                                    value={data.vocational_year_graduated}
                                                    onChange={(event) => setData("vocational_year_graduated", event.target.value)}
                                                />
                                            </Field>
                                        </div>
                                    </CardContent>
                                )}
                            </Card>

                            <Card id="student-section-reporting" className="scroll-mt-36">
                                <CardHeader className="cursor-pointer select-none" onClick={() => toggleSection("reporting")}>
                                    <div className="flex items-center justify-between">
                                        <CardTitle className="flex items-center gap-2 text-lg">
                                            <Banknote className="text-primary h-5 w-5" />
                                            Reporting Details
                                        </CardTitle>
                                        <ChevronDown
                                            className={cn(
                                                "text-muted-foreground h-5 w-5 transition-transform duration-200",
                                                collapsedSections.reporting && "-rotate-90",
                                            )}
                                        />
                                    </div>
                                </CardHeader>
                                {!collapsedSections.reporting && (
                                    <CardContent className="grid gap-5 md:grid-cols-2">
                                        <Field>
                                            <div className="flex items-center justify-between gap-2">
                                                <FieldLabel htmlFor="region_of_origin">Region of Origin</FieldLabel>
                                                <BadgeOptional variant="warning-outline" size="sm">
                                                    Optional
                                                </BadgeOptional>
                                            </div>
                                            <Select value={data.region_of_origin} onValueChange={handleRegionChange}>
                                                <SelectTrigger id="region_of_origin">
                                                    <SelectValue placeholder="Select region" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {PHILIPPINE_REGIONS.map((region) => (
                                                        <SelectItem key={region.code} value={region.value}>
                                                            {region.label}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        </Field>
                                        <Field>
                                            <div className="flex items-center justify-between gap-2">
                                                <FieldLabel htmlFor="ethnicity">Ethnicity</FieldLabel>
                                                <BadgeOptional variant="warning-outline" size="sm">
                                                    Optional
                                                </BadgeOptional>
                                            </div>
                                            <AutocompleteFieldInput
                                                id="ethnicity"
                                                value={data.ethnicity}
                                                onChange={(value: string) => setData("ethnicity", value)}
                                                fieldName="ethnicity"
                                                placeholder="Type or pick an ethnicity"
                                            />
                                        </Field>
                                        <Field>
                                            <div className="flex items-center justify-between gap-2">
                                                <FieldLabel htmlFor="province_of_origin">Province of Origin</FieldLabel>
                                                <BadgeOptional variant="warning-outline" size="sm">
                                                    Optional
                                                </BadgeOptional>
                                            </div>
                                            <Select
                                                value={data.province_of_origin}
                                                onValueChange={handleProvinceChange}
                                                disabled={!selectedRegion || provinceOptions.length === 0}
                                            >
                                                <SelectTrigger id="province_of_origin">
                                                    <SelectValue
                                                        placeholder={
                                                            selectedRegion && provinceOptions.length === 0
                                                                ? "No province required"
                                                                : "Select province"
                                                        }
                                                    />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {provinceOptions.map((province) => (
                                                        <SelectItem key={province.code} value={province.name}>
                                                            {province.name}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        </Field>
                                        <Field>
                                            <div className="flex items-center justify-between gap-2">
                                                <FieldLabel htmlFor="city_of_origin">City of Origin</FieldLabel>
                                                <BadgeOptional variant="warning-outline" size="sm">
                                                    Optional
                                                </BadgeOptional>
                                            </div>
                                            <Select
                                                value={data.city_of_origin}
                                                onValueChange={setSelectData("city_of_origin")}
                                                disabled={!selectedRegion || cityOptions.length === 0}
                                            >
                                                <SelectTrigger id="city_of_origin">
                                                    <SelectValue placeholder="Select city or municipality" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {cityOptions.map((city) => (
                                                        <SelectItem key={city.code} value={city.name}>
                                                            {city.name}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        </Field>
                                        <div className="flex items-center gap-3 rounded-md border p-3 md:col-span-2">
                                            <Checkbox
                                                id="is_indigenous_person"
                                                checked={data.is_indigenous_person}
                                                onCheckedChange={(checked) => setData("is_indigenous_person", checked === true)}
                                            />
                                            <Label htmlFor="is_indigenous_person" className="cursor-pointer">
                                                Indigenous Person
                                            </Label>
                                        </div>
                                        {data.is_indigenous_person && (
                                            <div className="md:col-span-2">
                                                <Field>
                                                    <div className="flex items-center justify-between gap-2">
                                                        <FieldLabel htmlFor="indigenous_group">Indigenous Group</FieldLabel>
                                                        <BadgeOptional variant="warning-outline" size="sm">
                                                            Optional
                                                        </BadgeOptional>
                                                    </div>
                                                    <Input
                                                        id="indigenous_group"
                                                        value={data.indigenous_group}
                                                        onChange={(event) => setData("indigenous_group", event.target.value)}
                                                    />
                                                </Field>
                                            </div>
                                        )}
                                        <div className="grid gap-3 md:col-span-2 md:grid-cols-3">
                                            <div className="flex items-center gap-2 rounded-md border p-3">
                                                <Checkbox
                                                    id="is_pwd"
                                                    checked={data.is_pwd}
                                                    onCheckedChange={(checked) => setData("is_pwd", checked === true)}
                                                />
                                                <Label htmlFor="is_pwd" className="cursor-pointer">
                                                    PWD
                                                </Label>
                                            </div>
                                            <div className="flex items-center gap-2 rounded-md border p-3">
                                                <Checkbox
                                                    id="is_solo_parent"
                                                    checked={data.is_solo_parent}
                                                    onCheckedChange={(checked) => setData("is_solo_parent", checked === true)}
                                                />
                                                <Label htmlFor="is_solo_parent" className="cursor-pointer">
                                                    Solo Parent
                                                </Label>
                                            </div>
                                            <div className="flex items-center gap-2 rounded-md border p-3">
                                                <Checkbox
                                                    id="is_senior_citizen"
                                                    checked={data.is_senior_citizen}
                                                    onCheckedChange={(checked) => setData("is_senior_citizen", checked === true)}
                                                />
                                                <Label htmlFor="is_senior_citizen" className="cursor-pointer">
                                                    Senior Citizen
                                                </Label>
                                            </div>
                                            <div className="flex items-center gap-2 rounded-md border p-3">
                                                <Checkbox
                                                    id="is_magna_carta"
                                                    checked={data.is_magna_carta}
                                                    onCheckedChange={(checked) => setData("is_magna_carta", checked === true)}
                                                />
                                                <Label htmlFor="is_magna_carta" className="cursor-pointer">
                                                    Magna Carta
                                                </Label>
                                            </div>
                                            <div className="flex items-center gap-2 rounded-md border p-3">
                                                <Checkbox
                                                    id="is_underprivileged"
                                                    checked={data.is_underprivileged}
                                                    onCheckedChange={(checked) => setData("is_underprivileged", checked === true)}
                                                />
                                                <Label htmlFor="is_underprivileged" className="cursor-pointer">
                                                    Underprivileged
                                                </Label>
                                            </div>
                                            <div className="flex items-center gap-2 rounded-md border p-3">
                                                <Checkbox
                                                    id="is_first_generation"
                                                    checked={data.is_first_generation}
                                                    onCheckedChange={(checked) => setData("is_first_generation", checked === true)}
                                                />
                                                <Label htmlFor="is_first_generation" className="cursor-pointer">
                                                    First Generation
                                                </Label>
                                            </div>
                                        </div>
                                        {data.is_pwd && (
                                            <div className="md:col-span-2">
                                                <Field>
                                                    <div className="flex items-center justify-between gap-2">
                                                        <FieldLabel htmlFor="pwd_type">PWD Type</FieldLabel>
                                                        <BadgeOptional variant="warning-outline" size="sm">
                                                            Optional
                                                        </BadgeOptional>
                                                    </div>
                                                    <Input
                                                        id="pwd_type"
                                                        value={data.pwd_type}
                                                        onChange={(event) => setData("pwd_type", event.target.value)}
                                                    />
                                                </Field>
                                            </div>
                                        )}
                                        <div className="grid gap-4 border-t pt-5 md:col-span-2 md:grid-cols-2">
                                            <div className="space-y-1 md:col-span-2">
                                                <h3 className="font-medium">Family Income</h3>
                                                <p className="text-muted-foreground text-sm">
                                                    Set income basis first, then choose one shared family range or separate father and mother ranges.
                                                </p>
                                            </div>
                                            <Field>
                                                <div className="flex items-center justify-between gap-2">
                                                    <FieldLabel htmlFor="income_bracket_mode">Income Basis</FieldLabel>
                                                    <BadgeOptional variant="warning-outline" size="sm">
                                                        Optional
                                                    </BadgeOptional>
                                                </div>
                                                <Select value={data.income_bracket_mode} onValueChange={setSelectData("income_bracket_mode")}>
                                                    <SelectTrigger id="income_bracket_mode">
                                                        <SelectValue placeholder="Select income basis" />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        {options.income_modes.map((mode) => (
                                                            <SelectItem key={mode.value} value={mode.value}>
                                                                {mode.label}
                                                            </SelectItem>
                                                        ))}
                                                    </SelectContent>
                                                </Select>
                                            </Field>
                                            <div className="flex items-center gap-2 rounded-md border p-3">
                                                <Checkbox
                                                    id="use_same_parent_income"
                                                    checked={data.use_same_parent_income}
                                                    onCheckedChange={(checked) => setData("use_same_parent_income", checked === true)}
                                                />
                                                <Label htmlFor="use_same_parent_income" className="cursor-pointer">
                                                    Father and mother have the same income bracket
                                                </Label>
                                            </div>
                                            {data.use_same_parent_income ? (
                                                <div className="md:col-span-2">
                                                    <Field>
                                                        <div className="flex items-center justify-between gap-2">
                                                            <FieldLabel htmlFor="family_income_bracket">Family Income Bracket</FieldLabel>
                                                            <BadgeOptional variant="warning-outline" size="sm">
                                                                Optional
                                                            </BadgeOptional>
                                                        </div>
                                                        <Select
                                                            value={data.family_income_bracket}
                                                            onValueChange={setSelectData("family_income_bracket")}
                                                        >
                                                            <SelectTrigger id="family_income_bracket">
                                                                <SelectValue placeholder="Select income range..." />
                                                            </SelectTrigger>
                                                            <SelectContent>
                                                                {activeIncomeBrackets.map((bracket) => (
                                                                    <SelectItem key={bracket.value} value={bracket.value}>
                                                                        {bracket.label}
                                                                    </SelectItem>
                                                                ))}
                                                            </SelectContent>
                                                        </Select>
                                                    </Field>
                                                </div>
                                            ) : (
                                                <>
                                                    <Field>
                                                        <div className="flex items-center justify-between gap-2">
                                                            <FieldLabel htmlFor="father_income_bracket">Father Income Bracket</FieldLabel>
                                                            <BadgeOptional variant="warning-outline" size="sm">
                                                                Optional
                                                            </BadgeOptional>
                                                        </div>
                                                        <Select
                                                            value={data.father_income_bracket}
                                                            onValueChange={setSelectData("father_income_bracket")}
                                                        >
                                                            <SelectTrigger id="father_income_bracket">
                                                                <SelectValue placeholder="Select income range..." />
                                                            </SelectTrigger>
                                                            <SelectContent>
                                                                {activeIncomeBrackets.map((bracket) => (
                                                                    <SelectItem key={bracket.value} value={bracket.value}>
                                                                        {bracket.label}
                                                                    </SelectItem>
                                                                ))}
                                                            </SelectContent>
                                                        </Select>
                                                    </Field>
                                                    <Field>
                                                        <div className="flex items-center justify-between gap-2">
                                                            <FieldLabel htmlFor="mother_income_bracket">Mother Income Bracket</FieldLabel>
                                                            <BadgeOptional variant="warning-outline" size="sm">
                                                                Optional
                                                            </BadgeOptional>
                                                        </div>
                                                        <Select
                                                            value={data.mother_income_bracket}
                                                            onValueChange={setSelectData("mother_income_bracket")}
                                                        >
                                                            <SelectTrigger id="mother_income_bracket">
                                                                <SelectValue placeholder="Select income range..." />
                                                            </SelectTrigger>
                                                            <SelectContent>
                                                                {activeIncomeBrackets.map((bracket) => (
                                                                    <SelectItem key={bracket.value} value={bracket.value}>
                                                                        {bracket.label}
                                                                    </SelectItem>
                                                                ))}
                                                            </SelectContent>
                                                        </Select>
                                                    </Field>
                                                </>
                                            )}
                                        </div>
                                        <Field>
                                            <div className="flex items-center justify-between gap-2">
                                                <FieldLabel htmlFor="scholarship_type">Scholarship</FieldLabel>
                                                <BadgeOptional variant="warning-outline" size="sm">
                                                    Optional
                                                </BadgeOptional>
                                            </div>
                                            <Select value={data.scholarship_type} onValueChange={setSelectData("scholarship_type")}>
                                                <SelectTrigger id="scholarship_type">
                                                    <SelectValue placeholder="Select scholarship" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {options.scholarship_types.map((type) => (
                                                        <SelectItem key={type.value} value={type.value.toString()}>
                                                            {type.label}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        </Field>
                                        {data.scholarship_type !== "none" && (
                                            <Field>
                                                <div className="flex items-center justify-between gap-2">
                                                    <FieldLabel htmlFor="scholarship_details">Scholarship Details</FieldLabel>
                                                    <BadgeOptional variant="warning-outline" size="sm">
                                                        Optional
                                                    </BadgeOptional>
                                                </div>
                                                <Textarea
                                                    id="scholarship_details"
                                                    value={data.scholarship_details}
                                                    onChange={(event) => setData("scholarship_details", event.target.value)}
                                                    rows={2}
                                                />
                                            </Field>
                                        )}

                                        {isGraduated && (
                                            <div className="grid gap-5 border-t pt-5 md:col-span-2 md:grid-cols-2">
                                                <Field>
                                                    <div className="flex items-center justify-between gap-2">
                                                        <FieldLabel htmlFor="employment_status">
                                                            <Briefcase className="mr-1 inline h-3.5 w-3.5" />
                                                            Employment Status
                                                        </FieldLabel>
                                                        <BadgeOptional variant="warning-outline" size="sm">
                                                            Optional
                                                        </BadgeOptional>
                                                    </div>
                                                    <Select value={data.employment_status} onValueChange={setSelectData("employment_status")}>
                                                        <SelectTrigger id="employment_status">
                                                            <SelectValue placeholder="Select status" />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            {options.employment_statuses.map((status) => (
                                                                <SelectItem key={status.value} value={status.value.toString()}>
                                                                    {status.label}
                                                                </SelectItem>
                                                            ))}
                                                        </SelectContent>
                                                    </Select>
                                                </Field>
                                                {showEmployment && (
                                                    <>
                                                        <Field>
                                                            <div className="flex items-center justify-between gap-2">
                                                                <FieldLabel htmlFor="employer_name">Employer</FieldLabel>
                                                                <BadgeOptional variant="warning-outline" size="sm">
                                                                    Optional
                                                                </BadgeOptional>
                                                            </div>
                                                            <AutocompleteInput
                                                                id="employer_name"
                                                                value={data.employer_name}
                                                                onChange={(value: string) => setData("employer_name", value)}
                                                                fieldName="employer_name"
                                                            />
                                                        </Field>
                                                        <Field>
                                                            <div className="flex items-center justify-between gap-2">
                                                                <FieldLabel htmlFor="job_position">Position</FieldLabel>
                                                                <BadgeOptional variant="warning-outline" size="sm">
                                                                    Optional
                                                                </BadgeOptional>
                                                            </div>
                                                            <AutocompleteInput
                                                                id="job_position"
                                                                value={data.job_position}
                                                                onChange={(value: string) => setData("job_position", value)}
                                                                fieldName="job_position"
                                                            />
                                                        </Field>
                                                        <Field>
                                                            <div className="flex items-center justify-between gap-2">
                                                                <FieldLabel htmlFor="employment_date">Employment Date</FieldLabel>
                                                                <BadgeOptional variant="warning-outline" size="sm">
                                                                    Optional
                                                                </BadgeOptional>
                                                            </div>
                                                            <Input
                                                                id="employment_date"
                                                                type="date"
                                                                value={data.employment_date}
                                                                onChange={(event) => setData("employment_date", event.target.value)}
                                                            />
                                                        </Field>
                                                        <div className="flex items-center gap-3 rounded-md border p-3">
                                                            <Checkbox
                                                                id="employed_by_institution"
                                                                checked={data.employed_by_institution}
                                                                onCheckedChange={(checked) => setData("employed_by_institution", checked === true)}
                                                            />
                                                            <Label htmlFor="employed_by_institution" className="cursor-pointer">
                                                                Employed by this institution
                                                            </Label>
                                                        </div>
                                                    </>
                                                )}
                                            </div>
                                        )}

                                        {isWithdrawn && (
                                            <div className="grid gap-5 border-t pt-5 md:col-span-2 md:grid-cols-2">
                                                <Field>
                                                    <div className="flex items-center justify-between gap-2">
                                                        <FieldLabel htmlFor="attrition_category">Attrition Category</FieldLabel>
                                                        <BadgeOptional variant="warning-outline" size="sm">
                                                            Optional
                                                        </BadgeOptional>
                                                    </div>
                                                    <Select value={data.attrition_category} onValueChange={setSelectData("attrition_category")}>
                                                        <SelectTrigger id="attrition_category">
                                                            <SelectValue placeholder="Select category" />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            {options.attrition_categories.map((category) => (
                                                                <SelectItem key={category.value} value={category.value.toString()}>
                                                                    {category.label}
                                                                </SelectItem>
                                                            ))}
                                                        </SelectContent>
                                                    </Select>
                                                </Field>
                                                <Field>
                                                    <div className="flex items-center justify-between gap-2">
                                                        <FieldLabel htmlFor="withdrawal_date">Withdrawal Date</FieldLabel>
                                                        <BadgeOptional variant="warning-outline" size="sm">
                                                            Optional
                                                        </BadgeOptional>
                                                    </div>
                                                    <Input
                                                        id="withdrawal_date"
                                                        type="date"
                                                        value={data.withdrawal_date}
                                                        onChange={(event) => setData("withdrawal_date", event.target.value)}
                                                    />
                                                </Field>
                                                <Field>
                                                    <div className="flex items-center justify-between gap-2">
                                                        <FieldLabel htmlFor="dropout_date">Dropout Date</FieldLabel>
                                                        <BadgeOptional variant="warning-outline" size="sm">
                                                            Optional
                                                        </BadgeOptional>
                                                    </div>
                                                    <Input
                                                        id="dropout_date"
                                                        type="date"
                                                        value={data.dropout_date}
                                                        onChange={(event) => setData("dropout_date", event.target.value)}
                                                    />
                                                </Field>
                                                <div className="md:col-span-2">
                                                    <Field>
                                                        <div className="flex items-center justify-between gap-2">
                                                            <FieldLabel htmlFor="withdrawal_reason">Withdrawal Reason</FieldLabel>
                                                            <BadgeOptional variant="warning-outline" size="sm">
                                                                Optional
                                                            </BadgeOptional>
                                                        </div>
                                                        <Textarea
                                                            id="withdrawal_reason"
                                                            value={data.withdrawal_reason}
                                                            onChange={(event) => setData("withdrawal_reason", event.target.value)}
                                                            rows={3}
                                                        />
                                                    </Field>
                                                </div>
                                            </div>
                                        )}

                                        <div className="md:col-span-2">
                                            <Field>
                                                <div className="flex items-center justify-between gap-2">
                                                    <FieldLabel htmlFor="remarks">Remarks</FieldLabel>
                                                    <BadgeOptional variant="warning-outline" size="sm">
                                                        Optional
                                                    </BadgeOptional>
                                                </div>
                                                <Textarea
                                                    id="remarks"
                                                    value={data.remarks}
                                                    onChange={(event) => setData("remarks", event.target.value)}
                                                    rows={3}
                                                />
                                            </Field>
                                        </div>
                                    </CardContent>
                                )}
                            </Card>
                        </div>

                        <div className="space-y-6">
                            <Card id="student-section-contact" className="scroll-mt-36">
                                <CardHeader className="cursor-pointer select-none" onClick={() => toggleSection("contact")}>
                                    <div className="flex items-center justify-between">
                                        <CardTitle className="flex items-center gap-2 text-lg">
                                            <Phone className="text-primary h-5 w-5" />
                                            Contact and Address
                                        </CardTitle>
                                        <ChevronDown
                                            className={cn(
                                                "text-muted-foreground h-5 w-5 transition-transform duration-200",
                                                collapsedSections.contact && "-rotate-90",
                                            )}
                                        />
                                    </div>
                                </CardHeader>
                                {!collapsedSections.contact && (
                                    <CardContent className="space-y-5">
                                        <Field>
                                            <div className="flex items-center justify-between gap-2">
                                                <FieldLabel htmlFor="personal_contact">Student Contact</FieldLabel>
                                                <BadgeOptional variant="info-outline" size="sm">
                                                    Auto
                                                </BadgeOptional>
                                            </div>
                                            <Input
                                                id="personal_contact"
                                                value={data.personal_contact}
                                                readOnly
                                                className="bg-muted text-muted-foreground"
                                            />
                                            <FieldDescription>Auto-filled from Phone above</FieldDescription>
                                        </Field>
                                        <div className="grid gap-3 md:grid-cols-2">
                                            <Field>
                                                <div className="flex items-center justify-between gap-2">
                                                    <FieldLabel htmlFor="facebook_contact">Facebook</FieldLabel>
                                                    <BadgeOptional variant="warning-outline" size="sm">
                                                        Optional
                                                    </BadgeOptional>
                                                </div>
                                                <Input
                                                    id="facebook_contact"
                                                    value={data.facebook_contact}
                                                    onChange={(event) => setData("facebook_contact", event.target.value)}
                                                    placeholder="facebook.com/username"
                                                />
                                            </Field>
                                            <Field>
                                                <div className="flex items-center justify-between gap-2">
                                                    <FieldLabel htmlFor="instagram">Instagram</FieldLabel>
                                                    <BadgeOptional variant="warning-outline" size="sm">
                                                        Optional
                                                    </BadgeOptional>
                                                </div>
                                                <Input
                                                    id="instagram"
                                                    value={data.instagram}
                                                    onChange={(event) => setData("instagram", event.target.value)}
                                                    placeholder="@username"
                                                />
                                            </Field>
                                            <Field>
                                                <div className="flex items-center justify-between gap-2">
                                                    <FieldLabel htmlFor="twitter">Twitter/X</FieldLabel>
                                                    <BadgeOptional variant="warning-outline" size="sm">
                                                        Optional
                                                    </BadgeOptional>
                                                </div>
                                                <Input
                                                    id="twitter"
                                                    value={data.twitter}
                                                    onChange={(event) => setData("twitter", event.target.value)}
                                                    placeholder="@username"
                                                />
                                            </Field>
                                            <Field>
                                                <div className="flex items-center justify-between gap-2">
                                                    <FieldLabel htmlFor="linkedin">LinkedIn</FieldLabel>
                                                    <BadgeOptional variant="warning-outline" size="sm">
                                                        Optional
                                                    </BadgeOptional>
                                                </div>
                                                <Input
                                                    id="linkedin"
                                                    value={data.linkedin}
                                                    onChange={(event) => setData("linkedin", event.target.value)}
                                                    placeholder="linkedin.com/in/username"
                                                />
                                            </Field>
                                        </div>
                                        <Field>
                                            <div className="flex items-center justify-between gap-2">
                                                <FieldLabel htmlFor="emergency_contact_name">Guardian Name</FieldLabel>
                                                <BadgeOptional variant="warning-outline" size="sm">
                                                    Optional
                                                </BadgeOptional>
                                            </div>
                                            <AutocompleteInput
                                                id="emergency_contact_name"
                                                value={data.emergency_contact_name}
                                                onChange={(value: string) => setData("emergency_contact_name", value)}
                                                onBlur={(value) => setData("emergency_contact_name", formatPersonName(value))}
                                                fieldName="emergency_contact_name"
                                            />
                                        </Field>
                                        <Field>
                                            <div className="flex items-center justify-between gap-2">
                                                <FieldLabel htmlFor="emergency_contact_phone">
                                                    <Phone className="mr-1 inline h-3.5 w-3.5" />
                                                    Guardian Phone
                                                </FieldLabel>
                                                <BadgeOptional variant="warning-outline" size="sm">
                                                    Optional
                                                </BadgeOptional>
                                            </div>
                                            <PhoneInput
                                                id="emergency_contact_phone"
                                                value={data.emergency_contact_phone}
                                                onChange={(value) => setData("emergency_contact_phone", value)}
                                                defaultCountryCode={branding?.defaultCountryCode}
                                            />
                                        </Field>
                                        <Field>
                                            <div className="flex items-center justify-between gap-2">
                                                <FieldLabel htmlFor="emergency_contact_relationship">Guardian Relationship</FieldLabel>
                                                <BadgeOptional variant="warning-outline" size="sm">
                                                    Optional
                                                </BadgeOptional>
                                            </div>
                                            <Select
                                                value={data.emergency_contact_relationship}
                                                onValueChange={setSelectData("emergency_contact_relationship")}
                                            >
                                                <SelectTrigger id="emergency_contact_relationship">
                                                    <SelectValue placeholder="Select relationship" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {RELATIONSHIP_OPTIONS.map((option) => (
                                                        <SelectItem key={option.value} value={option.value}>
                                                            {option.label}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                            <FieldDescription>Also used as the applicant guardian relationship.</FieldDescription>
                                        </Field>
                                        <Field>
                                            <div className="flex items-center justify-between gap-2">
                                                <FieldLabel htmlFor="emergency_contact_address">Guardian Address</FieldLabel>
                                                <BadgeOptional variant="warning-outline" size="sm">
                                                    Optional
                                                </BadgeOptional>
                                            </div>
                                            <Textarea
                                                id="emergency_contact_address"
                                                value={data.emergency_contact_address}
                                                onChange={(event) => setData("emergency_contact_address", event.target.value)}
                                                rows={2}
                                            />
                                        </Field>
                                        <div className="grid gap-3 border-t pt-4 md:grid-cols-2">
                                            <Field>
                                                <div className="flex items-center justify-between gap-2">
                                                    <FieldLabel htmlFor="father_occupation">Father Occupation</FieldLabel>
                                                    <BadgeOptional variant="warning-outline" size="sm">
                                                        Optional
                                                    </BadgeOptional>
                                                </div>
                                                <AutocompleteInput
                                                    id="father_occupation"
                                                    value={data.father_occupation}
                                                    onChange={(value: string) => setData("father_occupation", value)}
                                                    fieldName="father_occupation"
                                                />
                                            </Field>
                                            <Field>
                                                <div className="flex items-center justify-between gap-2">
                                                    <FieldLabel htmlFor="father_contact">Father Contact</FieldLabel>
                                                    <BadgeOptional variant="warning-outline" size="sm">
                                                        Optional
                                                    </BadgeOptional>
                                                </div>
                                                <PhoneInput
                                                    id="father_contact"
                                                    value={data.father_contact}
                                                    onChange={(value) => setData("father_contact", value)}
                                                    defaultCountryCode={branding?.defaultCountryCode}
                                                />
                                            </Field>
                                            <Field>
                                                <div className="flex items-center justify-between gap-2">
                                                    <FieldLabel htmlFor="father_email">Father Email</FieldLabel>
                                                    <BadgeOptional variant="warning-outline" size="sm">
                                                        Optional
                                                    </BadgeOptional>
                                                </div>
                                                <InputGroup>
                                                    <InputGroupInput
                                                        id="father_email"
                                                        type="email"
                                                        placeholder="father@example.com"
                                                        value={data.father_email}
                                                        onChange={(event) => setData("father_email", event.target.value)}
                                                    />
                                                    <InputGroupAddon align="inline-end">
                                                        <Mail className="h-4 w-4" />
                                                    </InputGroupAddon>
                                                </InputGroup>
                                            </Field>
                                            <Field>
                                                <div className="flex items-center justify-between gap-2">
                                                    <FieldLabel htmlFor="mother_occupation">Mother Occupation</FieldLabel>
                                                    <BadgeOptional variant="warning-outline" size="sm">
                                                        Optional
                                                    </BadgeOptional>
                                                </div>
                                                <AutocompleteInput
                                                    id="mother_occupation"
                                                    value={data.mother_occupation}
                                                    onChange={(value: string) => setData("mother_occupation", value)}
                                                    fieldName="mother_occupation"
                                                />
                                            </Field>
                                            <Field>
                                                <div className="flex items-center justify-between gap-2">
                                                    <FieldLabel htmlFor="mother_contact">Mother Contact</FieldLabel>
                                                    <BadgeOptional variant="warning-outline" size="sm">
                                                        Optional
                                                    </BadgeOptional>
                                                </div>
                                                <PhoneInput
                                                    id="mother_contact"
                                                    value={data.mother_contact}
                                                    onChange={(value) => setData("mother_contact", value)}
                                                    defaultCountryCode={branding?.defaultCountryCode}
                                                />
                                            </Field>
                                            <Field>
                                                <div className="flex items-center justify-between gap-2">
                                                    <FieldLabel htmlFor="mother_email">Mother Email</FieldLabel>
                                                    <BadgeOptional variant="warning-outline" size="sm">
                                                        Optional
                                                    </BadgeOptional>
                                                </div>
                                                <InputGroup>
                                                    <InputGroupInput
                                                        id="mother_email"
                                                        type="email"
                                                        placeholder="mother@example.com"
                                                        value={data.mother_email}
                                                        onChange={(event) => setData("mother_email", event.target.value)}
                                                    />
                                                    <InputGroupAddon align="inline-end">
                                                        <Mail className="h-4 w-4" />
                                                    </InputGroupAddon>
                                                </InputGroup>
                                            </Field>
                                            <Field>
                                                <div className="flex items-center justify-between gap-2">
                                                    <FieldLabel htmlFor="guardian_email">Guardian Email</FieldLabel>
                                                    <BadgeOptional variant="warning-outline" size="sm">
                                                        Optional
                                                    </BadgeOptional>
                                                </div>
                                                <InputGroup>
                                                    <InputGroupInput
                                                        id="guardian_email"
                                                        type="email"
                                                        placeholder="guardian@example.com"
                                                        value={data.guardian_email}
                                                        onChange={(event) => setData("guardian_email", event.target.value)}
                                                    />
                                                    <InputGroupAddon align="inline-end">
                                                        <Mail className="h-4 w-4" />
                                                    </InputGroupAddon>
                                                </InputGroup>
                                            </Field>
                                            <div className="md:col-span-2">
                                                <Field>
                                                    <div className="flex items-center justify-between gap-2">
                                                        <FieldLabel htmlFor="family_address">Family Address</FieldLabel>
                                                        <BadgeOptional variant="warning-outline" size="sm">
                                                            Optional
                                                        </BadgeOptional>
                                                    </div>
                                                    <Textarea
                                                        id="family_address"
                                                        value={data.family_address}
                                                        onChange={(event) => setData("family_address", event.target.value)}
                                                        rows={2}
                                                    />
                                                </Field>
                                            </div>
                                        </div>
                                    </CardContent>
                                )}
                            </Card>
                        </div>

                        {/* Right sidebar: Picture & Signature */}
                        <div id="student-section-assets" className="scroll-mt-36 space-y-6">
                            {flags.studentAvatarUpload && (
                                <Card className="overflow-hidden">
                                    <CardHeader className="pb-3">
                                        <CardTitle className="text-base">Profile Picture</CardTitle>
                                        <p className="text-muted-foreground text-xs">Upload 1x1 photo</p>
                                    </CardHeader>
                                    <CardContent className="flex flex-col items-center gap-4">
                                        <input type="file" ref={pictureInputRef} className="hidden" accept="image/*" onChange={handlePictureChange} />
                                        <div
                                            onDragOver={handlePictureDragOver}
                                            onDragLeave={handlePictureDragLeave}
                                            onDrop={handlePictureDrop}
                                            onClick={() => pictureInputRef.current?.click()}
                                            className={cn(
                                                "group relative flex h-36 w-36 cursor-pointer flex-col items-center justify-center overflow-hidden rounded-full border-2 border-dashed transition-all",
                                                isDragOverPicture
                                                    ? "border-primary bg-primary/10"
                                                    : "border-muted-foreground/25 hover:border-primary/50 hover:bg-muted/50",
                                            )}
                                        >
                                            {profilePreview ? (
                                                <>
                                                    <img src={profilePreview} alt="Profile preview" className="h-full w-full object-cover" />
                                                    <div className="absolute inset-0 flex flex-col items-center justify-center gap-1 bg-black/50 opacity-0 transition-opacity group-hover:opacity-100">
                                                        <Camera className="h-6 w-6 text-white" />
                                                        <span className="text-[10px] font-medium text-white">Change</span>
                                                    </div>
                                                </>
                                            ) : (
                                                <div className="text-muted-foreground flex flex-col items-center gap-2">
                                                    {isDragOverPicture ? (
                                                        <ImageUp className="text-primary h-8 w-8" />
                                                    ) : (
                                                        <Camera className="h-8 w-8" />
                                                    )}
                                                    <span className="text-xs font-medium">{isDragOverPicture ? "Drop here" : "Click or drag"}</span>
                                                </div>
                                            )}
                                        </div>
                                        {profilePreview && (
                                            <div className="flex gap-2">
                                                <Button type="button" variant="outline" size="sm" onClick={() => pictureInputRef.current?.click()}>
                                                    <Camera className="mr-1 h-3.5 w-3.5" />
                                                    Change
                                                </Button>
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={() => downloadPreview(profilePreview, getDownloadFilename("photo"))}
                                                >
                                                    <Download className="mr-1 h-3.5 w-3.5" />
                                                    Download
                                                </Button>
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={clearPicture}
                                                    className="text-destructive text-xs"
                                                >
                                                    Remove
                                                </Button>
                                            </div>
                                        )}
                                    </CardContent>
                                </Card>
                            )}

                            {flags.studentSignaturePad && (
                                <Card className="overflow-hidden">
                                    <CardHeader className="pb-3">
                                        <CardTitle className="text-base">Signature</CardTitle>
                                        <p className="text-muted-foreground text-xs">Draw or upload e-signature</p>
                                    </CardHeader>
                                    <CardContent className="flex flex-col items-center gap-4">
                                        {signaturePreview ? (
                                            <div className="flex flex-col items-center gap-3">
                                                <div className="bg-muted/20 flex h-20 w-full min-w-[180px] items-center justify-center rounded-lg border p-2">
                                                    <img
                                                        src={signaturePreview}
                                                        alt="Signature preview"
                                                        className="h-full max-w-full object-contain dark:invert"
                                                    />
                                                </div>
                                                <div className="flex gap-2">
                                                    <Button type="button" variant="outline" size="sm" onClick={() => setIsSignatureDialogOpen(true)}>
                                                        <PenLine className="mr-1 h-3.5 w-3.5" />
                                                        Change
                                                    </Button>
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        size="sm"
                                                        onClick={() => downloadPreview(signaturePreview!, getDownloadFilename("signature"))}
                                                    >
                                                        <Download className="mr-1 h-3.5 w-3.5" />
                                                        Download
                                                    </Button>
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={clearSignature}
                                                        className="text-destructive text-xs"
                                                    >
                                                        Remove
                                                    </Button>
                                                </div>
                                            </div>
                                        ) : (
                                            <div className="flex flex-col items-center gap-3">
                                                <div className="bg-muted/30 border-muted-foreground/25 flex h-20 w-full max-w-[220px] items-center justify-center rounded-lg border-2 border-dashed">
                                                    <PenLine className="text-muted-foreground/40 h-8 w-8" />
                                                </div>
                                                <Button type="button" variant="outline" size="sm" onClick={() => setIsSignatureDialogOpen(true)}>
                                                    <PenLine className="mr-1 h-3.5 w-3.5" />
                                                    Add Signature
                                                </Button>
                                            </div>
                                        )}
                                    </CardContent>
                                </Card>
                            )}
                            <CreateSignatureDialog
                                open={isSignatureDialogOpen}
                                onOpenChange={setIsSignatureDialogOpen}
                                onSave={handleSignatureSave}
                            />
                        </div>
                    </div>

                    {/* Sticky bottom bar */}
                    <div className="bg-background/95 sticky bottom-0 z-10 -mx-4 -mb-6 border-t px-4 py-3 backdrop-blur-sm sm:-mx-6 sm:px-6">
                        <div className="mx-auto flex max-w-6xl flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div className="min-w-0 space-y-2 text-sm">
                                <div className="flex flex-wrap items-center gap-3">
                                    <span className="text-muted-foreground">
                                        {filledRequired}/{requiredFields.length} required fields complete
                                    </span>
                                    <div className="bg-secondary h-1.5 w-28 rounded-full sm:w-36">
                                        <div
                                            className="bg-primary h-1.5 rounded-full transition-all duration-300"
                                            style={{ width: `${progressPercent}%` }}
                                        />
                                    </div>
                                    <span className="text-xs font-medium tabular-nums">{progressPercent}%</span>
                                    {submitErrorSectionId && (
                                        <button
                                            type="button"
                                            onClick={() => scrollToSection(submitErrorSectionId)}
                                            className="text-destructive inline-flex items-center gap-1 text-xs font-medium hover:underline"
                                        >
                                            <AlertCircle className="h-3.5 w-3.5" />
                                            Fix {sections.find((section) => section.id === submitErrorSectionId)?.label}
                                        </button>
                                    )}
                                </div>
                                {progress && (
                                    <div className="text-muted-foreground flex items-center gap-2 text-xs">
                                        <span className="text-primary font-medium">Uploading attachments</span>
                                        <div className="bg-secondary h-1.5 w-32 rounded-full">
                                            <div
                                                className="bg-primary h-1.5 rounded-full transition-all duration-300"
                                                style={{ width: `${progress.percentage}%` }}
                                            />
                                        </div>
                                        <span className="tabular-nums">{progress.percentage}%</span>
                                    </div>
                                )}
                            </div>
                            <div className="flex flex-wrap items-center gap-2">
                                <span className="text-muted-foreground hidden text-xs lg:inline">Ctrl+Enter to submit</span>
                                <Button type="button" variant="outline" disabled={processing} onClick={saveDraft}>
                                    <Save className="h-4 w-4" />
                                    Save draft
                                </Button>
                                <div className="flex gap-1">
                                    <Button type="button" disabled={processing} onClick={() => submitWithAction("view")}>
                                        {processing ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <Eye className="mr-2 h-4 w-4" />}
                                        {processing ? "Creating..." : "Submit & View"}
                                    </Button>
                                    <DropdownMenu>
                                        <DropdownMenuTrigger
                                            render={
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="icon"
                                                    disabled={processing}
                                                    aria-label="More create actions"
                                                />
                                            }
                                        >
                                            <ChevronDown className="h-4 w-4" />
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent align="end" className="w-64">
                                            <DropdownMenuItem onClick={() => submitWithAction("view")} className="cursor-pointer">
                                                <Eye className="mr-2 h-4 w-4" />
                                                Submit and View the record
                                            </DropdownMenuItem>
                                            <DropdownMenuItem onClick={() => submitWithAction("create_another")} className="cursor-pointer">
                                                <UserPlus className="mr-2 h-4 w-4" />
                                                Submit and create another one
                                            </DropdownMenuItem>
                                            <DropdownMenuSeparator />
                                            <DropdownMenuItem onClick={() => submitWithAction("create_enrollment")} className="cursor-pointer">
                                                <FilePlus2 className="mr-2 h-4 w-4" />
                                                Submit and create an enrollment
                                            </DropdownMenuItem>
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </TooltipProvider>
        </AdminLayout>
    );
}
