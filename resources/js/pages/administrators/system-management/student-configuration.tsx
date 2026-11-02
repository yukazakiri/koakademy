import { Badge } from "@/components/reui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Switch } from "@/components/ui/switch";
import { update } from "@/routes/administrators/system-management/student-configuration";
import type { StudentCategory, StudentConfiguration } from "@/types/student-configuration";
import { useForm } from "@inertiajs/react";
import { Loader2, Plus, Save, Trash2 } from "lucide-react";
import type { FormEvent } from "react";
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
    const set = <K extends keyof StudentConfiguration>(key: K, value: StudentConfiguration[K]) =>
        form.setData({ ...form.data, preset: "custom", [key]: value });
    const external = form.data.external_identifier;
    const externalSet = <K extends keyof typeof external>(key: K, value: (typeof external)[K]) =>
        set("external_identifier", { ...external, [key]: value });
    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.put(update.url(), {
            preserveScroll: true,
            onSuccess: () => toast.success("Student configuration saved."),
            onError: () => toast.error("Review the configuration errors."),
        });
    };

    return (
        <SystemManagementLayout
            user={user}
            access={access}
            activeSection="student_configuration"
            heading="Student Configuration"
            description="School-owned preferences. Saving never renumbers existing students or resets identifier counters."
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
                                    onClick={() => form.setData(structuredClone(preset))}
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
                        {categories.map((type) => (
                            <fieldset key={type} className="flex min-w-0 flex-col gap-3 border-t pt-4">
                                <div className="flex items-center gap-3">
                                    <Checkbox
                                        id={`type-${type}`}
                                        disabled={disabled}
                                        checked={form.data.enabled_types.includes(type)}
                                        onCheckedChange={(checked) =>
                                            set(
                                                "enabled_types",
                                                checked
                                                    ? [...form.data.enabled_types, type]
                                                    : form.data.enabled_types.filter((item) => item !== type),
                                            )
                                        }
                                    />
                                    <Label htmlFor={`type-${type}`}>{type.toUpperCase()}</Label>
                                    <Input
                                        aria-label={`${type} display label`}
                                        value={form.data.type_labels[type]}
                                        disabled={disabled}
                                        onChange={(event) => set("type_labels", { ...form.data.type_labels, [type]: event.target.value })}
                                    />
                                </div>
                                <p className="text-muted-foreground text-xs">
                                    Year/grade values must remain stable once used; labels may be customized.
                                </p>
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
                                                        position === index ? { ...item, value: Number(event.target.value) } : item,
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
                                                    [type]: form.data.year_levels[type].filter((_, position) => position !== index),
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
                                                    value: Math.max(...form.data.year_levels[type].map((level) => level.value), 0) + 1,
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
                        ))}
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle>External identifier</CardTitle>
                        <CardDescription>
                            An externally assigned reference, stored separately from school numbering. Legacy LRN-as-ID behavior is opt-in.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-4">
                        <Label className="flex items-center gap-3">
                            <Switch checked={external.enabled} disabled={disabled} onCheckedChange={(value) => externalSet("enabled", value)} />
                            Enable external identifier
                        </Label>
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
                                                checked ? [...external.student_types, type] : external.student_types.filter((item) => item !== type),
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
                            <Switch checked={external.required} disabled={disabled} onCheckedChange={(value) => externalSet("required", value)} />
                            Required for selected categories
                        </Label>
                        <Label className="flex items-center gap-3">
                            <Switch
                                checked={external.use_as_student_id}
                                disabled={disabled}
                                onCheckedChange={(value) => externalSet("use_as_student_id", value)}
                            />
                            Use external value as student number (legacy compatibility)
                        </Label>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle>Optional form fields</CardTitle>
                        <CardDescription>
                            Core identity and academic placement stay mandatory. Hidden values are preserved when existing records are edited.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-4">
                        {Object.entries(form.data.fields).map(([key, field]) => (
                            <div key={key} className="grid items-center gap-3 border-b pb-3 sm:grid-cols-[minmax(0,1fr)_auto_auto]">
                                <Input
                                    aria-label={`${key} label`}
                                    value={field.label}
                                    disabled={disabled}
                                    onChange={(event) => set("fields", { ...form.data.fields, [key]: { ...field, label: event.target.value } })}
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
                <Button type="submit" disabled={disabled}>
                    {form.processing ? <Loader2 className="size-4 animate-spin" /> : <Save className="size-4" />}Save student configuration
                </Button>
            </form>
        </SystemManagementLayout>
    );
}
