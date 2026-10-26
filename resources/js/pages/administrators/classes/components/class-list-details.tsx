import { cn } from "@/lib/utils";
import type { ClassRow } from "../columns";

export function semesterLabel(value: string | number): string {
    if (String(value) === "1") return "1st semester";
    if (String(value) === "2") return "2nd semester";
    if (String(value).toLowerCase() === "summer") return "Summer";
    return `Semester ${value}`;
}

export function classProgramLabel(row: ClassRow): string {
    if (row.classification === "shs") {
        return [row.shs_track, row.shs_strand, row.grade_level].filter(Boolean).join(" · ") || "Senior high school";
    }

    return [row.course_abbreviations?.join(", "), row.academic_year ? `Year ${row.academic_year}` : null].filter(Boolean).join(" · ") || "College";
}

export function ClassFaculty({ faculty }: { faculty: string }) {
    const unassigned = !faculty || faculty === "TBA";

    return <span className={cn("text-sm", unassigned && "text-muted-foreground")}>{unassigned ? "Not assigned" : faculty}</span>;
}

export function ClassEnrollment({ classRow }: { classRow: ClassRow }) {
    const { students_count: enrolled, maximum_slots: capacity } = classRow;
    const available = Math.max(capacity - enrolled, 0);
    const overCapacity = capacity > 0 && enrolled > capacity;
    const full = capacity > 0 && enrolled === capacity;
    const percentage = capacity > 0 ? Math.min((enrolled / capacity) * 100, 100) : 0;

    return (
        <div className="min-w-36 space-y-2">
            <div className="flex items-baseline justify-between gap-4 text-sm">
                <span className="tabular-nums">
                    <span className="font-medium">{enrolled}</span>
                    <span className="text-muted-foreground"> / {capacity}</span>
                    <span className="sr-only"> students enrolled</span>
                </span>
                <span className={cn("text-muted-foreground", overCapacity && "text-destructive")}>
                    {overCapacity ? `${enrolled - capacity} over capacity` : full ? "Full" : capacity > 0 ? `${available} open` : "No capacity set"}
                </span>
            </div>
            <div className="bg-muted h-1 overflow-hidden rounded-full" aria-hidden="true">
                <div className={cn("bg-primary/60 h-full rounded-full", overCapacity && "bg-destructive")} style={{ width: `${percentage}%` }} />
            </div>
        </div>
    );
}
