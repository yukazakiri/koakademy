import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Switch } from "@/components/ui/switch";
import { themes, type ColorTheme } from "@/conf/themes";
import { useTheme } from "@/hooks/use-theme";
import { useForm } from "@inertiajs/react";
import { Check, Columns3, Grid2X2, Laptop, LayoutList, Monitor, Moon, Paintbrush, ReceiptText, Save, Sun } from "lucide-react";
import { useEffect } from "react";
import { toast } from "sonner";

type PaymentWorkspacePreference = {
    layout: "guided" | "spreadsheet";
    density: "comfortable" | "compact";
    history_visibility: "auto" | "open" | "hidden";
    default_payment_method: string;
};

type PaymentMethodOption = {
    value: string;
    label: string;
};

type PersonalizationTabProps = {
    canConfigurePaymentWorkspace?: boolean;
    paymentWorkspace?: PaymentWorkspacePreference | null;
    paymentWorkspaceUrl?: string | null;
    paymentMethods?: PaymentMethodOption[];
    canConfigureTuitionAdjustmentWorkspace?: boolean;
    tuitionAdjustmentWorkspace?: { layout: "inspector" | "staged" } | null;
    tuitionAdjustmentWorkspaceUrl?: string | null;
};

const fallbackPaymentWorkspace: PaymentWorkspacePreference = {
    layout: "guided",
    density: "comfortable",
    history_visibility: "auto",
    default_payment_method: "Cash",
};

export function PersonalizationTab({
    canConfigurePaymentWorkspace = false,
    paymentWorkspace,
    paymentWorkspaceUrl,
    paymentMethods = [],
    canConfigureTuitionAdjustmentWorkspace = false,
    tuitionAdjustmentWorkspace,
    tuitionAdjustmentWorkspaceUrl,
}: PersonalizationTabProps) {
    const { theme, setThemeWithViewTransition, colorTheme, setColorTheme } = useTheme();
    const workspaceForm = useForm<PaymentWorkspacePreference>(paymentWorkspace ?? fallbackPaymentWorkspace);
    const tuitionWorkspaceForm = useForm<{ layout: "inspector" | "staged" }>(tuitionAdjustmentWorkspace ?? { layout: "inspector" });

    useEffect(() => {
        workspaceForm.setData(paymentWorkspace ?? fallbackPaymentWorkspace);
    }, [paymentWorkspace]);

    useEffect(() => {
        tuitionWorkspaceForm.setData(tuitionAdjustmentWorkspace ?? { layout: "inspector" });
    }, [tuitionAdjustmentWorkspace]);

    const savePaymentWorkspace = () => {
        if (!paymentWorkspaceUrl) return;

        workspaceForm.put(paymentWorkspaceUrl, {
            preserveScroll: true,
            onSuccess: () => toast.success("Finance workspace preferences saved."),
        });
    };

    return (
        <div className="space-y-6">
            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <Monitor className="h-5 w-5" />
                        Display Mode
                    </CardTitle>
                    <CardDescription>Choose your preferred interface appearance</CardDescription>
                </CardHeader>
                <CardContent>
                    <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                        {(
                            [
                                { value: "light", label: "Light", icon: Sun },
                                { value: "dark", label: "Dark", icon: Moon },
                                { value: "system", label: "System", icon: Laptop },
                            ] as const
                        ).map((mode) => (
                            <button
                                key={mode.value}
                                type="button"
                                onClick={(e) => setThemeWithViewTransition(mode.value, e)}
                                className={`relative flex items-center justify-center gap-3 rounded-xl border-2 p-4 transition-all duration-200 ${
                                    theme === mode.value
                                        ? "border-primary bg-primary/5 ring-primary/20 ring-1"
                                        : "border-muted hover:border-primary/50 hover:bg-muted/50"
                                } `}
                            >
                                <mode.icon className={`h-5 w-5 ${theme === mode.value ? "text-primary" : "text-muted-foreground"}`} />
                                <span className={`font-medium ${theme === mode.value ? "text-foreground" : "text-muted-foreground"}`}>
                                    {mode.label}
                                </span>
                                {theme === mode.value && (
                                    <div className="absolute top-3 right-3">
                                        <div className="bg-primary h-2 w-2 rounded-full" />
                                    </div>
                                )}
                            </button>
                        ))}
                    </div>
                </CardContent>
            </Card>

            {canConfigurePaymentWorkspace && paymentWorkspaceUrl && (
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <ReceiptText className="h-5 w-5" />
                            Finance Workspace
                        </CardTitle>
                        <CardDescription>
                            Set up the payment desk that best matches how you collect payments. These settings only affect your account.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-6">
                        <div className="space-y-3">
                            <Label className="text-muted-foreground text-xs font-medium tracking-wider uppercase">Default desk</Label>
                            <div className="grid gap-3 md:grid-cols-2">
                                {[
                                    {
                                        value: "guided" as const,
                                        title: "Guided desk",
                                        description: "One student at a time, with every balance and collection detail in view.",
                                        icon: ReceiptText,
                                    },
                                    {
                                        value: "spreadsheet" as const,
                                        title: "Spreadsheet desk",
                                        description: "A keyboard-first payment ledger for pasting independent rows from Excel.",
                                        icon: Grid2X2,
                                    },
                                ].map((workspace) => (
                                    <button
                                        key={workspace.value}
                                        type="button"
                                        onClick={() => workspaceForm.setData("layout", workspace.value)}
                                        className={`relative flex items-start gap-3 rounded-xl border p-4 text-left transition-colors ${
                                            workspaceForm.data.layout === workspace.value
                                                ? "border-primary bg-primary/5 ring-primary/20 ring-1"
                                                : "border-muted hover:border-primary/50 hover:bg-muted/50"
                                        }`}
                                    >
                                        <workspace.icon
                                            className={`mt-0.5 h-5 w-5 ${workspaceForm.data.layout === workspace.value ? "text-primary" : "text-muted-foreground"}`}
                                        />
                                        <span className="space-y-1">
                                            <span className="block font-medium">{workspace.title}</span>
                                            <span className="text-muted-foreground block text-sm leading-5">{workspace.description}</span>
                                        </span>
                                        {workspaceForm.data.layout === workspace.value && <Check className="text-primary ml-auto h-4 w-4" />}
                                    </button>
                                ))}
                            </div>
                        </div>

                        <div className="grid gap-5 md:grid-cols-3">
                            <div className="space-y-2">
                                <Label htmlFor="payment-workspace-density">Workspace density</Label>
                                <Select
                                    value={workspaceForm.data.density}
                                    onValueChange={(value) => workspaceForm.setData("density", value as PaymentWorkspacePreference["density"])}
                                >
                                    <SelectTrigger id="payment-workspace-density">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="comfortable">Comfortable</SelectItem>
                                        <SelectItem value="compact">Compact</SelectItem>
                                    </SelectContent>
                                </Select>
                                <p className="text-muted-foreground text-xs">Compact keeps more ledger rows on screen.</p>
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="payment-workspace-history">Payment history</Label>
                                <Select
                                    value={workspaceForm.data.history_visibility}
                                    onValueChange={(value) =>
                                        workspaceForm.setData("history_visibility", value as PaymentWorkspacePreference["history_visibility"])
                                    }
                                >
                                    <SelectTrigger id="payment-workspace-history">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="auto">Open when helpful</SelectItem>
                                        <SelectItem value="open">Always open</SelectItem>
                                        <SelectItem value="hidden">Keep hidden</SelectItem>
                                    </SelectContent>
                                </Select>
                                <p className="text-muted-foreground text-xs">You can still open or close it while recording.</p>
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="payment-workspace-method">Default payment method</Label>
                                <Select
                                    value={workspaceForm.data.default_payment_method}
                                    onValueChange={(value) => workspaceForm.setData("default_payment_method", value)}
                                >
                                    <SelectTrigger id="payment-workspace-method">
                                        <SelectValue placeholder="Choose a method" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {paymentMethods.map((method) => (
                                            <SelectItem key={method.value} value={method.value}>
                                                {method.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <p className="text-muted-foreground text-xs">Used to prefill new guided payments and ledger rows.</p>
                            </div>
                        </div>

                        <div className="flex justify-end border-t pt-4">
                            <Button type="button" onClick={savePaymentWorkspace} disabled={workspaceForm.processing}>
                                <Save className="mr-2 h-4 w-4" />
                                Save finance workspace
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            )}

            {canConfigureTuitionAdjustmentWorkspace && tuitionAdjustmentWorkspaceUrl && (
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Columns3 className="h-5 w-5" />
                            Tuition Adjustments Workspace
                        </CardTitle>
                        <CardDescription>
                            Choose the default review layout for your account. You can still switch layouts inside the workspace.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-5">
                        <div className="grid gap-3 md:grid-cols-2">
                            {[
                                {
                                    value: "inspector" as const,
                                    title: "Inspector",
                                    description: "Keep the selected enrollment, fee breakdown, and notification preview beside the ledger.",
                                    icon: Columns3,
                                },
                                {
                                    value: "staged" as const,
                                    title: "Staged table",
                                    description: "Use the full width for large pasted batches and review details only when needed.",
                                    icon: LayoutList,
                                },
                            ].map((workspace) => (
                                <button
                                    key={workspace.value}
                                    type="button"
                                    onClick={() => tuitionWorkspaceForm.setData("layout", workspace.value)}
                                    className={`relative flex items-start gap-3 rounded-xl border p-4 text-left transition-colors ${
                                        tuitionWorkspaceForm.data.layout === workspace.value
                                            ? "border-primary bg-primary/5 ring-primary/20 ring-1"
                                            : "border-muted hover:border-primary/50 hover:bg-muted/50"
                                    }`}
                                >
                                    <workspace.icon
                                        className={`mt-0.5 h-5 w-5 ${tuitionWorkspaceForm.data.layout === workspace.value ? "text-primary" : "text-muted-foreground"}`}
                                    />
                                    <span className="space-y-1">
                                        <span className="block font-medium">{workspace.title}</span>
                                        <span className="text-muted-foreground block text-sm leading-5">{workspace.description}</span>
                                    </span>
                                    {tuitionWorkspaceForm.data.layout === workspace.value && <Check className="text-primary ml-auto h-4 w-4" />}
                                </button>
                            ))}
                        </div>
                        <div className="flex justify-end border-t pt-4">
                            <Button
                                type="button"
                                disabled={tuitionWorkspaceForm.processing}
                                onClick={() =>
                                    tuitionWorkspaceForm.put(tuitionAdjustmentWorkspaceUrl, {
                                        preserveScroll: true,
                                        onSuccess: () => toast.success("Tuition adjustment workspace preference saved."),
                                    })
                                }
                            >
                                <Save className="mr-2 h-4 w-4" />
                                Save tuition layout
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            )}

            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <Paintbrush className="h-5 w-5" />
                        Color Theme
                    </CardTitle>
                    <CardDescription>Select a color palette that suits your style</CardDescription>
                </CardHeader>
                <CardContent className="space-y-6">
                    <div className="border-primary/20 bg-background relative overflow-hidden rounded-xl border-2 p-6 shadow-sm transition-all">
                        <div className="from-primary/5 to-accent/5 absolute inset-0 bg-gradient-to-br via-transparent" />
                        <div className="relative flex flex-col items-center justify-between gap-6 sm:flex-row">
                            <div className="space-y-1.5 text-center sm:text-left">
                                <div className="flex items-center justify-center gap-2 sm:justify-start">
                                    <h3 className="text-foreground text-xl font-bold tracking-tight">
                                        {themes.find((t) => t.id === colorTheme)?.name}
                                    </h3>
                                    <Badge variant="secondary" className="text-[10px] font-bold tracking-wider uppercase">
                                        Active
                                    </Badge>
                                </div>
                                <p className="text-muted-foreground max-w-md text-sm">{themes.find((t) => t.id === colorTheme)?.description}</p>
                            </div>

                            <div className="flex items-center gap-3">
                                <div className="mr-1 hidden flex-col items-end gap-0.5 border-r pr-4 sm:flex">
                                    <span className="text-muted-foreground text-[10px] font-medium tracking-wider uppercase">Typography</span>
                                    <span className="text-foreground text-xl leading-none" style={{ fontFamily: "var(--font-sans)" }}>
                                        Aa
                                    </span>
                                    <span className="text-muted-foreground text-[10px]" style={{ fontFamily: "var(--font-sans)" }}>
                                        123
                                    </span>
                                </div>

                                <div className="flex flex-col items-center gap-1">
                                    <div
                                        className="ring-border h-12 w-12 rounded-xl shadow-md ring-1 transition-transform hover:scale-110"
                                        style={{ backgroundColor: themes.find((t) => t.id === colorTheme)?.colors.primary }}
                                    />
                                    <span className="text-muted-foreground text-[10px] font-medium uppercase">Pri</span>
                                </div>
                                <div className="flex flex-col items-center gap-1">
                                    <div
                                        className="ring-border h-12 w-12 rounded-xl shadow-md ring-1 transition-transform hover:scale-110"
                                        style={{ backgroundColor: themes.find((t) => t.id === colorTheme)?.colors.secondary }}
                                    />
                                    <span className="text-muted-foreground text-[10px] font-medium uppercase">Sec</span>
                                </div>
                                <div className="flex flex-col items-center gap-1">
                                    <div
                                        className="ring-border h-12 w-12 rounded-xl shadow-md ring-1 transition-transform hover:scale-110"
                                        style={{ backgroundColor: themes.find((t) => t.id === colorTheme)?.colors.accent }}
                                    />
                                    <span className="text-muted-foreground text-[10px] font-medium uppercase">Acc</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div>
                        <Label className="text-muted-foreground mb-3 block text-xs font-medium tracking-wider uppercase">Available Themes</Label>
                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-3">
                            {themes.map((t) => {
                                return (
                                    <button
                                        key={t.id}
                                        type="button"
                                        onClick={() => setColorTheme(t.id as ColorTheme)}
                                        className={`group relative flex items-center justify-between rounded-xl border p-3 transition-all duration-200 ${
                                            colorTheme === t.id
                                                ? "border-primary bg-primary/5 ring-primary/20 shadow-md ring-1"
                                                : "border-muted bg-card hover:border-primary/30 hover:bg-accent/5 hover:shadow-sm"
                                        } `}
                                    >
                                        <div className="flex items-center gap-3">
                                            <div className="flex -space-x-2">
                                                <div
                                                    className="border-background h-8 w-8 rounded-full border-2 shadow-sm"
                                                    style={{ backgroundColor: t.colors.primary }}
                                                />
                                                <div
                                                    className="border-background h-8 w-8 rounded-full border-2 shadow-sm"
                                                    style={{ backgroundColor: t.colors.secondary }}
                                                />
                                            </div>
                                            <div className="space-y-0.5 text-left">
                                                <span
                                                    className={`block text-sm font-semibold transition-colors ${colorTheme === t.id ? "text-primary" : "text-foreground"}`}
                                                >
                                                    {t.name}
                                                </span>
                                                <span className="text-muted-foreground block text-[10px]">{t.font}</span>
                                            </div>
                                        </div>

                                        {colorTheme === t.id && (
                                            <div className="bg-primary text-primary-foreground animate-in zoom-in flex h-5 w-5 items-center justify-center rounded-full shadow-sm">
                                                <Check className="h-3 w-3" />
                                            </div>
                                        )}
                                    </button>
                                );
                            })}
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <LayoutGrid className="h-5 w-5" />
                        Dashboard Layout
                    </CardTitle>
                    <CardDescription>Customize your workspace density and behavior</CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                    <div className="bg-muted/20 flex items-center justify-between space-x-2 rounded-lg border p-4">
                        <Label htmlFor="compact-mode" className="flex cursor-pointer flex-col space-y-1">
                            <span className="font-medium">Compact Mode</span>
                            <span className="text-muted-foreground text-xs font-normal">
                                Reduce whitespace in lists and tables for higher density
                            </span>
                        </Label>
                        <Switch id="compact-mode" />
                    </div>
                    <div className="bg-muted/20 flex items-center justify-between space-x-2 rounded-lg border p-4">
                        <Label htmlFor="sidebar-collapsed" className="flex cursor-pointer flex-col space-y-1">
                            <span className="font-medium">Collapse Sidebar by Default</span>
                            <span className="text-muted-foreground text-xs font-normal">
                                Automatically collapse the navigation sidebar on page load
                            </span>
                        </Label>
                        <Switch id="sidebar-collapsed" />
                    </div>
                </CardContent>
            </Card>
        </div>
    );
}
