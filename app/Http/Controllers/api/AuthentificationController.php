<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class AuthentificationController extends Controller
{
   public function login(Request $request)
            {
            $request->validate([
                'email' => 'required|email',
                'password' => 'required',
                ]);

                $user = User::where('email', $request->email)->first();

                if (!$user || !Hash::check($request->password, $user->password)) {
                    return response()->json([
                        'message' => 'Email ou mot de passe invalide.'
                    ], 401);
                }

                // Créer un token avec Sanctum
                $token = $user->createToken('auth_token')->plainTextToken;

                // Ensure we return an exact role string and also full roles/permissions arrays
                $roleNames = $user->getRoleNames();
                $permissionNames = $user->getPermissionNames();

                // Fallback: if user has no roles yet, assign default 'user'
                if ($roleNames->isEmpty()) {
                    $user->assignRole('user');
                    $roleNames = $user->getRoleNames();
                }

                // Générer l'URL complète de l'image de profil
                $imageUrl = null;
                if ($user->profile_image && $user->profile_image !== 'default.png') {
                    if (Storage::disk('public')->exists('profile/' . $user->profile_image)) {
                        $imageUrl = asset('storage/profile/' . $user->profile_image);
                    }
                }
                if (!$imageUrl) {
                    $imageUrl = asset('storage/profile/default.png');
                }

                return response()->json([
                    'message' => 'Connexion réussie.',
                    'user' => [
                        'id' => $user->id,
                        'first_name' => $user->first_name,
                        'last_name' => $user->last_name,
                        'email' => $user->email,
                        'profile_image' => $imageUrl,
                        'role' => $roleNames->first() ?? null,
                        'permissions' => $permissionNames->toArray(),
                    ],
                    'token' => $token,
                ]);
            }




    public function register(Request $request)
            {
                $validator = Validator::make($request->all(), [
                    'first_name' => 'required|string|max:255',
                    'last_name' => 'required|string|max:255',
                    'email' => 'required|string|email|unique:users,email',
                    'password' => 'required|string|min:6',
                    'profile_image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',

                ]);

                if ($validator->fails()) {
                    return response()->json(['errors' => $validator->errors()], 422);
                }

                // Traitement de l'image si elle est envoyée
                $profileImageName = 'default.png';
                if ($request->hasFile('profile_image')) {
                    $image = $request->file('profile_image');
                    $profileImageName = time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();
                    $image->storeAs('profile', $profileImageName, 'public');
                }

                // Création de l'utilisateur
                $user = User::create([
                    'first_name' => $request->first_name,
                    'last_name' => $request->last_name,
                    'email' => $request->email,
                    'password' => Hash::make($request->password),
                    'profile_image' => $profileImageName,
                ]);

                $user->assignRole('user');// Assign a default role 'user'

                // Création du token Sanctum
                $token = $user->createToken('auth_token')->plainTextToken;

                // Ensure we return an exact role string and also full roles/permissions arrays
                $roleNames = $user->getRoleNames();
                $permissionNames = $user->getPermissionNames();

                // Générer l'URL complète de l'image de profil pour la réponse
                $imageUrl = asset('storage/profile/' . $profileImageName);

                return response()->json([
                    'message' => 'Inscription réussie.',
                    'user' => [
                        'id' => $user->id,
                        'first_name' => $user->first_name,
                        'last_name' => $user->last_name,
                        'email' => $user->email,
                        'profile_image' => $imageUrl,
                        'role' => $roleNames->first() ?? null,
                        'permissions' => $permissionNames->toArray(),
                    ],
                    'token' => $token,
                ], 201);
            }



    public function logout(Request $request)
            {
                $user = $request->user();
                $token = $user ? $user->currentAccessToken() : null;
                // Correction : vérifier que $token est bien une instance de PersonalAccessToken
                if ($token && ($token instanceof \Laravel\Sanctum\PersonalAccessToken)) {
                    $token->delete();
                    return response()->json(['message' => 'Logout successful']);
                }
                return response()->json(['message' => 'No active session found'], 401);
            }

    public function logoutfromAllDevices(Request $request)
            {
                // Supprime tous les tokens de l'utilisateur
                $request->user()->tokens()->delete();

                return response()->json(['message' => 'Logged out from all devices.']);
            }
}
