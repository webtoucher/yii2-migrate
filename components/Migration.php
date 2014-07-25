<?php
/**
 * @link https://github.com/webtoucher/yii2-migrate
 * @copyright Copyright (c) 2014 webtoucher
 * @license https://github.com/webtoucher/yii2-migrate/blob/master/LICENSE.md
 */

namespace webtoucher\migrate\components;

use yii\db\Expression;


/**
 * Migration class
 *
 * @author Alexey Kuznetsov <mirakuru@webtoucher.ru>
 * @since 2.0
 */
class Migration extends \yii\db\Migration
{
    const NOT_NULL = ' NOT NULL';

    // default values for fields
    const DEFAULT_FALSE     = ' DEFAULT FALSE';
    const DEFAULT_TRUE      = ' DEFAULT TRUE';
    const DEFAULT_ZERO      = ' DEFAULT 0';
    const DEFAULT_TIMESTAMP = ' DEFAULT now()';

    // ON DELETE and ON UPDATE options for relations
    const RESTRICT    = 'RESTRICT';
    const CASCADE     = 'CASCADE';
    const NO_ACTION   = 'NO ACTION';
    const SET_DEFAULT = 'SET DEFAULT';
    const SET_NULL    = 'SET NULL';

    /**
     * Returns custom default value.
     *
     * @param integer|string|Expression|null $value
     * @return string
     */
    public function defaultValue($value)
    {
        $value = $this->db->quoteValue($value);
        return " DEFAULT $value";
    }

    /**
     * Adds comment for a table.
     *
     * @param string $table
     * @param string $comment
     * @return void
     */
    public function addCommentOnTable($table, $comment)
    {
        $table = $this->db->quoteTableName($table);
        $comment = $this->db->quoteValue($comment);

        $this->execute("COMMENT ON TABLE $table IS $comment;");
    }

    /**
     * Adds comment for a table's column.
     *
     * @param string $table
     * @param string $column
     * @param string $comment
     * @return void
     */
    public function addCommentOnColumn($table, $column, $comment)
    {
        $table = $this->db->quoteTableName($table);
        $column = $this->db->quoteColumnName($column);
        $comment = $this->db->quoteValue($comment);

        $this->execute("COMMENT ON COLUMN $table.$column IS $comment;");
    }
}
