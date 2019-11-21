<?php

namespace DataDog\TestHelpers;

/**
 * Class CurlSpy
 *
 * Useful for recording calls to stubbed global curl functions built into PHP
 *
 * @see \DataDog\CurlSpyTestCase
 * @package DataDog
 */
class CurlSpy
{
    /**
     * @var array
     */
    public $argsFromCurlInitCalls = array();

    /**
     * @var array
     */
    public $curlInitReturnValues = array();

    /**
     * @var array
     */
    public $argsFromCurlSetoptCalls = array();

    /**
     * @var array
     */
    public $argsFromCurlExecCalls = array();

    /**
     * @var string
     */
    public $responseBody = '';

    /**
     * @var array
     */
    public $argsFromCurlGetinfoCalls = array();

    /**
     * @var int
     */
    public $responseCode;

    /**
     * @var array
     */
    public $argsFromCurlErrnoCalls = array();

    /**
     * @var int
     */
    public $errorNumber;

    /**
     * @var array
     */
    public $argsFromCurlCloseCalls = array();

    /**
     * @var array
     */
    public $argsFromErrorLogCalls = array();

    /**
     * @param string $url
     */
    public function curlInitWasCalledWithArg($url)
    {
        $this->argsFromCurlInitCalls[] = $url;
    }

    /**
     * @param resource $ch
     */
    public function curlInitDidReturn($ch)
    {
        $this->curlInitReturnValues[] = $ch;
    }

    /**
     * @var array
     */
    public $argsFromCurlErrorCalls = array();

    /**
     * @var string
     */
    public $curlError = "";

    /**
     * @param resource $ch
     * @param int $option
     * @param mixed $value
     */
    public function curlSetoptWasCalledWithArgs(
        $ch,
        $option,
        $value
    ) {
        $this->argsFromCurlSetoptCalls[] = array(
            $ch,
            $option,
            $value,
        );
    }

    /**
     * @param resource $ch
     */
    public function curlExecCalledWithArg($ch)
    {
        $this->argsFromCurlExecCalls[] = $ch;
    }

    /**
     * @param resource $ch
     */
    public function curlExecDidReturn($ch)
    {
        $this->curlExecReturnValues[] = $ch;
    }

    /**
     * @param resource $ch
     * @param int $opt
     */
    public function curlGetinfoCalledWithArgs($ch, $opt)
    {
        $this->argsFromCurlGetinfoCalls[] = array($ch, $opt);
    }

    /**
     * @param resource $ch
     */
    public function curlErrnoCalledWithArg($ch)
    {
        $this->argsFromCurlErrnoCalls[] = $ch;
    }

    /**
     * @param resource $ch
     */
    public function curlCloseCalledWithArg($ch)
    {
        $this->argsFromCurlCloseCalls[] = $ch;
    }

    /**
     * @param resource $ch
     */
    public function curlErrorCalledWithArg($ch)
    {
        $this->argsFromCurlErrorCalls[] = $ch;
    }

    /**
     * @param string $message
     */
    public function errorLogCallsWithArg($message)
    {
        $this->argsFromErrorLogCalls[] = $message;
    }
}