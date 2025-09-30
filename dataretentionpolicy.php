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
