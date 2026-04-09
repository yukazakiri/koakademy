import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Label } from "@/components/ui/label";
import { Separator } from "@/components/ui/separator";
import { cn } from "@/lib/utils";
import { usePage } from "@inertiajs/react";
import { AlertCircle, BookOpen, CalendarIcon, CheckCircle, FileText, ListIcon, Printer } from "lucide-react";
import React, { useEffect, useState } from "react";
import { toast } from "sonner";
import type { Branding, PrintOption, StudentDetail } from "../types";

export function PrintScheduleDialog({
    open,
    onOpenChange,
    student,
    initialOption = "both",
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    student: StudentDetail;
    initialOption?: PrintOption;
}) {
    const { props } = usePage<{ branding?: Branding }>();
    const orgName = props.branding?.organizationName || "KoAkademy";
    const [printOption, setPrintOption] = useState<PrintOption>(initialOption);

    useEffect(() => {
        if (open) {
            setPrintOption(initialOption);
        }
    }, [initialOption, open]);

    const handlePrint = () => {
        const printWindow = window.open("", "_blank", "width=1000,height=800");
        if (!printWindow) {
            toast.error("Please allow popups to print.");
            return;
        }

        try {
            const showSubjects = printOption === "subjects" || printOption === "both";
            const showSchedule = printOption === "schedule" || printOption === "both";
            const showChecklistCompleted = printOption === "checklist_completed";
            const showChecklistFull = printOption === "checklist_full";
            const showTranscript = printOption === "transcript";
            const isChecklist = showChecklistCompleted || showChecklistFull;

            // Common styles
            const commonStyles = `
*{margin:0;padding:0;box-sizing:border-box}
@page{size:letter portrait;margin:0.4in}
html,body{font-family:Arial,Helvetica,sans-serif;font-size:9pt;color:#222;background:#fff}
.page{width:100%;max-width:8in;margin:0 auto;padding:0.3in;page-break-after:always;page-break-inside:avoid}
.page:last-child{page-break-after:avoid}
.header{text-align:center;padding-bottom:8px;margin-bottom:12px;border-bottom:2px solid #222}
.header h1{font-size:13pt;font-weight:700;text-transform:uppercase;letter-spacing:1px;margin-bottom:2px}
.header p{font-size:8pt;color:#555}
.header .title{display:inline-block;margin-top:8px;padding:4px 16px;border:1.5px solid #222;font-size:10pt;font-weight:700;text-transform:uppercase;letter-spacing:0.5px}
.info{display:flex;justify-content:space-between;padding:8px 12px;margin-bottom:12px;background:#f8f8f8;border:1px solid #ddd;font-size:8pt}
.info div{line-height:1.6}
.info strong{display:inline-block;min-width:70px}
table{width:100%;border-collapse:collapse;font-size:8pt;margin-bottom:10px}
th,td{border:1px solid #333;padding:4px 6px;text-align:left;vertical-align:top}
th{background:#e8e8e8;font-weight:700;text-transform:uppercase;font-size:7pt}
.center{text-align:center}
tfoot td{background:#f0f0f0;font-weight:700}
.footer{display:flex;justify-content:space-between;margin-top:20px;padding-top:10px}
.sig{width:140px;border-top:1px solid #222;padding-top:4px;text-align:center;font-size:7pt}
.printed{text-align:right;font-size:6pt;color:#888;margin-top:8px}
.section{margin-top:16px}
.section-head{font-size:9pt;font-weight:700;padding:6px 8px;background:#e8e8e8;border:1px solid #333;border-bottom:none;text-transform:uppercase}
.passed{color:#16a34a}
.failed{color:#dc2626}
.pending{color:#888}
.badge{display:inline-block;padding:1px 6px;border-radius:2px;font-size:6pt;font-weight:600;text-transform:uppercase}
.badge-passed{background:#dcfce7;color:#166534;border:1px solid #86efac}
.badge-progress{background:#fef3c7;color:#92400e;border:1px solid #fcd34d}
.badge-pending{background:#f3f4f6;color:#6b7280;border:1px solid #d1d5db}
.summary{display:flex;gap:20px;padding:10px 12px;background:#f8f8f8;border:1px solid #ddd;margin-bottom:12px;font-size:8pt}
.summary-item{display:flex;flex-direction:column;align-items:center}
.summary-item .num{font-size:14pt;font-weight:700}
.summary-item .lbl{font-size:6pt;text-transform:uppercase;color:#666}
.doc-meta{margin-bottom:12px;padding:10px 12px;border:1px solid #333;background:#fafafa;font-size:8pt;line-height:1.7}
.doc-meta strong{display:inline-block;min-width:105px}
.transcript-year{margin-top:18px;padding:6px 8px;background:#dfe6ee;border:1px solid #333;font-size:9pt;font-weight:700;text-transform:uppercase}
.transcript-school{margin-top:10px;padding:5px 8px;background:#f3f4f6;border:1px solid #333;border-bottom:none;font-size:8pt;font-weight:700}
.transcript-term{padding:4px 8px;border:1px solid #333;border-top:none;border-bottom:none;font-size:7pt;font-weight:700;background:#fbfbfb;text-transform:uppercase}
.certify{margin-top:18px;font-size:8pt;line-height:1.8;text-align:justify}
@media print{html,body{-webkit-print-color-adjust:exact;print-color-adjust:exact}.page{padding:0;max-width:none}}`;

            const semLabel = student.current_semester === 1 ? "1st Sem" : student.current_semester === 2 ? "2nd Sem" : "Summer";

            let html = `<!DOCTYPE html><html><head><title>${student.name} - Record</title><style>${commonStyles}`;

            // Add schedule-specific styles if needed
            if (showSchedule) {
                html += `
.sched-title{font-size:9pt;font-weight:700;text-transform:uppercase;margin:12px 0 8px;padding-bottom:4px;border-bottom:1px solid #222}
.schedule{display:flex;border:1px solid #222;height:320px}
.times{width:32px;background:#f5f5f5;border-right:1px solid #222;position:relative;flex-shrink:0}
.time{position:absolute;font-size:6pt;color:#666;right:4px;transform:translateY(-50%)}
.days{flex:1;display:flex}
.day{flex:1;border-right:1px solid #ccc;display:flex;flex-direction:column}
.day:last-child{border-right:none}
.day-head{height:18px;background:#e8e8e8;border-bottom:1px solid #222;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:7pt;text-transform:uppercase}
.day-body{flex:1;position:relative}
.block{position:absolute;left:1px;right:1px;border-left:3px solid;padding:2px 3px;overflow:hidden;font-size:6pt;display:flex;flex-direction:column;justify-content:center}
.block .code{font-weight:700;color:#222}
.block .room{color:#555;font-size:5pt}
.legend{display:flex;flex-wrap:wrap;gap:8px;padding:6px 8px;background:#f8f8f8;border:1px solid #ddd;font-size:7pt;margin-top:8px}
.legend-item{display:flex;align-items:center;gap:3px}
.legend-item span{width:8px;height:8px;border:1px solid #222}`;
            }

            html += `</style></head><body>`;

            if (showTranscript) {
                const yearLevelLabel = (yearLevel: number | string | null) => {
                    const year = Number(yearLevel);

                    return year === 1
                        ? "1st Year"
                        : year === 2
                          ? "2nd Year"
                          : year === 3
                            ? "3rd Year"
                            : year === 4
                              ? "4th Year"
                              : yearLevel
                                ? `${yearLevel}th Year`
                                : "Unspecified Year";
                };

                const semesterLabel = (semester: number | string | null) => {
                    const sem = Number(semester);

                    return sem === 1 ? "1st Semester" : sem === 2 ? "2nd Semester" : sem === 3 ? "Summer" : "Unspecified Semester";
                };

                type TranscriptEntry = {
                    key: string;
                    yearLevel: number | null;
                    schoolName: string;
                    schoolYear: string;
                    semester: number | null;
                    code: string;
                    title: string;
                    units: number | string | null;
                    grade: string | number | null;
                    classification: string;
                    remarks: string;
                    creditedAs?: string;
                };

                const internalEntries: TranscriptEntry[] = [];
                const externalEntries: TranscriptEntry[] = [];
                const seenInternalKeys = new Set<string>();
                const seenExternalKeys = new Set<string>();

                student.checklist.forEach((yearGroup: any) => {
                    yearGroup.semesters.forEach((semesterGroup: any) => {
                        semesterGroup.subjects.forEach((subject: any) => {
                            const primaryRecord =
                                subject.enrollment_id || subject.grade !== null || subject.remarks || subject.classification !== "internal"
                                    ? {
                                          id: `primary-${subject.id}-${subject.enrollment_id ?? "none"}`,
                                          grade: subject.grade,
                                          remarks: subject.remarks,
                                          classification: subject.classification || "internal",
                                          school_name: subject.school_name || orgName,
                                          external_subject_code: subject.external_subject_code,
                                          external_subject_title: subject.external_subject_title,
                                          external_subject_units: subject.external_subject_units,
                                          school_year: subject.school_year,
                                          semester: subject.semester,
                                          academic_year: subject.academic_year ?? yearGroup.year,
                                      }
                                    : null;

                            const historyRecords = Array.isArray(subject.history)
                                ? subject.history.filter((history: any) => history.id !== subject.enrollment_id)
                                : [];

                            [primaryRecord, ...historyRecords].filter(Boolean).forEach((record: any) => {
                                const isExternal = record.classification === "credited";
                                const key = `${subject.id}-${record.id}`;

                                if (isExternal) {
                                    if (seenExternalKeys.has(key)) {
                                        return;
                                    }

                                    seenExternalKeys.add(key);
                                    externalEntries.push({
                                        key,
                                        yearLevel: Number(record.academic_year ?? yearGroup.year),
                                        schoolName: record.school_name || "External School",
                                        schoolYear: record.school_year || "N/A",
                                        semester: record.semester ?? semesterGroup.semester,
                                        code: record.external_subject_code || subject.code,
                                        title: record.external_subject_title || subject.title,
                                        units: record.external_subject_units ?? subject.units,
                                        grade: record.grade,
                                        classification: record.classification,
                                        remarks: record.remarks || "",
                                        creditedAs: `${subject.code} - ${subject.title}`,
                                    });

                                    return;
                                }

                                if (seenInternalKeys.has(key)) {
                                    return;
                                }

                                seenInternalKeys.add(key);
                                internalEntries.push({
                                    key,
                                    yearLevel: Number(record.academic_year ?? yearGroup.year),
                                    schoolName: orgName,
                                    schoolYear: record.school_year || student.current_school_year,
                                    semester: record.semester ?? semesterGroup.semester,
                                    code: subject.code,
                                    title: subject.title,
                                    units: subject.units,
                                    grade: record.grade,
                                    classification: record.classification || "internal",
                                    remarks: record.remarks || "",
                                });
                            });
                        });
                    });
                });

                student.non_credited_subjects.forEach((subject) => {
                    const key = `external-${subject.id}`;
                    if (seenExternalKeys.has(key)) {
                        return;
                    }

                    seenExternalKeys.add(key);
                    externalEntries.push({
                        key,
                        yearLevel: subject.academic_year,
                        schoolName: subject.school_name || "External School",
                        schoolYear: subject.school_year || "N/A",
                        semester: subject.semester,
                        code: subject.external_subject_code || "N/A",
                        title: subject.external_subject_title || "Untitled Subject",
                        units: subject.external_subject_units,
                        grade: subject.grade,
                        classification: "non_credited",
                        remarks: subject.remarks || "",
                        creditedAs: subject.linked_subject
                            ? `${subject.linked_subject.code} - ${subject.linked_subject.title}`
                            : "No equivalent subject",
                    });
                });

                const sortTranscriptEntries = (entries: TranscriptEntry[]) =>
                    [...entries].sort((left, right) => {
                        const leftYear = left.yearLevel ?? 99;
                        const rightYear = right.yearLevel ?? 99;
                        if (leftYear !== rightYear) return leftYear - rightYear;

                        if (left.schoolName !== right.schoolName) {
                            return left.schoolName.localeCompare(right.schoolName);
                        }

                        if (left.schoolYear !== right.schoolYear) {
                            return left.schoolYear.localeCompare(right.schoolYear);
                        }

                        if ((left.semester ?? 99) !== (right.semester ?? 99)) {
                            return (left.semester ?? 99) - (right.semester ?? 99);
                        }

                        return left.code.localeCompare(right.code);
                    });

                const sortedInternalEntries = sortTranscriptEntries(internalEntries);
                const sortedExternalEntries = sortTranscriptEntries(externalEntries);

                const renderTranscriptRows = (entries: TranscriptEntry[], includeCreditedAs: boolean) =>
                    entries
                        .map((entry) => {
                            const gradeValue = entry.grade ?? "—";
                            const gradeClass =
                                entry.grade !== null && entry.grade !== ""
                                    ? Number(entry.grade) <= 3.0 || Number(entry.grade) >= 75
                                        ? "passed"
                                        : "failed"
                                    : "";

                            return `
        <tr>
          <td><strong>${entry.code}</strong></td>
          <td>${entry.title}</td>
          <td class="center">${entry.units ?? "—"}</td>
          <td class="center ${gradeClass}">${gradeValue}</td>
          <td class="center">${entry.classification.replace("_", " ")}</td>
          ${includeCreditedAs ? `<td>${entry.creditedAs ?? "—"}</td>` : ""}
          <td>${entry.remarks || "—"}</td>
        </tr>`;
                        })
                        .join("");

                const renderTranscriptSection = (
                    entries: TranscriptEntry[],
                    title: string,
                    includeSchoolGrouping: boolean,
                    includeCreditedAs: boolean,
                ) => {
                    if (entries.length === 0) {
                        return `
  <div class="section">
    <div class="section-head">${title}</div>
    <table>
      <tbody>
        <tr><td class="center">No records available.</td></tr>
      </tbody>
    </table>
  </div>`;
                    }

                    let sectionHtml = `<div class="section"><div class="section-head">${title}</div>`;
                    let currentYear: number | null = null;
                    let currentSchoolName = "";
                    let currentTermKey = "";
                    let hasOpenTable = false;

                    entries.forEach((entry) => {
                        if (currentYear !== entry.yearLevel) {
                            if (hasOpenTable) {
                                sectionHtml += `</tbody></table>`;
                                hasOpenTable = false;
                            }

                            currentYear = entry.yearLevel;
                            currentSchoolName = "";
                            currentTermKey = "";
                            sectionHtml += `<div class="transcript-year">${yearLevelLabel(entry.yearLevel)}</div>`;
                        }

                        if (includeSchoolGrouping && currentSchoolName !== entry.schoolName) {
                            if (hasOpenTable) {
                                sectionHtml += `</tbody></table>`;
                                hasOpenTable = false;
                            }

                            currentSchoolName = entry.schoolName;
                            currentTermKey = "";
                            sectionHtml += `<div class="transcript-school">${entry.schoolName}</div>`;
                        }

                        const nextTermKey = `${entry.schoolYear}-${entry.semester}`;
                        if (currentTermKey !== nextTermKey) {
                            if (hasOpenTable) {
                                sectionHtml += `</tbody></table>`;
                            }

                            currentTermKey = nextTermKey;
                            hasOpenTable = true;
                            sectionHtml += `
  <div class="transcript-term">School Year ${entry.schoolYear} | ${semesterLabel(entry.semester)}</div>
  <table>
    <thead>
      <tr>
        <th style="width:78px">Code</th>
        <th>Descriptive Title</th>
        <th class="center" style="width:42px">Units</th>
        <th class="center" style="width:54px">Grade</th>
        <th class="center" style="width:72px">Class</th>
        ${includeCreditedAs ? '<th style="width:130px">Credited / Equivalent</th>' : ""}
        <th style="width:140px">Remarks</th>
      </tr>
    </thead>
    <tbody>`;
                        }

                        sectionHtml += renderTranscriptRows([entry], includeCreditedAs);
                    });

                    if (hasOpenTable) {
                        sectionHtml += `</tbody></table>`;
                    }

                    sectionHtml += `</div>`;

                    return sectionHtml;
                };

                html += `
<div class="page">
  <div class="header">
    <h1>${orgName}</h1>
    <p>Office of the Registrar</p>
    <div class="title">Official Transcript of Records</div>
  </div>
  <div class="doc-meta">
    <div><strong>Student Name:</strong> ${student.name}</div>
    <div><strong>Student Number:</strong> ${student.student_id ?? "—"}</div>
    <div><strong>Program:</strong> ${student.course.code} - ${student.course.title ?? ""}</div>
    <div><strong>Academic Standing:</strong> ${student.academic_year}</div>
    <div><strong>Date Issued:</strong> ${new Date().toLocaleDateString()}</div>
  </div>
  ${renderTranscriptSection(sortedInternalEntries, "Institutional Enrollment History", false, false)}
  ${renderTranscriptSection(sortedExternalEntries, "External School Academic Records", true, true)}
  <div class="certify">
    This transcript is issued upon request for official government and institutional purposes. It reflects the student's
    recorded enrollment history on file with the Registrar as of the date of printing.
  </div>
  <div class="footer">
    <div class="sig">Prepared by<br/>Registrar's Office</div>
    <div class="sig">Certified Correct<br/>School Registrar</div>
    <div class="sig">Date Issued</div>
  </div>
  <div class="printed">Printed: ${new Date().toLocaleString()}</div>
</div>`;
            }

            // Generate Schedule/Subjects pages
            if (!isChecklist && !showTranscript) {
                if (!student.current_enrolled_classes) {
                    toast.error("No schedule data available.");
                    printWindow.close();
                    return;
                }

                // Schedule grid generation
                const days = ["Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];
                const startHour = 7;
                const endHour = 20;
                const totalHours = endHour - startHour;

                const getTop = (time: string) => {
                    const [h, m] = time.split(":").map(Number);
                    return ((h + m / 60 - startHour) / totalHours) * 100;
                };

                const getHeight = (start: string, end: string) => {
                    const [sh, sm] = start.split(":").map(Number);
                    const [eh, em] = end.split(":").map(Number);
                    return ((eh + em / 60 - (sh + sm / 60)) / totalHours) * 100;
                };

                const scheduleHtml = days
                    .map((day) => {
                        const dayClasses = student.current_enrolled_classes.flatMap((cls) =>
                            cls.schedules
                                .filter((s) => {
                                    if (!s.day) return false;
                                    const d = s.day.toLowerCase();
                                    const target = day.toLowerCase();
                                    if (!(d.startsWith(target) || target.startsWith(d))) return false;
                                    const h = parseInt(s.start_time.split(":")[0], 10);
                                    return h >= startHour && h < endHour;
                                })
                                .map((s) => ({ ...s, ...cls })),
                        );

                        const blocks = dayClasses
                            .map(
                                (cls) => `
                        <div class="block" style="top:${getTop(cls.start_time)}%;height:${getHeight(cls.start_time, cls.end_time)}%;border-left-color:${cls.color};background:${cls.color}10;">
                            <span class="code">${cls.subject_code}</span>
                            <span class="room">${cls.room}</span>
                        </div>
                    `,
                            )
                            .join("");

                        return `<div class="day"><div class="day-head">${day}</div><div class="day-body">${blocks}</div></div>`;
                    })
                    .join("");

                const timeLabels = Array.from({ length: totalHours }, (_, i) => {
                    const h = startHour + i;
                    return `<div class="time" style="top:${(i / totalHours) * 100}%">${h > 12 ? h - 12 : h}${h >= 12 ? "pm" : "am"}</div>`;
                }).join("");

                const totalUnits = student.current_enrolled_classes.reduce((acc, cls) => acc + cls.units, 0);
                const subjectRows = student.current_enrolled_classes
                    .map(
                        (cls, i) => `
                    <tr>
                        <td>${i + 1}</td>
                        <td><strong>${cls.subject_code}</strong></td>
                        <td>${cls.subject_title}</td>
                        <td class="center">${cls.units}</td>
                        <td class="center">${cls.section}</td>
                        <td>${cls.schedules.map((s) => `${s.day} ${s.start_time.slice(0, 5)}-${s.end_time.slice(0, 5)}`).join("<br>")}</td>
                        <td>${cls.faculty}</td>
                    </tr>
                `,
                    )
                    .join("");

                if (showSubjects) {
                    html += `
<div class="page">
  <div class="header">
    <h1>${orgName}</h1>
    <p>Laoag City, Ilocos Norte</p>
    <div class="title">Certificate of Enrollment</div>
  </div>
  <div class="info">
    <div>
      <div><strong>Name:</strong> ${student.name}</div>
      <div><strong>ID No:</strong> ${student.student_id ?? "—"}</div>
      <div><strong>Course:</strong> ${student.course.code} - ${student.course.title ?? ""}</div>
    </div>
    <div style="text-align:right">
      <div><strong>Year:</strong> ${student.academic_year}</div>
      <div><strong>S.Y.:</strong> ${student.current_school_year}</div>
      <div><strong>Sem:</strong> ${semLabel}</div>
    </div>
  </div>
  <table>
    <thead>
      <tr>
        <th style="width:20px">#</th>
        <th style="width:70px">Code</th>
        <th>Subject Title</th>
        <th class="center" style="width:35px">Units</th>
        <th class="center" style="width:40px">Sec</th>
        <th style="width:100px">Schedule</th>
        <th>Instructor</th>
      </tr>
    </thead>
    <tbody>${subjectRows}</tbody>
    <tfoot>
      <tr>
        <td colspan="3" class="center">Total Units</td>
        <td class="center">${totalUnits}</td>
        <td colspan="3"></td>
      </tr>
    </tfoot>
  </table>
  ${
      !showSchedule
          ? `
  <div class="footer">
    <div class="sig">Registrar</div>
    <div class="sig">Date</div>
  </div>
  <div class="printed">Printed: ${new Date().toLocaleString()}</div>
  `
          : ""
  }
</div>`;
                }

                if (showSchedule) {
                    html += `
<div class="page">
  ${
      !showSubjects
          ? `
  <div class="header">
    <h1>${orgName}</h1>
    <p>Laoag City, Ilocos Norte</p>
    <div class="title">Class Schedule</div>
  </div>
  <div class="info">
    <div>
      <div><strong>Name:</strong> ${student.name}</div>
      <div><strong>ID No:</strong> ${student.student_id ?? "—"}</div>
      <div><strong>Course:</strong> ${student.course.code}</div>
    </div>
    <div style="text-align:right">
      <div><strong>Year:</strong> ${student.academic_year}</div>
      <div><strong>S.Y.:</strong> ${student.current_school_year}</div>
      <div><strong>Sem:</strong> ${semLabel}</div>
    </div>
  </div>
  `
          : `<div class="sched-title">Weekly Class Schedule</div>`
  }
  <div class="schedule">
    <div class="times">${timeLabels}</div>
    <div class="days">${scheduleHtml}</div>
  </div>
  <div class="legend">
    ${student.current_enrolled_classes
        .map(
            (cls) => `
      <div class="legend-item">
        <span style="background:${cls.color}"></span>
        <strong>${cls.subject_code}</strong> ${cls.subject_title} (${cls.units}u)
      </div>
    `,
        )
        .join("")}
  </div>
  <div class="footer">
    <div class="sig">Registrar</div>
    <div class="sig">Date</div>
  </div>
  <div class="printed">Printed: ${new Date().toLocaleString()}</div>
</div>`;
                }
            }

            // Generate Checklist pages
            if (isChecklist) {
                // Calculate stats
                let totalSubjects = 0;
                let completedSubjects = 0;
                let inProgressSubjects = 0;
                let totalUnits = 0;
                let completedUnits = 0;

                student.checklist.forEach((yearGroup: any) => {
                    yearGroup.semesters.forEach((sem: any) => {
                        sem.subjects.forEach((sub: any) => {
                            totalSubjects++;
                            totalUnits += Number(sub.units) || 0;
                            if (sub.status === "Completed") {
                                completedSubjects++;
                                completedUnits += Number(sub.units) || 0;
                            } else if (sub.status === "In Progress") {
                                inProgressSubjects++;
                            }
                        });
                    });
                });

                const pendingSubjects = totalSubjects - completedSubjects - inProgressSubjects;
                const progressPercent = totalSubjects > 0 ? Math.round((completedSubjects / totalSubjects) * 100) : 0;

                const documentTitle = showChecklistCompleted ? "Academic Transcript" : "Curriculum Checklist";

                html += `
<div class="page">
  <div class="header">
    <h1>${orgName}</h1>
    <p>Laoag City, Ilocos Norte</p>
    <div class="title">${documentTitle}</div>
  </div>
  <div class="info">
    <div>
      <div><strong>Name:</strong> ${student.name}</div>
      <div><strong>ID No:</strong> ${student.student_id ?? "—"}</div>
      <div><strong>Course:</strong> ${student.course.code} - ${student.course.title ?? ""}</div>
    </div>
    <div style="text-align:right">
      <div><strong>Year Level:</strong> ${student.academic_year}</div>
      <div><strong>S.Y.:</strong> ${student.current_school_year}</div>
      <div><strong>Status:</strong> ${student.status ?? "Active"}</div>
    </div>
  </div>

  <div class="summary">
    <div class="summary-item">
      <span class="num passed">${completedSubjects}</span>
      <span class="lbl">Completed</span>
    </div>
    ${
        showChecklistFull
            ? `
    <div class="summary-item">
      <span class="num" style="color:#f59e0b">${inProgressSubjects}</span>
      <span class="lbl">In Progress</span>
    </div>
    <div class="summary-item">
      <span class="num pending">${pendingSubjects}</span>
      <span class="lbl">Pending</span>
    </div>
    `
            : ""
    }
    <div class="summary-item">
      <span class="num">${completedUnits}/${totalUnits}</span>
      <span class="lbl">Units</span>
    </div>
    <div class="summary-item">
      <span class="num">${progressPercent}%</span>
      <span class="lbl">Progress</span>
    </div>
  </div>`;

                // Generate tables for each year/semester
                student.checklist.forEach((yearGroup: any) => {
                    const yearLabel =
                        yearGroup.year === 1
                            ? "1st Year"
                            : yearGroup.year === 2
                              ? "2nd Year"
                              : yearGroup.year === 3
                                ? "3rd Year"
                                : yearGroup.year === 4
                                  ? "4th Year"
                                  : `${yearGroup.year}th Year`;

                    yearGroup.semesters.forEach((sem: any) => {
                        const semesterLabel = sem.semester === 1 ? "1st Semester" : sem.semester === 2 ? "2nd Semester" : "Summer";

                        // Filter subjects based on option
                        const subjects = showChecklistCompleted ? sem.subjects.filter((s: any) => s.status === "Completed") : sem.subjects;

                        if (subjects.length === 0) return;

                        const semUnits = subjects.reduce((acc: number, s: any) => acc + (Number(s.units) || 0), 0);

                        html += `
  <div class="section">
    <div class="section-head">${yearLabel} - ${semesterLabel}</div>
    <table>
      <thead>
        <tr>
          <th style="width:70px">Code</th>
          <th>Subject Title</th>
          <th class="center" style="width:35px">Units</th>
          <th class="center" style="width:50px">Grade</th>
          ${showChecklistFull ? '<th class="center" style="width:60px">Status</th>' : ""}
          <th style="width:80px">Remarks</th>
        </tr>
      </thead>
      <tbody>`;

                        subjects.forEach((sub: any) => {
                            const gradeClass =
                                sub.grade && sub.grade !== "-" ? (Number(sub.grade) <= 3.0 || Number(sub.grade) >= 75 ? "passed" : "failed") : "";

                            const statusBadge =
                                sub.status === "Completed"
                                    ? '<span class="badge badge-passed">Passed</span>'
                                    : sub.status === "In Progress"
                                      ? '<span class="badge badge-progress">In Progress</span>'
                                      : '<span class="badge badge-pending">Pending</span>';

                            const classification =
                                sub.classification && sub.classification !== "internal" ? sub.classification.replace("_", " ") : "";

                            html += `
        <tr>
          <td><strong>${sub.code}</strong></td>
          <td>${sub.title}</td>
          <td class="center">${sub.units}</td>
          <td class="center ${gradeClass}"><strong>${sub.grade && sub.grade !== "-" ? sub.grade : "—"}</strong></td>
          ${showChecklistFull ? `<td class="center">${statusBadge}</td>` : ""}
          <td style="font-size:6pt">${classification}${sub.remarks ? (classification ? ", " : "") + sub.remarks : ""}</td>
        </tr>`;

                            if (sub.history && sub.history.length > 0) {
                                sub.history.forEach((hist: any, i: number) => {
                                    // Skip the record if it matches the primary record shown above
                                    if (hist.id === sub.enrollment_id) return;

                                    const hGradeClass =
                                        hist.grade && hist.grade !== "-"
                                            ? Number(hist.grade) <= 3.0 || Number(hist.grade) >= 75
                                                ? "passed"
                                                : "failed"
                                            : "";
                                    const hStatusBadge = hist.grade
                                        ? '<span class="badge badge-passed">Passed</span>'
                                        : '<span class="badge badge-progress">In Progress</span>';
                                    const hClassification =
                                        hist.classification && hist.classification !== "internal" ? hist.classification.replace("_", " ") : "";
                                    const hRemarks =
                                        `Take ${sub.history.length - i}: SY ${hist.school_year} (Sem ${hist.semester})` +
                                        (hist.remarks ? ` - ${hist.remarks}` : "");

                                    html += `
        <tr style="color: #666; font-style: italic; background-color: #fcfcfc;">
          <td style="padding-left: 15px;">↳ ${sub.code}</td>
          <td>${sub.title}</td>
          <td class="center">${sub.units}</td>
          <td class="center ${hGradeClass}">${hist.grade && hist.grade !== "-" ? hist.grade : "—"}</td>
          ${showChecklistFull ? `<td class="center">${hStatusBadge}</td>` : ""}
          <td style="font-size:6pt">${hClassification}${hRemarks ? (hClassification ? ", " : "") + hRemarks : ""}</td>
        </tr>`;
                                });
                            }
                        });

                        html += `
      </tbody>
      <tfoot>
        <tr>
          <td colspan="2" class="center">Subtotal</td>
          <td class="center">${semUnits}</td>
          <td colspan="${showChecklistFull ? "3" : "2"}"></td>
        </tr>
      </tfoot>
    </table>
  </div>`;
                    });
                });

                html += `
  <div class="footer">
    <div class="sig">Registrar</div>
    <div class="sig">Date</div>
  </div>
  <div class="printed">Printed: ${new Date().toLocaleString()}</div>
</div>`;
            }

            html += `<script>window.onload=()=>setTimeout(()=>{window.focus();window.print()},200)</script></body></html>`;

            printWindow.document.write(html);
            printWindow.document.close();
            onOpenChange(false);
        } catch (e) {
            console.error("Print generation failed:", e);
            toast.error("Failed to generate print view.");
            if (printWindow) printWindow.close();
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-[500px]">
                <DialogHeader>
                    <div className="flex items-center gap-3">
                        <div className="bg-primary/10 flex h-10 w-10 items-center justify-center rounded-full">
                            <Printer className="text-primary h-5 w-5" />
                        </div>
                        <div>
                            <DialogTitle>Print Student Record</DialogTitle>
                            <DialogDescription className="mt-1">Choose what to include in the printed document.</DialogDescription>
                        </div>
                    </div>
                </DialogHeader>

                <div className="space-y-4 py-4">
                    {/* Current Enrollment Section */}
                    <div className="space-y-3">
                        <Label className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">Current Enrollment</Label>
                        <div className="space-y-2">
                            <div
                                className={cn(
                                    "relative flex cursor-pointer items-center gap-3 rounded-lg border p-3 transition-all",
                                    printOption === "subjects"
                                        ? "border-primary bg-primary/5 ring-primary ring-1"
                                        : "hover:border-primary/50 hover:bg-accent",
                                )}
                                onClick={() => setPrintOption("subjects")}
                            >
                                <div
                                    className={cn(
                                        "flex h-4 w-4 flex-shrink-0 items-center justify-center rounded-full border",
                                        printOption === "subjects" ? "border-primary bg-primary" : "border-muted-foreground",
                                    )}
                                >
                                    {printOption === "subjects" && <div className="h-2 w-2 rounded-full bg-white" />}
                                </div>
                                <div className="min-w-0 flex-1">
                                    <div className="flex items-center gap-2">
                                        <ListIcon className="text-muted-foreground h-4 w-4 flex-shrink-0" />
                                        <span className="text-sm font-medium">Enrolled Subjects</span>
                                    </div>
                                    <p className="text-muted-foreground mt-0.5 text-xs">Current semester subjects with schedule</p>
                                </div>
                                <Badge variant="outline" className="flex-shrink-0 text-[10px]">
                                    1 pg
                                </Badge>
                            </div>

                            <div
                                className={cn(
                                    "relative flex cursor-pointer items-center gap-3 rounded-lg border p-3 transition-all",
                                    printOption === "schedule"
                                        ? "border-primary bg-primary/5 ring-primary ring-1"
                                        : "hover:border-primary/50 hover:bg-accent",
                                )}
                                onClick={() => setPrintOption("schedule")}
                            >
                                <div
                                    className={cn(
                                        "flex h-4 w-4 flex-shrink-0 items-center justify-center rounded-full border",
                                        printOption === "schedule" ? "border-primary bg-primary" : "border-muted-foreground",
                                    )}
                                >
                                    {printOption === "schedule" && <div className="h-2 w-2 rounded-full bg-white" />}
                                </div>
                                <div className="min-w-0 flex-1">
                                    <div className="flex items-center gap-2">
                                        <CalendarIcon className="text-muted-foreground h-4 w-4 flex-shrink-0" />
                                        <span className="text-sm font-medium">Weekly Schedule</span>
                                    </div>
                                    <p className="text-muted-foreground mt-0.5 text-xs">Visual class schedule grid</p>
                                </div>
                                <Badge variant="outline" className="flex-shrink-0 text-[10px]">
                                    1 pg
                                </Badge>
                            </div>

                            <div
                                className={cn(
                                    "relative flex cursor-pointer items-center gap-3 rounded-lg border p-3 transition-all",
                                    printOption === "both"
                                        ? "border-primary bg-primary/5 ring-primary ring-1"
                                        : "hover:border-primary/50 hover:bg-accent",
                                )}
                                onClick={() => setPrintOption("both")}
                            >
                                <div
                                    className={cn(
                                        "flex h-4 w-4 flex-shrink-0 items-center justify-center rounded-full border",
                                        printOption === "both" ? "border-primary bg-primary" : "border-muted-foreground",
                                    )}
                                >
                                    {printOption === "both" && <div className="h-2 w-2 rounded-full bg-white" />}
                                </div>
                                <div className="min-w-0 flex-1">
                                    <div className="flex items-center gap-2">
                                        <FileText className="text-muted-foreground h-4 w-4 flex-shrink-0" />
                                        <span className="text-sm font-medium">Complete Enrollment</span>
                                    </div>
                                    <p className="text-muted-foreground mt-0.5 text-xs">Subjects table + schedule grid</p>
                                </div>
                                <Badge variant="secondary" className="flex-shrink-0 text-[10px]">
                                    2 pg
                                </Badge>
                            </div>
                        </div>
                    </div>

                    <Separator />

                    {/* Checklist Section */}
                    <div className="space-y-3">
                        <Label className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">Curriculum Checklist</Label>
                        <div className="space-y-2">
                            <div
                                className={cn(
                                    "relative flex cursor-pointer items-center gap-3 rounded-lg border p-3 transition-all",
                                    printOption === "checklist_completed"
                                        ? "border-primary bg-primary/5 ring-primary ring-1"
                                        : "hover:border-primary/50 hover:bg-accent",
                                )}
                                onClick={() => setPrintOption("checklist_completed")}
                            >
                                <div
                                    className={cn(
                                        "flex h-4 w-4 flex-shrink-0 items-center justify-center rounded-full border",
                                        printOption === "checklist_completed" ? "border-primary bg-primary" : "border-muted-foreground",
                                    )}
                                >
                                    {printOption === "checklist_completed" && <div className="h-2 w-2 rounded-full bg-white" />}
                                </div>
                                <div className="min-w-0 flex-1">
                                    <div className="flex items-center gap-2">
                                        <CheckCircle className="h-4 w-4 flex-shrink-0 text-green-600" />
                                        <span className="text-sm font-medium">Academic Transcript</span>
                                    </div>
                                    <p className="text-muted-foreground mt-0.5 text-xs">Completed subjects with grades only</p>
                                </div>
                                <Badge variant="outline" className="flex-shrink-0 border-green-200 bg-green-50 text-[10px] text-green-700">
                                    Passed
                                </Badge>
                            </div>

                            <div
                                className={cn(
                                    "relative flex cursor-pointer items-center gap-3 rounded-lg border p-3 transition-all",
                                    printOption === "checklist_full"
                                        ? "border-primary bg-primary/5 ring-primary ring-1"
                                        : "hover:border-primary/50 hover:bg-accent",
                                )}
                                onClick={() => setPrintOption("checklist_full")}
                            >
                                <div
                                    className={cn(
                                        "flex h-4 w-4 flex-shrink-0 items-center justify-center rounded-full border",
                                        printOption === "checklist_full" ? "border-primary bg-primary" : "border-muted-foreground",
                                    )}
                                >
                                    {printOption === "checklist_full" && <div className="h-2 w-2 rounded-full bg-white" />}
                                </div>
                                <div className="min-w-0 flex-1">
                                    <div className="flex items-center gap-2">
                                        <BookOpen className="text-muted-foreground h-4 w-4 flex-shrink-0" />
                                        <span className="text-sm font-medium">Full Curriculum Checklist</span>
                                    </div>
                                    <p className="text-muted-foreground mt-0.5 text-xs">All subjects with completion status</p>
                                </div>
                                <Badge variant="secondary" className="flex-shrink-0 text-[10px]">
                                    All
                                </Badge>
                            </div>

                            <div
                                className={cn(
                                    "relative flex cursor-pointer items-center gap-3 rounded-lg border p-3 transition-all",
                                    printOption === "transcript"
                                        ? "border-primary bg-primary/5 ring-primary ring-1"
                                        : "hover:border-primary/50 hover:bg-accent",
                                )}
                                onClick={() => setPrintOption("transcript")}
                            >
                                <div
                                    className={cn(
                                        "flex h-4 w-4 flex-shrink-0 items-center justify-center rounded-full border",
                                        printOption === "transcript" ? "border-primary bg-primary" : "border-muted-foreground",
                                    )}
                                >
                                    {printOption === "transcript" && <div className="h-2 w-2 rounded-full bg-white" />}
                                </div>
                                <div className="min-w-0 flex-1">
                                    <div className="flex items-center gap-2">
                                        <FileText className="text-muted-foreground h-4 w-4 flex-shrink-0" />
                                        <span className="text-sm font-medium">Official Transcript of Records</span>
                                    </div>
                                    <p className="text-muted-foreground mt-0.5 text-xs">
                                        Formal portrait TOR grouped by year level, school year, and semester.
                                    </p>
                                </div>
                                <Badge variant="outline" className="flex-shrink-0 text-[10px]">
                                    TOR
                                </Badge>
                            </div>
                        </div>
                    </div>

                    <div className="bg-muted/30 text-muted-foreground rounded-lg border p-3 text-xs">
                        <div className="flex items-start gap-2">
                            <AlertCircle className="mt-0.5 h-4 w-4 flex-shrink-0" />
                            <div>
                                <p className="text-foreground mb-1 font-medium">Print Information</p>
                                <ul className="space-y-0.5">
                                    <li>Documents are formatted for official use</li>
                                    <li>Content auto-scales to fit letter size</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <DialogFooter>
                    <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                        Cancel
                    </Button>
                    <Button type="button" onClick={handlePrint} className="gap-2">
                        <Printer className="h-4 w-4" />
                        Print Document
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
