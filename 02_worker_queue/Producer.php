<?php
namespace Rmq02\Exchange;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'rmq02:producer')]
class Producer extends Command
{
    protected function configure(): void
    {
        $this->setDescription('02. producer 產生 "任務" 推入 rmq(暫無使用到 exchange)')
            ->addArgument('msg_str', InputArgument::REQUIRED, '傳入所需字串，傳遞後到消費者時，會根據裡面有幾個 . 去做 sleep 來模擬耗時任務。');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $msgStr = $input->getArgument('msg_str');
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


        $unqId = uniqid();
        $sleepCount = substr_count($msgStr, '.');

        $msgStr= "<error>($unqId)</error> ". $msgStr;
        $output->writeln("<comment> [*] 產生新消息: $msgStr 等待時間: $sleepCount 秒</comment>");

        $msg = new AMQPMessage(
            body: $msgStr,
            properties: ['delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT]
        );
        $channel->basic_publish(
            msg: $msg,
            exchange: '',
            routing_key: 'hello'
        );
        return Command::SUCCESS;
    }
}