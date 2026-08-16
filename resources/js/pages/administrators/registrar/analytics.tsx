import AdminLayout from "@/components/administrators/admin-layout";
import { SemesterSelector, type SemesterSelectorProps } from "@/components/semester-selector";
import { EnrollmentAnalyticsSection } from "@/pages/administrators/enrollments/analytics-section";
import type { EnrollmentManagementProps } from "@/pages/administrators/enrollments/types";
import { Head } from "@inertiajs/react";
import { RefreshCw } from "lucide-react";

type RegistrarAnalyticsProps = {
    user: EnrollmentManagementProps["user"];
    analytics: EnrollmentManagementProps["analytics"];
    applicantsCount: number;
    quality: {
        missing_department_count: number;
        missing_course_count: number;
    };
    filters: SemesterSelectorProps;
    generatedAt: string;
};

export default function RegistrarAnalytics({ user, analytics, applicantsCount, quality, filters, generatedAt }: RegistrarAnalyticsProps) {
    const generatedLabel = new Intl.DateTimeFormat(undefined, {
        dateStyle: "medium",
        timeStyle: "short",
    }).format(new Date(generatedAt));

    return (
        <AdminLayout user={user} title="Registrar Analytics">
            <Head title="Administrators • Registrar Analytics" />

            <div className="space-y-6 pb-10">
                <header className="flex flex-col gap-4 border-b pb-6 sm:flex-row sm:items-end sm:justify-between">
                    <div className="space-y-1">
                        <p className="text-muted-foreground text-[11px] font-semibold tracking-[0.16em] uppercase">Insights</p>
                        <h1 className="text-2xl font-semibold tracking-tight">Registrar Analytics</h1>
                        <p className="text-muted-foreground max-w-2xl text-sm leading-relaxed">
                            Enrollment composition, workflow movement, and record-quality signals for administrative planning.
                        </p>
                    </div>
                    <div className="flex flex-col items-start gap-2 sm:items-end">
                        <SemesterSelector {...filters} />
                        <p className="text-muted-foreground flex items-center gap-1.5 text-[11px]">
                            <RefreshCw className="size-3" aria-hidden="true" />
                            Calculated {generatedLabel}
                        </p>
                    </div>
                </header>

                <EnrollmentAnalyticsSection analytics={analytics} applicantsCount={applicantsCount} quality={quality} />
            </div>
        </AdminLayout>
    );
}
