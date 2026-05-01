<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

class ProfileFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // ── Identité ────────────────────────────────────────────────
            ->add('userNom', TextType::class, [
                'label' => 'Nom',
                'attr'  => ['placeholder' => 'Votre nom'],
                'constraints' => [
                    new NotBlank(['message' => 'Le nom est obligatoire.']),
                    new Length(['min' => 2, 'max' => 50]),
                    new Regex([
                        'pattern' => '/^[a-zA-ZÀ-ÿ\s\-]+$/',
                        'message' => 'Le nom ne peut contenir que des lettres.',
                    ]),
                ],
            ])
            ->add('userPrenom', TextType::class, [
                'label' => 'Prénom',
                'attr'  => ['placeholder' => 'Votre prénom'],
                'constraints' => [
                    new NotBlank(['message' => 'Le prénom est obligatoire.']),
                    new Length(['min' => 2, 'max' => 50]),
                    new Regex([
                        'pattern' => '/^[a-zA-ZÀ-ÿ\s\-]+$/',
                        'message' => 'Le prénom ne peut contenir que des lettres.',
                    ]),
                ],
            ])
            ->add('userDateDeNaissance', TextType::class, [
                'label' => 'Date de naissance',
                'attr'  => ['type' => 'date'],
            ])
            ->add('userSexe', ChoiceType::class, [
                'label'       => 'Sexe',
                'required'    => false,
                'placeholder' => '— Sélectionner —',
                'choices'     => ['Homme' => 'HOMME', 'Femme' => 'FEMME', 'Autre' => 'AUTRE'],
            ])

            // ── Physique ────────────────────────────────────────────────
            ->add('userPoids', NumberType::class, [
                'label'    => 'Poids (kg)',
                'required' => false,
                'scale'    => 1,
                'attr'     => ['placeholder' => 'Ex: 70.5', 'step' => '0.1'],
            ])
            ->add('userTaille', NumberType::class, [
                'label'    => 'Taille (cm)',
                'required' => false,
                'attr'     => ['placeholder' => 'Ex: 175'],
            ])
            ->add('userNiveauActivitePhysique', ChoiceType::class, [
                'label'       => "Niveau d'activité physique",
                'required'    => false,
                'placeholder' => '— Sélectionner —',
                'choices'     => [
                    'Sédentaire'   => 'SEDENTAIRE',
                    'Léger'        => 'LEGER',
                    'Modéré'       => 'MODERE',
                    'Intense'      => 'INTENSE',
                    'Très intense' => 'TRES_INTENSE',
                ],
            ])

            // ── Scolarité ───────────────────────────────────────────────
            ->add('userNiveauScolaire', ChoiceType::class, [
                'label'       => 'Niveau scolaire',
                'required'    => false,
                'placeholder' => '— Sélectionner —',
                'choices'     => [
                    'Primaire' => 'PRIMAIRE', 'Collège'  => 'COLLEGE',
                    'Lycée'    => 'LYCEE',    'Licence'  => 'LICENCE',
                    'Master'   => 'MASTER',   'Doctorat' => 'DOCTORAT',
                    'Autre'    => 'AUTRE',
                ],
            ])
            ->add('userEtablissementScolaire', TextType::class, [
                'label'    => 'Établissement scolaire',
                'required' => false,
                'attr'     => ['placeholder' => 'Ex: Université de Tunis'],
            ])

            // ── Notifications ───────────────────────────────────────────
            ->add('telegramChatId', TextType::class, [
                'label'    => 'Telegram Chat ID',
                'required' => false,
                'attr'     => ['placeholder' => 'Ex: 123456789'],
            ])

            // ── Avatar ──────────────────────────────────────────────────
            ->add('avatarFile', FileType::class, [
                'label'    => 'Photo de profil',
                'mapped'   => false,
                'required' => false,
                'constraints' => [
                    new File([
                        'maxSize'          => '2M',
                        'mimeTypes'        => ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
                        'mimeTypesMessage' => 'Image JPG, PNG, WebP ou GIF uniquement.',
                    ]),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => User::class]);
    }
}
