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
    hub?: {
        url: string;
        qr_svg_url: string;
        qr_png_url: string;
        sheet_pdf_url: string;
    } | null;
    hubSettings?: Record<string, unknown> | null;
    headerMeta?: Record<string, unknown> | null;
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
