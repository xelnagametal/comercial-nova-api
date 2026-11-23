<?php

namespace App\Models;

use Database\Factories\FacturaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'cliente_id',
    'user_id',
    'numero',
    'fecha_emision',
    'subtotal_iva_0',
    'subtotal_iva_15',
    'descuento',
    'iva',
    'total',
    'forma_pago',
    'estado',
    'observacion',
])]
class Factura extends Model
{
    /** @use HasFactory<FacturaFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return BelongsTo<Cliente, $this>
     */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    /**
     * El usuario vendedor que emitió la factura.
     *
     * @return BelongsTo<User, $this>
     */
    public function vendedor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return HasMany<DetalleFactura, $this>
     */
    public function detalles(): HasMany
    {
        return $this->hasMany(DetalleFactura::class);
    }

    /**
     * @return HasMany<MovimientoInventario, $this>
     */
    public function movimientosInventario(): HasMany
    {
        return $this->hasMany(MovimientoInventario::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha_emision' => 'datetime',
            'subtotal_iva_0' => 'decimal:2',
            'subtotal_iva_15' => 'decimal:2',
            'descuento' => 'decimal:2',
            'iva' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }
}
