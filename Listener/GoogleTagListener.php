<?php

namespace GoogleTagManager\Listener;

use GoogleTagManager\GoogleTagManager;
use GoogleTagManager\Service\GoogleTagService;
use Propel\Runtime\Exception\PropelException;
use ShortCode\Event\ShortCodeEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\Event\Customer\CustomerCreateOrUpdateEvent;
use Thelia\Core\Event\Customer\CustomerLoginEvent;
use Thelia\Core\Event\Loop\LoopExtendsParseResultsEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\CurrencyQuery;
use Thelia\Model\Lang;
use Thelia\Model\LangQuery;
use Thelia\Model\ProductQuery;

class GoogleTagListener implements EventSubscriberInterface
{
    public function __construct(
        protected GoogleTagService $googleTagService,
        protected RequestStack     $requestStack
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            GoogleTagManager::GOOGLE_TAG_VIEW_LIST_ITEM => ['getViewListItem', 128],
            GoogleTagManager::GOOGLE_TAG_VIEW_ITEM => ['getViewItem', 128],
            TheliaEvents::CUSTOMER_LOGIN => ['triggerLoginEvent', 128],
            TheliaEvents::CUSTOMER_CREATEACCOUNT => ['triggerRegisterEvent', 128],
            TheliaEvents::getLoopExtendsEvent(
                TheliaEvents::LOOP_EXTENDS_PARSE_RESULTS,
                'product'
            ) => ['trackProducts', 128]
        ];
    }

    /**
     * @throws \JsonException|PropelException
     */
    public function getViewListItem(ShortCodeEvent $event): void
    {
        $session = $this->requestStack->getSession();

        $productIds = $session->get(GoogleTagManager::GOOGLE_TAG_VIEW_LIST_ITEM, []);

        $items = $this->googleTagService->getProductItems($productIds, true);

        $result = [
            'event' => 'view_item_list',
            'ecommerce' => [
                'items' => $items
            ]
        ];

        $event->setResult(json_encode($result, JSON_THROW_ON_ERROR));

        $session->set(GoogleTagManager::GOOGLE_TAG_VIEW_LIST_ITEM, null);
    }

    /**
     * @throws \JsonException|PropelException
     */
    public function getViewItem(ShortCodeEvent $event): void
    {
        $session = $this->requestStack->getSession();

        if (!$productId = $session->get(GoogleTagManager::GOOGLE_TAG_VIEW_ITEM)) {
            return;
        }

        $product = ProductQuery::create()->filterById($productId)->findOne();

        /** @var Lang $lang */
        $lang = $session->get('thelia.current.lang') ?: LangQuery::create()->filterByByDefault(1)->findOne();

        $currency = $session->getCurrency() ?: CurrencyQuery::create()->findOneByByDefault(1);

        $items = $this->googleTagService->getProductItem($product, $lang, $currency, null, 1);

        $result = [
            'event' => 'view_item',
            'ecommerce' => [
                'items' => [$items],
                'value' => $items['price'],
                'currency' => $currency?->getCode(),
            ]
        ];

        $event->setResult(json_encode($result, JSON_THROW_ON_ERROR));

        $session->set(GoogleTagManager::GOOGLE_TAG_VIEW_ITEM, null);
    }

    public function trackProducts(LoopExtendsParseResultsEvent $event): void
    {
        $products = [];
        $request = $this->requestStack->getCurrentRequest();
        $session = $request?->getSession();

        if (!in_array($request?->get('_view'), ['product', 'category', 'brand', 'search'])) {
            $session->set(GoogleTagManager::GOOGLE_TAG_VIEW_LIST_ITEM, null);
            return;
        }

        foreach ($event->getLoopResult() as $product) {
            $products[] = $product->get('ID');
        }

        $session->set(GoogleTagManager::GOOGLE_TAG_VIEW_LIST_ITEM, $products);
    }

    public function triggerRegisterEvent(CustomerCreateOrUpdateEvent $event): void
    {
        $this->requestStack->getSession()->set(GoogleTagManager::GOOGLE_TAG_TRIGGER_LOGIN, 'account creation');
    }

    public function triggerLoginEvent(CustomerLoginEvent $event): void
    {
        if ($this->requestStack->getSession()->get(GoogleTagManager::GOOGLE_TAG_TRIGGER_LOGIN) !== "account creation") {
            $this->requestStack->getSession()->set(GoogleTagManager::GOOGLE_TAG_TRIGGER_LOGIN, 'account authentication');
        }
    }
}
