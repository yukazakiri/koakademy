import { ActiveJobsNotification } from "@/components/active-jobs-notification";
import { AdminMobileBottomNav } from "@/components/administrators/admin-mobile-bottom-nav";
import { AnalyticsScripts } from "@/components/analytics-scripts";
import { AnnouncementBanner } from "@/components/announcement-banner";
import { DemoModeBanner } from "@/components/demo-mode-banner";
import { FacultyBottomNav } from "@/components/faculty/faculty-bottom-nav";
import { SeoHead } from "@/components/seo-head";
import { StudentBottomNav } from "@/components/student/student-bottom-nav";
import { Toaster } from "@/components/ui/sonner";
import { OnlinePresenceProvider } from "@/contexts/online-presence-context";
import { isAdministratorPortalRole, isFacultyPortalRole, isStudentPortalRole } from "@/lib/portal-role";
import { User } from "@/types/user";
import { usePage } from "@inertiajs/react";

interface AppRootPageProps {
    auth?: {
        user?: User | null;
    };
    user?: User | null;
    announcements?: unknown[];
    hideMobileNavigation?: boolean;
}

export default function AppRootLayout({ children }: { children: React.ReactNode }) {
    const { props, component } = usePage<AppRootPageProps>();
    const authProps = props.auth;
    const pageUser = props.user;
    const user = authProps?.user ?? pageUser;
    const announcements = props.announcements ?? [];
    const pathname = typeof window !== "undefined" ? window.location.pathname : "";
    const isAuthComponent = ["login", "signup", "forgot-password", "reset-password", "auth/two-factor-challenge"].includes(component);
    const isAuthPath = ["/login", "/signup", "/forgot-password", "/reset-password", "/two-factor-challenge"].includes(pathname);
    const isAuthPage = isAuthComponent || isAuthPath;
    const isStandaloneFormPage = ["Forms/PublicShow", "Forms/Thanks"].includes(component);
    const hideMobileNavigation = props.hideMobileNavigation === true || isStandaloneFormPage;
    const isPortalUser = user ? isFacultyPortalRole(user.role) || isStudentPortalRole(user.role) || isAdministratorPortalRole(user.role) : false;

    return (
        <OnlinePresenceProvider>
            <SeoHead />
            <AnalyticsScripts />
            <DemoModeBanner />
            {!isAuthPage && !isPortalUser ? <AnnouncementBanner announcements={announcements} /> : null}
            {children}
            {!hideMobileNavigation && user && isFacultyPortalRole(user.role) ? <FacultyBottomNav /> : null}
            {!hideMobileNavigation && user && isStudentPortalRole(user.role) ? <StudentBottomNav /> : null}
            {!hideMobileNavigation && user && isAdministratorPortalRole(user.role) ? <AdminMobileBottomNav /> : null}
            {user ? <ActiveJobsNotification /> : null}
            <Toaster position="top-right" richColors />
        </OnlinePresenceProvider>
    );
}
