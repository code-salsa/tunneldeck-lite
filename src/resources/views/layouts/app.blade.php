<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'TunnelDeck Lite — Cloudflared Engine')</title>

    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

    <!-- Tailwind CSS (Zero Build Step CDN) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            DEFAULT: '#100091',
                            hover: '#0b0068',
                            light: '#eef2ff',
                        }
                    },
                    fontFamily: {
                        sans: ['Inter', 'system-ui', '-apple-system', 'sans-serif'],
                    },
                    boxShadow: {
                        '2xs': '0 1px 2px 0 rgba(0, 0, 0, 0.05)',
                    }
                }
            }
        }
    </script>

    <!-- Lucide Icons Library -->
    <script src="https://unpkg.com/lucide@latest"></script>

    <style>
        /* Custom scrollbars */
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        ::-webkit-scrollbar-track {
            background: #f1f5f9;
        }
        ::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 9999px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        /* Pulse / ping animations */
        @keyframes pulse-subtle {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.8; transform: scale(1.05); }
        }
        .animate-pulse-subtle {
            animation: pulse-subtle 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
    </style>

    @stack('styles')
</head>
<body class="bg-slate-50 text-slate-900 font-sans antialiased selection:bg-indigo-500 selection:text-white">

    @yield('content')

    <!-- Floating Global Toast Notifications Container -->
    <div id="toast-container" class="fixed top-6 right-6 z-50 flex flex-col gap-2.5 pointer-events-none"></div>

    <!-- ======================================================== -->
    <!-- GLOBAL MODAL: NO INTERNET CONNECTIVITY -->
    <!-- ======================================================== -->
    <div id="no-internet-modal" class="hidden fixed inset-0 z-[99999] flex items-center justify-center p-4 bg-slate-950/75 backdrop-blur-md transition-all duration-300 select-none">
        <div class="bg-white border border-slate-200/90 rounded-3xl w-full max-w-md p-6 sm:p-8 text-center relative shadow-2xl space-y-6">
            <!-- Icon with pulsing indicator -->
            <div class="relative mx-auto w-20 h-20 rounded-3xl bg-rose-50 border border-rose-100 flex items-center justify-center text-rose-600 shadow-inner">
                <i data-lucide="wifi-off" class="w-10 h-10"></i>
                <span class="absolute -top-1 -right-1 flex h-4 w-4">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-rose-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-4 w-4 bg-rose-500"></span>
                </span>
            </div>

            <!-- Content: Exactly as requested -->
            <div class="space-y-2">
                <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-rose-50 border border-rose-200/80 text-rose-700 text-xs font-bold uppercase tracking-wider">
                    <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                    <span>Offline</span>
                </div>
                <h3 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">
                    No Internet Connectivity
                </h3>
                <p class="text-sm font-medium text-slate-600 leading-relaxed max-w-sm mx-auto">
                    Please ensure you are connected to the internet before this app will work.
                </p>
            </div>

            <!-- Helpful Diagnostics / Status details -->
            <div class="p-3.5 rounded-2xl bg-slate-50 border border-slate-200/80 text-xs text-slate-500 space-y-1.5">
                <div class="flex items-center justify-between font-medium">
                    <span>Connection Status:</span>
                    <span id="connectivity-status-label" class="font-bold text-rose-600 flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-rose-500"></span>
                        <span id="connectivity-status-text">Disconnected</span>
                    </span>
                </div>
                <p class="text-[11px] text-slate-400 text-left pt-1 border-t border-slate-200/60 leading-normal">
                    TunnelDeck Lite requires active internet access for Cloudflare edge routing, tunnel creation, and license verification.
                </p>
            </div>

            <!-- Retry button -->
            <div>
                <button type="button" id="btn-retry-connectivity" onclick="checkInternetConnectivity(true)" class="w-full py-3 px-5 rounded-2xl bg-[#100091] hover:bg-[#0b0068] active:scale-[0.99] text-white text-sm font-bold transition flex items-center justify-center gap-2 shadow-lg shadow-[#100091]/25 cursor-pointer">
                    <i data-lucide="refresh-cw" id="btn-retry-icon" class="w-4 h-4"></i>
                    <span id="btn-retry-text">Check Connection Again</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Global Scripts -->
    <script>
        // Global CSRF Header setup for all fetch calls
        const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

        // Global Toast Notification System
        function showToast(message, type = 'success') {
            const container = document.getElementById('toast-container');
            if (!container) return;

            const toast = document.createElement('div');
            toast.className = `pointer-events-auto flex items-center gap-3 px-4 py-3 bg-white border ${type === 'error' ? 'border-rose-200' : 'border-emerald-200'} text-slate-800 rounded-2xl shadow-xl shadow-slate-900/10 transition-all duration-300 transform translate-y-[-10px] opacity-0`;
            
            const iconBg = type === 'error' ? 'bg-rose-100 text-rose-600' : 'bg-emerald-100 text-emerald-600';
            const iconSvg = type === 'error' 
                ? '<svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>'
                : '<svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>';

            toast.innerHTML = `
                <div class="w-7 h-7 rounded-full ${iconBg} flex items-center justify-center shrink-0">
                    ${iconSvg}
                </div>
                <span class="text-xs sm:text-sm font-semibold text-slate-800">${message}</span>
                <button type="button" class="ml-2 text-slate-400 hover:text-slate-600 transition cursor-pointer" onclick="this.parentElement.remove()">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            `;

            container.appendChild(toast);

            // Animate in
            requestAnimationFrame(() => {
                toast.classList.remove('translate-y-[-10px]', 'opacity-0');
            });

            // Auto dismiss after 4 seconds
            setTimeout(() => {
                toast.classList.add('opacity-0', 'translate-y-[-10px]');
                setTimeout(() => toast.remove(), 300);
            }, 4000);
        }

        // ========================================================
        // GLOBAL INTERNET CONNECTIVITY CHECKER
        // ========================================================
        let isInternetConnected = true;
        let isCheckingConnectivity = false;

        function showNoInternetModal(reason) {
            const modal = document.getElementById('no-internet-modal');
            if (!modal) return;
            
            isInternetConnected = false;
            modal.classList.remove('hidden');

            const statusText = document.getElementById('connectivity-status-text');
            if (statusText && reason) {
                statusText.textContent = reason;
            }

            if (window.lucide) window.lucide.createIcons();
        }

        function hideNoInternetModal() {
            const modal = document.getElementById('no-internet-modal');
            if (!modal) return;

            if (!isInternetConnected) {
                isInternetConnected = true;
                modal.classList.add('hidden');
                showToast('Internet connection active & restored!', 'success');
            } else {
                modal.classList.add('hidden');
            }
        }

        async function verifyWanConnectivity() {
            // If adapter says offline, we are definitely offline
            if (!navigator.onLine) {
                return false;
            }

            // 1. Probe local Laravel endpoint (tests socket out to 1.1.1.1 / 8.8.8.8)
            try {
                const controller = new AbortController();
                const timeoutId = setTimeout(() => controller.abort(), 2000);
                const res = await fetch('/api/connectivity-check?t=' + Date.now(), {
                    cache: 'no-store',
                    signal: controller.signal
                });
                clearTimeout(timeoutId);
                if (res.ok) {
                    const data = await res.json();
                    if (data && typeof data.online === 'boolean') {
                        return data.online;
                    }
                }
            } catch (err) {
                // If local backend is sluggish, fallback to direct browser ping
            }

            // 2. Fallback probe: direct external ping to public endpoint with no-cors
            try {
                const controller2 = new AbortController();
                const timeoutId2 = setTimeout(() => controller2.abort(), 2000);
                await fetch('https://licensing.tunneldeck.online/api/v1?t=' + Date.now(), {
                    method: 'HEAD',
                    mode: 'no-cors',
                    cache: 'no-store',
                    signal: controller2.signal
                });
                clearTimeout(timeoutId2);
                return true;
            } catch (e) {
                return false;
            }
        }

        async function checkInternetConnectivity(userInitiated = false) {
            if (isCheckingConnectivity) return;
            isCheckingConnectivity = true;

            const retryBtn = document.getElementById('btn-retry-connectivity');
            const retryIcon = document.getElementById('btn-retry-icon');
            const retryText = document.getElementById('btn-retry-text');

            if (userInitiated && retryBtn) {
                retryBtn.disabled = true;
                if (retryIcon) retryIcon.classList.add('animate-spin');
                if (retryText) retryText.textContent = 'Verifying Connection...';
            }

            try {
                const online = await verifyWanConnectivity();
                if (online) {
                    hideNoInternetModal();
                    if (userInitiated) {
                        showToast('Internet connection verified!', 'success');
                    }
                } else {
                    showNoInternetModal('No route to internet');
                    if (userInitiated) {
                        showToast('Still no internet connectivity. Please check your network.', 'error');
                    }
                }
            } catch (e) {
                showNoInternetModal('Network unreachable');
            } finally {
                isCheckingConnectivity = false;
                if (userInitiated && retryBtn) {
                    retryBtn.disabled = false;
                    if (retryIcon) retryIcon.classList.remove('animate-spin');
                    if (retryText) retryText.textContent = 'Check Connection Again';
                    if (window.lucide) window.lucide.createIcons();
                }
            }
        }

        // Real-time Network Events
        window.addEventListener('offline', () => {
            showNoInternetModal('Network adapter offline');
        });

        window.addEventListener('online', () => {
            checkInternetConnectivity();
        });

        // Periodic background pulse check every 8 seconds when window is active
        setInterval(() => {
            if (document.visibilityState === 'visible') {
                checkInternetConnectivity();
            }
        }, 8000);

        // Check on window focus (e.g. user switched back from WiFi settings)
        window.addEventListener('focus', () => {
            checkInternetConnectivity();
        });

        // Initialize Lucide icons on load
        document.addEventListener('DOMContentLoaded', () => {
            if (window.lucide) {
                window.lucide.createIcons();
            }
            checkInternetConnectivity();
        });
    </script>

    @stack('scripts')
</body>
</html>

