<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Bokku') }} - Personal Finance Management Made Simple</title>
    <meta name="description" content="Bokku is a self-hosted personal finance manager built with Laravel and Filament, inspired by Firefly III. Track your finances with Malaysian banking support.">
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700" rel="stylesheet" />
    
    <!-- Styles -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50 dark:bg-gray-900">
    <!-- Header -->
    <header class="bg-white dark:bg-gray-800 shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-6 md:justify-start md:space-x-10">
                <!-- Logo -->
                <div class="flex justify-start lg:w-0 lg:flex-1">
                    <span class="text-2xl font-bold text-gray-900 dark:text-white flex items-center">
                        📚💰 Bokku
                    </span>
                </div>

                <!-- Navigation -->
                <nav class="hidden md:flex space-x-10">
                    <a href="#features" class="text-base font-medium text-gray-500 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white">
                        Features
                    </a>
                    <a href="#about" class="text-base font-medium text-gray-500 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white">
                        About
                    </a>
                    <a href="https://github.com/chengkangzai/bokku" target="_blank" class="text-base font-medium text-gray-500 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white">
                        GitHub
                    </a>
                </nav>

                <!-- Auth Links -->
                <div class="flex items-center justify-end md:flex-1 lg:w-0 space-x-4">
                    @auth
                        <a href="{{ url('/admin') }}" class="whitespace-nowrap text-base font-medium text-gray-500 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white">
                            Dashboard
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="whitespace-nowrap text-base font-medium text-gray-500 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white">
                            Sign in
                        </a>
                        @if (Route::has('register'))
                            <a href="{{ route('register') }}" class="ml-8 whitespace-nowrap inline-flex items-center justify-center px-4 py-2 border border-transparent rounded-md shadow-sm text-base font-medium text-white bg-blue-600 hover:bg-blue-700">
                                Get Started
                            </a>
                        @endif
                    @endauth
                </div>
            </div>
        </div>
    </header>

    <!-- Hero Section -->
    <main class="bg-white dark:bg-gray-900">
        <div class="max-w-7xl mx-auto py-16 px-4 sm:py-20 sm:px-6 lg:px-8">
            <div class="text-center">
                <h1 class="text-4xl font-extrabold text-gray-900 dark:text-white sm:text-5xl md:text-6xl">
                    <span class="block">Personal Finance</span>
                    <span class="block text-blue-600 dark:text-blue-400">Made Simple</span>
                </h1>
                <p class="mt-3 max-w-md mx-auto text-base text-gray-500 dark:text-gray-300 sm:text-lg md:mt-5 md:text-xl md:max-w-3xl">
                    Bokku is your self-hosted personal finance manager. Track expenses, manage budgets, and gain insights into your financial health with Malaysian banking support.
                </p>
                <div class="mt-5 max-w-md mx-auto sm:flex sm:justify-center md:mt-8">
                    @auth
                        <div class="rounded-md shadow">
                            <a href="{{ url('/admin') }}" class="w-full flex items-center justify-center px-8 py-3 border border-transparent text-base font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 md:py-4 md:text-lg md:px-10">
                                Go to Dashboard
                            </a>
                        </div>
                    @else
                        <div class="rounded-md shadow">
                            <a href="{{ route('register') }}" class="w-full flex items-center justify-center px-8 py-3 border border-transparent text-base font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 md:py-4 md:text-lg md:px-10">
                                Get Started
                            </a>
                        </div>
                        <div class="mt-3 rounded-md shadow sm:mt-0 sm:ml-3">
                            <a href="{{ route('login') }}" class="w-full flex items-center justify-center px-8 py-3 border border-transparent text-base font-medium rounded-md text-blue-600 bg-white hover:bg-gray-50 dark:bg-gray-800 dark:text-blue-400 dark:hover:bg-gray-700 md:py-4 md:text-lg md:px-10">
                                Sign In
                            </a>
                        </div>
                    @endauth
                </div>
            </div>
        </div>

        <!-- Features Section -->
        <div id="features" class="py-12 bg-gray-50 dark:bg-gray-800">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="lg:text-center">
                    <h2 class="text-base text-blue-600 dark:text-blue-400 font-semibold tracking-wide uppercase">Features</h2>
                    <p class="mt-2 text-3xl leading-8 font-extrabold tracking-tight text-gray-900 dark:text-white sm:text-4xl">
                        Everything you need to manage your finances
                    </p>
                </div>

                <div class="mt-10">
                    <div class="space-y-10 md:space-y-0 md:grid md:grid-cols-2 md:gap-x-8 md:gap-y-10">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <div class="flex items-center justify-center h-12 w-12 rounded-md bg-blue-500 text-white">
                                    🏦
                                </div>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">Malaysian Banking Focus</h3>
                                <p class="mt-2 text-base text-gray-500 dark:text-gray-300">
                                    Built with Malaysian banks in mind. Supports MYR, local bank patterns, and payment methods like DuitNow and FPX.
                                </p>
                            </div>
                        </div>

                        <div class="flex">
                            <div class="flex-shrink-0">
                                <div class="flex items-center justify-center h-12 w-12 rounded-md bg-blue-500 text-white">
                                    🤖
                                </div>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">AI-Powered Import</h3>
                                <p class="mt-2 text-base text-gray-500 dark:text-gray-300">
                                    Smart transaction extraction from bank statements with automatic categorization and bank detection.
                                </p>
                            </div>
                        </div>

                        <div class="flex">
                            <div class="flex-shrink-0">
                                <div class="flex items-center justify-center h-12 w-12 rounded-md bg-blue-500 text-white">
                                    📊
                                </div>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">Comprehensive Tracking</h3>
                                <p class="mt-2 text-base text-gray-500 dark:text-gray-300">
                                    Track accounts, transactions, budgets, and recurring payments with detailed reporting and insights.
                                </p>
                            </div>
                        </div>

                        <div class="flex">
                            <div class="flex-shrink-0">
                                <div class="flex items-center justify-center h-12 w-12 rounded-md bg-blue-500 text-white">
                                    🔒
                                </div>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">Secure & Private</h3>
                                <p class="mt-2 text-base text-gray-500 dark:text-gray-300">
                                    Self-hosted solution with multi-tenant architecture ensuring your financial data stays private and secure.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- About Section -->
        <div id="about" class="bg-white dark:bg-gray-900">
            <div class="max-w-7xl mx-auto py-16 px-4 sm:py-20 sm:px-6 lg:px-8">
                <div class="lg:grid lg:grid-cols-2 lg:gap-8 lg:items-center">
                    <div>
                        <h2 class="text-3xl font-extrabold text-gray-900 dark:text-white sm:text-4xl">
                            About Bokku
                        </h2>
                        <p class="mt-3 text-lg text-gray-500 dark:text-gray-300">
                            The name Bokku comes from an evolution: <strong>book keeping</strong> → <strong>buku</strong> (books in Malay) → <strong>bokku</strong> (making it personal). 
                        </p>
                        <p class="mt-3 text-lg text-gray-500 dark:text-gray-300">
                            Inspired by <a href="https://firefly-iii.org/" target="_blank" class="text-blue-600 dark:text-blue-400 hover:underline">Firefly III</a>, 
                            Bokku focuses on Malaysian market specifics while providing a modern, streamlined experience built with Laravel and Filament.
                        </p>
                        <div class="mt-8">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <span class="text-2xl">🛠</span>
                                </div>
                                <div class="ml-3">
                                    <p class="text-base text-gray-500 dark:text-gray-300">
                                        Built with Laravel 12, Filament v4, and modern web technologies
                                    </p>
                                </div>
                            </div>
                            <div class="flex items-center mt-4">
                                <div class="flex-shrink-0">
                                    <span class="text-2xl">🇲🇾</span>
                                </div>
                                <div class="ml-3">
                                    <p class="text-base text-gray-500 dark:text-gray-300">
                                        Built with ❤️ in Malaysia
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-8 lg:mt-0">
                        <div class="text-center">
                            <div class="text-8xl mb-4">📚💰</div>
                            <h3 class="text-xl font-medium text-gray-900 dark:text-white">Your Personal Finance Manager</h3>
                            <p class="mt-2 text-gray-500 dark:text-gray-300">Simple. Secure. Self-hosted.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-gray-50 dark:bg-gray-800">
        <div class="max-w-7xl mx-auto py-12 px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center">
                <div class="flex items-center">
                    <span class="text-sm text-gray-500 dark:text-gray-400">
                        © {{ date('Y') }} Bokku. Open source personal finance manager.
                    </span>
                </div>
                <div class="flex space-x-6">
                    <a href="https://github.com/chengkangzai/bokku" target="_blank" class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300">
                        <span class="sr-only">GitHub</span>
                        <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24">
                            <path fill-rule="evenodd" d="M12 2C6.477 2 2 6.484 2 12.017c0 4.425 2.865 8.18 6.839 9.504.5.092.682-.217.682-.483 0-.237-.008-.868-.013-1.703-2.782.605-3.369-1.343-3.369-1.343-.454-1.158-1.11-1.466-1.11-1.466-.908-.62.069-.608.069-.608 1.003.07 1.531 1.032 1.531 1.032.892 1.53 2.341 1.088 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.113-4.555-4.951 0-1.093.39-1.988 1.029-2.688-.103-.253-.446-1.272.098-2.65 0 0 .84-.27 2.75 1.026A9.564 9.564 0 0112 6.844c.85.004 1.705.115 2.504.337 1.909-1.296 2.747-1.027 2.747-1.027.546 1.379.202 2.398.1 2.651.64.7 1.028 1.595 1.028 2.688 0 3.848-2.339 4.695-4.566 4.943.359.309.678.92.678 1.855 0 1.338-.012 2.419-.012 2.747 0 .268.18.58.688.482A10.019 10.019 0 0022 12.017C22 6.484 17.522 2 12 2z" clip-rule="evenodd"></path>
                        </svg>
                    </a>
                </div>
            </div>
        </div>
    </footer>
</body>
</html>