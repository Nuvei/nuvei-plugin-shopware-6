<?php declare (strict_types=1);

namespace Swag\NuveiCheckout\Storefront\Controller;

use Shopware\Storefront\Controller\StorefrontController;
use Swag\NuveiCheckout\Service\Nuvei;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Shopware\Core\Checkout\Cart\CartPersister;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SystemConfig\SystemConfigService;

#[Route(defaults: ['_routeScope' => ['storefront']])]
/**
 * @author Nuvei
 */
class CheckoutController extends StorefrontController
{
    private $paymentMethodRepo;
    private $currRepository;
    private $customerRepository;
    private $customerAddressRepository;
    private $countryRepository;
    private $langRepository;
    private $localeRepository;
    private $nuvei;
    private $cart;
    private $cartPersister;
    private $sysConfig;
    private $context;
    private $isUserLoggedIn;
    private $request;
    
    public function __construct(
        EntityRepositoryInterface|EntityRepository $pmRepository,
        EntityRepositoryInterface|EntityRepository $currRepository,
        EntityRepositoryInterface|EntityRepository $customerRepository,
        EntityRepositoryInterface|EntityRepository $customerAddressRepository,
        EntityRepositoryInterface|EntityRepository $countryRepository,
        EntityRepositoryInterface|EntityRepository $langRepository,
        EntityRepositoryInterface|EntityRepository $localeRepository,
        Nuvei $nuvei,
        CartPersister $cartPersister,
        SystemConfigService $sysConfig
    ) {
        $this->paymentMethodRepo            = $pmRepository;
        $this->currRepository               = $currRepository;
        $this->customerRepository           = $customerRepository;
        $this->customerAddressRepository    = $customerAddressRepository;
        $this->countryRepository            = $countryRepository;
        $this->langRepository               = $langRepository;
        $this->localeRepository             = $localeRepository;
        $this->nuvei                        = $nuvei;
        $this->cartPersister                = $cartPersister;
        $this->sysConfig                    = $sysConfig;
    }
    
    #[Route(path: '/nuvei_checkout', name: 'frontend.nuveicheckout.checkout', defaults: ["XmlHttpRequest" => true], methods: ['GET'])]
    /**
     * @param Request $request
     * @param Context $context
     * 
     * @return JsonResponse
     * 
     * Legacy route for SW 6.4
     * @Route("/nuvei_checkout", name="frontend.nuveicheckout.checkout", defaults={"XmlHttpRequest"=true}, methods={"GET"})
     */
    public function returnCheckoutData(Request $request, Context $context): JsonResponse
    {
        $this->nuvei->createLog('returnCheckoutData');
        
        $this->context  = $context;
        $this->request  = $request;
        $is_nuvei       = $this->isNuveiOrder($request->query->get('selected_pm'));
        
        // exit
        if (!$is_nuvei) {
            return new JsonResponse([
                'success' => 0,
            ]);
        }
        
        // get the Cart
        /** @var SalesChannelContext $context */
        $sales_channel_context  = $request->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT);
        $this->cart             = $this->cartPersister->load($sales_channel_context->getToken(), $sales_channel_context);
        
        if (!is_null($sales_channel_context->getCustomer())
            && isset($sales_channel_context->getCustomer()->guest)
            && false === $sales_channel_context->getCustomer()->guest
        ) {
            $this->isUserLoggedIn = true;
            
            $this->nuvei->createLog($sales_channel_context->getCustomer()->guest, 'is guest user');
        }
        
        // open the Nuvei Order
        $resp = $this->openOrder();
        
        if (empty($resp['status']) || 'SUCCESS' != $resp['status']) {
            $this->nuvei->createLog('', 'Problem when try to open new Order.', 'CRITICAL');
            
            return new JsonResponse([
                'success'   => 0,
                'msg'       => 'Unexpected problem. Please, choose another payment provider!'
            ]);
        }
        
        // prepare the parameters for the JS part
        # for UPO
        $use_upos = $save_pm = false;
        
        if ((bool) $this->sysConfig->get('SwagNuveiCheckout.config.nuveiUseUpos')
            && $this->isUserLoggedIn
        ) {
            $use_upos = $save_pm = true;
        }
        # /for UPO
        
        # blocked PMs
        $blocked_pms = $this->sysConfig->get('SwagNuveiCheckout.config.nuveiPmBlockList');
			
		if (!empty($blocked_pms)) {
			$blocked_pms = explode(',', $blocked_pms);
		}
        # /blocked PMs
        
        # blocked_cards
		$blocked_cards     = [];
		$blocked_cards_str = $this->sysConfig->get('SwagNuveiCheckout.config.nuveiBlockedCards');
		
        // clean the string from brakets and quotes
        if (!empty($blocked_cards_str)) {
            $blocked_cards_str = str_replace('],[', ';', $blocked_cards_str);
            $blocked_cards_str = str_replace('[', '', $blocked_cards_str);
            $blocked_cards_str = str_replace(']', '', $blocked_cards_str);
            $blocked_cards_str = str_replace('"', '', $blocked_cards_str);
            $blocked_cards_str = str_replace("'", '', $blocked_cards_str);

            if (!empty($blocked_cards_str)) {
                $blockCards_sets = explode(';', $blocked_cards_str);

                if (count($blockCards_sets) == 1) {
                    $blocked_cards = explode(',', current($blockCards_sets));
                } else {
                    foreach ($blockCards_sets as $elements) {
                        $blocked_cards[] = explode(',', $elements);
                    }
                }
            }
        }
		# /blocked_cards
        
        # get language
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('id', $context->getLanguageId()));
        
        $lang_data = $this->langRepository->search($criteria, $context)->first();
        ///
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('id', $lang_data->getLocaleId()));
        
        $locale_data = $this->localeRepository->search($criteria, $context)->first();
        # /get language
        
        $locale                 = substr($locale_data->getCode(), 0, 2);
        $useDCC                 = $this->sysConfig->get('SwagNuveiCheckout.config.nuveiDcc');
        $nuvei_order_details    = $this->request->getSession()->get('nuvei_order_details', []);
        
        if (isset($nuvei_order_details['amount']) && 0 == $nuvei_order_details['amount']) {
            $useDCC = 'false';
        }
        
        $locale         = substr($locale_data->getCode(), 0, 2);
        $nuveiSdkTransl = (string) $this->sysConfig->get('SwagNuveiCheckout.config.nuveiSdkTransl');
        
        $checkout_params = [
            'sessionToken'              => $resp['sessionToken'],
			'env'                       => 'sandbox' == $this->sysConfig
                ->get('SwagNuveiCheckout.config.nuveiMode') ? 'test' : 'prod',
			'merchantId'                => trim($this->sysConfig->get('SwagNuveiCheckout.config.nuveiMerchantId')),
			'merchantSiteId'            => trim($this->sysConfig->get('SwagNuveiCheckout.config.nuveiMerchantSiteId')),
			'country'                   => $nuvei_order_details['billingAddress']['country'] ?? '',
			'currency'                  => $nuvei_order_details['currency'] ?? '',
			'amount'                    => (string) ($nuvei_order_details['amount'] ?? ''),
			'renderTo'                  => '#nuvei_checkout',
			'useDCC'                    => $useDCC,
			'strict'                    => false,
			'savePM'                    => $save_pm,
			'showUserPaymentOptions'    => $use_upos,
//			'pmWhitelist'               => null,
//			'pmBlacklist'               => $blocked_pms,
//            'blockCards'                => $blocked_cards,
			'alwaysCollectCvv'          => true,
			'fullName'                  => trim(
                ($nuvei_order_details['billingAddress']['firstName'] ?? '') . ' '
                . ($nuvei_order_details['billingAddress']['lastName'] ?? '')
            ),
			'email'                     => $nuvei_order_details['billingAddress']['email'] ?? '',
			'payButton'                 => $this->sysConfig->get('SwagNuveiCheckout.config.nuveiPayButton'),
			'showResponseMessage'       => false, // shows/hide the response popups
			'locale'                    => $locale,
			'autoOpenPM'                => (bool) $this->sysConfig->get('SwagNuveiCheckout.config.nuveiAutoExpandPms'),
			'logLevel'                  => $this->sysConfig->get('SwagNuveiCheckout.config.nuveiSdkLogLevel'),
			'maskCvv'                   => true,
			'i18n'                      => json_decode($nuveiSdkTransl, true),
//			'apmWindowType'             => $this->sysConfig->get('SwagNuveiCheckout.config.nuveiApmWindowType'),
			'theme'                     => $this->sysConfig->get('SwagNuveiCheckout.config.nuveiSdkTheme'),
            'apmConfig'                 => [
                'googlePay' => [
                    'locale' => $locale
                ]
            ],
            'sourceApplication' => $this->nuvei->getSourceApplication(),
        ];
        
        if (!empty($blocked_pms)) {
            $checkout_params['pmBlacklist'] = $blocked_pms;
        }
        if (!empty($blocked_cards)) {
            $checkout_params['blockCards'] = $blocked_cards;
        }
        
//        if($is_rebilling) {
//            unset($checkout_params['pmBlacklist']);
//            $checkout_params['pmWhitelist'] = ['cc_card'];
//        }
        
        // only CC allowed
        if (0 == (float) $checkout_params['amount']) {
            $checkout_params['pmBlacklist'] = null;
            $checkout_params['pmWhitelist'] = ['cc_card'];
        }
        
        $this->nuvei->createLog($checkout_params, 'checkout JS params');
        
        return new JsonResponse([
            'success'           => 1,
            'nuveiSdkParams'    => $checkout_params,
            'texts'             => [
                'cardNeedToRefresh' => $this->trans('The Card data need to be refreshed.'),
            ]
        ]);
    }
    
    #[Route(path: '/nuvei_prepayment', name: 'frontend.nuveicheckout.prepayment', defaults: ["XmlHttpRequest" => true], methods: ['GET'])]
    /**
     * @param Request $request
     * @param Context $context
     * 
     * @return JsonResponse
     * 
     * Legacy route for SW 6.4
     * @Route("/nuvei_prepayment", name="frontend.nuveicheckout.prepayment", defaults={"XmlHttpRequest"=true}, methods={"GET"})
     */
    public function prePaymentCheck(Request $request, Context $context)
    {
        $this->nuvei->createLog('prePaymentCheck');
        
        $this->context  = $context;
        $this->request  = $request;
        $is_nuvei       = $this->isNuveiOrder($request->query->get('selected_pm'));
        
        $this->nuvei->createLog($this->request->getSession()->all(), 'getSession all');
        
        // exit
        if (!$is_nuvei) {
            return new JsonResponse([
                'success' => 0,
            ]);
        }
        
        // get the Cart
        /** @var SalesChannelContext $context */
        $sales_channel_context  = $request->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT);
        $this->cart             = $this->cartPersister->load($sales_channel_context->getToken(), $sales_channel_context);
        
        $nuvei_order_details    = $this->request->getSession()->get('nuvei_order_details', []);
        $session_data   = $nuvei_order_details['itemsDataHash'] ?? [];
        $current_data   = $this->getItemsBaseData();
        
        // success
        if ($session_data == md5(serialize($current_data))) {
            return new JsonResponse([
                'success' => 1,
            ]);
        }
        
        // error
        $this->nuvei->createLog(
            [
                '$session_data' => $session_data,
                'itemsDataHash' => md5(serialize($current_data)),
            ],
            'prePaymentCheck error'
        );
        
        return new JsonResponse([
            'success' => 0,
        ]);
    }
    
    /**
     * @return array
     */
    private function openOrder()
    {
        $this->nuvei->createLog('openOrder()');
        
        # get cart amount
        $amount = $this->cart->getPrice()->getTotalPrice();
        
        // exit
        if (!is_numeric($amount) || $amount < 0) {
            $msg = 'Missing the Cart total.';
			
            $this->nuvei->createLog($amount, $msg);
            
            return ['status' => 'ERROR'];
        }
        # /get cart amount
        
        # get cart currency
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('id', $this->context->getCurrencyId()));
        
        $curr_data  = $this->currRepository->search($criteria, $this->context)->first();
        
        if (empty($curr_data->isoCode)) {
            $msg = 'Missing the Cart currency ISO code.';
            $this->nuvei->createLog($curr_data, $msg);
            
            return ['status' => 'ERROR'];
        }
        
        $currency = $curr_data->isoCode;
        # /get cart currency
        
        // get base items data
        $items_data = $this->getItemsBaseData();
        
        if (empty($items_data)) {
            return ['status' => 'ERROR'];
        }
        
        # Try to update the order
        $addresses          = $this->getAddresses();
        $try_update_order   = true;
        $open_order_details = $this->request->getSession()->get('nuvei_order_details', []);
        $transactionType    = (float) $amount == 0 
            ? 'Auth' : $this->sysConfig->get('SwagNuveiCheckout.config.nuveiPaymentAction');
        
        // check for few mandatory fields
        if (!isset($open_order_details['transactionType'])
            || !isset($open_order_details['userTokenId'])
            || !isset($addresses['billingAddress']['email'])
        ) {
            $try_update_order = false;
        }
        elseif ($open_order_details['transactionType'] != $transactionType
            || $open_order_details['userTokenId'] != $addresses['billingAddress']['email']
        ) {
            $this->nuvei->createLog(
                [
                    'oo transactionType'    => $open_order_details['transactionType'],
                    '$transactionType'      => $transactionType,
                    'oo userTokenId'        => $open_order_details['userTokenId'],
                    'billing addr email'    => @$addresses['billingAddress']['email'],
                ],
                '$try_update_order = false',
                'DEBUG'
            );
            
            $try_update_order = false;
        }
        
        if ($try_update_order) {
            $up_resp        = $this->updateOrder($amount, $currency, $items_data);
            $resp_status    = $this->nuvei->getRequestStatus($up_resp);

            if (!empty($resp_status) && 'SUCCESS' == $resp_status) {
                return $up_resp;
            }
        }
        # /Try to update the order
        
        $oo_params = [
            'transactionType'   => $transactionType,
            'amount'            => $amount,
            'currency'          => $currency,
            'clientUniqueId'    => time() . '_' . uniqid(),
            'shippingAddress'   => $addresses['shippingAddress'],
            'billingAddress'    => $addresses['billingAddress'],
            'userDetails'       => $addresses['billingAddress'],
            'userTokenId'       => $addresses['billingAddress']['email'], // the UPO decision is in the SDK
            'merchantDetails'   => [
                'customField2'      => $this->cart->getToken(),
                'customField4'      => $amount,
                'customField5'      => $currency,
            ],
        ];
        
        $resp = $this->nuvei->callRestApi(
            'openOrder',
            $oo_params,
            array('merchantId', 'merchantSiteId', 'clientRequestId', 'amount', 'currency', 'timeStamp')
        );
        
        // save part of the response into the session
        if(!empty($resp['sessionToken'])
            && !empty($resp['status'])
            && 'SUCCESS' == $resp['status']
        ) {
            $sessionParams = [
                'sessionToken'      => $resp['sessionToken'],
                'orderId'           => $resp['orderId'],
                'clientRequestId'   => $resp['clientRequestId'],
                'transactionType'   => $oo_params['transactionType'], 
                'userTokenId'       => $oo_params['userTokenId'],
                'itemsDataHash'     => md5(serialize($items_data)),
                // the next parameters we will use for the JS response
                'billingAddress'    => $oo_params['billingAddress'],
                'currency'          => $oo_params['currency'],
                'amount'            => $oo_params['amount'],
            ];
            
            $this->request->getSession()->set('nuvei_order_details', $sessionParams);
        }
        
        return $resp;
    }
    
    /**
     * @param string $amount
     * @param string $currency
     * @param array $items_data
     * 
     * @return array
     */
    private function updateOrder($amount, $currency, $items_data)
    {
        $last_open_order_details = $this->request->getSession()->get('nuvei_order_details', []);
        
		$this->nuvei->createLog(
			$last_open_order_details,
			'updateOrder - session nuvei_order_details'
		);
		
		if (empty($last_open_order_details)
			|| empty($last_open_order_details['sessionToken'])
			|| empty($last_open_order_details['orderId'])
			|| empty($last_open_order_details['clientRequestId'])
		) {
            $this->nuvei->createLog('updateOrder - missing mandatory session data, continue with new openOrder.');
			
            return array('status' => 'ERROR');
		}
        
        $addresses = $this->getAddresses();
        
        // add other parameters
        $up_params = [
            'sessionToken'      => $last_open_order_details['sessionToken'],
            'orderId'           => $last_open_order_details['orderId'],
            'clientRequestId'   => $last_open_order_details['clientRequestId'],
            'amount'            => $amount,
            'currency'          => $currency,
            'shippingAddress'   => $addresses['shippingAddress'],
            'billingAddress'    => $addresses['billingAddress'],
            'userDetails'       => $addresses['billingAddress'],
            'items'				=> array(
				array(
					'name'          => 'ShopwaWare_Order',
					'price'         => $amount,
					'quantity'      => 1
				)
			),
            'merchantDetails'   => [
                'customField2'      => $this->cart->getToken(),
                'customField4'      => $amount,
                'customField5'      => $currency,
            ],
        ];
        
        // TODO - someday check for rebilling items
        
		$resp = $this->nuvei->callRestApi(
            'updateOrder',
            $up_params,
            array('merchantId', 'merchantSiteId', 'clientRequestId', 'amount', 'currency', 'timeStamp')
        );
        
        // update session data in case there are any changes
        if(!empty($resp['sessionToken'])
            && !empty($resp['status'])
            && 'SUCCESS' == $resp['status']
        ) {
            $last_open_order_details['sessionToken']    = $resp['sessionToken'];
            $last_open_order_details['orderId']         = $resp['orderId'];
            $last_open_order_details['clientRequestId'] = $resp['clientRequestId'];
            $last_open_order_details['itemsDataHash']   = md5(serialize($items_data));
            // the next parameters we will use for the JS response
            $last_open_order_details['billingAddress']  = $addresses['billingAddress'];
            $last_open_order_details['amount']          = $amount;
            $last_open_order_details['currency']        = $currency;
            
            $this->request->getSession()->set('nuvei_order_details', $last_open_order_details);
        }
        
        return $resp;
    }
    
    /**
     * @return array
     */
    private function getAddresses()
    {
        // get delivery address
        $addresses_obj = $this->cart->getDeliveries()->getAddresses()->getElements();
        
        if (!is_array($addresses_obj)) {
            $this->nuvei->createLog($addresses_obj);
            return [];
        }
        
        $first_address = current($addresses_obj);
        
        # get customer billing address
        // get the Customer data
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('id', $first_address->getCustomerId()))
            ->addAssociation('activeBillingAddress');
        
        $customer_data = $this->customerRepository->search($criteria, $this->context)->first();
        
        // get the Default Billing Address of the Customer
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('id', $customer_data->getDefaultBillingAddressId()));
        
        $address_data = $this->customerAddressRepository->search($criteria, $this->context)->first();
        
        // get the Country
        $criteria = (new Criteria())->addFilter(new EqualsFilter('id', $address_data->getCountryId()));
        
        $country_data = $this->countryRepository->search($criteria, $this->context)->first();
        # /get customer billing address
        
        return [
            'shippingAddress'   => [
                "firstName"	=> $first_address->getFirstName(),
                "lastName"	=> $first_address->getLastName(),
                "address"   => $first_address->getStreet(),
                "phone"     => $first_address->getPhoneNumber(),
                "zip"       => $first_address->getZipcode(),
                "city"      => $first_address->getCity(),
                'country'	=> $first_address->getCountry()->getIso(),
                'email'		=> $customer_data->getEmail(),
            ],
            'billingAddress'   => [
                "firstName"	=> $address_data->getFirstname(),
                "lastName"	=> $address_data->getLastname(),
                "address"   => $address_data->getStreet(),
                "phone"     => $address_data->getPhoneNumber(),
                "zip"       => $address_data->getZipcode(),
                "city"      => $address_data->getCity(),
                'country'	=> $country_data->getIso(),
                'email'		=> $customer_data->getEmail(),
            ],
        ];
    }
    
    /**
     * Check if Nuvei GW is selected.
     * 
     * @param string $selected_pm
     * @return boolean
     */
    private function isNuveiOrder($selected_pm): bool
    {
        // exit
        if (empty($selected_pm)) {
            $this->nuvei->createLog('CheckoutController error - selected payment method parameter is empty.');
            
            return false;
        }
        
        # Check if selected payment method is Nuvei
        $criteria = new Criteria();
        $criteria->addFilter(new MultiFilter(
            MultiFilter::CONNECTION_AND,
            [
                new EqualsFilter('active', 1),
                new EqualsFilter('id', $selected_pm),
                new ContainsFilter('handlerIdentifier', 'NuveiCheckout'),
            ]
        ));
        
        $pm = $this->paymentMethodRepo->search($criteria, $this->context)->first();
        
        // exit, the selected PM is not Nuvei or is not active
        if (empty($pm)) {
            $this->nuvei->createLog('CheckoutController error - this is not Nuvei order.');
            
            return false;
        }
        # /Check if selected payment method is Nuvei
        
        return true;
    }
    
    private function getItemsBaseData(): array
    {
        $items      = $this->cart->getLineItems();
        $items_data = [];
        
        // exit
        if (empty($items)) {
            $msg = 'There are no Items in the Cart.';
            $this->nuvei->createLog($items, $msg);
            
            return $items_data;
        }
        
        foreach ($items as $item) {
            $items_data[$item->getId()] = [
                'referencedId'  => $item->getReferencedId(),
                'label'         => $item->getLabel(),
                'quantity'      => $item->getQuantity(),
                'type'          => $item->getType(),
                'totalPrice'    => $item->getPrice()->getTotalPrice(),
                'children'      => $item->getChildren(),
            ];
        }
        
        $this->nuvei->createLog($items_data, '$items_data');
        
        return $items_data;
    }
    
}