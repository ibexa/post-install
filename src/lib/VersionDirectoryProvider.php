<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Platform\PostInstall;

use Composer\InstalledVersions;
use Composer\Semver\Semver;
use Composer\Semver\VersionParser;
use Exception;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class VersionDirectoryProvider
{
    /** @var \Composer\Semver\VersionParser */
    private $versionParser;

    public function get(string $product, string $path): string
    {
        $finder = new Finder();
        $finder
            ->in($path)
            ->ignoreDotFiles(false)
            ->directories()
            ->depth(0);

        $versionDirs = array_values(
            array_map(
                static function (SplFileInfo $dir): string {
                    return $dir->getRelativePathname();
                },
                iterator_to_array($finder)
            )
        );

        $productPackage = InstalledVersions::getRawData()['versions'][$product];
        $aliases = $productPackage['aliases'];
        $productVersion = $productPackage['version'];

        $normalizedAliases = array_map(function (string $alias): string {
            $normalizedAlias = $this->getVersionParser()->parseNumericAliasPrefix($alias);

            return trim($normalizedAlias, '.');
        }, $aliases);

        foreach (Semver::rsort($versionDirs) as $versionDir) {
            // directory name:
            //      matches version (i.e. dev-master)
            //      OR is one of the normalized aliases (3.3.x-dev => 3.3)
            //      OR is one of the aliases (3.3.x-dev)
            //      OR matches semver constraint (3.3, 3.3.1)
            if (
                $versionDir === $productVersion
                || in_array($versionDir, $normalizedAliases, true)
                || in_array($versionDir, $aliases, true)
                || (
                    false === strpos($versionDir, 'dev-')
                    && Semver::satisfies($productVersion, '~' . $versionDir)
                )
            ) {
                return $versionDir;
            }
        }

        throw new Exception('Can\'t find directory matching your product version');
    }

    private function getVersionParser(): VersionParser
    {
        if (null === $this->versionParser) {
            $this->versionParser = new VersionParser();
        }

        return $this->versionParser;
    }
}
