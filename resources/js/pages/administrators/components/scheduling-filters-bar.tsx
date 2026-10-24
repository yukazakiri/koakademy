import { useState } from "react";

import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { Command, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList } from "@/components/ui/command";
import { Input } from "@/components/ui/input";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { cn } from "@/lib/utils";
import { BookOpen, Building2, Check, ChevronsUpDown, GraduationCap, Layers, LayoutGrid, Loader2, Search, User as UserIcon, X } from "lucide-react";

type CourseOption = { id: number; code: string; title: string };
type RoomOption = { id: number; name: string; class_code: string | null; capacity?: number; type?: string };
type FacultyOption = { id: string; name: string; department: string | null };
type StudentSearchResult = { id: number; student_id: number; name: string };

export type PlanningLens = "overview" | "cohort" | "room" | "faculty";

type SchedulingFiltersBarProps = {
    planningLens?: PlanningLens;
    onPlanningLensChange?: (lens: PlanningLens) => void;
    search: string;
    onSearchChange: (value: string) => void;
    courseFilter: string;
    onCourseFilterChange: (value: string) => void;
    yearFilter: string;
    onYearFilterChange: (value: string) => void;
    sectionFilter?: string;
    onSectionFilterChange?: (value: string) => void;
    roomFilter: string;
    onRoomFilterChange: (value: string) => void;
    facultyFilter: string;
    onFacultyFilterChange: (value: string) => void;
    studentQuery: string;
    onStudentQueryChange: (value: string) => void;
    isSearchingStudent: boolean;
    studentResults: StudentSearchResult[];
    onSelectStudent: (student: StudentSearchResult) => void;
    hasFilters: boolean;
    onClearFilters: () => void;
    filteredCount: number;
    totalCount: number;
    isLoadingStudent: boolean;
    availableCourses: CourseOption[];
    availableYearLevels: string[];
    availableSections?: string[];
    availableRooms: RoomOption[];
    availableFaculty: FacultyOption[];
};

export default function SchedulingFiltersBar({
    planningLens = "overview",
    onPlanningLensChange,
    search,
    onSearchChange,
    courseFilter,
    onCourseFilterChange,
    yearFilter,
    onYearFilterChange,
    sectionFilter = "all",
    onSectionFilterChange,
    roomFilter,
    onRoomFilterChange,
    facultyFilter,
    onFacultyFilterChange,
    studentQuery,
    onStudentQueryChange,
    isSearchingStudent,
    studentResults,
    onSelectStudent,
    hasFilters,
    onClearFilters,
    filteredCount,
    totalCount,
    isLoadingStudent,
    availableCourses,
    availableYearLevels,
    availableSections = ["A", "B", "C", "D"],
    availableRooms,
    availableFaculty,
}: SchedulingFiltersBarProps) {
    const [roomFilterOpen, setRoomFilterOpen] = useState(false);
    const [facultyFilterOpen, setFacultyFilterOpen] = useState(false);
    const selectedRoom = availableRooms.find((room) => String(room.id) === roomFilter);
    const selectedFaculty = availableFaculty.find((faculty) => faculty.id === facultyFilter);

    return (
        <Card className="bg-muted/30 border shadow-none">
            <CardContent className="space-y-3 p-3">
                {onPlanningLensChange && (
                    <div className="flex flex-wrap items-center justify-between gap-2 border-b pb-2.5">
                        <div className="flex flex-wrap items-center gap-1.5">
                            <span className="text-muted-foreground mr-1 text-xs font-medium">Planning Lens:</span>
                            <Button
                                type="button"
                                size="sm"
                                variant={planningLens === "overview" ? "default" : "outline"}
                                className="h-7 px-2.5 text-xs"
                                onClick={() => onPlanningLensChange("overview")}
                            >
                                <LayoutGrid className="mr-1.5 h-3 w-3" /> Master Overview
                            </Button>
                            <Button
                                type="button"
                                size="sm"
                                variant={planningLens === "cohort" ? "default" : "outline"}
                                className="h-7 px-2.5 text-xs"
                                onClick={() => onPlanningLensChange("cohort")}
                            >
                                <Layers className="mr-1.5 h-3 w-3" /> By Cohort / Block Section
                            </Button>
                            <Button
                                type="button"
                                size="sm"
                                variant={planningLens === "room" ? "default" : "outline"}
                                className="h-7 px-2.5 text-xs"
                                onClick={() => onPlanningLensChange("room")}
                            >
                                <Building2 className="mr-1.5 h-3 w-3" /> By Room
                            </Button>
                            <Button
                                type="button"
                                size="sm"
                                variant={planningLens === "faculty" ? "default" : "outline"}
                                className="h-7 px-2.5 text-xs"
                                onClick={() => onPlanningLensChange("faculty")}
                            >
                                <UserIcon className="mr-1.5 h-3 w-3" /> By Faculty
                            </Button>
                        </div>

                        {planningLens === "cohort" && (
                            <span className="text-primary text-xs font-medium">
                                Tip: Select a Course, Year Level, and Section (A–D) to plan one block section&apos;s conflict-free week.
                            </span>
                        )}
                        {planningLens === "room" && (
                            <span className="text-primary text-xs font-medium">
                                Tip: Select a Room below to inspect its weekly utilization and open time windows.
                            </span>
                        )}
                        {planningLens === "faculty" && (
                            <span className="text-primary text-xs font-medium">
                                Tip: Select an Instructor below to review teaching load and avoid double-booking.
                            </span>
                        )}
                    </div>
                )}

                <div className="flex flex-wrap items-center gap-2">
                    <div className="relative min-w-[180px] flex-1">
                        <Search className="text-muted-foreground absolute top-2.5 left-2.5 h-3.5 w-3.5" />
                        <Input
                            placeholder="Search subjects, faculty..."
                            className="h-9 pl-8 text-sm"
                            value={search}
                            onChange={(e) => onSearchChange(e.target.value)}
                        />
                    </div>

                    <Select value={courseFilter} onValueChange={onCourseFilterChange}>
                        <SelectTrigger
                            className={cn(
                                "h-9 w-[150px] text-xs",
                                planningLens === "cohort" && courseFilter === "all" && "border-primary ring-primary/20 ring-2",
                            )}
                        >
                            <div className="flex items-center gap-1.5">
                                <BookOpen className="text-muted-foreground h-3 w-3" />
                                <SelectValue placeholder="Course" />
                            </div>
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Courses</SelectItem>
                            {availableCourses.map((c) => (
                                <SelectItem key={c.id} value={String(c.id)}>
                                    {c.code}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <Select value={yearFilter} onValueChange={onYearFilterChange}>
                        <SelectTrigger
                            className={cn(
                                "h-9 w-[130px] text-xs",
                                planningLens === "cohort" && yearFilter === "all" && "border-primary ring-primary/20 ring-2",
                            )}
                        >
                            <div className="flex items-center gap-1.5">
                                <GraduationCap className="text-muted-foreground h-3 w-3" />
                                <SelectValue placeholder="Year" />
                            </div>
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Years</SelectItem>
                            {availableYearLevels.map((y) => (
                                <SelectItem key={y} value={y}>
                                    {y}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    {onSectionFilterChange && (
                        <Select value={sectionFilter} onValueChange={onSectionFilterChange}>
                            <SelectTrigger
                                className={cn(
                                    "h-9 w-[125px] text-xs",
                                    planningLens === "cohort" && sectionFilter === "all" && "border-primary ring-primary/20 ring-2",
                                )}
                            >
                                <div className="flex items-center gap-1.5">
                                    <Layers className="text-muted-foreground h-3 w-3" />
                                    <SelectValue placeholder="Section" />
                                </div>
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All Sections</SelectItem>
                                {availableSections.map((sec) => (
                                    <SelectItem key={sec} value={sec}>
                                        Section {sec}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    )}

                    <Popover open={roomFilterOpen} onOpenChange={setRoomFilterOpen}>
                        <PopoverTrigger asChild>
                            <Button
                                type="button"
                                variant="outline"
                                role="combobox"
                                aria-expanded={roomFilterOpen}
                                className={cn(
                                    "h-9 w-[160px] justify-between px-3 text-xs font-normal",
                                    planningLens === "room" && roomFilter === "all" && "border-primary ring-primary/20 ring-2",
                                )}
                            >
                                <span className="flex min-w-0 items-center gap-1.5">
                                    <Building2 className="text-muted-foreground h-3 w-3 shrink-0" />
                                    <span className="truncate">{selectedRoom?.name ?? "All Rooms"}</span>
                                </span>
                                <ChevronsUpDown className="text-muted-foreground ml-2 h-3 w-3 shrink-0 opacity-50" />
                            </Button>
                        </PopoverTrigger>
                        <PopoverContent className="w-[240px] p-0" align="start">
                            <Command>
                                <CommandInput placeholder="Search rooms..." />
                                <CommandList>
                                    <CommandEmpty>No rooms found.</CommandEmpty>
                                    <CommandGroup>
                                        <CommandItem
                                            value="all rooms"
                                            onSelect={() => {
                                                onRoomFilterChange("all");
                                                setRoomFilterOpen(false);
                                            }}
                                        >
                                            <Check className={cn("h-3.5 w-3.5", roomFilter === "all" ? "opacity-100" : "opacity-0")} />
                                            All Rooms
                                        </CommandItem>
                                        {availableRooms.map((room) => (
                                            <CommandItem
                                                key={room.id}
                                                value={`${room.name} ${room.class_code ?? ""}`}
                                                onSelect={() => {
                                                    onRoomFilterChange(String(room.id));
                                                    setRoomFilterOpen(false);
                                                }}
                                            >
                                                <Check className={cn("h-3.5 w-3.5", roomFilter === String(room.id) ? "opacity-100" : "opacity-0")} />
                                                <span className="truncate">{room.name}</span>
                                                <span className="text-muted-foreground ml-auto text-[10px]">
                                                    {room.capacity ? `${room.capacity} seats` : (room.class_code ?? "")}
                                                </span>
                                            </CommandItem>
                                        ))}
                                    </CommandGroup>
                                </CommandList>
                            </Command>
                        </PopoverContent>
                    </Popover>

                    <Popover open={facultyFilterOpen} onOpenChange={setFacultyFilterOpen}>
                        <PopoverTrigger asChild>
                            <Button
                                type="button"
                                variant="outline"
                                role="combobox"
                                aria-expanded={facultyFilterOpen}
                                className={cn(
                                    "h-9 w-[180px] justify-between px-3 text-xs font-normal",
                                    planningLens === "faculty" && facultyFilter === "all" && "border-primary ring-primary/20 ring-2",
                                )}
                            >
                                <span className="flex min-w-0 items-center gap-1.5">
                                    <UserIcon className="text-muted-foreground h-3 w-3 shrink-0" />
                                    <span className="truncate">{selectedFaculty?.name ?? "All Faculty"}</span>
                                </span>
                                <ChevronsUpDown className="text-muted-foreground ml-2 h-3 w-3 shrink-0 opacity-50" />
                            </Button>
                        </PopoverTrigger>
                        <PopoverContent className="w-[260px] p-0" align="start">
                            <Command>
                                <CommandInput placeholder="Search faculty..." />
                                <CommandList>
                                    <CommandEmpty>No faculty found.</CommandEmpty>
                                    <CommandGroup>
                                        <CommandItem
                                            value="all faculty"
                                            onSelect={() => {
                                                onFacultyFilterChange("all");
                                                setFacultyFilterOpen(false);
                                            }}
                                        >
                                            <Check className={cn("h-3.5 w-3.5", facultyFilter === "all" ? "opacity-100" : "opacity-0")} />
                                            All Faculty
                                        </CommandItem>
                                        {availableFaculty.map((faculty) => (
                                            <CommandItem
                                                key={faculty.id}
                                                value={`${faculty.name} ${faculty.department ?? ""}`}
                                                onSelect={() => {
                                                    onFacultyFilterChange(faculty.id);
                                                    setFacultyFilterOpen(false);
                                                }}
                                            >
                                                <Check className={cn("h-3.5 w-3.5", facultyFilter === faculty.id ? "opacity-100" : "opacity-0")} />
                                                <span className="truncate">{faculty.name}</span>
                                                {faculty.department && (
                                                    <span className="text-muted-foreground ml-auto text-xs">{faculty.department}</span>
                                                )}
                                            </CommandItem>
                                        ))}
                                    </CommandGroup>
                                </CommandList>
                            </Command>
                        </PopoverContent>
                    </Popover>

                    <div className="relative min-w-[170px]">
                        <GraduationCap className="text-muted-foreground absolute top-2.5 left-2.5 h-3.5 w-3.5" />
                        <Input
                            placeholder="Find student..."
                            className="h-9 pl-8 text-sm"
                            value={studentQuery}
                            onChange={(e) => onStudentQueryChange(e.target.value)}
                        />
                        {isSearchingStudent && <Loader2 className="text-muted-foreground absolute top-2.5 right-2.5 h-3.5 w-3.5 animate-spin" />}

                        {studentResults.length > 0 && (
                            <div className="bg-popover absolute top-full right-0 left-0 z-50 mt-1 max-h-[200px] overflow-auto rounded-lg border shadow-lg">
                                {studentResults.map((s) => (
                                    <button
                                        key={s.id}
                                        className="hover:bg-muted flex w-full items-center justify-between px-3 py-2 text-left text-sm"
                                        onClick={() => onSelectStudent(s)}
                                    >
                                        <span className="font-medium">{s.name}</span>
                                        <span className="text-muted-foreground text-xs">ID: {s.student_id}</span>
                                    </button>
                                ))}
                            </div>
                        )}
                    </div>

                    {hasFilters && (
                        <Button variant="ghost" size="sm" onClick={onClearFilters} className="text-muted-foreground h-9 px-2.5 text-xs">
                            <X className="mr-1 h-3 w-3" /> Clear
                        </Button>
                    )}
                </div>

                {hasFilters && (
                    <div className="text-muted-foreground flex items-center gap-1.5 text-xs">
                        Showing <span className="text-foreground font-semibold">{filteredCount}</span> of {totalCount} classes
                        {isLoadingStudent && <Loader2 className="ml-2 h-3 w-3 animate-spin" />}
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
