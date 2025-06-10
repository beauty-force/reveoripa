<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Bank_payment extends Model
{
    use HasFactory;
    protected $fillable = ['user_id', 'point_id', 'account', 'bank_name', 'status', 'discount_rate', 'pt_rate'];
    protected $table = 'bank_payments';
}