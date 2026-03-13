<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Users extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'users';
    public $timestamps = false;

    protected $fillable = [
        'id',
        'nama',
        'email',
        'password',
        'status',
        'last_login',
        'reset_token',
        'register_token',
        'group_id',
        'raw_branch_id', // branch
    ];

    public $incrementing = false;
    protected $keyType = 'string';

    protected $hidden = ['password'];

    public function group()
    {
        return $this->belongsTo(GroupPath::class, 'group_id', 'id');
    }

    public function approvals()
    {
        return $this->hasMany(UserApproval::class, 'approved_by', 'id');
    }

    public function branch()
    {
        return $this->belongsTo(RawBranch::class, 'raw_branch_id', 'id');
    }
}