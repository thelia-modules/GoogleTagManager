<?php

declare(strict_types=1);

namespace GoogleTagManager\Hook\Theme;

use GoogleTagManager\Service\DataLayerProvider;
use GoogleTagManager\Service\GtmConfig;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\Hook\Theme\ThemeHookInterface;
use Twig\Environment;

final readonly class GoogleTagManagerThemeHook implements ThemeHookInterface
{
    public function __construct(
        private Environment $twig,
        private GTMConfig $config,
        private DataLayerProvider $dataLayerProvider,
    ) {
    }

    public function supports(string $hookName): bool
    {
        return \in_array($hookName,
            [
                'layout.head.bottom',
                'layout.body.top',
                'layout.body.bottom',
                'product.bottom'
            ], true);
    }

    public function render(string $hookName, array $parameters): string
    {
        $containerId = $this->config->getContainerId();

        if( empty($containerId) ) {
            return '';
        }

        $productId = $parameters['product']['id'] ?? null;
        return match ($hookName) {
            'layout.head.bottom' => $this->twig->render('@GoogleTagManagerModule/theme-hook/script.html.twig', [
                'containerId' => $containerId
            ]),
            'layout.body.top' => $this->twig->render('@GoogleTagManagerModule/theme-hook/noscript.html.twig', [
                'containerId' => $containerId
            ]),
            'layout.body.bottom' => $this->dataLayerProvider->renderJsInit(),
            'product.bottom' => $productId ? $this->dataLayerProvider->trackProduct($productId) : '',
            default => '',
        };
    }
}
