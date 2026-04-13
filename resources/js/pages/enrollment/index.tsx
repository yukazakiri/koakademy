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
import { AnimatePresence, motion } from "framer-motion";
import {
    BookOpen,
    Check,
    CheckCircle2,
    ChevronRight,
    Copy,
    FileText,
    GraduationCap,
    Loader2,
    School,
    Sparkles,
    Trash2,
    Upload,
    User,
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

type Flash = {
    success?: string;
    error?: string;
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
            // Parse success message to extract applicant ID
            const match = flash.success.match(/Applicant ID:\s*(\d+)/);
            const applicantId = match ? match[1] : "Pending";

            // Set success data
            const course = courses.find((c) => String(c.id) === data.course_id);
            setSuccessData({
                name: `${data.first_name} ${data.last_name}`,
                studentId: applicantId,
                course: course?.title ?? "Unknown Program",
            });
            setShowSuccess(true);
            toast.success("Registration submitted successfully!");
        }

        if (flash?.error) {
            toast.error(flash.error);
        }
    }, [flash?.error, flash?.success, courses, data.course_id, data.first_name, data.last_name]);

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
            return normalizedDepartment.length > 0 && courseDepartment === normalizedDepartment;
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

    return (
        <div className="flex min-h-screen flex-col bg-slate-50 dark:bg-slate-950">
            <Head title="Enrollment Registration" />

            {/* Success Screen - Full Screen Overlay */}
            <AnimatePresence>
                {showSuccess && successData && (
                    <motion.div
                        initial={{ opacity: 0 }}
                        animate={{ opacity: 1 }}
                        exit={{ opacity: 0 }}
                        className="bg-background/80 fixed inset-0 z-50 flex items-center justify-center p-4 backdrop-blur-sm"
                    >
                        <motion.div initial={{ scale: 0.9, y: 20 }} animate={{ scale: 1, y: 0 }} className="w-full max-w-lg">
                            <Card className="border-primary/20 overflow-hidden border-2 shadow-2xl">
                                <div className="from-primary/10 via-primary/5 to-background bg-gradient-to-br p-8 text-center">
                                    <motion.div
                                        initial={{ scale: 0 }}
                                        animate={{ scale: 1 }}
                                        transition={{ delay: 0.2, type: "spring", stiffness: 200 }}
                                        className="bg-primary/10 mx-auto mb-4 flex h-20 w-20 items-center justify-center rounded-full shadow-inner"
                                    >
                                        <CheckCircle2 className="text-primary h-10 w-10" />
                                    </motion.div>
                                    <motion.div initial={{ opacity: 0, y: 10 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: 0.3 }}>
                                        <h2 className="text-foreground text-2xl font-bold">Registration Successful!</h2>
                                        <p className="text-muted-foreground mt-2 text-sm">Your application has been submitted to the registrar.</p>
                                    </motion.div>
                                </div>
                                {/* ... existing success content ... */}
                                <CardContent className="space-y-6 p-6">
                                    <div className="bg-muted/30 space-y-4 rounded-lg border p-4">
                                        <div className="flex items-center justify-between">
                                            <span className="text-muted-foreground text-sm">Applicant Name</span>
                                            <span className="text-foreground text-right font-semibold">{successData.name}</span>
                                        </div>
                                        <Separator />
                                        <div className="flex items-center justify-between">
                                            <span className="text-muted-foreground text-sm">Applicant ID</span>
                                            <div className="flex items-center gap-2">
                                                <Badge variant="secondary" className="font-mono text-base">
                                                    {successData.studentId}
                                                </Badge>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    className="h-8 w-8"
                                                    onClick={() => copyToClipboard(successData.studentId)}
                                                >
                                                    <Copy className="h-4 w-4" />
                                                </Button>
                                            </div>
                                        </div>
                                        <Separator />
                                        <div className="flex items-center justify-between">
                                            <span className="text-muted-foreground text-sm">Program</span>
                                            <span className="text-foreground text-right font-medium">{successData.course}</span>
                                        </div>
                                    </div>

                                    <div className="rounded-lg border border-amber-200 bg-amber-50 p-4 dark:border-amber-900 dark:bg-amber-950/20">
                                        <h4 className="flex items-center gap-2 font-semibold text-amber-800 dark:text-amber-200">
                                            <Sparkles className="h-4 w-4" /> What's Next?
                                        </h4>
                                        <ul className="mt-2 ml-5 list-disc space-y-1 text-sm text-amber-700 dark:text-amber-300">
                                            <li>
                                                Save your <strong>Applicant ID</strong> for reference
                                            </li>
                                            <li>Wait for confirmation from the registrar</li>
                                            <li>Prepare required documents for verification</li>
                                        </ul>
                                    </div>

                                    <div className="flex flex-col gap-3 pt-2">
                                        <Button size="lg" onClick={handleStartNewRegistration} className="w-full">
                                            Submit Another Registration
                                        </Button>
                                        <Button variant="outline" size="lg" asChild className="w-full">
                                            <a href="/">Back to Homepage</a>
                                        </Button>
                                    </div>
                                </CardContent>
                            </Card>
                        </motion.div>
                    </motion.div>
                )}
            </AnimatePresence>

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
        </div>
    );
}
