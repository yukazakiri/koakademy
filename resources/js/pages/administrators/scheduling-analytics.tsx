import AdminLayout from "@/components/administrators/admin-layout";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { ScrollArea } from "@/components/ui/scroll-area";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Separator } from "@/components/ui/separator";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from "@/components/ui/tooltip";
import type { User } from "@/types/user";
import { Head, router } from "@inertiajs/react";
import {
    AlertTriangle,
    BookOpen,
    Building2,
    Calendar,
    ChevronDown,
    Clock,
    GraduationCap,
    LayoutGrid,
    List,
    Loader2,
    MapPin,
    RefreshCw,
    Search,
    User as UserIcon,
    Users,
    X,
} from "lucide-react";
import * as React from "react";
import { route } from "ziggy-js";

// ── Types ──────────────────────────────────────────────────────────

type ScheduleEntry = {
    day_of_week: string;
    start_time: string;
    end_time: string;
    time_range: string;
    room: string | null;
    room_id?: number | null;
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
            <div className="flex items-center gap-1.5 text-sm font-medium">{icon && <span className="text-muted-foreground">{icon}</span>}{value}</div>
        </div>
    );
}
// ── WeeklyTimetable ─────────────────────────────────────────────────

function WeeklyTimetable({ data, onBlockClick }: { data: ClassScheduleData[]; onBlockClick: (c: ClassScheduleData) => void }) {
    const hours = React.useMemo(() => Array.from({ length: HOUR_END - HOUR_START + 1 }, (_, i) => HOUR_START + i), []);
    const blocks = React.useMemo(() => buildBlocks(data), [data]);
    const totalH = (HOUR_END - HOUR_START + 1) * CELL_H;
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
                        {DAYS.map((_, dayIdx) => (
                            <div key={dayIdx} className="relative border-r last:border-r-0" style={{ height: totalH }}>
                                {/* Hour gridlines */}
                                {hours.map((h) => (
                                    <div key={h} className="border-border/40 absolute w-full border-b border-dashed" style={{ top: (h - HOUR_START) * CELL_H }} />
                                ))}

                                {/* Schedule blocks */}
                                {(blocksByDay.get(dayIdx) || []).map((b, i) => {
                                    const pal = getPalette(b.cls.subject_code);
                                    const w = b.totalCols > 1 ? `calc(${100 / b.totalCols}% - 2px)` : "calc(100% - 4px)";
                                    const l = b.totalCols > 1 ? `calc(${(b.col / b.totalCols) * 100}% + 2px)` : "2px";
                                    return (
                                        <Tooltip key={`${b.cls.id}-${i}`}>
                                            <TooltipTrigger asChild>
                                                <button
                                                    onClick={() => onBlockClick(b.cls)}
                                                    className={`absolute overflow-hidden rounded-md border-l-[3px] ${pal.accent} ${pal.bg} cursor-pointer p-1 text-left transition-all hover:z-20 hover:shadow-lg hover:brightness-95 active:scale-[0.98]`}
                                                    style={{ top: b.topPx, height: Math.max(b.heightPx - 2, 18), width: w, left: l }}
                                                >
                                                    <div className={`truncate text-[11px] font-bold leading-tight ${pal.text}`}>{b.cls.subject_code}</div>
                                                    {b.heightPx > 28 && <div className="text-muted-foreground truncate text-[10px] leading-tight">{b.cls.section}</div>}
                                                    {b.heightPx > 48 && <div className="text-muted-foreground mt-0.5 flex items-center gap-0.5 truncate text-[9px]"><MapPin className="h-2.5 w-2.5 shrink-0" />{b.sched.room || "—"}</div>}
                                                    {b.heightPx > 64 && b.cls.faculty_name && <div className="text-muted-foreground mt-0.5 flex items-center gap-0.5 truncate text-[9px]"><UserIcon className="h-2.5 w-2.5 shrink-0" />{b.cls.faculty_name}</div>}
                                                </button>
                                            </TooltipTrigger>
                                            <TooltipContent side="right" className="max-w-[220px] space-y-0.5">
                                                <div className="font-bold">{b.cls.subject_code}</div>
                                                <div className="text-xs">{b.cls.subject_title}</div>
                                                <div className="text-muted-foreground text-xs">Section: {b.cls.section}</div>
                                                <div className="text-muted-foreground text-xs">{b.sched.time_range}</div>
                                                {b.sched.room && <div className="text-muted-foreground text-xs">Room: {b.sched.room}</div>}
                                                <div className="text-muted-foreground text-xs">Faculty: {b.cls.faculty_name || "TBA"}</div>
                                            </TooltipContent>
                                        </Tooltip>
                                    );
                                })}
                            </div>
                        ))}
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

export default function SchedulingAnalytics({ user, schedule_data, stats, filters }: SchedulingAnalyticsProps) {
    // Filter state
    const [search, setSearch] = React.useState("");
    const [courseFilter, setCourseFilter] = React.useState("all");
    const [yearFilter, setYearFilter] = React.useState("all");
    const [sectionFilter, setSectionFilter] = React.useState("all");
    const [roomFilter, setRoomFilter] = React.useState("all");
    const [facultyFilter, setFacultyFilter] = React.useState("all");

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

    // Combined filtering
    const filteredData = React.useMemo(() => {
        let d = schedule_data;

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
            const id = parseInt(courseFilter);
            d = d.filter((c) => c.course_ids?.includes(id));
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
    }, [schedule_data, search, courseFilter, yearFilter, sectionFilter, roomFilter, facultyFilter, activeStudent]);

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
                    <Button variant="outline" size="sm" onClick={() => router.reload({ only: ["schedule_data", "stats", "filters"] })}>
                        <RefreshCw className="mr-1.5 h-3.5 w-3.5" /> Sync
                    </Button>
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
                            <div className="grid gap-2">
                                {conflicts.map((c, i) => (
                                    <div key={i} className="bg-destructive/5 flex items-center gap-3 rounded-lg border p-3 text-sm">
                                        <Badge variant="outline" className="shrink-0 text-[10px]">{c.conflict_type === "room" ? "Room" : "Faculty"}</Badge>
                                        <span className="text-muted-foreground">{c.day} {c.time}</span>
                                        <span className="font-medium">{c.class_1.subject_code} ({c.class_1.section})</span>
                                        <span className="text-muted-foreground">vs</span>
                                        <span className="font-medium">{c.class_2.subject_code} ({c.class_2.section})</span>
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
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between pb-3">
                        <div>
                            <CardTitle className="text-base">Weekly Schedule</CardTitle>
                            <CardDescription className="text-xs">Click any block for details</CardDescription>
                        </div>
                        <div className="bg-muted flex rounded-lg p-0.5">
                            <Button variant={viewMode === "timetable" ? "secondary" : "ghost"} size="sm" onClick={() => setViewMode("timetable")} className="h-7 rounded-md px-2.5 text-xs">
                                <LayoutGrid className="mr-1 h-3.5 w-3.5" /> Timetable
                            </Button>
                            <Button variant={viewMode === "list" ? "secondary" : "ghost"} size="sm" onClick={() => setViewMode("list")} className="h-7 rounded-md px-2.5 text-xs">
                                <List className="mr-1 h-3.5 w-3.5" /> List
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent className="pt-0">
                        {viewMode === "timetable" ? (
                            <WeeklyTimetable data={filteredData} onBlockClick={setSelectedClass} />
                        ) : (
                            <ScheduleListView data={filteredData} onClassClick={setSelectedClass} />
                        )}
                    </CardContent>
                </Card>
            </div>

            <ClassDetailsDialog classItem={selectedClass} open={!!selectedClass} onOpenChange={(o) => !o && setSelectedClass(null)} />
        </AdminLayout>
    );
}
