<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

class PassRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = Arr::only($this->resource->attributesToArray(), [
            'id', 'user_id', 'season_id', 'season_pass_type_id', 'pass_type',
            'passholder_first_name', 'passholder_last_name', 'passholder_email',
            'passholder_birth_date', 'country', 'is_renewal', 'renewal_pass_id',
            'renewal_order_number', 'promo_code', 'assign_code_date',
            'email_notify_time', 'redemption_date', 'created_at', 'updated_at',
        ]);

        foreach (['season', 'seasonPassType'] as $relation) {
            if ($this->resource->relationLoaded($relation)) {
                $data[$relation === 'seasonPassType' ? 'season_pass_type' : $relation] = $this->resource->getRelation($relation)?->toArray();
            }
        }

        if ($this->resource->relationLoaded('user')) {
            $data['user'] = $this->resource->getRelation('user')?->only(['id', 'first_name', 'last_name', 'email']);
        }

        return $data;
    }
}
