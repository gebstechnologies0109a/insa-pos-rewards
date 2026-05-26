<?php

namespace App\Http\Controllers\EPayAdmin;

use App\Http\Controllers\Controller;
use App\Models\EPayPlus\SmsLog;
use App\Models\EPayPlus\EPaySetting;
use Illuminate\Http\Request;

class SmsController extends Controller
{
    public function index(Request $request)
    {
        $query = SmsLog::query();

        if ($request->filled('direction')) {
            $query->where('direction', $request->direction);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('number', 'like', "%{$search}%")
                  ->orWhere('message', 'like', "%{$search}%");
            });
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $logs = $query->latest()->paginate(30)->withQueryString();

        $stats = [
            'total_sent' => SmsLog::where('direction', 'outgoing')->count(),
            'total_received' => SmsLog::where('direction', 'incoming')->count(),
            'failed' => SmsLog::where('status', 'failed')->count(),
            'today' => SmsLog::whereDate('created_at', today())->count(),
        ];

        return view('epayplus.sms-logs', compact('logs', 'stats'));
    }

    public function templates()
    {
        $templates = json_decode(EPaySetting::where('key', 'sms_templates')->value('value') ?? '[]', true);
        $providers = json_decode(EPaySetting::where('key', 'sms_providers')->value('value') ?? '[]', true);
        $routing = json_decode(EPaySetting::where('key', 'sms_routing')->value('value') ?? '[]', true);

        return view('epayplus.sms-templates', compact('templates', 'providers', 'routing'));
    }

    public function updateTemplates(Request $request)
    {
        $validated = $request->validate([
            'templates' => 'required|array',
            'templates.*.name' => 'required|string|max:100',
            'templates.*.keyword' => 'required|string|max:50',
            'templates.*.format' => 'required|string|max:500',
            'templates.*.provider' => 'nullable|string|max:50',
        ]);

        EPaySetting::updateOrCreate(
            ['key' => 'sms_templates'],
            ['value' => json_encode($validated['templates'])]
        );

        return back()->with('success', 'SMS templates updated.');
    }

    public function updateProviders(Request $request)
    {
        $validated = $request->validate([
            'providers' => 'required|array',
            'providers.*.name' => 'required|string|max:100',
            'providers.*.number' => 'required|string|max:30',
            'providers.*.type' => 'required|string|max:50',
            'providers.*.active' => 'nullable|boolean',
        ]);

        EPaySetting::updateOrCreate(
            ['key' => 'sms_providers'],
            ['value' => json_encode($validated['providers'])]
        );

        return back()->with('success', 'SMS providers updated.');
    }

    public function updateRouting(Request $request)
    {
        $validated = $request->validate([
            'routing' => 'required|array',
            'routing.*.prefix' => 'required|string|max:20',
            'routing.*.provider' => 'required|string|max:100',
            'routing.*.priority' => 'nullable|integer',
        ]);

        EPaySetting::updateOrCreate(
            ['key' => 'sms_routing'],
            ['value' => json_encode($validated['routing'])]
        );

        return back()->with('success', 'SMS routing rules updated.');
    }
}
