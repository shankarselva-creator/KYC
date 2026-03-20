<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Good {{ now()->hour < 12 ? 'morning' : (now()->hour < 17 ? 'afternoon' : 'evening') }}, {{ Auth::user()->name }}! 👋
            </h2>
            <span class="text-sm text-gray-500">{{ now()->format('l, F j, Y') }}</span>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            {{-- Flash Messages --}}
            @if (session('success'))
                <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg">
                    {{ session('success') }}
                </div>
            @endif

            {{-- Stats Cards --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                    <p class="text-sm text-gray-500 mb-1">Total Habits</p>
                    <p class="text-3xl font-bold text-gray-800">{{ $totalHabits }}</p>
                </div>
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                    <p class="text-sm text-gray-500 mb-1">Done Today</p>
                    <p class="text-3xl font-bold text-indigo-600">{{ $completedToday }}</p>
                </div>
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                    <p class="text-sm text-gray-500 mb-1">Completion Rate</p>
                    <p class="text-3xl font-bold text-emerald-600">{{ $completionRate }}%</p>
                </div>
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                    <p class="text-sm text-gray-500 mb-1">Best Streak</p>
                    <p class="text-3xl font-bold text-amber-500">{{ $bestStreak }} 🔥</p>
                </div>
            </div>

            {{-- Today's Habits --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-800">Today's Habits</h3>
                    <a href="{{ route('habits.create') }}" class="text-sm bg-indigo-600 text-white px-3 py-1.5 rounded-lg hover:bg-indigo-700 transition">+ New Habit</a>
                </div>

                @if ($habits->isEmpty())
                    <div class="text-center py-12 text-gray-400">
                        <svg class="mx-auto h-12 w-12 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                        </svg>
                        <p class="font-medium">No habits yet</p>
                        <p class="text-sm mt-1">Create your first habit to get started!</p>
                        <a href="{{ route('habits.create') }}" class="mt-4 inline-block bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 transition text-sm">Create Habit</a>
                    </div>
                @else
                    <div class="space-y-3">
                        @foreach ($habits as $habit)
                            @php $completed = $habit->isCompletedToday(); @endphp
                            <div class="flex items-center gap-4 p-4 rounded-lg border {{ $completed ? 'bg-gray-50 border-gray-200 opacity-75' : 'bg-white border-gray-200 hover:border-indigo-300' }} transition">
                                <div class="flex-shrink-0 w-10 h-10 rounded-full flex items-center justify-center text-white font-bold text-lg" style="background-color: {{ $habit->color }}">
                                    {{ mb_substr($habit->name, 0, 1) }}
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="font-medium text-gray-800 {{ $completed ? 'line-through text-gray-400' : '' }}">{{ $habit->name }}</p>
                                    @if ($habit->description)
                                        <p class="text-sm text-gray-500 truncate">{{ $habit->description }}</p>
                                    @endif
                                </div>
                                <div class="flex items-center gap-3">
                                    @if ($habit->streak > 0)
                                        <span class="text-sm font-medium text-amber-500">🔥 {{ $habit->streak }}</span>
                                    @endif
                                    <form method="POST" action="{{ route('habits.toggle', $habit) }}">
                                        @csrf
                                        <button type="submit" class="w-8 h-8 rounded-full border-2 flex items-center justify-center transition
                                            {{ $completed ? 'bg-indigo-500 border-indigo-500 text-white' : 'border-gray-300 hover:border-indigo-400 text-transparent hover:text-indigo-400' }}">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                                            </svg>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Weekly Progress --}}
            @if (!empty($weekData))
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Last 7 Days</h3>
                    <div class="flex items-end gap-2 h-24">
                        @php $maxCount = max(array_column($weekData, 'count'), 1); @endphp
                        @foreach ($weekData as $day)
                            <div class="flex-1 flex flex-col items-center gap-1">
                                <div class="w-full rounded-t-md bg-indigo-500 transition-all" style="height: {{ ($day['count'] / $maxCount) * 80 }}px; min-height: {{ $day['count'] > 0 ? '4px' : '0' }};"></div>
                                <span class="text-xs text-gray-500">{{ $day['label'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

        </div>
    </div>
</x-app-layout>
