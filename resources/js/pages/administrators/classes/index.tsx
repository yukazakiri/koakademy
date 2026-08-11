import AdminLayout from "@/components/administrators/admin-layout";
import { ClassScheduleVisualizer } from "@/components/administrators/classes/schedule-visualizer";
import { Filters, type FilterFieldConfig, type Filter as FilterType } from "@/components/reui/filters";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Checkbox } from "@/components/ui/checkbox";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { MultiSelect as SearchableMultiSelect } from "@/components/ui/multi-select";
import { ScrollArea } from "@/components/ui/scroll-area";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Separator } from "@/components/ui/separator";
import { Sheet, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle } from "@/components/ui/sheet";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { VisualRadioButton } from "@/components/ui/visual-radio-button";
import type { User } from "@/types/user";
import { Head, Link, router, useForm } from "@inertiajs/react";
import {
    BookOpen,
    CalendarIcon,
    Filter,
    GitCompare,
    GraduationCap,
    Layers,
    LayoutGrid,
    List,
    ListTodo,
    MapPin,
    Palette,
    Pencil,
    Plus,
    RotateCcw,
    Search,
    Settings2,
    Trash2,
    Users,
} from "lucide-react";
import * as React from "react";
import { useDebouncedCallback } from "use-debounce";
import { route } from "ziggy-js";
import { ClassRow, getColumns } from "./columns";
import { ClassCard } from "./components/class-card";
import { ClassCompareDialog } from "./components/class-compare-dialog";
import { ClassStats } from "./components/class-stats";
import { DeleteClassDialog } from "./components/delete-class-dialog";
import { DataTable } from "./data-table";

type SelectOption = { value: string; label: string };

type EntityOption = { id: string | number; label: string };

type ClassSchedule = {
    id?: number;
    day_of_week: string;
    start_time: string;
    end_time: string;
    room_id: number;
    room?: EntityOption | null;
};

type ClassSettingsFormData = {
    background_color: string;
    accent_color: string;
    theme: string;
    enable_announcements: boolean;
    enable_grade_visibility: boolean;
    enable_attendance_tracking: boolean;
    allow_late_submissions: boolean;
    enable_discussion_board: boolean;
    notify_students_on_schedule_changes: boolean;
    custom: Record<string, string>;
    banner_image: File | null;
};

type SelectedClass = {
    id: number;
    record_title: string;
    classification: "college" | "shs" | string;
    subject_code: string;
    subject_title: string;
    section: string;
    school_year: string;
    semester: string | number;
    academic_year: number | null;
    grade_level: string | null;
    shs_track: EntityOption | null;
    shs_strand: EntityOption | null;
    faculty: (EntityOption & { email?: string | null }) | null;
    room: EntityOption | null;
    course_codes: string[];
    course_abbreviations: string[] | null;
    course_ids: number[];
    subject_ids: number[];
    subjects: { id: number; code: string; title: string }[];
    maximum_slots: number;
    students_count: number;
    settings: {
        background_color?: string;
        accent_color?: string;
        banner_image?: string | null;
        theme?: string;
        enable_announcements?: boolean;
        enable_grade_visibility?: boolean;
        enable_attendance_tracking?: boolean;
        allow_late_submissions?: boolean;
        enable_discussion_board?: boolean;
        notify_students_on_schedule_changes?: boolean;
        custom?: Record<string, string>;
    };
    schedules: ClassSchedule[];
    enrollments: {
        id: number;
        student: {
            id: number;
            student_id: string | number | null;
            name: string;
            course: string | null;
            academic_year: string | number | null;
        } | null;
        status: string | null;
        prelim_grade: number | null;
        midterm_grade: number | null;
        finals_grade: number | null;
        total_average: number | null;
        remarks: string | null;
    }[];
    posts: {
        id: number;
        title: string;
        type: string;
        created_at: string | null;
    }[];
    filament: {
        view_url: string;
        edit_url: string;
    };
};

type ClassDialogTab = "details" | "schedule" | "settings";

interface ClassesIndexProps {
    user: User;
    filament: {
        classes: {
            index_url: string;
            create_url: string;
        };
    };
    classes: ClassRow[];
    selected_class: SelectedClass | null;
    filters: {
        search?: string | null;
        classification?: string | null;
        course_id?: number | null;
        shs_track_id?: number | null;
        shs_strand_id?: number | null;
        subject_code?: string | null;
        room_id?: number | null;
        faculty_id?: string | null;
        academic_year?: number | null;
        grade_level?: string | null;
        semester?: string | null;
        available_slots?: boolean | null;
        fully_enrolled?: boolean | null;
        per_page?: number | string | null;
        sort?: string | null;
        direction?: string | null;
    };
    options: {
        classifications: SelectOption[];
        sections: SelectOption[];
        semesters: SelectOption[];
        grade_levels: SelectOption[];
        day_of_week: SelectOption[];
        courses: EntityOption[];
        faculties: EntityOption[];
        rooms: EntityOption[];
        shs_tracks: EntityOption[];
    };
    defaults: {
        semester: string;
        school_year: string;
    };
}

function createActiveFilters(filters: ClassesIndexProps["filters"]): FilterType[] {
    const activeFilters: FilterType[] = [];

    if (filters.classification) {
        activeFilters.push({ id: "classification", field: "classification", operator: "is", values: [filters.classification] });
    }
    if (filters.course_id) activeFilters.push({ id: "course_id", field: "course_id", operator: "is", values: [filters.course_id] });
    if (filters.shs_track_id) activeFilters.push({ id: "shs_track_id", field: "shs_track_id", operator: "is", values: [filters.shs_track_id] });
    if (filters.room_id) activeFilters.push({ id: "room_id", field: "room_id", operator: "is", values: [filters.room_id] });
    if (filters.faculty_id) activeFilters.push({ id: "faculty_id", field: "faculty_id", operator: "is", values: [filters.faculty_id] });
    if (filters.academic_year) {
        activeFilters.push({ id: "academic_year", field: "academic_year", operator: "is", values: [filters.academic_year] });
    }
    if (filters.grade_level) activeFilters.push({ id: "grade_level", field: "grade_level", operator: "is", values: [filters.grade_level] });
    if (filters.semester) activeFilters.push({ id: "semester", field: "semester", operator: "is", values: [filters.semester] });
    if (filters.available_slots) activeFilters.push({ id: "available_slots", field: "available_slots", operator: "is", values: ["true"] });
    if (filters.fully_enrolled !== null && filters.fully_enrolled !== undefined) {
        activeFilters.push({
            id: "fully_enrolled",
            field: "fully_enrolled",
            operator: "is",
            values: [filters.fully_enrolled ? "true" : "false"],
        });
    }

    return activeFilters;
}

function normalizeSemester(semester: string | number): string {
    if (semester === 1 || semester === "1") return "1";
    if (semester === 2 || semester === "2") return "2";
    return String(semester);
}

function buildSubjectCodeFromSubjectOptions(selectedIds: string[], subjectOptions: { id: number; code: string }[]): string {
    const selected = new Set(selectedIds);
    const codes = subjectOptions
        .filter((s) => selected.has(String(s.id)))
        .map((s) => s.code)
        .filter(Boolean);
    return Array.from(new Set(codes)).join(", ");
}

function createDefaultSchedule(defaultRoomId: number): ClassSchedule {
    return {
        day_of_week: "Monday",
        start_time: "08:00",
        end_time: "09:00",
        room_id: defaultRoomId,
    };
}

function parseTimeToMinutes(value: string): number | null {
    const match = value.match(/^(\d{1,2}):(\d{2})$/);

    if (!match) {
        return null;
    }

    const hours = Number(match[1]);
    const minutes = Number(match[2]);

    if (!Number.isFinite(hours) || !Number.isFinite(minutes) || hours < 0 || hours > 23 || minutes < 0 || minutes > 59) {
        return null;
    }

    return hours * 60 + minutes;
}

function getInvalidScheduleIndexes(schedules: ClassSchedule[]): Set<number> {
    const invalidIndexes = new Set<number>();

    schedules.forEach((schedule, index) => {
        const start = parseTimeToMinutes(schedule.start_time);
        const end = parseTimeToMinutes(schedule.end_time);

        if (start === null || end === null || end <= start) {
            invalidIndexes.add(index);
        }
    });

    schedules.forEach((schedule, index) => {
        const start = parseTimeToMinutes(schedule.start_time);
        const end = parseTimeToMinutes(schedule.end_time);

        if (start === null || end === null || end <= start) {
            return;
        }

        schedules.slice(index + 1).forEach((otherSchedule, offset) => {
            const otherIndex = index + offset + 1;
            const otherStart = parseTimeToMinutes(otherSchedule.start_time);
            const otherEnd = parseTimeToMinutes(otherSchedule.end_time);

            if (
                schedule.day_of_week === otherSchedule.day_of_week &&
                otherStart !== null &&
                otherEnd !== null &&
                otherEnd > otherStart &&
                start < otherEnd &&
                end > otherStart
            ) {
                invalidIndexes.add(index);
                invalidIndexes.add(otherIndex);
            }
        });
    });

    return invalidIndexes;
}

function getTabForFormErrors(errors: Record<string, string>): ClassDialogTab {
    const keys = Object.keys(errors);

    if (keys.some((key) => key === "schedules" || key.startsWith("schedules."))) {
        return "schedule";
    }

    if (keys.some((key) => key === "settings" || key.startsWith("settings.") || key === "remove_banner_image")) {
        return "settings";
    }

    return "details";
}

function getFirstFormError(errors: Record<string, string>): string | null {
    const firstError = Object.values(errors).find((error) => typeof error === "string" && error.length > 0);

    return firstError ?? null;
}

function SchedulePlanner({
    schedules,
    setSchedules,
    rooms,
    dayOptions,
    defaultRoomId,
    classRoomId,
}: {
    schedules: ClassSchedule[];
    setSchedules: (nextSchedules: ClassSchedule[]) => void;
    rooms: EntityOption[];
    dayOptions: SelectOption[];
    defaultRoomId: number;
    classRoomId: number;
}) {
    React.useEffect(() => {
        if (schedules.length === 0) {
            setSchedules([createDefaultSchedule(defaultRoomId)]);
        }
    }, [defaultRoomId, schedules.length, setSchedules]);

    const selectedRoomId = Number(classRoomId || defaultRoomId);
    const selectedRoomLabel = rooms.find((room) => Number(room.id) === selectedRoomId)?.label ?? "selected class room";

    const updateScheduleAt = (index: number, patch: Partial<ClassSchedule>) => {
        setSchedules(schedules.map((schedule, scheduleIndex) => (scheduleIndex === index ? { ...schedule, ...patch } : schedule)));
    };

    const applyClassRoomToAllSchedules = () => {
        setSchedules(schedules.map((schedule) => ({ ...schedule, room: undefined, room_id: selectedRoomId })));
    };

    const addSchedule = () => {
        const sourceSchedule = schedules[schedules.length - 1] ?? createDefaultSchedule(classRoomId || defaultRoomId);
        const nextSchedule = {
            ...createDefaultSchedule(selectedRoomId),
            day_of_week: sourceSchedule.day_of_week,
            start_time: sourceSchedule.start_time,
            end_time: sourceSchedule.end_time,
            room: undefined,
            room_id: selectedRoomId,
        };

        setSchedules([...schedules, nextSchedule]);
    };

    const duplicateAt = (index: number) => {
        const nextSchedule = {
            ...schedules[index],
            id: undefined,
            room: undefined,
        };
        setSchedules([...schedules, nextSchedule]);
    };

    const removeSchedule = (index: number) => {
        if (schedules.length <= 1) {
            return;
        }

        setSchedules(schedules.filter((_, scheduleIndex) => scheduleIndex !== index));
    };

    const activeDays = new Set(schedules.map((schedule) => schedule.day_of_week).filter(Boolean)).size;
    const invalidBlocks = getInvalidScheduleIndexes(schedules).size;

    return (
        <div className="space-y-4">
            <div className="bg-card/70 flex flex-col gap-3 rounded-xl border p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between">
                <div className="space-y-1">
                    <h3 className="text-sm font-semibold">Weekly schedule</h3>
                    <p className="text-muted-foreground text-xs">Drag blocks on desktop, or right-click a block for quick edits.</p>
                </div>
                <div className="flex flex-wrap gap-2">
                    <Badge variant="secondary">
                        {schedules.length} block{schedules.length === 1 ? "" : "s"}
                    </Badge>
                    <Badge variant="secondary">
                        {activeDays} active day{activeDays === 1 ? "" : "s"}
                    </Badge>
                    {invalidBlocks > 0 ? <Badge variant="destructive">{invalidBlocks} invalid</Badge> : null}
                    <Button type="button" variant="outline" size="sm" className="h-8 text-xs" onClick={applyClassRoomToAllSchedules}>
                        <MapPin className="mr-1 h-3.5 w-3.5" />
                        Apply {selectedRoomLabel} to all
                    </Button>
                    <Button type="button" size="sm" className="h-8 text-xs" onClick={addSchedule}>
                        <Plus className="mr-1 h-3.5 w-3.5" />
                        Add block
                    </Button>
                </div>
            </div>

            {invalidBlocks > 0 ? (
                <div className="border-destructive/40 bg-destructive/5 text-destructive rounded-md border px-3 py-2 text-sm">
                    Schedule blocks on the same day cannot overlap. Adjust the invalid schedule before saving.
                </div>
            ) : null}

            <div className="bg-card/50 rounded-xl border p-2 shadow-sm sm:p-3">
                <div className="overflow-x-auto">
                    <div className="h-[720px] min-w-[860px]">
                        <ClassScheduleVisualizer
                            className="h-full min-h-0"
                            schedules={schedules}
                            rooms={rooms}
                            dayOptions={dayOptions}
                            onScheduleChange={(index, nextSchedule) => {
                                updateScheduleAt(index, nextSchedule);
                            }}
                            onDuplicateSchedule={duplicateAt}
                            onRemoveSchedule={removeSchedule}
                            canRemoveSchedule={schedules.length > 1}
                        />
                    </div>
                </div>
            </div>
        </div>
    );
}

export default function AdministratorClassesIndex({ user, classes, selected_class, filters, options, defaults }: ClassesIndexProps) {
    const [search, setSearch] = React.useState(filters.search || "");
    const [isSelectedClassLoading, setIsSelectedClassLoading] = React.useState(false);
    const [viewMode, setViewMode] = React.useState<"grid" | "list">("list");
    const [sortOption, setSortOption] = React.useState(`${filters.sort ?? "created_at"}:${filters.direction ?? "desc"}`);
    const [activeFilters, setActiveFilters] = React.useState<FilterType[]>(() => createActiveFilters(filters));
    const [isEditOpen, setIsEditOpen] = React.useState(false);
    const [isCopyOpen, setIsCopyOpen] = React.useState(false);
    const [isManageOpen, setIsManageOpen] = React.useState(false);
    const [editActiveTab, setEditActiveTab] = React.useState<ClassDialogTab>("details");
    const [copySourceId, setCopySourceId] = React.useState<number | null>(null);
    const [copySection, setCopySection] = React.useState("A");
    const [pendingDelete, setPendingDelete] = React.useState<ClassRow | null>(null);
    const [isCompareOpen, setIsCompareOpen] = React.useState(false);

    const [collegeSubjectOptions, setCollegeSubjectOptions] = React.useState<
        {
            id: number;
            label: string;
            code: string;
            title: string;
            course_id: number;
            course_code: string | null;
            academic_year: number | null;
            semester: string | null;
        }[]
    >([]);
    const [collegeSubjectsLoading, setCollegeSubjectsLoading] = React.useState(false);

    const [shsStrandOptions, setShsStrandOptions] = React.useState<EntityOption[]>([]);
    const [shsStrandsLoading, setShsStrandsLoading] = React.useState(false);

    const [shsSubjectOptions, setShsSubjectOptions] = React.useState<{ code: string; label: string; title: string }[]>([]);
    const [shsSubjectsLoading, setShsSubjectsLoading] = React.useState(false);

    const [subjectCodeTouched, setSubjectCodeTouched] = React.useState(false);

    const filteredClasses = React.useMemo(() => {
        let result = classes;

        // Apply search filter
        const searchTerm = search.trim().toLowerCase();
        if (searchTerm !== "") {
            result = result.filter((classRow) =>
                [
                    classRow.record_title,
                    classRow.subject_code,
                    classRow.subject_title,
                    classRow.section,
                    classRow.school_year,
                    String(classRow.semester ?? ""),
                    classRow.classification,
                    classRow.faculty,
                    classRow.shs_track,
                    classRow.shs_strand,
                ]
                    .filter((value): value is string => value !== null && value !== undefined)
                    .some((value) => value.toLowerCase().includes(searchTerm)),
            );
        }

        // Apply active filters
        for (const filter of activeFilters) {
            const values = Array.isArray(filter.values) ? filter.values : [filter.values];
            if (values.length === 0) continue;

            result = result.filter((classRow) => {
                switch (filter.field) {
                    case "classification":
                        return values.includes(classRow.classification);
                    case "semester":
                        return values.some((v) => String(classRow.semester) === String(v));
                    case "academic_year":
                        return values.some((v) => String(classRow.academic_year) === String(v));
                    case "grade_level":
                        return values.some((v) => classRow.grade_level === String(v));
                    case "available_slots":
                        return values.includes("true")
                            ? classRow.maximum_slots > classRow.students_count
                            : classRow.maximum_slots <= classRow.students_count;
                    case "fully_enrolled": {
                        const isFullyEnrolled = classRow.maximum_slots > 0 && classRow.students_count >= classRow.maximum_slots;
                        return values.includes("true") ? isFullyEnrolled : !isFullyEnrolled;
                    }
                    default:
                        return true;
                }
            });
        }

        return result;
    }, [classes, search, activeFilters]);

    const visibleClasses = filteredClasses;

    // Debounced server sync for full pagination and ID-based filters
    React.useEffect(() => {
        refreshClasses(search, activeFilters);
    }, [search, activeFilters]);

    const parseSortOption = (value: string): { sort: string; direction: "asc" | "desc" } => {
        const [sort = "created_at", direction = "desc"] = value.split(":");

        return {
            sort,
            direction: direction === "asc" ? "asc" : "desc",
        };
    };

    const buildFilterParams = (
        searchTerm: string,
        filterValues: FilterType[] = activeFilters,
        selectedSortOption: string = sortOption,
    ): Record<string, string | number> => {
        const sort = parseSortOption(selectedSortOption);
        const appliedFilters: Record<string, string | number> = {
            sort: sort.sort,
            direction: sort.direction,
        };

        if (searchTerm.trim()) {
            appliedFilters.search = searchTerm.trim();
        }

        filterValues.forEach((filter) => {
            const value = filter.values[0];

            if (typeof value === "string" || typeof value === "number") {
                appliedFilters[filter.field] = value;
            }
        });

        return appliedFilters;
    };

    const refreshClasses = useDebouncedCallback((searchTerm: string, filterValues: FilterType[], selectedSortOption: string = sortOption) => {
        router.get(route("administrators.classes.index"), buildFilterParams(searchTerm, filterValues, selectedSortOption), {
            only: ["classes", "filters"],
            preserveScroll: true,
            preserveState: true,
            replace: true,
        });
    }, 350);

    const handleFiltersChange = (newFilters: FilterType[]) => {
        setActiveFilters(newFilters);
    };

    const clearFilters = () => {
        setSearch("");
        setActiveFilters([]);
    };

    const handleSortChange = (value: string) => {
        setSortOption(value);
        refreshClasses(search, activeFilters, value);
    };

    const filterFields: FilterFieldConfig[] = React.useMemo(
        () => [
            {
                key: "classification",
                label: "Class Type",
                type: "select",
                icon: <GraduationCap className="h-4 w-4" />,
                options: options.classifications
                    .filter((option) => option.value !== "all")
                    .map((option) => ({ ...option, icon: <GraduationCap className="text-muted-foreground h-4 w-4" /> })),
            },
            {
                key: "course_id",
                label: "Course",
                type: "select",
                icon: <BookOpen className="h-4 w-4" />,
                options: options.courses.map((option) => ({
                    value: option.id,
                    label: option.label,
                    icon: <BookOpen className="text-muted-foreground h-4 w-4" />,
                })),
            },
            {
                key: "shs_track_id",
                label: "SHS Track",
                type: "select",
                icon: <Layers className="h-4 w-4" />,
                options: options.shs_tracks.map((option) => ({
                    value: option.id,
                    label: option.label,
                    icon: <Layers className="text-muted-foreground h-4 w-4" />,
                })),
            },
            {
                key: "faculty_id",
                label: "Faculty",
                type: "select",
                icon: <Users className="h-4 w-4" />,
                options: options.faculties.map((option) => ({
                    value: option.id,
                    label: option.label,
                    icon: <Users className="text-muted-foreground h-4 w-4" />,
                })),
            },
            {
                key: "room_id",
                label: "Room",
                type: "select",
                icon: <MapPin className="h-4 w-4" />,
                options: options.rooms.map((option) => ({
                    value: option.id,
                    label: option.label,
                    icon: <MapPin className="text-muted-foreground h-4 w-4" />,
                })),
            },
            {
                key: "semester",
                label: "Semester",
                type: "select",
                icon: <CalendarIcon className="h-4 w-4" />,
                options: options.semesters.map((option) => ({ ...option, icon: <CalendarIcon className="text-muted-foreground h-4 w-4" /> })),
            },
            {
                key: "academic_year",
                label: "College Year",
                type: "select",
                icon: <GraduationCap className="h-4 w-4" />,
                options: [1, 2, 3, 4].map((year) => ({
                    value: year,
                    label: `${year} year`,
                    icon: <GraduationCap className="text-muted-foreground h-4 w-4" />,
                })),
            },
            {
                key: "grade_level",
                label: "SHS Grade",
                type: "select",
                icon: <GraduationCap className="h-4 w-4" />,
                options: options.grade_levels.map((option) => ({ ...option, icon: <GraduationCap className="text-muted-foreground h-4 w-4" /> })),
            },
            {
                key: "available_slots",
                label: "Available Slots",
                type: "select",
                icon: <Users className="h-4 w-4" />,
                options: [{ value: "true", label: "Has available slots", icon: <Users className="h-4 w-4 text-green-500" /> }],
            },
            {
                key: "fully_enrolled",
                label: "Enrollment Status",
                type: "select",
                icon: <Users className="h-4 w-4" />,
                options: [
                    { value: "true", label: "Fully enrolled", icon: <Users className="h-4 w-4 text-red-500" /> },
                    { value: "false", label: "Not fully enrolled", icon: <Users className="h-4 w-4 text-green-500" /> },
                ],
            },
        ],
        [options],
    );

    const openManage = (classId: number) => {
        setIsManageOpen(true);
        setIsSelectedClassLoading(true);

        router.get(
            route("administrators.classes.index"),
            { selected: classId },
            {
                preserveState: true,
                replace: true,
                only: ["selected_class"],
                onFinish: () => setIsSelectedClassLoading(false),
            },
        );
    };

    const openEdit = (classId: number) => {
        setIsEditOpen(true);
        setEditActiveTab("details");
        setIsSelectedClassLoading(true);

        router.get(
            route("administrators.classes.index"),
            { selected: classId },
            {
                preserveState: true,
                replace: true,
                only: ["selected_class"],
                onFinish: () => setIsSelectedClassLoading(false),
            },
        );
    };

    const confirmDelete = (row: ClassRow) => {
        setPendingDelete(row);
    };

    const performDelete = () => {
        if (!pendingDelete) return;

        router.delete(route("administrators.classes.destroy", { class: pendingDelete.id }), {
            preserveScroll: true,
            onSuccess: () => {
                setPendingDelete(null);
                setIsManageOpen(false);
            },
        });
    };

    const columns = React.useMemo(
        () =>
            getColumns({
                onManage: openManage,
                onEdit: openEdit,
                onDelete: confirmDelete,
                onCopy: (id) => {
                    setCopySourceId(id);
                    setCopySection("A");
                    setIsCopyOpen(true);
                },
            }),
        [openManage, openEdit, setIsCopyOpen],
    );

    const editForm = useForm({
        classification: "college" as "college" | "shs",
        course_codes: [] as number[],
        subject_ids: [] as number[],
        subject_code: "",
        academic_year: 1 as number | null,

        shs_track_id: null as number | null,
        shs_strand_id: null as number | null,
        subject_code_shs: "",
        grade_level: "Grade 11" as string | null,

        faculty_id: null as string | null,
        semester: defaults.semester,
        school_year: defaults.school_year,
        section: "A",
        room_id: options.rooms[0]?.id ?? 0,
        maximum_slots: 40,

        schedules: [] as ClassSchedule[],

        settings: {
            background_color: "#ffffff",
            accent_color: "#3b82f6",
            theme: "default",
            enable_announcements: true,
            enable_grade_visibility: true,
            enable_attendance_tracking: false,
            allow_late_submissions: false,
            enable_discussion_board: false,
            notify_students_on_schedule_changes: true,
            custom: {} as Record<string, string>,
            banner_image: null as File | null,
        },

        remove_banner_image: false,
    });

    const editCurriculumYears = React.useMemo(() => {
        const selectedIds = new Set(editForm.data.subject_ids);

        return Array.from(
            new Set(
                collegeSubjectOptions
                    .filter((subject) => selectedIds.has(subject.id) && subject.academic_year !== null)
                    .map((subject) => subject.academic_year as number),
            ),
        ).sort((a, b) => a - b);
    }, [collegeSubjectOptions, editForm.data.subject_ids]);

    const editSubjectsAreResolved = React.useMemo(() => {
        const selectedIds = new Set(editForm.data.subject_ids);
        const selectedSubjects = collegeSubjectOptions.filter((subject) => selectedIds.has(subject.id));

        return selectedSubjects.length === editForm.data.subject_ids.length && selectedSubjects.every((subject) => subject.academic_year !== null);
    }, [collegeSubjectOptions, editForm.data.subject_ids]);

    const editUnanimousYear = editSubjectsAreResolved && editCurriculumYears.length === 1 ? editCurriculumYears[0] : null;
    const editSpansYears = editCurriculumYears.length > 1;

    React.useEffect(() => {
        if (editUnanimousYear !== null && editForm.data.academic_year !== editUnanimousYear) {
            editForm.setData("academic_year", editUnanimousYear);
            return;
        }

        if (editSpansYears && !editCurriculumYears.includes(editForm.data.academic_year ?? 0)) {
            editForm.setData("academic_year", editCurriculumYears[0]);
        }
    }, [editCurriculumYears, editForm.data.academic_year, editSpansYears, editUnanimousYear]);

    React.useEffect(() => {
        if (!isEditOpen || !selected_class) return;

        editForm.setData({
            classification: (selected_class.classification as "college" | "shs") ?? "college",
            course_codes: selected_class.course_ids ?? [],
            subject_ids: selected_class.subject_ids ?? [],
            subject_code: selected_class.subject_code ?? "",
            academic_year: selected_class.academic_year ?? null,

            shs_track_id: selected_class.shs_track ? Number(selected_class.shs_track.id) : null,
            shs_strand_id: selected_class.shs_strand ? Number(selected_class.shs_strand.id) : null,
            subject_code_shs: selected_class.classification === "shs" ? selected_class.subject_code : "",
            grade_level: selected_class.grade_level ?? null,

            faculty_id: selected_class.faculty ? String(selected_class.faculty.id) : null,
            semester: normalizeSemester(selected_class.semester),
            school_year: selected_class.school_year,
            section: selected_class.section,
            room_id: Number(selected_class.room?.id ?? options.rooms[0]?.id ?? 0),
            maximum_slots: selected_class.maximum_slots,

            schedules: selected_class.schedules.map((s) => ({
                day_of_week: s.day_of_week,
                start_time: s.start_time,
                end_time: s.end_time,
                room_id: s.room_id,
            })),

            settings: {
                background_color: selected_class.settings.background_color ?? "#ffffff",
                accent_color: selected_class.settings.accent_color ?? "#3b82f6",
                theme: selected_class.settings.theme ?? "default",
                enable_announcements: selected_class.settings.enable_announcements ?? true,
                enable_grade_visibility: selected_class.settings.enable_grade_visibility ?? true,
                enable_attendance_tracking: selected_class.settings.enable_attendance_tracking ?? false,
                allow_late_submissions: selected_class.settings.allow_late_submissions ?? false,
                enable_discussion_board: selected_class.settings.enable_discussion_board ?? false,
                notify_students_on_schedule_changes: selected_class.settings.notify_students_on_schedule_changes ?? true,
                custom: selected_class.settings.custom ?? {},
                banner_image: null as File | null,
            },
            remove_banner_image: false,
        } as typeof editForm.data);

        setSubjectCodeTouched(false);
        void loadCollegeSubjects(selected_class.course_ids ?? []);
        void loadShsStrands(selected_class.shs_track ? Number(selected_class.shs_track.id) : null);
        void loadShsSubjects(selected_class.shs_strand ? Number(selected_class.shs_strand.id) : null);
    }, [isEditOpen, selected_class]);

    const editFirstError = getFirstFormError(editForm.errors);

    const loadCollegeSubjects = async (courseIds: number[]) => {
        if (courseIds.length === 0) {
            setCollegeSubjectOptions([]);
            {
                editForm.setData("subject_ids", []);
                if (!subjectCodeTouched) {
                    editForm.setData("subject_code", "");
                }
            }
            return;
        }

        setCollegeSubjectsLoading(true);

        try {
            const response = await fetch(
                route("administrators.classes.options.subjects", {
                    course_ids: courseIds,
                }),
            );
            const data = (await response.json()) as {
                data: {
                    id: number;
                    label: string;
                    code: string;
                    title: string;
                    course_id: number;
                    course_code: string | null;
                    academic_year: number | null;
                    semester: string | null;
                }[];
            };

            setCollegeSubjectOptions(data.data);

            const availableSubjectIds = new Set(data.data.map((subject) => subject.id));

            if (!subjectCodeTouched) {
                const validSubjectIds = editForm.data.subject_ids.filter((id) => availableSubjectIds.has(id));

                if (validSubjectIds.length !== editForm.data.subject_ids.length) {
                    editForm.setData("subject_ids", validSubjectIds);
                }

                const computed = buildSubjectCodeFromSubjectOptions(validSubjectIds.map(String), data.data);
                if (computed) {
                    editForm.setData("subject_code", computed);
                } else {
                    editForm.setData("subject_code", "");
                }
            }
        } finally {
            setCollegeSubjectsLoading(false);
        }
    };

    const loadShsStrands = async (trackId: number | null) => {
        if (!trackId) {
            setShsStrandOptions([]);
            editForm.setData("shs_strand_id", null);
            return;
        }

        setShsStrandsLoading(true);

        try {
            const response = await fetch(
                route("administrators.classes.options.shs-strands", {
                    track_id: trackId,
                }),
            );
            const data = (await response.json()) as { data: EntityOption[] };
            setShsStrandOptions(data.data);
        } finally {
            setShsStrandsLoading(false);
        }
    };

    const loadShsSubjects = async (strandId: number | null) => {
        if (!strandId) {
            setShsSubjectOptions([]);
            return;
        }

        setShsSubjectsLoading(true);

        try {
            const response = await fetch(
                route("administrators.classes.options.shs-subjects", {
                    strand_id: strandId,
                }),
            );
            const data = (await response.json()) as {
                data: { code: string; label: string; title: string }[];
            };
            setShsSubjectOptions(data.data);
        } finally {
            setShsSubjectsLoading(false);
        }
    };

    const settingsEditor = ({
        settings,
        setSettings,
        removeBannerImage,
        setRemoveBannerImage,
    }: {
        settings: ClassSettingsFormData;
        setSettings: (nextSettings: ClassSettingsFormData) => void;
        removeBannerImage?: boolean;
        setRemoveBannerImage?: (value: boolean) => void;
    }) => {
        const customEntries = Object.entries(settings.custom);

        const toggles: Array<{
            key: keyof Pick<
                ClassSettingsFormData,
                | "enable_announcements"
                | "enable_grade_visibility"
                | "enable_attendance_tracking"
                | "allow_late_submissions"
                | "enable_discussion_board"
                | "notify_students_on_schedule_changes"
            >;
            label: string;
            desc: string;
        }> = [
            {
                key: "enable_announcements",
                label: "Enable announcements",
                desc: "Allow faculty to post announcements visible to all enrolled students.",
            },
            { key: "enable_grade_visibility", label: "Show grades to students", desc: "Students can view their grades and feedback securely." },
            { key: "enable_attendance_tracking", label: "Track attendance", desc: "Enable logging and charting of student attendance patterns." },
            { key: "allow_late_submissions", label: "Allow late submissions", desc: "Permit students to submit assignments after the deadlines." },
            {
                key: "enable_discussion_board",
                label: "Enable discussion board",
                desc: "Open a forum for students and faculty to discuss subject matter.",
            },
            {
                key: "notify_students_on_schedule_changes",
                label: "Notify schedule changes",
                desc: "Automatically notify enrolled students whenever this class schedule changes.",
            },
        ];

        return (
            <div className="grid gap-4">
                <div className="grid gap-3 sm:grid-cols-2">
                    <div className="space-y-2">
                        <Label>Background color</Label>
                        <Input
                            type="color"
                            value={settings.background_color}
                            onChange={(e) => setSettings({ ...settings, background_color: e.target.value })}
                        />
                    </div>

                    <div className="space-y-2">
                        <Label>Accent color</Label>
                        <Input
                            type="color"
                            value={settings.accent_color}
                            onChange={(e) => setSettings({ ...settings, accent_color: e.target.value })}
                        />
                    </div>
                </div>

                <div className="mt-2 space-y-3">
                    <Label className="flex items-center gap-2">
                        <Palette className="text-primary h-4 w-4" /> Theme & Branding
                    </Label>
                    <div className="grid gap-3 sm:grid-cols-3">
                        {[
                            { value: "default", label: "Default", desc: "Standard clean look." },
                            { value: "modern", label: "Modern", desc: "Contemporary and bold elements." },
                            { value: "classic", label: "Classic", desc: "Traditional academic styling." },
                            { value: "minimal", label: "Minimal", desc: "Distraction-free interface." },
                            { value: "vibrant", label: "Vibrant", desc: "Colorful and highly energetic." },
                        ].map((opt) => (
                            <VisualRadioButton
                                key={opt.value}
                                title={opt.label}
                                description={opt.desc}
                                checked={settings.theme === opt.value}
                                onSelect={() => setSettings({ ...settings, theme: opt.value })}
                                className="col-span-1"
                            />
                        ))}
                    </div>
                </div>

                <div className="mt-4 space-y-3">
                    <Label className="flex items-center gap-2">
                        <Layers className="text-primary h-4 w-4" /> Features & Attributes
                    </Label>
                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        {toggles.map((toggle) => (
                            <VisualRadioButton
                                key={toggle.key}
                                title={toggle.label}
                                description={toggle.desc}
                                checked={settings[toggle.key]}
                                onSelect={() =>
                                    setSettings({
                                        ...settings,
                                        [toggle.key]: !settings[toggle.key],
                                    } as ClassSettingsFormData)
                                }
                                className="col-span-1"
                            />
                        ))}
                    </div>
                </div>

                <div className="grid gap-3 rounded-lg border p-4">
                    <div className="flex items-center justify-between">
                        <div>
                            <div className="text-foreground text-sm font-medium">Banner image</div>
                            <div className="text-muted-foreground text-xs">Upload a class banner (optional).</div>
                        </div>
                        {setRemoveBannerImage ? (
                            <label className="flex items-center gap-2 text-sm">
                                <Checkbox
                                    checked={Boolean(removeBannerImage)}
                                    onCheckedChange={(checked) => setRemoveBannerImage(Boolean(checked))}
                                />
                                Remove
                            </label>
                        ) : null}
                    </div>
                    <Input
                        type="file"
                        accept="image/*"
                        onChange={(e) => {
                            const file = e.target.files?.[0] ?? null;
                            setSettings({ ...settings, banner_image: file });
                        }}
                    />
                </div>

                <div className="grid gap-3 rounded-lg border p-4">
                    <div>
                        <div className="text-foreground text-sm font-medium">Custom preferences</div>
                        <div className="text-muted-foreground text-xs">Optional key/value settings.</div>
                    </div>

                    <div className="grid gap-2">
                        {customEntries.length === 0 ? (
                            <div className="text-muted-foreground text-sm">No custom settings.</div>
                        ) : (
                            customEntries.map(([key, value]) => (
                                <div key={key} className="grid gap-2 sm:grid-cols-5">
                                    <Input className="sm:col-span-2" value={key} disabled />
                                    <Input
                                        className="sm:col-span-2"
                                        value={value}
                                        onChange={(e) => {
                                            setSettings({
                                                ...settings,
                                                custom: { ...settings.custom, [key]: e.target.value },
                                            });
                                        }}
                                    />
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        className="text-destructive"
                                        onClick={() => {
                                            const next = { ...settings.custom };
                                            delete next[key];
                                            setSettings({ ...settings, custom: next });
                                        }}
                                    >
                                        Remove
                                    </Button>
                                </div>
                            ))
                        )}

                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => {
                                const next = { ...settings.custom };
                                const keyBase = "setting";
                                let i = 1;
                                while (next[`${keyBase}_${i}`] !== undefined) i++;
                                next[`${keyBase}_${i}`] = "";
                                setSettings({ ...settings, custom: next });
                            }}
                        >
                            Add custom setting
                        </Button>
                    </div>
                </div>
            </div>
        );
    };

    return (
        <AdminLayout user={user} title="Classes">
            <Head title="Administrators • Classes" />

            {(() => {
                const filteredStatsTotalStudents = visibleClasses.reduce((acc, curr) => acc + curr.students_count, 0);
                const filteredStatsTotalClasses = visibleClasses.length;

                const toolbarContent = (
                    <>
                        <div className="relative max-w-md flex-1">
                            <Search className="text-muted-foreground absolute top-2.5 left-2.5 h-4 w-4" />
                            <Input
                                placeholder="Search classes..."
                                className="bg-background pl-8"
                                value={search}
                                onChange={(event) => {
                                    setSearch(event.target.value);
                                }}
                            />
                        </div>

                        <div className="flex flex-wrap items-center gap-2">
                            <Select value={sortOption} onValueChange={handleSortChange}>
                                <SelectTrigger className="h-8 w-[180px]">
                                    <SelectValue placeholder="Sort classes" />
                                </SelectTrigger>
                                <SelectContent align="end">
                                    <SelectItem value="created_at:desc">Latest added</SelectItem>
                                    <SelectItem value="created_at:asc">Oldest added</SelectItem>
                                    <SelectItem value="record_title:asc">Class A-Z</SelectItem>
                                    <SelectItem value="record_title:desc">Class Z-A</SelectItem>
                                    <SelectItem value="students_count:desc">Most enrolled</SelectItem>
                                    <SelectItem value="students_count:asc">Least enrolled</SelectItem>
                                </SelectContent>
                            </Select>

                            <Filters
                                fields={filterFields}
                                filters={activeFilters}
                                onChange={handleFiltersChange}
                                trigger={
                                    <Button variant="outline" className="relative gap-2" size="sm">
                                        <Filter className="h-4 w-4" />
                                        Filters
                                        {activeFilters.length > 0 && (
                                            <Badge variant="secondary" className="ml-1 h-5 min-w-5 rounded-full px-1.5 text-xs">
                                                {activeFilters.length}
                                            </Badge>
                                        )}
                                    </Button>
                                }
                            />

                            {(search.trim() !== "" || activeFilters.length > 0) && (
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={clearFilters}
                                    className="text-muted-foreground hover:text-foreground h-8 px-2"
                                >
                                    <RotateCcw className="mr-2 h-3.5 w-3.5" />
                                    Reset
                                </Button>
                            )}

                            <Separator orientation="vertical" className="mx-1 h-8" />

                            <div className="flex items-center rounded-md border p-0.5">
                                <Button
                                    variant={viewMode === "grid" ? "secondary" : "ghost"}
                                    size="icon"
                                    className="h-7 w-7"
                                    onClick={() => setViewMode("grid")}
                                    title="Grid view"
                                >
                                    <LayoutGrid className="h-4 w-4" />
                                </Button>
                                <Button
                                    variant={viewMode === "list" ? "secondary" : "ghost"}
                                    size="icon"
                                    className="h-7 w-7"
                                    onClick={() => setViewMode("list")}
                                    title="List view"
                                >
                                    <List className="h-4 w-4" />
                                </Button>
                            </div>
                        </div>
                    </>
                );

                return (
                    <div className="flex flex-col gap-6">
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div className="min-w-0">
                                <h2 className="text-2xl font-semibold tracking-tight">Classes</h2>
                                <p className="text-muted-foreground text-sm">Manage classes, track enrollment, and organize schedules.</p>
                            </div>
                            <div className="flex items-center gap-2">
                                <Button variant="outline" size="sm" onClick={() => setIsCompareOpen(true)}>
                                    <GitCompare className="mr-1.5 h-4 w-4" />
                                    <span className="hidden sm:inline">Compare</span>
                                    <span className="sm:hidden">Cmp</span>
                                </Button>
                                <Button asChild size="sm">
                                    <Link href={route("administrators.classes.create")}>
                                        <Plus className="mr-1.5 h-4 w-4" />
                                        <span className="hidden sm:inline">New class</span>
                                        <span className="sm:hidden">New</span>
                                    </Link>
                                </Button>
                            </div>
                        </div>
                        <ClassStats totalClasses={filteredStatsTotalClasses} totalStudents={filteredStatsTotalStudents} />

                        {viewMode === "grid" ? (
                            <div className="animate-in fade-in slide-in-from-bottom-4 overflow-hidden rounded-lg border duration-500">
                                <div className="flex flex-col gap-3 border-b px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                                    {toolbarContent}
                                </div>
                                {visibleClasses.length > 0 ? (
                                    <div className="grid gap-4 p-4 md:grid-cols-2 2xl:grid-cols-3">
                                        {visibleClasses.map((row) => (
                                            <ClassCard
                                                key={row.id}
                                                classRow={row}
                                                onManage={openManage}
                                                onEdit={openEdit}
                                                onDelete={confirmDelete}
                                                onCopy={(id) => {
                                                    setCopySourceId(id);
                                                    setCopySection("A");
                                                    setIsCopyOpen(true);
                                                }}
                                            />
                                        ))}
                                    </div>
                                ) : (
                                    <div className="flex min-h-[280px] flex-col items-center justify-center p-8 text-center">
                                        <div className="bg-muted/50 border-border flex h-14 w-14 items-center justify-center rounded-full border">
                                            <Layers className="text-muted-foreground h-7 w-7" />
                                        </div>
                                        <h3 className="mt-4 text-lg font-semibold">No classes found</h3>
                                        <p className="text-muted-foreground mt-1.5 max-w-sm text-sm">Try adjusting your search or filters.</p>
                                        {(search.trim() !== "" || activeFilters.length > 0) && (
                                            <Button variant="outline" size="sm" className="mt-5" onClick={clearFilters}>
                                                Clear search and filters
                                            </Button>
                                        )}
                                    </div>
                                )}
                            </div>
                        ) : (
                            <DataTable columns={columns} data={visibleClasses} toolbar={toolbarContent} />
                        )}

                        {viewMode === "grid" && visibleClasses.length > 0 && (
                            <div className="flex items-center justify-between gap-3 border-t pt-4">
                                <div className="text-muted-foreground text-sm">
                                    Showing {visibleClasses.length} {visibleClasses.length === 1 ? "class" : "classes"}
                                </div>
                            </div>
                        )}
                    </div>
                );
            })()}

            <Dialog open={isCopyOpen} onOpenChange={setIsCopyOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Copy class</DialogTitle>
                        <DialogDescription>Creates a new class with the same data (schedules not copied).</DialogDescription>
                    </DialogHeader>

                    <div className="space-y-2">
                        <Label>New section</Label>
                        <Select value={copySection} onValueChange={setCopySection}>
                            <SelectTrigger className="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {options.sections.map((sec) => (
                                    <SelectItem key={sec.value} value={sec.value}>
                                        {sec.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    <DialogFooter>
                        <Button variant="outline" onClick={() => setIsCopyOpen(false)}>
                            Cancel
                        </Button>
                        <Button
                            onClick={() => {
                                if (!copySourceId) return;
                                router.post(
                                    route("administrators.classes.copy", { class: copySourceId }),
                                    { section: copySection },
                                    { preserveScroll: true, onSuccess: () => setIsCopyOpen(false) },
                                );
                            }}
                        >
                            Copy
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog
                open={isEditOpen}
                onOpenChange={(open) => {
                    setIsEditOpen(open);
                    if (!open) {
                        setIsSelectedClassLoading(false);
                    }
                    if (open) {
                        setEditActiveTab("details");
                    }
                }}
            >
                <DialogContent className="bg-background/95 supports-[backdrop-filter]:bg-background/60 flex h-[95vh] w-full flex-col gap-0 overflow-hidden p-0 backdrop-blur sm:max-w-3xl md:max-w-5xl lg:max-w-[90vw] xl:max-w-[1400px]">
                    <DialogHeader className="bg-muted/20 border-b p-6 pb-4">
                        <DialogTitle className="text-xl font-bold">Edit class</DialogTitle>
                        <DialogDescription>Update the class record and schedule.</DialogDescription>
                    </DialogHeader>

                    {!selected_class ? (
                        <div className="text-muted-foreground flex-1 p-6 text-sm">
                            {isSelectedClassLoading ? "Loading class details..." : "Select a class first."}
                        </div>
                    ) : (
                        <Tabs
                            value={editActiveTab}
                            onValueChange={(value) => setEditActiveTab(value as ClassDialogTab)}
                            className="flex flex-1 flex-col overflow-hidden"
                        >
                            <div className="px-6 pt-4">
                                <TabsList className="h-12 w-full justify-start gap-6 rounded-none border-b bg-transparent p-0">
                                    <TabsTrigger
                                        value="details"
                                        className="data-[state=active]:border-primary text-muted-foreground data-[state=active]:text-foreground h-12 rounded-none px-2 data-[state=active]:border-b-2 data-[state=active]:bg-transparent data-[state=active]:shadow-none"
                                    >
                                        <ListTodo className="mr-2 h-4 w-4" />
                                        Details
                                    </TabsTrigger>
                                    <TabsTrigger
                                        value="schedule"
                                        className="data-[state=active]:border-primary text-muted-foreground data-[state=active]:text-foreground h-12 rounded-none px-2 data-[state=active]:border-b-2 data-[state=active]:bg-transparent data-[state=active]:shadow-none"
                                    >
                                        <CalendarIcon className="mr-2 h-4 w-4" />
                                        Schedule
                                    </TabsTrigger>
                                    <TabsTrigger
                                        value="settings"
                                        className="data-[state=active]:border-primary text-muted-foreground data-[state=active]:text-foreground h-12 rounded-none px-2 data-[state=active]:border-b-2 data-[state=active]:bg-transparent data-[state=active]:shadow-none"
                                    >
                                        <Settings2 className="mr-2 h-4 w-4" />
                                        Settings
                                    </TabsTrigger>
                                </TabsList>
                            </div>

                            <ScrollArea className="h-full min-h-0 flex-1">
                                <div className="p-6 pt-6">
                                    {editFirstError ? (
                                        <div className="border-destructive/40 bg-destructive/5 text-destructive mb-6 rounded-lg border px-4 py-3 text-sm">
                                            {editFirstError}
                                        </div>
                                    ) : null}
                                    <TabsContent value="details" className="m-0 space-y-6 outline-none">
                                        <div className="space-y-4">
                                            <Card className="border-border/60 shadow-sm">
                                                <CardHeader className="bg-muted/20 border-b pb-4">
                                                    <CardTitle className="text-base font-semibold">Class basics</CardTitle>
                                                </CardHeader>
                                                <CardContent className="space-y-4 pt-6">
                                                    <div className="grid gap-4 sm:grid-cols-2">
                                                        <div className="space-y-3 sm:col-span-2">
                                                            <Label>Program type</Label>
                                                            <div className="grid gap-3 sm:grid-cols-2">
                                                                <VisualRadioButton
                                                                    title="College"
                                                                    checked={editForm.data.classification === "college"}
                                                                    onSelect={() => {
                                                                        editForm.setData("classification", "college");
                                                                        editForm.setData("course_codes", []);
                                                                        editForm.setData("subject_ids", []);
                                                                        editForm.setData("subject_code", "");
                                                                        editForm.setData("shs_track_id", null);
                                                                        editForm.setData("shs_strand_id", null);
                                                                        editForm.setData("subject_code_shs", "");
                                                                        setCollegeSubjectOptions([]);
                                                                        setShsStrandOptions([]);
                                                                        setShsSubjectOptions([]);
                                                                        setSubjectCodeTouched(false);
                                                                    }}
                                                                />
                                                                <VisualRadioButton
                                                                    title="Senior High School"
                                                                    checked={editForm.data.classification === "shs"}
                                                                    onSelect={() => {
                                                                        editForm.setData("classification", "shs");
                                                                        editForm.setData("course_codes", []);
                                                                        editForm.setData("subject_ids", []);
                                                                        editForm.setData("subject_code", "");
                                                                        editForm.setData("shs_track_id", null);
                                                                        editForm.setData("shs_strand_id", null);
                                                                        editForm.setData("subject_code_shs", "");
                                                                        setCollegeSubjectOptions([]);
                                                                        setShsStrandOptions([]);
                                                                        setShsSubjectOptions([]);
                                                                        setSubjectCodeTouched(false);
                                                                    }}
                                                                />
                                                            </div>
                                                        </div>

                                                        {editForm.data.classification === "college" ? (
                                                            <>
                                                                <div className="space-y-2">
                                                                    <Label>Courses</Label>
                                                                    <SearchableMultiSelect
                                                                        placeholder="Search and select courses..."
                                                                        searchPlaceholder="Search courses..."
                                                                        emptyText="No courses found."
                                                                        options={options.courses.map((course) => ({
                                                                            value: String(course.id),
                                                                            label: course.label,
                                                                            searchText: course.label,
                                                                        }))}
                                                                        selected={editForm.data.course_codes.map(String)}
                                                                        onChange={(values) => {
                                                                            const next = values.map(Number);
                                                                            editForm.setData("course_codes", next);
                                                                            void loadCollegeSubjects(next);
                                                                        }}
                                                                    />
                                                                    {editForm.errors.course_codes ? (
                                                                        <p className="text-destructive text-xs">{editForm.errors.course_codes}</p>
                                                                    ) : null}
                                                                </div>

                                                                <div className="space-y-2">
                                                                    <Label>Subjects</Label>
                                                                    <SearchableMultiSelect
                                                                        placeholder={
                                                                            editForm.data.course_codes.length
                                                                                ? "Search and select subjects..."
                                                                                : "Select courses first"
                                                                        }
                                                                        searchPlaceholder="Search subjects..."
                                                                        emptyText="No subjects found."
                                                                        options={collegeSubjectOptions.map((subject) => ({
                                                                            value: String(subject.id),
                                                                            label: subject.label,
                                                                            description: `${subject.course_code ?? "Program"} · ${
                                                                                subject.academic_year
                                                                                    ? `Year ${subject.academic_year}`
                                                                                    : "Year unresolved"
                                                                            }${subject.semester ? ` · Semester ${subject.semester}` : ""}`,
                                                                            searchText: `${subject.code} ${subject.title} ${subject.label}`,
                                                                        }))}
                                                                        selected={editForm.data.subject_ids.map(String)}
                                                                        disabled={editForm.data.course_codes.length === 0 || collegeSubjectsLoading}
                                                                        onChange={(values) => {
                                                                            const subjectIds = values.map(Number);
                                                                            editForm.setData("subject_ids", subjectIds);
                                                                            const years = Array.from(
                                                                                new Set(
                                                                                    collegeSubjectOptions
                                                                                        .filter((subject) => subjectIds.includes(subject.id))
                                                                                        .map((subject) => subject.academic_year)
                                                                                        .filter((year): year is number => year !== null),
                                                                                ),
                                                                            ).sort((a, b) => a - b);

                                                                            if (years.length === 1) {
                                                                                editForm.setData("academic_year", years[0]);
                                                                            } else if (
                                                                                years.length > 1 &&
                                                                                !years.includes(editForm.data.academic_year ?? 0)
                                                                            ) {
                                                                                editForm.setData("academic_year", years[0]);
                                                                            }
                                                                            if (!subjectCodeTouched) {
                                                                                const computed = buildSubjectCodeFromSubjectOptions(
                                                                                    values,
                                                                                    collegeSubjectOptions,
                                                                                );
                                                                                editForm.setData("subject_code", computed);
                                                                            }
                                                                        }}
                                                                    />
                                                                    {editForm.errors.subject_ids ? (
                                                                        <p className="text-destructive text-xs">{editForm.errors.subject_ids}</p>
                                                                    ) : null}
                                                                </div>

                                                                <div className="space-y-2 sm:col-span-2">
                                                                    <Label>Class code or name</Label>
                                                                    <Input
                                                                        value={editForm.data.subject_code}
                                                                        placeholder="Auto-generated from subjects..."
                                                                        onChange={(e) => {
                                                                            setSubjectCodeTouched(true);
                                                                            editForm.setData("subject_code", e.target.value);
                                                                        }}
                                                                    />
                                                                </div>
                                                            </>
                                                        ) : (
                                                            <>
                                                                <div className="space-y-2">
                                                                    <Label>SHS track</Label>
                                                                    <Select
                                                                        value={editForm.data.shs_track_id ? String(editForm.data.shs_track_id) : ""}
                                                                        onValueChange={(val) => {
                                                                            const trackId = Number(val);
                                                                            editForm.setData("shs_track_id", trackId);
                                                                            editForm.setData("shs_strand_id", null);
                                                                            editForm.setData("subject_code_shs", "");
                                                                            void loadShsStrands(trackId);
                                                                        }}
                                                                    >
                                                                        <SelectTrigger className="w-full">
                                                                            <SelectValue placeholder="Select track" />
                                                                        </SelectTrigger>
                                                                        <SelectContent>
                                                                            {options.shs_tracks.map((track) => (
                                                                                <SelectItem key={track.id} value={String(track.id)}>
                                                                                    {track.label}
                                                                                </SelectItem>
                                                                            ))}
                                                                        </SelectContent>
                                                                    </Select>
                                                                </div>

                                                                <div className="space-y-2">
                                                                    <Label>SHS strand</Label>
                                                                    <Select
                                                                        value={editForm.data.shs_strand_id ? String(editForm.data.shs_strand_id) : ""}
                                                                        onValueChange={(val) => {
                                                                            const strandId = Number(val);
                                                                            editForm.setData("shs_strand_id", strandId);
                                                                            editForm.setData("subject_code_shs", "");
                                                                            void loadShsSubjects(strandId);
                                                                        }}
                                                                        disabled={!editForm.data.shs_track_id || shsStrandsLoading}
                                                                    >
                                                                        <SelectTrigger className="w-full">
                                                                            <SelectValue placeholder="Select strand" />
                                                                        </SelectTrigger>
                                                                        <SelectContent>
                                                                            {shsStrandOptions.map((strand) => (
                                                                                <SelectItem key={strand.id} value={String(strand.id)}>
                                                                                    {strand.label}
                                                                                </SelectItem>
                                                                            ))}
                                                                        </SelectContent>
                                                                    </Select>
                                                                </div>

                                                                <div className="space-y-2 sm:col-span-2">
                                                                    <Label>SHS subject</Label>
                                                                    <Select
                                                                        value={editForm.data.subject_code_shs}
                                                                        onValueChange={(val) => editForm.setData("subject_code_shs", val)}
                                                                        disabled={!editForm.data.shs_strand_id || shsSubjectsLoading}
                                                                    >
                                                                        <SelectTrigger className="w-full">
                                                                            <SelectValue placeholder="Select subject" />
                                                                        </SelectTrigger>
                                                                        <SelectContent>
                                                                            {shsSubjectOptions.map((subject) => (
                                                                                <SelectItem key={subject.code} value={subject.code}>
                                                                                    {subject.label}
                                                                                </SelectItem>
                                                                            ))}
                                                                        </SelectContent>
                                                                    </Select>
                                                                </div>

                                                                <div className="space-y-2">
                                                                    <Label>Grade level</Label>
                                                                    <Select
                                                                        value={editForm.data.grade_level || "Grade 11"}
                                                                        onValueChange={(val) => editForm.setData("grade_level", val)}
                                                                    >
                                                                        <SelectTrigger className="w-full">
                                                                            <SelectValue />
                                                                        </SelectTrigger>
                                                                        <SelectContent>
                                                                            {options.grade_levels.map((grade) => (
                                                                                <SelectItem key={grade.value} value={grade.value}>
                                                                                    {grade.label}
                                                                                </SelectItem>
                                                                            ))}
                                                                        </SelectContent>
                                                                    </Select>
                                                                </div>
                                                            </>
                                                        )}
                                                    </div>
                                                </CardContent>
                                            </Card>

                                            <Card className="border-border/60 h-fit shadow-sm">
                                                <CardHeader className="bg-muted/20 border-b pb-4">
                                                    <CardTitle className="text-base font-semibold">Teaching details</CardTitle>
                                                </CardHeader>
                                                <CardContent className="space-y-4 pt-6">
                                                    <div className="grid gap-4 sm:grid-cols-2">
                                                        <div className="space-y-2 sm:col-span-2">
                                                            <Label>Faculty</Label>
                                                            <Select
                                                                value={editForm.data.faculty_id ? String(editForm.data.faculty_id) : ""}
                                                                onValueChange={(val) => editForm.setData("faculty_id", val)}
                                                            >
                                                                <SelectTrigger className="w-full">
                                                                    <SelectValue placeholder="Select faculty" />
                                                                </SelectTrigger>
                                                                <SelectContent>
                                                                    {options.faculties.map((faculty) => (
                                                                        <SelectItem key={faculty.id} value={String(faculty.id)}>
                                                                            {faculty.label}
                                                                        </SelectItem>
                                                                    ))}
                                                                </SelectContent>
                                                            </Select>
                                                        </div>

                                                        {editForm.data.classification === "college" ? (
                                                            <div className="space-y-3 sm:col-span-2">
                                                                <Label>Year level</Label>
                                                                <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                                                                    {[1, 2, 3, 4].map((year) => (
                                                                        <VisualRadioButton
                                                                            key={year}
                                                                            title={`${year} Year`}
                                                                            checked={editForm.data.academic_year === year}
                                                                            onSelect={() => editForm.setData("academic_year", year)}
                                                                            disabled={
                                                                                editUnanimousYear !== null ||
                                                                                (editSpansYears && !editCurriculumYears.includes(year))
                                                                            }
                                                                            className="min-h-0 px-3 py-3"
                                                                        />
                                                                    ))}
                                                                </div>
                                                                {editUnanimousYear !== null ? (
                                                                    <p className="text-muted-foreground text-xs">
                                                                        Set automatically from the selected subjects’ curriculum placement.
                                                                    </p>
                                                                ) : editSpansYears ? (
                                                                    <div className="rounded-lg border border-amber-500/40 bg-amber-500/10 p-3 text-xs text-amber-900 dark:text-amber-100">
                                                                        This shared class spans curriculum years {editCurriculumYears.join(" and ")}.
                                                                        Program schedules use each subject’s year; this is only the legacy fallback.
                                                                    </div>
                                                                ) : null}
                                                            </div>
                                                        ) : null}

                                                        <div className="space-y-3 sm:col-span-2">
                                                            <Label>Semester</Label>
                                                            <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
                                                                {options.semesters.map((sem) => (
                                                                    <VisualRadioButton
                                                                        key={sem.value}
                                                                        title={sem.label}
                                                                        className="min-h-0 px-3 py-3"
                                                                        checked={editForm.data.semester === sem.value}
                                                                        onSelect={() => editForm.setData("semester", sem.value)}
                                                                    />
                                                                ))}
                                                            </div>
                                                        </div>

                                                        <div className="space-y-2">
                                                            <Label>School year</Label>
                                                            <Input
                                                                value={editForm.data.school_year}
                                                                onChange={(e) => editForm.setData("school_year", e.target.value)}
                                                                placeholder="e.g., 2023 - 2024"
                                                            />
                                                        </div>

                                                        <div className="space-y-2">
                                                            <Label>Section</Label>
                                                            <Select
                                                                value={editForm.data.section}
                                                                onValueChange={(val) => editForm.setData("section", val)}
                                                            >
                                                                <SelectTrigger className="w-full">
                                                                    <SelectValue />
                                                                </SelectTrigger>
                                                                <SelectContent>
                                                                    {options.sections.map((sec) => (
                                                                        <SelectItem key={sec.value} value={sec.value}>
                                                                            {sec.label}
                                                                        </SelectItem>
                                                                    ))}
                                                                </SelectContent>
                                                            </Select>
                                                        </div>

                                                        <div className="space-y-2">
                                                            <Label>Room</Label>
                                                            <Select
                                                                value={String(editForm.data.room_id)}
                                                                onValueChange={(val) => editForm.setData("room_id", Number(val))}
                                                            >
                                                                <SelectTrigger className="w-full">
                                                                    <SelectValue placeholder="Room" />
                                                                </SelectTrigger>
                                                                <SelectContent>
                                                                    {options.rooms.map((room) => (
                                                                        <SelectItem key={room.id} value={String(room.id)}>
                                                                            {room.label}
                                                                        </SelectItem>
                                                                    ))}
                                                                </SelectContent>
                                                            </Select>
                                                        </div>

                                                        <div className="space-y-2">
                                                            <Label>Class size limit</Label>
                                                            <Input
                                                                type="number"
                                                                value={String(editForm.data.maximum_slots)}
                                                                min={1}
                                                                onChange={(e) => editForm.setData("maximum_slots", Number(e.target.value))}
                                                            />
                                                        </div>
                                                    </div>
                                                </CardContent>
                                            </Card>
                                        </div>
                                    </TabsContent>

                                    <TabsContent value="schedule" className="m-0 flex h-full min-h-[500px] flex-col outline-none">
                                        <SchedulePlanner
                                            schedules={editForm.data.schedules}
                                            setSchedules={(nextSchedules) => editForm.setData("schedules", nextSchedules)}
                                            rooms={options.rooms}
                                            dayOptions={options.day_of_week}
                                            defaultRoomId={Number(options.rooms[0]?.id ?? 0)}
                                            classRoomId={Number(editForm.data.room_id)}
                                        />
                                    </TabsContent>

                                    <TabsContent value="settings" className="space-y-4">
                                        {settingsEditor({
                                            settings: editForm.data.settings,
                                            setSettings: (nextSettings) => editForm.setData("settings", nextSettings),
                                            removeBannerImage: editForm.data.remove_banner_image,
                                            setRemoveBannerImage: (value) => editForm.setData("remove_banner_image", value),
                                        })}
                                    </TabsContent>
                                </div>
                            </ScrollArea>
                        </Tabs>
                    )}

                    <DialogFooter className="bg-muted/10 border-t p-6">
                        <Button variant="outline" onClick={() => setIsEditOpen(false)}>
                            Cancel
                        </Button>
                        <Button
                            disabled={editForm.processing || !selected_class || getInvalidScheduleIndexes(editForm.data.schedules).size > 0}
                            onClick={() => {
                                if (!selected_class) return;
                                editForm.patch(route("administrators.classes.update", { class: selected_class.id }), {
                                    preserveScroll: true,
                                    forceFormData: true,
                                    onError: (errors) => {
                                        setEditActiveTab(getTabForFormErrors(errors as Record<string, string>));
                                    },
                                    onSuccess: () => {
                                        setIsEditOpen(false);
                                        editForm.reset();
                                        setEditActiveTab("details");
                                        router.reload({ only: ["classes", "selected_class"] });
                                    },
                                });
                            }}
                        >
                            {editForm.processing ? "Saving..." : "Save"}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <DeleteClassDialog pendingDelete={pendingDelete} onConfirm={performDelete} onCancel={() => setPendingDelete(null)} />

            <Sheet open={isManageOpen} onOpenChange={setIsManageOpen}>
                <SheetContent side="right" className="w-full sm:max-w-xl">
                    <SheetHeader>
                        <SheetTitle>{selected_class?.record_title ?? "Class"}</SheetTitle>
                        <SheetDescription>
                            {selected_class ? (
                                <span>
                                    {selected_class.subject_title} • {selected_class.school_year} • Sem {selected_class.semester}
                                </span>
                            ) : (
                                "Loading..."
                            )}
                        </SheetDescription>
                    </SheetHeader>

                    {!selected_class ? (
                        <div className="text-muted-foreground p-4 text-sm">Loading class details…</div>
                    ) : (
                        <div className="flex flex-1 flex-col gap-4 overflow-auto px-4">
                            <div className="flex flex-wrap gap-2">
                                <Badge variant="outline" className="capitalize">
                                    {selected_class.classification}
                                </Badge>
                                <Badge variant="secondary">
                                    {selected_class.students_count} / {selected_class.maximum_slots || "—"} enrolled
                                </Badge>
                            </div>

                            <Tabs defaultValue="overview">
                                <TabsList className="w-full justify-start">
                                    <TabsTrigger value="overview">Overview</TabsTrigger>
                                    <TabsTrigger value="schedule">Schedule</TabsTrigger>
                                    <TabsTrigger value="settings">Settings</TabsTrigger>
                                    <TabsTrigger value="relationships">Relationships</TabsTrigger>
                                </TabsList>

                                <TabsContent value="overview" className="space-y-3">
                                    <div className="grid gap-3 rounded-lg border p-4">
                                        <div className="grid gap-2">
                                            <div className="text-muted-foreground text-xs">Subject</div>
                                            <div className="text-foreground font-medium">
                                                {selected_class.subject_code} • {selected_class.subject_title}
                                            </div>
                                        </div>

                                        <div className="grid gap-2">
                                            <div className="text-muted-foreground text-xs">Faculty</div>
                                            <div className="text-foreground font-medium">{selected_class.faculty?.label ?? "TBA"}</div>
                                            {selected_class.faculty?.email ? (
                                                <div className="text-muted-foreground text-xs">{selected_class.faculty.email}</div>
                                            ) : null}
                                        </div>

                                        <div className="grid gap-2">
                                            <div className="text-muted-foreground text-xs">Room</div>
                                            <div className="text-foreground font-medium">{selected_class.room?.label ?? "—"}</div>
                                        </div>

                                        {selected_class.classification === "college" ? (
                                            <div className="grid gap-2">
                                                <div className="text-muted-foreground text-xs">Courses</div>
                                                <div className="text-foreground text-sm">
                                                    {selected_class.course_codes.length ? selected_class.course_codes.join(", ") : "—"}
                                                </div>
                                            </div>
                                        ) : (
                                            <div className="grid gap-2">
                                                <div className="text-muted-foreground text-xs">Track / Strand</div>
                                                <div className="text-foreground text-sm">
                                                    {[selected_class.shs_track?.label, selected_class.shs_strand?.label]
                                                        .filter(Boolean)
                                                        .join(" • ") || "—"}
                                                </div>
                                            </div>
                                        )}

                                        <div className="grid gap-2">
                                            <div className="text-muted-foreground text-xs">Academic period</div>
                                            <div className="text-foreground text-sm">
                                                {selected_class.school_year} • Sem {selected_class.semester} • Section {selected_class.section}
                                            </div>
                                        </div>
                                    </div>
                                </TabsContent>

                                <TabsContent value="schedule" className="space-y-3">
                                    <div className="grid gap-3">
                                        {selected_class.schedules.length === 0 ? (
                                            <div className="text-muted-foreground rounded-lg border p-4 text-sm">No schedules.</div>
                                        ) : (
                                            selected_class.schedules.map((schedule) => (
                                                <div
                                                    key={`${schedule.day_of_week}-${schedule.start_time}-${schedule.end_time}`}
                                                    className="rounded-lg border p-4"
                                                >
                                                    <div className="flex items-center justify-between">
                                                        <div className="text-foreground font-medium">{schedule.day_of_week}</div>
                                                        <div className="text-muted-foreground text-sm">
                                                            {schedule.start_time} – {schedule.end_time}
                                                        </div>
                                                    </div>
                                                    <div className="text-muted-foreground mt-2 text-sm">Room: {schedule.room?.label ?? "—"}</div>
                                                </div>
                                            ))
                                        )}

                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={() => {
                                                setIsEditOpen(true);
                                            }}
                                        >
                                            Edit schedule
                                        </Button>
                                    </div>
                                </TabsContent>

                                <TabsContent value="settings" className="space-y-3">
                                    <div className="grid gap-3 rounded-lg border p-4">
                                        <div className="grid gap-1">
                                            <div className="text-muted-foreground text-xs">Theme</div>
                                            <div className="text-foreground text-sm font-medium">{selected_class.settings.theme ?? "default"}</div>
                                        </div>
                                        <div className="grid grid-cols-2 gap-3">
                                            <div className="grid gap-1">
                                                <div className="text-muted-foreground text-xs">Background</div>
                                                <div className="text-foreground text-sm">{selected_class.settings.background_color ?? "—"}</div>
                                            </div>
                                            <div className="grid gap-1">
                                                <div className="text-muted-foreground text-xs">Accent</div>
                                                <div className="text-foreground text-sm">{selected_class.settings.accent_color ?? "—"}</div>
                                            </div>
                                        </div>

                                        <div className="grid gap-2">
                                            <div className="text-muted-foreground text-xs">Features</div>
                                            <div className="flex flex-wrap gap-2">
                                                {(
                                                    [
                                                        { key: "enable_announcements", label: "Announcements" },
                                                        { key: "enable_grade_visibility", label: "Grade visibility" },
                                                        { key: "enable_attendance_tracking", label: "Attendance" },
                                                        { key: "allow_late_submissions", label: "Late submissions" },
                                                        { key: "enable_discussion_board", label: "Discussion board" },
                                                        { key: "notify_students_on_schedule_changes", label: "Schedule alerts" },
                                                    ] as const
                                                ).map((toggle) => (
                                                    <Badge key={toggle.key} variant={selected_class.settings[toggle.key] ? "default" : "outline"}>
                                                        {toggle.label}
                                                    </Badge>
                                                ))}
                                            </div>
                                        </div>
                                    </div>

                                    <Button type="button" variant="outline" onClick={() => setIsEditOpen(true)}>
                                        Edit settings
                                    </Button>
                                </TabsContent>

                                <TabsContent value="relationships" className="space-y-3">
                                    <Tabs defaultValue="students">
                                        <TabsList className="w-full justify-start">
                                            <TabsTrigger value="students">Enrolled students</TabsTrigger>
                                            <TabsTrigger value="posts">Class posts</TabsTrigger>
                                        </TabsList>

                                        <TabsContent value="students" className="space-y-3">
                                            <div className="rounded-lg border">
                                                <Table>
                                                    <TableHeader>
                                                        <TableRow>
                                                            <TableHead>Student</TableHead>
                                                            <TableHead>Status</TableHead>
                                                            <TableHead className="text-right">Final</TableHead>
                                                        </TableRow>
                                                    </TableHeader>
                                                    <TableBody>
                                                        {selected_class.enrollments.length === 0 ? (
                                                            <TableRow>
                                                                <TableCell colSpan={3} className="text-muted-foreground h-20 text-center text-sm">
                                                                    No enrollments loaded.
                                                                </TableCell>
                                                            </TableRow>
                                                        ) : (
                                                            selected_class.enrollments.map((enrollment) => (
                                                                <TableRow key={enrollment.id}>
                                                                    <TableCell>
                                                                        <div className="flex flex-col">
                                                                            <span className="text-foreground font-medium">
                                                                                {enrollment.student?.name ?? "Unknown"}
                                                                            </span>
                                                                            <span className="text-muted-foreground text-xs">
                                                                                {enrollment.student?.course ?? "—"} •{" "}
                                                                                {enrollment.student?.academic_year ?? "—"}
                                                                            </span>
                                                                        </div>
                                                                    </TableCell>
                                                                    <TableCell>
                                                                        <span className="text-muted-foreground text-sm">
                                                                            {enrollment.status ?? "—"}
                                                                        </span>
                                                                    </TableCell>
                                                                    <TableCell className="text-muted-foreground text-right text-sm">
                                                                        {enrollment.total_average ?? "—"}
                                                                    </TableCell>
                                                                </TableRow>
                                                            ))
                                                        )}
                                                    </TableBody>
                                                </Table>
                                            </div>

                                            <Button asChild variant="outline">
                                                <Link href={route("administrators.classes.show", { class: selected_class.id })}>
                                                    Open full enrollments page
                                                </Link>
                                            </Button>
                                        </TabsContent>

                                        <TabsContent value="posts" className="space-y-3">
                                            <div className="grid gap-2">
                                                {selected_class.posts.length === 0 ? (
                                                    <div className="text-muted-foreground rounded-lg border p-4 text-sm">No posts loaded.</div>
                                                ) : (
                                                    selected_class.posts.map((post) => (
                                                        <div key={post.id} className="rounded-lg border p-4">
                                                            <div className="flex items-start justify-between gap-3">
                                                                <div className="min-w-0">
                                                                    <div className="text-foreground truncate font-medium">{post.title}</div>
                                                                    <div className="text-muted-foreground text-xs">Type: {post.type}</div>
                                                                </div>
                                                                <Badge variant="outline">#{post.id}</Badge>
                                                            </div>
                                                        </div>
                                                    ))
                                                )}
                                            </div>

                                            <Button asChild variant="outline">
                                                <Link href={route("administrators.classes.show", { class: selected_class.id })}>
                                                    Open full posts page
                                                </Link>
                                            </Button>
                                        </TabsContent>
                                    </Tabs>
                                </TabsContent>
                            </Tabs>
                        </div>
                    )}

                    <SheetFooter>
                        <div className="flex w-full flex-col gap-2">
                            <Button
                                type="button"
                                onClick={() => {
                                    if (!selected_class) return;
                                    setIsEditOpen(true);
                                    setEditActiveTab("details");
                                }}
                            >
                                <Pencil className="mr-2 h-4 w-4" />
                                Edit class
                            </Button>
                            {selected_class ? (
                                <Button asChild variant="outline">
                                    <Link href={route("administrators.classes.show", { class: selected_class.id })}>
                                        <BookOpen className="mr-2 h-4 w-4" />
                                        Open class page
                                    </Link>
                                </Button>
                            ) : null}
                            <Button
                                type="button"
                                variant="ghost"
                                className="text-destructive hover:text-destructive"
                                onClick={() => {
                                    if (!selected_class) return;
                                    setPendingDelete({
                                        id: selected_class.id,
                                        record_title: selected_class.record_title,
                                    } as ClassRow);
                                }}
                            >
                                <Trash2 className="mr-2 h-4 w-4" />
                                Delete class
                            </Button>
                        </div>
                    </SheetFooter>
                </SheetContent>
            </Sheet>

            <ClassCompareDialog
                open={isCompareOpen}
                onOpenChange={setIsCompareOpen}
                classes={classes.map((c) => ({ id: c.id, record_title: c.record_title, subject_code: c.subject_code, section: c.section }))}
            />
        </AdminLayout>
    );
}
