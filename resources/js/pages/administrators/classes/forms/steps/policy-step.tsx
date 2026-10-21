import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Separator } from "@/components/ui/separator";
import { Switch } from "@/components/ui/switch";
import { cn } from "@/lib/utils";
import { BellRing, Eye, FileClock, GraduationCap, ImageUp, MessageSquare, Plus, Trash2 } from "lucide-react";
import type { ReactNode } from "react";
import type { ClassFormData, ClassSettings } from "../../lib/class-defaults";

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
        <div className="space-y-5">
            <Card>
                <CardHeader>
                    <CardTitle>Classroom features</CardTitle>
                    <CardDescription>What the students of this class can do. Every feature can be turned off again later.</CardDescription>
                </CardHeader>
                <CardContent>
                    <div className="grid gap-3 md:grid-cols-2">
                        {featureToggles.map((toggle) => {
                            const enabled = Boolean(data.settings[toggle.key]);

                            return (
                                <label
                                    key={toggle.key}
                                    htmlFor={`class-policy-${toggle.key}`}
                                    className={cn(
                                        "flex cursor-pointer items-start gap-3 rounded-xl border p-4 transition-colors duration-200",
                                        enabled ? "border-primary/40 bg-primary/5" : "hover:bg-muted/60",
                                    )}
                                >
                                    <span className={cn("mt-0.5 shrink-0", enabled ? "text-primary" : "text-muted-foreground")}>{toggle.icon}</span>
                                    <span className="min-w-0 flex-1">
                                        <span className="block text-sm font-medium">{toggle.label}</span>
                                        <span className="mt-0.5 block text-xs text-muted-foreground">{toggle.description}</span>
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
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Appearance</CardTitle>
                    <CardDescription>How the class looks to the students. The banner is optional.</CardDescription>
                </CardHeader>
                <CardContent className="space-y-5">
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
                                <span className="font-mono text-xs text-muted-foreground">{data.settings.background_color}</span>
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
                                <span className="font-mono text-xs text-muted-foreground">{data.settings.accent_color}</span>
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
                        <p className="text-xs text-muted-foreground">Shown at the top of the class page for the students.</p>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Custom preferences</CardTitle>
                    <CardDescription>Extra key and value settings for this class.</CardDescription>
                </CardHeader>
                <CardContent className="space-y-3">
                    {customEntries.length === 0 ? (
                        <p className="text-sm text-muted-foreground">No custom settings yet.</p>
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
                                    className="text-muted-foreground hover:text-destructive"
                                    onClick={() => removeCustomSetting(key)}
                                    aria-label={`Remove ${key}`}
                                >
                                    <Trash2 className="size-4" />
                                </Button>
                            </div>
                        ))
                    )}

                    <Button type="button" variant="outline" size="sm" onClick={addCustomSetting}>
                        <Plus className="size-4" />
                        Add setting
                    </Button>
                </CardContent>
            </Card>
        </div>
    );
}
