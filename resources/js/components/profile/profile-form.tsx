import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import { Edit3, Save } from "lucide-react";

interface ProfileFormProps {
    userForm: {
        data: {
            name: string;
            email: string;
            phone: string;
            website: string;
            bio: string;
            department: string;
        };
        setData: (key: string, value: string) => void;
        errors: Record<string, string>;
        processing: boolean;
    };
    facultyForm?: {
        data: {
            office_hours: string;
            birth_date: string;
            gender: string;
        };
        setData: (key: string, value: string) => void;
        errors: Record<string, string>;
    };
    onSubmit: (e: React.FormEvent) => void;
}

export function ProfileForm({ userForm, facultyForm, onSubmit }: ProfileFormProps) {
    return (
        <Card id="profile-form">
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <Edit3 className="h-5 w-5" />
                    Profile Information
                </CardTitle>
                <CardDescription>Update your basic account details</CardDescription>
            </CardHeader>
            <CardContent>
                <form id="profile-form" onSubmit={onSubmit} className="space-y-6">
                    <div className="grid gap-6 md:grid-cols-2">
                        <div className="space-y-2">
                            <Label htmlFor="name">Full Name *</Label>
                            <Input id="name" value={userForm.data.name} onChange={(e) => userForm.setData("name", e.target.value)} required />
                            {userForm.errors.name && <p className="text-destructive text-sm">{userForm.errors.name}</p>}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="email">Email Address *</Label>
                            <Input
                                id="email"
                                type="email"
                                value={userForm.data.email}
                                onChange={(e) => userForm.setData("email", e.target.value)}
                                required
                            />
                            {userForm.errors.email && <p className="text-destructive text-sm">{userForm.errors.email}</p>}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="phone">Phone Number</Label>
                            <Input
                                id="phone"
                                type="tel"
                                value={userForm.data.phone}
                                onChange={(e) => userForm.setData("phone", e.target.value)}
                                placeholder="+63 XXX XXX XXXX"
                            />
                            {userForm.errors.phone && <p className="text-destructive text-sm">{userForm.errors.phone}</p>}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="website">Website</Label>
                            <Input
                                id="website"
                                type="url"
                                value={userForm.data.website}
                                onChange={(e) => userForm.setData("website", e.target.value)}
                                placeholder="https://example.com"
                            />
                            {userForm.errors.website && <p className="text-destructive text-sm">{userForm.errors.website}</p>}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="department">Department</Label>
                            <Input
                                id="department"
                                value={userForm.data.department}
                                onChange={(e) => userForm.setData("department", e.target.value)}
                                placeholder="e.g., Computer Science"
                            />
                            {userForm.errors.department && <p className="text-destructive text-sm">{userForm.errors.department}</p>}
                        </div>

                        {facultyForm && (
                            <>
                                <div className="space-y-2">
                                    <Label htmlFor="office_hours">Office Hours</Label>
                                    <Input
                                        id="office_hours"
                                        value={facultyForm.data.office_hours}
                                        onChange={(e) => facultyForm.setData("office_hours", e.target.value)}
                                        placeholder="e.g., Mon-Fri 9AM-5PM"
                                    />
                                    {facultyForm.errors.office_hours && <p className="text-destructive text-sm">{facultyForm.errors.office_hours}</p>}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="birth_date">Birth Date</Label>
                                    <Input
                                        id="birth_date"
                                        type="date"
                                        value={facultyForm.data.birth_date}
                                        onChange={(e) => facultyForm.setData("birth_date", e.target.value)}
                                    />
                                    {facultyForm.errors.birth_date && <p className="text-destructive text-sm">{facultyForm.errors.birth_date}</p>}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="gender">Gender</Label>
                                    <Select value={facultyForm.data.gender} onValueChange={(value) => facultyForm.setData("gender", value)}>
                                        <SelectTrigger id="gender">
                                            <SelectValue placeholder="Select Gender" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="male">Male</SelectItem>
                                            <SelectItem value="female">Female</SelectItem>
                                            <SelectItem value="other">Other</SelectItem>
                                            <SelectItem value="prefer_not_to_say">Prefer not to say</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    {facultyForm.errors.gender && <p className="text-destructive text-sm">{facultyForm.errors.gender}</p>}
                                </div>
                            </>
                        )}
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="bio">Bio</Label>
                        <Textarea
                            id="bio"
                            value={userForm.data.bio}
                            onChange={(e) => userForm.setData("bio", e.target.value)}
                            placeholder="Share a quick overview"
                            rows={4}
                        />
                        {userForm.errors.bio && <p className="text-destructive text-sm">{userForm.errors.bio}</p>}
                    </div>

                    <div className="flex justify-end">
                        <Button type="submit" disabled={userForm.processing}>
                            <Save className="mr-2 h-4 w-4" />
                            {userForm.processing ? "Saving..." : "Save Changes"}
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    );
}
