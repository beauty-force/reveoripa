<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CouponDiscountRate extends Model
{
    use HasFactory;
    protected $fillable = ['coupon_id', 'point_id', 'rate'];
}