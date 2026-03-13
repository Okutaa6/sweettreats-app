<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GroupAccess extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'group_access';

    protected $fillable = [
        'id',
        'group_id',
        'access_id',
    ];

    public $incrementing = false;
    public $timestamps = false;
    protected $keyType = 'string';

    public function group()
    {
        return $this->belongsTo(GroupPath::class, 'group_id', 'id');
    }

    public function access()
    {
        return $this->belongsTo(AccessPath::class, 'access_id', 'id');
    }
}
