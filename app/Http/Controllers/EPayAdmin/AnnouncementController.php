<?php

namespace App\Http\Controllers\EPayAdmin;

use App\Http\Controllers\Controller;
use App\Models\EPayPlus\Announcement;
use App\Models\EPayPlus\AuditLog;
use Illuminate\Http\Request;

class AnnouncementController extends Controller
{
    public function index()
    {
        $announcements = Announcement::orderByDesc('created_at')->paginate(25);
        return view('epayplus.announcements', compact('announcements'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title'      => 'required|string|max:255',
            'content'    => 'required|string|max:5000',
            'type'       => 'required|in:info,warning,success,danger',
            'starts_at'  => 'nullable|date',
            'expires_at' => 'nullable|date|after_or_equal:starts_at',
        ]);

        $data['is_active'] = true;
        $announcement = Announcement::create($data);

        AuditLog::record(auth()->id(), 'announcement_created', $announcement, "Created: {$announcement->title}");

        return back()->with('success', "Announcement '{$announcement->title}' created.");
    }

    public function update(Request $request, Announcement $announcement)
    {
        $data = $request->validate([
            'title'      => 'required|string|max:255',
            'content'    => 'required|string|max:5000',
            'type'       => 'required|in:info,warning,success,danger',
            'starts_at'  => 'nullable|date',
            'expires_at' => 'nullable|date|after_or_equal:starts_at',
        ]);

        $announcement->update($data);

        AuditLog::record(auth()->id(), 'announcement_updated', $announcement, "Updated: {$announcement->title}");

        return back()->with('success', "Announcement updated.");
    }

    public function destroy(Announcement $announcement)
    {
        $title = $announcement->title;
        $announcement->delete();

        AuditLog::record(auth()->id(), 'announcement_deleted', null, "Deleted: {$title}");

        return back()->with('success', "Announcement '{$title}' deleted.");
    }

    public function toggleStatus(Announcement $announcement)
    {
        $announcement->update(['is_active' => !$announcement->is_active]);
        $status = $announcement->is_active ? 'activated' : 'deactivated';

        return back()->with('success', "Announcement {$status}.");
    }
}
