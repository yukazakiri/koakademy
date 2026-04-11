import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from "@/components/ui/accordion";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Separator } from "@/components/ui/separator";
import { Table, TableBody, TableCell, TableRow } from "@/components/ui/table";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Link, usePage } from "@inertiajs/react";
import { Printer, AlertCircle, Banknote, BookOpen, Calendar as CalendarIcon, CheckCircle, Clock, FileText, GraduationCap, ShieldCheck, User as UserIcon, XCircle } from "lucide-react";
import React from "react";
import { Cell, Legend, Pie, PieChart, Tooltip as RechartsTooltip, ResponsiveContainer } from "recharts";
import type { Branding, StudentDetail } from "../types";
import { StudentSignaturePad } from "./student-signature-pad";
import { TextEntry } from "./text-entry";

interface StudentTabsProps {
    student: StudentDetail;
    options: {
        statuses?: { value: string; label: string }[];
    };
}

export function StudentTabs({ student, options }: StudentTabsProps) {
    const { props } = usePage<{ branding?: Branding }>();
    const currency = props.branding?.currency || "PHP";
    const zeroString = currency === "USD" ? "$ 0.00" : "₱ 0.00";

    return (
                        <Tabs defaultValue="academic" className="w-full">
                            <TabsList className="grid h-auto w-full grid-cols-2 lg:grid-cols-7">
                                <TabsTrigger value="academic">Academic</TabsTrigger>
                                <TabsTrigger value="parents">Parents</TabsTrigger>
                                <TabsTrigger value="school">School</TabsTrigger>
                                <TabsTrigger value="contact">Contact</TabsTrigger>
                                <TabsTrigger value="documents">Documents</TabsTrigger>
                                <TabsTrigger value="tuition">Tuition</TabsTrigger>
                                <TabsTrigger value="clearance">Clearance</TabsTrigger>
                            </TabsList>

                            <div className="mt-4">
                                <TabsContent value="academic">
                                    <Card>
                                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-4">
                                            <div className="space-y-1">
                                                <CardTitle className="text-base font-semibold">Academic Profile</CardTitle>
                                                <CardDescription>Current enrollment status and program details.</CardDescription>
                                            </div>
                                            <Badge
                                                variant={
                                                    student.status === "enrolled"
                                                        ? "default"
                                                        : student.status === "graduated"
                                                          ? "secondary"
                                                          : student.status === "dropped"
                                                            ? "destructive"
                                                            : "outline"
                                                }
                                                className="capitalize"
                                            >
                                                {options.statuses?.find((statusOption) => statusOption.value === student.status)?.label || student.status}
                                            </Badge>
                                        </CardHeader>
                                        <Separator />
                                        <CardContent className="p-6">
                                            <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                                                <div className="space-y-4">
                                                    <div className="grid gap-1">
                                                        <div className="text-muted-foreground flex items-center gap-2">
                                                            <CalendarIcon className="h-3.5 w-3.5" />
                                                            <span className="text-xs font-medium tracking-wider uppercase">Academic Year</span>
                                                        </div>
                                                        <div className="pl-5.5 text-sm font-medium">
                                                            {student.academic_year}
                                                            <span className="text-muted-foreground ml-2 font-normal">— Current Level</span>
                                                        </div>
                                                    </div>

                                                    <div className="grid gap-1">
                                                        <div className="text-muted-foreground flex items-center gap-2">
                                                            <BookOpen className="h-3.5 w-3.5" />
                                                            <span className="text-xs font-medium tracking-wider uppercase">Program</span>
                                                        </div>
                                                        <div className="pl-5.5">
                                                            <div className="text-sm font-semibold">{student.course.code}</div>
                                                            <div className="text-muted-foreground text-sm">{student.course.title}</div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div className="space-y-4">
                                                    <div className="grid gap-1">
                                                        <div className="text-muted-foreground flex items-center gap-2">
                                                            <ShieldCheck className="h-3.5 w-3.5" />
                                                            <span className="text-xs font-medium tracking-wider uppercase">Status Details</span>
                                                        </div>
                                                        <div className="pl-5.5">
                                                            {student.status === "graduated" ? (
                                                                <div className="bg-muted rounded-md p-3 text-sm">
                                                                    <div className="mb-1 flex items-center gap-2 font-medium">
                                                                        <GraduationCap className="h-4 w-4" />
                                                                        Alumni
                                                                    </div>
                                                                    <p className="text-muted-foreground text-xs leading-relaxed">
                                                                        Student has successfully completed all program requirements.
                                                                    </p>
                                                                </div>
                                                            ) : (
                                                                <div className="text-sm">
                                                                    Currently <span className="font-medium">{student.status}</span> for S.Y.{" "}
                                                                    {student.current_school_year}
                                                                </div>
                                                            )}
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </CardContent>
                                    </Card>
                                </TabsContent>
                                <TabsContent value="parents">
                                    <Card>
                                        <CardHeader>
                                            <CardTitle>Parent Information</CardTitle>
                                        </CardHeader>
                                        <CardContent className="grid grid-cols-1 gap-6 md:grid-cols-2">
                                            <TextEntry label="Father's Name" value={student.parents?.fathers_name} />
                                            <TextEntry label="Mother's Name" value={student.parents?.mothers_name} />
                                        </CardContent>
                                    </Card>
                                </TabsContent>
                                <TabsContent value="school">
                                    <Card>
                                        <CardHeader>
                                            <CardTitle>School Information</CardTitle>
                                        </CardHeader>
                                        <CardContent className="space-y-6">
                                            <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                                                <TextEntry label="Elementary School" value={student.education?.elementary_school} />
                                                <TextEntry label="Elem. Grad Year" value={student.education?.elementary_graduate_year} />
                                                <TextEntry label="Elem. Address" value={student.education?.elementary_school_address} />
                                            </div>
                                            <Separator />
                                            <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                                                <TextEntry label="Senior High School" value={student.education?.senior_high_name} />
                                                <TextEntry label="SHS Grad Year" value={student.education?.senior_high_graduate_year} />
                                                <TextEntry label="SHS Address" value={student.education?.senior_high_address} />
                                            </div>
                                        </CardContent>
                                    </Card>
                                </TabsContent>
                                <TabsContent value="contact">
                                    <Card>
                                        <CardHeader>
                                            <CardTitle>Contact Information</CardTitle>
                                        </CardHeader>
                                        <CardContent className="grid grid-cols-1 gap-6 md:grid-cols-2">
                                            <TextEntry label="Phone Number" value={student.contacts?.personal_contact} />
                                            <TextEntry label="Facebook" value={student.contacts?.facebook_contact} />
                                            <Separator className="md:col-span-2" />
                                            <TextEntry label="Emergency Contact" value={student.contacts?.emergency_contact_name} />
                                            <TextEntry label="Emergency Phone" value={student.contacts?.emergency_contact_phone} />
                                            <TextEntry label="Emergency Address" value={student.contacts?.emergency_contact_address} />
                                        </CardContent>
                                    </Card>
                                </TabsContent>
                                <TabsContent value="documents">
                                    <Card>
                                        <CardHeader>
                                            <CardTitle>Documents</CardTitle>
                                            <CardDescription>Manage required files and capture the student signature.</CardDescription>
                                        </CardHeader>
                                        <CardContent className="space-y-6">
                                            <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                                <div className="space-y-1">
                                                    <p className="text-sm font-medium">Document Manager</p>
                                                    <p className="text-muted-foreground text-xs">Upload and manage required student files.</p>
                                                </div>
                                                <Button asChild variant="outline" size="sm">
                                                    <Link href={route("administrators.students.documents.index", student.id)}>
                                                        <FileText className="mr-2 h-4 w-4" />
                                                        Open Manager
                                                    </Link>
                                                </Button>
                                            </div>
                                            <Separator />
                                            <div className="max-w-xs">
                                                <div className="text-muted-foreground mb-1.5 flex items-center gap-1.5 text-xs font-medium tracking-wider uppercase">
                                                    Signature
                                                </div>
                                                <StudentSignaturePad studentId={student.id} signatureUrl={student.signature_url} />
                                            </div>
                                        </CardContent>
                                    </Card>
                                </TabsContent>
                                <TabsContent value="tuition">
                                    {student.tuition ? (
                                        <div className="space-y-6">
                                            {/* Dashboard Header */}
                                            <div className="flex flex-col justify-between gap-4 md:flex-row md:items-center">
                                                <div>
                                                    <h3 className="flex items-center gap-2 text-xl font-bold tracking-tight">
                                                        Financial Dashboard
                                                        <Badge variant="outline" className="ml-2">
                                                            {student.tuition.school_year} • Sem {student.tuition.semester}
                                                        </Badge>
                                                    </h3>
                                                    <p className="text-muted-foreground text-sm">Real-time overview of student tuition and fees.</p>
                                                </div>
                                                <div className="flex items-center gap-2">
                                                    <Badge
                                                        className={
                                                            student.tuition.total_balance === zeroString
                                                                ? "border-green-200 bg-green-500/10 text-green-600 hover:bg-green-500/20"
                                                                : "border-amber-200 bg-amber-500/10 text-amber-600 hover:bg-amber-500/20"
                                                        }
                                                    >
                                                        {student.tuition.payment_status}
                                                    </Badge>
                                                    <Button variant="outline" size="sm" className="h-8 gap-2">
                                                        <Printer className="h-3.5 w-3.5" />
                                                        Print SOA
                                                    </Button>
                                                </div>
                                            </div>

                                            {/* Main Metrics Cards */}
                                            <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                                                <Card className="dark:to-background border-blue-100 bg-gradient-to-br from-blue-50 to-white shadow-sm transition-shadow hover:shadow-md dark:border-blue-900 dark:from-blue-950/20">
                                                    <CardContent className="p-6">
                                                        <div className="flex items-start justify-between">
                                                            <div>
                                                                <p className="mb-1 text-sm font-medium text-blue-600 dark:text-blue-400">
                                                                    Total Assessment
                                                                </p>
                                                                <h4 className="text-foreground text-3xl font-bold tracking-tight">
                                                                    {student.tuition.overall_tuition}
                                                                </h4>
                                                            </div>
                                                            <div className="rounded-full bg-blue-100 p-2 dark:bg-blue-900/30">
                                                                <Banknote className="h-5 w-5 text-blue-600 dark:text-blue-400" />
                                                            </div>
                                                        </div>
                                                        <div className="text-muted-foreground mt-4 flex items-center gap-1 text-xs">
                                                            <span className="text-foreground font-medium">{student.tuition.total_tuition}</span>{" "}
                                                            tuition + misc fees
                                                        </div>
                                                    </CardContent>
                                                </Card>

                                                <Card className="dark:to-background border-green-100 bg-gradient-to-br from-green-50 to-white shadow-sm transition-shadow hover:shadow-md dark:border-green-900 dark:from-green-950/20">
                                                    <CardContent className="p-6">
                                                        <div className="flex items-start justify-between">
                                                            <div>
                                                                <p className="mb-1 text-sm font-medium text-green-600 dark:text-green-400">
                                                                    Total Paid
                                                                </p>
                                                                <h4 className="text-foreground text-3xl font-bold tracking-tight">
                                                                    {student.tuition.total_paid}
                                                                </h4>
                                                            </div>
                                                            <div className="rounded-full bg-green-100 p-2 dark:bg-green-900/30">
                                                                <CheckCircle className="h-5 w-5 text-green-600 dark:text-green-400" />
                                                            </div>
                                                        </div>
                                                        <div className="mt-4 h-1.5 w-full overflow-hidden rounded-full bg-green-100 dark:bg-green-900/20">
                                                            <div
                                                                className="h-full rounded-full bg-green-500"
                                                                style={{ width: `${student.tuition.payment_progress}%` }}
                                                            />
                                                        </div>
                                                        <div className="mt-1 text-right text-xs font-medium text-green-600 dark:text-green-400">
                                                            {student.tuition.payment_progress}% Paid
                                                        </div>
                                                    </CardContent>
                                                </Card>

                                                <Card className="dark:to-background border-amber-100 bg-gradient-to-br from-amber-50 to-white shadow-sm transition-shadow hover:shadow-md dark:border-amber-900 dark:from-amber-950/20">
                                                    <CardContent className="p-6">
                                                        <div className="flex items-start justify-between">
                                                            <div>
                                                                <p className="mb-1 text-sm font-medium text-amber-600 dark:text-amber-400">
                                                                    Balance Due
                                                                </p>
                                                                <h4 className="text-foreground text-3xl font-bold tracking-tight">
                                                                    {student.tuition.total_balance}
                                                                </h4>
                                                            </div>
                                                            <div className="rounded-full bg-amber-100 p-2 dark:bg-amber-900/30">
                                                                <AlertCircle className="h-5 w-5 text-amber-600 dark:text-amber-400" />
                                                            </div>
                                                        </div>
                                                        <div className="text-muted-foreground mt-4 text-xs">
                                                            {student.tuition.total_balance === zeroString ? (
                                                                <span className="flex items-center gap-1 font-medium text-green-600">
                                                                    <CheckCircle className="h-3 w-3" /> Fully Settled
                                                                </span>
                                                            ) : (
                                                                <span className="font-medium text-amber-600">Payment required</span>
                                                            )}
                                                        </div>
                                                    </CardContent>
                                                </Card>
                                            </div>

                                            <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                                                {/* Visualization Column */}
                                                <Card className="border-none bg-transparent shadow-none lg:col-span-1">
                                                    <CardContent className="p-0">
                                                        <div className="bg-card h-full rounded-xl border p-4">
                                                            <h4 className="mb-4 text-sm font-semibold">Fee Distribution</h4>
                                                            <div className="relative h-[250px] w-full">
                                                                <ResponsiveContainer width="100%" height="100%">
                                                                    <PieChart>
                                                                        <Pie
                                                                            data={[
                                                                                {
                                                                                    name: "Lecture",
                                                                                    value: parseFloat(
                                                                                        student.tuition.total_lectures.replace(/[^0-9.-]+/g, ""),
                                                                                    ),
                                                                                },
                                                                                {
                                                                                    name: "Laboratory",
                                                                                    value: parseFloat(
                                                                                        student.tuition.total_laboratory.replace(/[^0-9.-]+/g, ""),
                                                                                    ),
                                                                                },
                                                                                {
                                                                                    name: "Misc",
                                                                                    value: parseFloat(
                                                                                        student.tuition.total_miscelaneous_fees.replace(
                                                                                            /[^0-9.-]+/g,
                                                                                            "",
                                                                                        ),
                                                                                    ),
                                                                                },
                                                                            ]}
                                                                            cx="50%"
                                                                            cy="50%"
                                                                            innerRadius={60}
                                                                            outerRadius={80}
                                                                            paddingAngle={5}
                                                                            dataKey="value"
                                                                        >
                                                                            <Cell fill="#3b82f6" /> {/* blue-500 */}
                                                                            <Cell fill="#8b5cf6" /> {/* violet-500 */}
                                                                            <Cell fill="#f59e0b" /> {/* amber-500 */}
                                                                        </Pie>
                                                                        <RechartsTooltip
                                                                            formatter={(value: number | string) =>
                                                                                new Intl.NumberFormat(currency === "USD" ? "en-US" : "en-PH", {
                                                                                    style: "currency",
                                                                                    currency: currency,
                                                                                }).format(Number(value))
                                                                            }
                                                                            contentStyle={{
                                                                                borderRadius: "8px",
                                                                                border: "none",
                                                                                boxShadow: "0 4px 6px -1px rgb(0 0 0 / 0.1)",
                                                                            }}
                                                                        />
                                                                        <Legend verticalAlign="bottom" height={36} />
                                                                    </PieChart>
                                                                </ResponsiveContainer>
                                                                {/* Center text */}
                                                                <div className="pointer-events-none absolute inset-0 flex items-center justify-center pb-8">
                                                                    <div className="text-center">
                                                                        <div className="text-muted-foreground text-xs">Total</div>
                                                                        <div className="text-sm font-bold">{student.tuition.overall_tuition}</div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </CardContent>
                                                </Card>

                                                {/* Breakdown & Details */}
                                                <Card className="overflow-hidden rounded-xl border lg:col-span-2">
                                                    <CardHeader className="bg-muted/30 pb-4">
                                                        <CardTitle className="text-base font-semibold">Detailed Breakdown</CardTitle>
                                                    </CardHeader>
                                                    <CardContent className="p-0">
                                                        <Accordion type="single" collapsible defaultValue="fees" className="w-full">
                                                            <AccordionItem value="fees" className="border-b">
                                                                <AccordionTrigger className="hover:bg-muted/50 px-6 py-3 transition-colors hover:no-underline">
                                                                    <div className="flex items-center gap-2">
                                                                        <FileText className="text-primary h-4 w-4" />
                                                                        <span>Tuition & Fees Breakdown</span>
                                                                    </div>
                                                                </AccordionTrigger>
                                                                <AccordionContent className="px-6 pb-4">
                                                                    <Table>
                                                                        <TableBody>
                                                                            <TableRow className="border-none hover:bg-transparent">
                                                                                <TableCell className="py-2 pl-0">Lecture Fees</TableCell>
                                                                                <TableCell className="py-2 pr-0 text-right font-mono">
                                                                                    {student.tuition.total_lectures}
                                                                                </TableCell>
                                                                            </TableRow>
                                                                            <TableRow className="border-none hover:bg-transparent">
                                                                                <TableCell className="py-2 pl-0">Laboratory Fees</TableCell>
                                                                                <TableCell className="py-2 pr-0 text-right font-mono">
                                                                                    {student.tuition.total_laboratory}
                                                                                </TableCell>
                                                                            </TableRow>
                                                                            <TableRow className="border-b hover:bg-transparent">
                                                                                <TableCell className="py-2 pl-0">Miscellaneous Fees</TableCell>
                                                                                <TableCell className="py-2 pr-0 text-right font-mono">
                                                                                    {student.tuition.total_miscelaneous_fees}
                                                                                </TableCell>
                                                                            </TableRow>

                                                                            {student.tuition.discount !== "0%" && (
                                                                                <TableRow className="text-green-600 hover:bg-transparent">
                                                                                    <TableCell className="flex items-center gap-2 py-2 pl-0">
                                                                                        Discount
                                                                                        <Badge
                                                                                            variant="outline"
                                                                                            className="h-5 border-green-200 bg-green-50 text-[10px] text-green-700"
                                                                                        >
                                                                                            {student.tuition.discount}
                                                                                        </Badge>
                                                                                    </TableCell>
                                                                                    <TableCell className="py-2 pr-0 text-right font-mono">
                                                                                        - Applied
                                                                                    </TableCell>
                                                                                </TableRow>
                                                                            )}

                                                                            <TableRow className="text-base font-bold hover:bg-transparent">
                                                                                <TableCell className="pt-4 pl-0">Total Amount Due</TableCell>
                                                                                <TableCell className="text-primary pt-4 pr-0 text-right font-mono">
                                                                                    {student.tuition.overall_tuition}
                                                                                </TableCell>
                                                                            </TableRow>
                                                                        </TableBody>
                                                                    </Table>
                                                                </AccordionContent>
                                                            </AccordionItem>

                                                            <AccordionItem value="payment" className="border-b-0">
                                                                <AccordionTrigger className="hover:bg-muted/50 px-6 py-3 transition-colors hover:no-underline">
                                                                    <div className="flex items-center gap-2">
                                                                        <Banknote className="h-4 w-4 text-green-600" />
                                                                        <span>Payment Details</span>
                                                                    </div>
                                                                </AccordionTrigger>
                                                                <AccordionContent className="px-6 pb-4">
                                                                    <div className="grid grid-cols-2 gap-4 pt-2">
                                                                        <div className="space-y-1">
                                                                            <p className="text-muted-foreground text-xs tracking-wider uppercase">
                                                                                Downpayment
                                                                            </p>
                                                                            <p className="font-medium">{student.tuition.downpayment}</p>
                                                                        </div>
                                                                        <div className="space-y-1">
                                                                            <p className="text-muted-foreground text-xs tracking-wider uppercase">
                                                                                Payment Status
                                                                            </p>
                                                                            <Badge
                                                                                variant="outline"
                                                                                className={
                                                                                    student.tuition.total_balance === zeroString
                                                                                        ? "border-green-200 bg-green-50 text-green-700"
                                                                                        : "border-amber-200 bg-amber-50 text-amber-700"
                                                                                }
                                                                            >
                                                                                {student.tuition.payment_status}
                                                                            </Badge>
                                                                        </div>
                                                                        <div className="space-y-1">
                                                                            <p className="text-muted-foreground text-xs tracking-wider uppercase">
                                                                                Academic Year
                                                                            </p>
                                                                            <p className="font-medium">Year {student.tuition.academic_year}</p>
                                                                        </div>
                                                                        <div className="space-y-1">
                                                                            <p className="text-muted-foreground text-xs tracking-wider uppercase">
                                                                                Last Updated
                                                                            </p>
                                                                            <p className="text-muted-foreground text-sm font-medium">Just now</p>
                                                                        </div>
                                                                    </div>
                                                                </AccordionContent>
                                                            </AccordionItem>
                                                        </Accordion>
                                                    </CardContent>
                                                </Card>
                                            </div>
                                        </div>
                                    ) : (
                                        <Card>
                                            <CardContent className="py-12">
                                                <div className="flex flex-col items-center justify-center space-y-4 text-center">
                                                    <div className="bg-muted rounded-full p-4">
                                                        <AlertCircle className="text-muted-foreground h-8 w-8" />
                                                    </div>
                                                    <div>
                                                        <h3 className="text-lg font-semibold">No Tuition Record Found</h3>
                                                        <p className="text-muted-foreground mt-1 text-sm">
                                                            No tuition record exists for the current academic period ({student.current_school_year} •
                                                            Semester {student.current_semester}).
                                                        </p>
                                                    </div>
                                                    <Button asChild variant="outline">
                                                        <Link href={route("administrators.students.edit", student.id)}>Create Tuition Record</Link>
                                                    </Button>
                                                </div>
                                            </CardContent>
                                        </Card>
                                    )}
                                </TabsContent>
                                <TabsContent value="clearance">
                                    <div className="space-y-6">
                                        <Card>
                                            <CardHeader>
                                                <CardTitle>Current Semester Clearance</CardTitle>
                                            </CardHeader>
                                            <CardContent className="grid grid-cols-1 gap-6 md:grid-cols-2">
                                                <div className="flex flex-col gap-1">
                                                    <dt className="text-muted-foreground text-xs font-medium tracking-wider uppercase">Status</dt>
                                                    <dd className="mt-1">
                                                        {student.current_clearance?.is_cleared ? (
                                                            <Badge className="bg-green-500 hover:bg-green-600">
                                                                <CheckCircle className="mr-1 h-3 w-3" /> Cleared
                                                            </Badge>
                                                        ) : (
                                                            <Badge variant="destructive">
                                                                <XCircle className="mr-1 h-3 w-3" /> Not Cleared
                                                            </Badge>
                                                        )}
                                                    </dd>
                                                </div>
                                                {student.current_clearance?.is_cleared && (
                                                    <>
                                                        <TextEntry
                                                            label="Cleared By"
                                                            value={student.current_clearance.cleared_by}
                                                            icon={<UserIcon className="h-4 w-4" />}
                                                        />
                                                        <TextEntry
                                                            label="Cleared At"
                                                            value={new Date(student.current_clearance.cleared_at).toLocaleString()}
                                                            icon={<Clock className="h-4 w-4" />}
                                                        />
                                                    </>
                                                )}
                                                {student.current_clearance?.remarks && (
                                                    <div className="md:col-span-2">
                                                        <TextEntry label="Remarks" value={student.current_clearance.remarks} />
                                                    </div>
                                                )}
                                            </CardContent>
                                        </Card>

                                        <Card>
                                            <CardHeader>
                                                <CardTitle>Previous Semester Clearance</CardTitle>
                                            </CardHeader>
                                            <CardContent className="space-y-4">
                                                <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                                                    <div className="flex flex-col gap-1">
                                                        <dt className="text-muted-foreground text-xs font-medium tracking-wider uppercase">Status</dt>
                                                        <dd className="mt-1">
                                                            {student.previous_clearance_validation.allowed ? (
                                                                <Badge className="bg-green-500 hover:bg-green-600">
                                                                    <CheckCircle className="mr-1 h-3 w-3" /> Cleared
                                                                </Badge>
                                                            ) : (
                                                                <Badge variant="destructive">
                                                                    <XCircle className="mr-1 h-3 w-3" /> Not Cleared
                                                                </Badge>
                                                            )}
                                                        </dd>
                                                    </div>
                                                    <TextEntry label="Enrollment Eligibility" value={student.previous_clearance_validation.message} />
                                                </div>
                                            </CardContent>
                                        </Card>
                                    </div>
                                </TabsContent>
                            </div>
                        </Tabs>

    );
}
