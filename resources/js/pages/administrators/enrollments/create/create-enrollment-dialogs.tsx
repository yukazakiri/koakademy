import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from "@/components/ui/alert-dialog";
import { Button } from "@/components/ui/button";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Loader2 } from "lucide-react";
import type { PendingOverloadConfirmation } from "./types";

interface CreateEnrollmentDialogsProps {
    discountDialogOpen: boolean;
    onDiscountDialogOpenChange: (open: boolean) => void;
    discountName: string;
    onDiscountNameChange: (value: string) => void;
    discountPercentage: string;
    onDiscountPercentageChange: (value: string) => void;
    discountErrors: { name?: string; percentage?: string };
    creatingDiscount: boolean;
    onCreateDiscount: (event: React.FormEvent<HTMLFormElement>) => void;
    pendingOverloadConfirmation: PendingOverloadConfirmation | null;
    onDismissOverload: () => void;
    onConfirmOverload: () => void;
    submitting: boolean;
}

export function CreateEnrollmentDialogs({
    discountDialogOpen,
    onDiscountDialogOpenChange,
    discountName,
    onDiscountNameChange,
    discountPercentage,
    onDiscountPercentageChange,
    discountErrors,
    creatingDiscount,
    onCreateDiscount,
    pendingOverloadConfirmation,
    onDismissOverload,
    onConfirmOverload,
    submitting,
}: CreateEnrollmentDialogsProps) {
    return (
        <>
            <Dialog open={discountDialogOpen} onOpenChange={onDiscountDialogOpenChange}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Create Discount</DialogTitle>
                        <DialogDescription>Add a reusable discount preset for future enrollments.</DialogDescription>
                    </DialogHeader>
                    <form className="space-y-4" onSubmit={onCreateDiscount}>
                        <div className="space-y-2">
                            <Label htmlFor="discount-name">Discount name</Label>
                            <Input
                                id="discount-name"
                                value={discountName}
                                onChange={(event) => onDiscountNameChange(event.target.value)}
                                placeholder="e.g., Athletic Scholarship"
                                maxLength={120}
                                autoFocus
                            />
                            {discountErrors.name && <p className="text-destructive text-xs">{discountErrors.name}</p>}
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="discount-percentage">Lecture fee discount (%)</Label>
                            <Input
                                id="discount-percentage"
                                type="number"
                                min="1"
                                max="100"
                                step="1"
                                value={discountPercentage}
                                onChange={(event) => onDiscountPercentageChange(event.target.value)}
                                placeholder="1–100"
                            />
                            {discountErrors.percentage && <p className="text-destructive text-xs">{discountErrors.percentage}</p>}
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => onDiscountDialogOpenChange(false)} disabled={creatingDiscount}>
                                Cancel
                            </Button>
                            <Button type="submit" disabled={creatingDiscount || !discountName.trim() || !discountPercentage}>
                                {creatingDiscount && <Loader2 className="h-4 w-4 animate-spin" />}
                                Create Discount
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <AlertDialog
                open={Boolean(pendingOverloadConfirmation)}
                onOpenChange={(open) => {
                    if (!open) onDismissOverload();
                }}
            >
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Force enroll into full class?</AlertDialogTitle>
                        <AlertDialogDescription>
                            The selected class has no available slots. Confirming will overload the class and enroll the student anyway.
                        </AlertDialogDescription>
                    </AlertDialogHeader>

                    {pendingOverloadConfirmation && (
                        <div className="space-y-2 rounded-md border bg-orange-50 p-3 text-sm dark:bg-orange-950/20">
                            {pendingOverloadConfirmation.sections.map(({ subject_code, section }) => (
                                <div key={`${subject_code}-${section.id}`} className="space-y-1">
                                    <div className="font-medium">
                                        {subject_code} - Section {section.section}
                                    </div>
                                    <div className="text-muted-foreground text-xs">
                                        {section.enrolled_count}/{section.max_slots} slots filled
                                        {section.faculty ? ` • ${section.faculty}` : ""}
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}

                    <AlertDialogFooter>
                        <AlertDialogCancel disabled={submitting}>Cancel</AlertDialogCancel>
                        <AlertDialogAction onClick={onConfirmOverload} disabled={submitting} className="bg-orange-600 hover:bg-orange-700">
                            Confirm overload
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </>
    );
}
