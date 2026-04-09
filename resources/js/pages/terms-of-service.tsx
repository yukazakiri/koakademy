import { Link, usePage } from "@inertiajs/react";

interface Branding {
    appName: string;
    organizationName: string;
    organizationShortName: string;
    organizationAddress?: string | null;
    supportEmail?: string | null;
    supportPhone?: string | null;
}

export default function TermsOfService() {
    const { props } = usePage<{ branding?: Branding }>();
    const appName = props.branding?.appName || "School Portal";
    const organizationName = props.branding?.organizationName || "University";
    const orgShortName = props.branding?.organizationShortName || "UNI";
    const organizationAddress = props.branding?.organizationAddress || "";
    const supportEmail = props.branding?.supportEmail || "support@koakademy.edu";
    const supportPhone = props.branding?.supportPhone || "";

    const lastUpdated = "December 6, 2025";

    return (
        <div className="bg-background">
            <div className="mx-auto max-w-4xl px-4 py-12">
                <div className="space-y-8">
                    <div className="text-center">
                        <h1 className="text-4xl font-bold tracking-tight">Terms of Service</h1>
                        <p className="text-muted-foreground mt-2">{organizationName}</p>
                        <p className="text-muted-foreground mt-2 text-sm">Last Updated: {lastUpdated}</p>
                    </div>

                    <div className="prose prose-slate dark:prose-invert max-w-none">
                        <section className="space-y-4">
                            <h2 className="text-2xl font-semibold">1. Acceptance of Terms</h2>
                            <p className="text-muted-foreground">
                                <strong>5.3 Institutional Data:</strong> All data belonging to {orgShortName}, including academic records, financial
                                information, and proprietary materials, must be protected and used solely for authorized purposes.
                            </p>
                        </section>

                        <section className="space-y-4">
                            <h2 className="text-2xl font-semibold">6. Prohibited Activities</h2>
                            <p className="text-muted-foreground">Users are strictly prohibited from:</p>
                            <ul className="text-muted-foreground list-disc pl-6">
                                <li>Sharing login credentials with unauthorized individuals</li>
                                <li>Using the System for any unlawful purpose or in violation of these Terms</li>
                                <li>Attempting to circumvent security measures or access controls</li>
                                <li>Copying, modifying, or distributing copyrighted materials without authorization</li>
                                <li>Introducing malware, viruses, or malicious code into the System</li>
                                <li>Using automated tools or scripts without authorization</li>
                                <li>Accessing data unrelated to one's assigned duties and responsibilities</li>
                            </ul>
                        </section>

                        <section className="space-y-4">
                            <h2 className="text-2xl font-semibold">7. Intellectual Property Rights</h2>
                            <p className="text-muted-foreground">
                                The System, including its software, features, and content, is owned by {orgShortName} and is protected by copyright,
                                trademark, and other intellectual property laws. Users do not acquire any ownership rights to the System or its
                                content.
                            </p>
                        </section>

                        <section className="space-y-4">
                            <h2 className="text-2xl font-semibold">8. System Availability and Maintenance</h2>
                            <p className="text-muted-foreground">
                                While we strive to maintain continuous System availability, we do not guarantee uninterrupted access. The System may
                                be temporarily unavailable for:
                            </p>
                            <ul className="text-muted-foreground list-disc pl-6">
                                <li>Scheduled maintenance and updates</li>
                                <li>Unforeseen technical issues</li>
                                <li>Force majeure events</li>
                            </ul>
                        </section>

                        <section className="space-y-4">
                            <h2 className="text-2xl font-semibold">9. Termination</h2>
                            <p className="text-muted-foreground">
                                <strong>9.1 By {orgShortName}:</strong> {orgShortName} reserves the right to terminate or suspend user access
                                immediately, without prior notice, for any breach of these Terms or for any reason deemed appropriate by the
                                institution.
                            </p>
                            <p className="text-muted-foreground">
                                <strong>9.2 By Users:</strong> Users may discontinue use of the System at any time by notifying the IT Department.
                            </p>
                            <p className="text-muted-foreground">
                                <strong>9.3 Effect of Termination:</strong> Upon termination, all access rights and privileges immediately cease, and
                                users must discontinue use of the System.
                            </p>
                        </section>

                        <section className="space-y-4">
                            <h2 className="text-2xl font-semibold">13. Changes to Terms</h2>
                            <p className="text-muted-foreground">
                                {orgShortName} reserves the right to modify these Terms at any time. Users will be notified of any material changes,
                                and continued use of the System after such notification constitutes acceptance of the modified Terms.
                            </p>
                        </section>

                        <section className="space-y-4">
                            <h2 className="text-2xl font-semibold">11. Governing Law</h2>
                            <p className="text-muted-foreground">
                                These Terms shall be governed by and construed in accordance with the laws of the Republic of the Philippines,
                                specifically applying to institutions operating in Baguio City and the Cordillera Administrative Region.
                            </p>
                        </section>

                        <section className="space-y-4">
                            <h2 className="text-2xl font-semibold">12. Compliance and Reporting</h2>
                            <p className="text-muted-foreground">
                                <strong>12.1 Data Breach Reporting:</strong> Any actual or suspected data breach must be reported to the IT Department
                                and Data Protection Officer immediately.
                            </p>
                            <p className="text-muted-foreground">
                                <strong>12.2 Violation Reporting:</strong> Users must report any violations of these Terms or suspicious activities to
                                their supervisor or the IT Department.
                            </p>
                        </section>

                        <section className="space-y-4">
                            <h2 className="text-2xl font-semibold">14. Contact Information</h2>
                            <p className="text-muted-foreground">For questions regarding these Terms of Service, please contact:</p>
                            <div className="bg-muted/40 mt-4 rounded-lg border p-4">
                                <p className="text-sm">
                                    <strong>{organizationName}</strong>
                                    <br />
                                    IT Department
                                    {organizationAddress ? (
                                        <>
                                            <br />
                                            {organizationAddress}
                                        </>
                                    ) : null}
                                    <br />
                                    Email: {supportEmail}
                                    {supportPhone ? (
                                        <>
                                            <br />
                                            Phone: {supportPhone}
                                        </>
                                    ) : null}
                                </p>
                            </div>
                        </section>

                        <section className="space-y-4">
                            <h2 className="text-2xl font-semibold">15. Acknowledgment</h2>
                            <p className="text-muted-foreground">
                                By using the {appName}, you acknowledge that you have read, understood, and agree to be bound by these Terms of
                                Service and all applicable policies and regulations of
                                {organizationName}.
                            </p>
                        </section>

                        <section className="space-y-4">
                            <h2 className="text-2xl font-semibold">14. Contact Information</h2>
                            <p className="text-muted-foreground">For questions regarding these Terms of Service, please contact:</p>
                            <div className="bg-muted/40 mt-4 rounded-lg border p-4">
                                <p className="text-sm">
                                    <strong>{organizationName}</strong>
                                    <br />
                                    IT Department
                                    {organizationAddress ? (
                                        <>
                                            <br />
                                            {organizationAddress}
                                        </>
                                    ) : null}
                                    <br />
                                    Email: {supportEmail}
                                    {supportPhone ? (
                                        <>
                                            <br />
                                            Phone: {supportPhone}
                                        </>
                                    ) : null}
                                </p>
                            </div>
                        </section>

                        <section className="space-y-4">
                            <h2 className="text-2xl font-semibold">15. Acknowledgment</h2>
                            <p className="text-muted-foreground">
                                By using the {appName}, you acknowledge that you have read, understood, and agree to be bound by these Terms of
                                Service and all applicable policies and regulations of {organizationName}.
                            </p>
                        </section>
                    </div>

                    <div className="flex justify-center gap-4 pt-8">
                        <Link
                            href="/login"
                            className="ring-offset-background focus-visible:ring-ring border-input bg-background hover:bg-accent hover:text-accent-foreground inline-flex h-10 items-center justify-center rounded-md border px-4 py-2 text-sm font-medium transition-colors focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none disabled:pointer-events-none disabled:opacity-50"
                        >
                            Back to Login
                        </Link>
                        <Link
                            href="/privacy-policy"
                            className="ring-offset-background focus-visible:ring-ring bg-primary text-primary-foreground hover:bg-primary/90 inline-flex h-10 items-center justify-center rounded-md px-4 py-2 text-sm font-medium transition-colors focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none disabled:pointer-events-none disabled:opacity-50"
                        >
                            Privacy Policy
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    );
}
