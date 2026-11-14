<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
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
     * Registra un usuario nuevo y le emite un token.
     *
     * @param  Request  $request  Debe incluir name, email, password, password_confirmation y device_name.
     * @return JsonResponse  Usuario creado y token en texto plano (201).
     */
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'email'       => ['required', 'email', 'max:255', 'unique:users,email'],
            'password'    => ['required', 'confirmed', Password::defaults()],
            'device_name' => ['required', 'string', 'max:255'],
        ]);

        $user = User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => $data['password'], // El cast 'hashed' del modelo lo hashea.
        ]);

        return response()->json([
            'user'  => $user,
            'token' => $user->createToken($data['device_name'])->plainTextToken,
        ], 201);
    }

    /**
     * Valida las credenciales y emite un token nuevo.
     *
     * @param  Request  $request  Debe incluir email, password y device_name.
     * @return JsonResponse  Usuario y token en texto plano.
     *
     * @throws ValidationException  Si las credenciales no son válidas (422).
     */
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email'       => ['required', 'email'],
            'password'    => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:255'],
        ]);

        $user = User::where('email', $data['email'])->first();

        // Mismo mensaje si el usuario no existe o la contraseña es incorrecta,
        // para no revelar qué correos están registrados.
        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        return response()->json([
            'user'  => $user,
            'token' => $user->createToken($data['device_name'])->plainTextToken,
        ]);
    }

    /**
     * Devuelve el usuario dueño del token actual.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json($request->user());
    }

    /**
     * Revoca solo el token con el que se hizo la petición (cierra sesión en ese dispositivo).
     *
     * @param  Request  $request
     * @return JsonResponse  Respuesta vacía (204).
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(null, 204);
    }
}