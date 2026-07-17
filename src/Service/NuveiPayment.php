<?php declare(strict_types=1);

namespace Swag\NuveiCheckout\Service;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\AbstractPaymentHandler;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerType;
use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStruct;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\Kernel;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Shopware\Core\System\StateMachine\Transition;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Nuvei synchronous payment handler for Shopware 6.7+.
 *
 * Migrated from the (removed in 6.7) SynchronousPaymentHandlerInterface
 * to the unified AbstractPaymentHandler introduced in Shopware 6.5.
 * Settle / Void / Refund actions continue to be served by the plugin's
 * own admin endpoint (Administration/Controller/OrderController.php),
 * so supports() returns false for both built-in handler types.
 */
class NuveiPayment extends AbstractPaymentHandler
{
    public function __construct(
        private readonly OrderTransactionStateHandler $transactionStateHandler,
        private readonly Nuvei $nuvei,
        private readonly StateMachineRegistry $stateMachineRegistry
    ) {}

    public function supports(PaymentHandlerType $type, string $paymentMethodId, Context $context): bool
    {
        // REFUND    -> handled via plugin's own admin API (/api/nuvei/check_order), not Shopware's built-in flow.
        // RECURRING -> rebilling not implemented yet.
        return false;
    }

    public function pay(
        Request $request,
        PaymentTransactionStruct $transaction,
        Context $context,
        ?Struct $validateStruct
    ): ?RedirectResponse {
        $this->nuvei->createLog($_REQUEST, 'pay()');

        if (empty($_REQUEST['nuveiTransactionId'])
            || !is_numeric($_REQUEST['nuveiTransactionId'])
        ) {
            $this->nuvei->createLog('A problem with nuveiTransactionId. Do not proccess the Order.');
            return null;
        }

        $orderTransactionId = $transaction->getOrderTransactionId();

        // set something like Pending
        $this->transactionStateHandler->processUnconfirmed($orderTransactionId, $context);

        // PaymentTransactionStruct no longer exposes the Order entity — look up the order_id from the transaction.
        $conn    = Kernel::getConnection();
        $orderId = $conn->fetchOne(
            'SELECT LOWER(HEX(order_id)) FROM order_transaction WHERE id = UNHEX(?)',
            [$orderTransactionId]
        );

        if ($orderId) {
            // set the State
            $this->stateMachineRegistry->transition(new Transition(
                OrderDefinition::ENTITY_NAME,
                $orderId,
                'process',
                'stateId'
            ), $context);
        }

        $json = json_encode([
            'nuveiTrId' => (int) $_REQUEST['nuveiTransactionId'],
        ]);

        $sql    = "UPDATE order_transaction "
            . "SET `custom_fields` = '" . $json . "' "
            . "WHERE HEX(id) = '" . $orderTransactionId . "'";
        $result = $conn->executeStatement($sql);

        return null;
    }
}
