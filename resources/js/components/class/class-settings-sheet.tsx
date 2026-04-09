import { Button } from "@/components/ui/button";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Switch } from "@/components/ui/switch";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { cn } from "@/lib/utils";
import { ClassSettings } from "@/types/class-detail-types";
import { router } from "@inertiajs/react";
import { IconAlertTriangle, IconCalendar, IconPalette, IconPhoto, IconPlus, IconSettings } from "@tabler/icons-react";
import { useState } from "react";
import { toast } from "sonner";

interface ClassSettingsDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    classData: {
        id: number;
        subject_code: string;
        section: string;
        settings: any;
        room_id?: string;
        faculty_id?: string;
        maximum_slots?: number;
        semester?: string;
        school_year?: string;
        classification?: string;
        shs_strand_id?: string;
        subject_id?: string;
        start_date?: string;
        schedules?: any[];
    };
    rooms: { id: number; name: string }[];
}

const colorSwatches = [
    "bg-violet-500",
    "bg-indigo-500",
    "bg-blue-500",
    "bg-cyan-500",
    "bg-emerald-500",
    "bg-lime-500",
    "bg-amber-500",
    "bg-orange-500",
    "bg-rose-500",
    "bg-fuchsia-500",
];

export function ClassSettingsDialog({ open, onOpenChange, classData, rooms }: ClassSettingsDialogProps) {
    const [settingsState, setSettingsState] = useState<ClassSettings>({
        accent_color: classData.settings.accent_color ?? null,
        background_color: classData.settings.background_color ?? null,
        banner_image: classData.settings.banner_image ?? classData.settings.banner_image_url ?? null,
        enable_announcements: !!classData.settings.enable_announcements,
        enable_grade_visibility: !!classData.settings.enable_grade_visibility,
        enable_attendance_tracking: !!classData.settings.enable_attendance_tracking,
        enable_performance_analytics: !!classData.settings.enable_performance_analytics,
        allow_late_submissions: !!classData.settings.allow_late_submissions,
        enable_discussion_board: !!classData.settings.enable_discussion_board,
        start_date: classData.start_date ?? classData.settings.start_date ?? null,
    });

    const [detailsForm, setDetailsForm] = useState({
        room_id: String(classData.room_id || ""),
        faculty_id: String(classData.faculty_id || ""),
        maximum_slots: String(classData.maximum_slots || 40),
        semester: classData.semester || "",
        school_year: classData.school_year || "",
        section: classData.section || "",
        schedules: (classData.schedules || []).map((s: any) => ({
            day_of_week: s.day_of_week,
            start_time: s.start_time,
            end_time: s.end_time,
            room_id: s.room_id,
        })),
    });

    const [isSavingSettings, setIsSavingSettings] = useState(false);
    const [isSavingDetails, setIsSavingDetails] = useState(false);
    const [activeTab, setActiveTab] = useState("visuals");
    const [conflictMessage, setConflictMessage] = useState<string | null>(null);

    const handleSaveSettings = () => {
        setIsSavingSettings(true);
        router.put(`/faculty/classes/${classData.id}/settings`, settingsState as any, {
            preserveScroll: true,
            onSuccess: () => {
                toast.success("Class settings updated successfully.");
            },
            onError: (errors) => {
                console.error("Settings update errors:", errors);
                toast.error("Failed to update settings. Please check your inputs.");
            },
            onFinish: () => setIsSavingSettings(false),
        });
    };

    const handleSaveDetails = () => {
        setIsSavingDetails(true);
        setConflictMessage(null);

        router.put(`/faculty/classes/${classData.id}`, detailsForm, {
            preserveScroll: true,
            onSuccess: () => {
                toast.success("Class details updated successfully.");
            },
            onError: (errors: any) => {
                console.error("Details update errors:", errors);
                let message = "Please check your inputs.";

                if (errors.schedules) {
                    message = errors.schedules;
                    setConflictMessage(errors.schedules);
                } else {
                    const scheduleErrors = Object.keys(errors).filter((key) => key.startsWith("schedules"));
                    if (scheduleErrors.length > 0) {
                        message = errors[scheduleErrors[0]];
                        setConflictMessage(message);
                    }
                }

                toast.error("Failed to update class details", { description: message });
            },
            onFinish: () => setIsSavingDetails(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="flex max-h-[90vh] flex-col overflow-hidden p-0 lg:min-w-[700px]">
                <DialogHeader className="border-border/60 bg-card/80 border-b px-6 py-4">
                    <DialogTitle>Class Settings</DialogTitle>
                    <DialogDescription>
                        Manage {classData.subject_code} • Section {classData.section}
                    </DialogDescription>
                </DialogHeader>

                <Tabs value={activeTab} onValueChange={setActiveTab} className="flex flex-1 flex-col overflow-hidden">
                    <div className="border-border/40 bg-muted/20 border-b px-6 py-2">
                        <TabsList className="grid w-full grid-cols-2">
                            <TabsTrigger value="visuals" className="gap-2">
                                <IconPalette className="h-4 w-4" /> Visuals & Features
                            </TabsTrigger>
                            <TabsTrigger value="details" className="gap-2">
                                <IconSettings className="h-4 w-4" /> Class Details
                            </TabsTrigger>
                        </TabsList>
                    </div>

                    <div className="bg-background/50 flex-1 overflow-y-auto">
                        <TabsContent value="visuals" className="mt-0 space-y-6 p-6">
                            {/* Visuals content remains the same */}
                            <section className="border-border/60 bg-background/70 space-y-4 rounded-xl border p-4">
                                <div className="space-y-3">
                                    <Label className="text-muted-foreground flex items-center gap-2 text-xs tracking-[0.3em] uppercase">
                                        <IconPalette className="size-3.5" />
                                        Theme
                                    </Label>
                                    <div>
                                        <Label className="text-sm font-medium">Primary Color</Label>
                                        <div className="mt-2 flex flex-wrap gap-2">
                                            {colorSwatches.map((color) => {
                                                const isSelected = settingsState.background_color === color;
                                                return (
                                                    <button
                                                        key={color}
                                                        className={cn(
                                                            "size-8 rounded-full border-2 transition-all",
                                                            color,
                                                            isSelected
                                                                ? "border-foreground ring-foreground/20 scale-110 ring-2"
                                                                : "border-transparent hover:scale-105",
                                                        )}
                                                        onClick={() => setSettingsState({ ...settingsState, background_color: color })}
                                                    />
                                                );
                                            })}
                                        </div>
                                    </div>
                                    <div className="pt-3">
                                        <Label className="text-sm font-medium">Accent Color</Label>
                                        <div className="mt-2 flex flex-wrap gap-2">
                                            {colorSwatches.map((color) => {
                                                const isSelected = settingsState.accent_color === color;
                                                return (
                                                    <button
                                                        key={color}
                                                        className={cn(
                                                            "size-8 rounded-full border-2 transition-all",
                                                            color,
                                                            isSelected
                                                                ? "border-foreground ring-foreground/20 scale-110 ring-2"
                                                                : "border-transparent hover:scale-105",
                                                        )}
                                                        onClick={() => setSettingsState({ ...settingsState, accent_color: color })}
                                                    />
                                                );
                                            })}
                                        </div>
                                    </div>
                                </div>

                                <div className="border-border/40 space-y-3 border-t pt-3">
                                    <Label className="text-muted-foreground flex items-center gap-2 text-xs tracking-[0.3em] uppercase">
                                        <IconPhoto className="size-3.5" />
                                        Visuals
                                    </Label>
                                    <div className="space-y-2">
                                        <Label htmlFor="banner-url">Banner Image URL</Label>
                                        <Input
                                            id="banner-image"
                                            placeholder="https://..."
                                            value={settingsState.banner_image ?? ""}
                                            onChange={(e) => setSettingsState({ ...settingsState, banner_image: e.target.value })}
                                            className="bg-background/50"
                                        />
                                    </div>
                                </div>
                                <div className="border-border/40 space-y-3 border-t pt-3">
                                    <div className="space-y-2">
                                        <Label htmlFor="start-date">Start Date</Label>
                                        <Input
                                            id="start-date"
                                            type="date"
                                            value={settingsState.start_date ?? ""}
                                            onChange={(e) => setSettingsState({ ...settingsState, start_date: e.target.value })}
                                            className="bg-background/50"
                                            max={new Date(new Date().getFullYear() + 1, 11, 31).toISOString().split("T")[0]}
                                        />
                                    </div>
                                </div>
                            </section>

                            <section className="border-border/60 bg-background/70 space-y-4 rounded-xl border p-4">
                                <p className="text-muted-foreground text-xs tracking-[0.3em] uppercase">Features</p>
                                {[
                                    ["enable_announcements", "Announcements"],
                                    ["enable_grade_visibility", "Grade Visibility"],
                                    ["enable_attendance_tracking", "Attendance Tracking"],
                                    ["allow_late_submissions", "Late Submissions"],
                                    ["enable_discussion_board", "Discussion Board"],
                                    ["enable_performance_analytics", "Analytics"],
                                ].map(([key, label]) => (
                                    <div key={key} className="bg-card/60 flex items-center justify-between rounded-lg p-3">
                                        <span className="text-sm font-medium">{label}</span>
                                        <Switch
                                            checked={Boolean(settingsState[key as keyof ClassSettings])}
                                            onCheckedChange={(checked) => setSettingsState((prev) => ({ ...prev, [key]: checked }))}
                                        />
                                    </div>
                                ))}
                            </section>
                        </TabsContent>

                        <TabsContent value="details" className="mt-0 space-y-6 p-6">
                            <div className="space-y-4">
                                <div className="grid grid-cols-2 gap-4">
                                    <div className="space-y-2">
                                        <Label>Room Assignment</Label>
                                        <Input
                                            value={detailsForm.room_id}
                                            onChange={(e) => setDetailsForm({ ...detailsForm, room_id: e.target.value })}
                                            placeholder="Room ID"
                                            readOnly
                                        />
                                        <p className="text-muted-foreground text-[10px]">To change room, please use the main Classes list edit.</p>
                                    </div>
                                    <div className="space-y-2">
                                        <Label>Max Slots</Label>
                                        <Input
                                            type="number"
                                            value={detailsForm.maximum_slots}
                                            onChange={(e) => setDetailsForm({ ...detailsForm, maximum_slots: e.target.value })}
                                        />
                                    </div>
                                </div>
                                <div className="grid grid-cols-2 gap-4">
                                    <div className="space-y-2">
                                        <Label>Semester</Label>
                                        <Input value={detailsForm.semester} disabled className="bg-muted" />
                                    </div>
                                    <div className="space-y-2">
                                        <Label>School Year</Label>
                                        <Input value={detailsForm.school_year} disabled className="bg-muted" />
                                    </div>
                                </div>
                            </div>

                            {/* Schedules */}
                            <div className="bg-background/50 space-y-4 rounded-lg border p-4">
                                <div className="flex items-center justify-between">
                                    <h4 className="flex items-center gap-2 font-medium">
                                        <IconCalendar className="h-4 w-4" /> Class Schedules
                                    </h4>
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        onClick={() => {
                                            setDetailsForm((prev) => ({
                                                ...prev,
                                                schedules: [
                                                    ...prev.schedules,
                                                    {
                                                        day_of_week: "Monday",
                                                        start_time: "08:00",
                                                        end_time: "10:00",
                                                        room_id: prev.room_id,
                                                    },
                                                ],
                                            }));
                                        }}
                                    >
                                        <IconPlus className="mr-1 h-3 w-3" /> Add
                                    </Button>
                                </div>

                                {conflictMessage && (
                                    <div className="rounded-md border border-red-200 bg-red-50 p-3 dark:border-red-900/50 dark:bg-red-900/20">
                                        <div className="flex items-start gap-3">
                                            <IconAlertTriangle className="h-5 w-5 shrink-0 text-red-600 dark:text-red-400" />
                                            <p className="text-sm text-red-800 dark:text-red-400">{conflictMessage}</p>
                                        </div>
                                    </div>
                                )}

                                {detailsForm.schedules.length === 0 && (
                                    <p className="text-muted-foreground py-4 text-center text-sm">No schedules added.</p>
                                )}

                                {detailsForm.schedules.map((sched, idx) => (
                                    <div key={idx} className="grid grid-cols-12 items-end gap-2 border-b pb-4 last:border-0 last:pb-0">
                                        <div className="col-span-3 space-y-1">
                                            <Label className="text-xs">Day</Label>
                                            <Select
                                                value={sched.day_of_week}
                                                onValueChange={(val) => {
                                                    const newScheds = [...detailsForm.schedules];
                                                    newScheds[idx].day_of_week = val;
                                                    setDetailsForm((prev) => ({ ...prev, schedules: newScheds }));
                                                }}
                                            >
                                                <SelectTrigger className="h-8 text-xs">
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday"].map((day) => (
                                                        <SelectItem key={day} value={day}>
                                                            {day}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        </div>
                                        <div className="col-span-3 space-y-1">
                                            <Label className="text-xs">Start</Label>
                                            <Input
                                                type="time"
                                                className="h-8 text-xs"
                                                value={sched.start_time ? sched.start_time.substring(0, 5) : ""}
                                                onChange={(e) => {
                                                    const newScheds = [...detailsForm.schedules];
                                                    newScheds[idx].start_time = e.target.value;
                                                    setDetailsForm((prev) => ({ ...prev, schedules: newScheds }));
                                                }}
                                            />
                                        </div>
                                        <div className="col-span-3 space-y-1">
                                            <Label className="text-xs">End</Label>
                                            <Input
                                                type="time"
                                                className="h-8 text-xs"
                                                value={sched.end_time ? sched.end_time.substring(0, 5) : ""}
                                                onChange={(e) => {
                                                    const newScheds = [...detailsForm.schedules];
                                                    newScheds[idx].end_time = e.target.value;
                                                    setDetailsForm((prev) => ({ ...prev, schedules: newScheds }));
                                                }}
                                            />
                                        </div>
                                        <div className="col-span-2 space-y-1">
                                            <Label className="text-xs">Room</Label>
                                            <Select
                                                value={String(sched.room_id)}
                                                onValueChange={(val) => {
                                                    const newScheds = [...detailsForm.schedules];
                                                    newScheds[idx].room_id = val;
                                                    setDetailsForm((prev) => ({ ...prev, schedules: newScheds }));
                                                }}
                                            >
                                                <SelectTrigger className="h-8 text-xs">
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {(rooms ?? []).map((room) => (
                                                        <SelectItem key={room.id} value={String(room.id)}>
                                                            {room.name}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        </div>
                                        <div className="col-span-1">
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                className="text-destructive hover:bg-destructive/10 h-8 w-8"
                                                onClick={() => {
                                                    setDetailsForm((prev) => ({
                                                        ...prev,
                                                        schedules: prev.schedules.filter((_, i) => i !== idx),
                                                    }));
                                                }}
                                            >
                                                <span className="sr-only">Delete</span>
                                                <IconPlus className="h-4 w-4 rotate-45" />
                                            </Button>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </TabsContent>
                    </div>
                </Tabs>

                <DialogFooter className="border-border/60 bg-card/90 border-t px-6 py-4">
                    <Button onClick={() => onOpenChange(false)} variant="outline" className="rounded-full">
                        Cancel
                    </Button>
                    <Button
                        onClick={activeTab === "visuals" ? handleSaveSettings : handleSaveDetails}
                        className="rounded-full"
                        disabled={isSavingSettings || isSavingDetails}
                    >
                        {isSavingSettings || isSavingDetails ? "Saving..." : `Save ${activeTab === "visuals" ? "Settings" : "Details"}`}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
