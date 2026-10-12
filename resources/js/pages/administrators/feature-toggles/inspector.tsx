import { assign, details, impact, preview, removeAssignment, update } from "@/actions/App/Http/Controllers/AdministratorFeatureToggleController";
import { Alert, AlertDescription, AlertTitle } from "@/components/reui/alert";
import { Badge } from "@/components/reui/badge";
import { Frame, FrameDescription, FrameHeader, FramePanel, FrameTitle } from "@/components/reui/frame";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Skeleton } from "@/components/ui/skeleton";
import { Switch } from "@/components/ui/switch";
import { router, useForm } from "@inertiajs/react";
import axios from "axios";
import { Trash2 } from "lucide-react";
import { useEffect, useRef, useState } from "react";
import { toast } from "sonner";
import type { FeaturePageProps } from "./index";
import type { Assignment, FeatureImpact, FeatureUser, ManagedFeature } from "./types";
import { portalFeatureUrl } from "./types";
import { UserPicker } from "./user-picker";

type InspectorProps = Pick<FeaturePageProps, "roles" | "schools" | "groups"> & { feature: ManagedFeature };

// datetime-local speaks wall-clock time; the API stores UTC.
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
    const [previewUser, setPreviewUser] = useState<FeatureUser | null>(null);
    const [previewResult, setPreviewResult] = useState<{ current: boolean; managed: { active: boolean; reason: string } } | null>(null);
    const [previewLoading, setPreviewLoading] = useState(false);
    const [previewError, setPreviewError] = useState(false);
    const [targetUser, setTargetUser] = useState<FeatureUser | null>(null);
    const [impactResult, setImpactResult] = useState<FeatureImpact | null>(null);
    const [impactLoading, setImpactLoading] = useState(false);
    const [impactError, setImpactError] = useState<string | null>(null);
    const [reviewedDraft, setReviewedDraft] = useState<string | null>(null);
    const [reviewedTargets, setReviewedTargets] = useState<string | null>(null);
    const [saveMessage, setSaveMessage] = useState<string | null>(null);
    const draft = JSON.stringify(policy.data);
    const currentDraft = useRef(draft);
    currentDraft.current = draft;
    const adoption = feature.policy.mode === "legacy" && policy.data.mode === "enforced";
    const targetsVersion = JSON.stringify({ groups, assignments });
    const reviewCurrent = reviewedDraft === draft && reviewedTargets === targetsVersion;

    const openImpact = async () => {
        const requestedDraft = draft;
        setImpactLoading(true);
        setImpactError(null);
        setImpactResult(null);
        setReviewedDraft(null);
        setReviewedTargets(null);
        try {
            const response = await axios.post<FeatureImpact>(portalFeatureUrl(impact.url(feature.key)), policy.data, {
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
                setSaveMessage(adopt ? "Managed policy adopted." : "Feature policy saved.");
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
                setAssignments((await response.json()).assignments);
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
        setPreviewUser(user);
        setPreviewLoading(true);
        setPreviewError(false);
        setPreviewResult(null);
        try {
            const response = await fetch(portalFeatureUrl(preview.url(feature.key, { query: { user_id: user.id } })), {
                headers: { Accept: "application/json" },
            });
            if (!response.ok) throw new Error("Preview failed");
            setPreviewResult(await response.json());
        } catch {
            setPreviewError(true);
        } finally {
            setPreviewLoading(false);
        }
    };
    const targetsOptions =
        target.data.subject_type === "role"
            ? roles.filter((role) => feature.catalog.roles.includes(role.value)).map((role) => ({ value: role.value, label: role.label }))
            : target.data.subject_type === "school"
              ? schools.map((school) => ({ value: String(school.id), label: school.name }))
              : groups.map((group) => ({ value: String(group.id), label: group.name }));

    return (
        <div className="flex min-w-0 flex-col gap-6">
            <Frame spacing="default">
                <FramePanel>
                    <FrameHeader>
                        <div className="flex flex-wrap items-center gap-2">
                            <FrameTitle>{feature.name}</FrameTitle>
                            <Badge variant="outline">{feature.catalog.tier.replaceAll("_", " ")}</Badge>
                        </div>
                        <FrameDescription>{feature.summary}</FrameDescription>
                    </FrameHeader>
                    <div className="flex flex-col gap-4">
                        <div className="flex flex-col gap-2">
                            <p className="text-sm font-medium">Built-in destination</p>
                            <p className="text-muted-foreground text-xs">
                                {feature.catalog.public
                                    ? "Public enrollment · installation-wide"
                                    : feature.catalog.roles.map((value) => roles.find((role) => role.value === value)?.label ?? value).join(", ")}
                            </p>
                            <p className="text-muted-foreground text-xs">
                                {feature.catalog.default_enabled
                                    ? "Available by default for eligible roles."
                                    : "Requires targeted release, percentage rollout, or approved beta opt-in."}
                            </p>
                        </div>
                        {feature.policy.mode === "legacy" && (
                            <div className="border-border flex flex-col gap-2 border-l-2 pl-3">
                                <Badge variant="warning-light">Existing access preserved</Badge>
                                <p className="text-muted-foreground text-xs">
                                    Managed settings do not apply until you choose Managed policy. Review user access before adopting.
                                </p>
                            </div>
                        )}
                        <form
                            className="flex flex-col gap-4"
                            onSubmit={(event) => {
                                event.preventDefault();
                                if (adoption) void openImpact();
                                else savePolicy();
                            }}
                        >
                            <div className="flex flex-col gap-2">
                                <Label>Evaluation mode</Label>
                                <Select value={policy.data.mode} onValueChange={(value) => policy.setData("mode", value as "legacy" | "enforced")}>
                                    <SelectTrigger aria-label="Evaluation mode">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="legacy">Legacy compatibility</SelectItem>
                                        <SelectItem value="enforced">Managed policy</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="flex items-center justify-between gap-3">
                                <div className="flex flex-col gap-0.5">
                                    <Label htmlFor={`enabled-${feature.key}`}>Feature enabled</Label>
                                    <p className="text-muted-foreground text-xs">Disable without losing assignments.</p>
                                </div>
                                <Switch
                                    id={`enabled-${feature.key}`}
                                    checked={policy.data.enabled}
                                    onCheckedChange={(value) => policy.setData("enabled", value)}
                                />
                            </div>
                            <div className="flex flex-col gap-2">
                                <Label>Release strategy</Label>
                                <Select
                                    value={policy.data.strategy}
                                    onValueChange={(value) => policy.setData("strategy", value as typeof policy.data.strategy)}
                                >
                                    <SelectTrigger aria-label="Release strategy">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="defaults">Built-in role defaults</SelectItem>
                                        {!feature.catalog.public && (
                                            <>
                                                <SelectItem value="targeted">Targets only</SelectItem>
                                                <SelectItem value="percentage">Stable percentage</SelectItem>
                                            </>
                                        )}
                                    </SelectContent>
                                </Select>
                            </div>
                            {policy.data.strategy === "percentage" && (
                                <div className="flex flex-col gap-2">
                                    <Label htmlFor="rollout-percentage">Percentage of eligible users</Label>
                                    <Input
                                        id="rollout-percentage"
                                        type="number"
                                        min={0}
                                        max={100}
                                        value={policy.data.percentage}
                                        onChange={(event) => policy.setData("percentage", Number(event.target.value))}
                                    />
                                    <p className="text-muted-foreground text-xs">
                                        Same cohort across requests. Increase to add people; decrease to roll back.
                                    </p>
                                </div>
                            )}
                            {feature.catalog.opt_in && (
                                <div className="flex items-center justify-between gap-3">
                                    <Label htmlFor="opt-in">Allow beta opt-in</Label>
                                    <Switch
                                        id="opt-in"
                                        checked={policy.data.allow_opt_in}
                                        onCheckedChange={(value) => policy.setData("allow_opt_in", value)}
                                    />
                                </div>
                            )}
                            <div className="grid gap-3 sm:grid-cols-2">
                                <div className="flex flex-col gap-2">
                                    <Label htmlFor="activate-at">Release from</Label>
                                    <Input
                                        id="activate-at"
                                        type="datetime-local"
                                        value={toLocalInput(policy.data.activate_at)}
                                        onChange={(event) => policy.setData("activate_at", fromLocalInput(event.target.value))}
                                    />
                                    <p className="text-muted-foreground text-xs">Blank releases immediately.</p>
                                </div>
                                <div className="flex flex-col gap-2">
                                    <Label htmlFor="retire-at">Retire at</Label>
                                    <Input
                                        id="retire-at"
                                        type="datetime-local"
                                        value={toLocalInput(policy.data.retire_at)}
                                        onChange={(event) => policy.setData("retire_at", fromLocalInput(event.target.value))}
                                    />
                                    <p className="text-muted-foreground text-xs">Blank keeps it available.</p>
                                </div>
                            </div>
                            {(feature.policy.mode === "legacy" || policy.isDirty) && (
                                <Button
                                    type="button"
                                    variant="outline"
                                    disabled={impactLoading || policy.processing}
                                    onClick={() => void openImpact()}
                                >
                                    {impactLoading ? "Checking impact…" : "Review impact of managed policy"}
                                </Button>
                            )}
                            {impactResult && (
                                <div aria-live="polite" className="border-border flex flex-col gap-2 border-l-2 pl-3">
                                    <p className="text-sm font-medium">Managed-policy impact now</p>
                                    <p className="text-muted-foreground text-xs">
                                        Compared {impactResult.sampled} of {impactResult.eligible ?? "public"} eligible users against this draft.
                                    </p>
                                    <div className="flex flex-wrap gap-2">
                                        <Badge variant="success-light">{impactResult.gaining} gain access</Badge>
                                        <Badge variant="destructive-light">{impactResult.losing} lose access</Badge>
                                        <Badge variant="outline">{impactResult.unchanged} unchanged</Badge>
                                    </div>
                                    {impactResult.note && <p className="text-muted-foreground text-xs">{impactResult.note}</p>}
                                    <p className="text-muted-foreground text-xs">{impactResult.scope_note}</p>
                                    {typeof impactResult.public_current === "boolean" && (
                                        <p className="text-sm">
                                            Public enrollment: {impactResult.public_current ? "available" : "unavailable"} now;{" "}
                                            {impactResult.public_proposed ? "available" : "unavailable"} with managed policy.
                                        </p>
                                    )}
                                    <p className="text-muted-foreground text-xs">
                                        Future schedules are evaluated at current time. Membership changes can change these counts.
                                    </p>
                                    {!reviewCurrent && (
                                        <Alert variant="warning">
                                            <AlertTitle>Draft changed</AlertTitle>
                                            <AlertDescription>Review again before adopting.</AlertDescription>
                                        </Alert>
                                    )}
                                    <div className="flex flex-wrap gap-2">
                                        <Button
                                            type="button"
                                            size="sm"
                                            disabled={!reviewCurrent || policy.processing}
                                            onClick={() => savePolicy(true)}
                                        >
                                            Adopt managed policy
                                        </Button>
                                        <Button type="button" size="sm" variant="ghost" onClick={() => setImpactResult(null)}>
                                            Cancel
                                        </Button>
                                    </div>
                                    {impactResult.losing > 0 && (
                                        <p className="text-muted-foreground text-xs">
                                            Legacy evaluations stay untouched, so switching back restores those users.
                                        </p>
                                    )}
                                </div>
                            )}
                            {impactError && (
                                <Alert variant="destructive">
                                    <AlertTitle>Impact unavailable</AlertTitle>
                                    <AlertDescription>{impactError}</AlertDescription>
                                </Alert>
                            )}
                            {Object.values(policy.errors).map((message) => (
                                <p role="alert" key={message} className="text-destructive text-xs">
                                    {message}
                                </p>
                            ))}
                            {saveMessage && (
                                <Alert variant="success" role="status">
                                    <AlertDescription>{saveMessage}</AlertDescription>
                                </Alert>
                            )}
                            <Button type="submit" disabled={policy.processing || impactLoading}>
                                {policy.processing ? "Saving…" : adoption ? "Review before adopting" : "Save policy"}
                            </Button>
                        </form>
                    </div>
                </FramePanel>
            </Frame>
            {!feature.catalog.public && (
                <Frame spacing="default">
                    <FramePanel>
                        <FrameHeader>
                            <FrameTitle>Audience targets</FrameTitle>
                            <FrameDescription>Exclusions win; permissions stay separate</FrameDescription>
                        </FrameHeader>
                        <div className="flex flex-col gap-4">
                            {loading ? (
                                <Skeleton className="h-20 w-full" />
                            ) : loadError ? (
                                <div role="alert" className="flex items-center gap-2 text-sm">
                                    Targets unavailable.
                                    <Button variant="outline" size="sm" onClick={() => setRefresh((value) => value + 1)}>
                                        Retry
                                    </Button>
                                </div>
                            ) : assignments.length === 0 ? (
                                <p className="text-muted-foreground text-sm">No explicit targets. Add one below.</p>
                            ) : (
                                <div className="flex flex-col gap-3">
                                    {assignments.map((assignment) => (
                                        <div key={assignment.id} className="flex items-center justify-between gap-2">
                                            <div className="flex min-w-0 flex-col gap-0.5">
                                                <span className="truncate text-sm font-medium">{assignment.label}</span>
                                                <span className="text-muted-foreground text-xs">
                                                    {assignment.subject_type} ·{" "}
                                                    {assignment.source === "self_service" ? "User preference" : "Administrator"}
                                                </span>
                                            </div>
                                            <div className="flex items-center gap-1">
                                                <Badge variant={assignment.effect === "allow" ? "success-light" : "destructive-light"}>
                                                    {assignment.effect === "allow" ? "Allow" : "Deny"}
                                                </Badge>
                                                <Button
                                                    size="icon-sm"
                                                    variant="ghost"
                                                    aria-label={`Remove target ${assignment.label}`}
                                                    onClick={() =>
                                                        router.delete(
                                                            portalFeatureUrl(
                                                                removeAssignment.url({ featureKey: feature.key, assignment: assignment.id }),
                                                            ),
                                                            {
                                                                preserveScroll: true,
                                                                onSuccess: () => {
                                                                    setRefresh((value) => value + 1);
                                                                    toast.success("Target removed");
                                                                },
                                                            },
                                                        )
                                                    }
                                                >
                                                    <Trash2 />
                                                </Button>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                            <form
                                className="flex flex-col gap-3"
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    target.post(portalFeatureUrl(assign.url(feature.key)), {
                                        preserveScroll: true,
                                        onSuccess: () => {
                                            setRefresh((value) => value + 1);
                                            toast.success("Target saved");
                                        },
                                    });
                                }}
                            >
                                <div className="grid grid-cols-2 gap-2">
                                    <Select
                                        value={target.data.subject_type}
                                        onValueChange={(value) => {
                                            target.setData({ ...target.data, subject_type: value, subject_key: "" });
                                            setTargetUser(null);
                                        }}
                                    >
                                        <SelectTrigger aria-label="Target type">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="role">Role</SelectItem>
                                            <SelectItem value="school">School</SelectItem>
                                            <SelectItem value="group">Beta group</SelectItem>
                                            <SelectItem value="user">User</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <Select value={target.data.effect} onValueChange={(value) => target.setData("effect", value)}>
                                        <SelectTrigger aria-label="Target effect">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="allow">Allow</SelectItem>
                                            <SelectItem value="deny">Deny</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                {target.data.subject_type === "user" ? (
                                    <>
                                        <UserPicker
                                            onSelect={(user) => {
                                                setTargetUser(user);
                                                target.setData("subject_key", String(user.id));
                                            }}
                                        />
                                        {targetUser && <p className="text-sm">Selected: {targetUser.name}</p>}
                                    </>
                                ) : (
                                    <Select value={target.data.subject_key} onValueChange={(value) => target.setData("subject_key", value)}>
                                        <SelectTrigger aria-label="Target subject">
                                            <SelectValue placeholder="Select target" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {targetsOptions.map((option) => (
                                                <SelectItem key={option.value} value={option.value}>
                                                    {option.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                )}
                                {Object.values(target.errors).map((message) => (
                                    <p role="alert" key={message} className="text-destructive text-xs">
                                        {message}
                                    </p>
                                ))}
                                <Button type="submit" variant="outline" size="sm" disabled={target.processing || !target.data.subject_key}>
                                    {target.processing ? "Saving…" : "Save target"}
                                </Button>
                            </form>
                        </div>
                    </FramePanel>
                </Frame>
            )}
            <Frame spacing="default">
                <FramePanel>
                    <FrameHeader>
                        <FrameTitle>Access preview</FrameTitle>
                        <FrameDescription>Compare current access with saved managed policy</FrameDescription>
                    </FrameHeader>
                    <div className="flex flex-col gap-3">
                        <UserPicker onSelect={runPreview} label="Preview user" />
                        {previewLoading ? (
                            <Skeleton className="h-16 w-full" />
                        ) : previewError && previewUser ? (
                            <div role="alert" className="flex items-center gap-2 text-sm">
                                Preview failed.
                                <Button variant="outline" size="sm" onClick={() => runPreview(previewUser)}>
                                    Retry
                                </Button>
                            </div>
                        ) : previewResult ? (
                            <div aria-live="polite" className="flex flex-col gap-2">
                                <p className="text-sm font-medium">{previewUser?.name}</p>
                                <div className="flex flex-wrap gap-2">
                                    <Badge variant={previewResult.current ? "success-light" : "destructive-light"}>
                                        Current: {previewResult.current ? "available" : "unavailable"}
                                    </Badge>
                                    <Badge variant={previewResult.managed.active ? "success-light" : "destructive-light"}>
                                        Managed: {previewResult.managed.active ? "available" : "unavailable"}
                                    </Badge>
                                </div>
                                <p className="text-muted-foreground text-xs">{previewResult.managed.reason}</p>
                            </div>
                        ) : (
                            <p className="text-muted-foreground text-xs">
                                Choose a user to explain their access. Preview does not grant permissions.
                            </p>
                        )}
                    </div>
                </FramePanel>
            </Frame>
        </div>
    );
}
