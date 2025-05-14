<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Middleware\Verify2FAMiddleware;
use Illuminate\Support\Facades\Mail;

class TwoFactorController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $code = rand(100000, 999999);
        $user->two_factor_code = $code;
        $user->save();

        $message = "Dear {$user->name},\n\n";
        $message .= "Your Two-Factor Authentication Code is: {$code}\n\n";
        $message .= "Please enter this code to complete your login process.\n";
        $message .= "This code will expire in 10 minutes.\n\n";
        $message .= "If you did not request this code, please ignore this email.\n\n";
        $message .= "Thank you,\n";
        $message .= 'Savings Management';

        Mail::raw($message, function ($email) use ($user) {
            $email->to($user->email)
                  ->subject('Your Two-Factor Authentication Code');
        });

        return view('auth.two-factor');
    }
    public function verify(Request $request){
        $request->validate([
            'code' => 'required|integer',
        ]);

        $user = auth()->user();

        if ($request->code == $user->two_factor_code){
            session(['two_factor_authenticated' => true]);
            return redirect()->intended('/');
        }
        return redirect()->route('two-factor')->withErrors(['code' => 'The Provided Code is Incorrect']);

    }
}
