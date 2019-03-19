<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerInterface;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerRegistry;
use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionChainProcessor;
use Shopware\Core\Checkout\Payment\Cart\Token\TokenFactoryInterface;
use Shopware\Core\Checkout\Payment\Cart\Token\TokenStruct;
use Shopware\Core\Checkout\Payment\Exception\InvalidOrderException;
use Shopware\Core\Checkout\Payment\Exception\TokenExpiredException;
use Shopware\Core\Checkout\Payment\Exception\UnknownPaymentMethodException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Struct\Uuid;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

class PaymentService
{
    /**
     * @var PaymentTransactionChainProcessor
     */
    private $paymentProcessor;

    /**
     * @var TokenFactoryInterface
     */
    private $tokenFactory;

    /**
     * @var EntityRepositoryInterface
     */
    private $paymentMethodRepository;

    /**
     * @var PaymentHandlerRegistry
     */
    private $paymentHandlerRegistry;

    public function __construct(
        PaymentTransactionChainProcessor $paymentProcessor,
        TokenFactoryInterface $tokenFactory,
        EntityRepositoryInterface $paymentMethodRepository,
        PaymentHandlerRegistry $paymentHandlerRegistry
    ) {
        $this->paymentProcessor = $paymentProcessor;
        $this->tokenFactory = $tokenFactory;
        $this->paymentMethodRepository = $paymentMethodRepository;
        $this->paymentHandlerRegistry = $paymentHandlerRegistry;
    }

    /**
     * @throws InvalidOrderException
     * @throws UnknownPaymentMethodException
     */
    public function handlePaymentByOrder(string $orderId, CheckoutContext $context, ?string $finishUrl = null): ?RedirectResponse
    {
        if (!Uuid::isValid($orderId)) {
            throw new InvalidOrderException($orderId);
        }

        return $this->paymentProcessor->process($orderId, $context->getContext(), $finishUrl);
    }

    /**
     * @throws UnknownPaymentMethodException
     * @throws TokenExpiredException
     */
    public function finalizeTransaction(string $paymentToken, Request $request, Context $context): TokenStruct
    {
        $paymentTokenStruct = $this->parseToken($paymentToken, $context);

        $paymentHandler = $this->getPaymentHandlerById($paymentTokenStruct->getPaymentMethodId(), $context);
        $paymentHandler->finalize($paymentTokenStruct->getTransactionId(), $request, $context);

        return $paymentTokenStruct;
    }

    /**
     * @throws TokenExpiredException
     */
    private function parseToken(string $token, Context $context): TokenStruct
    {
        $tokenStruct = $this->tokenFactory->parseToken($token, $context);

        if ($tokenStruct->isExpired()) {
            throw new TokenExpiredException($tokenStruct->getToken());
        }

        $this->tokenFactory->invalidateToken($tokenStruct->getToken(), $context);

        return $tokenStruct;
    }

    /**
     * @throws UnknownPaymentMethodException
     */
    private function getPaymentHandlerById(string $paymentMethodId, Context $context): PaymentHandlerInterface
    {
        $paymentMethods = $this->paymentMethodRepository->search(new Criteria([$paymentMethodId]), $context);

        /** @var PaymentMethodEntity|null $paymentMethod */
        $paymentMethod = $paymentMethods->get($paymentMethodId);
        if (!$paymentMethod) {
            throw new UnknownPaymentMethodException($paymentMethodId);
        }

        return $this->paymentHandlerRegistry->get($paymentMethod->getClass());
    }
}
