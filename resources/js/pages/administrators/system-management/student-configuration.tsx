import { Alert, AlertDescription, AlertTitle } from "@/components/reui/alert";
import { Badge } from "@/components/reui/badge";
import { Frame, FrameDescription, FramePanel, FrameTitle } from "@/components/reui/frame";
import { IconTile } from "@/components/reui/icon-tile";
import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from "@/components/ui/accordion";
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from "@/components/reui/alert-dialog";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Switch } from "@/components/ui/switch";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { cn } from "@/lib/utils";
import { index as identifiersIndex } from "@/routes/administrators/system-management/identifiers";
import { update } from "@/routes/administrators/system-management/student-configuration";
import type { CustomStudentField, StudentCategory, StudentConfiguration } from "@/types/student-configuration";
import { Link, useForm } from "@inertiajs/react";
import {
    AlertCircle,
    ArrowRight,
    BookOpen,
    Contact,
    Globe2,
    GraduationCap,
    Hash,
    Layers3,
    Loader2,
    Plus,
    Save,
    Settings2,
    Trash2,
    UserRound,
    UsersRound,
} from "lucide-react";
import { useEffect, useRef, useState, type FormEvent } from "react";
import { toast } from "sonner";
import SystemManagementLayout from "./layout";
import type { SystemManagementPageProps } from "./types";

const categories: StudentCategory[] = ["college", "shs", "tesda", "dhrt"];
const sectionLabels = {
    categories: "Tracks & progression",
    information: "Student profile fields",
    identifiers: "National & regional registries",
    defaults: "Admission defaults",
};
type SettingsSection = keyof typeof sectionLabels;
type Confirmation = { title: string; description: string; action: string; apply: () => void };
const fieldGroups = [
    { title: "Contact & communication", description: "Ways to reach a student.", keys: ["email", "phone"], icon: Contact },
    {
        title: "Demographics & background",
        description: "Personal information your institution chooses to collect.",
        keys: ["civil_status", "nationality", "citizenship", "religion", "birthplace", "height", "weight"],
        icon: UserRound,
    },
    {
        title: "Residence & address",
        description: "Where a student lives or can be contacted.",
        keys: ["current_address", "permanent_address"],
        icon: Globe2,
    },
];
const categoryIcons = { college: GraduationCap, shs: BookOpen, tesda: Settings2, dhrt: Layers3 };

function SettingsPanel({ title, description, children }: { title: string; description?: string; children: React.ReactNode }) {
    return (
        <Frame>
            <FramePanel>
                <div className="flex flex-col gap-4">
                    <div>
                        <FrameTitle>{title}</FrameTitle>
                        {description && <FrameDescription>{description}</FrameDescription>}
                    </div>
                    {children}
                </div>
            </FramePanel>
        </Frame>
    );
}

export default function StudentConfigurationPage({
    user,
    access,
    student_configuration,
    student_presets,
}: SystemManagementPageProps & { student_presets: Record<string, StudentConfiguration> }) {
    const form = useForm<StudentConfiguration>(student_configuration);
    const disabled = !access.sections.student_configuration?.can_update || form.processing;
    const [section, setSection] = useState<SettingsSection>("categories");
    const [detailsOpen, setDetailsOpen] = useState(false);
    const [confirmation, setConfirmation] = useState<Confirmation | null>(null);
    const [openTracks, setOpenTracks] = useState<string[]>([]);
    const errorSummaryRef = useRef<HTMLDivElement>(null);
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
    const save = () => {
        if (disabled || !form.isDirty) return;
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
                window.requestAnimationFrame(() => errorSummaryRef.current?.focus());
            },
        });
    };
    const submit = (event: FormEvent) => {
        event.preventDefault();
        save();
    };
    useEffect(() => {
        const keydown = (event: KeyboardEvent) => {
            if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === "s") {
                event.preventDefault();
                save();
            }
        };
        window.addEventListener("keydown", keydown);
        return () => window.removeEventListener("keydown", keydown);
    });

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
                    <Alert variant="destructive" ref={errorSummaryRef} tabIndex={-1}>
                        <AlertCircle />
                        <AlertTitle>Review your changes</AlertTitle>
                        <AlertDescription>
                            <ul>
                                {Object.entries(form.errors).map(([key, error]) => (
                                    <li key={key}>{error}</li>
                                ))}
                            </ul>
                        </AlertDescription>
                    </Alert>
                )}
                <Frame>
                    <FramePanel>
                        <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <div className="flex items-start gap-3">
                                <IconTile variant="soft">
                                    <UsersRound />
                                </IconTile>
                                <div className="flex flex-col gap-1">
                                    <FrameTitle>Student profile studio</FrameTitle>
                                    <FrameDescription>Shape records for your institution, without changing existing data.</FrameDescription>
                                    <div className="flex flex-wrap gap-2">
                                        <Badge variant="info-light">{form.data.enabled_types.length} active tracks</Badge>
                                        <Badge variant="outline">
                                            {Object.values(form.data.fields).filter((field) => field.visible).length} standard fields
                                        </Badge>
                                        <Badge variant="outline">{customFields.filter((field) => field.active).length} custom fields</Badge>
                                    </div>
                                </div>
                            </div>
                            <Link
                                href={identifiersIndex.url()}
                                className="text-primary inline-flex items-center gap-1 text-sm font-medium hover:underline"
                            >
                                Student number settings <ArrowRight className="size-4" />
                            </Link>
                        </div>
                    </FramePanel>
                </Frame>
                <Tabs value={section} onValueChange={(value) => setSection(value as SettingsSection)}>
                    <div className="max-w-full overflow-x-auto" aria-label="Student settings sections">
                        <TabsList>
                            <TabsTrigger value="categories">{sectionLabels.categories}</TabsTrigger>
                            <TabsTrigger value="information">{sectionLabels.information}</TabsTrigger>
                            <TabsTrigger value="identifiers">{sectionLabels.identifiers}</TabsTrigger>
                            <TabsTrigger value="defaults">{sectionLabels.defaults}</TabsTrigger>
                        </TabsList>
                    </div>
                    <p className="text-muted-foreground text-xs sm:hidden">Swipe left or right to see all settings sections.</p>
                    <TabsContent value="defaults">
                        <SettingsPanel
                            title="Admission defaults"
                            description="Choose what appears when staff create a student. Blank values let staff decide each time."
                        >
                            <div className="flex flex-col gap-2">
                                <Label htmlFor="default-type">Default track</Label>
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
                                        onClick={() =>
                                            setConfirmation({
                                                title: "Load starting preferences?",
                                                description:
                                                    "This replaces this draft's settings with the selected starting profile. Custom fields and existing student records are kept. Nothing changes until you save.",
                                                action: "Load preferences",
                                                apply: () => form.setData({ ...structuredClone(preset), custom_fields: customFields }),
                                            })
                                        }
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
                        </SettingsPanel>
                    </TabsContent>
                    <TabsContent value="categories">
                        <SettingsPanel
                            title="Educational tracks"
                            description="Choose supported pathways and shape their progression. Existing student records are never deleted."
                        >
                            <Accordion type="multiple" value={openTracks} onValueChange={setOpenTracks}>
                                {categories.map((type) => {
                                    const TrackIcon = categoryIcons[type];
                                    return (
                                        <AccordionItem key={type} value={type}>
                                            <AccordionTrigger>
                                                <span className="flex min-w-0 items-center gap-3">
                                                    <IconTile variant="soft" size="sm">
                                                        <TrackIcon />
                                                    </IconTile>
                                                    <span className="flex flex-col gap-1">
                                                        <span>{form.data.type_labels[type]}</span>
                                                        <span className="text-muted-foreground text-xs">
                                                            {form.data.year_levels[type].length} progression levels
                                                        </span>
                                                    </span>
                                                    <Badge variant={form.data.enabled_types.includes(type) ? "success-light" : "outline"}>
                                                        {form.data.enabled_types.includes(type) ? "Enabled" : "Disabled"}
                                                    </Badge>
                                                </span>
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
                                                    <div
                                                        className="flex flex-wrap gap-2"
                                                        aria-label={`${form.data.type_labels[type]} progression preview`}
                                                    >
                                                        {form.data.year_levels[type].map((level) => (
                                                            <Badge key={level.value} variant="outline">
                                                                {level.label}
                                                            </Badge>
                                                        ))}
                                                    </div>
                                                    <div className="flex flex-wrap items-center gap-2">
                                                        <span className="text-muted-foreground text-sm">Start with:</span>
                                                        {(type === "shs"
                                                            ? [{ count: 2, label: "Grades 11–12" }]
                                                            : [
                                                                  { count: 2, label: "2-year program" },
                                                                  { count: 4, label: "4-year program" },
                                                                  { count: 6, label: "6-year program" },
                                                              ]
                                                        ).map(({ count, label }) => (
                                                            <Button
                                                                key={count}
                                                                type="button"
                                                                size="sm"
                                                                variant="outline"
                                                                disabled={disabled}
                                                                onClick={() =>
                                                                    setConfirmation({
                                                                        title: `Use ${label}?`,
                                                                        description: `This replaces the current ${form.data.type_labels[type]} progression in this draft. Existing student records are not changed; review the levels before saving.`,
                                                                        action: "Use progression",
                                                                        apply: () =>
                                                                            set("year_levels", {
                                                                                ...form.data.year_levels,
                                                                                [type]: Array.from({ length: count }, (_, index) => ({
                                                                                    value: type === "shs" ? index + 11 : index + 1,
                                                                                    label:
                                                                                        type === "shs" ? `Grade ${index + 11}` : `Year ${index + 1}`,
                                                                                })),
                                                                            }),
                                                                    })
                                                                }
                                                            >
                                                                {label}
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
                                    );
                                })}
                            </Accordion>
                        </SettingsPanel>
                    </TabsContent>
                    <TabsContent value="identifiers">
                        <SettingsPanel
                            title="National & regional registries"
                            description="An external registry number is optional and distinct from your institution’s student number."
                        >
                            <div className="grid gap-3 sm:grid-cols-2">
                                <div className="flex gap-3">
                                    <IconTile variant="outline" size="sm">
                                        <Hash />
                                    </IconTile>
                                    <div>
                                        <p className="font-medium">Student number</p>
                                        <p className="text-muted-foreground text-sm">Assigned by your institution.</p>
                                        <Link href={identifiersIndex.url()} className="text-primary text-sm hover:underline">
                                            Configure numbering
                                        </Link>
                                    </div>
                                </div>
                                <div className="flex gap-3">
                                    <IconTile variant="soft" size="sm">
                                        <Globe2 />
                                    </IconTile>
                                    <div>
                                        <p className="font-medium">Registry number</p>
                                        <p className="text-muted-foreground text-sm">Assigned by a government or education authority.</p>
                                    </div>
                                </div>
                            </div>
                            <Label className="flex items-center gap-3">
                                <Switch checked={external.enabled} disabled={disabled} onCheckedChange={(value) => externalSet("enabled", value)} />
                                Collect a government or regional identifier
                            </Label>
                            <div>
                                <Button
                                    type="button"
                                    variant="outline"
                                    disabled={disabled}
                                    onClick={() => {
                                        const apply = () =>
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
                                        if (external.enabled)
                                            setConfirmation({
                                                title: "Use Philippine LRN settings?",
                                                description:
                                                    "This replaces the regional identifier settings in this draft and switches off legacy LRN-as-student-number mode. Existing student values and numbers will not change.",
                                                action: "Use LRN settings",
                                                apply,
                                            });
                                        else apply();
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
                        </SettingsPanel>
                    </TabsContent>
                    <TabsContent value="information">
                        <SettingsPanel
                            title="Student profile fields"
                            description="Choose which standard information staff collects. Name, birthdate, and academic placement remain mandatory."
                        >
                            <div className="flex flex-col gap-6">
                                {fieldGroups.map((group) => {
                                    const GroupIcon = group.icon;
                                    return (
                                        <div key={group.title} className="border-border/60 bg-muted/20 flex flex-col gap-3 rounded-xl border p-4">
                                            <div className="flex items-center gap-2.5">
                                                <IconTile variant="soft" size="sm">
                                                    <GroupIcon className="size-4" />
                                                </IconTile>
                                                <div>
                                                    <h3 className="text-foreground text-sm font-semibold">{group.title}</h3>
                                                    <p className="text-muted-foreground text-xs">{group.description}</p>
                                                </div>
                                            </div>
                                            <div className="divide-border/40 border-border/40 bg-card flex flex-col divide-y rounded-lg border">
                                                {group.keys.map((key) => {
                                                    const field = form.data.fields[key];
                                                    if (!field) return null;
                                                    return (
                                                        <div
                                                            key={key}
                                                            className="flex flex-col gap-2 p-3 sm:flex-row sm:items-center sm:justify-between"
                                                        >
                                                            <div className="flex-1 sm:max-w-xs">
                                                                <Input
                                                                    aria-label={`${key} label`}
                                                                    value={field.label}
                                                                    disabled={disabled}
                                                                    onChange={(event) =>
                                                                        set("fields", {
                                                                            ...form.data.fields,
                                                                            [key]: { ...field, label: event.target.value },
                                                                        })
                                                                    }
                                                                    className="h-8 text-sm"
                                                                />
                                                            </div>
                                                            <div className="flex items-center gap-4">
                                                                <Label className="flex items-center gap-2 text-xs">
                                                                    <Switch
                                                                        checked={field.visible}
                                                                        disabled={disabled}
                                                                        onCheckedChange={(checked) =>
                                                                            set("fields", {
                                                                                ...form.data.fields,
                                                                                [key]: {
                                                                                    ...field,
                                                                                    visible: checked === true,
                                                                                    required: checked === true && field.required,
                                                                                },
                                                                            })
                                                                        }
                                                                    />
                                                                    Visible
                                                                </Label>
                                                                <Label className="flex items-center gap-2 text-xs">
                                                                    <Checkbox
                                                                        checked={field.required}
                                                                        disabled={disabled || !field.visible}
                                                                        onCheckedChange={(checked) =>
                                                                            set("fields", {
                                                                                ...form.data.fields,
                                                                                [key]: { ...field, required: checked === true },
                                                                            })
                                                                        }
                                                                    />
                                                                    Required
                                                                </Label>
                                                            </div>
                                                        </div>
                                                    );
                                                })}
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        </SettingsPanel>
                        <SettingsPanel
                            title="Institution custom fields"
                            description="Add attributes unique to your institution. Active fields appear on administrator student forms and profiles. Existing responses are preserved if a field is archived."
                        >
                            {customFields.length === 0 ? (
                                <div className="border-border/70 text-muted-foreground rounded-xl border border-dashed p-6 text-center text-sm">
                                    No institution-specific fields yet. Click below to add one if the standard fields don't meet your needs.
                                </div>
                            ) : (
                                <div className="flex flex-col gap-3">
                                    {customFields.map((field, index) => {
                                        const saved = savedFields.some((item) => item.key === field.key);
                                        return (
                                            <div
                                                key={field.key}
                                                className="border-border/60 bg-card flex flex-col gap-3 rounded-xl border p-4 transition-all duration-200"
                                            >
                                                <div className="border-border/40 flex flex-wrap items-center justify-between gap-2 border-b pb-2">
                                                    <div className="flex items-center gap-2">
                                                        <span className="text-sm font-semibold">{field.label || "Untitled attribute"}</span>
                                                        <code className="text-muted-foreground font-mono text-[11px]">{field.key}</code>
                                                    </div>
                                                    <div className="flex items-center gap-2">
                                                        <Badge variant={field.active ? "success-light" : "outline"}>
                                                            {field.active ? "Active" : "Archived"}
                                                        </Badge>
                                                        {field.required && <Badge variant="warning-light">Required</Badge>}
                                                    </div>
                                                </div>
                                                <div className="grid gap-3 sm:grid-cols-2">
                                                    <div className="flex flex-col gap-1.5">
                                                        <Label htmlFor={`custom-label-${field.key}`} className="text-xs">
                                                            Field label
                                                        </Label>
                                                        <Input
                                                            id={`custom-label-${field.key}`}
                                                            value={field.label}
                                                            disabled={disabled}
                                                            onChange={(event) => updateCustom(index, { label: event.target.value })}
                                                            className="h-8 text-sm"
                                                        />
                                                    </div>
                                                    <div className="flex flex-col gap-1.5">
                                                        <Label htmlFor={`custom-type-${field.key}`} className="text-xs">
                                                            Field type
                                                        </Label>
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
                                                            <SelectTrigger id={`custom-type-${field.key}`} className="h-8 text-sm">
                                                                <SelectValue />
                                                            </SelectTrigger>
                                                            <SelectContent>
                                                                <SelectItem value="text">Short text</SelectItem>
                                                                <SelectItem value="number">Numeric value</SelectItem>
                                                                <SelectItem value="date">Date</SelectItem>
                                                                <SelectItem value="choice">Single choice (Dropdown)</SelectItem>
                                                            </SelectContent>
                                                        </Select>
                                                    </div>
                                                </div>
                                                {saved && (
                                                    <p className="text-muted-foreground text-[11px]">
                                                        Field type is locked after first save to prevent data corruption.
                                                    </p>
                                                )}
                                                {field.type === "choice" && (
                                                    <div className="border-border/40 bg-muted/20 flex flex-col gap-2 rounded-lg border p-3">
                                                        <Label className="text-xs font-medium">Dropdown Options</Label>
                                                        <div className="flex flex-col gap-2">
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
                                                                        className="h-8 text-sm"
                                                                    />
                                                                    <Button
                                                                        type="button"
                                                                        variant="ghost"
                                                                        size="icon"
                                                                        aria-label="Remove choice"
                                                                        disabled={disabled}
                                                                        onClick={() =>
                                                                            updateCustom(index, {
                                                                                options: field.options.filter((_, i) => i !== position),
                                                                            })
                                                                        }
                                                                        className="size-8"
                                                                    >
                                                                        <Trash2 className="size-4" />
                                                                    </Button>
                                                                </div>
                                                            ))}
                                                            <Button
                                                                type="button"
                                                                variant="outline"
                                                                size="sm"
                                                                disabled={disabled}
                                                                onClick={() =>
                                                                    updateCustom(index, {
                                                                        options: [...field.options, `Option ${field.options.length + 1}`],
                                                                    })
                                                                }
                                                                className="w-fit"
                                                            >
                                                                <Plus className="mr-1 size-3.5" /> Add option
                                                            </Button>
                                                        </div>
                                                    </div>
                                                )}
                                                <div className="border-border/40 flex flex-wrap items-center justify-between gap-4 border-t pt-2 text-xs">
                                                    <div className="flex items-center gap-4">
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
                                                            Required for new students
                                                        </Label>
                                                    </div>
                                                    {!saved && (
                                                        <Button
                                                            type="button"
                                                            variant="ghost"
                                                            size="sm"
                                                            disabled={disabled}
                                                            onClick={() =>
                                                                set(
                                                                    "custom_fields",
                                                                    customFields.filter((_, i) => i !== index),
                                                                )
                                                            }
                                                            className="text-destructive hover:text-destructive text-xs"
                                                        >
                                                            Delete draft field
                                                        </Button>
                                                    )}
                                                </div>
                                            </div>
                                        );
                                    })}
                                </div>
                            )}
                            <Button
                                type="button"
                                variant="outline"
                                disabled={disabled || customFields.length >= 30}
                                onClick={addCustom}
                                className="w-fit"
                            >
                                <Plus className="mr-1.5 size-4" /> Add custom student field
                            </Button>
                            <p className="text-muted-foreground text-xs">
                                Saving a custom field as required closes public online registration and restricts student creation to the
                                administrator form until it is optional again.
                            </p>
                        </SettingsPanel>
                    </TabsContent>
                </Tabs>
                <div className="border-border/60 bg-card/60 flex flex-wrap items-center justify-between gap-3 rounded-2xl border p-4 shadow-xs">
                    <div className="flex items-center gap-2 text-xs sm:text-sm">
                        <span className={cn("size-2 rounded-full", form.isDirty ? "bg-warning animate-pulse" : "bg-success")} aria-hidden="true" />
                        <span className="text-muted-foreground">{form.isDirty ? "Unsaved configuration changes" : "All changes saved"}</span>
                        <kbd className="border-border/60 bg-muted text-muted-foreground hidden rounded border px-1.5 py-0.5 font-mono text-[10px] sm:inline-block">
                            ⌘S
                        </kbd>
                    </div>
                    <Button type="submit" disabled={disabled || !form.isDirty} size="sm" className="gap-2">
                        {form.processing ? <Loader2 className="size-4 animate-spin" /> : <Save className="size-4" />}
                        Save student configuration
                    </Button>
                </div>
                <AlertDialog open={confirmation !== null} onOpenChange={(open) => !open && setConfirmation(null)}>
                    <AlertDialogContent>
                        <AlertDialogHeader>
                            <AlertDialogTitle>{confirmation?.title}</AlertDialogTitle>
                            <AlertDialogDescription>{confirmation?.description}</AlertDialogDescription>
                        </AlertDialogHeader>
                        <AlertDialogFooter>
                            <AlertDialogCancel>Cancel</AlertDialogCancel>
                            <AlertDialogAction
                                onClick={() => {
                                    confirmation?.apply();
                                    setConfirmation(null);
                                }}
                            >
                                {confirmation?.action}
                            </AlertDialogAction>
                        </AlertDialogFooter>
                    </AlertDialogContent>
                </AlertDialog>
            </form>
        </SystemManagementLayout>
    );
}
