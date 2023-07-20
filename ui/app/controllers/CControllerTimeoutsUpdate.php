<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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


class CControllerTimeoutsUpdate extends CController {

	protected function checkInput(): bool {
		$fields = [
			'timeout_zabbix_agent' =>		'required|db config.timeout_zabbix_agent|time_unit '.implode(':', [1, 600]),
			'timeout_simple_check' =>		'required|db config.timeout_simple_check|time_unit '.implode(':', [1, 600]),
			'timeout_snmp_agent' =>			'required|db config.timeout_snmp_agent|time_unit '.implode(':', [1, 600]),
			'timeout_external_check' =>		'required|db config.timeout_external_check|time_unit '.implode(':', [1, 600]),
			'timeout_db_monitor' =>			'required|db config.timeout_db_monitor|time_unit '.implode(':', [1, 600]),
			'timeout_http_agent' =>			'required|db config.timeout_http_agent|time_unit '.implode(':', [1, 600]),
			'timeout_ssh_agent' =>			'required|db config.timeout_ssh_agent|time_unit '.implode(':', [1, 600]),
			'timeout_telnet_agent' =>		'required|db config.timeout_telnet_agent|time_unit '.implode(':', [1, 600]),
			'timeout_script' =>				'required|db config.timeout_script|time_unit '.implode(':', [1, 600]),
			'socket_timeout' =>				'required|db config.socket_timeout|time_unit '.implode(':', [1, 300]),
			'connect_timeout' =>			'required|db config.connect_timeout|time_unit '.implode(':', [1, 30]),
			'media_type_test_timeout' =>	'required|db config.media_type_test_timeout|time_unit '.implode(':', [1, 300]),
			'script_timeout' =>				'required|db config.script_timeout|time_unit '.implode(':', [1, 300]),
			'item_test_timeout' =>			'required|db config.item_test_timeout|time_unit '.implode(':', [1, 600]),
			'report_test_timeout' =>		'required|db config.report_test_timeout|time_unit '.implode(':', [1, 300])
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			switch ($this->getValidationError()) {
				case self::VALIDATION_ERROR:
					$response = new CControllerResponseRedirect(
						(new CUrl('zabbix.php'))->setArgument('action', 'timeouts.edit')
					);

					$response->setFormData($this->getInputAll());
					CMessageHelper::setErrorTitle(_('Cannot update configuration'));

					$this->setResponse($response);
					break;

				case self::VALIDATION_FATAL_ERROR:
					$this->setResponse(new CControllerResponseFatal());
					break;
			}
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_ADMINISTRATION_GENERAL);
	}

	protected function doAction(): void {
		$settings = [
			CSettingsHelper::TIMEOUT_ZABBIX_AGENT => $this->getInput('timeout_zabbix_agent'),
			CSettingsHelper::TIMEOUT_SIMPLE_CHECK => $this->getInput('timeout_simple_check'),
			CSettingsHelper::TIMEOUT_SNMP_AGENT => $this->getInput('timeout_snmp_agent'),
			CSettingsHelper::TIMEOUT_EXTERNAL_CHECK => $this->getInput('timeout_external_check'),
			CSettingsHelper::TIMEOUT_DB_MONITOR => $this->getInput('timeout_db_monitor'),
			CSettingsHelper::TIMEOUT_HTTP_AGENT => $this->getInput('timeout_http_agent'),
			CSettingsHelper::TIMEOUT_SSH_AGENT => $this->getInput('timeout_ssh_agent'),
			CSettingsHelper::TIMEOUT_TELNET_AGENT => $this->getInput('timeout_telnet_agent'),
			CSettingsHelper::TIMEOUT_SCRIPT => $this->getInput('timeout_script'),
			CSettingsHelper::SOCKET_TIMEOUT => $this->getInput('socket_timeout'),
			CSettingsHelper::CONNECT_TIMEOUT => $this->getInput('connect_timeout'),
			CSettingsHelper::MEDIA_TYPE_TEST_TIMEOUT => $this->getInput('media_type_test_timeout'),
			CSettingsHelper::SCRIPT_TIMEOUT => $this->getInput('script_timeout'),
			CSettingsHelper::ITEM_TEST_TIMEOUT => $this->getInput('item_test_timeout'),
			CSettingsHelper::SCHEDULED_REPORT_TEST_TIMEOUT => $this->getInput('report_test_timeout')
		];

		$result = API::Settings()->update($settings);

		$response = new CControllerResponseRedirect(
			(new CUrl('zabbix.php'))->setArgument('action', 'timeouts.edit')
		);

		if ($result) {
			CMessageHelper::setSuccessTitle(_('Configuration updated'));
		}
		else {
			CMessageHelper::setErrorTitle(_('Cannot update configuration'));
			$response->setFormData($this->getInputAll());
		}

		$this->setResponse($response);
	}
}
