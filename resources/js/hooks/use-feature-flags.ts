import { usePage } from "@inertiajs/react";

interface FeatureFlags {
    experimentalKeys?: string[];
    enabledRoutes?: Record<string, boolean>;
    studentSignaturePad?: boolean;
    studentAvatarUpload?: boolean;
}

interface PageWithFlags {
    featureFlags?: FeatureFlags;
}

export function useFeatureFlags(): FeatureFlags {
    const { props } = usePage<PageWithFlags>();

    return props.featureFlags ?? {};
}
