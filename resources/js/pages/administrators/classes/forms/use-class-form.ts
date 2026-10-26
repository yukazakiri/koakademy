import { shsStrands, shsSubjects, subjects as subjectOptions } from "@/routes/administrators/classes/options";
import { useForm } from "@inertiajs/react";
import { useMemo, useRef, useState } from "react";
import {
    buildClassDefaults,
    normalizeClassDelivery,
    step1IsValid,
    step2IsValid,
    type ClassDefaultsInput,
    type ClassFormData,
    type EntityOption,
    type ShsSubjectOption,
    type SubjectOption,
    type ValidationResult,
} from "../lib/class-defaults";

type UseClassFormReturn = {
    form: ReturnType<typeof useForm<ClassFormData>>;
    subjectCodeTouched: boolean;
    setSubjectCodeTouched: (touched: boolean) => void;
    collegeSubjects: SubjectOption[];
    collegeSubjectsLoading: boolean;
    loadedCourseKey: string | null;
    collegeSubjectsError: string | null;
    loadCollegeSubjects: (courseIds: number[]) => Promise<SubjectOption[]>;
    loadShsStrands: (trackId: number) => Promise<EntityOption[]>;
    loadShsSubjects: (strandId: number) => Promise<ShsSubjectOption[]>;
    step1Valid: ValidationResult;
    step2Valid: ValidationResult;
};

type OptionsResponse<T> = { data: T[] };

function isSubjectOption(value: unknown): value is SubjectOption {
    return (
        typeof value === "object" &&
        value !== null &&
        "id" in value &&
        "label" in value &&
        "code" in value &&
        "course_id" in value &&
        "academic_year" in value
    );
}

function isEntityOption(value: unknown): value is EntityOption {
    return typeof value === "object" && value !== null && "id" in value && "label" in value;
}

function isShsSubjectOption(value: unknown): value is ShsSubjectOption {
    return typeof value === "object" && value !== null && "code" in value && "label" in value;
}

async function fetchOptionData<T>(url: string, guard: (value: unknown) => value is T): Promise<T[]> {
    const response = await fetch(url, { headers: { Accept: "application/json" } });
    if (!response.ok) throw new Error("Options could not be loaded.");
    const payload = (await response.json()) as Partial<OptionsResponse<unknown>>;
    const rows = Array.isArray(payload.data) ? payload.data : [];

    return rows.filter(guard);
}

export function useClassForm(defaults: ClassDefaultsInput, initialData?: ClassFormData): UseClassFormReturn {
    const form = useForm<ClassFormData>(normalizeClassDelivery(initialData ?? buildClassDefaults(defaults)));
    const [subjectCodeTouched, setSubjectCodeTouched] = useState(Boolean(initialData?.subject_code));
    const [collegeSubjects, setCollegeSubjects] = useState<SubjectOption[]>([]);
    const [collegeSubjectsLoading, setCollegeSubjectsLoading] = useState(false);

    const [loadedCourseKey, setLoadedCourseKey] = useState<string | null>(null);
    const [collegeSubjectsError, setCollegeSubjectsError] = useState<string | null>(null);
    const latestRequest = useRef(0);

    const loadCollegeSubjects = async (courseIds: number[]): Promise<SubjectOption[]> => {
        const requestId = ++latestRequest.current;
        const courseKey = courseIds.join(",");
        setCollegeSubjectsError(null);
        if (courseIds.length === 0) {
            setCollegeSubjects([]);
            setLoadedCourseKey(courseKey);
            setCollegeSubjectsLoading(false);
            return [];
        }

        setCollegeSubjectsLoading(true);
        try {
            const subjects = await fetchOptionData<SubjectOption>(subjectOptions.url({ query: { course_ids: courseIds } }), isSubjectOption);
            if (requestId === latestRequest.current) {
                setCollegeSubjects(subjects);
                setLoadedCourseKey(courseKey);
            }
            return subjects;
        } catch {
            if (requestId === latestRequest.current) {
                setCollegeSubjectsError("Subjects could not be loaded. Check your connection and try again.");
            }
            return [];
        } finally {
            if (requestId === latestRequest.current) setCollegeSubjectsLoading(false);
        }
    };

    const loadShsStrands = (trackId: number): Promise<EntityOption[]> => {
        return fetchOptionData<EntityOption>(shsStrands.url({ query: { track_id: trackId } }), isEntityOption);
    };

    const loadShsSubjects = (strandId: number): Promise<ShsSubjectOption[]> => {
        return fetchOptionData<ShsSubjectOption>(shsSubjects.url({ query: { strand_id: strandId } }), isShsSubjectOption);
    };

    const step1Valid = useMemo(() => step1IsValid(form.data, defaults.options), [defaults.options, form.data]);
    const step2Valid = useMemo(() => step2IsValid(form.data), [form.data]);

    return {
        form,
        subjectCodeTouched,
        setSubjectCodeTouched,
        collegeSubjects,
        collegeSubjectsLoading,
        loadedCourseKey,
        collegeSubjectsError,
        loadCollegeSubjects,
        loadShsStrands,
        loadShsSubjects,
        step1Valid,
        step2Valid,
    };
}
