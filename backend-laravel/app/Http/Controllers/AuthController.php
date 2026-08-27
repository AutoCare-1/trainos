<?php

namespace App\Http\Controllers;

use App\Models\Professional;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
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
            // is_admin vai junto só pra o menu decidir se mostra o CRM. Não é a
            // trava de acesso — quem manda é o middleware AdminOnly no backend;
            // forjar esse campo no cliente só revelaria um link que responde 404.
            'professional' => $professional->only(['id', 'name', 'email', 'is_admin']),
        ]);
    }

    /**
     * POST /auth/logout — encerra a sessão de verdade, no servidor.
     *
     * Até aqui "Sair" era só apagar o localStorage: o token continuava válido
     * pelos 7 dias do TTL, então quem copiou o token antes seguia com acesso.
     * Marcar tokens_valid_after derruba TODOS os tokens já emitidos pra este
     * personal (inclusive os de outros aparelhos, que é o comportamento
     * esperado de "sair" quando se desconfia de acesso indevido).
     */
    public function logout(Request $request): JsonResponse
    {
        $professional = Professional::find($request->user()->id);
        if (! $professional) {
            return response()->json(['error' => 'Profissional não encontrado'], 404);
        }

        $professional->forceFill(['tokens_valid_after' => now()])->save();
        Cache::forget("tokens_valid_after:{$professional->id}");

        return response()->json(['ok' => true]);
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
