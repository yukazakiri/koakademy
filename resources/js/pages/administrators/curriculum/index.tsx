import AdminLayout from "@/components/administrators/admin-layout";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import type { User } from "@/types/user";
import { Head, Link } from "@inertiajs/react";
import { Activity, ExternalLink, GraduationCap, Layers, ListChecks, Plus } from "lucide-react";
import { route } from "ziggy-js";

interface CurriculumIndexProps {
    user: User;
    stats: {
        programs: number;
        active_programs: number;
        subjects: number;
        subjects_with_requisites: number;
        curriculum_versions: number;
    };
    latest_programs: ProgramSummary[];
    versions: CurriculumVersion[];
    filament: {
        courses: {
            index_url: string;
            create_url: string;
        };
    };
}

type ProgramSummary = {
    id: number;
    code: string;
    title: string;
    department: string | null;
    curriculum_year: string | null;
    subjects_count: number;
    prerequisites_count: number;
    is_active: boolean;
};

type CurriculumVersion = {
    curriculum_year: string;
    program_count: number;
    active_program_count: number;
    subject_count: number;
};

export default function CurriculumIndex({ user, stats, latest_programs, versions, filament }: CurriculumIndexProps) {
    return (
        <AdminLayout user={user} title="Curriculum Overview">
            <Head title="Curriculum Overview" />
            <div className="flex flex-col gap-6">
                {/* Welcome Hero / Stats Banner */}
                <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
                    <Card className="from-primary/10 to-primary/5 border-none bg-gradient-to-br shadow-sm">
                        <CardHeader className="pb-2">
                            <CardDescription className="text-primary flex items-center gap-2 text-xs font-medium tracking-wide uppercase">
                                <GraduationCap className="h-4 w-4" />
                                Total Programs
                            </CardDescription>
                            <CardTitle className="text-primary text-4xl">{stats.programs}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-primary/80 text-sm">
                                <span className="font-semibold">{stats.active_programs}</span> currently active
                            </p>
                        </CardContent>
                    </Card>

                    <Card className="bg-card border-none shadow-sm">
                        <CardHeader className="pb-2">
                            <CardDescription className="text-muted-foreground flex items-center gap-2 text-xs font-medium tracking-wide uppercase">
                                <Layers className="h-4 w-4" />
                                Subjects
                            </CardDescription>
                            <CardTitle className="text-foreground text-4xl">{stats.subjects}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-muted-foreground text-sm">Total entries across all programs</p>
                        </CardContent>
                    </Card>

                    <Card className="bg-card border-none shadow-sm">
                        <CardHeader className="pb-2">
                            <CardDescription className="text-muted-foreground flex items-center gap-2 text-xs font-medium tracking-wide uppercase">
                                <ListChecks className="h-4 w-4" />
                                Prerequisites
                            </CardDescription>
                            <CardTitle className="text-foreground text-4xl">{stats.subjects_with_requisites}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-muted-foreground text-sm">Subjects requiring prior completion</p>
                        </CardContent>
                    </Card>

                    <Card className="bg-card border-none shadow-sm">
                        <CardHeader className="pb-2">
                            <CardDescription className="text-muted-foreground flex items-center gap-2 text-xs font-medium tracking-wide uppercase">
                                <Activity className="h-4 w-4" />
                                Versions
                            </CardDescription>
                            <CardTitle className="text-foreground text-4xl">{stats.curriculum_versions}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-muted-foreground text-sm">Active curriculum years assigned</p>
                        </CardContent>
                    </Card>
                </div>

                {/* Quick Actions */}
                <Card className="border shadow-sm">
                    <div className="flex flex-col items-center justify-between gap-4 p-6 sm:flex-row">
                        <div>
                            <h2 className="text-lg font-semibold">Administration & Management</h2>
                            <p className="text-muted-foreground text-sm">Manage program structures, update subjects, and configure requirements.</p>
                        </div>
                        <div className="flex w-full flex-wrap items-center gap-3 sm:w-auto">
                            <Button asChild className="w-full sm:w-auto">
                                <Link href="/administrators/curriculum/programs">View Program Datagrid</Link>
                            </Button>
                            <Button asChild variant="outline" className="w-full sm:w-auto">
                                <a href={filament.courses.create_url} target="_blank" rel="noreferrer">
                                    <Plus className="mr-2 size-4" />
                                    Create Program
                                </a>
                            </Button>
                            <Button asChild variant="secondary" className="w-full sm:w-auto">
                                <a href={filament.courses.index_url} target="_blank" rel="noreferrer">
                                    <ExternalLink className="mr-2 size-4" />
                                    Filament Editor
                                </a>
                            </Button>
                        </div>
                    </div>
                </Card>

                {/* Tables section */}
                <div className="grid gap-6 lg:grid-cols-[2fr_1fr]">
                    <Card className="border shadow-sm">
                        <CardHeader>
                            <CardTitle className="text-lg">Recent Programs</CardTitle>
                            <CardDescription>A quick look at the latest registered programs.</CardDescription>
                        </CardHeader>
                        <CardContent className="p-0">
                            <Table>
                                <TableHeader className="bg-muted/30">
                                    <TableRow>
                                        <TableHead className="py-3 pl-6">Program</TableHead>
                                        <TableHead className="py-3">Curriculum Year</TableHead>
                                        <TableHead className="py-3 text-right">Subjects</TableHead>
                                        <TableHead className="py-3 pr-6 text-right">Prereqs</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {latest_programs.length === 0 ? (
                                        <TableRow>
                                            <TableCell colSpan={4} className="text-muted-foreground h-32 text-center">
                                                No programs created yet.
                                            </TableCell>
                                        </TableRow>
                                    ) : (
                                        latest_programs.map((program) => (
                                            <TableRow key={program.id} className="hover:bg-muted/20">
                                                <TableCell className="pl-6">
                                                    <div className="flex items-center gap-2">
                                                        <Link
                                                            href={route("administrators.curriculum.programs.show", program.id)}
                                                            className="text-foreground hover:text-primary font-semibold transition-colors"
                                                        >
                                                            {program.code}
                                                        </Link>
                                                        {program.is_active ? (
                                                            <Badge
                                                                variant="secondary"
                                                                className="bg-emerald-100 text-emerald-800 dark:bg-emerald-900 dark:text-emerald-300"
                                                            >
                                                                Active
                                                            </Badge>
                                                        ) : (
                                                            <Badge variant="outline" className="text-muted-foreground">
                                                                Inactive
                                                            </Badge>
                                                        )}
                                                    </div>
                                                    <div className="text-muted-foreground mt-0.5 text-xs">{program.title}</div>
                                                </TableCell>
                                                <TableCell>
                                                    <span className="text-foreground text-sm font-medium">
                                                        {program.curriculum_year ?? "Unassigned"}
                                                    </span>
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    <span className="text-foreground text-sm font-medium">{program.subjects_count}</span>
                                                </TableCell>
                                                <TableCell className="pr-6 text-right">
                                                    <span className="text-muted-foreground text-sm">{program.prerequisites_count}</span>
                                                </TableCell>
                                            </TableRow>
                                        ))
                                    )}
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>

                    <Card className="h-fit border shadow-sm">
                        <CardHeader>
                            <CardTitle className="text-lg">Versions Summary</CardTitle>
                            <CardDescription>Distribution of programs by curriculum year.</CardDescription>
                        </CardHeader>
                        <CardContent className="p-0">
                            <Table>
                                <TableHeader className="bg-muted/30">
                                    <TableRow>
                                        <TableHead className="py-3 pl-6">Year</TableHead>
                                        <TableHead className="py-3 pr-6 text-right">Programs</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {versions.length === 0 ? (
                                        <TableRow>
                                            <TableCell colSpan={2} className="text-muted-foreground h-24 text-center">
                                                No curriculum years tracked yet.
                                            </TableCell>
                                        </TableRow>
                                    ) : (
                                        versions.map((version) => (
                                            <TableRow key={version.curriculum_year} className="hover:bg-muted/20">
                                                <TableCell className="pl-6">
                                                    <Link href={`/administrators/curriculum/programs?year=${version.curriculum_year}`}>
                                                        <Badge
                                                            variant="outline"
                                                            className="text-foreground hover:bg-primary/10 hover:text-primary cursor-pointer font-medium transition-colors"
                                                        >
                                                            {version.curriculum_year}
                                                        </Badge>
                                                    </Link>
                                                </TableCell>
                                                <TableCell className="pr-6 text-right">
                                                    <div className="text-foreground text-sm font-semibold">{version.program_count}</div>
                                                    <div className="text-muted-foreground text-xs">{version.subject_count} subjects</div>
                                                </TableCell>
                                            </TableRow>
                                        ))
                                    )}
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AdminLayout>
    );
}
