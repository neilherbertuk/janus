<p align="center"><img src="https://svgshare.com/i/96g.svg"></p>

# Janus - Asterisk Webhooks AGI

An Asterisk AGI application that enables twilio/nexmo type features on your Asterisk install. Allows you to easily, expressively and dynamically handle inbound calls using any language you like on an external system, all you need is a webserver.

Tested on Asterisk 15 with FreePBX installed and PHP 5.6.

## Features
* Inbound Call Webhook 
* TTS - Amazon Polly
* TTS Caching

## How It Works?

1. You configure any inbound numbers or a catch all that you want to use with `Janus` within your extensions.conf

2. An inbound call is passed to `Janus`

3. `Janus` looks up the webhook address and performs a POST request which includes Caller ID and a Unique ID number for the call

4. Your system replies with json asking `Janus` to perform certain tasks with the call

5. `Janus` will process the json response and perform any actions required, passing any data back to your system if required

6. If no more instructions are provided, call is hung up

# Requirements
* Linux
* Asterisk 15
* Lame
* Sox
* PHP 5.6+
* Git
* Composer

# Getting Started

Clone the repo to your asterisk environment

```bash
$ git clone url /var/lib/asterisk/agi-bin/janus && cd /var/lib/asterisk/agi-bin/janus
```

Install PHP Dependencies with Composer

```bash
$ composer install
```

Setup Environment File
```bash
$ cp .env.example .env
```

Edit your .env file and add the appropriate AWS keys for Amazon Polly

Set file ownership and permissions
```bash
sudo chown -R asterisk:asterisk *
sudo chmod 777 *
```

Add the following macro to your dialplan in extensions.conf, or extensions_custom.conf if using FreePBX

```
;-------------------------------------------------------------------------------
; Janus:
;
[macro-Janus]
exten => s,1,Ringing; Make them comfortable with 2 seconds of ringback
  same => n,Wait(2)
  same => n,Answer()
  same => n,AGI(janus/run.sh)
  same => n,Hangup()
;-------------------------------------------------------------------------------
```

Then call the `Janus` macro from any number you wish to use Janus. The following will add an internal extension number "1000" which will be answered by Janus.

```
[from-internal]
; Ext: 1000 - Janus Example
exten => 1000,1,Macro(Janus); Redirect call to macro
  same => n,Hangup()
```

*I am aware that the macro application has been depreciated, just haven't gotten round to looking at the alternative* 

`Janus` currently uses a sqlite database to store the webhook configuration. There is currently no functionality within Janus to edit this.

Ensure your install has automatic NTP syncing turned on, if your server's time drifts, you may experience issues with Amazon Polly.

# Call Object - JSON Structure

You can use any language you like, just output valid JSON for `Janus` to perform the required actions.

## Actions

You can provide a `hangupUrl` field within any of the following actions to tell `Janus` where to contact when a call has ended.

### Talk
The talk action will use a text-to-speech service to generate an audio stream to send to the call. Currently this only supports Amazon Polly.

The talk action must be accompanied by a text field.

You can specify a voice, but if this is not provided it will default to "Brian" which is a UK Male.

#### Fields

`text`: string - The message to be read to the caller

`voice` (Optional): string - The voice to use while reading the text to the caller

`type` (Optional): enum 'text'(default), 'ssml' - Whether the text field provided is plain text or in SSML format

`service` (Optional): Amazon - Allows you to change the TTS service used. Currently only Amazon Polly is available.

`spell` (Optional): true, false (Default) - Allows you to specify that the input `text` field are to be said on a character by character basis

`eventUrl` (Optional): string - A url to call after executing call action

`eventMethod` (Optional): enum 'POST' (default), 'GET' - Which method to use to call the eventUrl

`bargeIn` (Optional): boolean true, false (Default) - Allow the user to skip or interrupt the current action by pressing any key 

#### Amazon Polly
A list of voices you can use with Amazon Polly can be found here - https://docs.aws.amazon.com/polly/latest/dg/voicelist.html

#### Examples

##### Hello World

In this example application, `Janus` will use TTS to generate the words "Hello World" using the Amazon Polly voice "Brian"

```json
  [
    {
      "action" : "talk",
      "text" : "Hello World",
      "voice" : "Brian"
    },
    {
      "action" : "talk",
      "text" : "This is an example Jan us application",
      "voice" : "Brian"
    }
  ]
```

##### SSML Example

SSML documentation can be found here https://developer.amazon.com/docs/custom-skills/speech-synthesis-markup-language-ssml-reference.html

```json
  [
    {
      "action" : "talk",
      "text" : "<speak><lang xml:lang='pt-BR'>Bom dia.</lang> <prosody rate='fast'>I can speak fast.</prosody> <lang xml:lang='fr'>Au revoir!</lang></speak>",
      "type" : "ssml",
      "voice" : "Brian"  
    }
  ],
```

### Wait
The wait action will cause the call to wait for x number of seconds before carrying onto the next action.

This is useful to add gaps between talk actions.

Unless provided with the `legnth` field, a wait action lasts for 1 second, but can be any number of whole seconds.

The wait is accomplished by playing the built in "silence/1" audio file.

#### Fields

`length` (Optional): int - The number of whole seconds you want to wait to last. This will automatically round up to the nearest second if you provide it with a non whole number

#### Example

##### Wait for 1 seconds
```json
  [
    {
      "action" : "wait"
    }
  ]
```

##### Wait for 4 seconds
```json
  [
    {
      "action" : "wait",
      "length" : "4"
    }
  ]
```

### Input
The input action will collect DTFM input from the caller and send it to the supplied `eventUrl`. This action allows the user to press the hash key to submit their input at any time, input will automatically be sent to the `eventUrl` if the legnth equals the `maxDigits` field.

#### Fields

`timeout` (Optional): int - The number of seconds to wait for input before timing out

`eventUrl`: string - A url to send the DTMF collected from the call

`eventMethod` (Optional): enum 'POST' (default), 'GET' - Which method to use to post to the eventUrl 

`maxDigits` (Optional): int 4 (default) - Maximum number of digits to be accepted - Minimum of 1 and Maximum of 20

`submitOnTimeout` (Optional): bool False (Default) - Whether to submit any input to the `eventUrl` if there is a timeout.

#### Example

##### Default - Wait for 4 digits
```json
  [
    {
      "action" : "input",
      "eventUrl" : "http://localhost:8000/submitinput"
    }
  ]
```

##### Wait for 15 digits
```json
  [
    {
      "action" : "input",
      "eventUrl" : "http://localhost:8000/submitinput",
      "maxDigits" : 15
    }
  ]
```

### Hangup
The hangup action will be used to terminate the call. This can be useful if you are no longer on the initial webhook page and need to end the call without the original CCO continuing to execute

#### Example
```json
  [
    {
      "action" : "hangup"
    }
  ]
```