import AdminLayout from "@/components/administrators/admin-layout";
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Separator } from "@/components/ui/separator";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Textarea } from "@/components/ui/textarea";
import type { User } from "@/types/user";
import { Head, Link, useForm } from "@inertiajs/react";
import { Camera, UserPlus } from "lucide-react";
import { FormEventHandler, useMemo, useState } from "react";
import { route } from "ziggy-js";

type Option = { value: string; label: string };

interface FacultyCreateProps {
    user: User;
    defaults: {
        faculty_id_number: string;
        status: string;
    };
    options: {
        departments: string[];
        statuses: Option[];
        genders: Option[];
    };
}

function statusLabel(status: string): string {
    if (status === "active") return "Active";
    if (status === "inactive") return "Inactive";
    if (status === "on_leave") return "On Leave";
    return status;
}

export default function AdministratorFacultyCreate({ user, defaults, options }: FacultyCreateProps) {
    const { data, setData, post, processing, errors } = useForm({
        faculty_id_number: defaults.faculty_id_number ?? "",
        first_name: "",
        last_name: "",
        middle_name: "",
        email: "",
        department: "",
        status: defaults.status ?? "active",
        gender: "",
        birth_date: "",
        age: "",
        phone_number: "",
        office_hours: "",
        address_line1: "",
        biography: "",
        education: "",
        courses_taught: "",
        photo: null as File | null,
    });

    const [photoPreview, setPhotoPreview] = useState<string | null>(null);

    const departmentSuggestions = useMemo(() => {
        return options.departments.filter((dept) => dept && dept.trim().length > 0);
    }, [options.departments]);

    const displayName = useMemo(() => {
        const parts = [data.first_name, data.middle_name, data.last_name].map((p) => p.trim()).filter(Boolean);

        return parts.length ? parts.join(" ") : "New Faculty";
    }, [data.first_name, data.last_name, data.middle_name]);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route("administrators.faculties.store"), {
            forceFormData: true,
        });
    };

    return (
        <AdminLayout user={user} title="Create Faculty">
            <Head title="Administrators • Faculties • Create" />

            <div className="grid gap-6 lg:grid-cols-3">
                <div className="lg:col-span-2">
                    <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 className="text-2xl font-bold tracking-tight">Add Faculty</h2>
                            <p className="text-muted-foreground">Fill in the basics first — you can add details later.</p>
                        </div>

                        <Button variant="outline" asChild>
                            <Link href={route("administrators.faculties.index")}>Back</Link>
                        </Button>
                    </div>

                    <form onSubmit={submit} className="mt-4">
                        <Tabs defaultValue="basics" className="w-full">
                            <TabsList className="grid w-full grid-cols-3">
                                <TabsTrigger value="basics">Basics</TabsTrigger>
                                <TabsTrigger value="details">Details</TabsTrigger>
                                <TabsTrigger value="notes">Notes</TabsTrigger>
                            </TabsList>

                            <TabsContent value="basics" className="mt-4">
                                <Card>
                                    <CardHeader>
                                        <CardTitle>Basics</CardTitle>
                                        <CardDescription>These fields help you identify the faculty quickly.</CardDescription>
                                    </CardHeader>
                                    <CardContent className="grid gap-4 sm:grid-cols-2">
                                        <div className="space-y-2">
                                            <Label htmlFor="faculty_id_number">Faculty ID Number</Label>
                                            <Input
                                                id="faculty_id_number"
                                                value={data.faculty_id_number}
                                                onChange={(e) => setData("faculty_id_number", e.target.value)}
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
                                                onChange={(e) => setData("email", e.target.value)}
                                                required
                                            />
                                            {errors.email ? <p className="text-sm text-red-500">{errors.email}</p> : null}
                                        </div>

                                        <div className="space-y-2">
                                            <Label htmlFor="first_name">First Name</Label>
                                            <Input
                                                id="first_name"
                                                value={data.first_name}
                                                onChange={(e) => setData("first_name", e.target.value)}
                                                required
                                            />
                                            {errors.first_name ? <p className="text-sm text-red-500">{errors.first_name}</p> : null}
                                        </div>

                                        <div className="space-y-2">
                                            <Label htmlFor="last_name">Last Name</Label>
                                            <Input
                                                id="last_name"
                                                value={data.last_name}
                                                onChange={(e) => setData("last_name", e.target.value)}
                                                required
                                            />
                                            {errors.last_name ? <p className="text-sm text-red-500">{errors.last_name}</p> : null}
                                        </div>

                                        <div className="space-y-2 sm:col-span-2">
                                            <Label htmlFor="middle_name">Middle Name</Label>
                                            <Input
                                                id="middle_name"
                                                value={data.middle_name}
                                                onChange={(e) => setData("middle_name", e.target.value)}
                                                placeholder="Optional"
                                            />
                                            {errors.middle_name ? <p className="text-sm text-red-500">{errors.middle_name}</p> : null}
                                        </div>

                                        <Separator className="sm:col-span-2" />

                                        <div className="space-y-2">
                                            <Label>Status</Label>
                                            <Select value={data.status} onValueChange={(val) => setData("status", val)}>
                                                <SelectTrigger>
                                                    <SelectValue placeholder="Select status" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {options.statuses.map((opt) => (
                                                        <SelectItem key={opt.value} value={opt.value}>
                                                            {opt.label}
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
                                                list="departments"
                                                value={data.department}
                                                onChange={(e) => setData("department", e.target.value)}
                                                placeholder="Optional"
                                            />
                                            <datalist id="departments">
                                                {departmentSuggestions.map((dept) => (
                                                    <option key={dept} value={dept} />
                                                ))}
                                            </datalist>
                                            {errors.department ? <p className="text-sm text-red-500">{errors.department}</p> : null}
                                        </div>

                                        <div className="space-y-2 sm:col-span-2">
                                            <Label htmlFor="photo">Profile photo</Label>
                                            <Input
                                                id="photo"
                                                type="file"
                                                accept="image/*"
                                                onChange={(e) => {
                                                    const file = e.target.files?.[0] || null;
                                                    setData("photo", file);
                                                    setPhotoPreview(file ? URL.createObjectURL(file) : null);
                                                }}
                                            />
                                            {errors.photo ? <p className="text-sm text-red-500">{errors.photo}</p> : null}
                                        </div>
                                    </CardContent>
                                </Card>
                            </TabsContent>

                            <TabsContent value="details" className="mt-4">
                                <Card>
                                    <CardHeader>
                                        <CardTitle>Details</CardTitle>
                                        <CardDescription>Optional info — helpful for records and communication.</CardDescription>
                                    </CardHeader>
                                    <CardContent className="grid gap-4 sm:grid-cols-2">
                                        <div className="space-y-2">
                                            <Label htmlFor="phone_number">Phone Number</Label>
                                            <Input
                                                id="phone_number"
                                                value={data.phone_number}
                                                onChange={(e) => setData("phone_number", e.target.value)}
                                                placeholder="Optional"
                                            />
                                            {errors.phone_number ? <p className="text-sm text-red-500">{errors.phone_number}</p> : null}
                                        </div>

                                        <div className="space-y-2">
                                            <Label>Gender</Label>
                                            <Select
                                                value={data.gender || "none"}
                                                onValueChange={(val) => setData("gender", val === "none" ? "" : val)}
                                            >
                                                <SelectTrigger>
                                                    <SelectValue placeholder="Optional" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="none">—</SelectItem>
                                                    {options.genders.map((opt) => (
                                                        <SelectItem key={opt.value} value={opt.value}>
                                                            {opt.label}
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
                                                onChange={(e) => setData("birth_date", e.target.value)}
                                            />
                                            {errors.birth_date ? <p className="text-sm text-red-500">{errors.birth_date}</p> : null}
                                        </div>

                                        <div className="space-y-2">
                                            <Label htmlFor="age">Age</Label>
                                            <Input
                                                id="age"
                                                type="number"
                                                value={data.age}
                                                onChange={(e) => setData("age", e.target.value)}
                                                placeholder="Optional"
                                            />
                                            {errors.age ? <p className="text-sm text-red-500">{errors.age}</p> : null}
                                        </div>

                                        <div className="space-y-2 sm:col-span-2">
                                            <Label htmlFor="office_hours">Office Hours</Label>
                                            <Textarea
                                                id="office_hours"
                                                value={data.office_hours}
                                                onChange={(e) => setData("office_hours", e.target.value)}
                                                placeholder="Optional"
                                                rows={3}
                                            />
                                            {errors.office_hours ? <p className="text-sm text-red-500">{errors.office_hours}</p> : null}
                                        </div>

                                        <div className="space-y-2 sm:col-span-2">
                                            <Label htmlFor="address_line1">Address</Label>
                                            <Textarea
                                                id="address_line1"
                                                value={data.address_line1}
                                                onChange={(e) => setData("address_line1", e.target.value)}
                                                placeholder="Optional"
                                                rows={3}
                                            />
                                            {errors.address_line1 ? <p className="text-sm text-red-500">{errors.address_line1}</p> : null}
                                        </div>
                                    </CardContent>
                                </Card>
                            </TabsContent>

                            <TabsContent value="notes" className="mt-4">
                                <Card>
                                    <CardHeader>
                                        <CardTitle>Notes</CardTitle>
                                        <CardDescription>Use this for background info and teaching notes.</CardDescription>
                                    </CardHeader>
                                    <CardContent className="grid gap-4">
                                        <div className="space-y-2">
                                            <Label htmlFor="biography">Biography</Label>
                                            <Textarea
                                                id="biography"
                                                value={data.biography}
                                                onChange={(e) => setData("biography", e.target.value)}
                                                placeholder="Optional"
                                                rows={4}
                                            />
                                            {errors.biography ? <p className="text-sm text-red-500">{errors.biography}</p> : null}
                                        </div>

                                        <div className="space-y-2">
                                            <Label htmlFor="education">Education</Label>
                                            <Textarea
                                                id="education"
                                                value={data.education}
                                                onChange={(e) => setData("education", e.target.value)}
                                                placeholder="Optional"
                                                rows={3}
                                            />
                                            {errors.education ? <p className="text-sm text-red-500">{errors.education}</p> : null}
                                        </div>

                                        <div className="space-y-2">
                                            <Label htmlFor="courses_taught">Courses Taught</Label>
                                            <Textarea
                                                id="courses_taught"
                                                value={data.courses_taught}
                                                onChange={(e) => setData("courses_taught", e.target.value)}
                                                placeholder="Optional"
                                                rows={3}
                                            />
                                            {errors.courses_taught ? <p className="text-sm text-red-500">{errors.courses_taught}</p> : null}
                                        </div>
                                    </CardContent>
                                </Card>
                            </TabsContent>
                        </Tabs>

                        <div className="mt-6 flex justify-end gap-3">
                            <Button variant="outline" asChild>
                                <Link href={route("administrators.faculties.index")}>Cancel</Link>
                            </Button>
                            <Button type="submit" disabled={processing}>
                                <UserPlus className="mr-2 h-4 w-4" />
                                {processing ? "Creating…" : "Create Faculty"}
                            </Button>
                        </div>
                    </form>
                </div>

                <div className="lg:col-span-1">
                    <Card className="sticky top-6">
                        <CardHeader>
                            <CardTitle>Preview</CardTitle>
                            <CardDescription>This is what the profile will look like in the list.</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="flex items-center gap-3">
                                <Avatar className="h-12 w-12">
                                    <AvatarImage src={photoPreview ?? undefined} alt={displayName} />
                                    <AvatarFallback>{(displayName || "?").slice(0, 2).toUpperCase()}</AvatarFallback>
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

                            <div className="flex flex-wrap gap-2">
                                <Badge variant="outline">{statusLabel(data.status)}</Badge>
                                {data.department ? (
                                    <Badge variant="secondary">{data.department}</Badge>
                                ) : (
                                    <Badge variant="secondary">No department</Badge>
                                )}
                            </div>

                            <Separator />

                            <div className="text-muted-foreground space-y-1 text-sm">
                                <div>
                                    <span className="text-foreground font-medium">Faculty ID:</span> {data.faculty_id_number || "—"}
                                </div>
                                <div>
                                    <span className="text-foreground font-medium">Phone:</span> {data.phone_number || "—"}
                                </div>
                            </div>

                            <div className="rounded-md border p-3 text-sm">
                                <div className="font-medium">Tip</div>
                                <div className="text-muted-foreground">If you’re in a hurry, fill only the Basics tab and click Create.</div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AdminLayout>
    );
}
