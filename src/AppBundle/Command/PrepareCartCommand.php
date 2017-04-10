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

use AppBundle\AppBundle;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class PrepareCartCommand
 *
 * @category Cueillette
 * @author <mgi@emakina.fr>
 */
class PrepareCartCommand extends ContainerAwareCommand

{

    /**
     * @inheritdoc
     */
    protected function configure()
    {
        $this
            // the name of the command (the part after "bin/console")
            ->setName('app:prepare-cart')

            // the short description shown while running "php bin/console list"
            ->setDescription('Fetch the google spreadsheet and prepare the cart session.')

            // the full command description shown when running the command with
            // the "--help" option
            ->setHelp("Fetch the google spreadsheet and prepare the cart session.")
        ;
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $repository = $this->getContainer()->get('doctrine')->getRepository('AppBundle:Automaton');
        $automatons = $repository->findAll();

        /** @var \AppBundle\Services\Cueillette\CueilletteSpreadsheet $spreadSheetService */
        $spreadSheetService = $this->getContainer()->get('app.cueillette.spreadsheet');

        /** @var \AppBundle\Services\Cueillette\CueilletteCrawler $crawlerService */
        $crawlerService = $this->getContainer()->get('app.cueillette.crawler');

        /** @var \AppBundle\Entity\Automaton $automaton */
        foreach($automatons as $automaton) {
            $spreadSheetService->setAutomaton($automaton);
            $products = $spreadSheetService->getProductsToBuy();

            $crawlerService->prepareCart($products, $automaton);
        }
    }
}