import { preview, saveGroup, toggle } from "@/actions/App/Http/Controllers/AdministratorFeatureToggleController";
import AdminLayout from "@/components/administrators/admin-layout";
import { Badge } from "@/components/reui/badge";
import { Frame, FrameDescription, FrameHeader, FramePanel, FrameTitle } from "@/components/reui/frame";
import { Button } from "@/components/ui/button";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Switch } from "@/components/ui/switch";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Textarea } from "@/components/ui/textarea";
import { Head, router, useForm } from "@inertiajs/react";
import {
    Activity,
    AlertCircle,
    CheckCircle2,
    Clock,
    HelpCircle,
    History,
    Percent,
    Plus,
    Search,
    Shield,
    ShieldAlert,
    ShieldCheck,
    SlidersHorizontal,
    UserCheck,
    Users,
    X,
} from "lucide-react";
import { useMemo, useRef, useState } from "react";
import { toast } from "sonner";
import { FeatureInspector } from "./inspector";
import type { AuditEntry, BetaGroup, FeatureUser, ManagedFeature, ManagedFeatureWithPolicy } from "./types";
import { fallbackPolicy, portalFeatureUrl } from "./types";
import { UserPicker } from "./user-picker";

export interface FeaturePageProps {
    features: ManagedFeature[];
    roles: { value: string; label: string }[];
    schools: { id: number; name: string }[];
    groups: BetaGroup[];
    audit: AuditEntry[];
}

export function releaseStatus(feature: ManagedFeatureWithPolicy): {
    label: string;
    variant: "warning-light" | "destructive-light" | "info-light" | "success-light";
    detail: string;
} {
    if (feature.release_status === "uninitialized" || feature.policy.revision === 0) {
        return { label: "Uninitialized", variant: "warning-light", detail: "Run migrations" };
    }

    const policy = feature.policy;
    if (policy.mode === "legacy") return { label: "Legacy Preserved", variant: "warning-light", detail: "Legacy mode" };
    if (!policy.enabled) return { label: "Disabled", variant: "destructive-light", detail: "Turned off" };
    if (feature.release_status === "scheduled") return { label: "Scheduled", variant: "info-light", detail: "Scheduled release" };
    if (feature.release_status === "retired") return { label: "Retired", variant: "destructive-light", detail: "Retired feature" };
    if (policy.strategy === "percentage") return { label: `${policy.percentage}% Rollout`, variant: "success-light", detail: "Gradual rollout" };
    if (policy.strategy === "targeted") return { label: "Targets Only", variant: "info-light", detail: "Restricted targets" };
    if (policy.release_audience === "stable") return { label: "Stable release", variant: "success-light", detail: "Released to eligible users" };
    if (policy.release_audience === "beta") return { label: "Beta release", variant: "info-light", detail: "Enrolled beta participants" };
    if (policy.release_audience === "selected") return { label: "Private beta", variant: "info-light", detail: "Selected beta participants" };
    return feature.catalog.default_enabled
        ? { label: "Active", variant: "success-light", detail: "Role defaults" }
        : { label: "Opt-In / Targeted", variant: "info-light", detail: "Targets only" };
}

export default function FeatureTogglesIndex(props: FeaturePageProps) {
    const { roles, schools, groups, audit } = props;
    const features = useMemo<ManagedFeatureWithPolicy[]>(
        () =>
            props.features.map((feature) => ({
                ...feature,
                policy: feature.policy ?? fallbackPolicy(feature),
            })),
        [props.features],
    );

    // Active main navigation tab
    const [activeTab, setActiveTab] = useState<"catalog" | "groups" | "simulator" | "audit" | "guide">("catalog");

    // Catalog filtering state
    const [search, setSearch] = useState("");
    const [selectedCategory, setSelectedCategory] = useState("all");
    const [selectedStatus, setSelectedStatus] = useState("all");
    const [selectedTier, setSelectedTier] = useState("all");
    const [selectedKey, setSelectedKey] = useState(features[0]?.key ?? "");

    // Beta groups dialog state
    const [groupDialog, setGroupDialog] = useState(false);
    const [members, setMembers] = useState<FeatureUser[]>([]);
    const groupForm = useForm({
        id: null as number | null,
        name: "",
        description: "",
        school_id: null as number | null,
        user_ids: [] as number[],
    });

    // Global Simulator State
    const [simUser, setSimUser] = useState<FeatureUser | null>(null);
    const [simFeatureKey, setSimFeatureKey] = useState(features[0]?.key ?? "");
    const [simResult, setSimResult] = useState<{ current: boolean; managed: { active: boolean; reason: string }; mode: string } | null>(null);
    const [simLoading, setSimLoading] = useState(false);
    const simulatorRequest = useRef(0);

    // Audit search
    const [auditSearch, setAuditSearch] = useState("");

    // Quick toggle tracking
    const [togglingKeys, setTogglingKeys] = useState<Record<string, boolean>>({});

    const selectedFeature = features.find((f) => f.key === selectedKey) ?? features[0];

    // Compute distinct categories
    const categories = useMemo(() => {
        const set = new Set<string>();
        features.forEach((f) => {
            if (f.category) set.add(f.category);
        });
        return Array.from(set).sort();
    }, [features]);

    // KPI Metrics
    const metrics = useMemo(() => {
        const total = features.length;
        const active = features.filter((f) => f.release_status === "released").length;
        const rollouts = features.filter((f) => f.policy.strategy === "percentage" && f.release_status === "released").length;
        const legacy = features.filter((f) => f.policy.mode === "legacy").length;
        const totalTargets = features.reduce((acc, f) => acc + (f.assignment_count || 0), 0);
        return { total, active, rollouts, legacy, totalTargets };
    }, [features]);

    // Filter features
    const visibleFeatures = useMemo(() => {
        return features.filter((f) => {
            // Category filter
            if (selectedCategory !== "all" && f.category.toLowerCase() !== selectedCategory.toLowerCase()) {
                return false;
            }
            // Tier filter
            if (selectedTier !== "all" && f.catalog.tier !== selectedTier) {
                return false;
            }
            // Status filter
            if (selectedStatus === "active" && f.release_status !== "released") return false;
            if (selectedStatus === "disabled" && f.policy.enabled) return false;
            if (selectedStatus === "rollout" && f.policy.strategy !== "percentage") return false;
            if (selectedStatus === "legacy" && f.policy.mode !== "legacy") return false;
            if (selectedStatus === "scheduled" && f.release_status !== "scheduled") return false;
            if (selectedStatus === "beta" && !["beta", "selected"].includes(f.policy.release_audience ?? "")) return false;
            if (selectedStatus === "stable" && f.policy.release_audience !== "stable") return false;

            // Search query
            const query = search.trim().toLowerCase();
            if (!query) return true;
            return (
                f.name.toLowerCase().includes(query) ||
                f.key.toLowerCase().includes(query) ||
                f.category.toLowerCase().includes(query) ||
                (f.summary && f.summary.toLowerCase().includes(query))
            );
        });
    }, [features, search, selectedCategory, selectedStatus, selectedTier]);

    // Reset filters
    const clearFilters = () => {
        setSearch("");
        setSelectedCategory("all");
        setSelectedStatus("all");
        setSelectedTier("all");
    };

    const hasActiveFilters = search.trim() !== "" || selectedCategory !== "all" || selectedStatus !== "all" || selectedTier !== "all";

    // Quick toggle feature directly from card
    const handleCardToggle = (feature: ManagedFeatureWithPolicy) => {
        setTogglingKeys((prev) => ({ ...prev, [feature.key]: true }));
        router.post(
            portalFeatureUrl(toggle.url(feature.key)),
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success(`${feature.name} ${feature.policy.enabled ? "disabled" : "enabled"}`);
                },
                onError: () => toast.error("Could not toggle feature"),
                onFinish: () => {
                    setTogglingKeys((prev) => ({ ...prev, [feature.key]: false }));
                },
            },
        );
    };

    // Open Beta Group dialog
    const openGroup = (group?: BetaGroup) => {
        groupForm.setData({
            id: group?.id ?? null,
            name: group?.name ?? "",
            description: group?.description ?? "",
            school_id: group?.school_id ?? null,
            user_ids: group?.members.map((u) => u.id) ?? [],
        });
        groupForm.clearErrors();
        setMembers(group?.members ?? []);
        setGroupDialog(true);
    };

    // Global Simulator preview call
    const runGlobalSimulator = async (user: FeatureUser, key: string) => {
        const requestId = ++simulatorRequest.current;
        setSimUser(user);
        setSimLoading(true);
        setSimResult(null);
        try {
            const res = await fetch(portalFeatureUrl(preview.url(key, { query: { user_id: user.id } })), {
                headers: { Accept: "application/json" },
            });
            if (!res.ok) throw new Error("Evaluation failed");
            const result = await res.json();
            if (requestId === simulatorRequest.current) setSimResult(result);
        } catch {
            if (requestId === simulatorRequest.current) toast.error("Evaluation failed");
        } finally {
            if (requestId === simulatorRequest.current) setSimLoading(false);
        }
    };

    // Filter audit logs
    const filteredAudit = useMemo(() => {
        if (!auditSearch.trim()) return audit;
        const q = auditSearch.toLowerCase();
        return audit.filter(
            (entry) =>
                entry.action.toLowerCase().includes(q) ||
                (entry.feature_key && entry.feature_key.toLowerCase().includes(q)) ||
                (entry.actor && entry.actor.toLowerCase().includes(q)),
        );
    }, [audit, auditSearch]);

    return (
        <AdminLayout title="Feature access">
            <Head title="Feature Flags & Access Control" />

            <div className="flex flex-col gap-6">
                {/* Page Header */}
                <header className="flex flex-wrap items-start justify-between gap-4">
                    <div className="flex flex-col gap-1.5">
                        <div className="flex items-center gap-2.5">
                            <h1 className="text-foreground text-2xl font-bold tracking-tight">Feature Flags & Access Control</h1>
                            <Badge variant="outline" className="text-xs">
                                Local Control
                            </Badge>
                        </div>
                        <p className="text-muted-foreground max-w-2xl text-xs leading-relaxed">
                            Safely control which capabilities are visible across the institution. Manage gradual rollouts, test with beta groups, and
                            define audience override rules without modifying core permissions.
                        </p>
                    </div>

                    <div className="flex items-center gap-2">
                        <Button variant="outline" size="sm" className="h-8 text-xs" onClick={() => setActiveTab("guide")}>
                            <HelpCircle className="text-muted-foreground mr-1 size-3.5" />
                            How Access Works
                        </Button>
                        <Button size="sm" className="h-8 text-xs" onClick={() => openGroup()}>
                            <Users className="mr-1 size-3.5" />
                            New Beta Group
                        </Button>
                    </div>
                </header>

                {/* KPI Metrics Summary Bar */}
                <div className="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-5">
                    <button
                        type="button"
                        onClick={() => {
                            clearFilters();
                            setActiveTab("catalog");
                        }}
                        className="border-border bg-card hover:border-primary/40 hover:bg-muted/30 flex flex-col gap-1 rounded-xl border p-3 text-left transition-colors"
                    >
                        <span className="text-muted-foreground flex items-center justify-between text-[11px] font-medium">
                            Total Features
                            <SlidersHorizontal className="text-muted-foreground/60 size-3.5" />
                        </span>
                        <span className="text-foreground text-xl font-bold">{metrics.total}</span>
                        <span className="text-muted-foreground text-[10px]">In active registry</span>
                    </button>

                    <button
                        type="button"
                        onClick={() => {
                            clearFilters();
                            setSelectedStatus("active");
                            setActiveTab("catalog");
                        }}
                        className="border-border bg-card flex flex-col gap-1 rounded-xl border p-3 text-left transition-colors hover:border-emerald-500/40 hover:bg-emerald-500/5"
                    >
                        <span className="flex items-center justify-between text-[11px] font-medium text-emerald-700 dark:text-emerald-400">
                            Active Features
                            <CheckCircle2 className="size-3.5 text-emerald-500" />
                        </span>
                        <span className="text-xl font-bold text-emerald-600 dark:text-emerald-400">{metrics.active}</span>
                        <span className="text-muted-foreground text-[10px]">Live with managed policy</span>
                    </button>

                    <button
                        type="button"
                        onClick={() => {
                            clearFilters();
                            setSelectedStatus("rollout");
                            setActiveTab("catalog");
                        }}
                        className="border-border bg-card flex flex-col gap-1 rounded-xl border p-3 text-left transition-colors hover:border-sky-500/40 hover:bg-sky-500/5"
                    >
                        <span className="flex items-center justify-between text-[11px] font-medium text-sky-700 dark:text-sky-400">
                            Gradual Rollouts
                            <Percent className="size-3.5 text-sky-500" />
                        </span>
                        <span className="text-xl font-bold text-sky-600 dark:text-sky-400">{metrics.rollouts}</span>
                        <span className="text-muted-foreground text-[10px]">Stable percentage cohorts</span>
                    </button>

                    <button
                        type="button"
                        onClick={() => setActiveTab("groups")}
                        className="border-border bg-card flex flex-col gap-1 rounded-xl border p-3 text-left transition-colors hover:border-purple-500/40 hover:bg-purple-500/5"
                    >
                        <span className="flex items-center justify-between text-[11px] font-medium text-purple-700 dark:text-purple-400">
                            Beta Groups
                            <Users className="size-3.5 text-purple-500" />
                        </span>
                        <span className="text-xl font-bold text-purple-600 dark:text-purple-400">{groups.length}</span>
                        <span className="text-muted-foreground text-[10px]">Testing cohorts created</span>
                    </button>

                    <div className="border-border bg-card hidden flex-col gap-1 rounded-xl border p-3 text-left lg:flex">
                        <span className="text-muted-foreground flex items-center justify-between text-[11px] font-medium">
                            Audience Rules
                            <Shield className="text-muted-foreground/60 size-3.5" />
                        </span>
                        <span className="text-foreground text-xl font-bold">{metrics.totalTargets}</span>
                        <span className="text-muted-foreground text-[10px]">Target rules configured</span>
                    </div>
                </div>

                {/* Primary Navigation Tabs */}
                <Tabs value={activeTab} onValueChange={(val) => setActiveTab(val as typeof activeTab)} className="w-full">
                    <TabsList className="grid w-full max-w-3xl grid-cols-2 sm:grid-cols-5">
                        <TabsTrigger value="catalog" className="flex items-center gap-1.5 text-xs">
                            <SlidersHorizontal className="size-3.5" />
                            Feature Catalog
                        </TabsTrigger>
                        <TabsTrigger value="groups" className="flex items-center gap-1.5 text-xs">
                            <Users className="size-3.5" />
                            Beta Groups
                            {groups.length > 0 && (
                                <Badge variant="outline" className="ml-1 px-1.5 py-0 text-[10px]">
                                    {groups.length}
                                </Badge>
                            )}
                        </TabsTrigger>
                        <TabsTrigger value="simulator" className="flex items-center gap-1.5 text-xs">
                            <UserCheck className="size-3.5" />
                            User Access Checker
                        </TabsTrigger>
                        <TabsTrigger value="audit" className="flex items-center gap-1.5 text-xs">
                            <History className="size-3.5" />
                            Activity Log
                        </TabsTrigger>
                        <TabsTrigger value="guide" className="flex items-center gap-1.5 text-xs">
                            <HelpCircle className="size-3.5" />
                            Guide & Rules
                        </TabsTrigger>
                    </TabsList>

                    {/* ========================================================================= */}
                    {/* TAB 1: FEATURE CATALOG & MASTER-DETAIL WORKSPACE */}
                    {/* ========================================================================= */}
                    <TabsContent value="catalog" className="mt-5 flex flex-col gap-5">
                        {/* Filters Bar */}
                        <div className="border-border bg-card flex flex-wrap items-center justify-between gap-3 rounded-xl border p-3 shadow-2xs">
                            <div className="flex min-w-[280px] flex-1 flex-wrap items-center gap-2">
                                <div className="relative min-w-[200px] flex-1">
                                    <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2" />
                                    <Input
                                        placeholder="Search features by name, key, or audience…"
                                        value={search}
                                        onChange={(e) => setSearch(e.target.value)}
                                        className="h-9 pr-8 pl-8.5 text-xs"
                                    />
                                    {search && (
                                        <button
                                            type="button"
                                            onClick={() => setSearch("")}
                                            className="text-muted-foreground hover:text-foreground absolute top-1/2 right-2.5 -translate-y-1/2"
                                        >
                                            <X className="size-3.5" />
                                        </button>
                                    )}
                                </div>

                                {/* Category Select */}
                                <Select value={selectedCategory} onValueChange={setSelectedCategory}>
                                    <SelectTrigger size="sm" className="h-9 w-[140px] text-xs">
                                        <SelectValue placeholder="Category" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All Categories</SelectItem>
                                        {categories.map((cat) => (
                                            <SelectItem key={cat} value={cat}>
                                                {cat}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>

                                {/* Status Select */}
                                <Select value={selectedStatus} onValueChange={setSelectedStatus}>
                                    <SelectTrigger size="sm" className="h-9 w-[130px] text-xs">
                                        <SelectValue placeholder="Status" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All Statuses</SelectItem>
                                        <SelectItem value="active">Active Only</SelectItem>
                                        <SelectItem value="disabled">Disabled Only</SelectItem>
                                        <SelectItem value="rollout">In % Rollout</SelectItem>
                                        <SelectItem value="legacy">Legacy Mode</SelectItem>
                                        <SelectItem value="beta">Beta audiences</SelectItem>
                                        <SelectItem value="stable">Stable releases</SelectItem>
                                    </SelectContent>
                                </Select>

                                {/* Tier Select */}
                                <Select value={selectedTier} onValueChange={setSelectedTier}>
                                    <SelectTrigger size="sm" className="h-9 w-[120px] text-xs">
                                        <SelectValue placeholder="Tier" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All Tiers</SelectItem>
                                        <SelectItem value="built_in">Built-in</SelectItem>
                                        <SelectItem value="beta">Beta</SelectItem>
                                        <SelectItem value="restricted">Restricted</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="flex items-center gap-2">
                                {hasActiveFilters && (
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        className="text-muted-foreground hover:text-foreground h-8 text-xs"
                                        onClick={clearFilters}
                                    >
                                        Clear filters
                                    </Button>
                                )}
                                <span className="text-muted-foreground shrink-0 text-xs font-medium">
                                    {visibleFeatures.length} of {features.length} features
                                </span>
                            </div>
                        </div>

                        {/* Dual-Pane Layout */}
                        <div className="grid min-w-0 items-start gap-6 lg:grid-cols-[minmax(320px,420px)_minmax(0,1fr)]">
                            {/* Left Pane: Feature Directory List */}
                            <div className="flex max-h-[calc(100vh-280px)] flex-col gap-2.5 overflow-y-auto pr-1">
                                {visibleFeatures.length === 0 ? (
                                    <div className="border-border text-muted-foreground flex flex-col items-center justify-center rounded-xl border border-dashed py-12 text-center text-xs">
                                        <SlidersHorizontal className="text-muted-foreground/30 mb-2 size-8" />
                                        <p className="text-foreground font-medium">No matching features found</p>
                                        <p className="text-muted-foreground mt-0.5 text-[11px]">
                                            Try adjusting your search terms or filter criteria.
                                        </p>
                                        <Button variant="outline" size="sm" className="mt-3 text-xs" onClick={clearFilters}>
                                            Reset Filters
                                        </Button>
                                    </div>
                                ) : (
                                    visibleFeatures.map((feature) => {
                                        const isSelected = selectedKey === feature.key;
                                        const status = releaseStatus(feature);
                                        const isToggling = Boolean(togglingKeys[feature.key]);

                                        return (
                                            <div
                                                key={feature.key}
                                                role="button"
                                                tabIndex={0}
                                                onClick={() => setSelectedKey(feature.key)}
                                                onKeyDown={(e) => {
                                                    if (e.key === "Enter" || e.key === " ") {
                                                        setSelectedKey(feature.key);
                                                    }
                                                }}
                                                className={`group flex cursor-pointer flex-col gap-2 rounded-xl border p-3.5 text-left transition-colors ${
                                                    isSelected
                                                        ? "border-primary bg-primary/5 ring-primary shadow-xs ring-1"
                                                        : "border-border bg-card hover:border-border/80 hover:bg-muted/20"
                                                }`}
                                            >
                                                <div className="flex items-start justify-between gap-2">
                                                    <div className="flex min-w-0 flex-col">
                                                        <span className="text-foreground group-hover:text-primary truncate text-xs font-bold transition-colors">
                                                            {feature.name}
                                                        </span>
                                                        <span className="text-muted-foreground font-mono text-[11px]">{feature.key}</span>
                                                    </div>

                                                    {/* Card inline quick toggle switch */}
                                                    <div className="flex shrink-0 items-center gap-1.5" onClick={(e) => e.stopPropagation()}>
                                                        <Switch
                                                            id={`card-toggle-${feature.key}`}
                                                            checked={feature.policy.enabled}
                                                            disabled={isToggling || feature.policy.mode === "legacy"}
                                                            onCheckedChange={() => {
                                                                handleCardToggle(feature);
                                                            }}
                                                            aria-label={
                                                                feature.policy.mode === "legacy"
                                                                    ? `Adopt managed policy before toggling ${feature.name}`
                                                                    : `Toggle ${feature.name}`
                                                            }
                                                        />
                                                    </div>
                                                </div>

                                                {feature.summary && (
                                                    <p className="text-muted-foreground line-clamp-2 text-[11px] leading-relaxed">
                                                        {feature.summary}
                                                    </p>
                                                )}

                                                <div className="border-border/60 flex flex-wrap items-center justify-between gap-1.5 border-t pt-1">
                                                    <div className="flex flex-wrap items-center gap-1.5">
                                                        <Badge variant={status.variant} className="px-1.5 py-0 text-[10px]">
                                                            {status.label}
                                                        </Badge>
                                                        <Badge variant="outline" className="px-1.5 py-0 text-[10px] capitalize">
                                                            {feature.category}
                                                        </Badge>
                                                        {feature.policy.release_audience && (
                                                            <Badge variant="outline">
                                                                {feature.policy.release_audience === "selected"
                                                                    ? "Selected beta"
                                                                    : feature.policy.release_audience === "beta"
                                                                      ? "Beta"
                                                                      : "Stable"}
                                                            </Badge>
                                                        )}
                                                    </div>

                                                    {feature.assignment_count > 0 && (
                                                        <span className="text-muted-foreground flex items-center gap-1 text-[10px] font-medium">
                                                            <Users className="text-muted-foreground size-3" />
                                                            {feature.assignment_count} target{feature.assignment_count === 1 ? "" : "s"}
                                                        </span>
                                                    )}
                                                </div>
                                            </div>
                                        );
                                    })
                                )}
                            </div>

                            {/* Right Pane: Feature Inspector */}
                            <div className="min-w-0">
                                {selectedFeature ? (
                                    <FeatureInspector
                                        key={`${selectedFeature.key}:${selectedFeature.policy.revision}:${selectedFeature.assignment_count}`}
                                        feature={selectedFeature}
                                        roles={roles}
                                        schools={schools}
                                        groups={groups}
                                    />
                                ) : (
                                    <div className="border-border text-muted-foreground flex flex-col items-center justify-center rounded-xl border border-dashed py-16 text-center text-xs">
                                        <p>Select a feature from the directory to configure its release settings.</p>
                                    </div>
                                )}
                            </div>
                        </div>
                    </TabsContent>

                    {/* ========================================================================= */}
                    {/* TAB 2: BETA TESTING GROUPS WORKSPACE */}
                    {/* ========================================================================= */}
                    <TabsContent value="groups" className="mt-5 flex flex-col gap-5">
                        <Frame spacing="default">
                            <FramePanel>
                                <FrameHeader>
                                    <div className="flex flex-wrap items-center justify-between gap-3">
                                        <div>
                                            <FrameTitle>Beta Testing Cohorts</FrameTitle>
                                            <FrameDescription>
                                                Group users into test cohorts to preview and validate features before rolling out campus-wide.
                                            </FrameDescription>
                                        </div>
                                        <Button size="sm" onClick={() => openGroup()}>
                                            <Plus className="mr-1 size-3.5" />
                                            Create Beta Group
                                        </Button>
                                    </div>
                                </FrameHeader>

                                {groups.length === 0 ? (
                                    <div className="border-border text-muted-foreground flex flex-col items-center justify-center rounded-xl border border-dashed py-12 text-center text-xs">
                                        <Users className="text-muted-foreground/30 mb-2 size-10" />
                                        <h3 className="text-foreground text-sm font-semibold">No Beta Groups Configured</h3>
                                        <p className="text-muted-foreground mt-1 max-w-sm text-xs">
                                            Beta groups let you test experimental features with pilot teachers or student ambassadors without changing
                                            their institutional permissions.
                                        </p>
                                        <Button variant="outline" size="sm" className="mt-4 text-xs" onClick={() => openGroup()}>
                                            Create Your First Cohort
                                        </Button>
                                    </div>
                                ) : (
                                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                        {groups.map((group) => {
                                            const schoolName = schools.find((s) => s.id === group.school_id)?.name ?? "All Schools";
                                            return (
                                                <div
                                                    key={group.id}
                                                    className="border-border bg-card hover:border-border/80 flex flex-col justify-between rounded-xl border p-4 shadow-2xs transition-colors"
                                                >
                                                    <div className="flex flex-col gap-2">
                                                        <div className="flex items-start justify-between gap-2">
                                                            <h3 className="text-foreground text-sm font-bold">{group.name}</h3>
                                                            <Badge variant="outline" className="text-[10px]">
                                                                {schoolName}
                                                            </Badge>
                                                        </div>
                                                        {group.description && (
                                                            <p className="text-muted-foreground line-clamp-2 text-xs">{group.description}</p>
                                                        )}

                                                        <div className="text-muted-foreground flex items-center gap-1.5 pt-2 text-xs">
                                                            <Users className="text-primary size-3.5" />
                                                            <span className="text-foreground font-semibold">{group.members.length}</span> member
                                                            {group.members.length === 1 ? "" : "s"}
                                                        </div>

                                                        {/* Member Chips Preview */}
                                                        {group.members.length > 0 && (
                                                            <div className="flex max-h-20 flex-wrap gap-1 overflow-y-auto pt-1">
                                                                {group.members.slice(0, 5).map((m) => (
                                                                    <span
                                                                        key={m.id}
                                                                        className="bg-muted text-muted-foreground rounded px-1.5 py-0.5 text-[10px]"
                                                                    >
                                                                        {m.name}
                                                                    </span>
                                                                ))}
                                                                {group.members.length > 5 && (
                                                                    <span className="bg-muted text-muted-foreground rounded px-1.5 py-0.5 text-[10px]">
                                                                        +{group.members.length - 5} more
                                                                    </span>
                                                                )}
                                                            </div>
                                                        )}
                                                    </div>

                                                    <div className="border-border mt-4 flex justify-end border-t pt-3">
                                                        <Button size="sm" variant="outline" className="h-7 text-xs" onClick={() => openGroup(group)}>
                                                            Edit Group & Members
                                                        </Button>
                                                    </div>
                                                </div>
                                            );
                                        })}
                                    </div>
                                )}
                            </FramePanel>
                        </Frame>
                    </TabsContent>

                    {/* ========================================================================= */}
                    {/* TAB 3: GLOBAL ACCESS SIMULATOR */}
                    {/* ========================================================================= */}
                    <TabsContent value="simulator" className="mt-5 flex flex-col gap-5">
                        <Frame spacing="default">
                            <FramePanel>
                                <FrameHeader>
                                    <FrameTitle>Institution User Access Simulator</FrameTitle>
                                    <FrameDescription>
                                        Diagnose user access across features to see why a teacher or student can or cannot access specific tools.
                                    </FrameDescription>
                                </FrameHeader>

                                <div className="grid gap-6 md:grid-cols-2">
                                    <div className="flex flex-col gap-4">
                                        <div className="border-border bg-card flex flex-col gap-3 rounded-lg border p-4">
                                            <h3 className="text-foreground text-xs font-semibold">Step 1: Select a User</h3>
                                            <UserPicker
                                                label=""
                                                placeholder="Search user by name or email…"
                                                selectedUser={simUser}
                                                onSelect={(user) => {
                                                    setSimUser(user);
                                                    if (simFeatureKey) runGlobalSimulator(user, simFeatureKey);
                                                }}
                                                onClear={() => {
                                                    simulatorRequest.current++;
                                                    setSimLoading(false);
                                                    setSimUser(null);
                                                    setSimResult(null);
                                                }}
                                            />
                                        </div>

                                        <div className="border-border bg-card flex flex-col gap-3 rounded-lg border p-4">
                                            <h3 className="text-foreground text-xs font-semibold">Step 2: Select Feature</h3>
                                            <Select
                                                value={simFeatureKey}
                                                onValueChange={(key) => {
                                                    setSimFeatureKey(key);
                                                    if (simUser) runGlobalSimulator(simUser, key);
                                                }}
                                            >
                                                <SelectTrigger className="text-xs">
                                                    <SelectValue placeholder="Select feature…" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {features.map((f) => (
                                                        <SelectItem key={f.key} value={f.key}>
                                                            {f.name} ({f.category})
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        </div>
                                    </div>

                                    {/* Evaluation Verdict Panel */}
                                    <div className="flex flex-col">
                                        {simLoading ? (
                                            <div className="border-border bg-muted/20 flex h-48 items-center justify-center rounded-xl border p-6">
                                                <span className="text-muted-foreground animate-pulse text-xs">Evaluating access rules…</span>
                                            </div>
                                        ) : simResult && simUser ? (
                                            <div className="border-border bg-card flex flex-col gap-3 rounded-xl border p-5">
                                                <div className="flex items-center justify-between">
                                                    <div className="flex items-center gap-2.5">
                                                        {simResult.current ? (
                                                            <div className="flex size-9 items-center justify-center rounded-full bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                                                                <CheckCircle2 className="size-6" />
                                                            </div>
                                                        ) : (
                                                            <div className="flex size-9 items-center justify-center rounded-full bg-rose-500/10 text-rose-600 dark:text-rose-400">
                                                                <AlertCircle className="size-6" />
                                                            </div>
                                                        )}
                                                        <div>
                                                            <h4 className="text-foreground text-base font-bold">
                                                                {simResult.current ? "Available now" : "Unavailable now"}
                                                            </h4>
                                                            <p className="text-muted-foreground text-xs">
                                                                {simUser.name} ({simUser.email})
                                                            </p>
                                                        </div>
                                                    </div>

                                                    <Badge variant={simResult.current ? "success-light" : "destructive-light"}>
                                                        {simResult.current ? "Available" : "Unavailable"}
                                                    </Badge>
                                                </div>

                                                <div className="border-border bg-muted/30 mt-2 flex flex-col gap-1.5 rounded-lg border p-3.5 text-xs">
                                                    <span className="text-foreground font-semibold">Saved managed policy:</span>
                                                    <Badge variant={simResult.managed.active ? "success-light" : "destructive-light"}>
                                                        {simResult.managed.active ? "Would be available" : "Would be unavailable"}
                                                    </Badge>
                                                    <p className="text-muted-foreground leading-relaxed">{simResult.managed.reason}</p>
                                                    <p className="text-muted-foreground">
                                                        {simResult.mode === "legacy"
                                                            ? "Existing legacy access still applies until managed policy adoption."
                                                            : "Managed policy is active."}{" "}
                                                        Action permissions remain separate.
                                                    </p>
                                                </div>

                                                <div className="grid grid-cols-2 gap-2 pt-2 text-xs">
                                                    <div className="border-border/80 bg-muted/20 rounded-md border p-2">
                                                        <span className="text-muted-foreground block text-[11px]">User Role</span>
                                                        <span className="text-foreground font-semibold capitalize">{simUser.role || "None"}</span>
                                                    </div>
                                                    <div className="border-border/80 bg-muted/20 rounded-md border p-2">
                                                        <span className="text-muted-foreground block text-[11px]">School ID</span>
                                                        <span className="text-foreground font-semibold">
                                                            {simUser.school_id ? `School #${simUser.school_id}` : "Global"}
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        ) : (
                                            <div className="border-border text-muted-foreground flex h-full flex-col items-center justify-center rounded-xl border border-dashed p-6 py-12 text-center text-xs">
                                                <UserCheck className="text-muted-foreground/30 mb-2 size-10" />
                                                <p className="text-foreground font-semibold">Diagnostic Ready</p>
                                                <p className="text-muted-foreground mt-1 max-w-xs">
                                                    Select a user and feature on the left to simulate access resolution in real time.
                                                </p>
                                            </div>
                                        )}
                                    </div>
                                </div>
                            </FramePanel>
                        </Frame>
                    </TabsContent>

                    {/* ========================================================================= */}
                    {/* TAB 4: AUDIT & ACTIVITY HISTORY */}
                    {/* ========================================================================= */}
                    <TabsContent value="audit" className="mt-5 flex flex-col gap-5">
                        <Frame spacing="default">
                            <FramePanel>
                                <FrameHeader>
                                    <div className="flex flex-wrap items-center justify-between gap-3">
                                        <div>
                                            <FrameTitle>Recent Feature Changes</FrameTitle>
                                            <FrameDescription>
                                                Local audit log of all toggle updates, policy adoptions, and beta cohort modifications
                                            </FrameDescription>
                                        </div>
                                        <div className="relative w-64">
                                            <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2" />
                                            <Input
                                                placeholder="Filter activity…"
                                                value={auditSearch}
                                                onChange={(e) => setAuditSearch(e.target.value)}
                                                className="h-8 pl-8 text-xs"
                                            />
                                        </div>
                                    </div>
                                </FrameHeader>

                                {filteredAudit.length === 0 ? (
                                    <div className="border-border text-muted-foreground flex flex-col items-center justify-center rounded-xl border border-dashed py-12 text-center text-xs">
                                        <History className="text-muted-foreground/30 mb-2 size-8" />
                                        <p className="text-foreground font-medium">No recent feature changes recorded</p>
                                        <p className="text-muted-foreground mt-0.5 text-[11px]">
                                            Saved policy changes, target updates, and beta cohort actions will appear here.
                                        </p>
                                    </div>
                                ) : (
                                    <div className="divide-border flex flex-col divide-y">
                                        {filteredAudit.map((entry) => (
                                            <div key={entry.id} className="flex flex-wrap items-center justify-between gap-3 py-3 text-xs">
                                                <div className="flex min-w-0 items-center gap-2.5">
                                                    <div className="bg-muted text-muted-foreground flex size-7 shrink-0 items-center justify-center rounded-full">
                                                        <Activity className="size-3.5" />
                                                    </div>
                                                    <div className="flex min-w-0 flex-col">
                                                        <div className="flex flex-wrap items-center gap-1.5">
                                                            <span className="text-foreground font-semibold capitalize">
                                                                {entry.action.replaceAll("_", " ")}
                                                            </span>
                                                            <Badge variant="outline" className="px-1.5 py-0 font-mono text-[10px]">
                                                                {entry.feature_key ??
                                                                    (entry.action.startsWith("beta_access_") ? "Beta Access" : "Beta group")}
                                                            </Badge>
                                                        </div>
                                                        <span className="text-muted-foreground text-[11px]">
                                                            Performed by{" "}
                                                            <span className="text-foreground font-medium">{entry.actor ?? "System"}</span>
                                                        </span>
                                                    </div>
                                                </div>

                                                <span className="text-muted-foreground flex shrink-0 items-center gap-1 text-[11px]">
                                                    <Clock className="size-3" />
                                                    {entry.created_at}
                                                </span>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </FramePanel>
                        </Frame>
                    </TabsContent>

                    {/* ========================================================================= */}
                    {/* TAB 5: HOW ACCESS WORKS GUIDE */}
                    {/* ========================================================================= */}
                    <TabsContent value="guide" className="mt-5 flex flex-col gap-5">
                        <div className="grid gap-4 md:grid-cols-2">
                            <div className="border-border bg-card flex flex-col gap-2.5 rounded-xl border p-5">
                                <div className="text-foreground flex items-center gap-2 text-sm font-bold">
                                    <ShieldCheck className="size-4 text-emerald-500" />
                                    1. Feature Availability vs Permissions
                                </div>
                                <p className="text-muted-foreground text-xs leading-relaxed">
                                    Permissions determine <em>what an authenticated user is authorized to do</em> (e.g. Can an instructor submit
                                    grades?). Feature Toggles control <em>whether that tool or navigation link is switched on</em>. Turning a feature
                                    on never grants permissions the user lacks.
                                </p>
                            </div>

                            <div className="border-border bg-card flex flex-col gap-2.5 rounded-xl border p-5">
                                <div className="text-foreground flex items-center gap-2 text-sm font-bold">
                                    <Percent className="size-4 text-sky-500" />
                                    2. Gradual Rollout (% Rollout)
                                </div>
                                <p className="text-muted-foreground text-xs leading-relaxed">
                                    Percentage rollouts use deterministic hashing of each user ID. If you set a feature to 25%, the exact same 25% of
                                    users keep access on every request. Increasing to 50% retains the original 25% and adds 25% more. Decreasing
                                    safely rolls back.
                                </p>
                            </div>

                            <div className="border-border bg-card flex flex-col gap-2.5 rounded-xl border p-5">
                                <div className="text-foreground flex items-center gap-2 text-sm font-bold">
                                    <ShieldAlert className="size-4 text-rose-500" />
                                    3. Override Rule Priority (Deny Wins)
                                </div>
                                <p className="text-muted-foreground text-xs leading-relaxed">
                                    When evaluating whether a user can see a feature, rules are checked in this exact order:
                                </p>
                                <ol className="text-muted-foreground list-inside list-decimal space-y-1 text-xs">
                                    <li>
                                        <strong className="text-foreground">Explicit Deny Rule</strong> (Always blocks access immediately)
                                    </li>
                                    <li>
                                        <strong className="text-foreground">Explicit Allow Rule</strong> (Grants access to targeted school/role/user)
                                    </li>
                                    <li>
                                        <strong className="text-foreground">Release Strategy</strong> (Role defaults or gradual rollout %)
                                    </li>
                                </ol>
                            </div>

                            <div className="border-border bg-card flex flex-col gap-2.5 rounded-xl border p-5">
                                <div className="text-foreground flex items-center gap-2 text-sm font-bold">
                                    <Users className="size-4 text-purple-500" />
                                    4. Beta Cohorts
                                </div>
                                <p className="text-muted-foreground text-xs leading-relaxed">
                                    Beta groups are named collections of users (e.g., "Early Adopter Faculty" or "Student Ambassadors"). They allow
                                    cross-departmental testing cohorts without having to assign rules to every user individually.
                                </p>
                            </div>
                        </div>
                    </TabsContent>
                </Tabs>
            </div>

            {/* Modal: Create / Edit Beta Group */}
            <Dialog open={groupDialog} onOpenChange={setGroupDialog}>
                <DialogContent className="max-w-md">
                    <DialogHeader>
                        <DialogTitle>{groupForm.data.id ? "Edit Beta Group" : "Create Beta Group"}</DialogTitle>
                        <DialogDescription className="text-xs">
                            Beta groups allow targeting pilot users without altering their permanent system roles.
                        </DialogDescription>
                    </DialogHeader>

                    <form
                        onSubmit={(event) => {
                            event.preventDefault();
                            groupForm.post(portalFeatureUrl(saveGroup.url()), {
                                preserveScroll: true,
                                onSuccess: () => {
                                    setGroupDialog(false);
                                    toast.success("Beta group saved successfully");
                                },
                            });
                        }}
                        className="flex flex-col gap-4 pt-2"
                    >
                        <div className="flex flex-col gap-1.5">
                            <Label htmlFor="group-name" className="text-xs font-semibold">
                                Group Name
                            </Label>
                            <Input
                                id="group-name"
                                placeholder="e.g. Early Adopters, Pilot Instructors…"
                                className="text-xs"
                                value={groupForm.data.name}
                                onChange={(e) => groupForm.setData("name", e.target.value)}
                            />
                            {groupForm.errors.name && (
                                <p role="alert" className="text-destructive text-xs">
                                    {groupForm.errors.name}
                                </p>
                            )}
                        </div>

                        <div className="flex flex-col gap-1.5">
                            <Label htmlFor="group-description" className="text-xs font-semibold">
                                Description
                            </Label>
                            <Textarea
                                id="group-description"
                                placeholder="Purpose of this testing group…"
                                className="h-20 resize-none text-xs"
                                value={groupForm.data.description}
                                onChange={(e) => groupForm.setData("description", e.target.value)}
                            />
                        </div>

                        <div className="flex flex-col gap-1.5">
                            <Label className="text-xs font-semibold">School Scope</Label>
                            <Select
                                value={String(groupForm.data.school_id ?? "all")}
                                onValueChange={(val) => groupForm.setData("school_id", val === "all" ? null : Number(val))}
                            >
                                <SelectTrigger className="text-xs">
                                    <SelectValue placeholder="Scope to school…" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All Schools (Campus-wide)</SelectItem>
                                    {schools.map((school) => (
                                        <SelectItem key={school.id} value={String(school.id)}>
                                            {school.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="flex flex-col gap-2 pt-1">
                            <UserPicker
                                label="Add Cohort Members"
                                placeholder="Search user to add to this group…"
                                onSelect={(user) => {
                                    if (members.some((m) => m.id === user.id)) return;
                                    const next = [...members, user];
                                    setMembers(next);
                                    groupForm.setData(
                                        "user_ids",
                                        next.map((m) => m.id),
                                    );
                                }}
                            />

                            <div className="flex max-h-36 flex-col gap-1 overflow-y-auto">
                                {members.length === 0 ? (
                                    <span className="text-muted-foreground py-1 text-[11px] italic">
                                        No members added yet. Search above to add members.
                                    </span>
                                ) : (
                                    members.map((u) => (
                                        <div
                                            key={u.id}
                                            className="border-border bg-muted/30 flex items-center justify-between gap-2 rounded-md border px-2.5 py-1.5 text-xs"
                                        >
                                            <span className="text-foreground truncate font-medium">{u.name}</span>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon-xs"
                                                className="text-muted-foreground hover:text-destructive"
                                                onClick={() => {
                                                    const next = members.filter((m) => m.id !== u.id);
                                                    setMembers(next);
                                                    groupForm.setData(
                                                        "user_ids",
                                                        next.map((m) => m.id),
                                                    );
                                                }}
                                            >
                                                <X className="size-3" />
                                            </Button>
                                        </div>
                                    ))
                                )}
                            </div>

                            {groupForm.errors.user_ids && (
                                <p role="alert" className="text-destructive text-xs">
                                    {groupForm.errors.user_ids}
                                </p>
                            )}
                        </div>

                        <DialogFooter className="pt-2">
                            <Button variant="outline" type="button" onClick={() => setGroupDialog(false)}>
                                Cancel
                            </Button>
                            <Button type="submit" disabled={groupForm.processing}>
                                {groupForm.processing ? "Saving…" : "Save Beta Group"}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </AdminLayout>
    );
}
