<?php

namespace App\Http\Requests\Dump;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    
    public function rules(): array
    {
        return [
            // Dump
            'name_dump' => 'required',

            // Зоны (массив)
            'zones' => 'array|min:1',
            'zones.*.id' => 'nullable|exists:zones,id',
            'zones.*.name_zone' => 'required|string|max:255',
            'zones.*.volume' => 'required|numeric|min:0',
            'zones.*.ship' => 'boolean',
            'zones.*.delivery' => 'nullable|string|max:100',
            //'zones.*.dump_id' => 'required|exists:dumps,id',

            // Rocks
            'zones.*.rocks' => 'array|min:1',
            'zones.*.rocks.*.id' => 'required|exists:rocks,id',
        ];
    }
}
