<?php
/**
 * Emakina
 *
 * NOTICE OF LICENSE
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Cueillette's project to newer
 * versions in the future.
 *
 * @category    Cueillette
 * @package     Cueillette
 * @copyright   Copyright (c) 2017 Emakina. (http://www.emakina.fr)
 */

namespace AppBundle\Services;


use AppBundle\Entity\Automaton;
use AppBundle\Services\Cueillette\CueilletteSpreadsheet;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Class AutomatonService
 *
 * @category    Cueillette
 * @author      <mgi@emakina.fr>
 */
class AutomatonService
{
    private $dataDir;
    private $spreadsheetService;

    /**
     * AutomatonService constructor.
     *
     * @param string $rootDir
     * @param CueilletteSpreadsheet $spreadsheetService
     */
    public function __construct($rootDir, CueilletteSpreadsheet $spreadsheetService)
    {
        $varDirectory = realpath($rootDir . DIRECTORY_SEPARATOR . '../var');
        $dataDir = $varDirectory . DIRECTORY_SEPARATOR . 'data';
        if (!file_exists($dataDir)) {
            mkdir($dataDir, 0770, true);
        }
        $this->dataDir = $dataDir;

        $this->spreadsheetService = $spreadsheetService;
    }

    private function getAutomatonDirectory(Automaton $automaton)
    {
        $path = '';
        if ($automaton && $automaton->getId()) {
            $path = $this->dataDir . DIRECTORY_SEPARATOR . $automaton->getId() . DIRECTORY_SEPARATOR;
            if (!file_exists($path)) {
                mkdir($path);
            }
        }

        return $path;
    }

    private function getPathForFile($filename, Automaton $automaton, $checkExists = true)
    {
        $path = $this->getAutomatonDirectory($automaton) . $filename;
        if ($checkExists && !file_exists($path)) {
            $path = null;
        }

        return $path;
    }

    public function getCookieFile(Automaton $automaton, $checkExists = true)
    {
        return $this->getPathForFile('cookie.txt', $automaton, $checkExists);
    }

    public function getGGCredentialFile(Automaton $automaton, $checkExists = true)
    {
        return $this->getPathForFile('sheets.googleapis.com-cueillette-paysanne.json', $automaton, $checkExists);
    }

    public function storeCookieFile(Automaton $automaton)
    {
        $cookieFile = $this->getCookieFile($automaton, false);
        if ($cookieFile !== null && $automaton->getCookie() !== '') {
            file_put_contents($cookieFile, $automaton->getCookie());
        }
    }

    public function upload(Automaton $automaton, UploadedFile $file)
    {
        $fileName = md5(uniqid()) . '.' . $file->guessExtension();

        $file->move($this->getAutomatonDirectory($automaton), $fileName);

        return $fileName;
    }

    public function setGoogleAuthenticationKey(Automaton $automaton)
    {
        if (!empty($automaton->getGgCredentialCode())) {
            $this->spreadsheetService->setAccessToken(
                $automaton->getGgCredentialCode(),
                $this->getGGCredentialFile($automaton, false)
            );
        }
    }
}