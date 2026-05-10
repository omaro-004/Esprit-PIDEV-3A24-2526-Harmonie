# Harmonie — Plateforme de Gestion d'Équipe

## Description du projet

Harmonie est une application web Symfony dédiée à la gestion d'équipe, du planning et de la vie quotidienne du projet.
Elle regroupe plusieurs briques métier :

- 📅 planification d'événements, de tâches et de salles
- 🛡️ authentification sécurisée avec mot de passe, OAuth, Face ID, CAPTCHA et 2FA/TOTP
- 🧾 inscription multi-étapes avec capture de données personnelles, physiques et visuelles
- 🧠 modération et assistance IA pour le forum, les contenus et certains exports
- 🥗 nutrition, journal alimentaire et analyse photo
- 🏋️ activités sportives, statistiques et bilans PDF
- 📚 bibliothèque de cours avec fichiers, sauvegardes, signalements et suggestions
- 📝 journal d’humeur et méditation
- 💬 messagerie temps réel et notifications
- 🧑‍💼 tableaux de bord et back-office d’administration

L’architecture suit le modèle Symfony classique avec des contrôleurs orientés modules, des entités Doctrine, des services métier spécialisés, des formulaires dédiés et des vues Twig organisées par domaine.

## Stack Technique

| Domaine | Technologies détectées |
|---|---|
| Langage | PHP 8.2+ |
| Framework | Symfony 6.4 |
| ORM / Migrations | Doctrine ORM, Doctrine Migrations |
| Base de données | Doctrine DBAL + MariaDB 10.4.32 côté config |
| Templates | Twig, Twig Extra Bundle |
| Formulaires / Validation | Symfony Form, Validator, Property Access, Property Info |
| Sécurité | Security Bundle, OAuth2 Client, Google/Facebook OAuth |
| Temps réel | Mercure, Turbo, Messenger |
| UI / Assets | Asset Mapper, Stimulus Bundle, UX Turbo, UX Chart.js |
| Notifications | Mailer, Notifier |
| PDF / Exports | Dompdf, Knp Snappy, PhpSpreadsheet, Smalot PDF Parser |
| Pagination | Knp Paginator |
| APIs externes | Google API Client, Spoonacular, Groq, Mistral, YouTube, GitHub |
| Outils qualité | PHPUnit, PHPStan, Doctrine Doctor |

Fichiers de configuration principaux : `composer.json`, `config/routes.yaml`, `config/packages/doctrine.yaml`, `phpunit.dist.xml`, `phpstan.dist.neon`.

## Modules & Fonctionnalités

### 1) 👤 Utilisateurs, authentification et sécurité

**Objectif :** sécuriser l’accès à la plateforme avec plusieurs modes d’authentification et de protection.

#### Contrôleurs et routes

- **`HomepageController`**
  - `/` — `GET`
  - Redirige les utilisateurs administrateurs vers le tableau de bord admin, sinon affiche la page d’accueil.
  - Vue : `templates/homepage/index.html.twig`

- **`SecurityController`**
  - `/login` — `GET`/`POST`
  - `/logout` — selon la configuration de sécurité
  - Gère la connexion classique avec validation du CAPTCHA et authentification Symfony.
  - Vue : `templates/security/login.html.twig`

- **`RegistrationController`**
  - `/register` — étape 1 de l’inscription
  - `/register/step2` — étape 2
  - `/register/step3` — étape 3
  - `/register/save-face` — `POST`
  - `/register/skip-face` — `POST`
  - Inscription multi-étapes avec persistance progressive des données.
  - Vues : `templates/registration/register_step1.html.twig`, `register_step2.html.twig`, `register_step3.html.twig`

- **`PasswordResetController`**
  - `/forgot-password` — `GET`/`POST`
  - `/forgot-password/verify` — `POST`
  - `/forgot-password/reset` — `GET`/`POST`
  - Réinitialisation du mot de passe par e-mail avec lien sécurisé, expiration et confirmation.
  - Vue : `templates/security/forgot_password.html.twig`

- **`OAuthController`**
  - `/connect/google` — connexion Google
  - `/connect/google/check` — retour OAuth Google
  - `/connect/facebook` — connexion Facebook
  - `/connect/facebook/check` — retour OAuth Facebook
  - Permet la connexion via fournisseurs externes.

- **`FaceAuthController`**
  - `/face-verify` — affichage de la vérification faciale
  - `/face-verify/check` — `POST`
  - `/face-verify/success` — `POST`
  - `/profile/toggle-faceid` — `POST`
  - `/profile/update-face` — `POST`
  - Active, désactive et met à jour le Face ID côté profil et login.
  - Vue : `templates/security/face_verify.html.twig`

- **`ProfileController`**
  - `/profile` — redirection vers les paramètres
  - `/profile/settings` — `GET`/`POST`
  - `/profile/security` — `GET`/`POST`
  - `/profile/edit` — `GET`/`POST`
  - `/profile/2fa` — `GET`
  - Gère les informations personnelles, la photo de profil, les réglages sécurité, le changement d’e-mail et de mot de passe.
  - Vues : `templates/profile/index.html.twig`, `settings.html.twig`, `edit.html.twig`

- **`AvatarController`**
  - `/api/avatar/set-default` — `POST`
  - `/api/avatar/generate-from-url` — `POST`
  - Sert l’upload, la génération et la sélection de photo de profil.

- **`AdminUserController`**
  - `/admin/users` — `GET`
  - `/admin/users/search` — recherche
  - `/admin/users/suspended` — liste des comptes suspendus
  - `/admin/users/{id}` — consultation
  - `/admin/users/{id}/edit` — modification
  - `/admin/users/{id}/toggle` — `POST`
  - `/admin/users/{id}/suspicion` — score de suspicion
  - Administration des utilisateurs et gestion de l’état des comptes.
  - Vues : `templates/admin/users/*`

- **`MockUserController`**
  - `/dev/mock-user/list` — `GET`
  - `/dev/mock-user/set` — `POST`
  - `/dev/mock-user/current` — `GET`
  - Utilitaire de développement pour simuler l’utilisateur courant.

#### Entités, repositories et services liés

- Entité principale : `User`
- Repositories : `UserRepository`
- Services : `CaptchaService`, `TotpService`, `SuspicionScoreService`, `FaceAuthListener`, `CaptchaListener`
- Intégrations : Google OAuth, Facebook OAuth, e-mail de reset, Face ID, 2FA/TOTP

#### Règles métier visibles

- mot de passe hashé et vérifié à chaque connexion
- validation stricte des données d’inscription et de profil
- session persistante et redirections sécurisées
- activation/désactivation du Face ID depuis le profil
- score de suspicion calculé pour l’administration

---

### 2) 📅 Planning, calendrier, événements, tâches et salles

**Objectif :** centraliser la planification, les rappels, les exports et la synchronisation externe.

#### Contrôleurs et routes

- **`TachesController`**
  - `/tache` — `GET`
  - `/tache/index` — `GET`
  - `/tache/export/csv` — `GET`
  - `/tache/export/pdf` — `POST`
  - `/tache/export/excel` — `POST`
  - `/tache/{id}/statut` — `POST`
  - `/tache/new` — `GET`/`POST`
  - `/tache/{id}` — `GET`
  - `/tache/{id}/edit` — `GET`/`POST`
  - `/tache/{id}` — `POST` suppression
  - Fonctions : liste, création, détail, édition, suppression, changement de statut, export multi-format.
  - Vue : `templates/tache/*`, `templates/taches/index.html.twig`

- **`EvenementsController`**
  - `/evenement` — `GET`
  - `/evenement/index` — `GET`
  - `/evenement/salles-api` — récupération des salles
  - `/evenement/export/csv` — `GET`
  - `/evenement/export/pdf` — `POST`
  - `/evenement/export/excel` — `POST`
  - `/evenement/new` — `GET`/`POST`
  - `/evenement/{id}` — `GET`
  - `/evenement/{id}/edit` — `GET`/`POST`
  - `/evenement/{id}` — `POST` suppression
  - Gestion des événements, de leurs dates, lieux, salles et exports.
  - Vues : `templates/evenement/*`, `templates/evenements/index.html.twig`

- **`SalleController`**
  - CRUD public des salles
  - Liste, création, consultation, édition, suppression
  - Vues : `templates/salle/*`

- **`GoogleCalendarController`**
  - `/oauth/google/connect` — `GET`
  - `/oauth/google/callback` — `GET`
  - `/oauth/google/disconnect` — `GET`
  - `/oauth/google/pull` — `GET`
  - `/webhook/google-calendar` — `POST`
  - Synchronisation avec Google Calendar, webhook de réception et import des événements.

- **`AdminTacheController`**
  - `/admin/taches` — `GET`
  - `/admin/taches/new` — `GET`/`POST`
  - `/admin/taches/{id}` — `GET`
  - `/admin/taches/{id}/edit` — `GET`/`POST`
  - `/admin/taches/{id}` — `POST` suppression
  - Administration du planning des tâches.
  - Vues : `templates/admin/tache/*`

- **`AdminEvenementController`**
  - `/admin/evenements` — `GET`
  - `/admin/evenements/new` — `GET`/`POST`
  - `/admin/evenements/{id}` — `GET`
  - `/admin/evenements/{id}/edit` — `GET`/`POST`
  - `/admin/evenements/{id}` — `POST` suppression
  - Administration des événements avec validation métier centralisée.
  - Vues : `templates/admin/evenement/*`

- **`AdminSalleController`**
  - `/admin/salles` — `GET`
  - `/admin/salles/new` — `GET`/`POST`
  - `/admin/salles/{id}/edit` — `GET`/`POST`
  - `/admin/salles/{id}/toggle` — `POST`
  - `/admin/salles/{id}` — `POST` suppression
  - Administration des salles et de leur disponibilité.
  - Vues : `templates/admin/salle/*`

- **`AdminDemandeController`**
  - `/admin/demandes` — `GET`
  - `/admin/demandes/{id}/accepter` — `POST`
  - `/admin/demandes/{id}/refuser` — `POST`
  - Gestion des demandes de réservation et décision d’acceptation/refus.
  - Vue : `templates/admin/demande/index.html.twig`

- **`Api/EventApiController`**
  - `/api/events` — `GET`, `POST`
  - `/api/events/{id}` — `PUT`, `DELETE`
  - API CRUD des événements pour front ou synchronisation.

- **`Api/TaskApiController`**
  - `/api/tasks` — `GET`, `POST`
  - `/api/tasks/{id}` — `PUT`, `DELETE`
  - API CRUD des tâches.

- **`Api/KanbanStreamController`**
  - `/api/kanban/stream` — `GET`
  - Flux SSE pour mise à jour temps réel du tableau Kanban.

- **`Api/GithubSettingsController`**
  - `/api/github/settings` — `GET`, `PUT`
  - `/api/github/settings/sync-doing` — `POST`
  - Paramétrage GitHub et synchronisation des tâches en cours.

- **`Api/GithubWebhookController`**
  - `/api/webhooks/github` — `POST`
  - Réception des webhooks GitHub.

#### Entités, repositories et services liés

- Entités : `Tache`, `Evenement`, `Calendrier`, `Salle`, `DemandeReservation`
- Repositories : `TacheRepository`, `EvenementRepository`, `CalendrierRepository`, `SalleRepository`, `DemandeReservationRepository`
- Services : `PlanningDomainService`, `CalendarExportService`, `KanbanExportService`, `GoogleCalendarService`, `TelegramNotifier`, `EventReminderService`, `GithubIssueService`, `KanbanRealtimeNotifier`

#### Règles métier visibles

- validation des heures, dates, lieux et salles
- export CSV / PDF / Excel
- synchronisation Google Calendar
- notifications Telegram et flux temps réel
- gestion d’un calendrier central pour événements et tâches

---

### 3) 🥗 Nutrition et suivi alimentaire

**Objectif :** suivre les objectifs nutritionnels, les apports et les recettes.

#### Contrôleurs et routes

- **`NutritionController`**
  - `/nutrition`
  - `/nutrition/ajouter`
  - `/nutrition/recettes`
  - Endpoints API nutrition : objectifs, BMR, analyse photo, recettes, journal alimentaire, gestion des aliments et consommations.
  - Vues : `templates/nutrition/*`

- **`AdminNutritionController`**
  - `/admin/nutrition` — `GET`
  - `/admin/nutrition/export` — `GET`
  - `/admin/nutrition/api/list` — `GET`
  - `/admin/nutrition/api/create` — `POST`
  - `/admin/nutrition/api/update/{id}` — `POST`
  - `/admin/nutrition/api/delete/{id}` — `POST`
  - Administration du catalogue nutritionnel et export Excel.
  - Vue : `templates/admin/nutrition.html.twig`

#### Entités, repositories et services liés

- Entités : `Aliment`, `Consommation`
- Repositories : `AlimentRepository`, `ConsommationRepository`
- Services : `SpoonacularService`, `GeminiVisionService`, `AlimentManager`

#### Règles métier visibles

- calcul d’objectifs nutritionnels
- calcul du BMR/profil
- analyse d’image pour les repas
- ajout, modification et suppression d’aliments/consommations
- export des données nutritionnelles

---

### 4) 🏋️ Activités sportives et bien-être physique

**Objectif :** gérer les séances d’activités, le suivi statistique et les contenus sportifs.

#### Contrôleurs et routes

- **`ActivitesController`**
  - `/activites` — `GET`
  - `/activites/api/list` — `GET`
  - `/activites/api/add` — `POST`
  - `/activites/api/update/{id}` — `PUT`/`POST`
  - `/activites/api/delete/{id}` — `DELETE`/`POST`
  - `/activites/qr/{date}` — `GET`
  - `/activites/bilan/pdf` — `GET`
  - Vue : `templates/activites/index.html.twig`, `templates/activites/bilan_pdf.html.twig`

- **`AdminSportController`**
  - `/admin/sport` — `GET`
  - `/admin/sport/api/list` — `GET`
  - `/admin/sport/api/types` — `GET`
  - `/admin/sport/api/stats` — `GET`
  - `/admin/sport/api/create` — `POST`
  - `/admin/sport/api/update/{id}` — `POST`
  - `/admin/sport/api/delete/{id}` — `POST`
  - Administration des exercices et des sessions sportives.
  - Vue : `templates/admin/sport.html.twig`

- **`AdminSportYouTubeController`**
  - `/admin/sport/api/youtube-search` — `GET`
  - Recherche de vidéos sportives via YouTube.

- **`BlessureController`**
  - `/blessure` — `GET`
  - `/blessure/conseil` — `POST`
  - Conseils de récupération liés à une blessure.
  - Vue : `templates/blessure/index.html.twig`

#### Entités, repositories et services liés

- Entités : `Activite`, `Exercice`
- Repositories : `ActiviteRepository`, `ExerciceRepository`
- Services : `ActiviteManager`, `ExerciceStatsService`, `YouTubeService`, `QrCodeService`, `BlessureService`

#### Règles métier visibles

- statistiques sportives par exercice et par période
- export/bilan PDF
- génération de QR code
- suggestions vidéo et conseils de récupération

---

### 5) 💬 Forum, modération et assistance IA

**Objectif :** gérer un espace d’échanges enrichi par des outils IA et de modération.

#### Contrôleurs et routes

- **`ForumController`**
  - routes forum pour catégories, posts, commentaires, likes, édition, suppression et génération de contenu assisté
  - fact-check, fact-check chat, suggestion de réponse, sentiment, résumé, génération d’image, correction orthographique, traduction
  - Vues : `templates/forum/*`

- **`BackController`**
  - `/back` — tableau de bord back-office
  - `/back/categories` — `GET`
  - `/back/categories/new` — `GET`/`POST`
  - `/back/categories/{id}/edit` — `GET`/`POST`
  - `/back/categories/{id}/delete` — `POST`
  - `/back/posts` — `GET`
  - `/back/posts/{id}/delete` — `POST`
  - `/back/commentaires` — `GET`
  - `/back/commentaires/{id}/delete` — `POST`
  - Modération et administration du forum.
  - Vues : `templates/back/*`

- **`Api/ChatApiController`**
  - `/api/chat` — `POST`
  - Assistant conversationnel orienté planification/organisation.

#### Entités, repositories et services liés

- Entités : `Categorie`, `Post`, `Commentaire`, `Reaction`
- Repositories : `CategorieRepository`, `PostRepository`, `CommentaireRepository`, `ReactionRepository`
- Services : `ModerationService`, `FactCheckService`, `TranslationService`, `SpellCheckService`, `SummaryService`, `SentimentService`, `MistralService`, `ImageGen`, `PostValidator`

#### Règles métier visibles

- validation du contenu des posts/commentaires
- détection de contenu problématique
- traduction et correction automatiques
- génération de réponses et résumés

---

### 6) 📚 Bibliothèque et gestion de cours

**Objectif :** proposer un espace de cours avec fichiers, pages détaillées, publications et signalements.

#### Contrôleurs et routes

- **`LibraryController`** (racine)
  - `/library` — `GET`
  - Accès à la bibliothèque principale.
  - Vue : `templates/library/library.html.twig`

- **`LibraryControllers/LibraryController`**
  - version dédiée de la bibliothèque sous le même domaine fonctionnel
  - Vue : `templates/library/index.html.twig`

- **`LibraryControllers/CoursesController`**
  - `/courses` — listing
  - création / mise à jour / upload de couverture / publication / suppression des cours
  - Vue : `templates/library/courses.html.twig`

- **`LibraryControllers/CourseDetailsController`**
  - vue détaillée d’un cours et actions avancées sur le contenu, les fichiers, les notes, les exports et les suggestions IA
  - Vue : `templates/library/course-details.html.twig`

- **`AdminCoursesController`**
  - `/admin/courses` — `GET`
  - `/admin/courses/unpublish/{courseId}` — `POST`
  - `/admin/courses/view/{courseId}` — `GET`
  - Administration de la visibilité et de la consultation des cours.
  - Vues : `templates/admin/library/courses.html.twig`, `course-view.html.twig`

- **`AdminCourseReportsController`**
  - `/admin/course-reports` — `GET`
  - `/admin/course-reports/unpublish/{courseId}` — `POST`
  - `/admin/course-reports/dismiss/{reportId}` — `POST`
  - `/admin/course-reports/restore/{courseId}` — `POST`
  - Gestion des signalements de cours.
  - Vue : `templates/admin/library/course-reports.html.twig`

#### Entités, repositories et services liés

- Entités : `Subject`, `Course`, `CourseFile`, `SavedCourse`, `CourseReport`
- Repositories : `SubjectRepository`, `CourseRepository`, `CourseFileRepository`, `SavedCourseRepository`, `CourseReportRepository`
- Services : `CourseManager`, `SuggestionsService`, `GeminiService` (bibliothèque), `YouTubeService`

#### Règles métier visibles

- sauvegarde de cours par utilisateur
- gestion des fichiers associés à un cours
- publication / dépublication
- signalement et modération des contenus
- génération de suggestions et d’aide pédagogique

---

### 7) 📝 Journal d’humeur et méditation

**Objectif :** suivre l’état émotionnel et proposer des contenus de méditation.

#### Contrôleurs et routes

- **`JournalController`**
  - `/journal` — `GET`
  - `/journal/search` — `GET`
  - `/journal/stats` — `GET`
  - `/journal/transcribe` — `POST`
  - `/journal/new` — `GET`/`POST`
  - `/journal/{id}/edit` — `GET`/`POST`
  - `/journal/{id}/delete` — `POST`
  - Vues : `templates/journal/*`

- **`AdminJournalController`**
  - `/admin/journal` — `GET`
  - `/admin/journal/mark-read` — `POST`
  - `/admin/journal/students` — `GET`
  - `/admin/journal/user/{id}` — `GET`
  - `/admin/journal/user/{id}/rapport` — `GET`
  - Consultation des journaux d’humeur et export PDF de rapport.
  - Vues : `templates/admin/journal/*`

- **`MeditationController`**
  - `/meditation` — `GET`
  - `/meditation/search` — `GET`
  - `/meditation/{id}` — `GET`
  - Vue étudiant : `templates/meditation/etudiant/*`

- **`AdminMeditationController`**
  - `/admin/meditation` — `GET`
  - `/admin/meditation/search` — `GET`
  - `/admin/meditation/new` — `GET`/`POST`
  - `/admin/meditation/{id}` — `GET`
  - `/admin/meditation/{id}/edit` — `GET`/`POST`
  - `/admin/meditation/{id}/delete` — `POST`
  - `/admin/meditation/generate` — `POST`
  - `/admin/meditation/{id}/regenerate-conseils` — `POST`
  - `/admin/meditation/{id}/regenerate-session` — `POST`
  - `/admin/meditation/{id}/conseil/new` — `GET`/`POST`
  - `/admin/meditation/conseil/{id}/edit` — `GET`/`POST`
  - `/admin/meditation/conseil/{id}/delete` — `POST`
  - `/admin/meditation/pdf` — `GET`
  - `/admin/meditation/{id}/pdf` — `GET`
  - Administration des sessions et conseils de méditation.
  - Vues : `templates/meditation/admin/*`

#### Entités, repositories et services liés

- Entités : `JournalHumeur`, `SessionMeditation`, `Conseil`
- Repositories : `JournalHumeurRepository`, `SessionMeditationRepository`, `ConseilRepository`
- Services : `GroqService`, `SummaryService` (si utilisé), `SentimentService` (si utilisé), `TranslationService` (si utilisé)

#### Règles métier visibles

- recherche et statistiques sur le journal
- transcription texte/audio
- génération ou régénération de contenus de méditation
- export PDF des sessions et rapports

---

### 8) 🏢 Administration générale et tableaux de bord

**Objectif :** fournir les vues de synthèse et les outils de gestion transverses.

#### Contrôleurs et routes

- **`AdminDashboardController`**
  - `/admin` — `GET`
  - Tableau de bord global des statistiques.

- **`AdminUserController`**
  - administration des comptes, suspicion, suspension et recherche
  - déjà détaillé dans la partie utilisateurs

- **`AdminNutritionController`**, **`AdminSportController`**, **`AdminJournalController`**, **`AdminMeditationController`**, **`AdminCoursesController`**, **`AdminCourseReportsController`**, **`AdminEvenementController`**, **`AdminTacheController`**, **`AdminSalleController`**, **`AdminDemandeController`**
  - back-office par domaine avec actions CRUD et validation.

#### Vues principales

- `templates/admin/dashboard.html.twig`
- `templates/admin/base.html.twig`
- `templates/back/base_back.html.twig`

---

### 9) 💬 Messagerie et temps réel

**Objectif :** permettre des échanges asynchrones et le suivi en temps réel.

#### Contrôleurs et routes

- **`MessagingController`**
  - conversations, messages, envoi de messages, recherche d’utilisateurs, compteur de non-lus, token Mercure
  - flux utile pour la messagerie instantanée
  - Vues : `templates/messaging/*`

- **`Api/KanbanStreamController`**
  - flux SSE temps réel pour le Kanban

#### Services liés

- `TelegramNotifier`, `KanbanRealtimeNotifier`, Mercure

---

### 10) 🧰 Utilitaires, tests et pages techniques

- **`CoversController`** — sert les couvertures depuis `/covers/{filename}`
- **`ChatbotController`** — page assistant `/chatbot`
- **`TestController`** — page de vérification `/categories`
- **`Api/GithubSettingsController`** et **`Api/GithubWebhookController`** — intégration GitHub
- **`AdminDashboardController`** — point d’entrée admin

## Installation & Configuration

### 1. Installer les dépendances

```bash
composer install
```

### 2. Configurer l’environnement

Créer ou compléter un fichier `.env.local` avec au minimum :

```dotenv
APP_ENV=dev
APP_SECRET=change_me
DATABASE_URL="mysql://user:password@127.0.0.1:3306/harmonie?serverVersion=10.4.32-MariaDB"
```

Selon les fonctionnalités activées, renseigner aussi les variables liées à :

- la messagerie et Mercure
- Google OAuth / Google Calendar
- Facebook OAuth
- GitHub webhooks et synchronisation
- services IA (Groq, Mistral, Spoonacular, YouTube, etc.)
- mailer / reset password

### 3. Créer la base de données et appliquer le schéma

```bash
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
```

### 4. Installer les assets si nécessaire

```bash
php bin/console assets:install
```

## Lancement du projet

### Mode développement

```bash
php -S 127.0.0.1:8000 -t public
```

### Alternative Symfony CLI

```bash
symfony serve
```

L’application est ensuite accessible sur :

```text
http://127.0.0.1:8000
```

## Tests

Le projet contient bien une suite de tests PHPUnit, avec le fichier de configuration `phpunit.dist.xml` et un dossier `tests/` richement fourni.

### Lancement des tests

```bash
php bin/phpunit
```

### Tests détectés par domaine

- **Contrôleurs** : `ActivitesControllerTest`, `AdminJournalControllerTest`, `AdminMeditationControllerTest`, `AdminNutritionControllerTest`, `AdminSportControllerTest`, `AdminUserControllerTest`, `BlessureControllerTest`, `HomepageControllerTest`, `JournalControllerTest`, `MeditationControllerTest`, `ModulesControllerTest`, `NutritionControllerTest`, `ProfileControllerTest`, `RegistrationControllerTest`, `TestControllerTest`
- **Entités** : `ActiviteTest`, `AlimentTest`, `ConsommationTest`, `CourseFileTest`, `CourseReportTest`, `CourseTest`, `ExerciceTest`, `JournalHumeurTest`, `SavedCourseTest`, `SessionMeditationTest`, `SubjectTest`, `UserTest`
- **Repositories** : `ActiviteRepositoryTest`, `AlimentRepositoryTest`, `ConsommationRepositoryTest`, `ExerciceRepositoryTest`, `JournalHumeurRepositoryTest`, `SessionMeditationRepositoryTest`, `UserRepositoryTest`
- **Services** : `ActiviteManagerTest`, `AlimentManagerTest`, `BlessureServiceTest`, `CourseManagerTest`, `GeminiVisionServiceTest`, `GroqServiceTest`, `ModerationServiceTest`, `PostValidatorTest`, `SuspicionScoreServiceTest`, `UserManagerTest`
- **Sécurité** : `FacebookAuthenticatorTest`, `GoogleAuthenticatorTest`, `GoogleLoginAuthenticatorTest`
- **Formulaires** : `AdminEditUserFormTypeTest`, `RegistrationFormTypeTest`, `RegistrationStep2FormTypeTest`
- **Twig** : `AdminTemplateTest`, `AjouterTemplateTest`, `BilanPdfTemplateTest`, `RecettesTemplateTest`
- **Tests unitaires métier** : suites `RoomRequest`, `Event`, `Task` avec validation, relations, CUD, performances et cas limites
- **Listeners** : `CaptchaListenerTest`, `FaceAuthListenerTest`

> Remarque : quelques fichiers de test en doublon ou marqués `copy.php` sont présents dans le dépôt, mais ne sont pas listés comme classes principales.

## Structure du projet

```text
.
├── composer.json
├── config/
├── migrations/
├── public/
├── src/
├── templates/
├── tests/
├── translations/
├── var/
└── vendor/
```

### Sous-dossiers principaux

```text
src/
├── Controller/
├── Entity/
├── Form/
├── Repository/
├── Service/
└── EventListener/

templates/
├── admin/
├── back/
├── forum/
├── library/
├── meditation/
├── nutrition/
├── profile/
├── registration/
├── salle/
├── security/
├── tache/
└── evenement/
```

## Notes utiles

- Le routage Symfony est configuré via attributs dans les contrôleurs.
- La synchronisation Doctrine utilise MariaDB 10.4.32 côté configuration.
- Les modules planning, tâches et événements sont fortement couplés aux services `PlanningDomainService`, `GoogleCalendarService`, `TelegramNotifier` et aux exports.
- Le forum et certaines pages admin utilisent plusieurs services IA pour la modération, la traduction et le résumé.
- Les fonctionnalités Face ID, CAPTCHA, 2FA et reset password renforcent la sécurité utilisateur.

---

Si tu veux, je peux maintenant te générer une **version encore plus synthétique pour le README public** ou une **version orientée recruteur/portfolio**.
