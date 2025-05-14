<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Withdrawal;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class WithdrawController extends Controller
{
    public function withdraw()
    {
        return view('user.withdraw');
    }

    public function store(Request $request)
    {
        Log::info('Withdrawal request received', $request->all());

        try {
            // Base validation
            $request->validate([
                'amount' => 'required|numeric|min:1',
                'payment_method' => 'required|in:gcash,bank,cash',
                'notes' => 'nullable|string'
            ]);

            // Payment method specific validation
            switch($request->payment_method) {
                case 'gcash':
                    $request->validate([
                        'gcash_number' => 'required|string|max:255'
                    ]);
                    $accountDetails = $request->gcash_number;
                    $bankName = null;
                    break;

                case 'bank':
                    $request->validate([
                        'bank_name' => 'required|string|max:255',
                        'account_number' => 'required|string|max:255'
                    ]);
                    $accountDetails = $request->account_number;
                    $bankName = $request->bank_name;
                    break;

                case 'cash':
                    $request->validate([
                        'pickup_location' => 'required|string|max:255'
                    ]);
                    $accountDetails = $request->pickup_location;
                    $bankName = null;
                    break;

                default:
                    return redirect()->back()
                        ->with('error', 'Invalid payment method selected')
                        ->withInput();
            }

            $user = Auth::user();

            // Create withdrawal record
            $withdrawal = Withdrawal::create([
                'transaction_id' => Withdrawal::generateTransactionId(),
                'user_id' => $user->id,
                'name' => $user->name,
                'amount' => $request->amount,
                'payment_method' => $request->payment_method,
                'account_details' => $accountDetails,
                'bank_name' => $bankName,
                'notes' => $request->notes,
                'status' => 'pending'
            ]);

            Log::info('Withdrawal created successfully', [
                'withdrawal_id' => $withdrawal->id,
                'transaction_id' => $withdrawal->transaction_id
            ]);

            return redirect()->route('user.withdraw')
                ->with('success', 'Withdrawal request submitted successfully. Transaction ID: ' . $withdrawal->transaction_id);

        } catch (\Exception $e) {
            Log::error('Withdrawal submission error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->back()
                ->with('error', 'Error submitting withdrawal: ' . $e->getMessage())
                ->withInput();
        }
    }
  public function index()
{
    $withdrawals = Withdrawal::with('user')
        ->where('user_id', auth()->id())
        ->latest()
        ->paginate(10);

    return view('user.withdraw', compact('withdrawals'));
}

}
