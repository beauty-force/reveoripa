<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ranking_bonus extends Model
{
    use HasFactory;
    protected $table = 'ranking_bonus';
    protected $fillable = [
        'period',
        'rank',
        'type',
        'value',
    ];
}
