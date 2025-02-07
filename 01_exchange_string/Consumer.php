<?php
namespace Rmq01\Exchange;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'rmq01:consumer')]
class Consumer extends Command
{
    protected function configure(): void
    {
        $this->setDescription('01. consumer 消化 rmq 內的資訊(暫無使用到 exchange)');
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
        $channel->queue_declare(
            queue: 'hello', // 佇列名稱
            passive: false, // 被動，檢查Queue是否存在, 不存在則報錯
            durable: false, // 耐用，是否持久化… 重啟後是否存在
            exclusive: false, // 排他，當前連接使用後別的連接不能使用
            auto_delete: false, // 自動刪除，當最後一個消費者取消訂閱時，Queue是否自動刪除
        );
        $output->writeln('<comment> [*] 等待訊息推入. 想要離開的話請按 CTRL+C </comment>');
        $callback = fn ($msg) => $output->writeln('<info> [x] 得到訊息: ' . $msg->body . '</info>');

        // 設定消費者
        $channel->basic_consume(
            queue: 'hello', // 佇列名稱 QueueName
            consumer_tag: '', // 消費者標籤
            no_local: false, // 不要本地
            no_ack: true, // 不要確認，正式環境建議要手動確認，以確保訊息不會遺失
            exclusive: false, // 排他，當前連接使用後別的連接不能使用
            nowait: false, // 不等待
            callback: $callback, // callback
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