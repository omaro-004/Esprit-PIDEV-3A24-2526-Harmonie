<?php
namespace App\Form;

use App\Entity\Categorie;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CategorieType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nomCategorie', TextType::class, [
                'label' => false,
                'attr'  => [
                    'placeholder' => 'Ex: Programmation, Base de données...',
                    'class'       => 'form-input',
                    'maxlength'   => 100,
                ],
                'error_bubbling' => false,
            ])
            ->add('description', TextareaType::class, [
                'label'    => false,
                'required' => false,
                'attr'     => [
                    'placeholder' => 'Décris brièvement cette catégorie...',
                    'class'       => 'form-textarea',
                ],
                'error_bubbling' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Categorie::class,
        ]);
    }
}