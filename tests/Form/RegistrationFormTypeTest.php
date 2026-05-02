<?php

namespace App\Tests\Form;

use App\Entity\User;
use App\Form\RegistrationFormType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Validator\Validation;

class RegistrationFormTypeTest extends TypeTestCase
{
    protected function getExtensions(): array
    {
        $validator = Validation::createValidator();
        return [new ValidatorExtension($validator)];
    }

    public function testDataClassUser(): void
    {
        $form = $this->factory->create(RegistrationFormType::class);
        $this->assertSame(User::class, $form->getConfig()->getOption('data_class'));
    }

    public function testContientChampsPrincipaux(): void
    {
        $form = $this->factory->create(RegistrationFormType::class);

        $expected = ['userNom', 'userPrenom', 'userEmail', 'plainPassword', 'userDateDeNaissance'];
        foreach ($expected as $name) {
            $this->assertTrue($form->has($name));
        }
    }

    public function testPlainPasswordEstRepeatedEtNonMappe(): void
    {
        $form = $this->factory->create(RegistrationFormType::class);
        $config = $form->get('plainPassword')->getConfig();

        $this->assertInstanceOf(RepeatedType::class, $config->getType()->getInnerType());
        $this->assertFalse($config->getOption('mapped'));
    }

    public function testPlainPasswordAContraintesAttendue(): void
    {
        $form = $this->factory->create(RegistrationFormType::class);
        $constraints = $form->get('plainPassword')->getConfig()->getOption('constraints');

        $this->assertCount(1, array_filter($constraints, fn ($c) => $c instanceof NotBlank));
        $this->assertCount(1, array_filter($constraints, fn ($c) => $c instanceof Length));
        $this->assertCount(1, array_filter($constraints, fn ($c) => $c instanceof Regex));
    }

    public function testDateNaissanceEstTextTypeAvecAttrDate(): void
    {
        $form = $this->factory->create(RegistrationFormType::class);
        $config = $form->get('userDateDeNaissance')->getConfig();

        $this->assertInstanceOf(TextType::class, $config->getType()->getInnerType());

        $attr = $config->getOption('attr');
        $this->assertArrayHasKey('type', $attr);
        $this->assertSame('date', $attr['type']);
    }

    public function testDateNaissanceAContraintesAttendue(): void
    {
        $form = $this->factory->create(RegistrationFormType::class);
        $constraints = $form->get('userDateDeNaissance')->getConfig()->getOption('constraints');

        $this->assertCount(1, array_filter($constraints, fn ($c) => $c instanceof NotBlank));
        $this->assertCount(1, array_filter($constraints, fn ($c) => $c instanceof Regex));
    }
}
