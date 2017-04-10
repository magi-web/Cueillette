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

namespace AppBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class UpdateCatalogCommand
 *
 * @category    Cueillette
 * @author      <mgi@emakina.fr>
 */
class UpdateCatalogCommand extends ContainerAwareCommand
{
    /**
     * @inheritdoc
     */
    protected function configure()
    {
        $this
            // the name of the command (the part after "bin/console")
            ->setName('app:update-catalog')

            // the short description shown while running "php bin/console list"
            ->setDescription('Fetch the new catalog from website.')

            // the full command description shown when running the command with
            // the "--help" option
            ->setHelp("Fetch the new catalog from website & updates the spreadsheet...")
        ;
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var \AppBundle\Services\Cueillette\CueilletteCrawler $crawlerService */
        $crawlerService = $this->getContainer()->get('app.cueillette.crawler');
        $products = $crawlerService->fetchCatalog();

        /** @var \AppBundle\Services\Cueillette\CueilletteSpreadsheet $spreadSheetService */
        $spreadSheetService = $this->getContainer()->get('app.cueillette.spreadsheet');
        $repository = $this->getContainer()->get('doctrine')->getRepository('AppBundle:Automaton');
        $automatons = $repository->findAll();

        /** @var \AppBundle\Entity\Automaton $automaton */
        foreach($automatons as $automaton) {
            $spreadSheetService->setAutomaton($automaton);
            $spreadSheetService->importProducts($products, true);
        }
    }
}