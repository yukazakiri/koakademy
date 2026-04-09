import AdminLayout from "@/components/administrators/admin-layout";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Switch } from "@/components/ui/switch";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Textarea } from "@/components/ui/textarea";
import { User } from "@/types/user";
import { Head, useForm } from "@inertiajs/react";
import { Building2, Calendar, Globe, LayoutDashboard, Mail, Save, Search, ShieldCheck } from "lucide-react";
import { useEffect } from "react";
import { toast } from "sonner";

type GeneralSettings = {
    site_name: string | null;
    site_description: string | null;
    theme_color: string | null;
    support_email: string | null;
    support_phone: string | null;
    school_starting_date: string | null;
    school_ending_date: string | null;
    semester: number | null;
    curriculum_year: string | null;
    school_portal_url: string | null;
    school_portal_enabled: boolean;
    school_portal_maintenance: boolean;
    online_enrollment_enabled: boolean;
    enable_clearance_check: boolean;
    enable_signatures: boolean;
    enable_qr_codes: boolean;
    enable_public_transactions: boolean;
    enable_support_page: boolean;
    inventory_module_enabled: boolean;
    library_module_enabled: boolean;
    seo_title: string | null;
    seo_keywords: string | null;
};

interface SettingsPageProps {
    user: User;
    settings: GeneralSettings;
    flash: {
        success?: string;
        error?: string;
    } | null;
}

export default function AdministratorSettingsIndex({ user, settings, flash }: SettingsPageProps) {
    const { data, setData, patch, processing, errors, wasSuccessful } = useForm<GeneralSettings>({
        site_name: settings.site_name || "",
        site_description: settings.site_description || "",
        theme_color: settings.theme_color || "slate",
        support_email: settings.support_email || "",
        support_phone: settings.support_phone || "",
        school_starting_date: settings.school_starting_date || "",
        school_ending_date: settings.school_ending_date || "",
        semester: settings.semester || 1,
        curriculum_year: settings.curriculum_year || "",
        school_portal_url: settings.school_portal_url || "",
        school_portal_enabled: settings.school_portal_enabled,
        school_portal_maintenance: settings.school_portal_maintenance,
        online_enrollment_enabled: settings.online_enrollment_enabled,
        enable_clearance_check: settings.enable_clearance_check,
        enable_signatures: settings.enable_signatures,
        enable_qr_codes: settings.enable_qr_codes,
        enable_public_transactions: settings.enable_public_transactions,
        enable_support_page: settings.enable_support_page,
        inventory_module_enabled: settings.inventory_module_enabled,
        library_module_enabled: settings.library_module_enabled,
        seo_title: settings.seo_title || "",
        seo_keywords: settings.seo_keywords || "",
    });

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
        if (flash?.error) {
            toast.error(flash.error);
        }
    }, [flash]);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        patch(route("administrators.settings.update"), {
            preserveScroll: true,
            onSuccess: () => {
                // Optional: extra actions on success
            },
        });
    };

    return (
        <AdminLayout user={user} title="Settings">
            <Head title="Administrators • Settings" />

            <div className="space-y-6">
                <div className="flex flex-col gap-2">
                    <h2 className="text-foreground text-3xl font-bold tracking-tight">Global Settings</h2>
                    <p className="text-muted-foreground">Manage application configuration, features, and school details.</p>
                </div>

                <form onSubmit={submit}>
                    <Tabs defaultValue="general" className="w-full space-y-6">
                        <div className="flex items-center justify-between">
                            <TabsList className="grid w-full grid-cols-4 lg:w-[600px]">
                                <TabsTrigger value="general">General</TabsTrigger>
                                <TabsTrigger value="academic">Academic</TabsTrigger>
                                <TabsTrigger value="features">Features</TabsTrigger>
                                <TabsTrigger value="seo">SEO & Metadata</TabsTrigger>
                            </TabsList>
                            <div className="hidden lg:block">
                                <Button type="submit" disabled={processing} className="gap-2">
                                    <Save className="h-4 w-4" />
                                    {processing ? "Saving..." : "Save Changes"}
                                </Button>
                            </div>
                        </div>

                        <TabsContent value="general" className="space-y-4">
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <Globe className="text-primary h-5 w-5" />
                                        Site Information
                                    </CardTitle>
                                    <CardDescription>Basic details about the application and institution.</CardDescription>
                                </CardHeader>
                                <CardContent className="grid gap-6 md:grid-cols-2">
                                    <div className="space-y-2">
                                        <Label htmlFor="site_name">Site Name</Label>
                                        <Input
                                            id="site_name"
                                            value={data.site_name || ""}
                                            onChange={(e) => setData("site_name", e.target.value)}
                                            placeholder="e.g. Student Portal"
                                        />
                                        {errors.site_name && <p className="text-destructive text-sm">{errors.site_name}</p>}
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="school_portal_url">Portal URL</Label>
                                        <Input
                                            id="school_portal_url"
                                            value={data.school_portal_url || ""}
                                            onChange={(e) => setData("school_portal_url", e.target.value)}
                                            placeholder="https://portal.koakademy.edu"
                                        />
                                    </div>
                                    <div className="space-y-2 md:col-span-2">
                                        <Label htmlFor="site_description">Description</Label>
                                        <Textarea
                                            id="site_description"
                                            value={data.site_description || ""}
                                            onChange={(e) => setData("site_description", e.target.value)}
                                            placeholder="A brief description of the site..."
                                        />
                                    </div>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <Mail className="text-primary h-5 w-5" />
                                        Support Contact
                                    </CardTitle>
                                    <CardDescription>Contact information displayed to students and staff.</CardDescription>
                                </CardHeader>
                                <CardContent className="grid gap-6 md:grid-cols-2">
                                    <div className="space-y-2">
                                        <Label htmlFor="support_email">Support Email</Label>
                                        <Input
                                            id="support_email"
                                            type="email"
                                            value={data.support_email || ""}
                                            onChange={(e) => setData("support_email", e.target.value)}
                                            placeholder="support@koakademy.edu"
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="support_phone">Support Phone</Label>
                                        <Input
                                            id="support_phone"
                                            value={data.support_phone || ""}
                                            onChange={(e) => setData("support_phone", e.target.value)}
                                            placeholder="+63 900 000 0000"
                                        />
                                    </div>
                                </CardContent>
                            </Card>
                        </TabsContent>

                        <TabsContent value="academic" className="space-y-4">
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <Calendar className="text-primary h-5 w-5" />
                                        School Calendar
                                    </CardTitle>
                                    <CardDescription>Manage the current academic year and semester settings.</CardDescription>
                                </CardHeader>
                                <CardContent className="grid gap-6 md:grid-cols-2">
                                    <div className="space-y-2">
                                        <Label htmlFor="school_starting_date">School Year Start</Label>
                                        <Input
                                            id="school_starting_date"
                                            type="date"
                                            value={data.school_starting_date || ""}
                                            onChange={(e) => setData("school_starting_date", e.target.value)}
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="school_ending_date">School Year End</Label>
                                        <Input
                                            id="school_ending_date"
                                            type="date"
                                            value={data.school_ending_date || ""}
                                            onChange={(e) => setData("school_ending_date", e.target.value)}
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="semester">Current Semester</Label>
                                        <Select value={String(data.semester)} onValueChange={(val) => setData("semester", parseInt(val))}>
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select Semester" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="1">1st Semester</SelectItem>
                                                <SelectItem value="2">2nd Semester</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="curriculum_year">Curriculum Year</Label>
                                        <Input
                                            id="curriculum_year"
                                            value={data.curriculum_year || ""}
                                            onChange={(e) => setData("curriculum_year", e.target.value)}
                                            placeholder="e.g. 2018-2019"
                                        />
                                    </div>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <Building2 className="text-primary h-5 w-5" />
                                        Enrollment Configuration
                                    </CardTitle>
                                    <CardDescription>Control enrollment availability and validation rules.</CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div className="flex items-center justify-between rounded-lg border p-4">
                                        <div className="space-y-0.5">
                                            <Label className="text-base">Online Enrollment</Label>
                                            <p className="text-muted-foreground text-sm">Allow students to register and enroll online.</p>
                                        </div>
                                        <Switch
                                            checked={data.online_enrollment_enabled}
                                            onCheckedChange={(val) => setData("online_enrollment_enabled", val)}
                                        />
                                    </div>
                                    <div className="flex items-center justify-between rounded-lg border p-4">
                                        <div className="space-y-0.5">
                                            <Label className="text-base">Clearance Check</Label>
                                            <p className="text-muted-foreground text-sm">Require students to be cleared before enrolling.</p>
                                        </div>
                                        <Switch
                                            checked={data.enable_clearance_check}
                                            onCheckedChange={(val) => setData("enable_clearance_check", val)}
                                        />
                                    </div>
                                </CardContent>
                            </Card>
                        </TabsContent>

                        <TabsContent value="features" className="space-y-4">
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <LayoutDashboard className="text-primary h-5 w-5" />
                                        System Modules
                                    </CardTitle>
                                    <CardDescription>Enable or disable specific system modules.</CardDescription>
                                </CardHeader>
                                <CardContent className="grid gap-4 md:grid-cols-2">
                                    <div className="flex items-center space-x-2 rounded-lg border p-4">
                                        <Checkbox
                                            id="inventory"
                                            checked={data.inventory_module_enabled}
                                            onCheckedChange={(val) => setData("inventory_module_enabled", val === true)}
                                        />
                                        <div className="grid gap-1.5 leading-none">
                                            <Label htmlFor="inventory">Inventory System</Label>
                                            <p className="text-muted-foreground text-sm">Manage stocks and supplies.</p>
                                        </div>
                                    </div>
                                    <div className="flex items-center space-x-2 rounded-lg border p-4">
                                        <Checkbox
                                            id="library"
                                            checked={data.library_module_enabled}
                                            onCheckedChange={(val) => setData("library_module_enabled", val === true)}
                                        />
                                        <div className="grid gap-1.5 leading-none">
                                            <Label htmlFor="library">Library System</Label>
                                            <p className="text-muted-foreground text-sm">Book tracking and borrowing.</p>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <ShieldCheck className="text-primary h-5 w-5" />
                                        Security & Access
                                    </CardTitle>
                                    <CardDescription>Manage public access and verification features.</CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div className="flex items-center justify-between rounded-lg border p-4">
                                        <div className="space-y-0.5">
                                            <Label className="text-base">Public Transactions</Label>
                                            <p className="text-muted-foreground text-sm">Allow public users to verify transactions.</p>
                                        </div>
                                        <Switch
                                            checked={data.enable_public_transactions}
                                            onCheckedChange={(val) => setData("enable_public_transactions", val)}
                                        />
                                    </div>
                                    <div className="flex items-center justify-between rounded-lg border p-4">
                                        <div className="space-y-0.5">
                                            <Label className="text-base">Support Page</Label>
                                            <p className="text-muted-foreground text-sm">Enable the public support/helpdesk page.</p>
                                        </div>
                                        <Switch checked={data.enable_support_page} onCheckedChange={(val) => setData("enable_support_page", val)} />
                                    </div>
                                    <div className="flex items-center justify-between rounded-lg border p-4">
                                        <div className="space-y-0.5">
                                            <Label className="text-base">Digital Signatures</Label>
                                            <p className="text-muted-foreground text-sm">Enable digital signatures on generated documents.</p>
                                        </div>
                                        <Switch checked={data.enable_signatures} onCheckedChange={(val) => setData("enable_signatures", val)} />
                                    </div>
                                    <div className="flex items-center justify-between rounded-lg border p-4">
                                        <div className="space-y-0.5">
                                            <Label className="text-base">QR Codes</Label>
                                            <p className="text-muted-foreground text-sm">Generate QR codes for student IDs and documents.</p>
                                        </div>
                                        <Switch checked={data.enable_qr_codes} onCheckedChange={(val) => setData("enable_qr_codes", val)} />
                                    </div>
                                </CardContent>
                            </Card>
                        </TabsContent>

                        <TabsContent value="seo" className="space-y-4">
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <Search className="text-primary h-5 w-5" />
                                        SEO Settings
                                    </CardTitle>
                                    <CardDescription>Configure search engine optimization for public pages.</CardDescription>
                                </CardHeader>
                                <CardContent className="grid gap-6">
                                    <div className="space-y-2">
                                        <Label htmlFor="seo_title">SEO Title</Label>
                                        <Input
                                            id="seo_title"
                                            value={data.seo_title || ""}
                                            onChange={(e) => setData("seo_title", e.target.value)}
                                            placeholder="Default title for public pages"
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="seo_keywords">SEO Keywords</Label>
                                        <Textarea
                                            id="seo_keywords"
                                            value={data.seo_keywords || ""}
                                            onChange={(e) => setData("seo_keywords", e.target.value)}
                                            placeholder="school, enrollment, education, dccp"
                                        />
                                        <p className="text-muted-foreground text-xs">Comma-separated list of keywords.</p>
                                    </div>
                                </CardContent>
                            </Card>
                        </TabsContent>
                    </Tabs>

                    <div className="mt-6 flex justify-end lg:hidden">
                        <Button type="submit" disabled={processing} className="w-full gap-2">
                            <Save className="h-4 w-4" />
                            {processing ? "Saving Changes..." : "Save Changes"}
                        </Button>
                    </div>
                </form>
            </div>
        </AdminLayout>
    );
}
