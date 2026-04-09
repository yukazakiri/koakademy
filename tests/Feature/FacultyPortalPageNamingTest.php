<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Classes;
use App\Models\Faculty;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    config(['inertia.testing.ensure_pages_exist' => false]);

    $this->user = User::factory()->create([
        'role' => UserRole::Instructor,
        'email' => 'faculty-pages@example.com',
        'faculty_id_number' => 'FAC-9001',
    ]);

    $this->faculty = Faculty::factory()->create([
        'email' => $this->user->email,
        'faculty_id_number' => 'FAC-9001',
    ]);
});

it('renders normalized faculty classes page names', function (string $routeName, string $component, bool $requiresClass): void {
    $parameters = [];

    if ($requiresClass) {
        $class = Classes::factory()->create([
            'faculty_id' => $this->faculty->id,
        ]);

        $parameters = ['class' => $class];
    }

    $this->actingAs($this->user)
        ->get(route($routeName, $parameters))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->component($component, false));
})->with([
    ['faculty.classes.index', 'faculty/classes/index', false],
    ['faculty.classes.show', 'faculty/classes/show', true],
]);

it('uses normalized dashboard and schedule page names in the faculty route file', function (): void {
    $routeFile = file_get_contents(base_path('routes/web/faculty-portal.php'));

    expect($routeFile)
        ->toContain("Inertia::render('faculty/dashboard'")
        ->toContain("Inertia::render('faculty/schedule/index'");
});
