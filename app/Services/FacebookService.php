<?php

namespace App\Services;

use App\Helpers\FChatHelper;
use App\Http\Responses\ApiResponse;
use App\Http\Responses\ResponseError;
use App\Http\Responses\ResponseSuccess;
use App\Models\Connect;
use App\Repositories\ConnectRepository;
use App\Repositories\LogRepository;
use App\Repositories\UserRepository;

class FacebookService
{
    protected $logRepository;
    protected $userRepository;
    protected $connectRepository;
    public function __construct(LogRepository $logRepository,UserRepository $userRepository,ConnectRepository $connectRepository)
    {
        $this->logRepository = $logRepository;
        $this->userRepository = $userRepository;
        $this->connectRepository = $connectRepository;
    }

    public function webHook($data):ApiResponse
    {
        $this->logRepository->create([
            'data' => json_encode($data)
        ]);
        if($data['object']!=="page") return new ResponseError();
        $messaging = $data['entry'][0]['messaging'][0];
        $senderId = $messaging['sender']['id'];
        $user = $this->userRepository->findOrCreateUser($senderId);
        if ($user->created_at->__toString() === $user->updated_at->__toString()){
            $this->createConnect($user->id);
            $user = $this->userRepository->findOrCreateUser($senderId);
        }

        if(isset($messaging['postback'])) return $this->messagePostback($user,$messaging['postback']);

        $attachment = $messaging['message']['attachments'][0] ?? [];
        if(count($attachment)!==0 && empty($text)) return $this->sendAttachment($attachment,$user);
        $text = trim($messaging['message']['text']?? "");
        $resultText = $this->detectMessage($text);
        if($text==="#help") return $this->defaultAns($user);
        if($resultText==="connect") return $this->connect($user);
        if($resultText==="disconnect") return $this->disconnect($user);
        if($resultText==="text") return $this->sendMessageText($user,$text);

        return new ResponseSuccess();
    }

    public function messagePostback($user,$postback)
    {
        if($postback['title']==="Get Started") return $this->defaultAns($user);
        if($postback['payload']==="CONNECT") return $this->connect($user);
        if($postback['payload']==="DISCONNECT") return $this->disconnect($user);
    }

    public function sendMessageText($user,$message):ApiResponse
    {
        $connect = $user->connect;
        if(is_null($connect) || $connect->status!==Connect::STATUS_BUSY){
            $this->defaultAns($user);
            return (new ResponseSuccess([],200,"Gửi tin nhắn mặc định."));
        }
        $userConnected = $this->connectRepository->findOne([
            'to_user_id' => $connect->from_user_id
        ]);
        FChatHelper::sendMessageText($userConnected->user->fb_uid, FChatHelper::replaceBadWord($message));
        return ((new ResponseSuccess([],200,"SEND MESSAGE SUCCESS")));
    }

    public function defaultAns($user)
    {
        $text = "Chào bạn, đây là tin nhắn mặc định\n- gõ #ketnoi để tìm người lạ\n- gõ #ketthuc để ngắt kết nối với ai đó.\nChúng tớ đang phát triển, rất mong được các bạn ủng hộ.
    \nChúng tớ có gì nào\n- 13/05/2022 Chúng tớ đã cập nhật lại page, có thể gửi tin nhắn văn bản. gửi hình ảnh.\nChú ý, Hiện giờ chúng tớ vẫn chưa thể gửi tin nhắn quá 24h, vì vậy các bạn cần nhắn tin trong vòng 24h";

        $message = $this->buttonMenu($text);

        FChatHelper::sendMessageText($user->fb_uid,$message);
        return new ResponseSuccess();
    }

    public function buttonMenu($text):array
    {
        return [
            "attachment" => [
                'type' => 'template',
                'payload' => [
                    'template_type' => 'button',
                    'text' => "📣 ".$text,
                    'buttons' => [
                        FChatHelper::buttonConnect(),
                        FChatHelper::buttonDisconnect(),
                    ]
                ]
            ]
        ];
    }

    public function connect($user):ApiResponse
    {
        $connect = $user->connect;
        $status = $connect->status;
        if($connect->status===Connect::STATUS_BUSY){
            FChatHelper::sendMessageText($user->fb_uid,$this->buttonMenu("Bạn đang trong cuộc trò chuyện với ai đó."));
            return new ResponseSuccess([],200,"Bạn đang trong cuộc trò chuyện với ai đó.");
        }

        if($connect->status===Connect::STATUS_FIND){
            FChatHelper::sendMessageText($user->fb_uid,$this->buttonMenu("Bạn đang trong hàng đợi."));
            return new ResponseSuccess([],200,"Bạn đang trong hàng đợi.");
        }
        $connect->update([
            'status' => Connect::STATUS_FIND,
        ]);
        $userFind = $this->connectRepository->userFindConnectAndSetBusy($user->id);
        if (is_null($userFind)){
            FChatHelper::sendMessageText($user->fb_uid,$this->buttonMenu("Chúng tớ đang tìm người phù hợp với bạn!"));
            return new ResponseSuccess([],200,"Chúng tớ đang tìm người phù hợp với bạn!");
        }
        $connect->update([
            'status' => Connect::STATUS_BUSY,
            'to_user_id' => $userFind->from_user_id
        ]);
        FChatHelper::sendMessageText($user->fb_uid,$this->buttonMenu("Bạn đã được kết nối với người lạ!"));
        FChatHelper::sendMessageText($userFind->user->fb_uid,$this->buttonMenu("Bạn đã được kết nối với người lạ!"));
        return new ResponseSuccess([],200,"Bạn đã được kết nối với người lạ!");
    }

    public function disconnect($user): ApiResponse
    {
        $userIdMe = $user->id;
        $connect = $user->connect;
        if ($connect->status === Connect::STATUS_FREE){
             FChatHelper::sendMessageText($user->fb_uid,$this->buttonMenu("Bạn chưa kết nối với ai!"));
             return new ResponseSuccess([],200,"Bạn chưa kết nối với ai!");
        }
        $status = $connect->status;
        $connect->update([
            'to_user_id' => null,
            'status' => Connect::STATUS_FREE
        ]);
        if ($status === Connect::STATUS_FIND){
            FChatHelper::sendMessageText($user->fb_uid,$this->buttonMenu("Bạn đã rời khỏi hàng đợi!"));
            return new ResponseSuccess([],200,"Bạn đã rời khỏi hàng đợi!");
        }

        $userConnected = $this->connectRepository->findOne([
            'to_user_id' => $userIdMe
        ]);
        if(!is_null($userConnected)){
            FChatHelper::sendMessageText($userConnected->user->fb_uid, $this->buttonMenu("Người lạ đã ngắt kết nối với bạn."));
            $userConnected->update([
                'status' => Connect::STATUS_FREE,
                'to_user_id' => null
            ]);
        }

        FChatHelper::sendMessageText($user->fb_uid,$this->buttonMenu("Bạn đã ngắt kết nối với người lạ!"));
        return new ResponseSuccess([],200,"Bạn đã ngắt kết nối với người lạ!");
    }

    public function createConnect(int $userId)
    {
        return $this->connectRepository->create([
            'from_user_id' => $userId,
            'status' => "FREE",
            'message_last' => '',
            'send_last' => date('Y/m/d', time())
        ]);
    }

    public function detectMessage(string $text):string
    {
        if(empty($text)) return "";
        $connect = ['#ketnoi','#batdau','#timnguoila','#connect'];
        $disconnect = ['#pipi','#ketthuc','#tambiet','#disconnect'];
        if(in_array($text,$connect)) return "connect";
        if(in_array($text,$disconnect)) return "disconnect";
        return "text";
    }

    public function sendAttachment(array $attachment,$user):ApiResponse
    {
        $attachment['payload']['is_reusable']  =true;
        $connect = $user->connect;
        if(is_null($connect) || $connect->status!==Connect::STATUS_BUSY){
            $this->defaultAns($user);
            return (new ResponseSuccess([],200,"Gửi tin nhắn mặc định"));
        }
        $userConnected = $this->connectRepository->findOne([
            'to_user_id' => $connect->from_user_id
        ]);
        FChatHelper::sendMessageText($userConnected->user->fb_uid, [
            'attachment' => $attachment
        ]);
        return ((new ResponseSuccess([],200,"SEND MESSAGE ATTACHMENT SUCCESS")));
    }

}
