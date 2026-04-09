import { BadgeCheck, ChevronsUpDown, LogOut, School, Settings } from "lucide-react";
import * as React from "react";

import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from "@/components/ui/alert-dialog";
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuSeparator,
    DropdownMenuSub,
    DropdownMenuSubContent,
    DropdownMenuSubTrigger,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { SidebarMenu, SidebarMenuButton, SidebarMenuItem, useSidebar } from "@/components/ui/sidebar";
import { isFacultyPortalRole, isStudentPortalRole, normalizePortalRole } from "@/lib/portal-role";
import { Link, router, usePage } from "@inertiajs/react";

export function NavUser({
    user,
}: {
    user: {
        name: string;
        email: string;
        avatar: string;
        role?: string;
    };
}) {
    const { isMobile } = useSidebar();
    const { props, url } = usePage<{
        settings?: {
            activeSchoolId?: string | number;
            availableSchools?: Array<{
                id: number;
                name: string;
                code?: string | null;
            }>;
        };
        auth?: {
            user?: {
                role?: string | null;
            } | null;
        };
    }>();
    const [isMenuOpen, setIsMenuOpen] = React.useState(false);
    const [showLogoutDialog, setShowLogoutDialog] = React.useState(false);

    const settings = props.settings;
    const pathname = url.split("?")[0];
    const normalizedRole = normalizePortalRole(props.auth?.user?.role ?? user.role);
    const isStudentContext = pathname.startsWith("/student") || isStudentPortalRole(normalizedRole);
    const isFacultyContext = pathname.startsWith("/faculty") || isFacultyPortalRole(normalizedRole);
    const isAdministratorContext = pathname.startsWith("/administrators") || (!isStudentContext && !isFacultyContext);

    const accountHref = isStudentContext ? "/student/profile" : isFacultyContext ? "/faculty/profile" : "/administrators/settings";
    const activeSchoolEndpoint = isAdministratorContext
        ? "/administrators/settings/active-school"
        : isStudentContext
          ? "/student/settings/active-school"
          : isFacultyContext
            ? "/faculty/settings/active-school"
            : "/settings/active-school";

    const activeSchoolId = settings?.activeSchoolId?.toString();
    const availableSchools = settings?.availableSchools || [];

    const handleSchoolChange = (value: string) => {
        if (!value || value === activeSchoolId) {
            return;
        }

        router.put(
            activeSchoolEndpoint,
            {
                school_id: Number.parseInt(value, 10),
            },
            {
                preserveScroll: true,
            },
        );
    };

    const openLogoutDialog = () => {
        setIsMenuOpen(false);

        window.requestAnimationFrame(() => {
            setShowLogoutDialog(true);
        });
    };

    return (
        <SidebarMenu>
            <SidebarMenuItem>
                <DropdownMenu open={isMenuOpen} onOpenChange={setIsMenuOpen}>
                    <DropdownMenuTrigger asChild>
                        <SidebarMenuButton
                            size="lg"
                            className="data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground md:h-8 md:p-0"
                        >
                            <Avatar className="h-8 w-8 rounded-lg">
                                <AvatarImage src={user.avatar} alt={user.name} />
                                <AvatarFallback className="rounded-lg">{user.name.substring(0, 2).toUpperCase()}</AvatarFallback>
                            </Avatar>
                            <div className="grid flex-1 text-left text-sm leading-tight">
                                <span className="truncate font-medium">{user.name}</span>
                                <span className="truncate text-xs">{user.email}</span>
                            </div>
                            <ChevronsUpDown className="ml-auto size-4" />
                        </SidebarMenuButton>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent
                        className="w-(--radix-dropdown-menu-trigger-width) min-w-56 rounded-lg"
                        side={isMobile ? "bottom" : "right"}
                        align="end"
                        sideOffset={4}
                    >
                        <DropdownMenuLabel className="p-0 font-normal">
                            <div className="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
                                <Avatar className="h-8 w-8 rounded-lg">
                                    <AvatarImage src={user.avatar} alt={user.name} />
                                    <AvatarFallback className="rounded-lg">{user.name.substring(0, 2).toUpperCase()}</AvatarFallback>
                                </Avatar>
                                <div className="grid flex-1 text-left text-sm leading-tight">
                                    <span className="truncate font-medium">{user.name}</span>
                                    <span className="truncate text-xs">{user.email}</span>
                                </div>
                            </div>
                        </DropdownMenuLabel>

                        {availableSchools.length > 0 && (
                            <>
                                <DropdownMenuSeparator />
                                <DropdownMenuGroup>
                                    <DropdownMenuSub>
                                        <DropdownMenuSubTrigger>
                                            <School className="mr-2 size-4" />
                                            <span>Switch Active School</span>
                                        </DropdownMenuSubTrigger>
                                        <DropdownMenuSubContent>
                                            <DropdownMenuRadioGroup value={activeSchoolId} onValueChange={handleSchoolChange}>
                                                {availableSchools.map((school) => (
                                                    <DropdownMenuRadioItem key={school.id} value={school.id.toString()}>
                                                        {school.name} {school.code && `(${school.code})`}
                                                    </DropdownMenuRadioItem>
                                                ))}
                                            </DropdownMenuRadioGroup>
                                        </DropdownMenuSubContent>
                                    </DropdownMenuSub>
                                </DropdownMenuGroup>
                            </>
                        )}

                        <DropdownMenuSeparator />
                        <DropdownMenuGroup>
                            <DropdownMenuItem asChild>
                                <Link href={accountHref} className="flex w-full cursor-pointer items-center">
                                    <BadgeCheck className="mr-2 size-4" />
                                    Account
                                </Link>
                            </DropdownMenuItem>
                            {isAdministratorContext && (
                                <DropdownMenuItem asChild>
                                    <Link href="/administrators/system-management" className="flex w-full cursor-pointer items-center">
                                        <Settings className="mr-2 size-4" />
                                        System Settings
                                    </Link>
                                </DropdownMenuItem>
                            )}
                        </DropdownMenuGroup>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem className="cursor-pointer text-red-600 focus:bg-red-50 focus:text-red-600" onClick={openLogoutDialog}>
                            <LogOut className="mr-2 size-4" />
                            Log out
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
                <AlertDialog open={showLogoutDialog} onOpenChange={setShowLogoutDialog}>
                    <AlertDialogContent className="sm:max-w-md">
                        <AlertDialogHeader>
                            <AlertDialogTitle>Log out of your account?</AlertDialogTitle>
                            <AlertDialogDescription>
                                You will be redirected to the login page. Any unsaved changes may be lost.
                            </AlertDialogDescription>
                        </AlertDialogHeader>
                        <AlertDialogFooter>
                            <AlertDialogCancel>Cancel</AlertDialogCancel>
                            <AlertDialogAction className="bg-red-600 text-white hover:bg-red-700" onClick={() => router.post("/logout")}>
                                Log out
                            </AlertDialogAction>
                        </AlertDialogFooter>
                    </AlertDialogContent>
                </AlertDialog>
            </SidebarMenuItem>
        </SidebarMenu>
    );
}
