import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from "@/components/ui/alert-dialog";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Textarea } from "@/components/ui/textarea";
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from "@/components/ui/tooltip";
import { cn } from "@/lib/utils";
import { update as updateCurriculumCapabilities } from "@/routes/administrators/system-management/schools/curriculum-capabilities";
import { router, useForm } from "@inertiajs/react";
import {
    AlertTriangle,
    BookOpen,
    Building2,
    Calendar,
    CalendarDays,
    Check,
    CheckCircle2,
    Clock,
    Compass,
    Globe,
    GraduationCap,
    Info,
    Layers,
    Loader2,
    Mail,
    MapPin,
    Pencil,
    Phone,
    Plus,
    Save,
    Search,
    Shield,
    Trash2,
    Users,
    X,
} from "lucide-react";
import { FormEvent, useEffect, useMemo, useState } from "react";
import { toast } from "sonner";
import { route } from "ziggy-js";

import { submitSystemForm } from "./form-submit";
import SystemManagementLayout from "./layout";
import type { CurriculumCapability, School, SystemManagementPageProps } from "./types";

interface CreateSchoolFormData {
    name: string;
    code: string;
    country_code: string;
    school_level: string;
    description: string;
    location: string;
    phone: string;
    email: string;
    dean_name: string;
    dean_email: string;
}

interface SchoolDetailsFormData {
    school_id: string;
    name: string;
    code: string;
    country_code: string;
    school_level: string;
    description: string;
    location: string;
    phone: string;
    email: string;
}

const SCHOOL_LEVEL_OPTIONS = [
    { value: "higher_education", label: "College / University", description: "Undergraduate, graduate, and university programs." },
    { value: "junior_high", label: "Junior High School", description: "Middle school or junior high school grades 7 to 10." },
    { value: "senior_high", label: "Senior High School", description: "Senior high school grades 11 to 12." },
    { value: "elementary", label: "Elementary School", description: "Elementary or primary school grades 1 to 6." },
    { value: "technical_vocational", label: "TESDA / Technical-Vocational", description: "Vocational education and training programs." },
];

const CURRICULUM_FRAMEWORKS = [
    { value: "ched_psg", label: "CHED-aligned degree programs", levels: ["higher_education"], badgeColor: "border-sky-500/30 bg-sky-500/10 text-sky-700 dark:text-sky-300" },
    { value: "deped_matatag", label: "DepEd MATATAG Curriculum", levels: ["elementary", "junior_high"], badgeColor: "border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300" },
    { value: "deped_shs_k12", label: "DepEd Senior High (K–12)", levels: ["senior_high"], badgeColor: "border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-300" },
    { value: "deped_shs_revised", label: "DepEd Revised SHS", levels: ["senior_high"], badgeColor: "border-violet-500/30 bg-violet-500/10 text-violet-700 dark:text-violet-300" },
    { value: "tesda_tr", label: "TESDA Training Regulations", levels: ["higher_education", "technical_vocational"], badgeColor: "border-rose-500/30 bg-rose-500/10 text-rose-700 dark:text-rose-300" },
];

const schoolLevelLabel = (value?: string | null): string =>
    SCHOOL_LEVEL_OPTIONS.find((option) => option.value === value)?.label ?? "Not configured";

export default function SystemManagementSchoolPage({
    user,
    active_school,
    schools,
    access,
    system_semester,
    system_school_starting_date,
    system_school_ending_date,
    available_semesters,
    registrar_reporting,
    curriculum_capabilities = [],
}: SystemManagementPageProps) {
    const [isAddSchoolOpen, setIsAddSchoolOpen] = useState(false);
    const [isEditSchoolOpen, setIsEditSchoolOpen] = useState(false);
    const [isSwitchSchoolOpen, setIsSwitchSchoolOpen] = useState(false);
    const [editingSchool, setEditingSchool] = useState<School | null>(null);
    const [deletingSchool, setDeletingSchool] = useState<School | null>(null);
    const [capabilities, setCapabilities] = useState<CurriculumCapability[]>(curriculum_capabilities);
    const [directorySearch, setDirectorySearch] = useState("");

    const canUpdate = access.sections.school?.can_update ?? false;

    const schoolForm = useForm({ school_id: active_school?.id?.toString() || "" });
    const schoolDetailsForm = useForm<SchoolDetailsFormData>({
        school_id: active_school?.id?.toString() || "",
        name: active_school?.name || "",
        code: active_school?.code || "",
        country_code: active_school?.country_code || "",
        school_level: active_school?.school_level || "",
        description: active_school?.description || "",
        location: active_school?.location || "",
        phone: active_school?.phone || "",
        email: active_school?.email || "",
    });

    const createSchoolForm = useForm<CreateSchoolFormData>({
        name: "",
        code: "",
        country_code: "",
        school_level: "",
        description: "",
        location: "",
        phone: "",
        email: "",
        dean_name: "",
        dean_email: "",
    });

    const editSchoolForm = useForm<CreateSchoolFormData>({
        name: "",
        code: "",
        country_code: "",
        school_level: "",
        description: "",
        location: "",
        phone: "",
        email: "",
        dean_name: "",
        dean_email: "",
    });

    const academicCalendarForm = useForm({
        semester: system_semester ?? 1,
        school_starting_date: system_school_starting_date ?? "",
        school_ending_date: system_school_ending_date ?? "",
        maximum_registrar_year_level: registrar_reporting.maximum_year_level,
    });

    useEffect(() => {
        setCapabilities(curriculum_capabilities);
    }, [curriculum_capabilities]);

    useEffect(() => {
        academicCalendarForm.setData({
            semester: system_semester ?? 1,
            school_starting_date: system_school_starting_date ?? "",
            school_ending_date: system_school_ending_date ?? "",
            maximum_registrar_year_level: registrar_reporting.maximum_year_level,
        });
    }, [registrar_reporting.maximum_year_level, system_semester, system_school_starting_date, system_school_ending_date]);

    useEffect(() => {
        if (!active_school) return;

        schoolDetailsForm.setData({
            school_id: active_school.id.toString(),
            name: active_school.name,
            code: active_school.code,
            country_code: active_school.country_code || "",
            school_level: active_school.school_level || "",
            description: active_school.description || "",
            location: active_school.location || "",
            phone: active_school.phone || "",
            email: active_school.email || "",
        });
        schoolForm.setData("school_id", active_school.id.toString());
    }, [active_school]);

    const saveCurriculumCapabilities = (): void => {
        if (!active_school) return;

        router.put(
            updateCurriculumCapabilities(active_school.id),
            {
                capabilities: capabilities.map((capability) => ({
                    school_level: capability.school_level,
                    curriculum_framework: capability.curriculum_framework,
                    curriculum_reference: capability.reference,
                })),
            },
            {
                preserveScroll: true,
                onSuccess: () => toast.success("Supported curriculum pathways updated."),
                onError: () => toast.error("Review the curriculum pathways and try again."),
            },
        );
    };

    const addCurriculumCapability = (): void => {
        const defaultLevel = active_school?.school_level || "higher_education";
        const framework = CURRICULUM_FRAMEWORKS.find((option) => option.levels.includes(defaultLevel)) ?? CURRICULUM_FRAMEWORKS[0];

        setCapabilities((current) => [
            ...current,
            {
                id: `new-${Date.now()}`,
                persisted_id: null,
                school_level: defaultLevel,
                school_level_label: schoolLevelLabel(defaultLevel),
                curriculum_framework: framework.value,
                framework_label: framework.label,
                reference: "",
                is_enabled: true,
                is_derived: false,
            },
        ]);
    };

    const handleCreateSchool = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        createSchoolForm.post(route("administrators.system-management.school.store"), {
            onSuccess: () => {
                toast.success("School created successfully.");
                setIsAddSchoolOpen(false);
                createSchoolForm.reset();
            },
            onError: () => toast.error("Failed to create school."),
        });
    };

    const openEditDialog = (school: School): void => {
        setEditingSchool(school);
        editSchoolForm.setData({
            name: school.name,
            code: school.code,
            country_code: school.country_code || "",
            school_level: school.school_level || "",
            description: school.description || "",
            location: school.location || "",
            phone: school.phone || "",
            email: school.email || "",
            dean_name: school.dean_name || "",
            dean_email: school.dean_email || "",
        });
        setIsEditSchoolOpen(true);
    };

    const handleUpdateSchool = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();
        if (!editingSchool) return;

        editSchoolForm.put(route("administrators.system-management.schools.update", editingSchool.id), {
            preserveScroll: true,
            onSuccess: () => {
                toast.success("School updated successfully.");
                setIsEditSchoolOpen(false);
                setEditingSchool(null);
                editSchoolForm.reset();
            },
            onError: () => toast.error("Failed to update school."),
        });
    };

    const handleToggleSchoolStatus = (school: School): void => {
        router.patch(
            route("administrators.system-management.schools.status.update", school.id),
            { is_active: !school.is_active },
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success(school.is_active ? "School deactivated successfully." : "School activated successfully.");
                },
                onError: () => toast.error("Failed to update school status."),
            },
        );
    };

    const handleDeleteSchool = (): void => {
        if (!deletingSchool) return;

        router.delete(route("administrators.system-management.schools.destroy", deletingSchool.id), {
            preserveScroll: true,
            onSuccess: () => {
                toast.success("School archived successfully.");
                setDeletingSchool(null);
            },
            onError: () => {
                toast.error("Unable to archive school.");
                setDeletingSchool(null);
            },
        });
    };

    const handleForceDeleteSchool = (): void => {
        if (!deletingSchool) return;

        router.delete(route("administrators.system-management.schools.force-destroy", deletingSchool.id), {
            preserveScroll: true,
            onSuccess: () => {
                toast.success("School permanently deleted with related records.");
                setDeletingSchool(null);
            },
            onError: () => {
                toast.error("Unable to force delete school.");
                setDeletingSchool(null);
            },
        });
    };

    // Calculate approximate school year duration
    const schoolYearDuration = useMemo(() => {
        if (!academicCalendarForm.data.school_starting_date || !academicCalendarForm.data.school_ending_date) return null;
        const start = new Date(academicCalendarForm.data.school_starting_date);
        const end = new Date(academicCalendarForm.data.school_ending_date);
        const diffTime = Math.abs(end.getTime() - start.getTime());
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
        const months = Math.round((diffDays / 30.4375) * 10) / 10;
        return `${months} months (${diffDays} days)`;
    }, [academicCalendarForm.data.school_starting_date, academicCalendarForm.data.school_ending_date]);

    // Filter directory schools
    const filteredSchools = useMemo(() => {
        const query = directorySearch.trim().toLowerCase();
        if (!query) return schools;
        return schools.filter(
            (s) =>
                s.name.toLowerCase().includes(query) ||
                s.code.toLowerCase().includes(query) ||
                (s.country_code && s.country_code.toLowerCase().includes(query)) ||
                (s.location && s.location.toLowerCase().includes(query)) ||
                (s.dean_name && s.dean_name.toLowerCase().includes(query)),
        );
    }, [schools, directorySearch]);

    return (
        <SystemManagementLayout
            user={user}
            access={access}
            activeSection="school"
            heading="Institution & Campus"
            description="Manage the operating campus, institutional profile, curriculum frameworks, and academic calendar."
        >
            <div className="space-y-6">
                {/* Active Campus Scope Bar */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 rounded-xl border border-border/60 bg-card/65 p-4 shadow-xs backdrop-blur-xs">
                    <div className="flex items-center gap-3.5 min-w-0">
                        <div className="flex size-11 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary border border-primary/20 shadow-xs">
                            <Building2 className="size-5" />
                        </div>

                        <div className="min-w-0">
                            <div className="flex flex-wrap items-center gap-2">
                                <span className="font-semibold text-sm text-foreground truncate">
                                    {active_school ? active_school.name : "No Active Campus"}
                                </span>
                                {active_school && (
                                    <>
                                        <Badge variant="outline" className="font-mono text-[10px] px-1.5 h-5 border-border/60">
                                            {active_school.code}
                                        </Badge>
                                        {active_school.country_code && (
                                            <Badge variant="outline" className="font-mono text-[10px] px-1.5 h-5 border-border/60">
                                                {active_school.country_code}
                                            </Badge>
                                        )}
                                        <Badge variant="outline" className="text-[10px] px-1.5 h-5 border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300">
                                            Operating Campus
                                        </Badge>
                                    </>
                                )}
                            </div>
                            <p className="text-xs text-muted-foreground mt-0.5 truncate">
                                {active_school?.location || "Campus address unconfigured"} • {schoolLevelLabel(active_school?.school_level)}
                            </p>
                        </div>
                    </div>

                    <div className="flex items-center gap-2 shrink-0 self-start sm:self-center">
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => setIsSwitchSchoolOpen(true)}
                            className="h-8.5 gap-1.5 text-xs bg-background/80"
                        >
                            <Compass className="size-3.5" />
                            <span>Switch Campus ({schools.length})</span>
                        </Button>

                        <Button
                            size="sm"
                            onClick={() => setIsAddSchoolOpen(true)}
                            disabled={!canUpdate}
                            className="h-8.5 gap-1.5 text-xs shadow-xs"
                        >
                            <Plus className="size-3.5" />
                            <span>Add Campus</span>
                        </Button>
                    </div>
                </div>

                {/* Main Tabbed Console */}
                <Tabs defaultValue="profile" className="space-y-6">
                    <TabsList className="h-9 p-1 bg-muted/50 border border-border/40">
                        <TabsTrigger value="profile" className="text-xs px-3.5">
                            Campus Profile
                        </TabsTrigger>
                        <TabsTrigger value="curriculum" className="text-xs px-3.5">
                            Curriculum Pathways ({capabilities.length})
                        </TabsTrigger>
                        <TabsTrigger value="calendar" className="text-xs px-3.5">
                            Academic Calendar
                        </TabsTrigger>
                        <TabsTrigger value="directory" className="text-xs px-3.5">
                            All Campuses ({schools.length})
                        </TabsTrigger>
                    </TabsList>

                    {/* Tab 1: Campus Profile */}
                    <TabsContent value="profile" className="mt-0 space-y-6">
                        {active_school ? (
                            <Card className="border-border/60 bg-card/70 shadow-xs backdrop-blur-xs">
                                <CardHeader className="flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-border/40 pb-4">
                                    <div>
                                        <CardTitle className="text-base font-semibold">Campus Profile & Legal Identity</CardTitle>
                                        <CardDescription className="text-xs mt-0.5">
                                            Official contact numbers, emails, physical address, and education tier for {active_school.name}.
                                        </CardDescription>
                                    </div>

                                    <Button
                                        onClick={() =>
                                            submitSystemForm({
                                                form: schoolDetailsForm,
                                                routeName: "administrators.system-management.school-details.update",
                                                successMessage: "Campus details updated successfully.",
                                                errorMessage: "Failed to update campus details.",
                                            })
                                        }
                                        disabled={schoolDetailsForm.processing || !schoolDetailsForm.isDirty || !canUpdate}
                                        className="h-9 gap-1.5 shrink-0 self-start sm:self-center shadow-xs"
                                    >
                                        {schoolDetailsForm.processing ? <Loader2 className="size-4 animate-spin" /> : <Save className="size-4" />}
                                        <span>Save Profile</span>
                                    </Button>
                                </CardHeader>

                                <CardContent className="pt-6 space-y-6">
                                    {/* Core Campus Identity */}
                                    <div className="space-y-4">
                                        <div className="flex items-center gap-2 border-b border-border/40 pb-2">
                                            <Building2 className="size-4 text-primary" />
                                            <h3 className="text-xs font-semibold text-foreground uppercase tracking-wider">
                                                Identity & Institutional Classification
                                            </h3>
                                        </div>

                                        <div className="grid gap-4 sm:grid-cols-3">
                                            <div className="space-y-1.5 sm:col-span-2">
                                                <Label htmlFor="school_name" className="text-xs font-semibold">
                                                    Institutional Name
                                                </Label>
                                                <Input
                                                    id="school_name"
                                                    value={schoolDetailsForm.data.name}
                                                    onChange={(e) => schoolDetailsForm.setData("name", e.target.value)}
                                                    placeholder="e.g. KoAkademy University - Main Campus"
                                                    className="text-sm font-medium"
                                                />
                                            </div>

                                            <div className="space-y-1.5">
                                                <Label htmlFor="school_code" className="text-xs font-semibold">
                                                    Campus Code
                                                </Label>
                                                <Input
                                                    id="school_code"
                                                    value={schoolDetailsForm.data.code}
                                                    onChange={(e) => schoolDetailsForm.setData("code", e.target.value.toUpperCase())}
                                                    placeholder="MAIN01"
                                                    className="font-mono text-sm uppercase"
                                                />
                                            </div>

                                            <div className="space-y-1.5 sm:col-span-2">
                                                <Label htmlFor="school_level" className="text-xs font-semibold">
                                                    Primary Education Tier
                                                </Label>
                                                <Select
                                                    value={schoolDetailsForm.data.school_level}
                                                    onValueChange={(val) => schoolDetailsForm.setData("school_level", val ?? "")}
                                                >
                                                    <SelectTrigger id="school_level" className="h-9 text-xs">
                                                        <SelectValue placeholder="Select primary level" />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        {SCHOOL_LEVEL_OPTIONS.map((opt) => (
                                                            <SelectItem key={opt.value} value={opt.value} className="text-xs">
                                                                <span className="font-semibold">{opt.label}</span>
                                                                <span className="text-muted-foreground ml-2 text-[11px]">— {opt.description}</span>
                                                            </SelectItem>
                                                        ))}
                                                    </SelectContent>
                                                </Select>
                                            </div>

                                            <div className="space-y-1.5">
                                                <Label htmlFor="school_country_code" className="text-xs font-semibold">
                                                    Country Code (ISO 3166-1)
                                                </Label>
                                                <Input
                                                    id="school_country_code"
                                                    value={schoolDetailsForm.data.country_code}
                                                    onChange={(e) => schoolDetailsForm.setData("country_code", e.target.value.toUpperCase().slice(0, 2))}
                                                    maxLength={2}
                                                    pattern="[A-Za-z]{2}"
                                                    autoCapitalize="characters"
                                                    placeholder="PH"
                                                    className="font-mono text-xs uppercase"
                                                />
                                            </div>

                                            <div className="space-y-1.5 sm:col-span-3">
                                                <Label htmlFor="school_description" className="text-xs font-semibold">
                                                    Institutional Description & Mission
                                                </Label>
                                                <Textarea
                                                    id="school_description"
                                                    value={schoolDetailsForm.data.description}
                                                    onChange={(e) => schoolDetailsForm.setData("description", e.target.value)}
                                                    rows={3}
                                                    placeholder="Brief overview of this school campus..."
                                                    className="text-xs resize-none"
                                                />
                                            </div>
                                        </div>
                                    </div>

                                    {/* Official Contact & Physical Location */}
                                    <div className="space-y-4 pt-2">
                                        <div className="flex items-center gap-2 border-b border-border/40 pb-2">
                                            <MapPin className="size-4 text-primary" />
                                            <h3 className="text-xs font-semibold text-foreground uppercase tracking-wider">
                                                Contact & Physical Location
                                            </h3>
                                        </div>

                                        <div className="grid gap-4 sm:grid-cols-2">
                                            <div className="space-y-1.5">
                                                <Label htmlFor="school_phone" className="text-xs font-semibold">
                                                    Official Phone / Trunkline
                                                </Label>
                                                <div className="relative">
                                                    <Phone className="pointer-events-none absolute top-1/2 left-3 size-3.5 -translate-y-1/2 text-muted-foreground" />
                                                    <Input
                                                        id="school_phone"
                                                        value={schoolDetailsForm.data.phone}
                                                        onChange={(e) => schoolDetailsForm.setData("phone", e.target.value)}
                                                        placeholder="+63 2 8000 0000"
                                                        className="pl-8 text-xs font-mono"
                                                    />
                                                </div>
                                            </div>

                                            <div className="space-y-1.5">
                                                <Label htmlFor="school_email" className="text-xs font-semibold">
                                                    Official Inquiries Email
                                                </Label>
                                                <div className="relative">
                                                    <Mail className="pointer-events-none absolute top-1/2 left-3 size-3.5 -translate-y-1/2 text-muted-foreground" />
                                                    <Input
                                                        id="school_email"
                                                        type="email"
                                                        value={schoolDetailsForm.data.email}
                                                        onChange={(e) => schoolDetailsForm.setData("email", e.target.value)}
                                                        placeholder="registrar@school.edu.ph"
                                                        className="pl-8 text-xs font-mono"
                                                    />
                                                </div>
                                            </div>

                                            <div className="space-y-1.5 sm:col-span-2">
                                                <Label htmlFor="school_location" className="text-xs font-semibold">
                                                    Campus Street Address & City
                                                </Label>
                                                <div className="relative">
                                                    <MapPin className="pointer-events-none absolute top-1/2 left-3 size-3.5 -translate-y-1/2 text-muted-foreground" />
                                                    <Input
                                                        id="school_location"
                                                        value={schoolDetailsForm.data.location}
                                                        onChange={(e) => schoolDetailsForm.setData("location", e.target.value)}
                                                        placeholder="University Avenue, Metro Manila, Philippines"
                                                        className="pl-8 text-xs"
                                                    />
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        ) : (
                            <Card className="border-dashed border-border/80 p-8 text-center">
                                <Building2 className="mx-auto size-8 text-muted-foreground opacity-60" />
                                <h3 className="mt-3 text-sm font-semibold">No Active School Selected</h3>
                                <p className="mt-1 text-xs text-muted-foreground">Select a campus to review and configure its profile.</p>
                            </Card>
                        )}
                    </TabsContent>

                    {/* Tab 2: Curriculum Pathways */}
                    <TabsContent value="curriculum" className="mt-0 space-y-6">
                        <Card className="border-border/60 bg-card/70 shadow-xs backdrop-blur-xs">
                            <CardHeader className="flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-border/40 pb-4">
                                <div>
                                    <div className="flex items-center gap-2">
                                        <CardTitle className="text-base font-semibold">Supported Curriculum Pathways</CardTitle>
                                        <Badge variant="outline" className="text-xs border-border/60">
                                            {capabilities.length} Frameworks
                                        </Badge>
                                    </div>
                                    <CardDescription className="text-xs mt-0.5">
                                        Authorizes program creation rules for CHED degree courses, DepEd MATATAG, K-12 SHS, and TESDA regulations.
                                    </CardDescription>
                                </div>

                                <div className="flex items-center gap-2 self-start sm:self-center">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={addCurriculumCapability}
                                        disabled={!active_school || !canUpdate}
                                        className="h-8.5 gap-1.5 text-xs bg-background/80"
                                    >
                                        <Plus className="size-3.5" />
                                        <span>Add Pathway</span>
                                    </Button>

                                    <Button
                                        type="button"
                                        size="sm"
                                        onClick={saveCurriculumCapabilities}
                                        disabled={!active_school || !canUpdate}
                                        className="h-8.5 gap-1.5 text-xs shadow-xs"
                                    >
                                        <Save className="size-3.5" />
                                        <span>Save Pathways</span>
                                    </Button>
                                </div>
                            </CardHeader>

                            <CardContent className="p-0 divide-y divide-border/40">
                                {capabilities.length === 0 ? (
                                    <div className="p-8 text-center text-xs text-muted-foreground">
                                        No supported curriculum pathways registered for this campus. Click &quot;Add Pathway&quot; to authorize program frameworks.
                                    </div>
                                ) : (
                                    capabilities.map((capability, index) => {
                                        const frameworkOptions = CURRICULUM_FRAMEWORKS.filter((framework) =>
                                            framework.levels.includes(capability.school_level),
                                        );
                                        const matchedFramework = CURRICULUM_FRAMEWORKS.find((f) => f.value === capability.curriculum_framework);

                                        return (
                                            <div
                                                key={capability.id}
                                                className="grid gap-3.5 p-4 sm:grid-cols-[1fr_1.2fr_1.5fr_auto] sm:items-end hover:bg-muted/20 transition-colors"
                                            >
                                                <div className="space-y-1.5">
                                                    <Label className="text-[11px] font-semibold text-muted-foreground uppercase tracking-wider">
                                                        Education Level
                                                    </Label>
                                                    <Select
                                                        value={capability.school_level}
                                                        onValueChange={(value) => {
                                                            const nextFramework = CURRICULUM_FRAMEWORKS.find((framework) =>
                                                                framework.levels.includes(value ?? ""),
                                                            );
                                                            setCapabilities((current) =>
                                                                current.map((item, itemIndex) =>
                                                                    itemIndex === index
                                                                        ? {
                                                                              ...item,
                                                                              school_level: value ?? item.school_level,
                                                                              school_level_label: schoolLevelLabel(value),
                                                                              curriculum_framework: nextFramework?.value ?? item.curriculum_framework,
                                                                              framework_label: nextFramework?.label ?? item.framework_label,
                                                                          }
                                                                        : item,
                                                                ),
                                                            );
                                                        }}
                                                    >
                                                        <SelectTrigger className="h-8.5 text-xs bg-background">
                                                            <SelectValue />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            {SCHOOL_LEVEL_OPTIONS.map((option) => (
                                                                <SelectItem key={option.value} value={option.value} className="text-xs">
                                                                    {option.label}
                                                                </SelectItem>
                                                            ))}
                                                        </SelectContent>
                                                    </Select>
                                                </div>

                                                <div className="space-y-1.5">
                                                    <div className="flex items-center justify-between">
                                                        <Label className="text-[11px] font-semibold text-muted-foreground uppercase tracking-wider">
                                                            Regulatory Framework
                                                        </Label>
                                                        {matchedFramework && (
                                                            <Badge variant="outline" className={cn("text-[9.5px] px-1 h-4", matchedFramework.badgeColor)}>
                                                                Standard
                                                            </Badge>
                                                        )}
                                                    </div>
                                                    <Select
                                                        value={capability.curriculum_framework}
                                                        onValueChange={(value) =>
                                                            setCapabilities((current) =>
                                                                current.map((item, itemIndex) =>
                                                                    itemIndex === index
                                                                        ? { ...item, curriculum_framework: value ?? item.curriculum_framework }
                                                                        : item,
                                                                ),
                                                            )
                                                        }
                                                    >
                                                        <SelectTrigger className="h-8.5 text-xs bg-background">
                                                            <SelectValue />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            {frameworkOptions.map((option) => (
                                                                <SelectItem key={option.value} value={option.value} className="text-xs">
                                                                    {option.label}
                                                                </SelectItem>
                                                            ))}
                                                        </SelectContent>
                                                    </Select>
                                                </div>

                                                <div className="space-y-1.5">
                                                    <Label className="text-[11px] font-semibold text-muted-foreground uppercase tracking-wider">
                                                        Regulatory Reference / DepEd Order
                                                    </Label>
                                                    <Input
                                                        value={capability.reference}
                                                        onChange={(event) =>
                                                            setCapabilities((current) =>
                                                                current.map((item, itemIndex) =>
                                                                    itemIndex === index ? { ...item, reference: event.target.value } : item,
                                                                ),
                                                            )
                                                        }
                                                        placeholder="CMO No. 25, DepEd Order 21, TESDA TR"
                                                        className="h-8.5 text-xs font-mono"
                                                    />
                                                </div>

                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon"
                                                    onClick={() => setCapabilities((current) => current.filter((_, itemIndex) => itemIndex !== index))}
                                                    className="size-8 text-muted-foreground hover:text-destructive hover:bg-destructive/10"
                                                    aria-label="Remove pathway"
                                                >
                                                    <Trash2 className="size-3.5" />
                                                </Button>
                                            </div>
                                        );
                                    })
                                )}
                            </CardContent>
                        </Card>
                    </TabsContent>

                    {/* Tab 3: Academic Calendar Defaults */}
                    <TabsContent value="calendar" className="mt-0 space-y-6">
                        <Card className="border-border/60 bg-card/70 shadow-xs backdrop-blur-xs">
                            <CardHeader className="flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-border/40 pb-4">
                                <div className="flex items-start gap-3">
                                    <div className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary mt-0.5">
                                        <Calendar className="size-5" />
                                    </div>
                                    <div>
                                        <CardTitle className="text-base font-semibold">Academic Calendar Defaults</CardTitle>
                                        <CardDescription className="text-xs mt-0.5">
                                            System-wide standard term, starting/ending boundaries, and registrar year progression depth.
                                        </CardDescription>
                                    </div>
                                </div>

                                <Button
                                    onClick={() =>
                                        submitSystemForm({
                                            form: academicCalendarForm,
                                            routeName: "administrators.system-management.academic-calendar.update",
                                            successMessage: "Academic calendar defaults saved successfully.",
                                            errorMessage: "Failed to update academic calendar defaults.",
                                        })
                                    }
                                    disabled={
                                        academicCalendarForm.processing ||
                                        (!academicCalendarForm.isDirty &&
                                            academicCalendarForm.data.semester === (system_semester ?? 1) &&
                                            academicCalendarForm.data.school_starting_date === (system_school_starting_date ?? "") &&
                                            academicCalendarForm.data.school_ending_date === (system_school_ending_date ?? "") &&
                                            academicCalendarForm.data.maximum_registrar_year_level === registrar_reporting.maximum_year_level) ||
                                        !canUpdate
                                    }
                                    className="h-9 gap-1.5 shrink-0 self-start sm:self-center shadow-xs"
                                >
                                    {academicCalendarForm.processing ? <Loader2 className="size-4 animate-spin" /> : <Save className="size-4" />}
                                    <span>Save Calendar Defaults</span>
                                </Button>
                            </CardHeader>

                            <CardContent className="pt-6 space-y-6">
                                <div className="grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
                                    <div className="space-y-1.5 rounded-xl border border-border/50 bg-background/50 p-4">
                                        <Label htmlFor="system_semester" className="text-xs font-semibold text-foreground">
                                            Default System Semester
                                        </Label>
                                        <Select
                                            value={academicCalendarForm.data.semester.toString()}
                                            onValueChange={(value) => academicCalendarForm.setData("semester", parseInt(value ?? "1"))}
                                        >
                                            <SelectTrigger id="system_semester" className="h-9 text-xs bg-background">
                                                <SelectValue placeholder="Select semester" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {Object.entries(available_semesters ?? {}).map(([key, label]) => (
                                                    <SelectItem key={key} value={key} className="text-xs">
                                                        {label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <p className="text-[11px] text-muted-foreground mt-1">Pre-selected for new classes and enrollments.</p>
                                    </div>

                                    <div className="space-y-1.5 rounded-xl border border-border/50 bg-background/50 p-4">
                                        <Label htmlFor="school_starting_date" className="text-xs font-semibold text-foreground">
                                            School Year Opening Date
                                        </Label>
                                        <div className="relative">
                                            <CalendarDays className="pointer-events-none absolute top-1/2 left-3 size-3.5 -translate-y-1/2 text-muted-foreground" />
                                            <Input
                                                id="school_starting_date"
                                                type="date"
                                                value={academicCalendarForm.data.school_starting_date}
                                                onChange={(e) => academicCalendarForm.setData("school_starting_date", e.target.value)}
                                                className="pl-8 text-xs font-mono"
                                            />
                                        </div>
                                        <p className="text-[11px] text-muted-foreground mt-1">First day of instruction for current term.</p>
                                    </div>

                                    <div className="space-y-1.5 rounded-xl border border-border/50 bg-background/50 p-4">
                                        <Label htmlFor="school_ending_date" className="text-xs font-semibold text-foreground">
                                            School Year Closing Date
                                        </Label>
                                        <div className="relative">
                                            <CalendarDays className="pointer-events-none absolute top-1/2 left-3 size-3.5 -translate-y-1/2 text-muted-foreground" />
                                            <Input
                                                id="school_ending_date"
                                                type="date"
                                                value={academicCalendarForm.data.school_ending_date}
                                                onChange={(e) => academicCalendarForm.setData("school_ending_date", e.target.value)}
                                                className="pl-8 text-xs font-mono"
                                            />
                                        </div>
                                        <p className="text-[11px] text-muted-foreground mt-1">
                                            {schoolYearDuration ? `Cycle: ${schoolYearDuration}` : "End of academic period."}
                                        </p>
                                    </div>

                                    <div className="space-y-1.5 rounded-xl border border-border/50 bg-background/50 p-4">
                                        <Label htmlFor="maximum_registrar_year_level" className="text-xs font-semibold text-foreground">
                                            Highest Registrar Year Level
                                        </Label>
                                        <Input
                                            id="maximum_registrar_year_level"
                                            type="number"
                                            min="2"
                                            max="7"
                                            value={academicCalendarForm.data.maximum_registrar_year_level}
                                            onChange={(e) =>
                                                academicCalendarForm.setData("maximum_registrar_year_level", Number(e.target.value || 4))
                                            }
                                            className="h-9 font-mono text-sm"
                                        />
                                        <p className="text-[11px] text-muted-foreground mt-1">Standard: 4 (College) or 5 (Engineering/Arch).</p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </TabsContent>

                    {/* Tab 4: All Campuses Directory */}
                    <TabsContent value="directory" className="mt-0 space-y-5">
                        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                            <div className="relative w-full sm:max-w-xs">
                                <Search className="pointer-events-none absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    value={directorySearch}
                                    onChange={(e) => setDirectorySearch(e.target.value)}
                                    placeholder="Search campuses by name, code, dean..."
                                    className="h-8.5 pl-8 pr-7 text-xs bg-background/80"
                                />
                                {directorySearch && (
                                    <button
                                        onClick={() => setDirectorySearch("")}
                                        className="absolute top-1/2 right-2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                                    >
                                        <X className="size-3" />
                                    </button>
                                )}
                            </div>

                            <Badge variant="outline" className="text-xs font-mono h-6 border-border/60 self-end sm:self-center">
                                Showing {filteredSchools.length} of {schools.length} Campuses
                            </Badge>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            {filteredSchools.map((school) => {
                                const isOperating = active_school?.id === school.id;

                                return (
                                    <Card
                                        key={school.id}
                                        className={cn(
                                            "flex flex-col justify-between rounded-xl border border-border/60 bg-card/70 shadow-xs transition-all hover:border-border hover:shadow-md",
                                            isOperating && "border-primary/50 ring-1 ring-primary/20",
                                        )}
                                    >
                                        <CardHeader className="p-4.5 pb-3">
                                            <div className="flex items-start justify-between gap-2">
                                                <div className="min-w-0">
                                                    <div className="flex items-center gap-1.5">
                                                        <h3 className="font-semibold text-sm text-foreground truncate">{school.name}</h3>
                                                        {isOperating && (
                                                            <TooltipProvider delay={150}>
                                                                <Tooltip>
                                                                    <TooltipTrigger
                                                                        render={
                                                                            <span className="flex size-4 items-center justify-center rounded-full bg-primary text-primary-foreground">
                                                                                <Check className="size-2.5" />
                                                                            </span>
                                                                        }
                                                                    />
                                                                    <TooltipContent className="text-xs">Active operating campus</TooltipContent>
                                                                </Tooltip>
                                                            </TooltipProvider>
                                                        )}
                                                    </div>

                                                    <div className="mt-1.5 flex flex-wrap items-center gap-1.5">
                                                        <Badge variant="outline" className="font-mono text-[10px] px-1.5 h-4.5">
                                                            {school.code}
                                                        </Badge>
                                                        {school.country_code && (
                                                            <Badge variant="outline" className="font-mono text-[10px] px-1.5 h-4.5 border-border/60">
                                                                {school.country_code}
                                                            </Badge>
                                                        )}
                                                        <Badge
                                                            variant={school.is_active ? "outline" : "secondary"}
                                                            className={cn(
                                                                "text-[10px] px-1.5 h-4.5 font-normal",
                                                                school.is_active
                                                                    ? "border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300"
                                                                    : "border-border/60 text-muted-foreground",
                                                            )}
                                                        >
                                                            {school.is_active ? "Active" : "Inactive"}
                                                        </Badge>
                                                        <Badge variant="outline" className="text-[10px] px-1.5 h-4.5 border-border/60">
                                                            {schoolLevelLabel(school.school_level)}
                                                        </Badge>
                                                    </div>
                                                </div>
                                            </div>
                                        </CardHeader>

                                        <CardContent className="px-4.5 py-1 text-xs text-muted-foreground space-y-2">
                                            <p className="line-clamp-2 leading-relaxed">{school.description || "No description provided."}</p>

                                            <div className="pt-2 border-t border-border/30 space-y-1 text-[11px]">
                                                <div className="flex items-center gap-2 truncate">
                                                    <MapPin className="size-3 text-muted-foreground shrink-0" />
                                                    <span className="truncate">{school.location || "Location unset"}</span>
                                                </div>
                                                <div className="flex items-center gap-2 truncate">
                                                    <Mail className="size-3 text-muted-foreground shrink-0" />
                                                    <span className="truncate font-mono">{school.email || "No email"}</span>
                                                </div>
                                                {school.dean_name && (
                                                    <div className="flex items-center gap-2 truncate">
                                                        <GraduationCap className="size-3 text-muted-foreground shrink-0" />
                                                        <span className="truncate font-medium text-foreground/80">{school.dean_name}</span>
                                                    </div>
                                                )}
                                            </div>
                                        </CardContent>

                                        <div className="mt-4 flex items-center justify-between gap-2 border-t border-border/40 bg-muted/20 p-2.5">
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={() => openEditDialog(school)}
                                                disabled={!canUpdate}
                                                className="h-7.5 px-2.5 text-xs flex-1 bg-background"
                                            >
                                                <Pencil className="size-3 mr-1" />
                                                Edit
                                            </Button>

                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={() => handleToggleSchoolStatus(school)}
                                                disabled={!canUpdate}
                                                className="h-7.5 px-2.5 text-xs flex-1 bg-background"
                                            >
                                                {school.is_active ? "Deactivate" : "Activate"}
                                            </Button>

                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                onClick={() => setDeletingSchool(school)}
                                                disabled={schools.length <= 1 || !canUpdate}
                                                className="size-7.5 text-muted-foreground hover:text-destructive hover:bg-destructive/10 shrink-0"
                                                title="Archive Campus"
                                            >
                                                <Trash2 className="size-3.5" />
                                            </Button>
                                        </div>
                                    </Card>
                                );
                            })}
                        </div>
                    </TabsContent>
                </Tabs>

                {/* Switch Active Campus Dialog */}
                <Dialog open={isSwitchSchoolOpen} onOpenChange={setIsSwitchSchoolOpen}>
                    <DialogContent className="sm:max-w-[480px]">
                        <DialogHeader>
                            <DialogTitle className="text-base font-semibold">Switch Active Campus Context</DialogTitle>
                            <DialogDescription className="text-xs">
                                Select the active school instance to manage its students, billing, and programs.
                            </DialogDescription>
                        </DialogHeader>

                        <div className="space-y-2 max-h-[340px] overflow-y-auto pr-1 py-2">
                            {schools.map((school) => {
                                const isSelected = schoolForm.data.school_id === school.id.toString();

                                return (
                                    <button
                                        key={school.id}
                                        type="button"
                                        onClick={() => schoolForm.setData("school_id", school.id.toString())}
                                        className={cn(
                                            "w-full flex items-center justify-between rounded-xl border p-3 text-left transition-all outline-none",
                                            isSelected
                                                ? "border-primary bg-primary/5 shadow-xs ring-1 ring-primary/20"
                                                : "border-border/60 hover:bg-muted/60",
                                        )}
                                    >
                                        <div className="flex items-center gap-3 min-w-0">
                                            <div
                                                className={cn(
                                                    "flex size-9 shrink-0 items-center justify-center rounded-lg transition-colors",
                                                    isSelected ? "bg-primary text-primary-foreground" : "bg-muted text-muted-foreground",
                                                )}
                                            >
                                                <Building2 className="size-4" />
                                            </div>
                                            <div className="min-w-0">
                                                <p className="font-semibold text-xs text-foreground truncate">{school.name}</p>
                                                <div className="flex items-center gap-1.5 mt-0.5">
                                                    <span className="font-mono text-[10px] text-muted-foreground">{school.code}</span>
                                                    {school.country_code && (
                                                        <>
                                                            <span className="text-muted-foreground/50">•</span>
                                                            <span className="font-mono text-[10px] text-muted-foreground">{school.country_code}</span>
                                                        </>
                                                    )}
                                                    <span className="text-muted-foreground/50">•</span>
                                                    <span className="text-[10px] text-muted-foreground">{schoolLevelLabel(school.school_level)}</span>
                                                </div>
                                            </div>
                                        </div>

                                        {isSelected && (
                                            <span className="flex size-5 shrink-0 items-center justify-center rounded-full bg-primary text-primary-foreground shadow-xs">
                                                <Check className="size-3" />
                                            </span>
                                        )}
                                    </button>
                                );
                            })}
                        </div>

                        <DialogFooter className="border-t border-border/40 pt-3">
                            <Button type="button" variant="outline" size="sm" onClick={() => setIsSwitchSchoolOpen(false)} className="h-8 text-xs">
                                Cancel
                            </Button>
                            <Button
                                size="sm"
                                onClick={() => {
                                    submitSystemForm({
                                        form: schoolForm,
                                        routeName: "administrators.system-management.school.update",
                                        successMessage: "Operating campus context updated.",
                                        errorMessage: "Failed to update operating campus.",
                                    });
                                    setIsSwitchSchoolOpen(false);
                                }}
                                disabled={schoolForm.processing || schoolForm.data.school_id === active_school?.id?.toString() || !canUpdate}
                                className="h-8 gap-1.5 text-xs"
                            >
                                {schoolForm.processing ? <Loader2 className="size-3.5 animate-spin" /> : <Check className="size-3.5" />}
                                <span>Apply Campus Selection</span>
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>

                {/* Create Campus Dialog */}
                <Dialog open={isAddSchoolOpen} onOpenChange={setIsAddSchoolOpen}>
                    <DialogContent className="sm:max-w-[540px]">
                        <DialogHeader>
                            <DialogTitle className="text-base font-semibold">Register New Campus Profile</DialogTitle>
                            <DialogDescription className="text-xs">
                                Add another school or branch campus to your multi-institution workspace.
                            </DialogDescription>
                        </DialogHeader>
                        <form onSubmit={handleCreateSchool} className="space-y-4 pt-2">
                            <div className="grid gap-3 sm:grid-cols-3">
                                <div className="space-y-1.5 sm:col-span-2">
                                    <Label htmlFor="new_school_name" className="text-xs font-semibold">
                                        Campus Name
                                    </Label>
                                    <Input
                                        id="new_school_name"
                                        value={createSchoolForm.data.name}
                                        onChange={(e) => createSchoolForm.setData("name", e.target.value)}
                                        placeholder="KoAkademy - South Campus"
                                        required
                                        className="text-xs"
                                    />
                                </div>
                                <div className="space-y-1.5">
                                    <Label htmlFor="new_school_code" className="text-xs font-semibold">
                                        Code
                                    </Label>
                                    <Input
                                        id="new_school_code"
                                        value={createSchoolForm.data.code}
                                        onChange={(e) => createSchoolForm.setData("code", e.target.value.toUpperCase())}
                                        placeholder="STH02"
                                        required
                                        className="uppercase font-mono text-xs"
                                    />
                                </div>
                            </div>

                            <div className="grid gap-3 sm:grid-cols-3">
                                <div className="space-y-1.5 sm:col-span-2">
                                    <Label htmlFor="new_school_level" className="text-xs font-semibold">
                                        Education Level
                                    </Label>
                                    <Select
                                        value={createSchoolForm.data.school_level}
                                        onValueChange={(val) => createSchoolForm.setData("school_level", val ?? "")}
                                    >
                                        <SelectTrigger id="new_school_level" className="h-8.5 text-xs">
                                            <SelectValue placeholder="Select primary level" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {SCHOOL_LEVEL_OPTIONS.map((opt) => (
                                                <SelectItem key={opt.value} value={opt.value} className="text-xs">
                                                    {opt.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div className="space-y-1.5">
                                    <Label htmlFor="new_school_country_code" className="text-xs font-semibold">
                                        Country Code
                                    </Label>
                                    <Input
                                        id="new_school_country_code"
                                        value={createSchoolForm.data.country_code}
                                        onChange={(e) => createSchoolForm.setData("country_code", e.target.value.toUpperCase().slice(0, 2))}
                                        maxLength={2}
                                        pattern="[A-Za-z]{2}"
                                        autoCapitalize="characters"
                                        placeholder="PH"
                                        className="font-mono text-xs uppercase"
                                    />
                                </div>
                            </div>

                            <div className="space-y-1.5">
                                <Label htmlFor="new_school_description" className="text-xs font-semibold">
                                    Campus Description
                                </Label>
                                <Textarea
                                    id="new_school_description"
                                    value={createSchoolForm.data.description}
                                    onChange={(e) => createSchoolForm.setData("description", e.target.value)}
                                    rows={2}
                                    placeholder="Brief details about this campus..."
                                    className="text-xs resize-none"
                                />
                            </div>

                            <div className="grid gap-3 sm:grid-cols-2">
                                <div className="space-y-1.5">
                                    <Label htmlFor="new_school_phone" className="text-xs font-semibold">
                                        Phone Number
                                    </Label>
                                    <Input
                                        id="new_school_phone"
                                        value={createSchoolForm.data.phone}
                                        onChange={(e) => createSchoolForm.setData("phone", e.target.value)}
                                        placeholder="+63 2 8000 0000"
                                        className="text-xs font-mono"
                                    />
                                </div>
                                <div className="space-y-1.5">
                                    <Label htmlFor="new_school_email" className="text-xs font-semibold">
                                        Email Address
                                    </Label>
                                    <Input
                                        id="new_school_email"
                                        type="email"
                                        value={createSchoolForm.data.email}
                                        onChange={(e) => createSchoolForm.setData("email", e.target.value)}
                                        placeholder="campus@school.edu.ph"
                                        className="text-xs font-mono"
                                    />
                                </div>
                            </div>

                            <div className="space-y-1.5">
                                <Label htmlFor="new_school_location" className="text-xs font-semibold">
                                    Physical Address / Location
                                </Label>
                                <Input
                                    id="new_school_location"
                                    value={createSchoolForm.data.location}
                                    onChange={(e) => createSchoolForm.setData("location", e.target.value)}
                                    placeholder="City, Province"
                                    className="text-xs"
                                />
                            </div>

                            <div className="grid gap-3 sm:grid-cols-2">
                                <div className="space-y-1.5">
                                    <Label htmlFor="new_school_dean_name" className="text-xs font-semibold">
                                        Dean / Administrator Name
                                    </Label>
                                    <Input
                                        id="new_school_dean_name"
                                        value={createSchoolForm.data.dean_name}
                                        onChange={(e) => createSchoolForm.setData("dean_name", e.target.value)}
                                        placeholder="Dr. John Doe"
                                        className="text-xs"
                                    />
                                </div>
                                <div className="space-y-1.5">
                                    <Label htmlFor="new_school_dean_email" className="text-xs font-semibold">
                                        Dean Email
                                    </Label>
                                    <Input
                                        id="new_school_dean_email"
                                        type="email"
                                        value={createSchoolForm.data.dean_email}
                                        onChange={(e) => createSchoolForm.setData("dean_email", e.target.value)}
                                        placeholder="dean@school.edu.ph"
                                        className="text-xs font-mono"
                                    />
                                </div>
                            </div>

                            <DialogFooter className="border-t border-border/40 pt-3">
                                <Button type="button" variant="outline" size="sm" onClick={() => setIsAddSchoolOpen(false)} className="h-8 text-xs">
                                    Cancel
                                </Button>
                                <Button
                                    type="submit"
                                    size="sm"
                                    disabled={createSchoolForm.processing || !createSchoolForm.data.school_level || !canUpdate}
                                    className="h-8 gap-1.5 text-xs"
                                >
                                    {createSchoolForm.processing ? <Loader2 className="size-3.5 animate-spin" /> : <Check className="size-3.5" />}
                                    <span>Create Campus</span>
                                </Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>

                {/* Edit Campus Dialog */}
                <Dialog open={isEditSchoolOpen} onOpenChange={setIsEditSchoolOpen}>
                    <DialogContent className="sm:max-w-[580px]">
                        <DialogHeader>
                            <DialogTitle className="text-base font-semibold">Edit Campus Profile</DialogTitle>
                            <DialogDescription className="text-xs">
                                Update official information for {editingSchool?.name}.
                            </DialogDescription>
                        </DialogHeader>

                        <form onSubmit={handleUpdateSchool} className="space-y-4 pt-2">
                            <div className="grid gap-3 sm:grid-cols-3">
                                <div className="space-y-1.5 sm:col-span-2">
                                    <Label htmlFor="edit_school_name" className="text-xs font-semibold">
                                        Campus Name
                                    </Label>
                                    <Input
                                        id="edit_school_name"
                                        value={editSchoolForm.data.name}
                                        onChange={(e) => editSchoolForm.setData("name", e.target.value)}
                                        required
                                        className="text-xs font-medium"
                                    />
                                </div>
                                <div className="space-y-1.5">
                                    <Label htmlFor="edit_school_code" className="text-xs font-semibold">
                                        Campus Code
                                    </Label>
                                    <Input
                                        id="edit_school_code"
                                        value={editSchoolForm.data.code}
                                        onChange={(e) => editSchoolForm.setData("code", e.target.value.toUpperCase())}
                                        required
                                        className="uppercase font-mono text-xs"
                                    />
                                </div>
                            </div>

                            <div className="grid gap-3 sm:grid-cols-3">
                                <div className="space-y-1.5 sm:col-span-2">
                                    <Label htmlFor="edit_school_level" className="text-xs font-semibold">
                                        Education Level
                                    </Label>
                                    <Select
                                        value={editSchoolForm.data.school_level}
                                        onValueChange={(val) => editSchoolForm.setData("school_level", val ?? "")}
                                    >
                                        <SelectTrigger id="edit_school_level" className="h-8.5 text-xs">
                                            <SelectValue placeholder="Select primary level" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {SCHOOL_LEVEL_OPTIONS.map((opt) => (
                                                <SelectItem key={opt.value} value={opt.value} className="text-xs">
                                                    {opt.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div className="space-y-1.5">
                                    <Label htmlFor="edit_school_country_code" className="text-xs font-semibold">
                                        Country Code
                                    </Label>
                                    <Input
                                        id="edit_school_country_code"
                                        value={editSchoolForm.data.country_code}
                                        onChange={(e) => editSchoolForm.setData("country_code", e.target.value.toUpperCase().slice(0, 2))}
                                        maxLength={2}
                                        pattern="[A-Za-z]{2}"
                                        autoCapitalize="characters"
                                        placeholder="PH"
                                        className="font-mono text-xs uppercase"
                                    />
                                </div>
                            </div>

                            <div className="space-y-1.5">
                                <Label htmlFor="edit_school_description" className="text-xs font-semibold">
                                    Description
                                </Label>
                                <Textarea
                                    id="edit_school_description"
                                    value={editSchoolForm.data.description}
                                    onChange={(e) => editSchoolForm.setData("description", e.target.value)}
                                    rows={2}
                                    className="text-xs resize-none"
                                />
                            </div>

                            <div className="grid gap-3 sm:grid-cols-2">
                                <div className="space-y-1.5">
                                    <Label htmlFor="edit_school_phone" className="text-xs font-semibold">
                                        Phone Number
                                    </Label>
                                    <Input
                                        id="edit_school_phone"
                                        value={editSchoolForm.data.phone}
                                        onChange={(e) => editSchoolForm.setData("phone", e.target.value)}
                                        className="text-xs font-mono"
                                    />
                                </div>
                                <div className="space-y-1.5">
                                    <Label htmlFor="edit_school_email" className="text-xs font-semibold">
                                        Email Address
                                    </Label>
                                    <Input
                                        id="edit_school_email"
                                        type="email"
                                        value={editSchoolForm.data.email}
                                        onChange={(e) => editSchoolForm.setData("email", e.target.value)}
                                        className="text-xs font-mono"
                                    />
                                </div>
                            </div>

                            <div className="space-y-1.5">
                                <Label htmlFor="edit_school_location" className="text-xs font-semibold">
                                    Campus Location
                                </Label>
                                <Input
                                    id="edit_school_location"
                                    value={editSchoolForm.data.location}
                                    onChange={(e) => editSchoolForm.setData("location", e.target.value)}
                                    className="text-xs"
                                />
                            </div>

                            <div className="grid gap-3 sm:grid-cols-2">
                                <div className="space-y-1.5">
                                    <Label htmlFor="edit_school_dean_name" className="text-xs font-semibold">
                                        Dean / Administrator Name
                                    </Label>
                                    <Input
                                        id="edit_school_dean_name"
                                        value={editSchoolForm.data.dean_name}
                                        onChange={(e) => editSchoolForm.setData("dean_name", e.target.value)}
                                        className="text-xs"
                                    />
                                </div>
                                <div className="space-y-1.5">
                                    <Label htmlFor="edit_school_dean_email" className="text-xs font-semibold">
                                        Dean Email
                                    </Label>
                                    <Input
                                        id="edit_school_dean_email"
                                        type="email"
                                        value={editSchoolForm.data.dean_email}
                                        onChange={(e) => editSchoolForm.setData("dean_email", e.target.value)}
                                        className="text-xs font-mono"
                                    />
                                </div>
                            </div>

                            <DialogFooter className="border-t border-border/40 pt-3">
                                <Button type="button" variant="outline" size="sm" onClick={() => setIsEditSchoolOpen(false)} className="h-8 text-xs">
                                    Cancel
                                </Button>
                                <Button
                                    type="submit"
                                    size="sm"
                                    disabled={editSchoolForm.processing || !editSchoolForm.data.school_level || !canUpdate}
                                    className="h-8 gap-1.5 text-xs"
                                >
                                    {editSchoolForm.processing ? <Loader2 className="size-3.5 animate-spin" /> : <Save className="size-3.5" />}
                                    <span>Update Profile</span>
                                </Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>

                {/* Archive / Delete Confirmation Dialog */}
                <AlertDialog open={deletingSchool !== null} onOpenChange={(open) => !open && setDeletingSchool(null)}>
                    <AlertDialogContent>
                        <AlertDialogHeader>
                            <AlertDialogTitle className="text-base font-semibold">Archive Campus {deletingSchool?.name}?</AlertDialogTitle>
                            <AlertDialogDescription className="text-xs">
                                Archive will soft-delete <strong>{deletingSchool?.name}</strong> from operational campus selectors. Force delete permanently removes it and related school references.
                            </AlertDialogDescription>
                        </AlertDialogHeader>
                        <AlertDialogFooter>
                            <AlertDialogCancel className="text-xs h-8">Cancel</AlertDialogCancel>
                            <AlertDialogAction
                                className="bg-destructive text-destructive-foreground hover:bg-destructive/90 text-xs h-8"
                                onClick={handleForceDeleteSchool}
                            >
                                Force Delete
                            </AlertDialogAction>
                            <AlertDialogAction onClick={handleDeleteSchool} className="text-xs h-8">
                                Archive Campus
                            </AlertDialogAction>
                        </AlertDialogFooter>
                    </AlertDialogContent>
                </AlertDialog>
            </div>
        </SystemManagementLayout>
    );
}
