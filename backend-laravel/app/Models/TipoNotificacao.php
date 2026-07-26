<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TipoNotificacao extends Model
{
    protected $table = 'tipos_notificacao';

    protected $primaryKey = 'chave';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['chave', 'nome', 'descricao', 'categoria', 'publico', 'ativo_por_padrao'];

    protected $casts = [
        'ativo_por_padrao' => 'boolean',
    ];
}
