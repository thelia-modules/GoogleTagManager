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

namespace GoogleTagManager\Consent;

use ConsentManager\Event\CollectConsentServicesEvent;
use ConsentManager\Event\ConsentServiceDeclaration;
use GoogleTagManager\GoogleTagManager;
use GoogleTagManager\Service\GtmConfig;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Tells the ConsentManager module, when it is active, what the container loads, so the
 * banner asks before the tag runs. The event is named by its string: this class must
 * load while ConsentManager is not installed, and is only called when it is.
 */
final readonly class ConsentServiceDeclarer
{
    public const SERVICE_CODE = 'google_tag_manager';

    public function __construct(
        private GtmConfig $config,
    ) {
    }

    #[AsEventListener(event: 'consent_manager.services.collect')]
    public function declare(CollectConsentServicesEvent $event): void
    {
        if (!$this->config->isEnabled()) {
            return;
        }

        $event->add(new ConsentServiceDeclaration(
            code: self::SERVICE_CODE,
            title: 'Google Tag Manager',
            categoryCode: 'analytics',
            provider: 'Google Ireland Limited',
            // The container sets none itself; these are the Google tags it usually fires.
            cookies: ['_ga*', '_gid', '_gat*', '_gcl*'],
            privacyUrl: 'https://policies.google.com/privacy',
            description: 'Loads the measurement and advertising tags configured in the Google Tag Manager container.',
            translationDomain: GoogleTagManager::DOMAIN_NAME,
        ));
    }
}
