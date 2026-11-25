import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Separator } from "@/components/ui/separator";
import { Switch } from "@/components/ui/switch";
import { Textarea } from "@/components/ui/textarea";
import { cn } from "@/lib/utils";
import { Calculator, CheckCircle2, Loader2, Plus } from "lucide-react";
import type { DiscountOption, EnrollmentTotals, StudentDetails } from "./types";

interface AssessmentSidebarProps {
    isEdit: boolean;
    submitting: boolean;
    selectedStudent: StudentDetails | null;
    subjectsCount: number;
    totals: EnrollmentTotals;
    discount: string;
    discountId: number | null;
    selectedDiscountValue: string;
    discountOptions: DiscountOption[];
    onDiscountChange: (value: string) => void;
    onOpenCreateDiscount: () => void;
    miscellaneousFee: number;
    onMiscellaneousFeeChange: (value: number) => void;
    downpayment: number;
    onDownpaymentChange: (value: number) => void;
    notifyStudent: boolean;
    onNotifyStudentChange: (checked: boolean) => void;
    changeReason: string;
    onChangeReasonChange: (value: string) => void;
    formatCurrency: (amount: number) => string;
}

export function AssessmentSidebar({
    isEdit,
    submitting,
    selectedStudent,
    subjectsCount,
    totals,
    discount,
    discountId,
    selectedDiscountValue,
    discountOptions,
    onDiscountChange,
    onOpenCreateDiscount,
    miscellaneousFee,
    onMiscellaneousFeeChange,
    downpayment,
    onDownpaymentChange,
    notifyStudent,
    onNotifyStudentChange,
    changeReason,
    onChangeReasonChange,
    formatCurrency,
}: AssessmentSidebarProps) {
    return (
        <div className="lg:w-80">
            <div className="space-y-4 lg:sticky lg:top-4">
                <Card>
                    <CardHeader className="pb-3">
                        <div className="flex items-center gap-2">
                            <Calculator className="text-primary h-5 w-5" />
                            <CardTitle className="text-lg">Assessment</CardTitle>
                        </div>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="grid grid-cols-3 gap-2 text-center">
                            <div className="rounded-lg bg-blue-50 p-2 dark:bg-blue-950/30">
                                <div className="text-lg font-bold text-blue-600">{totals.subjectsCount}</div>
                                <div className="text-muted-foreground text-[10px]">Subjects</div>
                            </div>
                            <div className="rounded-lg bg-green-50 p-2 dark:bg-green-950/30">
                                <div className="text-lg font-bold text-green-600">{totals.totalLectureUnits}</div>
                                <div className="text-muted-foreground text-[10px]">Lecture</div>
                            </div>
                            <div className="rounded-lg bg-purple-50 p-2 dark:bg-purple-950/30">
                                <div className="text-lg font-bold text-purple-600">{totals.totalLabUnits}</div>
                                <div className="text-muted-foreground text-[10px]">Lab</div>
                            </div>
                        </div>

                        <Separator />

                        <div className="space-y-2">
                            <Label className="text-xs">Discount</Label>
                            <div className="flex items-center gap-2">
                                <Select value={selectedDiscountValue} onValueChange={onDiscountChange}>
                                    <SelectTrigger className="h-9 flex-1">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="none">No Discount</SelectItem>
                                        {discountId === null && parseInt(discount) > 0 && (
                                            <SelectItem value="legacy">Current Discount ({discount}%)</SelectItem>
                                        )}
                                        {discountOptions.map((option) => (
                                            <SelectItem key={option.id} value={option.id.toString()}>
                                                {option.name} ({option.percentage}%)
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="icon"
                                    className="h-9 w-9 shrink-0"
                                    aria-label="Create discount"
                                    title="Create discount"
                                    onClick={onOpenCreateDiscount}
                                >
                                    <Plus className="h-4 w-4" />
                                </Button>
                            </div>
                            <p className="text-muted-foreground text-xs">Discounts apply to lecture fees only.</p>
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="miscellaneous-fee" className="text-xs">
                                Miscellaneous Fee
                            </Label>
                            <Input
                                id="miscellaneous-fee"
                                type="number"
                                min="0"
                                step="0.01"
                                value={miscellaneousFee}
                                onChange={(event) => onMiscellaneousFeeChange(Math.max(0, Number(event.target.value)))}
                                className="h-9"
                            />
                            <p className="text-muted-foreground text-xs">Overrides the course fee for this enrollment only.</p>
                        </div>

                        <div className="space-y-2 text-sm">
                            <div className="flex justify-between">
                                <span className="text-muted-foreground">Lecture Fees</span>
                                <span>{formatCurrency(totals.totalLectures)}</span>
                            </div>
                            {totals.discountAmount > 0 && (
                                <div className="flex justify-between text-green-600">
                                    <span>Discount (-{discount}%)</span>
                                    <span>-{formatCurrency(totals.discountAmount)}</span>
                                </div>
                            )}
                            <div className="flex justify-between">
                                <span className="text-muted-foreground">Laboratory Fees</span>
                                <span>{formatCurrency(totals.totalLaboratory)}</span>
                            </div>
                            {totals.modularCount > 0 && (
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">Modular ({totals.modularCount})</span>
                                    <span>{formatCurrency(totals.totalModularFee)}</span>
                                </div>
                            )}
                            {totals.excludedSubjectsCount > 0 && (
                                <div className="flex justify-between text-amber-600 dark:text-amber-400">
                                    <span>Excluded subjects</span>
                                    <span>{totals.excludedSubjectsCount}</span>
                                </div>
                            )}
                            <div className="flex justify-between">
                                <span className="text-muted-foreground">Miscellaneous</span>
                                <span>{formatCurrency(totals.miscellaneous)}</span>
                            </div>
                            {totals.totalAdditionalFees > 0 && (
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">Additional</span>
                                    <span>{formatCurrency(totals.totalAdditionalFees)}</span>
                                </div>
                            )}
                        </div>

                        <Separator />

                        <div className="flex justify-between text-lg font-bold">
                            <span>Total</span>
                            <span className="text-primary">{formatCurrency(totals.overallTotal)}</span>
                        </div>

                        <div className="space-y-2">
                            <Label className="text-xs">Required Downpayment</Label>
                            <Input
                                type="number"
                                min={500}
                                step={10}
                                value={downpayment}
                                onChange={(e) => onDownpaymentChange(parseFloat(e.target.value) || 0)}
                                className="h-9"
                            />
                        </div>

                        <div
                            className={cn(
                                "flex justify-between rounded-lg p-3 font-bold",
                                totals.balance > 0
                                    ? "bg-orange-50 text-orange-700 dark:bg-orange-950/30 dark:text-orange-400"
                                    : "bg-green-50 text-green-700 dark:bg-green-950/30 dark:text-green-400",
                            )}
                        >
                            <span>Projected Balance</span>
                            <span>{formatCurrency(totals.balance)}</span>
                        </div>
                        <p className="text-muted-foreground text-xs">This downpayment is only counted as paid after cashier verification.</p>

                        {isEdit && (
                            <>
                                <Separator />
                                <div className="space-y-3">
                                    <div className="flex items-center gap-2">
                                        <Switch id="notify-student" checked={notifyStudent} onCheckedChange={onNotifyStudentChange} />
                                        <Label htmlFor="notify-student" className="cursor-pointer text-sm font-medium">
                                            Notify student of changes
                                        </Label>
                                    </div>
                                    {notifyStudent && (
                                        <div className="space-y-2">
                                            <Label htmlFor="change-reason" className="text-xs">
                                                Reason for change
                                            </Label>
                                            <Textarea
                                                id="change-reason"
                                                placeholder="e.g., Section reassignment due to class size balancing"
                                                value={changeReason}
                                                onChange={(e) => onChangeReasonChange(e.target.value)}
                                                rows={3}
                                                className="resize-none text-sm"
                                            />
                                            <p className="text-muted-foreground text-xs">
                                                An email with the updated class schedule and assessment PDF will be sent to{" "}
                                                {selectedStudent?.email || "the student"}.
                                            </p>
                                        </div>
                                    )}
                                </div>
                            </>
                        )}

                        <Button type="submit" className="w-full" size="lg" disabled={submitting || !selectedStudent || subjectsCount === 0}>
                            {submitting ? (
                                <>
                                    <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                    {isEdit ? "Saving..." : "Creating..."}
                                </>
                            ) : (
                                <>
                                    <CheckCircle2 className="mr-2 h-4 w-4" />
                                    {isEdit ? "Save Changes" : "Create Enrollment"}
                                </>
                            )}
                        </Button>
                    </CardContent>
                </Card>
            </div>
        </div>
    );
}
