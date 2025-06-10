<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Point_history extends Model
{
    use HasFactory;
    protected $table = 'point_history';
    protected $fillable = ['point_before', 'point_diff', 'point_type', 'user_id', 'ref_id'];
}