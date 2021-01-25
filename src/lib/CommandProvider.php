<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Platform\PostInstall;

use Ibexa\Platform\PostInstall\Command\IbexaSetupCommand;
use Ibexa\Platform\PostInstall\Command\IbexaSolrCommand;

class CommandProvider implements \Composer\Plugin\Capability\CommandProvider
{
    public function getCommands()
    {
        return [
            new IbexaSetupCommand(),
            new IbexaSolrCommand(),
        ];
    }
}
