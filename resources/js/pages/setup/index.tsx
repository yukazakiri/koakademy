import { Badge } from "@/components/reui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Checkbox } from "@/components/ui/checkbox";
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from "@/components/ui/collapsible";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Switch } from "@/components/ui/switch";
import { Head, useForm, usePage } from "@inertiajs/react";
import {
    BookOpen,
    Building2,
    CalendarDays,
    CheckCircle2,
    ChevronDown,
    ExternalLink,
    Eye,
    EyeOff,
    Info,
    Palette,
    Rocket,
    School,
    Search,
    Shield,
    Sparkles,
    Upload,
    User,
} from "lucide-react";
import React, { useMemo, useRef, useState } from "react";
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
        label: "College / University",
        description: "Undergraduate, graduate, and university-level programs.",
    },
    {
        value: "senior_high",
        label: "Senior High School",
        description: "Senior secondary education (e.g. Grades 11–12).",
    },
    {
        value: "junior_high",
        label: "Junior High School",
        description: "Middle or lower secondary school programs.",
    },
    {
        value: "elementary",
        label: "Elementary School",
        description: "Primary or foundational grade school education.",
    },
    {
        value: "technical_vocational",
        label: "Technical-Vocational",
        description: "Technical, trade, and vocational certifications (TVET).",
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

const COMMON_CURRENCIES = [
    { code: "USD", name: "US Dollar" },
    { code: "EUR", name: "Euro" },
    { code: "GBP", name: "British Pound" },
    { code: "CAD", name: "Canadian Dollar" },
    { code: "AUD", name: "Australian Dollar" },
    { code: "SGD", name: "Singapore Dollar" },
    { code: "JPY", name: "Japanese Yen" },
    { code: "INR", name: "Indian Rupee" },
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

    const [programSearch, setProgramSearch] = useState("");
    const [showPassword, setShowPassword] = useState(false);
    const [showConfirmPassword, setShowConfirmPassword] = useState(false);
    const [isContactsOpen, setIsContactsOpen] = useState(false);
    const [isCustomizationOpen, setIsCustomizationOpen] = useState(false);
    const [logoPreview, setLogoPreview] = useState<string | null>(null);

    // Section DOM refs for smooth scrolling
    const adminRef = useRef<HTMLElement>(null);
    const institutionRef = useRef<HTMLElement>(null);
    const curriculumRef = useRef<HTMLElement>(null);
    const academicRef = useRef<HTMLElement>(null);
    const preferencesRef = useRef<HTMLElement>(null);

    const { data, setData, post, processing, errors, clearErrors } = useForm({
        // Section 1: Administrator
        admin_name: "",
        admin_email: "",
        admin_password: "",
        admin_password_confirmation: "",
        // Section 2: Institution
        school_name: "",
        school_code: "",
        country_code: "PH",
        school_level: "higher_education",
        school_description: "",
        school_email: "",
        school_phone: "",
        school_location: "",
        dean_name: "",
        dean_email: "",
        // Section 3: Curriculum
        curriculum_framework: "",
        programs: [] as string[],
        seed_strand_subjects: false,
        // Section 4: Academic
        school_starting_date: "",
        school_ending_date: "",
        semester: "1",
        curriculum_year: "",
        currency: "PHP",
        // Section 5: Preferences
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

    /* ── Catalog and Jurisdictional Resolution ── */

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

    const calendarPresets = useMemo(() => {
        const raw = countryCatalog?.calendars ?? [];
        return raw.filter((p) => !p.country_code || p.country_code === data.country_code);
    }, [countryCatalog, data.country_code]);

    const selectedCountryName = useMemo(() => {
        return countries.find((c) => c.code === data.country_code)?.name ?? data.country_code;
    }, [countries, data.country_code]);

    const selectedLevelLabel = useMemo(() => {
        return schoolLevelOptions.find((l) => l.value === data.school_level)?.label ?? data.school_level;
    }, [schoolLevelOptions, data.school_level]);

    /* ── Handlers ── */

    const handleCountryChange = (newCode: string) => {
        if (newCode === data.country_code) return;
        setData((prev) => ({
            ...prev,
            country_code: newCode,
            curriculum_framework: "",
            programs: [],
            seed_strand_subjects: false,
            school_starting_date: "",
            school_ending_date: "",
            curriculum_year: "",
            currency: newCode === "PH" ? "PHP" : "",
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

    const applyCalendarPreset = (preset: CalendarPreset) => {
        const startYear = new Date(preset.starts).getFullYear();
        const endYear = new Date(preset.ends).getFullYear();
        setData((prev) => ({
            ...prev,
            school_starting_date: preset.starts,
            school_ending_date: preset.ends,
            semester: "1",
            curriculum_year: `${startYear}-${endYear}`,
        }));
    };

    const handleLogoFile = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (file) {
            setData("logo", file);
            setLogoPreview(URL.createObjectURL(file));
        }
    };

    const scrollToRef = (ref: React.RefObject<HTMLElement | null>) => {
        ref.current?.scrollIntoView({ behavior: "smooth", block: "start" });
    };

    /* ── Readiness Verification ── */

    const isStep1Complete = useMemo(() => {
        return (
            Boolean(data.admin_name.trim()) &&
            Boolean(data.admin_email.trim()) &&
            data.admin_password.length >= 8 &&
            data.admin_password === data.admin_password_confirmation
        );
    }, [data.admin_name, data.admin_email, data.admin_password, data.admin_password_confirmation]);

    const isStep2Complete = useMemo(() => {
        return Boolean(data.school_name.trim()) && Boolean(data.school_code.trim()) && Boolean(data.country_code) && Boolean(data.school_level);
    }, [data.school_name, data.school_code, data.country_code, data.school_level]);

    const isStep3Complete = useMemo(() => {
        // Framework is optional. Non-PH or unconfigured is automatically ready.
        if (availableFrameworks.length === 0 || !data.curriculum_framework) {
            return true;
        }
        return true;
    }, [availableFrameworks, data.curriculum_framework]);

    const isStep4Complete = useMemo(() => {
        const hasDates =
            Boolean(data.school_starting_date) && Boolean(data.school_ending_date) && data.school_ending_date > data.school_starting_date;
        const hasSemester = Boolean(data.semester);
        const hasCurrency = data.country_code === "PH" ? Boolean(data.currency) : /^[A-Z]{3}$/.test(data.currency.trim());
        return hasDates && hasSemester && hasCurrency;
    }, [data.school_starting_date, data.school_ending_date, data.semester, data.country_code, data.currency]);

    const completedSectionsCount = [isStep1Complete, isStep2Complete, isStep3Complete, isStep4Complete].filter(Boolean).length;
    const isLaunchReady = isStep1Complete && isStep2Complete && isStep4Complete;

    /* ── Client Validation & Submission ── */

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        clearErrors();

        if (!isStep1Complete) {
            toast.error("Please complete the Administrator Identity section.");
            scrollToRef(adminRef);
            return;
        }
        if (!isStep2Complete) {
            toast.error("Please complete the Institution Profile section.");
            scrollToRef(institutionRef);
            return;
        }
        if (!isStep4Complete) {
            toast.error("Please complete the Academic Calendar & Currency section.");
            scrollToRef(academicRef);
            return;
        }

        post("/setup", {
            onSuccess: () => {
                toast.success(`${appName} initialized successfully!`);
            },
            onError: (errs) => {
                toast.error("Please review the highlighted errors on the form.");
                if (errs.admin_name || errs.admin_email || errs.admin_password || errs.admin_password_confirmation) {
                    scrollToRef(adminRef);
                } else if (errs.school_name || errs.school_code || errs.country_code || errs.school_level || errs.school_email) {
                    scrollToRef(institutionRef);
                } else if (errs.curriculum_framework || errs.programs || errs.seed_strand_subjects) {
                    scrollToRef(curriculumRef);
                } else if (errs.school_starting_date || errs.school_ending_date || errs.semester || errs.curriculum_year || errs.currency) {
                    scrollToRef(academicRef);
                }
            },
        });
    };

    return (
        <div className="bg-muted/20 text-foreground min-h-screen font-sans antialiased">
            <Head title={`Setup — ${appName}`} />

            {/* Persistent Top Navigation Bar */}
            <header className="border-border/80 bg-background/95 sticky top-0 z-30 border-b backdrop-blur-md">
                <div className="mx-auto flex h-14 max-w-6xl items-center justify-between px-4 sm:px-6">
                    <div className="flex items-center gap-3">
                        {logoUrl ? (
                            <img src={logoUrl} alt={appName} className="size-7 rounded object-contain" />
                        ) : (
                            <div className="bg-primary text-primary-foreground flex size-7 items-center justify-center rounded-lg shadow-xs">
                                <Shield className="size-3.5" />
                            </div>
                        )}
                        <div className="flex items-center gap-2">
                            <span className="text-foreground text-sm font-semibold tracking-tight">{appName}</span>
                            <Badge variant="primary-light" size="xs">
                                System Initialization
                            </Badge>
                        </div>
                    </div>

                    <div className="flex items-center gap-3">
                        <span className="text-muted-foreground hidden text-xs sm:inline-block">
                            Readiness: <strong className="text-foreground">{completedSectionsCount} of 4</strong> required sections
                        </span>
                        <Button
                            type="button"
                            onClick={handleSubmit}
                            disabled={processing}
                            size="sm"
                            className="h-8 gap-1.5 px-4 text-xs font-semibold shadow-xs transition-transform active:scale-[0.98]"
                        >
                            {processing ? (
                                <span className="border-primary-foreground size-3.5 animate-spin rounded-full border-2 border-t-transparent" />
                            ) : (
                                <Rocket className="size-3.5" />
                            )}
                            Initialize Platform
                        </Button>
                    </div>
                </div>
            </header>

            {/* Main Guided Canvas Layout */}
            <div className="mx-auto max-w-6xl px-4 py-8 sm:px-6">
                <div className="grid grid-cols-1 gap-8 lg:grid-cols-12 lg:items-start">
                    {/* LEFT / CENTER: Full Page Guided Form Canvas (7 cols) */}
                    <div className="space-y-8 lg:col-span-7">
                        {/* Page Introduction Banner */}
                        <div className="space-y-1.5">
                            <h1 className="text-foreground text-2xl font-bold tracking-tight sm:text-3xl">Welcome to {appName}</h1>
                            <p className="text-muted-foreground text-sm leading-relaxed">
                                Complete this guided launchpad to create your primary administrator, establish your institution's jurisdiction and
                                curriculum architecture, and activate your academic schedule.
                            </p>
                        </div>

                        {/* ── SECTION 1: Administrator Identity ── */}
                        <section ref={adminRef} id="section-admin" className="scroll-mt-20">
                            <Card className="border-border/80 bg-background overflow-hidden rounded-2xl shadow-xs">
                                <CardHeader className="border-border/60 border-b pb-3.5">
                                    <div className="flex items-center justify-between">
                                        <div className="flex items-center gap-2.5">
                                            <div className="bg-primary/10 text-primary flex size-7 items-center justify-center rounded-lg">
                                                <User className="size-4" />
                                            </div>
                                            <div>
                                                <CardTitle className="text-sm font-semibold">1. Root Administrator</CardTitle>
                                                <CardDescription className="text-xs">
                                                    The primary super-admin account that controls system-wide settings.
                                                </CardDescription>
                                            </div>
                                        </div>
                                        {isStep1Complete ? (
                                            <Badge variant="success-light" size="xs">
                                                ✓ Ready
                                            </Badge>
                                        ) : (
                                            <Badge variant="outline" size="xs">
                                                Required
                                            </Badge>
                                        )}
                                    </div>
                                </CardHeader>

                                <CardContent className="space-y-4 p-5">
                                    <div className="grid gap-3.5 sm:grid-cols-2">
                                        <div className="space-y-1 sm:col-span-2">
                                            <Label htmlFor="admin_name" className="text-xs font-medium">
                                                Administrator Full Name <span className="text-destructive">*</span>
                                            </Label>
                                            <Input
                                                id="admin_name"
                                                value={data.admin_name}
                                                onChange={(e) => setData("admin_name", e.target.value)}
                                                placeholder="e.g. Maria Clara Santos"
                                                className="h-9 text-sm"
                                            />
                                            {errors.admin_name && <p className="text-destructive text-xs">{errors.admin_name}</p>}
                                        </div>

                                        <div className="space-y-1 sm:col-span-2">
                                            <Label htmlFor="admin_email" className="text-xs font-medium">
                                                Email Address <span className="text-destructive">*</span>
                                            </Label>
                                            <Input
                                                id="admin_email"
                                                type="email"
                                                value={data.admin_email}
                                                onChange={(e) => setData("admin_email", e.target.value)}
                                                placeholder="admin@institution.edu"
                                                className="h-9 text-sm"
                                            />
                                            {errors.admin_email && <p className="text-destructive text-xs">{errors.admin_email}</p>}
                                        </div>

                                        <div className="space-y-1">
                                            <Label htmlFor="admin_password" className="text-xs font-medium">
                                                Password <span className="text-destructive">*</span>
                                            </Label>
                                            <div className="relative">
                                                <Input
                                                    id="admin_password"
                                                    type={showPassword ? "text" : "password"}
                                                    value={data.admin_password}
                                                    onChange={(e) => setData("admin_password", e.target.value)}
                                                    placeholder="At least 8 characters"
                                                    className="h-9 pr-9 text-sm"
                                                />
                                                <button
                                                    type="button"
                                                    onClick={() => setShowPassword((p) => !p)}
                                                    tabIndex={-1}
                                                    className="text-muted-foreground hover:text-foreground absolute top-1/2 right-2.5 -translate-y-1/2"
                                                >
                                                    {showPassword ? <EyeOff className="size-4" /> : <Eye className="size-4" />}
                                                </button>
                                            </div>
                                            {errors.admin_password && <p className="text-destructive text-xs">{errors.admin_password}</p>}
                                        </div>

                                        <div className="space-y-1">
                                            <Label htmlFor="admin_password_confirmation" className="text-xs font-medium">
                                                Confirm Password <span className="text-destructive">*</span>
                                            </Label>
                                            <div className="relative">
                                                <Input
                                                    id="admin_password_confirmation"
                                                    type={showConfirmPassword ? "text" : "password"}
                                                    value={data.admin_password_confirmation}
                                                    onChange={(e) => setData("admin_password_confirmation", e.target.value)}
                                                    placeholder="Repeat password"
                                                    className="h-9 pr-9 text-sm"
                                                />
                                                <button
                                                    type="button"
                                                    onClick={() => setShowConfirmPassword((p) => !p)}
                                                    tabIndex={-1}
                                                    className="text-muted-foreground hover:text-foreground absolute top-1/2 right-2.5 -translate-y-1/2"
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
                        </section>

                        {/* ── SECTION 2: Institution & Jurisdiction ── */}
                        <section ref={institutionRef} id="section-institution" className="scroll-mt-20">
                            <Card className="border-border/80 bg-background overflow-hidden rounded-2xl shadow-xs">
                                <CardHeader className="border-border/60 border-b pb-3.5">
                                    <div className="flex items-center justify-between">
                                        <div className="flex items-center gap-2.5">
                                            <div className="bg-primary/10 text-primary flex size-7 items-center justify-center rounded-lg">
                                                <Building2 className="size-4" />
                                            </div>
                                            <div>
                                                <CardTitle className="text-sm font-semibold">2. Institution & Jurisdiction</CardTitle>
                                                <CardDescription className="text-xs">
                                                    Location, regulatory authority, and educational level.
                                                </CardDescription>
                                            </div>
                                        </div>
                                        {isStep2Complete ? (
                                            <Badge variant="success-light" size="xs">
                                                ✓ Ready
                                            </Badge>
                                        ) : (
                                            <Badge variant="outline" size="xs">
                                                Required
                                            </Badge>
                                        )}
                                    </div>
                                </CardHeader>

                                <CardContent className="space-y-4 p-5">
                                    {/* Country / Jurisdiction Picker */}
                                    <div className="bg-primary/5 border-primary/20 space-y-1.5 rounded-xl border p-3.5">
                                        <div className="flex items-center justify-between">
                                            <Label htmlFor="country_code" className="text-foreground text-xs font-semibold">
                                                Country / Jurisdiction <span className="text-destructive">*</span>
                                            </Label>
                                            <Badge variant="outline" size="xs">
                                                {selectedCountryName} ({data.country_code})
                                            </Badge>
                                        </div>

                                        <select
                                            id="country_code"
                                            value={data.country_code}
                                            onChange={(e) => handleCountryChange(e.target.value)}
                                            className="border-border bg-background text-foreground focus-visible:ring-primary h-9 w-full rounded-lg border px-3 text-sm focus-visible:ring-2 focus-visible:outline-none"
                                        >
                                            <option value="">Select country...</option>
                                            {countries.map((c) => (
                                                <option key={c.code} value={c.code}>
                                                    {c.name} ({c.code})
                                                </option>
                                            ))}
                                        </select>
                                        <p className="text-muted-foreground text-[11px]">
                                            Curriculum standards and academic presets dynamically update based on this country.
                                        </p>
                                    </div>

                                    {/* Institution Name and Code */}
                                    <div className="grid gap-3 sm:grid-cols-3">
                                        <div className="space-y-1 sm:col-span-2">
                                            <Label htmlFor="school_name" className="text-xs font-medium">
                                                Institution Name <span className="text-destructive">*</span>
                                            </Label>
                                            <Input
                                                id="school_name"
                                                value={data.school_name}
                                                onChange={(e) => setData("school_name", e.target.value)}
                                                placeholder="e.g. St. Jude Institute of Technology"
                                                className="h-9 text-sm"
                                            />
                                            {errors.school_name && <p className="text-destructive text-xs">{errors.school_name}</p>}
                                        </div>

                                        <div className="space-y-1">
                                            <Label htmlFor="school_code" className="text-xs font-medium">
                                                Code / Acronym <span className="text-destructive">*</span>
                                            </Label>
                                            <Input
                                                id="school_code"
                                                value={data.school_code}
                                                onChange={(e) => setData("school_code", e.target.value.toUpperCase().slice(0, 20))}
                                                placeholder="e.g. SJIT"
                                                className="h-9 text-sm uppercase"
                                            />
                                            {errors.school_code && <p className="text-destructive text-xs">{errors.school_code}</p>}
                                        </div>
                                    </div>

                                    {/* Level Selector */}
                                    <div className="space-y-1.5">
                                        <Label className="text-xs font-medium">
                                            Educational Level <span className="text-destructive">*</span>
                                        </Label>
                                        <div className="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                            {schoolLevelOptions.map((opt) => {
                                                const selected = data.school_level === opt.value;
                                                return (
                                                    <button
                                                        key={opt.value}
                                                        type="button"
                                                        onClick={() => handleLevelChange(opt.value)}
                                                        className={`flex flex-col items-start gap-0.5 rounded-xl border p-2.5 text-left transition-all active:scale-[0.98] ${
                                                            selected
                                                                ? "border-primary bg-primary/5 ring-primary/30 ring-1"
                                                                : "border-border/80 hover:border-border hover:bg-muted/40"
                                                        }`}
                                                    >
                                                        <div className="flex w-full items-center justify-between">
                                                            <span className="text-foreground text-xs font-semibold">{opt.label}</span>
                                                            {selected && <CheckCircle2 className="text-primary size-3.5 shrink-0" />}
                                                        </div>
                                                        <p className="text-muted-foreground line-clamp-1 text-[10px]">{opt.description}</p>
                                                    </button>
                                                );
                                            })}
                                        </div>
                                    </div>

                                    {/* Secondary Contacts Collapsible */}
                                    <Collapsible
                                        open={isContactsOpen}
                                        onOpenChange={setIsContactsOpen}
                                        className="border-border/80 rounded-xl border"
                                    >
                                        <CollapsibleTrigger asChild>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                className="text-muted-foreground hover:text-foreground flex w-full items-center justify-between p-3 text-xs"
                                            >
                                                <span className="flex items-center gap-2 font-medium">
                                                    <Info className="size-3.5" />
                                                    Additional Campus Contacts & Dean (Optional)
                                                </span>
                                                <ChevronDown
                                                    className={`size-3.5 transition-transform duration-200 ${isContactsOpen ? "rotate-180" : ""}`}
                                                />
                                            </Button>
                                        </CollapsibleTrigger>
                                        <CollapsibleContent className="space-y-3 border-t p-3 text-xs">
                                            <div className="grid gap-2.5 sm:grid-cols-2">
                                                <div className="space-y-1">
                                                    <Label htmlFor="school_email" className="text-[11px]">
                                                        Official Email
                                                    </Label>
                                                    <Input
                                                        id="school_email"
                                                        type="email"
                                                        value={data.school_email}
                                                        onChange={(e) => setData("school_email", e.target.value)}
                                                        placeholder="contact@institution.edu"
                                                        className="h-8 text-xs"
                                                    />
                                                </div>
                                                <div className="space-y-1">
                                                    <Label htmlFor="school_phone" className="text-[11px]">
                                                        Phone Number
                                                    </Label>
                                                    <Input
                                                        id="school_phone"
                                                        value={data.school_phone}
                                                        onChange={(e) => setData("school_phone", e.target.value)}
                                                        placeholder="+1 555 123 4567"
                                                        className="h-8 text-xs"
                                                    />
                                                </div>
                                                <div className="space-y-1 sm:col-span-2">
                                                    <Label htmlFor="school_location" className="text-[11px]">
                                                        Campus Location / Address
                                                    </Label>
                                                    <Input
                                                        id="school_location"
                                                        value={data.school_location}
                                                        onChange={(e) => setData("school_location", e.target.value)}
                                                        placeholder="100 Main Campus Ave, City"
                                                        className="h-8 text-xs"
                                                    />
                                                </div>
                                                <div className="space-y-1">
                                                    <Label htmlFor="dean_name" className="text-[11px]">
                                                        Dean or Institution Head
                                                    </Label>
                                                    <Input
                                                        id="dean_name"
                                                        value={data.dean_name}
                                                        onChange={(e) => setData("dean_name", e.target.value)}
                                                        placeholder="Dr. Alexander Vance"
                                                        className="h-8 text-xs"
                                                    />
                                                </div>
                                                <div className="space-y-1">
                                                    <Label htmlFor="dean_email" className="text-[11px]">
                                                        Dean Email Address
                                                    </Label>
                                                    <Input
                                                        id="dean_email"
                                                        type="email"
                                                        value={data.dean_email}
                                                        onChange={(e) => setData("dean_email", e.target.value)}
                                                        placeholder="dean@institution.edu"
                                                        className="h-8 text-xs"
                                                    />
                                                </div>
                                            </div>
                                        </CollapsibleContent>
                                    </Collapsible>
                                </CardContent>
                            </Card>
                        </section>

                        {/* ── SECTION 3: Curriculum Architecture ── */}
                        <section ref={curriculumRef} id="section-curriculum" className="scroll-mt-20">
                            <Card className="border-border/80 bg-background overflow-hidden rounded-2xl shadow-xs">
                                <CardHeader className="border-border/60 border-b pb-3.5">
                                    <div className="flex items-center justify-between">
                                        <div className="flex items-center gap-2.5">
                                            <div className="bg-primary/10 text-primary flex size-7 items-center justify-center rounded-lg">
                                                <BookOpen className="size-4" />
                                            </div>
                                            <div>
                                                <CardTitle className="text-sm font-semibold">3. Curriculum Architecture</CardTitle>
                                                <CardDescription className="text-xs">
                                                    Regulatory frameworks or standard academic cataloging.
                                                </CardDescription>
                                            </div>
                                        </div>
                                        <Badge variant="outline" size="xs">
                                            {selectedCountryName}
                                        </Badge>
                                    </div>
                                </CardHeader>

                                <CardContent className="space-y-4 p-5">
                                    {availableFrameworks.length === 0 ? (
                                        /* Guided banner for international or unconfigured countries */
                                        <div className="space-y-3">
                                            <div className="bg-muted/30 border-border/80 flex items-start gap-3 rounded-xl border p-4 text-xs">
                                                <Info className="text-primary mt-0.5 size-4 shrink-0" />
                                                <div className="space-y-1">
                                                    <p className="text-foreground text-xs font-semibold">
                                                        No education frameworks are available in this country.
                                                    </p>
                                                    <p className="text-muted-foreground text-[11px] leading-relaxed">
                                                        Your institution will launch with standard academic structure. You can add custom departments,
                                                        courses, and programs manually in the portal. Maintainers can easily contribute framework
                                                        providers for {selectedCountryName} in the catalog registry.
                                                    </p>
                                                </div>
                                            </div>

                                            <div className="border-border/60 bg-background flex items-center justify-between rounded-lg border p-3 text-xs">
                                                <div className="flex items-center gap-2">
                                                    <CheckCircle2 className="size-3.5 text-emerald-500" />
                                                    <span className="text-foreground font-medium">Standard Institution Catalog Active</span>
                                                </div>
                                                <Badge variant="secondary" size="xs">
                                                    Standard
                                                </Badge>
                                            </div>
                                        </div>
                                    ) : (
                                        /* Supported national frameworks (e.g. Philippines) */
                                        <div className="space-y-4">
                                            <div className="space-y-2">
                                                <div className="flex items-center justify-between">
                                                    <Label className="text-xs font-semibold">Available Regulatory Frameworks</Label>
                                                    <span className="text-muted-foreground text-[11px]">
                                                        Optional: Select framework or use standard
                                                    </span>
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
                                                                        <p className="text-muted-foreground line-clamp-2 text-[10px]">
                                                                            {f.description}
                                                                        </p>
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
                                            </div>

                                            {/* Program selection if framework has groups */}
                                            {activeFramework && currentProgramGroups.length > 0 && (
                                                <div className="border-border/80 space-y-3 rounded-xl border p-3.5">
                                                    <div className="flex flex-col gap-1 border-b pb-2 sm:flex-row sm:items-center sm:justify-between">
                                                        <div>
                                                            <h4 className="text-foreground text-xs font-semibold">Select Offerings to Preload</h4>
                                                            <p className="text-muted-foreground text-[10px]">
                                                                Departments and course records will be created automatically.
                                                            </p>
                                                        </div>
                                                        <Badge variant="primary-light" size="xs">
                                                            {data.programs.length} programs selected
                                                        </Badge>
                                                    </div>

                                                    <div className="relative">
                                                        <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2" />
                                                        <Input
                                                            value={programSearch}
                                                            onChange={(e) => setProgramSearch(e.target.value)}
                                                            placeholder="Search offerings by name or code..."
                                                            className="h-8 pl-8 text-xs"
                                                        />
                                                    </div>

                                                    <div className="max-h-64 space-y-3 overflow-y-auto pr-1">
                                                        {currentProgramGroups.map((group) => {
                                                            const filtered = group.programs.filter((p) =>
                                                                `${p.code} ${p.title}`.toLowerCase().includes(programSearch.toLowerCase()),
                                                            );
                                                            if (filtered.length === 0) return null;

                                                            const allSelected = filtered.every((p) => data.programs.includes(p.code));

                                                            return (
                                                                <div key={group.key} className="space-y-1">
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

                                                                    <div className="grid grid-cols-1 gap-1 sm:grid-cols-2">
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
                        </section>

                        {/* ── SECTION 4: Academic Calendar & Currency ── */}
                        <section ref={academicRef} id="section-academic" className="scroll-mt-20">
                            <Card className="border-border/80 bg-background overflow-hidden rounded-2xl shadow-xs">
                                <CardHeader className="border-border/60 border-b pb-3.5">
                                    <div className="flex items-center justify-between">
                                        <div className="flex items-center gap-2.5">
                                            <div className="bg-primary/10 text-primary flex size-7 items-center justify-center rounded-lg">
                                                <CalendarDays className="size-4" />
                                            </div>
                                            <div>
                                                <CardTitle className="text-sm font-semibold">4. Academic Calendar & Currency</CardTitle>
                                                <CardDescription className="text-xs">
                                                    Define the operational school year, initial term, and base currency.
                                                </CardDescription>
                                            </div>
                                        </div>
                                        {isStep4Complete ? (
                                            <Badge variant="success-light" size="xs">
                                                ✓ Ready
                                            </Badge>
                                        ) : (
                                            <Badge variant="outline" size="xs">
                                                Required
                                            </Badge>
                                        )}
                                    </div>
                                </CardHeader>

                                <CardContent className="space-y-4 p-5">
                                    {/* Presets chips */}
                                    {calendarPresets.length > 0 && (
                                        <div className="space-y-1.5">
                                            <Label className="text-xs font-semibold">Available Official Presets</Label>
                                            <div className="flex flex-wrap gap-2">
                                                {calendarPresets.map((preset) => (
                                                    <button
                                                        key={preset.key}
                                                        type="button"
                                                        onClick={() => applyCalendarPreset(preset)}
                                                        className="border-border/80 bg-muted/30 hover:border-primary/50 hover:bg-muted flex items-center gap-2 rounded-lg border px-3 py-1.5 text-left text-xs transition-colors active:scale-[0.98]"
                                                    >
                                                        <span className="text-foreground font-semibold">{preset.label}</span>
                                                        <span className="text-muted-foreground text-[10px]">{preset.note}</span>
                                                    </button>
                                                ))}
                                            </div>
                                        </div>
                                    )}

                                    {/* Date inputs */}
                                    <div className="grid gap-3.5 sm:grid-cols-2">
                                        <div className="space-y-1">
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
                                                    if (val && data.school_ending_date) {
                                                        const s = new Date(val).getFullYear();
                                                        const en = new Date(data.school_ending_date).getFullYear();
                                                        setData("curriculum_year", `${s}-${en}`);
                                                    }
                                                }}
                                                className="h-9 text-sm"
                                            />
                                            {errors.school_starting_date && <p className="text-destructive text-xs">{errors.school_starting_date}</p>}
                                        </div>

                                        <div className="space-y-1">
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
                                                    if (data.school_starting_date && val) {
                                                        const s = new Date(data.school_starting_date).getFullYear();
                                                        const en = new Date(val).getFullYear();
                                                        setData("curriculum_year", `${s}-${en}`);
                                                    }
                                                }}
                                                className="h-9 text-sm"
                                            />
                                            {errors.school_ending_date && <p className="text-destructive text-xs">{errors.school_ending_date}</p>}
                                        </div>

                                        <div className="space-y-1">
                                            <Label htmlFor="semester" className="text-xs font-medium">
                                                Initial Term / Semester <span className="text-destructive">*</span>
                                            </Label>
                                            <Select value={data.semester} onValueChange={(val) => setData("semester", val ?? "1")}>
                                                <SelectTrigger id="semester" className="h-9 text-sm">
                                                    <SelectValue placeholder="Select semester..." />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="1">1st Semester / Fall Term</SelectItem>
                                                    <SelectItem value="2">2nd Semester / Spring Term</SelectItem>
                                                    <SelectItem value="3">Summer / Midyear Term</SelectItem>
                                                </SelectContent>
                                            </Select>
                                            {errors.semester && <p className="text-destructive text-xs">{errors.semester}</p>}
                                        </div>

                                        <div className="space-y-1">
                                            <div className="flex items-center justify-between">
                                                <Label htmlFor="currency" className="text-xs font-medium">
                                                    Operating Currency <span className="text-destructive">*</span>
                                                </Label>
                                                {data.country_code === "PH" && (
                                                    <Badge variant="secondary" size="xs">
                                                        PHP Default
                                                    </Badge>
                                                )}
                                            </div>

                                            {data.country_code === "PH" ? (
                                                <Input id="currency" value="PHP" disabled className="bg-muted/40 h-9 font-mono text-sm" />
                                            ) : (
                                                <div className="space-y-1.5">
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
                                                        placeholder="e.g. USD"
                                                        maxLength={3}
                                                        className="h-9 font-mono text-sm uppercase"
                                                    />
                                                    <div className="flex flex-wrap gap-1">
                                                        {COMMON_CURRENCIES.map((c) => (
                                                            <button
                                                                key={c.code}
                                                                type="button"
                                                                onClick={() => {
                                                                    setData("currency", c.code);
                                                                    clearErrors("currency");
                                                                }}
                                                                className={`rounded border px-1.5 py-0.5 font-mono text-[10px] transition-colors ${
                                                                    data.currency === c.code
                                                                        ? "bg-primary text-primary-foreground border-primary"
                                                                        : "bg-muted/40 border-border/80 hover:bg-muted text-muted-foreground hover:text-foreground"
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
                        </section>

                        {/* ── SECTION 5: Branding & Modular Features (Optional) ── */}
                        <section ref={preferencesRef} id="section-preferences" className="scroll-mt-20">
                            <Card className="border-border/80 bg-background overflow-hidden rounded-2xl shadow-xs">
                                <CardHeader className="border-border/60 border-b pb-3.5">
                                    <div className="flex items-center justify-between">
                                        <div className="flex items-center gap-2.5">
                                            <div className="bg-primary/10 text-primary flex size-7 items-center justify-center rounded-lg">
                                                <Palette className="size-4" />
                                            </div>
                                            <div>
                                                <CardTitle className="text-sm font-semibold">5. Appearance & Modules</CardTitle>
                                                <CardDescription className="text-xs">
                                                    Optional visual customizations and functional modules.
                                                </CardDescription>
                                            </div>
                                        </div>
                                        <Badge variant="outline" size="xs">
                                            Optional
                                        </Badge>
                                    </div>
                                </CardHeader>

                                <CardContent className="space-y-4 p-5">
                                    <Collapsible
                                        open={isCustomizationOpen}
                                        onOpenChange={setIsCustomizationOpen}
                                        className="border-border/80 rounded-xl border"
                                    >
                                        <CollapsibleTrigger asChild>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                className="text-muted-foreground hover:text-foreground flex w-full items-center justify-between p-3.5 text-xs"
                                            >
                                                <span className="flex items-center gap-2 font-medium">
                                                    <Sparkles className="size-3.5" />
                                                    Customize theme accent, portal branding & system switches
                                                </span>
                                                <ChevronDown
                                                    className={`size-3.5 transition-transform duration-200 ${
                                                        isCustomizationOpen ? "rotate-180" : ""
                                                    }`}
                                                />
                                            </Button>
                                        </CollapsibleTrigger>

                                        <CollapsibleContent className="space-y-4 border-t p-4 text-xs">
                                            <div className="grid gap-3 sm:grid-cols-2">
                                                <div className="space-y-1">
                                                    <Label htmlFor="site_name" className="text-[11px]">
                                                        Portal Title Override
                                                    </Label>
                                                    <Input
                                                        id="site_name"
                                                        value={data.site_name}
                                                        onChange={(e) => setData("site_name", e.target.value)}
                                                        placeholder={data.school_name || "Portal Name"}
                                                        className="h-8 text-xs"
                                                    />
                                                </div>
                                                <div className="space-y-1">
                                                    <Label htmlFor="site_desc" className="text-[11px]">
                                                        Portal Description
                                                    </Label>
                                                    <Input
                                                        id="site_desc"
                                                        value={data.site_description}
                                                        onChange={(e) => setData("site_description", e.target.value)}
                                                        placeholder="Academic Portal & Information System"
                                                        className="h-8 text-xs"
                                                    />
                                                </div>
                                            </div>

                                            {/* Theme accent colors */}
                                            <div className="space-y-1.5 pt-1">
                                                <Label className="text-[11px]">Theme Accent Swatch</Label>
                                                <div className="flex flex-wrap gap-1.5">
                                                    {THEME_ACCENTS.map((t) => (
                                                        <button
                                                            key={t.value}
                                                            type="button"
                                                            onClick={() => setData("theme_color", t.value)}
                                                            className={`flex items-center gap-1.5 rounded-full border px-2 py-0.5 text-[10px] transition-all ${
                                                                data.theme_color === t.value
                                                                    ? "border-primary bg-primary/10 ring-primary ring-1"
                                                                    : "border-border/60 hover:border-border"
                                                            }`}
                                                        >
                                                            <span
                                                                className="size-2.5 shrink-0 rounded-full shadow-xs"
                                                                style={{ backgroundColor: t.value }}
                                                            />
                                                            <span>{t.name}</span>
                                                        </button>
                                                    ))}
                                                </div>
                                            </div>

                                            {/* Logo upload */}
                                            <div className="space-y-1 pt-1">
                                                <Label htmlFor="logo_upload" className="text-[11px]">
                                                    Logo Upload
                                                </Label>
                                                <div className="border-border/80 hover:border-primary/50 relative flex cursor-pointer items-center justify-center rounded-xl border border-dashed p-3 text-center transition-colors">
                                                    <input
                                                        id="logo_upload"
                                                        type="file"
                                                        accept="image/*"
                                                        onChange={handleLogoFile}
                                                        className="absolute inset-0 cursor-pointer opacity-0"
                                                    />
                                                    {logoPreview ? (
                                                        <div className="flex items-center gap-2.5">
                                                            <img
                                                                src={logoPreview}
                                                                alt="Logo"
                                                                className="size-8 rounded border object-contain p-0.5"
                                                            />
                                                            <span className="text-xs font-medium">Logo selected (click to replace)</span>
                                                        </div>
                                                    ) : (
                                                        <div className="text-muted-foreground flex items-center gap-2 text-xs">
                                                            <Upload className="size-3.5" />
                                                            <span>Click or drop image to upload custom logo</span>
                                                        </div>
                                                    )}
                                                </div>
                                            </div>

                                            {/* Feature switches */}
                                            <div className="space-y-2 pt-2">
                                                <Label className="text-[11px]">Module Capabilities</Label>
                                                <div className="grid gap-2 sm:grid-cols-2">
                                                    <label className="border-border/60 bg-muted/20 flex cursor-pointer items-center justify-between rounded-lg border p-2">
                                                        <span className="text-xs">Student Portal</span>
                                                        <Switch
                                                            checked={data.school_portal_enabled}
                                                            onCheckedChange={(v) => setData("school_portal_enabled", v)}
                                                        />
                                                    </label>
                                                    <label className="border-border/60 bg-muted/20 flex cursor-pointer items-center justify-between rounded-lg border p-2">
                                                        <span className="text-xs">Online Enrollment</span>
                                                        <Switch
                                                            checked={data.online_enrollment_enabled}
                                                            onCheckedChange={(v) => setData("online_enrollment_enabled", v)}
                                                        />
                                                    </label>
                                                    <label className="border-border/60 bg-muted/20 flex cursor-pointer items-center justify-between rounded-lg border p-2">
                                                        <span className="text-xs">Clearance Verification</span>
                                                        <Switch
                                                            checked={data.enable_clearance_check}
                                                            onCheckedChange={(v) => setData("enable_clearance_check", v)}
                                                        />
                                                    </label>
                                                    <label className="border-border/60 bg-muted/20 flex cursor-pointer items-center justify-between rounded-lg border p-2">
                                                        <span className="text-xs">QR Verification</span>
                                                        <Switch
                                                            checked={data.enable_qr_codes}
                                                            onCheckedChange={(v) => setData("enable_qr_codes", v)}
                                                        />
                                                    </label>
                                                </div>
                                            </div>
                                        </CollapsibleContent>
                                    </Collapsible>
                                </CardContent>
                            </Card>
                        </section>

                        {/* Bottom Initialize Action Button */}
                        <div className="pt-2">
                            <Button
                                type="button"
                                onClick={handleSubmit}
                                disabled={processing}
                                className="bg-primary text-primary-foreground h-12 w-full rounded-xl text-base font-semibold shadow-md transition-transform active:scale-[0.98]"
                            >
                                {processing ? (
                                    <span className="flex items-center gap-2">
                                        <span className="border-primary-foreground size-4 animate-spin rounded-full border-2 border-t-transparent" />
                                        Initializing Platform Instance...
                                    </span>
                                ) : (
                                    <span className="flex items-center gap-2">
                                        <Rocket className="size-5" />
                                        Initialize & Launch {appName}
                                    </span>
                                )}
                            </Button>
                        </div>
                    </div>

                    {/* RIGHT: Persistent Live Preview & Launch Readiness Inspector (5 cols) */}
                    <div className="space-y-6 lg:sticky lg:top-20 lg:col-span-5">
                        {/* Live Institution Preview Card */}
                        <Card className="border-border/80 bg-background overflow-hidden rounded-2xl shadow-xs">
                            <CardHeader className="border-border/60 border-b pb-3">
                                <div className="flex items-center justify-between">
                                    <span className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">
                                        Live Institution Preview
                                    </span>
                                    <Badge variant="outline" size="xs">
                                        Real-Time
                                    </Badge>
                                </div>
                            </CardHeader>

                            <CardContent className="space-y-4 p-5">
                                {/* Institution Badge & Name */}
                                <div className="flex items-start gap-3.5">
                                    <div
                                        className="flex size-12 shrink-0 items-center justify-center rounded-xl font-bold text-white shadow-xs"
                                        style={{ backgroundColor: data.theme_color || "#0f172a" }}
                                    >
                                        {logoPreview ? (
                                            <img src={logoPreview} alt="Logo" className="size-full rounded-xl object-contain p-1" />
                                        ) : data.school_code ? (
                                            data.school_code.slice(0, 3)
                                        ) : (
                                            <School className="size-6 text-white/90" />
                                        )}
                                    </div>
                                    <div className="min-w-0 flex-1 space-y-0.5">
                                        <h3 className="text-foreground truncate text-sm font-bold">{data.school_name || "Institution Name"}</h3>
                                        <div className="text-muted-foreground flex flex-wrap items-center gap-1.5 text-xs">
                                            <span className="font-mono font-medium">{data.school_code || "CODE"}</span>
                                            <span>·</span>
                                            <span>{selectedCountryName}</span>
                                        </div>
                                    </div>
                                </div>

                                {/* Spec Badges */}
                                <div className="grid grid-cols-2 gap-2 pt-1 text-xs">
                                    <div className="border-border/60 bg-muted/20 rounded-xl border p-2.5">
                                        <span className="text-muted-foreground block text-[10px] font-semibold uppercase">Level</span>
                                        <span className="text-foreground block truncate font-medium">{selectedLevelLabel}</span>
                                    </div>

                                    <div className="border-border/60 bg-muted/20 rounded-xl border p-2.5">
                                        <span className="text-muted-foreground block text-[10px] font-semibold uppercase">Framework</span>
                                        <span className="text-foreground block truncate font-medium">
                                            {activeFramework ? activeFramework.authority : "Standard"}
                                        </span>
                                    </div>

                                    <div className="border-border/60 bg-muted/20 rounded-xl border p-2.5">
                                        <span className="text-muted-foreground block text-[10px] font-semibold uppercase">Academic Year</span>
                                        <span className="text-foreground block truncate font-mono font-medium">
                                            {data.curriculum_year ? `AY ${data.curriculum_year}` : "Pending"}
                                        </span>
                                    </div>

                                    <div className="border-border/60 bg-muted/20 rounded-xl border p-2.5">
                                        <span className="text-muted-foreground block text-[10px] font-semibold uppercase">Currency</span>
                                        <span className="text-foreground font-mono font-medium">{data.currency || "PHP"}</span>
                                    </div>
                                </div>

                                {/* Admin readout */}
                                <div className="border-border/60 bg-muted/30 flex items-center justify-between rounded-xl border p-3 text-xs">
                                    <div className="flex items-center gap-2">
                                        <User className="text-primary size-3.5" />
                                        <span className="text-muted-foreground max-w-[170px] truncate">
                                            {data.admin_email || "admin email pending"}
                                        </span>
                                    </div>
                                    <Badge variant="outline" size="xs">
                                        Super Admin
                                    </Badge>
                                </div>
                            </CardContent>
                        </Card>

                        {/* Launch Readiness Inspector */}
                        <Card className="border-border/80 bg-background overflow-hidden rounded-2xl shadow-xs">
                            <CardHeader className="border-border/60 border-b pb-3">
                                <div className="flex items-center justify-between">
                                    <span className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">Readiness Checklist</span>
                                    <span className="text-foreground text-xs font-semibold">{completedSectionsCount} / 4</span>
                                </div>
                            </CardHeader>

                            <CardContent className="space-y-3 p-5 text-xs">
                                {/* Checklist items with click-to-scroll */}
                                <button
                                    type="button"
                                    onClick={() => scrollToRef(adminRef)}
                                    className="hover:bg-muted/40 flex w-full items-center justify-between rounded-lg p-1.5 text-left transition-colors"
                                >
                                    <span className="text-foreground flex items-center gap-2 font-medium">
                                        {isStep1Complete ? (
                                            <CheckCircle2 className="size-4 shrink-0 text-emerald-500" />
                                        ) : (
                                            <span className="border-muted-foreground/40 size-4 shrink-0 rounded-full border" />
                                        )}
                                        Root Administrator
                                    </span>
                                    <span className="text-muted-foreground text-[10px]">{isStep1Complete ? "Configured" : "Required"}</span>
                                </button>

                                <button
                                    type="button"
                                    onClick={() => scrollToRef(institutionRef)}
                                    className="hover:bg-muted/40 flex w-full items-center justify-between rounded-lg p-1.5 text-left transition-colors"
                                >
                                    <span className="text-foreground flex items-center gap-2 font-medium">
                                        {isStep2Complete ? (
                                            <CheckCircle2 className="size-4 shrink-0 text-emerald-500" />
                                        ) : (
                                            <span className="border-muted-foreground/40 size-4 shrink-0 rounded-full border" />
                                        )}
                                        Institution & Region
                                    </span>
                                    <span className="text-muted-foreground text-[10px]">{isStep2Complete ? "Configured" : "Required"}</span>
                                </button>

                                <button
                                    type="button"
                                    onClick={() => scrollToRef(curriculumRef)}
                                    className="hover:bg-muted/40 flex w-full items-center justify-between rounded-lg p-1.5 text-left transition-colors"
                                >
                                    <span className="text-foreground flex items-center gap-2 font-medium">
                                        {isStep3Complete ? (
                                            <CheckCircle2 className="size-4 shrink-0 text-emerald-500" />
                                        ) : (
                                            <span className="border-muted-foreground/40 size-4 shrink-0 rounded-full border" />
                                        )}
                                        Curriculum Architecture
                                    </span>
                                    <span className="text-muted-foreground text-[10px]">
                                        {activeFramework ? activeFramework.authority : "Standard"}
                                    </span>
                                </button>

                                <button
                                    type="button"
                                    onClick={() => scrollToRef(academicRef)}
                                    className="hover:bg-muted/40 flex w-full items-center justify-between rounded-lg p-1.5 text-left transition-colors"
                                >
                                    <span className="text-foreground flex items-center gap-2 font-medium">
                                        {isStep4Complete ? (
                                            <CheckCircle2 className="size-4 shrink-0 text-emerald-500" />
                                        ) : (
                                            <span className="border-muted-foreground/40 size-4 shrink-0 rounded-full border" />
                                        )}
                                        Academic Dates & Currency
                                    </span>
                                    <span className="text-muted-foreground text-[10px]">{isStep4Complete ? "Configured" : "Required"}</span>
                                </button>

                                <div className="border-border/60 border-t pt-3">
                                    <Button
                                        type="button"
                                        onClick={handleSubmit}
                                        disabled={processing}
                                        className="h-10 w-full text-xs font-semibold shadow-xs transition-transform active:scale-[0.98]"
                                    >
                                        {processing ? "Initializing..." : isLaunchReady ? "Launch Platform" : "Complete Requirements"}
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </div>
    );
}
