<?php

declare(strict_types=1);

use App\Enterprise\EntitlementService;
use App\Enterprise\InvalidLicense;
use App\Models\School;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\EnterpriseLicense;

it('keeps installation and school identities stable across service instances', function (): void {
    $school = School::factory()->create();
    $service = app(EntitlementService::class);
    $installation = $service->installationId();
    $reference = $service->schoolReference($school);

    app()->forgetInstance(EntitlementService::class);

    expect(app(EntitlementService::class)->installationId())->toBe($installation);
    expect(app(EntitlementService::class)->schoolReference($school))->toBe($reference);
});

it('rejects a license for another installation or school', function (string $field): void {
    EnterpriseLicense::configure();
    $school = School::factory()->create();
    $document = EnterpriseLicense::sign(EnterpriseLicense::claims($school, [$field => (string) Str::uuid()]));

    expect(fn () => app(EntitlementService::class)->import($school, $document))->toThrow(InvalidLicense::class, 'different installation or school');
    expect(app(EntitlementService::class)->grants($school, EnterpriseLicense::CAPABILITY))->toBeFalse();
})->with(['installation_id', 'school_reference']);

it('retains purchased capabilities when maintenance ends', function (): void {
    $this->travelTo(now()->setDate(2026, 10, 25)->startOfDay());
    EnterpriseLicense::configure();
    $school = School::factory()->create();
    $document = EnterpriseLicense::sign(EnterpriseLicense::claims($school, [
        'issued_at' => '2026-01-01T00:00:00+00:00', 'maintenance_until' => '2026-09-01T00:00:00+00:00',
    ]));
    app(EntitlementService::class)->import($school, $document);

    expect(app(EntitlementService::class)->grants($school, EnterpriseLicense::CAPABILITY))->toBeTrue();
});

it('rejects licenses issued in the future', function (): void {
    EnterpriseLicense::configure();
    $school = School::factory()->create();
    $document = EnterpriseLicense::sign(EnterpriseLicense::claims($school, ['issued_at' => now()->addDay()->format(DATE_ATOM)]));

    expect(fn () => app(EntitlementService::class)->import($school, $document))->toThrow(InvalidLicense::class, 'not valid yet');
});

it('accepts a newer license revision and prevents a downgrade', function (): void {
    EnterpriseLicense::configure();
    $school = School::factory()->create();
    $claims = EnterpriseLicense::claims($school);
    $original = EnterpriseLicense::sign($claims);
    $renewal = EnterpriseLicense::sign([...$claims, 'revision' => 2, 'grants' => ['enterprise.new-capability']]);
    $service = app(EntitlementService::class);
    $service->import($school, $original);
    $service->import($school, $renewal);

    expect($service->grants($school, 'enterprise.new-capability'))->toBeTrue();
    expect(fn () => $service->import($school, $original))->toThrow(InvalidLicense::class, 'newer revision');
    expect($service->grants($school, EnterpriseLicense::CAPABILITY))->toBeFalse();
});

it('revalidates stored signatures and permits repair with a valid license', function (): void {
    EnterpriseLicense::configure();
    $school = School::factory()->create();
    $document = EnterpriseLicense::sign(EnterpriseLicense::claims($school));
    $service = app(EntitlementService::class);
    $service->import($school, $document);
    DB::table('school_entitlements')->where('school_id', $school->id)->update(['signed_license' => '{"grants":["enterprise.consolidated-reports"]}']);

    expect($service->grants($school, EnterpriseLicense::CAPABILITY))->toBeFalse();
    $service->import($school, $document);
    expect($service->grants($school, EnterpriseLicense::CAPABILITY))->toBeTrue();
});
