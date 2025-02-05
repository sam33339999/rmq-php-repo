<?php
namespace Rmq03\Exchange;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'rmq03:producer')]
class Producer extends Command
{
    const EXCHANGE_NAME = 'logs';

    protected function configure(): void
    {
        $this->setDescription('03. producer 產生 "假log" 推入 rmq 等待消費者消化');
    }

    protected function generateFakeLog(): string
    {
        $logLevel = ['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency'];
        $logMessage = ['Hello World!', 'Hello PHP!', 'Hello Symfony!', 'Hello RabbitMQ!'];
        $logLevel = $logLevel[array_rand($logLevel)];
        $logMessage = $logMessage[array_rand($logMessage)];
        return "[$logLevel] $logMessage";
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $connection = new AMQPStreamConnection(
            host: 'localhost',
            port: 5672,
            user: 'guest',
            password: 'guest'
        );

        $channel = $connection->channel();
        $channel->exchange_declare(
            exchange: self::EXCHANGE_NAME, // 交換機名稱
            type: 'fanout', // 交換機類型 allow: fanout|direct|topic|headers
            passive: false, // 被動，檢查Exchange是否存在, 不存在則報錯
            durable: false, // 耐用，是否持久化… 重啟後是否存在
            auto_delete: false // 自動刪除，當最後一個消費者取消訂閱時，Exchange是否自動刪除
        );
        
        while (true) {
            $msgStr = uniqid() . '_' . $this->generateFakeLog();
            $msg = new AMQPMessage(
                body: $msgStr,
                properties: ['delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT]
            );

            $channel->basic_publish(
                msg: $msg,
                exchange: self::EXCHANGE_NAME,
                // routing_key: 'hello'
            );

            $output->writeln("<info> [*] 產生新消息: $msgStr</info>");
            usleep(1000000);
        }
    }
}