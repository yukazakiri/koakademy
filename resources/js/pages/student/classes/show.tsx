import { EnhancedClassHeader } from "@/components/class/enhanced-class-header";
import { ClassworkTab } from "@/components/class/tabs/classwork-tab";
import { StudentAttendanceTab } from "@/components/class/tabs/student-attendance-tab";
import { StudentPeopleTab } from "@/components/class/tabs/student-people-tab";
import { StudentStreamTab } from "@/components/class/tabs/student-stream-tab";
import StudentLayout from "@/components/student/student-layout";
import { Breadcrumb, BreadcrumbItem, BreadcrumbLink, BreadcrumbList, BreadcrumbPage, BreadcrumbSeparator } from "@/components/ui/breadcrumb";
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

            <div className="container mx-auto max-w-7xl space-y-8 px-4 py-6 md:px-8">
                {/* Breadcrumb Navigation */}
                <div className="flex items-center justify-between">
                    <Breadcrumb>
                        <BreadcrumbList>
                            <BreadcrumbItem>
                                <BreadcrumbLink asChild>
                                    <Link href="/student/dashboard">Dashboard</Link>
                                </BreadcrumbLink>
                            </BreadcrumbItem>
                            <BreadcrumbSeparator />
                            <BreadcrumbItem>
                                <BreadcrumbLink asChild>
                                    <Link href="/student/classes">My Classes</Link>
                                </BreadcrumbLink>
                            </BreadcrumbItem>
                            <BreadcrumbSeparator />
                            <BreadcrumbItem>
                                <BreadcrumbPage>{classData.subject_code}</BreadcrumbPage>
                            </BreadcrumbItem>
                        </BreadcrumbList>
                    </Breadcrumb>
                </div>

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
                    <TabsList className="no-scrollbar h-auto w-full justify-start gap-2 overflow-x-auto bg-transparent p-0">
                        <TabsTrigger
                            value="stream"
                            className="data-[state=active]:bg-primary/10 data-[state=active]:text-primary mb-1 rounded-full px-4 py-1 capitalize"
                        >
                            Stream
                        </TabsTrigger>
                        <TabsTrigger
                            value="classwork"
                            className="data-[state=active]:bg-primary/10 data-[state=active]:text-primary mb-1 rounded-full px-4 py-1 capitalize"
                        >
                            Classwork
                        </TabsTrigger>
                        <TabsTrigger
                            value="attendance"
                            className="data-[state=active]:bg-primary/10 data-[state=active]:text-primary mb-1 rounded-full px-4 py-1 capitalize"
                        >
                            My Attendance
                        </TabsTrigger>
                        <TabsTrigger
                            value="people"
                            className="data-[state=active]:bg-primary/10 data-[state=active]:text-primary mb-1 rounded-full px-4 py-1 capitalize"
                        >
                            Classmates
                        </TabsTrigger>
                    </TabsList>

                    <Separator className="bg-border/40 my-4" />

                    {/* Stream Tab */}
                    <TabsContent value="stream" className="animate-in fade-in-50 slide-in-from-bottom-2 mt-0 space-y-6 duration-500">
                        <StudentStreamTab classData={classData} teacher={teacher} classPosts={posts} />
                    </TabsContent>

                    {/* Classwork Tab */}
                    <TabsContent value="classwork" className="animate-in fade-in-50 slide-in-from-bottom-2 mt-0 duration-500">
                        <ClassworkTab classPosts={posts} />
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
