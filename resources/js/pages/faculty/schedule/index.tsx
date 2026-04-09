import { SubjectMatrix } from "@/components/dashboard/subject-matrix";
import { WeeklyScheduleDay, WeeklyTimetable } from "@/components/dashboard/weekly-timetable";
import FacultyLayout from "@/components/faculty/faculty-layout";
import { Card, CardContent } from "@/components/ui/card";

import { Tabs, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { User } from "@/types/user";
import { Head } from "@inertiajs/react";
import { IconCalendarEvent } from "@tabler/icons-react";
import { AnimatePresence, motion } from "framer-motion";
import { useEffect, useMemo, useState } from "react";
import { toast } from "sonner";

import { AgendaView } from "./components/agenda-view";
import { BirdsEyeView } from "./components/birds-eye-view";
import { ScheduleActions } from "./components/schedule-actions";
import { ScheduleHero } from "./components/schedule-hero";
import { ScheduleToolbar } from "./components/schedule-toolbar";
import {
    buildSnapshot,
    computeDiff,
    detectConflicts,
    normalizeCurrentEntries,
    parseTimeToMinutes,
    ScheduleBaselineSnapshot,
} from "./hooks/use-schedule-diff";
import { toFilteredSchedule, useScheduleFilter } from "./hooks/use-schedule-filter";

type ScheduleView = "overview" | "timetable" | "matrix" | "agenda";

interface SchedulePageProps {
    user: User;
    faculty_data: {
        weekly_schedule: WeeklyScheduleDay[];
    };
}

export default function SchedulePage({ user, faculty_data }: SchedulePageProps) {
    const baseSchedule = faculty_data.weekly_schedule ?? [];

    const baselineKey = useMemo(() => `dccp:scheduleBaseline:v1:${user.email}`, [user.email]);
    const viewKey = useMemo(() => `dccp:scheduleView:v1:${user.email}`, [user.email]);

    const [view, setView] = useState<ScheduleView>("overview");
    const [baseline, setBaseline] = useState<ScheduleBaselineSnapshot | null>(null);

    // Use the custom filter hook
    const { query, setQuery, day, setDay, showOnlyChanges, setShowOnlyChanges, dayOptions, resetFilters } = useScheduleFilter();

    useEffect(() => {
        try {
            const savedView = window.localStorage.getItem(viewKey);
            if (savedView === "overview" || savedView === "timetable" || savedView === "matrix" || savedView === "agenda") {
                setView(savedView);
                return;
            }

            const prefersMobileAgenda = window.matchMedia("(max-width: 640px)").matches;
            setView(prefersMobileAgenda ? "agenda" : "overview");
        } catch {
            // ignore
        }
    }, [viewKey]);

    useEffect(() => {
        try {
            window.localStorage.setItem(viewKey, view);
        } catch {
            // ignore
        }
    }, [view, viewKey]);

    useEffect(() => {
        try {
            const raw = window.localStorage.getItem(baselineKey);
            if (!raw) {
                setBaseline(null);
                return;
            }

            const parsed = JSON.parse(raw) as ScheduleBaselineSnapshot;
            if (parsed && Array.isArray(parsed.entries) && typeof parsed.savedAt === "string") {
                setBaseline(parsed);
            } else {
                setBaseline(null);
            }
        } catch {
            setBaseline(null);
        }
    }, [baselineKey]);

    const currentEntries = useMemo(() => normalizeCurrentEntries(baseSchedule), [baseSchedule]);
    const conflicts = useMemo(() => detectConflicts(currentEntries), [currentEntries]);
    const diff = useMemo(() => computeDiff(currentEntries, baseline), [currentEntries, baseline]);

    const filteredSchedule = useMemo(() => {
        return toFilteredSchedule(baseSchedule, query, day, diff.statusById, conflicts, showOnlyChanges && !!baseline);
    }, [baseSchedule, query, day, diff.statusById, conflicts, showOnlyChanges, baseline]);

    const today = useMemo(() => {
        return new Date().toLocaleDateString("en-US", { weekday: "long" });
    }, []);

    const todaySchedule = useMemo(() => {
        const dayEntry = baseSchedule.find((d) => d.day.toLowerCase() === today.toLowerCase());
        if (!dayEntry) return [];

        return (dayEntry.entries ?? [])
            .map((entry) => ({
                ...entry,
                start_minutes: parseTimeToMinutes(entry.start_time_24h ?? entry.start_time),
                end_minutes: parseTimeToMinutes(entry.end_time_24h ?? entry.end_time),
            }))
            .sort((a, b) => (a.start_minutes ?? 0) - (b.start_minutes ?? 0));
    }, [baseSchedule, today]);

    const nextClass = useMemo(() => {
        const now = new Date();
        const nowMinutes = now.getHours() * 60 + now.getMinutes();

        const upcoming = todaySchedule.find((entry) => {
            if (entry.start_minutes === null) return false;
            return entry.start_minutes > nowMinutes;
        });

        return upcoming ?? null;
    }, [todaySchedule]);

    const handleExport = async () => {
        const exportType = view === "matrix" ? "matrix" : "timetable";

        const toastId = toast.loading("Generating PDF...", {
            description: `Exporting your schedule in ${exportType} format. Please wait.`,
        });

        try {
            const response = await fetch(`/download/schedule?type=${exportType}`, {
                method: "GET",
            });

            if (!response.ok) {
                const data = await response.json();
                throw new Error(data.error || "Failed to generate PDF");
            }

            const blob = await response.blob();
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement("a");
            a.href = url;
            a.download = `schedule-${exportType}.pdf`;
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(url);
            document.body.removeChild(a);

            toast.success("PDF Exported", {
                id: toastId,
                description: "Your schedule has been successfully downloaded.",
            });
        } catch (error: unknown) {
            console.error("Export error:", error);
            const message = error instanceof Error ? error.message : "An unexpected error occurred.";
            toast.error("Export Failed", {
                id: toastId,
                description: message,
            });
        }
    };

    const handleSaveBaseline = () => {
        try {
            const snapshot = buildSnapshot(baseSchedule);
            window.localStorage.setItem(baselineKey, JSON.stringify(snapshot));
            setBaseline(snapshot);
            toast.success("Baseline saved", {
                description: "Changes will be highlighted from this snapshot.",
            });
        } catch (error) {
            console.error(error);
            toast.error("Could not save baseline");
        }
    };

    const handleClearBaseline = () => {
        try {
            window.localStorage.removeItem(baselineKey);
            setBaseline(null);
            setShowOnlyChanges(false);
            toast.success("Baseline cleared");
        } catch (error) {
            console.error(error);
            toast.error("Could not clear baseline");
        }
    };

    const hasChanges = diff.added.length + diff.changed.length + diff.removed.length > 0;

    return (
        <FacultyLayout
            user={{
                name: user.name,
                email: user.email,
                avatar: user.avatar,
                role: user.role,
            }}
        >
            <Head title="Schedule" />

            <main className="animate-in fade-in container mx-auto flex max-w-full flex-col gap-6 p-4 duration-500 sm:p-6 lg:p-8">
                {/* Header Section */}
                <div className="flex items-center justify-between gap-4">
                    <div className="space-y-1">
                        <h2 className="text-foreground text-2xl font-bold tracking-tight sm:text-3xl">Schedule</h2>
                        <div className="text-muted-foreground flex items-center gap-2 text-sm sm:text-base">
                            <IconCalendarEvent className="size-4 opacity-70" />
                            <span>Weekly overview • {today}</span>
                        </div>
                    </div>

                    <ScheduleActions handleExport={handleExport} handleClearBaseline={handleClearBaseline} baseline={parseBoolean(baseline)} />
                </div>

                <ScheduleHero schedule={filteredSchedule} nextClass={nextClass} />

                {/* Controls & Toolbar */}
                <ScheduleToolbar
                    query={query}
                    setQuery={setQuery}
                    day={day}
                    setDay={setDay}
                    dayOptions={dayOptions}
                    showOnlyChanges={showOnlyChanges}
                    setShowOnlyChanges={setShowOnlyChanges}
                    baseline={!!baseline}
                    hasChanges={hasChanges}
                    today={today}
                    handleSaveBaseline={handleSaveBaseline}
                    handleClearBaseline={handleClearBaseline}
                />

                {/* Main View Area - Full Width */}
                <div className="w-full">
                    <Card className="border-border/60 overflow-hidden shadow-sm">
                        <div className="bg-muted/10 border-b p-2 sm:p-4">
                            <Tabs value={view} onValueChange={(v) => setView(v as ScheduleView)} className="w-full">
                                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                                    <TabsList className="grid h-9 w-full grid-cols-4 sm:w-auto">
                                        <TabsTrigger value="overview" className="text-xs sm:text-sm">
                                            Overview
                                        </TabsTrigger>
                                        <TabsTrigger value="timetable" className="text-xs sm:text-sm">
                                            Timetable
                                        </TabsTrigger>
                                        <TabsTrigger value="matrix" className="text-xs sm:text-sm">
                                            Matrix
                                        </TabsTrigger>
                                        <TabsTrigger value="agenda" className="text-xs sm:text-sm">
                                            Agenda
                                        </TabsTrigger>
                                    </TabsList>
                                    <div className="text-muted-foreground hidden text-xs lg:block">Tip: Use "Agenda" for a daily mobile feed.</div>
                                </div>
                            </Tabs>
                        </div>
                        <CardContent className="p-0">
                            <div className="bg-background/50 min-h-[500px] p-2 sm:p-6">
                                <AnimatePresence mode="wait">
                                    {view === "overview" && (
                                        <motion.div
                                            key="overview"
                                            initial={{ opacity: 0, y: 5 }}
                                            animate={{ opacity: 1, y: 0 }}
                                            exit={{ opacity: 0, y: -5 }}
                                            transition={{ duration: 0.15 }}
                                        >
                                            <BirdsEyeView
                                                schedule={filteredSchedule}
                                                statusById={diff.statusById}
                                                conflicts={conflicts}
                                                highlightDay={today}
                                                baselineEnabled={!!baseline}
                                            />
                                        </motion.div>
                                    )}

                                    {view === "timetable" && (
                                        <motion.div
                                            key="timetable"
                                            initial={{ opacity: 0, x: -5 }}
                                            animate={{ opacity: 1, x: 0 }}
                                            exit={{ opacity: 0, x: 5 }}
                                            transition={{ duration: 0.15 }}
                                        >
                                            <WeeklyTimetable schedule={filteredSchedule} />
                                        </motion.div>
                                    )}

                                    {view === "matrix" && (
                                        <motion.div
                                            key="matrix"
                                            initial={{ opacity: 0, x: 5 }}
                                            animate={{ opacity: 1, x: 0 }}
                                            exit={{ opacity: 0, x: -5 }}
                                            transition={{ duration: 0.15 }}
                                        >
                                            <SubjectMatrix schedule={filteredSchedule} />
                                        </motion.div>
                                    )}

                                    {view === "agenda" && (
                                        <motion.div
                                            key="agenda"
                                            initial={{ opacity: 0, y: 5 }}
                                            animate={{ opacity: 1, y: 0 }}
                                            exit={{ opacity: 0, y: -5 }}
                                            transition={{ duration: 0.15 }}
                                        >
                                            <AgendaView schedule={filteredSchedule} statusById={diff.statusById} conflicts={conflicts} />
                                        </motion.div>
                                    )}
                                </AnimatePresence>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </main>
        </FacultyLayout>
    );
}

function parseBoolean(val: any): boolean {
    return !!val;
}
