<?php

declare(strict_types=1);

namespace App\Mcp\OAuth;

use DateInterval;
use Laravel\Passport\Passport;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\Grant\RefreshTokenGrant;
use League\OAuth2\Server\ResponseTypes\ResponseTypeInterface;
use Psr\Http\Message\ServerRequestInterface;

final class CoordinatedRefreshTokenGrant extends RefreshTokenGrant
{
    public function respondToAccessTokenRequest(
        ServerRequestInterface $request,
        ResponseTypeInterface $responseType,
        DateInterval $accessTokenTTL,
    ): ResponseTypeInterface {
        return Passport::refreshToken()->getConnection()->transaction(
            fn (): ResponseTypeInterface => parent::respondToAccessTokenRequest($request, $responseType, $accessTokenTTL),
        );
    }

    protected function validateOldRefreshToken(ServerRequestInterface $request, string $clientId): array
    {
        $connection = Passport::refreshToken()->getConnection();
        if ($connection->getDriverName() === 'sqlite') {
            // SQLite has no SELECT FOR UPDATE. Start a write transaction before
            // reading the credential so revocation cannot race the exchange.
            $connection->table(Passport::refreshToken()->getTable())->where('id', '')->update(['revoked' => false]);
        }

        $token = parent::validateOldRefreshToken($request, $clientId);

        // The sweep locks and revokes refresh tokens before access tokens.
        // Hold this row lock until the replacement pair has been persisted.
        $active = Passport::refreshToken()->newQuery()
            ->whereKey($token['refresh_token_id'])
            ->where('access_token_id', $token['access_token_id'])
            ->where('revoked', false)
            ->lockForUpdate()
            ->first();

        if (! $active) {
            throw OAuthServerException::invalidRefreshToken('Token has been revoked');
        }

        $accessToken = Passport::token()->newQuery()
            ->whereKey($token['access_token_id'])
            ->where('client_id', $clientId)
            ->where('user_id', $token['user_id'])
            ->where('revoked', false)
            ->lockForUpdate()
            ->first();

        if (! $accessToken) {
            throw OAuthServerException::invalidRefreshToken('Access token has been revoked');
        }

        return $token;
    }
}
