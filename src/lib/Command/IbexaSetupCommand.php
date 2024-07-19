<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\PostInstall\Command;

use Composer\Command\BaseCommand;
use Composer\InstalledVersions;
use Composer\IO\IOInterface;
use Composer\Semver\Semver;
use Composer\Semver\VersionParser;
use Exception;
use Ibexa\PostInstall\IbexaProductVersion;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class IbexaSetupCommand extends BaseCommand
{
    /** @var \Composer\Semver\VersionParser */
    private $versionParser;

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

        return \Symfony\Component\Console\Command\Command::SUCCESS;
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
        $aliases = $productPackage['aliases'] ?? [];
        $productVersion = $productPackage['version'] ?? '';

        $normalizedAliases = array_map(function (string $alias): string {
            $normalizedAlias = $this->getVersionParser()->parseNumericAliasPrefix($alias);

            if ($normalizedAlias === false) {
                throw new RuntimeException(sprintf('Unable to parse version. "%s" is invalid.', $alias));
            }

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
