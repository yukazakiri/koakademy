import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Sheet, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle } from "@/components/ui/sheet";
import { cn } from "@/lib/utils";
import { StudentEntry } from "@/types/class-detail-types";
import { IconCalendar, IconMail, IconMapPin, IconPhone, IconSchool, IconUser, IconUserCheck } from "@tabler/icons-react";

interface StudentProfileSheetProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    student: StudentEntry | null;
    studentInfo: Record<string, any> | null;
    loading: boolean;
    onTrackAttendance: (studentId: number) => void;
}

export function StudentProfileSheet({ open, onOpenChange, student, studentInfo, loading, onTrackAttendance }: StudentProfileSheetProps) {
    const isMobile = typeof window !== "undefined" ? window.innerWidth < 768 : false; // Simple check, ideally use hook but hooks are fine

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent side={isMobile ? "bottom" : "right"} className="bg-card text-foreground flex h-full w-[800px] flex-col sm:w-[540px]">
                <SheetHeader className="border-border/60 bg-card/80 space-y-2 border-b px-6 py-4">
                    <SheetTitle className="text-2xl">Student Information</SheetTitle>
                    <SheetDescription>{student ? student.name : "Loading..."}</SheetDescription>
                </SheetHeader>

                {loading ? (
                    <div className="flex flex-1 items-center justify-center">
                        <div className="text-center">
                            <div className="border-primary mx-auto h-12 w-12 animate-spin rounded-full border-b-2"></div>
                            <p className="text-muted-foreground mt-4 text-sm">Loading student information...</p>
                        </div>
                    </div>
                ) : student ? (
                    <div className="flex-1 overflow-y-auto">
                        {/* Hero Header */}
                        <div className="bg-muted/30 relative pt-10 pb-8">
                            <div className="from-primary/5 absolute inset-0 bg-gradient-to-b to-transparent" />
                            <div className="relative flex flex-col items-center px-6 text-center">
                                <div className="relative mb-4">
                                    <div className="bg-background text-primary ring-background flex size-32 items-center justify-center overflow-hidden rounded-full shadow-xl ring-4">
                                        {studentInfo?.picture ? (
                                            <img src={studentInfo.picture} alt={student.name} className="h-full w-full object-cover" />
                                        ) : (
                                            <span className="text-4xl font-bold">{student.name.charAt(0).toUpperCase()}</span>
                                        )}
                                    </div>
                                    {studentInfo && (
                                        <div
                                            className={cn(
                                                "border-background absolute right-1 bottom-1 size-6 rounded-full border-4",
                                                studentInfo.status === "Enrolled" ? "bg-emerald-500" : "bg-amber-500",
                                            )}
                                        />
                                    )}
                                </div>
                                <h2 className="text-2xl font-bold">{student.name}</h2>
                                <p className="text-muted-foreground font-mono">{student.student_id}</p>

                                <div className="mt-6 flex flex-wrap justify-center gap-2">
                                    <Badge variant={student.status === "Active" ? "default" : "secondary"}>{student.status}</Badge>
                                </div>

                                {/* Quick Actions (Only show if studentInfo is loaded for email/id) */}
                                {studentInfo && (
                                    <div className="mt-8 flex w-full max-w-md items-center justify-center gap-4">
                                        <Button
                                            className="flex-1 gap-2 shadow-sm"
                                            onClick={() => {
                                                onOpenChange(false);
                                                onTrackAttendance(
                                                    typeof studentInfo.id === "string" ? parseInt(studentInfo.id) : (studentInfo.id as number),
                                                );
                                            }}
                                        >
                                            <IconUserCheck className="size-4" />
                                            Track Attendance
                                        </Button>
                                        <Button
                                            variant="outline"
                                            className="bg-background flex-1 gap-2 shadow-sm"
                                            onClick={() => window.open(`mailto:${studentInfo.email}`, "_blank")}
                                            disabled={!studentInfo.email}
                                        >
                                            <IconMail className="size-4" />
                                            Email
                                        </Button>
                                    </div>
                                )}
                            </div>
                        </div>

                        {/* Only show detailed info if available */}
                        {studentInfo ? (
                            <div className="space-y-8 px-6 py-8">
                                {/* Personal Information Section */}
                                <div className="space-y-4">
                                    <div className="border-border/50 flex items-center gap-2 border-b pb-2">
                                        <div className="bg-primary/10 text-primary rounded-lg p-2">
                                            <IconUser className="size-5" />
                                        </div>
                                        <h3 className="text-lg font-semibold">Personal Details</h3>
                                    </div>

                                    <div className="grid grid-cols-1 gap-x-8 gap-y-6 md:grid-cols-2">
                                        <div className="space-y-1">
                                            <p className="text-muted-foreground text-xs font-medium tracking-wider uppercase">Student ID</p>
                                            <p className="text-sm font-medium">{studentInfo.student_id}</p>
                                        </div>
                                        {studentInfo.lrn && (
                                            <div className="space-y-1">
                                                <p className="text-muted-foreground text-xs font-medium tracking-wider uppercase">LRN</p>
                                                <p className="text-sm font-medium">{studentInfo.lrn}</p>
                                            </div>
                                        )}
                                        <div className="space-y-1">
                                            <p className="text-muted-foreground text-xs font-medium tracking-wider uppercase">Email Address</p>
                                            <div className="flex items-center gap-2">
                                                <IconMail className="text-muted-foreground size-3.5" />
                                                <p className="text-sm font-medium">{studentInfo.email || "N/A"}</p>
                                            </div>
                                        </div>
                                        <div className="space-y-1">
                                            <p className="text-muted-foreground text-xs font-medium tracking-wider uppercase">Phone Number</p>
                                            <div className="flex items-center gap-2">
                                                <IconPhone className="text-muted-foreground size-3.5" />
                                                <p className="text-sm font-medium">{studentInfo.phone || "N/A"}</p>
                                            </div>
                                        </div>
                                        <div className="space-y-1">
                                            <p className="text-muted-foreground text-xs font-medium tracking-wider uppercase">Gender</p>
                                            <p className="text-sm font-medium">{studentInfo.gender || "N/A"}</p>
                                        </div>
                                        <div className="space-y-1">
                                            <p className="text-muted-foreground text-xs font-medium tracking-wider uppercase">Civil Status</p>
                                            <p className="text-sm font-medium">{studentInfo.civil_status || "N/A"}</p>
                                        </div>
                                        <div className="space-y-1">
                                            <p className="text-muted-foreground text-xs font-medium tracking-wider uppercase">Nationality</p>
                                            <p className="text-sm font-medium">{studentInfo.nationality || "N/A"}</p>
                                        </div>
                                        <div className="space-y-1">
                                            <p className="text-muted-foreground text-xs font-medium tracking-wider uppercase">Religion</p>
                                            <p className="text-sm font-medium">{studentInfo.religion || "N/A"}</p>
                                        </div>
                                        {studentInfo.birth_date && (
                                            <div className="space-y-1">
                                                <p className="text-muted-foreground text-xs font-medium tracking-wider uppercase">Birth Date</p>
                                                <div className="flex items-center gap-2">
                                                    <IconCalendar className="text-muted-foreground size-3.5" />
                                                    <p className="text-sm font-medium">
                                                        {studentInfo.birth_date}
                                                        {studentInfo.age && (
                                                            <span className="text-muted-foreground ml-1">({studentInfo.age} years old)</span>
                                                        )}
                                                    </p>
                                                </div>
                                            </div>
                                        )}
                                    </div>

                                    {(studentInfo.address || studentInfo.emergency_contact) && (
                                        <div className="border-border/30 mt-6 grid grid-cols-1 gap-6 border-t pt-4">
                                            {studentInfo.address && (
                                                <div className="space-y-1">
                                                    <p className="text-muted-foreground text-xs font-medium tracking-wider uppercase">Address</p>
                                                    <div className="flex items-start gap-2">
                                                        <IconMapPin className="text-muted-foreground mt-0.5 size-3.5" />
                                                        <p className="text-sm leading-relaxed font-medium">{studentInfo.address}</p>
                                                    </div>
                                                </div>
                                            )}
                                            {studentInfo.emergency_contact && (
                                                <div className="space-y-1">
                                                    <p className="text-muted-foreground text-xs font-medium tracking-wider uppercase">
                                                        Emergency Contact
                                                    </p>
                                                    <p className="text-sm font-medium">{studentInfo.emergency_contact}</p>
                                                </div>
                                            )}
                                        </div>
                                    )}
                                </div>

                                {/* Academic Information Section */}
                                <div className="space-y-4">
                                    <div className="border-border/50 flex items-center gap-2 border-b pb-2">
                                        <div className="rounded-lg bg-blue-500/10 p-2 text-blue-600">
                                            <IconSchool className="size-5" />
                                        </div>
                                        <h3 className="text-lg font-semibold">Academic Details</h3>
                                    </div>

                                    <div className="grid grid-cols-1 gap-x-8 gap-y-6 md:grid-cols-2">
                                        <div className="col-span-full space-y-1">
                                            <p className="text-muted-foreground text-xs font-medium tracking-wider uppercase">Course</p>
                                            <p className="text-sm font-medium">
                                                {studentInfo.course?.name} <span className="text-muted-foreground">({studentInfo.course?.code})</span>
                                            </p>
                                        </div>
                                        <div className="space-y-1">
                                            <p className="text-muted-foreground text-xs font-medium tracking-wider uppercase">Academic Year</p>
                                            <p className="text-sm font-medium">{studentInfo.academic_year}</p>
                                        </div>
                                        <div className="space-y-1">
                                            <p className="text-muted-foreground text-xs font-medium tracking-wider uppercase">Student Type</p>
                                            <Badge variant="outline" className="font-normal">
                                                {studentInfo.student_type}
                                            </Badge>
                                        </div>
                                        <div className="space-y-1">
                                            <p className="text-muted-foreground text-xs font-medium tracking-wider uppercase">Enrollment Status</p>
                                            <p className="text-sm font-medium">{studentInfo.status}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        ) : (
                            <div className="text-muted-foreground p-10 text-center">
                                <p>Detailed profile information is loading or unavailable.</p>
                            </div>
                        )}
                    </div>
                ) : (
                    <div className="text-muted-foreground flex flex-1 flex-col items-center justify-center">
                        <IconUser className="h-16 w-16 opacity-20" />
                        <p className="mt-4">Select a student to view their information</p>
                    </div>
                )}

                <SheetFooter className="border-border/60 bg-card/90 border-t px-6 py-4">
                    <Button onClick={() => onOpenChange(false)} variant="outline" className="rounded-full">
                        Close
                    </Button>
                </SheetFooter>
            </SheetContent>
        </Sheet>
    );
}
