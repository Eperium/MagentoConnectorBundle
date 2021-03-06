<?php

namespace Pim\Bundle\MagentoConnectorBundle\Writer;

use Pim\Bundle\MagentoConnectorBundle\Guesser\WebserviceGuesser;
use Pim\Bundle\MagentoConnectorBundle\Manager\CategoryMappingManager;
use Akeneo\Bundle\BatchBundle\Item\InvalidItemException;
use Pim\Bundle\MagentoConnectorBundle\Webservice\SoapCallException;

/**
 * Magento category writer
 *
 * @author    Julien Sanchez <julien@akeneo.com>
 * @copyright 2013 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class CategoryWriter extends AbstractWriter
{
    const CATEGORY_CREATED          = 'Categories created';
    const CATEGORY_UPDATED          = 'Categories updated';
    const CATEGORY_MOVED            = 'Categories moved';
    const CATEGORY_TRANSLATION_SENT = 'Categories translations sent';

    /**
     * @var CategoryMappingManager
     */
    protected $categoryMappingManager;

    /**
     * Constructor
     *
     * @param WebserviceGuesser      $webserviceGuesser
     * @param CategoryMappingManager $categoryMappingManager
     */
    public function __construct(
        WebserviceGuesser $webserviceGuesser,
        CategoryMappingManager $categoryMappingManager
    ) {
        parent::__construct($webserviceGuesser);

        $this->categoryMappingManager = $categoryMappingManager;
    }

    /**
     * {@inheritdoc}
     */
    public function write(array $batches)
    {
        $this->beforeExecute();

        //creation for each product in the admin storeView (with default locale)
        foreach ($batches as $batch) {
            try {
                $this->handleNewCategory($batch);
                $this->handleUpdateCategory($batch);
                $this->handleMoveCategory($batch);
                $this->handleVariationCategory($batch);
            } catch (SoapCallException $e) {
                throw new InvalidItemException($e->getMessage(), array());
            }
        }
    }

    /**
     * Handle category creation
     * @param array $batch
     */
    protected function handleNewCategory(array $batch)
    {
        if (isset($batch['create'])) {
            foreach ($batch['create'] as $newCategory) {
                $pimCategory       = $newCategory['pimCategory'];
                $magentoCategoryId = $this->webservice->sendNewCategory($newCategory['magentoCategory']);
                $magentoUrl        = $this->soapUrl;

                $this->categoryMappingManager->registerCategoryMapping(
                    $pimCategory,
                    $magentoCategoryId,
                    $magentoUrl
                );

                $this->stepExecution->incrementSummaryInfo(self::CATEGORY_CREATED);
            }
        }
    }

    /**
     * Handle category update
     * @param array $batch
     */
    protected function handleUpdateCategory(array $batch)
    {
        if (isset($batch['update'])) {
            foreach ($batch['update'] as $updateCategory) {
                $this->webservice->sendUpdateCategory($updateCategory);

                $this->stepExecution->incrementSummaryInfo(self::CATEGORY_UPDATED);
            }
        }
    }

    /**
     * Handle category move
     * @param array $batch
     */
    protected function handleMoveCategory(array $batch)
    {
        if (isset($batch['move'])) {
            foreach ($batch['move'] as $moveCategory) {
                $this->webservice->sendMoveCategory($moveCategory);

                $this->stepExecution->incrementSummaryInfo(self::CATEGORY_MOVED);
            }
        }
    }

    /**
     * Handle category variation update
     * @param array $batch
     */
    protected function handleVariationCategory(array $batch)
    {
        if (isset($batch['variation'])) {
            foreach ($batch['variation'] as $variationCategory) {
                $pimCategory        = $variationCategory['pimCategory'];
                $magentoCategoryId  = $this->categoryMappingManager->getIdFromCategory($pimCategory, $this->soapUrl);
                $magentoCategory    = $variationCategory['magentoCategory'];
                $magentoCategory[0] = $magentoCategoryId;

                $this->webservice->sendUpdateCategory($magentoCategory);

                $this->stepExecution->incrementSummaryInfo(self::CATEGORY_TRANSLATION_SENT);
            }
        }
    }
}
