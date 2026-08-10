<?php

declare(strict_types=1);

namespace app\models\forms\client;

use yii\base\Model;

/**
 * Вхідна модель поповнення балансу клієнта.
 *
 * Перевіряє тільки transport-input поточного use case.
 * Пошук клієнта, зміна балансу та постановка Queue Job
 * залишаються відповідальністю ClientService.
 */
final class TopUpClientForm extends Model
{
    /**
     * mixed дозволяє безпечно прийняти сире значення з JSON
     * і відхилити числа, передані як FLOAT або INTEGER.
     */
    public mixed $amount = null;

    public function rules(): array
    {
        return [
            ['amount', 'required'],

            /**
             * Грошове значення приймається як decimal-string.
             * Це запобігає використанню FLOAT із можливою
             * втратою точності ще до потрапляння у Money.
             */
            [
                'amount',
                'string',
                'message' => 'Сума поповнення має бути передана як десятковий рядок.',
            ],
            [
                'amount',
                'match',
                'pattern' => '/^(?!0+(?:\.0{1,2})?$)\d{1,10}(?:\.\d{1,2})?$/',
                'message' => 'Сума поповнення має бути більшою за нуль, містити не більше 10 цифр до крапки та не більше 2 цифр після неї.',
            ],
        ];
    }
}
