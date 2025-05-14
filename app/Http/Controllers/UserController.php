<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Deposit;
use App\Models\Withdrawal;
use App\Models\Goal;
use App\Models\Verification;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;


class UserController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        // Get user's deposits
        $deposits = Deposit::where('user_id', $user->id)
                        ->orderBy('created_at', 'desc')
                        ->take(8)
                        ->get();

        // Get user's withdrawals
        $withdrawals = Withdrawal::where('user_id', $user->id)
                            ->orderBy('created_at', 'desc')
                            ->take(8)
                            ->get();

        // Get user's goals
        $goals = Goal::where('user_id', $user->id)
                    ->orderBy('created_at', 'desc')
                    ->get();

        // Get verification status
        $verification = Verification::where('user_id', $user->id)->first();

        // Calculate summary statistics
        $totalDeposits = Deposit::where('user_id', $user->id)
                            ->where('status', 'approved')
                            ->where('created_at', '>=', Carbon::now()->subMonths(6))
                            ->sum('amount');

        $totalWithdrawals = Withdrawal::where('user_id', $user->id)
                            ->where('status', 'approved')
                            ->where('created_at', '>=', Carbon::now()->subMonths(6))
                            ->sum('amount');

        // Calculate interest earned (3.5% per annum on current balance)
        // This is a simplified calculation
        $currentBalance = $totalDeposits - $totalWithdrawals;
        $interestRate = 0.035; // 3.5%
        $interestEarned = ($currentBalance * $interestRate) / 12 * 6; // For 6 months

        // Get monthly balance data for chart
        $monthlyBalances = $this->getMonthlyBalances($user->id);

        return view('user.index', compact(
            'user',
            'deposits',
            'withdrawals',
            'goals',
            'verification',
            'totalDeposits',
            'totalWithdrawals',
            'interestEarned',
            'currentBalance',
            'monthlyBalances'
        ));
    }

    /**
     * Get monthly balances for the chart
     *
     * @param int $userId
     * @return array
     */
    private function getMonthlyBalances($userId)
    {
        $result = [];
        $current = Carbon::now();

        // Get data for the last 12 months
        for ($i = 11; $i >= 0; $i--) {
            $month = Carbon::now()->subMonths($i);
            $startOfMonth = Carbon::now()->subMonths($i)->startOfMonth();
            $endOfMonth = Carbon::now()->subMonths($i)->endOfMonth();

            // Calculate deposits up to this month
            $depositsToDate = Deposit::where('user_id', $userId)
                ->where('status', 'approved')
                ->where('created_at', '<=', $endOfMonth)
                ->sum('amount');

            // Calculate withdrawals up to this month
            $withdrawalsToDate = Withdrawal::where('user_id', $userId)
                ->where('status', 'approved')
                ->where('created_at', '<=', $endOfMonth)
                ->sum('amount');

            // Calculate balance at the end of the month
            $balance = $depositsToDate - $withdrawalsToDate;

            $result[] = [
                'month' => $month->format('M'),
                'balance' => max(0, $balance)
            ];
        }

        return $result;
    }


}
