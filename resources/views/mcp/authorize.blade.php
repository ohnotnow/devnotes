<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Authorize {{ $client->name }} - {{ config('app.name') }}</title>
    @vite(['resources/css/app.css'])
    @fluxAppearance
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600&display=swap" rel="stylesheet" />
</head>
<body class="min-h-screen bg-white dark:bg-zinc-800">
    <div class="min-h-screen flex items-center justify-center p-4">
        <div class="w-full max-w-md">
            <flux:card class="space-y-6">
                <div class="text-center space-y-2">
                    <flux:heading size="xl" level="1">Authorize {{ $client->name }}</flux:heading>
                    <flux:text>This application will be able to use the {{ config('app.name') }} MCP tools on your behalf.</flux:text>
                </div>

                <flux:text class="text-center">Signed in as {{ $user->email }}</flux:text>

                @if (count($scopes) > 0)
                    <div class="space-y-2">
                        <flux:text class="font-medium">Permissions:</flux:text>
                        <ul class="space-y-1 list-disc list-inside">
                            @foreach ($scopes as $scope)
                                <li><flux:text class="inline">{{ $scope->description }}</flux:text></li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="flex gap-4">
                    <form method="POST" action="{{ route('passport.authorizations.deny') }}" class="flex-1">
                        @csrf
                        @method('DELETE')
                        <input type="hidden" name="state" value="">
                        <input type="hidden" name="client_id" value="{{ $client->id }}">
                        <input type="hidden" name="auth_token" value="{{ $authToken }}">
                        <flux:button type="submit" class="w-full" id="denyButton">Cancel</flux:button>
                    </form>

                    <form method="POST" action="{{ route('passport.authorizations.approve') }}" class="flex-1" id="authorizeForm">
                        @csrf
                        <input type="hidden" name="state" value="">
                        <input type="hidden" name="client_id" value="{{ $client->id }}">
                        <input type="hidden" name="auth_token" value="{{ $authToken }}">
                        <flux:button type="submit" variant="primary" class="w-full" id="authorizeButton" autofocus>Authorize</flux:button>
                    </form>
                </div>
            </flux:card>
        </div>
    </div>

    @fluxScripts
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // After approval the browser follows the redirect chain back to the
            // client; once that happens this popup has done its job, so close it.
            document.getElementById('authorizeForm').addEventListener('submit', function () {
                document.getElementById('authorizeButton').disabled = true;

                setTimeout(function () {
                    const checkRedirect = setInterval(function () {
                        if (!window.location.href.includes('/oauth/authorize') ||
                            window.location.search.includes('code=') ||
                            window.location.search.includes('error=')) {
                            clearInterval(checkRedirect);
                            window.close();
                        }
                    }, 100);

                    setTimeout(function () {
                        clearInterval(checkRedirect);
                        window.close();
                    }, 5000);
                }, 200);
            });

            document.getElementById('denyButton').closest('form').addEventListener('submit', function () {
                setTimeout(function () {
                    window.close();
                }, 200);
            });
        });
    </script>
</body>
</html>
