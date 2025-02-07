<?php
namespace Rmq05\Exchange;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'rmq05:producer')]
class Producer extends Command
{
    const EXCHANGE_NAME = 'topic_logs';

    protected function configure(): void
    {
        $this->setDescription('05. producer 產生 "假log" 推入 rmq 等待消費者消化(topics)');
    }

    /**
     * 產生假的 log.
     * 
     * @return array [routing_key, logMessage]
     * ## routing_key: string -> 通常為 AAA.BBB.CCC
     *      - AAA: 危害等級: (debug|info|critical|emergency)
     *      - BBB: 哪一個系統 (crm|cart|official|product)
     *      - CCC: 哪一個功能 (payment|order|user)
     * ### 例如:
     *      - debug.crm.payment
     *      - info.cart.order
     *      - critical.official.user
     *      - ...
     * ## logMessage: string - log 訊息
     */
    protected function generateFakeLog(): array
    {
        $logLevel = ['debug', 'info', 'critical', 'emergency'];
        $system = ['crm', 'cart', 'official', 'product'];
        $features = ['payment', 'order', 'user'];
        $logMessage = ['Hello World!', 'Hello PHP!', 'Hello Symfony!', 'Hello RabbitMQ!'];
        $logLevel = $logLevel[array_rand($logLevel)];
        $sys = $system[array_rand($system)];
        $feat = $features[array_rand($features)];

        $routingKey = "$logLevel.$sys.$feat";
        $logMessage = $logMessage[array_rand($logMessage)];
        return [$routingKey, $logMessage];
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
            type: 'topic', // 交換機類型 allow: fanout|direct|topic|headers
            passive: false, // 被動，檢查Exchange是否存在, 不存在則報錯
            durable: false, // 耐用，是否持久化… 重啟後是否存在
            auto_delete: false // 自動刪除，當最後一個消費者取消訂閱時，Exchange是否自動刪除
        );

        $counter = 10000000; // 記數器，測試 10000000 條消息
        while ($counter > 0) {
            [$routingKey, $logMessage] = $this->generateFakeLog();
            $msgStr = uniqid() . '_' . $logMessage;

            $msg = new AMQPMessage($msgStr);
            $channel->basic_publish(
                msg: $msg,
                exchange: self::EXCHANGE_NAME,
                routing_key: $routingKey, // 這裡也要補上 routing_key
            );

            $symfonyOutput = match(explode('.', $routingKey)[0]) {
                'debug' => "<fg=green>[$routingKey] $msgStr",
                'info' => "<fg=blue>[$routingKey] $msgStr",
                'critical' => "<fg=red>[$routingKey] $msgStr",
                'emergency' => "<fg=red;bg=yellow>[$routingKey] $msgStr",
            };

            $symfonyOutput = match(explode('.', $routingKey)[0]) {
                'debug' => sprintf("<fg=green>%30s\t %-20s", $routingKey, $msgStr),
                'info' => sprintf("<fg=blue>%30s\t %-20s", $routingKey, $msgStr),
                'critical' => sprintf("<fg=red>%30s\t %-20s", $routingKey, $msgStr),
                'emergency' => sprintf("<fg=red;bg=yellow>%30s\t %-20s", $routingKey, $msgStr),
            };

            $output->writeln($symfonyOutput);
            usleep(100000);
            $counter--;
        }

        return Command::SUCCESS;
    }
}