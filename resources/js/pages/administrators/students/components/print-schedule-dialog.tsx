import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Label } from "@/components/ui/label";
import { Separator } from "@/components/ui/separator";
import { useGradingConfig } from "@/hooks/use-grading-config";
import { cn } from "@/lib/utils";
import { usePage } from "@inertiajs/react";
import { AlertCircle, BookOpen, CalendarIcon, CheckCircle, FileText, ListIcon, Printer } from "lucide-react";
import { useEffect, useState } from "react";
import { toast } from "sonner";
import type { Branding, PrintOption, StudentDetail } from "../types";
import { generatePrintDocumentHtml } from "./print-schedule-html";

export { generatePrintDocumentHtml };

export function PrintScheduleDialog({
    open,
    onOpenChange,
    student,
    initialOption = "both",
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    student: StudentDetail;
    initialOption?: PrintOption;
}) {
    const { props } = usePage<{ branding?: Branding }>();
    const orgName = props.branding?.organizationName || "KoAkademy";
    const gradingConfig = useGradingConfig();
    const [printOption, setPrintOption] = useState<PrintOption>(initialOption);

    useEffect(() => {
        if (open) {
            setPrintOption(initialOption);
        }
    }, [initialOption, open]);

    const handlePrint = () => {
        const isChecklist = printOption === "checklist_completed" || printOption === "checklist_full";
        const showTranscript = printOption === "transcript";

        if (!isChecklist && !showTranscript && !student.current_enrolled_classes) {
            toast.error("No schedule data available.");
            return;
        }

        const printWindow = window.open("", "_blank", "width=1000,height=800");
        if (!printWindow) {
            toast.error("Please allow popups to print.");
            return;
        }

        printWindow.opener = null;

        try {
            const html = generatePrintDocumentHtml({
                student,
                orgName,
                gradingConfig,
                printOption,
            });

            printWindow.document.write(html);
            printWindow.document.close();
            onOpenChange(false);
        } catch (e) {
            console.error("Print generation failed:", e);
            toast.error("Failed to generate print view.");
            if (printWindow) printWindow.close();
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-[500px]">
                <DialogHeader>
                    <div className="flex items-center gap-3">
                        <div className="bg-primary/10 flex h-10 w-10 items-center justify-center rounded-full">
                            <Printer className="text-primary h-5 w-5" />
                        </div>
                        <div>
                            <DialogTitle>Print Student Record</DialogTitle>
                            <DialogDescription className="mt-1">Choose what to include in the printed document.</DialogDescription>
                        </div>
                    </div>
                </DialogHeader>

                <div className="space-y-4 py-4">
                    {/* Current Enrollment Section */}
                    <div className="space-y-3">
                        <Label className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">Current Enrollment</Label>
                        <div className="space-y-2">
                            <div
                                className={cn(
                                    "relative flex cursor-pointer items-center gap-3 rounded-lg border p-3 transition-all",
                                    printOption === "subjects"
                                        ? "border-primary bg-primary/5 ring-primary ring-1"
                                        : "hover:border-primary/50 hover:bg-accent",
                                )}
                                onClick={() => setPrintOption("subjects")}
                            >
                                <div
                                    className={cn(
                                        "flex h-4 w-4 flex-shrink-0 items-center justify-center rounded-full border",
                                        printOption === "subjects" ? "border-primary bg-primary" : "border-muted-foreground",
                                    )}
                                >
                                    {printOption === "subjects" && <div className="h-2 w-2 rounded-full bg-white" />}
                                </div>
                                <div className="min-w-0 flex-1">
                                    <div className="flex items-center gap-2">
                                        <ListIcon className="text-muted-foreground h-4 w-4 flex-shrink-0" />
                                        <span className="text-sm font-medium">Enrolled Subjects</span>
                                    </div>
                                    <p className="text-muted-foreground mt-0.5 text-xs">Current semester subjects with schedule</p>
                                </div>
                                <Badge variant="outline" className="flex-shrink-0 text-[10px]">
                                    1 pg
                                </Badge>
                            </div>

                            <div
                                className={cn(
                                    "relative flex cursor-pointer items-center gap-3 rounded-lg border p-3 transition-all",
                                    printOption === "schedule"
                                        ? "border-primary bg-primary/5 ring-primary ring-1"
                                        : "hover:border-primary/50 hover:bg-accent",
                                )}
                                onClick={() => setPrintOption("schedule")}
                            >
                                <div
                                    className={cn(
                                        "flex h-4 w-4 flex-shrink-0 items-center justify-center rounded-full border",
                                        printOption === "schedule" ? "border-primary bg-primary" : "border-muted-foreground",
                                    )}
                                >
                                    {printOption === "schedule" && <div className="h-2 w-2 rounded-full bg-white" />}
                                </div>
                                <div className="min-w-0 flex-1">
                                    <div className="flex items-center gap-2">
                                        <CalendarIcon className="text-muted-foreground h-4 w-4 flex-shrink-0" />
                                        <span className="text-sm font-medium">Weekly Schedule</span>
                                    </div>
                                    <p className="text-muted-foreground mt-0.5 text-xs">Visual class schedule grid</p>
                                </div>
                                <Badge variant="outline" className="flex-shrink-0 text-[10px]">
                                    1 pg
                                </Badge>
                            </div>

                            <div
                                className={cn(
                                    "relative flex cursor-pointer items-center gap-3 rounded-lg border p-3 transition-all",
                                    printOption === "both"
                                        ? "border-primary bg-primary/5 ring-primary ring-1"
                                        : "hover:border-primary/50 hover:bg-accent",
                                )}
                                onClick={() => setPrintOption("both")}
                            >
                                <div
                                    className={cn(
                                        "flex h-4 w-4 flex-shrink-0 items-center justify-center rounded-full border",
                                        printOption === "both" ? "border-primary bg-primary" : "border-muted-foreground",
                                    )}
                                >
                                    {printOption === "both" && <div className="h-2 w-2 rounded-full bg-white" />}
                                </div>
                                <div className="min-w-0 flex-1">
                                    <div className="flex items-center gap-2">
                                        <FileText className="text-muted-foreground h-4 w-4 flex-shrink-0" />
                                        <span className="text-sm font-medium">Complete Enrollment</span>
                                    </div>
                                    <p className="text-muted-foreground mt-0.5 text-xs">Subjects table + schedule grid</p>
                                </div>
                                <Badge variant="secondary" className="flex-shrink-0 text-[10px]">
                                    2 pg
                                </Badge>
                            </div>
                        </div>
                    </div>

                    <Separator />

                    {/* Checklist Section */}
                    <div className="space-y-3">
                        <Label className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">Curriculum Checklist</Label>
                        <div className="space-y-2">
                            <div
                                className={cn(
                                    "relative flex cursor-pointer items-center gap-3 rounded-lg border p-3 transition-all",
                                    printOption === "checklist_completed"
                                        ? "border-primary bg-primary/5 ring-primary ring-1"
                                        : "hover:border-primary/50 hover:bg-accent",
                                )}
                                onClick={() => setPrintOption("checklist_completed")}
                            >
                                <div
                                    className={cn(
                                        "flex h-4 w-4 flex-shrink-0 items-center justify-center rounded-full border",
                                        printOption === "checklist_completed" ? "border-primary bg-primary" : "border-muted-foreground",
                                    )}
                                >
                                    {printOption === "checklist_completed" && <div className="h-2 w-2 rounded-full bg-white" />}
                                </div>
                                <div className="min-w-0 flex-1">
                                    <div className="flex items-center gap-2">
                                        <CheckCircle className="h-4 w-4 flex-shrink-0 text-green-600" />
                                        <span className="text-sm font-medium">Academic Transcript</span>
                                    </div>
                                    <p className="text-muted-foreground mt-0.5 text-xs">Completed subjects with grades only</p>
                                </div>
                                <Badge variant="outline" className="flex-shrink-0 border-green-200 bg-green-50 text-[10px] text-green-700">
                                    Passed
                                </Badge>
                            </div>

                            <div
                                className={cn(
                                    "relative flex cursor-pointer items-center gap-3 rounded-lg border p-3 transition-all",
                                    printOption === "checklist_full"
                                        ? "border-primary bg-primary/5 ring-primary ring-1"
                                        : "hover:border-primary/50 hover:bg-accent",
                                )}
                                onClick={() => setPrintOption("checklist_full")}
                            >
                                <div
                                    className={cn(
                                        "flex h-4 w-4 flex-shrink-0 items-center justify-center rounded-full border",
                                        printOption === "checklist_full" ? "border-primary bg-primary" : "border-muted-foreground",
                                    )}
                                >
                                    {printOption === "checklist_full" && <div className="h-2 w-2 rounded-full bg-white" />}
                                </div>
                                <div className="min-w-0 flex-1">
                                    <div className="flex items-center gap-2">
                                        <BookOpen className="text-muted-foreground h-4 w-4 flex-shrink-0" />
                                        <span className="text-sm font-medium">Full Curriculum Checklist</span>
                                    </div>
                                    <p className="text-muted-foreground mt-0.5 text-xs">All subjects with completion status</p>
                                </div>
                                <Badge variant="secondary" className="flex-shrink-0 text-[10px]">
                                    All
                                </Badge>
                            </div>

                            <div
                                className={cn(
                                    "relative flex cursor-pointer items-center gap-3 rounded-lg border p-3 transition-all",
                                    printOption === "transcript"
                                        ? "border-primary bg-primary/5 ring-primary ring-1"
                                        : "hover:border-primary/50 hover:bg-accent",
                                )}
                                onClick={() => setPrintOption("transcript")}
                            >
                                <div
                                    className={cn(
                                        "flex h-4 w-4 flex-shrink-0 items-center justify-center rounded-full border",
                                        printOption === "transcript" ? "border-primary bg-primary" : "border-muted-foreground",
                                    )}
                                >
                                    {printOption === "transcript" && <div className="h-2 w-2 rounded-full bg-white" />}
                                </div>
                                <div className="min-w-0 flex-1">
                                    <div className="flex items-center gap-2">
                                        <FileText className="text-muted-foreground h-4 w-4 flex-shrink-0" />
                                        <span className="text-sm font-medium">Official Transcript of Records</span>
                                    </div>
                                    <p className="text-muted-foreground mt-0.5 text-xs">
                                        Formal portrait TOR grouped by year level, school year, and semester.
                                    </p>
                                </div>
                                <Badge variant="outline" className="flex-shrink-0 text-[10px]">
                                    TOR
                                </Badge>
                            </div>
                        </div>
                    </div>

                    <div className="bg-muted/30 text-muted-foreground rounded-lg border p-3 text-xs">
                        <div className="flex items-start gap-2">
                            <AlertCircle className="mt-0.5 h-4 w-4 flex-shrink-0" />
                            <div>
                                <p className="text-foreground mb-1 font-medium">Print Information</p>
                                <ul className="space-y-0.5">
                                    <li>Documents are formatted for official use</li>
                                    <li>Content auto-scales to fit letter size</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <DialogFooter>
                    <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                        Cancel
                    </Button>
                    <Button type="button" onClick={handlePrint} className="gap-2">
                        <Printer className="h-4 w-4" />
                        Print Document
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
