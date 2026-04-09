import { triggerGlobalCommandPalette } from "@/components/global-command-palette";
import { SemesterSelector, SemesterSelectorProps } from "@/components/semester-selector";
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
import { AnimatedThemeToggler } from "@/components/ui/animated-theme-toggler";
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Separator } from "@/components/ui/separator";
import { SidebarTrigger } from "@/components/ui/sidebar";
import { useIsMobile } from "@/hooks/use-mobile";
import { User } from "@/types/user";
import { Link, router, usePage } from "@inertiajs/react";
import { IconSearch } from "@tabler/icons-react";
import { ChevronDown, LogOut, Settings } from "lucide-react";
import { useEffect, useState } from "react";

interface SiteHeaderProps {
    user?: User;
}

export function SiteHeader({ user }: SiteHeaderProps) {
    const { settings } = usePage<{ settings: SemesterSelectorProps }>().props;
    const [shortcutHint, setShortcutHint] = useState("Ctrl K");
    const [showLogoutDialog, setShowLogoutDialog] = useState(false);
    const isMobile = useIsMobile();

    useEffect(() => {
        if (typeof window === "undefined") {
            return;
        }

        const isMac = window.navigator.userAgent.toLowerCase().includes("mac");
        setShortcutHint(isMac ? "⌘ K" : "Ctrl K");
    }, []);

    const role = user?.role.toLowerCase() || "";
    const isFaculty = ["professor", "associate_professor", "assistant_professor", "instructor", "part_time_faculty"].includes(role);
    const isStudent = ["student", "graduate_student", "shs_student"].includes(role);

    const profileLink = isFaculty ? "/faculty/profile" : isStudent ? "/student/profile" : "/profile";

    return (
        <header className="mt-4 flex h-(--header-height) shrink-0 items-center gap-2 border-b pb-3 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-(--header-height)">
            <div className="flex w-full items-center gap-1 px-4 lg:gap-2 lg:px-6">
                <SidebarTrigger className="-ml-1" />
                <Separator orientation="vertical" className="mx-2 data-[orientation=vertical]:h-4" />
                <h1 className="text-foreground text-base font-medium">Dashboard</h1>

                <button
                    type="button"
                    onClick={() => triggerGlobalCommandPalette()}
                    className="bg-background text-muted-foreground hover:bg-accent hover:text-accent-foreground hidden h-9 w-full max-w-sm items-center gap-2 rounded-md border px-3 text-left text-sm md:flex"
                >
                    <IconSearch className="h-4 w-4" />
                    <span className="flex-1 truncate">Search classes and students…</span>
                    <span className="text-muted-foreground text-xs">{shortcutHint}</span>
                </button>

                <div className="ml-auto flex items-center gap-2">
                    {settings && (
                        <div className="hidden md:flex">
                            <SemesterSelector {...settings} />
                        </div>
                    )}
                    <AnimatedThemeToggler className="text-primary hover:bg-accent hover:text-accent-foreground focus-visible:ring-ring inline-flex h-9 w-9 items-center justify-center gap-2 rounded-md px-0 text-sm font-medium whitespace-nowrap transition-all duration-200 focus-visible:ring-1 focus-visible:outline-none disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg]:size-4 [&_svg]:shrink-0" />

                    {/* Desktop Text Info */}
                    {user && (
                        <div className="text-muted-foreground hidden items-center gap-2 text-sm sm:flex">
                            <span className="text-foreground">{user.name}</span>
                            <span className="text-foreground text-xs">•</span>
                            <span className="text-foreground text-xs">{user.role}</span>
                        </div>
                    )}

                    {/* Mobile User Menu */}
                    {isMobile && user && (
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <button className="hover:bg-accent focus-visible:ring-primary flex items-center gap-1.5 rounded-full p-0.5 transition-all focus:outline-none focus-visible:ring-2 active:scale-95">
                                    <Avatar className="ring-primary/20 size-8 ring-2">
                                        <AvatarImage src={user.avatar ?? undefined} alt={user.name} />
                                        <AvatarFallback className="bg-primary/10 text-primary text-xs font-semibold">
                                            {user.name.slice(0, 2).toUpperCase()}
                                        </AvatarFallback>
                                    </Avatar>
                                    <ChevronDown className="text-muted-foreground size-3.5" />
                                </button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent className="w-64 rounded-xl p-2" align="end" sideOffset={8}>
                                {/* User info header */}
                                <div className="bg-accent/50 mb-2 flex items-center gap-3 rounded-lg p-3">
                                    <Avatar className="ring-primary/20 size-11 ring-2">
                                        <AvatarImage src={user.avatar ?? undefined} alt={user.name} />
                                        <AvatarFallback className="bg-primary/10 text-primary font-semibold">
                                            {user.name.slice(0, 2).toUpperCase()}
                                        </AvatarFallback>
                                    </Avatar>
                                    <div className="flex flex-col overflow-hidden">
                                        <span className="text-foreground truncate text-sm font-semibold">{user.name}</span>
                                        <span className="text-muted-foreground truncate text-xs">{user.email}</span>
                                        <span className="bg-primary/10 text-primary mt-0.5 inline-flex w-fit items-center rounded-full px-2 py-0.5 text-[10px] font-medium">
                                            {user.role}
                                        </span>
                                    </div>
                                </div>

                                <DropdownMenuSeparator />

                                <DropdownMenuLabel className="text-muted-foreground px-2 text-xs">Quick Actions</DropdownMenuLabel>

                                <DropdownMenuItem asChild className="cursor-pointer rounded-lg">
                                    <Link href={profileLink} className="flex items-center gap-3 px-2 py-2.5">
                                        <span className="bg-muted flex size-8 items-center justify-center rounded-lg">
                                            <Settings className="size-4" />
                                        </span>
                                        <span className="font-medium">Settings</span>
                                    </Link>
                                </DropdownMenuItem>

                                <DropdownMenuSeparator />

                                <DropdownMenuItem
                                    className="cursor-pointer rounded-lg text-red-600 focus:bg-red-50 focus:text-red-600 dark:focus:bg-red-950/50"
                                    onClick={() => setShowLogoutDialog(true)}
                                >
                                    <div className="flex items-center gap-3 px-2 py-2.5">
                                        <span className="flex size-8 items-center justify-center rounded-lg bg-red-100 dark:bg-red-950/50">
                                            <LogOut className="size-4" />
                                        </span>
                                        <span className="font-medium">Log out</span>
                                    </div>
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    )}
                </div>
            </div>

            <AlertDialog open={showLogoutDialog} onOpenChange={setShowLogoutDialog}>
                <AlertDialogContent className="sm:max-w-md">
                    <AlertDialogHeader>
                        <AlertDialogTitle>Log out of your account?</AlertDialogTitle>
                        <AlertDialogDescription>You will be redirected to the login page.</AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction className="bg-red-600 text-white hover:bg-red-700" onClick={() => router.post("/logout")}>
                            Log out
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </header>
    );
}
