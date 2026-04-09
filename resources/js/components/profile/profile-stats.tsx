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
    return (
        <div className="grid gap-4 md:grid-cols-4">
            {isFaculty && (
                <>
                    <Card className="border-primary/30 from-primary/15 to-muted/20 bg-gradient-to-br">
                        <CardContent className="flex items-center gap-4 p-6">
                            <div className="bg-primary/15 rounded-full p-3">
                                <BookOpen className="text-primary h-6 w-6" />
                            </div>
                            <div>
                                <p className="text-muted-foreground text-sm">Courses</p>
                                <p className="text-2xl font-bold">{coursesCount || "—"}</p>
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="border-primary/30 from-primary/15 to-muted/20 bg-gradient-to-br">
                        <CardContent className="flex items-center gap-4 p-6">
                            <div className="bg-primary/15 rounded-full p-3">
                                <Clock className="text-primary h-6 w-6" />
                            </div>
                            <div>
                                <p className="text-muted-foreground text-sm">Office Hours</p>
                                <p className="text-sm font-semibold">{officeHoursDisplay}</p>
                            </div>
                        </CardContent>
                    </Card>
                </>
            )}

            <Card className="border-primary/30 from-primary/15 to-muted/20 bg-gradient-to-br">
                <CardContent className="flex items-center gap-4 p-6">
                    <div className="bg-primary/15 rounded-full p-3">
                        <ShieldCheck className="text-primary h-6 w-6" />
                    </div>
                    <div className="flex-1">
                        <p className="text-muted-foreground text-sm">Profile</p>
                        <div className="mt-2 flex items-center gap-2">
                            <Progress
                                value={profileCompletion}
                                className={`h-2 flex-1 ${isStudent ? "[&>div]:from-primary [&>div]:to-accent h-3 [&>div]:bg-gradient-to-r" : ""}`}
                            />
                            <span className="text-sm font-bold">{profileCompletion}%</span>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card className="border-primary/30 from-primary/15 to-muted/20 bg-gradient-to-br">
                <CardContent className="flex items-center gap-4 p-6">
                    <div className="bg-primary/15 rounded-full p-3">
                        <GraduationCap className="text-primary h-6 w-6" />
                    </div>
                    <div>
                        <p className="text-muted-foreground text-sm">Education</p>
                        <p className="text-sm font-semibold">{educationItemsCount || "—"} Items</p>
                    </div>
                </CardContent>
            </Card>
        </div>
    );
}
