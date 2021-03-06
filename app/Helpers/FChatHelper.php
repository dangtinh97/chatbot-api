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

    public static function sendMessageText($to, $content,$messagingType="UPDATE")
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
            "recipient" => [
                "id" => $to
            ],
            "message" => $send
        ];
        $body['messaging_type'] = $messagingType;
        $tag = ["POST_PURCHASE_UPDATE","ACCOUNT_UPDATE","CONFIRMED_EVENT_UPDATE"];
        if($messagingType==="MESSAGE_TAG") $body['tag'] = $tag[array_rand($tag)];

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
            dd($response);
        }
        curl_close($curl);
        return $response;
    }

    public static function replaceBadWord(string $str)
    {

        return str_replace([
            'csvn', 'dit', '?????t', 'l???n', 'bu???i', 'gi???t', '??cm', 'dcm', 'sex', 'vcl', 'dcm', '??cm', '?????ng c???ng s???n', 'dang cong san',
            'giet',
        ], "***", $str);
    }

    public static function buttonConnect():array
    {
        return [
            'type' => "postback",
            'title' => "T??m ng?????i l???",
            'payload' => 'CONNECT'
        ];
    }

    public static function buttonDisconnect():array
    {
        return [
            'type' => "postback",
            'title' => "Ng???t k???t n???i",
            'payload' => 'DISCONNECT'
        ];
    }

    public static function gameOanTuTi():array
    {
        return [
            'type' => "postback",
            'title' => "Ch??i o???n t?? t??",
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
