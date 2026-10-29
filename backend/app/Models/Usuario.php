<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class Usuario extends Authenticatable
{
    use HasApiTokens;

    protected $table = 'usuarios';

    protected $primaryKey = 'id_usuario';

    public $timestamps = true;

    const CREATED_AT = 'creado_en';
    const UPDATED_AT = 'actualizado_en';

    protected $fillable = [
        'nombre',
        'correo',
        'contrasena',
        'estado',
    ];

    protected $hidden = [
        'contrasena',
    ];

    /**
     * Indica a Laravel qué campo contiene la contraseña.
     */
    public function getAuthPasswordName(): string
    {
        return 'contrasena';
    }

    public function restaurantes(): BelongsToMany
    {
        return $this->belongsToMany(
            Restaurante::class,
            'restaurantes_usuarios',
            'id_usuario',
            'id_restaurante',
            'id_usuario',
            'id_restaurante'
        )->withPivot(
            'id_restaurante_usuario',
            'rol',
            'creado_en'
        );
    }

    public function esSuperadministrador(): bool
    {
        return (bool) $this->es_superadministrador;
    }
}
