import type { LucideIcon } from "lucide-react";
import { Award, BarChart3, ClipboardList, FileText, GraduationCap, Users } from "lucide-react";

export type TemplateKey =
    "certificate_of_enrollment" | "registration_form" | "grade_report" | "enrolled_by_course" | "enrolled_by_subject" | "enrollment_summary";

export type TemplateVariant = {
    key: string;
    title: string;
    description: string;
    layout: "classic" | "modern" | "compact" | "statement" | "summary";
    accent: "ink" | "blue" | "gold" | "slate";
};

export type TemplateDefinition = {
    key: TemplateKey;
    title: string;
    description: string;
    group: "Student documents" | "Operational reports";
    mode: "student" | "report";
    formats: Array<"PDF" | "XLSX" | "Print">;
    icon: LucideIcon;
    variants: TemplateVariant[];
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
        defaultVariant: "classic",
        variants: [
            {
                key: "classic",
                title: "Classic registrar",
                description: "Traditional letterhead with a centered certification statement.",
                layout: "classic",
                accent: "ink",
            },
            {
                key: "institutional",
                title: "Institutional letterhead",
                description: "Formal centered masthead for government, embassy, and employer requests.",
                layout: "statement",
                accent: "gold",
            },
            {
                key: "compact",
                title: "Compact verification",
                description: "A concise one-page certificate with a small enrollment summary.",
                layout: "compact",
                accent: "slate",
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
        defaultVariant: "standard",
        variants: [
            {
                key: "standard",
                title: "Standard registration",
                description: "Balanced subject list for the student's official record.",
                layout: "classic",
                accent: "ink",
            },
            {
                key: "advising",
                title: "Advising worksheet",
                description: "Roomier schedule columns for advising and signature review.",
                layout: "modern",
                accent: "blue",
            },
            {
                key: "compact",
                title: "Compact registration",
                description: "Dense format for quick counter service and filing.",
                layout: "compact",
                accent: "slate",
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
        defaultVariant: "official",
        variants: [
            {
                key: "official",
                title: "Official grade report",
                description: "Formal term record with signatures and verification status.",
                layout: "classic",
                accent: "ink",
            },
            {
                key: "detailed",
                title: "Detailed academic record",
                description: "A clearer hierarchy for prelim, midterm, finals, and averages.",
                layout: "modern",
                accent: "blue",
            },
            {
                key: "compact",
                title: "Compact grade slip",
                description: "A condensed format for quick student pickup and filing.",
                layout: "compact",
                accent: "slate",
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
        defaultVariant: "standard",
        variants: [
            {
                key: "standard",
                title: "Standard operations",
                description: "Full-width roster for registrar and department review.",
                layout: "classic",
                accent: "ink",
            },
            {
                key: "executive",
                title: "Executive summary",
                description: "More breathing room and clearer report-level hierarchy.",
                layout: "summary",
                accent: "gold",
            },
            {
                key: "compact",
                title: "Compact filing copy",
                description: "Tighter rows for printing and physical filing.",
                layout: "compact",
                accent: "slate",
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
        defaultVariant: "standard",
        variants: [
            {
                key: "standard",
                title: "Standard roster",
                description: "Subject groups with section and student details.",
                layout: "classic",
                accent: "ink",
            },
            {
                key: "faculty",
                title: "Faculty handout",
                description: "Readable roster hierarchy for instructors and classrooms.",
                layout: "modern",
                accent: "blue",
            },
            {
                key: "compact",
                title: "Compact roster",
                description: "Dense list for attendance folders and quick reference.",
                layout: "compact",
                accent: "slate",
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
        defaultVariant: "standard",
        variants: [
            {
                key: "standard",
                title: "Standard summary",
                description: "Balanced tables for regular registrar reporting.",
                layout: "classic",
                accent: "ink",
            },
            {
                key: "executive",
                title: "Executive dashboard",
                description: "Summary-first presentation for leadership review.",
                layout: "summary",
                accent: "gold",
            },
            {
                key: "compact",
                title: "Compact summary",
                description: "Condensed report for recurring filing and circulation.",
                layout: "compact",
                accent: "slate",
            },
        ],
    },
];

export const STUDENT_TEMPLATES = new Set<TemplateKey>(["certificate_of_enrollment", "registration_form", "grade_report"]);

export function getTemplateDefinition(key: TemplateKey): TemplateDefinition {
    return TEMPLATES.find((template) => template.key === key) ?? TEMPLATES[0];
}

export function getTemplateVariant(template: TemplateKey, variantKey?: string): TemplateVariant {
    const definition = getTemplateDefinition(template);

    return definition.variants.find((variant) => variant.key === variantKey) ?? definition.variants[0];
}

export function getDefaultTemplateVariants(): Record<TemplateKey, string> {
    return Object.fromEntries(TEMPLATES.map((template) => [template.key, template.defaultVariant])) as Record<TemplateKey, string>;
}
