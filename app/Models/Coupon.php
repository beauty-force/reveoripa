<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    use HasFactory;
    protected $fillable = ['title', 'code', 'point', 'expiration', 'count', 'user_limit', 'type', 'need_line'];

    public function discount_rate()
    {
        return $this->hasMany(CouponDiscountRate::class, 'coupon_id', 'id')
            ->join('points', 'coupon_discount_rates.point_id', '=', 'points.id')
            ->orderBy('points.point', 'asc')
            ->select('coupon_discount_rates.*'); // Ensure only the fields from coupon_discount_rates are selected
    }
}