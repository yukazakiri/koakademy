import AdminLayout from "@/components/administrators/admin-layout";
import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from "@/components/ui/accordion";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Separator } from "@/components/ui/separator";
import { Switch } from "@/components/ui/switch";
import { Textarea } from "@/components/ui/textarea";
import { cn } from "@/lib/utils";
import type { User } from "@/types/user";
import { Head, Link, useForm } from "@inertiajs/react";
import { AlertCircle, ArrowLeft, ChevronDown, ChevronUp, GripVertical, Image as ImageIcon, Plus, Save, Sparkles, Trash2 } from "lucide-react";
import { useState } from "react";
import { toast } from "sonner";

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

interface OnboardingFeature {
    id?: number;
    feature_key: string;
    name: string;
    audience: "student" | "faculty" | "all";
    summary: string;
    badge: string;
    accent: string;
    cta_label: string;
    cta_url: string;
    steps: OnboardingStep[];
    is_active: boolean;
}

interface Props {
    auth: { user: User };
    feature: OnboardingFeature | null;
    audiences: Record<string, string>;
}

const createDefaultStepData = (): OnboardingStepData => ({
    title: "",
    summary: "",
    badge: "",
    accent: "",
    icon: "sparkles",
    image: "",
    highlights: ["", "", ""],
    stats: [
        { label: "", value: "" },
        { label: "", value: "" },
    ],
});

const defaultStep: OnboardingStep = {
    type: "step",
    data: createDefaultStepData(),
};

/**
 * Normalize step data to ensure it has the expected structure.
 * Handles both old format (flat object) and new format (type + data wrapper)
 */
function normalizeStep(step: unknown): OnboardingStep {
    if (!step || typeof step !== "object") {
        return { ...defaultStep, data: createDefaultStepData() };
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
                icon: String(stepData.icon || "sparkles"),
                image: String(stepData.image || ""),
                highlights: Array.isArray(stepData.highlights) ? stepData.highlights.map((h) => String(h || "")) : ["", "", ""],
                stats: Array.isArray(stepData.stats)
                    ? stepData.stats.map((s: unknown) => {
                          const stat = s as Record<string, unknown>;
                          return {
                              label: String(stat?.label || ""),
                              value: String(stat?.value || ""),
                          };
                      })
                    : [
                          { label: "", value: "" },
                          { label: "", value: "" },
                      ],
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
            icon: String(stepObj.icon || "sparkles"),
            image: String(stepObj.image || ""),
            highlights: Array.isArray(stepObj.highlights) ? stepObj.highlights.map((h) => String(h || "")) : ["", "", ""],
            stats: Array.isArray(stepObj.stats)
                ? stepObj.stats.map((s: unknown) => {
                      const stat = s as Record<string, unknown>;
                      return {
                          label: String(stat?.label || ""),
                          value: String(stat?.value || ""),
                      };
                  })
                : [
                      { label: "", value: "" },
                      { label: "", value: "" },
                  ],
        },
    };
}

/**
 * Normalize all steps from the feature
 */
function normalizeSteps(steps: unknown): OnboardingStep[] {
    if (!Array.isArray(steps) || steps.length === 0) {
        return [{ ...defaultStep, data: createDefaultStepData() }];
    }
    return steps.map(normalizeStep);
}

export default function OnboardingFeatureEdit({ auth, feature, audiences }: Props) {
    const isEditing = !!feature?.id;
    const [expandedSteps, setExpandedSteps] = useState<string[]>(feature?.steps?.map((_, i) => `step-${i}`) || ["step-0"]);

    const { data, setData, post, put, processing, errors } = useForm<OnboardingFeature>({
        feature_key: feature?.feature_key || "",
        name: feature?.name || "",
        audience: feature?.audience || "all",
        summary: feature?.summary || "",
        badge: feature?.badge || "",
        accent: feature?.accent || "",
        cta_label: feature?.cta_label || "",
        cta_url: feature?.cta_url || "",
        steps: normalizeSteps(feature?.steps),
        is_active: feature?.is_active ?? true,
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (isEditing) {
            put(route("administrators.onboarding-features.update", feature.id), {
                onSuccess: () => toast.success("Feature updated successfully"),
                onError: () => toast.error("Failed to update feature"),
            });
        } else {
            post(route("administrators.onboarding-features.store"), {
                onSuccess: () => toast.success("Feature created successfully"),
                onError: () => toast.error("Failed to create feature"),
            });
        }
    };

    const addStep = () => {
        const newSteps = [
            ...data.steps,
            {
                ...defaultStep,
                data: {
                    ...defaultStep.data,
                    highlights: ["", "", ""],
                    stats: [
                        { label: "", value: "" },
                        { label: "", value: "" },
                    ],
                },
            },
        ];
        setData("steps", newSteps);
        setExpandedSteps([...expandedSteps, `step-${newSteps.length - 1}`]);
    };

    const removeStep = (index: number) => {
        if (data.steps.length <= 1) {
            toast.error("At least one step is required");
            return;
        }
        const newSteps = data.steps.filter((_, i) => i !== index);
        setData("steps", newSteps);
    };

    const moveStep = (index: number, direction: "up" | "down") => {
        const newIndex = direction === "up" ? index - 1 : index + 1;
        if (newIndex < 0 || newIndex >= data.steps.length) return;

        const newSteps = [...data.steps];
        const [movedStep] = newSteps.splice(index, 1);
        newSteps.splice(newIndex, 0, movedStep);
        setData("steps", newSteps);
    };

    const updateStep = (index: number, field: string, value: string) => {
        const newSteps = [...data.steps];
        const stepData = { ...newSteps[index].data };

        // Handle simple field updates
        if (field === "title") stepData.title = value;
        else if (field === "summary") stepData.summary = value;
        else if (field === "badge") stepData.badge = value;
        else if (field === "accent") stepData.accent = value;
        else if (field === "icon") stepData.icon = value;
        else if (field === "image") stepData.image = value;

        newSteps[index] = { ...newSteps[index], data: stepData };
        setData("steps", newSteps);
    };

    const updateStepHighlight = (stepIndex: number, highlightIndex: number, value: string) => {
        const newSteps = [...data.steps];
        if (!newSteps[stepIndex].data.highlights) {
            newSteps[stepIndex].data.highlights = ["", "", ""];
        }
        newSteps[stepIndex].data.highlights![highlightIndex] = value;
        setData("steps", newSteps);
    };

    const updateStepStat = (stepIndex: number, statIndex: number, field: "label" | "value", value: string) => {
        const newSteps = [...data.steps];
        if (!newSteps[stepIndex].data.stats) {
            newSteps[stepIndex].data.stats = [
                { label: "", value: "" },
                { label: "", value: "" },
            ];
        }
        newSteps[stepIndex].data.stats![statIndex][field] = value;
        setData("steps", newSteps);
    };

    return (
        <AdminLayout user={auth.user} title={isEditing ? "Edit Feature" : "Create Feature"}>
            <Head title={`Administrators • ${isEditing ? "Edit" : "Create"} Onboarding Feature`} />

            <form onSubmit={handleSubmit} className="bg-muted/30 min-h-[calc(100vh-65px)]">
                {/* Header */}
                <div className="bg-background/95 sticky top-0 z-20 border-b backdrop-blur-md">
                    <div className="flex items-center justify-between px-6 py-4">
                        <div className="flex items-center gap-4">
                            <Button variant="ghost" size="icon" asChild>
                                <Link href={route("administrators.onboarding-features.index")}>
                                    <ArrowLeft className="h-4 w-4" />
                                </Link>
                            </Button>
                            <div>
                                <h1 className="text-lg font-semibold">{isEditing ? "Edit Onboarding Feature" : "Create Onboarding Feature"}</h1>
                                <p className="text-muted-foreground text-sm">
                                    {isEditing ? `Editing "${feature.name}"` : "Configure a new guided feature introduction"}
                                </p>
                            </div>
                        </div>

                        <div className="flex items-center gap-3">
                            <div className="flex items-center gap-2 px-3">
                                <Switch
                                    id="is_active"
                                    checked={data.is_active}
                                    onCheckedChange={(checked) => setData("is_active", checked)}
                                    className="data-[state=checked]:bg-emerald-500"
                                />
                                <Label htmlFor="is_active" className="text-sm font-medium">
                                    {data.is_active ? "Active" : "Inactive"}
                                </Label>
                            </div>

                            <Separator orientation="vertical" className="h-6" />

                            <Button type="submit" disabled={processing} className="gap-2">
                                <Save className="h-4 w-4" />
                                {processing ? "Saving..." : "Save Changes"}
                            </Button>
                        </div>
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
                                    <CardDescription>Basic information about this onboarding feature</CardDescription>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent className="space-y-6">
                            <div className="grid gap-6 md:grid-cols-2">
                                <div className="space-y-2">
                                    <Label htmlFor="feature_key">
                                        Feature Key <span className="text-destructive">*</span>
                                    </Label>
                                    <Input
                                        id="feature_key"
                                        value={data.feature_key}
                                        onChange={(e) => setData("feature_key", e.target.value)}
                                        placeholder="onboarding-faculty-toolkit"
                                        className={cn(errors.feature_key && "border-destructive")}
                                    />
                                    <p className="text-muted-foreground text-xs">Matches the Pennant feature flag name</p>
                                    {errors.feature_key && (
                                        <p className="text-destructive flex items-center gap-1 text-xs">
                                            <AlertCircle className="h-3 w-3" />
                                            {errors.feature_key}
                                        </p>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="name">
                                        Display Name <span className="text-destructive">*</span>
                                    </Label>
                                    <Input
                                        id="name"
                                        value={data.name}
                                        onChange={(e) => setData("name", e.target.value)}
                                        placeholder="Faculty Toolkit"
                                        className={cn(errors.name && "border-destructive")}
                                    />
                                    {errors.name && (
                                        <p className="text-destructive flex items-center gap-1 text-xs">
                                            <AlertCircle className="h-3 w-3" />
                                            {errors.name}
                                        </p>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="audience">
                                        Target Audience <span className="text-destructive">*</span>
                                    </Label>
                                    <Select
                                        value={data.audience}
                                        onValueChange={(value) => setData("audience", value as "student" | "faculty" | "all")}
                                    >
                                        <SelectTrigger className={cn(errors.audience && "border-destructive")}>
                                            <SelectValue placeholder="Select audience" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {Object.entries(audiences).map(([key, label]) => (
                                                <SelectItem key={key} value={key}>
                                                    {label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {errors.audience && (
                                        <p className="text-destructive flex items-center gap-1 text-xs">
                                            <AlertCircle className="h-3 w-3" />
                                            {errors.audience}
                                        </p>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="badge">Badge Label</Label>
                                    <Input id="badge" value={data.badge} onChange={(e) => setData("badge", e.target.value)} placeholder="New" />
                                    <p className="text-muted-foreground text-xs">Short label shown near the title</p>
                                </div>
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="summary">Summary</Label>
                                <Textarea
                                    id="summary"
                                    value={data.summary}
                                    onChange={(e) => setData("summary", e.target.value)}
                                    placeholder="Brief description of what this feature does..."
                                    rows={3}
                                />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="accent">Accent Color Class</Label>
                                <Input
                                    id="accent"
                                    value={data.accent}
                                    onChange={(e) => setData("accent", e.target.value)}
                                    placeholder="text-primary"
                                />
                                <p className="text-muted-foreground text-xs">Tailwind class for accent styling</p>
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
                                    <Label htmlFor="cta_label">CTA Label</Label>
                                    <Input
                                        id="cta_label"
                                        value={data.cta_label}
                                        onChange={(e) => setData("cta_label", e.target.value)}
                                        placeholder="Get Started"
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="cta_url">CTA URL</Label>
                                    <Input
                                        id="cta_url"
                                        value={data.cta_url}
                                        onChange={(e) => setData("cta_url", e.target.value)}
                                        placeholder="/faculty/action-center"
                                        className={cn(errors.cta_url && "border-destructive")}
                                    />
                                    <p className="text-muted-foreground text-xs">Relative path or full URL</p>
                                    {errors.cta_url && (
                                        <p className="text-destructive flex items-center gap-1 text-xs">
                                            <AlertCircle className="h-3 w-3" />
                                            {errors.cta_url}
                                        </p>
                                    )}
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Onboarding Steps */}
                    <Card>
                        <CardHeader>
                            <div className="flex items-center justify-between">
                                <div>
                                    <CardTitle>Onboarding Steps</CardTitle>
                                    <CardDescription>Add steps with titles, descriptions, and visual elements</CardDescription>
                                </div>
                                <Button type="button" variant="outline" size="sm" onClick={addStep} className="gap-2">
                                    <Plus className="h-4 w-4" />
                                    Add Step
                                </Button>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <Accordion type="multiple" value={expandedSteps} onValueChange={setExpandedSteps} className="space-y-3">
                                {data.steps.map((step, index) => (
                                    <AccordionItem key={index} value={`step-${index}`} className="bg-muted/30 rounded-lg border px-4">
                                        <AccordionTrigger className="py-3 hover:no-underline">
                                            <div className="flex flex-1 items-center gap-3">
                                                <div className="text-muted-foreground flex items-center gap-1">
                                                    <GripVertical className="h-4 w-4" />
                                                </div>
                                                <Badge variant="outline" className="shrink-0">
                                                    Step {index + 1}
                                                </Badge>
                                                <span className="truncate text-sm font-medium">{step.data.title || "Untitled step"}</span>
                                            </div>
                                        </AccordionTrigger>
                                        <AccordionContent className="pt-4 pb-6">
                                            <div className="space-y-6">
                                                {/* Step Actions */}
                                                <div className="flex items-center justify-end gap-2">
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() => moveStep(index, "up")}
                                                        disabled={index === 0}
                                                    >
                                                        <ChevronUp className="h-4 w-4" />
                                                    </Button>
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() => moveStep(index, "down")}
                                                        disabled={index === data.steps.length - 1}
                                                    >
                                                        <ChevronDown className="h-4 w-4" />
                                                    </Button>
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() => removeStep(index)}
                                                        className="text-destructive hover:text-destructive"
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                    </Button>
                                                </div>

                                                {/* Step Fields */}
                                                <div className="grid gap-4 md:grid-cols-2">
                                                    <div className="space-y-2">
                                                        <Label>
                                                            Title <span className="text-destructive">*</span>
                                                        </Label>
                                                        <Input
                                                            value={step.data.title}
                                                            onChange={(e) => updateStep(index, "title", e.target.value)}
                                                            placeholder="Step title"
                                                        />
                                                    </div>
                                                    <div className="space-y-2">
                                                        <Label>Icon</Label>
                                                        <Input
                                                            value={step.data.icon || ""}
                                                            onChange={(e) => updateStep(index, "icon", e.target.value)}
                                                            placeholder="sparkles"
                                                        />
                                                        <p className="text-muted-foreground text-xs">Lucide icon name</p>
                                                    </div>
                                                </div>

                                                <div className="space-y-2">
                                                    <Label>
                                                        Summary <span className="text-destructive">*</span>
                                                    </Label>
                                                    <Textarea
                                                        value={step.data.summary}
                                                        onChange={(e) => updateStep(index, "summary", e.target.value)}
                                                        placeholder="Describe what this step covers..."
                                                        rows={2}
                                                    />
                                                </div>

                                                <div className="grid gap-4 md:grid-cols-2">
                                                    <div className="space-y-2">
                                                        <Label>Badge</Label>
                                                        <Input
                                                            value={step.data.badge || ""}
                                                            onChange={(e) => updateStep(index, "badge", e.target.value)}
                                                            placeholder="Optional badge"
                                                        />
                                                    </div>
                                                    <div className="space-y-2">
                                                        <Label>Accent Class</Label>
                                                        <Input
                                                            value={step.data.accent || ""}
                                                            onChange={(e) => updateStep(index, "accent", e.target.value)}
                                                            placeholder="text-primary"
                                                        />
                                                    </div>
                                                </div>

                                                {/* Image */}
                                                <div className="space-y-2">
                                                    <Label>Preview Image URL</Label>
                                                    <Input
                                                        value={step.data.image || ""}
                                                        onChange={(e) => updateStep(index, "image", e.target.value)}
                                                        placeholder="/storage/onboarding/step-image.png"
                                                    />
                                                    {step.data.image && (
                                                        <div className="bg-muted relative mt-2 h-32 w-full overflow-hidden rounded-lg border">
                                                            <img
                                                                src={step.data.image}
                                                                alt="Step preview"
                                                                className="h-full w-full object-cover"
                                                                onError={(e) => {
                                                                    (e.target as HTMLImageElement).style.display = "none";
                                                                }}
                                                            />
                                                        </div>
                                                    )}
                                                </div>

                                                {/* Highlights */}
                                                <div className="space-y-3">
                                                    <Label>Highlights</Label>
                                                    <div className="grid gap-2">
                                                        {[0, 1, 2].map((i) => (
                                                            <Input
                                                                key={i}
                                                                value={step.data.highlights?.[i] || ""}
                                                                onChange={(e) => updateStepHighlight(index, i, e.target.value)}
                                                                placeholder={`Highlight ${i + 1}`}
                                                            />
                                                        ))}
                                                    </div>
                                                </div>

                                                {/* Stats */}
                                                <div className="space-y-3">
                                                    <Label>Stats</Label>
                                                    <div className="grid gap-3 md:grid-cols-2">
                                                        {[0, 1].map((i) => (
                                                            <div key={i} className="grid grid-cols-2 gap-2">
                                                                <Input
                                                                    value={step.data.stats?.[i]?.label || ""}
                                                                    onChange={(e) => updateStepStat(index, i, "label", e.target.value)}
                                                                    placeholder={`Stat ${i + 1} label`}
                                                                />
                                                                <Input
                                                                    value={step.data.stats?.[i]?.value || ""}
                                                                    onChange={(e) => updateStepStat(index, i, "value", e.target.value)}
                                                                    placeholder="Value"
                                                                />
                                                            </div>
                                                        ))}
                                                    </div>
                                                </div>
                                            </div>
                                        </AccordionContent>
                                    </AccordionItem>
                                ))}
                            </Accordion>

                            {data.steps.length === 0 && (
                                <div className="text-muted-foreground py-8 text-center">
                                    <ImageIcon className="mx-auto mb-3 h-10 w-10 opacity-30" />
                                    <p>No steps added yet</p>
                                    <Button type="button" variant="link" onClick={addStep}>
                                        Add your first step
                                    </Button>
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* Form Footer */}
                    <div className="flex items-center justify-between pt-4 pb-8">
                        <Button variant="outline" asChild>
                            <Link href={route("administrators.onboarding-features.index")}>Cancel</Link>
                        </Button>
                        <Button type="submit" disabled={processing} className="gap-2">
                            <Save className="h-4 w-4" />
                            {processing ? "Saving..." : isEditing ? "Update Feature" : "Create Feature"}
                        </Button>
                    </div>
                </div>
            </form>
        </AdminLayout>
    );
}
