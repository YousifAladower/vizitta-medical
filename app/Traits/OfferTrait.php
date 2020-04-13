<?php

namespace App\Traits;

use App\Models\OfferCategory;
use App\Models\Reservation;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

trait OfferTrait
{
    public function getOfferCatsV2()
    {
        $category = OfferCategory::query();
        return $category
            ->withOutTimer()
            ->select('id',
                DB::raw('name_' . $this->getCurrentLang() . ' as name'), 'photo',
                'hours',
                'minutes',
                'seconds')
            ->orderBy('lft')
            ->get();
    }

    public function getTimerOfferCategoriesV2()
    {
        $category = OfferCategory::query();
        return $category
            ->withTimer()
            ->select('id',
                DB::raw('name_' . $this->getCurrentLanguage() . ' as name'), 'photo',
                'hours',
                'minutes',
                'seconds')
            ->orderBy('lft')
            ->get();
    }

    public function getCurrentLanguage()
    {
        return app()->getLocale();
    }

    public function getUserOffersReservations($userId)
    {

        return Reservation::with(['offer' => function ($q) {
            $q->select('id',
                DB::raw('title_' . app()->getLocale() . ' as title')
            );
        }, 'provider' => function ($que) {
            $que->select('id', 'provider_id', DB::raw('name_' . app()->getLocale() . ' as name'));
        }, 'paymentMethod' => function ($qu) {
            $qu->select('id', DB::raw('name_' . app()->getLocale() . ' as name'));
        }])
            ->where('user_id', $userId)
            ->whereNotNull('offer_id')
            ->orderBy('day_date')
            ->orderBy('order')
            ->select('id', 'reservation_no','payment_method_id', 'offer_id', 'day_date', 'from_time', 'to_time', 'provider_rate', 'offer_rate', 'approved', 'provider_id', 'price', 'rate_comment',
                'rate_date')
            ->paginate(PAGINATION_COUNT);
    }


}
