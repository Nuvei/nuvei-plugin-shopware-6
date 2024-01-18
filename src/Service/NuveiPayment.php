<?php declare(strict_types=1);

namespace Swag\NuveiCheckout\Service;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\SynchronousPaymentHandlerInterface;
use Shopware\Core\Checkout\Payment\Cart\SyncPaymentTransactionStruct;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Kernel;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Shopware\Core\System\StateMachine\Transition;
use Swag\NuveiCheckout\Service\Nuvei;
use Symfony\Component\HttpFoundation\RequestStack;

class NuveiPayment implements SynchronousPaymentHandlerInterface
{
    private $transactionStateHandler;
    private $nuvei;
    private $stateMachineRegistry;
    private $sessionObj;

    public function __construct(
        OrderTransactionStateHandler $transactionStateHandler,
        Nuvei $nuvei,
        StateMachineRegistry $stateMachineRegistry,
        RequestStack $requestStack
    ) {
        $this->transactionStateHandler  = $transactionStateHandler;
        $this->nuvei                    = $nuvei;
        $this->stateMachineRegistry     = $stateMachineRegistry;
        $this->sessionObj               = $requestStack->getSession();
        
        $this->sessionObj->set('nuvei_order_details', []);
    }

    public function pay(
        SyncPaymentTransactionStruct $transaction, 
        RequestDataBag $dataBag, 
        SalesChannelContext $salesChannelContext
    ): void
    {
        $this->nuvei->createLog($_REQUEST, 'pay()');
        
        if (empty($_REQUEST['nuveiTransactionId']) 
            || !is_numeric($_REQUEST['nuveiTransactionId'])
        ) {
            $this->nuvei->createLog('A problem with nuveiTransactionId. Do not proccess the Order.');
            return;
        }
        
        $context = $salesChannelContext->getContext();
        
        // set something like Pending
        $this->transactionStateHandler->processUnconfirmed($transaction->getOrderTransaction()->getId(), $context);
        
        // set the State
        $this->stateMachineRegistry->transition(new Transition(
            OrderDefinition::ENTITY_NAME,
            $transaction->getOrder()->getId(),
            'process',
            'stateId'
        ), $context);
        
        $json   = json_encode([
            'nuveiTrId' => (int) $_REQUEST['nuveiTransactionId'],
        ]);
        
        $conn   = Kernel::getConnection();
        $sql    = "UPDATE order_transaction "
            . "SET `custom_fields` = '" . $json . "' "
            . "WHERE HEX(id) = '" . $transaction->getOrderTransaction()->getId() . "'";
        $result = $conn->executeStatement($sql);
    }
}
