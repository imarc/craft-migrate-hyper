<?php
namespace imarc\migratehyper\migrations;

use Craft;
use craft\base\FieldInterface;
use craft\db\Migration;
use craft\db\Query;
use craft\fieldlayoutelements\BaseField;
use craft\fieldlayoutelements\CustomField;
use craft\helpers\App;
use craft\helpers\Json;
use craft\helpers\Db;
use craft\fields\Link;

use yii\base\InvalidArgumentException;
class MigrateHyperContent extends Migration{

    public bool $resaveFields = true;
    public array $fields = [];
    public string $oldFieldTypeClass = '';

    public ?string $fieldHandle = null;

    public function __construct($fieldHandle) {
        $this->fieldHandle = $fieldHandle;
        parent::__construct();
    }

    public function safeUp():bool
    {
        App::maxPowerCaptain();
        if ($this->fieldHandle) {
            $this->fields = (new Query())
            ->from('{{%fields}}')
            ->where(['type' => Link::class])
            ->andWhere(['handle' => $this->fieldHandle])
            ->all();
        } else {
            $this->fields = (new Query())
            ->from('{{%fields}}')
            ->where(['type' => Link::class])
            ->all();
        }
        $this->processFieldContent();
        

        echo 'Finished Migration' . PHP_EOL;

        return true;
    }

    public function processFieldContent(): void
    {

        if (!count($this->fields)) {
            echo "$this->fieldHandle does not exist or is not a link field \n\n";
            return;
        }
        foreach ($this->fields as $fieldData) {
                echo "Preparing to migrate field “{$fieldData['handle']}” ({$fieldData['uid']}) content."  . PHP_EOL;

                // Fetch the field model because we'll need it later
                $field = Craft::$app->getFields()->getFieldById($fieldData['id']);


                if ($field) {
                    // Handle global field content
                    if ($field->context === 'global') {
                        // We have to use field instances, not just the field
                        foreach ($this->findFieldUsages($field) as $fieldLayoutUid) {
                            // Find content rows for each field instance
                            $sql = Craft::$app->getDb()->getQueryBuilder()->jsonExtract('content', [$fieldLayoutUid]);

                            $rows = (new Query())
                                ->select(['content', 'id', 'elementId'])
                                ->from('{{%elements_sites}}')
                                ->where([
                                    'and',
                                    ['not', ['content' => null]],
                                    $sql . ' IS NOT NULL',
                                ])
                                ->all();

                            foreach ($rows as $row) {
                                if (Json::isJsonObject($row['content'])) {
                                    $elementContent = Json::decode($row['content']) ?? [];
                                    $fieldContent = $elementContent[$fieldLayoutUid] ?? '';

                                    if (is_string($fieldContent) && Json::isJsonObject($fieldContent)) {
                                        $fieldContent = Json::decode($fieldContent) ?? [];
                                    }

                                    if ($fieldContent) {
                                        //echo json_encode($fieldContent) . "\n\n";
                                        $settings = $this->convertModel($field, $fieldContent);

                                        if ($settings) {
                                            $elementContent[$fieldLayoutUid] = $settings;

                                            // Direct database save on the content for performance, and not to mess with saving elements
                                            Db::update('{{%elements_sites}}', ['content' => $elementContent], ['id' => $row['id']]);

                                            echo '    > Migrated content for element #' . $row['elementId'] . PHP_EOL;
                                        } else {
                                            // Null model is okay, that's just an empty field content
                                            if ($settings !== null) {
                                                echo '    > Unable to convert content for element #' . $row['elementId'] . PHP_EOL;
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }

                if ($field) {
                    echo "    > Field “{$field['handle']}” content migrated." . PHP_EOL;
                }

                
        }
    }

    public function convertModel(Link $field, array|string $oldSettings): bool|array|null
    {
        if (is_array($oldSettings)) {
            $oldType = $oldSettings[0]['type'] ?? null;

            if (str_contains($oldType, 'verbb\\hyper')) {
                if (is_array($oldSettings) && array_key_exists("linkValue", $oldSettings[0])) {
                    $settings = [];


                    if ($oldType == "verbb\\hyper\\links\\Asset") {
                        $settings["type"] = 'asset';
                        
                    } elseif ($oldType == "verbb\\hyper\\links\\Url" || $oldType == "verbb\\hyper\\links\\Custom") {
                        $settings["type"] = 'url';
                    } elseif ($oldType == "verbb\\hyper\\links\\Category") {
                        $settings["type"] = 'category';
                    } elseif ($oldType == "verbb\\hyper\\links\\Email") {
                        $settings["type"] = 'email';
                    } elseif ($oldType == "verbb\\hyper\\links\\Entry") {
                        $settings["type"] = 'entry';
                    } elseif ($oldType == "verbb\\hyper\\links\\Phone") {
                        $settings["type"] = 'tel';
                    } elseif ($oldType == "verbb\\hyper\\links\\FormieForm") {
                        $settings["type"] = 'form';
                    }

                    
                    if ($settings["type"] == 'asset' || $settings["type"] == 'form' || $settings["type"] == 'category' || $settings["type"] == 'entry') {
                        $assetId = $oldSettings[0]["linkValue"];
                        //
                        if (is_array($assetId)) {
                            $assetId = $assetId[0];
                        }
                        //echo "$assetId = " . $assetId . PHP_EOL;
                        $siteId = $oldSettings[0]["linkSiteId"] ?? "1";
                        $settings["value"] = "{" . $settings["type"] . ":" . $assetId . "@" . $siteId . ":url}";
                    } else {
                        $settings["value"] = $oldSettings[0]["linkValue"];
                    }

                    if (array_key_exists("linkText", $oldSettings[0])) {
                        $settings["label"] = $oldSettings[0]["linkText"];
                    }
                    
                    if (array_key_exists("newWindow", $oldSettings[0])) {
                        if ($oldSettings[0]["newWindow"] == "true") {
                            $settings["target"] = "_blank";
                        }
                    }

                    return $settings;
                }

            }
        
        } elseif ($oldSettings) {
            $settings = [];
            $settings["type"] = "url";
            $settings["value"] = $oldSettings;
            return $settings;
        }
        return null;
    }


    public function safeDown(): bool
    {
        return false;
    }

    public function setConsoleRequest($value): void
    {
        $this->_consoleRequest = $value;
    }

    protected function findFieldUsages(FieldInterface $field): array
    {
        $uids = [];

        foreach (Craft::$app->getFields()->getAllLayouts() as $layout) {
            try {
                $fieldLayoutField = $layout->getField(fn(BaseField $layoutField) => (
                    $layoutField instanceof CustomField && $layoutField->getFieldUid() === $field->uid
                ));

                if ($fieldLayoutField) {
                    $uids[] = $fieldLayoutField->uid;
                }
            } catch (InvalidArgumentException) {

            }
        }

        return $uids;
    }
}