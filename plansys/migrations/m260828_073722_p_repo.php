<?php

/**
 * Tabel repository file upload (mirror dari repo fitur API & form).
 * Status file: temp (baru diupload, belum dirujuk) vs committed
 * (dirujuk baris tersimpan; aman dari pembersihan cleanRepo).
 * Idempoten: bila tabel sudah ada (instalasi lama), migration di-skip.
 */
class m260828_073722_p_repo extends Migration {

    public function up() {
        if ($this->dbConnection->schema->getTable('p_repo') !== null) {
            echo "    > table p_repo sudah ada, dilewati\n";
            return true;
        }

        $this->createTable('p_repo', array(
            'id'               => 'pk',
            'origin_file_name' => 'varchar(256) NOT NULL',
            'location'         => 'varchar(256) NOT NULL',
            'extension'        => 'varchar(256) NOT NULL',
            'hashed'           => 'text NOT NULL',
            'mime'             => 'varchar(256) NOT NULL',
            'user_id'          => 'integer NOT NULL',
            'created_date'     => 'datetime NOT NULL',
            'size'             => 'decimal',
            'src'              => 'varchar(256) NOT NULL',
            'status'           => "varchar(32) NOT NULL DEFAULT 'committed'",
        ));
        $this->addAutoIncrement('p_repo', 'id');
    }

    public function down() {
        if ($this->dbConnection->schema->getTable('p_repo') === null) {
            return true;
        }
        $this->dropAutoIncrement('p_repo', 'id');
        $this->dropTable('p_repo');
    }
}