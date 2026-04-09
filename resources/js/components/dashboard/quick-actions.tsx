import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { router } from "@inertiajs/react";
import { IconFilePlus, IconSettings, IconUpload, IconUsers } from "@tabler/icons-react";
import { useState } from "react";
import { GradeStudentsModal } from "./grade-students-modal";
import { TakeAttendanceModal } from "./take-attendance-modal";
import { UploadMaterialsModal } from "./upload-materials-modal";

interface ClassOption {
    id: number | string;
    subject_code: string;
    subject_title: string;
    section: string;
    classification?: string;
}

interface QuickActionsProps {
    classes?: ClassOption[];
}

export function QuickActions({ classes = [] }: QuickActionsProps) {
    const [gradeModalOpen, setGradeModalOpen] = useState(false);
    const [attendanceModalOpen, setAttendanceModalOpen] = useState(false);
    const [uploadModalOpen, setUploadModalOpen] = useState(false);

    const handleManageClasses = () => {
        router.visit("/classes");
    };

    return (
        <>
            <Card className="border-border/60 shadow-sm">
                <CardHeader className="pb-3">
                    <CardTitle>Quick Actions</CardTitle>
                    <CardDescription>Common tasks you perform often.</CardDescription>
                </CardHeader>
                <CardContent className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <Button
                        variant="outline"
                        className="hover:border-primary hover:bg-primary/5 h-24 flex-col items-center justify-center gap-2 transition-all"
                        onClick={() => setGradeModalOpen(true)}
                    >
                        <div className="bg-primary/10 text-primary flex size-10 items-center justify-center rounded-full">
                            <IconUsers className="size-5" />
                        </div>
                        <span className="font-medium">Grade Students</span>
                    </Button>
                    <Button
                        variant="outline"
                        className="hover:border-primary hover:bg-primary/5 h-24 flex-col items-center justify-center gap-2 transition-all"
                        onClick={() => setAttendanceModalOpen(true)}
                    >
                        <div className="flex size-10 items-center justify-center rounded-full bg-blue-500/10 text-blue-500">
                            <IconFilePlus className="size-5" />
                        </div>
                        <span className="font-medium">Take Attendance</span>
                    </Button>
                    <Button
                        variant="outline"
                        className="hover:border-primary hover:bg-primary/5 h-24 flex-col items-center justify-center gap-2 transition-all"
                        onClick={() => setUploadModalOpen(true)}
                    >
                        <div className="flex size-10 items-center justify-center rounded-full bg-green-500/10 text-green-500">
                            <IconUpload className="size-5" />
                        </div>
                        <span className="font-medium">Upload Materials</span>
                    </Button>
                    <Button
                        variant="outline"
                        className="hover:border-primary hover:bg-primary/5 h-24 flex-col items-center justify-center gap-2 transition-all"
                        onClick={handleManageClasses}
                    >
                        <div className="flex size-10 items-center justify-center rounded-full bg-orange-500/10 text-orange-500">
                            <IconSettings className="size-5" />
                        </div>
                        <span className="font-medium">Manage Classes</span>
                    </Button>
                </CardContent>
            </Card>

            {/* Modals */}
            <GradeStudentsModal open={gradeModalOpen} onOpenChange={setGradeModalOpen} classes={classes} />
            <TakeAttendanceModal open={attendanceModalOpen} onOpenChange={setAttendanceModalOpen} classes={classes} />
            <UploadMaterialsModal open={uploadModalOpen} onOpenChange={setUploadModalOpen} classes={classes} />
        </>
    );
}
