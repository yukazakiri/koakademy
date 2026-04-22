/**
 * General Weighted Average (GWA) utilities.
 *
 * Centralized so both administrator and student-facing pages compute and
 * format GWAs consistently. Supports two Philippine grading conventions:
 *  - Point scale (1.0 – 5.0, lower is better, <= 3.0 passes)
 *  - Percent scale (0 – 100, higher is better, >= 75 passes)
 *
 * Scale is auto-detected per row; rows whose scale conflicts with earlier
 * rows in the same group are skipped to avoid misleading averages.
 */

export type GradeScale = "point" | "percent";
export type GradeScalePreference = GradeScale | "auto";

export interface GwaItemLike {
    units: number | string | null | undefined;
    grade: number | string | null | undefined;
    // Optional identifiers used to apply admin-configured exclusions.
    subject_id?: number | string | null;
    id?: number | string | null;
    code?: string | null;
    title?: string | null;
}

export interface GradingConfig {
    scale: GradeScalePreference;
    point_passing_grade: number;
    percent_passing_grade: number;
    point_decimal_places: number;
    percent_decimal_places: number;
    include_failed_in_gwa: boolean;
    excluded_keywords: string[];
    excluded_subject_ids: number[];
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
    scale: "auto",
    point_passing_grade: 3.0,
    percent_passing_grade: 75,
    point_decimal_places: 4,
    percent_decimal_places: 2,
    include_failed_in_gwa: true,
    excluded_keywords: ["NSTP", "OJT"],
    excluded_subject_ids: [],
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

export function detectGradeScale(grade: number): GradeScale {
    return grade <= 5 ? "point" : "percent";
}

export function isPassingGrade(grade: number, scale: GradeScale, config?: Partial<GradingConfig> | null): boolean {
    const resolved = resolveConfig(config);
    return scale === "point" ? grade <= resolved.point_passing_grade : grade >= resolved.percent_passing_grade;
}

export function computeGwa(items: GwaItemLike[], options: ComputeGwaOptions = {}): GwaResult {
    const config = resolveConfig(options.config);
    const preferredScale: GradeScale | null = config.scale === "auto" ? null : config.scale;

    let weightedSum = 0;
    let gradedUnits = 0;
    let totalUnits = 0;
    let gradedCount = 0;
    let excludedCount = 0;
    let scale: GradeScale | null = preferredScale;

    for (const item of items) {
        if (isItemExcluded(item, config)) {
            excludedCount += 1;
            continue;
        }

        const units = Number(item.units) || 0;
        totalUnits += units;

        const numericGrade = parseNumericGrade(item.grade);
        if (numericGrade === null || units <= 0) {
            continue;
        }

        const rowScale = detectGradeScale(numericGrade);
        if (scale === null) {
            scale = rowScale;
        } else if (scale !== rowScale) {
            // Mixed scales — skip conflicting row to avoid misleading averages.
            continue;
        }

        if (!config.include_failed_in_gwa && !isPassingGrade(numericGrade, rowScale, config)) {
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
    const decimals =
        result.scale === "percent" ? resolved.percent_decimal_places : resolved.point_decimal_places;
    return result.gwa.toFixed(decimals);
}

export function gwaToneClass(result: GwaResult, config?: Partial<GradingConfig> | null): string {
    if (result.gwa === null || result.scale === null) {
        return "text-muted-foreground";
    }
    return isPassingGrade(result.gwa, result.scale, config) ? "text-green-600" : "text-destructive";
}

export function gradeScaleLabel(scale: GradeScale | null): string | null {
    if (scale === null) {
        return null;
    }
    return scale === "point" ? "1.0–5.0" : "%";
}
