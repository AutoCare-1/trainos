<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Student;
use Illuminate\Http\Request;

/** Rotas de "ficha do aluno" vistas pelo profissional — autenticadas por JWT, dono confirmado por professional_id. */
trait ResolvesStudentForProfessional
{
    protected function buscarAlunoDoProfissional(Request $request, string $id): ?Student
    {
        return Student::where('id', $id)->where('professional_id', $request->user()->id)->first();
    }
}
