<?php
namespace App\Commands;

use CodeIgniter\Test\CIUnitTestCase;
use App\Commands\Scrape;

class ScrapeTest extends CIUnitTestCase {

    public function testNoUrl() {

        // TODO: Unit tests - need to figure out how to get the CLI runner included in tests, documentation is unclear
        /*$scrape = new Scrape();
        $scrape->run();

        $this->assertCloseEnoughString("[Exception]\n\nURL not supplied");*/

    }

    public function testUrlWithNoProducts() {

    }

    public function testUrl() {

    }

}