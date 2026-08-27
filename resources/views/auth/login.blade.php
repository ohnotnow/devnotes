<x-layouts.app>
    <div class="min-h-screen flex items-center justify-center">
        <div class="w-full max-w-md">
            <flux:card>
                <div class="text-center mb-6">
                    <flux:heading size="xl" level="1">{{ ucwords(config('app.name')) }} Login</flux:heading>
                </div>
                
                @error('authentication')
                    <div class="mb-6">
                        <flux:text variant="danger" class="text-center font-bold p-4 bg-red-100 rounded">
                            {{ $message }}
                        </flux:text>
                    </div>
                @enderror

                @if (config('sso.enabled', true))
                    <flux:button href="{{ route('login.sso') }}" variant="primary" class="w-full">
                        Login with SSO
                    </flux:button>
                @else
                    <form method="POST" action="{{ route('login.local') }}" class="space-y-6">
                        @csrf

                        <flux:input
                            label="Username"
                            name="username"
                            type="text"
                            required
                            autofocus
                        />

                        <flux:input
                            label="Password"
                            name="password"
                            type="password"
                            required
                        />

                        <flux:separator class="my-4" />
                        
                        <flux:button type="submit" variant="primary" class="w-full">
                            Log In
                        </flux:button>
                    </form>
                @endif
            </flux:card>
        </div>
    </div>
</x-layouts.app>
