<?php

namespace littlemissrobot\watson\controllers;

use Craft;
use craft\web\Controller;
use littlemissrobot\watson\Watson;
use yii\web\Response;

class CpController extends Controller
{
    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        $this->requirePermission('accessPlugin-watson');

        return true;
    }

    public function actionIndex(): Response
    {
        $request = Craft::$app->getRequest();

        $filters = array_filter([
            'effectiveDirective' => $request->getParam('effectiveDirective'),
            'blockedUri' => $request->getParam('blockedUri'),
            'documentUri' => $request->getParam('documentUri'),
            'dateFrom' => $request->getParam('dateFrom'),
            'dateTo' => $request->getParam('dateTo'),
            'status' => $request->getParam('status', 'new'),
        ], static fn($v) => $v !== null && $v !== '');

        // Allow explicitly showing all statuses when status param is empty string
        if ($request->getParam('status') === '') {
            unset($filters['status']);
        }

        $page = max(1, (int) $request->getParam('page', 1));

        $allowedSorts = ['dateCreated', 'status', 'effectiveDirective', 'blockedUri', 'documentUri'];
        $sort = $request->getParam('sort', 'dateCreated');
        $sort = in_array($sort, $allowedSorts, true) ? $sort : 'dateCreated';
        $dir = $request->getParam('dir', 'desc') === 'asc' ? 'asc' : 'desc';

        $result = Watson::getInstance()->violations->getViolations($filters, $page, 50, $sort, $dir);

        return $this->renderTemplate('watson/violations/index', array_merge($result, [
            'filters' => array_merge(
                ['effectiveDirective' => '', 'blockedUri' => '', 'documentUri' => '', 'dateFrom' => '', 'dateTo' => '', 'status' => 'new'],
                $request->getQueryParams()
            ),
            'page' => $page,
            'sort' => $sort,
            'dir' => $dir,
            'settings' => Watson::getInstance()->getSettings(),
        ]));
    }

    public function actionSummary(): Response
    {
        $request = Craft::$app->getRequest();
        $limit = max(1, (int) $request->getParam('limit', 20));

        $allowedSorts = ['count', 'effectiveDirective', 'blockedUri', 'documentUri'];
        $sort = $request->getParam('sort', 'count');
        $sort = in_array($sort, $allowedSorts, true) ? $sort : 'count';
        $dir = $request->getParam('dir', 'desc') === 'asc' ? 'asc' : 'desc';

        $summary = Watson::getInstance()->violations->summarize($limit, $sort, $dir);

        return $this->renderTemplate('watson/violations/summary', [
            'summary' => $summary,
            'limit' => $limit,
            'sort' => $sort,
            'dir' => $dir,
            'settings' => Watson::getInstance()->getSettings(),
        ]);
    }

    public function actionUpdateStatus(): Response
    {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();
        $ids = array_filter(array_map('intval', (array) $request->getBodyParam('ids', [])));
        $status = (string) $request->getBodyParam('status', '');

        if (!empty($ids) && $status !== '') {
            if ($status === 'delete') {
                $count = Watson::getInstance()->violations->deleteByIds($ids);
                Craft::$app->getSession()->setNotice(
                    Craft::t('watson', '{n, plural, =1{1 violation} other{# violations}} deleted.', ['n' => $count])
                );
            } else {
                $count = Watson::getInstance()->violations->updateStatus($ids, $status);
                Craft::$app->getSession()->setNotice(
                    Craft::t('watson', '{n, plural, =1{1 violation} other{# violations}} marked as {status}.', [
                        'n' => $count,
                        'status' => $status,
                    ])
                );
            }
        }

        return $this->redirectToPostedUrl(null, 'watson/violations');
    }

    public function actionSettings(): Response
    {
        $settings = Watson::getInstance()->getSettings();

        if (Craft::$app->getRequest()->getIsPost()) {
            $settings->setAttributes(
                Craft::$app->getRequest()->getBodyParam('settings', []),
                false
            );

            if (Craft::$app->getPlugins()->savePluginSettings(Watson::getInstance(), $settings->getAttributes())) {
                Craft::$app->getSession()->setNotice(Craft::t('app', 'Settings saved.'));
            } else {
                Craft::$app->getSession()->setError(Craft::t('app', 'Couldn\'t save settings.'));
            }

            return $this->redirectToPostedUrl(null, 'watson/settings');
        }

        return $this->renderTemplate('watson/settings', [
            'settings' => $settings,
        ]);
    }

    public function actionPurge(): Response
    {
        $this->requirePostRequest();

        $days = Craft::$app->getRequest()->getBodyParam('days');
        $deleted = Watson::getInstance()->violations->purge($days !== null ? (int) $days : null);

        Craft::$app->getSession()->setNotice(
            Craft::t('watson', '{n, plural, =1{1 violation} other{# violations}} purged.', ['n' => $deleted])
        );

        return $this->redirectToPostedUrl(null, 'watson/violations');
    }
}
