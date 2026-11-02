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
    external_identifier: {
        enabled: false,
        label: "External Identifier",
        help_text: "An identifier assigned outside this institution.",
        required: false,
        student_types: ["shs"],
        format: "text",
        min_length: 1,
        max_length: 32,
        use_as_student_id: false,
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
