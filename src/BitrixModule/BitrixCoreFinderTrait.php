<?php

namespace Adv\BitrixEventsPlugin;

use Bitrix\Main\Application;
use Bitrix\Main\SystemException;
use Composer\Installer\PackageEvent;
use Composer\Script\Event;
use Exception;
use RuntimeException;
use Throwable;

/**
 * Trait BitrixCoreFinderTrait
 *
 * @package Adv\BitrixEventsPlugin
 */
trait BitrixCoreFinderTrait
{
    private $composerExtra = 'bitrix-dir';

    /**
     * @var Application
     */
    private static $application;
    private        $prologPath = '/modules/main/include/prolog_before.php';
    private        $defaults   = [
        './bitrix',
        '../../bitrix',
        'web/bitrix',
    ];

    /**
     * @param $path
     *
     * @return bool
     */
    protected function tryPath(string $path): bool
    {
        return \file_exists($path);
    }

    /**
     * @param PackageEvent $event
     *
     * @return string
     *
     * @throws RuntimeException
     * @throws BitrixEventPluginException
     */
    protected function findBitrixCorePath(PackageEvent $event): string
    {
        $extra = $event->getComposer()
                       ->getPackage()
                       ->getExtra();

        $pathList = \array_merge([$extra['bitrix-dir']], $this->defaults);

        foreach ($pathList as $path) {
            if (!$path) {
                $event->getIO()->writeError('Extras in bitrix-dir is not defined; try a default paths.');

                continue;
            }

            if ($this->tryPath($this->normalizePath($path))) {
                return $path;
            }
        }

        while (true) {
            $path = \trim(
                $event->getIO()->ask("We cant find bitrix in your project. Write you`r document root path or press Enter to skip.\n")
            );

            if (!$path) {
                break;
            }

            if ($this->tryPath($this->normalizePath($path))) {
                return $path;
            }
        }

        throw new BitrixEventPluginException('Wrong document root or bitrix is not found.');
    }

    /**
     * @param PackageEvent $event
     *
     * @throws BitrixEventPluginException
     */
    public function includeBitrix(PackageEvent $event)
    {
        try {
            self::$application = Application::getInstance();
        } catch (Throwable $e) {
            try {
                $this->includeBitrixFromDocumentRoot($this->findBitrixCorePath($event));
            } catch (Exception $e) {
                throw new BitrixEventPluginException('Wrong document root or bitrix is not found.');
            }
        }
    }

    /**
     * @param string $documentRoot
     *
     * @throws SystemException
     */
    public function includeBitrixFromDocumentRoot(string $documentRoot)
    {
        \define('NO_KEEP_STATISTIC', 'Y');
        \define('NOT_CHECK_PERMISSIONS', true);
        \define('PUBLIC_AJAX_MODE', true);
        \define('CHK_EVENT', false);
        \define('BX_WITH_ON_AFTER_EPILOG', false);
        \define('BX_NO_ACCELERATOR_RESET', true);

        $_SERVER['DOCUMENT_ROOT'] = $GLOBALS['DOCUMENT_ROOT'] = $documentRoot;

        /** @noinspection PhpIncludeInspection */
        require_once \sprintf('%s%s', $documentRoot, $this->prologPath);

        self::$application = Application::getInstance();
    }

    /**
     * @param PackageEvent $event
     *
     * @return Application
     *
     * @throws BitrixEventPluginException
     */
    public function getApplication(PackageEvent $event): Application
    {
        if (!$this::$application) {
            $this->includeBitrix($event);
        }

        return $this::$application;
    }

    /**
     * @param PackageEvent $event
     *
     * @throws BitrixEventPluginException
     */
    public function setApplication(PackageEvent $event)
    {
        if (!$this::$application) {
            $this->includeBitrix($event);
        }
    }

    /**
     * @param string $path
     *
     * @return string
     */
    private function normalizePath(string $path): string
    {
        return \realpath(\sprintf('%s%s%s%s%s', \getcwd(), DIRECTORY_SEPARATOR, $path, DIRECTORY_SEPARATOR, $this->prologPath)) ?: '';
    }
}
