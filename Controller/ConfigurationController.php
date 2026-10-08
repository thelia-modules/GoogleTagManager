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

namespace GoogleTagManager\Controller;

use Exception;
use GoogleTagManager\Form\ConfigurationForm;
use GoogleTagManager\GoogleTagManager;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Translation\Translator;

/**
 * Class Configuration
 * @package GoogleTagManager\Controller
 * @author Tom Pradat <tpradat@openstudio.fr>
 */
#[Route('/admin/module/googletagmanager', name: 'admin_googletagmanager_configuration_')]
class ConfigurationController extends BaseAdminController
{
    #[Route('/save', name: 'save', methods: ['POST'])]
    public function saveAction(): RedirectResponse|Response|null
    {
        if (null !== $response = $this->checkAuth(array(AdminResources::MODULE), array('googletagmanager'), AccessManager::UPDATE)) {
            return $response;
        }

        $form = $this->createForm(ConfigurationForm::getName()); // 'googletagmanager_configuration_form'

        try {
            $vform = $this->validateForm($form);
            $data = $vform->getData();

            GoogleTagManager::setConfigValue(GoogleTagManager::GOOGLE_TAG_MANAGER_GMT_ID_CONFIG_KEY, trim((string) $data['gtmId']));
        } catch (Exception $e) {
            $this->setupFormErrorContext(
                Translator::getInstance()->trans("Syntax error"),
                $e->getMessage(),
                $form,
                $e
            );
        }

        return $this->generateRedirectFromRoute('admin.module.configure', [], ['module_code' => 'GoogleTagManager']);
    }
}
