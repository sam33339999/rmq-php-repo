<?php
require __DIR__.'/vendor/autoload.php';

use Rmq01\Exchange\Consumer as Rmq01ExchangeConsumer;
use Rmq01\Exchange\Producer as Rmq01ExchangeProducer;
use Symfony\Component\Console\Application;

$application = new Application();

$application->add(new Rmq01ExchangeConsumer());
$application->add(new Rmq01ExchangeProducer());
$application->run();
