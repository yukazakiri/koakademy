import { Head, router } from "@inertiajs/react";
import { IconPlus, IconSchool } from "@tabler/icons-react";
import { useEffect, useMemo, useState } from "react";

import { ClassCards } from "@/components/class-cards";
import { ClassData, DataTable } from "@/components/data-table";
import FacultyLayout from "@/components/faculty/faculty-layout";
import { Stat } from "@/components/section-cards";
import { Button } from "@/components/ui/button";
import { Tabs, TabsContent } from "@/components/ui/tabs";
import { User } from "@/types/user";

import { ClassesStatus } from "@/pages/classes/components/classes-status";
import { ClassesToolbar } from "@/pages/classes/components/classes-toolbar";
import { CreateClassDialog } from "@/pages/classes/components/create-class-dialog";
import { EditClassDialog } from "@/pages/classes/components/edit-class-dialog";
import { useClassFilters } from "@/pages/classes/hooks/use-class-filters";
import { useClassSchedule } from "@/pages/classes/hooks/use-class-schedule";

import { ClassBoard } from "@/pages/classes/components/class-board";

interface ClassesProps {
    user: User;
    faculty_data: {
        stats: Stat[];
        classes: ClassData[];
    };
    shs_strands: {
        id: string;
        strand_name: string;
        description: string | null;
        track_name: string | null;
        track_id: number;
    }[];
    rooms: {
        id: number;
        name: string;
    }[];
    current_semester: string;
    current_school_year: string;
    current_faculty: {
        id: string;
        name: string;
    } | null;
}

export default function Classes({ user, faculty_data, shs_strands, rooms, current_semester, current_school_year, current_faculty }: ClassesProps) {
    const [viewMode, setViewMode] = useState<"board" | "gallery" | "list">("gallery");

    useEffect(() => {
        const savedMode = localStorage.getItem("dccp.classes.viewMode");
        if (savedMode === "gallery" || savedMode === "list" || savedMode === "board") {
            setViewMode(savedMode as any);
        }
    }, []);

    function handleSetViewMode(mode: "board" | "gallery" | "list") {
        setViewMode(mode);
        localStorage.setItem("dccp.classes.viewMode", mode);
    }

    // --- Logic Hooks ---
    const { roomsById, events, unscheduled, conflictIds, nextUp } = useClassSchedule(faculty_data?.classes ?? [], rooms);

    const {
        search,
        setSearch,
        filterClassification,
        setFilterClassification,
        filterDay,
        setFilterDay,
        filterRoom,
        setFilterRoom,
        onlyConflicts,
        setOnlyConflicts,
        onlyUnscheduled,
        setOnlyUnscheduled,
        effectiveClasses,
        resetFilters,
    } = useClassFilters(faculty_data?.classes ?? [], events, conflictIds, unscheduled);

    // --- State for Dialogs ---
    const [createDialogOpen, setCreateDialogOpen] = useState(false);
    const [isEditOpen, setIsEditOpen] = useState(false);
    const [editingClass, setEditingClass] = useState<ClassData | null>(null);

    useEffect(() => {
        if (typeof window !== "undefined") {
            const params = new URLSearchParams(window.location.search);
            if (params.get("create") === "1") {
                setCreateDialogOpen(true);
                router.visit("/classes", { replace: true, preserveScroll: true, preserveState: true });
            }
        }
    }, []);

    function handleEditClick(classItem: ClassData) {
        setEditingClass(classItem);
        setIsEditOpen(true);
    }

    // --- Stats Calculation for Overview ---
    const stats = useMemo(() => {
        const scheduledCount = new Set(events.map((event) => String(event.classItem.id))).size;
        const unscheduledCount = unscheduled.length;
        const conflictCount = conflictIds.size;
        const nearCapacityCount = (faculty_data?.classes ?? []).filter((item) => {
            const setMax = Number(item.maximum_slots) || 0;
            const current = Number(item.students_count) || 0;
            return setMax > 0 && current / setMax >= 0.85;
        }).length;

        return { scheduledCount, unscheduledCount, conflictCount, nearCapacityCount };
    }, [events, unscheduled, conflictIds, faculty_data?.classes]);

    // --- Pinned Classes Logic ---
    const [pinnedIds, setPinnedIds] = useState<string[]>(() => {
        if (typeof window === "undefined") return [];
        try {
            return JSON.parse(window.localStorage.getItem("dccp.classes.pinnedIds") || "[]");
        } catch {
            return [];
        }
    });

    function togglePinned(classId: string) {
        setPinnedIds((prev) => {
            const next = prev.includes(classId) ? prev.filter((id) => id !== classId) : [...prev, classId];
            localStorage.setItem("dccp.classes.pinnedIds", JSON.stringify(next));
            return next;
        });
    }

    const pinnedSet = useMemo(() => new Set(pinnedIds), [pinnedIds]);

    const pageSubtitle = `${current_semester === "summer" ? "Summer" : `Semester ${current_semester}`} • ${current_school_year}`;

    return (
        <FacultyLayout
            user={{
                name: user.name,
                email: user.email,
                avatar: user.avatar,
                role: user.role,
            }}
        >
            <Head title="Classes" />
            <main className="mx-auto w-full max-w-7xl space-y-6 p-4 sm:p-6 lg:p-8">
                {/* Header Section */}
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                    <div>
                        <h2 className="text-2xl font-bold tracking-tight">Your Classes</h2>
                        <p className="text-muted-foreground text-sm">{pageSubtitle}</p>
                    </div>
                    <div className="flex gap-2">
                        <Button onClick={() => setCreateDialogOpen(true)} className="w-full shadow-sm sm:w-auto">
                            <IconPlus className="mr-2 h-4 w-4" /> New Class
                        </Button>
                    </div>
                </div>

                {/* Status Section */}
                <ClassesStatus nextUp={nextUp} stats={stats} />

                {/* Filters and Content */}
                <div className="space-y-4">
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

                    <div className="animate-in fade-in slide-in-from-bottom-2 duration-500">
                        <Tabs value={viewMode} onValueChange={(v) => handleSetViewMode(v as any)} className="w-full">
                            <TabsContent value="board" className="mt-0 focus-visible:ring-0">
                                <ClassBoard
                                    events={events}
                                    classes={effectiveClasses}
                                    conflictIds={conflictIds}
                                    pinnedIds={pinnedSet}
                                    onTogglePinned={togglePinned}
                                    onEdit={handleEditClick}
                                    filterDay={filterDay}
                                />
                            </TabsContent>

                            <TabsContent value="gallery" className="mt-0 focus-visible:ring-0">
                                <ClassCards data={effectiveClasses} onEdit={handleEditClick} />
                            </TabsContent>

                            <TabsContent value="list" className="mt-0 focus-visible:ring-0">
                                <DataTable data={effectiveClasses} onEdit={handleEditClick} />
                            </TabsContent>
                        </Tabs>

                        {effectiveClasses.length === 0 && (
                            <div className="text-muted-foreground bg-muted/20 mt-4 flex flex-col items-center justify-center rounded-lg border border-dashed py-16 text-center">
                                <div className="bg-muted mb-3 rounded-full p-3">
                                    <IconSchool className="h-6 w-6" />
                                </div>
                                <p className="font-medium">No classes found</p>
                                <p className="mt-1 text-sm">Try adjusting your filters or search query.</p>
                                <Button variant="link" onClick={resetFilters} className="mt-2">
                                    Clear filters
                                </Button>
                            </div>
                        )}
                    </div>
                </div>
            </main>

            <EditClassDialog
                open={isEditOpen}
                onOpenChange={setIsEditOpen}
                classItem={editingClass}
                shs_strands={shs_strands}
                rooms={rooms}
                onSuccess={() => {
                    // handled by Inertia
                }}
            />

            <CreateClassDialog
                open={createDialogOpen}
                onOpenChange={setCreateDialogOpen}
                shs_strands={shs_strands}
                rooms={rooms}
                current_semester={current_semester}
                current_school_year={current_school_year}
                current_faculty={current_faculty}
            />
        </FacultyLayout>
    );
}
