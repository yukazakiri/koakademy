import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { GraduationCap, Save } from "lucide-react";

interface StudentDetailsFormProps {
    studentForm: {
        data: {
            first_name: string;
            middle_name: string;
            last_name: string;
            email: string;
            phone: string;
            birth_date: string;
            gender: string;
            civil_status: string;
            nationality: string;
            religion: string;
            address: string;
            emergency_contact: string;
        };
        setData: (key: string, value: string) => void;
        errors: Record<string, string>;
        processing: boolean;
    };
    onSubmit: (e: React.FormEvent) => void;
}

export function StudentDetailsForm({ studentForm, onSubmit }: StudentDetailsFormProps) {
    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <GraduationCap className="h-5 w-5" />
                    Student Details
                </CardTitle>
                <CardDescription>Additional information for student records</CardDescription>
            </CardHeader>
            <CardContent>
                <form id="student-form" onSubmit={onSubmit} className="space-y-6">
                    <div className="grid gap-6 md:grid-cols-2">
                        <div className="space-y-2">
                            <Label htmlFor="student_first_name">First Name *</Label>
                            <Input
                                id="student_first_name"
                                value={studentForm.data.first_name}
                                onChange={(e) => studentForm.setData("first_name", e.target.value)}
                                required
                            />
                            {studentForm.errors.first_name && <p className="text-destructive text-sm">{studentForm.errors.first_name}</p>}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="student_middle_name">Middle Name</Label>
                            <Input
                                id="student_middle_name"
                                value={studentForm.data.middle_name}
                                onChange={(e) => studentForm.setData("middle_name", e.target.value)}
                                placeholder="Optional"
                            />
                            {studentForm.errors.middle_name && <p className="text-destructive text-sm">{studentForm.errors.middle_name}</p>}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="student_last_name">Last Name *</Label>
                            <Input
                                id="student_last_name"
                                value={studentForm.data.last_name}
                                onChange={(e) => studentForm.setData("last_name", e.target.value)}
                                required
                            />
                            {studentForm.errors.last_name && <p className="text-destructive text-sm">{studentForm.errors.last_name}</p>}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="student_email">Student Email *</Label>
                            <Input
                                id="student_email"
                                type="email"
                                value={studentForm.data.email}
                                onChange={(e) => studentForm.setData("email", e.target.value)}
                                required
                            />
                            {studentForm.errors.email && <p className="text-destructive text-sm">{studentForm.errors.email}</p>}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="student_phone">Phone Number</Label>
                            <Input
                                id="student_phone"
                                type="tel"
                                value={studentForm.data.phone}
                                onChange={(e) => studentForm.setData("phone", e.target.value)}
                                placeholder="+63 XXX XXX XXXX"
                            />
                            {studentForm.errors.phone && <p className="text-destructive text-sm">{studentForm.errors.phone}</p>}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="student_birth_date">Birth Date</Label>
                            <Input
                                id="student_birth_date"
                                type="date"
                                value={studentForm.data.birth_date}
                                onChange={(e) => studentForm.setData("birth_date", e.target.value)}
                            />
                            {studentForm.errors.birth_date && <p className="text-destructive text-sm">{studentForm.errors.birth_date}</p>}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="student_gender">Gender</Label>
                            <Select value={studentForm.data.gender} onValueChange={(value) => studentForm.setData("gender", value)}>
                                <SelectTrigger id="student_gender">
                                    <SelectValue placeholder="Select Gender" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="male">Male</SelectItem>
                                    <SelectItem value="female">Female</SelectItem>
                                    <SelectItem value="other">Other</SelectItem>
                                    <SelectItem value="prefer_not_to_say">Prefer not to say</SelectItem>
                                </SelectContent>
                            </Select>
                            {studentForm.errors.gender && <p className="text-destructive text-sm">{studentForm.errors.gender}</p>}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="civil_status">Civil Status</Label>
                            <Select value={studentForm.data.civil_status} onValueChange={(value) => studentForm.setData("civil_status", value)}>
                                <SelectTrigger id="civil_status">
                                    <SelectValue placeholder="Select Status" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="single">Single</SelectItem>
                                    <SelectItem value="married">Married</SelectItem>
                                    <SelectItem value="widowed">Widowed</SelectItem>
                                    <SelectItem value="separated">Separated</SelectItem>
                                </SelectContent>
                            </Select>
                            {studentForm.errors.civil_status && <p className="text-destructive text-sm">{studentForm.errors.civil_status}</p>}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="nationality">Nationality</Label>
                            <Input
                                id="nationality"
                                value={studentForm.data.nationality}
                                onChange={(e) => studentForm.setData("nationality", e.target.value)}
                                placeholder="e.g. Filipino"
                            />
                            {studentForm.errors.nationality && <p className="text-destructive text-sm">{studentForm.errors.nationality}</p>}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="religion">Religion</Label>
                            <Input
                                id="religion"
                                value={studentForm.data.religion}
                                onChange={(e) => studentForm.setData("religion", e.target.value)}
                                placeholder="Optional"
                            />
                            {studentForm.errors.religion && <p className="text-destructive text-sm">{studentForm.errors.religion}</p>}
                        </div>
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="student_address">Address</Label>
                        <Input
                            id="student_address"
                            value={studentForm.data.address}
                            onChange={(e) => studentForm.setData("address", e.target.value)}
                            placeholder="Complete Home Address"
                        />
                        {studentForm.errors.address && <p className="text-destructive text-sm">{studentForm.errors.address}</p>}
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="emergency_contact">Emergency Contact</Label>
                        <Input
                            id="emergency_contact"
                            value={studentForm.data.emergency_contact}
                            onChange={(e) => studentForm.setData("emergency_contact", e.target.value)}
                            placeholder="Name and Phone Number"
                        />
                        {studentForm.errors.emergency_contact && <p className="text-destructive text-sm">{studentForm.errors.emergency_contact}</p>}
                    </div>

                    <div className="flex justify-end">
                        <Button type="submit" disabled={studentForm.processing}>
                            <Save className="mr-2 h-4 w-4" />
                            {studentForm.processing ? "Saving..." : "Save Student Details"}
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    );
}
