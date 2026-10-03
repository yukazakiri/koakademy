import AdminLayout from "@/components/administrators/admin-layout";
import type { User } from "@/types/user";
import { Head, usePage } from "@inertiajs/react";
import EnrollmentAnalyticsView from "./dashboards/enrollment-analytics-view";
import ExecutiveOverviewView from "./dashboards/executive-overview-view";
import OperationsCommandView from "./dashboards/operations-command-view";
import StudentDemographicsView from "./dashboards/student-demographics-view";
import type { AdminData } from "./dashboards/types";

export interface AdminDashboardProps {
    user: User;
    admin_data: AdminData;
    active_view?: "overview" | "enrollment" | "students" | "operations";
}

interface Branding {
    currency?: string;
}

const DASHBOARD_TITLES = {
    overview: "Executive Overview",
    enrollment: "Enrollment Analytics",
    students: "Student Demographics",
    operations: "Operations Command",
} as const;

export default function AdministratorDashboard({ user, admin_data, active_view = "overview" }: AdminDashboardProps) {
    const { props } = usePage<{ branding?: Branding }>();
    const currency = props.branding?.currency || "PHP";
    const title = DASHBOARD_TITLES[active_view] ?? "Dashboard";

    return (
        <AdminLayout user={user} title={title}>
            <Head title={`Administrators - ${title}`} />

            <div className="w-full space-y-6">
                {active_view === "overview" && <ExecutiveOverviewView user={user} admin_data={admin_data} currency={currency} />}

                {active_view === "enrollment" && <EnrollmentAnalyticsView user={user} admin_data={admin_data} currency={currency} />}

                {active_view === "students" && <StudentDemographicsView user={user} admin_data={admin_data} currency={currency} />}

                {active_view === "operations" && <OperationsCommandView user={user} admin_data={admin_data} currency={currency} />}
            </div>
        </AdminLayout>
    );
}
