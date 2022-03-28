<?php

declare(strict_types=1);

return [
    'CREATE TABLE user (
        id INT AUTO_INCREMENT NOT NULL,
        some_id INT NOT NULL,
        some_varchar VARCHAR(100) DEFAULT "default 1" NOT NULL,
        some_smallint SMALLINT DEFAULT 1 NOT NULL,
        some_decimal DECIMAL(4,2) DEFAULT 01.00 NOT NULL,
        some_text TEXT DEFAULT NULL,
        address_id INT NOT NULL,
        FULLTEXT(some_text),
        INDEX INDEX1 (some_id),
        INDEX INDEX2 (some_varchar),
        PRIMARY KEY(id)
    ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_520_ci` ENGINE = InnoDB;',
    'CREATE UNIQUE INDEX UNIQUE1 ON user (some_smallint);',
    'CREATE TABLE address (
        id INT AUTO_INCREMENT NOT NULL,
        street VARCHAR(100) NOT NULL,
        house_nr SMALLINT NOT NULL,
        PRIMARY KEY(id)
    ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_520_ci` ENGINE = InnoDB;',
    'ALTER TABLE user ADD CONSTRAINT CONSTRAINT1
        FOREIGN KEY (address_id)
        REFERENCES address (id)
        ON DELETE CASCADE;',
];
