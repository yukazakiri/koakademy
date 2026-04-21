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

export interface GwaItemLike {
    units: number | string | null | undefined;
    grade: number | string | null | undefined;
}

export interface GwaResult {
    gwa: number | null;
    totalUnits: number;
    gradedUnits: number;
    scale: GradeScale | null;
    itemCount: number;
    gradedCount: number;
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

export function isPassingGrade(grade: number, scale: GradeScale): boolean {
    return scale === "point" ? grade <= 3.0 : grade >= 75;
}

export function computeGwa(items: GwaItemLike[]): GwaResult {
    let weightedSum = 0;
    let gradedUnits = 0;
    let totalUnits = 0;
    let gradedCount = 0;
    let scale: GradeScale | null = null;

    for (const item of items) {
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
    };
}

export function formatGwa(result: GwaResult): string {
    if (result.gwa === null) {
        return "—";
    }
    return result.scale === "percent" ? result.gwa.toFixed(2) : result.gwa.toFixed(4);
}

export function gwaToneClass(result: GwaResult): string {
    if (result.gwa === null || result.scale === null) {
        return "text-muted-foreground";
    }
    return isPassingGrade(result.gwa, result.scale) ? "text-green-600" : "text-destructive";
}

export function gradeScaleLabel(scale: GradeScale | null): string | null {
    if (scale === null) {
        return null;
    }
    return scale === "point" ? "1.0–5.0" : "%";
}
