<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Faculty;
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
        $user = Auth::user();

        return Inertia::render('faculty-verify', [
            'email' => old('email', session('email', $user->email ?? '')),
        ]);
    }

    /**
     * Verify the faculty ID number.
     */
    public function verify(Request $request)
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email|max:255',
            'faculty_id_number' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return back()
                ->withErrors($validator->errors())
                ->withInput($request->only(['email', 'faculty_id_number']));
        }

        // Verify the faculty ID number matches the email
        $faculty = Faculty::where('faculty_id_number', $request->faculty_id_number)
            ->where('email', $request->email)
            ->first();

        if (! $faculty) {
            return back()
                ->withErrors(['faculty_id_number' => 'The faculty ID number does not match our records for this email address.'])
                ->withInput($request->only(['email', 'faculty_id_number']));
        }

        // Update the user with faculty metadata
        $user->update([
            'faculty_id_number' => $request->faculty_id_number,
            'record_id' => $faculty->id,
        ]);

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
