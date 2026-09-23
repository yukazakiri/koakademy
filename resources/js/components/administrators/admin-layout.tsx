import { AdminHeader } from "@/components/administrators/admin-header";
import { AdministratorSidebar } from "@/components/administrators/admin-sidebar";
import { InstitutionOnboarding, InstitutionSchoolLevelOnboarding } from "@/components/administrators/institution-school-level-onboarding";
import { AdminAiFloatingWidget } from "@/components/ai";
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
    [key: string]: unknown;
    announcements?: React.ComponentProps<typeof AnnouncementBanner>["announcements"];
    institutionOnboarding?: InstitutionOnboarding;
    auth?: {
        user?: User | null;
    };
}

interface AdminLayoutProps {
    user?: User;
    title?: string;
    immersive?: boolean;
    children: React.ReactNode;
}

export default function AdminLayout({ user, title, immersive = false, children }: AdminLayoutProps) {
    const { announcements, auth, institutionOnboarding } = usePage<PageProps>().props;
    const pageUrl = usePage().url;
    const isAiChatPage = pageUrl.startsWith("/administrators/ai");
    const resolvedUser = auth?.user ?? user;

    if (!resolvedUser) {
        return null;
    }

    if (immersive) {
        return (
            <ThemeProvider defaultTheme="system" storageKey="app-theme">
                <AnalyticsScripts />
                <div className="flex h-svh flex-col">
                    <ImpersonationBanner />
                    <AnnouncementBanner announcements={announcements ?? []} />
                    <div className="min-h-0 flex-1">{children}</div>
                </div>
                <GlobalCommandPalette user={resolvedUser} />
                <InstitutionSchoolLevelOnboarding onboarding={institutionOnboarding ?? null} />
            </ThemeProvider>
        );
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
                                <AnnouncementBanner announcements={announcements ?? []} />
                                {children}
                            </div>
                        </div>
                    </div>
                </SidebarInset>
                <GlobalCommandPalette user={resolvedUser} />
                <InstitutionSchoolLevelOnboarding onboarding={institutionOnboarding ?? null} />
                {!isAiChatPage && <AdminAiFloatingWidget user={resolvedUser} />}
            </SidebarProvider>
        </ThemeProvider>
    );
}
