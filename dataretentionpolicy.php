<?php

use CRM_DataRetentionPolicy_ExtensionUtil as E;

function dataretentionpolicy_civicrm_config(&$config) {}

function dataretentionpolicy_civicrm_install() {
  return TRUE;
}

function dataretentionpolicy_civicrm_uninstall() {
  return TRUE;
}

function dataretentionpolicy_civicrm_enable() {
  return TRUE;
}

function dataretentionpolicy_civicrm_disable() {
  return TRUE;
}

function dataretentionpolicy_civicrm_xmlMenu(&$files) {
  if (!is_array($files)) {
    $files = [];
  }
  $files[] = __DIR__ . DIRECTORY_SEPARATOR . 'xml' . DIRECTORY_SEPARATOR . 'Menu' . DIRECTORY_SEPARATOR . 'dataretentionpolicy.xml';
}

function dataretentionpolicy_civicrm_alterSettingsFolders(&$metaDataFolders = NULL) {
  $metaDataFolders[] = __DIR__ . DIRECTORY_SEPARATOR . 'settings';
}

function dataretentionpolicy_civicrm_navigationMenu(&$menu) {
  $path = ['Administer', 'System Settings'];

  $item = [
    'attributes' => [
      'label' => E::ts('Data Retention Policy'),
      'name' => 'data_retention_policy_settings',
      'url' => 'civicrm/admin/dataretentionpolicy/settings?reset=1',
      'permission' => 'administer CiviCRM',
      'operator' => NULL,
      'separator' => 0,
      'active' => 1,
    ],
  ];

  _dataretentionpolicy_insert_navigation_menu($menu, $path, $item);
}

function dataretentionpolicy_civicrm_jobTypes(&$jobTypes) {
  $jobTypes['data_retention_policy_cleanup'] = [
    'name' => 'data_retention_policy_cleanup',
    'label' => E::ts('Apply Data Retention Policies'),
    'description' => E::ts('Delete contacts and related records that exceed configured retention periods.'),
    'is_active' => 1,
    'api_entity' => 'DataRetentionPolicyJob',
    'api_action' => 'run',
  ];
}

function dataretentionpolicy_civicrm_managed(&$entities) {
  $entities[] = [
    'module' => 'uk.co.encircle.dataretentionpolicy',
    'name' => 'Data Retention Policy Job',
    'entity' => 'Job',
    'params' => [
      'version' => 3,
      'name' => 'Data Retention Job',
      'description' => E::ts('Apply configured data retention policies.'),
      'run_frequency' => 'Daily',
      'api_entity' => 'DataRetentionPolicyJob',
      'api_action' => 'run',
      'parameters' => NULL,
      'is_active' => 1,
      'job_type' => 'data_retention_policy_cleanup',
    ],
  ];
}

function _dataretentionpolicy_insert_navigation_menu(&$menu, $path, $item) {
  if (is_string($path)) {
    $path = explode('/', $path);
  }

  if (empty($path)) {
    foreach ($menu as $existing) {
      if (!empty($existing['attributes']['url']) && $existing['attributes']['url'] === $item['attributes']['url']) {
        return FALSE;
      }
    }

    $menu[] = $item;
    return TRUE;
  }

  $part = array_shift($path);
  foreach ($menu as &$entry) {
    if (empty($entry['attributes']['name']) && empty($entry['attributes']['label'])) {
      continue;
    }

    $name = CRM_Utils_Array::value('name', $entry['attributes']);
    $label = CRM_Utils_Array::value('label', $entry['attributes']);

    if ($name === $part || $label === $part) {
      if (!isset($entry['child'])) {
        $entry['child'] = [];
      }

      return _dataretentionpolicy_insert_navigation_menu($entry['child'], $path, $item);
    }
  }

  return FALSE;
}
