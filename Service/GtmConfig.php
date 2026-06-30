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

/**
 * Read access to the module configuration (the GTM container id).
 */
class GtmConfig
{
    public function getContainerId(): string
    {
        return (string) GoogleTagManager::getConfigValue(GoogleTagManager::GOOGLE_TAG_MANAGER_GMT_ID_CONFIG_KEY);
    }

    public function isEnabled(): bool
    {
        return '' !== $this->getContainerId();
    }
}
