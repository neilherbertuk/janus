<?php
require('vendor/autoload.php');

use Dotenv\Exception\InvalidPathException;
use GuzzleHttp\Client;
use Illuminate\Database\Capsule\Manager as Capsule;
use \App\Models\Webhook as DBWebhook;
use PAGI\Application\PAGIApplication;
use PAGI\Client\ChannelStatus;

declare(ticks=1);

date_default_timezone_set("Europe/London");

class Webhook extends PAGIApplication
{

    use \App\Migrations, \App\Setup, \App\PAGI, \App\TTS;

    protected $agi;

    protected $capsule;

    protected $CALL;

    protected $hangupUrl;

    /**
     * Setup Webhook Script
     *
     * @throws \PAGI\Exception\ChannelDownException
     */
    public function init()
    {
        set_time_limit(0);

        $this->log('Asterisk Webhooks AGI Init');

        $this->setCWD();

        $this->loadEnvironmentVariables();

        $this->loadEloquentORM();

        $this->seedDatabaseTables();

        $this->storeCallDetails();

        $this->log('Caller ID: ' . $this->CALL['FROM']);
        $this->log('Destination Number: ' . $this->CALL['DNID']);
        $this->log('Unique ID: ' . $this->CALL['UID']);
        $this->log('Call Start Time: '. $this->CALL['START_TIME']);

        $client = $this->getAgi();

        $client->answer();
    }

    /**
     * @throws \PAGI\Exception\ChannelDownException
     */
    protected function storeCallDetails()
    {

        $client = $this->getAgi();
        $variables = $client->getChannelVariables();

        // Get and store call details
        $this->CALL = [
            'UID' => $variables->getUniqueId(),                                 // Asterisk's Unique ID for the call
            'FROM' => $variables->getCallerId(),                                // Phone number of caller
            'DNID' => $variables->getDNID(),                                    // Dialed phone number
            'STATUS' => ChannelStatus::toString($client->channelStatus()),      // Call Status
            'START_TIME' => date('Y-m-d h:i:sa'),
            'END_TIME' => null
        ];
    }

    /**
     * @throws \PAGI\Exception\ChannelDownException
     */
    public function run()
    {
        try {

            $this->log('Run');
            $client = $this->getAgi();

            // Find webhook for dialed number
            if (empty($webhook = DBWebhook::where('dnid', '=', $this->CALL['DNID'])->first())) {
                throw new \Exception('Destination Number Not Configured ' . $this->CALL['DNID']);
            }
            $this->performWebhook(env('WEBHOOK', '')); // Currently overwriting this from env for development purposes

            // Hang Up Call
            $client->hangup();
        } catch (\Exception $exception) {
            $this->log('Error: ' . $exception->getMessage());
            $this->talk('Sorry, an error has occurred. Please hang up and try your call again.');
            $this->log('Hanging up call');
            $client->hangup();
            return;
        }
    }

    /**
     * @param $webhook
     * @param $client
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    protected function performWebhook($webhook, $method = 'POST', $data = [])
    {

        $method = strtoupper(trim($method));

        $webhook = trim($webhook);

        $this->log('Webhook URL: ' . $webhook);
        $this->log('Webhook Method: ' . $method);
        $this->log('Webhook Data: ' . implode_recur(', ', $data));
        $client = $this->getAgi();

        // Perform webhook
        $httpclient = new Client();

        $data['UID'] = $this->CALL['UID'];
        $data['FROM'] = $this->CALL['FROM'];

        try{
            $data['STATUS'] = ChannelStatus::toString($client->channelStatus());
        } catch (\Exception $exception) {
            if ($exception instanceof \PAGI\Exception\ChannelDownException)
            $data['STATUS'] = 'Call Terminated';
        }

        $data['START_TIME'] = $this->CALL['START_TIME'];
        $data['END_TIME'] = $this->CALL['END_TIME'];
        $data['DNID'] = $this->CALL['DNID'];

        $res = $httpclient->request($method, $webhook, [
            'headers' => [
                'User-Agent' => 'KEELEPBX/1.0',
                'Accept' => 'application/json',
            ],
            'json' => $data,
        ]);

        $callObject = json_decode($res->getBody());

        if ($callObject === null or $res->getStatusCode() != 200) {
            throw new \Exception('Webhook response not valid');
        }

        foreach ($callObject as $step) {

            // Look for hangupUrl
            if(!empty($step->hangupUrl)) {
                $this->hangupUrl = $step->hangupUrl;
                $this->log('Hangup URL set: '. $this->hangupUrl);
            }

            $step->action = strtolower($step->action); // Convert action verb to lower case

            $this->log('Action: ' . $step->action);

            if ($step->action == 'talk') {
                if (empty($step->voice))
                    $step->voice = 'Brian';

                if (empty($step->type))
                    $step->type = 'text';

                if (empty($step->service))
                    $step->service = 'Amazon';

                if (empty($step->force))
                    $step->force = false;

                if (empty($step->spell))
                    $step->spell = false;

                if ($step->spell) {
                    $step->text = str_split($step->text);
                } else {
                    $step->text = array($step->text);
                }

                if (empty($step->bargeIn))
                    $step->bargeIn = false;

                foreach ($step->text as $text) {
                    $this->talk($text, $step->voice, $step->type, $step->service, $step->force, $step->bargeIn);
                }

                $this->performAdditionalWebhook($step);

                continue;
            }

            if ($step->action == 'wait') {

                if (!empty($step->length) and is_int(intval($step->length))) {
                    for ($i = 0; $i < ceil(intval($step->length)); $i++) {
                        $this->log('Streaming silence');
                        $client->streamFile('silence/1');
                    }
                } else {
                    $this->log('Streaming silence');
                    $client->streamFile('silence/1');
                }

                $this->performAdditionalWebhook($step);

                continue;
            }

            if ($step->action == 'input') {

                if (empty($step->eventUrl))
                    $step->eventUrl = $webhook;

                if (empty($step->eventMethod))
                    $step->eventMethod = 'POST';

                if (!empty($step->timeout)) {
                    if ($step->timeout < 1 or $step->timeout > 10) {
                        $step->timeout = 3000;
                    } else {
                        $step->timeout = $step->timeout * 1000;
                    }
                } else {
                    $step->timeout = 3000;
                }

                if (!empty($step->maxDigits)) {
                    if ($step->maxDigits < 1 or $step->maxDigits > 20) {
                        $step->maxDigits = 4;
                    }
                }

                if (empty($step->maxDigits))
                    $step->maxDigits = 4;

                if (!empty($step->submitOnTimeout))
                    $step->submitOnTimeout = boolval($step->submitOnTimeout);

                if (empty($step->submitOnTimeout))
                    $step->submitOnTimeout = false;

                $result = $client->getData('silence/1', $step->timeout, $step->maxDigits);
                if (!$result->isTimeout()) {
                    $this->log('Read: ' . $result->getDigits());
                    $this->performWebhook($step->eventUrl, $step->eventMethod, ['DTMF' => $result->getDigits(), 'TIMED_OUT' => false]);
                    break;
                } else {
                    $this->log('Timeouted for get data with: ' . $result->getDigits());
                    if ($step->submitOnTimeout) {
                        $this->performWebhook($step->eventUrl, $step->eventMethod, ['DTMF' => $result->getDigits(), 'TIMED_OUT' => true]);
                        break;
                    }
                }

                continue;
            }

            if ($step->action == 'hangup') {

                $client->hangup();

                break;
            }

            $this->log('Action Unknown');

        }
    }

    /**
     * @param $step
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    protected function performAdditionalWebhook($step)
    {
        if (!empty($step->eventUrl)) {
            if (empty($step->eventMethod))
                $step->eventMethod = 'POST';

            $this->performWebhook($step->eventUrl, $step->eventMethod);
        }
    }
}