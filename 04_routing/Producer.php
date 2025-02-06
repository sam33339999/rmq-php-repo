<?php
namespace Rmq04\Exchange;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'rmq04:producer')]
class Producer extends Command
{
    const EXCHANGE_NAME = 'direct_logs';

    protected function configure(): void
    {
        $this->setDescription('04. producer 產生 "假log" 推入 rmq 等待消費者消化');
    }

    /**
     * 產生假的 log.
     * 
     * @return array [logLevel, logMessage]
     * ## logLevel: string
     *      - debug
     *      - info
     *      - critical
     *      - emergency
     * ## logMessage: string - log 訊息
     */
    protected function generateFakeLog(): array
    {
        $logLevel = ['debug', 'info', 'critical', 'emergency'];
        $logMessage = ['Hello World!', 'Hello PHP!', 'Hello Symfony!', 'Hello RabbitMQ!'];
        $logLevel = $logLevel[array_rand($logLevel)];
        $logMessage = $logMessage[array_rand($logMessage)];
        return [$logLevel, $logMessage];
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
            type: 'direct', // 交換機類型 allow: fanout|direct|topic|headers
            passive: false, // 被動，檢查Exchange是否存在, 不存在則報錯
            durable: false, // 耐用，是否持久化… 重啟後是否存在
            auto_delete: false // 自動刪除，當最後一個消費者取消訂閱時，Exchange是否自動刪除
        );

        $counter = 10000000; // 記數器，測試 100 條消息
        while ($counter > 0) {
            [$logLevel, $logMessage] = $this->generateFakeLog();
            $msgStr = uniqid() . '_' . $logMessage;

            $msg = new AMQPMessage($msgStr);
            $channel->basic_publish(
                msg: $msg,
                exchange: self::EXCHANGE_NAME,
                routing_key: $logLevel, // 這裡也要補上 routing_key
            );

            $symfonyOutput = match($logLevel) {
                'debug' => "<fg=green>[$logLevel] $msgStr",
                'info' => "<fg=blue>[$logLevel] $msgStr",
                'critical' => "<fg=red>[$logLevel] $msgStr",
                'emergency' => "<fg=red;bg=yellow>[$logLevel] $msgStr",
            };

            $output->writeln($symfonyOutput);
            usleep(100000);
            $counter--;
        }

        return Command::SUCCESS;
    }
}