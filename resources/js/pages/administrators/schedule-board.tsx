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
    SheetHeader,
    SheetTitle,
} from "@/components/ui/sheet";
import { create as createClass } from "@/routes/administrators/classes";
import { index as mainScheduleIndex } from "@/routes/administrators/scheduling-analytics";
import { Head, Link } from "@inertiajs/react";
import {
    AlertTriangle,
    Building2,
    CalendarDays,
    CheckCircle2,
    ChevronLeft,
    ChevronRight,
    Clock,
    Download,
    GraduationCap,
    List,
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
    X,
} from "lucide-react";
import { useEffect, useMemo, useState } from "react";
import GenerateOfferingsDialog from "./components/generate-offerings-dialog";
import ScheduleExportDialog from "./components/schedule-export-dialog";
import SchedulingPolicyDialog from "./components/scheduling-policy-dialog";
import { scheduledHours } from "./components/scheduling-readiness";
import SchedulingReviewDialog from "./components/scheduling-review-dialog";
import type { SchedulingAnalyticsProps } from "./components/scheduling-types";
import BoardAgendaList from "./schedule-board/components/board-agenda-list";
import BoardDetailSheet, { type DetailSheetMode } from "./schedule-board/components/board-detail-sheet";
import BoardWeekGrid from "./schedule-board/components/board-week-grid";
import { buildBoardDirectories, type DirectoryCategory, type DirectoryEntity } from "./schedule-board/lib/board-entities";
import QuickAddSheet, { type QuickAddTarget } from "./schedule-canvas/components/quick-add-sheet";
import { useScheduleStudio } from "./scheduling/hooks/use-schedule-studio";

export default function ScheduleBoard(props: SchedulingAnalyticsProps) {
    const { user, term_context, filters, creation_options } = props;
    const studio = useScheduleStudio(props);

    const [category, setCategory] = useState<DirectoryCategory>("sections");
    const [selectedId, setSelectedId] = useState<string | null>(null);
    const [displayMode, setDisplayMode] = useState<"week" | "list">("week");
    const [searchQuery, setSearchQuery] = useState("");
    const [detailMode, setDetailMode] = useState<DetailSheetMode>(null);
    const [quickAddTarget, setQuickAddTarget] = useState<QuickAddTarget | null>(null);
    const [unscheduledSheetOpen, setUnscheduledSheetOpen] = useState(false);

    const directories = useMemo(
        () => buildBoardDirectories(studio.classes, creation_options.rooms, creation_options.faculty, studio.conflicts),
        [studio.classes, creation_options.rooms, creation_options.faculty, studio.conflicts],
    );

    const currentItems = directories[category] ?? [];

    const selectedEntity: DirectoryEntity | null = useMemo(() => {
        if (selectedId) {
            const match = currentItems.find((item) => item.id === selectedId);
            if (match) return match;
        }
        return currentItems.find((item) => item.sessions.length > 0) ?? currentItems[0] ?? null;
    }, [currentItems, selectedId]);

    const activeEntityIndex = Math.max(
        0,
        currentItems.findIndex((item) => item.id === selectedEntity?.id),
    );

    useEffect(() => {
        if (selectedEntity && selectedEntity.id !== selectedId) {
            setSelectedId(selectedEntity.id);
        }
    }, [selectedEntity, selectedId]);

    const visibleSessions = useMemo(() => {
        if (!selectedEntity) return [];
        const q = searchQuery.trim().toLowerCase();
        if (!q) return selectedEntity.sessions;
        return selectedEntity.sessions.filter(
            (item) =>
                item.classItem.subject_code.toLowerCase().includes(q) ||
                item.classItem.subject_title.toLowerCase().includes(q) ||
                (item.classItem.faculty_name ?? "").toLowerCase().includes(q) ||
                (item.session.room ?? "").toLowerCase().includes(q),
        );
    }, [selectedEntity, searchQuery]);

    const unscheduledClasses = useMemo(
        () =>
            studio.classes.filter(
                (cls) => scheduledHours(cls.schedules) < (cls.required_hours ?? 3) || cls.schedules.length === 0,
            ),
        [studio.classes],
    );

    const pickerClasses = useMemo(() => {
        if (selectedEntity && selectedEntity.classes.length > 0) {
            return selectedEntity.classes;
        }
        return studio.classes;
    }, [selectedEntity, studio.classes]);

    const handleAddAtSlot = (day?: string, start?: string, end?: string) => {
        setQuickAddTarget({
            day: day ?? studio.visibleDays[0] ?? "Monday",
            startTime: start ?? "08:00",
            endTime: end ?? "09:30",
            roomId: selectedEntity?.category === "rooms" ? selectedEntity.roomId ?? null : null,
        });
    };

    const termLabel = `${term_context?.semester_label ?? `Semester ${studio.semester}`} · SY ${studio.schoolYear}`;

    return (
        <AdminLayout user={user} title="Schedule Board" flush>
            <Head title="Schedule Board" />

            {/* Zero Internal Sidebar: Full-Width Board Workspace inside Admin Layout */}
            <div className="bg-background text-foreground flex h-full flex-col overflow-hidden antialiased">
                {/* 1. Compact Top Bar: Category Tabs + Search + Week/List + Sliding Sheet Triggers */}
                <header className="border-border/60 bg-background/95 flex flex-wrap items-center justify-between gap-2.5 border-b px-4 py-2.5 backdrop-blur-sm">
                    <nav
                        aria-label="Board perspective tabs"
                        className="bg-muted/65 border-border/50 inline-flex items-center gap-0.5 rounded-xl border p-1"
                    >
                        {(
                            [
                                { id: "sections", label: "Sections", icon: GraduationCap },
                                { id: "rooms", label: "Rooms", icon: Building2 },
                                { id: "teachers", label: "Teachers", icon: User },
                            ] as Array<{ id: DirectoryCategory; label: string; icon: typeof GraduationCap }>
                        ).map((tab) => {
                            const Icon = tab.icon;
                            const active = category === tab.id;
                            return (
                                <button
                                    key={tab.id}
                                    type="button"
                                    role="tab"
                                    aria-selected={active}
                                    onClick={() => {
                                        setCategory(tab.id);
                                        setSelectedId(null);
                                        setDetailMode(null);
                                    }}
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
                        {/* Inline Search */}
                        <div className="relative w-44 sm:w-52">
                            <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2" />
                            <Input
                                type="search"
                                value={searchQuery}
                                onChange={(e) => setSearchQuery(e.target.value)}
                                placeholder="Search board…"
                                aria-label="Search board schedule"
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

                        {/* Week | List Toggle */}
                        <div
                            role="tablist"
                            aria-label="Timetable layout"
                            className="bg-muted/70 inline-flex items-center rounded-lg p-0.5"
                        >
                            <button
                                type="button"
                                role="tab"
                                aria-selected={displayMode === "week"}
                                onClick={() => setDisplayMode("week")}
                                className={`inline-flex items-center gap-1 rounded-md px-2.5 py-1 text-xs font-medium transition-colors ${
                                    displayMode === "week"
                                        ? "bg-background text-foreground shadow-2xs"
                                        : "text-muted-foreground hover:text-foreground"
                                }`}
                            >
                                <CalendarDays className="size-3.5" />
                                <span>Week</span>
                            </button>
                            <button
                                type="button"
                                role="tab"
                                aria-selected={displayMode === "list"}
                                onClick={() => setDisplayMode("list")}
                                className={`inline-flex items-center gap-1 rounded-md px-2.5 py-1 text-xs font-medium transition-colors ${
                                    displayMode === "list"
                                        ? "bg-background text-foreground shadow-2xs"
                                        : "text-muted-foreground hover:text-foreground"
                                }`}
                            >
                                <List className="size-3.5" />
                                <span>List</span>
                            </button>
                        </div>

                        {unscheduledClasses.length > 0 && (
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                className="h-8 gap-1.5 rounded-lg px-2.5 text-xs tabular-nums"
                                onClick={() => setUnscheduledSheetOpen(true)}
                            >
                                <Clock className="size-3.5 text-amber-600" />
                                <span>Needs time ({unscheduledClasses.length})</span>
                            </Button>
                        )}

                        <Button
                            type="button"
                            size="sm"
                            className="h-8 gap-1 rounded-lg px-3 text-xs font-medium"
                            onClick={() => handleAddAtSlot()}
                        >
                            <Plus className="size-3.5" />
                            <span>Add time</span>
                        </Button>

                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    className="size-8 rounded-lg"
                                    aria-label="More schedule options"
                                >
                                    <MoreHorizontal className="size-4" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" className="w-60">
                                <DropdownMenuItem
                                    disabled={!studio.history.canUndo}
                                    onSelect={() => void studio.history.undo()}
                                    className="flex items-center gap-2"
                                >
                                    <Undo2 className="size-3.5" /> Undo last move (Ctrl+Z)
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    disabled={!studio.history.canRedo}
                                    onSelect={() => void studio.history.redo()}
                                    className="flex items-center gap-2"
                                >
                                    <Redo2 className="size-3.5" /> Redo (Ctrl+Shift+Z)
                                </DropdownMenuItem>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem
                                    onSelect={() => void studio.toggleSundayScheduling()}
                                    className="flex items-center justify-between gap-2"
                                >
                                    <span className="flex items-center gap-2">
                                        <Sun className="size-3.5" /> Sunday scheduling
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
                                <DropdownMenuSeparator />
                                <DropdownMenuItem asChild>
                                    <Link href={createClass.url()} className="flex items-center gap-2">
                                        <Plus className="size-3.5" /> Create new class
                                    </Link>
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
                                    <Download className="size-3.5" /> Export PDF timetable
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    onSelect={() => studio.setReviewOpen(true)}
                                    className="flex items-center gap-2"
                                >
                                    <Send className="size-3.5" /> Review &amp; publish ({studio.draftCount} draft)
                                </DropdownMenuItem>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem asChild>
                                    <Link href={mainScheduleIndex.url()} className="flex items-center gap-2">
                                        <CalendarDays className="size-3.5" /> Open Main Schedule
                                    </Link>
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                </header>

                {/* 2. Quiet Inline Entity Selector Bar (Replaces the Left Directory Sidebar!) */}
                <div className="border-border/50 bg-muted/20 flex min-h-11 shrink-0 flex-wrap items-center justify-between gap-2.5 border-b px-4 py-1.5">
                    <div className="flex flex-wrap items-center gap-1.5">
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            className="size-7 rounded-lg"
                            aria-label="Previous item"
                            disabled={currentItems.length <= 1}
                            onClick={() => {
                                const prev = (activeEntityIndex - 1 + currentItems.length) % currentItems.length;
                                setSelectedId(currentItems[prev].id);
                                setDetailMode(null);
                            }}
                        >
                            <ChevronLeft className="size-4" />
                        </Button>

                        <Select
                            value={selectedEntity?.id ?? ""}
                            onValueChange={(id) => {
                                setSelectedId(id);
                                setDetailMode(null);
                            }}
                        >
                            <SelectTrigger className="bg-background h-8 w-[280px] rounded-lg text-xs font-semibold">
                                <SelectValue placeholder="Choose a schedule" />
                            </SelectTrigger>
                            <SelectContent className="max-h-80">
                                {currentItems.map((item) => (
                                    <SelectItem key={item.id} value={item.id}>
                                        <span className="flex items-center gap-2">
                                            {item.conflictCount > 0 && (
                                                <span className="size-1.5 rounded-full bg-rose-500" />
                                            )}
                                            <span>{item.title}</span>
                                            {item.badge && (
                                                <span className="text-muted-foreground text-[11px]">{item.badge}</span>
                                            )}
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
                            aria-label="Next item"
                            disabled={currentItems.length <= 1}
                            onClick={() => {
                                const next = (activeEntityIndex + 1) % currentItems.length;
                                setSelectedId(currentItems[next].id);
                                setDetailMode(null);
                            }}
                        >
                            <ChevronRight className="size-4" />
                        </Button>

                        {selectedEntity && (
                            <span className="text-muted-foreground ml-1 hidden text-xs tabular-nums md:inline">
                                {selectedEntity.subtitle} · {termLabel}
                            </span>
                        )}
                    </div>

                    <div className="flex items-center gap-2">
                        {selectedEntity && selectedEntity.conflictCount === 0 && selectedEntity.sessions.length > 0 && (
                            <span className="inline-flex items-center gap-1 rounded-full bg-emerald-500/12 px-2.5 py-0.5 text-[11px] font-medium text-emerald-700 dark:text-emerald-300">
                                <CheckCircle2 className="size-3" /> Clean
                            </span>
                        )}

                        {selectedEntity && selectedEntity.conflictCount > 0 && (
                            <Button
                                type="button"
                                size="sm"
                                variant="destructive"
                                className="h-7 gap-1.5 rounded-full px-2.5 text-[11px] font-semibold"
                                onClick={() => setDetailMode({ kind: "fix-overlaps", index: 0 })}
                            >
                                <AlertTriangle className="size-3" />
                                <span className="tabular-nums">
                                    Fix {selectedEntity.conflictCount}{" "}
                                    {selectedEntity.conflictCount === 1 ? "overlap" : "overlaps"}
                                </span>
                            </Button>
                        )}
                    </div>
                </div>

                {/* 3. Full-Width Draggable Weekly Grid or Agenda List */}
                {!selectedEntity ? (
                    <div className="text-muted-foreground flex flex-1 flex-col items-center justify-center gap-2 p-8 text-center">
                        <p className="text-foreground text-sm font-medium">No schedules found for this term</p>
                        <p className="max-w-sm text-xs">
                            Generate classes from your curriculum or create a class to start building timetables.
                        </p>
                    </div>
                ) : displayMode === "week" ? (
                    <BoardWeekGrid
                        studio={studio}
                        days={studio.visibleDays}
                        sessions={visibleSessions}
                        category={category}
                        ghostPreview={studio.ghostPreview}
                        selectedSessionId={detailMode?.kind === "session" ? detailMode.sessionId : undefined}
                        onSelectSession={(item) => {
                            if (item.session.id !== undefined) {
                                setDetailMode({
                                    kind: "session",
                                    sessionId: item.session.id,
                                    classId: item.classItem.id,
                                });
                            }
                        }}
                        onClickEmptySlot={(day, start, end) => handleAddAtSlot(day, start, end)}
                        onSwitchToList={() => setDisplayMode("list")}
                    />
                ) : (
                    <BoardAgendaList
                        days={studio.visibleDays}
                        sessions={visibleSessions}
                        category={category}
                        onSelectSession={(item) => {
                            if (item.session.id !== undefined) {
                                setDetailMode({
                                    kind: "session",
                                    sessionId: item.session.id,
                                    classId: item.classItem.id,
                                });
                            }
                        }}
                        onAddOnDay={(day) => handleAddAtSlot(day, "08:00", "09:30")}
                    />
                )}
            </div>

            {/* SLIDING SHEET 1: Unified Detail, Inline Edit & Guided Overlap Fix Sheet */}
            <BoardDetailSheet
                mode={detailMode}
                onClose={() => setDetailMode(null)}
                onModeChange={setDetailMode}
                entity={selectedEntity}
                studio={studio}
                rooms={creation_options.rooms}
                days={studio.visibleDays}
            />

            {/* SLIDING SHEET 2: Quick Schedule / Add Session Sheet */}
            <QuickAddSheet
                target={quickAddTarget}
                classes={pickerClasses}
                rooms={creation_options.rooms}
                days={studio.visibleDays}
                onClose={() => setQuickAddTarget(null)}
                onCreate={studio.addClassScheduleBlocks}
            />

            {/* SLIDING SHEET 3: Classes Needing Time */}
            <Sheet open={unscheduledSheetOpen} onOpenChange={setUnscheduledSheetOpen}>
                <SheetContent side="right" className="w-full gap-0 p-0 sm:max-w-md">
                    <SheetHeader className="border-border/60 border-b px-5 py-4 pr-11 text-left">
                        <SheetTitle className="text-base font-semibold">Classes needing times</SheetTitle>
                        <SheetDescription className="text-xs">
                            Pick any class below to add its weekly schedule directly from the sheet.
                        </SheetDescription>
                    </SheetHeader>
                    <div className="flex-1 space-y-2 overflow-y-auto p-4">
                        {unscheduledClasses.map((cls) => (
                            <button
                                key={cls.id}
                                type="button"
                                onClick={() => {
                                    setUnscheduledSheetOpen(false);
                                    setQuickAddTarget({
                                        classId: cls.id,
                                        day: studio.visibleDays[0] ?? "Monday",
                                        startTime: "08:00",
                                        endTime: "09:30",
                                        roomId: cls.room_id ?? null,
                                    });
                                }}
                                className="hover:bg-muted/60 border-border/60 flex w-full items-center justify-between gap-2 rounded-xl border px-3.5 py-2.5 text-left transition-colors"
                            >
                                <div className="min-w-0">
                                    <p className="text-xs font-semibold">
                                        {cls.subject_code}{" "}
                                        <span className="text-muted-foreground font-normal">Sec {cls.section}</span>
                                    </p>
                                    <p className="text-muted-foreground mt-0.5 truncate text-[11px]">
                                        {cls.subject_title}
                                    </p>
                                </div>
                                <Badge variant="outline" className="shrink-0 text-[11px] tabular-nums">
                                    {Number(scheduledHours(cls.schedules).toFixed(1))}/{cls.required_hours ?? 3}h
                                </Badge>
                            </button>
                        ))}
                    </div>
                </SheetContent>
            </Sheet>

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
                onResolveConflicts={() => {
                    studio.setReviewOpen(false);
                    setDetailMode({ kind: "fix-overlaps", index: 0 });
                }}
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
