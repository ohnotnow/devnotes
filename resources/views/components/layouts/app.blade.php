<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>{{ config('app.name') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @fluxAppearance
        @livewireStyles
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600&display=swap" rel="stylesheet" />
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <a href="#main-content" class="sr-only focus:not-sr-only focus:absolute focus:z-50 focus:m-3 focus:rounded-lg focus:bg-white focus:p-3 dark:focus:bg-zinc-800">Skip to content</a>
        @auth
            <flux:sidebar sticky collapsible class="bg-zinc-50 dark:bg-zinc-900 border-r border-zinc-200 dark:border-zinc-700 print:hidden">
                <flux:sidebar.header>
                    <flux:sidebar.brand
                        href="#"
                        logo="https://fluxui.dev/img/demo/logo.png"
                        logo:dark="https://fluxui.dev/img/demo/dark-mode-logo.png"
                        name="{{  config('app.name') }}"
                    />
                    <flux:sidebar.collapse class="in-data-flux-sidebar-on-desktop:not-in-data-flux-sidebar-collapsed-desktop:-mr-2" />
                </flux:sidebar.header>
                <flux:sidebar.nav aria-label="Primary">
                    <flux:sidebar.item icon="document-text" :href="route('home')" wire:navigate>Notes</flux:sidebar.item>
                    <flux:sidebar.item icon="sparkles" :href="route('tidy')" wire:navigate>Tidy</flux:sidebar.item>
                    @can('admin')
                        <flux:separator class="my-2" />
                        <flux:sidebar.item icon="users" :href="route('admin.users')" wire:navigate>Users</flux:sidebar.item>
                        <flux:sidebar.item icon="user-group" :href="route('admin.teams')" wire:navigate>Teams</flux:sidebar.item>
                        <flux:sidebar.item icon="arrow-up-tray" :href="route('admin.import')" wire:navigate>Import</flux:sidebar.item>
                    @endcan
                </flux:sidebar.nav>
                <flux:sidebar.spacer />
                <flux:sidebar.nav aria-label="Account">
                    <flux:sidebar.item icon="user-group" :href="route('team-settings')" wire:navigate>My teams</flux:sidebar.item>
                    <flux:sidebar.item icon="key" :href="route('api-tokens')" wire:navigate>API tokens</flux:sidebar.item>
                    @if (! config('sso.enabled'))
                        <flux:sidebar.item icon="lock-closed" :href="route('change-password')" wire:navigate>Change password</flux:sidebar.item>
                    @endif
                    <form method="post" action="{{ route('auth.logout') }}">
                        @csrf
                        <flux:sidebar.item tooltip="Logout" icon="arrow-right-start-on-rectangle" type="submit">Logout</flux:sidebar.item>
                    </form>
                </flux:sidebar.nav>
            </flux:sidebar>
        @endauth
        <flux:header class="lg:hidden print:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />
        </flux:header>

        <flux:main role="main" id="main-content" tabindex="-1">
            {{ $slot }}
        </flux:main>

        @persist('toast')
            <flux:toast />
        @endpersist
        @fluxScripts
        @stack('scripts')
    </body>
</html>
