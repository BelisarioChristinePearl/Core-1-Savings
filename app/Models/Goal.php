<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Goal extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'category',
        'name',
        'target_amount',
        'current_amount',
        'monthly_contribution',
        'target_date',
        'status'
    ];

    /**
     * Get the user that owns the goal
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
    /**
     * Calculate progress percentage
     *
     * @return float
     */
    public function getProgressPercentageAttribute()
    {
        if ($this->target_amount <= 0) {
            return 0;
        }
        
        return min(100, round(($this->current_amount / $this->target_amount) * 100, 2));
    }
    
    /**
     * Calculate months remaining until target date
     *
     * @return int
     */
    public function getMonthsRemainingAttribute()
    {
        $targetDate = \Carbon\Carbon::parse($this->target_date);
        $now = \Carbon\Carbon::now();
        
        if ($targetDate->isPast()) {
            return 0;
        }
        
        return $now->diffInMonths($targetDate);
    }
}