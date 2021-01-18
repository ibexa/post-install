<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Platform\PostInstall;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Plugin\Capable;
use Composer\Plugin\PluginInterface;
use Composer\Plugin\Capability\CommandProvider;
use Ibexa\Platform\PostInstall\CommandProvider as SetupToolCommandProvider;

class PostInstall implements PluginInterface, Capable
{
    public function activate(Composer $composer, IOInterface $io): void
    {
        $io->write('[Ibexa PostInstall tool] Activate', true, IOInterface::DEBUG);
    }

    public function deactivate(Composer $composer, IOInterface $io): void
    {
        $io->write('[Ibexa PostInstall tool] Deactivate', true, IOInterface::DEBUG);
    }

    public function uninstall(Composer $composer, IOInterface $io): void
    {
        $io->write('[Ibexa PostInstall tool] Uninstall',  true, IOInterface::DEBUG);
    }

    public function getCapabilities()
    {
        return [
            CommandProvider::class => SetupToolCommandProvider::class,
        ];
    }
}
