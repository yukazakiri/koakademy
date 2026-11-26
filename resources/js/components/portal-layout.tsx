import { AdministratorSidebar } from "@/components/administrators/admin-sidebar";
import { FacultySidebar } from "@/components/faculty/faculty-sidebar";
import { GlobalCommandPalette } from "@/components/global-command-palette";
import { NewsletterSubscribeDialog } from "@/components/newsletter-subscribe-dialog";
import { SiteHeader } from "@/components/site-header";
import { StudentSidebar } from "@/components/student/student-sidebar";
import { SidebarInset, SidebarProvider } from "@/components/ui/sidebar";
import { isAdministratorPortalRole, isFacultyPortalRole, isStudentPortalRole } from "@/lib/portal-role";
import { User } from "@/types/user";
import { usePage } from "@inertiajs/react";
import { AnnouncementBanner } from "./announcement-banner";
import { FeatureNoticeBanner } from "./feature-notice-banner";
import ImpersonationBanner from "./impersonation-banner";
import { PortalSidebar } from "./portal-sidebar";

interface PortalLayoutProps {
    user: User;
    children: React.ReactNode;
}

export default function PortalLayout({ user, children }: PortalLayoutProps) {
    const isAdministrator = isAdministratorPortalRole(user.role);
    const isStudent = isStudentPortalRole(user.role);
    const isFaculty = isFacultyPortalRole(user.role);
    const { announcements } = usePage().props as { announcements?: any[] };

    return (
        <SidebarProvider>
            {isAdministrator ? (
                <AdministratorSidebar user={user} />
            ) : isStudent ? (
                <StudentSidebar user={user} />
            ) : isFaculty ? (
                <FacultySidebar user={user} />
            ) : (
                <PortalSidebar user={user} />
            )}
            <SidebarInset>
                <SiteHeader user={user} />
                <ImpersonationBanner />
                <FeatureNoticeBanner />
                <AnnouncementBanner announcements={announcements ?? []} />
                <div className="flex flex-1 flex-col">
                    <div className="@container/main flex flex-1 flex-col gap-2">
                        <div className="flex flex-col gap-4 px-4 pt-4 pb-24 md:gap-6 md:py-6 lg:px-6">{children}</div>
                    </div>
                </div>
            </SidebarInset>
            <GlobalCommandPalette user={user} />
            <NewsletterSubscribeDialog />
        </SidebarProvider>
    );
}
