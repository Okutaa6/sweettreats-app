<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GroupPath extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'group_path';

    protected $fillable = [
        'id',
        'nama',
        'deskripsi',
        'landing_page',
    ];

    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    public function groupAccesses()
    {
        return $this->hasMany(GroupAccess::class, 'group_id', 'id');
    }

    public function users()
    {
        return $this->hasMany(Users::class, 'group_id', 'id');
    }
}
