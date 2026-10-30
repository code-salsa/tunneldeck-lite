<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CustomDomain;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DomainController extends Controller
{
    public function index(): JsonResponse
    {
        $domains = CustomDomain::withCount('tunnels')->orderBy('created_at', 'desc')->get();
        return response()->json([
            'status' => 'success',
            'domains' => $domains,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'domain' => ['required', 'string', 'max:150', 'unique:custom_domains,domain'],
        ]);

        $domain = CustomDomain::create([
            'user_id' => auth()->id(),
            'domain' => strtolower(trim($validated['domain'])),
            'validation_status' => 'active',
            'validated_at' => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Custom domain added successfully.',
            'domain' => $domain,
        ], 201);
    }

    public function destroy(CustomDomain $domain): JsonResponse
    {
        $domain->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Domain removed successfully.',
        ]);
    }
}
