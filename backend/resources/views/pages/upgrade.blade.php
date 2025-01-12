@extends('layouts.base')
@section('title', 'Pick a different plan')
@section('content')
    <!-- Session Status Alert -->
    @if(session('status'))
        <div id="alert" class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4">
            {{ session('status') }}
        </div>
    @endif

    <div class="mb-4">
        <div class="sm:flex sm:items-center">
            <div class="sm:flex-auto">
                <h1 class="text-xl font-semibold leading-6 text-gray-900">How would you like to use our service?</h1>
                <p class="mt-2 text-pretty text-md font-medium text-gray-600">We strive to be a fair and sustainable solution for people who, like the creator of Outlooktogcal.com, feel let down by big tech. Unfortunately, technical challenges and anti-competitive behaviour results in a bad user experience for the end user. We're here to change that.</p>
            </div>
        </div>
    </div>
    <div class="isolate mt-10 grid grid-cols-1 gap-8 md:grid-cols-2 xl:grid-cols-3">
        <div class="rounded-3xl p-8 ring-1 ring-gray-200">
            <h3 id="tier-hobby" class="text-lg/8 font-semibold text-gray-900">Free 🤲</h3>
            <p class="mt-4 text-sm/6 text-gray-600">The essentials to get one calendar synchronized.</p>
            <p class="mt-6 flex items-baseline gap-x-1">
                <!-- Price, update based on frequency toggle state -->
                <span class="text-4xl font-semibold tracking-tight text-gray-900">$0</span>
                <!-- Payment frequency, update based on frequency toggle state -->
                <span class="text-sm/6 font-semibold text-gray-600"> / forever</span>
            </p>
            @if (auth()->user()->plan && auth()->user()->plan->type === \App\Enums\Plan::FREE)
                <a href="#" class="mt-6 block w-full rounded-md px-3 py-2 text-center text-sm/6 font-semibold text-green-600 ring-1 ring-inset ring-green-200">
                    Your current plan
                </a>
            @else
                <a href="#" class="mt-6 block w-full rounded-md px-3 py-2 text-center text-sm/6 font-semibold text-gray-600 ring-1 ring-inset ring-gray-200">
                    Please reach out to us to downgrade
                </a>
            @endif
            <ul role="list" class="mt-8 space-y-3 text-sm/6 text-gray-600">
                <li class="flex gap-x-3">
                    <svg class="h-6 w-5 flex-none text-blue-600" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" data-slot="icon">
                        <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" />
                    </svg>
                    Real-time syncing
                </li>
                <li class="flex gap-x-3">
                    <svg class="h-6 w-5 flex-none text-blue-600" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" data-slot="icon">
                        <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" />
                    </svg>
                    Sync one calendar
                </li>
                <li class="flex gap-x-3">
                    <svg class="h-6 w-5 flex-none text-gray-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" data-slot="icon">
                        <path fill-rule="evenodd" d="M4 10a.75.75 0 0 1 .75-.75h10.5a.75.75 0 0 1 0 1.5H4.75A.75.75 0 0 1 4 10Z" clip-rule="evenodd" />
                    </svg>
                    Limited to 5 weeks <br>(previous week to next four weeks)
                </li>
            </ul>
        </div>
        <div class="rounded-3xl p-8 ring-2 ring-blue-600">
            <h3 id="tier-freelancer" class="text-lg/8 font-semibold text-gray-900">Fair 🤝</h3>
            <p class="mt-4 text-sm/6 text-gray-600">The perfect middle ground and keeps us running.</p>
            <p class="mt-6 flex items-baseline gap-x-1">
                <!-- Price, update based on frequency toggle state -->
                <span class="text-4xl font-semibold tracking-tight text-gray-900">$24</span>
                <!-- Payment frequency, update based on frequency toggle state -->
                <span class="text-sm/6 font-semibold text-gray-600">/ one year valid</span>
            </p>
            @if (auth()->user()->plan && auth()->user()->plan->type === \App\Enums\Plan::FREE)
                <x-lemon-button :href="auth()->user()->checkout(config('settings.plans.fair_plan_id'))->redirectTo(url('/onboarding/checkout?plan=fair'))" class="mt-6 block rounded-md bg-blue-600 px-3 py-2 text-center text-sm/6 font-semibold text-white shadow-sm hover:bg-blue-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600">
                    Upgrade to the fair plan
                </x-lemon-button>
            @elseif (auth()->user()->plan && auth()->user()->plan->type === \App\Enums\Plan::FAIR)
                <a href="#" class="mt-6 block w-full rounded-md px-3 py-2 text-center text-sm/6 font-semibold text-green-600 ring-1 ring-inset ring-green-200">
                    Your current plan
                </a>
            @else
                <a href="#" class="mt-6 block w-full rounded-md px-3 py-2 text-center text-sm/6 font-semibold text-gray-600 ring-1 ring-inset ring-gray-200">
                    Please reach out to us to downgrade
                </a>
            @endif
            <ul role="list" class="mt-8 space-y-3 text-sm/6 text-gray-600">
                <li class="flex gap-x-3">
                    <svg class="h-6 w-5 flex-none text-blue-600" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" data-slot="icon">
                        <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" />
                    </svg>
                    Real-time syncing
                </li>
                <li class="flex gap-x-3">
                    <svg class="h-6 w-5 flex-none text-blue-600" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" data-slot="icon">
                        <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" />
                    </svg>
                    Sync up to 4 calendar
                </li>
                <li class="flex gap-x-3">
                    <svg class="h-6 w-5 flex-none text-blue-600" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" data-slot="icon">
                        <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" />
                    </svg>
                    No limits for events
                </li>
                <li class="flex gap-x-3">
                    <svg class="h-6 w-5 flex-none text-blue-600" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" data-slot="icon">
                        <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" />
                    </svg>
                    Personal customer support
                </li>
            </ul>
        </div>
        <div class="rounded-3xl p-8 ring-1 ring-gray-200">
            <h3 id="tier-startup" class="text-lg/8 font-semibold text-gray-900">Supporter 🫶</h3>
            <p class="mt-4 text-sm/6 text-gray-600">For passionate users that appreciate our efforts.</p>
            <p class="mt-6 flex items-baseline gap-x-1">
                <!-- Price, update based on frequency toggle state -->
                <span class="text-4xl font-semibold tracking-tight text-gray-900">$48</span>
                <!-- Payment frequency, update based on frequency toggle state -->
                <span class="text-sm/6 font-semibold text-gray-600">/ one year valid</span>
            </p>
            @if (auth()->user()->plan && auth()->user()->plan->type !== \App\Enums\Plan::SUPPORTER)
                <x-lemon-button :href="auth()->user()->checkout(config('settings.plans.supporter_plan_id'))->redirectTo(url('/onboarding/checkout?plan=supporter'))" class="mt-6 block rounded-md px-3 py-2 text-center text-sm/6 font-semibold text-blue-600 ring-1 ring-inset ring-blue-200 hover:ring-blue-300 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600">
                    Upgrade to the supporter plan
                </x-lemon-button>
            @elseif (auth()->user()->plan && auth()->user()->plan->type === \App\Enums\Plan::SUPPORTER)
                <a href="#" class="mt-6 block w-full rounded-md px-3 py-2 text-center text-sm/6 font-semibold text-green-600 ring-1 ring-inset ring-green-200">
                    Your current plan
                </a>
            @endif
            <ul role="list" class="mt-8 space-y-3 text-sm/6 text-gray-600">
                <li class="flex gap-x-3">
                    <svg class="h-6 w-5 flex-none text-blue-600" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" data-slot="icon">
                        <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" />
                    </svg>
                    Real-time syncing
                </li>
                <li class="flex gap-x-3">
                    <svg class="h-6 w-5 flex-none text-blue-600" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" data-slot="icon">
                        <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" />
                    </svg>
                    Unlimited calendars
                </li>
                <li class="flex gap-x-3">
                    <svg class="h-6 w-5 flex-none text-blue-600" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" data-slot="icon">
                        <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" />
                    </svg>
                    No limits for events
                </li>
                <li class="flex gap-x-3">
                    <svg class="h-6 w-5 flex-none text-blue-600" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" data-slot="icon">
                        <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" />
                    </svg>
                    Personal customer support
                </li>
            </ul>
        </div>
    </div>
@endsection
