<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use App\Models\Deposit;

class DepositController extends Controller
{
    public function deposit(){
        return view('user.deposit');
    }
    public function store(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:1',
            'payment_method' => 'required|in:gcash,bank,cash',
            'reference_number' => 'nullable|required_if:payment_method,gcash,bank|string|max:255',
            'file-upload' => 'required|file|mimes:jpeg,png,jpg,gif|max:10240',
            'notes' => 'nullable|string'
        ]);

        $receiptPath = null;
        if ($request->hasFile('file-upload')) {
            $receiptPath = $request->file('file-upload')->store('receipts', 'public');
        }

        $user = Auth::user();

        $deposit = Deposit::create([
            'transaction_id' => Deposit::generateTransactionId(),
            'user_id' => $user->id,
            'name' => $user->name, // Save the user's name
            'amount' => $request->amount,
            'payment_method' => $request->payment_method,
            'reference_number' => $request->reference_number,
            'receipt_path' => $receiptPath,
            'notes' => $request->notes,
            'status' => 'pending'
        ]);

        return redirect()->route('user.deposit')
            ->with('success', 'Deposit transaction submitted successfully. Transaction ID: ' . $deposit->transaction_id);
    }
      public function index()
{
    $deposits = Deposit::with('user')
        ->where('user_id', auth()->id())
        ->latest()
        ->get();

    return view('user.deposit', compact('deposits'));
}

    
}
