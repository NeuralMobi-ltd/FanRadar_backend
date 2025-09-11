# Documentation API - Endpoints Y/

Cette documentation couvre tous les endpoints API qui commencent par "Y/" dans le système FanRadar.

## Table des matières

1. [Authentification](#authentification)
2. [Profil Utilisateur](#profil-utilisateur)
3. [Gestion des Posts](#gestion-des-posts)
4. [Gestion des Fandoms](#gestion-des-fandoms)
5. [Flux et Feeds](#flux-et-feeds)
6. [Recherche](#recherche)
7. [Catégories et Sous-catégories](#catégories-et-sous-catégories)
8. [Hashtags](#hashtags)
9. [Favoris](#favoris)
10. [Produits](#produits)

---

## 1. Authentification

### POST `/Y/auth/login`
**Description:** Connexion utilisateur avec validation étendue

**Headers:**
- Content-Type: application/json

**Body:**
```json
{
  "email": "user@example.com",
  "password": "password123"
}
```

**Réponse Success (200):**
```json
{
  "message": "Connexion réussie.",
  "user": {
    "id": 1,
    "first_name": "John",
    "last_name": "Doe",
    "email": "user@example.com",
    "profile_image": "url_to_image",
    "background_image": "url_to_image",
    "date_naissance": "1990-01-01",
    "gender": "male",
    "preferred_categories": [1, 3, 5],
    "role": "user",
    "permissions": [],
    "stats": {
      "followers": 10,
      "following": 5,
      "posts": 20
    }
  },
  "token": "sanctum_token"
}
```

### POST `/Y/auth/register`
**Description:** Inscription utilisateur avec vérification OTP

**Headers:**
- Content-Type: application/json

**Body:**
```json
{
  "first_name": "John",
  "last_name": "Doe",
  "email": "user@example.com",
  "password": "password123",
  "date_naissance": "1990-01-01",
  "gender": "male",
  "bio": "Ma bio",
  "preferred_categories": [1, 3, 5]
}
```

**Réponse Success (201):**
```json
{
  "message": "Inscription réussie. Un code OTP a été envoyé à votre email.",
  "email": "user@example.com",
  "next_step": "Vérifiez votre email et utilisez l'API verifyOTP pour confirmer votre inscription."
}
```

---

## 2. Profil Utilisateur

### GET `/Y/users/profile`
**Description:** Récupérer le profil de l'utilisateur connecté

**Headers:**
- Authorization: Bearer {token}

**Réponse Success (200):**
```json
{
  "success": true,
  "user": {
    "id": 1,
    "first_name": "John",
    "last_name": "Doe",
    "email": "user@example.com",
    "profile_image": "url_to_image",
    "background_image": "url_to_image",
    "bio": "Ma bio",
    "stats": {
      "followers": 10,
      "following": 5,
      "posts": 20,
      "fandoms": 3
    }
  }
}
```

### POST `/Y/users/profile`
**Description:** Mettre à jour le profil utilisateur

**Headers:**
- Authorization: Bearer {token}
- Content-Type: multipart/form-data

**Body:**
```json
{
  "first_name": "John Updated",
  "last_name": "Doe Updated",
  "bio": "Nouvelle bio",
  "profile_image": "file",
  "background_image": "file"
}
```

### GET `/Y/users/{userId}/posts`
**Description:** Récupérer les posts d'un utilisateur spécifique

**Headers:**
- Authorization: Bearer {token}

**Paramètres URL:**
- `userId` (integer): ID de l'utilisateur

**Query Parameters:**
- `page` (integer, optional): Numéro de page (défaut: 1)
- `limit` (integer, optional): Nombre d'éléments par page (défaut: 10)

**Réponse Success (200):**
```json
{
  "success": true,
  "data": {
    "user": {
      "id": 1,
      "first_name": "John",
      "last_name": "Doe"
    },
    "posts": [
      {
        "id": 1,
        "description": "Contenu du post",
        "media": ["url1", "url2"],
        "tags": ["tag1", "tag2"],
        "likes_count": 10,
        "comments_count": 5,
        "created_at": "2024-01-01T00:00:00Z"
      }
    ],
    "pagination": {
      "current_page": 1,
      "total_pages": 5,
      "total_items": 50,
      "has_more": true
    }
  }
}
```

### GET `/Y/users/{userId}/profile`
**Description:** Récupérer le profil public d'un utilisateur

**Headers:**
- Authorization: Bearer {token}

**Paramètres URL:**
- `userId` (integer): ID de l'utilisateur

**Réponse Success (200):**
```json
{
  "success": true,
  "data": {
    "user": {
      "id": 1,
      "first_name": "John",
      "last_name": "Doe",
      "email": "john@example.com",
      "profile_image": "https://example.com/storage/users/profiles/profile.jpg",
      "background_image": "https://example.com/storage/users/backgrounds/bg.jpg",
      "bio": "Passionné de technologie et fan de Harry Potter",
      "date_naissance": "1990-05-15",
      "gender": "male",
      "created_at": "2024-01-01T00:00:00Z",
      "is_following": false,
      "is_followed_by": false,
      "stats": {
        "followers": 150,
        "following": 75,
        "posts": 42,
        "fandoms": 8
      }
    }
  }
}
```

### POST `/Y/users/{userId}/follow`
**Description:** Suivre un utilisateur

**Headers:**
- Authorization: Bearer {token}

**Paramètres URL:**
- `userId` (integer): ID de l'utilisateur à suivre

**Réponse Success (201):**
```json
{
  "success": true,
  "message": "User followed successfully"
}
```

### DELETE `/Y/users/{userId}/unfollow`
**Description:** Ne plus suivre un utilisateur

**Headers:**
- Authorization: Bearer {token}

**Paramètres URL:**
- `userId` (integer): ID de l'utilisateur

**Réponse Success (200):**
```json
{
  "success": true,
  "message": "User unfollowed successfully"
}
```

### GET `/Y/users/{userId}/followers`
**Description:** Récupérer la liste des followers d'un utilisateur

**Headers:**
- Authorization: Bearer {token}

**Query Parameters:**
- `page` (integer, optional): Numéro de page
- `limit` (integer, optional): Nombre d'éléments par page

**Réponse Success (200):**
```json
{
  "success": true,
  "data": {
    "user_id": 1,
    "followers": [
      {
        "id": 2,
        "first_name": "Jane",
        "last_name": "Smith",
        "profile_image": "https://example.com/storage/users/profiles/jane.jpg",
        "bio": "Designer graphique",
        "is_following": true,
        "followed_at": "2024-01-15T10:30:00Z"
      },
      {
        "id": 3,
        "first_name": "Mike",
        "last_name": "Johnson",
        "profile_image": "https://example.com/storage/users/profiles/mike.jpg",
        "bio": "Développeur web",
        "is_following": false,
        "followed_at": "2024-01-20T14:45:00Z"
      }
    ],
    "followers_count": 150,
    "pagination": {
      "current_page": 1,
      "total_pages": 15,
      "total_items": 150,
      "per_page": 10,
      "has_more": true
    }
  }
}
```

### GET `/Y/users/{userId}/following`
**Description:** Récupérer la liste des utilisateurs suivis

**Headers:**
- Authorization: Bearer {token}

**Query Parameters:**
- `page` (integer, optional): Numéro de page
- `limit` (integer, optional): Nombre d'éléments par page

**Réponse Success (200):**
```json
{
  "success": true,
  "data": {
    "user_id": 1,
    "following": [
      {
        "id": 4,
        "first_name": "Alice",
        "last_name": "Brown",
        "profile_image": "https://example.com/storage/users/profiles/alice.jpg",
        "bio": "Artiste et illustratrice",
        "is_followed_by": true,
        "following_since": "2024-01-10T09:15:00Z"
      },
      {
        "id": 5,
        "first_name": "Bob",
        "last_name": "Wilson",
        "profile_image": "https://example.com/storage/users/profiles/bob.jpg",
        "bio": "Photographe",
        "is_followed_by": false,
        "following_since": "2024-01-25T16:20:00Z"
      }
    ],
    "following_count": 75,
    "pagination": {
      "current_page": 1,
      "total_pages": 8,
      "total_items": 75,
      "per_page": 10,
      "has_more": true
    }
  }
}
```

---

## 3. Gestion des Posts

### POST `/Y/posts/create`
**Description:** Créer un nouveau post

**Headers:**
- Authorization: Bearer {token}
- Content-Type: multipart/form-data

**Body:**
```json
{
  "description": "Contenu du post",
  "media": ["file1", "file2"],
  "tags": ["tag1", "tag2"],
  "subcategory_id": 1,
  "fandom_id": 1
}
```

**Réponse Success (201):**
```json
{
  "success": true,
  "data": {
    "post": {
      "id": 1,
      "description": "Contenu du post",
      "media": ["url1", "url2"],
      "tags": ["tag1", "tag2"],
      "user": {
        "id": 1,
        "first_name": "John",
        "last_name": "Doe"
      },
      "created_at": "2024-01-01T00:00:00Z"
    }
  }
}
```

### POST `/Y/posts/{postId}/update`
**Description:** Mettre à jour un post existant

**Headers:**
- Authorization: Bearer {token}
- Content-Type: multipart/form-data

**Paramètres URL:**
- `postId` (integer): ID du post

**Body:**
```json
{
  "description": "Nouveau contenu",
  "media": ["file1", "file2"],
  "tags": ["newtag1", "newtag2"]
}
```

### DELETE `/Y/posts/{postId}/delete`
**Description:** Supprimer un post

**Headers:**
- Authorization: Bearer {token}

**Paramètres URL:**
- `postId` (integer): ID du post

**Réponse Success (200):**
```json
{
  "success": true,
  "message": "Post deleted successfully"
}
```

### POST `/Y/posts/{postId}/comments`
**Description:** Ajouter un commentaire à un post

**Headers:**
- Authorization: Bearer {token}
- Content-Type: application/json

**Paramètres URL:**
- `postId` (integer): ID du post

**Body:**
```json
{
  "content": "Mon commentaire"
}
```

**Réponse Success (201):**
```json
{
  "success": true,
  "data": {
    "comment": {
      "id": 15,
      "content": "Mon commentaire",
      "post_id": 1,
      "user": {
        "id": 2,
        "first_name": "Jane",
        "last_name": "Smith",
        "profile_image": "https://example.com/storage/users/profiles/jane.jpg"
      },
      "created_at": "2024-01-01T12:30:00Z",
      "updated_at": "2024-01-01T12:30:00Z"
    }
  }
}
```

### GET `/Y/posts/{postId}/comments`
**Description:** Récupérer les commentaires d'un post

**Headers:**
- Authorization: Bearer {token}

**Query Parameters:**
- `page` (integer, optional): Numéro de page
- `limit` (integer, optional): Nombre de commentaires par page

**Réponse Success (200):**
```json
{
  "success": true,
  "data": {
    "post_id": 1,
    "comments": [
      {
        "id": 1,
        "content": "Super post !",
        "created_at": "2024-01-01T00:00:00Z",
        "user": {
          "id": 2,
          "first_name": "Jane",
          "last_name": "Smith",
          "profile_image": "url_to_image"
        }
      }
    ],
    "comments_count": 10,
    "pagination": {
      "current_page": 1,
      "total_pages": 2,
      "has_more": true
    }
  }
}
```

### POST `/Y/posts/save`
**Description:** Sauvegarder un post

**Headers:**
- Authorization: Bearer {token}
- Content-Type: application/json

**Body:**
```json
{
  "post_id": 1
}
```

**Réponse Success (201):**
```json
{
  "success": true,
  "message": "Post saved successfully",
  "data": {
    "saved_post": {
      "id": 1,
      "post_id": 1,
      "user_id": 2,
      "saved_at": "2024-01-01T12:30:00Z"
    }
  }
}
```

### POST `/Y/posts/unsave`
**Description:** Retirer un post des sauvegardés

**Headers:**
- Authorization: Bearer {token}
- Content-Type: application/json

**Body:**
```json
{
  "post_id": 1
}
```

**Réponse Success (200):**
```json
{
  "success": true,
  "message": "Post removed from saved posts successfully"
}
```

### GET `/Y/posts/savedPosts`
**Description:** Récupérer les posts sauvegardés de l'utilisateur

**Headers:**
- Authorization: Bearer {token}

**Query Parameters:**
- `page` (integer, optional): Numéro de page
- `limit` (integer, optional): Nombre de posts par page

**Réponse Success (200):**
```json
{
  "success": true,
  "data": {
    "saved_posts": [
      {
        "id": 1,
        "description": "Post intéressant que j'ai sauvegardé",
        "media": ["https://example.com/storage/posts/image1.jpg"],
        "tags": ["interesting", "saved"],
        "user": {
          "id": 3,
          "first_name": "Alice",
          "last_name": "Brown",
          "profile_image": "https://example.com/storage/users/profiles/alice.jpg"
        },
        "likes_count": 25,
        "comments_count": 8,
        "saved_at": "2024-01-15T10:30:00Z",
        "created_at": "2024-01-10T09:15:00Z"
      }
    ],
    "pagination": {
      "current_page": 1,
      "total_pages": 5,
      "total_items": 45,
      "per_page": 10,
      "has_more": true
    }
  }
}
```

### GET `/Y/posts/trending/top`
**Description:** Récupérer les posts trending

**Headers:**
- Authorization: Bearer {token}

**Query Parameters:**
- `page` (integer, optional): Numéro de page
- `limit` (integer, optional): Nombre de posts par page
- `days` (integer, optional): Nombre de jours pour le calcul trending (défaut: 7)

**Réponse Success (200):**
```json
{
  "success": true,
  "data": {
    "posts": [
      {
        "id": 1,
        "description": "Post viral du moment",
        "media": ["https://example.com/storage/posts/trending1.jpg"],
        "tags": ["viral", "trending"],
        "user": {
          "id": 5,
          "first_name": "Emma",
          "last_name": "Watson",
          "profile_image": "https://example.com/storage/users/profiles/emma.jpg"
        },
        "likes_count": 1250,
        "comments_count": 89,
        "trend_score": 95,
        "created_at": "2024-01-01T08:00:00Z"
      }
    ],
    "pagination": {
      "page": 1,
      "limit": 20,
      "hasNext": false
    }
  }
}
```

### GET `/Y/categories/{category_id}/posts`
**Description:** Récupérer tous les posts d'une catégorie

**Headers:**
- Authorization: Bearer {token}

**Paramètres URL:**
- `category_id` (integer): ID de la catégorie

**Query Parameters:**
- `page` (integer, optional): Numéro de page
- `limit` (integer, optional): Nombre de posts par page

**Réponse Success (200):**
```json
{
  "success": true,
  "data": {
    "category": {
      "id": 1,
      "name": "Entertainment",
      "description": "Divertissement et culture"
    },
    "subcategories": [
      {
        "id": 1,
        "name": "Movies",
        "description": "Films et cinéma"
      }
    ],
    "posts": [
      {
        "id": 1,
        "description": "Discussion sur le dernier film Marvel",
        "media": ["https://example.com/storage/posts/marvel.jpg"],
        "tags": ["marvel", "movies"],
        "user": {
          "id": 3,
          "first_name": "John",
          "last_name": "Doe",
          "profile_image": "https://example.com/storage/users/profiles/john.jpg"
        },
        "subcategory": {
          "id": 1,
          "name": "Movies",
          "category_id": 1
        },
        "likes_count": 45,
        "comments_count": 12,
        "created_at": "2024-01-01T14:30:00Z"
      }
    ],
    "posts_count": 150,
    "pagination": {
      "current_page": 1,
      "total_pages": 15,
      "total_items": 150,
      "per_page": 10,
      "has_more": true
    }
  }
}
```

---

## 4. Gestion des Fandoms

### GET `/Y/fandoms/{fandom_id}`
**Description:** Récupérer les détails d'un fandom

**Headers:**
- Authorization: Bearer {token}

**Paramètres URL:**
- `fandom_id` (integer): ID du fandom

**Réponse Success (200):**
```json
{
  "success": true,
  "data": {
    "fandom": {
      "id": 1,
      "name": "Harry Potter Fans",
      "description": "Description du fandom",
      "cover_image": "url_to_image",
      "logo_image": "url_to_image",
      "members_count": 150,
      "posts_count": 300,
      "created_at": "2024-01-01T00:00:00Z",
      "is_member": true,
      "member_role": "member"
    }
  }
}
```

### POST `/Y/fandoms/{fandom_id}/join`
**Description:** Rejoindre un fandom

**Headers:**
- Authorization: Bearer {token}

**Paramètres URL:**
- `fandom_id` (integer): ID du fandom

**Réponse Success (201):**
```json
{
  "success": true,
  "message": "Successfully joined the fandom"
}
```

### DELETE `/Y/fandoms/{fandom_id}/leave`
**Description:** Quitter un fandom

**Headers:**
- Authorization: Bearer {token}

**Paramètres URL:**
- `fandom_id` (integer): ID du fandom

### POST `/Y/fandoms`
**Description:** Créer un nouveau fandom

**Headers:**
- Authorization: Bearer {token}
- Content-Type: multipart/form-data

**Body:**
```json
{
  "name": "Nouveau Fandom",
  "description": "Description du fandom",
  "subcategory_id": 1,
  "cover_image": "file",
  "logo_image": "file"
}
```

### POST `/Y/fandoms/{fandom_id}`
**Description:** Mettre à jour un fandom existant

**Headers:**
- Authorization: Bearer {token}
- Content-Type: multipart/form-data

**Paramètres URL:**
- `fandom_id` (integer): ID du fandom

### GET `/Y/users/my-fandoms`
**Description:** Récupérer les fandoms de l'utilisateur connecté

**Headers:**
- Authorization: Bearer {token}

**Query Parameters:**
- `page` (integer, optional): Numéro de page
- `limit` (integer, optional): Nombre de fandoms par page

### PUT `/Y/fandoms/{fandom_id}/members/{user_id}/role`
**Description:** Changer le rôle d'un membre dans un fandom (admin uniquement)

**Headers:**
- Authorization: Bearer {token}
- Content-Type: application/json

**Paramètres URL:**
- `fandom_id` (integer): ID du fandom
- `user_id` (integer): ID de l'utilisateur

**Body:**
```json
{
  "role": "moderator"
}
```

### DELETE `/Y/fandoms/{fandom_id}/members/{user_id}`
**Description:** Supprimer un membre d'un fandom (admin uniquement)

**Headers:**
- Authorization: Bearer {token}

### POST `/Y/fandoms/{fandom_id}/posts`
**Description:** Ajouter un post à un fandom

**Headers:**
- Authorization: Bearer {token}
- Content-Type: multipart/form-data

**Paramètres URL:**
- `fandom_id` (integer): ID du fandom

**Body:**
```json
{
  "description": "Contenu du post",
  "media": ["file1", "file2"],
  "tags": ["tag1", "tag2"]
}
```

### PUT `/Y/fandoms/{fandom_id}/posts/{post_id}`
**Description:** Mettre à jour un post dans un fandom

**Headers:**
- Authorization: Bearer {token}

**Paramètres URL:**
- `fandom_id` (integer): ID du fandom
- `post_id` (integer): ID du post

### DELETE `/Y/fandoms/{fandom_id}/posts/{post_id}`
**Description:** Supprimer un post d'un fandom

**Headers:**
- Authorization: Bearer {token}

### GET `/Y/fandoms`
**Description:** Récupérer la liste des fandoms

**Headers:**
- Authorization: Bearer {token}

**Query Parameters:**
- `page` (integer, optional): Numéro de page
- `limit` (integer, optional): Nombre de fandoms par page
- `category_id` (integer, optional): Filtrer par catégorie

### GET `/Y/fandoms/search`
**Description:** Rechercher des fandoms

**Headers:**
- Authorization: Bearer {token}

**Query Parameters:**
- `q` (string, required): Terme de recherche
- `page` (integer, optional): Numéro de page
- `limit` (integer, optional): Nombre de résultats par page

### GET `/Y/categories/{category_id}/fandoms`
**Description:** Récupérer les fandoms d'une catégorie

**Headers:**
- Authorization: Bearer {token}

**Paramètres URL:**
- `category_id` (integer): ID de la catégorie

### GET `/Y/fandoms/{fandom_id}/posts`
**Description:** Récupérer les posts d'un fandom

**Headers:**
- Authorization: Bearer {token}

**Paramètres URL:**
- `fandom_id` (integer): ID du fandom

**Query Parameters:**
- `page` (integer, optional): Numéro de page
- `limit` (integer, optional): Nombre de posts par page

### GET `/Y/fandoms/{fandom_id}/members`
**Description:** Récupérer les membres d'un fandom

**Headers:**
- Authorization: Bearer {token}

**Paramètres URL:**
- `fandom_id` (integer): ID du fandom

**Query Parameters:**
- `page` (integer, optional): Numéro de page
- `limit` (integer, optional): Nombre de membres par page

### GET `/Y/fandoms/trending/top`
**Description:** Récupérer les fandoms trending

**Headers:**
- Authorization: Bearer {token}

**Query Parameters:**
- `page` (integer, optional): Numéro de page
- `limit` (integer, optional): Nombre de fandoms par page
- `days` (integer, optional): Période pour le calcul trending

---

## 5. Flux et Feeds

### GET `/Y/feed/following`
**Description:** Récupérer le feed des utilisateurs suivis

**Headers:**
- Authorization: Bearer {token}

**Query Parameters:**
- `page` (integer, optional): Numéro de page
- `limit` (integer, optional): Nombre de posts par page

**Réponse Success (200):**
```json
{
  "success": true,
  "data": {
    "posts": [
      {
        "id": 1,
        "description": "Contenu du post",
        "media": ["url1", "url2"],
        "tags": ["tag1", "tag2"],
        "user": {
          "id": 2,
          "first_name": "Jane",
          "last_name": "Smith",
          "profile_image": "url"
        },
        "likes_count": 15,
        "comments_count": 3,
        "created_at": "2024-01-01T00:00:00Z"
      }
    ],
    "pagination": {
      "current_page": 1,
      "has_more": true
    }
  }
}
```

### GET `/Y/feed/home`
**Description:** Récupérer le feed principal personnalisé

**Headers:**
- Authorization: Bearer {token}

**Query Parameters:**
- `page` (integer, optional): Numéro de page
- `limit` (integer, optional): Nombre de posts par page

### GET `/Y/feed/explore`
**Description:** Récupérer le feed d'exploration

**Headers:**
- Authorization: Bearer {token}

**Query Parameters:**
- `page` (integer, optional): Numéro de page
- `limit` (integer, optional): Nombre de posts par page

---

## 6. Recherche

### GET `/Y/search/users`
**Description:** Rechercher des utilisateurs

**Headers:**
- Authorization: Bearer {token}

**Query Parameters:**
- `q` (string, required): Terme de recherche
- `page` (integer, optional): Numéro de page
- `limit` (integer, optional): Nombre de résultats par page

**Réponse Success (200):**
```json
{
  "success": true,
  "data": {
    "users": [
      {
        "id": 1,
        "first_name": "John",
        "last_name": "Doe",
        "email": "john@example.com",
        "profile_image": "url",
        "is_following": false,
        "followers_count": 10
      }
    ],
    "pagination": {
      "current_page": 1,
      "total_pages": 3,
      "total_items": 25,
      "has_more": true
    }
  }
}
```

### GET `/Y/search/posts`
**Description:** Rechercher des posts

**Headers:**
- Authorization: Bearer {token}

**Query Parameters:**
- `q` (string, required): Terme de recherche
- `tags` (string, optional): Tags séparés par virgules
- `subcategory_id` (integer, optional): ID de sous-catégorie
- `page` (integer, optional): Numéro de page
- `limit` (integer, optional): Nombre de résultats par page

### GET `/Y/search/fandom`
**Description:** Rechercher des fandoms

**Headers:**
- Authorization: Bearer {token}

**Query Parameters:**
- `q` (string, required): Terme de recherche
- `page` (integer, optional): Numéro de page
- `limit` (integer, optional): Nombre de résultats par page

---

## 7. Catégories et Sous-catégories

### GET `/Y/categories/{category_id}/subcategories`
**Description:** Récupérer les sous-catégories d'une catégorie

**Paramètres URL:**
- `category_id` (integer): ID de la catégorie

**Réponse Success (200):**
```json
{
  "success": true,
  "data": {
    "category": {
      "id": 1,
      "name": "Entertainment",
      "image": "url_to_image",
      "description": "Description de la catégorie"
    },
    "subcategories": [
      {
        "id": 1,
        "name": "Movies",
        "description": "Films et cinéma"
      },
      {
        "id": 2,
        "name": "TV Shows",
        "description": "Séries télé"
      }
    ]
  }
}
```

### GET `/Y/categories`
**Description:** Récupérer toutes les catégories avec pagination

**Query Parameters:**
- `page` (integer, optional): Numéro de page
- `limit` (integer, optional): Nombre de catégories par page

**Réponse Success (200):**
```json
{
  "success": true,
  "data": {
    "categories": [
      {
        "id": 1,
        "name": "Entertainment",
        "slug": "entertainment",
        "description": "Divertissement",
        "image": "url_to_image",
        "created_at": "2024-01-01T00:00:00Z",
        "updated_at": "2024-01-01T00:00:00Z"
      }
    ],
    "pagination": {
      "current_page": 1,
      "last_page": 5,
      "per_page": 10,
      "total": 50,
      "has_more": true
    }
  }
}
```

### GET `/Y/subcategories/{subcategory}/content`
**Description:** Récupérer le contenu d'une sous-catégorie

**Headers:**
- Authorization: Bearer {token}

**Paramètres URL:**
- `subcategory` (integer): ID de la sous-catégorie

**Query Parameters:**
- `page` (integer, optional): Numéro de page
- `limit` (integer, optional): Nombre d'éléments par page

### GET `/Y/subcategories/{subcategory_id}/fandoms`
**Description:** Récupérer les fandoms d'une sous-catégorie

**Headers:**
- Authorization: Bearer {token}

**Paramètres URL:**
- `subcategory_id` (integer): ID de la sous-catégorie

---

## 8. Hashtags

### GET `/Y/hashtags/trending`
**Description:** Récupérer les hashtags trending

**Headers:**
- Authorization: Bearer {token}

**Query Parameters:**
- `page` (integer, optional): Numéro de page
- `limit` (integer, optional): Nombre de hashtags par page
- `days` (integer, optional): Période pour le calcul trending

**Réponse Success (200):**
```json
{
  "success": true,
  "data": {
    "hashtags": [
      {
        "id": 1,
        "tag_name": "harrypotter",
        "posts_count": 150,
        "growth_percentage": "+25%",
        "category": "Entertainment",
        "trend_score": 95
      }
    ],
    "pagination": {
      "current_page": 1,
      "has_more": true
    }
  }
}
```

### GET `/Y/hashtags/{hashtag_id}/posts`
**Description:** Récupérer les posts d'un hashtag

**Headers:**
- Authorization: Bearer {token}

**Paramètres URL:**
- `hashtag_id` (integer): ID du hashtag

**Query Parameters:**
- `page` (integer, optional): Numéro de page
- `limit` (integer, optional): Nombre de posts par page

**Réponse Success (200):**
```json
{
  "success": true,
  "data": {
    "hashtag": {
      "id": 1,
      "tag_name": "harrypotter",
      "posts_count": 150
    },
    "posts": [
      {
        "id": 1,
        "description": "Mon post Harry Potter",
        "media": ["url1"],
        "user": {
          "id": 1,
          "first_name": "John",
          "profile_image": "url"
        },
        "likes_count": 10,
        "comments_count": 5,
        "created_at": "2024-01-01T00:00:00Z"
      }
    ],
    "stats": {
      "totalPosts": 150,
      "growth": "+25%",
      "category": "Entertainment"
    }
  }
}
```

---

## 9. Favoris

### POST `/Y/posts/{postId}/favorite`
**Description:** Ajouter un post aux favoris

**Headers:**
- Authorization: Bearer {token}

**Paramètres URL:**
- `postId` (integer): ID du post

**Réponse Success (201):**
```json
{
  "success": true,
  "message": "Le post a été ajouté aux favoris avec succès."
}
```

### DELETE `/Y/posts/{postId}/removefavorite`
**Description:** Retirer un post des favoris

**Headers:**
- Authorization: Bearer {token}

**Paramètres URL:**
- `postId` (integer): ID du post

### POST `/Y/favorites/{pProductId}/favorite`
**Description:** Ajouter un produit aux favoris

**Headers:**
- Authorization: Bearer {token}

**Paramètres URL:**
- `pProductId` (integer): ID du produit

### DELETE `/Y/favorites/{pProductId}/removefavorite`
**Description:** Retirer un produit des favoris

**Headers:**
- Authorization: Bearer {token}

**Paramètres URL:**
- `pProductId` (integer): ID du produit

### GET `/Y/myfavorites/posts`
**Description:** Récupérer tous les posts favoris de l'utilisateur

**Headers:**
- Authorization: Bearer {token}

**Query Parameters:**
- `page` (integer, optional): Numéro de page
- `limit` (integer, optional): Nombre de posts par page

**Réponse Success (200):**
```json
{
  "success": true,
  "data": {
    "posts": [
      {
        "id": 1,
        "description": "Contenu du post",
        "media": ["url1", "url2"],
        "tags": ["tag1", "tag2"],
        "user": {
          "id": 2,
          "first_name": "Jane",
          "profile_image": "url"
        },
        "likes_count": 15,
        "comments_count": 3,
        "favorited_at": "2024-01-01T00:00:00Z"
      }
    ],
    "pagination": {
      "current_page": 1,
      "total_pages": 5,
      "total_items": 50,
      "has_more": true
    }
  }
}
```

### GET `/Y/myfavorites/products`
**Description:** Récupérer tous les produits favoris de l'utilisateur

**Headers:**
- Authorization: Bearer {token}

**Query Parameters:**
- `page` (integer, optional): Numéro de page
- `limit` (integer, optional): Nombre de produits par page

---

## 10. Produits

### GET `/Y/products/drag`
**Description:** Récupérer les produits en édition limitée (drag products)

**Headers:**
- Authorization: Bearer {token}

**Query Parameters:**
- `page` (integer, optional): Numéro de page
- `limit` (integer, optional): Nombre de produits par page
- `status` (string, optional): Statut des produits ('upcoming', 'active', 'expired', 'all')

**Réponse Success (200):**
```json
{
  "success": true,
  "data": {
    "products": [
      {
        "id": 1,
        "product_name": "Limited Edition T-Shirt",
        "description": "T-shirt en édition limitée",
        "price": 45.99,
        "original_price": 55.99,
        "promotion": 18,
        "stock": 50,
        "stock_percentage": 5,
        "sale_start_date": "2024-01-01T00:00:00Z",
        "sale_end_date": "2024-01-31T23:59:59Z",
        "status": "active",
        "time_remaining_days": 15,
        "is_limited": true,
        "urgency_level": "medium",
        "subcategory": {
          "id": 1,
          "name": "Clothing"
        },
        "media": [
          {
            "id": 1,
            "file_path": "url_to_image",
            "media_type": "image"
          }
        ],
        "tags": ["limited", "fashion"],
        "average_rating": 4.5,
        "ratings_count": 20,
        "favorites_count": 5,
        "created_at": "2024-01-01T00:00:00Z"
      }
    ],
    "statistics": {
      "total_drag_products": 100,
      "active_products": 25,
      "upcoming_products": 10,
      "expired_products": 65
    },
    "pagination": {
      "current_page": 1,
      "last_page": 10,
      "per_page": 10,
      "total": 100,
      "has_more": true
    }
  }
}
```

---

## Codes d'erreur communs

### 400 - Bad Request
```json
{
  "success": false,
  "error": "Invalid request parameters"
}
```

### 401 - Unauthorized
```json
{
  "success": false,
  "error": "Authentication required"
}
```

### 403 - Forbidden
```json
{
  "success": false,
  "error": "Access denied"
}
```

### 404 - Not Found
```json
{
  "success": false,
  "error": "Resource not found"
}
```

### 422 - Validation Error
```json
{
  "success": false,
  "errors": {
    "email": ["The email field is required."],
    "password": ["The password must be at least 6 characters."]
  }
}
```

### 500 - Internal Server Error
```json
{
  "success": false,
  "error": "Internal server error"
}
```

---

## Notes importantes

1. **Authentification:** La plupart des endpoints nécessitent un token d'authentification via Sanctum
2. **Pagination:** La plupart des endpoints de liste supportent la pagination avec `page` et `limit`
3. **Upload de fichiers:** Les endpoints d'upload utilisent `multipart/form-data`
4. **Formats de date:** Toutes les dates sont au format ISO 8601 (YYYY-MM-DDTHH:MM:SSZ)
5. **Validation:** Tous les endpoints valident les données d'entrée et retournent des erreurs détaillées
6. **Rate Limiting:** Les endpoints peuvent être soumis à une limitation de débit

Cette documentation couvre tous les endpoints API commençant par "Y/" et fournit des exemples détaillés pour chaque utilisation.
