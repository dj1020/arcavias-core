<?php

/**
 * @copyright Copyright (c) Metaways Infosystems GmbH, 2012
 * @license LGPLv3, http://www.arcavias.com/en/license
 */

return array(
	'item' => array(
		'aggregate' => '
			SELECT "key", COUNT("id") AS "count"
			FROM (
				SELECT DISTINCT :key AS "key", mprili."id" AS "id"
				FROM "mshop_price_list" AS mprili
				:joins
				WHERE :cond
				/*-orderby*/ ORDER BY :order /*orderby-*/
				LIMIT :size OFFSET :start
			) AS list
			GROUP BY "key"
		',
		'delete' => '
			DELETE FROM "mshop_price_list"
			WHERE :cond AND siteid = ?
		',
		'getposmax' => '
			SELECT MAX( "pos" ) AS pos
			FROM "mshop_price_list"
			WHERE "siteid" = ? AND "parentid" = ? AND "typeid" = ?
				AND "domain" = ?
		',
		'insert' => '
			INSERT INTO "mshop_price_list" (
				"parentid", "siteid", "typeid", "domain", "refid", "start",
				"end", "config", "pos", "status", "mtime", "editor", "ctime"
			) VALUES (
				?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
			)
		',
		'update' => '
			UPDATE "mshop_price_list"
			SET "parentid"=?, "siteid" = ?, "typeid" = ?, "domain" = ?,
				"refid" = ?, "start" = ?, "end" = ?, "config" = ?, "pos" = ?,
				"status" = ?, "mtime" = ?, "editor" = ?
			WHERE "id" = ?
		',
		'updatepos' => '
			UPDATE "mshop_price_list"
				SET "pos" = ?, "mtime" = ?, "editor" = ?
			WHERE "id" = ?
		',
		'move' => '
			UPDATE "mshop_price_list"
				SET "pos" = "pos" + ?, "mtime" = ?, "editor" = ?
			WHERE "siteid" = ? AND "parentid" = ? AND "typeid" = ?
				AND "domain" = ? AND "pos" >= ?
		',
		'search' => '
			SELECT DISTINCT mprili."id", mprili."parentid", mprili."siteid",
				mprili."typeid", mprili."domain", mprili."refid",
				mprili."start", mprili."end", mprili."config", mprili."pos",
				mprili."status", mprili."mtime", mprili."editor",
				mprili."ctime"
			FROM "mshop_price_list" AS mprili
			:joins
			WHERE :cond
			/*-orderby*/ ORDER BY :order /*orderby-*/
			LIMIT :size OFFSET :start
		',
		'count' => '
			SELECT COUNT(*) AS "count"
			FROM (
				SELECT DISTINCT mprili."id"
				FROM "mshop_price_list" AS mprili
				:joins
				WHERE :cond
				LIMIT 10000 OFFSET 0
			) AS list
		',
	),
);
