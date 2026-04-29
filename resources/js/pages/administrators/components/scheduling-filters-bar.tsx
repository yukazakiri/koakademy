import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { BookOpen, Building2, GraduationCap, Loader2, Search, User as UserIcon, X } from "lucide-react";

type CourseOption = { id: number; code: string; title: string };
type RoomOption = { id: number; name: string; class_code: string | null };
type FacultyOption = { id: string; name: string; department: string | null };
type StudentSearchResult = { id: number; student_id: number; name: string };

type SchedulingFiltersBarProps = {
    search: string;
    onSearchChange: (value: string) => void;
    courseFilter: string;
    onCourseFilterChange: (value: string) => void;
    yearFilter: string;
    onYearFilterChange: (value: string) => void;
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
    availableRooms: RoomOption[];
    availableFaculty: FacultyOption[];
};

export default function SchedulingFiltersBar({
    search,
    onSearchChange,
    courseFilter,
    onCourseFilterChange,
    yearFilter,
    onYearFilterChange,
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
    availableRooms,
    availableFaculty,
}: SchedulingFiltersBarProps) {
    return (
        <Card className="bg-muted/30 border shadow-none">
            <CardContent className="p-3">
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
                        <SelectTrigger className="h-9 w-[150px] text-xs">
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
                        <SelectTrigger className="h-9 w-[130px] text-xs">
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

                    <Select value={roomFilter} onValueChange={onRoomFilterChange}>
                        <SelectTrigger className="h-9 w-[140px] text-xs">
                            <div className="flex items-center gap-1.5">
                                <Building2 className="text-muted-foreground h-3 w-3" />
                                <SelectValue placeholder="Room" />
                            </div>
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Rooms</SelectItem>
                            {availableRooms.map((r) => (
                                <SelectItem key={r.id} value={String(r.id)}>
                                    {r.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <Select value={facultyFilter} onValueChange={onFacultyFilterChange}>
                        <SelectTrigger className="h-9 w-[160px] text-xs">
                            <div className="flex items-center gap-1.5">
                                <UserIcon className="text-muted-foreground h-3 w-3" />
                                <SelectValue placeholder="Faculty" />
                            </div>
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Faculty</SelectItem>
                            {availableFaculty.map((f) => (
                                <SelectItem key={f.id} value={f.id}>
                                    {f.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

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
                    <div className="text-muted-foreground mt-2 flex items-center gap-1.5 text-xs">
                        Showing <span className="text-foreground font-semibold">{filteredCount}</span> of {totalCount} classes
                        {isLoadingStudent && <Loader2 className="ml-2 h-3 w-3 animate-spin" />}
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
