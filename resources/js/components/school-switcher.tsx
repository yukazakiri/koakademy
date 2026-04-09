import { ChevronsUpDown, School } from "lucide-react";

import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuShortcut,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { SidebarMenu, SidebarMenuButton, SidebarMenuItem, useSidebar } from "@/components/ui/sidebar";
import { router, usePage } from "@inertiajs/react";

export function SchoolSwitcher() {
    const { isMobile } = useSidebar();
    const { settings } = usePage<{ settings: any }>().props;

    const activeSchoolId = settings?.activeSchoolId?.toString();
    const availableSchools = settings?.availableSchools || [];

    // If no schools available, or just 1, we might just show the static text,
    // but let's assume we show the switcher if we have schools
    if (!availableSchools || availableSchools.length === 0) return null;

    const activeSchool = availableSchools.find((s: any) => s.id.toString() === activeSchoolId) || availableSchools[0];

    const handleSchoolChange = (value: string) => {
        if (!value || value === activeSchoolId) return;

        let endpoint = "/settings/active-school";
        const pathname = window.location.pathname;
        if (pathname.startsWith("/administrators")) {
            endpoint = "/administrators/settings/active-school";
        } else if (pathname.startsWith("/student")) {
            endpoint = "/student/settings/active-school";
        }

        router.put(endpoint, { school_id: parseInt(value) }, { preserveScroll: true });
    };

    return (
        <SidebarMenu>
            <SidebarMenuItem>
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <SidebarMenuButton
                            size="lg"
                            className="data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground mb-2 border shadow-sm"
                        >
                            <div className="bg-primary/10 text-primary flex aspect-square size-8 items-center justify-center rounded-lg">
                                <School className="size-4" />
                            </div>
                            <div className="grid flex-1 text-left text-sm leading-tight">
                                <span className="truncate font-semibold">{activeSchool?.name || "Select School"}</span>
                                <span className="text-muted-foreground truncate text-xs">{activeSchool?.code || "No active branch"}</span>
                            </div>
                            <ChevronsUpDown className="ml-auto size-4" />
                        </SidebarMenuButton>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent
                        className="w-[--radix-dropdown-menu-trigger-width] min-w-56 rounded-lg"
                        align="start"
                        side={isMobile ? "bottom" : "right"}
                        sideOffset={4}
                    >
                        <DropdownMenuLabel className="text-muted-foreground text-xs">Schools & Branches</DropdownMenuLabel>
                        {availableSchools.map((school: any) => (
                            <DropdownMenuItem
                                key={school.id}
                                onClick={() => handleSchoolChange(school.id.toString())}
                                className="cursor-pointer gap-2 p-2"
                            >
                                <div className="flex size-6 items-center justify-center rounded-sm border">
                                    <School className="size-4 shrink-0" />
                                </div>
                                <span className="truncate">{school.name}</span>
                                {school.id.toString() === activeSchoolId && (
                                    <DropdownMenuShortcut className="text-primary ml-auto opacity-100">Active</DropdownMenuShortcut>
                                )}
                            </DropdownMenuItem>
                        ))}
                    </DropdownMenuContent>
                </DropdownMenu>
            </SidebarMenuItem>
        </SidebarMenu>
    );
}
