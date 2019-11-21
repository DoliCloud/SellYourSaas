<?php

namespace DataDog;

/**
 * Datadog implementation of StatsD
 **/

class DogStatsd
{
    const OK        = 0;
    const WARNING   = 1;
    const CRITICAL  = 2;
    const UNKNOWN   = 3;

    /**
     * @var string
     */
    private $host;
    /**
     * @var int
     */
    private $port;
    /**
     * @var string
     */
    private $socketPath;
    /**
     * @var string
     */
    private $datadogHost;
    /**
     * @var string
     */
    private $apiKey;
    /**
     * @var string
     */
    private $appKey;
    /**
     * @var string Config for submitting events via 'TCP' vs 'UDP'; default 'UDP'
     */
    private $submitEventsOver = 'UDP';
    /**
     * @var int Config pass-through for CURLOPT_SSL_VERIFYHOST; defaults 2
     */
    private $curlVerifySslHost;
    /**
     * @var int Config pass-through for CURLOPT_SSL_VERIFYPEER; default 1
     */
    private $curlVerifySslPeer;
    /**
     * @var array Tags to apply to all metrics
     */
    private $globalTags;

    private static $__eventUrl = '/api/v1/events';

    /**
     * DogStatsd constructor, takes a configuration array. The configuration can take any of the following values:
     * host,
     * port,
     * datadog_host,
     * curl_ssl_verify_host,
     * curl_ssl_verify_peer,
     * api_key and app_key
     *
     * @param array $config
     */
    public function __construct(array $config = array())
    {
        $this->host = isset($config['host']) ? $config['host'] : 'localhost';
        $this->port = isset($config['port']) ? $config['port'] : 8125;
        $this->socketPath = isset($config['socket_path']) ? $config['socket_path'] : null;

        $this->datadogHost = isset($config['datadog_host']) ? $config['datadog_host'] : 'https://app.datadoghq.com';
        $this->apiCurlSslVerifyHost = isset($config['curl_ssl_verify_host']) ? $config['curl_ssl_verify_host'] : 2;
        $this->apiCurlSslVerifyPeer = isset($config['curl_ssl_verify_peer']) ? $config['curl_ssl_verify_peer'] : 1;

        $this->apiKey = isset($config['api_key']) ? $config['api_key'] : null;
        $this->appKey = isset($config['app_key']) ? $config['app_key'] : null;

        $this->globalTags = isset($config['global_tags']) ? $config['global_tags'] : array();

        if ($this->apiKey !== null) {
            $this->submitEventsOver = 'TCP';
        }
    }

    /**
     * Log timing information
     *
     * @param string $stat The metric to in log timing info for.
     * @param float $time The elapsed time (ms) to log
     * @param float $sampleRate the rate (0-1) for sampling.
     * @param array|string $tags Key Value array of Tag => Value, or single tag as string
     */
    public function timing($stat, $time, $sampleRate = 1.0, $tags = null)
    {
        $this->send(array($stat => "$time|ms"), $sampleRate, $tags);
    }

    /**
     * A convenient alias for the timing function when used with micro-timing
     *
     * @param string $stat The metric name
     * @param float $time The elapsed time to log, IN SECONDS
     * @param float $sampleRate the rate (0-1) for sampling.
     * @param array|string $tags Key Value array of Tag => Value, or single tag as string
     **/
    public function microtiming($stat, $time, $sampleRate = 1.0, $tags = null)
    {
        $this->timing($stat, $time*1000, $sampleRate, $tags);
    }

    /**
     * Gauge
     *
     * @param string $stat The metric
     * @param float $value The value
     * @param float $sampleRate the rate (0-1) for sampling.
     * @param array|string $tags Key Value array of Tag => Value, or single tag as string
     **/
    public function gauge($stat, $value, $sampleRate = 1.0, $tags = null)
    {
        $this->send(array($stat => "$value|g"), $sampleRate, $tags);
    }

    /**
     * Histogram
     *
     * @param string $stat The metric
     * @param float $value The value
     * @param float $sampleRate the rate (0-1) for sampling.
     * @param array|string $tags Key Value array of Tag => Value, or single tag as string
     **/
    public function histogram($stat, $value, $sampleRate = 1.0, $tags = null)
    {
        $this->send(array($stat => "$value|h"), $sampleRate, $tags);
    }

    /**
     * Distribution
     *
     * @param string $stat The metric
     * @param float $value The value
     * @param float $sampleRate the rate (0-1) for sampling.
     * @param array|string $tags Key Value array of Tag => Value, or single tag as string
     **/
    public function distribution($stat, $value, $sampleRate = 1.0, $tags = null)
    {
        $this->send(array($stat => "$value|d"), $sampleRate, $tags);
    }

    /**
     * Set
     *
     * @param string $stat The metric
     * @param float $value The value
     * @param float $sampleRate the rate (0-1) for sampling.
     * @param array|string $tags Key Value array of Tag => Value, or single tag as string
     **/
    public function set($stat, $value, $sampleRate = 1.0, $tags = null)
    {
        $this->send(array($stat => "$value|s"), $sampleRate, $tags);
    }


    /**
     * Increments one or more stats counters
     *
     * @param string|array $stats The metric(s) to increment.
     * @param float $sampleRate the rate (0-1) for sampling.
     * @param array|string $tags Key Value array of Tag => Value, or single tag as string
     * @param int $value the amount to increment by (default 1)
     * @return boolean
     **/
    public function increment($stats, $sampleRate = 1.0, $tags = null, $value = 1)
    {
        $this->updateStats($stats, $value, $sampleRate, $tags);
    }

    /**
     * Decrements one or more stats counters.
     *
     * @param string|array $stats The metric(s) to decrement.
     * @param float $sampleRate the rate (0-1) for sampling.
     * @param array|string $tags Key Value array of Tag => Value, or single tag as string
     * @param int $value the amount to decrement by (default -1)
     * @return boolean
     **/
    public function decrement($stats, $sampleRate = 1.0, $tags = null, $value = -1)
    {
        if ($value > 0) {
            $value = -$value;
        }
        $this->updateStats($stats, $value, $sampleRate, $tags);
    }

    /**
     * Updates one or more stats counters by arbitrary amounts.
     *
     * @param string|array $stats The metric(s) to update. Should be either a string or array of metrics.
     * @param int $delta The amount to increment/decrement each metric by.
     * @param float $sampleRate the rate (0-1) for sampling.
     * @param array|string $tags Key Value array of Tag => Value, or single tag as string
     *
     * @return boolean
     **/
    public function updateStats($stats, $delta = 1, $sampleRate = 1.0, $tags = null)
    {
        if (!is_array($stats)) {
            $stats = array($stats);
        }
        $data = array();
        foreach ($stats as $stat) {
            $data[$stat] = "$delta|c";
        }
        $this->send($data, $sampleRate, $tags);
    }

    /**
     * Serialize tags to StatsD protocol
     *
     * @param string|array $tags The tags to be serialize
     *
     * @return string
     **/
    private function serialize_tags($tags)
    {
        $all_tags = array_merge(
            $this->normalize_tags($this->globalTags),
            $this->normalize_tags($tags)
        );

        if (count($all_tags) === 0) {
            return '';
        }
        $tag_strings = array();
        foreach ($all_tags as $tag => $value) {
            if ($value === null) {
                $tag_strings[] = $tag;
            } else {
                $tag_strings[] = $tag . ':' . $value;
            }
        }
        return '|#' . implode(',', $tag_strings);
    }

    /**
     * Turns tags in any format into an array of tags
     *
     * @param mixed $tags The tags to normalize
     * @return array
     */
    private function normalize_tags($tags)
    {
        if ($tags === null) {
            return array();
        }
        if (is_array($tags)) {
            $data = array();
            foreach ($tags as $tag_key => $tag_val) {
                if (isset($tag_val)) {
                    $data[$tag_key] = $tag_val;
                } else {
                    $data[$tag_key] = null;
                }
            }
            return $data;
        } else {
            $tags = explode(',', $tags);
            $data = array();
            foreach ($tags as $tag_string) {
                if (false === strpos($tag_string, ':')) {
                    $data[$tag_string] = null;
                } else {
                    list($key, $value) = explode(':', $tag_string, 2);
                    $data[$key] = $value;
                }
            }
            return $data;
        }
    }

    /**
     * Squirt the metrics over UDP
     * @param array $data Incoming Data
     * @param float $sampleRate the rate (0-1) for sampling.
     * @param array|string $tags Key Value array of Tag => Value, or single tag as string
     *
     * @return null
     **/
    public function send($data, $sampleRate = 1.0, $tags = null)
    {
        // sampling
        $sampledData = array();
        if ($sampleRate < 1) {
            foreach ($data as $stat => $value) {
                if ((mt_rand() / mt_getrandmax()) <= $sampleRate) {
                    $sampledData[$stat] = "$value|@$sampleRate";
                }
            }
        } else {
            $sampledData = $data;
        }

        if (empty($sampledData)) {
            return;
        }

        foreach ($sampledData as $stat => $value) {
            $value .= $this->serialize_tags($tags);
            $this->report("$stat:$value");
        }
    }

    /**
     * Send a custom service check status over UDP
     * @param string $name service check name
     * @param int $status service check status code (see OK, WARNING,...)
     * @param array|string $tags Key Value array of Tag => Value, or single tag as string
     * @param string $hostname hostname to associate with this service check status
     * @param string $message message to associate with this service check status
     * @param int $timestamp timestamp for the service check status (defaults to now)
     *
     * @return null
     **/
    public function service_check(
        $name,
        $status,
        $tags = null,
        $hostname = null,
        $message = null,
        $timestamp = null
    ) {
        $msg = "_sc|$name|$status";

        if ($timestamp !== null) {
            $msg .= sprintf("|d:%s", $timestamp);
        }
        if ($hostname !== null) {
            $msg .= sprintf("|h:%s", $hostname);
        }
        $msg .= $this->serialize_tags($tags);
        if ($message !== null) {
            $msg .= sprintf('|m:%s', $this->escape_sc_message($message));
        }

        $this->report($msg);
    }

    private function escape_sc_message($msg)
    {
        return str_replace("m:", "m\:", str_replace("\n", "\\n", $msg));
    }

    public function report($udp_message)
    {
        $this->flush($udp_message);
    }

    public function flush($udp_message)
    {
        // Non - Blocking UDP I/O - Use IP Addresses!
        $socket = is_null($this->socketPath) ? socket_create(AF_INET, SOCK_DGRAM, SOL_UDP) : socket_create(AF_UNIX, SOCK_DGRAM, 0);
        socket_set_nonblock($socket);

        if (!is_null($this->socketPath)) {
            socket_sendto($socket, $udp_message, strlen($udp_message), 0, $this->socketPath);
        } else {
            socket_sendto($socket, $udp_message, strlen($udp_message), 0, $this->host, $this->port);
        }

        socket_close($socket);
    }

    /**
     * Send an event to the Datadog HTTP api. Potentially slow, so avoid
     * making many call in a row if you don't want to stall your app.
     * Requires PHP >= 5.3.0
     *
     * @param string $title Title of the event
     * @param array $vals Optional values of the event. See
     *   https://docs.datadoghq.com/api/?lang=bash#post-an-event for the valid keys
     * @return null
     **/
    public function event($title, $vals = array())
    {

        // Assemble the request
        $vals['title'] = $title;

        // If sending events via UDP
        if ($this->submitEventsOver === 'UDP') { # FIX
            return $this->eventUdp($vals);
        }

        // Convert tags string or array into array of tags: ie ['key:value']
        if (isset($vals['tags'])) {
            $vals['tags'] = explode(",", substr($this->serialize_tags($vals['tags']), 2));
        }

        /**
         * @var boolean Flag for returning success
         */
        $success = true;

        // Get the url to POST to
        $url = $this->datadogHost . self::$__eventUrl
             . '?api_key='          . $this->apiKey
             . '&application_key='  . $this->appKey;

        $curl = curl_init($url);

        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, $this->curlVerifySslPeer);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, $this->curlVerifySslHost);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($vals));

        // Nab response and HTTP code
        $response_body = curl_exec($curl);
        $response_code = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);

        try {

            // Check for cURL errors
            if ($curlErrorNum = curl_errno($curl)) {
                throw new \Exception('Datadog event API call cURL issue #' . $curlErrorNum . ' - ' . curl_error($curl));
            }

            // Check response code is 202
            if ($response_code !== 200 && $response_code !== 202) {
                throw new \Exception('Datadog event API call HTTP response not OK - ' . $response_code . '; response body: ' . $response_body);
            }

            // Check for empty response body
            if (!$response_body) {
                throw new \Exception('Datadog event API call did not return a body');
            }

            // Decode JSON response
            if (!$decodedJson = json_decode($response_body, true)) {
                throw new \Exception('Datadog event API call did not return a body that could be decoded via json_decode');
            }

            // Check JSON decoded "status" is OK from the Datadog API
            if ($decodedJson['status'] !== 'ok') {
                throw new \Exception('Datadog event API response  status not "ok"; response body: ' . $response_body);
            }
        } catch (\Exception $e) {
            $success = false;

            // Use error_log for API submission errors to avoid warnings/etc.
            error_log($e->getMessage());
        }

        curl_close($curl);
        return $success;
    }

    /**
     * Formats $vals array into event for submission to Datadog via UDP
     * @param array $vals Optional values of the event. See
     *   https://docs.datadoghq.com/api/?lang=bash#post-an-event for the valid keys
     * @return null
     */
    private function eventUdp($vals)
    {

        // Format required values title and text
        $title = isset($vals['title']) ? (string) $vals['title'] : '';
        $text = isset($vals['text']) ? (string) $vals['text'] : '';

        // Format fields into string that follows Datadog event submission via UDP standards
        //   http://docs.datadoghq.com/guides/dogstatsd/#events
        $fields = '';
        $fields .= ($title);
        $fields .= ($text) ? '|' . str_replace("\n", "\\n", $text) : '|';
        $fields .= (isset($vals['date_happened'])) ? '|d:' . ((string) $vals['date_happened']) : '';
        $fields .= (isset($vals['hostname'])) ? '|h:' . ((string) $vals['hostname']) : '';
        $fields .= (isset($vals['aggregation_key'])) ? '|k:' . ((string) $vals['aggregation_key']) : '';
        $fields .= (isset($vals['priority'])) ? '|p:' . ((string) $vals['priority']) : '';
        $fields .= (isset($vals['source_type_name'])) ? '|s:' . ((string) $vals['source_type_name']) : '';
        $fields .= (isset($vals['alert_type'])) ? '|t:' . ((string) $vals['alert_type']) : '';
        $fields .= (isset($vals['tags'])) ? $this->serialize_tags($vals['tags']) : '';

        $title_length = strlen($title);
        $text_length = strlen($text);

        $this->report('_e{' . $title_length . ',' . $text_length . '}:' . $fields);

        return null;
    }
}
