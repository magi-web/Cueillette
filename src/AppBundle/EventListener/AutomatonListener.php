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

namespace AppBundle\EventListener;

use AppBundle\Entity\Automaton;
use AppBundle\Services\AutomatonService;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;

/**
 * Class AutomatonListener
 *
 * @category    Cueillette
 * @author      <mgi@emakina.fr>
 */
class AutomatonListener
{
    private $automatonService;

    public function __construct(AutomatonService $automatonService)
    {
        $this->automatonService = $automatonService;
    }

    public function postPersist(LifecycleEventArgs $args)
    {
        $entity = $args->getEntity();

        $this->handleFiles($entity);
    }

    public function preUpdate(PreUpdateEventArgs $args)
    {
        $entity = $args->getEntity();

        $this->handleFiles($entity);
        $this->handleGoogleAuthentication($entity);
    }

    public function postLoad(LifecycleEventArgs $args)
    {
        $entity = $args->getEntity();

        if ($entity instanceof Automaton) {
            $isReady = 1;
            if ($entity->getCookie() && ($path = $this->automatonService->getCookieFile($entity)) !== null) {
                $entity->setCookieFile($path);
                $isReady = $isReady << 1;
            }

            if (($path = $this->automatonService->getGGCredentialFile($entity)) !== null) {
                $entity->setGgCredentialFile($path);
                $isReady = $isReady << 1;
            }

            $entity->setIsReady($isReady === Automaton::IS_READY_STATE);
        }
    }

    public function handleFiles($entity)
    {
        if (!$entity instanceof Automaton) {
            return;
        }

        $this->automatonService->storeCookieFile($entity);
    }

    public function handleGoogleAuthentication($entity)
    {
        if(!$entity instanceof Automaton) {
            return;
        }

        $this->automatonService->setGoogleAuthenticationKey($entity);
    }
}