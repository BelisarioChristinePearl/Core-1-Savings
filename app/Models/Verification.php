<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;


class Verification extends Model
 {
    use HasFactory;
    protected $table = 'verifications';

            protected $fillable = [
            'user_id',
            'first_name',
            'last_name',
            'email',
            'date_of_birth',
            'street_address',
            'province',
            'city',
            'postal_code',
            'id_type',
            'id_number',
            'id_front',
            'id_back',
            'selfie',
            'employment_status',
            'monthly_income',
            'income_proof',
            'is_business',
            'business_name',
            'business_registration_number',
            'business_type',
            'business_registration',
            'business_permit',
            'verification_status',
            'rejection_reason',
            'verified_at',
            'verified_by',
        ];

        /**
         * The attributes that should be cast.
         *
         * @var array<string, string>
         */
        protected $casts = [
            'date_of_birth' => 'date',
            'monthly_income' => 'decimal:2',
            'is_business' => 'boolean',
            'verified_at' => 'datetime',
        ];

        /**
         * Get the user that owns the verification.
         */
        public function user(): BelongsTo
        {
            return $this->belongsTo(User::class);
        }

        /**
         * Get the admin who verified this request.
         */
        public function verifier(): BelongsTo
        {
            return $this->belongsTo(User::class, 'verified_by');
        }
}
