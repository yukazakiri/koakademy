import { Badge } from "@/components/ui/badge";
import { Checkbox } from "@/components/ui/checkbox";
import { cn } from "@/lib/utils";
import { show } from "@/routes/administrators/classes";
import { Link } from "@inertiajs/react";
import type { ClassRow } from "../columns";
import { deliveryModeLabel } from "../lib/class-defaults";
import { ClassEnrollment, ClassFaculty, classProgramLabel, semesterLabel } from "./class-list-details";
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
    return (
        <article className={cn("bg-card flex min-w-0 flex-col gap-6 rounded-lg border p-6", selected && "border-primary ring-primary ring-1")}>
            <div className="flex items-start gap-3">
                <label className="flex size-8 shrink-0 cursor-pointer items-center justify-center">
                    <Checkbox
                        checked={selected}
                        onCheckedChange={(value) => onSelect(Boolean(value))}
                        aria-label={`Select ${classRow.record_title}`}
                    />
                </label>
                <div className="min-w-0 space-y-1">
                    <Link
                        href={show.url(classRow.id)}
                        className="hover:text-primary focus-visible:outline-ring rounded-sm text-base font-semibold focus-visible:outline-2"
                    >
                        {classRow.record_title}
                    </Link>
                    <p className="text-muted-foreground text-sm">{classRow.subject_title}</p>
                    <p className="text-muted-foreground text-sm">
                        {classRow.subject_code} · Section {classRow.section}
                    </p>
                </div>
            </div>
            <div className="flex flex-wrap gap-2">
                <Badge variant="secondary">{classRow.classification === "shs" ? "Senior high" : "College"}</Badge>
                <Badge variant="outline">{deliveryModeLabel(classRow.delivery_mode)}</Badge>
                {classRow.deleted_at ? <Badge variant="outline">Archived</Badge> : null}
                <span className="text-muted-foreground text-sm">{classProgramLabel(classRow)}</span>
            </div>
            <dl className="grid gap-4 text-sm">
                <div className="grid grid-cols-[5rem_1fr] gap-3">
                    <dt className="text-muted-foreground">Faculty</dt>
                    <dd>
                        <ClassFaculty faculty={classRow.faculty} />
                    </dd>
                </div>
                <div className="grid grid-cols-[5rem_1fr] gap-3">
                    <dt className="text-muted-foreground">Term</dt>
                    <dd>
                        {classRow.school_year} · {semesterLabel(classRow.semester)}
                    </dd>
                </div>
            </dl>
            <div className="mt-auto space-y-4">
                <ClassEnrollment classRow={classRow} />
                <ClassRowActions classRow={classRow} {...actions} />
            </div>
        </article>
    );
}
