import StudentLayout from "@/components/student/student-layout";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { Tabs, TabsContent } from "@/components/ui/tabs";
import { User as UserType } from "@/types/user";
import { Head, router } from "@inertiajs/react";
import { IconCalendarStats, IconClockHour4, IconSchool, IconStack2 } from "@tabler/icons-react";
import { motion } from "framer-motion";
import { CalendarDays, Clock } from "lucide-react";
import { useEffect, useMemo, useState } from "react";

import { ClassCards } from "@/components/class-cards";
import { StudentScheduleBoard } from "@/components/class/student-schedule-board";
import { ClassData, DataTable } from "@/components/data-table";
import { ClassesStatus } from "@/pages/classes/components/classes-status";
import { ClassesToolbar } from "@/pages/classes/components/classes-toolbar";
import { useClassFilters } from "@/pages/classes/hooks/use-class-filters";
import { formatClassSchedule, useClassSchedule } from "@/pages/classes/hooks/use-class-schedule";

interface StudentScheduleProps {
    user: UserType;
    faculty_data: {
        classes: ClassData[];
        stats: any[];
    };
    rooms: { id: number; name: string }[];
}

const dashboardCardClass =
    "border-border/60 bg-card/75 rounded-lg shadow-sm transition-all duration-200 hover:border-primary/30 hover:bg-card hover:shadow-md";
const dashboardPanelClass = "border-border/60 bg-card/75 rounded-lg shadow-sm";

function ScheduleStatCard({ label, value, detail, icon: Icon }: { label: string; value: string | number; detail: string; icon: typeof IconSchool }) {
    return (
        <Card className={`${dashboardCardClass} group relative overflow-hidden hover:-translate-y-0.5`}>
            <Icon className="text-primary pointer-events-none absolute top-4 right-5 size-12 opacity-12 transition-all duration-200 group-hover:scale-105 group-hover:opacity-20" />
            <CardContent className="relative p-4 pr-16">
                <p className="text-muted-foreground text-[10px] font-semibold tracking-wide uppercase">{label}</p>
                <p className="text-foreground mt-2 text-2xl font-semibold tracking-tight">{value}</p>
                <p className="text-muted-foreground mt-1 text-xs">{detail}</p>
            </CardContent>
        </Card>
    );
}

export default function StudentSchedule({ user, faculty_data, rooms }: StudentScheduleProps) {
    const [viewMode, setViewMode] = useState<"board" | "gallery" | "list">("board");

    useEffect(() => {
        const savedMode = localStorage.getItem("dccp.student.schedule.viewMode");
        if (savedMode === "gallery" || savedMode === "list" || savedMode === "board") {
            setViewMode(savedMode as any);
        }
    }, []);

    function handleSetViewMode(mode: "board" | "gallery" | "list") {
        setViewMode(mode);
        localStorage.setItem("dccp.student.schedule.viewMode", mode);
    }

    // --- Logic Hooks for Classes ---
    const processedClasses = useMemo(() => {
        return (faculty_data?.classes ?? []).map((c) => {
            const calculated = formatClassSchedule(c.schedules ?? []);
            return {
                ...c,
                schedule: calculated || c.schedule || "TBA",
            };
        });
    }, [faculty_data?.classes]);

    const { events, unscheduled, conflictIds, nextUp } = useClassSchedule(processedClasses, rooms);

    const {
        search,
        setSearch,
        filterClassification,
        setFilterClassification,
        filterDay,
        setFilterDay,
        filterRoom,
        setFilterRoom,
        effectiveClasses,
        resetFilters,
    } = useClassFilters(processedClasses, events, conflictIds, unscheduled);

    // Stats for the class view
    const stats = useMemo(() => {
        const scheduledCount = new Set(events.map((event) => String(event.classItem.id))).size;
        const unscheduledCount = unscheduled.length;
        const conflictCount = conflictIds.size;

        return {
            scheduledCount,
            unscheduledCount,
            conflictCount,
            nearCapacityCount: 0,
        };
    }, [events, unscheduled, conflictIds]);

    return (
        <StudentLayout
            user={{
                name: user.name,
                email: user.email,
                avatar: user.avatar,
                role: user.role,
            }}
        >
            <Head title="Class Schedule" />

            <div className="mx-auto flex w-full max-w-7xl flex-col gap-5 p-4 pb-16 md:gap-6 md:p-6">
                {/* Header Section */}
                <Card className={dashboardPanelClass}>
                    <CardContent className="flex flex-col justify-between gap-5 p-4 md:flex-row md:items-end md:p-5">
                        <div className="space-y-2">
                            <div className="text-primary flex items-center gap-2 font-medium">
                                <CalendarDays className="h-4 w-4" />
                                <span className="text-muted-foreground text-[10px] font-semibold tracking-wide uppercase">Weekly Plan</span>
                            </div>
                            <div>
                                <h1 className="text-foreground text-2xl font-semibold tracking-tight md:text-3xl">Class Schedule</h1>
                                <p className="text-muted-foreground mt-1 flex max-w-xl items-center gap-2 text-sm">
                                    <Clock className="h-4 w-4 shrink-0" />
                                    Manage your enrolled class schedule for the selected academic period.
                                </p>
                            </div>
                        </div>
                        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 md:w-[28rem]">
                            <ScheduleStatCard label="Scheduled" value={stats.scheduledCount} detail="with time blocks" icon={IconCalendarStats} />
                            <ScheduleStatCard label="Unscheduled" value={stats.unscheduledCount} detail="awaiting schedule" icon={IconStack2} />
                            <ScheduleStatCard label="Conflicts" value={stats.conflictCount} detail="overlapping blocks" icon={IconClockHour4} />
                        </div>
                    </CardContent>
                </Card>

                <motion.div initial={{ opacity: 0, y: 10 }} animate={{ opacity: 1, y: 0 }} exit={{ opacity: 0, y: -10 }}>
                    {/* Status Section */}
                    {nextUp && (
                        <div className="mb-6">
                            <ClassesStatus nextUp={nextUp} stats={stats} baseUrl="/student/classes" />
                        </div>
                    )}

                    <Card className={dashboardPanelClass}>
                        <CardContent className="p-3 md:p-4">
                            <ClassesToolbar
                                viewMode={viewMode}
                                setViewMode={handleSetViewMode}
                                search={search}
                                setSearch={setSearch}
                                filterClassification={filterClassification}
                                setFilterClassification={setFilterClassification}
                                filterRoom={filterRoom}
                                setFilterRoom={setFilterRoom}
                                filterDay={filterDay}
                                setFilterDay={setFilterDay}
                                rooms={rooms}
                                resetFilters={resetFilters}
                                hasActiveFilters={search !== "" || filterClassification !== "all" || filterRoom !== "all" || filterDay !== "all"}
                            />
                        </CardContent>
                    </Card>

                    <div className="mt-4">
                        <Tabs value={viewMode} onValueChange={(v) => handleSetViewMode(v as any)} className="w-full">
                            <TabsContent value="board" className="mt-0 focus-visible:ring-0">
                                <StudentScheduleBoard events={events} classes={effectiveClasses} filterDay={filterDay} />
                            </TabsContent>

                            <TabsContent value="gallery" className="mt-0 focus-visible:ring-0">
                                <ClassCards data={effectiveClasses} onEdit={(item) => router.visit(`/student/classes/${item.id}`)} />
                            </TabsContent>

                            <TabsContent value="list" className="mt-0 focus-visible:ring-0">
                                <DataTable data={effectiveClasses} onEdit={(item) => router.visit(`/student/classes/${item.id}`)} />
                            </TabsContent>
                        </Tabs>

                        {effectiveClasses.length === 0 && (
                            <Card className={`${dashboardPanelClass} mt-4 overflow-hidden`}>
                                <CardContent className="relative flex min-h-[320px] flex-col items-center justify-center p-8 text-center">
                                    <IconSchool className="text-primary pointer-events-none absolute top-8 right-8 size-24 opacity-10" />
                                    <div className="bg-primary/10 text-primary mb-4 rounded-lg p-4">
                                        <IconSchool className="h-8 w-8" />
                                    </div>
                                    <p className="text-foreground font-semibold">No active classes found</p>
                                    <p className="text-muted-foreground mt-1 max-w-sm text-sm">
                                        You are not enrolled in any classes matching the current filters.
                                    </p>
                                    <Button variant="outline" onClick={resetFilters} className="mt-4 rounded-lg">
                                        Clear filters
                                    </Button>
                                </CardContent>
                            </Card>
                        )}
                    </div>
                </motion.div>
            </div>
        </StudentLayout>
    );
}
