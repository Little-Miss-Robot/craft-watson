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
 * Watson plugin.
 *
 * @method Settings getSettings()
 *
 * @author Little Miss Robot <hello@littlemissrobot.be>
 * @since 1.0.0
 */
class Watson extends Plugin
{
    // =========================================================================
    // Static Properties
    // =========================================================================

    /**
     * @var static
     */
    public static self $plugin;

    // =========================================================================
    // Public Properties
    // =========================================================================

    /**
     * @var string
     */
    public string $schemaVersion = '1.0.0';

    /**
     * @var bool
     */
    public bool $hasCpSection = true;

    /**
     * @var bool
     */
    public bool $hasCpSettings = true;

    // =========================================================================
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function config(): array
    {
        return [
            'components' => [
                'violations' => ['class' => ViolationService::class],
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public function init(): void
    {
        parent::init();

        self::$plugin = $this;

        $request = Craft::$app->getRequest();

        if (!$request->getIsConsoleRequest()) {
            if ($request->getIsCpRequest()) {
                $this->_registerCpUrlRules();
            } else {
                $this->_registerSiteUrlRules();
            }
        }
    }

    /**
     * Returns the violations service.
     *
     * @return ViolationService
     */
    public function getViolations(): ViolationService
    {
        $component = $this->get('violations');
        assert($component instanceof ViolationService);

        return $component;
    }

    /**
     * @inheritdoc
     */
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
                // Settings link is intentionally shown in production-locked environments;
                // the settings template handles read-only rendering via allowAdminChanges.
                'settings' => [
                    'label' => Craft::t('watson', 'Settings'),
                    'url' => 'watson/settings',
                ],
            ],
        ]);
    }

    /**
     * @inheritdoc
     */
    public function getSettingsResponse(): mixed
    {
        return Craft::$app->getResponse()->redirect(UrlHelper::cpUrl('watson/settings'));
    }

    // =========================================================================
    // Protected Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    protected function createSettingsModel(): Settings
    {
        return new Settings();
    }

    // =========================================================================
    // Private Methods
    // =========================================================================

    /**
     * Registers CP URL rules.
     *
     * @return void
     */
    private function _registerCpUrlRules(): void
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

    /**
     * Registers site URL rules for the CSP reporting endpoint.
     *
     * @return void
     */
    private function _registerSiteUrlRules(): void
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
