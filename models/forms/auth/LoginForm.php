<?php

declare(strict_types=1);

namespace app\models\forms\auth;

use app\models\entities\User;
use Yii;
use yii\base\Model;
use yii\base\Security;

/**
 * LoginForm is the model behind the login form.
 *
 * @property-read User|null $user
 */
class LoginForm extends Model
{
    public string $username = '';
    public string $password = '';
    private User|null $_user = null;
    private bool $_userLoaded = false;
    public function __construct(private readonly Security $security, $config = [])
    {
        parent::__construct($config);
    }

    /**
     * @return array the validation rules.
     */
    public function rules(): array
    {
        return [
            // username and password are both required
            [['username', 'password'], 'required'],
            // password is validated by validatePassword()
            ['password', 'validatePassword'],
        ];
    }

    /**
     * Validates the password.
     * This method serves as the inline validation for password.
     *
     * @param string $attribute the attribute currently being validated
     * @param array $params the additional name-value pairs given in the rule
     */
    public function validatePassword(string $attribute, array|null $params): void
    {
        if (!$this->hasErrors()) {
            $user = $this->getUser();

            if (!$user || !$this->security->validatePassword($this->password, $user->passwordHash)) {
                $this->addError($attribute, 'Incorrect username or password.');
            }
        }
    }

    /**
     * Авторизує адміністративного користувача в межах поточної Yii session.
     *
     * Persistent login навмисно не використовується:
     * після завершення session користувач повинен авторизуватися повторно.
     */
    public function login(): bool
    {
        if (!$this->validate()) {
            return false;
        }

        $user = $this->getUser();

        return $user !== null && Yii::$app->user->login($user);
    }

    /**
     * Finds user by [[username]]
     *
     * @return User|null
     */
    public function getUser(): User|null
    {
        if (!$this->_userLoaded) {
            $this->_user = User::findByUsername($this->username);
            $this->_userLoaded = true;
        }

        return $this->_user;
    }
}
