import { Button } from "@/components/ui/button";
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle, SheetTrigger } from "@/components/ui/sheet";
import { Link } from "@inertiajs/react";
import { IconCalendar, IconChecklist, IconFilePlus, IconLayoutGrid, IconMessagePlus, IconSettings, IconUsers } from "@tabler/icons-react";

interface QuickActionsSheetProps {
    classId: number;
    onOpenSettings: () => void;
}

export function QuickActionsSheet({ classId, onOpenSettings }: QuickActionsSheetProps) {
    return (
        <Sheet>
            <SheetTrigger asChild>
                <Button size="sm" className="rounded-full shadow-sm">
                    <IconLayoutGrid className="mr-2 size-4" />
                    Quick Actions
                </Button>
            </SheetTrigger>
            <SheetContent side="right" className="bg-background text-foreground flex w-full flex-col gap-0 p-0 sm:max-w-md">
                <SheetHeader className="border-b px-6 py-6">
                    <SheetTitle>Quick Actions</SheetTitle>
                    <SheetDescription>Common tasks and shortcuts for this class.</SheetDescription>
                </SheetHeader>
                <div className="flex-1 overflow-y-auto px-6 py-6">
                    <div className="grid grid-cols-2 gap-4">
                        <Button
                            variant="ghost"
                            className="bg-card text-card-foreground group h-32 flex-col gap-3 rounded-2xl border shadow-sm transition-all hover:border-emerald-500/20 hover:bg-emerald-500/5 hover:shadow-md"
                            asChild
                        >
                            <Link href={`/classes/${classId}?view=attendance`}>
                                <div className="rounded-full bg-emerald-500/10 p-3 transition-colors group-hover:bg-emerald-500/20">
                                    <IconChecklist className="size-8 text-emerald-600" />
                                </div>
                                <div className="text-center">
                                    <span className="block font-semibold">Attendance</span>
                                    <span className="text-muted-foreground text-[10px] font-normal">Check log</span>
                                </div>
                            </Link>
                        </Button>
                        <Button
                            variant="ghost"
                            className="bg-card text-card-foreground group h-32 flex-col gap-3 rounded-2xl border shadow-sm transition-all hover:border-blue-500/20 hover:bg-blue-500/5 hover:shadow-md"
                            asChild
                        >
                            <Link href={`/classes/${classId}?view=classwork`}>
                                <div className="rounded-full bg-blue-500/10 p-3 transition-colors group-hover:bg-blue-500/20">
                                    <IconFilePlus className="size-8 text-blue-600" />
                                </div>
                                <div className="text-center">
                                    <span className="block font-semibold">Classwork</span>
                                    <span className="text-muted-foreground text-[10px] font-normal">Create task</span>
                                </div>
                            </Link>
                        </Button>
                        <Button
                            variant="ghost"
                            className="bg-card text-card-foreground group h-32 flex-col gap-3 rounded-2xl border shadow-sm transition-all hover:border-violet-500/20 hover:bg-violet-500/5 hover:shadow-md"
                            asChild
                        >
                            <Link href={`/classes/${classId}?view=stream`}>
                                <div className="rounded-full bg-violet-500/10 p-3 transition-colors group-hover:bg-violet-500/20">
                                    <IconMessagePlus className="size-8 text-violet-600" />
                                </div>
                                <div className="text-center">
                                    <span className="block font-semibold">Announce</span>
                                    <span className="text-muted-foreground text-[10px] font-normal">Post update</span>
                                </div>
                            </Link>
                        </Button>
                        <Button
                            variant="ghost"
                            className="bg-card text-card-foreground group h-32 flex-col gap-3 rounded-2xl border shadow-sm transition-all hover:border-orange-500/20 hover:bg-orange-500/5 hover:shadow-md"
                            asChild
                        >
                            <Link href={`/classes/${classId}?view=people`}>
                                <div className="rounded-full bg-orange-500/10 p-3 transition-colors group-hover:bg-orange-500/20">
                                    <IconUsers className="size-8 text-orange-600" />
                                </div>
                                <div className="text-center">
                                    <span className="block font-semibold">People</span>
                                    <span className="text-muted-foreground text-[10px] font-normal">Manage list</span>
                                </div>
                            </Link>
                        </Button>
                    </div>

                    <div className="space-y-2">
                        <h4 className="text-muted-foreground text-sm font-medium tracking-wider uppercase">Other Actions</h4>
                        <Button variant="ghost" className="w-full justify-start" onClick={onOpenSettings}>
                            <IconSettings className="mr-2 size-4" />
                            Class Settings
                        </Button>
                        <Button variant="ghost" className="w-full justify-start" asChild>
                            <Link href="/schedule">
                                <IconCalendar className="mr-2 size-4" />
                                View Full Schedule
                            </Link>
                        </Button>
                    </div>
                </div>
            </SheetContent>
        </Sheet>
    );
}
