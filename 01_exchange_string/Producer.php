<?php
namespace Rmq01\Exchange;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'rmq01:producer')]
class Producer extends Command
{
    protected function configure(): void
    {
        $this->setDescription('01. producer 產生 "Hello World" 推入 rmq');
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

        $msgStr = 'Hello World! date: ' . date('Y-m-d H:i:s', time());
        $output->writeln("<comment> [*] 產生新消息: $msgStr </comment>");
        $msg = new AMQPMessage($msgStr);
        $channel->basic_publish(
            msg: $msg,
            exchange: '',
            routing_key: 'hello'
        );
        return Command::SUCCESS;
    }
}