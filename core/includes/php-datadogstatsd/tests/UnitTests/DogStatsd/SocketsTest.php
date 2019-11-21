<?php

namespace DataDog\UnitTests\DogStatsd;

use DateTime;
use DataDog\DogStatsd;
use DataDog\TestHelpers\SocketSpyTestCase;

class SocketsTest extends SocketSpyTestCase
{
    public function setUp()
    {
        parent::setUp();

        // Reset the stubs for mt_rand() and mt_getrandmax()
        global $mt_rand_stub_return_value;
        global $mt_getrandmax_stub_return_value;
        $mt_rand_stub_return_value = null;
        $mt_getrandmax_stub_return_value = null;
    }

    public function testTiming()
    {
        $stat = 'some.timing_metric';
        $time = 43;
        $sampleRate = 1.0;
        $tags = array('horse' => 'cart');
        $expectedUdpMessage = 'some.timing_metric:43|ms|#horse:cart';

        $dog = new DogStatsd(array());

        $dog->timing(
            $stat,
            $time,
            $sampleRate,
            $tags
        );

        $spy = $this->getSocketSpy();

        $this->assertSame(
            1,
            count($spy->argsFromSocketSendtoCalls),
            'Should send 1 UDP message'
        );

        $argsPassedToSocketSendTo = $spy->argsFromSocketSendtoCalls[0];

        $this->assertSame(
            $expectedUdpMessage,
            $argsPassedToSocketSendTo[1]
        );
    }

    public function testMicrotiming()
    {
        $stat = 'some.microtiming_metric';
        $time = 26;
        $sampleRate = 1.0;
        $tags = array('tuba' => 'solo');
        $expectedUdpMessage = 'some.microtiming_metric:26000|ms|#tuba:solo';

        $dog = new DogStatsd(array());

        $dog->microtiming(
            $stat,
            $time,
            $sampleRate,
            $tags
        );

        $spy = $this->getSocketSpy();

        $this->assertSame(
            1,
            count($spy->argsFromSocketSendtoCalls),
            'Should send 1 UDP message'
        );

        $argsPassedToSocketSendTo = $spy->argsFromSocketSendtoCalls[0];

        $this->assertSame(
            $expectedUdpMessage,
            $argsPassedToSocketSendTo[1]
        );
    }

    public function testGauge()
    {
        $stat = 'some.gauge_metric';
        $value = 5;
        $sampleRate = 1.0;
        $tags = array('baseball' => 'cap');
        $expectedUdpMessage = 'some.gauge_metric:5|g|#baseball:cap';

        $dog = new DogStatsd(array());

        $dog->gauge(
            $stat,
            $value,
            $sampleRate,
            $tags
        );

        $spy = $this->getSocketSpy();

        $this->assertSame(
            1,
            count($spy->argsFromSocketSendtoCalls),
            'Should send 1 UDP message'
        );

        $argsPassedToSocketSendTo = $spy->argsFromSocketSendtoCalls[0];

        $this->assertSame(
            $expectedUdpMessage,
            $argsPassedToSocketSendTo[1]
        );
    }

    public function testHistogram()
    {
        $stat = 'some.histogram_metric';
        $value = 109;
        $sampleRate = 1.0;
        $tags = array('happy' => 'days');
        $expectedUdpMessage = 'some.histogram_metric:109|h|#happy:days';

        $dog = new DogStatsd(array());

        $dog->histogram(
            $stat,
            $value,
            $sampleRate,
            $tags
        );

        $spy = $this->getSocketSpy();

        $this->assertSame(
            1,
            count($spy->argsFromSocketSendtoCalls),
            'Should send 1 UDP message'
        );

        $argsPassedToSocketSendTo = $spy->argsFromSocketSendtoCalls[0];

        $this->assertSame(
            $expectedUdpMessage,
            $argsPassedToSocketSendTo[1]
        );
    }

    public function testDistribution()
    {
        $stat = 'some.distribution_metric';
        $value = 7;
        $sampleRate = 1.0;
        $tags = array('floppy' => 'hat');
        $expectedUdpMessage = 'some.distribution_metric:7|d|#floppy:hat';

        $dog = new DogStatsd(array());

        $dog->distribution(
            $stat,
            $value,
            $sampleRate,
            $tags
        );

        $spy = $this->getSocketSpy();

        $this->assertSame(
            1,
            count($spy->argsFromSocketSendtoCalls),
            'Should send 1 UDP message'
        );

        $argsPassedToSocketSendTo = $spy->argsFromSocketSendtoCalls[0];

        $this->assertSame(
            $expectedUdpMessage,
            $argsPassedToSocketSendTo[1]
        );
    }

    public function testSet()
    {
        $stat = 'some.set_metric';
        $value = 22239;
        $sampleRate = 1.0;
        $tags = array('little' => 'bit');
        $expectedUdpMessage = 'some.set_metric:22239|s|#little:bit';

        $dog = new DogStatsd(array());

        $dog->set(
            $stat,
            $value,
            $sampleRate,
            $tags
        );

        $spy = $this->getSocketSpy();

        $this->assertSame(
            1,
            count($spy->argsFromSocketSendtoCalls),
            'Should send 1 UDP message'
        );

        $argsPassedToSocketSendTo = $spy->argsFromSocketSendtoCalls[0];

        $this->assertSame(
            $expectedUdpMessage,
            $argsPassedToSocketSendTo[1]
        );
    }

    /**
     * @dataProvider serviceCheckProvider
     * @param $name
     * @param $status
     * @param $tags
     * @param $hostname
     * @param $message
     * @param $timestamp
     * @param $expectedUdpMessage
     */
    public function testServiceCheck(
        $name,
        $status,
        $tags,
        $hostname,
        $message,
        $timestamp,
        $expectedUdpMessage
    ) {
        $dog = new DogStatsd(array());

        $dog->service_check(
            $name,
            $status,
            $tags,
            $hostname,
            $message,
            $timestamp
        );

        $spy = $this->getSocketSpy();

        $argsPassedToSocketSendto = $spy->argsFromSocketSendtoCalls[0];

        $this->assertSame(
            $expectedUdpMessage,
            $argsPassedToSocketSendto[1]
        );
    }

    public function serviceCheckProvider()
    {
        $name = 'neat-service';
        $status = DogStatsd::CRITICAL;
        $tags = array('red' => 'balloon', 'green' => 'ham');
        $hostname = 'some-host.com';
        $message = 'Important message';
        $timestamp = $this->getDeterministicTimestamp();

        return array(
            'all arguments provided' => array(
                $name,
                $status,
                $tags,
                $hostname,
                $message,
                $timestamp,
                '_sc|neat-service|2|d:1535776860|h:some-host.com|#red:balloon,green:ham|m:Important message',
            ),
            'without tags' => array(
                $name,
                $status,
                null,
                $hostname,
                $message,
                $timestamp,
                '_sc|neat-service|2|d:1535776860|h:some-host.com|m:Important message',
            ),
            'without hostname' => array(
                $name,
                $status,
                $tags,
                null,
                $message,
                $timestamp,
                '_sc|neat-service|2|d:1535776860|#red:balloon,green:ham|m:Important message',
            ),
            'without message' => array(
                $name,
                $status,
                $tags,
                $hostname,
                null,
                $timestamp,
                '_sc|neat-service|2|d:1535776860|h:some-host.com|#red:balloon,green:ham',
            ),
            'without timestamp' => array(
                $name,
                $status,
                $tags,
                $hostname,
                $message,
                null,
                '_sc|neat-service|2|h:some-host.com|#red:balloon,green:ham|m:Important message',
            ),
        );
    }

    public function testSend()
    {
        $data = array(
            'foo.metric' => '893|s',
            'bar.metric' => '4|s'
        );
        $sampleRate = 1.0;
        $tags = array(
            'cowboy' => 'hat'
        );

        $expectedUdpMessage1 = 'foo.metric:893|s|#cowboy:hat';
        $expectedUdpMessage2 = 'bar.metric:4|s|#cowboy:hat';

        $dog = new DogStatsd(array());

        $dog->send($data, $sampleRate, $tags);

        $spy = $this->getSocketSpy();

        $argsPassedToSocketSendtoCall1 = $spy->argsFromSocketSendtoCalls[0];
        $argsPassedToSocketSendtoCall2 = $spy->argsFromSocketSendtoCalls[1];

        $this->assertSame(
            2,
            count($spy->argsFromSocketSendtoCalls),
            'Should send 2 UDP messages'
        );

        $this->assertSame(
            $expectedUdpMessage1,
            $argsPassedToSocketSendtoCall1[1],
            'First UDP message should be correct'
        );

        $this->assertSame(
            $expectedUdpMessage2,
            $argsPassedToSocketSendtoCall2[1],
            'Second UDP message should be correct'
        );
    }

    public function testSendSerializesTagAsString()
    {
        $data = array(
            'foo.metric' => '82|s',
        );
        $sampleRate = 1.0;
        $tag = 'string:tag';

        $expectedUdpMessage = 'foo.metric:82|s|#string:tag';

        $dog = new DogStatsd(array());

        $dog->send($data, $sampleRate, $tag);

        $spy = $this->getSocketSpy();

        $this->assertSame(
            $expectedUdpMessage,
            $spy->argsFromSocketSendtoCalls[0][1],
            'Should serialize tag passed as string'
        );
    }

    public function testSendSerializesMessageWithoutTags()
    {
        $data = array(
            'foo.metric' => '19872|h',
        );
        $sampleRate = 1.0;
        $tag = null;

        $expectedUdpMessage = 'foo.metric:19872|h';

        $dog = new DogStatsd(array());

        $dog->send($data, $sampleRate, $tag);

        $spy = $this->getSocketSpy();

        $this->assertSame(
            $expectedUdpMessage,
            $spy->argsFromSocketSendtoCalls[0][1],
            'Should serialize message when no tags are provided'
        );
    }

    public function testSendReturnsEarlyWhenPassedEmptyData()
    {
        $dog = new DogStatsd(array());

        $dog->send(array());

        $spy = $this->getSocketSpy();

        $this->assertSame(
            0,
            count($spy->argsFromSocketSendtoCalls),
            'Should not send UDP message when event data is empty'
        );
    }

    public function testSendSendsWhenRandCalculationLessThanSampleRate()
    {
        global $mt_rand_stub_return_value;
        global $mt_getrandmax_stub_return_value;

        // 0.333 will be less than the sample rate, 0.5
        $mt_rand_stub_return_value = 1;
        $mt_getrandmax_stub_return_value = 3;

        $data = array(
            'foo.metric' => '469|s'
        );
        $sampleRate = 0.5;

        $dog = new DogStatsd(array());

        $dog->send($data, $sampleRate);

        $spy = $this->getSocketSpy();

        $this->assertSame(
            1,
            count($spy->argsFromSocketSendtoCalls),
            'Should send 1 UDP message'
        );
    }

    public function testSendSendsWhenRandCalculationEqualToSampleRate()
    {
        global $mt_rand_stub_return_value;
        global $mt_getrandmax_stub_return_value;

        // 1/2 will be equal to the sample rate, 0.5
        $mt_rand_stub_return_value = 1;
        $mt_getrandmax_stub_return_value = 2;

        $data = array(
            'foo.metric' => '23|g'
        );
        $sampleRate = 0.5;

        $dog = new DogStatsd(array());

        $dog->send($data, $sampleRate);

        $spy = $this->getSocketSpy();

        $this->assertSame(
            1,
            count($spy->argsFromSocketSendtoCalls),
            'Should send 1 UDP message'
        );
    }

    public function testSendDoesNotSendWhenRandCalculationGreaterThanSampleRate()
    {
        global $mt_rand_stub_return_value;
        global $mt_getrandmax_stub_return_value;

        // 1/1 will be greater than the sample rate, 0.5
        $mt_rand_stub_return_value = 1;
        $mt_getrandmax_stub_return_value = 1;

        $data = array(
            'foo.metric' => '23|g'
        );
        $sampleRate = 0.5;

        $dog = new DogStatsd(array());

        $dog->send($data, $sampleRate);

        $spy = $this->getSocketSpy();

        $this->assertSame(
            0,
            count($spy->argsFromSocketSendtoCalls),
            'Should not send a UDP message'
        );
    }

    public function testIncrement()
    {
        $stats = array(
            'foo.metric',
        );

        $expectedUdpMessage = 'foo.metric:1|c';

        $dog = new DogStatsd(array());

        $dog->increment($stats);

        $spy = $this->getSocketSpy();

        $this->assertSame(
            1,
            count($spy->argsFromSocketSendtoCalls),
            'Should send 1 UDP message'
        );

        $argsPassedToSocketSendto = $spy->argsFromSocketSendtoCalls[0];

        $this->assertSame(
            $expectedUdpMessage,
            $argsPassedToSocketSendto[1],
            'Should send the expected message'
        );
    }

    public function testDecrement()
    {
        $stats = array(
            'foo.metric',
        );

        $expectedUdpMessage = 'foo.metric:-1|c';

        $dog = new DogStatsd(array());

        $dog->decrement($stats);

        $spy = $this->getSocketSpy();

        $this->assertSame(
            1,
            count($spy->argsFromSocketSendtoCalls),
            'Should send 1 UDP message'
        );

        $argsPassedToSocketSendto = $spy->argsFromSocketSendtoCalls[0];

        $this->assertSame(
            $expectedUdpMessage,
            $argsPassedToSocketSendto[1],
            'Should send the expected message'
        );
    }

    public function testDecrementWithValueGreaterThanOne()
    {
        $stats = array(
            'foo.metric',
        );

        $expectedUdpMessage = 'foo.metric:-9|c';

        $dog = new DogStatsd(array());

        $dog->decrement($stats, 1.0, null, 9);

        $spy = $this->getSocketSpy();

        $this->assertSame(
            1,
            count($spy->argsFromSocketSendtoCalls),
            'Should send 1 UDP message'
        );

        $argsPassedToSocketSendto = $spy->argsFromSocketSendtoCalls[0];

        $this->assertSame(
            $expectedUdpMessage,
            $argsPassedToSocketSendto[1],
            'Should send the expected message'
        );
    }

    public function testDecrementWithValueLessThanOne()
    {
        $stats = array(
            'foo.metric',
        );

        $expectedUdpMessage = 'foo.metric:-47|c';

        $dog = new DogStatsd(array());

        $dog->decrement($stats, 1.0, null, -47);

        $spy = $this->getSocketSpy();

        $this->assertSame(
            1,
            count($spy->argsFromSocketSendtoCalls),
            'Should send 1 UDP message'
        );

        $argsPassedToSocketSendto = $spy->argsFromSocketSendtoCalls[0];

        $this->assertSame(
            $expectedUdpMessage,
            $argsPassedToSocketSendto[1],
            'Should send the expected message'
        );
    }

    public function testUpdateStats()
    {
        $stats = array(
            'foo.metric',
            'bar.metric',
        );
        $delta = 3;
        $sampleRate = 1.0;
        $tags = array(
            'every' => 'day',
        );

        $expectedUdpMessage1 = 'foo.metric:3|c|#every:day';
        $expectedUdpMessage2 = 'bar.metric:3|c|#every:day';

        $dog = new DogStatsd(array());

        $dog->updateStats($stats, $delta, $sampleRate, $tags);

        $spy = $this->getSocketSpy();

        $argsPassedToSocketSendto = $spy->argsFromSocketSendtoCalls;

        $this->assertSame(
            2,
            count($argsPassedToSocketSendto),
            'Should send 2 UDP messages'
        );

        $this->assertSame(
            $expectedUdpMessage1,
            $argsPassedToSocketSendto[0][1],
            'Should send the expected message for the first call'
        );

        $this->assertSame(
            $expectedUdpMessage2,
            $argsPassedToSocketSendto[1][1],
            'Should send the expected message for the first call'
        );
    }

    public function testUpdateStatsWithStringMetric()
    {
        $stats = 'foo.metric';
        $delta = -45;
        $sampleRate = 1.0;
        $tags = array(
            'long' => 'walk',
        );

        $expectedUdpMessage = 'foo.metric:-45|c|#long:walk';

        $dog = new DogStatsd(array());

        $dog->updateStats($stats, $delta, $sampleRate, $tags);

        $spy = $this->getSocketSpy();

        $this->assertSame(
            1,
            count($spy->argsFromSocketSendtoCalls),
            'Should send 1 UDP message'
        );

        $argsPassedToSocketSendto = $spy->argsFromSocketSendtoCalls[0];

        $this->assertSame(
            $expectedUdpMessage,
            $argsPassedToSocketSendto[1],
            'Should send the expected message'
        );
    }

    public function testReport()
    {
        $expectedUdpMessage = 'some fake UDP message';

        $dog = new DogStatsd(array());

        $dog->report($expectedUdpMessage);

        $spy = $this->getSocketSpy();

        $argsPassedToSocketSendto = $spy->argsFromSocketSendtoCalls[0];

        $this->assertSame(
            $expectedUdpMessage,
            $argsPassedToSocketSendto[1]
        );

        $this->assertSame(
            strlen($expectedUdpMessage),
            $argsPassedToSocketSendto[2]
        );
    }

    public function testFlushUdp()
    {
        $expectedUdpMessage = 'foo';

        $dog = new DogStatsd(array());

        $dog->flush($expectedUdpMessage);

        $spy = $this->getSocketSpy();

        $socketCreateReturnValue = $spy->socketCreateReturnValues[0];

        $this->assertCount(
            1,
            $spy->argsFromSocketCreateCalls,
            'Should call socket_create once'
        );

        $this->assertSame(
            array(AF_INET, SOCK_DGRAM, SOL_UDP),
            $spy->argsFromSocketCreateCalls[0],
            'Should create a UDP socket to send datagrams over IPv4'
        );

        $this->assertCount(
            1,
            $spy->argsFromSocketSetNonblockCalls,
            'Should call socket_set_nonblock once'
        );

        $this->assertSame(
            $socketCreateReturnValue,
            $spy->argsFromSocketSetNonblockCalls[0],
            'Should call socket_set_nonblock once with the socket previously created'
        );

        $this->assertCount(
            1,
            $spy->argsFromSocketSendtoCalls,
            'Should call socket_sendto once'
        );

        $this->assertSame(
            array(
                $socketCreateReturnValue,
                $expectedUdpMessage,
                strlen($expectedUdpMessage),
                0,
                'localhost',
                8125
            ),
            $spy->argsFromSocketSendtoCalls[0],
            'Should send the expected message to localhost:8125'
        );

        $this->assertCount(
            1,
            $spy->argsFromSocketCloseCalls,
            'Should call socket_close once'
        );

        $this->assertSame(
            $socketCreateReturnValue,
            $spy->socketCreateReturnValues[0],
            'Should close the socket previously created'
        );
    }

    public function testFlushUds()
    {
        $expectedUdsMessage = 'foo';
        $expectedUdsSocketPath = '/path/to/some.socket';

        $dog = new Dogstatsd(array("socket_path" => $expectedUdsSocketPath));

        $dog->flush($expectedUdsMessage);

        $spy = $this->getSocketSpy();

        $socketCreateReturnValue = $spy->socketCreateReturnValues[0];

        $this->assertCount(
            1,
            $spy->argsFromSocketCreateCalls,
            'Should call socket_create once'
        );

        $this->assertSame(
            array(AF_UNIX, SOCK_DGRAM, 0),
            $spy->argsFromSocketCreateCalls[0],
            'Should create a UDS socket to send datagrams over UDS'
        );

        $this->assertCount(
            1,
            $spy->argsFromSocketSetNonblockCalls,
            'Should call socket_set_nonblock once'
        );

        $this->assertSame(
            $socketCreateReturnValue,
            $spy->argsFromSocketSetNonblockCalls[0],
            'Should call socket_set_nonblock once with the socket previously created'
        );

        $this->assertCount(
            1,
            $spy->argsFromSocketSendtoCalls,
            'Should call socket_sendto once'
        );

        $this->assertSame(
            array(
                $socketCreateReturnValue,
                $expectedUdsMessage,
                strlen($expectedUdsMessage),
                0,
                $expectedUdsSocketPath,
                null
            ),
            $spy->argsFromSocketSendtoCalls[0],
            'Should send the expected message to /path/to/some.socket'
        );

        $this->assertCount(
            1,
            $spy->argsFromSocketCloseCalls,
            'Should call socket_close once'
        );

        $this->assertSame(
            $socketCreateReturnValue,
            $spy->socketCreateReturnValues[0],
            'Should close the socket previously created'
        );
    }

    public function testEventUdp()
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
                'chicken' => 'nachos',
            ),
        );

        $expectedUdpMessage = "_e{16,34}:Some event title|Some event text\\nthat spans 2 lines|d:1535776860|h:some.host.com|k:83e2cf|p:normal|s:jenkins|t:warning|#chicken:nachos";

        $dog = new DogStatsd(array());

        $dog->event($eventTitle, $eventVals);

        $spy = $this->getSocketSpy();

        $this->assertSame(
            1,
            count($spy->argsFromSocketSendtoCalls),
            'Should send 1 UDP message'
        );

        $argsPassedToSocketSendto = $spy->argsFromSocketSendtoCalls[0];

        $this->assertSame(
            $expectedUdpMessage,
            $argsPassedToSocketSendto[1]
        );
    }

    /**
     * @todo This test is technically correct, but it points out a flaw in
     *       the way events are handled. It is probably best to return early
     *       and avoid sending an empty event payload if no meaningful data
     *       is passed.
     */
    public function testEventUdpWithEmptyValues()
    {
        $eventTitle = '';

        $expectedUdpMessage = "_e{0,0}:|";

        $dog = new DogStatsd(array());

        $dog->event($eventTitle);

        $spy = $this->getSocketSpy();

        $this->assertSame(
            1,
            count($spy->argsFromSocketSendtoCalls),
            'Should send 1 UDP message'
        );

        $argsPassedToSocketSendto = $spy->argsFromSocketSendtoCalls[0];

        $this->assertSame(
            $expectedUdpMessage,
            $argsPassedToSocketSendto[1]
        );
    }

    public function testGlobalTags()
    {
        $dog = new DogStatsd(array(
            'global_tags' => array(
                'my_tag' => 'tag_value',
            ),
        ));
        $dog->timing('metric', 42, 1.0);
        $spy = $this->getSocketSpy();
        $this->assertSame(
            1,
            count($spy->argsFromSocketSendtoCalls),
            'Should send 1 UDP message'
        );
        $expectedUdpMessage = 'metric:42|ms|#my_tag:tag_value';
        $argsPassedToSocketSendTo = $spy->argsFromSocketSendtoCalls[0];

        $this->assertSame(
            $expectedUdpMessage,
            $argsPassedToSocketSendTo[1]
        );
    }

    public function testGlobalTagsAreSupplementedWithLocalTags()
    {
        $dog = new DogStatsd(array(
            'global_tags' => array(
                'my_tag' => 'tag_value',
            ),
        ));
        $dog->timing('metric', 42, 1.0, array('other_tag' => 'other_value'));
        $spy = $this->getSocketSpy();
        $this->assertSame(
            1,
            count($spy->argsFromSocketSendtoCalls),
            'Should send 1 UDP message'
        );
        $expectedUdpMessage = 'metric:42|ms|#my_tag:tag_value,other_tag:other_value';
        $argsPassedToSocketSendTo = $spy->argsFromSocketSendtoCalls[0];

        $this->assertSame(
            $expectedUdpMessage,
            $argsPassedToSocketSendTo[1]
        );
    }


    public function testGlobalTagsAreReplacedWithConflictingLocalTags()
    {
        $dog = new DogStatsd(array(
            'global_tags' => array(
                'my_tag' => 'tag_value',
            ),
        ));
        $dog->timing('metric', 42, 1.0, array('my_tag' => 'other_value'));
        $spy = $this->getSocketSpy();
        $this->assertSame(
            1,
            count($spy->argsFromSocketSendtoCalls),
            'Should send 1 UDP message'
        );
        $expectedUdpMessage = 'metric:42|ms|#my_tag:other_value';
        $argsPassedToSocketSendTo = $spy->argsFromSocketSendtoCalls[0];

        $this->assertSame(
            $expectedUdpMessage,
            $argsPassedToSocketSendTo[1]
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
}
