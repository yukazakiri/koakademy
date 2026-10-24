import AdminLayout from "@/components/administrators/admin-layout";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { index, show } from "@/routes/administrators/classes";
import type { User } from "@/types/user";
import { Head, Link } from "@inertiajs/react";
import { ArrowLeft, ArrowUpRight } from "lucide-react";
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

    return (
        <AdminLayout user={user} title={isEditing ? "Edit class" : "Create class"}>
            <Head title={isEditing ? `Edit class • ${class_title ?? "Class"}` : "Create class"} />

            <div className="mx-auto flex w-full max-w-screen-2xl flex-col gap-6">
                <header className="bg-card flex flex-col gap-4 rounded-xl border p-6 sm:flex-row sm:items-start sm:justify-between">
                    <div className="flex min-w-0 flex-col gap-3">
                        <Button
                            variant="ghost"
                            size="sm"
                            nativeButton={false}
                            render={<Link href={index.url()} />}
                            className="text-muted-foreground hover:text-foreground -ml-2 h-11 w-fit px-3 text-sm"
                        >
                            <ArrowLeft className="size-4" aria-hidden="true" />
                            Back to classes
                        </Button>

                        <div className="min-w-0">
                            <p className="text-muted-foreground text-sm font-medium">Academic operations / Classes</p>
                            <h1 className="mt-1 text-3xl font-semibold tracking-tight text-balance">
                                {isEditing ? class_title || "Edit class" : "New class"}
                            </h1>
                            <p className="text-muted-foreground mt-2 max-w-2xl text-sm text-pretty">
                                {isEditing
                                    ? "Manage the class details, teaching assignment, and timetable in one workspace. Review your changes before saving."
                                    : "Set up a class with a clear curriculum, teaching assignment, and weekly timetable. Review everything before creating the record."}
                            </p>
                        </div>
                    </div>

                    <div className="flex flex-wrap items-center gap-2 sm:pt-10">
                        <Badge variant="outline" className="px-3 py-2 text-sm tabular-nums">
                            {schoolYear} · {options.semesters.find((term) => term.value === semester)?.label ?? semester}
                        </Badge>
                        {isEditing && class_id ? (
                            <Button
                                variant="outline"
                                size="sm"
                                className="h-11 px-3 text-sm"
                                nativeButton={false}
                                render={<Link href={show.url(class_id)} />}
                            >
                                <ArrowUpRight className="size-4" aria-hidden="true" />
                                Open record
                            </Button>
                        ) : null}
                    </div>
                </header>

                {flash ? (
                    <div className="bg-card rounded-lg border px-4 py-3 text-sm" role="status">
                        {flash.message}
                    </div>
                ) : null}

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
