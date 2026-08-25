import {
    index as curriculumIndex,
    showProgram,
    storeProgram,
    toggleProgramStatus,
} from "@/actions/App/Http/Controllers/AdministratorCurriculumManagementController";
import AdminLayout from "@/components/administrators/admin-layout";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from "@/components/ui/dropdown-menu";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Tabs, TabsContent, TabsList, TabsTab } from "@/components/ui/tabs";
import { Textarea } from "@/components/ui/textarea";
import type { User } from "@/types/user";
import { Head, Link, router, useForm } from "@inertiajs/react";
import {
    ArrowRight,
    ArrowUpRight,
    BookOpen,
    CalendarDays,
    Check,
    CheckCircle2,
    ChevronLeft,
    CircleAlert,
    Clock3,
    Ellipsis,
    Filter,
    GraduationCap,
    Layers3,
    ListFilter,
    Plus,
    Search,
    Sparkles,
    Waypoints,
    X,
} from "lucide-react";
import { type FormEvent, useEffect, useMemo, useState } from "react";

interface DepartmentOption {
    id: number;
    name: string;
    code: string;
}

interface CurriculumProgramsProps {
    user: User;
    stats: {
        programs: number;
        active_programs: number;
        subjects: number;
        subjects_with_requisites: number;
        curriculum_versions: number;
    };
    programs: ProgramSummary[];
    departments: DepartmentOption[];
    course_types: { id: number; name: string }[];
    versions: CurriculumVersion[];
    school: { id: number; name: string; school_level: string | null } | null;
    capabilities: CurriculumCapability[];
    catalog_templates: CatalogTemplate[];
    shs_pathways: ShsPathway[];
}

type CurriculumCapability = {
    id: string;
    school_level: string;
    school_level_label: string;
    curriculum_framework: string;
    framework_label: string;
    reference: string;
    is_derived: boolean;
};

type CatalogTemplate = {
    framework: string;
    label: string;
    kind: string;
    code: string;
    title: string;
    stage: string | null;
    qualification_level: string | null;
    duration_hours: number | null;
    tesda_program_type: string | null;
    duration_years: number | null;
    internship_hours: number | null;
    bundled_qualifications: string[];
    advanced_topics: string | null;
    reference: string;
};

type ShsPathway = { id: number; title: string; strands_count: number; subjects_count: number };

type ProgramSummary = {
    id: number;
    code: string;
    title: string;
    department: string | null;
    department_id: number | null;
    department_name: string | null;
    course_type_id: number | null;
    course_type_name: string | null;
    curriculum_year: string | null;
    subjects_count: number;
    total_units: number;
    prerequisites_count: number;
    is_active: boolean;
    updated_at: string | null;
    curriculum_kind: string;
    curriculum_stage: string | null;
    curriculum_framework: string | null;
    qualification_level: string | null;
    duration_hours: number | null;
    tesda_program_type: string | null;
    duration_years: number | null;
    internship_hours: number | null;
    bundled_qualifications: string[];
    advanced_topics: string | null;
};

type CurriculumVersion = {
    curriculum_year: string;
    program_count: number;
    active_program_count: number;
    subject_count: number;
};

type StatusFilter = "all" | "active" | "inactive";
type CreateStep = "identity" | "details";

const FieldError = ({ message }: { message?: string }) => (message ? <p className="text-destructive mt-1 text-xs font-medium">{message}</p> : null);

function capabilityKind(schoolLevel?: string): string {
    switch (schoolLevel) {
        case "technical_vocational":
            return "tesda_qualification";
        case "elementary":
        case "junior_high":
            return "grade_pathway";
        case "senior_high":
            return "senior_high_pathway";
        default:
            return "program";
    }
}

function kindLabel(program: Pick<ProgramSummary, "curriculum_kind" | "curriculum_stage" | "qualification_level" | "tesda_program_type">): string {
    if (program.curriculum_kind === "tesda_qualification") {
        return program.tesda_program_type === "diploma" ? "Institutional diploma" : "TESDA qualification";
    }

    if (program.curriculum_kind === "grade_pathway") return program.curriculum_stage || "Grade pathway";
    if (program.curriculum_kind === "senior_high_pathway") return program.curriculum_stage || "Senior High pathway";
    if (program.curriculum_kind === "program") return "Academic program";

    return program.qualification_level || "Legacy curriculum";
}

function frameworkLabel(value: string | null): string {
    if (!value) return "Local curriculum";

    return value
        .split("_")
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
        .join(" ");
}

function departmentColor(code: string | null): string {
    const palette = [
        "bg-sky-500/10 text-sky-700 dark:text-sky-300",
        "bg-violet-500/10 text-violet-700 dark:text-violet-300",
        "bg-emerald-500/10 text-emerald-700 dark:text-emerald-300",
        "bg-amber-500/10 text-amber-700 dark:text-amber-300",
        "bg-rose-500/10 text-rose-700 dark:text-rose-300",
        "bg-cyan-500/10 text-cyan-700 dark:text-cyan-300",
    ];

    if (!code) return "bg-muted text-muted-foreground";

    return palette[code.charCodeAt(0) % palette.length];
}

export default function CurriculumPrograms({
    user,
    stats,
    programs,
    departments,
    course_types,
    versions,
    school,
    capabilities,
    catalog_templates,
    shs_pathways,
}: CurriculumProgramsProps) {
    const [isCreateOpen, setIsCreateOpen] = useState(false);
    const [createStep, setCreateStep] = useState<CreateStep>("identity");
    const [search, setSearch] = useState("");
    const [statusFilter, setStatusFilter] = useState<StatusFilter>("all");
    const [activeDepartment, setActiveDepartment] = useState<string>(() => {
        if (typeof window === "undefined") return "all";

        return new URLSearchParams(window.location.search).get("department") ?? "all";
    });
    const [activeFramework, setActiveFramework] = useState("all");

    const createForm = useForm({
        code: "",
        title: "",
        description: "",
        department_id: "",
        course_type_id: "",
        curriculum_year: "",
        lec_per_unit: "",
        lab_per_unit: "",
        miscelaneous: "",
        remarks: "",
        capability_id: capabilities[0]?.id ?? "",
        curriculum_kind: capabilityKind(capabilities[0]?.school_level),
        curriculum_stage: "",
        duration_hours: "",
        qualification_level: "",
        catalog_reference: "",
        tesda_program_type: "national_certificate",
        duration_years: "",
        internship_hours: "",
        bundled_qualifications: "",
        advanced_topics: "",
    });

    const selectedCapability = capabilities.find((capability) => capability.id === createForm.data.capability_id) ?? null;
    const compatibleTemplates = catalog_templates.filter((template) => template.framework === selectedCapability?.curriculum_framework);

    const departmentsWithPrograms = useMemo(() => {
        const ids = new Set(programs.map((program) => program.department_id).filter((id): id is number => id !== null));

        return departments.filter((department) => ids.has(department.id));
    }, [departments, programs]);

    const frameworkOptions = useMemo(() => {
        const labels = new Map(capabilities.map((capability) => [capability.curriculum_framework, capability.framework_label]));
        const values = programs.reduce<string[]>((result, program) => {
            if (program.curriculum_framework && !result.includes(program.curriculum_framework)) result.push(program.curriculum_framework);

            return result;
        }, []);

        return values.map((value) => ({ value, label: labels.get(value) ?? frameworkLabel(value) }));
    }, [capabilities, programs]);

    const filteredPrograms = useMemo(() => {
        const query = search.trim().toLowerCase();

        return programs.filter((program) => {
            const matchesQuery =
                query === "" ||
                [program.code, program.title, program.department, program.department_name, program.curriculum_year, program.course_type_name]
                    .filter(Boolean)
                    .join(" ")
                    .toLowerCase()
                    .includes(query);
            const matchesStatus = statusFilter === "all" || (statusFilter === "active" ? program.is_active : !program.is_active);
            const matchesDepartment = activeDepartment === "all" || String(program.department_id) === activeDepartment;
            const matchesFramework = activeFramework === "all" || program.curriculum_framework === activeFramework;

            return matchesQuery && matchesStatus && matchesDepartment && matchesFramework;
        });
    }, [activeDepartment, activeFramework, programs, search, statusFilter]);

    const filteredStats = useMemo(() => {
        const isUnfiltered = search.trim() === "" && statusFilter === "all" && activeDepartment === "all" && activeFramework === "all";

        if (isUnfiltered) return stats;

        return {
            programs: filteredPrograms.length,
            active_programs: filteredPrograms.filter((program) => program.is_active).length,
            subjects: filteredPrograms.reduce((sum, program) => sum + program.subjects_count, 0),
            subjects_with_requisites: filteredPrograms.reduce((sum, program) => sum + program.prerequisites_count, 0),
            curriculum_versions: new Set(filteredPrograms.map((program) => program.curriculum_year).filter(Boolean)).size,
        };
    }, [activeDepartment, activeFramework, filteredPrograms, search, stats, statusFilter]);

    const selectCapability = (capabilityId: string): void => {
        const capability = capabilities.find((item) => item.id === capabilityId);

        createForm.setData({
            ...createForm.data,
            capability_id: capabilityId,
            curriculum_kind: capabilityKind(capability?.school_level),
            curriculum_stage: "",
            duration_hours: "",
            qualification_level: "",
            catalog_reference: capability?.reference ?? "",
            tesda_program_type: "national_certificate",
            duration_years: "",
            internship_hours: "",
            bundled_qualifications: "",
            advanced_topics: "",
        });
    };

    const applyTemplate = (code: string): void => {
        const template = compatibleTemplates.find((item) => item.code === code);
        if (!template) return;

        createForm.setData({
            ...createForm.data,
            code: template.code,
            title: template.title,
            curriculum_kind: template.kind,
            curriculum_stage: template.stage ?? "",
            qualification_level: template.qualification_level ?? "",
            duration_hours: template.duration_hours?.toString() ?? "",
            catalog_reference: template.reference,
            tesda_program_type: template.tesda_program_type ?? "national_certificate",
            duration_years: template.duration_years?.toString() ?? "",
            internship_hours: template.internship_hours?.toString() ?? "",
            bundled_qualifications: template.bundled_qualifications.join(", "),
            advanced_topics: template.advanced_topics ?? "",
        });
    };

    useEffect(() => {
        if (activeDepartment !== "all" && isCreateOpen) {
            createForm.setData("department_id", activeDepartment);
        }
    }, [activeDepartment, isCreateOpen]);

    const resetCreateDialog = (): void => {
        createForm.reset();
        createForm.clearErrors();
        setCreateStep("identity");
    };

    const handleCreateProgram = (event: FormEvent): void => {
        event.preventDefault();

        createForm.post(storeProgram().url, {
            preserveScroll: true,
            onSuccess: () => {
                setIsCreateOpen(false);
                resetCreateDialog();
            },
            onError: (errors) => {
                const identityFields = [
                    "capability_id",
                    "code",
                    "course_type_id",
                    "curriculum_kind",
                    "curriculum_stage",
                    "curriculum_year",
                    "department_id",
                    "title",
                ];

                if (identityFields.some((field) => errors[field])) {
                    setCreateStep("identity");
                }
            },
        });
    };

    const toggleStatus = (program: ProgramSummary): void => {
        router.put(toggleProgramStatus(program.id).url, {}, { preserveScroll: true });
    };

    const clearFilters = (): void => {
        setSearch("");
        setStatusFilter("all");
        setActiveDepartment("all");
        setActiveFramework("all");
    };

    const activeFilterCount = [statusFilter !== "all", activeDepartment !== "all", activeFramework !== "all"].filter(Boolean).length;
    const readiness = filteredStats.programs > 0 ? Math.round((filteredStats.active_programs / filteredStats.programs) * 100) : 0;

    return (
        <AdminLayout user={user} title="Programs">
            <Head title="Programs · Curriculum" />
            <div className="relative isolate flex flex-col gap-7 overflow-hidden pb-4">
                <div className="pointer-events-none absolute -top-40 right-[-12rem] -z-10 size-[30rem] rounded-full bg-sky-400/10 blur-3xl dark:bg-sky-300/5" />
                <div className="pointer-events-none absolute top-[22rem] left-[-20rem] -z-10 size-[34rem] rounded-full bg-amber-300/10 blur-3xl dark:bg-amber-300/5" />

                <div className="text-muted-foreground flex flex-wrap items-center gap-2 text-xs font-medium">
                    <Link href={curriculumIndex().url} className="hover:text-foreground transition-colors">
                        Curriculum
                    </Link>
                    <span aria-hidden="true">/</span>
                    <span className="text-foreground">Programs</span>
                </div>

                <section className="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                    <div className="max-w-2xl space-y-4">
                        <div className="border-border/70 bg-background/70 text-muted-foreground inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-[11px] font-semibold tracking-[0.14em] uppercase shadow-xs backdrop-blur-xl">
                            <Sparkles className="size-3.5 text-amber-500" /> Curriculum studio
                        </div>
                        <div className="space-y-2">
                            <h1 className="text-4xl leading-[0.98] font-semibold tracking-[-0.045em] text-balance sm:text-5xl">
                                Programs that are ready to teach.
                            </h1>
                            <p className="text-muted-foreground max-w-xl text-sm leading-6 sm:text-base">
                                Define each pathway, build its subject structure, and keep the active catalog aligned with your school’s curriculum.
                            </p>
                        </div>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <Button asChild variant="ghost" className="rounded-full">
                            <Link href={curriculumIndex().url}>
                                <ChevronLeft className="size-4" /> Curriculum overview
                            </Link>
                        </Button>
                        <Button className="rounded-full px-4 shadow-sm" onClick={() => setIsCreateOpen(true)}>
                            <Plus className="size-4" /> Add program
                        </Button>
                    </div>
                </section>

                <section className="overflow-hidden rounded-[28px] border border-slate-800 bg-slate-950 text-white shadow-xl shadow-slate-950/10 dark:border-slate-700">
                    <div className="grid gap-0 lg:grid-cols-[minmax(0,1.15fr)_minmax(300px,0.85fr)]">
                        <div className="relative overflow-hidden p-6 sm:p-8">
                            <div className="pointer-events-none absolute top-0 right-0 size-64 rounded-full bg-sky-400/15 blur-3xl" />
                            <div className="relative flex h-full flex-col justify-between gap-8">
                                <div className="space-y-3">
                                    <div className="flex items-center gap-2 text-xs font-semibold tracking-[0.16em] text-slate-400 uppercase">
                                        <Waypoints className="size-4 text-sky-300" /> Program operations
                                    </div>
                                    <div className="max-w-lg space-y-2">
                                        <h2 className="text-2xl font-semibold tracking-[-0.03em] sm:text-3xl">One catalog, one source of truth.</h2>
                                        <p className="text-sm leading-6 text-slate-300">
                                            Use the list below to keep program identity, curriculum structure, and activation status in sync.
                                        </p>
                                    </div>
                                </div>
                                <div className="grid gap-3 sm:grid-cols-3">
                                    <div className="rounded-2xl border border-white/10 bg-white/[0.06] p-4 backdrop-blur-xl">
                                        <p className="text-xs text-slate-400">Programs in view</p>
                                        <p className="mt-2 text-2xl font-semibold tracking-tight">{filteredStats.programs}</p>
                                        <p className="mt-1 text-xs text-slate-400">{filteredStats.active_programs} active</p>
                                    </div>
                                    <div className="rounded-2xl border border-white/10 bg-white/[0.06] p-4 backdrop-blur-xl">
                                        <p className="text-xs text-slate-400">Subjects mapped</p>
                                        <p className="mt-2 text-2xl font-semibold tracking-tight">{filteredStats.subjects}</p>
                                        <p className="mt-1 text-xs text-slate-400">{filteredStats.subjects_with_requisites} with prerequisites</p>
                                    </div>
                                    <div className="rounded-2xl border border-white/10 bg-white/[0.06] p-4 backdrop-blur-xl">
                                        <p className="text-xs text-slate-400">Curriculum years</p>
                                        <p className="mt-2 text-2xl font-semibold tracking-tight">{filteredStats.curriculum_versions}</p>
                                        <p className="mt-1 text-xs text-slate-400">{school?.name ?? "Current school"}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div className="border-t border-white/10 bg-white/[0.04] p-6 sm:p-8 lg:border-t-0 lg:border-l">
                            <div className="flex items-start justify-between gap-4">
                                <div>
                                    <p className="text-xs font-semibold tracking-[0.16em] text-slate-400 uppercase">Catalog readiness</p>
                                    <p className="mt-2 text-3xl font-semibold tracking-[-0.03em]">{readiness}%</p>
                                </div>
                                <CheckCircle2 className="size-5 text-emerald-300" />
                            </div>
                            <div className="mt-6 h-2 overflow-hidden rounded-full bg-white/10">
                                <div
                                    className="h-full rounded-full bg-emerald-300 transition-[width] duration-500 ease-out"
                                    style={{ width: `${readiness}%` }}
                                />
                            </div>
                            <div className="mt-4 flex items-start gap-3 text-sm leading-5 text-slate-300">
                                <CircleAlert className="mt-0.5 size-4 shrink-0 text-amber-300" />
                                <p>
                                    {filteredStats.programs - filteredStats.active_programs === 0
                                        ? "Every visible program is active and ready for downstream enrollment workflows."
                                        : `${filteredStats.programs - filteredStats.active_programs} visible ${filteredStats.programs - filteredStats.active_programs === 1 ? "program needs" : "programs need"} review before activation.`}
                                </p>
                            </div>
                        </div>
                    </div>
                </section>

                <div className="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_310px]">
                    <section className="border-border/70 bg-card/80 min-w-0 overflow-hidden rounded-[24px] border shadow-sm backdrop-blur-xl">
                        <div className="border-border/70 border-b p-5 sm:p-6">
                            <div className="flex flex-col gap-5">
                                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                    <div>
                                        <div className="flex items-center gap-2">
                                            <h2 className="text-xl font-semibold tracking-[-0.025em]">Program catalog</h2>
                                            <Badge variant="secondary" className="rounded-full px-2.5">
                                                {filteredPrograms.length}
                                            </Badge>
                                        </div>
                                        <p className="text-muted-foreground mt-1 text-sm">
                                            Search the catalog, then open a program to manage its curriculum.
                                        </p>
                                    </div>
                                    <div className="text-muted-foreground flex items-center gap-2 text-xs">
                                        <ListFilter className="size-4" />{" "}
                                        {activeFilterCount > 0 ? `${activeFilterCount} filters active` : "All programs"}
                                    </div>
                                </div>

                                <div className="flex flex-col gap-3 lg:flex-row">
                                    <div className="relative min-w-0 flex-1">
                                        <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                                        <Input
                                            aria-label="Search programs"
                                            value={search}
                                            onChange={(event) => setSearch(event.target.value)}
                                            placeholder="Search by code, name, department, or year…"
                                            className="border-border/70 bg-background/70 h-10 rounded-xl pr-16 pl-9 shadow-none"
                                        />
                                        {search ? (
                                            <button
                                                type="button"
                                                aria-label="Clear search"
                                                onClick={() => setSearch("")}
                                                className="text-muted-foreground hover:bg-muted hover:text-foreground absolute top-1/2 right-3 -translate-y-1/2 rounded-md p-1 transition-colors"
                                            >
                                                <X className="size-3.5" />
                                            </button>
                                        ) : (
                                            <span className="bg-muted text-muted-foreground pointer-events-none absolute top-1/2 right-3 hidden -translate-y-1/2 rounded border px-1.5 py-0.5 text-[10px] font-medium sm:inline">
                                                ⌘ K
                                            </span>
                                        )}
                                    </div>
                                    <div className="flex flex-wrap gap-2">
                                        <Select value={activeDepartment} onValueChange={setActiveDepartment}>
                                            <SelectTrigger className="bg-background/70 h-10 min-w-[150px] rounded-xl shadow-none">
                                                <Filter className="text-muted-foreground size-3.5" />
                                                <SelectValue placeholder="Department" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="all">All departments</SelectItem>
                                                {departmentsWithPrograms.map((department) => (
                                                    <SelectItem key={department.id} value={String(department.id)}>
                                                        {department.code} · {department.name}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        {frameworkOptions.length > 0 && (
                                            <Select value={activeFramework} onValueChange={setActiveFramework}>
                                                <SelectTrigger className="bg-background/70 h-10 min-w-[150px] rounded-xl shadow-none">
                                                    <Layers3 className="text-muted-foreground size-3.5" />
                                                    <SelectValue placeholder="Framework" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="all">All frameworks</SelectItem>
                                                    {frameworkOptions.map((option) => (
                                                        <SelectItem key={option.value} value={option.value}>
                                                            {option.label}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        )}
                                    </div>
                                </div>

                                <div className="flex flex-wrap items-center gap-2">
                                    <div className="border-border/70 bg-muted/40 inline-flex rounded-xl border p-1">
                                        {(["all", "active", "inactive"] as StatusFilter[]).map((status) => {
                                            const label = status === "all" ? "All" : status === "active" ? "Active" : "Needs review";
                                            const count =
                                                status === "all"
                                                    ? programs.length
                                                    : status === "active"
                                                      ? programs.filter((program) => program.is_active).length
                                                      : programs.filter((program) => !program.is_active).length;

                                            return (
                                                <button
                                                    type="button"
                                                    key={status}
                                                    aria-pressed={statusFilter === status}
                                                    onClick={() => setStatusFilter(status)}
                                                    className={`rounded-lg px-3 py-1.5 text-xs font-semibold transition-[background-color,color,box-shadow] duration-200 ${
                                                        statusFilter === status
                                                            ? "bg-background text-foreground shadow-sm"
                                                            : "text-muted-foreground hover:text-foreground"
                                                    }`}
                                                >
                                                    {label} <span className="text-muted-foreground ml-1 text-[10px]">{count}</span>
                                                </button>
                                            );
                                        })}
                                    </div>
                                    {(search || activeFilterCount > 0) && (
                                        <button
                                            type="button"
                                            onClick={clearFilters}
                                            className="text-muted-foreground hover:text-foreground ml-auto text-xs font-medium transition-colors"
                                        >
                                            Clear filters
                                        </button>
                                    )}
                                </div>
                            </div>
                        </div>

                        <div className="divide-border/60 divide-y">
                            {filteredPrograms.length > 0 ? (
                                filteredPrograms.map((program) => (
                                    <article
                                        key={program.id}
                                        className="group hover:bg-muted/20 relative grid gap-5 p-5 transition-[background-color,transform] duration-200 sm:p-6 lg:grid-cols-[minmax(0,1fr)_180px_auto] lg:items-center"
                                    >
                                        <div className="absolute inset-y-5 left-0 w-1 rounded-r-full bg-sky-400/70 opacity-0 transition-opacity duration-200 group-hover:opacity-100" />
                                        <div className="min-w-0">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <Link
                                                    href={showProgram(program.id).url}
                                                    prefetch
                                                    className="hover:text-primary truncate text-base font-semibold tracking-[-0.015em] transition-colors"
                                                >
                                                    {program.code}
                                                </Link>
                                                <Badge
                                                    variant="secondary"
                                                    className={`rounded-full px-2 py-0.5 text-[10px] font-semibold ${
                                                        program.is_active
                                                            ? "bg-emerald-500/10 text-emerald-700 dark:text-emerald-300"
                                                            : "bg-amber-500/10 text-amber-700 dark:text-amber-300"
                                                    }`}
                                                >
                                                    {program.is_active ? "Active" : "Needs review"}
                                                </Badge>
                                            </div>
                                            <Link
                                                href={showProgram(program.id).url}
                                                className="text-muted-foreground hover:text-foreground mt-1 block truncate text-sm transition-colors"
                                            >
                                                {program.title}
                                            </Link>
                                            <div className="text-muted-foreground mt-3 flex flex-wrap items-center gap-2 text-xs">
                                                {program.department && (
                                                    <span className={`rounded-full px-2 py-1 font-semibold ${departmentColor(program.department)}`}>
                                                        {program.department}
                                                    </span>
                                                )}
                                                <span className="inline-flex items-center gap-1.5">
                                                    <Waypoints className="size-3.5" /> {kindLabel(program)}
                                                </span>
                                                {program.curriculum_framework && <span className="text-border">•</span>}
                                                {program.curriculum_framework && <span>{frameworkLabel(program.curriculum_framework)}</span>}
                                                {program.curriculum_year && (
                                                    <>
                                                        <span className="text-border">•</span>
                                                        <span className="inline-flex items-center gap-1.5">
                                                            <CalendarDays className="size-3.5" /> {program.curriculum_year}
                                                        </span>
                                                    </>
                                                )}
                                            </div>
                                        </div>

                                        <div className="border-border/60 grid grid-cols-3 gap-3 border-t pt-4 text-xs sm:grid-cols-3 lg:border-t-0 lg:border-l lg:pt-0 lg:pl-5">
                                            <div>
                                                <p className="text-muted-foreground">Subjects</p>
                                                <p className="text-foreground mt-1 text-sm font-semibold">{program.subjects_count}</p>
                                            </div>
                                            <div>
                                                <p className="text-muted-foreground">Units</p>
                                                <p className="text-foreground mt-1 text-sm font-semibold">{program.total_units}</p>
                                            </div>
                                            <div>
                                                <p className="text-muted-foreground">Prereqs</p>
                                                <p className="text-foreground mt-1 text-sm font-semibold">{program.prerequisites_count}</p>
                                            </div>
                                        </div>

                                        <div className="flex items-center justify-between gap-2 sm:justify-end">
                                            <Button asChild variant="secondary" size="sm" className="rounded-full px-3">
                                                <Link href={showProgram(program.id).url}>
                                                    Manage <ArrowUpRight className="size-3.5" />
                                                </Link>
                                            </Button>
                                            <DropdownMenu>
                                                <DropdownMenuTrigger asChild>
                                                    <Button variant="ghost" size="icon-sm" className="text-muted-foreground rounded-full">
                                                        <Ellipsis className="size-4" />
                                                        <span className="sr-only">Open actions for {program.code}</span>
                                                    </Button>
                                                </DropdownMenuTrigger>
                                                <DropdownMenuContent align="end">
                                                    <DropdownMenuItem onClick={() => toggleStatus(program)}>
                                                        {program.is_active ? "Deactivate program" : "Activate program"}
                                                    </DropdownMenuItem>
                                                </DropdownMenuContent>
                                            </DropdownMenu>
                                        </div>
                                    </article>
                                ))
                            ) : (
                                <div className="flex flex-col items-center justify-center px-6 py-20 text-center">
                                    <div className="bg-muted text-muted-foreground flex size-12 items-center justify-center rounded-2xl">
                                        <Search className="size-5" />
                                    </div>
                                    <h3 className="mt-4 text-sm font-semibold">No programs match these filters</h3>
                                    <p className="text-muted-foreground mt-1 max-w-sm text-sm">
                                        Try a different search or clear the filters to return to the full catalog.
                                    </p>
                                    <Button variant="outline" size="sm" className="mt-5 rounded-full" onClick={clearFilters}>
                                        Reset catalog view
                                    </Button>
                                </div>
                            )}
                        </div>
                    </section>

                    <aside className="grid gap-4">
                        <Card className="border-border/70 bg-card/80 rounded-[24px] shadow-sm backdrop-blur-xl">
                            <CardHeader className="gap-1 p-5">
                                <div className="flex items-center justify-between gap-3">
                                    <CardTitle className="text-base tracking-[-0.015em]">Curriculum versions</CardTitle>
                                    <Clock3 className="text-muted-foreground size-4" />
                                </div>
                                <CardDescription>Where your subject structures are concentrated.</CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-2 p-5 pt-0">
                                {versions.length > 0 ? (
                                    versions.slice(0, 5).map((version) => (
                                        <div key={version.curriculum_year} className="border-border/60 bg-background/50 rounded-2xl border p-3.5">
                                            <div className="flex items-center justify-between gap-3">
                                                <span className="text-sm font-semibold">{version.curriculum_year}</span>
                                                <span className="text-muted-foreground text-xs">
                                                    {version.active_program_count}/{version.program_count} active
                                                </span>
                                            </div>
                                            <div className="bg-muted mt-2 h-1.5 overflow-hidden rounded-full">
                                                <div
                                                    className="h-full rounded-full bg-sky-400 transition-[width] duration-500 ease-out"
                                                    style={{
                                                        width: `${version.program_count ? (version.active_program_count / version.program_count) * 100 : 0}%`,
                                                    }}
                                                />
                                            </div>
                                            <p className="text-muted-foreground mt-2 text-xs">{version.subject_count} subjects across this version</p>
                                        </div>
                                    ))
                                ) : (
                                    <p className="bg-muted/50 text-muted-foreground rounded-2xl p-4 text-sm">No curriculum versions yet.</p>
                                )}
                            </CardContent>
                        </Card>

                        <Card className="border-border/70 bg-card/80 rounded-[24px] shadow-sm backdrop-blur-xl">
                            <CardHeader className="gap-1 p-5">
                                <CardTitle className="text-base tracking-[-0.015em]">A reliable workflow</CardTitle>
                                <CardDescription>Keep every program moving through the same three stages.</CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-4 p-5 pt-0">
                                {[
                                    ["01", "Define", "Name the pathway and align it to a framework."],
                                    ["02", "Build", "Add subjects, units, sequencing, and prerequisites."],
                                    ["03", "Activate", "Publish only after the curriculum is ready for enrollment."],
                                ].map(([number, title, description]) => (
                                    <div key={number} className="flex gap-3">
                                        <span className="bg-muted text-muted-foreground flex size-7 shrink-0 items-center justify-center rounded-full text-[10px] font-bold">
                                            {number}
                                        </span>
                                        <div className="space-y-0.5">
                                            <p className="text-sm font-semibold">{title}</p>
                                            <p className="text-muted-foreground text-xs leading-5">{description}</p>
                                        </div>
                                    </div>
                                ))}
                                {shs_pathways.length > 0 && (
                                    <div className="mt-1 flex items-start gap-2 rounded-2xl border border-amber-500/20 bg-amber-500/5 p-3 text-xs leading-5 text-amber-800 dark:text-amber-200">
                                        <BookOpen className="mt-0.5 size-3.5 shrink-0" />
                                        <span>
                                            {shs_pathways.length} Senior High pathway records continue to use their track and strand structure.
                                        </span>
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </aside>
                </div>
            </div>

            <Dialog
                open={isCreateOpen}
                onOpenChange={(open) => {
                    setIsCreateOpen(open);
                    if (!open) resetCreateDialog();
                }}
            >
                <DialogContent className="border-border/80 bg-card/95 max-h-[min(880px,calc(100vh-2rem))] overflow-y-auto rounded-[26px] p-0 shadow-2xl backdrop-blur-2xl sm:max-w-3xl">
                    <div className="border-border/70 bg-muted/25 border-b px-6 py-6 sm:px-8">
                        <DialogHeader className="gap-3 pr-8 text-left">
                            <div className="flex size-11 items-center justify-center rounded-2xl bg-sky-500/10 text-sky-700 dark:text-sky-300">
                                <GraduationCap className="size-5" />
                            </div>
                            <div className="space-y-1">
                                <DialogTitle className="text-2xl tracking-[-0.03em]">Add a program</DialogTitle>
                                <DialogDescription className="max-w-xl leading-5">
                                    Start with the program identity, then add the standards and operational details your registrar needs.
                                </DialogDescription>
                            </div>
                        </DialogHeader>
                    </div>

                    <form className="grid gap-6 px-6 py-6 sm:px-8" onSubmit={handleCreateProgram}>
                        <Tabs value={createStep} onValueChange={(value) => setCreateStep(value as CreateStep)}>
                            <TabsList className="grid w-full grid-cols-2 rounded-xl p-1">
                                <TabsTab value="identity" className="justify-start gap-3 rounded-lg px-3 text-left">
                                    <span className="bg-muted flex size-6 items-center justify-center rounded-full text-[10px] font-bold">1</span>
                                    <span>
                                        <span className="block text-xs font-semibold">Identity</span>
                                        <span className="text-muted-foreground hidden text-[10px] font-normal sm:block">Name and pathway</span>
                                    </span>
                                </TabsTab>
                                <TabsTab value="details" className="justify-start gap-3 rounded-lg px-3 text-left">
                                    <span className="bg-muted flex size-6 items-center justify-center rounded-full text-[10px] font-bold">2</span>
                                    <span>
                                        <span className="block text-xs font-semibold">Standards & details</span>
                                        <span className="text-muted-foreground hidden text-[10px] font-normal sm:block">Complete the record</span>
                                    </span>
                                </TabsTab>
                            </TabsList>

                            <TabsContent value="identity" className="grid gap-5 pt-4">
                                <div className="rounded-2xl border border-sky-500/15 bg-sky-500/[0.04] p-4">
                                    <div className="flex items-start gap-3">
                                        <Sparkles className="mt-0.5 size-4 shrink-0 text-sky-600 dark:text-sky-300" />
                                        <div>
                                            <p className="text-sm font-semibold">Choose the right starting point</p>
                                            <p className="text-muted-foreground mt-1 text-xs leading-5">
                                                A framework keeps program metadata consistent. Use a catalog starter when one is available, or
                                                continue with a custom pathway.
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <div className="grid gap-4 md:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="create-capability">School capability</Label>
                                        <Select value={createForm.data.capability_id} onValueChange={selectCapability}>
                                            <SelectTrigger id="create-capability" className="rounded-xl">
                                                <SelectValue placeholder="Choose a supported pathway" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {capabilities.map((capability) => (
                                                    <SelectItem key={capability.id} value={capability.id}>
                                                        {capability.school_level_label} · {capability.framework_label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        {!capabilities.length && (
                                            <p className="text-muted-foreground text-xs">
                                                No framework is configured yet. You can still create a custom pathway.
                                            </p>
                                        )}
                                        <FieldError message={createForm.errors.capability_id} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="create-template">
                                            Catalog starter <span className="text-muted-foreground font-normal">(optional)</span>
                                        </Label>
                                        <Select onValueChange={applyTemplate} disabled={!selectedCapability || compatibleTemplates.length === 0}>
                                            <SelectTrigger id="create-template" className="rounded-xl">
                                                <SelectValue placeholder={selectedCapability ? "Choose a template" : "Choose a capability first"} />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {compatibleTemplates.map((template) => (
                                                    <SelectItem key={template.code} value={template.code}>
                                                        {template.label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                </div>

                                {selectedCapability && (
                                    <div className="flex flex-wrap items-center gap-2 text-xs">
                                        <Badge variant="secondary" className="rounded-full">
                                            {selectedCapability.school_level_label}
                                        </Badge>
                                        <Badge variant="outline" className="rounded-full">
                                            {selectedCapability.framework_label}
                                        </Badge>
                                        {school && <span className="text-muted-foreground">Adding to {school.name}</span>}
                                    </div>
                                )}

                                <div className="grid gap-4 md:grid-cols-[0.65fr_1.35fr]">
                                    <div className="grid gap-2">
                                        <Label htmlFor="create-code">Program code</Label>
                                        <Input
                                            id="create-code"
                                            className="rounded-xl uppercase"
                                            placeholder="e.g. BSIT"
                                            value={createForm.data.code}
                                            onChange={(event) => createForm.setData("code", event.target.value.toUpperCase())}
                                        />
                                        <FieldError message={createForm.errors.code} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="create-title">Program title</Label>
                                        <Input
                                            id="create-title"
                                            className="rounded-xl"
                                            placeholder="e.g. Bachelor of Science in Information Technology"
                                            value={createForm.data.title}
                                            onChange={(event) => createForm.setData("title", event.target.value)}
                                        />
                                        <FieldError message={createForm.errors.title} />
                                    </div>
                                </div>

                                <div className="grid gap-4 md:grid-cols-2">
                                    {createForm.data.curriculum_kind === "program" && (
                                        <div className="grid gap-2">
                                            <Label htmlFor="create-department">Department</Label>
                                            <Select
                                                value={createForm.data.department_id}
                                                onValueChange={(value) => createForm.setData("department_id", value)}
                                            >
                                                <SelectTrigger id="create-department" className="rounded-xl">
                                                    <SelectValue placeholder="Select department" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {departments.map((department) => (
                                                        <SelectItem key={department.id} value={String(department.id)}>
                                                            {department.code} · {department.name}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                            <FieldError message={createForm.errors.department_id} />
                                        </div>
                                    )}
                                    {createForm.data.curriculum_kind === "program" && (
                                        <div className="grid gap-2">
                                            <Label htmlFor="create-course-type">Program type</Label>
                                            <Select
                                                value={createForm.data.course_type_id}
                                                onValueChange={(value) => createForm.setData("course_type_id", value)}
                                            >
                                                <SelectTrigger id="create-course-type" className="rounded-xl">
                                                    <SelectValue placeholder="Select program type" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {course_types.map((type) => (
                                                        <SelectItem key={type.id} value={String(type.id)}>
                                                            {type.name}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                            <FieldError message={createForm.errors.course_type_id} />
                                        </div>
                                    )}
                                </div>

                                {(createForm.data.curriculum_kind === "grade_pathway" ||
                                    createForm.data.curriculum_kind === "senior_high_pathway") && (
                                    <div className="grid gap-2">
                                        <Label htmlFor="create-stage">Grade level / stage</Label>
                                        <Input
                                            id="create-stage"
                                            className="rounded-xl"
                                            placeholder="e.g. Grade 1, Grade 7, Grade 11"
                                            value={createForm.data.curriculum_stage}
                                            onChange={(event) => createForm.setData("curriculum_stage", event.target.value)}
                                        />
                                        <FieldError message={createForm.errors.curriculum_stage} />
                                    </div>
                                )}

                                <div className="grid gap-4 md:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="create-year">Curriculum year</Label>
                                        <Input
                                            id="create-year"
                                            className="rounded-xl"
                                            placeholder="e.g. 2024-2025"
                                            value={createForm.data.curriculum_year}
                                            onChange={(event) => createForm.setData("curriculum_year", event.target.value)}
                                        />
                                        <FieldError message={createForm.errors.curriculum_year} />
                                    </div>
                                    {createForm.data.curriculum_kind === "program" && (
                                        <div className="grid gap-2">
                                            <Label htmlFor="create-misc">
                                                Miscellaneous fee <span className="text-muted-foreground font-normal">(₱)</span>
                                            </Label>
                                            <Input
                                                id="create-misc"
                                                type="number"
                                                min="0"
                                                className="rounded-xl"
                                                placeholder="e.g. 3500"
                                                value={createForm.data.miscelaneous}
                                                onChange={(event) => createForm.setData("miscelaneous", event.target.value)}
                                            />
                                            <FieldError message={createForm.errors.miscelaneous} />
                                        </div>
                                    )}
                                </div>
                            </TabsContent>

                            <TabsContent value="details" className="grid gap-5 pt-4">
                                {createForm.data.curriculum_kind === "tesda_qualification" && (
                                    <div className="grid gap-4 rounded-2xl border border-amber-500/20 bg-amber-500/[0.04] p-4 md:grid-cols-2">
                                        <div className="grid gap-2">
                                            <Label htmlFor="create-tesda-type">TESDA program type</Label>
                                            <Select
                                                value={createForm.data.tesda_program_type}
                                                onValueChange={(value) => createForm.setData("tesda_program_type", value)}
                                            >
                                                <SelectTrigger id="create-tesda-type" className="rounded-xl">
                                                    <SelectValue placeholder="Select program type" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="national_certificate">National Certificate (NC)</SelectItem>
                                                    <SelectItem value="diploma">Institutional Diploma</SelectItem>
                                                </SelectContent>
                                            </Select>
                                            <FieldError message={createForm.errors.tesda_program_type} />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="create-qualification">Qualification level</Label>
                                            <Input
                                                id="create-qualification"
                                                className="rounded-xl"
                                                placeholder="e.g. NC II or Diploma"
                                                value={createForm.data.qualification_level}
                                                onChange={(event) => createForm.setData("qualification_level", event.target.value)}
                                            />
                                            <FieldError message={createForm.errors.qualification_level} />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="create-hours">Total program hours</Label>
                                            <Input
                                                id="create-hours"
                                                type="number"
                                                min="1"
                                                className="rounded-xl"
                                                value={createForm.data.duration_hours}
                                                onChange={(event) => createForm.setData("duration_hours", event.target.value)}
                                            />
                                            <FieldError message={createForm.errors.duration_hours} />
                                        </div>
                                        {createForm.data.tesda_program_type === "diploma" && (
                                            <>
                                                <div className="grid gap-2">
                                                    <Label htmlFor="create-duration-years">
                                                        Typical duration <span className="text-muted-foreground font-normal">(years)</span>
                                                    </Label>
                                                    <Input
                                                        id="create-duration-years"
                                                        type="number"
                                                        min="0.5"
                                                        max="10"
                                                        step="0.5"
                                                        className="rounded-xl"
                                                        placeholder="e.g. 1.5"
                                                        value={createForm.data.duration_years}
                                                        onChange={(event) => createForm.setData("duration_years", event.target.value)}
                                                    />
                                                    <FieldError message={createForm.errors.duration_years} />
                                                </div>
                                                <div className="grid gap-2">
                                                    <Label htmlFor="create-internship-hours">OJT / internship hours</Label>
                                                    <Input
                                                        id="create-internship-hours"
                                                        type="number"
                                                        min="0"
                                                        max="65535"
                                                        className="rounded-xl"
                                                        placeholder="e.g. 600"
                                                        value={createForm.data.internship_hours}
                                                        onChange={(event) => createForm.setData("internship_hours", event.target.value)}
                                                    />
                                                    <FieldError message={createForm.errors.internship_hours} />
                                                </div>
                                                <div className="grid gap-2 md:col-span-2">
                                                    <Label htmlFor="create-bundled">Bundled qualifications</Label>
                                                    <Input
                                                        id="create-bundled"
                                                        className="rounded-xl"
                                                        placeholder="Separate each qualification with a comma"
                                                        value={createForm.data.bundled_qualifications}
                                                        onChange={(event) => createForm.setData("bundled_qualifications", event.target.value)}
                                                    />
                                                    <FieldError message={createForm.errors.bundled_qualifications} />
                                                </div>
                                                <div className="grid gap-2 md:col-span-2">
                                                    <Label htmlFor="create-advanced">Advanced topics and internship context</Label>
                                                    <Textarea
                                                        id="create-advanced"
                                                        className="min-h-20 resize-none rounded-xl"
                                                        placeholder="Describe the advanced topics, operations, or internship context."
                                                        value={createForm.data.advanced_topics}
                                                        onChange={(event) => createForm.setData("advanced_topics", event.target.value)}
                                                    />
                                                    <FieldError message={createForm.errors.advanced_topics} />
                                                </div>
                                            </>
                                        )}
                                    </div>
                                )}

                                {createForm.data.curriculum_kind === "program" && (
                                    <div className="border-border/70 bg-muted/20 grid gap-4 rounded-2xl border p-4 md:grid-cols-2">
                                        <div className="grid gap-2">
                                            <Label htmlFor="create-lec">Lecture rate / unit</Label>
                                            <Input
                                                id="create-lec"
                                                type="number"
                                                min="0"
                                                className="rounded-xl"
                                                value={createForm.data.lec_per_unit}
                                                onChange={(event) => createForm.setData("lec_per_unit", event.target.value)}
                                            />
                                            <FieldError message={createForm.errors.lec_per_unit} />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="create-lab">Lab rate / unit</Label>
                                            <Input
                                                id="create-lab"
                                                type="number"
                                                min="0"
                                                className="rounded-xl"
                                                value={createForm.data.lab_per_unit}
                                                onChange={(event) => createForm.setData("lab_per_unit", event.target.value)}
                                            />
                                            <FieldError message={createForm.errors.lab_per_unit} />
                                        </div>
                                    </div>
                                )}

                                <div className="grid gap-4 md:grid-cols-2">
                                    <div className="grid gap-2 md:col-span-2">
                                        <Label htmlFor="create-description">Description</Label>
                                        <Textarea
                                            id="create-description"
                                            className="min-h-24 resize-none rounded-xl"
                                            placeholder="Give staff a concise description of this program and its intended outcome."
                                            value={createForm.data.description}
                                            onChange={(event) => createForm.setData("description", event.target.value)}
                                        />
                                        <FieldError message={createForm.errors.description} />
                                    </div>
                                    <div className="grid gap-2 md:col-span-2">
                                        <Label htmlFor="create-remarks">
                                            Internal remarks <span className="text-muted-foreground font-normal">(optional)</span>
                                        </Label>
                                        <Textarea
                                            id="create-remarks"
                                            className="min-h-20 resize-none rounded-xl"
                                            placeholder="Add internal notes for curriculum administrators."
                                            value={createForm.data.remarks}
                                            onChange={(event) => createForm.setData("remarks", event.target.value)}
                                        />
                                        <FieldError message={createForm.errors.remarks} />
                                    </div>
                                </div>
                            </TabsContent>
                        </Tabs>

                        <DialogFooter className="border-border/70 border-t pt-5 sm:justify-between">
                            <div className="text-muted-foreground flex items-center gap-2 text-xs">
                                <span className="flex size-5 items-center justify-center rounded-full bg-emerald-500/10 text-emerald-600 dark:text-emerald-300">
                                    <Check className="size-3" />
                                </span>
                                New programs are active by default; deactivate them when they need review.
                            </div>
                            <div className="flex flex-col-reverse gap-2 sm:flex-row">
                                {createStep === "details" && (
                                    <Button type="button" variant="ghost" className="rounded-full" onClick={() => setCreateStep("identity")}>
                                        <ChevronLeft className="size-4" /> Back
                                    </Button>
                                )}
                                {createStep === "identity" ? (
                                    <Button type="button" className="rounded-full px-4" onClick={() => setCreateStep("details")}>
                                        Continue <ArrowRight className="size-4" />
                                    </Button>
                                ) : (
                                    <Button type="submit" className="rounded-full px-4" disabled={createForm.processing}>
                                        {createForm.processing ? "Creating…" : "Create program"} <ArrowUpRight className="size-4" />
                                    </Button>
                                )}
                            </div>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </AdminLayout>
    );
}
