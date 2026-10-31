@extends('layouts.app')

@section('title', 'Dashboard — TunnelDeck Lite')

@section('content')
<div class="min-h-screen bg-slate-50 text-slate-900 flex flex-row relative">

    <!-- ======================================================== -->
    <!-- SIDEBAR NAVIGATION -->
    <!-- ======================================================== -->
    <aside id="sidebar" class="bg-white border-r border-slate-200/90 flex flex-col justify-between transition-all duration-300 sticky top-0 h-screen z-30 shadow-sm shrink-0 w-64">
        <!-- TOP OF SIDEBAR -->
        <div>
            <!-- Header Area with Logo and Toggle (Expanded) -->
            <div id="sidebar-header-expanded" class="flex items-center justify-between p-4 border-b border-slate-200/80">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="w-10 h-10 rounded-xl bg-[#100091] flex items-center justify-center shadow-md shadow-[#100091]/25 shrink-0 text-white">
                        <i data-lucide="radio" class="w-5 h-5 animate-pulse"></i>
                    </div>
                    <div class="min-w-0">
                        <div class="flex items-center gap-1.5">
                            <span class="font-extrabold text-base text-slate-900 tracking-tight">TunnelDeck</span>
                            <span class="text-[10px] uppercase font-bold tracking-wider px-1.5 py-0.5 rounded bg-indigo-50 text-[#100091] border border-indigo-200/80">Lite</span>
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
            <div id="sidebar-header-collapsed" class="hidden p-3 border-b border-slate-200/80 flex justify-center">
                <button type="button" onclick="toggleSidebar(false)" class="relative group w-11 h-11 rounded-xl bg-[#100091] flex items-center justify-center shadow-md shadow-[#100091]/25 cursor-pointer overflow-hidden transition-all duration-200 text-white" title="Expand Sidebar">
                    <div class="transition-all duration-200 group-hover:opacity-0 group-hover:scale-75 flex items-center justify-center">
                        <i data-lucide="radio" class="w-5 h-5 animate-pulse"></i>
                    </div>
                    <div class="absolute inset-0 flex items-center justify-center opacity-0 group-hover:opacity-100 group-hover:scale-100 transition-all duration-200 bg-[#0b0068] text-white">
                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="4" y1="6" x2="20" y2="6"></line>
                            <line x1="4" y1="12" x2="13" y2="12"></line>
                            <line x1="4" y1="18" x2="17" y2="18"></line>
                        </svg>
                    </div>
                </button>
            </div>

            <!-- MENU NAVIGATION -->
            <nav class="p-3 space-y-1.5">
                <button type="button" onclick="switchTab('dashboard')" id="nav-btn-dashboard" class="w-full flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-semibold transition cursor-pointer text-white bg-[#100091] shadow-sm shadow-[#100091]/20">
                    <i data-lucide="layout-dashboard" class="w-4 h-4 shrink-0"></i>
                    <span class="nav-label truncate">Dashboard</span>
                </button>

                <button type="button" onclick="switchTab('tunnels')" id="nav-btn-tunnels" class="w-full flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-semibold transition cursor-pointer text-slate-600 hover:text-slate-900 hover:bg-slate-100/80">
                    <i data-lucide="server" class="w-4 h-4 shrink-0"></i>
                    <span class="nav-label truncate">Tunnels</span>
                </button>

                <button type="button" onclick="switchTab('domains')" id="nav-btn-domains" class="w-full flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-semibold transition cursor-pointer text-slate-600 hover:text-slate-900 hover:bg-slate-100/80">
                    <i data-lucide="globe" class="w-4 h-4 shrink-0"></i>
                    <span class="nav-label truncate">Domains</span>
                </button>

                <button type="button" onclick="switchTab('settings')" id="nav-btn-settings" class="w-full flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-semibold transition cursor-pointer text-slate-600 hover:text-slate-900 hover:bg-slate-100/80">
                    <i data-lucide="settings" class="w-4 h-4 shrink-0"></i>
                    <span class="nav-label truncate">Settings</span>
                </button>
            </nav>
        </div>

        <!-- BOTTOM OF SIDEBAR -->
        <div class="p-3 border-t border-slate-200/80 space-y-2">
            <!-- User Info Card -->
            <div id="sidebar-user-card" class="p-2.5 rounded-xl bg-slate-50 border border-slate-200/70 flex items-center justify-between">
                <div class="flex items-center gap-2.5 min-w-0">
                    <div class="w-8 h-8 rounded-lg bg-indigo-100 text-[#100091] flex items-center justify-center font-bold text-xs shrink-0">
                        {{ strtoupper(substr($user->name, 0, 1)) }}
                    </div>
                    <div class="min-w-0 nav-label">
                        <p class="text-xs font-bold text-slate-800 truncate">{{ $user->name }}</p>
                        <p class="text-[10px] text-slate-400 font-medium truncate">{{ $user->email }}</p>
                    </div>
                </div>
                <div class="nav-label">
                    <span class="inline-block px-1.5 py-0.5 rounded text-[9px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">SQLite</span>
                </div>
            </div>

            <!-- Logout Button -->
            <form id="logout-form" method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="w-full flex items-center gap-2.5 px-3 py-2 rounded-xl text-xs font-semibold text-rose-600 hover:bg-rose-50 transition cursor-pointer">
                    <i data-lucide="log-out" class="w-4 h-4 shrink-0"></i>
                    <span class="nav-label">Sign Out</span>
                </button>
            </form>
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
                <button type="button" onclick="refreshDashboardData()" class="p-2 rounded-xl border border-slate-200 hover:bg-slate-50 text-slate-600 text-xs font-semibold flex items-center gap-1.5 transition cursor-pointer" title="Refresh Live Data">
                    <i data-lucide="refresh-cw" id="refresh-icon" class="w-3.5 h-3.5"></i>
                    <span class="hidden sm:inline">Sync</span>
                </button>

                <button type="button" onclick="openAddTunnelModal()" class="px-4 py-2 rounded-xl bg-[#100091] hover:bg-[#0b0068] text-white text-xs font-semibold shadow-md shadow-[#100091]/25 transition flex items-center gap-1.5 cursor-pointer shrink-0">
                    <i data-lucide="plus" class="w-4 h-4"></i>
                    <span>New Tunnel</span>
                </button>
            </div>
        </header>

        <!-- PAGE CONTENT CONTAINER -->
        <main class="p-6 flex-1 overflow-x-hidden">

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
                                    <div class="flex items-center gap-2">
                                        <span class="font-bold text-slate-900 text-sm">{{ $tunnel->name }}</span>
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

                <!-- Recent Activity Logs -->
                @if($activities->count() > 0)
                    <div class="bg-white border border-slate-200/90 rounded-2xl p-6 shadow-2xs">
                        <h4 class="text-sm font-bold text-slate-900 mb-4 flex items-center gap-2">
                            <i data-lucide="activity" class="w-4 h-4 text-[#100091]"></i>
                            <span>Recent Activity Stream</span>
                        </h4>
                        <div class="space-y-2.5">
                            @foreach($activities as $act)
                                <div class="text-xs flex items-center justify-between p-2.5 rounded-xl bg-slate-50 border border-slate-100 text-slate-700">
                                    <div class="flex items-center gap-2">
                                        <span class="w-1.5 h-1.5 rounded-full bg-[#100091]"></span>
                                        <span>{{ $act->description }}</span>
                                    </div>
                                    <span class="text-[11px] text-slate-400 font-mono">{{ $act->created_at->format('H:i:s') }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            <!-- ======================================================== -->
            <!-- 2. TUNNELS TAB CONTENT (Matching Screenshots) -->
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

                <!-- Tunnels Table Matching User Screenshot 2 -->
                <div class="bg-white border border-slate-200/90 rounded-2xl overflow-hidden shadow-2xs">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="border-b border-slate-200/80 bg-slate-50/70">
                                    <th class="py-3.5 px-6 text-[11px] font-bold text-slate-500 uppercase tracking-wider">STATUS</th>
                                    <th class="py-3.5 px-6 text-[11px] font-bold text-slate-500 uppercase tracking-wider">TUNNEL NAME</th>
                                    <th class="py-3.5 px-6 text-[11px] font-bold text-slate-500 uppercase tracking-wider">LOCAL URL</th>
                                    <th class="py-3.5 px-6 text-[11px] font-bold text-slate-500 uppercase tracking-wider">PUBLIC URL</th>
                                    <th class="py-3.5 px-6 text-[11px] font-bold text-slate-500 uppercase tracking-wider">CREATED DATE</th>
                                    <th class="py-3.5 px-6 text-[11px] font-bold text-slate-500 uppercase tracking-wider text-right">ACTIONS</th>
                                </tr>
                            </thead>
                            <tbody id="tunnels-table-body" class="divide-y divide-slate-100">
                                <!-- Populated dynamically by JS or initial Blade loop -->
                                @include('partials.tunnels_table_rows', ['tunnels' => $tunnels])
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- ======================================================== -->
            <!-- 3. CUSTOM DOMAINS TAB CONTENT -->
            <!-- ======================================================== -->
            <div id="tab-content-domains" class="hidden w-full">
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start w-full">
                    <!-- LEFT COLUMN: Header, Search & Table -->
                    <div class="lg:col-span-7 xl:col-span-7 2xl:col-span-7 space-y-6 w-full">
                        <!-- Header Section -->
                        <div class="w-full">
                            <h1 class="text-3xl font-extrabold text-slate-900 tracking-tight font-sans">Custom Domains</h1>
                            <p class="text-sm text-slate-500 mt-1">Configure and validate custom domains to route your local tunnels.</p>
                        </div>

                        <!-- Search and Add Domain Bar -->
                        <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
                            <div class="relative flex-1">
                                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                                    <i data-lucide="search" class="w-4 h-4"></i>
                                </div>
                                <input
                                    type="text"
                                    id="domain-search-input"
                                    oninput="filterDomainsList(this.value)"
                                    placeholder="Search custom domains..."
                                    class="w-full pl-10 pr-4 py-2.5 bg-white border border-slate-200/90 rounded-xl text-sm text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-[#100091]/20 focus:border-[#100091] shadow-2xs transition"
                                >
                            </div>

                            <button type="button" onclick="openAddDomainModal()" class="px-5 py-2.5 rounded-xl bg-[#100091] hover:bg-[#0b0068] text-white text-sm font-semibold shadow-md shadow-[#100091]/25 transition flex items-center justify-center gap-2 cursor-pointer shrink-0">
                                <i data-lucide="plus" class="w-4 h-4 stroke-[2.5]"></i>
                                <span>Add Domain</span>
                            </button>
                        </div>

                        <!-- Configured Domains Table Card -->
                        <div class="bg-white border border-slate-200/90 rounded-2xl shadow-2xs overflow-hidden">
                            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-white">
                                <div class="flex items-center gap-2.5">
                                    <i data-lucide="globe" class="w-4 h-4 text-blue-600 stroke-[2.2]"></i>
                                    <h3 class="font-extrabold text-slate-900 text-sm tracking-tight">Configured Domains</h3>
                                </div>
                                <span id="domains-count-badge" class="text-xs font-semibold text-slate-400">{{ $domains->count() }} Domains</span>
                            </div>

                            <div class="overflow-x-auto">
                                <table class="w-full text-left border-collapse text-xs">
                                    <thead>
                                        <tr class="border-b border-slate-100 bg-slate-50/50">
                                            <th class="py-3 px-6 font-bold text-slate-500 uppercase tracking-wider text-[11px]">DOMAIN</th>
                                            <th class="py-3 px-6 font-bold text-slate-500 uppercase tracking-wider text-[11px]">STATUS</th>
                                            <th class="py-3 px-6 font-bold text-slate-500 uppercase tracking-wider text-[11px]">VALIDATED AT</th>
                                            <th class="py-3 px-6 font-bold text-slate-500 uppercase tracking-wider text-[11px] text-right">ACTIONS</th>
                                        </tr>
                                    </thead>
                                    <tbody id="domains-table-body" class="divide-y divide-slate-100">
                                        @include('partials.domains_table_rows', ['domains' => $domains])
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- RIGHT COLUMN: Step-by-Step Free Cloudflare Guide (Starts at the top) -->
                    <div class="lg:col-span-5 xl:col-span-5 2xl:col-span-5 w-full space-y-4 lg:sticky lg:top-6">
                        <div class="bg-white border border-slate-200/90 rounded-2xl shadow-2xs p-5 sm:p-6 space-y-5 w-full">
                            <!-- Guide Header -->
                            <div class="flex items-start justify-between gap-3 border-b border-slate-100 pb-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-xl bg-orange-50 border border-orange-200/80 flex items-center justify-center text-orange-500 shadow-2xs">
                                        <i data-lucide="cloud" class="w-5 h-5 fill-current"></i>
                                    </div>
                                    <div>
                                        <h3 class="font-extrabold text-slate-900 text-sm tracking-tight">Connect Domain in Cloudflare</h3>
                                        <button type="button" onclick="openGuideModal()" class="text-[11px] text-[#100091] hover:underline font-semibold cursor-pointer text-left block">
                                            View detailed guide modal &rarr;
                                        </button>
                                    </div>
                                </div>
                                <span class="text-[10px] font-bold uppercase tracking-wider text-emerald-700 bg-emerald-50 px-2.5 py-1 rounded-full border border-emerald-200 shrink-0">
                                    Free Plan
                                </span>
                            </div>

                            <!-- Steps 1 to 5 -->
                            <div class="space-y-4 text-xs">
                                <div class="flex gap-3">
                                    <div class="w-6 h-6 rounded-full bg-[#100091] text-white font-bold text-[11px] flex items-center justify-center shrink-0 mt-0.5 shadow-2xs">1</div>
                                    <div class="space-y-0.5">
                                        <h4 class="font-bold text-slate-900">Sign Up / Log In to Cloudflare</h4>
                                        <p class="text-slate-500 text-[11px] leading-relaxed">
                                            Create a free account at <a href="https://dash.cloudflare.com/sign-up" target="_blank" rel="noreferrer" class="text-[#100091] font-semibold hover:underline">dash.cloudflare.com</a>. No credit card required.
                                        </p>
                                    </div>
                                </div>

                                <div class="flex gap-3">
                                    <div class="w-6 h-6 rounded-full bg-[#100091] text-white font-bold text-[11px] flex items-center justify-center shrink-0 mt-0.5 shadow-2xs">2</div>
                                    <div class="space-y-0.5">
                                        <h4 class="font-bold text-slate-900">Add Your Domain</h4>
                                        <p class="text-slate-500 text-[11px] leading-relaxed">Click "+ Add a Site", enter your root domain, and choose the Free ($0/month) plan.</p>
                                    </div>
                                </div>

                                <div class="flex gap-3">
                                    <div class="w-6 h-6 rounded-full bg-[#100091] text-white font-bold text-[11px] flex items-center justify-center shrink-0 mt-0.5 shadow-2xs">3</div>
                                    <div class="space-y-0.5">
                                        <h4 class="font-bold text-slate-900">Automatic DNS Records Scan</h4>
                                        <p class="text-slate-500 text-[11px] leading-relaxed">Cloudflare scans existing records. Review and click Continue.</p>
                                    </div>
                                </div>

                                <div class="flex gap-3">
                                    <div class="w-6 h-6 rounded-full bg-[#100091] text-white font-bold text-[11px] flex items-center justify-center shrink-0 mt-0.5 shadow-2xs">4</div>
                                    <div class="space-y-0.5">
                                        <h4 class="font-bold text-slate-900">Update Nameservers at Registrar</h4>
                                        <p class="text-slate-500 text-[11px] leading-relaxed">Log in to GoDaddy, Namecheap, Hostinger, etc. and update the 2 nameservers.</p>
                                    </div>
                                </div>

                                <div class="flex gap-3">
                                    <div class="w-6 h-6 rounded-full bg-[#100091] text-white font-bold text-[11px] flex items-center justify-center shrink-0 mt-0.5 shadow-2xs">5</div>
                                    <div class="space-y-0.5">
                                        <h4 class="font-bold text-slate-900">Validate in TunnelDeck</h4>
                                        <p class="text-slate-500 text-[11px] leading-relaxed">Click Validate on the left. Cloudflared authenticates your zone automatically!</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Perks Callout -->
                            <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200/80 space-y-1.5 text-[11px]">
                                <div class="flex items-center gap-1.5 font-bold text-slate-800">
                                    <i data-lucide="shield" class="w-3.5 h-3.5 text-emerald-600"></i>
                                    <span>Free Plan Includes:</span>
                                </div>
                                <ul class="space-y-1 text-slate-500 text-[11px] pl-4 list-disc">
                                    <li>Automatic SSL/TLS encryption for all subdomains</li>
                                    <li>Unlimited bandwidth for Cloudflare Tunnels</li>
                                    <li>Global Edge CDN &amp; DDoS protection</li>
                                </ul>
                            </div>

                            <!-- Open Cloudflare Button -->
                            <a href="https://dash.cloudflare.com" target="_blank" rel="noopener noreferrer" class="w-full py-2.5 px-4 rounded-xl bg-[#100091] hover:bg-[#0b0068] text-white font-semibold text-xs transition flex items-center justify-center gap-2 shadow-md shadow-[#100091]/25 cursor-pointer">
                                <span>Open Cloudflare Dashboard</span>
                                <i data-lucide="external-link" class="w-3.5 h-3.5"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ======================================================== -->
            <!-- 4. PLATFORM SETTINGS TAB CONTENT -->
            <!-- ======================================================== -->
            <div id="tab-content-settings" class="hidden space-y-6 w-full max-w-7xl">
                <div>
                    <h2 class="text-3xl font-extrabold text-slate-900 tracking-tight font-sans">Platform Settings</h2>
                    <p class="text-sm text-slate-500 mt-1">System configuration, binary paths, credentials &amp; security management</p>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start w-full">
                    <!-- Left Column: Platform Configuration (7 cols) -->
                    <div class="lg:col-span-7 xl:col-span-7 space-y-6 w-full">
                        <div class="bg-white p-6 rounded-2xl border border-slate-200/90 shadow-2xs space-y-6">
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
                                            src/backend/bin/windows/x64/cloudflared.exe (v2026.7)
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
                                            <span class="font-semibold text-slate-800">50</span>
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
                                            class="w-full px-3.5 pr-10 py-2.5 bg-white border border-slate-300 rounded-xl text-sm text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-[#100091]/20 focus:border-[#100091] transition"
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
                                            class="w-full px-3.5 pr-10 py-2.5 bg-white border border-slate-300 rounded-xl text-sm text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-[#100091]/20 focus:border-[#100091] transition"
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
                                            <input type="checkbox" id="settings-autofill-toggle" checked class="sr-only peer">
                                            <div class="w-9 h-5 bg-slate-300 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-[#100091]"></div>
                                        </div>
                                        <div class="space-y-0.5 text-left">
                                            <span class="block text-xs font-bold text-slate-800">Autofill in login page password field</span>
                                            <span class="block text-[11px] text-slate-500 leading-relaxed">When enabled, your password is automatically filled when visiting the login page.</span>
                                        </div>
                                    </label>
                                </div>

                                <!-- Submit Button -->
                                <button type="submit" id="save-password-btn" class="w-full py-2.5 px-4 rounded-xl bg-[#100091] hover:bg-[#0b0068] text-white font-bold text-xs shadow-md shadow-[#100091]/25 transition flex items-center justify-center gap-2 cursor-pointer disabled:opacity-50">
                                    <i data-lucide="save" class="w-4 h-4"></i>
                                    <span>Save Password</span>
                                </button>
                            </form>
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

</div>
@endsection

@push('scripts')
<script src="{{ asset('js/app.js') }}"></script>
@endpush
