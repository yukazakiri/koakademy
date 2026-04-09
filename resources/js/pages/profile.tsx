import React, { useEffect, useRef, useState } from "react";

import { useTheme } from "@/hooks/use-theme";
import { Head, Link, router, useForm, usePage } from "@inertiajs/react";
import { motion } from "framer-motion";
import { ArrowLeft, Palette, Plug, QrCode, Share2, User } from "lucide-react";

import { toast } from "sonner";

import PortalLayout from "@/components/portal-layout";
import { Button } from "@/components/ui/button";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";

import { type IdCardData } from "@/components/digital-id-card";
import {
    BrowserSessions,
    ConnectionsTab,
    ExperimentalTab,
    IdCardTab,
    IntegrationsTab,
    PersonalizationTab,
    ProfileForm,
    ProfileHeader,
    ProfileSidebar,
    ProfileStats,
    SecuritySection,
    StudentContactsForm,
    StudentDetailsForm,
    StudentEducationForm,
} from "@/components/profile";

const containerVariants = {
    hidden: { opacity: 0 },
    visible: {
        opacity: 1,
        transition: {
            staggerChildren: 0.1,
        },
    },
};

const itemVariants = {
    hidden: { y: 20, opacity: 0 },
    visible: {
        y: 0,
        opacity: 1,
        transition: {
            type: "spring" as const,
            stiffness: 300,
            damping: 24,
        },
    },
};

export default function ProfilePage() {
    useTheme();
    const {
        user,
        faculty,
        student,
        sessions,
        endpoints,
        connected_accounts = {},
        id_card,
        feature_flags,
    } = usePage<{
        id_card: {
            card_data: IdCardData;
            photo_url: string | null;
            qr_code: string;
            is_valid: boolean;
        } | null;
        connected_accounts: Record<string, boolean>;
        user: {
            id: number;
            name: string;
            email: string;
            avatar_url?: string;
            role: string;
            phone?: string;
            address?: string;
            city?: string;
            state?: string;
            country?: string;
            postal_code?: string;
            bio?: string;
            website?: string;
            department?: string;
            position?: string;
            two_factor_enabled: boolean;
            email_two_factor_enabled: boolean;
            recovery_codes?: string[];
        };
        faculty?: {
            first_name?: string;
            last_name?: string;
            middle_name?: string;
            email: string;
            phone_number?: string;
            department?: string;
            office_hours?: string;
            birth_date?: string;
            address_line1?: string;
            biography?: string;
            education?: string;
            courses_taught?: string;
            photo_url?: string;
            gender?: string;
            age?: number;
        };
        student?: {
            first_name: string;
            last_name: string;
            middle_name?: string;
            email: string;
            phone?: string;
            address?: string;
            civil_status?: string;
            nationality?: string;
            religion?: string;
            emergency_contact?: string;
            birth_date?: string;
            gender?: string;
            student_id?: number;
            course?: {
                code: string;
                title: string;
            };
            contacts?: {
                emergency_contact_name?: string;
                emergency_contact_phone?: string;
                emergency_contact_relationship?: string;
                facebook?: string;
                personal_contact?: string;
            };
            education?: {
                elementary_school?: string;
                elementary_year_graduated?: string;
                high_school?: string;
                high_school_year_graduated?: string;
                senior_high_school?: string;
                senior_high_year_graduated?: string;
            };
            parents?: {
                father_name?: string;
                mother_name?: string;
            };
            formatted_academic_year?: string;
        };
        sessions: Array<{
            id: string;
            ip_address: string;
            is_current_device: boolean;
            last_active: string;
            user_agent: string;
        }>;
        feature_flags?: {
            experimental?: string[];
            experimental_available?: string[];
            developer_mode_enabled?: boolean;
        };
        endpoints?: {
            profile_update: string;
            password_update: string;
            faculty_update: string;
            student_update: string;
            passkeys: string;
            passkeys_options: string;
            two_factor_enable: string;
            two_factor_confirm: string;
            two_factor_disable: string;
            two_factor_recovery_codes: string;
            email_auth_toggle: string;
            experimental_features: string;
            browser_sessions_logout: string;
        };
    }>().props;

    const isFaculty = ["professor", "associate_professor", "assistant_professor", "instructor", "part_time_faculty"].includes(user.role);
    const isStudent = ["student", "graduate_student", "shs_student"].includes(user.role);

    const paths = {
        profile_update: endpoints?.profile_update || "/profile",
        password_update: endpoints?.password_update || "/profile/password",
        faculty_update: endpoints?.faculty_update || "/profile/faculty",
        student_update: endpoints?.student_update || "/profile/student",
        passkeys: endpoints?.passkeys || "/profile/passkeys",
        passkeys_options: endpoints?.passkeys_options || "/profile/passkeys/options",
        two_factor_enable: endpoints?.two_factor_enable || "/profile/two-factor-authentication/enable",
        two_factor_confirm: endpoints?.two_factor_confirm || "/profile/two-factor-authentication/confirm",
        two_factor_disable: endpoints?.two_factor_disable || "/profile/two-factor-authentication",
        two_factor_recovery_codes: endpoints?.two_factor_recovery_codes || "/profile/two-factor-authentication/recovery-codes",
        email_auth_toggle: endpoints?.email_auth_toggle || "/profile/email-authentication",
        experimental_features: endpoints?.experimental_features || "/profile/experimental-features",
        browser_sessions_logout: endpoints?.browser_sessions_logout || "/profile/other-browser-sessions",
    };

    const userForm = useForm({
        name: user.name || "",
        email: user.email || "",
        phone: user.phone || "",
        address: user.address || "",
        city: user.city || "",
        state: user.state || "",
        country: user.country || "",
        postal_code: user.postal_code || "",
        bio: user.bio || "",
        website: user.website || "",
        department: user.department || "",
        position: user.position || "",
        avatar: null as File | null,
    });

    const facultyForm = useForm({
        first_name: faculty?.first_name || "",
        last_name: faculty?.last_name || "",
        middle_name: faculty?.middle_name || "",
        email: faculty?.email || user.email || "",
        phone_number: faculty?.phone_number || "",
        department: faculty?.department || "",
        office_hours: faculty?.office_hours || "",
        birth_date: faculty?.birth_date || "",
        address_line1: faculty?.address_line1 || "",
        biography: faculty?.biography || "",
        education: faculty?.education || "",
        courses_taught: faculty?.courses_taught || "",
        gender: faculty?.gender || "",
        age: faculty?.age || undefined,
    });

    const studentForm = useForm({
        first_name: student?.first_name || "",
        last_name: student?.last_name || "",
        middle_name: student?.middle_name || "",
        email: student?.email || user.email || "",
        phone: student?.phone || "",
        address: student?.address || "",
        civil_status: student?.civil_status || "",
        nationality: student?.nationality || "",
        religion: student?.religion || "",
        emergency_contact: student?.emergency_contact || "",
        birth_date: student?.birth_date || "",
        gender: student?.gender || "",
        contacts: {
            emergency_contact_name: student?.contacts?.emergency_contact_name || "",
            emergency_contact_phone: student?.contacts?.emergency_contact_phone || "",
            emergency_contact_relationship: student?.contacts?.emergency_contact_relationship || "",
            facebook: student?.contacts?.facebook || "",
            personal_contact: student?.contacts?.personal_contact || "",
        },
        education: {
            elementary_school: student?.education?.elementary_school || "",
            elementary_year_graduated: student?.education?.elementary_year_graduated || "",
            high_school: student?.education?.high_school || "",
            high_school_year_graduated: student?.education?.high_school_year_graduated || "",
            senior_high_school: student?.education?.senior_high_school || "",
            senior_high_year_graduated: student?.education?.senior_high_year_graduated || "",
        },
        parents: {
            father_name: student?.parents?.father_name || "",
            mother_name: student?.parents?.mother_name || "",
        },
    });

    const avatarInputRef = useRef<HTMLInputElement | null>(null);
    const [avatarPreview, setAvatarPreview] = useState<string | undefined>(user.avatar_url);
    const [hasChanges, setHasChanges] = useState(false);

    const courses = (facultyForm.data.courses_taught || "")
        .split(",")
        .map((course) => course.trim())
        .filter(Boolean);

    const educationItems = (facultyForm.data.education || "")
        .split("\n")
        .map((item) => item.trim())
        .filter(Boolean);

    const facultyName = [facultyForm.data.first_name, facultyForm.data.middle_name, facultyForm.data.last_name].filter(Boolean).join(" ").trim();
    const campusLocation = [userForm.data.city, userForm.data.state, userForm.data.country].filter(Boolean).join(", ");
    const officeHoursDisplay = facultyForm.data.office_hours || "Set your weekly hours";
    const biographyPreview = facultyForm.data.biography || userForm.data.bio || "Share your story and expertise.";

    const completionFields = [
        userForm.data.name,
        userForm.data.email,
        userForm.data.department,
        userForm.data.position,
        userForm.data.bio,
        facultyForm.data.office_hours,
        facultyForm.data.education,
        facultyForm.data.courses_taught,
        facultyForm.data.biography,
        studentForm.data.first_name,
        studentForm.data.last_name,
        studentForm.data.address,
        studentForm.data.emergency_contact,
    ];

    const filledFields = completionFields.filter((value) => Boolean(value && `${value}`.trim())).length;
    const profileCompletion = Math.min(100, Math.round((filledFields / completionFields.length) * 100));

    useEffect(() => {
        if (!userForm.data.avatar) {
            setAvatarPreview(user.avatar_url);
        }
    }, [user.avatar_url, userForm.data.avatar]);

    useEffect(() => {
        const hasUserChanges =
            userForm.data.name !== user.name ||
            userForm.data.email !== user.email ||
            userForm.data.phone !== (user.phone || "") ||
            userForm.data.address !== (user.address || "") ||
            userForm.data.city !== (user.city || "") ||
            userForm.data.state !== (user.state || "") ||
            userForm.data.country !== (user.country || "") ||
            userForm.data.postal_code !== (user.postal_code || "") ||
            userForm.data.bio !== (user.bio || "") ||
            userForm.data.website !== (user.website || "") ||
            userForm.data.department !== (user.department || "") ||
            userForm.data.position !== (user.position || "") ||
            userForm.data.avatar !== null;

        const hasFacultyChanges =
            facultyForm.data.first_name !== (faculty?.first_name || "") ||
            facultyForm.data.last_name !== (faculty?.last_name || "") ||
            facultyForm.data.middle_name !== (faculty?.middle_name || "") ||
            facultyForm.data.email !== (faculty?.email || user.email || "") ||
            facultyForm.data.phone_number !== (faculty?.phone_number || "") ||
            facultyForm.data.department !== (faculty?.department || "") ||
            facultyForm.data.office_hours !== (faculty?.office_hours || "") ||
            facultyForm.data.birth_date !== (faculty?.birth_date || "") ||
            facultyForm.data.address_line1 !== (faculty?.address_line1 || "") ||
            facultyForm.data.biography !== (faculty?.biography || "") ||
            facultyForm.data.education !== (faculty?.education || "") ||
            facultyForm.data.courses_taught !== (faculty?.courses_taught || "") ||
            facultyForm.data.gender !== (faculty?.gender || "") ||
            facultyForm.data.age !== (faculty?.age || undefined);

        const hasStudentChanges =
            studentForm.data.first_name !== (student?.first_name || "") ||
            studentForm.data.last_name !== (student?.last_name || "") ||
            studentForm.data.middle_name !== (student?.middle_name || "") ||
            studentForm.data.email !== (student?.email || user.email || "") ||
            studentForm.data.phone !== (student?.phone || "") ||
            studentForm.data.address !== (student?.address || "") ||
            studentForm.data.civil_status !== (student?.civil_status || "") ||
            studentForm.data.nationality !== (student?.nationality || "") ||
            studentForm.data.religion !== (student?.religion || "") ||
            studentForm.data.emergency_contact !== (student?.emergency_contact || "") ||
            studentForm.data.birth_date !== (student?.birth_date || "") ||
            studentForm.data.gender !== (student?.gender || "") ||
            studentForm.data.contacts.emergency_contact_name !== (student?.contacts?.emergency_contact_name || "") ||
            studentForm.data.contacts.emergency_contact_phone !== (student?.contacts?.emergency_contact_phone || "") ||
            studentForm.data.contacts.emergency_contact_relationship !== (student?.contacts?.emergency_contact_relationship || "") ||
            studentForm.data.contacts.facebook !== (student?.contacts?.facebook || "") ||
            studentForm.data.contacts.personal_contact !== (student?.contacts?.personal_contact || "") ||
            studentForm.data.education.elementary_school !== (student?.education?.elementary_school || "") ||
            studentForm.data.education.elementary_year_graduated !== (student?.education?.elementary_year_graduated || "") ||
            studentForm.data.education.high_school !== (student?.education?.high_school || "") ||
            studentForm.data.education.high_school_year_graduated !== (student?.education?.high_school_year_graduated || "") ||
            studentForm.data.education.senior_high_school !== (student?.education?.senior_high_school || "") ||
            studentForm.data.education.senior_high_year_graduated !== (student?.education?.senior_high_year_graduated || "") ||
            studentForm.data.parents.father_name !== (student?.parents?.father_name || "") ||
            studentForm.data.parents.mother_name !== (student?.parents?.mother_name || "");

        setHasChanges(hasUserChanges || hasFacultyChanges || hasStudentChanges);
    }, [userForm.data, facultyForm.data, studentForm.data, user, faculty, student]);

    useEffect(() => {
        if (typeof window === "undefined") return;
        const hash = window.location.hash;
        if (!hash) return;
        const targetId = hash.replace("#", "");
        const target = document.getElementById(targetId);
        if (target) {
            target.scrollIntoView({ behavior: "smooth", block: "start" });
        }
    }, []);

    const handleAvatarSelect = (event: React.ChangeEvent<HTMLInputElement>) => {
        const file = event.target.files?.[0];
        if (!file) return;
        userForm.setData("avatar", file);
        setAvatarPreview(URL.createObjectURL(file));
        setHasChanges(true);
        toast.success("Photo selected! Save your changes to update.");
    };

    const triggerAvatarPicker = () => {
        avatarInputRef.current?.click();
    };

    const handleUserSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        userForm.put(paths.profile_update, {
            forceFormData: true,
            onSuccess: () => {
                toast.success("Profile updated successfully!");
                setHasChanges(false);
                userForm.setData("avatar", null);
                router.visit(window.location.pathname, {
                    replace: true,
                    only: ["user"],
                });
            },
            onError: () => {
                toast.error("Failed to update profile. Please check your input.");
            },
        });
    };

    const handleStudentSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        studentForm.put(paths.student_update, {
            onSuccess: () => {
                toast.success("Student information updated successfully!");
                setHasChanges(false);
            },
            onError: () => {
                toast.error("Failed to update student information. Please check your input.");
            },
        });
    };

    const handleSaveClick = () => {
        const userSection = document.getElementById("profile-form");
        const facultySection = document.getElementById("faculty-form");
        const studentSection = document.getElementById("student-form");
        if (userSection) {
            userSection.scrollIntoView({ behavior: "smooth", block: "start" });
        } else if (facultySection) {
            facultySection.scrollIntoView({ behavior: "smooth", block: "start" });
        } else if (studentSection) {
            studentSection.scrollIntoView({ behavior: "smooth", block: "start" });
        }
    };

    const experimentalAvailable = feature_flags?.experimental_available || [];
    const developerModeEnabled = feature_flags?.developer_mode_enabled ?? false;

    return (
        <>
            <Head title="Profile Settings" />
            <PortalLayout
                user={{
                    name: user.name,
                    email: user.email,
                    avatar: user.avatar_url ?? null,
                    role: user.role,
                }}
            >
                <div className="space-y-8">
                    <div className="flex items-center gap-4">
                        <Link href="/dashboard">
                            <Button variant="outline" size="icon">
                                <ArrowLeft className="h-4 w-4" />
                            </Button>
                        </Link>
                        <div>
                            <h1 className="text-3xl font-bold tracking-tight">Profile Settings</h1>
                            <p className="text-muted-foreground">Manage your account, faculty details, and preferences</p>
                        </div>
                    </div>

                    <ProfileHeader
                        user={user}
                        student={student}
                        isStudent={isStudent}
                        isFaculty={isFaculty}
                        profileCompletion={profileCompletion}
                        avatarPreview={avatarPreview}
                        avatarInputRef={avatarInputRef}
                        hasChanges={hasChanges}
                        department={userForm.data.department}
                        position={userForm.data.position}
                        campusLocation={campusLocation}
                        facultyName={facultyName}
                        onAvatarSelect={handleAvatarSelect}
                        onTriggerAvatarPicker={triggerAvatarPicker}
                        onSaveClick={handleSaveClick}
                    />

                    <ProfileStats
                        isFaculty={isFaculty}
                        isStudent={isStudent}
                        coursesCount={courses.length}
                        officeHoursDisplay={officeHoursDisplay}
                        profileCompletion={profileCompletion}
                        educationItemsCount={educationItems.length}
                    />

                    <Tabs defaultValue="profile" className="flex w-full flex-col gap-8 lg:flex-row">
                        <aside className="shrink-0 lg:w-64">
                            <TabsList className="flex h-auto w-full flex-col items-stretch justify-start gap-2 bg-transparent p-0">
                                <TabsTrigger value="profile" className="data-[state=active]:bg-muted justify-start px-3 py-2">
                                    <User className="mr-2 h-4 w-4" />
                                    Profile
                                </TabsTrigger>
                                {id_card && (isFaculty || isStudent) && (
                                    <TabsTrigger value="id-card" className="data-[state=active]:bg-muted justify-start px-3 py-2">
                                        <QrCode className="mr-2 h-4 w-4" />
                                        Digital ID Card
                                    </TabsTrigger>
                                )}
                                <TabsTrigger value="accounts" className="data-[state=active]:bg-muted justify-start px-3 py-2">
                                    <Plug className="mr-2 h-4 w-4" />
                                    Accounts & Security
                                </TabsTrigger>
                                <TabsTrigger value="personalization" className="data-[state=active]:bg-muted justify-start px-3 py-2">
                                    <Palette className="mr-2 h-4 w-4" />
                                    Personalization
                                </TabsTrigger>
                                {experimentalAvailable.length > 0 && (
                                    <TabsTrigger value="experimental" className="data-[state=active]:bg-muted justify-start px-3 py-2">
                                        <Plug className="mr-2 h-4 w-4" />
                                        Experimental
                                    </TabsTrigger>
                                )}
                                <TabsTrigger value="connections" className="data-[state=active]:bg-muted justify-start px-3 py-2">
                                    <Share2 className="mr-2 h-4 w-4" />
                                    Connections
                                </TabsTrigger>
                                <TabsTrigger value="integrations" className="data-[state=active]:bg-muted justify-start px-3 py-2">
                                    <Plug className="mr-2 h-4 w-4" />
                                    Integrations
                                </TabsTrigger>
                            </TabsList>
                        </aside>

                        <div className="flex-1 lg:max-w-4xl">
                            <TabsContent value="profile" className="mt-0 outline-none">
                                <motion.div variants={containerVariants} initial="hidden" animate="visible" className="grid gap-8 lg:grid-cols-3">
                                    <motion.div variants={itemVariants} className="space-y-6 lg:col-span-2">
                                        <ProfileForm
                                            userForm={{
                                                data: userForm.data,
                                                setData: userForm.setData,
                                                errors: userForm.errors,
                                                processing: userForm.processing,
                                            }}
                                            facultyForm={
                                                isFaculty
                                                    ? {
                                                          data: facultyForm.data,
                                                          setData: facultyForm.setData,
                                                          errors: facultyForm.errors,
                                                      }
                                                    : undefined
                                            }
                                            onSubmit={handleUserSubmit}
                                        />

                                        {isStudent && (
                                            <>
                                                <StudentDetailsForm
                                                    studentForm={{
                                                        data: studentForm.data,
                                                        setData: studentForm.setData,
                                                        errors: studentForm.errors,
                                                        processing: studentForm.processing,
                                                    }}
                                                    onSubmit={handleStudentSubmit}
                                                />
                                                <StudentContactsForm
                                                    studentForm={{
                                                        data: studentForm.data,
                                                        setData: studentForm.setData,
                                                        processing: studentForm.processing,
                                                    }}
                                                    onSubmit={handleStudentSubmit}
                                                />
                                                <StudentEducationForm
                                                    studentForm={{
                                                        data: studentForm.data,
                                                        setData: studentForm.setData,
                                                        processing: studentForm.processing,
                                                    }}
                                                    onSubmit={handleStudentSubmit}
                                                />
                                            </>
                                        )}
                                    </motion.div>

                                    <motion.div variants={itemVariants}>
                                        <ProfileSidebar
                                            user={user}
                                            avatarPreview={avatarPreview}
                                            position={userForm.data.position}
                                            biographyPreview={biographyPreview}
                                            isFaculty={isFaculty}
                                        />
                                    </motion.div>
                                </motion.div>
                            </TabsContent>

                            {id_card && (isFaculty || isStudent) && (
                                <TabsContent value="id-card" className="mt-0 outline-none">
                                    <motion.div variants={containerVariants} initial="hidden" animate="visible">
                                        <motion.div variants={itemVariants}>
                                            <IdCardTab idCard={id_card} isFaculty={isFaculty} />
                                        </motion.div>
                                    </motion.div>
                                </TabsContent>
                            )}

                            <TabsContent value="accounts" className="mt-0 outline-none">
                                <div className="grid gap-6 lg:grid-cols-2">
                                    <SecuritySection
                                        isFaculty={isFaculty}
                                        isStudent={isStudent}
                                        user={user}
                                        paths={paths}
                                        developerModeEnabled={developerModeEnabled}
                                    />
                                    <BrowserSessions sessions={sessions} paths={paths} />
                                </div>
                            </TabsContent>

                            <TabsContent value="personalization" className="mt-0 outline-none">
                                <PersonalizationTab />
                            </TabsContent>

                            {experimentalAvailable.length > 0 && (
                                <TabsContent value="experimental" className="mt-0 outline-none">
                                    <ExperimentalTab
                                        experimentalAvailable={experimentalAvailable}
                                        experimentalFeatures={feature_flags?.experimental || []}
                                        paths={paths}
                                    />
                                </TabsContent>
                            )}

                            <TabsContent value="connections" className="mt-0 outline-none">
                                <ConnectionsTab connectedAccounts={connected_accounts} />
                            </TabsContent>

                            <TabsContent value="integrations" className="mt-0 outline-none">
                                <IntegrationsTab connectedAccounts={connected_accounts} />
                            </TabsContent>
                        </div>
                    </Tabs>
                </div>
            </PortalLayout>
        </>
    );
}
