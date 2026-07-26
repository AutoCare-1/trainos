<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreExpenseRequest;
use App\Http\Requests\UpdateExpenseRequest;
use App\Models\ProfessionalExpense;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GastoController extends Controller
{
    // GET / — lista despesas ativas do personal (recorrentes em aberto + avulsas ainda não encerradas)
    public function index(Request $request): JsonResponse
    {
        $hoje = now()->toDateString();

        $expenses = ProfessionalExpense::where('professional_id', $request->user()->id)
            ->where(fn ($q) => $q->whereNull('ends_on')->orWhere('ends_on', '>=', $hoje))
            ->orderByDesc('starts_on')
            ->get();

        return response()->json(['expenses' => $expenses]);
    }

    // POST / — cria uma despesa (recorrente ou avulsa)
    public function store(StoreExpenseRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $expense = ProfessionalExpense::create([
            'professional_id' => $request->user()->id,
            'description' => trim($validated['description']),
            'amount' => $validated['amount'],
            'is_recurring' => $validated['is_recurring'],
            'starts_on' => $validated['starts_on'],
            'ends_on' => $validated['is_recurring'] ? null : ($validated['ends_on'] ?? null),
        ]);

        return response()->json(['expense' => $expense], 201);
    }

    // PATCH /:id — edita a despesa. Se for recorrente e o valor mudar, fecha esta
    // linha e cria uma nova (mesma regra de nunca sobrescrever histórico usada em
    // student_billing_plans) — senão (avulsa, ou só mudou a descrição) atualiza direto.
    public function update(UpdateExpenseRequest $request, string $id): JsonResponse
    {
        $expense = ProfessionalExpense::where('id', $id)->where('professional_id', $request->user()->id)->first();
        if (! $expense) {
            return response()->json(['error' => 'Despesa não encontrada'], 404);
        }

        $validated = $request->validated();

        $novaDescricao = isset($validated['description']) ? trim($validated['description']) : $expense->description;
        $novoValor = $validated['amount'] ?? $expense->amount;
        $valorMudou = Money::toCents($novoValor) !== Money::toCents($expense->amount);

        $resultado = DB::transaction(function () use ($expense, $novaDescricao, $novoValor, $valorMudou, $validated) {
            if ($expense->is_recurring && $valorMudou) {
                $hoje = now()->toDateString();
                $expense->update(['ends_on' => $hoje]);

                return ProfessionalExpense::create([
                    'professional_id' => $expense->professional_id,
                    'description' => $novaDescricao,
                    'amount' => $novoValor,
                    'is_recurring' => true,
                    'starts_on' => $hoje,
                    'previous_expense_id' => $expense->id,
                ]);
            }

            $expense->update([
                'description' => $novaDescricao,
                'amount' => $novoValor,
                'starts_on' => $validated['starts_on'] ?? $expense->starts_on->toDateString(),
                'ends_on' => array_key_exists('ends_on', $validated) ? $validated['ends_on'] : $expense->ends_on,
            ]);

            return $expense;
        });

        return response()->json(['expense' => $resultado]);
    }

    // PATCH /:id/encerrar — para uma despesa recorrente de contar a partir de hoje (ou da data informada)
    public function encerrar(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'ends_on' => ['nullable', 'date'],
        ]);

        $expense = ProfessionalExpense::where('id', $id)->where('professional_id', $request->user()->id)->first();
        if (! $expense) {
            return response()->json(['error' => 'Despesa não encontrada'], 404);
        }

        $expense->update(['ends_on' => $validated['ends_on'] ?? now()->toDateString()]);

        return response()->json(['expense' => $expense]);
    }
}
