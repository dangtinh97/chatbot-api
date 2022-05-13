<?php

namespace App\Services;

use App\Helpers\FChatHelper;
use App\Http\Responses\ApiResponse;
use App\Http\Responses\ResponseError;
use App\Http\Responses\ResponseSuccess;
use App\Models\Connect;
use App\Repositories\ConnectRepository;
use App\Repositories\UserRepository;
use Illuminate\Support\Facades\Hash;

class UserService
{
    protected $userRepository;
    protected $connectRepository;

    public function __construct(UserRepository $userRepository, ConnectRepository $connectRepository)
    {
        $this->userRepository = $userRepository;
        $this->connectRepository = $connectRepository;
    }

    public function create(array $params): ApiResponse
    {
        $params['password'] = Hash::make("chatbot");
        if ($params['gender'] === "Anh" || $params['gender']==="male") $params['gender'] = "MALE";
        if ($params['gender'] === "Chị" || $params['gender']==="female") $params['gender'] = "FEMALE";
        if (empty($params['gender']) || ($params['gender'] == "MALE" && $params['gender'] !== "FEMALE")) $params['gender'] = "MALE";
        $user = $this->userRepository->updateOrCreate([
            'fb_uid' => $params['fb_uid']
        ], $params);
        if ($user->created_at->__toString() === $user->updated_at->__toString()) $this->createConnect($user->id);
        return new ResponseSuccess($user->toArray());
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

    public function connect(string $fbUid): array
    {
        $user = $this->userRepository->findOne([
            'fb_uid' => $fbUid
        ]);
        if (is_null($user)) return $this->newRegister();
        $connect = $user->connect;
        if ($connect->status === Connect::STATUS_BUSY) return FChatHelper::responseText("Bạn đang trong cuộc trò chuyện với 1 ai đó.\nVui lòng ngắt kết nối trước!");
        if ($connect->status === Connect::STATUS_FIND) return FChatHelper::responseText("Bạn đang trong hàng đợi!");
        $connect->update([
            'status' => Connect::STATUS_FIND
        ]);
        $userFind = $this->connectRepository->userFindConnectAndSetBusy($user->id);
        if (is_null($userFind)) return FChatHelper::responseText("Chúng tớ đang tìm người phù hợp với bạn!");
        $connect->update([
            'status' => Connect::STATUS_BUSY,
            'to_user_id' => $userFind->from_user_id
        ]);
        FChatHelper::sendMessage($userFind->user->fb_uid,"Bạn đã được kết nối với người lạ!");
        return FChatHelper::responseText("Bạn đã được kết nối với người lạ!");
    }

    public function disconnect($fbUid): array
    {
        $user = $this->userRepository->findOne([
            'fb_uid' => $fbUid
        ]);
        $userIdMe = $user->id;
        if (is_null($user)) return $this->newRegister();
        $connect = $user->connect;
        if ($connect->status === Connect::STATUS_FREE) return FChatHelper::responseText("Bạn chưa kết nối với ai!");
        $status = $connect->status;
        $connect->update([
            'to_user_id' => null,
            'status' => Connect::STATUS_FREE
        ]);
        if ($status === Connect::STATUS_FIND) return FChatHelper::responseText("Bạn đã rời khỏi hàng đợi!");

        $userConnected = $this->connectRepository->findOne([
            'to_user_id' => $userIdMe
        ]);
        if(!is_null($userConnected)){
            FChatHelper::sendMessage($userConnected->user->fb_uid, "Người lạ đã ngắt kết nối với bạn.");
            $userConnected->update([
                'status' => Connect::STATUS_FREE,
                'to_user_id' => null
            ]);
        }
        return FChatHelper::responseText("Bạn đã ngắt kết nối với người lạ!");
    }

    public function newRegister(): array
    {
        return [];
    }

    public function sendMessage($fbUid,$message):array
    {
        if(is_null($message)) return [];
        $user = $this->userRepository->findOne([
            'fb_uid' => $fbUid
        ]);
        if(is_null($user)) return $this->newRegister();
        $connect = $user->connect;
        if(is_null($connect) || $connect->status!==Connect::STATUS_BUSY) return ((new ResponseError('no connect',201))->toArray());
        $userConnected = $this->connectRepository->findOne([
            'to_user_id' => $connect->from_user_id
        ]);
        FChatHelper::sendMessage($userConnected->user->fb_uid, FChatHelper::replaceBadWord($message));
        return ((new ResponseSuccess([],200,"SEND MESSAGE SUCCESS"))->toArray());
    }
}
