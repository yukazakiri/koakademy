import AdminLayout from "@/components/administrators/admin-layout";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import type { User } from "@/types/user";
import { Head } from "@inertiajs/react";
import type { LucideIcon } from "lucide-react";
import { Activity, BarChart3, Bell, Building2, Calculator, Database, Globe, List, Lock, Mail, Palette, Share2, Webhook } from "lucide-react";
import type { ReactNode } from "react";
import type { SystemManagementAccess, SystemManagementSectionKey } from "./types";

interface SystemManagementNavItem {
    key: SystemManagementSectionKey;
    label: string;
    description: string;
    href: string;
    icon: LucideIcon;
}

export const systemManagementNavItems: SystemManagementNavItem[] = [
    {
        key: "school",
        label: "School & Campus",
        description: "Active school instance and campus details.",
        href: "/administrators/system-management/school",
        icon: Building2,
    },
    {
        key: "pipeline",
        label: "Enrollment Pipeline",
        description: "Workflow steps, permissions, and analytics cards.",
        href: "/administrators/system-management/enrollment-pipeline",
        icon: List,
    },
    {
        key: "seo",
        label: "SEO & Metadata",
        description: "Search engine defaults and social metadata.",
        href: "/administrators/system-management/seo",
        icon: Globe,
    },
    {
        key: "analytics",
        label: "Analytics",
        description: "Tracking providers, snippets, and telemetry controls.",
        href: "/administrators/system-management/analytics",
        icon: BarChart3,
    },
    {
        key: "brand",
        label: "Brand & Appearance",
        description: "Application identity, visuals, and contact data.",
        href: "/administrators/system-management/brand",
        icon: Palette,
    },
        {
        key: "socialite",
        label: "Social Auth",
        description: "OAuth credentials for supported providers.",
        href: "/administrators/system-management/socialite",
        icon: Share2,
    },
    {
        key: "mail",
        label: "Mail Server",
        description: "SMTP settings and delivery checks.",
        href: "/administrators/system-management/mail",
        icon: Mail,
    },
    {
        key: "api",
        label: "API Management",
        description: "Public API exposure and developer-friendly endpoint setup.",
        href: "/administrators/system-management/api",
        icon: Webhook,
    },
    {
        key: "notifications",
        label: "Notifications",
        description: "Configure notification channels and providers.",
        href: "/administrators/system-management/notifications",
        icon: Bell,
    },
    {
        key: "grading",
        label: "Grading System",
        description: "Scale, passing marks, and GWA exclusions (OJT, NSTP, etc.).",
        href: "/administrators/system-management/grading",
        icon: Calculator,
    },
    {
        key: "pulse",
        label: "System Pulse",
        description: "Live infrastructure and performance metrics.",
        href: "/administrators/system-management/pulse",
        icon: Activity,
    },
];

interface SystemManagementLayoutProps {
    user: User;
    access: SystemManagementAccess;
    activeSection: SystemManagementSectionKey;
    heading: string;
    description: string;
    children: ReactNode;
}

export default function SystemManagementLayout({ user, access, activeSection, heading, description, children }: SystemManagementLayoutProps) {
    const canUpdateActiveSection = access.sections[activeSection]?.can_update ?? false;

    return (
        <AdminLayout user={user} title="System Settings">
            <Head title={`System Settings • ${heading}`} />

            <div className="space-y-6">
                <div className="space-y-1">
                    <h1 className="text-2xl font-semibold tracking-tight">{heading}</h1>
                    <p className="text-muted-foreground">{description}</p>
                </div>

                {!canUpdateActiveSection ? (
                    <Alert>
                        <Lock className="h-4 w-4" />
                        <AlertTitle>Read-only access</AlertTitle>
                        <AlertDescription>You can view this section, but your role does not include update access for it.</AlertDescription>
                    </Alert>
                ) : null}

                {children}
            </div>
        </AdminLayout>
    );
}
