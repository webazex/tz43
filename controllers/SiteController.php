<?php

declare(strict_types=1);

namespace app\controllers;

use app\models\forms\auth\LoginForm;
use app\models\forms\site\ContactForm;
use Yii;
use yii\base\Security;
use yii\captcha\CaptchaAction;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\mail\MailerInterface;
use yii\web\Controller;
use yii\web\ErrorAction;
use yii\web\Response;

class SiteController extends Controller
{
    public function __construct(
        $id,
        $module,
        private readonly MailerInterface $mailer,
        private readonly Security $security,
        $config = [],
    ) {
        parent::__construct($id, $module, $config);
    }

    /**
     * Web-dashboard використовує звичайну Yii session-auth.
     *
     * API-захист залишається в ApiController, а тут контролюється тільки
     * доступ до server-rendered сторінок адміністративної панелі.
     */
    public function behaviors(): array
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'only' => [
                    'logout',
                    'dashboard',
                    'profile',
                    'clients',
                    'client',
                    'orders',
                    'order-create',
                ],
                'rules' => [
                    [
                        'actions' => [
                            'logout',
                            'dashboard',
                            'profile',
                            'clients',
                            'client',
                            'orders',
                            'order-create',
                        ],
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'logout' => ['post'],
                ],
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function actions(): array
    {
        return [
            'error' => [
                'class' => ErrorAction::class,
            ],
            'captcha' => [
                'class' => CaptchaAction::class,
                'fixedVerifyCode' => YII_ENV_TEST ? 'testme' : null,
                'transparent' => true,
            ],
        ];
    }

    /**
     * Displays homepage.
     */
    public function actionIndex(): string
    {
        return $this->render('login');
    }

    /**
     * Авторизація адміністратора через існуючий LoginForm.
     *
     * Для login використовується окремий легкий layout, щоб стандартний
     * Bootstrap-shell Yii2 Basic не змішувався із затвердженим dashboard UI.
     */
    public function actionLogin(): Response|string
    {
        if (!Yii::$app->user->isGuest) {
            return $this->redirect(['/site/clients']);
        }

        $this->layout = 'dashboard/auth';
        $model = new LoginForm($this->security);

        if ($model->load($this->request->post()) && $model->login()) {
            /**
             * Якщо AccessControl зберіг returnUrl — повертаємо користувача туди.
             * Інакше dashboard стартує зі списку клієнтів.
             */
            return $this->goBack(['/site/clients']);
        }

        /**
         * Пароль не повертаємо назад у HTML після невдалої авторизації.
         */
        $model->password = '';

        return $this->render('login', ['model' => $model]);
    }

    /**
     * Завершує session-auth та повертає на форму входу.
     */
    public function actionLogout(): Response
    {
        Yii::$app->user->logout();

        return $this->redirect(['/site/login']);
    }

    /**
     * Displays contact page.
     */
    public function actionContact(): Response|string
    {
        $model = new ContactForm();

        $contact = $model->load($this->request->post()) && $model->contact(
            $this->mailer,
            Yii::$app->params['adminEmail'],
            Yii::$app->params['senderEmail'],
            Yii::$app->params['senderName'],
        );

        if ($contact) {
            Yii::$app->session->setFlash(
                'success',
                'Thank you for contacting us. We will respond to you as soon as possible.',
            );

            return $this->refresh();
        }

        return $this->render('contact', ['model' => $model]);
    }

    /**
     * Displays about page.
     */
    public function actionAbout(): string
    {
        return $this->render('about');
    }

    /**
     * Кореневий dashboard не дублює окрему сторінку та веде у clients list.
     */
    public function actionDashboard(): Response
    {
        return $this->redirect(['/site/clients']);
    }

    /**
     * Read-only профіль адміністратора.
     */
    public function actionProfile(): string
    {
        $this->layout = 'dashboard/main';

        return $this->render('dashboard/profile');
    }

    /**
     * Сторінка списку клієнтів. Сам список завантажується через REST API.
     */
    public function actionClients(): string
    {
        $this->layout = 'dashboard/main';

        return $this->render('dashboard/clients');
    }

    /**
     * Сторінка клієнта передає у JS тільки route-параметр ID.
     * Business/application data завантажується через існуючий API.
     */
    public function actionClient(int $id): string
    {
        $this->layout = 'dashboard/main';

        return $this->render('dashboard/client', ['clientId' => $id]);
    }

    /**
     * Загальний список замовлень.
     */
    public function actionOrders(): string
    {
        $this->layout = 'dashboard/main';

        return $this->render('dashboard/orders');
    }

    /**
     * Окрема web-форма створення замовлення.
     */
    public function actionOrderCreate(): string
    {
        $this->layout = 'dashboard/main';

        return $this->render('dashboard/order-create');
    }
}
