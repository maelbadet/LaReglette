<?php

namespace App\Form;

use App\Entity\User;
use App\Enum\UserRole;
use App\Repository\UserRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class ChangeRoleType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('user', EntityType::class, [
                'class' => User::class,
                'choice_label' => static fn (User $user): string => sprintf('%s %s · %s', $user->getFirstName(), $user->getLastName(), $user->getEmail()),
                'query_builder' => static fn (UserRepository $users) => $users->createQueryBuilder('u')->orderBy('u.lastName', 'ASC')->addOrderBy('u.firstName', 'ASC')->addOrderBy('u.id', 'ASC'),
                'label' => 'Utilisateur',
                'placeholder' => 'Choisissez un compte',
                'row_attr' => ['class' => 'form-field'],
                'constraints' => [new Assert\NotNull(message: 'Choisissez un utilisateur.')],
            ])
            ->add('role', EnumType::class, [
                'class' => UserRole::class,
                'choice_label' => static fn (UserRole $role): string => $role->label(),
                'label' => 'Nouveau rôle',
                'placeholder' => 'Choisissez un rôle',
                'row_attr' => ['class' => 'form-field'],
                'constraints' => [new Assert\NotNull(message: 'Choisissez un rôle.')],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['csrf_token_id' => 'change_user_role']);
    }
}
