import AdminLayout from "@/components/administrators/admin-layout";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Checkbox } from "@/components/ui/checkbox";
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { ScrollArea } from "@/components/ui/scroll-area";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Separator } from "@/components/ui/separator";
import { Switch } from "@/components/ui/switch";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Textarea } from "@/components/ui/textarea";
import { Head, useForm, usePage } from "@inertiajs/react";
import {
    AlertTriangle,
    Banknote,
    Bell,
    BookOpen,
    Calendar,
    Clock,
    Eye,
    GraduationCap,
    Info,
    Mail,
    MapPin,
    Pencil,
    Send,
    Sparkles,
    Trash2,
    Trophy,
    User,
} from "lucide-react";
import { FormEventHandler, useMemo, useState } from "react";
import { toast } from "sonner";
import { route } from "ziggy-js";

interface Action {
    label: string;
    url: string;
    color: string;
    shouldOpenInNewTab: boolean;
}

interface Template {
    slug: string;
    name: string;
    description: string;
    category: string;
    variables: string[];
    default_channels: string[];
    styles: Record<string, string>;
}

interface Props {
    auth: any;
    templates: Template[];
    templatesByCategory: Record<string, Template[]>;
}

const categoryIcons: Record<string, any> = {
    informational: Info,
    celebration: Trophy,
    formal: GraduationCap,
    alert: AlertTriangle,
    reminder: Clock,
    academic: BookOpen,
    events: Sparkles,
    finance: Banknote,
};

const categoryColors: Record<string, string> = {
    informational: "bg-blue-500",
    celebration: "bg-purple-500",
    formal: "bg-slate-700",
    alert: "bg-amber-500",
    reminder: "bg-orange-400",
    academic: "bg-emerald-500",
    events: "bg-pink-500",
    finance: "bg-indigo-500",
};

const variableIcons: Record<string, any> = {
    title: Pencil,
    subtitle: Pencil,
    content: Pencil,
    action_url: Eye,
    action_text: Pencil,
    metadata: Info,
    recipient_name: User,
    reference_number: Pencil,
    details: Info,
    badge: Trophy,
    achievement: Trophy,
    stats: Info,
    priority: AlertTriangle,
    alert_code: AlertTriangle,
    action_items: Info,
    deadline: Calendar,
    due_date: Calendar,
    reminder_items: Info,
    suspension_date: Calendar,
    affected_levels: GraduationCap,
    reason_details: Info,
    resumption_info: Info,
    instructions: Info,
    issued_by: User,
    tagline: Pencil,
    event_type: Sparkles,
    event_date: Calendar,
    event_time: Clock,
    venue: MapPin,
    activities: Sparkles,
    schedule: Clock,
    participants: User,
    requirements: Info,
    organizer: User,
    school_year: Calendar,
    period_name: Pencil,
    important_dates: Calendar,
    deadlines: Calendar,
    notes: Info,
    department: GraduationCap,
    amount: Banknote,
    currency: Banknote,
    student_info: User,
    days_remaining: Clock,
    balance_breakdown: Info,
    payment_methods: Info,
    penalty_info: AlertTriangle,
    student_name: User,
    student_id: GraduationCap,
    course: BookOpen,
    year_level: GraduationCap,
    section: GraduationCap,
    semester: Calendar,
    subjects: BookOpen,
    next_steps: Info,
    assessment_url: Eye,
    grades: BookOpen,
    gwa: Trophy,
    subjects_passed: BookOpen,
    units_earned: BookOpen,
    academic_standing: Trophy,
    holiday_date: Calendar,
    holiday_day: Calendar,
    holiday_type: Sparkles,
    duration: Clock,
    resume_date: Calendar,
    reminders: Bell,
};

const variableLabels: Record<string, string> = {
    title: "Title",
    subtitle: "Subtitle",
    content: "Message Content",
    action_url: "Action URL",
    action_text: "Button Text",
    metadata: "Additional Metadata",
    recipient_name: "Recipient Name",
    reference_number: "Reference Number",
    details: "Details",
    badge: "Badge Text",
    achievement: "Achievement",
    stats: "Statistics",
    priority: "Priority Level",
    alert_code: "Alert Code",
    action_items: "Action Items",
    deadline: "Deadline",
    due_date: "Due Date",
    reminder_items: "Reminder Items",
    suspension_date: "Suspension Date",
    affected_levels: "Affected Levels",
    reason_details: "Reason/Details",
    resumption_info: "Resumption Info",
    instructions: "Instructions",
    issued_by: "Issued By",
    tagline: "Tagline",
    event_type: "Event Type",
    event_date: "Event Date",
    event_time: "Event Time",
    venue: "Venue",
    activities: "Activities",
    schedule: "Schedule",
    participants: "Participants",
    requirements: "Requirements",
    organizer: "Organizer",
    school_year: "School Year",
    period_name: "Period Name",
    important_dates: "Important Dates",
    deadlines: "Deadlines",
    notes: "Notes",
    department: "Department",
    amount: "Amount",
    currency: "Currency",
    student_info: "Student Information",
    days_remaining: "Days Remaining",
    balance_breakdown: "Balance Breakdown",
    payment_methods: "Payment Methods",
    penalty_info: "Penalty Information",
    student_name: "Student Name",
    student_id: "Student ID",
    course: "Course",
    year_level: "Year Level",
    section: "Section",
    semester: "Semester",
    subjects: "Subjects",
    next_steps: "Next Steps",
    assessment_url: "Assessment URL",
    grades: "Grades",
    gwa: "GWA",
    subjects_passed: "Subjects Passed",
    units_earned: "Units Earned",
    academic_standing: "Academic Standing",
    holiday_date: "Holiday Date",
    holiday_day: "Day of Week",
    holiday_type: "Holiday Type",
    duration: "Duration",
    resume_date: "Resume Date",
    reminders: "Reminders",
};

const variableTypes: Record<string, string> = {
    content: "textarea",
    details: "textarea",
    action_items: "textarea",
    reminder_items: "textarea",
    reason_details: "textarea",
    resumption_info: "textarea",
    instructions: "textarea",
    activities: "textarea",
    schedule: "textarea",
    requirements: "textarea",
    important_dates: "textarea",
    deadlines: "textarea",
    notes: "textarea",
    balance_breakdown: "textarea",
    payment_methods: "textarea",
    penalty_info: "textarea",
    subjects: "textarea",
    next_steps: "textarea",
    grades: "textarea",
    reminders: "textarea",
    metadata: "textarea",
    student_info: "textarea",
    action_url: "url",
    assessment_url: "url",
};

export default function Index() {
    const pageProps = usePage().props as unknown as Props;
    const auth = pageProps.auth;
    const templates = Array.isArray(pageProps.templates) ? pageProps.templates : [];
    const templatesByCategory = pageProps.templatesByCategory || {};
    const [selectedTemplate, setSelectedTemplate] = useState<Template | null>(null);
    const [activeTab, setActiveTab] = useState("compose");

    const [isPreviewOpen, setIsPreviewOpen] = useState(false);
    const [previewLoading, setPreviewLoading] = useState(false);
    const [previewData, setPreviewData] = useState<{ email?: string | null; database?: any } | null>(null);

    const { data, setData, post, processing, reset, errors, setError, clearErrors } = useForm({
        target_audience: [] as string[],
        channels: ["database"] as string[],
        title: "",
        content: "",
        type: "info",
        icon: "bell",
        actions: [] as Action[],
        template_slug: null as string | null,
        template_data: {} as Record<string, string>,
    });

    const targetOptions = [
        { id: "all", label: "Everyone (Students, Faculty, Staff)" },
        { id: "all_students", label: "All Students" },
        { id: "all_faculty", label: "All Faculty" },
        { id: "admin", label: "Administrators" },
        { id: "dean", label: "Deans" },
    ];

    const templateVariables = useMemo(() => {
        if (!selectedTemplate || !Array.isArray(selectedTemplate.variables)) return [];
        return selectedTemplate.variables.filter((v) => !["title", "content"].includes(v));
    }, [selectedTemplate]);

    const handleTemplateSelect = (slug: string) => {
        const template = templates.find((t) => t.slug === slug);
        if (!template) return;

        const typeMap: Record<string, string> = {
            "#e94560": "danger",
            "#667eea": "success",
            "#1a365d": "info",
            "#f59e0b": "warning",
            "#fcb69f": "warning",
            "#7c3aed": "info",
            "#1e3a5f": "info",
            "#059669": "success",
            "#1d4ed8": "info",
        };

        const initialTemplateData: Record<string, string> = {};
        if (Array.isArray(template.variables)) {
            template.variables.forEach((v) => {
                initialTemplateData[v] = "";
            });
        }

        // Update all form fields in a single state update to avoid multiple
        // re-renders that trigger Radix UI's internal composeRefs setState loop.
        setData((prev) => ({
            ...prev,
            template_slug: slug,
            channels: Array.isArray(template.default_channels) ? template.default_channels : ["database"],
            type: template.styles?.primary_color ? typeMap[template.styles.primary_color] || "info" : prev.type,
            template_data: initialTemplateData,
        }));

        setSelectedTemplate(template);
    };

    const handleSubmit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route("administrators.notifications.store"), {
            onSuccess: () => {
                toast.success("Notifications are being dispatched in the background!");
                reset();
                setSelectedTemplate(null);
            },
            onError: () => {
                toast.error("Failed to send notifications. Please check the form errors.");
            },
        });
    };

    const addAction = () => {
        setData("actions", [...data.actions, { label: "", url: "", color: "primary", shouldOpenInNewTab: false }]);
    };

    const removeAction = (index: number) => {
        const newActions = [...data.actions];
        newActions.splice(index, 1);
        setData("actions", newActions);
    };

    const updateAction = (index: number, field: keyof Action, value: any) => {
        const newActions = [...data.actions];
        newActions[index] = { ...newActions[index], [field]: value };
        setData("actions", newActions);
    };

    const toggleTarget = (checked: boolean, targetId: string) => {
        if (targetId === "all") {
            setData("target_audience", checked ? ["all"] : []);
        } else {
            let newTargets = [...data.target_audience].filter((t) => t !== "all");
            if (checked) {
                newTargets.push(targetId);
            } else {
                newTargets = newTargets.filter((t) => t !== targetId);
            }
            setData("target_audience", newTargets);
        }
    };

    const toggleChannel = (checked: boolean, channel: string) => {
        if (checked) {
            setData("channels", [...data.channels, channel]);
        } else {
            setData(
                "channels",
                data.channels.filter((c) => c !== channel),
            );
        }
    };

    const updateTemplateData = (field: string, value: string) => {
        setData("template_data", { ...data.template_data, [field]: value });
    };

    const handlePreview = async () => {
        setPreviewLoading(true);
        setIsPreviewOpen(true);
        clearErrors();

        try {
            const response = await window.axios.post(route("administrators.notifications.preview"), data);
            setPreviewData(response.data);
        } catch (error: any) {
            console.error(error);
            if (error.response?.status === 422) {
                const validationErrors = error.response.data.errors;
                for (const key in validationErrors) {
                    setError(key as any, validationErrors[key][0]);
                }
                toast.error("Please fill in the required fields before previewing.");
            } else {
                toast.error("Failed to generate preview. Check form fields.");
            }
            setIsPreviewOpen(false);
        } finally {
            setPreviewLoading(false);
        }
    };

    return (
        <AdminLayout user={auth.user} title="Notifications Center">
            <Head title="Notifications Center" />

            <div className="mx-auto max-w-6xl space-y-6 p-4 md:p-6 lg:p-8">
                <div className="flex items-center gap-3">
                    <div className="bg-primary/10 text-primary flex h-10 w-10 shrink-0 items-center justify-center rounded-lg">
                        <Bell className="h-5 w-5" />
                    </div>
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">Notifications Center</h1>
                        <p className="text-muted-foreground text-sm">Broadcast announcements and notifications to users.</p>
                    </div>
                </div>

                <form onSubmit={handleSubmit} className="space-y-6">
                    <div className="grid gap-6 lg:grid-cols-3">
                        <div className="space-y-6 lg:col-span-2">
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <Pencil className="h-5 w-5" />
                                        Template Selection
                                    </CardTitle>
                                    <CardDescription>Choose a pre-designed template or compose manually.</CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <Tabs value={activeTab} onValueChange={setActiveTab}>
                                        <TabsList className="mb-4">
                                            <TabsTrigger value="compose">Manual Compose</TabsTrigger>
                                            <TabsTrigger value="template">Use Template</TabsTrigger>
                                        </TabsList>

                                        <TabsContent value="compose" className="space-y-4">
                                            <div className="rounded-lg border border-dashed p-8 text-center">
                                                <Pencil className="text-muted-foreground/50 mx-auto h-10 w-10" />
                                                <p className="mt-2 font-medium">Manual Composition</p>
                                                <p className="text-muted-foreground text-sm">
                                                    Write your notification from scratch with full control over the content.
                                                </p>
                                            </div>
                                        </TabsContent>

                                        <TabsContent value="template" className="space-y-4">
                                            <ScrollArea className="h-[400px] pr-4">
                                                <div className="space-y-6">
                                                    {Object.entries(templatesByCategory || {}).map(([category, categoryTemplates]) => {
                                                        if (!Array.isArray(categoryTemplates)) return null;
                                                        const CategoryIcon = categoryIcons[category] || Info;
                                                        const categoryColor = categoryColors[category] || "bg-gray-500";

                                                        return (
                                                            <div key={category}>
                                                                <div className="mb-3 flex items-center gap-2">
                                                                    <div className={`rounded-md p-1.5 ${categoryColor}`}>
                                                                        <CategoryIcon className="h-4 w-4 text-white" />
                                                                    </div>
                                                                    <h3 className="font-semibold capitalize">{category.replace("_", " ")}</h3>
                                                                    <Badge variant="secondary" className="text-xs">
                                                                        {categoryTemplates.length}
                                                                    </Badge>
                                                                </div>
                                                                <div className="grid gap-3 sm:grid-cols-2">
                                                                    {Array.isArray(categoryTemplates) &&
                                                                        categoryTemplates.map((template) => (
                                                                            <div
                                                                                key={template.slug}
                                                                                className={`hover:border-primary/50 cursor-pointer rounded-lg border p-4 transition-all hover:shadow-sm ${
                                                                                    selectedTemplate?.slug === template.slug
                                                                                        ? "border-primary bg-primary/5 ring-primary ring-1"
                                                                                        : ""
                                                                                }`}
                                                                                onClick={() => handleTemplateSelect(template.slug)}
                                                                            >
                                                                                <div className="flex items-start justify-between">
                                                                                    <div>
                                                                                        <h4 className="font-medium">{template.name}</h4>
                                                                                        <p className="text-muted-foreground mt-1 text-sm">
                                                                                            {template.description}
                                                                                        </p>
                                                                                    </div>
                                                                                    {selectedTemplate?.slug === template.slug && (
                                                                                        <Badge variant="default" className="shrink-0">
                                                                                            Selected
                                                                                        </Badge>
                                                                                    )}
                                                                                </div>
                                                                                <div className="mt-3 flex flex-wrap gap-1">
                                                                                    {template.default_channels.includes("mail") && (
                                                                                        <Badge variant="outline" className="text-xs">
                                                                                            <Mail className="mr-1 h-3 w-3" />
                                                                                            Email
                                                                                        </Badge>
                                                                                    )}
                                                                                    {template.default_channels.includes("database") && (
                                                                                        <Badge variant="outline" className="text-xs">
                                                                                            <Bell className="mr-1 h-3 w-3" />
                                                                                            In-App
                                                                                        </Badge>
                                                                                    )}
                                                                                </div>
                                                                            </div>
                                                                        ))}
                                                                </div>
                                                                <Separator className="mt-6" />
                                                            </div>
                                                        );
                                                    })}
                                                </div>
                                            </ScrollArea>
                                        </TabsContent>
                                    </Tabs>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle>Content</CardTitle>
                                    <CardDescription>Compose the notification message.</CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                        <div className="space-y-2">
                                            <Label htmlFor="title">Title</Label>
                                            <Input
                                                id="title"
                                                placeholder="E.g., System Maintenance Tomorrow"
                                                value={data.title}
                                                onChange={(e) => setData("title", e.target.value)}
                                                className={errors.title ? "border-destructive" : ""}
                                            />
                                            {errors.title && <p className="text-destructive text-sm font-medium">{errors.title}</p>}
                                        </div>
                                        {activeTab === "compose" && (
                                            <div className="space-y-2">
                                                <Label htmlFor="type">Notification Type</Label>
                                                <Select value={data.type} onValueChange={(v) => setData("type", v)}>
                                                    <SelectTrigger className={errors.type ? "border-destructive" : ""}>
                                                        <SelectValue placeholder="Select type" />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem value="info">Info (Blue)</SelectItem>
                                                        <SelectItem value="success">Success (Green)</SelectItem>
                                                        <SelectItem value="warning">Warning (Yellow)</SelectItem>
                                                        <SelectItem value="error">Error (Red)</SelectItem>
                                                    </SelectContent>
                                                </Select>
                                            </div>
                                        )}
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="content">Message Content</Label>
                                        <Textarea
                                            id="content"
                                            placeholder="Enter the detailed notification message..."
                                            className={`min-h-[120px] ${errors.content ? "border-destructive" : ""}`}
                                            value={data.content}
                                            onChange={(e) => setData("content", e.target.value)}
                                        />
                                        {errors.content && <p className="text-destructive text-sm font-medium">{errors.content}</p>}
                                    </div>

                                    {selectedTemplate && templateVariables.length > 0 && (
                                        <div className="space-y-4 pt-4">
                                            <div className="flex items-center gap-2">
                                                <Sparkles className="text-primary h-4 w-4" />
                                                <Label className="text-base font-semibold">Template Variables</Label>
                                            </div>
                                            <p className="text-muted-foreground text-sm">Fill in the template-specific fields below.</p>
                                            <div className="grid gap-4 sm:grid-cols-2">
                                                {templateVariables.map((variable) => {
                                                    const VariableIcon = variableIcons[variable] || Pencil;
                                                    const label = variableLabels[variable] || variable.replace(/_/g, " ");
                                                    const inputType = variableTypes[variable] || "text";

                                                    return (
                                                        <div key={variable} className="space-y-2">
                                                            <Label htmlFor={`template_${variable}`} className="flex items-center gap-2">
                                                                <VariableIcon className="text-muted-foreground h-4 w-4" />
                                                                {label}
                                                            </Label>
                                                            {inputType === "textarea" ? (
                                                                <Textarea
                                                                    id={`template_${variable}`}
                                                                    placeholder={`Enter ${label.toLowerCase()}...`}
                                                                    className="min-h-[80px]"
                                                                    value={data.template_data[variable] || ""}
                                                                    onChange={(e) => updateTemplateData(variable, e.target.value)}
                                                                />
                                                            ) : (
                                                                <Input
                                                                    id={`template_${variable}`}
                                                                    type={inputType}
                                                                    placeholder={`Enter ${label.toLowerCase()}...`}
                                                                    value={data.template_data[variable] || ""}
                                                                    onChange={(e) => updateTemplateData(variable, e.target.value)}
                                                                />
                                                            )}
                                                            {errors[`template_data.${variable}` as keyof typeof errors] && (
                                                                <p className="text-destructive text-sm font-medium">
                                                                    {errors[`template_data.${variable}` as keyof typeof errors]}
                                                                </p>
                                                            )}
                                                        </div>
                                                    );
                                                })}
                                            </div>
                                        </div>
                                    )}

                                    <div className="space-y-4 pt-4">
                                        <div className="flex items-center justify-between">
                                            <Label>Action Buttons (Optional)</Label>
                                            <Button type="button" variant="outline" size="sm" onClick={addAction}>
                                                Add Action Button
                                            </Button>
                                        </div>

                                        {data.actions.map((action, index) => (
                                            <div key={index} className="flex flex-col gap-4 rounded-md border p-4 sm:flex-row sm:items-end">
                                                <div className="flex-1 space-y-2">
                                                    <Label>Button Label</Label>
                                                    <Input
                                                        placeholder="E.g., View Details"
                                                        value={action.label}
                                                        onChange={(e) => updateAction(index, "label", e.target.value)}
                                                    />
                                                </div>
                                                <div className="flex-1 space-y-2">
                                                    <Label>URL</Label>
                                                    <Input
                                                        placeholder="https://..."
                                                        value={action.url}
                                                        onChange={(e) => updateAction(index, "url", e.target.value)}
                                                    />
                                                </div>
                                                <div className="w-full space-y-2 sm:w-32">
                                                    <Label>Color</Label>
                                                    <Select value={action.color} onValueChange={(v) => updateAction(index, "color", v)}>
                                                        <SelectTrigger>
                                                            <SelectValue />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem value="primary">Primary</SelectItem>
                                                            <SelectItem value="secondary">Secondary</SelectItem>
                                                            <SelectItem value="success">Success</SelectItem>
                                                            <SelectItem value="danger">Danger</SelectItem>
                                                        </SelectContent>
                                                    </Select>
                                                </div>
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon"
                                                    className="text-muted-foreground hover:text-destructive shrink-0"
                                                    onClick={() => removeAction(index)}
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                </Button>
                                            </div>
                                        ))}
                                    </div>
                                </CardContent>
                            </Card>
                        </div>

                        <div className="space-y-6">
                            <Card>
                                <CardHeader>
                                    <CardTitle>Audience & Channels</CardTitle>
                                    <CardDescription>Select who should receive this notification and how.</CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-6">
                                    <div className="space-y-4">
                                        <Label>Target Audience</Label>
                                        <div className="space-y-3">
                                            {targetOptions.map((option) => (
                                                <div
                                                    key={option.id}
                                                    className="hover:bg-accent/50 flex cursor-pointer items-start space-x-3 rounded-md border p-3 transition-colors"
                                                >
                                                    <Checkbox
                                                        checked={data.target_audience.includes(option.id)}
                                                        onCheckedChange={(c) => toggleTarget(!!c, option.id)}
                                                    />
                                                    <div className="space-y-1 leading-none">
                                                        <Label
                                                            className="cursor-pointer text-sm"
                                                            onClick={() => toggleTarget(!data.target_audience.includes(option.id), option.id)}
                                                        >
                                                            {option.label}
                                                        </Label>
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                        {errors.target_audience && <p className="text-destructive text-sm font-medium">{errors.target_audience}</p>}
                                    </div>

                                    <Separator />

                                    <div className="space-y-4">
                                        <Label>Delivery Channels</Label>
                                        <div className="space-y-3">
                                            <div className="hover:bg-accent/50 flex cursor-pointer items-center justify-between rounded-md border p-3 transition-colors">
                                                <div className="flex items-center gap-3">
                                                    <Bell className="text-primary h-5 w-5" />
                                                    <div>
                                                        <Label className="cursor-pointer">In-App Notification</Label>
                                                        <p className="text-muted-foreground text-xs">Shows in the notification panel</p>
                                                    </div>
                                                </div>
                                                <Switch
                                                    checked={data.channels.includes("database")}
                                                    onCheckedChange={(c) => toggleChannel(c, "database")}
                                                />
                                            </div>
                                            <div className="hover:bg-accent/50 flex cursor-pointer items-center justify-between rounded-md border p-3 transition-colors">
                                                <div className="flex items-center gap-3">
                                                    <Mail className="text-primary h-5 w-5" />
                                                    <div>
                                                        <Label className="cursor-pointer">Email Notification</Label>
                                                        <p className="text-muted-foreground text-xs">Sends to user's email address</p>
                                                    </div>
                                                </div>
                                                <Switch checked={data.channels.includes("mail")} onCheckedChange={(c) => toggleChannel(c, "mail")} />
                                            </div>
                                        </div>
                                        {errors.channels && <p className="text-destructive text-sm font-medium">{errors.channels}</p>}
                                    </div>
                                </CardContent>
                            </Card>

                            {selectedTemplate && (
                                <Card className="border-primary/20 bg-primary/5">
                                    <CardHeader className="pb-3">
                                        <CardTitle className="flex items-center gap-2 text-base">
                                            <Eye className="h-4 w-4" />
                                            Template Preview
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <div className="space-y-2">
                                            <div className="flex items-center gap-2">
                                                <Badge
                                                    style={{
                                                        backgroundColor: selectedTemplate.styles?.primary_color || "#3b82f6",
                                                    }}
                                                    className="text-white"
                                                >
                                                    {selectedTemplate.name}
                                                </Badge>
                                            </div>
                                            <p className="text-muted-foreground text-sm">{selectedTemplate.description}</p>
                                            <div className="mt-3 space-y-1">
                                                <p className="text-xs font-medium">Variables:</p>
                                                <div className="flex flex-wrap gap-1">
                                                    {Array.isArray(selectedTemplate.variables) &&
                                                        selectedTemplate.variables.map((v) => (
                                                            <Badge key={v} variant="outline" className="text-xs">
                                                                {v}
                                                            </Badge>
                                                        ))}
                                                </div>
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>
                            )}

                            <div className="mt-6 flex w-full gap-4">
                                <Button
                                    type="button"
                                    variant="outline"
                                    className="min-h-[48px] flex-1"
                                    size="lg"
                                    onClick={handlePreview}
                                    disabled={processing || previewLoading}
                                >
                                    {previewLoading ? "Loading..." : "Preview"}
                                    <Eye className="ml-2 h-4 w-4" />
                                </Button>
                                <Button type="submit" disabled={processing || previewLoading} className="min-h-[48px] flex-1" size="lg">
                                    {processing ? (
                                        "Sending..."
                                    ) : (
                                        <>
                                            Send
                                            <Send className="ml-2 h-4 w-4" />
                                        </>
                                    )}
                                </Button>
                            </div>
                        </div>
                    </div>
                </form>

                <Dialog open={isPreviewOpen} onOpenChange={setIsPreviewOpen}>
                    <DialogContent className="flex max-h-[90vh] max-w-4xl flex-col">
                        <DialogHeader>
                            <DialogTitle>Notification Preview</DialogTitle>
                            <DialogDescription>Preview how your notification will appear to recipients.</DialogDescription>
                        </DialogHeader>

                        {previewLoading ? (
                            <div className="flex h-64 items-center justify-center">
                                <p className="text-muted-foreground animate-pulse">Generating preview...</p>
                            </div>
                        ) : previewData ? (
                            <Tabs defaultValue="email" className="flex min-h-0 flex-1 flex-col">
                                <TabsList className="grid w-full grid-cols-2 lg:w-[400px]">
                                    <TabsTrigger value="email">Email Preview</TabsTrigger>
                                    <TabsTrigger value="inapp">In-App Preview</TabsTrigger>
                                </TabsList>

                                <TabsContent value="email" className="mt-4 min-h-0 flex-1 overflow-hidden rounded-md border bg-white">
                                    {previewData.email ? (
                                        <iframe
                                            srcDoc={previewData.email}
                                            className="h-[600px] w-full border-none bg-white"
                                            title="Email Preview"
                                            sandbox="allow-same-origin"
                                        />
                                    ) : (
                                        <div className="text-muted-foreground flex h-full items-center justify-center">
                                            Unable to generate email preview.
                                        </div>
                                    )}
                                </TabsContent>

                                <TabsContent value="inapp" className="mt-4 flex min-h-0 flex-1 justify-center">
                                    {previewData.database ? (
                                        <div className="bg-background w-full max-w-sm overflow-hidden rounded-lg border shadow-sm">
                                            <div className="bg-muted/20 border-b p-4">
                                                <h3 className="flex items-center gap-2 text-sm font-semibold">
                                                    <Bell className="text-primary h-4 w-4" />
                                                    Notifications Dropdown
                                                </h3>
                                            </div>
                                            <div className="hover:bg-muted/50 flex items-start gap-4 p-4 transition-colors">
                                                <div className="mt-1">
                                                    <div className="bg-primary/10 text-primary flex h-8 w-8 items-center justify-center rounded-full">
                                                        <Bell className="h-4 w-4" />
                                                    </div>
                                                </div>
                                                <div className="flex-1 space-y-1">
                                                    <p className="text-sm leading-none font-medium">{previewData.database.title}</p>
                                                    <p className="text-muted-foreground line-clamp-2 text-sm">{previewData.database.message}</p>
                                                    <p className="text-muted-foreground pt-1 text-xs">Just now</p>
                                                </div>
                                            </div>
                                        </div>
                                    ) : (
                                        <div className="text-muted-foreground flex h-full w-full items-center justify-center rounded-md border">
                                            Unable to generate in-app preview.
                                        </div>
                                    )}
                                </TabsContent>
                            </Tabs>
                        ) : (
                            <div className="flex h-64 items-center justify-center">
                                <p className="text-muted-foreground">No preview data available.</p>
                            </div>
                        )}
                    </DialogContent>
                </Dialog>
            </div>
        </AdminLayout>
    );
}
