<?php

declare(strict_types=1);

use App\Models\Booking;
use App\Models\Course;
use App\Models\EventReminder;
use App\Models\Faculty;
use App\Models\ResourceBooking;
use App\Models\School;
use App\Models\Student;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Symfony\Component\Finder\Finder;

/**
 * SEC-004 · `Model::unguard()` disabled mass-assignment protection application-wide.
 *
 * While the guard was off, `isFillable()` answered `true` for every attribute
 * and `fillableFromArray()` handed back the whole payload, so every `$fillable`
 * array in the codebase was decoration and any handler that passed request data
 * to `fill()` could write any column of the model.
 */

/**
 * Every model class the application ships, discovered from disk so that a new
 * model is covered the moment it lands.
 *
 * @return list<class-string<Model>>
 */
function sec004ApplicationModelClasses(): array
{
    $roots = [base_path('app/Models') => 'App\\Models\\'];

    $moduleRoot = base_path('Modules');

    if (is_dir($moduleRoot)) {
        foreach (scandir($moduleRoot) as $module) {
            if ($module === '.' || $module === '..' || ! is_dir($moduleRoot.'/'.$module)) {
                continue;
            }

            $roots[$moduleRoot.'/'.$module.'/app/Models'] = 'Modules\\'.$module.'\\Models\\';
        }
    }

    $classes = [];

    foreach ($roots as $path => $prefix) {
        if (! is_dir($path)) {
            continue;
        }

        $finder = (new Finder)->files()->in($path)->name('*.php');

        foreach ($finder as $file) {
            $relative = Str::after($file->getPathname(), $path.'/');
            $class = $prefix.str_replace('/', '\\', Str::replaceLast('.php', '', $relative));

            if (! class_exists($class)) {
                continue;
            }

            $model = sec004ModelClass($class);

            if ($model === null) {
                continue;
            }

            $classes[] = $model;
        }
    }

    sort($classes);

    return $classes;
}

/**
 * Narrow a class name to an Eloquent model, skipping traits, relations and
 * anything that is not a model at all.
 *
 * @param  class-string  $class
 * @return class-string<Model>|null
 */
function sec004ModelClass(string $class): ?string
{
    if (! is_subclass_of($class, Model::class)) {
        return null;
    }

    return $class;
}

it('keeps Eloquent mass-assignment protection switched on', function (): void {
    expect(Model::isUnguarded())->toBeFalse()
        ->and(Model::preventsSilentlyDiscardingAttributes())->toBeTrue();
});

it('refuses to mass-assign an attribute that is not on the allowlist', function (): void {
    $course = Course::factory()->create(['description' => 'Original description']);

    expect(fn () => $course->fill([
        'description' => 'Updated description',
        'not_a_column' => 'injected',
    ]))->toThrow(MassAssignmentException::class);
});

it('never writes a column that is outside the model allowlist', function (): void {
    $school = School::factory()->create();

    $student = Student::factory()->create([
        'school_id' => $school->id,
        'document_location_id' => null,
    ]);

    $originalFirstName = $student->first_name;

    // `deleted_at` belongs to neither the allowlist nor the model's casts.
    expect(fn () => $student->fill([
        'first_name' => 'Renamed',
        'deleted_at' => now(),
    ]))->toThrow(MassAssignmentException::class);

    $student->refresh();

    expect($student->first_name)->toBe($originalFirstName)
        ->and($student->deleted_at)->toBeNull();
});

it('keeps the allowlist of every application model explicit', function (): void {
    $withoutAllowlist = [];

    foreach (sec004ApplicationModelClasses() as $class) {
        if ((new $class)->getFillable() === []) {
            $withoutAllowlist[] = $class;
        }
    }

    expect($withoutAllowlist)->toBe([]);
});

it('gives the models that had no allowlist one of their own columns', function (Model $model): void {
    $fillable = $model->getFillable();

    expect($fillable)->not->toBeEmpty();

    $column = (string) array_key_first($fillable);

    expect(fn () => $model->fill([$column => 'value', 'not_a_column' => 'injected']))
        ->toThrow(MassAssignmentException::class);

    expect($model->getAttributes())->not->toHaveKey('not_a_column');
})->with([
    'booking' => fn (): Booking => new Booking,
    'event reminder' => fn (): EventReminder => new EventReminder,
    'resource booking' => fn (): ResourceBooking => new ResourceBooking,
]);

it('does not let a request payload reach a column outside the allowlist', function (): void {
    $school = School::factory()->create();

    $faculty = Faculty::factory()->create([
        'school_id' => $school->id,
        'first_name' => 'Original',
        'last_name' => 'Professor',
    ]);

    // The allowlist is the only thing that decides what a payload may write.
    expect(fn () => $faculty->fill([
        'first_name' => 'Renamed',
        'not_a_column' => 'injected',
    ]))->toThrow(MassAssignmentException::class);

    $faculty->refresh();

    expect($faculty->first_name)->toBe('Original')
        ->and($faculty->getAttributes())->not->toHaveKey('not_a_column');
});
