<?php

namespace App\Http\Requests\Dump;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    
   public function rules()
{
    return [
        'name_dump' => 'required|string|max:255',

        // // Существующие зоны
        // 'zones.*.name_zone' => 'sometimes|required|string|max:255',
        // 'zones.*.volume' => 'sometimes|required|numeric|min:0|max:30',
        // 'zones.*.rocks.*.id' => 'sometimes|required|exists:rocks,id',
        // 'zones.*.delivery' => 'sometimes|boolean',

        // // Новые зоны
        // 'zones.new_*' => 'sometimes|array',
        // 'zones.new_*.name_zone' => 'sometimes|required|string|max:255',
        // 'zones.new_*.volume' => 'sometimes|required|numeric|min:0|max:30',
        // 'zones.new_*.rocks' => 'sometimes|array',
        // 'zones.new_*.rocks.*' => 'sometimes|exists:rocks,id',
        // 'zones.new_*.delivery' => 'sometimes|boolean',
    ];
}

}
