<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    // A tela de edição sempre reenvia o objeto inteiro (pré-preenchido com os
    // valores atuais), então after_or_equal:starts_on compara dentro do mesmo
    // payload — não precisa buscar o starts_on atual no banco pra validar.
    public function rules(): array
    {
        return [
            'description' => ['sometimes', 'required', 'string'],
            'amount' => ['sometimes', 'required', 'numeric', 'min:0'],
            'starts_on' => ['sometimes', 'required', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
        ];
    }
}
