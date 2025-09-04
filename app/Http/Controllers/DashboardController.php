<?php

namespace App\Http\Controllers;

use App\Models\InstagramAccount;
use App\Models\ContentContainer;
use App\Models\Schedule;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        $accounts = InstagramAccount::where('is_active', true)->get();
        $containers = ContentContainer::with('posts')->where('is_active', true)->get();
        $activeSchedules = Schedule::with(['contentContainer', 'instagramAccount'])
            ->where('status', 'active')
            ->get();

        return view('dashboard', compact('accounts', 'containers', 'activeSchedules'));
    }
}
