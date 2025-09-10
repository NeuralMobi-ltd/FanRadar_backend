# 📊 Diagramme de Flow - Système d'Authentication et Récupération de Mot de Passe

## 🔐 **PROCESSUS 1 : INSCRIPTION ET VÉRIFICATION**

```
┌─────────────────┐
│  📝 REGISTER    │
│ /Y/auth/register│
└─────────┬───────┘
          │
          ▼
┌─────────────────┐
│ • Valider data  │
│ • Vérifier email│
│ • Supprimer si  │
│   non vérifié   │
│ • Créer compte  │
│ • is_verified=❌│
│ • Générer OTP   │
│ • Envoyer email │
└─────────┬───────┘
          │
          ▼
┌─────────────────┐
│ 📧 EMAIL SENT   │
│ "Récupérer OTP" │
└─────────┬───────┘
          │
          ▼
┌─────────────────┐
│ ✅ VERIFY       │
│ /verifyregister │
└─────────┬───────┘
          │
          ▼
┌─────────────────┐
│ • Valider OTP   │
│ • Vérifier délai│
│ • is_verified=✅│
│ • Nettoyer OTP  │
└─────────┬───────┘
          │
          ▼
┌─────────────────┐
│ 🎉 COMPTE       │
│    ACTIVÉ       │
└─────────────────┘
```

## 🔑 **PROCESSUS 2 : RÉCUPÉRATION DE MOT DE PASSE**

```
┌─────────────────┐
│ 🔐 FORGET PWD   │
│ /forgetPassword │
└─────────┬───────┘
          │
          ▼
┌─────────────────┐
│ • Valider email │
│ • Vérifier user │
│ • Générer OTP   │
│ • Sauver OTP    │
│ • Envoyer email │
└─────────┬───────┘
          │
          ▼
┌─────────────────┐
│ 📧 EMAIL SENT   │
│ "OTP pour reset"│ or --------
└─────────┬───────┘            │
          │                    │
          ▼                    │
┌─────────────────┐            │
│ ✅ VERIFY OTP   │            │
│/verifyOTPforget-│            │
│   Password      │            │
└─────────┬───────┘            │
          │                    │
          ▼                    │
┌─────────────────┐            │
│ • Valider OTP   │            │
│ • Vérifier délai│<-----------
│ • Confirmer     │
│   validité      │
└─────────┬───────┘
          │
          ▼
┌─────────────────┐
│ 🔄 RESET PWD    │
│ /resetPassword  │
└─────────┬───────┘
          │
          ▼
┌─────────────────┐
│ • Valider OTP   │
│ • Nouveau pwd   │
│ • Confirmer pwd │
│ • Sauvegarder   │
│ • Nettoyer OTP  │
└─────────┬───────┘
          │
          ▼
┌─────────────────┐
│ 🎉 MOT DE PASSE │
│    RÉINITIALISÉ │
└─────────────────┘
```

## 📋 **RÉSUMÉ DES ROUTES ET FONCTIONS**

### **🔹 Processus d'Inscription**
| Route | Méthode | Fonction | Description |
|-------|---------|----------|-------------|
| `/Y/auth/register` | POST | `register()` | Création du compte + envoi OTP |
| `/verifyregister` | POST | `verifyregister()` | Vérification OTP + activation compte |

### **🔹 Processus de Récupération de Mot de Passe**
| Route | Méthode | Fonction | Description |
|-------|---------|----------|-------------|
| `/forgetPassword` | POST | `forgetPassword()` | Demande de reset + envoi OTP |
| `/verifyOTPforgetPassword` | POST | `verifyOTPforgetPassword()` | Vérification OTP pour reset |
| `/resetPassword` | POST | `resetPassword()` | Réinitialisation du mot de passe |

## ⚡ **CARACTÉRISTIQUES CLÉS**

### **🔒 Sécurité**
- ✅ OTP à 6 chiffres généré aléatoirement
- ✅ Expiration automatique après 10 minutes
- ✅ Suppression automatique des comptes non vérifiés
- ✅ Nettoyage des OTP après utilisation
- ✅ Validation stricte des données d'entrée

### **🛡️ Gestion des Comptes Existants**
- ✅ Si email existe + compte vérifié → **Erreur**
- ✅ Si email existe + compte non vérifié → **Suppression et recréation**
- ✅ Protection contre les inscriptions multiples

### **📧 Notifications Email**
- ✅ Email d'OTP pour inscription
- ✅ Email d'OTP pour récupération de mot de passe
- ✅ Messages clairs et instructions

### **⏱️ Gestion du Temps**
- ✅ Délai d'expiration : 10 minutes
- ✅ Vérification automatique de l'expiration
- ✅ Suppression des données expirées

## 🚨 **GESTION D'ERREURS**

### **Erreurs Possibles - Inscription**
- ❌ Email déjà utilisé (compte vérifié)
- ❌ Données de validation invalides
- ❌ OTP invalide ou expiré

### **Erreurs Possibles - Reset Password**
- ❌ Email non trouvé
- ❌ OTP invalide ou expiré
- ❌ Mots de passe non conformes

## 📱 **EXEMPLE D'UTILISATION**

### **Inscription Complète**
```bash
# 1. Inscription
POST /Y/auth/register
{
  "first_name": "John",
  "last_name": "Doe", 
  "email": "john@example.com",
  "password": "password123"
}

# 2. Vérification (après réception email)
POST /verifyregister
{
  "email": "john@example.com",
  "otp": "123456"
}
```

### **Récupération Mot de Passe**
```bash
# 1. Demande de reset
POST /forgetPassword
{
  "email": "john@example.com"
}

# 2. Vérification OTP
POST /verifyOTPforgetPassword
{
  "email": "john@example.com",
  "otp": "654321"
}

# 3. Nouveau mot de passe
POST /resetPassword
{
  "email": "john@example.com",
  "otp": "654321",
  "password": "newpassword123",
  "password_confirmation": "newpassword123"
}
```

---

**📝 Note :** Ce diagramme représente le flow actuel du système d'authentication de FanRadar Backend.  
**📅 Dernière mise à jour :** 10 septembre 2025
