<?php
namespace Rmq03\Exchange;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'rmq03:worker')]
class worker extends Command
{
    const EXCHANGE_NAME = 'logs';

    protected function configure(): void
    {
        $this->setDescription('03. consumer 消化 rmq 內的資訊');
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

        [$queueName, , ] = $channel->queue_declare();
        $channel->queue_bind($queueName, 'logs');
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
                $output->writeln('<info> [x] 處理訊息: ' . $msg->body . '完成！</info>');
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