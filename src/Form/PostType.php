<?php
namespace App\Form;

use App\Entity\Post;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Image;

/**
 * @extends AbstractType<Post>
 */
class PostType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('contenu', TextareaType::class, [
                'label'          => false,
                'empty_data'     => '',    // ← ajouter
                'attr'           => [
                    'placeholder' => 'Développe ta question ou ton sujet...',
                    'class'       => 'form-textarea',
                ],
                'error_bubbling' => false,
            ])
            ->add('titre', TextType::class, [
                'label'          => false,
                'empty_data'     => '',    // ← ajouter
                'attr'           => [
                    'placeholder' => 'Un titre clair et précis...',
                    'class'       => 'form-input',
                    'maxlength'   => 150,
                ],
                'error_bubbling' => false,
            ])
            ->add('imageFile', FileType::class, [
                'label'       => false,
                'required'    => false,
                'mapped'      => false,  // pas mappé sur l'entité
                'constraints' => [
                    new Image([
                        'maxSize'          => '2M',
                        'mimeTypes'        => ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
                        'mimeTypesMessage' => 'Format accepté : JPG, PNG, GIF, WEBP',
                        'maxSizeMessage'   => 'Image trop lourde (max 2Mo)',
                    ]),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Post::class,
        ]);
    }
}