<?php

declare(strict_types=1);

namespace Tmonier\SyliusWithdrawalPlugin\Controller\Shop;

use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Customer\Context\CustomerContextInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Tmonier\SyliusWithdrawalPlugin\Form\Type\WithdrawalStatementType;
use Tmonier\SyliusWithdrawalPlugin\Model\WithdrawalStatement;
use Tmonier\SyliusWithdrawalPlugin\Order\OrderMatcherInterface;
use Tmonier\SyliusWithdrawalPlugin\RateLimit\RateLimitKeys;
use Tmonier\SyliusWithdrawalPlugin\Repository\WithdrawalRequestRepositoryInterface;
use Tmonier\SyliusWithdrawalPlugin\Submitter\WithdrawalRequestSubmitterInterface;

/**
 * Two-step withdrawal function (Art. 11a CRD): statement form, then a separate
 * "confirm withdrawal" step. Available to guests and logged-in customers alike.
 */
final class WithdrawalController extends AbstractController
{
    private const SESSION_STATEMENT = 'tmonier_withdrawal.statement';

    private const SESSION_REFERENCE = 'tmonier_withdrawal.reference';

    private const CSRF_TOKEN_ID = 'tmonier_withdrawal_confirm';

    public function __construct(
        private readonly ChannelContextInterface $channelContext,
        private readonly LocaleContextInterface $localeContext,
        private readonly CustomerContextInterface $customerContext,
        private readonly OrderMatcherInterface $orderMatcher,
        private readonly WithdrawalRequestSubmitterInterface $submitter,
        private readonly WithdrawalRequestRepositoryInterface $withdrawalRequestRepository,
        private readonly ?RateLimiterFactory $ipLimiter = null,
        private readonly ?RateLimiterFactory $emailLimiter = null,
    ) {
    }

    public function formAction(Request $request): Response
    {
        $customer = $this->getCustomer();
        $order = $this->getCustomerOrder($request->query->getString('order'), $customer);

        $session = $request->getSession();
        /** @var array<string, mixed>|null $previous */
        $previous = $session->get(self::SESSION_STATEMENT);
        $statement = is_array($previous) ? WithdrawalStatement::fromArray($previous) : null;
        if (!$statement instanceof \Tmonier\SyliusWithdrawalPlugin\Model\WithdrawalStatement || ($order instanceof \Sylius\Component\Core\Model\OrderInterface && $statement->orderNumber !== $order->getNumber())) {
            $statement = $this->createPrefilledStatement($customer, $order);
        }

        $form = $this->createForm(WithdrawalStatementType::class, $statement, ['order' => $order]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $session->set(self::SESSION_STATEMENT, $statement->toArray());

            return $this->redirectToRoute('tmonier_withdrawal_shop_confirm', array_filter(['order' => $order?->getNumber()]));
        }

        return $this->render('@TmonierSyliusWithdrawalPlugin/shop/withdrawal/form.html.twig', [
            'form' => $form,
            'order' => $order,
        ]);
    }

    public function confirmAction(Request $request): Response
    {
        $session = $request->getSession();
        /** @var array<string, mixed>|null $data */
        $data = $session->get(self::SESSION_STATEMENT);
        if (!is_array($data)) {
            return $this->redirectToRoute('tmonier_withdrawal_shop_form');
        }

        $statement = WithdrawalStatement::fromArray($data);
        $customer = $this->getCustomer();
        $order = $this->getCustomerOrder($request->query->getString('order'), $customer);

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid(self::CSRF_TOKEN_ID, $request->request->getString('_csrf_token'))) {
                throw new BadRequestHttpException('Invalid CSRF token.');
            }

            // The statement stays in the session: the consumer can confirm it later.
            if ($this->isRateLimited($request, (string) $statement->email)) {
                $this->addFlash('error', 'tmonier_withdrawal.flash.too_many_requests');

                return $this->render('@TmonierSyliusWithdrawalPlugin/shop/withdrawal/confirm.html.twig', [
                    'statement' => $statement,
                    'order' => $order,
                    'selected_items' => $this->getSelectedItemLabels($order, $statement),
                    'csrf_token_id' => self::CSRF_TOKEN_ID,
                ], new Response(null, Response::HTTP_TOO_MANY_REQUESTS));
            }

            /** @var ChannelInterface $channel */
            $channel = $this->channelContext->getChannel();
            $withdrawalRequest = $this->submitter->submit(
                $statement,
                $channel,
                $this->localeContext->getLocaleCode(),
                $customer,
            );

            $session->remove(self::SESSION_STATEMENT);
            $session->set(self::SESSION_REFERENCE, $withdrawalRequest->getReference());

            return $this->redirectToRoute('tmonier_withdrawal_shop_done');
        }

        return $this->render('@TmonierSyliusWithdrawalPlugin/shop/withdrawal/confirm.html.twig', [
            'statement' => $statement,
            'order' => $order,
            'selected_items' => $this->getSelectedItemLabels($order, $statement),
            'csrf_token_id' => self::CSRF_TOKEN_ID,
        ]);
    }

    public function doneAction(Request $request): Response
    {
        $reference = $request->getSession()->get(self::SESSION_REFERENCE);
        $withdrawalRequest = is_string($reference) ? $this->withdrawalRequestRepository->findOneByReference($reference) : null;
        if (!$withdrawalRequest instanceof \Tmonier\SyliusWithdrawalPlugin\Entity\WithdrawalRequestInterface) {
            return $this->redirectToRoute('tmonier_withdrawal_shop_form');
        }

        return $this->render('@TmonierSyliusWithdrawalPlugin/shop/withdrawal/done.html.twig', [
            'withdrawal_request' => $withdrawalRequest,
        ]);
    }

    /**
     * Consumes one token per limiter (IP or IPv6 /56, then the normalized e-mail). A request
     * already refused for its IP does not use the e-mail budget: a blocked sender cannot keep
     * the owner of an address from confirming a statement from elsewhere.
     */
    private function isRateLimited(Request $request, string $email): bool
    {
        if ($this->ipLimiter instanceof RateLimiterFactory &&
            !$this->ipLimiter->create(RateLimitKeys::ip($request->getClientIp()))->consume()->isAccepted()) {
            return true;
        }

        return $this->emailLimiter instanceof RateLimiterFactory && '' !== $email &&
            !$this->emailLimiter->create(RateLimitKeys::email($email))->consume()->isAccepted();
    }

    private function getCustomer(): ?CustomerInterface
    {
        $customer = $this->customerContext->getCustomer();

        return $customer instanceof CustomerInterface && $customer->getUser() instanceof \Sylius\Component\User\Model\UserInterface ? $customer : null;
    }

    /** Only resolves orders owned by the logged-in customer. */
    private function getCustomerOrder(string $orderNumber, ?CustomerInterface $customer): ?OrderInterface
    {
        if ('' === $orderNumber || !$customer instanceof \Sylius\Component\Core\Model\CustomerInterface) {
            return null;
        }

        /** @var ChannelInterface $channel */
        $channel = $this->channelContext->getChannel();

        return $this->orderMatcher->match($channel, $orderNumber, (string) $customer->getEmail(), $customer);
    }

    private function createPrefilledStatement(?CustomerInterface $customer, ?OrderInterface $order): WithdrawalStatement
    {
        $statement = new WithdrawalStatement();
        if ($customer instanceof \Sylius\Component\Core\Model\CustomerInterface) {
            $statement->customerName = $customer->getFullName();
            $statement->email = $customer->getEmail();
        }

        if ($order instanceof \Sylius\Component\Core\Model\OrderInterface) {
            $statement->orderNumber = $order->getNumber();
        }

        return $statement;
    }

    /** @return list<string> */
    private function getSelectedItemLabels(?OrderInterface $order, WithdrawalStatement $statement): array
    {
        if (!$order instanceof \Sylius\Component\Core\Model\OrderInterface || [] === $statement->orderItemIds) {
            return [];
        }

        $labels = [];
        foreach ($order->getItems() as $item) {
            if (in_array($item->getId(), $statement->orderItemIds, true)) {
                $labels[] = trim(sprintf('%s %s × %d', $item->getProductName() ?? '', $item->getVariantName() ?? '', $item->getQuantity()));
            }
        }

        return $labels;
    }
}
