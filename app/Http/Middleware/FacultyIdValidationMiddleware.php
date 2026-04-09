<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final class FacultyIdValidationMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        // Check if user is authenticated
        if (! $user instanceof User) {
            return redirect('/login');
        }

        // Check if user is a faculty member
        // Check if faculty_id_number is not set or empty
        if ($user->role && $user->role->isFaculty() && empty($user->faculty_id_number)) {
            // Redirect to faculty ID validation page
            return redirect('/faculty-verify')
                ->with('warning', 'Please verify your faculty ID number to continue.')
                ->with('email', $user->email);
        }

        return $next($request);
    }
}
