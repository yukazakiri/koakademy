import { PortalSidebar } from "@/components/portal-sidebar";
import type { ComponentProps } from "react";

export function FacultySidebar(props: ComponentProps<typeof PortalSidebar>) {
    return <PortalSidebar {...props} />;
}
