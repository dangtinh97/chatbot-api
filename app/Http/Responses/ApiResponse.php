<?php

namespace App\Http\Responses;

abstract class ApiResponse implements CanRespond
{
    protected $code;
    protected $message;
    protected $success;
    protected $response;

    public function toArray()
    {
        // TODO: Implement toArray() method.
        return [
            'status' => $this->code,
            'success' => $this->success,
            'content' => $this->message,
            'data' => (object)$this->response,
        ];
    }

    public function getStatus()
    {
        return $this->code;
    }

    public function getContent()
    {
        return $this->message;
    }

    public function getData(){
        return $this->response;
    }
}
