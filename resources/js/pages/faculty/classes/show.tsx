import { ActivityLogSheet } from "@/components/class/activity-log-sheet";
import { ClassSettingsDialog } from "@/components/class/class-settings-sheet";
import { EnhancedClassHeader } from "@/components/class/enhanced-class-header";
import { StudentProfileSheet } from "@/components/class/student-profile-sheet";
import { AttendanceTab } from "@/components/class/tabs/attendance-tab";
import { ClassworkTab } from "@/components/class/tabs/classwork-tab";
import { PeopleTab } from "@/components/class/tabs/people-tab";
import { StreamTab } from "@/components/class/tabs/stream-tab";
import FacultyLayout from "@/components/faculty/faculty-layout";
import { GradeSheet } from "@/components/grade-sheet";
import { Breadcrumb, BreadcrumbItem, BreadcrumbLink, BreadcrumbList, BreadcrumbPage, BreadcrumbSeparator } from "@/components/ui/breadcrumb";
import { Separator } from "@/components/ui/separator";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from "@/components/ui/tooltip";
import { AttendanceOverview, ClassPostEntry, ClassSettings, MetricCard, ScheduleEntry, StudentEntry, TeacherEntry } from "@/types/class-detail-types";
import { User } from "@/types/user";
import { Head, Link } from "@inertiajs/react";
import axios from "axios";
import { useState } from "react";
import { toast } from "sonner";

interface ScheduleOption {
    id: string;
    label: string;
    day: string;
    room?: string | null;
    raw: ScheduleEntry;
}

interface ClassDetailProps {
    user: User;
    classData: {
        id: number;
        subject_code: string;
        subject_title: string;
        course_title: string;
        section: string;
        classification: string;
        schedules?: Array<{
            id: number;
            day_of_week: string;
            start_time: string;
            end_time: string;
            room_id: number;
        }>;
        settings: ClassSettings;
    };
    current_faculty: {
        id: string | null;
        name: string;
        email?: string | null;
    };
    teacher: TeacherEntry;
    students: StudentEntry[];
    posts: ClassPostEntry[];
    schedule: ScheduleEntry[];
    attendance: AttendanceOverview;
    auto_average: boolean;
    metrics?: MetricCard[] | null;
    rooms?: { id: number; name: string }[];
}

export default function ClassDetail({
    user,
    classData,
    current_faculty,
    teacher,
    students = [],
    posts = [],
    schedule = [],
    attendance,
    auto_average,
    rooms = [],
}: ClassDetailProps) {
    const [activeTab, setActiveTab] = useState("stream");
    const [isSettingsOpen, setIsSettingsOpen] = useState(false);
    const [isActivityLogOpen, setIsActivityLogOpen] = useState(false);

    // Student Info Sheet State
    const [studentInfoOpen, setStudentInfoOpen] = useState(false);
    const [selectedStudent, setSelectedStudent] = useState<StudentEntry | null>(null);
    const [studentInfo, setStudentInfo] = useState<Record<string, unknown> | null>(null);
    const [loadingStudentInfo, setLoadingStudentInfo] = useState(false);
    const [focusedAttendanceStudentId, setFocusedAttendanceStudentId] = useState<number | null>(null);

    const scheduleOptions: ScheduleOption[] = schedule.map((s) => ({
        id: s.id.toString(),
        label: `${s.day} ${s.start}-${s.end}`,
        day: s.day,
        room: s.room,
        raw: s,
    }));

    const toLocalYmd = (date: Date): string => {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, "0");
        const day = String(date.getDate()).padStart(2, "0");

        return `${year}-${month}-${day}`;
    };

    const defaultSessionDate = toLocalYmd(new Date());
    const classStartDate = classData.settings.start_date ? new Date(classData.settings.start_date) : null;

    const handleTabChange = (value: string) => {
        setActiveTab(value);
    };

    const handleViewStudentInfo = async (student: StudentEntry) => {
        setSelectedStudent(student);
        setStudentInfoOpen(true);
        setLoadingStudentInfo(true);
        setStudentInfo(null);

        try {
            if (!student.student_db_id) {
                throw new Error("Student record incomplete");
            }
            const response = await axios.get(`/faculty/students/${student.student_db_id}`);
            setStudentInfo(response.data.student);
        } catch (error) {
            console.error("Failed to fetch student details:", error);
            toast.error("Could not load student information");
            setStudentInfo(null);
        } finally {
            setLoadingStudentInfo(false);
        }
    };

    const handleTrackAttendance = (studentId: number) => {
        setFocusedAttendanceStudentId(studentId);
        setActiveTab("attendance");
        setStudentInfoOpen(false);
    };

    // Derived settings
    const settings = classData.settings;

    return (
        <FacultyLayout
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
                                    <Link href="/faculty/dashboard">Dashboard</Link>
                                </BreadcrumbLink>
                            </BreadcrumbItem>
                            <BreadcrumbSeparator />
                            <BreadcrumbItem>
                                <BreadcrumbLink asChild>
                                    <Link href="/faculty/classes">My Classes</Link>
                                </BreadcrumbLink>
                            </BreadcrumbItem>
                            <BreadcrumbSeparator />
                            <BreadcrumbItem>
                                <BreadcrumbPage>{classData.subject_code}</BreadcrumbPage>
                            </BreadcrumbItem>
                        </BreadcrumbList>
                    </Breadcrumb>
                </div>

                {/* Enhanced Class Header */}
                <EnhancedClassHeader
                    classData={classData}
                    teacher={teacher}
                    schedule={schedule}
                    enrollmentStats={{
                        current_count: students.length,
                        max_slots: 40, // This should come from classData in real implementation
                    }}
                    onSettingsClick={() => setIsSettingsOpen(true)}
                    onActivityLogClick={() => setIsActivityLogOpen(true)}
                />

                <Tabs value={activeTab} onValueChange={handleTabChange} className="w-full">
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
                            Attendance
                        </TabsTrigger>
                        {settings.enable_grade_visibility && (
                            <TabsTrigger
                                value="people"
                                className="data-[state=active]:bg-primary/10 data-[state=active]:text-primary mb-1 rounded-full px-4 py-1 capitalize"
                            >
                                People
                            </TabsTrigger>
                        )}

                        <TooltipProvider delayDuration={150}>
                            <Tooltip>
                                <TooltipTrigger asChild>
                                    <TabsTrigger
                                        value="grades"
                                        disabled={classData.classification === "shs"} // SHS classification check
                                        className="data-[state=active]:bg-primary/10 data-[state=active]:text-primary mb-1 rounded-full px-4 py-1 capitalize disabled:opacity-50"
                                    >
                                        Grades
                                    </TabsTrigger>
                                </TooltipTrigger>
                                {classData.classification === "shs" && (
                                    <TooltipContent side="top">Grades are managed in LIS for SHS classes.</TooltipContent>
                                )}
                            </Tooltip>
                        </TooltipProvider>
                    </TabsList>

                    <Separator className="bg-border/40 my-4" />

                    {/* Stream Tab */}
                    <TabsContent value="stream" className="animate-in fade-in-50 slide-in-from-bottom-2 mt-0 space-y-6 duration-500">
                        <StreamTab classData={classData} currentFaculty={current_faculty} students={students} classPosts={posts} />
                    </TabsContent>

                    {/* Classwork Tab */}
                    <TabsContent value="classwork" className="animate-in fade-in-50 slide-in-from-bottom-2 mt-0 duration-500">
                        <ClassworkTab
                            classId={classData.id}
                            classCode={classData.subject_code}
                            classSection={classData.section}
                            currentFacultyId={current_faculty.id}
                            classPosts={posts}
                            students={students}
                        />
                    </TabsContent>

                    {/* Attendance Tab */}
                    <TabsContent value="attendance" className="animate-in fade-in-50 slide-in-from-bottom-2 mt-0 space-y-6 duration-500">
                        <AttendanceTab
                            classData={classData}
                            attendance={attendance}
                            scheduleOptions={scheduleOptions}
                            classSchedules={classData.schedules ?? []}
                            rooms={rooms}
                            defaultSessionDate={defaultSessionDate}
                            classStartDate={classStartDate}
                            focusStudentId={focusedAttendanceStudentId}
                            onClearFocus={() => setFocusedAttendanceStudentId(null)}
                        />
                    </TabsContent>

                    {/* People Tab */}
                    <TabsContent value="people" className="animate-in fade-in-50 slide-in-from-bottom-2 mt-0 duration-500">
                        <PeopleTab
                            classData={classData}
                            teacher={teacher}
                            students={students}
                            onViewProfile={handleViewStudentInfo}
                            onTrackAttendance={(id) => handleTrackAttendance(id as number)}
                            onViewPublicInfo={() => toast.info("Public profile view coming soon")}
                        />
                    </TabsContent>

                    {/* Grades Tab */}
                    <TabsContent value="grades" className="animate-in fade-in-50 slide-in-from-bottom-2 mt-0 duration-500">
                        <GradeSheet
                            classId={classData.id}
                            students={students.map((student) => ({
                                id: student.id,
                                name: student.name,
                                studentId: student.student_id,
                                grades: {
                                    prelim: student.grades.prelim ?? null,
                                    midterm: student.grades.midterm ?? null,
                                    final: student.grades.final ?? null,
                                    average: student.grades.average ?? null,
                                },
                            }))}
                            autoAverageDefault={auto_average}
                        />
                    </TabsContent>
                </Tabs>
            </div>

            <ClassSettingsDialog open={isSettingsOpen} onOpenChange={setIsSettingsOpen} classData={classData} rooms={rooms} />

            <StudentProfileSheet
                open={studentInfoOpen}
                onOpenChange={setStudentInfoOpen}
                student={selectedStudent}
                studentInfo={studentInfo}
                loading={loadingStudentInfo}
                onTrackAttendance={(id) => handleTrackAttendance(id)}
            />

            <ActivityLogSheet
                open={isActivityLogOpen}
                onOpenChange={setIsActivityLogOpen}
                classId={classData.id}
                className={`${classData.subject_code} - ${classData.section}`}
            />
        </FacultyLayout>
    );
}
