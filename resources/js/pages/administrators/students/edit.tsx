import AdminLayout from "@/components/administrators/admin-layout";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Command, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList } from "@/components/ui/command";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Separator } from "@/components/ui/separator";
import { Switch } from "@/components/ui/switch";
import { Textarea } from "@/components/ui/textarea";
import { cn } from "@/lib/utils";
import type { User } from "@/types/user";
import { Head, Link, router, useForm } from "@inertiajs/react";
import { AnimatePresence, motion } from "framer-motion";
import { Activity, ArrowLeft, BookOpen, Check, Contact, LayoutGrid, Plus, Save, School, Sparkles, Trash2, User as UserIcon } from "lucide-react";
import { useEffect, useState } from "react";
import { toast } from "sonner";

declare let route: any;

interface Student {
    id: number;
    student_type: string;
    student_id: string;
    lrn: string | null;
    first_name: string;
    last_name: string;
    middle_name: string | null;
    gender: string;
    birth_date: string;
    age: number;
    email: string | null;
    course_id: number | null;
    academic_year: number;
    shs_strand_id: number | null;
    remarks: string | null;
    created_at: string;

    // Statistical Data
    ethnicity: string | null;
    city_of_origin: string | null;
    province_of_origin: string | null;
    region_of_origin: string | null;
    is_indigenous_person: boolean;
    indigenous_group: string | null;
    status: string | null;
    withdrawal_date: string | null;
    withdrawal_reason: string | null;
    attrition_category: string | null;
    dropout_date: string | null;
    scholarship_type: string | null;
    scholarship_details: string | null;
    employment_status: string | null;
    employer_name: string | null;
    job_position: string | null;
    employment_date: string | null;
    employed_by_institution: boolean;

    // Relationships
    Course: { code: string; title: string } | null;
    shsStrand: { strand_name: string } | null;
    studentContactsInfo: any;
    studentParentInfo: any;
    studentEducationInfo: any;
    personalInfo: any;
}

interface Option {
    value: string | number;
    label: string;
}

interface Subject {
    id: number;
    code: string;
    title: string;
    units: number;
}

interface EditStudentProps {
    user: User;
    student: Student;
    options: {
        types: Option[];
        statuses: Option[];
        courses: Option[];
        shs_strands: Option[];
        scholarship_types: Option[];
        employment_statuses: Option[];
        attrition_categories: Option[];
        regions: Option[];
        subjects: Option[];
    };
    current_enrollments: any[];
    current_classes: any[];
}

const SECTIONS = [
    { id: "basic", label: "Basic Info", icon: UserIcon },
    { id: "personal", label: "Personal & Contact", icon: Contact },
    { id: "education", label: "Education History", icon: School },
    { id: "statistical", label: "Statistical Data", icon: Activity },
    { id: "enrollment", label: "Enrollment & Classes", icon: BookOpen },
];

export default function AdministratorStudentEdit({ user, student, options, current_enrollments = [], current_classes = [] }: EditStudentProps) {
    const [activeSection, setActiveSection] = useState("basic");
    const [addSubjectOpen, setAddSubjectOpen] = useState(false);
    const [selectedSubject, setSelectedSubject] = useState<string | null>(null);
    const [isGeneratingId, setIsGeneratingId] = useState(false);

    const { data, setData, put, processing, errors } = useForm({
        // Basic Info
        student_type: student.student_type || "college",
        student_id: student.student_id || "",
        lrn: student.lrn || "",
        first_name: student.first_name || "",
        last_name: student.last_name || "",
        middle_name: student.middle_name || "",
        gender: student.gender || "male",
        birth_date: student.birth_date ? student.birth_date.split("T")[0] : "",
        age: student.age || 0,
        email: student.email || "",
        course_id: student.course_id ? student.course_id.toString() : "",
        academic_year: student.academic_year ? student.academic_year.toString() : "1",
        shs_strand_id: student.shs_strand_id ? student.shs_strand_id.toString() : "",
        remarks: student.remarks || "",

        // Guardian Contact
        personal_contact: student.studentContactsInfo?.personal_contact || "",
        emergency_contact_name: student.studentContactsInfo?.emergency_contact_name || "",
        emergency_contact_phone: student.studentContactsInfo?.emergency_contact_phone || "",
        emergency_contact_address: student.studentContactsInfo?.emergency_contact_address || "",

        // Parent Info
        fathers_name: student.studentParentInfo?.fathers_name || "",
        mothers_name: student.studentParentInfo?.mothers_name || "",

        // Education Info
        elementary_school: student.studentEducationInfo?.elementary_school || "",
        elementary_graduate_year: student.studentEducationInfo?.elementary_graduate_year || "",
        elementary_school_address: student.studentEducationInfo?.elementary_school_address || "",
        junior_high_school_name: student.studentEducationInfo?.junior_high_school_name || "",
        junior_high_graduation_year: student.studentEducationInfo?.junior_high_graduation_year || "",
        junior_high_school_address: student.studentEducationInfo?.junior_high_school_address || "",
        senior_high_name: student.studentEducationInfo?.senior_high_name || "",
        senior_high_graduate_year: student.studentEducationInfo?.senior_high_graduate_year || "",
        senior_high_address: student.studentEducationInfo?.senior_high_address || "",

        // Address & Personal Info
        current_address: student.personalInfo?.current_adress || "",
        permanent_address: student.personalInfo?.permanent_address || "",
        birthplace: student.personalInfo?.birthplace || "",
        civil_status: student.personalInfo?.civil_status || "",
        citizenship: student.personalInfo?.citizenship || "",
        religion: student.personalInfo?.religion || "",
        weight: student.personalInfo?.weight || "",
        height: student.personalInfo?.height || "",

        // Statistical Data
        ethnicity: student.ethnicity || "",
        city_of_origin: student.city_of_origin || "",
        province_of_origin: student.province_of_origin || "",
        region_of_origin: student.region_of_origin || "",
        is_indigenous_person: student.is_indigenous_person || false,
        indigenous_group: student.indigenous_group || "",
        status: student.status || "enrolled",
        withdrawal_date: student.withdrawal_date ? student.withdrawal_date.split("T")[0] : "",
        withdrawal_reason: student.withdrawal_reason || "",
        attrition_category: student.attrition_category || "",
        dropout_date: student.dropout_date ? student.dropout_date.split("T")[0] : "",
        scholarship_type: student.scholarship_type || "none",
        scholarship_details: student.scholarship_details || "",
        employment_status: student.employment_status || "not_applicable",
        employer_name: student.employer_name || "",
        job_position: student.job_position || "",
        employment_date: student.employment_date ? student.employment_date.split("T")[0] : "",
        employed_by_institution: student.employed_by_institution || false,
    });

    // Auto-calculate age
    useEffect(() => {
        if (data.birth_date) {
            const age = new Date().getFullYear() - new Date(data.birth_date).getFullYear();
            setData("age", age);
        }
    }, [data.birth_date]);

    const handleGenerateId = async () => {
        if (!data.student_type) return;

        setIsGeneratingId(true);
        try {
            // @ts-ignore
            const url = route("administrators.students.generate-id", { type: data.student_type });
            const response = await fetch(url);
            if (response.ok) {
                const result = await response.json();
                if (result.id) {
                    setData("student_id", result.id.toString());
                }
            }
        } catch (error) {
            console.error("Failed to generate ID", error);
        } finally {
            setIsGeneratingId(false);
        }
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        put(route("administrators.students.update", student.id), {
            onSuccess: () => {
                toast.success("Student updated successfully");
            },
            onError: (errors) => {
                toast.error("Failed to update student", {
                    description: Object.values(errors)[0] || "Please check the form for errors",
                });
            },
        });
    };

    const handleAddSubject = () => {
        if (!selectedSubject) return;

        router.post(
            route("administrators.students.subjects.add", student.id),
            {
                subject_id: selectedSubject,
            },
            {
                onSuccess: () => {
                    setAddSubjectOpen(false);
                    setSelectedSubject(null);
                    toast.success("Subject added successfully");
                },
                onError: (errors) => {
                    toast.error("Failed to add subject", {
                        description: Object.values(errors)[0] || "Please check for errors",
                    });
                },
            },
        );
    };

    const handleRemoveSubject = (id: number) => {
        if (confirm("Are you sure you want to remove this subject?")) {
            router.delete(route("administrators.students.subjects.remove", [student.id, id]));
        }
    };

    const isSHS = data.student_type === "shs";

    const getYearLevelOptions = () => {
        if (isSHS) {
            return [
                { value: "11", label: "Grade 11" },
                { value: "12", label: "Grade 12" },
            ];
        }
        return [
            { value: "1", label: "1st Year" },
            { value: "2", label: "2nd Year" },
            { value: "3", label: "3rd Year" },
            { value: "4", label: "4th Year" },
            { value: "5", label: "Graduate" },
        ];
    };

    return (
        <AdminLayout user={user} title="Edit Student">
            <Head title={`Edit Student • ${student.first_name} ${student.last_name}`} />

            <div className="flex h-[calc(100vh-4rem)] flex-col gap-6">
                {/* Header */}
                <div className="flex shrink-0 items-start justify-between border-b pb-4">
                    <div className="flex items-center gap-4">
                        <div className="bg-primary/10 text-primary flex h-16 w-16 items-center justify-center rounded-full text-2xl font-bold">
                            {student.first_name[0]}
                            {student.last_name[0]}
                        </div>
                        <div>
                            <h2 className="text-2xl font-bold tracking-tight">
                                {data.first_name} {data.last_name}
                            </h2>
                            <div className="text-muted-foreground mt-1 flex items-center gap-2">
                                <Badge variant="outline" className="font-mono">
                                    {isSHS ? data.lrn : data.student_id}
                                </Badge>
                                <span>•</span>
                                <span className="capitalize">{data.student_type.replace("_", " ")}</span>
                                <span>•</span>
                                <Badge variant={data.status === "enrolled" ? "default" : "secondary"} className="capitalize">
                                    {data.status.replace("_", " ")}
                                </Badge>
                            </div>
                        </div>
                    </div>
                    <div className="flex gap-2">
                        <Button asChild variant="ghost">
                            <Link href={route("administrators.students.show", student.id)}>
                                <ArrowLeft className="mr-2 h-4 w-4" /> Back to Profile
                            </Link>
                        </Button>
                        <Button onClick={submit} disabled={processing} className="min-w-[140px]">
                            <Save className="mr-2 h-4 w-4" />
                            {processing ? "Saving..." : "Save Changes"}
                        </Button>
                    </div>
                </div>

                {/* Content Area */}
                <div className="flex flex-1 gap-8 overflow-hidden">
                    {/* Sidebar Navigation */}
                    <nav className="flex w-64 shrink-0 flex-col gap-1 overflow-y-auto pr-2">
                        {SECTIONS.map((section) => (
                            <button
                                key={section.id}
                                onClick={() => setActiveSection(section.id)}
                                className={cn(
                                    "flex items-center gap-3 rounded-lg px-4 py-3 text-left text-sm font-medium transition-colors",
                                    activeSection === section.id
                                        ? "bg-primary text-primary-foreground shadow-sm"
                                        : "text-muted-foreground hover:bg-muted hover:text-foreground",
                                )}
                            >
                                <section.icon className="h-4 w-4" />
                                {section.label}
                            </button>
                        ))}
                    </nav>

                    {/* Main Form */}
                    <div className="flex-1 overflow-y-auto pr-6 pb-20">
                        <AnimatePresence mode="wait">
                            <motion.div
                                key={activeSection}
                                initial={{ opacity: 0, x: 20 }}
                                animate={{ opacity: 1, x: 0 }}
                                exit={{ opacity: 0, x: -20 }}
                                transition={{ duration: 0.2 }}
                                className="space-y-6"
                            >
                                {activeSection === "basic" && (
                                    <Card>
                                        <CardHeader>
                                            <CardTitle>Basic Information</CardTitle>
                                            <CardDescription>Core identity and academic details</CardDescription>
                                        </CardHeader>
                                        <CardContent className="grid grid-cols-1 gap-6 md:grid-cols-2">
                                            <div className="space-y-2">
                                                <Label>Student Type</Label>
                                                <Select value={data.student_type} onValueChange={(val) => setData("student_type", val)}>
                                                    <SelectTrigger>
                                                        <SelectValue />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        {options.types.map((opt) => (
                                                            <SelectItem key={opt.value} value={opt.value.toString()}>
                                                                {opt.label}
                                                            </SelectItem>
                                                        ))}
                                                    </SelectContent>
                                                </Select>
                                            </div>

                                            <div className="space-y-2">
                                                <Label>{isSHS ? "LRN" : "Student ID"}</Label>
                                                <div className="flex gap-2">
                                                    <Input
                                                        value={isSHS ? data.lrn : data.student_id}
                                                        onChange={(e) =>
                                                            isSHS ? setData("lrn", e.target.value) : setData("student_id", e.target.value)
                                                        }
                                                        placeholder={isSHS ? "Learner Reference Number" : "Student ID"}
                                                    />
                                                    {!isSHS && (
                                                        <Button
                                                            type="button"
                                                            variant="outline"
                                                            size="icon"
                                                            onClick={handleGenerateId}
                                                            disabled={isGeneratingId}
                                                            title="Generate Next Available ID"
                                                        >
                                                            <Sparkles className={`h-4 w-4 ${isGeneratingId ? "animate-spin" : ""}`} />
                                                        </Button>
                                                    )}
                                                </div>
                                                {isSHS
                                                    ? errors.lrn && <p className="text-destructive text-sm">{errors.lrn}</p>
                                                    : errors.student_id && <p className="text-destructive text-sm">{errors.student_id}</p>}
                                                {!isSHS && !data.student_id && (
                                                    <p className="text-muted-foreground text-xs">Leave empty to use the system ID ({student.id})</p>
                                                )}
                                            </div>

                                            <div className="space-y-2">
                                                <Label>First Name</Label>
                                                <Input value={data.first_name} onChange={(e) => setData("first_name", e.target.value)} />
                                            </div>
                                            <div className="space-y-2">
                                                <Label>Last Name</Label>
                                                <Input value={data.last_name} onChange={(e) => setData("last_name", e.target.value)} />
                                            </div>
                                            <div className="space-y-2">
                                                <Label>Middle Name</Label>
                                                <Input value={data.middle_name} onChange={(e) => setData("middle_name", e.target.value)} />
                                            </div>

                                            <div className="space-y-2">
                                                <Label>Gender</Label>
                                                <Select value={data.gender} onValueChange={(val) => setData("gender", val)}>
                                                    <SelectTrigger>
                                                        <SelectValue />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem value="male">Male</SelectItem>
                                                        <SelectItem value="female">Female</SelectItem>
                                                    </SelectContent>
                                                </Select>
                                            </div>

                                            <div className="space-y-2">
                                                <Label>Birth Date</Label>
                                                <Input type="date" value={data.birth_date} onChange={(e) => setData("birth_date", e.target.value)} />
                                            </div>
                                            <div className="space-y-2">
                                                <Label>Age</Label>
                                                <Input value={data.age} readOnly className="bg-muted" />
                                            </div>

                                            <div className="space-y-2">
                                                <Label>Email</Label>
                                                <Input type="email" value={data.email} onChange={(e) => setData("email", e.target.value)} />
                                            </div>

                                            {!isSHS ? (
                                                <div className="space-y-2">
                                                    <Label>Course</Label>
                                                    <Select value={data.course_id} onValueChange={(val) => setData("course_id", val)}>
                                                        <SelectTrigger>
                                                            <SelectValue />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            {options.courses.map((opt) => (
                                                                <SelectItem key={opt.value} value={opt.value.toString()}>
                                                                    {opt.label}
                                                                </SelectItem>
                                                            ))}
                                                        </SelectContent>
                                                    </Select>
                                                </div>
                                            ) : (
                                                <div className="space-y-2">
                                                    <Label>Strand</Label>
                                                    <Select value={data.shs_strand_id} onValueChange={(val) => setData("shs_strand_id", val)}>
                                                        <SelectTrigger>
                                                            <SelectValue />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            {options.shs_strands.map((opt) => (
                                                                <SelectItem key={opt.value} value={opt.value.toString()}>
                                                                    {opt.label}
                                                                </SelectItem>
                                                            ))}
                                                        </SelectContent>
                                                    </Select>
                                                </div>
                                            )}

                                            <div className="space-y-2">
                                                <Label>{isSHS ? "Grade Level" : "Year Level"}</Label>
                                                <Select value={data.academic_year} onValueChange={(val) => setData("academic_year", val)}>
                                                    <SelectTrigger>
                                                        <SelectValue />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        {getYearLevelOptions().map((option) => (
                                                            <SelectItem key={option.value} value={option.value}>
                                                                {option.label}
                                                            </SelectItem>
                                                        ))}
                                                    </SelectContent>
                                                </Select>
                                            </div>
                                        </CardContent>
                                    </Card>
                                )}

                                {activeSection === "personal" && (
                                    <div className="grid gap-6">
                                        <Card>
                                            <CardHeader>
                                                <CardTitle>Contact Information</CardTitle>
                                            </CardHeader>
                                            <CardContent className="grid gap-6 md:grid-cols-2">
                                                <div className="space-y-2">
                                                    <Label>Personal Contact</Label>
                                                    <Input
                                                        value={data.personal_contact}
                                                        onChange={(e) => setData("personal_contact", e.target.value)}
                                                    />
                                                </div>
                                                <div className="space-y-2">
                                                    <Label>Current Address</Label>
                                                    <Textarea
                                                        value={data.current_address}
                                                        onChange={(e) => setData("current_address", e.target.value)}
                                                    />
                                                </div>
                                                <div className="space-y-2">
                                                    <Label>Permanent Address</Label>
                                                    <Textarea
                                                        value={data.permanent_address}
                                                        onChange={(e) => setData("permanent_address", e.target.value)}
                                                    />
                                                </div>
                                            </CardContent>
                                        </Card>
                                        <Card>
                                            <CardHeader>
                                                <CardTitle>Guardian Information</CardTitle>
                                            </CardHeader>
                                            <CardContent className="grid gap-6 md:grid-cols-2">
                                                <div className="space-y-2">
                                                    <Label>Guardian Name</Label>
                                                    <Input
                                                        value={data.emergency_contact_name}
                                                        onChange={(e) => setData("emergency_contact_name", e.target.value)}
                                                    />
                                                </div>
                                                <div className="space-y-2">
                                                    <Label>Guardian Phone</Label>
                                                    <Input
                                                        value={data.emergency_contact_phone}
                                                        onChange={(e) => setData("emergency_contact_phone", e.target.value)}
                                                    />
                                                </div>
                                                <div className="space-y-2 md:col-span-2">
                                                    <Label>Guardian Address</Label>
                                                    <Input
                                                        value={data.emergency_contact_address}
                                                        onChange={(e) => setData("emergency_contact_address", e.target.value)}
                                                    />
                                                </div>
                                            </CardContent>
                                        </Card>
                                        <Card>
                                            <CardHeader>
                                                <CardTitle>Parent Information</CardTitle>
                                            </CardHeader>
                                            <CardContent className="grid gap-6 md:grid-cols-2">
                                                <div className="space-y-2">
                                                    <Label>Father's Name</Label>
                                                    <Input value={data.fathers_name} onChange={(e) => setData("fathers_name", e.target.value)} />
                                                </div>
                                                <div className="space-y-2">
                                                    <Label>Mother's Name</Label>
                                                    <Input value={data.mothers_name} onChange={(e) => setData("mothers_name", e.target.value)} />
                                                </div>
                                            </CardContent>
                                        </Card>
                                    </div>
                                )}

                                {activeSection === "education" && (
                                    <Card>
                                        <CardHeader>
                                            <CardTitle>Educational Background</CardTitle>
                                        </CardHeader>
                                        <CardContent className="space-y-8">
                                            <div className="space-y-4">
                                                <h4 className="flex items-center gap-2 font-medium">
                                                    <School className="h-4 w-4" /> Elementary
                                                </h4>
                                                <div className="grid gap-4 md:grid-cols-2">
                                                    <div className="space-y-2">
                                                        <Label>School Name</Label>
                                                        <Input
                                                            value={data.elementary_school}
                                                            onChange={(e) => setData("elementary_school", e.target.value)}
                                                        />
                                                    </div>
                                                    <div className="space-y-2">
                                                        <Label>Year Graduated</Label>
                                                        <Input
                                                            value={data.elementary_graduate_year}
                                                            onChange={(e) => setData("elementary_graduate_year", e.target.value)}
                                                        />
                                                    </div>
                                                    <div className="space-y-2 md:col-span-2">
                                                        <Label>Address</Label>
                                                        <Input
                                                            value={data.elementary_school_address}
                                                            onChange={(e) => setData("elementary_school_address", e.target.value)}
                                                        />
                                                    </div>
                                                </div>
                                            </div>
                                            <Separator />
                                            <div className="space-y-4">
                                                <h4 className="flex items-center gap-2 font-medium">
                                                    <School className="h-4 w-4" /> Junior High
                                                </h4>
                                                <div className="grid gap-4 md:grid-cols-2">
                                                    <div className="space-y-2">
                                                        <Label>School Name</Label>
                                                        <Input
                                                            value={data.junior_high_school_name}
                                                            onChange={(e) => setData("junior_high_school_name", e.target.value)}
                                                        />
                                                    </div>
                                                    <div className="space-y-2">
                                                        <Label>Year Graduated</Label>
                                                        <Input
                                                            value={data.junior_high_graduation_year}
                                                            onChange={(e) => setData("junior_high_graduation_year", e.target.value)}
                                                        />
                                                    </div>
                                                    <div className="space-y-2 md:col-span-2">
                                                        <Label>Address</Label>
                                                        <Input
                                                            value={data.junior_high_school_address}
                                                            onChange={(e) => setData("junior_high_school_address", e.target.value)}
                                                        />
                                                    </div>
                                                </div>
                                            </div>
                                            <Separator />
                                            <div className="space-y-4">
                                                <h4 className="flex items-center gap-2 font-medium">
                                                    <School className="h-4 w-4" /> Senior High
                                                </h4>
                                                <div className="grid gap-4 md:grid-cols-2">
                                                    <div className="space-y-2">
                                                        <Label>School Name</Label>
                                                        <Input
                                                            value={data.senior_high_name}
                                                            onChange={(e) => setData("senior_high_name", e.target.value)}
                                                        />
                                                    </div>
                                                    <div className="space-y-2">
                                                        <Label>Year Graduated</Label>
                                                        <Input
                                                            value={data.senior_high_graduate_year}
                                                            onChange={(e) => setData("senior_high_graduate_year", e.target.value)}
                                                        />
                                                    </div>
                                                    <div className="space-y-2 md:col-span-2">
                                                        <Label>Address</Label>
                                                        <Input
                                                            value={data.senior_high_address}
                                                            onChange={(e) => setData("senior_high_address", e.target.value)}
                                                        />
                                                    </div>
                                                </div>
                                            </div>
                                        </CardContent>
                                    </Card>
                                )}

                                {activeSection === "statistical" && (
                                    <div className="grid gap-6">
                                        <Card>
                                            <CardHeader>
                                                <CardTitle>Demographics</CardTitle>
                                            </CardHeader>
                                            <CardContent className="grid gap-6 md:grid-cols-2">
                                                <div className="space-y-2">
                                                    <Label>Region</Label>
                                                    <Select value={data.region_of_origin} onValueChange={(val) => setData("region_of_origin", val)}>
                                                        <SelectTrigger>
                                                            <SelectValue />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            {options.regions.map((r) => (
                                                                <SelectItem key={r.value} value={r.value.toString()}>
                                                                    {r.label}
                                                                </SelectItem>
                                                            ))}
                                                        </SelectContent>
                                                    </Select>
                                                </div>
                                                <div className="space-y-2">
                                                    <Label>Ethnicity</Label>
                                                    <Input value={data.ethnicity} onChange={(e) => setData("ethnicity", e.target.value)} />
                                                </div>
                                                <div className="flex items-center gap-4 rounded-lg border p-4 md:col-span-2">
                                                    <Switch
                                                        checked={data.is_indigenous_person}
                                                        onCheckedChange={(c) => setData("is_indigenous_person", c)}
                                                    />
                                                    <div className="flex-1">
                                                        <Label>Indigenous Person</Label>
                                                        <p className="text-muted-foreground text-sm">
                                                            Is the student a member of an indigenous group?
                                                        </p>
                                                    </div>
                                                    {data.is_indigenous_person && (
                                                        <Input
                                                            placeholder="Group Name"
                                                            className="w-64"
                                                            value={data.indigenous_group}
                                                            onChange={(e) => setData("indigenous_group", e.target.value)}
                                                        />
                                                    )}
                                                </div>
                                            </CardContent>
                                        </Card>
                                        <Card>
                                            <CardHeader>
                                                <CardTitle>Scholarship & Employment</CardTitle>
                                            </CardHeader>
                                            <CardContent className="grid gap-6 md:grid-cols-2">
                                                <div className="space-y-2">
                                                    <Label>Scholarship</Label>
                                                    <Select value={data.scholarship_type} onValueChange={(val) => setData("scholarship_type", val)}>
                                                        <SelectTrigger>
                                                            <SelectValue />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            {options.scholarship_types.map((s) => (
                                                                <SelectItem key={s.value} value={s.value.toString()}>
                                                                    {s.label}
                                                                </SelectItem>
                                                            ))}
                                                        </SelectContent>
                                                    </Select>
                                                </div>
                                                <div className="space-y-2">
                                                    <Label>Employment Status</Label>
                                                    <Select value={data.employment_status} onValueChange={(val) => setData("employment_status", val)}>
                                                        <SelectTrigger>
                                                            <SelectValue />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            {options.employment_statuses.map((s) => (
                                                                <SelectItem key={s.value} value={s.value.toString()}>
                                                                    {s.label}
                                                                </SelectItem>
                                                            ))}
                                                        </SelectContent>
                                                    </Select>
                                                </div>
                                            </CardContent>
                                        </Card>
                                    </div>
                                )}

                                {activeSection === "enrollment" && (
                                    <div className="space-y-6">
                                        {/* Enrolled Subjects */}
                                        <Card>
                                            <CardHeader className="flex flex-row items-center justify-between">
                                                <div>
                                                    <CardTitle>Enrolled Subjects</CardTitle>
                                                    <CardDescription>Subjects for the current semester</CardDescription>
                                                </div>
                                                <Dialog open={addSubjectOpen} onOpenChange={setAddSubjectOpen}>
                                                    <DialogTrigger asChild>
                                                        <Button size="sm" className="gap-2">
                                                            <Plus className="h-4 w-4" /> Add Subject
                                                        </Button>
                                                    </DialogTrigger>
                                                    <DialogContent className="max-w-md overflow-hidden p-0">
                                                        <DialogHeader className="px-4 pt-4 pb-2">
                                                            <DialogTitle>Add Subject</DialogTitle>
                                                            <DialogDescription>Search for a subject to add to the student's load.</DialogDescription>
                                                        </DialogHeader>
                                                        <Command className="border-t">
                                                            <CommandInput placeholder="Search subjects..." />
                                                            <CommandList>
                                                                <CommandEmpty>No subject found.</CommandEmpty>
                                                                <CommandGroup heading="Available Subjects">
                                                                    {options.subjects.map((subject) => (
                                                                        <CommandItem
                                                                            key={subject.value}
                                                                            onSelect={() => {
                                                                                setSelectedSubject(subject.value.toString());
                                                                            }}
                                                                            className="flex items-center justify-between"
                                                                        >
                                                                            <span>{subject.label}</span>
                                                                            {selectedSubject === subject.value.toString() && (
                                                                                <Check className="h-4 w-4" />
                                                                            )}
                                                                        </CommandItem>
                                                                    ))}
                                                                </CommandGroup>
                                                            </CommandList>
                                                        </Command>
                                                        <DialogFooter className="bg-muted/50 p-4">
                                                            <Button onClick={handleAddSubject} disabled={!selectedSubject}>
                                                                Add Selected Subject
                                                            </Button>
                                                        </DialogFooter>
                                                    </DialogContent>
                                                </Dialog>
                                            </CardHeader>
                                            <CardContent>
                                                {current_enrollments.length === 0 ? (
                                                    <div className="text-muted-foreground rounded-lg border-2 border-dashed py-12 text-center">
                                                        <BookOpen className="mx-auto mb-2 h-8 w-8 opacity-50" />
                                                        <p>No subjects enrolled for this semester.</p>
                                                    </div>
                                                ) : (
                                                    <div className="space-y-4">
                                                        {current_enrollments.map((enrollment) => (
                                                            <div
                                                                key={enrollment.id}
                                                                className="bg-card hover:bg-accent/5 flex items-center justify-between rounded-lg border p-4 transition-colors"
                                                            >
                                                                <div className="flex items-start gap-3">
                                                                    <div className="bg-primary/10 text-primary flex h-10 w-10 items-center justify-center rounded text-xs font-bold">
                                                                        {enrollment.subject.code.substring(0, 3)}
                                                                    </div>
                                                                    <div>
                                                                        <h4 className="font-semibold">{enrollment.subject.code}</h4>
                                                                        <p className="text-muted-foreground text-sm">{enrollment.subject.title}</p>
                                                                    </div>
                                                                </div>
                                                                <div className="flex items-center gap-4">
                                                                    <Badge variant="outline">{enrollment.subject.units} Units</Badge>
                                                                    <Button
                                                                        variant="ghost"
                                                                        size="icon"
                                                                        className="text-destructive hover:text-destructive hover:bg-destructive/10"
                                                                        onClick={() => handleRemoveSubject(enrollment.id)}
                                                                    >
                                                                        <Trash2 className="h-4 w-4" />
                                                                    </Button>
                                                                </div>
                                                            </div>
                                                        ))}
                                                    </div>
                                                )}
                                            </CardContent>
                                        </Card>

                                        {/* Current Classes */}
                                        <Card>
                                            <CardHeader>
                                                <CardTitle>Current Classes</CardTitle>
                                                <CardDescription>Scheduled classes for this semester</CardDescription>
                                            </CardHeader>
                                            <CardContent>
                                                {current_classes.length === 0 ? (
                                                    <div className="text-muted-foreground rounded-lg border-2 border-dashed py-12 text-center">
                                                        <LayoutGrid className="mx-auto mb-2 h-8 w-8 opacity-50" />
                                                        <p>No classes scheduled.</p>
                                                    </div>
                                                ) : (
                                                    <div className="grid gap-4 md:grid-cols-2">
                                                        {current_classes.map((classEnrollment) => (
                                                            <div key={classEnrollment.id} className="bg-card space-y-3 rounded-lg border p-4">
                                                                <div className="flex items-start justify-between">
                                                                    <div>
                                                                        <h4 className="font-bold">{classEnrollment.class?.subject?.code || "N/A"}</h4>
                                                                        <p className="text-muted-foreground max-w-[200px] truncate text-sm">
                                                                            {classEnrollment.class?.subject?.title || "No subject assigned"}
                                                                        </p>
                                                                    </div>
                                                                    <Badge>{classEnrollment.class?.section || "N/A"}</Badge>
                                                                </div>
                                                                <Separator />
                                                                <div className="space-y-1 text-sm">
                                                                    <div className="text-muted-foreground flex items-center gap-2">
                                                                        <UserIcon className="h-3 w-3" />
                                                                        <span>{classEnrollment.class?.faculty?.full_name || "TBA"}</span>
                                                                    </div>
                                                                    {/* Schedule display would go here */}
                                                                </div>
                                                            </div>
                                                        ))}
                                                    </div>
                                                )}
                                            </CardContent>
                                        </Card>
                                    </div>
                                )}
                            </motion.div>
                        </AnimatePresence>
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
