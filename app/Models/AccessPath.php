<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccessPath extends Model
{
    use HasFactory;
    use HasUuids;

    protected $primaryKey = 'id';
    protected $table = 'access_path';
    public $incrementing = false;
    public $timestamps = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'pid',
        'nama',
        'icon',
        'urutan',
        'urutan_path',
        'link',
        'pid_path',
    ];

    public function parent()
    {
        return $this->belongsTo(AccessPath::class, 'pid', 'id');
    }

    public function children()
    {
        return $this->hasMany(AccessPath::class, 'pid', 'id');
    }
}
