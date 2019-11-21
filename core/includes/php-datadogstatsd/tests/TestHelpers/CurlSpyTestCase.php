<?php

namespace DataDog\TestHelpers;

use PHPUnit\Framework\TestCase;

$curlSpy = new CurlSpy();

class CurlSpyTestCase extends TestCase
{
    /**
     * Set up a spy object to capture calls to built in curl functions
     */
    protected function setUp()
    {
        global $curlSpy;

        $curlSpy = new CurlSpy();

        parent::setUp();
    }

    /**
     * @return \DataDog\TestHelpers\CurlSpy
     */
    protected function getCurlSpy()
    {
        global $curlSpy;

        return $curlSpy;
    }
}
