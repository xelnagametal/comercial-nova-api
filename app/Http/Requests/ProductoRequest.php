<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Valida la creación (POST) y la actualización (PUT/PATCH) de un producto.
 *
 * El stock no se recibe aquí: solo cambia mediante movimientos de inventario.
 * Al actualizar se exige la versión leída por el cliente (bloqueo optimista).
 */
class ProductoRequest extends FormRequest
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
        $esActualizacion = $this->route('producto') !== null;

        return [
            'categoria_id' => ['required', 'integer', Rule::exists('categorias', 'id')->withoutTrashed()],
            'codigo' => ['required', 'string', 'max:50', Rule::unique('productos')->ignore($this->route('producto'))],
            'nombre' => ['required', 'string', 'max:255'],
            'precio' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
            'porcentaje_iva' => ['required', 'numeric', Rule::in([0, 15])],
            'activo' => ['sometimes', 'boolean'],
            'version' => [Rule::excludeIf(! $esActualizacion), 'required', 'integer'],
        ];
    }
}
