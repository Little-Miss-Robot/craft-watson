<?php

namespace littlemissrobot\watson;

use Craft;
use craft\base\Plugin;
use craft\events\RegisterUrlRulesEvent;
use craft\helpers\UrlHelper;
use craft\web\UrlManager;
use littlemissrobot\watson\models\Settings;
use littlemissrobot\watson\services\ViolationService;
use yii\base\Event;

/**
 * Watson plugin
 *
 * @property ViolationService $violations
 * @method Settings getSettings()
 */
class Watson extends Plugin
{
    public static self $plugin;

    public string $schemaVersion = '1.0.0';
    public bool $hasCpSection = true;
    public bool $hasCpSettings = true;

    public static function config(): array
    {
        return [
            'components' => [
                'violations' => ['class' => ViolationService::class],
            ],
        ];
    }

    public function init(): void
    {
        parent::init();

        self::$plugin = $this;

        $request = Craft::$app->getRequest();

        if (!$request->getIsConsoleRequest()) {
            if ($request->getIsCpRequest()) {
                $this->registerCpUrlRules();
            } else {
                $this->registerSiteUrlRules();
            }
        }
    }

    public function getCpNavItem(): ?array
    {
        $navItem = parent::getCpNavItem();
        $navItem['url'] = 'watson';

        return array_merge($navItem, [
            'subnav' => [
                'violations' => [
                    'label' => Craft::t('watson', 'Violations'),
                    'url' => 'watson/violations',
                ],
                'summary' => [
                    'label' => Craft::t('watson', 'Summary'),
                    'url' => 'watson/violations/summary',
                ],
                'settings' => [
                    'label' => Craft::t('watson', 'Settings'),
                    'url' => 'watson/settings',
                ],
            ],
        ]);
    }

    public function getSettingsResponse(): mixed
    {
        return Craft::$app->getResponse()->redirect(UrlHelper::cpUrl('watson/settings'));
    }

    protected function createSettingsModel(): Settings
    {
        return new Settings();
    }

    private function registerCpUrlRules(): void
    {
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function(RegisterUrlRulesEvent $event) {
                $event->rules['watson'] = 'watson/cp/index';
                $event->rules['watson/violations'] = 'watson/cp/index';
                $event->rules['watson/violations/summary'] = 'watson/cp/summary';
                $event->rules['watson/settings'] = 'watson/cp/settings';
            }
        );
    }

    private function registerSiteUrlRules(): void
    {
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_SITE_URL_RULES,
            function(RegisterUrlRulesEvent $event) {
                $event->rules['csp-report'] = 'watson/web/collect';
                $event->rules['csp-report/'] = 'watson/web/collect';
            }
        );
    }
}
