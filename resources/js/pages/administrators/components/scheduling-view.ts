import type { ClassScheduleData } from "./scheduling-types";

export function matchesPlannerGroup(
    item: Pick<ClassScheduleData, "course_ids" | "year_levels" | "course_year_levels">,
    course: string,
    year: string,
): boolean {
    if (course !== "all" && !(item.course_ids ?? []).some((id) => String(id) === course)) return false;
    const years = course === "all" ? item.year_levels : (item.course_year_levels?.[course] ?? item.year_levels);
    return year === "all" || years.includes(year);
}
