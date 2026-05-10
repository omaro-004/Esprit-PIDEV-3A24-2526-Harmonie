# IA - Generation d'image et d'avatar (photo de profil)

## Objectif
Cette fonctionnalite permet a un utilisateur de choisir sa photo de profil de trois manieres :
1) avatar par defaut, 2) import d'une image, 3) generation d'un avatar par IA.

## IA utilisee
- **Pollinations.AI** (https://image.pollinations.ai/) pour la generation d'avatar via prompt.
- Aucun compte ni cle API n'est necessaire pour Pollinations.AI.

> Note: Une autre IA existe dans le projet (Hugging Face / FLUX) pour les images de cours, pas pour la photo de profil.

## Parcours utilisateur (resume)
1) L'utilisateur saisit un prompt dans l'onglet "Generer par IA".
2) Le front genere une URL Pollinations a partir du prompt.
3) Le front appelle l'API interne `/api/avatar/generate-from-url` avec cette URL.
4) Le backend telecharge l'image distante, la verifie, puis la sauvegarde dans `public/user_images/`.
5) Le front affiche l'avatar genere et permet de l'utiliser.
6) A l'enregistrement du formulaire, le chemin est stocke dans `user.userImagePath`.

## Endpoints API
### POST `/api/avatar/generate-from-url`
- **But**: telecharger l'image IA depuis Pollinations et la stocker localement.
- **Entree JSON**:
  - `imageUrl` (string) : URL Pollinations generee par le front.
- **Regles**:
  - URL doit commencer par `https://image.pollinations.ai/`.
  - Le `Content-Type` doit etre de type `image/*`.
- **Sortie**:
  - `{ success: true, imagePath: "user_images/ai_avatar_xxx.png" }` en succes.

### POST `/api/avatar/set-default`
- **But**: selectionner un avatar par defaut.
- **Entree JSON**:
  - `avatarSlug` (string) : `avatar_01` a `avatar_12`.
- **Sortie**:
  - `{ success: true, imagePath: "avatars/default/avatar_XX.svg" }`.

## Fichiers et fonctions impliques
### Backend
- `src/Controller/AvatarController.php`
  - `generateFromUrl()` : telecharge l'image IA et la sauvegarde localement.
  - `setDefault()` : renvoie le chemin d'un avatar par defaut.

- `src/Controller/ProfileController.php`
  - `settings()` : applique `preSelectedAvatarPath` (avatar default ou IA) et gere l'upload local.

- `src/Controller/RegistrationController.php`
  - `registerStep2()` : meme logique avatar que le profil (selection/IA/upload).

- `src/Form/ProfileFormType.php`
  - champ `avatarFile` : upload, contraintes mime + taille 2 Mo.

- `src/Form/RegistrationStep2FormType.php`
  - champ `avatarFile` : upload, contraintes mime + taille 2 Mo.

- `src/Entity/User.php`
  - `userImagePath` : chemin de la photo de profil stocke en base.

### Frontend (Twig + JS inline)
- `templates/profile/settings.html.twig`
  - UI des 3 onglets (defaut / upload / IA).
  - JS: generation URL Pollinations, appel API, preview, selection finale.

- `templates/registration/register_step2.html.twig`
  - Meme UI et logique JS pour l'inscription.

### Stockage
- Avatars par defaut: `public/avatars/default/avatar_01.svg` ... `avatar_12.svg`.
- Avatars IA et uploads: `public/user_images/`.

## Regles de validation importantes
- Upload local limite a 2 Mo.
- Types acceptes: JPG, PNG, WebP, GIF.
- Les chemins acceptes pour selection sans upload:
  - `avatars/default/avatar_XX.svg`
  - `user_images/ai_avatar_*.png`

## Cle d'API / secrets
- **Aucune cle pour Pollinations.AI.**
- Le projet contient un service Hugging Face (non utilise pour l'avatar) qui requiert une cle.
  - Recommandation: deplacer la cle vers une variable d'environnement (ex: `HUGGINGFACE_API_KEY`) et ne jamais la committer.

## Points d'attention / securite
- L'API `generate-from-url` n'accepte que le domaine Pollinations pour eviter les telechargements arbitraires.
- Les fichiers generes sont stockes localement dans `public/user_images`.
- Pensez a purger les anciens avatars si necessaire (gestion du stockage).

## Tests manuels conseilles
1) Generer un avatar IA et le selectionner.
2) Uploader un PNG > 2 Mo (doit etre refuse).
3) Selectionner un avatar par defaut et sauvegarder.
4) Recharger la page profil et verifier l'affichage.
