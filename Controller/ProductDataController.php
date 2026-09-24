<?php

namespace GoogleTagManager\Controller;

use GoogleTagManager\Service\GoogleTagService;
use Propel\Runtime\Exception\PropelException;
use JsonException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Thelia\Controller\Front\BaseFrontController;
use Thelia\Core\HttpFoundation\JsonResponse;
use Thelia\Model\Base\CurrencyQuery;
use Thelia\Model\Base\RewritingUrlQuery;
use Thelia\Model\Currency;
use Thelia\Model\Lang;
use Thelia\Model\LangQuery;
use Thelia\Model\ProductQuery;
use Thelia\Model\ProductSaleElementsQuery;

#[Route('/googletagmanager', name: 'googletagmanager_product_data_')]
class ProductDataController extends BaseFrontController
{
    /**
     * @throws JsonException
     * @throws PropelException
     */
    #[Route('/getItem', name: 'get_item', methods: ['POST'])]
    public function getProductDataWithUrl(Request $request, GoogleTagService $googleTagService): JsonResponse
    {
        $requestContent = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $productUrl = parse_url($requestContent['productUrl']);
        $result = [];

        if (!isset($productUrl['path'])) {
            return new JsonResponse(json_encode($result, JSON_THROW_ON_ERROR));
        }

        $rewriteUrl = RewritingUrlQuery::create()
            ->filterByView('product')
            ->filterByUrl(substr($productUrl['path'], 1))
            ->findOne();

        $session = $request->getSession();

        /** @var Lang $lang */
        $lang = $session->get('thelia.current.lang') ?: LangQuery::create()->filterByByDefault(1)->findOne();

        /** @var Currency $currency */
        $currency = $session->get('thelia.current.currency') ?: CurrencyQuery::create()->filterByByDefault(1)->findOne();


        if (null !== $rewriteUrl) {
            $product = ProductQuery::create()->findPk($rewriteUrl->getViewId());
            $result = $googleTagService->getProductItem($product, $lang, $currency);
        }

        return new JsonResponse(json_encode([$result], JSON_THROW_ON_ERROR));
    }

    /**
     * @throws PropelException
     * @throws JsonException
     */
    #[Route('/getCartItem', name: 'get_cart_item', methods: ['POST'])]
    public function getCartItem(Request $request, GoogleTagService $googleTagService): JsonResponse
    {
        $requestContent = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $result = [];

        if (!isset($requestContent['pseId'], $requestContent['quantity'])) {
            return new JsonResponse(json_encode($result, JSON_THROW_ON_ERROR));
        }

        $pseId = $requestContent['pseId'];
        $quantity = $requestContent['quantity'];

        $pse = ProductSaleElementsQuery::create()->findPk($pseId);
        $product = $pse->getProduct();

        $session = $request->getSession();

        /** @var Lang $lang */
        $lang = $session->get('thelia.current.lang') ?: LangQuery::create()->filterByByDefault(1)->findOne();

        /** @var Currency $currency */
        $currency = $session->get('thelia.current.currency') ?: CurrencyQuery::create()->filterByByDefault(1)->findOne();

        $item = $googleTagService->getProductItem($product, $lang, $currency, $pse, $quantity);
        $price = $item['price'];
        $discount = $item['discount'] ?? 0;
        $quantity = $item['quantity'];

        $result = [
            'items' => [$item],
            'value' => round(($price - $discount) * $quantity, 2),
            'currency' => $item['currency']
        ];

        return new JsonResponse(json_encode($result, JSON_THROW_ON_ERROR));
    }
}
