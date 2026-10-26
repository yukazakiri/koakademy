import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Separator } from "@/components/ui/separator";
import { Switch } from "@/components/ui/switch";
import { BellRing, Eye, FileClock, GraduationCap, ImageUp, MessageSquare, Plus, Trash2 } from "lucide-react";
import type { ReactNode } from "react";
import type { ClassFormData, ClassSettings } from "../../lib/class-defaults";
import { FormSection } from "../form-section";

type PolicyStepProps = {
    data: ClassFormData;
    onDataChange: (data: Partial<ClassFormData>) => void;
};

type FeatureToggle = {
    key: keyof Pick<
        ClassSettings,
        | "enable_announcements"
        | "enable_grade_visibility"
        | "enable_attendance_tracking"
        | "allow_late_submissions"
        | "enable_discussion_board"
        | "notify_students_on_schedule_changes"
    >;
    label: string;
    description: string;
    icon: ReactNode;
};

const featureToggles: FeatureToggle[] = [
    {
        key: "enable_announcements",
        label: "Announcements",
        description: "Faculty can post announcements that every enrolled student sees.",
        icon: <BellRing className="size-4" />,
    },
    {
        key: "enable_grade_visibility",
        label: "Grade visibility",
        description: "Students can see their own grades and the feedback on them.",
        icon: <Eye className="size-4" />,
    },
    {
        key: "enable_attendance_tracking",
        label: "Attendance tracking",
        description: "Attendance is recorded per meeting and charted over the term.",
        icon: <FileClock className="size-4" />,
    },
    {
        key: "allow_late_submissions",
        label: "Late submissions",
        description: "Students may hand in assignments after the deadline.",
        icon: <GraduationCap className="size-4" />,
    },
    {
        key: "enable_discussion_board",
        label: "Discussion board",
        description: "Opens a forum where students and faculty can talk about the subject.",
        icon: <MessageSquare className="size-4" />,
    },
    {
        key: "notify_students_on_schedule_changes",
        label: "Schedule alerts",
        description: "Students are notified whenever the weekly schedule changes.",
        icon: <BellRing className="size-4" />,
    },
];

export function PolicyStep({ data, onDataChange }: PolicyStepProps): ReactNode {
    const updateSettings = (settings: Partial<ClassSettings>): void => {
        onDataChange({ settings: { ...data.settings, ...settings } });
    };

    const customEntries = Object.entries(data.settings.custom ?? {});

    const addCustomSetting = (): void => {
        const custom = { ...(data.settings.custom ?? {}) };
        let index = 1;

        while (custom[`setting_${index}`] !== undefined) {
            index += 1;
        }

        custom[`setting_${index}`] = "";

        updateSettings({ custom });
    };

    const updateCustomSetting = (key: string, value: string): void => {
        updateSettings({ custom: { ...(data.settings.custom ?? {}), [key]: value } });
    };

    const removeCustomSetting = (key: string): void => {
        const custom = { ...(data.settings.custom ?? {}) };

        delete custom[key];

        updateSettings({ custom });
    };

    return (
        <div className="space-y-6">
            <FormSection title="Classroom features" description="Control the tools students and faculty can use in this class.">
                <div className="divide-y">
                    {featureToggles.map((toggle) => {
                        const enabled = Boolean(data.settings[toggle.key]);

                        return (
                            <label
                                key={toggle.key}
                                htmlFor={`class-policy-${toggle.key}`}
                                className="flex cursor-pointer items-start gap-4 py-4 first:pt-0 last:pb-0"
                            >
                                <span className="text-muted-foreground mt-1 shrink-0" aria-hidden="true">
                                    {toggle.icon}
                                </span>
                                <span className="min-w-0 flex-1">
                                    <span className="block text-sm font-medium">{toggle.label}</span>
                                    <span className="text-muted-foreground mt-1 block text-sm">{toggle.description}</span>
                                </span>
                                <Switch
                                    id={`class-policy-${toggle.key}`}
                                    checked={enabled}
                                    onCheckedChange={(checked) => updateSettings({ [toggle.key]: checked })}
                                    className="mt-0.5"
                                />
                            </label>
                        );
                    })}
                </div>
            </FormSection>

            <FormSection title="Class appearance" description="Optional visual preferences for the student classroom.">
                <div className="space-y-6">
                    <div className="space-y-2">
                        <Label htmlFor="class-theme">Classroom theme</Label>
                        <Select value={data.settings.theme} onValueChange={(theme) => updateSettings({ theme: theme as ClassSettings["theme"] })}>
                            <SelectTrigger id="class-theme" className="w-full sm:max-w-sm">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {(["default", "modern", "classic", "minimal", "vibrant"] as const).map((theme) => (
                                    <SelectItem key={theme} value={theme}>
                                        {theme.charAt(0).toUpperCase() + theme.slice(1)}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="space-y-2">
                            <Label htmlFor="class-background-color">Background colour</Label>
                            <div className="flex items-center gap-3">
                                <Input
                                    id="class-background-color"
                                    type="color"
                                    value={data.settings.background_color}
                                    onChange={(event) => updateSettings({ background_color: event.target.value })}
                                    className="h-10 w-14 p-1"
                                />
                                <span className="text-muted-foreground font-mono text-sm">{data.settings.background_color}</span>
                            </div>
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="class-accent-color">Accent colour</Label>
                            <div className="flex items-center gap-3">
                                <Input
                                    id="class-accent-color"
                                    type="color"
                                    value={data.settings.accent_color}
                                    onChange={(event) => updateSettings({ accent_color: event.target.value })}
                                    className="h-10 w-14 p-1"
                                />
                                <span className="text-muted-foreground font-mono text-sm">{data.settings.accent_color}</span>
                            </div>
                        </div>
                    </div>

                    <Separator />

                    <div className="space-y-2">
                        <Label htmlFor="class-banner-image" className="gap-2">
                            <ImageUp className="size-4" />
                            Banner image
                        </Label>
                        <Input
                            id="class-banner-image"
                            type="file"
                            accept="image/*"
                            onChange={(event) => updateSettings({ banner_image: event.target.files?.[0] ?? null })}
                        />
                        <p className="text-muted-foreground text-sm">Shown at the top of the class page for the students.</p>
                    </div>
                </div>
            </FormSection>

            <FormSection title="Custom preferences" description="Additional values used by this class. Existing preference names are retained.">
                <div className="space-y-4">
                    {customEntries.length === 0 ? (
                        <p className="text-muted-foreground text-sm">No custom settings yet.</p>
                    ) : (
                        customEntries.map(([key, value]) => (
                            <div key={key} className="grid gap-2 sm:grid-cols-[minmax(0,1fr)_minmax(0,2fr)_auto]">
                                <Input value={key} disabled aria-label="Setting name" />
                                <Input
                                    value={value}
                                    onChange={(event) => updateCustomSetting(key, event.target.value)}
                                    aria-label={`Value for ${key}`}
                                />
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    className="text-muted-foreground hover:text-destructive size-11"
                                    onClick={() => removeCustomSetting(key)}
                                    aria-label={`Remove ${key}`}
                                >
                                    <Trash2 className="size-4" />
                                </Button>
                            </div>
                        ))
                    )}

                    <Button type="button" variant="outline" className="h-11 px-3" onClick={addCustomSetting}>
                        <Plus className="size-4" />
                        Add setting
                    </Button>
                </div>
            </FormSection>
        </div>
    );
}
