import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Filter, GraduationCap, Loader2, RotateCcw, Search, X } from "lucide-react";
import { useState } from "react";
import { emptyPlannerFilters, type PlannerFilters } from "../../components/scheduling-filters-bar";
import type { SchedulingAnalyticsProps } from "../../components/scheduling-types";
import type { ScheduleStudioController } from "../hooks/use-schedule-studio";

type StudioFiltersStripProps = {
    studio: ScheduleStudioController;
    filterOptions: SchedulingAnalyticsProps["filters"];
};

export default function StudioFiltersStrip({ studio, filterOptions }: StudioFiltersStripProps) {
    const [showStudentLookup, setShowStudentLookup] = useState(false);

    const changeFilter = (key: keyof PlannerFilters, next: string) => {
        studio.setViewFilters((prev) => ({
            ...prev,
            [key]: next,
            ...(key === "course" || key === "year" ? { section: "all" } : {}),
        }));
    };

    const hasActiveFilters =
        Object.values(studio.viewFilters).some((v) => v !== "all") || Boolean(studio.activeStudent);

    return (
        <section
            aria-label="Schedule filters"
            className="bg-card border-border/70 rounded-xl border px-3 py-2"
        >
            <div className="flex flex-wrap items-center gap-2">
                <span className="text-muted-foreground inline-flex items-center gap-1 text-xs font-medium">
                    <Filter className="size-3.5" aria-hidden="true" />
                    <span className="hidden sm:inline">Filter:</span>
                </span>

                {/* Program */}
                <Select value={studio.viewFilters.course} onValueChange={(v) => changeFilter("course", v)}>
                    <SelectTrigger className="h-8 w-[140px] text-xs" aria-label="Filter by program">
                        <SelectValue placeholder="All programs" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All programs</SelectItem>
                        {filterOptions.available_courses.map((course) => (
                            <SelectItem key={course.id} value={String(course.id)}>
                                {course.code}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>

                {/* Year */}
                <Select value={studio.viewFilters.year} onValueChange={(v) => changeFilter("year", v)}>
                    <SelectTrigger className="h-8 w-[124px] text-xs" aria-label="Filter by year level">
                        <SelectValue placeholder="All years" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All years</SelectItem>
                        {filterOptions.available_year_levels.map((year) => (
                            <SelectItem key={year} value={year}>
                                {year}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>

                {/* Section */}
                <Select value={studio.viewFilters.section} onValueChange={(v) => changeFilter("section", v)}>
                    <SelectTrigger className="h-8 w-[116px] text-xs" aria-label="Filter by section">
                        <SelectValue placeholder="All sections" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All sections</SelectItem>
                        {studio.availableSections.map((sec) => (
                            <SelectItem key={sec} value={sec}>
                                Section {sec}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>

                {/* Room */}
                <Select value={studio.viewFilters.room} onValueChange={(v) => changeFilter("room", v)}>
                    <SelectTrigger className="h-8 w-[138px] text-xs" aria-label="Filter by room">
                        <SelectValue placeholder="All rooms" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All rooms</SelectItem>
                        {filterOptions.available_rooms.map((room) => (
                            <SelectItem key={room.id} value={String(room.id)}>
                                {room.name}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>

                {/* Faculty */}
                <Select value={studio.viewFilters.faculty} onValueChange={(v) => changeFilter("faculty", v)}>
                    <SelectTrigger className="h-8 w-[150px] text-xs" aria-label="Filter by instructor">
                        <SelectValue placeholder="All instructors" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All instructors</SelectItem>
                        {filterOptions.available_faculty.map((f) => (
                            <SelectItem key={f.id} value={f.id}>
                                {f.name}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>

                {/* Student Timetable Lookup */}
                <div className="relative ml-auto flex items-center gap-1.5">
                    {studio.activeStudent ? (
                        <Badge variant="secondary" className="h-8 gap-1.5 rounded-md px-2.5 text-xs">
                            <GraduationCap className="size-3.5" />
                            <span>
                                {studio.activeStudent.student.name} ({studio.activeStudent.schedule.length} classes)
                            </span>
                            <button
                                type="button"
                                onClick={studio.clearStudent}
                                className="hover:bg-muted rounded p-0.5"
                                aria-label="Clear student filter"
                            >
                                <X className="size-3" />
                            </button>
                        </Badge>
                    ) : showStudentLookup ? (
                        <div className="relative w-56">
                            <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2" />
                            <Input
                                type="search"
                                value={studio.studentQuery}
                                onChange={(e) => studio.setStudentQuery(e.target.value)}
                                placeholder="Search student name or ID…"
                                className="h-8 pr-7 pl-8 text-xs"
                                autoFocus
                            />
                            <button
                                type="button"
                                onClick={() => {
                                    setShowStudentLookup(false);
                                    studio.setStudentQuery("");
                                }}
                                className="text-muted-foreground hover:text-foreground absolute top-1/2 right-2 -translate-y-1/2"
                                aria-label="Close student search"
                            >
                                <X className="size-3.5" />
                            </button>

                            {(studio.searchingStudent || studio.loadingStudent || studio.students.length > 0) && (
                                <div className="bg-popover border-border absolute top-full right-0 z-30 mt-1 max-h-56 w-64 overflow-y-auto rounded-lg border p-1 shadow-lg">
                                    {studio.searchingStudent || studio.loadingStudent ? (
                                        <div className="text-muted-foreground flex items-center gap-2 px-2.5 py-2 text-xs">
                                            <Loader2 className="size-3.5 animate-spin" /> Searching…
                                        </div>
                                    ) : (
                                        studio.students.map((student) => (
                                            <button
                                                key={student.id}
                                                type="button"
                                                onClick={() => {
                                                    void studio.selectStudent(student);
                                                    setShowStudentLookup(false);
                                                }}
                                                className="hover:bg-muted flex w-full flex-col rounded-md px-2.5 py-1.5 text-left text-xs"
                                            >
                                                <span className="font-medium">{student.name}</span>
                                                <span className="text-muted-foreground text-[11px] tabular-nums">ID {student.student_id}</span>
                                            </button>
                                        ))
                                    )}
                                </div>
                            )}
                        </div>
                    ) : (
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            className="h-8 gap-1.5 text-xs"
                            onClick={() => setShowStudentLookup(true)}
                        >
                            <GraduationCap className="size-3.5" />
                            <span>Student lookup</span>
                        </Button>
                    )}

                    {hasActiveFilters && (
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            className="h-8 gap-1 px-2 text-xs"
                            onClick={() => {
                                studio.setViewFilters(emptyPlannerFilters);
                                studio.clearStudent();
                                studio.setStudentQuery("");
                            }}
                        >
                            <RotateCcw className="size-3" />
                            Reset
                        </Button>
                    )}
                </div>
            </div>
        </section>
    );
}
