<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Platform\PostInstall\Command;

use Composer\Command\BaseCommand;
use Composer\InstalledVersions;
use Composer\IO\IOInterface;
use Composer\Semver\Semver;
use Composer\Semver\VersionParser;
use Exception;
use Ibexa\Platform\PostInstall\IbexaProductVersion;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\ProcessBuilder;

class IbexaSetupCommand extends BaseCommand
{
    /** @var \Composer\Semver\VersionParser */
    private $versionParser;

    private const PSH_RESOURCES_PATH = __DIR__ . '/../../../resources/platformsh';

    private const DEFAULT_SOLR_PORT = 9000;

    protected function configure(): void
    {
        $this->setName('ibexa:setup')
             ->setDescription('Runs post install configuration tool.')
             ->addOption('platformsh', null, InputOption::VALUE_NONE, 'Install Platform.sh config files')
             ->addOption('solr', null, InputOption::VALUE_OPTIONAL, 'Setup SOLR for development purposes', self::DEFAULT_SOLR_PORT)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($input->getOption('platformsh')) {
            $this->installPlatformShConfigFiles($input, $output);
        }
        if ($input->getOption('solr')) {
            $this->installSolrConfigFiles($input, $output);
        }

        return 1;
    }

    private function installPlatformShConfigFiles(InputInterface $input, OutputInterface $output): void
    {
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

    private function installSolrConfigFiles(InputInterface $input, OutputInterface $output): void
    {
        $port = is_numeric($input->getOption('solr')) ? (int)$input->getOption('solr') : null;

        if ($port === null) {
            throw new InvalidArgumentException('Port must be proper number.');
        }

        $this->getIO()->write(
            sprintf('Installing SOLR config files... (used PORT: %d)', $port),
            true,
            IOInterface::NORMAL
        );
        $fileSystem = new Filesystem();

        $product = IbexaProductVersion::getInstalledProduct();
        $version = IbexaProductVersion::getInstalledProductVersion();

        $solrConfigFiles = $this->getSolrVersionSpecificFiles($product, $version);

        if ($solrConfigFiles->count() === 0) {
            throw new InvalidArgumentException(
                sprintf('No SOLR configuration provided for %s (%s)', $product, $version
            ));
        }
        if (!$fileSystem->exists('bin/tika-app-1.20.jar')) {
            $this->getIO()->write('Downloading Tika App 1.20 ...', true, IOInterface::NORMAL);
            file_put_contents(
                "bin/tika-app-1.20.jar",
                fopen("https://archive.apache.org/dist/tika/tika-app-1.20.jar", 'r')
            );
            if (!$fileSystem->exists('bin/tika-app-1.20.jar')) {
                $this->getIO()->write('Download failed', true, IOInterface::NORMAL);
                return;
            }
        }

        if (!$fileSystem->exists('solr-7.7.3.tgz')) {
            $this->getIO()->write('Downloading SOLR 7.7.3...', true, IOInterface::NORMAL);
            file_put_contents(
                "solr-7.7.3.tgz",
                fopen("https://archive.apache.org/dist/lucene/solr/7.7.3/solr-7.7.3.tgz", 'r')
            );
            if (!$fileSystem->exists('solr-7.7.3.tgz')) {
                $this->getIO()->write('Download failed', true, IOInterface::NORMAL);
                return;
            }
        }

        if (!$fileSystem->exists('solr') && $fileSystem->exists('solr-7.7.3.tgz')) {
            $pharData = new \PharData('solr-7.7.3.tgz');
            $pharData->extractTo('.', null, true);
            $fileSystem->rename('solr-7.7.3', 'solr');
        }

        foreach ($solrConfigFiles as $file) {
            $fileSystem->copy(
                $file->getPathname(),
                'solr/server/ez/template/' . $file->getRelativePathname(),
                true
            );
        }
        $fileSystem->copy(
            'solr/server/solr/solr.xml',
            'solr/server/ez/solr.xml'
        );

        $process = (new ProcessBuilder(
            ['solr/bin/solr', '-s', 'ez', '-p', $port]))
            ->getProcess();
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }
        $this->getIO()->write($process->getOutput(), true, IOInterface::NORMAL);

        $this->getIO()->write('Creating cores...', true, IOInterface::NORMAL);
        $this->createCore('collection1', $port);
        $this->createCore('econtent', $port);
        $this->createCore('econtent_back', $port);

        $this->getIO()->write('Updating .env file...', true, IOInterface::NORMAL);
        $fp = fopen('.env', 'a');//opens file in append mode
        fwrite($fp, "SEARCH_ENGINE=solr\n");
        fwrite($fp, "SISO_SEARCH_SOLR_HOST=localhost\n");
        fwrite($fp, "SISO_SEARCH_SOLR_PORT={$port}\n");
        fwrite($fp, "SISO_SEARCH_SOLR_CORE=collection1\n");
        fwrite($fp, "SISO_SEARCH_SOLR_PATH=\n");
        fwrite($fp, "SOLR_DSN=http://\${SISO_SEARCH_SOLR_HOST}:\${SISO_SEARCH_SOLR_PORT}\${SISO_SEARCH_SOLR_PATH}\n");
        fwrite($fp, "SOLR_HOST=\${SISO_SEARCH_SOLR_HOST}\n");
        fwrite($fp, "SOLR_CORE=\${SISO_SEARCH_SOLR_CORE}\n");

        fclose($fp);

        $this->getIO()->write(
            'Setup complete, please run bin/console ibexa:reindex',
            true,
            IOInterface::NORMAL
        );
    }

    private function createCore(string $coreName, int $port): void
    {
        $headers = get_headers("http://localhost:{$port}/solr/{$coreName}/select");

        if ($headers && strpos( $headers[0], '200')) {
            $this->getIO()->write(
                sprintf('Core %s already exist, skiping.', $coreName),
                true,
                IOInterface::NORMAL
            );
            return;
        }

        $process = (new ProcessBuilder([
            'solr/bin/solr',
            'create_core', '-c', $coreName,
            '-d', 'solr/server/ez/template',
            '-p' , $port,
        ]))->getProcess();

        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }
        $this->getIO()->write($process->getOutput(), true, IOInterface::NORMAL);
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

    protected function getSolrVersionSpecificFiles(string $product, string $version): Finder
    {
        $productDir = str_replace('/', '-', $product);
        $versionDir = $this->getVersionDirectory(
            $product,
            self::PSH_RESOURCES_PATH . '/' . $productDir
        );

        $finder = new Finder();
        $finder
            ->in(self::PSH_RESOURCES_PATH . '/' . $productDir . '/' . $versionDir . '/' . '.platform/configsets/solr6/conf')
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
