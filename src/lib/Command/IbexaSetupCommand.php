<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\PostInstall\Command;

use Composer\Command\BaseCommand;
use Composer\InstalledVersions;
use Composer\Util\ProcessExecutor;
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
    private const string CLOUD_SETUP_COMMAND = 'bin/console ibexa:cloud:setup --upsun';

    protected function configure(): void
    {
        $this->addOption(
            'platformsh',
            null,
            InputOption::VALUE_NONE,
            'Install Upsun config files (delegates to "ibexa:cloud:setup --upsun" from ibexa/cloud)'
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

        if (!$input->getOption('platformsh')) {
            return Command::SUCCESS;
        }

        $io = $this->getIO();

        if (!InstalledVersions::isInstalled('ibexa/cloud')) {
            $io->writeError([
                '<error>Upsun config files are no longer shipped with ibexa/post-install.</error>',
                'Install the ibexa/cloud package and run "php ' . self::CLOUD_SETUP_COMMAND . '" instead.',
                'See https://doc.ibexa.co/en/5.0/ibexa_cloud/install_on_ibexa_cloud/#1-prepare-configuration-files',
            ]);

            return Command::FAILURE;
        }

        $io->write('Delegating to "php ' . self::CLOUD_SETUP_COMMAND . '" from ibexa/cloud...');

        return (new ProcessExecutor($io))->execute(
            ProcessExecutor::escape(PHP_BINARY) . ' ' . self::CLOUD_SETUP_COMMAND
        );
    }
}
