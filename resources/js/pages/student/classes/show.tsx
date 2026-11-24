import { ClassroomNavigation, ClassroomTab } from "@/components/class/classroom-navigation";
import { ClassroomUpcoming } from "@/components/class/classroom-upcoming";
import { EnhancedClassHeader } from "@/components/class/enhanced-class-header";
import { ClassworkTab } from "@/components/class/tabs/classwork-tab";
import { StudentAttendanceTab } from "@/components/class/tabs/student-attendance-tab";
import { StudentGradesTab } from "@/components/class/tabs/student-grades-tab";
import { StudentPeopleTab } from "@/components/class/tabs/student-people-tab";
import type { OnlineResourceCategoryPayload } from "@/components/class/tabs/resources-tab";
import { StudentResourcesTab } from "@/components/class/tabs/student-resources-tab";
import { StudentStreamTab } from "@/components/class/tabs/student-stream-tab";
import StudentLayout from "@/components/student/student-layout";
import { Button } from "@/components/ui/button";
import { Tabs, TabsContent } from "@/components/ui/tabs";
import { ClassPostEntry, ClassSettings, ScheduleEntry, TeacherEntry } from "@/types/class-detail-types";
import { User } from "@/types/user";
import { Head, Link } from "@inertiajs/react";
import { ArrowLeft, BookOpenCheck, GraduationCap, Link2, Radio, UserCheck, UsersRound } from "lucide-react";
import { useEffect, useMemo, useState } from "react";

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
        final_status?: "released" | "clearance_required" | "not_released";
    };
    my_attendance: {
        stats: { present: number; late: number; absent: number; excused: number };
        history: Array<{ id: number; date: string; status: string; remarks: string; topic: string }>;
    };
    classmates: Array<{ id: number; name: string; avatar: string }>;
    online_resources_module_enabled?: boolean;
    online_resources?: OnlineResourceCategoryPayload[];
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
    online_resources_module_enabled = true,
    online_resources = [],
}: StudentClassShowProps) {
    const onlineResourcesEnabled = online_resources_module_enabled && (classData.settings.enable_online_resources ?? true);

    const tabs = useMemo<ClassroomTab[]>(() => {
        const available: ClassroomTab[] = [
            { value: "stream", label: "Stream", icon: Radio },
            { value: "classwork", label: "Classwork", icon: BookOpenCheck },
        ];
        if (onlineResourcesEnabled) {
            available.push({ value: "resources", label: "Resources", icon: Link2, description: "Class reference links and guides" });
        }
        if (classData.settings.enable_attendance_tracking) {
            available.push({ value: "attendance", label: "My attendance", icon: UserCheck, description: "Review attendance history and totals" });
        }
        available.push({ value: "people", label: "Classmates", icon: UsersRound, description: "View your instructor and classmates" });
        if (classData.settings.enable_grade_visibility) {
            available.push({ value: "grades", label: "My grades", icon: GraduationCap, description: "Review published term grades" });
        }
        return available;
    }, [onlineResourcesEnabled, classData.settings.enable_attendance_tracking, classData.settings.enable_grade_visibility]);
    const [activeTab, setActiveTab] = useState("stream");

    useEffect(() => {
        const requested = new URLSearchParams(window.location.search).get("tab");
        if (requested && tabs.some((tab) => tab.value === requested && !tab.disabled)) setActiveTab(requested);
    }, [tabs]);

    const handleTabChange = (value: string) => {
        setActiveTab(value);
        const url = new URL(window.location.href);
        url.searchParams.set("tab", value);
        window.history.replaceState({}, "", url);
    };

    return (
        <StudentLayout user={{ name: user.name, email: user.email, avatar: user.avatar, role: user.role }}>
            <Head title={`${classData.subject_code} - ${classData.section}`} />

            <main className="mx-auto flex w-full max-w-7xl flex-col gap-4 px-3 py-4 pb-20 sm:px-5 md:gap-5 md:px-6 md:py-6">
                <Button asChild variant="ghost" size="sm" className="w-fit gap-2 px-1">
                    <Link href="/student/classes">
                        <ArrowLeft className="size-4" />
                        My classes
                    </Link>
                </Button>

                <EnhancedClassHeader
                    classData={classData}
                    teacher={teacher}
                    schedule={schedule}
                    enrollmentStats={{ current_count: classmates.length, max_slots: 0 }}
                    isStudent
                />

                <Tabs value={activeTab} onValueChange={handleTabChange} className="w-full">
                    <ClassroomNavigation activeTab={activeTab} onTabChange={handleTabChange} tabs={tabs} />

                    <TabsContent value="stream" className="mt-4">
                        <div className="grid items-start gap-4 lg:grid-cols-[minmax(0,1fr)_280px]">
                            <div className="min-w-0">
                                <StudentStreamTab classData={classData} teacher={teacher} classPosts={posts} />
                            </div>
                            <ClassroomUpcoming posts={posts} studentView onViewClasswork={() => handleTabChange("classwork")} />
                        </div>
                    </TabsContent>
                    <TabsContent value="classwork" className="mt-4">
                        <ClassworkTab classId={classData.id} classPosts={posts} isStudentView />
                    </TabsContent>
                    {onlineResourcesEnabled && (
                        <TabsContent value="resources" className="mt-4">
                            <StudentResourcesTab categories={online_resources} />
                        </TabsContent>
                    )}
                    {classData.settings.enable_attendance_tracking && (
                        <TabsContent value="attendance" className="mt-4">
                            <StudentAttendanceTab stats={my_attendance.stats} history={my_attendance.history} />
                        </TabsContent>
                    )}
                    <TabsContent value="people" className="mt-4">
                        <StudentPeopleTab teacher={teacher} classmates={classmates} />
                    </TabsContent>
                    {classData.settings.enable_grade_visibility && (
                        <TabsContent value="grades" className="mt-4">
                            <StudentGradesTab grades={my_grades} />
                        </TabsContent>
                    )}
                </Tabs>
            </main>
        </StudentLayout>
    );
}
