<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Filament\Resources\Classes\Api\Handlers\IndexHandler as ClassesIndexHandler;
use App\Filament\Resources\Faculties\Api\Requests\CreateFacultyRequest;
use App\Filament\Resources\Students\Api\Handlers\DeleteHandler as StudentDeleteHandler;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\Request;

it('denies students in CreateFacultyRequest::authorize()', function (): void {
    $student = User::factory()->create(['role' => UserRole::Student]);
    $faculty = User::factory()->create(['role' => UserRole::Instructor]);
    $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin]);

    $makeRequest = static function (User $user): CreateFacultyRequest {
        $req = CreateFacultyRequest::create('/api/faculties', 'POST');
        $req->setUserResolver(static fn (): User => $user);

        return $req;
    };

    expect($makeRequest($student)->authorize())->toBeFalse()
        ->and($makeRequest($faculty)->authorize())->toBeFalse()
        ->and($makeRequest($superAdmin)->authorize())->toBeTrue();
});

it('uses canonical ViewAny:Classes permission on Classes API IndexHandler', function (): void {
    $ref = new ReflectionProperty(ClassesIndexHandler::class, 'permission');

    expect($ref->getDefaultValue())->toBe('ViewAny:Classes');
});

it('forbids a student from deleting their own Student record via Students API DeleteHandler', function (): void {
    $studentUser = User::factory()->create(['role' => UserRole::Student]);
    $student = Student::factory()->create(['user_id' => $studentUser->id, 'email' => $studentUser->email]);

    $request = Request::create('/api/students/'.$student->id, 'DELETE');
    $request->setRouteResolver(static fn () => new class($student->id)
    {
        public function __construct(private readonly int $id) {}

        public function parameter(string $name, mixed $default = null): mixed
        {
            return $name === 'id' ? $this->id : $default;
        }
    });
    $request->setUserResolver(static fn (): User => $studentUser);

    $response = (new StudentDeleteHandler)->handler($request);

    expect($response->getStatusCode())->toBe(403)
        ->and(Student::query()->whereKey($student->id)->exists())->toBeTrue();
});
