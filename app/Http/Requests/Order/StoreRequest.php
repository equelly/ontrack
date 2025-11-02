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
            'content' => 'nullable|required_without_all:sets|string|max:1000',
            'image'=>'nullable|image|mimes:jpeg,png,jpg,gif,svg|min:10,max:5120',
            'mashine_id' => 'required|exists:mashines,id',
            'category_id'=> 'required|exists:categories,id',
            'user_id_req'=>'string',
            'sets' => 'required_without:content|array|min:1',
            'sets.*' => 'exists:sets,id',

            'content.max' => 'Описание не должно превышать 1000 символов.',
        ];
    }
    /** 
         * Get the error messages for the defined validation rules.
     *
     * @return array
     */
    public function messages()
    {
        return [
            
            'image.image' => 'Загружаемый файл должен быть изображением.',
            'image.mimes' => 'Изображение должно быть в формате: jpeg, png, jpg, gif, svg.',
            'image.max' => 'Максимальный размер изображения: 5MB.',
            'mashine_id.required' => 'Пожалуйста, выберите машину!',
            'mashine_id.exists' => 'Выбранная машина не найдена!',
            'content.required_without_all' => 'Заполните заявку или добавьте позиции комплектации!',
            'sets.required_without' => 'Добавьте хотя бы один пункт комплектации или заполните заявку',
            'sets.min' => 'Выберите минимум 1 позицию комплекткции.',
            'sets.*.exists' => 'Некоторые позиции не найдены.',

        ];
    }
}
