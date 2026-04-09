<?php

declare(strict_types=1);

namespace App\Filament\Resources\StudentEnrollments\Api\Transformers;

use App\Models\StudentEnrollment;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property StudentEnrollment $resource
 */
final class StudentEnrollmentTransformer extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            // Basic enrollment information
            'id' => $this->resource->id,
            'student_id' => $this->resource->student_id,
            'course_id' => $this->resource->course_id,
            'status' => $this->resource->status,
            'semester' => $this->resource->semester,
            'academic_year' => $this->resource->academic_year,
            'school_year' => $this->resource->school_year,
            'downpayment' => $this->resource->downpayment,
            'remarks' => $this->resource->remarks,

            // Timestamps
            'created_at' => format_timestamp($this->resource->created_at),
            'updated_at' => format_timestamp($this->resource->updated_at),
            'deleted_at' => format_timestamp($this->resource->deleted_at),

            // Student information
            'student' => $this->when($this->resource->relationLoaded('student'), fn (): array => [
                'id' => $this->resource->student->id,
                'full_name' => $this->resource->student->full_name,
                'first_name' => $this->resource->student->first_name,
                'last_name' => $this->resource->student->last_name,
                'middle_name' => $this->resource->student->middle_name,
                'email' => $this->resource->student->email,
                'academic_year' => $this->resource->student->academic_year,
                'formatted_academic_year' => $this->resource->student->formatted_academic_year,
                'gender' => $this->resource->student->gender,
                'birth_date' => $this->resource->student->birth_date,
                'student_type' => $this->resource->student->student_type,
                'lrn' => $this->resource->student->lrn,
                'course' => $this->when($this->resource->student->relationLoaded('course'), [
                    'id' => $this->resource->student->course?->id,
                    'code' => $this->resource->student->course?->code,
                    'title' => $this->resource->student->course?->title,
                ]),
            ]),

            // Course information
            'course' => $this->when($this->resource->relationLoaded('course'), fn (): array => [
                'id' => $this->resource->course->id,
                'code' => $this->resource->course->code,
                'title' => $this->resource->course->title,
                'lec_per_unit' => $this->resource->course->lec_per_unit,
                'lab_per_unit' => $this->resource->course->lab_per_unit,
                'miscellaneous_fee' => $this->resource->course->getMiscellaneousFee(),
            ]),

            // Subjects enrolled
            'subjects_enrolled' => $this->when($this->resource->relationLoaded('subjectsEnrolled'), fn () => $this->resource->subjectsEnrolled->map(fn ($subjectEnrollment): array => [
                'id' => $subjectEnrollment->id,
                'subject_id' => $subjectEnrollment->subject_id,
                'class_id' => $subjectEnrollment->class_id,
                'is_modular' => $subjectEnrollment->is_modular,
                'enrolled_lecture_units' => $subjectEnrollment->enrolled_lecture_units,
                'enrolled_laboratory_units' => $subjectEnrollment->enrolled_laboratory_units,
                'lecture_fee' => $subjectEnrollment->lecture_fee,
                'laboratory_fee' => $subjectEnrollment->laboratory_fee,
                'school_year' => $subjectEnrollment->school_year,
                'semester' => $subjectEnrollment->semester,
                'academic_year' => $subjectEnrollment->academic_year,

                // Subject details
                'subject' => $this->when($subjectEnrollment->relationLoaded('subject'), [
                    'id' => $subjectEnrollment->subject?->id,
                    'code' => $subjectEnrollment->subject?->code,
                    'title' => $subjectEnrollment->subject?->title,
                    'lecture' => $subjectEnrollment->subject?->lecture,
                    'laboratory' => $subjectEnrollment->subject?->laboratory,
                    'units' => $subjectEnrollment->subject?->units,
                    'pre_requisite' => $subjectEnrollment->subject?->pre_riquisite,
                ]),

                // Class details
                'class' => $this->when($subjectEnrollment->relationLoaded('class'), [
                    'id' => $subjectEnrollment->class?->id,
                    'section' => $subjectEnrollment->class?->section,
                    'maximum_slots' => $subjectEnrollment->class?->maximum_slots,
                    'faculty' => $this->when($subjectEnrollment->class?->relationLoaded('faculty'), [
                        'id' => $subjectEnrollment->class?->faculty?->id,
                        'full_name' => $subjectEnrollment->class?->faculty?->full_name,
                    ]),
                    'schedule' => $this->when($subjectEnrollment->class?->relationLoaded('schedule'),
                        $subjectEnrollment->class?->schedule->map(fn ($schedule): array => [
                            'id' => $schedule->id,
                            'day_of_week' => $schedule->day_of_week,
                            'start_time' => $schedule->start_time,
                            'end_time' => $schedule->end_time,
                            'room' => $this->when($schedule->relationLoaded('room'), [
                                'id' => $schedule->room?->id,
                                'name' => $schedule->room?->name,
                            ]),
                        ])
                    ),
                ]),
            ])),

            // Tuition information
            'tuition' => $this->when($this->resource->relationLoaded('studentTuition'), fn (): array => [
                'id' => $this->resource->studentTuition?->id,
                'discount' => $this->resource->studentTuition?->discount,
                'total_lectures' => $this->resource->studentTuition?->total_lectures,
                'total_laboratory' => $this->resource->studentTuition?->total_laboratory,
                'total_tuition' => $this->resource->studentTuition?->total_tuition,
                'total_miscelaneous_fees' => $this->resource->studentTuition?->total_miscelaneous_fees,
                'overall_tuition' => $this->resource->studentTuition?->overall_tuition,
                'downpayment' => $this->resource->studentTuition?->downpayment,
                'total_balance' => $this->resource->studentTuition?->total_balance,
            ]),

            // Additional fees
            'additional_fees' => $this->when($this->resource->relationLoaded('additionalFees'), fn (): array => [
                'items' => $this->resource->additionalFees->map(fn ($fee): array => [
                    'id' => $fee->id,
                    'fee_name' => $fee->fee_name,
                    'amount' => $fee->amount,
                    'description' => $fee->description,
                ]),
                'total' => $this->resource->additionalFees->sum('amount'),
            ]),

            // Enrollment transactions (current academic period)
            'transactions' => $this->when($this->resource->relationLoaded('enrollmentTransactions'), fn () => $this->resource->enrollmentTransactions->map(fn ($transaction): array => [
                'id' => $transaction->id,
                'transaction_number' => $transaction->transaction_number,
                'invoicenumber' => $transaction->invoicenumber,
                'description' => $transaction->description,
                'status' => $transaction->status,
                'transaction_date' => format_timestamp($transaction->transaction_date),
                'settlements' => $transaction->settlements,
                'total_amount' => $transaction->total_amount,
                'created_at' => format_timestamp($transaction->created_at),
                'updated_at' => format_timestamp($transaction->updated_at),
            ])),

            // Resources (assessment, certificate, etc.)
            'resources' => $this->when($this->resource->relationLoaded('resources'), fn () => $this->resource->resources->map(fn ($resource): array => [
                'id' => $resource->id,
                'type' => $resource->type,
                'file_name' => $resource->file_name,
                'file_path' => $resource->file_path,
                'file_size' => $resource->file_size,
                'mime_type' => $resource->mime_type,
                'disk' => $resource->disk,
                'created_at' => format_timestamp($resource->created_at),
            ])),

            // Computed URLs
            'assessment_url' => $this->resource->assessment_url ?? null,
            'certificate_url' => $this->resource->certificate_url ?? null,

            // Summary statistics
            'statistics' => [
                'total_units' => $this->when($this->resource->relationLoaded('subjectsEnrolled'), fn () => $this->resource->subjectsEnrolled->sum(fn ($subject): float|int|array => ($subject->enrolled_lecture_units ?? 0) + ($subject->enrolled_laboratory_units ?? 0))),
                'total_lecture_units' => $this->when($this->resource->relationLoaded('subjectsEnrolled'), fn () => $this->resource->subjectsEnrolled->sum('enrolled_lecture_units')),
                'total_laboratory_units' => $this->when($this->resource->relationLoaded('subjectsEnrolled'), fn () => $this->resource->subjectsEnrolled->sum('enrolled_laboratory_units')),
                'subjects_count' => $this->when($this->resource->relationLoaded('subjectsEnrolled'), $this->resource->subjectsEnrolled->count(...)),
                'total_paid' => $this->when($this->resource->relationLoaded('enrollmentTransactions'), fn () => $this->resource->enrollmentTransactions
                    ->where('status', 'completed')
                    ->sum('total_amount')),
            ],
        ];
    }
}
