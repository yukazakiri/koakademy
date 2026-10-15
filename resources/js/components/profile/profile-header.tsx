import React from "react";
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar";
import { Badge as ReUiBadge } from "@/components/reui/badge";
import { Button } from "@/components/ui/button";
import { Frame, FramePanel } from "@/components/reui/frame";
import { Progress } from "@/components/ui/progress";
import {
    BookOpen,
    Camera,
    CheckCircle2,
    Clock,
    GraduationCap,
    MapPin,
    Sparkles,
    UploadCloud,
    User,
} from "lucide-react";

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
    campusLocation: string;
    facultyName: string;
    coursesCount?: number;
    officeHoursDisplay?: string;
    onAvatarSelect: (event: React.ChangeEvent<HTMLInputElement>) => void;
    onTriggerAvatarPicker: () => void;
    onSaveClick?: () => void;
}

export function ProfileHeader({
    user,
    student,
    isStudent,
    isFaculty,
    profileCompletion,
    avatarPreview,
    avatarInputRef,
    department,
    campusLocation,
    facultyName,
    coursesCount = 0,
    officeHoursDisplay,
    onAvatarSelect,
    onTriggerAvatarPicker,
}: ProfileHeaderProps) {
    const roleLabel = user.role.replace(/_/g, " ");

    return (
        <Frame
            variant="default"
            spacing="sm"
            className="border-border/70 bg-gradient-to-br from-card/90 via-card/75 to-muted/20 relative overflow-hidden rounded-2xl shadow-sm"
        >
            {/* Ambient decorative elements */}
            <div className="pointer-events-none absolute -right-16 -top-16 h-56 w-56 rounded-full bg-primary/5 blur-3xl" />
            <div className="pointer-events-none absolute right-1/4 -bottom-12 h-36 w-36 rounded-full bg-info/5 blur-2xl" />

            <FramePanel className="relative border-0 bg-transparent p-5 sm:p-6 shadow-none">
                <div className="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
                    {/* User Identity Column */}
                    <div className="flex flex-col items-center gap-5 text-center sm:flex-row sm:text-left">
                        {/* Avatar with floating camera trigger */}
                        <div className="relative group shrink-0">
                            <div className="relative overflow-hidden rounded-2xl ring-4 ring-background shadow-md">
                                <Avatar className="h-20 w-20 sm:h-24 sm:w-24 rounded-2xl">
                                    <AvatarImage src={avatarPreview} alt={user.name} className="object-cover" />
                                    <AvatarFallback className="bg-primary/10 text-primary text-2xl font-bold rounded-2xl">
                                        {(user.name || "U").slice(0, 2).toUpperCase()}
                                    </AvatarFallback>
                                </Avatar>
                                <button
                                    type="button"
                                    onClick={onTriggerAvatarPicker}
                                    aria-label="Upload profile picture"
                                    className="absolute inset-0 flex items-center justify-center bg-black/40 text-white opacity-0 transition-opacity duration-200 group-hover:opacity-100 backdrop-blur-[2px]"
                                >
                                    <Camera className="h-6 w-6" />
                                </button>
                            </div>
                            <Button
                                type="button"
                                size="icon"
                                variant="secondary"
                                className="absolute -bottom-2 -right-2 h-8 w-8 rounded-full border border-border bg-background shadow-md hover:bg-muted"
                                onClick={onTriggerAvatarPicker}
                                title="Change profile photo"
                            >
                                <Camera className="h-3.5 w-3.5" />
                            </Button>
                            <input
                                ref={avatarInputRef}
                                type="file"
                                accept="image/*"
                                className="hidden"
                                onChange={onAvatarSelect}
                            />
                        </div>

                        {/* Details */}
                        <div className="min-w-0 space-y-2">
                            <div className="flex flex-wrap items-center justify-center gap-2 sm:justify-start">
                                <ReUiBadge variant="primary-light" size="sm" className="font-semibold uppercase tracking-wider text-[11px]">
                                    {roleLabel}
                                </ReUiBadge>
                                {profileCompletion === 100 ? (
                                    <ReUiBadge variant="success-light" size="sm" className="gap-1 font-medium text-[11px]">
                                        <CheckCircle2 className="h-3 w-3" />
                                        Complete
                                    </ReUiBadge>
                                ) : (
                                    <ReUiBadge variant="warning-light" size="sm" className="gap-1 font-medium text-[11px]">
                                        <Sparkles className="h-3 w-3" />
                                        {profileCompletion}% complete
                                    </ReUiBadge>
                                )}
                            </div>

                            <h1 className="text-foreground text-2xl sm:text-3xl font-bold tracking-tight">
                                {user.name}
                            </h1>

                            <p className="text-muted-foreground text-sm font-medium">
                                {isStudent
                                    ? student?.course?.title
                                        ? `${student.course.title}${student.formatted_academic_year ? ` • ${student.formatted_academic_year}` : ""}`
                                        : "Student Portal"
                                    : facultyName || (isFaculty ? (department ? `${department} Faculty` : "Faculty Member") : "KoAkademy User")}
                            </p>

                            {/* Metadata Pills */}
                            <div className="flex flex-wrap items-center justify-center gap-2 text-xs text-muted-foreground sm:justify-start sm:gap-3 pt-1">
                                {isStudent && student?.course?.code && (
                                    <span className="inline-flex items-center gap-1.5 rounded-md bg-muted/60 px-2 py-0.5 font-medium">
                                        <GraduationCap className="h-3.5 w-3.5 text-primary" />
                                        {student.course.code}
                                    </span>
                                )}
                                {isStudent && student?.student_id && (
                                    <span className="inline-flex items-center gap-1.5 rounded-md bg-muted/60 px-2 py-0.5 font-medium">
                                        <User className="h-3.5 w-3.5 text-primary" />
                                        ID #{student.student_id}
                                    </span>
                                )}
                                {!isStudent && department && (
                                    <span className="inline-flex items-center gap-1.5 rounded-md bg-muted/60 px-2 py-0.5 font-medium">
                                        <GraduationCap className="h-3.5 w-3.5 text-primary" />
                                        {department}
                                    </span>
                                )}
                                {campusLocation && (
                                    <span className="inline-flex items-center gap-1.5 rounded-md bg-muted/60 px-2 py-0.5 font-medium">
                                        <MapPin className="h-3.5 w-3.5 text-primary" />
                                        {campusLocation}
                                    </span>
                                )}
                                {isFaculty && coursesCount > 0 && (
                                    <span className="inline-flex items-center gap-1.5 rounded-md bg-muted/60 px-2 py-0.5 font-medium">
                                        <BookOpen className="h-3.5 w-3.5 text-info" />
                                        {coursesCount} Active {coursesCount === 1 ? "Course" : "Courses"}
                                    </span>
                                )}
                                {isFaculty && officeHoursDisplay && officeHoursDisplay !== "Set your weekly hours" && (
                                    <span className="inline-flex items-center gap-1.5 rounded-md bg-muted/60 px-2 py-0.5 font-medium">
                                        <Clock className="h-3.5 w-3.5 text-info" />
                                        {officeHoursDisplay}
                                    </span>
                                )}
                            </div>
                        </div>
                    </div>

                    {/* Completion Mini Card & Actions */}
                    <div className="flex flex-col sm:flex-row lg:flex-col items-center sm:items-stretch lg:items-end gap-3 shrink-0">
                        <div className="w-full sm:w-60 rounded-xl border border-border/60 bg-background/60 p-3 backdrop-blur-sm">
                            <div className="flex items-center justify-between text-xs mb-1.5">
                                <span className="text-muted-foreground font-medium">Profile strength</span>
                                <span className="font-semibold text-foreground">{profileCompletion}%</span>
                            </div>
                            <Progress value={profileCompletion} className="h-2 rounded-full" />
                            <p className="text-[11px] text-muted-foreground mt-1.5">
                                {profileCompletion === 100
                                    ? "All essential details provided"
                                    : "Complete details for institutional records"}
                            </p>
                        </div>

                        <div className="flex items-center gap-2 w-full sm:w-auto">
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={onTriggerAvatarPicker}
                                className="w-full sm:w-auto rounded-xl text-xs font-medium gap-1.5"
                            >
                                <UploadCloud className="h-3.5 w-3.5" />
                                Update Photo
                            </Button>
                        </div>
                    </div>
                </div>
            </FramePanel>
        </Frame>
    );
}

