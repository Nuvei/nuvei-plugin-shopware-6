<?php declare(strict_types=1);

namespace Swag\NuveiCheckout\Administration\Controller;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
//use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Swag\NuveiCheckout\Service\Nuvei;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
//use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['api']])]
/**
 * @author Nuvei
 */
class OrderController extends AbstractController
{
    private $nuvei;
    private $orderRepo;
    private $transactionRepo;
    private $currRepository;
    private $orderTransactionRepo;
    private $context;
    private $conn;
    
    public function __construct(
        Nuvei $nuvei,
        EntityRepository|EntityRepositoryInterface $orderRepo,
        EntityRepository|EntityRepositoryInterface $transactionRepo,
        EntityRepository|EntityRepositoryInterface $currRepository,
        EntityRepository|EntityRepositoryInterface $orderTransactionRepo,
        Connection $conn
    ) {
        $this->nuvei                = $nuvei;
        $this->orderRepo            = $orderRepo;
        $this->transactionRepo      = $transactionRepo;
        $this->currRepository       = $currRepository;
        $this->orderTransactionRepo = $orderTransactionRepo;
        $this->conn                 = $conn;
    }
    
    #[Route(path: '/api/nuvei/check_order', name: 'api.action.nuvei.check_order', defaults: ['auth_required' => false, 'XmlHttpRequest' => true], methods: ['POST', 'GET'])]
    /**
     * We do Ajax request to this method when Order datils page is loaded.
     * Here we decide is this a Nuvei Order, what actions we can do over it
     * and check for Nuvei notes related to the Transactions.
     * 
     * @Route("/api/nuvei/check_order", name="api.action.nuvei.check_order", defaults={"auth_required"=false, "XmlHttpRequest"=true}, methods={"GET", "POST"})
     * 
     * @param Request $request
     * @param Context $context
     * 
     * @return JsonResponse
     */
    public function checkForNuveiOrder(Request $request, Context $context)
    {
        $this->nuvei->createLog($request->query->all(), 'checkForNuveiOrder');
        
        $urlHash = $request->query->get('hash');
        
        if (empty($urlHash)) {
            $msg = 'URL hash is empty.';
            
            $this->nuvei->createLog($msg);
            
            return new JsonResponse([
                'success' => 0,
                'message' => $msg
            ]);
        }
        
        // extract the Order ID form the hash
        $matches = [];
        preg_match('/.*\/detail\/([a-zA-Z0-9]+)\/.*/', $urlHash, $matches);
        
        if (empty($matches[1])) {
            $msg = 'Can not find Order ID into hash.';
            
            $this->nuvei->createLog($msg);
            
            return new JsonResponse([
                'success' => 0,
                'message' => $msg
            ]);
        }
        
        $orderId = $matches[1];
        $this->nuvei->createLog('$orderId', $orderId);
        
        $canVoid    = false;
        $canSettle  = false;
        $canRefund  = false;
        $nuveiNotes = [];
        
        try {
            # search for the Order
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('id', $orderId));
            $order = $this->orderRepo->search($criteria, $context)->first();

            if (is_null($order)) {
                $msg = 'There is no Order or there is no Order object';

                $this->nuvei->createLog($order, $msg);

                return new JsonResponse([
                    'success' => 0,
                    'message' => $msg
                ]);
            }

            $customFields = $order->getCustomFields();

            if (is_null($customFields) || empty($customFields['nuveiTransactions'])) {
                $msg = 'There is no Nuvei data for this Order';

                $this->nuvei->createLog($msg);

                return new JsonResponse([
                    'success' => 0,
                    'message' => $msg
                ]);
            }
            
            $this->nuvei->createLog($customFields);
            
            foreach ($customFields['nuveiTransactions'] as $trId => $trData) {
                $nuveiNotes[$trId] = [
                    'date' => $trData['date'],
                    'note' => $trData['note'],
                ];
            }
            
            $last_tr = end($customFields['nuveiTransactions']);
            # /search for the Order

            # search for the Transaction
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('orderId', $order->getId()));
            $criteria->addAssociation('stateMachineState'); // Load the stateMachineState relation
            $transaction = $this->transactionRepo->search($criteria, $context)->first();

            if (is_null($transaction)) {
                $msg = 'There is no Transaction or there is no Transaction object';

                $this->nuvei->createLog($order, $msg);

                return new JsonResponse([
                    'success' => 0,
                    'message' => $msg
                ]);
            }
            
            $trState = $transaction->getStateMachineState()->getTechnicalName();
            
            $this->nuvei->createLog(
                [$transaction->getStateMachineState()->getName(), $trState],
                'transaction state'
            );
            # /search for the Transaction
        
            // set actions
            $trCreatedAt = $transaction->createdAt->getTimestamp();
            
            // actions for Sale and Settle
            if (in_array($last_tr['transaction_type'], ['Sale', 'Settle'])
                && 'approved' == strtolower($last_tr['status'])
                && in_array($last_tr['payment_method'], Nuvei::NUVEI_REFUND_PMS)
            ) {
                $canRefund  = true;
                
                $this->nuvei->createLog([strtotime('+48 hours'), $trCreatedAt]);
                
                if (time() <= strtotime('+48 hours') +  $trCreatedAt) {
                    $canVoid = true;
                }
            }
            
            // actions for Auth
            if ('Auth' == $last_tr['transaction_type'] && 0 < (float )$order->amountTotal) {
                $canSettle = true;
                
                if (in_array($last_tr['payment_method'], Nuvei::NUVEI_REFUND_PMS)
                    && time() <= strtotime('+48 hours') +  $trCreatedAt
                ) {
                    $canVoid = true;
                }
            }
            
            // last check for Void
            if ('cc_card' != $last_tr['payment_method']) {
                $canVoid = false;
            }
            
            // actions for Auth
            if (in_array($last_tr['payment_method'], Nuvei::NUVEI_REFUND_PMS)
                && 'refund_partially' == $trState
            ) {
                $canRefund = true;
            }
        }
        catch(\Exception $ex) {
            $this->nuvei->createLog($ex->getMessage(), 'Nuvei proccess exception', 'WARN');
            
            return new JsonResponse([
                'success' => 0,
                'message' => $ex->getMessage(),
            ]);
        }
        
        return new JsonResponse([
            'success'       => 1,
            'canSettle'     => $canSettle,
            'canVoid'       => $canVoid,
            'canRefund'     => $canRefund,
            'notes'         => $nuveiNotes,
            'orderNumber'   => $order->orderNumber,
        ]);
    }

    #[Route(path: '/api/nuvei/order_action', name: 'api.action.nuvei.order_action', defaults: ['auth_required' => false, 'XmlHttpRequest' => true], methods: ['POST', 'GET'])]
    /**
     * @param Request $request
     * @param Context $context
     * 
     * @return void|JsonResponse
     * 
     * @Route("/api/nuvei/order_action", name="api.action.nuvei.order_action", defaults={"auth_required"=false, "XmlHttpRequest"=true}, methods={"GET", "POST"})
     */
    public function orderAction(Request $request, Context $context)
    {
        $this->nuvei->createLog(@$_REQUEST, 'orderAction');
        
        $this->context  = $context;
        $order_number   = (int) $request->get('order_number');
        $action         = $request->get('action');
        
        if (0 == $order_number || !in_array($action, Nuvei::NUVEI_ORDER_ACTIONS)) {
            $msg = 'Invalid input datas.';
            
            $this->nuvei->createLog($msg);
            
            return new JsonResponse([
                'success' => 0,
                'message' => $msg
            ]);
        }
        
        // get Order object or Error message
        $order = $this->getOrderByNumer($order_number);
        // in case of error
        if (is_string($order)) {
            return new JsonResponse([
                'success' => 0,
                'message' => $order
            ]);
        }
        
        $curr_id    = $order->currencyId;
        $time       = date('YmdHis', time());
        
        // get ISO3 code or message
        $curr_code = $this->getCurrencyIsoCode($curr_id);
        // in case of error
        if (strlen($curr_code) > 3) {
            return new JsonResponse([
                'success' => 0,
                'message' => $curr_code
            ]);
        }
        
        $order_custom_fields = $order->customFields;
        
        if (is_null($order_custom_fields) || empty($order_custom_fields['nuveiTransactions'])) {
            $msg = 'There is no Nuvei data for this Order.';
            
            $this->nuvei->createLog($order_custom_fields, $msg);
            
            return new JsonResponse([
                'success' => 0,
                'message' => $msg
            ]);
        }
        
        $last_tr = [];
        
        foreach (array_reverse($order_custom_fields['nuveiTransactions'], true)  as $trId => $trData) {
            if ('settle' == $action && 'Auth' == $trData['transaction_type']) {
//                $last_tr['auth_code']   = $trData['auth_code'];
                $last_tr['tr_id']       = $trId;
                break;
            }
            
            if ('void' == $action && in_array($trData['transaction_type'], ['Settle', 'Sale', 'Auth'])) {
//                $last_tr['auth_code']   = $trData['auth_code'];
                $last_tr['tr_id']       = $trId;
                break;
            }
            
            if ('refund' == $action && in_array($trData['transaction_type'], ['Settle', 'Sale'])) {
//                $last_tr['auth_code']   = $trData['auth_code'];
                $last_tr['tr_id']       = $trId;
                break;
            }
        }
        
        if (empty($last_tr)) {
            $msg = 'There is no Nuvei previous data for this Order.';
            
            $this->nuvei->createLog($order_custom_fields, $msg);
            
            return new JsonResponse([
                'success' => 0,
                'message' => $msg
            ]);
        }
        
        $params = [
            'clientRequestId'       => $time . '_' . $order_number . '_' . uniqid(),
            'clientUniqueId'        => $order_number . '_' . $time . '_' . uniqid(),
            'amount'                => $order->amountTotal,
            'currency'              => $curr_code,
            'relatedTransactionId'  => $last_tr['tr_id'],
//            'authCode'              => $last_tr['auth_code'],
        ];
        
//        $checksum_params = ['merchantId', 'merchantSiteId', 'clientRequestId', 'clientUniqueId', 'amount', 'currency', 'relatedTransactionId', 'authCode', 'url', 'timeStamp'];
        
//        if (empty($params['authCode'])) {
//            unset($params['authCode']);
            
            $checksum_params = ['merchantId', 'merchantSiteId', 'clientRequestId', 'clientUniqueId', 'amount', 'currency', 'relatedTransactionId', 'url', 'timeStamp'];
//        }
        
        $resp = $this->nuvei->callRestApi($action . 'Transaction', $params, $checksum_params);
        
        if(!$resp
            || !is_array($resp)
            || @$resp['status'] == 'ERROR'
            || @$resp['transactionStatus'] == 'ERROR'
            || @$resp['transactionStatus'] == 'DECLINED'
        ) {
            $msg = ucfirst($action) . ' request return Error/Decline.';
            
            if (!empty($resp['paymentMethodErrorReason'])) {
                $msg = $resp['paymentMethodErrorReason'];
            }
            elseif (!empty($resp['gwErrorReason'])) {
                $msg = $resp['gwErrorReason'];
            }
            elseif (!empty($resp['paymentMethodErrorReason'])) {
                $msg = $resp['paymentMethodErrorReason'];
            }
            elseif (!empty($resp['reason'])) {
                $msg = $resp['reason'];
            }
            
            $this->nuvei->createLog($msg);
            
            return new JsonResponse([
                'success' => 0,
                'message' => $msg
            ]);
        }
        
        // TODO if the action is Void stop the rebilling
        
//        $this->orderTransactionRepo->create($params, $context);
//        $tr_data = $this->getTransactionByOrderId($order->id);
//        // in case of error message
//        if (is_string($tr_data)) {
//            return $tr_data;
//        }
        
        $msg = 'Request done, please refresh the page to update details.';
        
        $this->nuvei->createLog($msg, 'Success message.');
        
        return new JsonResponse([
            'success' => 1,
            'message' => $msg
        ]);
    }
    
    #[Route(path: '/api/nuvei/get_orders_data', name: 'api.action.nuvei.get_orders_data', defaults: ['auth_required' => false, 'XmlHttpRequest' => true], methods: ['POST', 'GET'])]
    /**
     * Receive a list with order numbers as GET parameter.
     * Then get from the DB the custom_fields for each order and check in it
     * for Nuvei transactions block. In the block search for total_curr_alert flag.
     * 
     *  @Route("/api/nuvei/get_orders_data", name="api.action.nuvei.get_orders_data", defaults={"auth_required"=false, "XmlHttpRequest"=true}, methods={"GET", "POST"})
     * 
     * @param Request $request
     * @param Context $context
     */
    public function getOrdersData(Request $request, Context $context)
    {
        $orders = json_decode($request->get('orders'));
        $sql    = 
            "SELECT order_number, custom_fields FROM `order` "
            . "WHERE order_number IN (" . implode(',', $orders) . ") "
                . "AND custom_fields <> '' "
                ;
        
        $fraud_orders   = [];
        $results        = $this->conn->executeQuery($sql)->fetchAllAssociative();
        
        foreach ($results as $order_data) {
            if (empty($order_data['custom_fields']['nuveiTransactions'])) {
                continue;
            }
            
            $nuveiTransactions = json_decode($order_data['custom_fields']['nuveiTransactions'], true);
            
            if (empty($nuveiTransactions) || !is_array($nuveiTransactions)) {
                $this->nuvei->createLog($nuveiTransactions, 'Empty transaction data.', 'WARN');
                continue;
            }
            
            foreach ($nuveiTransactions as $tr => $tr_data) {
                if (!empty($tr_data['total_curr_alert'])) {
                    $fraud_orders[] = $order_data['order_number'];
                    break;
                }
            }
        }
        
        exit(json_encode([
            'success'   => 1,
            'data'      => json_encode($fraud_orders)
        ]));
    }
    
    /**
     * Common function, getting Order data by its number.
     * 
     * @param int $order_number
     * @return object|string
     */
    private function getOrderByNumer($order_number)
    {
        $this->nuvei->createLog($order_number, 'getOrderByNumer');
        
        try {
            # search for the Order
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('orderNumber', $order_number));
            $order = $this->orderRepo->search($criteria, $this->context)->first();

            if (is_null($order)) {
                $msg = 'There is no Order or there is no Order object';

                $this->nuvei->createLog($order, $msg);

                return $msg;
            }
            
            return $order;
        }
        catch(\Exception $ex) {
            $msg = 'Exception when try to get the Order by its order number.';
            
            $this->nuvei->createLog($ex->getMessage(), $msg);

            return $msg;
        }
    }
    
    /**
     * @param string $order_id
     * @return object|string
     */
    private function getTransactionByOrderId($order_id)
    {
        $this->nuvei->createLog($order_id, 'getTransactionByOrderId');
        
        try {
            # search for the Order
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('orderId', $order_id));
            $tr = $this->orderTransactionRepo->search($criteria, $this->context)->last();

            if (is_null($tr)) {
                $msg = 'There is no Transaction or there is no Transaction object';

                $this->nuvei->createLog($tr, $msg);

                return $msg;
            }
            
            return $tr;
        }
        catch(\Exception $ex) {
            $msg = 'Exception when try to get the Transaction by its ID.';
            
            $this->nuvei->createLog($ex->getMessage(), $msg);

            return $msg;
        }
    }
    
    /**
     * Get currency ISO 3 code, by its ID.
     * 
     * @param string $curr_id
     * @return string
     */
    private function getCurrencyIsoCode($curr_id)
    {
        $this->nuvei->createLog($curr_id, 'getCurrencyIsoCode');
        
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('id', $curr_id));
        
        $curr_data = $this->currRepository->search($criteria, $this->context)->first();
        
        if (empty($curr_data->isoCode)) {
            $msg = 'Can not find currrency ISO3 code.';
			
            $this->nuvei->createLog($curr_data, $msg);
            
            return $msg;
        }
        
        return $curr_data->isoCode;
    }
    
}