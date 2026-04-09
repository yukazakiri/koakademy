import AdminLayout from "@/components/administrators/admin-layout";
import { Button } from "@/components/ui/button";
import { Head, Link, router } from "@inertiajs/react";
import { UserPlus } from "lucide-react";
import { useEffect, useState } from "react";
import { toast } from "sonner";
import { useDebouncedCallback } from "use-debounce";
import { route } from "ziggy-js";
import { ApplicantsCard } from "./applicants-card";
import { DeleteApplicantDialog, ForceDeleteApplicantDialog, ManageApplicantDialog } from "./enrollment-dialogs";
import type { ApplicantRow, EnrollmentApplicantsProps } from "./types";

export default function AdministratorEnrollmentApplicants({ user, applicants, filters }: EnrollmentApplicantsProps) {
    const [applicantSearch, setApplicantSearch] = useState(filters.search || "");
    const [selectedApplicant, setSelectedApplicant] = useState<ApplicantRow | null>(null);
    const [scholarshipType, setScholarshipType] = useState<string>("");
    const [isUpdating, setIsUpdating] = useState(false);

    const [deleteApplicant, setDeleteApplicant] = useState<ApplicantRow | null>(null);
    const [forceDeleteApplicant, setForceDeleteApplicant] = useState<ApplicantRow | null>(null);
    const [isDeleting, setIsDeleting] = useState(false);
    const [isSearching, setIsSearching] = useState(false);

    const safeApplicants = Array.isArray(applicants?.data) ? applicants.data : [];
    const applicantsPagination = {
        current_page: applicants?.current_page ?? 1,
        last_page: applicants?.last_page ?? 1,
        per_page: applicants?.per_page ?? 10,
        total: applicants?.total ?? 0,
        next_page_url: applicants?.next_page_url ?? null,
        prev_page_url: applicants?.prev_page_url ?? null,
        from: applicants?.from ?? 0,
        to: applicants?.to ?? 0,
    };

    useEffect(() => {
        setApplicantSearch(filters.search || "");
    }, [filters.search]);

    const applyFilters = (overrides: Record<string, unknown> = {}) => {
        router.get(
            route("administrators.enrollments.applicants"),
            {
                ...filters,
                search: applicantSearch || undefined,
                ...overrides,
            },
            {
                preserveState: true,
                replace: true,
                preserveScroll: true,
                only: ["applicants", "filters"],
                onStart: () => setIsSearching(true),
                onFinish: () => setIsSearching(false),
            },
        );
    };

    const handleApplicantSearch = useDebouncedCallback((term: string) => {
        applyFilters({ search: term || undefined });
    }, 300);

    const handleApplicantSearchChange = (value: string) => {
        setApplicantSearch(value);
        handleApplicantSearch(value);
    };

    const handleManageClick = (applicant: ApplicantRow) => {
        setSelectedApplicant(applicant);
        setScholarshipType(applicant.scholarship_type || "none");
    };

    const handleUpdateScholarship = () => {
        if (!selectedApplicant) return;

        setIsUpdating(true);
        router.patch(
            route("administrators.enrollments.scholarship.update", { student: selectedApplicant.id }),
            {
                scholarship_type: scholarshipType === "none" ? null : scholarshipType,
            },
            {
                onSuccess: () => {
                    toast.success("Scholarship status updated.");
                    setSelectedApplicant(null);
                },
                onError: () => {
                    toast.error("Failed to update scholarship status.");
                },
                onFinish: () => {
                    setIsUpdating(false);
                },
            },
        );
    };

    const handleDeleteApplicant = () => {
        if (!deleteApplicant) return;

        setIsDeleting(true);
        router.delete(route("administrators.students.destroy", { student: deleteApplicant.id }), {
            onSuccess: () => {
                toast.success(`Applicant "${deleteApplicant.name}" has been deleted.`);
                setDeleteApplicant(null);
            },
            onError: () => {
                toast.error("Failed to delete applicant.");
            },
            onFinish: () => {
                setIsDeleting(false);
            },
        });
    };

    const handleForceDeleteApplicant = () => {
        if (!forceDeleteApplicant) return;

        setIsDeleting(true);
        router.delete(route("administrators.students.force-destroy", { student: forceDeleteApplicant.id }), {
            onSuccess: () => {
                toast.success(`Applicant "${forceDeleteApplicant.name}" has been permanently deleted.`);
                setForceDeleteApplicant(null);
            },
            onError: () => {
                toast.error("Failed to permanently delete applicant.");
            },
            onFinish: () => {
                setIsDeleting(false);
            },
        });
    };

    return (
        <AdminLayout user={user} title="Applicants">
            <Head title="Administrators • Applicants" />

            <div className="space-y-8 pb-10">
                <div className="flex flex-col gap-6 sm:flex-row sm:items-center sm:justify-between">
                    <div className="space-y-1">
                        <h2 className="text-foreground text-3xl font-bold tracking-tight">Applicants</h2>
                        <p className="text-muted-foreground">Review and process incoming applicants before enrollment.</p>
                    </div>
                    <div className="flex items-center gap-3">
                        <Button variant="outline" asChild>
                            <Link href={route("administrators.enrollments.index")}>Students</Link>
                        </Button>
                        <Button asChild className="bg-primary text-primary-foreground hover:bg-primary/90">
                            <Link href={route("administrators.enrollments.create")}>
                                <UserPlus className="mr-2 h-4 w-4" />
                                New Enrollment
                            </Link>
                        </Button>
                    </div>
                </div>

                <ApplicantsCard
                    applicants={safeApplicants}
                    pagination={applicantsPagination}
                    filters={filters}
                    applicantSearch={applicantSearch}
                    isSearching={isSearching}
                    onSearchChange={handleApplicantSearchChange}
                    onManageScholarship={handleManageClick}
                    onDeleteApplicant={setDeleteApplicant}
                    onForceDeleteApplicant={setForceDeleteApplicant}
                />
            </div>

            <ManageApplicantDialog
                open={!!selectedApplicant}
                applicant={selectedApplicant}
                scholarshipType={scholarshipType}
                onScholarshipChange={setScholarshipType}
                onOpenChange={(open) => !open && setSelectedApplicant(null)}
                onSave={handleUpdateScholarship}
                isUpdating={isUpdating}
            />

            <DeleteApplicantDialog
                open={!!deleteApplicant}
                applicant={deleteApplicant}
                isDeleting={isDeleting}
                onOpenChange={(open) => !open && setDeleteApplicant(null)}
                onConfirm={handleDeleteApplicant}
            />

            <ForceDeleteApplicantDialog
                open={!!forceDeleteApplicant}
                applicant={forceDeleteApplicant}
                isDeleting={isDeleting}
                onOpenChange={(open) => !open && setForceDeleteApplicant(null)}
                onConfirm={handleForceDeleteApplicant}
            />
        </AdminLayout>
    );
}
