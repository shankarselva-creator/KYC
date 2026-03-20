<?php

namespace App\Http\Controllers;

use App\Models\Habit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class HabitController extends Controller
{
    public function index()
    {
        $habits = Auth::user()->habits()->orderBy('created_at', 'desc')->get();
        return view('habits.index', compact('habits'));
    }

    public function create()
    {
        return view('habits.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'color' => 'required|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'icon' => 'required|string|max:50',
            'frequency' => 'required|in:daily,weekly',
            'target_days' => 'nullable|array',
            'target_days.*' => 'integer|between:0,6',
        ]);

        $validated['user_id'] = Auth::id();
        Habit::create($validated);

        return redirect()->route('habits.index')->with('success', 'Habit created successfully!');
    }

    public function show(Habit $habit)
    {
        $this->authorize('view', $habit);
        $logs = $habit->logs()->orderBy('logged_date', 'desc')->take(30)->get();
        $completedDates = $habit->completedDatesThisMonth();
        return view('habits.show', compact('habit', 'logs', 'completedDates'));
    }

    public function edit(Habit $habit)
    {
        $this->authorize('update', $habit);
        return view('habits.edit', compact('habit'));
    }

    public function update(Request $request, Habit $habit)
    {
        $this->authorize('update', $habit);

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'color' => 'required|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'icon' => 'required|string|max:50',
            'frequency' => 'required|in:daily,weekly',
            'target_days' => 'nullable|array',
            'target_days.*' => 'integer|between:0,6',
            'is_active' => 'boolean',
        ]);

        $habit->update($validated);

        return redirect()->route('habits.index')->with('success', 'Habit updated successfully!');
    }

    public function destroy(Habit $habit)
    {
        $this->authorize('delete', $habit);
        $habit->delete();
        return redirect()->route('habits.index')->with('success', 'Habit deleted.');
    }
}
