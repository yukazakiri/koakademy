import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Separator } from "@/components/ui/separator";
import { Textarea } from "@/components/ui/textarea";
import { cn } from "@/lib/utils";
import { useForm } from "@inertiajs/react";
import { AlertCircle, Bot, CheckCircle2, Globe2, Image as ImageIcon, Link2, Loader2, Save, Search, Share2, Sparkles, Type } from "lucide-react";
import { useEffect, useMemo, useState } from "react";
import { toast } from "sonner";
import { route } from "ziggy-js";

import SystemManagementLayout from "./layout";
import type { SystemManagementPageProps } from "./types";

type RobotsDirective = "index, follow" | "index, nofollow" | "noindex, follow" | "noindex, nofollow";
type TwitterCard = "summary" | "summary_large_image";

interface SeoFormData {
    site_name: string;
    site_description: string;
    seo_title: string;
    seo_keywords: string;
    seo_metadata: {
        robots: RobotsDirective;
        og_image: string;
        twitter_handle: string;
        twitter_card: TwitterCard;
        canonical_url: string;
    };
}

const robotsOptions: Array<{ value: RobotsDirective; label: string; helper: string }> = [
    {
        value: "index, follow",
        label: "Show in search results",
        helper: "Best for public marketing or information pages.",
    },
    {
        value: "index, nofollow",
        label: "Show page only",
        helper: "Search engines can list the page but should not follow links.",
    },
    {
        value: "noindex, follow",
        label: "Hide page, follow links",
        helper: "Useful for private portals that still link to public pages.",
    },
    {
        value: "noindex, nofollow",
        label: "Hide from search",
        helper: "Recommended for admin-only or internal systems.",
    },
];

function characterTone(length: number, idealMin: number, idealMax: number): string {
    if (length === 0) {
        return "text-muted-foreground";
    }

    if (length < idealMin) {
        return "text-amber-600 dark:text-amber-400";
    }

    if (length > idealMax) {
        return "text-destructive";
    }

    return "text-emerald-600 dark:text-emerald-400";
}

function compactUrl(url: string): string {
    if (!url) {
        return "https://example.edu";
    }

    try {
        const parsed = new URL(url);

        return `${parsed.hostname}${parsed.pathname === "/" ? "" : parsed.pathname}`;
    } catch {
        return url.replace(/^https?:\/\//, "");
    }
}

export default function SystemManagementSeoPage({ user, general_settings, access }: SystemManagementPageProps) {
    const seoMetadata = general_settings?.seo_metadata ?? {};
    const canUpdate = access.sections.seo?.can_update ?? false;
    const [imageHasError, setImageHasError] = useState(false);

    const seoForm = useForm<SeoFormData>({
        site_name: general_settings?.site_name || "",
        site_description: general_settings?.site_description || "",
        seo_title: general_settings?.seo_title || "",
        seo_keywords: general_settings?.seo_keywords || "",
        seo_metadata: {
            robots: (seoMetadata.robots as RobotsDirective | undefined) || "index, follow",
            og_image: seoMetadata.og_image || "",
            twitter_handle: seoMetadata.twitter_handle || "",
            twitter_card: (seoMetadata.twitter_card as TwitterCard | undefined) || "summary_large_image",
            canonical_url: seoMetadata.canonical_url || "",
        },
    });

    useEffect(() => {
        setImageHasError(false);
    }, [seoForm.data.seo_metadata.og_image]);

    const errors = seoForm.errors as Record<string, string | undefined>;
    const resolvedTitle = seoForm.data.seo_title || seoForm.data.site_name || "KoAkademy";
    const resolvedDescription =
        seoForm.data.site_description || "Add a clear description so people understand what this portal offers before they click.";
    const resolvedCanonical =
        seoForm.data.seo_metadata.canonical_url ||
        (typeof window !== "undefined" ? `${window.location.origin}${window.location.pathname}` : "https://example.edu");
    const robotsOption = robotsOptions.find((option) => option.value === seoForm.data.seo_metadata.robots) ?? robotsOptions[0];
    const hasSocialImage = seoForm.data.seo_metadata.og_image.trim() !== "" && !imageHasError;
    const titleLength = resolvedTitle.length;
    const descriptionLength = seoForm.data.site_description.length;
    const completionItems = useMemo(
        () => [
            Boolean(seoForm.data.site_name.trim()),
            Boolean(seoForm.data.seo_title.trim()),
            Boolean(seoForm.data.site_description.trim()),
            Boolean(seoForm.data.seo_metadata.og_image.trim()),
            Boolean(seoForm.data.seo_metadata.robots),
        ],
        [
            seoForm.data.seo_metadata.og_image,
            seoForm.data.seo_metadata.robots,
            seoForm.data.seo_title,
            seoForm.data.site_description,
            seoForm.data.site_name,
        ],
    );
    const completion = Math.round((completionItems.filter(Boolean).length / completionItems.length) * 100);

    const setSeoMetadata = <Key extends keyof SeoFormData["seo_metadata"]>(key: Key, value: SeoFormData["seo_metadata"][Key]) => {
        seoForm.setData("seo_metadata", {
            ...seoForm.data.seo_metadata,
            [key]: value,
        });
    };

    const handleSave = () => {
        seoForm.put(route("administrators.system-management.seo.update"), {
            preserveScroll: true,
            onSuccess: () => toast.success("SEO settings updated successfully."),
            onError: () => toast.error("Failed to update SEO settings."),
        });
    };

    return (
        <SystemManagementLayout
            user={user}
            access={access}
            activeSection="seo"
            heading="Website & Sharing"
            description="Set how the portal appears in browser tabs, search results, and social link previews."
        >
            <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_390px]">
                <div className="space-y-5">
                    <Card className="border-primary/20 from-primary/5 via-background to-background bg-linear-to-br">
                        <CardHeader className="pb-3">
                            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <CardTitle className="flex items-center gap-2 text-base">
                                        <Sparkles className="h-4 w-4" />
                                        Setup Progress
                                    </CardTitle>
                                    <CardDescription>Fill the essentials once. The previews update as you type.</CardDescription>
                                </div>
                                <Badge variant="secondary">{completion}% complete</Badge>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div className="bg-muted h-2 rounded-full">
                                <div className="bg-primary h-2 rounded-full transition-all" style={{ width: `${completion}%` }} />
                            </div>
                            <div className="mt-4 flex flex-wrap items-center gap-2">
                                {seoForm.isDirty ? (
                                    <Badge className="bg-amber-100 text-amber-800 hover:bg-amber-100 dark:bg-amber-500/15 dark:text-amber-200">
                                        Unsaved changes
                                    </Badge>
                                ) : (
                                    <Badge className="bg-emerald-100 text-emerald-800 hover:bg-emerald-100 dark:bg-emerald-500/15 dark:text-emerald-200">
                                        Saved
                                    </Badge>
                                )}
                                <p className="text-muted-foreground text-xs">Search and social previews use these saved defaults.</p>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-base">
                                <Type className="h-4 w-4" />
                                1. Search Basics
                            </CardTitle>
                            <CardDescription>The title and description people see before they visit.</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-5">
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="space-y-2">
                                    <Label htmlFor="site_name">Site name</Label>
                                    <Input
                                        id="site_name"
                                        value={seoForm.data.site_name}
                                        disabled={!canUpdate}
                                        onChange={(event) => seoForm.setData("site_name", event.target.value)}
                                        placeholder="KoAkademy"
                                    />
                                    {errors.site_name ? <p className="text-destructive text-xs">{errors.site_name}</p> : null}
                                </div>
                                <div className="space-y-2">
                                    <div className="flex items-center justify-between gap-3">
                                        <Label htmlFor="seo_title">Search title</Label>
                                        <span className={cn("text-xs font-medium", characterTone(titleLength, 35, 60))}>{titleLength} chars</span>
                                    </div>
                                    <Input
                                        id="seo_title"
                                        value={seoForm.data.seo_title}
                                        disabled={!canUpdate}
                                        onChange={(event) => seoForm.setData("seo_title", event.target.value)}
                                        placeholder="KoAkademy Administrator Panel"
                                    />
                                    {errors.seo_title ? <p className="text-destructive text-xs">{errors.seo_title}</p> : null}
                                </div>
                            </div>

                            <div className="space-y-2">
                                <div className="flex items-center justify-between gap-3">
                                    <Label htmlFor="site_description">Search description</Label>
                                    <span className={cn("text-xs font-medium", characterTone(descriptionLength, 120, 160))}>
                                        {descriptionLength} / 160
                                    </span>
                                </div>
                                <Textarea
                                    id="site_description"
                                    rows={4}
                                    value={seoForm.data.site_description}
                                    disabled={!canUpdate}
                                    onChange={(event) => seoForm.setData("site_description", event.target.value)}
                                    placeholder="Describe the portal in one clear sentence."
                                />
                                {errors.site_description ? <p className="text-destructive text-xs">{errors.site_description}</p> : null}
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-base">
                                <Share2 className="h-4 w-4" />
                                2. Social Sharing
                            </CardTitle>
                            <CardDescription>Control the card that appears when someone shares a link.</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-5">
                            <div className="space-y-2">
                                <div className="flex items-center justify-between gap-3">
                                    <Label htmlFor="og_image">Share image URL</Label>
                                    <span className="text-muted-foreground text-xs">1200 x 630 recommended</span>
                                </div>
                                <div className="relative">
                                    <ImageIcon className="text-muted-foreground pointer-events-none absolute top-2.5 left-3 h-4 w-4" />
                                    <Input
                                        id="og_image"
                                        value={seoForm.data.seo_metadata.og_image}
                                        disabled={!canUpdate}
                                        onChange={(event) => setSeoMetadata("og_image", event.target.value)}
                                        className="pl-9"
                                        placeholder="https://example.edu/images/share-card.png"
                                    />
                                </div>
                                {errors["seo_metadata.og_image"] ? (
                                    <p className="text-destructive text-xs">{errors["seo_metadata.og_image"]}</p>
                                ) : null}
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="space-y-2">
                                    <Label htmlFor="twitter_card">Card style</Label>
                                    <Select
                                        value={seoForm.data.seo_metadata.twitter_card}
                                        disabled={!canUpdate}
                                        onValueChange={(value) => setSeoMetadata("twitter_card", value as TwitterCard)}
                                    >
                                        <SelectTrigger id="twitter_card">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="summary_large_image">Large image card</SelectItem>
                                            <SelectItem value="summary">Compact summary card</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="twitter_handle">X handle</Label>
                                    <Input
                                        id="twitter_handle"
                                        value={seoForm.data.seo_metadata.twitter_handle}
                                        disabled={!canUpdate}
                                        onChange={(event) => setSeoMetadata("twitter_handle", event.target.value)}
                                        placeholder="@koakademy"
                                    />
                                    {errors["seo_metadata.twitter_handle"] ? (
                                        <p className="text-destructive text-xs">{errors["seo_metadata.twitter_handle"]}</p>
                                    ) : null}
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-base">
                                <Bot className="h-4 w-4" />
                                3. Indexing
                            </CardTitle>
                            <CardDescription>Choose whether search engines should list the site and follow links.</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-5">
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="space-y-2">
                                    <Label htmlFor="robots">Search engine access</Label>
                                    <Select
                                        value={seoForm.data.seo_metadata.robots}
                                        disabled={!canUpdate}
                                        onValueChange={(value) => setSeoMetadata("robots", value as RobotsDirective)}
                                    >
                                        <SelectTrigger id="robots">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {robotsOptions.map((option) => (
                                                <SelectItem key={option.value} value={option.value}>
                                                    {option.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <p className="text-muted-foreground text-xs">{robotsOption.helper}</p>
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="canonical_url">Canonical URL</Label>
                                    <div className="relative">
                                        <Link2 className="text-muted-foreground pointer-events-none absolute top-2.5 left-3 h-4 w-4" />
                                        <Input
                                            id="canonical_url"
                                            value={seoForm.data.seo_metadata.canonical_url}
                                            disabled={!canUpdate}
                                            onChange={(event) => setSeoMetadata("canonical_url", event.target.value)}
                                            className="pl-9"
                                            placeholder="Leave blank to use the current page URL"
                                        />
                                    </div>
                                    {errors["seo_metadata.canonical_url"] ? (
                                        <p className="text-destructive text-xs">{errors["seo_metadata.canonical_url"]}</p>
                                    ) : null}
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-base">
                                <Search className="h-4 w-4" />
                                4. Advanced Keywords
                            </CardTitle>
                            <CardDescription>
                                Optional. Some older tools still read these, but modern search ranking rarely depends on them.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-2">
                            <Label htmlFor="seo_keywords">Keywords</Label>
                            <Textarea
                                id="seo_keywords"
                                rows={3}
                                value={seoForm.data.seo_keywords}
                                disabled={!canUpdate}
                                onChange={(event) => seoForm.setData("seo_keywords", event.target.value)}
                                placeholder="college portal, enrollment, student records, academic management"
                            />
                            {errors.seo_keywords ? <p className="text-destructive text-xs">{errors.seo_keywords}</p> : null}
                        </CardContent>
                    </Card>

                    <div className="bg-background/95 sticky bottom-4 z-10 rounded-xl border p-3 backdrop-blur">
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <p className="text-muted-foreground text-sm">
                                {canUpdate
                                    ? seoForm.isDirty
                                        ? "You have SEO changes waiting to be saved."
                                        : "No pending SEO changes."
                                    : "Your role can view these settings but cannot update them."}
                            </p>
                            <Button onClick={handleSave} disabled={!canUpdate || seoForm.processing || !seoForm.isDirty}>
                                {seoForm.processing ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <Save className="mr-2 h-4 w-4" />}
                                Save SEO Settings
                            </Button>
                        </div>
                    </div>
                </div>

                <div className="space-y-5 xl:sticky xl:top-[104px] xl:h-fit">
                    <Card>
                        <CardHeader className="pb-3">
                            <CardTitle className="flex items-center justify-between gap-3 text-sm">
                                <span className="flex items-center gap-2">
                                    <Search className="h-4 w-4" />
                                    Search Preview
                                </span>
                                <Badge variant="outline">{seoForm.data.seo_metadata.robots}</Badge>
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-2 rounded-lg border bg-white p-4 text-slate-950 dark:bg-slate-950 dark:text-slate-50">
                                <div className="flex items-center gap-2">
                                    <span className="flex h-7 w-7 items-center justify-center rounded-full bg-slate-100 text-slate-500 dark:bg-slate-900">
                                        <Globe2 className="h-3.5 w-3.5" />
                                    </span>
                                    <div className="min-w-0">
                                        <p className="truncate text-sm">{seoForm.data.site_name || "KoAkademy"}</p>
                                        <p className="truncate text-xs text-slate-500 dark:text-slate-400">{compactUrl(resolvedCanonical)}</p>
                                    </div>
                                </div>
                                <p className="line-clamp-2 text-xl leading-tight text-[#1a0dab] dark:text-[#8ab4f8]">{resolvedTitle}</p>
                                <p className="line-clamp-3 text-sm leading-relaxed text-[#4d5156] dark:text-[#bdc1c6]">{resolvedDescription}</p>
                            </div>
                            <div className="mt-3 grid gap-2 text-xs sm:grid-cols-2">
                                <div className="rounded-lg border p-2">
                                    <p className="text-muted-foreground">Title</p>
                                    <p className={cn("font-medium", characterTone(titleLength, 35, 60))}>
                                        {titleLength < 35 ? "Could be longer" : titleLength > 60 ? "May truncate" : "Good length"}
                                    </p>
                                </div>
                                <div className="rounded-lg border p-2">
                                    <p className="text-muted-foreground">Description</p>
                                    <p className={cn("font-medium", characterTone(descriptionLength, 120, 160))}>
                                        {descriptionLength < 120 ? "Could be richer" : descriptionLength > 160 ? "May truncate" : "Good length"}
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="pb-3">
                            <CardTitle className="flex items-center justify-between gap-3 text-sm">
                                <span className="flex items-center gap-2">
                                    <Share2 className="h-4 w-4" />
                                    Social Share Preview
                                </span>
                                <Badge variant="outline">
                                    {seoForm.data.seo_metadata.twitter_card === "summary_large_image" ? "Large card" : "Summary"}
                                </Badge>
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="p-0">
                            <div
                                className={cn(
                                    "bg-muted/30 flex items-center justify-center overflow-hidden border-y",
                                    seoForm.data.seo_metadata.twitter_card === "summary_large_image" ? "aspect-[1.91/1]" : "aspect-video",
                                )}
                            >
                                {hasSocialImage ? (
                                    <img
                                        src={seoForm.data.seo_metadata.og_image}
                                        alt="Social share preview"
                                        className="h-full w-full object-cover"
                                        onError={() => setImageHasError(true)}
                                    />
                                ) : (
                                    <div className="text-muted-foreground flex flex-col items-center gap-2 text-center">
                                        <ImageIcon className="h-8 w-8 opacity-50" />
                                        <span className="text-xs font-medium">{imageHasError ? "Image could not load" : "No share image yet"}</span>
                                    </div>
                                )}
                            </div>
                            <div className="space-y-1 p-4">
                                <p className="text-muted-foreground text-[11px] font-semibold tracking-wider uppercase">
                                    {compactUrl(resolvedCanonical)}
                                </p>
                                <p className="line-clamp-2 text-base leading-tight font-semibold">{resolvedTitle}</p>
                                <p className="text-muted-foreground line-clamp-2 text-sm leading-relaxed">{resolvedDescription}</p>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="pb-3">
                            <CardTitle className="flex items-center gap-2 text-sm">
                                <Bot className="h-4 w-4" />
                                Indexing Summary
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            <Alert>
                                {seoForm.data.seo_metadata.robots.startsWith("noindex") ? (
                                    <AlertCircle className="h-4 w-4" />
                                ) : (
                                    <CheckCircle2 className="h-4 w-4" />
                                )}
                                <AlertTitle>{robotsOption.label}</AlertTitle>
                                <AlertDescription>{robotsOption.helper}</AlertDescription>
                            </Alert>
                            <Separator />
                            <div className="space-y-1">
                                <p className="text-sm font-medium">Canonical target</p>
                                <p className="text-muted-foreground text-sm break-all">{resolvedCanonical}</p>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </SystemManagementLayout>
    );
}
