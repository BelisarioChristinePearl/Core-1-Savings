<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Deposit extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'transaction_id',
        'user_id',
        'name',
        'amount',
        'payment_method',
        'reference_number',
        'receipt_path',
        'notes',
        'status'
    ];

    /**
     * Generate a unique transaction ID
     *
     * @return string
     */
    public static function generateTransactionId()
    {
        $year = date('Y');
        $latest = self::whereYear('created_at', $year)->count() + 1;
        return '#DEP-' . $year . '-' . str_pad($latest, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Get the user that owns the deposit
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    

}
