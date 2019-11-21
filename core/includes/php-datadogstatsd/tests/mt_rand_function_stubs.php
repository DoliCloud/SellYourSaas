<?php

/**
 * Stubbed versions of mt_rand functions
 *
 * Useful for testing DataDog\DogStatsd::send with deterministic values.
 *
 * @see \DataDog\UnitTests\DogStatsd\SocketsTest
 */

namespace DataDog;

/**
 * Making this variable global to this file is necessary for interacting with
 * the stubbed global functions below.
 */
$mt_rand_stub_return_value = null;

/**
 * Stub of global built in function mt_rand()
 *
 * Useful for testing sampling with deterministic values.
 *
 * @return int|null
 */
function mt_rand()
{
    global $mt_rand_stub_return_value;

    if (is_null($mt_rand_stub_return_value)) {
        return \mt_rand();
    }

    return $mt_rand_stub_return_value;
}

/**
 * Making this variable global to this file is necessary for interacting with
 * the stubbed global functions below.
 */
$mt_getrandmax_stub_return_value = null;

/**
 * Stub of global built in function mt_getrandmax()
 *
 * Useful for testing sampling with deterministic values.
 *
 * @return int|null
 */
function mt_getrandmax()
{
    global $mt_getrandmax_stub_return_value;

    if (is_null($mt_getrandmax_stub_return_value)) {
        return \mt_getrandmax();
    }

    return $mt_getrandmax_stub_return_value;
}
