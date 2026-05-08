CREATE DATABASE IF NOT EXISTS `instaSenaiDB`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `instaSenaiDB`;

SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `usuario` (
    `id`           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `email`        VARCHAR(191)  NOT NULL,
    `nome`         VARCHAR(150)  NOT NULL,
    `nick`         VARCHAR(80)   NOT NULL,
    `senha`        VARCHAR(255)  NULL DEFAULT NULL,
    `data_criacao` DATE          NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_usuario_email` (`email`),
    UNIQUE KEY `uq_usuario_nick` (`nick`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `post` (
    `id`           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `id_user`      INT UNSIGNED  NOT NULL,
    `caminho_foto` VARCHAR(255)  NOT NULL,
    `descricao`    VARCHAR(255)  NOT NULL DEFAULT '',
    `data_criacao` DATE          NOT NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_post_id_user` (`id_user`),
    INDEX `idx_post_data_criacao` (`data_criacao`),
    CONSTRAINT `fk_post_usuario`
        FOREIGN KEY (`id_user`)
        REFERENCES `usuario` (`id`)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
