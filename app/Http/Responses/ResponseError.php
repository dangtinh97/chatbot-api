<?php

namespace App\Http\Responses;

class ResponseError extends ApiResponse
{
    public function __construct($message = null, $status = 500, $response = [])
    {
        $this->code = $status;
        $this->success = false;
        $this->message = !is_null($message) ? $message : trans('response_status.500');
        $this->response = $response;
    }
}
