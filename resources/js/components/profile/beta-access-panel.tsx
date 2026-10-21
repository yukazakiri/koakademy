import { updateBetaAccess, updateBetaFeaturePreference } from "@/actions/App/Http/Controllers/ProfileController";
import { Alert, AlertDescription } from "@/components/reui/alert";
import { Badge } from "@/components/reui/badge";
import { Frame, FrameDescription, FrameHeader, FramePanel, FrameTitle } from "@/components/reui/frame";
import { Label } from "@/components/ui/label";
import { Switch } from "@/components/ui/switch";
import { useForm } from "@inertiajs/react";
import { toast } from "sonner";

export interface BetaAccess {
    enrolled: boolean;
    joined_at: string | null;
    features: BetaFeature[];
}

interface BetaFeature {
    key: string;
    name: string;
    summary: string | null;
    audience: "beta" | "selected";
    enabled: boolean;
    available: boolean;
    reason: string;
}

function relativeUrl(url: string): string {
    const parsed = new URL(url, "http://localhost");
    return parsed.pathname + parsed.search;
}

function BetaFeaturePreference({ feature, enrolled }: { feature: BetaFeature; enrolled: boolean }) {
    const form = useForm({ feature_key: feature.key, enabled: feature.enabled });

    return (
        <div className="flex min-w-0 flex-col gap-2">
            <div className="flex items-start justify-between gap-4">
                <div className="flex min-w-0 flex-col gap-1">
                    <Label htmlFor={`beta-${feature.key}`}>{feature.name}</Label>
                    <p className="text-muted-foreground text-sm">{feature.summary}</p>
                    <div className="flex flex-wrap items-center gap-2">
                        <Badge variant={feature.available ? "success-light" : "warning-light"}>
                            {feature.available ? "Available now" : "Not available now"}
                        </Badge>
                        <span className="text-muted-foreground text-xs">{feature.reason}</span>
                    </div>
                </div>
                <Switch
                    id={`beta-${feature.key}`}
                    checked={feature.enabled}
                    disabled={!enrolled || form.processing}
                    onCheckedChange={(enabled) => {
                        form.transform(() => ({ feature_key: feature.key, enabled }));
                        form.put(relativeUrl(updateBetaFeaturePreference.url()), {
                            preserveScroll: true,
                            onSuccess: () => toast.success("Beta preference saved"),
                        });
                    }}
                />
            </div>
            {Object.values(form.errors).map((error) => (
                <p key={error} role="alert" className="text-destructive text-sm">
                    {error}
                </p>
            ))}
        </div>
    );
}

export function BetaAccessPanel({ beta }: { beta: BetaAccess }) {
    const form = useForm({ enabled: beta.enrolled });

    return (
        <Frame spacing="default">
            <FramePanel>
                <FrameHeader>
                    <FrameTitle>Beta Access</FrameTitle>
                    <FrameDescription>Try early releases without changing your permissions.</FrameDescription>
                </FrameHeader>
                <div className="flex flex-col gap-6">
                    <div className="flex items-start justify-between gap-4">
                        <div className="flex flex-col gap-2">
                            <Label htmlFor="join-beta-access">Join Beta Access</Label>
                            <p id="beta-consent" className="text-muted-foreground text-sm">
                                Receive beta features your administrator releases for your role. Features may change or be withdrawn. Leave at any
                                time; stable features remain available.
                            </p>
                            <p className="text-muted-foreground text-xs">
                                Existing legacy access is unchanged until your administrator adopts managed releases.
                            </p>
                            <Badge variant={beta.enrolled ? "success-light" : "info-light"}>
                                {beta.enrolled ? "Beta participant" : "Not enrolled"}
                            </Badge>
                        </div>
                        <Switch
                            id="join-beta-access"
                            aria-describedby="beta-consent"
                            checked={beta.enrolled}
                            disabled={form.processing}
                            onCheckedChange={(enabled) => {
                                form.transform(() => ({ enabled }));
                                form.put(relativeUrl(updateBetaAccess.url()), {
                                    preserveScroll: true,
                                    onSuccess: () => toast.success(enabled ? "Joined Beta Access" : "Left Beta Access"),
                                });
                            }}
                        />
                    </div>
                    {Object.values(form.errors).map((error) => (
                        <Alert key={error} variant="destructive">
                            <AlertDescription>{error}</AlertDescription>
                        </Alert>
                    ))}
                    {beta.features.length > 0 ? (
                        <div className="flex flex-col gap-6">
                            <p className="text-muted-foreground text-sm">
                                Individual switches express your preference. Selection rules, schedules, and administrator exclusions still apply.
                            </p>
                            {beta.features.map((feature) => (
                                <BetaFeaturePreference key={`${feature.key}:${feature.enabled}`} feature={feature} enrolled={beta.enrolled} />
                            ))}
                        </div>
                    ) : (
                        <p className="text-muted-foreground text-sm">
                            No beta features released for your role yet. Your participation applies to future beta releases.
                        </p>
                    )}
                </div>
            </FramePanel>
        </Frame>
    );
}
