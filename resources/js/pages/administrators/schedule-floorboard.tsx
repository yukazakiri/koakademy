import AdminLayout from "@/components/administrators/admin-layout";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Input } from "@/components/ui/input";
import { create as createClass, show as showClass } from "@/routes/administrators/classes";
import { index as boardIndex } from "@/routes/administrators/schedule-board";
import { index as triageIndex } from "@/routes/administrators/schedule-triage";
import { index as xrayIndex } from "@/routes/administrators/schedule-xray";
import { index as studioIndex } from "@/routes/administrators/scheduling-analytics";
import { Head, Link } from "@inertiajs/react";
import {
    AlertTriangle,
    ArrowRight,
    ArrowUpRight,
    Building2,
    CalendarDays,
    CheckCircle2,
    Clock,
    Download,
    GripVertical,
    Inbox,
    LayoutGrid,
    Loader2,
    MapPin,
    Monitor,
    MoreHorizontal,
    Plus,
    Redo2,
    Search,
    Send,
    SlidersHorizontal,
    Sparkles,
    Sun,
    Undo2,
    User,
    Users,
} from "lucide-react";
import { useMemo, useState } from "react";
import GenerateOfferingsDialog from "./components/generate-offerings-dialog";
import ScheduleExportDialog from "./components/schedule-export-dialog";
import SchedulingPolicyDialog from "./components/scheduling-policy-dialog";
import SchedulingReviewDialog from "./components/scheduling-review-dialog";
import SchedulingSessionDialog from "./components/scheduling-session-dialog";
import type { RoomOption, SchedulingAnalyticsProps } from "./components/scheduling-types";
import {
    buildFloorboardForDayWave,
    buildWaveSummariesForDay,
    CAMPUS_WAVES,
    type FloorRoomCardData,
    type RoomTicket,
    type TimeWaveId,
} from "./schedule-floorboard/lib/floorboard-engine";
import { useScheduleStudio } from "./scheduling/hooks/use-schedule-studio";
import { formatTimeRange } from "./scheduling/lib/time-geometry";

type RoomStatusFilter = "all" | "open" | "double_booked" | "occupied";

export default function ScheduleFloorboard(props: SchedulingAnalyticsProps) {
    const { user, term_context, filters, creation_options } = props;
    const studio = useScheduleStudio(props);

    const [selectedDay, setSelectedDay] = useState<string>("Monday");
    const [selectedWaveId, setSelectedWaveId] = useState<TimeWaveId>("morning");
    const [statusFilter, setStatusFilter] = useState<RoomStatusFilter>("all");
    const [search, setSearch] = useState("");
    const [draggedTicket, setDraggedTicket] = useState<RoomTicket | null>(null);
    const [hoveredRoomId, setHoveredRoomId] = useState<number | null>(null);
    const [seatRoomTarget, setSeatRoomTarget] = useState<RoomOption | null>(null);
    const [movingTicketKey, setMovingTicketKey] = useState<string | null>(null);

    const days = studio.visibleDays;
    const activeDay = days.includes(selectedDay) ? selectedDay : days[0];
    const activeWave = CAMPUS_WAVES.find((w) => w.id === selectedWaveId) ?? CAMPUS_WAVES[0];

    // Daily summary metrics for the top Day Ribbon
    const dayLoadStats = useMemo(() => {
        const map = new Map<string, { sessions: number; pct: number }>();
        const totalRooms = Math.max(1, creation_options.rooms.length);
        for (const day of days) {
            let count = 0;
            for (const cls of studio.classes) {
                for (const s of cls.schedules) {
                    if (s.day_of_week === day) count++;
                }
            }
            map.set(day, { sessions: count, pct: Math.min(100, Math.round((count / (totalRooms * 4)) * 100)) });
        }
        return map;
    }, [days, studio.classes, creation_options.rooms.length]);

    const waveSummaries = useMemo(
        () =>
            buildWaveSummariesForDay({
                day: activeDay,
                rooms: creation_options.rooms,
                classes: studio.classes,
                conflicts: studio.conflicts,
            }),
        [activeDay, creation_options.rooms, studio.classes, studio.conflicts],
    );

    const floorData = useMemo(
        () =>
            buildFloorboardForDayWave({
                day: activeDay,
                wave: activeWave,
                rooms: creation_options.rooms,
                classes: studio.classes,
                conflicts: studio.conflicts,
            }),
        [activeDay, activeWave, creation_options.rooms, studio.classes, studio.conflicts],
    );

    const visibleRoomCards = useMemo(() => {
        const q = search.trim().toLowerCase();
        return floorData.roomCards.filter((card) => {
            if (statusFilter !== "all" && card.status !== statusFilter) return false;
            if (!q) return true;
            const roomMatch = `${card.room.name} ${card.room.building ?? ""} ${card.room.type ?? ""}`
                .toLowerCase()
                .includes(q);
            const ticketMatch = card.tickets.some((t) =>
                `${t.classItem.subject_code} ${t.classItem.subject_title} ${t.classItem.faculty_name ?? ""} ${t.classItem.section}`
                    .toLowerCase()
                    .includes(q),
            );
            return roomMatch || ticketMatch;
        });
    }, [floorData.roomCards, statusFilter, search]);

    const counts = useMemo(
        () => ({
            all: floorData.roomCards.length,
            open: floorData.roomCards.filter((r) => r.status === "open").length,
            double_booked: floorData.roomCards.filter((r) => r.status === "double_booked").length,
            occupied: floorData.roomCards.filter((r) => r.status === "occupied").length,
        }),
        [floorData.roomCards],
    );

    const moveTicketToRoom = async (ticket: RoomTicket, targetRoom: RoomOption) => {
        if (ticket.session.room_id === targetRoom.id) return;
        setMovingTicketKey(ticket.key);
        try {
            await studio.updateScheduleBlockDirect(
                ticket.classItem,
                ticket.session,
                { room_id: targetRoom.id, delivery_mode: "face_to_face" },
                `Moved ${ticket.classItem.subject_code} into ${targetRoom.name}`,
            );
        } finally {
            setMovingTicketKey(null);
            setDraggedTicket(null);
            setHoveredRoomId(null);
        }
    };

    const editorClass = studio.editorTarget
        ? studio.classes.find((c) => c.id === studio.editorTarget?.classId)
        : undefined;
    const editorSession =
        editorClass && studio.editorTarget?.sessionId !== undefined
            ? editorClass.schedules.find((s) => s.id === studio.editorTarget?.sessionId)
            : undefined;

    return (
        <AdminLayout user={user} title="Campus Floorboard">
            <Head title="Campus Floorboard" />

            <div className="mx-auto flex max-w-[1600px] flex-col gap-5 p-4 sm:p-6">
                {/* 1. Architectural Top Title & Controls */}
                <header className="flex flex-wrap items-end justify-between gap-4">
                    <div className="space-y-1">
                        <p className="text-muted-foreground text-[11px] font-semibold uppercase tracking-[0.14em]">
                            Live Spatial Room Map · {term_context?.semester_label ?? `Semester ${studio.semester}`} · SY {studio.schoolYear}
                        </p>
                        <h1 className="text-2xl font-bold tracking-tight sm:text-3xl">Campus Floorboard</h1>
                        <p className="text-muted-foreground text-xs sm:text-sm">
                            Scan physical rooms like a floor map. Drag any class onto a green open room card to re-seat it instantly.
                        </p>
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            className="h-9 gap-1.5 rounded-xl text-xs"
                            disabled={!studio.history.canUndo}
                            onClick={() => void studio.history.undo()}
                        >
                            <Undo2 className="size-3.5" /> Undo
                        </Button>
                        <Button
                            type="button"
                            variant={studio.policy.allow_sunday_scheduling ? "secondary" : "outline"}
                            size="sm"
                            className="h-9 gap-1.5 rounded-xl text-xs"
                            onClick={() => void studio.toggleSundayScheduling()}
                        >
                            <Sun className="size-3.5" />
                            Sunday {studio.policy.allow_sunday_scheduling ? "On" : "Off"}
                        </Button>
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button type="button" variant="outline" size="icon" className="size-9 rounded-xl" aria-label="More options">
                                    <MoreHorizontal className="size-4" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" className="w-60">
                                <DropdownMenuItem disabled={!studio.history.canRedo} onSelect={() => void studio.history.redo()} className="flex items-center gap-2">
                                    <Redo2 className="size-3.5" /> Redo (Ctrl+Shift+Z)
                                </DropdownMenuItem>
                                <DropdownMenuItem onSelect={() => studio.setPolicyOpen(true)} className="flex items-center gap-2">
                                    <SlidersHorizontal className="size-3.5" /> Conflict rules
                                </DropdownMenuItem>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem asChild>
                                    <Link href={createClass.url()} className="flex items-center gap-2">
                                        <Plus className="size-3.5" /> Create new class
                                    </Link>
                                </DropdownMenuItem>
                                <DropdownMenuItem onSelect={() => studio.setGenerateOpen(true)} className="flex items-center gap-2">
                                    <Sparkles className="size-3.5" /> Generate from curriculum
                                </DropdownMenuItem>
                                <DropdownMenuItem onSelect={() => studio.setExportOpen(true)} className="flex items-center gap-2">
                                    <Download className="size-3.5" /> Export PDF timetable
                                </DropdownMenuItem>
                                <DropdownMenuItem onSelect={() => studio.setReviewOpen(true)} className="flex items-center gap-2">
                                    <Send className="size-3.5" /> Review &amp; publish ({studio.draftCount} draft)
                                </DropdownMenuItem>
                                <DropdownMenuSeparator />
                                <DropdownMenuLabel className="text-[11px]">Other Schedule Concepts</DropdownMenuLabel>
                                <DropdownMenuItem asChild>
                                    <Link href={triageIndex.url()} className="flex items-center gap-2">
                                        <Inbox className="size-3.5" /> Schedule Triage (Inbox)
                                    </Link>
                                </DropdownMenuItem>
                                <DropdownMenuItem asChild>
                                    <Link href={xrayIndex.url()} className="flex items-center gap-2">
                                        <Sparkles className="size-3.5" /> Schedule X-Ray
                                    </Link>
                                </DropdownMenuItem>
                                <DropdownMenuItem asChild>
                                    <Link href={boardIndex.url()} className="flex items-center gap-2">
                                        <CalendarDays className="size-3.5" /> Schedule Board
                                    </Link>
                                </DropdownMenuItem>
                                <DropdownMenuItem asChild>
                                    <Link href={studioIndex.url()} className="flex items-center gap-2">
                                        <LayoutGrid className="size-3.5" /> Multi-Lens Studio
                                    </Link>
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                </header>

                {/* 2. Architectural Day Ribbon (Full Width — Zero Sidebars!) */}
                <section
                    aria-label="Select campus day"
                    className={`grid grid-cols-3 gap-2 ${days.length === 7 ? "sm:grid-cols-7" : "sm:grid-cols-6"}`}
                >
                    {days.map((day) => {
                        const stat = dayLoadStats.get(day) ?? { sessions: 0, pct: 0 };
                        const active = day === activeDay;
                        return (
                            <button
                                key={day}
                                type="button"
                                onClick={() => setSelectedDay(day)}
                                className={`flex flex-col justify-between gap-2 rounded-2xl border p-3 text-left transition-all active:scale-[0.98] ${
                                    active
                                        ? "bg-foreground text-background border-foreground shadow-md"
                                        : "bg-card/80 border-border/60 hover:border-border text-foreground"
                                }`}
                            >
                                <div className="flex items-baseline justify-between gap-1">
                                    <span className="text-sm font-bold tracking-tight">{day.slice(0, 3)}</span>
                                    <span
                                        className={`text-[11px] font-medium tabular-nums ${
                                            active ? "text-background/75" : "text-muted-foreground"
                                        }`}
                                    >
                                        {stat.sessions} cls
                                    </span>
                                </div>
                                <div
                                    className={`h-1.5 w-full overflow-hidden rounded-full ${
                                        active ? "bg-background/20" : "bg-muted"
                                    }`}
                                >
                                    <div
                                        className={`h-full rounded-full ${
                                            active ? "bg-emerald-400" : "bg-foreground/50"
                                        }`}
                                        style={{ width: `${Math.max(stat.sessions > 0 ? 10 : 0, stat.pct)}%` }}
                                    />
                                </div>
                            </button>
                        );
                    })}
                </section>

                {/* 3. Four Campus Time Waves */}
                <section aria-label="Select time wave" className="grid grid-cols-1 gap-2.5 sm:grid-cols-2 lg:grid-cols-4">
                    {waveSummaries.map(({ wave, openRoomsCount, doubleBookedRoomsCount, occupiedRoomsCount }) => {
                        const active = wave.id === activeWave.id;
                        return (
                            <button
                                key={wave.id}
                                type="button"
                                onClick={() => setSelectedWaveId(wave.id)}
                                className={`flex items-center justify-between gap-3 rounded-2xl border px-4 py-3 text-left transition-all active:scale-[0.99] ${
                                    active
                                        ? "bg-card border-foreground/70 ring-foreground/20 shadow-sm ring-2"
                                        : "bg-card/55 border-border/60 hover:bg-card hover:border-border"
                                }`}
                            >
                                <div className="space-y-0.5">
                                    <div className="flex items-center gap-2">
                                        <span className="text-sm font-semibold tracking-tight">{wave.label}</span>
                                        {doubleBookedRoomsCount > 0 && (
                                            <span className="rounded-full bg-rose-500/15 px-1.5 py-0.5 text-[10px] font-semibold text-rose-700 tabular-nums dark:text-rose-300">
                                                {doubleBookedRoomsCount} clash
                                            </span>
                                        )}
                                    </div>
                                    <p className="text-muted-foreground text-xs tabular-nums">{wave.rangeLabel}</p>
                                </div>
                                <div className="text-right text-[11px] tabular-nums">
                                    <p className="font-semibold text-emerald-700 dark:text-emerald-400">
                                        {openRoomsCount} open
                                    </p>
                                    <p className="text-muted-foreground">{occupiedRoomsCount + doubleBookedRoomsCount} busy</p>
                                </div>
                            </button>
                        );
                    })}
                </section>

                {/* 4. Filter Pills + Search + Drag Indicator */}
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div className="flex flex-wrap items-center gap-1.5">
                        {(
                            [
                                { id: "all", label: `All Rooms (${counts.all})` },
                                { id: "double_booked", label: `Double-Booked (${counts.double_booked})` },
                                { id: "open", label: `Open Now (${counts.open})` },
                                { id: "occupied", label: `In Use (${counts.occupied})` },
                            ] as Array<{ id: RoomStatusFilter; label: string }>
                        ).map((pill) => (
                            <button
                                key={pill.id}
                                type="button"
                                onClick={() => setStatusFilter(pill.id)}
                                className={`rounded-full border px-3 py-1.5 text-xs font-medium transition-colors tabular-nums ${
                                    statusFilter === pill.id
                                        ? "bg-foreground text-background border-foreground"
                                        : "bg-card/70 hover:bg-muted border-border/60 text-foreground"
                                }`}
                            >
                                {pill.label}
                            </button>
                        ))}
                    </div>

                    <div className="flex items-center gap-2">
                        {draggedTicket && (
                            <Badge className="bg-emerald-600 text-white">
                                Drop {draggedTicket.classItem.subject_code} onto any green Open Room
                            </Badge>
                        )}
                        <div className="relative w-64">
                            <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-3.5 -translate-y-1/2" />
                            <Input
                                type="search"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Find room, class, teacher…"
                                className="bg-card h-9 rounded-full pl-8.5 text-xs"
                            />
                        </div>
                    </div>
                </div>

                {/* 5. THE SPATIAL ROOM FLOORPLAN GRID */}
                <section aria-label="Physical room floorplan" className="grid grid-cols-1 gap-3.5 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
                    {visibleRoomCards.map((card: FloorRoomCardData) => {
                        const isDropTarget = draggedTicket !== null && hoveredRoomId === card.room.id;
                        const isOpen = card.status === "open";
                        const isDouble = card.status === "double_booked";

                        return (
                            <div
                                key={card.room.id}
                                onDragOver={(e) => {
                                    e.preventDefault();
                                    setHoveredRoomId(card.room.id);
                                }}
                                onDragLeave={() => setHoveredRoomId((curr) => (curr === card.room.id ? null : curr))}
                                onDrop={(e) => {
                                    e.preventDefault();
                                    if (draggedTicket) {
                                        void moveTicketToRoom(draggedTicket, card.room);
                                    }
                                }}
                                className={`flex flex-col overflow-hidden rounded-2xl border transition-all ${
                                    isDropTarget
                                        ? "border-emerald-500 bg-emerald-500/15 ring-2 ring-emerald-500/40 shadow-lg scale-[1.01]"
                                        : isDouble
                                          ? "bg-card border-rose-400/70 shadow-sm"
                                          : isOpen
                                            ? "border-emerald-500/35 bg-emerald-500/[0.04] border-dashed hover:border-emerald-500/60"
                                            : "bg-card border-border/70 shadow-2xs"
                                }`}
                            >
                                {/* Room Card Header */}
                                <div
                                    className={`flex items-center justify-between gap-2 border-b px-4 py-3 ${
                                        isDouble
                                            ? "border-rose-400/30 bg-rose-500/[0.08]"
                                            : isOpen
                                              ? "border-emerald-500/20 bg-emerald-500/[0.05]"
                                              : "border-border/50 bg-muted/20"
                                    }`}
                                >
                                    <div className="min-w-0">
                                        <div className="flex items-center gap-1.5">
                                            <Building2 className="text-muted-foreground size-3.5 shrink-0" />
                                            <h2 className="truncate text-sm font-bold tracking-tight">{card.room.name}</h2>
                                        </div>
                                        <p className="text-muted-foreground truncate text-[11px] tabular-nums">
                                            {card.room.building ? `${card.room.building} · ` : ""}
                                            {card.capacity} seats
                                        </p>
                                    </div>

                                    {isDouble ? (
                                        <span className="inline-flex shrink-0 items-center gap-1 rounded-full bg-rose-600 px-2.5 py-0.5 text-[10px] font-semibold text-white tabular-nums">
                                            <AlertTriangle className="size-3" />
                                            {card.competingCount} competing
                                        </span>
                                    ) : isOpen ? (
                                        <span className="inline-flex shrink-0 items-center gap-1 rounded-full bg-emerald-600/15 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-emerald-700 dark:text-emerald-300">
                                            <CheckCircle2 className="size-3" />
                                            Open
                                        </span>
                                    ) : (
                                        <span className="text-muted-foreground inline-flex shrink-0 items-center gap-1 text-[11px] font-medium tabular-nums">
                                            <Users className="size-3" />
                                            {card.peakStudents}/{card.capacity}
                                        </span>
                                    )}
                                </div>

                                {/* Room Interior */}
                                <div className="flex flex-1 flex-col justify-between gap-2.5 p-3.5">
                                    {isOpen ? (
                                        <div className="flex flex-1 flex-col items-center justify-center gap-2 py-5 text-center">
                                            <p className="text-xs font-medium text-emerald-800 dark:text-emerald-300">
                                                {isDropTarget
                                                    ? `Release to move into ${card.room.name}`
                                                    : `Free during ${activeWave.label} (${activeWave.rangeLabel})`}
                                            </p>
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="outline"
                                                className="h-8 gap-1.5 rounded-xl border-emerald-500/40 bg-background/80 text-xs hover:bg-emerald-500/10"
                                                onClick={() => setSeatRoomTarget(card.room)}
                                            >
                                                <Plus className="size-3.5" /> Seat a class here
                                            </Button>
                                        </div>
                                    ) : (
                                        <div className="space-y-2">
                                            {card.tickets.map((ticket) => {
                                                const isMoving = movingTicketKey === ticket.key;
                                                return (
                                                    <div
                                                        key={ticket.key}
                                                        draggable
                                                        onDragStart={() => setDraggedTicket(ticket)}
                                                        onDragEnd={() => {
                                                            setDraggedTicket(null);
                                                            setHoveredRoomId(null);
                                                        }}
                                                        className={`bg-background group rounded-xl border p-2.5 transition-all ${
                                                            ticket.hasRoomConflict
                                                                ? "border-rose-400/60 shadow-2xs"
                                                                : "border-border/70"
                                                        } cursor-grab active:cursor-grabbing`}
                                                    >
                                                        <div className="flex items-start justify-between gap-2">
                                                            <div className="flex min-w-0 items-start gap-1.5">
                                                                <GripVertical className="text-muted-foreground/60 mt-0.5 size-3.5 shrink-0" />
                                                                <div className="min-w-0">
                                                                    <div className="flex flex-wrap items-center gap-1.5">
                                                                        <Link
                                                                            href={showClass.url(ticket.classItem.id)}
                                                                            className="hover:text-primary text-xs font-bold tracking-tight hover:underline"
                                                                        >
                                                                            {ticket.classItem.subject_code}
                                                                        </Link>
                                                                        <Badge variant="secondary" className="h-4 px-1.5 text-[10px]">
                                                                            Sec {ticket.classItem.section}
                                                                        </Badge>
                                                                        {isMoving && <Loader2 className="size-3 animate-spin" />}
                                                                    </div>
                                                                    <p className="text-muted-foreground line-clamp-1 text-[11px]">
                                                                        {ticket.classItem.subject_title}
                                                                    </p>
                                                                </div>
                                                            </div>

                                                            {/* 1-Click Quick Move to Any Open Room */}
                                                            {floorData.openRooms.length > 0 && (
                                                                <DropdownMenu>
                                                                    <DropdownMenuTrigger asChild>
                                                                        <Button
                                                                            type="button"
                                                                            size="sm"
                                                                            variant={ticket.hasRoomConflict ? "destructive" : "ghost"}
                                                                            className="h-6 shrink-0 gap-1 rounded-lg px-2 text-[10px]"
                                                                            disabled={isMoving}
                                                                        >
                                                                            <span>Move</span>
                                                                            <ArrowRight className="size-2.5" />
                                                                        </Button>
                                                                    </DropdownMenuTrigger>
                                                                    <DropdownMenuContent align="end" className="w-56">
                                                                        <DropdownMenuLabel className="text-[11px]">
                                                                            Open rooms in {activeWave.label}
                                                                        </DropdownMenuLabel>
                                                                        {floorData.openRooms.slice(0, 6).map((openRoom) => (
                                                                            <DropdownMenuItem
                                                                                key={openRoom.id}
                                                                                onSelect={() => void moveTicketToRoom(ticket, openRoom)}
                                                                                className="flex items-center justify-between gap-2 text-xs"
                                                                            >
                                                                                <span className="flex items-center gap-1.5 truncate">
                                                                                    <MapPin className="size-3 text-emerald-600" />
                                                                                    {openRoom.name}
                                                                                </span>
                                                                                <span className="text-muted-foreground text-[10px] tabular-nums">
                                                                                    Cap {openRoom.capacity ?? 40}
                                                                                </span>
                                                                            </DropdownMenuItem>
                                                                        ))}
                                                                    </DropdownMenuContent>
                                                                </DropdownMenu>
                                                            )}
                                                        </div>

                                                        <div className="text-muted-foreground mt-2 flex flex-wrap items-center justify-between gap-1.5 pl-5 text-[11px]">
                                                            <span className="inline-flex items-center gap-1 font-medium text-foreground/85 tabular-nums">
                                                                <Clock className="size-3" />
                                                                {formatTimeRange(ticket.startMin, ticket.endMin)}
                                                            </span>
                                                            <span className="inline-flex items-center gap-1 truncate">
                                                                <User className="size-3" />
                                                                {ticket.classItem.faculty_name ?? "Unassigned"}
                                                            </span>
                                                        </div>
                                                    </div>
                                                );
                                            })}
                                        </div>
                                    )}
                                </div>
                            </div>
                        );
                    })}
                </section>

                {/* Online & Unassigned Strip (only if present in this wave) */}
                {(floorData.onlineTickets.length > 0 || floorData.unassignedTickets.length > 0) && (
                    <section className="bg-card/70 border-border/60 rounded-2xl border p-4">
                        <div className="flex flex-wrap items-center justify-between gap-2">
                            <h3 className="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                <Monitor className="size-3.5" />
                                Online &amp; Unassigned Room Sessions ({floorData.onlineTickets.length + floorData.unassignedTickets.length})
                            </h3>
                        </div>
                        <div className="mt-3 flex flex-wrap gap-2">
                            {[...floorData.unassignedTickets, ...floorData.onlineTickets].map((t) => (
                                <div
                                    key={t.key}
                                    draggable
                                    onDragStart={() => setDraggedTicket(t)}
                                    onDragEnd={() => setDraggedTicket(null)}
                                    className="bg-background border-border/70 inline-flex cursor-grab items-center gap-2 rounded-xl border px-3 py-1.5 text-xs"
                                >
                                    <span className="font-semibold">
                                        {t.classItem.subject_code} · Sec {t.classItem.section}
                                    </span>
                                    <span className="text-muted-foreground tabular-nums">
                                        {formatTimeRange(t.startMin, t.endMin)}
                                    </span>
                                    <Link href={showClass.url(t.classItem.id)} className="text-primary hover:underline">
                                        <ArrowUpRight className="size-3" />
                                    </Link>
                                </div>
                            ))}
                        </div>
                    </section>
                )}
            </div>

            {/* Seat a Class into an Open Room Dialog */}
            <Dialog open={seatRoomTarget !== null} onOpenChange={(open) => !open && setSeatRoomTarget(null)}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Seat a class in {seatRoomTarget?.name}</DialogTitle>
                        <DialogDescription>
                            {activeDay} · {activeWave.label} ({activeWave.rangeLabel}) · Capacity {seatRoomTarget?.capacity ?? 40}
                        </DialogDescription>
                    </DialogHeader>
                    <div className="max-h-72 space-y-1.5 overflow-y-auto py-1">
                        {studio.classes.slice(0, 30).map((cls) => (
                            <button
                                key={cls.id}
                                type="button"
                                onClick={() => {
                                    const room = seatRoomTarget;
                                    setSeatRoomTarget(null);
                                    studio.setEditorTarget({
                                        classId: cls.id,
                                        initialDay: activeDay,
                                        initialStartTime: activeWave.defaultStartApi,
                                        initialEndTime: activeWave.defaultEndApi,
                                        initialRoomId: room?.id ?? null,
                                    });
                                }}
                                className="hover:bg-muted flex w-full items-center justify-between gap-2 rounded-xl border px-3.5 py-2.5 text-left transition-colors"
                            >
                                <div className="min-w-0">
                                    <p className="text-sm font-semibold">
                                        {cls.subject_code}{" "}
                                        <span className="text-muted-foreground text-xs font-normal">Sec {cls.section}</span>
                                    </p>
                                    <p className="text-muted-foreground truncate text-xs">{cls.subject_title}</p>
                                </div>
                                <Plus className="text-muted-foreground size-4 shrink-0" />
                            </button>
                        ))}
                    </div>
                </DialogContent>
            </Dialog>

            {/* Standard Dialogs */}
            {editorClass && (
                <SchedulingSessionDialog
                    key={`${editorClass.id}:${studio.editorTarget?.sessionId ?? "new"}:${studio.editorTarget?.initialDay ?? ""}`}
                    classItem={editorClass}
                    session={editorSession}
                    rooms={creation_options.rooms}
                    allowSunday={studio.policy.allow_sunday_scheduling}
                    initialDay={studio.editorTarget?.initialDay}
                    initialStartTime={studio.editorTarget?.initialStartTime}
                    initialEndTime={studio.editorTarget?.initialEndTime}
                    initialRoomId={studio.editorTarget?.initialRoomId}
                    onClose={() => studio.setEditorTarget(null)}
                    onSave={studio.saveEditorSession}
                    onDelete={async () => {
                        if (editorSession?.id !== undefined) {
                            await studio.deleteScheduleBlock(editorSession.id);
                        }
                    }}
                />
            )}

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
