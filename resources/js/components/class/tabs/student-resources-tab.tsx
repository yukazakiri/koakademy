import type { ComponentType } from "react";
import type { OnlineResourceCategoryPayload } from "./resources-tab";

export interface StudentResourcesTabProps {
    categories: OnlineResourceCategoryPayload[];
}

interface ModuleExport {
    StudentResourcesTab?: ComponentType<StudentResourcesTabProps>;
}

const modules = import.meta.glob<ModuleExport>(
    "../../../../../vendor/koakademy/online-resources/resources/assets/js/components/student-resources-tab.tsx",
    { eager: true },
);

const ModuleStudentResourcesTab = Object.values(modules)[0]?.StudentResourcesTab;

export function StudentResourcesTab(props: StudentResourcesTabProps) {
    if (!ModuleStudentResourcesTab) {
        return null;
    }

    return <ModuleStudentResourcesTab {...props} />;
}
