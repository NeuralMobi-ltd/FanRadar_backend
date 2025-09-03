<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SavedPost extends Model
{
    protected $fillable = [
        'user_id',
        'post_id',
    ];

    /**
     * Relation avec l'utilisateur qui a sauvegardé le post
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relation avec le post sauvegardé
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}
