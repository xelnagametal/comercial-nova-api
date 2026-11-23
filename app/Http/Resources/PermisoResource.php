<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

/**
 * Permiso con su acción separada del recurso ({recurso}.{accion}).
 *
 * @mixin Permission
 */
class PermisoResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'accion' => Str::after($this->name, '.'),
        ];
    }
}
