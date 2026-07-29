import { Head } from "@inertiajs/react";
import { AlertTriangle, Ban, CheckCircle2, ShieldX } from "lucide-react";

interface VerifiedDocument {
    type: string;
    document_number: string;
    student: string;
    student_number: string;
    issued_at: string;
    institution: string;
}

export default function VerifyFinancialDocument({
    status,
    document,
}: {
    status: "valid" | "revoked" | "invalid" | "integrity_failed";
    document?: VerifiedDocument;
}) {
    const valid = status === "valid";
    const Icon = valid ? CheckCircle2 : status === "revoked" ? Ban : status === "integrity_failed" ? ShieldX : AlertTriangle;
    const title = valid
        ? "Official document verified"
        : status === "revoked"
          ? "Document revoked"
          : status === "integrity_failed"
            ? "Integrity check failed"
            : "Document not found";

    return (
        <>
            <Head title="Financial Document Verification" />
            <main className="flex min-h-screen items-center justify-center bg-slate-100 p-4 text-slate-900">
                <section className="w-full max-w-xl overflow-hidden rounded-xl border border-slate-300 bg-white shadow-xl">
                    <header className={`border-b px-7 py-7 text-center ${valid ? "border-emerald-200 bg-emerald-50" : "border-red-200 bg-red-50"}`}>
                        <Icon className={`mx-auto size-14 ${valid ? "text-emerald-700" : "text-red-700"}`} />
                        <h1 className="mt-3 text-2xl font-bold">{title}</h1>
                        <p className="mt-1 text-sm text-slate-600">
                            {valid
                                ? "This record matches the document issued by the institution."
                                : "Do not accept this as a current official record."}
                        </p>
                    </header>
                    {document ? (
                        <div className="p-7">
                            <p className="text-center text-xs tracking-[0.16em] text-slate-500 uppercase">{document.institution}</p>
                            <h2 className="mt-2 text-center font-mono text-lg font-bold">{document.document_number}</h2>
                            <p className="mt-1 text-center text-sm font-medium text-slate-600">{document.type}</p>
                            <dl className="mt-6 grid grid-cols-2 border border-slate-300 text-sm">
                                {Object.entries({
                                    Student: document.student,
                                    "Student no.": document.student_number,
                                    Issued: document.issued_at,
                                }).map(([key, value]) => (
                                    <div key={key} className="border-b border-slate-200 p-3">
                                        <dt className="text-xs text-slate-500 uppercase">{key}</dt>
                                        <dd className="mt-1 font-semibold">{value}</dd>
                                    </div>
                                ))}
                            </dl>
                        </div>
                    ) : null}
                    <footer className="border-t border-slate-200 bg-slate-50 px-6 py-4 text-center text-xs text-slate-500">
                        Verification results come directly from the institution portal.
                    </footer>
                </section>
            </main>
        </>
    );
}
