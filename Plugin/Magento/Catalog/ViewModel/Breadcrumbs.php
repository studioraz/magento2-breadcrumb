<?php
/**
 * Copyright © 2020 Studio Raz. All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace SR\Breadcrumb\Plugin\Magento\Catalog\ViewModel;

use Exception;
use Magento\Catalog\Helper\Data;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Category\Collection;
use Magento\Catalog\ViewModel\Product\Breadcrumbs as NativeBreadcrumbs;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\StoreManagerInterface;

class Breadcrumbs
{
    /**
     * @var Data
     */
    private $catalogData;

    /**
     * @var StoreManagerInterface
     */
    private StoreManagerInterface $storeManager;

    /**
     * Breadcrumbs constructor.
     * @param StoreManagerInterface $storeManagerInterface
     * @param Data $catalogData
     */

    /**
     * Instance of category collection.
     *
     * @var Collection
     */
    protected Collection $categoryCollection;

    public function __construct(
        StoreManagerInterface $storeManagerInterface,
        Data                  $catalogData,
        Collection            $categoryCollection
    )
    {
        $this->storeManager = $storeManagerInterface;
        $this->catalogData = $catalogData;
        $this->categoryCollection = $categoryCollection;
    }

    /**
     * @param NativeBreadcrumbs $subject
     * @param                   $result
     * @return false|string
     */
    public function afterGetJsonConfigurationHtmlEscaped(NativeBreadcrumbs $subject, $result)
    {
        $breadcrumbsConfig = json_decode($result, true);

        if (isset($breadcrumbsConfig['breadcrumbs'])) {
            $breadcrumbsConfig['breadcrumbs']['fullCategoryPath'] = $this->getFullCategoryPath($this->catalogData->getProduct());
        }

        return json_encode($breadcrumbsConfig, JSON_HEX_TAG);
    }

    /**
     * @param Product $product
     * @return array
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws LocalizedException
     */
    public function getFullCategoryPath(Product $product): ?array
    {
        /** @var Collection $collection */
        $collection = $this->getProductCategories($product);

        if ($collection->count() == 0) {
            return [];
        }


        $pool = [];
        $targetCategory = null;

        /** @var Category $category */
        foreach ($collection as $category) {
            $pool[$category->getId()] = $category;

            if (!$category->getIsActive()) {
                continue;
            }

            // all parent categories must be active
            $child = $category;

            try {
                while ($child->getLevel() > 1 && $parent = $child->getParentCategory()) {
                    $pool[$parent->getId()] = $parent;

                    //skip if parent category not active or not in menu
                    if (!$parent->getIsActive()) {
                        $category = null;
                        break;
                    }
                    $child = $parent;
                }
            } catch (Exception $e) {
                // Not found exception is possible (corrupted data in DB)
                $category = null;
            }

            if ($category) {
                $targetCategory = $category;
                break;
            }
        }

        $path = [];

        if ($targetCategory) {
            $pathInStore = $category->getPathInStore();
            $pathIds = array_reverse(explode(',', $pathInStore));

            foreach ($pathIds as $categoryId) {
                if (isset($pool[$categoryId]) && $pool[$categoryId]->getName()) {
                    $category = $pool[$categoryId];
                    $path[] = [
                        'label' => $category->getName(),
                        'link' => $category->getUrl(),
                    ];
                }
            }
        }

        return $path;
    }

    /**
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws LocalizedException
     */
    private function getProductCategories(Product $product)
    {

        $rootCategoryId = $this->getRootCategoryId();

        $collection = $product->getCategoryCollection()
            ->addAttributeToFilter('path', ['like' => "1/{$rootCategoryId}/%"])
            ->addAttributeToSelect('name')
            ->addAttributeToSelect('is_active')
            ->setOrder('level', 'DESC');


        if ($collection->count() < 2) {
            return $collection;
        }

        /**
         * move referee category, if exists, to the beginning of the category ids array to give it higher priority.
         */
        if ($categoryId = $product->getCategoryId()) {
            $currentCategory = $collection->getItemById($categoryId);
            $collection->removeItemByKey($categoryId);
            $categories = $collection->getItems();
            $collection->removeAllItems();
            $collection->addItem($currentCategory);
            foreach ($categories as $category) {
                $collection->addItem($category);
            }
        }


        return $collection;
    }

    /**
     * @return int
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private function getRootCategoryId(): int
    {
        // get store group id for current store
        $storeGroupId = $this->storeManager->getStore()->getStoreGroupId();
        // get root category id
        return (int)$this->storeManager->getGroup($storeGroupId)->getRootCategoryId();
    }
}
