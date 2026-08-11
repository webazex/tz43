<?php

declare(strict_types=1);

namespace app\tests\Support\Helper;

use Codeception\Module;
use Codeception\Module\Yii2;
use JsonException;
use RuntimeException;

/**
 * Допоміжні actions для Functional suite.
 *
 * Клас містить тільки транспортні операції, яких немає серед
 * стандартних public actions Yii2 module у потрібному нам вигляді.
 *
 * Business logic, fixture data та assertions сюди не переносяться:
 * helper лише дозволяє тесту виконати реальний HTTP-запит
 * з JSON body через Yii2 test application.
 */
final class Functional extends Module
{
    /**
     * Виконує POST-запит із raw JSON body.
     *
     * Стандартний sendAjaxPostRequest() передає масив параметрів як
     * form data. Для REST API цього проєкту важливо перевіряти саме
     * application/json transport, оскільки request body обробляється
     * Yii JsonParser.
     *
     * Заголовки встановлюються тільки на час поточного запиту.
     * Після завершення вони видаляються, щоб один тестовий запит
     * не змінював transport state наступних запитів.
     *
     * @param array<string, mixed> $payload Дані JSON-запиту
     */
    public function sendJsonPostRequest(string $uri, array $payload): void
    {
        $yii2 = $this->getModule('Yii2');

        /**
         * getModule() має загальний return type Module.
         * Явна перевірка робить помилку конфігурації suite зрозумілою:
         * цей helper має працювати тільки разом із Yii2 module.
         */
        if (!$yii2 instanceof Yii2) {
            throw new RuntimeException(
                'Functional helper потребує увімкненого Yii2 module.'
            );
        }

        try {
            $content = json_encode(
                $payload,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE
            );
        } catch (JsonException $exception) {
            throw new RuntimeException(
                'Не вдалося серіалізувати payload functional-запиту у JSON.',
                0,
                $exception
            );
        }

        $yii2->haveHttpHeader('Accept', 'application/json');
        $yii2->haveHttpHeader('Content-Type', 'application/json');

        try {
            /**
             * _loadPage() є hidden API Yii2 module, призначеним саме
             * для використання з Helper-класів.
             *
             * На відміну від _request(), метод завантажує отриману
             * response у Yii2 module. Завдяки цьому після запиту
             * FunctionalTester зможе перевіряти HTTP status,
             * response body та інші характеристики відповіді.
             */
            $yii2->_loadPage('POST', $uri, [], [], [], $content);
        } finally {
            /**
             * Не залишаємо JSON headers глобальним станом module:
             * наступний request у тому самому сценарії повинен сам
             * визначати власний transport contract.
             */
            $yii2->unsetHttpHeader('Accept');
            $yii2->unsetHttpHeader('Content-Type');
        }
    }
}
