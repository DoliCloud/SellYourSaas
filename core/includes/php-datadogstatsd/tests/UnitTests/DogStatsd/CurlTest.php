<?php

namespace DataDog\UnitTests\DogStatsd;

use DateTime;
use Exception;
use DataDog\DogStatsd;
use DataDog\TestHelpers\CurlSpyTestCase;

class CurlTest extends CurlSpyTestCase
{
    public function testEvent()
    {
        $eventTitle = 'Some event title';
        $eventVals = array(
            'text'             => "Some event text\nthat spans 2 lines",
            'date_happened'    => $this->getDeterministicTimestamp(),
            'hostname'         => 'some.host.com',
            'aggregation_key'  => '83e2cf',
            'priority'         => 'normal',
            'source_type_name' => 'jenkins',
            'alert_type'       => 'warning',
            'tags'             => array(
                0 => 'chicken:nachos',
            ),
        );

        $expectedVals = array_merge($eventVals, array(
            'title' => $eventTitle,
        ));

        $expectedVals['tags'][0] = '0:' . $eventVals['tags'][0];

        $dog = new DogStatsd($this->getDogStatsdConfigForSendingEventOverCurl());

        $spy = $this->getCurlSpy();

        $spy->errorNumber = null;
        $spy->responseCode = 200;
        $spy->responseBody = json_encode(array('status' => 'ok'));

        $eventSendResult = $dog->event($eventTitle, $eventVals);

        $this->assertSame(
            array(
                'https://app.datadoghq.com/api/v1/events?api_key=some-api-key&application_key=some-app-key'
            ),
            $spy->argsFromCurlInitCalls,
            'Should initialize curl with expected url'
        );

        $this->assertSame(
            7,
            count($spy->argsFromCurlSetoptCalls),
            'Should call curl_setopt 7 times'
        );

        /**
         * @todo While technically correct, this test asserts on buggy behavior.
         *       The constructor for \DataDog\DogStatsd references the wrong
         *       instance property when parsing the config array, so until that
         *       bug is fixed, CURL_SSL_VERIFYPEER will always be set to null.
         * @see  https://github.com/DataDog/php-datadogstatsd/pull/65
         */
        $this->assertSame(
            array(
                $spy->curlInitReturnValues[0],
                CURLOPT_SSL_VERIFYPEER,
                null,
            ),
            $spy->argsFromCurlSetoptCalls[0],
            'Should configure curl to verify SSL peer'
        );

        /**
         * @todo While technically correct, this test asserts on buggy behavior.
         *       The constructor for \DataDog\DogStatsd references the wrong
         *       instance property when parsing the config array, so until that
         *       bug is fixed, CURL_SSL_VERIFYHOST will always be set to null.
         * @see  https://github.com/DataDog/php-datadogstatsd/pull/65
         */
        $this->assertSame(
            array(
                $spy->curlInitReturnValues[0],
                CURLOPT_SSL_VERIFYHOST,
                null,
            ),
            $spy->argsFromCurlSetoptCalls[1],
            'Should configure curl to verify SSL host'
        );

        $this->assertSame(
            array(
                $spy->curlInitReturnValues[0],
                CURLOPT_RETURNTRANSFER,
                true,
            ),
            $spy->argsFromCurlSetoptCalls[2],
            'Should configure curl to return transfer'
        );

        $this->assertSame(
            array(
                $spy->curlInitReturnValues[0],
                CURLOPT_HTTPHEADER,
                array('Content-Type: application/json'),
            ),
            $spy->argsFromCurlSetoptCalls[3],
            'Should set Content-Type header to application/json'
        );

        $this->assertSame(
            array(
                $spy->curlInitReturnValues[0],
                CURLOPT_POST,
                1,
            ),
            $spy->argsFromCurlSetoptCalls[4],
            'Should send curl request as POST request'
        );

        $this->assertSame(
            array(
                $spy->curlInitReturnValues[0],
                CURLOPT_HEADER,
                0,
            ),
            $spy->argsFromCurlSetoptCalls[5],
            'Should not return the header values in the return data'
        );

        $this->assertSame(
            array(
                $spy->curlInitReturnValues[0],
                CURLOPT_POSTFIELDS,
                json_encode($expectedVals),
            ),
            $spy->argsFromCurlSetoptCalls[6],
            'Should send JSON-encoded event data as request body'
        );

        $this->assertSame(
            array($spy->curlInitReturnValues[0]),
            $spy->argsFromCurlExecCalls,
            'Should execute the curl resource'
        );

        $this->assertSame(
            array(
                array(
                    $spy->curlInitReturnValues[0],
                    CURLINFO_HTTP_CODE,
                ),
            ),
            $spy->argsFromCurlGetinfoCalls,
            'Should get the HTTP response code from the curl request'
        );

        $this->assertSame(
            1,
            count($spy->argsFromCurlErrnoCalls),
            'Should check for curl errors on success'
        );

        $this->assertSame(
            array($spy->curlInitReturnValues[0]),
            $spy->argsFromCurlCloseCalls,
            'Should close the curl request'
        );

        $this->assertTrue(
            $eventSendResult,
            'Should successfully send event data via curl'
        );
    }

    public function testEventFailsWhenCurlReturnsError()
    {
        $eventTitle = 'Some event title';
        $eventVals = array();

        $dog = new DogStatsd($this->getDogStatsdConfigForSendingEventOverCurl());

        $spy = $this->getCurlSpy();

        $spy->curlError = 'some curl error message';
        $spy->errorNumber = 1; // Any number should cause an exception to throw

        $eventSendSucceeded = $dog->event($eventTitle, $eventVals);

        $this->assertFalse($eventSendSucceeded);

        $this->assertSame(
            array(
                sprintf(
                    'Datadog event API call cURL issue #%s - %s',
                    $spy->errorNumber,
                    $spy->curlError
                ),
            ),
            $spy->argsFromErrorLogCalls
        );
    }

    public function testEventFailsWhenCurlReturnsNon2xxStatus()
    {
        $eventTitle = 'Some event title';
        $eventVals = array();

        $dog = new DogStatsd($this->getDogStatsdConfigForSendingEventOverCurl());

        $spy = $this->getCurlSpy();

        $spy->responseCode = 500;
        $spy->responseBody = json_encode(array(
            'some' => 'data',
        ));

        $eventSendSucceeded = $dog->event($eventTitle, $eventVals);

        $this->assertFalse($eventSendSucceeded);

        $this->assertSame(
            array(
                sprintf(
                    'Datadog event API call HTTP response not OK - %d; response body: %s',
                    $spy->responseCode,
                    $spy->responseBody
                ),
            ),
            $spy->argsFromErrorLogCalls
        );
    }

    public function testEventFailsWhenCurlReturnsNoResponseBody()
    {
        $eventTitle = 'Some event title';
        $eventVals = array();

        $dog = new DogStatsd($this->getDogStatsdConfigForSendingEventOverCurl());

        $spy = $this->getCurlSpy();

        $spy->responseCode = 200;

        $eventSendSucceeded = $dog->event($eventTitle, $eventVals);

        $this->assertFalse($eventSendSucceeded);

        $this->assertSame(
            array(
                'Datadog event API call did not return a body',
            ),
            $spy->argsFromErrorLogCalls
        );
    }

    public function testEventFailsWhenCurlReturnsInvalidJSONInResponseBody()
    {
        $eventTitle = 'Some event title';
        $eventVals = array();

        $dog = new DogStatsd($this->getDogStatsdConfigForSendingEventOverCurl());

        $spy = $this->getCurlSpy();

        $spy->responseCode = 200;
        $spy->responseBody = "{something:{} that isn't valid JSON";

        $eventSendSucceeded = $dog->event($eventTitle, $eventVals);

        $this->assertFalse($eventSendSucceeded);

        $this->assertSame(
            array(
                'Datadog event API call did not return a body that could be decoded via json_decode',
            ),
            $spy->argsFromErrorLogCalls
        );
    }

    /**
     * Get a timestamp created from a real date that is deterministic in nature
     *
     * @return int
     */
    private function getDeterministicTimestamp()
    {
        $dateTime = DateTime::createFromFormat(
            DateTime::ATOM,
            '2018-09-01T4:41:00Z'
        );

        return $dateTime->getTimestamp();
    }

    private function getDogStatsdConfigForSendingEventOverCurl()
    {
        return array(
            'api_key' => 'some-api-key',
            'app_key' => 'some-app-key',
        );
    }
}
