<?php

namespace App\Http\Controllers;

use App\Models\DeviceLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceLogController extends Controller
{
    /**
     * Receive logs from the INSA POS v3 Android app.
     * No auth required — the device might not have a session.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'device_id'       => 'nullable|string|max:100',
            'device_model'    => 'nullable|string|max:150',
            'app_version'     => 'nullable|string|max:30',
            'android_version' => 'nullable|string|max:30',
            'level'           => 'nullable|string|in:debug,info,warn,error',
            'tag'             => 'nullable|string|max:100',
            'message'         => 'required|string|max:5000',
            'url'             => 'nullable|string|max:2000',
            'extra'           => 'nullable|string|max:5000',
            'logs'            => 'nullable|array',
            'logs.*.level'    => 'nullable|string',
            'logs.*.tag'      => 'nullable|string',
            'logs.*.message'  => 'required|string',
            'logs.*.url'      => 'nullable|string',
        ]);

        $ip = $request->ip();
        $base = [
            'device_id'       => $data['device_id'] ?? null,
            'device_model'    => $data['device_model'] ?? null,
            'app_version'     => $data['app_version'] ?? null,
            'android_version' => $data['android_version'] ?? null,
            'ip'              => $ip,
        ];

        // Single log entry
        if (!empty($data['message']) && empty($data['logs'])) {
            DeviceLog::create(array_merge($base, [
                'level'   => $data['level'] ?? 'info',
                'tag'     => $data['tag'] ?? null,
                'message' => $data['message'],
                'url'     => $data['url'] ?? null,
                'extra'   => $data['extra'] ?? null,
            ]));
        }

        // Batch log entries
        if (!empty($data['logs'])) {
            foreach ($data['logs'] as $log) {
                DeviceLog::create(array_merge($base, [
                    'level'   => $log['level'] ?? 'info',
                    'tag'     => $log['tag'] ?? 'batch',
                    'message' => $log['message'],
                    'url'     => $log['url'] ?? null,
                ]));
            }
        }

        return response()->json(['success' => true]);
    }

    /**
     * Display device logs (admin only).
     */
    public function index(Request $request)
    {
        $query = DeviceLog::orderByDesc('created_at');

        if ($request->filled('device_id')) {
            $query->where('device_id', $request->input('device_id'));
        }
        if ($request->filled('level')) {
            $query->where('level', $request->input('level'));
        }

        $logs = $query->paginate(100);
        $devices = DeviceLog::distinct()->pluck('device_id')->filter();

        return view('admin.device-logs', compact('logs', 'devices'));
    }

    /**
     * Clear all device logs.
     */
    public function clear(): JsonResponse
    {
        DeviceLog::truncate();
        return response()->json(['success' => true]);
    }
}
