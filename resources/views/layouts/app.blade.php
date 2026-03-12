<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
  <head>
    @viteReactRefresh
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name'))</title>
    <meta name="color-scheme" content="dark light">
    <script>
      (function() {
        try {
          var theme = localStorage.getItem('theme') || 'system';
          var d = document.documentElement;
          var isDark = theme === 'dark' || (theme === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
          if (isDark) d.classList.add('dark'); else d.classList.remove('dark');
        } catch (e) { /* no-op */ }
      })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/navbar.tsx'])
    @if(config('sentry.dsn'))
    <script>window.SENTRY_DSN = "{{ config('sentry.dsn') }}";</script>
    @vite(['resources/js/sentry.ts'])
    @endif
    @stack('head')
  </head>
  <body class="min-h-screen flex flex-col">
    <header class="site-header border-b border-gray-200 dark:border-[#3E3E3A] h-14">
      <div id="navbar" 
        data-authenticated="{{ auth()->check() ? 'true' : 'false' }}" 
        data-is-admin="{{ auth()->check() && auth()->user()->isAdmin() ? 'true' : 'false' }}" 
      />
    </header>

    <main class="flex-1 px-4 py-6 sm:px-6 lg:px-8">
      @yield('content')
    </main>

    <footer class="border-t border-gray-200 dark:border-[#3E3E3A] py-6 text-sm text-center text-gray-600 dark:text-[#A1A09A]">
      © {{ date('Y') }}
    </footer>

    @stack('scripts')
    <div id="sonner-toaster"></div>
    @vite(['resources/js/components/ui/sonner.tsx'])
    <script>
      // This is a bit of a hack to ensure the Toaster is rendered
      // In a real app we'd probably have a top-level React provider
    </script>
  </body>
</html>
