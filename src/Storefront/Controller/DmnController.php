<?php

namespace Swag\NuveiCheckout\Storefront\Controller;

use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Kernel;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Shopware\Core\System\StateMachine\Transition;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Controller\StorefrontController;
use Swag\NuveiCheckout\Service\Nuvei;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @author Nuvei
 * 
 * @Route(defaults={"_routeScope"={"storefront"}})
 */
class DmnController extends StorefrontController
{
    private $nuvei;
    private $systemConfigService;
    private $orderTransactionRepo;
    private $orderRepo;
    private $stateMachineStateRepository;
    private $request;
    private $context;
    private $order;
    private $transaction;
    private $stateMachineRegistry;

    public function __construct(
        Nuvei $nuvei, 
        SystemConfigService $systemConfigService,
        EntityRepositoryInterface $orderTransactionRepo,
        EntityRepositoryInterface $orderRepo,
        StateMachineRegistry $stateMachineRegistry
    ) {
        $this->nuvei                        = $nuvei;
        $this->systemConfigService          = $systemConfigService;
        $this->orderTransactionRepo         = $orderTransactionRepo;
        $this->orderRepo                    = $orderRepo;
        $this->stateMachineRegistry         = $stateMachineRegistry;
    }
    
    /**
     * @Route("/nuvei_dmn/", name="frontend.nuveicheckout.dmn", defaults={"XmlHttpRequest"=true, "csrf_protected"=false}, methods={"GET", "POST"})
     */
    public function getDmn(Request $request, Context $context): JsonResponse
    {
        $this->nuvei->createLog(@$_REQUEST, 'getDmn');
        
        # manually stop DMN process
//        $msg = 'DMN report: Manually stopped process.';
//        $this->nuvei->createLog(http_build_query(@$_REQUEST), $msg);
//        return new JsonResponse(['message' => $msg]);
        # /manually stop DMN process
        
        if ('CARD_TOKENIZATION' == $this->getRequestParam('type')) {
			$msg = 'DMN CARD_TOKENIZATION accepted.';
            
            $this->nuvei->createLog($msg);
            
            return new JsonResponse([
                'message' => $msg
            ]);
		}
        
        $req_status         = $this->nuvei->getRequestStatus();
        $dmnType            = $this->getRequestParam('dmnType');
        $tr_id              = (int) $this->getRequestParam('TransactionID');
        $transactionType    = $this->getRequestParam('transactionType');
        $this->context      = $context; // to be used from private methods
        // in the Requests made from the admin this holds the Order Number
        $clientUniqueId     = $this->getRequestParam('clientUniqueId');
        
        if (empty($req_status) && !$dmnType) {
			$msg = 'The Status is empty!';
            
            $this->nuvei->createLog($msg);
            
            return new JsonResponse([
                'error' => $msg
            ]);
		}
        
        // in case of Pending DMN
        if ('pending' == strtolower($req_status)) {
            $msg = 'Pending DMN. Wait for the final status if not already came.';
            
            $this->nuvei->createLog($msg);
            
            return new JsonResponse([
                'message' => $msg
            ]);
        }
        
        if (!$this->validateChecksum()) {
            return new JsonResponse([
                'error' => 'Checksum validation faild.'
            ]);
        }
        
        // TODO - Subscription State DMN
//        if ('subscription' == $dmnType) {
//            $this->dmnSubscrState();
//        }
        
        if(empty($tr_id) || !is_numeric($tr_id)) {
            $msg = 'TransactionID is empty or not numeric.';
            
			$this->nuvei->createLog($msg);
			
            return new JsonResponse([
                'error' => $msg
            ]);
		}
        
        // TODO - Subscription Payment DMN
//        if ('subscriptionPayment' == $dmnType && 0 != $tr_id) {
//            $this->dmnSubscrPayment();
//        }
        
		if(empty($transactionType)) {
            $msg = 'transactionType is empty.';
            
			$this->nuvei->createLog($msg);
			
            return new JsonResponse([
                'error' => $msg
            ]);
		}
		
        # Sale and Auth
        if(in_array($transactionType, array('Sale', 'Auth'))) {
			$resp = $this->dmnSaleAuth($tr_id); // array to return as JSON
            
            $this->nuvei->createLog($resp);
            
            return new JsonResponse($resp, !empty($resp['error']) ? 400 : 200);
        }
        
        $clientUniqueId_parts = explode('_', $clientUniqueId);
        
        # Settle, Void and Refund
        if (in_array($transactionType, ['Settle', 'Void', 'Refund', 'Credit'])) {
            $resp = $this->dmnSettleVoidRefund($clientUniqueId_parts[0], $transactionType);
            
            return new JsonResponse($resp);
        }
        
        // all other
        return new JsonResponse([
            'message' => 'Not recognized DMN.'
        ]);
    }
    
    /**
     * Validate the DMN.
     * On error print message and exit.
     * 
     * @return bool
     */
    private function validateChecksum()
    {
        $advanceResponseChecksum = $this->getRequestParam('advanceResponseChecksum');
		$responsechecksum        = $this->getRequestParam('responsechecksum');
        
        if (empty($advanceResponseChecksum) && empty($responsechecksum)) {
			$this->nuvei->createLog('Error - advanceResponseChecksum '
                . 'and responsechecksum parameters are empty.');
            
            return false;
		}
        
        # advanceResponseChecksum case
        if (!empty($advanceResponseChecksum)) {
            $str = $this->getRequestParam('totalAmount')
                . $this->getRequestParam('currency') 
                . $this->getRequestParam('responseTimeStamp')
                . $this->getRequestParam('PPP_TransactionID') 
                . $this->nuvei->getRequestStatus()
                . $this->getRequestParam('productId');
            
            $full_str   = $this->systemConfigService->get('SwagNuveiCheckout.config.nuveiSecretKey'). $str;
            $hash_str   = hash(
                $this->systemConfigService->get('SwagNuveiCheckout.config.nuveiHash'),
                $full_str
            );
            
            if ($hash_str != $advanceResponseChecksum) {
                $this->nuvei->createLog($str, 'Error - advanceResponseChecksum validation fail.');
                
                return false;
            }
            
            return true;
        }
        
        # subscription DMN with responsechecksum case
        $concat                 = '';
        $request_params_keys    = array_keys($_REQUEST);
        $custom_params_keys     = array(
			'responsechecksum',
		);
        
        $dmn_params_keys = array_diff($request_params_keys, $custom_params_keys);
        
        foreach($dmn_params_keys as $key) {
            $concat .= $this->getRequestParam($key, '');
        }
        
        $concat_final   = $concat . $this->systemConfigService->get('SwagNuveiCheckout.config.nuveiSecretKey');
        $checksum       = hash(
            $this->systemConfigService->get('SwagNuveiCheckout.config.nuveiHash'),
            $concat_final
        );
        
        if ($responsechecksum != $checksum) {
            $this->nuve->createLog(
                [
                    'urldecode($concat)' => urldecode($concat),
                    'utf8_encode($concat)' => utf8_encode($concat),
                ],
                'Error - responsechecksum validation fail.'
            );
            
            return false;
		}
		
        return true;
    }
    
    /**
     * TODO - Here is the logic for Subscription State DMN.
     */
    private function dmnSubscrState()
    {
        /*
        $subscriptionState  = strtolower($this->getRequestParam('subscriptionState'));
        $subscriptionId     = $this->getRequestParam('subscriptionId');
        $cri_parts          = explode('_', $this->getRequestParam('clientRequestId'));
        $order_id           = 0;

        if (empty($cri_parts) 
            || empty($cri_parts[0]) 
            || !is_numeric($cri_parts[0])
        ) {
            $this->nuvei->createLog($cri_parts, 'DMN Subscription Error with Client Request Id parts:');

            header('Content-Type: text/plain');
            exit('DMN Subscription Error with Client Request Id parts.');
        }

        $order_id   = (int) $cri_parts[0];
        $this->getOrder($order_id);

        if (empty($subscriptionState)) {
            $this->nuvei->createLog($subscriptionState, 'DMN Subscription Error - subscriptionState is empty.');

            header('Content-Type: text/plain');
            exit('DMN Subscription Error - subscriptionState is empty');
        }

        if ('active' == $subscriptionState) {
            $msg = $this->l('Subscription is Active.') . ' '
                . $this->l('Subscription ID: ') . $subscriptionId . ' '
                . $this->l('Plan ID: ') . $this->getRequestParam('planId');

            // save the Subscription ID
            $ord_subscr_ids = '';
            $sql            = "SELECT subscr_ids FROM safecharge_order_data WHERE order_id = " . $order_id;
            $res            = Db::getInstance()->executeS($sql);

            $this->nuvei->createLog($res, 'Order Rebilling data');

            if($res && is_array($res)) {
                $first_res = current($res);

                if(is_array($first_res) && !empty($first_res['subscr_ids'])) {
                    $ord_subscr_ids = $first_res['subscr_ids'];
                }
            }

            // just add the ID without the details, we need only the ID to cancel the Subscription
            if (!in_array($subscriptionId, $ord_subscr_ids)) {
                $ord_subscr_ids = $subscriptionId;
            }

            $sql = "UPDATE `safecharge_order_data` "
                . "SET subscr_ids = " . $ord_subscr_ids . " "
                . "WHERE order_id = " . $order_id;
            $res = Db::getInstance()->execute($sql);

            if(!$res) {
                $this->nuvei->createLog(
                    array(
                        'subscriptionId'    => $subscriptionId,
                        'order_id'          => $order_id,
                    ),
                    'DMN Error - the subscription ID was not added to the Order data',
                    'WARN'
                );
            }
            // save the Subscription ID END
        }
        elseif ('inactive' == $subscriptionState) {
            $msg = $this->l('Subscription is Inactive.') . ' '
                . $this->l('Subscription ID: ') . $subscriptionId;
        }
        elseif ('canceled' == $subscriptionState) {
            $msg = $this->l('Subscription was canceled.') . ' '
                .$this->l('Subscription ID: ') . $subscriptionId;
        }

        $message            = new MessageCore();
        $message->id_order  = $order_id;
        $message->private   = true;
        $message->message   = $msg;
        $message->add();

        // save the state
        $sql = "UPDATE `safecharge_order_data` "
            . "SET subscr_state = '" . $subscriptionState . "' "
            . "WHERE order_id = " . $order_id;
        $res = Db::getInstance()->execute($sql);

        if(!$res) {
            $this->nuvei->createLog(
                array(
                    'subscriptionId'    => $subscriptionId,
                    'order_id'          => $order_id,
                    'message'           => Db::getInstance()->getMsgError(),
                ),
                'DMN Error - the Subscription State was not added to the Order data',
                'WARN'
            );
        }

        header('Content-Type: text/plain');
        exit('DMN received.');
         * 
         */
    }
    
    /**
     * TODO - Here is the logic for Subscription Payment DMN
     */
    private function dmnSubscrPayment()
    {
        /*
        $cri_parts  = explode('_', $this->getRequestParam('clientRequestId'));
        $order_id   = 0;

        if (empty($cri_parts) || empty($cri_parts[0]) || !is_numeric($cri_parts[0])) {
            $this->nuvei->createLog($cri_parts, 'DMN Subscription Error with Client Request Id parts:');

            header('Content-Type: text/plain');
            exit('DMN Subscription Error with Client Request Id parts.');
        }

        $order_id   = (int) $cri_parts[0];
        $order_info = $this->getOrder($order_id);
        $currency   = new Currency((int)$order_info->id_currency);

        $msg = sprintf(
            // translators: %s: the status of the Payment
            $this->l('Subscription Payment with Status %s was made. '),
            $req_status
        )
            . $this->l('Plan ID: ') . $this->getRequestParam('planId') . '. '
            . $this->l('Subscription ID: ') . $this->getRequestParam('subscriptionId') . '. '
            . $this->l('Amount: ') . $this->module->formatMoney($this->getRequestParam('totalAmount'), $currency->iso_code) . ' '
            . $this->l('TransactionId: ') . $this->getRequestParam('TransactionID') . '.';

        $this->nuvei->createLog($msg, 'Subscription DMN Payment');

        $message            = new MessageCore();
        $message->id_order  = $order_id;
        $message->private   = true;
        $message->message   = $msg;
        $message->add();

        header('Content-Type: text/plain');
        exit('DMN received.');
         * 
         */
    }
    
    /**
     * Help method for Auth and Sale DMNs logic.
     * 
     * @param string $req_status    DMN status
     * @param int $tr_id            DMN transaction ID
     * 
     * @return array
     */
    private function dmnSaleAuth($tr_id)
    {
        $this->nuvei->createLog('dmnSaleAuth()');

        $tries              = 0;
        $order_id           = '';
        $max_tries          = 5;
        $sleep_time         = 5;
        $order_request_time = $this->getRequestParam('customField3', 0); // time of create/update order
		
        # for Auth and Sale implement Auto-Void if more than 30 minutes passed and still no Order
        if ($order_request_time > 0
			&& ( time() - $order_request_time > 1800 ) // more than 30 minutes
		) {
            $void_params    = [
                'clientUniqueId'        => gmdate('YmdHis') . '_' . uniqid(),
                'amount'                => (string) $this->getRequestParam('totalAmount'),
                'currency'              => $this->getRequestParam('currency'),
                'relatedTransactionId'  => $this->getRequestParam('TransactionID'),
            ];
            
            $this->nuvei->createLog('Try to Void a transaction by not existing WC Order.');
            
            $resp = $this->nuvei->callRestApi(
                'voidTransaction',
                $void_params,
                array('merchantId', 'merchantSiteId', 'clientRequestId', 'amount', 'currency', 'timeStamp')
            );
            
            // Void Success
            if (!empty($resp['transactionStatus'])
                && 'APPROVED' == $resp['transactionStatus']
                && !empty($resp['transactionId'])
            ) {
                return [
                    'message' => 'The searched Order does not exists, a Void request was made for this Transacrion.'
                ];
            }
		}
        # /for Auth and Sale implement Auto-Void if more than 30 minutes passed and still no Order
        
        // for the transaction
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customFields.nuveiTrId', $tr_id));
        
        // for the order
        $ordCr = new Criteria();
        
        $this->nuvei->createLog($tr_id, 'Search for Transaction with customFields.nuveiTrId');
        
        do {
            $tries++;
            $this->transaction = $this->orderTransactionRepo->search($criteria, $this->context)->first();
            
            if (is_object($this->transaction) && method_exists($this->transaction, 'getOrderId')) {
                $order_id = $this->transaction->getOrderId();
                
                $this->nuvei->createLog(
                    [
                        'transaction id'    => $this->transaction->getId(),
                        'order_id'          => $order_id,
                    ],
                    'Found Transaction by Nuvei Tr ID'
                );
                
            }
            
            if(empty($order_id)) {
                $this->nuvei->createLog($this->transaction, 'The DMN class wait for the Order.');
                sleep($sleep_time);
            }
            else {
                // check for slow saving process
                $ordCr->addFilter(new EqualsFilter('id', $order_id));
                
                $this->order    = $this->orderRepo->search($ordCr, $this->context)->first();
                $orderState     = $this->order->stateMachineState->technicalName;
                
                $this->nuvei->createLog(
                    [
                        'order state'   => $this->order->stateMachineState->name,
                        'order id'      => $order_id,
                        'order number'  => $this->order->orderNumber,
                    ],
                    'Found Order by Transaction'
                );
                
                // the first Nuvei state of an order must be STATE_IN_PROGRESS
                // default State for the Order is Open
                $this->nuvei->createLog($this->order->stateMachineState->name, 'Check the Order State:');
                
                if (OrderStates::STATE_OPEN == $this->order->stateMachineState->technicalName) {
                    $this->nuvei->createLog(
                        [
                            '$orderState' => $orderState,
                            'orderNumber' => $this->order->orderNumber,
                        ],
                        'The Order State must be in_progress. Wait few seconds and check again.'
                    );
                    
                    $order_id = ''; // clear the id
                    sleep($sleep_time);
                }
            }
        }
        while($tries <= $max_tries && empty($order_id));
        
        // already Complete or Cancelled order
        if (in_array(
            $this->order->stateMachineState->technicalName,
            [OrderStates::STATE_COMPLETED, OrderStates::STATE_CANCELLED]
        )) {
            $msg = 'This Order is alaedy Completed or Cancelled.';

            $this->nuvei->createLog($this->order->stateMachineState->technicalName, $msg);

            return [
                'message' => $msg
            ];
        }
        
        // the Order was not found
        if(empty($order_id)) {
            $msg = 'Can not find Order ID.';
                    
            $this->nuvei->createLog($msg);
            
            return [
                'error' => $msg
            ];
        }
        
        $up_resp = $this->updateCustomFields($order_id);
        
        if (!isset($up_resp['continue'])) {
            return $up_resp; // array with a message
        }
        
        // TODO try to start a Subscription
        
        return $this->changeOrderStatus(); // array with a message
    }
    
    private function dmnSettleVoidRefund($order_number, $transactionType)
    {
        $this->nuvei->createLog($order_number, $transactionType);
        
        // for the order
        $ordCr = new Criteria();
        $ordCr->addFilter(new EqualsFilter('orderNumber', $order_number));
        
        $this->order    = $this->orderRepo->search($ordCr, $this->context)->first();
        $order_id       = $this->order->id;
        
        // for the transaction
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('orderId', $order_id));
        
        $this->transaction = $this->orderTransactionRepo->search($criteria, $this->context)->last();
        
        // cases when can not accept this transaction type
        if ('Settle' == $transactionType
            && 'authorized' != $this->transaction->stateMachineState->technicalName
        ) {
            $msg = 'Can not apply Settle on transaction with State different than Authorized.';

            $this->nuvei->createLog($this->transaction->stateMachineState->technicalName, $msg);
            return [
                'message' => $msg
            ];
        }
        
        if ('Void' == $transactionType
            && !in_array(
                $this->transaction->stateMachineState->technicalName,
                ['authorized', 'paid']
            )
        ) {
            $msg = 'Can not apply Void on transaction with State different than Authorized and Paid.';

            $this->nuvei->createLog($this->transaction->stateMachineState->technicalName, $msg);
            return [
                'message' => $msg
            ];
        }
        
        if (in_array($transactionType, ['Refund', 'Credit'])
            && 'paid' != $this->transaction->stateMachineState->technicalName
        ) {
            $msg = 'Can not apply Refund on transaction with State different than Paid.';

            $this->nuvei->createLog($this->transaction->stateMachineState->technicalName, $msg);
            return [
                'message' => $msg
            ];
        }
        // /cases when can not accept this transaction type
        
        $up_resp = $this->updateCustomFields($order_id);
        
        if (!isset($up_resp['continue'])) {
            return $up_resp; // array with a message
        }
        
        // update Nuvei Transaction ID into the custom fields
        $custom_fields = $this->transaction->customFields;
        
        if (is_null($custom_fields)) {
            $custom_fields = [];
        }
        
        $custom_fields['nuveiTrId'] = (int) $this->getRequestParam('TransactionID');
        
        $conn   = Kernel::getConnection();
        $sql    = "UPDATE order_transaction "
                . "SET `custom_fields` = '" . json_encode($custom_fields) . "' "
                . "WHERE HEX(id) = '" . $this->transaction->getId() . "'";
        $conn->executeStatement($sql);
        
        return $this->changeOrderStatus(); // array with a message
    }
    
    /**
     * Update Order Custom Fields
     * 
     * @return JsonResponse
     */
    private function updateCustomFields()
    {
        $tr_id                  = (int) $this->getRequestParam('TransactionID');
        $this->nuveiOrderData   = $this->order->getCustomFields();
        
        $this->nuvei->createLog($this->nuveiOrderData, '$this->nuveiOrderData');
        
        $tr_type    = $this->getRequestParam('transactionType');
        $status     = $this->getRequestParam('Status');
        
        if (is_null($this->nuveiOrderData) || empty($this->nuveiOrderData)) {
            $this->nuveiOrderData = [
                'nuveiTransactions' => [],
//                'nuveiNotes'        => [],
            ];
        }
        if (!isset($this->nuveiOrderData['nuveiTransactions'])) {
            $this->nuveiOrderData = [
                'nuveiTransactions' => [],
            ];
        }
//        if (!isset($this->nuveiOrderData['nuveiNotes'])) {
//            $this->nuveiOrderData = [
//                'nuveiNotes' => [],
//            ];
//        }
        
        // check for existing data for this Transaction ID
        if (!empty($this->nuveiOrderData['nuveiTransactions'][$tr_id])) {
            // when Transaction type is same
            if ($this->nuveiOrderData['nuveiTransactions'][$tr_id]['transaction_type'] == $tr_type) {
                // repeating DMN
                if ($status == $this->nuveiOrderData['nuveiTransactions'][$tr_id]['status']) {
                    $msg = 'We already have a record for this DMN.';
                    $this->nuvei->createLog($msg);

                    return [
                        'message' => $msg
                    ];
                }
                
                // new status after approved status
                if ('approved' != strtolower($status)
                    && 'approved' == strtolower($this->nuveiOrderData['nuveiTransactions'][$tr_id]['status'])
                ) {
                    $msg = 'This Order is already Approved.';
                    $this->nuvei->createLog($msg);

                    return [
                        'message' => $msg
                    ];
                }
            }
            // same Transaction ID, but different Transaction Type
            else {
                $msg = 'New Transaction type coming for existing Transaction ID.';
                $this->nuvei->createLog($msg);

                return [
                    'message' => $msg
                ];
            }
            
//            $msg = 'New Transaction type coming for existing Transaction ID.';
//            $this->nuvei->createLog($msg);
//
//            return new JsonResponse([
//                'message' => $msg
//            ]);
        }
        
        $this->nuveiOrderData['nuveiTransactions'][$tr_id] = [
            'status'                    => $status,
            'auth_code'                 => $this->getRequestParam('AuthCode'),
            'related_transaction_id'    => $this->getRequestParam('relatedTransactionId'),
            'transaction_type'          => $tr_type,
            'payment_method'            => $this->getRequestParam('payment_method'),
            'total_amount'              => number_format($this->getRequestParam('totalAmount'), 2, '.'),
            'currency'                  => $this->getRequestParam('currency'),
            'date'                      => date('Y-m-d H:i:s'),
            'sw_order_id'               => $this->order->getId(),
            'sw_order_number'           => $this->order->orderNumber,
            'sw_transaction_id'         => $this->transaction->getId(),
        ];
        
        // save few numbers from SW tables
        $this->nuvei->createLog($this->nuveiOrderData);
        
        return [
            'continue' => true
        ];
    }
    
    /**
     * Change the status of the order.
     * 
     * @return array
     */
    private function changeOrderStatus()
    {
        $this->nuvei->createLog('changeOrderStatus()');
		
        $msg				= '';
        // dmn data
        $transactionType    = $this->getRequestParam('transactionType');
        $totalAmount        = (float) $this->getRequestParam('totalAmount');
        $status             = $this->getRequestParam('Status');
        // order data
        $order_amount       = round((float) $this->order->amountTotal, 2);
        
        $gw_data = 'Status: ' . $status
			. ',<br/> Transaction Type: ' . $transactionType
			. ',<br/> Transaction ID: ' . $this->getRequestParam('TransactionID')
			. ',<br/> Auth Code: ' . $this->getRequestParam('AuthCode')
			. ',<br/> Related Transaction ID: ' . $this->getRequestParam('relatedTransactionId')
			. ',<br/> Payment Method: ' . $this->getRequestParam('payment_method')
			. ',<br/> Total Amount: ' . number_format($this->getRequestParam('totalAmount'), 2, '.')
			. ',<br/> Currency: ' . $this->getRequestParam('currency');
        
        $msg                = $gw_data;
        $orderState         = '';
        $transactionState   = '';
        
        switch($status) {
            case 'CANCELED':
                $orderState         = 'cancel';
                $transactionState   = 'cancel';
                
                break;

            case 'APPROVED':
                $orderState         = 'complete';
                $transactionState   = 'paid';
				
                // Void
                if('Void' == $transactionType) {
                    $transactionState = 'cancel';
                    break;
                }
                
                // Refund
                if(in_array($transactionType, array('Credit', 'Refund'))) {
                    if ($order_amount == $totalAmount) {
                        $transactionState = 'refund';
                    }
                    else {
                        $transactionState = 'refund_partially';
                    }
                    
                    break;
                }
                
                if('Auth' == $transactionType) {
                    $transactionState   = 'authorize';
                }
                elseif('Settle' == $transactionType) {
                    
                }
				// compare DMN amount and Order amount
				elseif('Sale' == $transactionType && $order_amount !== $totalAmount) {
                    $msg .= 'The paid amount is different than the Order amount. Please check!';
				}
                
                break;

            case 'ERROR':
            case 'DECLINED':
            case 'FAIL':
                $error          = ", Message = " . $this->getRequestParam('message');
                $reason_holders = ['reason', 'Reason', 'paymentMethodErrorReason', 'gwErrorReason'];
                
                foreach($reason_holders as $key) {
                    if(!empty($this->getRequestParam($key))) {
                        $error .= ', Reason: ' . $this->getRequestParam($key);
                        break;
                    }
                }
                
                $msg = $gw_data . $error;
                
				// Sale or Auth
				if(in_array($transactionType, array('Sale', 'Auth'))) {
                    $orderState = 'cancel';
                    
                    if ('DECLINED' == $status) {
                        $transactionState   = 'cancel';
                    }
                    if (in_array($status, ['FAIL', 'ERROR'])) {
                        $transactionState   = 'fail';
                    }
                    
				}
				
                break;

            case 'PENDING':
//                $msg        = $default_msg_start . ' ' . $gw_data;
//				$status_id  = ''; // set it empty to avoid adding duplicate status in the history
                break;
                
            default:
                $this->nuvei->createLog($status, 'Unexisting status:');
        }
        
        $this->nuveiOrderData['nuveiTransactions'][(int) $this->getRequestParam('TransactionID')]['note'] = $msg;
        
        if (empty($orderState) || empty($transactionState)) {
            $msg = 'Can not recognize new Order States.';
            
            $this->nuvei->createLog($msg);
            return [
                'message' => $msg
            ];
        }
        
        # set Order state
        $this->stateMachineRegistry->transition(new Transition(
            'order',
            $this->order->getId(),
            $orderState,
            'stateId'
        ), $this->context);
        
        // add Nuvei Transaction data to the custom fields
        $json   = json_encode($this->nuveiOrderData);
        $conn   = Kernel::getConnection();
        $sql    = "UPDATE `order` "
                . "SET `custom_fields` = '" . $json . "' "
                . "WHERE HEX(id) = '" . $this->order->getId() . "'";
        
        $conn->executeStatement($sql);
        # /set Order state
        
        # set Transaction state
        $this->stateMachineRegistry->transition(new Transition(
            'order_transaction',
            $this->transaction->getId(),
            $transactionState,
            'stateId'
        ), $this->context);
        // save order message
        
		$this->nuvei->createLog(
			[
                'order state'       => $orderState,
                'transaction state' => $transactionState
            ],
			'Final states'
		);
        
        return [
            'message' => 'Changes of the Order are saved.'
        ];
    }
    
    /**
     * Get a request parameter by its name.
     * 
     * @param string $name
     * @param mixed $default
     * 
     * @return mixed
     */
    private function getRequestParam($name, $default = '')
    {
        if (isset($_REQUEST[$name])) {
            return $_REQUEST[$name];
        }
        
        return $default;
    }
    
}
