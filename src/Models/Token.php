<?php

namespace Akika\LaravelMpesa\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Token extends Model
{
    use HasFactory;

    protected $fillable = [
        'access_token', 'requested_at', 'expires_at'
    ];

    public $timestamps = false;

    // cretae a funciton to return time to expiry time in minutes
    public function timeToExpiry()
    {
        return now()->diffInMinutes($this->expires_at);
    }
}
