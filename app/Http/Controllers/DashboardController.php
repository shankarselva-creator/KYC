<?php

namespace App\Http\Controllers;

use App\Models\Instrument;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function terminal(Request $request): View
    {
        $account = $request->user()->primaryTradingAccount();
        $instruments = Instrument::where('is_active', true)->orderBy('sort_order')->get();

        return view('terminal', compact('account', 'instruments'));
    }

    public function history(Request $request): View
    {
        $account = $request->user()->primaryTradingAccount();

        $positions = $account
            ? $account->positions()->where('status', 'closed')->with('instrument')->latest('closed_at')->paginate(25)
            : collect();

        return view('history', compact('account', 'positions'));
    }
}
