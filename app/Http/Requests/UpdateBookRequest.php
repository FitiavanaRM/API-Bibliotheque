<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $bookId = $this->route('id');

        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'isbn' => ['sometimes', 'required', 'string', 'max:20', Rule::unique('books', 'isbn')->ignore($bookId)],
            'year' => ['sometimes', 'required', 'integer', 'min:0', 'max:' . date('Y')],
            'available' => ['sometimes', 'boolean'],
            'author_id' => ['sometimes', 'required', 'integer', 'exists:authors,id'],
        ];
    }
}