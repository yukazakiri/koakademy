<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Concerns\AuthorizesMcpRequests;
use BackedEnum;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Get the detailed profile of a student including student number, LRN, degree program, academic year level, contact info, clearance status, and enrollment standing.')]
#[IsReadOnly]
final class GetStudentProfileTool extends Tool
{
    use AuthorizesMcpRequests;

    public function handle(Request $request): ResponseFactory
    {
        $user = $this->requireRead($request);
        $studentId = $request->get('student_id') !== null ? (int) $request->get('student_id') : null;
        $student = $this->resolveStudentForCaller($user, $studentId);

        if (! $user->isStudentRole()) {
            $this->requirePermission($user, 'View:Student', 'You are not permitted to view student profiles.');
        }

        $student->loadMissing([
            'Course:id,code,title',
            'studentContactsInfo',
            'studentEducationInfo',
            'personalInfo',
        ]);

        return Response::structured([
            'id' => $student->id,
            'student_number' => (string) $student->student_id,
            'lrn' => $student->lrn,
            'name' => $student->full_name,
            'first_name' => $student->first_name,
            'middle_name' => $student->middle_name,
            'last_name' => $student->last_name,
            'suffix' => $student->suffix,
            'email' => $student->email,
            'phone' => $student->phone,
            'birth_date' => $student->birth_date?->format('Y-m-d'),
            'age' => $student->age,
            'gender' => $student->gender,
            'civil_status' => $student->civil_status,
            'nationality' => $student->nationality,
            'religion' => $student->religion,
            'address' => $student->address,
            'emergency_contact' => $student->emergency_contact,
            'status' => $student->status instanceof BackedEnum ? $student->status->value : $student->status,
            'clearance_status' => $student->clearance_status,
            'academic_year' => $student->academic_year,
            'student_type' => $student->student_type,
            'course' => $student->Course === null ? null : [
                'id' => $student->Course->id,
                'code' => $student->Course->code,
                'title' => $student->Course->title,
            ],
            'scholarship' => [
                'type' => $student->scholarship_type instanceof BackedEnum ? $student->scholarship_type->value : $student->scholarship_type,
                'details' => $student->scholarship_details,
            ],
            'education' => $student->studentEducationInfo === null ? null : [
                'elementary' => $student->studentEducationInfo->elementary_school,
                'junior_high' => $student->studentEducationInfo->junior_high_school,
                'senior_high' => $student->studentEducationInfo->senior_high_school,
                'college' => $student->studentEducationInfo->college,
            ],
        ]);
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'student_id' => $schema->integer()->min(1)->description('The student database ID or student number. Optional for student callers (defaults to their own profile).'),
        ];
    }
}
