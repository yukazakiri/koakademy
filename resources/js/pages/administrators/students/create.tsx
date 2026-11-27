import AdminLayout from "@/components/administrators/admin-layout";
import { Badge } from "@/components/ui/badge";
import { buttonVariants } from "@/components/ui/button";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import type { User } from "@/types/user";
import { Head, Link, usePage } from "@inertiajs/react";
import { ArrowLeft, FileSpreadsheet, UserPlus } from "lucide-react";
import { useState } from "react";
import { StudentBatchIntake } from "./components/student-batch-intake";
import { StudentFormStudio, type StudentFormOptions } from "./components/student-form-studio";

interface CreateStudentPageProps {
    user: User;
    options: StudentFormOptions;
}

export default function AdministratorStudentsCreate({ user, options }: CreateStudentPageProps) {
    const pageUrl = usePage().url;
    const initialMode = pageUrl.includes("mode=batch") ? "batch" : "single";
    const [intakeMode, setIntakeMode] = useState<"single" | "batch">(initialMode);

    const firstCourseCode = options.courses[0]?.items[0]?.label?.split(" - ")[0] || options.shs_strands[0]?.label || "BSCS";

    return (
        <AdminLayout user={user} title="Add Student">
            <Head title="Administrators • Add Student" />

            <div className="mx-auto flex w-full max-w-[1440px] flex-col gap-5">
                <div className="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                    <div className="space-y-1">
                        <div className="flex items-center gap-2">
                            <span className="text-muted-foreground text-[11px] font-semibold tracking-wider uppercase">
                                Student Records · Intake
                            </span>
                            {options.school_profile && (
                                <Badge variant="secondary" className="text-[10px]">
                                    {options.school_profile.school_level_label}
                                </Badge>
                            )}
                        </div>
                        <h1 className="text-2xl font-semibold tracking-tight">Add Students</h1>
                    </div>

                    <Link
                        href="/administrators/students"
                        className={buttonVariants({ variant: "outline", size: "sm", className: "h-9 gap-1.5 text-xs" })}
                    >
                        <ArrowLeft className="size-3.5" />
                        Back to Directory
                    </Link>
                </div>

                <Tabs value={intakeMode} onValueChange={(v) => setIntakeMode(v as "single" | "batch")} className="w-full">
                    <TabsList>
                        <TabsTrigger value="single" className="gap-1.5 text-xs">
                            <UserPlus className="size-3.5" />
                            Guided Single Intake
                        </TabsTrigger>
                        <TabsTrigger value="batch" className="gap-1.5 text-xs">
                            <FileSpreadsheet className="size-3.5" />
                            Batch Spreadsheet Intake
                        </TabsTrigger>
                    </TabsList>

                    <TabsContent value="single" className="mt-4">
                        <StudentFormStudio mode="create" options={options} />
                    </TabsContent>

                    <TabsContent value="batch" className="mt-4">
                        <StudentBatchIntake
                            configuration={options.student_configuration}
                            identifierPolicy={options.identifier_policy}
                            schoolProfile={options.school_profile}
                            sampleProgramCode={firstCourseCode}
                        />
                    </TabsContent>
                </Tabs>
            </div>
        </AdminLayout>
    );
}
