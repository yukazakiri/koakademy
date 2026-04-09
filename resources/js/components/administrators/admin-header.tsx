import { triggerGlobalCommandPalette } from "@/components/global-command-palette";
import { SemesterSelector, type SemesterSelectorProps } from "@/components/semester-selector";
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
import { Calendar } from "@/components/ui/calendar";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { Separator } from "@/components/ui/separator";
import { SidebarTrigger } from "@/components/ui/sidebar";
import { useIsMobile } from "@/hooks/use-mobile";
import { User } from "@/types/user";
import { Link, router, usePage } from "@inertiajs/react";
import { IconSearch } from "@tabler/icons-react";
import { ChevronDown, Clock, LogOut, Settings } from "lucide-react";
import { useEffect, useMemo, useState } from "react";

interface AdminHeaderProps {
    title: string;
    user: User;
}

export function AdminHeader({ title, user }: AdminHeaderProps) {
    const { settings } = usePage<{ settings: SemesterSelectorProps }>().props;
    const [shortcutHint, setShortcutHint] = useState("Ctrl K");
    const [showLogoutDialog, setShowLogoutDialog] = useState(false);
    const [currentTime, setCurrentTime] = useState(() => new Date());
    const [selectedDate, setSelectedDate] = useState<Date | undefined>(new Date());
    const isMobile = useIsMobile();
    const timeFormatter = useMemo(
        () =>
            new Intl.DateTimeFormat(undefined, {
                hour: "numeric",
                minute: "2-digit",
                second: "2-digit",
            }),
        [],
    );
    const dateFormatter = useMemo(
        () =>
            new Intl.DateTimeFormat(undefined, {
                weekday: "short",
                month: "short",
                day: "numeric",
            }),
        [],
    );
    const longDateFormatter = useMemo(
        () =>
            new Intl.DateTimeFormat(undefined, {
                weekday: "long",
                month: "long",
                day: "numeric",
                year: "numeric",
            }),
        [],
    );

    useEffect(() => {
        if (typeof window === "undefined") {
            return;
        }

        const isMac = window.navigator.userAgent.toLowerCase().includes("mac");
        setShortcutHint(isMac ? "⌘ K" : "Ctrl K");
    }, []);

    useEffect(() => {
        const timer = window.setInterval(() => {
            setCurrentTime(new Date());
        }, 1000);

        return () => {
            window.clearInterval(timer);
        };
    }, []);

    // Determine profile link based on user role
    const isAdmin = [
        "admin",
        "super_admin",
        "developer",
        "president",
        "vice_president",
        "dean",
        "associate_dean",
        "department_head",
        "program_chair",
        "registrar",
        "assistant_registrar",
        "cashier",
        "hr_manager",
        "student_affairs_officer",
        "guidance_counselor",
        "librarian",
    ].includes(user.role);
    const profileLink = isAdmin ? "/administrators/settings" : "/profile";
    const hours = currentTime.getHours();
    const minutes = currentTime.getMinutes();
    const seconds = currentTime.getSeconds();
    const hourAngle = (hours % 12) * 30 + minutes / 2;
    const minuteAngle = minutes * 6 + seconds / 10;
    const secondAngle = seconds * 6;

    return (
        <header className="mt-4 flex h-(--header-height) shrink-0 items-center gap-2 border-b pb-3 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-(--header-height)">
            <div className="flex w-full items-center gap-1 px-4 lg:gap-2 lg:px-6">
                <SidebarTrigger className="-ml-1" />
                <Separator orientation="vertical" className="mx-2 data-[orientation=vertical]:h-4" />

                <div className="flex flex-col">
                    <h1 className="text-foreground text-base font-medium">{title}</h1>
                    <p className="text-muted-foreground hidden text-xs sm:block">Beginner-friendly admin workspace</p>
                </div>

                <button
                    type="button"
                    onClick={() => triggerGlobalCommandPalette()}
                    className="bg-background text-muted-foreground hover:bg-accent hover:text-accent-foreground ml-4 hidden h-9 w-full max-w-sm items-center gap-2 rounded-md border px-3 text-left text-sm md:flex"
                >
                    <IconSearch className="h-4 w-4" />
                    <span className="flex-1 truncate">Search students and classes…</span>
                    <span className="text-muted-foreground text-xs">{shortcutHint}</span>
                </button>

                <div className="ml-auto flex items-center gap-2">
                    {settings ? (
                        <div className="hidden md:flex">
                            <SemesterSelector {...settings} />
                        </div>
                    ) : null}

                    {/* Desktop time indicator (hidden on mobile) */}
                    <Popover>
                        <PopoverTrigger asChild>
                            <button
                                type="button"
                                className="border-border/60 bg-background/80 hover:bg-accent hover:text-accent-foreground hidden items-center gap-3 rounded-full border px-3 py-1.5 text-left text-sm transition sm:flex"
                            >
                                <span className="bg-primary/10 text-primary flex size-8 items-center justify-center rounded-full">
                                    <Clock className="size-4" />
                                </span>
                                <span className="flex flex-col leading-none">
                                    <span className="text-foreground text-sm font-semibold tabular-nums">{timeFormatter.format(currentTime)}</span>
                                    <span className="text-muted-foreground text-xs">{dateFormatter.format(currentTime)}</span>
                                </span>
                            </button>
                        </PopoverTrigger>
                        <PopoverContent className="w-[340px] p-0" align="end" sideOffset={12}>
                            <div className="border-border/60 flex items-center gap-4 border-b p-4">
                                <div className="border-border/70 bg-muted/30 relative flex size-20 items-center justify-center rounded-full border shadow-inner">
                                    <span className="bg-foreground absolute size-1.5 rounded-full" />
                                    <span
                                        className="bg-foreground absolute top-1/2 left-1/2 h-5 w-0.5 origin-bottom rounded-full"
                                        style={{ transform: `translate(-50%, -100%) rotate(${hourAngle}deg)` }}
                                    />
                                    <span
                                        className="bg-foreground absolute top-1/2 left-1/2 h-7 w-0.5 origin-bottom rounded-full"
                                        style={{ transform: `translate(-50%, -100%) rotate(${minuteAngle}deg)` }}
                                    />
                                    <span
                                        className="bg-primary absolute top-1/2 left-1/2 h-8 w-px origin-bottom rounded-full"
                                        style={{ transform: `translate(-50%, -100%) rotate(${secondAngle}deg)` }}
                                    />
                                </div>
                                <div className="flex flex-col">
                                    <span className="text-muted-foreground text-[11px] font-semibold tracking-wide uppercase">Local time</span>
                                    <span className="text-foreground text-2xl font-semibold tabular-nums">{timeFormatter.format(currentTime)}</span>
                                    <span className="text-muted-foreground text-xs">{longDateFormatter.format(currentTime)}</span>
                                </div>
                            </div>
                            <div className="p-2">
                                <Calendar mode="single" selected={selectedDate} onSelect={setSelectedDate} className="mx-auto" />
                            </div>
                        </PopoverContent>
                    </Popover>

                    <AnimatedThemeToggler />

                    {/* Mobile User Menu */}
                    {isMobile && (
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

            {/* Logout Confirmation Dialog */}
            <AlertDialog open={showLogoutDialog} onOpenChange={setShowLogoutDialog}>
                <AlertDialogContent className="sm:max-w-md">
                    <AlertDialogHeader>
                        <AlertDialogTitle>Log out of your account?</AlertDialogTitle>
                        <AlertDialogDescription>You will be redirected to the login page. Any unsaved changes may be lost.</AlertDialogDescription>
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
