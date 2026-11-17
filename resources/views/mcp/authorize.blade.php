<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => ($appearance ?? 'system') === 'dark'])>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Authorize {{ $client->name }} - {{ config('app.name', 'KoAkademy') }}</title>
    <script>
        if (@json(($appearance ?? 'system') === 'system') && window.matchMedia('(prefers-color-scheme: dark)').matches) {
            document.documentElement.classList.add('dark');
        }
    </script>
    @vite(['resources/css/app.css'])
</head>
<body class="bg-background text-foreground font-sans antialiased">
<main class="flex min-h-screen items-center justify-center p-4">
    <div class="bg-card text-card-foreground w-full max-w-lg rounded-lg border shadow-sm">
        <div class="flex flex-col gap-3 p-6 pb-0">
            <p class="text-muted-foreground text-xs font-semibold tracking-wide uppercase">MCP connection</p>
            <h1 class="text-2xl font-semibold tracking-tight">Authorize {{ $client->name }}</h1>
            <p class="text-muted-foreground text-sm">Choose what this application may do with your account. Your existing school access and permissions still apply.</p>
            <div class="bg-muted/50 mt-1 rounded-lg border p-4">
                <p class="text-muted-foreground text-xs">Signed in as</p>
                <p class="break-all font-medium">{{ $user->email }}</p>
            </div>
        </div>

        <form method="POST" action="{{ route('passport.authorizations.approve') }}" id="authorizeForm" class="flex flex-col gap-4 p-6">
            @csrf
            <input type="hidden" name="client_id" value="{{ $client->id }}">
            <input type="hidden" name="auth_token" value="{{ $authToken }}">

            <fieldset class="flex flex-col gap-3">
                <legend class="mb-2 text-sm font-semibold">Connection permissions</legend>
                <div class="bg-muted/40 rounded-lg border p-4">
                    <p class="text-sm font-medium">Read access <span class="text-muted-foreground font-normal">· Required</span></p>
                    <p class="text-muted-foreground mt-1 text-sm">View records available to your account through MCP tools.</p>
                </div>

                @if (\App\Mcp\McpOAuth::writeGrantable($user))
                    <label for="allow-write" class="hover:bg-muted/40 flex cursor-pointer items-start gap-3 rounded-lg border p-4">
                        <input id="allow-write" type="checkbox" name="allow_write" value="1" class="accent-primary focus-visible:ring-primary mt-1 h-4 w-4 shrink-0 focus-visible:ring-2 focus-visible:ring-offset-2" aria-describedby="write-description">
                        <span class="flex flex-col gap-1">
                            <span class="text-sm font-medium">Allow write access</span>
                            <span id="write-description" class="text-muted-foreground text-sm">Allow this application to create, update, or delete records through any available MCP tool. Only actions your account is permitted to perform are allowed.</span>
                        </span>
                    </label>
                @else
                    <div class="bg-muted/40 rounded-lg border p-4">
                        <p class="text-sm font-medium">Write access unavailable</p>
                        <p class="text-muted-foreground mt-1 text-sm">
                            @if (! \App\Mcp\McpOAuth::userMayWrite($user))
                                Write access is available only to staff and administrative accounts.
                            @else
                                Data modifications are disabled in system settings.
                            @endif
                        </p>
                    </div>
                @endif
            </fieldset>

            @php($redirectUri = request()->query('redirect_uri', $client->redirect_uris[0] ?? null))
            @if ($redirectUri)
                <p class="text-muted-foreground text-xs break-all">After authorization you will be redirected to {{ $redirectUri }}</p>
            @endif

            <div class="flex gap-3 pt-2">
                <button type="submit" form="denyForm" class="hover:bg-muted focus-visible:ring-primary h-11 flex-1 rounded-md border px-4 text-sm font-medium focus-visible:ring-2 focus-visible:ring-offset-2">Cancel</button>
                <button type="submit" class="bg-primary text-primary-foreground hover:opacity-90 focus-visible:ring-primary h-11 flex-1 rounded-md px-4 text-sm font-medium focus-visible:ring-2 focus-visible:ring-offset-2">Authorize</button>
            </div>
        </form>

        <form method="POST" action="{{ route('passport.authorizations.deny') }}" id="denyForm">
            @csrf
            @method('DELETE')
            <input type="hidden" name="client_id" value="{{ $client->id }}">
            <input type="hidden" name="auth_token" value="{{ $authToken }}">
        </form>
    </div>
</main>
</body>
</html>
