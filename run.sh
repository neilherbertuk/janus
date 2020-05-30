#!/bin/bash
################################################################################
# Webhook application to run from dialplan.
################################################################################
me=$(dirname ${0})
root=${me}/
export root=`cd ${root}; pwd`

# This is the application that will be run.
export PAGIApplication=Webhook

# Make sure this is in the include path.
export PAGIBootstrap=Webhook.php

# PHP to run and options
php=/usr/bin/php
phpoptions="-c ${root}/resources/php.ini -d include_path=${root}/"

# Standard.. the idea is to have a common launcher.
launcher=${root}/vendor/marcelog/pagi/src/PAGI/Application/PAGILauncher.php

# Go!
${php} ${phpoptions} ${launcher}

