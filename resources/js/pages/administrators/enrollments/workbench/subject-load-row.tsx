import { Badge } from "@/components/reui/badge";
import { Button } from "@/components/ui/button";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import { cn } from "@/lib/utils";
import { AlertTriangle, Clock, Loader2, Trash2 } from "lucide-react";
import { useMemo } from "react";
import type {
    DraftSubjectEnrollment,
    SectionOption,
    WaitlistBadgeItem,
} from "./types";

export interface SubjectLoadRowProps {
    subject: DraftSubjectEnrollment;
    isAdded?: boolean;
    hasConflict?: boolean;
    conflictDetails?: string[];
    waitlist?: WaitlistBadgeItem;
    canManageTuitionFees: boolean;
    disabled?: boolean;
    isHighlighted?: boolean;
    onHoverChange?: (subjectId: number | null) => void;
    onSelectSection: (subjectId: number, classId: string) => void;
    onToggleModular: (subjectId: number, checked: boolean) => void;
    onToggleExclude: (subjectId: number, checked: boolean) => void;
    onRemove: (subjectId: number) => void;
    checkSectionConflicts: (
        subjectId: number,
        section: SectionOption,
    ) => string[];
    formatCurrency: (amount: number) => string;
}

export function SubjectLoadRow({
    subject,
    isAdded = false,
    hasConflict = false,
    conflictDetails,
    waitlist,
    canManageTuitionFees,
    disabled = false,
    isHighlighted = false,
    onHoverChange,
    onSelectSection,
    onToggleModular,
    onToggleExclude,
    onRemove,
    checkSectionConflicts,
    formatCurrency,
}: SubjectLoadRowProps) {
    const lecUnits = Number(subject.lecture_units) || 0;
    const labUnits = Number(subject.laboratory_units) || 0;
    const totalUnits = lecUnits + labUnits;
    const lineTotal =
        (Number(subject.lecture_fee) || 0) +
        (Number(subject.laboratory_fee) || 0);

    const sections = subject.sections ?? [];
    const hasNoSectionsForTerm =
        !subject.loading_sections &&
        subject.sections !== undefined &&
        sections.length === 0 &&
        !subject.class_id;

    const selectedSection = useMemo(
        () =>
            subject.class_id !== null
                ? sections.find((sec) => sec.id === subject.class_id)
                : undefined,
        [sections, subject.class_id],
    );

    const selectValue =
        subject.class_id !== null ? String(subject.class_id) : "none";

    const removeTooltip = subject.has_grades
        ? "Warning: This subject has recorded grades. Removing it requires grade-loss confirmation."
        : `Remove ${subject.subject_code}`;

    const excludeTooltip = !canManageTuitionFees
        ? "You do not have permission to exclude subjects from tuition"
        : subject.exclude_from_tuition
          ? "Excluded from tuition — click to include"
          : "Exclude this subject from tuition calculation";

    return (
        <div
            onMouseEnter={() => onHoverChange?.(subject.subject_id)}
            onMouseLeave={() => onHoverChange?.(null)}
            className={cn(
                "grid grid-cols-1 items-center gap-3 border-b border-border/70 px-3.5 py-3 transition-colors last:border-b-0 lg:grid-cols-[minmax(0,1.35fr)_minmax(0,1.15fr)_auto_auto]",
                hasConflict && "bg-destructive/[0.04] dark:bg-destructive/[0.08]",
                isHighlighted &&
                    !hasConflict &&
                    "bg-primary/[0.04] ring-1 ring-inset ring-primary/25 dark:bg-primary/[0.08]",
                isHighlighted &&
                    hasConflict &&
                    "ring-1 ring-inset ring-destructive/45",
                !isHighlighted && !hasConflict && "hover:bg-muted/35",
            )}
        >
            {/* Left column: subject identity, status badges, units, conflict details */}
            <div className="min-w-0 space-y-1">
                <div className="flex min-w-0 flex-wrap items-center gap-1.5">
                    <span className="shrink-0 font-mono text-xs font-semibold tracking-tight text-foreground">
                        {subject.subject_code}
                    </span>

                    {isAdded && (
                        <Badge variant="success-light" size="xs">
                            Added
                        </Badge>
                    )}

                    {hasConflict && (
                        <Badge variant="destructive-light" size="xs">
                            <AlertTriangle className="size-2.5" />
                            Time clash
                        </Badge>
                    )}

                    {subject.has_grades && (
                        <Badge
                            variant="warning-light"
                            size="xs"
                            title={
                                subject.grade_summary
                                    ? `Recorded grade: ${subject.grade_summary}`
                                    : "Subject has recorded grades"
                            }
                        >
                            Graded:{" "}
                            {subject.grade_summary?.trim() || "Recorded"}
                        </Badge>
                    )}

                    {waitlist && (
                        <Badge
                            variant="info-light"
                            size="xs"
                            title={waitlist.notes ?? undefined}
                        >
                            Waitlist #{waitlist.position}
                        </Badge>
                    )}

                    {subject.prerequisite_satisfied === false && (
                        <Badge
                            variant="warning-outline"
                            size="xs"
                            className="max-w-full"
                            title={`Missing prerequisite: ${subject.missing_prerequisite || "Required"}`}
                        >
                            <span className="truncate">
                                Prereq:{" "}
                                {subject.missing_prerequisite || "Unmet"}
                            </span>
                        </Badge>
                    )}

                    {subject.already_passed && (
                        <Badge variant="secondary" size="xs">
                            Passed
                        </Badge>
                    )}

                    {subject.is_failed_retake && (
                        <Badge variant="destructive-outline" size="xs">
                            Retake
                        </Badge>
                    )}
                </div>

                <p
                    className="truncate text-xs font-medium text-foreground/90"
                    title={subject.subject_title}
                >
                    {subject.subject_title}
                </p>

                <div className="flex min-w-0 flex-wrap items-center gap-x-2 gap-y-0.5 text-[11px] text-muted-foreground tabular-nums">
                    <span className="shrink-0">
                        {lecUnits}L / {labUnits}Lab · {totalUnits}u
                    </span>

                    {conflictDetails && conflictDetails.length > 0 && (
                        <span
                            className="inline-flex min-w-0 items-center gap-1 font-medium text-destructive"
                            title={conflictDetails.join(" · ")}
                        >
                            <Clock className="size-3 shrink-0" />
                            <span className="truncate">
                                {conflictDetails.join(" · ")}
                            </span>
                        </span>
                    )}
                </div>
            </div>

            {/* Section picker column */}
            <div className="min-w-0">
                {subject.loading_sections ? (
                    <div className="flex h-8 w-full items-center gap-2 rounded-lg border border-border/70 bg-muted/40 px-2.5 text-xs text-muted-foreground">
                        <Loader2 className="size-3.5 shrink-0 animate-spin" />
                        <span className="truncate">Loading sections…</span>
                    </div>
                ) : hasNoSectionsForTerm ? (
                    <div className="flex h-8 w-full items-center rounded-lg border border-dashed border-border bg-muted/30 px-2.5">
                        <Badge
                            variant="outline"
                            size="xs"
                            className="max-w-full border-transparent bg-transparent px-0 text-muted-foreground"
                        >
                            <span className="truncate">
                                No sections for term
                            </span>
                        </Badge>
                    </div>
                ) : (
                    <Select
                        value={selectValue}
                        onValueChange={(value) =>
                            onSelectSection(subject.subject_id, value)
                        }
                        disabled={disabled}
                    >
                        <SelectTrigger
                            className={cn(
                                "h-8 w-full min-w-0 text-xs",
                                hasConflict &&
                                    "border-destructive/60 focus-visible:border-destructive focus-visible:ring-destructive/25",
                            )}
                            aria-label={`Select section for ${subject.subject_code}`}
                        >
                            <SelectValue placeholder="Unassigned">
                                {selectedSection ? (
                                    <span className="flex min-w-0 items-center gap-1.5">
                                        <span className="truncate font-medium text-foreground">
                                            {selectedSection.section}
                                        </span>
                                        <Badge
                                            variant={
                                                selectedSection.is_full
                                                    ? "destructive-light"
                                                    : "secondary"
                                            }
                                            size="xs"
                                            className="shrink-0 tabular-nums"
                                        >
                                            {selectedSection.is_full
                                                ? "FULL"
                                                : `${selectedSection.enrolled_count}/${selectedSection.max_slots}`}
                                        </Badge>
                                        {selectedSection.schedule && (
                                            <span className="hidden truncate text-[11px] text-muted-foreground xl:inline">
                                                {selectedSection.schedule}
                                            </span>
                                        )}
                                    </span>
                                ) : subject.class_id &&
                                  subject.class_section ? (
                                    <span className="truncate font-medium text-foreground">
                                        {subject.class_section}
                                    </span>
                                ) : (
                                    <span className="text-muted-foreground">
                                        Unassigned
                                    </span>
                                )}
                            </SelectValue>
                        </SelectTrigger>

                        <SelectContent className="max-w-[360px]">
                            <SelectItem value="none" textValue="Unassigned">
                                <span className="text-xs text-muted-foreground">
                                    Unassigned
                                </span>
                            </SelectItem>

                            {sections.map((section) => {
                                const sectionConflicts = checkSectionConflicts(
                                    subject.subject_id,
                                    section,
                                );
                                const hasSectionClash =
                                    sectionConflicts.length > 0;

                                return (
                                    <SelectItem
                                        key={section.id}
                                        value={String(section.id)}
                                        textValue={`${section.section} ${section.schedule ?? ""} ${section.faculty ?? ""}`}
                                        className="py-1.5"
                                    >
                                        <div className="flex min-w-0 flex-col gap-0.5">
                                            <div className="flex flex-wrap items-center gap-1.5">
                                                <span className="font-medium text-foreground">
                                                    {section.section}
                                                </span>
                                                <Badge
                                                    variant={
                                                        section.is_full
                                                            ? "destructive-light"
                                                            : "secondary"
                                                    }
                                                    size="xs"
                                                    className="tabular-nums"
                                                >
                                                    {section.is_full
                                                        ? "FULL"
                                                        : `${section.enrolled_count}/${section.max_slots}`}
                                                </Badge>
                                                {hasSectionClash && (
                                                    <Badge
                                                        variant="destructive-light"
                                                        size="xs"
                                                    >
                                                        <AlertTriangle className="size-2.5" />
                                                        Clash
                                                    </Badge>
                                                )}
                                            </div>
                                            <span className="truncate text-[11px] text-muted-foreground">
                                                {section.schedule || "TBA"}
                                                {section.faculty
                                                    ? ` · ${section.faculty}`
                                                    : ""}
                                            </span>
                                            {hasSectionClash && (
                                                <span className="truncate text-[10px] font-medium text-destructive">
                                                    Overlaps{" "}
                                                    {sectionConflicts.join(
                                                        ", ",
                                                    )}
                                                </span>
                                            )}
                                        </div>
                                    </SelectItem>
                                );
                            })}
                        </SelectContent>
                    </Select>
                )}
            </div>

            {/* Toggle chips column: Mod & Excl */}
            <div className="flex shrink-0 items-center gap-1.5">
                <button
                    type="button"
                    aria-pressed={subject.is_modular}
                    disabled={disabled}
                    onClick={() =>
                        onToggleModular(subject.subject_id, !subject.is_modular)
                    }
                    title={
                        subject.is_modular
                            ? "Modular delivery enabled — click to disable"
                            : "Enable modular delivery for this subject"
                    }
                    className={cn(
                        "inline-flex h-7 items-center rounded-md border px-2 text-[11px] font-medium transition-colors outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:pointer-events-none disabled:opacity-50",
                        subject.is_modular
                            ? "border-primary/40 bg-primary/10 text-primary dark:border-primary/45 dark:bg-primary/20"
                            : "border-border bg-background text-muted-foreground hover:bg-muted hover:text-foreground",
                    )}
                >
                    Mod
                </button>

                <button
                    type="button"
                    aria-pressed={subject.exclude_from_tuition}
                    disabled={disabled || !canManageTuitionFees}
                    onClick={() =>
                        onToggleExclude(
                            subject.subject_id,
                            !subject.exclude_from_tuition,
                        )
                    }
                    title={excludeTooltip}
                    className={cn(
                        "inline-flex h-7 items-center rounded-md border px-2 text-[11px] font-medium transition-colors outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:pointer-events-none disabled:opacity-50",
                        subject.exclude_from_tuition
                            ? "border-warning/45 bg-warning/10 text-warning-foreground dark:border-warning/45 dark:bg-warning/15 dark:text-warning"
                            : "border-border bg-background text-muted-foreground hover:bg-muted hover:text-foreground",
                    )}
                >
                    Excl
                </button>
            </div>

            {/* Right column: line fee + remove action */}
            <div className="flex shrink-0 items-center justify-between gap-2 lg:justify-end">
                <div className="min-w-[82px] text-right">
                    {subject.calculating_fees ? (
                        <span className="inline-flex items-center justify-end gap-1 text-xs text-muted-foreground">
                            <Loader2 className="size-3 animate-spin" />
                            <span>…</span>
                        </span>
                    ) : subject.exclude_from_tuition ? (
                        <span className="text-xs font-medium text-muted-foreground">
                            Excluded
                        </span>
                    ) : (
                        <span className="text-xs font-semibold text-foreground tabular-nums">
                            {formatCurrency(lineTotal)}
                        </span>
                    )}
                </div>

                <Button
                    type="button"
                    variant="ghost"
                    size="icon-xs"
                    disabled={disabled}
                    onClick={() => onRemove(subject.subject_id)}
                    title={removeTooltip}
                    aria-label={removeTooltip}
                    className={cn(
                        "shrink-0 transition-colors",
                        subject.has_grades
                            ? "text-warning-foreground hover:bg-destructive/10 hover:text-destructive dark:text-warning"
                            : "text-muted-foreground hover:bg-destructive/10 hover:text-destructive",
                    )}
                >
                    <Trash2 className="size-3.5" />
                </Button>
            </div>
        </div>
    );
}
