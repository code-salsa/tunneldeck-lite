@extends('layouts.app')

@section('title', 'Dashboard — TunnelDeck ' . ((isset($user) && $user->isPro()) ? 'Pro' : 'Lite'))

@php
    $hasDummyTunnels = $tunnels->contains(fn($t) => (bool)($t->is_dummy ?? false));
    $isSimulationMode = ($isExploring ?? false) || $hasDummyTunnels;
    $userCurrency = $userCurrency ?? strtoupper($user->currency ?? 'INR');
    $proPriceDisplay = $proPriceDisplay ?? (($userCurrency === 'INR') ? '₹6,999' : '$99');
    $totalTunnels = $totalTunnels ?? ($tunnels ? $tunnels->count() : 0);
@endphp

@section('content')
<div class="min-h-screen bg-slate-50 text-slate-900 flex flex-row relative">

    <!-- ======================================================== -->
    <!-- SIDEBAR NAVIGATION -->
    <!-- ======================================================== -->
    <aside id="sidebar" class="bg-white border-r border-slate-200/90 flex flex-col justify-between transition-all duration-300 sticky top-0 h-screen z-30 shadow-sm shrink-0 w-64">
        <!-- TOP & MIDDLE OF SIDEBAR -->
        <div class="flex-1 flex flex-col min-h-0">
            <!-- Header Area with Logo and Toggle (Expanded) -->
            <div id="sidebar-header-expanded" class="flex items-center justify-between p-4 border-b border-slate-200/80 shrink-0">
                <div class="flex items-center gap-3 min-w-0">
                    <img src="{{ asset('images/branding-icon.png') }}?v={{ @filemtime(public_path('images/branding-icon.png')) ?: 1 }}" alt="TunnelDeck Logo" class="w-10 h-10 rounded-xl shadow-md shadow-[#100091]/20 shrink-0 object-contain">
                    <div class="min-w-0">
                        <div class="flex items-center gap-1.5">
                            <span class="font-extrabold text-base text-slate-900 tracking-tight">TunnelDeck</span>
                            <span id="brand-edition-badge" class="text-[10px] uppercase font-bold tracking-wider px-1.5 py-0.5 rounded {{ (isset($user) && $user->isPro()) ? 'bg-amber-100 text-amber-800 border border-amber-300' : 'bg-indigo-50 text-[#100091] border border-indigo-200/80' }}">{{ (isset($user) && $user->isPro()) ? 'Pro' : 'Lite' }}</span>
                        </div>
                        <p class="text-[11px] text-slate-400 font-medium truncate">Cloudflared Engine</p>
                    </div>
                </div>
                <button type="button" onclick="toggleSidebar(true)" class="p-2 rounded-xl text-slate-400 hover:text-[#100091] hover:bg-indigo-50/80 transition cursor-pointer shrink-0" title="Collapse Sidebar">
                    <!-- Half Hamburger Icon -->
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="4" y1="6" x2="20" y2="6"></line>
                        <line x1="4" y1="12" x2="13" y2="12"></line>
                        <line x1="4" y1="18" x2="17" y2="18"></line>
                    </svg>
                </button>
            </div>

            <!-- Header Area (Collapsed State) -->
            <div id="sidebar-header-collapsed" class="hidden p-3 border-b border-slate-200/80 flex justify-center shrink-0">
                <button type="button" onclick="toggleSidebar(false)" class="relative group w-11 h-11 rounded-xl flex items-center justify-center shadow-md shadow-[#100091]/20 cursor-pointer overflow-hidden transition-all duration-200" title="Expand Sidebar">
                    <div class="transition-all duration-200 group-hover:opacity-0 group-hover:scale-75 flex items-center justify-center">
                        <img src="{{ asset('images/branding-icon.png') }}?v={{ @filemtime(public_path('images/branding-icon.png')) ?: 1 }}" alt="TunnelDeck Logo" class="w-10 h-10 rounded-xl object-contain">
                    </div>
                    <div class="absolute inset-0 flex items-center justify-center opacity-0 group-hover:opacity-100 group-hover:scale-100 transition-all duration-200 bg-[#0b0068] text-white rounded-xl">
                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="4" y1="6" x2="20" y2="6"></line>
                            <line x1="4" y1="12" x2="13" y2="12"></line>
                            <line x1="4" y1="18" x2="17" y2="18"></line>
                        </svg>
                    </div>
                </button>
            </div>

            <!-- Middle Section: Menu Navigation -->
            <nav class="flex-1 overflow-y-auto p-3 space-y-1.5">
                <button type="button" onclick="switchTab('dashboard')" id="nav-btn-dashboard" class="w-full flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-semibold transition cursor-pointer text-white bg-[#100091] shadow-sm shadow-[#100091]/20">
                    <i data-lucide="layout-dashboard" class="w-4 h-4 shrink-0"></i>
                    <span class="nav-label truncate">Dashboard</span>
                </button>

                <button type="button" onclick="switchTab('tunnels')" id="nav-btn-tunnels" class="w-full flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-semibold transition cursor-pointer text-slate-600 hover:text-slate-900 hover:bg-slate-100/80">
                    <i data-lucide="server" class="w-4 h-4 shrink-0"></i>
                    <span class="nav-label truncate">Tunnels</span>
                </button>

                @if(view()->exists('partials.domains_tab'))
                <button type="button" onclick="switchTab('domains')" id="nav-btn-domains" class="w-full flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-semibold transition cursor-pointer text-slate-600 hover:text-slate-900 hover:bg-slate-100/80">
                    <i data-lucide="globe" class="w-4 h-4 shrink-0"></i>
                    <span class="nav-label truncate">Domains</span>
                </button>
                @endif

                <button type="button" onclick="switchTab('settings')" id="nav-btn-settings" class="w-full flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-semibold transition cursor-pointer text-slate-600 hover:text-slate-900 hover:bg-slate-100/80">
                    <i data-lucide="settings" class="w-4 h-4 shrink-0"></i>
                    <span class="nav-label truncate">Settings</span>
                </button>
            </nav>
        </div>

        <!-- BOTTOM OF SIDEBAR (FIXED AT BOTTOM ABOVE USER CARD) -->
        <div class="p-3 border-t border-slate-200/80 shrink-0 space-y-3">
            <!-- SIDEBAR PROMO CARDS (UPGRADE TO PRO & BUY ME A COFFEE) -->
            <div id="sidebar-cards-container" class="space-y-3 nav-label {{ (strtolower($user->package ?? 'lite') !== 'lite') ? 'hidden' : '' }}">
                <!-- Card 1: UPGRADE TO PRO -->
                <div id="sidebar-pro-card" class="p-3.5 rounded-2xl bg-gradient-to-br from-indigo-950 via-[#100091] to-purple-900 text-white shadow-md space-y-2.5 border border-indigo-500/20 {{ !$user->is_onboarded ? 'opacity-60' : '' }} transition-all duration-300">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-1.5">
                            <i data-lucide="zap" class="w-3.5 h-3.5 text-amber-300 fill-amber-300"></i>
                            <span class="text-[10px] font-black uppercase tracking-wider text-indigo-100">UPGRADE TO PRO</span>
                        </div>
                        <span id="sidebar-pro-price-badge" class="px-2 py-0.5 rounded-full text-[10px] font-black bg-amber-400 text-amber-950 shadow-2xs">
                            {{ $proPriceDisplay }}
                        </span>
                    </div>
                    <ul class="text-[11px] space-y-1 text-indigo-100/90 font-medium">
                        <li class="flex items-center gap-1.5">
                            <i data-lucide="check-circle-2" class="w-3 h-3 text-emerald-400 shrink-0"></i>
                            <span>Unlimited tunnels</span>
                        </li>
                        <li class="flex items-center gap-1.5">
                            <i data-lucide="check-circle-2" class="w-3 h-3 text-emerald-400 shrink-0"></i>
                            <span>Unlimited custom domains</span>
                        </li>
                    </ul>
                    <button type="button" id="btn-sidebar-upgrade-pro" onclick="{{ !$user->is_onboarded ? "openModal('onboarding-modal')" : "openProPaymentCheckout(this)" }}" class="w-full py-2 px-3 rounded-xl bg-amber-400 hover:bg-amber-300 text-amber-950 text-xs font-black transition flex items-center justify-center gap-1.5 shadow-sm cursor-pointer" title="{{ !$user->is_onboarded ? 'Complete onboarding first to upgrade' : 'Upgrade to Pro' }}">
                        @if(!$user->is_onboarded)
                            <i data-lucide="sparkles" class="w-3.5 h-3.5 text-amber-900"></i>
                            <span id="sidebar-pro-btn-text">Complete Onboarding</span>
                        @else
                            <i data-lucide="zap" class="w-3.5 h-3.5 fill-current"></i>
                            <span id="sidebar-pro-btn-text">Upgrade to Pro Now</span>
                        @endif
                    </button>
                </div>

                <!-- Card 2: BUY ME A COFFEE -->
                <div id="sidebar-coffee-card" class="p-3 rounded-2xl bg-amber-50/70 border border-amber-200/80 text-slate-800 space-y-2 {{ !$user->is_onboarded ? 'opacity-60' : '' }} transition-all duration-300">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-1.5 text-amber-900 font-extrabold text-xs">
                            <i data-lucide="coffee" class="w-3.5 h-3.5 text-amber-600"></i>
                            <span>Buy me a coffee</span>
                        </div>
                        <span class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-amber-200/80 text-amber-900">Tip ☕</span>
                    </div>
                    <p id="sidebar-coffee-message-text" class="text-[11px] text-slate-600 leading-tight">
                        Enjoying TunnelDeck Lite? Support development with a small coffee!
                    </p>
                    <div class="grid grid-cols-3 gap-1.5">
                        <button type="button" {{ !$user->is_onboarded ? 'disabled' : '' }} onclick="selectCoffeeAmount(100, this)" class="coffee-amount-btn py-1 px-1.5 rounded-lg text-[11px] font-bold border border-amber-200 bg-white text-slate-700 hover:border-amber-400 hover:bg-amber-100/50 transition {{ !$user->is_onboarded ? 'cursor-not-allowed opacity-50' : 'cursor-pointer' }}">
                            ₹100
                        </button>
                        <button type="button" {{ !$user->is_onboarded ? 'disabled' : '' }} onclick="selectCoffeeAmount(250, this)" class="coffee-amount-btn py-1 px-1.5 rounded-lg text-[11px] font-bold border border-amber-300 bg-amber-100/70 text-amber-900 hover:bg-amber-100 transition {{ !$user->is_onboarded ? 'cursor-not-allowed opacity-50' : 'cursor-pointer' }}">
                            ₹250
                        </button>
                        <button type="button" {{ !$user->is_onboarded ? 'disabled' : '' }} onclick="selectCoffeeAmount(500, this)" class="coffee-amount-btn py-1 px-1.5 rounded-lg text-[11px] font-bold border border-amber-200 bg-white text-slate-700 hover:border-amber-400 hover:bg-amber-100/50 transition {{ !$user->is_onboarded ? 'cursor-not-allowed opacity-50' : 'cursor-pointer' }}">
                            ₹500
                        </button>
                    </div>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-2.5 flex items-center text-xs font-bold text-slate-500">₹</span>
                        <input type="number" id="sidebar-coffee-amount" value="250" min="10" step="10" {{ !$user->is_onboarded ? 'disabled' : '' }} class="w-full pl-6 pr-2.5 py-1.5 bg-white border border-amber-200 rounded-lg text-xs font-bold text-slate-900 focus:outline-none focus:ring-1 focus:ring-amber-400 {{ !$user->is_onboarded ? 'cursor-not-allowed opacity-50' : '' }}">
                    </div>
                    <input type="text" id="sidebar-coffee-message" placeholder="Leave a friendly note (optional)" {{ !$user->is_onboarded ? 'disabled' : '' }} class="w-full px-2.5 py-1 bg-white border border-amber-200 rounded-lg text-[11px] text-slate-800 placeholder-slate-400 focus:outline-none {{ !$user->is_onboarded ? 'cursor-not-allowed opacity-50' : '' }}">
                    <button type="button" id="btn-sidebar-donate-coffee" onclick="{{ !$user->is_onboarded ? "openModal('onboarding-modal')" : "openCoffeePaymentCheckout(this)" }}" class="w-full py-1.5 px-3 rounded-xl bg-gradient-to-r from-amber-600 to-amber-500 hover:from-amber-700 hover:to-amber-600 text-white text-xs font-bold transition flex items-center justify-center gap-1.5 shadow-sm cursor-pointer" title="{{ !$user->is_onboarded ? 'Complete onboarding first to donate' : 'Donate Coffee' }}">
                        @if(!$user->is_onboarded)
                            <i data-lucide="sparkles" class="w-3.5 h-3.5"></i>
                            <span id="sidebar-coffee-btn-text">Complete Onboarding</span>
                        @else
                            <i data-lucide="heart" class="w-3.5 h-3.5 fill-current"></i>
                            <span id="sidebar-coffee-btn-text">Donate Coffee</span>
                        @endif
                    </button>
                </div>
            </div>
            <!-- User Info Card with Signout in Same Row -->
            <div id="sidebar-user-card" data-user-name="{{ $user->name }}" data-user-email="{{ $user->email }}" class="p-2 rounded-xl bg-slate-50 border border-slate-200/70 flex items-center justify-between gap-2">
                <div class="flex items-center gap-2.5 min-w-0">
                    <div class="w-8 h-8 rounded-lg bg-indigo-100 text-[#100091] flex items-center justify-center font-bold text-xs shrink-0" title="{{ $user->name }} ({{ $user->email }})">
                        {{ strtoupper(substr($user->name, 0, 1)) }}
                    </div>
                    <div class="min-w-0 nav-label">
                        <div class="flex items-center gap-1.5">
                            <p class="text-xs font-bold text-slate-800 truncate leading-tight">{{ $user->name }}</p>
                            <span id="sidebar-user-package" class="px-1.5 py-0.5 rounded text-[9px] font-extrabold uppercase {{ strtolower($user->package ?? 'lite') === 'pro' ? 'bg-amber-100 text-amber-800 border border-amber-300' : (strtolower($user->package ?? 'lite') === 'enterprise' ? 'bg-purple-100 text-purple-800 border border-purple-300' : 'bg-indigo-50 text-indigo-700 border border-indigo-200') }}">
                                {{ strtoupper($user->package ?? 'lite') }}
                            </span>
                        </div>
                        <p class="text-[10px] text-slate-400 font-medium truncate leading-tight">{{ $user->email }}</p>
                    </div>
                </div>

                <!-- Sign Out Button (Icon Only) -->
                <form id="logout-form" method="POST" action="{{ route('logout') }}" class="shrink-0 m-0">
                    @csrf
                    <button type="submit" class="p-1.5 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition cursor-pointer flex items-center justify-center" title="Sign Out">
                        <i data-lucide="log-out" class="w-4 h-4"></i>
                    </button>
                </form>
            </div>
        </div>
    </aside>

    <!-- ======================================================== -->
    <!-- MAIN CONTENT WRAPPER -->
    <!-- ======================================================== -->
    <div class="flex-1 flex flex-col min-w-0">
        <!-- TOP NAVBAR -->
        <header class="bg-white border-b border-slate-200/80 sticky top-0 z-20 px-6 py-3.5 flex items-center justify-between shadow-2xs">
            <div class="flex items-center gap-3">
                <h1 id="topbar-title" class="text-base font-extrabold text-slate-900 tracking-tight">Dashboard Overview</h1>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                    <span>SQLite Connected</span>
                </span>
            </div>

            <div class="flex items-center gap-3">
                <button type="button" onclick="openAddTunnelModal()" class="px-4 py-2 rounded-xl bg-[#100091] hover:bg-[#0b0068] text-white text-xs font-semibold shadow-md shadow-[#100091]/25 transition flex items-center gap-1.5 cursor-pointer shrink-0">
                    <i data-lucide="plus" class="w-4 h-4"></i>
                    <span>New Tunnel</span>
                </button>
            </div>
        </header>

        <!-- PAGE CONTENT CONTAINER -->
        <main class="p-6 flex-1 overflow-x-hidden">

            <!-- ======================================================== -->
            <!-- CONTINUATION OF ONBOARDING TOP BAR -->
            <!-- Shown if user exploring or onboarding not yet completed -->
            <!-- ======================================================== -->
            @if(!$user->is_onboarded)
            <div id="continue-onboarding-bar" class="p-3.5 rounded-2xl bg-gradient-to-r from-amber-500 via-orange-500 to-[#100091] text-white shadow-sm flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 mb-6">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-xl bg-white/20 backdrop-blur-xs flex items-center justify-center shrink-0">
                        <i data-lucide="sparkles" class="w-4 h-4 text-amber-200"></i>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-black uppercase tracking-wider text-amber-200">EXPLORING MODE</span>
                            <span class="text-xs font-medium text-white/95">You are exploring TunnelDeck in preview mode.</span>
                        </div>
                        <p class="text-[11px] text-white/80">Complete your account setup and register your license to unlock permanent credentials.</p>
                    </div>
                </div>
                <button type="button" onclick="openModal('onboarding-modal')" class="px-4 py-2 rounded-xl bg-white text-slate-900 hover:bg-slate-100 text-xs font-black shadow-sm transition flex items-center gap-1.5 cursor-pointer shrink-0">
                    <i data-lucide="sparkles" class="w-3.5 h-3.5 text-[#100091]"></i>
                    <span>Continue Onboarding &rarr;</span>
                </button>
            </div>
            @endif

            <!-- ======================================================== -->
            <!-- 1. DASHBOARD TAB CONTENT -->
            <!-- ======================================================== -->
            <div id="tab-content-dashboard" class="space-y-6">
                <!-- 4 METRICS CARDS -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <!-- Total Tunnels -->
                    <div class="bg-white border border-slate-200/90 rounded-2xl p-5 shadow-2xs space-y-2">
                        <div class="flex items-center justify-between text-slate-400">
                            <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Total Tunnels</span>
                            <div class="w-8 h-8 rounded-xl bg-indigo-50 text-[#100091] flex items-center justify-center">
                                <i data-lucide="server" class="w-4 h-4"></i>
                            </div>
                        </div>
                        <div class="flex items-baseline justify-between">
                            <span id="metric-total" class="text-2xl font-black text-slate-900">{{ $metrics['total'] }}</span>
                            <span class="text-xs text-slate-400">Registered</span>
                        </div>
                    </div>

                    <!-- Active Tunnels -->
                    <div class="bg-white border border-slate-200/90 rounded-2xl p-5 shadow-2xs space-y-2">
                        <div class="flex items-center justify-between text-slate-400">
                            <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Active Tunnels</span>
                            <div class="w-8 h-8 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
                                <i data-lucide="radio" class="w-4 h-4"></i>
                            </div>
                        </div>
                        <div class="flex items-baseline justify-between">
                            <span id="metric-active" class="text-2xl font-black text-emerald-600">{{ $metrics['active'] }}</span>
                            <span class="text-xs text-emerald-600 font-semibold flex items-center gap-1">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span> Running
                            </span>
                        </div>
                    </div>

                    <!-- Paused Tunnels -->
                    <div class="bg-white border border-slate-200/90 rounded-2xl p-5 shadow-2xs space-y-2">
                        <div class="flex items-center justify-between text-slate-400">
                            <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Paused Tunnels</span>
                            <div class="w-8 h-8 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center">
                                <i data-lucide="pause" class="w-4 h-4"></i>
                            </div>
                        </div>
                        <div class="flex items-baseline justify-between">
                            <span id="metric-paused" class="text-2xl font-black text-slate-700">{{ $metrics['paused'] }}</span>
                            <span class="text-xs text-slate-400">Offline</span>
                        </div>
                    </div>

                    <!-- Total Requests -->
                    <div class="bg-white border border-slate-200/90 rounded-2xl p-5 shadow-2xs space-y-2">
                        <div class="flex items-center justify-between text-slate-400">
                            <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Total Requests</span>
                            <div class="w-8 h-8 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center">
                                <i data-lucide="activity" class="w-4 h-4"></i>
                            </div>
                        </div>
                        <div class="flex items-baseline justify-between">
                            <span id="metric-requests" class="text-2xl font-black text-slate-900">{{ $metrics['total_requests'] }}</span>
                            <span class="text-xs text-slate-400 font-mono">Edge Hits</span>
                        </div>
                    </div>
                </div>

                <!-- SIDE-BY-SIDE CARDS: Active Tunnels Overview (Left) + Recent Activity Stream (Right) -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">
                    <!-- Active Tunnels Quick Overview -->
                    <div class="bg-white border border-slate-200/90 rounded-2xl overflow-hidden shadow-2xs">
                        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <i data-lucide="server" class="w-4 h-4 text-[#100091]"></i>
                                <h3 class="font-extrabold text-slate-900 text-sm">Active Tunnels Overview</h3>
                            </div>
                            <button type="button" onclick="switchTab('tunnels')" class="text-xs font-semibold text-[#100091] hover:underline cursor-pointer">
                                View All Tunnels &rarr;
                            </button>
                        </div>

                        <div id="dashboard-overview-tunnels" class="divide-y divide-slate-100">
                            @forelse($tunnels->take(4) as $tunnel)
                                <div class="p-4 sm:p-5 flex flex-col md:flex-row md:items-center justify-between gap-3 hover:bg-slate-50/70 transition">
                                    <div class="space-y-1">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <span class="font-bold text-slate-900 text-sm">{{ $tunnel->name }}</span>
                                            @if($tunnel->is_dummy)
                                                <span class="px-2 py-0.5 rounded text-[10px] font-extrabold uppercase bg-amber-100 text-amber-800 border border-amber-300">DEMO DATA</span>
                                            @endif
                                            @if($tunnel->status === 'running')
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                                    Running
                                                </span>
                                            @else
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-slate-100 text-slate-600 border border-slate-200">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
                                                    Stopped
                                                </span>
                                            @endif
                                        </div>
                                        <div class="flex items-center gap-2 text-xs">
                                            <span class="font-mono text-slate-600 bg-slate-100 px-2 py-0.5 rounded">Port {{ $tunnel->port }}</span>
                                            @if($tunnel->public_url)
                                                <span class="font-mono text-[#100091] bg-indigo-50 px-2 py-0.5 rounded truncate max-w-xs">{{ $tunnel->public_url }}</span>
                                            @endif
                                        </div>
                                        @if($tunnel->is_dummy && $tunnel->status === 'running')
                                            @php
                                                $rem = $tunnel->demo_remaining_seconds ?? 60;
                                                $timerText = sprintf('%02d:%02d', floor($rem / 60), $rem % 60);
                                            @endphp
                                            <div class="mt-1 flex items-center gap-1.5 text-[11px] font-bold text-amber-800 bg-amber-50 px-2 py-0.5 rounded-full border border-amber-200 w-fit">
                                                <i data-lucide="timer" class="w-3 h-3 text-amber-600 animate-pulse"></i>
                                                <span>Auto stop in <span class="font-mono text-amber-900" id="timer-dummy-dash-{{ $tunnel->id }}">{{ $timerText }}</span></span>
                                            </div>
                                        @endif
                                    </div>

                                    <div>
                                        @if($tunnel->status === 'running')
                                            <button type="button" onclick="stopTunnel({{ $tunnel->id }})" class="px-3.5 py-1.5 rounded-lg text-xs font-semibold border bg-rose-50 hover:bg-rose-100 text-rose-700 border-rose-200 transition cursor-pointer">
                                                Stop
                                            </button>
                                        @else
                                            <button type="button" onclick="openStartRoutingModal({{ $tunnel->id }})" class="px-3.5 py-1.5 rounded-lg text-xs font-semibold border bg-[#15803d] hover:bg-[#166534] text-white transition cursor-pointer shadow-2xs">
                                                Start
                                            </button>
                                        @endif
                                    </div>
                                </div>
                            @empty
                                <div class="py-12 text-center text-slate-500 text-sm">
                                    <p>No tunnels registered yet.</p>
                                    <button type="button" onclick="openAddTunnelModal()" class="mt-2 text-[#100091] font-semibold hover:underline text-xs">
                                        + Create your first tunnel
                                    </button>
                                </div>
                            @endforelse
                        </div>
                    </div>

                    <!-- Recent Activity Stream -->
                    <div class="bg-white border border-slate-200/90 rounded-2xl p-6 shadow-2xs space-y-4">
                        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                            <h4 class="text-sm font-bold text-slate-900 flex items-center gap-2">
                                <i data-lucide="activity" class="w-4 h-4 text-[#100091]"></i>
                                <span>Recent Activity Stream</span>
                            </h4>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-indigo-50 text-[#100091] border border-indigo-100">Live Log</span>
                        </div>

                        <div class="space-y-2.5">
                            @forelse($activities->take(6) as $act)
                                <div class="text-xs flex items-center justify-between p-2.5 rounded-xl bg-slate-50 border border-slate-100 text-slate-700">
                                    <div class="flex items-center gap-2 min-w-0">
                                        <span class="w-1.5 h-1.5 rounded-full bg-[#100091] shrink-0"></span>
                                        <span class="truncate">{{ $act->description }}</span>
                                    </div>
                                    <span class="text-[11px] text-slate-400 font-mono shrink-0 ml-2">{{ $act->created_at ? $act->created_at->format('H:i:s') : now()->format('H:i:s') }}</span>
                                </div>
                            @empty
                                <div class="text-xs flex items-center justify-between p-2.5 rounded-xl bg-slate-50 border border-slate-100 text-slate-700">
                                    <div class="flex items-center gap-2">
                                        <span class="w-1.5 h-1.5 rounded-full bg-[#100091]"></span>
                                        <span>TunnelDeck Lite Initialized with Cloudflare Tunneling Engine</span>
                                    </div>
                                    <span class="text-[11px] text-slate-400 font-mono">{{ now()->format('H:i:s') }}</span>
                                </div>
                            @endforelse
                        </div>

                        <div class="pt-3 border-t border-slate-100 flex items-center justify-between text-[11px] text-slate-500">
                            <span class="flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                                <span>SQLite Engine v3 &bull; Watchdog Active</span>
                            </span>
                            <span class="font-mono text-slate-400">Port Engine: 127.0.0.1</span>
                        </div>
                    </div>
                </div>

                <!-- ======================================================== -->
                <!-- SECTION A: OVERVIEW & TRADITIONAL CLI VS TUNNELDECK -->
                <!-- ======================================================== -->
                <div class="grid grid-cols-1 xl:grid-cols-12 gap-6 items-stretch">
                    <!-- Left: Overview of TunnelDeck & Architecture (7 cols) -->
                    <div class="xl:col-span-7 bg-white border border-slate-200/90 rounded-2xl p-6 shadow-2xs flex flex-col justify-between space-y-5">
                        <div>
                            <div class="flex items-center gap-3 mb-3">
                                <div class="w-10 h-10 rounded-xl bg-indigo-50 border border-indigo-100 flex items-center justify-center text-[#100091] shadow-2xs">
                                    <i data-lucide="cpu" class="w-5 h-5"></i>
                                </div>
                                <div>
                                    <div class="flex items-center gap-2">
                                        <h3 class="font-extrabold text-slate-900 text-base tracking-tight">Overview of TunnelDeck</h3>
                                        <span class="px-2 py-0.5 rounded text-[10px] font-extrabold uppercase bg-emerald-50 text-emerald-700 border border-emerald-200">Modern Architecture</span>
                                    </div>
                                    <p class="text-xs text-slate-500">Next-generation visual edge tunneling cockpit for developers and teams</p>
                                </div>
                            </div>

                            <p class="text-xs text-slate-600 leading-relaxed">
                                TunnelDeck is a dedicated desktop control center that securely exposes local development servers (<code class="px-1.5 py-0.5 rounded bg-slate-100 text-slate-800 font-mono text-[11px]">localhost:3000</code>, <code class="px-1.5 py-0.5 rounded bg-slate-100 text-slate-800 font-mono text-[11px]">localhost:8000</code>, Docker containers, and microservices) to the global internet via Cloudflare's Anycast edge backbone in milliseconds.
                            </p>
                        </div>

                        <!-- 3 Architecture Pillars -->
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-2">
                            <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-100 space-y-1">
                                <div class="flex items-center gap-1.5 text-slate-800 font-bold text-xs">
                                    <i data-lucide="shield-check" class="w-4 h-4 text-emerald-600 shrink-0"></i>
                                    <span>Zero-Trust Outbound</span>
                                </div>
                                <p class="text-[11px] text-slate-500 leading-normal">
                                    Outbound-only QUIC sockets mean zero opened firewall ports and no risky router NAT forwarding.
                                </p>
                            </div>

                            <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-100 space-y-1">
                                <div class="flex items-center gap-1.5 text-slate-800 font-bold text-xs">
                                    <i data-lucide="database" class="w-4 h-4 text-[#100091] shrink-0"></i>
                                    <span>SQLite Persistence</span>
                                </div>
                                <p class="text-[11px] text-slate-500 leading-normal">
                                    Configurations, named ports, and metadata remain saved across laptop reboots and OS updates.
                                </p>
                            </div>

                            <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-100 space-y-1">
                                <div class="flex items-center gap-1.5 text-slate-800 font-bold text-xs">
                                    <i data-lucide="refresh-cw" class="w-4 h-4 text-amber-600 shrink-0"></i>
                                    <span>Process Watchdog</span>
                                </div>
                                <p class="text-[11px] text-slate-500 leading-normal">
                                    Supervises daemon processes, instantly detecting dropped connections and auto-restarting tunnels.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Right: Traditional Command-Line vs TunnelDeck GUI (5 cols) -->
                    <div class="xl:col-span-5 bg-gradient-to-br from-slate-900 to-indigo-950 text-white rounded-2xl p-6 shadow-md flex flex-col justify-between space-y-4">
                        <div>
                            <div class="flex items-center justify-between mb-3 border-b border-white/10 pb-3">
                                <div class="flex items-center gap-2">
                                    <i data-lucide="terminal" class="w-4 h-4 text-rose-400"></i>
                                    <span class="text-xs font-bold text-slate-300 uppercase tracking-wider">Traditional CLI</span>
                                </div>
                                <div class="flex items-center gap-1.5 text-xs font-extrabold text-amber-300">
                                    <span>VS</span>
                                    <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                                </div>
                                <div class="flex items-center gap-2">
                                    <i data-lucide="layout" class="w-4 h-4 text-emerald-400"></i>
                                    <span class="text-xs font-bold text-emerald-300 uppercase tracking-wider">TunnelDeck GUI</span>
                                </div>
                            </div>

                            <p class="text-xs text-slate-300 leading-relaxed mb-3">
                                Replace brittle shell scripts, multiplexed terminal windows, and opaque log dumps with an elegant control deck:
                            </p>

                            <div class="space-y-2.5 text-xs">
                                <div class="flex items-start gap-2.5 p-2 rounded-xl bg-white/5 border border-white/10">
                                    <i data-lucide="x-circle" class="w-4 h-4 text-rose-400 shrink-0 mt-0.5"></i>
                                    <div class="text-slate-300 text-[11px] leading-snug">
                                        <strong class="text-white">CLI:</strong> Closing terminal tabs accidentally terminates active webhook connections.
                                    </div>
                                </div>

                                <div class="flex items-start gap-2.5 p-2 rounded-xl bg-white/5 border border-white/10">
                                    <i data-lucide="check-circle-2" class="w-4 h-4 text-emerald-400 shrink-0 mt-0.5"></i>
                                    <div class="text-slate-300 text-[11px] leading-snug">
                                        <strong class="text-emerald-300">TunnelDeck:</strong> 1-Click visual Start/Stop running quietly as background system daemons.
                                    </div>
                                </div>

                                <div class="flex items-start gap-2.5 p-2 rounded-xl bg-white/5 border border-white/10">
                                    <i data-lucide="check-circle-2" class="w-4 h-4 text-emerald-400 shrink-0 mt-0.5"></i>
                                    <div class="text-slate-300 text-[11px] leading-snug">
                                        <strong class="text-emerald-300">Zero Memorization:</strong> Never re-type long command flags like <code class="text-amber-200 font-mono">cloudflared tunnel --url...</code> again.
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="pt-3 border-t border-white/10 flex items-center justify-between text-[11px] text-slate-400">
                            <span>Clean workstation workflow</span>
                            <span class="text-emerald-400 font-semibold flex items-center gap-1">
                                <i data-lucide="sparkles" class="w-3 h-3"></i> Zero terminal clutter
                            </span>
                        </div>
                    </div>
                </div>

                <!-- ======================================================== -->
                <!-- SECTION B: CORE FEATURES OF TUNNELING -->
                <!-- ======================================================== -->
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <i data-lucide="sparkles" class="w-4 h-4 text-[#100091]"></i>
                            <h3 class="font-extrabold text-slate-900 text-sm tracking-tight">Core Features of Edge Tunneling</h3>
                        </div>
                        <span class="text-xs text-slate-400 font-medium">Built for modern developer workflows</span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        <!-- Feature 1: Webhook Relay -->
                        <div class="bg-white border border-slate-200/90 rounded-2xl p-5 shadow-2xs space-y-2.5 hover:border-indigo-200 transition">
                            <div class="w-9 h-9 rounded-xl bg-indigo-50 text-[#100091] flex items-center justify-center">
                                <i data-lucide="webhook" class="w-4 h-4"></i>
                            </div>
                            <h4 class="font-bold text-slate-900 text-xs">Local Webhook Relay</h4>
                            <p class="text-[11px] text-slate-500 leading-relaxed">
                                Receive live events from Stripe, Razorpay, GitHub, Shopify, PayPal, and Twilio directly into localhost with instant request reflection.
                            </p>
                        </div>

                        <!-- Feature 2: Mobile & Client Previews -->
                        <div class="bg-white border border-slate-200/90 rounded-2xl p-5 shadow-2xs space-y-2.5 hover:border-emerald-200 transition">
                            <div class="w-9 h-9 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
                                <i data-lucide="smartphone" class="w-4 h-4"></i>
                            </div>
                            <h4 class="font-bold text-slate-900 text-xs">Mobile & Client Previews</h4>
                            <p class="text-[11px] text-slate-500 leading-relaxed">
                                Share a live public URL with clients or test your responsive web app on real iOS and Android smartphones over cellular networks.
                            </p>
                        </div>

                        <!-- Feature 3: Auto Edge SSL/TLS -->
                        <div class="bg-white border border-slate-200/90 rounded-2xl p-5 shadow-2xs space-y-2.5 hover:border-blue-200 transition">
                            <div class="w-9 h-9 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center">
                                <i data-lucide="lock" class="w-4 h-4"></i>
                            </div>
                            <h4 class="font-bold text-slate-900 text-xs">Automated Edge HTTPS</h4>
                            <p class="text-[11px] text-slate-500 leading-relaxed">
                                Zero certificate configuration. Cloudflare automatically terminates modern TLS 1.3 encryption with HTTP/2 and HTTP/3 QUIC speeds.
                            </p>
                        </div>

                        <!-- Feature 4: NAT & Firewall Bypass -->
                        <div class="bg-white border border-slate-200/90 rounded-2xl p-5 shadow-2xs space-y-2.5 hover:border-amber-200 transition">
                            <div class="w-9 h-9 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center">
                                <i data-lucide="shield" class="w-4 h-4"></i>
                            </div>
                            <h4 class="font-bold text-slate-900 text-xs">Zero Firewall Openings</h4>
                            <p class="text-[11px] text-slate-500 leading-relaxed">
                                Works behind Carrier-Grade NAT (CGNAT), corporate VPNs, and mobile hotspots. No open router ports, port forwarding, or public IP exposure.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- ======================================================== -->
                <!-- SECTION C: LITE VS PRO COMPARISON & UPGRADE PROMOTION -->
                <!-- ======================================================== -->
                <div class="bg-gradient-to-br from-indigo-950 via-[#100091] to-slate-900 text-white rounded-3xl p-6 sm:p-8 shadow-xl relative overflow-hidden">
                    <!-- Background subtle ambient glow -->
                    <div class="absolute -right-20 -bottom-20 w-80 h-80 rounded-full bg-amber-500/10 blur-3xl pointer-events-none"></div>
                    <div class="absolute -left-20 -top-20 w-80 h-80 rounded-full bg-indigo-500/20 blur-3xl pointer-events-none"></div>

                    <div class="relative z-10 space-y-6">
                        <!-- Top Header & Price Row -->
                        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-white/10 pb-6">
                            <div>
                                <div class="flex items-center gap-2 mb-1.5">
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-amber-400 text-amber-950">
                                        EDITION COMPARISON
                                    </span>
                                    <span class="text-xs text-indigo-200 font-medium">Choose the tier that fits your engineering scope</span>
                                </div>
                                <h3 class="text-2xl font-black text-white tracking-tight">TunnelDeck Lite vs. TunnelDeck Pro</h3>
                                <p class="text-xs text-indigo-200/90 mt-1 max-w-xl">
                                    Lite gives you 5 fast tunnels with no custom domains. Pro unleashes unlimited tunnels, unlimited custom domains, and named persistent credentials.
                                </p>
                            </div>

                            <!-- Price & Interactive Checkout Button -->
                            <div class="flex flex-col items-start md:items-end gap-2 shrink-0">
                                <div class="flex items-baseline gap-1.5">
                                    <span class="text-3xl font-black text-amber-300" id="comparison-pro-price">{{ $proPriceDisplay }}</span>
                                    <span class="text-xs text-indigo-200 font-medium" id="comparison-pro-validity">/ per year</span>
                                </div>
                                <button type="button" onclick="openProPaymentCheckout(this)" class="px-5 py-2.5 rounded-xl bg-amber-400 hover:bg-amber-300 text-amber-950 font-black text-xs shadow-lg shadow-amber-400/25 transition flex items-center gap-2 cursor-pointer transform hover:scale-[1.02] active:scale-[0.98]">
                                    <i data-lucide="zap" class="w-4 h-4 fill-current"></i>
                                    <span>Upgrade to Pro Now</span>
                                </button>
                            </div>
                        </div>

                        <!-- Comparison Columns Grid -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Lite Edition Features -->
                            <div class="p-5 rounded-2xl bg-white/5 border border-white/10 space-y-3.5 backdrop-blur-xs">
                                <div class="flex items-center justify-between border-b border-white/10 pb-2.5">
                                    <div class="flex items-center gap-2">
                                        <span class="font-extrabold text-sm text-white">TunnelDeck Lite</span>
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-white/10 text-indigo-200">Current</span>
                                    </div>
                                    <span class="text-xs font-semibold text-emerald-400">Active</span>
                                </div>
                                <ul class="text-xs space-y-2 text-indigo-100">
                                    <li class="flex items-start gap-2">
                                        <i data-lucide="check" class="w-4 h-4 text-emerald-400 shrink-0 mt-0.5"></i>
                                        <span><strong>5 Concurrent Tunnels:</strong> Essential routing capacity for individual projects and dev servers.</span>
                                    </li>
                                    <li class="flex items-start gap-2">
                                        <i data-lucide="check" class="w-4 h-4 text-emerald-400 shrink-0 mt-0.5"></i>
                                        <span><strong>Automated TryCloudflare Subdomains:</strong> Quick public edge links generated on demand.</span>
                                    </li>
                                    <li class="flex items-start gap-2">
                                        <i data-lucide="check" class="w-4 h-4 text-emerald-400 shrink-0 mt-0.5"></i>
                                        <span><strong>Local SQLite Persistence:</strong> Save your ports and names locally.</span>
                                    </li>
                                    <li class="flex items-start gap-2">
                                        <i data-lucide="check" class="w-4 h-4 text-emerald-400 shrink-0 mt-0.5"></i>
                                        <span><strong>Process Watchdog:</strong> Automatic background status tracking and dead process recovery.</span>
                                    </li>
                                    <li class="flex items-start gap-2 text-rose-300/90">
                                        <i data-lucide="x" class="w-4 h-4 text-rose-400 shrink-0 mt-0.5"></i>
                                        <span><strong>No Custom Domains:</strong> Custom domain binding is exclusive to Pro edition</span>
                                    </li>
                                    <li class="flex items-start gap-2 text-indigo-300/70">
                                        <i data-lucide="minus" class="w-4 h-4 text-indigo-300/50 shrink-0 mt-0.5"></i>
                                        <span>Ephemeral randomized subdomains only (change on restart)</span>
                                    </li>
                                </ul>
                            </div>

                            <!-- Pro Edition Features -->
                            <div class="p-5 rounded-2xl bg-amber-400/10 border border-amber-300/30 space-y-3.5 backdrop-blur-xs">
                                <div class="flex items-center justify-between border-b border-amber-300/20 pb-2.5">
                                    <div class="flex items-center gap-2">
                                        <span class="font-extrabold text-sm text-amber-300">TunnelDeck Pro</span>
                                        <span class="px-2 py-0.5 rounded text-[10px] font-black uppercase bg-amber-400 text-amber-950">Recommended</span>
                                    </div>
                                    <span id="comparison-pro-card-price" class="text-xs font-black text-amber-300">{{ $proPriceDisplay }}</span>
                                </div>
                                <ul class="text-xs space-y-2 text-white">
                                    <li class="flex items-start gap-2">
                                        <i data-lucide="check-circle-2" class="w-4 h-4 text-amber-300 shrink-0 mt-0.5"></i>
                                        <span><strong>Unlimited Concurrent Tunnels:</strong> Run unlimited local services, microservices, APIs &amp; workers simultaneously.</span>
                                    </li>
                                    <li class="flex items-start gap-2">
                                        <i data-lucide="check-circle-2" class="w-4 h-4 text-amber-300 shrink-0 mt-0.5"></i>
                                        <span><strong>Unlimited Custom Domains:</strong> Route unlimited tunnels to custom branded domains (<code class="bg-white/10 px-1 py-0.5 rounded text-amber-200 font-mono text-[11px]">api.yourbrand.com</code>, <code class="bg-white/10 px-1 py-0.5 rounded text-amber-200 font-mono text-[11px]">app.yourdomain.io</code>).</span>
                                    </li>
                                    <li class="flex items-start gap-2">
                                        <i data-lucide="check-circle-2" class="w-4 h-4 text-amber-300 shrink-0 mt-0.5"></i>
                                        <span><strong>Named Persistent Credentials:</strong> Fixed URLs that never rotate or break your third-party webhook subscriptions.</span>
                                    </li>
                                    <li class="flex items-start gap-2">
                                        <i data-lucide="check-circle-2" class="w-4 h-4 text-amber-300 shrink-0 mt-0.5"></i>
                                        <span><strong>Priority Anycast Routing:</strong> High-bandwidth Cloudflare edge nodes with lowest edge latency.</span>
                                    </li>
                                    <li class="flex items-start gap-2">
                                        <i data-lucide="check-circle-2" class="w-4 h-4 text-amber-300 shrink-0 mt-0.5"></i>
                                        <span><strong>Zero Cloudflare Account Limit:</strong> Run unlimited custom projects under your personal tunnel tokens.</span>
                                    </li>
                                    <li class="flex items-start gap-2">
                                        <i data-lucide="check-circle-2" class="w-4 h-4 text-amber-300 shrink-0 mt-0.5"></i>
                                        <span><strong>Instant One-Click Activation:</strong> Seamless local license upgrade via Razorpay gateway.</span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ======================================================== -->
            <!-- 2. TUNNELS TAB CONTENT -->
            <!-- ======================================================== -->
            <div id="tab-content-tunnels" class="hidden space-y-6">
                <!-- Header -->
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-6 rounded-2xl border border-slate-200/90 shadow-2xs">
                    <div>
                        <h2 class="text-xl font-extrabold text-slate-900 tracking-tight">Forwarding Tunnels Manager</h2>
                        <p class="text-sm text-slate-500 mt-1">Create and monitor persistent local-to-public Cloudflare edge tunnels</p>
                    </div>
                    <button type="button" onclick="openAddTunnelModal()" class="px-4 py-2.5 rounded-xl bg-[#100091] hover:bg-[#0b0068] text-white text-xs font-semibold shadow-md shadow-[#100091]/25 transition flex items-center gap-2 cursor-pointer shrink-0">
                        <i data-lucide="plus" class="w-4 h-4"></i>
                        <span>Add New Tunnel</span>
                    </button>
                </div>

                <!-- Simulation / Demo Banner (if applicable) -->
                @if($isSimulationMode)
                <div id="dummy-tunnels-banner" class="p-3.5 rounded-2xl bg-amber-50 border border-amber-200 text-amber-900 flex items-center justify-between gap-3">
                    <div class="flex items-center gap-2.5 text-xs">
                        <i data-lucide="info" class="w-4 h-4 text-amber-600 shrink-0"></i>
                        <span><strong>Preview Simulation Mode:</strong> Displaying mock tunnels to explore features safely. Starting a tunnel demonstrates routing steps with a 60-second auto-stop timer. Completed onboarding cleans all mock data.</span>
                    </div>
                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-amber-200 text-amber-900 shrink-0">Demo Data</span>
                </div>
                @endif

                <!-- Bulk Selection Action Bar -->
                <div id="bulk-tunnels-action-bar" class="hidden p-3.5 rounded-2xl bg-indigo-50/90 border border-indigo-200/80 flex items-center justify-between gap-3 shadow-2xs">
                    <div class="flex items-center gap-2.5 text-xs text-indigo-950 font-semibold">
                        <span class="w-2 h-2 rounded-full bg-[#100091] animate-pulse"></span>
                        <span>Selected <span id="bulk-selected-count" class="font-extrabold text-[#100091]">0</span> tunnel(s)</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button" onclick="clearTunnelSelections()" class="px-3 py-1.5 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 text-xs font-medium transition cursor-pointer">
                            Cancel Selection
                        </button>
                        <button type="button" onclick="openBulkDeleteModal()" class="px-3.5 py-1.5 rounded-lg bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold transition flex items-center gap-1.5 shadow-2xs cursor-pointer">
                            <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                            <span id="btn-bulk-delete-label">Delete Tunnels</span>
                        </button>
                    </div>
                </div>

                <!-- Tunnels Table -->
                <div class="bg-white border border-slate-200/90 rounded-2xl overflow-hidden shadow-2xs">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="border-b border-slate-200/80 bg-slate-50/70">
                                    <th class="py-3.5 pl-6 pr-2 w-12 text-left">
                                        <input type="checkbox" id="check-all-tunnels" onchange="toggleSelectAllTunnels(this.checked)" class="w-4 h-4 rounded border-slate-300 text-[#100091] focus:ring-2 focus:ring-[#100091]/30 cursor-pointer accent-[#100091]" title="Select All Tunnels">
                                    </th>
                                    <th class="py-3.5 px-6 text-[11px] font-bold text-slate-500 uppercase tracking-wider">STATUS</th>
                                    <th class="py-3.5 px-6 text-[11px] font-bold text-slate-500 uppercase tracking-wider">TUNNEL NAME</th>
                                    <th class="py-3.5 px-6 text-[11px] font-bold text-slate-500 uppercase tracking-wider">LOCAL URL</th>
                                    <th class="py-3.5 px-6 text-[11px] font-bold text-slate-500 uppercase tracking-wider">PUBLIC URL</th>
                                    <th class="py-3.5 px-6 text-[11px] font-bold text-slate-500 uppercase tracking-wider">CREATED DATE</th>
                                    <th class="py-3.5 px-6 text-[11px] font-bold text-slate-500 uppercase tracking-wider text-right">ACTIONS</th>
                                </tr>
                            </thead>
                            <tbody id="tunnels-table-body" class="divide-y divide-slate-100">
                                @include('partials.tunnels_table_rows', ['tunnels' => $tunnels->take(20)])
                            </tbody>
                        </table>
                    </div>

                    <!-- Tunnels Pagination Bar -->
                    <div id="tunnels-pagination-bar" class="px-6 py-3.5 border-t border-slate-200/80 bg-slate-50/60 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-slate-500">
                        <div id="tunnels-pagination-info" class="font-medium text-slate-600">
                            Showing <span id="tunnels-pagination-start" class="font-bold text-slate-800">{{ $totalTunnels > 0 ? 1 : 0 }}</span> to <span id="tunnels-pagination-end" class="font-bold text-slate-800">{{ min(20, $totalTunnels) }}</span> of <span id="tunnels-pagination-total" class="font-bold text-slate-800">{{ $totalTunnels }}</span> tunnels
                        </div>
                        <div class="flex items-center gap-1.5" id="tunnels-pagination-controls">
                            <!-- Numbered pagination dynamically populated by JavaScript -->
                        </div>
                    </div>
                </div>
            </div>

            <!-- ======================================================== -->
            <!-- 3. CUSTOM DOMAINS TAB CONTENT (PRO ADDON) -->
            <!-- ======================================================== -->
            @if(view()->exists('partials.domains_tab'))
                @include('partials.domains_tab')
            @endif

            <!-- ======================================================== -->
            <!-- 4. PLATFORM SETTINGS TAB CONTENT -->
            <!-- ======================================================== -->
            <div id="tab-content-settings" class="hidden space-y-6 w-full max-w-7xl">
                <div>
                    <h2 class="text-3xl font-extrabold text-slate-900 tracking-tight font-sans">Platform Settings</h2>
                    <p class="text-sm text-slate-500 mt-1">System configuration, binary paths, credentials &amp; security management</p>
                </div>

                <!-- Simulation Mode Warning Banner -->
                @if($isSimulationMode)
                <div id="settings-demo-banner" class="p-4 rounded-2xl bg-amber-50 border border-amber-300 text-amber-900 flex items-start sm:items-center justify-between gap-3 shadow-2xs">
                    <div class="flex items-start sm:items-center gap-3">
                        <div class="w-8 h-8 rounded-xl bg-amber-200/80 text-amber-900 flex items-center justify-center shrink-0 mt-0.5 sm:mt-0">
                            <i data-lucide="lock" class="w-4 h-4"></i>
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="text-xs font-black uppercase tracking-wider">Simulation Mode Active</span>
                                <span id="settings-locked-badge" class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-amber-200 text-amber-900">Read-Only</span>
                            </div>
                            <p class="text-xs text-amber-800 mt-0.5">Platform settings cannot be changed in simulation mode. Complete your onboarding to personalize credentials and manage system settings.</p>
                        </div>
                    </div>
                    <button type="button" onclick="openModal('onboarding-modal')" class="px-3.5 py-1.5 rounded-xl bg-amber-600 hover:bg-amber-700 text-white text-xs font-bold transition flex items-center gap-1.5 cursor-pointer shrink-0">
                        <span>Complete Onboarding</span>
                        <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                    </button>
                </div>
                @endif

                <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start w-full">
                    <!-- Left Column: Platform Configuration (7 cols) -->
                    <div class="lg:col-span-7 xl:col-span-7 space-y-6 w-full">
                        <div class="bg-white p-6 rounded-2xl border border-slate-200/90 shadow-2xs space-y-6">
                            <!-- License & Cloud Subscription Card -->
                            <div class="p-5 rounded-2xl border border-indigo-100 bg-gradient-to-br from-indigo-50/50 via-white to-slate-50/50 shadow-2xs space-y-4">
                                <div class="flex items-center justify-between border-b border-indigo-100/60 pb-3">
                                    <div class="flex items-center gap-2.5">
                                        <div class="w-8 h-8 rounded-xl bg-indigo-100 text-[#100091] flex items-center justify-center">
                                            <i data-lucide="shield-check" class="w-4 h-4"></i>
                                        </div>
                                        <div>
                                            <h4 class="text-xs font-bold text-slate-800 uppercase tracking-wider">License &amp; Plan Tier</h4>
                                            <p class="text-[11px] text-slate-500">Connected to licensing.tunneldeck.online</p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                            <span>Active License</span>
                                        </span>
                                    </div>
                                </div>

                                <!-- Tier Info Grid -->
                                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
                                    <div class="p-3 rounded-xl bg-white border border-slate-200/80 shadow-2xs">
                                        <span class="text-slate-400 block text-[11px] mb-1">Current Tier</span>
                                        <span id="settings-license-badge" class="inline-flex px-2 py-0.5 rounded text-xs font-extrabold uppercase {{ strtolower($user->package ?? 'lite') === 'pro' ? 'bg-amber-100 text-amber-800 border border-amber-300' : (strtolower($user->package ?? 'lite') === 'enterprise' ? 'bg-purple-100 text-purple-800 border border-purple-300' : 'bg-indigo-50 text-indigo-700 border border-indigo-200') }}">
                                            {{ strtoupper($user->package ?? 'lite') }}
                                        </span>
                                    </div>
                                    <div class="p-3 rounded-xl bg-white border border-slate-200/80 shadow-2xs">
                                        <span class="text-slate-400 block text-[11px] mb-1">Max Tunnels</span>
                                        <span id="settings-license-tunnels" class="font-extrabold text-slate-900 text-xs">{{ $user->maxTunnels() }} Concurrent</span>
                                    </div>
                                    <div class="p-3 rounded-xl bg-white border border-slate-200/80 shadow-2xs">
                                        <span class="text-slate-400 block text-[11px] mb-1">Billing Currency</span>
                                        <span id="settings-license-currency" class="font-bold text-slate-700 text-xs">{{ $user->currency ?? 'INR' }}</span>
                                    </div>
                                    <div class="p-3 rounded-xl bg-white border border-slate-200/80 shadow-2xs">
                                        <span class="text-slate-400 block text-[11px] mb-1">Last Cloud Sync</span>
                                        <span id="settings-license-synced-at" class="font-semibold text-slate-600 text-[11px]">{{ $user->license_synced_at ? $user->license_synced_at->diffForHumans() : 'Just now' }}</span>
                                    </div>
                                </div>

                                <!-- Actions Row -->
                                <div class="pt-2 flex flex-wrap items-center justify-between gap-3 border-t border-indigo-100/60">
                                    <!-- Upgrade Simulation Buttons -->
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        <span class="text-[11px] font-bold text-slate-500 mr-1">Test Upgrades:</span>
                                        <button type="button" onclick="testPlanUpgrade('lite', this)" class="px-2.5 py-1 rounded-lg border border-slate-200 hover:bg-slate-100 text-slate-700 text-[11px] font-semibold transition cursor-pointer">
                                            Lite (10)
                                        </button>
                                        <button type="button" onclick="testPlanUpgrade('pro', this)" class="px-2.5 py-1 rounded-lg border border-amber-200 bg-amber-50/60 hover:bg-amber-100 text-amber-800 text-[11px] font-semibold transition cursor-pointer">
                                            Pro (50)
                                        </button>
                                        <button type="button" onclick="testPlanUpgrade('enterprise', this)" class="px-2.5 py-1 rounded-lg border border-purple-200 bg-purple-50/60 hover:bg-purple-100 text-purple-800 text-[11px] font-semibold transition cursor-pointer">
                                            Enterprise (500)
                                        </button>
                                    </div>

                                    <div class="flex items-center gap-2">
                                        <button type="button" id="btn-sync-license" onclick="syncLicenseFromCloud(this)" class="px-3 py-1.5 rounded-xl border border-indigo-200 bg-white hover:bg-indigo-50 text-indigo-700 text-xs font-bold transition flex items-center gap-1.5 cursor-pointer shadow-2xs">
                                            <i data-lucide="refresh-cw" class="w-3.5 h-3.5"></i>
                                            <span>Sync License</span>
                                        </button>
                                        <button type="button" id="btn-test-license-flow" onclick="openLicenseDiagnosticModal()" class="px-3 py-1.5 rounded-xl bg-[#100091] hover:bg-[#0b0068] text-white text-xs font-bold transition flex items-center gap-1.5 cursor-pointer shadow-2xs">
                                            <i data-lucide="sparkles" class="w-3.5 h-3.5"></i>
                                            <span>Test Live Flow</span>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Profile Card -->
                            <div class="p-4 rounded-xl border border-slate-200 bg-slate-50/50 space-y-3">
                                <h4 class="text-xs font-bold text-slate-700 uppercase tracking-wider flex items-center gap-1.5">
                                    <i data-lucide="user" class="w-3.5 h-3.5 text-[#100091]"></i>
                                    <span>Administrator Account</span>
                                </h4>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                                    <div>
                                        <span class="text-slate-400 block mb-0.5">Account Name</span>
                                        <span class="font-semibold text-slate-900">{{ $user->name }}</span>
                                    </div>
                                    <div>
                                        <span class="text-slate-400 block mb-0.5">Primary Email</span>
                                        <span class="font-semibold text-slate-900">{{ $user->email }}</span>
                                    </div>
                                    <div>
                                        <span class="text-slate-400 block mb-0.5">Role</span>
                                        <span class="inline-flex px-2 py-0.5 rounded bg-indigo-50 text-[#100091] font-semibold text-[11px] border border-indigo-200">
                                            Super Administrator
                                        </span>
                                    </div>
                                    <div>
                                        <span class="text-slate-400 block mb-0.5">Authentication Mode</span>
                                        <span class="text-slate-700 font-medium">Direct SQLite (No OTP)</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Engine Configuration -->
                            <div class="p-4 rounded-xl border border-slate-200 bg-slate-50/50 space-y-3">
                                <h4 class="text-xs font-bold text-slate-700 uppercase tracking-wider flex items-center gap-1.5">
                                    <i data-lucide="sliders" class="w-3.5 h-3.5 text-[#100091]"></i>
                                    <span>Tunneling Engine Details</span>
                                </h4>
                                <div class="space-y-3 text-xs">
                                    <div>
                                        <span class="text-slate-400 block mb-1">Bundled Binary</span>
                                        <code class="px-2.5 py-1 rounded bg-slate-100 border border-slate-200 text-slate-800 font-mono text-[11px] block overflow-x-auto">
                                            src/bin/windows/x64/cloudflared.exe (v2026.7)
                                        </code>
                                    </div>
                                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                        <div>
                                            <span class="text-slate-400 block mb-0.5">Process Timeout</span>
                                            <span class="font-semibold text-slate-800">30 seconds</span>
                                        </div>
                                        <div>
                                            <span class="text-slate-400 block mb-0.5">Auto-Restart</span>
                                            <span class="font-semibold text-emerald-600">Enabled</span>
                                        </div>
                                        <div>
                                            <span class="text-slate-400 block mb-0.5">Max Active Tunnels</span>
                                            <span id="settings-max-tunnels" class="font-semibold text-slate-800">{{ $user->maxTunnels() }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- SQLite Database -->
                            <div class="p-4 rounded-xl border border-slate-200 bg-slate-50/50 space-y-2">
                                <h4 class="text-xs font-bold text-slate-700 uppercase tracking-wider flex items-center gap-1.5">
                                    <i data-lucide="database" class="w-3.5 h-3.5 text-[#100091]"></i>
                                    <span>Database Engine</span>
                                </h4>
                                <div class="text-xs space-y-1">
                                    <div class="flex items-center gap-2">
                                        <span class="text-slate-400">Driver:</span>
                                        <span class="font-mono font-semibold text-slate-800">SQLite v3</span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span class="text-slate-400">Storage File:</span>
                                        <span class="font-mono text-slate-700">database/database.sqlite</span>
                                    </div>
                                    <div class="flex items-center gap-2 text-emerald-600 font-medium pt-1">
                                        <i data-lucide="check-circle-2" class="w-3.5 h-3.5"></i>
                                        <span>Migrations up to date &bull; Healthy</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column: Change Password (5 cols) -->
                    <div class="lg:col-span-5 xl:col-span-5 w-full space-y-4 lg:sticky lg:top-6">
                        <div class="bg-white border border-slate-200/90 rounded-2xl shadow-2xs p-6 space-y-5 w-full">
                            <div class="flex items-center gap-3 border-b border-slate-100 pb-4">
                                <div class="w-10 h-10 rounded-xl bg-indigo-50 border border-indigo-100 flex items-center justify-center text-[#100091] shadow-2xs">
                                    <i data-lucide="key" class="w-5 h-5"></i>
                                </div>
                                <div>
                                    <h3 class="font-extrabold text-slate-900 text-sm tracking-tight">Change Password</h3>
                                    <p class="text-[11px] text-slate-400">Update your login credentials</p>
                                </div>
                            </div>

                            <!-- Locked in Simulation Mode Notice -->
                            @if($isSimulationMode)
                            <div id="settings-password-locked-notice" class="p-3.5 rounded-xl bg-amber-50 border border-amber-200 text-amber-800 text-xs flex items-center gap-2.5">
                                <i data-lucide="lock" class="w-4 h-4 text-amber-600 shrink-0"></i>
                                <span><strong>Locked in Simulation Mode:</strong> Password modifications are disabled while exploring. Complete onboarding to personalize credentials.</span>
                            </div>
                            @endif

                            <form id="settings-password-form" onsubmit="handleSaveSettingsPassword(event)" class="space-y-4">
                                <!-- Current Password Display -->
                                <div>
                                    <label class="block text-xs font-bold text-slate-700 mb-1.5">Current Password</label>
                                    <div class="relative">
                                        <input
                                            type="password"
                                            id="settings-current-password"
                                            readonly
                                            value="123456"
                                            class="w-full px-3.5 pr-10 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-mono text-slate-700 select-all cursor-default"
                                        >
                                        <button type="button" onclick="toggleInputVisibility('settings-current-password', 'current-pw-eye')" class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-slate-600 transition cursor-pointer">
                                            <i data-lucide="eye" id="current-pw-eye" class="w-4 h-4"></i>
                                        </button>
                                    </div>
                                    <p class="text-[11px] text-slate-400 mt-1">Default factory credential for initial setup</p>
                                </div>

                                <!-- New Password -->
                                <div>
                                    <label class="block text-xs font-bold text-slate-700 mb-1.5">New Password</label>
                                    <div class="relative">
                                        <input
                                            type="password"
                                            id="settings-new-password"
                                            required
                                            minlength="6"
                                            placeholder="Enter your new password"
                                            {{ $isSimulationMode ? 'disabled' : '' }}
                                            class="w-full px-3.5 pr-10 py-2.5 bg-white border border-slate-300 rounded-xl text-sm text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-[#100091]/20 focus:border-[#100091] transition disabled:bg-slate-100 disabled:cursor-not-allowed disabled:text-slate-400"
                                        >
                                        <button type="button" onclick="toggleInputVisibility('settings-new-password', 'new-pw-eye')" class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-slate-600 transition cursor-pointer">
                                            <i data-lucide="eye" id="new-pw-eye" class="w-4 h-4"></i>
                                        </button>
                                    </div>
                                </div>

                                <!-- Confirm New Password -->
                                <div>
                                    <label class="block text-xs font-bold text-slate-700 mb-1.5">Confirm New Password</label>
                                    <div class="relative">
                                        <input
                                            type="password"
                                            id="settings-confirm-password"
                                            required
                                            minlength="6"
                                            placeholder="Re-enter your new password"
                                            {{ $isSimulationMode ? 'disabled' : '' }}
                                            class="w-full px-3.5 pr-10 py-2.5 bg-white border border-slate-300 rounded-xl text-sm text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-[#100091]/20 focus:border-[#100091] transition disabled:bg-slate-100 disabled:cursor-not-allowed disabled:text-slate-400"
                                        >
                                        <button type="button" onclick="toggleInputVisibility('settings-confirm-password', 'confirm-pw-eye')" class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-slate-600 transition cursor-pointer">
                                            <i data-lucide="eye" id="confirm-pw-eye" class="w-4 h-4"></i>
                                        </button>
                                    </div>
                                </div>

                                <!-- Autofill in login page toggle -->
                                <div class="pt-1 pb-1">
                                    <label class="flex items-start gap-3 p-3 rounded-xl bg-slate-50 border border-slate-200/80 cursor-pointer select-none hover:bg-slate-100/60 transition">
                                        <div class="relative inline-flex items-center shrink-0 mt-0.5">
                                            <input type="checkbox" id="settings-autofill-toggle" checked {{ $isSimulationMode ? 'disabled' : '' }} class="sr-only peer">
                                            <div class="w-9 h-5 bg-slate-300 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-[#100091]"></div>
                                        </div>
                                        <div class="space-y-0.5 text-left">
                                            <span class="block text-xs font-bold text-slate-800">Autofill in login page password field</span>
                                            <span class="block text-[11px] text-slate-500 leading-relaxed">When enabled, your password is automatically filled when visiting the login page.</span>
                                        </div>
                                    </label>
                                </div>

                                <!-- Submit Button -->
                                <button type="submit" id="save-password-btn" {{ $isSimulationMode ? 'disabled' : '' }} class="w-full py-2.5 px-4 rounded-xl {{ $isSimulationMode ? 'bg-slate-400 cursor-not-allowed' : 'bg-[#100091] hover:bg-[#0b0068] cursor-pointer' }} text-white font-bold text-xs shadow-md shadow-[#100091]/25 transition flex items-center justify-center gap-2">
                                    @if($isSimulationMode)
                                        <i data-lucide="lock" class="w-4 h-4"></i>
                                        <span id="save-password-btn-text">Locked in Simulation Mode</span>
                                    @else
                                        <i data-lucide="save" class="w-4 h-4"></i>
                                        <span id="save-password-btn-text">Save Password</span>
                                    @endif
                                </button>
                            </form>
                        </div>

                        <!-- Right Column Bottom: Simulation Mode Notice -->
                        <div id="settings-disabled-bottom-notice" class="{{ $isSimulationMode ? '' : 'hidden' }} p-4 rounded-2xl bg-amber-50/90 border border-amber-200 text-amber-900 shadow-2xs space-y-2.5">
                            <div class="flex items-start gap-2.5 text-xs">
                                <div class="w-7 h-7 rounded-lg bg-amber-200/80 text-amber-900 flex items-center justify-center shrink-0 mt-0.5">
                                    <i data-lucide="lock" class="w-3.5 h-3.5"></i>
                                </div>
                                <div class="space-y-1">
                                    <p class="font-bold text-amber-950 text-xs leading-tight">Settings are disabled during simulation mode.</p>
                                    <p class="text-[11px] text-amber-800 leading-relaxed">Finish Onboarding to enjoy Tunneldeck with permanent credentials and full customization.</p>
                                </div>
                            </div>
                            <button type="button" onclick="openModal('onboarding-modal')" class="w-full py-2 px-3 rounded-xl bg-amber-600 hover:bg-amber-700 text-white text-xs font-bold transition flex items-center justify-center gap-1.5 shadow-2xs cursor-pointer">
                                <span>Finish Onboarding</span>
                                <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

        </main>
    </div>

    <!-- ======================================================== -->
    <!-- ALL MODALS INCLUDED AS BLADE PARTIALS -->
    <!-- ======================================================== -->
    @include('partials.modals')

    @if(!$user->is_onboarded)
        @include('partials.onboarding_modal')
    @endif

</div>
@endsection

@push('scripts')
<script>
    window.IS_SIMULATION_MODE = {{ $isSimulationMode ? 'true' : 'false' }};
    window.IS_ONBOARDED = {{ $user->is_onboarded ? 'true' : 'false' }};
    window.INITIAL_TUNNELS = @json($tunnels);
</script>
<script src="{{ asset('js/app.js') }}"></script>
@if(file_exists(public_path('js/domains.js')))
<script src="{{ asset('js/domains.js') }}"></script>
@endif
@endpush
