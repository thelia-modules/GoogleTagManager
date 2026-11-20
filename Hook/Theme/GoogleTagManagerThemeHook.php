<?php

declare(strict_types=1);

namespace GoogleTagManager\Hook\Theme;

use GoogleTagManager\Consent\ConsentServiceDeclarer;
use GoogleTagManager\Service\DataLayerProviderInterface;
use GoogleTagManager\Service\GtmConfig;
use Thelia\Core\Hook\Theme\ThemeHookInterface;
use Twig\Environment;

final readonly class GoogleTagManagerThemeHook implements ThemeHookInterface
{
    /**
     * @param object|null $consentGate the ScriptGate of the ConsentManager module, wired by
     *                                 GoogleTagManager::configureServices() only while that module is active
     */
    public function __construct(
        private Environment $twig,
        private GtmConfig $config,
        private DataLayerProviderInterface $dataLayerProvider,
        private ?object $consentGate = null,
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
            // The dataLayer pushes must come before the container script loads gtm.js.
            // The dataLayer pushes only queue data in the page; the container is what
            // loads Google's scripts, so it alone waits for the visitor's consent.
            'layout.head.bottom' => $this->dataLayerProvider->renderHead()
                .$this->gated($this->twig->render('@GoogleTagManagerModule/theme-hook/script.html.twig', [
                    'containerId' => $containerId,
                ])),
            // The noscript fallback loads the container with no way to ask: dropped under a consent manager.
            'layout.body.top' => $this->gated($this->twig->render('@GoogleTagManagerModule/theme-hook/noscript.html.twig', [
                'containerId' => $containerId
            ])),
            'layout.body.bottom' => $this->dataLayerProvider->renderJsInit(),
            'product.bottom' => $productId ? $this->dataLayerProvider->trackProduct($productId) : '',
            default => '',
        };
    }

    private function gated(string $html): string
    {
        if (null === $this->consentGate || !method_exists($this->consentGate, 'gate')) {
            return $html;
        }

        return $this->consentGate->gate($html, ConsentServiceDeclarer::SERVICE_CODE);
    }
}
