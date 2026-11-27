import { createInertiaApp } from "@inertiajs/react";
import { createRoot } from "react-dom/client";
import { Toaster } from "sonner";
import "../../resources/css/app.css";
import CreateStudent from "../../resources/js/pages/administrators/students/create";
import Identifiers from "../../resources/js/pages/administrators/system-management/identifiers";
import StudentConfiguration from "../../resources/js/pages/administrators/system-management/student-configuration";
import type { StudentConfiguration as Configuration } from "../../resources/js/types/student-configuration";

const legacy = new URLSearchParams(location.search).has("legacy");
const categories = ["college", "shs", "tesda", "dhrt"] as const;
const labels = { college: "College / University", shs: "Senior High School", tesda: "Technical training", dhrt: "Diploma" };
const fields = {
    email: "Email",
    phone: "Phone",
    civil_status: "Civil Status",
    nationality: "Nationality",
    citizenship: "Citizenship",
    religion: "Religion",
    height: "Height",
    weight: "Weight",
    birthplace: "Birthplace",
    current_address: "Current Address",
    permanent_address: "Permanent Address",
};
const neutral: Configuration = {
    preset: "neutral",
    enabled_types: ["college"],
    default_type: "college",
    defaults: { nationality: "", citizenship: "", gender: "" },
    philippine_reporting: false,
    custom_fields: new URLSearchParams(location.search).has("custom")
        ? [{ key: "custom_1", label: "Cohort", type: "choice", active: true, required: true, options: ["Morning", "Evening"] }]
        : [],
    fields: Object.fromEntries(
        Object.entries(fields).map(([key, label]) => [key, { label, visible: !["religion", "weight", "height"].includes(key), required: false }]),
    ),
    documents: {
        birth_certificate: {
            label: "Birth Certificate / Identity Proof",
            description: "Official birth certificate or government-issued identity document (clear scan or photo)",
            visible: true,
            required: false,
        },
        form_138: {
            label: "Academic Transcript / Report Card",
            description: "Latest official academic transcript or prior school report card",
            visible: true,
            required: false,
        },
        good_moral_cert: {
            label: "Character / Transfer Reference",
            description: "Certificate of good standing, conduct, or transfer reference from previous school",
            visible: true,
            required: false,
        },
        picture_1x1: {
            label: "ID Photo",
            description: "Recent clear plain-background identification photo",
            visible: true,
            required: false,
        },
    },
    external_identifier: {
        enabled: false,
        label: "External Identifier",
        help_text: "An identifier assigned outside this institution.",
        placeholder: null,
        required: false,
        student_types: ["shs"],
        format: "text",
        min_length: 1,
        max_length: 32,
        use_as_student_id: false,
        allow_for_signup: true,
    },
    signup: {
        allow_student_self_signup: true,
        allow_faculty_self_signup: true,
        allow_identifier_recovery: true,
        require_faculty_id: false,
        student_identifier_placeholder: null,
        student_identifier_help_text: null,
        faculty_identifier_placeholder: null,
        faculty_identifier_help_text: null,
    },
    type_labels: labels,
    year_levels: Object.fromEntries(
        categories.map((type) => [
            type,
            type === "shs"
                ? [
                      { value: 11, label: "Grade 11" },
                      { value: 12, label: "Grade 12" },
                  ]
                : [
                      { value: 1, label: "1st Year" },
                      { value: 2, label: "2nd Year" },
                  ],
        ]),
    ) as Configuration["year_levels"],
};
const institution: Configuration = {
    ...neutral,
    preset: "legacy_ph",
    enabled_types: [...categories],
    defaults: { nationality: "filipino", citizenship: "filipino", gender: "male" },
    philippine_reporting: true,
    fields: Object.fromEntries(Object.entries(fields).map(([key, label]) => [key, { label, visible: true, required: false }])),
    documents: {
        birth_certificate: {
            label: "Birth Certificate",
            description: "PSA / NSO Birth Certificate (clear scan or photo)",
            visible: true,
            required: false,
        },
        form_138: {
            label: "Form 138 / Report Card",
            description: "Latest official high school or prior school report card",
            visible: true,
            required: false,
        },
        good_moral_cert: {
            label: "Good Moral Certificate",
            description: "Certificate of Good Moral Character from previous school",
            visible: true,
            required: false,
        },
        picture_1x1: {
            label: "1x1 ID Photo",
            description: "Recent clear 1x1 or 2x2 plain-background ID photo",
            visible: true,
            required: false,
        },
    },
    external_identifier: {
        ...neutral.external_identifier,
        enabled: true,
        label: "LRN",
        help_text: "Learner Reference Number assigned by the Department of Education.",
        required: true,
        max_length: 20,
        use_as_student_id: true,
    },
};
const configuration = legacy ? institution : neutral;
const sequence = {
    key: "student",
    label: "Student IDs",
    start_number: legacy ? 200000 : 1,
    next_number: legacy ? 208425 : 42,
    increment_by: 1,
    padding: 6,
    preview: legacy ? "208425" : "000042",
    prefix_mode: "none",
    prefix_value: "",
    enforce_prefix: false,
    enforce_length: false,
    exact_length: 6,
    min_length: 1,
    max_length: 64,
    type_prefixes: { college: "", shs: "", tesda: "", dhrt: "" },
    assignment_mode: "hybrid",
    character_set: "numeric",
    display_label: "Student ID",
    counter_scope: "shared",
    legacy_format: legacy,
};
const component = location.pathname.includes("identifiers") ? "identifiers" : location.pathname.includes("create") ? "create" : "configuration";
(window as unknown as { route: (name: string) => string }).route = () => "/administrators/students";

createInertiaApp({
    id: "root",
    page: {
        component,
        props: {
            errors: {},
            featureFlags: {},
            user: { id: 1, name: "Configuration QA", email: "qa@example.test", role: "developer", avatar: null },
            access: {
                can_access: true,
                sections: { identifiers: { can_view: true, can_update: true }, student_configuration: { can_view: true, can_update: true } },
            },
            student_configuration: configuration,
            student_presets: { neutral, legacy_ph: institution },
            external_identifier_presets: {},
            identifier_policies: {
                student: { display_label: "Student ID", assignment_mode: "hybrid", character_set: "numeric", legacy_format: legacy },
                staff: { display_label: "Faculty ID", assignment_mode: "hybrid", character_set: "numeric", legacy_format: legacy },
            },
            identifier_year: "2026",
            id_sequences: {
                student: sequence,
                staff: { ...sequence, key: "staff", label: "Staff IDs", start_number: 1, next_number: 84, preview: "000084" },
            },
            options: {
                configuration_scope: "school:qa",
                student_configuration: configuration,
                identifier_policy: sequence,
                types: configuration.enabled_types.map((value) => ({ value, label: labels[value] })),
                statuses: [{ value: "enrolled", label: "Enrolled" }],
                scholarship_types: [{ value: "none", label: "None" }],
                employment_statuses: [],
                attrition_categories: [],
                courses: [],
                shs_strands: [],
                religions: [],
                regions: [],
                income_modes: [],
                default_income_mode: "annual",
            },
        },
        url: location.pathname,
        version: "fixture",
        clearHistory: false,
        encryptHistory: false,
    },
    resolve: (name) => ({ identifiers: Identifiers, configuration: StudentConfiguration, create: CreateStudent })[name],
    setup: ({ el, App, props }) =>
        createRoot(el).render(
            <>
                <App {...props} />
                <Toaster />
            </>,
        ),
});
