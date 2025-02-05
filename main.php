<?php
require __DIR__.'/vendor/autoload.php';

// 01. 基本使用字符串交換 (Producer -> Consumer) 還沒使用到 exchange
use Rmq01\Exchange\Consumer as Rmq01ExchangeConsumer;
use Rmq01\Exchange\Producer as Rmq01ExchangeProducer;

// 02. 基本使用 worker queue (basic_qos ?) 還沒使用到 exchange
use Rmq02\Exchange\Producer as Rmq02ExchangeProducer;
use Rmq02\Exchange\Worker as Rmq02ExchangeWorker;

// 03. 使用 publish/subscribe (exchange)
use Rmq03\Exchange\Producer as Rmq03ExchangeProducer;
use Rmq03\Exchange\Worker as Rmq03ExchangeWorker;

use Symfony\Component\Console\Application;

$application = new Application();

$application->add(new Rmq01ExchangeConsumer());
$application->add(new Rmq01ExchangeProducer());

$application->add(new Rmq02ExchangeProducer());
$application->add(new Rmq02ExchangeWorker());

$application->add(new Rmq03ExchangeProducer());
$application->add(new Rmq03ExchangeWorker());


$application->run();
