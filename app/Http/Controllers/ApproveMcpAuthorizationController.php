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
        $authRequest = $this->getAuthRequestFromSession($request);
        $user = $request->user();
        $userId = $authRequest->getUser()?->getIdentifier();

        abort_unless($user instanceof User && $userId !== null && (string) $user->getAuthIdentifier() === (string) $userId, 403);

        $scopes = [new Scope(McpOAuth::READ)];

        if (($validated['allow_write'] ?? false) && McpOAuth::writeGrantable($user)) {
            $scopes[] = new Scope(McpOAuth::WRITE);
        }

        $authRequest->setScopes($scopes);
        $authRequest->setAuthorizationApproved(true);

        return $this->withErrorHandling(fn () => $this->convertResponse(
            $this->server->completeAuthorizationRequest($authRequest, $psrResponse)
        ), $authRequest->getGrantTypeId() === 'implicit');
    }
}
