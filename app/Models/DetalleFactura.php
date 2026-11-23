<?php

namespace App\Models;

use Database\Factories\DetalleFacturaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'factura_id',
    'producto_id',
    'codigo_producto',
    'descripcion',
    'cantidad',
    'precio_unitario',
    'descuento',
    'subtotal',
    'porcentaje_iva',
    'valor_iva',
    'total',
])]
#[WithoutTimestamps]
class DetalleFactura extends Model
{
    /** @use HasFactory<DetalleFacturaFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Factura, $this>
     */
    public function factura(): BelongsTo
    {
        return $this->belongsTo(Factura::class);
    }

    /**
     * @return BelongsTo<Producto, $this>
     */
    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cantidad' => 'integer',
            'precio_unitario' => 'decimal:2',
            'descuento' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'porcentaje_iva' => 'decimal:2',
            'valor_iva' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }
}
