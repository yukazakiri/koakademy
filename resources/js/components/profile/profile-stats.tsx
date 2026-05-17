import { Card, CardContent } from "@/components/ui/card";
import { Progress } from "@/components/ui/progress";
import { BookOpen, Clock, GraduationCap, ShieldCheck } from "lucide-react";

interface ProfileStatsProps {
    isFaculty: boolean;
    isStudent: boolean;
    coursesCount: number;
    officeHoursDisplay: string;
    profileCompletion: number;
    educationItemsCount: number;
}

export function ProfileStats({ isFaculty, isStudent, coursesCount, officeHoursDisplay, profileCompletion, educationItemsCount }: ProfileStatsProps) {
    const cardClass = "border-border/60 bg-card/75 rounded-lg shadow-sm";

    return (
        <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            {isFaculty && (
                <>
                    <Card className={cardClass}>
                        <CardContent className="flex items-center gap-3 p-4">
                            <div className="bg-primary/10 rounded-lg p-2.5">
                                <BookOpen className="text-primary h-5 w-5" />
                            </div>
                            <div className="min-w-0">
                                <p className="text-muted-foreground text-xs">Courses</p>
                                <p className="text-foreground truncate text-lg font-semibold">{coursesCount || "-"}</p>
                            </div>
                        </CardContent>
                    </Card>

                    <Card className={cardClass}>
                        <CardContent className="flex items-center gap-3 p-4">
                            <div className="bg-primary/10 rounded-lg p-2.5">
                                <Clock className="text-primary h-5 w-5" />
                            </div>
                            <div className="min-w-0">
                                <p className="text-muted-foreground text-xs">Office Hours</p>
                                <p className="text-foreground truncate text-sm font-semibold">{officeHoursDisplay}</p>
                            </div>
                        </CardContent>
                    </Card>
                </>
            )}

            <Card className={cardClass}>
                <CardContent className="flex items-center gap-3 p-4">
                    <div className="bg-primary/10 rounded-lg p-2.5">
                        <ShieldCheck className="text-primary h-5 w-5" />
                    </div>
                    <div className="min-w-0 flex-1">
                        <p className="text-muted-foreground text-xs">Profile</p>
                        <div className="mt-2 flex items-center gap-2">
                            <Progress
                                value={profileCompletion}
                                className={`h-2 flex-1 ${isStudent ? "[&>div]:from-primary [&>div]:to-accent [&>div]:bg-gradient-to-r" : ""}`}
                            />
                            <span className="text-foreground text-sm font-semibold">{profileCompletion}%</span>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card className={cardClass}>
                <CardContent className="flex items-center gap-3 p-4">
                    <div className="bg-primary/10 rounded-lg p-2.5">
                        <GraduationCap className="text-primary h-5 w-5" />
                    </div>
                    <div className="min-w-0">
                        <p className="text-muted-foreground text-xs">Education</p>
                        <p className="text-foreground truncate text-sm font-semibold">{educationItemsCount || "-"} Items</p>
                    </div>
                </CardContent>
            </Card>
        </div>
    );
}
