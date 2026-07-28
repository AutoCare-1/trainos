<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Student;

/** Rotas do portal do aluno são públicas, autenticadas pelo invite_token na URL — nunca por JWT. */
trait ResolvesStudentByToken
{
    protected function buscarAlunoPorToken(string $token): ?Student
    {
        return Student::where('invite_token', $token)->where('status', 'active')->first();
    }
}
