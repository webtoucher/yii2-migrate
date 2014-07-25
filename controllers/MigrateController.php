<?php

namespace webtoucher\migrate\controllers;

use Yii;
use yii\console\Exception;
use yii\db\Query;
use yii\helpers\ArrayHelper;
use yii\helpers\FileHelper;


class MigrateController extends \yii\console\controllers\MigrateController
{
    /**
     * @inheritdoc
     */
    public $templateFile = '@vendor/webtoucher/yii2-migrate/views/migration.php';

    /**
     * @var array Additional aliases of migration directories.
     */
    public $migrationLookup = [];

    /**
     * @var array Additional aliases of migration directories.
     */
    public $modulesPath = '@app/modules';

    /**
     * @inheritdoc
     */
    public function options($actionId)
    {
        return array_merge(
            parent::options($actionId),
            ['migrationLookup'] // global for all actions
        );
    }

    /**
     * @inheritdoc
     */
    public function actionUp($limit = 0)
    {
        $migrations = $this->getNewMigrations();
        if (empty($migrations)) {
            echo "No new migration found. Your system is up-to-date.\n";

            return self::EXIT_CODE_NORMAL;
        }

        $total = count($migrations);
        $limit = (int)$limit;
        if ($limit > 0) {
            $migrations = array_slice($migrations, 0, $limit);
        }

        $n = count($migrations);
        if ($n === $total) {
            echo "Total $n new " . ($n === 1 ? 'migration' : 'migrations') . " to be applied:\n";
        } else {
            echo "Total $n out of $total new " . ($total === 1 ? 'migration' : 'migrations') . " to be applied:\n";
        }

        echo "\nLookup:\n";
        foreach (array_unique($migrations) as $migration => $alias) {
            echo "    " . $alias . " (" . \Yii::getAlias($alias) . ")\n";
        }
        echo "\nMigrations:\n";
        foreach ($migrations as $migration => $alias) {
            echo "    " . $migration . " (" . $alias . ")\n";
        }

        if ($this->confirm('Apply the above ' . ($n === 1 ? 'migration' : 'migrations') . "?")) {
            foreach ($migrations as $migration => $alias) {
                if (!$this->migrateUp($migration, $alias)) {
                    echo "\nMigration failed. The rest of the migrations are canceled.\n";

                    return self::EXIT_CODE_ERROR;
                }
            }
            echo "\nMigrated up successfully.\n";
        }
        return self::EXIT_CODE_NORMAL;
    }

    /**
     * @inheritdoc
     */
    public function actionDown($limit = 1)
    {
        if ($limit === 'all') {
            $limit = null;
        } else {
            $limit = (int) $limit;
            if ($limit < 1) {
                throw new Exception("The step argument must be greater than 0.");
            }
        }

        $migrations = $this->getMigrationHistory($limit);

        if (empty($migrations)) {
            echo "No migration has been done before.\n";

            return self::EXIT_CODE_NORMAL;
        }

        $n = count($migrations);
        echo "Total $n " . ($n === 1 ? 'migration' : 'migrations') . " to be reverted:\n";
        foreach ($migrations as $migration => $info) {
            echo "    $migration (" . $info['alias'] . ")\n";
        }
        echo "\n";

        if ($this->confirm('Revert the above ' . ($n === 1 ? 'migration' : 'migrations') . "?")) {
            foreach ($migrations as $migration => $info) {
                if (!$this->migrateDown($migration, $info['alias'])) {
                    echo "\nMigration failed. The rest of the migrations are canceled.\n";

                    return self::EXIT_CODE_ERROR;
                }
            }
            echo "\nMigrated down successfully.\n";
        }
        return self::EXIT_CODE_NORMAL;
    }

    /**
     * @inheritdoc
     */
    public function actionRedo($limit = 1)
    {
        if ($limit === 'all') {
            $limit = null;
        } else {
            $limit = (int) $limit;
            if ($limit < 1) {
                throw new Exception("The step argument must be greater than 0.");
            }
        }

        $migrations = $this->getMigrationHistory($limit);

        if (empty($migrations)) {
            echo "No migration has been done before.\n";

            return self::EXIT_CODE_NORMAL;
        }

        $n = count($migrations);
        echo "Total $n " . ($n === 1 ? 'migration' : 'migrations') . " to be redone:\n";
        foreach ($migrations as $migration => $info) {
            echo "    $migration\n";
        }
        echo "\n";

        if ($this->confirm('Redo the above ' . ($n === 1 ? 'migration' : 'migrations') . "?")) {
            foreach ($migrations as $migration => $info) {
                if (!$this->migrateDown($migration, $info['alias'])) {
                    echo "\nMigration failed. The rest of the migrations are canceled.\n";

                    return self::EXIT_CODE_ERROR;
                }
            }
            foreach (array_reverse($migrations) as $migration => $info) {
                if (!$this->migrateUp($migration, $info['alias'])) {
                    echo "\nMigration failed. The rest of the migrations migrations are canceled.\n";

                    return self::EXIT_CODE_ERROR;
                }
            }
            echo "\nMigration redone successfully.\n";
        }
        return self::EXIT_CODE_NORMAL;
    }

    /**
     * @inheritdoc
     */
    public function actionMark($version)
    {
        $originalVersion = $version;
        if (preg_match('/^m?(\d{6}_\d{6})(_.*?)?$/', $version, $matches)) {
            $version = 'm' . $matches[1];
        } else {
            throw new Exception("The version argument must be either a timestamp (e.g. 101129_185401)\nor the full name of a migration (e.g. m101129_185401_create_user_table).");
        }

        // try mark up
        $migrations = $this->getNewMigrations();
        $i = 0;
        foreach ($migrations as $migration => $alias) {
            $stack[] = $migration;
            if (strpos($migration, $version . '_') === 0) {
                if ($this->confirm("Set migration history at $originalVersion?")) {
                    $command = $this->db->createCommand();
                    foreach ($stack as $applyMigration) {
                        $command->insert(
                            $this->migrationTable,
                            [
                                'version'    => $applyMigration,
                                'alias'      => $alias,
                                'apply_time' => time(),
                            ]
                        )->execute();
                    }
                    echo "The migration history is set at $originalVersion.\nNo actual migration was performed.\n";
                }

                return self::EXIT_CODE_NORMAL;
            }
            $i++;
        }

        // try mark down
        $migrations = array_keys($this->getMigrationHistory(-1));
        foreach ($migrations as $i => $migration) {
            if (strpos($migration, $version . '_') === 0) {
                if ($i === 0) {
                    echo "Already at '$originalVersion'. Nothing needs to be done.\n";
                } else {
                    if ($this->confirm("Set migration history at $originalVersion?")) {
                        $command = $this->db->createCommand();
                        for ($j = 0; $j < $i; ++$j) {
                            $command->delete(
                                $this->migrationTable,
                                [
                                    'version' => $migrations[$j],
                                ]
                            )->execute();
                        }
                        echo "The migration history is set at $originalVersion.\nNo actual migration was performed.\n";
                    }
                }

                return self::EXIT_CODE_NORMAL;
            }
        }

        throw new Exception("Unable to find the version '$originalVersion'.");
    }

    /**
     * @inheritdoc
     */
    public function actionHistory($limit = 10)
    {
        if ($limit === 'all') {
            $limit = null;
        } else {
            $limit = (int) $limit;
            if ($limit < 1) {
                throw new Exception("The step argument must be greater than 0.");
            }
        }

        $migrations = $this->getMigrationHistory($limit);

        if (empty($migrations)) {
            echo "No migration has been done before.\n";
        } else {
            $n = count($migrations);
            if ($limit > 0) {
                echo "Showing the last $n applied " . ($n === 1 ? 'migration' : 'migrations') . ":\n";
            } else {
                echo "Total $n " . ($n === 1 ? 'migration has' : 'migrations have') . " been applied before:\n";
            }
            foreach ($migrations as $version => $info) {
                echo "    (" . date('Y-m-d H:i:s', $info['apply_time']) . ') ' . $version . "\n";
            }
        }
    }

    /**
     * @inheritdoc
     */
    public function actionCreate($name, $module = null)
    {
        if (!empty($module)) {
            $migrationPath = "@app/modules/$module";
            $path = Yii::getAlias($migrationPath);
            if (!is_dir($path)) {
                throw new Exception("Module '$module' is not exists");
            }
            $path = "$path/migrations";
            if (!is_dir($path)) {
                mkdir($path);
            }
            $this->migrationPath = $path;
        }
        if (!preg_match('/^\w+$/', $name)) {
            throw new Exception("The migration name should contain letters, digits and/or underscore characters only.");
        }

        $name = 'm' . gmdate('ymd_His') . '_' . $name;
        $file = Yii::getAlias($this->migrationPath) . DIRECTORY_SEPARATOR . $name . '.php';

        if ($this->confirm("Create new migration '$file'?")) {
            $content = $this->renderFile(Yii::getAlias($this->templateFile), ['className' => $name]);
            file_put_contents(Yii::getAlias($file), $content);
            echo "New migration created successfully.\n";
        }
    }

    /**
     * Upgrades with the specified migration class.
     *
     * @param string $class The migration class name.
     * @param string $alias The migration alias.
     * @return boolean Whether the migration is successful.
     */
    protected function migrateUp($class, $alias)
    {
        if ($class === self::BASE_MIGRATION) {
            return true;
        }

        echo "*** applying $class\n";
        $start     = microtime(true);
        $migration = $this->createMigration($class, $alias);
        if ($migration->up() !== false) {
            $this->db->createCommand()->insert(
                $this->migrationTable,
                [
                    'version'    => $class,
                    'alias'      => $alias,
                    'apply_time' => time(),
                ]
            )->execute();
            $time = microtime(true) - $start;
            echo "*** applied $class (time: " . sprintf("%.3f", $time) . "s)\n\n";

            return true;
        } else {
            $time = microtime(true) - $start;
            echo "*** failed to apply $class (time: " . sprintf("%.3f", $time) . "s)\n\n";

            return false;
        }
    }

    /**
     * Downgrades with the specified migration class.
     *
     * @param string $class The migration class name.
     * @param string $alias The migration alias.
     * @return boolean Whether the migration is successful.
     */
    protected function migrateDown($class, $alias)
    {
        if ($class === self::BASE_MIGRATION) {
            return true;
        }

        echo "*** reverting $class\n";
        $start = microtime(true);
        $migration = $this->createMigration($class, $alias);
        if ($migration->down() !== false) {
            $this->removeMigrationHistory($class);
            $time = microtime(true) - $start;
            echo "*** reverted $class (time: " . sprintf("%.3f", $time) . "s)\n\n";

            return true;
        } else {
            $time = microtime(true) - $start;
            echo "*** failed to revert $class (time: " . sprintf("%.3f", $time) . "s)\n\n";

            return false;
        }
    }

    /**
     * Creates a new migration instance.
     *
     * @param string $class The migration class name.
     * @param string $alias The migration alias.
     * @return \yii\db\Migration The migration instance.
     */
    protected function createMigration($class, $alias)
    {
        $file = Yii::getAlias($alias) . DIRECTORY_SEPARATOR . $class . '.php';
        require_once($file);

        return new $class(['db' => $this->db]);
    }

    /**
     * @inheritdoc
     */
    protected function migrateToVersion($version)
    {
        $originalVersion = $version;

        // try migrate up
        $migrations = $this->getNewMigrations();
        $i = 0;
        foreach ($migrations as $migration => $alias) {
            if (strpos($migration, $version . '_') === 0) {
                $this->actionUp($i + 1);

                return self::EXIT_CODE_NORMAL;
            }
            $i++;
        }

        // try migrate down
        $migrations = array_keys($this->getMigrationHistory(null));
        foreach ($migrations as $i => $migration) {
            if (strpos($migration, $version . '_') === 0) {
                if ($i === 0) {
                    echo "Already at '$originalVersion'. Nothing needs to be done.\n";
                } else {
                    $this->actionDown($i);
                }

                return self::EXIT_CODE_NORMAL;
            }
        }

        throw new Exception("Unable to find the version '$originalVersion'.");
    }

    /**
     * @inheritdoc
     */
    protected function getMigrationHistory($limit)
    {
        if ($this->db->schema->getTableSchema($this->migrationTable, true) === null) {
            $this->createMigrationHistoryTable();
        }
        $query = new Query;
        $rows = $query->select(['version', 'alias', 'apply_time'])
            ->from($this->migrationTable)
            ->orderBy('version DESC')
            ->limit($limit)
            ->createCommand($this->db)
            ->queryAll();
        $history = ArrayHelper::map($rows, 'version', 'apply_time');
        foreach ($rows AS $row) {
            $history[$row['version']] = ['apply_time' => $row['apply_time'], 'alias' => $row['alias']];
        }
        unset($history[self::BASE_MIGRATION]);

        return $history;
    }

    /**
     * @inheritdoc
     */
    protected function createMigrationHistoryTable()
    {
        $tableName = $this->db->schema->getRawTableName($this->migrationTable);
        echo "Creating migration history table \"$tableName\"...";
        $this->db->createCommand()->createTable($this->migrationTable, [
            'version'    => 'varchar(180) NOT NULL PRIMARY KEY',
            'alias'      => 'varchar(180) NOT NULL',
            'apply_time' => 'integer',
        ])->execute();
        $this->db->createCommand()->insert($this->migrationTable, [
            'version'    => self::BASE_MIGRATION,
            'alias'      => $this->migrationPath,
            'apply_time' => time(),
        ])->execute();
        echo "done.\n";
    }

    /**
     * Returns the migrations that are not applied.
     *
     * @return array List of new migrations (key: migration version; value: alias)
     */
    protected function getNewMigrations()
    {
        $applied = [];
        foreach ($this->getMigrationHistory(null) as $version => $info) {
            $applied[substr($version, 1, 13)] = true;
        }

        $moduleMigrations = FileHelper::findFiles(Yii::getAlias($this->modulesPath), [
            'only' => ['*/migrations/*php']
        ]);
        array_walk($moduleMigrations, function(&$value) {
            $value = dirname($value);
        });
        $moduleMigrations = array_unique($moduleMigrations);
        $directories = ArrayHelper::merge([$this->migrationPath], $moduleMigrations, $this->migrationLookup);

        $migrations = [];
        foreach ($directories as $alias) {
            $dir    = Yii::getAlias($alias);
            $handle = opendir($dir);
            while (($file = readdir($handle)) !== false) {
                if ($file === '.' || $file === '..') {
                    continue;
                }
                $path = $dir . DIRECTORY_SEPARATOR . $file;
                if (preg_match('/^(m(\d{6}_\d{6})_.*?)\.php$/', $file, $matches) && is_file(
                        $path
                    ) && !isset($applied[$matches[2]])
                ) {
                    $migrations[$matches[1]] = $alias;
                }
            }
            closedir($handle);
        }
        ksort($migrations);

        return $migrations;
    }
}
