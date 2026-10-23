<?php

namespace App\Form;

use App\Repository\UserRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class ProfileType extends AbstractType
{
    public function __construct(private UserRepository $users) {}

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        foreach (['firstName' => ['Prénom', 'given-name'], 'lastName' => ['Nom', 'family-name']] as $field => [$label, $autocomplete]) {
            $builder->add($field, TextType::class, [
                'label' => $label,
                'row_attr' => ['class' => 'form-field'],
                'attr' => ['autocomplete' => $autocomplete, 'maxlength' => 255],
                'constraints' => [new Assert\NotBlank(), new Assert\Length(max: 255)],
            ]);
        }
        $builder->add('email', EmailType::class, [
            'label' => 'Adresse e-mail',
            'row_attr' => ['class' => 'form-field'],
            'attr' => ['autocomplete' => 'email', 'maxlength' => 180],
            'constraints' => [
                new Assert\NotBlank(),
                new Assert\Email(),
                new Assert\Length(max: 180),
                new Assert\Callback(function ($email, ExecutionContextInterface $context) use ($options): void {
                    if (!\is_string($email) || '' === $email) {
                        return;
                    }
                    $existing = $this->users->findOneBy(['email' => $email]);
                    if (null !== $existing && $existing->getId() !== $options['user_id']) {
                        $context->buildViolation('Cette adresse e-mail est déjà utilisée.')->addViolation();
                    }
                }),
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['csrf_token_id' => 'profile_edit']);
        $resolver->setRequired('user_id');
        $resolver->setAllowedTypes('user_id', ['int', 'null']);
    }
}
