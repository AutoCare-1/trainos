<?php

namespace App\Http\Requests;

use App\Models\StudentBillingPlan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAlunoRequest extends FormRequest
{
    // Autorização de dono (professional_id do JWT) é feita no controller, mesmo
    // padrão já usado em todo o resto do app (não há Policies aqui).
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string'],
            'email' => ['nullable', 'string'],
            'phone' => ['nullable', 'string'],
            'objective' => ['nullable', 'string'],
            'weight_kg' => ['nullable', 'numeric'],
            'height_cm' => ['nullable', 'numeric'],
            // Cobrança é opcional no cadastro — mas se um dos dois vier, o outro
            // também precisa vir (não faz sentido tipo sem valor ou valor sem tipo).
            'billing_type' => ['nullable', 'string', Rule::in(StudentBillingPlan::TIPOS_VALIDOS), 'required_with:monthly_value'],
            'monthly_value' => ['nullable', 'numeric', 'min:0', 'required_with:billing_type'],
        ];
    }
}
