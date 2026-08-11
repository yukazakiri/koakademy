import { useForm } from "@inertiajs/react";
import { useMemo, useState } from "react";
import { route } from "ziggy-js";
import {
    buildClassDefaults,
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
    const payload = (await response.json()) as Partial<OptionsResponse<unknown>>;
    const rows = Array.isArray(payload.data) ? payload.data : [];

    return rows.filter(guard);
}

export function useClassForm(defaults: ClassDefaultsInput, initialData?: ClassFormData): UseClassFormReturn {
    const form = useForm<ClassFormData>(initialData ?? buildClassDefaults(defaults));
    const [subjectCodeTouched, setSubjectCodeTouched] = useState(false);
    const [collegeSubjects, setCollegeSubjects] = useState<SubjectOption[]>([]);
    const [collegeSubjectsLoading, setCollegeSubjectsLoading] = useState(false);

    const loadCollegeSubjects = async (courseIds: number[]): Promise<SubjectOption[]> => {
        if (courseIds.length === 0) {
            setCollegeSubjects([]);
            return [];
        }

        setCollegeSubjectsLoading(true);
        try {
            const subjects = await fetchOptionData<SubjectOption>(
                route("administrators.classes.options.subjects", { course_ids: courseIds }),
                isSubjectOption,
            );
            setCollegeSubjects(subjects);
            return subjects;
        } finally {
            setCollegeSubjectsLoading(false);
        }
    };

    const loadShsStrands = (trackId: number): Promise<EntityOption[]> => {
        return fetchOptionData<EntityOption>(route("administrators.classes.options.shs-strands", { track_id: trackId }), isEntityOption);
    };

    const loadShsSubjects = (strandId: number): Promise<ShsSubjectOption[]> => {
        return fetchOptionData<ShsSubjectOption>(route("administrators.classes.options.shs-subjects", { strand_id: strandId }), isShsSubjectOption);
    };

    const step1Valid = useMemo(() => step1IsValid(form.data, defaults.options), [defaults.options, form.data]);
    const step2Valid = useMemo(() => step2IsValid(form.data), [form.data]);

    return {
        form,
        subjectCodeTouched,
        setSubjectCodeTouched,
        collegeSubjects,
        collegeSubjectsLoading,
        loadCollegeSubjects,
        loadShsStrands,
        loadShsSubjects,
        step1Valid,
        step2Valid,
    };
}
