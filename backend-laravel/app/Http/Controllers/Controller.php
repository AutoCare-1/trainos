<?php

namespace App\Http\Controllers;

use App\Models\Student;
use Illuminate\Http\Request;

abstract class Controller
{
    /**
     * Aluno já resolvido pelo middleware aluno.token (ResolveAlunoPorToken).
     *
     * Só faz sentido em rota que passa por esse middleware — se for chamado
     * fora dele, é bug de rota mal configurada, e falhar alto aqui é melhor
     * que devolver null e virar um 500 obscuro três linhas adiante.
     */
    protected function alunoDoPortal(Request $request): Student
    {
        $aluno = $request->attributes->get('aluno');
        if (! $aluno instanceof Student) {
            throw new \LogicException('Rota do portal sem o middleware aluno.token.');
        }

        return $aluno;
    }
}
