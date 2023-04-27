<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PaymentRequest extends FormRequest
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
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'amount' => 'required',
            'currency' => 'required',
            'provider' => 'required',
            'user_id' => 'required',
            'number' => 'required|digits:16',
            'exp_month' => 'required',
            'exp_year' => 'required',
            'cvc' => 'required',
        ];
    }
}
