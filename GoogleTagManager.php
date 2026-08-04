<?php
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

namespace GoogleTagManager;

use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Exception\PropelException;
use ShortCode\ShortCode;
use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;
use Thelia\Module\BaseModule;

class GoogleTagManager extends BaseModule
{
    /** @var string */
    public const DOMAIN_NAME = 'googletagmanager';
    public const GOOGLE_TAG_MANAGER_GMT_ID_CONFIG_KEY = 'googletagmanager_gtmId';

    public const GOOGLE_TAG_VIEW_ITEM = 'google_tag_view_item';
    public const GOOGLE_TAG_VIEW_LIST_ITEM = 'google_tag_view_list_item';
    public const GOOGLE_TAG_TRIGGER_LOGIN = 'google_tag_trigger_login';
    public const GOOGLE_TAG_PURCHASE = 'google_tag_purchase';

    /**
     * @throws PropelException
     */
    public function postActivation(ConnectionInterface $con = null): void
    {
        ShortCode::createNewShortCodeIfNotExist(self::GOOGLE_TAG_VIEW_LIST_ITEM, self::GOOGLE_TAG_VIEW_LIST_ITEM);
        ShortCode::createNewShortCodeIfNotExist(self::GOOGLE_TAG_VIEW_ITEM, self::GOOGLE_TAG_VIEW_ITEM);
    }

    /**
     * Defines how services are loaded in your modules.
     */
    public static function configureServices(ServicesConfigurator $services): void
    {
        $services->load(self::getModuleCode().'\\', __DIR__)
            ->exclude([
                __DIR__.'/I18n/*',
                __DIR__.'/Config/**/*.php',
                __DIR__.'/Tests/*',
                __DIR__.'/GoogleTagManager.php',
            ])
            ->autowire(true)
            ->autoconfigure(true);
    }
}
