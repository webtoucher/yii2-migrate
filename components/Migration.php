<?php

namespace webtoucher\migrate\components;


class Migration extends \yii\db\Migration
{
    const NOT_NULL = ' NOT NULL';

    // default values for fields
    const DEFAULT_FALSE     = ' DEFAULT FALSE';
    const DEFAULT_TRUE      = ' DEFAULT FALSE';
    const DEFAULT_ZERO      = ' DEFAULT 0';
    const DEFAULT_TIMESTAMP = ' DEFAULT now()';

    // ON DELETE and ON UPDATE options for relations
    const RESTRICT    = 'RESTRICT';
    const CASCADE     = 'CASCADE';
    const NO_ACTION   = 'NO ACTION';
    const SET_DEFAULT = 'SET DEFAULT';
    const SET_NULL    = 'SET NULL';

    /**
     * Adds comment for a table
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
     * Adds comment for a table's column
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
