import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Skeleton } from "@/components/ui/skeleton";
import { router } from "@inertiajs/react";
import { IconCalendar } from "@tabler/icons-react";
import axios from "axios";
import { useState } from "react";
import { toast } from "sonner";

interface ClassOption {
    id: number | string;
    subject_code: string;
    subject_title: string;
    section: string;
}

interface ScheduleOption {
    id: number;
    day_of_week: string;
    start_time: string;
    end_time: string;
    room?: string;
}

interface AttendanceSession {
    id: number;
    session_date: string;
    topic: string | null;
    is_locked: boolean;
    records: {
        id: number;
        status: string;
        student: {
            name: string;
            student_number: string;
        };
    }[];
}

interface TakeAttendanceModalProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    classes: ClassOption[];
}

export function TakeAttendanceModal({ open, onOpenChange, classes }: TakeAttendanceModalProps) {
    const [selectedClassId, setSelectedClassId] = useState<string>("");
    const [isLoading, setIsLoading] = useState(false);
    const [sessions, setSessions] = useState<AttendanceSession[]>([]);
    const [schedules, setSchedules] = useState<ScheduleOption[]>([]);
    const [selectedClass, setSelectedClass] = useState<ClassOption | null>(null);

    // New session form state
    const [showNewSessionForm, setShowNewSessionForm] = useState(false);
    const [newSessionDate, setNewSessionDate] = useState(new Date().toISOString().split("T")[0]);
    const [newSessionTopic, setNewSessionTopic] = useState("");
    const [newSessionScheduleId, setNewSessionScheduleId] = useState<string>("");
    const [isCreatingSession, setIsCreatingSession] = useState(false);

    const handleClassChange = async (classId: string) => {
        setSelectedClassId(classId);
        setIsLoading(true);
        setSessions([]);
        setSchedules([]);
        setShowNewSessionForm(false);

        const classItem = classes.find((c) => c.id.toString() === classId);
        setSelectedClass(classItem || null);

        try {
            const response = await axios.get(`/faculty/classes/${classId}/quick-action-data`);
            const data = response.data;

            setSessions(data.attendance?.sessions || []);
            setSchedules(data.schedule || []);

            if (data.schedule?.length > 0) {
                setNewSessionScheduleId(data.schedule[0].id.toString());
            }
        } catch (error) {
            console.error("Failed to fetch class data:", error);
            toast.error("Failed to load class data");
        } finally {
            setIsLoading(false);
        }
    };

    const handleCreateSession = async () => {
        if (!selectedClassId) return;

        setIsCreatingSession(true);

        router.post(
            `/faculty/classes/${selectedClassId}/attendance/sessions`,
            {
                session_date: newSessionDate,
                topic: newSessionTopic || null,
                schedule_id: newSessionScheduleId || null,
            },
            {
                preserveState: false,
                onSuccess: () => {
                    toast.success("Attendance session created!");
                    setShowNewSessionForm(false);
                    setNewSessionTopic("");
                    // Refresh class data
                    handleClassChange(selectedClassId);
                },
                onError: (errors) => {
                    console.error("Create session errors:", errors);
                    toast.error("Failed to create attendance session");
                },
                onFinish: () => {
                    setIsCreatingSession(false);
                },
            },
        );
    };

    const handleViewClass = () => {
        if (selectedClassId) {
            router.visit(`/faculty/classes/${selectedClassId}`);
            onOpenChange(false);
        }
    };

    const handleClose = () => {
        setSelectedClassId("");
        setSessions([]);
        setSchedules([]);
        setSelectedClass(null);
        setShowNewSessionForm(false);
        onOpenChange(false);
    };

    return (
        <Dialog open={open} onOpenChange={handleClose}>
            <DialogContent className="max-h-[90vh] max-w-3xl overflow-y-auto">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        Take Attendance
                        {selectedClass && (
                            <Badge variant="secondary" className="ml-2">
                                {selectedClass.subject_code} - {selectedClass.section}
                            </Badge>
                        )}
                    </DialogTitle>
                    <DialogDescription>Select a class to manage attendance sessions.</DialogDescription>
                </DialogHeader>

                <div className="space-y-6 py-4">
                    {/* Class Selector */}
                    <div className="space-y-2">
                        <label className="text-sm font-medium">Select Class</label>
                        <Select value={selectedClassId} onValueChange={handleClassChange}>
                            <SelectTrigger>
                                <SelectValue placeholder="Choose a class..." />
                            </SelectTrigger>
                            <SelectContent>
                                {classes.map((classItem) => (
                                    <SelectItem key={classItem.id} value={classItem.id.toString()}>
                                        {classItem.subject_code} - {classItem.section} ({classItem.subject_title})
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    {/* Loading State */}
                    {isLoading && (
                        <div className="space-y-4">
                            <Skeleton className="h-10 w-full" />
                            <Skeleton className="h-32 w-full" />
                        </div>
                    )}

                    {/* Sessions List */}
                    {!isLoading && selectedClassId && (
                        <div className="space-y-4">
                            <div className="flex items-center justify-between">
                                <h3 className="font-medium">Recent Sessions</h3>
                                <div className="flex gap-2">
                                    <Button variant="outline" size="sm" onClick={() => setShowNewSessionForm(!showNewSessionForm)}>
                                        {showNewSessionForm ? "Cancel" : "New Session"}
                                    </Button>
                                    <Button variant="secondary" size="sm" onClick={handleViewClass}>
                                        Open Full View
                                    </Button>
                                </div>
                            </div>

                            {/* New Session Form */}
                            {showNewSessionForm && (
                                <Card className="border-primary/30 bg-primary/5">
                                    <CardContent className="space-y-4 pt-4">
                                        <div className="grid grid-cols-2 gap-4">
                                            <div className="space-y-2">
                                                <Label>Date</Label>
                                                <Input type="date" value={newSessionDate} onChange={(e) => setNewSessionDate(e.target.value)} />
                                            </div>
                                            <div className="space-y-2">
                                                <Label>Schedule</Label>
                                                <Select value={newSessionScheduleId} onValueChange={setNewSessionScheduleId}>
                                                    <SelectTrigger>
                                                        <SelectValue placeholder="Select schedule..." />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        {schedules.map((schedule) => (
                                                            <SelectItem key={schedule.id} value={schedule.id.toString()}>
                                                                {schedule.day_of_week} {schedule.start_time} - {schedule.end_time}
                                                            </SelectItem>
                                                        ))}
                                                    </SelectContent>
                                                </Select>
                                            </div>
                                        </div>
                                        <div className="space-y-2">
                                            <Label>Topic (optional)</Label>
                                            <Input
                                                placeholder="e.g., Chapter 5 Review"
                                                value={newSessionTopic}
                                                onChange={(e) => setNewSessionTopic(e.target.value)}
                                            />
                                        </div>
                                        <Button onClick={handleCreateSession} disabled={isCreatingSession} className="w-full">
                                            {isCreatingSession ? "Creating..." : "Create Session"}
                                        </Button>
                                    </CardContent>
                                </Card>
                            )}

                            {sessions.length === 0 ? (
                                <div className="rounded-lg border border-dashed p-8 text-center">
                                    <IconCalendar className="text-muted-foreground mx-auto mb-2 size-8" />
                                    <p className="text-muted-foreground">No attendance sessions yet.</p>
                                    <p className="text-muted-foreground text-sm">Create your first session to start tracking attendance.</p>
                                </div>
                            ) : (
                                <div className="space-y-2">
                                    {sessions.slice(0, 5).map((session) => {
                                        const presentCount = session.records.filter((r) => r.status === "present").length;
                                        const absentCount = session.records.filter((r) => r.status === "absent").length;

                                        return (
                                            <Card key={session.id} className="hover:border-primary/50 transition-colors">
                                                <CardContent className="flex items-center justify-between py-3">
                                                    <div className="flex items-center gap-3">
                                                        <div className="bg-muted flex size-10 items-center justify-center rounded-lg text-center">
                                                            <div>
                                                                <span className="block text-xs font-medium">
                                                                    {new Date(session.session_date).toLocaleDateString("en-US", { month: "short" })}
                                                                </span>
                                                                <span className="text-sm font-bold">{new Date(session.session_date).getDate()}</span>
                                                            </div>
                                                        </div>
                                                        <div>
                                                            <p className="font-medium">{session.topic || "Regular Session"}</p>
                                                            <p className="text-muted-foreground text-xs">
                                                                {presentCount} Present • {absentCount} Absent
                                                            </p>
                                                        </div>
                                                    </div>
                                                    {session.is_locked && (
                                                        <Badge variant="secondary" className="text-xs">
                                                            Locked
                                                        </Badge>
                                                    )}
                                                </CardContent>
                                            </Card>
                                        );
                                    })}
                                    {sessions.length > 5 && (
                                        <p className="text-muted-foreground text-center text-sm">+{sessions.length - 5} more sessions</p>
                                    )}
                                </div>
                            )}
                        </div>
                    )}

                    {/* Initial State */}
                    {!selectedClassId && !isLoading && (
                        <div className="rounded-lg border border-dashed p-8 text-center">
                            <p className="text-muted-foreground">Select a class above to manage attendance.</p>
                        </div>
                    )}
                </div>
            </DialogContent>
        </Dialog>
    );
}
