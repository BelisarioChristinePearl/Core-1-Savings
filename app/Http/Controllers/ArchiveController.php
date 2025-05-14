<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Deposit;
use App\Models\Withdrawal;

class ArchiveController extends Controller
{
    public function index()
    {
        // Get all rejected withdrawals and deposits
        $archivedWithdrawals = Withdrawal::where('status', 'rejected')->get();
        $archivedDeposits = Deposit::where('status', 'rejected')->get();

        return view('admin.archive', compact('archivedWithdrawals', 'archivedDeposits'));
    }

    // Update withdrawal status from rejected to pending
    public function restoreWithdrawal($id)
    {
        $withdrawal = Withdrawal::findOrFail($id);
        $withdrawal->update(['status' => 'pending']);

        return redirect()->route('admin.archive')->with('success', 'Withdrawal set to pending status.');
    }

    // Update deposit status from rejected to pending
    public function restoreDeposit($id)
    {
        $deposit = Deposit::findOrFail($id);
        $deposit->update(['status' => 'pending']);

        return redirect()->route('admin.archive')->with('success', 'Deposit set to pending status.');
    }

    // Permanently delete withdrawal
    public function destroyWithdrawal($id)
    {
        $withdrawal = Withdrawal::findOrFail($id);
        $withdrawal->delete(); // This will permanently delete the record

        return redirect()->route('admin.archive')->with('success', 'Withdrawal permanently deleted.');
    }

    // Permanently delete deposit
    public function destroyDeposit($id)
    {
        $deposit = Deposit::findOrFail($id);
        $deposit->delete(); 

        return redirect()->route('admin.archive')->with('success', 'Deposit permanently deleted.');
    }
}
