<?php

declare(strict_types=1);

namespace Tmonier\SyliusWithdrawalPlugin\Form\Type;

use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItemInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Tmonier\SyliusWithdrawalPlugin\Model\WithdrawalStatement;

final class WithdrawalStatementType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('customerName', TextType::class, [
                'label' => 'tmonier_withdrawal.form.customer_name',
                'constraints' => [new NotBlank(), new Length(max: 255)],
            ])
            ->add('email', EmailType::class, [
                'label' => 'tmonier_withdrawal.form.email',
                'help' => 'tmonier_withdrawal.form.email_help',
                'constraints' => [new NotBlank(), new Email(), new Length(max: 255)],
            ])
            ->add('orderNumber', TextType::class, [
                'label' => 'tmonier_withdrawal.form.order_number',
                'help' => 'tmonier_withdrawal.form.order_number_help',
                'constraints' => [new NotBlank(), new Length(max: 255)],
            ])
        ;

        /** @var OrderInterface|null $order */
        $order = $options['order'];
        if (null !== $order) {
            $builder->add('orderItemIds', ChoiceType::class, [
                'label' => 'tmonier_withdrawal.form.order_items',
                'help' => 'tmonier_withdrawal.form.order_items_help',
                'choices' => $this->getOrderItemChoices($order),
                'expanded' => true,
                'multiple' => true,
                'required' => false,
            ]);
        }

        $builder->add('itemsDescription', TextareaType::class, [
            'label' => 'tmonier_withdrawal.form.items_description',
            'help' => 'tmonier_withdrawal.form.items_description_help',
            'required' => false,
            'constraints' => [new Length(max: 2000)],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => WithdrawalStatement::class,
            'order' => null,
        ]);
        $resolver->setAllowedTypes('order', ['null', OrderInterface::class]);
    }

    public function getBlockPrefix(): string
    {
        return 'tmonier_withdrawal_statement';
    }

    /** @return array<string, int> */
    private function getOrderItemChoices(OrderInterface $order): array
    {
        $choices = [];
        /** @var OrderItemInterface $item */
        foreach ($order->getItems() as $item) {
            $label = trim(sprintf('%s %s', $item->getProductName() ?? '', $item->getVariantName() ?? ''));
            $choices[sprintf('%s × %d', $label, $item->getQuantity())] = (int) $item->getId();
        }

        return $choices;
    }
}
