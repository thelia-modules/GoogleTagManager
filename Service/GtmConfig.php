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
use Symfony\Contracts\Service\ResetInterface;

/**
 * Read access to the module configuration (the GTM container id).
 *
 * The container id is asked for several times while one page is built — the head
 * container, the noscript frame, and the enabled test in front of each of them — and
 * every ask was a query on module_config. It is read once per request instead: the
 * framework clears the memo between requests and console commands (ResetInterface),
 * so a container id changed in the back office applies from the next request on.
 */
class GtmConfig implements ResetInterface
{
    private ?string $containerId = null;

    public function getContainerId(): string
    {
        return $this->containerId ??= (string) GoogleTagManager::getConfigValue(GoogleTagManager::GOOGLE_TAG_MANAGER_GMT_ID_CONFIG_KEY);
    }

    public function isEnabled(): bool
    {
        return '' !== $this->getContainerId();
    }

    public function reset(): void
    {
        $this->containerId = null;
    }
}
