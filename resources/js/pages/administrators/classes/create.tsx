import AdminLayout from "@/components/administrators/admin-layout";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import type { User } from "@/types/user";
import { Head, Link } from "@inertiajs/react";
import { ArrowLeft, Pencil } from "lucide-react";
import type { ReactNode } from "react";
import { route } from "ziggy-js";
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

            <div className="mx-auto flex w-full max-w-[1600px] flex-col gap-6">
                <header className="flex flex-col gap-4 border-b pb-5 sm:flex-row sm:items-start sm:justify-between">
                    <div className="flex min-w-0 flex-col gap-3">
                        <Button
                            variant="ghost"
                            size="sm"
                            nativeButton={false}
                            render={<Link href={route("administrators.classes.index")} />}
                            className="text-muted-foreground hover:text-foreground -ml-2 w-fit"
                        >
                            <ArrowLeft className="size-4" />
                            Back to classes
                        </Button>

                        <div className="min-w-0">
                            <p className="text-muted-foreground text-xs font-semibold uppercase tracking-widest">Academics · Class sections</p>
                            <h1 className="mt-1 truncate text-3xl font-semibold tracking-tight text-balance">
                                {isEditing ? class_title || "Edit class" : "New class"}
                            </h1>
                            <p className="text-muted-foreground mt-1.5 max-w-2xl text-sm text-pretty">
                                {isEditing
                                    ? "Change the class and its schedule. Nothing is saved until you confirm on the last step."
                                    : "Set up a class in five short steps. Nothing is saved until you confirm on the last step."}
                            </p>
                        </div>
                    </div>

                    <div className="flex flex-wrap items-center gap-2 sm:pt-10">
                        <Badge variant="outline" className="tabular-nums">
                            {schoolYear} · Semester {semester}
                        </Badge>
                        {isEditing ? (
                            <Button
                                variant="outline"
                                size="sm"
                                nativeButton={false}
                                render={<Link href={route("administrators.classes.show", { class: class_id })} />}
                            >
                                <Pencil className="size-4" />
                                Open record
                            </Button>
                        ) : null}
                    </div>
                </header>

                {flash ? (
                    <div className="rounded-lg border bg-card px-4 py-3 text-sm" role="status">
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
