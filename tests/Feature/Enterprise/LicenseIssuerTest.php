<?php

declare(strict_types=1);

use App\Enterprise\EntitlementService;
use App\Models\School;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\Support\EnterpriseLicense;

it('issues an importable license without exposing the signing key or overwriting output', function (): void {
    EnterpriseLicense::configure();
    $school = School::factory()->create();
    $directory = storage_path('framework/testing/enterprise-issuer-'.Str::uuid());
    File::ensureDirectoryExists($directory);
    $secretKey = base64_encode(sodium_crypto_sign_secretkey(sodium_crypto_sign_seed_keypair(str_repeat("\x01", SODIUM_CRYPTO_SIGN_SEEDBYTES))));

    try {
        File::put($directory.'/claims.json', json_encode(EnterpriseLicense::claims($school), JSON_THROW_ON_ERROR));
        File::put($directory.'/vendor.key', $secretKey);
        $arguments = [PHP_BINARY, base_path('scripts/issue-enterprise-license.php'),
            '--claims='.$directory.'/claims.json', '--private-key='.$directory.'/vendor.key', '--output='.$directory.'/school.license'];
        $process = new Process($arguments, base_path());
        $process->mustRun();
        $document = File::get($directory.'/school.license');
        app(EntitlementService::class)->import($school, $document);

        expect($process->getOutput().$process->getErrorOutput())->not->toContain($secretKey);
        expect(app(EntitlementService::class)->grants($school, EnterpriseLicense::CAPABILITY))->toBeTrue();

        $repeat = new Process($arguments, base_path());
        $repeat->run();
        expect($repeat->getExitCode())->toBe(1);
        expect(File::get($directory.'/school.license'))->toBe($document);
    } finally {
        File::deleteDirectory($directory);
    }
});
