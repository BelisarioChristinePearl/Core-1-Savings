<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Deposit;
use App\Models\Withdrawal;
use App\Models\Verification;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        // Get user statistics
        $totalUsers = User::count();
        $verifiedUsers = User::where('status', 'Verified')->count();
        $unverifiedUsers = $totalUsers - $verifiedUsers;

        // Get verification requests statistics
        $pendingVerifications = Verification::where('verification_status', 'pending')->count();
        $completedVerifications = Verification::where('verification_status', 'approved')->count();
        $rejectedVerifications = Verification::where('verification_status', 'rejected')->count();

        // Get deposit statistics
        $totalDeposits = Deposit::sum('amount');
        $pendingDeposits = Deposit::where('status', 'pending')->sum('amount');
        $completedDeposits = Deposit::where('status', 'completed')->sum('amount');
        $recentDeposits = Deposit::with('user')->orderBy('created_at', 'desc')->limit(5)->get();

        // Get withdrawal statistics
        $totalWithdrawals = Withdrawal::sum('amount');
        $pendingWithdrawals = Withdrawal::where('status', 'pending')->sum('amount');
        $completedWithdrawals = Withdrawal::where('status', 'completed')->sum('amount');
        $recentWithdrawals = Withdrawal::with('user')->orderBy('created_at', 'desc')->limit(5)->get();

        // Get monthly transaction data for charts
        $monthlyData = $this->getMonthlyTransactionData();
        $userRegistrationData = $this->getUserRegistrationData();
        $verificationStatusData = $this->getVerificationStatusData();

        return view('admin.index', compact(
            'totalUsers',
            'verifiedUsers',
            'unverifiedUsers',
            'pendingVerifications',
            'completedVerifications',
            'rejectedVerifications',
            'totalDeposits',
            'pendingDeposits',
            'completedDeposits',
            'totalWithdrawals',
            'pendingWithdrawals',
            'completedWithdrawals',
            'recentDeposits',
            'recentWithdrawals',
            'monthlyData',
            'userRegistrationData',
            'verificationStatusData'
        ));
    }

    private function getMonthlyTransactionData()
    {
        $months = collect([]);
        $depositsData = collect([]);
        $withdrawalsData = collect([]);

        // Get data for the last 6 months
        for ($i = 5; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $monthName = $date->format('M');
            $months->push($monthName);

            $monthlyDeposits = Deposit::whereMonth('created_at', $date->month)
                ->whereYear('created_at', $date->year)
                ->where('status', 'completed')
                ->sum('amount');

            $monthlyWithdrawals = Withdrawal::whereMonth('created_at', $date->month)
                ->whereYear('created_at', $date->year)
                ->where('status', 'completed')
                ->sum('amount');

            $depositsData->push($monthlyDeposits);
            $withdrawalsData->push($monthlyWithdrawals);
        }

        return [
            'months' => $months,
            'deposits' => $depositsData,
            'withdrawals' => $withdrawalsData
        ];
    }

    private function getUserRegistrationData()
    {
        $months = collect([]);
        $userData = collect([]);

        // Get data for the last 6 months
        for ($i = 5; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $monthName = $date->format('M');
            $months->push($monthName);

            $monthlyUsers = User::whereMonth('created_at', $date->month)
                ->whereYear('created_at', $date->year)
                ->count();

            $userData->push($monthlyUsers);
        }

        return [
            'months' => $months,
            'registrations' => $userData
        ];
    }

    private function getVerificationStatusData()
    {
        $pending = Verification::where('verification_status', 'pending')->count();
        $approved = Verification::where('verification_status', 'approved')->count();
        $rejected = Verification::where('verification_status', 'rejected')->count();

        return [
            'labels' => ['Pending', 'Approved', 'Rejected'],
            'data' => [$pending, $approved, $rejected]
        ];
    }
}
