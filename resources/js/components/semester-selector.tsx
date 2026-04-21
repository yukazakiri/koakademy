import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from "@/components/ui/tooltip";
import { router } from "@inertiajs/react";
import { IconSettings, IconUser } from "@tabler/icons-react";

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
    systemSemester,
    systemSchoolYear,
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

    const handleSemesterChange = (value: string) => {
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

    const handleSchoolYearChange = (value: string) => {
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

    const hasSemesterOverride = systemSemester != null && currentSemester != null && systemSemester !== currentSemester;
    const hasSchoolYearOverride = systemSchoolYear != null && currentSchoolYear != null && systemSchoolYear !== currentSchoolYear;

    return (
        <TooltipProvider>
            <div className="flex items-center gap-3">
                <div className="flex items-center gap-2">
                    <Select value={currentSemesterValue} onValueChange={handleSemesterChange}>
                        <SelectTrigger className="text-foreground h-8 w-[140px]">
                            <SelectValue placeholder="Select Semester" className="text-foreground" />
                        </SelectTrigger>
                        <SelectContent>
                            {Object.entries(safeAvailableSemesters).map(([key, label]) => (
                                <SelectItem key={key} value={key} className="text-foreground">
                                    {label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    {hasSemesterOverride && (
                        <Tooltip>
                            <TooltipTrigger asChild>
                                <div className="flex h-8 w-8 cursor-help items-center justify-center">
                                    <IconUser className="text-primary h-3 w-3" />
                                </div>
                            </TooltipTrigger>
                            <TooltipContent side="bottom">
                                <p>
                                    Your selection (System:{" "}
                                    {systemSemester != null ? (safeAvailableSemesters[systemSemester] ?? "Default") : "Default"})
                                </p>
                            </TooltipContent>
                        </Tooltip>
                    )}

                    {systemSemester != null && systemSemester !== currentSemester && (
                        <Tooltip>
                            <TooltipTrigger>
                                <div className="border-muted-foreground/20 bg-muted/20 flex items-center gap-1 rounded-md border border-dashed px-2 py-0.5">
                                    <IconSettings className="text-muted-foreground h-3 w-3" />
                                    <span className="text-muted-foreground text-xs">{safeAvailableSemesters[systemSemester] ?? "Default"}</span>
                                </div>
                            </TooltipTrigger>
                            <TooltipContent side="bottom">
                                <p>System default semester</p>
                            </TooltipContent>
                        </Tooltip>
                    )}
                </div>

                <div className="flex items-center gap-2">
                    <Select value={currentSchoolYearValue} onValueChange={handleSchoolYearChange}>
                        <SelectTrigger className="text-foreground h-8 w-[140px]">
                            <SelectValue placeholder="Select School Year" className="text-foreground" />
                        </SelectTrigger>
                        <SelectContent>
                            {Object.entries(safeAvailableSchoolYears).map(([key, label]) => (
                                <SelectItem key={key} value={key} className="text-foreground">
                                    {label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    {hasSchoolYearOverride && (
                        <Tooltip>
                            <TooltipTrigger asChild>
                                <div className="flex h-8 w-8 cursor-help items-center justify-center">
                                    <IconUser className="text-primary h-3 w-3" />
                                </div>
                            </TooltipTrigger>
                            <TooltipContent side="bottom">
                                <p>
                                    Your selection (System:{" "}
                                    {systemSchoolYear != null ? (safeAvailableSchoolYears[systemSchoolYear] ?? "Default") : "Default"})
                                </p>
                            </TooltipContent>
                        </Tooltip>
                    )}

                    {systemSchoolYear != null && systemSchoolYear !== currentSchoolYear && (
                        <Tooltip>
                            <TooltipTrigger>
                                <div className="border-muted-foreground/20 bg-muted/20 flex items-center gap-1 rounded-md border border-dashed px-2 py-0.5">
                                    <IconSettings className="text-muted-foreground h-3 w-3" />
                                    <span className="text-muted-foreground text-xs">{safeAvailableSchoolYears[systemSchoolYear] ?? "Default"}</span>
                                </div>
                            </TooltipTrigger>
                            <TooltipContent side="bottom">
                                <p>System default school year</p>
                            </TooltipContent>
                        </Tooltip>
                    )}
                </div>
            </div>
        </TooltipProvider>
    );
}
