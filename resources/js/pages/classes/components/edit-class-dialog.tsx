import type { ClassData } from "@/components/data-table";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { router } from "@inertiajs/react";
import { IconAlertTriangle, IconCalendar, IconPlus } from "@tabler/icons-react";
import { useEffect, useMemo, useState } from "react";
import { toast } from "sonner";

interface EditClassDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    classItem: ClassData | null;
    shs_strands: any[];
    rooms: { id: number; name: string }[];
    onSuccess?: () => void;
}

type EditSchedule = {
    day_of_week: string;
    start_time: string;
    end_time: string;
    room_id: string | number | null;
};

type EditClassForm = {
    room_id: string;
    faculty_id: string;
    maximum_slots: string;
    semester: string;
    school_year: string;
    section: string;
    classification: string;
    strand_id: string;
    track_id: string;
    subject_code: string;
    schedules: EditSchedule[];
};

export function EditClassDialog({ open, onOpenChange, classItem, shs_strands, rooms, onSuccess }: EditClassDialogProps) {
    const [editForm, setEditForm] = useState<EditClassForm>({
        room_id: "",
        faculty_id: "",
        maximum_slots: "",
        semester: "",
        school_year: "",
        section: "",
        classification: "college",
        strand_id: "",
        track_id: "",
        subject_code: "",
        schedules: [],
    });
    const [conflictMessage, setConflictMessage] = useState<string | null>(null);
    const [strandSubjects, setStrandSubjects] = useState<any[]>([]);

    const shsTrackOptions = useMemo(() => {
        const map = new Map<string, string>();
        for (const strand of shs_strands ?? []) {
            if (strand.track_id && strand.track_name) {
                map.set(String(strand.track_id), strand.track_name);
            }
        }
        return Array.from(map.entries()).map(([id, name]) => ({ id, name }));
    }, [shs_strands]);

    useEffect(() => {
        if (open && classItem) {
            const strandId = classItem.strand_id !== undefined && classItem.strand_id !== null ? String(classItem.strand_id) : "";

            const rawSchedules = Array.isArray(classItem.schedules) ? (classItem.schedules as unknown[]) : [];

            const normalizedSchedules: EditSchedule[] = rawSchedules.map((schedule) => {
                const raw = schedule as Record<string, unknown>;
                const roomIdValue = raw.room_id;
                const roomId = typeof roomIdValue === "string" || typeof roomIdValue === "number" ? roomIdValue : null;

                return {
                    day_of_week: String(raw.day_of_week ?? "Monday"),
                    start_time: String(raw.start_time ?? "08:00"),
                    end_time: String(raw.end_time ?? "10:00"),
                    room_id: roomId ?? (classItem.room_id !== undefined && classItem.room_id !== null ? classItem.room_id : null),
                };
            });

            const initialForm = {
                room_id: classItem.room_id !== undefined && classItem.room_id !== null ? String(classItem.room_id) : "",
                faculty_id: classItem.faculty_id !== undefined && classItem.faculty_id !== null ? String(classItem.faculty_id) : "",
                maximum_slots: String(classItem.maximum_slots || 40),
                semester: classItem.semester || "",
                school_year: classItem.school_year || "",
                section: classItem.section,
                classification: classItem.classification || "college",
                strand_id: strandId,
                track_id: "",
                subject_code: classItem.subject_code,
                schedules: normalizedSchedules,
            };

            if (strandId) {
                const strand = shs_strands.find((s) => String(s.id) === strandId);
                if (strand?.track_id) {
                    initialForm.track_id = String(strand.track_id);
                }
            }

            setEditForm(initialForm);
            setConflictMessage(null);
        }
    }, [open, classItem, shs_strands]);

    useEffect(() => {
        if (open && editForm.classification === "shs" && editForm.strand_id) {
            fetch(`/classes/strand-subjects?strand_id=${editForm.strand_id}`)
                .then((res) => res.json())
                .then((data) => {
                    setStrandSubjects(data.strand_subjects || []);
                })
                .catch((err) => console.error("Failed to fetch strand subjects", err));
        }
    }, [editForm.strand_id, editForm.classification, open]);

    function handleUpdateClass() {
        if (!classItem) return;

        router.put(`/classes/${classItem.id}`, editForm, {
            onSuccess: () => {
                onOpenChange(false);
                toast.success("Class updated successfully");
                onSuccess?.();
            },
            onError: (errors: any) => {
                console.error("Update failed", errors);
                let message = "Please check your inputs.";

                if (errors.schedules) {
                    message = errors.schedules;
                    setConflictMessage(errors.schedules);
                } else {
                    // Check for nested schedule errors (e.g., schedules.0.start_time)
                    const scheduleErrors = Object.keys(errors).filter((key) => key.startsWith("schedules"));
                    if (scheduleErrors.length > 0) {
                        message = errors[scheduleErrors[0]];
                        setConflictMessage(message);
                    }
                }

                toast.error("Class Update Failed", {
                    description: message,
                    duration: 5000,
                });
            },
        });
    }

    if (!classItem) return null;

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90vh] overflow-y-auto lg:max-w-[1200px]">
                <DialogHeader>
                    <DialogTitle>Edit Class Details</DialogTitle>
                    <DialogDescription>
                        Update information for {classItem.subject_code} - {classItem.section}
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-6 py-4">
                    <div className="flex items-center gap-2">
                        <Badge variant={editForm.classification === "shs" ? "default" : "secondary"}>
                            {editForm.classification === "shs" ? "Senior High School" : "College"}
                        </Badge>
                    </div>

                    {editForm.classification === "shs" && (
                        <div className="grid grid-cols-2 gap-4">
                            <div className="space-y-2">
                                <Label>SHS Track</Label>
                                <Select
                                    value={editForm.track_id}
                                    onValueChange={(val) => {
                                        setEditForm((prev) => ({ ...prev, track_id: val, strand_id: "" }));
                                    }}
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select Track" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {shsTrackOptions.length ? (
                                            shsTrackOptions.map((track) => (
                                                <SelectItem key={track.id} value={track.id}>
                                                    {track.name}
                                                </SelectItem>
                                            ))
                                        ) : (
                                            <SelectItem value="__none__" disabled>
                                                No tracks available
                                            </SelectItem>
                                        )}
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="space-y-2">
                                <Label>SHS Strand</Label>
                                <Select
                                    value={editForm.strand_id}
                                    onValueChange={(val) => setEditForm((prev) => ({ ...prev, strand_id: val }))}
                                    disabled={!editForm.track_id}
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select Strand" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {shs_strands
                                            .filter((s) => !editForm.track_id || String(s.track_id) === editForm.track_id)
                                            .map((strand) => (
                                                <SelectItem key={strand.id} value={String(strand.id)}>
                                                    {strand.strand_name}
                                                </SelectItem>
                                            ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="space-y-2">
                                <Label>Subject (Code - Title)</Label>
                                <Select
                                    value={editForm.subject_code}
                                    onValueChange={(value) => setEditForm({ ...editForm, subject_code: value })}
                                    disabled={!editForm.strand_id}
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select Subject" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {strandSubjects.map((subject) => (
                                            <SelectItem key={subject.code} value={subject.code}>
                                                {subject.code} - {subject.title}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="space-y-2">
                                <Label>Subject Code (Manual Override)</Label>
                                <Input
                                    value={editForm.subject_code}
                                    onChange={(e) => setEditForm((prev) => ({ ...prev, subject_code: e.target.value }))}
                                    placeholder="Enter subject code"
                                />
                            </div>
                        </div>
                    )}

                    <div className="grid grid-cols-3 gap-4">
                        <div className="space-y-2">
                            <Label>Section</Label>
                            <Select value={editForm.section} onValueChange={(value) => setEditForm({ ...editForm, section: value })}>
                                <SelectTrigger>
                                    <SelectValue placeholder="Select Section" />
                                </SelectTrigger>
                                <SelectContent>
                                    {["A", "B", "C", "D", "E", "F"].map((s) => (
                                        <SelectItem key={s} value={s}>
                                            Section {s}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="space-y-2">
                            <Label>Semester</Label>
                            <Select value={editForm.semester} onValueChange={(value) => setEditForm({ ...editForm, semester: value })}>
                                <SelectTrigger>
                                    <SelectValue placeholder="Select Semester" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="1">1st Semester</SelectItem>
                                    <SelectItem value="2">2nd Semester</SelectItem>
                                    <SelectItem value="summer">Summer</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="space-y-2">
                            <Label>School Year</Label>
                            <Input
                                value={editForm.school_year}
                                onChange={(e) => setEditForm({ ...editForm, school_year: e.target.value })}
                                placeholder="e.g. 2023-2024"
                            />
                        </div>
                    </div>

                    <div className="space-y-4 rounded-lg border p-4">
                        <div className="flex items-center justify-between">
                            <h4 className="flex items-center gap-2 font-medium">
                                <IconCalendar className="h-4 w-4" /> Class Schedules
                            </h4>
                            <Button
                                size="sm"
                                variant="outline"
                                onClick={() => {
                                    setEditForm((prev) => ({
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
                                <IconPlus className="mr-1 h-3 w-3" /> Add Schedule
                            </Button>
                        </div>

                        {conflictMessage && (
                            <div className="rounded-md border border-red-200 bg-red-50 p-4 dark:border-red-900/50 dark:bg-red-900/20">
                                <div className="flex items-start gap-3">
                                    <div className="rounded-full bg-red-100 p-1 dark:bg-red-900/40">
                                        <IconAlertTriangle className="h-5 w-5 text-red-600 dark:text-red-400" />
                                    </div>
                                    <div className="flex-1">
                                        <h5 className="mb-1 font-medium text-red-900 dark:text-red-300">Schedule Conflict Detected</h5>
                                        <p className="text-sm text-red-800 dark:text-red-400">{conflictMessage}</p>
                                    </div>
                                </div>
                            </div>
                        )}

                        {editForm.schedules.length === 0 && <p className="text-muted-foreground py-4 text-center text-sm">No schedules added.</p>}

                        {editForm.schedules.map((sched, idx) => (
                            <div key={idx} className="grid grid-cols-12 items-end gap-2 border-b pb-4 last:border-0 last:pb-0">
                                <div className="col-span-3 space-y-1">
                                    <Label className="text-xs">Day</Label>
                                    <Select
                                        value={sched.day_of_week}
                                        onValueChange={(val) => {
                                            const newScheds = [...editForm.schedules];
                                            newScheds[idx].day_of_week = val;
                                            setEditForm((prev) => ({ ...prev, schedules: newScheds }));
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
                                            const newScheds = [...editForm.schedules];
                                            newScheds[idx].start_time = e.target.value;
                                            setEditForm((prev) => ({ ...prev, schedules: newScheds }));
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
                                            const newScheds = [...editForm.schedules];
                                            newScheds[idx].end_time = e.target.value;
                                            setEditForm((prev) => ({ ...prev, schedules: newScheds }));
                                        }}
                                    />
                                </div>
                                <div className="col-span-2 space-y-1">
                                    <Label className="text-xs">Room</Label>
                                    <Select
                                        value={String(sched.room_id)}
                                        onValueChange={(val) => {
                                            const newScheds = [...editForm.schedules];
                                            newScheds[idx].room_id = val;
                                            setEditForm((prev) => ({ ...prev, schedules: newScheds }));
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
                                            setEditForm((prev) => ({
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
                </div>

                <DialogFooter>
                    <Button variant="outline" onClick={() => onOpenChange(false)}>
                        Cancel
                    </Button>
                    <Button onClick={handleUpdateClass}>Save Changes</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
