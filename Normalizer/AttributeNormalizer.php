<?php

namespace Pim\Bundle\MagentoConnectorBundle\Normalizer;

use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Pim\Bundle\FlexibleEntityBundle\Model\AbstractAttribute;
use Pim\Bundle\MagentoConnectorBundle\Normalizer\Exception\InvalidAttributeNameException;
use Pim\Bundle\MagentoConnectorBundle\Normalizer\Exception\AttributeTypeChangedException;
use Pim\Bundle\CatalogBundle\Model\ProductValueInterface;
use Pim\Bundle\MagentoConnectorBundle\Mapper\MappingCollection;
use Pim\Bundle\CatalogBundle\Entity\AttributeOption;
use Pim\Bundle\CatalogBundle\Model\ProductValue;
use Pim\Bundle\MagentoConnectorBundle\Manager\ProductValueManager;

/**
 * A normalizer to transform a option entity into an array
 *
 * @author    Julien Sanchez <julien@akeneo.com>
 * @copyright 2014 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class AttributeNormalizer implements NormalizerInterface
{
    const STORE_SCOPE    = 'store';
    const GLOBAL_SCOPE   = 'global';
    const MAGENTO_FORMAT = 'MagentoArray';

    /**
     * @var ProductValueNormalizer
     */
    protected $productValueNormalizer;

    /**
     * @var ProductValueManager
     */
    protected $productValueManager;

    /**
     * @var array
     */
    protected $supportedFormats = array(self::MAGENTO_FORMAT);

    /**
     * Constructor
     * @param ProductValueNormalizer $productValueNormalizer
     * @param ProductValueManager    $productValueManager
     */
    public function __construct(
        ProductValueNormalizer $productValueNormalizer,
        ProductValueManager $productValueManager
    ) {
        $this->productValueNormalizer = $productValueNormalizer;
        $this->productValueManager    = $productValueManager;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsNormalization($data, $format = null)
    {
        return $data instanceof AbstractAttribute && in_array($format, $this->supportedFormats);
    }

    /**
     * {@inheritdoc}
     */
    public function normalize($object, $format = null, array $context = array())
    {
        $normalizedAttribute = array(
            'scope'                         => $this->getNormalizedScope($object),
            'default_value'                 => $this->getNormalizedDefaultValue(
                $object,
                $context['defaultLocale'],
                $context['magentoAttributes'],
                $context['magentoAttributesOptions'],
                $context['attributeMapping']
            ),
            'is_unique'                     => $this->getNormalizedUnique($object),
            'is_required'                   => $this->getNormalizedRequired($object),
            'apply_to'                      => '',
            'is_configurable'               => $this->getNormalizedConfigurable($object),
            'is_searchable'                 => '1',
            'is_visible_in_advanced_search' => '1',
            'is_comparable'                 => '1',
            'is_used_for_promo_rules'       => '1',
            'is_visible_on_front'           => '1',
            'used_in_product_listing'       => '1',
            'additional_fields'             => array(),
            'frontend_label'                => $this->getNormalizedLabels(
                $object,
                $context['magentoStoreViews'],
                $context['defaultLocale'],
                $context['storeViewMapping'],
                $context['attributeMapping']
            )
        );

        $mappedAttributeType = $this->getNormalizedType($object);

        if ($context['create']) {
            $normalizedAttribute = array_merge(
                array(
                    'attribute_code' => $this->getNormalizedCode($object, $context['attributeMapping']),
                    'frontend_input' => $mappedAttributeType,
                ),
                $normalizedAttribute
            );
        } else {
            $magentoAttributeCode = $context['attributeMapping']->getTarget($object->getCode());
            $magentoAttributeType = $context['magentoAttributes'][$magentoAttributeCode]['type'];
            if ($mappedAttributeType !== $magentoAttributeType &&
                !in_array($object->getCode(), $this->getIgnoredAttributesForTypeChangeDetection())) {
                throw new AttributeTypeChangedException(
                    sprintf(
                        'The type for the attribute "%s" has changed (Is "%s" in Magento and is %s in Akeneo PIM. ' .
                        'This operation is not permitted by Magento. Please delete it first on Magento and try to ' .
                        'export again.',
                        $object->getCode(),
                        $context['magentoAttributes'][$magentoAttributeCode]['type'],
                        $mappedAttributeType
                    )
                );
            }

            $normalizedAttribute = array(
                $magentoAttributeCode,
                $normalizedAttribute
            );
        }

        return $normalizedAttribute;
    }

    /**
     * Get normalized type for attribute
     * @param AbstractAttribute $attribute
     *
     * @return string
     */
    protected function getNormalizedType(AbstractAttribute $attribute)
    {
        return isset($this->getTypeMapping()[$attribute->getAttributeType()]) ?
            $this->getTypeMapping()[$attribute->getAttributeType()] :
            'text';
    }

    /**
     * Get attribute type mapping
     * @return array
     */
    protected function getTypeMapping()
    {
        return array(
            'pim_catalog_identifier'       => 'text',
            'pim_catalog_text'             => 'text',
            'pim_catalog_textarea'         => 'textarea',
            'pim_catalog_multiselect'      => 'multiselect',
            'pim_catalog_simpleselect'     => 'select',
            'pim_catalog_price_collection' => 'price',
            'pim_catalog_number'           => 'text',
            'pim_catalog_boolean'          => 'boolean',
            'pim_catalog_date'             => 'date',
            'pim_catalog_file'             => 'text',
            'pim_catalog_image'            => 'media_image',
            'pim_catalog_metric'           => 'text'
        );
    }

    /**
     * Get normalized code for attribute
     * @param AbstractAttribute $attribute
     * @param MappingCollection $attributeMapping
     *
     * @throws InvalidAttributeNameException If attribute name is not valid
     * @return string
     */
    protected function getNormalizedCode(AbstractAttribute $attribute, MappingCollection $attributeMapping)
    {
        if (preg_match('/^[a-z][0-9a-z_]*$/', $attribute->getCode()) === 0) {
            throw new InvalidAttributeNameException(
                sprintf(
                    'The attribute "%s" have a code that is not compatible with Magento. Please use only letters ' .
                    '(a-z), numbers (0-9) or underscore(_). First caracter should also be a letter.',
                    $attribute->getCode()
                )
            );
        }

        return $attributeMapping->getTarget($attribute->getCode());
    }

    /**
     * Get normalized scope for attribute
     * @param AbstractAttribute $attribute
     *
     * @return string
     */
    protected function getNormalizedScope(AbstractAttribute $attribute)
    {
        return $attribute->isLocalizable() ? self::STORE_SCOPE : self::GLOBAL_SCOPE;
    }

    /**
     * Get normalized default value for attribute
     * @param AbstractAttribute $attribute
     * @param string            $defaultLocale
     * @param array             $magentoAttributes
     * @param array             $magentoAttributesOptions
     * @param MappingCollection $attributeMapping
     *
     * @return string
     */
    protected function getNormalizedDefaultValue(
        AbstractAttribute $attribute,
        $defaultLocale,
        array $magentoAttributes,
        array $magentoAttributesOptions,
        MappingCollection $attributeMapping
    ) {
        $context = array(
            'identifier'               => null,
            'scopeCode'                => null,
            'localeCode'               => $defaultLocale,
            'onlyLocalized'            => false,
            'magentoAttributes'        => $magentoAttributes,
            'magentoAttributesOptions' => $magentoAttributesOptions,
            'attributeMapping'         => $attributeMapping,
            'currencyCode'             => ''
        );

        if ($attribute->getDefaultValue() instanceof ProductValueInterface) {
            return reset(
                $this->productValueNormalizer->normalize($attribute->getDefaultValue(), 'MagentoArray', $context)
            );
        } elseif ($attribute->getDefaultValue() instanceof AttributeOption) {
            $productValue = $this->productValueManager->createProductValueForDefaultOption($attribute);

            $normalizedOption = $this->productValueNormalizer->normalize($productValue, 'MagentoArray', $context);

            return reset($normalizedOption);
        } else {
            return (null !== $attribute->getDefaultValue() ? (string) $attribute->getDefaultValue() : '');
        }
    }

    /**
     * Get normalized unquie value for attribute
     * @param AbstractAttribute $attribute
     *
     * @return string
     */
    protected function getNormalizedUnique(AbstractAttribute $attribute)
    {
        return $attribute->isUnique() ? '1' : '0';
    }

    /**
     * Get normalized is required for attribute
     * @param AbstractAttribute $attribute
     *
     * @return string
     */
    protected function getNormalizedRequired(AbstractAttribute $attribute)
    {
        return $attribute->isRequired() ? '1' : '0';
    }

    /**
     * Get normalized configurable for attribute
     * @param AbstractAttribute $attribute
     *
     * @return string
     */
    protected function getNormalizedConfigurable(AbstractAttribute $attribute)
    {
        return ($attribute->getAttributeType() === 'pim_catalog_simpleselect') ? '1' : '0';
    }

    /**
     * Get normalized labels for attribute
     * @param AbstractAttribute $attribute
     * @param array             $magentoStoreViews
     * @param string            $defaultLocale
     * @param MappingCollection $storeViewMapping
     * @param MappingCollection $attributeMapping
     *
     * @return string
     */
    protected function getNormalizedLabels(
        AbstractAttribute $attribute,
        array $magentoStoreViews,
        $defaultLocale,
        MappingCollection $storeViewMapping,
        MappingCollection $attributeMapping
    ) {
        $localizedLabels = array();

        foreach ($magentoStoreViews as $magentoStoreView) {
            $localeCode = $storeViewMapping->getSource($magentoStoreView['code']);

            $localizedLabels[] = array(
                'store_id' => $magentoStoreView['store_id'],
                'label'    => $this->getAttributeTranslation($attribute, $localeCode, $defaultLocale)
            );
        }

        return array_merge(
            array(
                array(
                    'store_id' => 0,
                    'label'    => $attributeMapping->getTarget($attribute->getCode())
                )
            ),
            $localizedLabels
        );
    }

    /**
     * Get attribute translation for given locale code
     * @param AbstractAttribute $attribute
     * @param string            $localeCode
     * @param string            $defaultLocale
     *
     * @return mixed
     */
    protected function getAttributeTranslation(AbstractAttribute $attribute, $localeCode, $defaultLocale)
    {
        foreach ($attribute->getTranslations() as $translation) {
            if (strtolower($translation->getLocale()) == strtolower($localeCode) &&
                $translation->getLabel() !== null) {
                return $translation->getLabel();
            }
        }

        if ($localeCode === $defaultLocale) {
            return $attribute->getCode();
        } else {
            return $this->getAttributeTranslation($attribute, $defaultLocale, $defaultLocale);
        }
    }

    /**
     * Get all ignored attribute for type change detection
     * @return array
     */
    protected function getIgnoredAttributesForTypeChangeDetection()
    {
        return array(
            'tax_class_id',
            'weight'
        );
    }
}
