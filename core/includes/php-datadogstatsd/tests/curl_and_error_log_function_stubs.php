<?php

/**
 * Stubbed versions of curl functions, and error_log
 *
 * Useful for testing DataDog\DogStatsd::event without actually sending HTTP
 * request, and without invoking the real error_log function.
 *
 * @see \DataDog\TestHelpers\CurlSpy
 * @see \DataDog\TestHelpers\CurlSpyTestCase
 */

namespace DataDog;

/**
 * Stub of built in global PHP function curl_init
 *
 * @param string|null $url
 * @return resource
 */
function curl_init($url = null)
{
    global $curlSpy;

    $curlSpy->curlInitWasCalledWithArg($url);

    $resource = fopen('/dev/null', 'r');

    $curlSpy->curlInitDidReturn($resource);

    return $resource;
}

/**
 * Stub of built in global PHP function curl_setopt
 *
 * @param resource $ch
 * @param int $option
 * @param mixed $value
 */
function curl_setopt($ch, $option, $value)
{
    global $curlSpy;

    $curlSpy->curlSetoptWasCalledWithArgs($ch, $option, $value);
}

/**
 * Stub of built in global PHP function curl_exec
 *
 * @param resource $ch
 * @return mixed
 */
function curl_exec($ch)
{
    global $curlSpy;

    $curlSpy->curlExecCalledWithArg($ch);

    return $curlSpy->responseBody;
}

/**
 * Stub of built in global PHP function curl_getinfo
 *
 * @param resource $ch
 * @param int $opt
 * @return int
 */
function curl_getinfo($ch, $opt)
{
    global $curlSpy;

    $curlSpy->curlGetinfoCalledWithArgs($ch, $opt);

    return $curlSpy->responseCode;
}

/**
 * Stub of built in global PHP function curl_errno
 *
 * @param resource $ch
 * @return int
 */
function curl_errno($ch)
{
    global $curlSpy;

    $curlSpy->curlErrnoCalledWithArg($ch);

    return $curlSpy->errorNumber;
}

/**
 * Stub of built in global PHP function curl_close
 *
 * @param resource $ch
 */
function curl_close($ch)
{
    global $curlSpy;

    $curlSpy->curlCloseCalledWithArg($ch);
}

/**
 * Stub of built in global PHP function curl_error
 *
 * @param resource $ch
 * @return string
 */
function curl_error($ch)
{
    global $curlSpy;

    $curlSpy->curlErrorCalledWithArg($ch);

    return $curlSpy->curlError;
}

/**
 * @param string $message
 */
function error_log($message)
{
    global $curlSpy;

    $curlSpy->errorLogCallsWithArg($message);
}