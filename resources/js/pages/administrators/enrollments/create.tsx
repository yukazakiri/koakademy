import { store as storeDiscount } from "@/actions/App/Http/Controllers/AdministratorEnrollmentDiscountController";
import { calculateSubjectFees, getStudentDetails } from "@/actions/App/Http/Controllers/AdministratorEnrollmentManagementController";
import AdminLayout from "@/components/administrators/admin-layout";
import { Badge } from "@/components/ui/badge";
import { Button, buttonVariants } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Command, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList } from "@/components/ui/command";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { ScrollArea } from "@/components/ui/scroll-area";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Switch } from "@/components/ui/switch";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Textarea } from "@/components/ui/textarea";
import { cn } from "@/lib/utils";
import { Head, Link, router, usePage } from "@inertiajs/react";
import axios from "axios";
import {
    AlertTriangle,
    ArrowLeft,
    BookOpen,
    Check,
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
import { AssessmentSidebar } from "./create/assessment-sidebar";
import { CreateEnrollmentDialogs } from "./create/create-enrollment-dialogs";
import { SchedulePreviewCard } from "./create/schedule-preview-card";
import {
    generateId,
    type AdditionalFee,
    type Branding,
    type CreateEnrollmentProps,
    type DiscountOption,
    type PendingOverloadConfirmation,
    type SectionOption,
    type StudentDetails,
    type StudentOption,
    type SubjectEnrollment,
    type SubjectOption,
} from "./create/types";
import type { StudentHoldItem } from "./types";

export default function AdministratorEnrollmentCreate({
    user,
    settings,
    discounts,
    enrollment,
    flash,
    billingConfiguration: initialBillingConfiguration,
    enrollment_period: enrollmentPeriod,
}: CreateEnrollmentProps) {
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
    const [overrideWindow, setOverrideWindow] = useState(false);
    const [studentHolds, setStudentHolds] = useState<StudentHoldItem[]>([]);
    const [overrideHolds, setOverrideHolds] = useState(false);
    const [overrideHoldsReason, setOverrideHoldsReason] = useState("");
    const [billingConfiguration, setBillingConfiguration] = useState(initialBillingConfiguration);
    const [billingLoading, setBillingLoading] = useState(false);
    const [billingError, setBillingError] = useState(false);
    const pendingFeeQuotes = useRef(0);
    const feeQuoteVersions = useRef(new Map<number, number>());
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
                  exclude_from_tuition: subject.exclude_from_tuition,
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
    const [discountId, setDiscountId] = useState<number | null>(enrollment?.tuition?.discount_id ?? null);
    const [discountOptions, setDiscountOptions] = useState<DiscountOption[]>(discounts);
    const [miscellaneousFee, setMiscellaneousFee] = useState(
        Number(enrollment?.tuition?.miscellaneous_fee ?? enrollment?.student.miscellaneous_fee ?? 3500) || 0,
    );
    const [discountDialogOpen, setDiscountDialogOpen] = useState(false);
    const [discountName, setDiscountName] = useState("");
    const [discountPercentage, setDiscountPercentage] = useState("");
    const [discountErrors, setDiscountErrors] = useState<Record<string, string>>({});
    const [creatingDiscount, setCreatingDiscount] = useState(false);
    const [downpayment, setDownpayment] = useState(Number(enrollment?.tuition?.downpayment ?? initialBillingConfiguration.default_downpayment) || 0);
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
    const [notifyStudent, setNotifyStudent] = useState(false);
    const [changeReason, setChangeReason] = useState("");
    const [confirmedOverloadClassIds, setConfirmedOverloadClassIds] = useState<number[]>([]);
    const [pendingOverloadConfirmation, setPendingOverloadConfirmation] = useState<PendingOverloadConfirmation | null>(null);

    const feeContextKey = `${selectedStudent?.id}:${semester}:${enrollment?.school_year ?? settings.currentSchoolYear}`;
    const currentFeeContext = useRef(feeContextKey);
    currentFeeContext.current = feeContextKey;

    const isTesdaPathway = selectedStudent?.curriculum_kind === "tesda_qualification";
    const academicYearOptions = isTesdaPathway
        ? [
              ["1", "Training Level 1"],
              ["2", "Training Level 2"],
              ["3", "Training Level 3"],
              ["4", "Training Level 4"],
              ["5", "Training Level 5"],
          ]
        : Object.entries(settings.availableAcademicYears);

    useEffect(() => {
        if (isEdit || !selectedStudent) return;
        const controller = new AbortController();
        setSubjectsEnrolled([]);
        setBillingLoading(true);
        setBillingError(false);
        fetch(
            getStudentDetails.url({
                query: {
                    student_id: selectedStudent.id,
                    semester,
                    school_year: settings.currentSchoolYear,
                },
            }),
            { signal: controller.signal },
        )
            .then((response) => {
                if (!response.ok) throw new Error("Billing rules unavailable");
                return response.json();
            })
            .then((details) => {
                setBillingConfiguration(details.billing_configuration);
                setMiscellaneousFee(Number(details.miscellaneous_fee) || 0);
                setDownpayment(Number(details.billing_configuration.default_downpayment) || 0);
                setStudentHolds(Array.isArray(details.active_holds) ? details.active_holds : []);
                setOverrideHolds(false);
                setOverrideHoldsReason("");
            })
            .catch((error) => {
                if (error.name !== "AbortError") {
                    setBillingError(true);
                    toast.error("Could not load enrollment billing rules. Reload before saving.");
                }
            })
            .finally(() => {
                if (!controller.signal.aborted) setBillingLoading(false);
            });
        return () => controller.abort();
    }, [isEdit, selectedStudent?.id, semester, settings.currentSchoolYear]);

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
                setMiscellaneousFee(Number(details.miscellaneous_fee) || 0);

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
    const calculateFees = async (subjectId: number, isModular = false) => {
        const contextKey = feeContextKey;
        pendingFeeQuotes.current++;
        try {
            const response = await fetch(
                calculateSubjectFees.url({
                    query: {
                        subject_id: subjectId,
                        is_modular: isModular ? 1 : 0,
                        ...(enrollment ? { enrollment_id: enrollment.id } : { student_id: selectedStudent?.id }),
                        semester,
                        school_year: enrollment?.school_year ?? settings.currentSchoolYear,
                    },
                }),
            );
            if (!response.ok) throw new Error("Could not calculate subject fees");
            const fees = (await response.json()) as { lecture_fee: number; laboratory_fee: number };
            if (currentFeeContext.current !== contextKey) throw new Error("Enrollment context changed");
            return fees;
        } finally {
            pendingFeeQuotes.current--;
        }
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
        let fees;
        try {
            fees = await calculateFees(subject.id, false);
        } catch {
            toast.error("Could not calculate subject fees. Subject was not added.");
            return;
        }
        const newSubject: SubjectEnrollment = {
            id: generateId(),
            subject_id: subject.id,
            subject_code: subject.code,
            subject_title: subject.title,
            class_id: null,
            is_modular: false,
            exclude_from_tuition: false,
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

        setSubjectsEnrolled((prev) => (prev.some((row) => row.subject_id === subject.id) ? prev : [...prev, newSubject]));

        // Load sections if subject has classes
        if (subject.has_classes && selectedStudent?.course_id) {
            await loadSectionsForSubject(subject.id);
        }
    };

    // Update subject field
    const updateSubject = (subjectId: number, updates: Partial<SubjectEnrollment>) => {
        setSubjectsEnrolled((prev) => prev.map((s) => (s.subject_id === subjectId ? { ...s, ...updates } : s)));
    };

    const getSelectedFullSections = useCallback(() => {
        return subjectsEnrolled
            .map((subject) => {
                const section = subject.section_options.find((option) => option.id === subject.class_id);

                return section?.is_full
                    ? {
                          subject_code: subject.subject_code,
                          section,
                      }
                    : null;
            })
            .filter((section): section is { subject_code: string; section: SectionOption } => Boolean(section));
    }, [subjectsEnrolled]);

    const handleSectionChange = (subject: SubjectEnrollment, value: string | null) => {
        if (value === null) {
            return;
        }

        if (value === "none") {
            updateSubject(subject.subject_id, { class_id: null });
            return;
        }

        const classId = parseInt(value);
        const section = subject.section_options.find((option) => option.id === classId);

        if (section?.is_full && !confirmedOverloadClassIds.includes(classId)) {
            setPendingOverloadConfirmation({
                mode: "select",
                subjectId: subject.subject_id,
                classId,
                sections: [{ subject_code: subject.subject_code, section }],
            });
            return;
        }

        updateSubject(subject.subject_id, { class_id: classId });
    };

    // Toggle modular
    const toggleModular = async (subjectId: number, isModular: boolean) => {
        const subject = subjectsEnrolled.find((s) => s.subject_id === subjectId);
        if (!subject) return;

        const quoteVersion = (feeQuoteVersions.current.get(subjectId) ?? 0) + 1;
        feeQuoteVersions.current.set(subjectId, quoteVersion);
        let fees;
        try {
            fees = await calculateFees(subjectId, isModular);
            if (feeQuoteVersions.current.get(subjectId) !== quoteVersion) return;
        } catch {
            toast.error("Could not calculate subject fees. Modular status was not changed.");
            return;
        }
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
        const billableSubjects = subjectsEnrolled.filter((s) => !s.exclude_from_tuition);
        const totalLectures = billableSubjects.reduce((sum, s) => sum + Number(s.lecture_fee || 0), 0);
        const totalLaboratory = billableSubjects.reduce((sum, s) => sum + Number(s.laboratory_fee || 0), 0);
        const modularCount = billableSubjects.filter((s) => s.is_modular).length;
        const excludedSubjectsCount = subjectsEnrolled.length - billableSubjects.length;
        const totalModularFee = modularCount * billingConfiguration.modular_fee;

        const discountPercent = parseInt(discount) || 0;
        const lectureDiscount = Math.round(totalLectures * discountPercent) / 100;
        const labDiscount =
            billingConfiguration.discount_scope === "lecture_and_laboratory" ? Math.round(totalLaboratory * discountPercent) / 100 : 0;
        const discountedLectures = Math.round((totalLectures - lectureDiscount) * 100) / 100;
        const discountedLaboratory = Math.round((totalLaboratory - labDiscount) * 100) / 100;
        const discountAmount = lectureDiscount + labDiscount;

        const totalTuition = discountedLectures + discountedLaboratory;
        const miscellaneous = Number(miscellaneousFee) || 0;
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
            excludedSubjectsCount,
            totalTuition,
            miscellaneous,
            totalAdditionalFees,
            overallTotal,
            balance,
        };
    }, [
        subjectsEnrolled,
        discount,
        downpayment,
        miscellaneousFee,
        additionalFees,
        billingConfiguration.modular_fee,
        billingConfiguration.discount_scope,
    ]);

    // Filter subjects based on search
    const filteredSubjects = useMemo(() => {
        if (!subjectSearch.trim()) return subjectOptions;
        const query = subjectSearch.toLowerCase();
        return subjectOptions.filter((s) => s.code.toLowerCase().includes(query) || s.title.toLowerCase().includes(query));
    }, [subjectOptions, subjectSearch]);

    // Submit form
    const submitEnrollment = (forceOverload = false) => {
        if (billingLoading || billingError || pendingFeeQuotes.current > 0) {
            toast.error("Billing rules unavailable. Reload before saving.");
            return;
        }
        if (!selectedStudent) {
            toast.error("Please select a student");
            return;
        }

        if (subjectsEnrolled.length === 0) {
            toast.error("Please add at least one subject");
            return;
        }

        const selectedFullSections = getSelectedFullSections();
        const hasUnconfirmedFullSection = selectedFullSections.some(({ section }) => !confirmedOverloadClassIds.includes(section.id));

        if (!forceOverload && hasUnconfirmedFullSection) {
            setPendingOverloadConfirmation({
                mode: "submit",
                sections: selectedFullSections.filter(({ section }) => !confirmedOverloadClassIds.includes(section.id)),
            });
            return;
        }

        setSubmitting(true);

        const subjects = subjectsEnrolled.map((s) => ({
            subject_id: s.subject_id,
            class_id: s.class_id,
            is_modular: s.is_modular,
            exclude_from_tuition: s.exclude_from_tuition,
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
            discount_id: discountId,
            miscellaneous_fee: miscellaneousFee,
            downpayment,
            additional_fees: fees,
            force_overload: forceOverload || selectedFullSections.some(({ section }) => confirmedOverloadClassIds.includes(section.id)),
            override_window: overrideWindow,
            override_holds: overrideHolds,
            override_holds_reason: overrideHolds ? overrideHoldsReason.trim() : null,
            ...(isEdit && { notify_student: notifyStudent, change_reason: changeReason }),
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

    // Submit form
    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        submitEnrollment();
    };

    const confirmOverloadEnrollment = () => {
        if (!pendingOverloadConfirmation) return;

        const classIds = pendingOverloadConfirmation.sections.map(({ section }) => section.id);
        setConfirmedOverloadClassIds((prev) => Array.from(new Set([...prev, ...classIds])));

        if (pendingOverloadConfirmation.mode === "select" && pendingOverloadConfirmation.subjectId && pendingOverloadConfirmation.classId) {
            updateSubject(pendingOverloadConfirmation.subjectId, { class_id: pendingOverloadConfirmation.classId });
            setPendingOverloadConfirmation(null);
            return;
        }

        setPendingOverloadConfirmation(null);
        submitEnrollment(true);
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

    const selectedDiscountValue = discountId !== null ? discountId.toString() : parseInt(discount) > 0 ? "legacy" : "none";

    const handleDiscountChange = (value: string) => {
        if (value === "none") {
            setDiscountId(null);
            setDiscount("0");
            return;
        }

        if (value === "legacy") {
            return;
        }

        const selectedDiscount = discountOptions.find((option) => option.id === Number(value));
        if (!selectedDiscount) {
            return;
        }

        setDiscountId(selectedDiscount.id);
        setDiscount(selectedDiscount.percentage.toString());
    };

    const handleCreateDiscount = async (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        event.stopPropagation();
        setDiscountErrors({});
        setCreatingDiscount(true);

        try {
            const response = await axios.post<DiscountOption>(storeDiscount.url(), {
                name: discountName,
                percentage: Number(discountPercentage),
            });
            const createdDiscount = response.data;

            setDiscountOptions((current) =>
                [...current, createdDiscount].sort((left, right) => left.percentage - right.percentage || left.name.localeCompare(right.name)),
            );
            setDiscountId(createdDiscount.id);
            setDiscount(createdDiscount.percentage.toString());
            setDiscountDialogOpen(false);
            setDiscountName("");
            setDiscountPercentage("");
            toast.success(`${createdDiscount.name} was created and selected.`);
        } catch (error) {
            if (axios.isAxiosError(error)) {
                const errors = error.response?.data?.errors as Record<string, string[]> | undefined;
                if (errors) {
                    setDiscountErrors({
                        name: errors.name?.[0] ?? errors.normalized_name?.[0] ?? "",
                        percentage: errors.percentage?.[0] ?? "",
                    });
                } else {
                    toast.error("Failed to create discount");
                }
            } else {
                toast.error("Failed to create discount");
            }
        } finally {
            setCreatingDiscount(false);
        }
    };

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
                            <Link
                                href={route("administrators.enrollments.index")}
                                className={buttonVariants({ variant: "outline", className: "gap-2" })}
                            >
                                <ArrowLeft className="h-4 w-4" />
                                Back
                            </Link>
                        </div>

                        {/* Flash Messages */}
                        {flash?.error && (
                            <div className="bg-destructive/10 border-destructive/50 text-destructive flex items-center gap-2 rounded-lg border px-4 py-3">
                                <AlertTriangle className="h-4 w-4" />
                                {flash.error}
                            </div>
                        )}

                        {/* Registration Window Status Banner */}
                        {!isEdit && enrollmentPeriod?.configured && (!enrollmentPeriod.can_enroll || enrollmentPeriod.is_late) && (
                            <div
                                className={cn(
                                    "flex flex-col gap-3 rounded-lg border px-4 py-3 sm:flex-row sm:items-center sm:justify-between",
                                    !enrollmentPeriod.can_enroll
                                        ? "border-destructive/40 bg-destructive/10 text-destructive"
                                        : "border-amber-500/40 bg-amber-500/10 text-amber-800 dark:text-amber-300",
                                )}
                            >
                                <div className="flex items-start gap-2.5 text-sm">
                                    <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0" />
                                    <div>
                                        <p className="font-semibold">{enrollmentPeriod.phase_label}</p>
                                        <p className="text-xs opacity-90">
                                            {enrollmentPeriod.summary_text}
                                            {enrollmentPeriod.is_late && enrollmentPeriod.auto_apply_late_fee && enrollmentPeriod.late_fee_amount > 0
                                                ? ` · "${enrollmentPeriod.late_fee_label}" (${formatCurrency(enrollmentPeriod.late_fee_amount)}) will be automatically added upon submission.`
                                                : ""}
                                        </p>
                                    </div>
                                </div>
                                {!enrollmentPeriod.can_enroll && enrollmentPeriod.enforce_windows && (
                                    <label className="flex shrink-0 cursor-pointer items-center gap-2 rounded-md border border-current/25 bg-background/80 px-3 py-1.5 text-xs font-medium">
                                        <Switch checked={overrideWindow} onCheckedChange={setOverrideWindow} />
                                        Admin override
                                    </label>
                                )}
                            </div>
                        )}

                        {/* Active Student Holds Warning & Audited Override */}
                        {!isEdit && studentHolds.length > 0 && (
                            <div className="border-destructive/40 bg-destructive/5 space-y-3 rounded-lg border px-4 py-3">
                                <div className="flex items-start justify-between gap-3">
                                    <div className="flex items-start gap-2.5 text-sm">
                                        <AlertTriangle className="text-destructive mt-0.5 h-4 w-4 shrink-0" />
                                        <div>
                                            <p className="text-destructive font-semibold">
                                                Active Student Holds ({studentHolds.length})
                                            </p>
                                            <ul className="mt-1 space-y-1 text-xs">
                                                {studentHolds.map((hold) => (
                                                    <li key={hold.id}>
                                                        <span className="font-semibold">[{hold.office_label}]</span> {hold.title} —{" "}
                                                        <span className="text-muted-foreground">{hold.reason}</span>
                                                    </li>
                                                ))}
                                            </ul>
                                        </div>
                                    </div>
                                    {studentHolds.some((h) => h.severity === "blocking" && h.blocks_enrollment) && (
                                        <label className="bg-background/80 flex shrink-0 cursor-pointer items-center gap-2 rounded-md border px-3 py-1.5 text-xs font-medium">
                                            <Switch checked={overrideHolds} onCheckedChange={setOverrideHolds} />
                                            Audited override
                                        </label>
                                    )}
                                </div>
                                {overrideHolds && (
                                    <div className="space-y-1">
                                        <Label htmlFor="override-holds-reason" className="text-xs font-medium">
                                            Override audit justification (required)
                                        </Label>
                                        <Textarea
                                            id="override-holds-reason"
                                            rows={2}
                                            value={overrideHoldsReason}
                                            onChange={(e) => setOverrideHoldsReason(e.target.value)}
                                            placeholder="Enter promissory note reference or authorized clearance waiver..."
                                            className="bg-background text-xs"
                                        />
                                    </div>
                                )}
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
                                                {selectedStudent?.student_id ? `ID: ${selectedStudent.student_id}` : ""}
                                                {selectedStudent?.course_code ? ` | ${selectedStudent.course_code}` : ""}
                                                {selectedStudent?.curriculum_kind === "tesda_qualification" ? " | TESDA" : " | College"}
                                                {selectedStudent?.formatted_academic_year ? ` | ${selectedStudent.formatted_academic_year}` : ""}
                                            </div>
                                            {selectedStudent?.course_name && (
                                                <div className="text-muted-foreground mt-1 text-xs">
                                                    {selectedStudent.course_name}
                                                    {selectedStudent.qualification_level ? ` · ${selectedStudent.qualification_level}` : ""}
                                                    {selectedStudent.tesda_program_type === "diploma" ? " · Institutional Diploma" : ""}
                                                </div>
                                            )}
                                            {selectedStudent?.tesda_program_type === "diploma" && selectedStudent.internship_hours && (
                                                <div className="text-muted-foreground mt-1 text-xs">
                                                    {selectedStudent.duration_hours
                                                        ? `${selectedStudent.duration_hours.toLocaleString()} total hours · `
                                                        : ""}
                                                    {selectedStudent.internship_hours.toLocaleString()} mandatory OJT hours
                                                </div>
                                            )}
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
                                                                {selectedStudent.course_code} |{" "}
                                                                {selectedStudent.curriculum_kind === "tesda_qualification" ? "TESDA" : "College"} |{" "}
                                                                {selectedStudent.formatted_academic_year}
                                                            </div>
                                                            {selectedStudent.course_name && (
                                                                <div className="text-muted-foreground mt-1 text-xs">
                                                                    {selectedStudent.course_name}
                                                                    {selectedStudent.qualification_level
                                                                        ? ` · ${selectedStudent.qualification_level}`
                                                                        : ""}
                                                                    {selectedStudent.tesda_program_type === "diploma"
                                                                        ? " · Institutional Diploma"
                                                                        : ""}
                                                                </div>
                                                            )}
                                                            {selectedStudent?.tesda_program_type === "diploma" &&
                                                                selectedStudent.internship_hours && (
                                                                    <div className="text-muted-foreground mt-1 text-xs">
                                                                        {selectedStudent.duration_hours
                                                                            ? `${selectedStudent.duration_hours.toLocaleString()} total hours · `
                                                                            : ""}
                                                                        {selectedStudent.internship_hours.toLocaleString()} mandatory OJT hours
                                                                    </div>
                                                                )}
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
                                                                                ID: {student.student_id ?? student.id} |{" "}
                                                                                {student.course_code || "No Course"}
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
                                            <Select value={semester} onValueChange={(value) => value && setSemester(value)}>
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
                                            <Label>{isTesdaPathway ? "Training Level" : "Year Level"}</Label>
                                            <Select value={academicYear} onValueChange={(value) => value && setAcademicYear(value)}>
                                                <SelectTrigger>
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {academicYearOptions.map(([value, label]) => (
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
                                                <CardTitle className="text-lg">
                                                    {isTesdaPathway ? "Select Training Modules" : "Select Subjects"}
                                                </CardTitle>
                                                <CardDescription>
                                                    Click {isTesdaPathway ? "modules" : "subjects"} to add/remove.{" "}
                                                    <Star className="inline h-3 w-3 fill-yellow-500 text-yellow-500" /> = has classes
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
                                        <Table className="min-w-[1120px]">
                                            <TableHeader>
                                                <TableRow>
                                                    <TableHead>Subject</TableHead>
                                                    <TableHead className="text-right">Lec Units</TableHead>
                                                    <TableHead className="text-right">Lab Units</TableHead>
                                                    <TableHead className="text-right">Lec Fee</TableHead>
                                                    <TableHead className="text-right">Lab Fee</TableHead>
                                                    <TableHead className="min-w-[200px]">Section</TableHead>
                                                    <TableHead className="min-w-[130px]">Modular</TableHead>
                                                    <TableHead className="min-w-[170px]">Tuition</TableHead>
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
                                                                    {subject.exclude_from_tuition && (
                                                                        <Badge variant="outline" className="text-xs">
                                                                            No tuition
                                                                        </Badge>
                                                                    )}
                                                                </div>
                                                                <div className="text-muted-foreground text-xs">{subject.subject_title}</div>
                                                            </div>
                                                        </TableCell>
                                                        <TableCell className="text-right">{subject.lecture_units}</TableCell>
                                                        <TableCell className="text-right">{subject.laboratory_units}</TableCell>
                                                        <TableCell className="text-right">
                                                            {subject.exclude_from_tuition ? "—" : formatCurrency(Number(subject.lecture_fee || 0))}
                                                        </TableCell>
                                                        <TableCell className="text-right">
                                                            {subject.exclude_from_tuition ? "—" : formatCurrency(Number(subject.laboratory_fee || 0))}
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
                                                                    onValueChange={(val) => handleSectionChange(subject, val)}
                                                                >
                                                                    <SelectTrigger className="h-9 w-[190px]">
                                                                        <SelectValue placeholder="Select section" />
                                                                    </SelectTrigger>
                                                                    <SelectContent>
                                                                        <SelectItem value="none">No section</SelectItem>
                                                                        {subject.section_options.map((section) => (
                                                                            <SelectItem key={section.id} value={section.id.toString()}>
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
                                                        <TableCell>
                                                            <div className="flex items-center gap-2">
                                                                <Switch
                                                                    id={`exclude-tuition-${subject.id}`}
                                                                    checked={subject.exclude_from_tuition}
                                                                    onCheckedChange={(checked) =>
                                                                        updateSubject(subject.subject_id, { exclude_from_tuition: checked })
                                                                    }
                                                                />
                                                                <Label htmlFor={`exclude-tuition-${subject.id}`} className="cursor-pointer text-xs">
                                                                    Exclude fees
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
                        <SchedulePreviewCard subjectsEnrolled={subjectsEnrolled} />

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
                    <AssessmentSidebar
                        isEdit={isEdit}
                        submitting={submitting}
                        selectedStudent={selectedStudent}
                        subjectsCount={subjectsEnrolled.length}
                        totals={totals}
                        discount={discount}
                        discountId={discountId}
                        selectedDiscountValue={selectedDiscountValue}
                        discountOptions={discountOptions}
                        onDiscountChange={handleDiscountChange}
                        onOpenCreateDiscount={() => setDiscountDialogOpen(true)}
                        miscellaneousFee={miscellaneousFee}
                        onMiscellaneousFeeChange={setMiscellaneousFee}
                        downpayment={downpayment}
                        onDownpaymentChange={setDownpayment}
                        notifyStudent={notifyStudent}
                        onNotifyStudentChange={setNotifyStudent}
                        changeReason={changeReason}
                        onChangeReasonChange={setChangeReason}
                        formatCurrency={formatCurrency}
                    />
                </div>
            </form>

            <CreateEnrollmentDialogs
                discountDialogOpen={discountDialogOpen}
                onDiscountDialogOpenChange={(open) => {
                    setDiscountDialogOpen(open);
                    if (!open) {
                        setDiscountErrors({});
                    }
                }}
                discountName={discountName}
                onDiscountNameChange={setDiscountName}
                discountPercentage={discountPercentage}
                onDiscountPercentageChange={setDiscountPercentage}
                discountErrors={discountErrors}
                creatingDiscount={creatingDiscount}
                onCreateDiscount={handleCreateDiscount}
                pendingOverloadConfirmation={pendingOverloadConfirmation}
                onDismissOverload={() => setPendingOverloadConfirmation(null)}
                onConfirmOverload={confirmOverloadEnrollment}
                submitting={submitting}
            />
        </AdminLayout>
    );
}
