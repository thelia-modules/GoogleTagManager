<?php


namespace GoogleTagManager\Hook\Theme;

use GoogleTagManager\Service\GtmConfig;
use Thelia\Core\Hook\Theme\ThemeHookInterface;
use Twig\Environment;

final readonly class GoogleTagManagerThemeHook implements ThemeHookInterface
{
    public function __construct(
        private Environment $twig,
        private GTMConfig $config
    ) {
    }

    public function supports(string $hookName): bool
    {
        return \in_array($hookName, ['layout.head.bottom', 'layout.body.top'], true);
    }

    public function render(string $hookName, array $parameters): string
    {
        $containerId = $this->config->getContainerId();

        if(empty($containerId)) {
            return $containerId;
        }

        return match ($hookName) {
            'layout.head.bottom' => $this->twig->render('@GoogleTagManagerModule/theme-hook/script.html.twig',[
                'containerId' => $containerId
            ]),
            'layout.body.top' => $this->twig->render('@GoogleTagManagerModule/theme-hook/noscript.html.twig',[
                'containerId' => $containerId
            ]),
        };
    }
}
