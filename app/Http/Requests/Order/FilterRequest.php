<?php

namespace App\Http\Requests\Order;

use Illuminate\Foundation\Http\FormRequest;

class FilterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true ;//при создании установлено false, меняем чтобы request работал
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            //
            'content'=>'',
            'created_at'=>'',
            'mashine_id'=>'',
            'category_id'=>'',
            'user_id_req'=>'',
           
        ];
    }
}
