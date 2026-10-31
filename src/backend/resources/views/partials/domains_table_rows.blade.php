@forelse($domains as $dom)
    <tr class="hover:bg-slate-50/70 transition" id="domain-row-{{ $dom->id }}" data-domain-name="{{ strtolower($dom->domain) }}">
        <!-- Domain Name -->
        <td class="py-4 px-6 font-bold text-slate-900 flex items-center gap-2">
            <span class="w-2 h-2 rounded-full {{ $dom->validation_status === 'validated' ? 'bg-emerald-500' : 'bg-slate-300' }}"></span>
            <span>{{ $dom->domain }}</span>
        </td>

        <!-- Status -->
        <td class="py-4 px-6">
            @if($dom->validation_status === 'validated')
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider bg-emerald-50 text-emerald-700 border border-emerald-200">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                    Validated
                </span>
            @elseif($dom->validation_status === 'failed')
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider bg-rose-50 text-rose-700 border border-rose-200">
                    <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                    Failed
                </span>
            @else
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider bg-amber-50 text-amber-700 border border-amber-200">
                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                    Pending
                </span>
            @endif
        </td>

        <!-- Validated At -->
        <td class="py-4 px-6 text-slate-600 font-medium">
            {{ $dom->validated_at ? \Carbon\Carbon::parse($dom->validated_at)->format('d M Y, h:i a') : '-' }}
        </td>

        <!-- Actions -->
        <td class="py-4 px-6 text-right">
            <div class="inline-flex items-center justify-end gap-3">
                @if($dom->validation_status === 'validated')
                    <span class="text-xs font-medium text-emerald-600">Valid Domain</span>
                @else
                    <button type="button" onclick="startDomainValidation({{ $dom->id }}, '{{ addslashes($dom->domain) }}')" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold border border-emerald-300 text-emerald-700 hover:bg-emerald-50 transition cursor-pointer shadow-2xs" title="Validate Domain with Cloudflare">
                        <i data-lucide="shield-check" class="w-3.5 h-3.5 text-emerald-600"></i>
                        <span>Validate</span>
                    </button>
                @endif

                <!-- Delete Button -->
                <button type="button" onclick="openDeleteDomainModal({{ $dom->id }}, '{{ addslashes($dom->domain) }}')" class="p-1.5 rounded-lg text-rose-400 hover:text-rose-600 hover:bg-rose-50 border border-rose-200/70 transition cursor-pointer" title="Delete Domain">
                    <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                </button>
            </div>
        </td>
    </tr>
@empty
    <tr>
        <td colspan="4" class="p-8 sm:p-12 text-center max-w-lg mx-auto space-y-4">
            <div class="w-16 h-16 rounded-2xl bg-indigo-50 border border-indigo-100 text-[#100091] flex items-center justify-center mx-auto shadow-2xs">
                <i data-lucide="globe" class="w-8 h-8 stroke-[1.8]"></i>
            </div>
            <div class="space-y-1">
                <h3 class="text-lg font-extrabold text-slate-900 tracking-tight">No domains added and verified yet.</h3>
                <p class="text-xs text-slate-600 leading-relaxed">
                    First add your domain in Cloudflare (<button type="button" onclick="openGuideModal()" class="text-[#100091] font-bold underline hover:text-[#0b0068] cursor-pointer">follow a step by step guide</button>) and then add a domain here.
                </p>
            </div>
            <div>
                <button type="button" onclick="openGuideModal()" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-orange-50 hover:bg-orange-100/80 text-orange-700 border border-orange-200 text-xs font-semibold transition cursor-pointer shadow-2xs">
                    <i data-lucide="cloud" class="w-4 h-4 text-orange-500 fill-current"></i>
                    <span>Follow Step-by-Step Guide</span>
                </button>
            </div>
            <div class="pt-3 border-t border-slate-100 flex items-center justify-center gap-3">
                <span class="text-xs text-slate-600 font-medium">Ready to add a domain?</span>
                <button type="button" onclick="openAddDomainModal()" class="px-4 py-2 rounded-xl bg-[#100091] hover:bg-[#0b0068] text-white text-xs font-semibold shadow-md shadow-[#100091]/25 transition cursor-pointer">
                    + Add Domain
                </button>
            </div>
        </td>
    </tr>
@endforelse
