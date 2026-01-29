<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Rank extends Model
{
    use HasFactory;
    protected $fillable = [
        'rank',
        'title',
        'image',
        'badge',
        'limit',
        'keep_limit',
        'bonus',
        'pt_rate',
        'dp_rate'
    ];
}
