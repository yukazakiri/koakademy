import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Separator } from "@/components/ui/separator";
import { CheckCircle2 } from "lucide-react";

interface ProfileSidebarProps {
    user: {
        name: string;
    };
    avatarPreview?: string;
    position?: string;
    biographyPreview?: string;
    isFaculty: boolean;
}

export function ProfileSidebar({ user, avatarPreview, position, biographyPreview, isFaculty }: ProfileSidebarProps) {
    return (
        <div className="space-y-6">
            <Card className="border-accent bg-accent/20 dark:border-accent/50 dark:bg-accent/10">
                <CardHeader>
                    <CardTitle className="text-base">Profile Tips</CardTitle>
                </CardHeader>
                <CardContent className="space-y-3 text-sm">
                    {isFaculty ? (
                        <>
                            <div className="flex items-start gap-2">
                                <CheckCircle2 className="text-accent-foreground mt-0.5 h-4 w-4" />
                                <p className="text-accent-foreground">Keep your office hours current for student meetings</p>
                            </div>
                            <div className="flex items-start gap-2">
                                <CheckCircle2 className="text-accent-foreground mt-0.5 h-4 w-4" />
                                <p className="text-accent-foreground">List active courses to help students find you</p>
                            </div>
                            <div className="flex items-start gap-2">
                                <CheckCircle2 className="text-accent-foreground mt-0.5 h-4 w-4" />
                                <p className="text-accent-foreground">Add your education and research interests</p>
                            </div>
                        </>
                    ) : (
                        <>
                            <div className="flex items-start gap-2">
                                <CheckCircle2 className="text-accent-foreground mt-0.5 h-4 w-4" />
                                <p className="text-accent-foreground">Complete your profile to help professors know you better</p>
                            </div>
                            <div className="flex items-start gap-2">
                                <CheckCircle2 className="text-accent-foreground mt-0.5 h-4 w-4" />
                                <p className="text-accent-foreground">Add emergency contacts for safety</p>
                            </div>
                            <div className="flex items-start gap-2">
                                <CheckCircle2 className="text-accent-foreground mt-0.5 h-4 w-4" />
                                <p className="text-accent-foreground">Keep your education history updated</p>
                            </div>
                        </>
                    )}
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle className="text-lg">Public View</CardTitle>
                    <CardDescription>How {isFaculty ? "students" : "others"} see you</CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                    <div className="flex items-start gap-3">
                        <Avatar className="h-12 w-12">
                            <AvatarImage src={avatarPreview} alt={user.name} />
                            <AvatarFallback>{(user.name || "").slice(0, 2).toUpperCase()}</AvatarFallback>
                        </Avatar>
                        <div className="flex-1 space-y-1">
                            <p className="font-semibold">{user.name}</p>
                            <p className="text-muted-foreground text-sm">{position}</p>
                        </div>
                    </div>
                    {biographyPreview && (
                        <>
                            <Separator />
                            <p className="text-muted-foreground line-clamp-3 text-sm">{biographyPreview}</p>
                        </>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}
