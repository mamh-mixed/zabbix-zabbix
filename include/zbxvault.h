/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

#ifndef ZABBIX_ZBXVAULT_H
#define ZABBIX_ZBXVAULT_H

#include "common.h"
#include "zbxjson.h"
#include "zbxalgo.h"

typedef	int (*zbx_vault_kvs_get_cb_t)(const char *path, zbx_hashset_t *kvs, char *vault_url, char *token, long timeout,
		char **error);
typedef	int (*zbx_vault_init_db_credentials_cb_t)(char *vault_url, char *token, long timeout, const char *db_path,
		char **dbuser, char **dbpassword, char **error);

void	zbx_vault_init_cb(zbx_vault_kvs_get_cb_t vault_kvs_get_cb,
		zbx_vault_init_db_credentials_cb_t vault_init_db_credentials);
int	zbx_vault_init_token_from_env(char **error);
int	zbx_vault_init_db_credentials(char **error);
int	zbx_vault_kvs_get(const char *path, zbx_hashset_t *kvs, char **error);

#endif
