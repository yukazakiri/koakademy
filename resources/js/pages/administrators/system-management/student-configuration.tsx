import { Badge } from "@/components/reui/badge";
import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from "@/components/ui/accordion";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Switch } from "@/components/ui/switch";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { index as identifiersIndex } from "@/routes/administrators/system-management/identifiers";
import { update } from "@/routes/administrators/system-management/student-configuration";
import type { CustomStudentField, StudentCategory, StudentConfiguration } from "@/types/student-configuration";
import { Link, useForm } from "@inertiajs/react";
import { ArrowRight, Loader2, Plus, Save, Trash2 } from "lucide-react";
import { useState, type FormEvent } from "react";
import { toast } from "sonner";
import SystemManagementLayout from "./layout";
import type { SystemManagementPageProps } from "./types";

const categories: StudentCategory[] = ["college", "shs", "tesda", "dhrt"];

export default function StudentConfigurationPage({
    user,
    access,
    student_configuration,
    student_presets,
}: SystemManagementPageProps & { student_presets: Record<string, StudentConfiguration> }) {
    const form = useForm<StudentConfiguration>(student_configuration);
    const disabled = !access.sections.student_configuration?.can_update || form.processing;
    const [section, setSection] = useState("categories");
    const [detailsOpen, setDetailsOpen] = useState(false);
    const set = <K extends keyof StudentConfiguration>(key: K, value: StudentConfiguration[K]) =>
        form.setData((previous) => ({ ...previous, preset: "custom", [key]: value }));
    const external = form.data.external_identifier;
    const customFields = form.data.custom_fields ?? [];
    const savedFields = student_configuration.custom_fields ?? [];
    const updateCustom = (index: number, changes: Partial<CustomStudentField>) =>
        set(
            "custom_fields",
            customFields.map((field, position) => (position === index ? { ...field, ...changes } : field)),
        );
    const addCustom = () => {
        // Stable opaque keys avoid collisions if two administrators add fields at once.
        const token = globalThis.crypto?.randomUUID?.().replaceAll("-", "") ?? `${Date.now().toString(36)}${Math.random().toString(36).slice(2)}`;
        const key = `custom_${token}`;
        set("custom_fields", [...customFields, { key, label: "New field", type: "text", active: true, required: false, options: [] }]);
    };
    const externalSet = <K extends keyof typeof external>(key: K, value: (typeof external)[K]) =>
        set("external_identifier", { ...external, [key]: value });
    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.put(update.url(), {
            preserveScroll: true,
            onSuccess: () => toast.success("Student configuration saved."),
            onError: (errors) => {
                const first = Object.keys(errors)[0] ?? "";
                if (first.startsWith("custom_fields") || first.startsWith("fields.")) setSection("information");
                else if (first.startsWith("external_identifier")) {
                    setSection("identifiers");
                    setDetailsOpen(true);
                } else if (
                    first.startsWith("default_type") ||
                    first.startsWith("enabled_types") ||
                    first.startsWith("type_labels") ||
                    first.startsWith("year_levels")
                )
                    setSection("categories");
                else if (first.startsWith("defaults") || first.startsWith("philippine_reporting")) setSection("defaults");
                toast.error("Review the configuration errors.");
            },
        });
    };

    return (
        <SystemManagementLayout
            user={user}
            access={access}
            activeSection="student_configuration"
            heading="Student Configuration"
            description="Shape student records for your institution. Changes never renumber students or erase saved values."
        >
            <form onSubmit={submit} className="flex flex-col gap-6">
                {Object.keys(form.errors).length > 0 && (
                    <Card>
                        <CardContent className="flex flex-col gap-2" role="alert">
                            {Object.entries(form.errors).map(([key, error]) => (
                                <p key={key} className="text-destructive text-sm">
                                    {key}: {error}
                                </p>
                            ))}
                        </CardContent>
                    </Card>
                )}
                <Card>
                    <CardHeader>
                        <CardTitle>Student records at a glance</CardTitle>
                        <CardDescription>Choose the information staff collects and how student numbers are assigned.</CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-wrap items-center gap-3">
                        <Badge variant="info-light">
                            {form.data.enabled_types.length} active {form.data.enabled_types.length === 1 ? "category" : "categories"}
                        </Badge>
                        <Badge variant="secondary">{Object.values(form.data.fields).filter((field) => field.visible).length} standard fields</Badge>
                        <Badge variant="secondary">{customFields.filter((field) => field.active).length} institution fields</Badge>
                        <Link
                            href={identifiersIndex.url()}
                            className="text-primary inline-flex items-center gap-1 text-sm font-medium hover:underline"
                        >
                            Configure student numbers <ArrowRight className="size-4" />
                        </Link>
                    </CardContent>
                </Card>
                <Tabs value={section} onValueChange={(value) => setSection(value)}>
                    <div className="max-w-full overflow-x-auto" aria-label="Student settings sections">
                        <TabsList>
                            <TabsTrigger value="categories">Categories</TabsTrigger>
                            <TabsTrigger value="information">Student information</TabsTrigger>
                            <TabsTrigger value="identifiers">Regional identifiers</TabsTrigger>
                            <TabsTrigger value="defaults">Defaults &amp; reporting</TabsTrigger>
                        </TabsList>
                    </div>
                    <p className="text-muted-foreground text-xs sm:hidden">Swipe left or right to see all settings sections.</p>
                    <TabsContent value="defaults">
                        <Card>
                            <CardHeader>
                                <CardTitle>Starting preferences</CardTitle>
                                <CardDescription>Loading a preset changes this draft only. Review it and save explicitly.</CardDescription>
                            </CardHeader>
                            <CardContent className="flex flex-col gap-4">
                                <Badge variant="info-light">
                                    {student_configuration.preset === "legacy_ph" ? "Existing installation preserved" : "School configuration"}
                                </Badge>
                                <div className="flex flex-wrap gap-2">
                                    {Object.entries(student_presets).map(([key, preset]) => (
                                        <Button
                                            key={key}
                                            type="button"
                                            variant="outline"
                                            disabled={disabled}
                                            onClick={() => {
                                                if (
                                                    !confirm(
                                                        "Load this starting profile? Unsaved settings will be replaced. Institution-specific fields will be kept.",
                                                    )
                                                )
                                                    return;
                                                form.setData({ ...structuredClone(preset), custom_fields: customFields });
                                            }}
                                        >
                                            {key === "neutral" ? "Load neutral defaults" : "Load Philippine legacy preferences"}
                                        </Button>
                                    ))}
                                </div>
                                <div className="grid gap-4 md:grid-cols-2">
                                    {(["nationality", "citizenship"] as const).map((key) => (
                                        <div className="flex flex-col gap-2" key={key}>
                                            <Label htmlFor={`default-${key}`}>Default {key}</Label>
                                            <Input
                                                id={`default-${key}`}
                                                value={form.data.defaults[key] ?? ""}
                                                disabled={disabled}
                                                onChange={(event) => set("defaults", { ...form.data.defaults, [key]: event.target.value })}
                                                placeholder="No preselected value"
                                            />
                                        </div>
                                    ))}
                                </div>
                                <div className="flex flex-col gap-2">
                                    <Label htmlFor="default-gender">Default gender</Label>
                                    <Select
                                        value={form.data.defaults.gender || "none"}
                                        disabled={disabled}
                                        onValueChange={(value) => set("defaults", { ...form.data.defaults, gender: value === "none" ? "" : value })}
                                    >
                                        <SelectTrigger id="default-gender">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="none">No preselected value</SelectItem>
                                            <SelectItem value="male">Male</SelectItem>
                                            <SelectItem value="female">Female</SelectItem>
                                            <SelectItem value="other">Other</SelectItem>
                                            <SelectItem value="prefer_not_to_say">Prefer not to say</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <Label className="flex items-center gap-3">
                                    <Switch
                                        checked={form.data.philippine_reporting}
                                        disabled={disabled}
                                        onCheckedChange={(value) => set("philippine_reporting", value)}
                                    />
                                    Philippine geography and reporting controls
                                </Label>
                            </CardContent>
                        </Card>
                    </TabsContent>
                    <TabsContent value="categories">
                        <Card>
                            <CardHeader>
                                <CardTitle>Student categories</CardTitle>
                                <CardDescription>
                                    Only supported categories are configurable. Disabling one does not delete its existing records.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="flex flex-col gap-6">
                                <div className="flex flex-col gap-2">
                                    <Label htmlFor="default-type">Default category</Label>
                                    <Select
                                        value={form.data.default_type}
                                        onValueChange={(value) => set("default_type", value as StudentCategory)}
                                        disabled={disabled}
                                    >
                                        <SelectTrigger id="default-type">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {form.data.enabled_types.map((type) => (
                                                <SelectItem key={type} value={type}>
                                                    {form.data.type_labels[type]}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                                <Accordion type="multiple">
                                    {categories.map((type) => (
                                        <AccordionItem key={type} value={type}>
                                            <AccordionTrigger>
                                                {form.data.type_labels[type]} · {form.data.enabled_types.includes(type) ? "Enabled" : "Disabled"}
                                            </AccordionTrigger>
                                            <AccordionContent>
                                                <fieldset className="flex min-w-0 flex-col gap-3">
                                                    <div className="flex items-center gap-3">
                                                        <Checkbox
                                                            id={`type-${type}`}
                                                            disabled={disabled}
                                                            checked={form.data.enabled_types.includes(type)}
                                                            onCheckedChange={(checked) => {
                                                                const enabled = checked
                                                                    ? [...form.data.enabled_types, type]
                                                                    : form.data.enabled_types.filter((item) => item !== type);
                                                                if (enabled.length === 0) {
                                                                    toast.error("Keep at least one student category enabled.");
                                                                    return;
                                                                }
                                                                form.setData((previous) => ({
                                                                    ...previous,
                                                                    preset: "custom",
                                                                    enabled_types: enabled,
                                                                    default_type: enabled.includes(previous.default_type)
                                                                        ? previous.default_type
                                                                        : enabled[0],
                                                                }));
                                                            }}
                                                        />
                                                        <Label htmlFor={`type-${type}`}>{type.toUpperCase()}</Label>
                                                        <Input
                                                            aria-label={`${type} display label`}
                                                            value={form.data.type_labels[type]}
                                                            disabled={disabled}
                                                            onChange={(event) =>
                                                                set("type_labels", { ...form.data.type_labels, [type]: event.target.value })
                                                            }
                                                        />
                                                    </div>
                                                    <p className="text-muted-foreground text-xs">
                                                        Year/grade values must remain stable once used; labels may be customized.
                                                    </p>
                                                    <div className="flex flex-wrap items-center gap-2">
                                                        <span className="text-muted-foreground text-sm">Quick setup:</span>
                                                        {[2, 4, 6].map((count) => (
                                                            <Button
                                                                key={count}
                                                                type="button"
                                                                size="sm"
                                                                variant="outline"
                                                                disabled={disabled}
                                                                onClick={() => {
                                                                    if (
                                                                        !confirm(
                                                                            `Replace the ${form.data.type_labels[type]} year/grade options with ${count} standard levels? Existing student records will not be changed.`,
                                                                        )
                                                                    )
                                                                        return;
                                                                    set("year_levels", {
                                                                        ...form.data.year_levels,
                                                                        [type]: Array.from({ length: count }, (_, index) => ({
                                                                            value: type === "shs" ? index + 11 : index + 1,
                                                                            label: type === "shs" ? `Grade ${index + 11}` : `Year ${index + 1}`,
                                                                        })),
                                                                    });
                                                                }}
                                                            >
                                                                {count} {type === "shs" ? "grades" : "years"}
                                                            </Button>
                                                        ))}
                                                    </div>
                                                    {form.data.year_levels[type].map((level, index) => (
                                                        <div key={index} className="flex gap-2">
                                                            <Input
                                                                aria-label={`${type} level ${index + 1} value`}
                                                                type="number"
                                                                min={1}
                                                                max={99}
                                                                value={level.value}
                                                                disabled={disabled}
                                                                onChange={(event) =>
                                                                    set("year_levels", {
                                                                        ...form.data.year_levels,
                                                                        [type]: form.data.year_levels[type].map((item, position) =>
                                                                            position === index
                                                                                ? { ...item, value: Number(event.target.value) }
                                                                                : item,
                                                                        ),
                                                                    })
                                                                }
                                                            />
                                                            <Input
                                                                aria-label={`${type} level ${index + 1} label`}
                                                                value={level.label}
                                                                disabled={disabled}
                                                                onChange={(event) =>
                                                                    set("year_levels", {
                                                                        ...form.data.year_levels,
                                                                        [type]: form.data.year_levels[type].map((item, position) =>
                                                                            position === index ? { ...item, label: event.target.value } : item,
                                                                        ),
                                                                    })
                                                                }
                                                            />
                                                            <Button
                                                                type="button"
                                                                variant="ghost"
                                                                size="icon"
                                                                aria-label="Remove level"
                                                                disabled={disabled}
                                                                onClick={() =>
                                                                    set("year_levels", {
                                                                        ...form.data.year_levels,
                                                                        [type]: form.data.year_levels[type].filter(
                                                                            (_, position) => position !== index,
                                                                        ),
                                                                    })
                                                                }
                                                            >
                                                                <Trash2 className="size-4" />
                                                            </Button>
                                                        </div>
                                                    ))}
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        disabled={disabled}
                                                        onClick={() =>
                                                            set("year_levels", {
                                                                ...form.data.year_levels,
                                                                [type]: [
                                                                    ...form.data.year_levels[type],
                                                                    {
                                                                        value:
                                                                            Math.max(...form.data.year_levels[type].map((level) => level.value), 0) +
                                                                            1,
                                                                        label: "New level",
                                                                    },
                                                                ],
                                                            })
                                                        }
                                                    >
                                                        <Plus className="size-4" />
                                                        Add level
                                                    </Button>
                                                </fieldset>
                                            </AccordionContent>
                                        </AccordionItem>
                                    ))}
                                </Accordion>
                            </CardContent>
                        </Card>
                    </TabsContent>
                    <TabsContent value="identifiers">
                        <Card>
                            <CardHeader>
                                <CardTitle>Government or regional identifier</CardTitle>
                                <CardDescription>
                                    Optional. Collect an identifier assigned outside your institution, such as the Philippine DepEd Learner Reference
                                    Number (LRN). Most institutions can leave this off.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="flex flex-col gap-4">
                                <Label className="flex items-center gap-3">
                                    <Switch
                                        checked={external.enabled}
                                        disabled={disabled}
                                        onCheckedChange={(value) => externalSet("enabled", value)}
                                    />
                                    Collect a government or regional identifier
                                </Label>
                                <div>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        disabled={disabled}
                                        onClick={() => {
                                            if (
                                                external.enabled &&
                                                !confirm(
                                                    "Replace the current regional identifier settings with the LRN shortcut? Existing student values and numbers will not change, but legacy LRN-as-student-number mode will be switched off.",
                                                )
                                            )
                                                return;
                                            set("external_identifier", {
                                                ...external,
                                                enabled: true,
                                                label: "LRN",
                                                help_text: "Learner Reference Number assigned by the Department of Education.",
                                                student_types: ["shs"],
                                                format: "digits",
                                                min_length: 12,
                                                max_length: 12,
                                                required: false,
                                                use_as_student_id: false,
                                            });
                                        }}
                                    >
                                        Use Philippine LRN settings
                                    </Button>
                                </div>
                                {external.enabled && (
                                    <p className="text-muted-foreground text-sm">
                                        Collecting <strong className="text-foreground">{external.label}</strong> for{" "}
                                        {external.student_types.map((type) => form.data.type_labels[type]).join(", ") || "no categories selected"}.{" "}
                                        {external.required ? "Required." : "Optional."}{" "}
                                        {external.use_as_student_id ? "Used as the student number." : "Kept separate from the student number."}
                                    </p>
                                )}
                                <Button type="button" variant="ghost" onClick={() => setDetailsOpen(!detailsOpen)} aria-expanded={detailsOpen}>
                                    {detailsOpen ? "Hide identifier details" : "Customize identifier details"}
                                </Button>
                                {detailsOpen && (
                                    <>
                                        <div className="grid gap-4 md:grid-cols-2">
                                            <div className="flex flex-col gap-2">
                                                <Label htmlFor="external-label">Label</Label>
                                                <Input
                                                    id="external-label"
                                                    value={external.label}
                                                    disabled={disabled}
                                                    onChange={(event) => externalSet("label", event.target.value)}
                                                />
                                            </div>
                                            <div className="flex flex-col gap-2">
                                                <Label htmlFor="external-help">Help text</Label>
                                                <Input
                                                    id="external-help"
                                                    value={external.help_text ?? ""}
                                                    disabled={disabled}
                                                    onChange={(event) => externalSet("help_text", event.target.value)}
                                                />
                                            </div>
                                        </div>
                                        <div className="flex flex-wrap gap-4">
                                            {categories.map((type) => (
                                                <Label key={type} className="flex items-center gap-2">
                                                    <Checkbox
                                                        checked={external.student_types.includes(type)}
                                                        disabled={disabled}
                                                        onCheckedChange={(checked) =>
                                                            externalSet(
                                                                "student_types",
                                                                checked
                                                                    ? [...external.student_types, type]
                                                                    : external.student_types.filter((item) => item !== type),
                                                            )
                                                        }
                                                    />
                                                    {form.data.type_labels[type]}
                                                </Label>
                                            ))}
                                        </div>
                                        <div className="grid gap-4 sm:grid-cols-3">
                                            <div className="flex flex-col gap-2">
                                                <Label htmlFor="external-format">Format</Label>
                                                <Select
                                                    value={external.format}
                                                    disabled={disabled}
                                                    onValueChange={(value) => externalSet("format", value as "text" | "digits")}
                                                >
                                                    <SelectTrigger id="external-format">
                                                        <SelectValue />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem value="text">Text</SelectItem>
                                                        <SelectItem value="digits">Digits only</SelectItem>
                                                    </SelectContent>
                                                </Select>
                                            </div>
                                            {(["min_length", "max_length"] as const).map((key) => (
                                                <div key={key} className="flex flex-col gap-2">
                                                    <Label htmlFor={key}>{key === "min_length" ? "Minimum length" : "Maximum length"}</Label>
                                                    <Input
                                                        id={key}
                                                        type="number"
                                                        min={1}
                                                        max={32}
                                                        value={external[key]}
                                                        disabled={disabled}
                                                        onChange={(event) => externalSet(key, Number(event.target.value))}
                                                    />
                                                </div>
                                            ))}
                                        </div>
                                        <Label className="flex items-center gap-3">
                                            <Switch
                                                checked={external.required}
                                                disabled={disabled}
                                                onCheckedChange={(value) => externalSet("required", value)}
                                            />
                                            Required for selected categories
                                        </Label>
                                        <Label className="flex items-center gap-3">
                                            <Switch
                                                checked={external.use_as_student_id}
                                                disabled={disabled}
                                                onCheckedChange={(value) => externalSet("use_as_student_id", value)}
                                            />
                                            Use this identifier as the student number (legacy compatibility)
                                        </Label>
                                    </>
                                )}
                            </CardContent>
                        </Card>
                    </TabsContent>
                    <TabsContent value="information">
                        <Card>
                            <CardHeader>
                                <CardTitle>Standard student fields</CardTitle>
                                <CardDescription>
                                    Core identity and academic placement stay mandatory. Hidden values are preserved when existing records are edited.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="flex flex-col gap-4">
                                <p className="text-muted-foreground text-sm">
                                    Choose which built-in fields to collect. Required built-in fields may direct unsupported creation channels to the
                                    administrator form.
                                </p>
                                {Object.entries(form.data.fields).map(([key, field]) => (
                                    <div key={key} className="grid items-center gap-3 border-b pb-3 sm:grid-cols-[minmax(0,1fr)_auto_auto]">
                                        <Input
                                            aria-label={`${key} label`}
                                            value={field.label}
                                            disabled={disabled}
                                            onChange={(event) =>
                                                set("fields", { ...form.data.fields, [key]: { ...field, label: event.target.value } })
                                            }
                                        />
                                        <Label className="flex items-center gap-2">
                                            <Checkbox
                                                checked={field.visible}
                                                disabled={disabled}
                                                onCheckedChange={(checked) =>
                                                    set("fields", {
                                                        ...form.data.fields,
                                                        [key]: { ...field, visible: checked === true, required: checked === true && field.required },
                                                    })
                                                }
                                            />
                                            Visible
                                        </Label>
                                        <Label className="flex items-center gap-2">
                                            <Checkbox
                                                checked={field.required}
                                                disabled={disabled || !field.visible}
                                                onCheckedChange={(checked) =>
                                                    set("fields", { ...form.data.fields, [key]: { ...field, required: checked === true } })
                                                }
                                            />
                                            Required
                                        </Label>
                                    </div>
                                ))}
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader>
                                <CardTitle>Institution-specific fields</CardTitle>
                                <CardDescription>
                                    Add fields for information unique to your institution. Staff can enter these on the administrator student form.
                                    Archived values are kept.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="flex flex-col gap-4">
                                {customFields.length === 0 && (
                                    <p className="text-muted-foreground text-sm">
                                        No institution-specific fields yet. Add one only if the standard fields do not cover your needs.
                                    </p>
                                )}
                                {customFields.map((field, index) => {
                                    const saved = savedFields.some((item) => item.key === field.key);
                                    return (
                                        <fieldset key={field.key} className="flex flex-col gap-3 rounded-md border p-4">
                                            <div className="flex flex-wrap items-center justify-between gap-2">
                                                <legend className="font-medium">{field.label || "Untitled field"}</legend>
                                                <Badge variant={field.active ? "info-light" : "secondary"}>
                                                    {field.active ? "Active" : "Archived"}
                                                </Badge>
                                            </div>
                                            <div className="grid gap-3 sm:grid-cols-2">
                                                <div className="flex flex-col gap-2">
                                                    <Label htmlFor={`custom-label-${field.key}`}>Field label</Label>
                                                    <Input
                                                        id={`custom-label-${field.key}`}
                                                        value={field.label}
                                                        disabled={disabled}
                                                        onChange={(event) => updateCustom(index, { label: event.target.value })}
                                                    />
                                                </div>
                                                <div className="flex flex-col gap-2">
                                                    <Label htmlFor={`custom-type-${field.key}`}>Answer type</Label>
                                                    <Select
                                                        value={field.type}
                                                        disabled={disabled || saved}
                                                        onValueChange={(value) =>
                                                            updateCustom(index, {
                                                                type: value as CustomStudentField["type"],
                                                                options: value === "choice" ? ["Option 1"] : [],
                                                            })
                                                        }
                                                    >
                                                        <SelectTrigger id={`custom-type-${field.key}`}>
                                                            <SelectValue />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem value="text">Short text</SelectItem>
                                                            <SelectItem value="number">Number</SelectItem>
                                                            <SelectItem value="date">Date</SelectItem>
                                                            <SelectItem value="choice">Choose one</SelectItem>
                                                        </SelectContent>
                                                    </Select>
                                                </div>
                                            </div>
                                            {saved && (
                                                <p className="text-muted-foreground text-xs">
                                                    Answer type is locked after saving to protect existing responses.
                                                </p>
                                            )}
                                            {field.type === "choice" && (
                                                <div className="flex flex-col gap-2">
                                                    <Label>Choices</Label>
                                                    {field.options.map((option, position) => (
                                                        <div key={position} className="flex gap-2">
                                                            <Input
                                                                aria-label={`${field.label} option ${position + 1}`}
                                                                value={option}
                                                                disabled={disabled}
                                                                onChange={(event) =>
                                                                    updateCustom(index, {
                                                                        options: field.options.map((item, i) =>
                                                                            i === position ? event.target.value : item,
                                                                        ),
                                                                    })
                                                                }
                                                            />
                                                            <Button
                                                                type="button"
                                                                variant="ghost"
                                                                size="icon"
                                                                aria-label="Remove choice"
                                                                disabled={disabled}
                                                                onClick={() =>
                                                                    updateCustom(index, { options: field.options.filter((_, i) => i !== position) })
                                                                }
                                                            >
                                                                <Trash2 className="size-4" />
                                                            </Button>
                                                        </div>
                                                    ))}
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        disabled={disabled}
                                                        onClick={() =>
                                                            updateCustom(index, { options: [...field.options, `Option ${field.options.length + 1}`] })
                                                        }
                                                    >
                                                        Add choice
                                                    </Button>
                                                </div>
                                            )}
                                            <div className="flex flex-wrap items-center gap-4">
                                                <Label className="flex items-center gap-2">
                                                    <Switch
                                                        checked={field.active}
                                                        disabled={disabled}
                                                        onCheckedChange={(active) =>
                                                            updateCustom(index, { active, required: active ? field.required : false })
                                                        }
                                                    />
                                                    Active
                                                </Label>
                                                <Label className="flex items-center gap-2">
                                                    <Checkbox
                                                        checked={field.required}
                                                        disabled={disabled || !field.active}
                                                        onCheckedChange={(required) => updateCustom(index, { required: required === true })}
                                                    />
                                                    Required for new records
                                                </Label>
                                                {!saved && (
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        disabled={disabled}
                                                        onClick={() =>
                                                            set(
                                                                "custom_fields",
                                                                customFields.filter((_, i) => i !== index),
                                                            )
                                                        }
                                                    >
                                                        Remove draft field
                                                    </Button>
                                                )}
                                            </div>
                                        </fieldset>
                                    );
                                })}
                                <Button type="button" variant="outline" disabled={disabled || customFields.length >= 30} onClick={addCustom}>
                                    <Plus className="size-4" /> Add institution field
                                </Button>
                                <p className="text-muted-foreground text-sm">
                                    These fields are available in administrator student records. Saving one as required closes public online
                                    registration and blocks student creation through API, Filament, and AI/MCP until it is optional again; staff can
                                    still create students in the administrator form.
                                </p>
                            </CardContent>
                        </Card>
                    </TabsContent>
                </Tabs>
                <div className="flex flex-wrap items-center justify-end gap-3">
                    <span className="text-muted-foreground text-sm">{form.isDirty ? "Unsaved changes" : "All changes saved"}</span>
                    <Button type="submit" disabled={disabled}>
                        {form.processing ? <Loader2 className="size-4 animate-spin" /> : <Save className="size-4" />}Save student configuration
                    </Button>
                </div>
            </form>
        </SystemManagementLayout>
    );
}
