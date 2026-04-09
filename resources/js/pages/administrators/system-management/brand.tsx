import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import { useForm } from "@inertiajs/react";
import { AppWindow, Briefcase, Image as ImageIcon, Loader2, Palette, PhoneCall, Save, Scale } from "lucide-react";

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
    logo: File | null;
    favicon: File | null;
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
        logo: null,
        favicon: null,
    });

    const primaryColor = brandForm.data.theme_color || "#0f172a";

    return (
        <SystemManagementLayout
            user={user}
            access={access}
            activeSection="brand"
            heading="Brand & Appearance Console"
            description="Control application identity, visual assets, and organization profile details used globally."
        >
            <div className="flex flex-col gap-6 xl:flex-row">
                {/* Left Column: Forms */}
                <div className="flex-1 space-y-6">
                    <div className="bg-muted/30 border-muted-foreground/10 flex flex-col justify-between gap-4 rounded-xl border p-4 sm:flex-row sm:items-center">
                        <div className="space-y-1">
                            <h2 className="text-foreground text-lg font-semibold tracking-tight">Brand Configuration</h2>
                            <p className="text-muted-foreground text-sm">Adjust names, aesthetics, and organization specifics.</p>
                        </div>
                        <Button
                            onClick={() =>
                                submitSystemForm({
                                    form: brandForm,
                                    routeName: "administrators.system-management.brand.update",
                                    successMessage: "Brand settings updated successfully.",
                                    errorMessage: "Failed to update brand settings.",
                                    hasFiles: true,
                                })
                            }
                            disabled={brandForm.processing || (!brandForm.isDirty && brandForm.data.logo === null && brandForm.data.favicon === null)}
                            className="w-full shrink-0 shadow-sm sm:w-auto"
                        >
                            {brandForm.processing ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <Save className="mr-2 h-4 w-4" />}
                            Apply Settings
                        </Button>
                    </div>

                    <div className="grid gap-6">
                        {/* Core Identity */}
                        <Card className="border-muted-foreground/10 overflow-hidden shadow-sm">
                            <div className="h-1 w-full" style={{ backgroundColor: primaryColor }} />
                            <CardHeader className="bg-muted/10 border-b pb-4">
                                <div className="flex items-center gap-2">
                                    <div className="rounded-md bg-blue-100 p-1.5 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400">
                                        <AppWindow className="h-4 w-4" />
                                    </div>
                                    <div>
                                        <CardTitle className="text-base">Software Identity</CardTitle>
                                        <CardDescription className="mt-0.5 text-xs">
                                            The application's public-facing primary names and taglines.
                                        </CardDescription>
                                    </div>
                                </div>
                            </CardHeader>
                            <CardContent className="space-y-5 p-5">
                                <div className="grid gap-5 sm:grid-cols-2">
                                    <div className="space-y-2.5">
                                        <Label htmlFor="app_name" className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">
                                            Application Full Name
                                        </Label>
                                        <Input
                                            id="app_name"
                                            value={brandForm.data.app_name}
                                            onChange={(event) => brandForm.setData("app_name", event.target.value)}
                                            className="bg-background focus:ring-2"
                                            placeholder="e.g. Acme Student Portal"
                                        />
                                    </div>
                                    <div className="space-y-2.5">
                                        <Label
                                            htmlFor="app_short_name"
                                            className="text-muted-foreground text-xs font-semibold tracking-wider uppercase"
                                        >
                                            Application Short Name
                                        </Label>
                                        <Input
                                            id="app_short_name"
                                            value={brandForm.data.app_short_name}
                                            onChange={(event) => brandForm.setData("app_short_name", event.target.value)}
                                            className="bg-background focus:ring-2"
                                            placeholder="e.g. ASP"
                                        />
                                    </div>
                                </div>
                                <div className="space-y-2.5">
                                    <Label htmlFor="tagline" className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">
                                        Tagline
                                    </Label>
                                    <Input
                                        id="tagline"
                                        value={brandForm.data.tagline}
                                        onChange={(event) => brandForm.setData("tagline", event.target.value)}
                                        className="bg-background focus:ring-2"
                                        placeholder="e.g. Your Campus, Your Connection"
                                    />
                                    <p className="text-muted-foreground text-[11px]">Appears in logins, footers, and emails.</p>
                                </div>
                            </CardContent>
                        </Card>

                        {/* Organization & Logistics */}
                        <Card className="border-muted-foreground/10 overflow-hidden shadow-sm">
                            <CardHeader className="bg-muted/10 border-b pb-4">
                                <div className="flex items-center gap-2">
                                    <div className="rounded-md bg-orange-100 p-1.5 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400">
                                        <Briefcase className="h-4 w-4" />
                                    </div>
                                    <div>
                                        <CardTitle className="text-base">Organization Profile</CardTitle>
                                        <CardDescription className="mt-0.5 text-xs">
                                            Physical and corporate identity used for formal documents.
                                        </CardDescription>
                                    </div>
                                </div>
                            </CardHeader>
                            <CardContent className="space-y-5 p-5">
                                <div className="grid gap-5 sm:grid-cols-2">
                                    <div className="space-y-2.5">
                                        <Label
                                            htmlFor="organization_name"
                                            className="text-muted-foreground text-xs font-semibold tracking-wider uppercase"
                                        >
                                            Organization Full Name
                                        </Label>
                                        <Input
                                            id="organization_name"
                                            value={brandForm.data.organization_name}
                                            onChange={(event) => brandForm.setData("organization_name", event.target.value)}
                                            className="bg-background"
                                        />
                                    </div>
                                    <div className="space-y-2.5">
                                        <Label
                                            htmlFor="organization_short_name"
                                            className="text-muted-foreground text-xs font-semibold tracking-wider uppercase"
                                        >
                                            Orgnization Short Name
                                        </Label>
                                        <Input
                                            id="organization_short_name"
                                            value={brandForm.data.organization_short_name}
                                            onChange={(event) => brandForm.setData("organization_short_name", event.target.value)}
                                            className="bg-background"
                                        />
                                    </div>
                                </div>

                                <div className="space-y-2.5">
                                    <Label
                                        htmlFor="organization_address"
                                        className="text-muted-foreground text-xs font-semibold tracking-wider uppercase"
                                    >
                                        Headquarters / Physical Address
                                    </Label>
                                    <Textarea
                                        id="organization_address"
                                        rows={2}
                                        value={brandForm.data.organization_address}
                                        onChange={(event) => brandForm.setData("organization_address", event.target.value)}
                                        className="bg-background resize-none leading-relaxed"
                                    />
                                </div>

                                <div className="grid gap-5 pt-2 sm:grid-cols-2">
                                    <div className="space-y-2.5">
                                        <Label
                                            htmlFor="support_email"
                                            className="text-muted-foreground flex items-center gap-1.5 text-xs font-semibold tracking-wider uppercase"
                                        >
                                            <PhoneCall className="h-3 w-3" /> System Support Email
                                        </Label>
                                        <Input
                                            id="support_email"
                                            type="email"
                                            value={brandForm.data.support_email}
                                            onChange={(event) => brandForm.setData("support_email", event.target.value)}
                                            className="bg-background"
                                        />
                                    </div>
                                    <div className="space-y-2.5">
                                        <Label
                                            htmlFor="support_phone"
                                            className="text-muted-foreground flex items-center gap-1.5 text-xs font-semibold tracking-wider uppercase"
                                        >
                                            <PhoneCall className="h-3 w-3" /> System Support Phone
                                        </Label>
                                        <Input
                                            id="support_phone"
                                            value={brandForm.data.support_phone}
                                            onChange={(event) => brandForm.setData("support_phone", event.target.value)}
                                            className="bg-background"
                                        />
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        {/* Aesthetics */}
                        <Card className="border-muted-foreground/10 overflow-hidden shadow-sm">
                            <CardHeader className="bg-muted/10 border-b pb-4">
                                <div className="flex items-center gap-2">
                                    <div className="rounded-md bg-pink-100 p-1.5 text-pink-700 dark:bg-pink-900/30 dark:text-pink-400">
                                        <Palette className="h-4 w-4" />
                                    </div>
                                    <div>
                                        <CardTitle className="text-base">Aesthetics & Icons</CardTitle>
                                        <CardDescription className="mt-0.5 text-xs">Colors, favicons, and portal branding marks.</CardDescription>
                                    </div>
                                </div>
                            </CardHeader>
                            <CardContent className="space-y-6 p-5">
                                <div className="grid gap-6 sm:grid-cols-2">
                                    <div className="space-y-3">
                                        <Label htmlFor="logo" className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">
                                            Brand Logo
                                        </Label>
                                        <div className="bg-muted/30 group border-muted-foreground/20 hover:border-primary/50 relative flex h-28 items-center justify-center overflow-hidden rounded-xl border-2 border-dashed transition-colors">
                                            <img
                                                src={branding?.logo || "/web-app-manifest-192x192.png"}
                                                alt="Current logo"
                                                className="max-h-full max-w-full object-contain p-4 drop-shadow-sm transition-transform group-hover:scale-105"
                                            />
                                        </div>
                                        <Input
                                            id="logo"
                                            type="file"
                                            accept="image/*"
                                            onChange={(event) => brandForm.setData("logo", event.target.files?.[0] || null)}
                                            className="file:bg-primary/10 file:text-primary hover:file:bg-primary/20 h-9 cursor-pointer text-xs file:mr-4 file:rounded-md file:border-0 file:px-3 file:py-1"
                                        />
                                    </div>
                                    <div className="space-y-3">
                                        <Label htmlFor="favicon" className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">
                                            Browser Favicon
                                        </Label>
                                        <div className="bg-muted/30 group border-muted-foreground/20 hover:border-primary/50 relative flex h-28 items-center justify-center overflow-hidden rounded-xl border-2 border-dashed transition-colors">
                                            <img
                                                src={branding?.favicon || "/web-app-manifest-192x192.png"}
                                                alt="Current favicon"
                                                className="h-16 w-16 object-contain drop-shadow-sm transition-transform group-hover:scale-110"
                                            />
                                        </div>
                                        <Input
                                            id="favicon"
                                            type="file"
                                            accept="image/*"
                                            onChange={(event) => brandForm.setData("favicon", event.target.files?.[0] || null)}
                                            className="file:bg-primary/10 file:text-primary hover:file:bg-primary/20 h-9 cursor-pointer text-xs file:mr-4 file:rounded-md file:border-0 file:px-3 file:py-1"
                                        />
                                    </div>
                                </div>
                                <div className="space-y-2.5">
                                    <Label htmlFor="theme_color" className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">
                                        Primary Theme Color
                                    </Label>
                                    <div className="bg-muted/20 flex items-center gap-3 rounded-lg border p-2">
                                        <div
                                            className="relative h-10 w-10 shrink-0 overflow-hidden rounded-md border shadow-sm"
                                            style={{ backgroundColor: primaryColor }}
                                        >
                                            <input
                                                type="color"
                                                className="absolute inset-[-10px] h-[50px] w-[50px] cursor-pointer opacity-0"
                                                value={primaryColor}
                                                onChange={(event) => brandForm.setData("theme_color", event.target.value)}
                                            />
                                        </div>
                                        <Input
                                            id="theme_color"
                                            value={primaryColor}
                                            onChange={(event) => brandForm.setData("theme_color", event.target.value)}
                                            className="bg-background w-[120px] font-mono text-sm tracking-wider uppercase"
                                        />
                                        <div className="text-muted-foreground hidden pl-2 text-xs sm:block">
                                            Used for auth screens and global accents.
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        {/* Legal & Localization */}
                        <Card className="border-muted-foreground/10 overflow-hidden shadow-sm">
                            <CardHeader className="bg-muted/10 border-b pb-4">
                                <div className="flex items-center gap-2">
                                    <div className="rounded-md bg-emerald-100 p-1.5 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400">
                                        <Scale className="h-4 w-4" />
                                    </div>
                                    <div>
                                        <CardTitle className="text-base">Legal & Localization</CardTitle>
                                        <CardDescription className="mt-0.5 text-xs">Regional formatting and copyright declarations.</CardDescription>
                                    </div>
                                </div>
                            </CardHeader>
                            <CardContent className="space-y-5 p-5">
                                <div className="grid gap-5 sm:grid-cols-2">
                                    <div className="space-y-2.5">
                                        <Label htmlFor="currency" className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">
                                            Primary Currency
                                        </Label>
                                        <Select value={brandForm.data.currency} onValueChange={(value) => brandForm.setData("currency", value)}>
                                            <SelectTrigger id="currency" className="bg-background">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="PHP">PHP ₱ - Philippine Peso</SelectItem>
                                                <SelectItem value="USD">USD $ - US Dollar</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div className="space-y-2.5">
                                        <Label
                                            htmlFor="copyright_text"
                                            className="text-muted-foreground text-xs font-semibold tracking-wider uppercase"
                                        >
                                            Copyright Declaration
                                        </Label>
                                        <Input
                                            id="copyright_text"
                                            value={brandForm.data.copyright_text}
                                            onChange={(event) => brandForm.setData("copyright_text", event.target.value)}
                                            className="bg-background"
                                            placeholder="Rights Reserved."
                                        />
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>

                {/* Right Column: Previews */}
                <div className="w-full shrink-0 space-y-6 xl:w-[380px] 2xl:w-[420px]">
                    <div className="top-[104px] space-y-6 xl:sticky">
                        <Card className="border-muted-foreground/10 overflow-hidden shadow-sm">
                            <CardHeader className="bg-muted/30 border-b px-4 pb-3">
                                <CardTitle className="flex items-center justify-between text-sm font-semibold">
                                    <div className="flex items-center gap-2">
                                        <ImageIcon className="text-muted-foreground h-4 w-4" />
                                        Design Preview
                                    </div>
                                    <Badge variant="outline" className="px-1.5 font-mono text-[10px] font-normal uppercase">
                                        Live View
                                    </Badge>
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="bg-background flex flex-col items-center p-0">
                                {/* Login screen mockup */}
                                <div
                                    className="flex h-40 w-full items-center justify-center p-6 transition-colors duration-500"
                                    style={{ backgroundColor: primaryColor }}
                                >
                                    <div className="relative flex h-full w-full flex-col items-center justify-center overflow-hidden rounded-xl border border-white/20 bg-white/10 p-4 shadow-lg backdrop-blur-sm dark:border-white/10 dark:bg-black/20">
                                        <div className="pointer-events-none absolute top-0 left-0 h-1/2 w-full bg-gradient-to-b from-white/10 to-transparent" />
                                        <div className="z-10 mb-3 flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-white p-1 shadow-sm md:h-12 md:w-12">
                                            <img
                                                src={branding?.logo || "/web-app-manifest-192x192.png"}
                                                alt="Preview logo"
                                                className="max-h-full max-w-full object-contain"
                                            />
                                        </div>
                                        <div className="z-10 w-full space-y-1 text-center">
                                            <h3 className="truncate px-2 text-sm leading-tight font-semibold text-white drop-shadow-md md:text-base">
                                                {brandForm.data.app_name || "School Portal Platform"}
                                            </h3>
                                            <p className="truncate px-4 text-[10px] leading-tight text-white/80 md:text-xs">
                                                {brandForm.data.tagline || "Your Campus, Your Connection"}
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                {/* Header mockup */}
                                <div className="bg-background flex w-full items-center justify-between border-b p-4">
                                    <div className="flex items-center gap-2">
                                        <img src={branding?.favicon || "/web-app-manifest-192x192.png"} className="h-5 w-5 rounded-sm object-contain" />
                                        <span className="max-w-[140px] truncate text-sm font-semibold">
                                            {brandForm.data.app_short_name || brandForm.data.app_name || "Portal"}
                                        </span>
                                    </div>
                                    <div className="flex gap-2">
                                        <div className="bg-muted h-6 w-16 rounded-full" />
                                        <div className="h-6 w-6 rounded-full" style={{ backgroundColor: primaryColor }} />
                                    </div>
                                </div>

                                {/* Footer mockup */}
                                <div className="bg-muted/30 w-full p-4 text-center">
                                    <p className="text-muted-foreground text-[10px] font-medium">
                                        &copy; {new Date().getFullYear()}{" "}
                                        {brandForm.data.copyright_text || brandForm.data.organization_name || "Your Organization"}
                                    </p>
                                    {(brandForm.data.support_email || brandForm.data.support_phone) && (
                                        <p className="text-muted-foreground/80 mt-1 truncate text-[9px]">
                                            Support: {[brandForm.data.support_email, brandForm.data.support_phone].filter(Boolean).join(" • ")}
                                        </p>
                                    )}
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </SystemManagementLayout>
    );
}
