<?php

/**
 * Stubbed versions of socket functions
 *
 * Useful for testing DataDog\DogStatsd::send without actually attempting to
 * send data over a socket.
 *
 * @see \DataDog\UnitTests\DogStatsd\SocketsTest
 * @see \DataDog\TestHelpers\SocketSpy
 * @see \DataDog\TestHelpers\SocketSpyTestCase
 */

namespace DataDog;

/**
 * Stub of built in global PHP function socket_create
 *
 * @param int $domain
 * @param int $type
 * @param int $protocol
 * @return resource
 */
function socket_create($domain, $type, $protocol)
{
    global $socketSpy;

    $socketSpy->socketCreateWasCalledWithArgs($domain, $type, $protocol);

    // A PHP resource of unimportance, useful primarily to assert that our stubs
    // of the global socket functions return or take a deterministic value.
    $resource = fopen('/dev/null', 'r');

    $socketSpy->socketCreateDidReturn($resource);

    return $resource;
}

/**
 * Stub of built in global PHP function socket_set_nonblock
 *
 * @param resource $socket
 */
function socket_set_nonblock($socket)
{
    global $socketSpy;

    $socketSpy->socketSetNonblockWasCalledWithArg($socket);
}

/**
 * Stub of built in global PHP function socket_sendto
 *
 * @param resource $socket
 * @param string $buf
 * @param int $len
 * @param int $flags
 * @param string $addr
 * @param int $port
 */
function socket_sendto($socket, $buf, $len, $flags, $addr, $port=null)
{
    global $socketSpy;

    $socketSpy->socketSendtoWasCalledWithArgs($socket, $buf, $len, $flags, $addr, $port);
}

/**
 * Stub of built in global PHP function socket_close
 *
 * @param resource $socket
 */
function socket_close($socket)
{
    global $socketSpy;

    $socketSpy->socketCloseWasCalled($socket);
}
