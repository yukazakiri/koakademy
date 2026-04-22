import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { Checkbox } from "@/components/ui/checkbox";
import { Combobox, type ComboboxOption } from "@/components/ui/combobox";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { RadioGroup, RadioGroupItem } from "@/components/ui/radio-group";
import { Separator } from "@/components/ui/separator";
import { Textarea } from "@/components/ui/textarea";
import { cn } from "@/lib/utils";
import { Head, router, useForm, usePage } from "@inertiajs/react";
import axios from "axios";
import { AnimatePresence, motion } from "framer-motion";
import {
    ArrowRight,
    BookOpen,
    Calendar,
    Check,
    CheckCircle2,
    ChevronRight,
    Copy,
    FileText,
    GraduationCap,
    Loader2,
    LogIn,
    School,
    Search,
    Sparkles,
    Trash2,
    Upload,
    User,
    UserPlus,
} from "lucide-react";
import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import { toast } from "sonner";
import { z } from "zod";

type Department = {
    code: string;
    label: string;
};

type Course = {
    id: number;
    code: string;
    title: string;
    department: string | null;
    description: string | null;
};

type FlashSubject = {
    code: string;
    title: string;
    lecture_units: number;
    laboratory_units: number;
    is_modular: boolean;
    lecture_fee: number;
    laboratory_fee: number;
};

type FlashTuition = {
    total_lectures: number;
    total_laboratory: number;
    total_tuition: number;
    miscellaneous: number;
    overall: number;
    balance: number;
};

type Flash = {
    success?: string;
    error?: string;
    studentId?: string;
    studentName?: string;
    course?: string;
    courseCode?: string;
    continuing?: boolean;
    schoolYear?: string;
    semester?: number;
    semesterLabel?: string;
    academicYear?: number | null;
    yearLevelLabel?: string | null;
    subjects?: FlashSubject[];
    totalUnits?: number;
    tuition?: FlashTuition | null;
};

type EnrollmentFormData = {
    student_type: "college" | "tesda";
    department: string;
    course_id: string;
    academic_year: string;

    first_name: string;
    middle_name: string;
    last_name: string;
    suffix: string;

    birth_date: string;
    gender: string;
    civil_status: string;
    nationality: string;
    religion: string;

    email: string;
    phone: string;
    address: string;

    contacts: {
        personal_contact: string;
        emergency_contact_name: string;
        emergency_contact_phone: string;
        emergency_contact_relationship: string;
    };

    parents: {
        father_name: string;
        father_contact: string;
        mother_name: string;
        mother_contact: string;
        guardian_name: string;
        guardian_relationship: string;
        guardian_contact: string;
        family_address: string;
    };

    education: {
        elementary_school: string;
        elementary_year_graduated: string;
        high_school: string;
        high_school_year_graduated: string;
        senior_high_school: string;
        senior_high_year_graduated: string;
        vocational_school: string;
        vocational_course: string;
        vocational_year_graduated: string;
    };

    consent: boolean;
};

interface EnrollmentCreateProps {
    departments: Department[];
    courses: Course[];
    flash?: Flash;
    college_enrollment_enabled?: boolean;
    tesda_enrollment_enabled?: boolean;
}

const steps = [
    { id: "program", label: "Program Selection", icon: BookOpen, description: "Choose your path" },
    { id: "personal", label: "Personal Details", icon: User, description: "Tell us about you" },
    {
        id: "contacts",
        label: "Family & Contacts",
        icon: School,
        description: "Emergency details",
    },
    { id: "documents", label: "Documents", icon: FileText, description: "Upload requirements" },
    { id: "review", label: "Review & Submit", icon: Sparkles, description: "Final check" },
] as const;

// Document types for TESDA enrollment
const DOCUMENT_TYPES = [
    { id: "psa_birth_certificate", label: "PSA Birth Certificate", required: true },
    { id: "high_school_diploma", label: "High School Diploma / Form 137", required: true },
    { id: "2x2_photo", label: "2x2 ID Photo", required: true },
    { id: "other", label: "Other Supporting Documents", required: false },
] as const;

type DocumentFile = {
    id: string;
    type: string;
    file: File;
    preview?: string;
};

function toDepartmentLabel(code: string, departments: Department[]): string {
    return departments.find((dept) => dept.code === code)?.label ?? code;
}

function courseLabel(course: Course): string {
    const dept = course.department ? course.department.toUpperCase() : "";
    const pieces = [course.title, course.code ? `(${course.code})` : null, dept ? `• ${dept}` : null].filter(Boolean);
    return pieces.join(" ");
}

// Validation Schemas
const programSchema = z.object({
    student_type: z.enum(["college", "tesda"]),
    course_id: z.string().min(1, "Please select a course"),
});

const personalSchema = z.object({
    first_name: z
        .string()
        .min(1, "First name is required")
        .regex(/^[a-zA-Z\s.-]+$/, "Name must contain only letters"),
    last_name: z
        .string()
        .min(1, "Last name is required")
        .regex(/^[a-zA-Z\s.-]+$/, "Name must contain only letters"),
    birth_date: z.string().min(1, "Birth date is required"),
    gender: z.string().min(1, "Gender is required"),
    nationality: z
        .string()
        .min(1, "Nationality is required")
        .regex(/^[a-zA-Z\s.-]+$/, "Nationality must contain only letters"),
    address: z.string().min(5, "Complete address is required"),
    email: z.string().email("Invalid email address").optional().or(z.literal("")),
    phone: z.string().min(10, "Phone number is required").regex(/^\d+$/, "Phone number must contain only numbers"),
});

const contactsSchema = z.object({
    contacts: z.object({
        emergency_contact_name: z
            .string()
            .min(1, "Emergency contact name is required")
            .regex(/^[a-zA-Z\s.-]+$/, "Name must contain only letters"),
        emergency_contact_phone: z.string().min(1, "Emergency contact phone is required").regex(/^\d+$/, "Phone number must contain only numbers"),
    }),
    parents: z.object({
        guardian_name: z
            .string()
            .min(1, "Guardian name is required")
            .regex(/^[a-zA-Z\s.-]+$/, "Name must contain only letters"),
        guardian_relationship: z
            .string()
            .min(1, "Guardian relationship is required")
            .regex(/^[a-zA-Z\s.-]+$/, "Relationship must contain only letters"),
        guardian_contact: z.string().min(1, "Guardian contact is required").regex(/^\d+$/, "Phone number must contain only numbers"),
    }),
});

// Success state data
type SuccessData = {
    name: string;
    studentId: string;
    course: string;
    courseCode?: string;
    continuing: boolean;
    schoolYear?: string;
    semesterLabel?: string;
    yearLevelLabel?: string;
    subjects?: FlashSubject[];
    totalUnits?: number;
    tuition?: FlashTuition | null;
};

interface Branding {
    appName: string;
    appShortName: string;
    organizationShortName: string;
}

const sanitizeNumberInput = (value: string) => value.replace(/\D/g, "");
const sanitizeNameInput = (value: string) => value.replace(/[^a-zA-Z\s.-]/g, "");

export default function EnrollmentCreate({ departments, courses, flash, college_enrollment_enabled = false, tesda_enrollment_enabled = true }: EnrollmentCreateProps) {
    const { props } = usePage<{ branding?: Branding }>();
    const appName = props.branding?.appName || "School Portal";
    const orgShortName = props.branding?.organizationShortName || "UNI";

    const [currentStep, setCurrentStep] = useState(0);
    const [direction, setDirection] = useState(0);
    const [showSuccess, setShowSuccess] = useState(false);
    const [successData, setSuccessData] = useState<SuccessData | null>(null);
    const [uploadedDocuments, setUploadedDocuments] = useState<DocumentFile[]>([]);
    const [isDragging, setIsDragging] = useState(false);
    const fileInputRef = useRef<HTMLInputElement>(null);

    // Identify / returning-student flow
    type MatchedStudent = {
        id: number;
        student_id: string;
        first_name: string;
        middle_name: string | null;
        last_name: string;
        full_name: string;
        email: string | null;
        student_type: string;
        status: string;
        academic_year: number | null;
        course: { id: number; code: string; title: string; department: string | null } | null;
    };

    const [mode, setMode] = useState<"identify" | "new">("identify");
    const [lookupEmail, setLookupEmail] = useState("");
    const [lookupStudentId, setLookupStudentId] = useState("");
    const [lookupLoading, setLookupLoading] = useState(false);
    const [lookupAttempted, setLookupAttempted] = useState(false);
    const [matchedStudent, setMatchedStudent] = useState<MatchedStudent | null>(null);

    // Continuing-student form state
    type SubjectOption = {
        id: number;
        code: string;
        title: string;
        lecture: number;
        laboratory: number;
        academic_year: number;
        semester: number;
        lec_per_unit: number;
        lab_per_unit: number;
        has_classes: boolean;
    };
    type SelectedSubject = {
        subject_id: number;
        code: string;
        title: string;
        lecture_units: number;
        laboratory_units: number;
        lec_per_unit: number;
        lab_per_unit: number;
        is_modular: boolean;
        lecture_fee: number;
        laboratory_fee: number;
    };
    type CourseInfo = {
        id: number;
        code: string;
        title: string;
        lec_per_unit: number;
        lab_per_unit: number;
        miscellaneous: number;
    };

    const [continuingStep, setContinuingStep] = useState<0 | 1 | 2>(0); // 0=confirm, 1=subjects, 2=review
    const [continuingYear, setContinuingYear] = useState("");
    const [continuingSemester, setContinuingSemester] = useState("");
    const [continuingConsent, setContinuingConsent] = useState(false);
    const [continuingSubmitting, setContinuingSubmitting] = useState(false);
    const [courseInfo, setCourseInfo] = useState<CourseInfo | null>(null);
    const [availableSubjects, setAvailableSubjects] = useState<SubjectOption[]>([]);
    const [selectedSubjects, setSelectedSubjects] = useState<SelectedSubject[]>([]);
    const [subjectSearch, setSubjectSearch] = useState("");
    const [subjectsLoading, setSubjectsLoading] = useState(false);

    const MODULAR_FEE_PER_SUBJECT = 2400;

    // Track which documents the user has available as soft copies
    const [availableDocuments, setAvailableDocuments] = useState<Record<string, boolean>>(
        Object.fromEntries(DOCUMENT_TYPES.map((doc) => [doc.id, false])),
    );

    // Check if user has any documents to upload
    const hasAnyDocumentsToUpload = useMemo(() => {
        return Object.values(availableDocuments).some((v) => v);
    }, [availableDocuments]);

    const { data, setData, post, processing, errors, reset } = useForm<EnrollmentFormData>({
        student_type: tesda_enrollment_enabled ? "tesda" : "college",
        department: "TESDA",
        course_id: "",
        academic_year: "",

        first_name: "",
        middle_name: "",
        last_name: "",
        suffix: "",

        birth_date: "",
        gender: "",
        civil_status: "",
        nationality: "Filipino",
        religion: "",

        email: "",
        phone: "",
        address: "",

        contacts: {
            personal_contact: "",
            emergency_contact_name: "",
            emergency_contact_phone: "",
            emergency_contact_relationship: "",
        },

        parents: {
            father_name: "",
            father_contact: "",
            mother_name: "",
            mother_contact: "",
            guardian_name: "",
            guardian_relationship: "",
            guardian_contact: "",
            family_address: "",
        },

        education: {
            elementary_school: "",
            elementary_year_graduated: "",
            high_school: "",
            high_school_year_graduated: "",
            senior_high_school: "",
            senior_high_year_graduated: "",
            vocational_school: "",
            vocational_course: "",
            vocational_year_graduated: "",
        },

        consent: false,
    });

    const errorsBag = errors as Record<string, string>;

    useEffect(() => {
        if (flash?.success) {
            // Prefer structured flash data (provided by both new + continuing endpoints),
            // fall back to regex parsing on the message for older payloads.
            const match = flash.success.match(/(?:Applicant|Student)\s+ID:\s*(\d+)/i);
            const fallbackId = match ? match[1] : "Pending";

            const formCourse = courses.find((c) => String(c.id) === data.course_id);
            const derivedName =
                flash.studentName ||
                (data.first_name || data.last_name ? `${data.first_name} ${data.last_name}`.trim() : "") ||
                "Student";

            setSuccessData({
                name: derivedName,
                studentId: flash.studentId ?? fallbackId,
                course: flash.course ?? formCourse?.title ?? "Program pending",
                courseCode: flash.courseCode,
                continuing: Boolean(flash.continuing),
                schoolYear: flash.schoolYear,
                semesterLabel: flash.semesterLabel,
                yearLevelLabel: flash.yearLevelLabel ?? undefined,
                subjects: flash.subjects,
                totalUnits: flash.totalUnits,
                tuition: flash.tuition,
            });
            setShowSuccess(true);
            toast.success(
                flash.continuing ? "Re-enrollment submitted successfully!" : "Registration submitted successfully!",
            );
        }

        if (flash?.error) {
            toast.error(flash.error);
        }
    }, [
        flash?.error,
        flash?.success,
        flash?.studentId,
        flash?.studentName,
        flash?.course,
        flash?.continuing,
        courses,
        data.course_id,
        data.first_name,
        data.last_name,
    ]);

    // Reset dependent fields when student type changes
    useEffect(() => {
        if (data.student_type === "tesda") {
            setData("department", "TESDA");
            setData("academic_year", "");
        } else if (data.department === "TESDA") {
            setData("department", "");
        }
        setData("course_id", "");
    }, [data.student_type]);

    const availableDepartments = useMemo(() => {
        if (data.student_type === "tesda") {
            return departments.filter((dept) => dept.code === "TESDA");
        }
        return departments.filter((dept) => dept.code !== "TESDA");
    }, [data.student_type, departments]);

    const availableCourses = useMemo(() => {
        const normalizedDepartment = data.department.trim().toUpperCase();
        return courses.filter((course) => {
            const courseDepartment = (course.department ?? "").trim().toUpperCase();
            if (data.student_type === "tesda") {
                return courseDepartment === "TESDA";
            }
            // For college: show all non-TESDA courses if no department selected, otherwise filter by department
            if (normalizedDepartment.length === 0) {
                return courseDepartment !== "TESDA";
            }
            return courseDepartment === normalizedDepartment;
        });
    }, [courses, data.department, data.student_type]);

    const courseOptions: ComboboxOption[] = useMemo(() => {
        return availableCourses.map((course) => ({
            value: String(course.id),
            label: courseLabel(course),
            description: course.description ?? undefined,
            searchText: [course.title, course.code, course.department].filter(Boolean).join(" "),
        }));
    }, [availableCourses]);

    const selectedCourse = useMemo(() => {
        const id = Number(data.course_id);
        if (!id) return null;
        return courses.find((course) => course.id === id) ?? null;
    }, [courses, data.course_id]);

    const validateStep = (step: number) => {
        try {
            if (step === 0) {
                programSchema.parse({ student_type: data.student_type, course_id: data.course_id });
            } else if (step === 1) {
                personalSchema.parse(data);
            } else if (step === 2) {
                contactsSchema.parse({ contacts: data.contacts, parents: data.parents });
            }
            return true;
        } catch (error) {
            if (error instanceof z.ZodError) {
                error.issues.forEach((issue) => {
                    toast.error(issue.message);
                });
            }
            return false;
        }
    };

    const goToStep = (stepIndex: number) => {
        if (stepIndex > currentStep) {
            // Validate current and intermediate steps
            for (let i = currentStep; i < stepIndex; i++) {
                if (!validateStep(i)) return;
            }
        }
        setDirection(stepIndex > currentStep ? 1 : -1);
        setCurrentStep(stepIndex);
    };

    const handleNext = () => {
        if (validateStep(currentStep)) {
            if (currentStep < steps.length - 1) {
                goToStep(currentStep + 1);
            }
        }
    };

    const handlePrev = () => {
        if (currentStep > 0) {
            goToStep(currentStep - 1);
        }
    };

    const submit = () => {
        if (!data.consent) {
            toast.error("Please agree to the data privacy notice.");
            return;
        }

        // Build FormData to include files
        const formData = new FormData();

        // Add all form data
        formData.append("student_type", data.student_type);
        formData.append("department", data.department);
        formData.append("course_id", data.course_id);
        formData.append("academic_year", data.academic_year);
        formData.append("first_name", data.first_name);
        formData.append("middle_name", data.middle_name);
        formData.append("last_name", data.last_name);
        formData.append("suffix", data.suffix);
        formData.append("birth_date", data.birth_date);
        formData.append("gender", data.gender);
        formData.append("civil_status", data.civil_status);
        formData.append("nationality", data.nationality);
        formData.append("religion", data.religion);
        formData.append("email", data.email);
        formData.append("phone", data.phone);
        formData.append("address", data.address);
        formData.append("consent", data.consent ? "1" : "0");

        // Add nested objects
        Object.entries(data.contacts).forEach(([key, value]) => {
            formData.append(`contacts[${key}]`, value);
        });
        Object.entries(data.parents).forEach(([key, value]) => {
            formData.append(`parents[${key}]`, value);
        });
        Object.entries(data.education).forEach(([key, value]) => {
            formData.append(`education[${key}]`, value);
        });

        // Add document files
        uploadedDocuments.forEach((doc, index) => {
            formData.append(`documents[${index}][type]`, doc.type);
            formData.append(`documents[${index}][file]`, doc.file);
        });

        router.post("/enrollment", formData, {
            preserveScroll: true,
            forceFormData: true,
            onError: (errors) => {
                const errorMessages = Object.values(errors);
                if (errorMessages.length > 0) {
                    toast.error(errorMessages[0] as string);
                } else {
                    toast.error("Please review the errors and try again.");
                }
            },
        });
    };

    const handleStartNewRegistration = () => {
        reset();
        setShowSuccess(false);
        setSuccessData(null);
        setCurrentStep(0);
        setUploadedDocuments([]);
        setAvailableDocuments(Object.fromEntries(DOCUMENT_TYPES.map((doc) => [doc.id, false])));
        setMode("identify");
        setMatchedStudent(null);
        setLookupAttempted(false);
        setLookupEmail("");
        setLookupStudentId("");
        setContinuingYear("");
        setContinuingSemester("");
        setContinuingConsent(false);
        setContinuingStep(0);
        setCourseInfo(null);
        setAvailableSubjects([]);
        setSelectedSubjects([]);
        setSubjectSearch("");
    };

    const copyToClipboard = (text: string) => {
        navigator.clipboard.writeText(text);
        toast.success("Copied to clipboard!");
    };

    // Document upload handlers
    const handleFileSelect = useCallback((files: FileList | null, documentType: string) => {
        if (!files) return;

        const MAX_FILE_SIZE = 10 * 1024 * 1024; // 10MB per file
        const ALLOWED_TYPES = ["image/jpeg", "image/png", "image/webp", "application/pdf"];

        Array.from(files).forEach((file) => {
            if (file.size > MAX_FILE_SIZE) {
                toast.error(`${file.name} is too large. Maximum size is 10MB.`);
                return;
            }

            if (!ALLOWED_TYPES.includes(file.type)) {
                toast.error(`${file.name} is not a valid file type. Please upload images (JPG, PNG, WebP) or PDF files.`);
                return;
            }

            const docFile: DocumentFile = {
                id: `${documentType}-${Date.now()}-${Math.random().toString(36).slice(2, 9)}`,
                type: documentType,
                file,
            };

            // Generate preview for images
            if (file.type.startsWith("image/")) {
                const reader = new FileReader();
                reader.onload = (e) => {
                    setUploadedDocuments((prev) =>
                        prev.map((doc) => (doc.id === docFile.id ? { ...doc, preview: e.target?.result as string } : doc)),
                    );
                };
                reader.readAsDataURL(file);
            }

            setUploadedDocuments((prev) => [...prev, docFile]);
            toast.success(`${file.name} added successfully!`);
        });
    }, []);

    const handleRemoveDocument = useCallback((docId: string) => {
        setUploadedDocuments((prev) => prev.filter((doc) => doc.id !== docId));
    }, []);

    const handleDragOver = useCallback((e: React.DragEvent) => {
        e.preventDefault();
        setIsDragging(true);
    }, []);

    const handleDragLeave = useCallback((e: React.DragEvent) => {
        e.preventDefault();
        setIsDragging(false);
    }, []);

    const handleDrop = useCallback(
        (e: React.DragEvent, documentType: string) => {
            e.preventDefault();
            setIsDragging(false);
            handleFileSelect(e.dataTransfer.files, documentType);
        },
        [handleFileSelect],
    );

    const getDocumentsForType = useCallback(
        (type: string) => {
            return uploadedDocuments.filter((doc) => doc.type === type);
        },
        [uploadedDocuments],
    );

    const variants = {
        enter: (direction: number) => ({
            x: direction > 0 ? 50 : -50,
            opacity: 0,
        }),
        center: {
            x: 0,
            opacity: 1,
        },
        exit: (direction: number) => ({
            x: direction < 0 ? 50 : -50,
            opacity: 0,
        }),
    };

    // --- Identify / returning-student handlers ---
    const handleLookup = async () => {
        if (!lookupEmail.trim() || !lookupStudentId.trim()) {
            toast.error("Please enter both your email and Student ID.");
            return;
        }
        setLookupLoading(true);
        try {
            const { data: payload } = await axios.post<{ matched: boolean; student?: MatchedStudent }>(
                "/enrollment/lookup",
                { email: lookupEmail.trim(), student_id: lookupStudentId.trim() },
            );

            setLookupAttempted(true);

            if (payload.matched && payload.student) {
                setMatchedStudent(payload.student);

                // Auto-advance to the next year level (capped at 4).
                // e.g. previous term = 1st year → default the re-enrollment to 2nd year.
                const prevYear = payload.student.academic_year ?? 0;
                const nextYear = prevYear > 0 ? Math.min(prevYear + 1, 4) : 0;
                setContinuingYear(nextYear > 0 ? String(nextYear) : "");
                setContinuingSemester("");
                setContinuingStep(0);
                setSelectedSubjects([]);
                setSubjectSearch("");
                setAvailableSubjects([]);
                setCourseInfo(null);
                // Fire-and-forget: pre-load the course + subject list for step 2.
                void fetchContinuingSubjects(lookupEmail.trim(), lookupStudentId.trim());
                toast.success(`Welcome back, ${payload.student.first_name}!`);
            } else {
                setMatchedStudent(null);
                toast.info("We couldn't find a matching record. You can register as a new applicant below.");
            }
        } catch (error) {
            toast.error("We couldn't verify your record right now. Please try again.");
        } finally {
            setLookupLoading(false);
        }
    };

    const handleResetLookup = () => {
        setMatchedStudent(null);
        setLookupAttempted(false);
        setContinuingYear("");
        setContinuingSemester("");
        setContinuingConsent(false);
        setContinuingStep(0);
        setCourseInfo(null);
        setAvailableSubjects([]);
        setSelectedSubjects([]);
        setSubjectSearch("");
    };

    const fetchContinuingSubjects = async (email: string, studentId: string) => {
        setSubjectsLoading(true);
        try {
            const { data: payload } = await axios.post<{ course: CourseInfo; subjects: SubjectOption[] }>(
                "/enrollment/subjects",
                { email, student_id: studentId },
            );
            setCourseInfo(payload.course);
            setAvailableSubjects(payload.subjects ?? []);
        } catch (error) {
            toast.error("Could not load subjects for your course. Please try again.");
        } finally {
            setSubjectsLoading(false);
        }
    };

    // Mirrors admin calculateFees: NSTP lecture is halved, modular halves lab fees.
    const calcSubjectFees = (subject: SubjectOption, isModular: boolean): { lecture_fee: number; laboratory_fee: number } => {
        const isNSTP = subject.code.toUpperCase().includes("NSTP");
        const totalUnits = subject.lecture + subject.laboratory;
        let lectureFee = subject.lecture ? totalUnits * subject.lec_per_unit : 0;
        if (isNSTP) lectureFee *= 0.5;
        let laboratoryFee = subject.laboratory ? 1 * subject.lab_per_unit : 0;
        if (isModular && subject.laboratory) laboratoryFee = laboratoryFee / 2;
        return {
            lecture_fee: Math.round(lectureFee * 100) / 100,
            laboratory_fee: Math.round(laboratoryFee * 100) / 100,
        };
    };

    const toggleContinuingSubject = (subject: SubjectOption) => {
        setSelectedSubjects((prev) => {
            const existingIndex = prev.findIndex((s) => s.subject_id === subject.id);
            if (existingIndex >= 0) {
                return prev.filter((s) => s.subject_id !== subject.id);
            }
            const fees = calcSubjectFees(subject, false);
            return [
                ...prev,
                {
                    subject_id: subject.id,
                    code: subject.code,
                    title: subject.title,
                    lecture_units: subject.lecture,
                    laboratory_units: subject.laboratory,
                    lec_per_unit: subject.lec_per_unit,
                    lab_per_unit: subject.lab_per_unit,
                    is_modular: false,
                    lecture_fee: fees.lecture_fee,
                    laboratory_fee: fees.laboratory_fee,
                },
            ];
        });
    };

    const toggleContinuingModular = (subjectId: number, isModular: boolean) => {
        setSelectedSubjects((prev) =>
            prev.map((s) => {
                if (s.subject_id !== subjectId) return s;
                const subjectOption = availableSubjects.find((o) => o.id === subjectId);
                if (!subjectOption) return { ...s, is_modular: isModular };
                const fees = calcSubjectFees(subjectOption, isModular);
                return { ...s, is_modular: isModular, lecture_fee: fees.lecture_fee, laboratory_fee: fees.laboratory_fee };
            }),
        );
    };

    const filteredContinuingSubjects = useMemo(() => {
        if (!subjectSearch.trim()) return availableSubjects;
        const q = subjectSearch.toLowerCase();
        return availableSubjects.filter((s) => s.code.toLowerCase().includes(q) || s.title.toLowerCase().includes(q));
    }, [availableSubjects, subjectSearch]);

    const continuingTotals = useMemo(() => {
        const totalLectures = selectedSubjects.reduce((sum, s) => sum + Number(s.lecture_fee || 0), 0);
        const totalLaboratory = selectedSubjects.reduce((sum, s) => sum + Number(s.laboratory_fee || 0), 0);
        const modularCount = selectedSubjects.filter((s) => s.is_modular).length;
        const totalModularFee = modularCount * MODULAR_FEE_PER_SUBJECT;
        const totalTuition = totalLectures + totalLaboratory;
        const miscellaneous = Number(courseInfo?.miscellaneous ?? 3500) || 0;
        const overallTotal = totalTuition + miscellaneous + totalModularFee;
        const totalLectureUnits = selectedSubjects.reduce((sum, s) => sum + Number(s.lecture_units || 0), 0);
        const totalLabUnits = selectedSubjects.reduce((sum, s) => sum + Number(s.laboratory_units || 0), 0);
        return {
            subjectsCount: selectedSubjects.length,
            totalLectureUnits,
            totalLabUnits,
            totalUnits: totalLectureUnits + totalLabUnits,
            totalLectures,
            totalLaboratory,
            modularCount,
            totalModularFee,
            totalTuition,
            miscellaneous,
            overallTotal,
        };
    }, [selectedSubjects, courseInfo]);

    const handleContinueAsNewApplicant = () => {
        if (lookupEmail && !data.email) {
            setData("email", lookupEmail);
        }
        setMode("new");
    };

    const handleSubmitContinuing = () => {
        if (!matchedStudent) return;
        if (matchedStudent.student_type !== "tesda" && !continuingYear) {
            toast.error("Please select your year level.");
            return;
        }
        if (selectedSubjects.length === 0) {
            toast.error("Please select at least one subject to enroll in.");
            return;
        }
        if (!continuingConsent) {
            toast.error("Please agree to the data privacy notice.");
            return;
        }

        setContinuingSubmitting(true);
        router.post(
            "/enrollment/continuing",
            {
                email: lookupEmail.trim(),
                student_id: lookupStudentId.trim(),
                academic_year: continuingYear || "1",
                semester: continuingSemester || undefined,
                subjects: selectedSubjects.map((s) => ({
                    subject_id: s.subject_id,
                    is_modular: s.is_modular,
                    lecture_fee: s.lecture_fee,
                    laboratory_fee: s.laboratory_fee,
                    enrolled_lecture_units: s.lecture_units,
                    enrolled_laboratory_units: s.laboratory_units,
                })),
                consent: true,
            },
            {
                preserveScroll: true,
                onError: (errs) => {
                    const first = Object.values(errs)[0];
                    if (typeof first === "string") {
                        toast.error(first);
                    } else {
                        toast.error("Please review the errors and try again.");
                    }
                },
                onFinish: () => setContinuingSubmitting(false),
            },
        );
    };

    const formatPhp = (amount: number) =>
        new Intl.NumberFormat("en-PH", {
            style: "currency",
            currency: "PHP",
            minimumFractionDigits: 0,
            maximumFractionDigits: 0,
        }).format(amount);

    const identifyView = (
        <>
            {/* Compact header */}
            <header className="bg-background/80 supports-[backdrop-filter]:bg-background/60 sticky top-0 z-40 w-full border-b backdrop-blur-md">
                <div className="container mx-auto flex h-16 max-w-3xl items-center justify-between px-4">
                    <div className="flex items-center gap-3">
                        <div className="bg-primary text-primary-foreground flex h-9 w-9 items-center justify-center rounded-lg shadow-sm">
                            <GraduationCap className="h-5 w-5" />
                        </div>
                        <div>
                            <h1 className="text-sm leading-none font-bold sm:text-base">{orgShortName} Enrollment</h1>
                            <p className="text-muted-foreground mt-0.5 text-[10px] sm:text-xs">Online Registration Portal</p>
                        </div>
                    </div>
                    <Button variant="ghost" size="sm" asChild>
                        <a href="/login" className="gap-1.5">
                            <LogIn className="h-4 w-4" />
                            <span className="hidden sm:inline">Sign in</span>
                        </a>
                    </Button>
                </div>
            </header>

            <main className="container mx-auto max-w-3xl flex-1 p-4 pb-20 sm:p-6 lg:p-8">
                {!matchedStudent ? (
                    <motion.div initial={{ opacity: 0, y: 12 }} animate={{ opacity: 1, y: 0 }} className="space-y-6">
                        {/* Hero */}
                        <div className="text-center">
                            <div className="bg-primary/10 text-primary mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl">
                                <GraduationCap className="h-7 w-7" />
                            </div>
                            <h2 className="text-foreground text-2xl font-bold sm:text-3xl">Welcome to {appName}</h2>
                            <p className="text-muted-foreground mx-auto mt-2 max-w-xl text-sm sm:text-base">
                                Let&apos;s get you started. If you&apos;re a returning student, we&apos;ll pull up your
                                record. If you&apos;re new, we&apos;ll guide you through a quick registration.
                            </p>
                        </div>

                        {/* Lookup card */}
                        <Card className="border-2 shadow-sm">
                            <CardContent className="space-y-5 p-6 sm:p-8">
                                <div className="flex items-start gap-3">
                                    <div className="bg-primary/10 text-primary flex h-10 w-10 shrink-0 items-center justify-center rounded-lg">
                                        <Search className="h-5 w-5" />
                                    </div>
                                    <div>
                                        <h3 className="text-foreground text-lg font-semibold">Continuing / Returning student?</h3>
                                        <p className="text-muted-foreground mt-1 text-sm">
                                            Enter the email and Student ID on file to enroll for the current term without
                                            re-entering your personal details.
                                        </p>
                                    </div>
                                </div>

                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="space-y-2">
                                        <Label htmlFor="lookup-email">School Email</Label>
                                        <Input
                                            id="lookup-email"
                                            type="email"
                                            placeholder="you@example.com"
                                            value={lookupEmail}
                                            autoComplete="email"
                                            onChange={(e) => setLookupEmail(e.target.value)}
                                            onKeyDown={(e) => {
                                                if (e.key === "Enter") handleLookup();
                                            }}
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="lookup-student-id">Student ID</Label>
                                        <Input
                                            id="lookup-student-id"
                                            inputMode="numeric"
                                            placeholder="e.g. 200123"
                                            value={lookupStudentId}
                                            onChange={(e) => setLookupStudentId(sanitizeNumberInput(e.target.value))}
                                            onKeyDown={(e) => {
                                                if (e.key === "Enter") handleLookup();
                                            }}
                                        />
                                    </div>
                                </div>

                                <Button onClick={handleLookup} disabled={lookupLoading} size="lg" className="w-full gap-2">
                                    {lookupLoading ? (
                                        <>
                                            <Loader2 className="h-4 w-4 animate-spin" /> Verifying&hellip;
                                        </>
                                    ) : (
                                        <>
                                            <Search className="h-4 w-4" /> Find my record
                                        </>
                                    )}
                                </Button>

                                {lookupAttempted && !matchedStudent && (
                                    <div className="border-border/60 bg-muted/40 text-muted-foreground flex gap-2.5 rounded-lg border p-3 text-sm">
                                        <Sparkles className="text-primary/70 mt-0.5 h-4 w-4 shrink-0" />
                                        <span>
                                            We couldn&apos;t find a student record matching that email + Student ID. If
                                            you&apos;re new, continue as a new applicant below.
                                        </span>
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        {/* Divider */}
                        <div className="relative my-2">
                            <div className="absolute inset-0 flex items-center">
                                <Separator />
                            </div>
                            <div className="relative flex justify-center">
                                <span className="bg-background text-muted-foreground px-3 text-xs font-medium tracking-wider uppercase">
                                    or
                                </span>
                            </div>
                        </div>

                        {/* New applicant card */}
                        <Card
                            role="button"
                            tabIndex={0}
                            onClick={handleContinueAsNewApplicant}
                            onKeyDown={(e) => {
                                if (e.key === "Enter" || e.key === " ") {
                                    e.preventDefault();
                                    handleContinueAsNewApplicant();
                                }
                            }}
                            className="hover:border-primary/60 hover:bg-primary/5 cursor-pointer border-2 transition-colors"
                        >
                            <CardContent className="flex items-center gap-4 p-6">
                                <div className="bg-primary/10 text-primary flex h-12 w-12 shrink-0 items-center justify-center rounded-lg">
                                    <UserPlus className="h-6 w-6" />
                                </div>
                                <div className="flex-1">
                                    <h3 className="text-foreground text-lg font-semibold">I&apos;m a new applicant</h3>
                                    <p className="text-muted-foreground mt-1 text-sm">
                                        First time applying? Register here &mdash; we&apos;ll walk you through a short
                                        5-step form.
                                    </p>
                                </div>
                                <ArrowRight className="text-muted-foreground h-5 w-5" />
                            </CardContent>
                        </Card>

                        <p className="text-muted-foreground text-center text-xs">
                            Already have a portal account?{" "}
                            <a href="/login" className="text-primary hover:underline">
                                Sign in
                            </a>{" "}
                            to re-enroll from your dashboard.
                        </p>
                    </motion.div>
                ) : (
                    /* =========== Matched / Continuing-student form =========== */
                    <motion.div initial={{ opacity: 0, y: 12 }} animate={{ opacity: 1, y: 0 }} className="space-y-6">
                        <Card className="border-2 shadow-sm">
                            <CardContent className="p-6 sm:p-8">
                                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                    <div className="flex items-start gap-3">
                                        <div className="bg-primary/10 text-primary flex h-12 w-12 shrink-0 items-center justify-center rounded-full">
                                            <CheckCircle2 className="h-6 w-6" />
                                        </div>
                                        <div>
                                            <p className="text-muted-foreground text-xs font-medium tracking-wider uppercase">
                                                Welcome back
                                            </p>
                                            <h3 className="text-foreground text-xl font-bold sm:text-2xl">
                                                {matchedStudent.full_name}
                                            </h3>
                                            <div className="mt-2 flex flex-wrap gap-2">
                                                <Badge variant="secondary" className="font-mono">
                                                    ID&nbsp;{matchedStudent.student_id}
                                                </Badge>
                                                <Badge variant="outline">
                                                    {matchedStudent.student_type === "tesda" ? "TESDA" : "College"}
                                                </Badge>
                                                {matchedStudent.status && (
                                                    <Badge variant="outline" className="capitalize">
                                                        {matchedStudent.status.replace(/_/g, " ")}
                                                    </Badge>
                                                )}
                                            </div>
                                        </div>
                                    </div>
                                    <Button variant="ghost" size="sm" onClick={handleResetLookup}>
                                        Not you?
                                    </Button>
                                </div>

                                {matchedStudent.course && (
                                    <div className="bg-muted/40 mt-5 rounded-lg border p-4 text-sm">
                                        <p className="text-muted-foreground text-xs font-medium tracking-wider uppercase">
                                            Current program on file
                                        </p>
                                        <p className="text-foreground mt-1 font-semibold">
                                            {matchedStudent.course.title}{" "}
                                            <span className="text-muted-foreground font-normal">
                                                ({matchedStudent.course.code})
                                            </span>
                                        </p>
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        {/* Step indicator */}
                        <div className="flex items-center justify-center gap-2 text-xs sm:text-sm">
                            {[
                                { label: "Program", icon: GraduationCap },
                                { label: "Subjects", icon: BookOpen },
                                { label: "Review", icon: FileText },
                            ].map((s, idx) => {
                                const Icon = s.icon;
                                const active = continuingStep === idx;
                                const done = continuingStep > idx;
                                return (
                                    <div key={s.label} className="flex items-center gap-2">
                                        <div
                                            className={cn(
                                                "flex items-center gap-1.5 rounded-full border px-2.5 py-1 transition-colors sm:px-3",
                                                active && "bg-primary text-primary-foreground border-primary",
                                                done && "bg-primary/10 text-primary border-primary/30",
                                                !active && !done && "text-muted-foreground",
                                            )}
                                        >
                                            {done ? <Check className="h-3.5 w-3.5" /> : <Icon className="h-3.5 w-3.5" />}
                                            <span className="hidden font-medium sm:inline">{s.label}</span>
                                            <span className="font-medium sm:hidden">{idx + 1}</span>
                                        </div>
                                        {idx < 2 && <ChevronRight className="text-muted-foreground/50 h-3.5 w-3.5" />}
                                    </div>
                                );
                            })}
                        </div>

                        {/* === Step 0: Confirm program + year + semester === */}
                        {continuingStep === 0 && (
                            <Card className="border-2 shadow-sm">
                                <CardContent className="space-y-5 p-6 sm:p-8">
                                    <div>
                                        <h3 className="text-foreground text-lg font-semibold">Confirm your program</h3>
                                        <p className="text-muted-foreground mt-1 text-sm">
                                            Your course is locked to your student record. Review your year level and
                                            semester, then continue to pick subjects.
                                        </p>
                                    </div>

                                    {/* Locked course display */}
                                    <div className="bg-muted/40 flex items-start gap-3 rounded-lg border p-4">
                                        <div className="bg-primary/10 text-primary flex h-10 w-10 shrink-0 items-center justify-center rounded-lg">
                                            <GraduationCap className="h-5 w-5" />
                                        </div>
                                        <div className="flex-1">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <p className="text-muted-foreground text-xs font-medium tracking-wider uppercase">
                                                    Course / Program
                                                </p>
                                                <span className="bg-primary/10 text-primary inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-medium">
                                                    <CheckCircle2 className="h-3 w-3" /> Locked
                                                </span>
                                            </div>
                                            <p className="text-foreground mt-0.5 font-semibold">
                                                {matchedStudent.course?.title ?? courseInfo?.title ?? "Your program"}
                                                {(matchedStudent.course?.code ?? courseInfo?.code) && (
                                                    <span className="text-muted-foreground font-normal">
                                                        {" "}
                                                        ({matchedStudent.course?.code ?? courseInfo?.code})
                                                    </span>
                                                )}
                                            </p>
                                            <p className="text-muted-foreground mt-1 text-xs">
                                                Need to transfer programs? Contact the registrar after submission.
                                            </p>
                                        </div>
                                    </div>

                                    {matchedStudent.student_type !== "tesda" && (
                                        <div className="space-y-2">
                                            <div className="flex flex-wrap items-center justify-between gap-2">
                                                <Label>Year Level</Label>
                                                {matchedStudent.academic_year &&
                                                    continuingYear ===
                                                        String(Math.min(matchedStudent.academic_year + 1, 4)) && (
                                                        <span className="bg-primary/10 text-primary inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-medium">
                                                            <ArrowRight className="h-3 w-3" /> Advanced from{" "}
                                                            {matchedStudent.academic_year === 1
                                                                ? "1st"
                                                                : matchedStudent.academic_year === 2
                                                                  ? "2nd"
                                                                  : matchedStudent.academic_year === 3
                                                                    ? "3rd"
                                                                    : `${matchedStudent.academic_year}th`}{" "}
                                                            year
                                                        </span>
                                                    )}
                                            </div>
                                            <RadioGroup
                                                value={continuingYear}
                                                onValueChange={setContinuingYear}
                                                className="grid grid-cols-2 gap-2 sm:grid-cols-4"
                                            >
                                                {["1", "2", "3", "4"].map((yr) => (
                                                    <Label
                                                        key={yr}
                                                        htmlFor={`cont-yr-${yr}`}
                                                        className={cn(
                                                            "hover:border-primary/60 flex cursor-pointer items-center justify-center gap-2 rounded-lg border-2 p-3 text-sm font-medium transition-colors",
                                                            continuingYear === yr && "border-primary bg-primary/5",
                                                        )}
                                                    >
                                                        <RadioGroupItem id={`cont-yr-${yr}`} value={yr} className="sr-only" />
                                                        {yr === "1" ? "1st" : yr === "2" ? "2nd" : yr === "3" ? "3rd" : "4th"}&nbsp;Year
                                                    </Label>
                                                ))}
                                            </RadioGroup>
                                        </div>
                                    )}

                                    <div className="space-y-2">
                                        <Label>Semester (optional)</Label>
                                        <RadioGroup
                                            value={continuingSemester}
                                            onValueChange={setContinuingSemester}
                                            className="grid grid-cols-1 gap-2 sm:grid-cols-3"
                                        >
                                            {[
                                                { value: "", label: "Use current" },
                                                { value: "1", label: "1st Semester" },
                                                { value: "2", label: "2nd Semester" },
                                            ].map((opt) => (
                                                <Label
                                                    key={opt.label}
                                                    htmlFor={`cont-sem-${opt.value || "current"}`}
                                                    className={cn(
                                                        "hover:border-primary/60 flex cursor-pointer items-center justify-center gap-2 rounded-lg border-2 p-3 text-sm font-medium transition-colors",
                                                        continuingSemester === opt.value && "border-primary bg-primary/5",
                                                    )}
                                                >
                                                    <RadioGroupItem
                                                        id={`cont-sem-${opt.value || "current"}`}
                                                        value={opt.value}
                                                        className="sr-only"
                                                    />
                                                    {opt.label}
                                                </Label>
                                            ))}
                                        </RadioGroup>
                                    </div>

                                    <div className="flex flex-col gap-3 sm:flex-row">
                                        <Button variant="outline" onClick={handleResetLookup} className="sm:flex-1">
                                            Back
                                        </Button>
                                        <Button
                                            onClick={() => {
                                                if (matchedStudent.student_type !== "tesda" && !continuingYear) {
                                                    toast.error("Please select your year level.");
                                                    return;
                                                }
                                                setContinuingStep(1);
                                            }}
                                            size="lg"
                                            className="gap-2 sm:flex-[2]"
                                        >
                                            Pick subjects <ChevronRight className="h-4 w-4" />
                                        </Button>
                                    </div>
                                </CardContent>
                            </Card>
                        )}

                        {/* === Step 1: Subject selection === */}
                        {continuingStep === 1 && (
                            <Card className="border-2 shadow-sm">
                                <CardContent className="space-y-5 p-6 sm:p-8">
                                    <div>
                                        <h3 className="text-foreground text-lg font-semibold">Select your subjects</h3>
                                        <p className="text-muted-foreground mt-1 text-sm">
                                            Tap a subject to add it. Toggle <strong>Modular</strong> for self-paced
                                            subjects (flat&nbsp;{formatPhp(MODULAR_FEE_PER_SUBJECT)} each).
                                        </p>
                                    </div>

                                    {/* Search */}
                                    <div className="relative">
                                        <Search className="text-muted-foreground absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2" />
                                        <Input
                                            value={subjectSearch}
                                            onChange={(e) => setSubjectSearch(e.target.value)}
                                            placeholder="Search by code or title..."
                                            className="pl-9"
                                        />
                                    </div>

                                    {/* Subject list */}
                                    <div className="max-h-[420px] space-y-2 overflow-y-auto pr-1 sm:max-h-[520px]">
                                        {subjectsLoading ? (
                                            <div className="text-muted-foreground flex items-center justify-center gap-2 py-10 text-sm">
                                                <Loader2 className="h-4 w-4 animate-spin" /> Loading subjects&hellip;
                                            </div>
                                        ) : filteredContinuingSubjects.length === 0 ? (
                                            <div className="text-muted-foreground flex flex-col items-center justify-center gap-2 py-10 text-sm">
                                                <BookOpen className="h-8 w-8 opacity-40" />
                                                <p>No subjects available for this course right now.</p>
                                            </div>
                                        ) : (
                                            filteredContinuingSubjects.map((subject) => {
                                                const selected = selectedSubjects.find((s) => s.subject_id === subject.id);
                                                const isSelected = Boolean(selected);
                                                return (
                                                    <div
                                                        key={subject.id}
                                                        className={cn(
                                                            "group flex flex-col gap-3 rounded-lg border p-3 transition-colors sm:flex-row sm:items-start",
                                                            isSelected
                                                                ? "border-primary bg-primary/5"
                                                                : "hover:border-primary/40 hover:bg-muted/30",
                                                        )}
                                                    >
                                                        <button
                                                            type="button"
                                                            onClick={() => toggleContinuingSubject(subject)}
                                                            className="flex flex-1 items-start gap-3 text-left"
                                                        >
                                                            <Checkbox
                                                                checked={isSelected}
                                                                className="mt-0.5 pointer-events-none"
                                                                aria-hidden
                                                            />
                                                            <div className="flex-1">
                                                                <div className="flex flex-wrap items-center gap-2">
                                                                    <span className="text-foreground font-mono text-xs font-semibold">
                                                                        {subject.code}
                                                                    </span>
                                                                    {subject.has_classes && (
                                                                        <Badge
                                                                            variant="secondary"
                                                                            className="h-5 gap-1 px-1.5 text-[10px]"
                                                                        >
                                                                            <CheckCircle2 className="h-3 w-3" /> Classes open
                                                                        </Badge>
                                                                    )}
                                                                    {subject.academic_year > 0 && (
                                                                        <Badge variant="outline" className="h-5 px-1.5 text-[10px]">
                                                                            Yr {subject.academic_year} • Sem {subject.semester}
                                                                        </Badge>
                                                                    )}
                                                                </div>
                                                                <p className="text-foreground mt-1 text-sm font-medium">
                                                                    {subject.title}
                                                                </p>
                                                                <div className="text-muted-foreground mt-1 flex flex-wrap gap-x-3 gap-y-0.5 text-xs">
                                                                    <span>Lec: {subject.lecture} unit(s)</span>
                                                                    <span>Lab: {subject.laboratory} unit(s)</span>
                                                                </div>
                                                            </div>
                                                        </button>
                                                        {isSelected && selected && (
                                                            <div className="border-primary/20 flex flex-col gap-2 border-t pt-2 sm:border-t-0 sm:border-l sm:pt-0 sm:pl-3">
                                                                <label className="flex cursor-pointer items-center gap-2 text-xs">
                                                                    <Checkbox
                                                                        checked={selected.is_modular}
                                                                        onCheckedChange={(checked) =>
                                                                            toggleContinuingModular(
                                                                                subject.id,
                                                                                checked === true,
                                                                            )
                                                                        }
                                                                    />
                                                                    <span className="text-muted-foreground">Modular</span>
                                                                </label>
                                                                <div className="text-right font-mono text-xs whitespace-nowrap">
                                                                    <div>Lec: {formatPhp(selected.lecture_fee)}</div>
                                                                    {selected.laboratory_fee > 0 && (
                                                                        <div>Lab: {formatPhp(selected.laboratory_fee)}</div>
                                                                    )}
                                                                </div>
                                                            </div>
                                                        )}
                                                    </div>
                                                );
                                            })
                                        )}
                                    </div>

                                    {/* Selection summary */}
                                    <div className="bg-muted/40 flex flex-wrap items-center justify-between gap-3 rounded-lg border p-3 text-sm">
                                        <div className="text-muted-foreground">
                                            <strong className="text-foreground">{continuingTotals.subjectsCount}</strong>{" "}
                                            subject{continuingTotals.subjectsCount === 1 ? "" : "s"} •{" "}
                                            <strong className="text-foreground">{continuingTotals.totalUnits}</strong>{" "}
                                            unit{continuingTotals.totalUnits === 1 ? "" : "s"}
                                        </div>
                                        <div className="text-foreground font-mono font-semibold">
                                            {formatPhp(continuingTotals.totalTuition)}
                                        </div>
                                    </div>

                                    <div className="flex flex-col gap-3 sm:flex-row">
                                        <Button
                                            variant="outline"
                                            onClick={() => setContinuingStep(0)}
                                            className="sm:flex-1"
                                        >
                                            Back
                                        </Button>
                                        <Button
                                            onClick={() => {
                                                if (selectedSubjects.length === 0) {
                                                    toast.error("Please pick at least one subject.");
                                                    return;
                                                }
                                                setContinuingStep(2);
                                            }}
                                            size="lg"
                                            className="gap-2 sm:flex-[2]"
                                        >
                                            Review tuition <ChevronRight className="h-4 w-4" />
                                        </Button>
                                    </div>
                                </CardContent>
                            </Card>
                        )}

                        {/* === Step 2: Review + tuition breakdown === */}
                        {continuingStep === 2 && (
                            <Card className="border-2 shadow-sm">
                                <CardContent className="space-y-5 p-6 sm:p-8">
                                    <div>
                                        <h3 className="text-foreground text-lg font-semibold">Review &amp; submit</h3>
                                        <p className="text-muted-foreground mt-1 text-sm">
                                            Here&apos;s your estimated tuition for this term. The registrar will finalize
                                            sections and payment details.
                                        </p>
                                    </div>

                                    {/* Selected subjects list */}
                                    <div className="overflow-hidden rounded-lg border">
                                        <div className="bg-muted/50 border-b px-3 py-2 text-xs font-semibold tracking-wider uppercase text-muted-foreground">
                                            Selected subjects ({continuingTotals.subjectsCount})
                                        </div>
                                        <div className="divide-y">
                                            {selectedSubjects.map((s) => (
                                                <div
                                                    key={s.subject_id}
                                                    className="flex flex-col gap-1 p-3 text-sm sm:flex-row sm:items-center sm:justify-between"
                                                >
                                                    <div className="min-w-0">
                                                        <p className="text-foreground truncate font-medium">
                                                            <span className="font-mono text-xs">{s.code}</span> &middot;{" "}
                                                            {s.title}
                                                            {s.is_modular && (
                                                                <Badge
                                                                    variant="outline"
                                                                    className="ml-2 h-5 px-1.5 text-[10px]"
                                                                >
                                                                    Modular
                                                                </Badge>
                                                            )}
                                                        </p>
                                                        <p className="text-muted-foreground text-xs">
                                                            {s.lecture_units} lec + {s.laboratory_units} lab unit(s)
                                                        </p>
                                                    </div>
                                                    <div className="text-right font-mono text-xs whitespace-nowrap">
                                                        {formatPhp(s.lecture_fee + s.laboratory_fee)}
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    </div>

                                    {/* Tuition breakdown */}
                                    <div className="bg-muted/30 space-y-2 rounded-lg border p-4 text-sm">
                                        <div className="text-muted-foreground mb-2 flex items-center gap-1.5 text-xs font-semibold tracking-wider uppercase">
                                            <Sparkles className="h-3.5 w-3.5" /> Tuition breakdown
                                        </div>
                                        <div className="flex justify-between">
                                            <span className="text-muted-foreground">Lecture fees</span>
                                            <span className="font-mono">{formatPhp(continuingTotals.totalLectures)}</span>
                                        </div>
                                        <div className="flex justify-between">
                                            <span className="text-muted-foreground">Laboratory fees</span>
                                            <span className="font-mono">{formatPhp(continuingTotals.totalLaboratory)}</span>
                                        </div>
                                        {continuingTotals.modularCount > 0 && (
                                            <div className="flex justify-between">
                                                <span className="text-muted-foreground">
                                                    Modular ({continuingTotals.modularCount} ×{" "}
                                                    {formatPhp(MODULAR_FEE_PER_SUBJECT)})
                                                </span>
                                                <span className="font-mono">
                                                    {formatPhp(continuingTotals.totalModularFee)}
                                                </span>
                                            </div>
                                        )}
                                        <Separator className="my-1" />
                                        <div className="flex justify-between font-medium">
                                            <span>Subtotal (tuition)</span>
                                            <span className="font-mono">{formatPhp(continuingTotals.totalTuition)}</span>
                                        </div>
                                        <div className="flex justify-between">
                                            <span className="text-muted-foreground">Miscellaneous fees</span>
                                            <span className="font-mono">{formatPhp(continuingTotals.miscellaneous)}</span>
                                        </div>
                                        <Separator className="my-1" />
                                        <div className="text-foreground flex items-center justify-between pt-1 text-base font-bold">
                                            <span>Estimated total</span>
                                            <span className="font-mono">{formatPhp(continuingTotals.overallTotal)}</span>
                                        </div>
                                        <p className="text-muted-foreground mt-2 text-xs">
                                            Final assessment (discounts, downpayment, additional fees) is handled by the
                                            registrar.
                                        </p>
                                    </div>

                                    {/* Consent */}
                                    <label className="bg-muted/20 flex cursor-pointer items-start gap-3 rounded-lg border p-3 text-sm">
                                        <Checkbox
                                            checked={continuingConsent}
                                            onCheckedChange={(checked) => setContinuingConsent(checked === true)}
                                            className="mt-0.5"
                                        />
                                        <span className="text-muted-foreground">
                                            I confirm that the information above is accurate and I agree to the
                                            school&apos;s data privacy notice.
                                        </span>
                                    </label>

                                    <div className="flex flex-col gap-3 sm:flex-row">
                                        <Button
                                            variant="outline"
                                            onClick={() => setContinuingStep(1)}
                                            className="sm:flex-1"
                                        >
                                            Back
                                        </Button>
                                        <Button
                                            onClick={handleSubmitContinuing}
                                            disabled={continuingSubmitting}
                                            size="lg"
                                            className="gap-2 sm:flex-[2]"
                                        >
                                            {continuingSubmitting ? (
                                                <>
                                                    <Loader2 className="h-4 w-4 animate-spin" /> Submitting&hellip;
                                                </>
                                            ) : (
                                                <>
                                                    Submit re-enrollment <ArrowRight className="h-4 w-4" />
                                                </>
                                            )}
                                        </Button>
                                    </div>
                                </CardContent>
                            </Card>
                        )}
                    </motion.div>
                )}
            </main>
        </>
    );

    return (
        <div className="bg-background relative flex min-h-screen flex-col">
            <Head title="Enrollment Registration" />

            {/* Decorative background — themed via shadcn tokens */}
            <div
                aria-hidden
                className="pointer-events-none absolute inset-0 -z-10 overflow-hidden"
            >
                <div className="bg-primary/10 absolute -top-24 -left-24 h-72 w-72 rounded-full blur-3xl sm:h-96 sm:w-96" />
                <div className="bg-primary/5 absolute top-1/3 -right-32 h-80 w-80 rounded-full blur-3xl sm:h-[28rem] sm:w-[28rem]" />
                <div
                    className="absolute inset-0 opacity-[0.03] dark:opacity-[0.05]"
                    style={{
                        backgroundImage:
                            "radial-gradient(circle at 1px 1px, currentColor 1px, transparent 0)",
                        backgroundSize: "24px 24px",
                    }}
                />
            </div>

            {/* Success Screen - Full-screen overlay, scrollable on mobile, centered on desktop */}
            <AnimatePresence>
                {showSuccess && successData && (
                    <motion.div
                        initial={{ opacity: 0 }}
                        animate={{ opacity: 1 }}
                        exit={{ opacity: 0 }}
                        className="bg-background/90 fixed inset-0 z-50 overflow-y-auto backdrop-blur-sm"
                    >
                        <div className="flex min-h-full items-start justify-center p-3 sm:items-center sm:p-6">
                            <motion.div
                                initial={{ scale: 0.95, y: 20 }}
                                animate={{ scale: 1, y: 0 }}
                                className="w-full max-w-2xl"
                            >
                                <Card className="border-primary/20 overflow-hidden border-2 shadow-2xl">
                                    {/* Header */}
                                    <div className="from-primary/10 via-primary/5 to-background bg-gradient-to-br p-6 text-center sm:p-8">
                                        <motion.div
                                            initial={{ scale: 0 }}
                                            animate={{ scale: 1 }}
                                            transition={{ delay: 0.2, type: "spring", stiffness: 200 }}
                                            className="bg-primary/10 mx-auto mb-3 flex h-16 w-16 items-center justify-center rounded-full shadow-inner sm:h-20 sm:w-20"
                                        >
                                            <CheckCircle2 className="text-primary h-8 w-8 sm:h-10 sm:w-10" />
                                        </motion.div>
                                        <motion.div
                                            initial={{ opacity: 0, y: 10 }}
                                            animate={{ opacity: 1, y: 0 }}
                                            transition={{ delay: 0.3 }}
                                        >
                                            <h2 className="text-foreground text-xl font-bold sm:text-2xl">
                                                {successData.continuing ? "Re-enrollment Submitted!" : "Registration Submitted!"}
                                            </h2>
                                            <p className="text-muted-foreground mt-1.5 text-sm">
                                                {successData.continuing
                                                    ? "Your re-enrollment request is on file. The registrar will review your subjects and finalize your assessment."
                                                    : "Your application is on file. The registrar will reach out for confirmation and document verification."}
                                            </p>
                                            {(successData.schoolYear || successData.semesterLabel) && (
                                                <div className="mt-3 flex flex-wrap justify-center gap-1.5">
                                                    {successData.schoolYear && (
                                                        <Badge variant="secondary" className="gap-1">
                                                            <Calendar className="h-3 w-3" />
                                                            SY {successData.schoolYear}
                                                        </Badge>
                                                    )}
                                                    {successData.semesterLabel && (
                                                        <Badge variant="secondary">{successData.semesterLabel}</Badge>
                                                    )}
                                                    {successData.yearLevelLabel && (
                                                        <Badge variant="outline">{successData.yearLevelLabel}</Badge>
                                                    )}
                                                </div>
                                            )}
                                        </motion.div>
                                    </div>

                                    <CardContent className="space-y-5 p-4 sm:p-6">
                                        {/* Student + Program card */}
                                        <div className="bg-muted/30 divide-y rounded-lg border">
                                            <div className="flex flex-col gap-1 p-3 sm:flex-row sm:items-center sm:justify-between sm:p-4">
                                                <span className="text-muted-foreground text-xs font-medium tracking-wider uppercase">
                                                    {successData.continuing ? "Student" : "Applicant"}
                                                </span>
                                                <span className="text-foreground font-semibold sm:text-right">
                                                    {successData.name}
                                                </span>
                                            </div>
                                            <div className="flex flex-col gap-1.5 p-3 sm:flex-row sm:items-center sm:justify-between sm:p-4">
                                                <span className="text-muted-foreground text-xs font-medium tracking-wider uppercase">
                                                    {successData.continuing ? "Student ID" : "Applicant ID"}
                                                </span>
                                                <div className="flex items-center gap-2 sm:justify-end">
                                                    <Badge variant="secondary" className="font-mono text-sm sm:text-base">
                                                        {successData.studentId}
                                                    </Badge>
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        className="h-7 w-7 sm:h-8 sm:w-8"
                                                        onClick={() => copyToClipboard(successData.studentId)}
                                                    >
                                                        <Copy className="h-3.5 w-3.5 sm:h-4 sm:w-4" />
                                                    </Button>
                                                </div>
                                            </div>
                                            <div className="flex flex-col gap-1 p-3 sm:flex-row sm:items-center sm:justify-between sm:p-4">
                                                <span className="text-muted-foreground text-xs font-medium tracking-wider uppercase">
                                                    Program
                                                </span>
                                                <span className="text-foreground font-medium sm:text-right">
                                                    {successData.course}
                                                    {successData.courseCode && (
                                                        <span className="text-muted-foreground ml-1 font-normal">
                                                            ({successData.courseCode})
                                                        </span>
                                                    )}
                                                </span>
                                            </div>
                                        </div>

                                        {/* Subjects list (continuing students only) */}
                                        {successData.continuing && successData.subjects && successData.subjects.length > 0 && (
                                            <div className="overflow-hidden rounded-lg border">
                                                <div className="bg-muted/50 flex items-center justify-between border-b px-3 py-2 text-xs font-semibold tracking-wider uppercase text-muted-foreground sm:px-4">
                                                    <span className="flex items-center gap-1.5">
                                                        <BookOpen className="h-3.5 w-3.5" /> Enrolled subjects
                                                    </span>
                                                    <span>
                                                        {successData.subjects.length} subj • {successData.totalUnits ?? 0} unit
                                                        {(successData.totalUnits ?? 0) === 1 ? "" : "s"}
                                                    </span>
                                                </div>
                                                <div className="max-h-56 divide-y overflow-y-auto sm:max-h-72">
                                                    {successData.subjects.map((s, idx) => (
                                                        <div
                                                            key={`${s.code}-${idx}`}
                                                            className="flex flex-col gap-1 px-3 py-2.5 text-sm sm:flex-row sm:items-center sm:justify-between sm:px-4"
                                                        >
                                                            <div className="min-w-0">
                                                                <p className="text-foreground truncate font-medium">
                                                                    <span className="font-mono text-xs">{s.code}</span>
                                                                    <span className="text-muted-foreground"> · </span>
                                                                    {s.title}
                                                                    {s.is_modular && (
                                                                        <Badge
                                                                            variant="outline"
                                                                            className="ml-2 h-5 px-1.5 text-[10px]"
                                                                        >
                                                                            Modular
                                                                        </Badge>
                                                                    )}
                                                                </p>
                                                                <p className="text-muted-foreground text-xs">
                                                                    {s.lecture_units} lec + {s.laboratory_units} lab unit(s)
                                                                </p>
                                                            </div>
                                                            <div className="text-right font-mono text-xs whitespace-nowrap sm:text-sm">
                                                                {new Intl.NumberFormat("en-PH", {
                                                                    style: "currency",
                                                                    currency: "PHP",
                                                                    minimumFractionDigits: 0,
                                                                    maximumFractionDigits: 0,
                                                                }).format(s.lecture_fee + s.laboratory_fee)}
                                                            </div>
                                                        </div>
                                                    ))}
                                                </div>
                                            </div>
                                        )}

                                        {/* Tuition breakdown (continuing students) */}
                                        {successData.continuing && successData.tuition && (
                                            <div className="bg-primary/5 space-y-2 rounded-lg border p-3 text-sm sm:p-4">
                                                <div className="text-muted-foreground mb-1 flex items-center gap-1.5 text-xs font-semibold tracking-wider uppercase">
                                                    <Sparkles className="h-3.5 w-3.5" /> Estimated tuition
                                                </div>
                                                <div className="grid grid-cols-1 gap-1.5 sm:grid-cols-2 sm:gap-x-6">
                                                    <div className="flex justify-between">
                                                        <span className="text-muted-foreground">Lecture fees</span>
                                                        <span className="font-mono">
                                                            {new Intl.NumberFormat("en-PH", {
                                                                style: "currency",
                                                                currency: "PHP",
                                                                minimumFractionDigits: 0,
                                                                maximumFractionDigits: 0,
                                                            }).format(successData.tuition.total_lectures)}
                                                        </span>
                                                    </div>
                                                    <div className="flex justify-between">
                                                        <span className="text-muted-foreground">Laboratory fees</span>
                                                        <span className="font-mono">
                                                            {new Intl.NumberFormat("en-PH", {
                                                                style: "currency",
                                                                currency: "PHP",
                                                                minimumFractionDigits: 0,
                                                                maximumFractionDigits: 0,
                                                            }).format(successData.tuition.total_laboratory)}
                                                        </span>
                                                    </div>
                                                    <div className="flex justify-between">
                                                        <span className="text-muted-foreground">Tuition subtotal</span>
                                                        <span className="font-mono">
                                                            {new Intl.NumberFormat("en-PH", {
                                                                style: "currency",
                                                                currency: "PHP",
                                                                minimumFractionDigits: 0,
                                                                maximumFractionDigits: 0,
                                                            }).format(successData.tuition.total_tuition)}
                                                        </span>
                                                    </div>
                                                    <div className="flex justify-between">
                                                        <span className="text-muted-foreground">Miscellaneous</span>
                                                        <span className="font-mono">
                                                            {new Intl.NumberFormat("en-PH", {
                                                                style: "currency",
                                                                currency: "PHP",
                                                                minimumFractionDigits: 0,
                                                                maximumFractionDigits: 0,
                                                            }).format(successData.tuition.miscellaneous)}
                                                        </span>
                                                    </div>
                                                </div>
                                                <Separator className="my-1" />
                                                <div className="text-foreground flex items-center justify-between pt-1 text-base font-bold">
                                                    <span>Estimated total</span>
                                                    <span className="font-mono">
                                                        {new Intl.NumberFormat("en-PH", {
                                                            style: "currency",
                                                            currency: "PHP",
                                                            minimumFractionDigits: 0,
                                                            maximumFractionDigits: 0,
                                                        }).format(successData.tuition.overall)}
                                                    </span>
                                                </div>
                                                <p className="text-muted-foreground text-xs">
                                                    Any discounts, downpayment, or additional fees will be applied by the
                                                    registrar during final assessment.
                                                </p>
                                            </div>
                                        )}

                                        {/* Next steps */}
                                        <div className="rounded-lg border border-amber-200 bg-amber-50 p-3 dark:border-amber-900 dark:bg-amber-950/20 sm:p-4">
                                            <h4 className="flex items-center gap-2 font-semibold text-amber-800 dark:text-amber-200">
                                                <Sparkles className="h-4 w-4" /> What&apos;s next?
                                            </h4>
                                            <ul className="mt-2 ml-5 list-disc space-y-1 text-sm text-amber-700 dark:text-amber-300">
                                                <li>
                                                    Save your{" "}
                                                    <strong>{successData.continuing ? "Student ID" : "Applicant ID"}</strong>{" "}
                                                    for reference.
                                                </li>
                                                {successData.continuing ? (
                                                    <>
                                                        <li>Watch your email for the finalized assessment slip.</li>
                                                        <li>Settle any downpayment with the cashier to complete enrollment.</li>
                                                    </>
                                                ) : (
                                                    <>
                                                        <li>Wait for confirmation from the registrar.</li>
                                                        <li>Prepare required documents for verification.</li>
                                                    </>
                                                )}
                                            </ul>
                                        </div>

                                        {/* Actions */}
                                        <div className="flex flex-col gap-2 pt-2 sm:flex-row-reverse">
                                            <Button size="lg" onClick={handleStartNewRegistration} className="sm:flex-1">
                                                {successData.continuing ? "Enroll another student" : "Submit another registration"}
                                            </Button>
                                            <Button variant="outline" size="lg" asChild className="sm:flex-1">
                                                <a href="/">Back to homepage</a>
                                            </Button>
                                        </div>
                                    </CardContent>
                                </Card>
                            </motion.div>
                        </div>
                    </motion.div>
                )}
            </AnimatePresence>

            {mode !== "new" ? (
                identifyView
            ) : (
                <>
            {/* Main Header - Sticky */}
            <header className="bg-background/80 supports-[backdrop-filter]:bg-background/60 sticky top-0 z-40 w-full border-b backdrop-blur-md">
                <div className="container mx-auto flex h-16 max-w-5xl items-center justify-between px-4">
                    <div className="flex items-center gap-3">
                        <div className="bg-primary text-primary-foreground flex h-9 w-9 items-center justify-center rounded-lg shadow-sm">
                            <GraduationCap className="h-5 w-5" />
                        </div>
                        <div>
                            <h1 className="text-sm leading-none font-bold sm:text-base">{orgShortName} Enrollment</h1>
                            <p className="text-muted-foreground mt-0.5 text-[10px] sm:text-xs">Online Registration</p>
                        </div>
                    </div>

                    {/* Desktop Steps */}
                    <div className="hidden items-center gap-2 md:flex">
                        {steps.map((step, index) => (
                            <div key={step.id} className="flex items-center">
                                <div
                                    className={cn(
                                        "flex h-8 w-8 items-center justify-center rounded-full border-2 text-xs font-bold transition-colors",
                                        currentStep === index
                                            ? "border-primary bg-primary text-primary-foreground"
                                            : currentStep > index
                                              ? "border-primary bg-primary text-primary-foreground"
                                              : "border-muted-foreground/30 text-muted-foreground",
                                    )}
                                >
                                    {currentStep > index ? <Check className="h-4 w-4" /> : index + 1}
                                </div>
                                {index < steps.length - 1 && (
                                    <div className={cn("mx-2 h-0.5 w-8", currentStep > index ? "bg-primary" : "bg-muted-foreground/30")} />
                                )}
                            </div>
                        ))}
                    </div>

                    {/* Mobile Step Indicator */}
                    <div className="flex flex-col items-end md:hidden">
                        <span className="text-muted-foreground text-xs font-medium">
                            Step {currentStep + 1} of {steps.length}
                        </span>
                        <div className="bg-muted mt-1 h-1.5 w-24 overflow-hidden rounded-full">
                            <motion.div
                                className="bg-primary h-full"
                                initial={{ width: 0 }}
                                animate={{ width: `${((currentStep + 1) / steps.length) * 100}%` }}
                            />
                        </div>
                    </div>
                </div>
            </header>

            {/* Content Area */}
            <main className="container mx-auto max-w-3xl flex-1 p-4 pb-32 sm:p-6 lg:p-8">
                {/* Mobile Step Title */}
                <div className="mb-6 md:hidden">
                    <h2 className="text-2xl font-bold">{steps[currentStep].label}</h2>
                    <p className="text-muted-foreground mt-1 text-sm">{steps[currentStep].description}</p>
                </div>

                <AnimatePresence mode="wait" custom={direction}>
                    <motion.div
                        key={currentStep}
                        custom={direction}
                        variants={variants}
                        initial="enter"
                        animate="center"
                        exit="exit"
                        transition={{ type: "spring", stiffness: 300, damping: 30 }}
                        className="space-y-6"
                    >
                        {/* Desktop Title (Hidden on mobile to avoid duplication) */}
                        <div className="mb-8 hidden space-y-2 md:block">
                            <h2 className="text-3xl font-bold tracking-tight">{steps[currentStep].label}</h2>
                            <p className="text-muted-foreground text-lg">
                                {currentStep === 0 && "Select your student type and desired program."}
                                {currentStep === 1 && "Fill in your personal information accurately."}
                                {currentStep === 2 && "Provide contact details for emergencies."}
                                {currentStep === 3 && "Check the documents you have ready to upload."}
                                {currentStep === 4 && "Review all information before submitting."}
                            </p>
                            <Separator className="mt-6" />
                        </div>

                        {/* Step 1: Program Selection */}
                        {currentStep === 0 && (
                            <div className="space-y-6">
                                <div className="space-y-3">
                                    <Label className="text-base font-semibold">What type of student are you?</Label>
                                    <RadioGroup
                                        value={data.student_type}
                                        onValueChange={(val: "college" | "tesda") => {
                                            setData("student_type", val);
                                        }}
                                        className="grid grid-cols-1 gap-4 sm:grid-cols-2"
                                    >
                                        <div className={cn("relative", !college_enrollment_enabled && "opacity-60")}>
                                            <RadioGroupItem value="college" id="type-college" className="peer sr-only" disabled={!college_enrollment_enabled} />
                                            <Label
                                                htmlFor="type-college"
                                                className={cn(
                                                    "border-muted bg-card flex items-center gap-4 rounded-xl border-2 p-4",
                                                    college_enrollment_enabled
                                                        ? "cursor-pointer hover:bg-accent peer-data-[state=checked]:border-primary peer-data-[state=checked]:bg-primary/5 shadow-sm transition-all"
                                                        : "cursor-not-allowed grayscale hover:bg-transparent",
                                                )}
                                            >
                                                <div className="bg-primary/10 shrink-0 rounded-full p-3">
                                                    <BookOpen className="text-primary h-6 w-6" />
                                                </div>
                                                <div className="space-y-1">
                                                    <span className="font-semibold">College Student</span>
                                                    <p className="text-muted-foreground text-xs font-normal">
                                                        4-year degree programs (BSIT, BSHM, BSBA).
                                                    </p>
                                                </div>
                                            </Label>
                                            {!college_enrollment_enabled && (
                                                <Badge variant="destructive" className="absolute top-2 right-2 px-1.5 py-0 text-[10px]">
                                                    Closed
                                                </Badge>
                                            )}
                                        </div>
                                        <div className={cn("relative", !tesda_enrollment_enabled && "opacity-60")}>
                                            <RadioGroupItem value="tesda" id="type-tesda" className="peer sr-only" disabled={!tesda_enrollment_enabled} />
                                            <Label
                                                htmlFor="type-tesda"
                                                className={cn(
                                                    "border-muted bg-card flex items-center gap-4 rounded-xl border-2 p-4",
                                                    tesda_enrollment_enabled
                                                        ? "cursor-pointer hover:bg-accent peer-data-[state=checked]:border-primary peer-data-[state=checked]:bg-primary/5 shadow-sm transition-all"
                                                        : "cursor-not-allowed grayscale hover:bg-transparent",
                                                )}
                                            >
                                                <div className={cn("shrink-0 rounded-full p-3", tesda_enrollment_enabled ? "bg-orange-500/10" : "bg-muted")}>
                                                    <Sparkles className={cn("h-6 w-6", tesda_enrollment_enabled ? "text-orange-600" : "text-muted-foreground")} />
                                                </div>
                                                <div className="space-y-1">
                                                    <span className="font-semibold">TESDA Scholar</span>
                                                    <p className="text-muted-foreground text-xs font-normal">
                                                        Short-term technical and vocational courses.
                                                    </p>
                                                </div>
                                            </Label>
                                            {!tesda_enrollment_enabled && (
                                                <Badge variant="destructive" className="absolute top-2 right-2 px-1.5 py-0 text-[10px]">
                                                    Closed
                                                </Badge>
                                            )}
                                        </div>
                                    </RadioGroup>
                                </div>

                                {data.student_type === "college" && availableDepartments.length > 0 && (
                                    <div className="space-y-3">
                                        <Label className="text-base font-semibold">Department</Label>
                                        <Card className="border shadow-sm">
                                            <CardContent className="p-4">
                                                <Combobox
                                                    value={data.department}
                                                    onValueChange={(val) => {
                                                        setData("department", val);
                                                        setData("course_id", "");
                                                    }}
                                                    options={availableDepartments.map((dept) => ({
                                                        value: dept.code,
                                                        label: dept.label,
                                                        searchText: `${dept.code} ${dept.label}`,
                                                    }))}
                                                    placeholder="Select a department (optional)..."
                                                    emptyText="No departments found."
                                                    className="w-full"
                                                />
                                                <p className="text-muted-foreground mt-2 text-xs">
                                                    Filter by department or leave empty to see all college programs.
                                                </p>
                                            </CardContent>
                                        </Card>
                                    </div>
                                )}

                                <div className="space-y-3">
                                    <Label className="text-base font-semibold">Course / Program</Label>
                                    <Card className="border shadow-sm">
                                        <CardContent className="p-4">
                                            <Combobox
                                                value={data.course_id}
                                                onValueChange={(val) => setData("course_id", val)}
                                                options={courseOptions}
                                                placeholder={data.student_type === "tesda" ? "Select a TESDA course..." : "Select a college program..."}
                                                emptyText={data.student_type === "tesda" ? "No TESDA courses found." : "No college programs found."}
                                                className="w-full"
                                            />
                                            {selectedCourse?.description && (
                                                <div className="text-muted-foreground bg-muted/30 mt-3 flex gap-2 rounded-md p-3 text-sm">
                                                    <div className="shrink-0 pt-0.5">
                                                        <School className="h-4 w-4" />
                                                    </div>
                                                    <p>{selectedCourse.description}</p>
                                                </div>
                                            )}
                                        </CardContent>
                                    </Card>
                                </div>
                            </div>
                        )}

                        {/* ... other steps ... */}
                        {/* I will fill these in via subsequent edits to ensure I don't hit token limits or mess up context */}
                        {/* Step 2: Personal Details */}
                        {currentStep === 1 && (
                            <div className="space-y-6">
                                {/* Mobile friendly form groups */}
                                <Card>
                                    <CardContent className="space-y-4 p-4 sm:p-6">
                                        <div className="mb-2 flex items-center gap-2">
                                            <User className="text-primary h-5 w-5" />
                                            <h3 className="text-lg font-semibold">Identity</h3>
                                        </div>
                                        <div className="grid gap-4 sm:grid-cols-2">
                                            <div className="space-y-2">
                                                <Label>
                                                    First Name <span className="text-destructive">*</span>
                                                </Label>
                                                <Input
                                                    value={data.first_name}
                                                    onChange={(e) => setData("first_name", sanitizeNameInput(e.target.value))}
                                                    placeholder="e.g. Juan"
                                                />
                                                {errorsBag.first_name && <p className="text-destructive text-xs">{errorsBag.first_name}</p>}
                                            </div>
                                            <div className="space-y-2">
                                                <Label>
                                                    Last Name <span className="text-destructive">*</span>
                                                </Label>
                                                <Input
                                                    value={data.last_name}
                                                    onChange={(e) => setData("last_name", sanitizeNameInput(e.target.value))}
                                                    placeholder="e.g. Dela Cruz"
                                                />
                                                {errorsBag.last_name && <p className="text-destructive text-xs">{errorsBag.last_name}</p>}
                                            </div>
                                            <div className="space-y-2">
                                                <Label>Middle Name</Label>
                                                <Input
                                                    value={data.middle_name}
                                                    onChange={(e) => setData("middle_name", sanitizeNameInput(e.target.value))}
                                                    placeholder="(Optional)"
                                                />
                                            </div>
                                            <div className="space-y-2">
                                                <Label>Suffix</Label>
                                                <Input
                                                    value={data.suffix}
                                                    onChange={(e) => setData("suffix", sanitizeNameInput(e.target.value))}
                                                    placeholder="e.g. Jr."
                                                />
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>

                                <Card>
                                    <CardContent className="space-y-4 p-4 sm:p-6">
                                        <div className="mb-2 flex items-center gap-2">
                                            <FileText className="text-primary h-5 w-5" />
                                            <h3 className="text-lg font-semibold">Demographics</h3>
                                        </div>
                                        <div className="grid gap-4 sm:grid-cols-2">
                                            <div className="space-y-2">
                                                <Label>
                                                    Birth Date <span className="text-destructive">*</span>
                                                </Label>
                                                <Input
                                                    type="date"
                                                    value={data.birth_date}
                                                    onChange={(e) => setData("birth_date", e.target.value)}
                                                    className="block w-full"
                                                />
                                            </div>
                                            <div className="space-y-2">
                                                <Label>
                                                    Gender <span className="text-destructive">*</span>
                                                </Label>
                                                <select
                                                    className="border-input bg-background ring-offset-background focus-visible:ring-ring flex h-10 w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none"
                                                    value={data.gender}
                                                    onChange={(e) => setData("gender", e.target.value)}
                                                >
                                                    <option value="">Select Gender</option>
                                                    <option value="male">Male</option>
                                                    <option value="female">Female</option>
                                                </select>
                                            </div>
                                            <div className="space-y-2">
                                                <Label>Civil Status</Label>
                                                <select
                                                    className="border-input bg-background ring-offset-background focus-visible:ring-ring flex h-10 w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none"
                                                    value={data.civil_status}
                                                    onChange={(e) => setData("civil_status", e.target.value)}
                                                >
                                                    <option value="">Select Status</option>
                                                    <option value="Single">Single</option>
                                                    <option value="Married">Married</option>
                                                    <option value="Widowed">Widowed</option>
                                                    <option value="Separated">Separated</option>
                                                </select>
                                            </div>
                                            <div className="space-y-2">
                                                <Label>
                                                    Nationality <span className="text-destructive">*</span>
                                                </Label>
                                                <Input
                                                    value={data.nationality}
                                                    onChange={(e) => setData("nationality", sanitizeNameInput(e.target.value))}
                                                />
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>

                                <Card>
                                    <CardContent className="space-y-4 p-4 sm:p-6">
                                        <div className="mb-2 flex items-center gap-2">
                                            <School className="text-primary h-5 w-5" />
                                            <h3 className="text-lg font-semibold">Contact Info</h3>
                                        </div>
                                        <div className="space-y-4">
                                            <div className="grid gap-4 sm:grid-cols-2">
                                                <div className="space-y-2">
                                                    <Label>Email Address</Label>
                                                    <Input
                                                        type="email"
                                                        value={data.email}
                                                        onChange={(e) => setData("email", e.target.value)}
                                                        placeholder="juan@example.com"
                                                    />
                                                </div>
                                                <div className="space-y-2">
                                                    <Label>
                                                        Mobile Number <span className="text-destructive">*</span>
                                                    </Label>
                                                    <Input
                                                        value={data.phone}
                                                        onChange={(e) => setData("phone", sanitizeNumberInput(e.target.value))}
                                                        placeholder="09123456789"
                                                        type="tel"
                                                        inputMode="numeric"
                                                    />
                                                </div>
                                            </div>
                                            <div className="space-y-2">
                                                <Label>
                                                    Complete Address <span className="text-destructive">*</span>
                                                </Label>
                                                <Textarea
                                                    value={data.address}
                                                    onChange={(e) => setData("address", e.target.value)}
                                                    placeholder="House No., Street, Barangay, City, Province"
                                                    className="min-h-[80px]"
                                                />
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>
                            </div>
                        )}

                        {/* Step 3: Contacts */}
                        {currentStep === 2 && (
                            <div className="space-y-6">
                                <Card>
                                    <CardContent className="space-y-4 p-4 sm:p-6">
                                        <div className="text-destructive mb-2 flex items-center gap-2">
                                            <School className="h-5 w-5" />
                                            <h3 className="text-lg font-semibold">Emergency Contact</h3>
                                        </div>
                                        <div className="grid gap-4 sm:grid-cols-2">
                                            <div className="space-y-2">
                                                <Label>
                                                    Contact Name <span className="text-destructive">*</span>
                                                </Label>
                                                <Input
                                                    value={data.contacts.emergency_contact_name}
                                                    onChange={(e) =>
                                                        setData("contacts", {
                                                            ...data.contacts,
                                                            emergency_contact_name: sanitizeNameInput(e.target.value),
                                                        })
                                                    }
                                                />
                                            </div>
                                            <div className="space-y-2">
                                                <Label>
                                                    Phone Number <span className="text-destructive">*</span>
                                                </Label>
                                                <Input
                                                    value={data.contacts.emergency_contact_phone}
                                                    onChange={(e) =>
                                                        setData("contacts", {
                                                            ...data.contacts,
                                                            emergency_contact_phone: sanitizeNumberInput(e.target.value),
                                                        })
                                                    }
                                                    type="tel"
                                                    inputMode="numeric"
                                                />
                                            </div>
                                            <div className="space-y-2 sm:col-span-2">
                                                <Label>Relationship</Label>
                                                <Input
                                                    value={data.contacts.emergency_contact_relationship}
                                                    onChange={(e) =>
                                                        setData("contacts", {
                                                            ...data.contacts,
                                                            emergency_contact_relationship: sanitizeNameInput(e.target.value),
                                                        })
                                                    }
                                                    placeholder="e.g. Mother, Father"
                                                />
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>

                                <Card>
                                    <CardContent className="space-y-4 p-4 sm:p-6">
                                        <div className="text-primary mb-2 flex items-center gap-2">
                                            <User className="h-5 w-5" />
                                            <h3 className="text-lg font-semibold">Guardian / Parents</h3>
                                        </div>
                                        <div className="grid gap-4 sm:grid-cols-2">
                                            <div className="space-y-2">
                                                <Label>
                                                    Guardian Name <span className="text-destructive">*</span>
                                                </Label>
                                                <Input
                                                    value={data.parents.guardian_name}
                                                    onChange={(e) =>
                                                        setData("parents", { ...data.parents, guardian_name: sanitizeNameInput(e.target.value) })
                                                    }
                                                />
                                            </div>
                                            <div className="space-y-2">
                                                <Label>
                                                    Guardian Contact <span className="text-destructive">*</span>
                                                </Label>
                                                <Input
                                                    value={data.parents.guardian_contact}
                                                    onChange={(e) =>
                                                        setData("parents", { ...data.parents, guardian_contact: sanitizeNumberInput(e.target.value) })
                                                    }
                                                    type="tel"
                                                    inputMode="numeric"
                                                />
                                            </div>
                                            <div className="space-y-2 sm:col-span-2">
                                                <Label>
                                                    Relationship <span className="text-destructive">*</span>
                                                </Label>
                                                <Input
                                                    value={data.parents.guardian_relationship}
                                                    onChange={(e) =>
                                                        setData("parents", {
                                                            ...data.parents,
                                                            guardian_relationship: sanitizeNameInput(e.target.value),
                                                        })
                                                    }
                                                />
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>
                            </div>
                        )}

                        {/* Step 4: Documents */}
                        {currentStep === 3 && (
                            <div className="space-y-6">
                                <div className="rounded-lg border border-blue-200 bg-blue-50 p-4 dark:border-blue-900 dark:bg-blue-950/20">
                                    <div className="flex gap-3">
                                        <div className="mt-0.5">
                                            <FileText className="h-5 w-5 text-blue-600 dark:text-blue-400" />
                                        </div>
                                        <div>
                                            <h4 className="font-semibold text-blue-800 dark:text-blue-200">Document Availability</h4>
                                            <p className="mt-1 text-sm leading-relaxed text-blue-700 dark:text-blue-300">
                                                Do you have soft copies (photos/scans) of your documents right now?
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <Card>
                                    <CardContent className="space-y-4 p-4 sm:p-6">
                                        <div className="flex items-center justify-between">
                                            <h3 className="text-lg font-semibold">Checklist</h3>
                                            <span className="text-muted-foreground text-xs">Select what you have</span>
                                        </div>
                                        <div className="grid gap-3">
                                            {DOCUMENT_TYPES.map((docType) => (
                                                <label
                                                    key={docType.id}
                                                    htmlFor={`doc-check-${docType.id}`}
                                                    className={cn(
                                                        "relative flex cursor-pointer items-start gap-4 rounded-xl border p-4 transition-all active:scale-[0.99]",
                                                        availableDocuments[docType.id]
                                                            ? "border-primary bg-primary/5 shadow-sm"
                                                            : "border-border hover:bg-muted/30",
                                                    )}
                                                >
                                                    <Checkbox
                                                        id={`doc-check-${docType.id}`}
                                                        checked={availableDocuments[docType.id]}
                                                        onCheckedChange={(checked) => {
                                                            setAvailableDocuments((prev) => ({ ...prev, [docType.id]: checked === true }));
                                                            if (!checked)
                                                                setUploadedDocuments((prev) => prev.filter((doc) => doc.type !== docType.id));
                                                        }}
                                                        className="mt-1"
                                                    />
                                                    <div className="flex-1">
                                                        <div className="flex items-center gap-2">
                                                            <span className="text-foreground font-medium">{docType.label}</span>
                                                            {docType.required && (
                                                                <Badge variant="secondary" className="h-5 px-1.5 text-[10px]">
                                                                    Required
                                                                </Badge>
                                                            )}
                                                        </div>
                                                        <p className="text-muted-foreground mt-0.5 text-xs">
                                                            {availableDocuments[docType.id]
                                                                ? "I have this document ready"
                                                                : "I will bring this later"}
                                                        </p>
                                                    </div>
                                                </label>
                                            ))}
                                        </div>
                                    </CardContent>
                                </Card>

                                {hasAnyDocumentsToUpload && (
                                    <div className="space-y-6">
                                        <div className="flex items-center gap-2 px-2">
                                            <Upload className="text-primary h-5 w-5" />
                                            <h3 className="text-lg font-semibold">Upload Selected Files</h3>
                                        </div>

                                        {DOCUMENT_TYPES.filter((d) => availableDocuments[d.id]).map((docType) => {
                                            const docsForType = getDocumentsForType(docType.id);
                                            return (
                                                <Card key={docType.id} className="overflow-hidden">
                                                    <div className="bg-muted/30 flex items-center justify-between border-b p-3">
                                                        <span className="text-sm font-medium">{docType.label}</span>
                                                        {docsForType.length > 0 && (
                                                            <Badge variant="default" className="bg-green-600 text-[10px] hover:bg-green-700">
                                                                {docsForType.length} Added
                                                            </Badge>
                                                        )}
                                                    </div>
                                                    <CardContent className="space-y-3 p-4">
                                                        {/* Uploaded Files List */}
                                                        {docsForType.length > 0 && (
                                                            <div className="grid grid-cols-1 gap-2">
                                                                {docsForType.map((doc) => (
                                                                    <div
                                                                        key={doc.id}
                                                                        className="bg-background flex items-center gap-3 rounded-lg border p-2 pr-3 shadow-sm"
                                                                    >
                                                                        {doc.preview ? (
                                                                            <img
                                                                                src={doc.preview}
                                                                                className="h-10 w-10 rounded border object-cover"
                                                                                alt=""
                                                                            />
                                                                        ) : (
                                                                            <div className="bg-muted flex h-10 w-10 items-center justify-center rounded">
                                                                                <FileText className="text-muted-foreground h-5 w-5" />
                                                                            </div>
                                                                        )}
                                                                        <div className="min-w-0 flex-1">
                                                                            <p className="truncate text-sm font-medium">{doc.file.name}</p>
                                                                            <p className="text-muted-foreground text-xs">
                                                                                {(doc.file.size / 1024).toFixed(0)} KB
                                                                            </p>
                                                                        </div>
                                                                        <Button
                                                                            size="icon"
                                                                            variant="ghost"
                                                                            className="text-destructive h-8 w-8"
                                                                            onClick={() => handleRemoveDocument(doc.id)}
                                                                        >
                                                                            <Trash2 className="h-4 w-4" />
                                                                        </Button>
                                                                    </div>
                                                                ))}
                                                            </div>
                                                        )}

                                                        {/* Mobile Friendly Dropzone */}
                                                        <div
                                                            onClick={() => {
                                                                if (fileInputRef.current) {
                                                                    fileInputRef.current.setAttribute("data-doc-type", docType.id);
                                                                    fileInputRef.current.click();
                                                                }
                                                            }}
                                                            className="hover:bg-muted/30 active:bg-muted flex cursor-pointer flex-col items-center justify-center gap-3 rounded-xl border-2 border-dashed p-6 text-center transition-colors"
                                                        >
                                                            <div className="bg-primary/10 text-primary flex h-10 w-10 items-center justify-center rounded-full">
                                                                <Upload className="h-5 w-5" />
                                                            </div>
                                                            <div>
                                                                <p className="text-foreground text-sm font-medium">Tap to upload</p>
                                                                <p className="text-muted-foreground mt-0.5 text-xs">or drag files here</p>
                                                            </div>
                                                        </div>
                                                    </CardContent>
                                                </Card>
                                            );
                                        })}
                                    </div>
                                )}

                                {/* Note about originals - simplified */}
                                {!hasAnyDocumentsToUpload && (
                                    <p className="text-muted-foreground px-4 text-center text-sm">
                                        You can skip uploading for now. Please bring your original documents to the registrar.
                                    </p>
                                )}
                            </div>
                        )}

                        {/* Step 5: Review */}
                        {currentStep === 4 && (
                            <div className="space-y-6">
                                <Card className="border-primary/20 shadow-md">
                                    <div className="bg-primary/5 border-primary/10 border-b p-6 text-center">
                                        <h3 className="text-primary text-xl font-bold">Review Details</h3>
                                        <p className="text-muted-foreground text-sm">Verify your information</p>
                                    </div>
                                    <CardContent className="p-0">
                                        <div className="divide-y">
                                            <div className="grid gap-1 p-4 sm:p-6">
                                                <span className="text-muted-foreground text-xs font-medium uppercase">Applicant</span>
                                                <p className="text-lg font-semibold">
                                                    {data.last_name}, {data.first_name} {data.middle_name}
                                                </p>
                                                <div className="text-muted-foreground mt-1 flex gap-4 text-sm">
                                                    <span>{data.gender}</span>
                                                    <span>•</span>
                                                    <span>{data.phone}</span>
                                                </div>
                                            </div>

                                            <div className="grid gap-1 p-4 sm:p-6">
                                                <span className="text-muted-foreground text-xs font-medium uppercase">Program</span>
                                                <p className="text-foreground font-medium">{selectedCourse?.title || "Not Selected"}</p>
                                                <Badge variant="outline" className="mt-2 w-fit">
                                                    {data.student_type.toUpperCase()}
                                                </Badge>
                                            </div>

                                            <div className="grid gap-1 p-4 sm:p-6">
                                                <span className="text-muted-foreground text-xs font-medium uppercase">Guardian</span>
                                                <p className="font-medium">{data.parents.guardian_name}</p>
                                                <p className="text-muted-foreground text-sm">{data.parents.guardian_relationship}</p>
                                                <p className="text-muted-foreground text-sm">{data.parents.guardian_contact}</p>
                                            </div>

                                            <div className="p-4 sm:p-6">
                                                <span className="text-muted-foreground mb-3 block text-xs font-medium uppercase">Documents</span>
                                                {uploadedDocuments.length > 0 ? (
                                                    <div className="flex flex-wrap gap-2">
                                                        {DOCUMENT_TYPES.filter((d) => getDocumentsForType(d.id).length > 0).map((doc) => (
                                                            <Badge key={doc.id} variant="secondary" className="gap-1 py-1 pr-2 pl-1">
                                                                <Check className="h-3 w-3 text-green-600" /> {doc.label}
                                                            </Badge>
                                                        ))}
                                                    </div>
                                                ) : (
                                                    <p className="text-muted-foreground text-sm italic">No documents uploaded (to follow)</p>
                                                )}
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>

                                <div className="bg-card flex items-start gap-3 rounded-xl border p-4 shadow-sm">
                                    <Checkbox
                                        id="consent"
                                        checked={data.consent}
                                        onCheckedChange={(val) => setData("consent", val === true)}
                                        className="mt-1"
                                    />
                                    <div className="space-y-1">
                                        <Label htmlFor="consent" className="cursor-pointer text-sm font-semibold sm:text-base">
                                            I confirm the information is correct.
                                        </Label>
                                        <p className="text-muted-foreground text-xs leading-relaxed">
                                            By checking this box, you agree to the Data Privacy Act of 2012 and allow the institution to process your
                                            personal data.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        )}
                    </motion.div>
                </AnimatePresence>
            </main>

            {/* Sticky Bottom Navigation Bar */}
            <div className="bg-background/95 supports-[backdrop-filter]:bg-background/80 sticky bottom-0 z-30 w-full border-t p-4 shadow-[0_-4px_6px_-1px_rgba(0,0,0,0.05)] backdrop-blur">
                <div className="container mx-auto flex max-w-3xl items-center gap-3">
                    <Button
                        variant="outline"
                        size="lg"
                        onClick={handlePrev}
                        disabled={currentStep === 0}
                        className="h-12 flex-1 border-2 text-base sm:w-32 sm:flex-none"
                    >
                        Back
                    </Button>

                    <Button
                        onClick={currentStep < steps.length - 1 ? handleNext : submit}
                        disabled={processing}
                        size="lg"
                        className={cn(
                            "shadow-primary/20 h-12 flex-[2] text-base font-semibold shadow-lg",
                            currentStep === steps.length - 1 && "from-primary to-primary/90 bg-gradient-to-r",
                        )}
                    >
                        {processing ? (
                            <>
                                <Loader2 className="mr-2 h-5 w-5 animate-spin" />
                                Processing...
                            </>
                        ) : currentStep < steps.length - 1 ? (
                            <>
                                Next Step <ChevronRight className="ml-2 h-5 w-5" />
                            </>
                        ) : (
                            <>
                                Submit Application <Sparkles className="ml-2 h-5 w-5" />
                            </>
                        )}
                    </Button>
                </div>
            </div>

            {/* Hidden Inputs (Required for functionality) */}
            <input
                ref={fileInputRef}
                type="file"
                className="hidden"
                accept="image/jpeg,image/png,image/webp,application/pdf"
                multiple
                onChange={(e) => {
                    const docType = e.target.getAttribute("data-doc-type") || "other";
                    handleFileSelect(e.target.files, docType);
                    e.target.value = "";
                }}
            />
                </>
            )}
        </div>
    );
}
