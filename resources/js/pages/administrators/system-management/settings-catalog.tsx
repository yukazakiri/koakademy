import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Sheet, SheetClose, SheetContent, SheetDescription, SheetHeader, SheetTitle, SheetTrigger } from "@/components/ui/sheet";
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from "@/components/ui/tooltip";
import { cn } from "@/lib/utils";
import { Link } from "@inertiajs/react";
import { motion, useReducedMotion } from "framer-motion";
import type { LucideIcon } from "lucide-react";
import {
    Activity,
    BarChart3,
    Bell,
    Building2,
    Calculator,
    CalendarClock,
    Check,
    ChevronRight,
    FileBadge2,
    Fingerprint,
    Globe,
    Hash,
    Layers,
    List,
    Lock,
    Mail,
    Palette,
    Search,
    Settings2,
    Share2,
    Webhook,
    X,
} from "lucide-react";
import { useMemo, useState } from "react";

import type { SystemManagementAccess, SystemManagementSectionKey } from "./types";

export type SystemSettingsGroupKey = "institution" | "academic_operations" | "experience" | "communications" | "integrations" | "system";

type SettingsMode = "editable" | "deployment" | "monitor";

export interface CategoryTheme {
    accentBg: string;
    accentText: string;
    accentBorder: string;
    badgeClass: string;
    pillClass: string;
    glowClass: string;
}

export const categoryThemes: Record<SystemSettingsGroupKey, CategoryTheme> = {
    institution: {
        accentBg: "bg-sky-500/10 dark:bg-sky-500/15",
        accentText: "text-sky-600 dark:text-sky-400",
        accentBorder: "border-sky-500/20",
        badgeClass: "bg-sky-500/10 text-sky-700 dark:text-sky-300 border-sky-300/40 dark:border-sky-700/40",
        pillClass: "hover:border-sky-500/40",
        glowClass: "from-sky-500/10 to-indigo-500/10",
    },
    academic_operations: {
        accentBg: "bg-emerald-500/10 dark:bg-emerald-500/15",
        accentText: "text-emerald-600 dark:text-emerald-400",
        accentBorder: "border-emerald-500/20",
        badgeClass: "bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 border-emerald-300/40 dark:border-emerald-700/40",
        pillClass: "hover:border-emerald-500/40",
        glowClass: "from-emerald-500/10 to-teal-500/10",
    },
    experience: {
        accentBg: "bg-violet-500/10 dark:bg-violet-500/15",
        accentText: "text-violet-600 dark:text-violet-400",
        accentBorder: "border-violet-500/20",
        badgeClass: "bg-violet-500/10 text-violet-700 dark:text-violet-300 border-violet-300/40 dark:border-violet-700/40",
        pillClass: "hover:border-violet-500/40",
        glowClass: "from-violet-500/10 to-purple-500/10",
    },
    communications: {
        accentBg: "bg-amber-500/10 dark:bg-amber-500/15",
        accentText: "text-amber-600 dark:text-amber-400",
        accentBorder: "border-amber-500/20",
        badgeClass: "bg-amber-500/10 text-amber-700 dark:text-amber-300 border-amber-300/40 dark:border-amber-700/40",
        pillClass: "hover:border-amber-500/40",
        glowClass: "from-amber-500/10 to-orange-500/10",
    },
    integrations: {
        accentBg: "bg-indigo-500/10 dark:bg-indigo-500/15",
        accentText: "text-indigo-600 dark:text-indigo-400",
        accentBorder: "border-indigo-500/20",
        badgeClass: "bg-indigo-500/10 text-indigo-700 dark:text-indigo-300 border-indigo-300/40 dark:border-indigo-700/40",
        pillClass: "hover:border-indigo-500/40",
        glowClass: "from-indigo-500/10 to-blue-500/10",
    },
    system: {
        accentBg: "bg-rose-500/10 dark:bg-rose-500/15",
        accentText: "text-rose-600 dark:text-rose-400",
        accentBorder: "border-rose-500/20",
        badgeClass: "bg-rose-500/10 text-rose-700 dark:text-rose-300 border-rose-300/40 dark:border-rose-700/40",
        pillClass: "hover:border-rose-500/40",
        glowClass: "from-rose-500/10 to-pink-500/10",
    },
};

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

export function getSiblingSettingsItems(section: SystemManagementSectionKey, access: SystemManagementAccess): SystemSettingsItem[] {
    const current = getSystemSettingsItem(section);
    return systemSettingsItems.filter((item) => item.group === current.group && access.sections[item.key]?.can_view);
}

export interface SystemSettingsStatusDetails {
    label: string;
    icon: LucideIcon;
    dotColor: string;
    badgeVariant: "default" | "secondary" | "outline";
    badgeClass: string;
}

export function getSystemSettingsStatus(item: SystemSettingsItem, access: SystemManagementAccess): SystemSettingsStatusDetails {
    if (item.mode === "deployment") {
        return {
            label: "Deployment-managed",
            icon: Settings2,
            dotColor: "bg-sky-500",
            badgeVariant: "outline",
            badgeClass: "border-sky-500/30 bg-sky-500/5 text-sky-700 dark:text-sky-300",
        };
    }

    if (item.mode === "monitor") {
        return {
            label: "Live monitor",
            icon: Activity,
            dotColor: "bg-emerald-500 animate-pulse",
            badgeVariant: "outline",
            badgeClass: "border-emerald-500/30 bg-emerald-500/5 text-emerald-700 dark:text-emerald-300",
        };
    }

    if (!access.sections[item.key]?.can_update) {
        return {
            label: "Read-only",
            icon: Lock,
            dotColor: "bg-zinc-400 dark:bg-zinc-500",
            badgeVariant: "outline",
            badgeClass: "border-border/60 bg-muted/50 text-muted-foreground",
        };
    }

    return {
        label: "Editable",
        icon: Check,
        dotColor: "bg-primary",
        badgeVariant: "outline",
        badgeClass: "border-primary/25 bg-primary/5 text-foreground font-medium",
    };
}

interface SystemSettingsNavigationProps {
    access: SystemManagementAccess;
    activeSection: SystemManagementSectionKey;
    mobile?: boolean;
    collapsed?: boolean;
    onToggleCollapse?: () => void;
}

function SettingsNavigationList({
    access,
    activeSection,
    collapsed = false,
    filterQuery = "",
    closeOnNavigate = false,
}: SystemSettingsNavigationProps & { filterQuery?: string; closeOnNavigate?: boolean }) {
    const reducedMotion = useReducedMotion();
    const query = filterQuery.trim().toLowerCase();

    const visibleGroups = useMemo(() => {
        return getVisibleSystemSettingsGroups(access)
            .map((group) => ({
                ...group,
                items: group.items.filter((item) => {
                    if (!query) return true;
                    return (
                        item.label.toLowerCase().includes(query) ||
                        item.description.toLowerCase().includes(query) ||
                        item.keywords.some((kw) => kw.toLowerCase().includes(query))
                    );
                }),
            }))
            .filter((group) => group.items.length > 0);
    }, [access, query]);

    if (visibleGroups.length === 0) {
        return (
            <div className="px-3 py-8 text-center text-xs text-muted-foreground">
                <Search className="mx-auto mb-2 size-4 opacity-50" />
                No matching settings
            </div>
        );
    }

    return (
        <TooltipProvider delay={150}>
            <nav aria-label="System Settings sections" className="space-y-4">
                {visibleGroups.map((group) => {
                    const theme = categoryThemes[group.key];

                    return (
                        <section key={group.key} aria-labelledby={`settings-nav-group-${group.key}`} className="space-y-1">
                            {!collapsed && (
                                <div className="flex items-center justify-between px-2.5 py-1">
                                    <h2
                                        id={`settings-nav-group-${group.key}`}
                                        className="text-[10.5px] font-semibold tracking-wider text-muted-foreground uppercase"
                                    >
                                        {group.label}
                                    </h2>
                                    <span className="text-[10px] font-mono text-muted-foreground/60">{group.items.length}</span>
                                </div>
                            )}

                            <div className="space-y-0.5">
                                {group.items.map((item) => {
                                    const Icon = item.icon;
                                    const active = item.key === activeSection;
                                    const status = getSystemSettingsStatus(item, access);

                                    const linkContent = (
                                        <Link
                                            href={item.href}
                                            prefetch
                                            cacheFor="30s"
                                            aria-current={active ? "page" : undefined}
                                            className={cn(
                                                "group relative flex items-center gap-2.5 rounded-lg text-sm font-medium transition-colors outline-none",
                                                collapsed ? "h-9 w-9 justify-center p-0 mx-auto" : "h-9 px-2.5 py-1.5",
                                                active
                                                    ? "bg-accent/80 text-foreground font-semibold shadow-xs"
                                                    : "text-muted-foreground hover:bg-muted/60 hover:text-foreground active:scale-[0.985]",
                                            )}
                                        >
                                            {active && (
                                                <motion.span
                                                    layoutId={reducedMotion ? undefined : "active-settings-nav-pill"}
                                                    className="absolute inset-0 rounded-lg bg-accent border border-border/80"
                                                    transition={{ type: "spring", bounce: 0.15, duration: 0.3 }}
                                                    aria-hidden="true"
                                                />
                                            )}

                                            <span
                                                className={cn(
                                                    "relative z-10 flex size-6 shrink-0 items-center justify-center rounded-md transition-colors",
                                                    active
                                                        ? cn(theme.accentBg, theme.accentText)
                                                        : "bg-muted/50 text-muted-foreground group-hover:bg-muted group-hover:text-foreground",
                                                )}
                                            >
                                                <Icon className="size-3.5" aria-hidden="true" />
                                            </span>

                                            {!collapsed && (
                                                <>
                                                    <span className="relative z-10 min-w-0 flex-1 truncate text-xs sm:text-[13px]">
                                                        {item.label}
                                                    </span>
                                                    <span
                                                        className={cn("relative z-10 size-1.5 shrink-0 rounded-full", status.dotColor)}
                                                        title={status.label}
                                                        aria-label={status.label}
                                                    />
                                                </>
                                            )}
                                        </Link>
                                    );

                                    if (collapsed) {
                                        return (
                                            <Tooltip key={item.key}>
                                                <TooltipTrigger render={linkContent} />
                                                <TooltipContent side="right" className="text-xs">
                                                    <p className="font-semibold">{item.label}</p>
                                                    <p className="text-muted-foreground text-[11px]">{status.label}</p>
                                                </TooltipContent>
                                            </Tooltip>
                                        );
                                    }

                                    return closeOnNavigate ? (
                                        <SheetClose asChild key={item.key}>
                                            {linkContent}
                                        </SheetClose>
                                    ) : (
                                        <div key={item.key}>{linkContent}</div>
                                    );
                                })}
                            </div>
                        </section>
                    );
                })}
            </nav>
        </TooltipProvider>
    );
}

export function SystemSettingsNavigation({
    access,
    activeSection,
    mobile = false,
    collapsed = false,
}: SystemSettingsNavigationProps) {
    const [filterQuery, setFilterQuery] = useState("");

    if (mobile) {
        return (
            <Sheet>
                <SheetTrigger asChild>
                    <Button variant="outline" size="sm" className="h-8 gap-2 text-xs font-medium lg:hidden">
                        <Settings2 className="size-3.5" />
                        Settings Menu
                    </Button>
                </SheetTrigger>
                <SheetContent side="left" className="flex flex-col w-[min(20rem,88vw)] p-0">
                    <SheetHeader className="border-b px-4 py-3.5">
                        <div className="flex items-center gap-2">
                            <span className="flex size-7 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                <Settings2 className="size-4" />
                            </span>
                            <div>
                                <SheetTitle className="text-sm font-semibold">System Settings</SheetTitle>
                                <SheetDescription className="text-xs">Institutional preferences</SheetDescription>
                            </div>
                        </div>
                    </SheetHeader>

                    <div className="px-3 pt-3">
                        <div className="relative">
                            <Search className="pointer-events-none absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                value={filterQuery}
                                onChange={(e) => setFilterQuery(e.target.value)}
                                placeholder="Quick filter..."
                                className="h-8 pl-8 pr-7 text-xs bg-muted/40"
                            />
                            {filterQuery && (
                                <button
                                    onClick={() => setFilterQuery("")}
                                    className="absolute top-1/2 right-2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                                    aria-label="Clear filter"
                                >
                                    <X className="size-3" />
                                </button>
                            )}
                        </div>
                    </div>

                    <div className="min-h-0 flex-1 overflow-y-auto px-3 py-3">
                        <SettingsNavigationList
                            access={access}
                            activeSection={activeSection}
                            filterQuery={filterQuery}
                            closeOnNavigate
                        />
                    </div>
                </SheetContent>
            </Sheet>
        );
    }

    return (
        <aside
            className={cn(
                "hidden lg:block shrink-0 transition-[width] duration-200 ease-out",
                collapsed ? "w-12" : "w-60 xl:w-64",
            )}
        >
            <div className="sticky top-6 rounded-xl border border-border/60 bg-card/65 p-2 shadow-xs backdrop-blur-md">
                {!collapsed && (
                    <div className="mb-3 space-y-2 px-1 pt-1">
                        <div className="flex items-center justify-between">
                            <div className="flex items-center gap-2">
                                <span className="flex size-6 items-center justify-center rounded-md bg-primary/10 text-primary">
                                    <Layers className="size-3.5" aria-hidden="true" />
                                </span>
                                <span className="text-xs font-semibold tracking-tight text-foreground">Navigation</span>
                            </div>
                            <Badge variant="outline" className="h-5 px-1.5 text-[10px] font-mono text-muted-foreground">
                                16 sections
                            </Badge>
                        </div>

                        <div className="relative">
                            <Search className="pointer-events-none absolute top-1/2 left-2 size-3 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                value={filterQuery}
                                onChange={(e) => setFilterQuery(e.target.value)}
                                placeholder="Filter sections..."
                                className="h-7.5 pl-7 pr-6 text-xs bg-muted/30 border-border/50"
                            />
                            {filterQuery && (
                                <button
                                    onClick={() => setFilterQuery("")}
                                    className="absolute top-1/2 right-1.5 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                                    aria-label="Clear filter"
                                >
                                    <X className="size-2.5" />
                                </button>
                            )}
                        </div>
                    </div>
                )}

                <div className="max-h-[calc(100vh-10rem)] overflow-y-auto pr-0.5 scrollbar-thin">
                    <SettingsNavigationList
                        access={access}
                        activeSection={activeSection}
                        collapsed={collapsed}
                        filterQuery={filterQuery}
                    />
                </div>
            </div>
        </aside>
    );
}

export function SystemSettingsStateBadge({ item, access }: { item: SystemSettingsItem; access: SystemManagementAccess }) {
    const status = getSystemSettingsStatus(item, access);

    return (
        <Badge
            variant="outline"
            className={cn("gap-1.5 text-xs font-normal border-border/60 bg-background/60", status.badgeClass)}
        >
            <span className={cn("size-1.5 rounded-full shrink-0", status.dotColor)} aria-hidden="true" />
            {status.label}
        </Badge>
    );
}
