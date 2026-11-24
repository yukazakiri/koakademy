import type { ComponentType } from "react";

export interface OnlineResourceCategoryPayload {
    id: number;
    [key: string]: unknown;
}

export interface CopyableClassOptionPayload {
    id: number;
    label: string;
    term: string;
}

export interface ClassResourcesTabProps {
    classId: number;
    categories: OnlineResourceCategoryPayload[];
    copyableClasses?: CopyableClassOptionPayload[];
    enableOnlineResources: boolean;
    onOpenSettings?: () => void;
}

interface ModuleExport {
    ClassResourcesTab?: ComponentType<ClassResourcesTabProps>;
}

const modules = import.meta.glob<ModuleExport>(
    "../../../../../vendor/koakademy/online-resources/resources/assets/js/components/class-resources-tab.tsx",
    { eager: true },
);

const ModuleClassResourcesTab = Object.values(modules)[0]?.ClassResourcesTab;

export function ClassResourcesTab(props: ClassResourcesTabProps) {
    if (!ModuleClassResourcesTab) {
        return null;
    }

    return <ModuleClassResourcesTab {...props} />;
}
