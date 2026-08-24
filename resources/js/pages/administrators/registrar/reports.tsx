import { generateBulkAssessments } from "@/actions/App/Http/Controllers/AdministratorEnrollmentManagementController";
import { ACTIVE_JOBS_REFRESH_EVENT } from "@/components/active-jobs-notification";
import AdminLayout from "@/components/administrators/admin-layout";
import { SemesterSelector, type SemesterSelectorProps } from "@/components/semester-selector";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { Combobox, type ComboboxOption } from "@/components/ui/combobox";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from "@/components/ui/sheet";
import { BulkReportsDialog } from "@/pages/administrators/enrollments/enrollment-dialogs";
import { ReportContent } from "@/pages/administrators/enrollments/report-content";
import type { BulkReportFilters, EnrollmentManagementProps, ReportFilters } from "@/pages/administrators/enrollments/types";
import { Head } from "@inertiajs/react";
import axios from "axios";
import { Check, ChevronRight, Download, FileSpreadsheet, FileText, Loader2, Printer, Search, Settings2, Sparkles } from "lucide-react";
import { useCallback, useEffect, useMemo, useState, type CSSProperties, type ReactNode, type SetStateAction } from "react";
import { toast } from "sonner";
import { route } from "ziggy-js";
import {
    getDefaultTemplateVariants,
    getTemplateDefinition,
    getTemplateVariant,
    STUDENT_TEMPLATES,
    TEMPLATES,
    type TemplateDefinition,
    type TemplateFormat,
    type TemplateKey,
} from "./report-template-registry";
type StudentSearchResult = {
    id: number;
    student_id: string | number | null;
    full_name: string;
    email: string | null;
    course_code: string | null;
    formatted_academic_year: string | null;
    academic_year: number | null;
    label: string;
};
type RegistrarReportsProps = {
    user: EnrollmentManagementProps["user"];
    filters: SemesterSelectorProps;
    assessment_export_options: EnrollmentManagementProps["assessment_export_options"];
};
type RecentOutput = { id: string; name: string; format: "PDF" | "XLSX" | "Print"; detail: string; createdAt: string };
type StudentDocumentPayload = {
    template: string;
    variant: string;
    title: string;
    subtitle: string;
    school: { name: string; logo: string; contact: string; email: string; address: string };
    student: {
        student_number: string | number | null;
        full_name: string;
        course_code: string | null;
        course_title: string | null;
        department: string | null;
        year_level: number | null;
    };
    enrollment: {
        school_year: string;
        semester_label: string;
        status: string;
        subjects: Array<{ code: string; title: string; units: number; section: string; schedule: string; grade: number | null }>;
        total_units: number;
    };
    grades: {
        subjects: Array<{
            code: string;
            title: string;
            units: number;
            prelim: number | null;
            midterm: number | null;
            finals: number | null;
            average: number | null;
            status: string;
        }>;
        term_average: number | null;
    };
    purpose: string | null;
    generated_at: string;
    generated_by: string;
};

const DEFAULT_REPORT_FILTERS: ReportFilters = {
    course_filter: "all",
    subject_filter: "all",
    department_filter: "all",
    year_level_filter: "all",
    status_filter: "active",
};

function buildUrl(name: string, query: Record<string, string | number | null | undefined>): string {
    const url = new URL(route(name), window.location.origin);
    Object.entries(query).forEach(([key, value]) => {
        if (value !== null && value !== undefined && value !== "") url.searchParams.set(key, String(value));
    });
    return url.toString();
}

function formatGrade(value: number | null): string {
    return value === null || value === undefined ? "—" : String(value);
}

const SAMPLE_SCHOOL = {
    name: "KoAkademy Institute",
    logo: "",
    contact: "(074) 444-5389",
    email: "registrar@koakademy.edu",
    address: "118 Bonifacio Street · Baguio City",
};

const SAMPLE_SUBJECTS = [
    { code: "IT 201", title: "Web Systems and Technologies", units: 3, section: "BSIT 2A", schedule: "MWF 9:00–10:00 AM", grade: 1.5 },
    { code: "IT 202", title: "Database Management", units: 3, section: "BSIT 2A", schedule: "TTh 10:30–12:00 NN", grade: 1.75 },
    { code: "GE 204", title: "Ethics and Society", units: 3, section: "BSIT 2B", schedule: "MWF 1:00–2:00 PM", grade: 1.25 },
    { code: "PE 2", title: "Physical Activity and Wellness", units: 2, section: "BSIT 2A", schedule: "Saturday 8:00–10:00 AM", grade: 1.5 },
];

function buildSampleStudentDocument(template: TemplateKey, variant: string, selectedStudent: StudentSearchResult | null): StudentDocumentPayload {
    const definition = getTemplateDefinition(template);
    const student = {
        student_number: selectedStudent?.student_id ?? "2024-0017",
        full_name: selectedStudent?.full_name ?? "Alexandra Santos",
        course_code: selectedStudent?.course_code ?? "BSIT",
        course_title: "Bachelor of Science in Information Technology",
        department: "Information Technology",
        year_level: 2,
    };
    const enrollmentSubjects = SAMPLE_SUBJECTS.map((subject) => ({ ...subject }));
    const gradeSubjects = SAMPLE_SUBJECTS.map((subject, index) => ({
        code: subject.code,
        title: subject.title,
        units: subject.units,
        prelim: [1.5, 1.75, 1.25, 1.5][index],
        midterm: [1.75, 1.5, 1.25, 1.5][index],
        finals: [1.5, 1.75, 1.25, 1.5][index],
        average: subject.grade,
        status: "Verified",
    }));

    return {
        template,
        variant,
        title: definition.title,
        subtitle: getTemplateVariant(template, variant).description,
        school: SAMPLE_SCHOOL,
        student,
        enrollment: {
            school_year: "2025–2026",
            semester_label: "2nd Semester",
            status: "Enrolled",
            subjects: enrollmentSubjects,
            total_units: enrollmentSubjects.reduce((total, subject) => total + subject.units, 0),
        },
        grades: {
            subjects: gradeSubjects,
            term_average: 1.5,
        },
        purpose: template === "certificate_of_enrollment" ? "Scholarship application" : null,
        generated_at: "August 24, 2026",
        generated_by: "Registrar Office",
    };
}

function buildSampleOperationalReport(template: TemplateKey, variant: string): Record<string, unknown> {
    const reportTitles: Record<string, [string, string]> = {
        enrolled_by_course: ["Master Enrollment List", "Students grouped by program, department, and year level"],
        enrolled_by_subject: ["Subject Roster", "Official class-facing list of enrolled students"],
        enrollment_summary: ["Enrollment Summary", "Current enrollment counts for registrar review"],
    };
    const [title, subtitle] = reportTitles[template] ?? reportTitles.enrolled_by_course;
    const common = {
        school: SAMPLE_SCHOOL,
        school_year: "2025–2026",
        semester: "2nd Semester",
        generated_at: "August 24, 2026",
        generated_by: "Registrar Office",
        variant,
    };

    if (template === "enrolled_by_subject") {
        return {
            ...common,
            report: {
                type: template,
                title,
                subtitle,
                filters_applied: { Subject: "All subjects", Department: "All departments" },
                total_count: 5,
                subject_groups: [
                    {
                        subject_code: "IT 201",
                        subject_title: "Web Systems and Technologies",
                        subject_units: 3,
                        total_enrolled: 3,
                        students: [
                            {
                                no: 1,
                                student_id: "2024-0017",
                                full_name: "Alexandra Santos",
                                course: "BSIT",
                                year_level: 2,
                                section: "BSIT 2A",
                                class_schedule: "MWF 9:00–10:00 AM",
                            },
                            {
                                no: 2,
                                student_id: "2024-0021",
                                full_name: "Miguel Reyes",
                                course: "BSIT",
                                year_level: 2,
                                section: "BSIT 2A",
                                class_schedule: "MWF 9:00–10:00 AM",
                            },
                            {
                                no: 3,
                                student_id: "2023-0094",
                                full_name: "Bea Navarro",
                                course: "BSCS",
                                year_level: 3,
                                section: "BSCS 3B",
                                class_schedule: "TTh 1:00–2:30 PM",
                            },
                        ],
                    },
                ],
            },
        };
    }

    if (template === "enrollment_summary") {
        return {
            ...common,
            report: {
                type: template,
                title,
                subtitle,
                filters_applied: { Department: "All departments", Status: "Active only" },
                total_enrolled: 128,
                by_department: [
                    { department: "Information Technology", count: 58 },
                    { department: "Business Administration", count: 42 },
                    { department: "Hospitality Management", count: 28 },
                ],
                by_course: [
                    { course_code: "BSIT", course_title: "Information Technology", department: "IT", count: 58 },
                    { course_code: "BSBA", course_title: "Business Administration", department: "BA", count: 42 },
                    { course_code: "BSHM", course_title: "Hospitality Management", department: "HM", count: 28 },
                ],
                by_year_level: [
                    { year_level: 1, count: 34 },
                    { year_level: 2, count: 36 },
                    { year_level: 3, count: 31 },
                    { year_level: 4, count: 27 },
                ],
                by_status: [{ status: "Active", count: 128 }],
            },
        };
    }

    return {
        ...common,
        report: {
            type: "enrolled_by_course",
            title,
            subtitle,
            filters_applied: { Course: "All courses", Department: "All departments", Status: "Active only" },
            total_count: 3,
            students: [
                {
                    no: 1,
                    student_id: "2024-0017",
                    full_name: "Alexandra Santos",
                    course: "BSIT",
                    department: "IT",
                    year_level: 2,
                    subjects_count: 4,
                    status: "Enrolled",
                },
                {
                    no: 2,
                    student_id: "2024-0021",
                    full_name: "Miguel Reyes",
                    course: "BSIT",
                    department: "IT",
                    year_level: 2,
                    subjects_count: 5,
                    status: "Enrolled",
                },
                {
                    no: 3,
                    student_id: "2023-0094",
                    full_name: "Bea Navarro",
                    course: "BSCS",
                    department: "IT",
                    year_level: 3,
                    subjects_count: 4,
                    status: "Enrolled",
                },
            ],
        },
    };
}

function buildClientPreview(
    template: TemplateKey,
    variant: string,
    selectedStudent: StudentSearchResult | null,
): Record<string, unknown> | StudentDocumentPayload {
    return STUDENT_TEMPLATES.has(template)
        ? buildSampleStudentDocument(template, variant, selectedStudent)
        : buildSampleOperationalReport(template, variant);
}

export default function RegistrarReports({ user, filters, assessment_export_options }: RegistrarReportsProps) {
    const [activeTemplate, setActiveTemplate] = useState<TemplateKey>("certificate_of_enrollment");
    const [catalogSearch, setCatalogSearch] = useState("");
    const [studentSearch, setStudentSearch] = useState("");
    const [studentResults, setStudentResults] = useState<StudentSearchResult[]>([]);
    const [selectedStudent, setSelectedStudent] = useState<StudentSearchResult | null>(null);
    const [reportFilters, setReportFilters] = useState<ReportFilters>(DEFAULT_REPORT_FILTERS);
    const [availableSubjects, setAvailableSubjects] = useState<Array<{ id: string | number; code: string; title: string; enrolled_count: number }>>(
        [],
    );
    const [availableCourses, setAvailableCourses] = useState<Array<{ id: number; code: string; title: string | null; department: string | null }>>(
        [],
    );
    const [isLoadingAvailableCourses, setIsLoadingAvailableCourses] = useState(false);
    const [courseOptionsError, setCourseOptionsError] = useState<string | null>(null);
    const [purpose, setPurpose] = useState("Scholarship, employment, or other lawful purpose");
    const [selectedVariants, setSelectedVariants] = useState<Record<TemplateKey, string>>(() => getDefaultTemplateVariants());
    const [variantSettingsFor, setVariantSettingsFor] = useState<TemplateKey | null>(null);
    const [previewData, setPreviewData] = useState<Record<string, unknown> | StudentDocumentPayload>(() =>
        buildClientPreview("certificate_of_enrollment", getDefaultTemplateVariants().certificate_of_enrollment, null),
    );
    const [isSamplePreview, setIsSamplePreview] = useState(true);
    const [isSearchingStudents, setIsSearchingStudents] = useState(false);
    const [isLoadingPreview, setIsLoadingPreview] = useState(false);
    const [recentOutputs, setRecentOutputs] = useState<RecentOutput[]>([]);
    const [isBulkReportsOpen, setIsBulkReportsOpen] = useState(false);
    const [bulkReportFilters, setBulkReportFilters] = useState<BulkReportFilters>({
        course_id: null,
        year_level: null,
        student_limit: null,
        include_deleted: false,
    });
    const [isGeneratingBulkReport, setIsGeneratingBulkReport] = useState(false);
    const template = getTemplateDefinition(activeTemplate);
    const activeVariant = getTemplateVariant(activeTemplate, selectedVariants[activeTemplate]);
    const isStudent = STUDENT_TEMPLATES.has(activeTemplate);
    const normalizedCatalogSearch = catalogSearch.trim().toLowerCase();
    const visibleTemplates = useMemo(
        () =>
            TEMPLATES.filter(
                (item) =>
                    !normalizedCatalogSearch || `${item.title} ${item.description} ${item.group}`.toLowerCase().includes(normalizedCatalogSearch),
            ),
        [normalizedCatalogSearch],
    );

    const loadCourseOptions = useCallback(async (): Promise<void> => {
        setIsLoadingAvailableCourses(true);
        setCourseOptionsError(null);

        try {
            const response = await fetch(buildUrl("administrators.enrollments.reports.course-options", {}), {
                headers: { Accept: "application/json" },
            });
            const payload = (await response.json().catch(() => ({}))) as {
                courses?: Array<{ id: number; code: string; title: string | null; department: string | null }>;
                message?: string;
            };

            if (!response.ok) {
                throw new Error(payload.message ?? "Course options could not be loaded.");
            }

            setAvailableCourses(Array.isArray(payload.courses) ? payload.courses : []);
        } catch (error) {
            setAvailableCourses([]);
            const message = error instanceof Error ? error.message : "Course options could not be loaded.";
            setCourseOptionsError(message);
            toast.error(message);
        } finally {
            setIsLoadingAvailableCourses(false);
        }
    }, []);

    const addRecentOutput = useCallback(
        (format: RecentOutput["format"], detail: string) => {
            setRecentOutputs((current) =>
                [
                    {
                        id: `${Date.now()}-${format}`,
                        name: template.title,
                        format,
                        detail,
                        createdAt: new Intl.DateTimeFormat(undefined, { hour: "numeric", minute: "2-digit" }).format(new Date()),
                    },
                    ...current,
                ].slice(0, 6),
            );
        },
        [template.title],
    );

    useEffect(() => {
        const search = studentSearch.trim();
        if (search.length < 2) {
            setStudentResults([]);
            setIsSearchingStudents(false);
            return;
        }
        let cancelled = false;
        setIsSearchingStudents(true);
        const timeout = window.setTimeout(async () => {
            try {
                const response = await fetch(buildUrl("administrators.enrollments.api.students", { search }), {
                    headers: { Accept: "application/json" },
                });
                if (!response.ok) throw new Error("student-search-failed");
                const results = (await response.json()) as StudentSearchResult[];
                if (!cancelled) setStudentResults(results);
            } catch {
                if (!cancelled) toast.error("Student search failed. Please try again.");
            } finally {
                if (!cancelled) setIsSearchingStudents(false);
            }
        }, 250);
        return () => {
            cancelled = true;
            window.clearTimeout(timeout);
        };
    }, [studentSearch]);

    useEffect(() => {
        void fetch(buildUrl("administrators.enrollments.reports.subject-options", {}), { headers: { Accept: "application/json" } })
            .then((response) => {
                if (!response.ok) throw new Error("Subject options could not be loaded.");

                return response.json();
            })
            .then((payload: { subjects?: typeof availableSubjects }) => setAvailableSubjects(payload.subjects ?? []))
            .catch(() => toast.error("Subject options could not be loaded. Please try again."));
        void loadCourseOptions();
    }, [loadCourseOptions]);

    const handleTemplateSelect = (key: TemplateKey) => {
        const nextVariant = selectedVariants[key] ?? getTemplateDefinition(key).defaultVariant;
        setActiveTemplate(key);
        setPreviewData(buildClientPreview(key, nextVariant, selectedStudent));
        setIsSamplePreview(true);
        setReportFilters(DEFAULT_REPORT_FILTERS);
    };

    const handleVariantSelect = (key: TemplateKey, variant: string) => {
        setSelectedVariants((current) => ({ ...current, [key]: variant }));
        if (key === activeTemplate) {
            setPreviewData(buildClientPreview(key, variant, selectedStudent));
            setIsSamplePreview(true);
        }
        setVariantSettingsFor(null);
    };

    const updateReportFilters = (updater: SetStateAction<ReportFilters>) => {
        setReportFilters(updater);
        setPreviewData(buildClientPreview(activeTemplate, activeVariant.key, selectedStudent));
        setIsSamplePreview(true);
    };

    const loadPreview = useCallback(async () => {
        if (isStudent && !selectedStudent) {
            toast.error("Select a student before generating this document.");
            return;
        }
        setIsLoadingPreview(true);
        try {
            const url = isStudent
                ? buildUrl("administrators.registrar.documents.preview", {
                      template: activeTemplate,
                      variant: activeVariant.key,
                      student_id: selectedStudent?.id,
                      purpose,
                  })
                : buildUrl("administrators.enrollments.reports.data", { report_type: activeTemplate, variant: activeVariant.key, ...reportFilters });
            const response = await fetch(url, { headers: { Accept: "application/json" } });
            const payload = (await response.json().catch(() => ({}))) as Record<string, unknown>;
            if (!response.ok) throw new Error((payload.message as string | undefined) ?? "Preview failed.");
            setPreviewData(payload);
            setIsSamplePreview(false);
        } catch (error) {
            toast.error(error instanceof Error ? error.message : "Could not generate the preview.");
        } finally {
            setIsLoadingPreview(false);
        }
    }, [activeTemplate, activeVariant.key, isStudent, purpose, reportFilters, selectedStudent]);

    const selectStudent = (student: StudentSearchResult) => {
        setSelectedStudent(student);
        setStudentSearch("");
        setStudentResults([]);
        setPreviewData(buildClientPreview(activeTemplate, activeVariant.key, student));
        setIsSamplePreview(true);
    };
    const handlePrint = () => {
        if (!canPrint) {
            toast.error(
                isSamplePreview ? "Generate a live preview before printing this document." : "Select a student before printing this document.",
            );
            return;
        }
        window.print();
        addRecentOutput("Print", isStudent ? (selectedStudent?.full_name ?? "Selected student") : "Current filters");
    };
    const handlePdfDownload = () => {
        if (!canExport) {
            toast.error("Select a student before downloading this document.");
            return;
        }
        const url = isStudent
            ? buildUrl("administrators.registrar.documents.pdf", {
                  template: activeTemplate,
                  variant: activeVariant.key,
                  student_id: selectedStudent?.id,
                  purpose,
              })
            : buildUrl("administrators.enrollments.reports.pdf", { report_type: activeTemplate, variant: activeVariant.key, ...reportFilters });
        window.open(url, "_blank", "noopener");
        addRecentOutput("PDF", isStudent ? (selectedStudent?.full_name ?? "Selected student") : "Current filters");
    };
    const handleExcelDownload = () => {
        if (!previewData || isStudent) return;
        window.open(
            buildUrl("administrators.enrollments.reports.export", { report_type: activeTemplate, variant: activeVariant.key, ...reportFilters }),
            "_blank",
            "noopener",
        );
        addRecentOutput("XLSX", "Operational workbook");
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
            setRecentOutputs((current) =>
                [
                    {
                        id: `${Date.now()}-bulk-pdf`,
                        name: "Bulk assessment export",
                        format: "PDF",
                        detail: "Queued PDF bundle",
                        createdAt: new Intl.DateTimeFormat(undefined, { hour: "numeric", minute: "2-digit" }).format(new Date()),
                    },
                    ...current,
                ].slice(0, 6),
            );
        } catch (error) {
            const message = axios.isAxiosError<{ message?: string }>(error) ? error.response?.data?.message : null;
            toast.error(message || "Failed to queue bulk assessment generation.", { id: toastId });
        } finally {
            setIsGeneratingBulkReport(false);
        }
    };

    const courseOptions: ComboboxOption[] = [
        { label: "All courses", value: "all", description: "Include every active program" },
        ...availableCourses.map((course) => ({ label: `${course.code} — ${course.title}`, value: course.code, description: course.department })),
    ];
    const subjectOptions: ComboboxOption[] = [
        { label: "All subjects", value: "all", description: "Include every subject" },
        ...availableSubjects.map((subject) => ({
            label: `${subject.code} — ${subject.title}`,
            value: String(subject.id),
            description: `${subject.enrolled_count} enrolled`,
        })),
    ];
    const settingsTemplate = variantSettingsFor ? getTemplateDefinition(variantSettingsFor) : null;
    const canExport = Boolean(previewData) && (!isStudent || Boolean(selectedStudent));
    const canPrint = canExport && !isSamplePreview;

    return (
        <AdminLayout user={user} title="Registrar Reports">
            <Head title="Administrators • Registrar Reports" />
            <style>{`@media print { body * { visibility: hidden !important; } #registrar-print-sheet, #registrar-print-sheet * { visibility: visible !important; } #registrar-print-sheet { position: absolute; inset: 0; width: 100%; background: #fff !important; padding: 0 !important; } .registrar-preview-paper { box-shadow: none !important; border: 0 !important; max-width: none !important; } }`}</style>
            <div className="space-y-7 pb-10">
                <header className="flex flex-col gap-5 border-b pb-6 xl:flex-row xl:items-end xl:justify-between">
                    <div className="max-w-2xl space-y-2">
                        <p className="text-muted-foreground text-[11px] font-semibold tracking-[0.2em] uppercase">Registrar workspace</p>
                        <h1 className="text-3xl font-semibold tracking-[-0.04em]">Documents, ready when you are.</h1>
                        <p className="text-muted-foreground text-sm leading-6">
                            Prepare official student documents and operational reports for the current academic period.
                        </p>
                    </div>
                    <div className="flex flex-col gap-2 xl:items-end">
                        <SemesterSelector {...filters} />
                        <span className="text-muted-foreground text-xs">PDF and print layouts use the school record on file.</span>
                    </div>
                </header>

                <div className="grid gap-6 xl:grid-cols-[280px_minmax(0,1fr)]">
                    <Card className="bg-card/80 h-fit rounded-2xl backdrop-blur-xl">
                        <div className="border-b p-4">
                            <div className="mb-3 flex items-center justify-between gap-3">
                                <div>
                                    <p className="text-sm font-semibold">Template catalog</p>
                                    <p className="text-muted-foreground mt-1 text-xs">Choose an official output.</p>
                                </div>
                                <Badge variant="secondary">{TEMPLATES.length}</Badge>
                            </div>
                            <div className="relative">
                                <Search className="text-muted-foreground absolute top-1/2 left-3 size-3.5 -translate-y-1/2" aria-hidden="true" />
                                <Input
                                    value={catalogSearch}
                                    onChange={(event) => setCatalogSearch(event.target.value)}
                                    placeholder="Find a template"
                                    className="h-9 pl-8 text-xs"
                                />
                            </div>
                        </div>
                        <div className="space-y-5 p-3">
                            {(["Student documents", "Operational reports"] as const).map((group) => {
                                const groupTemplates = visibleTemplates.filter((item) => item.group === group);
                                if (groupTemplates.length === 0) return null;
                                return (
                                    <section key={group} className="space-y-2">
                                        <p className="text-muted-foreground px-2 text-[10px] font-semibold tracking-[0.16em] uppercase">{group}</p>
                                        <div className="space-y-1">
                                            {groupTemplates.map((item) => (
                                                <div
                                                    key={item.key}
                                                    className={`flex items-stretch border transition-[background-color,border-color,box-shadow] duration-200 motion-reduce:transition-none ${activeTemplate === item.key ? "border-primary/30 bg-primary/8 shadow-xs" : "hover:border-border hover:bg-muted/50 border-transparent"}`}
                                                >
                                                    <button
                                                        type="button"
                                                        onClick={() => handleTemplateSelect(item.key)}
                                                        className="focus-visible:ring-ring/45 flex min-w-0 flex-1 items-start gap-3 px-2.5 py-3 text-left outline-none focus-visible:ring-2 focus-visible:ring-inset"
                                                        aria-pressed={activeTemplate === item.key}
                                                    >
                                                        <item.icon
                                                            className={`mt-0.5 size-4 shrink-0 ${activeTemplate === item.key ? "text-primary" : "text-muted-foreground"}`}
                                                            aria-hidden="true"
                                                        />
                                                        <span className="min-w-0 flex-1">
                                                            <span className="flex items-center justify-between gap-2">
                                                                <span className="text-sm font-medium">{item.title}</span>
                                                                {activeTemplate === item.key && (
                                                                    <Check className="text-primary size-3.5 shrink-0" aria-hidden="true" />
                                                                )}
                                                            </span>
                                                            <span className="text-muted-foreground mt-1 block text-xs leading-5">
                                                                {item.description}
                                                            </span>
                                                        </span>
                                                    </button>
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="icon-sm"
                                                        className="text-muted-foreground hover:text-foreground mt-2 mr-1.5 shrink-0 self-start"
                                                        onClick={() => {
                                                            handleTemplateSelect(item.key);
                                                            setVariantSettingsFor(item.key);
                                                        }}
                                                        aria-label={`Choose a format for ${item.title}`}
                                                        title={`Choose a format for ${item.title}`}
                                                    >
                                                        <Settings2 aria-hidden="true" />
                                                    </Button>
                                                </div>
                                            ))}
                                        </div>
                                    </section>
                                );
                            })}
                            <section className="space-y-2 border-t pt-4">
                                <p className="text-muted-foreground px-2 text-[10px] font-semibold tracking-[0.16em] uppercase">Bulk operations</p>
                                <button
                                    type="button"
                                    onClick={() => setIsBulkReportsOpen(true)}
                                    className="hover:bg-muted/50 flex w-full items-start gap-3 border border-transparent px-2.5 py-3 text-left transition-colors"
                                >
                                    <Download className="text-muted-foreground mt-0.5 size-4 shrink-0" />
                                    <span>
                                        <span className="block text-sm font-medium">Bulk assessment export</span>
                                        <span className="text-muted-foreground mt-1 block text-xs leading-5">
                                            Queue printable assessment PDFs for a scoped student group.
                                        </span>
                                    </span>
                                </button>
                            </section>
                        </div>
                    </Card>

                    <main className="min-w-0 space-y-5">
                        <section className="flex flex-col gap-4 border-b pb-5 md:flex-row md:items-start md:justify-between">
                            <div className="flex items-start gap-3">
                                <div className="bg-primary/10 text-primary flex size-10 shrink-0 items-center justify-center rounded-xl">
                                    <template.icon className="size-5" aria-hidden="true" />
                                </div>
                                <div>
                                    <div className="flex flex-wrap items-center gap-2">
                                        <h2 className="text-xl font-semibold tracking-tight">{template.title}</h2>
                                        <Badge variant="outline">{template.mode === "student" ? "Student document" : "Operational report"}</Badge>
                                    </div>
                                    <p className="text-muted-foreground mt-1 text-sm">
                                        {template.description} <span className="text-foreground/70">· {activeVariant.title}</span>
                                    </p>
                                </div>
                            </div>
                            <div className="flex items-center gap-3">
                                <div className="text-muted-foreground flex items-center gap-2 text-xs">
                                    {template.formats.map((format) => (
                                        <span key={format} className="inline-flex items-center gap-1">
                                            {format === "PDF" ? (
                                                <FileText className="size-3.5" />
                                            ) : format === "XLSX" ? (
                                                <FileSpreadsheet className="size-3.5" />
                                            ) : (
                                                <Printer className="size-3.5" />
                                            )}
                                            {format}
                                        </span>
                                    ))}
                                </div>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="icon-sm"
                                    onClick={() => setVariantSettingsFor(activeTemplate)}
                                    aria-label={`Choose a format for ${template.title}`}
                                    title="Choose document format"
                                >
                                    <Settings2 aria-hidden="true" />
                                </Button>
                            </div>
                        </section>
                        <div className="grid gap-5 2xl:grid-cols-[minmax(300px,0.42fr)_minmax(0,0.58fr)]">
                            <Card className="rounded-2xl">
                                <CardContent className="space-y-5 p-5">
                                    {isStudent ? (
                                        <div className="space-y-3">
                                            <div>
                                                <Label htmlFor="student-search">Student</Label>
                                                <p className="text-muted-foreground mt-1 text-xs">Search by name, student number, or program.</p>
                                            </div>
                                            <div className="relative">
                                                <Search
                                                    className="text-muted-foreground absolute top-1/2 left-3 size-4 -translate-y-1/2"
                                                    aria-hidden="true"
                                                />
                                                <Input
                                                    id="student-search"
                                                    value={studentSearch}
                                                    onChange={(event) => setStudentSearch(event.target.value)}
                                                    placeholder="Type at least 2 characters"
                                                    className="h-11 pl-9"
                                                />
                                                {isSearchingStudents && (
                                                    <Loader2 className="text-muted-foreground absolute top-1/2 right-3 size-4 -translate-y-1/2 animate-spin" />
                                                )}
                                            </div>
                                            {studentResults.length > 0 && (
                                                <div className="divide-y border">
                                                    {studentResults.slice(0, 8).map((student) => (
                                                        <button
                                                            type="button"
                                                            key={student.id}
                                                            onClick={() => selectStudent(student)}
                                                            className="hover:bg-muted/50 flex w-full items-center justify-between gap-3 p-3 text-left transition-colors"
                                                        >
                                                            <span className="min-w-0">
                                                                <span className="block truncate text-sm font-medium">{student.full_name}</span>
                                                                <span className="text-muted-foreground mt-0.5 block truncate text-xs">
                                                                    {student.student_id ?? "No student number"} ·{" "}
                                                                    {student.course_code ?? "No program"} ·{" "}
                                                                    {student.formatted_academic_year ?? "No year level"}
                                                                </span>
                                                            </span>
                                                            <ChevronRight className="text-muted-foreground size-4 shrink-0" />
                                                        </button>
                                                    ))}
                                                </div>
                                            )}
                                            {selectedStudent && (
                                                <div className="border-primary/25 bg-primary/6 flex items-start justify-between gap-3 border p-3">
                                                    <div>
                                                        <p className="text-sm font-semibold">{selectedStudent.full_name}</p>
                                                        <p className="text-muted-foreground mt-1 text-xs">
                                                            {selectedStudent.student_id ?? "No student number"} ·{" "}
                                                            {selectedStudent.course_code ?? "No program"} ·{" "}
                                                            {selectedStudent.formatted_academic_year ?? "No year level"}
                                                        </p>
                                                    </div>
                                                    <button
                                                        type="button"
                                                        className="text-muted-foreground hover:text-foreground text-xs underline"
                                                        onClick={() => setSelectedStudent(null)}
                                                    >
                                                        Change
                                                    </button>
                                                </div>
                                            )}
                                        </div>
                                    ) : (
                                        <div className="space-y-4">
                                            <div>
                                                <p className="text-sm font-semibold">Report scope</p>
                                                <p className="text-muted-foreground mt-1 text-xs">Filters apply to the selected academic period.</p>
                                            </div>
                                            {activeTemplate === "enrolled_by_course" && (
                                                <Combobox
                                                    label="Course"
                                                    options={courseOptions}
                                                    value={reportFilters.course_filter}
                                                    onValueChange={(value) =>
                                                        updateReportFilters((current) => ({ ...current, course_filter: value }))
                                                    }
                                                    placeholder="All courses"
                                                    searchPlaceholder="Search courses"
                                                />
                                            )}
                                            {activeTemplate === "enrolled_by_subject" && (
                                                <Combobox
                                                    label="Subject"
                                                    options={subjectOptions}
                                                    value={reportFilters.subject_filter}
                                                    onValueChange={(value) =>
                                                        updateReportFilters((current) => ({ ...current, subject_filter: value }))
                                                    }
                                                    placeholder="All subjects"
                                                    searchPlaceholder="Search subjects"
                                                />
                                            )}
                                            {(activeTemplate === "enrolled_by_course" || activeTemplate === "enrollment_summary") && (
                                                <div className="space-y-2">
                                                    <Label>Department</Label>
                                                    <Select
                                                        value={reportFilters.department_filter}
                                                        onValueChange={(value) =>
                                                            updateReportFilters((current) => ({ ...current, department_filter: value }))
                                                        }
                                                    >
                                                        <SelectTrigger>
                                                            <SelectValue placeholder="All departments" />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            {[
                                                                ["all", "All departments"],
                                                                ["IT", "Information Technology"],
                                                                ["HM", "Hospitality Management"],
                                                                ["BA", "Business Administration"],
                                                                ["TESDA", "TESDA"],
                                                            ].map(([value, label]) => (
                                                                <SelectItem key={value} value={value}>
                                                                    {label}
                                                                </SelectItem>
                                                            ))}
                                                        </SelectContent>
                                                    </Select>
                                                </div>
                                            )}
                                            {activeTemplate === "enrolled_by_course" && (
                                                <div className="space-y-2">
                                                    <Label>Year level</Label>
                                                    <Select
                                                        value={reportFilters.year_level_filter}
                                                        onValueChange={(value) =>
                                                            updateReportFilters((current) => ({ ...current, year_level_filter: value }))
                                                        }
                                                    >
                                                        <SelectTrigger>
                                                            <SelectValue placeholder="All year levels" />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem value="all">All year levels</SelectItem>
                                                            {[1, 2, 3, 4, 5, 6].map((year) => (
                                                                <SelectItem key={year} value={String(year)}>
                                                                    Year {year}
                                                                </SelectItem>
                                                            ))}
                                                        </SelectContent>
                                                    </Select>
                                                </div>
                                            )}
                                            <div className="space-y-2">
                                                <Label>Enrollment records</Label>
                                                <Select
                                                    value={reportFilters.status_filter}
                                                    onValueChange={(value) =>
                                                        updateReportFilters((current) => ({ ...current, status_filter: value }))
                                                    }
                                                >
                                                    <SelectTrigger>
                                                        <SelectValue placeholder="Active only" />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem value="active">Active only</SelectItem>
                                                        <SelectItem value="all">Include deleted</SelectItem>
                                                    </SelectContent>
                                                </Select>
                                            </div>
                                        </div>
                                    )}
                                    {activeTemplate === "certificate_of_enrollment" && (
                                        <div className="space-y-2 border-t pt-4">
                                            <Label htmlFor="document-purpose">Purpose line</Label>
                                            <Input id="document-purpose" value={purpose} onChange={(event) => setPurpose(event.target.value)} />
                                        </div>
                                    )}
                                    <div className="border-t pt-4">
                                        <Button
                                            className="w-full"
                                            onClick={() => void loadPreview()}
                                            disabled={isLoadingPreview || (isStudent && !selectedStudent)}
                                        >
                                            {isLoadingPreview ? (
                                                <Loader2 className="mr-2 size-4 animate-spin" />
                                            ) : (
                                                <FileText className="mr-2 size-4" />
                                            )}
                                            {isLoadingPreview ? "Preparing preview" : "Generate preview"}
                                        </Button>
                                        <p className="text-muted-foreground mt-2 text-center text-[11px]">
                                            The layout changes instantly when you choose another format. PDF and print use the current record.
                                        </p>
                                    </div>
                                </CardContent>
                            </Card>

                            <section className="min-w-0 space-y-4">
                                <div className="flex items-center justify-between gap-3">
                                    <div>
                                        <div className="flex items-center gap-2">
                                            <p className="text-sm font-semibold">Preview</p>
                                            <Badge variant={isSamplePreview ? "secondary" : "outline"} className="gap-1 text-[10px]">
                                                {isSamplePreview && <Sparkles className="size-3" aria-hidden="true" />}
                                                {isSamplePreview ? "Sample layout" : "Live record"}
                                            </Badge>
                                        </div>
                                        <p className="text-muted-foreground mt-1 text-xs">
                                            {isSamplePreview
                                                ? "This instant preview uses sample data. Generate a live preview before release."
                                                : "Review the official layout before releasing it."}
                                        </p>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <Button variant="outline" size="sm" onClick={handlePrint} disabled={!canPrint}>
                                            <Printer className="mr-2 size-3.5" /> Print
                                        </Button>
                                        <Button variant="outline" size="sm" onClick={handlePdfDownload} disabled={!canExport}>
                                            <Download className="mr-2 size-3.5" /> PDF
                                        </Button>
                                        {!isStudent && (
                                            <Button variant="outline" size="sm" onClick={handleExcelDownload} disabled={!canExport}>
                                                <FileSpreadsheet className="mr-2 size-3.5" /> XLSX
                                            </Button>
                                        )}
                                    </div>
                                </div>
                                <div
                                    id="registrar-print-sheet"
                                    className="registrar-preview-paper bg-muted/25 min-h-[640px] border p-4 shadow-sm sm:p-7"
                                >
                                    {previewData ? (
                                        isStudent ? (
                                            <StudentDocumentPreview data={previewData as StudentDocumentPayload} />
                                        ) : (
                                            <OperationalReportPreview data={previewData} variant={activeVariant} />
                                        )
                                    ) : (
                                        <div className="text-muted-foreground flex min-h-[580px] flex-col items-center justify-center border border-dashed text-center">
                                            <FileText className="text-muted-foreground/40 size-10" aria-hidden="true" />
                                            <p className="text-foreground mt-4 text-sm font-medium">Your document will appear here</p>
                                            <p className="mt-1 max-w-xs text-xs leading-5">
                                                Select a student or scope a report, then generate a preview to inspect the printable output.
                                            </p>
                                        </div>
                                    )}
                                </div>
                            </section>
                        </div>

                        <section className="border-t pt-5">
                            <div className="mb-3 flex items-end justify-between gap-3">
                                <div>
                                    <p className="text-sm font-semibold">Recent outputs</p>
                                    <p className="text-muted-foreground mt-1 text-xs">Files and print actions started in this session.</p>
                                </div>
                                <span className="text-muted-foreground text-xs">{recentOutputs.length} recorded</span>
                            </div>
                            {recentOutputs.length === 0 ? (
                                <div className="text-muted-foreground border border-dashed px-4 py-7 text-center text-xs">
                                    No outputs yet. Your recent print and download actions will show here.
                                </div>
                            ) : (
                                <div className="divide-y border">
                                    {recentOutputs.map((output) => (
                                        <div key={output.id} className="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                                            <div className="flex items-center gap-3">
                                                <div className="bg-muted flex size-8 items-center justify-center">
                                                    {output.format === "XLSX" ? (
                                                        <FileSpreadsheet className="size-4" />
                                                    ) : output.format === "Print" ? (
                                                        <Printer className="size-4" />
                                                    ) : (
                                                        <FileText className="size-4" />
                                                    )}
                                                </div>
                                                <div>
                                                    <p className="text-sm font-medium">{output.name}</p>
                                                    <p className="text-muted-foreground text-xs">
                                                        {output.detail} · {output.createdAt}
                                                    </p>
                                                </div>
                                            </div>
                                            <Badge variant="outline">{output.format}</Badge>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </section>
                    </main>
                </div>
            </div>
            <Sheet open={variantSettingsFor !== null} onOpenChange={(open) => !open && setVariantSettingsFor(null)}>
                <SheetContent className="w-full gap-0 overflow-y-auto p-0 sm:max-w-2xl">
                    {settingsTemplate && (
                        <>
                            <SheetHeader className="border-b px-6 py-6 pr-12">
                                <div className="flex items-start gap-3">
                                    <div className="bg-primary/10 text-primary flex size-10 shrink-0 items-center justify-center rounded-xl">
                                        <settingsTemplate.icon className="size-5" aria-hidden="true" />
                                    </div>
                                    <div>
                                        <SheetTitle className="tracking-[-0.02em]">Choose a document format</SheetTitle>
                                        <SheetDescription className="mt-1">
                                            {settingsTemplate.title} · Select a layout and the main preview updates immediately with sample data.
                                        </SheetDescription>
                                    </div>
                                </div>
                            </SheetHeader>
                            <div className="space-y-5 px-6 py-6">
                                <div className="bg-muted/30 rounded-xl border p-4">
                                    <p className="text-muted-foreground text-[10px] font-semibold tracking-[0.16em] uppercase">How formats work</p>
                                    <p className="text-muted-foreground mt-2 text-xs leading-5">
                                        Each format describes a different official output structure. Add another object to the template registry to
                                        introduce a new format; keep its structure key in the preview and PDF renderers.
                                    </p>
                                </div>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    {settingsTemplate.variants.map((variant) => {
                                        const isSelected = selectedVariants[settingsTemplate.key] === variant.key;
                                        return (
                                            <button
                                                type="button"
                                                key={variant.key}
                                                onClick={() => handleVariantSelect(settingsTemplate.key, variant.key)}
                                                className={`group focus-visible:ring-ring/45 rounded-2xl border p-3 text-left transition-[background-color,border-color,box-shadow,transform] duration-200 outline-none hover:-translate-y-0.5 hover:shadow-md focus-visible:ring-2 motion-reduce:transform-none motion-reduce:transition-none ${isSelected ? "border-primary bg-primary/5 shadow-sm" : "hover:border-primary/30"}`}
                                                aria-pressed={isSelected}
                                            >
                                                <TemplateVariantPreview template={settingsTemplate} variant={variant} />
                                                <div className="mt-3 flex items-start justify-between gap-3">
                                                    <div>
                                                        <p className="text-sm font-semibold">{variant.title}</p>
                                                        <p className="text-muted-foreground mt-1 text-xs leading-5">{variant.description}</p>
                                                        <div className="mt-2 flex flex-wrap gap-1">
                                                            {variant.includes.slice(0, 3).map((item) => (
                                                                <span
                                                                    key={item}
                                                                    className="bg-muted text-muted-foreground rounded px-1.5 py-0.5 text-[9px]"
                                                                >
                                                                    {item}
                                                                </span>
                                                            ))}
                                                        </div>
                                                    </div>
                                                    <span
                                                        className={`mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full border ${isSelected ? "border-primary bg-primary text-primary-foreground" : "border-border"}`}
                                                    >
                                                        {isSelected && <Check className="size-3" aria-hidden="true" />}
                                                    </span>
                                                </div>
                                            </button>
                                        );
                                    })}
                                </div>
                            </div>
                        </>
                    )}
                </SheetContent>
            </Sheet>
            <BulkReportsDialog
                open={isBulkReportsOpen}
                onOpenChange={(open) => {
                    setIsBulkReportsOpen(open);
                    if (open && (availableCourses.length === 0 || courseOptionsError !== null)) {
                        void loadCourseOptions();
                    }
                }}
                filters={bulkReportFilters}
                onFiltersChange={setBulkReportFilters}
                isGenerating={isGeneratingBulkReport}
                onGenerate={handleGenerateBulkAssessments}
                courseOptions={availableCourses.map((course) => ({ id: course.id, code: course.code, title: course.title }))}
                isLoadingCourses={isLoadingAvailableCourses}
                courseOptionsError={courseOptionsError}
                onRetryCourses={() => void loadCourseOptions()}
                studentLimitOptions={assessment_export_options.student_limits}
            />
        </AdminLayout>
    );
}

function TemplateVariantPreview({ template, variant }: { template: TemplateDefinition; variant: TemplateFormat }) {
    const isCertificate = template.key === "certificate_of_enrollment";
    const isRegistration = template.key === "registration_form";
    const isGradeReport = template.key === "grade_report";
    const isStatement = variant.structure === "certificate_statement";
    const isSummary = variant.structure.startsWith("summary_");
    const isRoster = variant.structure.startsWith("roster_") || variant.structure.startsWith("subject_");

    return (
        <div className="bg-background/90 overflow-hidden rounded-xl border shadow-xs" aria-hidden="true">
            <div className="bg-foreground h-1" />
            <div className="space-y-2 p-3">
                <div className="flex items-center gap-2 border-b pb-2">
                    <div className="bg-muted size-5 rounded-full border" />
                    <div className="flex-1 space-y-1">
                        <div className="bg-foreground/75 h-1.5 w-2/3 rounded-full" />
                        <div className="bg-muted-foreground/35 h-1 w-1/2 rounded-full" />
                    </div>
                    <span className="text-muted-foreground text-[8px] uppercase">{variant.orientation}</span>
                </div>
                <div className="space-y-1">
                    <div className="bg-foreground/70 mx-auto h-2 w-3/5 rounded-full" />
                    <div className="bg-muted-foreground/30 mx-auto h-1 w-2/5 rounded-full" />
                </div>
                {isStatement ? (
                    <div className="space-y-2 border p-2">
                        <div className="bg-muted h-1.5 w-full rounded-full" />
                        <div className="bg-muted h-1.5 w-4/5 rounded-full" />
                        <div className="bg-muted h-1.5 w-3/5 rounded-full" />
                    </div>
                ) : isSummary ? (
                    <div className="grid grid-cols-2 gap-1.5">
                        {["Total", "Department", "Course", "Status"].map((label) => (
                            <div key={label} className="rounded-md border p-1.5">
                                <div className="text-[8px] font-semibold">{label}</div>
                                <div className="bg-muted mt-1 h-1 w-3/4 rounded-full" />
                            </div>
                        ))}
                    </div>
                ) : isRoster ? (
                    <div className="space-y-1">
                        <div className="bg-muted-foreground/30 h-1.5 w-full rounded-full" />
                        {[1, 2, 3].map((row) => (
                            <div key={row} className="grid grid-cols-4 gap-1">
                                <div className="bg-muted h-1.5 rounded-full" />
                                <div className="bg-muted col-span-2 h-1.5 rounded-full" />
                                <div className="bg-muted h-1.5 rounded-full" />
                            </div>
                        ))}
                    </div>
                ) : (
                    <div className="space-y-1">
                        <div className="grid grid-cols-4 gap-1">
                            <div className="bg-muted-foreground/30 h-1.5 rounded-full" />
                            <div className="bg-muted-foreground/30 col-span-2 h-1.5 rounded-full" />
                            <div className="bg-muted-foreground/30 h-1.5 rounded-full" />
                        </div>
                        {[1, 2, 3].map((row) => (
                            <div key={row} className="grid grid-cols-4 gap-1">
                                <div className="bg-muted h-1.5 rounded-full" />
                                <div className="bg-muted col-span-2 h-1.5 rounded-full" />
                                <div className="bg-muted h-1.5 rounded-full" />
                            </div>
                        ))}
                    </div>
                )}
                <div className="text-muted-foreground flex items-center justify-between border-t pt-2 text-[8px]">
                    <span>Alexandra Santos · 2025–2026</span>
                    <span>{isCertificate ? "Certificate" : isRegistration ? "Registration" : isGradeReport ? "Grades" : "Report"}</span>
                </div>
            </div>
        </div>
    );
}

function OperationalReportPreview({ data }: { data: Record<string, unknown>; variant: TemplateFormat }) {
    return (
        <div className="bg-white">
            <div className="border-b px-5 py-3 text-xs">
                <span className="font-semibold">Format:</span> {variant.title} · {variant.description}
            </div>
            <ReportContent data={data} />
        </div>
    );
}

function StudentDocumentPreview({ data }: { data: StudentDocumentPayload }) {
    const student = data.student;
    const enrollment = data.enrollment;
    const isGradeReport = data.template === "grade_report";
    const isCertificate = data.template === "certificate_of_enrollment";
    const variant = getTemplateVariant(data.template as TemplateKey, data.variant);
    const isStatementCertificate = variant.structure === "certificate_statement";
    const isUnitsCertificate = variant.structure === "certificate_units";
    const isAdviserRegistration = variant.structure === "registration_advising";
    const isCompactRegistration = variant.structure === "registration_compact";
    const isTranscriptGrade = variant.structure === "grade_transcript";
    const isGradeSlip = variant.structure === "grade_slip";
    const subjects = isGradeReport
        ? data.grades.subjects.map((subject) => ({ ...subject, section: "", schedule: "", grade: null }))
        : enrollment.subjects.map((subject) => ({ ...subject, prelim: null, midterm: null, finals: null, average: subject.grade, status: "" }));
    const numberStyle: CSSProperties = { textAlign: "right" };
    const cellStyle: CSSProperties = { border: "1px solid #c9d0d5", padding: "6px", verticalAlign: "top" };
    const headerCellStyle: CSSProperties = {
        ...cellStyle,
        background: "#edf0f2",
        fontSize: "0.7rem",
        fontWeight: 700,
        letterSpacing: "0.04em",
        textTransform: "uppercase",
    };
    return (
        <div
            className={`registrar-document bg-white text-[10pt] leading-relaxed text-[#17202a] ${isGradeSlip ? "p-5 text-[9pt]" : "p-5 sm:p-8"}`}
            style={{ fontFamily: "Arial, Helvetica, sans-serif", borderTop: "5px solid #17202a" }}
        >
            <div className="flex items-center gap-3 border-b-2 border-[#17202a] pb-3">
                {data.school.logo && <img src={data.school.logo} alt="School logo" className="size-12 object-contain" />}
                <div>
                    <h3 className="m-0 text-[15pt] font-semibold tracking-[0.08em] uppercase">{data.school.name}</h3>
                    <p className="text-muted-foreground m-0 text-xs">{data.school.address}</p>
                    <p className="text-muted-foreground m-0 text-xs">
                        {data.school.contact}
                        {data.school.email ? ` · ${data.school.email}` : ""}
                    </p>
                </div>
            </div>
            <div className="my-5 text-center">
                <h2 className="m-0 text-[16pt] font-semibold tracking-[0.08em] uppercase">{data.title}</h2>
                <p className="text-muted-foreground m-1 text-xs">{data.subtitle}</p>
                <p className="text-muted-foreground m-1 text-[10px] uppercase">{variant.title}</p>
            </div>
            <div className="flex justify-between gap-4 border-y border-[#b8c0c7] py-2 text-xs">
                <span>
                    <strong>School Year:</strong> {enrollment.school_year}
                </span>
                <span>
                    <strong>Academic Period:</strong> {enrollment.semester_label}
                </span>
            </div>
            <div className="my-4 grid grid-cols-2 gap-x-5 gap-y-3 text-xs sm:grid-cols-3">
                <PreviewField label="Student name" value={student.full_name} />
                <PreviewField label="Student number" value={String(student.student_number ?? "—")} />
                <PreviewField label="Year level" value={student.year_level ? `Year ${student.year_level}` : "—"} />
                <PreviewField label="Program" value={student.course_code ?? "—"} />
                <PreviewField label="Department" value={student.department ?? "—"} />
                <PreviewField label="Enrollment status" value={enrollment.status} />
            </div>
            {isCertificate && (
                <p className="my-5 text-justify">
                    This is to certify that <strong>{student.full_name}</strong>, student number <strong>{student.student_number ?? "—"}</strong>, is
                    officially enrolled in the <strong>{student.course_code ?? "—"}</strong> program for the {enrollment.semester_label.toLowerCase()}{" "}
                    of School Year <strong>{enrollment.school_year}</strong>.
                </p>
            )}
            {data.purpose && isCertificate && (
                <div className="my-4 border border-[#b8c0c7] bg-[#f7f8f9] p-3 text-sm">
                    <strong>Issued for:</strong> {data.purpose}
                </div>
            )}
            {isStatementCertificate && (
                <div className="my-6 border-y-2 border-[#17202a] px-5 py-7 text-justify text-[11pt]">
                    This letter certifies that <strong>{student.full_name}</strong>, student number <strong>{student.student_number ?? "—"}</strong>,
                    is officially enrolled in the <strong>{student.course_code ?? "—"}</strong> program for the{" "}
                    {enrollment.semester_label.toLowerCase()} of School Year <strong>{enrollment.school_year}</strong>. The student's enrollment
                    status is recorded as <strong>{enrollment.status}</strong>.
                </div>
            )}
            {!isGradeReport && !isStatementCertificate && (
                <p className="my-4 text-sm">
                    {isCertificate
                        ? "Current registered subjects:"
                        : "The following subjects constitute the student's registration for the academic period shown above."}
                </p>
            )}
            {isGradeReport && (
                <p className="my-4 text-justify">
                    This report reflects the grades currently recorded for the student in the selected academic period.
                </p>
            )}
            {!isStatementCertificate && subjects.length > 0 ? (
                <table className="w-full border-collapse text-xs" style={{ borderCollapse: "collapse" }}>
                    <thead>
                        {isGradeReport ? (
                            isTranscriptGrade ? (
                                <tr>
                                    <th style={headerCellStyle}>Code</th>
                                    <th style={headerCellStyle}>Descriptive title</th>
                                    <th style={{ ...headerCellStyle, ...numberStyle }}>Units</th>
                                    <th style={{ ...headerCellStyle, ...numberStyle }}>Average</th>
                                    <th style={headerCellStyle}>Status</th>
                                </tr>
                            ) : isGradeSlip ? (
                                <tr>
                                    <th style={headerCellStyle}>Code</th>
                                    <th style={headerCellStyle}>Descriptive title</th>
                                    <th style={{ ...headerCellStyle, ...numberStyle }}>Average</th>
                                </tr>
                            ) : (
                                <tr>
                                    <th style={headerCellStyle}>Code</th>
                                    <th style={headerCellStyle}>Descriptive title</th>
                                    <th style={{ ...headerCellStyle, ...numberStyle }}>Units</th>
                                    <th style={{ ...headerCellStyle, ...numberStyle }}>Prelim</th>
                                    <th style={{ ...headerCellStyle, ...numberStyle }}>Midterm</th>
                                    <th style={{ ...headerCellStyle, ...numberStyle }}>Finals</th>
                                    <th style={{ ...headerCellStyle, ...numberStyle }}>Average</th>
                                    <th style={headerCellStyle}>Status</th>
                                </tr>
                            )
                        ) : (
                            <tr>
                                <th style={{ ...headerCellStyle, ...numberStyle }}>No.</th>
                                <th style={headerCellStyle}>Code</th>
                                <th style={headerCellStyle}>Descriptive title</th>
                                {!isUnitsCertificate && <th style={headerCellStyle}>Section</th>}
                                {!isCertificate && !isCompactRegistration && <th style={headerCellStyle}>Schedule</th>}
                                <th style={{ ...headerCellStyle, ...numberStyle }}>Units</th>
                            </tr>
                        )}
                    </thead>
                    <tbody>
                        {subjects.map((subject, index) => (
                            <tr key={`${subject.code}-${index}`}>
                                {isGradeReport ? (
                                    isTranscriptGrade ? (
                                        <>
                                            <td style={cellStyle}>{subject.code}</td>
                                            <td style={cellStyle}>{subject.title}</td>
                                            <td style={{ ...cellStyle, ...numberStyle }}>{subject.units}</td>
                                            <td style={{ ...cellStyle, ...numberStyle }}>{formatGrade(subject.average)}</td>
                                            <td style={cellStyle}>{subject.status}</td>
                                        </>
                                    ) : isGradeSlip ? (
                                        <>
                                            <td style={cellStyle}>{subject.code}</td>
                                            <td style={cellStyle}>{subject.title}</td>
                                            <td style={{ ...cellStyle, ...numberStyle }}>{formatGrade(subject.average)}</td>
                                        </>
                                    ) : (
                                        <>
                                            <td style={cellStyle}>{subject.code}</td>
                                            <td style={cellStyle}>{subject.title}</td>
                                            <td style={{ ...cellStyle, ...numberStyle }}>{subject.units}</td>
                                            <td style={{ ...cellStyle, ...numberStyle }}>{formatGrade(subject.prelim)}</td>
                                            <td style={{ ...cellStyle, ...numberStyle }}>{formatGrade(subject.midterm)}</td>
                                            <td style={{ ...cellStyle, ...numberStyle }}>{formatGrade(subject.finals)}</td>
                                            <td style={{ ...cellStyle, ...numberStyle }}>{formatGrade(subject.average)}</td>
                                            <td style={cellStyle}>{subject.status}</td>
                                        </>
                                    )
                                ) : (
                                    <>
                                        <td style={{ ...cellStyle, ...numberStyle }}>{index + 1}</td>
                                        <td style={cellStyle}>{subject.code}</td>
                                        <td style={cellStyle}>{subject.title}</td>
                                        {!isUnitsCertificate && <td style={cellStyle}>{subject.section}</td>}
                                        {!isCertificate && !isCompactRegistration && <td style={cellStyle}>{subject.schedule}</td>}
                                        <td style={{ ...cellStyle, ...numberStyle }}>{subject.units}</td>
                                    </>
                                )}
                            </tr>
                        ))}
                    </tbody>
                </table>
            ) : !isStatementCertificate ? (
                <div className="text-muted-foreground my-5 border border-dashed p-6 text-center text-sm">
                    No records were found for this academic period.
                </div>
            ) : null}
            {!isStatementCertificate && (
                <div className="flex justify-end gap-6 border-t-2 border-[#17202a] pt-2 text-sm font-semibold">
                    <span>{isGradeReport ? "Term average" : "Total units"}</span>
                    <span>{isGradeReport ? (data.grades.term_average ?? "—") : enrollment.total_units}</span>
                </div>
            )}
            {isCertificate && (
                <p className="my-5 text-justify">Issued upon the request of the student for whatever lawful purpose this certification may serve.</p>
            )}
            {isAdviserRegistration && (
                <div className="mt-8 grid grid-cols-2 gap-8 text-center text-xs">
                    <div className="border-t border-[#17202a] pt-1">
                        Adviser<span className="text-muted-foreground block">Reviewed and approved</span>
                    </div>
                    <div className="border-t border-[#17202a] pt-1">
                        Registrar<span className="text-muted-foreground block">Registration validated</span>
                    </div>
                </div>
            )}
            <div className="mt-14 flex justify-between gap-8 text-center text-xs">
                <div className="w-2/5 border-t border-[#17202a] pt-1">
                    {data.generated_by}
                    <span className="text-muted-foreground block">Prepared by</span>
                </div>
                <div className="w-2/5 border-t border-[#17202a] pt-1">
                    Registrar<span className="text-muted-foreground block">Authorized signature</span>
                </div>
            </div>
            <div className="text-muted-foreground mt-6 flex justify-between border-t pt-2 text-[10px]">
                <span>Generated {data.generated_at}</span>
                <span>{variant.title}</span>
            </div>
        </div>
    );
}

function PreviewField({ label, value }: { label: string; value: ReactNode }) {
    return (
        <div>
            <span className="text-muted-foreground block text-[10px] tracking-[0.08em] uppercase">{label}</span>
            <span className="block min-h-5 font-semibold">{value}</span>
        </div>
    );
}
