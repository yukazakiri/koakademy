import { formatPersonName } from "@/components/profile/profile-form-utils";
import { AutocompleteFieldInput } from "@/components/ui/autocomplete-field-input";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { InputGroup, InputGroupAddon, InputGroupInput } from "@/components/ui/input-group";
import { Label } from "@/components/ui/label";
import { PhoneInput } from "@/components/ui/phone-input";
import { Separator } from "@/components/ui/separator";
import { Link2, Save, User } from "lucide-react";

type ParentData = {
    father_name: string;
    father_occupation: string;
    father_contact: string;
    father_email: string;
    mother_name: string;
    mother_occupation: string;
    mother_contact: string;
    mother_email: string;
    guardian_name: string;
    guardian_relationship: string;
    guardian_contact: string;
    guardian_email: string;
    family_address: string;
};

type ContactData = {
    emergency_contact_name: string;
    emergency_contact_phone: string;
    emergency_contact_relationship: string;
    facebook: string;
    twitter: string;
    instagram: string;
    linkedin: string;
    personal_contact: string;
};

interface StudentContactsFormProps {
    studentForm: {
        data: StudentContactFormData;
        setData: <Key extends keyof StudentContactFormData>(key: Key, value: StudentContactFormData[Key]) => void;
        errors: Record<string, string>;
        processing: boolean;
    };
    onSubmit: (event: React.FormEvent) => void;
    defaultCountryCode?: string;
}

type StudentContactFormData = {
    parents: ParentData;
    contacts: ContactData;
};

const relationships = ["Mother", "Father", "Parent", "Spouse", "Sibling", "Guardian", "Relative", "Friend"];

function FieldError({ message }: { message?: string }) {
    return message ? <p className="text-destructive text-sm">{message}</p> : null;
}

export function StudentContactsForm({ studentForm, onSubmit, defaultCountryCode }: StudentContactsFormProps) {
    const errorFor = (key: string) => studentForm.errors[key];

    const updateParents = (key: keyof ParentData, value: string) => {
        studentForm.setData("parents", {
            ...studentForm.data.parents,
            [key]: value,
        });
    };

    const updateContacts = (key: keyof ContactData, value: string) => {
        studentForm.setData("contacts", {
            ...studentForm.data.contacts,
            [key]: value,
        });
    };

    return (
        <Card id="student-contacts" className="border-border/60 bg-card/75 scroll-mt-24 rounded-lg shadow-sm">
            <CardHeader className="pb-4">
                <CardTitle className="flex items-center gap-2">
                    <User className="h-5 w-5" />
                    Parent & Contact Information
                </CardTitle>
                <CardDescription>Family and emergency contact details used by the school.</CardDescription>
            </CardHeader>
            <CardContent>
                <form onSubmit={onSubmit} className="space-y-6">
                    <div className="grid gap-4 md:grid-cols-2">
                        <div className="space-y-2">
                            <Label htmlFor="father_name">Father&apos;s name</Label>
                            <Input
                                id="father_name"
                                autoComplete="off"
                                value={studentForm.data.parents.father_name}
                                onChange={(event) => updateParents("father_name", event.target.value)}
                                onBlur={(event) => updateParents("father_name", formatPersonName(event.target.value))}
                                aria-invalid={Boolean(errorFor("parents.father_name"))}
                            />
                            <FieldError message={errorFor("parents.father_name")} />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="mother_name">Mother&apos;s name</Label>
                            <Input
                                id="mother_name"
                                autoComplete="off"
                                value={studentForm.data.parents.mother_name}
                                onChange={(event) => updateParents("mother_name", event.target.value)}
                                onBlur={(event) => updateParents("mother_name", formatPersonName(event.target.value))}
                                aria-invalid={Boolean(errorFor("parents.mother_name"))}
                            />
                            <FieldError message={errorFor("parents.mother_name")} />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="father_occupation">Father&apos;s occupation</Label>
                            <Input
                                id="father_occupation"
                                value={studentForm.data.parents.father_occupation}
                                onChange={(event) => updateParents("father_occupation", event.target.value)}
                            />
                            <FieldError message={errorFor("parents.father_occupation")} />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="father_contact">Father&apos;s contact</Label>
                            <PhoneInput
                                id="father_contact"
                                value={studentForm.data.parents.father_contact}
                                onChange={(value) => updateParents("father_contact", value)}
                                defaultCountryCode={defaultCountryCode}
                            />
                            <FieldError message={errorFor("parents.father_contact")} />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="father_email">Father&apos;s email</Label>
                            <Input
                                id="father_email"
                                type="email"
                                value={studentForm.data.parents.father_email}
                                onChange={(event) => updateParents("father_email", event.target.value)}
                            />
                            <FieldError message={errorFor("parents.father_email")} />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="mother_occupation">Mother&apos;s occupation</Label>
                            <Input
                                id="mother_occupation"
                                value={studentForm.data.parents.mother_occupation}
                                onChange={(event) => updateParents("mother_occupation", event.target.value)}
                            />
                            <FieldError message={errorFor("parents.mother_occupation")} />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="mother_contact">Mother&apos;s contact</Label>
                            <PhoneInput
                                id="mother_contact"
                                value={studentForm.data.parents.mother_contact}
                                onChange={(value) => updateParents("mother_contact", value)}
                                defaultCountryCode={defaultCountryCode}
                            />
                            <FieldError message={errorFor("parents.mother_contact")} />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="mother_email">Mother&apos;s email</Label>
                            <Input
                                id="mother_email"
                                type="email"
                                value={studentForm.data.parents.mother_email}
                                onChange={(event) => updateParents("mother_email", event.target.value)}
                            />
                            <FieldError message={errorFor("parents.mother_email")} />
                        </div>
                    </div>

                    <Separator />

                    <section className="space-y-4">
                        <div>
                            <h3 className="text-sm font-semibold">Guardian</h3>
                            <p className="text-muted-foreground mt-1 text-sm">The school will use this person when it needs to reach your family.</p>
                        </div>
                        <div className="grid gap-4 md:grid-cols-2">
                            {(
                                [
                                    ["guardian_name", "Guardian name", "text"],
                                    ["guardian_relationship", "Relationship", "text"],
                                    ["guardian_email", "Guardian email", "email"],
                                ] as const
                            ).map(([key, label, type]) => (
                                <div key={key} className="space-y-2">
                                    <Label htmlFor={key}>{label}</Label>
                                    <Input
                                        id={key}
                                        type={type}
                                        value={studentForm.data.parents[key]}
                                        onChange={(event) => updateParents(key, event.target.value)}
                                        aria-invalid={Boolean(errorFor(`parents.${key}`))}
                                    />
                                    <FieldError message={errorFor(`parents.${key}`)} />
                                </div>
                            ))}
                            <div className="space-y-2">
                                <Label htmlFor="guardian_contact">Guardian contact</Label>
                                <PhoneInput
                                    id="guardian_contact"
                                    value={studentForm.data.parents.guardian_contact}
                                    onChange={(value) => updateParents("guardian_contact", value)}
                                    defaultCountryCode={defaultCountryCode}
                                />
                                <FieldError message={errorFor("parents.guardian_contact")} />
                            </div>
                            <div className="space-y-2 md:col-span-2">
                                <Label htmlFor="family_address">Family address</Label>
                                <Input
                                    id="family_address"
                                    value={studentForm.data.parents.family_address}
                                    onChange={(event) => updateParents("family_address", event.target.value)}
                                    aria-invalid={Boolean(errorFor("parents.family_address"))}
                                />
                                <FieldError message={errorFor("parents.family_address")} />
                            </div>
                        </div>
                    </section>

                    <Separator />

                    <div className="grid gap-4 md:grid-cols-2">
                        <div className="space-y-2">
                            <Label htmlFor="emergency_contact_name">Emergency contact name</Label>
                            <Input
                                id="emergency_contact_name"
                                value={studentForm.data.contacts.emergency_contact_name}
                                onChange={(event) => updateContacts("emergency_contact_name", event.target.value)}
                                onBlur={(event) => updateContacts("emergency_contact_name", formatPersonName(event.target.value))}
                                aria-invalid={Boolean(errorFor("contacts.emergency_contact_name"))}
                            />
                            <FieldError message={errorFor("contacts.emergency_contact_name")} />
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="emergency_contact_phone">Emergency contact phone</Label>
                            <PhoneInput
                                id="emergency_contact_phone"
                                value={studentForm.data.contacts.emergency_contact_phone}
                                onChange={(value) => updateContacts("emergency_contact_phone", value)}
                                defaultCountryCode={defaultCountryCode}
                            />
                            <FieldError message={errorFor("contacts.emergency_contact_phone")} />
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="emergency_contact_relationship">Relationship</Label>
                            <AutocompleteFieldInput
                                id="emergency_contact_relationship"
                                value={studentForm.data.contacts.emergency_contact_relationship}
                                onChange={(value) => updateContacts("emergency_contact_relationship", value)}
                                fieldName="emergency_contact_relationship"
                                options={relationships}
                                placeholder="Search or enter relationship"
                                aria-invalid={Boolean(errorFor("contacts.emergency_contact_relationship"))}
                            />
                            <FieldError message={errorFor("contacts.emergency_contact_relationship")} />
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="personal_contact">Personal phone</Label>
                            <PhoneInput
                                id="personal_contact"
                                value={studentForm.data.contacts.personal_contact}
                                onChange={(value) => updateContacts("personal_contact", value)}
                                defaultCountryCode={defaultCountryCode}
                            />
                            <FieldError message={errorFor("contacts.personal_contact")} />
                        </div>

                        <div className="space-y-2 md:col-span-2">
                            <Label htmlFor="facebook">Facebook profile</Label>
                            <InputGroup className="h-9">
                                <InputGroupAddon>
                                    <Link2 />
                                </InputGroupAddon>
                                <InputGroupInput
                                    id="facebook"
                                    type="url"
                                    inputMode="url"
                                    autoComplete="url"
                                    value={studentForm.data.contacts.facebook}
                                    onChange={(event) => updateContacts("facebook", event.target.value)}
                                    aria-invalid={Boolean(errorFor("contacts.facebook"))}
                                    placeholder="https://facebook.com/..."
                                />
                            </InputGroup>
                            <FieldError message={errorFor("contacts.facebook")} />
                        </div>
                        {(
                            [
                                ["twitter", "X / Twitter profile"],
                                ["instagram", "Instagram profile"],
                                ["linkedin", "LinkedIn profile"],
                            ] as const
                        ).map(([key, label]) => (
                            <div key={key} className="space-y-2">
                                <Label htmlFor={key}>{label}</Label>
                                <Input
                                    id={key}
                                    type="url"
                                    value={studentForm.data.contacts[key]}
                                    onChange={(event) => updateContacts(key, event.target.value)}
                                    aria-invalid={Boolean(errorFor(`contacts.${key}`))}
                                />
                                <FieldError message={errorFor(`contacts.${key}`)} />
                            </div>
                        ))}
                    </div>

                    <div className="flex justify-end">
                        <Button type="submit" disabled={studentForm.processing} className="rounded-lg">
                            <Save className="mr-2 h-4 w-4" />
                            {studentForm.processing ? "Saving..." : "Save contact information"}
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    );
}
