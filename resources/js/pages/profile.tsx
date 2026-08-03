import React, { useEffect, useRef, useState } from "react";

import { useTheme } from "@/hooks/use-theme";
import { Head, Link, router, useForm, usePage } from "@inertiajs/react";
import { motion } from "framer-motion";
import { ArrowLeft, BookOpen, Contact, GraduationCap, Palette, Plug, QrCode, Share2, User } from "lucide-react";

import { toast } from "sonner";

import PortalLayout from "@/components/portal-layout";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
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

type ConnectedAccount = {
    id: number;
    provider: string;
    provider_id: string;
    name?: string | null;
    nickname?: string | null;
    email?: string | null;
    avatar_path?: string | null;
    created_at?: string | null;
};

type ConnectedAccountsPayload = {
    providers: Record<string, boolean>;
    accounts: ConnectedAccount[];
};

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

const dashboardPanelClass = "border-border/60 bg-card/75 rounded-lg shadow-sm";

type StudentProfileMissingItem = {
    key: string;
    label: string;
    section: "student" | "contacts" | "education";
    example?: string;
};

type StudentProfileCompletion = {
    total: number;
    completed: number;
    percentage: number;
    missing: StudentProfileMissingItem[];
};

type StudentProfileTab = "basic" | "student" | "contacts" | "education";

const normalizeStudentGender = (gender?: string | null): string => {
    const normalizedGender = (gender ?? "")
        .trim()
        .toLowerCase()
        .replace(/[\s-]+/g, "_");

    return ["male", "female", "other", "prefer_not_to_say"].includes(normalizedGender) ? normalizedGender : "";
};

const tabForStudentFormErrors = (errors: Record<string, string>): StudentProfileTab => {
    const errorKeys = Object.keys(errors);

    if (errorKeys.some((key) => !key.includes("."))) {
        return "student";
    }

    if (errorKeys.some((key) => key.startsWith("contacts.") || key.startsWith("parents."))) {
        return "contacts";
    }

    if (errorKeys.some((key) => key.startsWith("education."))) {
        return "education";
    }

    return "student";
};

export default function ProfilePage() {
    useTheme();
    const {
        user,
        faculty,
        student,
        sessions,
        endpoints,
        connected_accounts = { providers: {}, accounts: [] },
        can_view_newsletter_settings = false,
        newsletter_settings_url = null,
        id_card,
        feature_flags,
        featureFlags,
        student_profile_completion,
        branding,
    } = usePage<{
        id_card: {
            card_data: IdCardData;
            photo_url: string | null;
            qr_code: string;
            is_valid: boolean;
        } | null;
        connected_accounts: ConnectedAccountsPayload;
        can_view_newsletter_settings?: boolean;
        newsletter_settings_url?: string | null;
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
            security_two_factor_enabled: boolean;
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
            student_information_updates?: boolean;
        };
        featureFlags?: {
            studentInformationUpdates?: boolean;
        };
        student_profile_completion?: StudentProfileCompletion;
        branding?: {
            defaultCountryCode?: string;
        };
        endpoints?: {
            profile_update: string;
            password_update: string;
            faculty_update: string;
            student_update: string;
            school_options: string;
            passkeys: string;
            passkeys_options: string;
            two_factor_enable: string;
            two_factor_confirm: string;
            two_factor_disable: string;
            two_factor_recovery_codes: string;
            security_two_factor_toggle: string;
            email_auth_toggle: string;
            experimental_features: string;
            browser_sessions_logout: string;
            api_keys: string;
        };
    }>().props;

    const isFaculty = ["professor", "associate_professor", "assistant_professor", "instructor", "part_time_faculty"].includes(user.role);
    const isStudent = ["student", "graduate_student", "shs_student"].includes(user.role);
    const studentInformationUpdatesEnabled =
        !isStudent || (feature_flags?.student_information_updates ?? featureFlags?.studentInformationUpdates ?? false);
    const initialStudentTab: StudentProfileTab = !studentInformationUpdatesEnabled
        ? "basic"
        : typeof window !== "undefined" && window.location.hash === "#student-contacts"
          ? "contacts"
          : typeof window !== "undefined" && window.location.hash === "#student-education"
            ? "education"
            : typeof window !== "undefined" && window.location.hash === "#student-information"
              ? "student"
              : "basic";
    const [studentProfileTab, setStudentProfileTab] = useState(initialStudentTab);

    const paths = {
        profile_update: endpoints?.profile_update || "/profile",
        password_update: endpoints?.password_update || "/profile/password",
        faculty_update: endpoints?.faculty_update || "/profile/faculty",
        student_update: endpoints?.student_update || "/profile/student",
        school_options: endpoints?.school_options || "/student/profile/school-options",
        passkeys: endpoints?.passkeys || "/profile/passkeys",
        passkeys_options: endpoints?.passkeys_options || "/profile/passkeys/options",
        two_factor_enable: endpoints?.two_factor_enable || "/profile/two-factor-authentication/enable",
        two_factor_confirm: endpoints?.two_factor_confirm || "/profile/two-factor-authentication/confirm",
        two_factor_disable: endpoints?.two_factor_disable || "/profile/two-factor-authentication",
        two_factor_recovery_codes: endpoints?.two_factor_recovery_codes || "/profile/two-factor-authentication/recovery-codes",
        security_two_factor_toggle: endpoints?.security_two_factor_toggle || "/profile/two-factor-authentication/login-challenges",
        email_auth_toggle: endpoints?.email_auth_toggle || "/profile/email-authentication",
        experimental_features: endpoints?.experimental_features || "/profile/experimental-features",
        browser_sessions_logout: endpoints?.browser_sessions_logout || "/profile/other-browser-sessions",
        api_keys: endpoints?.api_keys || "/profile/api-keys",
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
        gender: normalizeStudentGender(student?.gender),
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
    const publicProfileSubtitle = isStudent
        ? student?.course?.title || "Student"
        : isFaculty
          ? facultyForm.data.department || "Faculty Member"
          : user.role;

    const completionFields = [
        userForm.data.name,
        userForm.data.email,
        userForm.data.bio,
        facultyForm.data.office_hours,
        facultyForm.data.education,
        facultyForm.data.courses_taught,
        facultyForm.data.biography,
    ];

    const filledFields = completionFields.filter((value) => Boolean(value && `${value}`.trim())).length;
    const accountProfileCompletion = Math.min(100, Math.round((filledFields / completionFields.length) * 100));
    const profileCompletion = isStudent ? (student_profile_completion?.percentage ?? 0) : accountProfileCompletion;
    const missingStudentItems = student_profile_completion?.missing ?? [];
    const missingBySection = (section: StudentProfileMissingItem["section"]) => missingStudentItems.filter((item) => item.section === section).length;

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
            studentForm.data.gender !== normalizeStudentGender(student?.gender) ||
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
        if (targetId === "student-information") {
            setStudentProfileTab("student");
        } else if (targetId === "student-contacts") {
            setStudentProfileTab("contacts");
        } else if (targetId === "student-education") {
            setStudentProfileTab("education");
        }

        window.setTimeout(() => {
            const target = document.getElementById(targetId);
            if (target) {
                target.scrollIntoView({ behavior: "smooth", block: "start" });
            }
        }, 50);
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

    const handleFacultySubmit = (e: React.FormEvent) => {
        e.preventDefault();
        facultyForm.put(paths.faculty_update, {
            preserveScroll: true,
            onSuccess: () => {
                toast.success("Faculty information updated successfully!");
                setHasChanges(false);
                router.visit(window.location.pathname, {
                    replace: true,
                    preserveScroll: true,
                    only: ["faculty", "user"],
                });
            },
            onError: () => {
                toast.error("Please check the highlighted faculty fields.");
            },
        });
    };

    const handleStudentSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        studentForm.put(paths.student_update, {
            onSuccess: () => {
                toast.success("Student information updated successfully!");
                setHasChanges(false);
                router.visit(window.location.pathname, {
                    replace: true,
                    preserveScroll: true,
                    only: ["student", "student_profile_completion", "announcements", "feature_flags"],
                });
            },
            onError: (errors) => {
                const errorTab = tabForStudentFormErrors(errors);
                setStudentProfileTab(errorTab);
                toast.error("Please check the highlighted student fields.");

                if (typeof window !== "undefined") {
                    window.setTimeout(() => {
                        const targetId =
                            errorTab === "contacts" ? "student-contacts" : errorTab === "education" ? "student-education" : "student-information";

                        document.getElementById(targetId)?.scrollIntoView({ behavior: "smooth", block: "start" });
                    }, 50);
                }
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
                <div className="mx-auto flex w-full max-w-7xl min-w-0 flex-col gap-4 pb-24 md:gap-6 md:p-6">
                    <div className="flex min-w-0 items-center gap-3">
                        <Link href={isStudent ? "/student/dashboard" : isFaculty ? "/faculty/dashboard" : "/dashboard"}>
                            <Button variant="outline" size="icon" className="h-9 w-9 shrink-0 rounded-lg md:h-10 md:w-10">
                                <ArrowLeft className="h-4 w-4" />
                            </Button>
                        </Link>
                        <div className="min-w-0">
                            <h1 className="text-foreground truncate text-xl font-semibold tracking-tight md:text-3xl">Profile Settings</h1>
                            <p className="text-muted-foreground mt-1 line-clamp-2 text-xs sm:text-sm">
                                Manage your account details, security, and preferences.
                            </p>
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
                        department={isFaculty ? facultyForm.data.department : undefined}
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

                    <Tabs defaultValue="profile" className="grid w-full min-w-0 gap-4 lg:grid-cols-[17rem_minmax(0,1fr)] lg:gap-6">
                        <aside className="lg:sticky lg:top-24 lg:self-start">
                            <Card className={dashboardPanelClass}>
                                <CardContent className="p-2">
                                    <TabsList className="bg-muted/20 grid h-auto w-full grid-cols-2 gap-1 rounded-lg p-1 sm:grid-cols-3 lg:flex lg:flex-col lg:items-stretch">
                                        <TabsTrigger
                                            value="profile"
                                            className="min-w-0 justify-center rounded-lg px-2 py-2 text-xs sm:text-sm lg:w-full lg:justify-start lg:px-3"
                                        >
                                            <User className="mr-1.5 h-4 w-4 shrink-0 lg:mr-2" />
                                            Profile
                                        </TabsTrigger>
                                        {id_card && (isFaculty || isStudent) && (
                                            <TabsTrigger
                                                value="id-card"
                                                className="min-w-0 justify-center rounded-lg px-2 py-2 text-xs sm:text-sm lg:w-full lg:justify-start lg:px-3"
                                            >
                                                <QrCode className="mr-1.5 h-4 w-4 shrink-0 lg:mr-2" />
                                                <span className="truncate">Digital ID</span>
                                            </TabsTrigger>
                                        )}
                                        <TabsTrigger
                                            value="accounts"
                                            className="min-w-0 justify-center rounded-lg px-2 py-2 text-xs sm:text-sm lg:w-full lg:justify-start lg:px-3"
                                        >
                                            <Plug className="mr-1.5 h-4 w-4 shrink-0 lg:mr-2" />
                                            <span className="truncate sm:hidden">Security</span>
                                            <span className="hidden truncate sm:inline lg:hidden">Accounts</span>
                                            <span className="hidden truncate lg:inline">Accounts & Security</span>
                                        </TabsTrigger>
                                        <TabsTrigger
                                            value="personalization"
                                            className="min-w-0 justify-center rounded-lg px-2 py-2 text-xs sm:text-sm lg:w-full lg:justify-start lg:px-3"
                                        >
                                            <Palette className="mr-1.5 h-4 w-4 shrink-0 lg:mr-2" />
                                            <span className="truncate sm:hidden">Theme</span>
                                            <span className="hidden truncate sm:inline">Personalization</span>
                                        </TabsTrigger>
                                        {experimentalAvailable.length > 0 && (
                                            <TabsTrigger
                                                value="experimental"
                                                className="min-w-0 justify-center rounded-lg px-2 py-2 text-xs sm:text-sm lg:w-full lg:justify-start lg:px-3"
                                            >
                                                <Plug className="mr-1.5 h-4 w-4 shrink-0 lg:mr-2" />
                                                <span className="truncate">Experimental</span>
                                            </TabsTrigger>
                                        )}
                                        <TabsTrigger
                                            value="connections"
                                            className="min-w-0 justify-center rounded-lg px-2 py-2 text-xs sm:text-sm lg:w-full lg:justify-start lg:px-3"
                                        >
                                            <Share2 className="mr-1.5 h-4 w-4 shrink-0 lg:mr-2" />
                                            <span className="truncate">Connections</span>
                                        </TabsTrigger>
                                        <TabsTrigger
                                            value="integrations"
                                            className="min-w-0 justify-center rounded-lg px-2 py-2 text-xs sm:text-sm lg:w-full lg:justify-start lg:px-3"
                                        >
                                            <Plug className="mr-1.5 h-4 w-4 shrink-0 lg:mr-2" />
                                            <span className="truncate">Integrations</span>
                                        </TabsTrigger>
                                    </TabsList>
                                </CardContent>
                            </Card>
                        </aside>

                        <div className="min-w-0">
                            <TabsContent value="profile" className="mt-0 min-w-0 outline-none">
                                <motion.div
                                    variants={containerVariants}
                                    initial="hidden"
                                    animate="visible"
                                    className="grid min-w-0 gap-4 xl:grid-cols-[minmax(0,1fr)_20rem] xl:gap-5"
                                >
                                    <motion.div variants={itemVariants} className="min-w-0">
                                        <Tabs value={studentProfileTab} onValueChange={setStudentProfileTab} className="flex flex-col gap-4">
                                            {isStudent && (
                                                <Card className={dashboardPanelClass}>
                                                    <CardContent className="space-y-3 p-3">
                                                        <div className="min-w-0">
                                                            <div className="flex flex-wrap items-center gap-2">
                                                                <h2 className="text-sm font-semibold">Student Information</h2>
                                                                {student_profile_completion && (
                                                                    <Badge
                                                                        variant={profileCompletion === 100 ? "default" : "secondary"}
                                                                        className="rounded-md text-[10px]"
                                                                    >
                                                                        {student_profile_completion.completed}/{student_profile_completion.total}{" "}
                                                                        complete
                                                                    </Badge>
                                                                )}
                                                            </div>
                                                            <p className="text-muted-foreground mt-1 text-xs leading-relaxed break-words">
                                                                Keep official contact details clear with examples like +63 912 345 6789, Juan Dela
                                                                Cruz, Mother, Davao City, and 2024.
                                                            </p>
                                                        </div>
                                                        <TabsList className="bg-muted/20 grid h-auto w-full grid-cols-2 gap-1 rounded-lg p-1 sm:grid-cols-4">
                                                            <TabsTrigger value="basic" className="min-w-0 rounded-lg px-2 py-2 text-xs sm:text-sm">
                                                                <User className="mr-1.5 h-4 w-4 shrink-0" />
                                                                Basic
                                                            </TabsTrigger>
                                                            {studentInformationUpdatesEnabled && (
                                                                <>
                                                                    <TabsTrigger
                                                                        value="student"
                                                                        className="min-w-0 rounded-lg px-2 py-2 text-xs sm:text-sm"
                                                                    >
                                                                        <GraduationCap className="mr-1.5 h-4 w-4 shrink-0" />
                                                                        <span className="truncate">Student</span>
                                                                        {missingBySection("student") > 0 && (
                                                                            <Badge
                                                                                variant="secondary"
                                                                                className="ml-1.5 h-5 rounded-full px-1.5 text-[10px]"
                                                                            >
                                                                                {missingBySection("student")}
                                                                            </Badge>
                                                                        )}
                                                                    </TabsTrigger>
                                                                    <TabsTrigger
                                                                        value="contacts"
                                                                        className="min-w-0 rounded-lg px-2 py-2 text-xs sm:text-sm"
                                                                    >
                                                                        <Contact className="mr-1.5 h-4 w-4 shrink-0" />
                                                                        <span className="truncate">Contacts</span>
                                                                        {missingBySection("contacts") > 0 && (
                                                                            <Badge
                                                                                variant="secondary"
                                                                                className="ml-1.5 h-5 rounded-full px-1.5 text-[10px]"
                                                                            >
                                                                                {missingBySection("contacts")}
                                                                            </Badge>
                                                                        )}
                                                                    </TabsTrigger>
                                                                    <TabsTrigger
                                                                        value="education"
                                                                        className="min-w-0 rounded-lg px-2 py-2 text-xs sm:text-sm"
                                                                    >
                                                                        <BookOpen className="mr-1.5 h-4 w-4 shrink-0" />
                                                                        <span className="truncate">Education</span>
                                                                        {missingBySection("education") > 0 && (
                                                                            <Badge
                                                                                variant="secondary"
                                                                                className="ml-1.5 h-5 rounded-full px-1.5 text-[10px]"
                                                                            >
                                                                                {missingBySection("education")}
                                                                            </Badge>
                                                                        )}
                                                                    </TabsTrigger>
                                                                </>
                                                            )}
                                                        </TabsList>
                                                    </CardContent>
                                                </Card>
                                            )}

                                            <TabsContent value="basic" className="mt-0 min-w-0 outline-none">
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
                                                                  processing: facultyForm.processing,
                                                              }
                                                            : undefined
                                                    }
                                                    onSubmit={handleUserSubmit}
                                                    onFacultySubmit={handleFacultySubmit}
                                                    developerModeEnabled={developerModeEnabled}
                                                    defaultCountryCode={branding?.defaultCountryCode}
                                                />
                                            </TabsContent>

                                            {isStudent && !studentInformationUpdatesEnabled && (
                                                <Card className={dashboardPanelClass}>
                                                    <CardContent className="text-muted-foreground p-4 text-sm">
                                                        Student information updates are currently unavailable for your account.
                                                    </CardContent>
                                                </Card>
                                            )}

                                            {isStudent && studentInformationUpdatesEnabled && (
                                                <>
                                                    <TabsContent value="student" className="mt-0 min-w-0 outline-none">
                                                        <StudentDetailsForm
                                                            studentForm={{
                                                                data: studentForm.data,
                                                                setData: studentForm.setData,
                                                                errors: studentForm.errors,
                                                                processing: studentForm.processing,
                                                            }}
                                                            onSubmit={handleStudentSubmit}
                                                            defaultCountryCode={branding?.defaultCountryCode}
                                                        />
                                                    </TabsContent>
                                                    <TabsContent value="contacts" className="mt-0 min-w-0 outline-none">
                                                        <StudentContactsForm
                                                            studentForm={{
                                                                data: studentForm.data,
                                                                setData: studentForm.setData,
                                                                errors: studentForm.errors,
                                                                processing: studentForm.processing,
                                                            }}
                                                            onSubmit={handleStudentSubmit}
                                                            defaultCountryCode={branding?.defaultCountryCode}
                                                        />
                                                    </TabsContent>
                                                    <TabsContent value="education" className="mt-0 min-w-0 outline-none">
                                                        <StudentEducationForm
                                                            studentForm={{
                                                                data: studentForm.data,
                                                                setData: studentForm.setData,
                                                                errors: studentForm.errors,
                                                                processing: studentForm.processing,
                                                            }}
                                                            onSubmit={handleStudentSubmit}
                                                            schoolOptionsEndpoint={paths.school_options}
                                                        />
                                                    </TabsContent>
                                                </>
                                            )}
                                        </Tabs>
                                    </motion.div>

                                    <motion.div variants={itemVariants} className="hidden xl:block">
                                        <ProfileSidebar
                                            user={user}
                                            avatarPreview={avatarPreview}
                                            subtitle={publicProfileSubtitle}
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
                                <IntegrationsTab
                                    connectedAccounts={connected_accounts}
                                    canViewNewsletterSettings={can_view_newsletter_settings}
                                    newsletterSettingsUrl={newsletter_settings_url}
                                />
                            </TabsContent>
                        </div>
                    </Tabs>
                </div>
            </PortalLayout>
        </>
    );
}
