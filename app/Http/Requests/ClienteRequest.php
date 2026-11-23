<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Valida la creación (POST) y la actualización (PUT/PATCH) de un cliente.
 *
 * El formato de la identificación depende de su tipo: cédula de 10 dígitos,
 * RUC de 13 dígitos o pasaporte alfanumérico.
 */
class ClienteRequest extends FormRequest
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
        $tipo = $this->input('tipo_identificacion');

        return [
            'tipo_identificacion' => ['required', Rule::in(['RUC', 'CEDULA', 'PASAPORTE'])],
            'identificacion' => [
                'required',
                'string',
                Rule::when($tipo === 'CEDULA', ['digits:10']),
                Rule::when($tipo === 'RUC', ['digits:13']),
                Rule::when($tipo === 'PASAPORTE', ['alpha_num', 'max:20']),
                Rule::unique('clientes')->ignore($this->route('cliente')),
            ],
            'nombre' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:20'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'activo' => ['sometimes', 'boolean'],
        ];
    }
}
