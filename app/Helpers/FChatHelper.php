<?php

namespace App\Helpers;

use App\Mail\ErrorSendMessagePage;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

class FChatHelper
{
    public static function responseText($texts): array
    {
        if (gettype($texts) === "string") $texts = [$texts];
        return array_map(function ($text) {
            return [
                "text" => $text
            ];
        }, $texts);
    }

    public static function sendMessage($to, $content)
    {
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://fchat.vn/api/v1/message/send',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode([
                'user_id' => $to,
                'message' => [
                    [
                        'text' => $content
                    ]
                ]
            ]),
            CURLOPT_HTTPHEADER => array(
                'Token: ' . config('chatbot.f_chat.token_bot'),
                'Content-Type: application/json',
            ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);
    }

    public static function sendMessageText($to, $content)
    {
        $send = [];
        if(gettype($content)!=="array"){
            $send = [
                "text" => (string)$content
            ];
        }else{
            $send = $content;
        }
        $tokenPage = base64_decode(config('chatbot.facebook.token_page'));
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://graph.facebook.com/v13.0/me/messages?access_token=' . $tokenPage,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode([
                "messaging_type" => "UPDATE",
                "recipient" => [
                    "id" => $to
                ],
                "message" => $send
            ]),
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
            ),
        ));
        $response = curl_exec($curl);
        $arr = json_decode($response,true);
        if(isset($arr['error']))
        {
//            Mail::to(User::query()->where([
//                'fb_uid' => '1343954529053153'
//            ])->first())->send(new ErrorSendMessagePage($response));
        }
        curl_close($curl);
        return $response;
    }

    public static function replaceBadWord(string $str)
    {

        return str_replace([
            'csvn', 'dit', 'địt', 'lồn', 'buồi', 'giết', 'đcm', 'dcm', 'sex', 'vcl', 'dcm', 'đcm', 'đảng cộng sản', 'dang cong san',
            'giet',
        ], "***", $str);
    }
}
