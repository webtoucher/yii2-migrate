<?php
/**
 * This view is used by webtoucher\migrate\MigrateController.php
 *
 * @var string $className the new migration class name
 */

echo "<?php\n";
?>

use yii\db\Schema;


class <?= $className ?> extends webtoucher\migrate\components\Migration
{
    public function safeUp()
    {

    }

    public function safeDown()
    {
        echo "<?= $className ?> cannot be reverted.\n";

        return false;
    }
}