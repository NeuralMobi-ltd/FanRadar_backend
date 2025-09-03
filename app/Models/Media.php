<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Media extends Model
{
    protected $fillable = [
        'url', // pour stocker l'URL du fichier
        'type', // pour le type MIME
        'file_path', // si tu utilises ce champ ailleurs
        'media_type', // si tu utilises ce champ ailleurs
    ];
    public function mediable()
    {
        return $this->morphTo();
    }
}
