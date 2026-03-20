<?php

namespace App\Http\Controllers;

use App\Models\Habit;
use App\Models\HabitLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class HabitLogController extends Controller
{
    public function toggle(Request $request, Habit $habit)
    {
        $this->authorize('view', $habit);

        $date = $request->input('date', today()->toDateString());

        $log = HabitLog::where('habit_id', $habit->id)
            ->whereDate('logged_date', $date)
            ->first();

        if ($log) {
            $log->delete();
            $completed = false;
        } else {
            HabitLog::create([
                'habit_id' => $habit->id,
                'user_id' => Auth::id(),
                'logged_date' => $date,
                'completed' => true,
            ]);
            $habit->updateStreak();
            $completed = true;
        }

        if ($request->wantsJson()) {
            return response()->json(['completed' => $completed, 'streak' => $habit->fresh()->streak]);
        }

        return back()->with('success', $completed ? 'Habit marked complete!' : 'Habit unmarked.');
    }
}
