<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class AdminEditUserFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('userNom',    TextType::class,  ['label' => 'Nom'])
            ->add('userPrenom', TextType::class,  ['label' => 'Prénom'])
            ->add('userEmail',  EmailType::class, ['label' => 'Email'])
            ->add('userDateDeNaissance', TextType::class, [
                'label' => 'Date de naissance', 'attr' => ['type' => 'date'],
            ])
            ->add('userSexe', ChoiceType::class, [
                'label'    => 'Sexe', 'required' => false,
                'placeholder' => '—',
                'choices'  => ['Homme' => 'HOMME', 'Femme' => 'FEMME', 'Autre' => 'AUTRE'],
            ])
            ->add('userPoids', NumberType::class, [
                'label' => 'Poids (kg)', 'required' => false, 'attr' => ['step' => '0.1'],
            ])
            ->add('userTaille', NumberType::class, [
                'label' => 'Taille (cm)', 'required' => false,
            ])
            ->add('userNiveauActivitePhysique', ChoiceType::class, [
                'label' => "Niveau d'activité", 'required' => false, 'placeholder' => '—',
                'choices' => [
                    'Sédentaire' => 'SEDENTAIRE', 'Léger' => 'LEGER',
                    'Modéré' => 'MODERE', 'Intense' => 'INTENSE', 'Très intense' => 'TRES_INTENSE',
                ],
            ])
            ->add('userNiveauScolaire', ChoiceType::class, [
                'label' => 'Niveau scolaire', 'required' => false, 'placeholder' => '—',
                'choices' => [
                    'Primaire' => 'PRIMAIRE', 'Collège' => 'COLLEGE', 'Lycée' => 'LYCEE',
                    'Licence' => 'LICENCE', 'Master' => 'MASTER', 'Doctorat' => 'DOCTORAT', 'Autre' => 'AUTRE',
                ],
            ])
            ->add('userEtablissementScolaire', TextType::class, [
                'label' => 'Établissement', 'required' => false,
            ])
            ->add('typeUtilisateur', ChoiceType::class, [
                'label'   => 'Rôle',
                'choices' => ['Étudiant' => 'ETUDIANT', 'Admin' => 'ADMIN'],
            ])
            ->add('isActive', ChoiceType::class, [
                'label'   => 'Statut',
                'choices' => ['Actif' => true, 'Suspendu' => false],
            ])
            // ── Champ upload avatar (non mappé sur l'entité) ───────────────
            ->add('avatarFile', FileType::class, [
                'label'    => 'Photo de profil',
                'mapped'   => false,
                'required' => false,
                'constraints' => [
                    new File([
                        'maxSize'   => '2M',
                        'mimeTypes' => ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
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
