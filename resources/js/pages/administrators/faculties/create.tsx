import AdminLayout from "@/components/administrators/admin-layout";
import { Badge } from "@/components/reui/badge";
import { buttonVariants } from "@/components/ui/button";
import type { User } from "@/types/user";
import { Head, Link } from "@inertiajs/react";
import { ArrowLeft, UserPlus } from "lucide-react";
import { route } from "ziggy-js";
import { FacultyForm } from "./faculty-form";

type Option = { value: string; label: string };

interface FacultyCreateProps {
    user: User;
    defaults: {
        faculty_id_number: string;
        status: string;
    };
    options: {
        departments: string[];
        statuses: Option[];
        genders: Option[];
    };
}

export default function AdministratorFacultyCreate({ user, defaults, options }: FacultyCreateProps) {
    return (
        <AdminLayout user={user} title="Create Faculty Profile">
            <Head title="Administrators - Add Faculty" />

            <div className="space-y-6">
                <div className="flex flex-col gap-3 border-b pb-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="space-y-1">
                        <div className="text-muted-foreground flex items-center gap-2 text-xs">
                            <Link href={route("administrators.faculties.index")} className="hover:text-foreground transition-colors">
                                Faculty Directory
                            </Link>
                            <span>/</span>
                            <span className="text-foreground font-medium">New Faculty</span>
                        </div>
                        <div className="flex items-center gap-2.5">
                            <h2 className="text-2xl font-bold tracking-tight">Add New Faculty</h2>
                            <Badge variant="primary-light" size="sm" radius="full" className="gap-1 text-xs">
                                <UserPlus className="h-3 w-3" /> Registration
                            </Badge>
                        </div>
                        <p className="text-muted-foreground text-sm">
                            Configure academic credentials, institutional contact details, and initial status for this faculty member.
                        </p>
                    </div>

                    <Link href={route("administrators.faculties.index")} className={buttonVariants({ variant: "outline", size: "sm" })}>
                        <ArrowLeft className="mr-1.5 h-4 w-4" /> Back to Directory
                    </Link>
                </div>

                <FacultyForm mode="create" defaults={{ ...defaults, photo_url: null }} options={options} />
            </div>
        </AdminLayout>
    );
}
