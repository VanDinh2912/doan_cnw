<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Models\Concerns\GhiNhatKy;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, GhiNhatKy;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'khoa_luc',
        'bat_buoc_doi_mat_khau',
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
            'khoa_luc' => 'datetime',
            'pin_doi_luc' => 'datetime',
            'bat_buoc_doi_mat_khau' => 'boolean',
            'bat_buoc_doi_pin' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function donHangs(): HasMany
    {
        return $this->hasMany(DonHang::class);
    }

    public function bienDongKhos(): HasMany
    {
        return $this->hasMany(BienDongKho::class);
    }

    public function khachHang(): HasOne
    {
        return $this->hasOne(KhachHang::class);
    }
}
