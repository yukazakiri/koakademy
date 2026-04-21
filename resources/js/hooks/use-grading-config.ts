import { usePage } from "@inertiajs/react";
import { DEFAULT_GRADING_CONFIG, type GradingConfig } from "@/lib/gwa";

/**
 * Reads the shared grading configuration from Inertia shared props.
 *
 * The configuration is injected globally via `HandleInertiaRequests::share()`
 * so every authenticated page (admin and student) can pick it up without
 * needing explicit controller wiring.
 */
export function useGradingConfig(): GradingConfig {
    const { props } = usePage<{ grading?: Partial<GradingConfig> | null }>();
    const shared = props.grading ?? null;

    if (!shared) {
        return DEFAULT_GRADING_CONFIG;
    }

    return { ...DEFAULT_GRADING_CONFIG, ...shared };
}
