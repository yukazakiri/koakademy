<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Faculty;
use App\Models\User;
use App\Services\UserSecurityActivityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Inertia\Response;

final class FacultyVerificationController extends Controller
{
    /**
     * Show the faculty verification form.
     */
    public function showForm(): Response
    {
        /** @var User|null $user */
        $user = Auth::user();
        abort_unless($user instanceof User && $user->role?->isFaculty(), 403);

        return Inertia::render('faculty-verify', [
            'email' => $user->email,
        ]);
    }

    /**
     * Verify the faculty ID number against the authenticated user's own email.
     */
    public function verify(Request $request)
    {
        /** @var User|null $user */
        $user = Auth::user();
        abort_unless($user instanceof User && $user->role?->isFaculty(), 403);

        $validator = Validator::make($request->all(), [
            'faculty_id_number' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return back()
                ->withErrors($validator->errors())
                ->withInput($request->only(['faculty_id_number']));
        }

        $normalizedEmail = mb_strtolower(mb_trim((string) $user->email));
        $facultyIdNumber = mb_trim((string) $request->input('faculty_id_number'));

        // Always bind verification to the authenticated user's own email address.
        $faculty = Faculty::withoutSchoolScope()
            ->where('faculty_id_number', $facultyIdNumber)
            ->whereRaw('LOWER(email) = ?', [$normalizedEmail])
            ->first();

        if (! $faculty instanceof Faculty || ! $faculty->isRegisterable()) {
            return back()
                ->withErrors(['faculty_id_number' => 'The faculty ID number does not match our records for your account email address.'])
                ->withInput($request->only(['faculty_id_number']));
        }

        $alreadyClaimed = User::query()
            ->whereKeyNot($user->id)
            ->where(function ($query) use ($faculty, $facultyIdNumber): void {
                $query->where('record_id', $faculty->id)
                    ->orWhere('faculty_id_number', $facultyIdNumber);
            })
            ->exists();

        if ($alreadyClaimed) {
            return back()
                ->withErrors(['faculty_id_number' => 'This faculty ID number is already linked to another account. Please contact the registrar.'])
                ->withInput($request->only(['faculty_id_number']));
        }

        // Update the user with faculty metadata
        $user->update([
            'faculty_id_number' => $faculty->faculty_id_number,
            'record_id' => $faculty->id,
        ]);

        app(UserSecurityActivityService::class)->record($user, 'faculty_id_verified', 'success');

        $settings = app(\App\Settings\SiteSettings::class);
        $appName = $settings->getAppName();

        return redirect('/dashboard')
            ->with('success', 'Faculty ID verified successfully!')
            ->with('flash', [
                'type' => 'success',
                'message' => "Faculty ID verified successfully! Welcome to {$appName}.",
            ]);
    }
}
