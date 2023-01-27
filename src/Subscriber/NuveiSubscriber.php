<?php declare(strict_types=1);

namespace Swag\NuveiCheckout\Subscriber;

use Shopware\Storefront\Page\Checkout\Cart\CheckoutCartPageLoadedEvent;
use Shopware\Storefront\Page\Checkout\Finish\CheckoutFinishPageLoadedEvent;
use Shopware\Storefront\Page\Checkout\Finish\CheckoutFinishPageLoadedHook;
use Shopware\Storefront\Pagelet\Footer\FooterPageletLoadedEvent;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoadedEvent;
use Swag\NuveiCheckout\Service\Nuvei;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Shopware\Core\Checkout\Order\Event\OrderPaymentMethodChangedEvent;
use Shopware\Core\Checkout\Customer\CustomerEvents;
use Shopware\Core\Checkout\Order\OrderEvents;

use Shopware\Core\Checkout\Document\Event\InvoiceOrdersEvent;
use Shopware\Core\Checkout\Document\Event\StornoOrdersEvent;
use Shopware\Core\Checkout\Document\Event\DocumentOrderEvent;

/**
 * @author Nuvei
 */
class NuveiSubscriber implements EventSubscriberInterface
{
    private $nuvei;
    
    public function __construct(Nuvei $nuvei)
    {
        $this->nuvei = $nuvei;
    }
    
    public static function getSubscribedEvents(): array
    {
        return [
//            CheckoutFinishPageLoadedHook::class     => 'addNuveiCheckoutData',
//            CheckoutFinishPageLoadedEvent::class    => 'addNuveiCheckoutData2',
//            FooterPageletLoadedEvent::class         => 'addNuveiCheckoutData3',
//            CheckoutCartPageLoadedEvent::class      => 'addNuveiCheckoutData4',
//            CheckoutConfirmPageLoadedEvent::class   => 'addNuveiCheckoutData5',
//            OrderPaymentMethodChangedEvent::class   => 'addNuveiCheckoutData6',
//            CustomerEvents::CUSTOMER_CHANGED_PAYMENT_METHOD_EVENT => 'addNuveiCheckoutData7',
//            OrderEvents::ORDER_PAYMENT_METHOD_CHANGED => 'addNuveiCheckoutData8',
//            InvoiceOrdersEvent::class   => 'orderInvoice',
//            StornoOrdersEvent::class   => 'orderStorno',
//            DocumentOrderEvent::class   => 'docOrderEvent'
        ];
    }
    
    public function docOrderEvent(DocumentOrderEvent $event)
    {
        $this->nuvei->createLog('DocumentOrderEvent');
    }
    
    public function orderStorno(StornoOrdersEvent $event)
    {
        $this->nuvei->createLog('StornoOrdersEvent');
    }
    
    public function orderInvoice(InvoiceOrdersEvent $event)
    {
        $this->nuvei->createLog('InvoiceOrdersEvent');
    }

    public function addNuveiCheckoutData(CheckoutFinishPageLoadedHook $event): void
    {
        $this->nuvei->createLog('CheckoutFinishPageLoadedHook -> addNuveiCheckoutData');
        
//        $event->getPage()->addExtensions('some_data', 'Nuvei CHeckout data');
    }
    
    public function addNuveiCheckoutData2(CheckoutFinishPageLoadedEvent $event): void
    {
        $this->nuvei->createLog('CheckoutFinishPageLoadedEvent -> addNuveiCheckoutData2');
        
//        $event->getPage()->addExtensions('some_data', 'Nuvei CHeckout data');
    }
    
    public function addNuveiCheckoutData3(FooterPageletLoadedEvent $event): void
    {
        $this->nuvei->createLog('FooterPageletLoadedEvent -> addNuveiCheckoutData3');
        
//        $event->getPagelet()->addExtensions('some_data', 'Nuvei CHeckout data');
    }
    
    public function addNuveiCheckoutData4(CheckoutCartPageLoadedEvent $event): void
    {
        $this->nuvei->createLog('CheckoutCartPageLoadedEvent -> addNuveiCheckoutData4');
        
//        $event->getPagelet()->addExtensions('some_data', 'Nuvei CHeckout data');
    }
    
    public function addNuveiCheckoutData5(CheckoutConfirmPageLoadedEvent $event): void
    {
        $page       = $event->getPage();
        $context    = $event->getContext(); // SalesChannelContext
        
        $this->nuvei->createLog('CheckoutConfirmPageLoadedEvent');
//        $this->nuvei->createLog($page->getCart());
        
        $page->addArrayExtension('some_name', []);
    }
    
    public function addNuveiCheckoutData6(OrderPaymentMethodChangedEvent $event): void
    {
        $this->nuvei->createLog('OrderPaymentMethodChangedEvent -> addNuveiCheckoutData6');
        
//        $event->getPagelet()->addExtensions('some_data', 'Nuvei CHeckout data');
    }
    
    public function addNuveiCheckoutData7(EntityLoadedEvent $event)
    {
        $this->nuvei->createLog('customer changed payment method');
        
//        $event->getPagelet()->addExtensions('some_data', 'Nuvei CHeckout data');
    }
    
    public function addNuveiCheckoutData8(EntityLoadedEvent $event)
    {
        $this->nuvei->createLog('order payment method changed');
        
//        $event->getPagelet()->addExtensions('some_data', 'Nuvei CHeckout data');
    }
    
}
