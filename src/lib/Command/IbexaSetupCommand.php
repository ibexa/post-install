<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\PostInstall\Command;

use Composer\Command\BaseCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @deprecated 5.0.7 This command is deprecated and will be removed in 6.0.0 release. Rely on ibexa/cloud instead.
 */
#[AsCommand(name: 'ibexa:setup', description: 'Runs post install configuration tool.')]
class IbexaSetupCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this->addOption(
            'platformsh',
            null,
            InputOption::VALUE_NONE,
            'Install Platform.sh config files (no longer supported, use ibexa/cloud instead)'
        );
    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ): int {
        trigger_deprecation(
            'ibexa/post-install',
            '5.0.7',
            'This command is deprecated and will be removed in 6.0.0 release. Rely on ibexa/cloud instead.'
        );

        if ($input->getOption('platformsh')) {
            $this->getIO()->writeError([
                '<error>Platform.sh config files are no longer shipped with ibexa/post-install.</error>',
                'Install the ibexa/cloud package and run "php bin/console ibexa:cloud:setup --upsun" instead.',
                'See https://doc.ibexa.co/en/5.0/ibexa_cloud/install_on_ibexa_cloud/#1-prepare-configuration-files',
            ]);

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
