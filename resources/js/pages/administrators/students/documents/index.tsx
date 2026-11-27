import AdminLayout from "@/components/administrators/admin-layout";
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar";
import { Badge } from "@/components/ui/badge";
import { Button, buttonVariants } from "@/components/ui/button";
import type { User } from "@/types/user";
import { Head, Link, usePage } from "@inertiajs/react";
import { ArrowLeft, ExternalLink, Pencil } from "lucide-react";
import { getInitials } from "../columns";
import { DocumentStudioCards } from "../components/document-studio-cards";
import type { DocumentDossier, IdentifierPolicy, SchoolProfile } from "../shared-types";

interface StudentDocumentsIndexProps {
    student: {
        id: number;
        student_id: string | null;
        lrn?: string | null;
        full_name: string;
        email?: string | null;
        profile_url?: string | null;
        course?: { code: string; title: string } | null;
        academic_year?: string | null;
        student_type?: string | null;
    };
    dossier: DocumentDossier;
    school_profile?: SchoolProfile;
    identifier_policy?: IdentifierPolicy;
    export_zip_url?: string | null;
}

export default function AdministratorStudentDocumentsIndex({
    student,
    dossier,
    school_profile,
    identifier_policy,
    export_zip_url,
}: StudentDocumentsIndexProps) {
    const { auth } = usePage<{ auth: { user: User } }>().props;
    const idLabel = identifier_policy?.display_label || "ID";

    return (
        <AdminLayout user={auth.user} title={`Documents • ${student.full_name}`}>
            <Head title={`Administrators • Documents • ${student.full_name}`} />

            <div className="mx-auto flex w-full max-w-[1440px] flex-col gap-5">
                {/* Header */}
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                    <div className="flex items-center gap-3.5">
                        <Avatar className="border-border size-12 border">
                            <AvatarImage src={student.profile_url ?? undefined} alt={student.full_name} />
                            <AvatarFallback className="bg-primary/10 text-primary font-semibold">
                                {getInitials(student.full_name)}
                            </AvatarFallback>
                        </Avatar>
                        <div className="space-y-0.5">
                            <div className="flex flex-wrap items-center gap-2">
                                <span className="text-muted-foreground text-[11px] font-semibold tracking-wider uppercase">
                                    Student Document Studio
                                </span>
                                {school_profile && (
                                    <Badge variant="secondary" className="text-[10px]">
                                        {school_profile.school_level_label}
                                    </Badge>
                                )}
                            </div>
                            <h1 className="text-xl font-semibold tracking-tight sm:text-2xl">{student.full_name}</h1>
                            <p className="text-muted-foreground flex flex-wrap items-center gap-1.5 text-xs">
                                <span className="font-mono">
                                    {idLabel}: {student.student_id || `#${student.id}`}
                                </span>
                                {student.course && (
                                    <>
                                        <span>·</span>
                                        <span>{student.course.code}</span>
                                    </>
                                )}
                                {student.academic_year && (
                                    <>
                                        <span>·</span>
                                        <span>{student.academic_year}</span>
                                    </>
                                )}
                            </p>
                        </div>
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        <Link
                            href="/administrators/students/documents"
                            className={buttonVariants({ variant: "outline", size: "sm", className: "h-9 gap-1.5 text-xs" })}
                        >
                            <ArrowLeft className="size-3.5" />
                            Documents Hub
                        </Link>
                        <Button
                            render={<Link href={`/administrators/students/${student.id}`} />}
                            nativeButton={false}
                            variant="outline"
                            size="sm"
                            className="h-9 gap-1.5 text-xs"
                        >
                            <ExternalLink className="size-3.5" />
                            Student Profile
                        </Button>
                        <Button
                            render={<Link href={`/administrators/students/${student.id}/edit`} />}
                            nativeButton={false}
                            size="sm"
                            className="h-9 gap-1.5 text-xs"
                        >
                            <Pencil className="size-3.5" />
                            Edit Student
                        </Button>
                    </div>
                </div>

                {/* Unified Visual Folder Checklist Studio */}
                <DocumentStudioCards
                    studentId={student.id}
                    studentName={student.full_name}
                    dossier={dossier}
                    exportZipUrl={export_zip_url}
                />
            </div>
        </AdminLayout>
    );
}
