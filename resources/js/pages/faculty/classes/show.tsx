import { ActivityLogSheet } from "@/components/class/activity-log-sheet";
import { ClassSettingsDialog } from "@/components/class/class-settings-sheet";
import { ClassroomNavigation, ClassroomTab } from "@/components/class/classroom-navigation";
import { ClassroomUpcoming } from "@/components/class/classroom-upcoming";
import { EnhancedClassHeader } from "@/components/class/enhanced-class-header";
import { StudentProfileSheet } from "@/components/class/student-profile-sheet";
import { AttendanceTab } from "@/components/class/tabs/attendance-tab";
import { ClassworkTab } from "@/components/class/tabs/classwork-tab";
import { PeopleTab } from "@/components/class/tabs/people-tab";
import { StreamTab } from "@/components/class/tabs/stream-tab";
import FacultyLayout from "@/components/faculty/faculty-layout";
import { GradeSheet } from "@/components/grade-sheet";
import { Button } from "@/components/ui/button";
import { Tabs, TabsContent } from "@/components/ui/tabs";
import type { GradingConfigPayload } from "@/pages/administrators/system-management/types";
import { AttendanceOverview, ClassPostEntry, ClassSettings, ScheduleEntry, StudentEntry, TeacherEntry } from "@/types/class-detail-types";
import { User } from "@/types/user";
import { Head, Link } from "@inertiajs/react";
import axios from "axios";
import { ArrowLeft, BookOpenCheck, GraduationCap, Radio, UserCheck, UsersRound } from "lucide-react";
import { useEffect, useMemo, useState } from "react";
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
        schedules?: Array<{ id: number; day_of_week: string; start_time: string; end_time: string; room_id: number }>;
        settings: ClassSettings;
    };
    current_faculty: { id: string | null; name: string; email?: string | null };
    teacher: TeacherEntry;
    students: StudentEntry[];
    posts: ClassPostEntry[];
    schedule: ScheduleEntry[];
    attendance: AttendanceOverview;
    auto_average: boolean;
    grading_policy: GradingConfigPayload;
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
    grading_policy,
    rooms = [],
}: ClassDetailProps) {
    const tabs = useMemo<ClassroomTab[]>(
        () => [
            { value: "stream", label: "Stream", icon: Radio },
            { value: "classwork", label: "Classwork", icon: BookOpenCheck },
            { value: "attendance", label: "Attendance", icon: UserCheck, description: "Manage sessions and student records" },
            { value: "people", label: "People", icon: UsersRound, description: "Manage the class roster" },
            {
                value: "grades",
                label: "Grades",
                icon: GraduationCap,
                description: classData.classification === "shs" ? "Managed through LIS for SHS classes" : "Record and submit term grades",
                disabled: classData.classification === "shs",
            },
        ],
        [classData.classification],
    );
    const [activeTab, setActiveTab] = useState("stream");
    const [isSettingsOpen, setIsSettingsOpen] = useState(false);
    const [isActivityLogOpen, setIsActivityLogOpen] = useState(false);
    const [studentInfoOpen, setStudentInfoOpen] = useState(false);
    const [selectedStudent, setSelectedStudent] = useState<StudentEntry | null>(null);
    const [studentInfo, setStudentInfo] = useState<Record<string, unknown> | null>(null);
    const [loadingStudentInfo, setLoadingStudentInfo] = useState(false);
    const [focusedAttendanceStudentId, setFocusedAttendanceStudentId] = useState<number | null>(null);

    useEffect(() => {
        const requested = new URLSearchParams(window.location.search).get("tab");
        if (requested && tabs.some((tab) => tab.value === requested && !tab.disabled)) setActiveTab(requested);
    }, [tabs]);

    const handleTabChange = (value: string) => {
        const tab = tabs.find((item) => item.value === value);
        if (!tab || tab.disabled) return;
        setActiveTab(value);
        const url = new URL(window.location.href);
        url.searchParams.set("tab", value);
        window.history.replaceState({}, "", url);
    };

    const scheduleOptions: ScheduleOption[] = schedule.map((item) => ({
        id: item.id.toString(),
        label: `${item.day} ${item.start}-${item.end}`,
        day: item.day,
        room: item.room,
        raw: item,
    }));
    const toLocalYmd = (date: Date) =>
        `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}-${String(date.getDate()).padStart(2, "0")}`;
    const classStartDate = classData.settings.start_date ? new Date(classData.settings.start_date) : null;

    const handleViewStudentInfo = async (student: StudentEntry) => {
        setSelectedStudent(student);
        setStudentInfoOpen(true);
        setLoadingStudentInfo(true);
        setStudentInfo(null);
        try {
            if (!student.student_db_id) throw new Error("Student record incomplete");
            const response = await axios.get(`/faculty/students/${student.student_db_id}`);
            setStudentInfo(response.data.student);
        } catch {
            toast.error("Could not load student information");
        } finally {
            setLoadingStudentInfo(false);
        }
    };

    const handleTrackAttendance = (studentId: number) => {
        setFocusedAttendanceStudentId(studentId);
        handleTabChange("attendance");
        setStudentInfoOpen(false);
    };

    return (
        <FacultyLayout user={{ name: user.name, email: user.email, avatar: user.avatar, role: user.role }}>
            <Head title={`${classData.subject_code} - ${classData.section}`} />
            <main className="mx-auto flex w-full max-w-7xl flex-col gap-4 px-3 py-4 pb-20 sm:px-5 md:gap-5 md:px-6 md:py-6">
                <Button asChild variant="ghost" size="sm" className="w-fit gap-2 px-1">
                    <Link href="/faculty/classes">
                        <ArrowLeft className="size-4" />
                        My classes
                    </Link>
                </Button>
                <EnhancedClassHeader
                    classData={classData}
                    teacher={teacher}
                    schedule={schedule}
                    enrollmentStats={{ current_count: students.length, max_slots: 40 }}
                    onSettingsClick={() => setIsSettingsOpen(true)}
                    onActivityLogClick={() => setIsActivityLogOpen(true)}
                />

                <Tabs value={activeTab} onValueChange={handleTabChange} className="w-full">
                    <ClassroomNavigation activeTab={activeTab} onTabChange={handleTabChange} tabs={tabs} />
                    <TabsContent value="stream" className="mt-4">
                        <div className="grid items-start gap-4 lg:grid-cols-[minmax(0,1fr)_280px]">
                            <div className="min-w-0">
                                <StreamTab classData={classData} currentFaculty={current_faculty} students={students} classPosts={posts} />
                            </div>
                            <ClassroomUpcoming posts={posts} onViewClasswork={() => handleTabChange("classwork")} />
                        </div>
                    </TabsContent>
                    <TabsContent value="classwork" className="mt-4">
                        <ClassworkTab
                            classId={classData.id}
                            classCode={classData.subject_code}
                            classSection={classData.section}
                            currentFacultyId={current_faculty.id}
                            classPosts={posts}
                            students={students}
                        />
                    </TabsContent>
                    <TabsContent value="attendance" className="mt-4">
                        <AttendanceTab
                            classData={classData}
                            attendance={attendance}
                            scheduleOptions={scheduleOptions}
                            classSchedules={classData.schedules ?? []}
                            rooms={rooms}
                            defaultSessionDate={toLocalYmd(new Date())}
                            classStartDate={classStartDate}
                            focusStudentId={focusedAttendanceStudentId}
                            onClearFocus={() => setFocusedAttendanceStudentId(null)}
                        />
                    </TabsContent>
                    <TabsContent value="people" className="mt-4">
                        <PeopleTab
                            classData={classData}
                            teacher={teacher}
                            students={students}
                            onViewProfile={handleViewStudentInfo}
                            onTrackAttendance={(id) => handleTrackAttendance(id as number)}
                            onViewPublicInfo={() => toast.info("Public profile view coming soon")}
                        />
                    </TabsContent>
                    {classData.classification !== "shs" && (
                        <TabsContent value="grades" className="mt-4">
                            <GradeSheet
                                classId={classData.id}
                                students={students.map((student) => ({
                                    id: student.id,
                                    name: student.name,
                                    studentId: student.student_id,
                                    grades: {
                                        prelim: student.grades.prelim,
                                        midterm: student.grades.midterm,
                                        final: student.grades.final,
                                        average: student.grades.average,
                                        components: student.grades.components,
                                    },
                                }))}
                                autoAverageDefault={auto_average}
                                gradingPolicy={grading_policy}
                            />
                        </TabsContent>
                    )}
                </Tabs>
            </main>

            <ClassSettingsDialog open={isSettingsOpen} onOpenChange={setIsSettingsOpen} classData={classData} rooms={rooms} />
            <StudentProfileSheet
                open={studentInfoOpen}
                onOpenChange={setStudentInfoOpen}
                student={selectedStudent}
                studentInfo={studentInfo}
                loading={loadingStudentInfo}
                onTrackAttendance={handleTrackAttendance}
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
