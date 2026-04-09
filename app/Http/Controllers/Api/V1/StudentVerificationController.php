<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\VerifyStudentRequest;
use App\Models\Student;
use Illuminate\Http\JsonResponse;

final class StudentVerificationController extends Controller
{
    /**
     * Verify if a student exists in the system by student ID and email.
     */
    public function verify(VerifyStudentRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $studentId = $validated['student_id'];
        $email = $validated['email'];

        $student = Student::query()
            ->where('student_id', $studentId)
            ->where('email', $email)
            ->first();

        if (! $student) {
            return response()->json([
                'message' => 'Student not found',
                'data' => [
                    'exists' => false,
                    'student_id' => $studentId,
                    'email' => $email,
                ],
            ], 404);
        }

        return response()->json([
            'message' => 'Student verified successfully',
            'data' => [
                'exists' => true,
                'student_id' => $student->student_id,
                'email' => $student->email,
                'full_name' => $student->full_name,
                'status' => $student->status,
                'student_type' => $student->student_type,
            ],
        ]);
    }
}
