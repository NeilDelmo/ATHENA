<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Str;

class ProviderController extends Controller
{
    //
    public function redirectToGoogle()
    {
        return Socialite::driver('google')->redirect();
    }

    public function handleGoogleCallback()
    { //get user from the google
        try {
            $googleUser =  Socialite::driver('google')->user();
            //check if the user already exists in the database
            $existingUser = User::where('email', $googleUser->getEmail())->first();
            $user = User::updateOrCreate([
                'email' => $googleUser->getEmail(),
            ],['name' => $googleUser->getName(),
            'google_id' => $googleUser->getId(),
            'avatar' => $googleUser->getAvatar(),
            'password' => $existingUser ? $existingUser->password : bcrypt(Str::random(16)),
            ]); 

            //login the user
            Auth::login($user);
            return redirect()->intended('/dashboard');
        } catch (\Exception $e) {
            dd($e->getMessage());
        } //try and catch end here. 
    }
}
