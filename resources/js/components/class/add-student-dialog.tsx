import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle, DialogTrigger } from "@/components/ui/dialog";
import { HoverCard, HoverCardContent, HoverCardTrigger } from "@/components/ui/hover-card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { ScrollArea } from "@/components/ui/scroll-area";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { router } from "@inertiajs/react";
import { IconCheck, IconInfoCircle, IconLoader, IconPlus, IconSearch, IconUser, IconUserPlus } from "@tabler/icons-react";
import axios from "axios";
import { useEffect, useState } from "react";
import { toast } from "sonner";
import { useDebounce } from "use-debounce";

interface StudentResult {
    id: number;
    name: string;
    student_id: string;
    email: string | null;
    avatar: string | null;
    status: {
        in_this_class: boolean;
        in_other_section: string | null;
        has_subject_enrollment: boolean;
    };
    current_subjects: {
        code: string;
        title: string;
    }[];
}

interface StrandOption {
    id: number;
    name: string;
    track: string;
}

interface AddStudentDialogProps {
    classId: number | string;
    classification?: string; // 'college' | 'shs'
}

type FlashMessage = {
    success?: string;
    error?: string;
};

export function AddStudentDialog({ classId, classification = "college" }: AddStudentDialogProps) {
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState("");
    const [debouncedQuery] = useDebounce(query, 500);
    const [results, setResults] = useState<StudentResult[]>([]);
    const [loading, setLoading] = useState(false);
    const [addingId, setAddingId] = useState<number | null>(null);
    const [activeTab, setActiveTab] = useState("search");

    // SHS Form State
    const [strands, setStrands] = useState<StrandOption[]>([]);
    const [loadingStrands, setLoadingStrands] = useState(false);
    const [creatingStudent, setCreatingStudent] = useState(false);
    const [shsForm, setShsForm] = useState({
        lrn: "",
        last_name: "",
        first_name: "",
        middle_name: "",
        birth_date: "",
        gender: "",
        contact: "",
        strand_id: "",
        grade_level: "",
    });

    const isShs = classification === "shs";

    // Fetch strands when dialog opens and it's an SHS class
    useEffect(() => {
        if (open && isShs && strands.length === 0) {
            const fetchStrands = async () => {
                setLoadingStrands(true);
                try {
                    const response = await axios.get(`/faculty/classes/${classId}/shs-strands`);
                    setStrands(response.data.strands);
                } catch (error) {
                    console.error("Failed to fetch strands", error);
                } finally {
                    setLoadingStrands(false);
                }
            };
            fetchStrands();
        }
    }, [open, isShs, classId, strands.length]);

    useEffect(() => {
        if (debouncedQuery.length < 2) {
            setResults([]);
            return;
        }

        const searchStudents = async () => {
            setLoading(true);
            try {
                const response = await axios.get(`/faculty/classes/${classId}/students/search`, {
                    params: { query: debouncedQuery },
                });
                setResults(response.data.students);
            } catch (error) {
                console.error("Failed to search students", error);
                toast.error("Failed to search students. Please try again.");
            } finally {
                setLoading(false);
            }
        };

        searchStudents();
    }, [debouncedQuery, classId]);

    const handleAddStudent = (studentId: number) => {
        setAddingId(studentId);
        router.post(
            `/faculty/classes/${classId}/students`,
            { student_id: studentId },
            {
                onSuccess: (page) => {
                    const flash = (page.props as { flash?: FlashMessage }).flash;
                    if (flash?.error) {
                        toast.error(flash.error);
                        return;
                    }

                    toast.success(flash?.success ?? "Student added successfully");
                    setResults((prev) => prev.map((s) => (s.id === studentId ? { ...s, status: { ...s.status, in_this_class: true } } : s)));
                },
                onError: (errors) => {
                    toast.error("Failed to add student");
                    console.error(errors);
                },
                onFinish: () => {
                    setAddingId(null);
                },
                preserveScroll: true,
            },
        );
    };

    const handleCreateSHSStudent = () => {
        // Validate required fields
        if (
            !shsForm.lrn ||
            !shsForm.last_name ||
            !shsForm.first_name ||
            !shsForm.birth_date ||
            !shsForm.gender ||
            !shsForm.strand_id ||
            !shsForm.grade_level
        ) {
            toast.error("Please fill in all required fields");
            return;
        }

        setCreatingStudent(true);
        router.post(
            `/faculty/classes/${classId}/students/create-shs`,
            {
                ...shsForm,
                strand_id: parseInt(shsForm.strand_id),
                enroll_in_class: true,
            },
            {
                onSuccess: (page) => {
                    const flash = (page.props as { flash?: FlashMessage }).flash;
                    if (flash?.error) {
                        toast.error(flash.error);
                        return;
                    }

                    toast.success(flash?.success ?? "SHS student created and enrolled successfully");
                    setShsForm({
                        lrn: "",
                        last_name: "",
                        first_name: "",
                        middle_name: "",
                        birth_date: "",
                        gender: "",
                        contact: "",
                        strand_id: "",
                        grade_level: "",
                    });
                    setOpen(false);
                },
                onError: (errors: Record<string, string>) => {
                    const errorMessage = errors.lrn || errors.message || "Failed to create student";
                    toast.error(errorMessage);
                    console.error(errors);
                },
                onFinish: () => {
                    setCreatingStudent(false);
                },
                preserveScroll: true,
            },
        );
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm" className="h-8 gap-2">
                    <IconPlus className="h-4 w-4" />
                    <span className="hidden sm:inline">Add Student</span>
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Add Student to Class</DialogTitle>
                    <DialogDescription>
                        {isShs
                            ? "Search for existing students or create a new SHS student record."
                            : "Search for students by name or ID to manually enroll them in this class."}
                    </DialogDescription>
                </DialogHeader>

                {isShs ? (
                    <Tabs value={activeTab} onValueChange={setActiveTab} className="w-full">
                        <TabsList className="grid w-full grid-cols-2">
                            <TabsTrigger value="search">Search Existing</TabsTrigger>
                            <TabsTrigger value="create">Create New</TabsTrigger>
                        </TabsList>

                        <TabsContent value="search" className="space-y-4 py-4">
                            <div className="relative">
                                <IconSearch className="text-muted-foreground absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2" />
                                <Input
                                    placeholder="Search by name, LRN, or student ID..."
                                    value={query}
                                    onChange={(e) => setQuery(e.target.value)}
                                    className="pl-9"
                                />
                                {loading && (
                                    <IconLoader className="text-muted-foreground absolute top-1/2 right-3 h-4 w-4 -translate-y-1/2 animate-spin" />
                                )}
                            </div>

                            <ScrollArea className="h-[300px] rounded-md border p-2">
                                {results.length === 0 && query.length >= 2 && !loading ? (
                                    <div className="text-muted-foreground flex flex-col items-center justify-center p-8">
                                        <IconUser className="mb-2 h-10 w-10 opacity-20" />
                                        <p className="text-sm">No students found matching "{query}"</p>
                                        <p className="text-muted-foreground/60 mt-1 text-xs">Try creating a new student instead.</p>
                                    </div>
                                ) : results.length === 0 && query.length < 2 ? (
                                    <div className="text-muted-foreground flex flex-col items-center justify-center p-8">
                                        <IconSearch className="mb-2 h-10 w-10 opacity-20" />
                                        <p className="text-sm">Enter at least 2 characters to search.</p>
                                    </div>
                                ) : (
                                    <div className="space-y-2">
                                        {results.map((student) => (
                                            <div
                                                key={student.id}
                                                className="hover:bg-muted/50 flex items-center justify-between rounded-lg border p-3 transition-colors"
                                            >
                                                <div className="flex items-center gap-3">
                                                    <Avatar>
                                                        <AvatarImage src={student.avatar || undefined} />
                                                        <AvatarFallback>{student.name.charAt(0)}</AvatarFallback>
                                                    </Avatar>
                                                    <div className="space-y-1">
                                                        <div className="flex items-center gap-2">
                                                            <p className="text-sm leading-none font-medium">{student.name}</p>
                                                            <Badge variant="outline" className="h-4 px-1.5 py-0 text-[10px]">
                                                                {student.student_id}
                                                            </Badge>
                                                        </div>
                                                        <p className="text-muted-foreground text-xs">{student.email || "No email"}</p>
                                                    </div>
                                                </div>

                                                <Button
                                                    size="sm"
                                                    variant={student.status.in_this_class ? "ghost" : "default"}
                                                    disabled={student.status.in_this_class || addingId === student.id}
                                                    onClick={() => handleAddStudent(student.id)}
                                                >
                                                    {student.status.in_this_class ? (
                                                        <>
                                                            <IconCheck className="mr-2 h-4 w-4" />
                                                            Enrolled
                                                        </>
                                                    ) : addingId === student.id ? (
                                                        <>
                                                            <IconLoader className="mr-2 h-4 w-4 animate-spin" />
                                                            Adding...
                                                        </>
                                                    ) : (
                                                        "Add"
                                                    )}
                                                </Button>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </ScrollArea>
                        </TabsContent>

                        <TabsContent value="create" className="space-y-4 py-4">
                            <div className="grid gap-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="lrn">LRN (Learner Reference Number) *</Label>
                                    <Input
                                        id="lrn"
                                        placeholder="Enter 12-digit LRN"
                                        value={shsForm.lrn}
                                        onChange={(e) => setShsForm({ ...shsForm, lrn: e.target.value })}
                                    />
                                </div>

                                <div className="grid grid-cols-3 gap-3">
                                    <div className="grid gap-2">
                                        <Label htmlFor="last_name">Last Name *</Label>
                                        <Input
                                            id="last_name"
                                            placeholder="Surname"
                                            value={shsForm.last_name}
                                            onChange={(e) => setShsForm({ ...shsForm, last_name: e.target.value })}
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="first_name">First Name *</Label>
                                        <Input
                                            id="first_name"
                                            placeholder="First name"
                                            value={shsForm.first_name}
                                            onChange={(e) => setShsForm({ ...shsForm, first_name: e.target.value })}
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="middle_name">Middle Name</Label>
                                        <Input
                                            id="middle_name"
                                            placeholder="Middle name"
                                            value={shsForm.middle_name}
                                            onChange={(e) => setShsForm({ ...shsForm, middle_name: e.target.value })}
                                        />
                                    </div>
                                </div>

                                <div className="grid grid-cols-2 gap-3">
                                    <div className="grid gap-2">
                                        <Label htmlFor="birth_date">Birth Date *</Label>
                                        <Input
                                            id="birth_date"
                                            type="date"
                                            value={shsForm.birth_date}
                                            onChange={(e) => setShsForm({ ...shsForm, birth_date: e.target.value })}
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="gender">Gender *</Label>
                                        <Select value={shsForm.gender} onValueChange={(value) => setShsForm({ ...shsForm, gender: value })}>
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select gender" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="male">Male</SelectItem>
                                                <SelectItem value="female">Female</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="contact">Contact Number</Label>
                                    <Input
                                        id="contact"
                                        placeholder="09XX XXX XXXX"
                                        value={shsForm.contact}
                                        onChange={(e) => setShsForm({ ...shsForm, contact: e.target.value })}
                                    />
                                </div>

                                <div className="grid grid-cols-2 gap-3">
                                    <div className="grid gap-2">
                                        <Label htmlFor="strand">Strand *</Label>
                                        <Select value={shsForm.strand_id} onValueChange={(value) => setShsForm({ ...shsForm, strand_id: value })}>
                                            <SelectTrigger>
                                                <SelectValue placeholder={loadingStrands ? "Loading..." : "Select strand"} />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {strands.map((strand) => (
                                                    <SelectItem key={strand.id} value={String(strand.id)}>
                                                        {strand.name} ({strand.track})
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="grade_level">Grade Level *</Label>
                                        <Select value={shsForm.grade_level} onValueChange={(value) => setShsForm({ ...shsForm, grade_level: value })}>
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select grade" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="11">Grade 11</SelectItem>
                                                <SelectItem value="12">Grade 12</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                </div>

                                <Button className="mt-2 w-full" onClick={handleCreateSHSStudent} disabled={creatingStudent}>
                                    {creatingStudent ? (
                                        <>
                                            <IconLoader className="mr-2 h-4 w-4 animate-spin" />
                                            Creating...
                                        </>
                                    ) : (
                                        <>
                                            <IconUserPlus className="mr-2 h-4 w-4" />
                                            Create & Enroll Student
                                        </>
                                    )}
                                </Button>
                            </div>
                        </TabsContent>
                    </Tabs>
                ) : (
                    // Original search-only view for college classes
                    <div className="space-y-4 py-4">
                        <div className="relative">
                            <IconSearch className="text-muted-foreground absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2" />
                            <Input
                                placeholder="Search by name or student ID..."
                                value={query}
                                onChange={(e) => setQuery(e.target.value)}
                                className="pl-9"
                            />
                            {loading && (
                                <IconLoader className="text-muted-foreground absolute top-1/2 right-3 h-4 w-4 -translate-y-1/2 animate-spin" />
                            )}
                        </div>

                        <ScrollArea className="h-[300px] rounded-md border p-2">
                            {results.length === 0 && query.length >= 2 && !loading ? (
                                <div className="text-muted-foreground flex flex-col items-center justify-center p-8">
                                    <IconUser className="mb-2 h-10 w-10 opacity-20" />
                                    <p className="text-sm">No students found matching "{query}"</p>
                                    <p className="text-muted-foreground/60 text-xs">Try searching by ID or full name.</p>
                                </div>
                            ) : results.length === 0 && query.length < 2 ? (
                                <div className="text-muted-foreground flex flex-col items-center justify-center p-8">
                                    <IconSearch className="mb-2 h-10 w-10 opacity-20" />
                                    <p className="text-sm">Enter at least 2 characters to search.</p>
                                </div>
                            ) : (
                                <div className="space-y-2">
                                    {results.map((student) => (
                                        <div
                                            key={student.id}
                                            className="hover:bg-muted/50 flex items-center justify-between rounded-lg border p-3 transition-colors"
                                        >
                                            <div className="flex items-center gap-3">
                                                <Avatar>
                                                    <AvatarImage src={student.avatar || undefined} />
                                                    <AvatarFallback>{student.name.charAt(0)}</AvatarFallback>
                                                </Avatar>
                                                <div className="space-y-1">
                                                    <div className="flex items-center gap-2">
                                                        <p className="text-sm leading-none font-medium">{student.name}</p>
                                                        <Badge variant="outline" className="h-4 px-1.5 py-0 text-[10px]">
                                                            {student.student_id}
                                                        </Badge>
                                                    </div>
                                                    <div className="text-muted-foreground flex items-center gap-2 text-xs">
                                                        <span>{student.email || "No email"}</span>
                                                        {student.current_subjects.length > 0 && (
                                                            <HoverCard>
                                                                <HoverCardTrigger asChild>
                                                                    <span className="text-primary/80 hover:text-primary flex cursor-help items-center gap-0.5 transition-colors">
                                                                        <IconInfoCircle className="h-3 w-3" />
                                                                        {student.current_subjects.length} Subjects
                                                                    </span>
                                                                </HoverCardTrigger>
                                                                <HoverCardContent className="w-80" side="right" align="start">
                                                                    <div className="space-y-2">
                                                                        <h4 className="text-sm font-semibold">Current Subjects</h4>
                                                                        <ScrollArea className="h-48 pr-3">
                                                                            <ul className="space-y-2 text-xs">
                                                                                {student.current_subjects.map((subj, i) => (
                                                                                    <li key={i} className="flex flex-col border-b pb-1 last:border-0">
                                                                                        <span className="text-foreground font-medium">
                                                                                            {subj.code}
                                                                                        </span>
                                                                                        <span className="text-muted-foreground">{subj.title}</span>
                                                                                    </li>
                                                                                ))}
                                                                            </ul>
                                                                        </ScrollArea>
                                                                    </div>
                                                                </HoverCardContent>
                                                            </HoverCard>
                                                        )}
                                                    </div>
                                                    <div className="mt-1 flex gap-1">
                                                        {student.status.in_other_section && (
                                                            <Badge variant="destructive" className="px-1.5 py-0 text-[10px]">
                                                                Enrolled in Section {student.status.in_other_section}
                                                            </Badge>
                                                        )}
                                                        {!student.status.has_subject_enrollment && (
                                                            <Badge
                                                                variant="secondary"
                                                                className="border-amber-200 bg-amber-100 px-1.5 py-0 text-[10px] text-amber-800 hover:bg-amber-200"
                                                            >
                                                                No Subject Enrollment
                                                            </Badge>
                                                        )}
                                                    </div>
                                                </div>
                                            </div>

                                            <Button
                                                size="sm"
                                                variant={student.status.in_this_class ? "ghost" : "default"}
                                                disabled={student.status.in_this_class || addingId === student.id}
                                                onClick={() => handleAddStudent(student.id)}
                                            >
                                                {student.status.in_this_class ? (
                                                    <>
                                                        <IconCheck className="mr-2 h-4 w-4" />
                                                        Enrolled
                                                    </>
                                                ) : addingId === student.id ? (
                                                    <>
                                                        <IconLoader className="mr-2 h-4 w-4 animate-spin" />
                                                        Adding...
                                                    </>
                                                ) : (
                                                    "Add"
                                                )}
                                            </Button>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </ScrollArea>
                    </div>
                )}
            </DialogContent>
        </Dialog>
    );
}
