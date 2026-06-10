<?php

namespace littlemissrobot\watson\controllers;

use Craft;
use craft\web\Controller;
use littlemissrobot\watson\Watson;
use yii\web\Response;

class WebController extends Controller
{
    protected array|bool|int $allowAnonymous = ['collect'];

    public function beforeAction($action): bool
    {
        $this->enableCsrfValidation = false;
        return parent::beforeAction($action);
    }

    public function actionCollect(): Response
    {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();

        $result = Watson::getInstance()->violations->persistReports(
            $request->getRawBody(),
            (string) $request->getHeaders()->get('Content-Type', ''),
            $request->getUserAgent(),
            $request->getUserIP(),
            $request->getReferrer(),
        );

        Craft::info(
            sprintf(
                'Watson: accepted %d CSP report(s), wrote %d.',
                $result['received'],
                $result['written']
            ),
            __METHOD__
        );

        $response = Craft::$app->getResponse();
        $response->setStatusCode(204);

        return $this->asRaw('');
    }
}
