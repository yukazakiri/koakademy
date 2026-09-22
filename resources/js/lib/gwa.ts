/**
 * General Weighted Average (GWA) utilities.
 *
 * Centralized so both administrator and student-facing pages compute and
 * format GWAs consistently. The active school policy declares numerical
 * direction, grade bands, and precision instead of assuming a country-specific
 * score range or passing threshold.
 */

export type GradeScale = "numeric";

export interface GwaItemLike {
    units: number | string | null | undefined;
    grade: number | string | null | undefined;
    // Optional identifiers used to apply admin-configured exclusions.
    subject_id?: number | string | null;
    id?: number | string | null;
    code?: string | null;
    title?: string | null;
    grade_quality_points?: number | string | null;
    grade_outcome?: string | null;
}

export interface GradingConfig {
    name: string;
    input_type: "numeric" | "symbol";
    numeric_min: number;
    numeric_max: number;
    direction: "higher_is_better" | "lower_is_better";
    decimal_places: number;
    include_failed_in_gwa: boolean;
    excluded_keywords: string[];
    excluded_subject_ids: number[];
    bands: Array<{
        id: string;
        symbol: string | null;
        label: string;
        min: number | null;
        max: number | null;
        outcome: "pass" | "fail" | "incomplete" | "withdrawn" | "non_credit";
        quality_points: number | null;
        color: string;
        sort_order: number;
    }>;
    components: Array<{
        id: string;
        key: string;
        label: string;
        weight: number;
        required: boolean;
        sort_order: number;
    }>;
    policy_version_id?: number;
    policy_version?: number;
}

export interface GwaResult {
    gwa: number | null;
    totalUnits: number;
    gradedUnits: number;
    scale: GradeScale | null;
    itemCount: number;
    gradedCount: number;
    excludedCount: number;
}

export const DEFAULT_GRADING_CONFIG: GradingConfig = {
    name: "Default grading policy",
    input_type: "numeric",
    numeric_min: 0,
    numeric_max: 100,
    direction: "higher_is_better",
    decimal_places: 2,
    include_failed_in_gwa: true,
    excluded_keywords: [],
    excluded_subject_ids: [],
    bands: [
        { id: "pass", symbol: null, label: "Passing", min: 75, max: 100, outcome: "pass", quality_points: null, color: "success", sort_order: 0 },
        {
            id: "fail",
            symbol: null,
            label: "Failing",
            min: 0,
            max: 74.9999,
            outcome: "fail",
            quality_points: null,
            color: "destructive",
            sort_order: 1,
        },
    ],
    components: [
        { id: "prelim", key: "prelim", label: "Prelim", weight: 30, required: true, sort_order: 0 },
        { id: "midterm", key: "midterm", label: "Midterm", weight: 30, required: true, sort_order: 1 },
        { id: "final", key: "final", label: "Final", weight: 40, required: true, sort_order: 2 },
    ],
};

export interface ComputeGwaOptions {
    config?: Partial<GradingConfig> | null;
}

function resolveConfig(config: Partial<GradingConfig> | null | undefined): GradingConfig {
    if (!config) {
        return DEFAULT_GRADING_CONFIG;
    }
    return { ...DEFAULT_GRADING_CONFIG, ...config };
}

function itemSubjectId(item: GwaItemLike): number | null {
    const raw = item.subject_id ?? item.id;
    if (raw === null || raw === undefined || raw === "") {
        return null;
    }
    const asNumber = typeof raw === "number" ? raw : Number(raw);
    return Number.isFinite(asNumber) ? asNumber : null;
}

function itemMatchesKeyword(item: GwaItemLike, keywords: string[]): boolean {
    if (keywords.length === 0) {
        return false;
    }
    const haystack = `${item.code ?? ""} ${item.title ?? ""}`.toLowerCase();
    if (!haystack.trim()) {
        return false;
    }
    return keywords.some((keyword) => {
        const needle = keyword.trim().toLowerCase();
        return needle !== "" && haystack.includes(needle);
    });
}

export function isItemExcluded(item: GwaItemLike, config: GradingConfig): boolean {
    const id = itemSubjectId(item);
    if (id !== null && config.excluded_subject_ids.includes(id)) {
        return true;
    }
    return itemMatchesKeyword(item, config.excluded_keywords);
}

export function parseNumericGrade(grade: number | string | null | undefined): number | null {
    if (grade === null || grade === undefined || grade === "" || grade === "-") {
        return null;
    }
    const parsed = typeof grade === "number" ? grade : Number(grade);
    if (!Number.isFinite(parsed)) {
        return null;
    }
    return parsed;
}

export function gradeOutcome(
    grade: number | string | null | undefined,
    config?: Partial<GradingConfig> | null,
): GradingConfig["bands"][number]["outcome"] | null {
    const numericGrade = parseNumericGrade(grade);
    if (numericGrade === null) {
        return null;
    }

    const resolved = resolveConfig(config);
    return (
        resolved.bands.find(
            (band) => typeof band.min === "number" && typeof band.max === "number" && numericGrade >= band.min && numericGrade <= band.max,
        )?.outcome ?? null
    );
}

export function isPassingGrade(grade: number, config?: Partial<GradingConfig> | null): boolean {
    const resolved = resolveConfig(config);
    return gradeOutcome(grade, resolved) === "pass";
}

export function computeGwa(items: GwaItemLike[], options: ComputeGwaOptions = {}): GwaResult {
    const config = resolveConfig(options.config);
    let weightedSum = 0;
    let gradedUnits = 0;
    let totalUnits = 0;
    let gradedCount = 0;
    let excludedCount = 0;
    const scale: GradeScale = "numeric";

    for (const item of items) {
        if (isItemExcluded(item, config)) {
            excludedCount += 1;
            continue;
        }

        const units = Number(item.units) || 0;
        totalUnits += units;

        const numericGrade = parseNumericGrade(item.grade) ?? parseNumericGrade(item.grade_quality_points);
        if (numericGrade === null || units <= 0) {
            continue;
        }

        const isPassing = item.grade_outcome ? item.grade_outcome === "pass" : isPassingGrade(numericGrade, config);
        if (!config.include_failed_in_gwa && !isPassing) {
            continue;
        }

        weightedSum += numericGrade * units;
        gradedUnits += units;
        gradedCount += 1;
    }

    return {
        gwa: gradedUnits > 0 ? weightedSum / gradedUnits : null,
        totalUnits,
        gradedUnits,
        scale,
        itemCount: items.length,
        gradedCount,
        excludedCount,
    };
}

export function formatGwa(result: GwaResult, config?: Partial<GradingConfig> | null): string {
    if (result.gwa === null) {
        return "—";
    }
    const resolved = resolveConfig(config);
    return result.gwa.toFixed(resolved.decimal_places);
}

export function gwaToneClass(result: GwaResult, config?: Partial<GradingConfig> | null): string {
    if (result.gwa === null || result.scale === null) {
        return "text-muted-foreground";
    }
    return isPassingGrade(result.gwa, config) ? "text-green-600" : "text-destructive";
}

export function gradeScaleLabel(_scale: GradeScale | null, config?: Partial<GradingConfig> | null): string | null {
    const resolved = resolveConfig(config);
    if (resolved.input_type !== "numeric") {
        return "symbol";
    }

    return `${resolved.numeric_min}–${resolved.numeric_max}`;
}
