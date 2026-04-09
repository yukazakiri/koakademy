<?php

// @formatter:off
// phpcs:ignoreFile
/**
 * A helper file for your Eloquent Models
 * Copy the phpDocs from this file to the correct Model,
 * And remove them from this file, to prevent double declarations.
 *
 * @author Barry vd. Heuvel <barryvdh@gmail.com>
 */


namespace App\Models{
/**
 * Class Account
 *
 * @property-read Model|Eloquent $UserPerson
 * @property-read Faculty|null $faculty
 * @property-read mixed $approved_pending_enrollment
 * @property-read mixed $is_faculty
 * @property-read mixed $is_student
 * @property-read mixed $profile_photo_url
 * @property-read Model|Eloquent $person
 * @property-read ShsStudent|null $shsStudent
 * @property-read Student|null $student
 * @method static Builder<static>|Account newModelQuery()
 * @method static Builder<static>|Account newQuery()
 * @method static Builder<static>|Account onlyTrashed()
 * @method static Builder<static>|Account query()
 * @method static Builder<static>|Account withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|Account withoutTrashed()
 * @mixin \Eloquent
 * @property int $id
 * @property string|null $type
 * @property string|null $parent_id
 * @property string|null $name
 * @property string $username
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $loginby
 * @property string|null $address
 * @property string|null $lang
 * @property string|null $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property \Illuminate\Support\Carbon|null $two_factor_confirmed_at
 * @property string|null $otp_code
 * @property \Illuminate\Support\Carbon|null $otp_activated_at
 * @property \Illuminate\Support\Carbon|null $last_login
 * @property string|null $agent
 * @property string|null $host
 * @property bool|null $is_login
 * @property bool $is_active
 * @property bool $is_notification_active
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int|null $person_id
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property string|null $role
 * @property string|null $avatar
 * @property string|null $person_type
 * @property string|null $profile_photo_path
 * @property string|null $remember_token
 * @property int|null $current_team_id
 * @method static \Database\Factories\AccountFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Account whereAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Account whereAgent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Account whereAvatar($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Account whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Account whereCurrentTeamId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Account whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Account whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Account whereEmailVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Account whereHost($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Account whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Account whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Account whereIsLogin($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Account whereIsNotificationActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Account whereLang($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Account whereLastLogin($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Account whereLoginby($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Account whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Account whereOtpActivatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Account whereOtpCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Account whereParentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Account wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Account wherePersonId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Account wherePersonType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Account wherePhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Account whereProfilePhotoPath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Account whereProfilePhotoUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Account whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Account whereRole($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Account whereTwoFactorConfirmedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Account whereTwoFactorRecoveryCodes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Account whereTwoFactorSecret($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Account whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Account whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Account whereUsername($value)
 */
	final class Account extends \Eloquent implements \Filament\Models\Contracts\FilamentUser {}
}

namespace App\Models{
/**
 * @property-read StudentEnrollment|null $enrollment
 * @property-read string $formatted_amount
 * @method static Builder<static>|AdditionalFee newModelQuery()
 * @method static Builder<static>|AdditionalFee newQuery()
 * @method static Builder<static>|AdditionalFee query()
 * @mixin \Eloquent
 * @property int $id
 * @property int $enrollment_id
 * @property string $fee_name
 * @property string|null $description
 * @property numeric $amount
 * @property bool $is_required
 * @property int $sort_order
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property bool $is_separate_transaction
 * @property string|null $transaction_number
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdditionalFee whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdditionalFee whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdditionalFee whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdditionalFee whereEnrollmentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdditionalFee whereFeeName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdditionalFee whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdditionalFee whereIsRequired($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdditionalFee whereIsSeparateTransaction($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdditionalFee whereSortOrder($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdditionalFee whereTransactionNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdditionalFee whereUpdatedAt($value)
 */
	final class AdditionalFee extends \Eloquent {}
}

namespace App\Models{
/**
 * Class AdminTransaction
 *
 * @property-read Transaction|null $transaction
 * @property-read User|null $user
 * @method static Builder<static>|AdminTransaction newModelQuery()
 * @method static Builder<static>|AdminTransaction newQuery()
 * @method static Builder<static>|AdminTransaction query()
 * @mixin \Eloquent
 * @property int $id
 * @property int $admin_id
 * @property int $transaction_id
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminTransaction whereAdminId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminTransaction whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminTransaction whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminTransaction whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminTransaction whereTransactionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminTransaction whereUpdatedAt($value)
 */
	final class AdminTransaction extends \Eloquent {}
}

namespace App\Models{
/**
 * Class Announcement
 *
 * @property-read User|null $user
 * @method static Builder<static>|Announcement newModelQuery()
 * @method static Builder<static>|Announcement newQuery()
 * @method static Builder<static>|Announcement query()
 * @mixin \Eloquent
 * @property int $id
 * @property string $title
 * @property string $content
 * @property string $slug
 * @property string $status
 * @property int $user_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $attachments
 * @property int|null $class_id
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Announcement whereAttachments($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Announcement whereClassId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Announcement whereContent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Announcement whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Announcement whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Announcement whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Announcement whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Announcement whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Announcement whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Announcement whereUserId($value)
 */
	final class Announcement extends \Eloquent {}
}

namespace App\Models{
/**
 * Class Attendance
 *
 * @method static Builder<static>|Attendance newModelQuery()
 * @method static Builder<static>|Attendance newQuery()
 * @method static Builder<static>|Attendance query()
 * @mixin \Eloquent
 * @property int $id
 * @property int $class_enrollment_id
 * @property int $student_id
 * @property \Illuminate\Support\Carbon $date
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int $class_id
 * @property string|null $remarks
 * @property string|null $marked_at
 * @property string|null $marked_by
 * @property string|null $ip_address
 * @property string|null $location_data
 * @property string|null $deleted_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attendance whereClassEnrollmentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attendance whereClassId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attendance whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attendance whereDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attendance whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attendance whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attendance whereIpAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attendance whereLocationData($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attendance whereMarkedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attendance whereMarkedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attendance whereRemarks($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attendance whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attendance whereStudentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attendance whereUpdatedAt($value)
 */
	final class Attendance extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $resource_booking_id
 * @property int $booked_by
 * @property string $start_datetime
 * @property string $end_datetime
 * @property string $purpose
 * @property string|null $notes
 * @property int|null $expected_attendees
 * @property string $status
 * @property string|null $total_cost
 * @property int|null $approved_by
 * @property string|null $approved_at
 * @property string|null $approval_notes
 * @property string|null $cancelled_at
 * @property string|null $cancellation_reason
 * @property string|null $additional_requirements
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Database\Factories\BookingFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Booking newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Booking newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Booking query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Booking whereAdditionalRequirements($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Booking whereApprovalNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Booking whereApprovedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Booking whereApprovedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Booking whereBookedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Booking whereCancellationReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Booking whereCancelledAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Booking whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Booking whereEndDatetime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Booking whereExpectedAttendees($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Booking whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Booking whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Booking wherePurpose($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Booking whereResourceBookingId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Booking whereStartDatetime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Booking whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Booking whereTotalCost($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Booking whereUpdatedAt($value)
 */
	final class Booking extends \Eloquent {}
}

namespace App\Models{
/**
 * Class ClassEnrollment
 *
 * @property-read Classes|null $class
 * @property-read Student|null $student
 * @method static Builder<static>|ClassEnrollment newModelQuery()
 * @method static Builder<static>|ClassEnrollment newQuery()
 * @method static Builder<static>|ClassEnrollment onlyTrashed()
 * @method static Builder<static>|ClassEnrollment query()
 * @method static Builder<static>|ClassEnrollment withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|ClassEnrollment withoutTrashed()
 * @mixin \Eloquent
 * @property int $id
 * @property int $class_id
 * @property float $student_id
 * @property \Illuminate\Support\Carbon|null $completion_date
 * @property bool $status
 * @property string|null $remarks
 * @property float|null $prelim_grade
 * @property float|null $midterm_grade
 * @property float|null $finals_grade
 * @property float|null $total_average
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property bool $is_grades_finalized
 * @property bool $is_grades_verified
 * @property int|null $verified_by
 * @property \Illuminate\Support\Carbon|null $verified_at
 * @property string|null $verification_notes
 * @property bool|null $is_finals_submitted
 * @property bool|null $is_midterms_submitted
 * @property bool|null $is_prelim_submitted
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClassEnrollment whereClassId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClassEnrollment whereCompletionDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClassEnrollment whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClassEnrollment whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClassEnrollment whereFinalsGrade($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClassEnrollment whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClassEnrollment whereIsFinalsSubmitted($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClassEnrollment whereIsGradesFinalized($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClassEnrollment whereIsGradesVerified($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClassEnrollment whereIsMidtermsSubmitted($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClassEnrollment whereIsPrelimSubmitted($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClassEnrollment whereMidtermGrade($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClassEnrollment wherePrelimGrade($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClassEnrollment whereRemarks($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClassEnrollment whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClassEnrollment whereStudentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClassEnrollment whereTotalAverage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClassEnrollment whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClassEnrollment whereVerificationNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClassEnrollment whereVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClassEnrollment whereVerifiedBy($value)
 */
	final class ClassEnrollment extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $class_id
 * @property string $title
 * @property string|null $content
 * @property \App\Enums\ClassPostType $type
 * @property array<array-key, mixed>|null $attachments
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Classes $class
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClassPost newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClassPost newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClassPost query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClassPost whereAttachments($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClassPost whereClassId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClassPost whereContent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClassPost whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClassPost whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClassPost whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClassPost whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClassPost whereUpdatedAt($value)
 */
	final class ClassPost extends \Eloquent {}
}

namespace App\Models{
/**
 * Class Class
 *
 * @property-read Collection<int, ClassEnrollment> $ClassStudents
 * @property-read int|null $class_students_count
 * @property-read Faculty|null $Faculty
 * @property-read Room|null $Room
 * @property-read Collection<int, Schedule> $Schedule
 * @property-read int|null $schedule_count
 * @property-read ShsStrand|null $ShsStrand
 * @property-read StrandSubject|null $ShsSubject
 * @property-read ShsTrack|null $ShsTrack
 * @property-read Subject|null $Subject
 * @property-read Subject|null $SubjectByCode
 * @property-read Subject|null $SubjectByCodeFallback
 * @property-read Subject|null $SubjectById
 * @property-read Collection<int, ClassEnrollment> $class_enrollments
 * @property-read int|null $class_enrollments_count
 * @property-read Collection<int, ClassEnrollment> $enrollments
 * @property-read int|null $enrollments_count
 * @property-read mixed $active_subject
 * @property-read mixed $assigned_room_i_ds
 * @property-read mixed $assigned_rooms
 * @property-read mixed $class_subject_title
 * @property-read mixed $display_info
 * @property-read mixed $faculty_full_name
 * @property-read mixed $formatted_course_codes
 * @property-read mixed $formatted_track_strand
 * @property-read mixed[] $formatted_weekly_schedule
 * @property-read mixed $schedule_days
 * @property-read mixed $schedule_rooms
 * @property-read mixed $student_count
 * @property-read mixed $subject_title
 * @property-read mixed $subject_with_courses
 * @property-read mixed $subject_with_fallback
 * @property-read Collection<int, Schedule> $schedules
 * @property-read int|null $schedules_count
 * @method static Builder<static>|Classes college()
 * @method static Builder<static>|Classes currentAcademicPeriod()
 * @method static Builder<static>|Classes newModelQuery()
 * @method static Builder<static>|Classes newQuery()
 * @method static Builder<static>|Classes query()
 * @method static Builder<static>|Classes shs()
 * @mixin \Eloquent
 * @property int $id
 * @property string|null $subject_code
 * @property string|null $faculty_id
 * @property string|null $academic_year
 * @property string|null $semester
 * @property int|null $schedule_id
 * @property string|null $school_year
 * @property array<array-key, mixed>|null $course_codes
 * @property string|null $section
 * @property int|null $room_id
 * @property string|null $classification
 * @property int|null $maximum_slots
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int|null $shs_track_id
 * @property int|null $shs_strand_id
 * @property string|null $grade_level
 * @property int|null $subject_id
 * @property array<array-key, mixed>|null $subject_ids
 * @property array<array-key, mixed>|null $settings
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ClassPost> $classPosts
 * @property-read int|null $class_posts_count
 * @property-read string $record_title
 * @property-read mixed $subjects
 * @method static \Database\Factories\ClassesFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Classes whereAcademicYear($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Classes whereClassification($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Classes whereCourseCodes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Classes whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Classes whereFacultyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Classes whereGradeLevel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Classes whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Classes whereMaximumSlots($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Classes whereRoomId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Classes whereScheduleId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Classes whereSchoolYear($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Classes whereSection($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Classes whereSemester($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Classes whereSettings($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Classes whereShsStrandId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Classes whereShsTrackId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Classes whereSubjectCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Classes whereSubjectId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Classes whereSubjectIds($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Classes whereUpdatedAt($value)
 */
	final class Classes extends \Eloquent {}
}

namespace App\Models{
/**
 * Class Course
 *
 * @property int $id
 * @property string $code
 * @property string $title
 * @property string|null $description
 * @property int $units
 * @property string|null $lec_per_unit
 * @property string|null $lab_per_unit
 * @property int $year_level
 * @property int $semester
 * @property string|null $school_year
 * @property string|null $miscellaneous
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read string $course_code
 * @property-read Collection<int, Schedule> $schedules
 * @property-read int|null $schedules_count
 * @property-read Collection<int, Student> $students
 * @property-read int|null $students_count
 * @property-read Collection<int, Subject> $subjects
 * @property-read int|null $subjects_count
 * @method static Builder<static>|Course newModelQuery()
 * @method static Builder<static>|Course newQuery()
 * @method static Builder<static>|Course query()
 * @method static Builder<static>|Course whereCode($value)
 * @method static Builder<static>|Course whereCreatedAt($value)
 * @method static Builder<static>|Course whereDescription($value)
 * @method static Builder<static>|Course whereId($value)
 * @method static Builder<static>|Course whereIsActive($value)
 * @method static Builder<static>|Course whereLabPerUnit($value)
 * @method static Builder<static>|Course whereLecPerUnit($value)
 * @method static Builder<static>|Course whereMiscellaneous($value)
 * @method static Builder<static>|Course whereSchoolYear($value)
 * @method static Builder<static>|Course whereSemester($value)
 * @method static Builder<static>|Course whereTitle($value)
 * @method static Builder<static>|Course whereUnits($value)
 * @method static Builder<static>|Course whereUpdatedAt($value)
 * @method static Builder<static>|Course whereYearLevel($value)
 * @mixin \Eloquent
 * @property \App\Models\Department|null $department
 * @property string|null $remarks
 * @property string|null $curriculum_year
 * @property int|null $miscelaneous
 * @method static \Database\Factories\CourseFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Course whereCurriculumYear($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Course whereDepartment($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Course whereMiscelaneous($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Course whereRemarks($value)
 */
	final class Course extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $school_id
 * @property string $name
 * @property string $code
 * @property string|null $description
 * @property string|null $head_name
 * @property string|null $head_email
 * @property string|null $location
 * @property string|null $phone
 * @property string|null $email
 * @property bool $is_active
 * @property array|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read School $school
 * @property-read Collection<int, User> $users
 * @property-read int|null $users_count
 * @property-read Collection<int, Faculty> $faculty
 * @property-read int|null $faculty_count
 * @property-read Collection<int, Course> $courses
 * @property-read int|null $courses_count
 * @method static DepartmentFactory factory($count = null, $state = [])
 * @method static Builder<static>|Department newModelQuery()
 * @method static Builder<static>|Department newQuery()
 * @method static Builder<static>|Department query()
 * @method static Builder<static>|Department whereCode($value)
 * @method static Builder<static>|Department whereCreatedAt($value)
 * @method static Builder<static>|Department whereDescription($value)
 * @method static Builder<static>|Department whereEmail($value)
 * @method static Builder<static>|Department whereHeadEmail($value)
 * @method static Builder<static>|Department whereHeadName($value)
 * @method static Builder<static>|Department whereId($value)
 * @method static Builder<static>|Department whereIsActive($value)
 * @method static Builder<static>|Department whereLocation($value)
 * @method static Builder<static>|Department whereMetadata($value)
 * @method static Builder<static>|Department whereName($value)
 * @method static Builder<static>|Department wherePhone($value)
 * @method static Builder<static>|Department whereSchoolId($value)
 * @method static Builder<static>|Department whereUpdatedAt($value)
 * @method static Builder<static>|Department active()
 * @method static Builder<static>|Department forSchool(School|int $school)
 * @mixin \Eloquent
 * @property-read string $full_name
 * @property-read string $name_with_code
 */
	final class Department extends \Eloquent {}
}

namespace App\Models{
/**
 * Class DocumentLocation
 *
 * @method static Builder<static>|DocumentLocation newModelQuery()
 * @method static Builder<static>|DocumentLocation newQuery()
 * @method static Builder<static>|DocumentLocation query()
 * @mixin \Eloquent
 * @property int $id
 * @property string|null $birth_certificate
 * @property string|null $form_138
 * @property string|null $form_137
 * @property string|null $good_moral_cert
 * @property string|null $transfer_credentials
 * @property string|null $transcript_records
 * @property string|null $picture_1x1
 * @property string|null $created_at
 * @property string|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DocumentLocation whereBirthCertificate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DocumentLocation whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DocumentLocation whereForm137($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DocumentLocation whereForm138($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DocumentLocation whereGoodMoralCert($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DocumentLocation whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DocumentLocation wherePicture1x1($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DocumentLocation whereTranscriptRecords($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DocumentLocation whereTransferCredentials($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DocumentLocation whereUpdatedAt($value)
 */
	final class DocumentLocation extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $title
 * @property string|null $description
 * @property string $type
 * @property string|null $category
 * @property string|null $location
 * @property \Illuminate\Support\Carbon $start_datetime
 * @property \Illuminate\Support\Carbon $end_datetime
 * @property bool $is_all_day
 * @property string|null $recurrence_type
 * @property array<array-key, mixed>|null $recurrence_data
 * @property \Illuminate\Support\Carbon|null $recurrence_end_date
 * @property int|null $max_attendees
 * @property bool $requires_rsvp
 * @property bool $allow_guests
 * @property string $status
 * @property string $visibility
 * @property array<array-key, mixed>|null $custom_fields
 * @property string|null $notes
 * @property int $created_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\EventRsvp> $attendingRsvps
 * @property-read int|null $attending_rsvps_count
 * @property-read \App\Models\User $creator
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\EventReminder> $reminders
 * @property-read int|null $reminders_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\EventRsvp> $rsvps
 * @property-read int|null $rsvps_count
 * @property-read mixed $total_attendees
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Event active()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Event dateRange($startDate, $endDate)
 * @method static \Database\Factories\EventFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Event newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Event newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Event ofType(string $type)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Event public()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Event query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Event upcoming()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Event whereAllowGuests($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Event whereCategory($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Event whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Event whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Event whereCustomFields($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Event whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Event whereEndDatetime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Event whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Event whereIsAllDay($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Event whereLocation($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Event whereMaxAttendees($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Event whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Event whereRecurrenceData($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Event whereRecurrenceEndDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Event whereRecurrenceType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Event whereRequiresRsvp($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Event whereStartDatetime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Event whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Event whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Event whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Event whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Event whereVisibility($value)
 */
	final class Event extends \Eloquent implements \Guava\Calendar\Contracts\Eventable {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $event_id
 * @property int $user_id
 * @property string $reminder_type
 * @property int $minutes_before
 * @property string $status
 * @property string $scheduled_at
 * @property string|null $sent_at
 * @property string|null $message
 * @property string|null $delivery_data
 * @property string|null $failure_reason
 * @property int $retry_count
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Database\Factories\EventReminderFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventReminder newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventReminder newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventReminder query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventReminder whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventReminder whereDeliveryData($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventReminder whereEventId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventReminder whereFailureReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventReminder whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventReminder whereMessage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventReminder whereMinutesBefore($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventReminder whereReminderType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventReminder whereRetryCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventReminder whereScheduledAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventReminder whereSentAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventReminder whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventReminder whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventReminder whereUserId($value)
 */
	final class EventReminder extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $event_id
 * @property int $user_id
 * @property string $response
 * @property int $guest_count
 * @property string|null $dietary_requirements
 * @property string|null $special_requests
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $responded_at
 * @property bool $checked_in
 * @property \Illuminate\Support\Carbon|null $checked_in_at
 * @property array<array-key, mixed>|null $custom_responses
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Event $event
 * @property-read int $total_people
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventRsvp attending()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventRsvp checkedIn()
 * @method static \Database\Factories\EventRsvpFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventRsvp newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventRsvp newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventRsvp notAttending()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventRsvp pending()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventRsvp query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventRsvp whereCheckedIn($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventRsvp whereCheckedInAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventRsvp whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventRsvp whereCustomResponses($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventRsvp whereDietaryRequirements($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventRsvp whereEventId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventRsvp whereGuestCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventRsvp whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventRsvp whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventRsvp whereRespondedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventRsvp whereResponse($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventRsvp whereSpecialRequests($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventRsvp whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventRsvp whereUserId($value)
 */
	final class EventRsvp extends \Eloquent {}
}

namespace App\Models{
/**
 * @property-read string $filters_display
 * @property-read User|null $user
 * @method static Builder<static>|ExportJob newModelQuery()
 * @method static Builder<static>|ExportJob newQuery()
 * @method static Builder<static>|ExportJob query()
 * @mixin \Eloquent
 * @property int $id
 * @property string $job_id
 * @property int $user_id
 * @property string $export_type
 * @property array<array-key, mixed> $filters
 * @property string $format
 * @property string $status
 * @property string|null $file_content
 * @property string|null $file_name
 * @property string|null $error_message
 * @property \Illuminate\Support\Carbon|null $started_at
 * @property \Illuminate\Support\Carbon|null $completed_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExportJob whereCompletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExportJob whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExportJob whereErrorMessage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExportJob whereExportType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExportJob whereFileContent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExportJob whereFileName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExportJob whereFilters($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExportJob whereFormat($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExportJob whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExportJob whereJobId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExportJob whereStartedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExportJob whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExportJob whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExportJob whereUserId($value)
 */
	final class ExportJob extends \Eloquent {}
}

namespace App\Models{
/**
 * Class Faculty
 *
 * @property-read Account|null $account
 * @property-read Collection<int, ClassEnrollment> $classEnrollments
 * @property-read int|null $class_enrollments_count
 * @property-read Collection<int, Classes> $classes
 * @property-read int|null $classes_count
 * @property-read string $full_name
 * @property-read string $name
 * @property-read DatabaseNotificationCollection<int, DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @method static Builder<static>|Faculty newModelQuery()
 * @method static Builder<static>|Faculty newQuery()
 * @method static Builder<static>|Faculty query()
 * @mixin \Eloquent
 * @property string $first_name
 * @property string $last_name
 * @property string|null $middle_name
 * @property string $email
 * @property string|null $phone_number
 * @property string|null $department
 * @property string|null $office_hours
 * @property \Illuminate\Support\Carbon|null $birth_date
 * @property string|null $address_line1
 * @property string|null $biography
 * @property string|null $education
 * @property string|null $courses_taught
 * @property string|null $photo_url
 * @property string|null $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $gender
 * @property int|null $age
 * @property string|null $password
 * @property string|null $remember_token
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property string $id
 * @property string|null $faculty_code
 * @property string|null $faculty_id_number
 * @property-read \App\Models\Department|null $departmentBelongsTo
 * @method static \Database\Factories\FacultyFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Faculty whereAddressLine1($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Faculty whereAge($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Faculty whereBiography($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Faculty whereBirthDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Faculty whereCoursesTaught($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Faculty whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Faculty whereDepartment($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Faculty whereEducation($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Faculty whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Faculty whereEmailVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Faculty whereFacultyCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Faculty whereFacultyIdNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Faculty whereFirstName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Faculty whereFullName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Faculty whereGender($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Faculty whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Faculty whereLastName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Faculty whereMiddleName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Faculty whereOfficeHours($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Faculty wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Faculty wherePhoneNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Faculty wherePhotoUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Faculty whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Faculty whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Faculty whereUpdatedAt($value)
 */
	final class Faculty extends \Eloquent implements \Filament\Models\Contracts\FilamentUser, \Filament\Models\Contracts\HasAvatar {}
}

namespace App\Models{
/**
 * Class GeneralSetting
 *
 * @method static Builder<static>|GeneralSetting newModelQuery()
 * @method static Builder<static>|GeneralSetting newQuery()
 * @method static Builder<static>|GeneralSetting query()
 * @mixin \Eloquent
 * @property int $id
 * @property string|null $site_name
 * @property string|null $site_description
 * @property string|null $theme_color
 * @property string|null $support_email
 * @property string|null $support_phone
 * @property string|null $google_analytics_id
 * @property string|null $posthog_html_snippet
 * @property string|null $seo_title
 * @property string|null $seo_keywords
 * @property array<array-key, mixed>|null $seo_metadata
 * @property array<array-key, mixed>|null $email_settings
 * @property string|null $email_from_address
 * @property string|null $email_from_name
 * @property array<array-key, mixed>|null $social_network
 * @property array<array-key, mixed>|null $more_configs
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $school_starting_date
 * @property \Illuminate\Support\Carbon|null $school_ending_date
 * @property string|null $school_portal_url
 * @property bool|null $school_portal_enabled
 * @property bool|null $online_enrollment_enabled
 * @property bool|null $school_portal_maintenance
 * @property int $semester
 * @property array<array-key, mixed>|null $enrollment_courses
 * @property string|null $school_portal_logo
 * @property string|null $school_portal_favicon
 * @property string|null $school_portal_title
 * @property string|null $school_portal_description
 * @property bool $enable_clearance_check
 * @property bool $enable_signatures
 * @property bool|null $enable_qr_codes
 * @property bool|null $enable_public_transactions
 * @property bool $enable_support_page
 * @property array<array-key, mixed>|null $features
 * @property string|null $curriculum_year
 * @property bool $inventory_module_enabled
 * @property bool $library_module_enabled
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GeneralSetting whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GeneralSetting whereCurriculumYear($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GeneralSetting whereEmailFromAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GeneralSetting whereEmailFromName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GeneralSetting whereEmailSettings($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GeneralSetting whereEnableClearanceCheck($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GeneralSetting whereEnablePublicTransactions($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GeneralSetting whereEnableQrCodes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GeneralSetting whereEnableSignatures($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GeneralSetting whereEnableSupportPage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GeneralSetting whereEnrollmentCourses($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GeneralSetting whereFeatures($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GeneralSetting whereGoogleAnalyticsId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GeneralSetting whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GeneralSetting whereInventoryModuleEnabled($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GeneralSetting whereLibraryModuleEnabled($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GeneralSetting whereMoreConfigs($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GeneralSetting whereOnlineEnrollmentEnabled($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GeneralSetting wherePosthogHtmlSnippet($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GeneralSetting whereSchoolEndingDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GeneralSetting whereSchoolPortalDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GeneralSetting whereSchoolPortalEnabled($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GeneralSetting whereSchoolPortalFavicon($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GeneralSetting whereSchoolPortalLogo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GeneralSetting whereSchoolPortalMaintenance($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GeneralSetting whereSchoolPortalTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GeneralSetting whereSchoolPortalUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GeneralSetting whereSchoolStartingDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GeneralSetting whereSemester($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GeneralSetting whereSeoKeywords($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GeneralSetting whereSeoMetadata($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GeneralSetting whereSeoTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GeneralSetting whereSiteDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GeneralSetting whereSiteName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GeneralSetting whereSocialNetwork($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GeneralSetting whereSupportEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GeneralSetting whereSupportPhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GeneralSetting whereThemeColor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GeneralSetting whereUpdatedAt($value)
 */
	final class GeneralSetting extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $product_id
 * @property int $recorded_quantity
 * @property int $actual_quantity
 * @property int $variance
 * @property string $status
 * @property string|null $notes
 * @property int $amended_by
 * @property int|null $approved_by
 * @property Carbon $amendment_date
 * @property Carbon|null $approved_date
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read InventoryProduct $product
 * @property-read User $amendedBy
 * @property-read User|null $approvedBy
 * @method static Builder<static>|InventoryAmendment newModelQuery()
 * @method static Builder<static>|InventoryAmendment newQuery()
 * @method static Builder<static>|InventoryAmendment query()
 * @method static Builder<static>|InventoryAmendment whereId($value)
 * @method static Builder<static>|InventoryAmendment whereProductId($value)
 * @method static Builder<static>|InventoryAmendment whereRecordedQuantity($value)
 * @method static Builder<static>|InventoryAmendment whereActualQuantity($value)
 * @method static Builder<static>|InventoryAmendment whereVariance($value)
 * @method static Builder<static>|InventoryAmendment whereStatus($value)
 * @method static Builder<static>|InventoryAmendment whereNotes($value)
 * @method static Builder<static>|InventoryAmendment whereAmendedBy($value)
 * @method static Builder<static>|InventoryAmendment whereApprovedBy($value)
 * @method static Builder<static>|InventoryAmendment whereAmendmentDate($value)
 * @method static Builder<static>|InventoryAmendment whereApprovedDate($value)
 * @method static Builder<static>|InventoryAmendment whereCreatedAt($value)
 * @method static Builder<static>|InventoryAmendment whereUpdatedAt($value)
 * @mixin \Eloquent
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InventoryAmendment approved()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InventoryAmendment pending()
 */
	final class InventoryAmendment extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $product_id
 * @property int $quantity_borrowed
 * @property string $borrower_name
 * @property string|null $borrower_email
 * @property string|null $borrower_phone
 * @property string|null $department
 * @property string|null $purpose
 * @property string $status
 * @property Carbon $borrowed_date
 * @property Carbon|null $expected_return_date
 * @property Carbon|null $actual_return_date
 * @property int $quantity_returned
 * @property string|null $return_notes
 * @property int $issued_by
 * @property int|null $returned_to
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read InventoryProduct $product
 * @property-read User $issuedBy
 * @property-read User|null $returnedTo
 * @method static Builder<static>|InventoryBorrowing newModelQuery()
 * @method static Builder<static>|InventoryBorrowing newQuery()
 * @method static Builder<static>|InventoryBorrowing query()
 * @method static Builder<static>|InventoryBorrowing whereId($value)
 * @method static Builder<static>|InventoryBorrowing whereProductId($value)
 * @method static Builder<static>|InventoryBorrowing whereQuantityBorrowed($value)
 * @method static Builder<static>|InventoryBorrowing whereBorrowerName($value)
 * @method static Builder<static>|InventoryBorrowing whereBorrowerEmail($value)
 * @method static Builder<static>|InventoryBorrowing whereBorrowerPhone($value)
 * @method static Builder<static>|InventoryBorrowing whereDepartment($value)
 * @method static Builder<static>|InventoryBorrowing wherePurpose($value)
 * @method static Builder<static>|InventoryBorrowing whereStatus($value)
 * @method static Builder<static>|InventoryBorrowing whereBorrowedDate($value)
 * @method static Builder<static>|InventoryBorrowing whereExpectedReturnDate($value)
 * @method static Builder<static>|InventoryBorrowing whereActualReturnDate($value)
 * @method static Builder<static>|InventoryBorrowing whereQuantityReturned($value)
 * @method static Builder<static>|InventoryBorrowing whereReturnNotes($value)
 * @method static Builder<static>|InventoryBorrowing whereIssuedBy($value)
 * @method static Builder<static>|InventoryBorrowing whereReturnedTo($value)
 * @method static Builder<static>|InventoryBorrowing whereCreatedAt($value)
 * @method static Builder<static>|InventoryBorrowing whereUpdatedAt($value)
 * @mixin \Eloquent
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InventoryBorrowing active()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InventoryBorrowing overdue()
 */
	final class InventoryBorrowing extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property string $slug
 * @property bool $is_active
 * @property int|null $parent_id
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read InventoryCategory|null $parent
 * @property-read Collection<int, InventoryCategory> $children
 * @property-read int|null $children_count
 * @property-read Collection<int, InventoryProduct> $products
 * @property-read int|null $products_count
 * @method static Builder<static>|InventoryCategory newModelQuery()
 * @method static Builder<static>|InventoryCategory newQuery()
 * @method static Builder<static>|InventoryCategory query()
 * @method static Builder<static>|InventoryCategory whereId($value)
 * @method static Builder<static>|InventoryCategory whereName($value)
 * @method static Builder<static>|InventoryCategory whereDescription($value)
 * @method static Builder<static>|InventoryCategory whereSlug($value)
 * @method static Builder<static>|InventoryCategory whereIsActive($value)
 * @method static Builder<static>|InventoryCategory whereParentId($value)
 * @method static Builder<static>|InventoryCategory whereSortOrder($value)
 * @method static Builder<static>|InventoryCategory whereCreatedAt($value)
 * @method static Builder<static>|InventoryCategory whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	final class InventoryCategory extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $name
 * @property string $sku
 * @property string|null $description
 * @property int|null $category_id
 * @property int|null $supplier_id
 * @property float $price
 * @property float $cost
 * @property int $stock_quantity
 * @property int $min_stock_level
 * @property int|null $max_stock_level
 * @property string $unit
 * @property string|null $barcode
 * @property bool $track_stock
 * @property bool $is_active
 * @property array|null $images
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read InventoryCategory|null $category
 * @property-read InventorySupplier|null $supplier
 * @property-read Collection<int, InventoryStockMovement> $stockMovements
 * @property-read int|null $stock_movements_count
 * @property-read Collection<int, InventoryBorrowing> $borrowings
 * @property-read int|null $borrowings_count
 * @property-read Collection<int, InventoryAmendment> $amendments
 * @property-read int|null $amendments_count
 * @method static Builder<static>|InventoryProduct newModelQuery()
 * @method static Builder<static>|InventoryProduct newQuery()
 * @method static Builder<static>|InventoryProduct query()
 * @method static Builder<static>|InventoryProduct whereId($value)
 * @method static Builder<static>|InventoryProduct whereName($value)
 * @method static Builder<static>|InventoryProduct whereSku($value)
 * @method static Builder<static>|InventoryProduct whereDescription($value)
 * @method static Builder<static>|InventoryProduct whereCategoryId($value)
 * @method static Builder<static>|InventoryProduct whereSupplierId($value)
 * @method static Builder<static>|InventoryProduct wherePrice($value)
 * @method static Builder<static>|InventoryProduct whereCost($value)
 * @method static Builder<static>|InventoryProduct whereStockQuantity($value)
 * @method static Builder<static>|InventoryProduct whereMinStockLevel($value)
 * @method static Builder<static>|InventoryProduct whereMaxStockLevel($value)
 * @method static Builder<static>|InventoryProduct whereUnit($value)
 * @method static Builder<static>|InventoryProduct whereBarcode($value)
 * @method static Builder<static>|InventoryProduct whereTrackStock($value)
 * @method static Builder<static>|InventoryProduct whereIsActive($value)
 * @method static Builder<static>|InventoryProduct whereImages($value)
 * @method static Builder<static>|InventoryProduct whereNotes($value)
 * @method static Builder<static>|InventoryProduct whereCreatedAt($value)
 * @method static Builder<static>|InventoryProduct whereUpdatedAt($value)
 * @mixin \Eloquent
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InventoryProduct lowStock()
 */
	final class InventoryProduct extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $product_id
 * @property string $type
 * @property int $quantity
 * @property int $previous_stock
 * @property int $new_stock
 * @property string|null $reference
 * @property string|null $reason
 * @property int|null $user_id
 * @property Carbon $movement_date
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read InventoryProduct $product
 * @property-read User|null $user
 * @method static Builder<static>|InventoryStockMovement newModelQuery()
 * @method static Builder<static>|InventoryStockMovement newQuery()
 * @method static Builder<static>|InventoryStockMovement query()
 * @method static Builder<static>|InventoryStockMovement whereId($value)
 * @method static Builder<static>|InventoryStockMovement whereProductId($value)
 * @method static Builder<static>|InventoryStockMovement whereType($value)
 * @method static Builder<static>|InventoryStockMovement whereQuantity($value)
 * @method static Builder<static>|InventoryStockMovement wherePreviousStock($value)
 * @method static Builder<static>|InventoryStockMovement whereNewStock($value)
 * @method static Builder<static>|InventoryStockMovement whereReference($value)
 * @method static Builder<static>|InventoryStockMovement whereReason($value)
 * @method static Builder<static>|InventoryStockMovement whereUserId($value)
 * @method static Builder<static>|InventoryStockMovement whereMovementDate($value)
 * @method static Builder<static>|InventoryStockMovement whereCreatedAt($value)
 * @method static Builder<static>|InventoryStockMovement whereUpdatedAt($value)
 * @mixin \Eloquent
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InventoryStockMovement ofType(string $type)
 */
	final class InventoryStockMovement extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $name
 * @property string|null $contact_person
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $address
 * @property string|null $city
 * @property string|null $state
 * @property string|null $postal_code
 * @property string|null $country
 * @property string|null $tax_number
 * @property string|null $notes
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, InventoryProduct> $products
 * @property-read int|null $products_count
 * @method static Builder<static>|InventorySupplier newModelQuery()
 * @method static Builder<static>|InventorySupplier newQuery()
 * @method static Builder<static>|InventorySupplier query()
 * @method static Builder<static>|InventorySupplier whereId($value)
 * @method static Builder<static>|InventorySupplier whereName($value)
 * @method static Builder<static>|InventorySupplier whereContactPerson($value)
 * @method static Builder<static>|InventorySupplier whereEmail($value)
 * @method static Builder<static>|InventorySupplier wherePhone($value)
 * @method static Builder<static>|InventorySupplier whereAddress($value)
 * @method static Builder<static>|InventorySupplier whereCity($value)
 * @method static Builder<static>|InventorySupplier whereState($value)
 * @method static Builder<static>|InventorySupplier wherePostalCode($value)
 * @method static Builder<static>|InventorySupplier whereCountry($value)
 * @method static Builder<static>|InventorySupplier whereTaxNumber($value)
 * @method static Builder<static>|InventorySupplier whereNotes($value)
 * @method static Builder<static>|InventorySupplier whereIsActive($value)
 * @method static Builder<static>|InventorySupplier whereCreatedAt($value)
 * @method static Builder<static>|InventorySupplier whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	final class InventorySupplier extends \Eloquent {}
}

namespace App\Models{
/**
 * @property-read User|null $approver
 * @property-read Course|null $course
 * @property-read mixed $course_id
 * @property-read mixed $email
 * @property-read mixed $first_name
 * @property-read mixed $last_name
 * @method static Builder<static>|PendingEnrollment newModelQuery()
 * @method static Builder<static>|PendingEnrollment newQuery()
 * @method static Builder<static>|PendingEnrollment query()
 * @mixin \Eloquent
 * @property int $id
 * @property array<array-key, mixed> $data
 * @property string $status
 * @property string|null $remarks
 * @property int|null $approved_by
 * @property \Illuminate\Support\Carbon|null $processed_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PendingEnrollment whereApprovedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PendingEnrollment whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PendingEnrollment whereData($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PendingEnrollment whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PendingEnrollment whereProcessedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PendingEnrollment whereRemarks($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PendingEnrollment whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PendingEnrollment whereUpdatedAt($value)
 */
	final class PendingEnrollment extends \Eloquent {}
}

namespace App\Models{
/**
 * Class PendingUserEmail
 *
 * @method static Builder<static>|PendingUserEmail newModelQuery()
 * @method static Builder<static>|PendingUserEmail newQuery()
 * @method static Builder<static>|PendingUserEmail query()
 * @mixin \Eloquent
 * @property int $id
 * @property string $user_type
 * @property float $user_id
 * @property string $email
 * @property string $token
 * @property string|null $created_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PendingUserEmail whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PendingUserEmail whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PendingUserEmail whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PendingUserEmail whereToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PendingUserEmail whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PendingUserEmail whereUserType($value)
 */
	final class PendingUserEmail extends \Eloquent {}
}

namespace App\Models{
/**
 * Class Resource
 *
 * @method static Builder<static>|Resource newModelQuery()
 * @method static Builder<static>|Resource newQuery()
 * @method static Builder<static>|Resource query()
 * @mixin \Eloquent
 * @property int $id
 * @property string $resourceable_type
 * @property int $resourceable_id
 * @property string $type
 * @property string $file_path
 * @property string $file_name
 * @property string|null $mime_type
 * @property string $disk
 * @property int|null $file_size
 * @property array<array-key, mixed>|null $metadata
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Resource whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Resource whereDisk($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Resource whereFileName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Resource whereFilePath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Resource whereFileSize($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Resource whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Resource whereMetadata($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Resource whereMimeType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Resource whereResourceableId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Resource whereResourceableType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Resource whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Resource whereUpdatedAt($value)
 */
	final class Resource extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $resource_type
 * @property string $resource_name
 * @property string|null $resource_description
 * @property string|null $location
 * @property int|null $capacity
 * @property string|null $features
 * @property string|null $hourly_rate
 * @property string|null $availability_schedule
 * @property bool $requires_approval
 * @property bool $is_active
 * @property string|null $booking_rules
 * @property string|null $terms_and_conditions
 * @property int|null $managed_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Database\Factories\ResourceBookingFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResourceBooking newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResourceBooking newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResourceBooking query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResourceBooking whereAvailabilitySchedule($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResourceBooking whereBookingRules($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResourceBooking whereCapacity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResourceBooking whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResourceBooking whereFeatures($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResourceBooking whereHourlyRate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResourceBooking whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResourceBooking whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResourceBooking whereLocation($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResourceBooking whereManagedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResourceBooking whereRequiresApproval($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResourceBooking whereResourceDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResourceBooking whereResourceName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResourceBooking whereResourceType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResourceBooking whereTermsAndConditions($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResourceBooking whereUpdatedAt($value)
 */
	final class ResourceBooking extends \Eloquent {}
}

namespace App\Models{
/**
 * Class Room
 *
 * @property-read Collection<int, Classes> $classes
 * @property-read int|null $classes_count
 * @property-read Collection<int, Schedule> $schedules
 * @property-read int|null $schedules_count
 * @method static Builder<static>|Room newModelQuery()
 * @method static Builder<static>|Room newQuery()
 * @method static Builder<static>|Room query()
 * @mixin \Eloquent
 * @property int $id
 * @property string $name
 * @property string $class_code
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property bool $is_active
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Room active()
 * @method static \Database\Factories\RoomFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Room whereClassCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Room whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Room whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Room whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Room whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Room whereUpdatedAt($value)
 */
	final class Room extends \Eloquent {}
}

namespace App\Models{
/**
 * @property-read Classes|null $class
 * @property-read string $formatted_end_time
 * @property-read string $formatted_start_time
 * @property-read mixed $subject
 * @property-read string $time_range
 * @property-read Room|null $room
 * @method static Builder<static>|Schedule currentAcademicPeriod()
 * @method static Builder<static>|Schedule newModelQuery()
 * @method static Builder<static>|Schedule newQuery()
 * @method static Builder<static>|Schedule onlyTrashed()
 * @method static Builder<static>|Schedule query()
 * @method static Builder<static>|Schedule withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|Schedule withoutTrashed()
 * @mixin \Eloquent
 * @property int $id
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property string $day_of_week
 * @property \Illuminate\Support\Carbon $start_time
 * @property \Illuminate\Support\Carbon $end_time
 * @property int|null $class_id
 * @property int|null $room_id
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Database\Factories\ScheduleFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Schedule whereClassId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Schedule whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Schedule whereDayOfWeek($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Schedule whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Schedule whereEndTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Schedule whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Schedule whereRoomId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Schedule whereStartTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Schedule whereUpdatedAt($value)
 */
	final class Schedule extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $name
 * @property string $code
 * @property string|null $description
 * @property string|null $dean_name
 * @property string|null $dean_email
 * @property string|null $location
 * @property string|null $phone
 * @property string|null $email
 * @property bool $is_active
 * @property array|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Department> $departments
 * @property-read int|null $departments_count
 * @property-read Collection<int, User> $users
 * @property-read int|null $users_count
 * @property-read Collection<int, Faculty> $faculty
 * @property-read int|null $faculty_count
 * @method static SchoolFactory factory($count = null, $state = [])
 * @method static Builder<static>|School newModelQuery()
 * @method static Builder<static>|School newQuery()
 * @method static Builder<static>|School query()
 * @method static Builder<static>|School whereCode($value)
 * @method static Builder<static>|School whereCreatedAt($value)
 * @method static Builder<static>|School whereDeanEmail($value)
 * @method static Builder<static>|School whereDeanName($value)
 * @method static Builder<static>|School whereDescription($value)
 * @method static Builder<static>|School whereEmail($value)
 * @method static Builder<static>|School whereId($value)
 * @method static Builder<static>|School whereIsActive($value)
 * @method static Builder<static>|School whereLocation($value)
 * @method static Builder<static>|School whereMetadata($value)
 * @method static Builder<static>|School whereName($value)
 * @method static Builder<static>|School wherePhone($value)
 * @method static Builder<static>|School whereUpdatedAt($value)
 * @method static Builder<static>|School active()
 * @mixin \Eloquent
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Department> $activeDepartments
 * @property-read int|null $active_departments_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Course> $courses
 * @property-read int|null $courses_count
 * @property-read string $full_name
 */
	final class School extends \Eloquent {}
}

namespace App\Models{
/**
 * Class ShsStrand
 *
 * @property-read Collection<int, ShsStudent> $students
 * @property-read int|null $students_count
 * @property-read ShsTrack|null $track
 * @method static Builder<static>|ShsStrand newModelQuery()
 * @method static Builder<static>|ShsStrand newQuery()
 * @method static Builder<static>|ShsStrand query()
 * @mixin \Eloquent
 * @property int $id
 * @property int $track_id
 * @property string $strand_name
 * @property string|null $description
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShsStrand whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShsStrand whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShsStrand whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShsStrand whereStrandName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShsStrand whereTrackId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShsStrand whereUpdatedAt($value)
 */
	final class ShsStrand extends \Eloquent {}
}

namespace App\Models{
/**
 * Class ShsStudent
 *
 * @property-read Account|null $account
 * @property-read ShsStrand|null $strand
 * @property-read ShsTrack|null $track
 * @method static Builder<static>|ShsStudent newModelQuery()
 * @method static Builder<static>|ShsStudent newQuery()
 * @method static Builder<static>|ShsStudent query()
 * @mixin \Eloquent
 * @property int $id
 * @property string|null $student_lrn
 * @property string|null $fullname
 * @property string|null $civil_status
 * @property string|null $religion
 * @property string|null $nationality
 * @property \Illuminate\Support\Carbon|null $birthdate
 * @property string|null $guardian_name
 * @property string|null $guardian_contact
 * @property string|null $student_contact
 * @property string|null $complete_address
 * @property string|null $grade_level
 * @property string|null $gender
 * @property string|null $email
 * @property string|null $remarks
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int|null $strand_id
 * @property int|null $track_id
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShsStudent whereBirthdate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShsStudent whereCivilStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShsStudent whereCompleteAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShsStudent whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShsStudent whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShsStudent whereFullname($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShsStudent whereGender($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShsStudent whereGradeLevel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShsStudent whereGuardianContact($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShsStudent whereGuardianName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShsStudent whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShsStudent whereNationality($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShsStudent whereReligion($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShsStudent whereRemarks($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShsStudent whereStrandId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShsStudent whereStudentContact($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShsStudent whereStudentLrn($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShsStudent whereTrack($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShsStudent whereTrackId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShsStudent whereUpdatedAt($value)
 */
	final class ShsStudent extends \Eloquent {}
}

namespace App\Models{
/**
 * Class ShsTrack
 *
 * @property-read Collection<int, ShsStrand> $strands
 * @property-read int|null $strands_count
 * @property-read Collection<int, ShsStudent> $students
 * @property-read int|null $students_count
 * @method static Builder<static>|ShsTrack newModelQuery()
 * @method static Builder<static>|ShsTrack newQuery()
 * @method static Builder<static>|ShsTrack query()
 * @mixin \Eloquent
 * @property int $id
 * @property string $track_name
 * @property string|null $description
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShsTrack whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShsTrack whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShsTrack whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShsTrack whereTrackName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShsTrack whereUpdatedAt($value)
 */
	final class ShsTrack extends \Eloquent {}
}

namespace App\Models{
/**
 * Class StrandSubject
 *
 * @property-read ShsStrand|null $strand
 * @method static Builder<static>|StrandSubject newModelQuery()
 * @method static Builder<static>|StrandSubject newQuery()
 * @method static Builder<static>|StrandSubject query()
 * @mixin \Eloquent
 * @property int $id
 * @property string $code
 * @property string $title
 * @property string|null $description
 * @property string $grade_year
 * @property string $semester
 * @property int $strand_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StrandSubject whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StrandSubject whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StrandSubject whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StrandSubject whereGradeYear($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StrandSubject whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StrandSubject whereSemester($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StrandSubject whereStrandId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StrandSubject whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StrandSubject whereUpdatedAt($value)
 */
	final class StrandSubject extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $institution_id
 * @property int $student_id
 * @property string|null $lrn
 * @property string $student_type
 * @property string $first_name
 * @property string|null $middle_name
 * @property string $last_name
 * @property string|null $suffix
 * @property string|null $email
 * @property string|null $phone
 * @property Carbon $birth_date
 * @property string $gender
 * @property string $civil_status
 * @property string $nationality
 * @property string|null $religion
 * @property string|null $address
 * @property string|null $emergency_contact
 * @property string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, ClassEnrollment> $Classes
 * @property-read int|null $classes_count
 * @property-read Course|null $Course
 * @property-read DocumentLocation|null $DocumentLocation
 * @property-read Collection<int, StudentTransaction> $StudentTransactions
 * @property-read int|null $student_transactions_count
 * @property-read Collection<int, StudentTuition> $StudentTuition
 * @property-read int|null $student_tuition_count
 * @property-read Collection<int, Transaction> $Transaction
 * @property-read int|null $transaction_count
 * @property-read Account|null $account
 * @property-read Collection<int, ClassEnrollment> $classEnrollments
 * @property-read int|null $class_enrollments_count
 * @property-read Collection<int, StudentClearance> $clearances
 * @property-read int|null $clearances_count
 * @property-read string $formatted_academic_year
 * @property-read mixed $full_name
 * @property-read mixed $picture1x1
 * @property-read mixed $student_picture
 * @property-read DatabaseNotificationCollection<int, DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read StudentsPersonalInfo|null $personalInfo
 * @property-read Collection<int, resource> $resources
 * @property-read int|null $resources_count
 * @property-read StudentContact|null $studentContactsInfo
 * @property-read StudentEducationInfo|null $studentEducationInfo
 * @property-read StudentParentsInfo|null $studentParentInfo
 * @property-read Collection<int, SubjectEnrollment> $subjectEnrolled
 * @property-read int|null $subject_enrolled_count
 * @property-read Collection<int, SubjectEnrollment> $subjectEnrolledCurrent
 * @property-read int|null $subject_enrolled_current_count
 * @property-read Collection<int, Subject> $subjects
 * @property-read int|null $subjects_count
 * @property-read Collection<int, StudentTransaction> $transactions
 * @property-read int|null $transactions_count
 * @method static Builder<static>|Student newModelQuery()
 * @method static Builder<static>|Student newQuery()
 * @method static Builder<static>|Student onlyTrashed()
 * @method static Builder<static>|Student query()
 * @method static Builder<static>|Student whereAddress($value)
 * @method static Builder<static>|Student whereBirthDate($value)
 * @method static Builder<static>|Student whereCivilStatus($value)
 * @method static Builder<static>|Student whereCreatedAt($value)
 * @method static Builder<static>|Student whereEmail($value)
 * @method static Builder<static>|Student whereEmergencyContact($value)
 * @method static Builder<static>|Student whereFirstName($value)
 * @method static Builder<static>|Student whereGender($value)
 * @method static Builder<static>|Student whereId($value)
 * @method static Builder<static>|Student whereInstitutionId($value)
 * @method static Builder<static>|Student whereLastName($value)
 * @method static Builder<static>|Student whereLrn($value)
 * @method static Builder<static>|Student whereMiddleName($value)
 * @method static Builder<static>|Student whereNationality($value)
 * @method static Builder<static>|Student wherePhone($value)
 * @method static Builder<static>|Student whereReligion($value)
 * @method static Builder<static>|Student whereStatus($value)
 * @method static Builder<static>|Student whereStudentId($value)
 * @method static Builder<static>|Student whereStudentType($value)
 * @method static Builder<static>|Student whereSuffix($value)
 * @method static Builder<static>|Student whereUpdatedAt($value)
 * @method static Builder<static>|Student withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|Student withoutTrashed()
 * @mixin \Eloquent
 * @property int $age
 * @property array<array-key, mixed>|null $contacts
 * @property int $course_id
 * @property int|null $academic_year
 * @property string|null $remarks
 * @property string|null $profile_url
 * @property int|null $student_contact_id
 * @property int|null $student_parent_info
 * @property int|null $student_education_id
 * @property int|null $student_personal_id
 * @property int|null $document_location_id
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property string|null $clearance_status
 * @property int|null $year_graduated
 * @property string|null $special_order
 * @property \Illuminate\Support\Carbon|null $issued_date
 * @property array<array-key, mixed>|null $subject_enrolled
 * @property int|null $user_id
 * @property string|null $ethnicity
 * @property string|null $city_of_origin
 * @property string|null $province_of_origin
 * @property string|null $region_of_origin
 * @property bool $is_indigenous_person
 * @property string|null $indigenous_group
 * @property \Illuminate\Support\Carbon|null $withdrawal_date
 * @property string|null $withdrawal_reason
 * @property \App\Enums\AttritionCategory|null $attrition_category
 * @property \Illuminate\Support\Carbon|null $dropout_date
 * @property \App\Enums\EmploymentStatus|null $employment_status
 * @property string|null $employer_name
 * @property string|null $job_position
 * @property \Illuminate\Support\Carbon|null $employment_date
 * @property bool $employed_by_institution
 * @property \App\Enums\ScholarshipType|null $scholarship_type
 * @property string|null $scholarship_details
 * @property-read \Overtrue\LaravelVersionable\Version|null $firstVersion
 * @property-read \Overtrue\LaravelVersionable\Version|null $lastVersion
 * @property-read \Overtrue\LaravelVersionable\Version|null $latestVersion
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Modules\StudentMedicalRecords\Models\MedicalRecord> $medicalRecords
 * @property-read int|null $medical_records_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Overtrue\LaravelVersionable\Version> $versions
 * @property-read int|null $versions_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Student applicants()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Student byRegion(string $region)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Student byScholarship(\App\Enums\ScholarshipType $type)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Student dropped()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Student employed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Student enrolled()
 * @method static \Database\Factories\StudentFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Student graduates()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Student indigenous()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Student scholars()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Student whereAcademicYear($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Student whereAge($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Student whereAttritionCategory($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Student whereCityOfOrigin($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Student whereClearanceStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Student whereContacts($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Student whereCourseId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Student whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Student whereDocumentLocationId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Student whereDropoutDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Student whereEmployedByInstitution($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Student whereEmployerName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Student whereEmploymentDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Student whereEmploymentStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Student whereEthnicity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Student whereIndigenousGroup($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Student whereIsIndigenousPerson($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Student whereIssuedDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Student whereJobPosition($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Student whereProfileUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Student whereProvinceOfOrigin($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Student whereRegionOfOrigin($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Student whereRemarks($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Student whereScholarshipDetails($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Student whereScholarshipType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Student whereSpecialOrder($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Student whereStudentContactId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Student whereStudentEducationId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Student whereStudentParentInfo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Student whereStudentPersonalId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Student whereSubjectEnrolled($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Student whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Student whereWithdrawalDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Student whereWithdrawalReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Student whereYearGraduated($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Student withdrawn()
 */
	final class Student extends \Eloquent {}
}

namespace App\Models{
/**
 * @property-read string $formatted_semester
 * @property-read Student|null $student
 * @method static Builder<static>|StudentClearance newModelQuery()
 * @method static Builder<static>|StudentClearance newQuery()
 * @method static Builder<static>|StudentClearance query()
 * @mixin \Eloquent
 * @property int $id
 * @property int $student_id
 * @property string $academic_year
 * @property int $semester
 * @property bool $is_cleared
 * @property string|null $remarks
 * @property string|null $cleared_by
 * @property \Illuminate\Support\Carbon|null $cleared_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentClearance whereAcademicYear($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentClearance whereClearedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentClearance whereClearedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentClearance whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentClearance whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentClearance whereIsCleared($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentClearance whereRemarks($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentClearance whereSemester($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentClearance whereStudentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentClearance whereUpdatedAt($value)
 */
	final class StudentClearance extends \Eloquent {}
}

namespace App\Models{
/**
 * Class StudentContact
 *
 * @method static Builder<static>|StudentContact newModelQuery()
 * @method static Builder<static>|StudentContact newQuery()
 * @method static Builder<static>|StudentContact query()
 * @mixin \Eloquent
 * @property int $id
 * @property string|null $emergency_contact_name
 * @property string|null $emergency_contact_phone
 * @property string|null $emergency_contact_address
 * @property string|null $facebook_contact
 * @property string|null $personal_contact
 * @property string|null $created_at
 * @property string|null $updated_at
 * @property int|null $student_id
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentContact whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentContact whereEmergencyContactAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentContact whereEmergencyContactName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentContact whereEmergencyContactPhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentContact whereFacebookContact($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentContact whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentContact wherePersonalContact($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentContact whereStudentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentContact whereUpdatedAt($value)
 */
	final class StudentContact extends \Eloquent {}
}

namespace App\Models{
/**
 * Class StudentEducationInfo
 *
 * @method static Builder<static>|StudentEducationInfo newModelQuery()
 * @method static Builder<static>|StudentEducationInfo newQuery()
 * @method static Builder<static>|StudentEducationInfo query()
 * @mixin \Eloquent
 * @property int $id
 * @property string|null $elementary_school
 * @property int|null $elementary_graduate_year
 * @property string|null $senior_high_name
 * @property int|null $senior_high_graduate_year
 * @property string|null $elementary_school_address
 * @property string|null $senior_high_address
 * @property string|null $junior_high_school_name
 * @property string|null $junior_high_school_address
 * @property string|null $junior_high_graduation_year
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentEducationInfo whereElementaryGraduateYear($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentEducationInfo whereElementarySchool($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentEducationInfo whereElementarySchoolAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentEducationInfo whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentEducationInfo whereJuniorHighGraduationYear($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentEducationInfo whereJuniorHighSchoolAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentEducationInfo whereJuniorHighSchoolName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentEducationInfo whereSeniorHighAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentEducationInfo whereSeniorHighGraduateYear($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentEducationInfo whereSeniorHighName($value)
 */
	final class StudentEducationInfo extends \Eloquent {}
}

namespace App\Models{
/**
 * Class StudentEnrollment
 *
 * @property-read Collection<int, AdditionalFee> $additionalFees
 * @property-read int|null $additional_fees_count
 * @property-read Course|null $course
 * @property-read string $assessment_path
 * @property-read string $assessment_url
 * @property-read string $certificate_path
 * @property-read string $certificate_url
 * @property-read string $student_name
 * @property-read Collection<int, resource> $resources
 * @property-read int|null $resources_count
 * @property-read Student|null $student
 * @property-read StudentTuition|null $studentTuition
 * @property-read Collection<int, SubjectEnrollment> $subjectsEnrolled
 * @property-read int|null $subjects_enrolled_count
 * @method static Builder<static>|StudentEnrollment currentAcademicPeriod()
 * @method static Builder<static>|StudentEnrollment newModelQuery()
 * @method static Builder<static>|StudentEnrollment newQuery()
 * @method static Builder<static>|StudentEnrollment onlyTrashed()
 * @method static Builder<static>|StudentEnrollment query()
 * @method static Builder<static>|StudentEnrollment withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|StudentEnrollment withoutTrashed()
 * @mixin \Eloquent
 * @property int $id
 * @property string $student_id
 * @property string $course_id
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int|null $semester
 * @property int|null $academic_year
 * @property string|null $school_year
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property float|null $downpayment
 * @property string|null $remarks
 * @property string|null $payment_method
 * @property-read \Illuminate\Database\Eloquent\Collection $enrolled_classes_for_infolist
 * @property-read \Illuminate\Database\Eloquent\Collection $missing_classes_for_infolist
 * @method static \Database\Factories\StudentEnrollmentFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentEnrollment whereAcademicYear($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentEnrollment whereCourseId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentEnrollment whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentEnrollment whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentEnrollment whereDownpayment($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentEnrollment whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentEnrollment wherePaymentMethod($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentEnrollment whereRemarks($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentEnrollment whereSchoolYear($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentEnrollment whereSemester($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentEnrollment whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentEnrollment whereStudentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentEnrollment whereUpdatedAt($value)
 */
	final class StudentEnrollment extends \Eloquent {}
}

namespace App\Models{
/**
 * @property-read Account|null $changedByUser
 * @property-read Account|null $undoneByUser
 * @method static Builder<static>|StudentIdChangeLog newModelQuery()
 * @method static Builder<static>|StudentIdChangeLog newQuery()
 * @method static Builder<static>|StudentIdChangeLog query()
 * @method static Builder<static>|StudentIdChangeLog undoable()
 * @method static Builder<static>|StudentIdChangeLog undone()
 * @mixin \Eloquent
 * @property int $id
 * @property string $old_student_id
 * @property string $new_student_id
 * @property string $student_name
 * @property string $changed_by
 * @property array<array-key, mixed> $affected_records
 * @property array<array-key, mixed>|null $backup_data
 * @property bool $is_undone
 * @property \Illuminate\Support\Carbon|null $undone_at
 * @property string|null $undone_by
 * @property string|null $reason
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Database\Factories\StudentIdChangeLogFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentIdChangeLog whereAffectedRecords($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentIdChangeLog whereBackupData($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentIdChangeLog whereChangedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentIdChangeLog whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentIdChangeLog whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentIdChangeLog whereIsUndone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentIdChangeLog whereNewStudentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentIdChangeLog whereOldStudentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentIdChangeLog whereReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentIdChangeLog whereStudentName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentIdChangeLog whereUndoneAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentIdChangeLog whereUndoneBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentIdChangeLog whereUpdatedAt($value)
 */
	final class StudentIdChangeLog extends \Eloquent {}
}

namespace App\Models{
/**
 * Class StudentParentsInfo
 *
 * @method static Builder<static>|StudentParentsInfo newModelQuery()
 * @method static Builder<static>|StudentParentsInfo newQuery()
 * @method static Builder<static>|StudentParentsInfo query()
 * @mixin \Eloquent
 * @property int $id
 * @property string|null $fathers_name
 * @property string|null $mothers_name
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentParentsInfo whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentParentsInfo whereFathersName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentParentsInfo whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentParentsInfo whereMothersName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentParentsInfo whereUpdatedAt($value)
 */
	final class StudentParentsInfo extends \Eloquent {}
}

namespace App\Models{
/**
 * Class StudentTransaction
 *
 * @property-read Student|null $student
 * @property-read Transaction|null $transaction
 * @method static Builder<static>|StudentTransaction newModelQuery()
 * @method static Builder<static>|StudentTransaction newQuery()
 * @method static Builder<static>|StudentTransaction query()
 * @mixin \Eloquent
 * @property int $id
 * @property int $student_id
 * @property int $transaction_id
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int|null $amount
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentTransaction whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentTransaction whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentTransaction whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentTransaction whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentTransaction whereStudentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentTransaction whereTransactionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentTransaction whereUpdatedAt($value)
 */
	final class StudentTransaction extends \Eloquent {}
}

namespace App\Models{
/**
 * Class StudentTuition
 *
 * @property-read StudentEnrollment|null $enrollment
 * @property-read string $formatted_discount
 * @property-read string $formatted_downpayment
 * @property-read string $formatted_overall_tuition
 * @property-read string $formatted_semester
 * @property-read string $formatted_total_balance
 * @property-read string $formatted_total_laboratory
 * @property-read string $formatted_total_lectures
 * @property-read string $formatted_total_miscelaneous_fees
 * @property-read string $formatted_total_tuition
 * @property-read int $payment_progress
 * @property-read string $payment_status
 * @property-read string $status_class
 * @property-read Student|null $student
 * @method static Builder<static>|StudentTuition newModelQuery()
 * @method static Builder<static>|StudentTuition newQuery()
 * @method static Builder<static>|StudentTuition onlyTrashed()
 * @method static Builder<static>|StudentTuition query()
 * @method static Builder<static>|StudentTuition withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|StudentTuition withoutTrashed()
 * @mixin \Eloquent
 * @property int $id
 * @property float|null $total_tuition
 * @property float|null $total_balance
 * @property float|null $total_lectures
 * @property float|null $total_laboratory
 * @property float|null $total_miscelaneous_fees
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $status
 * @property int|null $semester
 * @property string|null $school_year
 * @property int|null $academic_year
 * @property int|null $student_id
 * @property int|null $enrollment_id
 * @property int|null $discount
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property float|null $overall_tuition
 * @property int|null $paid
 * @property float|null $downpayment
 * @property string|null $due_date
 * @property string|null $payment_method
 * @property float|null $original_lectures Original lecture fee amount before discount application
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentTuition whereAcademicYear($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentTuition whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentTuition whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentTuition whereDiscount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentTuition whereDownpayment($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentTuition whereDueDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentTuition whereEnrollmentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentTuition whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentTuition whereOriginalLectures($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentTuition whereOverallTuition($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentTuition wherePaid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentTuition wherePaymentMethod($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentTuition whereSchoolYear($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentTuition whereSemester($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentTuition whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentTuition whereStudentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentTuition whereTotalBalance($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentTuition whereTotalLaboratory($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentTuition whereTotalLectures($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentTuition whereTotalMiscelaneousFees($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentTuition whereTotalTuition($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentTuition whereUpdatedAt($value)
 */
	final class StudentTuition extends \Eloquent {}
}

namespace App\Models{
/**
 * Class StudentsPersonalInfo
 *
 * @method static Builder<static>|StudentsPersonalInfo newModelQuery()
 * @method static Builder<static>|StudentsPersonalInfo newQuery()
 * @method static Builder<static>|StudentsPersonalInfo query()
 * @mixin \Eloquent
 * @property int $id
 * @property string|null $birthplace
 * @property string|null $civil_status
 * @property string|null $citizenship
 * @property string|null $religion
 * @property string|null $weight
 * @property string|null $height
 * @property string|null $current_adress
 * @property string|null $permanent_address
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentsPersonalInfo whereBirthplace($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentsPersonalInfo whereCitizenship($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentsPersonalInfo whereCivilStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentsPersonalInfo whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentsPersonalInfo whereCurrentAdress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentsPersonalInfo whereHeight($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentsPersonalInfo whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentsPersonalInfo wherePermanentAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentsPersonalInfo whereReligion($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentsPersonalInfo whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentsPersonalInfo whereWeight($value)
 */
	final class StudentsPersonalInfo extends \Eloquent {}
}

namespace App\Models{
/**
 * Class Subject
 *
 * @property SubjectEnrolledEnum $classification
 * @property-read Collection<int, Classes> $classes
 * @property-read int|null $classes_count
 * @property-read Course|null $course
 * @property-read mixed $all_pre_requisites
 * @property-read int|float $laboratory_fee
 * @property-read int|float $lecture_fee
 * @property-read Collection<int, SubjectEnrollment> $subjectEnrolleds
 * @property-read int|null $subject_enrolleds_count
 * @method static Builder<static>|Subject credited()
 * @method static Builder<static>|Subject newModelQuery()
 * @method static Builder<static>|Subject newQuery()
 * @method static Builder<static>|Subject nonCredited()
 * @method static Builder<static>|Subject query()
 * @mixin \Eloquent
 * @property int $id
 * @property string $code
 * @property string $title
 * @property int|null $units
 * @property int|null $lecture
 * @property int|null $laboratory
 * @property array<array-key, mixed>|null $pre_riquisite
 * @property int|null $academic_year
 * @property int|null $semester
 * @property int|null $course_id
 * @property string|null $group
 * @property bool $is_credited
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $description
 * @property string|null $name
 * @method static \Database\Factories\SubjectFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subject whereAcademicYear($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subject whereClassification($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subject whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subject whereCourseId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subject whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subject whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subject whereGroup($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subject whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subject whereIsCredited($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subject whereLaboratory($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subject whereLecture($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subject whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subject wherePreRiquisite($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subject whereSemester($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subject whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subject whereUnits($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subject whereUpdatedAt($value)
 */
	final class Subject extends \Eloquent {}
}

namespace App\Models{
/**
 * Class SubjectEnrollment
 *
 * @property-read Classes|null $class
 * @property-read Student|null $student
 * @property-read StudentEnrollment|null $studentEnrollment
 * @property-read Subject|null $subject
 * @method static Builder<static>|SubjectEnrollment newModelQuery()
 * @method static Builder<static>|SubjectEnrollment newQuery()
 * @method static Builder<static>|SubjectEnrollment query()
 * @mixin \Eloquent
 * @property int $id
 * @property int|null $subject_id
 * @property int|null $class_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property float|null $grade
 * @property string|null $instructor
 * @property int|null $student_id
 * @property string|null $academic_year
 * @property string|null $school_year
 * @property int|null $semester
 * @property int|null $enrollment_id
 * @property string|null $remarks
 * @property string|null $classification
 * @property string|null $school_name
 * @property bool $is_credited
 * @property int|null $credited_subject_id
 * @property string|null $section
 * @property bool $is_modular
 * @property float|null $lecture_fee
 * @property float|null $laboratory_fee
 * @property int|null $enrolled_lecture_units
 * @property int|null $enrolled_laboratory_units
 * @property string|null $external_subject_code
 * @property string|null $external_subject_title
 * @property int|null $external_subject_units
 * @property-read \App\Models\Subject|null $creditedSubject
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubjectEnrollment whereAcademicYear($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubjectEnrollment whereClassId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubjectEnrollment whereClassification($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubjectEnrollment whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubjectEnrollment whereCreditedSubjectId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubjectEnrollment whereEnrolledLaboratoryUnits($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubjectEnrollment whereEnrolledLectureUnits($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubjectEnrollment whereEnrollmentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubjectEnrollment whereExternalSubjectCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubjectEnrollment whereExternalSubjectTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubjectEnrollment whereExternalSubjectUnits($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubjectEnrollment whereGrade($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubjectEnrollment whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubjectEnrollment whereInstructor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubjectEnrollment whereIsCredited($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubjectEnrollment whereIsModular($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubjectEnrollment whereLaboratoryFee($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubjectEnrollment whereLectureFee($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubjectEnrollment whereRemarks($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubjectEnrollment whereSchoolName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubjectEnrollment whereSchoolYear($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubjectEnrollment whereSection($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubjectEnrollment whereSemester($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubjectEnrollment whereStudentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubjectEnrollment whereSubjectId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubjectEnrollment whereUpdatedAt($value)
 */
	final class SubjectEnrollment extends \Eloquent {}
}

namespace App\Models{
/**
 * Class TracksStrand
 *
 * @method static Builder<static>|TracksStrand newModelQuery()
 * @method static Builder<static>|TracksStrand newQuery()
 * @method static Builder<static>|TracksStrand query()
 * @mixin \Eloquent
 * @property int $id
 * @property string $code
 * @property string $title
 * @property string|null $description
 * @property int $track_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TracksStrand whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TracksStrand whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TracksStrand whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TracksStrand whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TracksStrand whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TracksStrand whereTrackId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TracksStrand whereUpdatedAt($value)
 */
	final class TracksStrand extends \Eloquent {}
}

namespace App\Models{
/**
 * Class Transaction
 *
 * @property-read Collection<int, AdminTransaction> $adminTransactions
 * @property-read int|null $admin_transactions_count
 * @property-read array $academic_period
 * @property-read float $raw_total_amount
 * @property-read string $student_course
 * @property-read mixed $student_email
 * @property-read mixed $student_full_name
 * @property-read mixed $student_id
 * @property-read mixed $student_personal_contact
 * @property-read string|float $total_amount
 * @property-read string $transaction_type_string
 * @property-read Collection<int, Student> $student
 * @property-read int|null $student_count
 * @property-read Collection<int, StudentTransaction> $studentTransactions
 * @property-read int|null $student_transactions_count
 * @method static Builder<static>|Transaction dateRange($startDate = null, $endDate = null)
 * @method static Builder<static>|Transaction forAcademicPeriod(string $schoolYear, int $semester)
 * @method static Builder<static>|Transaction newModelQuery()
 * @method static Builder<static>|Transaction newQuery()
 * @method static Builder<static>|Transaction query()
 * @method static Builder<static>|Transaction sort($field = 'created_at', $direction = 'desc')
 * @method static Builder<static>|Transaction status($status = null)
 * @mixin \Eloquent
 * @property int $id
 * @property string|null $transaction_number
 * @property string $description
 * @property string $status
 * @property \Illuminate\Support\Carbon $transaction_date
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $invoicenumber
 * @property array<array-key, mixed>|null $settlements
 * @property string|null $signature
 * @property string|null $payment_method
 * @property int|null $user_id
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereInvoicenumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction wherePaymentMethod($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereSettlements($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereSignature($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereTransactionDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereTransactionNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereUserId($value)
 */
	final class Transaction extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id * @property string $name
 * @property string $email
 * @property UserRole $role
 * @property int|null $school_id
 * @property int|null $department_id
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $remember_token
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $avatar_url
 * @property string|null $theme_color
 * @property-read bool $is_cashier
 * @property-read bool $is_dept_head
 * @property-read bool $is_registrar
 * @property-read bool $is_super_admin
 * @property-read string $view_title_course
 * @property-read array $viewable_courses
 * @property-read DatabaseNotificationCollection<int, DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read Collection<int, AdminTransaction> $transactions
 * @property-read int|null $transactions_count
 * @property-read School|null $school
 * @property-read Department|null $department
 * @method static UserFactory factory($count = null, $state = [])
 * @method static Builder<static>|User newModelQuery()
 * @method static Builder<static>|User newQuery()
 * @method static Builder<static>|User onlyTrashed()
 * @method static Builder<static>|User query()
 * @method static Builder<static>|User whereAvatarUrl($value)
 * @method static Builder<static>|User whereCreatedAt($value)
 * @method static Builder<static>|User whereDeletedAt($value)
 * @method static Builder<static>|User whereEmail($value)
 * @method static Builder<static>|User whereEmailVerifiedAt($value)
 * @method static Builder<static>|User whereId($value)
 * @method static Builder<static>|User whereName($value)
 * @method static Builder<static>|User wherePassword($value)
 * @method static Builder<static>|User whereRememberToken($value)
 * @method static Builder<static>|User whereRole($value)
 * @method static Builder<static>|User whereSchoolId($value)
 * @method static Builder<static>|User whereDepartmentId($value)
 * @method static Builder<static>|User whereThemeColor($value)
 * @method static Builder<static>|User whereUpdatedAt($value)
 * @method static Builder<static>|User withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|User withoutTrashed()
 * @mixin \Eloquent
 * @property string|null $name
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property string|null $two_factor_confirmed_at
 * @property int|null $current_team_id
 * @property string|null $theme
 * @property string|null $profile_photo_path
 * @property bool $active_status
 * @property string $avatar
 * @property bool $dark_mode
 * @property string|null $messenger_color
 * @property string|null $phone
 * @property string|null $phone_verified_at
 * @property string $timezone
 * @property string $language
 * @property string|null $last_login_at
 * @property string|null $last_login_ip
 * @property bool $two_factor_enabled
 * @property int $login_attempts
 * @property string|null $locked_until
 * @property string|null $password_changed_at
 * @property bool $must_change_password
 * @property string $account_status
 * @property string|null $profile_completed_at
 * @property string|null $date_of_birth
 * @property string|null $gender
 * @property string|null $address
 * @property string|null $city
 * @property string|null $state
 * @property string|null $country
 * @property string|null $postal_code
 * @property string|null $bio
 * @property string|null $website
 * @property string|null $social_links
 * @property string|null $position
 * @property string|null $employee_id
 * @property string|null $hire_date
 * @property int|null $manager_id
 * @property string|null $notification_preferences
 * @property string|null $privacy_settings
 * @property bool $has_email_authentication
 * @property string|null $app_authentication_secret
 * @property array<array-key, mixed>|null $app_authentication_recovery_codes
 * @property-read string $organizational_context
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\LaravelPasskeys\Models\Passkey> $passkeys
 * @property-read int|null $passkeys_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Permission\Models\Permission> $permissions
 * @property-read int|null $permissions_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Permission\Models\Role> $roles
 * @property-read int|null $roles_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Laravel\Sanctum\PersonalAccessToken> $tokens
 * @property-read int|null $tokens_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User permission($permissions, $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User role($roles, $guard = null, $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereAccountStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereActiveStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereAppAuthenticationRecoveryCodes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereAppAuthenticationSecret($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereAvatar($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereBio($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCountry($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCurrentTeamId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereDarkMode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereDateOfBirth($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereDepartment($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmployeeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereGender($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereHasEmailAuthentication($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereHireDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereLanguage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereLastLoginAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereLastLoginIp($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereLockedUntil($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereLoginAttempts($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereManagerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereMessengerColor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereMustChangePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereNotificationPreferences($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePasswordChangedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePhoneVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePosition($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePostalCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePrivacySettings($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereProfileCompletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereProfilePhotoPath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereSocialLinks($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereState($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereTheme($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereTimezone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereTwoFactorConfirmedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereTwoFactorEnabled($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereTwoFactorRecoveryCodes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereTwoFactorSecret($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereWebsite($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withoutPermission($permissions)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withoutRole($roles, $guard = null)
 */
	final class User extends \Eloquent implements \Filament\Models\Contracts\FilamentUser, \Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication, \Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery, \Filament\Models\Contracts\HasAvatar, \Filament\Auth\MultiFactor\Email\Contracts\HasEmailAuthentication, \Spatie\LaravelPasskeys\Models\Concerns\HasPasskeys {}
}

namespace App\Models{
/**
 * @property-read User|null $user
 * @method static Builder<static>|UserSetting newModelQuery()
 * @method static Builder<static>|UserSetting newQuery()
 * @method static Builder<static>|UserSetting query()
 * @mixin \Eloquent
 * @property int $id
 * @property int $user_id
 * @property int|null $semester
 * @property int|null $school_year_start
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string $theme_preference
 * @property string $language_preference
 * @property string $timezone_preference
 * @property bool $email_notifications
 * @property bool $push_notifications
 * @property bool $sms_notifications
 * @property bool $desktop_notifications
 * @property string $notification_frequency
 * @property bool $privacy_profile_visible
 * @property bool $privacy_email_visible
 * @property bool $privacy_phone_visible
 * @property bool $privacy_show_online_status
 * @property bool $privacy_allow_direct_messages
 * @property bool $privacy_show_in_directory
 * @property bool $security_two_factor_enabled
 * @property int $security_session_timeout
 * @property bool $security_login_alerts
 * @property string $dashboard_layout
 * @property string|null $dashboard_widgets
 * @property string|null $table_preferences
 * @property bool $sidebar_collapsed
 * @property int $items_per_page
 * @property string $date_format
 * @property string $time_format
 * @property string $currency_format
 * @property string $number_format
 * @property string|null $custom_settings
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserSetting whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserSetting whereCurrencyFormat($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserSetting whereCustomSettings($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserSetting whereDashboardLayout($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserSetting whereDashboardWidgets($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserSetting whereDateFormat($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserSetting whereDesktopNotifications($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserSetting whereEmailNotifications($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserSetting whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserSetting whereItemsPerPage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserSetting whereLanguagePreference($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserSetting whereNotificationFrequency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserSetting whereNumberFormat($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserSetting wherePrivacyAllowDirectMessages($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserSetting wherePrivacyEmailVisible($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserSetting wherePrivacyPhoneVisible($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserSetting wherePrivacyProfileVisible($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserSetting wherePrivacyShowInDirectory($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserSetting wherePrivacyShowOnlineStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserSetting wherePushNotifications($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserSetting whereSchoolYearStart($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserSetting whereSecurityLoginAlerts($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserSetting whereSecuritySessionTimeout($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserSetting whereSecurityTwoFactorEnabled($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserSetting whereSemester($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserSetting whereSidebarCollapsed($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserSetting whereSmsNotifications($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserSetting whereTablePreferences($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserSetting whereThemePreference($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserSetting whereTimeFormat($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserSetting whereTimezonePreference($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserSetting whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserSetting whereUserId($value)
 */
	final class UserSetting extends \Eloquent {}
}

