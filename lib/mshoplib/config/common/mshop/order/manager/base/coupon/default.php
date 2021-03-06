<?php

/**
 * @copyright Copyright (c) Metaways Infosystems GmbH, 2013
 * @license LGPLv3, http://www.arcavias.com/en/license
 */
return array (
	'aggregate' => '
		SELECT "key", COUNT("id") AS "count"
		FROM (
			SELECT DISTINCT :key AS "key", mordbaco."id" AS "id"
			FROM "mshop_order_base_coupon" AS mordbaco
			:joins
			WHERE :cond
			/*-orderby*/ ORDER BY :order /*orderby-*/
			LIMIT :size OFFSET :start
		) AS list
		GROUP BY "key"
	',
	'item' => array (
		'delete' => '
			DELETE FROM "mshop_order_base_coupon"
			WHERE :cond AND siteid = ?
			',
		'insert' => '
			INSERT INTO "mshop_order_base_coupon" (
				"baseid", "siteid", "ordprodid", "code", "mtime", "editor",
				"ctime"
			) VALUES (
				?, ?, ?, ?, ?, ?, ?
			)
		',
		'update' => '
			UPDATE "mshop_order_base_coupon"
			SET "baseid" = ?, "siteid" = ?, "ordprodid" = ?, "code" = ?,
				"mtime" = ?, "editor" = ?
			WHERE "id" = ?
		',
		'search' => '
			SELECT DISTINCT mordbaco."id", mordbaco."baseid",
				mordbaco."siteid", mordbaco."ordprodid", mordbaco."code",
				mordbaco."mtime", mordbaco."editor", mordbaco."ctime"
			FROM "mshop_order_base_coupon" AS mordbaco
			:joins
			WHERE :cond
			/*-orderby*/ ORDER BY :order /*orderby-*/
			LIMIT :size OFFSET :start
		',
		'count' => '
			SELECT COUNT( DISTINCT mordbaco."id" ) AS "count"
			FROM "mshop_order_base_coupon" AS mordbaco
			:joins
			WHERE :cond
		'
	)
);
