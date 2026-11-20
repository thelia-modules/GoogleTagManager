<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace GoogleTagManager\Tests\Unit\Hook\Theme;

use GoogleTagManager\Consent\ConsentServiceDeclarer;
use GoogleTagManager\Hook\Theme\GoogleTagManagerThemeHook;
use GoogleTagManager\Service\DataLayerProviderInterface;
use GoogleTagManager\Service\GtmConfig;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/**
 * The container tag goes through the consent manager's gate when one is wired, and
 * renders as before when none is.
 */
final class GoogleTagManagerThemeHookTest extends TestCase
{
    public function testWithoutAConsentManagerTheContainerLoadsAsBefore(): void
    {
        $hook = new GoogleTagManagerThemeHook($this->twig(), $this->config(), $this->dataLayer());

        self::assertStringContainsString("<script>\n", $hook->render('layout.head.bottom', []));
        self::assertStringContainsString('<noscript>', $hook->render('layout.body.top', []));
    }

    public function testUnderAConsentManagerTheContainerAndItsFallbackGoThroughTheGate(): void
    {
        $gate = new class {
            /** @var list<string> */
            public array $services = [];

            public function gate(string $html, string $serviceCode): string
            {
                $this->services[] = $serviceCode;

                return '[gated]';
            }
        };
        $hook = new GoogleTagManagerThemeHook($this->twig(), $this->config(), $this->dataLayer(), $gate);

        self::assertSame('<push/>[gated]', $hook->render('layout.head.bottom', []), 'the dataLayer pushes stay, only the container waits');
        self::assertSame('[gated]', $hook->render('layout.body.top', []));
        self::assertSame([ConsentServiceDeclarer::SERVICE_CODE, ConsentServiceDeclarer::SERVICE_CODE], $gate->services);
    }

    private function twig(): Environment
    {
        $loader = new FilesystemLoader();
        $loader->addPath(\dirname(__DIR__, 4).'/templates', 'GoogleTagManagerModule');

        return new Environment($loader);
    }

    private function config(): GtmConfig
    {
        $config = $this->createStub(GtmConfig::class);
        $config->method('getContainerId')->willReturn('GTM-TEST');

        return $config;
    }

    private function dataLayer(): DataLayerProviderInterface
    {
        $dataLayer = $this->createStub(DataLayerProviderInterface::class);
        $dataLayer->method('renderHead')->willReturn('<push/>');

        return $dataLayer;
    }
}
