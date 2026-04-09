import { cn } from "@/lib/utils";
import { router } from "@inertiajs/react";
import { IconArrowRight, IconClipboardCheck, IconPlus, IconSettings, IconUpload, IconUsers } from "@tabler/icons-react";
import { motion } from "framer-motion";
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

interface QuickActionsPanelProps {
    classes?: ClassOption[];
}

const actions = [
    {
        id: "grade",
        label: "Grade Students",
        description: "Enter student grades",
        icon: IconUsers,
        color: "from-violet-500 to-purple-600",
        hoverBg: "group-hover:bg-violet-500/10",
        shadowColor: "shadow-violet-500/25",
    },
    {
        id: "attendance",
        label: "Take Attendance",
        description: "Mark student presence",
        icon: IconClipboardCheck,
        color: "from-blue-500 to-cyan-600",
        hoverBg: "group-hover:bg-blue-500/10",
        shadowColor: "shadow-blue-500/25",
    },
    {
        id: "upload",
        label: "Upload Materials",
        description: "Share learning resources",
        icon: IconUpload,
        color: "from-emerald-500 to-teal-600",
        hoverBg: "group-hover:bg-emerald-500/10",
        shadowColor: "shadow-emerald-500/25",
    },
    {
        id: "manage",
        label: "Manage Classes",
        description: "View all your classes",
        icon: IconSettings,
        color: "from-amber-500 to-orange-600",
        hoverBg: "group-hover:bg-amber-500/10",
        shadowColor: "shadow-amber-500/25",
    },
];

export function QuickActionsPanel({ classes = [] }: QuickActionsPanelProps) {
    const [gradeModalOpen, setGradeModalOpen] = useState(false);
    const [attendanceModalOpen, setAttendanceModalOpen] = useState(false);
    const [uploadModalOpen, setUploadModalOpen] = useState(false);

    const handleAction = (actionId: string) => {
        switch (actionId) {
            case "grade":
                setGradeModalOpen(true);
                break;
            case "attendance":
                setAttendanceModalOpen(true);
                break;
            case "upload":
                setUploadModalOpen(true);
                break;
            case "manage":
                router.visit("/faculty/classes");
                break;
        }
    };

    return (
        <>
            <motion.div
                initial={{ opacity: 0, y: 20 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ delay: 0.2 }}
                className="border-border/60 bg-card/80 rounded-2xl border p-5 backdrop-blur-sm"
            >
                <div className="mb-4 flex items-center justify-between">
                    <div>
                        <h2 className="text-foreground text-lg font-semibold">Quick Actions</h2>
                        <p className="text-muted-foreground text-sm">Get things done faster</p>
                    </div>
                    <motion.button
                        whileHover={{ scale: 1.05 }}
                        whileTap={{ scale: 0.95 }}
                        onClick={() => router.visit("/faculty/classes/create")}
                        className="bg-primary text-primary-foreground hover:bg-primary/90 flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-medium shadow-sm transition-colors"
                    >
                        <IconPlus className="h-3.5 w-3.5" />
                        New Class
                    </motion.button>
                </div>

                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    {actions.map((action, index) => {
                        const Icon = action.icon;
                        return (
                            <motion.button
                                key={action.id}
                                initial={{ opacity: 0, y: 20 }}
                                animate={{ opacity: 1, y: 0 }}
                                transition={{ delay: index * 0.1 }}
                                whileHover={{ scale: 1.02, y: -2 }}
                                whileTap={{ scale: 0.98 }}
                                onClick={() => handleAction(action.id)}
                                className={cn(
                                    "group border-border/60 relative flex flex-col items-center gap-3 rounded-xl border p-4 text-left transition-all duration-300",
                                    action.hoverBg,
                                    "hover:border-transparent hover:shadow-lg",
                                    `hover:${action.shadowColor}`,
                                )}
                            >
                                <div
                                    className="absolute inset-0 rounded-xl bg-gradient-to-br opacity-0 transition-opacity duration-300 group-hover:opacity-5"
                                    style={{ backgroundImage: `linear-gradient(to bottom right, var(--tw-gradient-stops))` }}
                                />

                                <motion.div
                                    whileHover={{ rotate: [0, -10, 10, 0], scale: 1.1 }}
                                    transition={{ duration: 0.4 }}
                                    className={cn("flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-br shadow-lg", action.color)}
                                >
                                    <Icon className="h-6 w-6 text-white" />
                                </motion.div>

                                <div className="text-center">
                                    <p className="text-foreground group-hover:text-foreground/90 font-medium">{action.label}</p>
                                    <p className="text-muted-foreground text-xs">{action.description}</p>
                                </div>

                                <motion.div initial={{ opacity: 0, x: -5 }} whileHover={{ opacity: 1, x: 0 }} className="absolute right-2 bottom-2">
                                    <IconArrowRight className="text-muted-foreground h-4 w-4" />
                                </motion.div>
                            </motion.button>
                        );
                    })}
                </div>
            </motion.div>

            <GradeStudentsModal open={gradeModalOpen} onOpenChange={setGradeModalOpen} classes={classes} />
            <TakeAttendanceModal open={attendanceModalOpen} onOpenChange={setAttendanceModalOpen} classes={classes} />
            <UploadMaterialsModal open={uploadModalOpen} onOpenChange={setUploadModalOpen} classes={classes} />
        </>
    );
}
