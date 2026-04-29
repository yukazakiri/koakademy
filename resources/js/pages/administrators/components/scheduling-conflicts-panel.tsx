import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { AlertTriangle, Building2, User as UserIcon, X } from "lucide-react";

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

type SchedulingConflictsPanelProps = {
    conflicts: ScheduleConflict[];
    expanded: boolean;
    showBadge?: boolean;
    showPanel?: boolean;
    onToggleExpanded: () => void;
    onCloseExpanded: () => void;
};

export default function SchedulingConflictsPanel({
    conflicts,
    expanded,
    showBadge = true,
    showPanel = true,
    onToggleExpanded,
    onCloseExpanded,
}: SchedulingConflictsPanelProps) {
    return (
        <>
            {showBadge && conflicts.length > 0 && (
                <Badge variant="destructive" className="cursor-pointer gap-1 px-2.5 py-1 text-xs" onClick={onToggleExpanded}>
                    <AlertTriangle className="h-3 w-3" /> {conflicts.length} Conflict{conflicts.length !== 1 ? "s" : ""}
                </Badge>
            )}

            {showPanel && expanded && conflicts.length > 0 && (
                <Card className="border-destructive/30 border">
                    <CardHeader className="pb-2">
                        <div className="flex items-center justify-between">
                            <CardTitle className="text-destructive flex items-center gap-2 text-sm">
                                <AlertTriangle className="h-4 w-4" /> Schedule Conflicts
                            </CardTitle>
                            <Button variant="ghost" size="sm" onClick={onCloseExpanded} className="h-7 px-2">
                                <X className="h-3.5 w-3.5" />
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <p className="text-muted-foreground mb-3 text-xs">
                            Conflicting classes share the same {conflicts.some((c) => c.conflict_type === "room") ? "room" : ""}
                            {conflicts.some((c) => c.conflict_type === "room") && conflicts.some((c) => c.conflict_type === "faculty") ? " or " : ""}
                            {conflicts.some((c) => c.conflict_type === "faculty") ? "faculty member" : ""} at the same time.
                        </p>

                        <div className="grid gap-2">
                            {conflicts.map((c, i) => (
                                <div
                                    key={i}
                                    className="grid grid-cols-[auto_1fr_auto_1fr] items-center gap-2 rounded-lg border border-red-200 bg-red-50/50 p-3 text-sm dark:border-red-900/50 dark:bg-red-950/20"
                                >
                                    <Badge
                                        variant="outline"
                                        className="shrink-0 border-red-300 text-[10px] text-red-700 dark:border-red-800 dark:text-red-300"
                                    >
                                        {c.conflict_type === "room" ? (
                                            <>
                                                <Building2 className="mr-0.5 h-3 w-3" />
                                                Room
                                            </>
                                        ) : (
                                            <>
                                                <UserIcon className="mr-0.5 h-3 w-3" />
                                                Faculty
                                            </>
                                        )}
                                    </Badge>
                                    <div>
                                        <div className="font-semibold">
                                            {c.class_1.subject_code} <span className="text-muted-foreground font-normal">({c.class_1.section})</span>
                                        </div>
                                        <div className="text-muted-foreground text-xs">
                                            {c.class_1.faculty || "TBA"} · {c.class_1.room || "No room"}
                                        </div>
                                    </div>
                                    <div className="text-muted-foreground flex flex-col items-center text-[10px]">
                                        <span className="font-semibold text-red-600 dark:text-red-400">{c.day.slice(0, 3)}</span>
                                        <span>{c.time}</span>
                                    </div>
                                    <div>
                                        <div className="font-semibold">
                                            {c.class_2.subject_code} <span className="text-muted-foreground font-normal">({c.class_2.section})</span>
                                        </div>
                                        <div className="text-muted-foreground text-xs">
                                            {c.class_2.faculty || "TBA"} · {c.class_2.room || "No room"}
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </CardContent>
                </Card>
            )}
        </>
    );
}
