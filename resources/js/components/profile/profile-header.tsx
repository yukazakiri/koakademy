import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { Camera, CheckCircle2, GraduationCap, MapPin, Save, UploadCloud, User } from "lucide-react";

interface ProfileHeaderProps {
    user: {
        name: string;
        role: string;
        avatar_url?: string;
    };
    student?: {
        course?: {
            code: string;
            title: string;
        };
        student_id?: number;
        formatted_academic_year?: string;
    };
    isStudent: boolean;
    isFaculty: boolean;
    profileCompletion: number;
    avatarPreview?: string;
    avatarInputRef: React.RefObject<HTMLInputElement | null>;
    hasChanges: boolean;
    department?: string;
    position?: string;
    campusLocation: string;
    facultyName: string;
    onAvatarSelect: (event: React.ChangeEvent<HTMLInputElement>) => void;
    onTriggerAvatarPicker: () => void;
    onSaveClick: () => void;
}

export function ProfileHeader({
    user,
    student,
    isStudent,
    isFaculty,
    profileCompletion,
    avatarPreview,
    avatarInputRef,
    hasChanges,
    department,
    position,
    campusLocation,
    facultyName,
    onAvatarSelect,
    onTriggerAvatarPicker,
    onSaveClick,
}: ProfileHeaderProps) {
    return (
        <Card
            className={`relative overflow-hidden border shadow-lg ${
                isStudent
                    ? "from-primary/30 via-secondary/30 to-accent/30 border-primary/20 dark:border-primary/30 bg-gradient-to-r"
                    : "from-primary/20 via-secondary/15 to-accent/20 bg-gradient-to-r"
            }`}
        >
            <CardContent className="relative p-8">
                <div className="flex flex-col gap-6 md:flex-row md:items-center md:justify-between">
                    <div className="flex flex-col gap-6 md:flex-row md:items-center">
                        <div className="relative">
                            <Avatar className="border-background h-28 w-28 border-4 shadow-xl">
                                <AvatarImage src={avatarPreview} alt={user.name} />
                                <AvatarFallback className="bg-primary/10 text-primary text-3xl font-bold">
                                    {(user.name || "").slice(0, 2).toUpperCase()}
                                </AvatarFallback>
                            </Avatar>
                            <Button
                                type="button"
                                size="icon"
                                variant="secondary"
                                className="absolute -right-2 -bottom-2 h-10 w-10 rounded-full shadow-lg"
                                onClick={onTriggerAvatarPicker}
                            >
                                <Camera className="h-5 w-5" />
                            </Button>
                            <input ref={avatarInputRef} type="file" accept="image/*" className="hidden" onChange={onAvatarSelect} />
                        </div>
                        <div className="space-y-2">
                            <div className="flex items-center gap-2">
                                <Badge variant="outline" className="bg-background/80">
                                    {user.role}
                                </Badge>
                                {profileCompletion === 100 && (
                                    <Badge className="bg-primary/15 text-primary">
                                        <CheckCircle2 className="mr-1 h-3 w-3" />
                                        Complete
                                    </Badge>
                                )}
                            </div>
                            <h2 className="text-4xl font-bold tracking-tight">{user.name}</h2>
                            <p className="text-muted-foreground text-lg">
                                {isStudent
                                    ? student?.course?.title
                                        ? `${student.course.title}${student.formatted_academic_year ? ` - ${student.formatted_academic_year}` : ""}`
                                        : "Student"
                                    : facultyName || position || (isFaculty ? "Faculty Member" : "")}
                            </p>
                            <div className="text-muted-foreground flex flex-wrap items-center gap-3 text-sm">
                                {isStudent && student?.course?.code && (
                                    <span className="flex items-center gap-1">
                                        <GraduationCap className="h-4 w-4" />
                                        {student.course.code}
                                    </span>
                                )}
                                {isStudent && student?.student_id && (
                                    <span className="flex items-center gap-1">
                                        <User className="h-4 w-4" />
                                        ID: {student.student_id}
                                    </span>
                                )}
                                {!isStudent && department && (
                                    <span className="flex items-center gap-1">
                                        <GraduationCap className="h-4 w-4" />
                                        {department}
                                    </span>
                                )}
                                {campusLocation && (
                                    <span className="flex items-center gap-1">
                                        <MapPin className="h-4 w-4" />
                                        {campusLocation}
                                    </span>
                                )}
                            </div>
                        </div>
                    </div>
                    <div className="flex gap-3">
                        <Button variant="secondary" onClick={onTriggerAvatarPicker}>
                            <UploadCloud className="mr-2 h-4 w-4" />
                            Change Photo
                        </Button>
                        {hasChanges && (
                            <Button onClick={onSaveClick}>
                                <Save className="mr-2 h-4 w-4" />
                                Save All
                            </Button>
                        )}
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}
