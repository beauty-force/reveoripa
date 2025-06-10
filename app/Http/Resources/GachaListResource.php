<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class GachaListResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $timeStatus = $this->timeStatus();
        $ableCount = auth()->user()?->type == 1 ? $this->ableCount() : 0;

        return [
            'id'=>$this->id,
            'title'=>$this->title,
            'point'=>$this->point,
            'consume_point'=>$this->consume_point,
            'count_card'=>$this->count_card,
            'count_rest'=>$this->lost_product_type == '1' ? 10000 : $this->count_card-$this->count,
            'thumbnail'=>getGachaThumbnailUrl($this->thumbnail),
            'status'=>$this->status,
            'timeStatus'=>$timeStatus,
            'order_level'=>$this->order_level,
            'rank_limit'=>$this->rank_limit,
            'ableCount'=>$ableCount,
            'remaining'=>$timeStatus == 0 ? (strtotime($this->start_time) - strtotime(date('Y-m-d H:i:s'))) : 
                ($this->end_time ? strtotime($this->end_time) - strtotime(date('Y-m-d H:i:s')) : 0),
            'gacha_limit'=>$this->gacha_limit,
        ];
    }
}
