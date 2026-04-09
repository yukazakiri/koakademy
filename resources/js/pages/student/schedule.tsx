import StudentLayout from "@/components/student/student-layout";
import { Button } from "@/components/ui/button";
import { Tabs, TabsContent } from "@/components/ui/tabs";
import { User as UserType } from "@/types/user";
import { Head, router } from "@inertiajs/react";
import { IconSchool } from "@tabler/icons-react";
import { motion } from "framer-motion";
import { Clock } from "lucide-react";
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

            <div className="mx-auto w-full max-w-7xl space-y-6 p-4 md:p-6">
                {/* Header Section */}
                <div className="space-y-4">
                    <div className="flex flex-col items-start justify-between gap-4 md:flex-row md:items-center">
                        <div>
                            <h1 className="text-2xl font-bold tracking-tight md:text-3xl">Class Schedule</h1>
                            <p className="text-muted-foreground mt-1 flex items-center gap-2 text-sm md:text-base">
                                <Clock className="h-4 w-4" />
                                Manage your weekly schedule
                            </p>
                        </div>
                    </div>
                </div>

                <motion.div initial={{ opacity: 0, y: 10 }} animate={{ opacity: 1, y: 0 }} exit={{ opacity: 0, y: -10 }}>
                    {/* Status Section */}
                    {nextUp && (
                        <div className="mb-6">
                            <ClassesStatus nextUp={nextUp} stats={stats} baseUrl="/student/classes" />
                        </div>
                    )}

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
                            <div className="text-muted-foreground bg-muted/20 mt-4 flex flex-col items-center justify-center rounded-lg border border-dashed py-16 text-center">
                                <div className="bg-muted mb-3 rounded-full p-3">
                                    <IconSchool className="h-6 w-6" />
                                </div>
                                <p className="font-medium">No active classes found</p>
                                <p className="mt-1 text-sm">You are not enrolled in any classes matching the filters.</p>
                                <Button variant="link" onClick={resetFilters} className="mt-2">
                                    Clear filters
                                </Button>
                            </div>
                        )}
                    </div>
                </motion.div>
            </div>
        </StudentLayout>
    );
}
