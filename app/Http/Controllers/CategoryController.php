<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = Category::all();
        return response()->json($categories);
    }

    // Affiche une catégorie spécifique
    public function show($id)
    {
        $category = Category::with('subcategories')->find($id);

        if (!$category) {
            return response()->json(['message' => 'Catégorie non trouvée'], 404);
        }

        return response()->json($category);
    }


    // Crée une nouvelle catégorie
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ]);

        // Gérer l'upload de l'image
        $imagePath = null;
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            if ($file->isValid()) {
                $imagePath = $file->store('category/images', 'public');
            }
        }

        $category = Category::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'image' => $imagePath,
        ]);

        return response()->json([
            'message' => 'Catégorie créée avec succès',
            'category' => $category
        ], 201);
    }

    // Met à jour une catégorie existante
    public function update(Request $request, $id)
    {
        $category = Category::find($id);

        if (!$category) {
            return response()->json(['message' => 'Catégorie non trouvée'], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|nullable|string|max:1000',
            'image' => 'sometimes|nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ]);

        $updateData = [];

        // Mettre à jour le nom si fourni
        if (isset($validated['name'])) {
            $updateData['name'] = $validated['name'];
        }

        // Mettre à jour la description si fournie
        if (array_key_exists('description', $validated)) {
            $updateData['description'] = $validated['description'];
        }

        // Gérer l'upload de la nouvelle image
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            if ($file->isValid()) {
                // Supprimer l'ancienne image si elle existe
                if ($category->image && Storage::disk('public')->exists($category->image)) {
                    Storage::disk('public')->delete($category->image);
                }

                // Uploader la nouvelle image
                $imagePath = $file->store('category/images', 'public');
                $updateData['image'] = $imagePath;
            }
        }

        $category->update($updateData);

        return response()->json([
            'message' => 'Catégorie mise à jour avec succès',
            'category' => $category
        ]);
    }

    // Supprime une catégorie
    public function destroy($id)
    {
        $category = Category::find($id);

        if (!$category) {
            return response()->json(['message' => 'Catégorie non trouvée'], 404);
        }

        // Supprimer l'image associée si elle existe
        if ($category->image && Storage::disk('public')->exists($category->image)) {
            Storage::disk('public')->delete($category->image);
        }

        $category->delete();

        return response()->json([
            'message' => 'Catégorie supprimée avec succès'
        ]);
    }



     public function getAllCategories(Request $request) {
        $page = max(1, (int) $request->get('page', 1));
        $limit = min(50, max(1, (int) $request->get('limit', 10)));

        // Récupérer les catégories avec pagination
        $categories = Category::paginate($limit, ['*'], 'page', $page);

        // Formater les données
        $formattedCategories = $categories->getCollection()->map(function ($category) {
            return [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug ?? null,
                'description' => $category->description ?? null,
                'image' => $category->image ?? null,
                'created_at' => $category->created_at ? $category->created_at->toISOString() : null,
                'updated_at' => $category->updated_at ? $category->updated_at->toISOString() : null,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'categories' => $formattedCategories,
                'pagination' => [
                    'current_page' => $categories->currentPage(),
                    'last_page' => $categories->lastPage(),
                    'per_page' => $categories->perPage(),
                    'total' => $categories->total(),
                    'has_more' => $categories->hasMorePages(),
                ]
            ]
        ]);
    }

}
