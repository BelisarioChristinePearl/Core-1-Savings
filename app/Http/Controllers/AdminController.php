<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;
use Intervention\Image\Laravel\Facades\Image;
use Illuminate\Support\Facades\Storage;
use App\Models\User;
use App\Models\Deposit;
use App\Models\Withdrawal;
use App\Models\Verification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;


class AdminController extends Controller
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
    public function show() {
        return view('admin.products');
    }

        public function login()
    {
        return view('auth.login');
    }
    public function verify(Request $request)
    {
        $status = $request->query('status', 'pending');

        $query = Verification::with('user');

        if ($status !== 'all') {
            $query->where('verification_status', $status);
        }

        $verifications = $query->latest()->paginate(10);

        return view('admin.client', compact('verifications'));
    }
    public function fetch($id)
    {
        $verification = Verification::findOrFail($id);
        $user = $verification->user;

        return response()->json([
            'verification' => $verification,
            'user' => $user
        ]);
    }
    public function updateStatus(Request $request, $id)
{
    $request->validate([
        'status' => 'required|in:approved,rejected',
        'rejection_reason' => 'required_if:status,rejected'
    ]);

    $verification = Verification::findOrFail($id);

    // Update verification status
    $verification->verification_status = $request->status;

    if ($request->status === 'rejected') {
        $verification->rejection_reason = $request->rejection_reason;
    } else {
        $verification->verified_at = now();
        $verification->verified_by = Auth::id();

        $user = $verification->user;
        $user->status = 'Verified';
        $user->save();
    }

    $verification->save();

    return response()->json([
        'success' => true,
        'message' => 'Verification status updated successfully'
    ]);
}

        public function admindeposit()
    {
        $deposits = Deposit::with('user')
            ->whereIn('status', ['pending', 'approved'])
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return view('admin.admin-deposit', compact('deposits'));
    }

    public function update(Request $request, $id)
{
    $deposit = Deposit::findOrFail($id);

    $request->validate([
        'status' => 'required|in:pending,approved,rejected',
        'rejection_reason' => 'required_if:status,rejected',
        'notes' => 'nullable|string|max:255',
    ]);

    $deposit->status = $request->status;

    // Set notes based on rejection reason
    if ($request->status === 'rejected') {
        if ($request->rejection_reason === 'Other') {
            $deposit->notes = $request->notes;
        } else {
            $deposit->notes = $request->rejection_reason;
        }

        // Get the user's email
        $user = $deposit->user; // Assuming you have a relationship set up

        // Send email notification
        if ($user && $user->email) {
            $this->sendRejectionEmail($user->email, $deposit);
        }
    }

    $deposit->save();

    return redirect()->route('admin.admin-deposit')
        ->with('success', 'Deposit status has been updated successfully');
}

private function sendRejectionEmail($email, $deposit)
{
    $rejectionReason = $deposit->notes;

    // Set up mail configuration
    $config = [
        'driver'     => env('MAIL_MAILER'),
        'host'       => env('MAIL_HOST'),
        'port'       => env('MAIL_PORT'),
        'encryption' => env('MAIL_ENCRYPTION'),
        'username'   => env('MAIL_USERNAME'),
        'password'   => env('MAIL_PASSWORD'),
        'from'       => [
            'address' => env('MAIL_FROM_ADDRESS'),
            'name'    => env('MAIL_FROM_NAME'),
        ],
    ];

    // Create the email content
    $subject = "Your Deposit Request Has Been Rejected";
    $message = "Dear {$deposit->user->name},\n\n";
    $message .= "We regret to inform you that your deposit request (ID: {$deposit->id}) has been rejected.\n\n";
    $message .= "Reason for rejection: {$rejectionReason}\n\n";
    $message .= "If you have any questions, please contact our support team.\n\n";
    $message .= "Regards,\n";
    $message .= env('MAIL_FROM_NAME');

    // Send raw email
    try {
        Mail::raw($message, function ($mail) use ($email, $subject) {
            $mail->to($email)
                 ->subject($subject);
        });

        // Log successful email sending
        Log::info("Rejection email sent to {$email} for deposit ID: {$deposit->id}");
    } catch (\Exception $e) {
        // Log error
        Log::error("Failed to send rejection email: " . $e->getMessage());
    }
}

    public function showReceipt($id)
    {
        $deposit = Deposit::findOrFail($id);

        if (!$deposit->receipt_path) {
            abort(404, 'Receipt not found');
        }

        $path = Storage::disk('public')->path($deposit->receipt_path);

        if (!file_exists($path)) {
            abort(404, 'Receipt file not found');
        }

        return response()->file($path);
    }
    public function adminWithdraw()
    {
        $withdrawals = Withdrawal::with('user')
            ->whereIn('status', ['pending', 'approved'])
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return view('admin.admin-withdraw', compact('withdrawals'));
    }
        public function updateWithdrawalStatus(Request $request, $id)
    {
        $withdrawal = Withdrawal::findOrFail($id);

        $request->validate([
            'status' => 'required|in:pending,approved,rejected',
            'rejection_reason' => 'required_if:status,rejected',
            'notes' => 'nullable|string|max:255',
        ]);

        $withdrawal->status = $request->status;

        // Set notes based on rejection reason
        if ($request->status === 'rejected') {
            if ($request->rejection_reason === 'Other') {
                $withdrawal->notes = $request->notes;
            } else {
                $withdrawal->notes = $request->rejection_reason;
            }

            $user = $withdrawal->user; 


            if ($user && $user->email) {
                $this->sendWithdrawalRejectionEmail($user->email, $withdrawal);
            }
        } elseif ($request->has('notes')) {
            $withdrawal->notes = $request->notes;
        }

        $withdrawal->save();

        return redirect()->route('admin.admin-withdraw')
            ->with('success', 'Withdrawal status has been updated successfully');
    }

    private function sendWithdrawalRejectionEmail($email, $withdrawal)
    {
        $rejectionReason = $withdrawal->notes;


        $subject = "Your Withdrawal Request Has Been Rejected";
        $message = "Dear {$withdrawal->user->name},\n\n";
        $message .= "We regret to inform you that your withdrawal request (ID: {$withdrawal->id}) for the amount of {$withdrawal->amount} has been rejected.\n\n";
        $message .= "Reason for rejection: {$rejectionReason}\n\n";
        $message .= "If you have any questions, please contact our support team.\n\n";
        $message .= "Regards,\n";
        $message .= env('MAIL_FROM_NAME');

        try {
            Mail::raw($message, function ($mail) use ($email, $subject) {
                $mail->to($email)
                    ->subject($subject);
            });


            Log::info("Withdrawal rejection email sent to {$email} for withdrawal ID: {$withdrawal->id}");
        } catch (\Exception $e) {

            Log::error("Failed to send withdrawal rejection email: " . $e->getMessage());
        }
    }


}
