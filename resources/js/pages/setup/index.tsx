import { Badge } from "@/components/reui/badge";
import { Stepper, StepperIndicator, StepperItem, StepperNav, StepperSeparator, StepperTitle, StepperTrigger } from "@/components/reui/stepper";
import { BeamSearch } from "@/components/spectrumui/beam-search";
import { KbdKey } from "@/components/spectrumui/kbd-key";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from "@/components/ui/collapsible";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Switch } from "@/components/ui/switch";
import { Head, useForm, usePage } from "@inertiajs/react";
import {
    ArrowLeft,
    BookOpen,
    CheckCircle2,
    ChevronDown,
    ExternalLink,
    Eye,
    EyeOff,
    Globe,
    GraduationCap,
    Info,
    Layers,
    Lock,
    Mail,
    Rocket,
    School,
    Sparkles,
    Terminal,
    Upload,
    User,
    Wrench,
} from "lucide-react";
import React, { useMemo, useState } from "react";
import { toast } from "sonner";

/* ── Types ── */

type SchoolLevelOption = {
    value: string;
    label: string;
    description: string;
};

type FrameworkOption = {
    country_code?: string;
    value: string;
    label: string;
    authority: string;
    description: string;
    reference: string;
    school_levels: string[];
    source_url: string | null;
};

type ProgramItem = {
    code: string;
    title: string;
    description?: string;
    meta?: string;
    hint?: string | null;
};

type ProgramGroup = {
    key: string;
    label: string;
    programs: ProgramItem[];
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
type TesdaSector = { key: string; label: string; qualifications: TesdaQualification[] };
type MatatagPhase = { sy: string; grades: string[]; status: string };

type CalendarPreset = {
    country_code?: string;
    key: string;
    label: string;
    starts: string;
    ends: string;
    terms: number;
    note: string;
    source: string;
    estimated: boolean;
};

type CountryCatalog = {
    as_of?: string | null;
    frameworks?: FrameworkOption[];
    program_groups?: Record<string, ProgramGroup[]>;
    calendars?: CalendarPreset[];
    ched?: ChedCluster[];
    shs?: { legacy: ShsTrack[]; revised: ShsTrack[] };
    tesda?: TesdaSector[];
    matatag?: { phases: MatatagPhase[]; learning_areas: Record<string, string[]> };
};

type CurriculumCatalog = CountryCatalog & {
    countries?: { code: string; name: string }[];
    catalogs_by_country?: Record<string, CountryCatalog>;
    by_country?: Record<string, CountryCatalog>;
    school_levels: SchoolLevelOption[];
};

const DEFAULT_SCHOOL_LEVELS: SchoolLevelOption[] = [
    {
        value: "higher_education",
        label: "Higher Education / University",
        description: "Undergraduate, graduate, colleges, and university-tier programs.",
    },
    {
        value: "senior_high",
        label: "Senior Secondary / High School",
        description: "Upper secondary or high school operations (Grades 11–12).",
    },
    {
        value: "junior_high",
        label: "Junior Secondary / Middle School",
        description: "Middle school or intermediate secondary educational programs.",
    },
    {
        value: "elementary",
        label: "Primary / Elementary School",
        description: "Foundational primary school and elementary operations.",
    },
    {
        value: "technical_vocational",
        label: "Vocational & Trade Academy",
        description: "Technical, vocational education and workforce certifications (TVET).",
    },
];

const THEME_ACCENTS = [
    { name: "Slate", value: "#0f172a" },
    { name: "Navy", value: "#1e3a5f" },
    { name: "Emerald", value: "#065f46" },
    { name: "Violet", value: "#581c87" },
    { name: "Amber", value: "#7c2d12" },
    { name: "Rose", value: "#9f1239" },
    { name: "Cyan", value: "#164e63" },
    { name: "Zinc", value: "#27272a" },
];

const QUICK_CURRENCIES = ["USD", "EUR", "GBP", "CAD", "AUD", "SGD", "JPY", "INR"];

const GLOBAL_CALENDAR_PRESETS = [
    {
        label: "Fall – Spring Academic Cycle",
        note: "August to May (Standard Northern Hemisphere)",
        starts: "2026-08-15",
        ends: "2027-05-30",
        year: "2026-2027",
    },
    {
        label: "Calendar Year Cycle",
        note: "January to November (Annual Session)",
        starts: "2027-01-15",
        ends: "2027-11-20",
        year: "2027",
    },
    {
        label: "Southern Hemisphere Cycle",
        note: "February to December",
        starts: "2027-02-15",
        ends: "2027-12-15",
        year: "2027",
    },
];

export default function Setup() {
    const { meta, branding, catalog } = usePage().props as {
        meta?: { appName?: string };
        branding?: { logo?: string | null };
        catalog?: CurriculumCatalog;
    };

    const appName = meta?.appName || "KoAkademy";
    const logoUrl = branding?.logo || null;
    const catalogData: CurriculumCatalog | undefined = catalog;
    const schoolLevelOptions: SchoolLevelOption[] = catalogData?.school_levels ?? DEFAULT_SCHOOL_LEVELS;
    const countries = catalogData?.countries ?? [];

    /* ── Guided State ── */
    const [currentStep, setCurrentStep] = useState<number>(1);
    const [programSearch, setProgramSearch] = useState("");
    const [showPassword, setShowPassword] = useState(false);
    const [showConfirmPassword, setShowConfirmPassword] = useState(false);
    const [isCampusContactsOpen, setIsCampusContactsOpen] = useState(false);
    const [logoPreview, setLogoPreview] = useState<string | null>(null);

    const { data, setData, post, processing, errors, clearErrors } = useForm({
        // Milestone 1: Administrator
        admin_name: "",
        admin_email: "",
        admin_password: "",
        admin_password_confirmation: "",
        // Milestone 2: Institution
        school_name: "",
        school_code: "",
        country_code: "US", // Clean OSS default
        school_level: "higher_education",
        school_description: "",
        school_email: "",
        school_phone: "",
        school_location: "",
        dean_name: "",
        dean_email: "",
        // Milestone 3: Curriculum & Architecture
        curriculum_framework: "",
        programs: [] as string[],
        seed_strand_subjects: false,
        // Milestone 4: Calendar & Currency
        school_starting_date: "2026-08-15",
        school_ending_date: "2027-05-30",
        semester: "1",
        curriculum_year: "2026-2027",
        currency: "USD",
        // Milestone 5: Customization & Modules
        site_name: "",
        site_description: "",
        theme_color: "#0f172a",
        support_email: "",
        support_phone: "",
        logo: null as File | null,
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

    /* ── Catalog Resolution ── */

    const countryCatalog = useMemo(() => {
        const catalogs = catalogData?.catalogs_by_country ?? catalogData?.by_country;
        if (catalogs && data.country_code && catalogs[data.country_code]) {
            return catalogs[data.country_code];
        }
        if (data.country_code === "PH") {
            return catalogData;
        }
        return undefined;
    }, [catalogData, data.country_code]);

    const availableFrameworks = useMemo(() => {
        const raw = countryCatalog?.frameworks ?? [];
        return raw.filter((f) => {
            const matchesCountry = !f.country_code || f.country_code === data.country_code;
            const matchesLevel = f.school_levels?.includes(data.school_level);
            return matchesCountry && matchesLevel;
        });
    }, [countryCatalog, data.country_code, data.school_level]);

    const activeFramework = useMemo(() => {
        return availableFrameworks.find((f) => f.value === data.curriculum_framework) ?? null;
    }, [availableFrameworks, data.curriculum_framework]);

    const currentProgramGroups = useMemo(() => {
        if (!activeFramework) return [];
        return countryCatalog?.program_groups?.[activeFramework.value] ?? [];
    }, [countryCatalog, activeFramework]);

    const regionalCalendarPresets = useMemo(() => {
        const raw = countryCatalog?.calendars ?? [];
        return raw.filter((p) => !p.country_code || p.country_code === data.country_code);
    }, [countryCatalog, data.country_code]);

    const selectedCountryName = useMemo(() => {
        return countries.find((c) => c.code === data.country_code)?.name ?? data.country_code;
    }, [countries, data.country_code]);

    /* ── Validations ── */

    const isStep1Valid = useMemo(() => {
        return (
            Boolean(data.admin_name.trim()) &&
            Boolean(data.admin_email.trim()) &&
            data.admin_password.length >= 8 &&
            data.admin_password === data.admin_password_confirmation
        );
    }, [data.admin_name, data.admin_email, data.admin_password, data.admin_password_confirmation]);

    const isStep2Valid = useMemo(() => {
        return Boolean(data.school_name.trim()) && Boolean(data.school_code.trim()) && Boolean(data.country_code) && Boolean(data.school_level);
    }, [data.school_name, data.school_code, data.country_code, data.school_level]);

    const isStep3Valid = true;

    const isStep4Valid = useMemo(() => {
        const hasDates =
            Boolean(data.school_starting_date) && Boolean(data.school_ending_date) && data.school_ending_date > data.school_starting_date;
        const hasSemester = Boolean(data.semester);
        const hasCurrency = data.country_code === "PH" ? Boolean(data.currency) : /^[A-Z]{3}$/.test(data.currency.trim());
        return hasDates && hasSemester && hasCurrency;
    }, [data.school_starting_date, data.school_ending_date, data.semester, data.country_code, data.currency]);

    /* ── Handlers ── */

    const handleCountryChange = (newCode: string) => {
        if (newCode === data.country_code) return;
        setData((prev) => ({
            ...prev,
            country_code: newCode,
            curriculum_framework: "",
            programs: [],
            seed_strand_subjects: false,
            currency: newCode === "PH" ? "PHP" : prev.currency === "PHP" ? "USD" : prev.currency || "USD",
        }));
        setProgramSearch("");
    };

    const handleLevelChange = (newLevel: string) => {
        if (newLevel === data.school_level) return;
        setData((prev) => ({
            ...prev,
            school_level: newLevel,
            curriculum_framework: "",
            programs: [],
            seed_strand_subjects: false,
        }));
        setProgramSearch("");
    };

    const toggleFramework = (val: string) => {
        setData((prev) => ({
            ...prev,
            curriculum_framework: prev.curriculum_framework === val ? "" : val,
            programs: [],
            seed_strand_subjects: false,
        }));
        setProgramSearch("");
    };

    const toggleProgramCode = (code: string) => {
        setData((prev) => {
            const has = prev.programs.includes(code);
            return {
                ...prev,
                programs: has ? prev.programs.filter((c) => c !== code) : [...prev.programs, code],
            };
        });
    };

    const toggleAllProgramsInGroup = (codes: string[]) => {
        setData((prev) => {
            const allSelected = codes.every((c) => prev.programs.includes(c));
            const set = new Set(prev.programs);
            if (allSelected) {
                codes.forEach((c) => set.delete(c));
            } else {
                codes.forEach((c) => set.add(c));
            }
            return { ...prev, programs: Array.from(set) };
        });
    };

    const handleLogoFile = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (file) {
            setData("logo", file);
            setLogoPreview(URL.createObjectURL(file));
        }
    };

    const handleFillDemoData = () => {
        setData((prev) => ({
            ...prev,
            admin_name: "Alex Morgan",
            admin_email: "admin@institution.edu",
            admin_password: "AdminPassword123!",
            admin_password_confirmation: "AdminPassword123!",
            school_name: "Pacific Institute of Science & Technology",
            school_code: "PIST",
            country_code: prev.country_code || "US",
            school_level: "higher_education",
            school_starting_date: "2026-08-15",
            school_ending_date: "2027-05-30",
            semester: "1",
            curriculum_year: "2026-2027",
            currency: prev.country_code === "PH" ? "PHP" : "USD",
            site_name: "Pacific Institute of Science & Technology",
            site_description: "Academic Information System & Student Portal",
            theme_color: "#0f172a",
        }));
        toast.success("Demo credentials & institutional profile prefilled.");
    };

    const handleNextStep = () => {
        clearErrors();

        if (currentStep === 1) {
            if (!isStep1Valid) {
                toast.error("Please provide administrator full name, email, and matching passwords (min. 8 characters).");
                return;
            }
            setCurrentStep(2);
            return;
        }

        if (currentStep === 2) {
            if (!isStep2Valid) {
                toast.error("Please configure the institution name, short code, and tier.");
                return;
            }
            setCurrentStep(3);
            return;
        }

        if (currentStep === 3) {
            setCurrentStep(4);
            return;
        }

        if (currentStep === 4) {
            if (!isStep4Valid) {
                toast.error("Please provide valid school dates and a 3-letter currency code.");
                return;
            }
            setCurrentStep(5);
            return;
        }
    };

    const handlePrevStep = () => {
        if (currentStep > 1) {
            setCurrentStep((prev) => prev - 1);
        }
    };

    const handleSubmit = (e?: React.FormEvent) => {
        if (e) e.preventDefault();
        clearErrors();

        if (!isStep1Valid) {
            toast.error("Administrator account credentials are required.");
            setCurrentStep(1);
            return;
        }
        if (!isStep2Valid) {
            toast.error("Institution profile and country are required.");
            setCurrentStep(2);
            return;
        }
        if (!isStep4Valid) {
            toast.error("Academic dates and a 3-letter currency code are required.");
            setCurrentStep(4);
            return;
        }

        post("/setup", {
            onSuccess: () => {
                toast.success(`${appName} initialized successfully.`);
            },
            onError: (errs) => {
                toast.error("Configuration validation failed. Please check the highlighted inputs.");
                if (errs.admin_name || errs.admin_email || errs.admin_password || errs.admin_password_confirmation) {
                    setCurrentStep(1);
                } else if (errs.school_name || errs.school_code || errs.country_code || errs.school_level || errs.school_email) {
                    setCurrentStep(2);
                } else if (errs.curriculum_framework || errs.programs || errs.seed_strand_subjects) {
                    setCurrentStep(3);
                } else if (errs.school_starting_date || errs.school_ending_date || errs.semester || errs.curriculum_year || errs.currency) {
                    setCurrentStep(4);
                }
            },
        });
    };

    return (
        <div className="bg-background text-foreground selection:bg-primary/20 min-h-screen font-sans antialiased">
            <Head title={`Setup — ${appName}`} />

            {/* Minimalist Spectrum-style Top Bar */}
            <header className="border-border/40 border-b">
                <div className="mx-auto flex h-14 max-w-2xl items-center justify-between px-4 sm:px-6">
                    <div className="flex items-center gap-3">
                        {logoUrl ? (
                            <img src={logoUrl} alt={appName} className="size-6 object-contain" />
                        ) : (
                            <div className="bg-foreground text-background flex size-6 items-center justify-center rounded-sm">
                                <Terminal className="size-3" />
                            </div>
                        )}
                        <span className="text-foreground text-sm font-medium tracking-tight">{appName}</span>
                        <span className="text-border">/</span>
                        <span className="text-muted-foreground text-xs">Setup</span>
                    </div>

                    <div className="flex items-center gap-2">
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            onClick={handleFillDemoData}
                            className="text-muted-foreground hover:text-foreground h-8 gap-1.5 px-2 text-xs"
                        >
                            <Sparkles className="size-3 text-amber-500" />
                            <span>Prefill Demo</span>
                            <KbdKey size="xs">D</KbdKey>
                        </Button>
                    </div>
                </div>
            </header>

            {/* Centered Minimalist Canvas */}
            <main className="mx-auto max-w-2xl px-4 py-10 sm:px-6">
                {/* ReUI Minimalist Stepper */}
                <div className="mb-10">
                    <Stepper value={currentStep} onValueChange={setCurrentStep} className="w-full">
                        <StepperNav className="flex w-full items-center justify-between">
                            <StepperItem step={1} completed={isStep1Valid}>
                                <StepperTrigger className="gap-2 text-xs">
                                    <StepperIndicator />
                                    <StepperTitle className="hidden sm:inline">Account</StepperTitle>
                                </StepperTrigger>
                                <StepperSeparator />
                            </StepperItem>

                            <StepperItem step={2} completed={isStep2Valid}>
                                <StepperTrigger className="gap-2 text-xs">
                                    <StepperIndicator />
                                    <StepperTitle className="hidden sm:inline">Institution</StepperTitle>
                                </StepperTrigger>
                                <StepperSeparator />
                            </StepperItem>

                            <StepperItem step={3} completed={isStep3Valid}>
                                <StepperTrigger className="gap-2 text-xs">
                                    <StepperIndicator />
                                    <StepperTitle className="hidden sm:inline">Curriculum</StepperTitle>
                                </StepperTrigger>
                                <StepperSeparator />
                            </StepperItem>

                            <StepperItem step={4} completed={isStep4Valid}>
                                <StepperTrigger className="gap-2 text-xs">
                                    <StepperIndicator />
                                    <StepperTitle className="hidden sm:inline">Schedule</StepperTitle>
                                </StepperTrigger>
                                <StepperSeparator />
                            </StepperItem>

                            <StepperItem step={5} completed={isStep1Valid && isStep2Valid && isStep4Valid}>
                                <StepperTrigger className="gap-2 text-xs">
                                    <StepperIndicator />
                                    <StepperTitle className="hidden sm:inline">Deploy</StepperTitle>
                                </StepperTrigger>
                            </StepperItem>
                        </StepperNav>
                    </Stepper>
                </div>

                {/* ── STEP 1: Administrator Account ── */}
                {currentStep === 1 && (
                    <div className="space-y-6">
                        <div className="space-y-1">
                            <div className="flex items-center gap-2">
                                <Badge variant="primary-light" size="xs">
                                    Milestone 1
                                </Badge>
                                <span className="text-muted-foreground text-xs">Root Governance</span>
                            </div>
                            <h2 className="text-foreground text-xl font-medium tracking-tight">Root Administrator</h2>
                            <p className="text-muted-foreground text-xs leading-relaxed">
                                Create the initial superuser account for system access and institution administration.
                            </p>
                        </div>

                        <div className="border-border/40 space-y-4 border-t pt-6">
                            <div className="space-y-1.5">
                                <Label htmlFor="admin_name" className="text-xs font-medium">
                                    Administrator Name <span className="text-destructive">*</span>
                                </Label>
                                <div className="relative">
                                    <User className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                                    <Input
                                        id="admin_name"
                                        value={data.admin_name}
                                        onChange={(e) => {
                                            setData("admin_name", e.target.value);
                                            clearErrors("admin_name");
                                        }}
                                        placeholder="Alex Morgan"
                                        className="h-10 pl-9 text-sm"
                                    />
                                </div>
                                {errors.admin_name && <p className="text-destructive text-xs">{errors.admin_name}</p>}
                            </div>

                            <div className="space-y-1.5">
                                <Label htmlFor="admin_email" className="text-xs font-medium">
                                    Root Admin Email <span className="text-destructive">*</span>
                                </Label>
                                <div className="relative">
                                    <Mail className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                                    <Input
                                        id="admin_email"
                                        type="email"
                                        value={data.admin_email}
                                        onChange={(e) => {
                                            setData("admin_email", e.target.value);
                                            clearErrors("admin_email");
                                        }}
                                        placeholder="admin@institution.edu"
                                        className="h-10 pl-9 text-sm"
                                    />
                                </div>
                                {errors.admin_email && <p className="text-destructive text-xs">{errors.admin_email}</p>}
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="space-y-1.5">
                                    <Label htmlFor="admin_password" className="text-xs font-medium">
                                        Master Password <span className="text-destructive">*</span>
                                    </Label>
                                    <div className="relative">
                                        <Lock className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                                        <Input
                                            id="admin_password"
                                            type={showPassword ? "text" : "password"}
                                            value={data.admin_password}
                                            onChange={(e) => {
                                                setData("admin_password", e.target.value);
                                                clearErrors("admin_password");
                                            }}
                                            placeholder="Min. 8 characters"
                                            className="h-10 px-9 font-mono text-sm"
                                        />
                                        <button
                                            type="button"
                                            onClick={() => setShowPassword((p) => !p)}
                                            tabIndex={-1}
                                            className="text-muted-foreground hover:text-foreground absolute top-1/2 right-3 -translate-y-1/2"
                                        >
                                            {showPassword ? <EyeOff className="size-4" /> : <Eye className="size-4" />}
                                        </button>
                                    </div>
                                    {errors.admin_password && <p className="text-destructive text-xs">{errors.admin_password}</p>}
                                </div>

                                <div className="space-y-1.5">
                                    <Label htmlFor="admin_password_confirmation" className="text-xs font-medium">
                                        Confirm Password <span className="text-destructive">*</span>
                                    </Label>
                                    <div className="relative">
                                        <Lock className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                                        <Input
                                            id="admin_password_confirmation"
                                            type={showConfirmPassword ? "text" : "password"}
                                            value={data.admin_password_confirmation}
                                            onChange={(e) => {
                                                setData("admin_password_confirmation", e.target.value);
                                                clearErrors("admin_password_confirmation");
                                            }}
                                            placeholder="Repeat password"
                                            className="h-10 px-9 font-mono text-sm"
                                        />
                                        <button
                                            type="button"
                                            onClick={() => setShowConfirmPassword((p) => !p)}
                                            tabIndex={-1}
                                            className="text-muted-foreground hover:text-foreground absolute top-1/2 right-3 -translate-y-1/2"
                                        >
                                            {showConfirmPassword ? <EyeOff className="size-4" /> : <Eye className="size-4" />}
                                        </button>
                                    </div>
                                    {errors.admin_password_confirmation && (
                                        <p className="text-destructive text-xs">{errors.admin_password_confirmation}</p>
                                    )}
                                </div>
                            </div>
                        </div>
                    </div>
                )}

                {/* ── STEP 2: Institution Profile ── */}
                {currentStep === 2 && (
                    <div className="space-y-6">
                        <div className="space-y-1">
                            <div className="flex items-center gap-2">
                                <Badge variant="primary-light" size="xs">
                                    Milestone 2
                                </Badge>
                                <span className="text-muted-foreground text-xs">Campus Identity</span>
                            </div>
                            <h2 className="text-foreground text-xl font-medium tracking-tight">Institution Profile</h2>
                            <p className="text-muted-foreground text-xs leading-relaxed">
                                Define your institutional identity, academic tier, and host country jurisdiction.
                            </p>
                        </div>

                        <div className="border-border/40 space-y-5 border-t pt-6">
                            {/* Country jurisdiction */}
                            <div className="space-y-1.5">
                                <div className="flex items-center justify-between">
                                    <Label htmlFor="country_code" className="text-xs font-medium">
                                        Host Jurisdiction <span className="text-destructive">*</span>
                                    </Label>
                                    <span className="text-muted-foreground font-mono text-xs">{data.country_code}</span>
                                </div>
                                <div className="relative">
                                    <Globe className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                                    <select
                                        id="country_code"
                                        value={data.country_code}
                                        onChange={(e) => handleCountryChange(e.target.value)}
                                        className="border-input bg-background text-foreground focus-visible:ring-ring h-10 w-full rounded-md border pr-3 pl-9 text-sm focus-visible:ring-1 focus-visible:outline-none"
                                    >
                                        <option value="">Select country...</option>
                                        {countries.map((c) => (
                                            <option key={c.code} value={c.code}>
                                                {c.name} ({c.code})
                                            </option>
                                        ))}
                                    </select>
                                </div>
                            </div>

                            {/* Name and Acronym */}
                            <div className="grid gap-4 sm:grid-cols-3">
                                <div className="space-y-1.5 sm:col-span-2">
                                    <Label htmlFor="school_name" className="text-xs font-medium">
                                        Institution Name <span className="text-destructive">*</span>
                                    </Label>
                                    <Input
                                        id="school_name"
                                        value={data.school_name}
                                        onChange={(e) => {
                                            setData("school_name", e.target.value);
                                            clearErrors("school_name");
                                        }}
                                        placeholder="Pacific Institute of Science & Technology"
                                        className="h-10 text-sm"
                                    />
                                    {errors.school_name && <p className="text-destructive text-xs">{errors.school_name}</p>}
                                </div>

                                <div className="space-y-1.5">
                                    <Label htmlFor="school_code" className="text-xs font-medium">
                                        Acronym Code <span className="text-destructive">*</span>
                                    </Label>
                                    <Input
                                        id="school_code"
                                        value={data.school_code}
                                        onChange={(e) => {
                                            setData("school_code", e.target.value.toUpperCase().slice(0, 20));
                                            clearErrors("school_code");
                                        }}
                                        placeholder="PIST"
                                        className="h-10 font-mono text-sm uppercase"
                                    />
                                    {errors.school_code && <p className="text-destructive text-xs">{errors.school_code}</p>}
                                </div>
                            </div>

                            {/* School Level Spectrum Rows */}
                            <div className="space-y-2 pt-2">
                                <Label className="text-xs font-medium">
                                    Academic Tier <span className="text-destructive">*</span>
                                </Label>
                                <div className="divide-border/40 border-border/40 divide-y border-y">
                                    {schoolLevelOptions.map((opt) => {
                                        const selected = data.school_level === opt.value;
                                        const IconComponent =
                                            opt.value === "higher_education"
                                                ? GraduationCap
                                                : opt.value === "senior_high"
                                                  ? School
                                                  : opt.value === "junior_high"
                                                    ? BookOpen
                                                    : opt.value === "elementary"
                                                      ? Sparkles
                                                      : Wrench;

                                        return (
                                            <button
                                                key={opt.value}
                                                type="button"
                                                onClick={() => handleLevelChange(opt.value)}
                                                className={`flex w-full items-center justify-between py-3 text-left transition-colors ${
                                                    selected ? "text-foreground font-medium" : "text-muted-foreground hover:text-foreground"
                                                }`}
                                            >
                                                <div className="flex items-center gap-3">
                                                    <IconComponent className={`size-4 ${selected ? "text-primary" : "text-muted-foreground"}`} />
                                                    <div>
                                                        <span className="block text-xs leading-none font-medium">{opt.label}</span>
                                                        <span className="text-muted-foreground mt-0.5 block text-[11px]">{opt.description}</span>
                                                    </div>
                                                </div>
                                                {selected && <CheckCircle2 className="text-primary size-4 shrink-0" />}
                                            </button>
                                        );
                                    })}
                                </div>
                            </div>

                            {/* Optional Campus Details Collapsible */}
                            <Collapsible
                                open={isCampusContactsOpen}
                                onOpenChange={setIsCampusContactsOpen}
                                className="border-border/40 border-t pt-2"
                            >
                                <CollapsibleTrigger asChild>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        className="text-muted-foreground hover:text-foreground flex w-full items-center justify-between p-0 text-xs"
                                    >
                                        <span className="flex items-center gap-2">
                                            <Info className="size-3.5" />
                                            Additional Campus Details (Optional)
                                        </span>
                                        <ChevronDown
                                            className={`size-3.5 transition-transform duration-200 ${isCampusContactsOpen ? "rotate-180" : ""}`}
                                        />
                                    </Button>
                                </CollapsibleTrigger>
                                <CollapsibleContent className="space-y-4 pt-4 text-xs">
                                    <div className="grid gap-3 sm:grid-cols-2">
                                        <div className="space-y-1">
                                            <Label htmlFor="school_email" className="text-[11px]">
                                                Campus Email
                                            </Label>
                                            <Input
                                                id="school_email"
                                                type="email"
                                                value={data.school_email}
                                                onChange={(e) => setData("school_email", e.target.value)}
                                                placeholder="contact@institution.edu"
                                                className="h-9 text-xs"
                                            />
                                        </div>
                                        <div className="space-y-1">
                                            <Label htmlFor="school_phone" className="text-[11px]">
                                                Campus Phone
                                            </Label>
                                            <Input
                                                id="school_phone"
                                                value={data.school_phone}
                                                onChange={(e) => setData("school_phone", e.target.value)}
                                                placeholder="+1 555 123 4567"
                                                className="h-9 text-xs"
                                            />
                                        </div>
                                        <div className="space-y-1 sm:col-span-2">
                                            <Label htmlFor="school_location" className="text-[11px]">
                                                Physical Address
                                            </Label>
                                            <Input
                                                id="school_location"
                                                value={data.school_location}
                                                onChange={(e) => setData("school_location", e.target.value)}
                                                placeholder="Main Campus Grounds, City"
                                                className="h-9 text-xs"
                                            />
                                        </div>
                                        <div className="space-y-1">
                                            <Label htmlFor="dean_name" className="text-[11px]">
                                                Dean / Department Head
                                            </Label>
                                            <Input
                                                id="dean_name"
                                                value={data.dean_name}
                                                onChange={(e) => setData("dean_name", e.target.value)}
                                                placeholder="Dr. Morgan Blake"
                                                className="h-9 text-xs"
                                            />
                                        </div>
                                        <div className="space-y-1">
                                            <Label htmlFor="dean_email" className="text-[11px]">
                                                Dean Email
                                            </Label>
                                            <Input
                                                id="dean_email"
                                                type="email"
                                                value={data.dean_email}
                                                onChange={(e) => setData("dean_email", e.target.value)}
                                                placeholder="dean@institution.edu"
                                                className="h-9 text-xs"
                                            />
                                        </div>
                                    </div>
                                </CollapsibleContent>
                            </Collapsible>
                        </div>
                    </div>
                )}

                {/* ── STEP 3: Curriculum Architecture ── */}
                {currentStep === 3 && (
                    <div className="space-y-6">
                        <div className="space-y-1">
                            <div className="flex items-center gap-2">
                                <Badge variant="primary-light" size="xs">
                                    Milestone 3
                                </Badge>
                                <span className="text-muted-foreground text-xs">{selectedCountryName}</span>
                            </div>
                            <h2 className="text-foreground text-xl font-medium tracking-tight">Academic Architecture</h2>
                            <p className="text-muted-foreground text-xs leading-relaxed">
                                Standard global hierarchy or localized regulatory framework template.
                            </p>
                        </div>

                        <div className="border-border/40 space-y-6 border-t pt-6">
                            {/* Standard Global Academic Architecture Description */}
                            <div className="space-y-2">
                                <div className="flex items-center gap-2">
                                    <Layers className="text-primary size-4" />
                                    <h3 className="text-foreground text-xs font-semibold">Standard Global Academic Model</h3>
                                </div>
                                <p className="text-muted-foreground text-xs leading-relaxed">
                                    Structured hierarchically: <strong>Departments</strong> → <strong>Programs</strong> →{" "}
                                    <strong>Courses & Subjects</strong> → <strong>Sections</strong>. Suitable for universities and secondary academies
                                    worldwide without external regulatory mandates.
                                </p>
                            </div>

                            {/* Regional Regulatory Frameworks */}
                            {availableFrameworks.length === 0 ? (
                                <div className="text-muted-foreground border-border/40 border-t pt-4 text-xs leading-relaxed">
                                    No regional regulatory framework packs are required or installed for {selectedCountryName}. You may define custom
                                    faculties, programs, and degrees freely in the administration console after deployment.
                                </div>
                            ) : (
                                <div className="border-border/40 space-y-4 border-t pt-4">
                                    <div className="flex items-center justify-between">
                                        <div className="space-y-0.5">
                                            <span className="text-foreground text-xs font-medium">
                                                Regional Framework Templates ({selectedCountryName})
                                            </span>
                                            <p className="text-muted-foreground text-[11px]">
                                                Select an official template to preload programs, or leave unselected for standard cataloging.
                                            </p>
                                        </div>
                                        {activeFramework && (
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => setData((p) => ({ ...p, curriculum_framework: "", programs: [] }))}
                                                className="text-muted-foreground hover:text-foreground h-6 text-[10px]"
                                            >
                                                Clear
                                            </Button>
                                        )}
                                    </div>

                                    <div className="divide-border/40 border-border/40 divide-y border-y">
                                        {availableFrameworks.map((f) => {
                                            const isSelected = data.curriculum_framework === f.value;
                                            return (
                                                <div
                                                    key={f.value}
                                                    role="button"
                                                    tabIndex={0}
                                                    onClick={() => toggleFramework(f.value)}
                                                    onKeyDown={(e) => {
                                                        if (e.key === "Enter" || e.key === " ") {
                                                            e.preventDefault();
                                                            toggleFramework(f.value);
                                                        }
                                                    }}
                                                    className="flex cursor-pointer items-start justify-between py-3 text-left transition-colors"
                                                >
                                                    <div className="space-y-1">
                                                        <div className="flex items-center gap-2">
                                                            <span className="text-foreground text-xs font-medium">{f.label}</span>
                                                            <Badge variant="outline" size="xs">
                                                                {f.authority}
                                                            </Badge>
                                                        </div>
                                                        <p className="text-muted-foreground line-clamp-2 text-[11px]">{f.description}</p>
                                                        <div className="text-muted-foreground flex items-center gap-3 pt-0.5 text-[10px]">
                                                            <span>Ref: {f.reference}</span>
                                                            {f.source_url && (
                                                                <a
                                                                    href={f.source_url}
                                                                    target="_blank"
                                                                    rel="noreferrer"
                                                                    onClick={(e) => e.stopPropagation()}
                                                                    className="text-primary inline-flex items-center gap-0.5 hover:underline"
                                                                >
                                                                    Issuance <ExternalLink className="size-2.5" />
                                                                </a>
                                                            )}
                                                        </div>
                                                    </div>
                                                    {isSelected && <CheckCircle2 className="text-primary mt-0.5 size-4 shrink-0" />}
                                                </div>
                                            );
                                        })}
                                    </div>

                                    {/* Programs list if framework selected */}
                                    {activeFramework && currentProgramGroups.length > 0 && (
                                        <div className="space-y-3 pt-2">
                                            <div className="flex items-center justify-between">
                                                <span className="text-foreground text-xs font-medium">
                                                    Programs to Instantiate ({data.programs.length} selected)
                                                </span>
                                            </div>

                                            {/* Spectrum UI BeamSearch Component */}
                                            <BeamSearch
                                                value={programSearch}
                                                onChange={setProgramSearch}
                                                placeholder="Filter programs by code or title..."
                                            />

                                            <div className="max-h-64 space-y-4 overflow-y-auto pr-1">
                                                {currentProgramGroups.map((group) => {
                                                    const filtered = group.programs.filter((p) =>
                                                        `${p.code} ${p.title}`.toLowerCase().includes(programSearch.toLowerCase()),
                                                    );
                                                    if (filtered.length === 0) return null;

                                                    const allSelected = filtered.every((p) => data.programs.includes(p.code));

                                                    return (
                                                        <div key={group.key} className="space-y-1.5">
                                                            <div className="flex items-center justify-between">
                                                                <span className="text-muted-foreground text-[10px] font-semibold tracking-wider uppercase">
                                                                    {group.label}
                                                                </span>
                                                                <button
                                                                    type="button"
                                                                    onClick={() => toggleAllProgramsInGroup(filtered.map((p) => p.code))}
                                                                    className="text-primary text-[10px] hover:underline"
                                                                >
                                                                    {allSelected ? "Deselect all" : "Select all"}
                                                                </button>
                                                            </div>

                                                            <div className="divide-border/30 border-border/30 divide-y border-y">
                                                                {filtered.map((program) => {
                                                                    const isChecked = data.programs.includes(program.code);
                                                                    return (
                                                                        <label
                                                                            key={program.code}
                                                                            className="flex cursor-pointer items-center justify-between py-2 text-xs"
                                                                        >
                                                                            <div className="flex items-center gap-2.5">
                                                                                <Checkbox
                                                                                    checked={isChecked}
                                                                                    onCheckedChange={() => toggleProgramCode(program.code)}
                                                                                    className="size-3.5"
                                                                                />
                                                                                <span className="font-mono text-xs">{program.code}</span>
                                                                                <span className="text-muted-foreground truncate text-xs">
                                                                                    {program.title}
                                                                                </span>
                                                                            </div>
                                                                        </label>
                                                                    );
                                                                })}
                                                            </div>
                                                        </div>
                                                    );
                                                })}
                                            </div>

                                            {(data.curriculum_framework === "deped_shs_k12" || data.curriculum_framework === "deped_shs_revised") && (
                                                <div className="flex items-center justify-between pt-2">
                                                    <div className="space-y-0.5">
                                                        <Label htmlFor="seed_strand" className="text-xs font-medium">
                                                            Preload Core & Applied Subjects
                                                        </Label>
                                                        <p className="text-muted-foreground text-[10px]">Seeds standard core subjects</p>
                                                    </div>
                                                    <Switch
                                                        id="seed_strand"
                                                        checked={data.seed_strand_subjects}
                                                        onCheckedChange={(v) => setData("seed_strand_subjects", v)}
                                                    />
                                                </div>
                                            )}
                                        </div>
                                    )}
                                </div>
                            )}
                        </div>
                    </div>
                )}

                {/* ── STEP 4: Calendar & Currency ── */}
                {currentStep === 4 && (
                    <div className="space-y-6">
                        <div className="space-y-1">
                            <div className="flex items-center gap-2">
                                <Badge variant="primary-light" size="xs">
                                    Milestone 4
                                </Badge>
                                <span className="text-muted-foreground text-xs">Timeline & Currency</span>
                            </div>
                            <h2 className="text-foreground text-xl font-medium tracking-tight">Academic Schedule</h2>
                            <p className="text-muted-foreground text-xs leading-relaxed">
                                Operational term dates and financial transaction currency.
                            </p>
                        </div>

                        <div className="border-border/40 space-y-5 border-t pt-6">
                            {/* Quick presets */}
                            <div className="space-y-2">
                                <Label className="text-xs font-medium">Calendar Presets</Label>
                                <div className="flex flex-wrap gap-2">
                                    {regionalCalendarPresets.length > 0
                                        ? regionalCalendarPresets.map((preset) => (
                                              <button
                                                  key={preset.key}
                                                  type="button"
                                                  onClick={() => {
                                                      const s = new Date(preset.starts).getFullYear();
                                                      const e = new Date(preset.ends).getFullYear();
                                                      setData((p) => ({
                                                          ...p,
                                                          school_starting_date: preset.starts,
                                                          school_ending_date: preset.ends,
                                                          curriculum_year: `${s}-${e}`,
                                                      }));
                                                  }}
                                                  className="text-muted-foreground hover:text-foreground border-border/60 hover:border-border rounded-md border px-2.5 py-1 text-xs transition-colors"
                                              >
                                                  {preset.label}
                                              </button>
                                          ))
                                        : GLOBAL_CALENDAR_PRESETS.map((preset) => (
                                              <button
                                                  key={preset.label}
                                                  type="button"
                                                  onClick={() => {
                                                      setData((p) => ({
                                                          ...p,
                                                          school_starting_date: preset.starts,
                                                          school_ending_date: preset.ends,
                                                          curriculum_year: preset.year,
                                                      }));
                                                  }}
                                                  className="text-muted-foreground hover:text-foreground border-border/60 hover:border-border rounded-md border px-2.5 py-1 text-xs transition-colors"
                                              >
                                                  {preset.label}
                                              </button>
                                          ))}
                                </div>
                            </div>

                            {/* Dates */}
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="space-y-1.5">
                                    <Label htmlFor="start_date" className="text-xs font-medium">
                                        School Year Start <span className="text-destructive">*</span>
                                    </Label>
                                    <Input
                                        id="start_date"
                                        type="date"
                                        value={data.school_starting_date}
                                        onChange={(e) => {
                                            const val = e.target.value;
                                            setData("school_starting_date", val);
                                            clearErrors("school_starting_date");
                                            if (val && data.school_ending_date) {
                                                const s = new Date(val).getFullYear();
                                                const en = new Date(data.school_ending_date).getFullYear();
                                                setData("curriculum_year", `${s}-${en}`);
                                            }
                                        }}
                                        className="h-10 text-sm"
                                    />
                                    {errors.school_starting_date && <p className="text-destructive text-xs">{errors.school_starting_date}</p>}
                                </div>

                                <div className="space-y-1.5">
                                    <Label htmlFor="end_date" className="text-xs font-medium">
                                        School Year End <span className="text-destructive">*</span>
                                    </Label>
                                    <Input
                                        id="end_date"
                                        type="date"
                                        min={data.school_starting_date || undefined}
                                        value={data.school_ending_date}
                                        onChange={(e) => {
                                            const val = e.target.value;
                                            setData("school_ending_date", val);
                                            clearErrors("school_ending_date");
                                            if (data.school_starting_date && val) {
                                                const s = new Date(data.school_starting_date).getFullYear();
                                                const en = new Date(val).getFullYear();
                                                setData("curriculum_year", `${s}-${en}`);
                                            }
                                        }}
                                        className="h-10 text-sm"
                                    />
                                    {errors.school_ending_date && <p className="text-destructive text-xs">{errors.school_ending_date}</p>}
                                </div>

                                <div className="space-y-1.5">
                                    <Label htmlFor="semester" className="text-xs font-medium">
                                        Initial Term <span className="text-destructive">*</span>
                                    </Label>
                                    <Select value={data.semester} onValueChange={(val) => setData("semester", val ?? "1")}>
                                        <SelectTrigger id="semester" className="h-10 text-sm">
                                            <SelectValue placeholder="Select semester..." />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="1">1st Semester / Fall Term</SelectItem>
                                            <SelectItem value="2">2nd Semester / Spring Term</SelectItem>
                                            <SelectItem value="3">Summer / Midyear Session</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    {errors.semester && <p className="text-destructive text-xs">{errors.semester}</p>}
                                </div>

                                <div className="space-y-1.5">
                                    <div className="flex items-center justify-between">
                                        <Label htmlFor="currency" className="text-xs font-medium">
                                            Base Currency <span className="text-destructive">*</span>
                                        </Label>
                                        {data.country_code === "PH" && (
                                            <Badge variant="secondary" size="xs">
                                                PHP Default
                                            </Badge>
                                        )}
                                    </div>

                                    {data.country_code === "PH" ? (
                                        <Input id="currency" value="PHP" disabled className="bg-muted/30 h-10 font-mono text-sm" />
                                    ) : (
                                        <div className="space-y-2">
                                            <Input
                                                id="currency"
                                                value={data.currency}
                                                onChange={(e) => {
                                                    setData(
                                                        "currency",
                                                        e.target.value
                                                            .toUpperCase()
                                                            .replace(/[^A-Z]/g, "")
                                                            .slice(0, 3),
                                                    );
                                                    clearErrors("currency");
                                                }}
                                                placeholder="USD"
                                                maxLength={3}
                                                className="h-10 font-mono text-sm uppercase"
                                            />
                                            <div className="flex flex-wrap gap-1">
                                                {QUICK_CURRENCIES.map((code) => (
                                                    <button
                                                        key={code}
                                                        type="button"
                                                        onClick={() => {
                                                            setData("currency", code);
                                                            clearErrors("currency");
                                                        }}
                                                        className={`rounded px-1.5 py-0.5 font-mono text-[10px] transition-colors ${
                                                            data.currency === code
                                                                ? "bg-foreground text-background font-semibold"
                                                                : "text-muted-foreground hover:text-foreground border-border/50 border"
                                                        }`}
                                                    >
                                                        {code}
                                                    </button>
                                                ))}
                                            </div>
                                        </div>
                                    )}
                                    {errors.currency && <p className="text-destructive text-xs">{errors.currency}</p>}
                                </div>
                            </div>
                        </div>
                    </div>
                )}

                {/* ── STEP 5: Deployment & Customization ── */}
                {currentStep === 5 && (
                    <div className="space-y-6">
                        <div className="space-y-1">
                            <div className="flex items-center gap-2">
                                <Badge variant="primary-light" size="xs">
                                    Milestone 5
                                </Badge>
                                <span className="text-muted-foreground text-xs">Pre-Flight Review</span>
                            </div>
                            <h2 className="text-foreground text-xl font-medium tracking-tight">Review & Deploy</h2>
                            <p className="text-muted-foreground text-xs leading-relaxed">
                                Review your institution parameters and initialize {appName}.
                            </p>
                        </div>

                        <div className="border-border/40 space-y-6 border-t pt-6">
                            {/* Summary Blueprint */}
                            <div className="divide-border/40 border-border/40 divide-y border-y text-xs">
                                <div className="flex items-center justify-between py-2.5">
                                    <span className="text-muted-foreground">Superuser Account</span>
                                    <span className="text-foreground font-mono">{data.admin_email || "Not set"}</span>
                                </div>
                                <div className="flex items-center justify-between py-2.5">
                                    <span className="text-muted-foreground">Institution</span>
                                    <span className="text-foreground font-medium">
                                        {data.school_name} ({data.school_code})
                                    </span>
                                </div>
                                <div className="flex items-center justify-between py-2.5">
                                    <span className="text-muted-foreground">Country Jurisdiction</span>
                                    <span className="text-foreground">
                                        {selectedCountryName} [{data.country_code}]
                                    </span>
                                </div>
                                <div className="flex items-center justify-between py-2.5">
                                    <span className="text-muted-foreground">Academic Tier</span>
                                    <span className="text-foreground">{schoolLevelOptions.find((l) => l.value === data.school_level)?.label}</span>
                                </div>
                                <div className="flex items-center justify-between py-2.5">
                                    <span className="text-muted-foreground">Academic Cycle</span>
                                    <span className="text-foreground font-mono">
                                        {data.school_starting_date} to {data.school_ending_date}
                                    </span>
                                </div>
                                <div className="flex items-center justify-between py-2.5">
                                    <span className="text-muted-foreground">Base Currency</span>
                                    <span className="text-foreground font-mono font-semibold">{data.currency}</span>
                                </div>
                            </div>

                            {/* Optional Branding & Modules Collapsible */}
                            <Collapsible className="border-border/40 border-t pt-2">
                                <CollapsibleTrigger asChild>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        className="text-muted-foreground hover:text-foreground flex w-full items-center justify-between p-0 text-xs"
                                    >
                                        <span>Optional Branding & Module Switches</span>
                                        <ChevronDown className="size-3.5" />
                                    </Button>
                                </CollapsibleTrigger>
                                <CollapsibleContent className="space-y-4 pt-4 text-xs">
                                    <div className="grid gap-3 sm:grid-cols-2">
                                        <div className="space-y-1">
                                            <Label htmlFor="site_name" className="text-[11px]">
                                                Portal Title Override
                                            </Label>
                                            <Input
                                                id="site_name"
                                                value={data.site_name}
                                                onChange={(e) => setData("site_name", e.target.value)}
                                                placeholder={data.school_name || "Academic Portal"}
                                                className="h-9 text-xs"
                                            />
                                        </div>
                                        <div className="space-y-1">
                                            <Label htmlFor="site_desc" className="text-[11px]">
                                                Portal Tagline
                                            </Label>
                                            <Input
                                                id="site_desc"
                                                value={data.site_description}
                                                onChange={(e) => setData("site_description", e.target.value)}
                                                placeholder="Academic Information System"
                                                className="h-9 text-xs"
                                            />
                                        </div>
                                    </div>

                                    {/* Color Dots */}
                                    <div className="space-y-1.5">
                                        <Label className="text-[11px]">Theme Accent</Label>
                                        <div className="flex items-center gap-2">
                                            {THEME_ACCENTS.map((t) => (
                                                <button
                                                    key={t.value}
                                                    type="button"
                                                    onClick={() => setData("theme_color", t.value)}
                                                    className={`size-5 rounded-full transition-transform ${
                                                        data.theme_color === t.value ? "ring-primary scale-110 ring-2 ring-offset-2" : "opacity-80"
                                                    }`}
                                                    style={{ backgroundColor: t.value }}
                                                    title={t.name}
                                                />
                                            ))}
                                        </div>
                                    </div>

                                    {/* Logo upload */}
                                    <div className="space-y-1">
                                        <Label htmlFor="logo_upload" className="text-[11px]">
                                            Emblem / Logo
                                        </Label>
                                        <div className="border-border/60 hover:border-border relative flex cursor-pointer items-center justify-center rounded-md border border-dashed p-3 text-center transition-colors">
                                            <input
                                                id="logo_upload"
                                                type="file"
                                                accept="image/*"
                                                onChange={handleLogoFile}
                                                className="absolute inset-0 cursor-pointer opacity-0"
                                            />
                                            {logoPreview ? (
                                                <div className="flex items-center gap-2">
                                                    <img src={logoPreview} alt="Logo" className="size-6 object-contain" />
                                                    <span className="text-[11px]">Logo selected</span>
                                                </div>
                                            ) : (
                                                <div className="text-muted-foreground flex items-center gap-1.5 text-[11px]">
                                                    <Upload className="size-3.5" />
                                                    <span>Upload custom logo</span>
                                                </div>
                                            )}
                                        </div>
                                    </div>

                                    {/* Feature switches */}
                                    <div className="divide-border/30 border-border/30 divide-y border-y pt-1">
                                        <div className="flex items-center justify-between py-2">
                                            <span className="text-[11px]">Student Self-Service Portal</span>
                                            <Switch
                                                checked={data.school_portal_enabled}
                                                onCheckedChange={(v) => setData("school_portal_enabled", v)}
                                            />
                                        </div>
                                        <div className="flex items-center justify-between py-2">
                                            <span className="text-[11px]">Online Enrollment Module</span>
                                            <Switch
                                                checked={data.online_enrollment_enabled}
                                                onCheckedChange={(v) => setData("online_enrollment_enabled", v)}
                                            />
                                        </div>
                                        <div className="flex items-center justify-between py-2">
                                            <span className="text-[11px]">Clearance Verification</span>
                                            <Switch
                                                checked={data.enable_clearance_check}
                                                onCheckedChange={(v) => setData("enable_clearance_check", v)}
                                            />
                                        </div>
                                        <div className="flex items-center justify-between py-2">
                                            <span className="text-[11px]">QR Code Verification</span>
                                            <Switch checked={data.enable_qr_codes} onCheckedChange={(v) => setData("enable_qr_codes", v)} />
                                        </div>
                                    </div>
                                </CollapsibleContent>
                            </Collapsible>
                        </div>
                    </div>
                )}

                {/* ── Minimalist Step Controls ── */}
                <div className="border-border/40 mt-12 flex items-center justify-between border-t pt-6">
                    <Button
                        type="button"
                        variant="ghost"
                        onClick={handlePrevStep}
                        disabled={currentStep === 1 || processing}
                        className="text-muted-foreground hover:text-foreground h-9 gap-1.5 text-xs"
                    >
                        <ArrowLeft className="size-3.5" />
                        <span>Back</span>
                        <KbdKey size="xs">esc</KbdKey>
                    </Button>

                    <div>
                        {currentStep < 5 ? (
                            <Button type="button" onClick={handleNextStep} disabled={processing} className="h-9 gap-1.5 px-4 text-xs font-medium">
                                <span>Continue</span>
                                <KbdKey size="xs">↵</KbdKey>
                            </Button>
                        ) : (
                            <Button
                                type="button"
                                onClick={() => handleSubmit()}
                                disabled={processing}
                                className="bg-foreground text-background hover:bg-foreground/90 h-9 gap-1.5 px-5 text-xs font-medium"
                            >
                                {processing ? (
                                    <span className="border-background size-3.5 animate-spin rounded-full border-2 border-t-transparent" />
                                ) : (
                                    <Rocket className="size-3.5" />
                                )}
                                <span>Initialize Instance</span>
                            </Button>
                        )}
                    </div>
                </div>
            </main>
        </div>
    );
}
