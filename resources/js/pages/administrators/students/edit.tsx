import AdminLayout from "@/components/administrators/admin-layout";
import { Badge } from "@/components/ui/badge";
import { buttonVariants } from "@/components/ui/button";
import type { User } from "@/types/user";
import { Head, Link } from "@inertiajs/react";
import { ArrowLeft, ExternalLink, FileCheck2 } from "lucide-react";
import { StudentFormStudio, type StudentFormOptions } from "./components/student-form-studio";
import type { StudentPortalAccountSummary } from "./components/student-portal-account-card";

interface EditStudentPageProps {
    user: User;
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    student: Record<string, any>;
    portal_account?: StudentPortalAccountSummary | null;
    student_assets?: {
        picture_1x1_url?: string | null;
        signature_url?: string | null;
    };
    options: StudentFormOptions;
}

export default function AdministratorStudentsEdit({
    user,
    student,
    portal_account,
    student_assets,
    options,
}: EditStudentPageProps) {
    const fullName =
        student.full_name ||
        [student.first_name, student.middle_name, student.last_name].filter(Boolean).join(" ") ||
        `Student #${student.id}`;

    return (
        <AdminLayout user={user} title={`Edit ${fullName}`}>
            <Head title={`Administrators • Edit • ${fullName}`} />

            <div className="mx-auto flex w-full max-w-[1440px] flex-col gap-5">
                <div className="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                    <div className="space-y-1">
                        <div className="flex flex-wrap items-center gap-2">
                            <span className="text-muted-foreground text-[11px] font-semibold tracking-wider uppercase">
                                Student Records · Edit
                            </span>
                            <Badge variant="outline" className="font-mono text-[10px]">
                                {student.student_id || `#${student.id}`}
                            </Badge>
                            {options.school_profile && (
                                <Badge variant="secondary" className="text-[10px]">
                                    {options.school_profile.school_level_label}
                                </Badge>
                            )}
                        </div>
                        <h1 className="text-2xl font-semibold tracking-tight">{fullName}</h1>
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        <Link
                            href={`/administrators/students/${student.id}/documents`}
                            className={buttonVariants({ variant: "outline", size: "sm", className: "h-9 gap-1.5 text-xs" })}
                        >
                            <FileCheck2 className="size-3.5" />
                            Documents
                        </Link>
                        <Link
                            href={`/administrators/students/${student.id}`}
                            className={buttonVariants({ variant: "outline", size: "sm", className: "h-9 gap-1.5 text-xs" })}
                        >
                            <ExternalLink className="size-3.5" />
                            View Profile
                        </Link>
                        <Link
                            href="/administrators/students"
                            className={buttonVariants({ variant: "ghost", size: "sm", className: "h-9 gap-1.5 text-xs" })}
                        >
                            <ArrowLeft className="size-3.5" />
                            Directory
                        </Link>
                    </div>
                </div>

                <StudentFormStudio
                    mode="edit"
                    options={options}
                    student={student}
                    portalAccount={portal_account}
                    studentAssets={student_assets}
                />
            </div>
        </AdminLayout>
    );
}
