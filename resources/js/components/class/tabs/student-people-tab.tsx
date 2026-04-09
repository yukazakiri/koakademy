import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { IconMail } from "@tabler/icons-react";

interface StudentPeopleTabProps {
    teacher: {
        id: string | null;
        name: string;
        email?: string | null;
        department?: string | null;
        photo_url?: string | null;
    };
    classmates: Array<{
        id: number;
        name: string;
        avatar: string;
    }>;
}

export function StudentPeopleTab({ teacher, classmates }: StudentPeopleTabProps) {
    return (
        <div className="mx-auto max-w-4xl space-y-6">
            <Card className="border-border/70 bg-card/90 shadow-sm">
                <CardHeader className="border-border/40 border-b pb-3">
                    <CardTitle className="text-primary text-xl">Instructor</CardTitle>
                </CardHeader>
                <CardContent className="pt-6">
                    <div className="flex items-center justify-between">
                        <div className="flex items-center gap-4">
                            <Avatar className="border-background h-12 w-12 border-2 shadow-sm">
                                <AvatarImage src={teacher.photo_url || undefined} alt={teacher.name} />
                                <AvatarFallback className="bg-primary/10 text-primary">{teacher.name.charAt(0)}</AvatarFallback>
                            </Avatar>
                            <div>
                                <p className="text-foreground font-semibold">{teacher.name}</p>
                                {teacher.email && (
                                    <div className="text-muted-foreground flex items-center gap-1.5 text-sm">
                                        <IconMail className="size-3.5" />
                                        <span>{teacher.email}</span>
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card className="border-border/70 bg-card/90 shadow-sm">
                <CardHeader className="border-border/40 flex flex-row items-center justify-between border-b pb-3">
                    <CardTitle className="text-primary text-xl">Classmates</CardTitle>
                    <span className="text-muted-foreground text-sm font-normal">{classmates.length} students</span>
                </CardHeader>
                <CardContent className="pt-2">
                    {classmates.length === 0 ? (
                        <div className="text-muted-foreground py-8 text-center">No other students in this class yet.</div>
                    ) : (
                        <div className="divide-border/40 divide-y">
                            {classmates.map((student) => (
                                <div key={student.id} className="hover:bg-muted/30 flex items-center gap-4 rounded-lg px-2 py-4 transition-colors">
                                    <Avatar className="border-border/50 h-9 w-9 border">
                                        <AvatarImage src={student.avatar} alt={student.name} />
                                        <AvatarFallback className="bg-muted text-muted-foreground text-xs">{student.name.charAt(0)}</AvatarFallback>
                                    </Avatar>
                                    <p className="text-foreground text-sm font-medium">{student.name}</p>
                                </div>
                            ))}
                        </div>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}
