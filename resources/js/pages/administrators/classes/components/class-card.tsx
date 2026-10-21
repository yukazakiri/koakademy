import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from "@/components/ui/dropdown-menu";
import { Link } from "@inertiajs/react";
import { BookOpen, CalendarIcon, Archive, Copy as CopyIcon, MoreHorizontal, Pencil, RotateCcw, Settings2, Trash2, Users } from "lucide-react";
import { route } from "ziggy-js";
import type { ClassRow } from "../columns";

export function ClassCard({
    classRow,
    onManage,
    onCopy,
    onEdit,
    onDelete,
    onRestore,
    onForceDelete,
}: {
    classRow: ClassRow;
    onManage: (id: number) => void;
    onCopy: (id: number) => void;
    onEdit: (id: number) => void;
    onDelete: (row: ClassRow) => void;
    onRestore?: (row: ClassRow) => void;
    onForceDelete?: (row: ClassRow) => void;
}) {
    const isArchived = Boolean(classRow.deleted_at);
    const atCapacity = classRow.maximum_slots > 0 && classRow.students_count >= classRow.maximum_slots;
    const percentage = classRow.maximum_slots > 0 ? Math.round((classRow.students_count / classRow.maximum_slots) * 100) : 0;
    const barColor = atCapacity ? "bg-destructive" : percentage >= 75 ? "bg-amber-500" : "bg-primary";
    const borderColor = classRow.classification === "shs" ? "border-l-amber-500" : "border-l-blue-500";
    const capacityDot = atCapacity ? "bg-destructive" : percentage >= 75 ? "bg-amber-500" : "bg-emerald-500";
    const capacityLabel = atCapacity ? "Full" : "Open";
    const slotsLeft = Math.max(classRow.maximum_slots - classRow.students_count, 0);

    return (
        <Card className={`hover:border-primary/40 border-l-4 shadow-sm ${borderColor} transition-colors`}>
            <CardContent className="space-y-3 p-4">
                <div className="flex items-start justify-between gap-2">
                    <div className="min-w-0">
                        <button type="button" onClick={() => onEdit(classRow.id)} className="text-left" title="Click to edit">
                            <span className="text-foreground hover:text-primary line-clamp-1 block font-semibold transition-colors">
                                {classRow.record_title}
                            </span>
                            <span className="text-muted-foreground line-clamp-1 block text-sm" title={classRow.subject_title}>
                                {classRow.subject_title}
                            </span>
                        </button>
                    </div>
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button variant="ghost" size="icon" className="text-muted-foreground h-8 w-8 shrink-0">
                                <MoreHorizontal className="h-4 w-4" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" className="w-48">
                            {isArchived ? (
                                <>
                                    <DropdownMenuItem onClick={() => onRestore?.(classRow)}>
                                        <RotateCcw className="mr-2 h-4 w-4" /> Restore class
                                    </DropdownMenuItem>
                                    <DropdownMenuItem
                                        onClick={() => onForceDelete?.(classRow)}
                                        className="text-destructive focus:text-destructive"
                                    >
                                        <Trash2 className="mr-2 h-4 w-4" /> Delete permanently
                                    </DropdownMenuItem>
                                </>
                            ) : (
                                <>
                                    <DropdownMenuItem onClick={() => onEdit(classRow.id)}>
                                        <Pencil className="mr-2 h-4 w-4" /> Edit class
                                    </DropdownMenuItem>
                                    <DropdownMenuItem onClick={() => onManage(classRow.id)}>
                                        <Settings2 className="mr-2 h-4 w-4" /> Manage details
                                    </DropdownMenuItem>
                                    <DropdownMenuItem asChild>
                                        <Link href={route("administrators.classes.show", { class: classRow.id })}>
                                            <BookOpen className="mr-2 h-4 w-4" /> Open class page
                                        </Link>
                                    </DropdownMenuItem>
                                    <DropdownMenuItem onClick={() => onCopy(classRow.id)}>
                                        <CopyIcon className="mr-2 h-4 w-4" /> Duplicate class
                                    </DropdownMenuItem>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem onClick={() => onDelete(classRow)} className="text-destructive focus:text-destructive">
                                        <Archive className="mr-2 h-4 w-4" /> Archive class
                                    </DropdownMenuItem>
                                </>
                            )}
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>

                <div className="flex flex-wrap items-center gap-1.5">
                    <Badge
                        variant="outline"
                        className={`text-[10px] ${classRow.classification === "shs" ? "border-amber-300 bg-amber-50 text-amber-700 dark:border-amber-700 dark:bg-amber-950 dark:text-amber-300" : "border-blue-300 bg-blue-50 text-blue-700 dark:border-blue-700 dark:bg-blue-950 dark:text-blue-300"}`}
                    >
                        {classRow.classification === "shs" ? "SHS" : "College"}
                    </Badge>
                    <Badge variant="secondary" className="text-[10px]">
                        {classRow.subject_code}
                    </Badge>
                    <Badge variant="outline" className="text-[10px]">
                        Sec {classRow.section}
                    </Badge>
                    {isArchived ? (
                        <Badge variant="outline" className="text-[10px]">
                            Archived
                        </Badge>
                    ) : null}
                    {classRow.classification === "shs" && classRow.shs_track ? (
                        <Badge variant="outline" className="text-[10px]">
                            {classRow.shs_strand ? `${classRow.shs_track} – ${classRow.shs_strand}` : classRow.shs_track}
                        </Badge>
                    ) : null}
                    {classRow.course_abbreviations?.map((code) => (
                        <Badge key={code} variant="outline" className="text-[10px]">
                            {code}
                        </Badge>
                    ))}
                </div>

                <div className="text-muted-foreground grid grid-cols-2 gap-x-4 gap-y-1 text-sm">
                    <span className="flex items-center gap-1.5 truncate" title={classRow.faculty || "Not assigned"}>
                        <Users className="h-3 w-3 shrink-0" />
                        {classRow.faculty || "Not assigned"}
                    </span>
                    <span className="flex items-center gap-1.5">
                        <CalendarIcon className="h-3 w-3 shrink-0" />
                        {classRow.school_year} · Sem {classRow.semester}
                    </span>
                </div>

                <div className="space-y-1.5 border-t pt-3">
                    <div className="flex items-center justify-between text-xs">
                        <div className="flex items-center gap-1.5">
                            <span className={`h-2 w-2 shrink-0 rounded-full ${capacityDot}`} />
                            <span className="font-medium">{capacityLabel}</span>
                            {!atCapacity && <span className="text-muted-foreground">· {slotsLeft} slots</span>}
                        </div>
                        <span className={`tabular-nums ${atCapacity ? "text-destructive font-medium" : "text-muted-foreground"}`}>
                            {classRow.students_count}/{classRow.maximum_slots} ({percentage}%)
                        </span>
                    </div>
                    <div className="bg-secondary h-2 w-full overflow-hidden rounded-full">
                        <div className={`h-full rounded-full transition-all ${barColor}`} style={{ width: `${Math.min(percentage, 100)}%` }} />
                    </div>
                </div>

                <div className="flex items-center gap-2 border-t pt-3">
                    {isArchived ? (
                        <>
                            <Button type="button" size="sm" variant="default" className="flex-1" onClick={() => onRestore?.(classRow)}>
                                <RotateCcw className="mr-1 h-3.5 w-3.5" />
                                Restore
                            </Button>
                            <Button type="button" size="sm" variant="outline" onClick={() => onForceDelete?.(classRow)} title="Delete permanently">
                                <Trash2 className="h-3.5 w-3.5" />
                            </Button>
                        </>
                    ) : (
                        <>
                            <Button type="button" size="sm" variant="default" className="flex-1" onClick={() => onEdit(classRow.id)}>
                                <Pencil className="mr-1 h-3 w-3" />
                                Edit
                            </Button>
                            <Button type="button" size="sm" variant="outline" onClick={() => onManage(classRow.id)} title="Manage">
                                <Settings2 className="h-3.5 w-3.5" />
                            </Button>
                            <Button type="button" size="sm" variant="outline" onClick={() => onCopy(classRow.id)} title="Duplicate">
                                <CopyIcon className="h-3.5 w-3.5" />
                            </Button>
                        </>
                    )}
                </div>
            </CardContent>
        </Card>
    );
}


