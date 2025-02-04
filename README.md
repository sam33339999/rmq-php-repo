# RabbitMQ (rmq-php-repo)

> 該 repo 使用 symfony 進行console 的整理項目，主要是為了練習 RabbitMQ 的使用。<br/>
> 主要邏輯的話請看對應檔案 class 中的 `execute` 方法。

## 01. RabbitMQ exchange string
> 最基礎使用 RabbitMQ 的方式，透過 exchange string 來傳遞訊息。

![image](./assets/01_exchange_string.png)

自己嘗試使用：
```shell
php main.php rmq01:producer # 產生訊息
php main.php rmq01:consumer # 接收訊息(消化)
```

