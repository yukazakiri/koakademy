<?php

declare(strict_types=1);

namespace App\Mcp\OAuth;

use DateInterval;
use JsonException;
use Laravel\Passport\Passport;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\Grant\AuthCodeGrant;
use League\OAuth2\Server\ResponseTypes\ResponseTypeInterface;
use LogicException;
use Psr\Http\Message\ServerRequestInterface;

final class CoordinatedAuthCodeGrant extends AuthCodeGrant
{
    public function respondToAccessTokenRequest(
        ServerRequestInterface $request,
        ResponseTypeInterface $responseType,
        DateInterval $accessTokenTTL,
    ): ResponseTypeInterface {
        return Passport::authCode()->getConnection()->transaction(function () use ($request, $responseType, $accessTokenTTL): ResponseTypeInterface {
            $connection = Passport::authCode()->getConnection();
            if ($connection->getDriverName() === 'sqlite') {
                $connection->table(Passport::authCode()->getTable())->where('id', '')->update(['revoked' => false]);
            }

            $encryptedCode = $this->getRequestParameter('code', $request);

            // Inspect only enough authenticated payload to lock the persisted
            // code. Passport still validates the client, expiry and PKCE.
            if (is_string($encryptedCode)) {
                try {
                    $payload = json_decode($this->decrypt($encryptedCode), true, flags: JSON_THROW_ON_ERROR);
                } catch (LogicException|JsonException) {
                    $payload = null;
                }

                if (is_array($payload) && is_string($payload['auth_code_id'] ?? null)) {
                    $active = Passport::authCode()->newQuery()
                        ->whereKey($payload['auth_code_id'])
                        ->where('revoked', false)
                        ->lockForUpdate()
                        ->first();

                    if (! $active) {
                        throw OAuthServerException::invalidGrant('Authorization code has been revoked');
                    }
                }
            }

            return parent::respondToAccessTokenRequest($request, $responseType, $accessTokenTTL);
        });
    }
}
