import AdminLayout from "@/components/administrators/admin-layout";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Head } from "@inertiajs/react";
import { CalendarDays, FileSpreadsheet, Settings2, Users } from "lucide-react";
import { useState } from "react";
import { MoveStudentDialog } from "./record/move-student-dialog";
import { RecordHeader } from "./record/record-header";
import { RecordSettings } from "./record/record-settings";
import { ScheduleOverview } from "./record/schedule-overview";
import { StudentRecords } from "./record/student-records";
import { TimetableEditor } from "./record/timetable-editor";
import type { ClassShowCapabilities, ClassShowProps, EnrollmentRow } from "./record/types";

const DEFAULT_CAPABILITIES: ClassShowCapabilities = { update: false, delete: false, restore: false, force_delete: false };

export default function AdministratorClassShow({
    user,
    class: classItem,
    capabilities: rawCapabilities,
    enrollments,
    raw_schedules,
    room_schedules,
    rooms,
    transferable_classes,
}: ClassShowProps) {
    const capabilities = rawCapabilities ?? DEFAULT_CAPABILITIES;
    const [scheduleDialogOpen, setScheduleDialogOpen] = useState(false);
    const [studentToMove, setStudentToMove] = useState<EnrollmentRow | null>(null);

    return (
        <AdminLayout user={user} title="Class record">
            <Head title={`Class • ${classItem.record_title}`} />
            <div className="text-foreground mx-auto flex w-full max-w-[1600px] min-w-0 flex-col gap-6">
                <RecordHeader classItem={classItem} capabilities={capabilities} />
                <Tabs defaultValue="schedule" className="min-w-0 gap-6" key={classItem.id}>
                    <div className="min-w-0 overflow-x-auto border-b">
                        <TabsList variant="underline" aria-label="Class record sections" className="w-max min-w-full justify-start gap-4 p-0">
                            <TabsTrigger value="schedule" className="data-active:text-primary h-12 grow-0 gap-2 rounded-none px-3 text-sm">
                                <CalendarDays className="size-4" aria-hidden="true" />
                                Schedule
                            </TabsTrigger>
                            <TabsTrigger value="roster" className="data-active:text-primary h-12 grow-0 gap-2 rounded-none px-3 text-sm">
                                <Users className="size-4" aria-hidden="true" />
                                Roster
                                <span className="bg-muted text-muted-foreground rounded-md px-2 text-xs tabular-nums">{enrollments.length}</span>
                            </TabsTrigger>
                            <TabsTrigger value="assessments" className="data-active:text-primary h-12 grow-0 gap-2 rounded-none px-3 text-sm">
                                <FileSpreadsheet className="size-4" aria-hidden="true" />
                                Assessments
                            </TabsTrigger>
                            <TabsTrigger value="settings" className="data-active:text-primary h-12 grow-0 gap-2 rounded-none px-3 text-sm">
                                <Settings2 className="size-4" aria-hidden="true" />
                                Settings
                            </TabsTrigger>
                        </TabsList>
                    </div>
                    <TabsContent value="schedule" className="min-w-0">
                        <ScheduleOverview classItem={classItem} canUpdate={capabilities.update} onManage={() => setScheduleDialogOpen(true)} />
                    </TabsContent>
                    <TabsContent value="roster" className="min-w-0">
                        <StudentRecords classItem={classItem} enrollments={enrollments} onMove={setStudentToMove} />
                    </TabsContent>
                    <TabsContent value="assessments" className="min-w-0">
                        <StudentRecords mode="assessments" classItem={classItem} enrollments={enrollments} onMove={setStudentToMove} />
                    </TabsContent>
                    <TabsContent value="settings" className="min-w-0">
                        <RecordSettings classItem={classItem} canUpdate={capabilities.update} />
                    </TabsContent>
                </Tabs>
                {capabilities.update && scheduleDialogOpen && (
                    <TimetableEditor
                        classItem={classItem}
                        initialSchedules={raw_schedules}
                        roomSchedules={room_schedules}
                        rooms={rooms}
                        open={scheduleDialogOpen}
                        onOpenChange={setScheduleDialogOpen}
                    />
                )}
                {studentToMove && (
                    <MoveStudentDialog
                        key={studentToMove.id}
                        student={studentToMove}
                        transferableClasses={transferable_classes || []}
                        currentClassId={classItem.id}
                        onClose={() => setStudentToMove(null)}
                    />
                )}
            </div>
        </AdminLayout>
    );
}
