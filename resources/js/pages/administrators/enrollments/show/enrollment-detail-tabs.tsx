import { index as tuitionAdjustments } from "@/actions/App/Http/Controllers/AdministratorTuitionAdjustmentController";
import { Avatar, AvatarFallback } from "@/components/ui/avatar";
import { Badge } from "@/components/ui/badge";
import { Button, buttonVariants } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Separator } from "@/components/ui/separator";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { TabsContent } from "@/components/ui/tabs";
import { cn } from "@/lib/utils";
import { router } from "@inertiajs/react";
import { AlertTriangle, CheckCircle2, Download, FileText, Pencil, School } from "lucide-react";
import { EditTransactionDialog, EnrollClassButton } from "./enrollment-action-dialogs";
import type { EnrollmentData, ShowEnrollmentPageProps } from "./types";

interface EnrollmentDetailTabsProps {
    enrollment: EnrollmentData;
    auth: ShowEnrollmentPageProps["auth"];
    formatMoney: (amount: number | null | undefined) => string;
    requiredDownpayment: number;
    totalPaid: number;
    balanceDue: number;
    reviewingRequirementId: number | null;
    onReviewRequirement: (requirementId: number, action: "verify" | "waive") => void;
    onStartWaiveRequirement: (requirementId: number) => void;
}

export function EnrollmentDetailTabs({
    enrollment,
    auth,
    formatMoney,
    requiredDownpayment,
    totalPaid,
    balanceDue,
    reviewingRequirementId,
    onReviewRequirement,
    onStartWaiveRequirement,
}: EnrollmentDetailTabsProps) {
    return (
        <>
            {/* CLASSES TAB */}
            <TabsContent value="classes" className="mt-5 space-y-5">
                <Card className="border-border/70 rounded-2xl shadow-sm">
                    <CardHeader className="gap-1">
                        <div className="flex items-center justify-between">
                            <div>
                                <CardTitle className="tracking-[-0.01em]">Class schedule</CardTitle>
                                <CardDescription>Enrolled subjects and class assignments</CardDescription>
                            </div>
                            <Badge variant="secondary">{enrollment.class_enrollments.length} Classes</Badge>
                        </div>
                    </CardHeader>
                    <CardContent className="p-0">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead className="pl-6">Subject</TableHead>
                                    <TableHead>Schedule &amp; Room</TableHead>
                                    <TableHead>Faculty</TableHead>
                                    <TableHead className="pr-6 text-center">Grades</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {enrollment.class_enrollments.length === 0 ? (
                                    <TableRow>
                                        <TableCell colSpan={4} className="text-muted-foreground py-12 text-center">
                                            No classes enrolled yet.
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    enrollment.class_enrollments.map((cls) => (
                                        <TableRow key={cls.id} className="hover:bg-muted/30 transition-colors">
                                            <TableCell className="pl-6 align-top">
                                                <div className="font-semibold">{cls.subject_code}</div>
                                                <div className="text-muted-foreground line-clamp-1 text-xs">{cls.subject_title}</div>
                                                <Badge variant="outline" className="mt-1 h-5 text-[10px]">
                                                    {cls.section}
                                                </Badge>
                                            </TableCell>
                                            <TableCell className="align-top">
                                                <div className="text-sm font-medium">{cls.schedule}</div>
                                                <div className="text-muted-foreground flex items-center gap-1 text-xs">
                                                    <School className="h-3 w-3" /> {cls.room}
                                                </div>
                                            </TableCell>
                                            <TableCell className="align-top">
                                                <div className="flex items-center gap-2">
                                                    <Avatar className="h-6 w-6">
                                                        <AvatarFallback className="text-[10px]">{cls.faculty.charAt(0)}</AvatarFallback>
                                                    </Avatar>
                                                    <span className="text-sm">{cls.faculty}</span>
                                                </div>
                                            </TableCell>
                                            <TableCell className="pr-6 text-center align-top">
                                                <div className="flex justify-center gap-1 font-mono text-xs">
                                                    <span
                                                        title="Prelim"
                                                        className={cn(
                                                            "bg-muted rounded px-1.5 py-0.5",
                                                            cls.grades.prelim ? "text-foreground" : "text-muted-foreground/50",
                                                        )}
                                                    >
                                                        {cls.grades.prelim ?? "-"}
                                                    </span>
                                                    <span
                                                        title="Midterm"
                                                        className={cn(
                                                            "bg-muted rounded px-1.5 py-0.5",
                                                            cls.grades.midterm ? "text-foreground" : "text-muted-foreground/50",
                                                        )}
                                                    >
                                                        {cls.grades.midterm ?? "-"}
                                                    </span>
                                                    <span
                                                        title="Finals"
                                                        className={cn(
                                                            "bg-muted rounded px-1.5 py-0.5",
                                                            cls.grades.finals ? "text-foreground" : "text-muted-foreground/50",
                                                        )}
                                                    >
                                                        {cls.grades.finals ?? "-"}
                                                    </span>
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    ))
                                )}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>

                {enrollment.missing_classes.length > 0 && (
                    <div className="overflow-hidden rounded-2xl border border-amber-200 bg-amber-50 shadow-sm dark:border-amber-900 dark:bg-amber-950/20">
                        <div className="flex items-center gap-3 border-b border-amber-200 bg-amber-100/50 p-4 dark:border-amber-900 dark:bg-amber-900/40">
                            <AlertTriangle className="h-5 w-5 text-amber-600 dark:text-amber-500" />
                            <div>
                                <h3 className="font-semibold text-amber-900 dark:text-amber-200">Action Required: Missing Class Assignments</h3>
                                <p className="text-xs text-amber-700 dark:text-amber-400">
                                    The student is enrolled in these subjects but not assigned to any class section.
                                </p>
                            </div>
                        </div>
                        <div className="p-0">
                            <Table>
                                <TableBody>
                                    {enrollment.missing_classes.map((cls, idx) => (
                                        <TableRow key={idx} className="hover:bg-amber-100/20">
                                            <TableCell className="pl-6">
                                                <div className="font-medium text-amber-900 dark:text-amber-200">{cls.subject_code}</div>
                                                <div className="text-xs text-amber-700 dark:text-amber-400">{cls.subject_title}</div>
                                            </TableCell>
                                            <TableCell>
                                                {cls.class_id ? (
                                                    <div className="flex flex-col">
                                                        <span className="text-muted-foreground text-xs">Available:</span>
                                                        <Badge variant="outline" className="bg-background w-fit">
                                                            {cls.section}
                                                        </Badge>
                                                    </div>
                                                ) : (
                                                    <span className="text-muted-foreground text-xs italic">No class offering available</span>
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                {cls.class_id && (
                                                    <div className="flex flex-col">
                                                        <span className="text-muted-foreground text-xs">Slots:</span>
                                                        <span
                                                            className={cn("text-sm font-medium", cls.is_full ? "text-red-600" : "text-green-600")}
                                                        >
                                                            {cls.available_slots} / {cls.max_slots}
                                                        </span>
                                                    </div>
                                                )}
                                            </TableCell>
                                            <TableCell className="pr-6 text-right">
                                                {cls.class_id && (
                                                    <EnrollClassButton
                                                        enrollmentId={enrollment.id}
                                                        classId={cls.class_id}
                                                        isFull={cls.is_full}
                                                        subjectCode={cls.subject_code}
                                                    />
                                                )}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                    </div>
                )}
            </TabsContent>

            {/* FINANCIAL TAB */}
            <TabsContent value="financial" className="mt-5 space-y-5">
                <div className="grid gap-6 md:grid-cols-2">
                    <Card className="border-border/70 rounded-2xl shadow-sm">
                        <CardHeader className="gap-1">
                            <CardTitle className="tracking-[-0.01em]">Fees breakdown</CardTitle>
                            <CardDescription>Detailed assessment of fees</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="space-y-2">
                                <div className="mb-2 flex items-center justify-between">
                                    <h4 className="text-sm font-semibold">Breakdown</h4>
                                    {auth.can_manage_tuition && (
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            className="text-muted-foreground hover:text-foreground h-6 w-6"
                                            aria-label="Open tuition adjustment workspace"
                                            onClick={() => router.visit(tuitionAdjustments.url({ query: { enrollment: enrollment.id } }))}
                                        >
                                            <Pencil className="h-3 w-3" />
                                        </Button>
                                    )}
                                </div>
                                <div className="flex justify-between text-sm">
                                    <span className="text-muted-foreground">Tuition Fee (Lectures)</span>
                                    <span>{formatMoney(enrollment.tuition?.total_lectures)}</span>
                                </div>
                                <div className="flex justify-between text-sm">
                                    <span className="text-muted-foreground">Laboratory Fee</span>
                                    <span>{formatMoney(enrollment.tuition?.total_laboratory)}</span>
                                </div>
                                <div className="flex justify-between text-sm">
                                    <span className="text-muted-foreground">Miscellaneous Fee</span>
                                    <span>{formatMoney(enrollment.tuition?.total_miscelaneous_fees)}</span>
                                </div>
                                {enrollment.additional_fees.map((fee) => (
                                    <div key={fee.id} className="flex justify-between text-sm">
                                        <span className="text-muted-foreground">{fee.fee_name}</span>
                                        <span>{formatMoney(fee.amount)}</span>
                                    </div>
                                ))}
                            </div>
                            <Separator />
                            <div className="space-y-2">
                                <div className="flex justify-between text-sm font-medium">
                                    <span>Assessed Fees</span>
                                    <span>{formatMoney(enrollment.tuition?.overall_tuition)}</span>
                                </div>
                                {enrollment.tuition?.discount ? (
                                    <div className="flex justify-between text-sm text-green-600">
                                        <span>Discount</span>
                                        <span>{enrollment.tuition.discount}% applied</span>
                                    </div>
                                ) : null}
                                <div className="flex justify-between pt-2 text-base font-bold">
                                    <span>Total Assessment</span>
                                    <span>{formatMoney(enrollment.tuition?.overall_tuition)}</span>
                                </div>
                                <div className="flex justify-between text-sm">
                                    <span>Required Downpayment</span>
                                    <span>{formatMoney(requiredDownpayment)}</span>
                                </div>
                                <div className="flex justify-between text-sm text-green-600">
                                    <span>Total Paid</span>
                                    <span>- {formatMoney(totalPaid)}</span>
                                </div>
                                <div className="flex justify-between text-base font-bold">
                                    <span>Remaining Balance</span>
                                    <span>{formatMoney(balanceDue)}</span>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="border-border/70 rounded-2xl shadow-sm">
                        <CardHeader className="gap-1">
                            <CardTitle className="tracking-[-0.01em]">Transaction history</CardTitle>
                            <CardDescription>Payments and adjustments</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="border-muted relative ml-3 space-y-6 border-l">
                                {enrollment.transactions.length === 0 ? (
                                    <p className="text-muted-foreground pl-6 text-sm">No transactions recorded yet.</p>
                                ) : (
                                    enrollment.transactions.map((tx) => (
                                        <div key={tx.id} className="relative pl-6">
                                            <div
                                                className={cn(
                                                    "border-background absolute top-1.5 -left-1.5 h-3 w-3 rounded-full border-2",
                                                    tx.status === "Paid" || tx.status === "Completed" ? "bg-green-500" : "bg-muted",
                                                )}
                                            />
                                            <div className="flex flex-col gap-1 sm:flex-row sm:items-start sm:justify-between">
                                                <div>
                                                    <p className="text-sm font-medium">{tx.description}</p>
                                                    <div className="mt-1 flex items-center gap-2">
                                                        <Badge variant="outline" className="h-5 text-[10px]">
                                                            {tx.invoicenumber}
                                                        </Badge>
                                                        <span className="text-muted-foreground text-xs">
                                                            {new Date(tx.created_at).toLocaleDateString()}
                                                        </span>
                                                    </div>
                                                </div>
                                                <div className="flex flex-col items-end">
                                                    <div className="flex items-center gap-2">
                                                        <span className="text-sm font-bold">{formatMoney(tx.amount || tx.total_amount)}</span>
                                                        {auth.can_verify_cashier && (
                                                            <EditTransactionDialog enrollmentId={enrollment.id} transaction={tx} />
                                                        )}
                                                    </div>
                                                    <Badge
                                                        variant={tx.status === "Paid" || tx.status === "Completed" ? "default" : "secondary"}
                                                        className={cn(
                                                            "mt-1 h-5 text-[10px]",
                                                            (tx.status === "Paid" || tx.status === "Completed") &&
                                                                "border-green-200 bg-green-100 text-green-800 hover:bg-green-100",
                                                        )}
                                                    >
                                                        {tx.status}
                                                    </Badge>
                                                </div>
                                            </div>
                                        </div>
                                    ))
                                )}
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </TabsContent>

            {/* RESOURCES TAB */}
            <TabsContent value="resources" className="mt-5 space-y-5">
                <Card className="border-border/70 rounded-2xl shadow-sm">
                    <CardHeader className="gap-1">
                        <CardTitle className="tracking-[-0.01em]">Generated documents</CardTitle>
                        <CardDescription>Official enrollment forms and assessments</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                            {enrollment.resources.map((res) => (
                                <div
                                    key={res.id}
                                    className="bg-card/60 border-border/70 hover:bg-muted/30 flex items-start gap-4 rounded-xl border p-4 transition-[background-color,box-shadow] duration-200 hover:shadow-sm"
                                >
                                    <div className="bg-primary/10 text-primary rounded-md p-2">
                                        <FileText className="h-6 w-6" />
                                    </div>
                                    <div className="flex-1 space-y-1">
                                        <p className="text-sm font-medium capitalize">{res.type.replace("_", " ")}</p>
                                        <p className="text-muted-foreground max-w-[150px] truncate font-mono text-xs">{res.file_name}</p>
                                        <p className="text-muted-foreground text-[10px]">{new Date(res.created_at).toLocaleString()}</p>
                                    </div>
                                    <a
                                        href={res.download_url}
                                        target="_blank"
                                        rel="noreferrer"
                                        className={buttonVariants({
                                            variant: "ghost",
                                            size: "icon",
                                            className: "text-muted-foreground h-8 w-8",
                                        })}
                                    >
                                        <Download className="h-4 w-4" />
                                    </a>
                                </div>
                            ))}
                            {enrollment.resources.length === 0 && (
                                <div className="text-muted-foreground col-span-full py-12 text-center">No documents available.</div>
                            )}
                        </div>
                    </CardContent>
                </Card>
            </TabsContent>

            {/* REQUIREMENTS TAB */}
            <TabsContent value="requirements" className="mt-5 space-y-5">
                <Card className="border-border/70 rounded-2xl shadow-sm">
                    <CardHeader className="gap-1">
                        <CardTitle className="tracking-[-0.01em]">Enrollment requirements</CardTitle>
                        <CardDescription>
                            Required items must be verified or waived with a reason before their configured workflow step.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        {enrollment.requirements.map((requirement) => (
                            <div
                                key={requirement.id}
                                className="bg-card/60 border-border/70 hover:bg-muted/20 flex flex-col gap-4 rounded-xl border p-4 transition-colors sm:flex-row sm:items-center sm:justify-between"
                            >
                                <div className="min-w-0">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <p className="font-medium">{requirement.label}</p>
                                        <Badge
                                            variant={requirement.status === "pending" ? "secondary" : "outline"}
                                            className={cn(
                                                requirement.status === "verified" && "border-green-200 bg-green-50 text-green-700",
                                                requirement.status === "waived" && "border-amber-200 bg-amber-50 text-amber-700",
                                            )}
                                        >
                                            {requirement.status}
                                        </Badge>
                                        {requirement.required && <Badge variant="outline">Required</Badge>}
                                    </div>
                                    {requirement.description && <p className="text-muted-foreground mt-1 text-sm">{requirement.description}</p>}
                                    <p className="text-muted-foreground mt-1 text-xs">
                                        {requirement.enforcement_step
                                            ? `Enforced before ${requirement.enforcement_step.replaceAll("_", " ")}`
                                            : "Collected for review without a workflow gate"}
                                    </p>
                                    {requirement.waiver_reason && (
                                        <p className="mt-2 text-sm text-amber-700">Waiver reason: {requirement.waiver_reason}</p>
                                    )}
                                </div>
                                {requirement.status === "pending" && auth.can_review_requirements && (
                                    <div className="flex shrink-0 gap-2">
                                        <Button
                                            type="button"
                                            size="sm"
                                            disabled={reviewingRequirementId === requirement.id}
                                            onClick={() => onReviewRequirement(requirement.id, "verify")}
                                        >
                                            <CheckCircle2 className="h-4 w-4" />
                                            Verify
                                        </Button>
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant="outline"
                                            disabled={reviewingRequirementId === requirement.id}
                                            onClick={() => onStartWaiveRequirement(requirement.id)}
                                        >
                                            Waive
                                        </Button>
                                    </div>
                                )}
                            </div>
                        ))}
                    </CardContent>
                </Card>
            </TabsContent>
        </>
    );
}
