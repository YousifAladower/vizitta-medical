<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ServiceReservationDetailsResource extends JsonResource
{
    public function toArray($request)
    {
        $result = [
            'id' => $this->id,
            'reservation_no' => $this->reservation_no,
            'day_date' => $this->day_date,
            'from_time' => $this->from_time,
            'to_time' => $this->to_time,
            'status' => $this->status,
            'price' => $this->price,
            'total_price' => $this->total_price,
            'service_type' => $this->service_type,
            'service_rate' => $this->service_rate,
            'rejected_reason_msg' => $this->rejected_reason_msg,
            'rejected_reason_notes' => $this->rejected_reason_notes,
            'paymentMethod' => app()->getLocale() == 'ar' ? $this->paymentMethod->name_ar : $this->paymentMethod->name_en,
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'mobile' => $this->user->mobile,
            ],
            'service' => [
                'id' => $this->service->id,
                'title' => app()->getLocale() == 'ar' ? $this->service->title_ar : $this->service->title_en,
                'price' => $this->service->price,
            ],
            'provider' => [
                'id' => $this->provider->id,
                'name' => app()->getLocale() == 'ar' ? $this->provider->name_ar : $this->provider->name_en,
                'logo' => $this->provider->logo,
            ],
            'branch' => [
                'id' => $this->branch->id,
                'name' => app()->getLocale() == 'ar' ? $this->branch->name_ar : $this->branch->name_en,
                'parent_type' => $this->branch->parent_type->name,
                'latitude' => $this->branch->latitude,
                'longitude' => $this->branch->longitude,
            ],
//            'payment_method' => [
//                'id' => $this->payment_method->id,
//                'name' => app()->getLocale() == 'ar' ? $this->payment_method->name_ar : $this->payment_method->name_en,
//            ],
        ];

        return $result;
    }

}
