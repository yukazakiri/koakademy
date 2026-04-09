import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from "@/components/ui/accordion";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { ScrollArea } from "@/components/ui/scroll-area";
import { Separator } from "@/components/ui/separator";
import { Switch } from "@/components/ui/switch";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Textarea } from "@/components/ui/textarea";
import { useForm } from "@inertiajs/react";
import { AlertCircle, BookOpen, Globe, Loader2, Mail, Palette, Phone, Save, School, Settings2, Sparkles, Webhook } from "lucide-react";
import * as React from "react";

import { submitSystemForm } from "./form-submit";
import SystemManagementLayout from "./layout";
import type { PublicApiFieldDefinition, SocialNetworkSettings, SystemManagementPageProps } from "./types";

type EditableFieldKey =
    | "site_name"
    | "site_description"
    | "theme_color"
    | "support_email"
    | "support_phone"
    | "school_portal_url"
    | "school_portal_title"
    | "school_portal_description";

interface ApiManagementFormData {
    public_api_enabled: boolean;
    public_settings_enabled: boolean;
    public_settings_fields: string[];
    site_name: string;
    site_description: string;
    theme_color: string;
    support_email: string;
    support_phone: string;
    social_network: SocialNetworkSettings;
    school_portal_url: string;
    school_portal_enabled: boolean;
    online_enrollment_enabled: boolean;
    school_portal_maintenance: boolean;
    school_portal_title: string;
    school_portal_description: string;
}

interface EditableFieldConfig {
    key: EditableFieldKey;
    icon: React.ElementType;
    helper: string;
}

const IDENTITY_FIELDS: EditableFieldConfig[] = [
    { key: "site_name", icon: Sparkles, helper: "Main public-facing website title." },
    { key: "site_description", icon: BookOpen, helper: "Short description used in hero sections and metadata." },
    { key: "theme_color", icon: Palette, helper: "Brand accent color stored as a string in the model." },
];

const CONTACT_FIELDS: EditableFieldConfig[] = [
    { key: "support_email", icon: Mail, helper: "Public contact email for support and inquiries." },
    { key: "support_phone", icon: Phone, helper: "Public support phone number." },
];

const PORTAL_FIELDS: EditableFieldConfig[] = [
    { key: "school_portal_url", icon: Globe, helper: "Student portal or school portal URL." },
    { key: "school_portal_title", icon: School, helper: "Portal CTA title shown on the website." },
    { key: "school_portal_description", icon: BookOpen, helper: "Short supporting copy for the portal CTA." },
];

const FIELD_GROUPS = [
    {
        value: "identity",
        title: "Identity",
        description: "Branding and descriptive copy.",
        fields: ["site_name", "site_description", "theme_color"],
    },
    {
        value: "contact",
        title: "Contact",
        description: "Support channels visible to visitors.",
        fields: ["support_email", "support_phone", "social_network"],
    },
    {
        value: "portal",
        title: "Portal",
        description: "Portal URL, labels, and public status flags.",
        fields: [
            "school_portal_url",
            "school_portal_enabled",
            "online_enrollment_enabled",
            "school_portal_maintenance",
            "school_portal_title",
            "school_portal_description",
        ],
    },
    {
        value: "computed",
        title: "Computed",
        description: "Read-only values derived by the app.",
        fields: ["school_year_string", "semester_name"],
    },
];

const SOCIAL_LINK_FIELDS: Array<{ key: keyof SocialNetworkSettings; label: string; placeholder: string }> = [
    { key: "facebook", label: "Facebook", placeholder: "https://facebook.com/your-page" },
    { key: "instagram", label: "Instagram", placeholder: "https://instagram.com/your-page" },
    { key: "twitter", label: "Twitter / X", placeholder: "https://x.com/your-page" },
    { key: "linkedin", label: "LinkedIn", placeholder: "https://linkedin.com/company/your-page" },
    { key: "youtube", label: "YouTube", placeholder: "https://youtube.com/@your-channel" },
    { key: "tiktok", label: "TikTok", placeholder: "https://tiktok.com/@your-page" },
];

function getPreviewValue(fieldKey: string, formData: ApiManagementFormData, generalSettings: SystemManagementPageProps["general_settings"]): unknown {
    return {
        site_name: formData.site_name || null,
        site_description: formData.site_description || null,
        theme_color: formData.theme_color || null,
        support_email: formData.support_email || null,
        support_phone: formData.support_phone || null,
        social_network: Object.fromEntries(Object.entries(formData.social_network).filter(([, value]) => Boolean(value))),
        school_portal_url: formData.school_portal_url || null,
        school_portal_enabled: formData.school_portal_enabled,
        online_enrollment_enabled: formData.online_enrollment_enabled,
        school_portal_maintenance: formData.school_portal_maintenance,
        school_portal_title: formData.school_portal_title || null,
        school_portal_description: formData.school_portal_description || null,
        school_year_string: generalSettings.school_year_string ?? null,
        semester_name: generalSettings.semester_name ?? null,
    }[fieldKey];
}

function ValueField({
    field,
    definition,
    form,
}: {
    field: EditableFieldConfig;
    definition: PublicApiFieldDefinition;
    form: ReturnType<typeof useForm<ApiManagementFormData>>;
}) {
    const Icon = field.icon;
    const isTextarea = definition.input === "textarea";
    const isColor = field.key === "theme_color";

    return (
        <div className="bg-background rounded-2xl border p-5">
            <div className="mb-4 flex items-start gap-3">
                <div className="bg-muted text-muted-foreground rounded-xl p-2">
                    <Icon className="h-4 w-4" />
                </div>
                <div className="min-w-0">
                    <div className="flex items-center gap-2">
                        <Label htmlFor={field.key} className="font-medium">
                            {definition.label}
                        </Label>
                        <code className="bg-muted rounded px-1.5 py-0.5 text-[11px]">{field.key}</code>
                    </div>
                    <p className="text-muted-foreground mt-1 text-sm">{field.helper}</p>
                </div>
            </div>

            {isColor ? (
                <div className="flex items-center gap-3">
                    <Input
                        id={`${field.key}-picker`}
                        type="color"
                        value={form.data.theme_color || "#0f172a"}
                        onChange={(event) => form.setData("theme_color", event.target.value)}
                        className="h-11 w-16 rounded-xl p-1"
                    />
                    <Input
                        id={field.key}
                        value={form.data.theme_color}
                        onChange={(event) => form.setData("theme_color", event.target.value)}
                        placeholder="#0f172a"
                    />
                </div>
            ) : isTextarea ? (
                <Textarea
                    id={field.key}
                    value={String(form.data[field.key] ?? "")}
                    onChange={(event) => form.setData(field.key, event.target.value)}
                    rows={4}
                />
            ) : (
                <Input
                    id={field.key}
                    type={definition.input}
                    value={String(form.data[field.key] ?? "")}
                    onChange={(event) => form.setData(field.key, event.target.value)}
                />
            )}
        </div>
    );
}

export default function SystemManagementApiPage({
    user,
    api_management,
    public_api_fields,
    public_api_url,
    general_settings,
    access,
}: SystemManagementPageProps) {
    const form = useForm<ApiManagementFormData>({
        public_api_enabled: api_management?.public_api_enabled ?? true,
        public_settings_enabled: api_management?.public_settings_enabled ?? true,
        public_settings_fields: api_management?.public_settings_fields ?? [],
        site_name: general_settings.site_name ?? "",
        site_description: general_settings.site_description ?? "",
        theme_color: general_settings.theme_color ?? "#0f172a",
        support_email: general_settings.support_email ?? "",
        support_phone: general_settings.support_phone ?? "",
        social_network: general_settings.social_network ?? {},
        school_portal_url: general_settings.school_portal_url ?? "",
        school_portal_enabled: general_settings.school_portal_enabled ?? false,
        online_enrollment_enabled: general_settings.online_enrollment_enabled ?? false,
        school_portal_maintenance: general_settings.school_portal_maintenance ?? false,
        school_portal_title: general_settings.school_portal_title ?? "",
        school_portal_description: general_settings.school_portal_description ?? "",
    });

    const toggleField = (fieldKey: string, checked: boolean) => {
        const nextFields = checked
            ? [...form.data.public_settings_fields, fieldKey]
            : form.data.public_settings_fields.filter((field) => field !== fieldKey);

        form.setData("public_settings_fields", Array.from(new Set(nextFields)));
    };

    const updateSocialLink = (key: keyof SocialNetworkSettings, value: string) => {
        form.setData("social_network", {
            ...form.data.social_network,
            [key]: value || null,
        });
    };

    const responseData = React.useMemo(
        () =>
            Object.fromEntries(
                form.data.public_settings_fields.map((fieldKey) => [fieldKey, getPreviewValue(fieldKey, form.data, general_settings)]),
            ),
        [form.data, general_settings],
    );

    const responseExample = React.useMemo(
        () =>
            JSON.stringify(
                {
                    message: "Public website settings retrieved successfully",
                    data: responseData,
                },
                null,
                2,
            ),
        [responseData],
    );

    const selectedFieldCount = form.data.public_settings_fields.length;
    const publicApiReady = form.data.public_api_enabled && form.data.public_settings_enabled && selectedFieldCount > 0;

    return (
        <SystemManagementLayout
            user={user}
            access={access}
            activeSection="api"
            heading="API Management"
            description="Configure the public settings API using native shadcn components, with a workspace layout that separates exposure rules, content values, and response output."
        >
            <div className="grid gap-6 lg:grid-cols-[280px_minmax(0,1fr)]">
                <Card className="border-border/70 h-fit lg:sticky lg:top-6">
                    <CardHeader className="space-y-4">
                        <div className="space-y-2">
                            <div className="inline-flex items-center gap-2 rounded-full border px-3 py-1 text-xs font-medium">
                                <Webhook className="h-3.5 w-3.5" />
                                Public Endpoint
                            </div>
                            <CardTitle className="text-xl">API Workspace</CardTitle>
                            <CardDescription>
                                Use the sections below to control access, edit values, and review the response contract.
                            </CardDescription>
                        </div>

                        <Alert>
                            <Settings2 className="h-4 w-4" />
                            <AlertTitle>{publicApiReady ? "Configured" : "Needs attention"}</AlertTitle>
                            <AlertDescription>
                                {publicApiReady
                                    ? `${selectedFieldCount} field(s) are currently included in the public response.`
                                    : "Enable the endpoint and choose at least one field to expose."}
                            </AlertDescription>
                        </Alert>
                    </CardHeader>

                    <CardContent className="space-y-4">
                        <div className="space-y-2">
                            <Label htmlFor="public_api_url">Endpoint</Label>
                            <Input id="public_api_url" value={public_api_url} readOnly className="font-mono text-xs" />
                        </div>

                        <div className="flex flex-wrap gap-2">
                            <Badge variant={publicApiReady ? "default" : "secondary"}>{publicApiReady ? "Ready" : "Incomplete"}</Badge>
                            <Badge variant="outline">{selectedFieldCount} selected</Badge>
                        </div>

                        <Separator />

                        <Button
                            onClick={() =>
                                submitSystemForm({
                                    form,
                                    routeName: "administrators.system-management.api.update",
                                    successMessage: "API management settings updated successfully.",
                                    errorMessage: "Failed to update API management settings.",
                                })
                            }
                            disabled={form.processing}
                            className="w-full"
                        >
                            {form.processing ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <Save className="mr-2 h-4 w-4" />}
                            Save Changes
                        </Button>
                    </CardContent>
                </Card>

                <Tabs defaultValue="exposure" orientation="vertical" className="min-w-0 gap-6">
                    <TabsList
                        variant="underline"
                        className="w-full justify-start gap-1 overflow-x-auto rounded-none border-b bg-transparent p-0 text-sm lg:w-52 lg:flex-col lg:items-stretch lg:border-r lg:border-b-0 lg:pr-4"
                    >
                        <TabsTrigger value="exposure" className="justify-start rounded-none px-3 py-2 lg:w-full">
                            Exposure Rules
                        </TabsTrigger>
                        <TabsTrigger value="values" className="justify-start rounded-none px-3 py-2 lg:w-full">
                            Website Values
                        </TabsTrigger>
                        <TabsTrigger value="social" className="justify-start rounded-none px-3 py-2 lg:w-full">
                            Social Links
                        </TabsTrigger>
                        <TabsTrigger value="response" className="justify-start rounded-none px-3 py-2 lg:w-full">
                            Response Contract
                        </TabsTrigger>
                    </TabsList>

                    <TabsContent value="exposure" className="min-w-0">
                        <Card>
                            <CardHeader>
                                <CardTitle>Exposure Rules</CardTitle>
                                <CardDescription>Choose what is public before editing what the website receives.</CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-6">
                                <div className="grid gap-4 md:grid-cols-2">
                                    <div className="rounded-2xl border p-5">
                                        <div className="flex items-center justify-between gap-4">
                                            <div>
                                                <Label className="font-medium">Enable public API group</Label>
                                                <p className="text-muted-foreground mt-1 text-sm">Master switch for all public API endpoints.</p>
                                            </div>
                                            <Switch
                                                checked={form.data.public_api_enabled}
                                                onCheckedChange={(checked) => form.setData("public_api_enabled", checked)}
                                            />
                                        </div>
                                    </div>

                                    <div className="rounded-2xl border p-5">
                                        <div className="flex items-center justify-between gap-4">
                                            <div>
                                                <Label className="font-medium">Enable settings endpoint</Label>
                                                <p className="text-muted-foreground mt-1 text-sm">Controls `GET /api/v1/public/settings`.</p>
                                            </div>
                                            <Switch
                                                checked={form.data.public_settings_enabled}
                                                onCheckedChange={(checked) => form.setData("public_settings_enabled", checked)}
                                            />
                                        </div>
                                    </div>
                                </div>

                                <Accordion type="multiple" defaultValue={["identity", "contact", "portal"]} className="rounded-2xl border px-5">
                                    {FIELD_GROUPS.map((group) => (
                                        <AccordionItem key={group.value} value={group.value}>
                                            <AccordionTrigger className="hover:no-underline">
                                                <div>
                                                    <div className="font-medium">{group.title}</div>
                                                    <div className="text-muted-foreground mt-1 text-sm">{group.description}</div>
                                                </div>
                                            </AccordionTrigger>
                                            <AccordionContent>
                                                <div className="grid gap-3 md:grid-cols-2">
                                                    {group.fields.map((fieldKey) => {
                                                        const definition = public_api_fields[fieldKey];
                                                        const checked = form.data.public_settings_fields.includes(fieldKey);

                                                        return (
                                                            <label
                                                                key={fieldKey}
                                                                className="hover:bg-muted/40 flex items-start gap-3 rounded-xl border p-4 transition-colors"
                                                            >
                                                                <Checkbox
                                                                    checked={checked}
                                                                    onCheckedChange={(value) => toggleField(fieldKey, value === true)}
                                                                />
                                                                <div className="space-y-1">
                                                                    <div className="flex flex-wrap items-center gap-2">
                                                                        <span className="text-sm font-medium">{definition.label}</span>
                                                                        <Badge variant="outline">
                                                                            {definition.editable ? "Editable" : "Computed"}
                                                                        </Badge>
                                                                    </div>
                                                                    <p className="text-muted-foreground text-sm">{definition.description}</p>
                                                                    <code className="text-muted-foreground text-xs">{fieldKey}</code>
                                                                </div>
                                                            </label>
                                                        );
                                                    })}
                                                </div>
                                            </AccordionContent>
                                        </AccordionItem>
                                    ))}
                                </Accordion>
                            </CardContent>
                        </Card>
                    </TabsContent>

                    <TabsContent value="values" className="min-w-0">
                        <ScrollArea className="h-[75vh] rounded-2xl border">
                            <div className="space-y-6 p-6">
                                <div className="space-y-1">
                                    <h2 className="text-xl font-semibold">Website Values</h2>
                                    <p className="text-muted-foreground text-sm">Proper form fields based on the model schema and casts.</p>
                                </div>

                                <div className="grid gap-4 xl:grid-cols-2">
                                    {IDENTITY_FIELDS.map((field) => (
                                        <ValueField key={field.key} field={field} definition={public_api_fields[field.key]} form={form} />
                                    ))}
                                    {CONTACT_FIELDS.map((field) => (
                                        <ValueField key={field.key} field={field} definition={public_api_fields[field.key]} form={form} />
                                    ))}
                                    {PORTAL_FIELDS.map((field) => (
                                        <ValueField key={field.key} field={field} definition={public_api_fields[field.key]} form={form} />
                                    ))}
                                </div>

                                <div className="grid gap-4 md:grid-cols-3">
                                    <div className="rounded-2xl border p-5">
                                        <div className="flex items-center justify-between gap-3">
                                            <div>
                                                <Label className="font-medium">Portal enabled</Label>
                                                <p className="text-muted-foreground mt-1 text-sm">Boolean cast on the model.</p>
                                            </div>
                                            <Switch
                                                checked={form.data.school_portal_enabled}
                                                onCheckedChange={(checked) => form.setData("school_portal_enabled", checked)}
                                            />
                                        </div>
                                    </div>
                                    <div className="rounded-2xl border p-5">
                                        <div className="flex items-center justify-between gap-3">
                                            <div>
                                                <Label className="font-medium">Online enrollment</Label>
                                                <p className="text-muted-foreground mt-1 text-sm">Boolean cast on the model.</p>
                                            </div>
                                            <Switch
                                                checked={form.data.online_enrollment_enabled}
                                                onCheckedChange={(checked) => form.setData("online_enrollment_enabled", checked)}
                                            />
                                        </div>
                                    </div>
                                    <div className="rounded-2xl border p-5">
                                        <div className="flex items-center justify-between gap-3">
                                            <div>
                                                <Label className="font-medium">Portal maintenance</Label>
                                                <p className="text-muted-foreground mt-1 text-sm">Boolean cast on the model.</p>
                                            </div>
                                            <Switch
                                                checked={form.data.school_portal_maintenance}
                                                onCheckedChange={(checked) => form.setData("school_portal_maintenance", checked)}
                                            />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </ScrollArea>
                    </TabsContent>

                    <TabsContent value="social" className="min-w-0">
                        <Card>
                            <CardHeader>
                                <CardTitle>Social Links</CardTitle>
                                <CardDescription>
                                    `social_network` is an array cast, so this section uses structured URL fields instead of a raw JSON editor.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-4 xl:grid-cols-2">
                                {SOCIAL_LINK_FIELDS.map((field) => (
                                    <div key={field.key} className="rounded-2xl border p-5">
                                        <Label htmlFor={`social-${field.key}`} className="font-medium">
                                            {field.label}
                                        </Label>
                                        <Input
                                            id={`social-${field.key}`}
                                            type="url"
                                            value={form.data.social_network[field.key] ?? ""}
                                            onChange={(event) => updateSocialLink(field.key, event.target.value)}
                                            placeholder={field.placeholder}
                                            className="mt-3"
                                        />
                                    </div>
                                ))}
                            </CardContent>
                        </Card>
                    </TabsContent>

                    <TabsContent value="response" className="min-w-0 space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>Response Contract</CardTitle>
                                <CardDescription>Selected fields on the left, exact JSON on the right.</CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-6 xl:grid-cols-[minmax(0,0.95fr)_minmax(0,1.05fr)]">
                                <div className="rounded-2xl border">
                                    <Table>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead>Field</TableHead>
                                                <TableHead>Type</TableHead>
                                                <TableHead>Visibility</TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {form.data.public_settings_fields.length > 0 ? (
                                                form.data.public_settings_fields.map((fieldKey) => (
                                                    <TableRow key={fieldKey}>
                                                        <TableCell className="font-medium">{public_api_fields[fieldKey].label}</TableCell>
                                                        <TableCell>
                                                            <code className="text-xs">{public_api_fields[fieldKey].input}</code>
                                                        </TableCell>
                                                        <TableCell>
                                                            <Badge variant="outline">
                                                                {public_api_fields[fieldKey].editable ? "Editable" : "Computed"}
                                                            </Badge>
                                                        </TableCell>
                                                    </TableRow>
                                                ))
                                            ) : (
                                                <TableRow>
                                                    <TableCell colSpan={3} className="text-muted-foreground h-24 text-center">
                                                        No fields selected yet.
                                                    </TableCell>
                                                </TableRow>
                                            )}
                                        </TableBody>
                                    </Table>
                                </div>

                                <div className="space-y-3">
                                    {!publicApiReady ? (
                                        <Alert>
                                            <AlertCircle className="h-4 w-4" />
                                            <AlertTitle>Preview incomplete</AlertTitle>
                                            <AlertDescription>
                                                Enable the endpoint and choose at least one field to generate a usable response example.
                                            </AlertDescription>
                                        </Alert>
                                    ) : null}

                                    <pre className="overflow-x-auto rounded-2xl bg-slate-950 p-5 text-xs leading-6 text-slate-100">
                                        {responseExample}
                                    </pre>
                                </div>
                            </CardContent>
                        </Card>
                    </TabsContent>
                </Tabs>
            </div>
        </SystemManagementLayout>
    );
}
