<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use App\Models\Verification;
use App\Models\User;


class VerifyController extends Controller
{
    public function verify(){
        return view('user.verified');
    }
    public function store(Request $request)
    {
        // Validate base form data
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'mobile' => 'required|string|max:20',
            'date_of_birth' => 'required|date|before:today',
            'street_address' => 'required|string|max:255',
            'province' => 'required|string|max:255',
            'city' => 'required|string|max:255',
            'postal_code' => 'required|string|max:10',
            'id_type' => 'required|string|in:passport,drivers_license,national_id,other',
            'id_number' => 'required|string|max:255',
            'id_front' => 'required|image|max:5120', // 5MB limit
            'id_back' => 'required|image|max:5120',
            'selfie' => 'required|image|max:5120',
            'employment_status' => 'required|string|in:employed,self_employed,business_owner,student,retired,unemployed',
            'monthly_income' => 'required|numeric|min:0',
            'income_proof' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240', // 10MB limit
            'terms' => 'required|accepted',
        ]);

        // Add business validation if is_business is checked
        if ($request->has('is_business')) {
            $businessRules = [
                'business_name' => 'required|string|max:255',
                'business_registration_number' => 'required|string|max:255',
                'business_type' => 'required|string|in:sole_proprietorship,partnership,llc,corporation,nonprofit',
                'business_registration' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
                'business_permit' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
            ];

            $validator->addRules($businessRules);
        }

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        // Store file uploads
        $idFrontPath = $request->file('id_front')->store('verifications/id_front', 'public');
        $idBackPath = $request->file('id_back')->store('verifications/id_back', 'public');
        $selfiePath = $request->file('selfie')->store('verifications/selfies', 'public');
        $incomeProofPath = $request->file('income_proof')->store('verifications/income_proof', 'public');

        // Store business files if provided
        $businessRegistrationPath = null;
        $businessPermitPath = null;

        if ($request->has('is_business')) {
            $businessRegistrationPath = $request->file('business_registration')->store('verifications/business_registration', 'public');
            $businessPermitPath = $request->file('business_permit')->store('verifications/business_permit', 'public');
        }

        // Create verification record
        $verification = Verification::create([
            'user_id' => Auth::id(),
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'date_of_birth' => $request->date_of_birth,
            'street_address' => $request->street_address,
            'province' => $request->province,
            'city' => $request->city,
            'postal_code' => $request->postal_code,
            'id_type' => $request->id_type,
            'id_number' => $request->id_number,
            'id_front' => $idFrontPath,
            'id_back' => $idBackPath,
            'selfie' => $selfiePath,
            'employment_status' => $request->employment_status,
            'monthly_income' => $request->monthly_income,
            'income_proof' => $incomeProofPath,
            'is_business' => $request->has('is_business'),
            'business_name' => $request->business_name,
            'business_registration_number' => $request->business_registration_number,
            'business_type' => $request->business_type,
            'business_registration' => $businessRegistrationPath,
            'business_permit' => $businessPermitPath,
            'verification_status' => 'pending',
        ]);

        $user = User::find($verification->user_id);
        if ($user) {
            $user->status = 'Non-verified';
            $user->save();
        }

        return redirect()->route('user.verified')->with('success', 'Your verification information has been submitted successfully.');
    }

    /**
     * Admin method to list verification requests
     *
     * @return \Illuminate\View\View
     */
    public function adminIndex()
    {
        $this->authorize('admin');

        $pendingVerifications = Verification::with('user')
            ->where('verification_status', 'pending')
            ->orderBy('created_at', 'asc')
            ->paginate(10);

        return view('admin.verifications.index', compact('pendingVerifications'));
    }


    public function adminShow($id)
    {
        $this->authorize('admin');

        $verification = Verification::with('user')->findOrFail($id);

        return view('admin.verifications.show', compact('verification'));
    }


    public function approve($id)
    {
        $this->authorize('admin');

        $verification = Verification::findOrFail($id);
        $verification->verification_status = 'approved';
        $verification->verified_at = now();
        $verification->verified_by = Auth::id();
        $verification->save();

        // Update user status
        $user = User::find($verification->user_id);
        $user->status = 'Verified';
        $user->save();

        return redirect()->route('admin.verifications.index')
            ->with('success', 'Verification request approved successfully.');
    }

    /**
     * Admin method to reject a verification
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function reject(Request $request, $id)
    {
        $this->authorize('admin');

        $request->validate([
            'rejection_reason' => 'required|string|max:1000',
        ]);

        $verification = Verification::findOrFail($id);
        $verification->verification_status = 'rejected';
        $verification->rejection_reason = $request->rejection_reason;
        $verification->verified_at = now();
        $verification->verified_by = Auth::id();
        $verification->save();

        // Update user status
        $user = User::find($verification->user_id);
        $user->status = 'Verification Rejected';
        $user->save();

        return redirect()->route('admin.verifications.index')
            ->with('success', 'Verification request rejected successfully.');
    }
}
