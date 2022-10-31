<?php
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


use Zabbix\Core\{
	CModule,
	CWidget
};

class CDashboardHelper {

	/**
	 * Get dashboard owner name.
	 */
	public static function getOwnerName(string $userid): string {
		$users = API::User()->get([
			'output' => ['name', 'surname', 'username'],
			'userids' => $userid
		]);

		return $users ? getUserFullname($users[0]) : _('Inaccessible user');
	}

	/**
	 * Update editable flag.
	 *
	 * @static
	 *
	 * @param array $dashboards  An associative array of the dashboards.
	 */
	public static function updateEditableFlag(array &$dashboards): void {
		$dashboards_rw = API::Dashboard()->get([
			'output' => [],
			'dashboardids' => array_keys($dashboards),
			'editable' => true,
			'preservekeys' => true
		]);

		foreach ($dashboards as $dashboardid => &$dashboard) {
			$dashboard['editable'] = array_key_exists($dashboardid, $dashboards_rw);
		}
		unset($dashboard);
	}

	/**
	 * Prepare widget pages for dashboard grid.
	 */
	public static function preparePagesForGrid(array $pages, ?string $templateid, bool $with_rf_rate): array {
		if (!$pages) {
			return [];
		}

		$grid_pages = [];

		foreach ($pages as $page) {
			$grid_page_widgets = [];

			CArrayHelper::sort($page['widgets'], ['y', 'x']);

			foreach ($page['widgets'] as $widget_data) {
				/** @var CWidget $widget */
				$widget = APP::ModuleManager()->getModule($widget_data['type']);

				if ($widget === null || $widget->getType() !== CModule::TYPE_WIDGET
						|| ($templateid !== null && !$widget->hasTemplateSupport())) {
					continue;
				}

				$widgetid = $widget_data['widgetid'];
				$fields_orig = self::convertWidgetFields($widget_data['fields']);

				// Transforms corrupted data to default values.
				$widget_form = $widget->getForm($fields_orig, $templateid);
				$widget_form->validate();
				$values = $widget_form->getFieldsValues();

				if ($with_rf_rate) {
					$rf_rate = (int) CProfile::get('web.dashboard.widget.rf_rate', -1, $widgetid);

					if ($rf_rate == -1) {
						if ($templateid === null) {
							$rf_rate = $values['rf_rate'] == -1 ? $widget->getDefaultRefreshRate() : $values['rf_rate'];
						}
						else {
							$rf_rate = $widget->getDefaultRefreshRate();
						}
					}
				}
				else {
					$rf_rate = 0;
				}

				$grid_page_widgets[] = [
					'widgetid' => $widgetid,
					'type' => $widget_data['type'],
					'name' => $widget_data['name'],
					'view_mode' => $widget_data['view_mode'],
					'pos' => [
						'x' => (int) $widget_data['x'],
						'y' => (int) $widget_data['y'],
						'width' => (int) $widget_data['width'],
						'height' => (int) $widget_data['height']
					],
					'rf_rate' => $rf_rate,
					'form' => $widget_form,
					'fields' => $fields_orig
				];
			}

			$grid_pages[] = [
				'dashboard_pageid' => $page['dashboard_pageid'],
				'name' => $page['name'],
				'display_period' => $page['display_period'],
				'widgets' => $grid_page_widgets
			];
		}

		return $grid_pages;
	}

	/**
	 * Get widget pages with inaccessible fields unset.
	 *
	 * @static
	 *
	 * @param array $pages
	 * @param array $pages[]['widgets']
	 * @param array $pages[]['widgets'][]['fields']
	 * @param array $pages[]['widgets'][]['fields'][]['type']
	 * @param array $pages[]['widgets'][]['fields'][]['value']
	 *
	 * @return array
	 */
	public static function unsetInaccessibleFields(array $pages): array {
		$ids = [
			ZBX_WIDGET_FIELD_TYPE_GROUP => [],
			ZBX_WIDGET_FIELD_TYPE_HOST => [],
			ZBX_WIDGET_FIELD_TYPE_ITEM => [],
			ZBX_WIDGET_FIELD_TYPE_ITEM_PROTOTYPE => [],
			ZBX_WIDGET_FIELD_TYPE_GRAPH => [],
			ZBX_WIDGET_FIELD_TYPE_GRAPH_PROTOTYPE => [],
			ZBX_WIDGET_FIELD_TYPE_MAP => [],
			ZBX_WIDGET_FIELD_TYPE_SERVICE => [],
			ZBX_WIDGET_FIELD_TYPE_SLA => []
		];

		foreach ($pages as $p_index => $page) {
			foreach ($page['widgets'] as $w_index => $widget) {
				foreach ($widget['fields'] as $f_index => $field) {
					if (array_key_exists($field['type'], $ids)) {
						$ids[$field['type']][$field['value']][] = ['p' => $p_index, 'w' => $w_index, 'f' => $f_index];
					}
				}
			}
		}

		$inaccessible_indexes = [];

		if ($ids[ZBX_WIDGET_FIELD_TYPE_GROUP]) {
			$db_groups = API::HostGroup()->get([
				'output' => [],
				'groupids' => array_keys($ids[ZBX_WIDGET_FIELD_TYPE_GROUP]),
				'preservekeys' => true
			]);

			foreach ($ids[ZBX_WIDGET_FIELD_TYPE_GROUP] as $groupid => $indexes) {
				if (!array_key_exists($groupid, $db_groups)) {
					$inaccessible_indexes = array_merge($inaccessible_indexes, $indexes);
				}
			}
		}

		if ($ids[ZBX_WIDGET_FIELD_TYPE_HOST]) {
			$db_hosts = API::Host()->get([
				'output' => [],
				'hostids' => array_keys($ids[ZBX_WIDGET_FIELD_TYPE_HOST]),
				'preservekeys' => true
			]);

			foreach ($ids[ZBX_WIDGET_FIELD_TYPE_HOST] as $hostid => $indexes) {
				if (!array_key_exists($hostid, $db_hosts)) {
					$inaccessible_indexes = array_merge($inaccessible_indexes, $indexes);
				}
			}
		}

		if ($ids[ZBX_WIDGET_FIELD_TYPE_ITEM]) {
			$db_items = API::Item()->get([
				'output' => [],
				'itemids' => array_keys($ids[ZBX_WIDGET_FIELD_TYPE_ITEM]),
				'webitems' => true,
				'preservekeys' => true
			]);

			foreach ($ids[ZBX_WIDGET_FIELD_TYPE_ITEM] as $itemid => $indexes) {
				if (!array_key_exists($itemid, $db_items)) {
					$inaccessible_indexes = array_merge($inaccessible_indexes, $indexes);
				}
			}
		}

		if ($ids[ZBX_WIDGET_FIELD_TYPE_ITEM_PROTOTYPE]) {
			$db_item_prototypes = API::ItemPrototype()->get([
				'output' => [],
				'itemids' => array_keys($ids[ZBX_WIDGET_FIELD_TYPE_ITEM_PROTOTYPE]),
				'preservekeys' => true
			]);

			foreach ($ids[ZBX_WIDGET_FIELD_TYPE_ITEM_PROTOTYPE] as $item_prototypeid => $indexes) {
				if (!array_key_exists($item_prototypeid, $db_item_prototypes)) {
					$inaccessible_indexes = array_merge($inaccessible_indexes, $indexes);
				}
			}
		}

		if ($ids[ZBX_WIDGET_FIELD_TYPE_GRAPH]) {
			$db_graphs = API::Graph()->get([
				'output' => [],
				'graphids' => array_keys($ids[ZBX_WIDGET_FIELD_TYPE_GRAPH]),
				'preservekeys' => true
			]);

			foreach ($ids[ZBX_WIDGET_FIELD_TYPE_GRAPH] as $graphid => $indexes) {
				if (!array_key_exists($graphid, $db_graphs)) {
					$inaccessible_indexes = array_merge($inaccessible_indexes, $indexes);
				}
			}
		}

		if ($ids[ZBX_WIDGET_FIELD_TYPE_GRAPH_PROTOTYPE]) {
			$db_graph_prototypes = API::GraphPrototype()->get([
				'output' => [],
				'graphids' => array_keys($ids[ZBX_WIDGET_FIELD_TYPE_GRAPH_PROTOTYPE]),
				'preservekeys' => true
			]);

			foreach ($ids[ZBX_WIDGET_FIELD_TYPE_GRAPH_PROTOTYPE] as $graph_prototypeid => $indexes) {
				if (!array_key_exists($graph_prototypeid, $db_graph_prototypes)) {
					$inaccessible_indexes = array_merge($inaccessible_indexes, $indexes);
				}
			}
		}

		if ($ids[ZBX_WIDGET_FIELD_TYPE_MAP]) {
			$db_sysmaps = API::Map()->get([
				'output' => [],
				'sysmapids' => array_keys($ids[ZBX_WIDGET_FIELD_TYPE_MAP]),
				'preservekeys' => true
			]);

			foreach ($ids[ZBX_WIDGET_FIELD_TYPE_MAP] as $sysmapid => $indexes) {
				if (!array_key_exists($sysmapid, $db_sysmaps)) {
					$inaccessible_indexes = array_merge($inaccessible_indexes, $indexes);
				}
			}
		}

		if ($ids[ZBX_WIDGET_FIELD_TYPE_SERVICE]) {
			$db_services = API::Service()->get([
				'output' => [],
				'serviceids' => array_keys($ids[ZBX_WIDGET_FIELD_TYPE_SERVICE]),
				'preservekeys' => true
			]);

			foreach ($ids[ZBX_WIDGET_FIELD_TYPE_SERVICE] as $serviceid => $indexes) {
				if (!array_key_exists($serviceid, $db_services)) {
					$inaccessible_indexes = array_merge($inaccessible_indexes, $indexes);
				}
			}
		}

		if ($ids[ZBX_WIDGET_FIELD_TYPE_SLA]) {
			$db_slas = API::Sla()->get([
				'output' => [],
				'slaids' => array_keys($ids[ZBX_WIDGET_FIELD_TYPE_SLA]),
				'preservekeys' => true
			]);

			foreach ($ids[ZBX_WIDGET_FIELD_TYPE_SLA] as $slaid => $indexes) {
				if (!array_key_exists($slaid, $db_slas)) {
					$inaccessible_indexes = array_merge($inaccessible_indexes, $indexes);
				}
			}
		}

		foreach ($inaccessible_indexes as $index) {
			unset($pages[$index['p']]['widgets'][$index['w']]['fields'][$index['f']]);
		}

		return $pages;
	}

	/**
	 * Converts fields, received from API to key/value format.
	 *
	 * @static
	 *
	 * @param array $fields  fields as received from API
	 *
	 * @return array
	 */
	public static function convertWidgetFields(array $fields): array {
		$ret = [];

		foreach ($fields as $field) {
			if (array_key_exists($field['name'], $ret)) {
				$ret[$field['name']] = (array) $ret[$field['name']];
				$ret[$field['name']][] = $field['value'];
			}
			else {
				$ret[$field['name']] = $field['value'];
			}
		}

		return $ret;
	}

	/**
	 * Checks, if any of widgets needs time selector.
	 *
	 * @static
	 *
	 * @param array $pages
	 *
	 * @return bool
	 */
	public static function hasTimeSelector(array $pages): bool {
		foreach ($pages as $page) {
			foreach ($page['widgets'] as $widget_data) {
				$widget = App::ModuleManager()->getModule($widget_data['type']);

				if ($widget !== null && $widget->getType() === CModule::TYPE_WIDGET
						&& $widget->usesTimeSelector($widget_data['fields'])) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Validate input parameters of dashboard pages.
	 *
	 * @static
	 *
	 * @var array  $dashboard_pages
	 * @var array  $dashboard_pages[]['widgets']
	 * @var string $dashboard_pages[]['widgets'][]['widgetid']       (optional)
	 * @var array  $dashboard_pages[]['widgets'][]['pos']
	 * @var int    $dashboard_pages[]['widgets'][]['pos']['x']
	 * @var int    $dashboard_pages[]['widgets'][]['pos']['y']
	 * @var int    $dashboard_pages[]['widgets'][]['pos']['width']
	 * @var int    $dashboard_pages[]['widgets'][]['pos']['height']
	 * @var string $dashboard_pages[]['widgets'][]['type']
	 * @var string $dashboard_pages[]['widgets'][]['name']
	 * @var string $dashboard_pages[]['widgets'][]['fields']         (optional) JSON object
	 *
	 * @return array  Widgets and/or errors.
	 */
	public static function validateDashboardPages(array $dashboard_pages, string $templateid = null): array {
		$errors = [];

		foreach ($dashboard_pages as $dashboard_page_index => &$dashboard_page) {
			$dashboard_page_errors = [];

			foreach (['name', 'display_period'] as $field) {
				if (!array_key_exists($field, $dashboard_page)) {
					$dashboard_page_errors[] = _s('Invalid parameter "%1$s": %2$s.', 'pages['.$dashboard_page_index.']',
						_s('the parameter "%1$s" is missing', $field)
					);
				}
			}

			if ($dashboard_page_errors) {
				$errors = array_merge($errors, $dashboard_page_errors);

				break;
			}

			if (!array_key_exists('widgets', $dashboard_page)) {
				$dashboard_page['widgets'] = [];
			}

			foreach ($dashboard_page['widgets'] as $widget_index => &$widget_data) {
				$widget = APP::ModuleManager()->getModule($widget_data['type']);

				if ($widget === null || $widget->getType() !== CModule::TYPE_WIDGET
						|| ($templateid !== null && !$widget->hasTemplateSupport())) {
					continue;
				}

				$widget_errors = [];

				if (!array_key_exists('pos', $widget_data)) {
					$widget_errors[] = _s('Invalid parameter "%1$s": %2$s.',
						'pages['.$dashboard_page_index.'][widgets]['.$widget_index.']',
						_s('the parameter "%1$s" is missing', 'pos')
					);
				}
				else {
					foreach (['x', 'y', 'width', 'height'] as $field) {
						if (!is_array($widget_data['pos']) || !array_key_exists($field, $widget_data['pos'])) {
							$widget_errors[] = _s('Invalid parameter "%1$s": %2$s.',
								'pages['.$dashboard_page_index.'][widgets]['.$widget_index.'][pos]',
								_s('the parameter "%1$s" is missing', $field)
							);
						}
					}
				}

				foreach (['type', 'name', 'view_mode'] as $field) {
					if (!array_key_exists($field, $widget_data)) {
						$widget_errors[] = _s('Invalid parameter "%1$s": %2$s.',
							'pages['.$dashboard_page_index.'][widgets]['.$widget_index.']',
							_s('the parameter "%1$s" is missing', $field)
						);
					}
				}

				if ($widget_errors) {
					$errors = array_merge($errors, $widget_errors);

					break 2;
				}

				$widget_fields = array_key_exists('fields', $widget_data) ? $widget_data['fields'] : [];
				$widget_data['form'] = $widget->getForm($widget_fields, $templateid);
				unset($widget_data['fields']);

				if ($widget_errors = $widget_data['form']->validate()) {
					if ($widget_data['name'] === '') {
						$widget_name = $widget->getDefaultName();
					}
					else {
						$widget_name = $widget_data['name'];
					}

					foreach ($widget_errors as $error) {
						$errors[] = _s('Cannot save widget "%1$s".', $widget_name).' '.$error;
					}
				}
			}
			unset($widget_data);
		}
		unset($dashboard_page);

		return [
			'dashboard_pages' => $dashboard_pages,
			'errors' => $errors
		];
	}

	/**
	 * Prepare data for cloning template dashboards.
	 * Replace item and graph ids to new ids.
	 *
	 * @static
	 *
	 * @param array  $dashboards  Dashboards array.
	 * @param string $templateid  New template id.
	 *
	 * @return array
	 */
	public static function prepareForClone(array $dashboards, $templateid): array {
		foreach ($dashboards as &$dashboard) {
			unset($dashboard['dashboardid'], $dashboard['uuid']);

			$dashboard['templateid'] = $templateid;

			foreach ($dashboard['pages'] as &$dashboard_page) {
				unset($dashboard_page['dashboard_pageid']);

				foreach ($dashboard_page['widgets'] as &$widget) {
					unset($widget['widgetid']);

					$items = [];
					$graphs = [];

					foreach ($widget['fields'] as $field) {
						switch ($field['type']) {
							case ZBX_WIDGET_FIELD_TYPE_ITEM:
							case ZBX_WIDGET_FIELD_TYPE_ITEM_PROTOTYPE:
								$items[$field['value']] = true;
								break;

							case ZBX_WIDGET_FIELD_TYPE_GRAPH:
							case ZBX_WIDGET_FIELD_TYPE_GRAPH_PROTOTYPE:
								$graphs[$field['value']] = true;
								break;
						}
					}

					if ($items) {
						$db_items = DBselect(
							'SELECT src.itemid AS srcid,dest.itemid as destid'.
							' FROM items dest,items src'.
							' WHERE dest.key_=src.key_'.
								' AND dest.hostid='.zbx_dbstr($templateid).
								' AND '.dbConditionInt('src.itemid', array_keys($items))
						);
						while ($db_item = DBfetch($db_items)) {
							$items[$db_item['srcid']] = $db_item['destid'];
						}
					}

					if ($graphs) {
						$db_graphs = DBselect(
							'SELECT src.graphid AS srcid,dest.graphid as destid'.
							' FROM graphs dest,graphs src,graphs_items destgi,items desti'.
							' WHERE dest.name=src.name'.
								' AND destgi.graphid=dest.graphid'.
								' AND destgi.itemid=desti.itemid'.
								' AND desti.hostid='.zbx_dbstr($templateid).
								' AND '.dbConditionInt('src.graphid', array_keys($graphs))
						);
						while ($db_graph = DBfetch($db_graphs)) {
							$graphs[$db_graph['srcid']] = $db_graph['destid'];
						}
					}

					foreach ($widget['fields'] as &$field) {
						switch ($field['type']) {
							case ZBX_WIDGET_FIELD_TYPE_ITEM:
							case ZBX_WIDGET_FIELD_TYPE_ITEM_PROTOTYPE:
								$field['value'] = $items[$field['value']];
								break;

							case ZBX_WIDGET_FIELD_TYPE_GRAPH:
							case ZBX_WIDGET_FIELD_TYPE_GRAPH_PROTOTYPE:
								$field['value'] = $graphs[$field['value']];
								break;
						}
					}
					unset($field);
				}
				unset ($widget);
			}
			unset($dashboard_page);
		}
		unset($dashboard);

		return $dashboards;
	}
}
