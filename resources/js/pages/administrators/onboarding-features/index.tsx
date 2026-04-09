import AdminLayout from "@/components/administrators/admin-layout";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
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
import { Switch } from "@/components/ui/switch";
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from "@/components/ui/tooltip";
import { cn } from "@/lib/utils";
import type { User } from "@/types/user";
import { Head, Link, router } from "@inertiajs/react";
import {
    ChevronRight,
    ExternalLink,
    Eye,
    Filter,
    FlaskConical,
    GraduationCap,
    ListChecks,
    MoreHorizontal,
    Pencil,
    Plus,
    Search,
    Sparkles,
    Target,
    Trash2,
    Users,
    X,
    Zap,
} from "lucide-react";
import { useState } from "react";
import { toast } from "sonner";
import { useDebouncedCallback } from "use-debounce";

declare let route: any;

interface OnboardingStep {
    type: string;
    data: {
        title: string;
        summary: string;
        badge?: string;
        accent?: string;
        icon?: string;
        image?: string;
        highlights?: string[];
        stats?: { label: string; value: string }[];
    };
}

interface OnboardingFeature {
    id: number;
    feature_key: string;
    name: string;
    audience: "student" | "faculty" | "all";
    summary: string | null;
    badge: string | null;
    accent: string | null;
    cta_label: string | null;
    cta_url: string | null;
    steps: OnboardingStep[];
    steps_count: number;
    is_active: boolean;
    created_at: string;
    updated_at: string;
}

interface Props {
    auth: { user: User };
    features: OnboardingFeature[];
    experimental_keys: string[];
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
        color: "bg-blue-500/10 text-blue-600 border-blue-500/20",
    },
    faculty: {
        label: "Faculty",
        icon: Users,
        color: "bg-purple-500/10 text-purple-600 border-purple-500/20",
    },
    all: {
        label: "Everyone",
        icon: Sparkles,
        color: "bg-amber-500/10 text-amber-600 border-amber-500/20",
    },
};

export default function OnboardingFeaturesIndex({ auth, features, experimental_keys, filters }: Props) {
    const [search, setSearch] = useState(filters.search || "");
    const [deleteTarget, setDeleteTarget] = useState<OnboardingFeature | null>(null);
    const [previewFeature, setPreviewFeature] = useState<OnboardingFeature | null>(null);

    const handleSearch = useDebouncedCallback((term: string) => {
        router.get(route("administrators.onboarding-features.index"), { ...filters, search: term || null }, { preserveState: true, replace: true });
    }, 300);

    const handleFilterChange = (key: string, value: string | null) => {
        router.get(route("administrators.onboarding-features.index"), { ...filters, [key]: value }, { preserveState: true, replace: true });
    };

    const clearFilters = () => {
        router.get(route("administrators.onboarding-features.index"), {}, { preserveState: true, replace: true });
        setSearch("");
    };

    const handleToggle = (feature: OnboardingFeature) => {
        router.post(
            route("administrators.onboarding-features.toggle", feature.id),
            {},
            {
                preserveState: true,
                onSuccess: () => {
                    toast.success(`${feature.name} ${feature.is_active ? "deactivated" : "activated"}`);
                },
                onError: () => toast.error("Failed to toggle feature"),
            },
        );
    };

    const handleDelete = () => {
        if (!deleteTarget) return;
        router.delete(route("administrators.onboarding-features.destroy", deleteTarget.id), {
            preserveState: true,
            onSuccess: () => {
                toast.success(`"${deleteTarget.name}" deleted`);
                setDeleteTarget(null);
            },
            onError: () => toast.error("Failed to delete feature"),
        });
    };

    const activeFilterCount = Object.values(filters).filter(Boolean).length - (filters.search ? 1 : 0);
    const activeFeatures = features.filter((f) => f.is_active).length;
    const experimentalFeatures = features.filter((f) => experimental_keys.includes(f.feature_key)).length;

    return (
        <AdminLayout user={auth.user} title="Onboarding Features">
            <Head title="Administrators • Onboarding Features" />

            <div className="from-background via-background to-muted/20 flex min-h-[calc(100vh-65px)] flex-col bg-gradient-to-br">
                {/* Hero Header */}
                <div className="from-primary/5 to-primary/5 relative overflow-hidden border-b bg-gradient-to-r via-transparent">
                    <div className="bg-grid-white/10 absolute inset-0 [mask-image:linear-gradient(0deg,transparent,black)]" />
                    <div className="relative px-6 py-8">
                        <div className="flex flex-col gap-6 md:flex-row md:items-start md:justify-between">
                            <div className="space-y-2">
                                <div className="flex items-center gap-3">
                                    <div className="bg-primary/10 text-primary flex h-12 w-12 items-center justify-center rounded-xl">
                                        <Sparkles className="h-6 w-6" />
                                    </div>
                                    <div>
                                        <h1 className="text-2xl font-bold tracking-tight">Onboarding Features</h1>
                                        <p className="text-muted-foreground text-sm">Manage guided feature introductions for portal users</p>
                                    </div>
                                </div>
                            </div>

                            <Button asChild className="shadow-primary/20 gap-2 shadow-lg">
                                <Link href={route("administrators.onboarding-features.create")}>
                                    <Plus className="h-4 w-4" />
                                    Create Feature
                                </Link>
                            </Button>
                        </div>

                        {/* Stats Cards */}
                        <div className="mt-6 grid grid-cols-2 gap-4 md:grid-cols-4">
                            <Card className="bg-background/60 border-0 backdrop-blur-sm">
                                <CardContent className="p-4">
                                    <div className="flex items-center justify-between">
                                        <div>
                                            <p className="text-muted-foreground text-xs font-medium tracking-wider uppercase">Total</p>
                                            <p className="text-2xl font-bold">{features.length}</p>
                                        </div>
                                        <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-slate-500/10">
                                            <ListChecks className="h-5 w-5 text-slate-600" />
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>

                            <Card className="bg-background/60 border-0 backdrop-blur-sm">
                                <CardContent className="p-4">
                                    <div className="flex items-center justify-between">
                                        <div>
                                            <p className="text-muted-foreground text-xs font-medium tracking-wider uppercase">Active</p>
                                            <p className="text-2xl font-bold text-emerald-600">{activeFeatures}</p>
                                        </div>
                                        <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-emerald-500/10">
                                            <Zap className="h-5 w-5 text-emerald-600" />
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>

                            <Card className="bg-background/60 border-0 backdrop-blur-sm">
                                <CardContent className="p-4">
                                    <div className="flex items-center justify-between">
                                        <div>
                                            <p className="text-muted-foreground text-xs font-medium tracking-wider uppercase">Inactive</p>
                                            <p className="text-2xl font-bold text-slate-500">{features.length - activeFeatures}</p>
                                        </div>
                                        <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-slate-500/10">
                                            <Target className="h-5 w-5 text-slate-500" />
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>

                            <Card className="bg-background/60 border-0 backdrop-blur-sm">
                                <CardContent className="p-4">
                                    <div className="flex items-center justify-between">
                                        <div>
                                            <p className="text-muted-foreground text-xs font-medium tracking-wider uppercase">Experimental</p>
                                            <p className="text-2xl font-bold text-purple-600">{experimentalFeatures}</p>
                                        </div>
                                        <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-purple-500/10">
                                            <FlaskConical className="h-5 w-5 text-purple-600" />
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>
                    </div>
                </div>

                {/* Toolbar */}
                <div className="bg-background/95 sticky top-0 z-10 border-b px-6 py-3 backdrop-blur-md">
                    <div className="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                        <div className="flex flex-1 items-center gap-2">
                            <div className="relative max-w-sm flex-1">
                                <Search className="text-muted-foreground absolute top-2.5 left-2.5 h-4 w-4" />
                                <Input
                                    placeholder="Search features..."
                                    className="bg-background h-9 pl-9"
                                    value={search}
                                    onChange={(e) => {
                                        setSearch(e.target.value);
                                        handleSearch(e.target.value);
                                    }}
                                />
                            </div>

                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <Button variant="outline" size="sm" className="h-9 gap-2 border-dashed">
                                        <Filter className="h-3.5 w-3.5" />
                                        Filters
                                        {activeFilterCount > 0 && (
                                            <Badge variant="secondary" className="ml-1 h-5 rounded-sm px-1.5 text-[10px]">
                                                {activeFilterCount}
                                            </Badge>
                                        )}
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end" className="w-[200px]">
                                    <DropdownMenuLabel>Filter by Audience</DropdownMenuLabel>
                                    <DropdownMenuSeparator />
                                    {(["student", "faculty", "all"] as const).map((audience) => (
                                        <DropdownMenuCheckboxItem
                                            key={audience}
                                            checked={filters.audience === audience}
                                            onCheckedChange={(checked) => handleFilterChange("audience", checked ? audience : null)}
                                        >
                                            <span className="flex items-center gap-2">
                                                {(() => {
                                                    const Icon = audienceConfig[audience].icon;
                                                    return <Icon className="h-3.5 w-3.5" />;
                                                })()}
                                                {audienceConfig[audience].label}
                                            </span>
                                        </DropdownMenuCheckboxItem>
                                    ))}
                                    <DropdownMenuSeparator />
                                    <DropdownMenuLabel>Filter by Status</DropdownMenuLabel>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuCheckboxItem
                                        checked={filters.status === "active"}
                                        onCheckedChange={(checked) => handleFilterChange("status", checked ? "active" : null)}
                                    >
                                        <span className="flex items-center gap-2">
                                            <div className="h-2 w-2 rounded-full bg-emerald-500" />
                                            Active
                                        </span>
                                    </DropdownMenuCheckboxItem>
                                    <DropdownMenuCheckboxItem
                                        checked={filters.status === "inactive"}
                                        onCheckedChange={(checked) => handleFilterChange("status", checked ? "inactive" : null)}
                                    >
                                        <span className="flex items-center gap-2">
                                            <div className="h-2 w-2 rounded-full bg-slate-400" />
                                            Inactive
                                        </span>
                                    </DropdownMenuCheckboxItem>
                                </DropdownMenuContent>
                            </DropdownMenu>

                            {activeFilterCount > 0 && (
                                <Button variant="ghost" size="sm" onClick={clearFilters} className="text-muted-foreground h-9 px-2">
                                    <X className="mr-1 h-3.5 w-3.5" />
                                    Reset
                                </Button>
                            )}
                        </div>
                    </div>
                </div>

                {/* Content */}
                <div className="flex-1 overflow-auto p-6">
                    {features.length === 0 ? (
                        <div className="flex h-full flex-col items-center justify-center py-16 text-center">
                            <div className="bg-muted/30 mb-6 flex h-20 w-20 items-center justify-center rounded-full">
                                <Sparkles className="text-muted-foreground/30 h-10 w-10" />
                            </div>
                            <h3 className="mb-2 text-xl font-semibold">No features found</h3>
                            <p className="text-muted-foreground mb-6 max-w-md">
                                {filters.search || activeFilterCount > 0
                                    ? "Try adjusting your filters or search terms."
                                    : "Create your first onboarding feature to guide users through new functionality."}
                            </p>
                            {filters.search || activeFilterCount > 0 ? (
                                <Button variant="outline" onClick={clearFilters}>
                                    Clear filters
                                </Button>
                            ) : (
                                <Button asChild>
                                    <Link href={route("administrators.onboarding-features.create")}>
                                        <Plus className="mr-2 h-4 w-4" />
                                        Create Feature
                                    </Link>
                                </Button>
                            )}
                        </div>
                    ) : (
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            {features.map((feature) => {
                                const isExperimental = experimental_keys.includes(feature.feature_key);
                                const AudienceIcon = audienceConfig[feature.audience].icon;

                                return (
                                    <Card
                                        key={feature.id}
                                        className={cn(
                                            "group hover:border-primary/30 relative overflow-hidden transition-all duration-300 hover:shadow-lg",
                                            !feature.is_active && "opacity-60 hover:opacity-100",
                                        )}
                                    >
                                        {/* Active indicator bar */}
                                        <div
                                            className={cn(
                                                "absolute top-0 right-0 left-0 h-1 transition-colors",
                                                feature.is_active
                                                    ? "bg-gradient-to-r from-emerald-500 to-emerald-400"
                                                    : "bg-slate-200 dark:bg-slate-800",
                                            )}
                                        />

                                        <CardHeader className="pb-3">
                                            <div className="flex items-start justify-between gap-2">
                                                <div className="min-w-0 flex-1">
                                                    <div className="mb-1 flex items-center gap-2">
                                                        <Badge
                                                            variant="outline"
                                                            className={cn("text-[10px] font-medium", audienceConfig[feature.audience].color)}
                                                        >
                                                            <AudienceIcon className="mr-1 h-3 w-3" />
                                                            {audienceConfig[feature.audience].label}
                                                        </Badge>
                                                        {isExperimental && (
                                                            <Badge
                                                                variant="secondary"
                                                                className="gap-1 bg-purple-100 text-[10px] text-purple-700 dark:bg-purple-900/30 dark:text-purple-400"
                                                            >
                                                                <FlaskConical className="h-2.5 w-2.5" />
                                                                Experimental
                                                            </Badge>
                                                        )}
                                                    </div>
                                                    <CardTitle className="truncate text-base leading-tight">{feature.name}</CardTitle>
                                                    <CardDescription className="mt-1 line-clamp-2 text-xs">
                                                        {feature.summary || "No description provided"}
                                                    </CardDescription>
                                                </div>

                                                <DropdownMenu>
                                                    <DropdownMenuTrigger asChild>
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            className="h-8 w-8 shrink-0 opacity-0 transition-opacity group-hover:opacity-100"
                                                        >
                                                            <MoreHorizontal className="h-4 w-4" />
                                                        </Button>
                                                    </DropdownMenuTrigger>
                                                    <DropdownMenuContent align="end">
                                                        <DropdownMenuItem onClick={() => setPreviewFeature(feature)}>
                                                            <Eye className="mr-2 h-4 w-4" />
                                                            Preview
                                                        </DropdownMenuItem>
                                                        <DropdownMenuItem asChild>
                                                            <Link href={route("administrators.onboarding-features.edit", feature.id)}>
                                                                <Pencil className="mr-2 h-4 w-4" />
                                                                Edit
                                                            </Link>
                                                        </DropdownMenuItem>
                                                        <DropdownMenuSeparator />
                                                        <DropdownMenuItem
                                                            onClick={() => setDeleteTarget(feature)}
                                                            className="text-destructive focus:text-destructive"
                                                        >
                                                            <Trash2 className="mr-2 h-4 w-4" />
                                                            Delete
                                                        </DropdownMenuItem>
                                                    </DropdownMenuContent>
                                                </DropdownMenu>
                                            </div>
                                        </CardHeader>

                                        <CardContent className="pt-0">
                                            {/* Feature key */}
                                            <div className="text-muted-foreground bg-muted/50 mb-4 truncate rounded px-2 py-1 font-mono text-[10px]">
                                                {feature.feature_key}
                                            </div>

                                            {/* Stats row */}
                                            <div className="text-muted-foreground mb-4 flex items-center justify-between text-xs">
                                                <span className="flex items-center gap-1">
                                                    <ListChecks className="h-3.5 w-3.5" />
                                                    {feature.steps_count} step{feature.steps_count !== 1 ? "s" : ""}
                                                </span>
                                                {feature.cta_url && (
                                                    <span className="flex items-center gap-1">
                                                        <ExternalLink className="h-3.5 w-3.5" />
                                                        Has CTA
                                                    </span>
                                                )}
                                            </div>

                                            {/* Actions footer */}
                                            <div className="flex items-center justify-between border-t pt-3">
                                                <TooltipProvider>
                                                    <Tooltip>
                                                        <TooltipTrigger asChild>
                                                            <div className="flex items-center gap-2">
                                                                <Switch
                                                                    checked={feature.is_active}
                                                                    onCheckedChange={() => handleToggle(feature)}
                                                                    className="data-[state=checked]:bg-emerald-500"
                                                                />
                                                                <span className="text-xs font-medium">
                                                                    {feature.is_active ? "Active" : "Inactive"}
                                                                </span>
                                                            </div>
                                                        </TooltipTrigger>
                                                        <TooltipContent>{feature.is_active ? "Deactivate" : "Activate"} this feature</TooltipContent>
                                                    </Tooltip>
                                                </TooltipProvider>

                                                <Button variant="ghost" size="sm" className="h-8 gap-1" asChild>
                                                    <Link href={route("administrators.onboarding-features.edit", feature.id)}>
                                                        Edit
                                                        <ChevronRight className="h-3.5 w-3.5" />
                                                    </Link>
                                                </Button>
                                            </div>
                                        </CardContent>
                                    </Card>
                                );
                            })}
                        </div>
                    )}
                </div>
            </div>

            {/* Delete Confirmation Dialog */}
            <Dialog open={!!deleteTarget} onOpenChange={(open) => !open && setDeleteTarget(null)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete onboarding feature?</DialogTitle>
                        <DialogDescription>
                            This will permanently delete "{deleteTarget?.name}" and deactivate the associated feature flag. This action cannot be
                            undone.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setDeleteTarget(null)}>
                            Cancel
                        </Button>
                        <Button variant="destructive" onClick={handleDelete}>
                            Delete Feature
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Preview Dialog */}
            <Dialog open={!!previewFeature} onOpenChange={(open) => !open && setPreviewFeature(null)}>
                <DialogContent className="max-h-[80vh] max-w-2xl overflow-y-auto">
                    {previewFeature && (
                        <>
                            <DialogHeader>
                                <div className="mb-2 flex items-center gap-2">
                                    <Badge variant="outline" className={cn(audienceConfig[previewFeature.audience].color)}>
                                        {audienceConfig[previewFeature.audience].label}
                                    </Badge>
                                    {previewFeature.is_active ? (
                                        <Badge className="border-emerald-500/20 bg-emerald-500/10 text-emerald-600">Active</Badge>
                                    ) : (
                                        <Badge variant="secondary">Inactive</Badge>
                                    )}
                                </div>
                                <DialogTitle className="text-xl">{previewFeature.name}</DialogTitle>
                                <DialogDescription>{previewFeature.summary}</DialogDescription>
                            </DialogHeader>

                            <div className="mt-4 space-y-4">
                                <div className="grid grid-cols-2 gap-4 text-sm">
                                    <div>
                                        <span className="text-muted-foreground">Feature Key:</span>
                                        <p className="bg-muted mt-1 rounded px-2 py-1 font-mono text-xs">{previewFeature.feature_key}</p>
                                    </div>
                                    {previewFeature.cta_url && (
                                        <div>
                                            <span className="text-muted-foreground">CTA:</span>
                                            <p className="mt-1 text-xs">
                                                <a href={previewFeature.cta_url} className="text-primary flex items-center gap-1 hover:underline">
                                                    {previewFeature.cta_label || previewFeature.cta_url}
                                                    <ExternalLink className="h-3 w-3" />
                                                </a>
                                            </p>
                                        </div>
                                    )}
                                </div>

                                {previewFeature.steps.length > 0 && (
                                    <div>
                                        <h4 className="mb-3 font-medium">Steps ({previewFeature.steps.length})</h4>
                                        <div className="space-y-3">
                                            {previewFeature.steps.map((step, index) => (
                                                <div key={index} className="bg-muted/30 flex gap-3 rounded-lg border p-3">
                                                    <div className="bg-primary/10 text-primary flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-xs font-bold">
                                                        {index + 1}
                                                    </div>
                                                    <div className="min-w-0 flex-1">
                                                        <p className="text-sm font-medium">{step.data.title}</p>
                                                        <p className="text-muted-foreground mt-0.5 line-clamp-2 text-xs">{step.data.summary}</p>
                                                        {step.data.highlights && step.data.highlights.length > 0 && (
                                                            <div className="mt-2 flex flex-wrap gap-1">
                                                                {step.data.highlights.filter(Boolean).map((h, i) => (
                                                                    <Badge key={i} variant="secondary" className="text-[10px]">
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

                            <DialogFooter className="mt-6">
                                <Button variant="outline" onClick={() => setPreviewFeature(null)}>
                                    Close
                                </Button>
                                <Button asChild>
                                    <Link href={route("administrators.onboarding-features.edit", previewFeature.id)}>
                                        <Pencil className="mr-2 h-4 w-4" />
                                        Edit Feature
                                    </Link>
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </DialogContent>
            </Dialog>
        </AdminLayout>
    );
}
