<?php

namespace App\Http\Requests\Order;

use Illuminate\Foundation\Http\FormRequest;

class StoreRequest extends FormRequest
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
            'image'=>'image|mimes:svg',//,jpeg,png,jpg,gif,
            'mashine_id'=>'string',
            'category_id'=>'',
            'user_id_req'=>'string',
            'sets'=>'array',
        ];
    }
    /** 
         * Get the error messages for the defined validation rules.
     *
     * @return array
     */
    // public function messages()
    // {
    //     return [
            
    //         'image.image' => 'Загружаемый файл должен быть изображением.',
    //         'image.mimes' => 'Изображение должно быть в формате: jpeg, png, jpg, gif, svg.',
    //     ];
    // }
}
