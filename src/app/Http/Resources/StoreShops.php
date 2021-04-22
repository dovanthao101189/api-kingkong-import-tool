<?php


namespace App\Http\Resources;


use Illuminate\Http\Resources\Json\JsonResource;

class StoreShops extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'type_shop' => $this->type_shop,
            'store_name' => $this->store_name,
            'store_front' => $this->store_front,
            'api_key' => $this->api_key,
            'secret_key' => $this->secret_key,
            'customer_id' => $this->customer_id,
            'created_at' => $this->created_at->format('d/m/Y'),
            'updated_at' => $this->updated_at->format('d/m/Y'),
        ];
    }
}
