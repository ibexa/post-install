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
        $this->setName('ibexa:setup')
             ->setDescription('Runs post install configuration tool.')
             ->addOption('platformsh', null, InputOption::VALUE_NONE, 'Install Platform.sh config files')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($input->getOption('platformsh')) {
            $this->getIO()->write('Installing Platform.sh config files...', true, IOInterface::NORMAL);

            $fileSystem = new Filesystem();

            $product = IbexaProductVersion::getInstalledProduct();
            $version = IbexaProductVersion::getInstalledProductVersion();

            $commonFiles = $this->getCommonFiles($version);
            $productSpecificFiles = $this->getProductSpecificFiles($product, $version);

            // helper array for detecting common file overrides
            $commonFilePathNames = array_fill_keys(
                array_map(static function (SplFileInfo $file): string {
                    return $file->getRelativePathname();
                }, iterator_to_array($commonFiles)),
                true
            );

            $this->getIO()->write('Copying common files', true, IOInterface::NORMAL);

            $progressBar = $this->getIO()->getProgressBar($commonFiles->count());
            foreach ($commonFiles as $file) {
                if ($fileSystem->exists($file->getRelativePathname())) {
                    $this->getIO()->write(printf('File \'%s\' exists and has been overwritten', $file->getRelativePathname()), true, IOInterface::VERBOSE);
                    $this->printNewLine();
                }

                $fileSystem->copy($file->getPathname(), $file->getRelativePathname(), true);
                $progressBar->advance();
                $this->printNewLine();
            }

            $this->printNewLine();
            $this->getIO()->write('Copying product specific files', true, IOInterface::NORMAL);

            $progressBar = $this->getIO()->getProgressBar($productSpecificFiles->count());
            foreach ($productSpecificFiles as $file) {
                if (
                    !array_key_exists($file->getRelativePathname(), $commonFilePathNames)
                    && $fileSystem->exists($file->getRelativePathname())
                ) {
                    $this->getIO()->write(printf('File \'%s\' exists and has been overwritten', $file->getRelativePathname()), true, IOInterface::VERBOSE);
                    $this->printNewLine();
                }

                $fileSystem->copy($file->getPathname(), $file->getRelativePathname(), true);
                $progressBar->advance();
                $this->printNewLine();
            }

            $progressBar->finish();
            $this->printNewLine();

            $this->getIO()->write('Platform.sh config files installed successfully', true, IOInterface::NORMAL);
        }

        return 1;
    }

    protected function getCommonFiles(string $version): Finder
    {
        $finder = new Finder();
        $finder
            ->in(self::PSH_RESOURCES_PATH.'/common/'.$version)
            ->ignoreDotFiles(false)
            ->followLinks()
            ->files();

        return $finder;
    }

    protected function getProductSpecificFiles(string $product, string $version): Finder
    {
        $finder = new Finder();
        $finder
            ->in(self::PSH_RESOURCES_PATH.'/'.str_replace('/', '-', $product).'/'.$version.'/')
            ->ignoreDotFiles(false)
            ->followLinks()
            ->files();

        return $finder;
    }

    protected function printNewLine(): void
    {
        $this->getIO()->write('', true, IOInterface::NORMAL);
    }
}
