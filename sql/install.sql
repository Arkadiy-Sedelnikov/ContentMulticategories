DROP TABLE IF EXISTS `#__contentmulticategories_categories`;
CREATE TABLE IF NOT EXISTS `#__contentmulticategories_categories` (
  `id` int(11) NOT NULL auto_increment,
  `category_id` int(11) NOT NULL,
  `article_id` int(11) NOT NULL,
  PRIMARY KEY  (`id`),
  KEY `category_id` (`category_id`),
  KEY `article_id` (`article_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;