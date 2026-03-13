<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserApproval extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'user_approval';

    protected $fillable = [
        'id',
        'approved_by',
        'user_id',
    ];

    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    public function user()
    {
        return $this->belongsTo(Users::class, 'user_id', 'id');
    }

    public function approver()
    {
        return $this->belongsTo(Users::class, 'approved_by', 'id');
    }
}
