import AdminLayout from "@/components/administrators/admin-layout";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Input } from "@/components/ui/input";
import { Separator } from "@/components/ui/separator";
import { Switch } from "@/components/ui/switch";
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from "@/components/ui/tooltip";
import { cn } from "@/lib/utils";
import type { User } from "@/types/user";
import { Head, router } from "@inertiajs/react";
import {
    Calendar,
    Eye,
    Filter,
    FlaskConical,
    Globe,
    GraduationCap,
    Layers,
    Sparkles,
    UserCog,
    Users,
    Zap,
} from "lucide-react";
import { useCallback, useState } from "react";
import { useDebouncedCallback } from "use-debounce";
import { toast } from "sonner";

interface OnboardingStepData {
    title: string;
    summary: string;
    badge?: string;
    accent?: string;
    icon?: string;
    image?: string;
    highlights?: string[];
    stats?: { label: string; value: string }[];
}

interface OnboardingStep {
    type: string;
    data: OnboardingStepData;
}

interface FeatureToggle {
    key: string;
    name: string;
    audience: "student" | "faculty" | "all";
    summary: string;
    badge: string;
    accent: string;
    cta_label: string;
    cta_url: string;
    steps: OnboardingStep[];
    steps_count: number;
    category: string;
    is_active: boolean;
    pennant_class: string | null;
    pennant_type: "class" | "string";
    pennant_global_state: boolean;
    pennant_user_overrides_count: number;
}

interface OverriddenUser {
    id: number;
    name: string;
    email: string;
    role: string;
    is_active: boolean;
}

interface Props {
    auth: { user: User };
    features: FeatureToggle[];
    filters: {
        search?: string | null;
        audience?: string | null;
        status?: string | null;
    };
}

const audienceConfig = {
    student: {
        label: "Students",
        icon: GraduationCap,
        dot: "bg-blue-500",
        bg: "bg-blue-500/8 text-blue-700 dark:text-blue-400",
    },
    faculty: {
        label: "Faculty",
        icon: Users,
        dot: "bg-violet-500",
        bg: "bg-violet-500/8 text-violet-700 dark:text-violet-400",
    },
    all: {
        label: "Everyone",
        icon: Sparkles,
        dot: "bg-amber-500",
        bg: "bg-amber-500/8 text-amber-700 dark:text-amber-400",
    },
};

export default function FeatureTogglesIndex({ auth, features: initialFeatures, filters }: Props) {
    const [search, setSearch] = useState(filters.search || "");
    const [previewFeature, setPreviewFeature] = useState<FeatureToggle | null>(null);
    const [overridesFeature, setOverridesFeature] = useState<FeatureToggle | null>(null);
    const [overriddenUsers, setOverriddenUsers] = useState<OverriddenUser[]>([]);
    const [loadingOverrides, setLoadingOverrides] = useState(false);
    const [userIdInput, setUserIdInput] = useState("");
    const [localFeatures, setLocalFeatures] = useState<FeatureToggle[]>(initialFeatures);

    const handleSearch = useDebouncedCallback((term: string) => {
        router.get(route("administrators.feature-toggles.index"), { ...filters, search: term || null }, { preserveState: true, replace: true });
    }, 300);

    const handleFilterChange = (key: string, value: string | null) => {
        router.get(route("administrators.feature-toggles.index"), { ...filters, [key]: value }, { preserveState: true, replace: true });
    };

    const clearFilters = () => {
        router.get(route("administrators.feature-toggles.index"), {}, { preserveState: true, replace: true });
        setSearch("");
    };

    const handleToggle = useCallback((feature: FeatureToggle) => {
        const newActive = !feature.is_active;
        // Optimistic: instantly flip the switch locally
        setLocalFeatures((prev) =>
            prev.map((f) =>
                f.key === feature.key
                    ? { ...f, is_active: newActive, pennant_global_state: newActive }
                    : f,
            ),
        );

        router.post(
            route("administrators.feature-toggles.toggle", feature.key),
            {},
            {
                preserveState: true,
                onSuccess: () => {
                    toast.success(`${feature.name} ${newActive ? "activated" : "deactivated"}`);
                },
                onError: () => {
                    // Roll back on error
                    setLocalFeatures((prev) =>
                        prev.map((f) =>
                            f.key === feature.key ? { ...f, is_active: !newActive, pennant_global_state: !newActive } : f,
                        ),
                    );
                    toast.error("Failed to toggle feature");
                },
            },
        );
    }, []);

    const loadOverrides = useCallback(async (feature: FeatureToggle) => {
        if (!feature.pennant_class) {
            toast.error("No class-based feature registered for this key");
            return;
        }
        setOverridesFeature(feature);
        setLoadingOverrides(true);
        try {
            const response = await fetch(route("administrators.feature-toggles.overridden-users", feature.key));
            const data = await response.json();
            setOverriddenUsers(data.users || []);
        } catch {
            toast.error("Failed to load user overrides");
        } finally {
            setLoadingOverrides(false);
        }
    }, []);

    const handleActivateForUser = useCallback((feature: FeatureToggle, userId: number) => {
        // Optimistic: instantly show user in override list
        setOverriddenUsers((prev) => {
            const existing = prev.find((u) => u.id === userId);
            if (existing) {
                return prev.map((u) => (u.id === userId ? { ...u, is_active: true } : u));
            }
            return [...prev, { id: userId, name: `User #${userId}`, email: "", role: "", is_active: true }];
        });
        // Update override count
        setLocalFeatures((prev) =>
            prev.map((f) =>
                f.key === feature.key ? { ...f, pennant_user_overrides_count: f.pennant_user_overrides_count + 1 } : f,
            ),
        );

        router.post(
            route("administrators.feature-toggles.activate-for-user", feature.key),
            { user_id: userId },
            {
                preserveState: true,
                onSuccess: () => {
                    toast.success("Feature activated for user");
                    if (overridesFeature) loadOverrides(overridesFeature);
                },
                onError: () => {
                    // Roll back
                    setOverriddenUsers((prev) => prev.filter((u) => u.id !== userId));
                    setLocalFeatures((prev) =>
                        prev.map((f) =>
                            f.key === feature.key ? { ...f, pennant_user_overrides_count: Math.max(0, f.pennant_user_overrides_count - 1) } : f,
                        ),
                    );
                    toast.error("Failed to activate for user");
                },
            },
        );
    }, [overridesFeature, loadOverrides]);

    const handleDeactivateForUser = useCallback((feature: FeatureToggle, userId: number) => {
        // Optimistic: instantly flip user's state
        setOverriddenUsers((prev) =>
            prev.map((u) => (u.id === userId ? { ...u, is_active: false } : u)),
        );

        router.post(
            route("administrators.feature-toggles.deactivate-for-user", feature.key),
            { user_id: userId },
            {
                preserveState: true,
                onSuccess: () => {
                    toast.success("Feature deactivated for user");
                    if (overridesFeature) loadOverrides(overridesFeature);
                },
                onError: () => {
                    // Roll back
                    setOverriddenUsers((prev) =>
                        prev.map((u) => (u.id === userId ? { ...u, is_active: true } : u)),
                    );
                    toast.error("Failed to deactivate for user");
                },
            },
        );
    }, [overridesFeature, loadOverrides]);

    const handlePurgeOverrides = useCallback((feature: FeatureToggle) => {
        // Optimistic: instantly clear overrides
        setOverriddenUsers([]);
        setLocalFeatures((prev) =>
            prev.map((f) => (f.key === feature.key ? { ...f, pennant_user_overrides_count: 0 } : f)),
        );

        router.post(
            route("administrators.feature-toggles.purge-overrides", feature.key),
            {},
            {
                preserveState: true,
                onSuccess: () => {
                    toast.success("All per-user overrides purged");
                },
                onError: () => {
                    // Reload to get accurate state
                    if (overridesFeature) loadOverrides(overridesFeature);
                    toast.error("Failed to purge overrides");
                },
            },
        );
    }, [overridesFeature, loadOverrides]);

    const activeFilterCount = Object.values(filters).filter(Boolean).length - (filters.search ? 1 : 0);
    const features = localFeatures;
    const activeCount = features.filter((f) => f.is_active).length;
    const inactiveCount = features.length - activeCount;
    const classBasedCount = features.filter((f) => f.pennant_type === "class").length;
    const overridesTotal = features.reduce((sum, f) => sum + f.pennant_user_overrides_count, 0);

    return (
        <AdminLayout user={auth.user} title="Feature Toggles">
            <Head title="Administrators • Feature Toggles" />

            <div className="flex flex-col">
                {/* Header */}
                <div className="border-b px-6 py-5">
                    <div className="flex items-center justify-between">
                        <div className="flex items-center gap-3">
                            <div className="bg-primary/10 text-primary flex h-9 w-9 items-center justify-center rounded-lg">
                                <FlaskConical className="h-4.5 w-4.5" />
                            </div>
                            <div>
                                <h1 className="text-lg font-semibold tracking-tight">Feature Toggles</h1>
                                <p className="text-muted-foreground text-xs">Pennant class-based features with per-user scoping, lottery rollouts, and A/B testing</p>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Stats row */}
                <div className="border-b px-6 py-3">
                    <div className="grid grid-cols-5 gap-3">
                        <div className="flex items-center gap-2.5">
                            <div className="bg-muted flex h-8 w-8 items-center justify-center rounded-md">
                                <Layers className="text-muted-foreground h-3.5 w-3.5" />
                            </div>
                            <div>
                                <p className="text-sm font-semibold tabular-nums">{features.length}</p>
                                <p className="text-muted-foreground text-[10px] leading-none">Total</p>
                            </div>
                        </div>
                        <div className="flex items-center gap-2.5">
                            <div className="flex h-8 w-8 items-center justify-center rounded-md bg-emerald-500/8">
                                <Zap className="h-3.5 w-3.5 text-emerald-600" />
                            </div>
                            <div>
                                <p className="text-sm font-semibold tabular-nums text-emerald-600">{activeCount}</p>
                                <p className="text-muted-foreground text-[10px] leading-none">Active</p>
                            </div>
                        </div>
                        <div className="flex items-center gap-2.5">
                            <div className="bg-muted flex h-8 w-8 items-center justify-center rounded-md">
                                <div className="bg-muted-foreground h-1.5 w-1.5 rounded-full" />
                            </div>
                            <div>
                                <p className="text-muted-foreground text-sm font-semibold tabular-nums">{inactiveCount}</p>
                                <p className="text-muted-foreground text-[10px] leading-none">Inactive</p>
                            </div>
                        </div>
                        <div className="flex items-center gap-2.5">
                            <div className="flex h-8 w-8 items-center justify-center rounded-md bg-blue-500/8">
                                <Globe className="h-3.5 w-3.5 text-blue-600" />
                            </div>
                            <div>
                                <p className="text-sm font-semibold tabular-nums text-blue-600">{classBasedCount}</p>
                                <p className="text-muted-foreground text-[10px] leading-none">Class-based</p>
                            </div>
                        </div>
                        <div className="flex items-center gap-2.5">
                            <div className="flex h-8 w-8 items-center justify-center rounded-md bg-purple-500/8">
                                <UserCog className="h-3.5 w-3.5 text-purple-600" />
                            </div>
                            <div>
                                <p className="text-sm font-semibold tabular-nums text-purple-600">{overridesTotal}</p>
                                <p className="text-muted-foreground text-[10px] leading-none">User overrides</p>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Toolbar */}
                <div className="border-b px-6 py-2.5">
                    <div className="flex items-center gap-2">
                        <div className="relative max-w-sm flex-1">
                            <Filter className="text-muted-foreground absolute top-2 left-2.5 h-3.5 w-3.5" />
                            <Input
                                placeholder="Search by name or key..."
                                className="bg-muted/50 h-8 rounded-md border-0 pl-8 text-xs shadow-none focus-visible:bg-background focus-visible:ring-1"
                                value={search}
                                onChange={(e) => {
                                    setSearch(e.target.value);
                                    handleSearch(e.target.value);
                                }}
                            />
                        </div>

                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button variant="outline" size="sm" className="h-8 gap-1.5 border-dashed text-xs">
                                    <Filter className="h-3 w-3" />
                                    Filter
                                    {activeFilterCount > 0 && (
                                        <Badge variant="secondary" className="ml-0.5 h-4 rounded px-1 text-[9px]">
                                            {activeFilterCount}
                                        </Badge>
                                    )}
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" className="w-[180px]">
                                <DropdownMenuLabel className="text-[10px]">Audience</DropdownMenuLabel>
                                <DropdownMenuSeparator />
                                {(["student", "faculty", "all"] as const).map((audience) => {
                                    const Icon = audienceConfig[audience].icon;
                                    return (
                                        <DropdownMenuCheckboxItem
                                            key={audience}
                                            checked={filters.audience === audience}
                                            onCheckedChange={(checked) => handleFilterChange("audience", checked ? audience : null)}
                                            className="text-xs"
                                        >
                                            <Icon className="mr-1.5 h-3 w-3" />
                                            {audienceConfig[audience].label}
                                        </DropdownMenuCheckboxItem>
                                    );
                                })}
                                <DropdownMenuSeparator />
                                <DropdownMenuLabel className="text-[10px]">Status</DropdownMenuLabel>
                                <DropdownMenuSeparator />
                                <DropdownMenuCheckboxItem
                                    checked={filters.status === "active"}
                                    onCheckedChange={(checked) => handleFilterChange("status", checked ? "active" : null)}
                                    className="text-xs"
                                >
                                    <span className="mr-1.5 h-2 w-2 rounded-full bg-emerald-500" />
                                    Active
                                </DropdownMenuCheckboxItem>
                                <DropdownMenuCheckboxItem
                                    checked={filters.status === "inactive"}
                                    onCheckedChange={(checked) => handleFilterChange("status", checked ? "inactive" : null)}
                                    className="text-xs"
                                >
                                    <span className="mr-1.5 h-2 w-2 rounded-full bg-slate-300" />
                                    Inactive
                                </DropdownMenuCheckboxItem>
                            </DropdownMenuContent>
                        </DropdownMenu>

                        {activeFilterCount > 0 && (
                            <Button variant="ghost" size="sm" onClick={clearFilters} className="text-muted-foreground h-8 gap-1 text-xs">
                                Clear
                            </Button>
                        )}
                    </div>
                </div>

                {/* Content */}
                <div className="flex-1 p-6">
                    {features.length === 0 ? (
                        <div className="flex flex-col items-center justify-center py-20 text-center">
                            <div className="bg-muted/40 mb-4 flex h-14 w-14 items-center justify-center rounded-xl">
                                <FlaskConical className="text-muted-foreground/40 h-6 w-6" />
                            </div>
                            <h3 className="text-sm font-semibold">No features found</h3>
                            <p className="text-muted-foreground mt-1 max-w-xs text-xs">
                                {filters.search || activeFilterCount > 0
                                    ? "Try adjusting your filters or search terms."
                                    : "Feature toggles are defined in code. Register new toggles in app/Features/Toggles/."}
                            </p>
                            {filters.search || activeFilterCount > 0 ? (
                                <div className="mt-4 flex gap-2">
                                    <Button variant="outline" size="sm" onClick={clearFilters}>
                                        Clear filters
                                    </Button>
                                </div>
                            ) : null}
                        </div>
                    ) : (
                        <div className="divide-y overflow-hidden rounded-lg border">
                            {features.map((feature) => {
                                const AudienceIcon = audienceConfig[feature.audience].icon;
                                const isClassBased = feature.pennant_type === "class";
                                const hasOverrides = feature.pennant_user_overrides_count > 0;

                                return (
                                    <div
                                        key={feature.key}
                                        className={cn(
                                            "group relative flex items-center gap-4 bg-background px-4 py-3 transition-colors hover:bg-muted/30",
                                            !feature.is_active && "opacity-50 hover:opacity-100",
                                        )}
                                    >
                                        {/* Status indicator */}
                                        <div className="flex items-center gap-3">
                                            <TooltipProvider>
                                                <Tooltip>
                                                    <TooltipTrigger asChild>
                                                        <Switch
                                                            checked={feature.is_active}
                                                            onCheckedChange={() => handleToggle(feature)}
                                                            className="data-[state=checked]:bg-emerald-500 data-[size=sm]"
                                                        />
                                                    </TooltipTrigger>
                                                    <TooltipContent side="left" className="text-xs">
                                                        {feature.is_active ? "Deactivate globally" : "Activate globally"}
                                                    </TooltipContent>
                                                </Tooltip>
                                            </TooltipProvider>
                                        </div>

                                        {/* Feature info */}
                                        <div className="min-w-0 flex-1">
                                            <div className="flex items-center gap-2">
                                                <span className="text-sm font-medium leading-tight">{feature.name}</span>
                                                <Badge variant="outline" className={cn("gap-1 text-[10px] font-medium", audienceConfig[feature.audience].bg)}>
                                                    <AudienceIcon className="h-2.5 w-2.5" />
                                                    {audienceConfig[feature.audience].label}
                                                </Badge>
                                                {isClassBased && (
                                                    <Badge variant="secondary" className="gap-0.5 bg-blue-500/8 text-[10px] text-blue-600">
                                                        <Globe className="h-2.5 w-2.5" />
                                                        Pennant
                                                    </Badge>
                                                )}
                                                {hasOverrides && (
                                                    <Badge variant="secondary" className="gap-0.5 bg-purple-500/8 text-[10px] text-purple-600">
                                                        <UserCog className="h-2.5 w-2.5" />
                                                        {feature.pennant_user_overrides_count} override{feature.pennant_user_overrides_count !== 1 ? "s" : ""}
                                                    </Badge>
                                                )}
                                            </div>
                                            <div className="mt-0.5 flex items-center gap-3">
                                                <code className="text-muted-foreground text-[11px] font-mono">{feature.key}</code>
                                                {isClassBased && feature.pennant_class && (
                                                    <>
                                                        <span className="text-muted-foreground/30">→</span>
                                                        <code className="text-muted-foreground/60 max-w-[200px] truncate text-[10px] font-mono">
                                                            {feature.pennant_class.replace("App\\Features\\Toggles\\", "…\\")}
                                                        </code>
                                                    </>
                                                )}
                                                {feature.summary && (
                                                    <>
                                                        <span className="text-muted-foreground/30">·</span>
                                                        <span className="text-muted-foreground line-clamp-1 text-[11px]">{feature.summary}</span>
                                                    </>
                                                )}
                                            </div>
                                        </div>

                                        {/* Pennant metadata */}
                                        <div className="text-muted-foreground hidden items-center gap-3 text-[11px] lg:flex">
                                            {isClassBased && (
                                                <TooltipProvider>
                                                    <Tooltip>
                                                        <TooltipTrigger asChild>
                                                            <span className={cn("flex items-center gap-1", feature.pennant_global_state ? "text-emerald-600" : "text-muted-foreground/50")}>
                                                                <Globe className="h-3 w-3" />
                                                                {feature.pennant_global_state ? "Global" : "Default"}
                                                            </span>
                                                        </TooltipTrigger>
                                                        <TooltipContent className="text-xs">
                                                            {feature.pennant_global_state ? "Force-activated for everyone" : "Resolved per-scope via class"}
                                                        </TooltipContent>
                                                    </Tooltip>
                                                </TooltipProvider>
                                            )}
                                            {hasOverrides && (
                                                <button
                                                    onClick={() => loadOverrides(feature)}
                                                    className="flex items-center gap-1 text-purple-600 hover:underline"
                                                >
                                                    <UserCog className="h-3 w-3" />
                                                    {feature.pennant_user_overrides_count}
                                                </button>
                                            )}
                                            <span className="flex items-center gap-1 tabular-nums">
                                                {feature.steps_count} step{feature.steps_count !== 1 ? "s" : ""}
                                            </span>
                                        </div>

                                        {/* Actions */}
                                        <div className="flex items-center gap-1">
                                            {isClassBased && (
                                                <TooltipProvider>
                                                    <Tooltip>
                                                        <TooltipTrigger asChild>
                                                            <Button variant="ghost" size="icon" className="h-7 w-7" onClick={() => loadOverrides(feature)}>
                                                                <UserCog className="h-3.5 w-3.5" />
                                                            </Button>
                                                        </TooltipTrigger>
                                                        <TooltipContent className="text-xs">Manage per-user overrides</TooltipContent>
                                                    </Tooltip>
                                                </TooltipProvider>
                                            )}
                                            <Button variant="ghost" size="icon" className="h-7 w-7" onClick={() => setPreviewFeature(feature)}>
                                                <Eye className="h-3.5 w-3.5" />
                                            </Button>
                                            <DropdownMenu>
                                                <DropdownMenuTrigger asChild>
                                                    <Button variant="ghost" size="icon" className="h-7 w-7">
                                                        <Calendar className="h-3.5 w-3.5" />
                                                    </Button>
                                                </DropdownMenuTrigger>
                                                <DropdownMenuContent align="end" className="w-[160px]">
                                                    <DropdownMenuItem onClick={() => setPreviewFeature(feature)} className="text-xs">
                                                        <Eye className="mr-2 h-3.5 w-3.5" />
                                                        Preview
                                                    </DropdownMenuItem>
                                                    {isClassBased && (
                                                        <>
                                                            <DropdownMenuSeparator />
                                                            <DropdownMenuItem onClick={() => loadOverrides(feature)} className="text-xs">
                                                                <UserCog className="mr-2 h-3.5 w-3.5" />
                                                                User overrides
                                                            </DropdownMenuItem>
                                                            {hasOverrides && (
                                                                <DropdownMenuItem onClick={() => handlePurgeOverrides(feature)} className="text-xs text-orange-600 focus:text-orange-600">
                                                                    Purge overrides
                                                                </DropdownMenuItem>
                                                            )}
                                                        </>
                                                    )}
                                                </DropdownMenuContent>
                                            </DropdownMenu>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    )}
                </div>
            </div>

            {/* Preview Dialog */}
            <Dialog open={!!previewFeature} onOpenChange={(open) => !open && setPreviewFeature(null)}>
                <DialogContent className="max-h-[80vh] max-w-xl overflow-y-auto">
                    {previewFeature && (
                        <>
                            <DialogHeader>
                                <div className="mb-1 flex items-center gap-2">
                                    <Badge variant="outline" className={cn("text-[10px]", audienceConfig[previewFeature.audience].bg)}>
                                        {(() => {
                                            const Icon = audienceConfig[previewFeature.audience].icon;
                                            return <Icon className="mr-1 h-2.5 w-2.5" />;
                                        })()}
                                        {audienceConfig[previewFeature.audience].label}
                                    </Badge>
                                    {previewFeature.is_active ? (
                                        <Badge className="bg-emerald-500/10 text-[10px] text-emerald-600">Active</Badge>
                                    ) : (
                                        <Badge variant="secondary" className="text-[10px]">Inactive</Badge>
                                    )}
                                    {previewFeature.pennant_type === "class" && (
                                        <Badge variant="secondary" className="gap-0.5 bg-blue-500/8 text-[10px] text-blue-600">
                                            <Globe className="h-2.5 w-2.5" />
                                            Pennant Class
                                        </Badge>
                                    )}
                                    {previewFeature.pennant_global_state && (
                                        <Badge variant="secondary" className="gap-0.5 bg-emerald-500/8 text-[10px] text-emerald-600">
                                            <Globe className="h-2.5 w-2.5" />
                                            Global
                                        </Badge>
                                    )}
                                </div>
                                <DialogTitle className="text-lg">{previewFeature.name}</DialogTitle>
                                <DialogDescription className="text-xs">{previewFeature.summary}</DialogDescription>
                            </DialogHeader>

                            <div className="mt-3 space-y-4">
                                <div className="grid grid-cols-2 gap-3">
                                    <div>
                                        <p className="text-muted-foreground mb-1 text-[10px] font-medium uppercase">Feature Key</p>
                                        <code className="bg-muted rounded px-2 py-1 font-mono text-xs">{previewFeature.key}</code>
                                    </div>
                                    {previewFeature.pennant_class && (
                                        <div>
                                            <p className="text-muted-foreground mb-1 text-[10px] font-medium uppercase">Pennant Class</p>
                                            <code className="bg-muted block truncate rounded px-2 py-1 font-mono text-[11px]">{previewFeature.pennant_class}</code>
                                        </div>
                                    )}
                                    {previewFeature.cta_url && (
                                        <div>
                                            <p className="text-muted-foreground mb-1 text-[10px] font-medium uppercase">CTA</p>
                                            <a href={previewFeature.cta_url} className="text-primary flex items-center gap-1 text-xs hover:underline">
                                                {previewFeature.cta_label || previewFeature.cta_url}
                                            </a>
                                        </div>
                                    )}
                                    <div>
                                        <p className="text-muted-foreground mb-1 text-[10px] font-medium uppercase">User Overrides</p>
                                        <span className="text-xs">{previewFeature.pennant_user_overrides_count} user{previewFeature.pennant_user_overrides_count !== 1 ? "s" : ""} with overrides</span>
                                    </div>
                                </div>

                                {previewFeature.steps.length > 0 && (
                                    <div>
                                        <Separator className="mb-3" />
                                        <p className="mb-2 text-xs font-medium">Onboarding Steps ({previewFeature.steps.length})</p>
                                        <div className="space-y-2">
                                            {previewFeature.steps.map((step, index) => (
                                                <div key={index} className="flex gap-2.5 rounded-md border p-2.5">
                                                    <div className="bg-primary/10 text-primary flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-[10px] font-bold">
                                                        {index + 1}
                                                    </div>
                                                    <div className="min-w-0 flex-1">
                                                        <p className="text-xs font-medium leading-tight">{step.data.title}</p>
                                                        <p className="text-muted-foreground mt-0.5 line-clamp-2 text-[11px]">{step.data.summary}</p>
                                                        {step.data.highlights && step.data.highlights.length > 0 && (
                                                            <div className="mt-1.5 flex flex-wrap gap-1">
                                                                {step.data.highlights.filter(Boolean).map((h, i) => (
                                                                    <Badge key={i} variant="secondary" className="text-[9px]">
                                                                        {h}
                                                                    </Badge>
                                                                ))}
                                                            </div>
                                                        )}
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                )}
                            </div>

                            <DialogFooter className="mt-4 gap-2">
                                <Button variant="outline" size="sm" onClick={() => setPreviewFeature(null)}>
                                    Close
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </DialogContent>
            </Dialog>

            {/* User Overrides Dialog */}
            <Dialog open={!!overridesFeature} onOpenChange={(open) => !open && { setOverridesFeature: setOverridesFeature(null), setOverriddenUsers: setOverriddenUsers([]) }.setOverridesFeature(open ? overridesFeature : null)}>
                <DialogContent className="max-h-[80vh] sm:max-w-lg">
                    {overridesFeature && (
                        <>
                            <DialogHeader>
                                <DialogTitle className="text-base">User Overrides — {overridesFeature.name}</DialogTitle>
                                <DialogDescription className="text-xs">
                                    Manage per-user feature flag overrides. Users listed here have explicit overrides that bypass the default resolution.
                                </DialogDescription>
                            </DialogHeader>

                            {/* Add user override */}
                            <div className="flex gap-2">
                                <Input
                                    placeholder="Enter user ID to add override..."
                                    className="h-8 text-xs"
                                    value={userIdInput}
                                    onChange={(e) => setUserIdInput(e.target.value)}
                                    onKeyDown={(e) => {
                                        if (e.key === "Enter" && userIdInput) {
                                            handleActivateForUser(overridesFeature, parseInt(userIdInput));
                                            setUserIdInput("");
                                        }
                                    }}
                                />
                                <Button
                                    size="sm"
                                    className="h-8 gap-1.5 text-xs"
                                    disabled={!userIdInput}
                                    onClick={() => {
                                        if (userIdInput) {
                                            handleActivateForUser(overridesFeature, parseInt(userIdInput));
                                            setUserIdInput("");
                                        }
                                    }}
                                >
                                    <UserCog className="h-3.5 w-3.5" />
                                    Activate
                                </Button>
                            </div>

                            {/* Overridden users list */}
                            <div className="max-h-[40vh] overflow-y-auto">
                                {loadingOverrides ? (
                                    <div className="flex items-center justify-center py-8">
                                        <div className="h-5 w-5 animate-spin rounded-full border-2 border-current border-t-transparent" />
                                    </div>
                                ) : overriddenUsers.length === 0 ? (
                                    <div className="flex flex-col items-center justify-center py-8 text-center">
                                        <UserCog className="text-muted-foreground/30 mb-2 h-8 w-8" />
                                        <p className="text-muted-foreground text-xs">No per-user overrides</p>
                                        <p className="text-muted-foreground/60 text-[11px]">All users follow the default resolution logic</p>
                                    </div>
                                ) : (
                                    <div className="divide-y rounded-md border">
                                        {overriddenUsers.map((user) => (
                                            <div key={user.id} className="flex items-center gap-3 px-3 py-2">
                                                <div className="min-w-0 flex-1">
                                                    <p className="text-xs font-medium">{user.name}</p>
                                                    <p className="text-muted-foreground text-[11px]">{user.email} · {user.role}</p>
                                                </div>
                                                <Badge
                                                    variant={user.is_active ? "default" : "secondary"}
                                                    className={cn(
                                                        "text-[10px]",
                                                        user.is_active && "bg-emerald-500/10 text-emerald-600",
                                                    )}
                                                >
                                                    {user.is_active ? "Active" : "Inactive"}
                                                </Badge>
                                                <div className="flex gap-1">
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        className="h-6 w-6"
                                                        onClick={() =>
                                                            user.is_active
                                                                ? handleDeactivateForUser(overridesFeature, user.id)
                                                                : handleActivateForUser(overridesFeature, user.id)
                                                        }
                                                    >
                                                        <Switch
                                                            checked={user.is_active}
                                                            className="data-[state=checked]:bg-emerald-500 scale-75"
                                                        />
                                                    </Button>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </div>

                            <DialogFooter className="gap-2">
                                {overriddenUsers.length > 0 && (
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        className="gap-1.5 text-xs text-orange-600 hover:text-orange-700"
                                        onClick={() => handlePurgeOverrides(overridesFeature)}
                                    >
                                        Purge All Overrides
                                    </Button>
                                )}
                                <Button variant="outline" size="sm" onClick={() => { setOverridesFeature(null); setOverriddenUsers([]); }}>
                                    Close
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </DialogContent>
            </Dialog>
        </AdminLayout>
    );
}