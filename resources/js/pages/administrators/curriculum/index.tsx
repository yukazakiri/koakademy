import AdminLayout from "@/components/administrators/admin-layout";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import type { User } from "@/types/user";
import { Head, Link } from "@inertiajs/react";
import {
    Activity,
    BookOpen,
    Building2,
    ChevronRight,
    GraduationCap,
    Layers,
    Search,
} from "lucide-react";
import { useMemo, useState } from "react";
import { route } from "ziggy-js";

interface DepartmentItem {
    id: number;
    name: string;
    code: string;
    is_active: boolean;
    courses_count: number;
    active_courses_count: number;
    subjects_count: number;
}

interface CurriculumVersion {
    curriculum_year: string;
    program_count: number;
    active_program_count: number;
    subject_count: number;
}

interface CurriculumIndexProps {
    user: User;
    stats: {
        departments: number;
        active_departments: number;
        programs: number;
        active_programs: number;
        subjects: number;
        curriculum_versions: number;
    };
    departments: DepartmentItem[];
    versions: CurriculumVersion[];
}

const departmentColors = [
    "from-blue-500/10 to-blue-600/5 border-blue-200/50 dark:border-blue-800/30",
    "from-violet-500/10 to-violet-600/5 border-violet-200/50 dark:border-violet-800/30",
    "from-emerald-500/10 to-emerald-600/5 border-emerald-200/50 dark:border-emerald-800/30",
    "from-amber-500/10 to-amber-600/5 border-amber-200/50 dark:border-amber-800/30",
    "from-rose-500/10 to-rose-600/5 border-rose-200/50 dark:border-rose-800/30",
    "from-cyan-500/10 to-cyan-600/5 border-cyan-200/50 dark:border-cyan-800/30",
    "from-orange-500/10 to-orange-600/5 border-orange-200/50 dark:border-orange-800/30",
    "from-teal-500/10 to-teal-600/5 border-teal-200/50 dark:border-teal-800/30",
];

const codeColors = [
    "bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300",
    "bg-violet-100 text-violet-700 dark:bg-violet-900/40 dark:text-violet-300",
    "bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300",
    "bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300",
    "bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300",
    "bg-cyan-100 text-cyan-700 dark:bg-cyan-900/40 dark:text-cyan-300",
    "bg-orange-100 text-orange-700 dark:bg-orange-900/40 dark:text-orange-300",
    "bg-teal-100 text-teal-700 dark:bg-teal-900/40 dark:text-teal-300",
];

export default function CurriculumIndex({ user, stats, departments, versions }: CurriculumIndexProps) {
    const [searchQuery, setSearchQuery] = useState("");

    const departmentsWithCourses = useMemo(
        () => departments.filter((d) => d.courses_count > 0),
        [departments],
    );

    const filteredDepartments = useMemo(() => {
        if (!searchQuery.trim()) return departmentsWithCourses;
        const q = searchQuery.toLowerCase();
        return departmentsWithCourses.filter(
            (d) => d.name.toLowerCase().includes(q) || d.code.toLowerCase().includes(q),
        );
    }, [departmentsWithCourses, searchQuery]);

    return (
        <AdminLayout user={user} title="Curriculum Management">
            <Head title="Curriculum Management" />
            <div className="flex flex-col gap-6">
                {/* Page Header */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-4">
                        <div className="bg-primary/10 rounded-xl p-3">
                            <GraduationCap className="text-primary size-6" />
                        </div>
                        <div>
                            <h1 className="text-2xl font-bold tracking-tight">Curriculum Management</h1>
                            <p className="text-muted-foreground text-sm">
                                Create and manage courses across departments, assign subjects and configure curriculum settings.
                            </p>
                        </div>
                    </div>
                    <Button asChild>
                        <Link href={route("administrators.curriculum.programs.index")}>
                            View All Programs
                            <ChevronRight className="ml-1 size-4" />
                        </Link>
                    </Button>
                </div>

                {/* Stats */}
                <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <Card className="border-none shadow-sm">
                        <CardHeader className="pb-2">
                            <CardDescription className="text-muted-foreground flex items-center gap-2 text-xs font-medium tracking-wide uppercase">
                                <Building2 className="h-4 w-4" />
                                Departments
                            </CardDescription>
                            <CardTitle className="text-foreground text-3xl font-bold">{stats.departments}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-muted-foreground text-sm">
                                <span className="font-semibold text-emerald-500">{departmentsWithCourses.length}</span> with courses
                            </p>
                        </CardContent>
                    </Card>

                    <Card className="border-none shadow-sm">
                        <CardHeader className="pb-2">
                            <CardDescription className="text-muted-foreground flex items-center gap-2 text-xs font-medium tracking-wide uppercase">
                                <GraduationCap className="h-4 w-4" />
                                Programs
                            </CardDescription>
                            <CardTitle className="text-foreground text-3xl font-bold">{stats.programs}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-muted-foreground text-sm">
                                <span className="font-semibold text-emerald-500">{stats.active_programs}</span> active
                            </p>
                        </CardContent>
                    </Card>

                    <Card className="border-none shadow-sm">
                        <CardHeader className="pb-2">
                            <CardDescription className="text-muted-foreground flex items-center gap-2 text-xs font-medium tracking-wide uppercase">
                                <Layers className="h-4 w-4" />
                                Total Subjects
                            </CardDescription>
                            <CardTitle className="text-foreground text-3xl font-bold">{stats.subjects}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-muted-foreground text-sm">Across all programs</p>
                        </CardContent>
                    </Card>

                    <Card className="border-none shadow-sm">
                        <CardHeader className="pb-2">
                            <CardDescription className="text-muted-foreground flex items-center gap-2 text-xs font-medium tracking-wide uppercase">
                                <Activity className="h-4 w-4" />
                                Curriculum Versions
                            </CardDescription>
                            <CardTitle className="text-foreground text-3xl font-bold">{stats.curriculum_versions}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-muted-foreground text-sm">Active curriculum years</p>
                        </CardContent>
                    </Card>
                </div>

                {/* Department Hub Section */}
                <Card className="border shadow-sm">
                    <CardHeader className="border-b pb-4">
                        <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <CardTitle className="flex items-center gap-2 text-lg">
                                    <Building2 className="text-muted-foreground size-5" />
                                    Departments with Programs
                                </CardTitle>
                                <CardDescription>
                                    Select a department to view and manage its courses.
                                </CardDescription>
                            </div>
                            <div className="relative w-full sm:w-64">
                                <Search className="text-muted-foreground absolute top-2.5 left-2.5 h-4 w-4" />
                                <Input
                                    id="department-search"
                                    placeholder="Search departments..."
                                    value={searchQuery}
                                    onChange={(e) => setSearchQuery(e.target.value)}
                                    className="bg-background h-9 w-full pl-9"
                                />
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent className="p-4 md:p-6">
                        {filteredDepartments.length === 0 ? (
                            <div className="text-muted-foreground flex flex-col items-center justify-center py-16">
                                <Building2 className="mb-3 size-10 opacity-40" />
                                <p className="font-medium">
                                    {searchQuery.trim() ? "No departments match your search." : "No departments with programs yet."}
                                </p>
                                <p className="mt-1 text-sm">
                                    {searchQuery.trim() ? "Try a different search term." : "Create your first program to get started."}
                                </p>
                            </div>
                        ) : (
                            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                                {filteredDepartments.map((dept, index) => {
                                    const colorIdx = index % departmentColors.length;
                                    return (
                                        <Link
                                            key={dept.id}
                                            href={`${route("administrators.curriculum.programs.index")}?department=${dept.id}`}
                                            className="group"
                                        >
                                            <Card
                                                className={`h-full border bg-gradient-to-br transition-all duration-200 hover:shadow-md hover:-translate-y-0.5 ${departmentColors[colorIdx]}`}
                                            >
                                                <CardContent className="p-4">
                                                    <div className="flex items-start justify-between gap-3">
                                                        <div className="min-w-0 flex-1">
                                                            <Badge
                                                                variant="secondary"
                                                                className={`mb-2 text-[11px] font-bold tracking-wider ${codeColors[colorIdx]}`}
                                                            >
                                                                {dept.code}
                                                            </Badge>
                                                            <h3 className="text-foreground group-hover:text-primary truncate text-sm font-semibold transition-colors">
                                                                {dept.name}
                                                            </h3>
                                                        </div>
                                                        <ChevronRight className="text-muted-foreground mt-1 size-4 shrink-0 opacity-0 transition-opacity group-hover:opacity-100" />
                                                    </div>
                                                    <div className="mt-3 flex items-center gap-3 text-xs">
                                                        <div className="text-muted-foreground flex items-center gap-1">
                                                            <BookOpen className="size-3.5" />
                                                            <span className="text-foreground font-semibold">{dept.courses_count}</span>
                                                            <span>courses</span>
                                                        </div>
                                                        <div className="bg-border h-3 w-px" />
                                                        <div className="text-muted-foreground flex items-center gap-1">
                                                            <Layers className="size-3.5" />
                                                            <span className="text-foreground font-semibold">{dept.subjects_count}</span>
                                                            <span>subjects</span>
                                                        </div>
                                                    </div>
                                                    {dept.active_courses_count < dept.courses_count && (
                                                        <div className="text-muted-foreground mt-2 text-[11px]">
                                                            {dept.active_courses_count} of {dept.courses_count} active
                                                        </div>
                                                    )}
                                                </CardContent>
                                            </Card>
                                        </Link>
                                    );
                                })}
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Versions Summary */}
                {versions.length > 0 && (
                    <Card className="border shadow-sm">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-lg">
                                <Activity className="text-muted-foreground size-5" />
                                Curriculum Versions
                            </CardTitle>
                            <CardDescription>Distribution of programs by curriculum year.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                                {versions.map((version) => (
                                    <Link
                                        key={version.curriculum_year}
                                        href={`${route("administrators.curriculum.programs.index")}?year=${version.curriculum_year}`}
                                        className="group"
                                    >
                                        <div className="bg-muted/30 hover:bg-muted/50 flex items-center justify-between rounded-lg border p-3 transition-colors">
                                            <div>
                                                <Badge
                                                    variant="outline"
                                                    className="text-foreground group-hover:bg-primary/10 group-hover:text-primary mb-1 font-medium transition-colors"
                                                >
                                                    {version.curriculum_year}
                                                </Badge>
                                                <div className="text-muted-foreground mt-1 text-xs">
                                                    {version.program_count} programs · {version.subject_count} subjects
                                                </div>
                                            </div>
                                            <ChevronRight className="text-muted-foreground size-4 opacity-0 transition-opacity group-hover:opacity-100" />
                                        </div>
                                    </Link>
                                ))}
                            </div>
                        </CardContent>
                    </Card>
                )}
            </div>
        </AdminLayout>
    );
}
