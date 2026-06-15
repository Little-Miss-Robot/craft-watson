<?php

namespace littlemissrobot\watson\controllers;

use Craft;
use craft\web\Controller;
use littlemissrobot\watson\Watson;
use yii\web\Response;

/**
 * Web controller.
 *
 * Handles public-facing requests, specifically the CSP report collection endpoint.
 *
 * @author Little Miss Robot <hello@littlemissrobot.be>
 * @since 1.0.0
 */
class WebController extends Controller
{
    // =========================================================================
    // Public Properties
    // =========================================================================

    /**
     * @var array<string>|bool|int
     */
    protected array|bool|int $allowAnonymous = ['collect'];

    // =========================================================================
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function beforeAction($action): bool
    {
        // The collect endpoint receives browser-generated reports that do not carry CSRF tokens.
        if ($action->id === 'collect') {
            $this->enableCsrfValidation = false;
        }

        return parent::beforeAction($action);
    }

    /**
     * Accepts a CSP violation report and stores it.
     *
     * Supports both the legacy `application/csp-report` format and the modern
     * Reporting API `application/reports+json` format. Always returns 204.
     *
     * @return Response
     */
    public function actionCollect(): Response
    {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();

        $result = Watson::$plugin->getViolations()->persistReports(
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
