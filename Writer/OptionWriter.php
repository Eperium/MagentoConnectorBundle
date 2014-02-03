<?php

namespace Pim\Bundle\MagentoConnectorBundle\Writer;

use Pim\Bundle\MagentoConnectorBundle\Validator\Constraints\HasValidCredentials;
use Oro\Bundle\BatchBundle\Item\InvalidItemException;
use Pim\Bundle\MagentoConnectorBundle\Webservice\SoapCallException;

/**
 * Magento option writer
 *
 * @author    Julien Sanchez <julien@akeneo.com>
 * @copyright 2014 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 *
 * @HasValidCredentials()
 */
class OptionWriter extends AbstractWriter
{
    /**
     * {@inheritdoc}
     */
    public function write(array $batches)
    {
        $this->beforeExecute();

        foreach ($batches as $options) {
            foreach ($options as $option) {
                try {
                    $this->webservice->createOption($option);
                } catch (SoapCallException $e) {
                    throw new InvalidItemException($e->getMessage(), array(json_encode($option)));
                }
            }
        }
    }
}