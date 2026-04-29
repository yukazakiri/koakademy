import AdminLayout from "@/components/administrators/admin-layout";
import { ClassScheduleVisualizer } from "@/Components/administrators/classes/schedule-visualizer";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Checkbox } from "@/components/ui/checkbox";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from "@/components/ui/dropdown-menu";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { MultiSelect as SearchableMultiSelect } from "@/components/ui/multi-select";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Sheet, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle } from "@/components/ui/sheet";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { VisualRadioButton } from "@/Components/ui/visual-radio-button";
import type { User } from "@/types/user";
import { Head, Link, router, useForm } from "@inertiajs/react";
import {
    BookOpen,
    CalendarIcon,
    Clock,
    Copy as CopyIcon,
    GraduationCap,
    Layers,
    LayoutGrid,
    List,
    ListTodo,
    MapPin,
    MoreHorizontal,
    Palette,
    Pencil,
    Plus,
    Search,
    Settings2,
    SlidersHorizontal,
    Trash2,
    Users,
    X,
} from "lucide-react";
import * as React from "react";
import { useDebouncedCallback } from "use-debounce";
import { route } from "ziggy-js";
import { ClassRow, getColumns } from "./columns";
import { DataTable } from "./data-table";

type SelectOption = { value: string; label: string };

type EntityOption = { id: string | number; label: string };

type ClassSchedule = {
    id?: number;
    day_of_week: string;
    start_time: string;
    end_time: string;
    room_id: number;
    room?: EntityOption | null;
};

type ClassSettingsFormData = {
    background_color: string;
    accent_color: string;
    theme: string;
    enable_announcements: boolean;
    enable_grade_visibility: boolean;
    enable_attendance_tracking: boolean;
    allow_late_submissions: boolean;
    enable_discussion_board: boolean;
    custom: Record<string, string>;
    banner_image: File | null;
};

type SelectedClass = {
    id: number;
    record_title: string;
    classification: "college" | "shs" | string;
    subject_code: string;
    subject_title: string;
    section: string;
    school_year: string;
    semester: string | number;
    academic_year: number | null;
    grade_level: string | null;
    shs_track: EntityOption | null;
    shs_strand: EntityOption | null;
    faculty: (EntityOption & { email?: string | null }) | null;
    room: EntityOption | null;
    course_codes: string[];
    course_ids: number[];
    subject_ids: number[];
    subjects: { id: number; code: string; title: string }[];
    maximum_slots: number;
    students_count: number;
    settings: {
        background_color?: string;
        accent_color?: string;
        banner_image?: string | null;
        theme?: string;
        enable_announcements?: boolean;
        enable_grade_visibility?: boolean;
        enable_attendance_tracking?: boolean;
        allow_late_submissions?: boolean;
        enable_discussion_board?: boolean;
        custom?: Record<string, string>;
    };
    schedules: ClassSchedule[];
    enrollments: {
        id: number;
        student: {
            id: number;
            student_id: string | number | null;
            name: string;
            course: string | null;
            academic_year: string | number | null;
        } | null;
        status: string | null;
        prelim_grade: number | null;
        midterm_grade: number | null;
        finals_grade: number | null;
        total_average: number | null;
        remarks: string | null;
    }[];
    posts: {
        id: number;
        title: string;
        type: string;
        created_at: string | null;
    }[];
    filament: {
        view_url: string;
        edit_url: string;
    };
};

type ClassDialogTab = "details" | "schedule" | "settings";

interface ClassesIndexProps {
    user: User;
    filament: {
        classes: {
            index_url: string;
            create_url: string;
        };
    };
    classes: {
        data: ClassRow[];
        prev_page_url: string | null;
        next_page_url: string | null;
        total: number;
        from: number;
        to: number;
        current_page: number;
        last_page: number;
        per_page: number;
    };
    selected_class: SelectedClass | null;
    filters: {
        search?: string | null;
        classification?: string | null;
        course_id?: number | null;
        shs_track_id?: number | null;
        shs_strand_id?: number | null;
        subject_code?: string | null;
        room_id?: number | null;
        faculty_id?: string | null;
        academic_year?: number | null;
        grade_level?: string | null;
        semester?: string | null;
        available_slots?: boolean | null;
        fully_enrolled?: boolean | null;
    };
    options: {
        classifications: SelectOption[];
        sections: SelectOption[];
        semesters: SelectOption[];
        grade_levels: SelectOption[];
        day_of_week: SelectOption[];
        courses: EntityOption[];
        faculties: EntityOption[];
        rooms: EntityOption[];
        shs_tracks: EntityOption[];
    };
    defaults: {
        semester: string;
        school_year: string;
    };
}

function ClassCard({
    classRow,
    onManage,
    onCopy,
    onEdit,
    onDelete,
}: {
    classRow: ClassRow;
    onManage: (id: number) => void;
    onCopy: (id: number) => void;
    onEdit: (id: number) => void;
    onDelete: (row: ClassRow) => void;
}) {
    const atCapacity = classRow.maximum_slots > 0 && classRow.students_count >= classRow.maximum_slots;
    const percentage = classRow.maximum_slots > 0 ? Math.round((classRow.students_count / classRow.maximum_slots) * 100) : 0;

    return (
        <Card className="hover:border-primary/50 transition-colors">
            <CardContent className="p-0">
                <div className="bg-muted/30 flex h-20 items-center justify-center rounded-t-lg">
                    <BookOpen className="text-muted-foreground/30 h-8 w-8" />
                </div>
                <div className="p-4">
                    <div className="mb-3 flex items-start justify-between gap-2">
                        <button
                            type="button"
                            onClick={() => onEdit(classRow.id)}
                            className="min-w-0 flex-1 text-left"
                            title="Click to edit class"
                        >
                            <span
                                className="text-foreground hover:text-primary line-clamp-1 block font-semibold transition-colors"
                                title={classRow.record_title}
                            >
                                {classRow.record_title}
                            </span>
                            <span className="text-muted-foreground line-clamp-1 block text-xs" title={classRow.subject_title}>
                                {classRow.subject_title}
                            </span>
                        </button>
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button variant="ghost" size="icon" className="text-muted-foreground -mt-1 -mr-1 h-8 w-8 shrink-0">
                                    <MoreHorizontal className="h-4 w-4" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" className="w-48">
                                <DropdownMenuItem onClick={() => onEdit(classRow.id)}>
                                    <Pencil className="mr-2 h-4 w-4" /> Edit class
                                </DropdownMenuItem>
                                <DropdownMenuItem onClick={() => onManage(classRow.id)}>
                                    <Settings2 className="mr-2 h-4 w-4" /> Manage details
                                </DropdownMenuItem>
                                <DropdownMenuItem asChild>
                                    <Link href={route("administrators.classes.show", { class: classRow.id })}>
                                        <BookOpen className="mr-2 h-4 w-4" /> Open class page
                                    </Link>
                                </DropdownMenuItem>
                                <DropdownMenuItem onClick={() => onCopy(classRow.id)}>
                                    <CopyIcon className="mr-2 h-4 w-4" /> Duplicate class
                                </DropdownMenuItem>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem onClick={() => onDelete(classRow)} className="text-destructive focus:text-destructive">
                                    <Trash2 className="mr-2 h-4 w-4" /> Delete class
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>

                    <div className="text-muted-foreground mb-4 grid gap-1.5 text-sm">
                        <div className="flex items-center gap-2">
                            <GraduationCap className="h-4 w-4 opacity-70" />
                            <span className="truncate">{classRow.section}</span>
                        </div>
                        <div className="flex items-center gap-2">
                            <Users className="h-4 w-4 opacity-70" />
                            <span className="truncate">{classRow.faculty}</span>
                        </div>
                    </div>

                    <div className="space-y-1.5">
                        <div className="flex items-center justify-between text-xs">
                            <span className={atCapacity ? "text-destructive font-medium" : "text-muted-foreground"}>
                                {classRow.students_count} / {classRow.maximum_slots} students
                            </span>
                            <span className="text-muted-foreground">{percentage}%</span>
                        </div>
                        <div className="bg-secondary h-2 w-full overflow-hidden rounded-full">
                            <div
                                className={`h-full ${atCapacity ? "bg-destructive" : "bg-primary"}`}
                                style={{ width: `${Math.min(percentage, 100)}%` }}
                            />
                        </div>
                    </div>

                    <div className="mt-4 flex items-center gap-2 border-t pt-3">
                        <Button type="button" size="sm" variant="default" className="flex-1" onClick={() => onEdit(classRow.id)}>
                            <Pencil className="mr-1.5 h-3.5 w-3.5" />
                            Edit
                        </Button>
                        <Button type="button" size="sm" variant="outline" onClick={() => onManage(classRow.id)} title="Manage details">
                            <Settings2 className="h-3.5 w-3.5" />
                        </Button>
                        <Button type="button" size="sm" variant="outline" onClick={() => onCopy(classRow.id)} title="Duplicate">
                            <CopyIcon className="h-3.5 w-3.5" />
                        </Button>
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}

function StatsOverview({ totalClasses, totalStudents }: { totalClasses: number; totalStudents: number }) {
    return (
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <Card>
                <CardContent className="flex flex-col justify-center p-6">
                    <div className="text-2xl font-bold">{totalClasses}</div>
                    <div className="text-muted-foreground text-xs">Total classes</div>
                </CardContent>
            </Card>
            <Card>
                <CardContent className="flex flex-col justify-center p-6">
                    <div className="text-2xl font-bold">{totalStudents.toLocaleString()}</div>
                    <div className="text-muted-foreground text-xs">Total enrolled students</div>
                </CardContent>
            </Card>
        </div>
    );
}

function normalizeSemester(semester: string | number): string {
    if (semester === 1 || semester === "1") return "1";
    if (semester === 2 || semester === "2") return "2";
    return String(semester);
}

function buildSubjectCodeFromSubjectOptions(selectedIds: string[], subjectOptions: { id: number; code: string }[]): string {
    const selected = new Set(selectedIds);
    const codes = subjectOptions
        .filter((s) => selected.has(String(s.id)))
        .map((s) => s.code)
        .filter(Boolean);
    return Array.from(new Set(codes)).join(", ");
}

function createDefaultSchedule(defaultRoomId: number): ClassSchedule {
    return {
        day_of_week: "Monday",
        start_time: "08:00",
        end_time: "09:00",
        room_id: defaultRoomId,
    };
}

function parseTimeToMinutes(value: string): number | null {
    const match = value.match(/^(\d{1,2}):(\d{2})$/);

    if (!match) {
        return null;
    }

    const hours = Number(match[1]);
    const minutes = Number(match[2]);

    if (!Number.isFinite(hours) || !Number.isFinite(minutes) || hours < 0 || hours > 23 || minutes < 0 || minutes > 59) {
        return null;
    }

    return hours * 60 + minutes;
}

function formatTimeLabel(value: string): string {
    const totalMinutes = parseTimeToMinutes(value);

    if (totalMinutes === null) {
        return "Invalid time";
    }

    const hours = Math.floor(totalMinutes / 60);
    const minutes = totalMinutes % 60;
    const normalizedHours = hours % 12 || 12;
    const suffix = hours >= 12 ? "PM" : "AM";

    return `${normalizedHours}:${String(minutes).padStart(2, "0")} ${suffix}`;
}

function getScheduleDurationLabel(schedule: ClassSchedule): string {
    const start = parseTimeToMinutes(schedule.start_time);
    const end = parseTimeToMinutes(schedule.end_time);

    if (start === null || end === null || end <= start) {
        return "Invalid time range";
    }

    const duration = end - start;
    const hours = Math.floor(duration / 60);
    const minutes = duration % 60;

    if (hours === 0) {
        return `${minutes} min`;
    }

    if (minutes === 0) {
        return `${hours} hr${hours === 1 ? "" : "s"}`;
    }

    return `${hours} hr ${minutes} min`;
}

function getTabForFormErrors(errors: Record<string, string>): ClassDialogTab {
    const keys = Object.keys(errors);

    if (keys.some((key) => key === "schedules" || key.startsWith("schedules."))) {
        return "schedule";
    }

    if (keys.some((key) => key === "settings" || key.startsWith("settings.") || key === "remove_banner_image")) {
        return "settings";
    }

    return "details";
}

function getFirstFormError(errors: Record<string, string>): string | null {
    const firstError = Object.values(errors).find((error) => typeof error === "string" && error.length > 0);

    return firstError ?? null;
}

function SchedulePlanner({
    schedules,
    setSchedules,
    rooms,
    dayOptions,
    defaultRoomId,
    classRoomId,
}: {
    schedules: ClassSchedule[];
    setSchedules: (nextSchedules: ClassSchedule[]) => void;
    rooms: EntityOption[];
    dayOptions: SelectOption[];
    defaultRoomId: number;
    classRoomId: number;
}) {
    const [selectedIndex, setSelectedIndex] = React.useState(0);

    React.useEffect(() => {
        if (schedules.length === 0) {
            setSchedules([createDefaultSchedule(defaultRoomId)]);
            setSelectedIndex(0);
            return;
        }

        if (selectedIndex > schedules.length - 1) {
            setSelectedIndex(Math.max(0, schedules.length - 1));
        }
    }, [defaultRoomId, schedules, selectedIndex, setSchedules]);

    const selectedSchedule = schedules[selectedIndex] ?? null;
    const roomLabelById = React.useMemo(() => new Map(rooms.map((room) => [room.id, room.label])), [rooms]);

    const updateScheduleAt = (index: number, patch: Partial<ClassSchedule>) => {
        setSchedules(schedules.map((schedule, scheduleIndex) => (scheduleIndex === index ? { ...schedule, ...patch } : schedule)));
    };

    const addSchedule = () => {
        const nextSchedule = {
            ...createDefaultSchedule(classRoomId || defaultRoomId),
            day_of_week: selectedSchedule?.day_of_week ?? "Monday",
        };

        setSchedules([...schedules, nextSchedule]);
        setSelectedIndex(schedules.length);
    };

    const duplicateSelectedSchedule = () => {
        if (!selectedSchedule) {
            return;
        }

        const nextSchedule = {
            ...selectedSchedule,
            room: undefined,
        };

        setSchedules([...schedules, nextSchedule]);
        setSelectedIndex(schedules.length);
    };

    const removeSchedule = (index: number) => {
        if (schedules.length <= 1) {
            return;
        }

        const nextSchedules = schedules.filter((_, scheduleIndex) => scheduleIndex !== index);
        setSchedules(nextSchedules);
        setSelectedIndex((currentIndex) => {
            if (currentIndex === index) {
                return Math.max(0, index - 1);
            }

            if (currentIndex > index) {
                return currentIndex - 1;
            }

            return currentIndex;
        });
    };

    const activeDays = new Set(schedules.map((schedule) => schedule.day_of_week).filter(Boolean)).size;
    const invalidBlocks = schedules.filter((schedule) => {
        const start = parseTimeToMinutes(schedule.start_time);
        const end = parseTimeToMinutes(schedule.end_time);

        return start === null || end === null || end <= start;
    }).length;

    return (
        <div className="grid gap-4 xl:grid-cols-[minmax(0,1fr)_360px]">
            <div className="space-y-4">
                <div className="bg-card/70 flex flex-col gap-3 rounded-xl border p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between">
                    <div className="space-y-1">
                        <h3 className="text-sm font-semibold">Weekly schedule</h3>
                        <p className="text-muted-foreground text-xs">
                            Drag blocks on desktop, or use the editor to adjust time, day, and room on any screen.
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Badge variant="secondary">
                            {schedules.length} block{schedules.length === 1 ? "" : "s"}
                        </Badge>
                        <Badge variant="secondary">
                            {activeDays} active day{activeDays === 1 ? "" : "s"}
                        </Badge>
                        {invalidBlocks > 0 ? <Badge variant="destructive">{invalidBlocks} invalid</Badge> : null}
                    </div>
                </div>

                <div className="bg-card/50 rounded-xl border p-2 shadow-sm sm:p-3">
                    <div className="overflow-x-auto">
                        <div className="h-[720px] min-w-[860px]">
                            <ClassScheduleVisualizer
                                className="h-full min-h-0"
                                schedules={schedules}
                                rooms={rooms}
                                onScheduleChange={(index, nextSchedule) => {
                                    updateScheduleAt(index, nextSchedule);
                                    setSelectedIndex(index);
                                }}
                            />
                        </div>
                    </div>
                </div>
            </div>

            <div className="space-y-4">
                <div className="bg-card rounded-xl border shadow-sm">
                    <div className="border-b p-4">
                        <div className="flex items-center justify-between gap-3">
                            <div>
                                <h3 className="text-sm font-semibold">Blocks</h3>
                                <p className="text-muted-foreground text-xs">Select a block to edit it precisely.</p>
                            </div>
                            <Button type="button" size="sm" onClick={addSchedule}>
                                <Plus className="mr-2 h-4 w-4" />
                                Add
                            </Button>
                        </div>
                    </div>

                    <div className="space-y-3 p-4">
                        {schedules.map((schedule, index) => {
                            const roomLabel = roomLabelById.get(schedule.room_id) ?? "Room TBA";
                            const isSelected = index === selectedIndex;

                            return (
                                <div
                                    key={`${schedule.day_of_week}-${schedule.start_time}-${schedule.end_time}-${index}`}
                                    className={`rounded-xl border transition-colors ${
                                        isSelected ? "border-primary bg-primary/5 shadow-sm" : "border-border bg-background"
                                    }`}
                                >
                                    <button
                                        type="button"
                                        className="flex w-full flex-col gap-3 p-4 text-left"
                                        onClick={() => setSelectedIndex(index)}
                                    >
                                        <div className="flex items-center justify-between gap-3">
                                            <div className="space-y-1">
                                                <div className="text-sm font-semibold">Block {index + 1}</div>
                                                <div className="text-muted-foreground text-xs">
                                                    {schedule.day_of_week || "Choose a day"} • {getScheduleDurationLabel(schedule)}
                                                </div>
                                            </div>
                                            {isSelected ? <Badge>Selected</Badge> : <Badge variant="secondary">Open</Badge>}
                                        </div>

                                        <div className="space-y-2 text-xs">
                                            <div className="flex items-center gap-2">
                                                <Clock className="text-muted-foreground h-3.5 w-3.5" />
                                                <span>
                                                    {formatTimeLabel(schedule.start_time)} to {formatTimeLabel(schedule.end_time)}
                                                </span>
                                            </div>
                                            <div className="flex items-center gap-2">
                                                <MapPin className="text-muted-foreground h-3.5 w-3.5" />
                                                <span>{roomLabel}</span>
                                            </div>
                                        </div>
                                    </button>

                                    <div className="flex items-center justify-between gap-2 border-t px-4 py-3">
                                        <Button type="button" variant="ghost" size="sm" onClick={duplicateSelectedSchedule} disabled={!isSelected}>
                                            Duplicate
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            className="text-destructive"
                                            onClick={() => removeSchedule(index)}
                                            disabled={schedules.length <= 1}
                                        >
                                            <X className="mr-1 h-3.5 w-3.5" />
                                            Remove
                                        </Button>
                                    </div>
                                </div>
                            );
                        })}

                        <Button
                            type="button"
                            variant="outline"
                            className="w-full"
                            onClick={() => setSchedules(schedules.map((schedule) => ({ ...schedule, room_id: classRoomId || defaultRoomId })))}
                        >
                            Use class room for all blocks
                        </Button>
                    </div>
                </div>

                {selectedSchedule ? (
                    <div className="bg-card rounded-xl border shadow-sm">
                        <div className="border-b p-4">
                            <h3 className="text-sm font-semibold">Block editor</h3>
                            <p className="text-muted-foreground text-xs">Fine-tune the selected schedule block.</p>
                        </div>

                        <div className="grid gap-4 p-4">
                            <div className="space-y-2">
                                <Label>Day</Label>
                                <Select
                                    value={selectedSchedule.day_of_week}
                                    onValueChange={(value) => updateScheduleAt(selectedIndex, { day_of_week: value })}
                                >
                                    <SelectTrigger className="w-full">
                                        <SelectValue placeholder="Select day" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {dayOptions.map((option) => (
                                            <SelectItem key={option.value} value={option.value}>
                                                {option.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="space-y-2">
                                    <Label>Start time</Label>
                                    <Input
                                        type="time"
                                        value={selectedSchedule.start_time}
                                        onChange={(event) => updateScheduleAt(selectedIndex, { start_time: event.target.value })}
                                    />
                                </div>

                                <div className="space-y-2">
                                    <Label>End time</Label>
                                    <Input
                                        type="time"
                                        value={selectedSchedule.end_time}
                                        onChange={(event) => updateScheduleAt(selectedIndex, { end_time: event.target.value })}
                                    />
                                </div>
                            </div>

                            <div className="space-y-2">
                                <Label>Room</Label>
                                <Select
                                    value={String(selectedSchedule.room_id)}
                                    onValueChange={(value) => updateScheduleAt(selectedIndex, { room_id: Number(value) })}
                                >
                                    <SelectTrigger className="w-full">
                                        <SelectValue placeholder="Select room" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {rooms.map((room) => (
                                            <SelectItem key={room.id} value={String(room.id)}>
                                                {room.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="bg-muted/40 rounded-lg border px-3 py-2 text-xs">
                                <span className="font-medium">Summary:</span> {selectedSchedule.day_of_week} •{" "}
                                {formatTimeLabel(selectedSchedule.start_time)} to {formatTimeLabel(selectedSchedule.end_time)} •{" "}
                                {roomLabelById.get(selectedSchedule.room_id) ?? "Room TBA"}
                            </div>
                        </div>
                    </div>
                ) : null}
            </div>
        </div>
    );
}

export default function AdministratorClassesIndex({ user, classes, selected_class, filters, options, defaults }: ClassesIndexProps) {
    const [search, setSearch] = React.useState(filters.search || "");
    const [viewMode, setViewMode] = React.useState<"grid" | "list">("grid");
    const [localClassification, setLocalClassification] = React.useState(filters.classification || "all");
    const [isCreateOpen, setIsCreateOpen] = React.useState(false);
    const [isEditOpen, setIsEditOpen] = React.useState(false);
    const [isCopyOpen, setIsCopyOpen] = React.useState(false);
    const [isManageOpen, setIsManageOpen] = React.useState(false);
    const [isFiltersOpen, setIsFiltersOpen] = React.useState(false);
    const [createActiveTab, setCreateActiveTab] = React.useState<ClassDialogTab>("details");
    const [editActiveTab, setEditActiveTab] = React.useState<ClassDialogTab>("details");
    const [copySourceId, setCopySourceId] = React.useState<number | null>(null);
    const [copySection, setCopySection] = React.useState("A");
    const [pendingDelete, setPendingDelete] = React.useState<ClassRow | null>(null);

    const [collegeSubjectOptions, setCollegeSubjectOptions] = React.useState<{ id: number; label: string; code: string; title: string }[]>([]);
    const [collegeSubjectsLoading, setCollegeSubjectsLoading] = React.useState(false);

    const [shsStrandOptions, setShsStrandOptions] = React.useState<EntityOption[]>([]);
    const [shsStrandsLoading, setShsStrandsLoading] = React.useState(false);

    const [shsSubjectOptions, setShsSubjectOptions] = React.useState<{ code: string; label: string; title: string }[]>([]);
    const [shsSubjectsLoading, setShsSubjectsLoading] = React.useState(false);

    const [subjectCodeTouched, setSubjectCodeTouched] = React.useState(false);

    const filteredClassesByClassification = React.useMemo(() => {
        if (localClassification === "all") {
            return classes.data;
        }
        return classes.data.filter((cls) => cls.classification?.toLowerCase() === localClassification.toLowerCase());
    }, [classes.data, localClassification]);

    const handleSearch = useDebouncedCallback((term: string) => {
        router.get(route("administrators.classes.index"), { ...filters, search: term }, { preserveState: true, replace: true });
    }, 300);

    const handleFilterChange = (key: string, value: string | number | boolean | null) => {
        router.get(
            route("administrators.classes.index"),
            {
                ...filters,
                [key]: value === "all" ? null : value,
            },
            { preserveState: true, replace: true },
        );
    };

    const openManage = (classId: number) => {
        setIsManageOpen(true);

        router.get(
            route("administrators.classes.index"),
            { ...filters, selected: classId },
            {
                preserveState: true,
                replace: true,
                only: ["selected_class"],
            },
        );
    };

    const openEdit = (classId: number) => {
        setIsEditOpen(true);
        setEditActiveTab("details");

        router.get(
            route("administrators.classes.index"),
            { ...filters, selected: classId },
            {
                preserveState: true,
                replace: true,
                only: ["selected_class"],
            },
        );
    };

    const confirmDelete = (row: ClassRow) => {
        setPendingDelete(row);
    };

    const performDelete = () => {
        if (!pendingDelete) return;

        router.delete(route("administrators.classes.destroy", { class: pendingDelete.id }), {
            preserveScroll: true,
            onSuccess: () => {
                setPendingDelete(null);
                setIsManageOpen(false);
            },
        });
    };

    const columns = React.useMemo(
        () =>
            getColumns({
                onManage: openManage,
                onEdit: openEdit,
                onDelete: confirmDelete,
                onCopy: (id) => {
                    setCopySourceId(id);
                    setCopySection("A");
                    setIsCopyOpen(true);
                },
            }),
        [openManage, openEdit, setIsCopyOpen],
    );

    const createForm = useForm({
        classification: "college" as "college" | "shs",
        course_codes: [] as number[],
        subject_ids: [] as number[],
        subject_code: "",
        academic_year: 1,

        shs_track_id: null as number | null,
        shs_strand_id: null as number | null,
        subject_code_shs: "",
        grade_level: "Grade 11",

        faculty_id: null as string | null,
        semester: defaults.semester,
        school_year: defaults.school_year,
        section: "A",
        room_id: options.rooms[0]?.id ?? 0,
        maximum_slots: 40,

        schedules: [createDefaultSchedule(options.rooms[0]?.id ?? 0)] as ClassSchedule[],

        settings: {
            background_color: "#ffffff",
            accent_color: "#3b82f6",
            theme: "default",
            enable_announcements: true,
            enable_grade_visibility: true,
            enable_attendance_tracking: false,
            allow_late_submissions: false,
            enable_discussion_board: false,
            custom: {} as Record<string, string>,
            banner_image: null as File | null,
        },
    });

    const editForm = useForm({
        classification: "college" as "college" | "shs",
        course_codes: [] as number[],
        subject_ids: [] as number[],
        subject_code: "",
        academic_year: 1 as number | null,

        shs_track_id: null as number | null,
        shs_strand_id: null as number | null,
        subject_code_shs: "",
        grade_level: "Grade 11" as string | null,

        faculty_id: null as string | null,
        semester: defaults.semester,
        school_year: defaults.school_year,
        section: "A",
        room_id: options.rooms[0]?.id ?? 0,
        maximum_slots: 40,

        schedules: [] as ClassSchedule[],

        settings: {
            background_color: "#ffffff",
            accent_color: "#3b82f6",
            theme: "default",
            enable_announcements: true,
            enable_grade_visibility: true,
            enable_attendance_tracking: false,
            allow_late_submissions: false,
            enable_discussion_board: false,
            custom: {} as Record<string, string>,
            banner_image: null as File | null,
        },

        remove_banner_image: false,
    });

    React.useEffect(() => {
        if (!isEditOpen || !selected_class) return;

        editForm.setData({
            classification: (selected_class.classification as "college" | "shs") ?? "college",
            course_codes: selected_class.course_ids ?? [],
            subject_ids: selected_class.subject_ids ?? [],
            subject_code: selected_class.subject_code ?? "",
            academic_year: selected_class.academic_year ?? null,

            shs_track_id: selected_class.shs_track?.id ?? null,
            shs_strand_id: selected_class.shs_strand?.id ?? null,
            subject_code_shs: selected_class.classification === "shs" ? selected_class.subject_code : "",
            grade_level: selected_class.grade_level ?? null,

            faculty_id: selected_class.faculty?.id ?? null,
            semester: normalizeSemester(selected_class.semester),
            school_year: selected_class.school_year,
            section: selected_class.section,
            room_id: selected_class.room?.id ?? options.rooms[0]?.id ?? 0,
            maximum_slots: selected_class.maximum_slots,

            schedules: selected_class.schedules.map((s) => ({
                day_of_week: s.day_of_week,
                start_time: s.start_time,
                end_time: s.end_time,
                room_id: s.room_id,
            })),

            settings: {
                background_color: selected_class.settings.background_color ?? "#ffffff",
                accent_color: selected_class.settings.accent_color ?? "#3b82f6",
                theme: selected_class.settings.theme ?? "default",
                enable_announcements: selected_class.settings.enable_announcements ?? true,
                enable_grade_visibility: selected_class.settings.enable_grade_visibility ?? true,
                enable_attendance_tracking: selected_class.settings.enable_attendance_tracking ?? false,
                allow_late_submissions: selected_class.settings.allow_late_submissions ?? false,
                enable_discussion_board: selected_class.settings.enable_discussion_board ?? false,
                custom: selected_class.settings.custom ?? {},
                banner_image: null,
            },
            remove_banner_image: false,
        });

        setSubjectCodeTouched(false);
        void loadCollegeSubjects(selected_class.course_ids ?? [], "edit");
        void loadShsStrands(selected_class.shs_track?.id ?? null, "edit");
        void loadShsSubjects(selected_class.shs_strand?.id ?? null);
    }, [isEditOpen, selected_class]);

    const createFirstError = getFirstFormError(createForm.errors);
    const editFirstError = getFirstFormError(editForm.errors);

    const loadCollegeSubjects = async (courseIds: number[], target: "create" | "edit") => {
        if (courseIds.length === 0) {
            setCollegeSubjectOptions([]);
            if (target === "create") {
                createForm.setData("subject_ids", []);
                if (!subjectCodeTouched) {
                    createForm.setData("subject_code", "");
                }
            }
            if (target === "edit") {
                editForm.setData("subject_ids", []);
                if (!subjectCodeTouched) {
                    editForm.setData("subject_code", "");
                }
            }
            return;
        }

        setCollegeSubjectsLoading(true);

        try {
            const response = await fetch(
                route("administrators.classes.options.subjects", {
                    course_ids: courseIds,
                }),
            );
            const data = (await response.json()) as {
                data: { id: number; label: string; code: string; title: string }[];
            };

            setCollegeSubjectOptions(data.data);

            const availableSubjectIds = new Set(data.data.map((subject) => subject.id));

            if (target === "create" && !subjectCodeTouched) {
                const validSubjectIds = createForm.data.subject_ids.filter((id) => availableSubjectIds.has(id));

                if (validSubjectIds.length !== createForm.data.subject_ids.length) {
                    createForm.setData("subject_ids", validSubjectIds);
                }

                const computed = buildSubjectCodeFromSubjectOptions(validSubjectIds.map(String), data.data);
                if (computed) {
                    createForm.setData("subject_code", computed);
                } else {
                    createForm.setData("subject_code", "");
                }
            }

            if (target === "edit" && !subjectCodeTouched) {
                const validSubjectIds = editForm.data.subject_ids.filter((id) => availableSubjectIds.has(id));

                if (validSubjectIds.length !== editForm.data.subject_ids.length) {
                    editForm.setData("subject_ids", validSubjectIds);
                }

                const computed = buildSubjectCodeFromSubjectOptions(validSubjectIds.map(String), data.data);
                if (computed) {
                    editForm.setData("subject_code", computed);
                } else {
                    editForm.setData("subject_code", "");
                }
            }
        } finally {
            setCollegeSubjectsLoading(false);
        }
    };

    const loadShsStrands = async (trackId: number | null, target: "create" | "edit") => {
        if (!trackId) {
            setShsStrandOptions([]);
            if (target === "create") createForm.setData("shs_strand_id", null);
            if (target === "edit") editForm.setData("shs_strand_id", null);
            return;
        }

        setShsStrandsLoading(true);

        try {
            const response = await fetch(
                route("administrators.classes.options.shs-strands", {
                    track_id: trackId,
                }),
            );
            const data = (await response.json()) as { data: EntityOption[] };
            setShsStrandOptions(data.data);
        } finally {
            setShsStrandsLoading(false);
        }
    };

    const loadShsSubjects = async (strandId: number | null) => {
        if (!strandId) {
            setShsSubjectOptions([]);
            return;
        }

        setShsSubjectsLoading(true);

        try {
            const response = await fetch(
                route("administrators.classes.options.shs-subjects", {
                    strand_id: strandId,
                }),
            );
            const data = (await response.json()) as {
                data: { code: string; label: string; title: string }[];
            };
            setShsSubjectOptions(data.data);
        } finally {
            setShsSubjectsLoading(false);
        }
    };

    const settingsEditor = ({
        settings,
        setSettings,
        removeBannerImage,
        setRemoveBannerImage,
    }: {
        settings: ClassSettingsFormData;
        setSettings: (nextSettings: ClassSettingsFormData) => void;
        removeBannerImage?: boolean;
        setRemoveBannerImage?: (value: boolean) => void;
    }) => {
        const customEntries = Object.entries(settings.custom);

        const toggles: Array<{
            key: keyof Pick<
                ClassSettingsFormData,
                | "enable_announcements"
                | "enable_grade_visibility"
                | "enable_attendance_tracking"
                | "allow_late_submissions"
                | "enable_discussion_board"
            >;
            label: string;
            desc: string;
        }> = [
            {
                key: "enable_announcements",
                label: "Enable announcements",
                desc: "Allow faculty to post announcements visible to all enrolled students.",
            },
            { key: "enable_grade_visibility", label: "Show grades to students", desc: "Students can view their grades and feedback securely." },
            { key: "enable_attendance_tracking", label: "Track attendance", desc: "Enable logging and charting of student attendance patterns." },
            { key: "allow_late_submissions", label: "Allow late submissions", desc: "Permit students to submit assignments after the deadlines." },
            {
                key: "enable_discussion_board",
                label: "Enable discussion board",
                desc: "Open a forum for students and faculty to discuss subject matter.",
            },
        ];

        return (
            <div className="grid gap-4">
                <div className="grid gap-3 sm:grid-cols-2">
                    <div className="space-y-2">
                        <Label>Background color</Label>
                        <Input
                            type="color"
                            value={settings.background_color}
                            onChange={(e) => setSettings({ ...settings, background_color: e.target.value })}
                        />
                    </div>

                    <div className="space-y-2">
                        <Label>Accent color</Label>
                        <Input
                            type="color"
                            value={settings.accent_color}
                            onChange={(e) => setSettings({ ...settings, accent_color: e.target.value })}
                        />
                    </div>
                </div>

                <div className="mt-2 space-y-3">
                    <Label className="flex items-center gap-2">
                        <Palette className="text-primary h-4 w-4" /> Theme & Branding
                    </Label>
                    <div className="grid gap-3 sm:grid-cols-3">
                        {[
                            { value: "default", label: "Default", desc: "Standard clean look." },
                            { value: "modern", label: "Modern", desc: "Contemporary and bold elements." },
                            { value: "classic", label: "Classic", desc: "Traditional academic styling." },
                            { value: "minimal", label: "Minimal", desc: "Distraction-free interface." },
                            { value: "vibrant", label: "Vibrant", desc: "Colorful and highly energetic." },
                        ].map((opt) => (
                            <VisualRadioButton
                                key={opt.value}
                                title={opt.label}
                                description={opt.desc}
                                checked={settings.theme === opt.value}
                                onSelect={() => setSettings({ ...settings, theme: opt.value })}
                                className="col-span-1"
                            />
                        ))}
                    </div>
                </div>

                <div className="mt-4 space-y-3">
                    <Label className="flex items-center gap-2">
                        <Layers className="text-primary h-4 w-4" /> Features & Attributes
                    </Label>
                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        {toggles.map((toggle) => (
                            <VisualRadioButton
                                key={toggle.key}
                                title={toggle.label}
                                description={toggle.desc}
                                checked={settings[toggle.key]}
                                onSelect={() =>
                                    setSettings({
                                        ...settings,
                                        [toggle.key]: !settings[toggle.key],
                                    } as ClassSettingsFormData)
                                }
                                className="col-span-1"
                            />
                        ))}
                    </div>
                </div>

                <div className="grid gap-3 rounded-lg border p-4">
                    <div className="flex items-center justify-between">
                        <div>
                            <div className="text-foreground text-sm font-medium">Banner image</div>
                            <div className="text-muted-foreground text-xs">Upload a class banner (optional).</div>
                        </div>
                        {setRemoveBannerImage ? (
                            <label className="flex items-center gap-2 text-sm">
                                <Checkbox
                                    checked={Boolean(removeBannerImage)}
                                    onCheckedChange={(checked) => setRemoveBannerImage(Boolean(checked))}
                                />
                                Remove
                            </label>
                        ) : null}
                    </div>
                    <Input
                        type="file"
                        accept="image/*"
                        onChange={(e) => {
                            const file = e.target.files?.[0] ?? null;
                            setSettings({ ...settings, banner_image: file });
                        }}
                    />
                </div>

                <div className="grid gap-3 rounded-lg border p-4">
                    <div>
                        <div className="text-foreground text-sm font-medium">Custom preferences</div>
                        <div className="text-muted-foreground text-xs">Optional key/value settings.</div>
                    </div>

                    <div className="grid gap-2">
                        {customEntries.length === 0 ? (
                            <div className="text-muted-foreground text-sm">No custom settings.</div>
                        ) : (
                            customEntries.map(([key, value]) => (
                                <div key={key} className="grid gap-2 sm:grid-cols-5">
                                    <Input className="sm:col-span-2" value={key} disabled />
                                    <Input
                                        className="sm:col-span-2"
                                        value={value}
                                        onChange={(e) => {
                                            setSettings({
                                                ...settings,
                                                custom: { ...settings.custom, [key]: e.target.value },
                                            });
                                        }}
                                    />
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        className="text-destructive"
                                        onClick={() => {
                                            const next = { ...settings.custom };
                                            delete next[key];
                                            setSettings({ ...settings, custom: next });
                                        }}
                                    >
                                        Remove
                                    </Button>
                                </div>
                            ))
                        )}

                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => {
                                const next = { ...settings.custom };
                                const keyBase = "setting";
                                let i = 1;
                                while (next[`${keyBase}_${i}`] !== undefined) i++;
                                next[`${keyBase}_${i}`] = "";
                                setSettings({ ...settings, custom: next });
                            }}
                        >
                            Add custom setting
                        </Button>
                    </div>
                </div>
            </div>
        );
    };

    return (
        <AdminLayout user={user} title="Classes">
            <Head title="Administrators • Classes" />

            {(() => {
                const courseLabelById = new Map(options.courses.map((course) => [course.id, course.label]));
                const roomLabelById = new Map(options.rooms.map((room) => [room.id, room.label]));
                const facultyLabelById = new Map(options.faculties.map((faculty) => [faculty.id, faculty.label]));
                const semesterLabelByValue = new Map(options.semesters.map((semester) => [semester.value, semester.label]));

                const activeFilterBadges: Array<{
                    key: string;
                    label: string;
                    onClear: () => void;
                }> = [];

                if (localClassification && localClassification !== "all") {
                    const label = options.classifications.find((c) => c.value === localClassification)?.label ?? localClassification;

                    activeFilterBadges.push({
                        key: "classification",
                        label: `Type: ${label}`,
                        onClear: () => setLocalClassification("all"),
                    });
                }

                if (filters.course_id) {
                    activeFilterBadges.push({
                        key: "course_id",
                        label: `Course: ${courseLabelById.get(filters.course_id) ?? filters.course_id}`,
                        onClear: () => handleFilterChange("course_id", null),
                    });
                }

                if (filters.faculty_id) {
                    activeFilterBadges.push({
                        key: "faculty_id",
                        label: `Faculty: ${facultyLabelById.get(filters.faculty_id) ?? filters.faculty_id}`,
                        onClear: () => handleFilterChange("faculty_id", null),
                    });
                }

                if (filters.room_id) {
                    activeFilterBadges.push({
                        key: "room_id",
                        label: `Room: ${roomLabelById.get(filters.room_id) ?? filters.room_id}`,
                        onClear: () => handleFilterChange("room_id", null),
                    });
                }

                if (filters.semester) {
                    activeFilterBadges.push({
                        key: "semester",
                        label: `Semester: ${semesterLabelByValue.get(filters.semester) ?? filters.semester}`,
                        onClear: () => handleFilterChange("semester", null),
                    });
                }

                if (filters.academic_year) {
                    activeFilterBadges.push({
                        key: "academic_year",
                        label: `Year: ${filters.academic_year}`,
                        onClear: () => handleFilterChange("academic_year", null),
                    });
                }

                if (filters.grade_level) {
                    activeFilterBadges.push({
                        key: "grade_level",
                        label: `Grade: ${filters.grade_level}`,
                        onClear: () => handleFilterChange("grade_level", null),
                    });
                }

                if (filters.available_slots) {
                    activeFilterBadges.push({
                        key: "available_slots",
                        label: "Has available slots",
                        onClear: () => handleFilterChange("available_slots", null),
                    });
                }

                if (filters.fully_enrolled) {
                    activeFilterBadges.push({
                        key: "fully_enrolled",
                        label: "Fully enrolled",
                        onClear: () => handleFilterChange("fully_enrolled", null),
                    });
                }

                const hasActiveFilters = activeFilterBadges.length > 0;

                const clearAll = () => {
                    setSearch("");
                    setLocalClassification("all");
                    router.get(route("administrators.classes.index"), {}, { replace: true });
                };

                const filteredStatsTotalStudents = filteredClassesByClassification.reduce((acc, curr) => acc + curr.students_count, 0);
                const filteredStatsTotalClasses = localClassification === "all" ? classes.total : filteredClassesByClassification.length;

                return (
                    <div className="flex flex-col gap-6">
                        <div className="flex flex-col gap-4">
                            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <div className="min-w-0">
                                    <h2 className="text-foreground text-3xl font-bold tracking-tight">Classes</h2>
                                    <p className="text-muted-foreground text-sm">
                                        Quick-edit, duplicate, or remove classes. Click any class title to open the editor.
                                    </p>
                                </div>
                                <div className="flex items-center gap-2">
                                    <Button variant="outline" size="sm" onClick={() => setIsFiltersOpen(true)}>
                                        <SlidersHorizontal className="mr-2 h-4 w-4" />
                                        Filters
                                        {hasActiveFilters ? (
                                            <Badge variant="secondary" className="ml-2 h-5 px-1.5">
                                                {activeFilterBadges.length}
                                            </Badge>
                                        ) : null}
                                    </Button>
                                    <Button size="sm" onClick={() => setIsCreateOpen(true)}>
                                        <Plus className="mr-1.5 h-4 w-4" />
                                        <span className="hidden sm:inline">New class</span>
                                        <span className="sm:hidden">New</span>
                                    </Button>
                                </div>
                            </div>

                            <StatsOverview totalClasses={filteredStatsTotalClasses} totalStudents={filteredStatsTotalStudents} />
                        </div>

                        <div className="bg-card flex flex-col gap-4 rounded-lg border p-4 shadow-sm md:flex-row md:items-center md:justify-between">
                            <div className="relative max-w-sm flex-1">
                                <Search className="text-muted-foreground absolute top-2.5 left-2.5 h-4 w-4" />
                                <Input
                                    placeholder="Search classes..."
                                    className="pl-8"
                                    value={search}
                                    onChange={(e) => {
                                        setSearch(e.target.value);
                                        handleSearch(e.target.value);
                                    }}
                                />
                            </div>

                            <div className="flex items-center gap-2 overflow-x-auto pb-2 md:pb-0">
                                <Tabs value={localClassification} onValueChange={(val) => setLocalClassification(val)} className="w-auto">
                                    <TabsList>
                                        <TabsTrigger value="all">All</TabsTrigger>
                                        <TabsTrigger value="college">College</TabsTrigger>
                                        <TabsTrigger value="shs">SHS</TabsTrigger>
                                    </TabsList>
                                </Tabs>

                                <div className="bg-border mx-2 h-6 w-px" />

                                <div className="bg-background flex items-center rounded-md border p-1">
                                    <Button
                                        variant={viewMode === "grid" ? "secondary" : "ghost"}
                                        size="icon"
                                        className="h-7 w-7"
                                        onClick={() => setViewMode("grid")}
                                        title="Grid view"
                                    >
                                        <LayoutGrid className="h-4 w-4" />
                                    </Button>
                                    <Button
                                        variant={viewMode === "list" ? "secondary" : "ghost"}
                                        size="icon"
                                        className="h-7 w-7"
                                        onClick={() => setViewMode("list")}
                                        title="List view"
                                    >
                                        <List className="h-4 w-4" />
                                    </Button>
                                </div>
                            </div>
                        </div>

                        {hasActiveFilters ? (
                            <div className="flex flex-wrap items-center gap-2">
                                <span className="text-muted-foreground text-sm">Active filters:</span>
                                {activeFilterBadges.map((badge) => (
                                    <Badge key={badge.key} variant="secondary" className="flex items-center gap-1">
                                        <span>{badge.label}</span>
                                        <button
                                            type="button"
                                            className="hover:bg-muted ml-1 inline-flex items-center rounded-sm p-0.5"
                                            onClick={badge.onClear}
                                            aria-label={`Remove ${badge.label}`}
                                        >
                                            <X className="h-3 w-3" />
                                        </button>
                                    </Badge>
                                ))}

                                <Button type="button" variant="ghost" size="sm" onClick={clearAll}>
                                    Clear all
                                </Button>
                            </div>
                        ) : null}

                        {filteredClassesByClassification.length === 0 ? (
                            <div className="bg-muted/10 animate-in fade-in zoom-in-95 flex min-h-[300px] flex-col items-center justify-center rounded-lg border border-dashed p-8 text-center duration-300">
                                <div className="bg-muted rounded-full p-4">
                                    <Search className="text-muted-foreground h-8 w-8" />
                                </div>
                                <h3 className="mt-4 text-lg font-semibold">No classes found</h3>
                                <p className="text-muted-foreground mt-2 max-w-sm text-sm">
                                    We couldn't find any classes matching your search or filters. Try adjusting them or create a new class.
                                </p>
                                <Button variant="outline" className="mt-6" onClick={clearAll}>
                                    Clear filters
                                </Button>
                            </div>
                        ) : (
                            <>
                                {viewMode === "grid" ? (
                                    <div className="animate-in fade-in slide-in-from-bottom-4 grid gap-4 duration-500 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                                        {filteredClassesByClassification.map((row) => (
                                            <ClassCard
                                                key={row.id}
                                                classRow={row}
                                                onManage={openManage}
                                                onEdit={openEdit}
                                                onDelete={confirmDelete}
                                                onCopy={(id) => {
                                                    setCopySourceId(id);
                                                    setCopySection("A");
                                                    setIsCopyOpen(true);
                                                }}
                                            />
                                        ))}
                                    </div>
                                ) : (
                                    <DataTable columns={columns} data={filteredClassesByClassification} pagination={undefined} filters={filters} />
                                )}

                                {viewMode === "grid" && (
                                    <div className="flex items-center justify-between gap-3 border-t pt-4">
                                        <div className="text-muted-foreground text-sm">
                                            Showing {filteredClassesByClassification.length}{" "}
                                            {filteredClassesByClassification.length === 1 ? "class" : "classes"}
                                            {localClassification !== "all" && ` (filtered from ${classes.total} total)`}
                                        </div>
                                    </div>
                                )}
                            </>
                        )}

                        <Sheet open={isFiltersOpen} onOpenChange={setIsFiltersOpen}>
                            <SheetContent side="right" className="w-full sm:max-w-lg">
                                <SheetHeader>
                                    <SheetTitle>Filters</SheetTitle>
                                    <SheetDescription>Refine the class list.</SheetDescription>
                                </SheetHeader>

                                <div className="flex flex-1 flex-col gap-4 overflow-auto px-4">
                                    <div className="grid gap-3 sm:grid-cols-2">
                                        <div className="space-y-2">
                                            <Label>Course</Label>
                                            <Select
                                                value={filters.course_id ? String(filters.course_id) : "all"}
                                                onValueChange={(val) => handleFilterChange("course_id", val === "all" ? null : Number(val))}
                                                disabled={filters.classification === "shs"}
                                            >
                                                <SelectTrigger className="w-full">
                                                    <SelectValue placeholder="Course" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="all">All courses</SelectItem>
                                                    {options.courses.map((course) => (
                                                        <SelectItem key={course.id} value={String(course.id)}>
                                                            {course.label}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        </div>

                                        <div className="space-y-2">
                                            <Label>Faculty</Label>
                                            <Select
                                                value={filters.faculty_id ? String(filters.faculty_id) : "all"}
                                                onValueChange={(val) => handleFilterChange("faculty_id", val === "all" ? null : Number(val))}
                                            >
                                                <SelectTrigger className="w-full">
                                                    <SelectValue placeholder="Faculty" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="all">All faculty</SelectItem>
                                                    {options.faculties.map((faculty) => (
                                                        <SelectItem key={faculty.id} value={String(faculty.id)}>
                                                            {faculty.label}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        </div>

                                        <div className="space-y-2">
                                            <Label>Room</Label>
                                            <Select
                                                value={filters.room_id ? String(filters.room_id) : "all"}
                                                onValueChange={(val) => handleFilterChange("room_id", val === "all" ? null : Number(val))}
                                            >
                                                <SelectTrigger className="w-full">
                                                    <SelectValue placeholder="Room" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="all">All rooms</SelectItem>
                                                    {options.rooms.map((room) => (
                                                        <SelectItem key={room.id} value={String(room.id)}>
                                                            {room.label}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        </div>

                                        <div className="space-y-2">
                                            <Label>Semester</Label>
                                            <Select
                                                value={filters.semester ?? "all"}
                                                onValueChange={(val) => handleFilterChange("semester", val === "all" ? null : val)}
                                            >
                                                <SelectTrigger className="w-full">
                                                    <SelectValue placeholder="Semester" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="all">All semesters</SelectItem>
                                                    {options.semesters.map((semester) => (
                                                        <SelectItem key={semester.value} value={semester.value}>
                                                            {semester.label}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        </div>

                                        <div className="space-y-2">
                                            <Label>College year</Label>
                                            <Select
                                                value={filters.academic_year ? String(filters.academic_year) : "all"}
                                                onValueChange={(val) => handleFilterChange("academic_year", val === "all" ? null : Number(val))}
                                                disabled={filters.classification === "shs"}
                                            >
                                                <SelectTrigger className="w-full">
                                                    <SelectValue placeholder="Year" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="all">All college years</SelectItem>
                                                    {[1, 2, 3, 4].map((year) => (
                                                        <SelectItem key={year} value={String(year)}>
                                                            {year} year
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        </div>

                                        <div className="space-y-2">
                                            <Label>SHS grade</Label>
                                            <Select
                                                value={filters.grade_level ?? "all"}
                                                onValueChange={(val) => handleFilterChange("grade_level", val === "all" ? null : val)}
                                                disabled={filters.classification === "college"}
                                            >
                                                <SelectTrigger className="w-full">
                                                    <SelectValue placeholder="Grade" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="all">All SHS grades</SelectItem>
                                                    {options.grade_levels.map((grade) => (
                                                        <SelectItem key={grade.value} value={grade.value}>
                                                            {grade.label}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        </div>
                                    </div>

                                    <div className="grid gap-3 rounded-lg border p-4">
                                        <div className="text-foreground text-sm font-medium">Enrollment</div>
                                        <label className="flex items-center justify-between gap-3 text-sm">
                                            <span className="text-muted-foreground">Has available slots</span>
                                            <Checkbox
                                                checked={Boolean(filters.available_slots)}
                                                onCheckedChange={(checked) => handleFilterChange("available_slots", checked ? true : null)}
                                            />
                                        </label>
                                        <label className="flex items-center justify-between gap-3 text-sm">
                                            <span className="text-muted-foreground">Fully enrolled only</span>
                                            <Checkbox
                                                checked={filters.fully_enrolled === true}
                                                onCheckedChange={(checked) => handleFilterChange("fully_enrolled", checked ? true : null)}
                                            />
                                        </label>
                                    </div>
                                </div>

                                <SheetFooter>
                                    <Button variant="outline" onClick={clearAll} disabled={!hasActiveFilters && search === ""}>
                                        Clear
                                    </Button>
                                    <Button onClick={() => setIsFiltersOpen(false)}>Done</Button>
                                </SheetFooter>
                            </SheetContent>
                        </Sheet>
                    </div>
                );
            })()}

            <Dialog open={isCopyOpen} onOpenChange={setIsCopyOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Copy class</DialogTitle>
                        <DialogDescription>Creates a new class with the same data (schedules not copied).</DialogDescription>
                    </DialogHeader>

                    <div className="space-y-2">
                        <Label>New section</Label>
                        <Select value={copySection} onValueChange={setCopySection}>
                            <SelectTrigger className="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {options.sections.map((sec) => (
                                    <SelectItem key={sec.value} value={sec.value}>
                                        {sec.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    <DialogFooter>
                        <Button variant="outline" onClick={() => setIsCopyOpen(false)}>
                            Cancel
                        </Button>
                        <Button
                            onClick={() => {
                                if (!copySourceId) return;
                                router.post(
                                    route("administrators.classes.copy", { class: copySourceId }),
                                    { section: copySection },
                                    { preserveScroll: true, onSuccess: () => setIsCopyOpen(false) },
                                );
                            }}
                        >
                            Copy
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog
                open={isCreateOpen}
                onOpenChange={(open) => {
                    setIsCreateOpen(open);
                    if (open) {
                        setCreateActiveTab("details");
                    }
                }}
            >
                <DialogContent className="bg-background/95 supports-[backdrop-filter]:bg-background/60 flex max-h-[95vh] w-full flex-col gap-0 overflow-hidden p-0 backdrop-blur sm:max-w-3xl md:max-w-5xl lg:max-w-[90vw] xl:max-w-[1400px]">
                    <DialogHeader className="bg-muted/20 border-b p-6 pb-4">
                        <DialogTitle className="text-xl font-bold">Create class</DialogTitle>
                        <DialogDescription>Build a class record, assign schedules, and configure settings.</DialogDescription>
                    </DialogHeader>

                    <Tabs
                        value={createActiveTab}
                        onValueChange={(value) => setCreateActiveTab(value as ClassDialogTab)}
                        className="flex flex-1 flex-col overflow-hidden"
                    >
                        <div className="px-6 pt-4">
                            <TabsList className="h-12 w-full justify-start gap-6 rounded-none border-b bg-transparent p-0">
                                <TabsTrigger
                                    value="details"
                                    className="data-[state=active]:border-primary text-muted-foreground data-[state=active]:text-foreground h-12 rounded-none px-2 data-[state=active]:border-b-2 data-[state=active]:bg-transparent data-[state=active]:shadow-none"
                                >
                                    <ListTodo className="mr-2 h-4 w-4" />
                                    Details
                                </TabsTrigger>
                                <TabsTrigger
                                    value="schedule"
                                    className="data-[state=active]:border-primary text-muted-foreground data-[state=active]:text-foreground h-12 rounded-none px-2 data-[state=active]:border-b-2 data-[state=active]:bg-transparent data-[state=active]:shadow-none"
                                >
                                    <CalendarIcon className="mr-2 h-4 w-4" />
                                    Schedule
                                </TabsTrigger>
                                <TabsTrigger
                                    value="settings"
                                    className="data-[state=active]:border-primary text-muted-foreground data-[state=active]:text-foreground h-12 rounded-none px-2 data-[state=active]:border-b-2 data-[state=active]:bg-transparent data-[state=active]:shadow-none"
                                >
                                    <Settings2 className="mr-2 h-4 w-4" />
                                    Settings
                                </TabsTrigger>
                            </TabsList>
                        </div>

                        <div className="flex-1 overflow-y-auto p-6 pt-6">
                            {createFirstError ? (
                                <div className="border-destructive/40 bg-destructive/5 text-destructive mb-6 rounded-lg border px-4 py-3 text-sm">
                                    {createFirstError}
                                </div>
                            ) : null}
                            <TabsContent value="details" className="m-0 space-y-6 outline-none">
                                <div className="grid gap-6 lg:grid-cols-2">
                                    <Card className="border-border/60 shadow-sm">
                                        <CardHeader className="bg-muted/20 border-b pb-4">
                                            <CardTitle className="text-base font-semibold">Academic Profile</CardTitle>
                                        </CardHeader>
                                        <CardContent className="space-y-4 pt-6">
                                            <div className="grid gap-4 sm:grid-cols-2">
                                                <div className="space-y-3 sm:col-span-2">
                                                    <Label>Class type</Label>
                                                    <div className="grid gap-3 sm:grid-cols-2">
                                                        <VisualRadioButton
                                                            title="College"
                                                            description="Higher education degrees and courses."
                                                            checked={createForm.data.classification === "college"}
                                                            onSelect={() => {
                                                                createForm.setData("classification", "college");
                                                                createForm.setData("course_codes", []);
                                                                createForm.setData("subject_ids", []);
                                                                createForm.setData("subject_code", "");
                                                                createForm.setData("shs_track_id", null);
                                                                createForm.setData("shs_strand_id", null);
                                                                createForm.setData("subject_code_shs", "");
                                                                setCollegeSubjectOptions([]);
                                                                setShsStrandOptions([]);
                                                                setShsSubjectOptions([]);
                                                                setSubjectCodeTouched(false);
                                                            }}
                                                        />
                                                        <VisualRadioButton
                                                            title="Senior High School"
                                                            description="K-12 pathway and strands."
                                                            checked={createForm.data.classification === "shs"}
                                                            onSelect={() => {
                                                                createForm.setData("classification", "shs");
                                                                createForm.setData("course_codes", []);
                                                                createForm.setData("subject_ids", []);
                                                                createForm.setData("subject_code", "");
                                                                createForm.setData("shs_track_id", null);
                                                                createForm.setData("shs_strand_id", null);
                                                                createForm.setData("subject_code_shs", "");
                                                                setCollegeSubjectOptions([]);
                                                                setShsStrandOptions([]);
                                                                setShsSubjectOptions([]);
                                                                setSubjectCodeTouched(false);
                                                            }}
                                                        />
                                                    </div>
                                                </div>

                                                {createForm.data.classification === "college" ? (
                                                    <>
                                                        <div className="space-y-2">
                                                            <Label>Associated courses</Label>
                                                            <SearchableMultiSelect
                                                                placeholder="Search and select courses..."
                                                                searchPlaceholder="Search courses..."
                                                                emptyText="No courses found."
                                                                options={options.courses.map((course) => ({
                                                                    value: String(course.id),
                                                                    label: course.label,
                                                                    searchText: course.label,
                                                                }))}
                                                                selected={createForm.data.course_codes.map(String)}
                                                                onChange={(values) => {
                                                                    const next = values.map(Number);
                                                                    createForm.setData("course_codes", next);
                                                                    void loadCollegeSubjects(next, "create");
                                                                }}
                                                            />
                                                            {createForm.errors.course_codes ? (
                                                                <p className="text-destructive text-xs">{createForm.errors.course_codes}</p>
                                                            ) : null}
                                                        </div>

                                                        <div className="space-y-2">
                                                            <Label>Subjects</Label>
                                                            <SearchableMultiSelect
                                                                placeholder={
                                                                    createForm.data.course_codes.length
                                                                        ? "Search and select subjects..."
                                                                        : "Select courses first"
                                                                }
                                                                searchPlaceholder="Search subjects..."
                                                                emptyText="No subjects found."
                                                                options={collegeSubjectOptions.map((subject) => ({
                                                                    value: String(subject.id),
                                                                    label: subject.label,
                                                                    description: subject.title,
                                                                    searchText: `${subject.code} ${subject.title} ${subject.label}`,
                                                                }))}
                                                                selected={createForm.data.subject_ids.map(String)}
                                                                disabled={createForm.data.course_codes.length === 0 || collegeSubjectsLoading}
                                                                onChange={(values) => {
                                                                    const subjectIds = values.map(Number);
                                                                    createForm.setData("subject_ids", subjectIds);
                                                                    if (!subjectCodeTouched) {
                                                                        const computed = buildSubjectCodeFromSubjectOptions(
                                                                            values,
                                                                            collegeSubjectOptions,
                                                                        );
                                                                        createForm.setData("subject_code", computed);
                                                                    }
                                                                }}
                                                            />
                                                            {createForm.errors.subject_ids ? (
                                                                <p className="text-destructive text-xs">{createForm.errors.subject_ids}</p>
                                                            ) : null}
                                                        </div>

                                                        <div className="space-y-2 sm:col-span-2">
                                                            <Label>Class name / subject code</Label>
                                                            <Input
                                                                value={createForm.data.subject_code}
                                                                placeholder="Auto-generated from subjects..."
                                                                onChange={(e) => {
                                                                    setSubjectCodeTouched(true);
                                                                    createForm.setData("subject_code", e.target.value);
                                                                }}
                                                            />
                                                            <p className="text-muted-foreground text-xs">
                                                                Auto-generated from selected subjects. You can customize it.
                                                            </p>
                                                        </div>
                                                    </>
                                                ) : (
                                                    <>
                                                        <div className="space-y-2">
                                                            <Label>SHS track</Label>
                                                            <Select
                                                                value={createForm.data.shs_track_id ? String(createForm.data.shs_track_id) : ""}
                                                                onValueChange={(val) => {
                                                                    const trackId = Number(val);
                                                                    createForm.setData("shs_track_id", trackId);
                                                                    createForm.setData("shs_strand_id", null);
                                                                    createForm.setData("subject_code_shs", "");
                                                                    void loadShsStrands(trackId, "create");
                                                                }}
                                                            >
                                                                <SelectTrigger className="w-full">
                                                                    <SelectValue placeholder={shsStrandsLoading ? "Loading..." : "Select track"} />
                                                                </SelectTrigger>
                                                                <SelectContent>
                                                                    {options.shs_tracks.map((track) => (
                                                                        <SelectItem key={track.id} value={String(track.id)}>
                                                                            {track.label}
                                                                        </SelectItem>
                                                                    ))}
                                                                </SelectContent>
                                                            </Select>
                                                        </div>

                                                        <div className="space-y-2">
                                                            <Label>SHS strand</Label>
                                                            <Select
                                                                value={createForm.data.shs_strand_id ? String(createForm.data.shs_strand_id) : ""}
                                                                onValueChange={(val) => {
                                                                    const strandId = Number(val);
                                                                    createForm.setData("shs_strand_id", strandId);
                                                                    createForm.setData("subject_code_shs", "");
                                                                    void loadShsSubjects(strandId);
                                                                }}
                                                                disabled={!createForm.data.shs_track_id || shsStrandsLoading}
                                                            >
                                                                <SelectTrigger className="w-full">
                                                                    <SelectValue placeholder={shsStrandsLoading ? "Loading..." : "Select strand"} />
                                                                </SelectTrigger>
                                                                <SelectContent>
                                                                    {shsStrandOptions.map((strand) => (
                                                                        <SelectItem key={strand.id} value={String(strand.id)}>
                                                                            {strand.label}
                                                                        </SelectItem>
                                                                    ))}
                                                                </SelectContent>
                                                            </Select>
                                                        </div>

                                                        <div className="space-y-2 sm:col-span-2">
                                                            <Label>SHS subject</Label>
                                                            <Select
                                                                value={createForm.data.subject_code_shs}
                                                                onValueChange={(val) => createForm.setData("subject_code_shs", val)}
                                                                disabled={!createForm.data.shs_strand_id || shsSubjectsLoading}
                                                            >
                                                                <SelectTrigger className="w-full">
                                                                    <SelectValue placeholder={shsSubjectsLoading ? "Loading..." : "Select subject"} />
                                                                </SelectTrigger>
                                                                <SelectContent>
                                                                    {shsSubjectOptions.map((subject) => (
                                                                        <SelectItem key={subject.code} value={subject.code}>
                                                                            {subject.label}
                                                                        </SelectItem>
                                                                    ))}
                                                                </SelectContent>
                                                            </Select>
                                                        </div>

                                                        <div className="space-y-2">
                                                            <Label>Grade level</Label>
                                                            <Select
                                                                value={createForm.data.grade_level}
                                                                onValueChange={(val) => createForm.setData("grade_level", val)}
                                                            >
                                                                <SelectTrigger className="w-full">
                                                                    <SelectValue />
                                                                </SelectTrigger>
                                                                <SelectContent>
                                                                    {options.grade_levels.map((grade) => (
                                                                        <SelectItem key={grade.value} value={grade.value}>
                                                                            {grade.label}
                                                                        </SelectItem>
                                                                    ))}
                                                                </SelectContent>
                                                            </Select>
                                                        </div>
                                                    </>
                                                )}
                                            </div>
                                        </CardContent>
                                    </Card>

                                    <Card className="border-border/60 h-fit shadow-sm">
                                        <CardHeader className="bg-muted/20 border-b pb-4">
                                            <CardTitle className="text-base font-semibold">Logistics & Organization</CardTitle>
                                        </CardHeader>
                                        <CardContent className="space-y-4 pt-6">
                                            <div className="grid gap-4 sm:grid-cols-2">
                                                <div className="space-y-2 sm:col-span-2">
                                                    <Label>Faculty</Label>
                                                    <Select
                                                        value={createForm.data.faculty_id ? String(createForm.data.faculty_id) : ""}
                                                        onValueChange={(val) => createForm.setData("faculty_id", val)}
                                                    >
                                                        <SelectTrigger className="w-full">
                                                            <SelectValue placeholder="Select faculty" />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            {options.faculties.map((faculty) => (
                                                                <SelectItem key={faculty.id} value={String(faculty.id)}>
                                                                    {faculty.label}
                                                                </SelectItem>
                                                            ))}
                                                        </SelectContent>
                                                    </Select>
                                                </div>

                                                {createForm.data.classification === "college" ? (
                                                    <div className="space-y-3 sm:col-span-2">
                                                        <Label>Year level</Label>
                                                        <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                                                            {[1, 2, 3, 4].map((year) => (
                                                                <VisualRadioButton
                                                                    key={year}
                                                                    title={`${year} Year`}
                                                                    checked={createForm.data.academic_year === year}
                                                                    onSelect={() => createForm.setData("academic_year", year)}
                                                                    className="min-h-0 px-3 py-3"
                                                                />
                                                            ))}
                                                        </div>
                                                    </div>
                                                ) : null}

                                                <div className="space-y-3 sm:col-span-2">
                                                    <Label>Semester</Label>
                                                    <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
                                                        {options.semesters.map((sem) => (
                                                            <VisualRadioButton
                                                                key={sem.value}
                                                                title={sem.label}
                                                                className="min-h-0 px-3 py-3"
                                                                checked={createForm.data.semester === sem.value}
                                                                onSelect={() => createForm.setData("semester", sem.value)}
                                                            />
                                                        ))}
                                                    </div>
                                                </div>

                                                <div className="space-y-2">
                                                    <Label>School year</Label>
                                                    <Input
                                                        value={createForm.data.school_year}
                                                        onChange={(e) => createForm.setData("school_year", e.target.value)}
                                                        placeholder="e.g., 2023 - 2024"
                                                    />
                                                </div>

                                                <div className="space-y-2">
                                                    <Label>Section</Label>
                                                    <Select
                                                        value={createForm.data.section}
                                                        onValueChange={(val) => createForm.setData("section", val)}
                                                    >
                                                        <SelectTrigger className="w-full">
                                                            <SelectValue />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            {options.sections.map((sec) => (
                                                                <SelectItem key={sec.value} value={sec.value}>
                                                                    {sec.label}
                                                                </SelectItem>
                                                            ))}
                                                        </SelectContent>
                                                    </Select>
                                                </div>

                                                <div className="space-y-2">
                                                    <Label>Room</Label>
                                                    <Select
                                                        value={String(createForm.data.room_id)}
                                                        onValueChange={(val) => createForm.setData("room_id", Number(val))}
                                                    >
                                                        <SelectTrigger className="w-full">
                                                            <SelectValue placeholder="Room" />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            {options.rooms.map((room) => (
                                                                <SelectItem key={room.id} value={String(room.id)}>
                                                                    {room.label}
                                                                </SelectItem>
                                                            ))}
                                                        </SelectContent>
                                                    </Select>
                                                </div>

                                                <div className="space-y-2">
                                                    <Label>Maximum slots</Label>
                                                    <Input
                                                        type="number"
                                                        value={String(createForm.data.maximum_slots)}
                                                        min={1}
                                                        onChange={(e) => createForm.setData("maximum_slots", Number(e.target.value))}
                                                    />
                                                </div>
                                            </div>
                                        </CardContent>
                                    </Card>
                                </div>
                            </TabsContent>

                            <TabsContent value="schedule" className="m-0 flex h-full min-h-[500px] flex-col outline-none">
                                <SchedulePlanner
                                    schedules={createForm.data.schedules}
                                    setSchedules={(nextSchedules) => createForm.setData("schedules", nextSchedules)}
                                    rooms={options.rooms}
                                    dayOptions={options.day_of_week}
                                    defaultRoomId={options.rooms[0]?.id ?? 0}
                                    classRoomId={createForm.data.room_id}
                                />
                            </TabsContent>

                            <TabsContent value="settings" className="space-y-4">
                                {settingsEditor({
                                    settings: createForm.data.settings,
                                    setSettings: (nextSettings) => createForm.setData("settings", nextSettings),
                                })}
                            </TabsContent>
                        </div>
                    </Tabs>

                    <DialogFooter className="bg-muted/10 border-t p-6">
                        <Button variant="outline" onClick={() => setIsCreateOpen(false)}>
                            Cancel
                        </Button>
                        <Button
                            disabled={createForm.processing}
                            onClick={() => {
                                createForm.post(route("administrators.classes.store"), {
                                    preserveScroll: true,
                                    forceFormData: true,
                                    onError: (errors) => {
                                        setCreateActiveTab(getTabForFormErrors(errors as Record<string, string>));
                                    },
                                    onSuccess: () => {
                                        setIsCreateOpen(false);
                                        createForm.reset();
                                        setCreateActiveTab("details");
                                        setSearch(filters.search || "");
                                    },
                                });
                            }}
                        >
                            {createForm.processing ? "Creating..." : "Create"}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog
                open={isEditOpen}
                onOpenChange={(open) => {
                    setIsEditOpen(open);
                    if (open) {
                        setEditActiveTab("details");
                    }
                }}
            >
                <DialogContent className="bg-background/95 supports-[backdrop-filter]:bg-background/60 flex max-h-[95vh] w-full flex-col gap-0 overflow-hidden p-0 backdrop-blur sm:max-w-3xl md:max-w-5xl lg:max-w-[90vw] xl:max-w-[1400px]">
                    <DialogHeader className="bg-muted/20 border-b p-6 pb-4">
                        <DialogTitle className="text-xl font-bold">Edit class</DialogTitle>
                        <DialogDescription>Update the class record and schedule.</DialogDescription>
                    </DialogHeader>

                    {!selected_class ? (
                        <div className="text-muted-foreground flex-1 p-6 text-sm">Select a class first.</div>
                    ) : (
                        <Tabs
                            value={editActiveTab}
                            onValueChange={(value) => setEditActiveTab(value as ClassDialogTab)}
                            className="flex flex-1 flex-col overflow-hidden"
                        >
                            <div className="px-6 pt-4">
                                <TabsList className="h-12 w-full justify-start gap-6 rounded-none border-b bg-transparent p-0">
                                    <TabsTrigger
                                        value="details"
                                        className="data-[state=active]:border-primary text-muted-foreground data-[state=active]:text-foreground h-12 rounded-none px-2 data-[state=active]:border-b-2 data-[state=active]:bg-transparent data-[state=active]:shadow-none"
                                    >
                                        <ListTodo className="mr-2 h-4 w-4" />
                                        Details
                                    </TabsTrigger>
                                    <TabsTrigger
                                        value="schedule"
                                        className="data-[state=active]:border-primary text-muted-foreground data-[state=active]:text-foreground h-12 rounded-none px-2 data-[state=active]:border-b-2 data-[state=active]:bg-transparent data-[state=active]:shadow-none"
                                    >
                                        <CalendarIcon className="mr-2 h-4 w-4" />
                                        Schedule
                                    </TabsTrigger>
                                    <TabsTrigger
                                        value="settings"
                                        className="data-[state=active]:border-primary text-muted-foreground data-[state=active]:text-foreground h-12 rounded-none px-2 data-[state=active]:border-b-2 data-[state=active]:bg-transparent data-[state=active]:shadow-none"
                                    >
                                        <Settings2 className="mr-2 h-4 w-4" />
                                        Settings
                                    </TabsTrigger>
                                </TabsList>
                            </div>

                            <div className="flex-1 overflow-y-auto p-6 pt-6">
                                {editFirstError ? (
                                    <div className="border-destructive/40 bg-destructive/5 text-destructive mb-6 rounded-lg border px-4 py-3 text-sm">
                                        {editFirstError}
                                    </div>
                                ) : null}
                                <TabsContent value="details" className="m-0 space-y-6 outline-none">
                                    <div className="grid gap-6 lg:grid-cols-2">
                                        <Card className="border-border/60 shadow-sm">
                                            <CardHeader className="bg-muted/20 border-b pb-4">
                                                <CardTitle className="text-base font-semibold">Academic Profile</CardTitle>
                                            </CardHeader>
                                            <CardContent className="space-y-4 pt-6">
                                                <div className="grid gap-4 sm:grid-cols-2">
                                                    <div className="space-y-3 sm:col-span-2">
                                                        <Label>Class type</Label>
                                                        <div className="grid gap-3 sm:grid-cols-2">
                                                            <VisualRadioButton
                                                                title="College"
                                                                description="Higher education degrees and courses."
                                                                checked={editForm.data.classification === "college"}
                                                                onSelect={() => {
                                                                    editForm.setData("classification", "college");
                                                                    editForm.setData("course_codes", []);
                                                                    editForm.setData("subject_ids", []);
                                                                    editForm.setData("subject_code", "");
                                                                    editForm.setData("shs_track_id", null);
                                                                    editForm.setData("shs_strand_id", null);
                                                                    editForm.setData("subject_code_shs", "");
                                                                    setCollegeSubjectOptions([]);
                                                                    setShsStrandOptions([]);
                                                                    setShsSubjectOptions([]);
                                                                    setSubjectCodeTouched(false);
                                                                }}
                                                            />
                                                            <VisualRadioButton
                                                                title="Senior High School"
                                                                description="K-12 pathway and strands."
                                                                checked={editForm.data.classification === "shs"}
                                                                onSelect={() => {
                                                                    editForm.setData("classification", "shs");
                                                                    editForm.setData("course_codes", []);
                                                                    editForm.setData("subject_ids", []);
                                                                    editForm.setData("subject_code", "");
                                                                    editForm.setData("shs_track_id", null);
                                                                    editForm.setData("shs_strand_id", null);
                                                                    editForm.setData("subject_code_shs", "");
                                                                    setCollegeSubjectOptions([]);
                                                                    setShsStrandOptions([]);
                                                                    setShsSubjectOptions([]);
                                                                    setSubjectCodeTouched(false);
                                                                }}
                                                            />
                                                        </div>
                                                    </div>

                                                    {editForm.data.classification === "college" ? (
                                                        <>
                                                            <div className="space-y-2">
                                                                <Label>Associated courses</Label>
                                                                <SearchableMultiSelect
                                                                    placeholder="Search and select courses..."
                                                                    searchPlaceholder="Search courses..."
                                                                    emptyText="No courses found."
                                                                    options={options.courses.map((course) => ({
                                                                        value: String(course.id),
                                                                        label: course.label,
                                                                        searchText: course.label,
                                                                    }))}
                                                                    selected={editForm.data.course_codes.map(String)}
                                                                    onChange={(values) => {
                                                                        const next = values.map(Number);
                                                                        editForm.setData("course_codes", next);
                                                                        void loadCollegeSubjects(next, "edit");
                                                                    }}
                                                                />
                                                                {editForm.errors.course_codes ? (
                                                                    <p className="text-destructive text-xs">{editForm.errors.course_codes}</p>
                                                                ) : null}
                                                            </div>

                                                            <div className="space-y-2">
                                                                <Label>Subjects</Label>
                                                                <SearchableMultiSelect
                                                                    placeholder={
                                                                        editForm.data.course_codes.length
                                                                            ? "Search and select subjects..."
                                                                            : "Select courses first"
                                                                    }
                                                                    searchPlaceholder="Search subjects..."
                                                                    emptyText="No subjects found."
                                                                    options={collegeSubjectOptions.map((subject) => ({
                                                                        value: String(subject.id),
                                                                        label: subject.label,
                                                                        description: subject.title,
                                                                        searchText: `${subject.code} ${subject.title} ${subject.label}`,
                                                                    }))}
                                                                    selected={editForm.data.subject_ids.map(String)}
                                                                    disabled={editForm.data.course_codes.length === 0 || collegeSubjectsLoading}
                                                                    onChange={(values) => {
                                                                        const subjectIds = values.map(Number);
                                                                        editForm.setData("subject_ids", subjectIds);
                                                                        if (!subjectCodeTouched) {
                                                                            const computed = buildSubjectCodeFromSubjectOptions(
                                                                                values,
                                                                                collegeSubjectOptions,
                                                                            );
                                                                            editForm.setData("subject_code", computed);
                                                                        }
                                                                    }}
                                                                />
                                                                {editForm.errors.subject_ids ? (
                                                                    <p className="text-destructive text-xs">{editForm.errors.subject_ids}</p>
                                                                ) : null}
                                                            </div>

                                                            <div className="space-y-2 sm:col-span-2">
                                                                <Label>Class name / subject code</Label>
                                                                <Input
                                                                    value={editForm.data.subject_code}
                                                                    placeholder="Auto-generated from subjects..."
                                                                    onChange={(e) => {
                                                                        setSubjectCodeTouched(true);
                                                                        editForm.setData("subject_code", e.target.value);
                                                                    }}
                                                                />
                                                            </div>
                                                        </>
                                                    ) : (
                                                        <>
                                                            <div className="space-y-2">
                                                                <Label>SHS track</Label>
                                                                <Select
                                                                    value={editForm.data.shs_track_id ? String(editForm.data.shs_track_id) : ""}
                                                                    onValueChange={(val) => {
                                                                        const trackId = Number(val);
                                                                        editForm.setData("shs_track_id", trackId);
                                                                        editForm.setData("shs_strand_id", null);
                                                                        editForm.setData("subject_code_shs", "");
                                                                        void loadShsStrands(trackId, "edit");
                                                                    }}
                                                                >
                                                                    <SelectTrigger className="w-full">
                                                                        <SelectValue placeholder="Select track" />
                                                                    </SelectTrigger>
                                                                    <SelectContent>
                                                                        {options.shs_tracks.map((track) => (
                                                                            <SelectItem key={track.id} value={String(track.id)}>
                                                                                {track.label}
                                                                            </SelectItem>
                                                                        ))}
                                                                    </SelectContent>
                                                                </Select>
                                                            </div>

                                                            <div className="space-y-2">
                                                                <Label>SHS strand</Label>
                                                                <Select
                                                                    value={editForm.data.shs_strand_id ? String(editForm.data.shs_strand_id) : ""}
                                                                    onValueChange={(val) => {
                                                                        const strandId = Number(val);
                                                                        editForm.setData("shs_strand_id", strandId);
                                                                        editForm.setData("subject_code_shs", "");
                                                                        void loadShsSubjects(strandId);
                                                                    }}
                                                                    disabled={!editForm.data.shs_track_id || shsStrandsLoading}
                                                                >
                                                                    <SelectTrigger className="w-full">
                                                                        <SelectValue placeholder="Select strand" />
                                                                    </SelectTrigger>
                                                                    <SelectContent>
                                                                        {shsStrandOptions.map((strand) => (
                                                                            <SelectItem key={strand.id} value={String(strand.id)}>
                                                                                {strand.label}
                                                                            </SelectItem>
                                                                        ))}
                                                                    </SelectContent>
                                                                </Select>
                                                            </div>

                                                            <div className="space-y-2 sm:col-span-2">
                                                                <Label>SHS subject</Label>
                                                                <Select
                                                                    value={editForm.data.subject_code_shs}
                                                                    onValueChange={(val) => editForm.setData("subject_code_shs", val)}
                                                                    disabled={!editForm.data.shs_strand_id || shsSubjectsLoading}
                                                                >
                                                                    <SelectTrigger className="w-full">
                                                                        <SelectValue placeholder="Select subject" />
                                                                    </SelectTrigger>
                                                                    <SelectContent>
                                                                        {shsSubjectOptions.map((subject) => (
                                                                            <SelectItem key={subject.code} value={subject.code}>
                                                                                {subject.label}
                                                                            </SelectItem>
                                                                        ))}
                                                                    </SelectContent>
                                                                </Select>
                                                            </div>

                                                            <div className="space-y-2">
                                                                <Label>Grade level</Label>
                                                                <Select
                                                                    value={editForm.data.grade_level || "Grade 11"}
                                                                    onValueChange={(val) => editForm.setData("grade_level", val)}
                                                                >
                                                                    <SelectTrigger className="w-full">
                                                                        <SelectValue />
                                                                    </SelectTrigger>
                                                                    <SelectContent>
                                                                        {options.grade_levels.map((grade) => (
                                                                            <SelectItem key={grade.value} value={grade.value}>
                                                                                {grade.label}
                                                                            </SelectItem>
                                                                        ))}
                                                                    </SelectContent>
                                                                </Select>
                                                            </div>
                                                        </>
                                                    )}
                                                </div>
                                            </CardContent>
                                        </Card>

                                        <Card className="border-border/60 h-fit shadow-sm">
                                            <CardHeader className="bg-muted/20 border-b pb-4">
                                                <CardTitle className="text-base font-semibold">Logistics & Organization</CardTitle>
                                            </CardHeader>
                                            <CardContent className="space-y-4 pt-6">
                                                <div className="grid gap-4 sm:grid-cols-2">
                                                    <div className="space-y-2 sm:col-span-2">
                                                        <Label>Faculty</Label>
                                                        <Select
                                                            value={editForm.data.faculty_id ? String(editForm.data.faculty_id) : ""}
                                                            onValueChange={(val) => editForm.setData("faculty_id", val)}
                                                        >
                                                            <SelectTrigger className="w-full">
                                                                <SelectValue placeholder="Select faculty" />
                                                            </SelectTrigger>
                                                            <SelectContent>
                                                                {options.faculties.map((faculty) => (
                                                                    <SelectItem key={faculty.id} value={String(faculty.id)}>
                                                                        {faculty.label}
                                                                    </SelectItem>
                                                                ))}
                                                            </SelectContent>
                                                        </Select>
                                                    </div>

                                                    {editForm.data.classification === "college" ? (
                                                        <div className="space-y-3 sm:col-span-2">
                                                            <Label>Year level</Label>
                                                            <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                                                                {[1, 2, 3, 4].map((year) => (
                                                                    <VisualRadioButton
                                                                        key={year}
                                                                        title={`${year} Year`}
                                                                        checked={editForm.data.academic_year === year}
                                                                        onSelect={() => editForm.setData("academic_year", year)}
                                                                        className="min-h-0 px-3 py-3"
                                                                    />
                                                                ))}
                                                            </div>
                                                        </div>
                                                    ) : null}

                                                    <div className="space-y-3 sm:col-span-2">
                                                        <Label>Semester</Label>
                                                        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
                                                            {options.semesters.map((sem) => (
                                                                <VisualRadioButton
                                                                    key={sem.value}
                                                                    title={sem.label}
                                                                    className="min-h-0 px-3 py-3"
                                                                    checked={editForm.data.semester === sem.value}
                                                                    onSelect={() => editForm.setData("semester", sem.value)}
                                                                />
                                                            ))}
                                                        </div>
                                                    </div>

                                                    <div className="space-y-2">
                                                        <Label>School year</Label>
                                                        <Input
                                                            value={editForm.data.school_year}
                                                            onChange={(e) => editForm.setData("school_year", e.target.value)}
                                                            placeholder="e.g., 2023 - 2024"
                                                        />
                                                    </div>

                                                    <div className="space-y-2">
                                                        <Label>Section</Label>
                                                        <Select
                                                            value={editForm.data.section}
                                                            onValueChange={(val) => editForm.setData("section", val)}
                                                        >
                                                            <SelectTrigger className="w-full">
                                                                <SelectValue />
                                                            </SelectTrigger>
                                                            <SelectContent>
                                                                {options.sections.map((sec) => (
                                                                    <SelectItem key={sec.value} value={sec.value}>
                                                                        {sec.label}
                                                                    </SelectItem>
                                                                ))}
                                                            </SelectContent>
                                                        </Select>
                                                    </div>

                                                    <div className="space-y-2">
                                                        <Label>Room</Label>
                                                        <Select
                                                            value={String(editForm.data.room_id)}
                                                            onValueChange={(val) => editForm.setData("room_id", Number(val))}
                                                        >
                                                            <SelectTrigger className="w-full">
                                                                <SelectValue placeholder="Room" />
                                                            </SelectTrigger>
                                                            <SelectContent>
                                                                {options.rooms.map((room) => (
                                                                    <SelectItem key={room.id} value={String(room.id)}>
                                                                        {room.label}
                                                                    </SelectItem>
                                                                ))}
                                                            </SelectContent>
                                                        </Select>
                                                    </div>

                                                    <div className="space-y-2">
                                                        <Label>Maximum slots</Label>
                                                        <Input
                                                            type="number"
                                                            value={String(editForm.data.maximum_slots)}
                                                            min={1}
                                                            onChange={(e) => editForm.setData("maximum_slots", Number(e.target.value))}
                                                        />
                                                    </div>
                                                </div>
                                            </CardContent>
                                        </Card>
                                    </div>
                                </TabsContent>

                                <TabsContent value="schedule" className="m-0 flex h-full min-h-[500px] flex-col outline-none">
                                    <SchedulePlanner
                                        schedules={editForm.data.schedules}
                                        setSchedules={(nextSchedules) => editForm.setData("schedules", nextSchedules)}
                                        rooms={options.rooms}
                                        dayOptions={options.day_of_week}
                                        defaultRoomId={options.rooms[0]?.id ?? 0}
                                        classRoomId={editForm.data.room_id}
                                    />
                                </TabsContent>

                                <TabsContent value="settings" className="space-y-4">
                                    {settingsEditor({
                                        settings: editForm.data.settings,
                                        setSettings: (nextSettings) => editForm.setData("settings", nextSettings),
                                        removeBannerImage: editForm.data.remove_banner_image,
                                        setRemoveBannerImage: (value) => editForm.setData("remove_banner_image", value),
                                    })}
                                </TabsContent>
                            </div>
                        </Tabs>
                    )}

                    <DialogFooter className="bg-muted/10 border-t p-6">
                        <Button variant="outline" onClick={() => setIsEditOpen(false)}>
                            Cancel
                        </Button>
                        <Button
                            disabled={editForm.processing || !selected_class}
                            onClick={() => {
                                if (!selected_class) return;
                                editForm.patch(route("administrators.classes.update", { class: selected_class.id }), {
                                    preserveScroll: true,
                                    forceFormData: true,
                                    onError: (errors) => {
                                        setEditActiveTab(getTabForFormErrors(errors as Record<string, string>));
                                    },
                                    onSuccess: () => {
                                        setIsEditOpen(false);
                                        editForm.reset();
                                        setEditActiveTab("details");
                                        router.reload({ only: ["classes", "selected_class"] });
                                    },
                                });
                            }}
                        >
                            {editForm.processing ? "Saving..." : "Save"}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog open={pendingDelete !== null} onOpenChange={(open) => !open && setPendingDelete(null)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete this class?</DialogTitle>
                        <DialogDescription>
                            This will permanently remove <span className="text-foreground font-medium">{pendingDelete?.record_title}</span> along with
                            its schedules and settings. Enrolled students will lose access. This action cannot be undone.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setPendingDelete(null)}>
                            Cancel
                        </Button>
                        <Button variant="destructive" onClick={performDelete}>
                            <Trash2 className="mr-2 h-4 w-4" />
                            Delete class
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Sheet open={isManageOpen} onOpenChange={setIsManageOpen}>
                <SheetContent side="right" className="w-full sm:max-w-xl">
                    <SheetHeader>
                        <SheetTitle>{selected_class?.record_title ?? "Class"}</SheetTitle>
                        <SheetDescription>
                            {selected_class ? (
                                <span>
                                    {selected_class.subject_title} • {selected_class.school_year} • Sem {selected_class.semester}
                                </span>
                            ) : (
                                "Loading..."
                            )}
                        </SheetDescription>
                    </SheetHeader>

                    {!selected_class ? (
                        <div className="text-muted-foreground p-4 text-sm">Loading class details…</div>
                    ) : (
                        <div className="flex flex-1 flex-col gap-4 overflow-auto px-4">
                            <div className="flex flex-wrap gap-2">
                                <Badge variant="outline" className="capitalize">
                                    {selected_class.classification}
                                </Badge>
                                <Badge variant="secondary">
                                    {selected_class.students_count} / {selected_class.maximum_slots || "—"} enrolled
                                </Badge>
                            </div>

                            <Tabs defaultValue="overview">
                                <TabsList className="w-full justify-start">
                                    <TabsTrigger value="overview">Overview</TabsTrigger>
                                    <TabsTrigger value="schedule">Schedule</TabsTrigger>
                                    <TabsTrigger value="settings">Settings</TabsTrigger>
                                    <TabsTrigger value="relationships">Relationships</TabsTrigger>
                                </TabsList>

                                <TabsContent value="overview" className="space-y-3">
                                    <div className="grid gap-3 rounded-lg border p-4">
                                        <div className="grid gap-2">
                                            <div className="text-muted-foreground text-xs">Subject</div>
                                            <div className="text-foreground font-medium">
                                                {selected_class.subject_code} • {selected_class.subject_title}
                                            </div>
                                        </div>

                                        <div className="grid gap-2">
                                            <div className="text-muted-foreground text-xs">Faculty</div>
                                            <div className="text-foreground font-medium">{selected_class.faculty?.label ?? "TBA"}</div>
                                            {selected_class.faculty?.email ? (
                                                <div className="text-muted-foreground text-xs">{selected_class.faculty.email}</div>
                                            ) : null}
                                        </div>

                                        <div className="grid gap-2">
                                            <div className="text-muted-foreground text-xs">Room</div>
                                            <div className="text-foreground font-medium">{selected_class.room?.label ?? "—"}</div>
                                        </div>

                                        {selected_class.classification === "college" ? (
                                            <div className="grid gap-2">
                                                <div className="text-muted-foreground text-xs">Courses</div>
                                                <div className="text-foreground text-sm">
                                                    {selected_class.course_codes.length ? selected_class.course_codes.join(", ") : "—"}
                                                </div>
                                            </div>
                                        ) : (
                                            <div className="grid gap-2">
                                                <div className="text-muted-foreground text-xs">Track / Strand</div>
                                                <div className="text-foreground text-sm">
                                                    {[selected_class.shs_track?.label, selected_class.shs_strand?.label]
                                                        .filter(Boolean)
                                                        .join(" • ") || "—"}
                                                </div>
                                            </div>
                                        )}

                                        <div className="grid gap-2">
                                            <div className="text-muted-foreground text-xs">Academic period</div>
                                            <div className="text-foreground text-sm">
                                                {selected_class.school_year} • Sem {selected_class.semester} • Section {selected_class.section}
                                            </div>
                                        </div>
                                    </div>
                                </TabsContent>

                                <TabsContent value="schedule" className="space-y-3">
                                    <div className="grid gap-3">
                                        {selected_class.schedules.length === 0 ? (
                                            <div className="text-muted-foreground rounded-lg border p-4 text-sm">No schedules.</div>
                                        ) : (
                                            selected_class.schedules.map((schedule) => (
                                                <div
                                                    key={`${schedule.day_of_week}-${schedule.start_time}-${schedule.end_time}`}
                                                    className="rounded-lg border p-4"
                                                >
                                                    <div className="flex items-center justify-between">
                                                        <div className="text-foreground font-medium">{schedule.day_of_week}</div>
                                                        <div className="text-muted-foreground text-sm">
                                                            {schedule.start_time} – {schedule.end_time}
                                                        </div>
                                                    </div>
                                                    <div className="text-muted-foreground mt-2 text-sm">Room: {schedule.room?.label ?? "—"}</div>
                                                </div>
                                            ))
                                        )}

                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={() => {
                                                setIsEditOpen(true);
                                            }}
                                        >
                                            Edit schedule
                                        </Button>
                                    </div>
                                </TabsContent>

                                <TabsContent value="settings" className="space-y-3">
                                    <div className="grid gap-3 rounded-lg border p-4">
                                        <div className="grid gap-1">
                                            <div className="text-muted-foreground text-xs">Theme</div>
                                            <div className="text-foreground text-sm font-medium">{selected_class.settings.theme ?? "default"}</div>
                                        </div>
                                        <div className="grid grid-cols-2 gap-3">
                                            <div className="grid gap-1">
                                                <div className="text-muted-foreground text-xs">Background</div>
                                                <div className="text-foreground text-sm">{selected_class.settings.background_color ?? "—"}</div>
                                            </div>
                                            <div className="grid gap-1">
                                                <div className="text-muted-foreground text-xs">Accent</div>
                                                <div className="text-foreground text-sm">{selected_class.settings.accent_color ?? "—"}</div>
                                            </div>
                                        </div>

                                        <div className="grid gap-2">
                                            <div className="text-muted-foreground text-xs">Features</div>
                                            <div className="flex flex-wrap gap-2">
                                                {(
                                                    [
                                                        { key: "enable_announcements", label: "Announcements" },
                                                        { key: "enable_grade_visibility", label: "Grade visibility" },
                                                        { key: "enable_attendance_tracking", label: "Attendance" },
                                                        { key: "allow_late_submissions", label: "Late submissions" },
                                                        { key: "enable_discussion_board", label: "Discussion board" },
                                                    ] as const
                                                ).map((toggle) => (
                                                    <Badge key={toggle.key} variant={selected_class.settings[toggle.key] ? "default" : "outline"}>
                                                        {toggle.label}
                                                    </Badge>
                                                ))}
                                            </div>
                                        </div>
                                    </div>

                                    <Button type="button" variant="outline" onClick={() => setIsEditOpen(true)}>
                                        Edit settings
                                    </Button>
                                </TabsContent>

                                <TabsContent value="relationships" className="space-y-3">
                                    <Tabs defaultValue="students">
                                        <TabsList className="w-full justify-start">
                                            <TabsTrigger value="students">Enrolled students</TabsTrigger>
                                            <TabsTrigger value="posts">Class posts</TabsTrigger>
                                        </TabsList>

                                        <TabsContent value="students" className="space-y-3">
                                            <div className="rounded-lg border">
                                                <Table>
                                                    <TableHeader>
                                                        <TableRow>
                                                            <TableHead>Student</TableHead>
                                                            <TableHead>Status</TableHead>
                                                            <TableHead className="text-right">Final</TableHead>
                                                        </TableRow>
                                                    </TableHeader>
                                                    <TableBody>
                                                        {selected_class.enrollments.length === 0 ? (
                                                            <TableRow>
                                                                <TableCell colSpan={3} className="text-muted-foreground h-20 text-center text-sm">
                                                                    No enrollments loaded.
                                                                </TableCell>
                                                            </TableRow>
                                                        ) : (
                                                            selected_class.enrollments.map((enrollment) => (
                                                                <TableRow key={enrollment.id}>
                                                                    <TableCell>
                                                                        <div className="flex flex-col">
                                                                            <span className="text-foreground font-medium">
                                                                                {enrollment.student?.name ?? "Unknown"}
                                                                            </span>
                                                                            <span className="text-muted-foreground text-xs">
                                                                                {enrollment.student?.course ?? "—"} •{" "}
                                                                                {enrollment.student?.academic_year ?? "—"}
                                                                            </span>
                                                                        </div>
                                                                    </TableCell>
                                                                    <TableCell>
                                                                        <span className="text-muted-foreground text-sm">
                                                                            {enrollment.status ?? "—"}
                                                                        </span>
                                                                    </TableCell>
                                                                    <TableCell className="text-muted-foreground text-right text-sm">
                                                                        {enrollment.total_average ?? "—"}
                                                                    </TableCell>
                                                                </TableRow>
                                                            ))
                                                        )}
                                                    </TableBody>
                                                </Table>
                                            </div>

                                            <Button asChild variant="outline">
                                                <Link href={route("administrators.classes.show", { class: selected_class.id })}>
                                                    Open full enrollments page
                                                </Link>
                                            </Button>
                                        </TabsContent>

                                        <TabsContent value="posts" className="space-y-3">
                                            <div className="grid gap-2">
                                                {selected_class.posts.length === 0 ? (
                                                    <div className="text-muted-foreground rounded-lg border p-4 text-sm">No posts loaded.</div>
                                                ) : (
                                                    selected_class.posts.map((post) => (
                                                        <div key={post.id} className="rounded-lg border p-4">
                                                            <div className="flex items-start justify-between gap-3">
                                                                <div className="min-w-0">
                                                                    <div className="text-foreground truncate font-medium">{post.title}</div>
                                                                    <div className="text-muted-foreground text-xs">Type: {post.type}</div>
                                                                </div>
                                                                <Badge variant="outline">#{post.id}</Badge>
                                                            </div>
                                                        </div>
                                                    ))
                                                )}
                                            </div>

                                            <Button asChild variant="outline">
                                                <Link href={route("administrators.classes.show", { class: selected_class.id })}>
                                                    Open full posts page
                                                </Link>
                                            </Button>
                                        </TabsContent>
                                    </Tabs>
                                </TabsContent>
                            </Tabs>
                        </div>
                    )}

                    <SheetFooter>
                        <div className="flex w-full flex-col gap-2">
                            <Button
                                type="button"
                                onClick={() => {
                                    if (!selected_class) return;
                                    setIsEditOpen(true);
                                    setEditActiveTab("details");
                                }}
                            >
                                <Pencil className="mr-2 h-4 w-4" />
                                Edit class
                            </Button>
                            {selected_class ? (
                                <Button asChild variant="outline">
                                    <Link href={route("administrators.classes.show", { class: selected_class.id })}>
                                        <BookOpen className="mr-2 h-4 w-4" />
                                        Open class page
                                    </Link>
                                </Button>
                            ) : null}
                            <Button
                                type="button"
                                variant="ghost"
                                className="text-destructive hover:text-destructive"
                                onClick={() => {
                                    if (!selected_class) return;
                                    setPendingDelete({
                                        id: selected_class.id,
                                        record_title: selected_class.record_title,
                                    } as ClassRow);
                                }}
                            >
                                <Trash2 className="mr-2 h-4 w-4" />
                                Delete class
                            </Button>
                        </div>
                    </SheetFooter>
                </SheetContent>
            </Sheet>
        </AdminLayout>
    );
}
