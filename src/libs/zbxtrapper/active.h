/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
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

#ifndef ZABBIX_TRAPPER_ACTIVE_H
#define ZABBIX_TRAPPER_ACTIVE_H

#include "zbxcomms.h"
#include "zbxjson.h"
#include "zbxdbhigh.h"

int	send_list_of_active_checks(zbx_socket_t *sock, char *request, const zbx_events_funcs_t *events_cbs,
		int config_timeout, zbx_autoreg_update_host_func_t autoreg_update_host_cb);
int	send_list_of_active_checks_json(zbx_socket_t *sock, struct zbx_json_parse *jp,
		const zbx_events_funcs_t *events_cbs, int config_timeout,
		zbx_autoreg_update_host_func_t autoreg_update_host_cb);
#endif
