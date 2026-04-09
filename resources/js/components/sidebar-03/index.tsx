import { DashboardSidebar } from "@/components/sidebar-03/app-sidebar";
import { SidebarInset, SidebarProvider } from "@/components/ui/sidebar";
import { User } from "@/types/user";

export default function Sidebar03({ user }: { user: User }) {
    return (
        <SidebarProvider>
            <div className="relative flex h-screen w-full">
                <DashboardSidebar user={user} />
                <SidebarInset className="flex flex-col" />
            </div>
        </SidebarProvider>
    );
}
