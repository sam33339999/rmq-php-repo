<?php
namespace Rmq02\Exchange;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'rmq02:worker')]
class Worker extends Command
{
    protected function configure(): void
    {
        $this->setDescription('02. consumer 消化 rmq 內的資訊');
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

        // 設定 callback
        $callback = function (AMQPMessage $msg) use (&$output) {
            $output->writeln('<comment> [x] 得到訊息: ' . $msg->body . '</comment>');
            $sleepCount = substr_count($msg->body, '.');
            $output->writeln("<comment> [x] 模擬情境需要花時間, sleep ($sleepCount)</comment>");
            sleep($sleepCount);

            $output->writeln('<info> [x] 處理訊息: ' . $msg->body . '完成！</info>');
            $msg->ack();
        };

        // 單獨設定預先存取的數量 -> consumer prefetch -> 消費者預先取得？
        $channel->basic_qos(
            prefetch_size: 0, // 預先取得大小（0為不限制, 預設值）
            prefetch_count: 1, // 預先取得數量 (0為不限制, 預設值)
            a_global: false, // 全域，共享？ 設定為共享時，不會因為不同消費者而有不同的預先取得數量 (也就是說，設定共享後，設定多少就是多少，不會因為多開 worker 而增加)
        );
        // 設定消費者
        $channel->basic_consume(
            queue: 'hello', // 佇列名稱 QueueName
            consumer_tag: '', // 消費者標籤
            no_local: false, // 不要本地
            no_ack: false, // 不要確認，正式環境建議要手動確認，以確保訊息不會遺失
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