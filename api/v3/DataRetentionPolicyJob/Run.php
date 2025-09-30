<?php

use CRM_DataRetentionPolicy_ExtensionUtil as E;

function civicrm_api3_data_retention_policy_job_run($params) {
  $processor = new CRM_DataRetentionPolicy_Service_RetentionProcessor();
  $results = $processor->applyPolicies();

  $messages = [];
  $total = 0;
  foreach ($results as $entity => $count) {
    $messages[] = sprintf('%s: %d', $entity, $count);
    $total += $count;
  }

  return civicrm_api3_create_success([
    'total_deleted' => $total,
    'details' => $results,
    'message' => E::ts('Deleted records - %1', [1 => implode(', ', $messages)]),
  ], $params, 'DataRetentionPolicyJob', 'run');
}
