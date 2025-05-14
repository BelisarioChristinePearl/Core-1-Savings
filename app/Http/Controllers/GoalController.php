<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Goal;
use App\Models\Deposit;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;


class GoalController extends Controller
{
    public function goal()
    {
        return view('user.goal');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'category' => 'required|string',
            'name' => 'required|string',
            'target_amount' => 'required|numeric|min:0',
            'monthly_contribution' => 'required|numeric|min:0',
            'target_date' => 'required|date|after:today',
        ]);

        $goal = new Goal();
        $goal->user_id = Auth::id();
        $goal->category = $validated['category'];
        $goal->name = $validated['name'];
        $goal->target_amount = $validated['target_amount'];
        $goal->monthly_contribution = $validated['monthly_contribution'];
        $goal->target_date = $validated['target_date'];

        // If target amount is low (quick goal) or contribution is high relative to target,
        // it's likely just started. Otherwise, it's on-track by default
        if ($goal->target_amount <= 1000 || $goal->monthly_contribution >= $goal->target_amount / 3) {
            $goal->status = 'just-started';
        }

        $goal->save();

        return redirect()->route('user.goal')->with('success', 'Goal created successfully!');

    }

    /**
     * Fetches user's deposit balance
     */
    public function getDepositBalance()
    {
        $user = Auth::user();
        $totalDeposit = Deposit::where('user_id', $user->id)
            ->where('status', 'approved')
            ->sum('amount');

        return response()->json([
            'balance' => $totalDeposit
        ]);
    }

    /**
     * Update goal with deposit contribution
     */
    public function contributeFromDeposit(Request $request, Goal $goal)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0',
        ]);

        $user = Auth::user();
        $availableBalance = Deposit::where('user_id', $user->id)
            ->where('status', 'approved')
            ->sum('amount');

        // Check if user has enough balance
        if ($availableBalance < $validated['amount']) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient balance'
            ], 400);
        }

        // Update goal progress
        $goal->current_amount += $validated['amount'];

        // Update goal status based on progress
        $progressPercentage = $goal->getProgressPercentageAttribute();
        if ($progressPercentage >= 50) {
            $goal->status = 'on-track';
        } elseif ($progressPercentage >= 20) {
            $goal->status = 'at-risk';
        } else {
            $goal->status = 'just-started';
        }

        $goal->save();



        return response()->json([
            'success' => true,
            'current_amount' => $goal->current_amount,
            'progress' => $goal->getProgressPercentageAttribute(),
            'status' => $goal->status
        ]);
    }
    public function index()
    {
        $goals = Auth::user()->goals;
        return view('user.goal', compact('goals'));
    }
}
