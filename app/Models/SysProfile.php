<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SysProfile extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'sys_profile';

    protected $fillable = [
        'id',
        'syslogo',
        'systitle',
        'sysname',
    ];

    public $incrementing = false;
    public $timestamps = false;
    protected $keyType = 'string';
}
