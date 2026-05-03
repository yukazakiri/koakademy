import { PortalSidebar } from "@/components/portal-sidebar";
import type { ComponentProps } from "react";

export function StudentSidebar(props: ComponentProps<typeof PortalSidebar>) {
    return <PortalSidebar {...props} />;
}
