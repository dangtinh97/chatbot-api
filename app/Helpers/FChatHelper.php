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

        $body = [
//                "messaging_type" => "UPDATE",
            "recipient" => [
                "id" => $to
            ],
            "message" => $send
        ];
        dump($body);
        $body['messaging_type'] = 'MESSAGE_TAG';
        $body['tag'] = "CONFIRMED_EVENT_UPDATE";
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
            CURLOPT_POSTFIELDS => json_encode($body),
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
            ),
        ));
        $response = curl_exec($curl);
        $arr = json_decode($response,true);
        if(isset($arr['error']))
        {

//            dd($response);
//            dd($response);
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

    public static function buttonConnect():array
    {
        return [
            'type' => "postback",
            'title' => "Tìm người lạ",
            'payload' => 'CONNECT'
        ];
    }

    public static function buttonDisconnect():array
    {
        return [
            'type' => "postback",
            'title' => "Ngắt kết nối",
            'payload' => 'DISCONNECT'
        ];
    }

    public static function gameOanTuTi():array
    {
        return [
            'type' => "postback",
            'title' => "Chơi oẳn tù tì",
            'payload' => 'OAN_TU_TI'
        ];
    }

    //quick_replies
    public static function quickReplies(string $text,array $replies):array
    {
        return [
            "text" => $text,
            "quick_replies" => array_map(function ($reply){
                return [
                    "content_type" => "text",
                    "title" => $reply['title'],
                    'payload' => $reply['payload']
                ];
            },$replies)
        ];
    }


    public static function buttonUrl(string $url,string $title):array
    {
        return [
            'type'=>'web_url',
            'url'=> $url,
            'title' => $title
        ];
    }

    /**
     * @return string[]
     */
    public static function buttonMenu():array
    {
        return [
            'type' => "postback",
            'title' => "Menu",
            'payload' => 'MENU'
        ];
    }
}
