<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-slate-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Account Suspended — TunnelDeck</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="h-full flex items-center justify-center p-4 font-sans antialiased text-slate-800">
    <div class="w-full max-w-lg bg-white border border-rose-200/80 rounded-3xl p-7 sm:p-9 shadow-2xl shadow-rose-950/5 relative text-center space-y-6">
        
        <!-- Danger Shield Icon -->
        <div class="w-20 h-20 rounded-3xl bg-rose-50 border border-rose-200/90 text-rose-600 flex items-center justify-center mx-auto shadow-inner">
            <i data-lucide="shield-alert" class="w-10 h-10"></i>
        </div>

        <div class="space-y-2">
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-rose-50 border border-rose-200 text-rose-800 text-[11px] font-bold uppercase tracking-wider">
                <span class="w-2 h-2 rounded-full bg-rose-500 animate-pulse"></span>
                <span>License Access Suspended</span>
            </span>
            <h1 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">Application Access Suspended</h1>
            <p class="text-xs sm:text-sm text-slate-500 max-w-md mx-auto leading-relaxed">
                Your TunnelDeck license or account access has been suspended by the licensing manager administrator. All background routing tunnels have been stopped.
            </p>
        </div>

        <!-- Identity & Device Details -->
        <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200/80 text-left space-y-2.5 text-xs">
            <div class="flex items-center justify-between pb-2 border-b border-slate-200/60">
                <span class="text-slate-400 font-medium">Account Email:</span>
                <span class="font-mono font-semibold text-slate-800">{{ $user->email }}</span>
            </div>
            <div class="flex items-center justify-between pb-2 border-b border-slate-200/60">
                <span class="text-slate-400 font-medium">Hardware Device ID (HWID):</span>
                <span class="font-mono font-bold text-slate-800">{{ $hwid }}</span>
            </div>
            <div class="flex items-center justify-between">
                <span class="text-slate-400 font-medium">Status:</span>
                <span class="font-bold text-rose-600 uppercase text-[11px] flex items-center gap-1">
                    <i data-lucide="x-circle" class="w-3.5 h-3.5"></i> Suspended
                </span>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="space-y-3 pt-2">
            <button type="button" id="btn-check-status" onclick="checkStatusManual(this)" class="w-full py-3 px-4 rounded-xl bg-slate-900 hover:bg-slate-800 text-white text-xs sm:text-sm font-bold transition flex items-center justify-center gap-2 shadow-sm cursor-pointer">
                <i data-lucide="refresh-cw" class="w-4 h-4" id="check-icon"></i>
                <span id="check-text">Check Status &amp; Re-activate</span>
            </button>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="w-full py-2.5 px-4 rounded-xl border border-slate-200 hover:bg-slate-50 text-slate-600 text-xs font-semibold transition cursor-pointer">
                    Sign Out
                </button>
            </form>
        </div>

        <p class="text-[11px] text-slate-400">
            Need assistance? Contact support at <a href="https://licensing.tunneldeck.online" target="_blank" class="text-indigo-600 underline font-semibold">licensing.tunneldeck.online</a>
        </p>
    </div>

    <script>
        lucide.createIcons();

        // 1. Manual Re-activation Check
        async function checkStatusManual(btn) {
            const icon = document.getElementById('check-icon');
            const text = document.getElementById('check-text');
            icon.classList.add('animate-spin');
            text.innerText = 'Checking licensing server...';
            btn.disabled = true;

            try {
                const res = await fetch('/api/license/sync');
                const data = await res.json();

                if (!data.suspended && data.status === 'active') {
                    text.innerText = 'Access Restored! Redirecting...';
                    setTimeout(() => {
                        window.location.href = '/';
                    }, 800);
                    return;
                }

                alert(data.message || 'Account is still suspended by licensing administrator.');
            } catch (err) {
                alert('Unable to reach licensing server. Please check internet connection.');
            } finally {
                icon.classList.remove('animate-spin');
                text.innerText = 'Check Status & Re-activate';
                btn.disabled = false;
            }
        }

        // 2. Real-time Auto-Polling (Check every 10 seconds if admin has un-suspended user)
        setInterval(async () => {
            try {
                const res = await fetch('/api/license/sync');
                const data = await res.json();
                if (!data.suspended && data.status === 'active') {
                    window.location.href = '/';
                }
            } catch (e) {
                // Ignore connection blips
            }
        }, 10000);
    </script>
</body>
</html>

