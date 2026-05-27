<?php

namespace App\Http\Controllers\EPayAdmin;

use App\Http\Controllers\Controller;
use App\Models\EPayPlus\License;
use App\Models\EPayPlus\Retailer;
use Illuminate\Http\Request;

class LicenseController extends Controller
{
    public function index(Request $request)
    {
        $query = License::with(['retailer', 'device'])->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                  ->orWhere('machine_uid', 'like', "%{$search}%");
            });
        }

        $licenses = $query->paginate(25)->withQueryString();
        $retailers = Retailer::orderBy('business_name')->get(['id', 'business_name', 'account_id']);

        $stats = [
            'available' => License::where('status', 'available')->count(),
            'active' => License::where('status', 'active')->count(),
            'blocked' => License::whereIn('status', ['blocked', 'revoked'])->count(),
            'expiring' => License::where('status', 'active')
                ->whereNotNull('expires_at')
                ->where('expires_at', '<=', now()->addDays(30))
                ->count(),
        ];

        return view('epayplus.licenses.index', compact('licenses', 'retailers', 'stats'));
    }

    public function generate(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|in:retailer,kiosk',
            'quantity' => 'required|integer|min:1|max:50',
            'retailer_id' => 'nullable|integer|exists:epay_retailers,id',
            'expires_at' => 'nullable|date|after:today',
            'notes' => 'nullable|string|max:500',
        ]);

        $created = [];
        for ($i = 0; $i < $validated['quantity']; $i++) {
            $created[] = License::create([
                'code' => License::generateCode($validated['type']),
                'type' => $validated['type'],
                'status' => 'available',
                'retailer_id' => $validated['retailer_id'] ?? null,
                'expires_at' => $validated['expires_at'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ]);
        }

        return back()->with('success', count($created) . ' license key(s) generated.');
    }

    public function activate(Request $request, License $license)
    {
        $validated = $request->validate([
            'machine_uid' => 'required|string|max:100',
            'retailer_id' => 'nullable|integer|exists:epay_retailers,id',
        ]);

        if ($license->isBlocked()) {
            return back()->with('error', 'This license is blocked or revoked.');
        }

        $license->update([
            'status' => 'active',
            'machine_uid' => $validated['machine_uid'],
            'retailer_id' => $validated['retailer_id'] ?? $license->retailer_id,
            'activated_at' => now(),
        ]);

        return back()->with('success', "License {$license->code} activated for machine {$validated['machine_uid']}.");
    }

    public function transfer(Request $request, License $license)
    {
        $validated = $request->validate([
            'retailer_id' => 'nullable|integer|exists:epay_retailers,id',
        ]);

        $license->transferTo($validated['retailer_id'] ?? null);

        return back()->with('success', "License {$license->code} transferred and reset for reassignment.");
    }

    public function revoke(License $license)
    {
        $license->revoke();
        return back()->with('success', "License {$license->code} revoked.");
    }

    public function block(License $license)
    {
        $license->block();
        return back()->with('success', "License {$license->code} blocked.");
    }
}
