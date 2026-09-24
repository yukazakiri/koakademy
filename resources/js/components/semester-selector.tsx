import { Select, SelectContent, SelectGroup, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { router } from "@inertiajs/react";

export interface SemesterSelectorProps {
    currentSemester?: number | null;
    currentSchoolYear?: number | null;
    systemSemester?: number | null;
    systemSchoolYear?: number | null;
    availableSemesters?: Record<number, string> | null;
    availableSchoolYears?: Record<number, string> | null;
}

export function SemesterSelector({
    currentSemester,
    currentSchoolYear,
    availableSemesters,
    availableSchoolYears,
}: SemesterSelectorProps) {
    function resolveSettingsEndpoint(path: "semester" | "school-year"): string {
        if (typeof window !== "undefined") {
            const pathname = window.location.pathname;
            if (pathname.startsWith("/administrators")) {
                return `/administrators/settings/${path}`;
            }
            if (pathname.startsWith("/student")) {
                return `/student/settings/${path}`;
            }
            if (pathname.startsWith("/faculty")) {
                return `/faculty/settings/${path}`;
            }
        }

        return `/settings/${path}`;
    }

    const handleSemesterChange = (value: string | null) => {
        if (value == null) {
            return;
        }

        router.put(
            resolveSettingsEndpoint("semester"),
            {
                semester: parseInt(value),
            },
            {
                preserveScroll: true,
            },
        );
    };

    const handleSchoolYearChange = (value: string | null) => {
        if (value == null) {
            return;
        }

        router.put(
            resolveSettingsEndpoint("school-year"),
            {
                school_year_start: parseInt(value),
            },
            {
                preserveScroll: true,
            },
        );
    };

    const currentSemesterValue = currentSemester != null ? currentSemester.toString() : undefined;
    const currentSchoolYearValue = currentSchoolYear != null ? currentSchoolYear.toString() : undefined;

    const safeAvailableSemesters: Record<number, string> = availableSemesters ?? {};
    const safeAvailableSchoolYears: Record<number, string> = availableSchoolYears ?? {};

    const semesterItems = Object.entries(safeAvailableSemesters).map(([value, label]) => ({
        label,
        value,
    }));

    const schoolYearItems = Object.entries(safeAvailableSchoolYears).map(([value, label]) => ({
        label,
        value,
    }));

    return (
        <div className="flex items-center gap-3">
            <Select items={semesterItems} value={currentSemesterValue} onValueChange={handleSemesterChange}>
                <SelectTrigger className="h-8 w-[140px] text-foreground font-medium">
                    <SelectValue placeholder="Select Semester" />
                </SelectTrigger>
                <SelectContent alignItemWithTrigger={false}>
                    <SelectGroup>
                        {semesterItems.map((item) => (
                            <SelectItem key={item.value} value={item.value}>
                                {item.label}
                            </SelectItem>
                        ))}
                    </SelectGroup>
                </SelectContent>
            </Select>

            <Select items={schoolYearItems} value={currentSchoolYearValue} onValueChange={handleSchoolYearChange}>
                <SelectTrigger className="h-8 w-[140px] text-foreground font-medium">
                    <SelectValue placeholder="Select School Year" />
                </SelectTrigger>
                <SelectContent alignItemWithTrigger={false}>
                    <SelectGroup>
                        {schoolYearItems.map((item) => (
                            <SelectItem key={item.value} value={item.value}>
                                {item.label}
                            </SelectItem>
                        ))}
                    </SelectGroup>
                </SelectContent>
            </Select>
        </div>
    );
}
