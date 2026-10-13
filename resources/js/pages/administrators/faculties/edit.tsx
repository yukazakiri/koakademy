import AdminLayout from "@/components/administrators/admin-layout";
import { Badge } from "@/components/reui/badge";
import { buttonVariants } from "@/components/ui/button";
import type { User } from "@/types/user";
import { Head, Link } from "@inertiajs/react";
import { ArrowLeft, UserCog } from "lucide-react";
import { route } from "ziggy-js";
import { FacultyForm, type FacultyFormPayload } from "./faculty-form";

type Option = { value: string; label: string };

interface FacultyEditProps {
    user: User;
    faculty: FacultyFormPayload;
    options: {
        departments: string[];
        statuses: Option[];
        genders: Option[];
    };
}

export default function AdministratorFacultyEdit({ user, faculty, options }: FacultyEditProps) {
    const fullName = [faculty.first_name, faculty.middle_name, faculty.last_name].filter(Boolean).join(" ");

    return (
        <AdminLayout user={user} title={`Edit Faculty - ${fullName}`}>
            <Head title={`Administrators - Edit Faculty - ${fullName}`} />

            <div className="space-y-6">
                <div className="flex flex-col gap-3 border-b pb-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="space-y-1">
                        <div className="text-muted-foreground flex items-center gap-2 text-xs">
                            <Link href={route("administrators.faculties.index")} className="hover:text-foreground transition-colors">
                                Faculty Directory
                            </Link>
                            <span>/</span>
                            <Link href={route("administrators.faculties.show", faculty.id ?? "")} className="hover:text-foreground transition-colors">
                                {fullName}
                            </Link>
                            <span>/</span>
                            <span className="text-foreground font-medium">Edit Profile</span>
                        </div>
                        <div className="flex items-center gap-2.5">
                            <h2 className="text-2xl font-bold tracking-tight">Edit Faculty Profile</h2>
                            <Badge variant="primary-light" size="sm" radius="full" className="gap-1 text-xs">
                                <UserCog className="h-3 w-3" /> #{faculty.faculty_id_number || "No ID"}
                            </Badge>
                        </div>
                        <p className="text-muted-foreground text-sm">
                            Keep academic credentials, contact information, consultation hours, and department assignments synchronized.
                        </p>
                    </div>

                    <div className="flex items-center gap-2">
                        <Link
                            href={route("administrators.faculties.show", faculty.id ?? "")}
                            className={buttonVariants({ variant: "outline", size: "sm" })}
                        >
                            <ArrowLeft className="mr-1.5 h-4 w-4" /> View Details
                        </Link>
                    </div>
                </div>

                <FacultyForm mode="edit" faculty={faculty} options={options} />
            </div>
        </AdminLayout>
    );
}
