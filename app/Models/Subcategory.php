<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Subcategory extends Model
{
    use HasFactory;
    protected $fillable = ['category_id', 'name'];

    // Une sous-catégorie appartient à une catégorie
    public function category()
    {
        return $this->belongsTo(Category::class);
    }
    public function posts()
    {
        return $this->hasMany(Post::class);
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }
    public function fandoms()
    {
        return $this->hasMany(\App\Models\Fandom::class, 'subcategory_id');
    }
}
