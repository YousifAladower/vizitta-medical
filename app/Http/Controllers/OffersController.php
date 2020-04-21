<?php

namespace App\Http\Controllers;


use App\Models\Doctor;
use App\Models\Filter;
use App\Models\GeneralNotification;
use App\Models\Mix;
use App\Models\Offer;
use App\Models\OfferBranchTime;
use App\Models\OfferCategory;
use App\Models\PromoCode;
use App\Models\PromoCodeCategory;
use App\Models\Provider;
use App\Models\Reservation;
use App\Models\ReservedTime;
use App\Models\Specification;
use App\Models\User;
use App\Models\Payment;
use App\Traits\GlobalTrait;
use App\Traits\DoctorTrait;
use App\Traits\OdooTrait;
use App\Traits\OfferTrait;
use App\Traits\SMSTrait;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Validator;
use DateTime;
use DB;
use Str;

use Illuminate\Validation\Rule;
use function foo\func;

class OffersController extends Controller
{
    use  GlobalTrait, OfferTrait, SMSTrait, DoctorTrait, OdooTrait;

    public function __construct()
    {

    }

    public function index(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                "category_id" => "required",
            ]);

            if ($validator->fails()) {
                $code = $this->returnCodeAccordingToInput($validator);
                return $this->returnValidationError($code, $validator);
            }

            $user = $this->auth('user-api');
            if (!$user) {
                return $this->returnError('D000', trans('messages.User not found'));
            }

            $orderBy = 'id';
            if (isset($request->mostVisits) && $request->mostVisits == 1) {
                $orderBy = 'visits';
            }

            if (isset($request->mostpaid) && $request->mostpaid == 1) {
                $orderBy = 'uses';
            }

            /*  if (isset($request->lessThan) && $request->lessThan == 1) {
                  $orderBy = 'price';
              }*/

            // if 0 get all offer
            if ($request->category_id != 0) {
                $category = PromoCodeCategory::find($request->category_id);
                if (!$category) {
                    return $this->returnError('D000', trans('messages.category not found'));
                }
                $categoryId = $category->id;
                if (!$category)
                    return $this->returnError('E001', trans('messages.There is no category with this id'));
                else {

                    if (isset($request->featured) && $request->featured == 1) {
                        $offers = PromoCode::where(function ($qq) use ($user) {
                            $qq->where('general', 1)
                                ->orWhereHas('users', function ($qu) use ($user) {
                                    $qu->where('users.id', $user->id);
                                });
                        })->featured()
                            ->active()
                            ->valid()
                            ->with(['provider' => function ($q) {
                                $q->select('id', 'rate', 'logo', 'type_id',
                                    DB::raw('name_' . $this->getCurrentLang() . ' as name'));
                                $q->with(['type' => function ($q) {
                                    $q->select('id', DB::raw('name_' . app()->getLocale() . ' as name'));
                                }]);
                            }])
                            ->whereHas('categories', function ($q) use ($categoryId) {
                                $q->where('promocodes_categories.id', $categoryId);
                            })
                            ->limit(10)
                            ->selection()
                            ->inRandomOrder()
                            ->get();
                    } else {
                        //return $this->returnError('E001', trans('messages.There featured  must be 1 or not present '));
                        $offers = PromoCode::where(function ($qq) use ($user) {
                            $qq->where('general', 1)
                                ->orWhereHas('users', function ($qu) use ($user) {
                                    $qu->where('users.id', $user->id);
                                });
                        })->active()
                            ->valid()
                            ->with(['provider' => function ($q) {
                                $q->select('id', 'rate', 'logo', 'type_id',
                                    DB::raw('name_' . $this->getCurrentLang() . ' as name'));
                                $q->with(['type' => function ($q) {
                                    $q->select('id', DB::raw('name_' . app()->getLocale() . ' as name'));
                                }]);
                            }])
                            ->whereHas('categories', function ($q) use ($categoryId) {
                                $q->where('promocodes_categories.id', $categoryId);
                            })
                            ->selection()
                            ->inRandomOrder()
                            ->paginate(10);
                    }
                }

            } else {

                if (isset($request->featured) && $request->featured == 1) {
                    $offers = PromoCode::where(function ($qq) use ($user) {
                        $qq->where('general', 1)
                            ->orWhereHas('users', function ($qu) use ($user) {
                                $qu->where('users.id', $user->id);
                            });
                    })->featured()
                        ->active()
                        ->valid()
                        ->with(['provider' => function ($q) {
                            $q->select('id', 'rate', 'logo', 'type_id',
                                DB::raw('name_' . $this->getCurrentLang() . ' as name'));
                            $q->with(['type' => function ($q) {
                                $q->select('id', DB::raw('name_' . app()->getLocale() . ' as name'));
                            }]);
                        },])
                        ->selection()
                        ->limit(25)
                        ->inRandomOrder()
                        ->get();
                } else

                    // PromoCode::find(6) -> currentId();
                    $offers = PromoCode::where(function ($qq) use ($user) {
                        $qq->where('general', 1)
                            ->orWhereHas('users', function ($qu) use ($user) {
                                $qu->where('users.id', $user->id);
                            });
                    })->active()
                        ->valid()
                        ->with(['provider' => function ($q) {
                            $q->select('id', 'rate', 'logo', 'type_id',
                                DB::raw('name_' . $this->getCurrentLang() . ' as name'));
                            $q->with(['type' => function ($q) {
                                $q->select('id', DB::raw('name_' . app()->getLocale() . ' as name'));
                            }]);
                        }])
                        ->selection()
                        ->inRandomOrder()
                        ->paginate(10);
            }

            if (isset($request->featured) && $request->featured == 1) {
                //if coupon allowed only for some users
                /*        if (isset($offers) && $offers->count() > 0) {
                            foreach ($offers as $index => $offer) {
                                if (!empty($offer->users) && count($offer->users) > 0) {
                                    $authUserExistsForThisOffer = in_array($user->id, array_column($offer->users->toArray(), 'id'));
                                    if (!$authUserExistsForThisOffer) {
                                        unset($offers[$index]);
                                    }
                                }
                            }
                        }*/
                return $this->returnData('featured_offers', $offers);
            }

            $selectedValue = 0;
            if (count($offers->toArray()) > 0) {

                foreach ($offers as $index => $offer) {
                    unset($offer->provider_id);
                    unset($offer->available_count);
                    unset($offer->status);
                    unset($offer->created_at);
                    unset($offer->updated_at);
                    unset($offer->specification_id);
                    /* if ($offers->coupons_type_id == 1) {
                         $offers->price = "0";
                     }*/
                    if ($offer->coupons_type_id == 2) {
                        $offer->discount = "0";
                        $offer->code = "0";
                    }

                }

                $offers = json_decode($offers->toJson());
                $total_count = $offers->total;
                $offersJson = new \stdClass();
                $offersJson->current_page = $offers->current_page;
                $offersJson->total_pages = $offers->last_page;
                $offersJson->total_count = $total_count;
                $offersJson->data = $offers->data;
                return $this->returnData('offers', $offersJson);
            }

            return $this->returnError('E001', trans('messages.No offers founded'));

        } catch (\Exception $ex) {
            return $this->returnError('E1' . $ex->getCode(), $ex->getMessage());
            // return $ex;
        }
    }

    public function indexV2(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                "category_id" => "required|exists:offers_categories,id",
                "subCategory_id" => "required",
            ]);

            if ($validator->fails()) {
                $code = $this->returnCodeAccordingToInput($validator);
                return $this->returnValidationError($code, $validator);
            }

            $user = $this->auth('user-api');
            if (!$user) {
                //return $this->returnError('D000', trans('messages.User not found'));
                return $this->getOfferForVisitors($request);
            }

            $orderBy = 'id';
            $conditions = [];

            if (isset($request->filter_id) && !empty($request->filter_id)) {
                $filter = Filter::find($request->filter_id);
                if (!$filter)
                    return $this->returnError('D000', trans('messages.filter not found'));
                if (in_array($filter->operation, [0, 1, 2])) { //if filter operation is < or > or =
                    if ($filter->operation == 0) {   //less than
                        array_push($conditions, ['price_after_discount', '<=', (int)$filter->price]);
                    } elseif ($filter->operation == 1) {  //greater than
                        array_push($conditions, ['price_after_discount', '>=', (int)$filter->price]);
                    } else {
                        array_push($conditions, ['price_after_discount', '=', (int)$filter->price]);
                    }
                    $orderBy = 'price';
                } elseif (in_array($filter->operation, [3, 4, 5])) {  //3-> most paid 4-> most visited 5-> latest
                    if ($filter->operation == 3) {
                        $orderBy = 'uses';
                    } elseif ($filter->operation == 4) {
                        $orderBy = 'visits';
                    } else
                        $orderBy = 'id';
                } else {
                    $orderBy = 'id';
                }
            }

            $category = OfferCategory::find($request->category_id);
            $categoryId = $category->id;

            // if 0 get all offer in this subCategory
            if ($request->subCategory_id != 0) {
                $subCategory = OfferCategory::whereNotNull('parent_id')->where('id', $request->subCategory_id)->first();

                if (!$subCategory)
                    return $this->returnError('E001', trans('messages.offer subcategory not found'));
                $subCategoryId = $subCategory->id;

                if (isset($request->featured) && $request->featured == 1) {
                    if (!empty($conditions) && count($conditions) > 0) {
                        $offers = Offer::where(function ($qq) use ($user) {
                            $qq->where('general', 1)
                                ->orWhereHas('users', function ($qu) use ($user) {
                                    $qu->where('users.id', $user->id);
                                });
                        })
                            ->where($conditions)
                            ->featured()
                            ->active()
                            ->valid()
                            ->with(['provider' => function ($q) {
                                $q->select('id', 'rate', 'logo', 'type_id',
                                    DB::raw('name_' . $this->getCurrentLang() . ' as name'));
                                $q->with(['type' => function ($q) {
                                    $q->select('id', DB::raw('name_' . app()->getLocale() . ' as name'));
                                }]);
                            }])
                            ->whereHas('categories', function ($q) use ($subCategoryId, $categoryId) {
                                $q->where('offers_categories.id', $subCategoryId);
                                $q->where('parent_id', $categoryId);
                            })
                            ->orderBy($orderBy, 'DESC')
                            ->limit(10)
                            ->selection()
                            ->get();
                    } else {
                        $offers = Offer::where(function ($qq) use ($user) {
                            $qq->where('general', 1)
                                ->orWhereHas('users', function ($qu) use ($user) {
                                    $qu->where('users.id', $user->id);
                                });
                        })
                            ->featured()
                            ->active()
                            ->valid()
                            ->with(['provider' => function ($q) {
                                $q->select('id', 'rate', 'logo', 'type_id',
                                    DB::raw('name_' . $this->getCurrentLang() . ' as name'));
                                $q->with(['type' => function ($q) {
                                    $q->select('id', DB::raw('name_' . app()->getLocale() . ' as name'));
                                }]);
                            }])
                            ->whereHas('categories', function ($q) use ($subCategoryId, $categoryId) {
                                $q->where('offers_categories.id', $subCategoryId);
                                $q->where('parent_id', $categoryId);
                            })
                            ->orderBy($orderBy, 'DESC')
                            ->limit(10)
                            ->selection()
                            ->get();
                    }

                } else { // get non featured offers
                    //return $this->returnError('E001', trans('messages.There featured  must be 1 or not present '));
                    if (!empty($conditions) && count($conditions) > 0) {

                        $offers = Offer::where(function ($qq) use ($user) {
                            $qq->where('general', 1)
                                ->orWhereHas('users', function ($qu) use ($user) {
                                    $qu->where('users.id', $user->id);
                                });
                        })->active()
                            ->valid()
                            ->with(['provider' => function ($q) {
                                $q->select('id', 'rate', 'logo', 'type_id',
                                    DB::raw('name_' . $this->getCurrentLang() . ' as name'));
                                $q->with(['type' => function ($q) {
                                    $q->select('id', DB::raw('name_' . app()->getLocale() . ' as name'));
                                }]);
                            }])
                            ->whereHas('categories', function ($q) use ($subCategoryId, $categoryId) {
                                $q->where('offers_categories.id', $subCategoryId);
                                $q->where('parent_id', $categoryId);
                            })
                            ->where($conditions)
                            ->selection()
                            ->orderBy($orderBy, 'DESC')
                            ->paginate(10);
                    } else {
                        $offers = Offer::where(function ($qq) use ($user) {
                            $qq->where('general', 1)
                                ->orWhereHas('users', function ($qu) use ($user) {
                                    $qu->where('users.id', $user->id);
                                });
                        })
                            ->active()
                            ->valid()
                            ->with(['provider' => function ($q) {
                                $q->select('id', 'rate', 'logo', 'type_id',
                                    DB::raw('name_' . $this->getCurrentLang() . ' as name'));
                                $q->with(['type' => function ($q) {
                                    $q->select('id', DB::raw('name_' . app()->getLocale() . ' as name'));
                                }]);
                            }])
                            ->whereHas('categories', function ($q) use ($subCategoryId, $categoryId) {
                                $q->where('offers_categories.id', $subCategoryId);
                                $q->where('parent_id', $categoryId);
                            })
                            ->selection()
                            ->orderBy($orderBy, 'DESC')
                            ->paginate(10);
                    }
                }
            } else {   // if the is no subcategory id  (i.e subCategory_id =0 )will get all offers  in main category
                if (isset($request->featured) && $request->featured == 1) {
                    if (!empty($conditions) && count($conditions) > 0) {
                        $offers = Offer::where(function ($qq) use ($user) {
                            $qq->where('general', 1)
                                ->orWhereHas('users', function ($qu) use ($user) {
                                    $qu->where('users.id', $user->id);
                                });
                        })->featured()
                            ->active()
                            ->valid()
                            ->with(['provider' => function ($q) {
                                $q->select('id', 'rate', 'logo', 'type_id',
                                    DB::raw('name_' . $this->getCurrentLang() . ' as name'));
                                $q->with(['type' => function ($q) {
                                    $q->select('id', DB::raw('name_' . app()->getLocale() . ' as name'));
                                }]);
                            },])
                            ->where($conditions)
                            ->whereHas('categories', function ($q) use ($categoryId) {
                                $q->where('parent_id', $categoryId);
                            })
                            ->orderBy($orderBy, 'DESC')
                            ->selection()
                            ->limit(25)
                            ->get();
                    } else {
                        $offers = Offer::where(function ($qq) use ($user) {
                            $qq->where('general', 1)
                                ->orWhereHas('users', function ($qu) use ($user) {
                                    $qu->where('users.id', $user->id);
                                });
                        })->featured()
                            ->active()
                            ->valid()
                            ->with(['provider' => function ($q) {
                                $q->select('id', 'rate', 'logo', 'type_id',
                                    DB::raw('name_' . $this->getCurrentLang() . ' as name'));
                                $q->with(['type' => function ($q) {
                                    $q->select('id', DB::raw('name_' . app()->getLocale() . ' as name'));
                                }]);
                            },])
                            ->whereHas('categories', function ($q) use ($categoryId) {
                                $q->where('parent_id', $categoryId);
                            })
                            ->orderBy($orderBy, 'DESC')
                            ->selection()
                            ->limit(25)
                            ->get();
                    }
                } else {
                    if (!empty($conditions) && count($conditions) > 0) {
                        // PromoCode::find(6) -> currentId();
                        $offers = Offer::where(function ($qq) use ($user) {
                            $qq->where('general', 1)
                                ->orWhereHas('users', function ($qu) use ($user) {
                                    $qu->where('users.id', $user->id);
                                });
                        })->where($conditions)
                            ->whereHas('categories', function ($q) use ($categoryId) {
                                $q->where('parent_id', $categoryId);

                            })
                            ->active()
                            ->valid()
                            ->with(['provider' => function ($q) {
                                $q->select('id', 'rate', 'logo', 'type_id',
                                    DB::raw('name_' . $this->getCurrentLang() . ' as name'));
                                $q->with(['type' => function ($q) {
                                    $q->select('id', DB::raw('name_' . app()->getLocale() . ' as name'));
                                }]);
                            }])
                            ->selection()
                            ->orderBy($orderBy, 'DESC')
                            ->paginate(10);
                    } else {
                        $offers = Offer::where(function ($qq) use ($user) {
                            $qq->where('general', 1)
                                ->orWhereHas('users', function ($qu) use ($user) {
                                    $qu->where('users.id', $user->id);
                                });
                        })->active()
                            ->valid()
                            ->whereHas('categories', function ($q) use ($categoryId) {
                                $q->where('parent_id', $categoryId);

                            })
                            ->with(['provider' => function ($q) {
                                $q->select('id', 'rate', 'logo', 'type_id',
                                    DB::raw('name_' . $this->getCurrentLang() . ' as name'));
                                $q->with(['type' => function ($q) {
                                    $q->select('id', DB::raw('name_' . app()->getLocale() . ' as name'));
                                }]);
                            }])
                            ->selection()
                            ->orderBy($orderBy, 'DESC')
                            ->paginate(10);
                    }
                }
            }

            if (isset($request->featured) && $request->featured == 1) {
                //if coupon allowed only for some users
                /*        if (isset($offers) && $offers->count() > 0) {
                            foreach ($offers as $index => $offer) {
                                if (!empty($offer->users) && count($offer->users) > 0) {
                                    $authUserExistsForThisOffer = in_array($user->id, array_column($offer->users->toArray(), 'id'));
                                    if (!$authUserExistsForThisOffer) {
                                        unset($offers[$index]);
                                    }
                                }
                            }
                        }*/
                return $this->returnData('featured_offers', $offers);
            }

            $selectedValue = 0;
            if (count($offers->toArray()) > 0) {

                foreach ($offers as $index => $offer) {
                    unset($offer->provider_id);
                    unset($offer->available_count);
                    unset($offer->status);
                    unset($offer->created_at);
                    unset($offer->updated_at);
                    unset($offer->specification_id);
                    /* if ($offers->coupons_type_id == 1) {
                         $offers->price = "0";
                     }*/
                    if ($offer->coupons_type_id == 2) {
                        $offer->discount = "0";
                        $offer->code = "0";
                    }

                }

                $offers = json_decode($offers->toJson());
                $total_count = $offers->total;
                $offersJson = new \stdClass();
                $offersJson->current_page = $offers->current_page;
                $offersJson->total_pages = $offers->last_page;
                $offersJson->total_count = $total_count;
                $offersJson->data = $offers->data;
                return $this->returnData('offers', $offersJson);
            }

            return $this->returnError('E001', trans('messages.No offers founded'));

        } catch (\Exception $ex) {
            return $this->returnError('E1' . $ex->getCode(), $ex->getMessage());
        }
    }


    public function getReservationDetails(Request $request)
    {
        try {

            $validator = Validator::make($request->all(), [
                "reservation_id" => "required|exists:reservations,id",
            ]);

            if ($validator->fails()) {
                $code = $this->returnCodeAccordingToInput($validator);
                return $this->returnValidationError($code, $validator);
            }

            $user = $this->auth('user-api');
            if (!$user)
                return $this->returnError('D000', __('messages.user not found'));

            $reservation_details = $this->getUserOffersReservationByReservationId($request->reservation_id);

            if ($reservation_details) {
                $main_provider = Provider::where('id', $reservation_details->provider['provider_id'])
                    ->select('id', \Illuminate\Support\Facades\DB::raw('name_' . app()->getLocale() . ' as name'))
                    ->first();
                $reservation_details->main_provider = $main_provider ? $main_provider : '';
                $reservation_details->makeHidden(['for_me', 'branch_no', 'is_reported', 'admin_value_from_reservation_price_Tax', 'reservation_total', 'comment_report']);
                return $this->returnData('reservation_details', $reservation_details);
            } else
                return $this->returnError('E001', trans('messages.No reservations founded'));
        } catch (\Exception $ex) {
            return $ex;
            return $this->returnError($ex->getCode(), $ex->getMessage());
        }
    }


    public function show(Request $request, $allow_code = false, $proCode = 0)
    {
        try {
            $user = $this->auth('user-api');
            if (!$user) {
                return $this->returnError('D000', trans('messages.User not found'));
            }
            $validator = Validator::make($request->all(), [
                "id" => "required|exists:promocodes,id",
            ]);

            if ($validator->fails()) {
                $code = $this->returnCodeAccordingToInput($validator);
                return $this->returnValidationError($code, $validator);
            }

            $user = $this->auth('user-api');
            $_offer = PromoCode::active()->with(['provider' => function ($q) {
                $q->select('id', 'rate', 'logo', 'type_id',
                    DB::raw('name_' . $this->getCurrentLang() . ' as name'));
                $q->with(['type' => function ($q) {
                    $q->select('id', DB::raw('name_' . app()->getLocale() . ' as name'));
                }]);
            }, 'promocodebranches' => function ($qq) {
                $qq->select('*')->with(['branch' => function ($qqq) {
                    $qqq->select('id', DB::raw('name_' . $this->getCurrentLang() . ' as name'));
                }]);
            }
            ])->selection();

            $offer = $_offer->find($request->id);
            if ($offer != null)
                unset($offer->provider_id);
            unset($offer->available_count);
            unset($offer->status);
            unset($offer->created_at);
            unset($offer->updated_at);
            /* if ($offer->coupons_type_id == 1) {
                 $offer->price = "0";
             }*/
            if ($offer->coupons_type_id == 2) {
                $offer->discount = "0";
                if (!$allow_code)
                    $offer->code = "0";
                else
                    $offer->code = $proCode;
            }

            event(new \App\Events\OfferWasVisited($offer));   // fire increase countrt  event
            return $this->returnData('offer', json_decode(json_encode($offer)));

            return $this->returnError('E001', trans('messages.No offer with this id'));

        } catch (\Exception $ex) {
            return $this->returnError($ex->getCode(), $ex->getMessage());
        }
    }

    public function saveOfferPaymentDetails(Request $request)
    {
        try {
            $rules = [
                "id" => "required|exists:promocodes,id",
                "for_me" => "required|in:0,1",
                "total_amount" => "required",
                //"process_num" => "required|unique:payments,payment_no",
            ];

            if ($request->has('for_me')) {
                if ($request->for_me == 0) {
                    $rules['invited_user_mobile'] = "required";
                }
            }

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                $code = $this->returnCodeAccordingToInput($validator);
                return $this->returnValidationError($code, $validator);
            }
            $user = null;
            if ($request->api_token)
                $user = User::where('api_token', $request->api_token)->first();
            if (!$user)
                return $this->returnError('D000', trans('messages.User not found'));

            //check if amount paid equel offer amount
            $promoCode = PromoCode::find($request->id);
            if ($promoCode->price != $request->total_amount) {
                return $this->returnError('D000', trans('messages.paid amount not equal coupon price'));
            }
            if ($promoCode->coupons_type_id != 2) {
                return $this->returnError('D000', trans('messages.cannot pay this coupon'));
            }

            $invitedUser = null;
            $invitedMobile = null;
            if ($request->for_me == 0) {
                $invitedUser = User::where('mobile', $request->invited_user_mobile)->first();
                if (!$invitedUser)
                    return $this->returnError('D000', trans('messages.this mobile to belong to any user'));
                $invitedMobile = $invitedUser->mobile;
            }
            $payment = $this->savePayment($request, $user->id, $invitedMobile);
            $bank_fees = Mix::select('bank_fees')->first();
            $data = [];
            $data['bank_journal'] = 8;
            $data['bank_account'] = 26;
            $data['bank_fees_account'] = 480;
            $data['prepayments_account'] = 420;
            $data['offer_amount_WithoutVAT'] = $request->total_amount;
            $data['reference'] = $payment->payment_no;
            $data['comment'] = " سداد المستخدم عرض  " . $payment->offer->title;
            $data['bank_fees'] = $bank_fees->bank_fees ? $bank_fees->bank_fees : 0;
            $data['cost_center_id'] = 510;

            //save payment tp odoo system api
            if ($user->odoo_user_id) {
                $partner_id = $user->odoo_user_id;
                $data['partner_id'] = $partner_id;
            } else {
                // if provider not has an account on odoo , create new account
                // save user  to odoo erp system
                $odoo_user_id = $this->saveUserToOdoo($user->mobile, $user->name);
                $user->update(['odoo_user_id' => $odoo_user_id]);
                $partner_id = $odoo_user_id;
                $data['partner_id'] = $partner_id;
            }

            //save the app percentage of paid coupon and add the remain of coupon balance to   the branch
            $settings = $this->getAppInfo();
            // $promoCodeMainProvider = $promoCode->provider;
            $paid_coupon_percentage = $promoCode->paid_coupon_percentage;
            $appValueOfThisCoupon = (isset($promoCode->paid_coupon_percentage) && $promoCode->paid_coupon_percentage != null && $promoCode->paid_coupon_percentage > 0) ? ($promoCode->paid_coupon_percentage * $request->total_amount) / 100 : 0;
            $providerValueOfthisCoupon = $request->total_amount - $appValueOfThisCoupon;

            //saveApp Value
            Mix::first()->update(['admin_coupon_balance' => (int)($appValueOfThisCoupon + $settings->admin_coupon_balance)]);
            $odoo_offer_id = $this->BuyOffer($data);  // save to odoo
            $payment->update(['odoo_offer_id' => $odoo_offer_id, 'provider_value_of_coupon' => $providerValueOfthisCoupon]);

            if ($payment->id)
                return $this->show($request, true, $payment->code);
            else
                return $this->returnError('E001', trans('messages.failed to save data'));

        } catch (\Exception $ex) {
            return $this->returnError('E1' . $ex->getCode(), $ex->getMessage());
        }
    }

    public function sendCouponToMobile(Request $request)
    {

        $rules = [
            "id" => "required|exists:promocodes,id",
            "for_me" => "required|in:0,1",
            "code" => "exists:promocodes,code",
        ];
        if ($request->has('for_me')) {
            if ($request->for_me == 0) {
                $rules['invited_user_mobile'] = "required";
            }
        }
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $code = $this->returnCodeAccordingToInput($validator);
            return $this->returnValidationError($code, $validator);
        }
        $user = null;
        $mobile = null;
        if ($request->api_token)
            $user = User::where('api_token', $request->api_token)->first();
        if (!$user)
            return $this->returnError('D000', trans('messages.User not found'));
        $mobile = $user->mobile;
        //check if amount paid equel offer amount
        $promoCode = PromoCode::find($request->id);
        $invitedUser = null;
        if ($request->for_me == 0) {
            $invitedUser = User::where('mobile', $request->invited_user_mobile)->first();
            if (!$invitedUser)
                return $this->returnError('D000', trans('messages.this mobile to belong to any user'));
            $mobile = $invitedUser->mobile;
        }
        $code = $promoCode->code;
        $title = $promoCode->title;
        try {
            $message = __('messages.your Coupon Code') . " " . "{$title} " . __('messages.is') . "  - {$code} ";
            //send mobile sms
            $this->sendSMS($mobile, $message);
            return $this->returnSuccessMessage(trans('coupon code sent successfully'));

        } catch (\Exception $ex) {
        }
        return $this->returnError('E001', trans('messages.failed to send coupon code'));
    }

    protected function savePayment($request, $userId, $invitedMobile)
    {
        $code = $this->getRandomString(6);
        $inputs = [
            'user_id' => $userId,
            'for_me' => $request->for_me,
            'invited_user_mobile' => $invitedMobile,
            'amount' => $request->total_amount,
            'current_amount' => $request->total_amount,
            'payment_no' => Str::random(30),
            'offer_id' => $request->id,
            'code' => $code
        ];
        $payment = Payment::create($inputs);
        return $payment;
    }

    public function doctors(Request $request)
    {
        try {
            $user = $this->auth('user-api');
            if (!$user) {
                return $this->returnError('D000', trans('messages.User not found'));
            }
            $validator = Validator::make($request->all(), [
                "offer_id" => "required|exists:promocodes,id",
                "branch_id" => "required|exists:providers,id",
            ]);

            if ($validator->fails()) {
                $code = $this->returnCodeAccordingToInput($validator);
                return $this->returnValidationError($code, $validator);
            }

            $doctorIds = PromoCode::find($request->offer_id)->promocodedoctors->pluck('doctor_id');
            return $doctors = $this->getOfferDoctors($doctorIds, $user->id, $request->branch_id);

        } catch (\Exception $ex) {
            return $this->returnError($ex->getCode(), $ex->getMessage());
        }
    }


    protected function getOfferDoctors($doctorIds, $userId = null, $branch_id)
    {
        $doctor = Doctor::query();
        $doctor = $doctor->whereHas('provider', function ($q) use ($branch_id) {
            $q->where('id', $branch_id);
        })->whereIn('id', $doctorIds)
            ->with(['specification' => function ($q1) {
                $q1->select('id', \Illuminate\Support\Facades\DB::raw('name_' . app()->getLocale() . ' as name'));
            },// 'times' => function($q){
                // $q->orderBy('order');
                //},
                'nationality' => function ($q2) {
                    $q2->select('id', \Illuminate\Support\Facades\DB::raw('name_' . app()->getLocale() . ' as name'));
                }, 'insuranceCompanies' => function ($q2) {
                    $q2->select('insurance_companies.id', 'image', \Illuminate\Support\Facades\DB::raw('name_' . app()->getLocale() . ' as name'));
                }, 'nickname' => function ($q3) {
                    $q3->select('id', \Illuminate\Support\Facades\DB::raw('name_' . app()->getLocale() . ' as name'));
                }, 'provider' => function ($provider) use ($userId) {

                    $provider->with(['type' => function ($q) {
                        $q->select('id', DB::raw('name_' . app()->getLocale() . ' as name'));

                    }, 'favourites' => function ($qu) use ($userId) {
                        $qu->where('user_id', $userId)->select('provider_id');
                    }, 'city' => function ($q) {
                        $q->select('id', DB::raw('name_' . app()->getLocale() . ' as name'));
                    }, 'district' => function ($q) {
                        $q->select('id', DB::raw('name_' . $this->getCurrentLang() . ' as name'));
                    }])->select('id', 'type_id', DB::raw('name_' . app()->getLocale() . ' as name'));
                }]);

        $doctor = $doctor->select('id', 'provider_id', 'specification_id', 'nationality_id', 'nickname_id', 'photo', 'gender', 'rate', 'price', 'status',
            DB::raw('name_' . $this->getCurrentLang() . ' as name'),
            DB::raw('information_' . $this->getCurrentLang() . ' as information')
        );

        $doctors = $doctor->/*where('doctors.status', 1)->*/ paginate(10);

        if (count($doctors) > 0) {
            foreach ($doctors as $key => $doctor) {
                $doctor->time = "";
                $days = $doctor->times;
                $match = $this->getMatchedDateToDays($days);

                if (!$match || $match['date'] == null) {
                    $doctor->time = new \stdClass();;
                    continue;
                }
                $doctorTimesCount = $this->getDoctorTimePeriodsInDay($match['day'], $match['day']['day_code'], true);
                $availableTime = $this->getFirstAvailableTime($doctor->id, $doctorTimesCount, $days, $match['date'], $match['index']);
                $doctor->time = $availableTime;
                $doctor->branch_name = Doctor::find($doctor->id)->provider->{'name_' . app()->getLocale()};
            }
            $total_count = $doctors->total();
            $doctors->getCollection()->each(function ($doctor) {
                $doctor->makeVisible(['name_en', 'name_ar', 'information_en', 'information_ar']);
                return $doctor;
            });


            $doctors = json_decode($doctors->toJson());
            $doctorsJson = new \stdClass();
            $doctorsJson->current_page = $doctors->current_page;
            $doctorsJson->total_pages = $doctors->last_page;
            $doctorsJson->total_count = $total_count;
            $doctorsJson->data = $doctors->data;
            return $this->returnData('doctors', $doctorsJson);
        }

        return $this->returnError('E001', trans('messages.No doctors founded'));
    }

    private function getOfferForVisitors(Request $request)
    {
        $orderBy = 'id';
        $conditions = [];
        $category = OfferCategory::find($request->category_id);
        $categoryId = $category->id;
        if (isset($request->filter_id) && !empty($request->filter_id)) {
            $filter = Filter::find($request->filter_id);
            if (!$filter)
                return $this->returnError('D000', trans('messages.filter not found'));
            if (in_array($filter->operation, [0, 1, 2])) { //if filter operation is < or > or =
                if ($filter->operation == 0) {   //less than
                    array_push($conditions, ['price_after_discount', '<=', (int)$filter->price]);
                } elseif ($filter->operation == 1) {  //greater than
                    array_push($conditions, ['price_after_discount', '>=', (int)$filter->price]);
                } else {
                    array_push($conditions, ['price_after_discount', '=', (int)$filter->price]);
                }
                $orderBy = 'price';
            } elseif (in_array($filter->operation, [3, 4, 5])) {  //3-> most paid 4-> most visited 5-> latest
                if ($filter->operation == 3) {
                    $orderBy = 'uses';
                } elseif ($filter->operation == 4) {
                    $orderBy = 'visits';
                } else
                    $orderBy = 'id';
            } else {
                $orderBy = 'id';
            }
        }

        // if 0 get all offer in this subCategory
        if ($request->subCategory_id != 0) {
            $subCategory = OfferCategory::whereNotNull('parent_id')
                ->where('id', $request->subCategory_id)
                ->first();
            if (!$subCategory)
                return $this->returnError('E001', trans('messages.offer subcategory not found'));
            $subCategoryId = $subCategory->id;

            if (isset($request->featured) && $request->featured == 1) {
                if (!empty($conditions) && count($conditions) > 0) {
                    $offers = Offer::where(function ($qq) {
                        $qq->where('general', 1);

                    })
                        ->where($conditions)
                        ->featured()
                        ->active()
                        ->valid()
                        ->with(['provider' => function ($q) {
                            $q->select('id', 'rate', 'logo', 'type_id',
                                DB::raw('name_' . $this->getCurrentLang() . ' as name'));
                            $q->with(['type' => function ($q) {
                                $q->select('id', DB::raw('name_' . app()->getLocale() . ' as name'));
                            }]);
                        }])
                        ->whereHas('categories', function ($q) use ($subCategoryId, $categoryId) {
                            $q->where('offers_categories.id', $subCategoryId);
                            $q->where('parent_id', $categoryId);
                        })
                        ->orderBy($orderBy, 'DESC')
                        ->limit(10)
                        ->selection()
                        ->get();
                } else {
                    $offers = Offer::where(function ($qq) {
                        $qq->where('general', 1);
                    })
                        ->featured()
                        ->active()
                        ->valid()
                        ->with(['provider' => function ($q) {
                            $q->select('id', 'rate', 'logo', 'type_id',
                                DB::raw('name_' . $this->getCurrentLang() . ' as name'));
                            $q->with(['type' => function ($q) {
                                $q->select('id', DB::raw('name_' . app()->getLocale() . ' as name'));
                            }]);
                        }])
                        ->whereHas('categories', function ($q) use ($subCategoryId, $categoryId) {
                            $q->where('offers_categories.id', $subCategoryId);
                            $q->where('parent_id', $categoryId);
                        })
                        ->orderBy($orderBy, 'DESC')
                        ->limit(10)
                        ->selection()
                        ->get();
                }

            } else { // get non featured offers
                //return $this->returnError('E001', trans('messages.There featured  must be 1 or not present '));
                if (!empty($conditions) && count($conditions) > 0) {

                    $offers = Offer::where(function ($qq) {
                        $qq->where('general', 1);

                    })->active()
                        ->valid()
                        ->with(['provider' => function ($q) {
                            $q->select('id', 'rate', 'logo', 'type_id',
                                DB::raw('name_' . $this->getCurrentLang() . ' as name'));
                            $q->with(['type' => function ($q) {
                                $q->select('id', DB::raw('name_' . app()->getLocale() . ' as name'));
                            }]);
                        }])
                        ->whereHas('categories', function ($q) use ($subCategoryId, $categoryId) {
                            $q->where('offers_categories.id', $subCategoryId);
                            $q->where('parent_id', $categoryId);
                        })
                        ->where($conditions)
                        ->selection()
                        ->orderBy($orderBy, 'DESC')
                        ->paginate(10);
                } else {
                    $offers = Offer::where(function ($qq) {
                        $qq->where('general', 1);
                    })
                        ->active()
                        ->valid()
                        ->with(['provider' => function ($q) {
                            $q->select('id', 'rate', 'logo', 'type_id',
                                DB::raw('name_' . $this->getCurrentLang() . ' as name'));
                            $q->with(['type' => function ($q) {
                                $q->select('id', DB::raw('name_' . app()->getLocale() . ' as name'));
                            }]);
                        }])
                        ->whereHas('categories', function ($q) use ($subCategoryId, $categoryId) {
                            $q->where('offers_categories.id', $subCategoryId);
                            $q->where('parent_id', $categoryId);
                        })
                        ->selection()
                        ->orderBy($orderBy, 'DESC')
                        ->paginate(10);
                }
            }
        } else {   // if the is no subcategory id  (i.e subCategory_id =0 )will get all offers  in main category
            if (isset($request->featured) && $request->featured == 1) {
                if (!empty($conditions) && count($conditions) > 0) {
                    $offers = Offer::where(function ($qq) {
                        $qq->where('general', 1);
                    })->featured()
                        ->active()
                        ->valid()
                        ->with(['provider' => function ($q) {
                            $q->select('id', 'rate', 'logo', 'type_id',
                                DB::raw('name_' . $this->getCurrentLang() . ' as name'));
                            $q->with(['type' => function ($q) {
                                $q->select('id', DB::raw('name_' . app()->getLocale() . ' as name'));
                            }]);
                        },])
                        ->where($conditions)
                        ->whereHas('categories', function ($q) use ($categoryId) {
                            $q->where('parent_id', $categoryId);
                        })
                        ->orderBy($orderBy, 'DESC')
                        ->selection()
                        ->limit(25)
                        ->get();
                } else {

                    $offers = Offer::where(function ($qq) {
                        $qq->where('general', 1);
                    })->featured()
                        ->active()
                        ->valid()
                        ->with(['provider' => function ($q) {
                            $q->select('id', 'rate', 'logo', 'type_id',
                                DB::raw('name_' . $this->getCurrentLang() . ' as name'));
                            $q->with(['type' => function ($q) {
                                $q->select('id', DB::raw('name_' . app()->getLocale() . ' as name'));
                            }]);
                        },])
                        ->whereHas('categories', function ($q) use ($categoryId) {
                            $q->where('parent_id', $categoryId);
                        })
                        ->orderBy($orderBy, 'DESC')
                        ->selection()
                        ->limit(25)
                        ->get();
                }
            } else {
                if (!empty($conditions) && count($conditions) > 0) {
                    // PromoCode::find(6) -> currentId();
                    $offers = Offer::where(function ($qq) {
                        $qq->where('general', 1);
                    })->where($conditions)
                        ->whereHas('categories', function ($q) use ($categoryId) {
                            $q->where('parent_id', $categoryId);

                        })
                        ->active()
                        ->valid()
                        ->with(['provider' => function ($q) {
                            $q->select('id', 'rate', 'logo', 'type_id',
                                DB::raw('name_' . $this->getCurrentLang() . ' as name'));
                            $q->with(['type' => function ($q) {
                                $q->select('id', DB::raw('name_' . app()->getLocale() . ' as name'));
                            }]);
                        }])
                        ->selection()
                        ->orderBy($orderBy, 'DESC')
                        ->paginate(10);
                } else {
                    $offers = Offer::where(function ($qq) {
                        $qq->where('general', 1);
                    })->active()
                        ->valid()
                        ->whereHas('categories', function ($q) use ($categoryId) {
                            $q->where('parent_id', $categoryId);

                        })
                        ->with(['provider' => function ($q) {
                            $q->select('id', 'rate', 'logo', 'type_id',
                                DB::raw('name_' . $this->getCurrentLang() . ' as name'));
                            $q->with(['type' => function ($q) {
                                $q->select('id', DB::raw('name_' . app()->getLocale() . ' as name'));
                            }]);
                        }])
                        ->selection()
                        ->orderBy($orderBy, 'DESC')
                        ->paginate(10);
                }
            }
        }

        if (isset($request->featured) && $request->featured == 1) {
            //if coupon allowed only for some users
            /*        if (isset($offers) && $offers->count() > 0) {
                        foreach ($offers as $index => $offer) {
                            if (!empty($offer->users) && count($offer->users) > 0) {
                                $authUserExistsForThisOffer = in_array($user->id, array_column($offer->users->toArray(), 'id'));
                                if (!$authUserExistsForThisOffer) {
                                    unset($offers[$index]);
                                }
                            }
                        }
                    }*/
            return $this->returnData('featured_offers', $offers);
        }

        $selectedValue = 0;
        if (count($offers->toArray()) > 0) {

            foreach ($offers as $index => $offer) {
                unset($offer->provider_id);
                unset($offer->available_count);
                unset($offer->status);
                unset($offer->created_at);
                unset($offer->updated_at);
                unset($offer->specification_id);
                /* if ($offers->coupons_type_id == 1) {
                     $offers->price = "0";
                 }*/
                if ($offer->coupons_type_id == 2) {
                    $offer->discount = "0";
                    $offer->code = "0";
                }
            }

            $offers = json_decode($offers->toJson());
            $total_count = $offers->total;
            $offersJson = new \stdClass();
            $offersJson->current_page = $offers->current_page;
            $offersJson->total_pages = $offers->last_page;
            $offersJson->total_count = $total_count;
            $offersJson->data = $offers->data;
            return $this->returnData('offers', $offersJson);
        }

        return $this->returnError('E001', trans('messages.No offers founded'));

    }


    public
    function banners(Request $request)
    {
        try {
            $banners = $this->getBanners();
            if (count($banners->toArray()) > 0) {
                $banners->each(function ($banner) {
                    if ($banner->type == 'App\Models\PromoCodeCategory') {
                        $type = 'category';
                    } elseif ($banner->type == 'App\Models\PromoCode') {
                        $type = 'offer';
                    } else {
                        $type = 'none';
                    }
                    $banner->type = $type;
                    return $banner;
                });
            }
            return $this->returnData('banners', $banners);
        } catch (\Exception $ex) {
            return $this->returnError($ex->getCode(), $ex->getMessage());
        }
    }


    public
    function bannersV2(Request $request)
    {
        try {
            $banners = $this->getBannersV2();
            if (count($banners->toArray()) > 0) {
                $banners->each(function ($banner) {
                    $banner->type = $banner->type === 'App\Models\OfferCategory' ? 'category' : 'offer';
                    return $banner;
                });
            }
            return $this->returnData('banners', $banners);
        } catch (\Exception $ex) {
            return $this->returnError($ex->getCode(), $ex->getMessage());
        }
    }

    public function showV2(Request $request, $allow_code = false, $proCode = 0)
    {
        try {

            $validator = Validator::make($request->all(), [
                "id" => "required|exists:offers,id",
            ]);

            if ($validator->fails()) {
                $code = $this->returnCodeAccordingToInput($validator);
                return $this->returnValidationError($code, $validator);
            }

            $user = $this->auth('user-api');
            $_offer = Offer::active()->with(['provider' => function ($q) {
                $q->select('id', 'rate', 'logo', 'type_id',
                    DB::raw('name_' . $this->getCurrentLang() . ' as name'));
                $q->with(['type' => function ($q) {
                    $q->select('id', DB::raw('name_' . app()->getLocale() . ' as name'));
                }]);
            }, 'offerBranches' => function ($qq) {
                $qq->select('*')->with(['branch' => function ($qqq) {
                    $qqq->select('id', DB::raw('name_' . $this->getCurrentLang() . ' as name'));
                }]);
            }
            ])->selection();

            $offer = $_offer->find($request->id);
            if ($offer != null) unset($offer->provider_id);
            unset($offer->available_count);
            unset($offer->status);
            unset($offer->created_at);
            unset($offer->updated_at);
            /* if ($offer->coupons_type_id == 1) {
                 $offer->price = "0";
             }*/
            if ($offer->coupons_type_id == 2) {
                $offer->discount = "0";
                if (!$allow_code)
                    $offer->code = "0";
                else
                    $offer->code = $proCode;
            }

            $offer->content = $offer->contents()->pluck('content_' . app()->getLocale() . ' as content');
            event(new \App\Events\OfferWasVisitedV2($offer));   // fire increase countrt  event

            return $this->returnData('offer', json_decode(json_encode($offer)));

            // return $this->returnError('E001', trans('messages.No offer with this id'));

        } catch (\Exception $ex) {
            return $this->returnError($ex->getCode(), $ex->getMessage());
        }
    }


    //get availbles  slot times by day
    public function getAvailableTimes(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                "offer_id" => "required|exists:offers,id",
                "branch_id" => "required|exists:providers,id",
                "date" => "required|date_format:Y-m-d",
            ]);


            if ($validator->fails()) {
                $code = $this->returnCodeAccordingToInput($validator);
                return $this->returnValidationError($code, $validator);
            }
            $date = $request->date;
            $offer = Offer::find($request->offer_id);
            $branch = Provider::whereNotNull('provider_id')->find($request->branch_id);
            if (!$branch)
                return $this->returnError('E001', trans('messages.No doctor with this id'));

            $d = new DateTime($date);
            $day_name = strtolower($d->format('l'));
            $days_name = ['saturday' => 'sat', 'sunday' => 'sun', 'monday' => 'mon', 'tuesday' => 'tue', 'wednesday' => 'wed', 'thursday' => 'thu', 'friday' => 'fri'];
            $dayCode = $days_name[$day_name];


            if ($offer != null) {
                $day = $offer->times()->where('branch_id', $branch->id)->where('day_code', $dayCode)->first();
                $doctorTimesCount = $this->getOfferTimePeriodsInDay($day, $dayCode, true);
                $times = [];
                $date = $request->date;
                $offerTimesCount = $this->getOfferTimePeriodsInDay($day, $dayCode, true);
                $availableTime = $this->getAllOfferAvailableTime($offer->id, $request->branch_id, $offerTimesCount, [$day], $date);
                if (count((array)$availableTime))
                    array_push($times, $availableTime);

                $res = [];
                if (count($times)) {
                    foreach ($times as $key => $time) {
                        $res = array_merge_recursive($time, $res);
                    }
                }
                $offer->times = $res;

                ########### Start To Get Doctor Times After The Current Time ############
                $collection = collect($offer->times);
                $filtered = $collection->filter(function ($value, $key) {

                    if (date('Y-m-d') == $value['date'])
                        return $value['from_time'] > date('H:i:s');
                    else
                        return $value;
                });
                $offer->times = array_values($filtered->all());
                ########### End To Get Doctor Times After The Current Time ############

                return $this->returnData('offer_available_times', $offer->times);
            }

            return $this->returnError('E001', trans('messages.No Offer with this id'));
        } catch (\Exception $ex) {
            return $this->returnError($ex->getCode(), $ex->getMessage());
        }
    }


    public function reserveTime(Request $request)
    {

        try {
            $rules = [
                "offer_id" => "required|numeric|exists:offers,id",
                "payment_method_id" => "required|numeric|exists:payment_methods,id",
                "day_date" => "required|date",
                "agreement" => "required|boolean",
                "from_time" => "required",
                "to_time" => "required",
                //"address" => "required",
                "provider_id" => "required|exists:providers,id",
                //"type" => "required|in:1,2",
            ];

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                $code = $this->returnCodeAccordingToInput($validator);
                return $this->returnValidationError($code, $validator);
            }

            $user = $this->auth('user-api');
            if ($user == null)
                return $this->returnError('E001', trans('messages.There is no user with this id'));

            $validation = $this->validateFields(['offer' => ['provider_id' => $request->provider_id, 'offer_id' => $request->offer_id, 'day_date' => $request->day_date, 'from_time' => $request->from_time, 'to_time' => $request->to_time]]);

            if (!$request->agreement)
                return $this->returnError('E006', trans('messages.Agreement is required'));


            if (strtotime($request->day_date) < strtotime(Carbon::now()->format('Y-m-d')) ||
                ($request->day_date == Carbon::now()->format('Y-m-d') && strtotime($request->to_time) < strtotime(Carbon::now()->format('H:i:s'))))
                return $this->returnError('D000', trans("messages.You can't reserve to a time passed"));

            if ($validation->offer_found)
                return $this->returnError('E001', trans('messages.This time is not available'));

            $branch = Provider::find($request->provider_id);
            $provider = Provider::find($branch->provider_id);

            $offer = Offer::with(['times' => function ($q) use ($branch) {
                $q->where('branch_id', $branch->id);
            }])->find($request->offer_id);

            $reservationDayName = date('l', strtotime($request->day_date));

            $rightDay = false;
            $timeOrder = 1;
            $last = false;
            $times = [];
            $day_code = substr(strtolower($reservationDayName), 0, 3);

            foreach ($offer->times as $time) {
                if ($time['day_code'] == $day_code) {
                    $times = $this->getOfferTimePeriodsInDay($time, substr(strtolower($reservationDayName), 0, 3), false);
                    foreach ($times as $key => $time) {
                        if ($time['from_time'] == Carbon::parse($request->from_time)->format('H:i')
                            && $time['to_time'] == Carbon::parse($request->to_time)->format('H:i')) {
                            $rightDay = true;
                            $timeOrder = $key + 1;
                            if (count($times) == ($key + 1))
                                $last = true;
                            break;
                        }
                    }
                }
            }

            if (!$rightDay)
                return $this->returnError('E001', trans('messages.This day is not in offer days'));

            $reservationCode = $this->getRandomStringL(8);

            $reservation = Reservation::create([
                "reservation_no" => $reservationCode,
                "user_id" => $user->id,
                "offer_id" => $request->offer_id,
                "day_date" => date('Y-m-d', strtotime($request->day_date)),
                "from_time" => date('H:i:s', strtotime($request->from_time)),
                "to_time" => date('H:i:s', strtotime($request->to_time)),
                "payment_method_id" => $request->payment_method_id,
                "paid" => 0,
                "provider_id" => $request->provider_id,
                'order' => $timeOrder,
                'price' => $request->price
            ]);


            if (!$provider)
                return $this->returnError('E001', 'لا يوجد مقدم خدمه  للحجز');

            $provider->makeVisible(['application_percentage_bill', 'application_percentage']);

            if ($last) {
                ReservedTime::create([
                    'offer_id' => $offer->id,
                    'day_date' => date('Y-m-d', strtotime($request->day_date)),
                    'branch_id' => $branch->id
                ]);
            }

            $reserve = new \stdClass();
            $reserve->reservation_no = $reservation->reservation_no;
            $reserve->day_date = date('l', strtotime($request->day_date));
            $reserve->code = date('l', strtotime($request->day_date));
            $reserve->day_date = date('l', strtotime($request->day_date));
            $reserve->reservation_date = date('Y-m-d', strtotime($request->day_date));
            $reserve->price = $reservation->price;
            $reserve->payment_method = $reservation->paymentMethod()->select('id', DB::raw('name_' . $this->getCurrentLang() . ' as name'))->first();
            $reserve->from_time = $reservation->from_time;
            $reserve->to_time = $reservation->to_time;
            $reserve->provider = Provider::providerSelection()->find($branch->provider_id);
            $reserve->branch = Provider::providerSelection()->find($request->provider_id);

            if ($request->filled('latitude') && $request->filled('longitude')) {
                $reserve->branch->distance = (string)$this->getDistance($reserve->branch->latitude, $reserve->branch->longitude, $request->latitude, $request->longitude, 'K');
            }
            $reserve->offer = Offer::select('id', 'title_' . app()->getLocale() . ' as title')->find($request->offer_id);

            DB::commit();
        } catch (\Exception $ex) {
            DB::rollback();

            return $ex;
            //return $this->returnError('D000', __('messages.sorry please try again later'));
        }

        try {
            //push notification
            (new \App\Http\Controllers\NotificationController(['title' => __('messages.New Reservation'), 'body' => __('messages.You have new reservation')]))->sendProvider($branch); // branch
            (new \App\Http\Controllers\NotificationController(['title' => __('messages.New Reservation'), 'body' => __('messages.You have new reservation')]))->sendProvider($provider); // main  provider

            $providerName = $provider->{'name_' . app()->getLocale()};
            $smsMessage = __('messages.dear_service_provider') . ' ( ' . $providerName . ' ) ' . __('messages.provider_have_new_reservation_from_MedicalCall');
            $this->sendSMS($provider->mobile, $smsMessage);  //sms for main provider

            (new \App\Http\Controllers\NotificationController(['title' => __('messages.New Reservation'), 'body' => __('messages.You have new reservation')]))->sendProviderWeb($branch, null, 'new_reservation'); //branch
            (new \App\Http\Controllers\NotificationController(['title' => __('messages.New Reservation'), 'body' => __('messages.You have new reservation')]))->sendProviderWeb($provider, null, 'new_reservation');  //main provider

            $notification = GeneralNotification::create([
                'title_ar' => 'حجز  عرض جديد لدي مقدم الخدمة ' . ' ' . $providerName,
                'title_en' => 'New offer reservation for ' . ' ' . $providerName,
                'content_ar' => 'هناك حجز عرض جديد برقم ' . ' ' . $reservation->reservation_no . ' ' . ' ( ' . $providerName . ' )',
                'content_en' => __('messages.You have new reservation no:') . ' ' . $reservation->reservation_no . ' ' . ' ( ' . $providerName . ' )',
                'notificationable_type' => 'App\Models\Provider',
                'notificationable_id' => $reservation->provider_id,
                'data_id' => $reservation->id,
                'type' => 1 //new reservation
            ]);
            $notify = [
                'provider_name' => $providerName,
                'reservation_no' => $reservation->reservation_no,
                'reservation_id' => $reservation->id,
                'content' => __('messages.You have new reservation no:') . ' ' . $reservation->reservation_no . ' ' . ' ( ' . $providerName . ' )',
                'photo' => $reserve->provider->logo,
                'notification_id' => $notification->id
            ];
            //fire pusher  notification for admin  stop pusher for now
            try {
                //event(new \App\Events\NewReservation($notify));   // fire pusher new reservation  event notification*/
            } catch (\Exception $ex) {
                return $ex;
            }
        } catch (\Exception $ex) {
            return $ex;
        }
        return $this->returnData('reservation', $reserve);
    }


    public function getAllOffersReservations(Request $request)
    {
        try {
            $user = $this->auth('user-api');
            if (!$user)
                return $this->returnError('D000', __('messages.user not found'));

            $reservations = $this->getUserOffersReservations($user->id);

            if (isset($reservations) && $reservations->count() > 0) {
                foreach ($reservations as $key => $reservation) {
                    $main_provider = Provider::where('id', $reservation->provider['provider_id'])->select('id', \Illuminate\Support\Facades\DB::raw('name_' . app()->getLocale() . ' as name'))->first();
                    $reservation->main_provider = $main_provider ? $main_provider : '';
                    $reservation->makeHidden(['for_me', 'branch_no', 'is_reported', 'admin_value_from_reservation_price_Tax', 'reservation_total', 'comment_report']);
                }
            }

            if (count($reservations->toArray()) > 0) {
                $total_count = $reservations->total();
                $reservations = json_decode($reservations->toJson());
                $reservationsJson = new \stdClass();
                $reservationsJson->current_page = $reservations->current_page;
                $reservationsJson->total_pages = $reservations->last_page;
                $reservationsJson->per_page = PAGINATION_COUNT;
                $reservationsJson->total_count = $total_count;
                $reservationsJson->data = $reservations->data;
                return $this->returnData('reservations', $reservationsJson);
            }
            return $this->returnError('E001', trans('messages.No reservations founded'));
        } catch (\Exception $ex) {
            return $this->returnError($ex->getCode(), $ex->getMessage());
        }
    }

    protected
    function getRandomStringL($length)
    {
        $characters = '0123456789';
        $string = '';
        for ($i = 0; $i < $length; $i++) {
            $string .= $characters[mt_rand(0, strlen($characters) - 1)];
        }
        $chkCode = Reservation::where('reservation_no', $string)->first();
        if ($chkCode) {
            $this->getRandomString(8);
        }
        return $string;
    }


    public function getTimesAsArrayOfDayCodes(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                "offer_id" => "required|numeric|exists:offers,id",
                "branch_id" => "required|numeric|exists:providers,id",
            ]);
            if ($validator->fails()) {
                $code = $this->returnCodeAccordingToInput($validator);
                return $this->returnValidationError($code, $validator);
            }

            $times = OfferBranchTime::where('offer_id', $request->offer_id)
                ->where('branch_id', $request->branch_id)
                ->pluck('day_code');

            if (count($times) > 0)
                return $this->returnData('timesCodes', $times);
            else
                return $this->returnData('timesCodes', $times, trans('messages.no times for offers'));

        } catch (\Exception $ex) {
            return $this->returnError($ex->getCode(), $ex->getMessage());
        }
    }


    public function getTimes(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                "offer_id" => "required|numeric|exists:offers,id",
                "branch_id" => "required|numeric|exists:providers,id",
            ]);
            if ($validator->fails()) {
                $code = $this->returnCodeAccordingToInput($validator);
                return $this->returnValidationError($code, $validator);
            }
            $doctor = Doctor::with('times')->find($request->id);
            if ($doctor == null)
                return $this->returnError('E001', trans('messages.No doctor with this id'));

            $times = $this->getDoctorTimesPeroids($doctor->times);
            if (count($times) > 0) {
                return $this->returnData('times', $times);
            }
            return $this->returnError('E001', trans('messages.No doctor times found'));
        } catch (\Exception $ex) {
            return $this->returnError($ex->getCode(), $ex->getMessage());
        }
    }

}
