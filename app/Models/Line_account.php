<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Line_account extends Model
{
    use HasFactory;
    protected $table = 'line_account';
    protected $fillable = ['lineId', 'eventId'];
}
