import type {
    BlueprintStepId,
    Configuration,
    EffectiveConfiguration,
    OperatorField,
    OperatorSchema,
    PolicySource,
    RegistryItem,
    Rule,
} from "./types";

export const blueprintSteps: { id: BlueprintStepId; title: string; shortTitle: string; description: string }[] = [
    { id: "scope", title: "Choose template and scope", shortTitle: "Scope", description: "Decide which students this policy covers." },
    {
        id: "eligibility",
        title: "Availability and eligibility",
        shortTitle: "Eligibility",
        description: "Choose when enrollment opens and who may enroll.",
    },
    { id: "documents", title: "Required documents", shortTitle: "Documents", description: "List what students must submit." },
    {
        id: "assignment",
        title: "Subjects and classes",
        shortTitle: "Assignment",
        description: "Choose how subjects and class sections are selected.",
    },
    { id: "billing", title: "Tuition and payment", shortTitle: "Billing", description: "Set fees, discounts, payment methods, and gates." },
    { id: "workflow", title: "Approvals and notifications", shortTitle: "Workflow", description: "Build the staff approval journey and messages." },
    {
        id: "publish",
        title: "Test, publish, and activate",
        shortTitle: "Publish",
        description: "Simulate the policy before it affects future enrollments.",
    },
];

export function copy<T>(value: T): T {
    return JSON.parse(JSON.stringify(value)) as T;
}

export function slug(value: string): string {
    return value
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9]+/g, "_")
        .replace(/^_|_$/g, "");
}

export function normalizeConfiguration(configuration: Configuration | null | undefined): EffectiveConfiguration {
    return {
        schema_version: configuration?.schema_version ?? 1,
        rules: copy(configuration?.rules ?? []),
        requirements: copy(configuration?.requirements ?? []),
        assignment: copy(configuration?.assignment ?? { strategy: "assignment.manual", configuration: {} }),
        billing: copy(
            configuration?.billing ?? {
                strategy: "billing.course_rate",
                configuration: {
                    nstp_lecture_multiplier: 0.5,
                    modular_laboratory_multiplier: 0.5,
                    modular_fee: 2400,
                    miscellaneous_fee_fallback: 3500,
                    discount_scope: "lecture_only",
                    allow_overall_override: true,
                    receipt_mode: "required",
                    minimum_payment: { type: "none", value: 0 },
                },
                allowed_payment_methods: [],
            },
        ),
        workflow: copy(configuration?.workflow ?? { steps: [] }),
        notifications: copy(configuration?.notifications ?? []),
    };
}

export function mergeConfigurations(inherited: Configuration | null | undefined, local: Configuration): EffectiveConfiguration {
    const base = normalizeConfiguration(inherited);
    const rules = mergeKeyed(base.rules, local.rules);
    const requirements = mergeKeyed(base.requirements, local.requirements);

    return normalizeConfiguration({
        ...base,
        ...local,
        rules,
        requirements,
        assignment: local.assignment ?? base.assignment,
        billing: local.billing ?? base.billing,
        workflow: local.workflow ?? base.workflow,
        notifications: local.notifications ?? base.notifications,
    });
}

function mergeKeyed<T extends { key: string; enabled?: boolean }>(inherited: T[], overrides: T[] | undefined): T[] {
    const entries = new Map(inherited.map((entry) => [entry.key, copy(entry)]));
    for (const override of overrides ?? []) {
        if (override.enabled === false) {
            entries.delete(override.key);
        } else {
            entries.set(override.key, { ...(entries.get(override.key) ?? ({} as T)), ...copy(override) });
        }
    }

    return [...entries.values()];
}

export function operatorDefaults(item?: RegistryItem): Record<string, unknown> {
    return Object.fromEntries(
        (item?.operator_schema?.fields ?? []).map((field) => {
            if (field.recommended !== undefined && field.recommended !== null) return [field.key, copy(field.recommended)];
            if (field.control === "multi_select") return [field.key, []];
            if (field.control === "date_range") return [field.key, { starts_at: "", ends_at: "" }];
            if (field.control === "boolean") return [field.key, false];
            return [field.key, field.options?.[0]?.value ?? ""];
        }),
    );
}

export function newRule(handler: string, rules: Rule[], item?: RegistryItem): Rule {
    return {
        key: `${slug(handler)}_${rules.length + 1}`,
        handler,
        enabled: true,
        configuration: operatorDefaults(item),
    };
}

export function schemaHelp(item?: RegistryItem): OperatorSchema {
    return {
        description: item?.operator_schema?.description ?? "Configure this enrollment behavior.",
        what_it_does: item?.operator_schema?.what_it_does ?? item?.operator_schema?.description ?? "Controls part of the enrollment process.",
        impact: item?.operator_schema?.impact ?? "This setting applies whenever the selected policy scope matches a student.",
        example: item?.operator_schema?.example ?? "Keep the recommended value first, then confirm it with simulation.",
        docs_anchor: item?.operator_schema?.docs_anchor ?? "overview",
        fields: item?.operator_schema?.fields ?? [],
    };
}

export function fieldIsVisible(field: OperatorField, values: Record<string, unknown>): boolean {
    if (!field.visible_when) return true;
    const actual = values[field.visible_when.field];
    if (field.visible_when.equals !== undefined) return actual === field.visible_when.equals;
    if (field.visible_when.in) return field.visible_when.in.includes(actual);
    return true;
}

export function sectionSource(sourceMap: Record<string, PolicySource>, section: string, key?: string): PolicySource | undefined {
    return sourceMap[key ? `${section}.${key}` : section];
}

export function configurationFingerprint(configuration: Configuration): string {
    return JSON.stringify(configuration);
}

export function sectionIsComplete(step: BlueprintStepId, configuration: EffectiveConfiguration): boolean {
    return (
        {
            scope: true,
            eligibility: configuration.rules.length > 0,
            documents: true,
            assignment: Boolean(configuration.assignment.strategy),
            billing: Boolean(configuration.billing.strategy),
            workflow: configuration.workflow.steps.length > 0 && configuration.workflow.steps.some((item) => item.terminal),
            publish: false,
        } satisfies Record<BlueprintStepId, boolean>
    )[step];
}
