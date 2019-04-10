<?php

require_once 'php/config.php';

date_default_timezone_set(TIME_ZONE);

require_once "php/ezSQL/shared/ez_sql_core.php";
if(USE_PDO){
    require_once "php/ezSQL/ez_sql_pdo.php";
}
require_once "php/ezSQL/ez_sql_mysql.php";
require_once 'php/db.php';
require_once 'php/delta.php';

$sql = "CREATE TABLE " . TABLES_PREFIX . "survey (
id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
title text,
description longtext,
date_created timestamp NULL DEFAULT NULL,
status varchar(20) DEFAULT NULL,
email text NULL,
redirect_url text NULL,
daily_limit int NULL,
total_limit int NULL,
PRIMARY KEY  (id)
);";
delta($sql);

$sql = "CREATE TABLE " . TABLES_PREFIX . "question (
id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
survey_id bigint(20) unsigned NOT NULL,
question_type varchar(20) DEFAULT NULL,
question text,
choices longtext,
statistics longtext,
stats longtext,
attachment text NULL,
is_required varchar(1) DEFAULT 'n',
order_by int(11) NOT NULL DEFAULT '9999',
PRIMARY KEY  (id)
);";
delta($sql);

$sql = "CREATE TABLE " . TABLES_PREFIX . "choices (
id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
survey_id int(11) NOT NULL,
question_id int(11) NOT NULL,
choice text,
PRIMARY KEY  (id)
);";

delta($sql);

$sql = "CREATE TABLE " . TABLES_PREFIX . "answers (
id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
survey_id int(11) NOT NULL,
question_id int(11) NOT NULL,
results_id int(11) DEFAULT NULL,
choice_id int(11) DEFAULT NULL,
answer text,
PRIMARY KEY  (id)
);";

delta($sql);

$sql = "CREATE TABLE " . TABLES_PREFIX . "results (
id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
survey_id bigint(20) unsigned NOT NULL,
date_taken timestamp NULL DEFAULT NULL,
ip_address text NULL,
PRIMARY KEY  (id)
);";
delta($sql);

$sql = "CREATE TABLE " . TABLES_PREFIX . "admin (
id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
name text,
email text,
password text,
added_on timestamp NULL DEFAULT NULL,
last_seen timestamp NULL DEFAULT NULL,
permissions text,
status varchar(20) DEFAULT NULL,
PRIMARY KEY  (id)
);";
delta($sql);

