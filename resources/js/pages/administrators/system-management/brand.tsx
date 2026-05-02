import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { RadioGroup, RadioGroupItem } from "@/components/ui/radio-group";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Separator } from "@/components/ui/separator";
import { Textarea } from "@/components/ui/textarea";
import { cn } from "@/lib/utils";
import { useForm } from "@inertiajs/react";
import { Building2, Check, Columns2, ImageIcon, Loader2, Mail, Paintbrush, Phone, RectangleHorizontal, Save, Sparkles, SquareDashed, Type, WandSparkles } from "lucide-react";
import { useMemo, useState } from "react";

import { submitSystemForm } from "./form-submit";
import SystemManagementLayout from "./layout";
import type { SystemManagementPageProps } from "./types";

interface BrandFormData {
    app_name: string;
    app_short_name: string;
    organization_name: string;
    organization_short_name: string;
    organization_address: string;
    support_email: string;
    support_phone: string;
    tagline: string;
    copyright_text: string;
    theme_color: string;
    currency: string;
    auth_layout: "card" | "split" | "minimal";
    logo: File | null;
}

export default function SystemManagementBrandPage({ user, branding, access }: SystemManagementPageProps) {
    const brandForm = useForm<BrandFormData>({
        app_name: branding?.app_name || "",
        app_short_name: branding?.app_short_name || "",
        organization_name: branding?.organization_name || "",
        organization_short_name: branding?.organization_short_name || "",
        organization_address: branding?.organization_address || "",
        support_email: branding?.support_email || "",
        support_phone: branding?.support_phone || "",
        tagline: branding?.tagline || "",
        copyright_text: branding?.copyright_text || "",
        theme_color: branding?.theme_color || "#0f172a",
        currency: branding?.currency || "PHP",
        auth_layout: branding?.auth_layout || "split",
        logo: null,
    });

    const [logoPreview, setLogoPreview] = useState<string | null>(null);
    const primaryColor = brandForm.data.theme_color || "#0f172a";
    const isDirty = brandForm.isDirty || brandForm.data.logo !== null;

    const currentLogo = logoPreview || branding?.logo || "/web-app-manifest-192x192.png";
    const displayName = brandForm.data.app_name || "Your School Portal";
    const displayShortName = brandForm.data.app_short_name || displayName;
    const displayTagline = brandForm.data.tagline || "Your Campus, Your Connection";

    const completion = useMemo(() => {
        const checks = [
            Boolean(brandForm.data.app_name),
            Boolean(brandForm.data.organization_name),
            Boolean(brandForm.data.tagline),
            Boolean(brandForm.data.support_email),
        ];
        const done = checks.filter(Boolean).length;

        return Math.round((done / checks.length) * 100);
    }, [brandForm.data.app_name, brandForm.data.organization_name, brandForm.data.support_email, brandForm.data.tagline]);

    const handleSave = () => {
        submitSystemForm({
            form: brandForm,
            routeName: "administrators.system-management.brand.update",
            successMessage: "Brand settings saved successfully.",
            errorMessage: "Failed to save brand settings.",
            hasFiles: true,
        });
    };

    return (
        <SystemManagementLayout
            user={user}
            access={access}
            activeSection="brand"
            heading="Brand & Appearance"
            description="A simple guided setup for your portal's look and feel."
        >
            <div className="grid gap-6 xl:grid-cols-[1fr_360px]">
                <div className="space-y-5">
                    <Card className="border-primary/20 bg-gradient-to-br from-primary/5 via-background to-background">
                        <CardHeader className="pb-3">
                            <div className="flex items-center justify-between gap-3">
                                <div>
                                    <CardTitle className="flex items-center gap-2 text-base">
                                        <WandSparkles className="h-4 w-4" />
                                        Quick Setup Status
                                    </CardTitle>
                                    <CardDescription>Fill in the basics below. Everything updates in the live preview.</CardDescription>
                                </div>
                                <Badge variant="secondary">{completion}% complete</Badge>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div className="bg-muted h-2 rounded-full">
                                <div className="bg-primary h-2 rounded-full transition-all" style={{ width: `${completion}%` }} />
                            </div>
                            <div className="mt-4 flex flex-wrap items-center gap-2">
                                {isDirty ? (
                                    <Badge className="bg-amber-100 text-amber-800 hover:bg-amber-100">Unsaved changes</Badge>
                                ) : (
                                    <Badge className="bg-emerald-100 text-emerald-800 hover:bg-emerald-100">All changes saved</Badge>
                                )}
                                <p className="text-muted-foreground text-xs">Tip: click Save Changes after editing.</p>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-base">
                                <Type className="h-4 w-4" />
                                1) Portal Name & Identity
                            </CardTitle>
                            <CardDescription>What users will read first when they visit your portal.</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="space-y-2">
                                    <Label htmlFor="app_name">Portal Name</Label>
                                    <Input id="app_name" value={brandForm.data.app_name} onChange={(e) => brandForm.setData("app_name", e.target.value)} placeholder="Example: KoAkademy Portal" />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="app_short_name">Short Name</Label>
                                    <Input
                                        id="app_short_name"
                                        value={brandForm.data.app_short_name}
                                        onChange={(e) => brandForm.setData("app_short_name", e.target.value)}
                                        placeholder="Example: KOA"
                                    />
                                </div>
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="tagline">Welcome Message / Tagline</Label>
                                <Input
                                    id="tagline"
                                    value={brandForm.data.tagline}
                                    onChange={(e) => brandForm.setData("tagline", e.target.value)}
                                    placeholder="Example: Your Campus, Your Connection"
                                />
                                <p className="text-muted-foreground text-xs">Shown on login and related pages.</p>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-base">
                                <ImageIcon className="h-4 w-4" />
                                2) Logo & Color
                            </CardTitle>
                            <CardDescription>Upload your logo and pick a color. The system handles the rest.</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <button
                                type="button"
                                className="bg-muted/30 border-muted-foreground/30 hover:border-primary/50 block w-full rounded-xl border-2 border-dashed p-4 text-left transition-colors"
                                onClick={() => document.getElementById("brand-logo-input")?.click()}
                            >
                                <div className="flex items-center gap-4">
                                    <img src={currentLogo} alt="Logo preview" className="h-14 w-14 rounded-md border object-contain p-1" />
                                    <div>
                                        <p className="font-medium">Click to upload or replace your logo</p>
                                        <p className="text-muted-foreground text-xs">PNG, JPG, SVG, GIF, or WebP (max 5MB). Favicon and app icons are auto-generated.</p>
                                    </div>
                                </div>
                            </button>
                            <input
                                id="brand-logo-input"
                                type="file"
                                accept="image/png,image/jpeg,image/gif,image/webp,image/svg+xml"
                                className="hidden"
                                onChange={(event) => {
                                    const file = event.target.files?.[0] || null;
                                    brandForm.setData("logo", file);
                                    if (!file) {
                                        setLogoPreview(null);
                                        return;
                                    }

                                    const reader = new FileReader();
                                    reader.onload = (ev) => setLogoPreview(ev.target?.result as string);
                                    reader.readAsDataURL(file);
                                }}
                            />

                            <div className="space-y-2">
                                <Label htmlFor="theme_color" className="flex items-center gap-2">
                                    <Paintbrush className="h-4 w-4" />
                                    Primary Color
                                </Label>
                                <div className="flex items-center gap-3">
                                    <div className="relative h-10 w-10 overflow-hidden rounded-md border" style={{ backgroundColor: primaryColor }}>
                                        <input
                                            type="color"
                                            value={primaryColor}
                                            className="absolute inset-0 h-full w-full cursor-pointer opacity-0"
                                            onChange={(e) => brandForm.setData("theme_color", e.target.value)}
                                        />
                                    </div>
                                    <Input id="theme_color" value={primaryColor} onChange={(e) => brandForm.setData("theme_color", e.target.value)} className="w-32 font-mono uppercase" />
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-base">
                                <Sparkles className="h-4 w-4" />
                                3) Login Page Style
                            </CardTitle>
                            <CardDescription>Pick the layout your users see when signing in.</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            <p className="text-muted-foreground text-sm">Tip for non-technical admins: choose the preview that looks closest to how you want login to feel.</p>
                            <RadioGroup
                                value={brandForm.data.auth_layout}
                                onValueChange={(value) => brandForm.setData("auth_layout", value as "card" | "split" | "minimal")}
                                className="grid gap-3 md:grid-cols-3"
                            >
                                {[
                                    { key: "card", title: "Card", desc: "Simple centered login card." },
                                    { key: "split", title: "Split", desc: "Form + branded side panel." },
                                    { key: "minimal", title: "Minimal", desc: "Clean and compact." },
                                ].map((option) => {
                                    const isSelected = brandForm.data.auth_layout === option.key;
                                    return (
                                        <label
                                            key={option.key}
                                            htmlFor={`auth-layout-${option.key}`}
                                            onClick={() => brandForm.setData("auth_layout", option.key as "card" | "split" | "minimal")}
                                            className={cn(
                                                "bg-card text-card-foreground relative cursor-pointer rounded-xl border p-3 transition-all",
                                                "focus-within:ring-primary/30 focus-within:ring-2",
                                                isSelected ? "border-primary ring-primary/20 ring-2" : "border-border hover:border-primary/40",
                                            )}
                                        >
                                            <RadioGroupItem id={`auth-layout-${option.key}`} value={option.key} className="sr-only" />
                                            {isSelected ? (
                                                <span className="bg-primary text-primary-foreground absolute top-2 right-2 inline-flex h-5 w-5 items-center justify-center rounded-full">
                                                    <Check className="h-3 w-3" />
                                                </span>
                                            ) : null}
                                            <div className="bg-muted/30 mb-2 overflow-hidden rounded-lg border">
                                                {option.key === "card" ? (
                                                    <div className="flex h-16 items-center justify-center p-2">
                                                        <div className="bg-background h-full w-16 rounded border p-1 shadow-sm">
                                                            <div className="bg-muted mb-1 h-1.5 w-8 rounded" />
                                                            <div className="bg-muted h-1.5 w-full rounded" />
                                                            <div className="bg-muted mt-1 h-1.5 w-10 rounded" />
                                                        </div>
                                                    </div>
                                                ) : null}

                                                {option.key === "split" ? (
                                                    <div className="grid h-16 grid-cols-2">
                                                        <div className="bg-background p-2">
                                                            <div className="bg-muted mb-1 h-1.5 w-8 rounded" />
                                                            <div className="bg-muted h-1.5 w-full rounded" />
                                                            <div className="bg-muted mt-1 h-1.5 w-10 rounded" />
                                                        </div>
                                                        <div className="opacity-80" style={{ backgroundColor: primaryColor }} />
                                                    </div>
                                                ) : null}

                                                {option.key === "minimal" ? (
                                                    <div className="bg-background flex h-16 items-center justify-center p-2">
                                                        <div className="w-16 space-y-1">
                                                            <div className="bg-muted mx-auto h-1.5 w-8 rounded" />
                                                            <div className="bg-muted h-1.5 w-full rounded" />
                                                            <div className="bg-muted h-1.5 w-10 rounded" />
                                                        </div>
                                                    </div>
                                                ) : null}
                                            </div>
                                            <div className="mb-1 flex items-center gap-1.5">
                                                {option.key === "card" ? <SquareDashed className="text-muted-foreground h-3.5 w-3.5" /> : null}
                                                {option.key === "split" ? <Columns2 className="text-muted-foreground h-3.5 w-3.5" /> : null}
                                                {option.key === "minimal" ? <RectangleHorizontal className="text-muted-foreground h-3.5 w-3.5" /> : null}
                                                <p className="text-sm font-medium">{option.title}</p>
                                            </div>
                                            <p className="text-muted-foreground text-xs">{option.desc}</p>
                                        </label>
                                    );
                                })}
                            </RadioGroup>
                            <p className="text-muted-foreground text-xs">
                                Currently selected: <span className="text-foreground font-medium capitalize">{brandForm.data.auth_layout}</span>
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-base">
                                <Building2 className="h-4 w-4" />
                                4) School Contact & Legal
                            </CardTitle>
                            <CardDescription>Used in footers, support info, and formal documents.</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="space-y-2">
                                    <Label htmlFor="organization_name">School / Organization Name</Label>
                                    <Input
                                        id="organization_name"
                                        value={brandForm.data.organization_name}
                                        onChange={(e) => brandForm.setData("organization_name", e.target.value)}
                                        placeholder="Example: Divine Child Catholic Parish"
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="organization_short_name">Short Name</Label>
                                    <Input
                                        id="organization_short_name"
                                        value={brandForm.data.organization_short_name}
                                        onChange={(e) => brandForm.setData("organization_short_name", e.target.value)}
                                        placeholder="Example: DCCP"
                                    />
                                </div>
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="organization_address">Address</Label>
                                <Textarea
                                    id="organization_address"
                                    rows={2}
                                    value={brandForm.data.organization_address}
                                    onChange={(e) => brandForm.setData("organization_address", e.target.value)}
                                    placeholder="Complete school address"
                                />
                            </div>
                            <Separator />
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="space-y-2">
                                    <Label htmlFor="support_email" className="flex items-center gap-2">
                                        <Mail className="h-4 w-4" />
                                        Support Email
                                    </Label>
                                    <Input
                                        id="support_email"
                                        type="email"
                                        value={brandForm.data.support_email}
                                        onChange={(e) => brandForm.setData("support_email", e.target.value)}
                                        placeholder="support@yourschool.edu"
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="support_phone" className="flex items-center gap-2">
                                        <Phone className="h-4 w-4" />
                                        Support Phone
                                    </Label>
                                    <Input id="support_phone" value={brandForm.data.support_phone} onChange={(e) => brandForm.setData("support_phone", e.target.value)} placeholder="+63 912 345 6789" />
                                </div>
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="space-y-2">
                                    <Label htmlFor="copyright_text">Copyright Text</Label>
                                    <Input
                                        id="copyright_text"
                                        value={brandForm.data.copyright_text}
                                        onChange={(e) => brandForm.setData("copyright_text", e.target.value)}
                                        placeholder="All rights reserved."
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="currency">Currency</Label>
                                    <Select value={brandForm.data.currency} onValueChange={(value) => brandForm.setData("currency", value)}>
                                        <SelectTrigger id="currency">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="PHP">PHP ₱ - Philippine Peso</SelectItem>
                                            <SelectItem value="USD">USD $ - US Dollar</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <div className="sticky bottom-4 z-10 rounded-xl border bg-background/95 p-3 backdrop-blur">
                        <div className="flex items-center justify-between gap-3">
                            <p className="text-muted-foreground text-sm">{isDirty ? "You have changes waiting to be saved." : "No pending changes."}</p>
                            <Button onClick={handleSave} disabled={brandForm.processing || !isDirty}>
                                {brandForm.processing ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <Save className="mr-2 h-4 w-4" />}
                                Save Changes
                            </Button>
                        </div>
                    </div>
                </div>

                <div className="space-y-5 xl:sticky xl:top-[104px] xl:h-fit">
                    <Card className="overflow-hidden">
                        <CardHeader className="bg-muted/40 pb-3">
                            <CardTitle className="text-sm">Live Preview (what users will see)</CardTitle>
                            <CardDescription className="text-xs">Updates instantly while you edit.</CardDescription>
                        </CardHeader>
                        <CardContent className="p-0">
                            <div className="p-4" style={{ backgroundColor: primaryColor }}>
                                <div className="rounded-lg border border-white/20 bg-white/10 p-4 backdrop-blur-sm">
                                    <div className="mb-2 flex items-center justify-center">
                                        <img src={currentLogo} alt="Preview logo" className="h-10 w-10 rounded bg-white p-1 object-contain" />
                                    </div>
                                    <p className="truncate text-center text-sm font-semibold text-white">{displayName}</p>
                                    <p className="truncate text-center text-xs text-white/80">{displayTagline}</p>
                                </div>
                            </div>
                            <div className="space-y-3 p-4">
                                <div className="flex items-center gap-2">
                                    <img src={currentLogo} alt="Preview mini logo" className="h-5 w-5 rounded object-contain" />
                                    <span className="truncate text-sm font-medium">{displayShortName}</span>
                                </div>
                                <p className="text-muted-foreground text-xs">Login layout: <span className="text-foreground font-medium capitalize">{brandForm.data.auth_layout}</span></p>
                                <Separator />
                                <p className="text-muted-foreground text-xs">Support: {[brandForm.data.support_email, brandForm.data.support_phone].filter(Boolean).join(" • ") || "Not set yet"}</p>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </SystemManagementLayout>
    );
}
