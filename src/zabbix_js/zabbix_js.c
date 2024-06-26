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

#include "zbxlog.h"
#include "zbxgetopt.h"
#include "zbxembed.h"
#include "zbxmutexs.h"
#include "zbxstr.h"
#include "zbxnix.h"
#include "zbxbincommon.h"

ZBX_GET_CONFIG_VAR2(const char *, const char *, zbx_progname, NULL)
static const char	title_message[] = "zabbix_js";
static const char	syslog_app_name[] = "zabbix_js";
static const char	*usage_message[] = {
	"-s script-file", "-p input-param", "[-l log-level]", "[-t timeout]", NULL,
	"-s script-file", "-i input-file", "[-l log-level]", "[-t timeout]", NULL,
	"-h", NULL,
	"-V", NULL,
	NULL	/* end of text */
};

#define ZBX_SERVICE_NAME_LEN	64
char	zabbix_event_source[ZBX_SERVICE_NAME_LEN] = APPLICATION_NAME;
#undef ZBX_SERVICE_NAME_LEN

#define JS_TIMEOUT_MIN		1
#define JS_TIMEOUT_MAX		60
#define JS_TIMEOUT_DEF		ZBX_ES_TIMEOUT
#define JS_TIMEOUT_MIN_STR	ZBX_STR(JS_TIMEOUT_MIN)
#define JS_TIMEOUT_MAX_STR	ZBX_STR(JS_TIMEOUT_MAX)
#define JS_TIMEOUT_DEF_STR	ZBX_STR(JS_TIMEOUT_DEF)

static const char	*help_message[] = {
	"Execute script using Zabbix embedded scripting engine.",
	"",
	"General options:",
	"  -s,--script script-file      Specify the filename of script to execute. Specify - for",
	"                               standard input.",
	"  -i,--input input-file        Specify input parameter file name. Specify - for",
	"                               standard input.",
	"  -p,--param input-param       Specify input parameter",
	"  -l,--loglevel log-level      Specify log level",
	"  -t --timeout timeout         Specify the timeout in seconds. Valid range: " JS_TIMEOUT_MIN_STR "-"
			JS_TIMEOUT_MAX_STR " seconds",
	"                               (default: " JS_TIMEOUT_DEF_STR " seconds)",
	"  -h --help                    Display this help message",
	"  -V --version                 Display version number",
	"",
	"Example:",
	"  zabbix_js -s script-file.js -p example",
	NULL	/* end of text */
};

/* long options */
struct zbx_option	longopts[] =
{
	{"script",			1,	NULL,	's'},
	{"input",			1,	NULL,	'i'},
	{"param",			1,	NULL,	'p'},
	{"loglevel",			1,	NULL,	'l'},
	{"timeout",			1,	NULL,	't'},
	{"help",			0,	NULL,	'h'},
	{"version",			0,	NULL,	'V'},
	{0}
};

/* short options */
static char	shortopts[] = "s:i:p:hVl:t:";

/* end of COMMAND LINE OPTIONS */

static char	*read_file(const char *filename, char **error)
{
	char	buffer[4096];
	int	n, fd;
	char	*data = NULL;
	size_t	data_alloc = 0, data_offset = 0;

	if (0 != strcmp(filename, "-"))
	{
		if (-1 == (fd = open(filename, O_RDONLY)))
		{
			*error = zbx_strdup(NULL, zbx_strerror(errno));
			return NULL;
		}
	}
	else
		fd = STDIN_FILENO;

	while (0 != (n = read(fd, buffer, sizeof(buffer))))
	{
		if (-1 == n)
		{
			if (fd != STDIN_FILENO)
				close(fd);
			zbx_free(data);
			*error = zbx_strdup(NULL, zbx_strerror(errno));
			return NULL;
		}
		zbx_strncpy_alloc(&data, &data_alloc, &data_offset, buffer, n);
	}

	if (fd != STDIN_FILENO)
		close(fd);

	return data;
}

int	main(int argc, char **argv)
{
	int			ret = FAIL, loglevel = LOG_LEVEL_WARNING, timeout = 0;
	char			*script_file = NULL, *input_file = NULL, *param = NULL, ch, *script = NULL,
				*error = NULL, *result = NULL, script_error[MAX_STRING_LEN];
	zbx_config_log_t	log_file_cfg = {NULL, NULL, ZBX_LOG_TYPE_UNDEFINED, 0};

	/* see description of 'optarg' in 'man 3 getopt' */
	char			*zbx_optarg = NULL;

	/* see description of 'optind' in 'man 3 getopt' */
	int			zbx_optind = 0;

	const char		*config_source_ip = NULL;

	zbx_progname = get_program_name(argv[0]);

	zbx_init_library_common(zbx_log_impl, get_zbx_progname, zbx_backtrace);
#ifndef _WINDOWS
	zbx_init_library_nix(get_zbx_progname, NULL);
#endif
	/* parse the command-line */
	while ((char)EOF != (ch = (char)zbx_getopt_long(argc, argv, shortopts, longopts, NULL, &zbx_optarg,
			&zbx_optind)))
	{
		switch (ch)
		{
			case 's':
				if (NULL == script_file)
					script_file = zbx_strdup(NULL, zbx_optarg);
				break;
			case 'i':
				if (NULL == input_file)
					input_file = zbx_strdup(NULL, zbx_optarg);
				break;
			case 'p':
				if (NULL == param)
					param = zbx_strdup(NULL, zbx_optarg);
				break;
			case 'l':
				loglevel = atoi(zbx_optarg);
				break;
			case 't':
				if (FAIL == zbx_is_uint_n_range(zbx_optarg, ZBX_MAX_UINT64_LEN, &timeout,
						sizeof(timeout), JS_TIMEOUT_MIN, JS_TIMEOUT_MAX))
				{
					zbx_error("Invalid timeout, valid range [" JS_TIMEOUT_MIN_STR ":"
							JS_TIMEOUT_MAX_STR "] seconds");
					exit(EXIT_FAILURE);
				}

				break;
			case 'h':
				zbx_print_help(zbx_progname, help_message, usage_message, NULL);
				ret = SUCCEED;
				goto clean;
			case 'V':
				zbx_print_version(title_message);
				ret = SUCCEED;
				goto clean;
			default:
				zbx_print_usage(zbx_progname, usage_message);
				goto clean;
		}
	}

	if (SUCCEED != zbx_locks_create(&error))
	{
		zbx_error("cannot create locks: %s", error);
		goto clean;
	}

	if (SUCCEED != zbx_open_log(&log_file_cfg, loglevel, syslog_app_name, zabbix_event_source, &error))
	{
		zbx_error("cannot open log: %s", error);
		goto clean;
	}

	if (NULL == script_file || (NULL == input_file && NULL == param))
	{
		zbx_print_usage(zbx_progname, usage_message);
		goto close;
	}

	if (NULL != input_file && NULL != param)
	{
		zbx_error("input and script options are mutually exclusive");
		goto close;
	}

	if (0 == strcmp(script_file, "-") && NULL != input_file && 0 == strcmp(input_file, "-"))
	{
		zbx_error("cannot read script and input parameters from standard input at the same time");
		goto close;
	}

	if (NULL == (script = read_file(script_file, &error)))
	{
		if (NULL != error)
			zbx_error("cannot read script file: %s", error);
		else
			zbx_error("cannot use empty script file");

		goto close;
	}

	if (NULL != input_file)
	{
		if (NULL == (param = read_file(input_file, &error)))
		{
			if (NULL != error)
				zbx_error("cannot read input file: %s", error);
			else
				zbx_error("cannot use empty input file");

			goto close;
		}
	}

	if (FAIL == zbx_es_execute_command(script, param, timeout, config_source_ip, &result, script_error,
			sizeof(script_error), NULL))
	{
		zbx_error("error executing script:\n%s", script_error);
		goto close;
	}
	ret = SUCCEED;
	printf("\n%s\n", result);
close:
	zbx_close_log();
#ifndef _WINDOWS
	zbx_locks_destroy();
#endif
clean:
	zbx_free(result);
	zbx_free(error);
	zbx_free(script);
	zbx_free(script_file);
	zbx_free(input_file);
	zbx_free(param);

	return SUCCEED == ret ? EXIT_SUCCESS : EXIT_FAILURE;
}
