import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { edit, index, restore } from "@/routes/administrators/classes";
import { Link, router } from "@inertiajs/react";
import { Archive, ArrowLeft, Pencil, RotateCcw } from "lucide-react";
import { useState } from "react";
import { toast } from "sonner";
import type { ClassShowCapabilities, ClassShowData } from "./types";

export function RecordHeader({ classItem, capabilities }: { classItem: ClassShowData; capabilities: ClassShowCapabilities }) {
    const [restoring, setRestoring] = useState(false);

    return (
        <header className="flex flex-col gap-6">
            <nav aria-label="Breadcrumb" className="text-muted-foreground flex items-center gap-2 text-sm">
                <Link
                    href={index()}
                    className="hover:text-foreground focus-visible:outline-ring inline-flex min-h-8 items-center gap-2 rounded-sm focus-visible:outline-2"
                >
                    <ArrowLeft className="size-4" aria-hidden="true" />
                    Classes
                </Link>
                <span aria-hidden="true">/</span>
                <span className="text-foreground truncate">Class record</span>
            </nav>

            <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
                <div className="min-w-0 space-y-2">
                    <div className="flex flex-wrap items-center gap-2 text-sm">
                        <span className="text-primary font-medium">{classItem.subject_code}</span>
                        <span className="text-muted-foreground" aria-hidden="true">
                            ·
                        </span>
                        <span className="text-muted-foreground">{classItem.classification === "shs" ? "Senior high school" : "College"}</span>
                        <Badge variant="outline">
                            {classItem.delivery_summary === "mixed"
                                ? "Mixed"
                                : (classItem.delivery_summary ?? classItem.delivery_mode) === "online"
                                  ? "Online"
                                  : "Face to face"}
                        </Badge>
                        {classItem.is_archived && <Badge variant="outline">Archived</Badge>}
                    </div>
                    <h1 className="text-2xl font-semibold tracking-tight text-balance">{classItem.record_title}</h1>
                    {classItem.subject_title && classItem.subject_title !== "N/A" && classItem.subject_title !== classItem.record_title && (
                        <p className="text-muted-foreground max-w-3xl text-base text-pretty">{classItem.subject_title}</p>
                    )}
                </div>
                {capabilities.update && (
                    <Button nativeButton={false} render={<Link href={edit(classItem.id)} />} className="h-10 gap-2 self-start px-4">
                        <Pencil className="size-4" aria-hidden="true" />
                        Edit class
                    </Button>
                )}
            </div>

            <dl className="grid grid-cols-2 gap-4 text-sm sm:grid-cols-4 sm:gap-6">
                <ContextField label="Academic term" value={classItem.semester} />
                <ContextField label="School year" value={classItem.school_year} />
                <ContextField label="Section & year" value={[classItem.section, classItem.year_level].filter(Boolean).join(" · ")} />
                <ContextField
                    label={classItem.classification === "shs" ? "Track & strand" : "Cohort"}
                    value={classItem.associated_courses || "Not specified"}
                />
            </dl>

            {classItem.is_archived && (
                <div className="bg-muted/40 flex flex-col gap-4 rounded-xl border p-4 sm:flex-row sm:items-center sm:justify-between" role="status">
                    <div className="flex items-start gap-3">
                        <Archive className="text-muted-foreground mt-1 size-4 shrink-0" aria-hidden="true" />
                        <div className="space-y-1 text-sm">
                            <p className="font-medium">This class is archived</p>
                            <p className="text-muted-foreground">
                                Its schedule and enrollments are archived with it. Restore the class to return them to the live list.
                            </p>
                        </div>
                    </div>
                    {capabilities.restore && (
                        <Button
                            variant="outline"
                            disabled={restoring}
                            className="h-10 self-start px-4"
                            onClick={() =>
                                router.post(
                                    restore.url(classItem.id),
                                    {},
                                    {
                                        preserveScroll: true,
                                        onStart: () => setRestoring(true),
                                        onFinish: () => setRestoring(false),
                                        onSuccess: () => toast.success(`${classItem.record_title} restored.`),
                                        onError: () => toast.error("The class could not be restored. Please try again."),
                                    },
                                )
                            }
                        >
                            <RotateCcw className="size-4" aria-hidden="true" />
                            {restoring ? "Restoring…" : "Restore class"}
                        </Button>
                    )}
                </div>
            )}
        </header>
    );
}

function ContextField({ label, value }: { label: string; value: string }) {
    return (
        <div className="min-w-0 space-y-1">
            <dt className="text-muted-foreground">{label}</dt>
            <dd className="font-medium break-words">{value || "Not specified"}</dd>
        </div>
    );
}
