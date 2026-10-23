import type { AgentRoleKey } from "@/components/ai/use-ai-chat";

export interface AiFeatureFlags {
    any: boolean;
    agents: Partial<Record<AgentRoleKey, boolean>>;
}

export interface FeatureFlags {
    experimentalKeys?: string[];
    enabledRoutes?: Record<string, boolean>;
    library?: boolean;
    ai?: AiFeatureFlags;
    studentSignaturePad?: boolean;
    studentAvatarUpload?: boolean;
    studentInformationUpdates?: boolean;
}
