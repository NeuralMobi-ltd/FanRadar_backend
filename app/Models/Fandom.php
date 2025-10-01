<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Fandom extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'description',
        'subcategory_id',
        'cover_image',
        'logo_image',
        'isactive',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'isactive' => 'boolean',
        ];
    }

    public function subcategory()
    {
        return $this->belongsTo(Subcategory::class);
    }

    public function posts()
    {
    // Relation vers les posts appartenant à ce fandom via la colonne fandom_id
    return $this->hasMany(Post::class, 'fandom_id');
    }

    public function members()
    {
        return $this->hasMany(Member::class);
    }
}
