<?php

declare(strict_types=1);

/*************************************************************************************/
/*      This file is part of the GoogleTagManager package.                           */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace GoogleTagManager\Service;

use GoogleTagManager\GoogleTagManager;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Domain\Taxation\TaxEngine\TaxEngine;

/**
 * Decides which dataLayer events must be pushed for the current page (based on the
 * current `_view`), and produces the corresponding markup using {@see GtmTagRenderer}.
 * This holds the orchestration logic that used to live in the front hook.
 */
class DataLayerProvider
{
    public function __construct(
        private readonly GoogleTagService         $googleTagService,
        private readonly GtmTagRenderer           $renderer,
        private readonly TaxEngine                $taxEngine,
        private readonly RequestStack             $requestStack,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function renderHead(): string
    {
        $request = $this->requestStack->getCurrentRequest();
        $session = $request?->getSession();
        $view = $request?->attributes->get('_view', $request->query->get('_view', $request->request->get('_view')));

        $html = $this->renderer->dataLayerPush($this->googleTagService->getTheliaPageViewParameters());

        if (\in_array($view, ['category', 'brand', 'search'], true)) {
            $html .= $this->renderer->shortCodePush(GoogleTagManager::GOOGLE_TAG_VIEW_LIST_ITEM);
        }

        if ('product' === $view) {
            $html .= $this->renderer->shortCodePush(GoogleTagManager::GOOGLE_TAG_VIEW_ITEM);
        }

        if (null !== $authAction = $session?->get(GoogleTagManager::GOOGLE_TAG_TRIGGER_LOGIN)) {
            $html .= $this->renderer->dataLayerPush($this->googleTagService->getLogInData($authAction));
            $session->set(GoogleTagManager::GOOGLE_TAG_TRIGGER_LOGIN, null);
        }

        if ('order-delivery' === $view) {
            $cart = $session?->getSessionCart($this->eventDispatcher);
            $country = $this->taxEngine->getDeliveryCountry();

            $html .= $this->renderer->dataLayerPush($this->googleTagService->getCartData($cart?->getId(), $country));
            $html .= $this->renderer->dataLayerPush($this->googleTagService->getCheckOutData($cart?->getId(), $country));
        }

        if ('order-placed' === $view
            && $orderId = $request?->attributes->get('order_id', $request->query->get('order_id', $request->request->get('order_id')))) {
            $html .= $this->renderer->dataLayerPush($this->googleTagService->getPurchaseData((int) $orderId));
            $html .= $this->renderer->dataLayerPush($this->googleTagService->getPaymentInfo((int) $orderId));
            $html .= $this->renderer->dataLayerPush($this->googleTagService->getShippingInfo((int) $orderId));
        }

        return $html;
    }

    public function renderJsInit(): string
    {
        $request = $this->requestStack->getCurrentRequest();
        $view = $request?->attributes->get('_view', $request->query->get('_view', $request->request->get('_view')));

        $html = '';

        if (\in_array($view, ['category', 'brand', 'search'], true)) {
            $html .= $this->renderer->renderSelectItem();
        }

        return $html.$this->renderer->renderAddToCart();
    }

    /**
     * Stores the viewed product id in session, consumed by GoogleTagListener::getViewItem
     * to resolve the [google_tag_view_item] ShortCode at response time.
     */
    public function trackProduct(int|string|null $productId): void
    {
        $this->requestStack->getCurrentRequest()?->getSession()?->set(GoogleTagManager::GOOGLE_TAG_VIEW_ITEM, $productId);
    }
}
