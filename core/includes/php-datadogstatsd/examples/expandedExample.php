<?php

require '../vendor/autoload.php';

use DataDog\DogStatsd;

// by setting the api_key you are switching from UDP to TCP for events
$statsd = new DogStatsd(
    array('api_key' => '046a22167c96272932f7f95753ceb81013e1fcac',
          'app_key' => '2167c96272932f013e1fcac7f95753ceb81046a2',
     )
  );

$runFor = 5; // Set to five minutes. Increase or decrease to have script run longer or shorter.
$scriptStartTime = time();

echo "Script starting.\n";

// Send metrics and events for 5 minutes.
while (time() < $scriptStartTime + ($runFor * 60)) {
    $startTime1 = microtime(true);
    $statsd->increment('web.page_views');
    $statsd->histogram('web.render_time', 15);
    $statsd->distribution('web.render_time', 15);
    $statsd->set('web.uniques', 3); // A unique user id

    runFunction($statsd);
    $statsd->timing('test.data.point', microtime(true) - $startTime1, 1, array('tagname' => 'php_example_tag_1'));

    sleep(1); // Sleep for one second
}

echo "Script has completed.\n";

function runFunction($statsd)
{
    $startTime = microtime(true);

    $testArray = array();
    for ($i = 0; $i < rand(1, 1000000000); $i++) {
        $testArray[$i] = $i;

        // Simulate an event at every 1000000th element
        if ($i % 1000000 == 0) {
            echo "Event simulated.\n";
            $statsd->event('A thing broke!', array(
                'alert_type'      => 'error',
                'aggregation_key' => 'test_aggr'
            ));
            $statsd->event('Now it is fixed.', array(
                'alert_type'      => 'success',
                'aggregation_key' => 'test_aggr'
            ));
        }
    }
    unset($testArray);
    $statsd->timing('test.data.point', microtime(true) - $startTime, 1, array('tagname' => 'php_example_tag_2'));
}
