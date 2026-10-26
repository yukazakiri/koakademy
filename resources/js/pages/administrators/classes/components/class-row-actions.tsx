import { Button } from "@/components/ui/button";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { show } from "@/routes/administrators/classes";
import { Link } from "@inertiajs/react";
import { Archive, CalendarDays, Copy, Eye, MoreHorizontal, Pencil, RotateCcw, Settings2, Trash2 } from "lucide-react";
import type { ClassCapabilities, ClassRow } from "../columns";

export type ClassActionHandlers = {
    onManage: (id: number) => void;
    onCopy: (id: number) => void;
    onEdit: (id: number) => void;
    onSchedule: (id: number) => void;
    onDelete: (row: ClassRow) => void;
    onRestore: (row: ClassRow) => void;
    onForceDelete: (row: ClassRow) => void;
    capabilities: ClassCapabilities;
};

export function ClassRowActions({
    classRow,
    capabilities,
    onManage,
    onCopy,
    onEdit,
    onSchedule,
    onDelete,
    onRestore,
    onForceDelete,
}: ClassActionHandlers & { classRow: ClassRow }) {
    const isArchived = Boolean(classRow.deleted_at);

    return (
        <div className="flex items-center justify-end gap-2">
            {!isArchived && capabilities.update ? (
                <Button type="button" variant="outline" className="h-9 px-3 text-sm" onClick={() => onSchedule(classRow.id)}>
                    <CalendarDays className="size-4" aria-hidden="true" />
                    Schedule
                </Button>
            ) : null}
            {isArchived && capabilities.restore ? (
                <Button type="button" variant="outline" className="h-9 px-3 text-sm" onClick={() => onRestore(classRow)}>
                    <RotateCcw className="size-4" aria-hidden="true" />
                    Restore
                </Button>
            ) : null}
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button type="button" variant="ghost" size="icon" className="size-9" aria-label={`Actions for ${classRow.record_title}`}>
                        <MoreHorizontal className="size-4" aria-hidden="true" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" className="w-52">
                    <DropdownMenuLabel>Class actions</DropdownMenuLabel>
                    <DropdownMenuItem asChild>
                        <Link href={show.url(classRow.id)}>
                            <Eye aria-hidden="true" /> View class
                        </Link>
                    </DropdownMenuItem>
                    {!isArchived ? (
                        <>
                            {capabilities.update ? (
                                <>
                                    <DropdownMenuItem onClick={() => onEdit(classRow.id)}>
                                        <Pencil aria-hidden="true" /> Edit class
                                    </DropdownMenuItem>
                                    <DropdownMenuItem onClick={() => onSchedule(classRow.id)}>
                                        <CalendarDays aria-hidden="true" /> Edit schedule
                                    </DropdownMenuItem>
                                    <DropdownMenuItem onClick={() => onManage(classRow.id)}>
                                        <Settings2 aria-hidden="true" /> Manage details
                                    </DropdownMenuItem>
                                </>
                            ) : null}
                            {capabilities.create ? (
                                <DropdownMenuItem onClick={() => onCopy(classRow.id)}>
                                    <Copy aria-hidden="true" /> Duplicate class
                                </DropdownMenuItem>
                            ) : null}
                            {capabilities.delete ? (
                                <>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem variant="destructive" onClick={() => onDelete(classRow)}>
                                        <Archive aria-hidden="true" /> Archive class
                                    </DropdownMenuItem>
                                </>
                            ) : null}
                        </>
                    ) : (
                        <>
                            {capabilities.restore ? (
                                <DropdownMenuItem onClick={() => onRestore(classRow)}>
                                    <RotateCcw aria-hidden="true" /> Restore class
                                </DropdownMenuItem>
                            ) : null}
                            {capabilities.force_delete ? (
                                <DropdownMenuItem variant="destructive" onClick={() => onForceDelete(classRow)}>
                                    <Trash2 aria-hidden="true" /> Delete permanently
                                </DropdownMenuItem>
                            ) : null}
                        </>
                    )}
                </DropdownMenuContent>
            </DropdownMenu>
        </div>
    );
}
