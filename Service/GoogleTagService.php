<?php

namespace GoogleTagManager\Service;

use GoogleTagManager\Events\GoogleTagPageViewEvent;
use Propel\Runtime\Exception\PropelException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Model\BrandQuery;
use Thelia\Model\CartItem;
use Thelia\Model\CartQuery;
use Thelia\Model\Category;
use Thelia\Model\CategoryQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Country;
use Thelia\Model\Currency;
use Thelia\Model\CurrencyQuery;
use Thelia\Model\Customer;
use Thelia\Model\Lang;
use Thelia\Model\LangQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderProduct;
use Thelia\Model\OrderQuery;
use Thelia\Model\Product;
use Thelia\Model\ProductQuery;
use Thelia\Model\ProductSaleElements;
use Thelia\TaxEngine\Calculator;
use Thelia\TaxEngine\TaxEngine;

class GoogleTagService
{
    public function __construct(
        protected RequestStack $requestStack,
        protected TaxEngine $taxEngine,
        protected EventDispatcherInterface $dispatcher
    ) {
    }

    /**
     * @throws PropelException
     * @throws \JsonException
     */
    public function getTheliaPageViewParameters(): false|string
    {
        /** @var Customer $user */
        $user = $this->requestStack->getSession()->getCustomerUser();
        $isConnected = null !== $user ? 1 : 0;

        $view = $this->requestStack->getCurrentRequest()?->get('_view');
        $pageType = $this->getPageType($view);

        $result = [
            'event' => 'thelia_page_view',
            'user' => [
                'logged' => $isConnected
            ],
            'google_tag_params' => [
                'ecomm_pagetype' => $this->getPageType($view)
            ]
        ];

        if ($isConnected) {
            $result['user']['userId'] = $user->getRef();
            $result['user']['umd'] = hash('md5', $user->getEmail());
            $result['user']['ush'] = hash('sha256', $user->getEmail());
        }

        if (in_array($pageType, ['category', 'product'])) {
            $result['google_tag_params']['ecomm_category'] = $this->getPageName($view);
        }

        if ($pageType === 'product') {
            $result['google_tag_params']['ecomm_prodid'] = $this->getPageProductRef($view);
        }

        if (in_array($pageType, ['cart', 'purchase'])) {
            $result['google_tag_params']['ecomm_totalvalue'] = $this->getOrderTotalAmount($view);
        }

        $event = new GoogleTagPageViewEvent($result, $user, $view);
        $event = $this->dispatcher->dispatch($event);

        return json_encode($event->getResult(), JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
    }

    /**
     * @throws PropelException
     */
    public function getProductItem(
        Product              $product,
        Lang                 $lang,
        Currency             $currency,
        ?ProductSaleElements $pse = null,
                             $quantity = null,
                             $itemList = false,
                             $taxed = false,
        ?Country             $country = null
    ): array {
        $product->setLocale($lang->getLocale());
        $isDefaultPse = false;

        $category = CategoryQuery::create()->findPk($product->getDefaultCategoryId());
        $categories = $this->getCategories($category, $lang->getLocale(), []);

        if (null === $pse) {
            $isDefaultPse = true;
            $pse = $product->getDefaultSaleElements();
        }

        $productPrice = $pse->getPromo() ?
            $pse->getPricesByCurrency($currency)->getPromoPrice() :
            $pse->getPricesByCurrency($currency)->getPrice();

        if ($taxed && null !== $country) {
            $calculator = new Calculator();
            $calculator->loadTaxRule($product->getTaxRule(), $country, $product);
            $productPrice = $calculator->getTaxedPrice($productPrice);
        }

        if (null === $quantity) {
            $quantity = (int)$pse->getQuantity();
        }

        $brand = $product->getBrand();

        $item = [
            'item_id' => (string)$product->getId(),
            'item_name' => htmlspecialchars($product->getTitle()),
            'item_brand' => htmlspecialchars(null !== $brand ? $brand->setLocale($lang->getLocale())->getTitle() : ConfigQuery::read('store_name')),
            'affiliation' => htmlspecialchars(ConfigQuery::read('store_name')),
            'price' => round($productPrice, 2),
            'currency' => $currency->getCode(),
            'quantity' => $quantity
        ];

        if ($itemList) {
            $item['item_list_id'] = $this->requestStack->getCurrentRequest()?->get('_view');
            $item['item_list_name'] = $this->requestStack->getCurrentRequest()?->get('_view');
        }

        foreach ($categories as $index => $categoryTitle) {
            $categoryIndex = 'item_category' . $index + 1;
            if ($index === 0) {
                $categoryIndex = 'item_category';
            }
            $item[$categoryIndex] = htmlspecialchars($categoryTitle);
        }

        if (!$isDefaultPse) {
            $attributes = '';
            foreach ($combinations = $pse->getAttributeCombinations() as $combinationIndex => $attributeCombination) {
                $attribute = $attributeCombination->getAttribute()->setLocale($lang->getLocale());
                $attributeAv = $attributeCombination->getAttributeAv()->setLocale($lang->getLocale());
                $attributes .= htmlspecialchars($attribute->getTitle() . ': ' . $attributeAv->getTitle());

                if ($combinationIndex + 1 !== count($combinations->getData())) {
                    $attributes .= ', ';
                }
            }

            if (!empty($attributes)) {
                $item['item_variant'] = $attributes;
            }
        }

        return $item;
    }

    /**
     * @throws PropelException
     */
    public function getOrderProductItem(
        OrderProduct         $orderProduct,
        Lang                 $lang,
        Currency             $currency,
        $quantity = null,
        $itemList = false,
        $taxed = false,
        ?Country             $country = null
    ): array {
        $product = ProductQuery::create()->findOneByRef($orderProduct->getProductRef());
        $brand = $product?->getBrand();

        $productPrice = $orderProduct->getWasInPromo() ? $orderProduct->getPromoPrice() : $orderProduct->getPrice();

        $category = CategoryQuery::create()->findPk($product?->getDefaultCategoryId());
        $categories = $category ? $this->getCategories($category, $lang->getLocale(), []) : [];

        if ($taxed && null !== $country) {
            $productPrice += (float)$orderProduct->getOrderProductTaxes()->getFirst()?->getAmount();
        }

        $item = [
            'item_id' => $product?->getId() ?? (int)$orderProduct->getProductRef(),
            'item_name' => htmlspecialchars($orderProduct->getTitle()),
            'item_brand' => htmlspecialchars(null !== $brand ? $brand->setLocale($lang->getLocale())->getTitle() : ConfigQuery::read('store_name')),
            'affiliation' => htmlspecialchars(ConfigQuery::read('store_name')),
            'price' => round($productPrice, 2),
            'currency' => $currency->getCode(),
            'quantity' => $quantity
        ];

        if ($itemList) {
            $item['item_list_id'] = $this->requestStack->getCurrentRequest()?->get('_view');
            $item['item_list_name'] = $this->requestStack->getCurrentRequest()?->get('_view');
        }

        foreach ($categories as $index => $categoryTitle) {
            $categoryIndex = 'item_category' . $index + 1;
            if ($index === 0) {
                $categoryIndex = 'item_category';
            }
            $item[$categoryIndex] = htmlspecialchars($categoryTitle);
        }

        $attributes = '';
        foreach ($combinations = $orderProduct->getOrderProductAttributeCombinations() as $combinationIndex => $attributeCombination) {
            $attributes .= htmlspecialchars($attributeCombination->getAttributeTitle() . ': ' . $attributeCombination->getAttributeAvTitle());

            if ($combinationIndex + 1 !== count($combinations->getData())) {
                $attributes .= ', ';
            }
        }

        if (!empty($attributes)) {
            $item['item_variant'] = $attributes;
        }

        return $item;
    }

    /**
     * @throws PropelException
     */
    public function getProductItems(array $productIds = null, $itemList = false): array
    {
        $session = $this->requestStack->getSession();
        $products = ProductQuery::create()->filterById($productIds)->find();

        /** @var Lang $lang */
        $lang = $session->get('thelia.current.lang') ?: LangQuery::create()->filterByByDefault(1)->findOne();

        $currency = $session->getCurrency() ?: CurrencyQuery::create()->findOneByByDefault(1);

        $items = [];

        foreach ($products as $product) {
            $items[] = $this->getProductItem($product, $lang, $currency, null, null, $itemList);
        }

        return $items;
    }

    /**
     * @throws \JsonException
     */
    public function getLogInData($authAction): false|string
    {
        /** @var Customer $customer */
        $customer = $this->requestStack->getSession()->getCustomerUser();
        $isConnected = null !== $customer ? 1 : 0;

        $result = [
            'event' => 'thelia_auth_success',
            'auth_action' => $authAction,
            'user' => [
                'logged' => $isConnected
            ]
        ];

        if ($isConnected) {
            $result['user']['userId'] = $customer->getRef();
        }

        return json_encode($result, JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
    }

    /**
     * @throws PropelException
     * @throws \JsonException
     */
    public function getCartData(?int $cartId, $addressCountry): string
    {
        if (!$cartId || !$cart = CartQuery::create()->findPk($cartId)) {
            return json_encode([], JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
        }

        $items = array_map(function (CartItem $cartItem) use ($addressCountry) {
            return $this->getProductCartItems($cartItem, $addressCountry);
        }, iterator_to_array($cart->getCartItems()));

        return json_encode([
            'event' => 'view_cart',
            'ecommerce' => [
                'currency' => $cart->getCurrency()?->getCode(),
                'value' => $cart->getTaxedAmount($addressCountry),
                'items' => $items
            ]
        ], JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
    }

    /**
     * @throws PropelException
     * @throws \JsonException
     */
    public function getCheckOutData(?int $cartId, $addressCountry): string
    {
        if (!$cartId || !$cart = CartQuery::create()->findPk($cartId)) {
            return json_encode([], JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
        }

        /** @var Session $session */
        $session = $this->requestStack->getSession();

        $coupons = implode(',', $session->getConsumedCoupons());

        $items = array_map(function (CartItem $cartItem) use ($addressCountry) {
            return $this->getProductCartItems($cartItem, $addressCountry);
        }, iterator_to_array($cart->getCartItems()));

        return json_encode([
            'event' => 'begin_checkout',
            'ecommerce' => [
                'currency' => $cart->getCurrency()?->getCode(),
                'value' => $cart->getTaxedAmount($addressCountry),
                'coupon' => $coupons,
                'items' => $items
            ]
        ], JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
    }

    /**
     * @throws PropelException
     * @throws \JsonException
     */
    public function getPaymentInfo(int $orderId): false|string|null
    {
        $order = OrderQuery::create()->findPk($orderId);

        if (null === $order) {
            return null;
        }

        /** @var Session $session */
        $session = $this->requestStack->getSession();

        $currency = $session->getCurrency() ?: CurrencyQuery::create()->findOneByByDefault(1);

        $coupons = implode(',', $session->getConsumedCoupons());

        $paymentType = $order->getPaymentModuleInstance()->getCode();

        return json_encode([
            'event' => 'add_payment_info',
            'ecommerce' => [
                'currency' => $currency?->getCode(),
                'value' => $order->getTotalAmount($tax, false),
                'coupon' => $coupons,
                'payment_type' => $paymentType,
                'items' => $this->getOrderProductItems($order, $order->getOrderAddressRelatedByInvoiceOrderAddressId()->getCountry())
            ]
        ], JSON_THROW_ON_ERROR);
    }

    /**
     * @throws PropelException
     * @throws \JsonException
     */
    public function getShippingInfo(int $orderId): false|string|null
    {
        $order = OrderQuery::create()->findPk($orderId);

        if (null === $order) {
            return null;
        }

        /** @var Session $session */
        $session = $this->requestStack->getSession();

        $currency = $session->getCurrency() ?: CurrencyQuery::create()->findOneByByDefault(1);

        $coupons = implode(',', $session->getConsumedCoupons());

        $shippingType = $order->getDeliveryModuleInstance()->getCode();

        return json_encode([
            'event' => 'add_shipping_info',
            'ecommerce' => [
                'currency' => $currency?->getCode(),
                'value' => $order->getTotalAmount($tax, false),
                'coupon' => $coupons,
                'shipping_tier' => $shippingType,
                'items' => $this->getOrderProductItems($order, $order->getOrderAddressRelatedByInvoiceOrderAddressId()->getCountry())
            ]
        ], JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
    }

    /**
     * @throws PropelException
     * @throws \JsonException
     */
    public function getPurchaseData(int $orderId): false|string|null
    {
        $order = OrderQuery::create()->findPk($orderId);

        if (null === $order) {
            return null;
        }

        /** @var Session $session */
        $session = $this->requestStack->getSession();

        $currency = $session->getCurrency() ?: CurrencyQuery::create()->findOneByByDefault(1);

        $invoiceAddress = $order->getOrderAddressRelatedByInvoiceOrderAddressId();
        $address = $invoiceAddress->getAddress1() .
            (empty($invoiceAddress->getAddress2()) ? '' : ' ' . $invoiceAddress->getAddress2()) .
            (empty($invoiceAddress->getAddress3()) ? '' : ' ' . $invoiceAddress->getAddress3());

        return json_encode([
            'event' => 'purchase',
            'ecommerce' => [
                'transaction_id' => $order->getRef(),
                'value' => $order->getTotalAmount($tax, false),
                'tax' => $tax,
                'shipping' => $order->getPostage(),
                'currency' => $currency?->getCode(),
                'affiliation' => htmlspecialchars(ConfigQuery::read('store_name')),
                'items' => $this->getOrderProductItems($order, $invoiceAddress->getCountry())
            ],
            'user_purchase' => [
                'email' => $order->getCustomer()->getEmail(),
                'address' => [
                    'first_name' => $invoiceAddress->getFirstname(),
                    'last_name' => $invoiceAddress->getLastname(),
                    'address' => $address,
                    'city' => $invoiceAddress->getZipcode(),
                    'country' => $invoiceAddress->getCountry()->getIsoalpha2()
                ]
            ]
        ], JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
    }

    /**
     * @throws PropelException
     */
    public function getOrderProductItems(Order $order, Country $country): array
    {
        $session = $this->requestStack->getSession();
        $products = $order->getOrderProducts();

        /** @var Lang $lang */
        $lang = $session->get('thelia.current.lang') ?: LangQuery::create()->filterByByDefault(1)->findOne();

        $currency = $session->getCurrency() ?: CurrencyQuery::create()->findOneByByDefault(1);

        $items = [];

        foreach ($products as $orderProduct) {
            $items[] = $this->getOrderProductItem($orderProduct, $lang, $currency, $orderProduct->getQuantity(), false, true, $country);
        }

        return $items;
    }

    /**
     * @throws PropelException
     */
    public function getProductCartItems(CartItem $cartItem, Country $country): array
    {
        $session = $this->requestStack->getSession();

        /** @var Lang $lang */
        $lang = $session->get('thelia.current.lang') ?: LangQuery::create()->filterByByDefault(1)->findOne();

        $currency = $session->getCurrency() ?: CurrencyQuery::create()->findOneByByDefault(1);

        $product = $cartItem->getProductSaleElements()->getProduct();

        return $this->getProductItem($product, $lang, $currency, $cartItem->getProductSaleElements(), $cartItem->getQuantity(), false, true, $country);
    }

    protected function getCategories(Category $category, $locale, $categories)
    {
        if ($category->getParent() !== 0) {
            $parent = CategoryQuery::create()->findPk($category->getParent());
            $categories = $this->getCategories($parent, $locale, $categories);
        }

        $categories[] = htmlspecialchars($category->setLocale($locale)->getTitle());

        return $categories;
    }

    protected function getPageType($view): string
    {
        return match ($view) {
            'index' => 'home',
            'product', 'category', 'content' => $view,
            'brand' => 'category',
            'folder' => 'dossier',
            'search' => 'searchresults',
            'cart', 'order-delivery' => 'cart',
            'order-placed' => 'purchase',
            'account', 'account-orders', 'account-update', 'account-address' => 'account',
            default => 'other',
        };
    }

    protected function getPageName($view): ?string
    {
        switch ($view) {
            case 'category':
                $pageEntity = CategoryQuery::create()->findPk($this->requestStack->getCurrentRequest()?->get('category_id'));
                break;
            case 'brand':
                $pageEntity = BrandQuery::create()->findPk($this->requestStack->getCurrentRequest()?->get('brand_id'));
                break;
            case 'product':
                $pageEntity = ProductQuery::create()->findPk($this->requestStack->getCurrentRequest()?->get('product_id'));
                break;
            default:
                return null;
        }

        if (null === $pageEntity) {
            return null;
        }

        $lang = $this->requestStack->getSession()->getLang();
        if (null === $lang) {
            return null;
        }

        return htmlspecialchars($pageEntity->setLocale($lang->getLocale())->getTitle());
    }

    /**
     * @throws PropelException
     */
    protected function getPageProductRef($view)
    {
        switch ($view) {
            case 'product':
                $product = ProductQuery::create()->findPk($this->requestStack->getCurrentRequest()->get('product_id'));
                $productRefs = [$product->getRef()];
                break;

            case 'cart':
            case 'order-delivery':
                $cart = $this->requestStack->getSession()->getSessionCart();
                $productRefs = array_map(static function (CartItem $item) {
                    return $item->getProduct()->getRef();
                }, iterator_to_array($cart?->getCartItems()));
                break;

            case 'order-placed':
                $order = OrderQuery::create()->findPk($this->requestStack->getCurrentRequest()?->get('order_id'));
                $productRefs = array_map(static function (OrderProduct $item) {
                    return $item->getProductRef();
                }, iterator_to_array($order->getOrderProducts()));
                break;

            default:
                return null;
        }

        return $productRefs;
    }

    /**
     * @throws PropelException
     */
    protected function getOrderTotalAmount($view)
    {
        switch ($view) {
            case 'cart':
            case 'order-delivery':
                return $this->requestStack->getSession()->getSessionCart($this->dispatcher)?->getTaxedAmount($this->taxEngine->getDeliveryCountry());
            case 'order-placed':
                $order = OrderQuery::create()->findPk($this->requestStack->getCurrentRequest()?->get('order_id'));
                return $order->getTotalAmount($tax, false) - $tax;
            default:
                return null;
        }
    }
}
