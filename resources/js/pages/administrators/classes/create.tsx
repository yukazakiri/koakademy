import AdminLayout from "@/components/administrators/admin-layout";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { index, show } from "@/routes/administrators/classes";
import type { User } from "@/types/user";
import { Head, Link } from "@inertiajs/react";
import { ArrowLeft, ArrowUpRight, BookOpen, Calendar, GraduationCap } from "lucide-react";
import type { ReactNode } from "react";
import { ClassWizard } from "./forms/class-wizard";
import type { ClassFormData, ClassFormOptions } from "./lib/class-defaults";

type CreateClassProps = {
    user: User;
    filament: {
        classes: {
            index_url: string;
        };
    };
    options: ClassFormOptions;
    defaults:
        | ClassFormData
        | {
              semester: "1" | "2" | "summer";
              school_year: string;
          };
    mode?: "create" | "edit";
    class_id?: number;
    class_title?: string;
    flash: { type: string; message: string } | null;
};

export default function AdministratorClassCreate({
    user,
    options,
    defaults,
    mode = "create",
    class_id,
    class_title,
    flash,
}: CreateClassProps): ReactNode {
    const isEditing = mode === "edit";
    const semester = defaults.semester;
    const schoolYear = defaults.school_year;
    const initialData = isEditing ? (defaults as ClassFormData) : undefined;
    const termLabel = options.semesters.find((term) => term.value === semester)?.label ?? semester;

    return (
        <AdminLayout user={user} title={isEditing ? "Edit class" : "Create class"}>
            <Head title={isEditing ? `Edit class • ${class_title ?? "Class"}` : "Create new class"} />

            <div className="mx-auto flex w-full max-w-screen-2xl flex-col gap-6 pb-12">
                {/* Header Banner */}
                <header className="bg-card relative overflow-hidden rounded-2xl border p-6 shadow-xs sm:p-7">
                    <div className="flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between">
                        <div className="flex min-w-0 flex-col gap-3">
                            <div className="flex items-center gap-2">
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    nativeButton={false}
                                    render={<Link href={index.url()} />}
                                    className="text-muted-foreground hover:text-foreground -ml-2 h-8 gap-1.5 px-2.5 text-xs font-semibold"
                                >
                                    <ArrowLeft className="size-3.5" aria-hidden="true" />
                                    Classes
                                </Button>
                                <span className="text-muted-foreground/60 text-xs">/</span>
                                <span className="text-muted-foreground text-xs font-medium">
                                    {isEditing ? "Edit class offering" : "New class offering"}
                                </span>
                            </div>

                            <div className="min-w-0 space-y-1">
                                <div className="flex items-center gap-2.5">
                                    <div className="bg-primary/10 text-primary flex size-9 shrink-0 items-center justify-center rounded-xl">
                                        {isEditing ? <GraduationCap className="size-5" /> : <BookOpen className="size-5" />}
                                    </div>
                                    <h1 className="text-foreground text-2xl font-bold tracking-tight text-balance sm:text-3xl">
                                        {isEditing ? class_title || "Edit class" : "Create new class"}
                                    </h1>
                                </div>
                                <p className="text-muted-foreground max-w-2xl pl-11.5 text-xs text-pretty sm:text-sm">
                                    {isEditing
                                        ? "Update curriculum placement, faculty assignment, and weekly recurring timetable for this class offering."
                                        : "Configure curriculum placement, instructor assignment, and weekly sessions. Classroom policies and themes are managed by faculty."}
                                </p>
                            </div>
                        </div>

                        <div className="flex flex-wrap items-center gap-2 self-start sm:pt-2">
                            <Badge
                                variant="outline"
                                className="border-border/80 bg-muted/40 text-foreground gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold backdrop-blur"
                            >
                                <Calendar className="text-primary size-3.5" />
                                {schoolYear} · {termLabel}
                            </Badge>

                            {isEditing && class_id ? (
                                <Button
                                    variant="outline"
                                    size="sm"
                                    className="h-9 gap-1.5 rounded-lg px-3 text-xs font-semibold shadow-2xs"
                                    nativeButton={false}
                                    render={<Link href={show.url(class_id)} />}
                                >
                                    <ArrowUpRight className="size-3.5" aria-hidden="true" />
                                    Open record
                                </Button>
                            ) : null}
                        </div>
                    </div>
                </header>

                {flash ? (
                    <div className="border-primary/20 bg-primary/5 text-foreground rounded-xl border px-4 py-3 text-sm shadow-2xs" role="status">
                        {flash.message}
                    </div>
                ) : null}

                {/* Main Stepper Wizard */}
                <ClassWizard
                    semester={semester}
                    school_year={schoolYear}
                    options={options}
                    mode={mode}
                    classId={class_id}
                    initialData={initialData}
                />
            </div>
        </AdminLayout>
    );
}
