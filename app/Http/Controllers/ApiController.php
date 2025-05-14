<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Deposit;
use App\Models\Withdrawal;
use App\Models\User;

class ApiController extends Controller
{
    public function checkBalance(Request $request)
{
    $userId = $request->input('user_id');

    // Calculate total deposits
    $totalDeposits = Deposit::where('user_id', $userId)
        ->where('status', 'approved')
        ->sum('amount');

    // Calculate total withdrawals
    $totalWithdrawals = Withdrawal::where('user_id', $userId)
        ->where('status', 'approved')
        ->sum('amount');

    // Calculate balance
    $balance = $totalDeposits - $totalWithdrawals;

    // Calculate projected growth
    // Get user's account type first (personal or corporate)
    $accountType = User::find($userId)->account_type ?? 'personal';

    // Set interest rate based on account type
    $interestRate = ($accountType === 'corporate') ? 0.075 : 0.05;

    // Calculate projected growth
    $projectedGrowth = $balance * (($accountType === 'corporate') ? 0.075 : 0.05);

    return response()->json([
        'balance' => $balance,
        'projected_growth' => $projectedGrowth,
        'total_available' => $balance + $projectedGrowth
    ]);
}
}
