<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;



class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'mobile',
        'password',
        'status',
        'notes',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
    public function verifications(): HasMany
    {
        return $this->hasMany(Verification::class);
    }
    public function withdrawals()
{
    return $this->hasMany(Withdrawal::class);
}
    public function verification()
    {
        return $this->hasOne(Verification::class);
    }
    public function hasPendingVerification(): bool
    {
        return $this->verifications()
            ->where('verification_status', 'pending')
            ->exists();
    }
    public function isVerified(): bool
    {
        return $this->status === 'Verified';
    }
    public function isAdmin(): bool
    {
        return $this->utype === 'ADM';
    }
    public function goals()
{
    return $this->hasMany(Goal::class);
}



}
