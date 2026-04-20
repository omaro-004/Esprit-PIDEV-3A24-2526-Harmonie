<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\Regex;

class SecuritySettingsFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // ── Changement d'email ──────────────────────────────────────
            ->add('newEmail', EmailType::class, [
                'label'    => 'Nouvel email',
                'required' => false,
                'mapped'   => false,
                'attr'     => ['placeholder' => 'nouveau@email.com', 'autocomplete' => 'email'],
                'constraints' => [
                    new Email(['message' => "Format d'email invalide."]),
                ],
            ])

            // ── Mot de passe actuel (obligatoire si changement de mdp) ──
            ->add('currentPassword', PasswordType::class, [
                'label'    => 'Mot de passe actuel',
                'required' => false,
                'mapped'   => false,
                'attr'     => ['placeholder' => '••••••••', 'autocomplete' => 'current-password'],
            ])

            // ── Nouveau mot de passe (avec confirmation) ────────────────
            ->add('newPassword', RepeatedType::class, [
                'type'           => PasswordType::class,
                'mapped'         => false,
                'required'       => false,
                'first_options'  => [
                    'label' => 'Nouveau mot de passe',
                    'attr'  => ['placeholder' => '••••••••', 'autocomplete' => 'new-password'],
                ],
                'second_options' => [
                    'label' => 'Confirmer le nouveau mot de passe',
                    'attr'  => ['placeholder' => '••••••••', 'autocomplete' => 'new-password'],
                ],
                'invalid_message' => 'Les mots de passe ne correspondent pas.',
                'constraints'     => [
                    new Length([
                        'min'        => 8,
                        'minMessage' => 'Le mot de passe doit contenir au moins 8 caractères.',
                    ]),
                    new Regex([
                        'pattern' => '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).+$/',
                        'message' => 'Le mot de passe doit contenir au moins 1 majuscule, 1 minuscule, 1 chiffre et 1 symbole.',
                    ]),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null, // pas mappé sur une entité
        ]);
    }
}
