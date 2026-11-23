<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Autenticación de la API mediante Personal Access Tokens de Sanctum.
 *
 * No usa sesión ni cookies: cada cliente recibe un token Bearer que debe
 * enviar en el header "Authorization: Bearer {token}".
 */
class AuthController extends Controller
{
    /**
     * Valida las credenciales y emite un token nuevo.
     *
     * @param  Request  $request  Debe incluir email, password y device_name.
     * @return JsonResponse Usuario con sus roles y permisos, y token en texto plano.
     *
     * @throws ValidationException Si las credenciales no son válidas (422).
     */
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:255'],
        ]);

        $user = User::where('email', $data['email'])->first();

        // Mismo mensaje si el usuario no existe (o fue eliminado), está inactivo
        // o la contraseña es incorrecta, para no revelar qué correos están registrados.
        if (! $user || ! $user->activo || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        return response()->json([
            'user' => new UserResource($user),
            'token' => $user->createToken($data['device_name'])->plainTextToken,
        ]);
    }

    /**
     * Devuelve el usuario dueño del token actual con sus roles y permisos.
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json(new UserResource($request->user()));
    }

    /**
     * Revoca solo el token con el que se hizo la petición (cierra sesión en ese dispositivo).
     *
     * @return JsonResponse Respuesta vacía (204).
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(null, 204);
    }
}
