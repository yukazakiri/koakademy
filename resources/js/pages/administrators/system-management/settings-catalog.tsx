import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Sheet, SheetClose, SheetContent, SheetDescription, SheetHeader, SheetTitle, SheetTrigger } from "@/components/ui/sheet";
import { cn } from "@/lib/utils";
import { Link } from "@inertiajs/react";
import type { LucideIcon } from "lucide-react";
import {
    Activity,
    BarChart3,
    Bell,
    Building2,
    Calculator,
    CalendarClock,
    Check,
    FileBadge2,
    Fingerprint,
    Globe,
    Hash,
    List,
    Lock,
    Mail,
    Palette,
    Settings2,
    Share2,
    Webhook,
} from "lucide-react";

import type { SystemManagementAccess, SystemManagementSectionKey } from "./types";

export type SystemSettingsGroupKey = "institution" | "academic_operations" | "experience" | "communications" | "integrations" | "system";

type SettingsMode = "editable" | "deployment" | "monitor";

export interface SystemSettingsGroup {
    key: SystemSettingsGroupKey;
    label: string;
    description: string;
}

export interface SystemSettingsItem {
    key: SystemManagementSectionKey;
    group: SystemSettingsGroupKey;
    label: string;
    description: string;
    href: string;
    keywords: string[];
    icon: LucideIcon;
    mode?: SettingsMode;
}

export const systemSettingsGroups: SystemSettingsGroup[] = [
    {
        key: "institution",
        label: "Institution",
        description: "Campus identity, academic calendar, and admissions policy.",
    },
    {
        key: "academic_operations",
        label: "Academic Operations",
        description: "Rules and official records used throughout the academic lifecycle.",
    },
    {
        key: "experience",
        label: "Experience",
        description: "How the institution is presented across the portal and public web.",
    },
    {
        key: "communications",
        label: "Communications",
        description: "Delivery channels, consent-based marketing, and provider readiness.",
    },
    {
        key: "integrations",
        label: "Access & Integrations",
        description: "External sign-in and API access for connected services.",
    },
    {
        key: "system",
        label: "System",
        description: "Operational visibility for the running platform.",
    },
];

export const systemSettingsItems: SystemSettingsItem[] = [
    {
        key: "school",
        group: "institution",
        label: "Institution & Campus",
        description: "Set the active school, campus identity, contacts, and academic calendar.",
        href: "/administrators/system-management/school",
        keywords: ["school", "campus", "academic calendar", "semester", "contact"],
        icon: Building2,
    },
    {
        key: "pipeline",
        group: "institution",
        label: "Admissions & Enrollment",
        description: "Design, test, and publish the student enrollment journey.",
        href: "/administrators/system-management/enrollment-pipeline",
        keywords: ["admissions", "enrollment", "blueprint", "workflow", "policy"],
        icon: List,
    },
    {
        key: "grading",
        group: "academic_operations",
        label: "Grade Calculation",
        description: "Define grading scales, GWA rules, and course exclusions.",
        href: "/administrators/system-management/grading",
        keywords: ["grading", "gwa", "passing mark", "exemptions", "transcript"],
        icon: Calculator,
    },
    {
        key: "identifiers",
        group: "academic_operations",
        label: "Student & Staff IDs",
        description: "Manage the numeric sequences used for student and staff records.",
        href: "/administrators/system-management/identifiers",
        keywords: ["identifier", "id sequence", "student id", "staff id", "numbering"],
        icon: Hash,
    },
    {
        key: "faculty_fields",
        group: "academic_operations",
        label: "Faculty Fields",
        description: "Define the sensitive staff fields and import headers used by your institution.",
        href: "/administrators/system-management/faculty-fields",
        keywords: ["faculty", "employee", "staff", "government id", "tax", "import", "custom fields"],
        icon: Fingerprint,
    },
    {
        key: "finance_documents",
        group: "academic_operations",
        label: "Finance Documents",
        description: "Control the issuance and delivery of official receipts and invoices.",
        href: "/administrators/system-management/finance-documents",
        keywords: ["finance", "receipt", "invoice", "billing", "document"],
        icon: FileBadge2,
    },
    {
        key: "tuition_payment_schedule",
        group: "academic_operations",
        label: "Tuition Payment Schedule",
        description: "Configure installment percentages, rounding, and remainder rules by student type.",
        href: "/administrators/system-management/tuition-payment-schedule",
        keywords: ["tuition", "installments", "prelim", "midterm", "finals", "rounding"],
        icon: CalendarClock,
    },
    {
        key: "brand",
        group: "experience",
        label: "Brand & Sign-in",
        description: "Shape your portal identity, visual language, and sign-in appearance.",
        href: "/administrators/system-management/brand",
        keywords: ["brand", "logo", "appearance", "authentication", "sign in"],
        icon: Palette,
    },
    {
        key: "seo",
        group: "experience",
        label: "Website & Sharing",
        description: "Control search visibility, browser metadata, and social previews.",
        href: "/administrators/system-management/seo",
        keywords: ["seo", "metadata", "sharing", "social preview", "robots"],
        icon: Globe,
    },
    {
        key: "analytics",
        group: "experience",
        label: "Analytics & Tracking",
        description: "Configure privacy-aware telemetry providers and tracking scripts.",
        href: "/administrators/system-management/analytics",
        keywords: ["analytics", "tracking", "telemetry", "google", "umami"],
        icon: BarChart3,
    },
    {
        key: "mail",
        group: "communications",
        label: "Email Delivery",
        description: "Review the deployment-managed transport used for transactional mail.",
        href: "/administrators/system-management/mail",
        keywords: ["email", "mail", "smtp", "delivery", "transport"],
        icon: Mail,
        mode: "deployment",
    },
    {
        key: "notifications",
        group: "communications",
        label: "Notifications",
        description: "Choose how the platform sends email, in-app, realtime, and SMS updates.",
        href: "/administrators/system-management/notifications",
        keywords: ["notifications", "pusher", "sms", "broadcast", "channels"],
        icon: Bell,
    },
    {
        key: "newsletter",
        group: "communications",
        label: "Newsletter",
        description: "Configure consent-based marketing contacts and test the provider connection.",
        href: "/administrators/system-management/newsletter",
        keywords: ["newsletter", "marketing", "consent", "mailchimp", "subscribers"],
        icon: Mail,
    },
    {
        key: "socialite",
        group: "integrations",
        label: "Sign-in Providers",
        description: "Connect the OAuth providers available on your sign-in screen.",
        href: "/administrators/system-management/socialite",
        keywords: ["oauth", "social login", "google", "facebook", "sign in"],
        icon: Share2,
    },
    {
        key: "api",
        group: "integrations",
        label: "API & Integrations",
        description: "Set public API exposure, portal values, and response contracts.",
        href: "/administrators/system-management/api",
        keywords: ["api", "integrations", "public settings", "webhook", "developer"],
        icon: Webhook,
    },
    {
        key: "pulse",
        group: "system",
        label: "System Health",
        description: "Monitor the live operational health and performance of the platform.",
        href: "/administrators/system-management/pulse",
        keywords: ["health", "pulse", "metrics", "queue", "performance"],
        icon: Activity,
        mode: "monitor",
    },
];

export function getSystemSettingsItem(section: SystemManagementSectionKey): SystemSettingsItem {
    const item = systemSettingsItems.find((candidate) => candidate.key === section);

    if (!item) {
        throw new Error(`No System Settings catalog entry exists for ${section}.`);
    }

    return item;
}

export function getVisibleSystemSettingsGroups(access: SystemManagementAccess): Array<SystemSettingsGroup & { items: SystemSettingsItem[] }> {
    return systemSettingsGroups
        .map((group) => ({
            ...group,
            items: systemSettingsItems.filter((item) => item.group === group.key && access.sections[item.key]?.can_view),
        }))
        .filter((group) => group.items.length > 0);
}

export function getSystemSettingsStatus(item: SystemSettingsItem, access: SystemManagementAccess): { label: string; icon: LucideIcon } {
    if (item.mode === "deployment") {
        return { label: "Deployment-managed", icon: Settings2 };
    }

    if (item.mode === "monitor") {
        return { label: "Monitor only", icon: Activity };
    }

    if (!access.sections[item.key]?.can_update) {
        return { label: "Read-only", icon: Lock };
    }

    return { label: "Editable", icon: Check };
}

interface SystemSettingsNavigationProps {
    access: SystemManagementAccess;
    activeSection: SystemManagementSectionKey;
    mobile?: boolean;
}

function SettingsNavigationList({ access, activeSection, closeOnNavigate = false }: SystemSettingsNavigationProps & { closeOnNavigate?: boolean }) {
    return (
        <nav aria-label="System Settings sections" className="space-y-5">
            {getVisibleSystemSettingsGroups(access).map((group) => (
                <section key={group.key} aria-labelledby={`settings-group-${group.key}`}>
                    <h2
                        id={`settings-group-${group.key}`}
                        className="text-muted-foreground px-2 text-[0.7rem] font-semibold tracking-[0.08em] uppercase"
                    >
                        {group.label}
                    </h2>
                    <div className="mt-1 space-y-0.5">
                        {group.items.map((item) => {
                            const Icon = item.icon;
                            const active = item.key === activeSection;

                            const link = (
                                <Link
                                    href={item.href}
                                    aria-current={active ? "page" : undefined}
                                    className={cn(
                                        "group/settings-item flex min-h-10 items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm font-medium transition-[background-color,color,transform] duration-150 motion-reduce:transform-none",
                                        active
                                            ? "bg-primary text-primary-foreground shadow-sm"
                                            : "text-muted-foreground hover:bg-muted hover:text-foreground active:scale-[0.985]",
                                    )}
                                >
                                    <Icon className="size-4 shrink-0" aria-hidden="true" />
                                    <span className="min-w-0 truncate">{item.label}</span>
                                </Link>
                            );

                            return closeOnNavigate ? (
                                <SheetClose asChild key={item.key}>
                                    {link}
                                </SheetClose>
                            ) : (
                                <div key={item.key}>{link}</div>
                            );
                        })}
                    </div>
                </section>
            ))}
        </nav>
    );
}

export function SystemSettingsNavigation({ access, activeSection, mobile = false }: SystemSettingsNavigationProps) {
    if (mobile) {
        return (
            <Sheet>
                <SheetTrigger asChild>
                    <Button variant="outline" size="sm" className="gap-2 lg:hidden">
                        <Settings2 className="size-4" />
                        Browse settings
                    </Button>
                </SheetTrigger>
                <SheetContent side="left" className="w-[min(22rem,88vw)] p-0 motion-reduce:transition-none">
                    <SheetHeader className="border-b pr-12">
                        <SheetTitle>System Settings</SheetTitle>
                        <SheetDescription>Browse the settings available to your role.</SheetDescription>
                    </SheetHeader>
                    <div className="min-h-0 flex-1 overflow-y-auto p-4">
                        <SettingsNavigationList access={access} activeSection={activeSection} closeOnNavigate />
                    </div>
                </SheetContent>
            </Sheet>
        );
    }

    return (
        <aside className="hidden lg:block">
            <div className="border-border/70 bg-card/90 [@media(prefers-contrast:more)]:border-foreground/60 [@media(prefers-reduced-transparency:reduce)]:bg-card sticky top-6 rounded-2xl border p-3 shadow-sm backdrop-blur-xl">
                <div className="mb-4 flex items-center gap-2 px-2 pt-1">
                    <span className="bg-primary/10 text-primary flex size-7 items-center justify-center rounded-lg">
                        <Settings2 className="size-4" aria-hidden="true" />
                    </span>
                    <span className="text-sm font-semibold">System Settings</span>
                </div>
                <SettingsNavigationList access={access} activeSection={activeSection} />
            </div>
        </aside>
    );
}

export function SystemSettingsStateBadge({ item, access }: { item: SystemSettingsItem; access: SystemManagementAccess }) {
    const status = getSystemSettingsStatus(item, access);
    const Icon = status.icon;

    return (
        <Badge variant="outline" className="border-border/70 bg-background/70 text-muted-foreground gap-1.5">
            <Icon className="size-3" aria-hidden="true" />
            {status.label}
        </Badge>
    );
}
