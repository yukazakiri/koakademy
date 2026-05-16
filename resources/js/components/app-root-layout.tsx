import { AdminMobileBottomNav } from "@/components/administrators/admin-mobile-bottom-nav";
import { AnnouncementBanner } from "@/components/announcement-banner";
import { AnalyticsScripts } from "@/components/analytics-scripts";
import { DemoModeBanner } from "@/components/demo-mode-banner";
import { FacultyBottomNav } from "@/components/faculty/faculty-bottom-nav";
import { SeoHead } from "@/components/seo-head";
import { StudentBottomNav } from "@/components/student/student-bottom-nav";
import { Toaster } from "@/components/ui/sonner";
import { isAdministratorPortalRole, isFacultyPortalRole, isStudentPortalRole } from "@/lib/portal-role";
import { User } from "@/types/user";
import { usePage } from "@inertiajs/react";

export default function AppRootLayout({ children }: { children: React.ReactNode }) {
    const { props, component } = usePage();
    const authProps = props.auth as { user?: User } | undefined;
    const pageUser = (props as { user?: User }).user;
    const user = authProps?.user ?? pageUser;
    const announcements = (props as { announcements?: unknown[] }).announcements ?? [];
    const pathname = typeof window !== "undefined" ? window.location.pathname : "";
    const isAuthComponent = ["login", "signup", "forgot-password", "reset-password", "auth/two-factor-challenge"].includes(component);
    const isAuthPath = ["/login", "/signup", "/forgot-password", "/reset-password", "/two-factor-challenge"].includes(pathname);
    const isAuthPage = isAuthComponent || isAuthPath;

    return (
        <>
            <SeoHead />
            <AnalyticsScripts />
            <DemoModeBanner />
            {!isAuthPage ? <AnnouncementBanner announcements={announcements} /> : null}
            {children}
            {user && isFacultyPortalRole(user.role) ? <FacultyBottomNav /> : null}
            {user && isStudentPortalRole(user.role) ? <StudentBottomNav /> : null}
            {user && isAdministratorPortalRole(user.role) ? <AdminMobileBottomNav /> : null}
            <Toaster position="top-right" richColors />
        </>
    );
}
