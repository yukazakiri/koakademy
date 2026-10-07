import { index as tuitionAdjustments } from "@/actions/App/Http/Controllers/AdministratorTuitionAdjustmentController";
import { Button } from "@/components/ui/button";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { router } from "@inertiajs/react";
import {
    ArrowRightLeft,
    BookOpen,
    ChevronDown,
    CreditCard,
    FileText,
    GraduationCap,
    RotateCcw,
    Settings,
    ShieldCheck,
    Trash2,
    UserCog,
    Zap,
} from "lucide-react";
import { toast } from "sonner";
import type { StudentDetail, StudentOptions } from "../types";

declare const route: (name: string, params?: Record<string, unknown> | string | number) => string;

interface StudentActionMenuProps {
    student: StudentDetail;
    options: StudentOptions;
    setActionDialog: (dialog: string | null) => void;
    setDeleteAction?: (action: "softDelete" | "forceDelete" | "restore" | null) => void;
}

export function StudentActionMenu({ student, options, setActionDialog, setDeleteAction }: StudentActionMenuProps) {
    return (
        <DropdownMenu>
            <DropdownMenuTrigger render={<Button variant="outline" className="gap-2" />}>
                <Settings className="h-4 w-4" />
                Actions
                <ChevronDown className="h-4 w-4 opacity-50" />
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-64">
                <DropdownMenuLabel>Student Actions</DropdownMenuLabel>
                <DropdownMenuSeparator />
                <DropdownMenuGroup>
                    <DropdownMenuLabel className="text-muted-foreground px-2 py-1 text-xs font-normal">Account & System</DropdownMenuLabel>
                    <DropdownMenuItem
                        onClick={() =>
                            router.post(
                                route("administrators.students.link-account", student.id),
                                {},
                                {
                                    preserveScroll: true,
                                    onSuccess: () => toast.success("Account linked"),
                                    onError: () => toast.error("Failed to link account"),
                                },
                            )
                        }
                    >
                        <UserCog className="mr-2 h-4 w-4" /> Link Account
                    </DropdownMenuItem>
                    <DropdownMenuItem onClick={() => setActionDialog("updateId")}>
                        <FileText className="mr-2 h-4 w-4" /> Update ID
                    </DropdownMenuItem>
                    {options.id_changes.length > 0 && (
                        <DropdownMenuItem onClick={() => setActionDialog("undoId")}>
                            <RotateCcw className="mr-2 h-4 w-4" /> Undo ID Change
                        </DropdownMenuItem>
                    )}
                </DropdownMenuGroup>
                <DropdownMenuSeparator />
                <DropdownMenuGroup>
                    <DropdownMenuLabel className="text-muted-foreground px-2 py-1 text-xs font-normal">Academic</DropdownMenuLabel>
                    <DropdownMenuItem onClick={() => setActionDialog("updateStatus")}>
                        <GraduationCap className="mr-2 h-4 w-4" /> Update Status
                    </DropdownMenuItem>
                    <DropdownMenuItem onClick={() => setActionDialog("changeCourse")}>
                        <BookOpen className="mr-2 h-4 w-4" /> Change Course
                    </DropdownMenuItem>
                    <DropdownMenuItem onClick={() => setActionDialog("retryEnrollment")}>
                        <ArrowRightLeft className="mr-2 h-4 w-4" /> Retry Enrollment
                    </DropdownMenuItem>
                </DropdownMenuGroup>
                <DropdownMenuSeparator />
                <DropdownMenuGroup>
                    <DropdownMenuLabel className="text-muted-foreground px-2 py-1 text-xs font-normal">Financial</DropdownMenuLabel>
                    <DropdownMenuItem onClick={() => router.visit(tuitionAdjustments.url({ query: { student: student.id } }))}>
                        <CreditCard className="mr-2 h-4 w-4" /> Manage Tuition
                    </DropdownMenuItem>
                </DropdownMenuGroup>
                <DropdownMenuSeparator />
                <DropdownMenuGroup>
                    <DropdownMenuLabel className="text-muted-foreground px-2 py-1 text-xs font-normal">Administrative</DropdownMenuLabel>
                    <DropdownMenuItem onClick={() => setActionDialog("clearance")}>
                        <ShieldCheck className="mr-2 h-4 w-4" /> Manage Clearance
                    </DropdownMenuItem>
                </DropdownMenuGroup>
                <DropdownMenuSeparator />
                <DropdownMenuGroup>
                    <DropdownMenuLabel className="text-muted-foreground px-2 py-1 text-xs font-normal">Record</DropdownMenuLabel>
                    {student.is_trashed ? (
                        <DropdownMenuItem onClick={() => setDeleteAction?.("restore")}>
                            <RotateCcw className="mr-2 h-4 w-4" /> Restore Student
                        </DropdownMenuItem>
                    ) : (
                        <DropdownMenuItem onClick={() => setDeleteAction?.("softDelete")}>
                            <Trash2 className="mr-2 h-4 w-4" /> Soft Delete
                        </DropdownMenuItem>
                    )}
                    <DropdownMenuItem onClick={() => setDeleteAction?.("forceDelete")} className="text-destructive focus:text-destructive">
                        <Zap className="mr-2 h-4 w-4" /> Force Delete
                    </DropdownMenuItem>
                </DropdownMenuGroup>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
