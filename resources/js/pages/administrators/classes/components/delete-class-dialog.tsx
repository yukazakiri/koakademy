import { Button } from "@/components/ui/button";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Archive } from "lucide-react";
import type { ClassRow } from "../columns";

interface DeleteClassDialogProps {
    pendingDelete: ClassRow | null;
    onConfirm: () => void;
    onCancel: () => void;
    processing?: boolean;
}

export function DeleteClassDialog({ pendingDelete, onConfirm, onCancel, processing = false }: DeleteClassDialogProps) {
    return (
        <Dialog open={pendingDelete !== null} onOpenChange={(open) => !open && onCancel()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Archive this class?</DialogTitle>
                    <DialogDescription>
                        <span className="text-foreground font-medium">{pendingDelete?.record_title}</span> will be removed from the live list,
                        together with its schedule blocks and enrolments. Nothing is lost: the class can be restored from the archived view,
                        and it brings its schedule and enrolments back with it.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="outline" onClick={onCancel} disabled={processing}>
                        Cancel
                    </Button>
                    <Button variant="destructive" onClick={onConfirm} disabled={processing}>
                        <Archive className="mr-2 h-4 w-4" />
                        Archive class
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
