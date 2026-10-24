import { BookOpen, GraduationCap } from "lucide-react";
import type { ReactNode } from "react";
import type { ClassFormData, ClassFormOptions, Classification, SubjectOption } from "../../lib/class-defaults";
import { ClassificationPicker } from "../fields/classification-picker";
import { CollegeCourseSubject } from "../fields/college-course-subject";
import { SectionSlotInput } from "../fields/section-slot-input";
import { ShsTrackStrandSubject } from "../fields/shs-track-strand-subject";
import { FormSection } from "../form-section";

type BasicsStepProps = {
    data: ClassFormData;
    options: ClassFormOptions;
    collegeSubjects: SubjectOption[];
    subjectCodeTouched: boolean;
    subjectsLoading: boolean;
    onClassificationChange: (classification: Classification) => void;
    onDataChange: (data: Partial<ClassFormData>) => void;
    onSubjectCodeTouched: () => void;
};

export function BasicsStep({
    data,
    options,
    collegeSubjects,
    subjectCodeTouched,
    subjectsLoading,
    onClassificationChange,
    onDataChange,
    onSubjectCodeTouched,
}: BasicsStepProps): ReactNode {
    return (
        <div className="space-y-6">
            <FormSection title="Academic level" description="Choose the curriculum this class belongs to.">
                <ClassificationPicker
                    value={data.classification}
                    onChange={onClassificationChange}
                    collegeIcon={<BookOpen className="size-5" />}
                    shsIcon={<GraduationCap className="size-5" />}
                />
            </FormSection>

            <FormSection
                title={data.classification === "college" ? "Programs and subjects" : "Track, strand, and subject"}
                description={
                    data.classification === "college"
                        ? "Connect the class to its curriculum. Multiple programs can share a class."
                        : "Select the senior high school curriculum and subject."
                }
            >
                {data.classification === "college" ? (
                    <CollegeCourseSubject
                        courses={options.courses}
                        subjects={collegeSubjects}
                        academicYears={options.academic_years}
                        selectedCourseIds={data.course_codes}
                        selectedSubjectIds={data.subject_ids}
                        subjectCode={data.subject_code}
                        academicYear={data.academic_year}
                        subjectCodeTouched={subjectCodeTouched}
                        subjectsLoading={subjectsLoading}
                        onCoursesChange={(course_codes) => onDataChange({ course_codes })}
                        onSubjectsChange={(subject_ids, subject_code) =>
                            onDataChange({
                                subject_ids,
                                ...(subject_code === undefined ? {} : { subject_code }),
                            })
                        }
                        onSubjectCodeChange={(subject_code) => onDataChange({ subject_code })}
                        onAcademicYearChange={(academic_year) => onDataChange({ academic_year })}
                        onSubjectCodeTouched={onSubjectCodeTouched}
                    />
                ) : (
                    <ShsTrackStrandSubject
                        tracks={options.shs_tracks}
                        gradeLevels={options.grade_levels}
                        trackId={data.shs_track_id}
                        strandId={data.shs_strand_id}
                        subjectCode={data.subject_code_shs}
                        gradeLevel={data.grade_level}
                        onTrackChange={(shs_track_id) => onDataChange({ shs_track_id })}
                        onStrandChange={(shs_strand_id) => onDataChange({ shs_strand_id })}
                        onSubjectCodeChange={(subject_code_shs) => onDataChange({ subject_code_shs })}
                        onGradeLevelChange={(grade_level) => onDataChange({ grade_level })}
                    />
                )}
            </FormSection>

            <FormSection title="Section and enrollment" description="Set the section and the number of students it can accommodate.">
                <SectionSlotInput
                    sections={options.sections}
                    semesters={options.semesters}
                    section={data.section}
                    semester={data.semester}
                    schoolYear={data.school_year}
                    maximumSlots={data.maximum_slots}
                    onSectionChange={(section) => onDataChange({ section })}
                    onMaximumSlotsChange={(maximum_slots) => onDataChange({ maximum_slots })}
                />
            </FormSection>
        </div>
    );
}
