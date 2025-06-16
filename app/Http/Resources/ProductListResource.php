<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;

class ProductListResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'id'=>$this->id,
            'name'=>$this->name,
            'point'=>$this->point,
            'dp'=>$this->dp,
            'rare'=>$this->rare,
            'marks'=>$this->marks,
            'lost_type'=>$this->lost_type,
            // 'emission_percentage'=>$this->emission_percentage,
            'image'=>getProductImageUrl($this->image),
            'is_last'=>$this->is_last,
            'gacha_id'=>$this->gacha_id,

            'category'=>getCategoryTitle($this->category_id),
            'product_type'=>$this->product_type,
            'status_product'=>$this->status_product,
            'order'=>$this->order,

            'status'=>$this->status,
            'is_lost_product'=>$this->is_lost_product,
            'rank'=>$this->rank,
            // 'badge'=>$this->rare == 'PSA' ? '/images/psa10.png' : ($this->rare == 'BOX' || $this->rare == 'パック' ? '/images/unopened.png' : null),
            'expiration'=>$this->created_at->addDays(7)->format('Y年n月j日 H:i'),
            'updated_at'=>$this->updated_at,
            'gacha_title'=>$this->gacha_title,
        ];
    }
}
