<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('habits.index') }}" class="text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <div class="w-8 h-8 rounded-full flex items-center justify-center text-white font-bold" style="background-color: {{ $habit->color }}">
                {{ mb_substr($habit->name, 0, 1) }}
            </div>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $habit->name }}</h2>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            {{-- Habit Info --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        @if ($habit->description)
                            <p class="text-gray-600 mb-2">{{ $habit->description }}</p>
                        @endif
                        <div class="flex items-center gap-4 text-sm text-gray-500">
                            <span class="capitalize">📅 {{ $habit->frequency }}</span>
                            <span class="text-amber-500 font-medium">🔥 {{ $habit->streak }} day streak</span>
                            <span class="{{ $habit->is_active ? 'text-green-600' : 'text-gray-400' }}">
                                {{ $habit->is_active ? '✓ Active' : '✗ Inactive' }}
                            </span>
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <form method="POST" action="{{ route('habits.toggle', $habit) }}">
                            @csrf
                            @php $completedToday = $habit->isCompletedToday(); @endphp
                            <button type="submit"
                                class="px-4 py-2 rounded-lg text-sm font-medium transition {{ $completedToday ? 'bg-gray-100 text-gray-600 hover:bg-gray-200' : 'bg-indigo-600 text-white hover:bg-indigo-700' }}">
                                {{ $completedToday ? '✓ Done Today' : 'Mark Done' }}
                            </button>
                        </form>
                        <a href="{{ route('habits.edit', $habit) }}" class="px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 transition">Edit</a>
                    </div>
                </div>
            </div>

            {{-- Monthly Calendar --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">{{ now()->format('F Y') }}</h3>
                @php
                    $daysInMonth = now()->daysInMonth;
                    $firstDay = now()->startOfMonth()->dayOfWeek;
                @endphp
                <div class="grid grid-cols-7 gap-1 text-center">
                    @foreach (['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'] as $day)
                        <div class="text-xs font-medium text-gray-400 py-1">{{ $day }}</div>
                    @endforeach
                    @for ($i = 0; $i < $firstDay; $i++)
                        <div></div>
                    @endfor
                    @for ($d = 1; $d <= $daysInMonth; $d++)
                        @php
                            $dateStr = now()->format('Y-m-') . str_pad($d, 2, '0', STR_PAD_LEFT);
                            $done = in_array($dateStr, $completedDates);
                            $isToday = $d === (int)now()->format('j');
                        @endphp
                        <div class="aspect-square flex items-center justify-center rounded-full text-sm
                            {{ $done ? 'text-white font-semibold' : ($isToday ? 'ring-2 ring-indigo-400 text-gray-700' : 'text-gray-600') }}"
                            style="{{ $done ? 'background-color: ' . $habit->color . ';' : '' }}">
                            {{ $d }}
                        </div>
                    @endfor
                </div>
            </div>

            {{-- Recent Logs --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Recent Activity</h3>
                @if ($logs->isEmpty())
                    <p class="text-gray-400 text-sm text-center py-6">No activity yet. Start logging today!</p>
                @else
                    <div class="space-y-2">
                        @foreach ($logs as $log)
                            <div class="flex items-center justify-between py-2 border-b border-gray-50 last:border-0">
                                <span class="text-sm text-gray-700">{{ $log->logged_date->format('M j, Y') }}</span>
                                <span class="{{ $log->completed ? 'text-green-600' : 'text-red-400' }} text-sm font-medium">
                                    {{ $log->completed ? '✓ Completed' : '✗ Missed' }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

        </div>
    </div>
</x-app-layout>
