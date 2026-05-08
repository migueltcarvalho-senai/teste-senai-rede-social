-- ===========================================================================
-- MIGRATION FILE — instaSenaiDB
-- Projeto  : InstaSenai (Rede Social SENAI)
-- Banco    : instaSenaiDB
-- Charset  : utf8mb4  (suporte completo a emojis e caracteres especiais)
-- Engine   : InnoDB   (suporte a transações e chaves estrangeiras)
-- Criado em: 2026-05-08
--
-- INSTRUÇÕES DE USO:
--   1. Abra o phpMyAdmin ou o terminal MySQL/MariaDB do XAMPP.
--   2. Execute este arquivo completo para criar o banco e todas as tabelas.
--   Exemplo via terminal:
--     mysql -u root -P 3307 < migrate.sql
-- ===========================================================================


-- ---------------------------------------------------------------------------
-- STEP 1: Cria o banco de dados caso ainda não exista
-- ---------------------------------------------------------------------------
CREATE DATABASE IF NOT EXISTS `instaSenaiDB`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

-- Seleciona o banco para as operações seguintes
USE `instaSenaiDB`;

-- ---------------------------------------------------------------------------
-- STEP 2: Desativa checagem de chaves estrangeiras durante a criação
--         para evitar erros de ordem de criação de tabelas
-- ---------------------------------------------------------------------------
SET FOREIGN_KEY_CHECKS = 0;


-- ===========================================================================
-- TABELA: usuario
-- Armazena todos os usuários da plataforma (login Google ou local).
--
-- Colunas:
--   id           → Identificador único auto-incrementado (PK)
--   email        → E-mail do usuário (único, vindo do Google ou cadastro manual)
--   nome         → Nome completo do usuário
--   nick         → Apelido/username exibido no feed (ex: gerado do e-mail)
--   senha        → Hash da senha para login local (NULL para usuários Google)
--   data_criacao → Data em que o cadastro foi criado
-- ===========================================================================
CREATE TABLE IF NOT EXISTS `usuario` (
    -- Chave primária auto-incrementada
    `id`           INT UNSIGNED    NOT NULL AUTO_INCREMENT,

    -- E-mail único: usado para identificar o usuário e evitar duplicatas no login Google
    `email`        VARCHAR(191)    NOT NULL,

    -- Nome completo recebido do Google ou digitado no cadastro
    `nome`         VARCHAR(150)    NOT NULL,

    -- Apelido exibido no feed (gerado a partir do e-mail no login Google)
    `nick`         VARCHAR(80)     NOT NULL,

    -- Senha hash (bcrypt). NULL quando o usuário se autenticou via Google
    `senha`        VARCHAR(255)    NULL DEFAULT NULL,

    -- Data de criação do cadastro (preenchida pelo MySQL com CURDATE())
    `data_criacao` DATE            NOT NULL,

    -- Definição da chave primária
    PRIMARY KEY (`id`),

    -- Índice único no e-mail: garante que não existam dois usuários com o mesmo e-mail
    UNIQUE KEY `uq_usuario_email` (`email`),

    -- Índice único no nick: evita apelidos duplicados no feed
    UNIQUE KEY `uq_usuario_nick` (`nick`)

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Usuários da plataforma InstaSenai (Google OAuth e login local)';


-- ===========================================================================
-- TABELA: post
-- Armazena as postagens de fotos publicadas pelos usuários.
--
-- Colunas:
--   id           → Identificador único auto-incrementado (PK)
--   id_user      → FK para usuario.id — quem fez o post
--   caminho_foto → Caminho relativo da imagem salva no servidor (ex: uploads/post_1_1715000000.jpg)
--   descricao    → Legenda/texto do post (máx. 255 caracteres)
--   data_criacao → Data de publicação do post
-- ===========================================================================
CREATE TABLE IF NOT EXISTS `post` (
    -- Chave primária auto-incrementada
    `id`           INT UNSIGNED    NOT NULL AUTO_INCREMENT,

    -- Referência ao usuário que criou o post
    `id_user`      INT UNSIGNED    NOT NULL,

    -- Caminho relativo da imagem no servidor (relativo à raiz do projeto)
    -- Exemplo: "uploads/post_3_1715123456.jpg"
    `caminho_foto` VARCHAR(255)    NOT NULL,

    -- Legenda da foto escrita pelo usuário
    `descricao`    VARCHAR(255)    NOT NULL DEFAULT '',

    -- Data de criação do post (preenchida com CURDATE() no momento da inserção)
    `data_criacao` DATE            NOT NULL,

    -- Definição da chave primária
    PRIMARY KEY (`id`),

    -- Índice na FK para acelerar JOINs e consultas por usuário
    INDEX `idx_post_id_user` (`id_user`),

    -- Índice na data para acelerar ordenação do feed (ORDER BY data DESC)
    INDEX `idx_post_data_criacao` (`data_criacao`),

    -- Chave estrangeira: se o usuário for deletado, seus posts são deletados também
    CONSTRAINT `fk_post_usuario`
        FOREIGN KEY (`id_user`)
        REFERENCES `usuario` (`id`)
        ON DELETE CASCADE
        ON UPDATE CASCADE

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Postagens de fotos publicadas pelos usuários no feed';


-- ---------------------------------------------------------------------------
-- STEP 3: Reativa a checagem de chaves estrangeiras
-- ---------------------------------------------------------------------------
SET FOREIGN_KEY_CHECKS = 1;


-- ===========================================================================
-- DADOS DE EXEMPLO (SEED OPCIONAL)
-- Descomente as linhas abaixo se quiser popular o banco para testes.
-- ===========================================================================

/*
-- Usuário de exemplo (sem senha = login via Google)
INSERT INTO `usuario` (`email`, `nome`, `nick`, `senha`, `data_criacao`) VALUES
    ('aluno.teste@aluno.senai.br', 'Aluno Teste',    'aluno.teste',    NULL, CURDATE()),
    ('prof.demo@docente.senai.br', 'Professor Demo',  'prof.demo',      NULL, CURDATE());

-- Posts de exemplo ligados ao usuário 1
INSERT INTO `post` (`id_user`, `caminho_foto`, `descricao`, `data_criacao`) VALUES
    (1, 'uploads/exemplo_post_1.jpg', 'Primeira foto do feed! #SENAI', CURDATE()),
    (1, 'uploads/exemplo_post_2.jpg', 'Aula incrível hoje.',           CURDATE()),
    (2, 'uploads/exemplo_post_3.jpg', 'Bom dia turma!',                CURDATE());
*/


-- ===========================================================================
-- FIM DA MIGRATION
-- ===========================================================================
