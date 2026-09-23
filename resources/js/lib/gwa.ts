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
    grade_symbol?: string | null;
    enrollment_id?: number | string | null;
    is_enrolled?: boolean;
    classification?: string | null;
    history?: GwaItemLike[];
}

export interface GradingConfig {
    name: string;
    input_type: "numeric" | "symbol";
    numeric_min: number;
    numeric_max: number;
    direction: "higher_is_better" | "lower_is_better";
    decimal_places: number;
    include_failed_in_gwa: boolean;
    gwa_formula?: "weighted_units" | "weighted_subjects" | "unweighted";
    gwa_subject_divisor_basis?: "enrolled_subjects" | "graded_subjects" | "curriculum_subjects";
    gwa_calculation_metric?: "numeric_grade" | "quality_points";
    retake_strategy?: "latest" | "highest" | "first" | "all";
    include_credited_in_gwa?: boolean;
    zero_is_dropped?: boolean;
    treat_incomplete_as?: "exclude" | "fail";
    exclude_zero_unit_subjects?: boolean;
    transferee_scale_enabled?: boolean;
    transferee_point_scale_min?: number;
    transferee_point_scale_max?: number;
    transferee_point_passing_grade?: number;
    transferee_point_direction?: "lower_is_better" | "higher_is_better";
    transferee_conversion_method?: "formula" | "table";
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
    enrolledCount: number;
    divisor: number;
    divisorType: "units" | "subjects";
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
    gwa_formula: "weighted_units",
    gwa_subject_divisor_basis: "enrolled_subjects",
    gwa_calculation_metric: "numeric_grade",
    retake_strategy: "latest",
    include_credited_in_gwa: true,
    zero_is_dropped: false,
    treat_incomplete_as: "exclude",
    exclude_zero_unit_subjects: true,
    transferee_scale_enabled: true,
    transferee_point_scale_min: 1.0,
    transferee_point_scale_max: 5.0,
    transferee_point_passing_grade: 3.0,
    transferee_point_direction: "lower_is_better",
    transferee_conversion_method: "formula",
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

export function convertTransfereePointToPercentage(
    grade: number,
    config?: Partial<GradingConfig> | null,
): { isPass: boolean; equivalent: number } {
    const resolved = resolveConfig(config);
    const pointMin = resolved.transferee_point_scale_min ?? 1.0;
    const pointMax = resolved.transferee_point_scale_max ?? 5.0;
    const pointPassing = resolved.transferee_point_passing_grade ?? 3.0;
    const pointDirection = resolved.transferee_point_direction ?? "lower_is_better";
    const method = resolved.transferee_conversion_method ?? "formula";

    const isPass = pointDirection === "lower_is_better" ? grade <= pointPassing : grade >= pointPassing;

    const passBands = resolved.bands.filter((b) => b.outcome === "pass" && typeof b.min === "number");
    const instPassing = passBands.length > 0 ? Math.min(...passBands.map((b) => b.min as number)) : 75.0;
    const instMax = passBands.length > 0 ? Math.max(...passBands.map((b) => b.max as number)) : 100.0;

    let equivalent: number;
    if (method === "table") {
        if (pointDirection === "higher_is_better") {
            if (grade >= 5.0) equivalent = 99.0;
            else if (grade >= 4.75) equivalent = 96.0;
            else if (grade >= 4.5) equivalent = 93.0;
            else if (grade >= 4.25) equivalent = 90.0;
            else if (grade >= 4.0) equivalent = 87.0;
            else if (grade >= 3.75) equivalent = 84.0;
            else if (grade >= 3.5) equivalent = 81.0;
            else if (grade >= 3.25) equivalent = 78.0;
            else if (grade >= 3.0) equivalent = instPassing;
            else if (grade >= 2.0) equivalent = Math.max(0, instPassing - 5.0);
            else equivalent = Math.max(0, instPassing - 10.0);
        } else {
            if (grade <= 1.0) equivalent = 99.0;
            else if (grade <= 1.25) equivalent = 96.0;
            else if (grade <= 1.5) equivalent = 93.0;
            else if (grade <= 1.75) equivalent = 90.0;
            else if (grade <= 2.0) equivalent = 87.0;
            else if (grade <= 2.25) equivalent = 84.0;
            else if (grade <= 2.5) equivalent = 81.0;
            else if (grade <= 2.75) equivalent = 78.0;
            else if (grade <= 3.0) equivalent = instPassing;
            else if (grade <= 4.0) equivalent = Math.max(0, instPassing - 5.0);
            else equivalent = Math.max(0, instPassing - 10.0);
        }
    } else {
        if (pointDirection === "higher_is_better") {
            if (isPass) {
                const span = Math.max(0.01, pointMax - pointPassing);
                const fraction = (grade - pointPassing) / span;
                equivalent = instPassing + fraction * (instMax - instPassing);
            } else {
                const span = Math.max(0.01, pointPassing - pointMin);
                const fraction = (pointPassing - grade) / span;
                equivalent = Math.max(0, instPassing - 1.0 - fraction * 15.0);
            }
        } else {
            if (isPass) {
                const span = Math.max(0.01, pointPassing - pointMin);
                const fraction = (pointPassing - grade) / span;
                equivalent = instPassing + fraction * (instMax - instPassing);
            } else {
                const span = Math.max(0.01, pointMax - pointPassing);
                const fraction = (grade - pointPassing) / span;
                equivalent = Math.max(0, instPassing - 1.0 - fraction * 15.0);
            }
        }
    }

    return {
        isPass,
        equivalent: Math.round(Math.max(0, Math.min(instMax, equivalent)) * 100) / 100,
    };
}

export function isTransfereeDecimalGrade(
    grade: number | string | null | undefined,
    config?: Partial<GradingConfig> | null,
    classification?: string | null,
): boolean {
    if (classification === "internal") return false;
    const numeric = parseNumericGrade(grade);
    if (numeric === null) return false;
    const resolved = resolveConfig(config);
    if ((resolved.transferee_scale_enabled ?? true) === false) return false;
    const policyMax = resolved.numeric_max ?? 100;
    const pointMin = resolved.transferee_point_scale_min ?? 1.0;
    const pointMax = resolved.transferee_point_scale_max ?? 5.0;

    return policyMax >= 50 && numeric >= pointMin && numeric <= pointMax;
}

export function resolveItemBand(
    grade: number | string | null | undefined,
    config?: Partial<GradingConfig> | null,
    classification?: string | null,
): GradingConfig["bands"][number] | null {
    if (grade === null || grade === undefined || grade === "" || grade === "-") {
        return null;
    }
    const resolved = resolveConfig(config);
    const asString = String(grade).trim().toUpperCase();

    const symbolMatch = resolved.bands.find((band) => band.symbol !== null && band.symbol.trim().toUpperCase() === asString);
    if (symbolMatch) {
        return symbolMatch;
    }

    const numericGrade = parseNumericGrade(grade);
    if (numericGrade !== null) {
        if (isTransfereeDecimalGrade(numericGrade, resolved, classification)) {
            const { isPass } = convertTransfereePointToPercentage(numericGrade, resolved);
            const targetOutcome = isPass ? "pass" : "fail";
            return resolved.bands.find((band) => band.outcome === targetOutcome) ?? null;
        }

        return (
            resolved.bands.find(
                (band) => typeof band.min === "number" && typeof band.max === "number" && numericGrade >= band.min && numericGrade <= band.max,
            ) ?? null
        );
    }

    return null;
}

export function gradeOutcome(
    grade: number | string | null | undefined,
    config?: Partial<GradingConfig> | null,
    classification?: string | null,
): GradingConfig["bands"][number]["outcome"] | null {
    const band = resolveItemBand(grade, config, classification);
    return band?.outcome ?? null;
}

export function isPassingGrade(
    grade: number | string,
    config?: Partial<GradingConfig> | null,
    classification?: string | null,
): boolean {
    const resolved = resolveConfig(config);
    return gradeOutcome(grade, resolved, classification) === "pass";
}

export function computeGwa(items: GwaItemLike[], options: ComputeGwaOptions = {}): GwaResult {
    const config = resolveConfig(options.config);
    const gwaFormula = config.gwa_formula ?? "weighted_units";
    const divisorBasis = config.gwa_subject_divisor_basis ?? "enrolled_subjects";
    const metric = config.gwa_calculation_metric ?? (config.input_type === "symbol" ? "quality_points" : "numeric_grade");
    const zeroIsDropped = config.zero_is_dropped ?? false;
    const includeCredited = config.include_credited_in_gwa ?? true;
    const treatIncompleteAs = config.treat_incomplete_as ?? "exclude";
    const excludeZeroUnits = config.exclude_zero_unit_subjects ?? true;

    let weightedSum = 0;
    let gradedUnits = 0;
    let totalUnits = 0;
    let gradedCount = 0;
    let enrolledCount = 0;
    let eligibleItemCount = 0;
    let excludedCount = 0;
    const scale: GradeScale = "numeric";

    for (const item of items) {
        if (isItemExcluded(item, config)) {
            excludedCount += 1;
            continue;
        }

        const isCredited = item.classification === "credited";
        const isNonCredited = item.classification === "non_credited";
        if (isNonCredited || (isCredited && !includeCredited)) {
            excludedCount += 1;
            continue;
        }

        const units = Number(item.units) || 0;
        if (excludeZeroUnits && units <= 0) {
            excludedCount += 1;
            continue;
        }

        totalUnits += units;
        eligibleItemCount += 1;

        const isEnrolled = item.is_enrolled !== false && (item.enrollment_id != null || item.is_enrolled === true);
        if (isEnrolled) {
            enrolledCount += 1;
        }

        const band = resolveItemBand(item.grade, config, item.classification);
        const outcome = item.grade_outcome ?? band?.outcome ?? null;
        const numericGrade = parseNumericGrade(item.grade);

        const isDropped = outcome === "withdrawn" || outcome === "dropped" || (zeroIsDropped && numericGrade === 0);
        if (isDropped) {
            continue;
        }

        if (outcome === "non_credit") {
            continue;
        }
        if (outcome === "incomplete" && treatIncompleteAs === "exclude") {
            continue;
        }

        let gradeValue: number | null = null;
        const isTransfereeDecimal = numericGrade !== null && isTransfereeDecimalGrade(numericGrade, config, item.classification);

        if (metric === "quality_points" || config.input_type === "symbol") {
            const qp = parseNumericGrade(item.grade_quality_points) ?? band?.quality_points ?? null;
            if (qp !== null) {
                gradeValue = qp;
            } else if (numericGrade !== null && !isTransfereeDecimal) {
                gradeValue = numericGrade;
            }
        } else if (isTransfereeDecimal) {
            const { equivalent } = convertTransfereePointToPercentage(numericGrade, config);
            gradeValue = equivalent;
        } else {
            gradeValue = numericGrade ?? parseNumericGrade(item.grade_quality_points);
        }

        if (gradeValue === null) {
            continue;
        }

        const isPassing = outcome === "pass" || (outcome === null && numericGrade !== null && isPassingGrade(numericGrade, config, item.classification));
        if (!config.include_failed_in_gwa && !isPassing) {
            continue;
        }

        if (gwaFormula === "unweighted") {
            weightedSum += gradeValue;
        } else {
            weightedSum += gradeValue * units;
        }

        gradedUnits += units;
        gradedCount += 1;
    }

    let divisor = 0;
    let divisorType: "units" | "subjects" = "units";

    if (gwaFormula === "weighted_units") {
        divisor = gradedUnits;
        divisorType = "units";
    } else {
        divisorType = "subjects";
        if (divisorBasis === "graded_subjects") {
            divisor = gradedCount;
        } else if (divisorBasis === "curriculum_subjects") {
            divisor = eligibleItemCount;
        } else {
            divisor = enrolledCount > 0 ? enrolledCount : gradedCount;
        }
    }

    const gwa = divisor > 0 && gradedCount > 0 ? weightedSum / divisor : null;

    return {
        gwa,
        totalUnits,
        gradedUnits,
        scale,
        itemCount: eligibleItemCount,
        gradedCount,
        enrolledCount,
        divisor,
        divisorType,
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
    const normalizedGrade =
        result.divisorType === "subjects" && result.gradedUnits > 0 ? (result.gwa * result.divisor) / result.gradedUnits : result.gwa;
    return isPassingGrade(normalizedGrade, config) ? "text-green-600" : "text-destructive";
}

export function gradeScaleLabel(_scale: GradeScale | null, config?: Partial<GradingConfig> | null): string | null {
    const resolved = resolveConfig(config);
    if (resolved.input_type !== "numeric") {
        return "symbol";
    }

    return `${resolved.numeric_min}–${resolved.numeric_max}`;
}
