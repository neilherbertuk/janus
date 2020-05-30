<?php

namespace App;

use App\Models\Webhook as DBWebhook;

trait PAGI
{
    /**
     * Logs to asterisk console.
     *
     * @param string $msg Message to log.
     *
     * @return void
     */
    public function log($msg)
    {
        $agi = $this->getAgi();
        $this->logger->debug($msg);
        $agi->consoleLog($msg);
    }

    /**
     * (non-PHPdoc)
     * @see PAGI\Application.PAGIApplication::shutdown()
     */
    public function shutdown()
    {
        $this->CALL['END_TIME'] = date('Y-m-d h:i:sa');

        if (empty($webhook = DBWebhook::where('dnid', '=', $this->CALL['DNID'])->first())) {
            throw new \Exception('Destination Number Not Configured ' . $this->CALL['DNID']);
        }

        $this->performWebhook((!empty($this->hangupUrl) ? $this->hangupUrl : env('WEBHOOK', $webhook)));

        try {
            $this->log('Shutdown');
            $client = $this->getAgi();
            $client->hangup();
        } catch (\Exception $e) {
        }
    }

    /**
     * (non-PHPdoc)
     * @see PAGI\Application.PAGIApplication::errorHandler()
     */
    public function errorHandler($type, $message, $file, $line)
    {
        $this->log(
            'ErrorHandler: '
            . implode(' ', array($type, $message, $file, $line))
        );
    }

    /**
     * (non-PHPdoc)
     * @see PAGI\Application.PAGIApplication::signalHandler()
     */
    public function signalHandler($signal)
    {

        if (!$this->converting) {
            $this->log('SignalHandler got signal: ' . $signal);
            $this->talk('Sorry, an error has occurred. Please hang up and try your call again.');
            $this->log('Hanging up call');
            $this->shutdown();
            exit(0);
        }
    }
}