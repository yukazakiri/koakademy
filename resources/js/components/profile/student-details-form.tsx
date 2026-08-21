import { formatPersonName } from "@/components/profile/profile-form-utils";
import { AutocompleteFieldInput } from "@/components/ui/autocomplete-field-input";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { InputGroup, InputGroupAddon, InputGroupInput } from "@/components/ui/input-group";
import { Label } from "@/components/ui/label";
import { PhoneInput } from "@/components/ui/phone-input";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { GraduationCap, Mail, MapPin, Save } from "lucide-react";

type StudentDetailsData = {
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
    personal_info: {
        birthplace: string;
        citizenship: string;
        weight: string;
        height: string;
        current_address: string;
        permanent_address: string;
    };
};

interface StudentDetailsFormProps {
    studentForm: {
        data: StudentDetailsData;
        setData: <Key extends keyof StudentDetailsData>(key: Key, value: StudentDetailsData[Key]) => void;
        errors: Record<string, string>;
        processing: boolean;
    };
    onSubmit: (event: React.FormEvent) => void;
    defaultCountryCode?: string;
}

const civilStatuses = [
    ["single", "Single"],
    ["married", "Married"],
    ["widowed", "Widowed"],
    ["separated", "Separated"],
    ["annulled", "Annulled"],
] as const;

const nationalities = [
    "Filipino",
    "American",
    "Australian",
    "British",
    "Canadian",
    "Chinese",
    "Indian",
    "Indonesian",
    "Japanese",
    "Korean",
    "Malaysian",
    "Singaporean",
    "Thai",
    "Vietnamese",
];

const religions = [
    "Roman Catholic",
    "Islam",
    "Iglesia ni Cristo",
    "Born Again Christian",
    "Seventh-day Adventist",
    "Protestant",
    "Evangelical Christian",
    "Buddhist",
    "Hindu",
    "None",
    "Prefer not to say",
];

function FieldError({ message }: { message?: string }) {
    return message ? <p className="text-destructive text-sm">{message}</p> : null;
}

export function StudentDetailsForm({ studentForm, onSubmit, defaultCountryCode }: StudentDetailsFormProps) {
    const student = studentForm.data;
    const updatePersonalInfo = (key: keyof StudentDetailsData["personal_info"], value: string) => {
        studentForm.setData("personal_info", { ...student.personal_info, [key]: value });
    };
    const knownCivilStatus = civilStatuses.find(([value]) => value === student.civil_status.toLowerCase());
    const selectedCivilStatus = knownCivilStatus?.[0] ?? student.civil_status;
    const civilStatusOptions: ReadonlyArray<readonly [string, string]> =
        knownCivilStatus || !student.civil_status ? civilStatuses : [[student.civil_status, student.civil_status], ...civilStatuses];

    return (
        <Card id="student-information" className="border-border/60 bg-card/75 scroll-mt-24 rounded-lg shadow-sm">
            <CardHeader className="pb-4">
                <CardTitle className="flex items-center gap-2">
                    <GraduationCap className="h-5 w-5" />
                    Student Details
                </CardTitle>
                <CardDescription>Personal information used for your student record.</CardDescription>
            </CardHeader>
            <CardContent>
                <form id="student-form" onSubmit={onSubmit} className="space-y-5">
                    <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                        {(
                            [
                                ["first_name", "First name *", "given-name", true],
                                ["middle_name", "Middle name", "additional-name", false],
                                ["last_name", "Last name *", "family-name", true],
                            ] as const
                        ).map(([key, label, autoComplete, required]) => (
                            <div key={key} className="space-y-2">
                                <Label htmlFor={`student_${key}`}>{label}</Label>
                                <Input
                                    id={`student_${key}`}
                                    autoComplete={autoComplete}
                                    value={student[key]}
                                    onChange={(event) => studentForm.setData(key, event.target.value)}
                                    onBlur={(event) => studentForm.setData(key, formatPersonName(event.target.value))}
                                    aria-invalid={Boolean(studentForm.errors[key])}
                                    required={required}
                                />
                                <FieldError message={studentForm.errors[key]} />
                            </div>
                        ))}

                        <div className="space-y-2 lg:col-span-2">
                            <Label htmlFor="student_email">Student email *</Label>
                            <InputGroup className="h-9">
                                <InputGroupAddon>
                                    <Mail />
                                </InputGroupAddon>
                                <InputGroupInput
                                    id="student_email"
                                    type="email"
                                    inputMode="email"
                                    autoComplete="email"
                                    value={student.email}
                                    onChange={(event) => studentForm.setData("email", event.target.value)}
                                    aria-invalid={Boolean(studentForm.errors.email)}
                                    required
                                />
                            </InputGroup>
                            <FieldError message={studentForm.errors.email} />
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="student_phone">Phone number</Label>
                            <PhoneInput
                                id="student_phone"
                                value={student.phone}
                                onChange={(value) => studentForm.setData("phone", value)}
                                defaultCountryCode={defaultCountryCode}
                            />
                            <FieldError message={studentForm.errors.phone} />
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="student_birth_date">Birth date</Label>
                            <Input
                                id="student_birth_date"
                                type="date"
                                autoComplete="bday"
                                max={new Date().toISOString().slice(0, 10)}
                                value={student.birth_date}
                                onChange={(event) => studentForm.setData("birth_date", event.target.value)}
                                aria-invalid={Boolean(studentForm.errors.birth_date)}
                            />
                            <FieldError message={studentForm.errors.birth_date} />
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="student_gender">Gender</Label>
                            <Select value={student.gender} onValueChange={(value) => studentForm.setData("gender", value)}>
                                <SelectTrigger id="student_gender" aria-invalid={Boolean(studentForm.errors.gender)}>
                                    <SelectValue placeholder="Select gender" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="male">Male</SelectItem>
                                    <SelectItem value="female">Female</SelectItem>
                                    <SelectItem value="other">Other</SelectItem>
                                    <SelectItem value="prefer_not_to_say">Prefer not to say</SelectItem>
                                </SelectContent>
                            </Select>
                            <FieldError message={studentForm.errors.gender} />
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="civil_status">Civil status</Label>
                            <Select value={selectedCivilStatus} onValueChange={(value) => studentForm.setData("civil_status", value)}>
                                <SelectTrigger id="civil_status" aria-invalid={Boolean(studentForm.errors.civil_status)}>
                                    <SelectValue placeholder="Select status" />
                                </SelectTrigger>
                                <SelectContent>
                                    {civilStatusOptions.map(([value, label]) => (
                                        <SelectItem key={value} value={value}>
                                            {label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <FieldError message={studentForm.errors.civil_status} />
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="nationality">Nationality</Label>
                            <AutocompleteFieldInput
                                id="nationality"
                                value={student.nationality}
                                onChange={(value) => studentForm.setData("nationality", value)}
                                fieldName="nationality"
                                options={nationalities}
                                placeholder="Search or enter nationality"
                                aria-invalid={Boolean(studentForm.errors.nationality)}
                            />
                            <FieldError message={studentForm.errors.nationality} />
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="religion">Religion</Label>
                            <AutocompleteFieldInput
                                id="religion"
                                value={student.religion}
                                onChange={(value) => studentForm.setData("religion", value)}
                                fieldName="religion"
                                options={religions}
                                placeholder="Search or enter religion"
                                aria-invalid={Boolean(studentForm.errors.religion)}
                            />
                            <FieldError message={studentForm.errors.religion} />
                        </div>

                        <div className="space-y-2 md:col-span-2 lg:col-span-3">
                            <Label htmlFor="student_address">Home address</Label>
                            <InputGroup className="h-9">
                                <InputGroupAddon>
                                    <MapPin />
                                </InputGroupAddon>
                                <InputGroupInput
                                    id="student_address"
                                    autoComplete="street-address"
                                    value={student.address}
                                    onChange={(event) => studentForm.setData("address", event.target.value)}
                                    aria-invalid={Boolean(studentForm.errors.address)}
                                    placeholder="House number, street, barangay, city, and province"
                                />
                            </InputGroup>
                            <FieldError message={studentForm.errors.address} />
                        </div>

                        <div className="border-border/70 border-t pt-5 md:col-span-2 lg:col-span-3">
                            <p className="text-muted-foreground text-xs font-semibold tracking-wide uppercase">Personal record</p>
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="student_birthplace">Birthplace</Label>
                            <Input
                                id="student_birthplace"
                                value={student.personal_info.birthplace}
                                onChange={(event) => updatePersonalInfo("birthplace", event.target.value)}
                                placeholder="City, province"
                                aria-invalid={Boolean(studentForm.errors["personal_info.birthplace"])}
                            />
                            <FieldError message={studentForm.errors["personal_info.birthplace"]} />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="student_citizenship">Citizenship</Label>
                            <Input
                                id="student_citizenship"
                                value={student.personal_info.citizenship}
                                onChange={(event) => updatePersonalInfo("citizenship", event.target.value)}
                                placeholder="e.g. Filipino"
                                aria-invalid={Boolean(studentForm.errors["personal_info.citizenship"])}
                            />
                            <FieldError message={studentForm.errors["personal_info.citizenship"]} />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="student_height">Height (cm)</Label>
                            <Input
                                id="student_height"
                                type="number"
                                min="1"
                                step="0.01"
                                value={student.personal_info.height}
                                onChange={(event) => updatePersonalInfo("height", event.target.value)}
                                aria-invalid={Boolean(studentForm.errors["personal_info.height"])}
                            />
                            <FieldError message={studentForm.errors["personal_info.height"]} />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="student_weight">Weight (kg)</Label>
                            <Input
                                id="student_weight"
                                type="number"
                                min="1"
                                step="0.01"
                                value={student.personal_info.weight}
                                onChange={(event) => updatePersonalInfo("weight", event.target.value)}
                                aria-invalid={Boolean(studentForm.errors["personal_info.weight"])}
                            />
                            <FieldError message={studentForm.errors["personal_info.weight"]} />
                        </div>
                        <div className="space-y-2 md:col-span-2 lg:col-span-3">
                            <Label htmlFor="student_current_address">Current address</Label>
                            <Input
                                id="student_current_address"
                                value={student.personal_info.current_address}
                                onChange={(event) => updatePersonalInfo("current_address", event.target.value)}
                                placeholder="Where you currently reside"
                                aria-invalid={Boolean(studentForm.errors["personal_info.current_address"])}
                            />
                            <FieldError message={studentForm.errors["personal_info.current_address"]} />
                        </div>
                        <div className="space-y-2 md:col-span-2 lg:col-span-3">
                            <Label htmlFor="student_permanent_address">Permanent address</Label>
                            <Input
                                id="student_permanent_address"
                                value={student.personal_info.permanent_address}
                                onChange={(event) => updatePersonalInfo("permanent_address", event.target.value)}
                                placeholder="Home address if different from current address"
                                aria-invalid={Boolean(studentForm.errors["personal_info.permanent_address"])}
                            />
                            <FieldError message={studentForm.errors["personal_info.permanent_address"]} />
                        </div>

                        <div className="space-y-2 md:col-span-2 lg:col-span-3">
                            <Label htmlFor="emergency_contact">Additional emergency contact details</Label>
                            <Input
                                id="emergency_contact"
                                value={student.emergency_contact}
                                onChange={(event) => studentForm.setData("emergency_contact", event.target.value)}
                                aria-invalid={Boolean(studentForm.errors.emergency_contact)}
                                placeholder="Name, relationship, or alternate contact details"
                            />
                            <FieldError message={studentForm.errors.emergency_contact} />
                        </div>
                    </div>

                    <div className="flex justify-end">
                        <Button type="submit" disabled={studentForm.processing} className="rounded-lg">
                            <Save className="mr-2 h-4 w-4" />
                            {studentForm.processing ? "Saving..." : "Save student details"}
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    );
}
