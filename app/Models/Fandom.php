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
    ];

    // Expose noms calculés sans stocker category_id dans la table
    protected $appends = ['subcategory_name', 'category_name'];

    public function getSubcategoryNameAttribute()
    {
        return $this->subcategory ? $this->subcategory->name : null;
    }

    public function getCategoryNameAttribute()
    {
        return $this->subcategory && $this->subcategory->category ? $this->subcategory->category->name : null;
    }

    public function subcategory()
    {
        return $this->belongsTo(SubCategory::class);
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

    public function category()
    {
        // On récupère la catégorie via la sous-catégorie
        return $this->subcategory ? $this->subcategory->category : null;
    }
}
