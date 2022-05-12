<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FChatRegisterRequest extends ApiFormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'fb_uid' => 'required',
            'full_name' => 'required',
        ];
    }
}
