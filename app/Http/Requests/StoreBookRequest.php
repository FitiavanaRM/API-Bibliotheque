<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBookRequest extends FormRequest
{
    public function authorize(): bool
    {   
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'isbn' => ['required', 'string', 'max:20', 'unique:books,isbn'],
            'year' => ['required', 'integer', 'min:0', 'max:' . date('Y')],
            'available' => ['sometimes', 'boolean'],
            'author_id' => ['required', 'integer', 'exists:authors,id'],
        ];
    }
}