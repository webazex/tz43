<?php

declare(strict_types=1);

namespace app\jobs;

use app\services\PendingOrdersProcessor;
use RuntimeException;
use Yii;
use yii\base\BaseObject;
use yii\queue\JobInterface;
use yii\queue\Queue;

/**
 * Queue Job автоматичної оплати pending-замовлень одного клієнта.
 *
 * У payload зберігається тільки clientId. ActiveRecord та application
 * services не серіалізуються: worker отримує актуальні дані з БД
 * безпосередньо під час виконання Job.
 *
 * Бізнес-логіка FIFO та робота з балансом залишаються
 * у PendingOrdersProcessor.
 */
final class ProcessPendingOrdersJob extends BaseObject implements JobInterface
{
    /**
     * Ідентифікатор клієнта, pending-замовлення якого потрібно обробити.
     *
     * Властивість публічна, оскільки yii2-queue серіалізує Job під час
     * постановки в чергу та відновлює її в окремому worker-процесі.
     */
    public int $clientId;

    /**
     * Запускає application use case обробки pending-замовлень.
     *
     * Job не відкриває транзакцію та не реалізує FIFO самостійно.
     * Уся фінансова атомарність залишається всередині processor.
     *
     * Якщо processor повернув failure, Job кидає exception. Без цього
     * yii2-queue вважала б Job успішно виконаною та видалила її з черги.
     *
     * @param Queue $queue Компонент черги, що виконує поточну Job.
     */
    public function execute($queue): void
    {
        /**
         * Job створюється в одному процесі, а виконується в іншому.
         * Тому processor отримується з DI container під час execute(),
         * а не передається у серіалізований payload.
         *
         * @var PendingOrdersProcessor $processor
         */
        $processor = Yii::$container->get(PendingOrdersProcessor::class);
        $result = $processor->process($this->clientId);

        if ($result->isSuccess()) {
            return;
        }

        $error = $result->error();

        /**
         * Exception потрібний для коректного failure-механізму yii2-queue.
         * Початкова технічна помилка вже записана processor у Yii log.
         */
        throw new RuntimeException(
            sprintf(
                'Не вдалося обробити pending-замовлення клієнта %d: %s.',
                $this->clientId,
                $error->code
            )
        );
    }
}
