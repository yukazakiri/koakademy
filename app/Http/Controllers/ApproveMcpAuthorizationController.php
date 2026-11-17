<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Mcp\McpOAuth;
use App\Models\User;
use Illuminate\Http\Request;
use Laravel\Passport\Bridge\Scope;
use Laravel\Passport\Http\Controllers\ApproveAuthorizationController;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\HttpFoundation\Response;

final class ApproveMcpAuthorizationController extends ApproveAuthorizationController
{
    public function approve(Request $request, ResponseInterface $psrResponse): Response
    {
        $validated = $request->validate(['allow_write' => ['sometimes', 'boolean']]);

        // Serialize consent and account-wide revocation on the user row. The
        // transaction also covers persistence of the new authorization code.
        return (new User)->getConnection()->transaction(function () use ($request, $psrResponse, $validated): Response {
            $authRequest = $this->getAuthRequestFromSession($request);
            $userId = $authRequest->getUser()?->getIdentifier();
            $connection = (new User)->getConnection();
            if ($connection->getDriverName() === 'sqlite') {
                $connection->table((new User)->getTable())->where('id', -1)->update(['session_version' => 0]);
            }
            $user = $userId !== null ? User::query()->whereKey($userId)->lockForUpdate()->first() : null;

            abort_unless($user instanceof User && (string) $request->user()?->getAuthIdentifier() === (string) $userId, 403);
            abort_if($user->suspended_at !== null || (int) $request->session()->get('user_session_version', 0) !== $user->session_version, 403);

            $scopes = [new Scope(McpOAuth::READ)];

            if (($validated['allow_write'] ?? false) && McpOAuth::writeGrantable($user)) {
                $scopes[] = new Scope(McpOAuth::WRITE);
            }

            $authRequest->setScopes($scopes);
            $authRequest->setAuthorizationApproved(true);

            return $this->withErrorHandling(fn () => $this->convertResponse(
                $this->server->completeAuthorizationRequest($authRequest, $psrResponse)
            ), $authRequest->getGrantTypeId() === 'implicit');
        });
    }
}
