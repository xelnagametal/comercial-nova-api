<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Valida la creación (POST) y la actualización (PUT/PATCH) de un usuario.
 *
 * Al actualizar, la contraseña es opcional: si no se envía se conserva la actual.
 * `roles` es la lista completa de roles del usuario (al menos uno).
 */
class UserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $esActualizacion = $this->route('user') !== null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($this->route('user'))],
            'password' => [
                $esActualizacion ? 'nullable' : 'required',
                'confirmed',
                Password::defaults(),
            ],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', 'distinct', Rule::exists('roles', 'name')->where('guard_name', 'web')],
            'activo' => ['sometimes', 'boolean'],
        ];
    }
}
