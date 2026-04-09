<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Spatie\LaravelPasskeys\Actions\GeneratePasskeyRegisterOptionsAction;
use Spatie\LaravelPasskeys\Actions\StorePasskeyAction;
use Spatie\LaravelPasskeys\Support\Config;
use Throwable;

final class PasskeyController extends Controller
{
    /**
     * Generate registration options for a new passkey.
     */
    public function generateRegistrationOptions(Request $request)
    {
        $user = Auth::user();

        // Dynamic RP ID to match current domain
        config(['passkeys.relying_party.id' => $request->getHost()]);
        // Also set app.url just in case actions rely on it
        config(['app.url' => $request->getSchemeAndHttpHost()]);

        $generatePassKeyOptionsAction = Config::getAction('generate_passkey_register_options', GeneratePasskeyRegisterOptionsAction::class);

        $options = $generatePassKeyOptionsAction->execute($user);

        $request->session()->put('passkey-registration-options', $options);

        return response()->json([
            'options' => json_decode($options),
        ]);
    }

    /**
     * Store a newly created passkey.
     */
    public function store(Request $request)
    {
        $request->validate([
            'passkey' => 'required|string',
            'name' => 'required|string|max:255',
        ]);

        $user = Auth::user();

        // Dynamic RP ID to match current domain
        config(['passkeys.relying_party.id' => $request->getHost()]);
        config(['app.url' => $request->getSchemeAndHttpHost()]);

        $storePasskeyAction = Config::getAction('store_passkey', StorePasskeyAction::class);

        try {
            $passkey = $request->input('passkey');
            $name = $request->input('name');
            $options = $request->session()->pull('passkey-registration-options');

            if (! $options) {
                return response()->json(['error' => 'Registration options not found or expired.'], 400);
            }

            $storePasskeyAction->execute(
                $user,
                $passkey,
                $options,
                $request->getHost(), // hostName
                ['name' => $name]
            );

            return back()->with('flash', [
                'success' => 'Passkey added successfully.',
            ]);
        } catch (Throwable $e) {
            return back()->withErrors(['error' => 'Failed to add passkey: '.$e->getMessage()]);
        }
    }

    /**
     * Delete a passkey.
     */
    public function destroy(Request $request, $id)
    {
        $user = Auth::user();

        $user->passkeys()->where('id', $id)->delete();

        return back()->with('flash', [
            'success' => 'Passkey deleted successfully.',
        ]);
    }

    public function index(Request $request)
    {
        $user = Auth::user();

        // Select specific columns to avoid fetching binary data
        $passkeys = $user->passkeys()
            ->select(['id', 'name', 'created_at', 'last_used_at'])
            ->get();

        return response()->json([
            'passkeys' => $passkeys,
        ]);
    }
}
