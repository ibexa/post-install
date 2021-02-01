<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Platform\PostInstall\Command;

use Composer\Command\BaseCommand;
use Composer\IO\IOInterface;
use Ibexa\Platform\PostInstall\IbexaProductVersion;
use Ibexa\Platform\PostInstall\VersionDirectoryProvider;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class IbexaSetupCommand extends BaseCommand
{
    private const PSH_RESOURCES_PATH = __DIR__ . '/../../../resources/platformsh';

    protected function configure(): void
    {
        $this
            ->setName('ibexa:setup:platform-sh,')
            ->setDescription('Runs post install configuration tool.')
            ->addOption('platformsh', null, InputOption::VALUE_NONE, 'Install Platform.sh config files')
            ->setAliases(['ibexa:setup'])
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($input->getOption('platformsh')) {
            $this->getIO()->write('Installing Platform.sh config files...', true, IOInterface::NORMAL);

            $fileSystem = new Filesystem();

            $product = IbexaProductVersion::getInstalledProduct();
            $version = IbexaProductVersion::getInstalledProductVersion();

            $commonFiles = $this->getCommonFiles($product);
            $productSpecificFiles = $this->getProductSpecificFiles($product, $version);

            // helper array for detecting common file overrides
            $commonFilePathNames = array_fill_keys(
                array_map(static function (SplFileInfo $file): string {
                    return $file->getRelativePathname();
                }, iterator_to_array($commonFiles)),
                true
            );

            $output->writeln('Copying common files');

            $progressBar = new ProgressBar($output);
            $progressBar->start($commonFiles->count());
            $this->printNewLine($output);
            foreach ($commonFiles as $file) {
                if ($fileSystem->exists($file->getRelativePathname())) {
                    $output->writeln(
                        sprintf("File '%s' exists and has been overwritten", $file->getRelativePathname()),
                        OutputInterface::VERBOSITY_VERBOSE
                    );
                }

                $fileSystem->copy($file->getPathname(), $file->getRelativePathname(), true);
                $progressBar->advance();
                $this->printNewLine($output);
            }

            $progressBar->finish();
            $output->writeln("\nCopying product specific files");

            $progressBar->start($productSpecificFiles->count());
            $this->printNewLine($output);
            foreach ($productSpecificFiles as $file) {
                if (
                    !array_key_exists($file->getRelativePathname(), $commonFilePathNames)
                    && $fileSystem->exists($file->getRelativePathname())
                ) {
                    $output->writeln(
                        sprintf("File '%s' exists and has been overwritten", $file->getRelativePathname()),
                        OutputInterface::VERBOSITY_VERBOSE
                    );
                }

                $fileSystem->copy($file->getPathname(), $file->getRelativePathname(), true);
                $progressBar->advance();
                $this->printNewLine($output);
            }

            $progressBar->finish();

            $output->writeln("\nPlatform.sh config files installed successfully");
        }

        return 1;
    }

    protected function getCommonFiles(string $product): Finder
    {
        $versionDir = $this->getVersionDirectory($product, self::PSH_RESOURCES_PATH . '/common');

        $finder = new Finder();
        $finder
            ->in(self::PSH_RESOURCES_PATH . '/common/' . $versionDir)
            ->ignoreDotFiles(false)
            ->followLinks()
            ->files();

        return $finder;
    }

    protected function getProductSpecificFiles(string $product, string $version): Finder
    {
        $productDir = str_replace('/', '-', $product);
        $versionDir = $this->getVersionDirectory($product, self::PSH_RESOURCES_PATH . '/' . $productDir);

        $finder = new Finder();
        $finder
            ->in(self::PSH_RESOURCES_PATH . '/' . $productDir . '/' . $versionDir . '/')
            ->ignoreDotFiles(false)
            ->followLinks()
            ->files();

        return $finder;
    }

    protected function printNewLine(OutputInterface $output): void
    {
        $output->writeln('');
    }

    private function getVersionDirectory(string $product, string $path): string
    {
        $directoryProvider = new VersionDirectoryProvider();

        return $directoryProvider->get($product, $path);
    }
}
