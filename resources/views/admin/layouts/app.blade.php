<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') - Weylo Admin</title>

    <!-- Tailwind CSS via CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#fdf4ff',
                            100: '#fae8ff',
                            200: '#f5d0fe',
                            300: '#f0abfc',
                            400: '#e879f9',
                            500: '#d946ef',
                            600: '#c026d3',
                            700: '#a21caf',
                            800: '#86198f',
                            900: '#701a75',
                        }
                    }
                }
            }
        }
    </script>

    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <style>
        [x-cloak] { display: none !important; }
    </style>

    @stack('styles')
</head>
<body class="bg-gray-100" x-data="{ sidebarOpen: false }">
    <div class="min-h-screen flex">
        <!-- Mobile Menu Overlay -->
        <div x-show="sidebarOpen"
             x-cloak
             @click="sidebarOpen = false"
             class="fixed inset-0 bg-black bg-opacity-50 z-40 lg:hidden"
             x-transition:enter="transition-opacity ease-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition-opacity ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"></div>

        <!-- Sidebar -->
        <aside :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
               class="fixed inset-y-0 left-0 z-50 w-64 bg-gray-900 transform transition-transform duration-300 ease-in-out lg:translate-x-0 lg:relative lg:flex lg:flex-col">

            <!-- Logo -->
            <div class="flex items-center justify-between h-16 px-6 bg-gray-800 flex-shrink-0">
                <a href="{{ route('admin.dashboard') }}" class="flex items-center space-x-2">
                    <div class="w-8 h-8 bg-primary-500 rounded-lg flex items-center justify-center">
                        <i class="fas fa-comment-dots text-white"></i>
                    </div>
                    <span class="text-white font-bold text-lg">Weylo</span>
                </a>
                <button @click="sidebarOpen = false" class="lg:hidden text-gray-400 hover:text-white">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <!-- Navigation -->
            <nav class="flex-1 mt-6 px-3 overflow-y-auto">
                <div class="space-y-1">
                    <a href="{{ route('admin.dashboard') }}"
                       class="flex items-center px-4 py-3 text-sm rounded-lg transition-colors {{ request()->routeIs('admin.dashboard') ? 'bg-primary-600 text-white' : 'text-gray-300 hover:bg-gray-800 hover:text-white' }}">
                        <i class="fas fa-chart-pie w-5 mr-3"></i>
                        Dashboard
                    </a>

                    <a href="{{ route('admin.users.index') }}"
                       class="flex items-center px-4 py-3 text-sm rounded-lg transition-colors {{ request()->routeIs('admin.users.*') ? 'bg-primary-600 text-white' : 'text-gray-300 hover:bg-gray-800 hover:text-white' }}">
                        <i class="fas fa-users w-5 mr-3"></i>
                        Utilisateurs
                    </a>

                    <a href="{{ route('admin.transactions.index') }}"
                       class="flex items-center px-4 py-3 text-sm rounded-lg transition-colors {{ request()->routeIs('admin.transactions.*') ? 'bg-primary-600 text-white' : 'text-gray-300 hover:bg-gray-800 hover:text-white' }}">
                        <i class="fas fa-exchange-alt w-5 mr-3"></i>
                        Transactions
                        @php
                            $pendingWithdrawalsTransactionsCount = \App\Models\Transaction::where('type', 'withdrawal')->where('status', 'pending')->count();
                        @endphp
                        @if($pendingWithdrawalsTransactionsCount > 0)
                            <span class="ml-auto bg-yellow-500 text-gray-900 text-xs font-bold px-2 py-0.5 rounded-full">{{ $pendingWithdrawalsTransactionsCount }}</span>
                        @endif
                    </a>

                    <a href="{{ route('admin.moderation.index') }}"
                       class="flex items-center px-4 py-3 text-sm rounded-lg transition-colors {{ request()->routeIs('admin.moderation.*') ? 'bg-primary-600 text-white' : 'text-gray-300 hover:bg-gray-800 hover:text-white' }}">
                        <i class="fas fa-shield-alt w-5 mr-3"></i>
                        Modération
                        @if(isset($pendingReportsCount) && $pendingReportsCount > 0)
                            <span class="ml-auto bg-red-500 text-white text-xs font-bold px-2 py-0.5 rounded-full">{{ $pendingReportsCount }}</span>
                        @endif
                    </a>

                    <a href="{{ route('admin.withdrawals.index') }}"
                       class="flex items-center px-4 py-3 text-sm rounded-lg transition-colors {{ request()->routeIs('admin.withdrawals.*') ? 'bg-primary-600 text-white' : 'text-gray-300 hover:bg-gray-800 hover:text-white' }}">
                        <i class="fas fa-money-bill-wave w-5 mr-3"></i>
                        Retraits
                        @if(isset($pendingWithdrawalsCount) && $pendingWithdrawalsCount > 0)
                            <span class="ml-auto bg-yellow-500 text-gray-900 text-xs font-bold px-2 py-0.5 rounded-full">{{ $pendingWithdrawalsCount }}</span>
                        @endif
                    </a>

                    <a href="{{ route('admin.confessions.index') }}"
                       class="flex items-center px-4 py-3 text-sm rounded-lg transition-colors {{ request()->routeIs('admin.confessions.*') ? 'bg-primary-600 text-white' : 'text-gray-300 hover:bg-gray-800 hover:text-white' }}">
                        <i class="fas fa-heart w-5 mr-3"></i>
                        Confessions
                    </a>

                    <a href="{{ route('admin.messages.index') }}"
                       class="flex items-center px-4 py-3 text-sm rounded-lg transition-colors {{ request()->routeIs('admin.messages.*') ? 'bg-primary-600 text-white' : 'text-gray-300 hover:bg-gray-800 hover:text-white' }}">
                        <i class="fas fa-envelope w-5 mr-3"></i>
                        Messages
                    </a>

                    <a href="{{ route('admin.stories.index') }}"
                       class="flex items-center px-4 py-3 text-sm rounded-lg transition-colors {{ request()->routeIs('admin.stories.*') ? 'bg-primary-600 text-white' : 'text-gray-300 hover:bg-gray-800 hover:text-white' }}">
                        <i class="fas fa-images w-5 mr-3"></i>
                        Stories
                    </a>

                    <a href="{{ route('admin.groups.index') }}"
                       class="flex items-center px-4 py-3 text-sm rounded-lg transition-colors {{ request()->routeIs('admin.groups.index') || request()->routeIs('admin.groups.show') ? 'bg-primary-600 text-white' : 'text-gray-300 hover:bg-gray-800 hover:text-white' }}">
                        <i class="fas fa-users-between-lines w-5 mr-3"></i>
                        Groupes
                    </a>

                    <a href="{{ route('admin.group-categories.index') }}"
                       class="flex items-center px-4 py-3 text-sm rounded-lg transition-colors {{ request()->routeIs('admin.group-categories.*') ? 'bg-primary-600 text-white' : 'text-gray-300 hover:bg-gray-800 hover:text-white' }}">
                        <i class="fas fa-tags w-5 mr-3"></i>
                        Catégories Groupes
                    </a>

                    <a href="{{ route('admin.gifts.index') }}"
                       class="flex items-center px-4 py-3 text-sm rounded-lg transition-colors {{ request()->routeIs('admin.gifts.index') ? 'bg-primary-600 text-white' : 'text-gray-300 hover:bg-gray-800 hover:text-white' }}">
                        <i class="fas fa-gift w-5 mr-3"></i>
                        Transactions Cadeaux
                    </a>

                    <a href="{{ route('admin.gift-categories.index') }}"
                       class="flex items-center px-4 py-3 text-sm rounded-lg transition-colors {{ request()->routeIs('admin.gift-categories.*') ? 'bg-primary-600 text-white' : 'text-gray-300 hover:bg-gray-800 hover:text-white' }}">
                        <i class="fas fa-folder w-5 mr-3"></i>
                        Catégories Cadeaux
                    </a>

                    <a href="{{ route('admin.gift-management.index') }}"
                       class="flex items-center px-4 py-3 text-sm rounded-lg transition-colors {{ request()->routeIs('admin.gift-management.*') ? 'bg-primary-600 text-white' : 'text-gray-300 hover:bg-gray-800 hover:text-white' }}">
                        <i class="fas fa-gifts w-5 mr-3"></i>
                        Gestion Cadeaux
                    </a>

                    <a href="{{ route('admin.payments.index') }}"
                       class="flex items-center px-4 py-3 text-sm rounded-lg transition-colors {{ request()->routeIs('admin.payments.*') ? 'bg-primary-600 text-white' : 'text-gray-300 hover:bg-gray-800 hover:text-white' }}">
                        <i class="fas fa-credit-card w-5 mr-3"></i>
                        Paiements
                    </a>

                    <a href="{{ route('admin.analytics') }}"
                       class="flex items-center px-4 py-3 text-sm rounded-lg transition-colors {{ request()->routeIs('admin.analytics') ? 'bg-primary-600 text-white' : 'text-gray-300 hover:bg-gray-800 hover:text-white' }}">
                        <i class="fas fa-chart-line w-5 mr-3"></i>
                        Analytics
                    </a>

                    <a href="{{ route('admin.revenue') }}"
                       class="flex items-center px-4 py-3 text-sm rounded-lg transition-colors {{ request()->routeIs('admin.revenue') ? 'bg-primary-600 text-white' : 'text-gray-300 hover:bg-gray-800 hover:text-white' }}">
                        <i class="fas fa-coins w-5 mr-3"></i>
                        Revenus
                    </a>

                    <a href="{{ route('admin.link-generator') }}"
                       class="flex items-center px-4 py-3 text-sm rounded-lg transition-colors {{ request()->routeIs('admin.link-generator') ? 'bg-primary-600 text-white' : 'text-gray-300 hover:bg-gray-800 hover:text-white' }}">
                        <i class="fas fa-link w-5 mr-3"></i>
                        Générateur de liens
                    </a>
                </div>

                <!-- Section Settings -->
                <div class="mt-8 pt-6 border-t border-gray-700">
                    <p class="px-4 text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Administration</p>

                    @if(auth()->user()->is_admin)
                    <a href="{{ route('admin.team.index') }}"
                       class="flex items-center px-4 py-3 text-sm rounded-lg transition-colors {{ request()->routeIs('admin.team.*') ? 'bg-primary-600 text-white' : 'text-gray-300 hover:bg-gray-800 hover:text-white' }}">
                        <i class="fas fa-user-shield w-5 mr-3"></i>
                        Équipe
                    </a>
                    @endif

                    <a href="{{ route('admin.settings') }}"
                       class="flex items-center px-4 py-3 text-sm rounded-lg transition-colors {{ request()->routeIs('admin.settings') ? 'bg-primary-600 text-white' : 'text-gray-300 hover:bg-gray-800 hover:text-white' }}">
                        <i class="fas fa-cog w-5 mr-3"></i>
                        Configuration
                    </a>

                    <a href="{{ route('admin.service-config.index') }}"
                       class="flex items-center px-4 py-3 text-sm rounded-lg transition-colors {{ request()->routeIs('admin.service-config.*') ? 'bg-primary-600 text-white' : 'text-gray-300 hover:bg-gray-800 hover:text-white' }}">
                        <i class="fas fa-plug w-5 mr-3"></i>
                        Services API
                    </a>

                    <a href="{{ route('admin.legal-pages.index') }}"
                       class="flex items-center px-4 py-3 text-sm rounded-lg transition-colors {{ request()->routeIs('admin.legal-pages.*') ? 'bg-primary-600 text-white' : 'text-gray-300 hover:bg-gray-800 hover:text-white' }}">
                        <i class="fas fa-file-contract w-5 mr-3"></i>
                        Pages Légales
                    </a>

                    <a href="{{ route('admin.payment-config.index') }}"
                       class="flex items-center px-4 py-3 text-sm rounded-lg transition-colors {{ request()->routeIs('admin.payment-config.*') ? 'bg-primary-600 text-white' : 'text-gray-300 hover:bg-gray-800 hover:text-white' }}">
                        <i class="fas fa-money-check-alt w-5 mr-3"></i>
                        Config Paiements
                    </a>

                    <a href="{{ route('admin.maintenance.index') }}"
                       class="flex items-center px-4 py-3 text-sm rounded-lg transition-colors {{ request()->routeIs('admin.maintenance.*') ? 'bg-primary-600 text-white' : 'text-gray-300 hover:bg-gray-800 hover:text-white' }}">
                        <i class="fas fa-tools w-5 mr-3"></i>
                        Mode Maintenance
                        @php
                            $maintenanceEnabled = \App\Models\Setting::get('maintenance_mode_enabled', false);
                        @endphp
                        @if($maintenanceEnabled)
                            <span class="ml-auto bg-red-500 text-white text-xs font-bold px-2 py-0.5 rounded-full">ACTIF</span>
                        @endif
                    </a>
                </div>
            </nav>

            <!-- User Info at bottom -->
            <div class="p-4 border-t border-gray-700 flex-shrink-0">
                <div class="flex items-center">
                    <a href="{{ route('admin.profile') }}" class="w-10 h-10 rounded-full flex items-center justify-center overflow-hidden flex-shrink-0 hover:ring-2 hover:ring-primary-500 transition-all">
                        @if(auth()->user()->avatar)
                            <img src="{{ auth()->user()->avatar_url }}" alt="{{ auth()->user()->first_name }}" class="w-10 h-10 object-cover">
                        @else
                            <div class="w-10 h-10 bg-primary-500 rounded-full flex items-center justify-center text-white font-bold">
                                {{ strtoupper(substr(auth()->user()->first_name ?? 'A', 0, 1)) }}
                            </div>
                        @endif
                    </a>
                    <a href="{{ route('admin.profile') }}" class="ml-3 flex-1 hover:opacity-80 transition-opacity">
                        <p class="text-sm font-medium text-white">{{ auth()->user()->first_name ?? 'Admin' }}</p>
                        <p class="text-xs text-gray-400">{{ ucfirst(auth()->user()->role ?? 'admin') }}</p>
                    </a>
                    <form action="{{ route('admin.logout') }}" method="POST">
                        @csrf
                        <button type="submit" class="text-gray-400 hover:text-red-400 transition-colors" title="Déconnexion">
                            <i class="fas fa-sign-out-alt"></i>
                        </button>
                    </form>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <div class="flex-1 flex flex-col min-h-screen lg:min-w-0">
            <!-- Top Header -->
            <header class="bg-white shadow-sm border-b border-gray-200 h-16 flex items-center justify-between px-6 flex-shrink-0">
                <div class="flex items-center">
                    <button @click="sidebarOpen = true" class="lg:hidden text-gray-600 hover:text-gray-900 mr-4">
                        <i class="fas fa-bars text-xl"></i>
                    </button>
                    <h1 class="text-xl font-semibold text-gray-800">@yield('header', 'Dashboard')</h1>
                </div>

                <div class="flex items-center space-x-4">
                    <!-- Notifications -->
                    <div x-data="{ open: false }" class="relative">
                        <button @click="open = !open" class="relative text-gray-600 hover:text-gray-900">
                            <i class="fas fa-bell text-xl"></i>
                            @if(isset($notificationsCount) && $notificationsCount > 0)
                                <span class="absolute -top-1 -right-1 w-5 h-5 bg-red-500 text-white text-xs rounded-full flex items-center justify-center">{{ $notificationsCount }}</span>
                            @endif
                        </button>

                        <div x-show="open"
                             x-cloak
                             @click.away="open = false"
                             class="absolute right-0 mt-2 w-80 bg-white rounded-lg shadow-lg border border-gray-200 py-2 z-50">
                            <div class="px-4 py-2 border-b border-gray-200">
                                <h3 class="font-semibold text-gray-800">Notifications</h3>
                            </div>
                            <div class="max-h-64 overflow-y-auto">
                                <p class="px-4 py-3 text-sm text-gray-500">Aucune nouvelle notification</p>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <a href="/" target="_blank" class="text-gray-600 hover:text-gray-900" title="Voir le site">
                        <i class="fas fa-external-link-alt"></i>
                    </a>
                </div>
            </header>

            <!-- Page Content -->
            <main class="flex-1 p-6 overflow-auto">
                <!-- Flash Messages -->
                @if(session('success'))
                    <div class="mb-6 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg flex items-center justify-between" role="alert">
                        <div class="flex items-center">
                            <i class="fas fa-check-circle mr-2"></i>
                            <span>{{ session('success') }}</span>
                        </div>
                        <button onclick="this.parentElement.remove()" class="text-green-700 hover:text-green-900">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                @endif

                @if(session('error'))
                    <div class="mb-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg flex items-center justify-between" role="alert">
                        <div class="flex items-center">
                            <i class="fas fa-exclamation-circle mr-2"></i>
                            <span>{{ session('error') }}</span>
                        </div>
                        <button onclick="this.parentElement.remove()" class="text-red-700 hover:text-red-900">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                @endif

                @yield('content')
            </main>

            <!-- Footer -->
            <footer class="border-t border-gray-200 bg-white px-6 py-4 flex-shrink-0">
                <p class="text-sm text-gray-500 text-center">
                    &copy; {{ date('Y') }} Weylo. Tous droits réservés.
                </p>
            </footer>
        </div>
    </div>

    @stack('scripts')
</body>
</html>
