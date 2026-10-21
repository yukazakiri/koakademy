import { AlertTriangle, GraduationCap, Users } from "lucide-react";
import type { ReactNode } from "react";

type ClassStatsProps = {
    totalClasses: number;
    totalStudents: number;
    totalCapacity: number;
    needsAttention: number;
};

type StatProps = {
    label: string;
    value: ReactNode;
    subtext: string;
    icon: ReactNode;
    tone?: "default" | "alert";
};

function Stat({ label, value, subtext, icon, tone = "default" }: StatProps): ReactNode {
    return (
        <div
            className={`rounded-xl border p-4 ${
                tone === "alert" ? "border-destructive/30 bg-destructive/5" : "border-border bg-card"
            }`}
        >
            <div className="flex items-center gap-2">
                <span className={tone === "alert" ? "text-destructive" : "text-muted-foreground"}>{icon}</span>
                <span className="text-muted-foreground text-[0.7rem] font-semibold uppercase tracking-wider">{label}</span>
            </div>
            <div className={`mt-1.5 text-2xl font-semibold tabular-nums ${tone === "alert" ? "text-destructive" : ""}`}>{value}</div>
            <div className="text-muted-foreground text-xs">{subtext}</div>
        </div>
    );
}

export function ClassStats({ totalClasses, totalStudents, totalCapacity, needsAttention }: ClassStatsProps) {
    const fillRate = totalCapacity > 0 ? Math.round((totalStudents / totalCapacity) * 100) : 0;
    const openSlots = Math.max(totalCapacity - totalStudents, 0);

    return (
        <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <Stat
                label="Sections"
                value={totalClasses.toLocaleString()}
                subtext="in the current term"
                icon={<GraduationCap className="size-4" />}
            />
            <Stat
                label="Enrolled"
                value={totalStudents.toLocaleString()}
                subtext={`${openSlots.toLocaleString()} seats still open`}
                icon={<Users className="size-4" />}
            />
            <Stat
                label="Capacity used"
                value={`${fillRate}%`}
                subtext={`of ${totalCapacity.toLocaleString()} seats`}
                icon={<GraduationCap className="size-4" />}
            />
            <Stat
                label="Needs attention"
                value={needsAttention.toLocaleString()}
                subtext={needsAttention === 0 ? "everything is in order" : "classes are waiting on someone"}
                icon={<AlertTriangle className="size-4" />}
                tone={needsAttention > 0 ? "alert" : "default"}
            />
        </div>
    );
}
