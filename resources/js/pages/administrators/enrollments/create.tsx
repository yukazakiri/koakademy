import AdminLayout from "@/components/administrators/admin-layout";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from "@/components/ui/collapsible";
import { Command, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList } from "@/components/ui/command";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Switch } from "@/components/ui/switch";

import { ScrollArea } from "@/components/ui/scroll-area";
import { Separator } from "@/components/ui/separator";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { cn } from "@/lib/utils";
import type { User } from "@/types/user";
import { Head, Link, router, usePage } from "@inertiajs/react";
import {
    AlertTriangle,
    ArrowLeft,
    BookOpen,
    Calculator,
    Calendar,
    Check,
    CheckCircle2,
    ChevronDown,
    ChevronsUpDown,
    Loader2,
    Plus,
    Search,
    Star,
    Trash2,
    User as UserIcon,
    X,
} from "lucide-react";
import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import { toast } from "sonner";
import { route } from "ziggy-js";

// Types
interface StudentOption {
    id: number;
    full_name: string;
    email: string;
    course_id: number | null;
    course_code: string | null;
    academic_year: number | null;
    formatted_academic_year: string | null;
    label: string;
}

interface StudentDetails {
    id: number;
    full_name: string;
    email: string;
    course_id: number | null;
    course_code: string | null;
    course_name: string | null;
    academic_year: number | null;
    formatted_academic_year: string | null;
    miscellaneous_fee: number;
}

interface SubjectOption {
    id: number;
    code: string;
    title: string;
    lecture: number;
    laboratory: number;
    lec_per_unit: number;
    lab_per_unit: number;
    has_classes: boolean;
    label: string;
}

interface SectionOption {
    id: number;
    section: string;
    faculty: string;
    enrolled_count: number;
    max_slots: number;
    available_slots: number | null;
    is_full: boolean;
    label: string;
    schedule: string;
    schedules?: {
        day: string;
        start_time: string;
        end_time: string;
        room: string | null;
    }[];
}

interface SubjectEnrollment {
    id: string;
    subject_id: number;
    subject_code: string;
    subject_title: string;
    class_id: number | null;
    is_modular: boolean;
    lecture_units: number;
    laboratory_units: number;
    lecture_fee: number;
    laboratory_fee: number;
    lec_per_unit: number;
    lab_per_unit: number;
    section_options: SectionOption[];
    loading_sections: boolean;
    has_classes: boolean;
}

interface AdditionalFee {
    id: string;
    fee_name: string;
    amount: number;
}

interface EnrollmentSubject {
    subject_id: number;
    subject_code: string;
    subject_title: string;
    class_id: number | null;
    is_modular: boolean;
    lecture_units: number;
    laboratory_units: number;
    lecture_fee: number;
    laboratory_fee: number;
    lec_per_unit: number;
    lab_per_unit: number;
    has_classes: boolean;
}

interface EnrollmentData {
    id: number;
    semester: number;
    academic_year: number;
    school_year: string;
    student: StudentDetails;
    subjects: EnrollmentSubject[];
    tuition: {
        discount: number;
        downpayment: number;
    } | null;
    additional_fees: Array<{
        fee_name: string;
        amount: number;
    }>;
}

interface CreateEnrollmentProps {
    user: User;
    settings: {
        currentSemester: number;
        currentSchoolYear: string;
        availableSemesters: { value: number; label: string }[];
        availableAcademicYears: Record<string, string>;
    };
    enrollment?: EnrollmentData;
    flash?: {
        success?: string;
        error?: string;
    };
}

interface Branding {
    currency: string;
}

// Generate unique ID
function generateId(): string {
    return Math.random().toString(36).substring(2, 9);
}

// Time helper
function getMinutesFromTime(timeStr: string): number {
    if (!timeStr) return 0;

    // Handle ISO string or full datetime
    if (timeStr.includes("T")) {
        const date = new Date(timeStr);
        if (!isNaN(date.getTime())) {
            return date.getHours() * 60 + date.getMinutes();
        }
    }

    const [h, m] = timeStr.split(":").map(Number);
    if (isNaN(h) || isNaN(m)) return 0;
    return h * 60 + m;
}

const START_HOUR = 7; // 7 AM
const END_HOUR = 21; // 9 PM
const TOTAL_MINUTES = (END_HOUR - START_HOUR) * 60;
const DAYS = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"];
const COLORS = [
    "bg-blue-100 border-blue-300 text-blue-800 dark:bg-blue-900/30 dark:border-blue-700 dark:text-blue-300",
    "bg-green-100 border-green-300 text-green-800 dark:bg-green-900/30 dark:border-green-700 dark:text-green-300",
    "bg-purple-100 border-purple-300 text-purple-800 dark:bg-purple-900/30 dark:border-purple-700 dark:text-purple-300",
    "bg-orange-100 border-orange-300 text-orange-800 dark:bg-orange-900/30 dark:border-orange-700 dark:text-orange-300",
    "bg-pink-100 border-pink-300 text-pink-800 dark:bg-pink-900/30 dark:border-pink-700 dark:text-pink-300",
    "bg-teal-100 border-teal-300 text-teal-800 dark:bg-teal-900/30 dark:border-teal-700 dark:text-teal-300",
    "bg-indigo-100 border-indigo-300 text-indigo-800 dark:bg-indigo-900/30 dark:border-indigo-700 dark:text-indigo-300",
    "bg-red-100 border-red-300 text-red-800 dark:bg-red-900/30 dark:border-red-700 dark:text-red-300",
];

// Modular fee constant
const MODULAR_FEE_PER_SUBJECT = 2400;

export default function AdministratorEnrollmentCreate({ user, settings, enrollment, flash }: CreateEnrollmentProps) {
    const { props } = usePage<{ branding?: Branding }>();
    const currency = props.branding?.currency || "PHP";

    const formatCurrency = (amount: number) => {
        return new Intl.NumberFormat(currency === "USD" ? "en-US" : "en-PH", {
            style: "currency",
            currency: currency,
            minimumFractionDigits: 0,
            maximumFractionDigits: 0,
        }).format(amount);
    };

    const isEdit = Boolean(enrollment);
    // Student search state
    const [studentOpen, setStudentOpen] = useState(false);
    const [studentSearch, setStudentSearch] = useState("");
    const [studentOptions, setStudentOptions] = useState<StudentOption[]>([]);
    const [studentLoading, setStudentLoading] = useState(false);
    const [selectedStudent, setSelectedStudent] = useState<StudentDetails | null>(enrollment?.student ?? null);

    // Subject state
    const [subjectSearch, setSubjectSearch] = useState("");
    const [subjectOptions, setSubjectOptions] = useState<SubjectOption[]>([]);
    const [subjectLoading, setSubjectLoading] = useState(false);
    const [subjectsEnrolled, setSubjectsEnrolled] = useState<SubjectEnrollment[]>(() =>
        enrollment
            ? enrollment.subjects.map((subject) => ({
                  id: `existing-${subject.subject_id}`,
                  subject_id: subject.subject_id,
                  subject_code: subject.subject_code,
                  subject_title: subject.subject_title,
                  class_id: subject.class_id,
                  is_modular: subject.is_modular,
                  lecture_units: Number(subject.lecture_units) || 0,
                  laboratory_units: Number(subject.laboratory_units) || 0,
                  lecture_fee: Number(subject.lecture_fee) || 0,
                  laboratory_fee: Number(subject.laboratory_fee) || 0,
                  lec_per_unit: Number(subject.lec_per_unit) || 0,
                  lab_per_unit: Number(subject.lab_per_unit) || 0,
                  section_options: [],
                  loading_sections: subject.has_classes,
                  has_classes: subject.has_classes,
              }))
            : [],
    );

    // Form state
    const [semester, setSemester] = useState((enrollment?.semester ?? settings.currentSemester).toString());
    const [academicYear, setAcademicYear] = useState((enrollment?.academic_year ?? 1).toString());
    const [discount, setDiscount] = useState((enrollment?.tuition?.discount ?? 0).toString());
    const [downpayment, setDownpayment] = useState(Number(enrollment?.tuition?.downpayment ?? 3500) || 0);
    const [additionalFees, setAdditionalFees] = useState<AdditionalFee[]>(
        enrollment
            ? enrollment.additional_fees.map((fee) => ({
                  id: generateId(),
                  fee_name: fee.fee_name,
                  amount: Number(fee.amount) || 0,
              }))
            : [],
    );
    const [submitting, setSubmitting] = useState(false);

    // Refs for debouncing
    const studentSearchTimeout = useRef<ReturnType<typeof setTimeout> | null>(null);
    const subjectSearchTimeout = useRef<ReturnType<typeof setTimeout> | null>(null);

    // Search students
    const searchStudents = useCallback((search: string) => {
        if (studentSearchTimeout.current) {
            clearTimeout(studentSearchTimeout.current);
        }

        if (search.length < 2) {
            setStudentOptions([]);
            return;
        }

        studentSearchTimeout.current = setTimeout(async () => {
            setStudentLoading(true);
            try {
                const response = await fetch(route("administrators.enrollments.api.students") + `?search=${encodeURIComponent(search)}`);
                const data = await response.json();
                setStudentOptions(data);
            } catch (error) {
                console.error("Error searching students:", error);
                toast.error("Failed to search students");
            } finally {
                setStudentLoading(false);
            }
        }, 300);
    }, []);

    // Load subjects for course
    const loadSubjects = useCallback(async (courseId: number, search = "") => {
        if (subjectSearchTimeout.current) {
            clearTimeout(subjectSearchTimeout.current);
        }

        subjectSearchTimeout.current = setTimeout(async () => {
            setSubjectLoading(true);
            try {
                const params = new URLSearchParams({
                    course_id: courseId.toString(),
                    search,
                });
                const response = await fetch(route("administrators.enrollments.api.subjects") + `?${params}`);
                const data = await response.json();
                setSubjectOptions(data);
            } catch (error) {
                console.error("Error loading subjects:", error);
                toast.error("Failed to load subjects");
            } finally {
                setSubjectLoading(false);
            }
        }, 200);
    }, []);

    const fetchStudentDetails = useCallback(
        async (studentId: number | string, showToast = true) => {
            setStudentLoading(true);
            try {
                const response = await fetch(
                    route("administrators.enrollments.api.student-details") + `?student_id=${encodeURIComponent(String(studentId))}`,
                );
                const details: StudentDetails = await response.json();
                setSelectedStudent(details);

                if (details.academic_year) {
                    setAcademicYear(details.academic_year.toString());
                }

                setSubjectsEnrolled([]);
                setSubjectOptions([]);

                if (details.course_id) {
                    loadSubjects(details.course_id);
                }

                if (showToast) {
                    toast.success(`Selected ${details.full_name}`);
                }
            } catch (error) {
                console.error("Error fetching student details:", error);
                toast.error("Failed to load student details");
            } finally {
                setStudentLoading(false);
            }
        },
        [loadSubjects],
    );

    // Handle student selection
    const handleSelectStudent = async (student: StudentOption) => {
        setStudentOpen(false);
        setStudentSearch("");
        await fetchStudentDetails(student.id);
    };

    // Calculate fees for a subject
    const calculateFees = (subject: SubjectOption, isModular = false) => {
        const isNSTP = subject.code.toUpperCase().includes("NSTP");
        const totalUnits = subject.lecture + subject.laboratory;

        let lectureFee = subject.lecture ? totalUnits * subject.lec_per_unit : 0;
        if (isNSTP) {
            lectureFee *= 0.5;
        }

        let laboratoryFee = subject.laboratory ? 1 * subject.lab_per_unit : 0;
        if (isModular && subject.laboratory) {
            laboratoryFee = laboratoryFee / 2;
        }

        return {
            lecture_fee: Math.round(lectureFee * 100) / 100,
            laboratory_fee: Math.round(laboratoryFee * 100) / 100,
        };
    };

    const loadSectionsForSubject = useCallback(
        async (subjectId: number) => {
            if (!selectedStudent?.course_id) return;

            setSubjectsEnrolled((prev) => prev.map((s) => (s.subject_id === subjectId ? { ...s, loading_sections: true } : s)));

            try {
                const params = new URLSearchParams({
                    subject_id: subjectId.toString(),
                    course_id: selectedStudent.course_id.toString(),
                });
                const response = await fetch(route("administrators.enrollments.api.sections") + `?${params}`);
                const sections: SectionOption[] = await response.json();

                setSubjectsEnrolled((prev) =>
                    prev.map((s) => (s.subject_id === subjectId ? { ...s, section_options: sections, loading_sections: false } : s)),
                );
            } catch (error) {
                console.error("Error loading sections:", error);
                setSubjectsEnrolled((prev) => prev.map((s) => (s.subject_id === subjectId ? { ...s, loading_sections: false } : s)));
            }
        },
        [selectedStudent?.course_id],
    );

    // Toggle subject enrollment
    const toggleSubject = async (subject: SubjectOption) => {
        const isEnrolled = subjectsEnrolled.some((s) => s.subject_id === subject.id);

        if (isEnrolled) {
            // Remove subject
            setSubjectsEnrolled((prev) => prev.filter((s) => s.subject_id !== subject.id));
            return;
        }

        // Add subject
        const fees = calculateFees(subject, false);
        const newSubject: SubjectEnrollment = {
            id: generateId(),
            subject_id: subject.id,
            subject_code: subject.code,
            subject_title: subject.title,
            class_id: null,
            is_modular: false,
            lecture_units: subject.lecture,
            laboratory_units: subject.laboratory,
            lecture_fee: fees.lecture_fee,
            laboratory_fee: fees.laboratory_fee,
            lec_per_unit: subject.lec_per_unit,
            lab_per_unit: subject.lab_per_unit,
            section_options: [],
            loading_sections: subject.has_classes,
            has_classes: subject.has_classes,
        };

        setSubjectsEnrolled((prev) => [...prev, newSubject]);

        // Load sections if subject has classes
        if (subject.has_classes && selectedStudent?.course_id) {
            await loadSectionsForSubject(subject.id);
        }
    };

    // Update subject field
    const updateSubject = (subjectId: number, updates: Partial<SubjectEnrollment>) => {
        setSubjectsEnrolled((prev) => prev.map((s) => (s.subject_id === subjectId ? { ...s, ...updates } : s)));
    };

    // Toggle modular
    const toggleModular = (subjectId: number, isModular: boolean) => {
        const subject = subjectsEnrolled.find((s) => s.subject_id === subjectId);
        if (!subject) return;

        const subjectOption = subjectOptions.find((o) => o.id === subjectId);
        if (!subjectOption) {
            updateSubject(subjectId, { is_modular: isModular });
            return;
        }

        const fees = calculateFees(subjectOption, isModular);
        updateSubject(subjectId, {
            is_modular: isModular,
            lecture_fee: fees.lecture_fee,
            laboratory_fee: fees.laboratory_fee,
        });
    };

    // Remove subject
    const removeSubject = (subjectId: number) => {
        setSubjectsEnrolled((prev) => prev.filter((s) => s.subject_id !== subjectId));
    };

    // Calculate totals
    const totals = useMemo(() => {
        const totalLectures = subjectsEnrolled.reduce((sum, s) => sum + Number(s.lecture_fee || 0), 0);
        const totalLaboratory = subjectsEnrolled.reduce((sum, s) => sum + Number(s.laboratory_fee || 0), 0);
        const modularCount = subjectsEnrolled.filter((s) => s.is_modular).length;
        const totalModularFee = modularCount * MODULAR_FEE_PER_SUBJECT;

        const discountPercent = parseInt(discount) || 0;
        const discountedLectures = totalLectures * (1 - discountPercent / 100);
        const discountAmount = totalLectures - discountedLectures;

        const totalTuition = discountedLectures + totalLaboratory;
        const miscellaneous = Number(selectedStudent?.miscellaneous_fee ?? 3500) || 0;
        const totalAdditionalFees = additionalFees.reduce((sum, f) => sum + Number(f.amount || 0), 0);

        const overallTotal = totalTuition + miscellaneous + totalAdditionalFees + totalModularFee;
        const balance = overallTotal - (downpayment || 0);

        const totalLectureUnits = subjectsEnrolled.reduce((sum, s) => sum + Number(s.lecture_units || 0), 0);
        const totalLabUnits = subjectsEnrolled.reduce((sum, s) => sum + Number(s.laboratory_units || 0), 0);

        return {
            subjectsCount: subjectsEnrolled.length,
            totalLectureUnits,
            totalLabUnits,
            totalUnits: totalLectureUnits + totalLabUnits,
            totalLectures,
            discountedLectures,
            discountAmount,
            totalLaboratory,
            totalModularFee,
            modularCount,
            totalTuition,
            miscellaneous,
            totalAdditionalFees,
            overallTotal,
            balance,
        };
    }, [subjectsEnrolled, discount, downpayment, selectedStudent, additionalFees]);

    // Filter subjects based on search
    const filteredSubjects = useMemo(() => {
        if (!subjectSearch.trim()) return subjectOptions;
        const query = subjectSearch.toLowerCase();
        return subjectOptions.filter((s) => s.code.toLowerCase().includes(query) || s.title.toLowerCase().includes(query));
    }, [subjectOptions, subjectSearch]);

    // Submit form
    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (!selectedStudent) {
            toast.error("Please select a student");
            return;
        }

        if (subjectsEnrolled.length === 0) {
            toast.error("Please add at least one subject");
            return;
        }

        setSubmitting(true);

        const subjects = subjectsEnrolled.map((s) => ({
            subject_id: s.subject_id,
            class_id: s.class_id,
            is_modular: s.is_modular,
            lecture_fee: s.lecture_fee,
            laboratory_fee: s.laboratory_fee,
            enrolled_lecture_units: s.lecture_units,
            enrolled_laboratory_units: s.laboratory_units,
        }));

        const fees = additionalFees.filter((f) => f.fee_name && f.amount > 0).map((f) => ({ fee_name: f.fee_name, amount: f.amount }));

        const payload = {
            student_id: selectedStudent.id.toString(),
            semester: parseInt(semester),
            academic_year: parseInt(academicYear),
            subjects,
            discount: parseInt(discount),
            downpayment,
            additional_fees: fees,
        };

        const options = {
            onSuccess: () => {
                toast.success(isEdit ? "Enrollment updated successfully!" : "Enrollment created successfully!");
            },
            onError: (errors: Record<string, string>) => {
                const firstError = Object.values(errors)[0];
                toast.error(typeof firstError === "string" ? firstError : "Failed to save enrollment");
            },
            onFinish: () => setSubmitting(false),
        };

        if (isEdit) {
            router.put(route("administrators.enrollments.update", enrollment?.id), payload, options);
        } else {
            router.post(route("administrators.enrollments.store"), payload, options);
        }
    };

    useEffect(() => {
        if (!selectedStudent?.course_id || !enrollment) return;

        enrollment.subjects.forEach((subject) => {
            if (subject.has_classes) {
                loadSectionsForSubject(subject.subject_id);
            }
        });
    }, [enrollment, loadSectionsForSubject, selectedStudent?.course_id]);

    useEffect(() => {
        if (isEdit || selectedStudent) return;
        const studentIdParam = new URLSearchParams(window.location.search).get("student_id");
        if (!studentIdParam) return;

        fetchStudentDetails(studentIdParam, false);
    }, [fetchStudentDetails, isEdit, selectedStudent]);

    // Effect: Load subjects when search changes
    useEffect(() => {
        if (selectedStudent?.course_id) {
            loadSubjects(selectedStudent.course_id, subjectSearch);
        }
    }, [subjectSearch, selectedStudent?.course_id, loadSubjects]);

    // Discount options
    const discountOptions = [
        { value: "0", label: "No Discount" },
        ...Array.from({ length: 20 }, (_, i) => ({
            value: ((i + 1) * 5).toString(),
            label: `${(i + 1) * 5}%`,
        })),
    ];

    const pageTitle = isEdit ? "Edit Enrollment" : "Create Enrollment";
    const displaySchoolYear = enrollment?.school_year ?? settings.currentSchoolYear;
    const displaySemester = enrollment?.semester ?? settings.currentSemester;

    return (
        <AdminLayout user={user} title={pageTitle}>
            <Head title={pageTitle} />

            <form onSubmit={handleSubmit}>
                <div className="flex flex-col gap-6 lg:flex-row">
                    {/* Main Content */}
                    <div className="flex-1 space-y-6">
                        {/* Header */}
                        <div className="flex items-center justify-between">
                            <div>
                                <h1 className="text-2xl font-bold tracking-tight">{pageTitle}</h1>
                                <p className="text-muted-foreground">
                                    {displaySchoolYear} - Semester {displaySemester}
                                </p>
                            </div>
                            <Button variant="outline" asChild>
                                <Link href={route("administrators.enrollments.index")}>
                                    <ArrowLeft className="mr-2 h-4 w-4" />
                                    Back
                                </Link>
                            </Button>
                        </div>

                        {/* Flash Messages */}
                        {flash?.error && (
                            <div className="bg-destructive/10 border-destructive/50 text-destructive flex items-center gap-2 rounded-lg border px-4 py-3">
                                <AlertTriangle className="h-4 w-4" />
                                {flash.error}
                            </div>
                        )}

                        {/* Step 1: Student Selection */}
                        <Card>
                            <CardHeader className="pb-3">
                                <div className="flex items-center gap-2">
                                    <div className="bg-primary text-primary-foreground flex h-8 w-8 items-center justify-center rounded-full text-sm font-bold">
                                        1
                                    </div>
                                    <div>
                                        <CardTitle className="text-lg">{isEdit ? "Student" : "Select Student"}</CardTitle>
                                        <CardDescription>
                                            {isEdit ? "Enrollment student details" : "Search and select the student to enroll"}
                                        </CardDescription>
                                    </div>
                                </div>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                {isEdit ? (
                                    <div className="flex items-center gap-3 rounded-lg border px-4 py-3">
                                        <div className="bg-primary/10 text-primary flex h-10 w-10 items-center justify-center rounded-full">
                                            <UserIcon className="h-5 w-5" />
                                        </div>
                                        <div>
                                            <div className="font-medium">{selectedStudent?.full_name ?? "Student not found"}</div>
                                            <div className="text-muted-foreground text-xs">
                                                {selectedStudent?.course_code ?? ""}
                                                {selectedStudent?.formatted_academic_year ? ` | ${selectedStudent.formatted_academic_year}` : ""}
                                            </div>
                                        </div>
                                    </div>
                                ) : (
                                    <Popover open={studentOpen} onOpenChange={setStudentOpen}>
                                        <PopoverTrigger asChild>
                                            <Button
                                                variant="outline"
                                                role="combobox"
                                                aria-expanded={studentOpen}
                                                className="h-12 w-full justify-between text-left font-normal"
                                            >
                                                {selectedStudent ? (
                                                    <div className="flex items-center gap-3">
                                                        <div className="bg-primary/10 text-primary flex h-8 w-8 items-center justify-center rounded-full">
                                                            <UserIcon className="h-4 w-4" />
                                                        </div>
                                                        <div>
                                                            <div className="font-medium">{selectedStudent.full_name}</div>
                                                            <div className="text-muted-foreground text-xs">
                                                                {selectedStudent.course_code} | {selectedStudent.formatted_academic_year}
                                                            </div>
                                                        </div>
                                                    </div>
                                                ) : (
                                                    <span className="text-muted-foreground">Search for a student...</span>
                                                )}
                                                <ChevronsUpDown className="ml-2 h-4 w-4 shrink-0 opacity-50" />
                                            </Button>
                                        </PopoverTrigger>
                                        <PopoverContent className="w-[var(--radix-popover-trigger-width)] p-0" align="start">
                                            <Command shouldFilter={false}>
                                                <CommandInput
                                                    placeholder="Type ID, name, or course..."
                                                    value={studentSearch}
                                                    onValueChange={(value) => {
                                                        setStudentSearch(value);
                                                        searchStudents(value);
                                                    }}
                                                />
                                                <CommandList>
                                                    {studentLoading ? (
                                                        <div className="flex items-center justify-center py-6">
                                                            <Loader2 className="text-muted-foreground h-5 w-5 animate-spin" />
                                                        </div>
                                                    ) : studentOptions.length === 0 ? (
                                                        <CommandEmpty>
                                                            {studentSearch.length < 2 ? "Type at least 2 characters..." : "No students found."}
                                                        </CommandEmpty>
                                                    ) : (
                                                        <CommandGroup>
                                                            {studentOptions.map((student) => (
                                                                <CommandItem
                                                                    key={student.id}
                                                                    value={student.id.toString()}
                                                                    onSelect={() => handleSelectStudent(student)}
                                                                    className="cursor-pointer"
                                                                >
                                                                    <div className="flex w-full items-center gap-3">
                                                                        <div className="bg-muted text-muted-foreground flex h-8 w-8 items-center justify-center rounded-full text-xs font-medium">
                                                                            {student.full_name.charAt(0)}
                                                                        </div>
                                                                        <div className="min-w-0 flex-1">
                                                                            <div className="truncate font-medium">{student.full_name}</div>
                                                                            <div className="text-muted-foreground text-xs">
                                                                                ID: {student.id} | {student.course_code || "No Course"}
                                                                            </div>
                                                                        </div>
                                                                        {selectedStudent?.id === student.id && (
                                                                            <Check className="text-primary h-4 w-4" />
                                                                        )}
                                                                    </div>
                                                                </CommandItem>
                                                            ))}
                                                        </CommandGroup>
                                                    )}
                                                </CommandList>
                                            </Command>
                                        </PopoverContent>
                                    </Popover>
                                )}

                                {/* Enrollment Options */}
                                {selectedStudent && (
                                    <div className="grid grid-cols-2 gap-4 pt-2">
                                        <div className="space-y-2">
                                            <Label>Semester</Label>
                                            <Select value={semester} onValueChange={setSemester}>
                                                <SelectTrigger>
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="1">1st Semester</SelectItem>
                                                    <SelectItem value="2">2nd Semester</SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </div>
                                        <div className="space-y-2">
                                            <Label>Year Level</Label>
                                            <Select value={academicYear} onValueChange={setAcademicYear}>
                                                <SelectTrigger>
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {Object.entries(settings.availableAcademicYears).map(([value, label]) => (
                                                        <SelectItem key={value} value={value}>
                                                            {label}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        </div>
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        {/* Step 2: Subject Selection */}
                        {selectedStudent && (
                            <Card>
                                <CardHeader className="pb-3">
                                    <div className="flex items-center justify-between">
                                        <div className="flex items-center gap-2">
                                            <div className="bg-primary text-primary-foreground flex h-8 w-8 items-center justify-center rounded-full text-sm font-bold">
                                                2
                                            </div>
                                            <div>
                                                <CardTitle className="text-lg">Select Subjects</CardTitle>
                                                <CardDescription>
                                                    Click subjects to add/remove. <Star className="inline h-3 w-3 fill-yellow-500 text-yellow-500" />{" "}
                                                    = has classes
                                                </CardDescription>
                                            </div>
                                        </div>
                                        <Badge variant="secondary" className="text-sm">
                                            {subjectsEnrolled.length} selected
                                        </Badge>
                                    </div>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    {/* Search */}
                                    <div className="relative">
                                        <Search className="text-muted-foreground absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2" />
                                        <Input
                                            placeholder="Filter subjects..."
                                            value={subjectSearch}
                                            onChange={(e) => setSubjectSearch(e.target.value)}
                                            className="pl-10"
                                        />
                                        {subjectLoading && <Loader2 className="absolute top-1/2 right-3 h-4 w-4 -translate-y-1/2 animate-spin" />}
                                    </div>

                                    {/* Subject Grid */}
                                    <ScrollArea className="h-[300px] rounded-md border">
                                        {filteredSubjects.length === 0 ? (
                                            <div className="text-muted-foreground flex h-full flex-col items-center justify-center py-8">
                                                <BookOpen className="mb-2 h-8 w-8 opacity-50" />
                                                <p>No subjects found</p>
                                            </div>
                                        ) : (
                                            <div className="grid gap-1 p-2">
                                                {filteredSubjects.map((subject) => {
                                                    const isEnrolled = subjectsEnrolled.some((s) => s.subject_id === subject.id);
                                                    return (
                                                        <button
                                                            key={subject.id}
                                                            type="button"
                                                            onClick={() => toggleSubject(subject)}
                                                            className={cn(
                                                                "flex w-full items-center gap-3 rounded-lg p-3 text-left transition-all",
                                                                "hover:bg-accent",
                                                                isEnrolled && "bg-primary/10 border-primary/30 border",
                                                            )}
                                                        >
                                                            <div
                                                                className={cn(
                                                                    "flex h-5 w-5 items-center justify-center rounded border transition-colors",
                                                                    isEnrolled
                                                                        ? "bg-primary border-primary text-primary-foreground"
                                                                        : "border-muted-foreground/30",
                                                                )}
                                                            >
                                                                {isEnrolled && <Check className="h-3 w-3" />}
                                                            </div>

                                                            <div className="min-w-0 flex-1">
                                                                <div className="flex items-center gap-2">
                                                                    <span className="font-medium">{subject.code}</span>
                                                                    {subject.has_classes ? (
                                                                        <Star className="h-3.5 w-3.5 fill-yellow-500 text-yellow-500" />
                                                                    ) : (
                                                                        <AlertTriangle className="h-3.5 w-3.5 text-orange-500" />
                                                                    )}
                                                                </div>
                                                                <div className="text-muted-foreground truncate text-sm">{subject.title}</div>
                                                            </div>

                                                            <div className="text-muted-foreground text-right text-xs">
                                                                <div>
                                                                    {subject.lecture}L / {subject.laboratory}Lab
                                                                </div>
                                                                <div className="text-foreground font-medium">
                                                                    {subject.lecture + subject.laboratory} units
                                                                </div>
                                                            </div>
                                                        </button>
                                                    );
                                                })}
                                            </div>
                                        )}
                                    </ScrollArea>
                                </CardContent>
                            </Card>
                        )}

                        {/* Step 3: Configure Subjects */}
                        {subjectsEnrolled.length > 0 && (
                            <Card>
                                <CardHeader className="pb-3">
                                    <div className="flex items-center gap-2">
                                        <div className="bg-primary text-primary-foreground flex h-8 w-8 items-center justify-center rounded-full text-sm font-bold">
                                            3
                                        </div>
                                        <div>
                                            <CardTitle className="text-lg">Configure Subjects</CardTitle>
                                            <CardDescription>Select sections and adjust settings</CardDescription>
                                        </div>
                                    </div>
                                </CardHeader>
                                <CardContent>
                                    <div className="overflow-x-auto rounded-md border">
                                        <Table className="min-w-[960px]">
                                            <TableHeader>
                                                <TableRow>
                                                    <TableHead>Subject</TableHead>
                                                    <TableHead className="text-right">Lec Units</TableHead>
                                                    <TableHead className="text-right">Lab Units</TableHead>
                                                    <TableHead className="text-right">Lec Fee</TableHead>
                                                    <TableHead className="text-right">Lab Fee</TableHead>
                                                    <TableHead className="min-w-[200px]">Section</TableHead>
                                                    <TableHead className="min-w-[130px]">Modular</TableHead>
                                                    <TableHead className="text-right">Actions</TableHead>
                                                </TableRow>
                                            </TableHeader>
                                            <TableBody>
                                                {subjectsEnrolled.map((subject) => (
                                                    <TableRow key={subject.id}>
                                                        <TableCell>
                                                            <div className="space-y-1">
                                                                <div className="flex items-center gap-2">
                                                                    <span className="font-medium">{subject.subject_code}</span>
                                                                    {subject.is_modular && (
                                                                        <Badge variant="secondary" className="text-xs">
                                                                            Modular
                                                                        </Badge>
                                                                    )}
                                                                </div>
                                                                <div className="text-muted-foreground text-xs">{subject.subject_title}</div>
                                                            </div>
                                                        </TableCell>
                                                        <TableCell className="text-right">{subject.lecture_units}</TableCell>
                                                        <TableCell className="text-right">{subject.laboratory_units}</TableCell>
                                                        <TableCell className="text-right">
                                                            {formatCurrency(Number(subject.lecture_fee || 0))}
                                                        </TableCell>
                                                        <TableCell className="text-right">
                                                            {formatCurrency(Number(subject.laboratory_fee || 0))}
                                                        </TableCell>
                                                        <TableCell>
                                                            {subject.loading_sections ? (
                                                                <div className="text-muted-foreground flex items-center gap-2 text-sm">
                                                                    <Loader2 className="h-4 w-4 animate-spin" />
                                                                    Loading...
                                                                </div>
                                                            ) : subject.section_options.length > 0 ? (
                                                                <Select
                                                                    value={subject.class_id?.toString() ?? "none"}
                                                                    onValueChange={(val) =>
                                                                        updateSubject(subject.subject_id, {
                                                                            class_id: val === "none" ? null : parseInt(val),
                                                                        })
                                                                    }
                                                                >
                                                                    <SelectTrigger className="h-9 w-[190px]">
                                                                        <SelectValue placeholder="Select section" />
                                                                    </SelectTrigger>
                                                                    <SelectContent>
                                                                        <SelectItem value="none">No section</SelectItem>
                                                                        {subject.section_options.map((section) => (
                                                                            <SelectItem
                                                                                key={section.id}
                                                                                value={section.id.toString()}
                                                                                disabled={section.is_full}
                                                                            >
                                                                                {section.label}
                                                                            </SelectItem>
                                                                        ))}
                                                                    </SelectContent>
                                                                </Select>
                                                            ) : !subject.has_classes ? (
                                                                <span className="text-xs text-orange-600">No classes</span>
                                                            ) : (
                                                                <span className="text-muted-foreground text-xs">Not available</span>
                                                            )}
                                                        </TableCell>
                                                        <TableCell>
                                                            <div className="flex items-center gap-2">
                                                                <Switch
                                                                    id={`modular-${subject.id}`}
                                                                    checked={subject.is_modular}
                                                                    onCheckedChange={(checked) => toggleModular(subject.subject_id, checked)}
                                                                />
                                                                <Label htmlFor={`modular-${subject.id}`} className="cursor-pointer text-xs">
                                                                    Modular
                                                                </Label>
                                                            </div>
                                                        </TableCell>
                                                        <TableCell className="text-right">
                                                            <Button
                                                                type="button"
                                                                variant="ghost"
                                                                size="icon"
                                                                className="text-muted-foreground hover:text-destructive h-8 w-8"
                                                                onClick={() => removeSubject(subject.subject_id)}
                                                            >
                                                                <X className="h-4 w-4" />
                                                            </Button>
                                                        </TableCell>
                                                    </TableRow>
                                                ))}
                                            </TableBody>
                                        </Table>
                                    </div>
                                </CardContent>
                            </Card>
                        )}

                        {/* Schedule Matrix */}
                        {subjectsEnrolled.some((s) => s.class_id) && (
                            <Collapsible defaultOpen>
                                <Card>
                                    <CardHeader className="pb-3">
                                        <div className="flex items-center justify-between">
                                            <div className="flex items-center gap-2">
                                                <div className="bg-primary text-primary-foreground flex h-8 w-8 items-center justify-center rounded-full text-sm font-bold">
                                                    <Calendar className="h-4 w-4" />
                                                </div>
                                                <div>
                                                    <CardTitle className="text-lg">Class Schedule</CardTitle>
                                                    <CardDescription>Schedule matrix of enrolled subjects</CardDescription>
                                                </div>
                                            </div>
                                            <CollapsibleTrigger asChild>
                                                <Button variant="ghost" size="sm" className="w-9 p-0">
                                                    <ChevronDown className="h-4 w-4" />
                                                    <span className="sr-only">Toggle</span>
                                                </Button>
                                            </CollapsibleTrigger>
                                        </div>
                                    </CardHeader>
                                    <CollapsibleContent>
                                        <CardContent>
                                            <div className="overflow-x-auto rounded-md border">
                                                <div className="flex h-[600px] min-w-[800px]">
                                                    {/* Time Column */}
                                                    <div className="bg-muted/30 w-16 flex-none border-r">
                                                        <div className="bg-muted/50 h-8 border-b"></div> {/* Header spacer */}
                                                        {Array.from({ length: END_HOUR - START_HOUR }).map((_, i) => (
                                                            <div
                                                                key={i}
                                                                className="text-muted-foreground relative h-[calc(100%/(14))] border-b p-1 text-right text-[10px]"
                                                            >
                                                                <span className="relative -top-2">
                                                                    {(START_HOUR + i) % 12 || 12} {START_HOUR + i >= 12 ? "PM" : "AM"}
                                                                </span>
                                                            </div>
                                                        ))}
                                                    </div>

                                                    {/* Days Columns */}
                                                    <div className="grid h-full flex-1 grid-cols-6 divide-x">
                                                        {DAYS.map((day) => (
                                                            <div key={day} className="relative flex h-full min-w-[100px] flex-col">
                                                                {/* Header */}
                                                                <div className="bg-muted/50 sticky top-0 z-10 flex h-8 flex-none items-center justify-center border-b text-xs font-medium">
                                                                    {day.slice(0, 3)}
                                                                </div>

                                                                {/* Grid Body - Relative Container for Absolutes */}
                                                                <div className="relative flex-1">
                                                                    {/* Grid Lines */}
                                                                    {Array.from({
                                                                        length: END_HOUR - START_HOUR,
                                                                    }).map((_, i) => (
                                                                        <div
                                                                            key={i}
                                                                            className="border-muted/50 h-[calc(100%/(14))] border-b border-dashed"
                                                                        ></div>
                                                                    ))}

                                                                    {/* Events */}
                                                                    {subjectsEnrolled
                                                                        .filter((s) => s.class_id)
                                                                        .map((subject, index) => {
                                                                            const section = subject.section_options.find(
                                                                                (opt) => opt.id === subject.class_id,
                                                                            );
                                                                            if (!section?.schedules) return null;

                                                                            return section.schedules
                                                                                .filter((sched) => sched.day.toLowerCase() === day.toLowerCase())
                                                                                .map((sched, schedIndex) => {
                                                                                    const startMin =
                                                                                        getMinutesFromTime(sched.start_time) - START_HOUR * 60;
                                                                                    const endMin =
                                                                                        getMinutesFromTime(sched.end_time) - START_HOUR * 60;
                                                                                    const top = (startMin / TOTAL_MINUTES) * 100;
                                                                                    const height = ((endMin - startMin) / TOTAL_MINUTES) * 100;

                                                                                    return (
                                                                                        <div
                                                                                            key={`${subject.id}-${schedIndex}`}
                                                                                            className={cn(
                                                                                                "absolute left-[3%] w-[94%] cursor-help overflow-hidden rounded border p-1 text-[10px] leading-tight shadow-sm transition-all hover:z-20 hover:scale-[1.02]",
                                                                                                COLORS[index % COLORS.length],
                                                                                            )}
                                                                                            style={{
                                                                                                top: `${top}%`,
                                                                                                height: `${height}%`,
                                                                                            }}
                                                                                            title={`${subject.subject_code}\n${sched.start_time} - ${sched.end_time}\n${sched.room || "TBA"}`}
                                                                                        >
                                                                                            <div className="truncate font-bold">
                                                                                                {subject.subject_code}
                                                                                            </div>
                                                                                            <div className="truncate opacity-90">
                                                                                                {sched.room || "TBA"}
                                                                                            </div>
                                                                                            <div className="truncate text-[9px] opacity-75">
                                                                                                {section.section}
                                                                                            </div>
                                                                                        </div>
                                                                                    );
                                                                                });
                                                                        })}
                                                                </div>
                                                            </div>
                                                        ))}
                                                    </div>
                                                </div>
                                            </div>
                                        </CardContent>
                                    </CollapsibleContent>
                                </Card>
                            </Collapsible>
                        )}

                        {/* Additional Fees */}
                        {selectedStudent && (
                            <Card>
                                <CardHeader className="pb-3">
                                    <div className="flex items-center justify-between">
                                        <div className="flex items-center gap-2">
                                            <div className="bg-muted text-muted-foreground flex h-8 w-8 items-center justify-center rounded-full text-sm font-bold">
                                                +
                                            </div>
                                            <div>
                                                <CardTitle className="text-lg">Additional Fees</CardTitle>
                                                <CardDescription>Optional extra charges</CardDescription>
                                            </div>
                                        </div>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            onClick={() => setAdditionalFees((prev) => [...prev, { id: generateId(), fee_name: "", amount: 0 }])}
                                        >
                                            <Plus className="mr-1 h-4 w-4" />
                                            Add Fee
                                        </Button>
                                    </div>
                                </CardHeader>
                                {additionalFees.length > 0 && (
                                    <CardContent>
                                        <div className="space-y-2">
                                            {additionalFees.map((fee) => (
                                                <div key={fee.id} className="flex gap-2">
                                                    <Input
                                                        placeholder="Fee name"
                                                        value={fee.fee_name}
                                                        onChange={(e) =>
                                                            setAdditionalFees((prev) =>
                                                                prev.map((f) => (f.id === fee.id ? { ...f, fee_name: e.target.value } : f)),
                                                            )
                                                        }
                                                        className="flex-1"
                                                    />
                                                    <Input
                                                        type="number"
                                                        placeholder="Amount"
                                                        value={fee.amount || ""}
                                                        onChange={(e) =>
                                                            setAdditionalFees((prev) =>
                                                                prev.map((f) =>
                                                                    f.id === fee.id ? { ...f, amount: parseFloat(e.target.value) || 0 } : f,
                                                                ),
                                                            )
                                                        }
                                                        className="w-32"
                                                    />
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="icon"
                                                        onClick={() => setAdditionalFees((prev) => prev.filter((f) => f.id !== fee.id))}
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                    </Button>
                                                </div>
                                            ))}
                                        </div>
                                    </CardContent>
                                )}
                            </Card>
                        )}
                    </div>

                    {/* Sidebar - Assessment Summary */}
                    <div className="lg:w-80">
                        <div className="space-y-4 lg:sticky lg:top-4">
                            <Card>
                                <CardHeader className="pb-3">
                                    <div className="flex items-center gap-2">
                                        <Calculator className="text-primary h-5 w-5" />
                                        <CardTitle className="text-lg">Assessment</CardTitle>
                                    </div>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    {/* Units Summary */}
                                    <div className="grid grid-cols-3 gap-2 text-center">
                                        <div className="rounded-lg bg-blue-50 p-2 dark:bg-blue-950/30">
                                            <div className="text-lg font-bold text-blue-600">{totals.subjectsCount}</div>
                                            <div className="text-muted-foreground text-[10px]">Subjects</div>
                                        </div>
                                        <div className="rounded-lg bg-green-50 p-2 dark:bg-green-950/30">
                                            <div className="text-lg font-bold text-green-600">{totals.totalLectureUnits}</div>
                                            <div className="text-muted-foreground text-[10px]">Lecture</div>
                                        </div>
                                        <div className="rounded-lg bg-purple-50 p-2 dark:bg-purple-950/30">
                                            <div className="text-lg font-bold text-purple-600">{totals.totalLabUnits}</div>
                                            <div className="text-muted-foreground text-[10px]">Lab</div>
                                        </div>
                                    </div>

                                    <Separator />

                                    {/* Discount */}
                                    <div className="space-y-2">
                                        <Label className="text-xs">Discount</Label>
                                        <Select value={discount} onValueChange={setDiscount}>
                                            <SelectTrigger className="h-9">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {discountOptions.map((opt) => (
                                                    <SelectItem key={opt.value} value={opt.value}>
                                                        {opt.label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>

                                    {/* Fees Breakdown */}
                                    <div className="space-y-2 text-sm">
                                        <div className="flex justify-between">
                                            <span className="text-muted-foreground">Lecture Fees</span>
                                            <span>{formatCurrency(totals.totalLectures)}</span>
                                        </div>
                                        {totals.discountAmount > 0 && (
                                            <div className="flex justify-between text-green-600">
                                                <span>Discount (-{discount}%)</span>
                                                <span>-{formatCurrency(totals.discountAmount)}</span>
                                            </div>
                                        )}
                                        <div className="flex justify-between">
                                            <span className="text-muted-foreground">Laboratory Fees</span>
                                            <span>{formatCurrency(totals.totalLaboratory)}</span>
                                        </div>
                                        {totals.modularCount > 0 && (
                                            <div className="flex justify-between">
                                                <span className="text-muted-foreground">Modular ({totals.modularCount})</span>
                                                <span>{formatCurrency(totals.totalModularFee)}</span>
                                            </div>
                                        )}
                                        <div className="flex justify-between">
                                            <span className="text-muted-foreground">Miscellaneous</span>
                                            <span>{formatCurrency(totals.miscellaneous)}</span>
                                        </div>
                                        {totals.totalAdditionalFees > 0 && (
                                            <div className="flex justify-between">
                                                <span className="text-muted-foreground">Additional</span>
                                                <span>{formatCurrency(totals.totalAdditionalFees)}</span>
                                            </div>
                                        )}
                                    </div>

                                    <Separator />

                                    {/* Total */}
                                    <div className="flex justify-between text-lg font-bold">
                                        <span>Total</span>
                                        <span className="text-primary">{formatCurrency(totals.overallTotal)}</span>
                                    </div>

                                    {/* Downpayment */}
                                    <div className="space-y-2">
                                        <Label className="text-xs">Downpayment</Label>
                                        <Input
                                            type="number"
                                            min={500}
                                            step={100}
                                            value={downpayment}
                                            onChange={(e) => setDownpayment(parseFloat(e.target.value) || 0)}
                                            className="h-9"
                                        />
                                    </div>

                                    {/* Balance */}
                                    <div
                                        className={cn(
                                            "flex justify-between rounded-lg p-3 font-bold",
                                            totals.balance > 0
                                                ? "bg-orange-50 text-orange-700 dark:bg-orange-950/30 dark:text-orange-400"
                                                : "bg-green-50 text-green-700 dark:bg-green-950/30 dark:text-green-400",
                                        )}
                                    >
                                        <span>Balance</span>
                                        <span>{formatCurrency(totals.balance)}</span>
                                    </div>

                                    {/* Submit Button */}
                                    <Button
                                        type="submit"
                                        className="w-full"
                                        size="lg"
                                        disabled={submitting || !selectedStudent || subjectsEnrolled.length === 0}
                                    >
                                        {submitting ? (
                                            <>
                                                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                                {isEdit ? "Saving..." : "Creating..."}
                                            </>
                                        ) : (
                                            <>
                                                <CheckCircle2 className="mr-2 h-4 w-4" />
                                                {isEdit ? "Save Changes" : "Create Enrollment"}
                                            </>
                                        )}
                                    </Button>
                                </CardContent>
                            </Card>
                        </div>
                    </div>
                </div>
            </form>
        </AdminLayout>
    );
}
