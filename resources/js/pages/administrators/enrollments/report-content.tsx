import { Fragment, type CSSProperties } from "react";

type ReportContentProps = {
    data: Record<string, unknown>;
};
type ReportPayload = Record<string, unknown> & {
    type: string;
    title?: string;
    subtitle?: string;
};
type ChedGenderCounts = { male: number; female: number };
type ChedEnrollmentYearKey = "year_5" | "year_6" | "year_7";
type ChedReportRow = {
    program_title: string;
    program_code: string;
    major: string | null;
    major_code: string | null;
    with_thesis: string;
    program_status: string;
    delivery_mode: string;
    credit_units: number;
    enrolment: {
        new_freshmen: ChedGenderCounts;
        old_first_year: ChedGenderCounts;
        year_2: ChedGenderCounts;
        year_3: ChedGenderCounts;
        year_4: ChedGenderCounts;
        year_5?: ChedGenderCounts;
        year_6?: ChedGenderCounts;
        year_7?: ChedGenderCounts;
        subtotal: ChedGenderCounts;
        total: number;
    };
    graduates: { male: number; female: number; total: number };
};

function isReportPayload(value: unknown): value is ReportPayload {
    return typeof value === "object" && value !== null && "type" in value && typeof value.type === "string";
}

/**
 * ReportContent renders the formal PDF-style report inside the preview modal.
 * Uses inline styles so the content prints correctly via window.print().
 */
export function ReportContent({ data }: ReportContentProps) {
    const school = data.school as { name: string; logo: string; contact: string; email: string; address: string } | undefined;
    const report = isReportPayload(data.report) ? data.report : isReportPayload(data) ? data : null;
    const schoolYear = data.school_year as string;
    const semester = data.semester as string;
    const generatedAt = data.generated_at as string;
    const generatedBy = data.generated_by as string;

    if (!report) return null;

    const reportType = report.type;
    const title = report.title ?? "Report";
    const subtitle = report.subtitle ?? "";
    const filtersApplied = report.filters_applied as Record<string, string | null> | undefined;

    if (reportType === "ched_eform_bc") {
        return <ChedReportContent data={data} />;
    }

    return (
        <div style={{ fontFamily: "'Times New Roman', Times, serif", color: "#000", fontSize: "9pt", lineHeight: 1.3 }}>
            {/* Header */}
            <div style={{ textAlign: "center", marginBottom: 15, borderBottom: "2px solid #000", paddingBottom: 10 }}>
                {school?.logo && <img src={school.logo} alt="School Logo" style={{ height: 50, marginBottom: 4 }} crossOrigin="anonymous" />}
                <h1 style={{ fontSize: "14pt", fontWeight: "bold", textTransform: "uppercase", letterSpacing: 1, marginBottom: 2 }}>
                    {school?.name || "KoAkademy"}
                </h1>
                <div style={{ fontSize: "8pt", color: "#333", marginBottom: 2 }}>{school?.address || "123 Example Street, Sample City"}</div>
                <div style={{ fontSize: "8pt", color: "#333" }}>
                    Tel: {school?.contact || "444-5389/442-4160"}
                    {school?.email ? ` | Email: ${school.email}` : ""}
                </div>
            </div>

            {/* Title */}
            <div style={{ textAlign: "center", margin: "12px 0 6px" }}>
                <h2 style={{ fontSize: "12pt", fontWeight: "bold", textTransform: "uppercase" }}>{title}</h2>
                <div style={{ fontSize: "10pt", color: "#333", marginTop: 2 }}>{subtitle}</div>
            </div>

            {/* Meta */}
            <div
                style={{
                    display: "flex",
                    justifyContent: "space-between",
                    fontSize: "8pt",
                    color: "#555",
                    marginBottom: 10,
                    borderBottom: "1px solid #ccc",
                    paddingBottom: 6,
                }}
            >
                <div>
                    <strong>School Year:</strong> {schoolYear} | <strong>Semester:</strong> {semester}
                </div>
                <div>
                    <strong>Generated:</strong> {generatedAt} | <strong>By:</strong> {generatedBy}
                </div>
            </div>

            {/* Filters Applied */}
            {filtersApplied && Object.keys(filtersApplied).length > 0 && (
                <div style={{ display: "flex", gap: 10, flexWrap: "wrap", marginBottom: 10, fontSize: "8pt" }}>
                    <strong>Filters:</strong>
                    {Object.entries(filtersApplied).map(([key, value]) =>
                        value ? (
                            <span key={key} style={{ background: "#f0f0f0", padding: "2px 6px", borderRadius: 3 }}>
                                {key}: {value}
                            </span>
                        ) : null,
                    )}
                </div>
            )}

            {/* Report Body */}
            {reportType === "enrolled_by_course" && <EnrolledByCourseReport report={report} />}
            {reportType === "enrolled_by_subject" && <EnrolledBySubjectReport report={report} />}
            {reportType === "enrollment_summary" && <EnrollmentSummaryReport report={report} />}

            {/* Footer */}
            <div
                style={{
                    marginTop: 20,
                    borderTop: "1px solid #ccc",
                    paddingTop: 8,
                    display: "flex",
                    justifyContent: "space-between",
                    fontSize: "8pt",
                    color: "#555",
                }}
            >
                <div>This is a system-generated report.</div>
                <div>Page 1 of 1</div>
            </div>

            {/* Signatures */}
            <div style={{ marginTop: 30, display: "flex", justifyContent: "space-between" }}>
                <div style={{ textAlign: "center", width: 180 }}>
                    <div style={{ borderTop: "1px solid #000", marginTop: 30, paddingTop: 4, fontSize: "9pt" }}>Prepared By</div>
                    <div style={{ fontSize: "8pt", color: "#555" }}>{generatedBy}</div>
                </div>
                <div style={{ textAlign: "center", width: 180 }}>
                    <div style={{ borderTop: "1px solid #000", marginTop: 30, paddingTop: 4, fontSize: "9pt" }}>Noted By</div>
                    <div style={{ fontSize: "8pt", color: "#555" }}>Registrar</div>
                </div>
            </div>
        </div>
    );
}

function EnrolledByCourseReport({ report }: { report: Record<string, unknown> }) {
    const students = report.students as Array<{
        no: number;
        student_id: string | number | null;
        full_name: string | null;
        course: string | null;
        department: string | null;
        year_level: number | null;
        subjects_count: number;
        status: string | null;
    }>;
    const totalCount = report.total_count as number;

    const tableStyle: CSSProperties = { width: "100%", borderCollapse: "collapse", marginBottom: 12, fontSize: "8pt" };
    const thStyle: CSSProperties = {
        background: "#f3f4f6",
        color: "#111827",
        padding: "4px 6px",
        textAlign: "left",
        fontWeight: "bold",
        fontSize: "8pt",
        textTransform: "uppercase",
        borderBottom: "1px solid #d1d5db",
    };
    const tdStyle: CSSProperties = { padding: "4px 6px", borderBottom: "1px solid #e5e7eb" };
    const tdAltStyle: CSSProperties = { ...tdStyle, background: "#fafafa" };

    return (
        <div>
            <div style={{ marginBottom: 12, fontSize: "10pt" }}>
                <strong>Total Students:</strong> {totalCount}
            </div>
            <table style={tableStyle}>
                <thead>
                    <tr>
                        <th style={thStyle}>No.</th>
                        <th style={thStyle}>Student ID</th>
                        <th style={thStyle}>Full Name</th>
                        <th style={thStyle}>Course</th>
                        <th style={thStyle}>Department</th>
                        <th style={thStyle}>Year Level</th>
                        <th style={thStyle}>Subjects</th>
                        <th style={thStyle}>Status</th>
                    </tr>
                </thead>
                <tbody>
                    {students && students.length > 0 ? (
                        students.map((student, index) => (
                            <tr key={index}>
                                <td style={index % 2 === 1 ? tdAltStyle : tdStyle}>{student.no}</td>
                                <td style={index % 2 === 1 ? tdAltStyle : tdStyle}>{student.student_id || "—"}</td>
                                <td style={index % 2 === 1 ? tdAltStyle : tdStyle}>{student.full_name || "—"}</td>
                                <td style={index % 2 === 1 ? tdAltStyle : tdStyle}>{student.course || "—"}</td>
                                <td style={index % 2 === 1 ? tdAltStyle : tdStyle}>{student.department || "—"}</td>
                                <td style={index % 2 === 1 ? tdAltStyle : tdStyle}>{student.year_level ? `Year ${student.year_level}` : "—"}</td>
                                <td style={index % 2 === 1 ? tdAltStyle : tdStyle}>{student.subjects_count}</td>
                                <td style={index % 2 === 1 ? tdAltStyle : tdStyle}>{student.status || "—"}</td>
                            </tr>
                        ))
                    ) : (
                        <tr>
                            <td colSpan={8} style={{ ...tdStyle, textAlign: "center", padding: 20, color: "#999" }}>
                                No students found matching the selected filters.
                            </td>
                        </tr>
                    )}
                </tbody>
                {students && students.length > 0 && (
                    <tfoot>
                        <tr>
                            <td colSpan={6} style={{ ...tdStyle, fontWeight: "bold", borderTop: "2px solid #333", background: "#f0f0f0" }}>
                                Total
                            </td>
                            <td style={{ ...tdStyle, fontWeight: "bold", borderTop: "2px solid #333", background: "#f0f0f0" }}>
                                {students.reduce((sum, s) => sum + s.subjects_count, 0)}
                            </td>
                            <td style={{ ...tdStyle, fontWeight: "bold", borderTop: "2px solid #333", background: "#f0f0f0" }}>
                                {totalCount} students
                            </td>
                        </tr>
                    </tfoot>
                )}
            </table>
        </div>
    );
}

function EnrolledBySubjectReport({ report }: { report: Record<string, unknown> }) {
    const subjectGroups = report.subject_groups as Array<{
        subject_code: string;
        subject_title: string;
        subject_units: number;
        total_enrolled: number;
        students: Array<{
            no: number;
            student_id: string | number | null;
            full_name: string | null;
            course: string | null;
            year_level: number | null;
            section: string;
            class_schedule: string;
        }>;
    }>;
    const totalCount = report.total_count as number;

    const tableStyle: CSSProperties = { width: "100%", borderCollapse: "collapse", marginBottom: 12, fontSize: "8pt" };
    const thStyle: CSSProperties = {
        background: "#f3f4f6",
        color: "#111827",
        padding: "4px 6px",
        textAlign: "left",
        fontWeight: "bold",
        fontSize: "8pt",
        textTransform: "uppercase",
        borderBottom: "1px solid #d1d5db",
    };
    const tdStyle: CSSProperties = { padding: "4px 6px", borderBottom: "1px solid #e5e7eb" };
    const tdAltStyle: CSSProperties = { ...tdStyle, background: "#fafafa" };

    return (
        <div>
            <div style={{ marginBottom: 12, fontSize: "10pt" }}>
                <strong>Total Enrollments:</strong> {totalCount} | <strong>Subjects:</strong> {subjectGroups?.length || 0}
            </div>
            {subjectGroups && subjectGroups.length > 0 ? (
                subjectGroups.map((group, groupIndex) => (
                    <div key={groupIndex} style={{ marginBottom: 20 }}>
                        <div
                            style={{
                                background: "#f0f0f0",
                                padding: "8px 10px",
                                fontWeight: "bold",
                                border: "1px solid #ddd",
                                borderBottom: "none",
                                fontSize: "11pt",
                            }}
                        >
                            {group.subject_code} - {group.subject_title}
                            <span style={{ fontWeight: "normal", color: "#555", fontSize: "10pt", marginLeft: 8 }}>
                                ({group.total_enrolled} student{group.total_enrolled !== 1 ? "s" : ""} | {group.subject_units} units)
                            </span>
                        </div>
                        <table style={tableStyle}>
                            <thead>
                                <tr>
                                    <th style={thStyle}>No.</th>
                                    <th style={thStyle}>Student ID</th>
                                    <th style={thStyle}>Full Name</th>
                                    <th style={thStyle}>Course</th>
                                    <th style={thStyle}>Year</th>
                                    <th style={thStyle}>Section</th>
                                </tr>
                            </thead>
                            <tbody>
                                {group.students.map((student, index) => (
                                    <tr key={index}>
                                        <td style={index % 2 === 1 ? tdAltStyle : tdStyle}>{student.no}</td>
                                        <td style={index % 2 === 1 ? tdAltStyle : tdStyle}>{student.student_id || "—"}</td>
                                        <td style={index % 2 === 1 ? tdAltStyle : tdStyle}>{student.full_name || "—"}</td>
                                        <td style={index % 2 === 1 ? tdAltStyle : tdStyle}>{student.course || "—"}</td>
                                        <td style={index % 2 === 1 ? tdAltStyle : tdStyle}>
                                            {student.year_level ? `Year ${student.year_level}` : "—"}
                                        </td>
                                        <td style={index % 2 === 1 ? tdAltStyle : tdStyle}>{student.section}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                ))
            ) : (
                <div style={{ textAlign: "center", padding: 40, color: "#999", fontSize: "11pt" }}>
                    No subject enrollment data found matching the selected filters.
                </div>
            )}
        </div>
    );
}

function EnrollmentSummaryReport({ report }: { report: Record<string, unknown> }) {
    const totalEnrolled = report.total_enrolled as number;
    const byDepartment = report.by_department as Array<{ department: string; count: number }>;
    const byCourse = report.by_course as Array<{ course_code: string; course_title: string; department: string; count: number }>;
    const byYearLevel = report.by_year_level as Array<{ year_level: number; count: number }>;
    const byStatus = report.by_status as Array<{ status: string; count: number }>;

    const tableStyle: CSSProperties = { width: "100%", borderCollapse: "collapse", marginBottom: 12, fontSize: "8pt" };
    const thStyle: CSSProperties = {
        background: "#f3f4f6",
        color: "#111827",
        padding: "4px 6px",
        textAlign: "left",
        fontWeight: "bold",
        fontSize: "8pt",
        textTransform: "uppercase",
        borderBottom: "1px solid #d1d5db",
    };
    const tdStyle: CSSProperties = { padding: "4px 6px", borderBottom: "1px solid #e5e7eb" };
    const tdAltStyle: CSSProperties = { ...tdStyle, background: "#fafafa" };

    return (
        <div>
            {/* Summary Cards */}
            <div style={{ display: "grid", gridTemplateColumns: "repeat(3, 1fr)", gap: 12, marginBottom: 20 }}>
                <div style={{ border: "1px solid #ddd", padding: 12, textAlign: "center", borderRadius: 4 }}>
                    <div style={{ fontSize: "20pt", fontWeight: "bold" }}>{totalEnrolled}</div>
                    <div style={{ fontSize: "9pt", color: "#555", textTransform: "uppercase" }}>Total Enrolled</div>
                </div>
                <div style={{ border: "1px solid #ddd", padding: 12, textAlign: "center", borderRadius: 4 }}>
                    <div style={{ fontSize: "20pt", fontWeight: "bold" }}>{byDepartment?.length || 0}</div>
                    <div style={{ fontSize: "9pt", color: "#555", textTransform: "uppercase" }}>Departments</div>
                </div>
                <div style={{ border: "1px solid #ddd", padding: 12, textAlign: "center", borderRadius: 4 }}>
                    <div style={{ fontSize: "20pt", fontWeight: "bold" }}>{byCourse?.length || 0}</div>
                    <div style={{ fontSize: "9pt", color: "#555", textTransform: "uppercase" }}>Programs</div>
                </div>
            </div>

            {/* By Department */}
            <div style={{ marginBottom: 20 }}>
                <h3 style={{ fontSize: "12pt", fontWeight: "bold", marginBottom: 8, borderBottom: "1px solid #333", paddingBottom: 4 }}>
                    Enrollment by Department
                </h3>
                <table style={tableStyle}>
                    <thead>
                        <tr>
                            <th style={thStyle}>Department</th>
                            <th style={thStyle}>Count</th>
                            <th style={thStyle}>Percentage</th>
                        </tr>
                    </thead>
                    <tbody>
                        {byDepartment?.map((item, index) => (
                            <tr key={index}>
                                <td style={index % 2 === 1 ? tdAltStyle : tdStyle}>{item.department}</td>
                                <td style={index % 2 === 1 ? tdAltStyle : tdStyle}>{item.count}</td>
                                <td style={index % 2 === 1 ? tdAltStyle : tdStyle}>
                                    {totalEnrolled > 0 ? ((item.count / totalEnrolled) * 100).toFixed(1) : 0}%
                                </td>
                            </tr>
                        ))}
                    </tbody>
                    <tfoot>
                        <tr>
                            <td style={{ ...tdStyle, fontWeight: "bold", borderTop: "2px solid #333", background: "#f0f0f0" }}>Total</td>
                            <td style={{ ...tdStyle, fontWeight: "bold", borderTop: "2px solid #333", background: "#f0f0f0" }}>{totalEnrolled}</td>
                            <td style={{ ...tdStyle, fontWeight: "bold", borderTop: "2px solid #333", background: "#f0f0f0" }}>100%</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            {/* By Course */}
            <div style={{ marginBottom: 20 }}>
                <h3 style={{ fontSize: "12pt", fontWeight: "bold", marginBottom: 8, borderBottom: "1px solid #333", paddingBottom: 4 }}>
                    Enrollment by Course/Program
                </h3>
                <table style={tableStyle}>
                    <thead>
                        <tr>
                            <th style={thStyle}>Code</th>
                            <th style={thStyle}>Program Title</th>
                            <th style={thStyle}>Department</th>
                            <th style={thStyle}>Count</th>
                            <th style={thStyle}>Percentage</th>
                        </tr>
                    </thead>
                    <tbody>
                        {byCourse?.map((item, index) => (
                            <tr key={index}>
                                <td style={index % 2 === 1 ? tdAltStyle : tdStyle}>{item.course_code}</td>
                                <td style={index % 2 === 1 ? tdAltStyle : tdStyle}>{item.course_title}</td>
                                <td style={index % 2 === 1 ? tdAltStyle : tdStyle}>{item.department}</td>
                                <td style={index % 2 === 1 ? tdAltStyle : tdStyle}>{item.count}</td>
                                <td style={index % 2 === 1 ? tdAltStyle : tdStyle}>
                                    {totalEnrolled > 0 ? ((item.count / totalEnrolled) * 100).toFixed(1) : 0}%
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {/* By Year Level */}
            <div style={{ marginBottom: 20 }}>
                <h3 style={{ fontSize: "12pt", fontWeight: "bold", marginBottom: 8, borderBottom: "1px solid #333", paddingBottom: 4 }}>
                    Enrollment by Year Level
                </h3>
                <table style={tableStyle}>
                    <thead>
                        <tr>
                            <th style={thStyle}>Year Level</th>
                            <th style={thStyle}>Count</th>
                            <th style={thStyle}>Percentage</th>
                        </tr>
                    </thead>
                    <tbody>
                        {byYearLevel?.map((item, index) => (
                            <tr key={index}>
                                <td style={index % 2 === 1 ? tdAltStyle : tdStyle}>Year {item.year_level}</td>
                                <td style={index % 2 === 1 ? tdAltStyle : tdStyle}>{item.count}</td>
                                <td style={index % 2 === 1 ? tdAltStyle : tdStyle}>
                                    {totalEnrolled > 0 ? ((item.count / totalEnrolled) * 100).toFixed(1) : 0}%
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {/* By Status */}
            <div style={{ marginBottom: 20 }}>
                <h3 style={{ fontSize: "12pt", fontWeight: "bold", marginBottom: 8, borderBottom: "1px solid #333", paddingBottom: 4 }}>
                    Enrollment by Status
                </h3>
                <table style={tableStyle}>
                    <thead>
                        <tr>
                            <th style={thStyle}>Status</th>
                            <th style={thStyle}>Count</th>
                            <th style={thStyle}>Percentage</th>
                        </tr>
                    </thead>
                    <tbody>
                        {byStatus?.map((item, index) => (
                            <tr key={index}>
                                <td style={index % 2 === 1 ? tdAltStyle : tdStyle}>{item.status}</td>
                                <td style={index % 2 === 1 ? tdAltStyle : tdStyle}>{item.count}</td>
                                <td style={index % 2 === 1 ? tdAltStyle : tdStyle}>
                                    {totalEnrolled > 0 ? ((item.count / totalEnrolled) * 100).toFixed(1) : 0}%
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

function ChedReportContent({ data }: ReportContentProps) {
    const directReport = isReportPayload(data.report) ? data.report : isReportPayload(data) ? data : null;
    if (directReport?.type !== "ched_eform_bc") return null;

    const report = directReport as ReportPayload & {
        summary?: { total_programs?: number; total_enrolled?: number; total_graduates?: number };
        sheets?: Record<string, ChedReportRow[]>;
    };
    const school = data.school as { name: string; logo: string; contact: string; email: string; address: string } | undefined;
    const schoolYear = typeof data.school_year === "string" && data.school_year !== "" ? data.school_year : null;
    const semester = typeof data.semester === "string" && data.semester !== "" ? data.semester : null;
    const generatedAt = typeof data.generated_at === "string" && data.generated_at !== "" ? data.generated_at : null;
    const generatedBy = typeof data.generated_by === "string" && data.generated_by !== "" ? data.generated_by : null;
    const reportTitle = report.title ?? "CHED E-Form B/C - Curriculum Program Profile, Enrolment & Graduates";
    const reportSubtitle = report.subtitle ?? null;
    const periodDetails = [schoolYear ? `Academic Year: ${schoolYear}` : null, semester ? `Term: ${semester}` : null].filter(
        (detail): detail is string => detail !== null,
    );
    const enrolmentYearColumns: Array<{ key: ChedEnrollmentYearKey; label: string }> = [
        { key: "year_5", label: "5th Yr" },
        { key: "year_6", label: "6th Yr" },
        { key: "year_7", label: "7th Yr" },
    ];

    const tableStyle: CSSProperties = { width: "100%", borderCollapse: "collapse", fontSize: "7.5pt", marginBottom: 20 };
    const thStyle: CSSProperties = {
        border: "1px solid #111",
        padding: "4px 3px",
        textAlign: "center",
        backgroundColor: "#f3f4f6",
        fontWeight: "bold",
    };
    const tdStyle: CSSProperties = { border: "1px solid #ccc", padding: "3px 4px" };
    const numStyle: CSSProperties = { textAlign: "right", fontFamily: "monospace" };

    return (
        <div style={{ fontFamily: "Arial, sans-serif", color: "#000", fontSize: "8pt", lineHeight: 1.2 }}>
            <div style={{ textAlign: "center", marginBottom: 12, borderBottom: "2px solid #000", paddingBottom: 8 }}>
                <h1 style={{ fontSize: "13pt", fontWeight: "bold", textTransform: "uppercase", letterSpacing: 0.5, margin: 0 }}>
                    COMMISSION ON HIGHER EDUCATION (CHED)
                </h1>
                <h2 style={{ fontSize: "11pt", fontWeight: "bold", margin: "2px 0" }}>{reportTitle}</h2>
                {reportSubtitle && <div style={{ fontSize: "8pt", color: "#444", marginBottom: 2 }}>{reportSubtitle}</div>}
                <div style={{ fontSize: "8pt", color: "#444" }}>
                    Institution: {school?.name || "KoAkademy"}
                    {periodDetails.length > 0 ? ` | ${periodDetails.join(" | ")}` : ""}
                </div>
            </div>

            <div style={{ display: "flex", justifyContent: "space-between", fontSize: "8pt", color: "#555", marginBottom: 12 }}>
                <div>
                    Programs: <strong>{report.summary?.total_programs ?? 0}</strong> | Enrolment:{" "}
                    <strong>{report.summary?.total_enrolled ?? 0}</strong> | Graduates: <strong>{report.summary?.total_graduates ?? 0}</strong>
                </div>
                {(generatedAt || generatedBy) && (
                    <div>
                        {generatedAt ? `Generated: ${generatedAt}` : "Generated"}
                        {generatedBy ? ` (${generatedBy})` : ""}
                    </div>
                )}
            </div>

            {Object.entries(report.sheets ?? {}).map(([sheetName, rows]) => {
                const visibleEnrolmentYearColumns = enrolmentYearColumns.filter((column) =>
                    rows.some((row) => row.enrolment[column.key] !== undefined),
                );

                return (
                    <div key={sheetName} style={{ marginBottom: 25 }}>
                        <h3
                            style={{
                                fontSize: "10pt",
                                fontWeight: "bold",
                                textTransform: "uppercase",
                                margin: "0 0 6px 0",
                                borderBottom: "1.5px solid #222",
                                paddingBottom: 2,
                            }}
                        >
                            Sheet: {sheetName} ({rows.length} {rows.length === 1 ? "Program" : "Programs"})
                        </h3>
                        <div style={{ overflowX: "auto" }}>
                            <table style={tableStyle}>
                                <thead>
                                    <tr>
                                        <th rowSpan={2} style={thStyle}>
                                            Program Title
                                        </th>
                                        <th rowSpan={2} style={thStyle}>
                                            Code
                                        </th>
                                        <th rowSpan={2} style={thStyle}>
                                            Major
                                        </th>
                                        <th rowSpan={2} style={thStyle}>
                                            Status
                                        </th>
                                        <th rowSpan={2} style={thStyle}>
                                            Mode
                                        </th>
                                        <th rowSpan={2} style={thStyle}>
                                            Units
                                        </th>
                                        <th colSpan={2} style={thStyle}>
                                            New Freshmen
                                        </th>
                                        <th colSpan={2} style={thStyle}>
                                            Old 1st Yr
                                        </th>
                                        <th colSpan={2} style={thStyle}>
                                            2nd Yr
                                        </th>
                                        <th colSpan={2} style={thStyle}>
                                            3rd Yr
                                        </th>
                                        <th colSpan={2} style={thStyle}>
                                            4th Yr
                                        </th>
                                        {visibleEnrolmentYearColumns.map((column) => (
                                            <th key={column.key} colSpan={2} style={thStyle}>
                                                {column.label}
                                            </th>
                                        ))}
                                        <th colSpan={2} style={thStyle}>
                                            Sub-Total
                                        </th>
                                        <th rowSpan={2} style={{ ...thStyle, backgroundColor: "#e5e7eb" }}>
                                            Enrol Total
                                        </th>
                                        <th colSpan={3} style={{ ...thStyle, backgroundColor: "#fef3c7" }}>
                                            Graduates
                                        </th>
                                    </tr>
                                    <tr>
                                        <th style={thStyle}>M</th>
                                        <th style={thStyle}>F</th>
                                        <th style={thStyle}>M</th>
                                        <th style={thStyle}>F</th>
                                        <th style={thStyle}>M</th>
                                        <th style={thStyle}>F</th>
                                        <th style={thStyle}>M</th>
                                        <th style={thStyle}>F</th>
                                        <th style={thStyle}>M</th>
                                        <th style={thStyle}>F</th>
                                        {visibleEnrolmentYearColumns.map((column) => (
                                            <Fragment key={column.key}>
                                                <th style={thStyle}>M</th>
                                                <th style={thStyle}>F</th>
                                            </Fragment>
                                        ))}
                                        <th style={thStyle}>M</th>
                                        <th style={thStyle}>F</th>
                                        <th style={thStyle}>M</th>
                                        <th style={thStyle}>F</th>
                                        <th style={thStyle}>Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {rows.map((row, rowIndex) => (
                                        <tr key={rowIndex} style={{ backgroundColor: rowIndex % 2 === 1 ? "#fafafa" : "#fff" }}>
                                            <td style={{ ...tdStyle, fontWeight: "500" }}>{row.program_title}</td>
                                            <td style={{ ...tdStyle, textAlign: "center" }}>{row.program_code}</td>
                                            <td style={tdStyle}>{row.major || "—"}</td>
                                            <td style={{ ...tdStyle, textAlign: "center" }}>{row.program_status}</td>
                                            <td style={{ ...tdStyle, textAlign: "center" }}>{row.delivery_mode}</td>
                                            <td style={{ ...tdStyle, ...numStyle }}>{row.credit_units}</td>
                                            <td style={{ ...tdStyle, ...numStyle }}>{row.enrolment.new_freshmen.male}</td>
                                            <td style={{ ...tdStyle, ...numStyle }}>{row.enrolment.new_freshmen.female}</td>
                                            <td style={{ ...tdStyle, ...numStyle }}>{row.enrolment.old_first_year.male}</td>
                                            <td style={{ ...tdStyle, ...numStyle }}>{row.enrolment.old_first_year.female}</td>
                                            <td style={{ ...tdStyle, ...numStyle }}>{row.enrolment.year_2.male}</td>
                                            <td style={{ ...tdStyle, ...numStyle }}>{row.enrolment.year_2.female}</td>
                                            <td style={{ ...tdStyle, ...numStyle }}>{row.enrolment.year_3.male}</td>
                                            <td style={{ ...tdStyle, ...numStyle }}>{row.enrolment.year_3.female}</td>
                                            <td style={{ ...tdStyle, ...numStyle }}>{row.enrolment.year_4.male}</td>
                                            <td style={{ ...tdStyle, ...numStyle }}>{row.enrolment.year_4.female}</td>
                                            {visibleEnrolmentYearColumns.map((column) => (
                                                <Fragment key={column.key}>
                                                    <td style={{ ...tdStyle, ...numStyle }}>{row.enrolment[column.key]?.male ?? 0}</td>
                                                    <td style={{ ...tdStyle, ...numStyle }}>{row.enrolment[column.key]?.female ?? 0}</td>
                                                </Fragment>
                                            ))}
                                            <td style={{ ...tdStyle, ...numStyle, fontWeight: "bold" }}>{row.enrolment.subtotal.male}</td>
                                            <td style={{ ...tdStyle, ...numStyle, fontWeight: "bold" }}>{row.enrolment.subtotal.female}</td>
                                            <td style={{ ...tdStyle, ...numStyle, fontWeight: "bold", backgroundColor: "#f3f4f6" }}>
                                                {row.enrolment.total}
                                            </td>
                                            <td style={{ ...tdStyle, ...numStyle }}>{row.graduates.male}</td>
                                            <td style={{ ...tdStyle, ...numStyle }}>{row.graduates.female}</td>
                                            <td style={{ ...tdStyle, ...numStyle, fontWeight: "bold", backgroundColor: "#fef9c3" }}>
                                                {row.graduates.total}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                );
            })}
        </div>
    );
}
