<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ranking_history extends Model
{
    use HasFactory;
    protected $table = 'ranking_history';
    protected $fillable = [
        'period',
        'startDate',
        'endDate',
        'rank',
        'user_id',
        'ref_id',
        'total_point',
        'description',
        'status',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function profile()
    {
        return $this->hasOne(Profile::class, 'user_id', 'user_id');
    }
}
