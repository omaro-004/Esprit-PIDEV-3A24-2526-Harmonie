# Gestion User - Notes de tests et corrections

Ce document decrit :
- la logique du test unitaire `UserManagerTest`
- les corrections PHPStan (RegistrationController + SuspicionScoreService + config)
- les corrections base de donnees (schema + entites + config Doctrine)

## 1) Test unitaire : UserManagerTest
Fichier : tests/Service/UserManagerTest.php

Objectif : verifier rapidement les regles simples autour de l'entite `User`.

### 1.1 Construction d'un utilisateur valide
La methode privee `createValidUser()` instancie un `User` avec :
- nom, prenom, email valides
- mot de passe deja hash (string)
- date de naissance au format `YYYY-MM-DD`
- type utilisateur `ETUDIANT`
- compte actif par defaut

Cette methode sert de base commune aux tests suivants.

### 1.2 Logique des 6 tests
1) **Email valide**
   - verifie que l'email n'est pas vide
   - verifie qu'il contient un `@`

2) **Nom et prenom non vides**
   - verifie que les deux champs sont remplis

3) **Date de naissance au bon format**
   - verifie que la date suit `YYYY-MM-DD` via regex

4) **Type utilisateur valide**
   - verifie que la valeur est dans `['ETUDIANT', 'ADMIN']`

5) **Utilisateur actif par defaut**
   - verifie que `isActive()` retourne `true`

6) **Roles retournes correctement**
   - verifie que `ROLE_USER` est present dans `getRoles()`

Ce test ne valide pas la persistence en base, seulement la logique d'entite.

## 2) Corrections PHPStan

### 2.1 RegistrationController
Fichier : src/Controller/RegistrationController.php

Probleme PHPStan :
- `instanceof DateTimeInterface` toujours faux, car `getUserDateDeNaissance()` retourne une string.

Correction :
- suppression du `instanceof` et stockage direct de la string :
  - `dateNaissance => $user->getUserDateDeNaissance()`


### 2.3 Configuration PHPStan
Fichier : phpstan.neon

Corrections :
- ajout des includes Symfony :
  - vendor/phpstan/phpstan-symfony/extension.neon
  - vendor/phpstan/phpstan-symfony/rules.neon
- correction de la cle `containerXmlPath`

## 3) Corrections Base de donnees / Doctrine

### 3.1 Probleme initial
- `doctrine:schema:validate` indiquait un schema non synchronise
- `schema:update --force` cassait a cause de `RENAME INDEX` (non supporte par MariaDB 10.4)
- la base `integration-f` etait vide pour Doctrine (dump non importe)

### 3.2 Actions effectuees
1) Import complet du dump dans `integration-f`.
2) Mise a jour de la version serveur Doctrine pour MariaDB 10.4.32.
3) Correction du schema avec SQL adapte (sans `RENAME INDEX`).
4) Traitement du champ `seance.date_fin` (suppression des zero dates avant modif).
5) Conversion des ENUM en VARCHAR pour l'entite `User`.

### 3.3 Fichiers touches
- config/packages/doctrine.yaml
  - server_version: `10.4.32-MariaDB`
  - schema_filter conserve (user, conversation, message)
- .env
  - DATABASE_URL mis a jour pour la version MariaDB
- src/Entity/User.php
  - enums remplaces par `VARCHAR(20)` + options par defaut
- schema_fix.sql
  - script SQL utilise pour synchroniser le schema

### 3.4 Pourquoi remplacer ENUM par VARCHAR
- MariaDB + Doctrine generaient un faux diff en boucle sur les ENUM
- `doctrine:schema:validate` restait en erreur
- conversion en `VARCHAR(20)` stabilise le mapping

Si tu veux garder des ENUM, il faudra un **type Doctrine custom**.

## 4) Etat final
- `php bin/console doctrine:schema:validate` -> OK
- PHPStan passe sur les fichiers corriges

## 5) Commandes utiles

### Tests
- PHPUnit :
  - `php bin/phpunit tests/Service/UserManagerTest.php`

### PHPStan
- `./vendor/bin/phpstan.bat analyse src/Controller/RegistrationController.php`


### Doctrine
- `php bin/console doctrine:schema:validate`
- `php bin/console doctrine:schema:update --dump-sql`

## 6) Notes
- Si tu changes `User` (type, enum, relation), relance `schema:validate`.
- Pour un audit avance, tu peux ajouter des tests unitaires sur `Message` et `Conversation`.
