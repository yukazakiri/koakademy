import { Badge } from "@/components/reui/badge";
import {
    Frame,
    FrameDescription,
    FrameHeader,
    FramePanel,
    FrameTitle,
} from "@/components/reui/frame";
import { IconTile } from "@/components/reui/icon-tile";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { cn } from "@/lib/utils";
import {
    BookOpen,
    Check,
    Loader2,
    Plus,
    Search,
    Sparkles,
    X,
} from "lucide-react";
import { useEffect, useMemo, useRef, useState } from "react";
import type { SubjectOption } from "./types";

type PickerTab = "recommended" | "all" | "has_classes" | "retakes";

export interface SubjectPickerPanelProps {
    subjectOptions: SubjectOption[];
    enrolledSubjectIds: Set<number>;
    loadingSubjects: boolean;
    subjectSearch: string;
    onSubjectSearchChange: (value: string) => void;
    onAddSubject: (subject: SubjectOption) => void;
    onLoadRecommendedBlock: () => void;
    isTesdaPathway: boolean;
    targetAcademicYear: number;
    targetSemester: number;
    disabled?: boolean;
}

export function SubjectPickerPanel({
    subjectOptions,
    enrolledSubjectIds,
    loadingSubjects,
    subjectSearch,
    onSubjectSearchChange,
    onAddSubject,
    onLoadRecommendedBlock,
    isTesdaPathway,
    targetAcademicYear,
    targetSemester,
    disabled = false,
}: SubjectPickerPanelProps) {
    const [activeTab, setActiveTab] = useState<PickerTab>("recommended");
    const searchInputRef = useRef<HTMLInputElement | null>(null);

    // Focus subject search on "/" keyboard shortcut when not typing in an input
    useEffect(() => {
        const handleKeyDown = (event: KeyboardEvent) => {
            if (event.key !== "/" || event.metaKey || event.ctrlKey || event.altKey) {
                return;
            }
            const target = event.target as HTMLElement | null;
            const tagName = target?.tagName?.toLowerCase();
            if (
                tagName === "input" ||
                tagName === "textarea" ||
                tagName === "select" ||
                target?.isContentEditable
            ) {
                return;
            }
            if (disabled) return;
            event.preventDefault();
            searchInputRef.current?.focus();
        };

        window.addEventListener("keydown", handleKeyDown);
        return () => window.removeEventListener("keydown", handleKeyDown);
    }, [disabled]);

    const tabCounts = useMemo(() => {
        let recommended = 0;
        let hasClasses = 0;
        let retakes = 0;
        let recommendedPending = 0;

        for (const sub of subjectOptions) {
            if (sub.recommended) {
                recommended += 1;
                if (!enrolledSubjectIds.has(sub.id)) {
                    recommendedPending += 1;
                }
            }
            if (sub.has_classes) hasClasses += 1;
            if (sub.is_failed_retake) retakes += 1;
        }

        return {
            recommended,
            all: subjectOptions.length,
            has_classes: hasClasses,
            retakes,
            recommendedPending,
        };
    }, [subjectOptions, enrolledSubjectIds]);

    const filteredSubjects = useMemo(() => {
        switch (activeTab) {
            case "recommended":
                return subjectOptions.filter((s) => Boolean(s.recommended));
            case "has_classes":
                return subjectOptions.filter((s) => s.has_classes);
            case "retakes":
                return subjectOptions.filter((s) => Boolean(s.is_failed_retake));
            case "all":
            default:
                return subjectOptions;
        }
    }, [subjectOptions, activeTab]);

    const tabs: { id: PickerTab; label: string; count: number }[] = [
        { id: "recommended", label: "Recommended", count: tabCounts.recommended },
        { id: "all", label: "All", count: tabCounts.all },
        { id: "has_classes", label: "Has Classes", count: tabCounts.has_classes },
        { id: "retakes", label: "Retakes", count: tabCounts.retakes },
    ];

    const curriculumSubtitle = isTesdaPathway
        ? "TESDA Qualification Curriculum"
        : `Year ${targetAcademicYear} · Sem ${targetSemester} Curriculum`;

    return (
        <Frame spacing="sm" className="min-w-0 w-full">
            <FrameHeader className="flex-row flex-wrap items-center justify-between gap-2">
                <div className="flex min-w-0 items-center gap-2.5">
                    <IconTile
                        variant="soft"
                        size="xs"
                        className="shrink-0 text-primary"
                    >
                        <BookOpen />
                    </IconTile>
                    <div className="min-w-0">
                        <FrameTitle className="truncate">
                            Subject Catalog
                        </FrameTitle>
                        <FrameDescription className="truncate text-xs">
                            {curriculumSubtitle}
                        </FrameDescription>
                    </div>
                </div>

                <Button
                    type="button"
                    size="xs"
                    variant={
                        tabCounts.recommendedPending > 0 ? "default" : "outline"
                    }
                    disabled={
                        disabled ||
                        loadingSubjects ||
                        tabCounts.recommendedPending === 0
                    }
                    onClick={onLoadRecommendedBlock}
                    className="shrink-0 gap-1.5"
                >
                    <Sparkles className="size-3" />
                    <span>
                        Load Recommended ({tabCounts.recommendedPending})
                    </span>
                </Button>
            </FrameHeader>

            <FramePanel className="min-w-0 p-0">
                {/* Search and filter toolbar */}
                <div className="space-y-2.5 border-b border-border/70 bg-muted/20 p-3">
                    <div className="relative min-w-0">
                        <Search className="pointer-events-none absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            ref={searchInputRef}
                            type="text"
                            value={subjectSearch}
                            onChange={(e) =>
                                onSubjectSearchChange(e.target.value)
                            }
                            placeholder="Search code or title…"
                            disabled={disabled}
                            className="h-8 bg-background pr-12 pl-8 text-xs"
                            aria-label="Search curriculum subjects"
                        />
                        <div className="absolute top-1/2 right-2 flex -translate-y-1/2 items-center gap-1">
                            {loadingSubjects && (
                                <Loader2 className="size-3.5 animate-spin text-muted-foreground" />
                            )}
                            {subjectSearch ? (
                                <button
                                    type="button"
                                    onClick={() => onSubjectSearchChange("")}
                                    disabled={disabled}
                                    className="inline-flex size-5 items-center justify-center rounded text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                                    aria-label="Clear search"
                                >
                                    <X className="size-3" />
                                </button>
                            ) : (
                                <kbd className="pointer-events-none inline-flex h-5 min-w-5 items-center justify-center rounded border border-border bg-muted/70 px-1 font-mono text-[10px] font-medium text-muted-foreground">
                                    /
                                </kbd>
                            )}
                        </div>
                    </div>

                    <div
                        role="tablist"
                        aria-label="Subject catalog filters"
                        className="flex flex-wrap items-center gap-1"
                    >
                        {tabs.map((tab) => {
                            const isActive = activeTab === tab.id;
                            return (
                                <button
                                    key={tab.id}
                                    type="button"
                                    role="tab"
                                    aria-selected={isActive}
                                    onClick={() => setActiveTab(tab.id)}
                                    className={cn(
                                        "inline-flex items-center gap-1.5 rounded-md border px-2 py-1 text-[11px] font-medium transition-colors outline-none focus-visible:ring-2 focus-visible:ring-ring",
                                        isActive
                                            ? "border-primary/30 bg-primary/10 text-primary dark:border-primary/35 dark:bg-primary/15"
                                            : "border-transparent bg-transparent text-muted-foreground hover:bg-muted hover:text-foreground",
                                    )}
                                >
                                    <span>{tab.label}</span>
                                    <span
                                        className={cn(
                                            "rounded px-1 text-[10px] tabular-nums",
                                            isActive
                                                ? "bg-primary/15 text-primary"
                                                : "bg-muted text-muted-foreground",
                                        )}
                                    >
                                        {tab.count}
                                    </span>
                                </button>
                            );
                        })}
                    </div>
                </div>

                {/* Scrollable subject catalog list */}
                <div className="max-h-64 overflow-y-auto divide-y divide-border/60">
                    {loadingSubjects && subjectOptions.length === 0 ? (
                        <div className="flex items-center justify-center gap-2 px-4 py-10 text-xs text-muted-foreground">
                            <Loader2 className="size-4 animate-spin" />
                            <span>Loading curriculum subjects…</span>
                        </div>
                    ) : filteredSubjects.length === 0 ? (
                        <div className="flex flex-col items-center justify-center gap-2 px-4 py-8 text-center">
                            <p className="text-xs font-medium text-foreground">
                                No matching subjects in{" "}
                                {tabs.find((t) => t.id === activeTab)?.label}
                            </p>
                            <p className="text-[11px] text-muted-foreground">
                                {subjectSearch
                                    ? `No subjects matched "${subjectSearch}".`
                                    : activeTab === "recommended"
                                      ? "No recommended subjects for the selected term."
                                      : "Try another filter or broaden your search."}
                            </p>
                            {activeTab !== "all" && tabCounts.all > 0 && (
                                <Button
                                    type="button"
                                    size="xs"
                                    variant="outline"
                                    onClick={() => setActiveTab("all")}
                                    className="mt-1"
                                >
                                    View all ({tabCounts.all})
                                </Button>
                            )}
                        </div>
                    ) : (
                        filteredSubjects.map((subject) => {
                            const isEnrolled = enrolledSubjectIds.has(
                                subject.id,
                            );
                            const lecUnits = Number(subject.lecture) || 0;
                            const labUnits = Number(subject.laboratory) || 0;
                            const totalUnits = lecUnits + labUnits;
                            const hasChecklistBadges =
                                Boolean(subject.recommended) ||
                                Boolean(subject.already_passed) ||
                                Boolean(subject.is_failed_retake) ||
                                subject.prerequisite_satisfied === false;

                            return (
                                <div
                                    key={subject.id}
                                    className={cn(
                                        "flex min-w-0 items-center justify-between gap-3 px-3 py-2.5 transition-colors hover:bg-muted/35",
                                        isEnrolled && "bg-muted/20",
                                    )}
                                >
                                    <div className="min-w-0 flex-1 space-y-0.5">
                                        <div className="flex min-w-0 flex-wrap items-center gap-1.5">
                                            <span className="shrink-0 font-mono text-xs font-semibold text-foreground">
                                                {subject.code}
                                            </span>

                                            {!isTesdaPathway &&
                                            (subject.academic_year ||
                                                subject.semester) ? (
                                                <Badge
                                                    variant="outline"
                                                    size="xs"
                                                    className="tabular-nums"
                                                >
                                                    Y
                                                    {subject.academic_year ??
                                                        "?"}{" "}
                                                    · S
                                                    {subject.semester ?? "?"}
                                                </Badge>
                                            ) : isTesdaPathway ? (
                                                <Badge
                                                    variant="outline"
                                                    size="xs"
                                                >
                                                    TESDA
                                                </Badge>
                                            ) : null}

                                            <span className="text-[11px] text-muted-foreground tabular-nums">
                                                {totalUnits}u ({lecUnits}L/
                                                {labUnits}Lab)
                                            </span>

                                            {subject.has_classes ? (
                                                <Badge
                                                    variant="success-light"
                                                    size="xs"
                                                >
                                                    Has classes
                                                </Badge>
                                            ) : (
                                                <Badge
                                                    variant="secondary"
                                                    size="xs"
                                                >
                                                    No classes
                                                </Badge>
                                            )}
                                        </div>

                                        <p
                                            className="truncate text-xs text-foreground/90"
                                            title={subject.title}
                                        >
                                            {subject.title}
                                        </p>

                                        {hasChecklistBadges && (
                                            <div className="flex flex-wrap items-center gap-1 pt-0.5">
                                                {subject.recommended && (
                                                    <Badge
                                                        variant="primary-light"
                                                        size="xs"
                                                    >
                                                        Recommended
                                                    </Badge>
                                                )}

                                                {subject.already_passed && (
                                                    <Badge
                                                        variant="secondary"
                                                        size="xs"
                                                    >
                                                        Already passed
                                                    </Badge>
                                                )}

                                                {subject.is_failed_retake && (
                                                    <Badge
                                                        variant="destructive-light"
                                                        size="xs"
                                                    >
                                                        Retake
                                                    </Badge>
                                                )}

                                                {subject.prerequisite_satisfied ===
                                                    false && (
                                                    <Badge
                                                        variant="warning-outline"
                                                        size="xs"
                                                        className="max-w-full"
                                                        title={`Missing prereq: ${subject.missing_prerequisite || "Required"}`}
                                                    >
                                                        <span className="truncate">
                                                            Missing prereq:{" "}
                                                            {subject.missing_prerequisite ||
                                                                "Required"}
                                                        </span>
                                                    </Badge>
                                                )}
                                            </div>
                                        )}
                                    </div>

                                    <div className="shrink-0">
                                        {isEnrolled ? (
                                            <Button
                                                type="button"
                                                size="xs"
                                                variant="secondary"
                                                disabled
                                                className="gap-1 text-success-foreground dark:text-success"
                                            >
                                                <Check className="size-3" />
                                                <span>Added</span>
                                            </Button>
                                        ) : (
                                            <Button
                                                type="button"
                                                size="xs"
                                                variant="outline"
                                                disabled={disabled}
                                                onClick={() =>
                                                    onAddSubject(subject)
                                                }
                                                className="gap-1"
                                            >
                                                <Plus className="size-3" />
                                                <span>Add</span>
                                            </Button>
                                        )}
                                    </div>
                                </div>
                            );
                        })
                    )}
                </div>
            </FramePanel>
        </Frame>
    );
}
