import { type CSSProperties } from "react";

type ReportContentProps = {
    data: Record<string, unknown>;
};

/**
 * ReportContent renders the formal PDF-style report inside the preview modal.
 * Uses inline styles so the content prints correctly via window.print().
 */
export function ReportContent({ data }: ReportContentProps) {
    const school = data.school as { name: string; logo: string; contact: string; email: string; address: string } | undefined;
    const report = data.report as Record<string, unknown>;
    const schoolYear = data.school_year as string;
    const semester = data.semester as string;
    const generatedAt = data.generated_at as string;
    const generatedBy = data.generated_by as string;

    if (!report) return null;

    const reportType = report.type as string;
    const title = report.title as string;
    const subtitle = report.subtitle as string;
    const filtersApplied = report.filters_applied as Record<string, string | null> | undefined;

    return (
        <div style={{ fontFamily: "'Times New Roman', Times, serif", color: "#000", fontSize: "9pt", lineHeight: 1.3 }}>
            {/* Header */}
            <div style={{ textAlign: "center", marginBottom: 15, borderBottom: "2px solid #000", paddingBottom: 10 }}>
                {school?.logo && <img src={school.logo} alt="School Logo" style={{ height: 50, marginBottom: 4 }} crossOrigin="anonymous" />}
                <h1 style={{ fontSize: "14pt", fontWeight: "bold", textTransform: "uppercase", letterSpacing: 1, marginBottom: 2 }}>
                    {school?.name || "KoAkademy"}
                </h1>
                <div style={{ fontSize: "8pt", color: "#333", marginBottom: 2 }}>
                    {school?.address || "118 Bonifacio Street, Holyghost Proper, Baguio City"}
                </div>
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
