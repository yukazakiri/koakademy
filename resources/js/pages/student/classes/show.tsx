import { EnhancedClassHeader } from "@/components/class/enhanced-class-header";
import { ClassworkTab } from "@/components/class/tabs/classwork-tab";
import { StudentAttendanceTab } from "@/components/class/tabs/student-attendance-tab";
import { StudentPeopleTab } from "@/components/class/tabs/student-people-tab";
import { StudentStreamTab } from "@/components/class/tabs/student-stream-tab";
import StudentLayout from "@/components/student/student-layout";
import { Breadcrumb, BreadcrumbItem, BreadcrumbLink, BreadcrumbList, BreadcrumbPage, BreadcrumbSeparator } from "@/components/ui/breadcrumb";
import { Card, CardContent } from "@/components/ui/card";
import { Separator } from "@/components/ui/separator";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { ClassPostEntry, ClassSettings, ScheduleEntry, TeacherEntry } from "@/types/class-detail-types";
import { User } from "@/types/user";
import { Head, Link } from "@inertiajs/react";
import { useState } from "react";

interface StudentClassShowProps {
    user: User;
    classData: {
        id: number;
        subject_code: string;
        subject_title: string;
        section: string;
        classification: string;
        room: string;
        school_year: string;
        semester: string;
        schedules?: Array<{
            id: number;
            day: string;
            start: string;
            end: string;
            room: string;
        }>;
        settings: ClassSettings;
    };
    teacher: TeacherEntry;
    posts: ClassPostEntry[];
    schedule: ScheduleEntry[];
    my_grades: {
        prelim?: number;
        midterm?: number;
        final?: number;
        average?: number;
    };
    my_attendance: {
        stats: {
            present: number;
            late: number;
            absent: number;
            excused: number;
        };
        history: Array<{
            id: number;
            date: string;
            status: string;
            remarks: string;
            topic: string;
        }>;
    };
    classmates: Array<{
        id: number;
        name: string;
        avatar: string;
    }>;
}

const dashboardPanelClass = "border-border/60 bg-card/75 rounded-lg shadow-sm";

export default function StudentClassShow({
    user,
    classData,
    teacher,
    posts = [],
    schedule = [],
    my_grades,
    my_attendance,
    classmates = [],
}: StudentClassShowProps) {
    const [activeTab, setActiveTab] = useState("stream");

    // Helper to safely format schedule for header if needed, though EnhancedClassHeader expects specific format
    // We can pass schedule directly as it matches ScheduleEntry[] mostly or close enough

    return (
        <StudentLayout
            user={{
                name: user.name,
                email: user.email,
                avatar: user.avatar,
                role: user.role,
            }}
        >
            <Head title={`${classData.subject_code} - ${classData.section}`} />

            <div className="mx-auto flex w-full max-w-7xl flex-col gap-5 p-4 pb-16 md:gap-6 md:p-6">
                {/* Breadcrumb Navigation */}
                <Card className={dashboardPanelClass}>
                    <CardContent className="p-4 md:p-5">
                        <Breadcrumb>
                            <BreadcrumbList className="gap-1 text-xs sm:text-sm">
                                <BreadcrumbItem>
                                    <BreadcrumbLink asChild>
                                        <Link href="/student/dashboard">Dashboard</Link>
                                    </BreadcrumbLink>
                                </BreadcrumbItem>
                                <BreadcrumbSeparator />
                                <BreadcrumbItem>
                                    <BreadcrumbLink asChild>
                                        <Link href="/student/classes">My Academics</Link>
                                    </BreadcrumbLink>
                                </BreadcrumbItem>
                                <BreadcrumbSeparator />
                                <BreadcrumbItem>
                                    <BreadcrumbPage className="max-w-[10rem] truncate sm:max-w-none">{classData.subject_code}</BreadcrumbPage>
                                </BreadcrumbItem>
                            </BreadcrumbList>
                        </Breadcrumb>
                    </CardContent>
                </Card>

                {/* Enhanced Class Header - Read Only mostly */}
                <EnhancedClassHeader
                    classData={classData}
                    teacher={teacher}
                    schedule={schedule}
                    enrollmentStats={{
                        current_count: classmates.length,
                        max_slots: 0, // Not needed for student view usually
                    }}
                    // Hide actions for students
                    isStudent={true}
                />

                <Tabs value={activeTab} onValueChange={setActiveTab} className="w-full">
                    <Card className={dashboardPanelClass}>
                        <CardContent className="p-2">
                            <TabsList className="no-scrollbar bg-muted/25 h-auto w-full justify-start gap-1 overflow-x-auto rounded-lg p-1">
                                <TabsTrigger
                                    value="stream"
                                    className="data-[state=active]:bg-primary/10 data-[state=active]:text-primary rounded-lg px-4 py-2 capitalize"
                                >
                                    Stream
                                </TabsTrigger>
                                <TabsTrigger
                                    value="classwork"
                                    className="data-[state=active]:bg-primary/10 data-[state=active]:text-primary rounded-lg px-4 py-2 capitalize"
                                >
                                    Classwork
                                </TabsTrigger>
                                <TabsTrigger
                                    value="attendance"
                                    className="data-[state=active]:bg-primary/10 data-[state=active]:text-primary rounded-lg px-4 py-2 capitalize"
                                >
                                    My Attendance
                                </TabsTrigger>
                                <TabsTrigger
                                    value="people"
                                    className="data-[state=active]:bg-primary/10 data-[state=active]:text-primary rounded-lg px-4 py-2 capitalize"
                                >
                                    Classmates
                                </TabsTrigger>
                            </TabsList>
                        </CardContent>
                    </Card>

                    <Separator className="bg-border/40 my-4" />

                    {/* Stream Tab */}
                    <TabsContent value="stream" className="animate-in fade-in-50 slide-in-from-bottom-2 mt-0 space-y-6 duration-500">
                        <StudentStreamTab classData={classData} teacher={teacher} classPosts={posts} />
                    </TabsContent>

                    {/* Classwork Tab */}
                    <TabsContent value="classwork" className="animate-in fade-in-50 slide-in-from-bottom-2 mt-0 duration-500">
                        <ClassworkTab classPosts={posts} isStudentView={true} />
                    </TabsContent>

                    {/* Attendance Tab */}
                    <TabsContent value="attendance" className="animate-in fade-in-50 slide-in-from-bottom-2 mt-0 space-y-6 duration-500">
                        <StudentAttendanceTab stats={my_attendance.stats} history={my_attendance.history} />
                    </TabsContent>

                    {/* People Tab */}
                    <TabsContent value="people" className="animate-in fade-in-50 slide-in-from-bottom-2 mt-0 duration-500">
                        <StudentPeopleTab teacher={teacher} classmates={classmates} />
                    </TabsContent>
                </Tabs>
            </div>
        </StudentLayout>
    );
}
