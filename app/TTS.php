<?php

namespace App;

trait TTS
{

    protected $converting = false;

    /**
     * Generate TTS and stream output to call
     *
     * @param string $text
     * @param string $voice
     * @param string $type
     * @param string $service
     * @param bool $force
     * @param bool $bargeIn
     * @throws \Exception
     */
    public function talk($text = '', $voice = 'Brian', $type = 'text', $service = 'Amazon', $force = false, $bargeIn = false)
    {

        // Is service specified
        if (empty($service))
            throw new \Exception('No TTS Service Provided');

        $service = ucfirst(escapeshellcmd($service));

        // Is voice provided
        if (empty($voice))
            throw new \Exception('No TTS Voice Provided');

        $voice = escapeshellcmd($voice);

        // Is text provided
        if (isset($text) && strlen($text) == 0)
            throw new \Exception('No Text Provided to TTS');

        // Is type provided
        if (empty($type))
            throw new \Exception('No Type Provided to TTS');

        $type = strtolower(escapeshellcmd($type));

        $soundsdir = base_path('resources/' . $service);// Directory to store generated TTS files in
        $extension = env('TTS_EXTENSION', 'gsm');// File Extension to save TTS as
        $format = env('TTS_FORMAT', 'gsm');// Output Codec to use with SOX
        $sampleRate = env('TTS_SAMPLERATE', '8000');// Output Sample Rate to save TTS to
        $filename = md5($voice . $type . $text);

        if (!file_exists($soundsdir . '/' . $filename . '.' . $extension) or $force) {

            $this->log('TTS: Not cached - generating');

            switch ($service) {
                case 'Amazon':
                    $this->log('TTS: Service - ' . $service);
                    if (!file_exists($soundsdir)) {
                        if (!mkdir($soundsdir, 0777, true))
                            throw new \Exception('Could not create resources/' . $service . ' folder');
                    }

                    if (!file_exists($soundsdir . '/raw/')) {
                        if (!mkdir($soundsdir . '/raw/', 0777, true))
                            throw new \Exception('Could not create resources/' . $service . '/raw/ folder');
                    }

                    $this->log('TTS: Creating TTS Service');
                    $adapter = new \AudioManager\Adapter\Polly();
                    $adapter->getOptions()->initialize()
                        ->setVersion('latest')
                        ->setRegion('eu-west-2')
                        ->setCredentials()
                        ->setKey(getenv('AWS_KEY', ''))
                        ->setSecret(getenv('AWS_SECRET', ''));
                    $adapter->getOptions()->setOutputFormat('mp3'); //Default 'mp3'
                    $adapter->getOptions()->setSampleRate('16000'); //Default '16000'
                    $adapter->getOptions()->setTextType($type); //Default 'text'
                    $adapter->getOptions()->setVoiceId($voice);
                    $this->log('TTS: Service Setup Complete');
                    break;
                default:
                    throw new \Exception('TTS: ' . $service . ' Not Found');
                    break;
            }

            $this->log('TTS: Generating TTS');

            // Get audio file
            $manager = new \AudioManager\Manager($adapter);
            $audioContent = $manager->read($text);
            $this->log('TTS: Getting Audio Content');
            $this->log(implode_recur('\r\n', $manager->getHeaders()));

            $file = file_put_contents($soundsdir . '/raw/' . $filename . '.mp3', $audioContent);
            $this->log('TTS: File Size - ' . $file);
            if (!is_int($file) or $file == 0 or empty($file)) {
                throw new \Exception('TTS: Unable to write raw audio file');
            }

            if (!chmod($soundsdir . '/raw/' . $filename . '.mp3', 0777))
                throw new \Exception('TTS: Unable to change permissions on raw file');

            // Convert mp3 to supported format of Asterisk
            $this->log('TTS: Converting MP3 to Supported Format');
            $this->converting = true;
            exec('/usr/bin/lame --decode ' . $soundsdir . '/raw/' . $filename . '.mp3 - | sox -t wav - -r ' . $sampleRate . ' -c 1 -e ' . $format . ' ' . $soundsdir . '/' . $filename . '.' . $extension, $output, $return_status);
            $this->converting = false;
            $this->log(implode_recur('\r\n', $output));
            $this->log($return_status);

            // Set permissions on converted file
            if (!chmod($soundsdir . '/' . $filename . '.' . $extension, 0777))
                throw new \Exception('TTS: Unable to change permission on converted TTS');

            $this->log('TTS: Deleting raw tts file');
            if (!unlink($soundsdir . '/raw/' . $filename . '.mp3'))
                throw new \Exception('TTS: Unable to delete raw mp3 file');

            $this->log('TTS: Generation Complete');
        } else {
            $this->log('TTS: File Already exists - using cache');
        }

        // Asterisk Gateway Interface
        // Push audio to phone call
        $client = $this->getAgi();

        $this->log('TTS: Streaming TTS to call');

        if ($bargeIn) {
            $bargeIn = "0123456789#";
        } else {
            $bargeIn = '""';
        }
        $client->streamFile($soundsdir . '/' . $filename, $bargeIn);
    }

}