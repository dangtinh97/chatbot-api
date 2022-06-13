<?php

namespace App\Services;

use App\Helpers\FChatHelper;
use App\Http\Responses\ApiResponse;
use App\Http\Responses\ResponseError;
use App\Http\Responses\ResponseSuccess;
use App\Models\Connect;
use App\Models\User;
use App\Repositories\ConnectRepository;
use App\Repositories\LogRepository;
use App\Repositories\OanTuTiRepository;
use App\Repositories\UserRepository;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FacebookService
{
    protected $logRepository;
    protected $userRepository;
    protected $connectRepository;
    protected $oanTuTiRepository;
    /** @var User $user */
    private $user;
    public $fbUid;
    public function __construct(LogRepository $logRepository,UserRepository $userRepository,ConnectRepository $connectRepository,OanTuTiRepository $oanTuTiRepository)
    {
        $this->logRepository = $logRepository;
        $this->userRepository = $userRepository;
        $this->connectRepository = $connectRepository;
        $this->oanTuTiRepository = $oanTuTiRepository;
    }

    public function webHook($data):ApiResponse
    {
        $this->logRepository->create([
            'data' => json_encode($data)
        ]);
        if($data['object']!=="page") return new ResponseError();
        $messaging = $data['entry'][0]['messaging'][0];
        $senderId = $messaging['sender']['id'];
        $this->fbUid = $senderId;
        $user = $this->userRepository->findOrCreateUser($senderId);
        if ($user->created_at->__toString() === $user->updated_at->__toString()){
            $this->createConnect($user->id);
            $user = $this->userRepository->findOrCreateUser($senderId);
        }
        $this->user = $user;
        $this->setRoom($user);

//        FChatHelper::sendMessageText($senderId, $this->buttonMenu("dev"));
//        dd("a");
//        return $this->defaultAns($user);
        if(isset($messaging['postback'])) return $this->messagePostback($user,$messaging['postback']);
        if(isset($messaging['message']['quick_reply'])) return $this->messageQuickReply($user,$messaging['message']['quick_reply']);
        $text = trim($messaging['message']['text']?? "");
        $attachment = $messaging['message']['attachments'][0] ?? [];
        if(count($attachment)!==0 && empty($text)) return $this->sendAttachment($attachment,$user);

        $resultText = $this->detectMessage($text);
        if($text==="#help") return $this->defaultAns($user);
        if($resultText==="connect") return $this->connect($user);
        if($resultText==="disconnect") return $this->disconnect($user);
        if($resultText==="text") return $this->sendMessageText($user,$text);

        return new ResponseSuccess();
    }

    public function messageQuickReply(User $user,$quickReply):ApiResponse
    {
        $payload = $quickReply['payload'];
        if(strstr($payload,"OAN_TU_TI")) return $this->oanTuTi($user,str_replace("OAN_TU_TI_","",$payload));
        dd($quickReply);
    }

    public function messagePostback($user,$postback):ApiResponse
    {
        if($postback['title']==="Get Started" || $postback['title']==="Get started") return $this->defaultAns($user);
        if($postback['payload']==="CONNECT") return $this->connect($user);
        if($postback['payload']==="DISCONNECT") return $this->disconnect($user);
        if($postback['payload']==="OAN_TU_TI") return $this->oanTuTi($user);
        return $this->defaultAns($user);
    }

    private function oanTuTi($user,string $reply=""):ApiResponse
    {
        $quickReplies = [
            [
                'title' => 'búa',
                'payload' => "OAN_TU_TI_BUA"
            ],
            [
                'title' => 'kéo',
                'payload' => "OAN_TU_TI_KEO"
            ],
            [
                'title' => 'bao',
                'payload' => "OAN_TU_TI_BAO"
            ],
            [
                'title' => 'từ chối',
                'payload' => "OAN_TU_TI_DO_NOT_PLAY"
            ]
        ];

        $userId = $user->id;
        if($user->connect->status!==Connect::STATUS_BUSY) {
            FChatHelper::sendMessageText($user->fb_uid,$this->buttonMenu("Bạn không thể chơi oẳn tù tì vì bạn chưa kết nối với ai!",$user->connect->status));
            return new ResponseSuccess();
        }
        $oanTuTi = $user->connect->oantuti;
        $connect = $user->connect;
        $userConnect = $connect->userConnect;
        $text = "";
        $sendTo = "";
        if(is_null($oanTuTi)){
            // user 1 mời chơi
            $this->oanTuTiRepository->create([
               'room_uuid' => $connect->room_uuid,
               'status' => "SEND_REQUEST_".$user->id
            ]);
            $text = "Người lạ muốn cùng bạn chơi oẳn tù tì...";
            $sendTo = $userConnect->fb_uid;
        }
        $status = $oanTuTi->status;
//        dd($userConnect,$connect,$user,$oanTuTi);
        if(empty($text) && empty($reply) && $oanTuTi->status==="SEND_REQUEST_".$user->id){
            // user 1 mời lần thứ 2
            $text = "Người lạ muốn cùng bạn chơi oẳn tù tì.";
            $sendTo = $userConnect->fb_uid;
        }

        if(empty($text) && empty($reply) && $oanTuTi->status==="SEND_REQUEST_".$userConnect->id){
            // user 2 vào 1 cuộc đã có
            $text = "Người lạ đang chờ bạn chơi oẳn tù tì.";
            $sendTo = $user->fb_uid;
        }

        if(!empty($sendTo)){
            $message = FChatHelper::quickReplies($text,$quickReplies);
            FChatHelper::sendMessageText($sendTo,$message);
            return new ResponseSuccess();
        }

        if($reply==="DO_NOT_PLAY")
        {
            $textMe = "";
            $textFriend = "";
            $oanTuTi->delete();
            FChatHelper::sendMessageText($userConnect->fb_uid,"Người lạ đã từ chối chơi oẳn tù tì cùng bạn!");
            FChatHelper::sendMessageText($user->fb_uid,"Bạn đã rời khỏi trò chơi oẳn tù tì!");
            return new ResponseSuccess();
        }

        if(!in_array($reply,["BUA","KEO","BAO"])) return new ResponseError();

        $isStatusSendRequest = is_numeric(strpos($oanTuTi->status, "SEND_REQUEST_"));

        if($isStatusSendRequest && $status===$userConnect->id)
        {
            $oanTuTi->update([
                'status' => 'PLAYER_'.$userId,
                'player_1' => $reply
            ]);

            FChatHelper::sendMessageText($userConnect->fb_id,[

            ]);
        }


        return new ResponseSuccess();
    }

    private function setRoom($user): ?string
    {
        $roomUUID = null;
        if($user->connect->status!==Connect::STATUS_BUSY) return $roomUUID;
        if(!is_null($user->connect->room_uuid)) return $user->connect->room_uuid;
        $roomUUID = Str::uuid();
        $connectTo = $user->connect->to_user_id;
        $user->connect->update([
            'room_uuid' => $roomUUID
        ]);
        $this->connectRepository->findOne([
            'from_user_id' => $connectTo
        ])->update([
            'room_uuid' => $roomUUID
        ]);
        return $roomUUID;

    }

    public function sendMessageText($user,$message):ApiResponse
    {
        $message = str_replace(['https://','http://'],'',$message);
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

    public function defaultAns($user):ApiResponse
    {
        $text = "Chào bạn, đây là tin nhắn mặc định\n- gõ #ketnoi để tìm người lạ\n- gõ #ketthuc để ngắt kết nối với ai đó.\nChúng tớ đang phát triển, rất mong được các bạn ủng hộ.
    \nChúng tớ có gì nào\n- 13/05/2022 Chúng tớ đã cập nhật lại page, có thể gửi tin nhắn văn bản. gửi hình ảnh.\n- 21/05/2022 Chúng tớ đã update có thể gửi tin nhắn liên tục ngoài 24h";

        $message = $this->buttonMenu($text);

        $send = FChatHelper::sendMessageText($user->fb_uid,$message);
        return new ResponseSuccess();
    }

    public function buttonMenu($text,$statusConnect="NONE"):array
    {
        /**
        "payload": {
        "template_type":"generic",
        "elements":[
        {
        "title":"<TITLE_TEXT>",
        "image_url":"<IMAGE_URL_TO_DISPLAY>",
        "subtitle":"<SUBTITLE_TEXT>",
        "default_action": {
        "type": "web_url",
        "url": "<DEFAULT_URL_TO_OPEN>",
        "messenger_extensions": <TRUE | FALSE>,
        "webview_height_ratio": "<COMPACT | TALL | FULL>"
        },
        "buttons":[<BUTTON_OBJECT>, ...]
        },
        ...
        ]
        }
         */


        $file = Storage::disk('public')->get('qc/5_nt_1.json');
        $data = json_decode($file,true);
        $item = $data[array_rand($data)];

        $buttons = [
            FChatHelper::buttonConnect(),
            FChatHelper::buttonDisconnect(),
            FChatHelper::buttonUrl("https://tool.nguoila.online/user-boi-bai-tarot/".$this->fbUid,"Bói bài tarot")
        ];
        $attachments =  [
            "attachment" => [
                'type' => 'template',
                'payload' => [
                    'template_type' => 'generic',
                    'elements' => [
                        [
                            "title" => $text,
                            'image_url' => "https://thientue.vn/images/tarot/tarot-card.png",//$item['image'],
                            'subtitle' => "Bói tarot hôm nay",//,"QC: ".$item['name']."\nGiá:".$item['price'],
                            'default_action' => [
                                'type' => "web_url",
                                'url' => "https://tool.nguoila.online/user-boi-bai-tarot/".$this->fbUid,//"https://shopee.vn/shop5nangtien",
                                "messenger_extensions" => false,
                                'webview_height_ratio' => "tall",
                            ],
                            'buttons' => $buttons
                        ]
                    ]
                ]
            ]
        ];



//        $attachments['attachment']["payload"]["buttons"] = $buttons;
//        dd($attachments);
        return $attachments;
    }

    public function buttonMenuV1($text,$statusConnect="NONE"):array
    {
        $buttons = [
            FChatHelper::buttonConnect(),
            FChatHelper::buttonDisconnect(),
        ];
        $attachments =  [
            "attachment" => [
                'type' => 'template',
                'payload' => [
                    'template_type' => 'button',
                    'text' => "📣 ".$text,
                ]
            ]
        ];

        if($statusConnect==="CONNECTED" || $statusConnect==="BUSY")
        {
            $buttons[]=FChatHelper::gameOanTuTi();
        }

        $attachments['attachment']["payload"]["buttons"] = $buttons;

        return $attachments;
    }

    public function connect($user):ApiResponse
    {
        $uuid = Str::uuid();
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
        $userFind = $this->connectRepository->userFindConnectAndSetBusy($user->id,$uuid);
        if (is_null($userFind)){
            FChatHelper::sendMessageText($user->fb_uid,$this->buttonMenu("Chúng tớ đang tìm người phù hợp với bạn!"));
            return new ResponseSuccess([],200,"Chúng tớ đang tìm người phù hợp với bạn!");
        }
        $connect->update([
            'status' => Connect::STATUS_BUSY,
            'to_user_id' => $userFind->from_user_id,
            'room_uuid' => $uuid
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
