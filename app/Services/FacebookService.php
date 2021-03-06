<?php

namespace App\Services;

use App\Helpers\FChatHelper;
use App\Http\Responses\ApiResponse;
use App\Http\Responses\ResponseError;
use App\Http\Responses\ResponseSuccess;
use App\Models\Connect;
use App\Models\User;
use App\Repositories\BlockRepository;
use App\Repositories\ConnectRepository;
use App\Repositories\ImageRepository;
use App\Repositories\LogRepository;
use App\Repositories\OanTuTiRepository;
use App\Repositories\UserRepository;
use Illuminate\Support\Str;

class FacebookService
{
    protected $logRepository;

    protected $userRepository;

    protected $connectRepository;

    protected $oanTuTiRepository;

    protected $blockRepository;

    protected $imageRepository;

    /** @var User $user */
    protected $user;

    public $fbUid;

    public $blockConfig;

    public function __construct(
        LogRepository $logRepository,
        UserRepository $userRepository,
        ConnectRepository $connectRepository,
        OanTuTiRepository $oanTuTiRepository,
        BlockRepository $blockRepository,
        ImageRepository $imageRepository
    ) {
        $this->logRepository = $logRepository;
        $this->userRepository = $userRepository;
        $this->connectRepository = $connectRepository;
        $this->oanTuTiRepository = $oanTuTiRepository;
        $this->blockRepository = $blockRepository;
        $this->imageRepository = $imageRepository;
        $this->blockConfig();
    }

    public function webHook($data): ApiResponse
    {
        $this->logRepository->create([
            'data' => json_encode($data)
        ]);
        if ($data['object'] !== "page") {
            return new ResponseError();
        }
        $messaging = $data['entry'][0]['messaging'][0];
        $senderId = $messaging['sender']['id'];
        $this->fbUid = $senderId;

        /** @var User $user */
        $user = $this->userRepository->findOrCreateUser($senderId);
        if ($user->created_at->__toString() === $user->updated_at->__toString()) {
            $this->createConnect($user->id);
            $user = $this->userRepository->findOrCreateUser($senderId);
        }
        $this->user = $user;
        $this->setRoom($user);

        if (isset($messaging['postback'])) {
            return $this->messagePostback($user, $messaging['postback']);
        }

        $text = trim($messaging['message']['text'] ?? "");
        $attachment = $messaging['message']['attachments'][0] ?? [];
        if (count($attachment) !== 0 && empty($text)) {
            return $this->sendAttachment($attachment, $user);
        }

        $resultText = $this->detectMessage($text);
        if ($text === "#help" || $text === "#menu") {
            return $this->menu();
        }
        if ($text === "#girlw") {
            return $this->viewGirlImage();
        }

        if ($resultText === "connect") {
            return $this->connect($user);
        }
        if ($resultText === "disconnect") {
            return $this->disconnect($user);
        }
        if ($resultText === "text") {
            return $this->sendMessageText($user, $text);
        }

        return new ResponseSuccess();
    }

    public function messagePostback($user, $postback): ApiResponse
    {
        if ($postback['title'] === "Get Started" || $postback == "B???t ?????u") {
            return $this->defaultAns($user);
        }
        if ($postback['payload'] === "CONNECT") {
            return $this->connect($user);
        }
        if ($postback['payload'] === "DISCONNECT") {
            return $this->disconnect($user);
        }
        if ($postback['payload'] === "MENU") {
            return $this->menu();
        }

        return $this->defaultAns($user);
    }

    private function setRoom($user): ?string
    {
        $roomUUID = null;
        if ($user->connect->status !== Connect::STATUS_BUSY) {
            return $roomUUID;
        }
        if (!is_null($user->connect->room_uuid)) {
            return $user->connect->room_uuid;
        }
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

    public function sendMessageText($user, $message): ApiResponse
    {
        $message = str_replace(['https://', 'http://'], '', $message);
        $connect = $user->connect;
        if (is_null($connect) || $connect->status !== Connect::STATUS_BUSY) {
            $this->defaultAns($user);

            return (new ResponseSuccess([], 200, "G???i tin nh???n m???c ?????nh."));
        }
        $fromUser = $connect;
        $userConnected = $this->connectRepository->findOne([
            'to_user_id' => $connect->from_user_id
        ]);
        $timeLast = $userConnected->user->updated_at->timestamp;
        $hour = (time() - $timeLast) / (60 * 60 * 24);
        $messageType = "";
        $sendTo = $userConnected->user->fb_uid;
        if($hour > 0.95){
            $messageType = "MESSAGE_TAG";
            $sendTo = $user->fb_uid;
            $message = "H??? th???ng kh??ng th??? g???i tin nh???n cho ng?????i l???(ng?????i l??? ???? kh??ng truy c???p trong 24h). R???t xin l???i v?? s??? b???t ti???n n??y, b???n c?? th??? k???t n???i v???i ng?????i kh??c ????? ti???p t???c";
        }else{
            $messageType = "RESPONSE";
        }
        FChatHelper::sendMessageText($sendTo, FChatHelper::replaceBadWord($message), $messageType);

        return ((new ResponseSuccess([], 200, "SEND MESSAGE SUCCESS")));
    }

    public function defaultAns($user): ApiResponse
    {
        $text = "Ch??o b???n, ????y l?? tin nh???n m???c ?????nh\n- g?? #ketnoi ????? t??m ng?????i l???\n- g?? #ketthuc ????? ng???t k???t n???i v???i ai ????.\nCh??ng t??? ??ang ph??t tri???n, r???t mong ???????c c??c b???n ???ng h???.
    \nCh??ng t??? c?? g?? n??o\n- 13/05/2022 Ch??ng t??? ???? c???p nh???t l???i page, c?? th??? g???i tin nh???n v??n b???n. g???i h??nh ???nh.\n- 21/05/2022 Ch??ng t??? ???? update c?? th??? g???i tin nh???n li??n t???c ngo??i 24h";

        $message = $this->buttonMenu("Ch??o m???ng b???n ???? ?????n v???i Haui Chatbot");

        $send = FChatHelper::sendMessageText($user->fb_uid, $message);

        return new ResponseSuccess();
    }

    /**
     * @param $text
     * @param $statusConnect
     *
     * @return array[]
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function buttonMenu($text, $statusConnect = "NONE"): array
    {
        $data = ($this->blockConfig->where('name', 'DEFAULT')->first()->data);
        $data = json_decode($data, true);
        $buttons = [];

        if ($statusConnect === Connect::STATUS_FREE) {
            $buttons[] = FChatHelper::buttonConnect();
        }
        if ($statusConnect === Connect::STATUS_FIND || $statusConnect === Connect::STATUS_BUSY) {
            $buttons[] = FChatHelper::buttonDisconnect();
        }
        /** @var array $buttonConfig */
        $buttonConfig = &$data['attachment']['payload']['elements'][0]['buttons'];
        if (count($buttons) === 0) {
            $buttons[] = FChatHelper::buttonConnect();
        }
        $buttonConfig[] = $buttons[0];
        $buttonConfig[0]['url'] = $buttonConfig[0]['url']."?fb_uid=".$this->fbUid;
        $data['attachment']['payload']['elements'][0]['title'] = $text;
        $attachments = [
            "attachment" => [
                'type' => 'template',
                'payload' => [
                    'template_type' => 'generic',
                    'elements' => [
                        [
                            "title" => $text,
                            'image_url' => "https://storage.googleapis.com/datinee-dev/dino-2.png",//$item['image'],
                            'subtitle' => "Kh???ng Long Ch???y B???",//,"QC: ".$item['name']."\nGi??:".$item['price'],
                            'default_action' => [
                                'type' => "web_url",
                                'url' => "https://tool.nguoila.online/game/dino?fb_uid=".$this->fbUid
                                    ."?utm_source=haui_chatbot",//"https://shopee.vn/shop5nangtien",
                                "messenger_extensions" => false,
                                'webview_height_ratio' => "tall",
                            ],
                            'buttons' => $buttons
                        ]
                    ]
                ]
            ]
        ];

        return $data;
    }

    public function connect($user): ApiResponse
    {
        $uuid = Str::uuid();
        $connect = $user->connect;
        $status = $connect->status;
        if ($connect->status === Connect::STATUS_BUSY) {
            FChatHelper::sendMessageText($user->fb_uid,
                $this->buttonMenu("B???n ??ang trong cu???c tr?? chuy???n v???i ai ????.", Connect::STATUS_BUSY));

            return new ResponseSuccess([], 200, "B???n ??ang trong cu???c tr?? chuy???n v???i ai ????.");
        }

        if ($connect->status === Connect::STATUS_FIND) {
            FChatHelper::sendMessageText($user->fb_uid,
                $this->buttonMenu("B???n ??ang trong h??ng ?????i.", Connect::STATUS_FIND));

            return new ResponseSuccess([], 200, "B???n ??ang trong h??ng ?????i.");
        }
        $connect->update([
            'status' => Connect::STATUS_FIND,
        ]);
        $userFind = $this->connectRepository->userFindConnectAndSetBusy($user->id, $uuid);
        if (is_null($userFind)) {
            FChatHelper::sendMessageText($user->fb_uid,
                $this->buttonMenu("Ch??ng t??? ??ang t??m ng?????i ph?? h???p v???i b???n!", Connect::STATUS_FIND));

            return new ResponseSuccess([], 200, "Ch??ng t??? ??ang t??m ng?????i ph?? h???p v???i b???n!");
        }
        $connect->update([
            'status' => Connect::STATUS_BUSY,
            'to_user_id' => $userFind->from_user_id,
            'room_uuid' => $uuid
        ]);
        FChatHelper::sendMessageText($user->fb_uid,
            $this->buttonMenu("B???n ???? ???????c k???t n???i v???i ng?????i l???!", Connect::STATUS_BUSY));
        FChatHelper::sendMessageText($userFind->user->fb_uid,
            $this->buttonMenu("B???n ???? ???????c k???t n???i v???i ng?????i l???!", Connect::STATUS_BUSY));

        return new ResponseSuccess([], 200, "B???n ???? ???????c k???t n???i v???i ng?????i l???!");
    }

    public function disconnect($user): ApiResponse
    {
        $userIdMe = $user->id;
        $connect = $user->connect;
        if ($connect->status === Connect::STATUS_FREE) {
            FChatHelper::sendMessageText($user->fb_uid,
                $this->buttonMenu("B???n ch??a k???t n???i v???i ai!", Connect::STATUS_FREE));

            return new ResponseSuccess([], 200, "B???n ch??a k???t n???i v???i ai!");
        }
        $status = $connect->status;
        $connect->update([
            'to_user_id' => null,
            'status' => Connect::STATUS_FREE
        ]);
        if ($status === Connect::STATUS_FIND) {
            FChatHelper::sendMessageText($user->fb_uid,
                $this->buttonMenu("B???n ???? r???i kh???i h??ng ?????i!", Connect::STATUS_FREE));

            return new ResponseSuccess([], 200, "B???n ???? r???i kh???i h??ng ?????i!");
        }

        $userConnected = $this->connectRepository->findOne([
            'to_user_id' => $userIdMe
        ]);
        if (!is_null($userConnected)) {
            FChatHelper::sendMessageText($userConnected->user->fb_uid,
                $this->buttonMenu("Ng?????i l??? ???? ng???t k???t n???i v???i b???n.", Connect::STATUS_FREE));
            $userConnected->update([
                'status' => Connect::STATUS_FREE,
                'to_user_id' => null
            ]);
        }

        FChatHelper::sendMessageText($user->fb_uid,
            $this->buttonMenu("B???n ???? ng???t k???t n???i v???i ng?????i l???!", Connect::STATUS_FREE));

        return new ResponseSuccess([], 200, "B???n ???? ng???t k???t n???i v???i ng?????i l???!");
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

    public function detectMessage(string $text): string
    {
        if (empty($text)) {
            return "";
        }
        $connect = ['#ketnoi', '#batdau', '#timnguoila', '#connect'];
        $disconnect = ['#pipi', '#ketthuc', '#tambiet', '#disconnect', '#ngatketnoi'];
        if (in_array($text, $connect)) {
            return "connect";
        }
        if (in_array($text, $disconnect)) {
            return "disconnect";
        }

        return "text";
    }

    public function sendAttachment(array $attachment, $user): ApiResponse
    {
        $attachment['payload']['is_reusable'] = true;
        $connect = $user->connect;
        if (is_null($connect) || $connect->status !== Connect::STATUS_BUSY) {
            $this->defaultAns($user);

            return (new ResponseSuccess([], 200, "G???i tin nh???n m???c ?????nh"));
        }
        $userConnected = $this->connectRepository->findOne([
            'to_user_id' => $connect->from_user_id
        ]);
        FChatHelper::sendMessageText($userConnected->user->fb_uid, [
            'attachment' => $attachment
        ]);

        return ((new ResponseSuccess([], 200, "SEND MESSAGE ATTACHMENT SUCCESS")));
    }

    /**
     * @param $fbUid
     *
     * @return \App\Http\Responses\ApiResponse
     */
    private function menu(): ApiResponse
    {
        $fbUid = $this->user->fb_uid;

        //ph???n n??y g???n qu???ng c??o ngay ban ?????u.........
        $attachments = [
            "attachment" => [
                'type' => 'template',
                'payload' => [
                    'template_type' => 'generic',
                    'elements' => [
                        [
                            "title" => "B??i b??i tarot",
                            'image_url' => "https://thientue.vn/images/tarot/tarot-card.png",//$item['image'],
                            'subtitle' => "B??i tarot h??m nay",//,"QC: ".$item['name']."\nGi??:".$item['price'],
                            'default_action' => [
                                'type' => "web_url",
                                'url' => "https://tool.nguoila.online/user-boi-bai-tarot/".$this->fbUid,
                                //"https://shopee.vn/shop5nangtien",
                                "messenger_extensions" => false,
                                'webview_height_ratio' => "tall",
                            ],
                            'buttons' => [
                                FChatHelper::buttonUrl("https://tool.nguoila.online/game/flappy-bird?fb_uid="
                                    .$this->fbUid, "Flappy bird"),
                                FChatHelper::buttonUrl("https://tool.nguoila.online/game/dino?fb_uid=".$this->fbUid
                                    ."?utm_source=haui_chatbot", "Kh???ng long ch???y b???"),
                                FChatHelper::buttonUrl("https://tool.nguoila.online/caro-online?fb_uid=".$this->fbUid
                                    ."?utm_source=haui_chatbot", "Ch??i Caro online"),
                            ]
                        ]
                    ]
                ]
            ]
        ];
        FChatHelper::sendMessageText($fbUid, $attachments);

        return new ResponseSuccess();
    }

    /**
     * @return mixed
     */
    private function blockConfig()
    {
        $all = $this->blockRepository->all();
        $this->blockConfig = $all;

        return $all;
    }

    private function viewGirlImage(): ApiResponse
    {
        $images = $this->imageRepository->randomImage('GIRL', 1);
        $url = $images[0]['url'];
        $message = [
            'attachment' => [
                'type' => 'image',
                'payload' => [
                    'url' => $url,
                    'is_reusable' => true
                ]
            ]
        ];
        FChatHelper::sendMessageText($this->fbUid, $message);
        $this->help();

        return new ResponseSuccess([], 200, "Xem ???nh girls");
    }

    /**
     * @return \App\Http\Responses\ApiResponse
     */
    private function help(): ApiResponse
    {
        FChatHelper::sendMessageText($this->fbUid, "
        Danh s??ch c??c l???nh:
        - #ketnoi: B???t ?????u t??m b???n chat
        - #ketthuc: K???t th??c chat
        - #girlw: Xem ???nh g??i xinh ch???n l???c 
        ");

        return new ResponseSuccess();
    }
}
