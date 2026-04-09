import { CreateSessionDialog } from "@/components/class/create-session-dialog";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Calendar, CalendarDayButton } from "@/components/ui/calendar";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from "@/components/ui/dropdown-menu";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from "@/components/ui/sheet";
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from "@/components/ui/tooltip";
import { cn } from "@/lib/utils";
import { AttendanceOverview, AttendanceRecordEntry, AttendanceSessionEntry, AttendanceStatus, ScheduleEntry } from "@/types/class-detail-types";
import { DndContext, useDraggable, useDroppable, type DragEndEvent } from "@dnd-kit/core";
import { CSS } from "@dnd-kit/utilities";
import { router } from "@inertiajs/react";
import {
    IconBolt,
    IconCalendar,
    IconDeviceFloppy,
    IconDotsVertical,
    IconDownload,
    IconEdit,
    IconFileSpreadsheet,
    IconFileTypePdf,
    IconGripVertical,
    IconPlus,
    IconTrash,
    IconUsers,
    IconX,
} from "@tabler/icons-react";
import { useEffect, useMemo, useState, type ComponentProps } from "react";
import { toast } from "sonner";

interface ScheduleOption {
    id: string;
    label: string;
    day: string;
    room?: string | null;
    raw: ScheduleEntry;
}

interface AttendanceTabProps {
    classData: {
        id: number;
    };
    attendance: AttendanceOverview;
    scheduleOptions: ScheduleOption[];
    classSchedules: Array<{
        id: number;
        day_of_week: string;
        start_time: string;
        end_time: string;
        room_id: number;
    }>;
    rooms: { id: number; name: string }[];
    defaultSessionDate: string;
    classStartDate: Date | null;
    focusStudentId: number | null;
    onClearFocus: () => void;
}

const attendanceStatusMeta: Record<AttendanceStatus, { label: string; description: string; badgeClass: string; dotClass: string }> = {
    present: {
        label: "Present",
        description: "Arrived on time",
        badgeClass: "bg-emerald-500/15 text-emerald-600",
        dotClass: "bg-emerald-500",
    },
    late: {
        label: "Late",
        description: "Arrived after bell",
        badgeClass: "bg-amber-500/15 text-amber-600",
        dotClass: "bg-amber-500",
    },
    absent: {
        label: "Absent",
        description: "Did not attend",
        badgeClass: "bg-rose-500/15 text-rose-600",
        dotClass: "bg-rose-500",
    },
    excused: {
        label: "Excused",
        description: "Excused absence",
        badgeClass: "bg-indigo-500/15 text-indigo-600",
        dotClass: "bg-indigo-500",
    },
};

export function AttendanceTab({
    classData,
    attendance,
    scheduleOptions,
    classSchedules,
    rooms,
    defaultSessionDate,
    classStartDate,
    focusStudentId,
    onClearFocus,
}: AttendanceTabProps) {
    const [selectedAttendanceSessionId, setSelectedAttendanceSessionId] = useState<number | null>(attendance.sessions[0]?.id ?? null);
    const [recordRemarksDrafts, setRecordRemarksDrafts] = useState<Record<number, string>>({});
    const [isRosterSheetOpen, setIsRosterSheetOpen] = useState(false);
    const [sessionPendingDelete, setSessionPendingDelete] = useState<AttendanceSessionEntry | null>(null);

    const [studentFilter, setStudentFilter] = useState("");
    const [keyboardFocusEnrollmentId, setKeyboardFocusEnrollmentId] = useState<number | null>(null);
    const [pendingOpenSessionDate, setPendingOpenSessionDate] = useState<string | null>(null);

    // Effect to handle external focus request
    useEffect(() => {
        if (focusStudentId) {
            if (!attendance.sessions.length) {
                toast.info("Create a session first to track attendance");
                onClearFocus();
                return;
            }

            if (selectedAttendanceSessionId === null) {
                setSelectedAttendanceSessionId(attendance.sessions[0].id);
            }

            setIsRosterSheetOpen(true);
        }
    }, [focusStudentId, attendance.sessions, selectedAttendanceSessionId, onClearFocus]);

    useEffect(() => {
        const firstSessionId = attendance.sessions[0]?.id ?? null;
        if (selectedAttendanceSessionId === null && firstSessionId !== null) {
            setSelectedAttendanceSessionId(firstSessionId);
            return;
        }

        const isCurrentSessionMissing =
            selectedAttendanceSessionId !== null && !attendance.sessions.some((session) => session.id === selectedAttendanceSessionId);
        if (isCurrentSessionMissing) {
            setSelectedAttendanceSessionId(firstSessionId);
        }
    }, [attendance.sessions, selectedAttendanceSessionId]);

    useEffect(() => {
        setRecordRemarksDrafts({});
    }, [selectedAttendanceSessionId]);

    // Auto-scroll to focused student when sheet opens
    useEffect(() => {
        if (isRosterSheetOpen && focusStudentId) {
            // Small timeout to allow the sheet to render
            setTimeout(() => {
                const element = document.getElementById(`attendance-row-${focusStudentId}`);
                if (element) {
                    element.scrollIntoView({ behavior: "smooth", block: "center" });
                    // Optional: Add a temporary flash effect
                    element.classList.add("bg-primary/10");
                    setTimeout(() => element.classList.remove("bg-primary/10"), 2000);
                }
            }, 300);
        }
    }, [isRosterSheetOpen, focusStudentId]);

    const selectedAttendanceSession = useMemo(() => {
        if (selectedAttendanceSessionId === null) {
            return null;
        }

        return attendance.sessions.find((session) => session.id === selectedAttendanceSessionId) ?? null;
    }, [attendance.sessions, selectedAttendanceSessionId]);

    const filteredRosterRecords = useMemo(() => {
        if (!selectedAttendanceSession || selectedAttendanceSession.is_no_meeting) {
            return [] as AttendanceRecordEntry[];
        }

        const query = studentFilter.trim().toLowerCase();

        return selectedAttendanceSession.records
            .slice()
            .sort((a, b) => a.student.name.localeCompare(b.student.name))
            .filter((record) => {
                if (!query) {
                    return true;
                }

                return record.student.name.toLowerCase().includes(query) || record.student.student_number.toLowerCase().includes(query);
            });
    }, [selectedAttendanceSession, studentFilter]);

    useEffect(() => {
        if (!pendingOpenSessionDate) {
            return;
        }

        const matchingSession = attendance.sessions.find((session) => session.session_date === pendingOpenSessionDate);

        if (matchingSession) {
            openRosterWorkspace(matchingSession.id);
            setPendingOpenSessionDate(null);
        }
    }, [attendance.sessions, pendingOpenSessionDate]);

    useEffect(() => {
        if (!isRosterSheetOpen) {
            return;
        }

        if (!selectedAttendanceSession || selectedAttendanceSession.is_no_meeting || filteredRosterRecords.length === 0) {
            setKeyboardFocusEnrollmentId(null);
            return;
        }

        const preferred = focusStudentId ?? keyboardFocusEnrollmentId;

        if (preferred && filteredRosterRecords.some((record) => record.class_enrollment_id === preferred)) {
            setKeyboardFocusEnrollmentId(preferred);
            return;
        }

        setKeyboardFocusEnrollmentId(filteredRosterRecords[0].class_enrollment_id);
    }, [filteredRosterRecords, focusStudentId, isRosterSheetOpen, selectedAttendanceSession]);

    useEffect(() => {
        if (!isRosterSheetOpen) {
            return;
        }

        const handler = (event: KeyboardEvent) => {
            if (!selectedAttendanceSession || selectedAttendanceSession.is_locked || selectedAttendanceSession.is_no_meeting) {
                return;
            }

            if (filteredRosterRecords.length === 0) {
                return;
            }

            const target = event.target as HTMLElement | null;
            const tag = target?.tagName?.toLowerCase();

            if (tag === "input" || tag === "textarea" || tag === "select" || target?.isContentEditable) {
                return;
            }

            if (!keyboardFocusEnrollmentId) {
                setKeyboardFocusEnrollmentId(filteredRosterRecords[0].class_enrollment_id);
                return;
            }

            const currentIndex = filteredRosterRecords.findIndex((record) => record.class_enrollment_id === keyboardFocusEnrollmentId);

            const clamp = (value: number) => Math.min(Math.max(value, 0), filteredRosterRecords.length - 1);

            if (event.key === "ArrowDown" || event.key === "j") {
                event.preventDefault();
                const next = filteredRosterRecords[clamp(currentIndex + 1)];
                setKeyboardFocusEnrollmentId(next.class_enrollment_id);
                document.getElementById(`attendance-row-${next.class_enrollment_id}`)?.scrollIntoView({
                    behavior: "smooth",
                    block: "center",
                });
                return;
            }

            if (event.key === "ArrowUp" || event.key === "k") {
                event.preventDefault();
                const next = filteredRosterRecords[clamp(currentIndex - 1)];
                setKeyboardFocusEnrollmentId(next.class_enrollment_id);
                document.getElementById(`attendance-row-${next.class_enrollment_id}`)?.scrollIntoView({
                    behavior: "smooth",
                    block: "center",
                });
                return;
            }

            if (event.key === "Escape") {
                setIsRosterSheetOpen(false);
                onClearFocus();
                return;
            }

            const current = filteredRosterRecords[currentIndex];
            if (!current) {
                return;
            }

            const key = event.key.toLowerCase();

            const mappedStatus: AttendanceStatus | null =
                key === "1" || key === "p"
                    ? "present"
                    : key === "2" || key === "l"
                      ? "late"
                      : key === "3" || key === "a"
                        ? "absent"
                        : key === "4" || key === "e"
                          ? "excused"
                          : null;

            if (mappedStatus) {
                event.preventDefault();
                handleUpdateAttendanceRecord(current, mappedStatus, current.remarks ?? null);
            }
        };

        window.addEventListener("keydown", handler);

        return () => {
            window.removeEventListener("keydown", handler);
        };
    }, [filteredRosterRecords, isRosterSheetOpen, keyboardFocusEnrollmentId, selectedAttendanceSession]);

    const attendanceSummary = attendance.summary ?? {
        by_status: {
            present: 0,
            late: 0,
            absent: 0,
            excused: 0,
        },
        total_sessions: 0,
        last_taken_at: null,
    };

    const handleUpdateAttendanceRecord = (record: AttendanceRecordEntry, status: AttendanceStatus, remarks?: string | null) => {
        if (selectedAttendanceSession === null) {
            return;
        }

        const sanitizedRemarks = typeof remarks === "string" ? remarks : (record.remarks ?? null);
        const hasStatusChanged = record.status !== status;
        const hasRemarksChanged = sanitizedRemarks !== (record.remarks ?? null);

        if (!hasStatusChanged && !hasRemarksChanged) {
            return;
        }

        router.post(
            `/faculty/classes/${classData.id}/attendance/sessions/${selectedAttendanceSession.id}/records`,
            {
                records: [
                    {
                        class_enrollment_id: record.class_enrollment_id,
                        status,
                        remarks: sanitizedRemarks,
                    },
                ],
            },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    setRecordRemarksDrafts((previous) => {
                        const next = { ...previous };
                        delete next[record.id];
                        return next;
                    });
                    toast.success(`Updated ${record.student.name} to ${attendanceStatusMeta[status].label}`);
                },
                onError: () => {
                    toast.error("Unable to update attendance");
                },
            },
        );
    };

    const openRosterWorkspace = (sessionId: number) => {
        setSelectedAttendanceSessionId(sessionId);
        setIsRosterSheetOpen(true);
    };

    const handleBulkUpdate = (status: AttendanceStatus) => {
        if (!selectedAttendanceSession || selectedAttendanceSession.is_locked || selectedAttendanceSession.is_no_meeting) {
            return;
        }

        const recordsToUpdate = filteredRosterRecords
            .filter((record) => record.status !== status)
            .map((record) => ({
                class_enrollment_id: record.class_enrollment_id,
                status,
                remarks: record.remarks ?? null,
            }));

        if (recordsToUpdate.length === 0) {
            toast.info("Nothing to update");
            return;
        }

        router.post(
            `/faculty/classes/${classData.id}/attendance/sessions/${selectedAttendanceSession.id}/records`,
            {
                records: recordsToUpdate,
            },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    toast.success(`Updated ${recordsToUpdate.length} student(s) to ${attendanceStatusMeta[status].label}`);
                },
                onError: () => {
                    toast.error("Unable to update attendance");
                },
            },
        );
    };

    const handleTodaySession = () => {
        const existing = attendance.sessions.find((session) => session.session_date === defaultSessionDate);

        if (existing) {
            openRosterWorkspace(existing.id);
            return;
        }

        const todayName = new Date().toLocaleDateString("en-US", { weekday: "long" });
        const scheduleForToday = scheduleOptions.find((option) => option.day === todayName);

        if (!scheduleForToday) {
            toast.error(`No schedule for ${todayName}`);
            return;
        }

        setPendingOpenSessionDate(defaultSessionDate);

        router.post(
            `/faculty/classes/${classData.id}/attendance/sessions`,
            {
                session_date: defaultSessionDate,
                schedule_id: Number(scheduleForToday.id),
                topic: "Today's session",
                default_status: "present",
                mark_all: true,
            },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    toast.success("Today's session created");
                },
                onError: (errors: Record<string, string>) => {
                    const firstError = Object.values(errors)[0];
                    toast.error("Unable to create today's session", {
                        description: typeof firstError === "string" ? firstError : undefined,
                    });
                    setPendingOpenSessionDate(null);
                },
            },
        );
    };

    const handleDeleteAttendanceSession = (sessionId: number) => {
        router.delete(`/faculty/classes/${classData.id}/attendance/sessions/${sessionId}`, {
            preserveScroll: true,
            onSuccess: () => {
                toast.success("Attendance session removed");
                if (selectedAttendanceSessionId === sessionId) {
                    setSelectedAttendanceSessionId(null);
                    setIsRosterSheetOpen(false);
                }
            },
            onError: () => {
                toast.error("Unable to delete attendance session");
            },
        });
    };

    const handleToggleSessionLock = (session: AttendanceSessionEntry) => {
        router.put(
            `/faculty/classes/${classData.id}/attendance/sessions/${session.id}`,
            {
                lock_session: !session.is_locked,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success(`Session ${session.is_locked ? "unlocked" : "locked"}`);
                },
                onError: () => {
                    toast.error("Unable to update session lock state");
                },
            },
        );
    };

    // Calendar & View Mode Logic
    const [viewMode, setViewMode] = useState<"list" | "calendar">("list");
    const [calendarDialogOpen, setCalendarDialogOpen] = useState(false);
    const [calendarDialogDate, setCalendarDialogDate] = useState<string | null>(null);

    const toLocalYmd = (date: Date): string => {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, "0");
        const day = String(date.getDate()).padStart(2, "0");

        return `${year}-${month}-${day}`;
    };

    const fromYmd = (value: string): Date => {
        const [year, month, day] = value.split("-").map(Number);

        return new Date(year, (month ?? 1) - 1, day ?? 1);
    };

    const todayYmd = useMemo(() => toLocalYmd(new Date()), []);

    const weekdays = useMemo(() => ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday"], []);

    type EditableSchedule = {
        clientId: string;
        id?: number;
        day_of_week: string;
        start_time: string;
        end_time: string;
        room_id: number;
    };

    const newClientId = (): string => {
        return `new:${Date.now()}:${Math.random().toString(16).slice(2)}`;
    };

    const normalizeSchedules = (input: AttendanceTabProps["classSchedules"]): EditableSchedule[] => {
        return input.map((schedule) => ({
            clientId: `schedule:${schedule.id}`,
            id: schedule.id,
            day_of_week: schedule.day_of_week,
            start_time: (schedule.start_time ?? "").slice(0, 5),
            end_time: (schedule.end_time ?? "").slice(0, 5),
            room_id: schedule.room_id,
        }));
    };

    const [scheduleEditorOpen, setScheduleEditorOpen] = useState(false);
    const [scheduleDraft, setScheduleDraft] = useState<EditableSchedule[]>(() => normalizeSchedules(classSchedules));
    const [scheduleEditTargetId, setScheduleEditTargetId] = useState<string | null>(null);
    const [isSavingSchedules, setIsSavingSchedules] = useState(false);

    useEffect(() => {
        if (scheduleEditorOpen) {
            return;
        }

        setScheduleDraft(normalizeSchedules(classSchedules));
    }, [classSchedules, scheduleEditorOpen]);

    const scheduleFingerprint = (schedules: EditableSchedule[]): string => {
        return JSON.stringify(
            schedules
                .map((schedule) => ({
                    id: schedule.id ?? null,
                    day_of_week: schedule.day_of_week,
                    start_time: schedule.start_time,
                    end_time: schedule.end_time,
                    room_id: schedule.room_id,
                }))
                .sort((a, b) => {
                    const idA = a.id ?? 0;
                    const idB = b.id ?? 0;

                    if (idA !== idB) {
                        return idA - idB;
                    }

                    return `${a.day_of_week}${a.start_time}${a.end_time}${a.room_id}`.localeCompare(
                        `${b.day_of_week}${b.start_time}${b.end_time}${b.room_id}`,
                    );
                }),
        );
    };

    const schedulesDirty = useMemo(() => {
        return scheduleFingerprint(scheduleDraft) !== scheduleFingerprint(normalizeSchedules(classSchedules));
    }, [scheduleDraft, classSchedules]);

    const formatTime = (value: string): string => {
        if (!value) {
            return "—";
        }

        const [hours, minutes] = value.split(":").map(Number);
        const date = new Date(2000, 0, 1, hours ?? 0, minutes ?? 0);

        return date.toLocaleTimeString(undefined, { hour: "numeric", minute: "2-digit" });
    };

    const handleScheduleDragEnd = (event: DragEndEvent) => {
        const activeId = String(event.active.id);
        const overId = event.over ? String(event.over.id) : null;

        if (!overId || !overId.startsWith("day:")) {
            return;
        }

        const nextDay = overId.replace("day:", "");

        setScheduleDraft((previous) =>
            previous.map((schedule) => (schedule.clientId === activeId ? { ...schedule, day_of_week: nextDay } : schedule)),
        );
    };

    const handleSaveSchedules = () => {
        setIsSavingSchedules(true);

        router.put(
            `/faculty/classes/${classData.id}/schedules`,
            {
                schedules: scheduleDraft.map((schedule) => ({
                    id: schedule.id,
                    day_of_week: schedule.day_of_week,
                    start_time: schedule.start_time,
                    end_time: schedule.end_time,
                    room_id: schedule.room_id,
                })),
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success("Schedules updated");
                    setScheduleEditorOpen(false);
                },
                onError: (errors: Record<string, string>) => {
                    const message = errors.schedules ?? Object.values(errors)[0];
                    toast.error("Unable to update schedules", {
                        description: typeof message === "string" ? message : undefined,
                    });
                },
                onFinish: () => setIsSavingSchedules(false),
            },
        );
    };

    const handleAddSchedule = () => {
        const defaultRoomId = rooms[0]?.id;

        if (!defaultRoomId) {
            toast.error("No rooms available");
            return;
        }

        setScheduleDraft((previous) => [
            ...previous,
            {
                clientId: newClientId(),
                day_of_week: "Monday",
                start_time: "08:00",
                end_time: "09:00",
                room_id: defaultRoomId,
            },
        ]);
    };

    const roomsById = useMemo(() => {
        return new Map(rooms.map((room) => [room.id, room.name]));
    }, [rooms]);

    const updateScheduleDraft = (clientId: string, patch: Partial<EditableSchedule>) => {
        setScheduleDraft((previous) => previous.map((schedule) => (schedule.clientId === clientId ? { ...schedule, ...patch } : schedule)));
    };

    const removeScheduleDraft = (clientId: string) => {
        setScheduleDraft((previous) => previous.filter((schedule) => schedule.clientId !== clientId));

        if (scheduleEditTargetId === clientId) {
            setScheduleEditTargetId(null);
        }
    };

    const scheduleToEdit = useMemo(() => {
        if (!scheduleEditTargetId) {
            return null;
        }

        return scheduleDraft.find((schedule) => schedule.clientId === scheduleEditTargetId) ?? null;
    }, [scheduleDraft, scheduleEditTargetId]);

    const calendarEvents = useMemo(() => {
        return (attendance.calendar_events ?? []).reduce(
            (acc, event) => {
                acc[event.date] = event;
                return acc;
            },
            {} as Record<string, import("@/types/class-detail-types").CalendarEvent>,
        );
    }, [attendance.calendar_events]);

    const handleCalendarDayClick = (date: Date, event: import("@/types/class-detail-types").CalendarEvent | undefined) => {
        const dateStr = toLocalYmd(date);
        if (event?.type === "missing") {
            setCalendarDialogDate(dateStr);
            setCalendarDialogOpen(true);
        } else if (event?.session_id) {
            openRosterWorkspace(event.session_id);
        }
    };

    return (
        <div className="space-y-6">
            <Card className="border-border/60 bg-card text-card-foreground">
                <CardHeader className="flex flex-col gap-3 space-y-0 sm:flex-row sm:items-center sm:justify-between">
                    <div className="space-y-1">
                        <CardTitle className="text-base">Schedule planner</CardTitle>
                        <p className="text-muted-foreground text-sm">Drag a schedule card to another day, then save.</p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => {
                                if (scheduleEditorOpen) {
                                    setScheduleEditorOpen(false);
                                    setScheduleEditTargetId(null);
                                    setScheduleDraft(normalizeSchedules(classSchedules));
                                    return;
                                }
                                setScheduleEditorOpen(true);
                            }}
                        >
                            <IconEdit className="mr-2 size-4" />
                            {scheduleEditorOpen ? "Cancel edit" : "Edit schedule"}
                        </Button>
                        <Button size="sm" onClick={handleSaveSchedules} disabled={!scheduleEditorOpen || !schedulesDirty || isSavingSchedules}>
                            <IconDeviceFloppy className="mr-2 size-4" />
                            Save changes
                        </Button>
                    </div>
                </CardHeader>
                <CardContent className="space-y-3">
                    {!scheduleEditorOpen ? (
                        <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                            {scheduleOptions.length ? (
                                scheduleOptions.map((option) => (
                                    <div key={option.id} className="border-border/60 bg-muted/10 rounded-xl border px-3 py-2 text-sm">
                                        <div className="flex items-center justify-between gap-3">
                                            <span className="text-foreground font-medium">{option.day}</span>
                                            <span className="text-muted-foreground text-xs">{option.room ?? "TBA"}</span>
                                        </div>
                                        <div className="text-muted-foreground mt-1 text-xs">
                                            {option.raw.start} – {option.raw.end}
                                        </div>
                                    </div>
                                ))
                            ) : (
                                <div className="text-muted-foreground text-sm">No schedules set yet.</div>
                            )}
                        </div>
                    ) : (
                        <>
                            <DndContext onDragEnd={handleScheduleDragEnd}>
                                <div className="flex gap-3 overflow-x-auto pb-2">
                                    {weekdays.map((day) => (
                                        <ScheduleDayColumn
                                            key={day}
                                            day={day}
                                            schedules={scheduleDraft.filter((schedule) => schedule.day_of_week === day)}
                                            roomsById={roomsById}
                                            formatTime={formatTime}
                                            onEdit={(id: string) => setScheduleEditTargetId(id)}
                                            onRemove={(id: string) => removeScheduleDraft(id)}
                                        />
                                    ))}
                                </div>
                            </DndContext>
                            <div className="flex flex-wrap items-center justify-between gap-2">
                                <Button variant="outline" size="sm" onClick={handleAddSchedule}>
                                    <IconPlus className="mr-2 size-4" />
                                    Add schedule
                                </Button>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => setScheduleDraft(normalizeSchedules(classSchedules))}
                                    disabled={!schedulesDirty || isSavingSchedules}
                                >
                                    <IconX className="mr-2 size-4" />
                                    Reset
                                </Button>
                            </div>
                        </>
                    )}
                </CardContent>
            </Card>

            <Dialog
                open={scheduleEditorOpen && scheduleToEdit !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setScheduleEditTargetId(null);
                    }
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Edit schedule</DialogTitle>
                        <DialogDescription>Adjust day, time, and room.</DialogDescription>
                    </DialogHeader>
                    {scheduleToEdit ? (
                        <div className="grid gap-4">
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="space-y-2">
                                    <Label>Day</Label>
                                    <Select
                                        value={scheduleToEdit.day_of_week}
                                        onValueChange={(value) => updateScheduleDraft(scheduleToEdit.clientId, { day_of_week: value })}
                                    >
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {weekdays.map((day) => (
                                                <SelectItem key={day} value={day}>
                                                    {day}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="space-y-2">
                                    <Label>Room</Label>
                                    <Select
                                        value={String(scheduleToEdit.room_id)}
                                        onValueChange={(value) => updateScheduleDraft(scheduleToEdit.clientId, { room_id: Number(value) })}
                                    >
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {rooms.map((room) => (
                                                <SelectItem key={room.id} value={String(room.id)}>
                                                    {room.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="space-y-2">
                                    <Label>Start time</Label>
                                    <Input
                                        type="time"
                                        value={scheduleToEdit.start_time}
                                        onChange={(event) => updateScheduleDraft(scheduleToEdit.clientId, { start_time: event.target.value })}
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label>End time</Label>
                                    <Input
                                        type="time"
                                        value={scheduleToEdit.end_time}
                                        onChange={(event) => updateScheduleDraft(scheduleToEdit.clientId, { end_time: event.target.value })}
                                    />
                                </div>
                            </div>
                        </div>
                    ) : null}
                    <DialogFooter className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-between">
                        <Button
                            variant="destructive"
                            onClick={() => {
                                if (scheduleToEdit) {
                                    removeScheduleDraft(scheduleToEdit.clientId);
                                }
                                setScheduleEditTargetId(null);
                            }}
                            disabled={!scheduleToEdit}
                        >
                            Delete
                        </Button>
                        <Button variant="outline" onClick={() => setScheduleEditTargetId(null)}>
                            Done
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <div className="flex items-center justify-between">
                <div className="flex items-center gap-4">
                    <h3 className="text-lg font-semibold">attendance sessions</h3>
                    <div className="bg-muted/20 flex items-center rounded-lg border p-1">
                        <button
                            onClick={() => setViewMode("list")}
                            className={cn(
                                "flex items-center gap-2 rounded-md px-3 py-1 text-sm font-medium transition-colors",
                                viewMode === "list" ? "bg-background shadow-sm" : "hover:bg-muted/50 text-muted-foreground",
                            )}
                        >
                            <IconCalendar className="size-4" />
                            List
                        </button>
                        <button
                            onClick={() => setViewMode("calendar")}
                            className={cn(
                                "flex items-center gap-2 rounded-md px-3 py-1 text-sm font-medium transition-colors",
                                viewMode === "calendar" ? "bg-background shadow-sm" : "hover:bg-muted/50 text-muted-foreground",
                            )}
                        >
                            <IconCalendar className="size-4" />
                            Calendar
                        </button>
                    </div>
                </div>
                <div className="flex items-center gap-2">
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button variant="outline" size="sm">
                                <IconDownload className="mr-2 size-4" />
                                Export
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <DropdownMenuItem
                                onClick={() => {
                                    toast.loading("Generating Excel...", { id: "export-excel" });
                                    window.location.href = `/faculty/classes/${classData.id}/attendance/export?format=excel`;
                                    setTimeout(() => toast.dismiss("export-excel"), 2000);
                                }}
                            >
                                <IconFileSpreadsheet className="mr-2 size-4" />
                                Export as Excel
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                onClick={() => {
                                    toast.loading("Generating PDF...", { id: "export-pdf" });
                                    window.location.href = `/faculty/classes/${classData.id}/attendance/export?format=pdf`;
                                    setTimeout(() => toast.dismiss("export-pdf"), 3000);
                                }}
                            >
                                <IconFileTypePdf className="mr-2 size-4" />
                                Export as PDF
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                    <Button size="sm" onClick={handleTodaySession}>
                        <IconBolt className="mr-2 size-4" />
                        Today’s session
                    </Button>
                    <CreateSessionDialog
                        classId={classData.id}
                        scheduleOptions={scheduleOptions}
                        defaultDate={defaultSessionDate}
                        classStartDate={classStartDate}
                    />
                </div>
                {/* Controlled dialog for calendar clicks */}
                <CreateSessionDialog
                    classId={classData.id}
                    scheduleOptions={scheduleOptions}
                    defaultDate={defaultSessionDate}
                    classStartDate={classStartDate}
                    externalOpen={calendarDialogOpen}
                    onOpenChange={setCalendarDialogOpen}
                    prefilledDate={calendarDialogDate}
                />
            </div>

            <div className="grid gap-6 lg:grid-cols-[1fr_300px]">
                <div className="space-y-4">
                    {viewMode === "list" ? (
                        attendance.sessions.length === 0 ? (
                            <Card className="bg-card text-card-foreground border-dashed">
                                <CardContent className="flex flex-col items-center justify-center py-10 text-center">
                                    <div className="bg-muted rounded-full p-4">
                                        <IconCalendar className="text-muted-foreground size-6" />
                                    </div>
                                    <h3 className="mt-4 text-lg font-semibold">No sessions yet</h3>
                                    <p className="text-muted-foreground mt-2 max-w-sm text-sm">
                                        Create your first attendance session to start tracking student attendance.
                                    </p>
                                </CardContent>
                            </Card>
                        ) : (
                            <div className="grid gap-4">
                                {attendance.sessions.map((session) => (
                                    <Card
                                        key={session.id}
                                        className="hover:border-primary/50 bg-card text-card-foreground border-border/60 overflow-hidden transition-all"
                                    >
                                        <div className="flex items-center justify-between p-4">
                                            <div className="flex items-center gap-4">
                                                <div className="bg-muted/30 flex max-w-40 min-w-[60px] flex-col items-center justify-center rounded-lg border px-3 py-2 text-center">
                                                    <span className="text-muted-foreground text-xs font-medium uppercase">
                                                        {session.session_date
                                                            ? fromYmd(session.session_date).toLocaleDateString("en-US", { month: "short" })
                                                            : "-"}
                                                    </span>
                                                    <span className="text-xl font-bold">
                                                        {session.session_date ? fromYmd(session.session_date).getDate() : "-"}
                                                    </span>
                                                </div>
                                                <div>
                                                    <h4 className="font-semibold">{session.topic || "Regular Session"}</h4>
                                                    <p className="text-muted-foreground text-sm">
                                                        {session.records.filter((r) => r.status === "present").length} Present •{" "}
                                                        {session.records.filter((r) => r.status === "absent").length} Absent
                                                    </p>
                                                </div>
                                            </div>
                                            <div className="flex items-center gap-2">
                                                <Button variant="outline" size="sm" onClick={() => openRosterWorkspace(session.id)}>
                                                    View Roster
                                                </Button>
                                                <DropdownMenu>
                                                    <DropdownMenuTrigger asChild>
                                                        <Button variant="ghost" size="icon">
                                                            <IconDotsVertical className="size-4" />
                                                        </Button>
                                                    </DropdownMenuTrigger>
                                                    <DropdownMenuContent align="end">
                                                        <DropdownMenuItem onClick={() => handleToggleSessionLock(session)}>
                                                            {session.is_locked ? "Unlock Session" : "Lock Session"}
                                                        </DropdownMenuItem>
                                                        <DropdownMenuSeparator />
                                                        <DropdownMenuItem
                                                            className="text-destructive"
                                                            onClick={() => setSessionPendingDelete(session)}
                                                        >
                                                            Delete Session
                                                        </DropdownMenuItem>
                                                    </DropdownMenuContent>
                                                </DropdownMenu>
                                            </div>
                                        </div>
                                    </Card>
                                ))}
                            </div>
                        )
                    ) : (
                        <div className="border-border/60 bg-card rounded-xl border p-4">
                            <Calendar
                                mode="single"
                                selected={selectedAttendanceSession?.session_date ? fromYmd(selectedAttendanceSession.session_date) : undefined}
                                onSelect={(date) => {
                                    if (!date) return;
                                    const dateStr = toLocalYmd(date);
                                    const event = calendarEvents[dateStr];
                                    if (event && event.session_id) {
                                        openRosterWorkspace(event.session_id);
                                    }
                                }}
                                className="w-full"
                                classNames={{
                                    months: "w-full",
                                    month: "w-full space-y-4",
                                    table: "w-full border-collapse space-y-1",
                                    head_row: "flex w-full justify-between",
                                    row: "flex w-full mt-2 justify-between",
                                    cell: "h-24 w-full p-1 relative [&:has([aria-selected])]:bg-accent first:[&:has([aria-selected])]:rounded-l-md last:[&:has([aria-selected])]:rounded-r-md focus-within:relative focus-within:z-20",
                                    day: cn(
                                        "hover:bg-muted/50 flex h-full w-full flex-col items-start justify-start gap-1 rounded-md p-2 text-left font-normal transition-colors aria-selected:opacity-100",
                                    ),
                                    day_selected:
                                        "bg-primary text-primary-foreground hover:bg-primary hover:text-primary-foreground focus:bg-primary focus:text-primary-foreground",
                                    day_today: "bg-accent/50 text-accent-foreground",
                                    day_outside: "text-muted-foreground opacity-50",
                                    day_disabled: "text-muted-foreground opacity-50",
                                    day_range_middle: "aria-selected:bg-accent aria-selected:text-accent-foreground",
                                    day_hidden: "invisible",
                                }}
                                components={{
                                    DayButton: (props: ComponentProps<typeof CalendarDayButton>) => {
                                        const { day, className } = props;
                                        const dateStr = toLocalYmd(day.date);
                                        const event = calendarEvents[dateStr];
                                        const isToday = dateStr === todayYmd;

                                        let bgColor = "transparent";
                                        let textColor = "text-muted-foreground";
                                        let borderColor = "transparent";

                                        if (event) {
                                            if (event.type === "recorded") {
                                                bgColor = "bg-emerald-500/10";
                                                textColor = "text-emerald-700 dark:text-emerald-400";
                                                borderColor = "border-emerald-200 dark:border-emerald-500/30";
                                            } else if (event.type === "missing") {
                                                bgColor = "bg-rose-500/10";
                                                textColor = "text-rose-700 dark:text-rose-400";
                                                borderColor = "border-rose-200 dark:border-rose-500/30";
                                            } else if (event.type === "no-meeting") {
                                                bgColor = "bg-amber-500/10";
                                                textColor = "text-amber-700 dark:text-amber-400";
                                                borderColor = "border-amber-200 dark:border-amber-500/30";
                                            }
                                        }

                                        // We modify the className passed to CalendarDayButton to pass our styles
                                        // However, CalendarDayButton renders a Button which might not accept children easily if it expects day number.
                                        // Wait, CalendarDayButton logic is complex (see calendar.tsx). It uses data attributes.
                                        // If we replace it, we must ensure we pass all props.

                                        // Actually simplest way is to render CalendarDayButton but use the Popover as wrapper?
                                        // But CalendarDayButton returns a Button. PopoverTrigger asChild expects a single child.
                                        // This works.

                                        // We also want to inject the content (stats/dots) INSIDE the button.
                                        // CalendarDayButton keeps children (usually day number).
                                        // But `react-day-picker` passes `children` (day number) to it.
                                        // Typescript says `CalendarDayButton` accepts props matching `DayButton`.

                                        // Let's reuse CalendarDayButton and override children?
                                        // OR easier: Build our own button content and pass it as children?
                                        // But `CalendarDayButton` doesn't take 'content' prop easily.

                                        // Wait, the children of DayButton IS the day number.
                                        // If we use `DayButton` component:

                                        return (
                                            <TooltipProvider>
                                                <Tooltip>
                                                    <TooltipTrigger asChild>
                                                        <CalendarDayButton
                                                            {...props}
                                                            className={cn(
                                                                className,
                                                                bgColor,
                                                                borderColor,
                                                                textColor,
                                                                event ? "border border-solid" : "border-transparent",
                                                                "hover:bg-muted/50",
                                                            )}
                                                            onClick={() => handleCalendarDayClick(day.date, event)}
                                                        >
                                                            <div className="flex h-full w-full flex-col items-start gap-1">
                                                                <span
                                                                    className={cn(
                                                                        "text-sm font-medium",
                                                                        isToday &&
                                                                            "bg-primary text-primary-foreground -mt-1 -ml-1 flex size-6 items-center justify-center rounded-full shadow-sm",
                                                                    )}
                                                                >
                                                                    {day.date.getDate()}
                                                                </span>

                                                                {event && (
                                                                    <div className="mt-1 flex w-full flex-col gap-0.5">
                                                                        {event.type === "recorded" && event.stats ? (
                                                                            <>
                                                                                <div className="flex items-center gap-1 text-[10px] font-semibold text-emerald-600 sm:text-xs dark:text-emerald-400">
                                                                                    <div className="size-1.5 rounded-full bg-emerald-500" />
                                                                                    {event.stats.present} P
                                                                                </div>
                                                                                {event.stats.absent > 0 && (
                                                                                    <div className="flex items-center gap-1 text-[10px] text-rose-600 sm:text-xs dark:text-rose-400">
                                                                                        <div className="size-1.5 rounded-full bg-rose-500" />
                                                                                        {event.stats.absent} A
                                                                                    </div>
                                                                                )}
                                                                            </>
                                                                        ) : event.type === "no-meeting" ? (
                                                                            <div className="flex items-center gap-1 text-[10px] font-medium text-amber-600 sm:text-xs dark:text-amber-400">
                                                                                <div className="size-1.5 rounded-full bg-amber-500" />
                                                                                No Meet
                                                                            </div>
                                                                        ) : (
                                                                            <div className="flex items-center gap-1 text-[10px] font-medium text-rose-600 sm:text-xs dark:text-rose-400">
                                                                                <div className="size-1.5 rounded-full bg-rose-500" />
                                                                                Click +
                                                                            </div>
                                                                        )}
                                                                    </div>
                                                                )}
                                                            </div>
                                                        </CalendarDayButton>
                                                    </TooltipTrigger>
                                                    {event && (
                                                        <TooltipContent side="right" className="max-w-xs">
                                                            <div className="space-y-1">
                                                                <p className="text-sm font-semibold">
                                                                    {new Date(day.date).toLocaleDateString(undefined, {
                                                                        weekday: "long",
                                                                        month: "short",
                                                                        day: "numeric",
                                                                    })}
                                                                </p>
                                                                {event.type === "recorded" && event.stats ? (
                                                                    <p className="text-xs">
                                                                        Present: {event.stats.present} • Absent: {event.stats.absent} • Late:{" "}
                                                                        {event.stats.late}
                                                                    </p>
                                                                ) : event.type === "no-meeting" ? (
                                                                    <p className="text-xs italic">No meeting: {event.reason || "No reason"}</p>
                                                                ) : (
                                                                    <p className="text-xs">Click to create session</p>
                                                                )}
                                                            </div>
                                                        </TooltipContent>
                                                    )}
                                                </Tooltip>
                                            </TooltipProvider>
                                        );
                                    },
                                }}
                            />
                        </div>
                    )}
                </div>

                <div className="space-y-6">
                    <Card className="bg-card text-card-foreground">
                        <CardHeader>
                            <CardTitle>Overview</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid grid-cols-2 gap-4">
                                <div className="rounded-lg border p-3 text-center">
                                    <div className="text-2xl font-bold text-emerald-600">{attendanceSummary.by_status.present}</div>
                                    <div className="text-muted-foreground text-xs">Present</div>
                                </div>
                                <div className="rounded-lg border p-3 text-center">
                                    <div className="text-2xl font-bold text-rose-600">{attendanceSummary.by_status.absent}</div>
                                    <div className="text-muted-foreground text-xs">Absent</div>
                                </div>
                                <div className="rounded-lg border p-3 text-center">
                                    <div className="text-2xl font-bold text-amber-600">{attendanceSummary.by_status.late}</div>
                                    <div className="text-muted-foreground text-xs">Late</div>
                                </div>
                                <div className="rounded-lg border p-3 text-center">
                                    <div className="text-2xl font-bold text-blue-600">{attendanceSummary.by_status.excused}</div>
                                    <div className="text-muted-foreground text-xs">Excused</div>
                                </div>
                            </div>
                            <div className="border-t pt-4">
                                <div className="flex justify-between text-sm">
                                    <span className="text-muted-foreground">Total Sessions</span>
                                    <span className="font-medium">{attendanceSummary.total_sessions}</span>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>

            <Sheet
                open={isRosterSheetOpen}
                onOpenChange={(open) => {
                    setIsRosterSheetOpen(open);
                    if (!open) {
                        onClearFocus();
                    }
                }}
            >
                <SheetContent side="right" className="bg-background text-foreground flex w-[95%] flex-col gap-0 p-0 sm:max-w-3xl">
                    <SheetHeader className="border-border/60 bg-card/50 sticky top-0 z-10 space-y-2 border-b px-6 py-4 backdrop-blur-sm">
                        <SheetTitle className="text-2xl">Roster workspace</SheetTitle>
                        <SheetDescription>Update individual attendance without leaving the timeline.</SheetDescription>
                    </SheetHeader>
                    <div className="flex-1 space-y-6 overflow-y-auto px-6 py-6">
                        {selectedAttendanceSession ? (
                            <>
                                <div className="bg-card text-muted-foreground flex flex-wrap items-center gap-3 rounded-2xl border p-4 text-sm shadow-sm">
                                    <div className="flex items-center gap-2">
                                        <div className="bg-primary/10 text-primary flex size-8 items-center justify-center rounded-full">
                                            <IconCalendar className="size-4" />
                                        </div>
                                        <span className="text-foreground font-semibold">{selectedAttendanceSession.topic || "General meeting"}</span>
                                    </div>
                                    <div className="bg-border/60 h-4 w-px" />
                                    <span>
                                        {selectedAttendanceSession.session_date
                                            ? fromYmd(selectedAttendanceSession.session_date).toLocaleDateString(undefined, { dateStyle: "medium" })
                                            : "Unscheduled"}
                                    </span>
                                    <div className="bg-border/60 h-4 w-px" />
                                    <span>{selectedAttendanceSession.taken_by || "You"}</span>
                                    {selectedAttendanceSession.is_no_meeting ? (
                                        <Badge
                                            variant="secondary"
                                            className="ml-auto rounded-full bg-amber-500/15 text-amber-700 hover:bg-amber-500/25"
                                        >
                                            No meeting
                                            {selectedAttendanceSession.no_meeting_reason ? ` • ${selectedAttendanceSession.no_meeting_reason}` : ""}
                                        </Badge>
                                    ) : null}
                                    <div className="ml-auto flex items-center gap-2">
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() => handleToggleSessionLock(selectedAttendanceSession)}
                                            className="h-8"
                                        >
                                            {selectedAttendanceSession.is_locked ? "Unlock" : "Lock"}
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            onClick={() => setSessionPendingDelete(selectedAttendanceSession)}
                                            className="text-destructive hover:bg-destructive/10 hover:text-destructive h-8 w-8"
                                        >
                                            <IconTrash className="size-4" />
                                        </Button>
                                    </div>
                                </div>

                                <div className="border-border/60 bg-card space-y-3 rounded-2xl border p-4 shadow-sm">
                                    <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                                        <div className="space-y-2">
                                            <Label className="text-muted-foreground text-xs tracking-[0.2em] uppercase">Search</Label>
                                            <Input
                                                value={studentFilter}
                                                onChange={(event) => setStudentFilter(event.target.value)}
                                                placeholder="Search student name or ID..."
                                                className="h-9 w-full sm:w-80"
                                            />
                                        </div>
                                        <div className="flex flex-wrap items-center gap-2">
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={() => handleBulkUpdate("present")}
                                                disabled={
                                                    selectedAttendanceSession.is_locked ||
                                                    selectedAttendanceSession.is_no_meeting ||
                                                    filteredRosterRecords.length === 0
                                                }
                                            >
                                                Mark shown Present
                                            </Button>
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={() => handleBulkUpdate("absent")}
                                                disabled={
                                                    selectedAttendanceSession.is_locked ||
                                                    selectedAttendanceSession.is_no_meeting ||
                                                    filteredRosterRecords.length === 0
                                                }
                                            >
                                                Mark shown Absent
                                            </Button>
                                            <DropdownMenu>
                                                <DropdownMenuTrigger asChild>
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        disabled={selectedAttendanceSession.is_locked || selectedAttendanceSession.is_no_meeting}
                                                    >
                                                        <IconDotsVertical className="mr-2 size-4" />
                                                        More
                                                    </Button>
                                                </DropdownMenuTrigger>
                                                <DropdownMenuContent align="end">
                                                    <DropdownMenuItem onClick={() => handleBulkUpdate("late")}>Mark shown Late</DropdownMenuItem>
                                                    <DropdownMenuItem onClick={() => handleBulkUpdate("excused")}>
                                                        Mark shown Excused
                                                    </DropdownMenuItem>
                                                    <DropdownMenuSeparator />
                                                    <DropdownMenuItem onClick={() => setStudentFilter("")}>Clear search</DropdownMenuItem>
                                                </DropdownMenuContent>
                                            </DropdownMenu>
                                        </div>
                                    </div>
                                    <p className="text-muted-foreground text-xs">
                                        Shortcuts: ↑/↓ (or j/k) select • 1/P Present • 2/L Late • 3/A Absent • 4/E Excused • Esc close
                                    </p>
                                </div>

                                <div className="border-border/60 bg-card overflow-hidden rounded-2xl border shadow-sm">
                                    {selectedAttendanceSession.is_no_meeting ? (
                                        <div className="flex flex-col items-center justify-center py-12 text-center">
                                            <div className="mb-3 rounded-full bg-amber-100 p-3 text-amber-600">
                                                <IconCalendar className="size-6" />
                                            </div>
                                            <h3 className="text-foreground font-semibold">No Meeting</h3>
                                            <p className="text-muted-foreground mt-1 max-w-xs text-sm">
                                                This session was marked as no meeting. Roster updates are skipped for{" "}
                                                {selectedAttendanceSession.no_meeting_reason || "this date"}.
                                            </p>
                                        </div>
                                    ) : selectedAttendanceSession.records.length === 0 ? (
                                        <div className="flex flex-col items-center justify-center py-12 text-center">
                                            <div className="bg-muted text-muted-foreground mb-3 rounded-full p-3">
                                                <IconUsers className="size-6" />
                                            </div>
                                            <h3 className="text-foreground font-semibold">No Students</h3>
                                            <p className="text-muted-foreground mt-1 max-w-xs text-sm">
                                                Students will appear here once they are enrolled in the class roster.
                                            </p>
                                        </div>
                                    ) : filteredRosterRecords.length === 0 ? (
                                        <div className="flex flex-col items-center justify-center py-12 text-center">
                                            <div className="bg-muted text-muted-foreground mb-3 rounded-full p-3">
                                                <IconUsers className="size-6" />
                                            </div>
                                            <h3 className="text-foreground font-semibold">No matches</h3>
                                            <p className="text-muted-foreground mt-1 max-w-xs text-sm">Try a different search term.</p>
                                        </div>
                                    ) : (
                                        <div className="divide-border/60 divide-y">
                                            {filteredRosterRecords.map((record) => {
                                                const remarkValue = recordRemarksDrafts[record.id] ?? record.remarks ?? "";
                                                const isFocused = focusStudentId === record.class_enrollment_id;
                                                const isKeyboardFocused = keyboardFocusEnrollmentId === record.class_enrollment_id;
                                                const canEditRecord = !selectedAttendanceSession.is_locked;

                                                return (
                                                    <div
                                                        key={record.id}
                                                        id={`attendance-row-${record.class_enrollment_id}`}
                                                        onClick={() => setKeyboardFocusEnrollmentId(record.class_enrollment_id)}
                                                        className={cn(
                                                            "hover:bg-muted/30 flex flex-col gap-3 px-4 py-4 transition-all duration-500 md:flex-row md:items-center md:gap-4",
                                                            isFocused && "bg-primary/5 ring-primary/20 shadow-sm ring-1",
                                                            isKeyboardFocused && "ring-primary/30 ring-2",
                                                        )}
                                                    >
                                                        <div className="min-w-0 flex-1">
                                                            <p className="text-foreground truncate text-sm font-semibold">{record.student.name}</p>
                                                            <p className="text-muted-foreground truncate text-xs">{record.student.student_number}</p>
                                                        </div>
                                                        <div className="flex flex-wrap gap-1.5">
                                                            {(Object.keys(attendanceStatusMeta) as AttendanceStatus[]).map((status) => {
                                                                let activeClass = "";
                                                                let inactiveClass = "hover:bg-muted";

                                                                switch (status) {
                                                                    case "present":
                                                                        activeClass =
                                                                            "bg-emerald-100 text-emerald-700 hover:bg-emerald-200 border-emerald-200 dark:bg-emerald-500/20 dark:text-emerald-300 dark:border-emerald-500/30";
                                                                        inactiveClass =
                                                                            "hover:text-emerald-600 hover:bg-emerald-50 dark:hover:bg-emerald-500/10";
                                                                        break;
                                                                    case "absent":
                                                                        activeClass =
                                                                            "bg-rose-100 text-rose-700 hover:bg-rose-200 border-rose-200 dark:bg-rose-500/20 dark:text-rose-300 dark:border-rose-500/30";
                                                                        inactiveClass =
                                                                            "hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-500/10";
                                                                        break;
                                                                    case "late":
                                                                        activeClass =
                                                                            "bg-amber-100 text-amber-700 hover:bg-amber-200 border-amber-200 dark:bg-amber-500/20 dark:text-amber-300 dark:border-amber-500/30";
                                                                        inactiveClass =
                                                                            "hover:text-amber-600 hover:bg-amber-50 dark:hover:bg-amber-500/10";
                                                                        break;
                                                                    case "excused":
                                                                        activeClass =
                                                                            "bg-blue-100 text-blue-700 hover:bg-blue-200 border-blue-200 dark:bg-blue-500/20 dark:text-blue-300 dark:border-blue-500/30";
                                                                        inactiveClass =
                                                                            "hover:text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-500/10";
                                                                        break;
                                                                }

                                                                const isActive = record.status === status;

                                                                return (
                                                                    <button
                                                                        key={`${record.id}-${status}`}
                                                                        type="button"
                                                                        disabled={!canEditRecord}
                                                                        className={cn(
                                                                            "rounded-full border px-3 py-1 text-xs font-medium transition-all",
                                                                            isActive
                                                                                ? activeClass + " shadow-sm"
                                                                                : "text-muted-foreground border-transparent bg-transparent " +
                                                                                      inactiveClass,
                                                                        )}
                                                                        onClick={() => handleUpdateAttendanceRecord(record, status, remarkValue)}
                                                                    >
                                                                        {attendanceStatusMeta[status].label}
                                                                    </button>
                                                                );
                                                            })}
                                                        </div>
                                                        <div className="w-full md:w-48">
                                                            <Input
                                                                value={remarkValue}
                                                                onChange={(event) =>
                                                                    setRecordRemarksDrafts((previous) => ({
                                                                        ...previous,
                                                                        [record.id]: event.target.value,
                                                                    }))
                                                                }
                                                                onBlur={() => handleUpdateAttendanceRecord(record, record.status, remarkValue)}
                                                                disabled={!canEditRecord}
                                                                placeholder="Add remark..."
                                                                className="bg-muted/30 focus:bg-background focus:border-input h-8 border-transparent text-xs transition-all"
                                                            />
                                                        </div>
                                                    </div>
                                                );
                                            })}
                                        </div>
                                    )}
                                </div>
                            </>
                        ) : (
                            <div className="border-border/60 bg-muted/10 flex flex-col items-center justify-center rounded-3xl border border-dashed py-20 text-center">
                                <div className="bg-muted mb-4 rounded-full p-4">
                                    <IconCalendar className="text-muted-foreground size-8" />
                                </div>
                                <h3 className="text-lg font-semibold">No Session Selected</h3>
                                <p className="text-muted-foreground mt-2 max-w-sm text-sm">
                                    Select a session from the timeline to reveal the roster workspace and start taking attendance.
                                </p>
                            </div>
                        )}
                    </div>
                </SheetContent>
            </Sheet>

            <Dialog
                open={sessionPendingDelete !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setSessionPendingDelete(null);
                    }
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete attendance session?</DialogTitle>
                        <DialogDescription>This will remove the roster data for this meeting.</DialogDescription>
                    </DialogHeader>
                    <div className="text-muted-foreground space-y-2 text-sm">
                        <p className="text-foreground font-semibold">
                            {sessionPendingDelete?.session_date ? new Date(sessionPendingDelete.session_date).toLocaleDateString() : "Session"}
                        </p>
                        <p>{sessionPendingDelete?.topic || `Schedule #${sessionPendingDelete?.schedule_id ?? ""}`}</p>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setSessionPendingDelete(null)}>
                            Cancel
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={() => {
                                if (sessionPendingDelete) {
                                    handleDeleteAttendanceSession(sessionPendingDelete.id);
                                    setSessionPendingDelete(null);
                                }
                            }}
                        >
                            Delete session
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}

interface ScheduleDayColumnProps {
    day: string;
    schedules: Array<{
        clientId: string;
        start_time: string;
        end_time: string;
        room_id: number;
    }>;
    roomsById: Map<number, string>;
    formatTime: (value: string) => string;
    onEdit: (clientId: string) => void;
    onRemove: (clientId: string) => void;
}

function ScheduleDayColumn({ day, schedules, roomsById, formatTime, onEdit, onRemove }: ScheduleDayColumnProps) {
    const { setNodeRef, isOver } = useDroppable({
        id: `day:${day}`,
    });

    const sortedSchedules = schedules.slice().sort((a, b) => `${a.start_time}${a.end_time}`.localeCompare(`${b.start_time}${b.end_time}`));

    return (
        <div
            ref={setNodeRef}
            className={cn("border-border/60 bg-muted/5 min-w-[220px] flex-1 rounded-xl border p-3", isOver && "ring-primary/20 bg-primary/5 ring-2")}
        >
            <div className="flex items-center justify-between">
                <p className="text-foreground text-sm font-semibold">{day}</p>
                <p className="text-muted-foreground text-xs">{sortedSchedules.length}</p>
            </div>
            <div className="mt-3 flex flex-col gap-2">
                {sortedSchedules.length ? (
                    sortedSchedules.map((schedule) => (
                        <ScheduleDraggableCard
                            key={schedule.clientId}
                            schedule={schedule}
                            roomsById={roomsById}
                            formatTime={formatTime}
                            onEdit={onEdit}
                            onRemove={onRemove}
                        />
                    ))
                ) : (
                    <div className="border-border/60 bg-background/40 text-muted-foreground rounded-lg border border-dashed px-3 py-4 text-xs">
                        Drop here
                    </div>
                )}
            </div>
        </div>
    );
}

interface ScheduleDraggableCardProps {
    schedule: {
        clientId: string;
        start_time: string;
        end_time: string;
        room_id: number;
    };
    roomsById: Map<number, string>;
    formatTime: (value: string) => string;
    onEdit: (clientId: string) => void;
    onRemove: (clientId: string) => void;
}

function ScheduleDraggableCard({ schedule, roomsById, formatTime, onEdit, onRemove }: ScheduleDraggableCardProps) {
    const { attributes, listeners, setNodeRef, transform, isDragging } = useDraggable({
        id: schedule.clientId,
    });

    const style = {
        transform: CSS.Transform.toString(transform),
    };

    const roomName = roomsById.get(schedule.room_id) ?? "Room";

    return (
        <div
            ref={setNodeRef}
            style={style}
            className={cn("border-border/60 bg-background rounded-lg border px-3 py-2 shadow-sm", isDragging && "opacity-70 shadow-md")}
        >
            <div className="flex items-start gap-2">
                <button
                    type="button"
                    className="text-muted-foreground hover:bg-muted/40 mt-0.5 inline-flex h-6 w-6 cursor-grab items-center justify-center rounded-md active:cursor-grabbing"
                    {...listeners}
                    {...attributes}
                >
                    <IconGripVertical className="size-4" />
                </button>
                <div className="min-w-0 flex-1">
                    <p className="text-foreground text-xs font-semibold">
                        {formatTime(schedule.start_time)} – {formatTime(schedule.end_time)}
                    </p>
                    <p className="text-muted-foreground truncate text-[11px]">{roomName}</p>
                </div>
                <div className="flex items-center gap-1">
                    <Button type="button" variant="ghost" size="icon" className="h-7 w-7" onClick={() => onEdit(schedule.clientId)}>
                        <IconEdit className="size-4" />
                        <span className="sr-only">Edit</span>
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="text-destructive hover:bg-destructive/10 hover:text-destructive h-7 w-7"
                        onClick={() => onRemove(schedule.clientId)}
                    >
                        <IconTrash className="size-4" />
                        <span className="sr-only">Remove</span>
                    </Button>
                </div>
            </div>
        </div>
    );
}
