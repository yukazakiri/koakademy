<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Features\Toggles\McpServerAccess;
use App\Mcp\OAuth\CoordinatedRefreshTokenGrant;
use App\Models\GeneralSetting;
use App\Models\Room;
use App\Models\School;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\User;
use App\Services\AdministratorUserAccessService;
use App\Services\GeneralSettingsService;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Laravel\Passport\Bridge\ClientRepository;
use Laravel\Passport\Bridge\RefreshTokenRepository;
use Laravel\Passport\Events\AccessTokenRevoked;
use Laravel\Passport\Passport;
use Laravel\Pennant\Feature;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\ResponseTypes\BearerTokenResponse;
use Spatie\Permission\Models\Permission;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;

function authorizeMcpClientForTest($test, User $user, bool $allowWrite, string $scope = 'mcp:use'): array
{
    $registration = $test->postJson('/oauth/register', [
        'client_name' => 'OAuth Test Client',
        'redirect_uris' => ['http://127.0.0.1:52817/callback'],
    ])->assertCreated();
    $clientId = $registration->json('client_id');
    $verifier = str_repeat('b', 64);
    $query = http_build_query([
        'client_id' => $clientId,
        'redirect_uri' => 'http://127.0.0.1:52817/callback',
        'response_type' => 'code',
        'scope' => $scope,
        'code_challenge' => mb_rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='),
        'code_challenge_method' => 'S256',
    ]);

    $test->actingAs($user)->get('/oauth/authorize?'.$query)->assertOk();
    $data = ['client_id' => $clientId, 'auth_token' => session('authToken')];
    if ($allowWrite) {
        $data['allow_write'] = '1';
    }

    $authorization = $test->post('/oauth/authorize', $data)->assertRedirect();
    parse_str((string) parse_url($authorization->headers->get('Location'), PHP_URL_QUERY), $callback);
    $tokens = $test->post('/oauth/token', [
        'grant_type' => 'authorization_code',
        'client_id' => $clientId,
        'redirect_uri' => 'http://127.0.0.1:52817/callback',
        'code_verifier' => $verifier,
        'code' => $callback['code'],
    ])->assertOk()->json();

    Auth::forgetGuards();
    Auth::shouldUse('web');

    return ['client_id' => $clientId, ...$tokens];
}

beforeEach(function (): void {
    config(['activitylog.enabled' => false, 'api.mcp.enabled' => true]);
    $this->withoutVite();

    $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    openssl_pkey_export($key, $privateKey);
    config([
        'passport.private_key' => $privateKey,
        'passport.public_key' => openssl_pkey_get_details($key)['key'],
    ]);
});

it('advertises oauth registration when an mcp request is unauthenticated', function (): void {
    $response = $this->postJson('/mcp/koakademy', [
        'jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list',
    ]);

    $response->assertUnauthorized();
    expect($response->headers->get('WWW-Authenticate'))
        ->toContain('/.well-known/oauth-protected-resource/mcp/koakademy');

    $this->getJson('/.well-known/oauth-protected-resource/mcp/koakademy')
        ->assertOk()
        ->assertJsonPath('resource', 'http://localhost/mcp/koakademy')
        ->assertJsonPath('authorization_servers.0', 'http://localhost');

    $this->getJson('/.well-known/oauth-authorization-server')
        ->assertOk()
        ->assertJsonPath('registration_endpoint', 'http://localhost/oauth/register')
        ->assertJsonPath('authorization_endpoint', 'http://localhost/oauth/authorize')
        ->assertJsonPath('token_endpoint', 'http://localhost/oauth/token');
});

it('rejects unsafe client redirect uris', function (string $redirectUri): void {
    $this->postJson('/oauth/register', [
        'client_name' => 'Unsafe client',
        'redirect_uris' => [$redirectUri],
    ])->assertBadRequest()->assertJsonPath('error', 'invalid_redirect_uri');

    expect(DB::table('oauth_clients')->count())->toBe(0);
})->with([
    'non-loopback http' => 'http://attacker.example/callback',
    'redirect with fragment' => 'https://client.example/callback#fragment',
    'unapproved custom scheme' => 'malicious://callback',
]);

it('registers public clients and limits registration attempts', function (): void {
    foreach (range(1, 10) as $attempt) {
        $this->postJson('/oauth/register', [
            'client_name' => 'MCP Client '.$attempt,
            'redirect_uris' => ['http://127.0.0.1:52817/callback'],
        ])->assertCreated()->assertJsonPath('token_endpoint_auth_method', 'none');
    }

    $this->postJson('/oauth/register', [
        'redirect_uris' => ['http://127.0.0.1:52817/callback'],
    ])->assertTooManyRequests();

    expect(DB::table('oauth_clients')->count())->toBe(10);
});

it('requires pkce for public client authorization', function (): void {
    $clientId = $this->postJson('/oauth/register', [
        'client_name' => 'PKCE client',
        'redirect_uris' => ['http://127.0.0.1:52817/callback'],
    ])->assertCreated()->json('client_id');

    $query = http_build_query([
        'client_id' => $clientId,
        'redirect_uri' => 'http://127.0.0.1:52817/callback',
        'response_type' => 'code',
    ]);

    $this->get('/oauth/authorize?'.$query)->assertBadRequest()->assertJsonPath('hint', 'Code challenge must be provided for public clients');
});

it('revokes only the target users credentials on the configured passport connection', function (): void {
    config(['database.connections.passport_test' => array_replace(config('database.connections.sqlite'), ['database' => ':memory:'])]);
    $connection = DB::connection('passport_test');
    foreach (['oauth_auth_codes', 'oauth_access_tokens', 'oauth_refresh_tokens', 'oauth_device_codes'] as $table) {
        $create = DB::connection('sqlite')->selectOne('SELECT sql FROM sqlite_master WHERE type = ? AND name = ?', ['table', $table]);
        $connection->statement($create->sql);
    }
    config(['passport.connection' => 'passport_test']);

    $user = User::factory()->create();
    $other = User::factory()->create();
    foreach ([$user, $other] as $owner) {
        $id = (string) $owner->id;
        $connection->table('oauth_access_tokens')->insert(['id' => 'access-'.$id, 'user_id' => $owner->id, 'client_id' => 'client', 'revoked' => false]);
        $connection->table('oauth_refresh_tokens')->insert(['id' => 'refresh-'.$id, 'access_token_id' => 'access-'.$id, 'revoked' => false]);
        $connection->table('oauth_auth_codes')->insert(['id' => 'code-'.$id, 'user_id' => $owner->id, 'client_id' => 'client', 'revoked' => false]);
        $connection->table('oauth_device_codes')->insert(['id' => 'device-'.$id, 'user_id' => $owner->id, 'client_id' => 'client', 'user_code' => mb_str_pad($id, 8, '0', STR_PAD_LEFT), 'scopes' => '[]', 'revoked' => false]);
    }

    app(AdministratorUserAccessService::class)->revokeAll($user);

    foreach (['oauth_access_tokens' => 'access-', 'oauth_refresh_tokens' => 'refresh-', 'oauth_auth_codes' => 'code-', 'oauth_device_codes' => 'device-'] as $table => $prefix) {
        expect((bool) $connection->table($table)->where('id', $prefix.$user->id)->value('revoked'))->toBeTrue()
            ->and((bool) $connection->table($table)->where('id', $prefix.$other->id)->value('revoked'))->toBeFalse();
    }
});

it('keeps refresh token validation and replacement in the revocation transaction', function (): void {
    $school = School::factory()->create();
    $user = User::factory()->create(['role' => UserRole::Instructor, 'school_id' => $school->id]);
    $tokens = authorizeMcpClientForTest($this, $user, false);
    $levels = [];
    Event::listen(AccessTokenRevoked::class, function () use (&$levels): void {
        $levels[] = Passport::refreshToken()->getConnection()->transactionLevel();
    });

    $this->post('/oauth/token', [
        'grant_type' => 'refresh_token',
        'client_id' => $tokens['client_id'],
        'refresh_token' => $tokens['refresh_token'],
    ])->assertOk();

    // RefreshDatabase already has one test transaction; issuance must add its own.
    expect($levels)->not->toBeEmpty()->each->toBeGreaterThan(1);
});

it('rejects a refresh revoked after its initial validation but before it is locked', function (): void {
    $school = School::factory()->create();
    $user = User::factory()->create(['role' => UserRole::Instructor, 'school_id' => $school->id]);
    $tokens = authorizeMcpClientForTest($this, $user, false);
    $repository = new class(app('events'), $user) extends RefreshTokenRepository
    {
        public bool $revokedDuringValidation = false;

        public function __construct($events, private User $user)
        {
            parent::__construct($events);
        }

        public function isRefreshTokenRevoked(string $tokenId): bool
        {
            $result = parent::isRefreshTokenRevoked($tokenId);
            if (! $this->revokedDuringValidation) {
                $this->revokedDuringValidation = true;
                app(AdministratorUserAccessService::class)->revokeAll($this->user);
            }

            return $result;
        }
    };
    $grant = new CoordinatedRefreshTokenGrant($repository);
    $grant->setEncryptionKey(Passport::tokenEncryptionKey(app(Encrypter::class)));
    $grant->setClientRepository(app(ClientRepository::class));
    $request = (new PsrHttpFactory)->createRequest(Illuminate\Http\Request::create('/oauth/token', 'POST', [
        'grant_type' => 'refresh_token',
        'client_id' => $tokens['client_id'],
        'refresh_token' => $tokens['refresh_token'],
    ]));

    expect(fn () => $grant->respondToAccessTokenRequest($request, new BearerTokenResponse, new DateInterval('PT1H')))
        ->toThrow(OAuthServerException::class);
    expect($repository->revokedDuringValidation)->toBeTrue();
    expect(Passport::token()->newQuery()->where('user_id', $user->id)->count())->toBe(1);
});

it('refuses authorization-code exchange after an account-wide revocation', function (): void {
    $user = User::factory()->create(['role' => UserRole::Instructor]);
    $clientId = $this->postJson('/oauth/register', [
        'client_name' => 'Revocation test',
        'redirect_uris' => ['http://127.0.0.1:52817/callback'],
    ])->assertCreated()->json('client_id');
    $verifier = str_repeat('v', 64);
    $query = http_build_query([
        'client_id' => $clientId,
        'redirect_uri' => 'http://127.0.0.1:52817/callback',
        'response_type' => 'code',
        'code_challenge' => mb_rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='),
        'code_challenge_method' => 'S256',
    ]);
    $this->actingAs($user)->get('/oauth/authorize?'.$query)->assertOk();
    $authorization = $this->post('/oauth/authorize', [
        'client_id' => $clientId,
        'auth_token' => session('authToken'),
    ])->assertRedirect();
    parse_str((string) parse_url($authorization->headers->get('Location'), PHP_URL_QUERY), $callback);

    app(AdministratorUserAccessService::class)->revokeAll($user);
    $this->post('/oauth/token', [
        'grant_type' => 'authorization_code',
        'client_id' => $clientId,
        'redirect_uri' => 'http://127.0.0.1:52817/callback',
        'code_verifier' => $verifier,
        'code' => $callback['code'],
    ])->assertBadRequest()->assertJsonPath('error', 'invalid_grant');
    expect(Passport::token()->newQuery()->where('user_id', $user->id)->count())->toBe(0);
});

it('rejects a stale consent submission after account-wide revocation', function (): void {
    $user = User::factory()->create(['role' => UserRole::Instructor]);
    $clientId = $this->postJson('/oauth/register', [
        'client_name' => 'Pending consent',
        'redirect_uris' => ['http://127.0.0.1:52817/callback'],
    ])->assertCreated()->json('client_id');
    $query = http_build_query([
        'client_id' => $clientId,
        'redirect_uri' => 'http://127.0.0.1:52817/callback',
        'response_type' => 'code',
        'code_challenge' => str_repeat('a', 43),
        'code_challenge_method' => 'S256',
    ]);
    $this->actingAs($user)->get('/oauth/authorize?'.$query)->assertOk();
    $authToken = session('authToken');

    app(AdministratorUserAccessService::class)->revokeAll($user);
    $this->post('/oauth/authorize', ['client_id' => $clientId, 'auth_token' => $authToken])
        ->assertForbidden();

    expect(Passport::authCode()->newQuery()->where('user_id', $user->id)->count())->toBe(0);
});

it('refuses access-token rotation when the prior access token is revoked', function (): void {
    $school = School::factory()->create();
    $user = User::factory()->create(['role' => UserRole::Instructor, 'school_id' => $school->id]);
    $tokens = authorizeMcpClientForTest($this, $user, false);
    Passport::token()->newQuery()->where('user_id', $user->id)->update(['revoked' => true]);

    $this->post('/oauth/token', [
        'grant_type' => 'refresh_token',
        'client_id' => $tokens['client_id'],
        'refresh_token' => $tokens['refresh_token'],
    ])->assertBadRequest()->assertJsonPath('error', 'invalid_grant');
});

it('grants write access only when approved and keeps it through token refresh', function (): void {
    $school = School::factory()->create();
    $user = User::factory()->create(['role' => UserRole::Admin, 'school_id' => $school->id]);
    $student = Student::factory()->create(['school_id' => $school->id]);
    $enrollment = StudentEnrollment::factory()->create([
        'school_id' => $school->id,
        'student_id' => $student->id,
        'remarks' => 'Original',
    ]);
    $tokens = authorizeMcpClientForTest($this, $user, true);
    $token = $tokens['access_token'];

    $this->withToken($token)->postJson('/mcp/koakademy', [
        'jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call',
        'params' => ['name' => 'get-my-context-tool', 'arguments' => []],
    ])->assertOk()
        ->assertJsonPath('result.structuredContent.mcp.abilities', ['mcp:read', 'mcp:write'])
        ->assertJsonPath('result.structuredContent.mcp.can_write', true);

    $mutation = $this->withToken($token)->postJson('/mcp/koakademy', [
        'jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/call',
        'params' => ['name' => 'update-enrollment-remarks-tool', 'arguments' => ['enrollment_id' => $enrollment->id, 'remarks' => 'Approved']],
    ]);
    $mutation->assertOk()->assertJsonPath('result.isError', false)->assertJsonPath('result.structuredContent.remarks', 'Approved');
    expect($enrollment->refresh()->remarks)->toBe('Approved');

    $this->withToken($token)->postJson('/mcp/koakademy', [
        'jsonrpc' => '2.0', 'id' => 3, 'method' => 'tools/call',
        'params' => ['name' => 'manage-room-tool', 'arguments' => ['action' => 'create', 'name' => 'OAuth Room']],
    ])->assertOk()->assertJsonPath('result.isError', false);
    expect(Room::query()->where('name', 'OAuth Room')->where('school_id', $school->id)->exists())->toBeTrue();

    $rotated = $this->post('/oauth/token', [
        'grant_type' => 'refresh_token',
        'client_id' => $tokens['client_id'],
        'refresh_token' => $tokens['refresh_token'],
    ])->assertOk()->json();
    Auth::forgetGuards();
    Auth::shouldUse('web');
    $this->withToken($rotated['access_token'])->postJson('/mcp/koakademy', [
        'jsonrpc' => '2.0', 'id' => 4, 'method' => 'tools/call',
        'params' => ['name' => 'get-my-context-tool', 'arguments' => []],
    ])->assertOk()->assertJsonPath('result.structuredContent.mcp.can_write', true);

    app(App\Services\UserSystemRoleService::class)->assign($user, UserRole::Student);
    Auth::forgetGuards();
    Auth::shouldUse('web');
    $this->withToken($rotated['access_token'])->postJson('/mcp/koakademy', [
        'jsonrpc' => '2.0', 'id' => 5, 'method' => 'tools/call',
        'params' => ['name' => 'get-my-context-tool', 'arguments' => []],
    ])->assertOk()->assertJsonPath('result.structuredContent.mcp.can_write', false);
});

it('does not grant writes merely because the client requested the write scope', function (): void {
    $school = School::factory()->create();
    $user = User::factory()->create(['role' => UserRole::AdministrativeAssistant, 'school_id' => $school->id]);
    $tokens = authorizeMcpClientForTest($this, $user, false, 'mcp:use mcp:write');

    $this->withToken($tokens['access_token'])->postJson('/mcp/koakademy', [
        'jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call',
        'params' => ['name' => 'get-my-context-tool', 'arguments' => []],
    ])->assertOk()->assertJsonPath('result.structuredContent.mcp.can_write', false);

    $this->post('/oauth/token', [
        'grant_type' => 'refresh_token',
        'client_id' => $tokens['client_id'],
        'refresh_token' => $tokens['refresh_token'],
        'scope' => 'mcp:use mcp:write',
    ])->assertBadRequest()->assertJsonPath('error', 'invalid_scope');
});

it('shows an unchecked write checkbox for eligible accounts and hides it when writes are disabled', function (): void {
    $school = School::factory()->create();
    $user = User::factory()->create(['role' => UserRole::Instructor, 'school_id' => $school->id]);
    $clientId = $this->postJson('/oauth/register', [
        'client_name' => 'Consent test',
        'redirect_uris' => ['http://127.0.0.1:52817/callback'],
    ])->assertCreated()->json('client_id');
    $query = http_build_query([
        'client_id' => $clientId,
        'redirect_uri' => 'http://127.0.0.1:52817/callback',
        'response_type' => 'code',
        'scope' => 'mcp:use mcp:write',
        'code_challenge' => str_repeat('a', 43),
        'code_challenge_method' => 'S256',
    ]);

    $response = $this->actingAs($user)->get('/oauth/authorize?'.$query)->assertOk();
    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $checkboxes = (new DOMXPath($document))->query('//input[@name="allow_write"]');
    expect($checkboxes->length)->toBe(1)
        ->and($checkboxes->item(0)->hasAttribute('checked'))->toBeFalse();

    app(GeneralSettingsService::class)->updateApiManagementConfig(['mcp_enabled' => true, 'mcp_write_enabled' => false]);
    $this->get('/oauth/authorize?'.$query)
        ->assertOk()
        ->assertSee('Data modifications are disabled in system settings.')
        ->assertDontSee('name="allow_write"', false);
    $authorization = $this->post('/oauth/authorize', [
        'client_id' => $clientId,
        'auth_token' => session('authToken'),
        'allow_write' => '1',
    ])->assertRedirect();
    parse_str((string) parse_url($authorization->headers->get('Location'), PHP_URL_QUERY), $callback);
    expect($callback)->toHaveKey('code');
    $code = DB::table('oauth_auth_codes')->latest('expires_at')->first();
    expect(json_decode($code->scopes, true))->not->toContain('mcp:write');
});

it('does not grant write access to students even if the consent request is forged', function (UserRole $role): void {
    $school = School::factory()->create();
    $user = User::factory()->create(['role' => $role, 'school_id' => $school->id]);
    $tokens = authorizeMcpClientForTest($this, $user, true);

    $this->withToken($tokens['access_token'])->postJson('/mcp/koakademy', [
        'jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call',
        'params' => ['name' => 'get-my-context-tool', 'arguments' => []],
    ])->assertOk()->assertJsonPath('result.structuredContent.mcp.can_write', false);
})->with([UserRole::Student, UserRole::GraduateStudent, UserRole::ShsStudent]);

it('rechecks role eligibility when the consent form is submitted', function (): void {
    $school = School::factory()->create();
    $user = User::factory()->create(['role' => UserRole::Instructor, 'school_id' => $school->id]);
    $clientId = $this->postJson('/oauth/register', [
        'client_name' => 'Role change',
        'redirect_uris' => ['http://127.0.0.1:52817/callback'],
    ])->assertCreated()->json('client_id');
    $query = http_build_query([
        'client_id' => $clientId,
        'redirect_uri' => 'http://127.0.0.1:52817/callback',
        'response_type' => 'code',
        'code_challenge' => str_repeat('a', 43),
        'code_challenge_method' => 'S256',
    ]);

    $this->actingAs($user)->get('/oauth/authorize?'.$query)->assertOk()->assertSee('Allow write access');
    $user->forceFill(['role' => UserRole::Student])->save();

    $this->post('/oauth/authorize', ['client_id' => $clientId, 'auth_token' => session('authToken'), 'allow_write' => '1'])
        ->assertRedirect();
    $code = DB::table('oauth_auth_codes')->first();
    expect(json_decode($code->scopes, true))->not->toContain('mcp:write');
});

it('rejects approval if another user replaces the account that saw consent', function (): void {
    $first = User::factory()->create(['role' => UserRole::Instructor]);
    $other = User::factory()->create(['role' => UserRole::Instructor]);
    $clientId = $this->postJson('/oauth/register', [
        'client_name' => 'Account switch',
        'redirect_uris' => ['http://127.0.0.1:52817/callback'],
    ])->assertCreated()->json('client_id');
    $query = http_build_query([
        'client_id' => $clientId,
        'redirect_uri' => 'http://127.0.0.1:52817/callback',
        'response_type' => 'code',
        'code_challenge' => str_repeat('a', 43),
        'code_challenge_method' => 'S256',
    ]);
    $this->actingAs($first)->get('/oauth/authorize?'.$query)->assertOk();
    $authToken = session('authToken');

    $this->actingAs($other)->post('/oauth/authorize', ['client_id' => $clientId, 'auth_token' => $authToken, 'allow_write' => '1'])
        ->assertForbidden();
    expect(DB::table('oauth_auth_codes')->count())->toBe(0);
});

it('keeps the global write switch and per-tool permissions in force for oauth', function (): void {
    $school = School::factory()->create();
    $user = User::factory()->create(['role' => UserRole::Instructor, 'school_id' => $school->id]);
    $tokens = authorizeMcpClientForTest($this, $user, true);

    $this->withToken($tokens['access_token'])->postJson('/mcp/koakademy', [
        'jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call',
        'params' => ['name' => 'manage-room-tool', 'arguments' => ['action' => 'create', 'name' => 'Not permitted']],
    ])->assertOk()->assertJsonPath('result.isError', true)->assertSee('not permitted to modify rooms');
    expect(Room::query()->where('name', 'Not permitted')->exists())->toBeFalse();

    Permission::firstOrCreate(['name' => 'Update:Room', 'guard_name' => 'web']);
    Auth::forgetGuards();
    Auth::shouldUse('web');
    $user->givePermissionTo('Update:Room');
    Auth::forgetGuards();
    Auth::shouldUse('web');
    $this->withToken($tokens['access_token'])->postJson('/mcp/koakademy', [
        'jsonrpc' => '2.0', 'id' => 10, 'method' => 'tools/call',
        'params' => ['name' => 'manage-room-tool', 'arguments' => ['action' => 'create', 'name' => 'Permitted']],
    ])->assertOk()->assertJsonPath('result.isError', false);
    expect(Room::query()->where('name', 'Permitted')->where('school_id', $school->id)->exists())->toBeTrue();

    app(GeneralSettingsService::class)->updateApiManagementConfig(['mcp_enabled' => true, 'mcp_write_enabled' => false]);
    Auth::forgetGuards();
    Auth::shouldUse('web');
    $this->withToken($tokens['access_token'])->postJson('/mcp/koakademy', [
        'jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/call',
        'params' => ['name' => 'manage-room-tool', 'arguments' => ['action' => 'create', 'name' => 'Disabled']],
    ])->assertOk()->assertJsonPath('result.isError', true)->assertSee('disabled in system settings');
    expect(Room::query()->where('name', 'Disabled')->exists())->toBeFalse();
});

it('authenticates through pkce and keeps oauth tokens read only', function (): void {
    $school = School::factory()->create();
    GeneralSetting::create([
        'school_starting_date' => now()->startOfYear(),
        'school_ending_date' => now()->startOfYear()->addYear(),
        'semester' => 1,
        'site_name' => 'OAuth Test School',
    ]);
    $user = User::factory()->create([
        'role' => UserRole::Instructor,
        'school_id' => $school->id,
        'security_two_factor_enabled' => false,
        'password' => 'password',
    ]);
    $registration = $this->postJson('/oauth/register', [
        'client_name' => 'Test assistant',
        'redirect_uris' => ['http://127.0.0.1:52817/callback'],
    ])->assertCreated();
    $clientId = $registration->json('client_id');
    $verifier = str_repeat('a', 64);
    $challenge = mb_rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

    $query = http_build_query([
        'client_id' => $clientId,
        'redirect_uri' => 'http://127.0.0.1:52817/callback',
        'response_type' => 'code',
        'scope' => 'mcp:use',
        'state' => 'example-state',
        'code_challenge' => $challenge,
        'code_challenge_method' => 'S256',
    ]);

    $this->get('/oauth/authorize?'.$query)->assertRedirect(route('login'));

    $login = $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect();
    parse_str((string) parse_url($login->headers->get('Location'), PHP_URL_QUERY), $intended);
    expect(parse_url($login->headers->get('Location'), PHP_URL_PATH))->toBe('/oauth/authorize')
        ->and($intended['client_id'])->toBe($clientId)
        ->and($intended['code_challenge'])->toBe($challenge);

    $this->get('/oauth/authorize?'.$query)
        ->assertOk()
        ->assertSee('Test assistant')
        ->assertSee('Allow write access')
        ->assertSee('create, update, or delete records')
        ->assertHeader('X-Frame-Options', 'DENY');

    $authorization = $this->post('/oauth/authorize', [
        'client_id' => $clientId,
        'auth_token' => session('authToken'),
    ])->assertRedirect();
    parse_str((string) parse_url($authorization->headers->get('Location'), PHP_URL_QUERY), $callback);
    expect($callback)->toHaveKey('code')->and($callback['state'])->toBe('example-state');

    $tokens = $this->post('/oauth/token', [
        'grant_type' => 'authorization_code',
        'client_id' => $clientId,
        'redirect_uri' => 'http://127.0.0.1:52817/callback',
        'code_verifier' => $verifier,
        'code' => $callback['code'],
    ])->assertOk()->json();
    $token = $tokens['access_token'];
    $refreshToken = $tokens['refresh_token'];
    expect($token)->toBeString()->not->toBeEmpty();

    $this->get('/oauth/authorize?'.$query)->assertOk()->assertSee('Test assistant');

    $rotated = $this->post('/oauth/token', [
        'grant_type' => 'refresh_token',
        'client_id' => $clientId,
        'refresh_token' => $refreshToken,
    ])->assertOk()->json();
    $this->post('/oauth/token', [
        'grant_type' => 'refresh_token',
        'client_id' => $clientId,
        'refresh_token' => $refreshToken,
    ])->assertBadRequest()->assertJsonPath('error', 'invalid_grant');
    $token = $rotated['access_token'];
    $refreshToken = $rotated['refresh_token'];

    Auth::forgetGuards();
    Auth::shouldUse('web');
    $context = $this->withToken($token)->postJson('/mcp/koakademy', [
        'jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call',
        'params' => ['name' => 'get-my-context-tool', 'arguments' => []],
    ]);
    $context->assertOk()
        ->assertJsonPath('result.structuredContent.user.id', $user->id)
        ->assertJsonPath('result.structuredContent.mcp.can_write', false)
        ->assertJsonPath('result.structuredContent.mcp.abilities', ['mcp:read']);

    $otherSchool = School::factory()->create();
    $this->withToken($token)
        ->withHeader('X-KoAkademy-School', (string) $otherSchool->id)
        ->postJson('/mcp/koakademy', [
            'jsonrpc' => '2.0', 'id' => 10, 'method' => 'tools/list',
        ])->assertForbidden();
    $this->withoutHeader('X-KoAkademy-School');

    $this->withToken($token)->postJson('/mcp/koakademy', [
        'jsonrpc' => '2.0', 'id' => 11, 'method' => 'tools/call',
        'params' => ['name' => 'get-school-metrics-tool', 'arguments' => []],
    ])->assertOk()->assertJsonPath('result.isError', true)->assertSee('restricted to administrators');

    Feature::for($user)->deactivate(McpServerAccess::class);
    $this->withToken($token)->postJson('/mcp/koakademy', [
        'jsonrpc' => '2.0', 'id' => 12, 'method' => 'tools/list',
    ])->assertNotFound();
    Feature::for($user)->activate(McpServerAccess::class);

    $write = $this->withToken($token)->postJson('/mcp/koakademy', [
        'jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/call',
        'params' => ['name' => 'update-enrollment-remarks-tool', 'arguments' => ['enrollment_id' => 1, 'remarks' => 'No']],
    ]);
    $write->assertOk()->assertJsonPath('result.isError', true)->assertSee('mcp:write');

    $user->forceFill(['suspended_at' => now()])->save();
    Auth::forgetGuards();
    Auth::shouldUse('web');
    $this->withToken($token)->postJson('/mcp/koakademy', [
        'jsonrpc' => '2.0', 'id' => 20, 'method' => 'tools/list',
    ])->assertForbidden();
    $user->forceFill(['suspended_at' => null])->save();

    app(AdministratorUserAccessService::class)->revokeAll($user);
    Auth::forgetGuards();
    Auth::shouldUse('web');
    $this->withToken($token)->postJson('/mcp/koakademy', [
        'jsonrpc' => '2.0', 'id' => 3, 'method' => 'tools/list',
    ])->assertUnauthorized();

    $this->post('/oauth/token', [
        'grant_type' => 'refresh_token',
        'client_id' => $clientId,
        'refresh_token' => $refreshToken,
    ])->assertBadRequest()->assertJsonPath('error', 'invalid_grant');
});
