<?php

namespace App\Traits\Dashboard;

use App\Models\Offer;
use App\Models\OfferBranch;
//use App\Models\PromoCode_Doctor;
use App\Models\Provider;
//use App\Models\Doctor;
use App\Models\User;
use Freshbitsweb\Laratables\Laratables;
//use Illuminate\Http\Request;
//use function foo\func;

trait OfferTrait
{
    public function getOfferById($id)
    {
        return Offer::find($id);
    }

    public function getOfferByIdWithRelation($id)
    {
        $offer = Offer::with('offerBranches', 'reservations')->find($id);
        if (!$offer) {
            return null;
        }
        return $offer;
    }

    public function getOfferByIdWithRelations($id)
    {
        $offer = Offer::with(['offerBranches' => function ($q) {
            $q->wherehas('branch');
            $q->with(['branch' => function ($qq) {
                $qq->select('id', 'name_ar', 'provider_id');
            }]);
        }, 'reservations'])->find($id);
        if (!$offer) {
            return null;
        }
        return $offer;
    }

    public function getAll()
    {
        return Laratables::recordsOf(Offer::class, function ($query) {
            return $query->orderBy('expired_at', 'DESC');
        });
    }

    public function getAllBeneficiaries($couponId)
    {
        return User::with(['reservations' => function ($re) use ($couponId) {
            // $re -> where('reservations.')select('user_id','reservation_no');
            $re->where('promocode_id', $couponId);
        }])->whereHas('reservations', function ($q) use ($couponId) {
            $q->whereHas('coupon', function ($qq) use ($couponId) {
                $qq->where('id', $couponId);
            });
        })->get();
    }

    public function getBranchTable($promoId)
    {
        return Laratables::recordsOf(OfferBranch::class, function ($query) use ($promoId) {
            return $query->where('offer_id', $promoId);
        });
    }

    /*public function getDoctorTable($promoId)
    {
        return Laratables::recordsOf(PromoCode_Doctor::class, function ($query) use ($promoId) {
            return $query->where('promocodes_id', $promoId);
        });
    }*/

    public function createOffer($request)
    {
        $offer = Offer::create($request);
        return $offer;
    }

    public function updateOffer($offer, $request)
    {
        $offer = $offer->update($request);
        return $offer;
    }

    public function saveCouponBranchs($offerId, $branchsIds, $provider_id)
    {
        if (count($branchsIds) > 0) {
            foreach ($branchsIds as $id) {
                OfferBranch::Create([
                    'offer_id' => $offerId,
                    'branch_id' => $id,
                ]);
            }
        } else { //save all branches  and doctors for that provider

            $branchs = Provider::where('provider_id', $provider_id)->pluck('id');
            if (count($branchs) > 0) {
                foreach ($branchs as $id) {
                    OfferBranch::Create([
                        'offer_id' => $offerId,
                        'branch_id' => $id,
                    ]);

                    /*$branch = Provider::find($id);
                    $doctorIds = $branch->doctors()->pluck('id');

                    foreach ($doctorIds as $doctor_id) {
                        PromoCode_Doctor::Create([
                            'promocodes_id' => $offerId,
                            'doctor_id' => $doctor_id,
                        ]);
                    }*/
                }
            }
        }
    }

    /*    public function saveCouponDoctors($pormoCodeId, $doctorsIds, $provider_id)
        {
            if (count($doctorsIds) > 0) {
                foreach ($doctorsIds as $id) {
                    PromoCode_Doctor::Create([
                        'promocodes_id' => $pormoCodeId,
                        'doctor_id' => $id,
                    ]);
                }
            } else {

                $branches = PromoCode_branch::where('promocodes_id', $pormoCodeId)->pluck('branch_id');

                if (count($branches) > 0) {
                    foreach ($branches as $branche_id) {
                        $doctors = Doctor::where('provider_id', $branche_id)->pluck('id');
                        foreach ($doctors as $doctor_id) {
                            PromoCode_Doctor::Create([
                                'promocodes_id' => $pormoCodeId,
                                'doctor_id' => $doctor_id,
                            ]);
                        }
                    }
                }
            }
        }*/


}
