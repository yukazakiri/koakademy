import { Badge } from "@/components/reui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Checkbox } from "@/components/ui/checkbox";
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from "@/components/ui/collapsible";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Progress } from "@/components/ui/progress";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Switch } from "@/components/ui/switch";
import { Head, useForm, usePage } from "@inertiajs/react";
import {
    ArrowLeft,
    ArrowRight,
    BookOpen,
    Building2,
    CalendarDays,
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
    Palette,
    Rocket,
    School,
    Search,
    ShieldCheck,
    Sparkles,
    Terminal,
    Upload,
    User,
    Workflow,
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

const QUICK_CURRENCIES = [
    { code: "USD", name: "US Dollar" },
    { code: "EUR", name: "Euro" },
    { code: "GBP", name: "British Pound" },
    { code: "CAD", name: "Canadian Dollar" },
    { code: "AUD", name: "Australian Dollar" },
    { code: "SGD", name: "Singapore Dollar" },
    { code: "JPY", name: "Japanese Yen" },
    { code: "INR", name: "Indian Rupee" },
];

const GLOBAL_CALENDAR_PRESETS = [
    {
        label: "Fall - Spring Academic Cycle",
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

    /* ── Guided Onboarding State ── */
    const [currentStep, setCurrentStep] = useState<number>(1);
    const [programSearch, setProgramSearch] = useState("");
    const [showPassword, setShowPassword] = useState(false);
    const [showConfirmPassword, setShowConfirmPassword] = useState(false);
    const [isCampusContactsOpen, setIsCampusContactsOpen] = useState(false);
    const [logoPreview, setLogoPreview] = useState<string | null>(null);

    const { data, setData, post, processing, errors, clearErrors } = useForm({
        // Step 1: Administrator
        admin_name: "",
        admin_email: "",
        admin_password: "",
        admin_password_confirmation: "",
        // Step 2: Institution
        school_name: "",
        school_code: "",
        country_code: "US", // Clean OSS default, customizable to any ISO code
        school_level: "higher_education",
        school_description: "",
        school_email: "",
        school_phone: "",
        school_location: "",
        dean_name: "",
        dean_email: "",
        // Step 3: Architecture & Framework
        curriculum_framework: "",
        programs: [] as string[],
        seed_strand_subjects: false,
        // Step 4: Academic Calendar & Currency
        school_starting_date: "2026-08-15",
        school_ending_date: "2027-05-30",
        semester: "1",
        curriculum_year: "2026-2027",
        currency: "USD",
        // Step 5: Branding & Modules
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

    const selectedLevelLabel = useMemo(() => {
        return schoolLevelOptions.find((l) => l.value === data.school_level)?.label ?? data.school_level;
    }, [schoolLevelOptions, data.school_level]);

    /* ── Step Validations ── */

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

    const isStep3Valid = true; // Inherently valid (Standard Global Architecture or selected framework)

    const isStep4Valid = useMemo(() => {
        const hasDates =
            Boolean(data.school_starting_date) && Boolean(data.school_ending_date) && data.school_ending_date > data.school_starting_date;
        const hasSemester = Boolean(data.semester);
        const hasCurrency = data.country_code === "PH" ? Boolean(data.currency) : /^[A-Z]{3}$/.test(data.currency.trim());
        return hasDates && hasSemester && hasCurrency;
    }, [data.school_starting_date, data.school_ending_date, data.semester, data.country_code, data.currency]);

    const isStep5Valid = isStep1Valid && isStep2Valid && isStep4Valid;

    const stepsStatus = useMemo(() => {
        return [
            { id: 1, title: "Administrator", label: "Root Superuser", completed: isStep1Valid, icon: ShieldCheck },
            { id: 2, title: "Institution", label: "Profile & Tier", completed: isStep2Valid, icon: Building2 },
            { id: 3, title: "Architecture", label: "Curriculum Model", completed: isStep3Valid, icon: Layers },
            { id: 4, title: "Schedule", label: "Calendar & Currency", completed: isStep4Valid, icon: CalendarDays },
            { id: 5, title: "Launchpad", label: "Branding & Review", completed: isStep5Valid, icon: Rocket },
        ];
    }, [isStep1Valid, isStep2Valid, isStep3Valid, isStep4Valid, isStep5Valid]);

    const completedStepsCount = [isStep1Valid, isStep2Valid, isStep3Valid, isStep4Valid].filter(Boolean).length;
    const progressPercentage = Math.round((completedStepsCount / 4) * 100);

    /* ── Handlers & Helpers ── */

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
            site_description: "Academic Information System & Digital Student Portal",
            theme_color: "#0f172a",
        }));
        toast.success("Applied recommended institutional defaults! You can customize any value.");
    };

    /* ── Step Navigation ── */

    const handleNextStep = () => {
        clearErrors();

        if (currentStep === 1) {
            if (!isStep1Valid) {
                toast.error("Please fill in administrator name, email, and matching passwords (min 8 chars).");
                return;
            }
            setCurrentStep(2);
            return;
        }

        if (currentStep === 2) {
            if (!isStep2Valid) {
                toast.error("Please configure the institution name, short code, and academic tier.");
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

    /* ── Form Submission ── */

    const handleSubmit = (e?: React.FormEvent) => {
        if (e) e.preventDefault();
        clearErrors();

        if (!isStep1Valid) {
            toast.error("Please provide valid administrator credentials.");
            setCurrentStep(1);
            return;
        }
        if (!isStep2Valid) {
            toast.error("Please configure institution details.");
            setCurrentStep(2);
            return;
        }
        if (!isStep4Valid) {
            toast.error("Please provide valid academic dates and a 3-letter currency code.");
            setCurrentStep(4);
            return;
        }

        post("/setup", {
            onSuccess: () => {
                toast.success(`${appName} initialized successfully!`);
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
        <div className="bg-muted/15 text-foreground selection:bg-primary/20 min-h-screen font-sans antialiased">
            <Head title={`Onboarding & Setup Guide — ${appName}`} />

            {/* Top Onboarding Header */}
            <header className="border-border/70 bg-background/90 sticky top-0 z-40 border-b backdrop-blur-md">
                <div className="mx-auto flex h-14 max-w-6xl items-center justify-between px-4 sm:px-6">
                    {/* Brand & Guide badge */}
                    <div className="flex items-center gap-3">
                        {logoUrl ? (
                            <img src={logoUrl} alt={appName} className="size-7 rounded object-contain" />
                        ) : (
                            <div className="bg-primary text-primary-foreground flex size-7 items-center justify-center rounded-lg shadow-xs">
                                <Terminal className="size-3.5" />
                            </div>
                        )}
                        <div className="flex items-center gap-2">
                            <span className="text-foreground text-sm font-semibold tracking-tight">{appName}</span>
                            <Badge variant="outline" size="xs" className="font-mono text-[10px]">
                                Setup Guide
                            </Badge>
                        </div>
                    </div>

                    {/* Progress pill & Quick-actions */}
                    <div className="flex items-center gap-2 sm:gap-3">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={handleFillDemoData}
                            className="text-muted-foreground hover:text-foreground hidden h-8 gap-1.5 px-2.5 text-xs sm:inline-flex"
                        >
                            <Sparkles className="size-3 text-amber-500" />
                            <span>Prefill Demo Data</span>
                        </Button>

                        <div className="bg-muted/50 hidden h-4 w-px sm:block" />

                        <div className="flex items-center gap-2 font-mono text-xs">
                            <span className="text-muted-foreground hidden sm:inline">Milestone</span>
                            <Badge variant="primary-light" size="xs">
                                {currentStep} of 5
                            </Badge>
                        </div>
                    </div>
                </div>

                {/* Top Subtle Progress Bar */}
                <div className="bg-muted h-0.5 w-full overflow-hidden">
                    <div
                        className="bg-primary h-full transition-all duration-300 ease-out"
                        style={{ width: `${Math.max((currentStep / 5) * 100, 15)}%` }}
                    />
                </div>
            </header>

            {/* Main Guided Canvas */}
            <main className="mx-auto max-w-6xl px-4 py-8 sm:px-6">
                <div className="grid grid-cols-1 gap-8 lg:grid-cols-12 lg:items-start">
                    {/* ── LEFT: Onboarding Guide Rail & Live Companion (4 cols) ── */}
                    <aside className="space-y-6 lg:sticky lg:top-20 lg:col-span-4">
                        {/* Onboarding Milestones Navigation */}
                        <Card className="border-border/80 bg-background overflow-hidden rounded-2xl shadow-xs">
                            <CardHeader className="border-border/60 border-b p-4">
                                <div className="flex items-center justify-between">
                                    <div className="space-y-0.5">
                                        <CardTitle className="text-xs font-bold tracking-wider uppercase">Onboarding Track</CardTitle>
                                        <CardDescription className="text-[11px]">{completedStepsCount} of 4 core milestones ready</CardDescription>
                                    </div>
                                    <span className="text-foreground font-mono text-xs font-semibold">{progressPercentage}%</span>
                                </div>
                                <Progress value={progressPercentage} className="mt-2 h-1.5" />
                            </CardHeader>

                            <CardContent className="p-2">
                                <nav className="space-y-1">
                                    {stepsStatus.map((step) => {
                                        const StepIcon = step.icon;
                                        const isActive = currentStep === step.id;
                                        const isCompleted = step.completed;

                                        return (
                                            <button
                                                key={step.id}
                                                type="button"
                                                onClick={() => setCurrentStep(step.id)}
                                                className={`flex w-full items-center justify-between rounded-xl px-3 py-2.5 text-left transition-all ${
                                                    isActive
                                                        ? "bg-primary/10 text-primary font-semibold shadow-xs"
                                                        : "text-muted-foreground hover:text-foreground hover:bg-muted/50"
                                                }`}
                                            >
                                                <div className="flex items-center gap-2.5">
                                                    <div
                                                        className={`flex size-7 shrink-0 items-center justify-center rounded-lg ${
                                                            isActive
                                                                ? "bg-primary text-primary-foreground"
                                                                : isCompleted
                                                                  ? "bg-emerald-500/10 text-emerald-600 dark:text-emerald-400"
                                                                  : "bg-muted text-muted-foreground"
                                                        }`}
                                                    >
                                                        {isCompleted && !isActive ? (
                                                            <CheckCircle2 className="size-4" />
                                                        ) : (
                                                            <StepIcon className="size-3.5" />
                                                        )}
                                                    </div>
                                                    <div className="min-w-0">
                                                        <span className="block truncate text-xs font-medium">
                                                            {step.id}. {step.title}
                                                        </span>
                                                        <span className="text-muted-foreground block truncate text-[10px] font-normal">
                                                            {step.label}
                                                        </span>
                                                    </div>
                                                </div>

                                                {isCompleted ? (
                                                    <Badge variant="success-light" size="xs">
                                                        ✓ Done
                                                    </Badge>
                                                ) : isActive ? (
                                                    <Badge variant="primary-light" size="xs">
                                                        Current
                                                    </Badge>
                                                ) : (
                                                    <Badge variant="outline" size="xs" className="opacity-60">
                                                        Pending
                                                    </Badge>
                                                )}
                                            </button>
                                        );
                                    })}
                                </nav>
                            </CardContent>
                        </Card>

                        {/* Live Campus Identity Card */}
                        <Card className="border-border/80 bg-background overflow-hidden rounded-2xl shadow-xs">
                            <CardHeader className="border-border/60 border-b p-4 pb-3">
                                <div className="flex items-center justify-between">
                                    <span className="text-muted-foreground flex items-center gap-1.5 text-[11px] font-semibold tracking-wider uppercase">
                                        <Building2 className="size-3.5" />
                                        Campus Preview
                                    </span>
                                    <Badge variant="outline" size="xs" className="font-mono text-[9px]">
                                        Dynamic Seal
                                    </Badge>
                                </div>
                            </CardHeader>

                            <CardContent className="space-y-3.5 p-4">
                                {/* Digital institution badge */}
                                <div className="bg-muted/30 border-border/80 overflow-hidden rounded-xl border">
                                    {/* Accent Banner */}
                                    <div
                                        className="h-9 w-full transition-colors duration-300"
                                        style={{ backgroundColor: data.theme_color || "#0f172a" }}
                                    />

                                    <div className="p-3">
                                        <div className="-mt-7 mb-2 flex items-center justify-between">
                                            <div
                                                className="ring-background flex size-11 items-center justify-center rounded-xl font-mono text-sm font-bold text-white shadow-md ring-2 transition-colors"
                                                style={{ backgroundColor: data.theme_color || "#0f172a" }}
                                            >
                                                {logoPreview ? (
                                                    <img src={logoPreview} alt="Logo" className="size-full rounded-xl object-contain p-1" />
                                                ) : data.school_code ? (
                                                    data.school_code.slice(0, 3)
                                                ) : (
                                                    <School className="size-5 text-white/90" />
                                                )}
                                            </div>
                                            <Badge variant="outline" size="xs" className="font-mono">
                                                {data.school_code || "CODE"}
                                            </Badge>
                                        </div>

                                        <h4 className="text-foreground truncate text-sm font-bold">
                                            {data.school_name || "Institution Name Pending"}
                                        </h4>
                                        <p className="text-muted-foreground truncate text-[11px]">{selectedCountryName}</p>

                                        <div className="border-border/60 mt-3 grid grid-cols-2 gap-1.5 border-t pt-2 text-[10px]">
                                            <div>
                                                <span className="text-muted-foreground block">Academic Tier</span>
                                                <span className="text-foreground truncate font-medium">{selectedLevelLabel}</span>
                                            </div>
                                            <div>
                                                <span className="text-muted-foreground block">Academic Year</span>
                                                <span className="text-foreground font-mono font-medium">
                                                    {data.curriculum_year ? `AY ${data.curriculum_year}` : "Pending"}
                                                </span>
                                            </div>
                                            <div>
                                                <span className="text-muted-foreground block">Operating Currency</span>
                                                <span className="text-foreground font-mono font-semibold">{data.currency || "USD"}</span>
                                            </div>
                                            <div>
                                                <span className="text-muted-foreground block">Superuser</span>
                                                <span className="text-foreground truncate font-mono">
                                                    {data.admin_email ? data.admin_email.split("@")[0] : "admin"}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div className="text-muted-foreground flex items-center justify-between font-mono text-[10px]">
                                    <span className="flex items-center gap-1">
                                        <span className="inline-block size-1.5 rounded-full bg-emerald-500" />
                                        DB: Connected
                                    </span>
                                    <span>PHP 8.5 · Octane</span>
                                </div>
                            </CardContent>
                        </Card>
                    </aside>

                    {/* ── RIGHT: Active Guided Milestone Canvas (8 cols) ── */}
                    <section className="space-y-6 lg:col-span-8">
                        {/* ── MILESTONE 1: Administrator Account ── */}
                        {currentStep === 1 && (
                            <Card className="border-border/80 bg-background overflow-hidden rounded-2xl shadow-xs">
                                <CardHeader className="border-border/60 border-b pb-4">
                                    <div className="flex items-center justify-between">
                                        <div className="flex items-center gap-2.5">
                                            <div className="bg-primary/10 text-primary flex size-8 items-center justify-center rounded-xl">
                                                <ShieldCheck className="size-4" />
                                            </div>
                                            <div>
                                                <div className="flex items-center gap-2">
                                                    <CardTitle className="text-base font-bold">1. Root Administrator Account</CardTitle>
                                                    <Badge variant="primary-light" size="xs">
                                                        Step 1 of 5
                                                    </Badge>
                                                </div>
                                                <CardDescription className="text-xs">
                                                    Who will have root governance and system ownership of this academic instance?
                                                </CardDescription>
                                            </div>
                                        </div>
                                    </div>
                                </CardHeader>

                                <CardContent className="space-y-4 p-6">
                                    <div className="bg-muted/30 border-border/80 flex items-start gap-3 rounded-xl border p-3.5 text-xs">
                                        <Info className="text-primary mt-0.5 size-4 shrink-0" />
                                        <p className="text-muted-foreground leading-relaxed">
                                            This initial master account has unrestricted permissions. You will use it to access the central
                                            administration console, configure academic terms, and invite registrars, department heads, and teachers.
                                        </p>
                                    </div>

                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <div className="space-y-1.5 sm:col-span-2">
                                            <Label htmlFor="admin_name" className="text-xs font-semibold">
                                                Administrator Full Name <span className="text-destructive">*</span>
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
                                                    placeholder="e.g. Dr. Alex Morgan"
                                                    className="h-10 pl-9 text-sm"
                                                />
                                            </div>
                                            {errors.admin_name && <p className="text-destructive text-xs">{errors.admin_name}</p>}
                                        </div>

                                        <div className="space-y-1.5 sm:col-span-2">
                                            <Label htmlFor="admin_email" className="text-xs font-semibold">
                                                Root Admin Email Address <span className="text-destructive">*</span>
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

                                        <div className="space-y-1.5">
                                            <Label htmlFor="admin_password" className="text-xs font-semibold">
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
                                            <Label htmlFor="admin_password_confirmation" className="text-xs font-semibold">
                                                Confirm Master Password <span className="text-destructive">*</span>
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
                                </CardContent>
                            </Card>
                        )}

                        {/* ── MILESTONE 2: Institution Profile & Jurisdiction ── */}
                        {currentStep === 2 && (
                            <Card className="border-border/80 bg-background overflow-hidden rounded-2xl shadow-xs">
                                <CardHeader className="border-border/60 border-b pb-4">
                                    <div className="flex items-center justify-between">
                                        <div className="flex items-center gap-2.5">
                                            <div className="bg-primary/10 text-primary flex size-8 items-center justify-center rounded-xl">
                                                <Building2 className="size-4" />
                                            </div>
                                            <div>
                                                <div className="flex items-center gap-2">
                                                    <CardTitle className="text-base font-bold">2. Institution & Regional Jurisdiction</CardTitle>
                                                    <Badge variant="primary-light" size="xs">
                                                        Step 2 of 5
                                                    </Badge>
                                                </div>
                                                <CardDescription className="text-xs">
                                                    Tell us about your campus identity, operating tier, and host country.
                                                </CardDescription>
                                            </div>
                                        </div>
                                    </div>
                                </CardHeader>

                                <CardContent className="space-y-5 p-6">
                                    {/* Country jurisdiction */}
                                    <div className="bg-muted/30 border-border/80 space-y-2 rounded-xl border p-4">
                                        <div className="flex items-center justify-between">
                                            <Label htmlFor="country_code" className="text-foreground flex items-center gap-1.5 text-xs font-semibold">
                                                <Globe className="text-primary size-4" />
                                                Host Country / Jurisdiction <span className="text-destructive">*</span>
                                            </Label>
                                            <Badge variant="outline" size="xs" className="font-mono">
                                                {data.country_code}
                                            </Badge>
                                        </div>

                                        <select
                                            id="country_code"
                                            value={data.country_code}
                                            onChange={(e) => handleCountryChange(e.target.value)}
                                            className="border-border bg-background text-foreground focus-visible:ring-primary h-10 w-full rounded-lg border px-3 text-sm focus-visible:ring-2 focus-visible:outline-none"
                                        >
                                            <option value="">Select country...</option>
                                            {countries.map((c) => (
                                                <option key={c.code} value={c.code}>
                                                    {c.name} ({c.code})
                                                </option>
                                            ))}
                                        </select>
                                        <p className="text-muted-foreground text-[11px]">
                                            Determines available regional regulatory catalogs, default localization, and currency rules.
                                        </p>
                                    </div>

                                    {/* Name and Acronym */}
                                    <div className="grid gap-4 sm:grid-cols-3">
                                        <div className="space-y-1.5 sm:col-span-2">
                                            <Label htmlFor="school_name" className="text-xs font-semibold">
                                                Institution / Organization Name <span className="text-destructive">*</span>
                                            </Label>
                                            <Input
                                                id="school_name"
                                                value={data.school_name}
                                                onChange={(e) => {
                                                    setData("school_name", e.target.value);
                                                    clearErrors("school_name");
                                                }}
                                                placeholder="e.g. Pacific Institute of Science & Technology"
                                                className="h-10 text-sm"
                                            />
                                            {errors.school_name && <p className="text-destructive text-xs">{errors.school_name}</p>}
                                        </div>

                                        <div className="space-y-1.5">
                                            <Label htmlFor="school_code" className="text-xs font-semibold">
                                                Institution Code <span className="text-destructive">*</span>
                                            </Label>
                                            <Input
                                                id="school_code"
                                                value={data.school_code}
                                                onChange={(e) => {
                                                    setData("school_code", e.target.value.toUpperCase().slice(0, 20));
                                                    clearErrors("school_code");
                                                }}
                                                placeholder="e.g. PIST"
                                                className="h-10 font-mono text-sm uppercase"
                                            />
                                            {errors.school_code && <p className="text-destructive text-xs">{errors.school_code}</p>}
                                        </div>
                                    </div>

                                    {/* Institutional Tier / Level Cards */}
                                    <div className="space-y-2">
                                        <Label className="text-xs font-semibold">
                                            Institutional Tier / Level <span className="text-destructive">*</span>
                                        </Label>
                                        <div className="grid grid-cols-1 gap-2.5 sm:grid-cols-2 lg:grid-cols-3">
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
                                                        className={`flex flex-col items-start gap-1.5 rounded-xl border p-3 text-left transition-all active:scale-[0.98] ${
                                                            selected
                                                                ? "border-primary bg-primary/5 ring-primary/30 ring-1"
                                                                : "border-border/80 hover:border-border hover:bg-muted/40"
                                                        }`}
                                                    >
                                                        <div className="flex w-full items-center justify-between">
                                                            <div className="flex items-center gap-2">
                                                                <div
                                                                    className={`flex size-6 items-center justify-center rounded-md ${
                                                                        selected
                                                                            ? "bg-primary text-primary-foreground"
                                                                            : "bg-muted text-muted-foreground"
                                                                    }`}
                                                                >
                                                                    <IconComponent className="size-3.5" />
                                                                </div>
                                                                <span className="text-foreground text-xs font-semibold">{opt.label}</span>
                                                            </div>
                                                            {selected && <CheckCircle2 className="text-primary size-4 shrink-0" />}
                                                        </div>
                                                        <p className="text-muted-foreground line-clamp-2 text-[10px] leading-relaxed">
                                                            {opt.description}
                                                        </p>
                                                    </button>
                                                );
                                            })}
                                        </div>
                                    </div>

                                    {/* Campus Contact Details Collapsible */}
                                    <Collapsible
                                        open={isCampusContactsOpen}
                                        onOpenChange={setIsCampusContactsOpen}
                                        className="border-border/80 rounded-xl border"
                                    >
                                        <CollapsibleTrigger asChild>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                className="text-muted-foreground hover:text-foreground flex w-full items-center justify-between p-3.5 text-xs"
                                            >
                                                <span className="flex items-center gap-2 font-medium">
                                                    <Info className="size-4" />
                                                    Add Campus Address, Contact & Dean (Optional)
                                                </span>
                                                <ChevronDown
                                                    className={`size-4 transition-transform duration-200 ${isCampusContactsOpen ? "rotate-180" : ""}`}
                                                />
                                            </Button>
                                        </CollapsibleTrigger>
                                        <CollapsibleContent className="space-y-3.5 border-t p-4 text-xs">
                                            <div className="grid gap-3 sm:grid-cols-2">
                                                <div className="space-y-1">
                                                    <Label htmlFor="school_email" className="text-[11px]">
                                                        Campus Contact Email
                                                    </Label>
                                                    <Input
                                                        id="school_email"
                                                        type="email"
                                                        value={data.school_email}
                                                        onChange={(e) => setData("school_email", e.target.value)}
                                                        placeholder="contact@institution.edu"
                                                        className="h-8.5 text-xs"
                                                    />
                                                </div>
                                                <div className="space-y-1">
                                                    <Label htmlFor="school_phone" className="text-[11px]">
                                                        Campus Phone Number
                                                    </Label>
                                                    <Input
                                                        id="school_phone"
                                                        value={data.school_phone}
                                                        onChange={(e) => setData("school_phone", e.target.value)}
                                                        placeholder="+1 555 123 4567"
                                                        className="h-8.5 text-xs"
                                                    />
                                                </div>
                                                <div className="space-y-1 sm:col-span-2">
                                                    <Label htmlFor="school_location" className="text-[11px]">
                                                        Campus Physical Address
                                                    </Label>
                                                    <Input
                                                        id="school_location"
                                                        value={data.school_location}
                                                        onChange={(e) => setData("school_location", e.target.value)}
                                                        placeholder="Main Campus Grounds, City"
                                                        className="h-8.5 text-xs"
                                                    />
                                                </div>
                                                <div className="space-y-1">
                                                    <Label htmlFor="dean_name" className="text-[11px]">
                                                        Dean or Head of School
                                                    </Label>
                                                    <Input
                                                        id="dean_name"
                                                        value={data.dean_name}
                                                        onChange={(e) => setData("dean_name", e.target.value)}
                                                        placeholder="Dr. Morgan Blake"
                                                        className="h-8.5 text-xs"
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
                                                        className="h-8.5 text-xs"
                                                    />
                                                </div>
                                            </div>
                                        </CollapsibleContent>
                                    </Collapsible>
                                </CardContent>
                            </Card>
                        )}

                        {/* ── MILESTONE 3: Academic Architecture & Catalog ── */}
                        {currentStep === 3 && (
                            <Card className="border-border/80 bg-background overflow-hidden rounded-2xl shadow-xs">
                                <CardHeader className="border-border/60 border-b pb-4">
                                    <div className="flex items-center justify-between">
                                        <div className="flex items-center gap-2.5">
                                            <div className="bg-primary/10 text-primary flex size-8 items-center justify-center rounded-xl">
                                                <Workflow className="size-4" />
                                            </div>
                                            <div>
                                                <div className="flex items-center gap-2">
                                                    <CardTitle className="text-base font-bold">3. Academic Architecture & Catalog</CardTitle>
                                                    <Badge variant="primary-light" size="xs">
                                                        Step 3 of 5
                                                    </Badge>
                                                </div>
                                                <CardDescription className="text-xs">
                                                    How are programs, departments, and curricula organized?
                                                </CardDescription>
                                            </div>
                                        </div>
                                        <Badge variant="outline" size="xs">
                                            {selectedCountryName}
                                        </Badge>
                                    </div>
                                </CardHeader>

                                <CardContent className="space-y-5 p-6">
                                    {/* Standard Global Academic Architecture (Default) */}
                                    <div className="border-border/80 bg-muted/20 space-y-3 rounded-xl border p-4 text-xs">
                                        <div className="flex items-center justify-between">
                                            <div className="flex items-center gap-2">
                                                <div className="bg-primary/10 text-primary flex size-6 items-center justify-center rounded-md">
                                                    <Layers className="size-3.5" />
                                                </div>
                                                <span className="text-foreground text-xs font-semibold">Standard Global Academic Architecture</span>
                                            </div>
                                            <Badge variant="secondary" size="xs">
                                                Default Architecture
                                            </Badge>
                                        </div>
                                        <p className="text-muted-foreground text-[11px] leading-relaxed">
                                            Operates under a universal four-tier hierarchy: <strong>Faculties & Departments</strong> →{" "}
                                            <strong>Degree / Diploma Programs</strong> → <strong>Courses & Subjects</strong> →{" "}
                                            <strong>Class Sections</strong>. Compatible with universities, academies, and schools worldwide.
                                        </p>

                                        {/* Visual Architecture Hierarchy Pills */}
                                        <div className="flex flex-wrap items-center gap-1.5 pt-1 font-mono text-[10px]">
                                            <span className="bg-background border-border/80 rounded-md border px-2 py-0.5 font-medium">
                                                Departments
                                            </span>
                                            <span className="text-muted-foreground">→</span>
                                            <span className="bg-background border-border/80 rounded-md border px-2 py-0.5 font-medium">Programs</span>
                                            <span className="text-muted-foreground">→</span>
                                            <span className="bg-background border-border/80 rounded-md border px-2 py-0.5 font-medium">Courses</span>
                                            <span className="text-muted-foreground">→</span>
                                            <span className="bg-background border-border/80 rounded-md border px-2 py-0.5 font-medium">Classes</span>
                                        </div>
                                    </div>

                                    {/* Regional Regulatory Frameworks (Country-Specific) */}
                                    {availableFrameworks.length === 0 ? (
                                        <div className="bg-background border-border/60 flex items-start gap-3 rounded-xl border p-4 text-xs">
                                            <Info className="text-primary mt-0.5 size-4 shrink-0" />
                                            <div className="space-y-1">
                                                <p className="text-foreground text-xs font-medium">
                                                    Standard Global Academic Architecture is active for {selectedCountryName}.
                                                </p>
                                                <p className="text-muted-foreground text-[11px] leading-relaxed">
                                                    No regional regulatory framework packs are required or installed for this country. You can define
                                                    custom departments and programs freely after launch, or contribute country-specific packs via{" "}
                                                    <code className="text-foreground font-mono">config/setup_catalog.php</code>.
                                                </p>
                                            </div>
                                        </div>
                                    ) : (
                                        /* Region has registered catalog provider (e.g. Philippines) */
                                        <div className="space-y-4 pt-1">
                                            <div className="flex items-center justify-between">
                                                <div className="space-y-0.5">
                                                    <Label className="text-foreground text-xs font-semibold">
                                                        Optional Regional Template: {selectedCountryName}
                                                    </Label>
                                                    <p className="text-muted-foreground text-[11px]">
                                                        Select an official framework to preload governed programs, or leave unselected for standard
                                                        cataloging.
                                                    </p>
                                                </div>
                                                {activeFramework && (
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() => {
                                                            setData((p) => ({ ...p, curriculum_framework: "", programs: [] }));
                                                        }}
                                                        className="text-muted-foreground hover:text-foreground h-6 text-[10px]"
                                                    >
                                                        Clear Template
                                                    </Button>
                                                )}
                                            </div>

                                            <div className="grid grid-cols-1 gap-2.5 sm:grid-cols-2">
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
                                                            className={`flex cursor-pointer flex-col justify-between gap-2 rounded-xl border p-3 text-left transition-all active:scale-[0.98] ${
                                                                isSelected
                                                                    ? "border-primary bg-primary/5 ring-primary/30 ring-1"
                                                                    : "border-border/80 hover:border-border hover:bg-muted/40"
                                                            }`}
                                                        >
                                                            <div className="flex items-start justify-between gap-1.5">
                                                                <div className="space-y-0.5">
                                                                    <div className="flex items-center gap-1.5">
                                                                        <span className="text-foreground text-xs font-semibold">{f.label}</span>
                                                                        <Badge variant="outline" size="xs">
                                                                            {f.authority}
                                                                        </Badge>
                                                                    </div>
                                                                    <p className="text-muted-foreground line-clamp-2 text-[10px]">{f.description}</p>
                                                                </div>
                                                                {isSelected && <CheckCircle2 className="text-primary mt-0.5 size-3.5 shrink-0" />}
                                                            </div>

                                                            <div className="text-muted-foreground flex items-center justify-between pt-1 text-[10px]">
                                                                <span className="max-w-[180px] truncate">Ref: {f.reference}</span>
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
                                                    );
                                                })}
                                            </div>

                                            {/* Preload programs if selected */}
                                            {activeFramework && currentProgramGroups.length > 0 && (
                                                <div className="border-border/80 space-y-3 rounded-xl border p-4">
                                                    <div className="flex flex-col gap-1 border-b pb-2 sm:flex-row sm:items-center sm:justify-between">
                                                        <div>
                                                            <h4 className="text-foreground text-xs font-semibold">Select Programs to Instantiate</h4>
                                                            <p className="text-muted-foreground text-[10px]">
                                                                Populates departments and curriculum catalog records upon installation.
                                                            </p>
                                                        </div>
                                                        <Badge variant="primary-light" size="xs">
                                                            {data.programs.length} programs selected
                                                        </Badge>
                                                    </div>

                                                    <div className="relative">
                                                        <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-3.5 -translate-y-1/2" />
                                                        <Input
                                                            value={programSearch}
                                                            onChange={(e) => setProgramSearch(e.target.value)}
                                                            placeholder="Filter program catalog by code or title..."
                                                            className="h-8.5 pl-8 text-xs"
                                                        />
                                                    </div>

                                                    <div className="max-h-60 space-y-3 overflow-y-auto pr-1">
                                                        {currentProgramGroups.map((group) => {
                                                            const filtered = group.programs.filter((p) =>
                                                                `${p.code} ${p.title}`.toLowerCase().includes(programSearch.toLowerCase()),
                                                            );
                                                            if (filtered.length === 0) return null;

                                                            const allSelected = filtered.every((p) => data.programs.includes(p.code));

                                                            return (
                                                                <div key={group.key} className="space-y-1.5">
                                                                    <div className="flex items-center justify-between px-1">
                                                                        <span className="text-muted-foreground text-[10px] font-semibold tracking-wider uppercase">
                                                                            {group.label}
                                                                        </span>
                                                                        <button
                                                                            type="button"
                                                                            onClick={() => toggleAllProgramsInGroup(filtered.map((p) => p.code))}
                                                                            className="text-primary text-[10px] font-medium hover:underline"
                                                                        >
                                                                            {allSelected ? "Deselect all" : "Select all"}
                                                                        </button>
                                                                    </div>

                                                                    <div className="grid grid-cols-1 gap-1.5 sm:grid-cols-2">
                                                                        {filtered.map((program) => {
                                                                            const isChecked = data.programs.includes(program.code);
                                                                            return (
                                                                                <label
                                                                                    key={program.code}
                                                                                    className={`flex cursor-pointer items-start gap-2 rounded-lg border p-2 text-xs transition-colors ${
                                                                                        isChecked
                                                                                            ? "border-primary/50 bg-primary/5"
                                                                                            : "border-border/60 hover:border-border hover:bg-muted/40"
                                                                                    }`}
                                                                                >
                                                                                    <Checkbox
                                                                                        checked={isChecked}
                                                                                        onCheckedChange={() => toggleProgramCode(program.code)}
                                                                                        className="mt-0.5 size-3.5"
                                                                                    />
                                                                                    <div className="min-w-0 flex-1">
                                                                                        <div className="flex items-center gap-1 truncate font-medium">
                                                                                            <span className="text-foreground">{program.code}</span>
                                                                                            <span className="text-muted-foreground truncate">
                                                                                                {program.title}
                                                                                            </span>
                                                                                        </div>
                                                                                        {program.meta && (
                                                                                            <p className="text-muted-foreground truncate text-[10px]">
                                                                                                {program.meta}
                                                                                            </p>
                                                                                        )}
                                                                                    </div>
                                                                                </label>
                                                                            );
                                                                        })}
                                                                    </div>
                                                                </div>
                                                            );
                                                        })}
                                                    </div>

                                                    {(data.curriculum_framework === "deped_shs_k12" ||
                                                        data.curriculum_framework === "deped_shs_revised") && (
                                                        <div className="bg-muted/30 border-border/80 flex items-center justify-between rounded-lg border p-2.5">
                                                            <div className="space-y-0.5">
                                                                <Label htmlFor="seed_strand" className="cursor-pointer text-xs font-medium">
                                                                    Preload Core & Applied Subjects
                                                                </Label>
                                                                <p className="text-muted-foreground text-[10px]">
                                                                    Seeds the 15 standard DepEd core subjects to selected strands.
                                                                </p>
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
                                </CardContent>
                            </Card>
                        )}

                        {/* ── MILESTONE 4: Academic Schedule & Currency ── */}
                        {currentStep === 4 && (
                            <Card className="border-border/80 bg-background overflow-hidden rounded-2xl shadow-xs">
                                <CardHeader className="border-border/60 border-b pb-4">
                                    <div className="flex items-center justify-between">
                                        <div className="flex items-center gap-2.5">
                                            <div className="bg-primary/10 text-primary flex size-8 items-center justify-center rounded-xl">
                                                <CalendarDays className="size-4" />
                                            </div>
                                            <div>
                                                <div className="flex items-center gap-2">
                                                    <CardTitle className="text-base font-bold">4. Academic Schedule & Currency</CardTitle>
                                                    <Badge variant="primary-light" size="xs">
                                                        Step 4 of 5
                                                    </Badge>
                                                </div>
                                                <CardDescription className="text-xs">
                                                    Set up operating term dates and standard transaction currency.
                                                </CardDescription>
                                            </div>
                                        </div>
                                    </div>
                                </CardHeader>

                                <CardContent className="space-y-5 p-6">
                                    {/* Presets (Regional or Global) */}
                                    <div className="space-y-2">
                                        <Label className="text-xs font-semibold">Quick Calendar Presets</Label>
                                        <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
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
                                                          className="border-border/80 bg-muted/20 hover:border-primary/50 hover:bg-muted/40 flex flex-col items-start gap-1 rounded-xl border p-2.5 text-left transition-all active:scale-[0.98]"
                                                      >
                                                          <span className="text-foreground text-xs font-semibold">{preset.label}</span>
                                                          <span className="text-muted-foreground text-[10px]">{preset.note}</span>
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
                                                          className="border-border/80 bg-muted/20 hover:border-primary/50 hover:bg-muted/40 flex flex-col items-start gap-1 rounded-xl border p-2.5 text-left transition-all active:scale-[0.98]"
                                                      >
                                                          <span className="text-foreground text-xs font-semibold">{preset.label}</span>
                                                          <span className="text-muted-foreground text-[10px]">{preset.note}</span>
                                                      </button>
                                                  ))}
                                        </div>
                                    </div>

                                    {/* Start & End Dates */}
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <div className="space-y-1.5">
                                            <Label htmlFor="start_date" className="text-xs font-semibold">
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
                                            <Label htmlFor="end_date" className="text-xs font-semibold">
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
                                            <Label htmlFor="semester" className="text-xs font-semibold">
                                                Initial Academic Term <span className="text-destructive">*</span>
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
                                                <Label htmlFor="currency" className="text-xs font-semibold">
                                                    Base Currency (ISO 4217) <span className="text-destructive">*</span>
                                                </Label>
                                                {data.country_code === "PH" && (
                                                    <Badge variant="secondary" size="xs">
                                                        PHP Default
                                                    </Badge>
                                                )}
                                            </div>

                                            {data.country_code === "PH" ? (
                                                <Input id="currency" value="PHP" disabled className="bg-muted/40 h-10 font-mono text-sm" />
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
                                                        placeholder="e.g. USD, EUR, GBP"
                                                        maxLength={3}
                                                        className="h-10 font-mono text-sm uppercase"
                                                    />
                                                    <div className="flex flex-wrap gap-1.5">
                                                        {QUICK_CURRENCIES.map((c) => (
                                                            <button
                                                                key={c.code}
                                                                type="button"
                                                                onClick={() => {
                                                                    setData("currency", c.code);
                                                                    clearErrors("currency");
                                                                }}
                                                                className={`rounded-lg border px-2 py-1 font-mono text-xs transition-colors active:scale-[0.98] ${
                                                                    data.currency === c.code
                                                                        ? "bg-primary text-primary-foreground border-primary"
                                                                        : "bg-muted/30 border-border/80 hover:bg-muted text-muted-foreground hover:text-foreground"
                                                                }`}
                                                            >
                                                                {c.code}
                                                            </button>
                                                        ))}
                                                    </div>
                                                </div>
                                            )}
                                            {errors.currency && <p className="text-destructive text-xs">{errors.currency}</p>}
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        )}

                        {/* ── MILESTONE 5: Branding, Modules & Launchpad ── */}
                        {currentStep === 5 && (
                            <div className="space-y-6">
                                {/* Branding and Modules Card */}
                                <Card className="border-border/80 bg-background overflow-hidden rounded-2xl shadow-xs">
                                    <CardHeader className="border-border/60 border-b pb-4">
                                        <div className="flex items-center justify-between">
                                            <div className="flex items-center gap-2.5">
                                                <div className="bg-primary/10 text-primary flex size-8 items-center justify-center rounded-xl">
                                                    <Palette className="size-4" />
                                                </div>
                                                <div>
                                                    <div className="flex items-center gap-2">
                                                        <CardTitle className="text-base font-bold">5. Platform Branding & Feature Modules</CardTitle>
                                                        <Badge variant="primary-light" size="xs">
                                                            Step 5 of 5
                                                        </Badge>
                                                    </div>
                                                    <CardDescription className="text-xs">
                                                        Personalize portal visual identity and enable operational modules.
                                                    </CardDescription>
                                                </div>
                                            </div>
                                        </div>
                                    </CardHeader>

                                    <CardContent className="space-y-5 p-6">
                                        {/* Site Title and Description */}
                                        <div className="grid gap-4 sm:grid-cols-2">
                                            <div className="space-y-1.5">
                                                <Label htmlFor="site_name" className="text-xs font-semibold">
                                                    Portal Title Override
                                                </Label>
                                                <Input
                                                    id="site_name"
                                                    value={data.site_name}
                                                    onChange={(e) => setData("site_name", e.target.value)}
                                                    placeholder={data.school_name || "Academic Portal"}
                                                    className="h-9.5 text-sm"
                                                />
                                            </div>
                                            <div className="space-y-1.5">
                                                <Label htmlFor="site_desc" className="text-xs font-semibold">
                                                    Portal Tagline / Description
                                                </Label>
                                                <Input
                                                    id="site_desc"
                                                    value={data.site_description}
                                                    onChange={(e) => setData("site_description", e.target.value)}
                                                    placeholder="Academic Information System & Student Portal"
                                                    className="h-9.5 text-sm"
                                                />
                                            </div>
                                        </div>

                                        {/* Color Accent Picker */}
                                        <div className="space-y-2">
                                            <Label className="text-xs font-semibold">Theme Accent Palette</Label>
                                            <div className="flex flex-wrap gap-2">
                                                {THEME_ACCENTS.map((t) => (
                                                    <button
                                                        key={t.value}
                                                        type="button"
                                                        onClick={() => setData("theme_color", t.value)}
                                                        className={`flex items-center gap-2 rounded-full border px-3 py-1 text-xs transition-all active:scale-[0.98] ${
                                                            data.theme_color === t.value
                                                                ? "border-primary bg-primary/10 ring-primary font-semibold ring-1"
                                                                : "border-border/70 hover:border-border"
                                                        }`}
                                                    >
                                                        <span
                                                            className="size-3.5 shrink-0 rounded-full shadow-xs"
                                                            style={{ backgroundColor: t.value }}
                                                        />
                                                        <span>{t.name}</span>
                                                    </button>
                                                ))}
                                            </div>
                                        </div>

                                        {/* Logo Upload Dropzone */}
                                        <div className="space-y-1.5">
                                            <Label htmlFor="logo_upload" className="text-xs font-semibold">
                                                Institution Seal / Logo
                                            </Label>
                                            <div className="border-border/80 hover:border-primary/50 relative flex cursor-pointer items-center justify-center rounded-xl border border-dashed p-4 text-center transition-colors">
                                                <input
                                                    id="logo_upload"
                                                    type="file"
                                                    accept="image/*"
                                                    onChange={handleLogoFile}
                                                    className="absolute inset-0 cursor-pointer opacity-0"
                                                />
                                                {logoPreview ? (
                                                    <div className="flex items-center gap-3">
                                                        <img
                                                            src={logoPreview}
                                                            alt="Logo preview"
                                                            className="size-10 rounded-lg border object-contain p-1"
                                                        />
                                                        <span className="text-xs font-medium">Custom logo uploaded (click to replace)</span>
                                                    </div>
                                                ) : (
                                                    <div className="text-muted-foreground flex items-center gap-2 text-xs">
                                                        <Upload className="size-4" />
                                                        <span>Click or drop image to upload institution emblem</span>
                                                    </div>
                                                )}
                                            </div>
                                        </div>

                                        {/* Module switches */}
                                        <div className="space-y-2 pt-2">
                                            <Label className="text-xs font-semibold">Operational Feature Switches</Label>
                                            <div className="grid gap-2.5 sm:grid-cols-2">
                                                <label className="border-border/70 bg-muted/20 flex cursor-pointer items-center justify-between rounded-xl border p-3">
                                                    <div className="space-y-0.5">
                                                        <span className="text-foreground block text-xs font-semibold">Student Portal</span>
                                                        <span className="text-muted-foreground block text-[10px]">Self-service records & grades</span>
                                                    </div>
                                                    <Switch
                                                        checked={data.school_portal_enabled}
                                                        onCheckedChange={(v) => setData("school_portal_enabled", v)}
                                                    />
                                                </label>

                                                <label className="border-border/70 bg-muted/20 flex cursor-pointer items-center justify-between rounded-xl border p-3">
                                                    <div className="space-y-0.5">
                                                        <span className="text-foreground block text-xs font-semibold">Online Enrollment</span>
                                                        <span className="text-muted-foreground block text-[10px]">Self-enrolment for students</span>
                                                    </div>
                                                    <Switch
                                                        checked={data.online_enrollment_enabled}
                                                        onCheckedChange={(v) => setData("online_enrollment_enabled", v)}
                                                    />
                                                </label>

                                                <label className="border-border/70 bg-muted/20 flex cursor-pointer items-center justify-between rounded-xl border p-3">
                                                    <div className="space-y-0.5">
                                                        <span className="text-foreground block text-xs font-semibold">Clearance Verification</span>
                                                        <span className="text-muted-foreground block text-[10px]">End-of-term sign-offs</span>
                                                    </div>
                                                    <Switch
                                                        checked={data.enable_clearance_check}
                                                        onCheckedChange={(v) => setData("enable_clearance_check", v)}
                                                    />
                                                </label>

                                                <label className="border-border/70 bg-muted/20 flex cursor-pointer items-center justify-between rounded-xl border p-3">
                                                    <div className="space-y-0.5">
                                                        <span className="text-foreground block text-xs font-semibold">QR Code Verification</span>
                                                        <span className="text-muted-foreground block text-[10px]">Document authenticity badges</span>
                                                    </div>
                                                    <Switch checked={data.enable_qr_codes} onCheckedChange={(v) => setData("enable_qr_codes", v)} />
                                                </label>
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>

                                {/* Pre-flight Launchpad Card */}
                                <Card className="border-primary/40 bg-background overflow-hidden rounded-2xl shadow-md">
                                    <CardHeader className="bg-primary/5 border-border/60 border-b p-5">
                                        <div className="flex items-center justify-between">
                                            <div className="flex items-center gap-2.5">
                                                <div className="bg-primary text-primary-foreground flex size-8 items-center justify-center rounded-xl shadow-xs">
                                                    <Rocket className="size-4" />
                                                </div>
                                                <div>
                                                    <CardTitle className="text-sm font-bold">Deployment Pre-Flight Review</CardTitle>
                                                    <CardDescription className="text-xs">
                                                        Verify your configuration blueprint before instantiating {appName}.
                                                    </CardDescription>
                                                </div>
                                            </div>
                                            <Badge variant="success-light" size="xs">
                                                Ready to Deploy
                                            </Badge>
                                        </div>
                                    </CardHeader>

                                    <CardContent className="space-y-4 p-5">
                                        <div className="grid grid-cols-2 gap-2 text-xs sm:grid-cols-4">
                                            <div className="bg-muted/30 border-border/70 rounded-xl border p-2.5">
                                                <span className="text-muted-foreground block text-[10px] uppercase">Institution</span>
                                                <span className="text-foreground block truncate font-semibold">{data.school_name || "Not set"}</span>
                                            </div>
                                            <div className="bg-muted/30 border-border/70 rounded-xl border p-2.5">
                                                <span className="text-muted-foreground block text-[10px] uppercase">Jurisdiction</span>
                                                <span className="text-foreground block truncate font-semibold">
                                                    {selectedCountryName} [{data.country_code}]
                                                </span>
                                            </div>
                                            <div className="bg-muted/30 border-border/70 rounded-xl border p-2.5">
                                                <span className="text-muted-foreground block text-[10px] uppercase">Superadmin</span>
                                                <span className="text-foreground block truncate font-semibold">{data.admin_email || "Not set"}</span>
                                            </div>
                                            <div className="bg-muted/30 border-border/70 rounded-xl border p-2.5">
                                                <span className="text-muted-foreground block text-[10px] uppercase">Base Currency</span>
                                                <span className="text-foreground block truncate font-mono font-semibold">
                                                    {data.currency || "USD"}
                                                </span>
                                            </div>
                                        </div>

                                        <Button
                                            type="button"
                                            onClick={() => handleSubmit()}
                                            disabled={processing}
                                            className="bg-primary text-primary-foreground h-12 w-full rounded-xl text-sm font-semibold shadow-md transition-transform active:scale-[0.98]"
                                        >
                                            {processing ? (
                                                <span className="flex items-center gap-2">
                                                    <span className="border-primary-foreground size-4 animate-spin rounded-full border-2 border-t-transparent" />
                                                    Instantiating Academic Instance...
                                                </span>
                                            ) : (
                                                <span className="flex items-center gap-2">
                                                    <Rocket className="size-4" />
                                                    Deploy & Initialize {appName}
                                                </span>
                                            )}
                                        </Button>
                                    </CardContent>
                                </Card>
                            </div>
                        )}

                        {/* ── Guided Step Controls Footer ── */}
                        <div className="border-border/60 bg-background flex items-center justify-between rounded-2xl border p-4 shadow-xs">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={handlePrevStep}
                                disabled={currentStep === 1 || processing}
                                className="h-9 gap-1.5 text-xs font-semibold"
                            >
                                <ArrowLeft className="size-3.5" />
                                Back
                            </Button>

                            <div className="flex items-center gap-2">
                                {currentStep < 5 ? (
                                    <Button
                                        type="button"
                                        onClick={handleNextStep}
                                        disabled={processing}
                                        className="h-9 gap-1.5 px-5 text-xs font-semibold shadow-xs transition-transform active:scale-[0.98]"
                                    >
                                        <span>Continue</span>
                                        <ArrowRight className="size-3.5" />
                                    </Button>
                                ) : (
                                    <Button
                                        type="button"
                                        onClick={() => handleSubmit()}
                                        disabled={processing}
                                        className="bg-primary text-primary-foreground h-9 gap-1.5 px-6 text-xs font-semibold shadow-xs transition-transform active:scale-[0.98]"
                                    >
                                        {processing ? (
                                            <span className="border-primary-foreground size-3.5 animate-spin rounded-full border-2 border-t-transparent" />
                                        ) : (
                                            <Rocket className="size-3.5" />
                                        )}
                                        Deploy Instance
                                    </Button>
                                )}
                            </div>
                        </div>
                    </section>
                </div>
            </main>
        </div>
    );
}
