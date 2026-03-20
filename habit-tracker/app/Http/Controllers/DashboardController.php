<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $habits = $user->habits()->where('is_active', true)->get();

        $totalHabits = $habits->count();
        $completedToday = $habits->filter(fn ($h) => $h->isCompletedToday())->count();
        $completionRate = $totalHabits > 0 ? round(($completedToday / $totalHabits) * 100) : 0;

        $bestStreak = $habits->max('streak') ?? 0;

        // Last 7 days completion data for chart
        $weekData = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $completed = $user->habitLogs()
                ->whereDate('logged_date', $date)
                ->where('completed', true)
                ->count();
            $weekData[] = [
                'label' => $date->format('D'),
                'count' => $completed,
                'date' => $date->format('Y-m-d'),
            ];
        }

        return view('dashboard', compact(
            'habits', 'totalHabits', 'completedToday', 'completionRate', 'bestStreak', 'weekData'
        ));
    }
}
