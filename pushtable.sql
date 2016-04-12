CREATE TABLE `dbpush_queries` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `event_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `original_query` longtext COLLATE utf8_unicode_ci,
  `pushed` enum('yes','no') COLLATE utf8_unicode_ci NOT NULL DEFAULT 'no',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=0 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;


// CREATE TRIGGER table_x_bi BEFORE INSERT ON table_x FOR EACH ROW BEGIN DECLARE original_query TEXT; SET original_query = (SELECT info FROM INFORMATION_SCHEMA.PROCESSLIST WHERE id = CONNECTION_ID()); INSERT INTO dbpush_queries (original_query) VALUES (original_query); END //
// CREATE TRIGGER table_x_bu BEFORE UPDATE ON table_x FOR EACH ROW BEGIN DECLARE original_query TEXT; SET original_query = (SELECT info FROM INFORMATION_SCHEMA.PROCESSLIST WHERE id = CONNECTION_ID()); INSERT INTO dbpush_queries (original_query) VALUES (original_query); END //
// CREATE TRIGGER table_x_bd BEFORE DELETE ON table_x FOR EACH ROW BEGIN DECLARE original_query TEXT; SET original_query = (SELECT info FROM INFORMATION_SCHEMA.PROCESSLIST WHERE id = CONNECTION_ID()); INSERT INTO dbpush_queries (original_query) VALUES (original_query); END //
