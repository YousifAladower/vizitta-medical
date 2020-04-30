<?php

namespace App\Http\Controllers;

use App\Http\Resources\ProviderServicesResource;
use App\Mail\AcceptReservationMail;
use App\Mail\RejectReservationMail;
use App\Models\CommentReport;
use App\Models\Doctor;
use App\Models\PromoCode;
use App\Models\Reason;
use App\Models\Service;
use App\Models\Ticket;
use App\Models\Replay;
use App\Models\Provider;
use App\Models\ReportingType;
use App\Models\Reservation;
use App\Models\User;
use App\Models\Token;
use App\Models\UserAttachment;
use App\Models\UserRecord;
use App\Traits\DoctorTrait;
use App\Traits\GlobalTrait;
use App\Traits\OdooTrait;
use App\Traits\SMSTrait;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Traits\ProviderTrait;
use App\Mail\NewReplyMessageMail;
use App\Mail\NewUserMessageMail;
use Illuminate\Support\Facades\DB;
use Validator;
use Auth;
use Mail;
use JWTAuth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use DateTime;

class GlobalProviderController extends Controller
{
    use ProviderTrait, GlobalTrait, DoctorTrait, SMSTrait, OdooTrait;

    public function __construct(Request $request)
    {

    }

    public function getProviderServices(Request $request)
    {
        try {
            $type = $request->type;
            $provider = Provider::whereNull('provider_id')->find($request->provider_id);
            if (!$provider)
                return $this->returnError('E001', trans('messages.No provider with this id'));

            $services = Service::with('types')->whereHas('provider', function ($q) use ($provider) {
                $q->where('id', $provider->id);
            })->orderBy('id', 'DESC')
                ->paginate(PAGINATION_COUNT);

//            $result = new ProviderServicesResource($services);

            if (count($services->toArray()) > 0) {

                $total_count = $services->total();
                $services = json_decode($services->toJson());
                $servicesJson = new \stdClass();
                $servicesJson->current_page = $services->current_page;
                $servicesJson->total_pages = $services->last_page;
                $servicesJson->total_count = $total_count;
                $servicesJson->per_page = PAGINATION_COUNT;
                $servicesJson->data = $services->data;
                return $this->returnData('services', $servicesJson);
            }

            return $this->returnData('services', $services);
        } catch (\Exception $ex) {
            return $this->returnError($ex->getCode(), $ex->getMessage());
        }
    }

}
