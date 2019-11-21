<?php

namespace DataDog\TestHelpers;

use PHPUnit\Framework\TestCase;

/**
 * Making this variable global to this file is necessary for interacting with
 * the stubbed global functions below.
 */
$socketSpy = new SocketSpy();

/**
 * Class SocketSpyTestCase
 *
 * A PHPUnit TestCase useful for spying on calls to global built in socket
 * functions
 *
 * @package DataDog
 */
class SocketSpyTestCase extends TestCase
{
    /**
     * Set up a spy object to capture calls to global built in socket functions
     */
    protected function setUp()
    {
        global $socketSpy;

        $socketSpy = new SocketSpy();

        parent::setUp();
    }

    /**
     * @return \DataDog\TestHelpers\SocketSpy
     */
    protected function getSocketSpy()
    {
        global $socketSpy;

        return $socketSpy;
    }
}
