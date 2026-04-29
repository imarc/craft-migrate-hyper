<?php
namespace imarc\migratehyper\migrations;

use Craft;
use craft\db\Migration;
use craft\db\Query;
use craft\helpers\App;
use craft\helpers\Json;
use craft\fields\Link;
use Mpdf\Tag\P;
use yii\console\Controller;

class MigrateHyperField extends Migration{
    // Properties
    // =========================================================================

    public bool $resaveFields = true;
    public array $fields = [];
    public string $oldFieldTypeClass = '';

    public ?string $fieldHandle = null;

    //private ?Controller $_consoleRequest = null;

    public function __construct($fieldHandle) {
        $this->fieldHandle = $fieldHandle;
        parent::__construct();
    }

    public function safeUp(): bool 
    {
        App::maxPowerCaptain();
        $linkFields = $this->getFields();
        $fieldsService = Craft::$app->getFields();
        $linkField = $fieldsService->getFieldById(560);
        //$fieldConfig = $fieldsService->createFieldConfig($linkField);

        if (!count($linkFields)) {
            echo "$this->fieldHandle does not exist or is not a hyper field \n\n";
            return false;
        }

        foreach($linkFields as $field) {
            //if ($field["id"] == 1514) {
                $settings = Json::decode($field['settings']);
                //echo json_encode($settings) . "\n\n";
                echo "Converting " . $field["handle"] . "\n\n";
                $types = [];
                $typeSettings = [];
                $showLabelField = false;
                foreach ($settings["linkTypes"] as $linkType) {
                    if ($linkType["enabled"]) {
                        if ($linkType["type"] == "verbb\\hyper\\links\\Asset") {
                            $types[] = "asset";
                            $typeSettings["asset"] = [
                                "sources" => $linkType["sources"]
                            ];
                        }
                        if ($linkType["type"] == "verbb\\hyper\\links\\Category") {
                            $types[] = "category";
                            $typeSettings["category"] = [
                                    "sources" => $linkType["sources"]
                            ];
                        }
                        if ($linkType["type"] == "verbb\\hyper\\links\\Custom") {
                            if (!in_array("url", $types)) {
                                $types[] = "url";
                            }
                            $typeSettings["url"] = [
                                    "allowRootRelativeUrls" => 1,
                                    "allowAnchors" => 1,
                                    "allowCustomSchemes" => 1
                            ];
                        }
                        if ($linkType["type"] == "verbb\\hyper\\links\\Email") {
                            $types[] = "email";
                        }
                        if ($linkType["type"] == "verbb\\hyper\\links\\Entry") {
                            $types[] = "entry";
                            $typeSettings["entry"] = [
                                "sources" => $linkType["sources"]
                            ];
                        }
                        if ($linkType["type"] == "verbb\\hyper\\links\\Phone") {
                            $types[] = "tel";
                        }
                        if ($linkType["type"] == "verbb\\hyper\\links\\Url") {
                            if (!in_array("url", $types)) {
                                $types[] = "url";
                            }
                        }
                        if ($linkType["type"] == "verbb\\hyper\\links\\FormieForm") {
                            $types[] = "form";
                            $typeSettings["form"] = [
                                "sources" => $linkType["sources"]
                            ];
                        }
                        if (!$showLabelField) {
                            if (array_key_exists("layoutConfig", $linkType) &&
                                array_key_exists("tabs", $linkType["layoutConfig"])) {
                                foreach ($linkType["layoutConfig"]["tabs"] as $tab) {
                                    if (array_key_exists("elements", $tab)) {
                                        foreach ($tab["elements"] as $el) {
                                            if (array_key_exists("attribute", $el) && $el["attribute"] == "linkText") {
                                                $showLabelField = true;
                                                break;
                                            }
                                        }
                                    }
                                    if ($showLabelField) {
                                        break;
                                    }
                                }
                            }                                
                        }
                    }
                }

                // echo json_encode($types) . "\n\n";
                // echo json_encode($typeSettings) . "\n\n";
            
                $newFieldConfig = $field;
                unset($newFieldConfig['type'], $newFieldConfig['settings']);


                $newFieldConfig["showLabelField"] = $showLabelField;
                $newFieldConfig["advancedFields"] = [
                        "target"
                    ];

                
                $newFieldConfig['typeSettings'] = $typeSettings;
                $newFieldConfig['types'] = $types;
                //echo json_encode($newFieldConfig) . "\n\n";

                
                $newField = new Link($newFieldConfig);
                if (!$fieldsService->saveField($newField)) {
                    echo "Failed to convert $this->fieldHandle \n\n";
                }
            
            //}
        }
        return true;
    }


    public function safeDown(): bool
    {
        return false;
    }

    public function setConsoleRequest($value): void
    {
        $this->_consoleRequest = $value;
    }

    public function getFields()
    {

        if ($this->fieldHandle) {
            $blockFields = (new Query())
                ->from('{{%fields}}')
                ->where(['type' => "verbb\\hyper\\fields\\HyperField"])
                ->andWhere(['handle' => $this->fieldHandle])
                ->all();
        } else {
            $blockFields = (new Query())
                ->from('{{%fields}}')
                ->where(['type' => "verbb\\hyper\\fields\\HyperField"])
                ->all();
        }
        

        return $blockFields;

    }

}