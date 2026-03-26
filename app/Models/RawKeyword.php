<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RawKeyword extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'raw_keyword';

    protected $fillable = [
        'keyword',
        'description',
        'end_date',
    ];

    public $incrementing = false;
    public $timestamps   = false;
    protected $keyType   = 'string';

    protected $casts = [
        'end_date'   => 'date',
        'created_at' => 'datetime',
    ];
}