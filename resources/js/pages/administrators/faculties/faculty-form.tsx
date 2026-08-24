import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Progress } from "@/components/ui/progress";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Separator } from "@/components/ui/separator";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Textarea } from "@/components/ui/textarea";
import { Link, useForm } from "@inertiajs/react";
import { Camera, Save, UserPlus } from "lucide-react";
import type { FormEventHandler } from "react";
import { useMemo, useState } from "react";
import { route } from "ziggy-js";

type Option = { value: string; label: string };

export type FacultyFormPayload = {
    id?: string;
    faculty_id_number: string | null;
    first_name: string;
    middle_name: string | null;
    last_name: string;
    email: string;
    phone_number: string | null;
    department: string | null;
    position: string | null;
    status: string | null;
    gender: string | null;
    birth_date: string | null;
    date_employed: string | null;
    age: number | null;
    office_hours: string | null;
    address_line1: string | null;
    biography: string | null;
    education: string | null;
    courses_taught: string | null;
    photo_url: string | null;
};

type FacultyFormProps = {
    mode: "create" | "edit";
    defaults?: Partial<FacultyFormPayload>;
    faculty?: FacultyFormPayload;
    options: {
        departments: string[];
        statuses: Option[];
        genders: Option[];
    };
};

function statusLabel(status: string): string {
    if (status === "active") return "Active";
    if (status === "inactive") return "Inactive";
    if (status === "on_leave") return "On Leave";

    return status || "Unknown";
}

function blankToString(value: string | number | null | undefined): string {
    return value === null || value === undefined ? "" : String(value);
}

export function FacultyForm({ mode, defaults, faculty, options }: FacultyFormProps) {
    const isEdit = mode === "edit";
    const profile = faculty ?? defaults;

    const { data, setData, post, put, processing, errors, progress } = useForm({
        faculty_id_number: blankToString(profile?.faculty_id_number),
        first_name: blankToString(profile?.first_name),
        last_name: blankToString(profile?.last_name),
        middle_name: blankToString(profile?.middle_name),
        email: blankToString(profile?.email),
        department: blankToString(profile?.department),
        position: blankToString(profile?.position),
        status: blankToString(profile?.status) || "active",
        gender: blankToString(profile?.gender),
        birth_date: blankToString(profile?.birth_date),
        date_employed: blankToString(profile?.date_employed),
        age: blankToString(profile?.age),
        phone_number: blankToString(profile?.phone_number),
        office_hours: blankToString(profile?.office_hours),
        address_line1: blankToString(profile?.address_line1),
        biography: blankToString(profile?.biography),
        education: blankToString(profile?.education),
        courses_taught: blankToString(profile?.courses_taught),
        photo: null as File | null,
    });

    const [photoPreview, setPhotoPreview] = useState<string | null>(profile?.photo_url ?? null);

    const departmentSuggestions = useMemo(() => options.departments.filter((department) => department.trim().length > 0), [options.departments]);
    const displayName = useMemo(() => {
        const parts = [data.first_name, data.middle_name, data.last_name].map((part) => part.trim()).filter(Boolean);

        return parts.length ? parts.join(" ") : isEdit ? "Faculty" : "New Faculty";
    }, [data.first_name, data.last_name, data.middle_name, isEdit]);

    const completion = useMemo(() => {
        const fields = [data.faculty_id_number, data.first_name, data.last_name, data.email, data.department, data.phone_number, data.office_hours];
        const filled = fields.filter((value) => value.trim().length > 0).length;

        return Math.round((filled / fields.length) * 100);
    }, [data.department, data.email, data.faculty_id_number, data.first_name, data.last_name, data.office_hours, data.phone_number]);

    const submit: FormEventHandler = (event) => {
        event.preventDefault();

        if (isEdit && faculty?.id) {
            put(route("administrators.faculties.update", faculty.id), { forceFormData: true });
            return;
        }

        post(route("administrators.faculties.store"), { forceFormData: true });
    };

    const backHref = isEdit && faculty?.id ? route("administrators.faculties.show", faculty.id) : route("administrators.faculties.index");

    return (
        <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
            <form onSubmit={submit} className="space-y-4">
                <Tabs defaultValue="basics" className="w-full">
                    <TabsList className="grid w-full grid-cols-4">
                        <TabsTrigger value="basics">Basics</TabsTrigger>
                        <TabsTrigger value="details">Details</TabsTrigger>
                        <TabsTrigger value="records">Records</TabsTrigger>
                        <TabsTrigger value="review">Review</TabsTrigger>
                    </TabsList>

                    <TabsContent value="basics" className="mt-4">
                        <Card>
                            <CardHeader>
                                <CardTitle>Identity</CardTitle>
                                <CardDescription>Core information used across faculty records, classes, and portal access.</CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-4 sm:grid-cols-2">
                                <div className="space-y-2">
                                    <Label htmlFor="faculty_id_number">Faculty ID Number</Label>
                                    <Input
                                        id="faculty_id_number"
                                        value={data.faculty_id_number}
                                        onChange={(event) => setData("faculty_id_number", event.target.value)}
                                        required
                                    />
                                    {errors.faculty_id_number ? <p className="text-sm text-red-500">{errors.faculty_id_number}</p> : null}
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="email">Email</Label>
                                    <Input
                                        id="email"
                                        type="email"
                                        value={data.email}
                                        onChange={(event) => setData("email", event.target.value)}
                                        required
                                    />
                                    {errors.email ? <p className="text-sm text-red-500">{errors.email}</p> : null}
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="first_name">First Name</Label>
                                    <Input
                                        id="first_name"
                                        value={data.first_name}
                                        onChange={(event) => setData("first_name", event.target.value)}
                                        required
                                    />
                                    {errors.first_name ? <p className="text-sm text-red-500">{errors.first_name}</p> : null}
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="last_name">Last Name</Label>
                                    <Input
                                        id="last_name"
                                        value={data.last_name}
                                        onChange={(event) => setData("last_name", event.target.value)}
                                        required
                                    />
                                    {errors.last_name ? <p className="text-sm text-red-500">{errors.last_name}</p> : null}
                                </div>
                                <div className="space-y-2 sm:col-span-2">
                                    <Label htmlFor="middle_name">Middle Name</Label>
                                    <Input
                                        id="middle_name"
                                        value={data.middle_name}
                                        onChange={(event) => setData("middle_name", event.target.value)}
                                    />
                                    {errors.middle_name ? <p className="text-sm text-red-500">{errors.middle_name}</p> : null}
                                </div>
                                <Separator className="sm:col-span-2" />
                                <div className="space-y-2">
                                    <Label>Status</Label>
                                    <Select value={data.status} onValueChange={(value) => setData("status", value ?? "")}>
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select status" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {options.statuses.map((option) => (
                                                <SelectItem key={option.value} value={option.value}>
                                                    {option.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {errors.status ? <p className="text-sm text-red-500">{errors.status}</p> : null}
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="department">Department</Label>
                                    <Input
                                        id="department"
                                        list="faculty-departments"
                                        value={data.department}
                                        onChange={(event) => setData("department", event.target.value)}
                                    />
                                    <datalist id="faculty-departments">
                                        {departmentSuggestions.map((department) => (
                                            <option key={department} value={department} />
                                        ))}
                                    </datalist>
                                    {errors.department ? <p className="text-sm text-red-500">{errors.department}</p> : null}
                                </div>
                                <div className="space-y-2 sm:col-span-2">
                                    <Label htmlFor="position">Job Title</Label>
                                    <Input
                                        id="position"
                                        value={data.position}
                                        onChange={(event) => setData("position", event.target.value)}
                                        placeholder="Professor, Dean, Registrar, etc."
                                    />
                                    {errors.position ? <p className="text-sm text-red-500">{errors.position}</p> : null}
                                </div>
                                <div className="space-y-2 sm:col-span-2">
                                    <Label htmlFor="photo">{isEdit ? "Update photo" : "Profile photo"}</Label>
                                    <Input
                                        id="photo"
                                        type="file"
                                        accept="image/*"
                                        onChange={(event) => {
                                            const file = event.target.files?.[0] ?? null;
                                            setData("photo", file);
                                            setPhotoPreview(file ? URL.createObjectURL(file) : (profile?.photo_url ?? null));
                                        }}
                                    />
                                    {progress ? <Progress value={progress.percentage} className="h-2" /> : null}
                                    {errors.photo ? <p className="text-sm text-red-500">{errors.photo}</p> : null}
                                </div>
                            </CardContent>
                        </Card>
                    </TabsContent>

                    <TabsContent value="details" className="mt-4">
                        <Card>
                            <CardHeader>
                                <CardTitle>Contact and Availability</CardTitle>
                                <CardDescription>Useful for departmental coordination and student-facing schedules.</CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-4 sm:grid-cols-2">
                                <div className="space-y-2">
                                    <Label htmlFor="phone_number">Phone Number</Label>
                                    <Input
                                        id="phone_number"
                                        value={data.phone_number}
                                        onChange={(event) => setData("phone_number", event.target.value)}
                                    />
                                    {errors.phone_number ? <p className="text-sm text-red-500">{errors.phone_number}</p> : null}
                                </div>
                                <div className="space-y-2">
                                    <Label>Gender</Label>
                                    <Select
                                        value={data.gender || "none"}
                                        onValueChange={(value) => setData("gender", value === "none" || value === null ? "" : value)}
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Optional" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="none">Not specified</SelectItem>
                                            {options.genders.map((option) => (
                                                <SelectItem key={option.value} value={option.value}>
                                                    {option.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {errors.gender ? <p className="text-sm text-red-500">{errors.gender}</p> : null}
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="birth_date">Birth Date</Label>
                                    <Input
                                        id="birth_date"
                                        type="date"
                                        value={data.birth_date}
                                        onChange={(event) => setData("birth_date", event.target.value)}
                                    />
                                    {errors.birth_date ? <p className="text-sm text-red-500">{errors.birth_date}</p> : null}
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="date_employed">Date Employed</Label>
                                    <Input
                                        id="date_employed"
                                        type="date"
                                        value={data.date_employed}
                                        onChange={(event) => setData("date_employed", event.target.value)}
                                    />
                                    {errors.date_employed ? <p className="text-sm text-red-500">{errors.date_employed}</p> : null}
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="age">Age</Label>
                                    <Input id="age" type="number" value={data.age} onChange={(event) => setData("age", event.target.value)} />
                                    {errors.age ? <p className="text-sm text-red-500">{errors.age}</p> : null}
                                </div>
                                <div className="space-y-2 sm:col-span-2">
                                    <Label htmlFor="office_hours">Office Hours</Label>
                                    <Textarea
                                        id="office_hours"
                                        value={data.office_hours}
                                        onChange={(event) => setData("office_hours", event.target.value)}
                                        rows={3}
                                    />
                                    {errors.office_hours ? <p className="text-sm text-red-500">{errors.office_hours}</p> : null}
                                </div>
                                <div className="space-y-2 sm:col-span-2">
                                    <Label htmlFor="address_line1">Address</Label>
                                    <Textarea
                                        id="address_line1"
                                        value={data.address_line1}
                                        onChange={(event) => setData("address_line1", event.target.value)}
                                        rows={3}
                                    />
                                    {errors.address_line1 ? <p className="text-sm text-red-500">{errors.address_line1}</p> : null}
                                </div>
                            </CardContent>
                        </Card>
                    </TabsContent>

                    <TabsContent value="records" className="mt-4">
                        <Card>
                            <CardHeader>
                                <CardTitle>Profile Records</CardTitle>
                                <CardDescription>Academic notes that help departments evaluate teaching fit and advising readiness.</CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-4">
                                <div className="space-y-2">
                                    <Label htmlFor="biography">Biography</Label>
                                    <Textarea
                                        id="biography"
                                        value={data.biography}
                                        onChange={(event) => setData("biography", event.target.value)}
                                        rows={4}
                                    />
                                    {errors.biography ? <p className="text-sm text-red-500">{errors.biography}</p> : null}
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="education">Education</Label>
                                    <Textarea
                                        id="education"
                                        value={data.education}
                                        onChange={(event) => setData("education", event.target.value)}
                                        rows={3}
                                    />
                                    {errors.education ? <p className="text-sm text-red-500">{errors.education}</p> : null}
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="courses_taught">Courses Taught</Label>
                                    <Textarea
                                        id="courses_taught"
                                        value={data.courses_taught}
                                        onChange={(event) => setData("courses_taught", event.target.value)}
                                        rows={3}
                                    />
                                    {errors.courses_taught ? <p className="text-sm text-red-500">{errors.courses_taught}</p> : null}
                                </div>
                            </CardContent>
                        </Card>
                    </TabsContent>

                    <TabsContent value="review" className="mt-4">
                        <Card>
                            <CardHeader>
                                <CardTitle>Final Review</CardTitle>
                                <CardDescription>Confirm the operational fields before saving.</CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-4 sm:grid-cols-2">
                                <ReviewItem label="Name" value={displayName} />
                                <ReviewItem label="Email" value={data.email || "Missing"} />
                                <ReviewItem label="Faculty ID" value={data.faculty_id_number || "Missing"} />
                                <ReviewItem label="Department" value={data.department || "No department"} />
                                <ReviewItem label="Status" value={statusLabel(data.status)} />
                                <ReviewItem
                                    label="Portal readiness"
                                    value={data.email && data.faculty_id_number ? "Ready for portal account" : "Needs email and faculty ID"}
                                />
                            </CardContent>
                        </Card>
                    </TabsContent>
                </Tabs>

                <div className="flex justify-end gap-3">
                    <Button variant="outline" asChild>
                        <Link href={backHref}>Cancel</Link>
                    </Button>
                    <Button type="submit" disabled={processing}>
                        {isEdit ? <Save className="mr-2 h-4 w-4" /> : <UserPlus className="mr-2 h-4 w-4" />}
                        {processing ? "Saving..." : isEdit ? "Save Changes" : "Create Faculty"}
                    </Button>
                </div>
            </form>

            <Card className="h-fit lg:sticky lg:top-6">
                <CardHeader>
                    <CardTitle>Review Panel</CardTitle>
                    <CardDescription>Live profile preview and completion check.</CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                    <div className="flex items-center gap-3">
                        <Avatar className="h-12 w-12">
                            <AvatarImage src={photoPreview ?? undefined} alt={displayName} />
                            <AvatarFallback>{displayName.slice(0, 2).toUpperCase()}</AvatarFallback>
                        </Avatar>
                        <div className="min-w-0">
                            <div className="truncate font-medium">{displayName}</div>
                            <div className="text-muted-foreground truncate text-sm">{data.email || "No email yet"}</div>
                        </div>
                        <Button
                            type="button"
                            variant="outline"
                            size="icon"
                            className="ml-auto"
                            onClick={() => document.getElementById("photo")?.click()}
                        >
                            <Camera className="h-4 w-4" />
                        </Button>
                    </div>
                    <div className="space-y-2">
                        <div className="flex items-center justify-between text-sm">
                            <span className="font-medium">Profile completion</span>
                            <span className="text-muted-foreground">{completion}%</span>
                        </div>
                        <Progress value={completion} className="h-2" />
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Badge variant="outline">{statusLabel(data.status)}</Badge>
                        <Badge variant="secondary">{data.department || "No department"}</Badge>
                        {data.faculty_id_number ? <Badge variant="secondary">ID ready</Badge> : <Badge variant="outline">Missing ID</Badge>}
                    </div>
                    <Separator />
                    <div className="text-muted-foreground space-y-1 text-sm">
                        <div>
                            <span className="text-foreground font-medium">Phone:</span> {data.phone_number || "None"}
                        </div>
                        <div>
                            <span className="text-foreground font-medium">Office hours:</span> {data.office_hours ? "Listed" : "Not listed"}
                        </div>
                        <div>
                            <span className="text-foreground font-medium">Teaching profile:</span>{" "}
                            {data.education || data.courses_taught ? "Has records" : "Needs records"}
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    );
}

function ReviewItem({ label, value }: { label: string; value: string }) {
    return (
        <div className="rounded-md border p-3">
            <div className="text-muted-foreground text-xs">{label}</div>
            <div className="mt-1 text-sm font-medium">{value}</div>
        </div>
    );
}
