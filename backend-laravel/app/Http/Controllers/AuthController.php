<?php

namespace App\Http\Controllers;

use App\Models\Professional;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    // GET /auth/me — dados do profissional autenticado
    public function me(Request $request): JsonResponse
    {
        // O middleware de auth só valida o JWT (sem tocar o banco — ver JwtAuthenticate),
        // então $request->user() aqui só tem o id da claim sub. Essa rota é a única
        // que precisa dos dados reais, então busca explicitamente — mesmo padrão do
        // /me do Node (só ele consulta o profissional; requireAuth nunca consultava).
        $professional = Professional::find($request->user()->id);
        if (! $professional) {
            return response()->json(['error' => 'Profissional não encontrado'], 404);
        }

        return response()->json([
            'professional' => $professional->only(['id', 'name', 'email']),
        ]);
    }

    // POST /auth/signup
    public function signup(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string'],
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string', 'min:6'],
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => 'name, email e password (mín. 6 caracteres) são obrigatórios'], 400);
        }

        $email = mb_strtolower(trim($request->input('email')));

        if (Professional::where('email', $email)->exists()) {
            return response()->json(['error' => 'Já existe um profissional com este e-mail'], 409);
        }

        $professional = Professional::create([
            'name' => trim($request->input('name')),
            'email' => $email,
            'password_hash' => Hash::make($request->input('password')),
        ]);
        // created_at é preenchido pelo default da coluna (useCurrent()), não pelo
        // Eloquent (model tem $timestamps = false) — refresh() traz o valor real.
        $professional->refresh();

        $token = Auth::guard('api')->login($professional);

        return response()->json([
            'token' => $token,
            'professional' => $professional->only(['id', 'name', 'email', 'created_at']),
        ], 201);
    }

    // POST /auth/login
    public function login(Request $request): JsonResponse
    {
        $email = trim((string) $request->input('email'));
        $password = (string) $request->input('password');

        if ($email === '' || $password === '') {
            return response()->json(['error' => 'email e password são obrigatórios'], 400);
        }

        $professional = Professional::where('email', mb_strtolower($email))->first();

        if (! $professional || ! Hash::check($password, $professional->password_hash)) {
            return response()->json(['error' => 'E-mail ou senha inválidos'], 401);
        }

        $token = Auth::guard('api')->login($professional);

        return response()->json([
            'token' => $token,
            'professional' => $professional->only(['id', 'name', 'email']),
        ]);
    }
}
