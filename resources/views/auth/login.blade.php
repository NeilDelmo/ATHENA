<x-guest-layout>
    <div class="fixed inset-0 flex min-h-screen bg-gray-50 dark:bg-gray-950 font-sans">
        
        <div class="hidden lg:flex lg:w-1/2 relative bg-gradient-to-tr from-red-950 via-slate-950 to-red-900 text-white p-12 flex-col justify-between overflow-hidden">
            <div class="absolute inset-0 opacity-10 bg-[linear-gradient(to_right,#808080_1px,transparent_1px),linear-gradient(to_bottom,#808080_1px,transparent_1px)] bg-[size:24px_24px]"></div>
            
            <div class="relative z-10 flex items-center gap-3">
                <span class="text-2xl font-black tracking-widest text-red-500">ATHENA</span>
                <span class="text-xs px-2 py-0.5 bg-red-500/20 text-red-300 rounded-full border border-red-500/30">v1.0</span>
            </div>

            <div class="relative z-10 max-w-md my-auto">
                <h1 class="text-4xl font-extrabold tracking-tight leading-none mb-4 bg-gradient-to-r from-white via-slate-200 to-red-200 bg-clip-text text-transparent">
                    An Automated Research Management and Monitoring System with Analytics and Research Support Tools
                </h1>
                <p class="text-slate-400 text-lg leading-relaxed">
                    Secure access for Batangas State University faculty members.
                </p>
            </div>

            <div class="relative z-10 text-xs text-slate-500 border-t border-slate-800/60 pt-4 flex justify-between items-center">
                <span>© {{ date('Y') }} Project ATHENA. All rights reserved.</span>
                <span class="font-medium text-slate-400">BatStateU Portal</span>
            </div>
        </div>

        <div class="w-full lg:w-1/2 flex items-center justify-center p-6 sm:p-12 overflow-y-auto">
            <div class="w-full max-w-md space-y-8">
                
                <div class="lg:hidden text-center mb-6">
                    <h2 class="text-3xl font-black tracking-widest text-red-600 dark:text-red-500">ATHENA</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Academic Tracking & Higher Ed Network Asset</p>
                </div>

                <div>
                    <h2 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">
                        Welcome Back
                    </h2>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                        Sign in to manage your academic credentials.
                    </p>
                </div>

                <x-auth-session-status class="mb-4" :status="session('status')" />

                <div>
                    <a href="{{ url('auth/google') }}" class="w-full flex justify-center items-center gap-3 bg-white dark:bg-gray-900 text-gray-700 dark:text-gray-200 font-semibold py-3 px-4 border border-gray-200 dark:border-gray-800 rounded-xl shadow-sm hover:bg-gray-50 dark:hover:bg-gray-800 hover:border-gray-300 dark:hover:border-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition-all duration-200 cursor-pointer">
                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
                            <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                            <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z" fill="#FBBC05"/>
                            <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.53 12-4.53z" fill="#EA4335"/>
                        </svg>
                        <span>Sign in with Red Spartan Email</span>
                    </a>
                </div>

                <div class="relative flex items-center py-2">
                    <div class="flex-grow border-t border-gray-200 dark:border-gray-800"></div>
                    <span class="flex-shrink mx-4 text-gray-400 dark:text-gray-600 text-xs uppercase tracking-widest font-medium">or local user</span>
                    <div class="flex-grow border-t border-gray-200 dark:border-gray-800"></div>
                </div>

                <form method="POST" action="{{ route('login') }}" class="space-y-5">
                    @csrf

                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Email Address</label>
                        <input id="email" class="block w-full rounded-xl border-gray-200 dark:border-gray-800 dark:bg-gray-900 text-gray-900 dark:text-gray-100 shadow-sm focus:border-red-600 focus:ring-red-600 py-2.5" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
                        <x-input-error :messages="$errors->get('email')" class="mt-1.5" />
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label for="password" class="text-sm font-medium text-gray-700 dark:text-gray-300">Password</label>
                            @if (Route::has('password.request'))
                                <a class="text-xs font-semibold text-red-600 dark:text-red-400 hover:underline" href="{{ route('password.request') }}">
                                    Forgot password?
                                </a>
                            @endif
                        </div>
                        <input id="password" class="block w-full rounded-xl border-gray-200 dark:border-gray-800 dark:bg-gray-900 text-gray-900 dark:text-gray-100 shadow-sm focus:border-red-600 focus:ring-red-600 py-2.5" type="password" name="password" required autocomplete="current-password" />
                        <x-input-error :messages="$errors->get('password')" class="mt-1.5" />
                    </div>

                    <div class="flex items-center">
                        <input id="remember_me" type="checkbox" class="rounded border-gray-300 dark:border-gray-800 text-red-600 shadow-sm focus:ring-red-500 dark:bg-gray-900" name="remember">
                        <label for="remember_me" class="ms-2 text-sm text-gray-600 dark:text-gray-400 select-none">Keep me signed in</label>
                    </div>

                    <div>
                        <button type="submit" class="w-full flex justify-center py-3 px-4 border border-transparent rounded-xl shadow-sm text-sm font-semibold text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition-colors duration-150 cursor-pointer">
                            Sign In
                        </button>
                    </div>
                </form>

            </div>
        </div>

    </div>
</x-guest-layout>