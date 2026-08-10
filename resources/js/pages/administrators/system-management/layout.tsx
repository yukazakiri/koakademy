import AdminLayout from "@/components/administrators/admin-layout";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Head, Link } from "@inertiajs/react";
import { ChevronRight, Lock } from "lucide-react";
import type { ReactNode } from "react";

import type { User } from "@/types/user";
import { getSystemSettingsItem, getSystemSettingsStatus, SystemSettingsNavigation } from "./settings-catalog";
import type { SystemManagementAccess, SystemManagementSectionKey } from "./types";

interface SystemManagementLayoutProps {
    user: User;
    access: SystemManagementAccess;
    activeSection: SystemManagementSectionKey;
    heading: string;
    description: string;
    children: ReactNode;
}

export default function SystemManagementLayout({ user, access, activeSection, heading, description, children }: SystemManagementLayoutProps) {
    const item = getSystemSettingsItem(activeSection);
    const category =
        item.group === "academic_operations"
            ? "Academic Operations"
            : item.group === "integrations"
              ? "Access & Integrations"
              : item.group[0].toUpperCase() + item.group.slice(1);
    const canUpdateActiveSection = access.sections[activeSection]?.can_update ?? false;
    const status = getSystemSettingsStatus(item, access);
    const StatusIcon = status.icon;

    return (
        <AdminLayout user={user} title="System Settings">
            <Head title={`System Settings • ${heading}`} />

            <div className="system-settings mx-auto w-full max-w-[90rem] space-y-6">
                <header className="border-border/70 bg-card/85 [@media(prefers-contrast:more)]:border-foreground/60 [@media(prefers-reduced-transparency:reduce)]:bg-card rounded-2xl border px-5 py-5 shadow-sm backdrop-blur-xl sm:px-6">
                    <div className="flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between">
                        <div className="min-w-0">
                            <div className="text-muted-foreground mb-3 flex flex-wrap items-center gap-1.5 text-xs font-medium">
                                <Link
                                    href="/administrators/system-management"
                                    className="hover:text-foreground focus-visible:ring-ring rounded-sm transition-colors focus-visible:ring-2 focus-visible:outline-none"
                                >
                                    System Settings
                                </Link>
                                <ChevronRight className="size-3.5" aria-hidden="true" />
                                <span>{category}</span>
                            </div>
                            <div className="flex items-start gap-3">
                                <span className="bg-primary/10 text-primary mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-xl">
                                    <item.icon className="size-[1.125rem]" aria-hidden="true" />
                                </span>
                                <div>
                                    <p className="text-muted-foreground text-xs font-semibold tracking-[0.08em] uppercase">{category}</p>
                                    <h1 className="text-foreground mt-1 text-2xl font-semibold tracking-[-0.02em] sm:text-[1.75rem]">{heading}</h1>
                                    <p className="text-muted-foreground mt-1.5 max-w-3xl text-sm leading-6">{description}</p>
                                </div>
                            </div>
                        </div>
                        <div className="flex shrink-0 items-center gap-2 self-start">
                            <Badge variant="outline" className="border-border/70 bg-background/70 text-muted-foreground gap-1.5">
                                <StatusIcon className="size-3" aria-hidden="true" />
                                {status.label}
                            </Badge>
                            <SystemSettingsNavigation access={access} activeSection={activeSection} mobile />
                        </div>
                    </div>
                </header>

                <div className="grid gap-6 lg:grid-cols-[15rem_minmax(0,1fr)] lg:items-start">
                    <SystemSettingsNavigation access={access} activeSection={activeSection} />
                    <main className="min-w-0 space-y-6">
                        {!canUpdateActiveSection ? (
                            <Alert>
                                <Lock className="size-4" />
                                <AlertTitle>Read-only access</AlertTitle>
                                <AlertDescription>You can review this configuration, but your role cannot change it.</AlertDescription>
                            </Alert>
                        ) : null}

                        <fieldset disabled={!canUpdateActiveSection} className="min-w-0 space-y-6 border-0 p-0 disabled:cursor-not-allowed">
                            {children}
                        </fieldset>
                    </main>
                </div>
            </div>
        </AdminLayout>
    );
}
