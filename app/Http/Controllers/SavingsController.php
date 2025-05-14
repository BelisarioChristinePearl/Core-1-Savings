<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Deposit;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\Withdrawal;



class SavingsController extends Controller
{
    public function index(Request $request)
{
    // Get filters
    $status = $request->input('status', 'all');
    $accountType = $request->input('accountType', 'all');
    $balanceRange = $request->input('balanceRange', 'all');
    $dateFrom = $request->input('date', null);

    // Base query - group deposits by user_id
    $query = Deposit::select(
            'user_id',
            DB::raw('MAX(name) as name'),
            DB::raw('SUM(amount) as total_deposits'),
            DB::raw('MAX(created_at) as last_activity'),
            DB::raw('COUNT(*) as transaction_count')
        )
        ->groupBy('user_id');

    // Apply filters if needed
    if ($status !== 'all') {
        $isActive = $status === 'active';
        $query->where('status', $isActive ? 'approved' : 'pending');
    }

    if ($accountType !== 'all') {
        $query->where('payment_method', $accountType === 'corporate' ? 'corporate' : 'personal');
    }

    if ($balanceRange !== 'all') {
        // Parse balance range
        [$min, $max] = $this->parseBalanceRange($balanceRange);
        $query->havingRaw('SUM(amount) >= ?', [$min]);

        if ($max !== null) {
            $query->havingRaw('SUM(amount) <= ?', [$max]);
        }
    }

    if ($dateFrom) {
        $query->whereDate('created_at', '>=', $dateFrom);
    }

    // Get paginated results
    $savingsAccounts = $query->paginate(10);

    // Calculate actual balance by subtracting approved withdrawals
    foreach ($savingsAccounts as $account) {
        // Get total withdrawals for this user that are approved
        $totalWithdrawals = Withdrawal::where('user_id', $account->user_id)
            ->where('status', 'approved')
            ->sum('amount');
            
        // Calculate the actual balance after withdrawals
        $account->total_amount = $account->total_deposits - $totalWithdrawals;
        
        // For Personal accounts: 5% annual interest rate
        // For Corporate accounts: 7.5% annual interest rate
        $interestRate = ($account->payment_method === 'corporate') ? 0.075 : 0.05;

        // Calculate projected growth
        $account->projected_growth = $this->calculateProjectedGrowth(
            $account->total_amount,
            $interestRate,
            ($account->payment_method === 'corporate') ? 'annually' : 'semi-annually'
        );

        // Set account type based on payment method
        $account->account_type = ($account->payment_method === 'corporate') ? 'corporate' : 'personal';

        // Set status based on transactions and balance
        $account->status = ($account->transaction_count > 0 && $account->total_amount > 0) ? 'active' : 'inactive';
        
        // Get latest withdrawal date for this user
        $latestWithdrawal = Withdrawal::where('user_id', $account->user_id)
            ->latest('created_at')
            ->first();
            
        // Compare latest deposit date with latest withdrawal date
        if ($latestWithdrawal && $latestWithdrawal->created_at > $account->last_activity) {
            $account->last_activity = $latestWithdrawal->created_at;
        }
    }

    return view('admin.products', compact('savingsAccounts'));
}


    private function parseBalanceRange($range)
    {
        switch ($range) {
            case '0-1000':
                return [0, 1000];
            case '1000-5000':
                return [1000, 5000];
            case '5000-10000':
                return [5000, 10000];
            case '10000-above':
                return [10000, null];
            default:
                return [0, null];
        }
    }
    private function calculateProjectedGrowth($principal, $rate, $period)
    {
        // For semi-annually, calculate for half a year
        $periodMultiplier = ($period === 'semi-annually') ? 0.5 : 1;
        $compoundFrequency = ($period === 'semi-annually') ? 2 : 1;

        // Simple compound interest formula: P(1 + r/n)^(nt)
        // Where P = principal, r = rate, n = compounds per year, t = time in years
        $projectedAmount = $principal * pow(1 + ($rate / $compoundFrequency), $compoundFrequency * $periodMultiplier);

        return $projectedAmount;
    }
    public function exportToPdf()
    {
        // Get deposits grouped by user_id similar to index method
        $deposits = Deposit::select(
            'user_id',
            DB::raw('MAX(name) as name'),
            DB::raw('SUM(amount) as total_amount'),
            DB::raw('MAX(created_at) as last_activity'),
            DB::raw('COUNT(*) as transaction_count'),
            DB::raw('MAX(payment_method) as payment_method'),
            DB::raw('MAX(status) as status')
        )
        ->groupBy('user_id')
        ->get();

        // Format data to match what the PDF view expects
        foreach ($deposits as $deposit) {
            // Set account type based on payment method
            $deposit->account_type = ($deposit->payment_method === 'corporate') ? 'corporate' : 'personal';

            // Set status based on the status field
            $deposit->status = ($deposit->status === 'approved') ? 'active' : 'inactive';

            // Calculate projected growth
            $interestRate = ($deposit->payment_method === 'corporate') ? 0.075 : 0.05;
            $deposit->projected_growth = $this->calculateProjectedGrowth(
                $deposit->total_amount,
                $interestRate,
                ($deposit->payment_method === 'corporate') ? 'annually' : 'semi-annually'
            );
        }

        // Create the actual data content as a separate view
        $contentHtml = view('saving-contents', ['savingsAccounts' => $deposits])->render();

        // Encrypt the content - using a simple base64 encoding for demonstration
        // In a production environment, consider using stronger encryption
        $encryptedContent = base64_encode($contentHtml);

        // Pass the encrypted content to the password-protected view
        $view = view('savings-pdf', [
            'encryptedContent' => $encryptedContent,
            'correctPassword' => 'savings' // The password you specified
        ]);

        // Return the view as a downloadable HTML file
        return response($view)
            ->header('Content-Type', 'text/html')
            ->header('Content-Disposition', 'attachment; filename="savings-accounts.html"');
    }
}
