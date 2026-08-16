import { generateBulkAssessments } from "@/actions/App/Http/Controllers/AdministratorEnrollmentManagementController";
import { ACTIVE_JOBS_REFRESH_EVENT } from "@/components/active-jobs-notification";
import AdminLayout from "@/components/administrators/admin-layout";
import { SemesterSelector, type SemesterSelectorProps } from "@/components/semester-selector";
import type { ComboboxOption } from "@/components/ui/combobox";
import { BulkReportsDialog } from "@/pages/administrators/enrollments/enrollment-dialogs";
import { ReportsSection, type RecentReportOutput } from "@/pages/administrators/enrollments/reports-section";
import type { BulkReportFilters, EnrollmentManagementProps, ReportFilters } from "@/pages/administrators/enrollments/types";
import {
    courseOptions as enrollmentReportCourseOptions,
    subjectOptions as enrollmentReportSubjectOptions,
    exportMethod as exportEnrollmentReport,
    previewPdf as previewEnrollmentReportPdf,
} from "@/routes/administrators/enrollments/reports";
import { Head } from "@inertiajs/react";
import axios from "axios";
import { Clock3 } from "lucide-react";
import { useCallback, useEffect, useMemo, useState } from "react";
import { toast } from "sonner";

type SubjectOption = {
    id: string | number;
    code: string;
    title: string;
    units: number;
    enrolled_count: number;
    class_count: number;
    sections: string[];
};

type CourseOption = {
    id: number;
    code: string;
    title: string;
    department: string;
};

type RegistrarReportsProps = {
    user: EnrollmentManagementProps["user"];
    filters: SemesterSelectorProps;
    assessment_export_options: EnrollmentManagementProps["assessment_export_options"];
};

const DEFAULT_REPORT_FILTERS: ReportFilters = {
    course_filter: "all",
    subject_filter: "all",
    department_filter: "all",
    year_level_filter: "all",
    status_filter: "active",
};

const REPORT_NAMES: Record<string, string> = {
    enrolled_by_course: "Master enrollment list",
    enrolled_by_subject: "Students by subject",
    enrollment_summary: "Enrollment summary",
};

export default function RegistrarReports({ user, filters, assessment_export_options }: RegistrarReportsProps) {
    const [activeReportCard, setActiveReportCard] = useState<string>("enrolled_by_course");
    const [reportFilters, setReportFilters] = useState<ReportFilters>(DEFAULT_REPORT_FILTERS);
    const [isLoadingReport, setIsLoadingReport] = useState(false);
    const [availableSubjects, setAvailableSubjects] = useState<SubjectOption[]>([]);
    const [availableCourses, setAvailableCourses] = useState<CourseOption[]>([]);
    const [isLoadingFilterOptions, setIsLoadingFilterOptions] = useState(false);
    const [isBulkReportsOpen, setIsBulkReportsOpen] = useState(false);
    const [bulkReportFilters, setBulkReportFilters] = useState<BulkReportFilters>({
        course_id: null,
        year_level: null,
        student_limit: null,
        include_deleted: false,
    });
    const [isGeneratingBulkReport, setIsGeneratingBulkReport] = useState(false);
    const [recentOutputs, setRecentOutputs] = useState<RecentReportOutput[]>([]);

    const addRecentOutput = useCallback((output: Omit<RecentReportOutput, "id" | "createdAt">) => {
        const createdAt = new Intl.DateTimeFormat(undefined, { hour: "numeric", minute: "2-digit" }).format(new Date());
        setRecentOutputs((current) => [{ ...output, id: `${Date.now()}-${output.format}`, createdAt }, ...current].slice(0, 6));
    }, []);

    const fetchReportFilterOptions = useCallback(async () => {
        setIsLoadingFilterOptions(true);
        try {
            const [subjectsResponse, coursesResponse] = await Promise.all([
                fetch(enrollmentReportSubjectOptions.url(), { headers: { Accept: "application/json" } }),
                fetch(enrollmentReportCourseOptions.url(), { headers: { Accept: "application/json" } }),
            ]);

            if (subjectsResponse.ok) {
                const data = (await subjectsResponse.json()) as { subjects?: SubjectOption[] };
                setAvailableSubjects(data.subjects ?? []);
            }

            if (coursesResponse.ok) {
                const data = (await coursesResponse.json()) as { courses?: CourseOption[] };
                setAvailableCourses(data.courses ?? []);
            }
        } catch (error) {
            console.error("Failed to fetch registrar report options:", error);
            toast.error("Some report filters could not be loaded.");
        } finally {
            setIsLoadingFilterOptions(false);
        }
    }, []);

    useEffect(() => {
        void fetchReportFilterOptions();
    }, [fetchReportFilterOptions]);

    const subjectComboboxOptions = useMemo<ComboboxOption[]>(
        () => [
            { label: "All Subjects", value: "all", description: "Include all subjects with active classes" },
            ...availableSubjects.map((subject) => ({
                label: `${subject.code} - ${subject.title}`,
                value: String(subject.id),
                description: `${subject.enrolled_count} enrolled · ${subject.class_count} class${subject.class_count === 1 ? "" : "es"}`,
                searchText: `${subject.code} ${subject.title} ${subject.sections.join(" ")}`,
            })),
        ],
        [availableSubjects],
    );

    const courseComboboxOptions = useMemo<ComboboxOption[]>(
        () => [
            { label: "All Courses", value: "all", description: "Include all courses" },
            ...availableCourses.map((course) => ({
                label: `${course.code} - ${course.title}`,
                value: course.code,
                description: course.department,
                searchText: `${course.code} ${course.title} ${course.department}`,
            })),
        ],
        [availableCourses],
    );

    const bulkCourseOptions = useMemo(
        () => availableCourses.map((course) => ({ id: course.id, code: course.code, title: course.title })),
        [availableCourses],
    );

    const handleReportCardClick = (type: string) => {
        setActiveReportCard(type);
        setReportFilters(DEFAULT_REPORT_FILTERS);
    };

    const handleGenerateReport = async () => {
        setIsLoadingReport(true);
        try {
            const query = new URLSearchParams({ report_type: activeReportCard, ...reportFilters });
            const response = await fetch(`${previewEnrollmentReportPdf.url()}?${query.toString()}`, {
                headers: { Accept: "application/json" },
            });
            const payload = (await response.json().catch(() => ({}))) as { message?: string; error?: string };

            if (!response.ok) {
                toast.error(payload.error || payload.message || "Failed to queue the PDF preview.");
                return;
            }

            toast.success(payload.message || "PDF preview queued. You will be notified when it is ready.");
            addRecentOutput({
                name: REPORT_NAMES[activeReportCard] ?? "Registrar report",
                format: "PDF",
                status: "Queued",
            });
        } catch (error) {
            console.error("Failed to queue registrar report:", error);
            toast.error("Failed to queue the PDF preview. Please try again.");
        } finally {
            setIsLoadingReport(false);
        }
    };

    const handleExportExcel = () => {
        const query = new URLSearchParams({ report_type: activeReportCard, ...reportFilters, format: "excel" });
        window.open(`${exportEnrollmentReport.url()}?${query.toString()}`, "_blank", "noopener");
        addRecentOutput({
            name: REPORT_NAMES[activeReportCard] ?? "Registrar report",
            format: "Excel",
            status: "Opened",
        });
    };

    const handleGenerateBulkAssessments = async () => {
        setIsGeneratingBulkReport(true);
        const toastId = "bulk-assessment-queue";
        toast.loading("Queueing bulk assessment export...", { id: toastId });

        try {
            const response = await axios.post<{ message: string }>(generateBulkAssessments.url(), bulkReportFilters);
            toast.success(response.data.message, { id: toastId });
            window.dispatchEvent(new Event(ACTIVE_JOBS_REFRESH_EVENT));
            setIsBulkReportsOpen(false);
            setBulkReportFilters({ course_id: null, year_level: null, student_limit: null, include_deleted: false });
            addRecentOutput({ name: "Bulk assessment export", format: "PDF bundle", status: "Queued" });
        } catch (error) {
            const message = axios.isAxiosError<{ message?: string }>(error) ? error.response?.data?.message : null;
            toast.error(message || "Failed to queue bulk assessment generation.", { id: toastId });
        } finally {
            setIsGeneratingBulkReport(false);
        }
    };

    return (
        <AdminLayout user={user} title="Registrar Reports">
            <Head title="Administrators • Registrar Reports" />

            <div className="space-y-6 pb-10">
                <header className="flex flex-col gap-4 border-b pb-6 sm:flex-row sm:items-end sm:justify-between">
                    <div className="space-y-1">
                        <p className="text-muted-foreground text-[11px] font-semibold tracking-[0.16em] uppercase">Insights</p>
                        <h1 className="text-2xl font-semibold tracking-tight">Registrar Reports</h1>
                        <p className="text-muted-foreground max-w-2xl text-sm leading-relaxed">
                            Configure official student lists, enrollment summaries, and assessment exports without leaving the registrar workspace.
                        </p>
                    </div>
                    <div className="flex flex-col items-start gap-2 sm:items-end">
                        <SemesterSelector {...filters} />
                        <p className="text-muted-foreground flex items-center gap-1.5 text-[11px]">
                            <Clock3 className="size-3" aria-hidden="true" />
                            Generated files are delivered through notifications
                        </p>
                    </div>
                </header>

                <ReportsSection
                    activeReportCard={activeReportCard}
                    reportFilters={reportFilters}
                    courseComboboxOptions={courseComboboxOptions}
                    subjectComboboxOptions={subjectComboboxOptions}
                    isLoadingFilterOptions={isLoadingFilterOptions}
                    isLoadingReport={isLoadingReport}
                    recentOutputs={recentOutputs}
                    onOpenBulkReports={() => setIsBulkReportsOpen(true)}
                    onReportCardClick={handleReportCardClick}
                    onReportFiltersChange={setReportFilters}
                    onCancelInlineFilters={() => setReportFilters(DEFAULT_REPORT_FILTERS)}
                    onGenerateReport={handleGenerateReport}
                    onExportExcel={handleExportExcel}
                />

                <BulkReportsDialog
                    open={isBulkReportsOpen}
                    onOpenChange={setIsBulkReportsOpen}
                    filters={bulkReportFilters}
                    onFiltersChange={setBulkReportFilters}
                    isGenerating={isGeneratingBulkReport}
                    onGenerate={handleGenerateBulkAssessments}
                    courseOptions={bulkCourseOptions}
                    studentLimitOptions={assessment_export_options.student_limits}
                />
            </div>
        </AdminLayout>
    );
}
