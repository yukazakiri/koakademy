import { AdminMobileBottomNav } from "@/components/administrators/admin-mobile-bottom-nav";
import { AnalyticsScripts } from "@/components/analytics-scripts";
import { FacultyBottomNav } from "@/components/faculty/faculty-bottom-nav";
import { SeoHead } from "@/components/seo-head";
import { StudentBottomNav } from "@/components/student/student-bottom-nav";
import { Toaster } from "@/components/ui/sonner";
import { isAdministratorPortalRole, isFacultyPortalRole, isStudentPortalRole } from "@/lib/portal-role";
import { User } from "@/types/user";
import { usePage } from "@inertiajs/react";

export default function AppRootLayout({ children }: { children: React.ReactNode }) {
    const { props } = usePage();
    const user = (props.auth as any)?.user || ((props as any).user as User | undefined);

    return (
        <>
            <SeoHead />
            <AnalyticsScripts />
            {children}
            {user && isFacultyPortalRole(user.role) ? <FacultyBottomNav /> : null}
            {user && isStudentPortalRole(user.role) ? <StudentBottomNav /> : null}
            {user && isAdministratorPortalRole(user.role) ? <AdminMobileBottomNav /> : null}
            <Toaster position="top-right" richColors />
        </>
    );
}
