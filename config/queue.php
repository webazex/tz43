<?php

declare(strict_types=1);

use yii\mutex\MysqlMutex;
use yii\queue\db\Queue;

/**
 * Спільна конфігурація DB Queue для web- та console-застосунків.
 *
 * Web-застосунок використовуватиме компонент для постановки Job у чергу,
 * а console-застосунок — для запуску worker через queue/run або queue/listen.
 * Обидва застосунки працюють з однією таблицею та одним каналом.
 */
return [
    'class' => Queue::class,
    'db' => 'db',
    'tableName' => '{{%queue}}',
    'channel' => 'default',

    /**
     * MysqlMutex синхронізує резервування Job між паралельними workers.
     * Це не замінює блокування бізнес-даних усередині Job: баланс клієнта
     * і pending-замовлення окремо захищатиме application-транзакція.
     */
    'mutex' => MysqlMutex::class,
];
