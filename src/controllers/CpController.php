<?php

namespace littlemissrobot\watson\controllers;

use Craft;
use craft\helpers\Cp;
use craft\web\Controller;
use littlemissrobot\watson\Watson;
use yii\web\Response;

/**
 * CP controller.
 *
 * Handles all control panel requests for the Watson plugin.
 *
 * @author Little Miss Robot <hello@littlemissrobot.be>
 * @since 1.0.0
 */
class CpController extends Controller
{
    // =========================================================================
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        $this->requirePermission('accessPlugin-watson');

        return true;
    }

    /**
     * Renders the violations index.
     *
     * @return Response
     */
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

        $result = Watson::$plugin->getViolations()->getViolations($filters, $page, 50, $sort, $dir);

        return $this->renderTemplate('watson/violations/index', array_merge($result, [
            'filters' => array_merge(
                ['effectiveDirective' => '', 'blockedUri' => '', 'documentUri' => '', 'dateFrom' => '', 'dateTo' => '', 'status' => 'new'],
                $request->getQueryParams()
            ),
            'page' => $page,
            'sort' => $sort,
            'dir' => $dir,
            'settings' => Watson::$plugin->getSettings(),
        ]));
    }

    /**
     * Renders the violations summary.
     *
     * @return Response
     */
    public function actionSummary(): Response
    {
        $request = Craft::$app->getRequest();
        $limit = max(1, (int) $request->getParam('limit', 20));

        $allowedSorts = ['count', 'effectiveDirective', 'blockedUri', 'documentUri'];
        $sort = $request->getParam('sort', 'count');
        $sort = in_array($sort, $allowedSorts, true) ? $sort : 'count';
        $dir = $request->getParam('dir', 'desc') === 'asc' ? 'asc' : 'desc';

        $summary = Watson::$plugin->getViolations()->summarize($limit, $sort, $dir);

        return $this->renderTemplate('watson/violations/summary', [
            'summary' => $summary,
            'limit' => $limit,
            'sort' => $sort,
            'dir' => $dir,
            'settings' => Watson::$plugin->getSettings(),
        ]);
    }

    /**
     * Updates the status of one or more violations, or deletes them.
     *
     * @return Response
     */
    public function actionUpdateStatus(): Response
    {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();
        $ids = array_filter(array_map('intval', (array) $request->getBodyParam('ids', [])));
        $status = (string) $request->getBodyParam('status', '');

        $allowedStatuses = ['new', 'resolved', 'ignored', 'delete'];

        if (!empty($ids) && in_array($status, $allowedStatuses, true)) {
            if ($status === 'delete') {
                $count = Watson::$plugin->getViolations()->deleteByIds($ids);
                Craft::$app->getSession()->setNotice(
                    Craft::t('watson', '{n, plural, =1{1 violation} other{# violations}} deleted.', ['n' => $count])
                );
            } else {
                $count = Watson::$plugin->getViolations()->updateStatus($ids, $status);
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

    /**
     * Renders the settings page, and saves settings on POST.
     *
     * @return Response
     */
    public function actionSettings(): Response
    {
        $readOnly = !Craft::$app->getConfig()->getGeneral()->allowAdminChanges;
        $settings = Watson::$plugin->getSettings();

        if (!$readOnly && Craft::$app->getRequest()->getIsPost()) {
            $this->requirePostRequest();
            $this->requireAdmin();

            $settings->setAttributes(
                Craft::$app->getRequest()->getBodyParam('settings', [])
            );

            if (Craft::$app->getPlugins()->savePluginSettings(Watson::$plugin, $settings->getAttributes())) {
                Craft::$app->getSession()->setNotice(Craft::t('app', 'Settings saved.'));
            } else {
                Craft::$app->getSession()->setError(Craft::t('app', 'Couldn\'t save settings.'));
            }

            return $this->redirectToPostedUrl(null, 'watson/settings');
        }

        return $this->renderTemplate('watson/settings', [
            'settings' => $settings,
            'readOnly' => $readOnly,
        ]);
    }

    /**
     * Purges violations older than a given number of days.
     *
     * @return Response
     */
    public function actionPurge(): Response
    {
        $this->requirePostRequest();

        $days = Craft::$app->getRequest()->getBodyParam('days');
        $deleted = Watson::$plugin->getViolations()->purge($days !== null ? (int) $days : null);

        Craft::$app->getSession()->setNotice(
            Craft::t('watson', '{n, plural, =1{1 violation} other{# violations}} purged.', ['n' => $deleted])
        );

        return $this->redirectToPostedUrl(null, 'watson/violations');
    }
}
