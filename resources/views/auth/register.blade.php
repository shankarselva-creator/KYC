@extends('layouts.app')
@section('title', 'Register')

@section('body')
<div class="min-h-full flex items-center justify-center px-4 py-10">
    <div class="w-full max-w-sm">
        <div class="text-center mb-8">
            <h1 class="text-2xl font-bold text-white">{{ config('app.name') }}</h1>
            <p class="text-sm text-gray-400 mt-1">Open a free demo account</p>
        </div>

        <form method="POST" action="{{ route('register') }}" class="bg-panel border border-edge rounded-xl p-6 space-y-4">
            @csrf

            @if ($errors->any())
                <div class="bg-down/10 border border-down/40 text-down text-sm rounded-md px-3 py-2 space-y-1">
                    @foreach ($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            <div>
                <label class="block text-xs text-gray-400 mb-1">Name</label>
                <input name="name" type="text" value="{{ old('name') }}" required autofocus
                    class="w-full bg-panel2 border border-edge rounded-md px-3 py-2 text-sm focus:outline-none focus:border-accent">
            </div>

            <div>
                <label class="block text-xs text-gray-400 mb-1">Email</label>
                <input name="email" type="email" value="{{ old('email') }}" required
                    class="w-full bg-panel2 border border-edge rounded-md px-3 py-2 text-sm focus:outline-none focus:border-accent">
            </div>

            <div>
                <label class="block text-xs text-gray-400 mb-1">Password</label>
                <input name="password" type="password" required
                    class="w-full bg-panel2 border border-edge rounded-md px-3 py-2 text-sm focus:outline-none focus:border-accent">
            </div>

            <div>
                <label class="block text-xs text-gray-400 mb-1">Confirm Password</label>
                <input name="password_confirmation" type="password" required
                    class="w-full bg-panel2 border border-edge rounded-md px-3 py-2 text-sm focus:outline-none focus:border-accent">
            </div>

            <button type="submit"
                class="w-full bg-accent hover:bg-blue-600 text-white font-medium rounded-md py-2 text-sm transition">
                Create demo account
            </button>

            <p class="text-center text-xs text-gray-400">
                Already registered? <a href="{{ route('login') }}" class="text-accent hover:underline">Sign in</a>
            </p>
        </form>
    </div>
</div>
@endsection
