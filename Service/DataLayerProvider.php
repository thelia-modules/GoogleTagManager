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
use Twig\Environment;

/**
 * Decides which dataLayer events must be pushed for the current page (based on the
 * current `_view`), and produces the corresponding markup using {@see GtmTagRenderer}.
 * This holds the orchestration logic that used to live in the front hook.
 */
final readonly class DataLayerProvider
{
    public function __construct(
        private GoogleTagService         $googleTagService,
        private GtmTagRenderer           $renderer,
        private TaxEngine                $taxEngine,
        private RequestStack             $requestStack,
        private EventDispatcherInterface $eventDispatcher,
        private Environment              $twig,
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

        $view = $request?->attributes->get('_route', $request->query->get('_route', $request->request->get('_route')));

        if ('checkout_delivery' === $view) {
            $cart = $session?->getSessionCart($this->eventDispatcher);
            $country = $this->taxEngine->getDeliveryCountry();

            $html .= $this->renderer->dataLayerPush($this->googleTagService->getCartData($cart?->getId(), $country));
            $html .= $this->renderer->dataLayerPush($this->googleTagService->getCheckOutData($cart?->getId(), $country));
        }

        // Only on the /pay page for now. The placed order id is captured in session by
        // GoogleTagListener::trackPurchase (the request carries no order_id here).
        if ('checkout_pay' === $view
            && null !== $orderId = $session?->get(GoogleTagManager::GOOGLE_TAG_PURCHASE)) {
            $html .= $this->renderer->dataLayerPush($this->googleTagService->getPurchaseData((int) $orderId));
            $html .= $this->renderer->dataLayerPush($this->googleTagService->getPaymentInfo((int) $orderId));
            $html .= $this->renderer->dataLayerPush($this->googleTagService->getShippingInfo((int) $orderId));
            $session->set(GoogleTagManager::GOOGLE_TAG_PURCHASE, null);
        }

        return $html;
    }

    public function renderJsInit(): string
    {
        $request = $this->requestStack->getCurrentRequest();
        $view = $request?->attributes->get('_view', $request->query->get('_view', $request->request->get('_view')));
        $html = '';

        if (\in_array($view, ['category', 'brand', 'search', 'folder', 'content', 'page'], true)) {
            $html .= $this->twig->render('@GoogleTagManagerModule/theme-hook/getItems.html.twig');
        }

        // Always loaded: a listing page may also carry an add-to-cart button.
        return $html.$this->twig->render('@GoogleTagManagerModule/theme-hook/addToCart.html.twig');
    }

    /**
     * Stores the viewed product id in session, consumed by GoogleTagListener::getViewItem
     * to resolve the [google_tag_view_item] ShortCode at response time.
     */
    public function trackProduct(int|string|null $productId): string
    {
        $this->requestStack->getCurrentRequest()?->getSession()?->set(GoogleTagManager::GOOGLE_TAG_VIEW_ITEM, $productId);
        return '';
    }
}
