<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Administrators\UpsertEnrollmentPeriodRequest;
use App\Models\User;
use App\Services\EnrollmentPeriodService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

final class AdministratorEnrollmentPeriodController extends Controller
{
    public function __construct(
        private readonly EnrollmentPeriodService $periodService,
    ) {}

    public function show(): JsonResponse
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return response()->json([
            'enrollment_period' => $this->periodService->describe(),
        ]);
    }

    public function upsert(UpsertEnrollmentPeriodRequest $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $this->periodService->upsertForTerm($request->validated(), $user);

        return back()->with('flash', [
            'success' => 'Enrollment registration window updated for the active term.',
        ]);
    }
}
