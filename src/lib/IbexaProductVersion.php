<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Platform\PostInstall;

use Composer\InstalledVersions;

class IbexaProductVersion
{
    /** @const array<int, string> Order of this array is very important */
    public const IBEXA_PRODUCTS = [
        'ibexa/commerce',
        'ibexa/experience',
        'ibexa/content',
        'ibexa/oss',
    ];

    public static function getInstalledProduct(): string
    {
        $packages = InstalledVersions::getInstalledPackages();
        $ibexaPackages = array_filter($packages, static function (string $packageName): bool {
            return strpos($packageName, 'ibexa/') !== false;
        });

        // removes unrelated Ibexa packages
        $installedIbexaProducts = array_values(array_intersect($ibexaPackages, self::IBEXA_PRODUCTS));

        // sorts $installedIbexaProducts according to the order of self::IBEXA_PRODUCTS
        $installedIbexaProducts = array_keys(
            array_filter(
                array_replace(
                    array_fill_keys(self::IBEXA_PRODUCTS, false),
                    array_fill_keys($installedIbexaProducts, true)
                )
            )
        );

        // first element in the array is the package matching product edition
        return reset($installedIbexaProducts);
    }

    public static function getInstalledProductVersion(): string
    {
        $installedProduct = self::getInstalledProduct();

        return InstalledVersions::getVersion($installedProduct);
    }
}
