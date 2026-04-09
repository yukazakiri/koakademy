<?php

declare(strict_types=1);

use App\Enums\StudentType;
use App\Mail\SignupOtpMail;
use App\Models\Course;
use App\Models\Faculty;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;

// uses(\Illuminate\Foundation\Testing\RefreshDatabase::class); // Managed globally

beforeEach(function () {
    $this->course = Course::factory()->create();
});

it('can lookup existing student email', function () {
    $student = Student::factory()->create([
        'email' => 'student@test.com',
        'student_type' => StudentType::College,
        'course_id' => $this->course->id,
        'document_location_id' => null,
        'status' => 'enrolled',
    ]);

    $response = $this->postJson(route('signup.email-lookup'), [
        'email' => 'student@test.com',
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'found' => true,
            'type' => 'student',
            'record_id' => $student->id,
        ])
        ->assertJsonMissing(['student_id', 'lrn']);
});

it('can lookup existing faculty email', function () {
    $faculty = Faculty::factory()->create([
        'email' => 'faculty@test.com',
    ]);

    $response = $this->postJson(route('signup.email-lookup'), [
        'email' => 'faculty@test.com',
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'found' => true,
            'type' => 'faculty',
            'record_id' => $faculty->id,
        ]);
});

it('returns error for unknown email', function () {
    $response = $this->postJson(route('signup.email-lookup'), [
        'email' => 'unknown@test.com',
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'found' => false,
            'message' => 'Email not found in our records. Please use your registered school email.',
        ]);
});

it('returns error for existing user account', function () {
    User::factory()->create([
        'email' => 'existing@test.com',
    ]);

    $response = $this->postJson(route('signup.email-lookup'), [
        'email' => 'existing@test.com',
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'found' => false,
            'account_exists' => true,
        ]);
});

it('can send otp with valid student credentials', function () {
    Mail::fake();

    $student = Student::factory()->create([
        'email' => 'student@test.com',
        'student_id' => '123456',
        'student_type' => StudentType::College,
        'course_id' => $this->course->id,
        'document_location_id' => null,
        'status' => 'enrolled',
    ]);

    $response = $this->postJson(route('signup.send-otp'), [
        'email' => 'student@test.com',
        'user_type' => 'student',
        'student_type' => 'college',
        'student_id' => '123456',
        'record_id' => $student->id,
    ]);

    $response->assertStatus(200);

    Mail::assertSent(SignupOtpMail::class, function ($mail) use ($student) {
        return $mail->hasTo($student->email);
    });
});

it('fails to send otp with mismatched student id', function () {
    $student = Student::factory()->create([
        'email' => 'student@test.com',
        'student_id' => '123456',
        'student_type' => StudentType::College,
        'course_id' => $this->course->id,
        'document_location_id' => null,
        'status' => 'enrolled',
    ]);

    $response = $this->postJson(route('signup.send-otp'), [
        'email' => 'student@test.com',
        'user_type' => 'student',
        'student_type' => 'college',
        'student_id' => '999999', // Wrong ID
        'record_id' => $student->id,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['student_id']);
});

it('can signup with valid otp', function () {
    $student = Student::factory()->create([
        'email' => 'student@test.com',
        'student_id' => '123456',
        'student_type' => StudentType::College,
        'course_id' => $this->course->id,
        'document_location_id' => null,
        'status' => 'enrolled',
    ]);

    $otp = '123456';
    Cache::put('signup_otp_student@test.com', $otp, 600);

    Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);

    $response = $this->post(route('signup'), [
        'name' => 'Test Student',
        'email' => 'student@test.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'user_type' => 'student',
        'student_type' => 'college',
        'student_id' => '123456',
        'record_id' => $student->id,
        'otp' => $otp,
    ]);

    $response->assertRedirect('/student/dashboard');
    $this->assertAuthenticated();

    $this->assertDatabaseHas('users', [
        'email' => 'student@test.com',
    ]);

    $user = User::where('email', 'student@test.com')->first();
    expect($user->email_verified_at)->not->toBeNull();
});

it('fails signup with invalid otp', function () {
    $student = Student::factory()->create([
        'email' => 'student@test.com',
        'student_id' => '123456',
        'student_type' => StudentType::College,
        'course_id' => $this->course->id,
        'document_location_id' => null,
        'status' => 'enrolled',
    ]);

    $otp = '123456';
    Cache::put('signup_otp_student@test.com', $otp, 600);

    $response = $this->post(route('signup'), [
        'name' => 'Test Student',
        'email' => 'student@test.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'user_type' => 'student',
        'student_type' => 'college',
        'student_id' => '123456',
        'record_id' => $student->id,
        'otp' => 'WRONG',
    ]);

    $response->assertSessionHasErrors(['otp']);
    $this->assertGuest();
});
