<?php


namespace App\Http\Responses;


class ResponseCustomize extends ApiResponse
{
    public function __construct($code=201,$message = 'Lỗi không xác định, vui lòng thử lại', $response = [])
    {
        $this->code = $code;
        $this->message = $message;
        $this->response = $response;
        $this->success = false;
    }
}
