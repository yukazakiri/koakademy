import { Button } from "@/components/ui/button";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { router } from "@inertiajs/react";
import { IconExchange, IconLoader } from "@tabler/icons-react";
import axios from "axios";
import { useEffect, useState } from "react";
import { toast } from "sonner";

interface MoveStudentDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    classId: number | string;
    studentId: number | string | null;
    studentName?: string;
}

interface Section {
    id: number;
    section: string;
    faculty_name: string;
    schedule: string;
}

export function MoveStudentDialog({ open, onOpenChange, classId, studentId, studentName }: MoveStudentDialogProps) {
    const [sections, setSections] = useState<Section[]>([]);
    const [loading, setLoading] = useState(false);
    const [targetClassId, setTargetClassId] = useState<string>("");
    const [submitting, setSubmitting] = useState(false);

    useEffect(() => {
        if (open && classId) {
            const fetchSections = async () => {
                setLoading(true);
                try {
                    const response = await axios.get(`/faculty/classes/${classId}/related-sections`);
                    setSections(response.data.sections);
                } catch (error) {
                    console.error("Failed to fetch sections", error);
                    toast.error("Failed to load available sections.");
                } finally {
                    setLoading(false);
                }
            };
            fetchSections();
        }
    }, [open, classId]);

    const handleSubmit = () => {
        if (!studentId || !targetClassId) return;

        setSubmitting(true);
        router.post(
            `/faculty/classes/${classId}/students/${studentId}/move`,
            { target_class_id: targetClassId },
            {
                onSuccess: () => {
                    toast.success("Transfer request sent successfully");
                    onOpenChange(false);
                },
                onError: () => {
                    toast.error("Failed to send transfer request");
                },
                onFinish: () => {
                    setSubmitting(false);
                },
            },
        );
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-[425px]">
                <DialogHeader>
                    <DialogTitle>Request Student Transfer</DialogTitle>
                    <DialogDescription>
                        Request to transfer <strong>{studentName}</strong> to another section. The target faculty will need to accept this request.
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-4 py-4">
                    {loading ? (
                        <div className="flex justify-center p-4">
                            <IconLoader className="text-muted-foreground animate-spin" />
                        </div>
                    ) : sections.length === 0 ? (
                        <p className="text-muted-foreground text-center text-sm">No other sections found for this subject.</p>
                    ) : (
                        <div className="space-y-2">
                            <label className="text-sm font-medium">Select Target Section</label>
                            <Select value={targetClassId} onValueChange={setTargetClassId}>
                                <SelectTrigger>
                                    <SelectValue placeholder="Select a section" />
                                </SelectTrigger>
                                <SelectContent>
                                    {sections.map((section) => (
                                        <SelectItem key={section.id} value={String(section.id)}>
                                            <div className="flex flex-col items-start text-left">
                                                <span className="font-semibold">{section.section}</span>
                                                <span className="text-muted-foreground text-xs">
                                                    {section.faculty_name} • {section.schedule}
                                                </span>
                                            </div>
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    )}
                </div>

                <DialogFooter>
                    <Button variant="outline" onClick={() => onOpenChange(false)}>
                        Cancel
                    </Button>
                    <Button onClick={handleSubmit} disabled={submitting || !targetClassId} className="gap-2">
                        {submitting ? <IconLoader className="h-4 w-4 animate-spin" /> : <IconExchange className="h-4 w-4" />}
                        Send Request
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
