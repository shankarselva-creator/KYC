<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">My Habits</h2>
            <a href="{{ route('habits.create') }}" class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 transition text-sm font-medium">+ New Habit</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

            @if (session('success'))
                <div class="mb-4 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg">
                    {{ session('success') }}
                </div>
            @endif

            @if ($habits->isEmpty())
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-12 text-center text-gray-400">
                    <svg class="mx-auto h-14 w-14 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4v16m8-8H4"/>
                    </svg>
                    <p class="text-lg font-medium">No habits yet</p>
                    <p class="text-sm mt-1 mb-6">Start building better habits today.</p>
                    <a href="{{ route('habits.create') }}" class="bg-indigo-600 text-white px-5 py-2.5 rounded-lg hover:bg-indigo-700 transition font-medium">Create Your First Habit</a>
                </div>
            @else
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach ($habits as $habit)
                        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 hover:shadow-md transition {{ !$habit->is_active ? 'opacity-60' : '' }}">
                            <div class="flex items-start justify-between mb-3">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-full flex items-center justify-center text-white font-bold text-lg" style="background-color: {{ $habit->color }}">
                                        {{ mb_substr($habit->name, 0, 1) }}
                                    </div>
                                    <div>
                                        <h3 class="font-semibold text-gray-800">{{ $habit->name }}</h3>
                                        <span class="text-xs text-gray-400 capitalize">{{ $habit->frequency }}</span>
                                    </div>
                                </div>
                                <div class="flex gap-1">
                                    <a href="{{ route('habits.edit', $habit) }}" class="p-1.5 text-gray-400 hover:text-indigo-600 rounded transition">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </a>
                                    <form method="POST" action="{{ route('habits.destroy', $habit) }}" onsubmit="return confirm('Delete this habit?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="p-1.5 text-gray-400 hover:text-red-500 rounded transition">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    </form>
                                </div>
                            </div>

                            @if ($habit->description)
                                <p class="text-sm text-gray-500 mb-3">{{ $habit->description }}</p>
                            @endif

                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-1 text-amber-500">
                                    <span>🔥</span>
                                    <span class="text-sm font-medium">{{ $habit->streak }} day streak</span>
                                </div>
                                <a href="{{ route('habits.show', $habit) }}" class="text-sm text-indigo-600 hover:text-indigo-800 font-medium">View →</a>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
