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

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;


/**
 * Class Automaton
 *
 * @category    Cueillette
 * @author      <mgi@emakina.fr>
 */
/**
 * @ORM\Entity
 * @ORM\Table(name="automator")
 */
class Automaton
{
    const IS_READY_STATE = (1 << 2);
    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer")
     */
    protected $id;

    /**
     * @ORM\Column(type="string", unique=true)
     */
    protected $spreadsheetId;
    /**
     * @ORM\Column(type="string")
     */
    protected $ggEditor;
    /**
     * @ORM\Column(type="text")
     */
    protected $cookie;

    /**
     * @ORM\OneToOne(targetEntity="AppBundle\Entity\User", cascade={"persist"})
     */
    protected $user;

    protected $cookieFile;

    protected $ggCredentialFile;

    protected $ggCredentialCode;

    protected $isReady;

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param mixed $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * @return mixed
     */
    public function getSpreadsheetId()
    {
        return $this->spreadsheetId;
    }

    /**
     * @param mixed $spreadsheetId
     */
    public function setSpreadsheetId($spreadsheetId)
    {
        $this->spreadsheetId = $spreadsheetId;
    }

    /**
     * @return mixed
     */
    public function getGgEditor()
    {
        return $this->ggEditor;
    }

    /**
     * @param mixed $ggEditor
     */
    public function setGgEditor($ggEditor)
    {
        $this->ggEditor = $ggEditor;
    }

    /**
     * @return mixed
     */
    public function getCookie()
    {
        return $this->cookie;
    }

    /**
     * @param mixed $cookie
     */
    public function setCookie($cookie)
    {
        $this->cookie = $cookie;
    }

    /**
     * @return mixed
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * @param mixed $user
     */
    public function setUser($user)
    {
        $this->user = $user;
    }

    /**
     * @return mixed
     */
    public function getCookieFile()
    {
        return $this->cookieFile;
    }

    /**
     * @param mixed $cookieFile
     */
    public function setCookieFile($cookieFile)
    {
        $this->cookieFile = $cookieFile;
    }

    /**
     * @return mixed
     */
    public function getGgCredentialFile()
    {
        return $this->ggCredentialFile;
    }

    /**
     * @param mixed $ggCredentialFile
     */
    public function setGgCredentialFile($ggCredentialFile)
    {
        $this->ggCredentialFile = $ggCredentialFile;
    }

    /**
     * @return mixed
     */
    public function getGgCredentialCode()
    {
        return $this->ggCredentialCode;
    }

    /**
     * @param mixed $ggCredentialCode
     */
    public function setGgCredentialCode($ggCredentialCode)
    {
        $this->ggCredentialCode = $ggCredentialCode;
    }

    /**
     * @return mixed
     */
    public function getIsReady()
    {
        return $this->isReady;
    }

    /**
     * @param mixed $isReady
     */
    public function setIsReady($isReady)
    {
        $this->isReady = $isReady;
    }



}