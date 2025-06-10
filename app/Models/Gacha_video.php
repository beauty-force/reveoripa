<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Gacha_video extends Model
{
    use HasFactory;
    protected $fillable = [
        'gacha_id',
        'level',
        'point',
        'file'
    ];
}
