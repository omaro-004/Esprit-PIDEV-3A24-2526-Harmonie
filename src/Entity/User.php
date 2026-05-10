<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use SensitiveParameter;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Annotation\Ignore;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[UniqueEntity(fields: ['userEmail'], message: 'Cet email est déjà utilisé.')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'user_id', type: Types::INTEGER)]

    private ?int $userId = null;

    #[ORM\Column(name: 'user_nom', type: Types::STRING, length: 50)]
    #[Assert\NotBlank(message: 'Le nom est obligatoire.', groups: ['Default', 'step1'])]
    #[Assert\Length(min: 2, max: 50, groups: ['Default', 'step1'])]
    private string $userNom = '';

    #[ORM\Column(name: 'user_prenom', type: Types::STRING, length: 50)]
    #[Assert\NotBlank(message: 'Le prénom est obligatoire.', groups: ['Default', 'step1'])]
    #[Assert\Length(min: 2, max: 50, groups: ['Default', 'step1'])]
    private string $userPrenom = '';

    #[ORM\Column(name: 'user_email', type: Types::STRING, length: 100, unique: true)]
    #[Assert\NotBlank(message: "L'email est obligatoire.", groups: ['Default', 'step1'])]
    #[Assert\Email(message: "Format d'email invalide.", groups: ['Default', 'step1'])]
    private string $userEmail = '';

    #[ORM\Column(name: 'user_password', type: Types::STRING, length: 255)]
    #[Ignore]
    private string $userPassword = '';

    #[ORM\Column(name: 'user_date_de_naissance', type: Types::STRING, length: 10)]
    #[Assert\NotBlank(message: 'La date de naissance est obligatoire.', groups: ['Default', 'step1'])]
    #[Assert\Regex(pattern: '/^\d{4}-\d{2}-\d{2}$/', message: 'Format de date invalide (YYYY-MM-DD).', groups: ['Default', 'step1'])]
    private string $userDateDeNaissance = '';

    #[ORM\Column(
        name: 'user_sexe',
        type: Types::STRING,
        length: 20,
        nullable: true
    )]
    private ?string $userSexe = null;

    #[ORM\Column(
        name: 'user_poids',
        type: Types::DECIMAL,
        precision: 5,
        scale: 2,
        nullable: true,
        options: ['default' => null]
    )]
    private ?string $userPoids = null;

    #[ORM\Column(
        name: 'user_taille',
        type: Types::INTEGER,
        nullable: true,
        options: ['default' => null]
    )]
    private ?int $userTaille = null;

    #[ORM\Column(
        name: 'user_niveau_activite_physique',
        type: Types::STRING,
        length: 20,
        nullable: true
    )]
    private ?string $userNiveauActivitePhysique = null;

    #[ORM\Column(
        name: 'user_niveau_scolaire',
        type: Types::STRING,
        length: 20,
        nullable: true
    )]
    private ?string $userNiveauScolaire = null;

    #[ORM\Column(name: 'user_etablissement_scolaire', type: Types::STRING, length: 255, nullable: true)]
    private ?string $userEtablissementScolaire = null;

    #[ORM\Column(name: 'date_inscription', type: Types::STRING, length: 10)]
    private string $dateInscription = '';

    #[ORM\Column(
        name: 'type_utilisateur',
        type: Types::STRING,
        length: 20,
        options: ['default' => 'ETUDIANT']
    )]
    private string $typeUtilisateur = 'ETUDIANT';

    #[ORM\Column(name: 'is_active', type: Types::BOOLEAN, options: ['default' => true])]
    private bool $isActive = true;

    #[ORM\Column(name: 'user_image_path', type: Types::STRING, length: 255, nullable: true)]
    private ?string $userImagePath = null;

    #[ORM\Column(name: 'face_image_path', type: Types::STRING, length: 500, nullable: true)]
    private ?string $faceImagePath = null;

    #[ORM\Column(name: 'face_id_enabled', type: Types::BOOLEAN, options: ['default' => false])]
    private bool $faceIdEnabled = false;

    #[ORM\Column(name: 'google_id', type: Types::STRING, length: 255, nullable: true, unique: true)]
    private ?string $googleId = null;

    #[ORM\Column(name: 'facebook_id', type: Types::STRING, length: 255, nullable: true, unique: true)]
    private ?string $facebookId = null;

    #[ORM\Column(name: 'oauth_avatar_url', type: Types::STRING, length: 500, nullable: true)]
    private ?string $oauthAvatarUrl = null;

    /**
     * Secret TOTP pour Google Authenticator — réinitialisation de mot de passe.
     */
    #[ORM\Column(name: 'totp_secret', type: Types::STRING, length: 255, nullable: true)]
    #[Ignore]
    private ?string $totpSecret = null;

    #[ORM\Column(name: 'telegram_chat_id', type: Types::STRING, length: 100, nullable: true)]
    private ?string $telegramChatId = null;

    #[ORM\Column(name: 'google_access_token', type: Types::TEXT, nullable: true)]
    #[Ignore]
    private ?string $googleAccessToken = null;

    #[ORM\Column(name: 'google_refresh_token', type: Types::STRING, length: 512, nullable: true)]
    #[Ignore]
    private ?string $googleRefreshToken = null;

    #[ORM\Column(name: 'google_token_expires_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Ignore]
    private ?\DateTimeInterface $googleTokenExpiresAt = null;

    // ── Symfony UserInterface ──────────────────────────────────────────────────

    public function getUserIdentifier(): string { return $this->userEmail; }

    public function getRoles(): array
    {
        return $this->typeUtilisateur === 'ADMIN'
            ? ['ROLE_ADMIN', 'ROLE_USER']
            : ['ROLE_USER'];
    }

    public function getPassword(): string { return $this->userPassword; }
    public function eraseCredentials(): void {}

    // ── Aliases ────────────────────────────────────────────────────────────────
    public function getFirstName(): string { return $this->userPrenom; }
    public function getLastName(): string  { return $this->userNom; }
    public function getEmail(): string     { return $this->userEmail; }

    // ── Getters / Setters ──────────────────────────────────────────────────────

    public function getUserId(): ?int { return $this->userId; }
    public function getId(): ?int     { return $this->userId; }

    public function getUserNom(): string             { return $this->userNom; }
    public function setUserNom(string $v): self      { $this->userNom = $v; return $this; }

    public function getUserPrenom(): string          { return $this->userPrenom; }
    public function setUserPrenom(string $v): self   { $this->userPrenom = $v; return $this; }

    public function getUserEmail(): string           { return $this->userEmail; }
    public function setUserEmail(string $v): self    { $this->userEmail = $v; return $this; }

    public function getUserPassword(): string        { return $this->userPassword; }
    public function setUserPassword(#[SensitiveParameter] string $v): self { $this->userPassword = $v; return $this; }

    public function getUserDateDeNaissance(): string         { return $this->userDateDeNaissance; }
    public function setUserDateDeNaissance(string $v): self  { $this->userDateDeNaissance = $v; return $this; }

    public function getUserSexe(): ?string           { return $this->userSexe; }
    public function setUserSexe(?string $v): self    { $this->userSexe = $v; return $this; }

    public function getUserPoids(): ?string          { return $this->userPoids; }
    public function setUserPoids(?string $v): self   { $this->userPoids = $v; return $this; }

    public function getUserTaille(): ?int            { return $this->userTaille; }
    public function setUserTaille(?int $v): self     { $this->userTaille = $v; return $this; }

    public function getUserNiveauActivitePhysique(): ?string        { return $this->userNiveauActivitePhysique; }
    public function setUserNiveauActivitePhysique(?string $v): self { $this->userNiveauActivitePhysique = $v; return $this; }

    public function getUserNiveauScolaire(): ?string        { return $this->userNiveauScolaire; }
    public function setUserNiveauScolaire(?string $v): self { $this->userNiveauScolaire = $v; return $this; }

    public function getUserEtablissementScolaire(): ?string        { return $this->userEtablissementScolaire; }
    public function setUserEtablissementScolaire(?string $v): self { $this->userEtablissementScolaire = $v; return $this; }

    public function getDateInscription(): string         { return $this->dateInscription; }
    public function setDateInscription(string $v): self  { $this->dateInscription = $v; return $this; }

    public function getTypeUtilisateur(): string         { return $this->typeUtilisateur; }
    public function setTypeUtilisateur(string $v): self  { $this->typeUtilisateur = $v; return $this; }

    public function getIsActive(): bool      { return $this->isActive; }
    public function isActive(): bool         { return $this->isActive; }
    public function setIsActive(bool $v): self { $this->isActive = $v; return $this; }

    public function getUserImagePath(): ?string          { return $this->userImagePath; }
    public function setUserImagePath(?string $v): self   { $this->userImagePath = $v; return $this; }

    public function getFaceImagePath(): ?string          { return $this->faceImagePath; }
    public function setFaceImagePath(?string $v): self   { $this->faceImagePath = $v; return $this; }

    public function isFaceIdEnabled(): bool          { return $this->faceIdEnabled; }
    public function getFaceIdEnabled(): bool         { return $this->faceIdEnabled; }
    public function setFaceIdEnabled(bool $v): self  { $this->faceIdEnabled = $v; return $this; }

    public function getGoogleId(): ?string           { return $this->googleId; }
    public function setGoogleId(?string $v): self    { $this->googleId = $v; return $this; }

    public function getFacebookId(): ?string         { return $this->facebookId; }
    public function setFacebookId(?string $v): self  { $this->facebookId = $v; return $this; }

    public function getOauthAvatarUrl(): ?string         { return $this->oauthAvatarUrl; }
    public function setOauthAvatarUrl(?string $v): self  { $this->oauthAvatarUrl = $v; return $this; }

    public function getTotpSecret(): ?string         { return $this->totpSecret; }
    public function setTotpSecret(#[SensitiveParameter] ?string $v): self  { $this->totpSecret = $v; return $this; }

    public function getTelegramChatId(): ?string          { return $this->telegramChatId; }
    public function setTelegramChatId(?string $v): self   { $this->telegramChatId = $v; return $this; }

    public function getGoogleAccessToken(): ?string         { return $this->googleAccessToken; }
    public function setGoogleAccessToken(#[SensitiveParameter] ?string $v): self  { $this->googleAccessToken = $v; return $this; }

    public function getGoogleRefreshToken(): ?string         { return $this->googleRefreshToken; }
    public function setGoogleRefreshToken(#[SensitiveParameter] ?string $v): self  { $this->googleRefreshToken = $v; return $this; }

    public function getGoogleTokenExpiresAt(): ?\DateTimeInterface         { return $this->googleTokenExpiresAt; }
    public function setGoogleTokenExpiresAt(#[SensitiveParameter] ?\DateTimeInterface $v): self  { $this->googleTokenExpiresAt = $v; return $this; }

    public function getDisplayAvatar(): ?string
    {
        return $this->userImagePath ?? $this->oauthAvatarUrl;
    }

    public function isOAuthUser(): bool
    {
        return $this->googleId !== null || $this->facebookId !== null;
    }
}
