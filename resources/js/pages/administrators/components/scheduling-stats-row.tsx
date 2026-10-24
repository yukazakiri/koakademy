import { Badge } from "@/components/ui/badge";
import { BookOpen, Clock, GraduationCap, Inbox, Users, X } from "lucide-react";
import SchedulingConflictsPanel from "./scheduling-conflicts-panel";

type ClassScheduleData = {
    student_count: number;
    is_unscheduled?: boolean;
    is_complete?: boolean;
    required_hours?: number;
    scheduled_hours?: number;
};

type StudentSchedule = {
    student: { name: string };
};

type ConflictClass = {
    id?: number;
    subject_code: string;
    section: string;
    room: string | null;
    faculty: string | null;
};

type ScheduleConflict = {
    day: string;
    time: string;
    class_1: ConflictClass;
    class_2: ConflictClass;
    conflict_type: "room" | "faculty" | "section";
};

type SchedulingStatsRowProps = {
    filteredData: ClassScheduleData[];
    totalClasses: number;
    hasFilters: boolean;
    conflicts: ScheduleConflict[];
    conflictsExpanded: boolean;
    onToggleConflictsExpanded: () => void;
    activeStudent: StudentSchedule | null;
    onClearActiveStudent: () => void;
    queueOpen?: boolean;
    onToggleQueue?: () => void;
};

export default function SchedulingStatsRow({
    filteredData,
    totalClasses,
    hasFilters,
    conflicts,
    conflictsExpanded,
    onToggleConflictsExpanded,
    activeStudent,
    onClearActiveStudent,
    queueOpen = false,
    onToggleQueue,
}: SchedulingStatsRowProps) {
    const unscheduledCount = filteredData.filter((c) => c.is_unscheduled || (c.scheduled_hours ?? 0) === 0).length;
    const incompleteCount = filteredData.filter(
        (c) => !c.is_unscheduled && (c.scheduled_hours ?? 0) > 0 && c.is_complete === false,
    ).length;

    const totalRequiredHours = filteredData.reduce((acc, c) => acc + (c.required_hours ?? 3), 0);
    const totalScheduledHours = filteredData.reduce((acc, c) => acc + (c.scheduled_hours ?? 0), 0);

    return (
        <div className="flex flex-wrap items-center gap-2">
            <Badge variant="secondary" className="gap-1 px-2.5 py-1 text-xs">
                <BookOpen className="h-3 w-3" /> {filteredData.length} {hasFilters ? `/ ${totalClasses}` : ""} Classes
            </Badge>

            <Badge variant="secondary" className="gap-1 px-2.5 py-1 text-xs">
                <Clock className="h-3 w-3" /> {totalScheduledHours.toFixed(1).replace(/\.0$/, "")} / {totalRequiredHours}h Scheduled
            </Badge>

            {onToggleQueue && (
                <Badge
                    variant={unscheduledCount > 0 ? "default" : "outline"}
                    onClick={onToggleQueue}
                    className={`cursor-pointer gap-1 px-2.5 py-1 text-xs transition-colors ${
                        unscheduledCount > 0
                            ? "bg-amber-600 text-white hover:bg-amber-700"
                            : queueOpen
                              ? "border-primary bg-primary/10 text-primary"
                              : ""
                    }`}
                >
                    <Inbox className="h-3 w-3" />
                    {unscheduledCount} Unscheduled
                    {incompleteCount > 0 ? ` · ${incompleteCount} Incomplete` : ""}
                </Badge>
            )}

            <Badge variant="secondary" className="gap-1 px-2.5 py-1 text-xs">
                <Users className="h-3 w-3" /> {filteredData.reduce((a, c) => a + c.student_count, 0).toLocaleString()} Students
            </Badge>

            <SchedulingConflictsPanel
                conflicts={conflicts}
                expanded={conflictsExpanded}
                showPanel={false}
                onToggleExpanded={onToggleConflictsExpanded}
                onCloseExpanded={onToggleConflictsExpanded}
            />

            {activeStudent && (
                <Badge className="gap-1 bg-indigo-100 px-2.5 py-1 text-xs text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300">
                    <GraduationCap className="h-3 w-3" />
                    {activeStudent.student.name}
                    <button onClick={onClearActiveStudent} className="hover:text-destructive ml-1">
                        <X className="h-3 w-3" />
                    </button>
                </Badge>
            )}
        </div>
    );
}
