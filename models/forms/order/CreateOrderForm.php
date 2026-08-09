<?php

declare(strict_types=1);

namespace app\models\forms\order;

use yii\base\Model;

/**
 * Вхідна модель створення замовлення.
 *
 * Приймає та перевіряє тільки дані, які має право передати зовнішній
 * споживач. Статус замовлення навмисно відсутній: paid або pending
 * визначає OrderService на підставі актуального балансу клієнта.
 *
 * Form Model не читає дані з БД і не виконує persistence.
 */
final class CreateOrderForm extends Model
{
    public mixed $client_id = null;
    public mixed $amount = null;
    public mixed $description = null;

    public function rules(): array
    {
        return [
            ['description', 'trim'],

            [['client_id', 'amount', 'description'], 'required'],

            [
                'client_id',
                'integer',
                'min' => 1,
                'message' => 'Ідентифікатор клієнта має бути цілим числом.',
                'tooSmall' => 'Ідентифікатор клієнта має бути більшим за нуль.',
            ],

            /**
             * Грошове значення приймається як decimal-string.
             * Це не допускає передачу FLOAT із вже втраченою точністю.
             */
            [
                'amount',
                'string',
                'message' => 'Сума замовлення має бути передана як десятковий рядок.',
            ],
            [
                'amount',
                'match',
                'pattern' => '/^(?!0+(?:\.0{1,2})?$)\d{1,10}(?:\.\d{1,2})?$/',
                'message' => 'Сума замовлення має бути більшою за нуль, містити не більше 10 цифр до крапки та не більше 2 цифр після неї.',
            ],

            ['description', 'string'],
        ];
    }
}