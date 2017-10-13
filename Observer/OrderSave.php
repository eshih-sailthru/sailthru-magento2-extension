<?php
namespace Sailthru\MageSail\Observer;

use Magento\Catalog\Helper\Product as ProductHelper;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order;
use Sailthru\MageSail\Helper\ClientManager;
use Sailthru\MageSail\Helper\ProductData as SailthruProduct;
use Sailthru\MageSail\Helper\Settings as SailthruSettings;
use Sailthru\MageSail\Cookie\Hid as SailthruCookie;
use Sailthru\MageSail\Logger;

class OrderSave implements ObserverInterface {

    /** @var ProductHelper  */
    private $productHelper;

    /** @var ClientManager  */
    private $sailthruClient;

    /** @var SailthruSettings  */
    private $sailthruSettings;

    /** @var SailthruCookie  */
    private $sailthruCookie;

    /** @var SailthruProduct  */
    private $sailthruProduct;

    /** @var  Logger */
    private $logger;

    public function __construct(
        ProductHelper $productHelper,
        ClientManager $clientManager,
        SailthruSettings $sailthruSettings,
        SailthruCookie $sailthruCookie,
        SailthruProduct $sailthruProduct,
        Logger $logger
    ) {
        $this->productHelper = $productHelper;
        $this->sailthruClient = $clientManager;
        $this->sailthruSettings = $sailthruSettings;
        $this->sailthruCookie = $sailthruCookie;
        $this->sailthruProduct = $sailthruProduct;
        $this->logger = $logger;
    }

    public function execute(Observer $observer) {
        /** @var Order $order */
        $order = $observer->getOrder();
        $storeId = $order->getStoreId();
        $this->sailthruClient = $this->sailthruClient->getClient(true, $storeId);
        $orderData = $this->_getData($order);
        try {
            $this->sailthruClient->apiPost("purchase", $orderData);
        } catch (\Sailthru_Client_Exception $e) {
            $this->logger->err("Error sync'ing purchase #{} - ({}) {}", [$order->getIncrementId(), $e->getCode(), $e->getMessage()]);
        }
    }

    protected function _getData(Order $order)
    {
        return [
            'email'       => $email = $order->getCustomerEmail(),
            'items'       => $this->processItems($order),
            'adjustments' => $adjustments = $this->processAdjustments($order),
            'vars'        => $this->getOrderVars($order, $adjustments),
            'message_id'  => $this->sailthruCookie->getBid(),
            'tenders'     => $this->processTenders($order),
        ];
    }

    /**
     * Prepare data on items in cart or order.
     *
     * @param Order $order
     * @return array
     */
    public function processItems(Order $order)
    {
        /** @var \Magento\Sales\Model\Order\Item[] $items */
        $items = $order->getAllVisibleItems();
        $data = [];
        $configurableSkus = [];
        foreach ($items as $item) {
            $product = $item->getProduct();
            $_item = [];
            $_item['vars'] = [];
            if ($item->getProduct()->getTypeId() == 'configurable') {
                $parentIds[] = $item->getParentItemId();
                $options = $item->getProductOptions();
                $_item['id'] = $options['simple_sku'];
                $_item['title'] = $options['simple_name'];
                $_item['vars'] = $this->getItemVars($options);
                $configurableSkus[] = $options['simple_sku'];
            } elseif (!in_array($item->getSku(), $configurableSkus) && $item->getProductType() != 'bundle') {
                $_item['id'] = $item->getSku();
                $_item['title'] = $item->getName();
            } else {
                $_item['id'] = null;
            }
            if ($_item['id']) {
                $_item['qty'] = $item->getQtyOrdered();
                $_item['url'] = $this->sailthruProduct->getProductUrl($product);
                $_item['image']=$this->sailthruProduct->getBaseImageUrl($product);
                $_item['price'] = $item->getPrice() * 100;
                if ($tags = $this->sailthruProduct->getTags($product)) {
                    $_item['tags'] = $tags;
                }
                $data[] = $_item;
            }
        }
        return $data;
    }
    /**
     * Get order adjustments
     * @param Order $order
     * @return array
     */
    public function processAdjustments(Order $order)
    {
        $adjustments = [];
        if ($shipCost = $order->getShippingAmount()) {
            $adjustments[] = [
                'title' => 'Shipping',
                'price' => $shipCost*100,
            ];
        }
        if ($discount = $order->getDiscountAmount()) {
            $adjustments[] = [
                'title' => 'Discount',
                'price' => (($discount > 0) ? $discount*-1 : $discount)*100,
            ];
        }
        if ($tax = $order->getTaxAmount()) {
            $adjustments[] = [
                'title' => 'Tax',
                'price' => $tax*100,
            ];
        }
        return $adjustments;
    }
    /**
     * Get payment information
     * @param  Order         $order
     * @return string|array
     */
    public function processTenders(Order $order)
    {
        if ($order->getPayment()) {
            $tenders = [
                [
                    'title' => $order->getPayment()->getCcType(),
                    'price' => $order->getPayment()->getBaseAmountOrdered()
                ]
            ];
            if ($tenders[0]['title'] == null) {
                return '';
            }
            return $tenders;
        } else {
            return '';
        }
    }

    /**
     * Get Sailthru item object vars
     * @param array $options
     * @return array
     */
    public function getItemVars($options)
    {
        $vars = [];
        if (array_key_exists('attributes_info', $options)) {
            foreach ($options['attributes_info'] as $attribute) {
                $vars[$attribute['label']] = $attribute['value'];
            }
        }
        return $vars;
    }

    /**
     * Get Sailthru order object vars
     * @param Order $order
     * @param array $adjustments
     *
     * @return array
     */
    public function getOrderVars( Order $order, $adjustments)
    {
        $vars = [];
        foreach ($adjustments as $adj) {
            $vars[$adj['title']] =  $adj['price'];
        }
        $vars['orderId'] = $order->getId();
        return $vars;
    }
}