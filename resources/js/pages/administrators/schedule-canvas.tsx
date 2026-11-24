import AdminLayout from "@/components/administrators/admin-layout";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Input } from "@/components/ui/input";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from "@/components/ui/sheet";
import { Switch } from "@/components/ui/switch";
import { show as showClass } from "@/routes/administrators/classes";
import { index as boardIndex } from "@/routes/administrators/schedule-board";
import studentRoutes from "@/routes/administrators/scheduling-analytics/students";
import { Head, Link, router } from "@inertiajs/react";
import {
    AlertTriangle,
    ArrowRightLeft,
    ArrowUpRight,
    Building2,
    CalendarDays,
    ChevronLeft,
    ChevronRight,
    Columns3,
    Download,
    Eye,
    Filter,
    GraduationCap,
    Loader2,
    Monitor,
    MoreHorizontal,
    Pencil,
    Plus,
    Redo2,
    Search,
    Send,
    SlidersHorizontal,
    Sparkles,
    Sun,
    Trash2,
    Undo2,
    User,
    X,
} from "lucide-react";
import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import GenerateOfferingsDialog from "./components/generate-offerings-dialog";
import ScheduleExportDialog from "./components/schedule-export-dialog";
import SchedulingPolicyDialog from "./components/scheduling-policy-dialog";
import SchedulingReviewDialog from "./components/scheduling-review-dialog";
import type {
    ClassScheduleData,
    SchedulingAnalyticsProps,
    StudentSearchResult,
    SuggestedRoom,
    SuggestedSlot,
} from "./components/scheduling-types";
import BlockPopover, { type InlineBlockPatch } from "./schedule-canvas/components/block-popover";
import {
    FloatingContextMenu,
    FloatingMenuItem,
    FloatingMenuLabel,
    FloatingMenuSeparator,
} from "./schedule-canvas/components/floating-context-menu";
import QuickAddSheet, { type QuickAddTarget } from "./schedule-canvas/components/quick-add-sheet";
import {
    buildCohortWeekModel,
    buildFacultyDirectory,
    buildRoomCompareDayModel,
    buildRoomDirectory,
    buildRoomWeekModel,
    buildStudentStyleWeekModel,
    buildTeacherWeekModel,
    CANVAS_END_MIN,
    CANVAS_HOURS,
    CANVAS_START_MIN,
    extractCohortOptions,
    formatCompactRange,
    formatMinutes12h,
    getCanvasDays,
    isOnlineMode,
    minutesToApiTime,
    snapMinutes,
    SUBJECT_TONES,
    type CanvasBlock,
    type OpenGap,
    type TeacherHandoff,
} from "./schedule-canvas/lib/canvas-engine";
import { useScheduleStudio } from "./scheduling/hooks/use-schedule-studio";

type CanvasTab = "rooms" | "students" | "teachers";
type RoomViewMode = "week" | "compare_day";
type StudentSubMode = "section" | "individual";
type DeliveryFilter = "all" | "face_to_face" | "online";

const HOUR_HEIGHT = 64;
const TOTAL_GRID_HEIGHT = CANVAS_HOURS.length * HOUR_HEIGHT;
const MAX_VISIBLE_LANES = 3;

function minToPx(minutes: number): number {
    return ((minutes - CANVAS_START_MIN) / 60) * HOUR_HEIGHT;
}

function durationToPx(durationMin: number): number {
    return Math.max(30, (durationMin / 60) * HOUR_HEIGHT);
}

export default function ScheduleCanvas(props: SchedulingAnalyticsProps) {
    const { user, term_context, filters, creation_options } = props;
    const studio = useScheduleStudio(props);

    const [activeTab, setActiveTab] = useState<CanvasTab>("rooms");
    const gridScrollRef = useRef<HTMLDivElement | null>(null);

    // ── SEARCH & SLIDING FILTER SHEET STATE ──
    const [searchQuery, setSearchQuery] = useState("");
    const [filterSheetOpen, setFilterSheetOpen] = useState(false);
    const [conflictsOnly, setConflictsOnly] = useState(false);
    const [deliveryFilter, setDeliveryFilter] = useState<DeliveryFilter>("all");
    const [roomTypeFilter, setRoomTypeFilter] = useState<string>("all");
    const [departmentFilter, setDepartmentFilter] = useState<string>("all");

    const activeFilterCount =
        (conflictsOnly ? 1 : 0) +
        (deliveryFilter !== "all" ? 1 : 0) +
        (roomTypeFilter !== "all" ? 1 : 0) +
        (departmentFilter !== "all" ? 1 : 0);

    const resetAllFilters = useCallback(() => {
        setSearchQuery("");
        setConflictsOnly(false);
        setDeliveryFilter("all");
        setRoomTypeFilter("all");
        setDepartmentFilter("all");
    }, []);

    const days = useMemo(
        () => getCanvasDays(studio.policy.allow_sunday_scheduling),
        [studio.policy.allow_sunday_scheduling],
    );

    const conflictScheduleIds = useMemo(() => {
        const set = new Set<number>();
        for (const c of studio.conflicts) {
            if (typeof c.schedule_1_id === "number") set.add(c.schedule_1_id);
            if (typeof c.schedule_2_id === "number") set.add(c.schedule_2_id);
        }
        return set;
    }, [studio.conflicts]);

    const filteredClasses = useMemo(() => {
        const q = searchQuery.trim().toLowerCase();
        if (!q && !conflictsOnly && deliveryFilter === "all") {
            return studio.classes;
        }

        return studio.classes
            .map((cls) => {
                const classTextMatches =
                    !q ||
                    cls.subject_code.toLowerCase().includes(q) ||
                    cls.subject_title.toLowerCase().includes(q) ||
                    (cls.faculty_name ?? "").toLowerCase().includes(q) ||
                    (cls.courses ?? "").toLowerCase().includes(q) ||
                    (cls.section ?? "").toLowerCase().includes(q);

                const matchingSchedules = cls.schedules.filter((s) => {
                    const online = isOnlineMode(s.delivery_mode ?? cls.delivery_mode);
                    if (deliveryFilter === "online" && !online) return false;
                    if (deliveryFilter === "face_to_face" && online) return false;
                    if (conflictsOnly && (s.id === undefined || !conflictScheduleIds.has(s.id))) return false;
                    if (!classTextMatches) {
                        const roomMatches = (s.room ?? "").toLowerCase().includes(q);
                        if (!roomMatches) return false;
                    }
                    return true;
                });

                return { ...cls, schedules: matchingSchedules };
            })
            .filter((cls) => cls.schedules.length > 0);
    }, [studio.classes, searchQuery, conflictsOnly, deliveryFilter, conflictScheduleIds]);

    const availableRoomTypes = useMemo(() => {
        const set = new Set<string>();
        for (const r of creation_options.rooms) {
            if (r.type) set.add(r.type);
        }
        return [...set].sort((a, b) => a.localeCompare(b));
    }, [creation_options.rooms]);

    const availableDepartments = useMemo(() => {
        const set = new Set<string>();
        for (const f of creation_options.faculty) {
            if (f.department) set.add(f.department);
        }
        return [...set].sort((a, b) => a.localeCompare(b));
    }, [creation_options.faculty]);

    // ── TAB 1 STATE: ROOMS ──
    const filteredRoomOptions = useMemo(() => {
        return creation_options.rooms.filter((r) => {
            if (roomTypeFilter !== "all" && (r.type ?? "lecture") !== roomTypeFilter) return false;
            return true;
        });
    }, [creation_options.rooms, roomTypeFilter]);

    const roomDirectory = useMemo(
        () => buildRoomDirectory(filteredRoomOptions, filteredClasses, studio.conflicts),
        [filteredRoomOptions, filteredClasses, studio.conflicts],
    );
    const [selectedRoomId, setSelectedRoomId] = useState<number | null>(
        () => roomDirectory[0]?.room.id ?? creation_options.rooms[0]?.id ?? null,
    );
    const [roomViewMode, setRoomViewMode] = useState<RoomViewMode>("week");
    const [compareDay, setCompareDay] = useState<string>("Monday");
    const [comparePage, setComparePage] = useState(0);

    const activeRoomIndex = Math.max(
        0,
        roomDirectory.findIndex((r) => r.room.id === selectedRoomId),
    );
    const activeRoomEntry = roomDirectory[activeRoomIndex] ?? roomDirectory[0] ?? null;
    const activeRoom = activeRoomEntry?.room ?? null;

    const roomWeek = useMemo(
        () =>
            buildRoomWeekModel({
                room: activeRoom,
                days,
                classes: filteredClasses,
                conflicts: studio.conflicts,
            }),
        [activeRoom, days, filteredClasses, studio.conflicts],
    );

    const COMPARE_PAGE_SIZE = 6;
    const compareRoomsSlice = useMemo(() => {
        const ordered = roomDirectory.map((r) => r.room);
        const maxPage = Math.max(0, Math.ceil(ordered.length / COMPARE_PAGE_SIZE) - 1);
        const safePage = Math.min(comparePage, maxPage);
        const start = safePage * COMPARE_PAGE_SIZE;
        return ordered.slice(start, start + COMPARE_PAGE_SIZE);
    }, [roomDirectory, comparePage]);

    const compareDayColumns = useMemo(
        () =>
            buildRoomCompareDayModel({
                day: compareDay,
                rooms: compareRoomsSlice,
                classes: filteredClasses,
                conflicts: studio.conflicts,
            }),
        [compareDay, compareRoomsSlice, filteredClasses, studio.conflicts],
    );

    // ── TAB 2 STATE: STUDENTS & SECTIONS ──
    const cohortOptions = useMemo(() => extractCohortOptions(studio.classes), [studio.classes]);
    const [studentSubMode, setStudentSubMode] = useState<StudentSubMode>("section");
    const [selectedProgram, setSelectedProgram] = useState<string>(
        () => cohortOptions.programs[0] ?? "General",
    );
    const availableYears = cohortOptions.yearLevelsByProgram[selectedProgram] ?? ["1st Year"];
    const [selectedYearLevel, setSelectedYearLevel] = useState<string>(
        () => availableYears[0] ?? "1st Year",
    );
    const safeYearLevel = availableYears.includes(selectedYearLevel)
        ? selectedYearLevel
        : (availableYears[0] ?? "1st Year");
    const availableSections =
        cohortOptions.sectionsByProgramYear[`${selectedProgram}::${safeYearLevel}`] ?? ["A"];
    const [selectedSection, setSelectedSection] = useState<string>(
        () => availableSections[0] ?? "A",
    );
    const safeSection = availableSections.includes(selectedSection)
        ? selectedSection
        : (availableSections[0] ?? "A");

    const [studentQuery, setStudentQuery] = useState("");
    const [studentResults, setStudentResults] = useState<StudentSearchResult[]>([]);
    const [searchingStudents, setSearchingStudents] = useState(false);
    const [selectedStudent, setSelectedStudent] = useState<StudentSearchResult | null>(null);
    const [studentClasses, setStudentClasses] = useState<ClassScheduleData[]>([]);
    const [loadingStudentSchedule, setLoadingStudentSchedule] = useState(false);

    useEffect(() => {
        if (studentSubMode !== "individual") return;
        const trimmed = studentQuery.trim();
        if (trimmed.length < 2) {
            setStudentResults([]);
            return;
        }
        const timer = window.setTimeout(async () => {
            setSearchingStudents(true);
            try {
                const res = await fetch(
                    studentRoutes.search.url({ query: { query: trimmed } }),
                    { headers: { Accept: "application/json" } },
                );
                if (res.ok) {
                    const data = (await res.json()) as { students?: StudentSearchResult[] };
                    setStudentResults(data.students ?? []);
                }
            } finally {
                setSearchingStudents(false);
            }
        }, 240);
        return () => window.clearTimeout(timer);
    }, [studentQuery, studentSubMode]);

    const selectIndividualStudent = useCallback(async (student: StudentSearchResult) => {
        setSelectedStudent(student);
        setStudentQuery("");
        setStudentResults([]);
        setLoadingStudentSchedule(true);
        try {
            const res = await fetch(studentRoutes.schedule.url(student.id), {
                headers: { Accept: "application/json" },
            });
            if (res.ok) {
                const data = (await res.json()) as { schedules?: ClassScheduleData[] };
                setStudentClasses(data.schedules ?? []);
            }
        } finally {
            setLoadingStudentSchedule(false);
        }
    }, []);

    const cohortWeek = useMemo(() => {
        if (studentSubMode === "individual") {
            return buildStudentStyleWeekModel(studentClasses, days, studio.conflicts);
        }
        return buildCohortWeekModel({
            program: selectedProgram,
            yearLevel: safeYearLevel,
            section: safeSection,
            days,
            classes: filteredClasses,
            conflicts: studio.conflicts,
        });
    }, [
        studentSubMode,
        studentClasses,
        selectedProgram,
        safeYearLevel,
        safeSection,
        days,
        filteredClasses,
        studio.conflicts,
    ]);

    // ── TAB 3 STATE: TEACHERS ──
    const filteredFacultyOptions = useMemo(() => {
        return creation_options.faculty.filter((f) => {
            if (departmentFilter !== "all" && (f.department ?? "") !== departmentFilter) return false;
            return true;
        });
    }, [creation_options.faculty, departmentFilter]);

    const facultyDirectory = useMemo(
        () => buildFacultyDirectory(filteredFacultyOptions, filteredClasses, studio.conflicts),
        [filteredFacultyOptions, filteredClasses, studio.conflicts],
    );
    const [selectedFacultyId, setSelectedFacultyId] = useState<string | null>(
        () => facultyDirectory[0]?.faculty.id ?? creation_options.faculty[0]?.id ?? null,
    );
    const activeFacultyIndex = Math.max(
        0,
        facultyDirectory.findIndex((f) => String(f.faculty.id) === String(selectedFacultyId)),
    );
    const activeFacultyEntry = facultyDirectory[activeFacultyIndex] ?? facultyDirectory[0] ?? null;
    const activeFaculty = activeFacultyEntry?.faculty ?? null;

    const teacherWeek = useMemo(
        () =>
            buildTeacherWeekModel({
                faculty: activeFaculty,
                days,
                classes: filteredClasses,
                conflicts: studio.conflicts,
            }),
        [activeFaculty, days, filteredClasses, studio.conflicts],
    );

    // ── INTERACTION STATE (All Sliding Sheets + Drag & Drop + Right-Click Context Menu) ──
    const [inspectedBlock, setInspectedBlock] = useState<{ block: CanvasBlock; mode: "view" | "edit" } | null>(null);
    const [clusterBlocks, setClusterBlocks] = useState<{ title: string; blocks: CanvasBlock[] } | null>(null);
    const [quickAddTarget, setQuickAddTarget] = useState<QuickAddTarget | null>(null);
    const [draggedBlock, setDraggedBlock] = useState<CanvasBlock | null>(null);
    const [dragPreview, setDragPreview] = useState<{ columnKey: string; startMin: number; durationMin: number } | null>(null);
    const [contextMenuTarget, setContextMenuTarget] = useState<
        | { kind: "block"; x: number; y: number; block: CanvasBlock }
        | { kind: "empty"; x: number; y: number; day: string; startMin: number; endMin: number; roomId?: number | null; roomName?: string }
        | null
    >(null);

    useEffect(() => {
        if (gridScrollRef.current) {
            gridScrollRef.current.scrollTop = 0;
        }
    }, []);

    useEffect(() => {
        if (inspectedBlock?.block.hasConflict && inspectedBlock.block.session.id !== undefined) {
            void studio.loadSuggestionsForSchedule(inspectedBlock.block.session.id);
        }
    }, [inspectedBlock?.block.session.id, inspectedBlock?.block.hasConflict, studio.loadSuggestionsForSchedule]);

    const inspectedSuggestions =
        inspectedBlock?.block.session.id !== undefined
            ? studio.suggestionsCache[inspectedBlock.block.session.id]
            : undefined;
    const inspectedLoadingSuggestions =
        inspectedBlock?.block.session.id !== undefined &&
        studio.loadingSuggestionsFor === inspectedBlock.block.session.id;

    // ── DRAG & DROP HANDLERS ──
    const handleColumnDragOver = (
        event: React.DragEvent<HTMLDivElement>,
        columnKey: string,
    ) => {
        if (!draggedBlock) return;
        event.preventDefault();
        event.dataTransfer.dropEffect = "move";
        const rect = event.currentTarget.getBoundingClientRect();
        const offsetY = Math.max(0, event.clientY - rect.top);
        const rawMin = CANVAS_START_MIN + (offsetY / HOUR_HEIGHT) * 60;
        const snapped = Math.max(
            CANVAS_START_MIN,
            Math.min(CANVAS_END_MIN - draggedBlock.durationMin, snapMinutes(rawMin, 15)),
        );
        setDragPreview({ columnKey, startMin: snapped, durationMin: draggedBlock.durationMin });
    };

    const handleColumnDrop = async (
        event: React.DragEvent<HTMLDivElement>,
        target: { day: string; roomId?: number },
    ) => {
        event.preventDefault();
        if (!draggedBlock) return;
        const rect = event.currentTarget.getBoundingClientRect();
        const offsetY = Math.max(0, event.clientY - rect.top);
        const rawMin = CANVAS_START_MIN + (offsetY / HOUR_HEIGHT) * 60;
        const snappedStart = Math.max(
            CANVAS_START_MIN,
            Math.min(CANVAS_END_MIN - draggedBlock.durationMin, snapMinutes(rawMin, 15)),
        );
        const snappedEnd = snappedStart + draggedBlock.durationMin;

        const blockToMove = draggedBlock;
        setDraggedBlock(null);
        setDragPreview(null);

        const patch: {
            day_of_week: string;
            start_time: string;
            end_time: string;
            room_id?: number;
        } = {
            day_of_week: target.day,
            start_time: minutesToApiTime(snappedStart),
            end_time: minutesToApiTime(snappedEnd),
        };
        if (target.roomId !== undefined) {
            patch.room_id = target.roomId;
        }

        await studio.updateScheduleBlockDirect(
            blockToMove.classItem,
            blockToMove.session,
            patch,
            `Moved ${blockToMove.classItem.subject_code} to ${target.day} ${formatCompactRange(snappedStart, snappedEnd)}`,
        );
    };

    // Capture exact clicked time on right-click in empty calendar column
    const captureEmptyContextSlot = (
        event: React.MouseEvent<HTMLDivElement>,
        day: string,
        roomId?: number | null,
        roomName?: string,
    ) => {
        if ((event.target as HTMLElement | null)?.closest("[data-canvas-block]")) {
            return;
        }
        event.preventDefault();
        const rect = event.currentTarget.getBoundingClientRect();
        const offsetY = Math.max(0, event.clientY - rect.top);
        const rawMin = CANVAS_START_MIN + (offsetY / HOUR_HEIGHT) * 60;
        const startMin = Math.max(CANVAS_START_MIN, Math.min(CANVAS_END_MIN - 60, snapMinutes(rawMin, 30)));
        const endMin = Math.min(CANVAS_END_MIN, startMin + 90);
        setContextMenuTarget({
            kind: "empty",
            x: event.clientX,
            y: event.clientY,
            day,
            startMin,
            endMin,
            roomId,
            roomName,
        });
    };

    // ── INLINE SLIDING SHEET SAVE & SMART FIX HANDLERS ──
    const handleSaveInlineEdit = async (block: CanvasBlock, patch: InlineBlockPatch) => {
        await studio.updateScheduleBlockDirect(
            block.classItem,
            block.session,
            patch,
            `Updated ${block.classItem.subject_code}`,
        );
    };

    const handleApplyRoomFix = async (block: CanvasBlock, room: SuggestedRoom) => {
        await studio.updateScheduleBlockDirect(
            block.classItem,
            block.session,
            { room_id: room.room_id, delivery_mode: "face_to_face" },
            `Moved ${block.classItem.subject_code} to ${room.name}`,
        );
    };

    const handleApplySlotFix = async (block: CanvasBlock, slot: SuggestedSlot) => {
        await studio.updateScheduleBlockDirect(
            block.classItem,
            block.session,
            {
                day_of_week: slot.day_of_week,
                start_time: slot.start_time,
                end_time: slot.end_time,
                room_id: slot.room_id,
            },
            `Moved ${block.classItem.subject_code} to ${slot.day_of_week} ${slot.time_range}`,
        );
    };

    const handleConvertOnlineFix = async (block: CanvasBlock) => {
        await studio.updateScheduleBlockDirect(
            block.classItem,
            block.session,
            { delivery_mode: studio.policy.default_online_delivery_mode, room_id: null },
            `Switched ${block.classItem.subject_code} to Online`,
        );
    };

    const handleShiftBlockHour = async (block: CanvasBlock, deltaMin: number) => {
        const nextStart = Math.max(
            CANVAS_START_MIN,
            Math.min(CANVAS_END_MIN - block.durationMin, block.startMin + deltaMin),
        );
        const nextEnd = nextStart + block.durationMin;
        await studio.updateScheduleBlockDirect(
            block.classItem,
            block.session,
            {
                day_of_week: block.day,
                start_time: minutesToApiTime(nextStart),
                end_time: minutesToApiTime(nextEnd),
            },
            `Shifted ${block.classItem.subject_code} to ${formatCompactRange(nextStart, nextEnd)}`,
        );
    };

    // Helper: render blocks inside one vertical column WITH right-click ContextMenu & Drag-and-Drop
    const renderColumnBlocks = (
        columnKey: string,
        columnTitle: string,
        blocks: CanvasBlock[],
        canDrag: boolean,
        styleMode: "room" | "student" | "teacher",
    ) => {
        const visible: CanvasBlock[] = [];
        const hiddenRaw: CanvasBlock[] = [];

        for (const block of blocks) {
            if (block.laneCount <= MAX_VISIBLE_LANES || block.laneIndex < MAX_VISIBLE_LANES - 1) {
                visible.push(block);
            } else {
                hiddenRaw.push(block);
            }
        }

        const overflowClusters: Array<{ key: string; startMin: number; endMin: number; hidden: CanvasBlock[] }> = [];
        const sortedHidden = [...hiddenRaw].sort((a, b) => a.startMin - b.startMin);
        for (const block of sortedHidden) {
            const last = overflowClusters[overflowClusters.length - 1];
            if (!last || block.startMin >= last.endMin) {
                overflowClusters.push({
                    key: `${block.startMin}`,
                    startMin: block.startMin,
                    endMin: block.endMin,
                    hidden: [block],
                });
            } else {
                last.endMin = Math.max(last.endMin, block.endMin);
                last.hidden.push(block);
            }
        }

        const readOnly = activeTab === "students" && studentSubMode === "individual";

        return (
            <>
                {visible.map((block) => {
                    const effectiveLaneCount = Math.min(block.laneCount, MAX_VISIBLE_LANES);
                    const widthPct = 100 / effectiveLaneCount;
                    const leftPct = block.laneIndex * widthPct;
                    const top = minToPx(block.startMin) + 2;
                    const height = durationToPx(block.durationMin) - 4;
                    const tone = SUBJECT_TONES[block.toneIndex];
                    const compact = height < 54;
                    const isDraggingThis = draggedBlock?.key === block.key;

                    const cardClasses = block.hasConflict
                        ? "bg-rose-500/[0.11] border-rose-500/45 text-rose-950 dark:text-rose-100 hover:bg-rose-500/[0.16]"
                        : styleMode === "student"
                          ? `${tone.bg} ${tone.border} ${tone.text} ${block.isOnline ? "border-dashed" : ""} hover:brightness-[0.97]`
                          : "bg-card border-border/80 text-foreground hover:border-foreground/40 shadow-2xs";

                    return (
                        <button
                            key={block.key}
                            data-canvas-block={block.key}
                            type="button"
                            draggable={canDrag}
                            onDragStart={(e) => {
                                if (!canDrag) return;
                                e.dataTransfer.effectAllowed = "move";
                                e.dataTransfer.setData("text/plain", block.key);
                                setDraggedBlock(block);
                            }}
                            onDragEnd={() => {
                                setDraggedBlock(null);
                                setDragPreview(null);
                            }}
                            onContextMenu={(e) => {
                                e.preventDefault();
                                e.stopPropagation();
                                setContextMenuTarget({
                                    kind: "block",
                                    x: e.clientX,
                                    y: e.clientY,
                                    block,
                                });
                            }}
                            onClick={(e) => {
                                e.stopPropagation();
                                setInspectedBlock({ block, mode: "view" });
                            }}
                            style={{
                                top: `${top}px`,
                                height: `${height}px`,
                                left: `calc(${leftPct}% + 3px)`,
                                width: `calc(${widthPct}% - 6px)`,
                            }}
                            className={`group absolute z-10 flex flex-col justify-between overflow-hidden rounded-lg border px-2 py-1.5 text-left transition-colors active:scale-[0.98] ${cardClasses} ${
                                canDrag ? "cursor-grab active:cursor-grabbing" : "cursor-pointer"
                            } ${isDraggingThis ? "opacity-40" : ""}`}
                        >
                            {styleMode !== "student" && (
                                <span
                                    className={`absolute top-1.5 bottom-1.5 left-0 w-[3px] rounded-r-full ${
                                        block.hasConflict
                                            ? "bg-rose-500"
                                            : styleMode === "teacher"
                                              ? tone.accent
                                              : "bg-foreground/65"
                                    }`}
                                />
                            )}

                            <div className={`min-w-0 ${styleMode !== "student" ? "pl-1.5" : ""}`}>
                                <div className="flex items-center justify-between gap-1">
                                    <span className="truncate text-[11px] font-bold tracking-tight leading-tight">
                                        {block.classItem.subject_code}
                                    </span>
                                    {block.hasConflict ? (
                                        <AlertTriangle className="size-3 shrink-0 text-rose-600 dark:text-rose-400" />
                                    ) : block.isOnline ? (
                                        <Monitor className="size-3 shrink-0 opacity-60" />
                                    ) : (
                                        <span className="shrink-0 text-[10px] opacity-60">
                                            {block.classItem.section}
                                        </span>
                                    )}
                                </div>

                                {!compact && (
                                    <p className="mt-0.5 truncate text-[10px] opacity-75">
                                        {styleMode === "room"
                                            ? block.classItem.faculty_name ?? "No instructor"
                                            : styleMode === "teacher"
                                              ? `${block.isOnline ? "Online" : block.session.room ?? "No room"} · Sec ${block.classItem.section}`
                                              : block.isOnline
                                                ? "Online session"
                                                : block.session.room ?? "No room"}
                                    </p>
                                )}
                            </div>

                            {!compact && height >= 72 && (
                                <div className={`${styleMode !== "student" ? "pl-1.5" : ""}`}>
                                    {styleMode === "room" && block.seatCapacity > 0 ? (
                                        <div className="space-y-1">
                                            <div className="flex items-center justify-between gap-1 text-[9.5px] opacity-70 tabular-nums">
                                                <span className="truncate whitespace-nowrap">
                                                    {formatCompactRange(block.startMin, block.endMin)}
                                                </span>
                                                {effectiveLaneCount < 3 && (
                                                    <span
                                                        className={`shrink-0 ${
                                                            block.overcrowded
                                                                ? "font-semibold text-rose-600 dark:text-rose-400"
                                                                : ""
                                                        }`}
                                                    >
                                                        {block.seatCount}/{block.seatCapacity}
                                                    </span>
                                                )}
                                            </div>
                                            <div className="bg-muted/80 h-1 overflow-hidden rounded-full">
                                                <div
                                                    className={`h-full rounded-full ${
                                                        block.overcrowded
                                                            ? "bg-rose-500"
                                                            : block.seatPct >= 90
                                                              ? "bg-amber-500"
                                                              : "bg-emerald-500/80"
                                                    }`}
                                                    style={{ width: `${Math.min(100, Math.max(6, block.seatPct))}%` }}
                                                />
                                            </div>
                                        </div>
                                    ) : (
                                        <p className="truncate whitespace-nowrap text-[9.5px] opacity-65 tabular-nums">
                                            {formatCompactRange(block.startMin, block.endMin)}
                                        </p>
                                    )}
                                </div>
                            )}
                        </button>
                    );
                })}

                {overflowClusters.map((cluster) => {
                    const top = minToPx(cluster.startMin) + 2;
                    const height = durationToPx(cluster.endMin - cluster.startMin) - 4;
                    const widthPct = 100 / MAX_VISIBLE_LANES;
                    const leftPct = (MAX_VISIBLE_LANES - 1) * widthPct;
                    const overlappingAll = blocks.filter(
                        (b) => b.startMin < cluster.endMin && b.endMin > cluster.startMin,
                    );

                    return (
                        <button
                            key={`${columnKey}-overflow-${cluster.key}`}
                            type="button"
                            onClick={(e) => {
                                e.stopPropagation();
                                setClusterBlocks({
                                    title: `${columnTitle} · ${formatCompactRange(cluster.startMin, cluster.endMin)}`,
                                    blocks: overlappingAll,
                                });
                            }}
                            style={{
                                top: `${top}px`,
                                height: `${height}px`,
                                left: `calc(${leftPct}% + 3px)`,
                                width: `calc(${widthPct}% - 6px)`,
                            }}
                            className="absolute z-10 flex flex-col items-center justify-center rounded-lg border border-dashed border-rose-500/50 bg-rose-500/[0.08] px-1 text-center text-[11px] font-semibold text-rose-700 transition-colors hover:bg-rose-500/15 dark:text-rose-300"
                        >
                            <span>+{cluster.hidden.length}</span>
                            <span className="text-[9px] font-normal opacity-80">more</span>
                        </button>
                    );
                })}
            </>
        );
    };

    const loadStatusStyle = (status: "light" | "balanced" | "heavy" | "overloaded") => {
        switch (status) {
            case "overloaded":
                return { label: "Overloaded", cls: "bg-rose-500/12 text-rose-700 dark:text-rose-300" };
            case "heavy":
                return { label: "Heavy load", cls: "bg-amber-500/15 text-amber-800 dark:text-amber-300" };
            case "balanced":
                return { label: "Balanced", cls: "bg-emerald-500/12 text-emerald-700 dark:text-emerald-300" };
            default:
                return { label: "Light load", cls: "bg-muted text-muted-foreground" };
        }
    };

    const renderFloatingContextMenu = () => {
        const readOnly = activeTab === "students" && studentSubMode === "individual";
        const closeMenu = () => setContextMenuTarget(null);

        return (
            <FloatingContextMenu
                position={contextMenuTarget ? { x: contextMenuTarget.x, y: contextMenuTarget.y } : null}
                onClose={closeMenu}
            >
                {contextMenuTarget?.kind === "block" ? (
                    <>
                        <FloatingMenuLabel
                            title={`${contextMenuTarget.block.classItem.subject_code} · Sec ${contextMenuTarget.block.classItem.section}`}
                            subtitle={`${contextMenuTarget.block.day} · ${formatCompactRange(contextMenuTarget.block.startMin, contextMenuTarget.block.endMin)}`}
                        />
                        <FloatingMenuSeparator />
                        <FloatingMenuItem
                            icon={<Eye className="size-3.5" />}
                            label={contextMenuTarget.block.hasConflict ? "View details & 1-click fixes" : "View details"}
                            onSelect={() => {
                                const b = contextMenuTarget.block;
                                closeMenu();
                                setInspectedBlock({ block: b, mode: "view" });
                            }}
                        />
                        {!readOnly && (
                            <>
                                <FloatingMenuItem
                                    icon={<Pencil className="size-3.5" />}
                                    label="Edit time & room"
                                    onSelect={() => {
                                        const b = contextMenuTarget.block;
                                        closeMenu();
                                        setInspectedBlock({ block: b, mode: "edit" });
                                    }}
                                />
                                <FloatingMenuItem
                                    icon={<CalendarDays className="size-3.5" />}
                                    label="Move 1 hour later"
                                    onSelect={() => {
                                        const b = contextMenuTarget.block;
                                        closeMenu();
                                        void handleShiftBlockHour(b, 60);
                                    }}
                                />
                                {!contextMenuTarget.block.isOnline && (
                                    <FloatingMenuItem
                                        icon={<Monitor className="size-3.5" />}
                                        label="Switch to Online"
                                        onSelect={() => {
                                            const b = contextMenuTarget.block;
                                            closeMenu();
                                            void handleConvertOnlineFix(b);
                                        }}
                                    />
                                )}
                            </>
                        )}
                        <FloatingMenuSeparator />
                        <FloatingMenuItem
                            icon={<ArrowUpRight className="size-3.5" />}
                            label="Open class record"
                            onSelect={() => {
                                const id = contextMenuTarget.block.classItem.id;
                                closeMenu();
                                router.visit(showClass.url(id));
                            }}
                        />
                        {!readOnly && contextMenuTarget.block.session.id !== undefined && (
                            <>
                                <FloatingMenuSeparator />
                                <FloatingMenuItem
                                    icon={<Trash2 className="size-3.5" />}
                                    label="Remove session"
                                    destructive
                                    onSelect={() => {
                                        const sid = contextMenuTarget.block.session.id!;
                                        closeMenu();
                                        void studio.deleteScheduleBlock(sid);
                                    }}
                                />
                            </>
                        )}
                    </>
                ) : contextMenuTarget?.kind === "empty" ? (
                    <>
                        <FloatingMenuLabel
                            title={`${contextMenuTarget.day}${contextMenuTarget.roomName ? ` · ${contextMenuTarget.roomName}` : ""}`}
                            subtitle={`${formatMinutes12h(contextMenuTarget.startMin)} – ${formatMinutes12h(contextMenuTarget.endMin)}`}
                        />
                        <FloatingMenuSeparator />
                        <FloatingMenuItem
                            icon={<Plus className="size-3.5" />}
                            label="Schedule a class here"
                            onSelect={() => {
                                const slot = contextMenuTarget;
                                closeMenu();
                                setQuickAddTarget({
                                    day: slot.day,
                                    startTime: minutesToApiTime(slot.startMin),
                                    endTime: minutesToApiTime(slot.endMin),
                                    roomId: slot.roomId ?? (activeTab === "rooms" ? activeRoom?.id ?? null : null),
                                });
                            }}
                        />
                        <FloatingMenuItem
                            icon={<Filter className="size-3.5" />}
                            label="Filter schedule…"
                            onSelect={() => {
                                closeMenu();
                                setFilterSheetOpen(true);
                            }}
                        />
                        {studio.history.canUndo && (
                            <FloatingMenuItem
                                icon={<Undo2 className="size-3.5" />}
                                label="Undo last change"
                                onSelect={() => {
                                    closeMenu();
                                    void studio.history.undo();
                                }}
                            />
                        )}
                        <FloatingMenuSeparator />
                        <FloatingMenuItem
                            icon={<Sun className="size-3.5" />}
                            label={studio.policy.allow_sunday_scheduling ? "Hide Sunday classes" : "Include Sunday classes"}
                            onSelect={() => {
                                closeMenu();
                                void studio.toggleSundayScheduling();
                            }}
                        />
                    </>
                ) : null}
            </FloatingContextMenu>
        );
    };

    return (
        <AdminLayout user={user} title="Schedule" flush>
            <Head title="Schedule" />

            <div className="bg-background text-foreground flex h-full flex-col overflow-hidden antialiased">
                {/* ── 1. MINIMALIST WORKSPACE BAR (Tabs + Search + Sliding Filter Sheet Trigger) ── */}
                <div className="border-border/60 bg-background/95 flex flex-wrap items-center justify-between gap-2.5 border-b px-4 py-2.5 backdrop-blur-sm">
                    <nav
                        aria-label="Schedule perspective tabs"
                        className="bg-muted/65 border-border/50 inline-flex items-center gap-0.5 rounded-xl border p-1"
                    >
                        {(
                            [
                                { id: "rooms", label: "Rooms", icon: Building2 },
                                { id: "students", label: "Students & Sections", icon: GraduationCap },
                                { id: "teachers", label: "Teachers", icon: User },
                            ] as Array<{ id: CanvasTab; label: string; icon: typeof Building2 }>
                        ).map((tab) => {
                            const Icon = tab.icon;
                            const active = activeTab === tab.id;
                            return (
                                <button
                                    key={tab.id}
                                    type="button"
                                    role="tab"
                                    aria-selected={active}
                                    onClick={() => setActiveTab(tab.id)}
                                    className={`inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-medium transition-colors active:scale-[0.97] ${
                                        active
                                            ? "bg-background text-foreground shadow-xs"
                                            : "text-muted-foreground hover:text-foreground"
                                    }`}
                                >
                                    <Icon className="size-3.5" />
                                    <span>{tab.label}</span>
                                </button>
                            );
                        })}
                    </nav>

                    <div className="flex flex-wrap items-center gap-2">
                        <div className="relative w-48 sm:w-56">
                            <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2" />
                            <Input
                                type="search"
                                value={searchQuery}
                                onChange={(e) => setSearchQuery(e.target.value)}
                                placeholder="Search schedule…"
                                aria-label="Search weekly schedule"
                                className="bg-muted/35 focus-visible:bg-background h-8 rounded-lg pr-7 pl-8 text-xs"
                            />
                            {searchQuery && (
                                <button
                                    type="button"
                                    onClick={() => setSearchQuery("")}
                                    aria-label="Clear search"
                                    className="text-muted-foreground hover:text-foreground absolute top-1/2 right-2 -translate-y-1/2"
                                >
                                    <X className="size-3.5" />
                                </button>
                            )}
                        </div>

                        <Button
                            type="button"
                            variant={activeFilterCount > 0 ? "secondary" : "outline"}
                            size="sm"
                            className="h-8 gap-1.5 rounded-lg px-2.5 text-xs"
                            onClick={() => setFilterSheetOpen(true)}
                        >
                            <Filter className="size-3.5" />
                            <span>Filters</span>
                            {activeFilterCount > 0 && (
                                <span className="bg-foreground text-background inline-flex size-4 items-center justify-center rounded-full text-[10px] font-semibold tabular-nums">
                                    {activeFilterCount}
                                </span>
                            )}
                        </Button>

                        {studio.history.canUndo && (
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                className="h-8 gap-1 rounded-lg px-2.5 text-xs"
                                onClick={() => void studio.history.undo()}
                            >
                                <Undo2 className="size-3.5" />
                                <span className="hidden xl:inline">Undo</span>
                            </Button>
                        )}

                        <Button
                            type="button"
                            size="sm"
                            className="h-8 gap-1 rounded-lg px-3 text-xs"
                            onClick={() =>
                                setQuickAddTarget({
                                    day: days[0] ?? "Monday",
                                    startTime: "08:00",
                                    endTime: "09:30",
                                    roomId: activeTab === "rooms" ? activeRoom?.id ?? null : null,
                                })
                            }
                        >
                            <Plus className="size-3.5" />
                            <span>Schedule</span>
                        </Button>

                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    className="size-8 rounded-lg"
                                    aria-label="Schedule options"
                                >
                                    <MoreHorizontal className="size-4" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" className="w-56">
                                <DropdownMenuItem
                                    disabled={!studio.history.canRedo}
                                    onSelect={() => void studio.history.redo()}
                                    className="flex items-center gap-2"
                                >
                                    <Redo2 className="size-3.5" /> Redo change
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    onSelect={() => void studio.toggleSundayScheduling()}
                                    className="flex items-center justify-between gap-2"
                                >
                                    <span className="flex items-center gap-2">
                                        <Sun className="size-3.5" /> Sunday classes
                                    </span>
                                    <Badge variant="outline" className="text-[10px]">
                                        {studio.policy.allow_sunday_scheduling ? "On" : "Off"}
                                    </Badge>
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    onSelect={() => studio.setPolicyOpen(true)}
                                    className="flex items-center gap-2"
                                >
                                    <SlidersHorizontal className="size-3.5" /> Conflict rules
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    onSelect={() => studio.setGenerateOpen(true)}
                                    className="flex items-center gap-2"
                                >
                                    <Sparkles className="size-3.5" /> Generate from curriculum
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    onSelect={() => studio.setExportOpen(true)}
                                    className="flex items-center gap-2"
                                >
                                    <Download className="size-3.5" /> Export PDF
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    onSelect={() => studio.setReviewOpen(true)}
                                    className="flex items-center gap-2"
                                >
                                    <Send className="size-3.5" /> Review &amp; Publish
                                </DropdownMenuItem>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem asChild>
                                    <Link href={boardIndex.url()} className="flex items-center gap-2">
                                        <CalendarDays className="size-3.5" /> Open Schedule Board
                                    </Link>
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                </div>

                {/* ── 2. QUIET INLINE CONTEXT BAR ── */}
                <div className="border-border/50 bg-muted/20 flex min-h-11 shrink-0 flex-wrap items-center justify-between gap-2.5 border-b px-4 py-1.5">
                    {activeTab === "rooms" && (
                        <>
                            <div className="flex flex-wrap items-center gap-2">
                                {roomViewMode === "week" ? (
                                    <div className="flex items-center gap-1">
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            className="size-7 rounded-lg"
                                            aria-label="Previous room"
                                            disabled={roomDirectory.length <= 1}
                                            onClick={() => {
                                                const prev = (activeRoomIndex - 1 + roomDirectory.length) % roomDirectory.length;
                                                setSelectedRoomId(roomDirectory[prev].room.id);
                                            }}
                                        >
                                            <ChevronLeft className="size-4" />
                                        </Button>

                                        <Select
                                            value={activeRoom ? String(activeRoom.id) : ""}
                                            onValueChange={(v) => setSelectedRoomId(Number(v))}
                                        >
                                            <SelectTrigger className="bg-background h-8 w-[235px] rounded-lg text-xs font-medium">
                                                <SelectValue placeholder="Choose a room" />
                                            </SelectTrigger>
                                            <SelectContent className="max-h-72">
                                                {roomDirectory.map((entry) => (
                                                    <SelectItem key={entry.room.id} value={String(entry.room.id)}>
                                                        <span className="flex items-center gap-2">
                                                            {entry.conflictCount > 0 && (
                                                                <span className="size-1.5 rounded-full bg-rose-500" />
                                                            )}
                                                            <span>{entry.room.name}</span>
                                                            <span className="text-muted-foreground text-[11px] tabular-nums">
                                                                ({entry.room.capacity ?? 40} seats)
                                                            </span>
                                                        </span>
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>

                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            className="size-7 rounded-lg"
                                            aria-label="Next room"
                                            disabled={roomDirectory.length <= 1}
                                            onClick={() => {
                                                const next = (activeRoomIndex + 1) % roomDirectory.length;
                                                setSelectedRoomId(roomDirectory[next].room.id);
                                            }}
                                        >
                                            <ChevronRight className="size-4" />
                                        </Button>
                                    </div>
                                ) : (
                                    <div className="flex flex-wrap items-center gap-1.5">
                                        {days.map((d) => (
                                            <button
                                                key={d}
                                                type="button"
                                                onClick={() => setCompareDay(d)}
                                                className={`rounded-lg px-2 py-1 text-xs font-medium transition-colors ${
                                                    compareDay === d
                                                        ? "bg-foreground text-background"
                                                        : "text-muted-foreground hover:bg-muted hover:text-foreground"
                                                }`}
                                            >
                                                {d.slice(0, 3)}
                                            </button>
                                        ))}

                                        <div className="bg-border/60 mx-0.5 h-4 w-px" />

                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            className="h-7 px-1.5 text-xs"
                                            disabled={comparePage === 0}
                                            onClick={() => setComparePage((p) => Math.max(0, p - 1))}
                                        >
                                            <ChevronLeft className="size-3.5" />
                                        </Button>
                                        <span className="text-muted-foreground text-[11px] tabular-nums">
                                            {comparePage * COMPARE_PAGE_SIZE + 1}–
                                            {Math.min(roomDirectory.length, (comparePage + 1) * COMPARE_PAGE_SIZE)} /{" "}
                                            {roomDirectory.length}
                                        </span>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            className="h-7 px-1.5 text-xs"
                                            disabled={(comparePage + 1) * COMPARE_PAGE_SIZE >= roomDirectory.length}
                                            onClick={() => setComparePage((p) => p + 1)}
                                        >
                                            <ChevronRight className="size-3.5" />
                                        </Button>
                                    </div>
                                )}

                                <div className="bg-border/60 hidden h-4 w-px sm:block" />

                                <div className="bg-muted/70 inline-flex rounded-lg p-0.5 text-[11px]">
                                    <button
                                        type="button"
                                        onClick={() => setRoomViewMode("week")}
                                        className={`inline-flex items-center gap-1 rounded-md px-2.5 py-1 font-medium transition-colors ${
                                            roomViewMode === "week"
                                                ? "bg-background text-foreground shadow-2xs"
                                                : "text-muted-foreground hover:text-foreground"
                                        }`}
                                    >
                                        <CalendarDays className="size-3" />
                                        Week
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => setRoomViewMode("compare_day")}
                                        className={`inline-flex items-center gap-1 rounded-md px-2.5 py-1 font-medium transition-colors ${
                                            roomViewMode === "compare_day"
                                                ? "bg-background text-foreground shadow-2xs"
                                                : "text-muted-foreground hover:text-foreground"
                                        }`}
                                    >
                                        <Columns3 className="size-3" />
                                        Compare Rooms
                                    </button>
                                </div>
                            </div>

                            <div className="text-muted-foreground flex items-center gap-2.5 text-xs tabular-nums">
                                {roomViewMode === "week" && activeRoom ? (
                                    <>
                                        <span>{activeRoom.capacity ?? 40} seats</span>
                                        <span>·</span>
                                        <span>
                                            {roomWeek.weeklyHours}h/wk ({roomWeek.utilizationPct}% used)
                                        </span>
                                        {roomWeek.conflictCount > 0 && (
                                            <button
                                                type="button"
                                                onClick={() => setConflictsOnly((v) => !v)}
                                                className={`inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-semibold transition-colors ${
                                                    conflictsOnly
                                                        ? "bg-rose-600 text-white"
                                                        : "bg-rose-500/12 text-rose-700 hover:bg-rose-500/20 dark:text-rose-300"
                                                }`}
                                                title="Toggle conflicts only"
                                            >
                                                <AlertTriangle className="size-3" />
                                                {roomWeek.conflictCount} overlapping
                                            </button>
                                        )}
                                    </>
                                ) : (
                                    <span>Drag any class horizontally across columns to switch rooms</span>
                                )}
                            </div>
                        </>
                    )}

                    {activeTab === "students" && (
                        <>
                            <div className="flex flex-wrap items-center gap-2">
                                <div className="bg-muted/70 inline-flex rounded-lg p-0.5 text-[11px]">
                                    <button
                                        type="button"
                                        onClick={() => setStudentSubMode("section")}
                                        className={`rounded-md px-2.5 py-1 font-medium transition-colors ${
                                            studentSubMode === "section"
                                                ? "bg-background text-foreground shadow-2xs"
                                                : "text-muted-foreground hover:text-foreground"
                                        }`}
                                    >
                                        Section &amp; Year
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => setStudentSubMode("individual")}
                                        className={`rounded-md px-2.5 py-1 font-medium transition-colors ${
                                            studentSubMode === "individual"
                                                ? "bg-background text-foreground shadow-2xs"
                                                : "text-muted-foreground hover:text-foreground"
                                        }`}
                                    >
                                        Individual Student
                                    </button>
                                </div>

                                <div className="bg-border/60 hidden h-4 w-px sm:block" />

                                {studentSubMode === "section" ? (
                                    <div className="flex flex-wrap items-center gap-1.5">
                                        <Select
                                            value={selectedProgram}
                                            onValueChange={(prog) => {
                                                setSelectedProgram(prog);
                                                const nextYears = cohortOptions.yearLevelsByProgram[prog] ?? ["1st Year"];
                                                setSelectedYearLevel(nextYears[0] ?? "1st Year");
                                            }}
                                        >
                                            <SelectTrigger className="bg-background h-8 w-[136px] rounded-lg text-xs font-medium">
                                                <SelectValue placeholder="Program" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {cohortOptions.programs.map((p) => (
                                                    <SelectItem key={p} value={p}>
                                                        {p}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>

                                        <Select value={safeYearLevel} onValueChange={setSelectedYearLevel}>
                                            <SelectTrigger className="bg-background h-8 w-[124px] rounded-lg text-xs font-medium">
                                                <SelectValue placeholder="Year Level" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {availableYears.map((yr) => (
                                                    <SelectItem key={yr} value={yr}>
                                                        {yr}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>

                                        <Select value={safeSection} onValueChange={setSelectedSection}>
                                            <SelectTrigger className="bg-background h-8 w-[104px] rounded-lg text-xs font-medium">
                                                <SelectValue placeholder="Section" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {availableSections.map((sec) => (
                                                    <SelectItem key={sec} value={sec}>
                                                        Sec {sec}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                ) : (
                                    <div className="relative flex items-center gap-2">
                                        <div className="relative w-64">
                                            <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2" />
                                            <Input
                                                type="search"
                                                value={studentQuery}
                                                onChange={(e) => setStudentQuery(e.target.value)}
                                                placeholder="Search student name or ID…"
                                                className="bg-background h-8 rounded-lg pl-8 text-xs"
                                            />
                                            {searchingStudents && (
                                                <Loader2 className="text-muted-foreground absolute top-1/2 right-2.5 size-3.5 -translate-y-1/2 animate-spin" />
                                            )}

                                            {studentResults.length > 0 && (
                                                <div className="bg-popover border-border absolute top-full right-0 left-0 z-40 mt-1 max-h-60 overflow-y-auto rounded-xl border p-1 shadow-lg">
                                                    {studentResults.map((s) => (
                                                        <button
                                                            key={s.id}
                                                            type="button"
                                                            onClick={() => void selectIndividualStudent(s)}
                                                            className="hover:bg-muted flex w-full items-center justify-between gap-2 rounded-lg px-2.5 py-2 text-left text-xs transition-colors"
                                                        >
                                                            <span className="truncate font-medium">{s.name}</span>
                                                            <span className="text-muted-foreground shrink-0 text-[11px] tabular-nums">
                                                                #{s.student_id}
                                                            </span>
                                                        </button>
                                                    ))}
                                                </div>
                                            )}
                                        </div>

                                        {selectedStudent && (
                                            <span className="bg-background border-border inline-flex items-center gap-1.5 rounded-lg border px-2.5 py-1 text-xs font-medium">
                                                {selectedStudent.name}
                                                <span className="text-muted-foreground tabular-nums">
                                                    #{selectedStudent.student_id}
                                                </span>
                                                <button
                                                    type="button"
                                                    onClick={() => {
                                                        setSelectedStudent(null);
                                                        setStudentClasses([]);
                                                    }}
                                                    className="text-muted-foreground hover:text-foreground"
                                                    aria-label="Clear student"
                                                >
                                                    <X className="size-3" />
                                                </button>
                                            </span>
                                        )}
                                    </div>
                                )}
                            </div>

                            <div className="text-muted-foreground flex items-center gap-2.5 text-xs tabular-nums">
                                <span>{cohortWeek.subjectCount} subjects</span>
                                <span>·</span>
                                <span>{cohortWeek.weeklyHours}h/wk</span>
                                {cohortWeek.onlineSessionCount > 0 && (
                                    <>
                                        <span>·</span>
                                        <span>{cohortWeek.onlineSessionCount} online</span>
                                    </>
                                )}
                                {cohortWeek.conflictCount > 0 && (
                                    <button
                                        type="button"
                                        onClick={() => setConflictsOnly((v) => !v)}
                                        className={`inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-semibold transition-colors ${
                                            conflictsOnly
                                                ? "bg-rose-600 text-white"
                                                : "bg-rose-500/12 text-rose-700 hover:bg-rose-500/20 dark:text-rose-300"
                                        }`}
                                    >
                                        <AlertTriangle className="size-3" />
                                        {cohortWeek.conflictCount} overlapping
                                    </button>
                                )}
                            </div>
                        </>
                    )}

                    {activeTab === "teachers" && (
                        <>
                            <div className="flex items-center gap-1">
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    className="size-7 rounded-lg"
                                    aria-label="Previous teacher"
                                    disabled={facultyDirectory.length <= 1}
                                    onClick={() => {
                                        const prev = (activeFacultyIndex - 1 + facultyDirectory.length) % facultyDirectory.length;
                                        setSelectedFacultyId(facultyDirectory[prev].faculty.id);
                                    }}
                                >
                                    <ChevronLeft className="size-4" />
                                </Button>

                                <Select
                                    value={activeFaculty ? String(activeFaculty.id) : ""}
                                    onValueChange={setSelectedFacultyId}
                                >
                                    <SelectTrigger className="bg-background h-8 w-[245px] rounded-lg text-xs font-medium">
                                        <SelectValue placeholder="Choose a teacher" />
                                    </SelectTrigger>
                                    <SelectContent className="max-h-72">
                                        {facultyDirectory.map((entry) => (
                                            <SelectItem key={entry.faculty.id} value={String(entry.faculty.id)}>
                                                <span className="flex items-center gap-2">
                                                    {entry.conflictCount > 0 && (
                                                        <span className="size-1.5 rounded-full bg-rose-500" />
                                                    )}
                                                    <span>{entry.faculty.name}</span>
                                                    <span className="text-muted-foreground text-[11px] tabular-nums">
                                                        {entry.weeklyHours}h
                                                    </span>
                                                </span>
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>

                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    className="size-7 rounded-lg"
                                    aria-label="Next teacher"
                                    disabled={facultyDirectory.length <= 1}
                                    onClick={() => {
                                        const next = (activeFacultyIndex + 1) % facultyDirectory.length;
                                        setSelectedFacultyId(facultyDirectory[next].faculty.id);
                                    }}
                                >
                                    <ChevronRight className="size-4" />
                                </Button>
                            </div>

                            <div className="flex flex-wrap items-center gap-2 text-xs tabular-nums">
                                <span
                                    className={`inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-[11px] font-semibold ${
                                        loadStatusStyle(teacherWeek.loadStatus).cls
                                    }`}
                                >
                                    {teacherWeek.weeklyHours} / 21h · {loadStatusStyle(teacherWeek.loadStatus).label}
                                </span>

                                <span className="text-muted-foreground hidden sm:inline">
                                    {teacherWeek.teachingDaysCount} teaching{" "}
                                    {teacherWeek.teachingDaysCount === 1 ? "day" : "days"}
                                </span>

                                {teacherWeek.handoffCount > 0 && (
                                    <span className="inline-flex items-center gap-1 rounded-full bg-amber-500/15 px-2 py-0.5 text-[11px] font-medium text-amber-800 dark:text-amber-300">
                                        <ArrowRightLeft className="size-3" />
                                        {teacherWeek.handoffCount} handoffs
                                    </span>
                                )}

                                {teacherWeek.conflictCount > 0 && (
                                    <button
                                        type="button"
                                        onClick={() => setConflictsOnly((v) => !v)}
                                        className={`inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-semibold transition-colors ${
                                            conflictsOnly
                                                ? "bg-rose-600 text-white"
                                                : "bg-rose-500/12 text-rose-700 hover:bg-rose-500/20 dark:text-rose-300"
                                        }`}
                                    >
                                        <AlertTriangle className="size-3" />
                                        {teacherWeek.conflictCount} overlapping
                                    </button>
                                )}
                            </div>
                        </>
                    )}
                </div>

                {/* Active Filter Pills Strip */}
                {(activeFilterCount > 0 || searchQuery.trim() !== "") && (
                    <div className="border-border/50 bg-muted/10 flex flex-wrap items-center gap-1.5 border-b px-4 py-1.5 text-[11px]">
                        <span className="text-muted-foreground font-medium">Filtered:</span>
                        {searchQuery.trim() !== "" && (
                            <Badge variant="secondary" className="gap-1 rounded-md font-normal">
                                Search: &ldquo;{searchQuery.trim()}&rdquo;
                                <button type="button" onClick={() => setSearchQuery("")} aria-label="Clear search">
                                    <X className="size-3" />
                                </button>
                            </Badge>
                        )}
                        {conflictsOnly && (
                            <Badge variant="secondary" className="gap-1 rounded-md bg-rose-500/12 text-rose-700 dark:text-rose-300">
                                Conflicts only
                                <button type="button" onClick={() => setConflictsOnly(false)} aria-label="Clear conflicts filter">
                                    <X className="size-3" />
                                </button>
                            </Badge>
                        )}
                        {deliveryFilter !== "all" && (
                            <Badge variant="secondary" className="gap-1 rounded-md font-normal">
                                {deliveryFilter === "online" ? "Online only" : "Face-to-Face only"}
                                <button type="button" onClick={() => setDeliveryFilter("all")} aria-label="Clear delivery filter">
                                    <X className="size-3" />
                                </button>
                            </Badge>
                        )}
                        {roomTypeFilter !== "all" && (
                            <Badge variant="secondary" className="gap-1 rounded-md font-normal capitalize">
                                Room: {roomTypeFilter}
                                <button type="button" onClick={() => setRoomTypeFilter("all")} aria-label="Clear room type filter">
                                    <X className="size-3" />
                                </button>
                            </Badge>
                        )}
                        {departmentFilter !== "all" && (
                            <Badge variant="secondary" className="gap-1 rounded-md font-normal">
                                Dept: {departmentFilter}
                                <button type="button" onClick={() => setDepartmentFilter("all")} aria-label="Clear department filter">
                                    <X className="size-3" />
                                </button>
                            </Badge>
                        )}
                        <button
                            type="button"
                            onClick={resetAllFilters}
                            className="text-primary ml-1 font-medium hover:underline"
                        >
                            Clear all
                        </button>
                    </div>
                )}

                {/* ── 3. FULL-WIDTH MINIMALIST WEEKLY CALENDAR CANVAS ── */}
                <main ref={gridScrollRef} className="min-h-0 flex-1 overflow-y-auto">
                    {activeTab === "students" && studentSubMode === "individual" && !selectedStudent ? (
                        <div className="flex h-full flex-col items-center justify-center gap-2 p-10 text-center">
                            <div className="bg-muted flex size-12 items-center justify-center rounded-2xl">
                                <GraduationCap className="text-muted-foreground size-6" />
                            </div>
                            <h2 className="text-sm font-semibold">Look up an individual student</h2>
                            <p className="text-muted-foreground max-w-sm text-xs text-pretty">
                                Type a student&apos;s name or student number above to inspect their personal enrolled weekly timetable.
                            </p>
                        </div>
                    ) : loadingStudentSchedule ? (
                        <div className="text-muted-foreground flex h-full items-center justify-center gap-2 text-xs">
                            <Loader2 className="size-4 animate-spin" />
                            Loading student timetable…
                        </div>
                    ) : (
                        <div className="min-w-[760px]">
                            {/* Sticky Column Headers */}
                            <div
                                className="border-border/60 bg-background/95 sticky top-0 z-20 grid border-b backdrop-blur-sm"
                                style={{
                                    gridTemplateColumns:
                                        activeTab === "rooms" && roomViewMode === "compare_day"
                                            ? `60px repeat(${Math.max(1, compareDayColumns.length)}, minmax(0, 1fr))`
                                            : `60px repeat(${days.length}, minmax(0, 1fr))`,
                                }}
                            >
                                <div className="border-border/40 border-r" />

                                {activeTab === "rooms" && roomViewMode === "compare_day"
                                    ? compareDayColumns.map((col) => (
                                          <div
                                              key={col.room.id}
                                              className="border-border/40 flex items-center justify-between gap-1 border-r px-3 py-2.5 last:border-r-0"
                                          >
                                              <div className="min-w-0">
                                                  <p className="truncate text-xs font-semibold tracking-tight">
                                                      {col.room.name}
                                                  </p>
                                                  <p className="text-muted-foreground text-[10px] tabular-nums">
                                                      {col.room.capacity ?? 40} seats · {col.blocks.length}{" "}
                                                      {col.blocks.length === 1 ? "class" : "classes"}
                                                  </p>
                                              </div>
                                              {col.conflictCount > 0 && (
                                                  <span className="size-2 shrink-0 rounded-full bg-rose-500" title="Double-booked room" />
                                              )}
                                          </div>
                                      ))
                                    : days.map((day) => {
                                          const daySubtitle =
                                              activeTab === "rooms"
                                                  ? `${roomWeek.blocksByDay[day].length} ${
                                                        roomWeek.blocksByDay[day].length === 1 ? "class" : "classes"
                                                    }`
                                                  : activeTab === "students"
                                                    ? cohortWeek.hoursByDay[day] > 0
                                                        ? `${cohortWeek.hoursByDay[day]}h`
                                                        : "Free"
                                                    : teacherWeek.hoursByDay[day] > 0
                                                      ? `${teacherWeek.hoursByDay[day]}h`
                                                      : "Free";

                                          const dayHasClash =
                                              activeTab === "rooms"
                                                  ? roomWeek.blocksByDay[day].some((b) => b.hasConflict)
                                                  : activeTab === "students"
                                                    ? cohortWeek.blocksByDay[day].some((b) => b.hasConflict)
                                                    : teacherWeek.blocksByDay[day].some((b) => b.hasConflict);

                                          const teacherDayLoadPct =
                                              activeTab === "teachers"
                                                  ? Math.min(100, Math.round((teacherWeek.hoursByDay[day] / 6) * 100))
                                                  : 0;

                                          return (
                                              <div
                                                  key={day}
                                                  className="border-border/40 border-r px-3 py-2.5 last:border-r-0"
                                              >
                                                  <div className="flex items-baseline justify-between gap-2">
                                                      <span className="text-xs font-semibold tracking-tight">
                                                          {day.slice(0, 3)}
                                                      </span>
                                                      <span
                                                          className={`text-[11px] tabular-nums ${
                                                              dayHasClash
                                                                  ? "font-semibold text-rose-600 dark:text-rose-400"
                                                                  : "text-muted-foreground"
                                                          }`}
                                                      >
                                                          {daySubtitle}
                                                      </span>
                                                  </div>
                                                  {activeTab === "teachers" && (
                                                      <div className="bg-muted mt-1.5 h-1 overflow-hidden rounded-full">
                                                          <div
                                                              className={`h-full rounded-full ${
                                                                  teacherWeek.hoursByDay[day] >= 6
                                                                      ? "bg-amber-500"
                                                                      : "bg-foreground/50"
                                                              }`}
                                                              style={{ width: `${teacherDayLoadPct}%` }}
                                                          />
                                                      </div>
                                                  )}
                                              </div>
                                          );
                                      })}
                            </div>

                            {/* Vertical Time Grid Body */}
                            <div
                                className="relative grid"
                                style={{
                                    height: `${TOTAL_GRID_HEIGHT}px`,
                                    gridTemplateColumns:
                                        activeTab === "rooms" && roomViewMode === "compare_day"
                                            ? `60px repeat(${Math.max(1, compareDayColumns.length)}, minmax(0, 1fr))`
                                            : `60px repeat(${days.length}, minmax(0, 1fr))`,
                                }}
                            >
                                {/* Left Time Gutter */}
                                <div className="border-border/40 relative border-r select-none">
                                    {CANVAS_HOURS.map((hour, i) => (
                                        <div
                                            key={hour}
                                            style={{ top: `${i * HOUR_HEIGHT}px`, height: `${HOUR_HEIGHT}px` }}
                                            className="absolute right-2 left-0 flex justify-end"
                                        >
                                            <span
                                                className={`text-muted-foreground text-[10px] font-medium tabular-nums ${
                                                    i === 0 ? "translate-y-1" : "-translate-y-2"
                                                }`}
                                            >
                                                {hour === 12 ? "12 PM" : hour > 12 ? `${hour - 12} PM` : `${hour} AM`}
                                            </span>
                                        </div>
                                    ))}
                                </div>

                                {activeTab === "rooms" && roomViewMode === "compare_day"
                                    ? compareDayColumns.map((col) => {
                                          const colKey = `room-${col.room.id}`;
                                          return (
                                              <div
                                                  key={colKey}
                                                  data-canvas-column={colKey}
                                                  onContextMenu={(e) =>
                                                      captureEmptyContextSlot(e, compareDay, col.room.id, col.room.name)
                                                  }
                                                  onDragOver={(e) => handleColumnDragOver(e, colKey)}
                                                  onDrop={(e) =>
                                                      void handleColumnDrop(e, { day: compareDay, roomId: col.room.id })
                                                  }
                                                  className="border-border/40 relative border-r last:border-r-0"
                                              >
                                                  {CANVAS_HOURS.map((hour, i) => (
                                                      <div
                                                          key={hour}
                                                          style={{ top: `${i * HOUR_HEIGHT}px`, height: `${HOUR_HEIGHT}px` }}
                                                          className="border-border/35 pointer-events-none absolute right-0 left-0 border-t"
                                                      />
                                                  ))}

                                                  {dragPreview && dragPreview.columnKey === colKey && (
                                                      <div
                                                          style={{
                                                              top: `${minToPx(dragPreview.startMin)}px`,
                                                              height: `${durationToPx(dragPreview.durationMin)}px`,
                                                          }}
                                                          className="border-primary/70 bg-primary/12 text-primary pointer-events-none absolute right-1 left-1 z-20 flex flex-col justify-between rounded-lg border-2 border-dashed p-1.5 text-[10px] font-semibold"
                                                      >
                                                          <span className="truncate">{draggedBlock?.classItem.subject_code}</span>
                                                          <span className="tabular-nums">
                                                              {formatCompactRange(
                                                                  dragPreview.startMin,
                                                                  dragPreview.startMin + dragPreview.durationMin,
                                                              )}
                                                          </span>
                                                      </div>
                                                  )}

                                                  {renderColumnBlocks(colKey, col.room.name, col.blocks, true, "room")}
                                              </div>
                                          );
                                      })
                                    : days.map((day) => {
                                          const colBlocks =
                                              activeTab === "rooms"
                                                  ? roomWeek.blocksByDay[day]
                                                  : activeTab === "students"
                                                    ? cohortWeek.blocksByDay[day]
                                                    : teacherWeek.blocksByDay[day];

                                          const canDrag =
                                              !(activeTab === "students" && studentSubMode === "individual");

                                          const styleMode: "room" | "student" | "teacher" =
                                              activeTab === "rooms"
                                                  ? "room"
                                                  : activeTab === "students"
                                                    ? "student"
                                                    : "teacher";

                                          const openGaps: OpenGap[] =
                                              activeTab === "rooms"
                                                  ? roomWeek.openGapsByDay[day]
                                                  : activeTab === "students"
                                                    ? cohortWeek.idleGapsByDay[day]
                                                    : [];

                                          const handoffs: TeacherHandoff[] =
                                              activeTab === "teachers" ? teacherWeek.handoffsByDay[day] : [];

                                          return (
                                              <div
                                                  key={day}
                                                  data-canvas-column={day}
                                                  onContextMenu={(e) =>
                                                      captureEmptyContextSlot(
                                                          e,
                                                          day,
                                                          activeTab === "rooms" ? activeRoom?.id ?? null : null,
                                                          activeTab === "rooms" ? activeRoom?.name : undefined,
                                                      )
                                                  }
                                                  onDragOver={(e) => canDrag && handleColumnDragOver(e, day)}
                                                  onDrop={(e) => canDrag && void handleColumnDrop(e, { day })}
                                                  className="border-border/40 relative border-r last:border-r-0"
                                              >
                                                  {CANVAS_HOURS.map((hour, i) => (
                                                      <div
                                                          key={hour}
                                                          style={{ top: `${i * HOUR_HEIGHT}px`, height: `${HOUR_HEIGHT}px` }}
                                                          className="border-border/35 pointer-events-none absolute right-0 left-0 border-t"
                                                      >
                                                          <div className="border-border/20 absolute top-1/2 right-0 left-0 border-t border-dashed" />
                                                      </div>
                                                  ))}

                                                  {/* Tab 1 (Rooms): Quiet Open-Gap Click-to-Schedule Cues */}
                                                  {activeTab === "rooms" &&
                                                      activeRoom &&
                                                      openGaps.map((gap) => (
                                                          <button
                                                              key={gap.key}
                                                              type="button"
                                                              onClick={() =>
                                                                  setQuickAddTarget({
                                                                      roomId: activeRoom.id,
                                                                      day,
                                                                      startTime: minutesToApiTime(gap.startMin),
                                                                      endTime: minutesToApiTime(
                                                                          Math.min(gap.startMin + 90, gap.endMin),
                                                                      ),
                                                                  })
                                                              }
                                                              style={{
                                                                  top: `${minToPx(gap.startMin) + 4}px`,
                                                                  height: `${durationToPx(gap.durationMin) - 8}px`,
                                                              }}
                                                              className="group absolute right-1.5 left-1.5 z-0 flex items-center justify-center rounded-lg border border-transparent transition-colors hover:border-emerald-500/35 hover:bg-emerald-500/[0.06]"
                                                          >
                                                              <span className="text-muted-foreground/45 text-[10px] font-medium opacity-75 transition-colors group-hover:text-emerald-700 group-hover:opacity-100 dark:group-hover:text-emerald-300 tabular-nums">
                                                                  + Open · {Math.round((gap.durationMin / 60) * 10) / 10}h
                                                              </span>
                                                          </button>
                                                      ))}

                                                  {/* Tab 2 (Students & Sections): Long Student Idle-Gap Cue */}
                                                  {activeTab === "students" &&
                                                      openGaps.map((gap) => (
                                                          <div
                                                              key={gap.key}
                                                              style={{
                                                                  top: `${minToPx(gap.startMin) + 6}px`,
                                                                  height: `${durationToPx(gap.durationMin) - 12}px`,
                                                              }}
                                                              className="pointer-events-none absolute right-2 left-2 z-0 flex items-center justify-center rounded-lg border border-dashed border-amber-500/25 bg-amber-500/[0.03]"
                                                          >
                                                              <span className="text-[10px] font-medium text-amber-700/70 dark:text-amber-300/70 tabular-nums">
                                                                  {Math.round((gap.durationMin / 60) * 10) / 10}h student gap
                                                              </span>
                                                          </div>
                                                      ))}

                                                  {/* Tab 3 (Teachers): Back-to-Back Room-Change Handoff Marker */}
                                                  {activeTab === "teachers" &&
                                                      handoffs.map((h) => (
                                                          <div
                                                              key={h.key}
                                                              style={{ top: `${minToPx(h.atMin) - 9}px` }}
                                                              className="pointer-events-none absolute right-1.5 left-1.5 z-20 flex items-center justify-center"
                                                          >
                                                              <span className="border-amber-500/40 bg-amber-50 text-amber-900 dark:bg-amber-950/90 dark:text-amber-200 inline-flex max-w-full items-center gap-1 rounded-full border px-2 py-0.5 text-[9.5px] font-semibold shadow-xs">
                                                                  <ArrowRightLeft className="size-2.5 shrink-0" />
                                                                  <span className="truncate whitespace-nowrap">
                                                                      {h.gapMinutes}m · {h.fromRoom} → {h.toRoom}
                                                                  </span>
                                                              </span>
                                                          </div>
                                                      ))}

                                                  {/* Drag Ghost Preview with live snapped time */}
                                                  {dragPreview && dragPreview.columnKey === day && (
                                                      <div
                                                          style={{
                                                              top: `${minToPx(dragPreview.startMin)}px`,
                                                              height: `${durationToPx(dragPreview.durationMin)}px`,
                                                          }}
                                                          className="border-primary/70 bg-primary/12 text-primary pointer-events-none absolute right-1 left-1 z-20 flex flex-col justify-between rounded-lg border-2 border-dashed p-1.5 text-[10px] font-semibold"
                                                      >
                                                          <span className="truncate">{draggedBlock?.classItem.subject_code}</span>
                                                          <span className="tabular-nums">
                                                              {formatCompactRange(
                                                                  dragPreview.startMin,
                                                                  dragPreview.startMin + dragPreview.durationMin,
                                                              )}
                                                          </span>
                                                      </div>
                                                  )}

                                                  {renderColumnBlocks(day, day, colBlocks, canDrag, styleMode)}
                                              </div>
                                          );
                                      })}
                            </div>
                        </div>
                    )}
                </main>
            </div>

            {renderFloatingContextMenu()}

            {/* ── SLIDING SHEET 1: BLOCK INSPECTOR + INLINE EDIT + 1-CLICK FIXES ── */}
            <BlockPopover
                block={inspectedBlock?.block ?? null}
                initialMode={inspectedBlock?.mode ?? "view"}
                contextTab={activeTab}
                readOnly={activeTab === "students" && studentSubMode === "individual"}
                rooms={creation_options.rooms}
                days={days}
                suggestions={inspectedSuggestions}
                loadingSuggestions={inspectedLoadingSuggestions}
                onClose={() => setInspectedBlock(null)}
                onSaveEdit={handleSaveInlineEdit}
                onDelete={async (block) => {
                    if (block.session.id !== undefined) {
                        await studio.deleteScheduleBlock(block.session.id);
                    }
                }}
                onApplyRoom={handleApplyRoomFix}
                onApplySlot={handleApplySlotFix}
                onConvertOnline={handleConvertOnlineFix}
            />

            {/* ── SLIDING SHEET 2: FILTERS & VIEW PREFERENCES ── */}
            <Sheet open={filterSheetOpen} onOpenChange={setFilterSheetOpen}>
                <SheetContent side="right" className="w-full gap-0 p-0 sm:max-w-sm">
                    <SheetHeader className="border-border/60 border-b px-5 py-4 pr-11 text-left">
                        <SheetTitle className="text-base font-semibold">Filter Schedule</SheetTitle>
                        <SheetDescription className="text-xs">
                            Refine what appears on the weekly calendar without cluttering the workspace.
                        </SheetDescription>
                    </SheetHeader>

                    <div className="flex-1 space-y-5 overflow-y-auto p-5">
                        <div className="bg-muted/25 border-border/60 flex items-center justify-between gap-3 rounded-xl border p-3.5">
                            <div className="space-y-0.5">
                                <p className="text-xs font-semibold">Show conflicts only</p>
                                <p className="text-muted-foreground text-[11px]">
                                    Focus strictly on overlapping classes
                                </p>
                            </div>
                            <Switch
                                checked={conflictsOnly}
                                onCheckedChange={setConflictsOnly}
                                aria-label="Show conflicts only"
                            />
                        </div>

                        <div className="space-y-2">
                            <label className="text-muted-foreground text-[11px] font-semibold uppercase tracking-wider">
                                Delivery Mode
                            </label>
                            <div className="bg-muted/60 grid grid-cols-3 gap-1 rounded-xl p-1">
                                {(
                                    [
                                        { id: "all", label: "All" },
                                        { id: "face_to_face", label: "Campus" },
                                        { id: "online", label: "Online" },
                                    ] as Array<{ id: DeliveryFilter; label: string }>
                                ).map((opt) => (
                                    <button
                                        key={opt.id}
                                        type="button"
                                        onClick={() => setDeliveryFilter(opt.id)}
                                        className={`rounded-lg py-1.5 text-xs font-medium transition-colors ${
                                            deliveryFilter === opt.id
                                                ? "bg-background text-foreground shadow-2xs"
                                                : "text-muted-foreground hover:text-foreground"
                                        }`}
                                    >
                                        {opt.label}
                                    </button>
                                ))}
                            </div>
                        </div>

                        {availableRoomTypes.length > 0 && (
                            <div className="space-y-2">
                                <label className="text-muted-foreground text-[11px] font-semibold uppercase tracking-wider">
                                    Room Type
                                </label>
                                <Select value={roomTypeFilter} onValueChange={setRoomTypeFilter}>
                                    <SelectTrigger className="h-9 rounded-xl text-xs capitalize">
                                        <SelectValue placeholder="All room types" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All room types</SelectItem>
                                        {availableRoomTypes.map((type) => (
                                            <SelectItem key={type} value={type} className="capitalize">
                                                {type}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        )}

                        {availableDepartments.length > 0 && (
                            <div className="space-y-2">
                                <label className="text-muted-foreground text-[11px] font-semibold uppercase tracking-wider">
                                    Faculty Department
                                </label>
                                <Select value={departmentFilter} onValueChange={setDepartmentFilter}>
                                    <SelectTrigger className="h-9 rounded-xl text-xs">
                                        <SelectValue placeholder="All departments" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All departments</SelectItem>
                                        {availableDepartments.map((dept) => (
                                            <SelectItem key={dept} value={dept}>
                                                {dept}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        )}

                        <div className="bg-muted/25 border-border/60 flex items-center justify-between gap-3 rounded-xl border p-3.5">
                            <div className="space-y-0.5">
                                <p className="text-xs font-semibold">Include Sunday</p>
                                <p className="text-muted-foreground text-[11px]">
                                    Allow and display Sunday schedule columns
                                </p>
                            </div>
                            <Switch
                                checked={studio.policy.allow_sunday_scheduling}
                                onCheckedChange={() => void studio.toggleSundayScheduling()}
                                aria-label="Include Sunday"
                            />
                        </div>
                    </div>

                    <SheetFooter className="border-border/60 flex-row items-center justify-between border-t px-5 py-3.5">
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            className="h-8 rounded-lg text-xs"
                            disabled={activeFilterCount === 0 && searchQuery === ""}
                            onClick={resetAllFilters}
                        >
                            Reset all
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            className="h-8 rounded-lg text-xs"
                            onClick={() => setFilterSheetOpen(false)}
                        >
                            Done
                        </Button>
                    </SheetFooter>
                </SheetContent>
            </Sheet>

            {/* ── SLIDING SHEET 3: OVERFLOW CLUSTER PICKER ── */}
            <Sheet open={Boolean(clusterBlocks)} onOpenChange={(open) => !open && setClusterBlocks(null)}>
                <SheetContent side="right" className="w-full gap-0 p-0 sm:max-w-sm">
                    <SheetHeader className="border-border/60 border-b px-5 py-4 pr-11 text-left">
                        <SheetTitle className="text-sm font-semibold">{clusterBlocks?.title}</SheetTitle>
                        <SheetDescription className="text-xs">
                            Select any overlapping class to inspect, edit, or apply a 1-click fix.
                        </SheetDescription>
                    </SheetHeader>
                    <div className="flex-1 space-y-2 overflow-y-auto p-4">
                        {clusterBlocks?.blocks.map((block) => (
                            <button
                                key={block.key}
                                type="button"
                                onClick={() => {
                                    setClusterBlocks(null);
                                    setInspectedBlock({ block, mode: "view" });
                                }}
                                className="hover:bg-muted/60 border-border/60 flex w-full items-center justify-between gap-2 rounded-xl border px-3.5 py-2.5 text-left text-xs transition-colors"
                            >
                                <div className="min-w-0">
                                    <p className="font-semibold">
                                        {block.classItem.subject_code} · Sec {block.classItem.section}
                                    </p>
                                    <p className="text-muted-foreground mt-0.5 truncate text-[11px]">
                                        {block.classItem.faculty_name ?? "No instructor"} · {block.session.room ?? "Online"}
                                    </p>
                                </div>
                                <span className="text-muted-foreground shrink-0 text-[11px] tabular-nums">
                                    {formatCompactRange(block.startMin, block.endMin)}
                                </span>
                            </button>
                        ))}
                    </div>
                </SheetContent>
            </Sheet>

            {/* ── SLIDING SHEET 4: QUICK SCHEDULE / ADD SESSION ── */}
            <QuickAddSheet
                target={quickAddTarget}
                classes={studio.classes}
                rooms={creation_options.rooms}
                days={days}
                onClose={() => setQuickAddTarget(null)}
                onCreate={studio.addClassScheduleBlocks}
            />

            {/* Secondary Term-Level Utility Dialogs */}
            <SchedulingReviewDialog
                key={`${studio.reviewOpen}-${studio.draftCount}`}
                open={studio.reviewOpen}
                onOpenChange={studio.setReviewOpen}
                classes={studio.classes}
                conflictCount={studio.conflicts.length}
                draftCount={studio.draftCount}
                termLabel={`${studio.schoolYear} · ${term_context?.semester_label ?? `Semester ${studio.semester}`}`}
                isPublishing={studio.publishing}
                onPublish={studio.publishDrafts}
                onResolveConflicts={() => studio.setReviewOpen(false)}
            />

            <GenerateOfferingsDialog
                open={studio.generateOpen}
                onOpenChange={studio.setGenerateOpen}
                schoolYear={studio.schoolYear}
                semester={studio.semester}
                courses={creation_options.courses}
                shsStrands={creation_options.shs_strands}
                rooms={creation_options.rooms}
                onGenerate={studio.generateClasses}
            />

            <ScheduleExportDialog
                open={studio.exportOpen}
                onOpenChange={studio.setExportOpen}
                courses={filters.available_courses}
                initialCourseId={null}
            />

            <SchedulingPolicyDialog
                open={studio.policyOpen}
                onOpenChange={studio.setPolicyOpen}
                policy={studio.policy}
                onSave={studio.saveSchedulingPolicy}
            />
        </AdminLayout>
    );
}
