import { Link, usePage } from "@inertiajs/react";

interface Branding {
    appName: string;
    organizationName: string;
    organizationShortName: string;
    organizationAddress?: string | null;
    supportEmail?: string | null;
    supportPhone?: string | null;
}

export default function PrivacyPolicy() {
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
                        <h1 className="text-4xl font-bold tracking-tight">Privacy Policy</h1>
                        <p className="text-muted-foreground mt-2">{organizationName}</p>
                        <p className="text-muted-foreground mt-2 text-sm">Last Updated: {lastUpdated}</p>
                    </div>

                    <div className="prose prose-slate dark:prose-invert max-w-none">
                        <section className="space-y-4">
                            <h2 className="text-2xl font-semibold">1. Introduction</h2>
                            <p className="text-muted-foreground">
                                {organizationName} ("{orgShortName}", "we", "us", or "our") is committed to protecting the privacy and security of
                                personal information entrusted to us. This Privacy Policy describes how we collect, use, disclose, and protect
                                personal data through the {appName} ("System") in compliance with the Philippine Data Privacy Act of 2012 (Republic
                                Act No. 10173) and its Implementing Rules and Regulations.
                            </p>
                        </section>

                        <section className="space-y-4">
                            <h2 className="text-2xl font-semibold">2. Information We Collect</h2>

                            <h3 className="mt-6 text-xl font-semibold">2.1 Faculty Information</h3>
                            <p className="text-muted-foreground">We collect and process the following information from faculty members:</p>
                            <ul className="text-muted-foreground list-disc pl-6">
                                <li>Full name, employee ID, and faculty identification number</li>
                                <li>Contact information (email address, phone number, address)</li>
                                <li>Employment details (department, position, hire date, employment status)</li>
                                <li>Academic credentials and qualifications</li>
                                <li>Teaching assignments and schedules</li>
                                <li>Professional development records</li>
                                <li>System access logs and usage data</li>
                            </ul>

                            <h3 className="mt-6 text-xl font-semibold">2.2 Administrator Information</h3>
                            <p className="text-muted-foreground">For administrative personnel, we collect:</p>
                            <ul className="text-muted-foreground list-disc pl-6">
                                <li>Full name and employee identification</li>
                                <li>Contact information and department assignment</li>
                                <li>Role and access level within the System</li>
                                <li>System activity logs and audit trails</li>
                            </ul>

                            <h3 className="mt-6 text-xl font-semibold">2.3 Student Information</h3>
                            <p className="text-muted-foreground">
                                Faculty and administrators may access student information as necessary for their duties, including:
                            </p>
                            <ul className="text-muted-foreground list-disc pl-6">
                                <li>Student names, IDs, and enrollment information</li>
                                <li>Academic records and grades</li>
                                <li>Attendance records</li>
                                <li>Contact information</li>
                            </ul>

                            <h3 className="mt-6 text-xl font-semibold">2.4 Technical Data</h3>
                            <p className="text-muted-foreground">We automatically collect:</p>
                            <ul className="text-muted-foreground list-disc pl-6">
                                <li>IP addresses and device information</li>
                                <li>Browser type and version</li>
                                <li>Login timestamps and session data</li>
                                <li>Pages accessed and actions performed within the System</li>
                            </ul>
                        </section>

                        <section className="space-y-4">
                            <h2 className="text-2xl font-semibold">3. Legal Basis for Processing</h2>
                            <p className="text-muted-foreground">
                                Under the Data Privacy Act of 2012, we process personal information based on the following lawful criteria:
                            </p>
                            <ul className="text-muted-foreground list-disc pl-6">
                                <li>
                                    <strong>Consent:</strong> Users provide consent when creating accounts and accepting these terms
                                </li>
                                <li>
                                    <strong>Contractual Necessity:</strong> Processing is necessary for employment contracts and student enrollment
                                </li>
                                <li>
                                    <strong>Legal Obligation:</strong> Compliance with educational regulations and government reporting requirements
                                </li>
                                <li>
                                    <strong>Legitimate Interests:</strong> Educational administration, institutional security, and service improvement
                                </li>
                            </ul>
                        </section>

                        <section className="space-y-4">
                            <h2 className="text-2xl font-semibold">4. How We Use Your Information</h2>
                            <p className="text-muted-foreground">Personal information is used for:</p>
                            <ul className="text-muted-foreground list-disc pl-6">
                                <li>Managing faculty assignments, schedules, and workloads</li>
                                <li>Processing and maintaining academic records</li>
                                <li>Facilitating communication between faculty, students, and administration</li>
                                <li>Generating institutional reports for compliance and accreditation</li>
                                <li>Ensuring system security and preventing unauthorized access</li>
                                <li>Improving System functionality and user experience</li>
                                <li>Complying with regulatory and legal requirements</li>
                                <li>Supporting institutional research and planning</li>
                            </ul>
                        </section>

                        <section className="space-y-4">
                            <h2 className="text-2xl font-semibold">5. Data Sharing and Disclosure</h2>
                            <p className="text-muted-foreground">We may share personal information with:</p>
                            <ul className="text-muted-foreground list-disc pl-6">
                                <li>
                                    <strong>Government Agencies:</strong> Commission on Higher Education (CHED), Department of Education (DepEd), and
                                    other regulatory bodies as required by law
                                </li>
                                <li>
                                    <strong>Accrediting Bodies:</strong> For institutional accreditation purposes
                                </li>
                                <li>
                                    <strong>Service Providers:</strong> Third-party vendors who assist in system maintenance and hosting, bound by
                                    confidentiality agreements
                                </li>
                                <li>
                                    <strong>Legal Authorities:</strong> When required by court order or legal process
                                </li>
                            </ul>
                            <p className="text-muted-foreground mt-4">
                                We do not sell, rent, or trade personal information to third parties for marketing purposes.
                            </p>
                        </section>

                        <section className="space-y-4">
                            <h2 className="text-2xl font-semibold">6. Data Security</h2>
                            <p className="text-muted-foreground">
                                {orgShortName} implements appropriate organizational, physical, and technical security measures to protect personal
                                information, including:
                            </p>
                            <ul className="text-muted-foreground list-disc pl-6">
                                <li>Encryption of data in transit and at rest</li>
                                <li>Access controls and authentication mechanisms</li>
                                <li>Regular security assessments and vulnerability testing</li>
                                <li>Employee training on data protection</li>
                                <li>Incident response procedures</li>
                                <li>Physical security measures for data centers and equipment</li>
                            </ul>
                        </section>

                        <section className="space-y-4">
                            <h2 className="text-2xl font-semibold">7. Data Retention</h2>
                            <p className="text-muted-foreground">
                                Personal information is retained for as long as necessary to fulfill the purposes for which it was collected, or as
                                required by applicable laws and regulations. Specific retention periods include:
                            </p>
                            <ul className="text-muted-foreground list-disc pl-6">
                                <li>
                                    <strong>Academic Records:</strong> Permanently retained as required by CHED regulations
                                </li>
                                <li>
                                    <strong>Employment Records:</strong> Retained for the duration of employment plus 10 years
                                </li>
                                <li>
                                    <strong>System Access Logs:</strong> Retained for 5 years
                                </li>
                                <li>
                                    <strong>Account Information:</strong> Retained until account deletion request or employment termination
                                </li>
                            </ul>
                        </section>

                        <section className="space-y-4">
                            <h2 className="text-2xl font-semibold">8. Your Rights Under the Data Privacy Act</h2>
                            <p className="text-muted-foreground">As a data subject, you have the following rights under Republic Act No. 10173:</p>
                            <ul className="text-muted-foreground list-disc pl-6">
                                <li>
                                    <strong>Right to Be Informed:</strong> You have the right to know how your personal data is being collected, used,
                                    and processed
                                </li>
                                <li>
                                    <strong>Right to Access:</strong> You may request access to your personal information held by {orgShortName}
                                </li>
                                <li>
                                    <strong>Right to Correction:</strong> You may request correction of inaccurate or incomplete personal data
                                </li>
                                <li>
                                    <strong>Right to Erasure:</strong> You may request deletion of your personal data, subject to legal retention
                                    requirements
                                </li>
                                <li>
                                    <strong>Right to Object:</strong> You may object to the processing of your personal data in certain circumstances
                                </li>
                                <li>
                                    <strong>Right to Data Portability:</strong> You may request a copy of your personal data in a commonly used
                                    electronic format
                                </li>
                                <li>
                                    <strong>Right to File a Complaint:</strong> You may file a complaint with the National Privacy Commission if you
                                    believe your rights have been violated
                                </li>
                            </ul>
                        </section>

                        <section className="space-y-4">
                            <h2 className="text-2xl font-semibold">9. Exercising Your Rights</h2>
                            <p className="text-muted-foreground">
                                To exercise any of your data privacy rights, please submit a written request to our Data Protection Officer. We will
                                respond to your request within 30 days. You may be asked to verify your identity before we process your request.
                            </p>
                        </section>

                        <section className="space-y-4">
                            <h2 className="text-2xl font-semibold">10. Cookies and Tracking</h2>
                            <p className="text-muted-foreground">The System uses cookies and similar technologies to:</p>
                            <ul className="text-muted-foreground list-disc pl-6">
                                <li>Maintain user sessions and authentication</li>
                                <li>Remember user preferences</li>
                                <li>Analyze System usage and performance</li>
                                <li>Enhance security</li>
                            </ul>
                            <p className="text-muted-foreground mt-4">
                                Essential cookies are required for the System to function properly. You may configure your browser to refuse cookies,
                                but this may affect System functionality.
                            </p>
                        </section>

                        <section className="space-y-4">
                            <h2 className="text-2xl font-semibold">11. Children's Privacy</h2>
                            <p className="text-muted-foreground">
                                The {appName} is designed for use by faculty and administrators who are adults. We do not knowingly collect personal
                                information from individuals under 18 years of age for account creation purposes. Student information processed
                                through the System is handled in accordance with applicable privacy laws and parental consent requirements.
                            </p>
                        </section>

                        <section className="space-y-4">
                            <h2 className="text-2xl font-semibold">12. Data Breach Notification</h2>
                            <p className="text-muted-foreground">
                                In the event of a personal data breach that poses a real risk of serious harm to affected individuals, {orgShortName}{" "}
                                will:
                            </p>
                            <ul className="text-muted-foreground list-disc pl-6">
                                <li>Notify the National Privacy Commission within 72 hours of becoming aware of the breach</li>
                                <li>Notify affected data subjects when required by law</li>
                                <li>Document the breach and remediation measures taken</li>
                                <li>Implement measures to prevent future breaches</li>
                            </ul>
                        </section>

                        <section className="space-y-4">
                            <h2 className="text-2xl font-semibold">13. Changes to This Privacy Policy</h2>
                            <p className="text-muted-foreground">
                                {orgShortName} may update this Privacy Policy from time to time to reflect changes in our practices or legal
                                requirements. We will notify users of any material changes through the System or via email. Continued use of the
                                System after such notification constitutes acceptance of the updated Privacy Policy.
                            </p>
                        </section>

                        <section className="space-y-4">
                            <h2 className="text-2xl font-semibold">14. Contact Information</h2>
                            <p className="text-muted-foreground">
                                For questions, concerns, or requests regarding this Privacy Policy or your personal data, please contact:
                            </p>
                            <div className="bg-muted/40 mt-4 rounded-lg border p-4">
                                <p className="text-sm">
                                    <strong>Data Protection Officer</strong>
                                    <br />
                                    {organizationName}
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
                            <div className="bg-muted/40 mt-4 rounded-lg border p-4">
                                <p className="text-sm">
                                    <strong>IT Department</strong>
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
                            <h2 className="text-2xl font-semibold">15. National Privacy Commission</h2>
                            <p className="text-muted-foreground">
                                If you believe that your data privacy rights have been violated and {orgShortName} has not adequately addressed your
                                concerns, you may file a complaint with:
                            </p>
                            <div className="bg-muted/40 mt-4 rounded-lg border p-4">
                                <p className="text-sm">
                                    <strong>National Privacy Commission</strong>
                                    <br />
                                    3rd Floor, Core G, GSIS Headquarters Building
                                    <br />
                                    Financial Center, Pasay City 1308, Philippines
                                    <br />
                                    Website: www.privacy.gov.ph
                                    <br />
                                    Email: info@privacy.gov.ph
                                </p>
                            </div>
                        </section>

                        <section className="space-y-4">
                            <h2 className="text-2xl font-semibold">16. Acknowledgment</h2>
                            <p className="text-muted-foreground">
                                By using the {appName}, you acknowledge that you have read, understood, and agree to this Privacy Policy. You consent
                                to the collection, use, and disclosure of your personal information as described herein, in accordance with the Data
                                Privacy Act of 2012.
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
                            href="/terms-of-service"
                            className="ring-offset-background focus-visible:ring-ring bg-primary text-primary-foreground hover:bg-primary/90 inline-flex h-10 items-center justify-center rounded-md px-4 py-2 text-sm font-medium transition-colors focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none disabled:pointer-events-none disabled:opacity-50"
                        >
                            Terms of Service
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    );
}
