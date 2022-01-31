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
     * @var
     */
    private $storeManagerInterface;

    /**
     * Breadcrumbs constructor.
     * @param StoreManagerInterface $storeManagerInterface
     * @param Data $catalogData
     */
    public function __construct(
        StoreManagerInterface $storeManagerInterface,
        Data $catalogData
    ) {
        $this->storeManager = $storeManagerInterface;
        $this->catalogData = $catalogData;
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
     */
    public function getFullCategoryPath(Product $product): ?array
    {
        /** @var Collection $collection */
        $collection = $product->getCategoryCollection();
        $rootCategoryId = $this->getRootCategoryId();
        try {
            $collection
                ->addAttributeToFilter('path', ['like' => "1/{$rootCategoryId}/%"])
                ->addAttributeToSelect('name')
                ->addAttributeToSelect('include_in_menu')
                ->addAttributeToSelect('is_active')
                ->setOrder('level', 'DESC');
        } catch (LocalizedException $e) {
            return null;
        }

        $pool           = [];
        $targetCategory = null;

        /** @var Category $category */
        foreach ($collection as $category) {
            $pool[$category->getId()] = $category;

            if (!$category->getIsActive() && !$category->getIncludeInMenu()) {
                continue;
            }

            // all parent categories must be active
            $child = $category;

            try {
                while ($child->getLevel() > 1 && $parent = $child->getParentCategory()) {
                    $pool[$parent->getId()] = $parent;

                    //skip if parent category not active or not in menu
                    if (!$parent->getIsActive() || !$parent->getIncludeInMenu() && $parent->getId() != $rootCategoryId) {
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
            $pathIds     = array_reverse(explode(',', $pathInStore));

            foreach ($pathIds as $categoryId) {
                if (isset($pool[$categoryId]) && $pool[$categoryId]->getName()) {
                    $category = $pool[$categoryId];
                    $path[]   = [
                        'label' => $category->getName(),
                        'link'  => $category->getUrl(),
                    ];
                }
            }
        }

        return $path;
    }

    /**
     * @return int
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getRootCategoryId()
    {
        // get store group id for current store
        $storeGroupId = $this->storeManager->getStore()->getStoreGroupId();
        // get root category id
        $rootCategoryId = $this->storeManager->getGroup($storeGroupId)->getRootCategoryId();
        return $rootCategoryId;
    }
}
