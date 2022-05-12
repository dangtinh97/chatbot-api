<?php

namespace App\Http\Requests;

use App\Http\Responses\ResponseError;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class ApiFormRequest extends FormRequest
{
    protected function failedValidation(Validator $validator)
    {
        $content = $validator->errors()->first();
        throw new HttpResponseException( response()->json((new ResponseError($content,422))->toArray()));
    }
}
