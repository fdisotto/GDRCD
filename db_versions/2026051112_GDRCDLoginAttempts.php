<?php

class GDRCDLoginAttempts extends DbMigration
{
    /**
     * @inheritDoc
     */
    public function up()
    {
        gdrcd_query("
            CREATE TABLE IF NOT EXISTS login_attempts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                ip VARCHAR(45) NOT NULL,
                username VARCHAR(50) NULL,
                attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                success TINYINT(1) NOT NULL DEFAULT 0,
                INDEX idx_login_attempts_ip_time (ip, attempted_at),
                INDEX idx_login_attempts_user_time (username, attempted_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8
        ");
    }

    /**
     * @inheritDoc
     */
    public function down()
    {
        gdrcd_query("DROP TABLE IF EXISTS login_attempts");
    }
}
