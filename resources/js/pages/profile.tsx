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
    StudentReportingForm,
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
    section: "personal" | "family" | "education" | "reporting";
    example?: string;
};

type StudentProfileCompletion = {
    total: number;
    completed: number;
    percentage: number;
    missing: StudentProfileMissingItem[];
};

type StudentProfileTab = "basic" | "personal" | "family" | "education" | "reporting";

const normalizeStudentGender = (gender?: string | null): string => {
    const normalizedGender = (gender ?? "")
        .trim()
        .toLowerCase()
        .replace(/[\s-]+/g, "_");

    return ["male", "female", "other", "prefer_not_to_say"].includes(normalizedGender) ? normalizedGender : "";
};

const tabForStudentFormErrors = (errors: Record<string, string>): StudentProfileTab => {
    const errorKeys = Object.keys(errors);

    if (errorKeys.some((key) => key.startsWith("contacts.") || key.startsWith("parents."))) {
        return "family";
    }

    if (errorKeys.some((key) => key.startsWith("education."))) {
        return "education";
    }

    if (
        errorKeys.some((key) =>
            [
                "ethnicity",
                "city_of_origin",
                "province_of_origin",
                "region_of_origin",
                "is_",
                "income_",
                "family_income",
                "father_income",
                "mother_income",
                "indigenous_",
                "pwd_",
            ].some((prefix) => key.startsWith(prefix)),
        )
    ) {
        return "reporting";
    }

    return "personal";
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
        can_configure_payment_workspace = false,
        payment_workspace = null,
        payment_workspace_url = null,
        payment_methods = [],
        can_configure_tuition_adjustment_workspace = false,
        tuition_adjustment_workspace = null,
        tuition_adjustment_workspace_url = null,
        id_card,
        feature_flags,
        featureFlags,
        student_profile_completion,
        income_modes = [],
        default_income_mode = "annual",
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
        can_configure_payment_workspace?: boolean;
        payment_workspace?: {
            layout: "guided" | "spreadsheet";
            density: "comfortable" | "compact";
            history_visibility: "auto" | "open" | "hidden";
            default_payment_method: string;
        } | null;
        payment_workspace_url?: string | null;
        payment_methods?: Array<{ value: string; label: string }>;
        can_configure_tuition_adjustment_workspace?: boolean;
        tuition_adjustment_workspace?: { layout: "inspector" | "staged" } | null;
        tuition_adjustment_workspace_url?: string | null;
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
                twitter?: string;
                instagram?: string;
                linkedin?: string;
                personal_contact?: string;
            };
            education?: {
                elementary_school?: string;
                elementary_year_graduated?: string;
                high_school?: string;
                high_school_year_graduated?: string;
                senior_high_school?: string;
                senior_high_year_graduated?: string;
                college_school?: string;
                college_course?: string;
                college_year_graduated?: string;
                vocational_school?: string;
                vocational_course?: string;
                vocational_year_graduated?: string;
            };
            parents?: {
                father_name?: string;
                father_occupation?: string;
                father_contact?: string;
                father_email?: string;
                mother_name?: string;
                mother_occupation?: string;
                mother_contact?: string;
                mother_email?: string;
                guardian_name?: string;
                guardian_relationship?: string;
                guardian_contact?: string;
                guardian_email?: string;
                family_address?: string;
            };
            personal_info?: {
                birthplace?: string;
                citizenship?: string;
                weight?: number | string;
                height?: number | string;
                current_address?: string;
                permanent_address?: string;
            };
            ethnicity?: string;
            city_of_origin?: string;
            province_of_origin?: string;
            region_of_origin?: string;
            is_indigenous_person?: boolean;
            indigenous_group?: string;
            is_pwd?: boolean;
            pwd_type?: string;
            is_solo_parent?: boolean;
            is_senior_citizen?: boolean;
            is_magna_carta?: boolean;
            is_underprivileged?: boolean;
            is_first_generation?: boolean;
            income_bracket_mode?: string;
            use_same_parent_income?: boolean;
            family_income_bracket?: string;
            father_income_bracket?: string;
            mother_income_bracket?: string;
            profile_reporting_confirmed_at?: string;
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
        income_modes?: Array<{ value: string; label: string; brackets: Array<{ value: string; label: string }> }>;
        default_income_mode?: string;
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
          ? "family"
          : typeof window !== "undefined" && window.location.hash === "#student-education"
            ? "education"
            : typeof window !== "undefined" && window.location.hash === "#student-reporting"
              ? "reporting"
              : typeof window !== "undefined" && ["#student-information", "#student-personal"].includes(window.location.hash)
                ? "personal"
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

    const initialStudentFormData = {
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
            twitter: student?.contacts?.twitter || "",
            instagram: student?.contacts?.instagram || "",
            linkedin: student?.contacts?.linkedin || "",
            personal_contact: student?.contacts?.personal_contact || "",
        },
        education: {
            elementary_school: student?.education?.elementary_school || "",
            elementary_year_graduated: student?.education?.elementary_year_graduated || "",
            high_school: student?.education?.high_school || "",
            high_school_year_graduated: student?.education?.high_school_year_graduated || "",
            senior_high_school: student?.education?.senior_high_school || "",
            senior_high_year_graduated: student?.education?.senior_high_year_graduated || "",
            college_school: student?.education?.college_school || "",
            college_course: student?.education?.college_course || "",
            college_year_graduated: student?.education?.college_year_graduated || "",
            vocational_school: student?.education?.vocational_school || "",
            vocational_course: student?.education?.vocational_course || "",
            vocational_year_graduated: student?.education?.vocational_year_graduated || "",
        },
        parents: {
            father_name: student?.parents?.father_name || "",
            father_occupation: student?.parents?.father_occupation || "",
            father_contact: student?.parents?.father_contact || "",
            father_email: student?.parents?.father_email || "",
            mother_name: student?.parents?.mother_name || "",
            mother_occupation: student?.parents?.mother_occupation || "",
            mother_contact: student?.parents?.mother_contact || "",
            mother_email: student?.parents?.mother_email || "",
            guardian_name: student?.parents?.guardian_name || "",
            guardian_relationship: student?.parents?.guardian_relationship || "",
            guardian_contact: student?.parents?.guardian_contact || "",
            guardian_email: student?.parents?.guardian_email || "",
            family_address: student?.parents?.family_address || "",
        },
        personal_info: {
            birthplace: student?.personal_info?.birthplace || "",
            citizenship: student?.personal_info?.citizenship || "",
            weight: `${student?.personal_info?.weight ?? ""}`,
            height: `${student?.personal_info?.height ?? ""}`,
            current_address: student?.personal_info?.current_address || "",
            permanent_address: student?.personal_info?.permanent_address || "",
        },
        ethnicity: student?.ethnicity || "",
        city_of_origin: student?.city_of_origin || "",
        province_of_origin: student?.province_of_origin || "",
        region_of_origin: student?.region_of_origin || "",
        is_indigenous_person: student?.is_indigenous_person ?? false,
        indigenous_group: student?.indigenous_group || "",
        is_pwd: student?.is_pwd ?? false,
        pwd_type: student?.pwd_type || "",
        is_solo_parent: student?.is_solo_parent ?? false,
        is_senior_citizen: student?.is_senior_citizen ?? false,
        is_magna_carta: student?.is_magna_carta ?? false,
        is_underprivileged: student?.is_underprivileged ?? false,
        is_first_generation: student?.is_first_generation ?? false,
        income_bracket_mode: student?.income_bracket_mode || default_income_mode,
        use_same_parent_income: student?.use_same_parent_income ?? true,
        family_income_bracket: student?.family_income_bracket || "",
        father_income_bracket: student?.father_income_bracket || "",
        mother_income_bracket: student?.mother_income_bracket || "",
        reporting_confirmed: false,
    };

    const studentForm = useForm(initialStudentFormData);

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

        const hasStudentChanges = JSON.stringify(studentForm.data) !== JSON.stringify(initialStudentFormData);

        setHasChanges(hasUserChanges || hasFacultyChanges || hasStudentChanges);
    }, [userForm.data, facultyForm.data, studentForm.data, user, faculty, student]);

    useEffect(() => {
        if (typeof window === "undefined") return;
        const hash = window.location.hash;
        if (!hash) return;
        const targetId = hash.replace("#", "");
        if (targetId === "student-information" || targetId === "student-personal") {
            setStudentProfileTab("personal");
        } else if (targetId === "student-contacts") {
            setStudentProfileTab("family");
        } else if (targetId === "student-education") {
            setStudentProfileTab("education");
        } else if (targetId === "student-reporting") {
            setStudentProfileTab("reporting");
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
        studentForm.transform((data) => ({
            ...data,
            reporting_confirmed: studentProfileTab === "reporting",
        }));
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
                            errorTab === "family"
                                ? "student-contacts"
                                : errorTab === "education"
                                  ? "student-education"
                                  : errorTab === "reporting"
                                    ? "student-reporting"
                                    : "student-personal";

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
                                                        <TabsList className="bg-muted/20 grid h-auto w-full grid-cols-2 gap-1 rounded-lg p-1 sm:grid-cols-5">
                                                            <TabsTrigger value="basic" className="min-w-0 rounded-lg px-2 py-2 text-xs sm:text-sm">
                                                                <User className="mr-1.5 h-4 w-4 shrink-0" />
                                                                Basic
                                                            </TabsTrigger>
                                                            {studentInformationUpdatesEnabled && (
                                                                <>
                                                                    <TabsTrigger
                                                                        value="personal"
                                                                        className="min-w-0 rounded-lg px-2 py-2 text-xs sm:text-sm"
                                                                    >
                                                                        <GraduationCap className="mr-1.5 h-4 w-4 shrink-0" />
                                                                        <span className="truncate">Personal</span>
                                                                        {missingBySection("personal") > 0 && (
                                                                            <Badge
                                                                                variant="secondary"
                                                                                className="ml-1.5 h-5 rounded-full px-1.5 text-[10px]"
                                                                            >
                                                                                {missingBySection("personal")}
                                                                            </Badge>
                                                                        )}
                                                                    </TabsTrigger>
                                                                    <TabsTrigger
                                                                        value="family"
                                                                        className="min-w-0 rounded-lg px-2 py-2 text-xs sm:text-sm"
                                                                    >
                                                                        <Contact className="mr-1.5 h-4 w-4 shrink-0" />
                                                                        <span className="truncate">Family</span>
                                                                        {missingBySection("family") > 0 && (
                                                                            <Badge
                                                                                variant="secondary"
                                                                                className="ml-1.5 h-5 rounded-full px-1.5 text-[10px]"
                                                                            >
                                                                                {missingBySection("family")}
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
                                                                    <TabsTrigger
                                                                        value="reporting"
                                                                        className="min-w-0 rounded-lg px-2 py-2 text-xs sm:text-sm"
                                                                    >
                                                                        <BookOpen className="mr-1.5 h-4 w-4 shrink-0" />
                                                                        <span className="truncate">Reporting</span>
                                                                        {missingBySection("reporting") > 0 && (
                                                                            <Badge
                                                                                variant="secondary"
                                                                                className="ml-1.5 h-5 rounded-full px-1.5 text-[10px]"
                                                                            >
                                                                                {missingBySection("reporting")}
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
                                                    <TabsContent value="personal" className="mt-0 min-w-0 outline-none">
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
                                                    <TabsContent value="family" className="mt-0 min-w-0 outline-none">
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
                                                    <TabsContent value="reporting" className="mt-0 min-w-0 outline-none">
                                                        <StudentReportingForm
                                                            studentForm={{
                                                                data: studentForm.data,
                                                                setData: studentForm.setData,
                                                                errors: studentForm.errors,
                                                                processing: studentForm.processing,
                                                            }}
                                                            incomeModes={income_modes}
                                                            onSubmit={handleStudentSubmit}
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
                                <PersonalizationTab
                                    canConfigurePaymentWorkspace={can_configure_payment_workspace}
                                    paymentWorkspace={payment_workspace}
                                    paymentWorkspaceUrl={payment_workspace_url}
                                    paymentMethods={payment_methods}
                                    canConfigureTuitionAdjustmentWorkspace={can_configure_tuition_adjustment_workspace}
                                    tuitionAdjustmentWorkspace={tuition_adjustment_workspace}
                                    tuitionAdjustmentWorkspaceUrl={tuition_adjustment_workspace_url}
                                />
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
