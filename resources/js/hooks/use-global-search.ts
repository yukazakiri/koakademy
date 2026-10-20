import { useCallback, useEffect, useRef, useState } from "react";
import { route } from "ziggy-js";

export type ClassSearchResult = {
    id: number;
    record_title: string;
    subject_code: string;
    subject_title: string;
    section: string;
    school_year: string;
    semester: string;
    classification: string;
    faculty?: string;
    students_count?: number;
    maximum_slots?: number;
    room?: string;
    courses?: string;
    url?: string | null;
};

export type ScheduleSearchResult = ClassSearchResult & { day_of_week: string; time_range: string; room: string };

export type UserSearchResult = {
    id: number;
    name: string;
    email: string;
    role: string;
    avatar: string | null;
    url?: string | null;
};

export type FacultySearchResult = {
    id: string;
    name: string;
    email: string;
    department: string;
    avatar: string | null;
    url?: string | null;
};

export type EnrollmentSearchResult = {
    id: number;
    student_name: string;
    course_code: string;
    year_level: string;
    status: string;
    school_year: string;
    semester: string;
    url?: string | null;
};

export type StudentSearchResult = {
    id: number;
    student_id: number | string;
    name: string;
    course: string | null;
    course_title: string | null;
    academic_year: string;
    type?: string | null;
    status?: string | null;
    class_count?: number;
    sections?: string[];
    url?: string | null;
};

export type SubjectSearchResult = {
    id: number;
    code: string;
    title: string;
    units: number;
    url?: string | null;
};

export type CourseSearchResult = {
    edit_url?: string | null;
    id: number;
    code: string;
    title: string;
    department: string;
    url?: string | null;
};

/** Result groups the palette can render. */
export type SearchGroup = "students" | "classes" | "users" | "faculty" | "enrollments" | "subjects" | "courses" | "schedule";

/** Narrowing the palette to one group asks the API for more rows of that group. */
export type SearchType = SearchGroup | "all";

export type SearchResults = {
    students: StudentSearchResult[];
    classes: ClassSearchResult[];
    users: UserSearchResult[];
    faculty: FacultySearchResult[];
    enrollments: EnrollmentSearchResult[];
    subjects: SubjectSearchResult[];
    courses: CourseSearchResult[];
    schedule: ScheduleSearchResult[];
};

/**
 * `idle` — nothing typed yet.
 * `too-short` — one character: nothing is fetched, the palette explains why.
 * `loading` — a request is in flight (or is about to be).
 * `success` — the last request finished; results may still be empty.
 * `error` — the last request failed; the palette says so instead of "no matches".
 */
export type SearchStatus = "idle" | "too-short" | "loading" | "success" | "error";

export type SearchScope = "administrator" | "faculty" | "student";

/** Mirrors GlobalSearchService::MIN_QUERY_LENGTH on the server. */
export const MIN_QUERY_LENGTH = 2;

/** Mirrors GlobalSearchService::RESULT_LIMIT on the server. */
export const RESULT_LIMIT = 5;

const DEBOUNCE_MS = 250;

const EMPTY_RESULTS: SearchResults = {
    students: [],
    classes: [],
    users: [],
    faculty: [],
    enrollments: [],
    subjects: [],
    courses: [],
    schedule: [],
};

function groupOrEmpty<T>(value: unknown): T[] {
    return Array.isArray(value) ? (value as T[]) : [];
}

function normalizeResults(payload: Record<string, unknown>): SearchResults {
    return {
        students: groupOrEmpty<StudentSearchResult>(payload.students),
        classes: groupOrEmpty<ClassSearchResult>(payload.classes),
        users: groupOrEmpty<UserSearchResult>(payload.users),
        faculty: groupOrEmpty<FacultySearchResult>(payload.faculty),
        enrollments: groupOrEmpty<EnrollmentSearchResult>(payload.enrollments),
        subjects: groupOrEmpty<SubjectSearchResult>(payload.subjects),
        courses: groupOrEmpty<CourseSearchResult>(payload.courses),
        schedule: groupOrEmpty<ScheduleSearchResult>(payload.schedule),
    };
}

/**
 * URLs are built from the named routes so a portal prefix can never drift out of
 * sync with the palette again (faculty search lives under `/faculty`).
 */
function searchUrls(scope: SearchScope, query: string, type: SearchType): string[] {
    if (scope === "administrator") {
        return [route("administrators.search", { q: query, type })];
    }

    if (scope === "faculty") {
        return [route("faculty.search.classes", { q: query }), route("faculty.search.students", { q: query })];
    }

    return [route("student.search", { q: query, type })];
}

export type UseGlobalSearchOptions = {
    scope: SearchScope;
    /** Raw text from the palette input. */
    query: string;
    /** Requested group, or `all` while the palette shows every group. */
    type?: SearchType;
    /** Skip fetching entirely (palette closed). */
    enabled?: boolean;
};

export type UseGlobalSearchResult = {
    status: SearchStatus;
    results: SearchResults;
    /** Total rows across groups, for "n results" affordances. */
    total: number;
    retry: () => void;
};

export function useGlobalSearch({ scope, query, type = "all", enabled = true }: UseGlobalSearchOptions): UseGlobalSearchResult {
    const trimmed = query.trim();
    const isSearchable = enabled && trimmed.length >= MIN_QUERY_LENGTH;

    const [status, setStatus] = useState<SearchStatus>("idle");
    const [results, setResults] = useState<SearchResults>(EMPTY_RESULTS);
    const [attempt, setAttempt] = useState(0);
    const abortRef = useRef<AbortController | null>(null);

    const retry = useCallback(() => {
        setAttempt((value) => value + 1);
    }, []);

    useEffect(() => {
        abortRef.current?.abort();
        abortRef.current = null;

        if (! enabled || trimmed.length === 0) {
            setStatus("idle");
            setResults(EMPTY_RESULTS);
            return;
        }

        if (! isSearchable) {
            setStatus("too-short");
            setResults(EMPTY_RESULTS);
            return;
        }

        // Show the loading state immediately: waiting for the debounce before
        // switching away from "no matches" made the palette flash an empty state.
        setStatus("loading");
        setResults(EMPTY_RESULTS);

        const abortController = new AbortController();
        abortRef.current = abortController;

        const timer = window.setTimeout(async () => {
            try {
                const responses = await Promise.all(
                    searchUrls(scope, trimmed, type).map((url) =>
                        fetch(url, {
                            method: "GET",
                            headers: {
                                Accept: "application/json",
                                "X-Requested-With": "XMLHttpRequest",
                            },
                            signal: abortController.signal,
                        }),
                    ),
                );

                if (responses.some((response) => ! response.ok)) {
                    throw new Error(`Search failed with status ${responses.map((response) => response.status).join(", ")}`);
                }

                const payloads = (await Promise.all(responses.map((response) => response.json()))) as Record<string, unknown>[];

                // Faculty answers come from one endpoint per group; merge them.
                const merged = payloads.reduce<Record<string, unknown>>((carry, payload) => ({ ...carry, ...payload }), {});

                setResults(normalizeResults(merged));
                setStatus("success");
            } catch (error: unknown) {
                if (error instanceof DOMException && error.name === "AbortError") {
                    return;
                }

                setResults(EMPTY_RESULTS);
                setStatus("error");
            }
        }, DEBOUNCE_MS);

        return () => {
            abortController.abort();
            window.clearTimeout(timer);
        };
    }, [attempt, enabled, isSearchable, scope, trimmed, type]);

    const total =
        results.students.length +
        results.classes.length +
        results.users.length +
        results.faculty.length +
        results.enrollments.length +
        results.subjects.length +
        results.courses.length + results.schedule.length;

    return { status, results, total, retry };
}
