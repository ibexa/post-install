<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\PostInstall;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;

class PostInstall implements PluginInterface
{
    public function activate(
        Composer $composer,
        IOInterface $io
    ): void {
        $io->write('[Ibexa PostInstall tool] Activate', true, IOInterface::DEBUG);
    }

    public function deactivate(
        Composer $composer,
        IOInterface $io
    ): void {
        $io->write('[Ibexa PostInstall tool] Deactivate', true, IOInterface::DEBUG);
    }

    public function uninstall(
        Composer $composer,
        IOInterface $io
    ): void {
        $io->write('[Ibexa PostInstall tool] Uninstall', true, IOInterface::DEBUG);
    }
}
