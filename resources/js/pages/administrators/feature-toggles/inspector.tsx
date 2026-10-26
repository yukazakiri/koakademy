import {
    assign,
    details,
    impact,
    preview,
    purgeOverrides,
    removeAssignment,
    toggle,
    update,
} from "@/actions/App/Http/Controllers/AdministratorFeatureToggleController";
import { Alert, AlertDescription, AlertTitle } from "@/components/reui/alert";
import {
    AlertDialog,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from "@/components/reui/alert-dialog";
import { Badge } from "@/components/reui/badge";
import { Frame, FrameDescription, FrameHeader, FramePanel, FrameTitle } from "@/components/reui/frame";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Separator } from "@/components/ui/separator";
import { Skeleton } from "@/components/ui/skeleton";
import { Slider } from "@/components/ui/slider";
import { Switch } from "@/components/ui/switch";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { router, useForm } from "@inertiajs/react";
import axios from "axios";
import {
    Calendar,
    Check,
    CheckCircle2,
    Clock,
    Copy,
    Filter,
    Info,
    Percent,
    RotateCcw,
    School as SchoolIcon,
    Shield,
    Sliders,
    Sparkles,
    Trash2,
    UserCheck,
    Users,
    XCircle,
} from "lucide-react";
import { useEffect, useRef, useState } from "react";
import { toast } from "sonner";
import type { FeaturePageProps } from "./index";
import type { Assignment, FeatureImpact, FeatureUser, ManagedFeatureWithPolicy } from "./types";
import { portalFeatureUrl } from "./types";
import { UserPicker } from "./user-picker";

type InspectorProps = Pick<FeaturePageProps, "roles" | "schools" | "groups"> & {
    feature: ManagedFeatureWithPolicy;
    onFeatureUpdated?: () => void;
};

function toLocalInput(value: string | null): string {
    if (!value) return "";
    const parsed = new Date(value);
    if (Number.isNaN(parsed.getTime())) return "";
    return new Date(parsed.getTime() - parsed.getTimezoneOffset() * 60_000).toISOString().slice(0, 16);
}

function fromLocalInput(value: string): string | null {
    if (!value) return null;
    const parsed = new Date(value);
    return Number.isNaN(parsed.getTime()) ? null : parsed.toISOString();
}

export function FeatureInspector({ feature, roles, schools, groups }: InspectorProps) {
    const policy = useForm({ ...feature.policy });
    const target = useForm({ subject_type: "role", subject_key: "", effect: "allow" });
    const [assignments, setAssignments] = useState<Assignment[]>([]);
    const [loading, setLoading] = useState(true);
    const [loadError, setLoadError] = useState(false);
    const [refresh, setRefresh] = useState(0);

    // Target filters
    const [targetFilter, setTargetFilter] = useState<"all" | "role" | "school" | "group" | "user">("all");

    // Preview / Simulator
    const [previewUser, setPreviewUser] = useState<FeatureUser | null>(null);
    const [previewResult, setPreviewResult] = useState<{ current: boolean; managed: { active: boolean; reason: string }; mode: string } | null>(null);
    const [previewLoading, setPreviewLoading] = useState(false);
    const [previewError, setPreviewError] = useState(false);
    const previewRequest = useRef(0);

    // Impact & Adoption
    const [impactResult, setImpactResult] = useState<FeatureImpact | null>(null);
    const [impactLoading, setImpactLoading] = useState(false);
    const [impactError, setImpactError] = useState<string | null>(null);
    const [reviewedDraft, setReviewedDraft] = useState<string | null>(null);
    const [reviewedTargets, setReviewedTargets] = useState<string | null>(null);
    const [saveMessage, setSaveMessage] = useState<string | null>(null);
    const [copiedKey, setCopiedKey] = useState(false);

    // Quick toggle loading
    const [toggling, setToggling] = useState(false);
    const [clearDialog, setClearDialog] = useState(false);
    const [clearing, setClearing] = useState(false);

    const draft = JSON.stringify(policy.data);
    const currentDraft = useRef(draft);
    currentDraft.current = draft;
    const adoption = feature.policy.mode === "legacy" && policy.data.mode === "enforced";
    const targetsVersion = JSON.stringify({ groups, assignments });
    const reviewCurrent = reviewedDraft === draft && reviewedTargets === targetsVersion;

    const copyKey = () => {
        navigator.clipboard.writeText(feature.key);
        setCopiedKey(true);
        toast.success("Feature key copied to clipboard");
        setTimeout(() => setCopiedKey(false), 2000);
    };

    const handleQuickToggle = () => {
        setToggling(true);
        router.post(
            portalFeatureUrl(toggle.url(feature.key)),
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success(`Feature ${policy.data.enabled ? "disabled" : "enabled"}`);
                },
                onError: () => toast.error("Could not toggle feature"),
                onFinish: () => setToggling(false),
            },
        );
    };

    const handlePurgeOverrides = async () => {
        setClearing(true);
        try {
            await axios.post(portalFeatureUrl(purgeOverrides.url(feature.key)));
            setRefresh((value) => value + 1);
            setClearDialog(false);
            router.reload({ only: ["features", "audit"] });
            toast.success("User overrides cleared");
        } catch {
            toast.error("Failed to clear overrides");
        } finally {
            setClearing(false);
        }
    };

    const openImpact = async (adopt = false) => {
        const proposal = adopt ? { ...policy.data, mode: "enforced" as const } : policy.data;
        if (adopt) policy.setData("mode", "enforced");
        const requestedDraft = JSON.stringify(proposal);
        setImpactLoading(true);
        setImpactError(null);
        setImpactResult(null);
        setReviewedDraft(null);
        setReviewedTargets(null);
        try {
            const response = await axios.post<FeatureImpact>(portalFeatureUrl(impact.url(feature.key)), proposal, {
                headers: { Accept: "application/json" },
            });
            if (currentDraft.current !== requestedDraft) return;
            setImpactResult(response.data);
            setReviewedDraft(requestedDraft);
            setReviewedTargets(targetsVersion);
        } catch (error) {
            const message = axios.isAxiosError(error)
                ? Object.values(error.response?.data.errors ?? {})
                      .flat()
                      .join(" ")
                : "";
            setImpactError(message || "Could not calculate impact. Retry before adopting.");
        } finally {
            setImpactLoading(false);
        }
    };

    const savePolicy = (adopt = false) => {
        if (adopt && (!impactResult || !reviewCurrent)) return;
        setSaveMessage(null);
        policy.transform((data) => ({ ...data, mode: adopt ? "enforced" : data.mode }));
        policy.put(portalFeatureUrl(update.url(feature.key)), {
            preserveScroll: true,
            onSuccess: () => {
                setImpactResult(null);
                setReviewedDraft(null);
                const msg = adopt ? "Managed policy adopted successfully." : "Feature policy saved.";
                setSaveMessage(msg);
                toast.success(msg);
            },
            onFinish: () => policy.transform((data) => data),
        });
    };

    useEffect(() => {
        const controller = new AbortController();
        setLoading(true);
        setLoadError(false);
        fetch(portalFeatureUrl(details.url(feature.key)), { signal: controller.signal, headers: { Accept: "application/json" } })
            .then(async (response) => {
                if (!response.ok) throw new Error("Load failed");
                const data = await response.json();
                setAssignments(data.assignments || []);
            })
            .catch(() => {
                if (!controller.signal.aborted) setLoadError(true);
            })
            .finally(() => {
                if (!controller.signal.aborted) setLoading(false);
            });
        return () => controller.abort();
    }, [feature.key, refresh]);

    const runPreview = async (user: FeatureUser) => {
        const requestId = ++previewRequest.current;
        setPreviewUser(user);
        setPreviewLoading(true);
        setPreviewError(false);
        setPreviewResult(null);
        try {
            const response = await fetch(portalFeatureUrl(preview.url(feature.key, { query: { user_id: user.id } })), {
                headers: { Accept: "application/json" },
            });
            if (!response.ok) throw new Error("Preview failed");
            const result = await response.json();
            if (requestId === previewRequest.current) setPreviewResult(result);
        } catch {
            if (requestId === previewRequest.current) setPreviewError(true);
        } finally {
            if (requestId === previewRequest.current) setPreviewLoading(false);
        }
    };

    const targetsOptions =
        target.data.subject_type === "role"
            ? roles.filter((role) => feature.catalog.roles.includes(role.value)).map((role) => ({ value: role.value, label: role.label }))
            : target.data.subject_type === "school"
              ? schools.map((school) => ({ value: String(school.id), label: school.name }))
              : groups.map((group) => ({ value: String(group.id), label: group.name }));

    const filteredAssignments = assignments.filter((a) => {
        if (targetFilter === "all") return true;
        return a.subject_type === targetFilter;
    });

    const isLegacy = feature.policy.mode === "legacy";

    return (
        <div className="flex min-w-0 flex-col gap-5">
            {/* Main Feature Header Card */}
            <div className="border-border bg-card rounded-xl border p-5 shadow-xs">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="flex min-w-0 flex-1 flex-col gap-1.5">
                        <div className="flex flex-wrap items-center gap-2">
                            <h2 className="text-foreground text-xl font-bold tracking-tight">{feature.name}</h2>
                            <Badge variant="outline" className="text-xs capitalize">
                                {feature.category}
                            </Badge>
                            <Badge variant={feature.catalog.tier === "built_in" ? "outline" : "warning-light"} className="text-xs">
                                {feature.catalog.tier.replace("_", " ")}
                            </Badge>
                        </div>
                        <p className="text-muted-foreground text-xs leading-relaxed">{feature.summary}</p>
                        <div className="mt-1 flex flex-wrap items-center gap-2 text-xs">
                            <button
                                type="button"
                                onClick={copyKey}
                                className="bg-muted/60 text-muted-foreground hover:bg-muted hover:text-foreground inline-flex items-center gap-1 rounded px-2 py-0.5 font-mono text-[11px] transition-colors"
                                title="Click to copy feature key"
                            >
                                <code>{feature.key}</code>
                                {copiedKey ? <Check className="size-3 text-emerald-500" /> : <Copy className="size-3" />}
                            </button>
                            <span className="text-muted-foreground/60">•</span>
                            <span className="text-muted-foreground">
                                {feature.catalog.public
                                    ? "Available to all users campus-wide"
                                    : `Eligible roles: ${feature.catalog.roles.map((r) => roles.find((role) => role.value === r)?.label ?? r).join(", ")}`}
                            </span>
                        </div>
                    </div>

                    {/* Master Status Quick Toggle */}
                    <div className="border-border/80 bg-muted/30 flex shrink-0 items-center gap-3 rounded-lg border px-3 py-2">
                        <div className="flex flex-col text-right">
                            <span className="text-foreground text-xs font-semibold">
                                {isLegacy ? "Existing access" : feature.policy.enabled ? "Enabled" : "Disabled"}
                            </span>
                            <span className="text-muted-foreground text-[10px]">{isLegacy ? "Review before adopting" : "Saved master switch"}</span>
                        </div>
                        <Switch
                            id={`quick-toggle-${feature.key}`}
                            checked={feature.policy.enabled}
                            disabled={toggling || isLegacy || policy.isDirty}
                            onCheckedChange={handleQuickToggle}
                            aria-label={`Toggle ${feature.name}`}
                        />
                    </div>
                </div>

                {isLegacy && (
                    <div className="mt-4 flex items-center justify-between gap-3 rounded-lg border border-amber-500/20 bg-amber-500/5 p-3 text-xs text-amber-900 dark:text-amber-200">
                        <div className="flex items-start gap-2.5">
                            <Info className="mt-0.5 size-4 shrink-0 text-amber-600 dark:text-amber-400" />
                            <div>
                                <span className="font-semibold">Legacy Mode Active: </span>
                                Managed rollout settings will not take effect until adopted. Simulate impact below to adopt safely.
                            </div>
                        </div>
                        <Button
                            size="sm"
                            variant="outline"
                            className="h-7 shrink-0 border-amber-400/40 text-xs hover:bg-amber-500/10"
                            onClick={() => {
                                void openImpact(true);
                            }}
                        >
                            Review & Adopt
                        </Button>
                    </div>
                )}
            </div>

            {/* Feature Sub-Tabs: Policy, Audience Targets, Simulator */}
            <Tabs defaultValue="policy" className="w-full">
                <TabsList className="w-full overflow-x-auto">
                    <TabsTrigger value="policy" className="flex items-center gap-1.5 text-xs">
                        <Sliders className="size-3.5" />
                        Release
                    </TabsTrigger>
                    <TabsTrigger value="targets" className="flex items-center gap-1.5 text-xs">
                        <Users className="size-3.5" />
                        Targets
                        {assignments.length > 0 && (
                            <Badge variant="outline" className="ml-1 px-1.5 py-0 text-[10px]">
                                {assignments.length}
                            </Badge>
                        )}
                    </TabsTrigger>
                    <TabsTrigger value="simulator" className="flex items-center gap-1.5 text-xs">
                        <UserCheck className="size-3.5" />
                        Check access
                    </TabsTrigger>
                </TabsList>

                {/* TAB 1: RELEASE POLICY & SCHEDULE */}
                <TabsContent value="policy" className="mt-4 flex flex-col gap-5">
                    <Frame spacing="default">
                        <FramePanel>
                            <FrameHeader>
                                <FrameTitle>Release Configuration</FrameTitle>
                                <FrameDescription>Choose who receives this feature and schedule rollout windows</FrameDescription>
                            </FrameHeader>

                            <form
                                className="flex flex-col gap-5"
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    if (adoption) void openImpact();
                                    else savePolicy();
                                }}
                            >
                                {/* Strategy Selector */}
                                <div className="flex flex-col gap-2">
                                    <Label htmlFor="release-audience">Release audience</Label>
                                    <Select
                                        value={policy.data.release_audience ?? "existing"}
                                        onValueChange={(value) =>
                                            policy.setData(
                                                "release_audience",
                                                value === "existing" ? null : (value as "stable" | "beta" | "selected"),
                                            )
                                        }
                                    >
                                        <SelectTrigger id="release-audience">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {feature.policy.release_audience === null && (
                                                <SelectItem value="existing">Keep existing distribution</SelectItem>
                                            )}
                                            {feature.catalog.tier !== "restricted" && (
                                                <SelectItem value="stable">Stable: all eligible users</SelectItem>
                                            )}
                                            {feature.catalog.opt_in && <SelectItem value="beta">Beta: enrolled participants</SelectItem>}
                                            {feature.catalog.opt_in && <SelectItem value="selected">Private beta: selected participants</SelectItem>}
                                        </SelectContent>
                                    </Select>
                                    <p className="text-muted-foreground text-sm">
                                        {policy.data.release_audience === "beta"
                                            ? "Requires Join Beta Access in profile. New participants qualify automatically."
                                            : policy.data.release_audience === "selected"
                                              ? "Requires Beta Access enrollment and a matching administrator Allow rule."
                                              : policy.data.release_audience === "stable"
                                                ? "Includes standard users and beta participants. Old beta opt-outs do not hide stable releases."
                                                : "Preserves current defaults, explicit assignments, and per-feature opt-in."}
                                    </p>
                                </div>
                                <div className="flex flex-col gap-2">
                                    <Label className="text-xs font-semibold">Distribution within audience</Label>
                                    <div className="grid gap-2 sm:grid-cols-3">
                                        <label
                                            className={`flex cursor-pointer flex-col gap-1 rounded-lg border p-3 text-xs transition-colors ${
                                                policy.data.strategy === "defaults"
                                                    ? "border-primary bg-primary/5 text-foreground ring-primary ring-1"
                                                    : "border-border bg-card text-muted-foreground hover:border-border/80"
                                            }`}
                                        >
                                            <input
                                                type="radio"
                                                name="strategy"
                                                className="sr-only"
                                                checked={policy.data.strategy === "defaults"}
                                                onChange={() => policy.setData("strategy", "defaults")}
                                            />
                                            <div className="text-foreground flex items-center gap-1.5 font-semibold">
                                                <Shield className="text-primary size-3.5" />
                                                {policy.data.release_audience ? "Full audience" : "Role defaults"}
                                            </div>
                                            <p className="text-muted-foreground text-[11px] leading-normal">
                                                {policy.data.release_audience
                                                    ? "Release to everyone in selected audience."
                                                    : "Use catalog defaults; beta and restricted features stay off by default."}
                                            </p>
                                        </label>

                                        {!feature.catalog.public && (
                                            <>
                                                <label
                                                    className={`flex cursor-pointer flex-col gap-1 rounded-lg border p-3 text-xs transition-colors ${
                                                        policy.data.strategy === "targeted"
                                                            ? "border-primary bg-primary/5 text-foreground ring-primary ring-1"
                                                            : "border-border bg-card text-muted-foreground hover:border-border/80"
                                                    }`}
                                                >
                                                    <input
                                                        type="radio"
                                                        name="strategy"
                                                        className="sr-only"
                                                        checked={policy.data.strategy === "targeted"}
                                                        onChange={() => policy.setData("strategy", "targeted")}
                                                    />
                                                    <div className="text-foreground flex items-center gap-1.5 font-semibold">
                                                        <Users className="size-3.5 text-sky-500" />
                                                        Targeted Only
                                                    </div>
                                                    <p className="text-muted-foreground text-[11px] leading-normal">
                                                        Only users/groups explicitly listed below.
                                                    </p>
                                                </label>

                                                <label
                                                    className={`flex cursor-pointer flex-col gap-1 rounded-lg border p-3 text-xs transition-colors ${
                                                        policy.data.strategy === "percentage"
                                                            ? "border-primary bg-primary/5 text-foreground ring-primary ring-1"
                                                            : "border-border bg-card text-muted-foreground hover:border-border/80"
                                                    }`}
                                                >
                                                    <input
                                                        type="radio"
                                                        name="strategy"
                                                        className="sr-only"
                                                        checked={policy.data.strategy === "percentage"}
                                                        onChange={() => policy.setData("strategy", "percentage")}
                                                    />
                                                    <div className="text-foreground flex items-center gap-1.5 font-semibold">
                                                        <Percent className="size-3.5 text-emerald-500" />
                                                        Gradual Rollout
                                                    </div>
                                                    <p className="text-muted-foreground text-[11px] leading-normal">
                                                        Roll out progressively to X% of users.
                                                    </p>
                                                </label>
                                            </>
                                        )}
                                    </div>
                                </div>

                                {/* Percentage Rollout Slider */}
                                {policy.data.strategy === "percentage" && (
                                    <div className="border-border bg-muted/20 flex flex-col gap-3 rounded-lg border p-4">
                                        <div className="flex items-center justify-between">
                                            <Label htmlFor="rollout-slider" className="text-xs font-semibold">
                                                Rollout Percentage
                                            </Label>
                                            <span className="text-primary font-mono text-sm font-bold">{policy.data.percentage}%</span>
                                        </div>
                                        <Slider
                                            id="rollout-slider"
                                            min={0}
                                            max={100}
                                            step={1}
                                            value={[policy.data.percentage]}
                                            onValueChange={(val) => policy.setData("percentage", val[0] ?? 0)}
                                            className="w-full cursor-pointer py-1"
                                        />
                                        <div className="flex flex-wrap items-center justify-between gap-1.5 pt-1">
                                            {[10, 25, 50, 75, 100].map((preset) => (
                                                <Button
                                                    key={preset}
                                                    type="button"
                                                    variant={policy.data.percentage === preset ? "default" : "outline"}
                                                    size="xs"
                                                    className="h-6 px-2.5 text-[11px]"
                                                    onClick={() => policy.setData("percentage", preset)}
                                                >
                                                    {preset}%
                                                </Button>
                                            ))}
                                        </div>
                                        <p className="text-muted-foreground text-[11px] leading-relaxed">
                                            Percentage applies inside selected audience. Users are assigned deterministically by their user ID.
                                            Increasing the percentage safely adds new users while keeping existing users active.
                                        </p>
                                    </div>
                                )}

                                {/* Beta Opt-In Option */}
                                {feature.catalog.opt_in && policy.data.release_audience === null && (
                                    <div className="border-border flex items-center justify-between gap-3 rounded-lg border p-3">
                                        <div className="flex flex-col gap-0.5">
                                            <Label htmlFor="opt-in-switch" className="text-xs font-semibold">
                                                Allow Voluntary Beta Opt-In
                                            </Label>
                                            <p className="text-muted-foreground text-[11px]">
                                                Eligible users can choose to turn this beta feature on in their profile settings.
                                            </p>
                                        </div>
                                        <Switch
                                            id="opt-in-switch"
                                            checked={policy.data.allow_opt_in}
                                            onCheckedChange={(checked) => policy.setData("allow_opt_in", checked)}
                                        />
                                    </div>
                                )}

                                {/* Release & Retirement Schedule */}
                                <div className="border-border bg-muted/10 grid gap-3 rounded-lg border p-3.5 sm:grid-cols-2">
                                    <div className="flex flex-col gap-1.5">
                                        <Label htmlFor="activate-at" className="flex items-center gap-1.5 text-xs font-medium">
                                            <Calendar className="text-muted-foreground size-3.5" />
                                            Release Start Date
                                        </Label>
                                        <Input
                                            id="activate-at"
                                            type="datetime-local"
                                            className="text-xs"
                                            value={toLocalInput(policy.data.activate_at)}
                                            onChange={(event) => policy.setData("activate_at", fromLocalInput(event.target.value))}
                                        />
                                        <p className="text-muted-foreground text-[11px]">Blank releases immediately.</p>
                                    </div>
                                    <div className="flex flex-col gap-1.5">
                                        <Label htmlFor="retire-at" className="flex items-center gap-1.5 text-xs font-medium">
                                            <Clock className="text-muted-foreground size-3.5" />
                                            Retirement End Date
                                        </Label>
                                        <Input
                                            id="retire-at"
                                            type="datetime-local"
                                            className="text-xs"
                                            value={toLocalInput(policy.data.retire_at)}
                                            onChange={(event) => policy.setData("retire_at", fromLocalInput(event.target.value))}
                                        />
                                        <p className="text-muted-foreground text-[11px]">Blank keeps it active indefinitely.</p>
                                    </div>
                                </div>

                                {/* Evaluation Mode Selector */}
                                <div className="border-border bg-card flex items-center justify-between gap-3 rounded-lg border p-3">
                                    <div className="flex flex-col gap-0.5">
                                        <Label className="text-xs font-semibold">Policy Control Mode</Label>
                                        <p className="text-muted-foreground text-[11px]">
                                            {policy.data.mode === "enforced"
                                                ? "These settings apply when published under managed policy."
                                                : "Legacy Compatibility preserves original defaults until adopted."}
                                        </p>
                                    </div>
                                    <Select
                                        value={policy.data.mode}
                                        onValueChange={(value) => policy.setData("mode", value as "legacy" | "enforced")}
                                    >
                                        <SelectTrigger size="sm" className="w-44 text-xs">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="enforced">Managed Policy</SelectItem>
                                            <SelectItem value="legacy">Legacy Mode</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>

                                {/* Impact Simulation Box */}
                                {(feature.policy.mode === "legacy" || policy.isDirty) && (
                                    <div className="border-primary/20 bg-primary/5 flex flex-col gap-3 rounded-xl border p-4">
                                        <div className="flex items-center justify-between">
                                            <div className="flex items-center gap-2">
                                                <Sparkles className="text-primary size-4" />
                                                <h4 className="text-foreground text-xs font-semibold">Simulate Policy Impact</h4>
                                            </div>
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="outline"
                                                className="h-7 text-xs"
                                                disabled={impactLoading || policy.processing}
                                                onClick={() => void openImpact()}
                                            >
                                                {impactLoading ? "Calculating…" : "Check User Impact"}
                                            </Button>
                                        </div>

                                        {impactResult && (
                                            <div aria-live="polite" className="flex flex-col gap-3 pt-2">
                                                <div className="grid grid-cols-3 gap-2">
                                                    <div className="flex flex-col items-center rounded-lg border border-emerald-500/20 bg-emerald-500/10 p-2 text-center">
                                                        <span className="text-lg font-bold text-emerald-600 dark:text-emerald-400">
                                                            +{impactResult.gaining}
                                                        </span>
                                                        <span className="text-[10px] text-emerald-700 dark:text-emerald-300">Gain Access</span>
                                                    </div>
                                                    <div className="flex flex-col items-center rounded-lg border border-rose-500/20 bg-rose-500/10 p-2 text-center">
                                                        <span className="text-lg font-bold text-rose-600 dark:text-rose-400">
                                                            -{impactResult.losing}
                                                        </span>
                                                        <span className="text-[10px] text-rose-700 dark:text-rose-300">Lose Access</span>
                                                    </div>
                                                    <div className="border-border bg-card flex flex-col items-center rounded-lg border p-2 text-center">
                                                        <span className="text-foreground text-lg font-bold">{impactResult.unchanged}</span>
                                                        <span className="text-muted-foreground text-[10px]">Unchanged</span>
                                                    </div>
                                                </div>

                                                <p className="text-muted-foreground text-[11px]">
                                                    Sampled {impactResult.sampled} of {impactResult.eligible ?? "all"} eligible users.
                                                    {impactResult.note && ` ${impactResult.note}`}
                                                </p>

                                                {!reviewCurrent && (
                                                    <Alert variant="warning" className="py-2">
                                                        <AlertTitle className="text-xs font-semibold">Settings Changed</AlertTitle>
                                                        <AlertDescription className="text-xs">
                                                            Your draft has changed since the last check. Please recalculate before adopting.
                                                        </AlertDescription>
                                                    </Alert>
                                                )}

                                                <div className="flex items-center gap-2 pt-1">
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        disabled={!reviewCurrent || policy.processing}
                                                        onClick={() => savePolicy(true)}
                                                    >
                                                        Confirm & Adopt Policy
                                                    </Button>
                                                    <Button type="button" size="sm" variant="ghost" onClick={() => setImpactResult(null)}>
                                                        Dismiss
                                                    </Button>
                                                </div>
                                            </div>
                                        )}

                                        {impactError && (
                                            <Alert variant="destructive">
                                                <AlertTitle className="text-xs font-semibold">Simulation Error</AlertTitle>
                                                <AlertDescription className="text-xs">{impactError}</AlertDescription>
                                            </Alert>
                                        )}
                                    </div>
                                )}

                                {Object.values(policy.errors).map((message) => (
                                    <p role="alert" key={message} className="text-destructive text-xs">
                                        {message}
                                    </p>
                                ))}

                                {saveMessage && (
                                    <Alert variant="success" role="status">
                                        <AlertDescription className="text-xs">{saveMessage}</AlertDescription>
                                    </Alert>
                                )}

                                <div className="flex items-center justify-between pt-2">
                                    <p className="text-muted-foreground text-[11px]">Revision {feature.policy.revision}</p>
                                    <Button type="submit" disabled={policy.processing || impactLoading}>
                                        {policy.processing ? "Saving…" : adoption ? "Review & Adopt Policy" : "Save Policy Changes"}
                                    </Button>
                                </div>
                            </form>
                        </FramePanel>
                    </Frame>
                </TabsContent>

                {/* TAB 2: AUDIENCE TARGETS & RULES */}
                <TabsContent value="targets" className="mt-4 flex flex-col gap-5">
                    {!feature.catalog.public ? (
                        <Frame spacing="default">
                            <FramePanel>
                                <FrameHeader>
                                    <div className="flex items-center justify-between gap-3">
                                        <div>
                                            <FrameTitle>Audience Override Rules</FrameTitle>
                                            <FrameDescription>Explicit Deny rules always override Allow rules.</FrameDescription>
                                        </div>
                                        {assignments.some((a) => a.subject_type === "user") && (
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                className="text-destructive hover:bg-destructive/10 border-destructive/20 h-7 text-xs"
                                                onClick={() => setClearDialog(true)}
                                            >
                                                <RotateCcw className="mr-1 size-3" />
                                                Clear User Overrides
                                            </Button>
                                        )}
                                    </div>
                                </FrameHeader>

                                {/* Filter pills */}
                                {assignments.length > 0 && (
                                    <div className="flex flex-wrap items-center gap-1.5 pb-2">
                                        <span className="text-muted-foreground mr-1 flex items-center gap-1 text-xs">
                                            <Filter className="size-3" /> Filter:
                                        </span>
                                        {(["all", "role", "school", "group", "user"] as const).map((ft) => (
                                            <Button
                                                key={ft}
                                                type="button"
                                                size="xs"
                                                variant={targetFilter === ft ? "default" : "outline"}
                                                className="h-6 text-[11px] capitalize"
                                                onClick={() => setTargetFilter(ft)}
                                            >
                                                {ft === "all" ? `All (${assignments.length})` : ft}
                                            </Button>
                                        ))}
                                    </div>
                                )}

                                {/* Existing Rules List */}
                                <div className="flex flex-col gap-2.5">
                                    {loading ? (
                                        <Skeleton className="h-24 w-full" />
                                    ) : loadError ? (
                                        <div
                                            role="alert"
                                            className="border-destructive/20 bg-destructive/5 text-destructive flex items-center justify-between rounded-lg border p-3 text-xs"
                                        >
                                            <span>Failed to load targets.</span>
                                            <Button variant="outline" size="sm" className="h-6 text-xs" onClick={() => setRefresh((v) => v + 1)}>
                                                Retry
                                            </Button>
                                        </div>
                                    ) : filteredAssignments.length === 0 ? (
                                        <div className="border-border text-muted-foreground flex flex-col items-center justify-center rounded-lg border border-dashed py-8 text-center text-xs">
                                            <Users className="text-muted-foreground/40 mb-2 size-8" />
                                            <p className="text-foreground font-medium">No explicit audience overrides</p>
                                            <p className="text-muted-foreground mt-0.5 text-[11px]">
                                                All eligible users inherit the release strategy policy above.
                                            </p>
                                        </div>
                                    ) : (
                                        filteredAssignments.map((assignment) => (
                                            <div
                                                key={assignment.id}
                                                className="border-border bg-card hover:border-border/80 flex items-center justify-between gap-3 rounded-lg border p-3 transition-colors"
                                            >
                                                <div className="flex min-w-0 items-center gap-3">
                                                    <div className="bg-muted text-foreground flex size-8 shrink-0 items-center justify-center rounded-lg">
                                                        {assignment.subject_type === "role" && <Shield className="size-4" />}
                                                        {assignment.subject_type === "school" && <SchoolIcon className="size-4" />}
                                                        {assignment.subject_type === "group" && <Users className="size-4" />}
                                                        {assignment.subject_type === "user" && <UserCheck className="size-4" />}
                                                    </div>
                                                    <div className="flex min-w-0 flex-col">
                                                        <span className="text-foreground truncate text-xs font-semibold">{assignment.label}</span>
                                                        <span className="text-muted-foreground text-[11px] capitalize">
                                                            {assignment.subject_type} ·{" "}
                                                            {assignment.source === "self_service" ? "User Opt-In" : "Admin Rule"}
                                                        </span>
                                                    </div>
                                                </div>

                                                <div className="flex shrink-0 items-center gap-2">
                                                    <Badge
                                                        variant={assignment.effect === "allow" ? "success-light" : "destructive-light"}
                                                        className="text-xs"
                                                    >
                                                        {assignment.effect === "allow" ? "Allow Access" : "Deny Access"}
                                                    </Badge>
                                                    <Button
                                                        size="icon-xs"
                                                        variant="ghost"
                                                        className="text-muted-foreground hover:text-destructive"
                                                        aria-label={`Remove target ${assignment.label}`}
                                                        onClick={() =>
                                                            router.delete(
                                                                portalFeatureUrl(
                                                                    removeAssignment.url({
                                                                        featureKey: feature.key,
                                                                        assignment: assignment.id,
                                                                    }),
                                                                ),
                                                                {
                                                                    preserveScroll: true,
                                                                    onSuccess: () => {
                                                                        setRefresh((v) => v + 1);
                                                                        toast.success("Target removed");
                                                                    },
                                                                },
                                                            )
                                                        }
                                                    >
                                                        <Trash2 className="size-3.5" />
                                                    </Button>
                                                </div>
                                            </div>
                                        ))
                                    )}
                                </div>

                                <Separator className="my-4" />

                                {/* Add New Target Form */}
                                <form
                                    className="border-border bg-muted/20 flex flex-col gap-3 rounded-lg border p-4"
                                    onSubmit={(event) => {
                                        event.preventDefault();
                                        target.post(portalFeatureUrl(assign.url(feature.key)), {
                                            preserveScroll: true,
                                            onSuccess: () => {
                                                setRefresh((v) => v + 1);
                                                target.reset();
                                                toast.success("Audience override added");
                                            },
                                        });
                                    }}
                                >
                                    <h4 className="text-foreground text-xs font-semibold">Add Audience Override Rule</h4>

                                    <div className="grid grid-cols-2 gap-2">
                                        <div className="flex flex-col gap-1">
                                            <Label className="text-[11px]">Target Type</Label>
                                            <Select
                                                value={target.data.subject_type}
                                                onValueChange={(value) => {
                                                    target.setData({ ...target.data, subject_type: value, subject_key: "" });
                                                }}
                                            >
                                                <SelectTrigger size="sm" className="text-xs">
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="role">Specific Role</SelectItem>
                                                    <SelectItem value="school">Specific School</SelectItem>
                                                    <SelectItem value="group">Beta Cohort (Group)</SelectItem>
                                                    <SelectItem value="user">Individual User</SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </div>

                                        <div className="flex flex-col gap-1">
                                            <Label className="text-[11px]">Rule Effect</Label>
                                            <Select
                                                value={target.data.effect}
                                                onValueChange={(value) => target.setData("effect", value as "allow" | "deny")}
                                            >
                                                <SelectTrigger size="sm" className="text-xs">
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="allow">Allow (Grant Access)</SelectItem>
                                                    <SelectItem value="deny">Deny (Block Access)</SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </div>
                                    </div>

                                    {target.data.subject_type === "user" ? (
                                        <UserPicker label="Select User" onSelect={(user) => target.setData("subject_key", String(user.id))} />
                                    ) : (
                                        <div className="flex flex-col gap-1">
                                            <Label className="text-[11px]">Select Target</Label>
                                            <Select value={target.data.subject_key} onValueChange={(value) => target.setData("subject_key", value)}>
                                                <SelectTrigger size="sm" className="text-xs">
                                                    <SelectValue placeholder="Choose target…" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {targetsOptions.map((option) => (
                                                        <SelectItem key={option.value} value={option.value}>
                                                            {option.label}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        </div>
                                    )}

                                    {Object.values(target.errors).map((message) => (
                                        <p role="alert" key={message} className="text-destructive text-xs">
                                            {message}
                                        </p>
                                    ))}

                                    <Button type="submit" size="sm" className="self-end" disabled={target.processing || !target.data.subject_key}>
                                        {target.processing ? "Adding…" : "Add Override Rule"}
                                    </Button>
                                </form>
                            </FramePanel>
                        </Frame>
                    ) : (
                        <div className="border-border bg-card text-muted-foreground rounded-lg border p-6 text-center text-xs">
                            This feature is public campus-wide and does not use audience targets.
                        </div>
                    )}
                </TabsContent>

                {/* TAB 3: USER ACCESS SIMULATOR / DIAGNOSTIC */}
                <TabsContent value="simulator" className="mt-4 flex flex-col gap-4">
                    <Frame spacing="default">
                        <FramePanel>
                            <FrameHeader>
                                <FrameTitle>User Access Simulator</FrameTitle>
                                <FrameDescription>Inspect why a specific user can or cannot access {feature.name}</FrameDescription>
                            </FrameHeader>

                            <div className="flex flex-col gap-4">
                                <UserPicker
                                    label="Search User to Test"
                                    placeholder="Type user's name or email to evaluate access…"
                                    selectedUser={previewUser}
                                    onSelect={runPreview}
                                    onClear={() => {
                                        previewRequest.current++;
                                        setPreviewLoading(false);
                                        setPreviewError(false);
                                        setPreviewUser(null);
                                        setPreviewResult(null);
                                    }}
                                />

                                {previewLoading ? (
                                    <Skeleton className="h-24 w-full rounded-xl" />
                                ) : previewError && previewUser ? (
                                    <div
                                        role="alert"
                                        className="border-destructive/20 bg-destructive/5 text-destructive flex items-center justify-between rounded-lg border p-3 text-xs"
                                    >
                                        <span>Evaluation failed. Please try again.</span>
                                        <Button variant="outline" size="sm" className="h-6 text-xs" onClick={() => runPreview(previewUser)}>
                                            Retry
                                        </Button>
                                    </div>
                                ) : previewResult && previewUser ? (
                                    <div aria-live="polite" className="border-border bg-card flex flex-col gap-3 rounded-xl border p-4">
                                        <div className="flex items-center justify-between gap-3">
                                            <div className="flex items-center gap-2">
                                                {previewResult.current ? (
                                                    <div className="flex size-7 items-center justify-center rounded-full bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                                                        <CheckCircle2 className="size-5" />
                                                    </div>
                                                ) : (
                                                    <div className="flex size-7 items-center justify-center rounded-full bg-rose-500/10 text-rose-600 dark:text-rose-400">
                                                        <XCircle className="size-5" />
                                                    </div>
                                                )}
                                                <div>
                                                    <h4 className="text-foreground text-sm font-bold">
                                                        {previewResult.current ? "Available now" : "Unavailable now"}
                                                    </h4>
                                                    <p className="text-muted-foreground text-[11px]">
                                                        For {previewUser.name} ({previewUser.email})
                                                    </p>
                                                </div>
                                            </div>

                                            <Badge variant={previewResult.current ? "success-light" : "destructive-light"}>
                                                {previewResult.current ? "Available" : "Unavailable"}
                                            </Badge>
                                        </div>

                                        <div className="border-border/80 bg-muted/30 flex flex-col gap-1 rounded-lg border p-3 text-xs">
                                            <span className="text-foreground font-semibold">Saved managed policy:</span>
                                            <Badge variant={previewResult.managed.active ? "success-light" : "destructive-light"}>
                                                {previewResult.managed.active ? "Would be available" : "Would be unavailable"}
                                            </Badge>
                                            <p className="text-muted-foreground leading-relaxed">{previewResult.managed.reason}</p>
                                            <p className="text-muted-foreground">
                                                Unsaved edits are not included. Action permissions remain separate.
                                            </p>
                                        </div>

                                        {isLegacy && (
                                            <div className="border-border text-muted-foreground flex items-center justify-between border-t pt-3 text-xs">
                                                <span>Current Legacy Status:</span>
                                                <Badge variant={previewResult.current ? "success-light" : "destructive-light"}>
                                                    {previewResult.current ? "Currently Active" : "Currently Inactive"}
                                                </Badge>
                                            </div>
                                        )}
                                    </div>
                                ) : (
                                    <div className="border-border text-muted-foreground flex flex-col items-center justify-center rounded-lg border border-dashed py-8 text-center text-xs">
                                        <UserCheck className="text-muted-foreground/30 mb-2 size-8" />
                                        <p className="text-foreground font-medium">Select a user above to test their access</p>
                                        <p className="text-muted-foreground mt-0.5 text-[11px]">
                                            This simulator evaluates all role defaults, rollout buckets, school rules, and beta groups without
                                            changing any user data.
                                        </p>
                                    </div>
                                )}
                            </div>
                        </FramePanel>
                    </Frame>
                </TabsContent>
            </Tabs>
            <AlertDialog
                open={clearDialog}
                onOpenChange={(open) => {
                    if (!clearing) setClearDialog(open);
                }}
            >
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Clear user overrides?</AlertDialogTitle>
                        <AlertDialogDescription>
                            Removes individual administrator assignments and beta preferences for this feature. Users inherit audience rules again.
                            Legacy results stay untouched.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel disabled={clearing}>Cancel</AlertDialogCancel>
                        <Button variant="destructive" disabled={clearing} onClick={() => void handlePurgeOverrides()}>
                            {clearing ? "Clearing…" : "Clear overrides"}
                        </Button>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </div>
    );
}
