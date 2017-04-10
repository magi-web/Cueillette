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

namespace AppBundle\Services\Cueillette;

use AppBundle\Entity\Automaton;
use Symfony\Bundle\FrameworkBundle\Templating\EngineInterface;
use Symfony\Component\DomCrawler\Crawler;
use Psr\Log\LoggerInterface;

/**
 * Class CueilletteCrawler
 *
 * @category    Cueillette
 * @author      <mgi@emakina.fr>
 */
class CueilletteCrawler
{
    /** @var  string $domain */
    private $domain;

    /** @var  array $extraProducts */
    private $extraProducts;

    /** @var  string session token */
    private $token;

    /** @var  string cache directory */
    private $cacheDir;

    /** @var EngineInterface $templating */
    protected $templating;

    /**
     * CueilletteCrawler constructor.
     *
     * @param string $domain
     * @param array $extraProducts
     * @param string $cacheDir
     * @param EngineInterface $templating
     */
    public function __construct($domain, array $extraProducts, $cacheDir, EngineInterface $templating)
    {
        $this->extraProducts = $extraProducts;

        $this->domain = $domain;
        $this->websiteURL = 'http://' . $domain;

        $this->token = null;
        $this->cacheDir = $cacheDir;
        $this->templating = $templating;
    }

    /**
     * Fetch the catalog by browsing the website url
     *
     * @return array
     */
    public function fetchCatalog()
    {
        // create curl resource
        $ch = curl_init();

        // set url
        curl_setopt($ch, CURLOPT_URL, $this->websiteURL . "/31-nos-paniers-bio");

        //return the transfer as a string
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        // $output contains the output string
        $c_output = curl_exec($ch);

        // close curl resource to free up system resources
        curl_close($ch);

        $crawler = new Crawler($c_output);
        $crawler = $crawler->filter('[itemtype="http://schema.org/Product"]');
        $products = $crawler->each(
            function (Crawler $crawler, $i) {
                $productLink = $crawler->filter('[itemprop="url"]');

                $description = $crawler->filter('[itemprop="description"]')->text();
                $description = trim(preg_replace('/Composition(.*?):/', "", $description));

                return array(
                    'name'        => trim($productLink->attr('title')),
                    'description' => $description,
                    'price'       => floatval(str_replace(',', '.', $crawler->filter('[itemprop="price"]')->text())),
                    'url'         => $productLink->attr('href')
                );
            }
        );

        $products = array_merge($products, $this->extraProducts);

        return $products;
    }

    private function setToken($content)
    {
        $this->token = null;
        preg_match('/var static_token = \'(.*)\'/', $content, $matches);
        if (!empty($matches)) {
            var_dump($matches);
            $this->token = $matches[1];
        }
    }

    public function newCurlResource(Automaton $automaton)
    {
        $cookie_file_path = tempnam(sys_get_temp_dir(), 'CueilletteCookie');

        $ch = curl_init();

        curl_setopt(
            $ch,
            CURLOPT_USERAGENT,
            'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13) Gecko/20080311 Firefox/2.0.0.13'
        );
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_NOBODY, false);
        curl_setopt($ch, CURLOPT_URL, $this->websiteURL);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file_path);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $automaton->getCookieFile());
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $server_output = curl_exec($ch);

        $this->setToken($server_output);

        file_put_contents($this->cacheDir . DIRECTORY_SEPARATOR . 'home.html', $server_output);

        return $ch;
    }

    private function getProductsInCart($ch)
    {
        $ids = array();

        // set url
        curl_setopt($ch, CURLOPT_URL, $this->websiteURL . "/commande-rapide");

        //return the transfer as a string
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        // $output contains the output string
        $c_output = curl_exec($ch);

        file_put_contents($this->cacheDir . DIRECTORY_SEPARATOR . 'cart.html', $c_output);

        preg_match_all('/class="cart_quantity_delete" id="(\d+)_/', $c_output, $matches);
        if (!empty($matches)) {
            $ids = $matches[1];
        }

        return $ids;
    }

    private function flushCart($ch)
    {
        $productIds = $this->getProductsInCart($ch);
        foreach ($productIds as $productId) {
            $this->deleteItem($ch, $productId);
            sleep(1);
        }
    }

    private function addToCart($ch, $productId, $qty)
    {
        $query = http_build_query(
            array(
                'controller' => 'cart',
                'add'        => '1',
                'ajax'       => 'true',
                'qty'        => $qty,
                'id_product' => $productId,
                'token'      => $this->token
            )
        );

        return $this->postRequest($ch, $query);
    }

    private function deleteItem($ch, $productId)
    {
        $query = http_build_query(
            array(
                'controller'          => 'cart',
                'ajax'                => 'true',
                'delete'              => 'true',
                'summary'             => 'true',
                'ipa'                 => 0,
                'id_address_delivery' => 255,
                'id_product'          => $productId,
                'token'               => $this->token,
                'allow_refresh'       => 1
            )
        );

        return $this->postRequest($ch, $query);
    }

    private function postRequest($ch, $query)
    {

        $url = $this->websiteURL . "/?rand=" . (int) (microtime(true) * 1000);
        var_dump($url);
        var_dump($query);

        curl_setopt(
            $ch,
            CURLOPT_HTTPHEADER,
            array(
                "Host"             => $this->domain,
                "User-Agent"       => "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_12_2) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/55.0.2883.95 Safari/537.36",
                "Accept"           => "application/json, text/javascript, */*; q=0.01",
                "Accept-Language"  => "fr-FR,fr;q=0.8,en-US;q=0.6,en;q=0.4,nl;q=0.2",
                "Accept-Encoding"  => "gzip, deflate",
                "Accept-Charset"   => "ISO-8859-1,utf-8;q=0.7,*;q=0.7",
                "Content-Type"     => "application/x-www-form-urlencoded; charset=UTF-8",
                "Connection"       => "keep-alive",
                "X-Requested-With" => "XMLHttpRequest",
                "Referer"          => $this->websiteURL
            )
        );

        // set url
        curl_setopt($ch, CURLOPT_URL, $url);

        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt(
            $ch,
            CURLOPT_POSTFIELDS,
            $query
        );

        //return the transfer as a string
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        // $output contains the output string
        return curl_exec($ch);
    }

    /**
     * Ends the curl resource
     *
     * @param resource $ch
     */
    private function closeCurlResource($ch)
    {
        curl_close($ch);
    }

    /**
     * Prepare the cart
     *
     * @param array $products
     * @param Automaton $automaton
     */
    public function prepareCart(array $products, Automaton $automaton)
    {
        if (!empty($products)) {
            $ch = $this->newCurlResource($automaton);

            if ($ch) {
                if ($this->token) {
                    $this->flushCart($ch);
                    foreach ($products as $product) {
                        file_put_contents(
                            $this->cacheDir . DIRECTORY_SEPARATOR . "cart-{$product["id"]}.html",
                            $this->addToCart($ch, $product["id"], $product["qty"])
                        );

                        sleep(1);
                    }
                }

                $this->closeCurlResource($ch);
            }
        }
    }
}