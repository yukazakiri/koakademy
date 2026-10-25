import { Badge } from "@/components/ui/badge";
import { Checkbox } from "@/components/ui/checkbox";
import { cn } from "@/lib/utils";
import { show } from "@/routes/administrators/classes";
import { Link } from "@inertiajs/react";
import { BookOpen, Building2, Calendar, GraduationCap, MapPin, Monitor, UserCheck } from "lucide-react";
import type { ClassRow } from "../columns";
import { deliveryModeLabel } from "../lib/class-defaults";
import { ClassEnrollment, classProgramLabel, semesterLabel } from "./class-list-details";
import { ClassRowActions, type ClassActionHandlers } from "./class-row-actions";

export function ClassCard({
    classRow,
    selected,
    onSelect,
    ...actions
}: ClassActionHandlers & {
    classRow: ClassRow;
    selected: boolean;
    onSelect: (selected: boolean) => void;
}) {
    const isCollege = classRow.classification !== "shs";
    const delivery = classRow.delivery_summary ?? classRow.delivery_mode;
    const isOnline = delivery === "online";
    const isUnassignedFaculty = !classRow.faculty || classRow.faculty === "TBA";

    return (
        <article
            className={cn(
                "group bg-card text-card-foreground relative flex min-w-0 flex-col overflow-hidden rounded-2xl border shadow-2xs transition-all duration-200 hover:shadow-sm",
                selected && "border-primary ring-primary/30 bg-primary/[0.02] ring-1",
            )}
        >
            {/* Visual Top Accent Banner */}
            <div className="from-primary/10 via-primary/5 to-muted/20 relative border-b bg-gradient-to-br p-4">
                <div className="flex items-center justify-between gap-2">
                    <div className="flex items-center gap-2">
                        <label className="flex size-6 shrink-0 cursor-pointer items-center justify-center">
                            <Checkbox
                                checked={selected}
                                onCheckedChange={(value) => onSelect(Boolean(value))}
                                aria-label={`Select ${classRow.record_title}`}
                                className="rounded-[5px]"
                            />
                        </label>

                        <Badge variant="outline" className="bg-background/80 font-mono text-xs font-semibold backdrop-blur">
                            Section {classRow.section}
                        </Badge>
                    </div>

                    <div className="flex items-center gap-1.5">
                        <Badge variant="secondary" className="gap-1 text-[11px] font-medium">
                            {isCollege ? <BookOpen className="size-3" /> : <GraduationCap className="size-3" />}
                            {isCollege ? "College" : "SHS"}
                        </Badge>

                        <Badge
                            variant="outline"
                            className={cn(
                                "gap-1 text-[11px] font-medium",
                                isOnline && "border-indigo-500/30 bg-indigo-50/50 text-indigo-700 dark:bg-indigo-950/30 dark:text-indigo-300",
                                !isOnline && "border-blue-500/30 bg-blue-50/50 text-blue-700 dark:bg-blue-950/30 dark:text-blue-300",
                            )}
                        >
                            {isOnline ? <Monitor className="size-3" /> : <Building2 className="size-3" />}
                            {deliveryModeLabel(delivery)}
                        </Badge>

                        {classRow.deleted_at ? (
                            <Badge variant="destructive" className="text-[11px]">
                                Archived
                            </Badge>
                        ) : null}
                    </div>
                </div>

                <div className="mt-3 space-y-1">
                    <Link
                        href={show.url(classRow.id)}
                        className="hover:text-primary focus-visible:outline-ring text-foreground block truncate rounded-sm text-lg font-bold tracking-tight transition-colors focus-visible:outline-2"
                    >
                        {classRow.record_title}
                    </Link>

                    <p className="text-muted-foreground truncate text-xs font-medium">
                        {classRow.subject_title || classRow.subject_code} · {classProgramLabel(classRow)}
                    </p>
                </div>
            </div>

            {/* Card Body Information */}
            <div className="flex flex-1 flex-col gap-4 p-4">
                <div className="grid grid-cols-2 gap-2.5 text-xs">
                    {/* Faculty */}
                    <div className="bg-muted/20 rounded-xl border p-2.5">
                        <span className="text-muted-foreground flex items-center gap-1 font-medium">
                            <UserCheck className="text-primary size-3 shrink-0" />
                            Faculty
                        </span>
                        <p className={cn("mt-1 truncate font-semibold", isUnassignedFaculty && "text-muted-foreground font-normal")}>
                            {isUnassignedFaculty ? "Unassigned" : classRow.faculty}
                        </p>
                    </div>

                    {/* Room */}
                    <div className="bg-muted/20 rounded-xl border p-2.5">
                        <span className="text-muted-foreground flex items-center gap-1 font-medium">
                            <MapPin className="text-primary size-3 shrink-0" />
                            Location
                        </span>
                        <p className="text-foreground mt-1 truncate font-semibold">
                            {isOnline ? (
                                <span className="font-medium text-indigo-600 dark:text-indigo-400">Online</span>
                            ) : (
                                classRow.room || <span className="text-muted-foreground font-normal">Unassigned</span>
                            )}
                        </p>
                    </div>

                    {/* Term */}
                    <div className="bg-muted/20 col-span-2 rounded-xl border p-2.5">
                        <span className="text-muted-foreground flex items-center gap-1 font-medium">
                            <Calendar className="text-primary size-3 shrink-0" />
                            Academic Term
                        </span>
                        <p className="text-foreground mt-0.5 truncate font-semibold">
                            {classRow.school_year} · {semesterLabel(classRow.semester)}
                        </p>
                    </div>
                </div>

                {/* Enrollment Capacity & Actions */}
                <div className="mt-auto space-y-3.5 pt-1">
                    <ClassEnrollment classRow={classRow} />
                    <div className="border-t pt-3">
                        <ClassRowActions classRow={classRow} {...actions} />
                    </div>
                </div>
            </div>
        </article>
    );
}
