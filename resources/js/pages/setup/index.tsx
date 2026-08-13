import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardTitle } from "@/components/ui/card";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Switch } from "@/components/ui/switch";
import { Textarea } from "@/components/ui/textarea";
import { Head, useForm, usePage } from "@inertiajs/react";
import { AnimatePresence, motion } from "framer-motion";
import {
    AlertCircle,
    ArrowRight,
    BookOpen,
    Building2,
    CalendarDays,
    CheckCircle2,
    ChevronLeft,
    ChevronRight,
    ClipboardList,
    ExternalLink,
    Eye,
    EyeOff,
    GraduationCap,
    Image as ImageIcon,
    Loader2,
    Palette,
    Rocket,
    Search,
    ShieldCheck,
    Sparkles,
    ToggleRight,
    UserCircle,
} from "lucide-react";
import React, { useState } from "react";
import { toast } from "sonner";

const STEPS = [
    { label: "Administrator", description: "Super Admin Account", icon: UserCircle, required: true },
    { label: "Institution", description: "School & Campus Profile", icon: Building2, required: true },
    { label: "Curriculum", description: "CHED · DepEd · TESDA", icon: BookOpen, required: false },
    { label: "Academic Period", description: "School Year & Semester", icon: GraduationCap, required: true },
    { label: "Brand & Appearance", description: "Theme, Currency & Identity", icon: Palette, required: false },
    { label: "Feature Toggles", description: "Modules & Capabilities", icon: ToggleRight, required: false },
    { label: "Review & Finish", description: "Summary & Confirm", icon: ClipboardList, required: true },
];

const slideVariants = {
    hidden: (dir: number) => ({ x: dir > 0 ? 40 : -40, opacity: 0 }),
    visible: { x: 0, opacity: 1, transition: { type: "spring" as const, stiffness: 300, damping: 30 } },
    exit: (dir: number) => ({ x: dir < 0 ? 40 : -40, opacity: 0, transition: { ease: "easeInOut" as const, duration: 0.18 } }),
};

const CURRENCIES = [
    { value: "PHP", label: "PHP — Philippine Peso" },
    { value: "USD", label: "USD — US Dollar" },
    { value: "EUR", label: "EUR — Euro" },
    { value: "GBP", label: "GBP — British Pound" },
    { value: "CAD", label: "CAD — Canadian Dollar" },
    { value: "AUD", label: "AUD — Australian Dollar" },
    { value: "JPY", label: "JPY — Japanese Yen" },
    { value: "KRW", label: "KRW — South Korean Won" },
    { value: "INR", label: "INR — Indian Rupee" },
    { value: "SGD", label: "SGD — Singapore Dollar" },
];

const THEME_COLORS = [
    { value: "#0f172a", label: "Slate" },
    { value: "#1e3a5f", label: "Navy" },
    { value: "#065f46", label: "Emerald" },
    { value: "#7c2d12", label: "Amber" },
    { value: "#581c87", label: "Violet" },
    { value: "#9f1239", label: "Rose" },
    { value: "#164e63", label: "Cyan" },
    { value: "#3f3f46", label: "Zinc" },
];

const SCHOOL_LEVELS = [
    {
        value: "higher_education",
        label: "College / University",
        description: "Undergraduate, graduate, and university-level programs.",
    },
    {
        value: "junior_high",
        label: "Middle School / Junior High School",
        description: "Middle school or junior high school operations.",
    },
    {
        value: "senior_high",
        label: "Senior High School",
        description: "Senior high school programs, usually grades 11 to 12.",
    },
    {
        value: "elementary",
        label: "Elementary / Grade School",
        description: "Elementary or grade school operations.",
    },
    {
        value: "technical_vocational",
        label: "TESDA / Technical-Vocational Institute",
        description: "Technical and vocational education and training (TVET) programs under TESDA.",
    },
];

/* ── Curriculum catalog types (passed from SetupController) ── */

type SchoolLevelOption = {
    value: string;
    label: string;
    description: string;
};

type FrameworkOption = {
    value: string;
    label: string;
    authority: string;
    description: string;
    reference: string;
    school_levels: string[];
    source_url: string | null;
};

type ChedProgram = {
    code: string;
    title: string;
    description: string;
    units: number;
    year_level: number;
    type: string;
    reference: string;
    verified: boolean;
    source_url: string | null;
    department_code: string | null;
    department_name: string | null;
};

type ChedCluster = {
    key: string;
    label: string;
    department_code: string;
    department_name: string;
    programs: ChedProgram[];
};

type ShsStrand = { key: string; name: string; description: string };
type ShsTrack = { key: string; name: string; description: string; strands: ShsStrand[] };

type TesdaQualification = {
    code: string;
    title: string;
    nc_level: number;
    pqf_level: number;
    diploma: boolean;
    superseded: boolean;
    description: string;
    reference: string;
    source_url: string | null;
};

type TesdaSector = {
    key: string;
    label: string;
    department_code: string;
    department_name: string;
    qualifications: TesdaQualification[];
};

type MatatagPhase = { sy: string; grades: string[]; status: string };

type CalendarPreset = {
    key: string;
    label: string;
    starts: string;
    ends: string;
    terms: number;
    note: string;
    source: string;
    estimated: boolean;
};

type CurriculumCatalog = {
    as_of: string;
    school_levels: SchoolLevelOption[];
    frameworks: FrameworkOption[];
    ched: ChedCluster[];
    shs: { legacy: ShsTrack[]; revised: ShsTrack[] };
    tesda: TesdaSector[];
    matatag: { phases: MatatagPhase[]; learning_areas: Record<string, string[]> };
    calendars: CalendarPreset[];
};

/* ── Stable field components defined OUTSIDE the page component ── */

interface FieldProps {
    id: string;
    label: string;
    type?: string;
    placeholder?: string;
    description?: string;
    uppercase?: boolean;
    value: string;
    onChange: (val: string) => void;
    error?: string;
    required?: boolean;
}

function TextField({
    id,
    label,
    type = "text",
    placeholder = "",
    description = "",
    uppercase = false,
    value,
    onChange,
    error,
    required = false,
}: FieldProps) {
    return (
        <div className="space-y-1.5">
            <Label htmlFor={id} className="text-foreground text-sm font-medium">
                {label}
                {required && <span className="text-destructive ml-0.5">*</span>}
            </Label>
            <Input
                id={id}
                name={id}
                type={type}
                value={value}
                onChange={(e) => onChange(e.target.value)}
                placeholder={placeholder}
                autoComplete={type === "password" ? "new-password" : "off"}
                className={[
                    "border-border bg-background text-foreground placeholder:text-muted-foreground/60 h-11",
                    "focus-visible:ring-primary transition-colors duration-150 focus-visible:border-transparent focus-visible:ring-2",
                    uppercase ? "uppercase placeholder:normal-case" : "",
                    error ? "border-destructive focus-visible:ring-destructive" : "hover:border-primary/60",
                ]
                    .filter(Boolean)
                    .join(" ")}
            />
            {description && !error && <p className="text-muted-foreground text-xs">{description}</p>}
            {error && (
                <motion.p initial={{ opacity: 0, y: -4 }} animate={{ opacity: 1, y: 0 }} className="text-destructive text-xs">
                    {error}
                </motion.p>
            )}
        </div>
    );
}

interface PasswordFieldProps {
    id: string;
    label: string;
    placeholder?: string;
    description?: string;
    value: string;
    onChange: (val: string) => void;
    error?: string;
    required?: boolean;
}

function PasswordField({ id, label, placeholder = "", description = "", value, onChange, error, required = false }: PasswordFieldProps) {
    const [show, setShow] = useState(false);

    return (
        <div className="space-y-1.5">
            <Label htmlFor={id} className="text-foreground text-sm font-medium">
                {label}
                {required && <span className="text-destructive ml-0.5">*</span>}
            </Label>
            <div className="relative">
                <Input
                    id={id}
                    name={id}
                    type={show ? "text" : "password"}
                    value={value}
                    onChange={(e) => onChange(e.target.value)}
                    placeholder={placeholder}
                    autoComplete="new-password"
                    className={[
                        "border-border bg-background text-foreground placeholder:text-muted-foreground/60 h-11 pr-11",
                        "focus-visible:ring-primary transition-colors duration-150 focus-visible:border-transparent focus-visible:ring-2",
                        error ? "border-destructive focus-visible:ring-destructive" : "hover:border-primary/60",
                    ]
                        .filter(Boolean)
                        .join(" ")}
                />
                <button
                    type="button"
                    tabIndex={-1}
                    onClick={() => setShow((s) => !s)}
                    className="text-muted-foreground hover:text-foreground absolute top-1/2 right-3 -translate-y-1/2 transition-colors focus:outline-none"
                    aria-label={show ? "Hide password" : "Show password"}
                >
                    {show ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                </button>
            </div>
            {description && !error && <p className="text-muted-foreground text-xs">{description}</p>}
            {error && (
                <motion.p initial={{ opacity: 0, y: -4 }} animate={{ opacity: 1, y: 0 }} className="text-destructive text-xs">
                    {error}
                </motion.p>
            )}
        </div>
    );
}

interface DateFieldProps {
    id: string;
    label: string;
    value: string;
    onChange: (val: string) => void;
    error?: string;
    min?: string;
    required?: boolean;
}

function DateField({ id, label, value, onChange, error, min, required = false }: DateFieldProps) {
    return (
        <div className="space-y-1.5">
            <Label htmlFor={id} className="text-foreground text-sm font-medium">
                {label}
                {required && <span className="text-destructive ml-0.5">*</span>}
            </Label>
            <div className="relative">
                <CalendarDays className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2" />
                <Input
                    id={id}
                    name={id}
                    type="date"
                    value={value}
                    min={min}
                    onChange={(e) => onChange(e.target.value)}
                    className={[
                        "border-border bg-background text-foreground h-11 pl-9",
                        "focus-visible:ring-primary transition-colors duration-150 focus-visible:border-transparent focus-visible:ring-2",
                        error ? "border-destructive focus-visible:ring-destructive" : "hover:border-primary/60",
                    ]
                        .filter(Boolean)
                        .join(" ")}
                />
            </div>
            {error && (
                <motion.p initial={{ opacity: 0, y: -4 }} animate={{ opacity: 1, y: 0 }} className="text-destructive text-xs">
                    {error}
                </motion.p>
            )}
        </div>
    );
}

interface FeatureToggleProps {
    id: string;
    label: string;
    description: string;
    checked: boolean;
    onCheckedChange: (checked: boolean) => void;
    icon: React.ReactNode;
}

function FeatureToggle({ id, label, description, checked, onCheckedChange, icon }: FeatureToggleProps) {
    return (
        <div className="border-border hover:border-primary/30 bg-background flex items-center justify-between gap-4 rounded-lg border p-4 transition-colors">
            <div className="flex items-start gap-3">
                <div className="bg-primary/10 text-primary mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-md">
                    {icon}
                </div>
                <div className="space-y-0.5">
                    <Label htmlFor={id} className="text-foreground cursor-pointer text-sm font-medium">
                        {label}
                    </Label>
                    <p className="text-muted-foreground text-xs leading-relaxed">{description}</p>
                </div>
            </div>
            <Switch id={id} checked={checked} onCheckedChange={onCheckedChange} className="shrink-0 scale-125" />
        </div>
    );
}

/* ── Step Header ── */

function StepHeader({
    stepNumber,
    totalSteps,
    icon,
    title,
    description,
    onBack,
    optional,
}: {
    stepNumber: number;
    totalSteps: number;
    icon: React.ReactNode;
    title: string;
    description: string;
    onBack?: () => void;
    optional?: boolean;
}) {
    return (
        <div className="space-y-1">
            {onBack && (
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    onClick={onBack}
                    className="text-muted-foreground hover:bg-muted hover:text-foreground mb-3 -ml-2 h-8 px-2"
                >
                    <ChevronLeft className="mr-1 h-4 w-4" /> Back
                </Button>
            )}
            <div className="mb-3 flex items-center gap-2">
                <div className="bg-primary text-primary-foreground flex h-7 w-7 items-center justify-center rounded-full">
                    {icon}
                </div>
                <span className="text-muted-foreground text-xs font-semibold tracking-widest uppercase">
                    Step {stepNumber} of {totalSteps}
                </span>
                {optional && (
                    <Badge variant="outline" className="border-primary/30 text-primary ml-1 text-[10px]">
                        Optional
                    </Badge>
                )}
            </div>
            <CardTitle className="text-foreground text-2xl font-bold tracking-tight">{title}</CardTitle>
            <CardDescription className="text-muted-foreground text-sm">{description}</CardDescription>
        </div>
    );
}

/* ── Curriculum step components ── */

function AuthorityBadge({ authority }: { authority: string }) {
    const styles: Record<string, string> = {
        CHED: "border-blue-200 bg-blue-50 text-blue-700 dark:border-blue-900 dark:bg-blue-950 dark:text-blue-300",
        DepEd: "border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-300",
        TESDA: "border-violet-200 bg-violet-50 text-violet-700 dark:border-violet-900 dark:bg-violet-950 dark:text-violet-300",
    };

    return (
        <span className={`inline-flex items-center rounded-full border px-2 py-0.5 text-[10px] font-semibold tracking-wide uppercase ${styles[authority] ?? ""}`}>
            {authority}
        </span>
    );
}

function FrameworkCard({
    framework,
    selected,
    onSelect,
}: {
    framework: FrameworkOption;
    selected: boolean;
    onSelect: () => void;
}) {
    return (
        <div
            role="button"
            tabIndex={0}
            onClick={onSelect}
            onKeyDown={(e) => {
                if (e.key === "Enter" || e.key === " ") {
                    e.preventDefault();
                    onSelect();
                }
            }}
            aria-pressed={selected}
            className={[
                "flex w-full cursor-pointer flex-col gap-2 rounded-xl border p-4 text-left transition-all",
                selected
                    ? "border-primary bg-primary/5 ring-primary ring-2"
                    : "border-border bg-background hover:border-primary/40 hover:bg-muted/40",
            ].join(" ")}
        >
            <div className="flex items-center justify-between gap-2">
                <div className="flex items-center gap-2">
                    <span className="text-foreground text-sm font-semibold">{framework.label}</span>
                    <AuthorityBadge authority={framework.authority} />
                </div>
                {selected && <CheckCircle2 className="text-primary h-4 w-4 shrink-0" />}
            </div>
            <p className="text-muted-foreground text-xs leading-relaxed">{framework.description}</p>
            <div className="flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px]">
                <span className="text-muted-foreground">
                    Reference: <span className="text-foreground/80 font-medium">{framework.reference}</span>
                </span>
                {framework.source_url && (
                    <a
                        href={framework.source_url}
                        target="_blank"
                        rel="noreferrer"
                        onClick={(e) => e.stopPropagation()}
                        className="text-primary hover:text-primary/80 inline-flex items-center gap-0.5 font-medium"
                    >
                        Source <ExternalLink className="h-3 w-3" />
                    </a>
                )}
            </div>
        </div>
    );
}

function ProgramRow({
    code,
    title,
    meta,
    hint,
    checked,
    onToggle,
}: {
    code: string;
    title: string;
    meta: string;
    hint?: string;
    checked: boolean;
    onToggle: () => void;
}) {
    return (
        <label
            className={[
                "flex cursor-pointer items-start gap-3 rounded-lg border p-3 transition-colors",
                checked ? "border-primary/60 bg-primary/5" : "border-border bg-background hover:border-primary/30",
            ].join(" ")}
        >
            <Checkbox
                checked={checked}
                onCheckedChange={onToggle}
                className="mt-0.5 scale-110"
            />
            <span className="min-w-0 flex-1">
                <span className="flex flex-wrap items-center gap-x-2 gap-y-0.5">
                    <span className="text-foreground text-sm font-medium">{title}</span>
                    <span className="text-muted-foreground font-mono text-[10px]">{code}</span>
                </span>
                <span className="text-muted-foreground mt-0.5 block text-xs">{meta}</span>
                {hint && <span className="text-amber-600 dark:text-amber-400 mt-0.5 block text-[11px]">{hint}</span>}
            </span>
        </label>
    );
}

/* ── Page ── */

export default function Setup() {
    const { meta, branding, catalog } = usePage().props as { meta?: { appName?: string }; branding?: { logo?: string | null }; catalog?: CurriculumCatalog };
    const appName = meta?.appName || "Platform";
    const logoUrl = branding?.logo || null;
    const catalogData: CurriculumCatalog | undefined = catalog;
    const schoolLevelOptions: SchoolLevelOption[] = catalogData?.school_levels ?? SCHOOL_LEVELS;

    const [step, setStep] = useState(1);
    const [direction, setDirection] = useState(1);
    const [showSplash, setShowSplash] = useState(true);
    const [logoPreview, setLogoPreview] = useState<string | null>(null);
    const [programSearch, setProgramSearch] = useState("");

    const goToStep = (next: number) => {
        setDirection(next > step ? 1 : -1);
        setStep(next);
    };

    const { data, setData, post, processing, errors, clearErrors } = useForm({
        // Step 1: Administrator
        admin_name: "",
        admin_email: "",
        admin_password: "",
        admin_password_confirmation: "",
        // Step 2: Institution
        school_name: "",
        school_code: "",
        school_level: "",
        school_description: "",
        school_email: "",
        school_phone: "",
        school_location: "",
        dean_name: "",
        dean_email: "",
        // Step 3: Curriculum & Programs
        curriculum_framework: "",
        programs: [] as string[],
        seed_strand_subjects: false,
        // Step 4: Academic Period
        school_starting_date: "",
        school_ending_date: "",
        semester: "1",
        curriculum_year: "",
        // Step 5: Brand & Appearance
        site_name: "",
        site_description: "",
        theme_color: "#0f172a",
        currency: "PHP",
        support_email: "",
        support_phone: "",
        logo: null as File | null,
        // Step 6: Feature Toggles
        school_portal_enabled: true,
        online_enrollment_enabled: true,
        enable_clearance_check: true,
        enable_signatures: false,
        enable_qr_codes: false,
        enable_public_transactions: false,
        enable_support_page: true,
        inventory_module_enabled: false,
        library_module_enabled: false,
        enable_student_transfer_email_notifications: true,
        enable_faculty_transfer_email_notifications: true,
    });

    /* ── Step validation ── */

    const handleNextStep1 = () => {
        clearErrors();
        const errs: string[] = [];
        if (!data.admin_name.trim()) errs.push("Please provide your full name.");
        if (!data.admin_email || !/^\S+@\S+\.\S+$/.test(data.admin_email)) errs.push("Please provide a valid admin email.");
        if (!data.admin_password || data.admin_password.length < 8) errs.push("Password must be at least 8 characters.");
        if (data.admin_password !== data.admin_password_confirmation) errs.push("Passwords do not match.");
        if (errs.length) {
            errs.forEach((e) => toast.error(e));
            return;
        }
        goToStep(2);
    };

    const handleNextStep2 = () => {
        clearErrors();
        const errs: string[] = [];
        if (!data.school_name.trim()) errs.push("Please provide an institution name.");
        if (!data.school_code.trim()) errs.push("Please provide an institution code.");
        if (!data.school_level) errs.push("Please choose the institution's school level.");
        if (errs.length) {
            errs.forEach((e) => toast.error(e));
            return;
        }
        goToStep(3);
    };

    /* ── Curriculum helpers ── */

    const frameworks = (catalogData?.frameworks ?? []).filter((f) => f.school_levels.includes(data.school_level));
    const currentFramework = frameworks.find((f) => f.value === data.curriculum_framework) ?? null;

    const selectFramework = (value: string) => {
        setData("curriculum_framework", data.curriculum_framework === value ? "" : value);
        setData("programs", []);
        setProgramSearch("");
    };

    const toggleProgram = (code: string) => {
        setData("programs", data.programs.includes(code) ? data.programs.filter((c) => c !== code) : [...data.programs, code]);
    };

    const togglePrograms = (codes: string[]) => {
        const allSelected = codes.every((c) => data.programs.includes(c));
        const merged = new Set(data.programs);
        codes.forEach((c) => (allSelected ? merged.delete(c) : merged.add(c)));
        setData("programs", Array.from(merged));
    };

    const selectedProgramTitles = (): string[] => {
        const titles: string[] = [];
        if (currentFramework?.value === "ched_psg") {
            for (const cluster of catalogData?.ched ?? []) {
                for (const program of cluster.programs) {
                    if (data.programs.includes(program.code)) titles.push(`${program.code} — ${program.title}`);
                }
            }
        } else if (currentFramework?.value === "tesda_tr") {
            for (const sector of catalogData?.tesda ?? []) {
                for (const qualification of sector.qualifications) {
                    if (data.programs.includes(qualification.code)) titles.push(`${qualification.code} — ${qualification.title}`);
                }
            }
        } else if (currentFramework?.value === "deped_shs_k12" || currentFramework?.value === "deped_shs_revised") {
            const tracks = currentFramework?.value === "deped_shs_revised" ? catalogData?.shs.revised : catalogData?.shs.legacy;
            for (const track of tracks ?? []) {
                for (const strand of track.strands) {
                    if (data.programs.includes(`${track.key}:${strand.key}`)) titles.push(`${track.name} · ${strand.name}`);
                }
            }
        }
        return titles;
    };

    const schoolLevelLabel = schoolLevelOptions.find((l) => l.value === data.school_level)?.label ?? data.school_level;

    const enabledToggles: string[] = [];
    if (data.school_portal_enabled) enabledToggles.push("Student Portal");
    if (data.online_enrollment_enabled) enabledToggles.push("Online Enrollment");
    if (data.enable_clearance_check) enabledToggles.push("Clearance Check");
    if (data.enable_signatures) enabledToggles.push("Digital Signatures");
    if (data.enable_qr_codes) enabledToggles.push("QR Codes");
    if (data.enable_public_transactions) enabledToggles.push("Public Transactions");
    if (data.enable_support_page) enabledToggles.push("Support Page");
    if (data.inventory_module_enabled) enabledToggles.push("Inventory Module");
    if (data.library_module_enabled) enabledToggles.push("Library Module");

    const handleNextStep3 = () => {
        clearErrors();
        goToStep(4);
    };

    const handleNextStep4 = () => {
        clearErrors();
        const errs: string[] = [];
        if (!data.school_starting_date) errs.push("Please set the school year start date.");
        if (!data.school_ending_date) errs.push("Please set the school year end date.");
        if (data.school_starting_date && data.school_ending_date && data.school_ending_date <= data.school_starting_date) {
            errs.push("School year end must be after start date.");
        }
        if (errs.length) {
            errs.forEach((e) => toast.error(e));
            return;
        }
        goToStep(5);
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post("/setup", {
            onSuccess: () => {
                toast.success(`${appName} initialized successfully!`);
            },
            onError: (errs) => {
                toast.error("Please correct the errors and try again.");
                if (errs.admin_name || errs.admin_email || errs.admin_password) goToStep(1);
                else if (errs.school_name || errs.school_code || errs.school_level || errs.school_email) goToStep(2);
                else if (errs.curriculum_framework || errs.programs) goToStep(3);
                else if (errs.school_starting_date || errs.school_ending_date || errs.semester) goToStep(4);
            },
        });
    };

    const hasErrors = Object.keys(errors).length > 0;

    if (showSplash) {
        return (
            <div className="bg-background relative flex min-h-screen items-center justify-center overflow-hidden p-4 py-10">
                <Head title={`Welcome to ${appName}`} />

                <div className="pointer-events-none absolute inset-0 overflow-hidden" aria-hidden>
                    <div className="bg-primary/5 absolute -top-1/4 -left-1/4 h-1/2 w-1/2 rounded-full blur-[120px]" />
                    <div className="bg-secondary/10 absolute -right-1/4 -bottom-1/4 h-1/2 w-1/2 rounded-full blur-[120px]" />
                    <div className="absolute inset-0 bg-[linear-gradient(to_right,hsl(var(--border))_1px,transparent_1px),linear-gradient(to_bottom,hsl(var(--border))_1px,transparent_1px)] [mask-image:radial-gradient(ellipse_65%_55%_at_50%_50%,#000_20%,transparent_100%)] bg-[size:4rem_4rem] opacity-20" />
                </div>

                <motion.main
                    initial={{ opacity: 0, y: 18 }}
                    animate={{ opacity: 1, y: 0 }}
                    transition={{ type: "spring", stiffness: 260, damping: 28 }}
                    className="relative z-10 flex min-h-[calc(100vh-5rem)] w-full max-w-[900px] flex-col items-center justify-center text-center"
                >
                    <div className="space-y-10">
                        <div className="flex flex-col items-center gap-5">
                            {logoUrl ? (
                                <img src={logoUrl} alt={appName} className="h-20 w-auto drop-shadow-sm" />
                            ) : (
                                <div className="bg-primary/10 ring-primary/20 rounded-xl p-4 ring-1">
                                    <ShieldCheck className="text-primary h-9 w-9" />
                                </div>
                            )}
                            <div className="text-muted-foreground text-xs font-semibold tracking-[0.28em] uppercase">First-time setup</div>
                        </div>

                        <div className="space-y-5">
                            <h1 className="text-foreground font-serif text-5xl leading-tight font-semibold sm:text-6xl lg:text-7xl">
                                Welcome to {appName}
                            </h1>
                            <p className="text-muted-foreground mx-auto max-w-2xl text-lg leading-relaxed sm:text-xl">
                                A few guided steps will prepare your administrator account, institution profile, academic year, and portal
                                preferences.
                            </p>
                        </div>

                        <div className="text-muted-foreground flex flex-wrap items-center justify-center gap-x-4 gap-y-2 text-sm">
                            <span>Administrator</span>
                            <span className="bg-border h-px w-8" aria-hidden />
                            <span>Institution</span>
                            <span className="bg-border h-px w-8" aria-hidden />
                            <span>Curriculum</span>
                            <span className="bg-border h-px w-8" aria-hidden />
                            <span>Academic Period</span>
                            <span className="bg-border h-px w-8" aria-hidden />
                            <span>Preferences</span>
                        </div>

                        <div className="flex justify-center pt-2">
                            <Button
                                type="button"
                                onClick={() => setShowSplash(false)}
                                className="group bg-primary text-primary-foreground hover:bg-primary/90 h-12 min-w-[220px] px-8 text-base shadow-lg shadow-black/10"
                            >
                                Continue
                                <ArrowRight className="ml-2 h-4 w-4 transition-transform group-hover:translate-x-0.5" />
                            </Button>
                        </div>

                        <div className="flex justify-center">
                            <div className="from-border via-primary/60 to-border h-px w-32 bg-gradient-to-r" aria-hidden />
                        </div>
                    </div>

                    <div className="pointer-events-none absolute inset-x-0 bottom-8 hidden justify-center md:flex" aria-hidden>
                        <div className="text-muted-foreground/50 flex items-center gap-3 text-xs">
                            <Sparkles className="h-3.5 w-3.5" />
                            <span>Setup can be adjusted later from System Management</span>
                        </div>
                    </div>
                </motion.main>
            </div>
        );
    }

    return (
        <div className="bg-background relative flex min-h-screen flex-col items-center justify-center overflow-hidden p-4 py-10">
            <Head title={`Initialize ${appName}`} />

            {/* Ambient blobs */}
            <div className="pointer-events-none absolute inset-0 overflow-hidden" aria-hidden>
                <div className="bg-primary/5 absolute -top-1/4 -left-1/4 h-1/2 w-1/2 rounded-full blur-[120px]" />
                <div className="bg-secondary/10 absolute -right-1/4 -bottom-1/4 h-1/2 w-1/2 rounded-full blur-[120px]" />
                <div className="absolute inset-0 bg-[linear-gradient(to_right,hsl(var(--border))_1px,transparent_1px),linear-gradient(to_bottom,hsl(var(--border))_1px,transparent_1px)] [mask-image:radial-gradient(ellipse_60%_50%_at_50%_50%,#000_20%,transparent_100%)] bg-[size:4rem_4rem] opacity-20" />
            </div>

            <div className="relative z-10 flex w-full max-w-[1100px] flex-col gap-8 md:flex-row md:items-stretch">
                {/* ── Left sidebar ── */}
                <div className="flex w-full shrink-0 flex-col gap-8 md:w-[280px] md:pt-8">
                    {/* Brand */}
                    <motion.div initial={{ opacity: 0, y: 16 }} animate={{ opacity: 1, y: 0 }} className="flex items-center gap-3">
                        {logoUrl ? (
                            <img src={logoUrl} alt={appName} className="h-9 w-auto" />
                        ) : (
                            <div className="bg-primary/10 ring-primary/20 rounded-xl p-2 ring-1">
                                <ShieldCheck className="text-primary h-5 w-5" />
                            </div>
                        )}
                        <span className="text-foreground text-lg font-bold tracking-tight">{appName}</span>
                    </motion.div>

                    {/* Headline */}
                    <motion.div initial={{ opacity: 0, y: 16 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: 0.08 }} className="space-y-2">
                        <h2 className="text-foreground font-serif text-3xl leading-tight">
                            System <br />
                            <span className="text-muted-foreground">Setup.</span>
                        </h2>
                        <p className="text-muted-foreground text-sm leading-relaxed">
                            Configure your installation in a few steps. Required fields get you running; optional steps let you fine-tune later.
                        </p>
                    </motion.div>

                    {/* Step tracker */}
                    <motion.div
                        initial={{ opacity: 0, y: 16 }}
                        animate={{ opacity: 1, y: 0 }}
                        transition={{ delay: 0.16 }}
                        className="relative hidden space-y-5 pt-4 md:block"
                    >
                        <div className="bg-border absolute top-2 bottom-8 left-4 w-0.5 rounded-full">
                            <motion.div
                                className="bg-primary w-full rounded-full"
                                animate={{ height: step === 1 ? "0%" : `${((step - 1) / (STEPS.length - 1)) * 100}%` }}
                                transition={{ ease: "easeInOut", duration: 0.4 }}
                            />
                        </div>

                        {STEPS.map((s, i) => {
                            const num = i + 1;
                            const active = step === num;
                            const done = step > num;
                            return (
                                <div key={num} className="relative z-10 flex items-start gap-4">
                                    <div
                                        className={[
                                            "ring-background mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full ring-4 transition-all duration-300",
                                            done || active
                                                ? "bg-primary text-primary-foreground shadow-primary/20 shadow-md"
                                                : "border-border bg-muted text-muted-foreground border",
                                        ].join(" ")}
                                    >
                                        {done ? <ShieldCheck className="h-3.5 w-3.5" /> : <span className="text-xs font-bold">{num}</span>}
                                    </div>
                                    <div className={`pt-0.5 transition-opacity duration-300 ${active || done ? "opacity-100" : "opacity-40"}`}>
                                        <div className="flex items-center gap-2">
                                            <p className={`text-sm leading-none font-semibold ${active ? "text-foreground" : "text-muted-foreground"}`}>
                                                {s.label}
                                            </p>
                                            {!s.required && (
                                                <Badge variant="outline" className="border-muted-foreground/20 text-muted-foreground px-1 text-[9px]">
                                                    SKIP
                                                </Badge>
                                            )}
                                        </div>
                                        <p className="text-muted-foreground mt-1 text-xs">{s.description}</p>
                                    </div>
                                </div>
                            );
                        })}
                    </motion.div>

                    {/* Skip hint */}
                    {step >= 5 && (
                        <motion.div
                            initial={{ opacity: 0, y: 8 }}
                            animate={{ opacity: 1, y: 0 }}
                            className="border-primary/20 bg-primary/5 hidden rounded-lg border p-3 md:block"
                        >
                            <p className="text-muted-foreground text-xs leading-relaxed">
                                Steps <strong className="text-foreground">3</strong>, <strong className="text-foreground">5</strong> &{" "}
                                <strong className="text-foreground">6</strong> are <span className="text-primary font-medium">optional</span>.
                                You can configure them later from System Management.
                            </p>
                        </motion.div>
                    )}
                </div>

                {/* ── Form area ── */}
                <motion.div initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: 0.24 }} className="w-full flex-1">
                    {/* Progress bar */}
                    <div className="bg-border mb-2 h-1 w-full overflow-hidden rounded-full">
                        <motion.div
                            className="bg-primary h-full rounded-full"
                            animate={{ width: `${(step / STEPS.length) * 100}%` }}
                            transition={{ ease: "easeInOut", duration: 0.35 }}
                        />
                    </div>

                    <Card className="border-border bg-card shadow-foreground/5 relative flex min-h-[520px] flex-col overflow-hidden shadow-xl">
                        {/* Error banner */}
                        <AnimatePresence>
                            {hasErrors && (
                                <motion.div
                                    key="error-banner"
                                    initial={{ opacity: 0, y: -8 }}
                                    animate={{ opacity: 1, y: 0 }}
                                    exit={{ opacity: 0, y: -8 }}
                                    className="absolute inset-x-0 top-0 z-50"
                                >
                                    <Alert variant="destructive" className="bg-destructive/10 text-destructive rounded-none border-x-0 border-t-0">
                                        <AlertCircle className="h-4 w-4" />
                                        <AlertTitle className="text-sm font-semibold">Action Required</AlertTitle>
                                        <AlertDescription className="text-xs">Please correct the highlighted fields below.</AlertDescription>
                                    </Alert>
                                </motion.div>
                            )}
                        </AnimatePresence>

                        <CardContent className="flex-1 p-0">
                            <form onSubmit={submit} noValidate>
                                <AnimatePresence mode="wait" custom={direction}>
                                    {/* ── STEP 1: Admin ── */}
                                    {step === 1 && (
                                        <motion.div
                                            key="step1"
                                            custom={direction}
                                            variants={slideVariants}
                                            initial="hidden"
                                            animate="visible"
                                            exit="exit"
                                            className="space-y-6 p-6 md:p-10"
                                        >
                                            <StepHeader
                                                stepNumber={1}
                                                totalSteps={STEPS.length}
                                                icon={<UserCircle className="h-4 w-4" />}
                                                title="Create your account"
                                                description="This account will have full unrestricted access to all modules and settings."
                                            />

                                            <div className="space-y-4">
                                                <TextField
                                                    id="admin_name"
                                                    label="Full Name"
                                                    placeholder="John Doe"
                                                    value={data.admin_name}
                                                    onChange={(v) => setData("admin_name", v)}
                                                    error={errors.admin_name}
                                                    required
                                                />
                                                <TextField
                                                    id="admin_email"
                                                    label="Email Address"
                                                    type="email"
                                                    placeholder="admin@example.com"
                                                    description="Used for account recovery and system notifications."
                                                    value={data.admin_email}
                                                    onChange={(v) => setData("admin_email", v)}
                                                    error={errors.admin_email}
                                                    required
                                                />
                                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                                    <PasswordField
                                                        id="admin_password"
                                                        label="Password"
                                                        description="At least 8 characters."
                                                        value={data.admin_password}
                                                        onChange={(v) => setData("admin_password", v)}
                                                        error={errors.admin_password}
                                                        required
                                                    />
                                                    <PasswordField
                                                        id="admin_password_confirmation"
                                                        label="Confirm Password"
                                                        value={data.admin_password_confirmation}
                                                        onChange={(v) => setData("admin_password_confirmation", v)}
                                                        required
                                                    />
                                                </div>
                                            </div>

                                            <div className="flex justify-end pt-2">
                                                <Button
                                                    type="button"
                                                    onClick={handleNextStep1}
                                                    className="group bg-primary text-primary-foreground hover:bg-primary/90 h-11 w-full px-8 sm:w-auto"
                                                >
                                                    Continue to Institution
                                                    <ChevronRight className="ml-2 h-4 w-4 transition-transform group-hover:translate-x-0.5" />
                                                </Button>
                                            </div>
                                        </motion.div>
                                    )}

                                    {/* ── STEP 2: Institution ── */}
                                    {step === 2 && (
                                        <motion.div
                                            key="step2"
                                            custom={direction}
                                            variants={slideVariants}
                                            initial="hidden"
                                            animate="visible"
                                            exit="exit"
                                            className="space-y-6 p-6 md:p-10"
                                        >
                                            <StepHeader
                                                stepNumber={2}
                                                totalSteps={STEPS.length}
                                                icon={<Building2 className="h-4 w-4" />}
                                                title="Institution Details"
                                                description="Register the primary educational institution for this deployment."
                                                onBack={() => goToStep(1)}
                                            />

                                            <div className="space-y-5">
                                                {/* Primary Info */}
                                                <div className="space-y-4">
                                                    <div className="flex items-center gap-2 border-b pb-2">
                                                        <Building2 className="text-muted-foreground h-4 w-4" />
                                                        <h3 className="text-foreground text-sm font-medium">Primary Information</h3>
                                                    </div>
                                                    <TextField
                                                        id="school_name"
                                                        label="Institution Name"
                                                        placeholder="e.g. KoAkademy"
                                                        value={data.school_name}
                                                        onChange={(v) => setData("school_name", v)}
                                                        error={errors.school_name}
                                                        required
                                                    />
                                                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                                        <TextField
                                                            id="school_code"
                                                            label="Institution Code"
                                                            placeholder="KOA"
                                                            uppercase
                                                            description="Short acronym identifier."
                                                            value={data.school_code}
                                                            onChange={(v) => setData("school_code", v)}
                                                            error={errors.school_code}
                                                            required
                                                        />
                                                        <TextField
                                                            id="school_email"
                                                            label="Official Email"
                                                            type="email"
                                                            placeholder="info@institution.edu"
                                                            description="Public contact email."
                                                            value={data.school_email}
                                                            onChange={(v) => setData("school_email", v)}
                                                            error={errors.school_email}
                                                        />
                                                    </div>
                                                    <div className="space-y-2">
                                                        <Label className="text-foreground text-sm font-medium">
                                                            School Level
                                                            <span className="text-destructive ml-0.5">*</span>
                                                        </Label>
                                                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                                            {schoolLevelOptions.map((level) => {
                                                                const selected = data.school_level === level.value;

                                                                return (
                                                                    <button
                                                                        key={level.value}
                                                                        type="button"
                                                                        onClick={() => setData("school_level", level.value)}
                                                                        className={[
                                                                            "border-border bg-background text-left transition-colors",
                                                                            "hover:border-primary/50 focus-visible:ring-primary rounded-lg border p-4 focus-visible:ring-2 focus-visible:outline-none",
                                                                            selected ? "border-primary bg-primary/5 ring-primary/20 ring-1" : "",
                                                                            errors.school_level ? "border-destructive" : "",
                                                                        ]
                                                                            .filter(Boolean)
                                                                            .join(" ")}
                                                                        aria-pressed={selected}
                                                                    >
                                                                        <div className="flex items-start gap-3">
                                                                            <div
                                                                                className={[
                                                                                    "mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full border",
                                                                                    selected ? "border-primary bg-primary" : "border-muted-foreground/40",
                                                                                ].join(" ")}
                                                                            >
                                                                                {selected && <ShieldCheck className="text-primary-foreground h-3 w-3" />}
                                                                            </div>
                                                                            <div className="space-y-1">
                                                                                <p className="text-foreground text-sm font-medium">{level.label}</p>
                                                                                <p className="text-muted-foreground text-xs leading-relaxed">{level.description}</p>
                                                                            </div>
                                                                        </div>
                                                                    </button>
                                                                );
                                                            })}
                                                        </div>
                                                        {errors.school_level && (
                                                            <motion.p
                                                                initial={{ opacity: 0, y: -4 }}
                                                                animate={{ opacity: 1, y: 0 }}
                                                                className="text-destructive text-xs"
                                                            >
                                                                {errors.school_level}
                                                            </motion.p>
                                                        )}
                                                    </div>
                                                    <div className="space-y-1.5">
                                                        <Label htmlFor="school_description" className="text-foreground text-sm font-medium">
                                                            Description
                                                        </Label>
                                                        <Textarea
                                                            id="school_description"
                                                            value={data.school_description}
                                                            onChange={(e) => setData("school_description", e.target.value)}
                                                            placeholder="A brief overview of the institution..."
                                                            rows={3}
                                                            className="bg-background border-border placeholder:text-muted-foreground/60 resize-none leading-relaxed hover:border-primary/60 focus-visible:ring-primary focus-visible:border-transparent focus-visible:ring-2"
                                                        />
                                                    </div>
                                                </div>

                                                {/* Contact Info */}
                                                <div className="space-y-4">
                                                    <div className="flex items-center gap-2 border-b pb-2">
                                                        <BookOpen className="text-muted-foreground h-4 w-4" />
                                                        <h3 className="text-foreground text-sm font-medium">Contact & Location</h3>
                                                    </div>
                                                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                                        <TextField
                                                            id="school_phone"
                                                            label="Phone Number"
                                                            type="tel"
                                                            placeholder="+63 2 1234 5678"
                                                            value={data.school_phone}
                                                            onChange={(v) => setData("school_phone", v)}
                                                        />
                                                        <TextField
                                                            id="school_location"
                                                            label="Address / Location"
                                                            placeholder="123 University Ave, City"
                                                            value={data.school_location}
                                                            onChange={(v) => setData("school_location", v)}
                                                        />
                                                    </div>
                                                </div>

                                                {/* Dean Info */}
                                                <div className="space-y-4">
                                                    <div className="flex items-center gap-2 border-b pb-2">
                                                        <GraduationCap className="text-muted-foreground h-4 w-4" />
                                                        <h3 className="text-foreground text-sm font-medium">Dean / Head (Optional)</h3>
                                                    </div>
                                                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                                        <TextField
                                                            id="dean_name"
                                                            label="Dean Name"
                                                            placeholder="Dr. Jane Smith"
                                                            value={data.dean_name}
                                                            onChange={(v) => setData("dean_name", v)}
                                                        />
                                                        <TextField
                                                            id="dean_email"
                                                            label="Dean Email"
                                                            type="email"
                                                            placeholder="dean@institution.edu"
                                                            value={data.dean_email}
                                                            onChange={(v) => setData("dean_email", v)}
                                                        />
                                                    </div>
                                                </div>
                                            </div>

                                            <div className="flex justify-end pt-2">
                                                <Button
                                                    type="button"
                                                    onClick={handleNextStep2}
                                                    className="group bg-primary text-primary-foreground hover:bg-primary/90 h-11 w-full px-8 sm:w-auto"
                                                >
                                                    Continue to Curriculum
                                                    <ChevronRight className="ml-2 h-4 w-4 transition-transform group-hover:translate-x-0.5" />
                                                </Button>
                                            </div>
                                        </motion.div>
                                    )}

                                    {/* ── STEP 3: Curriculum & Programs ── */}
                                    {step === 3 && (
                                        <motion.div
                                            key="step3"
                                            custom={direction}
                                            variants={slideVariants}
                                            initial="hidden"
                                            animate="visible"
                                            exit="exit"
                                            className="space-y-6 p-6 md:p-10"
                                        >
                                            <StepHeader
                                                stepNumber={3}
                                                totalSteps={STEPS.length}
                                                icon={<BookOpen className="h-4 w-4" />}
                                                title="Curriculum & Programs"
                                                description="Align your programs with the latest CHED, DepEd, and TESDA curriculum frameworks. Program structures are pre-created for you."
                                                onBack={() => goToStep(2)}
                                                optional
                                            />

                                            <div className="space-y-5">
                                                {/* Framework selection */}
                                                <div className="space-y-3">
                                                    <div className="flex items-center gap-2 border-b pb-2">
                                                        <ShieldCheck className="text-muted-foreground h-4 w-4" />
                                                        <h3 className="text-foreground text-sm font-medium">Regulatory Framework</h3>
                                                    </div>
                                                    {frameworks.length === 0 ? (
                                                        <div className="border-border bg-muted/50 rounded-lg border p-4">
                                                            <p className="text-muted-foreground text-xs leading-relaxed">
                                                                No curriculum framework applies to the selected institution level. You can skip this step and
                                                                configure programs later from <span className="text-foreground font-medium">Curriculum & Classes</span>.
                                                            </p>
                                                        </div>
                                                    ) : (
                                                        <div className="grid grid-cols-1 gap-3 lg:grid-cols-2">
                                                            {frameworks.map((framework) => (
                                                                <FrameworkCard
                                                                    key={framework.value}
                                                                    framework={framework}
                                                                    selected={data.curriculum_framework === framework.value}
                                                                    onSelect={() => selectFramework(framework.value)}
                                                                />
                                                            ))}
                                                        </div>
                                                    )}
                                                    {catalogData && (
                                                        <p className="text-muted-foreground text-[11px]">
                                                            Catalog researched as of <span className="text-foreground/70 font-medium">{catalogData.as_of}</span>.
                                                            Unverified issuances are flagged for review.
                                                        </p>
                                                    )}
                                                </div>

                                                {currentFramework?.value === "ched_psg" && (
                                                    <div className="space-y-3">
                                                        <div className="flex items-center justify-between gap-2 border-b pb-2">
                                                            <div className="flex items-center gap-2">
                                                                <BookOpen className="text-muted-foreground h-4 w-4" />
                                                                <h3 className="text-foreground text-sm font-medium">Degree & Diploma Programs</h3>
                                                            </div>
                                                            <Badge variant="outline" className="border-primary/30 text-primary text-[10px]">
                                                                {data.programs.length} selected
                                                            </Badge>
                                                        </div>
                                                        <div className="relative">
                                                            <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2" />
                                                            <Input
                                                                value={programSearch}
                                                                onChange={(e) => setProgramSearch(e.target.value)}
                                                                placeholder="Search programs (e.g. Nursing, IT, Accountancy)…"
                                                                className="border-border bg-background text-foreground placeholder:text-muted-foreground/60 focus-visible:ring-primary h-11 pl-9"
                                                            />
                                                        </div>
                                                        <div className="space-y-4">
                                                            {(catalogData?.ched ?? []).map((cluster) => {
                                                                const filtered = cluster.programs.filter(
                                                                    (p) =>
                                                                        !programSearch ||
                                                                        p.title.toLowerCase().includes(programSearch.toLowerCase()) ||
                                                                        p.code.toLowerCase().includes(programSearch.toLowerCase()),
                                                                );
                                                                if (filtered.length === 0) return null;
                                                                return (
                                                                    <div key={cluster.key} className="space-y-2">
                                                                        <div className="flex items-center justify-between">
                                                                            <p className="text-muted-foreground text-xs font-semibold tracking-wide uppercase">
                                                                                {cluster.label}
                                                                            </p>
                                                                            <Button
                                                                                type="button"
                                                                                variant="ghost"
                                                                                size="sm"
                                                                                onClick={() => togglePrograms(filtered.map((p) => p.code))}
                                                                                className="text-primary h-7 px-2 text-xs"
                                                                            >
                                                                                {filtered.every((p) => data.programs.includes(p.code)) ? "Deselect all" : "Select all"}
                                                                            </Button>
                                                                        </div>
                                                                        <div className="grid grid-cols-1 gap-2 lg:grid-cols-2">
                                                                            {filtered.map((program) => (
                                                                                <ProgramRow
                                                                                    key={program.code}
                                                                                    code={program.code}
                                                                                    title={program.title}
                                                                                    meta={`${program.units} units · ${program.year_level}-year · ${program.reference}`}
                                                                                    hint={program.verified ? undefined : "Issuance number not yet verified — confirm with CHED."}
                                                                                    checked={data.programs.includes(program.code)}
                                                                                    onToggle={() => toggleProgram(program.code)}
                                                                                />
                                                                            ))}
                                                                        </div>
                                                                    </div>
                                                                );
                                                            })}
                                                        </div>
                                                    </div>
                                                )}

                                                {currentFramework?.value === "tesda_tr" && (
                                                    <div className="space-y-3">
                                                        <div className="flex items-center justify-between gap-2 border-b pb-2">
                                                            <div className="flex items-center gap-2">
                                                                <ToggleRight className="text-muted-foreground h-4 w-4" />
                                                                <h3 className="text-foreground text-sm font-medium">TVET Qualifications (NC I-IV)</h3>
                                                            </div>
                                                            <Badge variant="outline" className="border-primary/30 text-primary text-[10px]">
                                                                {data.programs.length} selected
                                                            </Badge>
                                                        </div>
                                                        <div className="space-y-4">
                                                            {(catalogData?.tesda ?? []).map((sector) => (
                                                                <div key={sector.key} className="space-y-2">
                                                                    <div className="flex items-center justify-between">
                                                                        <p className="text-muted-foreground text-xs font-semibold tracking-wide uppercase">{sector.label}</p>
                                                                        <Button
                                                                            type="button"
                                                                            variant="ghost"
                                                                            size="sm"
                                                                            onClick={() => togglePrograms(sector.qualifications.map((q) => q.code))}
                                                                            className="text-primary h-7 px-2 text-xs"
                                                                        >
                                                                            {sector.qualifications.every((q) => data.programs.includes(q.code)) ? "Deselect all" : "Select all"}
                                                                        </Button>
                                                                    </div>
                                                                    <div className="grid grid-cols-1 gap-2 lg:grid-cols-2">
                                                                        {sector.qualifications.map((q) => (
                                                                            <ProgramRow
                                                                                key={q.code}
                                                                                code={q.code}
                                                                                title={q.title}
                                                                                meta={q.diploma ? `TESDA Diploma · PQF Level ${q.pqf_level}` : `NC ${q.nc_level} · PQF Level ${q.pqf_level} · ${q.reference}`}
                                                                                hint={q.superseded ? "Superseded TR — a newer Training Regulation exists." : undefined}
                                                                                checked={data.programs.includes(q.code)}
                                                                                onToggle={() => toggleProgram(q.code)}
                                                                            />
                                                                        ))}
                                                                    </div>
                                                                </div>
                                                            ))}
                                                        </div>
                                                    </div>
                                                )}

                                                {(currentFramework?.value === "deped_shs_k12" || currentFramework?.value === "deped_shs_revised") && (
                                                    <div className="space-y-3">
                                                        <div className="flex items-center justify-between gap-2 border-b pb-2">
                                                            <div className="flex items-center gap-2">
                                                                <GraduationCap className="text-muted-foreground h-4 w-4" />
                                                                <h3 className="text-foreground text-sm font-medium">Tracks & Strands</h3>
                                                            </div>
                                                            <Badge variant="outline" className="border-primary/30 text-primary text-[10px]">
                                                                {data.programs.length} selected
                                                            </Badge>
                                                        </div>
                                                        <div className="space-y-4">
                                                            {(currentFramework?.value === "deped_shs_revised" ? catalogData?.shs.revised : catalogData?.shs.legacy)?.map((track) => (
                                                                <div key={track.key} className="border-border rounded-xl border p-4">
                                                                    <div className="flex items-start justify-between gap-3">
                                                                        <div className="space-y-1">
                                                                            <p className="text-foreground text-sm font-semibold">{track.name}</p>
                                                                            <p className="text-muted-foreground text-xs leading-relaxed">{track.description}</p>
                                                                        </div>
                                                                        <Button
                                                                            type="button"
                                                                            variant="ghost"
                                                                            size="sm"
                                                                            onClick={() => togglePrograms(track.strands.map((s) => `${track.key}:${s.key}`))}
                                                                            className="text-primary h-7 shrink-0 px-2 text-xs"
                                                                        >
                                                                            {track.strands.every((s) => data.programs.includes(`${track.key}:${s.key}`)) ? "Deselect" : "Select all"}
                                                                        </Button>
                                                                    </div>
                                                                    <div className="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2">
                                                                        {track.strands.map((strand) => (
                                                                            <ProgramRow
                                                                                key={`${track.key}:${strand.key}`}
                                                                                code={`${track.key}:${strand.key}`}
                                                                                title={strand.name}
                                                                                meta={strand.description}
                                                                                checked={data.programs.includes(`${track.key}:${strand.key}`)}
                                                                                onToggle={() => toggleProgram(`${track.key}:${strand.key}`)}
                                                                            />
                                                                        ))}
                                                                    </div>
                                                                </div>
                                                            ))}
                                                        </div>
                                                        <div className="border-border bg-muted/50 flex items-center justify-between gap-4 rounded-lg border p-4">
                                                            <div className="space-y-0.5">
                                                                <Label htmlFor="seed_strand_subjects" className="text-foreground cursor-pointer text-sm font-medium">
                                                                    Preload SHS core subjects
                                                                </Label>
                                                                <p className="text-muted-foreground text-xs leading-relaxed">
                                                                    Adds the standard 15 core + applied subjects (e.g., Oral Communication, General Mathematics) to
                                                                    each selected strand.
                                                                </p>
                                                            </div>
                                                            <Switch
                                                                id="seed_strand_subjects"
                                                                checked={data.seed_strand_subjects}
                                                                onCheckedChange={(v) => setData("seed_strand_subjects", v)}
                                                                className="shrink-0 scale-125"
                                                            />
                                                        </div>
                                                    </div>
                                                )}

                                                {currentFramework?.value === "deped_matatag" && (
                                                    <div className="space-y-4">
                                                        <div className="flex items-center gap-2 border-b pb-2">
                                                            <CalendarDays className="text-muted-foreground h-4 w-4" />
                                                            <h3 className="text-foreground text-sm font-medium">MATATAG Rollout Timeline</h3>
                                                        </div>
                                                        <div className="grid grid-cols-1 gap-2 sm:grid-cols-3">
                                                            {(catalogData?.matatag.phases ?? []).map((phase) => (
                                                                <div key={phase.sy} className="border-border bg-muted/40 rounded-lg border p-3">
                                                                    <p className="text-foreground text-sm font-semibold">{phase.sy}</p>
                                                                    <p className="text-primary mt-0.5 text-xs font-medium">{phase.grades.join(", ")}</p>
                                                                    <p className="text-muted-foreground mt-1 text-[11px]">{phase.status}</p>
                                                                </div>
                                                            ))}
                                                        </div>
                                                        <div className="space-y-2">
                                                            <p className="text-muted-foreground text-xs font-semibold tracking-wide uppercase">Learning Areas per Grade Band</p>
                                                            <div className="space-y-2">
                                                                {Object.entries(catalogData?.matatag.learning_areas ?? {}).map(([band, areas]) => (
                                                                    <div key={band} className="border-border rounded-lg border p-3">
                                                                        <p className="text-muted-foreground text-[11px] font-medium uppercase">{band.replaceAll("_", " ")}</p>
                                                                        <div className="mt-1.5 flex flex-wrap gap-1.5">
                                                                            {areas.map((area) => (
                                                                                <Badge key={area} variant="outline" className="text-foreground/80 text-[10px]">
                                                                                    {area}
                                                                                </Badge>
                                                                            ))}
                                                                        </div>
                                                                    </div>
                                                                ))}
                                                            </div>
                                                        </div>
                                                        <div className="border-border bg-muted/50 rounded-lg border p-4">
                                                            <p className="text-muted-foreground text-xs leading-relaxed">
                                                                The MATATAG framework is recorded on your institution for grade-band configuration. GMRC, Values
                                                                Education, and Makabansa are reflected in the learning areas above; Mother Tongue is now used as a
                                                                medium of instruction rather than a separate subject.
                                                            </p>
                                                        </div>
                                                    </div>
                                                )}
                                            </div>

                                            <div className="flex items-center justify-between gap-3 pt-2">
                                                <Button type="button" variant="outline" onClick={() => goToStep(2)} className="h-11">
                                                    <ChevronLeft className="mr-1 h-4 w-4" /> Back
                                                </Button>
                                                <div className="flex gap-2">
                                                    <Button type="button" variant="ghost" onClick={() => goToStep(4)} className="text-muted-foreground h-11">
                                                        Skip for now
                                                    </Button>
                                                    <Button
                                                        type="button"
                                                        onClick={handleNextStep3}
                                                        className="group bg-primary text-primary-foreground hover:bg-primary/90 h-11 px-8"
                                                    >
                                                        {data.programs.length > 0
                                                            ? `Continue (${data.programs.length} selected)`
                                                            : "Continue to Academic Period"}
                                                        <ChevronRight className="ml-2 h-4 w-4 transition-transform group-hover:translate-x-0.5" />
                                                    </Button>
                                                </div>
                                            </div>
                                        </motion.div>
                                    )}

                                    {/* ── STEP 4: Academic Period ── */}
                                    {step === 4 && (
                                        <motion.div
                                            key="step4"
                                            custom={direction}
                                            variants={slideVariants}
                                            initial="hidden"
                                            animate="visible"
                                            exit="exit"
                                            className="space-y-6 p-6 md:p-10"
                                        >
                                            <StepHeader
                                                stepNumber={4}
                                                totalSteps={STEPS.length}
                                                icon={<GraduationCap className="h-4 w-4" />}
                                                title="Academic Period"
                                                description="Set the current school year, semester, and curriculum year to seed GeneralSettings."
                                                onBack={() => goToStep(3)}
                                            />

                                            <div className="space-y-4">
                                                {/* Semester select */}
                                                <div className="space-y-1.5">
                                                    <Label htmlFor="semester" className="text-foreground text-sm font-medium">
                                                        Current Semester
                                                        <span className="text-destructive ml-0.5">*</span>
                                                    </Label>
                                                    <Select value={data.semester} onValueChange={(val) => setData("semester", val ?? "")}>
                                                        <SelectTrigger
                                                            id="semester"
                                                            className={[
                                                                "border-border bg-background text-foreground hover:border-primary/60 h-11 transition-colors",
                                                                errors.semester ? "border-destructive" : "",
                                                            ].join(" ")}
                                                        >
                                                            <SelectValue placeholder="Select semester…" />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem value="1">1st Semester</SelectItem>
                                                            <SelectItem value="2">2nd Semester</SelectItem>
                                                            <SelectItem value="3">Summer / Midyear</SelectItem>
                                                        </SelectContent>
                                                    </Select>
                                                    {errors.semester && (
                                                        <motion.p
                                                            initial={{ opacity: 0, y: -4 }}
                                                            animate={{ opacity: 1, y: 0 }}
                                                            className="text-destructive text-xs"
                                                        >
                                                            {errors.semester}
                                                        </motion.p>
                                                    )}
                                                </div>

                                                {/* Calendar presets */}
                                                {(catalogData?.calendars ?? []).length > 0 && (
                                                    <div className="space-y-2">
                                                        <p className="text-muted-foreground text-xs font-semibold tracking-wide uppercase">
                                                            Official Calendar Presets
                                                        </p>
                                                        <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                                            {(catalogData?.calendars ?? []).map((preset) => (
                                                                <button
                                                                    key={preset.key}
                                                                    type="button"
                                                                    onClick={() => {
                                                                        setData("school_starting_date", preset.starts);
                                                                        setData("school_ending_date", preset.ends);
                                                                        setData("semester", "1");
                                                                        setData("curriculum_year", `${new Date(preset.starts).getFullYear()}-${new Date(preset.ends).getFullYear()}`);
                                                                    }}
                                                                    className="border-border hover:border-primary/50 bg-muted/40 hover:bg-muted/60 flex flex-col gap-1 rounded-lg border p-3 text-left transition-colors"
                                                                >
                                                                    <span className="text-foreground text-sm font-medium">{preset.label}</span>
                                                                    <span className="text-muted-foreground text-[11px] leading-relaxed">{preset.note}</span>
                                                                    <span className="text-primary text-[11px] font-medium">{preset.source}</span>
                                                                </button>
                                                            ))}
                                                        </div>
                                                    </div>
                                                )}

                                                {/* Curriculum year preview */}
                                                {data.school_starting_date && data.school_ending_date && (
                                                    <div className="border-border bg-muted/50 flex items-center gap-3 rounded-lg border p-3">
                                                        <BookOpen className="text-primary h-4 w-4 shrink-0" />
                                                        <div className="text-sm">
                                                            <span className="text-muted-foreground">Curriculum Year: </span>
                                                            <span className="text-foreground font-medium">{data.curriculum_year}</span>
                                                            <span className="text-muted-foreground ml-1 text-xs">(auto-calculated)</span>
                                                        </div>
                                                    </div>
                                                )}

                                                {/* Date pickers */}
                                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                                    <DateField
                                                        id="school_starting_date"
                                                        label="School Year Start"
                                                        value={data.school_starting_date}
                                                        onChange={(v) => {
                                                            setData("school_starting_date", v);
                                                            if (v && data.school_ending_date) {
                                                                const start = new Date(v).getFullYear();
                                                                const end = new Date(data.school_ending_date).getFullYear();
                                                                setData("curriculum_year", `${start}-${end}`);
                                                            }
                                                        }}
                                                        error={errors.school_starting_date}
                                                        required
                                                    />
                                                    <DateField
                                                        id="school_ending_date"
                                                        label="School Year End"
                                                        value={data.school_ending_date}
                                                        min={data.school_starting_date || undefined}
                                                        onChange={(v) => {
                                                            setData("school_ending_date", v);
                                                            if (data.school_starting_date && v) {
                                                                const start = new Date(data.school_starting_date).getFullYear();
                                                                const end = new Date(v).getFullYear();
                                                                setData("curriculum_year", `${start}-${end}`);
                                                            }
                                                        }}
                                                        error={errors.school_ending_date}
                                                        required
                                                    />
                                                </div>

                                                {/* Info callout */}
                                                <div className="border-border bg-muted/50 flex items-start gap-3 rounded-lg border p-4">
                                                    <BookOpen className="text-muted-foreground mt-0.5 h-4 w-4 shrink-0" />
                                                    <p className="text-muted-foreground text-xs leading-relaxed">
                                                        These values populate <span className="text-foreground font-medium">GeneralSettings</span> and
                                                        are used to filter classes, enrollments, and reports. You can update them anytime from the
                                                        admin panel.
                                                    </p>
                                                </div>
                                            </div>

                                            <div className="flex items-center justify-between gap-3 pt-2">
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    onClick={() => goToStep(3)}
                                                    className="h-11"
                                                >
                                                    <ChevronLeft className="mr-1 h-4 w-4" /> Back
                                                </Button>
                                                <div className="flex gap-2">
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        onClick={() => goToStep(7)}
                                                        className="text-muted-foreground h-11"
                                                    >
                                                        Skip optional steps
                                                        <Rocket className="ml-2 h-4 w-4" />
                                                    </Button>
                                                    <Button
                                                        type="button"
                                                        onClick={handleNextStep4}
                                                        className="group bg-primary text-primary-foreground hover:bg-primary/90 h-11 px-8"
                                                    >
                                                        Continue
                                                        <ChevronRight className="ml-2 h-4 w-4 transition-transform group-hover:translate-x-0.5" />
                                                    </Button>
                                                </div>
                                            </div>
                                        </motion.div>
                                    )}

                                    {/* ── STEP 5: Brand & Appearance ── */}
                                    {step === 5 && (
                                        <motion.div
                                            key="step5"
                                            custom={direction}
                                            variants={slideVariants}
                                            initial="hidden"
                                            animate="visible"
                                            exit="exit"
                                            className="space-y-6 p-6 md:p-10"
                                        >
                                            <StepHeader
                                                stepNumber={5}
                                                totalSteps={STEPS.length}
                                                icon={<Palette className="h-4 w-4" />}
                                                title="Brand & Appearance"
                                                description="Customize the visual identity and currency for your platform. Configurable later from System Management."
                                                onBack={() => goToStep(4)}
                                                optional
                                            />

                                            <div className="space-y-5">
                                                {/* Site Identity */}
                                                <div className="space-y-4">
                                                    <div className="flex items-center gap-2 border-b pb-2">
                                                        <Palette className="text-muted-foreground h-4 w-4" />
                                                        <h3 className="text-foreground text-sm font-medium">Site Identity</h3>
                                                    </div>
                                                    <TextField
                                                        id="site_name"
                                                        label="Site Name"
                                                        placeholder="e.g. KoAkademy Portal"
                                                        description="Overrides the platform name displayed across the app."
                                                        value={data.site_name}
                                                        onChange={(v) => setData("site_name", v)}
                                                        error={errors.site_name}
                                                    />
                                                    <div className="space-y-1.5">
                                                        <Label htmlFor="site_description" className="text-foreground text-sm font-medium">
                                                            Site Description
                                                        </Label>
                                                        <Textarea
                                                            id="site_description"
                                                            value={data.site_description}
                                                            onChange={(e) => setData("site_description", e.target.value)}
                                                            placeholder="A short tagline or description of your institution..."
                                                            rows={2}
                                                            className="bg-background border-border placeholder:text-muted-foreground/60 resize-none leading-relaxed hover:border-primary/60 focus-visible:ring-primary focus-visible:border-transparent focus-visible:ring-2"
                                                        />
                                                    </div>
                                                </div>

                                                {/* Logo Upload */}
                                                <div className="space-y-4">
                                                    <div className="flex items-center gap-2 border-b pb-2">
                                                        <ImageIcon className="text-muted-foreground h-4 w-4" />
                                                        <h3 className="text-foreground text-sm font-medium">Institution Logo</h3>
                                                    </div>
                                                    <div className="space-y-3">
                                                        <div
                                                            className="border-border hover:border-primary/50 bg-muted/30 relative flex h-32 cursor-pointer items-center justify-center overflow-hidden rounded-xl border-2 border-dashed transition-colors"
                                                            onClick={() => document.getElementById("logo-upload")?.click()}
                                                        >
                                                            {logoPreview ? (
                                                                <img
                                                                    src={logoPreview}
                                                                    alt="Logo preview"
                                                                    className="max-h-28 max-w-full object-contain p-3"
                                                                />
                                                            ) : (
                                                                <div className="flex flex-col items-center gap-2 text-center">
                                                                    <ImageIcon className="text-muted-foreground/60 h-8 w-8" />
                                                                    <div>
                                                                        <p className="text-foreground text-sm font-medium">Upload your logo</p>
                                                                        <p className="text-muted-foreground text-xs">PNG, JPG, SVG, or WEBP (max 5MB)</p>
                                                                    </div>
                                                                </div>
                                                            )}
                                                        </div>
                                                        <input
                                                            id="logo-upload"
                                                            type="file"
                                                            accept="image/*"
                                                            className="hidden"
                                                            onChange={(e) => {
                                                                const file = e.target.files?.[0] || null;
                                                                setData("logo", file);
                                                                if (file) {
                                                                    const reader = new FileReader();
                                                                    reader.onload = (ev) => setLogoPreview(ev.target?.result as string);
                                                                    reader.readAsDataURL(file);
                                                                } else {
                                                                    setLogoPreview(null);
                                                                }
                                                            }}
                                                        />
                                                        <div className="border-border bg-muted/50 flex items-start gap-2.5 rounded-lg border p-3">
                                                            <Sparkles className="text-primary mt-0.5 h-3.5 w-3.5 shrink-0" />
                                                            <p className="text-muted-foreground text-xs leading-relaxed">
                                                                A single upload auto-generates <span className="text-foreground font-medium">favicon</span>,{" "}
                                                                <span className="text-foreground font-medium">PWA icons</span>,{" "}
                                                                <span className="text-foreground font-medium">Apple touch icon</span>, and{" "}
                                                                <span className="text-foreground font-medium">OG image</span> for SEO and social sharing.
                                                            </p>
                                                        </div>
                                                    </div>
                                                </div>

                                                {/* Theme Color */}
                                                <div className="space-y-4">
                                                    <div className="flex items-center gap-2 border-b pb-2">
                                                        <Sparkles className="text-muted-foreground h-4 w-4" />
                                                        <h3 className="text-foreground text-sm font-medium">Theme Color</h3>
                                                    </div>
                                                    <div className="flex flex-wrap gap-3">
                                                        {THEME_COLORS.map((color) => (
                                                            <button
                                                                key={color.value}
                                                                type="button"
                                                                onClick={() => setData("theme_color", color.value)}
                                                                className={`group relative flex h-10 w-10 items-center justify-center rounded-full border-2 transition-all ${
                                                                    data.theme_color === color.value
                                                                        ? "border-foreground scale-110 ring-2 ring-foreground/20"
                                                                        : "border-transparent hover:scale-105"
                                                                }`}
                                                                title={color.label}
                                                            >
                                                                <div
                                                                    className="h-7 w-7 rounded-full shadow-sm"
                                                                    style={{ backgroundColor: color.value }}
                                                                />
                                                                {data.theme_color === color.value && (
                                                                    <ShieldCheck className="text-primary-foreground absolute h-4 w-4 drop-shadow-[0_1px_2px_rgba(0,0,0,0.5)]" />
                                                                )}
                                                            </button>
                                                        ))}
                                                        {/* Custom color input */}
                                                        <div className="flex items-center gap-2">
                                                            <input
                                                                type="color"
                                                                value={data.theme_color}
                                                                onChange={(e) => setData("theme_color", e.target.value)}
                                                                className="h-10 w-10 cursor-pointer rounded-full border-0 bg-transparent p-0"
                                                                title="Custom color"
                                                            />
                                                            <span className="text-muted-foreground font-mono text-xs">{data.theme_color}</span>
                                                        </div>
                                                    </div>
                                                </div>

                                                {/* Currency & Support */}
                                                <div className="space-y-4">
                                                    <div className="flex items-center gap-2 border-b pb-2">
                                                        <BookOpen className="text-muted-foreground h-4 w-4" />
                                                        <h3 className="text-foreground text-sm font-medium">Currency & Support</h3>
                                                    </div>
                                                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                                        <div className="space-y-1.5">
                                                            <Label htmlFor="currency" className="text-foreground text-sm font-medium">
                                                                Currency
                                                            </Label>
                                                            <Select value={data.currency} onValueChange={(val) => setData("currency", val ?? "")}>
                                                                <SelectTrigger
                                                                    id="currency"
                                                                    className="border-border bg-background text-foreground hover:border-primary/60 h-11 transition-colors"
                                                                >
                                                                    <SelectValue placeholder="Select currency…" />
                                                                </SelectTrigger>
                                                                <SelectContent>
                                                                    {CURRENCIES.map((c) => (
                                                                        <SelectItem key={c.value} value={c.value}>
                                                                            {c.label}
                                                                        </SelectItem>
                                                                    ))}
                                                                </SelectContent>
                                                            </Select>
                                                        </div>
                                                        <div className="space-y-1.5" />
                                                    </div>
                                                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                                        <TextField
                                                            id="support_email"
                                                            label="Support Email"
                                                            type="email"
                                                            placeholder="support@institution.edu"
                                                            description="For user inquiries and helpdesk."
                                                            value={data.support_email}
                                                            onChange={(v) => setData("support_email", v)}
                                                            error={errors.support_email}
                                                        />
                                                        <TextField
                                                            id="support_phone"
                                                            label="Support Phone"
                                                            type="tel"
                                                            placeholder="+63 2 1234 5678"
                                                            value={data.support_phone}
                                                            onChange={(v) => setData("support_phone", v)}
                                                            error={errors.support_phone}
                                                        />
                                                    </div>
                                                </div>
                                            </div>

                                            <div className="flex items-center justify-between gap-3 pt-2">
                                                <Button type="button" variant="outline" onClick={() => goToStep(4)} className="h-11">
                                                    <ChevronLeft className="mr-1 h-4 w-4" /> Back
                                                </Button>
                                                <div className="flex gap-2">
                                                    <Button type="button" variant="ghost" onClick={() => goToStep(6)} className="text-muted-foreground h-11">
                                                        Skip for now
                                                    </Button>
                                                    <Button
                                                        type="button"
                                                        onClick={() => goToStep(6)}
                                                        className="group bg-primary text-primary-foreground hover:bg-primary/90 h-11 px-8"
                                                    >
                                                        Continue to Features
                                                        <ChevronRight className="ml-2 h-4 w-4 transition-transform group-hover:translate-x-0.5" />
                                                    </Button>
                                                </div>
                                            </div>
                                        </motion.div>
                                    )}

                                    {/* ── STEP 6: Feature Toggles ── */}
                                    {step === 6 && (
                                        <motion.div
                                            key="step6"
                                            custom={direction}
                                            variants={slideVariants}
                                            initial="hidden"
                                            animate="visible"
                                            exit="exit"
                                            className="space-y-6 p-6 md:p-10"
                                        >
                                            <StepHeader
                                                stepNumber={6}
                                                totalSteps={STEPS.length}
                                                icon={<ToggleRight className="h-4 w-4" />}
                                                title="Feature Toggles"
                                                description="Enable or disable platform modules. All can be reconfigured later from System Management."
                                                onBack={() => goToStep(5)}
                                                optional
                                            />

                                            <div className="space-y-5">
                                                {/* Portal & Enrollment */}
                                                <div className="space-y-3">
                                                    <div className="flex items-center gap-2 border-b pb-2">
                                                        <Building2 className="text-muted-foreground h-4 w-4" />
                                                        <h3 className="text-foreground text-sm font-medium">Portal & Enrollment</h3>
                                                    </div>
                                                    <FeatureToggle
                                                        id="school_portal_enabled"
                                                        label="Student Portal"
                                                        description="Enable the student-facing portal with self-service access."
                                                        checked={data.school_portal_enabled}
                                                        onCheckedChange={(v) => setData("school_portal_enabled", v)}
                                                        icon={<Building2 className="h-4 w-4" />}
                                                    />
                                                    <FeatureToggle
                                                        id="online_enrollment_enabled"
                                                        label="Online Enrollment"
                                                        description="Allow students to enroll in courses through the portal."
                                                        checked={data.online_enrollment_enabled}
                                                        onCheckedChange={(v) => setData("online_enrollment_enabled", v)}
                                                        icon={<GraduationCap className="h-4 w-4" />}
                                                    />
                                                    <FeatureToggle
                                                        id="enable_support_page"
                                                        label="Support Page"
                                                        description="Public-facing help and support request page."
                                                        checked={data.enable_support_page}
                                                        onCheckedChange={(v) => setData("enable_support_page", v)}
                                                        icon={<BookOpen className="h-4 w-4" />}
                                                    />
                                                </div>

                                                {/* Verification & Security */}
                                                <div className="space-y-3">
                                                    <div className="flex items-center gap-2 border-b pb-2">
                                                        <ShieldCheck className="text-muted-foreground h-4 w-4" />
                                                        <h3 className="text-foreground text-sm font-medium">Verification & Security</h3>
                                                    </div>
                                                    <FeatureToggle
                                                        id="enable_clearance_check"
                                                        label="Clearance Check"
                                                        description="Require clearance verification before graduation or transfer."
                                                        checked={data.enable_clearance_check}
                                                        onCheckedChange={(v) => setData("enable_clearance_check", v)}
                                                        icon={<ShieldCheck className="h-4 w-4" />}
                                                    />
                                                    <FeatureToggle
                                                        id="enable_signatures"
                                                        label="Digital Signatures"
                                                        description="Enable digital signature workflows for documents and approvals."
                                                        checked={data.enable_signatures}
                                                        onCheckedChange={(v) => setData("enable_signatures", v)}
                                                        icon={<Sparkles className="h-4 w-4" />}
                                                    />
                                                    <FeatureToggle
                                                        id="enable_qr_codes"
                                                        label="QR Codes"
                                                        description="Generate QR codes for IDs, receipts, and verification links."
                                                        checked={data.enable_qr_codes}
                                                        onCheckedChange={(v) => setData("enable_qr_codes", v)}
                                                        icon={<ToggleRight className="h-4 w-4" />}
                                                    />
                                                    <FeatureToggle
                                                        id="enable_public_transactions"
                                                        label="Public Transactions"
                                                        description="Allow transaction records to be viewed by the public."
                                                        checked={data.enable_public_transactions}
                                                        onCheckedChange={(v) => setData("enable_public_transactions", v)}
                                                        icon={<BookOpen className="h-4 w-4" />}
                                                    />
                                                </div>

                                                {/* Modules */}
                                                <div className="space-y-3">
                                                    <div className="flex items-center gap-2 border-b pb-2">
                                                        <Rocket className="text-muted-foreground h-4 w-4" />
                                                        <h3 className="text-foreground text-sm font-medium">Extended Modules</h3>
                                                    </div>
                                                    <FeatureToggle
                                                        id="inventory_module_enabled"
                                                        label="Inventory Module"
                                                        description="Track supplies, equipment, and asset management."
                                                        checked={data.inventory_module_enabled}
                                                        onCheckedChange={(v) => setData("inventory_module_enabled", v)}
                                                        icon={<BookOpen className="h-4 w-4" />}
                                                    />
                                                    <FeatureToggle
                                                        id="library_module_enabled"
                                                        label="Library Module"
                                                        description="Catalog management, borrowing, and returns tracking."
                                                        checked={data.library_module_enabled}
                                                        onCheckedChange={(v) => setData("library_module_enabled", v)}
                                                        icon={<BookOpen className="h-4 w-4" />}
                                                    />
                                                </div>

                                                {/* Email Notifications */}
                                                <div className="space-y-3">
                                                    <div className="flex items-center gap-2 border-b pb-2">
                                                        <AlertCircle className="text-muted-foreground h-4 w-4" />
                                                        <h3 className="text-foreground text-sm font-medium">Email Notifications</h3>
                                                    </div>
                                                    <FeatureToggle
                                                        id="enable_student_transfer_email_notifications"
                                                        label="Student Transfer Emails"
                                                        description="Send email alerts when students transfer between departments."
                                                        checked={data.enable_student_transfer_email_notifications}
                                                        onCheckedChange={(v) => setData("enable_student_transfer_email_notifications", v)}
                                                        icon={<AlertCircle className="h-4 w-4" />}
                                                    />
                                                    <FeatureToggle
                                                        id="enable_faculty_transfer_email_notifications"
                                                        label="Faculty Transfer Emails"
                                                        description="Send email alerts when faculty members transfer between departments."
                                                        checked={data.enable_faculty_transfer_email_notifications}
                                                        onCheckedChange={(v) => setData("enable_faculty_transfer_email_notifications", v)}
                                                        icon={<AlertCircle className="h-4 w-4" />}
                                                    />
                                                </div>
                                            </div>

                                            {/* Continue to review */}
                                            <div className="space-y-3 pt-2">
                                                <div className="flex items-center justify-between gap-3">
                                                    <Button type="button" variant="outline" onClick={() => goToStep(5)} className="h-11">
                                                        <ChevronLeft className="mr-1 h-4 w-4" /> Back
                                                    </Button>
                                                    <Button
                                                        type="button"
                                                        onClick={() => goToStep(7)}
                                                        className="group bg-primary text-primary-foreground hover:bg-primary/90 h-11 px-8"
                                                    >
                                                        Continue to Review
                                                        <ChevronRight className="ml-2 h-4 w-4 transition-transform group-hover:translate-x-0.5" />
                                                    </Button>
                                                </div>
                                            </div>
                                        </motion.div>
                                    )}

                                    {/* ── STEP 7: Review & Finish ── */}
                                    {step === 7 && (
                                        <motion.div
                                            key="step7"
                                            custom={direction}
                                            variants={slideVariants}
                                            initial="hidden"
                                            animate="visible"
                                            exit="exit"
                                            className="space-y-6 p-6 md:p-10"
                                        >
                                            <StepHeader
                                                stepNumber={7}
                                                totalSteps={STEPS.length}
                                                icon={<ClipboardList className="h-4 w-4" />}
                                                title="Review & Finish"
                                                description="Confirm the details below. Everything can still be adjusted later from System Management."
                                                onBack={() => goToStep(6)}
                                            />

                                            <div className="space-y-4">
                                                <div className="border-border rounded-lg border p-4">
                                                    <div className="flex items-center gap-2 border-b pb-2">
                                                        <UserCircle className="text-muted-foreground h-4 w-4" />
                                                        <h3 className="text-foreground text-sm font-medium">Administrator</h3>
                                                    </div>
                                                    <dl className="mt-2 grid grid-cols-1 gap-x-6 gap-y-1.5 text-sm sm:grid-cols-2">
                                                        <div>
                                                            <dt className="text-muted-foreground text-xs">Full Name</dt>
                                                            <dd className="text-foreground font-medium">{data.admin_name || "—"}</dd>
                                                        </div>
                                                        <div>
                                                            <dt className="text-muted-foreground text-xs">Email Address</dt>
                                                            <dd className="text-foreground font-medium">{data.admin_email || "—"}</dd>
                                                        </div>
                                                    </dl>
                                                </div>

                                                <div className="border-border rounded-lg border p-4">
                                                    <div className="flex items-center gap-2 border-b pb-2">
                                                        <Building2 className="text-muted-foreground h-4 w-4" />
                                                        <h3 className="text-foreground text-sm font-medium">Institution</h3>
                                                    </div>
                                                    <dl className="mt-2 grid grid-cols-1 gap-x-6 gap-y-1.5 text-sm sm:grid-cols-2">
                                                        <div>
                                                            <dt className="text-muted-foreground text-xs">Name</dt>
                                                            <dd className="text-foreground font-medium">{data.school_name || "—"}</dd>
                                                        </div>
                                                        <div>
                                                            <dt className="text-muted-foreground text-xs">Code</dt>
                                                            <dd className="text-foreground font-medium">{data.school_code || "—"}</dd>
                                                        </div>
                                                        <div>
                                                            <dt className="text-muted-foreground text-xs">School Level</dt>
                                                            <dd className="text-foreground font-medium">{schoolLevelLabel}</dd>
                                                        </div>
                                                        <div>
                                                            <dt className="text-muted-foreground text-xs">Location</dt>
                                                            <dd className="text-foreground font-medium">{data.school_location || "—"}</dd>
                                                        </div>
                                                    </dl>
                                                </div>

                                                <div className="border-border rounded-lg border p-4">
                                                    <div className="flex items-center gap-2 border-b pb-2">
                                                        <BookOpen className="text-muted-foreground h-4 w-4" />
                                                        <h3 className="text-foreground text-sm font-medium">Curriculum & Programs</h3>
                                                    </div>
                                                    {currentFramework ? (
                                                        <div className="mt-2 space-y-1.5 text-sm">
                                                            <p className="text-foreground flex items-center gap-2 font-medium">
                                                                {currentFramework.label} <AuthorityBadge authority={currentFramework.authority} />
                                                            </p>
                                                            <p className="text-muted-foreground text-xs">Reference: {currentFramework.reference}</p>
                                                            {data.programs.length > 0 ? (
                                                                <div className="flex flex-wrap gap-1.5 pt-1">
                                                                    {selectedProgramTitles().map((title) => (
                                                                        <Badge key={title} variant="outline" className="text-foreground/80 text-[10px]">
                                                                            {title}
                                                                        </Badge>
                                                                    ))}
                                                                </div>
                                                            ) : (
                                                                <p className="text-muted-foreground text-xs">No programs selected — you can add them later.</p>
                                                            )}
                                                            {data.seed_strand_subjects && (
                                                                <p className="text-primary text-xs font-medium">SHS core subjects will be preloaded for each selected strand.</p>
                                                            )}
                                                        </div>
                                                    ) : (
                                                        <p className="text-muted-foreground mt-2 text-xs">Skipped — curriculum can be configured later from Curriculum & Classes.</p>
                                                    )}
                                                </div>

                                                <div className="border-border rounded-lg border p-4">
                                                    <div className="flex items-center gap-2 border-b pb-2">
                                                        <CalendarDays className="text-muted-foreground h-4 w-4" />
                                                        <h3 className="text-foreground text-sm font-medium">Academic Period</h3>
                                                    </div>
                                                    <dl className="mt-2 grid grid-cols-1 gap-x-6 gap-y-1.5 text-sm sm:grid-cols-2">
                                                        <div>
                                                            <dt className="text-muted-foreground text-xs">School Year Start</dt>
                                                            <dd className="text-foreground font-medium">{data.school_starting_date || "—"}</dd>
                                                        </div>
                                                        <div>
                                                            <dt className="text-muted-foreground text-xs">School Year End</dt>
                                                            <dd className="text-foreground font-medium">{data.school_ending_date || "—"}</dd>
                                                        </div>
                                                        <div>
                                                            <dt className="text-muted-foreground text-xs">Semester / Term</dt>
                                                            <dd className="text-foreground font-medium">
                                                                {data.semester === "1" ? "1st Semester" : data.semester === "2" ? "2nd Semester" : data.semester === "3" ? "Summer / Midyear" : "—"}
                                                            </dd>
                                                        </div>
                                                        <div>
                                                            <dt className="text-muted-foreground text-xs">Curriculum Year</dt>
                                                            <dd className="text-foreground font-medium">{data.curriculum_year || "—"}</dd>
                                                        </div>
                                                    </dl>
                                                </div>

                                                <div className="border-border rounded-lg border p-4">
                                                    <div className="flex items-center gap-2 border-b pb-2">
                                                        <Palette className="text-muted-foreground h-4 w-4" />
                                                        <h3 className="text-foreground text-sm font-medium">Brand & Appearance</h3>
                                                    </div>
                                                    <dl className="mt-2 grid grid-cols-1 gap-x-6 gap-y-1.5 text-sm sm:grid-cols-2">
                                                        <div>
                                                            <dt className="text-muted-foreground text-xs">Site Name</dt>
                                                            <dd className="text-foreground font-medium">{data.site_name || data.school_name || "—"}</dd>
                                                        </div>
                                                        <div>
                                                            <dt className="text-muted-foreground text-xs">Currency</dt>
                                                            <dd className="text-foreground font-medium">{data.currency}</dd>
                                                        </div>
                                                        <div>
                                                            <dt className="text-muted-foreground text-xs">Theme Color</dt>
                                                            <dd className="flex items-center gap-2 font-medium">
                                                                <span className="border-border inline-block h-4 w-4 rounded-full border" style={{ backgroundColor: data.theme_color }} />
                                                                <span className="text-foreground">{data.theme_color}</span>
                                                            </dd>
                                                        </div>
                                                        <div>
                                                            <dt className="text-muted-foreground text-xs">Logo</dt>
                                                            <dd className="text-foreground font-medium">{logoPreview ? "Uploaded" : "—"}</dd>
                                                        </div>
                                                    </dl>
                                                </div>

                                                <div className="border-border rounded-lg border p-4">
                                                    <div className="flex items-center gap-2 border-b pb-2">
                                                        <ToggleRight className="text-muted-foreground h-4 w-4" />
                                                        <h3 className="text-foreground text-sm font-medium">Feature Toggles</h3>
                                                    </div>
                                                    <div className="mt-2 flex flex-wrap gap-1.5">
                                                        {enabledToggles.map((toggle) => (
                                                            <Badge key={toggle} variant="outline" className="border-primary/30 text-primary text-[10px]">
                                                                {toggle}
                                                            </Badge>
                                                        ))}
                                                        {enabledToggles.length === 0 && (
                                                            <p className="text-muted-foreground text-xs">All modules disabled — enable them later from System Management.</p>
                                                        )}
                                                    </div>
                                                </div>
                                            </div>

                                            {/* Submit section */}
                                            <div className="space-y-3 pt-2">
                                                <Button
                                                    type="submit"
                                                    disabled={processing}
                                                    className="group bg-primary text-primary-foreground hover:bg-primary/90 relative h-12 w-full overflow-hidden text-base font-medium"
                                                >
                                                    <span
                                                        className={`flex items-center justify-center gap-2 transition-transform duration-300 ${processing ? "-translate-y-12" : "translate-y-0"}`}
                                                    >
                                                        <Sparkles className="h-4 w-4 opacity-70" />
                                                        Complete Setup
                                                    </span>
                                                    <span
                                                        className={`absolute inset-0 flex items-center justify-center gap-2 transition-transform duration-300 ${processing ? "translate-y-0" : "translate-y-12"}`}
                                                    >
                                                        <Loader2 className="h-4 w-4 animate-spin opacity-70" />
                                                        Initializing…
                                                    </span>
                                                </Button>
                                                <p className="text-muted-foreground text-center text-xs">
                                                    All settings can be modified later from{" "}
                                                    <span className="text-foreground font-medium">System Management</span>.
                                                </p>
                                            </div>
                                        </motion.div>
                                    )}
                                </AnimatePresence>
                            </form>
                        </CardContent>
                    </Card>
                </motion.div>
            </div>

            <style dangerouslySetInnerHTML={{ __html: `[type="date"]::-webkit-calendar-picker-indicator { opacity: 0.4; cursor: pointer; }` }} />
        </div>
    );
}
