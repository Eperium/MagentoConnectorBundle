<?php

namespace spec\Pim\Bundle\MagentoConnectorBundle\Guesser;

use Pim\Bundle\MagentoConnectorBundle\Webservice\MagentoSoapClient;
use Pim\Bundle\MagentoConnectorBundle\Webservice\MagentoSoapClientFactory;
use Pim\Bundle\MagentoConnectorBundle\Webservice\MagentoSoapClientParameters;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class WebserviceGuesserSpec extends ObjectBehavior
{
    function let(MagentoSoapClientFactory $magentoSoapClientFactory, MagentoSoapClientParameters $clientParameters)
    {
        $this->beConstructedWith($magentoSoapClientFactory);

        $clientParameters->getSoapUrl()->willReturn('soap_url');
        $clientParameters->getSoapUsername()->willReturn('soap_username');
        $clientParameters->getSoapApiKey()->willReturn('soap_api_key');
    }

    function it_shoulds_guess_webservice_for_parameters($magentoSoapClientFactory, $clientParameters, MagentoSoapClient $magentoSoapClient)
    {
        $magentoSoapClientFactory->getMagentoSoapClient($clientParameters)->willReturn($magentoSoapClient);

        $magentoSoapClient->call('core_magento.info')->willReturn(array('magento_version' => '1.8'));

        $this->getWebservice($clientParameters)->shouldBeAnInstanceOf('Pim\Bundle\MagentoConnectorBundle\Webservice\Webservice');
    }

    function it_shoulds_guess_an_old_webservice_for_parameters($magentoSoapClientFactory, $clientParameters, MagentoSoapClient $magentoSoapClient)
    {
        $magentoSoapClientFactory->getMagentoSoapClient($clientParameters)->willReturn($magentoSoapClient);

        $magentoSoapClient->call('core_magento.info')->willReturn(array('magento_version' => '1.6'));

        $this->getWebservice($clientParameters)->shouldBeAnInstanceOf('Pim\Bundle\MagentoConnectorBundle\Webservice\Webservice16');
    }

    function it_raises_an_exception_if_the_version_number_is_not_well_formed($magentoSoapClientFactory, $clientParameters, MagentoSoapClient $magentoSoapClient)
    {
        $magentoSoapClientFactory->getMagentoSoapClient($clientParameters)->willReturn($magentoSoapClient);

        $magentoSoapClient->call('core_magento.info')->willReturn(array('magento_version' => 'v1.6'));

        $this->shouldThrow('Pim\Bundle\MagentoConnectorBundle\Guesser\NotSupportedVersionException')->during('getWebservice', array($clientParameters));
    }
}
