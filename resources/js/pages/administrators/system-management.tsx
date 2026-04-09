import AdminLayout from "@/components/administrators/admin-layout";
import { Badge } from "@/components/ui/badge";
import { Button, buttonVariants } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Combobox, type ComboboxOption } from "@/components/ui/combobox";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Separator } from "@/components/ui/separator";
import { Switch } from "@/components/ui/switch";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Textarea } from "@/components/ui/textarea";
import { cn } from "@/lib/utils";
import { Head, useForm } from "@inertiajs/react";
import axios from "axios";
import {
    Activity,
    AlertCircle,
    AlertTriangle,
    ArrowDown,
    ArrowUp,
    AtSign,
    Banknote,
    Building2,
    Check,
    CheckCircle2,
    Clock,
    Database,
    Database as DatabaseIcon,
    Globe,
    HardDrive,
    Info,
    KeyRound,
    Link2,
    List,
    Loader2,
    Mail,
    Palette,
    Plus,
    Save,
    Server,
    Share2,
    Trash2,
    Zap,
} from "lucide-react";
import { useEffect, useState } from "react";
import { Area, AreaChart, Cell, Pie, PieChart, ResponsiveContainer } from "recharts";
import { toast } from "sonner";

// Define route helper type for Ziggy
declare global {
    var route: (name: string, params?: unknown, absolute?: boolean) => string;
}

// --- Interfaces ---

interface School {
    id: number;
    name: string;
    code: string;
    description?: string;
    location?: string;
    phone?: string;
    email?: string;
    is_active: boolean;
}

interface SeoMetadata {
    robots?: string;
    og_image?: string;
    twitter_handle?: string;
    twitter_card?: string;
    canonical_url?: string;
}

interface GeneralSettings {
    site_name: string;
    site_description: string | null;
    seo_title: string | null;
    seo_keywords: string | null;
    seo_metadata: SeoMetadata | null;
    theme_color: string | null;
    currency: string | null;
    email_from_address: string | null;
    email_from_name: string | null;
}

interface SanityConfig {
    project_id: string;
    dataset: string;
    token: string;
    api_version: string;
    use_cdn: boolean;
}

interface SocialiteConfig {
    facebook_client_id?: string;
    facebook_client_secret?: string;
    google_client_id?: string;
    google_client_secret?: string;
    twitter_client_id?: string;
    twitter_client_secret?: string;
    github_client_id?: string;
    github_client_secret?: string;
    linkedin_client_id?: string;
    linkedin_client_secret?: string;
}

interface MailConfig {
    driver: string;
    host: string;
    port: number;
    username: string;
    password: string;
    encryption: string;
}

interface PulseData {
    servers: {
        servers: Record<
            string,
            {
                name: string;
                cpu_current: number;
                memory_current: number;
                memory_total: number;
                cpu: Record<string, string | null>;
                memory: Record<string, string | null>;
                storage: Array<{ directory: string; total: number; used: number }>;
                updated_at: string;
            }
        >;
    };
    usage: {
        userRequestCounts: Array<{
            key: string;
            user: { name: string; email: string; avatar?: string };
            count: number;
        }>;
    };
    slow_requests: {
        slowRequests: Array<{
            uri: string;
            method: string;
            action: string;
            count: string;
            slowest: string;
            threshold: number;
        }>;
    };
    queues: {
        queues: Array<{
            queue: string;
            size: number;
            failed: number;
        }>;
    };
    cache: {
        allCacheInteractions: {
            hits: string;
            misses: string;
        };
        cacheKeyInteractions: Array<{
            key: string;
            hits: number | string;
            misses: number | string;
        }>;
    };
    slow_queries: {
        slowQueries: Array<{
            sql: string;
            count: number;
            slowest: number;
            threshold: number;
        }>;
    };
    exceptions: {
        exceptions: Array<{
            class: string;
            message: string;
            count: number;
            latest: string;
        }>;
    };
    slow_jobs: {
        slowJobs: Array<{
            job: string;
            count: number;
            slowest: number;
            threshold: number;
        }>;
    };
    slow_outgoing_requests: {
        slowOutgoingRequests: Array<{
            uri: string;
            method: string;
            count: number;
            slowest: number;
            threshold: number;
        }>;
    };
}

interface BrandingSettings {
    app_name: string | null;
    app_short_name: string | null;
    organization_name: string | null;
    organization_short_name: string | null;
    organization_address: string | null;
    support_email: string | null;
    support_phone: string | null;
    tagline: string | null;
    copyright_text: string | null;
    theme_color: string | null;
    currency: string | null;
    logo: string | null;
    favicon: string | null;
}

interface EnrollmentPipelineSettings {
    submitted_label: string;
    entry_step_key?: string;
    completion_step_key?: string;
    steps: {
        key: string;
        status: string;
        label: string;
        color: string;
        allowed_roles: string[];
        action_type: "standard" | "department_verification" | "cashier_verification";
    }[];
}

interface EnrollmentStatsSettings {
    cards: {
        key: string;
        label: string;
        metric: "total_records" | "active_records" | "trashed_records" | "status_count" | "paid_count";
        statuses: string[];
        color: string;
    }[];
}

interface PageProps {
    user: Record<string, unknown>;
    general_settings: GeneralSettings;
    active_school: School | null;
    schools: School[];
    sanity_config: SanityConfig;
    socialite_config: SocialiteConfig;
    mail_config: MailConfig;
    branding: BrandingSettings;
    enrollment_pipeline: EnrollmentPipelineSettings;
    enrollment_stats: EnrollmentStatsSettings;
    available_roles: string[];
    [key: string]: unknown;
}

// --- Icons & Assets ---

const GoogleIcon = ({ className }: { className?: string }) => (
    <svg className={className} viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
        <path
            d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"
            fill="#4285F4"
        />
        <path
            d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"
            fill="#34A853"
        />
        <path
            d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"
            fill="#FBBC05"
        />
        <path
            d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"
            fill="#EA4335"
        />
    </svg>
);

const FacebookIcon = ({ className }: { className?: string }) => (
    <svg className={className} viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" fill="#1877F2">
        <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z" />
    </svg>
);

// Navigation items configuration
const navItems = [
    { id: "school", label: "School & Campus", icon: Building2, description: "Active school instance" },
    { id: "pipeline", label: "Enrollment Pipeline", icon: List, description: "Enrollment workflow configuration" },
    { id: "seo", label: "SEO & Metadata", icon: Globe, description: "Search engine optimization" },
    { id: "brand", label: "Brand & Appearance", icon: Palette, description: "Theme and visual identity" },
    { id: "sanity", label: "Content (Sanity)", icon: Database, description: "CMS connection" },
    { id: "socialite", label: "Social Auth", icon: Share2, description: "OAuth providers" },
    { id: "mail", label: "Mail Server", icon: Mail, description: "SMTP configuration" },
] as const;

export default function SystemManagement({
    user,
    general_settings,
    active_school,
    schools,
    sanity_config,
    socialite_config,
    mail_config,
    branding,
    enrollment_pipeline,
    enrollment_stats,
    available_roles,
}: PageProps) {
    type SubmitForm = {
        put: (url: string, options: { onSuccess: () => void; onError: () => void }) => void;
        post: (
            url: string,
            options: {
                forceFormData?: boolean;
                onSuccess: () => void;
                onError: () => void;
            },
        ) => void;
        transform: (callback: (data: Record<string, unknown>) => Record<string, unknown>) => SubmitForm;
    };

    const [activeTab, setActiveTab] = useState("school");
    const [selectedStepIndex, setSelectedStepIndex] = useState<number | "submitted" | null>("submitted");
    const [editingCardIndex, setEditingCardIndex] = useState<number | null>(null);
    const [pulseData, setPulseData] = useState<PulseData | null>(null);
    const [loadingPulse, setLoadingPulse] = useState(false);
    const [isAddSchoolOpen, setIsAddSchoolOpen] = useState(false);
    const [selectedRoleByStep, setSelectedRoleByStep] = useState<Record<number, string>>({});

    useEffect(() => {
        if (activeTab === "pulse") {
            const fetchPulse = async () => {
                setLoadingPulse(true);
                try {
                    const response = await axios.get("/api/pulse");
                    const responseData = response.data.data || response.data;
                    setPulseData(responseData);
                } catch (error) {
                    console.error("Failed to load Pulse data", error);
                    toast.error("Failed to load system metrics");
                } finally {
                    setLoadingPulse(false);
                }
            };

            fetchPulse();
            const interval = setInterval(fetchPulse, 5000);
            return () => clearInterval(interval);
        }
    }, [activeTab]);

    // Forms
    const schoolForm = useForm({ school_id: active_school?.id?.toString() || "" });

    const schoolDetailsForm = useForm({
        school_id: active_school?.id?.toString() || "",
        name: active_school?.name || "",
        code: active_school?.code || "",
        description: active_school?.description || "",
        location: active_school?.location || "",
        phone: active_school?.phone || "",
        email: active_school?.email || "",
    });

    const createSchoolForm = useForm({
        name: "",
        code: "",
        description: "",
        location: "",
        phone: "",
        email: "",
    });

    // Update details form when active school changes
    useEffect(() => {
        if (active_school) {
            schoolDetailsForm.setData({
                school_id: active_school.id.toString(),
                name: active_school.name,
                code: active_school.code,
                description: active_school.description || "",
                location: active_school.location || "",
                phone: active_school.phone || "",
                email: active_school.email || "",
            });
        }
    }, [active_school]);

    const seoForm = useForm({
        site_name: general_settings?.site_name || "",
        site_description: general_settings?.site_description || "",
        seo_title: general_settings?.seo_title || "",
        seo_keywords: general_settings?.seo_keywords || "",
        seo_metadata: {
            robots: general_settings?.seo_metadata?.robots || "index, follow",
            og_image: general_settings?.seo_metadata?.og_image || "",
            twitter_handle: general_settings?.seo_metadata?.twitter_handle || "",
            twitter_card: general_settings?.seo_metadata?.twitter_card || "summary_large_image",
            canonical_url: general_settings?.seo_metadata?.canonical_url || "",
        },
    });

    const brandForm = useForm({
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
        logo: null as File | null,
        favicon: null as File | null,
    });

    const sanityForm = useForm({
        project_id: sanity_config?.project_id || "",
        dataset: sanity_config?.dataset || "",
        token: sanity_config?.token || "",
        api_version: sanity_config?.api_version || "",
        use_cdn: sanity_config?.use_cdn || false,
    });

    const socialiteForm = useForm({
        facebook_client_id: socialite_config?.facebook_client_id || "",
        facebook_client_secret: socialite_config?.facebook_client_secret || "",
        google_client_id: socialite_config?.google_client_id || "",
        google_client_secret: socialite_config?.google_client_secret || "",
        twitter_client_id: socialite_config?.twitter_client_id || "",
        twitter_client_secret: socialite_config?.twitter_client_secret || "",
        github_client_id: socialite_config?.github_client_id || "",
        github_client_secret: socialite_config?.github_client_secret || "",
        linkedin_client_id: socialite_config?.linkedin_client_id || "",
        linkedin_client_secret: socialite_config?.linkedin_client_secret || "",
    });

    const mailForm = useForm({
        email_from_address: general_settings?.email_from_address || "",
        email_from_name: general_settings?.email_from_name || "",
        driver: mail_config?.driver || "smtp",
        host: mail_config?.host || "",
        port: mail_config?.port || 587,
        username: mail_config?.username || "",
        password: mail_config?.password || "",
        encryption: mail_config?.encryption || "tls",
    });

    const initialPipelineSteps = enrollment_pipeline?.steps || [];

    const pipelineForm = useForm({
        submitted_label: enrollment_pipeline?.submitted_label || "Submitted",
        entry_step_key: enrollment_pipeline?.entry_step_key || initialPipelineSteps[0]?.key || "",
        completion_step_key: enrollment_pipeline?.completion_step_key || initialPipelineSteps[initialPipelineSteps.length - 1]?.key || "",
        steps: initialPipelineSteps.map((step) => ({
            key: step.key,
            status: step.status,
            label: step.label,
            color: step.color,
            allowed_roles: step.allowed_roles || [],
            action_type: step.action_type || "standard",
        })),
        enrollment_stats: {
            cards: enrollment_stats?.cards || [],
        },
    });

    const colorOptions = ["yellow", "blue", "green", "emerald", "teal", "gray", "amber", "red", "indigo", "orange"];
    const roleComboboxOptions: ComboboxOption[] = available_roles.map((roleName) => ({
        value: roleName,
        label: roleName,
        searchText: roleName,
    }));
    const stepTypeOptions = [
        { value: "standard", label: "Standard Step" },
        { value: "department_verification", label: "Verification Step" },
        { value: "cashier_verification", label: "Payment Verification Step" },
    ] as const;
    const statsMetricOptions = [
        { value: "total_records", label: "Total Records" },
        { value: "active_records", label: "Active Records" },
        { value: "trashed_records", label: "Deleted Records" },
        { value: "status_count", label: "Status Count" },
        { value: "paid_count", label: "Fully Paid Count" },
    ] as const;

    const updatePipelineStep = (index: number, field: "key" | "status" | "label" | "color" | "action_type", value: string) => {
        const steps = [...pipelineForm.data.steps];
        if (!steps[index]) {
            return;
        }

        steps[index][field] = value;
        pipelineForm.setData("steps", steps);
    };

    const addRoleToStep = (index: number) => {
        const selectedRole = selectedRoleByStep[index];
        if (!selectedRole) {
            return;
        }

        const steps = [...pipelineForm.data.steps];
        if (!steps[index]) {
            return;
        }

        const roles = steps[index].allowed_roles || [];
        if (!roles.includes(selectedRole)) {
            steps[index].allowed_roles = [...roles, selectedRole];
            pipelineForm.setData("steps", steps);
        }

        setSelectedRoleByStep((current) => ({ ...current, [index]: "" }));
    };

    const removeRoleFromStep = (index: number, roleName: string) => {
        const steps = [...pipelineForm.data.steps];
        if (!steps[index]) {
            return;
        }

        steps[index].allowed_roles = (steps[index].allowed_roles || []).filter((role) => role !== roleName);
        pipelineForm.setData("steps", steps);
    };

    const addPipelineStep = () => {
        const nextIndex = pipelineForm.data.steps.length + 1;
        pipelineForm.setData("steps", [
            ...pipelineForm.data.steps,
            {
                key: `step_${nextIndex}`,
                status: "",
                label: "",
                color: "indigo",
                allowed_roles: [],
                action_type: "standard",
            },
        ]);
    };

    const removePipelineStep = (index: number) => {
        const nextSteps = pipelineForm.data.steps.filter((_, stepIndex) => stepIndex !== index);
        pipelineForm.setData("steps", nextSteps);
    };

    const movePipelineStep = (index: number, direction: "up" | "down") => {
        const targetIndex = direction === "up" ? index - 1 : index + 1;
        if (targetIndex < 0 || targetIndex >= pipelineForm.data.steps.length) {
            return;
        }

        const reordered = [...pipelineForm.data.steps];
        const [moved] = reordered.splice(index, 1);
        reordered.splice(targetIndex, 0, moved);
        pipelineForm.setData("steps", reordered);
    };

    const updateStatsCard = (index: number, field: "key" | "label" | "metric" | "color", value: string) => {
        const cards = [...pipelineForm.data.enrollment_stats.cards];
        if (!cards[index]) {
            return;
        }

        cards[index][field] = value;
        pipelineForm.setData("enrollment_stats", { cards });
    };

    const addStatsCard = () => {
        const nextIndex = pipelineForm.data.enrollment_stats.cards.length + 1;
        pipelineForm.setData("enrollment_stats", {
            cards: [
                ...pipelineForm.data.enrollment_stats.cards,
                {
                    key: `stat_${nextIndex}`,
                    label: "",
                    metric: "total_records",
                    statuses: [],
                    color: "blue",
                },
            ],
        });
    };

    const removeStatsCard = (index: number) => {
        pipelineForm.setData("enrollment_stats", {
            cards: pipelineForm.data.enrollment_stats.cards.filter((_, cardIndex) => cardIndex !== index),
        });
    };

    const toggleStatsCardStatus = (index: number, statusValue: string) => {
        const cards = [...pipelineForm.data.enrollment_stats.cards];
        if (!cards[index]) {
            return;
        }

        const statuses = cards[index].statuses || [];
        cards[index].statuses = statuses.includes(statusValue) ? statuses.filter((status) => status !== statusValue) : [...statuses, statusValue];

        pipelineForm.setData("enrollment_stats", { cards });
    };

    const [testEmail, setTestEmail] = useState("");
    const [sendingTest, setSendingTest] = useState(false);

    // Submit Handlers
    const handleSubmit = (form: SubmitForm, routeName: string, successMsg: string, errorMsg: string, hasFiles = false) => {
        if (hasFiles) {
            form.transform((data: Record<string, unknown>) => ({
                ...data,
                _method: "PUT",
            })).post(route(routeName), {
                forceFormData: true,
                onSuccess: () => toast.success(successMsg),
                onError: () => toast.error(errorMsg),
            });
        } else {
            form.put(route(routeName), {
                onSuccess: () => toast.success(successMsg),
                onError: () => toast.error(errorMsg),
            });
        }
    };

    const handleTestEmail = async (e: React.FormEvent) => {
        e.preventDefault();
        if (!testEmail) {
            toast.error("Please enter an email address");
            return;
        }

        setSendingTest(true);
        try {
            const response = await axios.post(route("administrators.system-management.mail.test"), {
                to: testEmail,
            });

            toast.success(response.data.message || "Test email sent successfully!");
            setTestEmail("");
        } catch (error: unknown) {
            console.error(error);
            const responseData =
                axios.isAxiosError(error) && typeof error.response?.data === "object" && error.response?.data !== null
                    ? (error.response.data as { message?: string; exception?: string })
                    : null;
            const errorMessage = responseData?.message || (error instanceof Error ? error.message : "An unexpected error occurred");
            const errorDetail = responseData?.exception || "";

            toast.error("Failed to send test email", {
                description: errorDetail ? `${errorMessage}: ${errorDetail}` : errorMessage,
            });
        } finally {
            setSendingTest(false);
        }
    };

    const handleCreateSchool = (e: React.FormEvent) => {
        e.preventDefault();
        createSchoolForm.post(route("administrators.system-management.school.store"), {
            onSuccess: () => {
                toast.success("School created successfully");
                setIsAddSchoolOpen(false);
                createSchoolForm.reset();
            },
            onError: () => toast.error("Failed to create school"),
        });
    };

    // Determine statuses
    const getConfigStatus = (id: string): boolean => {
        switch (id) {
            case "school":
                return !!active_school;
            case "seo":
                return !!seoForm.data.site_name;
            case "pipeline":
                return pipelineForm.data.steps.length > 0 && pipelineForm.data.steps.every((step) => step.status && step.label);
            case "brand":
                return !!brandForm.data.app_name && !!brandForm.data.organization_name;
            case "sanity":
                return !!sanityForm.data.project_id && !!sanityForm.data.dataset;
            case "socialite":
                return !!socialiteForm.data.google_client_id || !!socialiteForm.data.facebook_client_id || !!socialiteForm.data.github_client_id;
            case "mail":
                return !!mailForm.data.host && !!mailForm.data.username;
            default:
                return false;
        }
    };

    const workflowPreview = [
        {
            id: "submitted",
            label: pipelineForm.data.submitted_label || "Submitted",
            status: "Submitted",
            color: "gray",
            roles: [] as string[],
            isFinal: false,
        },
        ...pipelineForm.data.steps.map((step, index) => ({
            id: step.key || `step-${index}`,
            label: step.label || `Additional Step ${index + 1}`,
            status: step.status || "Unset Status",
            color: step.color || "indigo",
            roles: step.allowed_roles || [],
            isFinal: step.key === pipelineForm.data.completion_step_key,
        })),
    ];

    return (
        <AdminLayout user={user} title="System Settings">
            <Head title="System Settings" />

            <div className="space-y-6">
                {/* Header */}
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">System Settings</h1>
                    <p className="text-muted-foreground">Manage your application configuration and integrations</p>
                </div>

                {/* Main Content */}
                <Tabs value={activeTab} onValueChange={setActiveTab} className="space-y-6">
                    {/* Horizontal Tab Navigation */}
                    <div className="border-b">
                        <TabsList className="h-auto w-full justify-start gap-0 rounded-none bg-transparent p-0">
                            {navItems.map((item) => {
                                const Icon = item.icon;
                                const isConfigured = getConfigStatus(item.id);
                                return (
                                    <TabsTrigger
                                        key={item.id}
                                        value={item.id}
                                        className="data-[state=active]:border-primary relative rounded-none border-b-2 border-transparent px-4 py-3 data-[state=active]:bg-transparent data-[state=active]:shadow-none"
                                    >
                                        <div className="flex items-center gap-2">
                                            <Icon className="h-4 w-4" />
                                            <span className="hidden sm:inline">{item.label}</span>
                                            <div className={cn("h-2 w-2 rounded-full", isConfigured ? "bg-green-500" : "bg-amber-500")} />
                                        </div>
                                    </TabsTrigger>
                                );
                            })}
                            <TabsTrigger
                                value="pulse"
                                className="data-[state=active]:border-primary relative rounded-none border-b-2 border-transparent px-4 py-3 data-[state=active]:bg-transparent data-[state=active]:shadow-none"
                            >
                                <div className="flex items-center gap-2">
                                    <Activity className="h-4 w-4" />
                                    <span className="hidden sm:inline">System Pulse</span>
                                    <div className="h-2 w-2 animate-pulse rounded-full bg-blue-500" />
                                </div>
                            </TabsTrigger>
                        </TabsList>
                    </div>

                    {/* School Tab */}
                    <TabsContent value="school" className="space-y-6">
                        <Card>
                            <CardHeader>
                                <div className="flex items-start justify-between">
                                    <div className="space-y-1">
                                        <CardTitle>School Configuration</CardTitle>
                                        <CardDescription>Select the active school for this system instance</CardDescription>
                                    </div>
                                    <Button
                                        onClick={() =>
                                            handleSubmit(
                                                schoolForm,
                                                "administrators.system-management.school.update",
                                                "School updated successfully",
                                                "Failed to update school",
                                            )
                                        }
                                        disabled={schoolForm.processing}
                                    >
                                        {schoolForm.processing ? (
                                            <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                        ) : (
                                            <Save className="mr-2 h-4 w-4" />
                                        )}
                                        Save Changes
                                    </Button>
                                </div>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="grid gap-3">
                                    {schools.map((school) => (
                                        <div
                                            key={school.id}
                                            onClick={() => schoolForm.setData("school_id", school.id.toString())}
                                            className={cn(
                                                "hover:bg-accent flex cursor-pointer items-center justify-between rounded-lg border p-4 transition-all",
                                                schoolForm.data.school_id === school.id.toString()
                                                    ? "border-primary ring-primary/20 bg-primary/5 ring-2"
                                                    : "",
                                            )}
                                        >
                                            <div className="flex items-center gap-4">
                                                <div
                                                    className={cn(
                                                        "flex h-10 w-10 items-center justify-center rounded-lg",
                                                        schoolForm.data.school_id === school.id.toString()
                                                            ? "bg-primary text-primary-foreground"
                                                            : "bg-muted",
                                                    )}
                                                >
                                                    <Building2 className="h-5 w-5" />
                                                </div>
                                                <div>
                                                    <h4 className="font-medium">{school.name}</h4>
                                                    <p className="text-muted-foreground text-sm">{school.code}</p>
                                                </div>
                                            </div>
                                            {schoolForm.data.school_id === school.id.toString() && (
                                                <div className="bg-primary text-primary-foreground flex h-6 w-6 items-center justify-center rounded-full">
                                                    <Check className="h-4 w-4" />
                                                </div>
                                            )}
                                        </div>
                                    ))}

                                    <Dialog open={isAddSchoolOpen} onOpenChange={setIsAddSchoolOpen}>
                                        <DialogTrigger className={cn(buttonVariants({ variant: "outline" }), "w-full border-dashed")}>
                                            <Plus className="mr-2 h-4 w-4" />
                                            Add New School
                                        </DialogTrigger>
                                        <DialogContent className="sm:max-w-[500px]">
                                            <DialogHeader>
                                                <DialogTitle>Add New School</DialogTitle>
                                                <DialogDescription>
                                                    Create a new school organization. This will isolate data for this new campus.
                                                </DialogDescription>
                                            </DialogHeader>
                                            <form onSubmit={handleCreateSchool} className="space-y-4 py-4">
                                                <div className="grid gap-4 sm:grid-cols-2">
                                                    <div className="space-y-2">
                                                        <Label htmlFor="new_school_name">School Name</Label>
                                                        <Input
                                                            id="new_school_name"
                                                            value={createSchoolForm.data.name}
                                                            onChange={(e) => createSchoolForm.setData("name", e.target.value)}
                                                            placeholder="e.g. KoAkademy - New Campus"
                                                            required
                                                        />
                                                    </div>
                                                    <div className="space-y-2">
                                                        <Label htmlFor="new_school_code">School Code</Label>
                                                        <Input
                                                            id="new_school_code"
                                                            value={createSchoolForm.data.code}
                                                            onChange={(e) => createSchoolForm.setData("code", e.target.value)}
                                                            placeholder="e.g. KOA-NEW"
                                                            required
                                                        />
                                                    </div>
                                                </div>
                                                <div className="space-y-2">
                                                    <Label htmlFor="new_school_desc">Description</Label>
                                                    <Textarea
                                                        id="new_school_desc"
                                                        value={createSchoolForm.data.description}
                                                        onChange={(e) => createSchoolForm.setData("description", e.target.value)}
                                                        placeholder="Optional description..."
                                                    />
                                                </div>
                                                <div className="space-y-2">
                                                    <Label htmlFor="new_school_loc">Location</Label>
                                                    <Input
                                                        id="new_school_loc"
                                                        value={createSchoolForm.data.location}
                                                        onChange={(e) => createSchoolForm.setData("location", e.target.value)}
                                                        placeholder="City, Province"
                                                    />
                                                </div>
                                                <DialogFooter>
                                                    <Button type="button" variant="outline" onClick={() => setIsAddSchoolOpen(false)}>
                                                        Cancel
                                                    </Button>
                                                    <Button type="submit" disabled={createSchoolForm.processing}>
                                                        {createSchoolForm.processing ? (
                                                            <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                                        ) : (
                                                            "Create School"
                                                        )}
                                                    </Button>
                                                </DialogFooter>
                                            </form>
                                        </DialogContent>
                                    </Dialog>
                                </div>

                                <div className="rounded-lg border border-amber-200 bg-amber-50 p-4 dark:border-amber-900/50 dark:bg-amber-900/20">
                                    <div className="flex gap-3">
                                        <AlertTriangle className="h-5 w-5 shrink-0 text-amber-600 dark:text-amber-500" />
                                        <div>
                                            <p className="font-medium text-amber-800 dark:text-amber-200">Important Notice</p>
                                            <p className="mt-1 text-sm text-amber-700 dark:text-amber-300">
                                                Changing the active school affects all student and faculty portal data globally.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        {/* School Details Card */}
                        <Card>
                            <CardHeader>
                                <div className="flex items-start justify-between">
                                    <div className="space-y-1">
                                        <CardTitle>School Details</CardTitle>
                                        <CardDescription>Configure information for {active_school?.name || "the active school"}</CardDescription>
                                    </div>
                                    <Button
                                        onClick={() =>
                                            handleSubmit(
                                                schoolDetailsForm,
                                                "administrators.system-management.school-details.update",
                                                "School details updated successfully",
                                                "Failed to update school details",
                                            )
                                        }
                                        disabled={schoolDetailsForm.processing || !active_school}
                                    >
                                        {schoolDetailsForm.processing ? (
                                            <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                        ) : (
                                            <Save className="mr-2 h-4 w-4" />
                                        )}
                                        Save Details
                                    </Button>
                                </div>
                            </CardHeader>
                            <CardContent className="space-y-6">
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="space-y-2">
                                        <Label htmlFor="school_name">School Name</Label>
                                        <Input
                                            id="school_name"
                                            value={schoolDetailsForm.data.name}
                                            onChange={(e) => schoolDetailsForm.setData("name", e.target.value)}
                                            placeholder="e.g. KoAkademy"
                                            disabled={!active_school}
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="school_code">School Code</Label>
                                        <Input
                                            id="school_code"
                                            value={schoolDetailsForm.data.code}
                                            onChange={(e) => schoolDetailsForm.setData("code", e.target.value)}
                                            placeholder="e.g. KOA-MAIN"
                                            disabled={!active_school}
                                        />
                                    </div>
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="school_description">Description</Label>
                                    <Textarea
                                        id="school_description"
                                        value={schoolDetailsForm.data.description}
                                        onChange={(e) => schoolDetailsForm.setData("description", e.target.value)}
                                        placeholder="Brief description of the campus..."
                                        rows={3}
                                        disabled={!active_school}
                                    />
                                </div>

                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="space-y-2">
                                        <Label htmlFor="school_phone">Phone Number</Label>
                                        <Input
                                            id="school_phone"
                                            value={schoolDetailsForm.data.phone}
                                            onChange={(e) => schoolDetailsForm.setData("phone", e.target.value)}
                                            placeholder="+63 912 345 6789"
                                            disabled={!active_school}
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="school_email">Email Address</Label>
                                        <Input
                                            id="school_email"
                                            type="email"
                                            value={schoolDetailsForm.data.email}
                                            onChange={(e) => schoolDetailsForm.setData("email", e.target.value)}
                                            placeholder="info@koakademy.edu"
                                            disabled={!active_school}
                                        />
                                    </div>
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="school_location">Location / Address</Label>
                                    <Input
                                        id="school_location"
                                        value={schoolDetailsForm.data.location}
                                        onChange={(e) => schoolDetailsForm.setData("location", e.target.value)}
                                        placeholder="Full address of the campus"
                                        disabled={!active_school}
                                    />
                                </div>
                            </CardContent>
                        </Card>
                    </TabsContent>

                    {/* Enrollment Pipeline Tab */}
                    <TabsContent value="pipeline" className="space-y-6">
                        <div className="flex items-center justify-between">
                            <div className="space-y-1">
                                <h2 className="text-2xl font-bold tracking-tight">Enrollment Pipeline Builder</h2>
                                <p className="text-muted-foreground">Design your student enrollment journey, statuses, and tracking metrics.</p>
                            </div>
                            <Button
                                onClick={() =>
                                    handleSubmit(
                                        pipelineForm,
                                        "administrators.system-management.enrollment-pipeline.update",
                                        "Enrollment pipeline updated successfully",
                                        "Failed to update enrollment pipeline",
                                    )
                                }
                                disabled={pipelineForm.processing}
                            >
                                {pipelineForm.processing ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <Save className="mr-2 h-4 w-4" />}
                                Save Pipeline Configuration
                            </Button>
                        </div>

                        <Tabs defaultValue="workflow" className="w-full">
                            <TabsList className="grid w-full max-w-[400px] grid-cols-2">
                                <TabsTrigger value="workflow">Workflow Builder</TabsTrigger>
                                <TabsTrigger value="stats">Analytics Cards</TabsTrigger>
                            </TabsList>

                            {/* WORKFLOW BUILDER TAB */}
                            <TabsContent value="workflow" className="mt-6 space-y-6">
                                <div className="grid gap-6 lg:grid-cols-12">
                                    {/* Left Column: Visual Timeline */}
                                    <Card className="flex flex-col border-2 shadow-sm lg:col-span-4 lg:h-[800px]">
                                        <CardHeader className="bg-muted/10 flex flex-row items-center justify-between pb-2">
                                            <div>
                                                <CardTitle className="text-base">Journey Flow</CardTitle>
                                                <CardDescription>Visual order of the workflow</CardDescription>
                                            </div>
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={() => {
                                                    addPipelineStep();
                                                    setSelectedStepIndex(pipelineForm.data.steps.length);
                                                }}
                                            >
                                                <Plus className="h-4 w-4" />
                                            </Button>
                                        </CardHeader>
                                        <Separator />
                                        <CardContent className="flex-1 overflow-y-auto px-4 pt-6">
                                            <div className="relative space-y-1 pl-3">
                                                {/* Vertical line connecting nodes */}
                                                <div className="bg-muted/70 absolute top-6 bottom-6 left-[23px] w-[2px]"></div>

                                                {/* Submitted Node */}
                                                <div
                                                    className={cn(
                                                        "relative flex cursor-pointer items-start gap-3 rounded-lg p-3 transition-all",
                                                        selectedStepIndex === "submitted"
                                                            ? "bg-accent/50 ring-border shadow-sm ring-1"
                                                            : "hover:bg-accent/30",
                                                    )}
                                                    onClick={() => setSelectedStepIndex("submitted")}
                                                >
                                                    <div
                                                        className={cn(
                                                            "bg-background relative z-10 mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full border-2",
                                                            selectedStepIndex === "submitted" ? "border-primary" : "border-muted-foreground/50",
                                                        )}
                                                    >
                                                        <div
                                                            className={cn(
                                                                "h-2.5 w-2.5 rounded-full",
                                                                selectedStepIndex === "submitted" ? "bg-primary" : "bg-transparent",
                                                            )}
                                                        />
                                                    </div>
                                                    <div className="flex-1 space-y-1">
                                                        <p
                                                            className={cn(
                                                                "text-sm leading-none font-medium",
                                                                selectedStepIndex === "submitted" ? "text-primary" : "",
                                                            )}
                                                        >
                                                            {pipelineForm.data.submitted_label || "Submitted"}
                                                        </p>
                                                        <p className="text-muted-foreground text-xs">Initial entry state</p>
                                                    </div>
                                                </div>

                                                {/* Steps Nodes */}
                                                {pipelineForm.data.steps.map((step, index) => (
                                                    <div
                                                        key={index}
                                                        className={cn(
                                                            "group relative flex cursor-pointer items-start gap-3 rounded-lg p-3 transition-all",
                                                            selectedStepIndex === index
                                                                ? "bg-accent/50 ring-border shadow-sm ring-1"
                                                                : "hover:bg-accent/30",
                                                        )}
                                                        onClick={() => setSelectedStepIndex(index)}
                                                    >
                                                        <div
                                                            className={cn(
                                                                "bg-background relative z-10 mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full border-2",
                                                                selectedStepIndex === index ? "border-primary" : "border-muted-foreground/50",
                                                            )}
                                                        >
                                                            <div
                                                                className={cn(
                                                                    "h-2.5 w-2.5 rounded-full",
                                                                    selectedStepIndex === index ? "bg-primary" : "bg-transparent",
                                                                )}
                                                            />
                                                        </div>
                                                        <div className="flex-1 space-y-1">
                                                            <div className="flex items-center justify-between">
                                                                <p
                                                                    className={cn(
                                                                        "line-clamp-1 text-sm leading-none font-medium",
                                                                        selectedStepIndex === index ? "text-primary" : "",
                                                                    )}
                                                                >
                                                                    {step.label || `Step ${index + 1}`}
                                                                </p>
                                                                <div className="-mt-1 flex opacity-0 transition-opacity group-hover:opacity-100">
                                                                    <Button
                                                                        size="icon"
                                                                        variant="ghost"
                                                                        className="h-6 w-6 shrink-0"
                                                                        onClick={(e) => {
                                                                            e.stopPropagation();
                                                                            movePipelineStep(index, "up");
                                                                        }}
                                                                        disabled={index === 0}
                                                                    >
                                                                        <ArrowUp className="h-3 w-3" />
                                                                    </Button>
                                                                    <Button
                                                                        size="icon"
                                                                        variant="ghost"
                                                                        className="h-6 w-6 shrink-0"
                                                                        onClick={(e) => {
                                                                            e.stopPropagation();
                                                                            movePipelineStep(index, "down");
                                                                        }}
                                                                        disabled={index === pipelineForm.data.steps.length - 1}
                                                                    >
                                                                        <ArrowDown className="h-3 w-3" />
                                                                    </Button>
                                                                </div>
                                                            </div>
                                                            <p className="text-muted-foreground line-clamp-1 text-xs">{step.status || "No status"}</p>
                                                            <div className="mt-2 flex flex-wrap gap-1.5">
                                                                {pipelineForm.data.entry_step_key === step.key && (
                                                                    <Badge
                                                                        variant="outline"
                                                                        className="h-4 border-blue-200 bg-blue-50 px-1.5 text-[10px] text-blue-700 dark:border-blue-800 dark:bg-blue-900/20 dark:text-blue-400"
                                                                    >
                                                                        Entry Point
                                                                    </Badge>
                                                                )}
                                                                {pipelineForm.data.completion_step_key === step.key && (
                                                                    <Badge
                                                                        variant="outline"
                                                                        className="h-4 border-green-200 bg-green-50 px-1.5 text-[10px] text-green-700 dark:border-green-800 dark:bg-green-900/20 dark:text-green-400"
                                                                    >
                                                                        Completion
                                                                    </Badge>
                                                                )}
                                                                <Badge
                                                                    variant="secondary"
                                                                    className={cn(
                                                                        "h-4 px-1.5 text-[10px] opacity-80",
                                                                        step.color === "indigo"
                                                                            ? ""
                                                                            : `bg-${step.color}-100 text-${step.color}-800 dark:bg-${step.color}-900/30 dark:text-${step.color}-300`,
                                                                    )}
                                                                >
                                                                    {step.color}
                                                                </Badge>
                                                            </div>
                                                        </div>
                                                    </div>
                                                ))}

                                                {/* Final Node (visual only) */}
                                                <div className="relative flex items-start gap-3 rounded-lg p-3 opacity-60">
                                                    <div className="bg-background border-muted-foreground relative z-10 mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full border-2 border-dashed">
                                                        <Check className="text-muted-foreground h-3 w-3" />
                                                    </div>
                                                    <div className="flex-1 space-y-1">
                                                        <p className="text-muted-foreground text-sm leading-none font-medium">End of Journey</p>
                                                        <p className="text-muted-foreground text-xs">Records reach their final state</p>
                                                    </div>
                                                </div>
                                            </div>
                                        </CardContent>
                                    </Card>

                                    {/* Right Column: Step Editor */}
                                    <div className="space-y-6 lg:col-span-8">
                                        {selectedStepIndex === "submitted" && (
                                            <Card className="border-primary/20 h-full border-2 shadow-sm transition-all">
                                                <CardHeader className="bg-muted/30 border-b pb-4">
                                                    <div className="flex items-center gap-2">
                                                        <div className="bg-primary/10 rounded-md p-2">
                                                            <Info className="text-primary h-5 w-5" />
                                                        </div>
                                                        <div>
                                                            <CardTitle className="text-lg">Submitted Step Config</CardTitle>
                                                            <CardDescription>This is the system default state for new enrollments.</CardDescription>
                                                        </div>
                                                    </div>
                                                </CardHeader>
                                                <CardContent className="pt-6">
                                                    <div className="space-y-4">
                                                        <div className="space-y-2">
                                                            <Label htmlFor="submitted_label">Display Label</Label>
                                                            <Input
                                                                id="submitted_label"
                                                                value={pipelineForm.data.submitted_label}
                                                                onChange={(e) => pipelineForm.setData("submitted_label", e.target.value)}
                                                                className="max-w-md"
                                                                placeholder="e.g., Application Received"
                                                            />
                                                            <p className="text-muted-foreground text-xs">
                                                                This label is shown to students immediately after submitting their form.
                                                            </p>
                                                        </div>
                                                    </div>
                                                </CardContent>
                                            </Card>
                                        )}

                                        {typeof selectedStepIndex === "number" && pipelineForm.data.steps[selectedStepIndex] && (
                                            <Card className="border-primary/20 animate-in fade-in zoom-in-95 h-full border-2 shadow-sm transition-all duration-200">
                                                <CardHeader className="bg-muted/30 border-b pb-4">
                                                    <div className="flex items-center justify-between">
                                                        <div className="flex items-center gap-2">
                                                            <div className="bg-primary/10 rounded-md p-2">
                                                                <Zap className="text-primary h-5 w-5" />
                                                            </div>
                                                            <div>
                                                                <CardTitle className="text-lg">
                                                                    Edit Step:{" "}
                                                                    {pipelineForm.data.steps[selectedStepIndex].label ||
                                                                        `Step ${selectedStepIndex + 1}`}
                                                                </CardTitle>
                                                                <CardDescription>
                                                                    Configure rules and identifiers for this workflow stage.
                                                                </CardDescription>
                                                            </div>
                                                        </div>
                                                        <Button
                                                            variant="destructive"
                                                            size="sm"
                                                            onClick={() => {
                                                                removePipelineStep(selectedStepIndex);
                                                                setSelectedStepIndex(Math.max(0, selectedStepIndex - 1));
                                                            }}
                                                        >
                                                            <Trash2 className="mr-2 h-4 w-4" /> Delete Step
                                                        </Button>
                                                    </div>
                                                </CardHeader>
                                                <CardContent className="space-y-8 pt-6">
                                                    <div className="grid gap-6 sm:grid-cols-2">
                                                        <div className="space-y-2">
                                                            <Label htmlFor="step_key">Internal Key</Label>
                                                            <Input
                                                                id="step_key"
                                                                value={pipelineForm.data.steps[selectedStepIndex].key}
                                                                onChange={(e) => updatePipelineStep(selectedStepIndex, "key", e.target.value)}
                                                                placeholder="e.g., dept_eval"
                                                            />
                                                            <p className="text-muted-foreground text-xs">Unique identifier used in code/API.</p>
                                                        </div>
                                                        <div className="space-y-2">
                                                            <Label htmlFor="step_action">Action Type</Label>
                                                            <Select
                                                                value={pipelineForm.data.steps[selectedStepIndex].action_type || "standard"}
                                                                onValueChange={(value) => updatePipelineStep(selectedStepIndex, "action_type", value)}
                                                            >
                                                                <SelectTrigger id="step_action">
                                                                    <SelectValue />
                                                                </SelectTrigger>
                                                                <SelectContent>
                                                                    {stepTypeOptions.map((option) => (
                                                                        <SelectItem key={option.value} value={option.value}>
                                                                            <div className="flex items-center gap-2">
                                                                                {option.value === "standard" && (
                                                                                    <List className="text-muted-foreground h-4 w-4" />
                                                                                )}
                                                                                {option.value === "department_verification" && (
                                                                                    <CheckCircle2 className="h-4 w-4 text-blue-500" />
                                                                                )}
                                                                                {option.value === "cashier_verification" && (
                                                                                    <Banknote className="h-4 w-4 text-green-500" />
                                                                                )}
                                                                                {option.label}
                                                                            </div>
                                                                        </SelectItem>
                                                                    ))}
                                                                </SelectContent>
                                                            </Select>
                                                            <p className="text-muted-foreground text-xs">
                                                                Defines UI behavior when processing records.
                                                            </p>
                                                        </div>
                                                    </div>

                                                    <div className="grid gap-6 sm:grid-cols-2">
                                                        <div className="space-y-2">
                                                            <Label htmlFor="step_status">Status Value</Label>
                                                            <Input
                                                                id="step_status"
                                                                value={pipelineForm.data.steps[selectedStepIndex].status}
                                                                onChange={(e) => updatePipelineStep(selectedStepIndex, "status", e.target.value)}
                                                                placeholder="e.g., Pending Eval"
                                                            />
                                                            <p className="text-muted-foreground text-xs">Exact string stored in database.</p>
                                                        </div>
                                                        <div className="space-y-2">
                                                            <Label htmlFor="step_label">Display Label</Label>
                                                            <Input
                                                                id="step_label"
                                                                value={pipelineForm.data.steps[selectedStepIndex].label}
                                                                onChange={(e) => updatePipelineStep(selectedStepIndex, "label", e.target.value)}
                                                                placeholder="e.g., Department Evaluation"
                                                            />
                                                            <p className="text-muted-foreground text-xs">Human-readable name shown to users.</p>
                                                        </div>
                                                    </div>

                                                    <Separator />

                                                    <div className="grid gap-6 sm:grid-cols-2">
                                                        <div className="space-y-4">
                                                            <div>
                                                                <Label>Badge Color</Label>
                                                                <p className="text-muted-foreground mb-3 text-xs">
                                                                    Color theme used for pills and badges.
                                                                </p>
                                                                <div className="flex flex-wrap gap-2">
                                                                    {colorOptions.map((color) => {
                                                                        const isSelected = pipelineForm.data.steps[selectedStepIndex].color === color;
                                                                        return (
                                                                            <button
                                                                                key={color}
                                                                                type="button"
                                                                                onClick={() => updatePipelineStep(selectedStepIndex, "color", color)}
                                                                                className={cn(
                                                                                    "flex h-8 w-8 items-center justify-center rounded-full border-2 transition-all",
                                                                                    isSelected
                                                                                        ? "border-primary ring-primary/20 scale-110 ring-2"
                                                                                        : "border-transparent hover:scale-110",
                                                                                    `bg-${color}-500 dark:bg-${color}-600`,
                                                                                )}
                                                                                title={color}
                                                                            >
                                                                                {isSelected && (
                                                                                    <Check className="h-4 w-4 text-white drop-shadow-sm" />
                                                                                )}
                                                                            </button>
                                                                        );
                                                                    })}
                                                                </div>
                                                            </div>

                                                            <div className="space-y-4 pt-4">
                                                                <Label>Milestone Assignments</Label>
                                                                <div className="bg-card hover:bg-accent/30 flex items-center justify-between rounded-lg border p-3 transition-colors">
                                                                    <div className="space-y-0.5">
                                                                        <Label className="cursor-pointer text-sm" htmlFor="toggle_entry">
                                                                            Is Entry Step
                                                                        </Label>
                                                                        <p className="text-muted-foreground text-xs">
                                                                            First step records enter after submission
                                                                        </p>
                                                                    </div>
                                                                    <Switch
                                                                        id="toggle_entry"
                                                                        checked={
                                                                            pipelineForm.data.entry_step_key ===
                                                                            pipelineForm.data.steps[selectedStepIndex].key
                                                                        }
                                                                        onCheckedChange={(checked) =>
                                                                            checked &&
                                                                            pipelineForm.setData(
                                                                                "entry_step_key",
                                                                                pipelineForm.data.steps[selectedStepIndex].key,
                                                                            )
                                                                        }
                                                                    />
                                                                </div>
                                                                <div className="bg-card hover:bg-accent/30 flex items-center justify-between rounded-lg border p-3 transition-colors">
                                                                    <div className="space-y-0.5">
                                                                        <Label className="cursor-pointer text-sm" htmlFor="toggle_completion">
                                                                            Is Completion Step
                                                                        </Label>
                                                                        <p className="text-muted-foreground text-xs">
                                                                            Final step signifying enrollment success
                                                                        </p>
                                                                    </div>
                                                                    <Switch
                                                                        id="toggle_completion"
                                                                        checked={
                                                                            pipelineForm.data.completion_step_key ===
                                                                            pipelineForm.data.steps[selectedStepIndex].key
                                                                        }
                                                                        onCheckedChange={(checked) =>
                                                                            checked &&
                                                                            pipelineForm.setData(
                                                                                "completion_step_key",
                                                                                pipelineForm.data.steps[selectedStepIndex].key,
                                                                            )
                                                                        }
                                                                    />
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <div className="bg-muted/20 space-y-3 rounded-lg border p-4">
                                                            <div>
                                                                <Label>Allowed Roles (Access Control)</Label>
                                                                <p className="text-muted-foreground mb-3 text-xs">
                                                                    Which admin roles can process applicants at this step?
                                                                </p>
                                                            </div>
                                                            <div className="flex gap-2">
                                                                <Combobox
                                                                    options={roleComboboxOptions}
                                                                    value={selectedRoleByStep[selectedStepIndex] || ""}
                                                                    onValueChange={(value) =>
                                                                        setSelectedRoleByStep((current) => ({
                                                                            ...current,
                                                                            [selectedStepIndex]: value,
                                                                        }))
                                                                    }
                                                                    placeholder="Select a role..."
                                                                    searchPlaceholder="Search roles..."
                                                                    emptyText="No roles found."
                                                                />
                                                                <Button
                                                                    type="button"
                                                                    variant="secondary"
                                                                    onClick={() => addRoleToStep(selectedStepIndex)}
                                                                    disabled={!selectedRoleByStep[selectedStepIndex]}
                                                                >
                                                                    Add
                                                                </Button>
                                                            </div>
                                                            <div className="flex flex-col gap-2 pt-2">
                                                                {!pipelineForm.data.steps[selectedStepIndex].allowed_roles ||
                                                                pipelineForm.data.steps[selectedStepIndex].allowed_roles.length === 0 ? (
                                                                    <div className="text-muted-foreground bg-background rounded-md border border-dashed p-3 text-center text-sm">
                                                                        No roles restricted. Accessible to all admins by default.
                                                                    </div>
                                                                ) : (
                                                                    pipelineForm.data.steps[selectedStepIndex].allowed_roles.map((roleName) => (
                                                                        <div
                                                                            key={roleName}
                                                                            className="bg-background flex items-center justify-between rounded-md border p-2 text-sm shadow-sm"
                                                                        >
                                                                            <span className="flex items-center gap-2">
                                                                                <div className="bg-primary/50 h-2 w-2 rounded-full" />
                                                                                {roleName}
                                                                            </span>
                                                                            <Button
                                                                                type="button"
                                                                                variant="ghost"
                                                                                size="icon"
                                                                                className="text-muted-foreground hover:text-destructive hover:bg-destructive/10 h-6 w-6"
                                                                                onClick={() => removeRoleFromStep(selectedStepIndex, roleName)}
                                                                            >
                                                                                <Trash2 className="h-3 w-3" />
                                                                            </Button>
                                                                        </div>
                                                                    ))
                                                                )}
                                                            </div>
                                                        </div>
                                                    </div>
                                                </CardContent>
                                            </Card>
                                        )}

                                        {selectedStepIndex === null && (
                                            <Card className="bg-muted/20 flex h-full min-h-[400px] items-center justify-center border-dashed">
                                                <CardContent className="text-muted-foreground flex flex-col items-center justify-center space-y-4 p-12 text-center">
                                                    <div className="bg-muted/50 mb-2 flex h-16 w-16 items-center justify-center rounded-full">
                                                        <Activity className="text-muted-foreground/50 h-8 w-8" />
                                                    </div>
                                                    <div>
                                                        <p className="text-foreground text-lg font-medium">No Step Selected</p>
                                                        <p className="mt-1 max-w-sm text-sm">
                                                            Select a step from the journey timeline on the left to edit its configuration.
                                                        </p>
                                                    </div>
                                                </CardContent>
                                            </Card>
                                        )}
                                    </div>
                                </div>
                            </TabsContent>

                            {/* ANALYTICS CARDS TAB */}
                            <TabsContent value="stats" className="mt-6 space-y-6">
                                <div className="mb-4 flex items-center justify-between">
                                    <div className="space-y-1">
                                        <h3 className="text-lg font-medium">Dashboard Analytics Cards</h3>
                                        <p className="text-muted-foreground text-sm">
                                            Define which metrics to show on the main enrollment dashboard.
                                        </p>
                                    </div>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() => {
                                            addStatsCard();
                                            setEditingCardIndex(pipelineForm.data.enrollment_stats.cards.length);
                                        }}
                                    >
                                        <Plus className="mr-2 h-4 w-4" /> Add Card
                                    </Button>
                                </div>

                                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                                    {pipelineForm.data.enrollment_stats.cards.map((card, index) => {
                                        const metricLabel = statsMetricOptions.find((m) => m.value === card.metric)?.label || card.metric;
                                        return (
                                            <div
                                                key={index}
                                                className={cn(
                                                    "group bg-card hover:border-primary relative cursor-pointer overflow-hidden rounded-xl border p-5 shadow-sm transition-all hover:shadow-md",
                                                    editingCardIndex === index ? "ring-primary border-primary ring-2" : "",
                                                )}
                                                onClick={() => setEditingCardIndex(index)}
                                            >
                                                <div className="mb-4 flex items-center justify-between">
                                                    <div
                                                        className={cn(
                                                            "rounded-lg p-2",
                                                            card.color === "indigo"
                                                                ? "bg-indigo-100 text-indigo-600 dark:bg-indigo-900/30 dark:text-indigo-400"
                                                                : `bg-${card.color}-100 dark:bg-${card.color}-900/30 text-${card.color}-600 dark:text-${card.color}-400`,
                                                        )}
                                                    >
                                                        <Activity className="h-4 w-4" />
                                                    </div>
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        className="text-destructive hover:bg-destructive/10 h-6 w-6 opacity-0 transition-opacity group-hover:opacity-100"
                                                        onClick={(e) => {
                                                            e.stopPropagation();
                                                            removeStatsCard(index);
                                                            if (editingCardIndex === index) setEditingCardIndex(null);
                                                        }}
                                                    >
                                                        <Trash2 className="h-3 w-3" />
                                                    </Button>
                                                </div>
                                                <div className="space-y-1">
                                                    <p className="text-muted-foreground text-sm font-medium">{card.label || "Unnamed Card"}</p>
                                                    <h4 className="font-mono text-2xl font-bold">--</h4>
                                                </div>
                                                <div className="text-muted-foreground mt-4 flex items-center justify-between border-t border-dashed pt-4 text-xs">
                                                    <span>{metricLabel}</span>
                                                    {card.metric === "status_count" && (
                                                        <Badge variant="secondary" className="h-4 px-1 text-[10px]">
                                                            {card.statuses?.length || 0} tracks
                                                        </Badge>
                                                    )}
                                                </div>
                                            </div>
                                        );
                                    })}

                                    <div
                                        className="text-muted-foreground hover:bg-accent/50 hover:text-foreground flex min-h-[160px] cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed p-5 transition-all"
                                        onClick={() => {
                                            addStatsCard();
                                            setEditingCardIndex(pipelineForm.data.enrollment_stats.cards.length);
                                        }}
                                    >
                                        <div className="bg-muted/50 mb-3 flex h-10 w-10 items-center justify-center rounded-full">
                                            <Plus className="h-5 w-5" />
                                        </div>
                                        <p className="font-medium">Create New Card</p>
                                    </div>
                                </div>

                                {/* EDIT DIALOG FOR STATS CARDS */}
                                <Dialog open={editingCardIndex !== null} onOpenChange={(open) => !open && setEditingCardIndex(null)}>
                                    <DialogContent className="sm:max-w-[600px]">
                                        <DialogHeader>
                                            <DialogTitle>Edit Analytics Card</DialogTitle>
                                            <DialogDescription>
                                                Configure how this metric is calculated and displayed on the dashboard.
                                            </DialogDescription>
                                        </DialogHeader>
                                        {editingCardIndex !== null && pipelineForm.data.enrollment_stats.cards[editingCardIndex] && (
                                            <div className="space-y-6 py-4">
                                                <div className="grid gap-4 sm:grid-cols-2">
                                                    <div className="space-y-2">
                                                        <Label htmlFor="edit_stats_key">Internal Key</Label>
                                                        <Input
                                                            id="edit_stats_key"
                                                            value={pipelineForm.data.enrollment_stats.cards[editingCardIndex].key}
                                                            onChange={(e) => updateStatsCard(editingCardIndex, "key", e.target.value)}
                                                        />
                                                    </div>
                                                    <div className="space-y-2">
                                                        <Label htmlFor="edit_stats_label">Display Title</Label>
                                                        <Input
                                                            id="edit_stats_label"
                                                            value={pipelineForm.data.enrollment_stats.cards[editingCardIndex].label}
                                                            onChange={(e) => updateStatsCard(editingCardIndex, "label", e.target.value)}
                                                        />
                                                    </div>
                                                </div>

                                                <div className="grid gap-4 sm:grid-cols-2">
                                                    <div className="space-y-2">
                                                        <Label htmlFor="edit_stats_metric">Metric Type</Label>
                                                        <Select
                                                            value={pipelineForm.data.enrollment_stats.cards[editingCardIndex].metric}
                                                            onValueChange={(value) => updateStatsCard(editingCardIndex, "metric", value)}
                                                        >
                                                            <SelectTrigger id="edit_stats_metric">
                                                                <SelectValue />
                                                            </SelectTrigger>
                                                            <SelectContent>
                                                                {statsMetricOptions.map((metric) => (
                                                                    <SelectItem key={metric.value} value={metric.value}>
                                                                        {metric.label}
                                                                    </SelectItem>
                                                                ))}
                                                            </SelectContent>
                                                        </Select>
                                                    </div>
                                                    <div className="space-y-2">
                                                        <Label>Theme Color</Label>
                                                        <Select
                                                            value={pipelineForm.data.enrollment_stats.cards[editingCardIndex].color}
                                                            onValueChange={(value) => updateStatsCard(editingCardIndex, "color", value)}
                                                        >
                                                            <SelectTrigger>
                                                                <SelectValue />
                                                            </SelectTrigger>
                                                            <SelectContent>
                                                                {colorOptions.map((color) => (
                                                                    <SelectItem key={color} value={color}>
                                                                        <div className="flex items-center gap-2">
                                                                            <div className={cn("h-3 w-3 rounded-full", `bg-${color}-500`)} />
                                                                            <span className="capitalize">{color}</span>
                                                                        </div>
                                                                    </SelectItem>
                                                                ))}
                                                            </SelectContent>
                                                        </Select>
                                                    </div>
                                                </div>

                                                {pipelineForm.data.enrollment_stats.cards[editingCardIndex].metric === "status_count" && (
                                                    <div className="bg-muted/20 space-y-3 rounded-lg border p-4">
                                                        <div>
                                                            <Label>Tracked Statuses</Label>
                                                            <p className="text-muted-foreground text-xs">
                                                                Select the workflow statuses to include in this metric.
                                                            </p>
                                                        </div>
                                                        <div className="flex flex-wrap gap-2">
                                                            {pipelineForm.data.steps.map((step, stepIndex) => {
                                                                const selected = (
                                                                    pipelineForm.data.enrollment_stats.cards[editingCardIndex].statuses || []
                                                                ).includes(step.status);
                                                                return (
                                                                    <Button
                                                                        key={stepIndex}
                                                                        type="button"
                                                                        variant={selected ? "default" : "outline"}
                                                                        size="sm"
                                                                        onClick={() => toggleStatsCardStatus(editingCardIndex, step.status)}
                                                                        className={selected ? "ring-primary/20 ring-2" : "bg-background"}
                                                                    >
                                                                        {selected && <Check className="mr-1 h-3 w-3" />}
                                                                        {step.label || step.status || `Step ${stepIndex + 1}`}
                                                                    </Button>
                                                                );
                                                            })}
                                                        </div>
                                                    </div>
                                                )}
                                            </div>
                                        )}
                                        <DialogFooter>
                                            <Button type="button" onClick={() => setEditingCardIndex(null)}>
                                                Done
                                            </Button>
                                        </DialogFooter>
                                    </DialogContent>
                                </Dialog>
                            </TabsContent>
                        </Tabs>
                    </TabsContent>

                    {/* SEO Tab */}
                    <TabsContent value="seo" className="space-y-6">
                        <Card>
                            <CardHeader>
                                <div className="flex items-start justify-between">
                                    <div className="space-y-1">
                                        <CardTitle>SEO & Metadata Settings</CardTitle>
                                        <CardDescription>Configure site title, description, and keywords for search engines</CardDescription>
                                    </div>
                                    <Button
                                        onClick={() =>
                                            handleSubmit(
                                                seoForm,
                                                "administrators.system-management.seo.update",
                                                "SEO settings updated successfully",
                                                "Failed to update SEO settings",
                                            )
                                        }
                                        disabled={seoForm.processing}
                                    >
                                        {seoForm.processing ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <Save className="mr-2 h-4 w-4" />}
                                        Save Changes
                                    </Button>
                                </div>
                            </CardHeader>
                            <CardContent className="space-y-6">
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="space-y-2">
                                        <Label htmlFor="site_name">Site Name</Label>
                                        <Input
                                            id="site_name"
                                            value={seoForm.data.site_name}
                                            onChange={(e) => seoForm.setData("site_name", e.target.value)}
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="seo_title">Default Title</Label>
                                        <Input
                                            id="seo_title"
                                            value={seoForm.data.seo_title}
                                            onChange={(e) => seoForm.setData("seo_title", e.target.value)}
                                        />
                                    </div>
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="site_description">Meta Description</Label>
                                    <Textarea
                                        id="site_description"
                                        rows={3}
                                        value={seoForm.data.site_description}
                                        onChange={(e) => seoForm.setData("site_description", e.target.value)}
                                        placeholder="Brief summary of your site (155 chars recommended)"
                                    />
                                    <p className="text-muted-foreground text-right text-xs">
                                        {seoForm.data.site_description?.length || 0} / 155 characters
                                    </p>
                                </div>

                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="space-y-2">
                                        <Label htmlFor="robots">Meta Robots</Label>
                                        <Select
                                            value={seoForm.data.seo_metadata.robots}
                                            onValueChange={(val) => seoForm.setData("seo_metadata", { ...seoForm.data.seo_metadata, robots: val })}
                                        >
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select indexing rule" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="index, follow">Index, Follow (Public)</SelectItem>
                                                <SelectItem value="noindex, nofollow">No Index, No Follow (Private)</SelectItem>
                                                <SelectItem value="index, nofollow">Index, No Follow</SelectItem>
                                                <SelectItem value="noindex, follow">No Index, Follow</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="canonical">Canonical URL</Label>
                                        <Input
                                            id="canonical"
                                            placeholder="https://example.com"
                                            value={seoForm.data.seo_metadata.canonical_url}
                                            onChange={(e) =>
                                                seoForm.setData("seo_metadata", { ...seoForm.data.seo_metadata, canonical_url: e.target.value })
                                            }
                                        />
                                    </div>
                                </div>

                                <Separator />

                                <div className="space-y-4">
                                    <h3 className="font-medium">Social Media (Open Graph)</h3>

                                    <div className="space-y-2">
                                        <Label htmlFor="og_image">Social Share Image URL</Label>
                                        <Input
                                            id="og_image"
                                            placeholder="https://example.com/og-image.jpg"
                                            value={seoForm.data.seo_metadata.og_image}
                                            onChange={(e) =>
                                                seoForm.setData("seo_metadata", { ...seoForm.data.seo_metadata, og_image: e.target.value })
                                            }
                                        />
                                    </div>

                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <div className="space-y-2">
                                            <Label htmlFor="twitter_card">Twitter Card Type</Label>
                                            <Select
                                                value={seoForm.data.seo_metadata.twitter_card}
                                                onValueChange={(val) =>
                                                    seoForm.setData("seo_metadata", { ...seoForm.data.seo_metadata, twitter_card: val })
                                                }
                                            >
                                                <SelectTrigger>
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="summary">Summary</SelectItem>
                                                    <SelectItem value="summary_large_image">Summary Large Image</SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </div>
                                        <div className="space-y-2">
                                            <Label htmlFor="twitter_handle">Twitter Handle</Label>
                                            <Input
                                                id="twitter_handle"
                                                placeholder="@username"
                                                value={seoForm.data.seo_metadata.twitter_handle}
                                                onChange={(e) =>
                                                    seoForm.setData("seo_metadata", { ...seoForm.data.seo_metadata, twitter_handle: e.target.value })
                                                }
                                            />
                                        </div>
                                    </div>
                                </div>

                                <Separator />

                                <div className="space-y-2">
                                    <Label htmlFor="seo_keywords">Keywords</Label>
                                    <Textarea
                                        id="seo_keywords"
                                        rows={2}
                                        value={seoForm.data.seo_keywords}
                                        onChange={(e) => seoForm.setData("seo_keywords", e.target.value)}
                                        placeholder="education, school, enrollment, philippines"
                                    />
                                </div>
                            </CardContent>
                        </Card>

                        {/* SEO Preview */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Search Preview</CardTitle>
                                <CardDescription>How your site appears in search results</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div className="rounded-lg border bg-white p-4 dark:bg-zinc-950">
                                    <div className="text-muted-foreground mb-1 flex items-center gap-2 text-xs">
                                        <div className="bg-muted h-4 w-4 rounded-full" />
                                        <span>yourdomain.com</span>
                                    </div>
                                    <h4 className="line-clamp-1 cursor-pointer text-lg font-medium text-blue-600 hover:underline">
                                        {seoForm.data.seo_title || seoForm.data.site_name || "Page Title"}
                                    </h4>
                                    <p className="text-muted-foreground mt-1 line-clamp-2 text-sm">
                                        {seoForm.data.site_description || "No description provided. Add a meta description to improve SEO."}
                                    </p>
                                </div>
                            </CardContent>
                        </Card>
                    </TabsContent>

                    {/* Brand Tab */}
                    <TabsContent value="brand" className="space-y-6">
                        {/* Application Identity */}
                        <Card>
                            <CardHeader>
                                <div className="flex items-start justify-between">
                                    <div className="space-y-1">
                                        <CardTitle>Application Identity</CardTitle>
                                        <CardDescription>Configure how your application identifies itself across the system</CardDescription>
                                    </div>
                                    <Button
                                        onClick={() =>
                                            handleSubmit(
                                                brandForm,
                                                "administrators.system-management.brand.update",
                                                "Brand settings updated successfully",
                                                "Failed to update brand settings",
                                                true,
                                            )
                                        }
                                        disabled={brandForm.processing}
                                    >
                                        {brandForm.processing ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <Save className="mr-2 h-4 w-4" />}
                                        Save Changes
                                    </Button>
                                </div>
                            </CardHeader>
                            <CardContent className="space-y-6">
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="space-y-2">
                                        <Label htmlFor="app_name">Application Name</Label>
                                        <Input
                                            id="app_name"
                                            value={brandForm.data.app_name}
                                            onChange={(e) => brandForm.setData("app_name", e.target.value)}
                                            placeholder="My School Portal"
                                        />
                                        <p className="text-muted-foreground text-xs">Full application name shown in headers and titles</p>
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="app_short_name">Short Name</Label>
                                        <Input
                                            id="app_short_name"
                                            value={brandForm.data.app_short_name}
                                            onChange={(e) => brandForm.setData("app_short_name", e.target.value)}
                                            placeholder="PORTAL"
                                        />
                                        <p className="text-muted-foreground text-xs">Used in compact spaces and mobile views</p>
                                    </div>
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="tagline">Tagline</Label>
                                    <Input
                                        id="tagline"
                                        value={brandForm.data.tagline}
                                        onChange={(e) => brandForm.setData("tagline", e.target.value)}
                                        placeholder="Your Campus, Your Connection"
                                    />
                                    <p className="text-muted-foreground text-xs">A short slogan or description for the application</p>
                                </div>
                            </CardContent>
                        </Card>

                        {/* Organization Details */}
                        <Card>
                            <CardHeader>
                                <CardTitle>Organization Details</CardTitle>
                                <CardDescription>Information about your institution displayed in documents and communications</CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-6">
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="space-y-2">
                                        <Label htmlFor="organization_name">Organization Name</Label>
                                        <Input
                                            id="organization_name"
                                            value={brandForm.data.organization_name}
                                            onChange={(e) => brandForm.setData("organization_name", e.target.value)}
                                            placeholder="University of Technology"
                                        />
                                        <p className="text-muted-foreground text-xs">Full legal name of the institution</p>
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="organization_short_name">Organization Short Name</Label>
                                        <Input
                                            id="organization_short_name"
                                            value={brandForm.data.organization_short_name}
                                            onChange={(e) => brandForm.setData("organization_short_name", e.target.value)}
                                            placeholder="UOT"
                                        />
                                        <p className="text-muted-foreground text-xs">Abbreviation or acronym</p>
                                    </div>
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="organization_address">Organization Address</Label>
                                    <Textarea
                                        id="organization_address"
                                        rows={2}
                                        value={brandForm.data.organization_address}
                                        onChange={(e) => brandForm.setData("organization_address", e.target.value)}
                                        placeholder="123 Main Street, City, Province, Philippines"
                                    />
                                    <p className="text-muted-foreground text-xs">Used in official documents and footers</p>
                                </div>

                                <Separator />

                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="space-y-2">
                                        <Label htmlFor="support_email">Support Email</Label>
                                        <Input
                                            id="support_email"
                                            type="email"
                                            value={brandForm.data.support_email}
                                            onChange={(e) => brandForm.setData("support_email", e.target.value)}
                                            placeholder="support@school.edu"
                                        />
                                        <p className="text-muted-foreground text-xs">Contact email for user support</p>
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="support_phone">Support Phone</Label>
                                        <Input
                                            id="support_phone"
                                            type="tel"
                                            value={brandForm.data.support_phone}
                                            onChange={(e) => brandForm.setData("support_phone", e.target.value)}
                                            placeholder="+63 (02) 1234-5678"
                                        />
                                        <p className="text-muted-foreground text-xs">Contact phone number for support</p>
                                    </div>
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="copyright_text">Copyright Text</Label>
                                    <Input
                                        id="copyright_text"
                                        value={brandForm.data.copyright_text}
                                        onChange={(e) => brandForm.setData("copyright_text", e.target.value)}
                                        placeholder="University of Technology. All rights reserved."
                                    />
                                    <p className="text-muted-foreground text-xs">Displayed in footer. Year is added automatically.</p>
                                </div>
                            </CardContent>
                        </Card>

                        {/* Visual Identity */}
                        <Card>
                            <CardHeader>
                                <CardTitle>Visual Identity</CardTitle>
                                <CardDescription>Customize logos, colors, and regional settings</CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-6">
                                <div className="space-y-4">
                                    <h3 className="text-sm font-medium">Brand Assets</h3>
                                    <div className="grid gap-6 sm:grid-cols-2">
                                        <div className="space-y-2">
                                            <Label htmlFor="logo">System Logo</Label>
                                            <div className="flex flex-col gap-3">
                                                <div className="bg-muted/50 flex h-24 w-full items-center justify-center rounded-lg border border-dashed p-4">
                                                    {branding?.logo ? (
                                                        <img
                                                            src={branding.logo}
                                                            alt="Current Logo"
                                                            className="max-h-full max-w-full object-contain"
                                                        />
                                                    ) : (
                                                        <img
                                                            src="/web-app-manifest-192x192.png"
                                                            alt="Default Logo"
                                                            className="max-h-full max-w-full object-contain opacity-50"
                                                        />
                                                    )}
                                                </div>
                                                <div className="space-y-1">
                                                    <Input
                                                        id="logo"
                                                        type="file"
                                                        accept="image/*"
                                                        onChange={(e) => brandForm.setData("logo", e.target.files ? e.target.files[0] : null)}
                                                        className="text-xs"
                                                    />
                                                    <p className="text-muted-foreground text-xs">Recommended: 512x512px PNG or SVG</p>
                                                </div>
                                            </div>
                                        </div>

                                        <div className="space-y-2">
                                            <Label htmlFor="favicon">Favicon / App Icon</Label>
                                            <div className="flex flex-col gap-3">
                                                <div className="bg-muted/50 flex h-24 w-full items-center justify-center rounded-lg border border-dashed p-4">
                                                    {branding?.favicon ? (
                                                        <img
                                                            src={branding.favicon}
                                                            alt="Current Favicon"
                                                            className="max-h-full max-w-full object-contain"
                                                        />
                                                    ) : (
                                                        <img
                                                            src="/web-app-manifest-192x192.png"
                                                            alt="Default Favicon"
                                                            className="max-h-full max-w-full object-contain opacity-50"
                                                        />
                                                    )}
                                                </div>
                                                <div className="space-y-1">
                                                    <Input
                                                        id="favicon"
                                                        type="file"
                                                        accept="image/*"
                                                        onChange={(e) => brandForm.setData("favicon", e.target.files ? e.target.files[0] : null)}
                                                        className="text-xs"
                                                    />
                                                    <p className="text-muted-foreground text-xs">Recommended: Square PNG (e.g., 32x32, 192x192)</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <Separator />

                                <div className="space-y-4">
                                    <Label>Theme Color</Label>
                                    <div className="flex items-center gap-4">
                                        <div
                                            className="relative h-14 w-14 cursor-pointer overflow-hidden rounded-lg border-2 shadow-sm"
                                            style={{ backgroundColor: brandForm.data.theme_color || "#000000" }}
                                        >
                                            <input
                                                type="color"
                                                className="absolute inset-0 h-full w-full cursor-pointer opacity-0"
                                                value={brandForm.data.theme_color || "#000000"}
                                                onChange={(e) => brandForm.setData("theme_color", e.target.value)}
                                            />
                                        </div>
                                        <div className="space-y-1">
                                            <Input
                                                value={brandForm.data.theme_color || ""}
                                                onChange={(e) => brandForm.setData("theme_color", e.target.value)}
                                                className="w-32 font-mono text-sm"
                                                placeholder="#000000"
                                            />
                                            <p className="text-muted-foreground text-xs">Click the color box to pick</p>
                                        </div>
                                    </div>
                                </div>

                                <Separator />

                                <div className="space-y-4">
                                    <Label>Default Currency</Label>
                                    <Select value={brandForm.data.currency} onValueChange={(val) => brandForm.setData("currency", val)}>
                                        <SelectTrigger className="w-64">
                                            <SelectValue placeholder="Select currency" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="PHP">PHP - Philippine Peso (₱)</SelectItem>
                                            <SelectItem value="USD">USD - US Dollar ($)</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <p className="text-muted-foreground text-sm">Used for financial transactions and displays.</p>
                                </div>
                            </CardContent>
                        </Card>

                        {/* Brand Preview */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Brand Preview</CardTitle>
                                <CardDescription>See how your branding looks in the UI</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div className="space-y-6">
                                    {/* Header Preview */}
                                    <div className="space-y-2">
                                        <p className="text-muted-foreground text-sm font-medium">Header Preview</p>
                                        <div
                                            className="flex items-center gap-3 rounded-lg p-4 text-white"
                                            style={{ backgroundColor: brandForm.data.theme_color || "#0f172a" }}
                                        >
                                            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-white/20 font-bold">
                                                {brandForm.data.app_short_name?.charAt(0) || "S"}
                                            </div>
                                            <div>
                                                <h4 className="font-semibold">{brandForm.data.app_name || "School Portal"}</h4>
                                                <p className="text-sm opacity-80">{brandForm.data.tagline || "Your Campus, Your Connection"}</p>
                                            </div>
                                        </div>
                                    </div>

                                    {/* Buttons Preview */}
                                    <div className="space-y-2">
                                        <p className="text-muted-foreground text-sm font-medium">Button Styles</p>
                                        <div className="flex items-center gap-3">
                                            <div
                                                className="flex h-10 items-center justify-center rounded-md px-4 text-sm font-medium text-white"
                                                style={{ backgroundColor: brandForm.data.theme_color || "#000" }}
                                            >
                                                Primary Button
                                            </div>
                                            <div
                                                className="flex h-10 items-center justify-center rounded-md border-2 px-4 text-sm font-medium"
                                                style={{
                                                    borderColor: brandForm.data.theme_color || "#000",
                                                    color: brandForm.data.theme_color || "#000",
                                                }}
                                            >
                                                Outline Button
                                            </div>
                                        </div>
                                    </div>

                                    {/* Footer Preview */}
                                    <div className="space-y-2">
                                        <p className="text-muted-foreground text-sm font-medium">Footer Preview</p>
                                        <div className="bg-muted rounded-lg p-4 text-center">
                                            <p className="text-muted-foreground text-sm">
                                                &copy; {new Date().getFullYear()}{" "}
                                                {brandForm.data.copyright_text || brandForm.data.organization_name || "Your Organization"}
                                            </p>
                                            {brandForm.data.support_email && (
                                                <p className="text-muted-foreground mt-1 text-xs">
                                                    Need help? Contact us at{" "}
                                                    <span style={{ color: brandForm.data.theme_color || "#000" }} className="font-medium">
                                                        {brandForm.data.support_email}
                                                    </span>
                                                </p>
                                            )}
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </TabsContent>

                    {/* Sanity Tab */}
                    <TabsContent value="sanity" className="space-y-6">
                        <Card>
                            <CardHeader>
                                <div className="flex items-start justify-between">
                                    <div className="space-y-1">
                                        <CardTitle>Sanity CMS Connection</CardTitle>
                                        <CardDescription>Connect to Sanity CMS for dynamic content management</CardDescription>
                                    </div>
                                    <Button
                                        onClick={() =>
                                            handleSubmit(
                                                sanityForm,
                                                "administrators.system-management.sanity.update",
                                                "Sanity settings updated successfully",
                                                "Failed to update Sanity settings",
                                            )
                                        }
                                        disabled={sanityForm.processing}
                                    >
                                        {sanityForm.processing ? (
                                            <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                        ) : (
                                            <Save className="mr-2 h-4 w-4" />
                                        )}
                                        Save Changes
                                    </Button>
                                </div>
                            </CardHeader>
                            <CardContent className="space-y-6">
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="space-y-2">
                                        <Label>Project ID</Label>
                                        <Input
                                            value={sanityForm.data.project_id}
                                            onChange={(e) => sanityForm.setData("project_id", e.target.value)}
                                            placeholder="abc123def"
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label>Dataset</Label>
                                        <Input
                                            value={sanityForm.data.dataset}
                                            onChange={(e) => sanityForm.setData("dataset", e.target.value)}
                                            placeholder="production"
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label>API Version</Label>
                                        <Input
                                            value={sanityForm.data.api_version}
                                            onChange={(e) => sanityForm.setData("api_version", e.target.value)}
                                            placeholder="2024-01-01"
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label>API Token</Label>
                                        <Input
                                            type="password"
                                            value={sanityForm.data.token}
                                            onChange={(e) => sanityForm.setData("token", e.target.value)}
                                            placeholder="sk..."
                                        />
                                    </div>
                                </div>

                                <div className="flex items-center justify-between rounded-lg border p-4">
                                    <div className="space-y-0.5">
                                        <Label className="text-base">Use CDN</Label>
                                        <p className="text-muted-foreground text-sm">Enable for faster response times (may serve cached data)</p>
                                    </div>
                                    <Switch checked={sanityForm.data.use_cdn} onCheckedChange={(c) => sanityForm.setData("use_cdn", c)} />
                                </div>
                            </CardContent>
                        </Card>

                        {/* Connection Status */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Connection Status</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="space-y-3">
                                    <div className="flex items-center justify-between py-2">
                                        <span className="text-muted-foreground text-sm">Project ID</span>
                                        <code className="bg-muted rounded px-2 py-1 font-mono text-xs">
                                            {sanityForm.data.project_id || "Not configured"}
                                        </code>
                                    </div>
                                    <Separator />
                                    <div className="flex items-center justify-between py-2">
                                        <span className="text-muted-foreground text-sm">Dataset</span>
                                        <Badge variant="outline">{sanityForm.data.dataset || "Not set"}</Badge>
                                    </div>
                                    <Separator />
                                    <div className="flex items-center justify-between py-2">
                                        <span className="text-muted-foreground text-sm">Status</span>
                                        {sanityForm.data.project_id && sanityForm.data.dataset ? (
                                            <div className="flex items-center gap-2 text-sm font-medium text-green-600">
                                                <div className="h-2 w-2 animate-pulse rounded-full bg-green-500" />
                                                Ready
                                            </div>
                                        ) : (
                                            <div className="flex items-center gap-2 text-sm font-medium text-amber-600">
                                                <AlertCircle className="h-4 w-4" />
                                                Incomplete
                                            </div>
                                        )}
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </TabsContent>

                    {/* Socialite Tab */}
                    <TabsContent value="socialite" className="space-y-6">
                        <Card>
                            <CardHeader>
                                <div className="flex items-start justify-between">
                                    <div className="space-y-1">
                                        <CardTitle>Social Authentication</CardTitle>
                                        <CardDescription>Enable login via Google, Facebook, GitHub, X, and LinkedIn</CardDescription>
                                    </div>
                                    <Button
                                        onClick={() =>
                                            handleSubmit(
                                                socialiteForm,
                                                "administrators.system-management.socialite.update",
                                                "Social auth updated successfully",
                                                "Failed to update",
                                            )
                                        }
                                        disabled={socialiteForm.processing}
                                    >
                                        {socialiteForm.processing ? (
                                            <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                        ) : (
                                            <Save className="mr-2 h-4 w-4" />
                                        )}
                                        Save Changes
                                    </Button>
                                </div>
                            </CardHeader>
                            <CardContent className="space-y-6">
                                <div className="rounded-lg border border-blue-200 bg-blue-50 p-4 dark:border-blue-900/50 dark:bg-blue-900/20">
                                    <div className="flex gap-3">
                                        <Info className="h-5 w-5 shrink-0 text-blue-600 dark:text-blue-400" />
                                        <div className="text-sm">
                                            <p className="font-medium text-blue-800 dark:text-blue-200">Setup Instructions</p>
                                            <p className="mt-1 text-blue-700 dark:text-blue-300">
                                                Create an OAuth application in each provider's developer console. Use this callback URL format:
                                            </p>
                                            <code className="mt-2 block rounded bg-blue-100 px-2 py-1 text-xs dark:bg-blue-900/50">
                                                {window.location.origin}/integrations/&#123;provider&#125;/callback
                                            </code>
                                        </div>
                                    </div>
                                </div>

                                {/* Google */}
                                <div className="space-y-4">
                                    <div className="flex items-center gap-2">
                                        <GoogleIcon className="h-5 w-5" />
                                        <h3 className="font-medium">Google</h3>
                                        {socialiteForm.data.google_client_id && (
                                            <Badge variant="outline" className="border-green-200 bg-green-50 text-green-600">
                                                Configured
                                            </Badge>
                                        )}
                                    </div>
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <div className="space-y-2">
                                            <Label>Client ID</Label>
                                            <Input
                                                value={socialiteForm.data.google_client_id}
                                                onChange={(e) => socialiteForm.setData("google_client_id", e.target.value)}
                                            />
                                        </div>
                                        <div className="space-y-2">
                                            <Label>Client Secret</Label>
                                            <Input
                                                type="password"
                                                value={socialiteForm.data.google_client_secret}
                                                onChange={(e) => socialiteForm.setData("google_client_secret", e.target.value)}
                                            />
                                        </div>
                                    </div>
                                </div>

                                <Separator />

                                {/* Facebook */}
                                <div className="space-y-4">
                                    <div className="flex items-center gap-2">
                                        <FacebookIcon className="h-5 w-5" />
                                        <h3 className="font-medium">Facebook</h3>
                                        {socialiteForm.data.facebook_client_id && (
                                            <Badge variant="outline" className="border-green-200 bg-green-50 text-green-600">
                                                Configured
                                            </Badge>
                                        )}
                                    </div>
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <div className="space-y-2">
                                            <Label>App ID</Label>
                                            <Input
                                                value={socialiteForm.data.facebook_client_id}
                                                onChange={(e) => socialiteForm.setData("facebook_client_id", e.target.value)}
                                            />
                                        </div>
                                        <div className="space-y-2">
                                            <Label>App Secret</Label>
                                            <Input
                                                type="password"
                                                value={socialiteForm.data.facebook_client_secret}
                                                onChange={(e) => socialiteForm.setData("facebook_client_secret", e.target.value)}
                                            />
                                        </div>
                                    </div>
                                </div>

                                <Separator />

                                {/* GitHub */}
                                <div className="space-y-4">
                                    <div className="flex items-center gap-2">
                                        <KeyRound className="h-5 w-5" />
                                        <h3 className="font-medium">GitHub</h3>
                                        {socialiteForm.data.github_client_id && (
                                            <Badge variant="outline" className="border-green-200 bg-green-50 text-green-600">
                                                Configured
                                            </Badge>
                                        )}
                                    </div>
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <div className="space-y-2">
                                            <Label>Client ID</Label>
                                            <Input
                                                value={socialiteForm.data.github_client_id}
                                                onChange={(e) => socialiteForm.setData("github_client_id", e.target.value)}
                                            />
                                        </div>
                                        <div className="space-y-2">
                                            <Label>Client Secret</Label>
                                            <Input
                                                type="password"
                                                value={socialiteForm.data.github_client_secret}
                                                onChange={(e) => socialiteForm.setData("github_client_secret", e.target.value)}
                                            />
                                        </div>
                                    </div>
                                </div>

                                <Separator />

                                {/* Twitter */}
                                <div className="space-y-4">
                                    <div className="flex items-center gap-2">
                                        <AtSign className="h-5 w-5" />
                                        <h3 className="font-medium">Twitter / X</h3>
                                        {socialiteForm.data.twitter_client_id && (
                                            <Badge variant="outline" className="border-green-200 bg-green-50 text-green-600">
                                                Configured
                                            </Badge>
                                        )}
                                    </div>
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <div className="space-y-2">
                                            <Label>Client ID</Label>
                                            <Input
                                                value={socialiteForm.data.twitter_client_id}
                                                onChange={(e) => socialiteForm.setData("twitter_client_id", e.target.value)}
                                            />
                                        </div>
                                        <div className="space-y-2">
                                            <Label>Client Secret</Label>
                                            <Input
                                                type="password"
                                                value={socialiteForm.data.twitter_client_secret}
                                                onChange={(e) => socialiteForm.setData("twitter_client_secret", e.target.value)}
                                            />
                                        </div>
                                    </div>
                                </div>

                                <Separator />

                                {/* LinkedIn */}
                                <div className="space-y-4">
                                    <div className="flex items-center gap-2">
                                        <Link2 className="h-5 w-5 text-blue-700" />
                                        <h3 className="font-medium">LinkedIn</h3>
                                        {socialiteForm.data.linkedin_client_id && (
                                            <Badge variant="outline" className="border-green-200 bg-green-50 text-green-600">
                                                Configured
                                            </Badge>
                                        )}
                                    </div>
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <div className="space-y-2">
                                            <Label>Client ID</Label>
                                            <Input
                                                value={socialiteForm.data.linkedin_client_id}
                                                onChange={(e) => socialiteForm.setData("linkedin_client_id", e.target.value)}
                                            />
                                        </div>
                                        <div className="space-y-2">
                                            <Label>Client Secret</Label>
                                            <Input
                                                type="password"
                                                value={socialiteForm.data.linkedin_client_secret}
                                                onChange={(e) => socialiteForm.setData("linkedin_client_secret", e.target.value)}
                                            />
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </TabsContent>

                    {/* Mail Tab */}
                    <TabsContent value="mail" className="space-y-6">
                        <Card>
                            <CardHeader>
                                <div className="flex items-start justify-between">
                                    <div className="space-y-1">
                                        <CardTitle>Mail Server Settings</CardTitle>
                                        <CardDescription>Configure SMTP for system emails and notifications</CardDescription>
                                    </div>
                                    <Button
                                        onClick={() =>
                                            handleSubmit(
                                                mailForm,
                                                "administrators.system-management.mail.update",
                                                "Mail settings updated successfully",
                                                "Failed to update",
                                            )
                                        }
                                        disabled={mailForm.processing}
                                    >
                                        {mailForm.processing ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <Save className="mr-2 h-4 w-4" />}
                                        Save Changes
                                    </Button>
                                </div>
                            </CardHeader>
                            <CardContent className="space-y-6">
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="space-y-2">
                                        <Label>From Name</Label>
                                        <Input
                                            value={mailForm.data.email_from_name}
                                            onChange={(e) => mailForm.setData("email_from_name", e.target.value)}
                                            placeholder="School Admin"
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label>From Address</Label>
                                        <Input
                                            type="email"
                                            value={mailForm.data.email_from_address}
                                            onChange={(e) => mailForm.setData("email_from_address", e.target.value)}
                                            placeholder="noreply@example.com"
                                        />
                                    </div>
                                </div>

                                <Separator />

                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="space-y-2">
                                        <Label>Mail Driver</Label>
                                        <Select value={mailForm.data.driver} onValueChange={(v) => mailForm.setData("driver", v)}>
                                            <SelectTrigger>
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="smtp">SMTP</SelectItem>
                                                <SelectItem value="mailgun">Mailgun</SelectItem>
                                                <SelectItem value="ses">Amazon SES</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div className="space-y-2">
                                        <Label>Encryption</Label>
                                        <Select value={mailForm.data.encryption || "tls"} onValueChange={(v) => mailForm.setData("encryption", v)}>
                                            <SelectTrigger>
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="tls">TLS</SelectItem>
                                                <SelectItem value="ssl">SSL</SelectItem>
                                                <SelectItem value="none">None</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div className="space-y-2">
                                        <Label>SMTP Host</Label>
                                        <Input
                                            value={mailForm.data.host}
                                            onChange={(e) => mailForm.setData("host", e.target.value)}
                                            placeholder="smtp.example.com"
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label>Port</Label>
                                        <Input
                                            type="number"
                                            value={mailForm.data.port}
                                            onChange={(e) => mailForm.setData("port", parseInt(e.target.value) || 587)}
                                            placeholder="587"
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label>Username</Label>
                                        <Input value={mailForm.data.username} onChange={(e) => mailForm.setData("username", e.target.value)} />
                                    </div>
                                    <div className="space-y-2">
                                        <Label>Password</Label>
                                        <Input
                                            type="password"
                                            value={mailForm.data.password}
                                            onChange={(e) => mailForm.setData("password", e.target.value)}
                                        />
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        {/* Test Email */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Send Test Email</CardTitle>
                                <CardDescription>Verify your mail configuration works correctly</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div className="flex gap-3">
                                    <Input
                                        type="email"
                                        placeholder="recipient@example.com"
                                        value={testEmail}
                                        onChange={(e) => setTestEmail(e.target.value)}
                                        className="max-w-sm"
                                    />
                                    <Button variant="secondary" onClick={handleTestEmail} disabled={sendingTest || !testEmail}>
                                        {sendingTest ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <Mail className="mr-2 h-4 w-4" />}
                                        Send Test
                                    </Button>
                                </div>
                                <p className="text-muted-foreground mt-2 text-xs">Save your settings first to test with the latest configuration.</p>
                            </CardContent>
                        </Card>
                    </TabsContent>

                    {/* Pulse Tab */}
                    <TabsContent value="pulse" className="space-y-6">
                        {loadingPulse && !pulseData && (
                            <div className="flex h-64 items-center justify-center">
                                <div className="flex flex-col items-center gap-4">
                                    <Loader2 className="text-primary h-8 w-8 animate-spin" />
                                    <p className="text-muted-foreground text-sm">Loading system metrics...</p>
                                </div>
                            </div>
                        )}

                        {pulseData && (
                            <>
                                {/* Servers Grid */}
                                <div className="grid gap-6 lg:grid-cols-2">
                                    {Object.entries(pulseData.servers.servers).map(([slug, server]) => {
                                        const cpuData = Object.entries(server.cpu)
                                            .filter((entry) => entry[1] !== null)
                                            .map(([time, val]) => ({ time, value: Number(val) }));

                                        return (
                                            <Card key={slug}>
                                                <CardHeader className="pb-2">
                                                    <div className="flex items-center justify-between">
                                                        <div className="flex items-center gap-2">
                                                            <Server className="text-muted-foreground h-4 w-4" />
                                                            <CardTitle className="text-base">{server.name}</CardTitle>
                                                        </div>
                                                        <Badge variant={server.cpu_current > 80 ? "destructive" : "secondary"}>
                                                            CPU {server.cpu_current}%
                                                        </Badge>
                                                    </div>
                                                </CardHeader>
                                                <CardContent className="space-y-4">
                                                    <div className="h-24">
                                                        <ResponsiveContainer width="100%" height="100%">
                                                            <AreaChart data={cpuData}>
                                                                <defs>
                                                                    <linearGradient id={`cpu-${slug}`} x1="0" y1="0" x2="0" y2="1">
                                                                        <stop offset="5%" stopColor="hsl(var(--primary))" stopOpacity={0.3} />
                                                                        <stop offset="95%" stopColor="hsl(var(--primary))" stopOpacity={0} />
                                                                    </linearGradient>
                                                                </defs>
                                                                <Area
                                                                    type="monotone"
                                                                    dataKey="value"
                                                                    stroke="hsl(var(--primary))"
                                                                    fillOpacity={1}
                                                                    fill={`url(#cpu-${slug})`}
                                                                    strokeWidth={2}
                                                                    isAnimationActive={false}
                                                                />
                                                            </AreaChart>
                                                        </ResponsiveContainer>
                                                    </div>

                                                    <div className="grid grid-cols-2 gap-4 text-center">
                                                        <div className="bg-muted/50 rounded-lg p-3">
                                                            <div className="text-2xl font-bold">{server.cpu_current}%</div>
                                                            <div className="text-muted-foreground text-xs">CPU</div>
                                                        </div>
                                                        <div className="bg-muted/50 rounded-lg p-3">
                                                            <div className="text-2xl font-bold">
                                                                {Math.round((server.memory_current / server.memory_total) * 100)}%
                                                            </div>
                                                            <div className="text-muted-foreground text-xs">Memory</div>
                                                        </div>
                                                    </div>

                                                    {/* Storage */}
                                                    <div className="space-y-2">
                                                        <div className="text-muted-foreground flex items-center gap-1 text-xs font-medium">
                                                            <HardDrive className="h-3 w-3" /> Storage
                                                        </div>
                                                        {server.storage.map((disk, i) => {
                                                            const percent = Math.round((disk.used / disk.total) * 100);
                                                            return (
                                                                <div key={i} className="space-y-1">
                                                                    <div className="flex justify-between text-xs">
                                                                        <span className="font-mono">{disk.directory}</span>
                                                                        <span
                                                                            className={cn(
                                                                                percent > 90
                                                                                    ? "text-destructive font-medium"
                                                                                    : "text-muted-foreground",
                                                                            )}
                                                                        >
                                                                            {percent}%
                                                                        </span>
                                                                    </div>
                                                                    <div className="bg-muted h-1.5 w-full overflow-hidden rounded-full">
                                                                        <div
                                                                            className={cn("h-full", percent > 90 ? "bg-destructive" : "bg-primary")}
                                                                            style={{ width: `${percent}%` }}
                                                                        />
                                                                    </div>
                                                                </div>
                                                            );
                                                        })}
                                                    </div>
                                                </CardContent>
                                            </Card>
                                        );
                                    })}
                                </div>

                                {/* Stats Grid */}
                                <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                                    {/* Cache */}
                                    <Card>
                                        <CardHeader className="pb-2">
                                            <CardTitle className="flex items-center gap-2 text-base">
                                                <DatabaseIcon className="h-4 w-4 text-purple-500" />
                                                Cache
                                            </CardTitle>
                                        </CardHeader>
                                        <CardContent>
                                            {pulseData.cache.allCacheInteractions ? (
                                                <div className="flex items-center justify-between">
                                                    <div className="h-32 w-32">
                                                        <ResponsiveContainer width="100%" height="100%">
                                                            <PieChart>
                                                                <Pie
                                                                    data={[
                                                                        { name: "Hits", value: Number(pulseData.cache.allCacheInteractions.hits) },
                                                                        {
                                                                            name: "Misses",
                                                                            value: Number(pulseData.cache.allCacheInteractions.misses),
                                                                        },
                                                                    ]}
                                                                    cx="50%"
                                                                    cy="50%"
                                                                    innerRadius={35}
                                                                    outerRadius={50}
                                                                    paddingAngle={2}
                                                                    dataKey="value"
                                                                >
                                                                    <Cell fill="#22c55e" />
                                                                    <Cell fill="#ef4444" />
                                                                </Pie>
                                                            </PieChart>
                                                        </ResponsiveContainer>
                                                    </div>
                                                    <div className="space-y-2 text-sm">
                                                        <div className="flex items-center gap-2">
                                                            <div className="h-2 w-2 rounded-full bg-green-500" />
                                                            <span className="text-muted-foreground">Hits:</span>
                                                            <span className="font-medium">
                                                                {Number(pulseData.cache.allCacheInteractions.hits).toLocaleString()}
                                                            </span>
                                                        </div>
                                                        <div className="flex items-center gap-2">
                                                            <div className="h-2 w-2 rounded-full bg-red-500" />
                                                            <span className="text-muted-foreground">Misses:</span>
                                                            <span className="font-medium">
                                                                {Number(pulseData.cache.allCacheInteractions.misses).toLocaleString()}
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>
                                            ) : (
                                                <p className="text-muted-foreground py-8 text-center text-sm">No cache data</p>
                                            )}
                                        </CardContent>
                                    </Card>

                                    {/* Queues */}
                                    <Card>
                                        <CardHeader className="pb-2">
                                            <CardTitle className="flex items-center gap-2 text-base">
                                                <List className="h-4 w-4 text-indigo-500" />
                                                Queues
                                            </CardTitle>
                                        </CardHeader>
                                        <CardContent>
                                            {pulseData.queues.queues.length === 0 ? (
                                                <p className="text-muted-foreground py-8 text-center text-sm">All queues empty</p>
                                            ) : (
                                                <div className="space-y-3">
                                                    {pulseData.queues.queues.slice(0, 4).map((q, i) => (
                                                        <div key={i} className="flex items-center justify-between text-sm">
                                                            <span className="font-mono">{q.queue}</span>
                                                            <div className="flex items-center gap-3">
                                                                <span className="text-muted-foreground">{q.size} pending</span>
                                                                {q.failed > 0 && (
                                                                    <Badge variant="destructive" className="text-xs">
                                                                        {q.failed} failed
                                                                    </Badge>
                                                                )}
                                                            </div>
                                                        </div>
                                                    ))}
                                                </div>
                                            )}
                                        </CardContent>
                                    </Card>

                                    {/* Exceptions */}
                                    <Card>
                                        <CardHeader className="pb-2">
                                            <CardTitle className="flex items-center gap-2 text-base">
                                                <Zap className="h-4 w-4 text-red-500" />
                                                Exceptions
                                            </CardTitle>
                                        </CardHeader>
                                        <CardContent>
                                            {pulseData.exceptions.exceptions.length === 0 ? (
                                                <p className="text-muted-foreground py-8 text-center text-sm">No recent exceptions</p>
                                            ) : (
                                                <div className="space-y-3">
                                                    {pulseData.exceptions.exceptions.slice(0, 3).map((ex, i) => (
                                                        <div key={i} className="space-y-1">
                                                            <div className="flex items-center justify-between">
                                                                <span className="text-destructive max-w-[180px] truncate font-mono text-xs">
                                                                    {ex.class.split("\\").pop()}
                                                                </span>
                                                                <Badge variant="secondary" className="text-xs">
                                                                    {ex.count}
                                                                </Badge>
                                                            </div>
                                                            <p className="text-muted-foreground truncate text-xs">{ex.message}</p>
                                                        </div>
                                                    ))}
                                                </div>
                                            )}
                                        </CardContent>
                                    </Card>
                                </div>

                                {/* Slow Requests & Users */}
                                <div className="grid gap-6 lg:grid-cols-2">
                                    <Card>
                                        <CardHeader className="pb-2">
                                            <CardTitle className="flex items-center gap-2 text-base">
                                                <AlertTriangle className="h-4 w-4 text-amber-500" />
                                                Slow Requests
                                            </CardTitle>
                                        </CardHeader>
                                        <CardContent>
                                            {pulseData.slow_requests.slowRequests.length === 0 ? (
                                                <p className="text-muted-foreground py-8 text-center text-sm">No slow requests</p>
                                            ) : (
                                                <div className="space-y-3">
                                                    {pulseData.slow_requests.slowRequests.slice(0, 5).map((req, i) => (
                                                        <div key={i} className="flex items-center justify-between text-sm">
                                                            <div className="flex items-center gap-2 overflow-hidden">
                                                                <Badge variant="outline" className="shrink-0 text-xs">
                                                                    {req.method}
                                                                </Badge>
                                                                <span className="truncate font-mono text-xs">{req.uri}</span>
                                                            </div>
                                                            <span className="shrink-0 font-mono text-amber-600">
                                                                {Number(req.slowest).toLocaleString()}ms
                                                            </span>
                                                        </div>
                                                    ))}
                                                </div>
                                            )}
                                        </CardContent>
                                    </Card>

                                    <Card>
                                        <CardHeader className="pb-2">
                                            <CardTitle className="flex items-center gap-2 text-base">
                                                <Activity className="h-4 w-4 text-blue-500" />
                                                Top Users
                                            </CardTitle>
                                        </CardHeader>
                                        <CardContent>
                                            {pulseData.usage.userRequestCounts.length === 0 ? (
                                                <p className="text-muted-foreground py-8 text-center text-sm">No user activity</p>
                                            ) : (
                                                <div className="space-y-3">
                                                    {pulseData.usage.userRequestCounts.slice(0, 5).map((usage, i) => (
                                                        <div key={i} className="flex items-center justify-between">
                                                            <div className="flex items-center gap-3">
                                                                <div className="bg-muted flex h-8 w-8 items-center justify-center overflow-hidden rounded-full text-xs font-medium">
                                                                    {usage.user.avatar ? (
                                                                        <img src={usage.user.avatar} alt="" className="h-full w-full object-cover" />
                                                                    ) : (
                                                                        usage.user.name.charAt(0)
                                                                    )}
                                                                </div>
                                                                <div className="text-sm">
                                                                    <p className="font-medium">{usage.user.name}</p>
                                                                    <p className="text-muted-foreground text-xs">{usage.user.email}</p>
                                                                </div>
                                                            </div>
                                                            <Badge variant="secondary">{usage.count}</Badge>
                                                        </div>
                                                    ))}
                                                </div>
                                            )}
                                        </CardContent>
                                    </Card>
                                </div>

                                {/* Slow Queries & Jobs */}
                                <div className="grid gap-6 lg:grid-cols-2">
                                    <Card>
                                        <CardHeader className="pb-2">
                                            <CardTitle className="flex items-center gap-2 text-base">
                                                <DatabaseIcon className="h-4 w-4 text-orange-500" />
                                                Slow Queries
                                            </CardTitle>
                                        </CardHeader>
                                        <CardContent>
                                            {pulseData.slow_queries.slowQueries.length === 0 ? (
                                                <p className="text-muted-foreground py-8 text-center text-sm">No slow queries</p>
                                            ) : (
                                                <div className="space-y-3">
                                                    {pulseData.slow_queries.slowQueries.slice(0, 3).map((query, i) => (
                                                        <div key={i} className="space-y-1">
                                                            <code className="bg-muted block truncate rounded p-2 text-xs">{query.sql}</code>
                                                            <div className="text-muted-foreground flex justify-between text-xs">
                                                                <span>{query.count}x</span>
                                                                <span className="font-medium text-orange-600">
                                                                    {Number(query.slowest).toLocaleString()}ms
                                                                </span>
                                                            </div>
                                                        </div>
                                                    ))}
                                                </div>
                                            )}
                                        </CardContent>
                                    </Card>

                                    <Card>
                                        <CardHeader className="pb-2">
                                            <CardTitle className="flex items-center gap-2 text-base">
                                                <Clock className="h-4 w-4 text-cyan-500" />
                                                Slow Jobs
                                            </CardTitle>
                                        </CardHeader>
                                        <CardContent>
                                            {pulseData.slow_jobs.slowJobs.length === 0 ? (
                                                <p className="text-muted-foreground py-8 text-center text-sm">No slow jobs</p>
                                            ) : (
                                                <div className="space-y-3">
                                                    {pulseData.slow_jobs.slowJobs.slice(0, 5).map((job, i) => (
                                                        <div key={i} className="flex items-center justify-between text-sm">
                                                            <span className="max-w-[200px] truncate font-mono text-xs">{job.job}</span>
                                                            <span className="font-mono text-cyan-600">{Number(job.slowest).toLocaleString()}ms</span>
                                                        </div>
                                                    ))}
                                                </div>
                                            )}
                                        </CardContent>
                                    </Card>
                                </div>
                            </>
                        )}
                    </TabsContent>
                </Tabs>
            </div>
        </AdminLayout>
    );
}
