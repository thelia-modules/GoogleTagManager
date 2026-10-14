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
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Validator\Constraints\Regex;
use Thelia\Core\Translation\Translator;
use Thelia\Form\BaseForm;

/**
 * Class Configuration
 * @package GoogleTagManager\Form
 * @author Tom Pradat <tpradat@openstudio.fr>
 */
class ConfigurationForm extends BaseForm
{
    public static function getName(): string
    {
        return 'googletagmanager_configuration_form';
    }

    protected function buildForm(): void
    {
        $form = $this->formBuilder;

        $value = GoogleTagManager::getConfigValue(GoogleTagManager::GOOGLE_TAG_MANAGER_GMT_ID_CONFIG_KEY);
        $form->add(
            "gtmId",
            TextType::class,
            array(
                'data'  => $value,
                // Optional: an empty id switches the tags off on the front.
                'required' => false,
                // The id is printed in a JavaScript string and in a URL: letters, digits and dashes only (GTM-XXXXXXX).
                'constraints' => [
                    new Regex(pattern: '/^[A-Za-z0-9-]*$/'),
                ],
                'label' => Translator::getInstance()->trans("Google Tag Manager Id", [], GoogleTagManager::DOMAIN_NAME),
                'label_attr' => array(
                    'for' => "gtmId"
                ),
            )
        );
    }
}
