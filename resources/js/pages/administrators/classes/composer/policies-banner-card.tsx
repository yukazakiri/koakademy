import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from "@/components/ui/collapsible";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Separator } from "@/components/ui/separator";
import { Switch } from "@/components/ui/switch";
import { cn } from "@/lib/utils";
import {
    BellRing,
    ChevronDown,
    Eye,
    FileClock,
    FolderOpen,
    GraduationCap,
    ImageUp,
    MessageSquare,
    Palette,
    Plus,
    RotateCcw,
    Trash2,
} from "lucide-react";
import { useEffect, useRef, useState, type ChangeEvent, type ReactNode } from "react";
import { toast } from "sonner";
import type { ClassSettings } from "../lib/class-defaults";

const MAX_BANNER_BYTES = 4 * 1024 * 1024;
const ALLOWED_BANNER_TYPES = new Set(["image/jpeg", "image/png", "image/webp"]);

type PolicyToggleKey = keyof Pick<
    ClassSettings,
    | "enable_announcements"
    | "enable_grade_visibility"
    | "enable_attendance_tracking"
    | "enable_online_resources"
    | "allow_late_submissions"
    | "enable_discussion_board"
    | "notify_students_on_schedule_changes"
>;

const POLICY_TOGGLES: Array<{
    key: PolicyToggleKey;
    label: string;
    description: string;
    icon: ReactNode;
}> = [
    {
        key: "enable_announcements",
        label: "Announcements",
        description: "Allow instructors to post class updates visible to all enrolled students.",
        icon: <BellRing className="size-4" aria-hidden="true" />,
    },
    {
        key: "enable_grade_visibility",
        label: "Grade Visibility",
        description: "Let students view their posted grades and instructor feedback.",
        icon: <Eye className="size-4" aria-hidden="true" />,
    },
    {
        key: "enable_attendance_tracking",
        label: "Attendance Tracking",
        description: "Track per-session student attendance and surface attendance rates.",
        icon: <FileClock className="size-4" aria-hidden="true" />,
    },
    {
        key: "enable_online_resources",
        label: "Online Resources",
        description: "Enable downloadable files, handouts, and study materials in the classroom.",
        icon: <FolderOpen className="size-4" aria-hidden="true" />,
    },
    {
        key: "allow_late_submissions",
        label: "Late Submissions",
        description: "Permit students to submit coursework after the scheduled due date.",
        icon: <GraduationCap className="size-4" aria-hidden="true" />,
    },
    {
        key: "enable_discussion_board",
        label: "Discussion Board",
        description: "Provide a class forum for questions and collaborative discussion.",
        icon: <MessageSquare className="size-4" aria-hidden="true" />,
    },
    {
        key: "notify_students_on_schedule_changes",
        label: "Schedule-Change Notifications",
        description: "Notify enrolled students whenever weekly meeting times or rooms change.",
        icon: <BellRing className="size-4" aria-hidden="true" />,
    },
];

const THEME_OPTIONS: Array<{ value: ClassSettings["theme"]; label: string }> = [
    { value: "default", label: "Default" },
    { value: "modern", label: "Modern" },
    { value: "classic", label: "Classic" },
    { value: "minimal", label: "Minimal" },
    { value: "vibrant", label: "Vibrant" },
];

type CustomRow = {
    id: string;
    key: string;
    value: string;
};

export type PoliciesBannerCardProps = {
    settings: ClassSettings;
    existingBannerUrl: string | null;
    removeBannerImage: boolean;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onSettingsChange: (patch: Partial<ClassSettings>) => void;
    onRemoveBannerChange: (remove: boolean) => void;
    onCustomErrorChange: (error: string | null) => void;
    errors?: Record<string, string>;
};

export function PoliciesBannerCard({
    settings,
    existingBannerUrl,
    removeBannerImage,
    open,
    onOpenChange,
    onSettingsChange,
    onRemoveBannerChange,
    onCustomErrorChange,
    errors = {},
}: PoliciesBannerCardProps): ReactNode {
    const fileInputRef = useRef<HTMLInputElement>(null);
    const [localPreviewUrl, setLocalPreviewUrl] = useState<string | null>(null);
    const [bannerError, setBannerError] = useState<string | null>(null);
    const [customRows, setCustomRows] = useState<CustomRow[]>(() =>
        Object.entries(settings.custom ?? {}).map(([key, value], idx) => ({
            id: `row-${idx}-${key}`,
            key,
            value,
        })),
    );
    const syncedCustomJsonRef = useRef<string>(JSON.stringify(settings.custom ?? {}));

    // Sync local custom rows only when settings.custom changes externally (e.g., draft restore)
    useEffect(() => {
        const incoming = JSON.stringify(settings.custom ?? {});
        if (incoming !== syncedCustomJsonRef.current) {
            syncedCustomJsonRef.current = incoming;
            onCustomErrorChange(null);
            setCustomRows(
                Object.entries(settings.custom ?? {}).map(([key, value], idx) => ({
                    id: `row-ext-${idx}-${key}`,
                    key,
                    value,
                })),
            );
        }
    }, [settings.custom, onCustomErrorChange]);

    // Manage object URL for newly picked banner file
    useEffect(() => {
        if (!(settings.banner_image instanceof File)) {
            setLocalPreviewUrl(null);
            return;
        }
        const url = URL.createObjectURL(settings.banner_image);
        setLocalPreviewUrl(url);
        return () => {
            URL.revokeObjectURL(url);
        };
    }, [settings.banner_image]);

    const activePolicyCount = POLICY_TOGGLES.filter((toggle) => {
        if (toggle.key === "enable_online_resources") {
            return settings.enable_online_resources ?? true;
        }
        return Boolean(settings[toggle.key]);
    }).length;

    const activeBannerPreview = localPreviewUrl ?? (!removeBannerImage ? existingBannerUrl : null);

    const handleBannerFilePick = (event: ChangeEvent<HTMLInputElement>): void => {
        const file = event.target.files?.[0] ?? null;
        event.target.value = "";
        if (!file) return;

        if (file.type && !ALLOWED_BANNER_TYPES.has(file.type)) {
            const msg = "Please choose a JPEG, PNG, or WebP image.";
            setBannerError(msg);
            toast.error(msg);
            return;
        }

        if (file.size > MAX_BANNER_BYTES) {
            const msg = "Banner image must be 4 MB or smaller.";
            setBannerError(msg);
            toast.error(msg);
            return;
        }

        setBannerError(null);
        onRemoveBannerChange(false);
        onSettingsChange({ banner_image: file });
    };

    const handleRemoveBanner = (): void => {
        setBannerError(null);
        onSettingsChange({ banner_image: null });
        if (existingBannerUrl) {
            onRemoveBannerChange(true);
        }
    };

    const handleRestoreExistingBanner = (): void => {
        setBannerError(null);
        onRemoveBannerChange(false);
    };

    const commitCustomRows = (nextRows: CustomRow[]): void => {
        setCustomRows(nextRows);
        const keys = nextRows.map((row) => row.key.trim()).filter(Boolean);
        onCustomErrorChange(new Set(keys).size === keys.length ? null : "Metadata keys must be unique after trimming spaces.");
        const record: Record<string, string> = Object.create(null);
        for (const row of nextRows) {
            const trimmedKey = row.key.trim();
            if (trimmedKey.length > 0) {
                record[trimmedKey] = row.value;
            }
        }
        syncedCustomJsonRef.current = JSON.stringify(record);
        onSettingsChange({ custom: record });
    };

    const addCustomRow = (): void => {
        commitCustomRows([
            ...customRows,
            { id: `row-${Date.now()}-${Math.random().toString(36).slice(2, 6)}`, key: "", value: "" },
        ]);
    };

    const updateCustomRow = (id: string, field: "key" | "value", nextValue: string): void => {
        commitCustomRows(customRows.map((row) => (row.id === id ? { ...row, [field]: nextValue } : row)));
    };

    const removeCustomRow = (id: string): void => {
        commitCustomRows(customRows.filter((row) => row.id !== id));
    };

    const themeLabel = THEME_OPTIONS.find((t) => t.value === settings.theme)?.label ?? "Default";

    return (
        <Card id="composer-settings" className="scroll-mt-24 rounded-2xl shadow-2xs">
            <Collapsible open={open} onOpenChange={onOpenChange}>
                <CardHeader className={cn("pb-3.5", open && "border-b")}>
                    <CollapsibleTrigger asChild>
                        <button
                            type="button"
                            className="flex w-full items-center justify-between gap-3 text-left outline-none focus-visible:ring-2 focus-visible:ring-ring"
                        >
                            <div className="flex items-center gap-2.5">
                                <span className="bg-muted text-muted-foreground flex size-6 shrink-0 items-center justify-center rounded-md border text-xs font-bold">
                                    3
                                </span>
                                <div>
                                    <CardTitle className="text-sm font-bold tracking-tight">
                                        Policies, Appearance &amp; Banner
                                    </CardTitle>
                                    <p className="text-muted-foreground mt-0.5 text-xs">
                                        {activePolicyCount} of {POLICY_TOGGLES.length} policies enabled · {themeLabel} theme
                                        {activeBannerPreview ? " · Custom banner" : ""}
                                    </p>
                                </div>
                            </div>

                            <div className="flex items-center gap-2">
                                {existingBannerUrl && !removeBannerImage && !(settings.banner_image instanceof File) ? (
                                    <Badge
                                        variant="outline"
                                        className="hidden border-emerald-500/35 bg-emerald-500/10 text-[11px] font-medium text-emerald-700 sm:inline-flex dark:text-emerald-300"
                                    >
                                        Banner preserved
                                    </Badge>
                                ) : null}
                                <ChevronDown
                                    className={cn(
                                        "text-muted-foreground size-4 shrink-0 transition-transform duration-200",
                                        open && "rotate-180",
                                    )}
                                    aria-hidden="true"
                                />
                            </div>
                        </button>
                    </CollapsibleTrigger>
                </CardHeader>

                <CollapsibleContent>
                    <CardContent className="space-y-6 pt-4">
                        {/* Classroom Policy Toggles */}
                        <div className="space-y-2.5">
                            <h3 className="text-foreground text-xs font-semibold tracking-wide uppercase">
                                Classroom Features
                            </h3>
                            <div className="grid gap-2.5 sm:grid-cols-2">
                                {POLICY_TOGGLES.map((toggle) => {
                                    const checked =
                                        toggle.key === "enable_online_resources"
                                            ? (settings.enable_online_resources ?? true)
                                            : Boolean(settings[toggle.key]);

                                    return (
                                        <label
                                            key={toggle.key}
                                            htmlFor={`composer-policy-${toggle.key}`}
                                            className={cn(
                                                "flex cursor-pointer items-start justify-between gap-3 rounded-xl border p-3 transition-colors",
                                                checked ? "border-primary/25 bg-primary/[0.02]" : "bg-card hover:bg-muted/30",
                                            )}
                                        >
                                            <div className="flex min-w-0 items-start gap-2.5">
                                                <span className="text-muted-foreground mt-0.5 shrink-0">{toggle.icon}</span>
                                                <div className="min-w-0">
                                                    <span className="text-foreground block text-xs font-semibold">
                                                        {toggle.label}
                                                    </span>
                                                    <span className="text-muted-foreground mt-0.5 block text-[11px] leading-relaxed">
                                                        {toggle.description}
                                                    </span>
                                                </div>
                                            </div>
                                            <Switch
                                                id={`composer-policy-${toggle.key}`}
                                                checked={checked}
                                                onCheckedChange={(nextChecked) =>
                                                    onSettingsChange({ [toggle.key]: nextChecked })
                                                }
                                                className="mt-0.5 shrink-0"
                                            />
                                        </label>
                                    );
                                })}
                            </div>
                        </div>

                        <Separator />

                        {/* Appearance & Banner Grid */}
                        <div className="grid gap-6 md:grid-cols-2">
                            {/* Appearance */}
                            <div className="space-y-4">
                                <h3 className="text-foreground flex items-center gap-1.5 text-xs font-semibold tracking-wide uppercase">
                                    <Palette className="text-primary size-3.5" aria-hidden="true" />
                                    Appearance
                                </h3>

                                <div className="space-y-1.5">
                                    <Label htmlFor="composer-theme" className="text-xs font-medium">
                                        Theme preset
                                    </Label>
                                    <Select
                                        value={settings.theme}
                                        onValueChange={(value) =>
                                            onSettingsChange({ theme: value as ClassSettings["theme"] })
                                        }
                                    >
                                        <SelectTrigger id="composer-theme" className="w-full">
                                            <SelectValue placeholder="Choose theme" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {THEME_OPTIONS.map((theme) => (
                                                <SelectItem key={theme.value} value={theme.value}>
                                                    {theme.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div className="grid grid-cols-2 gap-3">
                                    <div className="space-y-1.5">
                                        <Label htmlFor="composer-bg-color" className="text-xs font-medium">
                                            Background color
                                        </Label>
                                        <div className="flex items-center gap-2 rounded-lg border px-2 py-1.5">
                                            <input
                                                id="composer-bg-color"
                                                type="color"
                                                value={settings.background_color}
                                                onChange={(e) =>
                                                    onSettingsChange({ background_color: e.target.value })
                                                }
                                                className="size-6 cursor-pointer rounded border-0 bg-transparent p-0"
                                            />
                                            <span className="text-muted-foreground font-mono text-xs uppercase">
                                                {settings.background_color}
                                            </span>
                                        </div>
                                    </div>

                                    <div className="space-y-1.5">
                                        <Label htmlFor="composer-accent-color" className="text-xs font-medium">
                                            Accent color
                                        </Label>
                                        <div className="flex items-center gap-2 rounded-lg border px-2 py-1.5">
                                            <input
                                                id="composer-accent-color"
                                                type="color"
                                                value={settings.accent_color}
                                                onChange={(e) => onSettingsChange({ accent_color: e.target.value })}
                                                className="size-6 cursor-pointer rounded border-0 bg-transparent p-0"
                                            />
                                            <span className="text-muted-foreground font-mono text-xs uppercase">
                                                {settings.accent_color}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {/* Banner Image */}
                            <div className="space-y-3">
                                <div className="flex items-center justify-between gap-2">
                                    <h3 className="text-foreground flex items-center gap-1.5 text-xs font-semibold tracking-wide uppercase">
                                        <ImageUp className="text-primary size-3.5" aria-hidden="true" />
                                        Class Banner
                                    </h3>
                                    <span className="text-muted-foreground text-[11px]">JPG, PNG, WebP · Max 4 MB</span>
                                </div>

                                <input
                                    ref={fileInputRef}
                                    type="file"
                                    tabIndex={-1}
                                    accept="image/jpeg,image/png,image/webp"
                                    className="sr-only"
                                    onChange={handleBannerFilePick}
                                />

                                <div className="bg-muted/25 relative overflow-hidden rounded-xl border">
                                    {activeBannerPreview ? (
                                        <img
                                            src={activeBannerPreview}
                                            alt="Class banner preview"
                                            className="h-28 w-full object-cover"
                                        />
                                    ) : (
                                        <div
                                            className="flex h-28 w-full flex-col items-center justify-center gap-1 px-4 text-center"
                                            style={{
                                                background: `linear-gradient(135deg, ${settings.background_color} 0%, ${settings.accent_color}22 100%)`,
                                            }}
                                        >
                                            <ImageUp className="text-muted-foreground size-5" aria-hidden="true" />
                                            <span className="text-muted-foreground text-xs font-medium">
                                                {removeBannerImage
                                                    ? "Existing banner marked for removal"
                                                    : "No custom banner uploaded"}
                                            </span>
                                        </div>
                                    )}
                                </div>

                                <div className="flex flex-wrap items-center justify-between gap-2">
                                    <div className="text-muted-foreground min-w-0 truncate text-xs">
                                        {settings.banner_image instanceof File ? (
                                            <span className="text-foreground font-medium">
                                                Selected: {settings.banner_image.name}
                                            </span>
                                        ) : removeBannerImage ? (
                                            <span className="text-amber-700 dark:text-amber-400">
                                                Will be removed when saved
                                            </span>
                                        ) : existingBannerUrl ? (
                                            <span>Existing banner active</span>
                                        ) : (
                                            <span>Uses theme colors by default</span>
                                        )}
                                    </div>

                                    <div className="flex items-center gap-1.5">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            className="h-8 gap-1.5 text-xs"
                                            onClick={() => fileInputRef.current?.click()}
                                        >
                                            <ImageUp className="size-3.5" aria-hidden="true" />
                                            {activeBannerPreview ? "Replace Banner" : "Upload Banner"}
                                        </Button>

                                        {activeBannerPreview ? (
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                className="text-muted-foreground hover:text-destructive h-8 text-xs"
                                                onClick={handleRemoveBanner}
                                            >
                                                Remove
                                            </Button>
                                        ) : removeBannerImage && existingBannerUrl ? (
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                className="h-8 gap-1 text-xs"
                                                onClick={handleRestoreExistingBanner}
                                            >
                                                <RotateCcw className="size-3" aria-hidden="true" />
                                                Keep existing
                                            </Button>
                                        ) : null}
                                    </div>
                                </div>

                                {bannerError || errors["settings.banner_image"] ? (
                                    <p role="alert" className="text-destructive text-xs font-medium">
                                        {bannerError ?? errors["settings.banner_image"]}
                                    </p>
                                ) : null}
                            </div>
                        </div>

                        <Separator />

                        {/* Custom Key-Value Metadata */}
                        <div className="space-y-3">
                            <div className="flex items-center justify-between gap-2">
                                <div>
                                    <h3 className="text-foreground text-xs font-semibold tracking-wide uppercase">
                                        Custom Metadata
                                    </h3>
                                    <p className="text-muted-foreground text-xs">
                                        Optional key-value properties saved alongside this class record.
                                    </p>
                                </div>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    className="h-8 gap-1 text-xs"
                                    onClick={addCustomRow}
                                >
                                    <Plus className="size-3.5" aria-hidden="true" />
                                    Add Field
                                </Button>
                            </div>

                            {customRows.length === 0 ? (
                                <p className="text-muted-foreground rounded-xl border border-dashed px-3.5 py-2.5 text-xs">
                                    No custom metadata fields added.
                                </p>
                            ) : (
                                <div className="space-y-2">
                                    {customRows.map((row) => (
                                        <div
                                            key={row.id}
                                            className="grid grid-cols-[minmax(0,1fr)_minmax(0,1.4fr)_auto] items-center gap-2"
                                        >
                                            <Input
                                                value={row.key}
                                                onChange={(e) => updateCustomRow(row.id, "key", e.target.value)}
                                                placeholder="Property name"
                                                aria-label="Custom metadata key"
                                                aria-invalid={row.key.trim() !== "" && customRows.filter((other) => other.key.trim() === row.key.trim()).length > 1}
                                                className="h-9 rounded-lg text-xs aria-invalid:border-destructive"
                                            />
                                            <Input
                                                value={row.value}
                                                onChange={(e) => updateCustomRow(row.id, "value", e.target.value)}
                                                placeholder="Value"
                                                aria-label={`Value for ${row.key || "custom field"}`}
                                                className="h-9 rounded-lg text-xs"
                                            />
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon-sm"
                                                className="text-muted-foreground hover:text-destructive"
                                                aria-label={`Remove ${row.key || "custom field"}`}
                                                onClick={() => removeCustomRow(row.id)}
                                            >
                                                <Trash2 className="size-3.5" />
                                            </Button>
                                        </div>
                                    ))}
                                </div>
                            )}
                            {customRows.some((row) => row.key.trim() !== "" && customRows.filter((other) => other.key.trim() === row.key.trim()).length > 1) ? (
                                <p role="alert" className="text-destructive text-xs">Metadata keys must be unique after trimming spaces.</p>
                            ) : null}
                        </div>
                    </CardContent>
                </CollapsibleContent>
            </Collapsible>
        </Card>
    );
}
