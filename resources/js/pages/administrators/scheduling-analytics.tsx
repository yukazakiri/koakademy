import AdminLayout from "@/components/administrators/admin-layout";
import CreateClassDialog from "./components/create-class-dialog";
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle } from "@/components/ui/alert-dialog";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { ScrollArea } from "@/components/ui/scroll-area";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Separator } from "@/components/ui/separator";
import { Switch } from "@/components/ui/switch";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from "@/components/ui/tooltip";
import type { User } from "@/types/user";
import { DndContext, DragOverlay, MouseSensor, TouchSensor, useDraggable, useDroppable, useSensor, useSensors } from "@dnd-kit/core";
import type { DragEndEvent, DragStartEvent, Modifier } from "@dnd-kit/core";
import { Head, router } from "@inertiajs/react";
import axios from "axios";
import {
    AlertTriangle,
    BookOpen,
    Building2,
    Calendar,
    Clock,
    GraduationCap,
    GripVertical,
    LayoutGrid,
    List,
    Loader2,
    MapPin,
    Plus,
    RefreshCw,
    Search,
    User as UserIcon,
    Users,
    X,
} from "lucide-react";
import * as React from "react";
import { toast } from "sonner";
import { route } from "ziggy-js";

// ── Types ──────────────────────────────────────────────────────────

type ScheduleEntry = {
    id?: number;
    day_of_week: string;
    start_time: string;
    end_time: string;
    time_range: string;
    room: string | null;
    room_id?: number | null;
};

type DragData = {
    block: Block;
    scheduleId: number;
    originalDay: string;
    originalStartTime: string;
    originalEndTime: string;
};

type ClassScheduleData = {
    id: number;
    subject_code: string;
    subject_title: string;
    section: string;
    grade_level: string | null;
    faculty_id?: string | null;
    faculty_name: string | null;
    room_name: string | null;
    courses: string;
    course_ids: number[];
    student_count: number;
    schedules: ScheduleEntry[];
};

type CourseOption = { id: number; code: string; title: string };
type RoomOption = { id: number; name: string; class_code: string | null };
type FacultyOption = { id: string; name: string; department: string | null };

type ScheduleConflict = {
    day: string;
    time: string;
    class_1: { subject_code: string; section: string; room: string | null; faculty: string | null };
    class_2: { subject_code: string; section: string; room: string | null; faculty: string | null };
    conflict_type: "room" | "faculty";
};

type ScheduleStats = {
    total_classes: number;
    total_students: number;
    classes_by_year_level: Record<string, number>;
    classes_by_course: Record<string, number>;
    schedule_conflicts: ScheduleConflict[];
};

type StudentSearchResult = {
    id: number;
    student_id: number;
    name: string;
    course_id: number | null;
    academic_year: number | null;
};

type StudentSchedule = {
    student: { id: number; student_id: number; name: string; course: string | null; academic_year: number | null };
    schedule: Array<{
        id: number;
        subject_code: string;
        subject_title: string;
        section: string;
        faculty_name: string | null;
        schedules: ScheduleEntry[];
    }>;
};

interface SchedulingAnalyticsProps {
    user: User;
    schedule_data: ClassScheduleData[];
    stats: ScheduleStats;
    filters: {
        available_courses: CourseOption[];
        available_year_levels: string[];
        available_sections: string[];
        available_rooms: RoomOption[];
        available_faculty: FacultyOption[];
        current_filters: { course: string | null; year_level: string | null; section: string | null };
    };
    creation_options: {
        rooms: RoomOption[];
        faculty: FacultyOption[];
        courses: Array<{ id: number; code: string; title: string; curriculum_year: string | null }>;
        shs_tracks: Array<{ id: number; track_name: string }>;
        shs_strands: Array<{ id: number; strand_name: string; track_id: number; track_name: string | null }>;
        sections: string[];
        semesters: Array<{ value: string; label: string }>;
    };
    defaults: { semester: string; school_year: string };
}

// ── Constants ──────────────────────────────────────────────────────

const DAYS = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"] as const;
const DAYS_SHORT = ["Mon", "Tue", "Wed", "Thu", "Fri", "Sat"] as const;
const HOUR_START = 7;
const HOUR_END = 19;
const CELL_H = 56; // px per hour

const PALETTES = [
    { accent: "border-l-rose-500", bg: "bg-rose-500/10 dark:bg-rose-400/15", text: "text-rose-700 dark:text-rose-300", badge: "bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300", border: "border-rose-200 dark:border-rose-800" },
    { accent: "border-l-sky-500", bg: "bg-sky-500/10 dark:bg-sky-400/15", text: "text-sky-700 dark:text-sky-300", badge: "bg-sky-100 text-sky-700 dark:bg-sky-900/40 dark:text-sky-300", border: "border-sky-200 dark:border-sky-800" },
    { accent: "border-l-amber-500", bg: "bg-amber-500/10 dark:bg-amber-400/15", text: "text-amber-700 dark:text-amber-300", badge: "bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300", border: "border-amber-200 dark:border-amber-800" },
    { accent: "border-l-emerald-500", bg: "bg-emerald-500/10 dark:bg-emerald-400/15", text: "text-emerald-700 dark:text-emerald-300", badge: "bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300", border: "border-emerald-200 dark:border-emerald-800" },
    { accent: "border-l-violet-500", bg: "bg-violet-500/10 dark:bg-violet-400/15", text: "text-violet-700 dark:text-violet-300", badge: "bg-violet-100 text-violet-700 dark:bg-violet-900/40 dark:text-violet-300", border: "border-violet-200 dark:border-violet-800" },
    { accent: "border-l-orange-500", bg: "bg-orange-500/10 dark:bg-orange-400/15", text: "text-orange-700 dark:text-orange-300", badge: "bg-orange-100 text-orange-700 dark:bg-orange-900/40 dark:text-orange-300", border: "border-orange-200 dark:border-orange-800" },
    { accent: "border-l-teal-500", bg: "bg-teal-500/10 dark:bg-teal-400/15", text: "text-teal-700 dark:text-teal-300", badge: "bg-teal-100 text-teal-700 dark:bg-teal-900/40 dark:text-teal-300", border: "border-teal-200 dark:border-teal-800" },
    { accent: "border-l-pink-500", bg: "bg-pink-500/10 dark:bg-pink-400/15", text: "text-pink-700 dark:text-pink-300", badge: "bg-pink-100 text-pink-700 dark:bg-pink-900/40 dark:text-pink-300", border: "border-pink-200 dark:border-pink-800" },
    { accent: "border-l-cyan-500", bg: "bg-cyan-500/10 dark:bg-cyan-400/15", text: "text-cyan-700 dark:text-cyan-300", badge: "bg-cyan-100 text-cyan-700 dark:bg-cyan-900/40 dark:text-cyan-300", border: "border-cyan-200 dark:border-cyan-800" },
    { accent: "border-l-indigo-500", bg: "bg-indigo-500/10 dark:bg-indigo-400/15", text: "text-indigo-700 dark:text-indigo-300", badge: "bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300", border: "border-indigo-200 dark:border-indigo-800" },
    { accent: "border-l-lime-500", bg: "bg-lime-500/10 dark:bg-lime-400/15", text: "text-lime-700 dark:text-lime-300", badge: "bg-lime-100 text-lime-700 dark:bg-lime-900/40 dark:text-lime-300", border: "border-lime-200 dark:border-lime-800" },
    { accent: "border-l-fuchsia-500", bg: "bg-fuchsia-500/10 dark:bg-fuchsia-400/15", text: "text-fuchsia-700 dark:text-fuchsia-300", badge: "bg-fuchsia-100 text-fuchsia-700 dark:bg-fuchsia-900/40 dark:text-fuchsia-300", border: "border-fuchsia-200 dark:border-fuchsia-800" },
];

// ── Helpers ─────────────────────────────────────────────────────────

function hashStr(s: string): number {
    let h = 0;
    for (let i = 0; i < s.length; i++) h = s.charCodeAt(i) + ((h << 5) - h);
    return Math.abs(h);
}

const getPalette = (s: string) => PALETTES[hashStr(s) % PALETTES.length];

/** Parse "09:00 AM" → total minutes from midnight */
function parseMinutes(t: string): number {
    if (!t) return 0;
    const m = t.match(/^(\d{1,2}):(\d{2})\s*(AM|PM)?$/i);
    if (!m) return 0;
    let h = parseInt(m[1]);
    const min = parseInt(m[2]);
    const ap = m[3]?.toUpperCase();
    if (ap === "PM" && h !== 12) h += 12;
    if (ap === "AM" && h === 12) h = 0;
    return h * 60 + min;
}

function fmtHour(h: number): string {
    if (h === 0) return "12 AM";
    if (h === 12) return "12 PM";
    return h > 12 ? `${h - 12} PM` : `${h} AM`;
}

type Block = {
    cls: ClassScheduleData;
    sched: ScheduleEntry;
    dayIdx: number;
    topPx: number;
    heightPx: number;
    col: number;
    totalCols: number;
};

function buildBlocks(data: ClassScheduleData[]): Block[] {
    // Build raw blocks
    const raw: Omit<Block, "col" | "totalCols">[] = [];
    for (const cls of data) {
        for (const sched of cls.schedules) {
            const dayIdx = DAYS.indexOf(sched.day_of_week);
            if (dayIdx < 0) continue;
            const startMin = parseMinutes(sched.start_time);
            const endMin = parseMinutes(sched.end_time);
            const topPx = ((startMin / 60) - HOUR_START) * CELL_H;
            const heightPx = Math.max(((endMin - startMin) / 60) * CELL_H, 20);
            raw.push({ cls, sched, dayIdx, topPx, heightPx });
        }
    }

    // Layout overlapping blocks per day
    const result: Block[] = [];
    for (let d = 0; d < 6; d++) {
        const dayBlocks = raw.filter((b) => b.dayIdx === d).sort((a, b) => a.topPx - b.topPx);
        // Greedy column assignment
        const columns: { end: number }[] = [];
        const assigned = dayBlocks.map((b) => {
            const bEnd = b.topPx + b.heightPx;
            let col = columns.findIndex((c) => c.end <= b.topPx + 1);
            if (col === -1) {
                col = columns.length;
                columns.push({ end: bEnd });
            } else {
                columns[col].end = bEnd;
            }
            return { ...b, col, totalCols: 0 };
        });
        const numCols = columns.length;
        assigned.forEach((b) => {
            b.totalCols = numCols;
            result.push(b as Block);
        });
    }
    return result;
}

/** Convert total minutes from midnight to "HH:MM" (24h) */
function minutesToHHMM(totalMin: number): string {
    const h = Math.floor(totalMin / 60);
    const m = totalMin % 60;
    return `${String(h).padStart(2, "0")}:${String(m).padStart(2, "0")}`;
}

/** Convert total minutes from midnight to "h:mm AM/PM" */
function minutesToDisplay(totalMin: number): string {
    let h = Math.floor(totalMin / 60);
    const m = totalMin % 60;
    const ap = h >= 12 ? "PM" : "AM";
    if (h === 0) h = 12;
    else if (h > 12) h -= 12;
    return `${h}:${String(m).padStart(2, "0")} ${ap}`;
}

const SNAP_MINUTES = 15;

// ── DraggableBlock ─────────────────────────────────────────────────

function DraggableBlock({ id, data, className, style, children }: { id: string; data: DragData; className?: string; style?: React.CSSProperties; children: React.ReactNode }) {
    const { attributes, listeners, setNodeRef, isDragging } = useDraggable({ id, data });
    return (
        <div ref={setNodeRef} {...listeners} {...attributes} className={`${className || ""} ${isDragging ? "opacity-30" : ""}`} style={style}>
            {children}
        </div>
    );
}

// ── DroppableDay ───────────────────────────────────────────────────

function DroppableDay({ dayIdx, children, isOver }: { dayIdx: number; children: React.ReactNode; isOver?: boolean }) {
    const { setNodeRef, isOver: dropIsOver } = useDroppable({ id: `day-${dayIdx}`, data: { dayIdx } });
    const active = isOver ?? dropIsOver;
    return (
        <div ref={setNodeRef} className={`relative border-r last:border-r-0 transition-colors duration-150 ${active ? "bg-primary/5" : ""}`}>
            {children}
        </div>
    );
}

// ── ClassDetailsDialog ──────────────────────────────────────────────

function ClassDetailsDialog({ classItem, open, onOpenChange }: { classItem: ClassScheduleData | null; open: boolean; onOpenChange: (o: boolean) => void }) {
    if (!classItem) return null;
    const pal = getPalette(classItem.subject_code);
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-[480px]">
                <DialogHeader>
                    <div className="mb-1 flex items-center gap-3">
                        <div className={`rounded-xl p-2.5 ${pal.badge}`}><BookOpen className="h-5 w-5" /></div>
                        <div>
                            <DialogTitle>{classItem.subject_code}</DialogTitle>
                            <DialogDescription>{classItem.subject_title}</DialogDescription>
                        </div>
                    </div>
                </DialogHeader>
                <div className="grid gap-4 py-3">
                    <div className="grid grid-cols-2 gap-4">
                        <InfoField icon={<Users className="h-4 w-4" />} label="Section" value={classItem.section} />
                        <InfoField icon={<GraduationCap className="h-4 w-4" />} label="Course" value={classItem.courses || "N/A"} />
                        <InfoField label="Year Level" value={classItem.grade_level || "N/A"} />
                        <InfoField label="Students" value={`${classItem.student_count} Enrolled`} />
                        <div className="col-span-2">
                            <InfoField icon={<UserIcon className="h-4 w-4" />} label="Faculty" value={classItem.faculty_name || "TBA"} />
                        </div>
                    </div>
                    <Separator />
                    <div className="space-y-2">
                        <Label className="text-sm font-semibold">Schedule</Label>
                        {classItem.schedules.length > 0 ? classItem.schedules.map((s, i) => (
                            <div key={i} className={`flex items-center justify-between rounded-lg border p-2.5 ${pal.bg} ${pal.border}`}>
                                <div className="flex items-center gap-2">
                                    <Calendar className={`h-3.5 w-3.5 ${pal.text}`} />
                                    <span className="text-sm font-medium">{s.day_of_week}</span>
                                </div>
                                <div className="flex items-center gap-2">
                                    <span className="text-muted-foreground flex items-center gap-1 text-xs"><Clock className="h-3 w-3" />{s.time_range}</span>
                                    {s.room && <Badge variant="outline" className="text-xs"><MapPin className="mr-0.5 h-3 w-3" />{s.room}</Badge>}
                                </div>
                            </div>
                        )) : <p className="text-muted-foreground text-sm italic">No schedule assigned.</p>}
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    );
}

function InfoField({ icon, label, value }: { icon?: React.ReactNode; label: string; value: string }) {
    return (
        <div className="space-y-0.5">
            <Label className="text-muted-foreground text-[10px] tracking-wider uppercase">{label}</Label>
            <div className="flex items-center gap-1.5 text-sm font-medium text-foreground">{icon && <span className="text-muted-foreground">{icon}</span>}{value}</div>
        </div>
    );
}
// ── WeeklyTimetable ─────────────────────────────────────────────────

function WeeklyTimetable({ data, onBlockClick, conflicts = [], editMode = false, onResizeStart, dropPreview, selectedScheduleId, onScheduleSelect }: { data: ClassScheduleData[]; onBlockClick: (c: ClassScheduleData) => void; conflicts?: ScheduleConflict[]; editMode?: boolean; onResizeStart?: (scheduleId: number, edge: "top" | "bottom", initialY: number, startMin: number, endMin: number) => void; dropPreview?: { day: string; startMin: number; duration: number; subject: string; pal: { bg: string; border: string; text: string; accent: string; badge: string; } } | null; selectedScheduleId?: number | null; onScheduleSelect?: (scheduleId: number | null) => void }) {
    const hours = React.useMemo(() => Array.from({ length: HOUR_END - HOUR_START + 1 }, (_, i) => HOUR_START + i), []);
    const blocks = React.useMemo(() => buildBlocks(data), [data]);
    const totalH = (HOUR_END - HOUR_START + 1) * CELL_H;

    const getConflicts = (cls: ClassScheduleData) => {
        return conflicts.filter(
            (c) =>
                (c.class_1.subject_code === cls.subject_code && c.class_1.section === cls.section) ||
                (c.class_2.subject_code === cls.subject_code && c.class_2.section === cls.section),
        );
    };
    const blocksByDay = React.useMemo(() => {
        const m = new Map<number, Block[]>();
        for (let d = 0; d < 6; d++) m.set(d, []);
        blocks.forEach((b) => m.get(b.dayIdx)?.push(b));
        return m;
    }, [blocks]);

    // Count per day
    const dayCounts = React.useMemo(() => {
        const counts: number[] = [0, 0, 0, 0, 0, 0];
        const seen = new Set<string>();
        blocks.forEach((b) => {
            const key = `${b.dayIdx}-${b.cls.id}`;
            if (!seen.has(key)) { seen.add(key); counts[b.dayIdx]++; }
        });
        return counts;
    }, [blocks]);

    if (data.length === 0) {
        return (
            <div className="flex flex-col items-center justify-center py-20 text-center">
                <div className="bg-muted mb-4 rounded-full p-4"><Calendar className="text-muted-foreground h-10 w-10" /></div>
                <h3 className="mb-1 text-lg font-semibold">No classes to display</h3>
                <p className="text-muted-foreground max-w-sm text-sm">Adjust your filters or try a different combination to see scheduled classes.</p>
            </div>
        );
    }

    const renderBlock = (b: Block, i: number) => {
        const classConflicts = getConflicts(b.cls);
        const hasConflict = classConflicts.length > 0;
        const schedId = b.sched.id;
        
        const pal = getPalette(b.cls.subject_code);
        const w = b.totalCols > 1 ? `calc(${100 / b.totalCols}% - 2px)` : "calc(100% - 4px)";
        const l = b.totalCols > 1 ? `calc(${(b.col / b.totalCols) * 100}% + 2px)` : "2px";
        const blockStyle = { top: b.topPx, height: Math.max(b.heightPx - 2, 18), width: w, left: l };

        const blockInner = (
            <>
                {editMode && <GripVertical className="absolute top-0.5 right-0.5 h-3 w-3 text-muted-foreground opacity-60" />}
                {hasConflict && !editMode && <AlertTriangle className="absolute top-0.5 right-0.5 h-3 w-3 text-red-500 animate-pulse" />}
                
                {editMode && onResizeStart && (
                    <>
                        <div 
                            className="absolute top-0 left-0 right-0 h-2.5 cursor-ns-resize z-30 opacity-0 hover:bg-black/10 dark:hover:bg-white/10 transition-colors" 
                            onPointerDown={(e) => { e.stopPropagation(); onResizeStart(schedId, "top", e.clientY, parseMinutes(b.sched.start_time), parseMinutes(b.sched.end_time)); }}
                            onMouseDown={(e) => e.stopPropagation()}
                            onTouchStart={(e) => e.stopPropagation()}
                        />
                        <div 
                            className="absolute bottom-0 left-0 right-0 h-2.5 cursor-ns-resize z-30 opacity-0 hover:bg-black/10 dark:hover:bg-white/10 transition-colors" 
                            onPointerDown={(e) => { e.stopPropagation(); onResizeStart(schedId, "bottom", e.clientY, parseMinutes(b.sched.start_time), parseMinutes(b.sched.end_time)); }}
                            onMouseDown={(e) => e.stopPropagation()}
                            onTouchStart={(e) => e.stopPropagation()}
                        />
                    </>
                )}

                <div className={`truncate text-[11px] font-bold leading-tight ${hasConflict ? "text-red-700 dark:text-red-300" : pal.text}`}>{b.cls.subject_code}</div>
                {b.heightPx > 28 && <div className="text-muted-foreground truncate text-[10px] leading-tight">{b.cls.section}</div>}
                {b.heightPx > 48 && <div className="text-muted-foreground mt-0.5 flex items-center gap-0.5 truncate text-[9px]"><MapPin className="h-2.5 w-2.5 shrink-0" />{b.sched.room || "—"}</div>}
                {b.heightPx > 64 && b.cls.faculty_name && <div className="text-muted-foreground mt-0.5 flex items-center gap-0.5 truncate text-[9px]"><UserIcon className="h-2.5 w-2.5 shrink-0" />{b.cls.faculty_name}</div>}
            </>
        );

        const isSelected = selectedScheduleId === schedId;
        const baseClassName = `absolute overflow-hidden rounded-md border-l-[3px] ${hasConflict ? "border-l-red-500 ring-2 ring-red-400/50 dark:ring-red-500/40" : pal.accent} ${hasConflict ? "bg-red-500/12 dark:bg-red-400/15" : pal.bg} ${isSelected ? "ring-2 ring-primary z-30" : ""} p-1 text-left transition-all hover:z-20 hover:shadow-lg hover:brightness-95`;

        if (editMode && schedId) {
            const dragData: DragData = {
                block: b,
                scheduleId: schedId,
                originalDay: b.sched.day_of_week,
                originalStartTime: b.sched.start_time,
                originalEndTime: b.sched.end_time,
            };
            return (
                <DraggableBlock
                    key={`${b.cls.id}-${schedId}-${i}`}
                    id={`sched-${schedId}`}
                    data={dragData}
                    className={`${baseClassName} cursor-grab active:cursor-grabbing`}
                    style={blockStyle}
                >
                    <button
                        type="button"
                        className="absolute inset-0 z-40"
                        onClick={(e) => { e.stopPropagation(); onScheduleSelect?.(isSelected ? null : schedId); }}
                        aria-label={`Select ${b.cls.subject_code} schedule`}
                    />
                    <div className="relative z-10 pointer-events-none">{blockInner}</div>
                </DraggableBlock>
            );
        }

        return (
            <Tooltip key={`${b.cls.id}-${i}`}>
                <TooltipTrigger asChild>
                    <button
                        onClick={() => onBlockClick(b.cls)}
                        className={`${baseClassName} cursor-pointer active:scale-[0.98]`}
                        style={blockStyle}
                    >
                        {blockInner}
                    </button>
                </TooltipTrigger>
                <TooltipContent side="right" className="max-w-[260px] space-y-2 p-3">
                    <div>
                        <div className="font-bold">{b.cls.subject_code}</div>
                        <div className="text-muted-foreground text-xs">{b.cls.subject_title}</div>
                    </div>
                    <div className="grid gap-0.5">
                        <div className="text-muted-foreground text-xs">Section: <span className="text-foreground font-medium">{b.cls.section}</span></div>
                        <div className="text-muted-foreground text-xs">Time: <span className="text-foreground font-medium">{b.sched.time_range}</span></div>
                        {b.sched.room && <div className="text-muted-foreground text-xs">Room: <span className="text-foreground font-medium">{b.sched.room}</span></div>}
                        <div className="text-muted-foreground text-xs">Faculty: <span className="text-foreground font-medium">{b.cls.faculty_name || "TBA"}</span></div>
                    </div>
                    {hasConflict && (
                        <div className="mt-2 rounded border border-red-200 bg-red-50 p-2 dark:border-red-900/50 dark:bg-red-950/30">
                            <div className="mb-1 flex items-center gap-1 font-semibold text-red-600 dark:text-red-400 text-xs">
                                <AlertTriangle className="h-3 w-3" /> Conflicts
                            </div>
                            <ul className="grid gap-1">
                                {classConflicts.map((c, idx) => {
                                    const otherClass = c.class_1.subject_code === b.cls.subject_code && c.class_1.section === b.cls.section ? c.class_2 : c.class_1;
                                    return (
                                        <li key={idx} className="text-muted-foreground flex gap-1.5 text-xs">
                                            <span className="mt-0.5 text-red-500">•</span>
                                            <span>
                                                <span className="font-medium text-red-600 dark:text-red-400">{otherClass.subject_code} ({otherClass.section})</span>
                                                {" "}— Same {c.conflict_type === "room" ? "Room" : "Faculty"}
                                            </span>
                                        </li>
                                    );
                                })}
                            </ul>
                        </div>
                    )}
                </TooltipContent>
            </Tooltip>
        );
    };

    return (
        <TooltipProvider delayDuration={200}>
            <ScrollArea className="h-[680px] w-full rounded-xl border">
                <div className="min-w-[860px]">
                    {/* Day headers */}
                    <div className="bg-muted/50 sticky top-0 z-20 grid grid-cols-[60px_repeat(6,1fr)] border-b backdrop-blur">
                        <div className="flex items-center justify-center border-r p-2">
                            <Clock className="text-muted-foreground h-3.5 w-3.5" />
                        </div>
                        {DAYS.map((day, i) => (
                            <div key={day} className="border-r p-2 text-center last:border-r-0">
                                <div className="text-xs font-semibold">{DAYS_SHORT[i]}</div>
                                <div className="text-muted-foreground text-[10px]">{dayCounts[i]} class{dayCounts[i] !== 1 ? "es" : ""}</div>
                            </div>
                        ))}
                    </div>

                    {/* Grid body */}
                    <div className="grid grid-cols-[60px_repeat(6,1fr)]">
                        {/* Time gutter */}
                        <div className="border-r">
                            {hours.map((h) => (
                                <div key={h} className="text-muted-foreground flex items-start justify-end border-b border-dashed pr-2 pt-1 font-mono text-[10px]" style={{ height: CELL_H }}>
                                    {fmtHour(h)}
                                </div>
                            ))}
                        </div>

                        {/* Day columns */}
                        {DAYS.map((dayName, dayIdx) => {
                            const dayContent = (
                                <>
                                    {/* Hour gridlines */}
                                    {hours.map((h) => (
                                        <div key={h} className="border-border/40 absolute w-full border-b border-dashed" style={{ top: (h - HOUR_START) * CELL_H }} />
                                    ))}
                                    {/* Schedule blocks */}
                                    {(blocksByDay.get(dayIdx) || []).map((b, i) => renderBlock(b, i))}
                                    
                                    {/* The Ghost Drop Placeholder */}
                                    {dropPreview && dropPreview.day === dayName && (
                                        <div 
                                            className={`absolute left-[2px] right-[2px] rounded-md border-[2.5px] border-dashed ${dropPreview.pal.accent.replace('border-l-[3px]', 'border-current')} bg-background/60 z-10 pointer-events-none flex items-start p-1.5 backdrop-blur-[1px] opacity-80`}
                                            style={{
                                                top: ((dropPreview.startMin / 60) - HOUR_START) * CELL_H,
                                                height: (dropPreview.duration / 60) * CELL_H
                                            }}
                                        >
                                            <span className={`text-[10px] font-bold tracking-tight uppercase opacity-70 ${dropPreview.pal.text}`}>{dropPreview.subject} (Drop Here)</span>
                                        </div>
                                    )}
                                </>
                            );

                            if (editMode) {
                                return (
                                    <DroppableDay key={dayIdx} dayIdx={dayIdx}>
                                        <div style={{ height: totalH }}>{dayContent}</div>
                                    </DroppableDay>
                                );
                            }
                            return (
                                <div key={dayIdx} className="relative border-r last:border-r-0" style={{ height: totalH }}>
                                    {dayContent}
                                </div>
                            );
                        })}
                    </div>
                </div>
            </ScrollArea>
        </TooltipProvider>
    );
}

// ── ScheduleListView ────────────────────────────────────────────────

function ScheduleListView({ data, onClassClick }: { data: ClassScheduleData[]; onClassClick: (c: ClassScheduleData) => void }) {
    if (data.length === 0) {
        return (
            <div className="flex flex-col items-center justify-center py-16 text-center">
                <Search className="text-muted-foreground mb-3 h-8 w-8 opacity-30" />
                <p className="text-muted-foreground">No classes match your current filters.</p>
            </div>
        );
    }
    return (
        <div className="overflow-hidden rounded-xl border">
            <Table>
                <TableHeader className="bg-muted/50">
                    <TableRow>
                        <TableHead>Subject</TableHead>
                        <TableHead>Section</TableHead>
                        <TableHead className="hidden md:table-cell">Faculty</TableHead>
                        <TableHead className="hidden md:table-cell">Room</TableHead>
                        <TableHead className="hidden lg:table-cell">Schedule</TableHead>
                        <TableHead className="text-right">Students</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {data.map((c) => {
                        const pal = getPalette(c.subject_code);
                        return (
                            <TableRow key={c.id} className="hover:bg-muted/30 cursor-pointer transition-colors" onClick={() => onClassClick(c)}>
                                <TableCell>
                                    <div className="flex items-center gap-2.5">
                                        <div className={`h-9 w-1 shrink-0 rounded-full ${pal.accent.replace("border-l-", "bg-")}`} />
                                        <div>
                                            <div className="font-medium">{c.subject_code}</div>
                                            <div className="text-muted-foreground max-w-[180px] truncate text-xs">{c.subject_title}</div>
                                        </div>
                                    </div>
                                </TableCell>
                                <TableCell><Badge variant="secondary" className={`${pal.badge} text-xs`}>{c.section}</Badge></TableCell>
                                <TableCell className="text-muted-foreground hidden text-sm md:table-cell">{c.faculty_name || "TBA"}</TableCell>
                                <TableCell className="text-muted-foreground hidden text-sm md:table-cell">{c.schedules[0]?.room || "—"}</TableCell>
                                <TableCell className="hidden lg:table-cell">
                                    <div className="flex flex-col gap-0.5">
                                        {c.schedules.slice(0, 2).map((s, i) => (
                                            <span key={i} className="text-muted-foreground text-xs">{s.day_of_week.slice(0, 3)} {s.time_range}</span>
                                        ))}
                                        {c.schedules.length > 2 && <span className="text-muted-foreground text-[10px]">+{c.schedules.length - 2} more</span>}
                                    </div>
                                </TableCell>
                                <TableCell className="text-right font-medium">{c.student_count}</TableCell>
                            </TableRow>
                        );
                    })}
                </TableBody>
            </Table>
        </div>
    );
}
// ── Main Component ──────────────────────────────────────────────────

export default function SchedulingAnalytics({ user, schedule_data, stats, filters, creation_options, defaults }: SchedulingAnalyticsProps) {
    // Filter state
    const [search, setSearch] = React.useState("");
    const [courseFilter, setCourseFilter] = React.useState("all");
    const [yearFilter, setYearFilter] = React.useState("all");
    const [sectionFilter, setSectionFilter] = React.useState("all");
    const [roomFilter, setRoomFilter] = React.useState("all");
    const [facultyFilter, setFacultyFilter] = React.useState("all");

    const [createDialogOpen, setCreateDialogOpen] = React.useState(false);

    // Student search state
    const [studentQuery, setStudentQuery] = React.useState("");
    const [studentResults, setStudentResults] = React.useState<StudentSearchResult[]>([]);
    const [activeStudent, setActiveStudent] = React.useState<StudentSchedule | null>(null);
    const [isSearchingStudent, setIsSearchingStudent] = React.useState(false);
    const [isLoadingStudent, setIsLoadingStudent] = React.useState(false);
    const studentTimeoutRef = React.useRef<ReturnType<typeof setTimeout> | null>(null);

    // View & dialog state
    const [viewMode, setViewMode] = React.useState<"timetable" | "list">("timetable");
    const [selectedClass, setSelectedClass] = React.useState<ClassScheduleData | null>(null);
    const [conflictsExpanded, setConflictsExpanded] = React.useState(false);

    const [editMode, setEditMode] = React.useState(false);
    const [selectedScheduleForRoom, setSelectedScheduleForRoom] = React.useState<number | null>(null);
    const [activeDrag, setActiveDrag] = React.useState<(DragData & { width: number; height: number }) | null>(null);
    const [activeDragDelta, setActiveDragDelta] = React.useState(0);
    const [hoveredDay, setHoveredDay] = React.useState<string | null>(null);
    const [pendingMove, setPendingMove] = React.useState<{
        scheduleId: number;
        subjectCode: string;
        section: string;
        fromDay: string;
        toDay: string;
        fromTime: string;
        toTime: string;
        newStartTime: string;
        newEndTime: string;
    } | null>(null);
    const [isSaving, setIsSaving] = React.useState(false);
    const [localData, setLocalData] = React.useState<ClassScheduleData[]>(schedule_data);
    const [resizing, setResizing] = React.useState<{ scheduleId: number; edge: "top" | "bottom"; initialY: number; originalStartMin: number; originalEndMin: number; currentStartMin?: number; currentEndMin?: number; } | null>(null);
    const localDataRef = React.useRef(localData);
    const resizeTooltipRef = React.useRef<HTMLDivElement>(null);

    // Sync localData when server data changes
    React.useEffect(() => {
        setLocalData(schedule_data);
    }, [schedule_data]);

    React.useEffect(() => {
        localDataRef.current = localData;
    }, [localData]);

    // DnD sensors
    const mouseSensor = useSensor(MouseSensor, { activationConstraint: { distance: 8 } });
    const touchSensor = useSensor(TouchSensor, { activationConstraint: { delay: 200, tolerance: 5 } });
    React.useEffect(() => {
        if (!resizing) return;

        const handlePointerMove = (e: PointerEvent) => {
            const deltaY = e.clientY - resizing.initialY;
            const pxPerMin = CELL_H / 60;
            const deltaMin = Math.round(deltaY / pxPerMin);

            let nStart = resizing.originalStartMin;
            let nEnd = resizing.originalEndMin;
            
            if (resizing.edge === "top") {
                nStart += deltaMin;
                nStart = Math.round(nStart / SNAP_MINUTES) * SNAP_MINUTES;
                nStart = Math.max(HOUR_START * 60, Math.min(nStart, nEnd - SNAP_MINUTES));
            } else {
                nEnd += deltaMin;
                nEnd = Math.round(nEnd / SNAP_MINUTES) * SNAP_MINUTES;
                nEnd = Math.max(nStart + SNAP_MINUTES, Math.min(nEnd, HOUR_END * 60));
            }

            // Only trigger re-render if the snapped 15-min interval actually changed
            if (nStart !== resizing.currentStartMin || nEnd !== resizing.currentEndMin) {
                resizing.currentStartMin = nStart;
                resizing.currentEndMin = nEnd;
                
                setLocalData(prev => prev.map(cls => ({
                    ...cls,
                    schedules: cls.schedules.map(s => {
                        if (s.id === resizing.scheduleId) {
                            return {
                                ...s,
                                start_time: minutesToDisplay(nStart),
                                end_time: minutesToDisplay(nEnd),
                                time_range: `${minutesToDisplay(nStart)} - ${minutesToDisplay(nEnd)}`
                            };
                        }
                        return s;
                    })
                })));
            }

            // Update DOM tooltip directly to avoid React lag
            if (resizeTooltipRef.current) {
                resizeTooltipRef.current.style.opacity = "1";
                resizeTooltipRef.current.style.transform = `translate(${e.clientX + 16}px, ${e.clientY - 16}px)`;
                resizeTooltipRef.current.innerText = `${minutesToDisplay(nStart)} - ${minutesToDisplay(nEnd)}`;
            }
        };

        const handlePointerUp = () => {
            if (resizeTooltipRef.current) resizeTooltipRef.current.style.opacity = "0";
            setResizing(null);
            
            const targetCls = localDataRef.current.find(c => c.schedules.some(s => s.id === resizing.scheduleId));
            const targetSched = targetCls?.schedules.find(s => s.id === resizing.scheduleId);
            if (!targetCls || !targetSched) return;

            const newStartMin = parseMinutes(targetSched.start_time);
            const newEndMin = parseMinutes(targetSched.end_time);

            if (newStartMin !== resizing.originalStartMin || newEndMin !== resizing.originalEndMin) {
                const moveData = {
                    scheduleId: resizing.scheduleId,
                    subjectCode: targetCls.subject_code,
                    section: targetCls.section,
                    fromDay: targetSched.day_of_week,
                    toDay: targetSched.day_of_week,
                    fromTime: minutesToDisplay(resizing.originalStartMin),
                    toTime: minutesToDisplay(newStartMin),
                    newStartTime: minutesToHHMM(newStartMin),
                    newEndTime: minutesToHHMM(newEndMin),
                };

                let hasConflict = false;
                for (const c of localDataRef.current) {
                    for (const s of c.schedules) {
                        if (s.id === resizing.scheduleId) continue;
                        if (s.day_of_week !== targetSched.day_of_week) continue;

                        const sameRoom = targetSched.room_id && s.room_id === targetSched.room_id;
                        const sameFaculty = targetCls.faculty_id && c.faculty_id === targetCls.faculty_id;

                        if (sameRoom || sameFaculty) {
                            const sStart = parseMinutes(s.start_time);
                            const sEnd = parseMinutes(s.end_time);
                            if (newStartMin < sEnd && newEndMin > sStart) {
                                hasConflict = true;
                            }
                        }
                    }
                }

                if (hasConflict) setPendingMove(moveData);
                else executeMove(moveData);
            }
        };

        window.addEventListener("pointermove", handlePointerMove);
        window.addEventListener("pointerup", handlePointerUp);

        return () => {
            window.removeEventListener("pointermove", handlePointerMove);
            window.removeEventListener("pointerup", handlePointerUp);
        };
    }, [resizing]);

    const handleResizeStart = React.useCallback((scheduleId: number, edge: "top" | "bottom", initialY: number, originalStartMin: number, originalEndMin: number) => {
        if (!editMode) return;
        setResizing({ scheduleId, edge, initialY, originalStartMin, originalEndMin });
    }, [editMode]);

    const sensors = useSensors(mouseSensor, touchSensor);

    // Student search handlers
    const searchStudents = React.useCallback(async (q: string) => {
        if (q.length < 2) { setStudentResults([]); return; }
        setIsSearchingStudent(true);
        try {
            const res = await fetch(route("administrators.scheduling-analytics.students.search", { query: q }));
            const data = await res.json();
            setStudentResults(data.students || []);
        } catch { setStudentResults([]); } finally { setIsSearchingStudent(false); }
    }, []);

    const handleStudentQueryChange = (v: string) => {
        setStudentQuery(v);
        if (studentTimeoutRef.current) clearTimeout(studentTimeoutRef.current);
        studentTimeoutRef.current = setTimeout(() => searchStudents(v), 300);
    };

    const selectStudent = async (s: StudentSearchResult) => {
        setIsLoadingStudent(true);
        setStudentQuery("");
        setStudentResults([]);
        try {
            const res = await fetch(route("administrators.scheduling-analytics.students.schedule", { studentId: s.id }));
            setActiveStudent(await res.json());
        } catch { /* ignore */ } finally { setIsLoadingStudent(false); }
    };

    // ── Drag-and-drop handlers ──────────────────────────────────────
    const handleDragStart = (event: DragStartEvent) => {
        const data = event.active.data.current as DragData;
        const rect = event.active.rect.current.initial;
        setActiveDrag({
            ...data,
            width: rect?.width || 200,
            height: rect?.height || 50,
        });
        setActiveDragDelta(0);
    };

    const handleDragMove = React.useCallback((event: DragMoveEvent) => {
        setActiveDragDelta(event.delta.y);
    }, []);

    const handleDragOver = React.useCallback((event: DragOverEvent) => {
        const overDayIdx = event.over?.data?.current?.dayIdx;
        if (overDayIdx !== undefined) {
            setHoveredDay(DAYS[overDayIdx]);
        } else {
            setHoveredDay(null);
        }
    }, []);

    const handleDragEnd = (event: DragEndEvent) => {
        setActiveDrag(null);
        setActiveDragDelta(0);
        setHoveredDay(null);
        const { active, over, delta } = event;
        if (!over || !active.data.current) return;

        const data = active.data.current as DragData;
        const overData = over.data.current as { dayIdx?: number } | undefined;
        const newDayIdx = overData?.dayIdx;
        if (newDayIdx === undefined) return;

        const newDay = DAYS[newDayIdx];
        const b = data.block;

        // Calculate new time from vertical delta
        const pxPerMin = CELL_H / 60;
        const deltaMin = Math.round(delta.y / pxPerMin);
        const oldStartMin = parseMinutes(data.originalStartTime);
        const oldEndMin = parseMinutes(data.originalEndTime);
        const duration = oldEndMin - oldStartMin;

        let newStartMin = oldStartMin + deltaMin;
        // Snap to 15-minute grid
        newStartMin = Math.round(newStartMin / SNAP_MINUTES) * SNAP_MINUTES;
        // Clamp to valid range
        newStartMin = Math.max(HOUR_START * 60, Math.min(newStartMin, HOUR_END * 60 - duration));
        const newEndMin = newStartMin + duration;

        // If nothing changed, skip
        if (newDay === data.originalDay && newStartMin === oldStartMin) return;

        const moveData = {
            scheduleId: data.scheduleId,
            subjectCode: b.cls.subject_code,
            section: b.cls.section,
            fromDay: data.originalDay,
            toDay: newDay,
            fromTime: minutesToDisplay(oldStartMin),
            toTime: minutesToDisplay(newStartMin),
            newStartTime: minutesToHHMM(newStartMin),
            newEndTime: minutesToHHMM(newEndMin),
        };

        const targetCls = localData.find((c) => c.schedules.some((s) => s.id === data.scheduleId));
        const targetSched = targetCls?.schedules.find((s) => s.id === data.scheduleId);
        
        let hasConflict = false;
        if (targetCls && targetSched) {
            for (const c of localData) {
                for (const s of c.schedules) {
                    if (s.id === data.scheduleId) continue;
                    if (s.day_of_week !== newDay) continue;

                    const sameRoom = targetSched.room_id && s.room_id === targetSched.room_id;
                    const sameFaculty = targetCls.faculty_id && c.faculty_id === targetCls.faculty_id;

                    if (sameRoom || sameFaculty) {
                        const sStart = parseMinutes(s.start_time);
                        const sEnd = parseMinutes(s.end_time);
                        if (newStartMin < sEnd && newEndMin > sStart) {
                            hasConflict = true;
                        }
                    }
                }
            }
        }

        if (hasConflict) {
            setPendingMove(moveData);
        } else {
            executeMove(moveData);
        }
    };

    const executeMove = async (move: NonNullable<typeof pendingMove>) => {
        setPendingMove(null);
        setIsSaving(true);

        // Optimistic update
        const prevData = [...localData];
        setLocalData((prev) =>
            prev.map((cls) => ({
                ...cls,
                schedules: cls.schedules.map((s) => {
                    if (s.id === move.scheduleId) {
                        return {
                            ...s,
                            day_of_week: move.toDay,
                            start_time: minutesToDisplay(parseInt(move.newStartTime.split(":")[0]) * 60 + parseInt(move.newStartTime.split(":")[1])),
                            end_time: minutesToDisplay(parseInt(move.newEndTime.split(":")[0]) * 60 + parseInt(move.newEndTime.split(":")[1])),
                            time_range: `${minutesToDisplay(parseInt(move.newStartTime.split(":")[0]) * 60 + parseInt(move.newStartTime.split(":")[1]))} - ${minutesToDisplay(parseInt(move.newEndTime.split(":")[0]) * 60 + parseInt(move.newEndTime.split(":")[1]))}`,
                        };
                    }
                    return s;
                }),
            })),
        );

        try {
            const res = await axios.patch(
                route("administrators.scheduling-analytics.schedules.update", { schedule: move.scheduleId }),
                {
                    day_of_week: move.toDay,
                    start_time: move.newStartTime,
                    end_time: move.newEndTime,
                }
            );

            const result = res.data;
            toast.success(`Moved ${move.subjectCode} (${move.section}) to ${move.toDay} at ${move.toTime}`);

            if (result.conflicts?.length > 0) {
                toast.warning(`${result.conflicts.length} schedule conflict${result.conflicts.length > 1 ? "s" : ""} detected after move.`);
            }

            // Reload data from server to get accurate state
            router.reload({ only: ["schedule_data", "stats", "filters"] });
        } catch (error: unknown) {
            console.error("Schedule update failed", error);
            // Rollback optimistic update
            setLocalData(prevData);
            
            let msg = "An unexpected error occurred.";
            if (axios.isAxiosError(error) && error.response?.data?.message) {
                msg = error.response.data.message;
            } else if (error instanceof Error) {
                msg = error.message;
            }

            toast.error("Failed to update schedule", {
                description: msg,
            });
        } finally {
            setIsSaving(false);
        }
    };

    const confirmMove = () => {
        if (pendingMove) executeMove(pendingMove);
    };

    const cancelMove = () => setPendingMove(null);

    const handleClassCreated = (classItem: ClassScheduleData) => {
        setLocalData((prev) => [...prev, classItem]);
    };

    const assignRoomToSchedule = async (scheduleId: number, roomId: number | null) => {
        const targetCls = localData.find((c) => c.schedules.some((s) => s.id === scheduleId));
        const targetSched = targetCls?.schedules.find((s) => s.id === scheduleId);
        if (!targetCls || !targetSched) return;

        const prevRoomId = targetSched.room_id;

        setLocalData((prev) =>
            prev.map((cls) => ({
                ...cls,
                schedules: cls.schedules.map((s) =>
                    s.id === scheduleId ? { ...s, room_id: roomId, room: roomId ? creation_options.rooms.find((r) => r.id === roomId)?.name ?? null : null } : s,
                ),
            })),
        );

        try {
            const res = await axios.patch(
                route("administrators.scheduling-analytics.schedules.update", { schedule: scheduleId }),
                {
                    day_of_week: targetSched.day_of_week,
                    start_time: targetSched.start_time,
                    end_time: targetSched.end_time,
                    room_id: roomId,
                },
            );

            toast.success(`Room updated for ${targetCls.subject_code}`);

            if (res.data.conflicts?.length > 0) {
                toast.warning(`${res.data.conflicts.length} schedule conflict${res.data.conflicts.length > 1 ? "s" : ""} detected.`);
            }

            router.reload({ only: ["schedule_data", "stats", "filters"] });
        } catch (error: unknown) {
            setLocalData((prev) =>
                prev.map((cls) => ({
                    ...cls,
                    schedules: cls.schedules.map((s) =>
                        s.id === scheduleId ? { ...s, room_id: prevRoomId, room: prevRoomId ? creation_options.rooms.find((r) => r.id === prevRoomId)?.name ?? null : null } : s,
                    ),
                })),
            );

            let msg = "Failed to update room.";
            if (axios.isAxiosError(error) && error.response?.data?.message) {
                msg = error.response.data.message;
            }
            toast.error(msg);
        }
    };

    // Combined filtering
    const filteredData = React.useMemo(() => {
        let d = localData;

        if (search) {
            const q = search.toLowerCase();
            d = d.filter((c) =>
                c.subject_code.toLowerCase().includes(q) ||
                c.subject_title.toLowerCase().includes(q) ||
                c.section.toLowerCase().includes(q) ||
                c.faculty_name?.toLowerCase().includes(q) ||
                c.courses?.toLowerCase().includes(q),
            );
        }
        if (courseFilter !== "all") {
            d = d.filter((c) => c.course_ids?.some((cid) => String(cid) === courseFilter));
        }
        if (yearFilter !== "all") d = d.filter((c) => c.grade_level === yearFilter);
        if (sectionFilter !== "all") d = d.filter((c) => c.section === sectionFilter);
        if (roomFilter !== "all") {
            const id = parseInt(roomFilter);
            d = d.filter((c) => c.schedules.some((s) => s.room_id === id));
        }
        if (facultyFilter !== "all") d = d.filter((c) => c.faculty_id === facultyFilter);

        // Student filter (exclusive)
        if (activeStudent) {
            const classIds = new Set(activeStudent.schedule.map((s) => s.id));
            d = d.filter((c) => classIds.has(c.id));
        }

        return d;
    }, [localData, search, courseFilter, yearFilter, sectionFilter, roomFilter, facultyFilter, activeStudent]);

    const hasFilters = search || courseFilter !== "all" || yearFilter !== "all" || sectionFilter !== "all" || roomFilter !== "all" || facultyFilter !== "all" || activeStudent;

    const clearFilters = () => {
        setSearch("");
        setCourseFilter("all");
        setYearFilter("all");
        setSectionFilter("all");
        setRoomFilter("all");
        setFacultyFilter("all");
        setActiveStudent(null);
        setStudentQuery("");
    };

    const conflicts = React.useMemo(() => {
        if (!hasFilters) return stats.schedule_conflicts;
        return stats.schedule_conflicts.filter((c) => {
            const match = (x: { subject_code: string; section: string }) => filteredData.some((d) => d.subject_code === x.subject_code && d.section === x.section);
            return match(c.class_1) || match(c.class_2);
        });
    }, [filteredData, stats.schedule_conflicts, hasFilters]);

    return (
        <AdminLayout user={user} title="Schedule Overview">
            <Head title="Schedule Overview" />

            <div className="space-y-4">
                {/* ── Header ── */}
                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">Schedule Overview</h1>
                        <p className="text-muted-foreground mt-0.5 text-sm">Bird's-eye view of all academic schedules. Filter by any dimension.</p>
                    </div>
                    <div className="flex items-center gap-2">
                        <Button variant="default" size="sm" onClick={() => setCreateDialogOpen(true)}>
                            <Plus className="mr-1.5 h-3.5 w-3.5" /> Create Class
                        </Button>
                        <Button variant="outline" size="sm" onClick={() => router.reload({ only: ["schedule_data", "stats", "filters"] })}>
                            <RefreshCw className="mr-1.5 h-3.5 w-3.5" /> Sync
                        </Button>
                    </div>
                </div>

                {/* ── Stats row ── */}
                <div className="flex flex-wrap items-center gap-2">
                    <Badge variant="secondary" className="gap-1 px-2.5 py-1 text-xs">
                        <BookOpen className="h-3 w-3" /> {filteredData.length} {hasFilters ? `/ ${stats.total_classes}` : ""} Classes
                    </Badge>
                    <Badge variant="secondary" className="gap-1 px-2.5 py-1 text-xs">
                        <Users className="h-3 w-3" /> {filteredData.reduce((a, c) => a + c.student_count, 0).toLocaleString()} Students
                    </Badge>
                    {conflicts.length > 0 && (
                        <Badge variant="destructive" className="cursor-pointer gap-1 px-2.5 py-1 text-xs" onClick={() => setConflictsExpanded(!conflictsExpanded)}>
                            <AlertTriangle className="h-3 w-3" /> {conflicts.length} Conflict{conflicts.length !== 1 ? "s" : ""}
                        </Badge>
                    )}
                    {activeStudent && (
                        <Badge className="gap-1 bg-indigo-100 px-2.5 py-1 text-xs text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300">
                            <GraduationCap className="h-3 w-3" />
                            {activeStudent.student.name}
                            <button onClick={() => setActiveStudent(null)} className="hover:text-destructive ml-1"><X className="h-3 w-3" /></button>
                        </Badge>
                    )}
                </div>

                {/* ── Conflicts banner ── */}
                {conflictsExpanded && conflicts.length > 0 && (
                    <Card className="border-destructive/30 border">
                        <CardHeader className="pb-2">
                            <div className="flex items-center justify-between">
                                <CardTitle className="text-destructive flex items-center gap-2 text-sm"><AlertTriangle className="h-4 w-4" /> Schedule Conflicts</CardTitle>
                                <Button variant="ghost" size="sm" onClick={() => setConflictsExpanded(false)} className="h-7 px-2"><X className="h-3.5 w-3.5" /></Button>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <p className="text-muted-foreground mb-3 text-xs">Conflicting classes share the same {conflicts.some((c) => c.conflict_type === "room") ? "room" : ""}{conflicts.some((c) => c.conflict_type === "room") && conflicts.some((c) => c.conflict_type === "faculty") ? " or " : ""}{conflicts.some((c) => c.conflict_type === "faculty") ? "faculty member" : ""} at the same time. Look for blocks with <span className="inline-flex items-center gap-0.5 font-medium text-red-600 dark:text-red-400"><AlertTriangle className="inline h-3 w-3" /> red outlines</span> on the timetable.</p>
                            <div className="grid gap-2">
                                {conflicts.map((c, i) => (
                                    <div key={i} className="grid grid-cols-[auto_1fr_auto_1fr] items-center gap-2 rounded-lg border border-red-200 bg-red-50/50 p-3 text-sm dark:border-red-900/50 dark:bg-red-950/20">
                                        <Badge variant="outline" className="border-red-300 text-red-700 dark:border-red-800 dark:text-red-300 shrink-0 text-[10px]">
                                            {c.conflict_type === "room" ? <><Building2 className="mr-0.5 h-3 w-3" />Room</> : <><UserIcon className="mr-0.5 h-3 w-3" />Faculty</>}
                                        </Badge>
                                        <div>
                                            <div className="font-semibold">{c.class_1.subject_code} <span className="text-muted-foreground font-normal">({c.class_1.section})</span></div>
                                            <div className="text-muted-foreground text-xs">{c.class_1.faculty || "TBA"} · {c.class_1.room || "No room"}</div>
                                        </div>
                                        <div className="text-muted-foreground flex flex-col items-center text-[10px]">
                                            <span className="font-semibold text-red-600 dark:text-red-400">{c.day.slice(0, 3)}</span>
                                            <span>{c.time}</span>
                                        </div>
                                        <div>
                                            <div className="font-semibold">{c.class_2.subject_code} <span className="text-muted-foreground font-normal">({c.class_2.section})</span></div>
                                            <div className="text-muted-foreground text-xs">{c.class_2.faculty || "TBA"} · {c.class_2.room || "No room"}</div>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* ── Unified filter bar ── */}
                <Card className="bg-muted/30 border shadow-none">
                    <CardContent className="p-3">
                        <div className="flex flex-wrap items-center gap-2">
                            {/* Text search */}
                            <div className="relative min-w-[180px] flex-1">
                                <Search className="text-muted-foreground absolute top-2.5 left-2.5 h-3.5 w-3.5" />
                                <Input placeholder="Search subjects, faculty..." className="h-9 pl-8 text-sm" value={search} onChange={(e) => setSearch(e.target.value)} />
                            </div>

                            {/* Course */}
                            <Select value={courseFilter} onValueChange={setCourseFilter}>
                                <SelectTrigger className="h-9 w-[150px] text-xs">
                                    <div className="flex items-center gap-1.5"><BookOpen className="text-muted-foreground h-3 w-3" /><SelectValue placeholder="Course" /></div>
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All Courses</SelectItem>
                                    {filters.available_courses.map((c) => <SelectItem key={c.id} value={String(c.id)}>{c.code}</SelectItem>)}
                                </SelectContent>
                            </Select>

                            {/* Year */}
                            <Select value={yearFilter} onValueChange={setYearFilter}>
                                <SelectTrigger className="h-9 w-[130px] text-xs">
                                    <div className="flex items-center gap-1.5"><GraduationCap className="text-muted-foreground h-3 w-3" /><SelectValue placeholder="Year" /></div>
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All Years</SelectItem>
                                    {filters.available_year_levels.map((y) => <SelectItem key={y} value={y}>{y}</SelectItem>)}
                                </SelectContent>
                            </Select>

                            {/* Room */}
                            <Select value={roomFilter} onValueChange={setRoomFilter}>
                                <SelectTrigger className="h-9 w-[140px] text-xs">
                                    <div className="flex items-center gap-1.5"><Building2 className="text-muted-foreground h-3 w-3" /><SelectValue placeholder="Room" /></div>
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All Rooms</SelectItem>
                                    {filters.available_rooms.map((r) => <SelectItem key={r.id} value={String(r.id)}>{r.name}</SelectItem>)}
                                </SelectContent>
                            </Select>

                            {/* Faculty */}
                            <Select value={facultyFilter} onValueChange={setFacultyFilter}>
                                <SelectTrigger className="h-9 w-[160px] text-xs">
                                    <div className="flex items-center gap-1.5"><UserIcon className="text-muted-foreground h-3 w-3" /><SelectValue placeholder="Faculty" /></div>
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All Faculty</SelectItem>
                                    {filters.available_faculty.map((f) => <SelectItem key={f.id} value={f.id}>{f.name}</SelectItem>)}
                                </SelectContent>
                            </Select>

                            {/* Student search */}
                            <div className="relative min-w-[170px]">
                                <GraduationCap className="text-muted-foreground absolute top-2.5 left-2.5 h-3.5 w-3.5" />
                                <Input
                                    placeholder="Find student..."
                                    className="h-9 pl-8 text-sm"
                                    value={studentQuery}
                                    onChange={(e) => handleStudentQueryChange(e.target.value)}
                                />
                                {isSearchingStudent && <Loader2 className="text-muted-foreground absolute top-2.5 right-2.5 h-3.5 w-3.5 animate-spin" />}

                                {studentResults.length > 0 && (
                                    <div className="bg-popover absolute top-full right-0 left-0 z-50 mt-1 max-h-[200px] overflow-auto rounded-lg border shadow-lg">
                                        {studentResults.map((s) => (
                                            <button key={s.id} className="hover:bg-muted flex w-full items-center justify-between px-3 py-2 text-left text-sm" onClick={() => selectStudent(s)}>
                                                <span className="font-medium">{s.name}</span>
                                                <span className="text-muted-foreground text-xs">ID: {s.student_id}</span>
                                            </button>
                                        ))}
                                    </div>
                                )}
                            </div>

                            {/* Clear */}
                            {hasFilters && (
                                <Button variant="ghost" size="sm" onClick={clearFilters} className="text-muted-foreground h-9 px-2.5 text-xs">
                                    <X className="mr-1 h-3 w-3" /> Clear
                                </Button>
                            )}
                        </div>

                        {/* Active filters summary */}
                        {hasFilters && (
                            <div className="text-muted-foreground mt-2 flex items-center gap-1.5 text-xs">
                                Showing <span className="text-foreground font-semibold">{filteredData.length}</span> of {schedule_data.length} classes
                                {isLoadingStudent && <Loader2 className="ml-2 h-3 w-3 animate-spin" />}
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* ── Main schedule view ── */}
                <DndContext sensors={sensors} onDragStart={handleDragStart} onDragMove={handleDragMove} onDragOver={handleDragOver} onDragEnd={handleDragEnd}>
                    {/* Global resize tooltip */}
                    <div 
                        ref={resizeTooltipRef}
                        className="fixed top-0 left-0 z-[9999] pointer-events-none opacity-0 whitespace-nowrap text-foreground font-semibold text-xs bg-background/95 px-2.5 py-1.5 rounded-md shadow-2xl border dark:border-border/50 backdrop-blur-md transition-opacity duration-150 ease-out"
                    />

                    <div className={`flex gap-4 ${editMode && viewMode === "timetable" ? "flex-row" : ""}`}>
                        <Card className={`${editMode && viewMode === "timetable" ? "flex-1" : "w-full"}`}>
                            <CardHeader className="flex flex-row items-center justify-between pb-3">
                                <div>
                                    <CardTitle className="text-base flex items-center gap-2">
                                        Weekly Schedule
                                        {editMode && <Badge variant="outline" className="bg-primary/10 text-primary hover:bg-primary/10 border-primary/20 text-[10px]">Edit Mode</Badge>}
                                    </CardTitle>
                                    <CardDescription className="text-xs">
                                        {editMode ? "Drag and drop classes to reschedule them." : "Click any block for details."}
                                    </CardDescription>
                                </div>
                                <div className="flex items-center gap-4">
                                    {viewMode === "timetable" && (
                                        <div className="flex items-center gap-2">
                                            <Label htmlFor="edit-mode" className="text-xs font-medium cursor-pointer">Edit Mode</Label>
                                            <Switch id="edit-mode" checked={editMode} onCheckedChange={(v) => { setEditMode(v); if (!v) setSelectedScheduleForRoom(null); }} />
                                        </div>
                                    )}
                                    <div className="bg-muted flex rounded-lg p-0.5">
                                        <Button variant={viewMode === "timetable" ? "secondary" : "ghost"} size="sm" onClick={() => { setViewMode("timetable"); }} className="h-7 rounded-md px-2.5 text-xs">
                                            <LayoutGrid className="mr-1 h-3.5 w-3.5" /> Timetable
                                        </Button>
                                        <Button variant={viewMode === "list" ? "secondary" : "ghost"} size="sm" onClick={() => { setViewMode("list"); setEditMode(false); setSelectedScheduleForRoom(null); }} className="h-7 rounded-md px-2.5 text-xs">
                                            <List className="mr-1 h-3.5 w-3.5" /> List
                                        </Button>
                                    </div>
                                </div>
                            </CardHeader>
                            <CardContent className="pt-0">
                                {viewMode === "timetable" ? (() => {
                                    let dropPreview = null;
                                    if (activeDrag) {
                                        const pxPerMin = CELL_H / 60;
                                        const deltaMin = Math.round(activeDragDelta / pxPerMin);
                                        const snappedDeltaMin = Math.round(deltaMin / SNAP_MINUTES) * SNAP_MINUTES;

                                        const oldStartMin = parseMinutes(activeDrag.originalStartTime);
                                        const duration = parseMinutes(activeDrag.originalEndTime) - oldStartMin;

                                        let newStartMin = oldStartMin + snappedDeltaMin;
                                        newStartMin = Math.max(HOUR_START * 60, Math.min(newStartMin, HOUR_END * 60 - duration));

                                        dropPreview = {
                                            day: hoveredDay || activeDrag.originalDay,
                                            startMin: newStartMin,
                                            duration: duration,
                                            subject: activeDrag.block.cls.subject_code,
                                            pal: getPalette(activeDrag.block.cls.subject_code)
                                        };
                                    }
                                    return <WeeklyTimetable data={filteredData} onBlockClick={(c) => { if (editMode) { setSelectedClass(null); } else { setSelectedClass(c); } }} conflicts={conflicts} editMode={editMode} onResizeStart={handleResizeStart} dropPreview={dropPreview} selectedScheduleId={selectedScheduleForRoom} onScheduleSelect={setSelectedScheduleForRoom} />;
                                })() : (
                                    <ScheduleListView data={filteredData} onClassClick={setSelectedClass} />
                                )}
                            </CardContent>
                        </Card>

                        {editMode && viewMode === "timetable" && (
                            <div className="w-[200px] shrink-0">
                                <Card className="h-full">
                                    <CardHeader className="pb-2">
                                        <CardTitle className="text-xs flex items-center gap-1.5">
                                            <Building2 className="h-3.5 w-3.5" /> Rooms
                                        </CardTitle>
                                        <CardDescription className="text-[10px]">
                                            Click to assign selected schedule
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent className="pt-0">
                                        <ScrollArea className="h-[580px]">
                                            <div className="space-y-1.5 pr-2">
                                                {creation_options.rooms.map((room) => {
                                                    const roomSchedules = localData.flatMap((c) =>
                                                        c.schedules.filter((s) => s.room_id === room.id).map((s) => ({ ...s, subject_code: c.subject_code, section: c.section })),
                                                    );
                                                    return (
                                                        <button
                                                            key={room.id}
                                                            type="button"
                                                            onClick={() => {
                                                                if (selectedScheduleForRoom) {
                                                                    assignRoomToSchedule(selectedScheduleForRoom, room.id);
                                                                    setSelectedScheduleForRoom(null);
                                                                }
                                                            }}
                                                            className={`w-full text-left rounded-lg border p-2 transition-colors ${
                                                                selectedScheduleForRoom
                                                                    ? "border-primary/50 bg-primary/5 hover:bg-primary/10 cursor-pointer"
                                                                    : "border-border bg-muted/30 cursor-default"
                                                            }`}
                                                        >
                                                            <div className="flex items-center justify-between">
                                                                <span className="text-xs font-medium truncate">{room.name}</span>
                                                                <span className="text-[10px] text-muted-foreground bg-background px-1 rounded">{roomSchedules.length}</span>
                                                            </div>
                                                            {roomSchedules.length > 0 && (
                                                                <div className="mt-1 space-y-0.5">
                                                                    {roomSchedules.slice(0, 3).map((s, i) => (
                                                                        <div key={i} className="text-[9px] text-muted-foreground truncate">
                                                                            {s.subject_code} — {s.day_of_week.slice(0, 3)} {s.time_range}
                                                                        </div>
                                                                    ))}
                                                                    {roomSchedules.length > 3 && (
                                                                        <div className="text-[9px] text-muted-foreground">+{roomSchedules.length - 3} more</div>
                                                                    )}
                                                                </div>
                                                            )}
                                                        </button>
                                                    );
                                                })}
                                                <button
                                                    type="button"
                                                    onClick={() => {
                                                        if (selectedScheduleForRoom) {
                                                            assignRoomToSchedule(selectedScheduleForRoom, null);
                                                            setSelectedScheduleForRoom(null);
                                                        }
                                                    }}
                                                    className={`w-full text-left rounded-lg border border-dashed p-2 transition-colors ${
                                                        selectedScheduleForRoom
                                                            ? "border-destructive/50 bg-destructive/5 hover:bg-destructive/10 cursor-pointer"
                                                            : "border-border bg-muted/30 cursor-default"
                                                    }`}
                                                >
                                                    <span className="text-xs text-muted-foreground">No Room</span>
                                                </button>
                                            </div>
                                        </ScrollArea>
                                    </CardContent>
                                </Card>
                            </div>
                        )}
                    </div>

                    <DragOverlay dropAnimation={null}>
                        {activeDrag ? (() => {
                            const pal = getPalette(activeDrag.block.cls.subject_code);
                            const classConflicts = conflicts.filter(
                                (c) =>
                                    (c.class_1.subject_code === activeDrag.block.cls.subject_code && c.class_1.section === activeDrag.block.cls.section) ||
                                    (c.class_2.subject_code === activeDrag.block.cls.subject_code && c.class_2.section === activeDrag.block.cls.section)
                            );
                            const hasConflict = classConflicts.length > 0;
                            const baseClassName = `overflow-hidden rounded-md border-l-[3px] ${hasConflict ? "border-l-red-500 ring-2 ring-red-400/50 dark:ring-red-500/40" : pal.accent} ${hasConflict ? "bg-red-500/12 dark:bg-red-400/15" : pal.bg} p-1 text-left shadow-2xl opacity-90`;

                            const pxPerMin = CELL_H / 60;
                            const deltaMin = Math.round(activeDragDelta / pxPerMin);
                            const snappedDeltaMin = Math.round(deltaMin / SNAP_MINUTES) * SNAP_MINUTES;
                            
                            const oldStartMin = parseMinutes(activeDrag.originalStartTime);
                            const oldEndMin = parseMinutes(activeDrag.originalEndTime);
                            const duration = oldEndMin - oldStartMin;
                            
                            let newStartMin = oldStartMin + snappedDeltaMin;
                            newStartMin = Math.max(HOUR_START * 60, Math.min(newStartMin, HOUR_END * 60 - duration));
                            const newEndMin = newStartMin + duration;

                            return (
                                <div style={{ position: 'relative', width: activeDrag.width, height: activeDrag.height, transform: "scale(1.02) rotate(1deg)", transformOrigin: "top left", transition: "transform 0.15s ease-out", zIndex: 9999 }}>
                                    {/* The mirrored block */}
                                    <div
                                        className={baseClassName}
                                        style={{ width: '100%', height: '100%', boxShadow: "0 25px 50px -12px rgba(0, 0, 0, 0.4)" }}
                                    >
                                        <div className={`truncate text-[11px] font-bold leading-tight ${hasConflict ? "text-red-700 dark:text-red-300" : pal.text}`}>{activeDrag.block.cls.subject_code}</div>
                                        {activeDrag.block.heightPx > 28 && <div className="text-muted-foreground truncate text-[10px] leading-tight">{activeDrag.block.cls.section}</div>}
                                        {activeDrag.block.heightPx > 48 && <div className="text-muted-foreground mt-0.5 flex items-center gap-0.5 truncate text-[9px]"><MapPin className="h-2.5 w-2.5 shrink-0" />{activeDrag.block.sched.room || "—"}</div>}
                                        {activeDrag.block.heightPx > 64 && activeDrag.block.cls.faculty_name && <div className="text-muted-foreground mt-0.5 flex items-center gap-0.5 truncate text-[9px]"><UserIcon className="h-2.5 w-2.5 shrink-0" />{activeDrag.block.cls.faculty_name}</div>}
                                    </div>
                                    
                                    {/* The floating tooltip positioned below the block */}
                                    <div className="absolute top-[calc(100%+8px)] left-1/2 -translate-x-1/2 whitespace-nowrap text-foreground font-semibold text-xs bg-background/95 px-2.5 py-1.5 rounded-md shadow-2xl border dark:border-border/50 backdrop-blur-md z-[10000] flex items-center gap-2">
                                        <div className="flex items-center gap-1.5">
                                            <Calendar className="w-3.5 h-3.5 text-primary" />
                                            <span className="text-primary">{hoveredDay || activeDrag.originalDay}</span>
                                        </div>
                                        <div className="w-1 h-1 rounded-full bg-border mx-0.5" />
                                        <div className="flex items-center gap-1.5 text-muted-foreground">
                                            <Clock className="w-3.5 h-3.5" />
                                            <span>{minutesToDisplay(newStartMin)} - {minutesToDisplay(newEndMin)}</span>
                                        </div>
                                    </div>
                                </div>
                            );
                        })() : null}
                    </DragOverlay>
                </DndContext>
            </div>

            <ClassDetailsDialog classItem={selectedClass} open={!!selectedClass} onOpenChange={(o) => !o && setSelectedClass(null)} />

            {/* Move Confirmation Dialog */}
            <AlertDialog open={!!pendingMove} onOpenChange={(o) => !o && cancelMove()}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Confirm Schedule Change</AlertDialogTitle>
                        <AlertDialogDescription>
                            Are you sure you want to move <strong>{pendingMove?.subjectCode} ({pendingMove?.section})</strong>?
                            <div className="mt-4 space-y-2 rounded-lg bg-muted p-3">
                                <div className="grid grid-cols-[1fr_auto_1fr] items-center gap-2 text-sm">
                                    <div className="text-right">
                                        <div className="font-semibold">{pendingMove?.fromDay}</div>
                                        <div className="text-muted-foreground">{pendingMove?.fromTime}</div>
                                    </div>
                                    <div className="text-muted-foreground px-2">→</div>
                                    <div>
                                        <div className="font-semibold text-primary">{pendingMove?.toDay}</div>
                                        <div className="text-primary font-medium">{pendingMove?.toTime}</div>
                                    </div>
                                </div>
                            </div>
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel disabled={isSaving}>Cancel</AlertDialogCancel>
                        <AlertDialogAction onClick={confirmMove} disabled={isSaving}>
                            {isSaving ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : "Confirm Move"}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>

            <CreateClassDialog
                open={createDialogOpen}
                onOpenChange={setCreateDialogOpen}
                options={creation_options}
                defaults={defaults}
                onClassCreated={handleClassCreated}
            />
        </AdminLayout>
    );
}
