<?php

namespace App\Http\Controllers;

use App\Services\HardwareFingerprintService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class SuspensionController extends Controller
{
    /**
     * Display the suspension screen.
     */
    public function show(HardwareFingerprintService $hwidService): View|RedirectResponse
    {
        $user = Auth::user();

        // If user is not suspended, redirect back to dashboard
        if (!$user || (method_exists($user, 'isSuspended') && !$user->isSuspended())) {
            return redirect()->route('dashboard');
        }

        return view('suspended', [
            'user' => $user,
            'hwid' => $hwidService->getHwid(),
        ]);
    }
}

