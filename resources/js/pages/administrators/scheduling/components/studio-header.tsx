import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from "@/components/ui/tooltip";
import { create as createClass, index as classesIndex } from "@/routes/administrators/classes";
import { index as scheduleBoardIndex } from "@/routes/administrators/schedule-board";
import { Link } from "@inertiajs/react";
import {
    AlertTriangle,
    CalendarDays,
    CalendarPlus,
    Download,
    Flame,
    Layers,
    MoreHorizontal,
    Palette,
    PanelLeft,
    Plus,
    Redo2,
    Send,
    SlidersHorizontal,
    Sparkles,
    Sun,
    Undo2,
    ZoomIn,
} from "lucide-react";
import type { SchedulingAnalyticsProps } from "../../components/scheduling-types";
import type { ScheduleStudioController, StudioLens } from "../hooks/use-schedule-studio";
import type { ColorScheme } from "../lib/schedule-colors";
import type { GridDensity } from "../lib/time-geometry";

type StudioHeaderProps = {
    studio: ScheduleStudioController;
    termContext?: SchedulingAnalyticsProps["term_context"];
};

const LENS_OPTIONS: Array<{ value: StudioLens; label: string; icon: typeof CalendarDays }> = [
    { value: "week", label: "Week Canvas", icon: CalendarDays },
    { value: "matrix", label: "Resource Matrix", icon: Layers },
    { value: "heatmap", label: "Heatmap", icon: Flame },
];

const DENSITY_OPTIONS: Array<{ value: GridDensity; label: string; hint: string }> = [
    { value: "birdseye", label: "Bird's-Eye", hint: "Full day visible at once" },
    { value: "standard", label: "Standard", hint: "Balanced room & faculty labels" },
    { value: "expanded", label: "Expanded", hint: "Detailed 15-min precision" },
];

const COLOR_OPTIONS: Array<{ value: ColorScheme; label: string }> = [
    { value: "subject", label: "Subject & Section" },
    { value: "delivery", label: "Delivery Mode" },
    { value: "status", label: "Draft vs Published" },
    { value: "health", label: "Room & Capacity Health" },
];

export default function StudioHeader({ studio, termContext }: StudioHeaderProps) {
    const years = termContext?.available_school_years ?? [studio.schoolYear];
    const semesters = termContext?.available_semesters ?? [
        { value: "1", label: "1st Semester" },
        { value: "2", label: "2nd Semester" },
        { value: "summer", label: "Summer" },
    ];

    return (
        <TooltipProvider delay={200}>
            <header className="bg-card/80 border-border/70 sticky top-0 z-20 rounded-xl border px-3.5 py-3 shadow-xs backdrop-blur-md">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    {/* Left: Rail toggle + Title + Term selector */}
                    <div className="flex flex-wrap items-center gap-2.5">
                        <Tooltip>
                            <TooltipTrigger
                                render={
                                    <Button
                                        type="button"
                                        variant={studio.railOpen ? "secondary" : "outline"}
                                        size="icon"
                                        className="size-8 shrink-0 active:scale-[0.96]"
                                        onClick={() => studio.setRailOpen((open) => !open)}
                                        aria-label={studio.railOpen ? "Hide class rail" : "Show class rail"}
                                    >
                                        <PanelLeft className="size-4" aria-hidden="true" />
                                    </Button>
                                }
                            />
                            <TooltipContent>{studio.railOpen ? "Hide class rail" : "Show class rail"}</TooltipContent>
                        </Tooltip>

                        <div className="min-w-0">
                            <div className="flex items-center gap-2">
                                <h1 className="text-base font-semibold tracking-tight text-balance sm:text-lg">Schedule Studio</h1>
                                {studio.draftCount > 0 && (
                                    <Badge variant="outline" className="border-amber-500/40 bg-amber-500/10 text-[11px] text-amber-700 tabular-nums dark:text-amber-300">
                                        {studio.draftCount} draft
                                    </Badge>
                                )}
                            </div>
                        </div>

                        <div className="bg-border/60 hidden h-5 w-px sm:block" aria-hidden="true" />

                        <div className="flex items-center gap-1.5">
                            <Select value={studio.schoolYear} onValueChange={(value) => studio.changeTerm(value, studio.semester)}>
                                <SelectTrigger className="h-8 w-[148px] text-xs font-medium tabular-nums" aria-label="School year">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {years.map((year) => (
                                        <SelectItem key={year} value={year} className="tabular-nums">
                                            SY {year}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <Select value={studio.semester} onValueChange={(value) => studio.changeTerm(studio.schoolYear, value)}>
                                <SelectTrigger className="h-8 w-[132px] text-xs font-medium" aria-label="Semester">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {semesters.map((sem) => (
                                        <SelectItem key={sem.value} value={sem.value}>
                                            {sem.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    {/* Center: Bird's-Eye Lens Switcher */}
                    <div
                        role="tablist"
                        aria-label="Schedule views"
                        className="bg-muted/70 border-border/60 inline-flex items-center rounded-lg border p-0.5"
                    >
                        {LENS_OPTIONS.map((option) => {
                            const Icon = option.icon;
                            const active = studio.activeLens === option.value;
                            return (
                                <button
                                    key={option.value}
                                    type="button"
                                    role="tab"
                                    aria-selected={active}
                                    onClick={() => studio.setActiveLens(option.value)}
                                    className={`inline-flex items-center gap-1.5 rounded-md px-2.5 py-1 text-xs font-medium transition-colors active:scale-[0.98] ${
                                        active
                                            ? "bg-background text-foreground shadow-xs"
                                            : "text-muted-foreground hover:text-foreground"
                                    }`}
                                >
                                    <Icon className="size-3.5" aria-hidden="true" />
                                    <span>{option.label}</span>
                                </button>
                            );
                        })}
                    </div>

                    {/* Right: Controls & Primary Actions */}
                    <div className="flex flex-wrap items-center gap-1.5">
                        {/* Undo / Redo */}
                        <div className="bg-muted/50 border-border/60 inline-flex items-center rounded-lg border p-0.5">
                            <Tooltip>
                                <TooltipTrigger
                                    render={
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            className="size-7 active:scale-[0.95]"
                                            disabled={!studio.history.canUndo}
                                            onClick={() => void studio.history.undo()}
                                            aria-label="Undo schedule change"
                                        >
                                            <Undo2 className="size-3.5" aria-hidden="true" />
                                        </Button>
                                    }
                                />
                                <TooltipContent>
                                    {studio.history.undoLabel ? `Undo (${studio.history.undoLabel}) · Ctrl+Z` : "Undo · Ctrl+Z"}
                                </TooltipContent>
                            </Tooltip>
                            <Tooltip>
                                <TooltipTrigger
                                    render={
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            className="size-7 active:scale-[0.95]"
                                            disabled={!studio.history.canRedo}
                                            onClick={() => void studio.history.redo()}
                                            aria-label="Redo schedule change"
                                        >
                                            <Redo2 className="size-3.5" aria-hidden="true" />
                                        </Button>
                                    }
                                />
                                <TooltipContent>
                                    {studio.history.redoLabel ? `Redo (${studio.history.redoLabel}) · Ctrl+Shift+Z` : "Redo · Ctrl+Shift+Z"}
                                </TooltipContent>
                            </Tooltip>
                        </div>

                        {/* Sunday Quick Toggle */}
                        <Tooltip>
                            <TooltipTrigger
                                render={
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant={studio.policy.allow_sunday_scheduling ? "secondary" : "outline"}
                                        disabled={studio.togglingSunday}
                                        onClick={() => void studio.toggleSundayScheduling()}
                                        className={`h-8 gap-1.5 px-2.5 text-xs active:scale-[0.97] ${
                                            studio.policy.allow_sunday_scheduling
                                                ? "border-amber-500/35 bg-amber-500/10 text-amber-800 hover:bg-amber-500/15 dark:text-amber-300"
                                                : ""
                                        }`}
                                    >
                                        <Sun className="size-3.5" aria-hidden="true" />
                                        <span className="hidden xl:inline">Sunday</span>
                                        <span className="text-[10px] font-semibold uppercase">
                                            {studio.policy.allow_sunday_scheduling ? "On" : "Off"}
                                        </span>
                                    </Button>
                                }
                            />
                            <TooltipContent>
                                {studio.policy.allow_sunday_scheduling
                                    ? "Sunday scheduling is enabled (click to switch to Mon–Sat)"
                                    : "Enable Sunday scheduling (7-day week)"}
                            </TooltipContent>
                        </Tooltip>

                        {/* Density & Color-by controls (relevant for Week & Matrix) */}
                        {studio.activeLens !== "heatmap" && (
                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <Button type="button" variant="outline" size="sm" className="h-8 gap-1.5 px-2.5 text-xs">
                                        <ZoomIn className="size-3.5" aria-hidden="true" />
                                        <span className="hidden lg:inline">
                                            {DENSITY_OPTIONS.find((d) => d.value === studio.density)?.label}
                                        </span>
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end" className="w-56">
                                    <DropdownMenuLabel className="text-xs">Grid Density</DropdownMenuLabel>
                                    {DENSITY_OPTIONS.map((opt) => (
                                        <DropdownMenuItem
                                            key={opt.value}
                                            onSelect={() => studio.setDensity(opt.value)}
                                            className="flex flex-col items-start gap-0.5"
                                        >
                                            <span className={studio.density === opt.value ? "text-primary font-semibold" : "font-medium"}>
                                                {opt.label}
                                            </span>
                                            <span className="text-muted-foreground text-[11px]">{opt.hint}</span>
                                        </DropdownMenuItem>
                                    ))}
                                    <DropdownMenuSeparator />
                                    <DropdownMenuLabel className="flex items-center gap-1.5 text-xs">
                                        <Palette className="size-3" /> Color Blocks By
                                    </DropdownMenuLabel>
                                    {COLOR_OPTIONS.map((opt) => (
                                        <DropdownMenuItem key={opt.value} onSelect={() => studio.setColorScheme(opt.value)}>
                                            <span className={studio.colorScheme === opt.value ? "text-primary font-semibold" : ""}>
                                                {opt.label}
                                            </span>
                                        </DropdownMenuItem>
                                    ))}
                                </DropdownMenuContent>
                            </DropdownMenu>
                        )}

                        {/* Conflict Workbench Toggle */}
                        <Button
                            type="button"
                            size="sm"
                            variant={studio.conflicts.length > 0 ? "destructive" : "outline"}
                            className={`h-8 gap-1.5 px-2.5 text-xs active:scale-[0.97] ${
                                studio.workbenchOpen && studio.conflicts.length === 0 ? "bg-secondary" : ""
                            }`}
                            onClick={() => studio.setWorkbenchOpen((open) => !open)}
                        >
                            <AlertTriangle className="size-3.5" aria-hidden="true" />
                            <span className="tabular-nums">
                                {studio.conflicts.length > 0 ? `${studio.conflicts.length} Conflicts` : "0 Conflicts"}
                            </span>
                        </Button>

                        {/* Review & Publish */}
                        <Button
                            type="button"
                            size="sm"
                            className="h-8 gap-1.5 px-3 text-xs active:scale-[0.97]"
                            onClick={() => studio.setReviewOpen(true)}
                        >
                            <Send className="size-3.5" aria-hidden="true" />
                            <span>{studio.draftCount > 0 ? `Publish (${studio.draftCount})` : "Review"}</span>
                        </Button>

                        {/* More Actions */}
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button type="button" variant="outline" size="icon" className="size-8" aria-label="More schedule actions">
                                    <MoreHorizontal className="size-4" aria-hidden="true" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" className="w-56">
                                <DropdownMenuItem asChild>
                                    <Link href={createClass.url()} className="flex items-center gap-2">
                                        <Plus className="size-3.5" /> Add new class
                                    </Link>
                                </DropdownMenuItem>
                                <DropdownMenuItem onSelect={() => studio.setGenerateOpen(true)} className="flex items-center gap-2">
                                    <Sparkles className="size-3.5" /> Generate classes from curriculum
                                </DropdownMenuItem>
                                <DropdownMenuItem onSelect={() => studio.setExportOpen(true)} className="flex items-center gap-2">
                                    <Download className="size-3.5" /> Export PDF timetable
                                </DropdownMenuItem>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem onSelect={() => studio.setPolicyOpen(true)} className="flex items-center gap-2">
                                    <SlidersHorizontal className="size-3.5" /> Conflict &amp; delivery rules
                                </DropdownMenuItem>
                                <DropdownMenuItem asChild>
                                    <Link href={scheduleBoardIndex.url()} className="flex items-center gap-2">
                                        <CalendarDays className="size-3.5" /> Switch to Schedule Board (Simple View)
                                    </Link>
                                </DropdownMenuItem>
                                <DropdownMenuItem asChild>
                                    <Link href={classesIndex.url()} className="flex items-center gap-2">
                                        <CalendarPlus className="size-3.5" /> Open Class Management
                                    </Link>
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                </div>
            </header>
        </TooltipProvider>
    );
}
