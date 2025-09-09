# 📚 PersonnaliseController API Documentation

## 🔗 Base URL
Toutes les routes commencent par : `https://your-domain.com/api/`

---

## 🔐 Authentification

### 1. Connexion Utilisateur
**POST** `/Y/auth/login`

Authentifie un utilisateur et retourne un token d'accès.

**Body :**
```json
{
    "email": "user@example.com",
    "password": "password123"
}
```

**Réponse :**
```json
{
    "success": true,
    "token": "sanctum_token_here",
    "user": {
        "id": 1,
        "first_name": "John",
        "last_name": "Doe",
        "email": "user@example.com"
    }
}
```

### 2. Inscription Utilisateur
**POST** `/Y/auth/register`

Créer un nouveau compte utilisateur.

**Body :**
```json
{
    "first_name": "John",
    "last_name": "Doe",
    "email": "user@example.com",
    "password": "password123",
    "password_confirmation": "password123"
}
```

### 3. Mot de Passe Oublié
**POST** `/forgetPassword`

Envoie un OTP pour réinitialiser le mot de passe.

**Body :**
```json
{
    "email": "user@example.com"
}
```

### 4. Réinitialiser Mot de Passe
**POST** `/resetPassword`

Réinitialise le mot de passe avec l'OTP.

**Body :**
```json
{
    "email": "user@example.com",
    "otp": "123456",
    "password": "new_password123",
    "password_confirmation": "new_password123"
}
```

### 5. Vérifier OTP
**POST** `/verifyOTP`

Vérifie le code OTP.

**Body :**
```json
{
    "email": "user@example.com",
    "otp": "123456"
}
```

---

## 👤 Gestion Profil Utilisateur

### 6. Obtenir Profil Personnel (Authentifié)
**GET** `/Y/users/profile`
🔒 *Nécessite une authentification*

Récupère le profil de l'utilisateur connecté.

### 7. Mettre à Jour Profil (Authentifié)
**POST** `/Y/users/profile`
🔒 *Nécessite une authentification*

Met à jour le profil de l'utilisateur connecté.

**Body :**
```json
{
    "first_name": "John",
    "last_name": "Doe",
    "bio": "Nouveau bio",
    "profile_image": "image_file_or_url"
}
```

### 8. Obtenir Profil Utilisateur par ID
**GET** `/Y/users/{userId}/profile`

Récupère le profil d'un utilisateur spécifique.

### 9. Obtenir Posts d'un Utilisateur
**GET** `/Y/users/{userId}/posts`

Récupère tous les posts d'un utilisateur.

**Paramètres de requête :**
- `page` : Numéro de page (défaut: 1)
- `limit` : Nombre d'éléments par page (défaut: 10)

### 10. Mettre à Jour Avatar
**PUT** `/users/avatar`

Met à jour l'avatar de l'utilisateur.

### 11. Mettre à Jour Photo de Couverture
**PUT** `/users/cover-photo`

Met à jour la photo de couverture de l'utilisateur.

---

## 📝 Gestion des Posts

### 12. Créer un Post (Authentifié)
**POST** `/Y/posts/create`
🔒 *Nécessite une authentification*

Crée un nouveau post.

**Body :**
```json
{
    "description": "Contenu du post",
    "content_status": "published",
    "medias": ["file1", "file2"],
    "tags": ["tag1", "tag2"],
    "schedule_at": "2024-01-01T12:00:00Z"
}
```

### 13. Mettre à Jour un Post (Authentifié)
**POST** `/Y/posts/{postId}/update`
🔒 *Nécessite une authentification*

Met à jour un post existant.

### 14. Supprimer un Post (Authentifié)
**DELETE** `/Y/posts/{postId}/delete`
🔒 *Nécessite une authentification*

Supprime un post.

### 15. Ajouter aux Favoris
**POST** `/Y/posts/{postId}/favorite`
🔒 *Nécessite une authentification*

Ajoute un post aux favoris.

### 16. Retirer des Favoris
**DELETE** `/Y/posts/{postId}/removefavorite`
🔒 *Nécessite une authentification*

Retire un post des favoris.

### 17. Sauvegarder un Post
**POST** `/Y/posts/save`
🔒 *Nécessite une authentification*

Sauvegarde un post.

**Body :**
```json
{
    "post_id": 123
}
```

### 18. Désauvegarder un Post
**POST** `/Y/posts/unsave`
🔒 *Nécessite une authentification*

Retire un post des sauvegardés.

### 19. Obtenir Posts Sauvegardés
**GET** `/Y/posts/savedPosts`
🔒 *Nécessite une authentification*

Récupère tous les posts sauvegardés de l'utilisateur.

### 20. Partager un Post
**POST** `/posts/{postId}/share`

Partage un post.

### 21. Obtenir Posts Tendances
**GET** `/Y/posts/trending/top`

Récupère les posts les plus populaires.

### 22. Obtenir Commentaires d'un Post
**GET** `/Y/posts/{postId}/comments`

Récupère tous les commentaires d'un post.

**Paramètres de requête :**
- `page` : Numéro de page
- `limit` : Nombre d'éléments par page

---

## 💬 Commentaires

### 23. Ajouter un Commentaire (Authentifié)
**POST** `/Y/posts/{postId}/comments`
🔒 *Nécessite une authentification*

Ajoute un commentaire à un post.

**Body :**
```json
{
    "content": "Contenu du commentaire"
}
```

---

## 👥 Relations Sociales

### 24. Suivre un Utilisateur (Authentifié)
**POST** `/Y/users/{userId}/follow`
🔒 *Nécessite une authentification*

Suit un utilisateur.

### 25. Ne Plus Suivre un Utilisateur (Authentifié)
**DELETE** `/Y/users/{userId}/unfollow`
🔒 *Nécessite une authentification*

Arrête de suivre un utilisateur.

### 26. Obtenir Abonnés d'un Utilisateur
**GET** `/Y/users/{userId}/followers`

Récupère la liste des abonnés d'un utilisateur.

### 27. Obtenir Abonnements d'un Utilisateur
**GET** `/Y/users/{userId}/following`

Récupère la liste des personnes suivies par un utilisateur.

---

## 📰 Flux de Contenu

### 28. Flux Personnel (Authentifié)
**GET** `/Y/feed/home`
🔒 *Nécessite une authentification*

Récupère le flux personnalisé de l'utilisateur.

### 29. Flux d'Exploration
**GET** `/Y/feed/explore`

Récupère le flux d'exploration public.

### 30. Flux des Abonnements (Authentifié)
**GET** `/Y/feed/following`
🔒 *Nécessite une authentification*

Récupère les posts des personnes suivies.

---

## 🏛️ Gestion des Fandoms

### 31. Obtenir Tous les Fandoms
**GET** `/Y/fandoms`

Récupère tous les fandoms disponibles.

**Paramètres de requête :**
- `page` : Numéro de page
- `limit` : Nombre d'éléments par page

### 32. Rechercher des Fandoms
**GET** `/Y/fandoms/search`

Recherche des fandoms par nom ou description.

**Paramètres de requête :**
- `q` : Terme de recherche
- `page` : Numéro de page
- `limit` : Nombre d'éléments par page

### 33. Obtenir Fandoms par Catégorie
**GET** `/Y/categories/{category_id}/fandoms`

Récupère tous les fandoms d'une catégorie spécifique.

**Paramètres de requête :**
- `page` : Numéro de page
- `limit` : Nombre d'éléments par page

### 34. Obtenir Fandoms Tendances
**GET** `/Y/fandoms/trending/top`

Récupère les fandoms les plus populaires.

### 35. Obtenir un Fandom par ID (Authentifié)
**GET** `/Y/fandoms/{fandom_id}`
🔒 *Nécessite une authentification*

Récupère les détails d'un fandom spécifique.

### 36. Créer un Fandom (Authentifié)
**POST** `/Y/fandoms`
🔒 *Nécessite une authentification*

Crée un nouveau fandom.

**Body :**
```json
{
    "name": "Nom du Fandom",
    "description": "Description du fandom",
    "subcategory_id": 1,
    "cover_image": "image_file_or_url",
    "logo_image": "image_file_or_url"
}
```

### 37. Mettre à Jour un Fandom (Authentifié)
**POST** `/Y/fandoms/{fandom_id}`
🔒 *Nécessite une authentification*

Met à jour un fandom existant (Admin uniquement).

### 38. Rejoindre un Fandom (Authentifié)
**POST** `/Y/fandoms/{fandom_id}/join`
🔒 *Nécessite une authentification*

Rejoint un fandom.

### 39. Quitter un Fandom (Authentifié)
**DELETE** `/Y/fandoms/{fandom_id}/leave`
🔒 *Nécessite une authentification*

Quitte un fandom.

### 40. Obtenir Mes Fandoms (Authentifié)
**GET** `/Y/users/my-fandoms`
🔒 *Nécessite une authentification*

Récupère tous les fandoms dont l'utilisateur est membre.

**Paramètres de requête :**
- `role` : Filtrer par rôle (`member`, `moderator`, `admin`)
- `page` : Numéro de page
- `limit` : Nombre d'éléments par page

---

## 👥 Gestion Membres Fandom

### 41. Obtenir Membres d'un Fandom
**GET** `/Y/fandoms/{fandom_id}/members`

Récupère tous les membres d'un fandom.

### 42. Changer Rôle d'un Membre (Authentifié)
**PUT** `/Y/fandoms/{fandom_id}/members/{user_id}/role`
🔒 *Nécessite une authentification (Admin)*

Change le rôle d'un membre dans un fandom.

**Body :**
```json
{
    "role": "moderator"
}
```

### 43. Supprimer un Membre (Authentifié)
**DELETE** `/Y/fandoms/{fandom_id}/members/{user_id}`
🔒 *Nécessite une authentification (Admin)*

Supprime un membre d'un fandom.

---

## 📝 Posts dans les Fandoms

### 44. Ajouter un Post à un Fandom (Authentifié)
**POST** `/Y/fandoms/{fandom_id}/posts`
🔒 *Nécessite une authentification*

Ajoute un post dans un fandom.

**Body :**
```json
{
    "description": "Contenu du post",
    "content_status": "published",
    "medias": ["file1", "file2"],
    "tags": ["tag1", "tag2"]
}
```

### 45. Mettre à Jour un Post dans un Fandom (Authentifié)
**PUT** `/Y/fandoms/{fandom_id}/posts/{post_id}`
🔒 *Nécessite une authentification*

Met à jour un post dans un fandom.

### 46. Supprimer un Post dans un Fandom (Authentifié)
**DELETE** `/Y/fandoms/{fandom_id}/posts/{post_id}`
🔒 *Nécessite une authentification*

Supprime un post dans un fandom.

### 47. Obtenir Posts d'un Fandom
**GET** `/Y/fandoms/{fandom_id}/posts`

Récupère tous les posts d'un fandom.

---

## 🏷️ Catégories et Sous-catégories

### 48. Obtenir Toutes les Catégories
**GET** `/Y/categories`

Récupère toutes les catégories avec pagination.

**Paramètres de requête :**
- `page` : Numéro de page
- `limit` : Nombre d'éléments par page

### 49. Obtenir Sous-catégories d'une Catégorie
**GET** `/Y/categories/{category_id}/subcategories`

Récupère les sous-catégories d'une catégorie.

### 50. Obtenir Posts par Catégorie
**GET** `/Y/categories/{category_id}/posts`

Récupère tous les posts d'une catégorie.

### 51. Obtenir Fandoms par Catégorie (Duplicate)
**GET** `/Y/categories/{category_id}/fandoms`

Récupère les fandoms d'une catégorie.

### 52. Obtenir Contenu d'une Sous-catégorie
**GET** `/Y/subcategories/{subcategory}/content`

Récupère le contenu d'une sous-catégorie.

### 53. Obtenir Fandoms d'une Sous-catégorie
**GET** `/Y/subcategories/{subcategory_id}/fandoms`

Récupère les fandoms d'une sous-catégorie.

---

## 🔍 Recherche

### 54. Rechercher des Utilisateurs
**GET** `/Y/search/users`

Recherche des utilisateurs par nom.

**Paramètres de requête :**
- `q` : Terme de recherche
- `page` : Numéro de page
- `per_page` : Nombre d'éléments par page

### 55. Rechercher des Posts
**GET** `/Y/search/posts`

Recherche des posts par contenu, tags ou sous-catégorie.

**Paramètres de requête :**
- `q` : Terme de recherche
- `page` : Numéro de page
- `per_page` : Nombre d'éléments par page

### 56. Rechercher des Fandoms avec Pagination
**GET** `/Y/search/fandom`

Recherche des fandoms avec pagination.

**Paramètres de requête :**
- `q` : Terme de recherche
- `page` : Numéro de page
- `per_page` : Nombre d'éléments par page

---

## #️⃣ Hashtags

### 57. Obtenir Hashtags Tendances
**GET** `/Y/hashtags/trending`

Récupère les hashtags les plus populaires.

**Paramètres de requête :**
- `limit` : Nombre d'hashtags (défaut: 10, max: 50)
- `days` : Période en jours (défaut: 7)

### 58. Obtenir Posts par Hashtag
**GET** `/Y/hashtags/{hashtag_id}/posts`

Récupère tous les posts associés à un hashtag.

**Paramètres de requête :**
- `page` : Numéro de page
- `limit` : Nombre d'éléments par page

---

## 🛍️ E-Commerce / Store

### 59. Obtenir Produits Drag (Édition Limitée)
**GET** `/Y/products/drag`

Récupère les produits en édition limitée.

**Paramètres de requête :**
- `page` : Numéro de page
- `limit` : Nombre d'éléments par page
- `status` : Statut (`upcoming`, `active`, `expired`, `all`)

**Réponse :**
```json
{
    "success": true,
    "data": {
        "products": [
            {
                "id": 1,
                "product_name": "Produit Limité",
                "price": 99.99,
                "stock": 50,
                "sale_start_date": "2024-01-01T00:00:00.000Z",
                "sale_end_date": "2024-01-31T23:59:59.000Z",
                "status": "active",
                "time_remaining_days": 15,
                "urgency_level": "medium"
            }
        ],
        "statistics": {
            "total_drag_products": 45,
            "active_products": 12,
            "upcoming_products": 8,
            "expired_products": 25
        },
        "pagination": {
            "current_page": 1,
            "last_page": 5,
            "per_page": 10,
            "total": 45,
            "has_more": true
        }
    }
}
```

### 60. Ajouter Produit aux Favoris (Authentifié)
**POST** `/Y/favorites/{pProductId}/favorite`
🔒 *Nécessite une authentification*

Ajoute un produit aux favoris.

### 61. Retirer Produit des Favoris (Authentifié)
**DELETE** `/Y/favorites/{pProductId}/removefavorite`
🔒 *Nécessite une authentification*

Retire un produit des favoris.

### 62. Obtenir Posts Favoris (Authentifié)
**GET** `/Y/myfavorites/posts`
🔒 *Nécessite une authentification*

Récupère tous les posts favoris de l'utilisateur.

### 63. Obtenir Produits Favoris (Authentifié)
**GET** `/Y/myfavorites/products`
🔒 *Nécessite une authentification*

Récupère tous les produits favoris de l'utilisateur.

---

## 🛒 Panier et Commandes

### 64. Obtenir Catégories Store
**GET** `/store/categories`

Récupère les catégories du store.

### 65. Obtenir Marques Store
**GET** `/store/brands`

Récupère les marques du store.

### 66. Ajouter au Panier
**POST** `/store/cart`

Ajoute un produit au panier.

### 67. Ajouter à la Liste de Souhaits
**POST** `/store/wishlist/{productId}`

Ajoute un produit à la liste de souhaits.

### 68. Obtenir Panier
**GET** `/store/cart`

Récupère le contenu du panier.

### 69. Mettre à Jour Élément du Panier
**PUT** `/store/cart/{itemId}`

Met à jour un élément du panier.

### 70. Supprimer Élément du Panier
**DELETE** `/store/cart/{itemId}`

Supprime un élément du panier.

### 71. Créer une Commande
**POST** `/store/orders`

Crée une nouvelle commande.

### 72. Obtenir Commandes
**GET** `/store/orders`

Récupère les commandes de l'utilisateur.

### 73. Annuler une Commande
**PUT** `/store/orders/{orderId}/cancel`

Annule une commande.

### 74. Obtenir Détails d'une Commande
**GET** `/store/orders/{orderId}`

Récupère les détails d'une commande.

### 75. Évaluer une Commande
**POST** `/store/orders/{orderId}/review`

Évalue une commande.

---

## 🖼️ Upload et Médias

### 76. Upload d'Image
**POST** `/upload/image`

Upload une image et retourne son URL.

**Body (form-data) :**
- `image` : Fichier image

**Réponse :**
```json
{
    "success": true,
    "url": "https://domain.com/storage/uploads/image.jpg"
}
```

---

## 📊 Codes de Réponse HTTP

- **200** : Succès
- **201** : Créé avec succès
- **400** : Requête invalide
- **401** : Non authentifié
- **403** : Accès refusé
- **404** : Ressource non trouvée
- **422** : Erreur de validation
- **500** : Erreur serveur

---

## 🔒 Authentification Required

Les routes marquées avec 🔒 nécessitent un token d'authentification dans le header :

```
Authorization: Bearer your_sanctum_token_here
```

---

## 📝 Notes Importantes

1. **Pagination** : La plupart des listes supportent la pagination avec `page` et `limit`
2. **Filtres** : Beaucoup d'endpoints supportent des filtres via les paramètres de requête
3. **Upload** : Les uploads de fichiers utilisent `multipart/form-data`
4. **Dates** : Toutes les dates sont au format ISO 8601 (UTC)
5. **Content Status** : Seuls les contenus avec `content_status = 'published'` sont visibles publiquement

---

## 🚀 Exemples d'Utilisation

### Créer un post avec médias
```javascript
const formData = new FormData();
formData.append('description', 'Mon nouveau post');
formData.append('content_status', 'published');
formData.append('medias[]', fileInput.files[0]);
formData.append('tags[]', 'nature');

fetch('/api/Y/posts/create', {
    method: 'POST',
    headers: {
        'Authorization': 'Bearer ' + token
    },
    body: formData
});
```

### Rechercher des fandoms
```javascript
fetch('/api/Y/fandoms/search?q=anime&page=1&limit=20')
    .then(response => response.json())
    .then(data => console.log(data));
```

### Rejoindre un fandom
```javascript
fetch('/api/Y/fandoms/123/join', {
    method: 'POST',
    headers: {
        'Authorization': 'Bearer ' + token,
        'Content-Type': 'application/json'
    }
});
```

---

## 📞 Support

Pour toute question ou problème concernant l'API, veuillez contacter l'équipe de développement.

---

*Documentation générée automatiquement - Version 1.0*
