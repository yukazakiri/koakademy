import { Button } from "@/components/ui/button";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Head, usePage } from "@inertiajs/react";
import { ArrowLeft, Printer } from "lucide-react";
import { useCallback, useRef, useState } from "react";
import { route } from "ziggy-js";

// Paper sizes in mm (Philippines standard)
const PAPER_SIZES = {
    a4: {
        name: "A4 (210 x 297 mm)",
        width: 297,
        height: 210,
        cssWidth: "297mm",
        cssHeight: "210mm",
    },
    long: {
        name: 'Long Bond (8.5" x 13")',
        width: 330.2,
        height: 215.9,
        cssWidth: "330.2mm",
        cssHeight: "215.9mm",
    },
    short: {
        name: 'Short Bond (8.5" x 11")',
        width: 279.4,
        height: 215.9,
        cssWidth: "279.4mm",
        cssHeight: "215.9mm",
    },
} as const;

type PaperSize = keyof typeof PAPER_SIZES;

interface AssessmentData {
    student: {
        full_name: string;
        student_id: string;
        course_code: string;
    };
    enrollment: {
        school_year: string;
        semester: number;
        semester_label: string;
    };
    subjects: Array<{
        code: string;
        title: string;
        units: number;
        is_modular: boolean;
        lecture_fee: number;
        laboratory_fee: number;
        schedule: {
            monday: string;
            tuesday: string;
            wednesday: string;
            thursday: string;
            friday: string;
            saturday: string;
        };
    }>;
    totals: {
        units: number;
        lecture: number;
        laboratory: number;
        modular_subjects: number;
        modular_fee: number;
    };
    additional_fees: Array<{
        name: string;
        amount: number;
        is_required: boolean;
    }>;
    additional_fees_total: number;
    tuition: {
        total_lectures: number;
        total_laboratory: number;
        total_miscelaneous_fees: number;
        discount: number;
        downpayment: number;
        overall_tuition: number;
        total_balance: number;
    } | null;
    total_amount: number;
    school: {
        name: string;
        logo: string;
        contact: string;
        email: string;
        address: string;
    };
    generated_at: string;
}

interface PageProps {
    data: AssessmentData;
    enrollmentId: number;
}

interface Branding {
    currency: string;
}

export default function AssessmentPreview({ data, enrollmentId }: PageProps) {
    const { props } = usePage<{ branding?: Branding }>();
    const currency = props.branding?.currency || "PHP";

    const formatMoney = (amount: number | null | undefined) => {
        if (amount === null || amount === undefined) return currency === "USD" ? "$0.00" : "₱0.00";
        return new Intl.NumberFormat(currency === "USD" ? "en-US" : "en-PH", {
            style: "currency",
            currency: currency,
        }).format(amount);
    };

    const [paperSize, setPaperSize] = useState<PaperSize>("long");
    const printRef = useRef<HTMLDivElement>(null);

    const handlePrint = useCallback(() => {
        window.print();
    }, []);

    const handleDownloadPdf = useCallback(() => {
        window.open(route("assessment.download", { record: enrollmentId }), "_blank");
    }, [enrollmentId]);

    const handleBack = useCallback(() => {
        if (window.history.length > 1) {
            window.history.back();
        } else {
            window.location.href = route("administrators.enrollments.index");
        }
    }, []);

    const daysOfWeek = ["monday", "tuesday", "wednesday", "thursday", "friday", "saturday"] as const;
    const selectedPaper = PAPER_SIZES[paperSize];

    // Generate isolated document styles
    const documentStyles = `
    /* ==========================================
       ASSESSMENT DOCUMENT - ISOLATED STYLES
       These styles are scoped to .assessment-doc
       to prevent conflicts with the app theme
       ========================================== */
    
    /* Reset all inherited styles within the document */
    .assessment-doc,
    .assessment-doc * {
      all: revert;
      box-sizing: border-box;
      -webkit-print-color-adjust: exact !important;
      print-color-adjust: exact !important;
    }
    
    .assessment-doc {
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important;
      font-size: 8pt !important;
      line-height: 1.3 !important;
      color: #000000 !important;
      background-color: #ffffff !important;
      -webkit-font-smoothing: antialiased;
      -moz-osx-font-smoothing: grayscale;
      position: relative;
      overflow: hidden; /* Ensure no bleed */
    }
    
    /* Typography */
    .assessment-doc h1,
    .assessment-doc h2,
    .assessment-doc h3,
    .assessment-doc p,
    .assessment-doc span,
    .assessment-doc td,
    .assessment-doc th,
    .assessment-doc strong,
    .assessment-doc div {
      color: #000000 !important;
      margin: 0;
      padding: 0;
    }
    
    .assessment-doc strong {
      font-weight: 700 !important;
    }
    
    /* Header */
    .assessment-doc .doc-header {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 12px;
      margin-bottom: 12px;
      padding-bottom: 8px;
      border-bottom: 2px solid #000000;
      width: 100%;
    }
    
    .assessment-doc .doc-header img {
      width: 55px;
      height: 55px;
      object-fit: contain;
    }
    
    .assessment-doc .doc-header-text {
      text-align: center;
      flex: 1;
    }
    
    .assessment-doc .doc-header h1 {
      font-size: 12pt !important;
      font-weight: 700 !important;
      margin: 0 0 2px 0 !important;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    
    .assessment-doc .doc-header .school-info {
      font-size: 7pt !important;
      color: #374151 !important;
      margin: 2px 0 !important;
    }
    
    .assessment-doc .doc-header .doc-title {
      font-size: 11pt !important;
      font-weight: 700 !important;
      margin-top: 6px !important;
      text-transform: uppercase;
      letter-spacing: 1px;
    }
    
    /* Student Info Box */
    .assessment-doc .student-info {
      background-color: #f3f4f6 !important;
      padding: 10px 12px;
      border-radius: 4px;
      font-size: 7.5pt !important;
      margin-bottom: 10px;
      border: 1px solid #d1d5db;
      width: 100%;
    }
    
    .assessment-doc .student-info p {
      margin-bottom: 3px !important;
      color: #000000 !important;
    }
    
    .assessment-doc .student-info p:last-child {
      margin-bottom: 0 !important;
    }
    
    /* Tables */
    .assessment-doc table {
      width: 100%;
      border-collapse: collapse;
      font-size: 7pt !important;
      margin-bottom: 10px;
      table-layout: fixed; /* Force table layout to respect widths */
    }
    
    .assessment-doc table th {
      background-color: #1e40af !important;
      color: #ffffff !important;
      font-weight: 600 !important;
      padding: 5px 6px !important;
      text-align: left;
      border: 1px solid #1e3a8a !important;
      overflow: hidden;
      white-space: nowrap;
      text-overflow: ellipsis;
    }
    
    .assessment-doc table th.text-center {
      text-align: center !important;
    }
    
    .assessment-doc table th.text-right {
      text-align: right !important;
    }
    
    .assessment-doc table td {
      padding: 4px 6px !important;
      border: 1px solid #d1d5db !important;
      color: #000000 !important;
      background-color: #ffffff !important;
      overflow: hidden;
      white-space: nowrap;
      text-overflow: ellipsis;
    }
    
    .assessment-doc table td.text-center {
      text-align: center !important;
    }
    
    .assessment-doc table td.text-right {
      text-align: right !important;
    }
    
    .assessment-doc table tr.total-row {
      background-color: #e5e7eb !important;
      font-weight: 700 !important;
    }
    
    .assessment-doc table tr.total-row td {
      background-color: #e5e7eb !important;
      font-weight: 700 !important;
    }
    
    .assessment-doc table tr.modular-note td {
      background-color: #faf5ff !important;
      color: #6b21a8 !important;
      font-size: 6.5pt !important;
      font-style: italic;
      white-space: normal; /* Allow wrapping for the note */
    }
    
    /* Schedule table cell highlighting */
    .assessment-doc .schedule-cell-filled {
      background-color: #dbeafe !important;
    }
    
    /* Badge styles */
    .assessment-doc .badge-modular {
      background-color: #e9d5ff !important;
      color: #6b21a8 !important;
      padding: 1px 5px;
      border-radius: 3px;
      font-size: 6pt !important;
      font-weight: 600 !important;
      display: inline-block;
    }
    
    .assessment-doc .badge-regular {
      color: #6b7280 !important;
      font-size: 6pt !important;
    }
    
    /* Right column - Fees breakdown */
    .assessment-doc .fees-column {
      border-left: 2px solid #d1d5db;
      padding-left: 12px;
    }
    
    .assessment-doc .fees-section {
      background-color: #f9fafb !important;
      padding: 10px;
      border-radius: 4px;
      height: 100%;
      border: 1px solid #e5e7eb;
    }
    
    .assessment-doc .fees-section h2 {
      font-size: 10pt !important;
      font-weight: 700 !important;
      color: #000000 !important;
      border-bottom: 2px solid #1e40af;
      padding-bottom: 6px;
      margin-bottom: 10px !important;
    }
    
    /* Fee boxes */
    .assessment-doc .fee-box {
      background-color: #ffffff !important;
      padding: 8px 10px;
      border-radius: 4px;
      border: 1px solid #d1d5db;
      margin-bottom: 8px;
      font-size: 7pt !important;
      width: 100%;
    }
    
    .assessment-doc .fee-box-title {
      font-weight: 700 !important;
      margin-bottom: 4px !important;
      color: #000000 !important;
      font-size: 7.5pt !important;
      border-bottom: 1px solid #e5e7eb;
      padding-bottom: 3px;
    }
    
    .assessment-doc .fee-box p {
      margin-bottom: 3px !important;
      color: #000000 !important;
    }
    
    .assessment-doc .fee-box .fee-total {
      border-top: 1px solid #d1d5db;
      padding-top: 4px;
      margin-top: 4px !important;
      font-weight: 700 !important;
    }
    
    .assessment-doc .fee-box .modular-text {
      color: #6b21a8 !important;
    }
    
    .assessment-doc .fee-box .required-tag {
      color: #dc2626 !important;
      font-size: 6pt !important;
      margin-left: 4px;
    }
    
    /* Summary box */
    .assessment-doc .summary-box {
      background-color: #eff6ff !important;
      padding: 8px 10px;
      border-radius: 4px;
      border: 2px solid #93c5fd;
      margin-bottom: 8px;
      font-size: 7pt !important;
      width: 100%;
    }
    
    .assessment-doc .summary-box p {
      color: #000000 !important;
    }
    
    .assessment-doc .summary-box .summary-title {
      font-weight: 700 !important;
      margin-bottom: 4px !important;
      font-size: 8pt !important;
    }
    
    .assessment-doc .summary-box .grand-total {
      border-top: 2px solid #3b82f6;
      padding-top: 4px;
      margin-top: 4px !important;
      font-weight: 700 !important;
    }
    
    .assessment-doc .summary-box .balance {
      font-size: 9pt !important;
      font-weight: 700 !important;
      color: #1e40af !important;
    }
    
    /* Signatures */
    .assessment-doc .signatures {
      margin-top: 24px;
      width: 100%;
    }
    
    .assessment-doc .signature-line {
      margin-bottom: 24px;
    }
    
    .assessment-doc .signature-line .line {
      border-bottom: 1px solid #000000;
      width: 160px;
    }
    
    .assessment-doc .signature-line p {
      font-size: 7pt !important;
      margin-top: 3px !important;
      color: #374151 !important;
    }
    
    /* Layout */
    .assessment-doc .two-column {
      display: flex;
      gap: 16px;
      width: 100%;
      flex-direction: row; /* Enforce row */
    }
    
    .assessment-doc .left-column {
      flex: 0 0 65%; /* Rigid width */
      width: 65%;
      max-width: 65%;
    }
    
    .assessment-doc .right-column {
      flex: 0 0 35%; /* Rigid width */
      width: 35%;
      max-width: 35%;
    }
    
    /* ==========================================
       PRINT STYLES
       ========================================== */
    @media print {
      @page {
        size: ${selectedPaper.cssWidth} ${selectedPaper.cssHeight};
        margin: 0;
      }
      
      /* 1. Hide everything in the body first */
      body {
        visibility: hidden !important;
        overflow: visible !important;
      }

      /* 2. Target our specific print wrapper and make it visible */
      .print-wrapper {
        visibility: visible !important;
        display: block !important;
        
        /* 3. Break out of any parent layout (flex/grid) completely */
        position: fixed !important;
        top: 0 !important;
        left: 0 !important;
        right: 0 !important;
        bottom: 0 !important;
        z-index: 9999 !important;
        
        /* 4. Reset dimensions to fill the paper */
        width: 100% !important;
        height: 100% !important;
        
        /* 5. Clean background */
        background: white !important;
        margin: 0 !important;
        padding: 0 !important;
      }

      /* 6. Ensure the document inside expands correctly */
      .assessment-doc {
        visibility: visible !important;
        display: block !important;
        width: 100% !important;
        /* Use standard padding for the paper */
        padding: 10mm !important;
        margin: 0 !important;
        background: white !important;
        
        /* Reset any potential layout inheritance */
        position: static !important;
        box-shadow: none !important;
        border: none !important;
        transform: none !important;
      }

      /* 7. Hide non-print controls specifically */
      .no-print {
        display: none !important;
      }
      
      /* 8. Force Layout Resets for inner content */
      .assessment-doc .two-column {
        display: flex !important;
        flex-direction: row !important;
        gap: 16px !important;
        width: 100% !important;
      }
      
      .assessment-doc .left-column {
        width: 65% !important;
        flex: 0 0 65% !important;
      }
      
      .assessment-doc .right-column {
        width: 35% !important;
        flex: 0 0 35% !important;
      }

      /* 9. Prevent page breaks inside components */
      .assessment-doc .fee-box,
      .assessment-doc .summary-box,
      .assessment-doc table,
      .assessment-doc .student-info {
        break-inside: avoid !important;
        page-break-inside: avoid !important;
      }
    }
  `;

    return (
        <>
            <Head title={`Assessment - ${data.student.full_name}`} />

            {/* Inject isolated document styles */}
            <style dangerouslySetInnerHTML={{ __html: documentStyles }} />

            {/* Main Container - Dark Background for contrast */}
            <div className="print-wrapper flex min-h-screen items-start justify-center overflow-auto bg-[#525659] py-8">
                {/* Print Controls - Hidden when printing */}
                <div className="no-print fixed top-4 right-4 z-50 flex items-center gap-3 rounded-lg border border-gray-200 bg-white p-2 shadow-lg">
                    <div className="flex items-center gap-2">
                        <span className="text-xs text-gray-600">Paper:</span>
                        <Select value={paperSize} onValueChange={(v) => setPaperSize(v as PaperSize)}>
                            <SelectTrigger className="h-8 w-[180px] text-xs">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {Object.entries(PAPER_SIZES).map(([key, { name }]) => (
                                    <SelectItem key={key} value={key} className="text-xs">
                                        {name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <Button variant="outline" size="sm" onClick={handleBack} className="h-8">
                        <ArrowLeft className="mr-1 h-3 w-3" />
                        Back
                    </Button>
                    <Button size="sm" onClick={handlePrint} className="h-8 bg-black text-white hover:bg-gray-800">
                        <Printer className="mr-1 h-3 w-3" />
                        Print
                    </Button>
                </div>

                {/* Document Preview Container */}
                <div className="no-print-label relative">
                    <div className="no-print absolute -top-6 left-0 text-[10px] font-medium text-white/70">Previewing: {selectedPaper.name}</div>

                    {/* The Printable Document - Fully Isolated from Theme */}
                    <div
                        ref={printRef}
                        className="assessment-doc"
                        style={{
                            width: selectedPaper.cssWidth,
                            minHeight: selectedPaper.cssHeight,
                            padding: "8mm",
                            boxShadow: "0 25px 50px -12px rgba(0, 0, 0, 0.5)",
                        }}
                    >
                        {/* Document Header */}
                        <div className="doc-header">
                            <img src={data.school.logo} alt="School Logo" />
                            <div className="doc-header-text">
                                <h1>{data.school.name}</h1>
                                <p className="school-info">
                                    {data.school.address}
                                    {data.school.contact && ` | Tel: ${data.school.contact}`}
                                    {data.school.email && ` | Email: ${data.school.email}`}
                                </p>
                                <p className="doc-title">Assessment Form</p>
                            </div>
                        </div>

                        {/* Two Column Layout */}
                        <div className="two-column">
                            {/* Left Column - Subjects & Schedule */}
                            <div className="left-column">
                                {/* Student Information */}
                                <div className="student-info">
                                    <p>
                                        <strong>Course:</strong> {data.student.course_code}
                                    </p>
                                    <p>
                                        <strong>Full Name:</strong> {data.student.full_name} | <strong>ID:</strong> {data.student.student_id}
                                    </p>
                                    <p>
                                        <strong>Semester/School Year:</strong> {data.enrollment.semester_label} {data.enrollment.school_year}
                                    </p>
                                    <p>
                                        <strong>Date Generated:</strong> {data.generated_at}
                                    </p>
                                </div>

                                {/* Subjects Table */}
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Code</th>
                                            <th>Subject Title</th>
                                            <th className="text-center">Type</th>
                                            <th className="text-center">Units</th>
                                            <th className="text-right">Lec Fee</th>
                                            <th className="text-right">Lab Fee</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {data.subjects.map((subject, idx) => (
                                            <tr key={idx}>
                                                <td>{subject.code}</td>
                                                <td>{subject.title}</td>
                                                <td className="text-center">
                                                    {subject.is_modular ? (
                                                        <span className="badge-modular">Modular</span>
                                                    ) : (
                                                        <span className="badge-regular">Regular</span>
                                                    )}
                                                </td>
                                                <td className="text-center">{subject.units}</td>
                                                <td className="text-right">
                                                    {subject.lecture_fee.toLocaleString("en-PH", { minimumFractionDigits: 2 })}
                                                </td>
                                                <td className="text-right">
                                                    {subject.laboratory_fee.toLocaleString("en-PH", { minimumFractionDigits: 2 })}
                                                </td>
                                            </tr>
                                        ))}
                                        <tr className="total-row">
                                            <td colSpan={3}>
                                                <strong>TOTAL</strong>
                                            </td>
                                            <td className="text-center">
                                                <strong>{data.totals.units}</strong>
                                            </td>
                                            <td className="text-right">
                                                <strong>{data.totals.lecture.toLocaleString("en-PH", { minimumFractionDigits: 2 })}</strong>
                                            </td>
                                            <td className="text-right">
                                                <strong>{data.totals.laboratory.toLocaleString("en-PH", { minimumFractionDigits: 2 })}</strong>
                                            </td>
                                        </tr>
                                        {data.totals.modular_subjects > 0 && (
                                            <tr className="modular-note">
                                                <td colSpan={6}>
                                                    * {data.totals.modular_subjects} Modular Subject(s) @ {formatMoney(2400)} each =
                                                    {formatMoney(data.totals.modular_fee)}
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>

                                {/* Class Schedule Table */}
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Subject</th>
                                            <th className="text-center">Mon</th>
                                            <th className="text-center">Tue</th>
                                            <th className="text-center">Wed</th>
                                            <th className="text-center">Thu</th>
                                            <th className="text-center">Fri</th>
                                            <th className="text-center">Sat</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {data.subjects.map((subject, idx) => (
                                            <tr key={idx}>
                                                <td>{subject.title}</td>
                                                {daysOfWeek.map((day) => (
                                                    <td key={day} className={`text-center ${subject.schedule[day] ? "schedule-cell-filled" : ""}`}>
                                                        {subject.schedule[day] || "-"}
                                                    </td>
                                                ))}
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>

                            {/* Right Column - Fee Breakdown */}
                            <div className="right-column fees-column">
                                <div className="fees-section">
                                    <h2>Breakdown of Fees</h2>

                                    {/* Tuition Fee Details */}
                                    <div className="fee-box">
                                        <p className="fee-box-title">Tuition Fee Details</p>
                                        <p>Sub-Total (Tuition): {formatMoney(data.totals.lecture)}</p>
                                        <p>Discount: {data.tuition?.discount || 0}%</p>
                                        <p className="fee-total">Total Tuition: {formatMoney(data.tuition?.total_lectures)}</p>
                                    </div>

                                    {/* Additional Fees */}
                                    <div className="fee-box">
                                        <p className="fee-box-title">Additional Fees</p>
                                        <p>Laboratory Fee: {formatMoney(data.tuition?.total_laboratory)}</p>
                                        <p>Miscellaneous Fee: {formatMoney(data.tuition?.total_miscelaneous_fees)}</p>
                                        {data.totals.modular_subjects > 0 && (
                                            <p className="modular-text">
                                                Modular Fee ({data.totals.modular_subjects} subjects): {formatMoney(data.totals.modular_fee)}
                                            </p>
                                        )}
                                        {data.additional_fees.map((fee, idx) => (
                                            <p key={idx}>
                                                {fee.name}: {formatMoney(fee.amount)}
                                                {fee.is_required && <span className="required-tag">(Required)</span>}
                                            </p>
                                        ))}
                                        {data.additional_fees_total > 0 && (
                                            <p className="fee-total">Additional Total: {formatMoney(data.additional_fees_total)}</p>
                                        )}
                                    </div>

                                    {/* Payment Summary */}
                                    <div className="summary-box">
                                        <p className="summary-title">Payment Summary</p>
                                        <p>Tuition Fee: {formatMoney(data.tuition?.total_lectures)}</p>
                                        <p>Laboratory Fee: {formatMoney(data.tuition?.total_laboratory)}</p>
                                        <p>Miscellaneous Fee: {formatMoney(data.tuition?.total_miscelaneous_fees)}</p>
                                        {data.totals.modular_subjects > 0 && (
                                            <p className="modular-text" style={{ fontSize: "6pt" }}>
                                                * Modular fees ({data.totals.modular_subjects}) included in tuition
                                            </p>
                                        )}
                                        {data.additional_fees_total > 0 && <p>Additional Fees: {formatMoney(data.additional_fees_total)}</p>}
                                        <p className="grand-total">Total Amount: {formatMoney(data.tuition?.overall_tuition || data.total_amount)}</p>
                                        <p>Downpayment: {formatMoney(data.tuition?.downpayment)}</p>
                                        <p className="balance">Balance: {formatMoney(data.tuition?.total_balance)}</p>
                                    </div>

                                    {/* Signature Lines */}
                                    <div className="signatures">
                                        <div className="signature-line">
                                            <div className="line" />
                                            <p>Assessed By</p>
                                        </div>
                                        <div className="signature-line">
                                            <div className="line" />
                                            <p>Student Signature</p>
                                        </div>
                                        <div className="signature-line">
                                            <div className="line" />
                                            <p>Registrar</p>
                                        </div>
                                        <div className="signature-line">
                                            <div className="line" />
                                            <p>Cashier</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}
