<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Faculty;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        $faculty = Faculty::query()->where('email', $user->email)->first();
        $student = Student::query()
            ->where('user_id', $user->id)
            ->with(['studentContactsInfo', 'studentEducationInfo', 'studentParentInfo', 'Course'])
            ->first();

        return response()->json([
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role?->value ?? 'user',
                    'avatar_url' => $user->avatar_url,
                    'phone' => $user->phone,
                    'address' => $user->address,
                    'city' => $user->city,
                    'state' => $user->state,
                    'country' => $user->country,
                    'postal_code' => $user->postal_code,
                    'bio' => $user->bio,
                    'website' => $user->website,
                    'department' => $user->department_profile ?? $user->department,
                    'position' => $user->position,
                ],
                'faculty' => $faculty ? [
                    'id' => $faculty->id,
                    'faculty_id_number' => $faculty->faculty_id_number,
                    'first_name' => $faculty->first_name,
                    'last_name' => $faculty->last_name,
                    'middle_name' => $faculty->middle_name,
                    'email' => $faculty->email,
                    'phone_number' => $faculty->phone_number,
                    'department' => $faculty->department,
                    'office_hours' => $faculty->office_hours,
                    'birth_date' => $faculty->birth_date?->format('Y-m-d'),
                    'address_line1' => $faculty->address_line1,
                    'biography' => $faculty->biography,
                    'education' => $faculty->education,
                    'courses_taught' => $faculty->courses_taught,
                    'photo_url' => $faculty->photo_url,
                    'gender' => $faculty->gender,
                    'age' => $faculty->age,
                ] : null,
                'student' => $student ? [
                    'id' => $student->id,
                    'student_id' => $student->student_id,
                    'first_name' => $student->first_name,
                    'last_name' => $student->last_name,
                    'middle_name' => $student->middle_name,
                    'email' => $student->email,
                    'phone' => $student->phone,
                    'address' => $student->address,
                    'civil_status' => $student->civil_status,
                    'nationality' => $student->nationality,
                    'religion' => $student->religion,
                    'emergency_contact' => $student->emergency_contact,
                    'birth_date' => $student->birth_date?->format('Y-m-d'),
                    'gender' => $student->gender,
                    'academic_year' => $student->academic_year,
                    'formatted_academic_year' => $student->formatted_academic_year,
                    'course' => $student->Course ? [
                        'id' => $student->Course->id,
                        'code' => $student->Course->code,
                        'title' => $student->Course->title,
                    ] : null,
                    'contacts' => [
                        'emergency_contact_name' => $student->studentContactsInfo?->emergency_contact_name,
                        'emergency_contact_phone' => $student->studentContactsInfo?->emergency_contact_phone,
                        'emergency_contact_relationship' => $student->studentContactsInfo?->emergency_contact_relationship,
                        'facebook' => $student->studentContactsInfo?->facebook,
                        'personal_contact' => $student->studentContactsInfo?->personal_contact,
                    ],
                    'education' => [
                        'elementary_school' => $student->studentEducationInfo?->elementary_school,
                        'elementary_year_graduated' => $student->studentEducationInfo?->elementary_year_graduated,
                        'high_school' => $student->studentEducationInfo?->high_school,
                        'high_school_year_graduated' => $student->studentEducationInfo?->high_school_year_graduated,
                        'senior_high_school' => $student->studentEducationInfo?->senior_high_school,
                        'senior_high_year_graduated' => $student->studentEducationInfo?->senior_high_year_graduated,
                    ],
                    'parents' => [
                        'father_name' => $student->studentParentInfo?->father_name,
                        'mother_name' => $student->studentParentInfo?->mother_name,
                    ],
                ] : null,
            ],
        ]);
    }
}
