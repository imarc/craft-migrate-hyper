<?php

namespace imarc\migratehyper\console\controllers;

use Craft;
use imarc\migratehyper\migrations\MigrateHyperField;
use imarc\migratehyper\migrations\MigrateHyperContent;
use yii\console\Controller;

class ConversionController extends Controller
{
    // run with command php craft migratehyper/conversion

    public function actionIndex() {        

        return "Action Index";

    }

    // run with command php craft migratehyper/conversion/migrate-hyper-field

    public function actionMigrateHyperField(?string $fieldName = null) {

        $migration = new MigrateHyperField($fieldName);
        $migration->up();

        return "Migrated hyper fields";
    }

    // run with command php craft migratehyper/conversion/migrate-hyper-content
    public function actionMigrateHyperContent(?string $fieldName = null) {

        $migration = new MigrateHyperContent($fieldName);
        $migration->up();

        return "Migrated hyper fields";

    }
}