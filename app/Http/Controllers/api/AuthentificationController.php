<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

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

                return response()->json([
                    'message' => 'Connexion réussie.',
                    'user' => [
                        'id' => $user->id,
                        'first_name' => $user->first_name,
                        'last_name' => $user->last_name,
                        'email' => $user->email,
                        'profile_image' => $user->profile_image,
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
                    'bio' => 'nullable|string|max:2000',
                ]);

                if ($validator->fails()) {
                    return response()->json(['errors' => $validator->errors()], 422);
                }

                // Traitement de l'image si elle est envoyée
                /*$profileImagePath = null;
                if ($request->hasFile('profile_image')) {
                    $profileImagePath = $request->file('profile_image')->store('profile_images', 'public');
                }*/

                // Création de l'utilisateur
                $user = User::create([
                    'first_name' => $request->first_name,
                    'last_name' => $request->last_name,
                    'email' => $request->email,
                    'password' => Hash::make($request->password),
                    'profile_image' => $profileImagePath ?? null,
                    'bio' => $request->bio ?? null,
                ]);

                $user->assignRole('user');// Assign a default role 'user'

                // Création du token Sanctum
                $token = $user->createToken('auth_token')->plainTextToken;

                // Ensure we return an exact role string and also full roles/permissions arrays
                $roleNames = $user->getRoleNames();
                $permissionNames = $user->getPermissionNames();

                return response()->json([
                    'message' => 'Inscription réussie.',
                    'user' => [
                        'id' => $user->id,
                        'first_name' => $user->first_name,
                        'last_name' => $user->last_name,
                        'email' => $user->email,
                        'profile_image' => $user->profile_image,
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
