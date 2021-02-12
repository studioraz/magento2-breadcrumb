/**
 * Copyright © 2020 Studio Raz. All rights reserved.
 * See LICENSE.txt for license details.
 */

define([
    'jquery',
    'Magento_Theme/js/model/breadcrumb-list'
], function ($, breadcrumbList) {
    'use strict';

    return function (widget) {
        $.widget('mage.breadcrumbs', widget, {
            /**
             * Append category and product crumbs.
             *
             * @private
             */
            _appendCatalogCrumbs: function () {
                var categoryCrumbs = this._resolveCategoryCrumbs();

                categoryCrumbs.forEach(function (crumbInfo) {
                    breadcrumbList.push(crumbInfo);
                });

                if (categoryCrumbs.length === 0 && this.options.fullCategoryPath) {
                    this.options.fullCategoryPath.forEach(function (crumbInfo) {
                        breadcrumbList.push(crumbInfo);
                    });
                }

                if (this.options.product) {
                    breadcrumbList.push(this._getProductCrumb());
                }
            }
        });

        return $.mage.breadcrumbs;
    };
});
