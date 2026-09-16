import {
    fetchAiModels,
    testAiConnection,
    updateAi,
} from "@/actions/App/Http/Controllers/AdministratorSystemManagementController";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Switch } from "@/components/ui/switch";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Textarea } from "@/components/ui/textarea";
import { useForm } from "@inertiajs/react";
import axios from "axios";
import {
    AlertCircle,
    AlertTriangle,
    Bot,
    CheckCircle2,
    Cpu,
    Eye,
    EyeOff,
    Globe,
    KeyRound,
    Layers,
    Loader2,
    Plus,
    RefreshCw,
    Save,
    Server,
    ShieldCheck,
    Sparkles,
    Trash2,
    Zap,
} from "lucide-react";
import * as React from "react";
import { toast } from "sonner";

import { submitSystemForm } from "./form-submit";
import SystemManagementLayout from "./layout";
import type {
    AiConfigPayload,
    AiDiscoveredModel,
    AiProviderAdminConfig,
    AiProviderKey,
    CustomAiProviderAdminConfig,
    SystemManagementPageProps,
} from "./types";

interface CustomAiProviderFormData {
    key: string;
    label: string;
    enabled: boolean;
    api_key: string;
    base_url: string;
    headers: Record<string, string>;
    headers_text: string;
    default_chat_model: string;
    default_fast_model: string;
    default_embeddings_model: string;
    custom_models: string[];
}

interface AiFormPayload {
    enabled: boolean;
    primary_provider: string;
    fallback_provider: string;
    failover_enabled: boolean;
    request_timeout_seconds: number;
    providers: Record<
        AiProviderKey,
        {
            enabled: boolean;
            api_key: string;
            base_url: string;
            default_chat_model: string;
            default_fast_model: string;
            default_embeddings_model: string;
            custom_models: string[];
        }
    >;
    custom_providers: Record<string, CustomAiProviderFormData>;
}

const PROVIDER_METAS: Record<
    AiProviderKey,
    {
        name: string;
        badge: string;
        description: string;
        defaultBaseUrl: string;
        recommendedFor: string;
    }
> = {
    anthropic: {
        name: "Anthropic (Claude)",
        badge: "Recommended",
        description: "Leading model for reasoning, long context, and complex tool-use workflows.",
        defaultBaseUrl: "https://api.anthropic.com/v1",
        recommendedFor: "Agent orchestration & complex instruction following",
    },
    openai: {
        name: "OpenAI",
        badge: "General Purpose",
        description: "Standard model suite including GPT-4o, embeddings, audio, and vision.",
        defaultBaseUrl: "https://api.openai.com/v1",
        recommendedFor: "Embeddings, RAG, and multimodal extraction",
    },
    gemini: {
        name: "Google Gemini",
        badge: "Multimodal",
        description: "Google's 2.0/2.5 Flash & Pro series with native multimodal document processing.",
        defaultBaseUrl: "https://generativelanguage.googleapis.com/v1beta",
        recommendedFor: "Student TOR documents & image inspection",
    },
    groq: {
        name: "Groq",
        badge: "Ultra Fast",
        description: "Low-latency LPU hardware running open weights (Llama 3.3, Whisper).",
        defaultBaseUrl: "https://api.groq.com/openai/v1",
        recommendedFor: "High-speed interactive student assistance",
    },
    deepseek: {
        name: "DeepSeek",
        badge: "Reasoning",
        description: "Cost-efficient reasoning models for complex scheduling analysis.",
        defaultBaseUrl: "https://api.deepseek.com",
        recommendedFor: "Curriculum logic and policy simulations",
    },
    mistral: {
        name: "Mistral AI",
        badge: "Open Weights",
        description: "Enterprise European models including Mistral Large and Codestral.",
        defaultBaseUrl: "https://api.mistral.ai/v1",
        recommendedFor: "Instruction following and code assistance",
    },
    openrouter: {
        name: "OpenRouter",
        badge: "Aggregator",
        description: "Unified gateway routing across hundreds of open and proprietary models.",
        defaultBaseUrl: "https://openrouter.ai/api/v1",
        recommendedFor: "Provider redundancy & niche open-source weights",
    },
    ollama: {
        name: "Ollama (Self-Hosted)",
        badge: "Air-Gapped / Privacy",
        description: "Local GPU inference engine for 100% on-premise confidential data.",
        defaultBaseUrl: "http://localhost:11434",
        recommendedFor: "Offline student records & FERPA compliance",
    },
    "openai-compatible": {
        name: "OpenAI-Compatible (Custom)",
        badge: "Standard Custom",
        description: "Connect standard OpenAI-compatible proxies, vLLM, or LM Studio endpoints.",
        defaultBaseUrl: "",
        recommendedFor: "Campus GPU server or custom proxy",
    },
};

export default function SystemManagementAiPage({
    user,
    access,
    ai_config,
}: SystemManagementPageProps) {
    const canUpdate = access.sections.ai?.can_update ?? false;

    // Initialize built-in providers
    const initialProviders = React.useMemo(() => {
        const result = {} as AiFormPayload["providers"];
        const keys = Object.keys(PROVIDER_METAS) as AiProviderKey[];

        for (const key of keys) {
            const raw = ai_config?.providers?.[key];
            result[key] = {
                enabled: raw?.enabled ?? false,
                api_key: "",
                base_url: raw?.base_url ?? PROVIDER_METAS[key].defaultBaseUrl,
                default_chat_model: raw?.default_chat_model ?? "",
                default_fast_model: raw?.default_fast_model ?? "",
                default_embeddings_model: raw?.default_embeddings_model ?? "",
                custom_models: Array.isArray(raw?.custom_models) ? raw.custom_models : [],
            };
        }

        return result;
    }, [ai_config]);

    // Initialize custom OpenAI-compatible providers
    const initialCustomProviders = React.useMemo(() => {
        const result: Record<string, CustomAiProviderFormData> = {};
        const rawCustom = ai_config?.custom_providers ?? {};

        for (const [key, val] of Object.entries(rawCustom)) {
            result[key] = {
                key: val.key,
                label: val.label || key,
                enabled: val.enabled ?? true,
                api_key: "",
                base_url: val.base_url || "",
                headers: val.headers || {},
                headers_text: val.headers ? JSON.stringify(val.headers, null, 2) : "",
                default_chat_model: val.default_chat_model || "",
                default_fast_model: val.default_fast_model || "",
                default_embeddings_model: val.default_embeddings_model || "",
                custom_models: Array.isArray(val.custom_models) ? val.custom_models : [],
            };
        }

        return result;
    }, [ai_config]);

    const form = useForm<AiFormPayload>({
        enabled: ai_config?.enabled ?? true,
        primary_provider: ai_config?.primary_provider ?? "anthropic",
        fallback_provider: ai_config?.fallback_provider ?? "openai",
        failover_enabled: ai_config?.failover_enabled ?? true,
        request_timeout_seconds: ai_config?.request_timeout_seconds ?? 60,
        providers: initialProviders,
        custom_providers: initialCustomProviders,
    });

    const [activeTab, setActiveTab] = React.useState<string>("anthropic");
    const [showKey, setShowKey] = React.useState<Record<string, boolean>>({});
    const [newCustomModel, setNewCustomModel] = React.useState("");

    // Modal state for adding a custom OpenAI-compatible provider
    const [addProviderOpen, setAddProviderOpen] = React.useState(false);
    const [newProviderName, setNewProviderName] = React.useState("");
    const [newProviderKey, setNewProviderKey] = React.useState("");
    const [newProviderUrl, setNewProviderUrl] = React.useState("");
    const [newProviderKeySecret, setNewProviderKeySecret] = React.useState("");
    const [newProviderChatModel, setNewProviderChatModel] = React.useState("");

    // Live fetching & testing states
    const [fetchingModels, setFetchingModels] = React.useState(false);
    const [fetchMessage, setFetchMessage] = React.useState<{ text: string; error: boolean } | null>(null);
    const [testingConnection, setTestingConnection] = React.useState(false);
    const [connectionStatus, setConnectionStatus] = React.useState<{ text: string; latency?: number; success: boolean } | null>(null);

    // Discovered models cache maintained in state
    const [discoveredModels, setDiscoveredModels] = React.useState<Record<string, AiDiscoveredModel[]>>(() => {
        const initial: Record<string, AiDiscoveredModel[]> = {};
        const keys = Object.keys(PROVIDER_METAS) as AiProviderKey[];
        for (const key of keys) {
            initial[key] = ai_config?.providers?.[key]?.discovered_models ?? [];
        }
        for (const [key, val] of Object.entries(ai_config?.custom_providers ?? {})) {
            initial[key] = val.discovered_models ?? [];
        }
        return initial;
    });

    const isCustomProvider = Boolean(form.data.custom_providers[activeTab]);

    const activeBuiltInConfig = ai_config?.providers?.[activeTab as AiProviderKey];
    const activeCustomConfig = ai_config?.custom_providers?.[activeTab];

    const activeBuiltInForm = form.data.providers[activeTab as AiProviderKey];
    const activeCustomForm = form.data.custom_providers[activeTab];

    const toggleShowKey = (provider: string) => {
        setShowKey((prev) => ({ ...prev, [provider]: !prev[provider] }));
    };

    const updateActiveField = (field: string, value: any) => {
        if (isCustomProvider) {
            form.setData("custom_providers", {
                ...form.data.custom_providers,
                [activeTab]: {
                    ...form.data.custom_providers[activeTab],
                    [field]: value,
                },
            });
        } else {
            form.setData("providers", {
                ...form.data.providers,
                [activeTab as AiProviderKey]: {
                    ...form.data.providers[activeTab as AiProviderKey],
                    [field]: value,
                },
            });
        }
    };

    const addCustomModel = () => {
        const trimmed = newCustomModel.trim();
        if (!trimmed) return;

        const currentCustom = isCustomProvider
            ? activeCustomForm.custom_models || []
            : activeBuiltInForm.custom_models || [];

        if (!currentCustom.includes(trimmed)) {
            updateActiveField("custom_models", [...currentCustom, trimmed]);
            toast.success(`Custom model "${trimmed}" added.`);
        }
        setNewCustomModel("");
    };

    const removeCustomModel = (modelToRemove: string) => {
        const currentCustom = isCustomProvider
            ? activeCustomForm.custom_models || []
            : activeBuiltInForm.custom_models || [];

        updateActiveField(
            "custom_models",
            currentCustom.filter((m) => m !== modelToRemove)
        );
    };

    const handleCreateCustomProvider = () => {
        const name = newProviderName.trim();
        const key = (newProviderKey.trim() || name.toLowerCase().replace(/[^a-z0-9_-]/g, "-")).trim();
        const url = newProviderUrl.trim();

        if (!name || !key || !url) {
            toast.error("Please fill in Name, Key, and Base URL.");
            return;
        }

        if (form.data.providers[key as AiProviderKey] || form.data.custom_providers[key]) {
            toast.error(`A provider with key "${key}" already exists.`);
            return;
        }

        const newCustomEntry: CustomAiProviderFormData = {
            key,
            label: name,
            enabled: true,
            api_key: newProviderKeySecret.trim(),
            base_url: url,
            headers: {},
            headers_text: "",
            default_chat_model: newProviderChatModel.trim(),
            default_fast_model: "",
            default_embeddings_model: "",
            custom_models: [],
        };

        form.setData("custom_providers", {
            ...form.data.custom_providers,
            [key]: newCustomEntry,
        });

        setActiveTab(key);
        setAddProviderOpen(false);
        setNewProviderName("");
        setNewProviderKey("");
        setNewProviderUrl("");
        setNewProviderKeySecret("");
        setNewProviderChatModel("");

        toast.success(`Custom OpenAI-compatible provider "${name}" added!`);
    };

    const handleDeleteCustomProvider = (keyToDelete: string) => {
        const updated = { ...form.data.custom_providers };
        delete updated[keyToDelete];

        form.setData("custom_providers", updated);

        if (form.data.primary_provider === keyToDelete) {
            form.setData("primary_provider", "anthropic");
        }
        if (form.data.fallback_provider === keyToDelete) {
            form.setData("fallback_provider", "openai");
        }

        setActiveTab("anthropic");
        toast.info(`Custom provider "${keyToDelete}" removed.`);
    };

    const parseHeaders = (text: string): Record<string, string> => {
        if (!text.trim()) return {};
        try {
            const parsed = JSON.parse(text);
            return typeof parsed === "object" && parsed !== null ? parsed : {};
        } catch {
            return {};
        }
    };

    const handleFetchModels = async () => {
        setFetchingModels(true);
        setFetchMessage(null);

        try {
            const apiKey = isCustomProvider ? activeCustomForm.api_key : activeBuiltInForm.api_key;
            const baseUrl = isCustomProvider ? activeCustomForm.base_url : activeBuiltInForm.base_url;
            const headers = isCustomProvider ? parseHeaders(activeCustomForm.headers_text) : {};

            const payload: { provider: string; api_key?: string; base_url?: string; headers?: Record<string, string> } = {
                provider: activeTab,
            };

            if (apiKey) payload.api_key = apiKey;
            if (baseUrl) payload.base_url = baseUrl;
            if (Object.keys(headers).length > 0) payload.headers = headers;

            const response = await axios.post(fetchAiModels.url(), payload);

            if (response.data.success) {
                const models: AiDiscoveredModel[] = response.data.models;
                setDiscoveredModels((prev) => ({
                    ...prev,
                    [activeTab]: models,
                }));
                setFetchMessage({
                    text: `Fetched ${models.length} models successfully.`,
                    error: false,
                });
                toast.success(`Discovered ${models.length} live models.`);
            }
        } catch (error: any) {
            const errorMsg =
                error.response?.data?.message ||
                error.message ||
                "Failed to fetch models from endpoint. You can enter model names manually below.";
            setFetchMessage({ text: errorMsg, error: true });
            toast.error("Could not fetch models automatically. Manual input is available.");
        } finally {
            setFetchingModels(false);
        }
    };

    const handleTestConnection = async () => {
        setTestingConnection(true);
        setConnectionStatus(null);

        try {
            const apiKey = isCustomProvider ? activeCustomForm.api_key : activeBuiltInForm.api_key;
            const baseUrl = isCustomProvider ? activeCustomForm.base_url : activeBuiltInForm.base_url;
            const headers = isCustomProvider ? parseHeaders(activeCustomForm.headers_text) : {};

            const payload: { provider: string; api_key?: string; base_url?: string; headers?: Record<string, string> } = {
                provider: activeTab,
            };

            if (apiKey) payload.api_key = apiKey;
            if (baseUrl) payload.base_url = baseUrl;
            if (Object.keys(headers).length > 0) payload.headers = headers;

            const response = await axios.post(testAiConnection.url(), payload);

            if (response.data.success) {
                setConnectionStatus({
                    text: response.data.message,
                    latency: response.data.latency_ms,
                    success: true,
                });
                toast.success(`Connected! (${response.data.latency_ms}ms)`);
            }
        } catch (error: any) {
            const errorMsg =
                error.response?.data?.message ||
                error.message ||
                "Connection failed. Check your API key or endpoint URL.";
            setConnectionStatus({
                text: errorMsg,
                success: false,
            });
            toast.error(errorMsg);
        } finally {
            setTestingConnection(false);
        }
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        // Prepare clean custom_providers array with parsed headers
        const processedCustomProviders: Record<string, any> = {};
        for (const [k, v] of Object.entries(form.data.custom_providers)) {
            processedCustomProviders[k] = {
                ...v,
                headers: parseHeaders(v.headers_text),
            };
        }

        form.transform((data) => ({
            ...data,
            custom_providers: processedCustomProviders,
        }));

        submitSystemForm({
            form,
            routeName: "administrators.system-management.ai.update",
            successMessage: "AI provider configuration saved and injected into runtime.",
            errorMessage: "Failed to save AI configuration. Please check the fields and try again.",
        });
    };

    // Current active provider metadata & form values
    const activeLabel = isCustomProvider
        ? activeCustomForm.label
        : PROVIDER_METAS[activeTab as AiProviderKey]?.name || activeTab;

    const activeDescription = isCustomProvider
        ? "Custom OpenAI-compatible inference server or proxy."
        : PROVIDER_METAS[activeTab as AiProviderKey]?.description || "";

    const activeBadge = isCustomProvider
        ? "Custom OpenAI-Compatible"
        : PROVIDER_METAS[activeTab as AiProviderKey]?.badge || "Standard";

    const activeConfigured = isCustomProvider
        ? activeCustomConfig?.configured || Boolean(activeCustomForm.api_key)
        : activeBuiltInConfig?.configured || false;

    const activeMaskedKey = isCustomProvider
        ? activeCustomConfig?.api_key_masked || ""
        : activeBuiltInConfig?.api_key_masked || "";

    const activeEnabled = isCustomProvider
        ? activeCustomForm.enabled
        : activeBuiltInForm?.enabled ?? false;

    const activeApiKey = isCustomProvider ? activeCustomForm.api_key : activeBuiltInForm?.api_key ?? "";
    const activeBaseUrl = isCustomProvider ? activeCustomForm.base_url : activeBuiltInForm?.base_url ?? "";
    const activeChatModel = isCustomProvider ? activeCustomForm.default_chat_model : activeBuiltInForm?.default_chat_model ?? "";
    const activeFastModel = isCustomProvider ? activeCustomForm.default_fast_model : activeBuiltInForm?.default_fast_model ?? "";
    const activeEmbeddingsModel = isCustomProvider ? activeCustomForm.default_embeddings_model : activeBuiltInForm?.default_embeddings_model ?? "";
    const activeCustomModels = isCustomProvider ? activeCustomForm.custom_models : activeBuiltInForm?.custom_models ?? [];

    // All available selectable options for primary and fallback providers
    const allProviderChoices = React.useMemo(() => {
        const list: { key: string; label: string; isCustom?: boolean }[] = [];

        (Object.keys(PROVIDER_METAS) as AiProviderKey[]).forEach((k) => {
            list.push({ key: k, label: PROVIDER_METAS[k].name, isCustom: false });
        });

        Object.keys(form.data.custom_providers).forEach((ck) => {
            list.push({
                key: ck,
                label: `${form.data.custom_providers[ck].label} (Custom)`,
                isCustom: true,
            });
        });

        return list;
    }, [form.data.custom_providers]);

    // Aggregate selectable models for datalist
    const availableModels = React.useMemo(() => {
        const discovered = discoveredModels[activeTab] || [];
        const custom = activeCustomModels || [];

        const map = new Map<string, string>();
        discovered.forEach((m) => map.set(m.id, m.name || m.id));
        custom.forEach((id) => {
            if (!map.has(id)) map.set(id, `${id} (Custom)`);
        });

        if (activeChatModel && !map.has(activeChatModel)) map.set(activeChatModel, activeChatModel);
        if (activeFastModel && !map.has(activeFastModel)) map.set(activeFastModel, activeFastModel);
        if (activeEmbeddingsModel && !map.has(activeEmbeddingsModel)) map.set(activeEmbeddingsModel, activeEmbeddingsModel);

        return Array.from(map.entries()).map(([id, label]) => ({ id, label }));
    }, [discoveredModels, activeTab, activeCustomModels, activeChatModel, activeFastModel, activeEmbeddingsModel]);

    return (
        <SystemManagementLayout
            user={user}
            access={access}
            activeSection="ai"
            heading="AI Providers & Multi-Model Engine"
            description="Manage provider credentials, live model synchronization from /models endpoints, custom OpenAI-compatible APIs, and failover."
        >
            <form onSubmit={handleSubmit} className="space-y-6">
                {/* Global AI Policy & Failover Card */}
                <Card className="border-indigo-500/20 bg-gradient-to-br from-indigo-500/5 via-background to-background">
                    <CardHeader className="pb-4">
                        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                            <div>
                                <CardTitle className="flex items-center gap-2 text-lg font-semibold tracking-tight">
                                    <Sparkles className="size-5 text-indigo-500" />
                                    Global Orchestration & Failover
                                </CardTitle>
                                <CardDescription>
                                    Define primary provider preference (including custom OpenAI-compatible APIs), automatic error failover, and timeout.
                                </CardDescription>
                            </div>
                            <div className="flex items-center gap-3">
                                <Label htmlFor="ai-master-switch" className="text-sm font-medium">
                                    AI Engine Active
                                </Label>
                                <Switch
                                    id="ai-master-switch"
                                    checked={form.data.enabled}
                                    onCheckedChange={(checked) => form.setData("enabled", checked)}
                                    disabled={!canUpdate}
                                />
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent className="grid grid-cols-1 md:grid-cols-4 gap-4 pt-0">
                        <div className="space-y-1.5">
                            <Label htmlFor="primary_provider" className="text-xs font-medium">
                                Primary AI Provider
                            </Label>
                            <Select
                                value={form.data.primary_provider}
                                onValueChange={(val: string) => form.setData("primary_provider", val)}
                                disabled={!canUpdate || !form.data.enabled}
                            >
                                <SelectTrigger id="primary_provider">
                                    <SelectValue placeholder="Select primary" />
                                </SelectTrigger>
                                <SelectContent>
                                    {allProviderChoices.map((p) => (
                                        <SelectItem key={p.key} value={p.key}>
                                            {p.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="space-y-1.5">
                            <Label htmlFor="fallback_provider" className="text-xs font-medium">
                                Fallback Provider (Failover)
                            </Label>
                            <Select
                                value={form.data.fallback_provider || "none"}
                                onValueChange={(val) => form.setData("fallback_provider", val === "none" ? "" : val)}
                                disabled={!canUpdate || !form.data.enabled}
                            >
                                <SelectTrigger id="fallback_provider">
                                    <SelectValue placeholder="None (Fail immediate)" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="none">None (Fail immediate)</SelectItem>
                                    {allProviderChoices
                                        .filter((p) => p.key !== form.data.primary_provider)
                                        .map((p) => (
                                            <SelectItem key={p.key} value={p.key}>
                                                {p.label}
                                            </SelectItem>
                                        ))}
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="space-y-1.5">
                            <Label htmlFor="request_timeout_seconds" className="text-xs font-medium">
                                Request Timeout (Seconds)
                            </Label>
                            <Input
                                id="request_timeout_seconds"
                                type="number"
                                min={5}
                                max={300}
                                value={form.data.request_timeout_seconds}
                                onChange={(e) => form.setData("request_timeout_seconds", parseInt(e.target.value, 10) || 60)}
                                disabled={!canUpdate || !form.data.enabled}
                            />
                        </div>

                        <div className="flex flex-col justify-end">
                            <div className="flex items-center justify-between rounded-lg border p-2.5 bg-muted/30">
                                <div className="space-y-0.5">
                                    <Label className="text-xs font-medium">Auto-Failover</Label>
                                    <p className="text-[11px] text-muted-foreground">Switch on rate limit / outage</p>
                                </div>
                                <Switch
                                    checked={form.data.failover_enabled}
                                    onCheckedChange={(checked) => form.setData("failover_enabled", checked)}
                                    disabled={!canUpdate || !form.data.enabled || !form.data.fallback_provider}
                                />
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Provider Configuration Card with Tabs */}
                <Card>
                    <CardHeader className="pb-3 border-b">
                        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                            <div>
                                <CardTitle className="text-base font-semibold">Configured AI Providers & Custom APIs</CardTitle>
                                <CardDescription className="text-xs">
                                    Manage API credentials, configure OpenAI-compatible inference servers, test connectivity, and fetch live models.
                                </CardDescription>
                            </div>
                            <div className="flex items-center gap-2">
                                {/* Dialog to Add a Custom OpenAI-Compatible Provider */}
                                <Dialog open={addProviderOpen} onOpenChange={setAddProviderOpen}>
                                    <DialogTrigger asChild>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            className="text-xs h-8 gap-1.5 border-dashed"
                                            disabled={!canUpdate}
                                        >
                                            <Plus className="size-3.5 text-primary" />
                                            Add Custom Provider
                                        </Button>
                                    </DialogTrigger>
                                    <DialogContent className="sm:max-w-md">
                                        <DialogHeader>
                                            <DialogTitle className="text-base font-semibold flex items-center gap-2">
                                                <Server className="size-4 text-primary" />
                                                Add OpenAI-Compatible Provider
                                            </DialogTitle>
                                            <DialogDescription className="text-xs">
                                                Connect any custom inference endpoint (vLLM, LM Studio, Ollama, Together AI, Fireworks, etc.) using the OpenAI protocol.
                                            </DialogDescription>
                                        </DialogHeader>

                                        <div className="space-y-3 py-2">
                                            <div className="space-y-1">
                                                <Label htmlFor="custom_name" className="text-xs">
                                                    Provider Display Name *
                                                </Label>
                                                <Input
                                                    id="custom_name"
                                                    placeholder="e.g. Campus GPU Cluster (vLLM)"
                                                    value={newProviderName}
                                                    onChange={(e) => {
                                                        setNewProviderName(e.target.value);
                                                        if (!newProviderKey) {
                                                            setNewProviderKey(
                                                                e.target.value.toLowerCase().replace(/[^a-z0-9_-]/g, "-")
                                                            );
                                                        }
                                                    }}
                                                    className="text-xs"
                                                />
                                            </div>

                                            <div className="space-y-1">
                                                <Label htmlFor="custom_key" className="text-xs">
                                                    Provider Identifier / Slug *
                                                </Label>
                                                <Input
                                                    id="custom_key"
                                                    placeholder="e.g. vllm-cluster"
                                                    value={newProviderKey}
                                                    onChange={(e) => setNewProviderKey(e.target.value)}
                                                    className="font-mono text-xs"
                                                />
                                            </div>

                                            <div className="space-y-1">
                                                <Label htmlFor="custom_url" className="text-xs">
                                                    Base URL Endpoint *
                                                </Label>
                                                <Input
                                                    id="custom_url"
                                                    placeholder="http://192.168.1.100:8000/v1 or https://api.together.xyz/v1"
                                                    value={newProviderUrl}
                                                    onChange={(e) => setNewProviderUrl(e.target.value)}
                                                    className="font-mono text-xs"
                                                />
                                            </div>

                                            <div className="space-y-1">
                                                <Label htmlFor="custom_secret" className="text-xs">
                                                    API Key (Optional for local servers)
                                                </Label>
                                                <Input
                                                    id="custom_secret"
                                                    type="password"
                                                    placeholder="sk-..."
                                                    value={newProviderKeySecret}
                                                    onChange={(e) => setNewProviderKeySecret(e.target.value)}
                                                    className="font-mono text-xs"
                                                />
                                            </div>

                                            <div className="space-y-1">
                                                <Label htmlFor="custom_model" className="text-xs">
                                                    Default Chat Model (Optional)
                                                </Label>
                                                <Input
                                                    id="custom_model"
                                                    placeholder="e.g. mistralai/Mistral-7B-Instruct-v0.3"
                                                    value={newProviderChatModel}
                                                    onChange={(e) => setNewProviderChatModel(e.target.value)}
                                                    className="font-mono text-xs"
                                                />
                                            </div>
                                        </div>

                                        <DialogFooter className="gap-2 sm:gap-0">
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                onClick={() => setAddProviderOpen(false)}
                                                className="text-xs h-8"
                                            >
                                                Cancel
                                            </Button>
                                            <Button
                                                type="button"
                                                size="sm"
                                                onClick={handleCreateCustomProvider}
                                                className="text-xs h-8"
                                            >
                                                Register Provider
                                            </Button>
                                        </DialogFooter>
                                    </DialogContent>
                                </Dialog>

                                <Badge variant={activeEnabled ? "default" : "secondary"}>
                                    {activeEnabled ? "Active" : "Disabled"}
                                </Badge>
                                {activeConfigured && (
                                    <Badge variant="outline" className="text-emerald-600 dark:text-emerald-400 border-emerald-500/30">
                                        <CheckCircle2 className="size-3 mr-1" />
                                        Configured
                                    </Badge>
                                )}
                            </div>
                        </div>

                        {/* Provider Horizontal Tabs */}
                        <div className="pt-2 overflow-x-auto">
                            <Tabs
                                value={activeTab}
                                onValueChange={(val) => {
                                    setActiveTab(val);
                                    setFetchMessage(null);
                                    setConnectionStatus(null);
                                }}
                            >
                                <TabsList className="bg-muted/60 p-1 flex-wrap h-auto gap-1">
                                    {(Object.keys(PROVIDER_METAS) as AiProviderKey[]).map((pKey) => {
                                        const isConfigured = ai_config?.providers?.[pKey]?.configured;
                                        const isEnabled = form.data.providers[pKey]?.enabled;
                                        return (
                                            <TabsTrigger key={pKey} value={pKey} className="text-xs gap-1.5 px-3 h-7">
                                                {PROVIDER_METAS[pKey].name}
                                                {isEnabled && <span className="size-1.5 rounded-full bg-emerald-500" />}
                                            </TabsTrigger>
                                        );
                                    })}

                                    {/* Custom Provider Tabs */}
                                    {Object.keys(form.data.custom_providers).map((cKey) => {
                                        const custom = form.data.custom_providers[cKey];
                                        return (
                                            <TabsTrigger
                                                key={cKey}
                                                value={cKey}
                                                className="text-xs gap-1.5 px-3 h-7 border border-dashed border-primary/40 bg-primary/5 text-primary"
                                            >
                                                <Server className="size-3" />
                                                {custom.label}
                                                {custom.enabled && <span className="size-1.5 rounded-full bg-emerald-500" />}
                                            </TabsTrigger>
                                        );
                                    })}
                                </TabsList>
                            </Tabs>
                        </div>
                    </CardHeader>

                    <CardContent className="p-6 space-y-6">
                        {/* Provider Header Banner */}
                        <div className="flex flex-col sm:flex-row sm:items-center justify-between p-4 rounded-xl border bg-muted/20 gap-3">
                            <div>
                                <div className="flex items-center gap-2">
                                    <h3 className="font-semibold text-base flex items-center gap-2">
                                        {isCustomProvider && <Server className="size-4 text-primary" />}
                                        {activeLabel}
                                    </h3>
                                    <Badge variant="outline" className="text-xs">
                                        {activeBadge}
                                    </Badge>
                                    {isCustomProvider && (
                                        <Badge variant="secondary" className="text-[10px] font-mono">
                                            key: {activeTab}
                                        </Badge>
                                    )}
                                </div>
                                <p className="text-xs text-muted-foreground mt-0.5">{activeDescription}</p>
                            </div>

                            <div className="flex items-center gap-4 shrink-0">
                                <div className="flex items-center gap-2">
                                    <Label htmlFor={`enable-${activeTab}`} className="text-xs font-medium cursor-pointer">
                                        Enable Provider
                                    </Label>
                                    <Switch
                                        id={`enable-${activeTab}`}
                                        checked={activeEnabled}
                                        onCheckedChange={(checked) => updateActiveField("enabled", checked)}
                                        disabled={!canUpdate}
                                    />
                                </div>

                                {isCustomProvider && (
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => handleDeleteCustomProvider(activeTab)}
                                        disabled={!canUpdate}
                                        className="text-xs text-destructive hover:bg-destructive/10 h-8 gap-1.5"
                                        title="Delete custom provider"
                                    >
                                        <Trash2 className="size-3.5" />
                                        Remove
                                    </Button>
                                )}
                            </div>
                        </div>

                        {/* Credentials & Endpoint Inputs */}
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            {/* API Key */}
                            <div className="space-y-2">
                                <div className="flex items-center justify-between">
                                    <Label htmlFor="provider_api_key" className="text-xs font-medium flex items-center gap-1.5">
                                        <KeyRound className="size-3.5 text-muted-foreground" />
                                        API Key
                                    </Label>
                                    {activeMaskedKey && (
                                        <span className="text-[11px] text-muted-foreground font-mono">
                                            Current: {activeMaskedKey}
                                        </span>
                                    )}
                                </div>
                                <div className="relative">
                                    <Input
                                        id="provider_api_key"
                                        type={showKey[activeTab] ? "text" : "password"}
                                        placeholder={
                                            activeConfigured
                                                ? "Leave blank to keep stored API key"
                                                : "Enter provider API key (sk-...)"
                                        }
                                        value={activeApiKey}
                                        onChange={(e) => updateActiveField("api_key", e.target.value)}
                                        disabled={!canUpdate}
                                        className="pr-10 font-mono text-xs"
                                    />
                                    <button
                                        type="button"
                                        onClick={() => toggleShowKey(activeTab)}
                                        className="absolute right-2.5 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                                    >
                                        {showKey[activeTab] ? <EyeOff className="size-4" /> : <Eye className="size-4" />}
                                    </button>
                                </div>
                                <p className="text-[11px] text-muted-foreground">
                                    Encrypted with AES-256 before database persistence. Never returned to the client in cleartext.
                                </p>
                            </div>

                            {/* Base URL */}
                            <div className="space-y-2">
                                <Label htmlFor="provider_base_url" className="text-xs font-medium flex items-center gap-1.5">
                                    <Globe className="size-3.5 text-muted-foreground" />
                                    Base Endpoint URL
                                </Label>
                                <Input
                                    id="provider_base_url"
                                    type="text"
                                    placeholder={isCustomProvider ? "http://localhost:8000/v1" : (PROVIDER_METAS[activeTab as AiProviderKey]?.defaultBaseUrl || "https://...")}
                                    value={activeBaseUrl}
                                    onChange={(e) => updateActiveField("base_url", e.target.value)}
                                    disabled={!canUpdate}
                                    className="font-mono text-xs"
                                />
                                <p className="text-[11px] text-muted-foreground">
                                    Base endpoint URL hosting the `/models` and `/chat/completions` routes.
                                </p>
                            </div>
                        </div>

                        {/* Custom Headers Configuration for OpenAI-Compatible providers */}
                        {isCustomProvider && (
                            <div className="space-y-2 p-3 rounded-lg border bg-muted/10">
                                <Label htmlFor="custom_headers" className="text-xs font-medium flex items-center gap-1.5">
                                    <Server className="size-3.5 text-primary" />
                                    Custom HTTP Headers (JSON format)
                                </Label>
                                <Textarea
                                    id="custom_headers"
                                    rows={2}
                                    placeholder='{ "X-Tenant-ID": "school-campus-1", "Authorization": "Bearer custom-token" }'
                                    value={activeCustomForm.headers_text}
                                    onChange={(e) => updateActiveField("headers_text", e.target.value)}
                                    disabled={!canUpdate}
                                    className="font-mono text-xs"
                                />
                                <p className="text-[11px] text-muted-foreground">
                                    Optional HTTP headers sent with every request to this OpenAI-compatible server.
                                </p>
                            </div>
                        )}

                        {/* Test Connection & Live /models Discovery Bar */}
                        <div className="flex flex-wrap items-center justify-between gap-3 p-3 rounded-lg border bg-background/50">
                            <div className="flex items-center gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={handleTestConnection}
                                    disabled={testingConnection || !canUpdate}
                                    className="text-xs h-8 gap-1.5"
                                >
                                    {testingConnection ? <Loader2 className="size-3.5 animate-spin" /> : <Zap className="size-3.5 text-amber-500" />}
                                    Test Connectivity
                                </Button>

                                <Button
                                    type="button"
                                    variant="secondary"
                                    size="sm"
                                    onClick={handleFetchModels}
                                    disabled={fetchingModels || !canUpdate}
                                    className="text-xs h-8 gap-1.5"
                                >
                                    {fetchingModels ? (
                                        <Loader2 className="size-3.5 animate-spin" />
                                    ) : (
                                        <RefreshCw className="size-3.5 text-indigo-500" />
                                    )}
                                    Fetch Live Models from /models
                                </Button>
                            </div>

                            <div className="flex items-center gap-2 text-xs text-muted-foreground">
                                {discoveredModels[activeTab]?.length > 0 ? (
                                    <Badge variant="outline" className="text-xs font-mono">
                                        {discoveredModels[activeTab].length} models discovered
                                    </Badge>
                                ) : (
                                    <span className="text-[11px]">No models fetched yet. Manual input enabled.</span>
                                )}
                            </div>
                        </div>

                        {/* Connection Test / Model Fetch Alert Feedback */}
                        {connectionStatus && (
                            <Alert variant={connectionStatus.success ? "default" : "destructive"} className="py-2.5">
                                {connectionStatus.success ? (
                                    <CheckCircle2 className="size-4 text-emerald-500" />
                                ) : (
                                    <AlertCircle className="size-4" />
                                )}
                                <AlertTitle className="text-xs font-semibold">
                                    {connectionStatus.success ? "Connection Verified" : "Connection Check Failed"}
                                </AlertTitle>
                                <AlertDescription className="text-xs">{connectionStatus.text}</AlertDescription>
                            </Alert>
                        )}

                        {fetchMessage && (
                            <Alert variant={fetchMessage.error ? "destructive" : "default"} className="py-2.5">
                                {fetchMessage.error ? <AlertTriangle className="size-4" /> : <CheckCircle2 className="size-4 text-emerald-500" />}
                                <AlertTitle className="text-xs font-semibold">
                                    {fetchMessage.error ? "Model Discovery Notice" : "Model Catalog Synchronized"}
                                </AlertTitle>
                                <AlertDescription className="text-xs">{fetchMessage.text}</AlertDescription>
                            </Alert>
                        )}

                        {/* Model Selection Matrix (Dropdown / Manual Hybrid) */}
                        <div className="space-y-4 pt-2">
                            <div className="flex items-center justify-between">
                                <h4 className="text-sm font-semibold flex items-center gap-1.5">
                                    <Layers className="size-4 text-indigo-500" />
                                    Default Model Assignments
                                </h4>
                                <span className="text-[11px] text-muted-foreground">
                                    Select from discovered models or type custom model identifier
                                </span>
                            </div>

                            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                {/* Chat / Primary Model */}
                                <div className="space-y-1.5">
                                    <Label htmlFor="default_chat_model" className="text-xs font-medium">
                                        Default Chat / Agent Model
                                    </Label>
                                    <Input
                                        id="default_chat_model"
                                        list={`models-list-${activeTab}`}
                                        value={activeChatModel}
                                        onChange={(e) => updateActiveField("default_chat_model", e.target.value)}
                                        placeholder="e.g. llama-3.3-70b or gpt-4o"
                                        disabled={!canUpdate}
                                        className="font-mono text-xs"
                                    />
                                    <p className="text-[11px] text-muted-foreground">
                                        Used by default for complex student & faculty agent prompts.
                                    </p>
                                </div>

                                {/* Fast / Low Cost Model */}
                                <div className="space-y-1.5">
                                    <Label htmlFor="default_fast_model" className="text-xs font-medium">
                                        Fast / Lightweight Model
                                    </Label>
                                    <Input
                                        id="default_fast_model"
                                        list={`models-list-${activeTab}`}
                                        value={activeFastModel}
                                        onChange={(e) => updateActiveField("default_fast_model", e.target.value)}
                                        placeholder="e.g. llama-3.1-8b or gpt-4o-mini"
                                        disabled={!canUpdate}
                                        className="font-mono text-xs"
                                    />
                                    <p className="text-[11px] text-muted-foreground">
                                        Used for summarization, quick validations, and title generation.
                                    </p>
                                </div>

                                {/* Embeddings Model */}
                                <div className="space-y-1.5">
                                    <Label htmlFor="default_embeddings_model" className="text-xs font-medium">
                                        Vector Embeddings Model
                                    </Label>
                                    <Input
                                        id="default_embeddings_model"
                                        list={`models-list-${activeTab}`}
                                        value={activeEmbeddingsModel}
                                        onChange={(e) => updateActiveField("default_embeddings_model", e.target.value)}
                                        placeholder="e.g. text-embedding-3-small or bge-large"
                                        disabled={!canUpdate}
                                        className="font-mono text-xs"
                                    />
                                    <p className="text-[11px] text-muted-foreground">
                                        Used for RAG similarity search and vector indexing.
                                    </p>
                                </div>
                            </div>

                            {/* Datalist for Autocomplete from Discovered Models */}
                            <datalist id={`models-list-${activeTab}`}>
                                {availableModels.map((m) => (
                                    <option key={m.id} value={m.id}>
                                        {m.label}
                                    </option>
                                ))}
                            </datalist>
                        </div>

                        {/* Custom / Manual Model Manager */}
                        <div className="space-y-3 p-4 rounded-xl border bg-muted/10">
                            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                                <div>
                                    <h4 className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                        Custom & Fine-Tuned Model Overrides
                                    </h4>
                                    <p className="text-[11px] text-muted-foreground">
                                        If your model is not exposed via /models (e.g. fine-tuned tags, private deployments), register it manually here.
                                    </p>
                                </div>
                            </div>

                            <div className="flex items-center gap-2 max-w-md">
                                <Input
                                    type="text"
                                    placeholder="e.g. ft:gpt-4o-mini:school:custom-v1"
                                    value={newCustomModel}
                                    onChange={(e) => setNewCustomModel(e.target.value)}
                                    onKeyDown={(e) => {
                                        if (e.key === "Enter") {
                                            e.preventDefault();
                                            addCustomModel();
                                        }
                                    }}
                                    disabled={!canUpdate}
                                    className="font-mono text-xs h-8"
                                />
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={addCustomModel}
                                    disabled={!canUpdate || !newCustomModel.trim()}
                                    className="h-8 text-xs gap-1"
                                >
                                    <Plus className="size-3.5" />
                                    Add Model
                                </Button>
                            </div>

                            {/* Chips of Registered Custom Models */}
                            {activeCustomModels?.length > 0 ? (
                                <div className="flex flex-wrap gap-1.5 pt-1">
                                    {activeCustomModels.map((modelId) => (
                                        <Badge
                                            key={modelId}
                                            variant="secondary"
                                            className="font-mono text-[11px] py-0.5 px-2 gap-1.5"
                                        >
                                            {modelId}
                                            {canUpdate && (
                                                <button
                                                    type="button"
                                                    onClick={() => removeCustomModel(modelId)}
                                                    className="text-muted-foreground hover:text-destructive"
                                                >
                                                    <Trash2 className="size-3" />
                                                </button>
                                            )}
                                        </Badge>
                                    ))}
                                </div>
                            ) : (
                                <p className="text-[11px] text-muted-foreground italic">
                                    No manual model overrides added. You can also type directly in the fields above.
                                </p>
                            )}
                        </div>
                    </CardContent>
                </Card>

                {/* Sticky Action Footer */}
                {canUpdate && (
                    <div className="sticky bottom-4 z-10 flex items-center justify-between p-4 rounded-xl border bg-background/95 backdrop-blur shadow-lg">
                        <div className="flex items-center gap-2 text-xs text-muted-foreground">
                            <ShieldCheck className="size-4 text-emerald-500" />
                            <span>Configuration changes will immediately apply to all active agents.</span>
                        </div>
                        <Button type="submit" disabled={form.processing} className="gap-2 text-xs h-9">
                            {form.processing ? <Loader2 className="size-4 animate-spin" /> : <Save className="size-4" />}
                            Save AI Configuration
                        </Button>
                    </div>
                )}
            </form>
        </SystemManagementLayout>
    );
}
