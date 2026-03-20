<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class Habit extends Model
{
    protected $fillable = [
        'user_id', 'name', 'description', 'color', 'icon',
        'frequency', 'target_days', 'is_active', 'streak', 'streak_last_date',
    ];

    protected $casts = [
        'target_days' => 'array',
        'is_active' => 'boolean',
        'streak_last_date' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(HabitLog::class);
    }

    public function isCompletedToday(): bool
    {
        return $this->logs()
            ->whereDate('logged_date', today())
            ->where('completed', true)
            ->exists();
    }

    public function completedDatesThisMonth(): array
    {
        return $this->logs()
            ->whereYear('logged_date', now()->year)
            ->whereMonth('logged_date', now()->month)
            ->where('completed', true)
            ->pluck('logged_date')
            ->map(fn ($d) => Carbon::parse($d)->format('Y-m-d'))
            ->toArray();
    }

    public function updateStreak(): void
    {
        $today = today();
        $yesterday = today()->subDay();

        if ($this->streak_last_date && $this->streak_last_date->equalTo($today)) {
            return; // already updated today
        }

        if ($this->streak_last_date && $this->streak_last_date->equalTo($yesterday)) {
            $this->increment('streak');
        } else {
            $this->streak = 1;
        }

        $this->streak_last_date = $today;
        $this->save();
    }
}
