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
use InvalidArgumentException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\ProcessBuilder;

/**
 * @internal
 */
final class IbexaSetupSolrCommand extends BaseCommand
{
    private const PSH_RESOURCES_PATH = __DIR__ . '/../../../resources/platformsh';

    private const SOLR_RESOURCES_PATH = __DIR__ . '/../../../resources/solr';

    private const DEFAULT_SOLR_PORT = 9000;

    private const TIKA_APP_URL = 'https://archive.apache.org/dist/tika/tika-app-1.20.jar';

    private const SOLR_URL = 'https://archive.apache.org/dist/lucene/solr/7.7.3/solr-7.7.3.tgz';

    protected function configure(): void
    {
        $this->setName('ibexa:setup:solr')
             ->setDescription('Runs solr configuration setup for developing purposes.')
             ->addOption('port', null, InputOption::VALUE_OPTIONAL, 'Setup SOLR for development purposes', self::DEFAULT_SOLR_PORT)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $port = is_numeric($input->getOption('port')) ? (int)$input->getOption('port') : null;

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

        $httpDownloader = $this->getComposer()->getLoop()->getHttpDownloader();

        if (!$fileSystem->exists('bin/tika-app-1.20.jar')) {
            $this->getIO()->write('Downloading Tika App 1.20 ...', true, IOInterface::NORMAL);
            $httpDownloader->copy(
                self::TIKA_APP_URL,
                'bin/tika-app-1.20.jar'
            );
        }

        if (!$fileSystem->exists('solr-7.7.3.tgz')) {
            $this->getIO()->write('Downloading SOLR 7.7.3...', true, IOInterface::NORMAL);
            $httpDownloader->copy(
                self::SOLR_URL,
                'solr-7.7.3.tgz'
            );
        }

        if (!$fileSystem->exists('solr') && $fileSystem->exists('solr-7.7.3.tgz')) {
            $pharData = new \PharData('solr-7.7.3.tgz');
            $pharData->extractTo('.', null, true);
            $fileSystem->rename('solr-7.7.3', 'solr');
        }

        $this->getIO()->write('Copying common configuration files...', true, IOInterface::NORMAL);

        $solrCommonConfigFiles = $this->getSolrCommonFiles($product);
        foreach ($solrCommonConfigFiles as $file) {
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

        $specificSolrConfiguration = $this->getSolrVersionSpecificFiles($product);

        $this->getIO()->write('Copying version specific configuration files...', true, IOInterface::NORMAL);
        foreach ($specificSolrConfiguration as $file) {
            $fileSystem->copy(
                $file->getPathname(),
                'solr/server/ez/template/' . $file->getRelativePathname(),
                true
            );
        }

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

        return 0;
    }

    private function createCore(string $coreName, int $port): void
    {
        $headers = get_headers("http://localhost:{$port}/solr/{$coreName}/select");

        if ($headers && strpos($headers[0], '200')) {
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
            '-p', $port,
        ]))->getProcess();

        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }
        $this->getIO()->write($process->getOutput(), true, IOInterface::NORMAL);
    }

    protected function getSolrVersionSpecificFiles(string $product): Finder
    {
        $productDir = str_replace('/', '-', $product);
        $versionDir = $this->getVersionDirectory(
            $product,
            self::SOLR_RESOURCES_PATH . '/' . $productDir
        );

        $finder = new Finder();
        $finder
            ->in(self::SOLR_RESOURCES_PATH . '/' . $productDir . '/' . $versionDir . '/' . 'override')
            ->ignoreDotFiles(false)
            ->followLinks()
            ->files();

        return $finder;
    }

    protected function getSolrCommonFiles(string $product): Finder
    {
        $productDir = str_replace('/', '-', $product);
        $versionDir = $this->getVersionDirectory(
            $product,
            self::SOLR_RESOURCES_PATH . '/' . $productDir
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
        $directoryProvider = new VersionDirectoryProvider();

        return $directoryProvider->get($product, $path);
    }
}
