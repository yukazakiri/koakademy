import { ActiveJobsNotification } from "@/components/active-jobs-notification";
import { AdminHeader } from "@/components/administrators/admin-header";
import { AdministratorSidebar } from "@/components/administrators/admin-sidebar";
import { AnalyticsScripts } from "@/components/analytics-scripts";
import { AnnouncementBanner } from "@/components/announcement-banner";
import { GlobalCommandPalette } from "@/components/global-command-palette";
import ImpersonationBanner from "@/components/impersonation-banner";
import { SidebarInset, SidebarProvider } from "@/components/ui/sidebar";
import { ThemeProvider } from "@/hooks/use-theme";
import { User } from "@/types/user";
import { usePage } from "@inertiajs/react";
import React from "react";

interface PageProps {
    announcements: any[];
    auth?: {
        user?: User | null;
    };
}

interface AdminLayoutProps {
    user?: User;
    title?: string;
    children: React.ReactNode;
}

export default function AdminLayout({ user, title, children }: AdminLayoutProps) {
    const { announcements, auth } = usePage<PageProps>().props;
    const resolvedUser = auth?.user ?? user;

    if (!resolvedUser) {
        return null;
    }

    return (
        <ThemeProvider defaultTheme="system" storageKey="app-theme">
            <AnalyticsScripts />
            <SidebarProvider>
                <AdministratorSidebar user={resolvedUser} />
                <SidebarInset>
                    <ImpersonationBanner />
                    <AdminHeader title={title || "Portal"} user={resolvedUser} />
                    <div className="flex flex-1 flex-col">
                        <div className="@container/main flex flex-1 flex-col gap-2">
                            <div className="flex flex-col gap-4 px-4 py-4 pb-24 md:gap-6 md:py-6 md:pb-6 lg:px-6">
                                <AnnouncementBanner announcements={announcements} />
                                {children}
                            </div>
                        </div>
                    </div>
                </SidebarInset>
                <GlobalCommandPalette user={resolvedUser} />
                <ActiveJobsNotification />
            </SidebarProvider>
        </ThemeProvider>
    );
}
