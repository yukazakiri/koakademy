import { AdminHeader } from "@/components/administrators/admin-header";
import { AdministratorSidebar } from "@/components/administrators/admin-sidebar";
import { AdminPageNavigationSkeleton, AdminSkeletonFixtureCatalog } from "@/components/administrators/admin-skeleton";
import { InstitutionOnboarding, InstitutionSchoolLevelOnboarding } from "@/components/administrators/institution-school-level-onboarding";
import { AnalyticsScripts } from "@/components/analytics-scripts";
import { AnnouncementBanner } from "@/components/announcement-banner";
import { GlobalCommandPalette } from "@/components/global-command-palette";
import ImpersonationBanner from "@/components/impersonation-banner";
import { SidebarInset, SidebarProvider } from "@/components/ui/sidebar";
import { resolveAdminPageDefinition } from "@/config/admin-page-definitions";
import { ThemeProvider } from "@/hooks/use-theme";
import { User } from "@/types/user";
import { router, usePage } from "@inertiajs/react";
import React, { useEffect, useState } from "react";

interface PageProps {
    [key: string]: unknown;
    announcements?: any[];
    institutionOnboarding?: InstitutionOnboarding;
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
    const { announcements, auth, institutionOnboarding } = usePage<PageProps>().props;
    const resolvedUser = auth?.user ?? user;
    const [navigationDefinition, setNavigationDefinition] = useState<ReturnType<typeof resolveAdminPageDefinition>>(null);

    useEffect(() => {
        const removeStartListener = router.on("start", ({ detail }) => {
            if (detail.visit.method !== "get") {
                return;
            }

            const definition = resolveAdminPageDefinition(String(detail.visit.url));

            if (definition) {
                setNavigationDefinition(definition);
            }
        });
        const clearNavigationSkeleton = () => setNavigationDefinition(null);
        const removeFinishListener = router.on("finish", clearNavigationSkeleton);
        const removeCancelListener = router.on("cancel", clearNavigationSkeleton);

        return () => {
            removeStartListener();
            removeFinishListener();
            removeCancelListener();
        };
    }, []);

    if (typeof window !== "undefined" && (window as Window & { __BONEYARD_BUILD?: boolean }).__BONEYARD_BUILD) {
        return <AdminSkeletonFixtureCatalog />;
    }

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
                                <AnnouncementBanner announcements={announcements ?? []} />
                                {navigationDefinition ? <AdminPageNavigationSkeleton definition={navigationDefinition} /> : children}
                            </div>
                        </div>
                    </div>
                </SidebarInset>
                <GlobalCommandPalette user={resolvedUser} />
                <InstitutionSchoolLevelOnboarding onboarding={institutionOnboarding ?? null} />
            </SidebarProvider>
        </ThemeProvider>
    );
}
