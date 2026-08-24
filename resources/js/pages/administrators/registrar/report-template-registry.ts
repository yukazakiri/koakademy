import type { LucideIcon } from "lucide-react";
import { Award, BarChart3, ClipboardList, FileText, GraduationCap, Users } from "lucide-react";

export type TemplateKey =
    "certificate_of_enrollment" | "registration_form" | "grade_report" | "enrolled_by_course" | "enrolled_by_subject" | "enrollment_summary";

export type TemplateFormat = {
    key: string;
    title: string;
    description: string;
    structure:
        | "certificate_subjects"
        | "certificate_statement"
        | "certificate_units"
        | "registration_full"
        | "registration_advising"
        | "registration_compact"
        | "grade_detailed"
        | "grade_transcript"
        | "grade_slip"
        | "roster_full"
        | "roster_signoff"
        | "roster_compact"
        | "subject_attendance"
        | "subject_faculty"
        | "subject_student_list"
        | "summary_breakdown"
        | "summary_leadership"
        | "summary_status";
    includes: string[];
    orientation: "portrait" | "landscape";
};

export type TemplateDefinition = {
    key: TemplateKey;
    title: string;
    description: string;
    group: "Student documents" | "Operational reports";
    mode: "student" | "report";
    formats: Array<"PDF" | "XLSX" | "Print">;
    icon: LucideIcon;
    variants: TemplateFormat[];
    defaultVariant: string;
};

export const TEMPLATES: TemplateDefinition[] = [
    {
        key: "certificate_of_enrollment",
        title: "Certificate of enrollment",
        description: "Formal proof of a student's current registration.",
        group: "Student documents",
        mode: "student",
        formats: ["PDF", "Print"],
        icon: Award,
        defaultVariant: "full_certificate",
        variants: [
            {
                key: "full_certificate",
                title: "Full enrollment certificate",
                description: "Certification statement followed by the student's current registered subjects and units.",
                structure: "certificate_subjects",
                includes: ["Certification statement", "Student details", "Registered subjects", "Total units"],
                orientation: "portrait",
            },
            {
                key: "verification_letter",
                title: "Enrollment verification letter",
                description: "A statement-only letter for employers, embassies, scholarship offices, and other third parties.",
                structure: "certificate_statement",
                includes: ["Formal verification", "Purpose line", "Period and status", "Registrar signature"],
                orientation: "portrait",
            },
            {
                key: "units_certificate",
                title: "Enrollment and units certificate",
                description: "A concise certificate that documents the registered load without the full class schedule.",
                structure: "certificate_units",
                includes: ["Certification statement", "Subject and unit list", "Total units", "Registrar signature"],
                orientation: "portrait",
            },
        ],
    },
    {
        key: "registration_form",
        title: "Registration form",
        description: "Printable subject registration with schedules and units.",
        group: "Student documents",
        mode: "student",
        formats: ["PDF", "Print"],
        icon: ClipboardList,
        defaultVariant: "student_copy",
        variants: [
            {
                key: "student_copy",
                title: "Student copy",
                description: "The complete registration record with section, meeting schedule, and registered units.",
                structure: "registration_full",
                includes: ["Complete schedule", "Section", "Subject details", "Total registered units"],
                orientation: "portrait",
            },
            {
                key: "adviser_copy",
                title: "Adviser review copy",
                description: "A review sheet with the student's schedule plus adviser and registrar sign-off fields.",
                structure: "registration_advising",
                includes: ["Schedule review", "Adviser sign-off", "Registrar sign-off", "Total registered units"],
                orientation: "portrait",
            },
            {
                key: "receipt_copy",
                title: "Registration receipt copy",
                description: "A counter-friendly list of registered subjects, sections, and units for quick release and filing.",
                structure: "registration_compact",
                includes: ["Subject list", "Section", "Units", "Registration period"],
                orientation: "portrait",
            },
        ],
    },
    {
        key: "grade_report",
        title: "Grade report",
        description: "Term grades with verification status and average.",
        group: "Student documents",
        mode: "student",
        formats: ["PDF", "Print"],
        icon: GraduationCap,
        defaultVariant: "official_record",
        variants: [
            {
                key: "official_record",
                title: "Official grade record",
                description: "The full term record with prelim, midterm, finals, average, and verification status.",
                structure: "grade_detailed",
                includes: ["Prelim / midterm / finals", "Average", "Verification status", "Term average"],
                orientation: "portrait",
            },
            {
                key: "transcript_style",
                title: "Transcript-style record",
                description: "A cleaner academic record focused on course, units, average, and final status.",
                structure: "grade_transcript",
                includes: ["Course and title", "Units", "Final average", "Verification status"],
                orientation: "portrait",
            },
            {
                key: "grade_slip",
                title: "Student grade slip",
                description: "A condensed pickup copy with the essential subject grades and term average.",
                structure: "grade_slip",
                includes: ["Course and title", "Final average", "Term average", "Student copy"],
                orientation: "portrait",
            },
        ],
    },
    {
        key: "enrolled_by_course",
        title: "Master enrollment list",
        description: "Students grouped by program, department, and year level.",
        group: "Operational reports",
        mode: "report",
        formats: ["PDF", "XLSX", "Print"],
        icon: Users,
        defaultVariant: "full_roster",
        variants: [
            {
                key: "full_roster",
                title: "Full registrar roster",
                description: "The complete student list with program, department, year level, subject count, and status.",
                structure: "roster_full",
                includes: ["Full student details", "Program and department", "Subject count", "Status"],
                orientation: "landscape",
            },
            {
                key: "course_signoff",
                title: "Course sign-off roster",
                description: "A course-level roster with an acknowledgement column for department or adviser review.",
                structure: "roster_signoff",
                includes: ["Student identity", "Program and year", "Review status", "Acknowledgement"],
                orientation: "landscape",
            },
            {
                key: "compact_roster",
                title: "Compact filing roster",
                description: "A reduced roster for recurring filing, circulation, and quick headcount checks.",
                structure: "roster_compact",
                includes: ["Student ID", "Full name", "Course", "Year level"],
                orientation: "landscape",
            },
        ],
    },
    {
        key: "enrolled_by_subject",
        title: "Subject roster",
        description: "Official class-facing list of students by subject.",
        group: "Operational reports",
        mode: "report",
        formats: ["PDF", "XLSX", "Print"],
        icon: FileText,
        defaultVariant: "attendance_roster",
        variants: [
            {
                key: "attendance_roster",
                title: "Attendance roster",
                description: "Subject groups with student identity and a blank signature column for each meeting.",
                structure: "subject_attendance",
                includes: ["Subject groups", "Student ID", "Section", "Attendance signature"],
                orientation: "landscape",
            },
            {
                key: "faculty_roster",
                title: "Faculty class list",
                description: "A teaching copy that keeps course, year, section, and schedule details visible at a glance.",
                structure: "subject_faculty",
                includes: ["Student identity", "Course and year", "Section", "Class schedule"],
                orientation: "landscape",
            },
            {
                key: "student_list",
                title: "Student list",
                description: "A simple list of enrolled students for posting, distribution, or classroom reference.",
                structure: "subject_student_list",
                includes: ["Student number", "Full name", "Course", "Year level"],
                orientation: "landscape",
            },
        ],
    },
    {
        key: "enrollment_summary",
        title: "Enrollment summary",
        description: "Counts by department, program, year level, and status.",
        group: "Operational reports",
        mode: "report",
        formats: ["PDF", "XLSX", "Print"],
        icon: BarChart3,
        defaultVariant: "department_breakdown",
        variants: [
            {
                key: "department_breakdown",
                title: "Department breakdown",
                description: "A detailed summary of enrollment by department, course, year level, and status.",
                structure: "summary_breakdown",
                includes: ["Department totals", "Course totals", "Year levels", "Status totals"],
                orientation: "landscape",
            },
            {
                key: "leadership_summary",
                title: "Leadership summary",
                description: "A summary-first report with headline enrollment totals and the largest course groups.",
                structure: "summary_leadership",
                includes: ["Headline total", "Department totals", "Course ranking", "Decision-ready summary"],
                orientation: "landscape",
            },
            {
                key: "status_summary",
                title: "Status summary",
                description: "A concise operational report centered on active, completed, and other enrollment statuses.",
                structure: "summary_status",
                includes: ["Total enrolled", "Status counts", "Status percentage", "Period metadata"],
                orientation: "landscape",
            },
        ],
    },
];

export const STUDENT_TEMPLATES = new Set<TemplateKey>(["certificate_of_enrollment", "registration_form", "grade_report"]);

export function getTemplateDefinition(key: TemplateKey): TemplateDefinition {
    return TEMPLATES.find((template) => template.key === key) ?? TEMPLATES[0];
}

export function getTemplateVariant(template: TemplateKey, variantKey?: string): TemplateFormat {
    const definition = getTemplateDefinition(template);

    return definition.variants.find((variant) => variant.key === variantKey) ?? definition.variants[0];
}

export function getDefaultTemplateVariants(): Record<TemplateKey, string> {
    return Object.fromEntries(TEMPLATES.map((template) => [template.key, template.defaultVariant])) as Record<TemplateKey, string>;
}
