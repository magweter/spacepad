@extends('layouts.blank')
@section('title', 'Register')
@section('page')
    <div class="flex min-h-full flex-col justify-center py-24 sm:px-6 lg:px-8">
    <div class="sm:mx-auto sm:w-full sm:max-w-md">
            <img class="mx-auto h-12 w-auto" src="/images/logo-black.svg" alt="Logo">
            <h2 class="mt-6 text-center text-2xl/9 font-bold tracking-tight text-gray-900">Welcome to Spacepad</h2>
            <p class="mt-2 text-center text-lg text-gray-500">Register to start using your display today</p>
        </div>

        <div class="mt-10 sm:mx-auto sm:w-full sm:max-w-[480px]">
            <div class="bg-white px-6 py-12 border sm:rounded-lg sm:px-12">
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
                    <form action="{{ route('register.store') }}" method="POST">
                        @csrf
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
                        <div>
                            <button type="submit" class="flex w-full justify-center rounded-md bg-oxford px-3 py-1.5 text-sm/6 font-semibold text-white shadow-sm hover:bg-blue-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600">Start free 7-day trail</button>
                        </div>
                    </form>

                    <div>
                        <div class="relative mt-10">
                            <div class="absolute inset-0 flex items-center" aria-hidden="true">
                                <div class="w-full border-t border-gray-200"></div>
                            </div>
                            <div class="relative flex justify-center text-sm/6 font-medium">
                                <span class="bg-white px-6 text-gray-900">Or continue with</span>
                            </div>
                        </div>

                        <div class="mt-6 grid grid-cols-1 gap-4">
                            <a href="{{ route('auth.microsoft.redirect') }}" class="flex w-full items-center justify-center gap-3 rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus-visible:ring-transparent">
                                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" x="0px" y="0px" width="100" height="100" viewBox="0 0 48 48">
                                    <path fill="#ff5722" d="M6 6H22V22H6z" transform="rotate(-180 14 14)"></path><path fill="#4caf50" d="M26 6H42V22H26z" transform="rotate(-180 34 14)"></path><path fill="#ffc107" d="M26 26H42V42H26z" transform="rotate(-180 34 34)"></path><path fill="#03a9f4" d="M6 26H22V42H6z" transform="rotate(-180 14 34)"></path>
                                </svg>
                                <span class="text-sm/6 font-semibold">Microsoft</span>
                            </a>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
