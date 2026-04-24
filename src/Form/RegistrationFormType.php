<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('userNom', TextType::class, [
                'label' => 'Nom',
                'attr'  => ['placeholder' => 'Votre nom', 'autocomplete' => 'family-name'],
                'constraints' => [
                    new NotBlank(['message' => 'Le nom est obligatoire.']),
                    new Length([
                        'min' => 4,
                        'minMessage' => 'Le nom doit contenir au moins 4 caractères.',
                        'max' => 50,
                    ]),
                    new Regex([
                        'pattern' => '/^[a-zA-ZÀ-ÿ\s\-]+$/',
                        'message' => 'Le nom ne peut contenir que des lettres.',
                    ]),
                ],
            ])
            ->add('userPrenom', TextType::class, [
                'label' => 'Prénom',
                'attr'  => ['placeholder' => 'Votre prénom', 'autocomplete' => 'given-name'],
                'constraints' => [
                    new NotBlank(['message' => 'Le prénom est obligatoire.']),
                    new Length([
                        'min' => 4,
                        'minMessage' => 'Le prénom doit contenir au moins 4 caractères.',
                        'max' => 50,
                    ]),
                    new Regex([
                        'pattern' => '/^[a-zA-ZÀ-ÿ\s\-]+$/',
                        'message' => 'Le prénom ne peut contenir que des lettres.',
                    ]),
                ],
            ])
            ->add('userEmail', EmailType::class, [
                'label' => 'Email',
                'attr'  => ['placeholder' => 'votre@email.com', 'autocomplete' => 'email'],
                'constraints' => [
                    new NotBlank(['message' => "L'email est obligatoire."]),
                ],
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type'            => PasswordType::class,
                'mapped'          => false,
                'first_options'   => [
                    'label' => 'Mot de passe',
                    'attr'  => ['placeholder' => '••••••••', 'autocomplete' => 'new-password'],
                ],
                'second_options'  => [
                    'label' => 'Confirmer le mot de passe',
                    'attr'  => ['placeholder' => '••••••••', 'autocomplete' => 'new-password'],
                ],
                'invalid_message' => 'Les mots de passe ne correspondent pas.',
                'constraints'     => [
                    new NotBlank(['message' => 'Le mot de passe est obligatoire.']),
                    new Length([
                        'min' => 8,
                        'minMessage' => 'Le mot de passe doit contenir au moins 8 caractères.',
                    ]),
                    new Regex([
                        'pattern' => '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).+$/',
                        'message' => 'Le mot de passe doit contenir au moins 1 majuscule, 1 minuscule, 1 chiffre et 1 symbole.',
                    ]),
                ],
            ])
            // ── CORRECTION : TextType au lieu de DateType ──────────────────────────
            // User::$userDateDeNaissance est stocké en VARCHAR(10) au format Y-m-d.
            // DateType attend un \DateTimeInterface et échoue avec un string → TransformationFailedException.
            // TextType + attr type="date" donne le même rendu HTML natif tout en restant compatible string.
            ->add('userDateDeNaissance', TextType::class, [
                'label' => 'Date de naissance',
                'attr'  => [
                    'type'        => 'date',
                    'max'         => (new \DateTime('-5 years'))->format('Y-m-d'),
                    'placeholder' => 'YYYY-MM-DD',
                ],
                'constraints' => [
                    new NotBlank(['message' => 'La date de naissance est obligatoire.']),
                    new Regex([
                        'pattern' => '/^\d{4}-\d{2}-\d{2}$/',
                        'message' => 'Format de date invalide (YYYY-MM-DD).',
                    ]),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => User::class]);
    }
}
