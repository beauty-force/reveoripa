<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;

class DeliveryProductResource extends JsonResource
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
            'rare'=>$this->rare,
            'image'=>getProductImageUrl($this->image),
            'status'=>$this->status,
            'updated_at'=>$this->updated_at->diffForHumans(),
            'updated_at_time'=>$this->updated_at->format('Y-m-d H:i'),
            'tracking_number'=>$this->tracking_number,
            'user_name'=>$this->user_name,
            'email'=>$this->email,
            'user_id'=>$this->user_id,
            'gacha_title'=>$this->gacha_title,
            'rank'=>$this->rank,
            'badge'=>$this->rare == 'PSA' ? '/images/psa10.png' : ($this->rare == 'BOX' || $this->rare == 'パック' ? '/images/unopened.png' : null),
        ];
    }
}
