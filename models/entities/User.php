<?php

declare(strict_types=1);

namespace app\models\entities;

use yii\base\Security;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;
use yii\web\IdentityInterface;

/**
 * ActiveRecord-модель користувача адміністративної панелі.
 *
 * @property int $id
 * @property string $username
 * @property string $email
 * @property string $auth_key
 * @property string $password_hash
 * @property string $status
 * @property int $created_at
 * @property int $updated_at
 *
 * @property-read string $passwordHash
 */
final class User extends ActiveRecord implements IdentityInterface
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_BLOCKED = 'blocked';

    /**
     * Повертає таблицю, створену міграцією create_user_table.
     */
    public static function tableName(): string
    {
        return '{{%user}}';
    }

    /**
     * Автоматично заповнює created_at та updated_at Unix-часом.
     */
    public function behaviors(): array
    {
        return [
            'timestamp' => [
                'class' => TimestampBehavior::class,
                'createdAtAttribute' => 'created_at',
                'updatedAtAttribute' => 'updated_at',
            ],
        ];
    }

    /**
     * Правила моделі є application-рівнем захисту даних.
     *
     * UNIQUE-індекси в БД залишаються остаточним захистом від дублів,
     * зокрема під час одночасного виконання декількох запитів.
     */
    public function rules(): array
    {
        return [
            [['username', 'email', 'auth_key', 'password_hash'], 'required'],

            [['username', 'email'], 'trim'],

            ['username', 'string', 'min' => 3, 'max' => 64],
            ['username', 'unique', 'message' => 'This username is already in use.'],

            ['email', 'email'],
            ['email', 'string', 'max' => 255],
            ['email', 'unique', 'message' => 'This email is already in use.'],

            ['auth_key', 'string', 'max' => 32],
            ['password_hash', 'string', 'max' => 255],

            [
                'status',
                'default',
                'value' => self::STATUS_ACTIVE,
            ],
            [
                'status',
                'in',
                'range' => [
                    self::STATUS_ACTIVE,
                    self::STATUS_BLOCKED,
                ],
            ],

            [['created_at', 'updated_at'], 'integer'],
        ];
    }

    /**
     * Знаходить активного користувача за первинним ключем.
     *
     * Фільтр за статусом не дозволяє заблокованому користувачу
     * продовжувати авторизацію через збережену cookie.
     */
    public static function findIdentity($id): static|null
    {
        return static::find()
            ->where([
                'id' => $id,
                'status' => self::STATUS_ACTIVE,
            ])
            ->one();
    }

    /**
     * Access token для адміністративної панелі не використовується.
     *
     * API-клієнти пізніше отримають окремий механізм автентифікації,
     * тому токени не змішуються з обліковими записами працівників.
     */
    public static function findIdentityByAccessToken(
        $token,
        $type = null
    ): static|null {
        return null;
    }

    /**
     * Знаходить активного користувача за логіном.
     *
     * У MySQL/MariaDB порівняння є регістронезалежним завдяки
     * collation utf8mb4_unicode_ci, заданій у міграції.
     */
    public static function findByUsername(string $username): static|null
    {
        return static::find()
            ->where([
                'username' => trim($username),
                'status' => self::STATUS_ACTIVE,
            ])
            ->one();
    }

    /**
     * Повертає первинний ключ у форматі IdentityInterface.
     */
    public function getId(): int|string
    {
        return (int) $this->getPrimaryKey();
    }

    /**
     * Повертає auth key, який Yii використовує для remember me.
     */
    public function getAuthKey(): string|null
    {
        return $this->auth_key;
    }

    /**
     * Порівнює auth key без залежного від значення часу виконання.
     */
    public function validateAuthKey($authKey): bool
    {
        return hash_equals(
            $this->auth_key,
            (string) $authKey
        );
    }

    /**
     * Getter зберігає сумісність із поточним LoginForm:
     *
     * $user->passwordHash
     *
     * Фактичне поле таблиці при цьому називається password_hash.
     */
    public function getPasswordHash(): string
    {
        return $this->password_hash;
    }

    /**
     * Створює безпечний хеш нового пароля.
     *
     * Відкритий пароль не зберігається у властивостях ActiveRecord
     * і ніколи не записується в базу даних.
     */
    public function setPassword(string $password, Security $security): void {
        $this->password_hash = $security->generatePasswordHash($password);
    }

    /**
     * Генерує випадковий ключ для перевірки довготривалої cookie.
     */
    public function generateAuthKey(Security $security): void
    {
        $this->auth_key = $security->generateRandomString(32);
    }
}
