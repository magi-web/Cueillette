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

namespace AppBundle\Controller;

use AppBundle\Entity\Automaton;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;

/**
 * Automaton controller.
 *
 * @Route("automaton")
 */
class AutomatonController extends Controller
{
    /**
     * Lists all automaton entities.
     *
     * @Route("/", name="automaton_index")
     * @Method("GET")
     */
    public function indexAction()
    {
        $em = $this->getDoctrine()->getManager();

        $automatons = $em->getRepository('AppBundle:Automaton')->findAll();

        return $this->render(
            'automaton/index.html.twig',
            array(
                'automatons' => $automatons,
            )
        );
    }

    /**
     * Creates a new automaton entity.
     *
     * @Route("/new", name="automaton_new")
     * @Method({"GET", "POST"})
     */
    public function newAction(Request $request)
    {
        $automaton = new Automaton();
        $form = $this->createForm('AppBundle\Form\AutomatonType', $automaton);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($automaton);
            $em->flush($automaton);

            return $this->redirectToRoute('automaton_show', array('id' => $automaton->getId()));
        }

        return $this->render(
            'automaton/new.html.twig',
            array(
                'automaton' => $automaton,
                'form'      => $form->createView(),
            )
        );
    }

    /**
     * Finds and displays a automaton entity.
     *
     * @Route("/{id}", name="automaton_show")
     * @Method("GET")
     */
    public function showAction(Automaton $automaton)
    {
        $deleteForm = $this->createDeleteForm($automaton);

        return $this->render(
            'automaton/show.html.twig',
            array(
                'automaton'   => $automaton,
                'delete_form' => $deleteForm->createView(),
            )
        );
    }

    /**
     * Displays a form to edit an existing automaton entity.
     *
     * @Route("/{id}/edit", name="automaton_edit")
     * @Method({"GET", "POST"})
     */
    public function editAction(Request $request, Automaton $automaton)
    {
        $url = '';
        if (!$automaton->getIsReady()) {
            /** @var \AppBundle\Services\Cueillette\CueilletteSpreadsheet $ggService */
            $ggService = $this->get('app.cueillette.spreadsheet');
            $url = $ggService->getGGAuthUrl();
        }

        $deleteForm = $this->createDeleteForm($automaton);
        $editForm = $this->createForm('AppBundle\Form\AutomatonType', $automaton);
        $editForm->handleRequest($request);

        if ($editForm->isSubmitted() && $editForm->isValid()) {
            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('automaton_edit', array('id' => $automaton->getId()));
        }

        return $this->render(
            'automaton/edit.html.twig',
            array(
                'automaton'   => $automaton,
                'edit_form'   => $editForm->createView(),
                'delete_form' => $deleteForm->createView(),
                'gg_auth_url' => $url
            )
        );
    }

    /**
     * Deletes a automaton entity.
     *
     * @Route("/{id}", name="automaton_delete")
     * @Method("DELETE")
     */
    public function deleteAction(Request $request, Automaton $automaton)
    {
        $form = $this->createDeleteForm($automaton);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->remove($automaton);
            $em->flush($automaton);
        }

        return $this->redirectToRoute('automaton_index');
    }

    /**
     * Creates a form to delete a automaton entity.
     *
     * @param Automaton $automaton The automaton entity
     *
     * @return \Symfony\Component\Form\Form The form
     */
    private function createDeleteForm(Automaton $automaton)
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('automaton_delete', array('id' => $automaton->getId())))
            ->setMethod('DELETE')
            ->getForm();
    }

    /**
     * Checks automaton google link token.
     *
     * @Route("/{id}/catalog", name="automaton_catalog")
     * @Method("GET")
     */
    public function catalogAction(Request $request, Automaton $automaton)
    {
        /** @var \AppBundle\Services\Cueillette\CueilletteCrawler $crawlerService */
        $crawlerService = $this->get('app.cueillette.crawler');
        $products = $crawlerService->fetchCatalog();

        /** @var \AppBundle\Services\Cueillette\CueilletteSpreadsheet $spreadSheetService */
        $spreadSheetService = $this->get('app.cueillette.spreadsheet');
        $spreadSheetService->setAutomaton($automaton);
        $spreadSheetService->importProducts($products, true);

        $this->addFlash('notice', 'The spreadsheet has been updated !');

        return $this->redirectToRoute('automaton_edit', array('id' => $automaton->getId()));
    }

    /**
     * Checks automaton google link token.
     *
     * @Route("/{id}/cart", name="automaton_cart")
     * @Method("GET")
     */
    public function cartAction(Request $request, Automaton $automaton)
    {
        /** @var \AppBundle\Services\Cueillette\CueilletteSpreadsheet $spreadSheetService */
        $spreadSheetService = $this->get('app.cueillette.spreadsheet');
        $spreadSheetService->setAutomaton($automaton);
        $products = $spreadSheetService->getProductsToBuy();

        /** @var \AppBundle\Services\Cueillette\CueilletteCrawler $crawlerService */
        $crawlerService = $this->get('app.cueillette.crawler');
        $crawlerService->prepareCart($products, $automaton);

        $this->addFlash('notice', 'The cart has been updated !');

        return $this->redirectToRoute('automaton_edit', array('id' => $automaton->getId()));
    }
}
