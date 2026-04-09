import { Button } from "@/components/ui/button";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Link } from "@inertiajs/react";
import { IconCalendarEvent, IconDotsVertical, IconDownload, IconPlus, IconRefresh, IconSettings } from "@tabler/icons-react";

interface ScheduleActionsProps {
    handleExport: () => void;
    handleClearBaseline: () => void;
    baseline: boolean;
}

export function ScheduleActions({ handleExport, handleClearBaseline, baseline }: ScheduleActionsProps) {
    return (
        <>
            {/* Desktop Actions */}
            <div className="hidden items-center gap-2 md:flex">
                <Button variant="outline" onClick={handleExport} className="h-9 gap-2">
                    <IconDownload className="size-4" />
                    <span>Export PDF</span>
                </Button>

                <Button asChild className="h-9 gap-2">
                    <Link href="/classes?create=1">
                        <IconPlus className="size-4" />
                        <span>Create Class</span>
                    </Link>
                </Button>

                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button variant="ghost" size="icon" className="h-9 w-9">
                            <IconDotsVertical className="size-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" className="w-56">
                        <DropdownMenuLabel>Manage Schedule</DropdownMenuLabel>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem disabled>
                            <IconRefresh className="mr-2 size-4" />
                            <span>Sync to Calendar</span>
                            <span className="text-muted-foreground ml-auto text-xs">Soon</span>
                        </DropdownMenuItem>
                        <DropdownMenuItem asChild>
                            <Link href="/settings">
                                <IconSettings className="mr-2 size-4" />
                                <span>Settings</span>
                            </Link>
                        </DropdownMenuItem>
                        {baseline && (
                            <>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem
                                    onClick={handleClearBaseline}
                                    className="text-destructive focus:bg-destructive/10 focus:text-destructive"
                                >
                                    <span>Clear Baseline Snapshot</span>
                                </DropdownMenuItem>
                            </>
                        )}
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>

            {/* Mobile Actions (Icon Only + Menu) */}
            <div className="flex items-center gap-1 md:hidden">
                <Button asChild size="icon" variant="ghost" className="h-9 w-9">
                    <Link href="/classes?create=1">
                        <IconPlus className="size-5" />
                    </Link>
                </Button>

                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button variant="ghost" size="icon" className="h-9 w-9">
                            <IconDotsVertical className="size-5" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" className="w-56 font-medium">
                        <DropdownMenuLabel>Actions</DropdownMenuLabel>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem asChild>
                            <Link href="/classes?create=1">
                                <IconPlus className="mr-2 size-4" />
                                Create Class
                            </Link>
                        </DropdownMenuItem>
                        <DropdownMenuItem onClick={handleExport}>
                            <IconDownload className="mr-2 size-4" />
                            Export PDF
                        </DropdownMenuItem>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem disabled>
                            <IconCalendarEvent className="mr-2 size-4" />
                            Sync to Calendar (Soon)
                        </DropdownMenuItem>
                        {baseline && (
                            <DropdownMenuItem onClick={handleClearBaseline} className="text-destructive">
                                Clear Baseline
                            </DropdownMenuItem>
                        )}
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </>
    );
}
