@forelse($tunnels as $tunnel)
    <tr class="hover:bg-slate-50/80 transition group border-l-4 border-transparent" id="tunnel-row-{{ $tunnel->id }}" data-tunnel-id="{{ $tunnel->id }}">
        <!-- 0. BULK CHECKBOX -->
        <td class="py-4 pl-6 pr-2 w-12 text-left whitespace-nowrap">
            <input type="checkbox"
                   class="tunnel-row-checkbox w-4 h-4 rounded border-slate-300 text-[#100091] focus:ring-2 focus:ring-[#100091]/30 cursor-pointer accent-[#100091]"
                   value="{{ $tunnel->id }}"
                   data-name="{{ $tunnel->name }}"
                   data-port="{{ $tunnel->port }}"
                   data-status="{{ $tunnel->status }}"
                   onchange="handleTunnelCheckboxChange({{ $tunnel->id }}, this)">
        </td>

        <!-- 1. STATUS -->
        <td class="py-4 px-6 whitespace-nowrap">
            @if($tunnel->status === 'running')
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-200">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                    <span>Running</span>
                </span>
                @if($tunnel->is_dummy)
                    @php
                        $rem = $tunnel->demo_remaining_seconds ?? 60;
                        $timerText = sprintf('%02d:%02d', floor($rem / 60), $rem % 60);
                    @endphp
                    <div class="mt-1.5 flex items-center gap-1.5 text-[11px] font-bold text-amber-800 bg-amber-50 px-2 py-0.5 rounded-full border border-amber-200/90 w-fit">
                        <i data-lucide="timer" class="w-3 h-3 text-amber-600 animate-pulse"></i>
                        <span>Auto stop tunnel in <span class="font-mono text-amber-900" id="timer-dummy-{{ $tunnel->id }}">{{ $timerText }}</span></span>
                    </div>
                @endif
            @elseif($tunnel->status === 'starting')
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-blue-50 text-blue-700 border border-blue-200">
                    <span class="w-1.5 h-1.5 rounded-full bg-blue-500 animate-ping"></span>
                    <span>Starting...</span>
                </span>
            @else
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-slate-100/90 text-slate-600 border border-slate-200">
                    <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
                    <span>Stopped</span>
                </span>
            @endif
        </td>

        <!-- 2. TUNNEL NAME -->
        <td class="py-4 px-6 font-bold text-slate-900 text-sm whitespace-nowrap">
            <div class="flex items-center gap-2">
                <span>{{ $tunnel->name }}</span>
                @if($tunnel->is_dummy)
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-extrabold uppercase tracking-wider bg-amber-100 text-amber-800 border border-amber-300 shadow-2xs">
                        <i data-lucide="sparkles" class="w-3 h-3 text-amber-600"></i>
                        <span>Demo Data</span>
                    </span>
                @endif
            </div>
        </td>

        <!-- 3. LOCAL URL -->
        <td class="py-4 px-6 whitespace-nowrap">
            <div class="inline-flex items-center gap-2">
                <span class="px-3 py-1.5 rounded-lg bg-slate-50 border border-slate-200 text-xs font-mono text-slate-700">
                    http://localhost:{{ $tunnel->port }}
                </span>
                <button type="button" onclick="copyToClipboard('http://localhost:{{ $tunnel->port }}', this)" class="p-1 text-slate-400 hover:text-slate-600 transition cursor-pointer" title="Copy Local URL">
                    <i data-lucide="clipboard" class="w-4 h-4"></i>
                </button>
            </div>
        </td>

        <!-- 4. PUBLIC URL -->
        <td class="py-4 px-6 whitespace-nowrap">
            @if($tunnel->status === 'running' && $tunnel->public_url)
                <div class="inline-flex items-center gap-2">
                    <a href="{{ $tunnel->public_url }}" target="_blank" rel="noreferrer" class="text-xs font-mono text-[#100091] hover:underline flex items-center gap-1 font-medium max-w-xs truncate">
                        <span>{{ $tunnel->public_url }}</span>
                        <i data-lucide="external-link" class="w-3.5 h-3.5 text-slate-400 shrink-0"></i>
                    </a>
                    <button type="button" onclick="copyToClipboard('{{ $tunnel->public_url }}', this)" class="p-1 text-slate-400 hover:text-slate-600 transition cursor-pointer shrink-0" title="Copy Public URL">
                        <i data-lucide="copy" class="w-3.5 h-3.5"></i>
                    </button>
                </div>
            @else
                <span class="text-xs text-slate-500 font-normal">Not Running</span>
            @endif
        </td>

        <!-- 5. CREATED DATE -->
        <td class="py-4 px-6 text-xs text-slate-600 whitespace-nowrap">
            {{ $tunnel->created_at ? $tunnel->created_at->format('d M Y') : '31 Oct 2026' }}
        </td>

        <!-- 6. ACTIONS -->
        <td class="py-4 px-6 text-right whitespace-nowrap">
            <div class="inline-flex items-center justify-end gap-2">
                @if($tunnel->status === 'running')
                    <button type="button" onclick="stopTunnel({{ $tunnel->id }})" class="px-3.5 py-1.5 rounded-lg bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold flex items-center gap-1.5 shadow-2xs transition cursor-pointer" title="Stop Tunnel">
                        <i data-lucide="pause" class="w-3 h-3 fill-current"></i>
                        <span>Stop</span>
                    </button>
                @else
                    <button type="button" onclick="openStartRoutingModal({{ $tunnel->id }})" class="px-3.5 py-1.5 rounded-lg bg-[#15803d] hover:bg-[#166534] text-white text-xs font-bold flex items-center gap-1.5 shadow-2xs transition cursor-pointer" title="Start Tunnel">
                        <i data-lucide="play" class="w-3 h-3 fill-current"></i>
                        <span>Start</span>
                    </button>
                @endif

                <!-- Edit Button -->
                <button type="button" onclick="openEditTunnelModal({{ $tunnel->id }}, '{{ addslashes($tunnel->name) }}', {{ $tunnel->port }})" class="p-1.5 rounded-lg border border-slate-200 hover:bg-slate-50 text-slate-500 hover:text-slate-700 transition cursor-pointer" title="Edit Tunnel">
                    <i data-lucide="pencil" class="w-4 h-4"></i>
                </button>

                <!-- Delete Button -->
                <button type="button" onclick="openDeleteTunnelModal({{ $tunnel->id }}, '{{ addslashes($tunnel->name) }}', {{ $tunnel->port }})" class="p-1.5 rounded-lg border border-slate-200 hover:bg-rose-50 text-rose-500 hover:text-rose-600 transition cursor-pointer" title="Delete Tunnel">
                    <i data-lucide="trash-2" class="w-4 h-4 stroke-[1.8]"></i>
                </button>
            </div>
        </td>
    </tr>
@empty
    <tr>
        <td colspan="7" class="py-16 text-center text-slate-500 text-sm">
            <p class="font-medium text-slate-700">No tunnels registered yet.</p>
            <p class="text-xs text-slate-400 mt-1">Create your first forwarding tunnel to expose local ports.</p>
            <button type="button" onclick="openAddTunnelModal()" class="mt-4 px-4 py-2 rounded-xl bg-[#100091] text-white text-xs font-semibold hover:bg-[#0b0068] shadow-sm transition cursor-pointer">
                + Add New Tunnel
            </button>
        </td>
    </tr>
@endforelse

