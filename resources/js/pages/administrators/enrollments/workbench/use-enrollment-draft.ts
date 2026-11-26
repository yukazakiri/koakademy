import { store as storeDiscount } from "@/actions/App/Http/Controllers/AdministratorEnrollmentDiscountController";
import {
    calculateSubjectFees,
    getStudentDetails,
    getSubjectSections,
    searchStudents,
    searchSubjects,
    store as storeEnrollmentAction,
    updateEnrollment as updateEnrollmentAction,
} from "@/actions/App/Http/Controllers/AdministratorEnrollmentManagementController";
import { router } from "@inertiajs/react";
import axios from "axios";
import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import { toast } from "sonner";
import type { StudentHoldItem } from "../types";
import type {
    BillingConfiguration,
    DiscountOption,
    DraftAdditionalFee,
    DraftDiffSummary,
    DraftSubjectEnrollment,
    EnrollmentTotals,
    EnrollmentWorkbenchPageProps,
    PendingOverloadConfirmation,
    ScheduleConflict,
    SectionOption,
    SectionSchedule,
    StudentDetails,
    StudentOption,
    SubjectOption,
    WaitlistBadgeItem,
} from "./types";

const CUSTOM_LEGACY_DISCOUNT_VALUE = "__legacy__";

function parseTimeToMinutes(timeStr: string): number {
    if (!timeStr) return -1;
    const clean = timeStr.trim().toUpperCase();
    const match12 = clean.match(/^(\d{1,2}):(\d{2})\s*(AM|PM)$/);
    if (match12) {
        let h = parseInt(match12[1], 10);
        const m = parseInt(match12[2], 10);
        if (match12[3] === "PM" && h < 12) h += 12;
        if (match12[3] === "AM" && h === 12) h = 0;
        return h * 60 + m;
    }
    const match24 = clean.match(/^(\d{1,2}):(\d{2})/);
    if (match24) {
        return parseInt(match24[1], 10) * 60 + parseInt(match24[2], 10);
    }
    return -1;
}

function normalizeDay(day: string): string {
    const key = day.trim().toLowerCase().slice(0, 3);
    const map: Record<string, string> = {
        mon: "Monday",
        tue: "Tuesday",
        wed: "Wednesday",
        thu: "Thursday",
        fri: "Friday",
        sat: "Saturday",
        sun: "Sunday",
    };
    return map[key] ?? day.trim();
}

function formatMinutes(minutes: number): string {
    const h24 = Math.floor(minutes / 60);
    const m = minutes % 60;
    const period = h24 >= 12 ? "PM" : "AM";
    const h12 = h24 % 12 === 0 ? 12 : h24 % 12;
    return `${h12}:${String(m).padStart(2, "0")} ${period}`;
}

export function schedulesOverlap(a: SectionSchedule, b: SectionSchedule): string | null {
    if (normalizeDay(a.day) !== normalizeDay(b.day)) return null;
    const startA = parseTimeToMinutes(a.start_time);
    const endA = parseTimeToMinutes(a.end_time);
    const startB = parseTimeToMinutes(b.start_time);
    const endB = parseTimeToMinutes(b.end_time);
    if (startA < 0 || endA <= startA || startB < 0 || endB <= startB) return null;
    const overlapStart = Math.max(startA, startB);
    const overlapEnd = Math.min(endA, endB);
    if (overlapStart < overlapEnd) {
        return `${normalizeDay(a.day).slice(0, 3)} ${formatMinutes(overlapStart)}–${formatMinutes(overlapEnd)}`;
    }
    return null;
}

export function useEnrollmentDraft(props: EnrollmentWorkbenchPageProps) {
    const {
        billingConfiguration,
        enrollment_period: enrollmentPeriod,
        active_holds: initialActiveHolds = [],
        waitlists = [],
        is_terminal_locked: isTerminalLocked = false,
        can_manage_tuition_fees: canManageTuitionFees = true,
        recommended_unit_cap: initialUnitCap = 24,
        settings,
        discounts,
        enrollment,
        flash,
    } = props;

    const isEdit = Boolean(enrollment);
    const initialSubjects = useMemo<DraftSubjectEnrollment[]>(
        () => enrollment?.subjects ?? [],
        [enrollment],
    );

    const [selectedStudent, setSelectedStudent] = useState<StudentDetails | null>(
        enrollment?.student ?? null,
    );
    const [activeBillingConfig, setActiveBillingConfig] =
        useState<BillingConfiguration>(billingConfiguration);
    const [studentSearch, setStudentSearch] = useState("");
    const [studentOptions, setStudentOptions] = useState<StudentOption[]>([]);
    const [loadingStudents, setLoadingStudents] = useState(false);

    const [semester, setSemester] = useState<string>(
        String(enrollment?.semester ?? settings.currentSemester),
    );
    const [academicYear, setAcademicYear] = useState<string>(
        String(enrollment?.academic_year ?? enrollment?.student?.academic_year ?? 1),
    );
    const schoolYear = enrollment?.school_year ?? settings.currentSchoolYear;

    const [subjectSearch, setSubjectSearch] = useState("");
    const [subjectOptions, setSubjectOptions] = useState<SubjectOption[]>([]);
    const [loadingSubjects, setLoadingSubjects] = useState(false);
    const [subjectsEnrolled, setSubjectsEnrolled] = useState<DraftSubjectEnrollment[]>(
        initialSubjects,
    );

    const [discountOptions, setDiscountOptions] = useState<DiscountOption[]>(discounts);
    const [discount, setDiscount] = useState<string>(
        String(enrollment?.tuition?.discount ?? 0),
    );
    const [discountId, setDiscountId] = useState<number | null>(
        enrollment?.tuition?.discount_id ?? null,
    );
    const [miscellaneousFee, setMiscellaneousFee] = useState<string>(
        String(
            enrollment?.tuition?.miscellaneous_fee ??
                enrollment?.student?.miscellaneous_fee ??
                3500,
        ),
    );
    const [downpayment, setDownpayment] = useState<string>(
        String(
            enrollment?.tuition?.downpayment ?? billingConfiguration.default_downpayment,
        ),
    );
    const [additionalFees, setAdditionalFees] = useState<DraftAdditionalFee[]>(
        enrollment?.additional_fees?.map((fee) => ({
            id: fee.id,
            fee_name: fee.fee_name,
            amount: String(fee.amount),
            is_separate_transaction: fee.is_separate_transaction,
            transaction_number: fee.transaction_number,
        })) ?? [],
    );

    const [remarks, setRemarks] = useState<string>(enrollment?.remarks ?? "");
    const [notifyStudent, setNotifyStudent] = useState(false);
    const [changeReason, setChangeReason] = useState("");
    const [overrideWindow, setOverrideWindow] = useState(false);
    const [studentHolds, setStudentHolds] = useState<StudentHoldItem[]>(initialActiveHolds);
    const [overrideHolds, setOverrideHolds] = useState(false);
    const [overrideHoldsReason, setOverrideHoldsReason] = useState("");
    const [confirmGradeLoss, setConfirmGradeLoss] = useState(false);

    const [submitting, setSubmitting] = useState(false);
    const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});
    const [pendingOverloadConfirmation, setPendingOverloadConfirmation] =
        useState<PendingOverloadConfirmation | null>(null);
    const [pendingGradeLossOpen, setPendingGradeLossOpen] = useState(false);

    // Inline discount creation dialog state
    const [discountDialogOpen, setDiscountDialogOpen] = useState(false);
    const [discountName, setDiscountName] = useState("");
    const [discountPercentage, setDiscountPercentage] = useState("");
    const [discountErrors, setDiscountErrors] = useState<{
        name?: string;
        percentage?: string;
    }>({});
    const [creatingDiscount, setCreatingDiscount] = useState(false);

    const initializedEditSections = useRef(false);

    useEffect(() => {
        if (flash?.success) toast.success(flash.success);
        if (flash?.error) toast.error(flash.error);
    }, [flash]);

    useEffect(() => {
        setDiscountOptions(discounts);
    }, [discounts]);

    const isTesdaPathway = selectedStudent?.curriculum_kind === "tesda_qualification";
    const unitCap = selectedStudent?.recommended_unit_cap ?? initialUnitCap;

    const waitlistBySubjectId = useMemo(() => {
        const map = new Map<number, WaitlistBadgeItem>();
        for (const w of waitlists) {
            map.set(w.subject_id, w);
        }
        return map;
    }, [waitlists]);

    // Fetch sections for a given subject scoped to the enrollment's exact term
    const fetchSectionsForSubject = useCallback(
        async (subjectId: number, courseId: number): Promise<SectionOption[]> => {
            try {
                const response = await axios.get<SectionOption[]>(
                    getSubjectSections.url({
                        query: {
                            subject_id: subjectId,
                            course_id: courseId,
                            school_year: schoolYear,
                            semester,
                        },
                    }),
                );
                return response.data;
            } catch (error) {
                console.error("Failed to fetch sections:", error);
                return [];
            }
        },
        [schoolYear, semester],
    );

    // Search students for Create mode
    useEffect(() => {
        if (isEdit) return;
        const timer = setTimeout(async () => {
            setLoadingStudents(true);
            try {
                const response = await axios.get<StudentOption[]>(
                    searchStudents.url({ query: { search: studentSearch } }),
                );
                setStudentOptions(response.data);
            } catch (error) {
                console.error("Failed to search students:", error);
            } finally {
                setLoadingStudents(false);
            }
        }, 250);
        return () => clearTimeout(timer);
    }, [studentSearch, isEdit]);

    // Fetch course subjects enriched with checklist progress & term classes
    const refreshCourseSubjects = useCallback(async () => {
        if (!selectedStudent?.course_id) {
            setSubjectOptions([]);
            return;
        }
        setLoadingSubjects(true);
        try {
            const response = await axios.get<SubjectOption[]>(
                searchSubjects.url({
                    query: {
                        course_id: selectedStudent.course_id,
                        student_id: selectedStudent.id,
                        school_year: schoolYear,
                        semester,
                        academic_year: academicYear,
                        search: subjectSearch,
                    },
                }),
            );
            setSubjectOptions(response.data);
        } catch (error) {
            console.error("Failed to load subjects:", error);
        } finally {
            setLoadingSubjects(false);
        }
    }, [selectedStudent?.course_id, selectedStudent?.id, schoolYear, semester, academicYear, subjectSearch]);

    useEffect(() => {
        const timer = setTimeout(() => {
            void refreshCourseSubjects();
        }, 200);
        return () => clearTimeout(timer);
    }, [refreshCourseSubjects]);

    // Hydrate sections for existing subjects in Edit mode (and whenever semester changes in Edit)
    useEffect(() => {
        if (!isEdit || !selectedStudent?.course_id || subjectsEnrolled.length === 0) return;
        if (initializedEditSections.current) return;
        initializedEditSections.current = true;

        const courseId = selectedStudent.course_id;
        subjectsEnrolled.forEach(async (sub) => {
            if (sub.sections && sub.sections.length > 0) return;
            const sections = await fetchSectionsForSubject(sub.subject_id, courseId);
            setSubjectsEnrolled((prev) =>
                prev.map((s) =>
                    s.subject_id === sub.subject_id
                        ? { ...s, sections, loading_sections: false }
                        : s,
                ),
            );
        });
    }, [isEdit, selectedStudent?.course_id, subjectsEnrolled, fetchSectionsForSubject]);

    // Reload billing config & duplicate check when student/semester changes in Create mode
    const handleSelectStudent = useCallback(
        async (student: StudentOption) => {
            setOverrideHolds(false);
            setOverrideHoldsReason("");
            setSubjectsEnrolled([]);
            setFieldErrors({});
            try {
                const response = await axios.get<StudentDetails>(
                    getStudentDetails.url({
                        query: {
                            student_id: student.id,
                            semester,
                            school_year: schoolYear,
                        },
                    }),
                );
                const details = response.data;
                setSelectedStudent(details);
                setStudentHolds(details.active_holds ?? []);
                if (details.billing_configuration) {
                    setActiveBillingConfig(details.billing_configuration);
                    setDownpayment(String(details.billing_configuration.default_downpayment));
                }
                setMiscellaneousFee(String(details.miscellaneous_fee ?? 3500));
                if (details.academic_year) {
                    setAcademicYear(String(details.academic_year));
                }
            } catch (error) {
                console.error("Failed to fetch student details:", error);
                toast.error("Failed to load student details.");
            }
        },
        [semester, schoolYear],
    );

    // Re-check duplicate enrollment / billing when semester changes in Create mode
    useEffect(() => {
        if (isEdit || !selectedStudent?.id) return;
        let cancelled = false;
        axios
            .get<StudentDetails>(
                getStudentDetails.url({
                    query: {
                        student_id: selectedStudent.id,
                        semester,
                        school_year: schoolYear,
                    },
                }),
            )
            .then((response) => {
                if (cancelled) return;
                setSelectedStudent((prev) =>
                    prev && prev.id === response.data.id ? response.data : prev,
                );
                setStudentHolds(response.data.active_holds ?? []);
                if (response.data.billing_configuration) {
                    setActiveBillingConfig(response.data.billing_configuration);
                }
            })
            .catch(() => {});
        return () => {
            cancelled = true;
        };
    }, [isEdit, selectedStudent?.id, semester, schoolYear]);

    const handleAddSubject = useCallback(
        async (subject: SubjectOption) => {
            if (!selectedStudent?.course_id) return;
            if (subjectsEnrolled.some((s) => s.subject_id === subject.id)) {
                toast.info(`${subject.code} is already in this enrollment.`);
                return;
            }

            const newEntry: DraftSubjectEnrollment = {
                subject_id: subject.id,
                subject_code: subject.code,
                subject_title: subject.title,
                class_id: null,
                is_modular: false,
                exclude_from_tuition: false,
                lecture_units: subject.lecture,
                laboratory_units: subject.laboratory,
                lecture_fee: 0,
                laboratory_fee: 0,
                lec_per_unit: subject.lec_per_unit,
                lab_per_unit: subject.lab_per_unit,
                has_classes: subject.has_classes,
                prerequisite_satisfied: subject.prerequisite_satisfied ?? true,
                missing_prerequisite: subject.missing_prerequisite ?? null,
                already_passed: subject.already_passed ?? false,
                is_failed_retake: subject.is_failed_retake ?? false,
                sections: [],
                loading_sections: true,
                calculating_fees: true,
            };

            setSubjectsEnrolled((prev) => [...prev, newEntry]);

            const [sections, feesResult] = await Promise.all([
                fetchSectionsForSubject(subject.id, selectedStudent.course_id),
                axios
                    .get<{ lecture_fee: number; laboratory_fee: number }>(
                        calculateSubjectFees.url({
                            query: {
                                subject_id: subject.id,
                                is_modular: false,
                                ...(isEdit && enrollment
                                    ? { enrollment_id: enrollment.id }
                                    : {
                                          student_id: selectedStudent.id,
                                          semester,
                                          school_year: schoolYear,
                                      }),
                            },
                        }),
                    )
                    .then((r) => r.data)
                    .catch(() => ({ lecture_fee: 0, laboratory_fee: 0 })),
            ]);

            setSubjectsEnrolled((prev) =>
                prev.map((s) =>
                    s.subject_id === subject.id
                        ? {
                              ...s,
                              sections,
                              loading_sections: false,
                              lecture_fee: feesResult.lecture_fee,
                              laboratory_fee: feesResult.laboratory_fee,
                              calculating_fees: false,
                          }
                        : s,
                ),
            );
        },
        [
            selectedStudent?.course_id,
            selectedStudent?.id,
            subjectsEnrolled,
            fetchSectionsForSubject,
            isEdit,
            enrollment,
            semester,
            schoolYear,
        ],
    );

    const handleLoadRecommendedBlock = useCallback(async () => {
        const recommended = subjectOptions.filter(
            (s) =>
                s.recommended &&
                !subjectsEnrolled.some((enrolled) => enrolled.subject_id === s.id),
        );
        if (recommended.length === 0) {
            toast.info("All recommended curriculum subjects for this term are already loaded.");
            return;
        }
        for (const sub of recommended) {
            await handleAddSubject(sub);
        }
        toast.success(`Added ${recommended.length} recommended curriculum subject(s).`);
    }, [subjectOptions, subjectsEnrolled, handleAddSubject]);

    const handleRemoveSubject = useCallback((subjectId: number) => {
        setSubjectsEnrolled((prev) => prev.filter((s) => s.subject_id !== subjectId));
    }, []);

    const handleToggleModular = useCallback(
        async (subjectId: number, checked: boolean) => {
            setSubjectsEnrolled((prev) =>
                prev.map((s) =>
                    s.subject_id === subjectId
                        ? { ...s, is_modular: checked, calculating_fees: true }
                        : s,
                ),
            );
            try {
                const response = await axios.get<{
                    lecture_fee: number;
                    laboratory_fee: number;
                }>(
                    calculateSubjectFees.url({
                        query: {
                            subject_id: subjectId,
                            is_modular: checked,
                            ...(isEdit && enrollment
                                ? { enrollment_id: enrollment.id }
                                : selectedStudent
                                  ? {
                                        student_id: selectedStudent.id,
                                        semester,
                                        school_year: schoolYear,
                                    }
                                  : {}),
                        },
                    }),
                );
                setSubjectsEnrolled((prev) =>
                    prev.map((s) =>
                        s.subject_id === subjectId
                            ? {
                                  ...s,
                                  lecture_fee: response.data.lecture_fee,
                                  laboratory_fee: response.data.laboratory_fee,
                                  calculating_fees: false,
                              }
                            : s,
                    ),
                );
            } catch {
                setSubjectsEnrolled((prev) =>
                    prev.map((s) =>
                        s.subject_id === subjectId ? { ...s, calculating_fees: false } : s,
                    ),
                );
            }
        },
        [isEdit, enrollment, selectedStudent, semester, schoolYear],
    );

    const handleToggleExclude = useCallback((subjectId: number, checked: boolean) => {
        setSubjectsEnrolled((prev) =>
            prev.map((s) =>
                s.subject_id === subjectId ? { ...s, exclude_from_tuition: checked } : s,
            ),
        );
    }, []);

    const handleSelectSection = useCallback((subjectId: number, classId: string) => {
        const numericId = classId === "none" ? null : parseInt(classId, 10);
        setSubjectsEnrolled((prev) =>
            prev.map((s) => {
                if (s.subject_id !== subjectId) return s;
                const matched = s.sections?.find((sec) => sec.id === numericId);
                return {
                    ...s,
                    class_id: numericId,
                    class_section: matched?.section ?? null,
                };
            }),
        );
    }, []);

    const handleAddFee = useCallback(() => {
        setAdditionalFees((prev) => [...prev, { fee_name: "", amount: "" }]);
    }, []);

    const handleRemoveFee = useCallback((index: number) => {
        setAdditionalFees((prev) => {
            const target = prev[index];
            if (target?.is_separate_transaction || target?.transaction_number) {
                toast.warning("Receipted separate-transaction fees are protected and cannot be removed.");
                return prev;
            }
            return prev.filter((_, i) => i !== index);
        });
    }, []);

    const handleUpdateFee = useCallback(
        (index: number, field: "fee_name" | "amount", value: string) => {
            setAdditionalFees((prev) =>
                prev.map((fee, i) => {
                    if (i !== index) return fee;
                    if (fee.is_separate_transaction || fee.transaction_number) return fee;
                    return { ...fee, [field]: value };
                }),
            );
        },
        [],
    );

    const selectedDiscountValue = useMemo(() => {
        if (discountId !== null) return String(discountId);
        if ((parseInt(discount, 10) || 0) > 0) return CUSTOM_LEGACY_DISCOUNT_VALUE;
        return "none";
    }, [discountId, discount]);

    const handleDiscountChange = useCallback(
        (value: string) => {
            if (value === "none") {
                setDiscountId(null);
                setDiscount("0");
                return;
            }
            if (value === CUSTOM_LEGACY_DISCOUNT_VALUE) return;
            const selected = discountOptions.find((o) => String(o.id) === value);
            if (!selected) return;
            setDiscountId(selected.id);
            setDiscount(String(selected.percentage));
        },
        [discountOptions],
    );

    const handleCreateDiscount = useCallback(
        async (event: React.FormEvent) => {
            event.preventDefault();
            setCreatingDiscount(true);
            setDiscountErrors({});
            try {
                const response = await axios.post<{ discount: DiscountOption }>(
                    storeDiscount.url(),
                    { name: discountName, percentage: discountPercentage },
                );
                const created = response.data.discount;
                setDiscountOptions((prev) =>
                    [...prev.filter((i) => i.id !== created.id), created].sort(
                        (a, b) => a.percentage - b.percentage || a.name.localeCompare(b.name),
                    ),
                );
                setDiscountId(created.id);
                setDiscount(String(created.percentage));
                setDiscountName("");
                setDiscountPercentage("");
                setDiscountDialogOpen(false);
                toast.success(`Discount "${created.name}" created and selected.`);
            } catch (error) {
                if (axios.isAxiosError(error) && error.response?.status === 422) {
                    const errors = (error.response.data?.errors ?? {}) as Record<
                        string,
                        string[]
                    >;
                    setDiscountErrors({
                        name: errors.name?.[0],
                        percentage: errors.percentage?.[0],
                    });
                } else {
                    toast.error("Failed to create discount.");
                }
            } finally {
                setCreatingDiscount(false);
            }
        },
        [discountName, discountPercentage],
    );

    // Live schedule conflicts across all currently assigned sections
    const scheduleConflicts = useMemo<ScheduleConflict[]>(() => {
        const assigned = subjectsEnrolled
            .map((sub) => {
                const sec = sub.sections?.find((s) => s.id === sub.class_id);
                return sec ? { sub, sec } : null;
            })
            .filter(Boolean) as { sub: DraftSubjectEnrollment; sec: SectionOption }[];

        const found: ScheduleConflict[] = [];
        for (let i = 0; i < assigned.length; i++) {
            for (let j = i + 1; j < assigned.length; j++) {
                const itemA = assigned[i];
                const itemB = assigned[j];
                for (const schedA of itemA.sec.schedules ?? []) {
                    for (const schedB of itemB.sec.schedules ?? []) {
                        const overlap = schedulesOverlap(schedA, schedB);
                        if (overlap) {
                            found.push({
                                subjectAId: itemA.sub.subject_id,
                                subjectACode: itemA.sub.subject_code,
                                subjectBId: itemB.sub.subject_id,
                                subjectBCode: itemB.sub.subject_code,
                                day: normalizeDay(schedA.day),
                                overlapWindow: overlap,
                            });
                        }
                    }
                }
            }
        }
        return found;
    }, [subjectsEnrolled]);

    const conflictingSubjectIds = useMemo(() => {
        const ids = new Set<number>();
        for (const c of scheduleConflicts) {
            ids.add(c.subjectAId);
            ids.add(c.subjectBId);
        }
        return ids;
    }, [scheduleConflicts]);

    const checkSectionConflictsWithDraft = useCallback(
        (subjectId: number, candidateSection: SectionOption): string[] => {
            const warnings: string[] = [];
            for (const other of subjectsEnrolled) {
                if (other.subject_id === subjectId || !other.class_id) continue;
                const otherSec = other.sections?.find((s) => s.id === other.class_id);
                if (!otherSec) continue;
                for (const schedA of candidateSection.schedules ?? []) {
                    for (const schedB of otherSec.schedules ?? []) {
                        const overlap = schedulesOverlap(schedA, schedB);
                        if (overlap) {
                            warnings.push(`${other.subject_code} (${overlap})`);
                        }
                    }
                }
            }
            return Array.from(new Set(warnings));
        },
        [subjectsEnrolled],
    );

    // Live tuition & unit totals
    const totals = useMemo<EnrollmentTotals>(() => {
        let lectureFees = 0;
        let labFees = 0;
        let modularFees = 0;
        let totalLectureUnits = 0;
        let totalLabUnits = 0;
        let excludedCount = 0;

        for (const sub of subjectsEnrolled) {
            totalLectureUnits += Number(sub.lecture_units) || 0;
            totalLabUnits += Number(sub.laboratory_units) || 0;
            if (sub.exclude_from_tuition) {
                excludedCount += 1;
                continue;
            }
            lectureFees += Number(sub.lecture_fee) || 0;
            labFees += Number(sub.laboratory_fee) || 0;
            if (sub.is_modular) {
                modularFees += activeBillingConfig.modular_fee;
            }
        }

        const discountPercent = parseInt(discount, 10) || 0;
        const discountScope = activeBillingConfig.discount_scope ?? "lecture_only";
        const lectureDiscountAmount = (lectureFees * discountPercent) / 100;
        const labDiscountAmount =
            discountScope === "lecture_and_laboratory"
                ? (labFees * discountPercent) / 100
                : 0;
        const discountAmount = lectureDiscountAmount + labDiscountAmount;
        const discountedLecture = lectureFees - lectureDiscountAmount;
        const discountedLab = labFees - labDiscountAmount;
        const totalTuition = discountedLecture + discountedLab + modularFees;
        const miscFee = parseFloat(miscellaneousFee) || 0;
        const additionalFeesTotal = additionalFees.reduce(
            (sum, fee) => sum + (parseFloat(fee.amount) || 0),
            0,
        );
        const overallTotal = totalTuition + miscFee + additionalFeesTotal;
        const downpaymentAmount = parseFloat(downpayment) || 0;
        const projectedBalance = Math.max(0, overallTotal - downpaymentAmount);

        return {
            lectureFees,
            labFees,
            modularFees,
            discountAmount,
            discountedLecture,
            discountedLab,
            totalTuition,
            miscFee,
            additionalFeesTotal,
            overallTotal,
            downpaymentAmount,
            projectedBalance,
            totalLectureUnits,
            totalLabUnits,
            totalUnits: totalLectureUnits + totalLabUnits,
            excludedCount,
        };
    }, [subjectsEnrolled, discount, miscellaneousFee, additionalFees, downpayment, activeBillingConfig]);

    // Live Before/After Diff Summary (for Edit Workbench & unsaved-changes guard)
    const diff = useMemo<DraftDiffSummary>(() => {
        const origById = new Map(initialSubjects.map((s) => [s.subject_id, s]));
        const currIds = new Set(subjectsEnrolled.map((s) => s.subject_id));

        const addedSubjectIds = subjectsEnrolled
            .filter((s) => !origById.has(s.subject_id))
            .map((s) => s.subject_id);

        const removedSubjects = initialSubjects.filter((s) => !currIds.has(s.subject_id));
        const gradedRemovals = removedSubjects.filter((s) => s.has_grades);

        const changedSectionSubjectIds = subjectsEnrolled
            .filter((s) => {
                const orig = origById.get(s.subject_id);
                return orig && (orig.class_id ?? null) !== (s.class_id ?? null);
            })
            .map((s) => s.subject_id);

        const initialUnits = initialSubjects.reduce(
            (sum, s) => sum + (Number(s.lecture_units) || 0) + (Number(s.laboratory_units) || 0),
            0,
        );
        const previousTotal =
            enrollment?.tuition?.overall_tuition ??
            (isEdit ? totals.overallTotal : 0);
        const tuitionDelta = Math.round((totals.overallTotal - previousTotal) * 100) / 100;
        const unitsDelta = totals.totalUnits - initialUnits;

        const totalPaid = enrollment?.tuition?.total_paid ?? 0;
        const projectedBalanceAfterPaid = Math.max(
            0,
            Math.round((totals.overallTotal - totalPaid) * 100) / 100,
        );
        const projectedOverpaymentCredit = Math.max(
            0,
            Math.round((totalPaid - totals.overallTotal) * 100) / 100,
        );

        const subjectFlagsChanged = subjectsEnrolled.some((s) => {
            const orig = origById.get(s.subject_id);
            if (!orig) return true;
            return (
                orig.is_modular !== s.is_modular ||
                orig.exclude_from_tuition !== s.exclude_from_tuition
            );
        });

        const metaChanged = isEdit
            ? String(enrollment?.semester) !== semester ||
              String(enrollment?.academic_year) !== academicYear ||
              String(enrollment?.tuition?.discount ?? 0) !== discount ||
              String(enrollment?.tuition?.miscellaneous_fee ?? 3500) !== miscellaneousFee ||
              String(enrollment?.tuition?.downpayment ?? 0) !== downpayment ||
              (enrollment?.remarks ?? "") !== remarks ||
              (enrollment?.additional_fees?.length ?? 0) !== additionalFees.length
            : Boolean(selectedStudent) || subjectsEnrolled.length > 0;

        const isDirty =
            addedSubjectIds.length > 0 ||
            removedSubjects.length > 0 ||
            changedSectionSubjectIds.length > 0 ||
            subjectFlagsChanged ||
            metaChanged;

        return {
            isDirty,
            addedSubjectIds,
            removedSubjects,
            changedSectionSubjectIds,
            gradedRemovals,
            previousTotal,
            tuitionDelta,
            unitsDelta,
            projectedBalanceAfterPaid,
            projectedOverpaymentCredit,
        };
    }, [
        initialSubjects,
        subjectsEnrolled,
        enrollment,
        isEdit,
        totals,
        semester,
        academicYear,
        discount,
        miscellaneousFee,
        downpayment,
        remarks,
        additionalFees.length,
        selectedStudent,
    ]);

    // Warn on browser tab close / reload when draft has unsaved changes
    useEffect(() => {
        if (!diff.isDirty || submitting) return;
        const handler = (e: BeforeUnloadEvent) => {
            e.preventDefault();
            e.returnValue = "";
        };
        window.addEventListener("beforeunload", handler);
        return () => window.removeEventListener("beforeunload", handler);
    }, [diff.isDirty, submitting]);

    const blockingHolds = useMemo(() => {
        return studentHolds.filter((hold) =>
            isEdit ? hold.blocks_add_drop : hold.blocks_enrollment,
        );
    }, [studentHolds, isEdit]);

    const subjectsModifiedInEdit =
        isEdit && (diff.addedSubjectIds.length > 0 || diff.removedSubjects.length > 0);

    const windowRequiresOverride = useMemo(() => {
        if (!enrollmentPeriod || !enrollmentPeriod.enforce_windows) return false;
        if (isEdit) {
            return subjectsModifiedInEdit && !enrollmentPeriod.add_drop_open;
        }
        return !enrollmentPeriod.can_enroll;
    }, [enrollmentPeriod, isEdit, subjectsModifiedInEdit]);

    const holdsRequireOverride = useMemo(() => {
        if (blockingHolds.length === 0) return false;
        return isEdit ? subjectsModifiedInEdit : true;
    }, [blockingHolds.length, isEdit, subjectsModifiedInEdit]);

    const isUnitOverload = totals.totalUnits > unitCap;

    const buildPayload = useCallback(
        (forceOverload: boolean, allowGradeLoss: boolean) => ({
            student_id: selectedStudent!.id,
            semester: parseInt(semester, 10),
            academic_year: parseInt(academicYear, 10),
            subjects: subjectsEnrolled.map((sub) => ({
                subject_id: sub.subject_id,
                class_id: sub.class_id,
                is_modular: sub.is_modular,
                exclude_from_tuition: sub.exclude_from_tuition,
                lecture_fee: Number(sub.lecture_fee) || 0,
                laboratory_fee: Number(sub.laboratory_fee) || 0,
                enrolled_lecture_units: Number(sub.lecture_units) || 0,
                enrolled_laboratory_units: Number(sub.laboratory_units) || 0,
            })),
            discount: parseInt(discount, 10) || 0,
            discount_id: discountId,
            miscellaneous_fee: parseFloat(miscellaneousFee) || 0,
            downpayment: parseFloat(downpayment) || 0,
            additional_fees: additionalFees
                .filter((f) => f.fee_name.trim() && (parseFloat(f.amount) || 0) >= 0)
                .map((f) => ({
                    ...(f.id ? { id: f.id } : {}),
                    fee_name: f.fee_name.trim(),
                    amount: parseFloat(f.amount) || 0,
                })),
            remarks: remarks.trim() || null,
            force_overload: forceOverload,
            override_window: overrideWindow,
            override_holds: overrideHolds,
            override_holds_reason: overrideHolds ? overrideHoldsReason.trim() : null,
            confirm_grade_loss: allowGradeLoss,
            override_unit_overload: isUnitOverload,
            ...(isEdit && {
                notify_student: notifyStudent,
                change_reason: changeReason,
            }),
        }),
        [
            selectedStudent,
            semester,
            academicYear,
            subjectsEnrolled,
            discount,
            discountId,
            miscellaneousFee,
            downpayment,
            additionalFees,
            remarks,
            overrideWindow,
            overrideHolds,
            overrideHoldsReason,
            isUnitOverload,
            isEdit,
            notifyStudent,
            changeReason,
        ],
    );

    const submitEnrollment = useCallback(
        (forceOverload = false, allowGradeLoss = confirmGradeLoss) => {
            if (!selectedStudent) return;
            setSubmitting(true);
            setFieldErrors({});
            const payload = buildPayload(forceOverload, allowGradeLoss);

            const options = {
                onFinish: () => {
                    setSubmitting(false);
                    setPendingOverloadConfirmation(null);
                    setPendingGradeLossOpen(false);
                },
                onError: (errors: Record<string, string>) => {
                    setFieldErrors(errors);
                    const firstError = Object.values(errors)[0];
                    toast.error(firstError || "Please fix the highlighted validation errors.");
                },
            };

            if (isEdit && enrollment) {
                router.put(updateEnrollmentAction.url(enrollment.id), payload, options);
            } else {
                router.post(storeEnrollmentAction.url(), payload, options);
            }
        },
        [selectedStudent, confirmGradeLoss, buildPayload, isEdit, enrollment],
    );

    const handleSubmit = useCallback(
        (event?: React.FormEvent) => {
            event?.preventDefault();
            if (isTerminalLocked) {
                toast.error("Cancelled or rejected enrollments cannot be edited directly.");
                return;
            }
            if (!selectedStudent) {
                toast.error("Please select a student first.");
                return;
            }
            if (!isEdit && selectedStudent.existing_enrollment) {
                toast.error("This student already has an active enrollment for the selected term.");
                return;
            }
            if (subjectsEnrolled.length === 0) {
                toast.error("Please add at least one subject.");
                return;
            }
            if (windowRequiresOverride && !overrideWindow) {
                toast.error(
                    isEdit
                        ? "Add/Drop period is closed. Enable window override to modify subjects."
                        : "Registration window is closed. Enable window override to proceed.",
                );
                return;
            }
            if (holdsRequireOverride) {
                if (!overrideHolds) {
                    toast.error("Resolve active blocking holds or enable an audited hold override.");
                    return;
                }
                if (overrideHoldsReason.trim().length < 5) {
                    toast.error("Provide an audited override reason (at least 5 characters).");
                    return;
                }
            }
            if (isEdit && diff.gradedRemovals.length > 0 && !confirmGradeLoss) {
                setPendingGradeLossOpen(true);
                return;
            }

            const fullSections = subjectsEnrolled
                .map((sub) => {
                    if (!sub.class_id || !sub.sections) return null;
                    const sec = sub.sections.find((s) => s.id === sub.class_id);
                    if (!sec || !sec.is_full) return null;
                    // In edit mode, ignore if the student was already in this exact section
                    const orig = initialSubjects.find((o) => o.subject_id === sub.subject_id);
                    if (isEdit && orig?.class_id === sub.class_id) return null;
                    return {
                        subjectCode: sub.subject_code,
                        subjectTitle: sub.subject_title,
                        section: sec.section,
                        enrolledCount: sec.enrolled_count,
                        maxSlots: sec.max_slots,
                    };
                })
                .filter(Boolean) as PendingOverloadConfirmation["fullSections"];

            if (fullSections.length > 0) {
                setPendingOverloadConfirmation({ fullSections });
                return;
            }

            submitEnrollment(false, confirmGradeLoss);
        },
        [
            isTerminalLocked,
            selectedStudent,
            isEdit,
            subjectsEnrolled,
            windowRequiresOverride,
            overrideWindow,
            holdsRequireOverride,
            overrideHolds,
            overrideHoldsReason,
            diff.gradedRemovals.length,
            confirmGradeLoss,
            initialSubjects,
            submitEnrollment,
        ],
    );

    const formatCurrency = useCallback(
        (amount: number) =>
            new Intl.NumberFormat("en-PH", {
                style: "currency",
                currency: "PHP",
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            }).format(amount),
        [],
    );

    return {
        isEdit,
        isTesdaPathway,
        isTerminalLocked,
        canManageTuitionFees,
        enrollment,
        enrollmentPeriod,
        schoolYear,
        settings,
        selectedStudent,
        studentSearch,
        setStudentSearch,
        studentOptions,
        loadingStudents,
        handleSelectStudent,
        semester,
        setSemester,
        academicYear,
        setAcademicYear,
        subjectSearch,
        setSubjectSearch,
        subjectOptions,
        loadingSubjects,
        subjectsEnrolled,
        handleAddSubject,
        handleLoadRecommendedBlock,
        handleRemoveSubject,
        handleToggleModular,
        handleToggleExclude,
        handleSelectSection,
        discountOptions,
        discount,
        discountId,
        selectedDiscountValue,
        handleDiscountChange,
        miscellaneousFee,
        setMiscellaneousFee,
        downpayment,
        setDownpayment,
        additionalFees,
        handleAddFee,
        handleRemoveFee,
        handleUpdateFee,
        remarks,
        setRemarks,
        notifyStudent,
        setNotifyStudent,
        changeReason,
        setChangeReason,
        overrideWindow,
        setOverrideWindow,
        studentHolds,
        blockingHolds,
        overrideHolds,
        setOverrideHolds,
        overrideHoldsReason,
        setOverrideHoldsReason,
        windowRequiresOverride,
        holdsRequireOverride,
        confirmGradeLoss,
        setConfirmGradeLoss,
        pendingGradeLossOpen,
        setPendingGradeLossOpen,
        waitlistBySubjectId,
        scheduleConflicts,
        conflictingSubjectIds,
        checkSectionConflictsWithDraft,
        totals,
        diff,
        unitCap,
        isUnitOverload,
        submitting,
        fieldErrors,
        pendingOverloadConfirmation,
        setPendingOverloadConfirmation,
        discountDialogOpen,
        setDiscountDialogOpen,
        discountName,
        setDiscountName,
        discountPercentage,
        setDiscountPercentage,
        discountErrors,
        creatingDiscount,
        handleCreateDiscount,
        handleSubmit,
        submitEnrollment,
        formatCurrency,
    };
}

export type EnrollmentDraftState = ReturnType<typeof useEnrollmentDraft>;
