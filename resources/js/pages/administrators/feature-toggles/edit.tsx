import AdminLayout from "@/components/administrators/admin-layout";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Separator } from "@/components/ui/separator";
import { cn } from "@/lib/utils";
import type { User } from "@/types/user";
import { Head, Link } from "@inertiajs/react";
import { ArrowLeft, Globe, Sparkles } from "lucide-react";

declare let route: any;

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
    is_active: boolean;
    pennant_class: string | null;
    pennant_type: "class" | "string";
    pennant_global_state: boolean;
    pennant_user_overrides_count: number;
}

interface Props {
    auth: { user: User };
    feature: FeatureToggle | null;
    audiences: Record<string, string>;
}

/**
 * Normalize step data to ensure it has the expected structure.
 * Handles both old format (flat object) and new format (type + data wrapper)
 */
function normalizeStep(step: unknown): OnboardingStep {
    if (!step || typeof step !== "object") {
        return { type: "step", data: { title: "", summary: "" } };
    }

    const stepObj = step as Record<string, unknown>;

    // Check if it's already in the correct format (has type and data)
    if (stepObj.type === "step" && stepObj.data && typeof stepObj.data === "object") {
        const stepData = stepObj.data as Record<string, unknown>;
        return {
            type: "step",
            data: {
                title: String(stepData.title || ""),
                summary: String(stepData.summary || ""),
                badge: String(stepData.badge || ""),
                accent: String(stepData.accent || ""),
                icon: String(stepData.icon || ""),
                image: String(stepData.image || ""),
                highlights: Array.isArray(stepData.highlights) ? stepData.highlights.map((h) => String(h || "")) : [],
                stats: Array.isArray(stepData.stats)
                    ? stepData.stats.map((s: unknown) => {
                          const stat = s as Record<string, unknown>;
                          return {
                              label: String(stat?.label || ""),
                              value: String(stat?.value || ""),
                          };
                      })
                    : [],
            },
        };
    }

    // Old format: flat object with title, summary, etc. directly on step
    return {
        type: "step",
        data: {
            title: String(stepObj.title || ""),
            summary: String(stepObj.summary || ""),
            badge: String(stepObj.badge || ""),
            accent: String(stepObj.accent || ""),
            icon: String(stepObj.icon || ""),
            image: String(stepObj.image || ""),
            highlights: Array.isArray(stepObj.highlights) ? stepObj.highlights.map((h) => String(h || "")) : [],
            stats: Array.isArray(stepObj.stats)
                ? stepObj.stats.map((s: unknown) => {
                      const stat = s as Record<string, unknown>;
                      return {
                          label: String(stat?.label || ""),
                          value: String(stat?.value || ""),
                      };
                  })
                : [],
        },
    };
}

/**
 * Normalize all steps from the feature
 */
function normalizeSteps(steps: unknown): OnboardingStep[] {
    if (!Array.isArray(steps) || steps.length === 0) {
        return [];
    }
    return steps.map(normalizeStep);
}

const audienceConfig = {
    student: {
        label: "Students",
        bg: "bg-blue-500/8 text-blue-700 dark:text-blue-400",
    },
    faculty: {
        label: "Faculty",
        bg: "bg-violet-500/8 text-violet-700 dark:text-violet-400",
    },
    all: {
        label: "Everyone",
        bg: "bg-amber-500/8 text-amber-700 dark:text-amber-400",
    },
};

export default function FeatureTogglePreview({ auth, feature, audiences }: Props) {
    if (!feature) {
        return (
            <AdminLayout user={auth.user} title="Feature Toggle Preview">
                <Head title="Administrators • Feature Toggle Preview" />
                <div className="flex min-h-[calc(100vh-65px)] flex-col items-center justify-center">
                    <p className="text-muted-foreground">Feature toggle not found.</p>
                    <Button variant="outline" className="mt-4 gap-2" asChild>
                        <Link href={route("administrators.feature-toggles.index")}>
                            <ArrowLeft className="h-4 w-4" />
                            Back to Toggles
                        </Link>
                    </Button>
                </div>
            </AdminLayout>
        );
    }

    const steps = normalizeSteps(feature.steps);
    const isClassBased = feature.pennant_type === "class";

    return (
        <AdminLayout user={auth.user} title="Feature Toggle Preview">
            <Head title={`Administrators • ${feature.name}`} />

            <div className="bg-muted/30 min-h-[calc(100vh-65px)]">
                {/* Header */}
                <div className="bg-background/95 sticky top-0 z-20 border-b backdrop-blur-md">
                    <div className="flex items-center justify-between px-6 py-4">
                        <div className="flex items-center gap-4">
                            <Button variant="ghost" size="icon" asChild>
                                <Link href={route("administrators.feature-toggles.index")}>
                                    <ArrowLeft className="h-4 w-4" />
                                </Link>
                            </Button>
                            <div>
                                <h1 className="text-lg font-semibold">{feature.name}</h1>
                                <p className="text-muted-foreground text-sm">
                                    Read-only preview of feature toggle metadata
                                </p>
                            </div>
                        </div>

                        <Button variant="outline" className="gap-2" asChild>
                            <Link href={route("administrators.feature-toggles.index")}>
                                <ArrowLeft className="h-4 w-4" />
                                Back to Toggles
                            </Link>
                        </Button>
                    </div>
                </div>

                <div className="mx-auto max-w-5xl space-y-6 p-6">
                    {/* Feature Overview */}
                    <Card>
                        <CardHeader>
                            <div className="flex items-center gap-3">
                                <div className="bg-primary/10 text-primary flex h-10 w-10 items-center justify-center rounded-lg">
                                    <Sparkles className="h-5 w-5" />
                                </div>
                                <div>
                                    <CardTitle>Feature Overview</CardTitle>
                                    <CardDescription>Basic information about this feature toggle</CardDescription>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent className="space-y-6">
                            <div className="grid gap-6 md:grid-cols-2">
                                <div className="space-y-2">
                                    <p className="text-muted-foreground text-[10px] font-medium uppercase">Feature Key</p>
                                    <code className="bg-muted rounded px-2 py-1 font-mono text-xs">{feature.key}</code>
                                </div>

                                <div className="space-y-2">
                                    <p className="text-muted-foreground text-[10px] font-medium uppercase">Display Name</p>
                                    <p className="text-sm font-medium">{feature.name}</p>
                                </div>

                                <div className="space-y-2">
                                    <p className="text-muted-foreground text-[10px] font-medium uppercase">Target Audience</p>
                                    <Badge variant="outline" className={cn("text-[10px]", audienceConfig[feature.audience].bg)}>
                                        {audiences[feature.audience] || feature.audience}
                                    </Badge>
                                </div>

                                <div className="space-y-2">
                                    <p className="text-muted-foreground text-[10px] font-medium uppercase">Badge Label</p>
                                    <p className="text-sm">{feature.badge || "—"}</p>
                                </div>
                            </div>

                            <div className="space-y-2">
                                <p className="text-muted-foreground text-[10px] font-medium uppercase">Summary</p>
                                <p className="text-sm">{feature.summary || "—"}</p>
                            </div>

                            <div className="space-y-2">
                                <p className="text-muted-foreground text-[10px] font-medium uppercase">Accent Color Class</p>
                                <p className="text-sm">{feature.accent || "—"}</p>
                            </div>

                            <div className="grid gap-6 md:grid-cols-2">
                                <div className="space-y-2">
                                    <p className="text-muted-foreground text-[10px] font-medium uppercase">Status</p>
                                    <Badge className={feature.is_active ? "bg-emerald-500/10 text-emerald-600" : ""} variant={feature.is_active ? "default" : "secondary"}>
                                        {feature.is_active ? "Active" : "Inactive"}
                                    </Badge>
                                </div>

                                {isClassBased && feature.pennant_class && (
                                    <div className="space-y-2">
                                        <p className="text-muted-foreground text-[10px] font-medium uppercase">Pennant Class</p>
                                        <code className="bg-muted block truncate rounded px-2 py-1 font-mono text-[11px]">{feature.pennant_class}</code>
                                    </div>
                                )}

                                {isClassBased && (
                                    <div className="space-y-2">
                                        <p className="text-muted-foreground text-[10px] font-medium uppercase">Global State</p>
                                        <span className={cn("flex items-center gap-1 text-sm", feature.pennant_global_state ? "text-emerald-600" : "text-muted-foreground")}>
                                            <Globe className="h-3.5 w-3.5" />
                                            {feature.pennant_global_state ? "Force-activated for everyone" : "Resolved per-scope via class"}
                                        </span>
                                    </div>
                                )}

                                <div className="space-y-2">
                                    <p className="text-muted-foreground text-[10px] font-medium uppercase">User Overrides</p>
                                    <p className="text-sm">{feature.pennant_user_overrides_count} user{feature.pennant_user_overrides_count !== 1 ? "s" : ""} with overrides</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Call To Action */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Call to Action</CardTitle>
                            <CardDescription>Optional button to direct users after viewing the onboarding</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="grid gap-6 md:grid-cols-2">
                                <div className="space-y-2">
                                    <p className="text-muted-foreground text-[10px] font-medium uppercase">CTA Label</p>
                                    <p className="text-sm">{feature.cta_label || "—"}</p>
                                </div>
                                <div className="space-y-2">
                                    <p className="text-muted-foreground text-[10px] font-medium uppercase">CTA URL</p>
                                    {feature.cta_url ? (
                                        <a href={feature.cta_url} className="text-primary text-sm hover:underline">
                                            {feature.cta_url}
                                        </a>
                                    ) : (
                                        <p className="text-sm">—</p>
                                    )}
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Onboarding Steps */}
                    {steps.length > 0 && (
                        <Card>
                            <CardHeader>
                                <CardTitle>Onboarding Steps</CardTitle>
                                <CardDescription>Steps with titles, descriptions, and visual elements</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div className="space-y-3">
                                    {steps.map((step, index) => (
                                        <div key={index} className="bg-muted/30 rounded-lg border px-4 py-3">
                                            <div className="flex items-center gap-3">
                                                <Badge variant="outline" className="shrink-0">
                                                    Step {index + 1}
                                                </Badge>
                                                <span className="truncate text-sm font-medium">{step.data.title || "Untitled step"}</span>
                                            </div>
                                            <div className="mt-3 space-y-4">
                                                <div className="grid gap-4 md:grid-cols-2">
                                                    <div className="space-y-1">
                                                        <p className="text-muted-foreground text-[10px] font-medium uppercase">Title</p>
                                                        <p className="text-sm">{step.data.title || "—"}</p>
                                                    </div>
                                                    <div className="space-y-1">
                                                        <p className="text-muted-foreground text-[10px] font-medium uppercase">Icon</p>
                                                        <p className="text-sm">{step.data.icon || "—"}</p>
                                                    </div>
                                                </div>

                                                <div className="space-y-1">
                                                    <p className="text-muted-foreground text-[10px] font-medium uppercase">Summary</p>
                                                    <p className="text-sm">{step.data.summary || "—"}</p>
                                                </div>

                                                <div className="grid gap-4 md:grid-cols-2">
                                                    <div className="space-y-1">
                                                        <p className="text-muted-foreground text-[10px] font-medium uppercase">Badge</p>
                                                        <p className="text-sm">{step.data.badge || "—"}</p>
                                                    </div>
                                                    <div className="space-y-1">
                                                        <p className="text-muted-foreground text-[10px] font-medium uppercase">Accent Class</p>
                                                        <p className="text-sm">{step.data.accent || "—"}</p>
                                                    </div>
                                                </div>

                                                {step.data.image && (
                                                    <div className="space-y-1">
                                                        <p className="text-muted-foreground text-[10px] font-medium uppercase">Preview Image</p>
                                                        <div className="bg-muted relative mt-1 h-32 w-full overflow-hidden rounded-lg border">
                                                            <img
                                                                src={step.data.image}
                                                                alt="Step preview"
                                                                className="h-full w-full object-cover"
                                                                onError={(e) => {
                                                                    (e.target as HTMLImageElement).style.display = "none";
                                                                }}
                                                            />
                                                        </div>
                                                    </div>
                                                )}

                                                {step.data.highlights && step.data.highlights.length > 0 && (
                                                    <div className="space-y-1">
                                                        <p className="text-muted-foreground text-[10px] font-medium uppercase">Highlights</p>
                                                        <div className="flex flex-wrap gap-1">
                                                            {step.data.highlights.filter(Boolean).map((h, i) => (
                                                                <Badge key={i} variant="secondary" className="text-[9px]">
                                                                    {h}
                                                                </Badge>
                                                            ))}
                                                        </div>
                                                    </div>
                                                )}

                                                {step.data.stats && step.data.stats.length > 0 && (
                                                    <div className="space-y-1">
                                                        <p className="text-muted-foreground text-[10px] font-medium uppercase">Stats</p>
                                                        <div className="grid gap-2 md:grid-cols-2">
                                                            {step.data.stats.map((stat, i) => (
                                                                <div key={i} className="grid grid-cols-2 gap-2 text-sm">
                                                                    <span className="text-muted-foreground">{stat.label || "—"}</span>
                                                                    <span>{stat.value || "—"}</span>
                                                                </div>
                                                            ))}
                                                        </div>
                                                    </div>
                                                )}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </CardContent>
                        </Card>
                    )}

                    {/* Footer */}
                    <div className="flex items-center justify-start pt-4 pb-8">
                        <Button variant="outline" className="gap-2" asChild>
                            <Link href={route("administrators.feature-toggles.index")}>
                                <ArrowLeft className="h-4 w-4" />
                                Back to Toggles
                            </Link>
                        </Button>
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
