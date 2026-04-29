import { Badge } from "@/components/ui/badge";
import { BookOpen, GraduationCap, Users, X } from "lucide-react";
import SchedulingConflictsPanel from "./scheduling-conflicts-panel";

type ClassScheduleData = {
    student_count: number;
};

type StudentSchedule = {
    student: { name: string };
};

type ConflictClass = {
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
    conflict_type: "room" | "faculty";
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
}: SchedulingStatsRowProps) {
    return (
        <div className="flex flex-wrap items-center gap-2">
            <Badge variant="secondary" className="gap-1 px-2.5 py-1 text-xs">
                <BookOpen className="h-3 w-3" /> {filteredData.length} {hasFilters ? `/ ${totalClasses}` : ""} Classes
            </Badge>
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
