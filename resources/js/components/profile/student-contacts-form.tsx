import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Separator } from "@/components/ui/separator";
import { Save, User } from "lucide-react";

interface StudentContactsFormProps {
    studentForm: {
        data: {
            parents: {
                father_name: string;
                mother_name: string;
            };
            contacts: {
                emergency_contact_name: string;
                emergency_contact_phone: string;
                emergency_contact_relationship: string;
                facebook: string;
                personal_contact: string;
            };
        };
        setData: (key: string, value: any) => void;
        processing: boolean;
    };
    onSubmit: (e: React.FormEvent) => void;
}

export function StudentContactsForm({ studentForm, onSubmit }: StudentContactsFormProps) {
    const updateParents = (key: string, value: string) => {
        studentForm.setData("parents", {
            ...studentForm.data.parents,
            [key]: value,
        });
    };

    const updateContacts = (key: string, value: string) => {
        studentForm.setData("contacts", {
            ...studentForm.data.contacts,
            [key]: value,
        });
    };

    return (
        <Card className="mt-6">
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <User className="h-5 w-5" />
                    Parent & Contact Information
                </CardTitle>
                <CardDescription>Update your contact and family details</CardDescription>
            </CardHeader>
            <CardContent>
                <form onSubmit={onSubmit} className="space-y-6">
                    <div className="grid gap-6 md:grid-cols-2">
                        <div className="space-y-2">
                            <Label htmlFor="father_name">Father&apos;s Name</Label>
                            <Input
                                id="father_name"
                                value={studentForm.data.parents.father_name}
                                onChange={(e) => updateParents("father_name", e.target.value)}
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="mother_name">Mother&apos;s Name</Label>
                            <Input
                                id="mother_name"
                                value={studentForm.data.parents.mother_name}
                                onChange={(e) => updateParents("mother_name", e.target.value)}
                            />
                        </div>
                    </div>

                    <Separator />

                    <div className="grid gap-6 md:grid-cols-2">
                        <div className="space-y-2">
                            <Label htmlFor="emergency_contact_name">Emergency Contact Name</Label>
                            <Input
                                id="emergency_contact_name"
                                value={studentForm.data.contacts.emergency_contact_name}
                                onChange={(e) => updateContacts("emergency_contact_name", e.target.value)}
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="emergency_contact_phone">Emergency Contact Phone</Label>
                            <Input
                                id="emergency_contact_phone"
                                value={studentForm.data.contacts.emergency_contact_phone}
                                onChange={(e) => updateContacts("emergency_contact_phone", e.target.value)}
                            />
                        </div>
                        <div className="space-y-2 md:col-span-2">
                            <Label htmlFor="emergency_contact_relationship">Emergency Contact Relationship</Label>
                            <Input
                                id="emergency_contact_relationship"
                                value={studentForm.data.contacts.emergency_contact_relationship}
                                onChange={(e) => updateContacts("emergency_contact_relationship", e.target.value)}
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="personal_contact">Personal Contact</Label>
                            <Input
                                id="personal_contact"
                                value={studentForm.data.contacts.personal_contact}
                                onChange={(e) => updateContacts("personal_contact", e.target.value)}
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="facebook">Facebook Link</Label>
                            <Input
                                id="facebook"
                                value={studentForm.data.contacts.facebook}
                                onChange={(e) => updateContacts("facebook", e.target.value)}
                                placeholder="https://facebook.com/..."
                            />
                        </div>
                    </div>

                    <div className="flex justify-end">
                        <Button type="submit" disabled={studentForm.processing}>
                            <Save className="mr-2 h-4 w-4" />
                            {studentForm.processing ? "Saving..." : "Save Contact Info"}
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    );
}
