<?php

namespace Kitano\PaymentBundle\PaymentSystem;

use Kitano\PaymentBundle\PaymentSystem\SimpleCreditCardInterface;
use Kitano\PaymentBundle\Entity\Transaction;
use Kitano\PaymentBundle\Entity\AuthorizationTransaction;
use Kitano\PaymentBundle\KitanoPaymentEvents;
use Kitano\PaymentBundle\Repository\TransactionRepositoryInterface;
use Kitano\PaymentBundle\PaymentException;
use Kitano\PaymentBundle\PaymentSystem\HandlePaymentResponse;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Templating\EngineInterface;
use Symfony\Component\Routing\RouterInterface;

class FreePaymentSystem
    implements SimpleCreditCardInterface
{
    /* @var TransactionRepositoryInterface */
    protected $transactionRepository;

    /** @var null|LoggerInterface */
    protected $logger = null;

    /* @var EngineInterface */
    protected $templating;

    /** @var null| string */
    protected $internalBackToShopRoute = null;

    /** @var null| string */
    protected $externalBackToShopRoute = null;

    /** @var null| Router */
    protected $router = null;

    public function __construct(
        TransactionRepositoryInterface $transactionRepository,
        LoggerInterface $logger,
        EngineInterface $templating,
        $internalBackToShopRoute,
        $externalBackToShopRoute,
        RouterInterface $router
    )
    {
        $this->transactionRepository = $transactionRepository;
        $this->logger = $logger;
        $this->templating = $templating;

        $this->internalBackToShopRoute = $internalBackToShopRoute;
        $this->externalBackToShopRoute = $externalBackToShopRoute;
        $this->router = $router;
    }

    /**
     * {@inheritDoc}
     */
    public function renderLinkToPayment(Transaction $transaction)
    {
        return $this->templating->render('KitanoPaymentBundle:PaymentSystem:freeOrderLinkToPayment.html.twig', array(
            'orderId' => $transaction->getOrderId(),
            'transactionId' => $transaction->getId(),
            'transactionType' => 'free',
            'internalBackToShopRoute' => $this->internalBackToShopRoute,
            'externalBackToShopRoute' => $this->externalBackToShopRoute
        ));
    }


    /**
     * {@inheritDoc}
     */
    public function authorizeAndCapture(Transaction $transaction)
    {
        // Nothing to do
    }


    /**
     * {@inheritDoc}
     */
    public function handleBackToShop(Request $request)
    {
        $requestData = $request->request;
        $transaction = $this->transactionRepository->find($requestData->get('transactionId', null));
        if (round($transaction->getAmount(), 2) != 0) {
            throw new PaymentException("Amount is not null in freePaymentSystem, strange");
        }
        $transaction->setState(AuthorizationTransaction::STATE_APPROVED);
        $transaction->setSuccess(true);
        $transaction->setExtraData($requestData->all());
        $this->transactionRepository->save($transaction);

        return $this->container->get('router')->generate($route, $parameters, $referenceType);
        
        $externalBackToShopUrl = $this->router->generate($this->externalBackToShopRoute, array(
            'transactionId' => $transaction->getId(),
            'orderId' => $transaction->getOrderId()
        ));
        
        $response = new RedirectResponse($externalBackToShopUrl, "302");
        return new HandlePaymentResponse($transaction, $response);
    }

    /**
     * {@inheritDoc}
     */
    public function handlePaymentNotification(Request $request)
    {
        // no payment notification
    }

}