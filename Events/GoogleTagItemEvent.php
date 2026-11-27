<?php

namespace GoogleTagManager\Events;

use Thelia\Core\Event\ActionEvent;
use Thelia\Model\OrderProduct;
use Thelia\Model\Product;

/**
 * Dispatched once an item of the dataLayer is built, whatever the event it belongs to.
 * A listener completes or changes the item with setItem(). The product is set for
 * the items of a listing, a product page or a cart; the order product, for the items
 * of an order (and the product too, unless it no longer exists).
 */
class GoogleTagItemEvent extends ActionEvent
{
    protected array $item;
    protected ?Product $product;
    protected ?OrderProduct $orderProduct;
    protected string $locale;

    public function __construct(array $item, ?Product $product, ?OrderProduct $orderProduct, string $locale)
    {
        $this->item = $item;
        $this->product = $product;
        $this->orderProduct = $orderProduct;
        $this->locale = $locale;
    }

    public function getItem(): array
    {
        return $this->item;
    }

    public function setItem(array $item): GoogleTagItemEvent
    {
        $this->item = $item;

        return $this;
    }

    public function getProduct(): ?Product
    {
        return $this->product;
    }

    public function getOrderProduct(): ?OrderProduct
    {
        return $this->orderProduct;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }
}
