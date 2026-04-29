<?php

namespace imarc\migratehyper;

use Craft;
use craft\base\Element;
use craft\base\Model;
use craft\base\Plugin;
use craft\events\RegisterCpNavItemsEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\helpers\Db;

use craft\web\twig\variables\Cp;
use craft\web\twig\variables\CraftVariable;
use craft\web\UrlManager;
use yii\base\Event;

/**
 * Migrate Hyper plugin
 *
 * @author Linnea Hartsuyker <info@imarc.com>
 * @copyright Linnea Hartsuyker
 * @license MIT
 */
class MigrateHyper extends Plugin
{

    public string $schemaVersion = '1.0.0';
    //public bool $hasCpSection = true;
    //public bool $hasCpSettings = true;

    public static function config(): array
    {
        return [
            'components' => [
                // Define component configs here...
            ],
        ];
    }

    public function init(): void
    {
        parent::init();

        // Craft::$app->onInit(function() {
        //     $this->attachEventHandlers();
        // });

        if (Craft::$app->request->isConsoleRequest) {
            $this->controllerNamespace = 'imarc\migratehyper\console\controllers';
        } 

        Craft::info(
            Craft::t(
                'migratehyper',
                'Migrate Hyper plugin loaded',
                ['name' => $this->name]
            ),
            __METHOD__
        );
    }



    // Protected Methods
    // =========================================================================

    /**
     * Creates and returns the model used to store the plugin’s settings.
     *
     * @return \craft\base\Model|null
     */
    protected function createSettingsModel(): ?craft\base\Model
    {
        return new Settings();
    }

}

