<?php

namespace App\Http\Controllers\EPayAdmin;

use App\Http\Controllers\Controller;
use App\Models\EPayPlus\Blacklist;
use Illuminate\Http\Request;

class BlacklistController extends Controller
{
    public function index(Request $request)
    {
        $query = Blacklist::orderByDesc('created_at');

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }
        if ($request->filled('search')) {
            $query->where('value', 'like', "%{$request->search}%");
        }

        $entries = $query->paginate(30)->withQueryString();

        return view('epayplus.blacklists.index', compact('entries'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|in:phone,account,device,machine',
            'value' => 'required|string|max:150',
            'reason' => 'nullable|string|max:500',
        ]);

        Blacklist::updateOrCreate(
            ['type' => $validated['type'], 'value' => $validated['value']],
            [
                'reason' => $validated['reason'] ?? null,
                'is_active' => true,
                'blocked_by' => auth()->user()->name ?? 'admin',
                'blocked_at' => now(),
            ]
        );

        return back()->with('success', 'Entry added to blacklist.');
    }

    public function toggle(Blacklist $blacklist)
    {
        $blacklist->update(['is_active' => !$blacklist->is_active]);
        $status = $blacklist->is_active ? 'activated' : 'deactivated';
        return back()->with('success', "Blacklist entry {$status}.");
    }

    public function destroy(Blacklist $blacklist)
    {
        $blacklist->delete();
        return back()->with('success', 'Blacklist entry removed.');
    }
}
