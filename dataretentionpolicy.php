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

function dataretentionpolicy_civicrm_navigationMenu(&$menu) {
  $administerMenuId = CRM_Core_DAO::getFieldValue('CRM_Core_DAO_Navigation', 'Administer', 'id', 'name');
  if (!$administerMenuId || empty($menu[$administerMenuId]['child'])) {
    return;
  }
  foreach ($menu[$administerMenuId]['child'] as $id => $item) {
    if ($item['name'] === 'System Settings') {
      $childMenu =& $menu[$administerMenuId]['child'][$id]['child'];
      if (!is_array($childMenu)) {
        $childMenu = [];
      }
      $weight = empty($childMenu) ? 0 : (int) CRM_Utils_Array::value('weight', end($childMenu), 0) + 1;
      $navId = CRM_Core_DAO::singleValueQuery('SELECT COALESCE(MAX(id), 0) + 1 FROM civicrm_navigation');
      $childMenu[] = [
        'attributes' => [
          'label' => E::ts('Data Retention Policy'),
          'name' => 'Data Retention Policy',
          'url' => 'civicrm/admin/dataretentionpolicy/settings',
          'permission' => 'administer CiviCRM',
          'operator' => NULL,
          'separator' => 0,
          'parentID' => $id,
          'navID' => $navId,
          'weight' => $weight,
        ],
      ];
      break;
    }
  }
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
