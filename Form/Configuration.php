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

namespace GoogleTagManager\Form;


use GoogleTagManager\GoogleTagManager;
use Thelia\Core\Translation\Translator;
use Thelia\Form\BaseForm;

/**
 * Class Configuration
 * @package GoogleTagManager\Form
 * @author Tom Pradat <tpradat@openstudio.fr>
 */
class Configuration extends BaseForm
{
    protected function buildForm()
    {
        $form = $this->formBuilder;

        $lang = $this->getRequest()->getSession()->get('thelia.admin.edition.lang');
        $value = GoogleTagManager::getConfigValue('googletagmanager_gtmId', null, $lang->getLocale());
        $form->add(
            "gtmId",
            "text",
            array(
                'data'  => $value,
                'label' => Translator::getInstance()->trans("Google Tag Manager Id",[] ,GoogleTagManager::DOMAIN_NAME),
                'label_attr' => array(
                    'for' => "gtmId"
                ),
            )
        );
    }

    public function getName(){
        return 'googletagmanager';
    }
}