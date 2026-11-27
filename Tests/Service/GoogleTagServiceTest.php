<?php

declare(strict_types=1);

namespace GoogleTagManager\Tests\Service;

use GoogleTagManager\Events\GoogleTagItemEvent;
use GoogleTagManager\Service\GoogleTagService;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Thelia\Domain\Taxation\TaxEngine\TaxEngine;
use Thelia\Model\Cart;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Country;
use Thelia\Model\Order;
use Thelia\Model\OrderProduct;
use Thelia\Model\OrderProductTax;
use Thelia\Model\Product;
use Thelia\Model\TaxRuleCountry;
use Thelia\Test\IntegrationTestCase;

/**
 * The dataLayer of the cart and of the order, on a country taxed at 20 %: a line of 2 units
 * at 10.00 is 20.00 before tax and 24.00 with it. GA4 reads the value of an event as the sum
 * of price times quantity of its items, before tax and before shipping.
 */
final class GoogleTagServiceTest extends IntegrationTestCase
{
    private EventDispatcher $dispatcher;
    private GoogleTagService $service;
    private Country $country;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        ConfigQuery::write('store_name', 'Test shop');

        $factory = $this->createFixtureFactory();
        $connection = $this->getPropelConnection();

        $this->country = (new Country())
            ->setIsocode('997')
            ->setIsoalpha2('XX')
            ->setIsoalpha3('XXX')
            ->setVisible(1)
            ->setShopCountry(false);
        $this->country->save($connection);

        $taxRule = $factory->taxRule(['isDefault' => false]);
        $tax = $factory->tax(['requirements' => ['percent' => '20.000000'], 'title' => 'VAT XX']);
        (new TaxRuleCountry())
            ->setTaxRuleId($taxRule->getId())
            ->setCountryId($this->country->getId())
            ->setTaxId($tax->getId())
            ->setPosition(1)
            ->save($connection);

        $category = $factory->category();
        $category->setLocale('en_US')->setTitle('Drinks')->save($connection);

        $this->product = $factory->product(
            $category,
            $taxRule,
            $factory->currency(),
            ['title' => 'Cider', 'basePrice' => 10.0, 'baseQuantity' => 100],
        );

        $requestStack = $this->getService('request_stack');
        $this->dispatcher = new EventDispatcher();
        $this->service = new GoogleTagService(
            $requestStack,
            new TaxEngine($requestStack, $this->dispatcher),
            $this->dispatcher,
        );
    }

    public function testViewCartValueIsBeforeTax(): void
    {
        $cart = $this->cartOfTwoUnits();

        $data = json_decode($this->service->getCartData($cart->getId(), $this->country), true, flags: \JSON_THROW_ON_ERROR);

        self::assertSame('view_cart', $data['event']);
        self::assertEqualsWithDelta(20.0, $data['ecommerce']['value'], 0.001);
        self::assertEqualsWithDelta(10.0, $data['ecommerce']['items'][0]['price'], 0.001);
    }

    public function testBeginCheckoutValueIsBeforeTax(): void
    {
        $cart = $this->cartOfTwoUnits();

        $data = json_decode($this->service->getCheckOutData($cart->getId(), $this->country), true, flags: \JSON_THROW_ON_ERROR);

        self::assertSame('begin_checkout', $data['event']);
        self::assertEqualsWithDelta(20.0, $data['ecommerce']['value'], 0.001);
    }

    public function testPaymentInfoValueIsTheTaxedTotalMinusTheTax(): void
    {
        $order = $this->orderOfTwoUnits();

        $data = json_decode((string) $this->service->getPaymentInfo($order->getId()), true, flags: \JSON_THROW_ON_ERROR);

        self::assertSame('add_payment_info', $data['event']);
        self::assertEqualsWithDelta(20.0, $data['ecommerce']['value'], 0.001);
    }

    public function testShippingInfoValueIsTheTaxedTotalMinusTheTax(): void
    {
        $order = $this->orderOfTwoUnits();

        $data = json_decode((string) $this->service->getShippingInfo($order->getId()), true, flags: \JSON_THROW_ON_ERROR);

        self::assertSame('add_shipping_info', $data['event']);
        self::assertEqualsWithDelta(20.0, $data['ecommerce']['value'], 0.001);
    }

    public function testPurchaseValueIsTheSumOfItsItemsAndKeepsTaxAndShipping(): void
    {
        $order = $this->orderOfTwoUnits();

        $data = json_decode((string) $this->service->getPurchaseData($order->getId()), true, flags: \JSON_THROW_ON_ERROR);

        $ecommerce = $data['ecommerce'];
        self::assertEqualsWithDelta(20.0, $ecommerce['value'], 0.001);
        self::assertEqualsWithDelta(4.0, $ecommerce['tax'], 0.001);
        self::assertEqualsWithDelta(5.0, $ecommerce['shipping'], 0.001);

        $sum = array_sum(array_map(static fn (array $item): float => $item['price'] * $item['quantity'], $ecommerce['items']));
        self::assertEqualsWithDelta($ecommerce['value'], $sum, 0.001);
    }

    public function testPurchaseItemIdIsAString(): void
    {
        $order = $this->orderOfTwoUnits();

        $data = json_decode((string) $this->service->getPurchaseData($order->getId()), true, flags: \JSON_THROW_ON_ERROR);

        self::assertSame((string) $this->product->getId(), $data['ecommerce']['items'][0]['item_id']);
    }

    public function testPurchaseItemIdOfADeletedProductIsAString(): void
    {
        $order = $this->orderOfTwoUnits(productRef: '4242');

        $data = json_decode((string) $this->service->getPurchaseData($order->getId()), true, flags: \JSON_THROW_ON_ERROR);

        self::assertSame('4242', $data['ecommerce']['items'][0]['item_id']);
    }

    public function testAListenerCanChangeTheNameOfAProductItem(): void
    {
        $this->dispatcher->addListener(GoogleTagItemEvent::class, function (GoogleTagItemEvent $event): void {
            self::assertSame($this->product->getId(), $event->getProduct()?->getId());
            self::assertNull($event->getOrderProduct());
            self::assertSame('en_US', $event->getLocale());

            $event->setItem([...$event->getItem(), 'item_name' => $event->getItem()['item_name'].' - subtitle']);
        });

        $cart = $this->cartOfTwoUnits();
        $data = json_decode($this->service->getCartData($cart->getId(), $this->country), true, flags: \JSON_THROW_ON_ERROR);

        self::assertSame('Cider - subtitle', $data['ecommerce']['items'][0]['item_name']);
    }

    public function testAListenerCanChangeTheNameOfAnOrderProductItem(): void
    {
        $this->dispatcher->addListener(GoogleTagItemEvent::class, function (GoogleTagItemEvent $event): void {
            self::assertSame($this->product->getId(), $event->getProduct()?->getId());
            self::assertInstanceOf(OrderProduct::class, $event->getOrderProduct());
            self::assertSame('en_US', $event->getLocale());

            $event->setItem([...$event->getItem(), 'item_name' => $event->getItem()['item_name'].' - subtitle']);
        });

        $order = $this->orderOfTwoUnits(title: 'Cider');
        $data = json_decode((string) $this->service->getPurchaseData($order->getId()), true, flags: \JSON_THROW_ON_ERROR);

        self::assertSame('Cider - subtitle', $data['ecommerce']['items'][0]['item_name']);
    }

    private function cartOfTwoUnits(): Cart
    {
        $factory = $this->createFixtureFactory();
        $cart = $factory->cart();
        $factory->cartItem($cart, $this->product, overrides: ['quantity' => 2.0, 'price' => '10.000000', 'promoPrice' => '10.000000']);

        return $cart;
    }

    private function orderOfTwoUnits(?string $productRef = null, string $title = 'Cider'): Order
    {
        $factory = $this->createFixtureFactory();
        $connection = $this->getPropelConnection();

        $order = $factory->order(overrides: ['postage' => '5.000000']);
        // The invoice address decides the country the items are reported in.
        $order->getOrderAddressRelatedByInvoiceOrderAddressId()->setCountryId($this->country->getId())->save($connection);

        $line = $factory->orderProduct($order, [
            'productRef' => $productRef ?? $this->product->getRef(),
            'title' => $title,
            'quantity' => 2.0,
            'price' => '10.000000',
            'promoPrice' => '10.000000',
        ]);
        (new OrderProductTax())
            ->setOrderProductId($line->getId())
            ->setTitle('VAT XX')
            ->setAmount('2.000000')
            ->setPromoAmount('2.000000')
            ->save($connection);

        return $order;
    }
}
