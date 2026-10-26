<?php

declare(strict_types=1);

namespace App\Enterprise;

use App\Models\School;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class EntitlementService
{
    public function __construct(private LicenseVerifier $verifier) {}

    public function installationId(): string
    {
        $id = DB::table('enterprise_installations')->where('id', 1)->value('uuid');
        if (is_string($id)) {
            return $id;
        }

        DB::table('enterprise_installations')->insertOrIgnore([
            'id' => 1, 'uuid' => (string) Str::uuid(), 'created_at' => now(),
        ]);

        $id = DB::table('enterprise_installations')->where('id', 1)->value('uuid');
        if (! is_string($id)) {
            throw new InvalidLicense('The installation identity could not be loaded.');
        }

        return $id;
    }

    public function schoolReference(School $school): string
    {
        $reference = DB::table('school_entitlements')->where('school_id', $school->id)->value('school_reference');
        if (is_string($reference)) {
            return $reference;
        }

        DB::table('school_entitlements')->insertOrIgnore([
            'school_id' => $school->id, 'school_reference' => (string) Str::uuid(),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $reference = DB::table('school_entitlements')->where('school_id', $school->id)->value('school_reference');
        if (! is_string($reference)) {
            throw new InvalidLicense('The school license identity could not be loaded.');
        }

        return $reference;
    }

    public function license(School $school): ?LicenseClaims
    {
        $row = DB::table('school_entitlements')->where('school_id', $school->id)->first();
        if ($row === null || ! is_string($row->signed_license) || ! is_string($row->school_reference)) {
            return null;
        }

        // Verify the stored document on every use. Editable database columns
        // and feature assignments are never authoritative commercial grants.
        try {
            $claims = $this->verifier->verify($row->signed_license);
            $this->assertBound($claims, $this->installationId(), $row->school_reference);

            return $claims;
        } catch (InvalidLicense) {
            return null;
        }
    }

    public function grants(School $school, string $capability): bool
    {
        return in_array($capability, $this->license($school)->grants ?? [], true);
    }

    public function import(School $school, string $document, ?int $actorId = null): LicenseClaims
    {
        $claims = $this->verifier->verify($document);
        $this->assertBound($claims, $this->installationId(), $this->schoolReference($school));

        DB::transaction(function () use ($school, $document, $claims, $actorId): void {
            $row = DB::table('school_entitlements')->where('school_id', $school->id)->lockForUpdate()->first();
            if ($row === null) {
                throw new InvalidLicense('The school license binding is no longer available. Reload the school before importing.');
            }

            if (is_string($row->signed_license)) {
                try {
                    $previous = $this->verifier->verify($row->signed_license);
                } catch (InvalidLicense) {
                    // A valid replacement also repairs a corrupted document
                    // or a license signed by a retired verification key.
                    $previous = null;
                }
                if ($previous !== null && $row->signed_license !== $document && ($claims->licenseId !== $previous->licenseId
                    || $claims->revision <= $previous->revision || $claims->issuedAt < $previous->issuedAt)) {
                    throw new InvalidLicense('A replacement license must retain its ID and have a newer revision.');
                }
            }

            DB::table('school_entitlements')->where('school_id', $school->id)->update([
                'signed_license' => $document, 'imported_by' => $actorId,
                'imported_at' => now(), 'updated_at' => now(),
            ]);
        });

        return $claims;
    }

    private function assertBound(LicenseClaims $claims, string $installationId, string $schoolReference): void
    {
        if ($claims->installationId !== $installationId || $claims->schoolReference !== $schoolReference) {
            throw new InvalidLicense('This license belongs to a different installation or school.');
        }

        if ($claims->issuedAt > now()) {
            throw new InvalidLicense('This license is not valid yet.');
        }
    }
}
