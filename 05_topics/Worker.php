<?php
namespace Rmq05\Exchange;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'rmq05:worker')]
class Worker extends Command
{
    const EXCHANGE_NAME = 'topic_logs';

    protected function configure(): void
    {
        $this->setDescription('05. worker(consumer) 消化 rmq 內的資訊(topics)')
            ->setHelp('這個指令會消化 RabbitMQ 內的訊息')
            ->addArgument('logLevel', InputArgument::IS_ARRAY, 'log 等級');
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

        [$queueName, , ] = $channel->queue_declare(); // 臨時queue，不需要名稱，RabbitMQ 會自動產生；通常斷掉連結後會會刪除 queue
        $listenLogLevels = implode(', ', $input->getArgument('logLevel'));
        $output->writeln("<comment> [*] 監聽 log 等級: $listenLogLevels</comment>");
        foreach ($input->getArgument('logLevel') as $logLevel) {
            $channel->queue_bind($queueName, self::EXCHANGE_NAME, $logLevel);
        }
        $output->writeln('<comment> [*] 等待訊息推入. 想要離開的話請按 CTRL+C </comment>');

        // 設定消費者
        $channel->basic_consume(
            queue: $queueName, // 佇列名稱 QueueName
            consumer_tag: '', // 消費者標籤
            no_local: false, // 不要本地
            no_ack: true, // 不要確認，正式環境建議要手動確認，以確保訊息不會遺失
            exclusive: false, // 排他，當前連接使用後別的連接不能使用
            nowait: false, // 不等待
            callback: function (AMQPMessage $msg) use (&$output) {
                $output->writeln('<comment> [x] routing key: ' . $msg->getRoutingKey() . '</comment>');
                $output->writeln('<info> [x] '. $msg->getRoutingKey() . '  處理訊息: ' . $msg->body . '完成！</info>');
            },
        );

        try {
            $channel->consume(); // 開始消費
        } catch (\Throwable $exception) {
            $output->writeln('<error>' . $exception->getMessage() . '</error>');
            return Command::FAILURE;
        } finally {
            $channel->close();
            $connection->close();
        }

        return Command::SUCCESS;
    }
}