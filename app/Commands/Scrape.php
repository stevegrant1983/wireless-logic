<?php
namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Exception;
use DOMDocument;
use DOMXPath;
use DOMElement;

class Scrape extends BaseCommand {

    protected $group = 'demo';
    protected $name = "scrape";
    protected $description = "Scrapes data from supplied URL and returns parsed and sorted product information";
    protected $usage = "scrape <url>";
    protected $arguments = [
        'url' => "The web URL to scrape"
    ];

    public function run(array $params) {

        if(count($params) === 0) {
            throw new Exception("URL not supplied");
        }

        try {
            $html = $this->curlRequest($params[0]);
            $dom = new DOMDocument();
            // Ensure HTML5/old DOM parser mismatches are ignored
            libxml_use_internal_errors(true);
            $dom->loadHTML($html);
            $products = $this->parseContent($dom);
            CLI::print(json_encode($products));
        }
        catch (\Exception $e) {
            CLI::print($e->getMessage());
        }

    }

    private function curlRequest(string $url) {

        // Perform curl request on URL
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true
        ]);

        // Execute the request
        $response = curl_exec($curl);

        // If the HTTP response code is a 4xx or 5xx, throw an exception
        if(curl_getinfo($curl, CURLINFO_HTTP_CODE) > 308) {
            throw new Exception("Unable to retrieve URL");
        }

        return $response;

    }

    private function parseContent(DOMDocument $dom) {

        // Parse DOM and return objects
        $xpath = new DOMXPath($dom);

        $products = [];

        // First check we have a structure - is there a <div> with class "package " (space important as the example code contains other classes too)
        $check = $xpath->query('//div[contains(@class, "package ")]');
        if($check->length === 0) {
            throw new Exception("Invalid page structure, no <div> elements found with class 'package', as expected");
        }

        // Iterate through each of the <div class="package "> elements - these are our products
        // Product title is contained in an <h3> element
        foreach($xpath->query('//div[contains(@class, "package ")]/div/h3') as $index => $title) {
            $products[$index] = [];
            $products[$index]['title'] = $title->nodeValue;
        }

        // Description is misleadingly contained in a class called "package-name"
        foreach($xpath->query('//div[contains(@class, "package-name")]') as $index => $description) {
            $products[$index]['description'] = $description->nodeValue;
        }

        // With the price, we need to work out whether it is an annual or monthly price and work out the annual cost for monthly packages
        // so that we can sort them in the required order
        foreach($xpath->query('//div[contains(@class, "package-price")]') as $index => $price) {

            $elements = $price->getElementsByTagName('span');
            $products[$index]['price'] = $this->extractPrice($elements->item(0));

            // If the price contains a paragraph, it's an annual package so has a discount value - extract this
            $elements = $price->getElementsByTagName('p');
            if($elements->length > 0) {
                $products[$index]['discount'] = $this->extractPrice($elements->item(0));
                $products[$index]['annual_price'] = $products[$index]['price']; // Set an annual_price key/value pair for sorting purposes
            }
            else {
                // If there is no paragraph within the price element, it's monthly, so we need to calculate the annual cost for sorting purposes
                // Also add a 0-value discount field for uniform structure
                $products[$index]['discount'] = 0;
                $products[$index]['annual_price'] = $products[$index]['price'] * 12;
            }

        }

        // Sort products by total annual cost
        usort($products, [$this, "descendingSort"]);

        // Remove the annual_cost fields as these are not required any longer
        return $this->removeKeys($products);

    }

    private function extractPrice(DOMElement $element) {

        // Remove the pound sign and convert to a float
        return floatval(str_replace("£", "", str_replace("Save ", "", $element->nodeValue)));

    }

    private function descendingSort(array $product1, array $product2) {

        // Check to see if the annual_price figure in $product1 is greater than or less than the annual_price figure in $product2
        // Also, if equal, return 0 so that usort passes over
        if($product1['annual_price'] == $product2['annual_price']) return 0;
        return ($product1['annual_price'] < $product2['annual_price']) ? 1 : -1;

    }

    private function removeKeys(array $products) {

        foreach($products as &$product) {
            unset($product['annual_price']);
        }

        return $products;

    }

}