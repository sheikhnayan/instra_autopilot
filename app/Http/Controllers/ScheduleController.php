<?php

namespace App\Http\Controllers;

use App\Models\Schedule;
use App\Models\ContentContainer;
use App\Models\InstagramAccount;
use Illuminate\Http\Request;

class ScheduleController extends Controller
{
    public function index()
    {
        $schedules = Schedule::with(['contentContainer', 'instagramAccount'])
            ->orderBy('created_at', 'desc')
            ->get();
        
        return view('schedules.index', compact('schedules'));
    }

    public function create(Request $request)
    {
        $containers = ContentContainer::where('is_active', true)->get();
        $accounts = InstagramAccount::where('is_active', true)->get();
        $selectedAccountId = $request->get('account');
        $selectedContainerId = $request->get('container');
        
        return view('schedules.create', compact('containers', 'accounts', 'selectedAccountId', 'selectedContainerId'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|max:255',
            'content_container_id' => 'required|exists:content_containers,id',
            'instagram_account_id' => 'required|exists:instagram_accounts,id',
            'start_date' => 'required|date|after_or_equal:today',
            'start_time' => 'required',
            'interval_minutes' => 'required|integer|min:15|max:1440',
            'repeat_cycle' => 'boolean'
        ]);

        Schedule::create([
            'name' => $request->name,
            'content_container_id' => $request->content_container_id,
            'instagram_account_id' => $request->instagram_account_id,
            'start_date' => $request->start_date,
            'start_time' => $request->start_time,
            'interval_minutes' => $request->interval_minutes,
            'repeat_cycle' => $request->boolean('repeat_cycle', true),
            'status' => 'active',
            'current_post_index' => 0
        ]);

        return redirect()->route('schedules.index')
            ->with('success', 'Schedule created successfully!');
    }

    public function show(Schedule $schedule)
    {
        $schedule->load(['contentContainer.posts', 'instagramAccount']);
        return view('schedules.show', compact('schedule'));
    }

    public function edit(Schedule $schedule)
    {
        $containers = ContentContainer::where('is_active', true)->get();
        $accounts = InstagramAccount::where('is_active', true)->get();
        
        return view('schedules.edit', compact('schedule', 'containers', 'accounts'));
    }

    public function update(Request $request, Schedule $schedule)
    {
        $request->validate([
            'name' => 'required|max:255',
            'start_date' => 'required|date',
            'start_time' => 'required',
            'interval_minutes' => 'required|integer|min:15|max:1440',
            'repeat_cycle' => 'boolean'
        ]);

        $schedule->update([
            'name' => $request->name,
            'start_date' => $request->start_date,
            'start_time' => $request->start_time,
            'interval_minutes' => $request->interval_minutes,
            'repeat_cycle' => $request->boolean('repeat_cycle', true)
        ]);

        return redirect()->route('schedules.show', $schedule)
            ->with('success', 'Schedule updated successfully!');
    }

    public function destroy(Schedule $schedule)
    {
        $schedule->delete();
        return redirect()->route('schedules.index')
            ->with('success', 'Schedule deleted successfully!');
    }

    public function toggle(Schedule $schedule)
    {
        $newStatus = $schedule->status === 'active' ? 'paused' : 'active';
        $schedule->update(['status' => $newStatus]);

        $message = $newStatus === 'active' ? 'Schedule activated!' : 'Schedule paused!';
        
        return redirect()->back()->with('success', $message);
    }
}
