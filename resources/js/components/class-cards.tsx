import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from "@/components/ui/card";
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from "@/components/ui/dropdown-menu";
import { cn } from "@/lib/utils";
import { User } from "@/types/user";
import { Link, usePage } from "@inertiajs/react";
import { IconCalendar, IconDotsVertical, IconMapPin, IconSchool, IconUsers } from "@tabler/icons-react";
import { ClassData } from "./data-table";

interface ClassCardsProps {
    data: ClassData[];
    onEdit: (classItem: ClassData) => void;
}

type ThemeStyles = {
    background: string;
    overlay: string;
    accentBg: string;
    accentText: string;
    hoverShadow: string;
};

const THEME_STYLES: Record<string, ThemeStyles> = {
    violet: {
        background: "bg-gradient-to-br from-violet-950/70 via-background to-background",
        overlay: "bg-gradient-to-t from-background/90 via-background/40 to-transparent",
        accentBg: "bg-violet-500/20",
        accentText: "text-violet-500",
        hoverShadow: "hover:shadow-violet-500/20 hover:border-violet-500/30",
    },
    indigo: {
        background: "bg-gradient-to-br from-indigo-950/70 via-background to-background",
        overlay: "bg-gradient-to-t from-background/90 via-background/40 to-transparent",
        accentBg: "bg-indigo-500/20",
        accentText: "text-indigo-500",
        hoverShadow: "hover:shadow-indigo-500/20 hover:border-indigo-500/30",
    },
    blue: {
        background: "bg-gradient-to-br from-blue-950/70 via-background to-background",
        overlay: "bg-gradient-to-t from-background/90 via-background/40 to-transparent",
        accentBg: "bg-blue-500/20",
        accentText: "text-blue-500",
        hoverShadow: "hover:shadow-blue-500/20 hover:border-blue-500/30",
    },
    cyan: {
        background: "bg-gradient-to-br from-cyan-950/70 via-background to-background",
        overlay: "bg-gradient-to-t from-background/90 via-background/40 to-transparent",
        accentBg: "bg-cyan-500/20",
        accentText: "text-cyan-500",
        hoverShadow: "hover:shadow-cyan-500/20 hover:border-cyan-500/30",
    },
    emerald: {
        background: "bg-gradient-to-br from-emerald-950/70 via-background to-background",
        overlay: "bg-gradient-to-t from-background/90 via-background/40 to-transparent",
        accentBg: "bg-emerald-500/20",
        accentText: "text-emerald-500",
        hoverShadow: "hover:shadow-emerald-500/20 hover:border-emerald-500/30",
    },
    lime: {
        background: "bg-gradient-to-br from-lime-950/70 via-background to-background",
        overlay: "bg-gradient-to-t from-background/90 via-background/40 to-transparent",
        accentBg: "bg-lime-500/20",
        accentText: "text-lime-500",
        hoverShadow: "hover:shadow-lime-500/20 hover:border-lime-500/30",
    },
    amber: {
        background: "bg-gradient-to-br from-amber-950/70 via-background to-background",
        overlay: "bg-gradient-to-t from-background/90 via-background/40 to-transparent",
        accentBg: "bg-amber-500/20",
        accentText: "text-amber-500",
        hoverShadow: "hover:shadow-amber-500/20 hover:border-amber-500/30",
    },
    orange: {
        background: "bg-gradient-to-br from-orange-950/70 via-background to-background",
        overlay: "bg-gradient-to-t from-background/90 via-background/40 to-transparent",
        accentBg: "bg-orange-500/20",
        accentText: "text-orange-500",
        hoverShadow: "hover:shadow-orange-500/20 hover:border-orange-500/30",
    },
    rose: {
        background: "bg-gradient-to-br from-rose-950/70 via-background to-background",
        overlay: "bg-gradient-to-t from-background/90 via-background/40 to-transparent",
        accentBg: "bg-rose-500/20",
        accentText: "text-rose-500",
        hoverShadow: "hover:shadow-rose-500/20 hover:border-rose-500/30",
    },
    fuchsia: {
        background: "bg-gradient-to-br from-fuchsia-950/70 via-background to-background",
        overlay: "bg-gradient-to-t from-background/90 via-background/40 to-transparent",
        accentBg: "bg-fuchsia-500/20",
        accentText: "text-fuchsia-500",
        hoverShadow: "hover:shadow-fuchsia-500/20 hover:border-fuchsia-500/30",
    },
    background: {
        background: "bg-gradient-to-br from-muted/40 via-background to-background",
        overlay: "bg-gradient-to-t from-background/90 via-background/40 to-transparent",
        accentBg: "bg-primary/10",
        accentText: "text-primary",
        hoverShadow: "hover:shadow-primary/10 hover:border-primary/20",
    },
};

function extractColorToken(bgClass: string | null | undefined): string | null {
    if (!bgClass) {
        return null;
    }

    const match = bgClass.match(/\bbg-([a-z]+)-\d+\b/);
    if (!match) {
        return null;
    }

    return match[1] ?? null;
}

function resolveClassTheme(classItem: ClassData): {
    theme: ThemeStyles;
    accentClass: string | null;
    backgroundClass: string | null;
    bannerImage: string | null;
    classificationLabel: string | null;
    classificationBadge: string;
} {
    const settings = (classItem.settings ?? {}) as Record<string, unknown>;

    const accentClass = typeof settings.accent_color === "string" ? settings.accent_color : null;
    const backgroundClass = typeof settings.background_color === "string" ? settings.background_color : null;
    const bannerImage = typeof settings.banner_image === "string" ? settings.banner_image : null;

    const backgroundToken = extractColorToken(backgroundClass) ?? "background";
    const accentToken = extractColorToken(accentClass) ?? "blue";

    const theme = {
        ...(THEME_STYLES[backgroundToken] ?? THEME_STYLES.background),
        accentBg: (THEME_STYLES[accentToken] ?? THEME_STYLES.blue).accentBg,
        accentText: (THEME_STYLES[accentToken] ?? THEME_STYLES.blue).accentText,
    };

    const classification = (classItem.classification ?? "").toLowerCase();
    const classificationLabel = classification ? (classification === "shs" ? "Senior High" : "College") : null;
    const classificationBadge =
        classification === "shs" ? "bg-amber-500/90 text-amber-50 border-amber-600/20" : "bg-emerald-500/90 text-emerald-50 border-emerald-600/20";

    return { theme, accentClass, backgroundClass, bannerImage, classificationLabel, classificationBadge };
}

export function ClassCards({ data, onEdit }: ClassCardsProps) {
    const { props } = usePage<{ user: User }>();
    const user = props.user;

    const isFaculty = ["professor", "instructor", "associate_professor", "assistant_professor", "part_time_faculty"].includes(user.role);
    const isStudent = ["student", "shs_student", "graduate_student"].includes(user.role);
    const isAdmin = ["admin", "super_admin", "developer", "president"].includes(user.role);

    const getBaseUrl = () => {
        if (isFaculty) return "/faculty/classes";
        if (isStudent) return "/student/classes";
        if (isAdmin) return "/administrators/classes";
        return "/classes";
    };

    const baseUrl = getBaseUrl();

    if (!data?.length) {
        return (
            <div className="animate-in fade-in zoom-in-95 flex flex-col items-center justify-center py-20 text-center duration-500">
                <div className="bg-muted/50 ring-border/50 rounded-full p-8 ring-1">
                    <IconSchool className="text-muted-foreground h-12 w-12" />
                </div>
                <h3 className="mt-6 text-xl font-bold">No Classes Found</h3>
                <p className="text-muted-foreground mt-2 max-w-sm text-sm">You don't have any classes assigned for this semester.</p>
            </div>
        );
    }

    return (
        <div className="grid grid-cols-1 gap-5 md:grid-cols-2 lg:grid-cols-3">
            {data.map((classItem, index) => {
                const { theme, accentClass, bannerImage, classificationLabel, classificationBadge } = resolveClassTheme(classItem);

                const accentDotClass = accentClass?.includes("bg-") ? accentClass : "bg-muted";
                const scheduleLabel =
                    classItem.schedule ||
                    (classItem.semester && classItem.school_year ? `${classItem.semester} • ${classItem.school_year}` : "Schedule TBA");

                return (
                    <Card
                        key={classItem.id}
                        className={cn(
                            "group border-border/60 bg-muted text-card-foreground hover:shadow-3xl relative overflow-hidden border shadow-lg transition-all duration-300 hover:-translate-y-1",
                            theme.hoverShadow,
                            "animate-in fade-in slide-in-from-bottom-4 fill-mode-both",
                        )}
                        style={{ animationDelay: `${index * 50}ms` }}
                    >
                        <div className="absolute inset-0 z-0">
                            {bannerImage ? (
                                <div
                                    className="h-full w-full bg-cover bg-center transition-transform duration-700 group-hover:scale-105"
                                    style={{ backgroundImage: `url(${bannerImage})` }}
                                />
                            ) : (
                                <div className={cn("h-full w-full opacity-60", theme.background)} />
                            )}
                            <div className={cn("absolute inset-0 transition-opacity duration-300", theme.overlay)} />
                        </div>

                        <CardHeader className="relative z-10 px-5 pt-5 pb-3">
                            <div className="flex items-start justify-between gap-3">
                                <div className="flex min-w-0 items-start gap-4">
                                    <div
                                        className={cn(
                                            "bg-muted/20 flex size-12 items-center justify-center rounded-xl shadow-sm ring-1 ring-white/20 backdrop-blur-md transition-transform group-hover:scale-110",
                                            theme.accentBg,
                                        )}
                                    >
                                        <IconSchool className={cn("h-6 w-6", theme.accentText)} />
                                    </div>

                                    <div className="min-w-0 space-y-1.5">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <Badge
                                                variant="secondary"
                                                className={cn(
                                                    "rounded-md border px-2 py-0.5 text-xs font-semibold whitespace-nowrap shadow-sm",
                                                    classificationBadge,
                                                )}
                                            >
                                                {classificationLabel || classItem.subject_code}
                                            </Badge>
                                            {classificationLabel && (
                                                <Badge
                                                    variant="outline"
                                                    className="bg-background/40 text-foreground/90 rounded-md border-white/20 backdrop-blur"
                                                >
                                                    {classItem.subject_code}
                                                </Badge>
                                            )}
                                            <Badge
                                                variant="outline"
                                                className="bg-background/40 text-foreground/90 rounded-md border-white/20 backdrop-blur"
                                            >
                                                Section {classItem.section}
                                            </Badge>
                                        </div>

                                        <CardTitle className="line-clamp-2 text-lg leading-tight font-bold tracking-tight">
                                            {classItem.subject_title}
                                        </CardTitle>
                                    </div>
                                </div>

                                <DropdownMenu>
                                    <DropdownMenuTrigger asChild>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            className="bg-background/10 text-foreground/80 hover:bg-background/30 hover:text-foreground -mt-2 -mr-2 h-8 w-8 rounded-full backdrop-blur-sm"
                                        >
                                            <IconDotsVertical className="h-4 w-4" />
                                        </Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="end" className="w-48">
                                        <DropdownMenuItem asChild>
                                            <Link href={`${baseUrl}/${classItem.id}`} className="cursor-pointer">
                                                <IconSchool className="mr-2 h-4 w-4" /> Open class
                                            </Link>
                                        </DropdownMenuItem>
                                        <DropdownMenuItem asChild>
                                            <Link href={`${baseUrl}/${classItem.id}?view=people`} prefetch className="cursor-pointer">
                                                <IconUsers className="mr-2 h-4 w-4" /> People
                                            </Link>
                                        </DropdownMenuItem>
                                        <DropdownMenuItem asChild>
                                            <Link href={`${baseUrl}/${classItem.id}?view=attendance`} prefetch className="cursor-pointer">
                                                <IconCalendar className="mr-2 h-4 w-4" /> Attendance
                                            </Link>
                                        </DropdownMenuItem>
                                        <DropdownMenuItem onClick={() => onEdit(classItem)} className="cursor-pointer">
                                            Edit details
                                        </DropdownMenuItem>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </div>
                        </CardHeader>

                        <CardContent className="relative z-10 space-y-4 px-5 pb-4">
                            {/* Location & Time */}
                            <div className="bg-muted/40 border-muted/10 flex flex-col gap-2 rounded-lg border p-3 backdrop-blur-sm">
                                <div className="group-hover:text-foreground flex items-center gap-2.5 text-sm transition-colors">
                                    <div className={cn("size-2 rounded-full ring-2 ring-white/20", accentDotClass)} />
                                    <span className="font-semibold opacity-90">{scheduleLabel}</span>
                                </div>
                                <div className="text-muted-foreground group-hover:text-foreground/80 flex items-center gap-2.5 text-sm transition-colors">
                                    <IconMapPin className="h-4 w-4 opacity-70" />
                                    <span>{classItem.room || "Room TBA"}</span>
                                </div>
                            </div>

                            <div className="flex items-center justify-between">
                                <div className="flex items-center gap-2 text-sm font-medium">
                                    <IconUsers className="text-muted-foreground h-4 w-4" />
                                    <span>{classItem.students_count ?? 0} students</span>
                                </div>
                            </div>
                        </CardContent>

                        <CardFooter className="border-border/40 bg-background/40 relative z-10 flex items-center gap-2 border-t px-5 py-3 backdrop-blur-md">
                            <Button asChild size="sm" className="bg-primary/90 hover:bg-primary rounded-lg font-semibold shadow-sm">
                                <Link href={`${baseUrl}/${classItem.id}`}>Open Class</Link>
                            </Button>
                            <Button
                                asChild
                                variant="outline"
                                size="sm"
                                className="hover:text-foreground rounded-lg border-white/20 bg-white/10 shadow-sm hover:bg-white/20"
                            >
                                <Link href={`${baseUrl}/${classItem.id}?view=attendance`} prefetch>
                                    Attendance
                                </Link>
                            </Button>
                        </CardFooter>
                    </Card>
                );
            })}
        </div>
    );
}
