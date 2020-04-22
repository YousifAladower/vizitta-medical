<?php

namespace App\Http\Controllers;

use App\Models\AdminWebToken;
use App\Models\Reservation;
use App\Models\User;
use App\Models\Provider;
use Carbon\Carbon;

class NotificationController extends Controller
{
    protected $device_token;
    protected $title;
    protected $body;
    protected const API_ACCESS_KEY_PROVIDER = 'AAAAaPb2xeE:APA91bETQPIQYimxzzR9zIs-NbVcrz-AKKT1iDFqoMtJJ-Kpy57OoUvqPzo99Fcxf8D7YfWCtMOUByPESe9m74uUAvPX6dV6EDUSHQQO7qakkk_ZfZdo_Q2Zge7Ilajl9TY5U_lNfjMy';
    protected const API_ACCESS_KEY_USER = 'AAAAc1Y3kCA:APA91bGJNpIGQQo2LeIbiGzcNZQyITAbyR9zHQXkFKGifEj9cLdvaOy3n8YV8_vLzMPRrY0kUJm2634OUjApRf7PTJ4aj8PHRfZKgyy_05-0JxI7S_5AQ6IMEB9QF_HfG2fybbehpxQL';
    protected const API_ACCESS_KEY_ADMIN = 'AAAAc1Y3kCA:APA91bGJNpIGQQo2LeIbiGzcNZQyITAbyR9zHQXkFKGifEj9cLdvaOy3n8YV8_vLzMPRrY0kUJm2634OUjApRf7PTJ4aj8PHRfZKgyy_05-0JxI7S_5AQ6IMEB9QF_HfG2fybbehpxQL';
    private const fcmUrl = 'https://fcm.googleapis.com/fcm/send';

    //

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct(Array $data)
    {
        $this->title = $data['title'];
        $this->body = $data['body'];
    }

    public function sendUser(User $notify, $bill = false, $reservation_id = null)
    {
        // $data['device_token'] = $User->device_token;
        $notification = [
            'title' => $this->title,
            'body' => $this->body,
            "click_action" => "action"
        ];
        if ($bill && $reservation_id != null) {
            $extraNotificationData = [
                'upload_bill' => '1',
                'reservation_id' => $reservation_id
            ];

            // $extraNotificationData = ["message" => $notification,"moredata" =>'New Data'];
            $fcmNotification = [
                //'registration_ids' => $tokenList, //multple token array
                'to' => $notify->device_token,//'/topics/alldevices',// $User->device_token, //single token
                'notification' => $notification,
                'data' => $extraNotificationData
            ];

        } else {
            // $extraNotificationData = ["message" => $notification,"moredata" =>'New Data'];
            //
            $fcmNotification = [
                //'registration_ids' => $tokenList, //multple token array
                'to' => $notify->device_token,//'/topics/alldevices',// $User->device_token, //single token
                'notification' => $notification,
            ];
        }

        return $this->sendFCM($fcmNotification, 'user');
    }

    public function sendProvider(Provider $notify)
    {
        $notification = [
            'title' => $this->title,
            'body' => $this->body,
        ];

        // $extraNotificationData = ["message" => $notification,"moredata" =>'New Data'];
        $fcmNotification = [
            //'registration_ids' => $tokenList, //multple token array
            'to' => $notify->device_token,//'/topics/alldevices',// $User->device_token, //single token
            'notification' => $notification,
        ];
        //if ($notify->device_token)
        return $this->sendFCM($fcmNotification, 'provider');
        /*  if ($notify->web_token != null)
              $this->sendProviderWebBrowser($notify);*/
    }

    public function sendProviderWeb(Provider $notify, $reservation_no = null, $type = 'new_reservation')
    {
        if ($reservation_no != null) {
            $notification = [
                'title' => $this->title,
                'body' => $this->body,
                "reservation_no" => $reservation_no,
                "type" => $type
            ];
        } else {

            $notification = [
                'title' => $this->title,
                'body' => $this->body,
                "type" => $type
            ];
        }

        $notificationData = new \stdClass();
        $notificationData->notification = $notification;
        // $extraNotificationData = ["message" => $notification,"moredata" =>'New Data'];
        $fcmNotification = [
            //'registration_ids' => $tokenList, //multple token array
            'to' => $notify->web_token,//'/topics/alldevices',// $User->device_token, //single token
            'data' => $notificationData

        ];
        return $this->sendFCM($fcmNotification, 'provider');

    }


    public function sendAdminWeb($type)
    {
        $notification = [
            'title' => $this->title,
            'body' => $this->body,
            "type" => $type
        ];
        $tokenList = AdminWebToken::pluck('token')->toArray();
        $notificationData = new \stdClass();
        $notificationData->notification = $notification;
        // $extraNotificationData = ["message" => $notification,"moredata" =>'New Data'];
        $fcmNotification = [
            'registration_ids' => $tokenList,
            //'to' => $notify->web_token,//'/topics/alldevices',// $User->device_token, //single token
            'data' => $notificationData
        ];
        return $this->sendFCM($fcmNotification, 'admin');
    }


    /*  // weBrowser Push Format
      public function sendProviderWebBrowser(Provider $notify)
      {

          $notification = [
              'title' => $this->title,
              'body' => $this->body,
          ];

          $notificationData = new \stdClass();
          $notificationData->notification = $notification;
          // $extraNotificationData = ["message" => $notification,"moredata" =>'New Data'];
          $fcmNotification = [
              //'registration_ids' => $tokenList, //multple token array
              'to' => $notify->web_token,//'/topics/alldevices',// $User->device_token, //single token
              'data' => $notificationData

          ];


          $this->sendFCM($fcmNotification, 'provider');
      }*/


    private
    function sendFCM($fcmNotification, $type = 'user')
    {
        if ($type == 'provider') {
            $key = self::API_ACCESS_KEY_PROVIDER;
        } elseif ($type == 'admin') {
            $key = self::API_ACCESS_KEY_ADMIN;
        } else {
            $key = self::API_ACCESS_KEY_USER;
        }

        $headers = [
            'Authorization: key=' . $key,
            'Content-Type: application/json'
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, self::fcmUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fcmNotification));
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }

    public
    function setData(Array $data)
    {
        $this->device_token = $data['device_token'];
        $this->title = $data['title'];
        $this->body = $data['body'];
    }
}
