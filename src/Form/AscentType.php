<?php

namespace App\Form;

use App\Entity\Ascent;
use App\Entity\Boulder;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AscentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('completedAt', DateTimeType::class, [
                'label' => 'Date et heure de l’ascension',
                'input' => 'datetime_immutable',
                'widget' => 'single_text',
                'row_attr' => ['class' => 'form-field'],
                'help' => 'Entre l’ouverture du bloc et maintenant.',
            ])
            ->add('rating', null, ['label' => 'Votre note sur 5', 'attr' => ['min' => 1, 'max' => 5], 'row_attr' => ['class' => 'form-field']])
            ->add('comment', null, ['label' => 'Commentaire', 'required' => false, 'attr' => ['rows' => 4, 'maxlength' => 5000], 'row_attr' => ['class' => 'form-field']])
            ->add('boulder', EntityType::class, [
                'class' => Boulder::class,
                'query_builder' => static function (\App\Repository\BoulderRepository $repository) use ($options) {
                    $query = $repository->createQueryBuilder('b')->andWhere('b.openedAt <= :now')
                        ->andWhere('b.dismantledAt IS NULL OR b.dismantledAt > :now')->setParameter('now', new \DateTimeImmutable());
                    if (null !== $options['boulder']) {
                        $query->andWhere('b.id = :id')->setParameter('id', $options['boulder']->getId());
                    }

                    return $query;
                },
                'label' => 'Bloc grimpé',
                'placeholder' => 'Choisissez un bloc',
                'choice_label' => static fn(Boulder $boulder): string => sprintf('%s · Cotation %s', $boulder->getName(), $boulder->getGradeLabel()),
                'row_attr' => ['class' => 'form-field'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Ascent::class,
            'boulder' => null,
            'csrf_token_id' => 'ascent_create',
        ]);
        $resolver->setAllowedTypes('boulder', ['null', Boulder::class]);
    }
}
