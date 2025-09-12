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
            'instagram_account_ids' => 'required|array|min:1',
            'instagram_account_ids.*' => 'exists:instagram_accounts,id',
            'start_date' => 'required|date',
            'start_time' => 'required',
            'interval_minutes' => 'required|integer|min:1|max:1440',
            'repeat_cycle' => 'boolean'
        ]);

        // Convert start_date and start_time to New York timezone for storage
        $nyTimezone = new \DateTimeZone('America/New_York');
        
        // Get the start date and time from request
        $startDate = $request->start_date;
        $startTime = $request->start_time;
        
        // If no start_date provided, default to today
        if (empty($startDate)) {
            $startDate = date('Y-m-d');
        }
        
        $startDateTime = $startDate . ' ' . $startTime;
        
        // Debug the date values
        \Log::info('Schedule creation debug', [
            'request_start_date' => $request->start_date,
            'final_start_date' => $startDate,
            'start_time' => $startTime,
            'combined' => $startDateTime,
            'account_count' => count($request->instagram_account_ids)
        ]);
        
        $startDateTimeNY = \Carbon\Carbon::createFromFormat('Y-m-d H:i', $startDateTime, $nyTimezone);

        $createdSchedules = [];
        
        // Create a schedule for each selected Instagram account
        foreach ($request->instagram_account_ids as $accountId) {
            $schedule = Schedule::create([
                'name' => $request->name,
                'content_container_id' => $request->content_container_id,
                'instagram_account_id' => $accountId,
                'start_date' => $startDateTimeNY->format('Y-m-d'),
                'start_time' => $startDateTimeNY->format('H:i:s'),
                'interval_minutes' => $request->interval_minutes,
                'repeat_cycle' => $request->boolean('repeat_cycle', true),
                'status' => 'active'
            ]);
            
            $createdSchedules[] = $schedule;
        }

        $accountCount = count($createdSchedules);
        $message = $accountCount === 1 
            ? 'Schedule created successfully!' 
            : "Successfully created {$accountCount} schedules for selected accounts!";
            
        return redirect()->route('schedules.index')
            ->with('success', $message . ' All times are in New York timezone.');
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
            'interval_minutes' => 'required|integer|min:1|max:1440',
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
