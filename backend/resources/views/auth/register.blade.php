@extends('layouts.blank')
@section('title', 'Register')
@section('page')
    <div class="flex min-h-full flex-col justify-center py-24 sm:px-6 lg:px-8">
    <div class="sm:mx-auto sm:w-full sm:max-w-md">
            <img class="mx-auto h-12 w-auto" src="/images/logo-black.svg" alt="Logo">
            <h2 class="mt-6 text-center text-2xl/9 font-bold tracking-tight text-gray-900">Welcome to Spacepad</h2>
            <p class="mt-2 text-center text-lg text-gray-500">Register to start using your display today</p>
        </div>

        <x-cards.card class="mt-10 sm:mx-auto sm:w-full sm:max-w-[450px]">
            <div class="py-6 sm:px-6">
                <x-alerts.alert />

                @if(session('registered'))
                    <div class="flex flex-col items-center justify-center p-8 mb-8">
                        <div class="mb-4">
                            <svg class="h-12 w-12 text-orange" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
                            </svg>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-900 mb-2">Please check your email</h3>
                        <p class="text-gray-700 text-center">You should receive an e-mail with a login link shortly.</p>
                    </div>
                @else
                    @if(! config('settings.disable_email_login'))
                        <form action="{{ route('register.store') }}" method="POST">
                            @csrf
                            {!! RecaptchaV3::field('register') !!}
                            <div class="mb-3">
                                <label for="name" class="block text-sm/6 font-medium text-gray-900">Name</label>
                                <div class="mt-2">
                                    <input id="name" name="name" type="text" autocomplete="name" required class="block w-full rounded-md border-0 px-3 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-blue-600 sm:text-sm/6">
                                </div>
                            </div>
                            <div class="mb-6">
                                <label for="email" class="block text-sm/6 font-medium text-gray-900">Email address</label>
                                <div class="mt-2">
                                    <input id="email" name="email" type="email" autocomplete="email" required class="block w-full rounded-md border-0 px-3 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-blue-600 sm:text-sm/6">
                                </div>
                            </div>
                            <div class="mb-4">
                                <button type="submit" class="flex w-full justify-center rounded-md bg-oxford px-3 py-2 text-sm/6 font-semibold text-white shadow-sm hover:bg-blue-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600">
                                    {{ config('settings.is_self_hosted') ? 'Register' : 'Get started' }}
                                </button>
                            </div>
                        </form>

                        <div class="relative my-6">
                            <div class="absolute inset-0 flex items-center" aria-hidden="true">
                                <div class="w-full border-t border-gray-200"></div>
                            </div>
                            <div class="relative flex justify-center text-sm/6 font-medium">
                                <span class="bg-white px-6 text-gray-900">Or continue with</span>
                            </div>
                        </div>
                    @endif

                    <div class="flex flex-col space-y-4">
                        @if(config('services.microsoft.enabled'))
                            <a href="{{ route('auth.microsoft.redirect') }}" class="flex w-full items-center justify-center gap-3 rounded-md bg-white px-3 py-3 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus-visible:ring-transparent">
                                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" x="0px" y="0px" width="100" height="100" viewBox="0 0 48 48">
                                    <path fill="#ff5722" d="M6 6H22V22H6z" transform="rotate(-180 14 14)"></path><path fill="#4caf50" d="M26 6H42V22H26z" transform="rotate(-180 34 14)"></path><path fill="#ffc107" d="M26 26H42V42H26z" transform="rotate(-180 34 34)"></path><path fill="#03a9f4" d="M6 26H22V42H6z" transform="rotate(-180 14 34)"></path>
                                </svg>
                                <span class="text-sm/6 font-semibold">Microsoft</span>
                            </a>
                        @endif

                        @if(config('services.google.enabled'))
                            <a href="{{ route('auth.google.redirect') }}" class="flex w-full items-center justify-center gap-3 rounded-md bg-white px-3 py-3 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus-visible:ring-transparent">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M12.0003 4.75C13.7703 4.75 15.3553 5.36002 16.6053 6.54998L20.0303 3.125C17.9502 1.19 15.2353 0 12.0003 0C7.31028 0 3.25527 2.69 1.28027 6.60998L5.27028 9.70498C6.21525 6.86002 8.87028 4.75 12.0003 4.75Z" fill="#EA4335" />
                                    <path d="M23.49 12.275C23.49 11.49 23.415 10.73 23.3 10H12V14.51H18.47C18.18 15.99 17.34 17.25 16.08 18.1L19.945 21.1C22.2 19.01 23.49 15.92 23.49 12.275Z" fill="#4285F4" />
                                    <path d="M5.26498 14.2949C5.02498 13.5699 4.88501 12.7999 4.88501 11.9999C4.88501 11.1999 5.01998 10.4299 5.26498 9.7049L1.275 6.60986C0.46 8.22986 0 10.0599 0 11.9999C0 13.9399 0.46 15.7699 1.28 17.3899L5.26498 14.2949Z" fill="#FBBC05" />
                                    <path d="M12.0004 24.0001C15.2404 24.0001 17.9654 22.935 19.9454 21.095L16.0804 18.095C15.0054 18.82 13.6204 19.245 12.0004 19.245C8.8704 19.245 6.21537 17.135 5.2654 14.29L1.27539 17.385C3.25539 21.31 7.3104 24.0001 12.0004 24.0001Z" fill="#34A853" />
                                </svg>
                                <span class="text-sm/6 font-semibold">Google</span>
                            </a>
                        @endif

                        @if(config('settings.disable_email_login') && ! config('services.microsoft.enabled') && ! config('services.google.enabled'))
                            <div class="p-4 bg-orange-100 text-orange-800 rounded text-center">No email registration or authentication provider configured.</div>
                        @endif
                    </div>

                    <div class="mt-6 text-sm text-center">
                        <label class="text-gray-500">By continuing, you indicate that you agree to our<br> <a href="https://spacepad.io/terms" target="_blank" class="text-blue-600 hover:text-blue-500">Terms of Service</a> and <a href="https://spacepad.io/privacy" target="_blank" class="text-blue-600 hover:text-blue-500">Privacy Policy</a>.</label>
                    </div>
                @endif
            </div>
        </x-cards.card>
    </div>
@endsection
