@extends('layouts.app')
@section('title', 'History')

@section('body')
<div class="h-full flex flex-col">
    <header class="bg-panel border-b border-edge px-4 py-2 flex items-center justify-between">
        <div class="flex items-center gap-4">
            <span class="font-bold text-white">{{ config('app.name') }}</span>
            @if ($account)
                <span class="text-xs text-gray-400">#{{ $account->login }} · Trade History</span>
            @endif
        </div>
        <div class="flex items-center gap-3 text-xs">
            <a href="{{ route('terminal') }}" class="text-gray-300 hover:text-white">Terminal</a>
            <form method="POST" action="{{ route('logout') }}">@csrf
                <button class="text-gray-300 hover:text-white">Logout</button>
            </form>
        </div>
    </header>

    <div class="flex-1 overflow-auto p-4">
        @if (! $account || $positions->isEmpty())
            <div class="text-sm text-gray-500">No closed trades yet.</div>
        @else
            <table class="w-full text-xs bg-panel border border-edge rounded-lg overflow-hidden">
                <thead class="text-gray-500 bg-panel2">
                    <tr>
                        <th class="text-left px-3 py-2 font-medium">Ticket</th>
                        <th class="text-left px-2 py-2 font-medium">Symbol</th>
                        <th class="text-left px-2 py-2 font-medium">Type</th>
                        <th class="text-right px-2 py-2 font-medium">Volume</th>
                        <th class="text-right px-2 py-2 font-medium">Open</th>
                        <th class="text-right px-2 py-2 font-medium">Close</th>
                        <th class="text-left px-2 py-2 font-medium">Opened</th>
                        <th class="text-left px-2 py-2 font-medium">Closed</th>
                        <th class="text-right px-3 py-2 font-medium">Profit</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($positions as $p)
                    <tr class="border-t border-edge/50">
                        <td class="px-3 py-1.5 tabular-nums text-gray-400">{{ $p->ticket }}</td>
                        <td class="px-2 py-1.5 font-medium">{{ $p->instrument->symbol }}</td>
                        <td class="px-2 py-1.5 uppercase {{ $p->side === 'buy' ? 'text-up' : 'text-down' }}">{{ $p->side }}</td>
                        <td class="px-2 py-1.5 text-right tabular-nums">{{ number_format($p->volume, 2) }}</td>
                        <td class="px-2 py-1.5 text-right tabular-nums">{{ number_format($p->open_price, $p->instrument->digits) }}</td>
                        <td class="px-2 py-1.5 text-right tabular-nums">{{ number_format($p->close_price, $p->instrument->digits) }}</td>
                        <td class="px-2 py-1.5 text-gray-400">{{ $p->opened_at?->format('Y-m-d H:i') }}</td>
                        <td class="px-2 py-1.5 text-gray-400">{{ $p->closed_at?->format('Y-m-d H:i') }}</td>
                        <td class="px-3 py-1.5 text-right tabular-nums {{ $p->profit > 0 ? 'text-up' : ($p->profit < 0 ? 'text-down' : 'text-gray-300') }}">
                            {{ number_format($p->profit, 2) }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="mt-4">{{ $positions->links() }}</div>
        @endif
    </div>
</div>
@endsection
