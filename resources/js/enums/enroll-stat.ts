/**
 * Enrollment status constants — mirrors App\Enums\EnrollStat on the backend.
 * Single source of truth for status values, labels, and colours on the frontend.
 */

export const ENROLL_STAT = {
    Pending: "Pending",
    VerifiedByDeptHead: "Verified By Dept Head",
    VerifiedByCashier: "Verified By Cashier",
} as const;

export type EnrollStatValue = (typeof ENROLL_STAT)[keyof typeof ENROLL_STAT];

export const ENROLL_STAT_LABELS: Record<EnrollStatValue, string> = {
    [ENROLL_STAT.Pending]: "Pending",
    [ENROLL_STAT.VerifiedByDeptHead]: "Verified By Dept Head",
    [ENROLL_STAT.VerifiedByCashier]: "Verified By Cashier",
};

/**
 * Tailwind class pairs for each status — bg + text, light and dark mode.
 */
export const ENROLL_STAT_CLASSES: Record<EnrollStatValue, string> = {
    [ENROLL_STAT.Pending]: "bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400",
    [ENROLL_STAT.VerifiedByDeptHead]: "bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400",
    [ENROLL_STAT.VerifiedByCashier]: "bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400",
};

/**
 * Returns the Tailwind colour classes for a given status string.
 * Falls back to a neutral grey if the value is unrecognised.
 */
export function getEnrollStatClasses(status: string | null | undefined): string {
    if (!status) return "bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400";
    return ENROLL_STAT_CLASSES[status as EnrollStatValue] ?? "bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400";
}

/** All statuses as an ordered array, useful for building select options. */
export const ENROLL_STAT_OPTIONS: { value: EnrollStatValue; label: string }[] = [
    { value: ENROLL_STAT.Pending, label: ENROLL_STAT_LABELS[ENROLL_STAT.Pending] },
    { value: ENROLL_STAT.VerifiedByDeptHead, label: ENROLL_STAT_LABELS[ENROLL_STAT.VerifiedByDeptHead] },
    { value: ENROLL_STAT.VerifiedByCashier, label: ENROLL_STAT_LABELS[ENROLL_STAT.VerifiedByCashier] },
];
