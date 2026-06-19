@extends('layouts.app')
@section('title', 'Login')

@section('body')
<div class="min-h-full flex items-center justify-center px-4">
    <div class="w-full max-w-sm">
        <div class="text-center mb-8">
            <h1 class="text-2xl font-bold text-white">{{ config('app.name') }}</h1>
            <p class="text-sm text-gray-400 mt-1">Sign in to your trading account</p>
        </div>

        <form method="POST" action="{{ route('login') }}" class="bg-panel border border-edge rounded-xl p-6 space-y-4">
            @csrf

            @if ($errors->any())
                <div class="bg-down/10 border border-down/40 text-down text-sm rounded-md px-3 py-2">
                    {{ $errors->first() }}
                </div>
            @endif

            <div>
                <label class="block text-xs text-gray-400 mb-1">Email</label>
                <input name="email" type="email" value="{{ old('email') }}" required autofocus
                    class="w-full bg-panel2 border border-edge rounded-md px-3 py-2 text-sm focus:outline-none focus:border-accent">
            </div>

            <div>
                <label class="block text-xs text-gray-400 mb-1">Password</label>
                <input name="password" type="password" required
                    class="w-full bg-panel2 border border-edge rounded-md px-3 py-2 text-sm focus:outline-none focus:border-accent">
            </div>

            <label class="flex items-center gap-2 text-xs text-gray-400">
                <input type="checkbox" name="remember" class="rounded border-edge bg-panel2"> Remember me
            </label>

            <button type="submit"
                class="w-full bg-accent hover:bg-blue-600 text-white font-medium rounded-md py-2 text-sm transition">
                Sign in
            </button>

            <p class="text-center text-xs text-gray-400">
                No account? <a href="{{ route('register') }}" class="text-accent hover:underline">Create one</a>
            </p>
        </form>
    </div>
</div>
@endsection
