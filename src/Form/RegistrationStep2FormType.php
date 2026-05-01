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
use Symfony\Component\Validator\Constraints\LessThanOrEqual;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;

class RegistrationStep2FormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
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
            ])
            ->add('userSexe', ChoiceType::class, [
                'label'       => 'Sexe',
                'required'    => false,
                'placeholder' => '— Sélectionner —',
                'choices'     => ['Homme' => 'HOMME', 'Femme' => 'FEMME', 'Autre' => 'AUTRE'],
            ])
            ->add('userPoids', NumberType::class, [
                'label'    => 'Poids (kg)',
                'required' => false,
                'scale'    => 1,
                'attr'     => ['placeholder' => 'Ex: 70.5', 'step' => '0.1', 'min' => 40, 'max' => 200],
                'constraints' => [
                    new GreaterThanOrEqual(['value' => 40, 'message' => 'Le poids minimum est 40 kg.']),
                    new LessThanOrEqual(['value' => 200, 'message' => 'Le poids maximum est 200 kg.']),
                ],
            ])
            ->add('userTaille', NumberType::class, [
                'label'    => 'Taille (cm)',
                'required' => false,
                'attr'     => ['placeholder' => 'Ex: 175', 'min' => 100, 'max' => 210],
                'constraints' => [
                    new GreaterThanOrEqual(['value' => 100, 'message' => 'La taille minimum est 100 cm.']),
                    new LessThanOrEqual(['value' => 210, 'message' => 'La taille maximum est 210 cm.']),
                ],
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
                'attr'     => ['placeholder' => 'Ex: Université Paris-Saclay'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class'        => User::class,
            'validation_groups' => ['step2'],  // ← clé du correctif
        ]);
    }
}
