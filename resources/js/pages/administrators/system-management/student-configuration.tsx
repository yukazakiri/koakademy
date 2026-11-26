import { Alert, AlertDescription, AlertTitle } from "@/components/reui/alert";
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
import { Badge } from "@/components/reui/badge";
import { Frame, FrameDescription, FramePanel, FrameTitle } from "@/components/reui/frame";
import { IconTile } from "@/components/reui/icon-tile";
import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from "@/components/ui/accordion";
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
import type {
    CustomStudentField,
    DocumentSlotKey,
    DocumentSlotPolicy,
    ExternalIdentifierPreset,
    SignupPolicyConfiguration,
    StudentCategory,
    StudentConfiguration,
    StudentIdentifierPolicy,
} from "@/types/student-configuration";
import { Link, useForm } from "@inertiajs/react";
import {
    AlertCircle,
    ArrowRight,
    BadgeCheck,
    BookOpen,
    Contact,
    FileCheck2,
    Globe2,
    GraduationCap,
    Hash,
    Layers3,
    Loader2,
    Plus,
    Save,
    Settings2,
    Sparkles,
    Trash2,
    UserCheck,
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
    signup: "Portal signup",
    defaults: "Admission defaults",
};
const DEFAULT_SIGNUP_POLICY: SignupPolicyConfiguration = {
    allow_student_self_signup: true,
    allow_faculty_self_signup: true,
    allow_identifier_recovery: true,
    require_faculty_id: false,
    student_identifier_placeholder: null,
    student_identifier_help_text: null,
    faculty_identifier_placeholder: null,
    faculty_identifier_help_text: null,
};
const DOCUMENT_SLOT_KEYS: DocumentSlotKey[] = ["birth_certificate", "form_138", "good_moral_cert", "picture_1x1"];
const UNIVERSAL_DOCUMENTS: Record<DocumentSlotKey, DocumentSlotPolicy> = {
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
};
const PHILIPPINE_DOCUMENTS: Record<DocumentSlotKey, DocumentSlotPolicy> = {
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
};
function resolveDocumentPolicies(documents?: Partial<Record<DocumentSlotKey, Partial<DocumentSlotPolicy>>>): Record<DocumentSlotKey, DocumentSlotPolicy> {
    return Object.fromEntries(
        DOCUMENT_SLOT_KEYS.map((slotKey) => [
            slotKey,
            {
                ...UNIVERSAL_DOCUMENTS[slotKey],
                ...(documents?.[slotKey] ?? {}),
            },
        ]),
    ) as Record<DocumentSlotKey, DocumentSlotPolicy>;
}
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
    external_identifier_presets,
    identifier_policies,
}: SystemManagementPageProps & {
    student_presets: Record<string, StudentConfiguration>;
    external_identifier_presets?: Record<string, ExternalIdentifierPreset>;
    identifier_policies?: {
        student?: Partial<StudentIdentifierPolicy>;
        staff?: Partial<StudentIdentifierPolicy>;
    };
}) {
    const initialConfiguration: StudentConfiguration = {
        ...student_configuration,
        documents: resolveDocumentPolicies(student_configuration.documents),
        external_identifier: {
            placeholder: null,
            allow_for_signup: true,
            ...student_configuration.external_identifier,
        },
        signup: {
            ...DEFAULT_SIGNUP_POLICY,
            ...(student_configuration.signup ?? {}),
        },
    };
    const form = useForm<StudentConfiguration>(initialConfiguration);
    const disabled = !access.sections.student_configuration?.can_update || form.processing;
    const [section, setSection] = useState<SettingsSection>("categories");
    const [detailsOpen, setDetailsOpen] = useState(Boolean(student_configuration.external_identifier.enabled));
    const [confirmation, setConfirmation] = useState<Confirmation | null>(null);
    const [openTracks, setOpenTracks] = useState<string[]>([]);
    const errorSummaryRef = useRef<HTMLDivElement>(null);
    const focusSummaryOnMount = useRef(false);
    const set = <K extends keyof StudentConfiguration>(key: K, value: StudentConfiguration[K]) =>
        form.setData((previous) => ({ ...previous, preset: "custom", [key]: value }));
    const external = form.data.external_identifier;
    const signupPolicy = form.data.signup ?? DEFAULT_SIGNUP_POLICY;
    const documents = resolveDocumentPolicies(form.data.documents);
    const customFields = form.data.custom_fields ?? [];
    const savedFields = student_configuration.custom_fields ?? [];
    const updateDocument = (slotKey: DocumentSlotKey, changes: Partial<DocumentSlotPolicy>) =>
        set("documents", {
            ...documents,
            [slotKey]: { ...documents[slotKey], ...changes },
        });
    const applyDocumentLabelPreset = (preset: Record<DocumentSlotKey, DocumentSlotPolicy>) =>
        set(
            "documents",
            Object.fromEntries(
                DOCUMENT_SLOT_KEYS.map((slotKey) => [
                    slotKey,
                    {
                        ...documents[slotKey],
                        label: preset[slotKey].label,
                        description: preset[slotKey].description,
                    },
                ]),
            ) as Record<DocumentSlotKey, DocumentSlotPolicy>,
        );
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
    const signupSet = <K extends keyof SignupPolicyConfiguration>(key: K, value: SignupPolicyConfiguration[K]) =>
        set("signup", { ...signupPolicy, [key]: value });
    const studentPrimaryLabel = identifier_policies?.student?.display_label?.trim() || "Student ID";
    const facultyPrimaryLabel = identifier_policies?.staff?.display_label?.trim() || "Faculty ID";
    const externalAppliesToEnabled =
        external.enabled &&
        (external.allow_for_signup ?? true) &&
        external.student_types.some((type) => form.data.enabled_types.includes(type));
    const externalOnlyForEnabled =
        externalAppliesToEnabled &&
        external.use_as_student_id &&
        form.data.enabled_types.every((type) => external.student_types.includes(type));
    const previewIdentifierLabel = externalOnlyForEnabled
        ? external.label || "External Identifier"
        : externalAppliesToEnabled
          ? `${studentPrimaryLabel} or ${external.label || "External Identifier"}`
          : studentPrimaryLabel;
    const previewIdentifierPlaceholder =
        signupPolicy.student_identifier_placeholder?.trim() ||
        (externalOnlyForEnabled
            ? external.placeholder?.trim() || `Enter your ${external.label || "External Identifier"}`
            : externalAppliesToEnabled && external.placeholder?.trim()
              ? `e.g. 2026-0001 or ${external.placeholder.replace(/^e\.g\.\s*/i, "")}`
              : `e.g. 2026-0001`);
    const previewIdentifierHelp =
        signupPolicy.student_identifier_help_text?.trim() ||
        (externalAppliesToEnabled && !externalOnlyForEnabled
            ? `Enter your ${studentPrimaryLabel} or ${external.label || "External Identifier"} — we'll automatically match your student record.`
            : "Found on your admission notice, registration slip, or campus ID card.");
    const save = () => {
        if (disabled || !form.isDirty) return;
        form.put(update.url(), {
            preserveScroll: true,
            onSuccess: () => toast.success("Student configuration saved."),
            onError: (errors) => {
                const first = Object.keys(errors)[0] ?? "";
                const invalidTracks = Object.keys(errors)
                    .filter((key) => key.startsWith("type_labels.") || key.startsWith("year_levels."))
                    .map((key) => key.split(".")[1])
                    .filter(Boolean);
                if (invalidTracks.length > 0) {
                    setOpenTracks((previous) => Array.from(new Set([...previous, ...invalidTracks])));
                }

                if (first.startsWith("custom_fields") || first.startsWith("fields.") || first.startsWith("documents.") || first === "documents")
                    setSection("information");
                else if (first.startsWith("external_identifier")) {
                    setSection("identifiers");
                    setDetailsOpen(true);
                } else if (first.startsWith("signup.")) {
                    setSection("signup");
                } else if (first === "default_type") {
                    if (errors.default_type?.toLowerCase().includes("enabled")) {
                        setSection("categories");
                    } else {
                        setSection("defaults");
                    }
                } else if (first.startsWith("enabled_types") || first.startsWith("type_labels") || first.startsWith("year_levels"))
                    setSection("categories");
                else if (first.startsWith("defaults") || first.startsWith("philippine_reporting")) setSection("defaults");
                toast.error("Review the configuration errors.");
                if (errorSummaryRef.current) {
                    errorSummaryRef.current.focus();
                    focusSummaryOnMount.current = false;
                } else {
                    focusSummaryOnMount.current = true;
                }
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
    useEffect(() => {
        if (!focusSummaryOnMount.current || !errorSummaryRef.current) return;
        focusSummaryOnMount.current = false;
        errorSummaryRef.current.focus();
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
                            <TabsTrigger value="signup">{sectionLabels.signup}</TabsTrigger>
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
                                                apply: () =>
                                                    form.setData({
                                                        ...structuredClone(preset),
                                                        documents: resolveDocumentPolicies(structuredClone(preset.documents)),
                                                        custom_fields: customFields,
                                                    }),
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
                            description="An external registry number is assigned outside your institution. Set whether it is required and whether it is the student number below."
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
                                <Switch
                                    checked={external.enabled}
                                    disabled={disabled}
                                    onCheckedChange={(value) => {
                                        externalSet("enabled", value);
                                        if (value) setDetailsOpen(true);
                                    }}
                                />
                                Collect a government or regional identifier
                            </Label>
                            {external_identifier_presets && Object.keys(external_identifier_presets).length > 0 && (
                                <div className="space-y-2.5">
                                    <div>
                                        <p className="text-foreground text-sm font-medium">Regional registry presets</p>
                                        <p className="text-muted-foreground text-xs">
                                            Pick a starting standard for your country or institution, then customize any label or validation rule below.
                                        </p>
                                    </div>
                                    <div className="grid gap-2.5 sm:grid-cols-2 lg:grid-cols-3">
                                        {Object.entries(external_identifier_presets).map(([presetKey, preset]) => {
                                            const isSelected =
                                                preset.config.enabled === external.enabled &&
                                                (!preset.config.enabled ||
                                                    (preset.config.label === external.label &&
                                                        preset.config.format === external.format &&
                                                        preset.config.min_length === external.min_length &&
                                                        preset.config.max_length === external.max_length));
                                            return (
                                                <button
                                                    key={presetKey}
                                                    type="button"
                                                    disabled={disabled}
                                                    onClick={() => {
                                                        const apply = () => {
                                                            set("external_identifier", {
                                                                ...external,
                                                                ...preset.config,
                                                            });
                                                            if (preset.config.enabled) {
                                                                setDetailsOpen(true);
                                                            }
                                                        };
                                                        if (external.enabled && presetKey !== "none") {
                                                            setConfirmation({
                                                                title: `Use ${preset.name}?`,
                                                                description:
                                                                    "This replaces the regional identifier fields in this draft. Existing student values and numbers will not change until you save.",
                                                                action: "Apply preset",
                                                                apply,
                                                            });
                                                        } else {
                                                            apply();
                                                        }
                                                    }}
                                                    className={cn(
                                                        "border-border/70 bg-card/60 hover:border-primary/50 hover:bg-primary/5 flex flex-col items-start gap-1.5 rounded-xl border p-3 text-left transition-all disabled:opacity-50",
                                                        isSelected && "border-primary bg-primary/5 ring-primary/20 ring-1",
                                                    )}
                                                >
                                                    <div className="flex w-full items-center justify-between gap-2">
                                                        <Badge variant={isSelected ? "info-light" : "outline"} className="text-[10px]">
                                                            {preset.region}
                                                        </Badge>
                                                        {preset.config.enabled && (
                                                            <span className="text-muted-foreground font-mono text-[10px]">
                                                                {preset.config.label}
                                                            </span>
                                                        )}
                                                    </div>
                                                    <p className="text-foreground text-xs font-semibold">{preset.name}</p>
                                                    <p className="text-muted-foreground text-[11px] leading-snug">{preset.description}</p>
                                                </button>
                                            );
                                        })}
                                    </div>
                                </div>
                            )}
                            {external.enabled && (
                                <p className="text-muted-foreground text-sm">
                                    Collecting <strong className="text-foreground">{external.label}</strong> for{" "}
                                    {external.student_types.map((type) => form.data.type_labels[type]).join(", ") || "no categories selected"}.{" "}
                                    {external.required ? "Required." : "Optional."}{" "}
                                    {external.use_as_student_id ? "Used as the student number." : "Kept separate from the student number."}{" "}
                                    {(external.allow_for_signup ?? true) ? "Accepted on /signup." : "Not accepted on /signup."}
                                </p>
                            )}
                            <Button type="button" variant="ghost" size="sm" className="w-fit" onClick={() => setDetailsOpen(!detailsOpen)} aria-expanded={detailsOpen}>
                                {detailsOpen ? "Hide identifier details" : "Customize identifier details"}
                            </Button>
                            {detailsOpen && (
                                <>
                                    <div className="grid gap-4 md:grid-cols-3">
                                        <div className="flex flex-col gap-2">
                                            <Label htmlFor="external-label">Label</Label>
                                            <Input
                                                id="external-label"
                                                value={external.label}
                                                disabled={disabled}
                                                placeholder="e.g. LRN, ULN, USI, National ID"
                                                onChange={(event) => externalSet("label", event.target.value)}
                                            />
                                        </div>
                                        <div className="flex flex-col gap-2">
                                            <Label htmlFor="external-placeholder">Example placeholder</Label>
                                            <Input
                                                id="external-placeholder"
                                                value={external.placeholder ?? ""}
                                                disabled={disabled}
                                                placeholder="e.g. 1234567890"
                                                onChange={(event) => externalSet("placeholder", event.target.value || null)}
                                            />
                                        </div>
                                        <div className="flex flex-col gap-2">
                                            <Label htmlFor="external-help">Help text</Label>
                                            <Input
                                                id="external-help"
                                                value={external.help_text ?? ""}
                                                disabled={disabled}
                                                placeholder="Short description shown on forms"
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
                                                onValueChange={(value) => externalSet("format", value as "text" | "digits" | "alphanumeric")}
                                            >
                                                <SelectTrigger id="external-format">
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="text">Text (any characters)</SelectItem>
                                                    <SelectItem value="digits">Digits only (0–9)</SelectItem>
                                                    <SelectItem value="alphanumeric">Alphanumeric (A–Z, 0–9)</SelectItem>
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
                                    <div className="flex flex-col gap-3">
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
                                                checked={external.allow_for_signup ?? true}
                                                disabled={disabled}
                                                onCheckedChange={(value) => externalSet("allow_for_signup", value)}
                                            />
                                            Allow students to verify using this identifier on /signup (auto-detected alongside {studentPrimaryLabel})
                                        </Label>
                                        <Label className="flex items-center gap-3">
                                            <Switch
                                                checked={external.use_as_student_id}
                                                disabled={disabled}
                                                onCheckedChange={(value) => externalSet("use_as_student_id", value)}
                                            />
                                            Use this identifier as the student number (legacy compatibility)
                                        </Label>
                                    </div>
                                </>
                            )}
                        </SettingsPanel>
                    </TabsContent>
                    <TabsContent value="signup" className="flex flex-col gap-6">
                        <SettingsPanel
                            title="Self-service portal activation"
                            description="Control how students and faculty claim their portal accounts on /signup. Students enter one universal identifier and KoAkademy matches their record automatically."
                        >
                            <div className="divide-border/40 border-border/50 bg-card flex flex-col divide-y rounded-xl border">
                                <div className="flex items-center justify-between gap-4 p-4">
                                    <div className="flex items-start gap-3">
                                        <IconTile variant="soft" size="sm">
                                            <GraduationCap className="size-4" />
                                        </IconTile>
                                        <div>
                                            <p className="text-foreground text-sm font-medium">Allow student self-signup</p>
                                            <p className="text-muted-foreground text-xs">
                                                Students on the active roster can activate their own portal login using their email and{" "}
                                                {previewIdentifierLabel}.
                                            </p>
                                        </div>
                                    </div>
                                    <Switch
                                        aria-label="Allow student self-signup"
                                        checked={signupPolicy.allow_student_self_signup}
                                        disabled={disabled}
                                        onCheckedChange={(value) => signupSet("allow_student_self_signup", value)}
                                    />
                                </div>
                                <div className="flex items-center justify-between gap-4 p-4">
                                    <div className="flex items-start gap-3">
                                        <IconTile variant="soft" size="sm">
                                            <UserCheck className="size-4" />
                                        </IconTile>
                                        <div>
                                            <p className="text-foreground text-sm font-medium">Allow faculty self-signup</p>
                                            <p className="text-muted-foreground text-xs">
                                                Active faculty and instructors can claim their portal account using their institutional email.
                                            </p>
                                        </div>
                                    </div>
                                    <Switch
                                        aria-label="Allow faculty self-signup"
                                        checked={signupPolicy.allow_faculty_self_signup}
                                        disabled={disabled}
                                        onCheckedChange={(value) => signupSet("allow_faculty_self_signup", value)}
                                    />
                                </div>
                                <div className="flex items-center justify-between gap-4 p-4">
                                    <div className="flex items-start gap-3">
                                        <IconTile variant="outline" size="sm">
                                            <Sparkles className="size-4" />
                                        </IconTile>
                                        <div>
                                            <p className="text-foreground text-sm font-medium">Allow self-service student ID recovery</p>
                                            <p className="text-muted-foreground text-xs">
                                                Show an &ldquo;Email my {previewIdentifierLabel}&rdquo; button on /signup so students who forgot their
                                                ID can receive it securely by email.
                                            </p>
                                        </div>
                                    </div>
                                    <Switch
                                        aria-label="Allow self-service student ID recovery"
                                        checked={signupPolicy.allow_identifier_recovery}
                                        disabled={disabled}
                                        onCheckedChange={(value) => signupSet("allow_identifier_recovery", value)}
                                    />
                                </div>
                                <div className="flex items-center justify-between gap-4 p-4">
                                    <div className="flex items-start gap-3">
                                        <IconTile variant="outline" size="sm">
                                            <BadgeCheck className="size-4" />
                                        </IconTile>
                                        <div>
                                            <p className="text-foreground text-sm font-medium">Require {facultyPrimaryLabel} on faculty signup</p>
                                            <p className="text-muted-foreground text-xs">
                                                Require instructors to enter their {facultyPrimaryLabel} even when their email matches a single school
                                                record.
                                            </p>
                                        </div>
                                    </div>
                                    <Switch
                                        aria-label="Require faculty ID on signup"
                                        checked={signupPolicy.require_faculty_id}
                                        disabled={disabled}
                                        onCheckedChange={(value) => signupSet("require_faculty_id", value)}
                                    />
                                </div>
                            </div>
                        </SettingsPanel>

                        <SettingsPanel
                            title="Universal signup labels & guidance"
                            description="Customize the placeholder and helper text shown on /signup. Primary ID labels come from Student & Staff Identifiers."
                        >
                            <div className="border-border/60 bg-muted/20 flex flex-wrap items-center justify-between gap-3 rounded-xl border px-4 py-3 text-xs">
                                <div className="flex flex-wrap items-center gap-2">
                                    <span className="text-muted-foreground">Active primary labels:</span>
                                    <Badge variant="outline">{studentPrimaryLabel}</Badge>
                                    <Badge variant="outline">{facultyPrimaryLabel}</Badge>
                                    {externalAppliesToEnabled && <Badge variant="info-light">+ {external.label}</Badge>}
                                </div>
                                <Link href={identifiersIndex.url()} className="text-primary inline-flex items-center gap-1 font-medium hover:underline">
                                    Rename primary labels <ArrowRight className="size-3.5" />
                                </Link>
                            </div>

                            <div className="grid gap-4 md:grid-cols-2">
                                <div className="flex flex-col gap-2">
                                    <Label htmlFor="signup-student-placeholder">Student identifier placeholder</Label>
                                    <Input
                                        id="signup-student-placeholder"
                                        value={signupPolicy.student_identifier_placeholder ?? ""}
                                        disabled={disabled}
                                        placeholder={previewIdentifierPlaceholder}
                                        onChange={(event) => signupSet("student_identifier_placeholder", event.target.value || null)}
                                    />
                                </div>
                                <div className="flex flex-col gap-2">
                                    <Label htmlFor="signup-faculty-placeholder">Faculty identifier placeholder</Label>
                                    <Input
                                        id="signup-faculty-placeholder"
                                        value={signupPolicy.faculty_identifier_placeholder ?? ""}
                                        disabled={disabled}
                                        placeholder="e.g. FAC-2026-001"
                                        onChange={(event) => signupSet("faculty_identifier_placeholder", event.target.value || null)}
                                    />
                                </div>
                                <div className="flex flex-col gap-2">
                                    <Label htmlFor="signup-student-help">Student identifier help text</Label>
                                    <Input
                                        id="signup-student-help"
                                        value={signupPolicy.student_identifier_help_text ?? ""}
                                        disabled={disabled}
                                        placeholder="Found on your admission notice, registration slip, or campus ID card."
                                        onChange={(event) => signupSet("student_identifier_help_text", event.target.value || null)}
                                    />
                                </div>
                                <div className="flex flex-col gap-2">
                                    <Label htmlFor="signup-faculty-help">Faculty identifier help text</Label>
                                    <Input
                                        id="signup-faculty-help"
                                        value={signupPolicy.faculty_identifier_help_text ?? ""}
                                        disabled={disabled}
                                        placeholder={`Enter the ${facultyPrimaryLabel} assigned by HR or your institution.`}
                                        onChange={(event) => signupSet("faculty_identifier_help_text", event.target.value || null)}
                                    />
                                </div>
                            </div>

                            {/* Live Signup Step 3 Preview */}
                            <div className="border-primary/25 bg-primary/5 space-y-3 rounded-2xl border p-4">
                                <div className="flex items-center justify-between gap-2">
                                    <span className="text-primary inline-flex items-center gap-1.5 text-xs font-semibold">
                                        <Sparkles className="size-3.5" />
                                        Live preview — /signup Step 3 (Student)
                                    </span>
                                    <Badge variant={signupPolicy.allow_student_self_signup ? "success-light" : "outline"}>
                                        {signupPolicy.allow_student_self_signup ? "Active on /signup" : "Self-signup disabled"}
                                    </Badge>
                                </div>
                                <div className="bg-background border-border/70 space-y-2.5 rounded-xl border p-4 shadow-2xs">
                                    <p className="text-foreground text-xs font-semibold">{previewIdentifierLabel}</p>
                                    <div className="border-border/80 bg-muted/20 text-muted-foreground flex h-10 items-center gap-2 rounded-lg border px-3 font-mono text-xs">
                                        <BadgeCheck className="size-3.5 shrink-0 opacity-70" />
                                        <span className="truncate">{previewIdentifierPlaceholder}</span>
                                    </div>
                                    <p className="text-muted-foreground text-[11px]">{previewIdentifierHelp}</p>
                                    {signupPolicy.allow_identifier_recovery && (
                                        <p className="text-primary text-[11px] font-medium">
                                            Don&apos;t remember your ID? Email my {previewIdentifierLabel}
                                        </p>
                                    )}
                                </div>
                            </div>
                        </SettingsPanel>
                    </TabsContent>
                    <TabsContent value="information" className="flex flex-col gap-6">
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
                            title="Student document vault checklist"
                            description="Customize the admission and registrar document slots shown in the student document vault and dashboard checklist. Hiding a slot removes it from student upload checklists without deleting stored files."
                        >
                            <div className="flex flex-wrap items-center justify-between gap-2">
                                <div className="flex items-center gap-2">
                                    <IconTile variant="soft" size="sm">
                                        <FileCheck2 className="size-4" />
                                    </IconTile>
                                    <span className="text-muted-foreground text-xs">
                                        {DOCUMENT_SLOT_KEYS.filter((slotKey) => documents[slotKey].visible).length} of {DOCUMENT_SLOT_KEYS.length}{" "}
                                        slots visible to students
                                    </span>
                                </div>
                                <div className="flex flex-wrap gap-2">
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        disabled={disabled}
                                        onClick={() => applyDocumentLabelPreset(UNIVERSAL_DOCUMENTS)}
                                    >
                                        Universal international labels
                                    </Button>
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        disabled={disabled}
                                        onClick={() => applyDocumentLabelPreset(PHILIPPINE_DOCUMENTS)}
                                    >
                                        Philippine registrar labels
                                    </Button>
                                </div>
                            </div>
                            <div className="divide-border/40 border-border/50 bg-card flex flex-col divide-y rounded-xl border">
                                {DOCUMENT_SLOT_KEYS.map((slotKey) => {
                                    const slot = documents[slotKey];
                                    return (
                                        <div key={slotKey} className="flex flex-col gap-3 p-4">
                                            <div className="flex flex-wrap items-center justify-between gap-2">
                                                <div className="flex items-center gap-2">
                                                    <span className="text-foreground text-sm font-medium">{slot.label || slotKey}</span>
                                                    <code className="text-muted-foreground font-mono text-[11px]">{slotKey}</code>
                                                </div>
                                                <div className="flex items-center gap-4">
                                                    <Label className="flex items-center gap-2 text-xs">
                                                        <Switch
                                                            aria-label={`${slotKey} visible`}
                                                            checked={slot.visible}
                                                            disabled={disabled}
                                                            onCheckedChange={(checked) =>
                                                                updateDocument(slotKey, {
                                                                    visible: checked === true,
                                                                    required: checked === true && slot.required,
                                                                })
                                                            }
                                                        />
                                                        Visible
                                                    </Label>
                                                    <Label className="flex items-center gap-2 text-xs">
                                                        <Checkbox
                                                            aria-label={`${slotKey} required`}
                                                            checked={slot.required}
                                                            disabled={disabled || !slot.visible}
                                                            onCheckedChange={(checked) =>
                                                                updateDocument(slotKey, { required: checked === true })
                                                            }
                                                        />
                                                        Required
                                                    </Label>
                                                </div>
                                            </div>
                                            <div className="grid gap-3 md:grid-cols-2">
                                                <div className="flex flex-col gap-1.5">
                                                    <Label htmlFor={`document-label-${slotKey}`} className="text-xs">
                                                        Display label
                                                    </Label>
                                                    <Input
                                                        id={`document-label-${slotKey}`}
                                                        aria-label={`${slotKey} document label`}
                                                        value={slot.label}
                                                        maxLength={80}
                                                        disabled={disabled}
                                                        onChange={(event) => updateDocument(slotKey, { label: event.target.value })}
                                                        className="h-8 text-sm"
                                                    />
                                                </div>
                                                <div className="flex flex-col gap-1.5">
                                                    <Label htmlFor={`document-description-${slotKey}`} className="text-xs">
                                                        Student guidance description
                                                    </Label>
                                                    <Input
                                                        id={`document-description-${slotKey}`}
                                                        aria-label={`${slotKey} document description`}
                                                        value={slot.description}
                                                        maxLength={250}
                                                        disabled={disabled}
                                                        onChange={(event) => updateDocument(slotKey, { description: event.target.value })}
                                                        className="h-8 text-sm"
                                                    />
                                                </div>
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
