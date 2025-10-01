<?php

class CRM_DataRetentionPolicy_Service_RetentionProcessor {

  public function applyPolicies() {
    $settings = Civi::settings();
    $results = [];

    $this->logAction('job_start', 'DataRetentionPolicyJob', NULL, ['time' => date('c')]);

    foreach ($this->getEntityConfigurations($settings) as $config) {
      $amount = (int) $settings->get($config['amount_setting']);
      $unit = $settings->get($config['unit_setting']);
      if ($amount <= 0) {
        $results[$config['entity']] = 0;
        continue;
      }

      $cutoff = $this->calculateCutoffDate($amount, $unit);
      if ($cutoff === NULL) {
        $results[$config['entity']] = 0;
        continue;
      }

      $count = $this->deleteExpiredRecords($config, $cutoff);
      $results[$config['entity']] = $count;
    }

    if ($this->shouldCleanCustomData($settings)) {
      $deletedCustom = $this->cleanOrphanCustomData();
      $results['Custom data orphans'] = $deletedCustom;
    }

    $purgedLogs = $this->purgeAuditLog($settings);
    $results['Audit log purge'] = $purgedLogs;

    $this->logAction('job_complete', 'DataRetentionPolicyJob', NULL, ['time' => date('c'), 'results' => $results]);

    return $results;
  }

  protected function deleteExpiredRecords(array $config, DateTime $cutoff) {
    $ids = $this->getIdsToDelete($config, $cutoff);
    $count = 0;
    foreach ($ids as $id) {
      $snapshot = $this->getRecordSnapshot($config['api_entity'], $id);
      $params = ['id' => $id];
      if (!empty($config['api_params'])) {
        $params = array_merge($params, $config['api_params']);
      }
      try {
        civicrm_api3($config['api_entity'], 'delete', $params);
        $count++;
        $context = [
          'cutoff' => $cutoff->format('Y-m-d H:i:s'),
          'api_entity' => $config['api_entity'],
        ];
        if ($snapshot !== NULL) {
          $context['record'] = $snapshot;
        }
        $this->logAction('delete', $config['entity'], $id, $context);
      }
      catch (CiviCRM_API3_Exception $e) {
        Civi::log()->error('Data Retention Policy failed to delete record', [
          'entity' => $config['entity'],
          'id' => $id,
          'message' => $e->getMessage(),
        ]);
        $this->logAction('delete_failed', $config['entity'], $id, [
          'message' => $e->getMessage(),
          'api_entity' => $config['api_entity'],
        ]);
      }
    }
    return $count;
  }

  protected function getIdsToDelete(array $config, DateTime $cutoff) {
    $params = [1 => [$cutoff->format('Y-m-d H:i:s'), 'String']];
    $sql = sprintf(
      'SELECT %s AS record_id FROM %s WHERE %s IS NOT NULL AND %s < %%1 AND %s',
      $config['id_field'],
      $config['table'],
      $config['date_expression'],
      $config['date_expression'],
      $config['additional_where']
    );

    $dao = CRM_Core_DAO::executeQuery($sql, $params);
    $ids = [];
    while ($dao->fetch()) {
      $ids[] = $dao->record_id;
    }
    return $ids;
  }

  protected function getEntityConfigurations($settings = NULL) {
    if ($settings === NULL) {
      $settings = Civi::settings();
    }

    $contactDateSource = $settings->get('data_retention_contact_date_source');
    if ($contactDateSource !== 'login') {
      $contactDateSource = 'activity';
    }
    $contactDateExpression = 'COALESCE(last_activity_date, modified_date, created_date)';
    if ($contactDateSource === 'login') {
      $contactDateExpression = 'COALESCE((SELECT MAX(log_date) FROM civicrm_uf_match uf WHERE uf.contact_id = civicrm_contact.id), last_activity_date, modified_date, created_date)';
    }

    return [
      [
        'amount_setting' => 'data_retention_contact_years',
        'unit_setting' => 'data_retention_contact_unit',
        'entity' => 'Contact',
        'api_entity' => 'Contact',
        'table' => 'civicrm_contact',
        'id_field' => 'id',
        'date_expression' => $contactDateExpression,
        'additional_where' => 'is_deleted = 0',
      ],
      [
        'amount_setting' => 'data_retention_contact_trash_days',
        'unit_setting' => 'data_retention_contact_trash_unit',
        'entity' => 'Contact (trash)',
        'api_entity' => 'Contact',
        'table' => 'civicrm_contact',
        'id_field' => 'id',
        'date_expression' => 'modified_date',
        'additional_where' => 'is_deleted = 1',
        'api_params' => ['skip_undelete' => 1],
      ],
      [
        'amount_setting' => 'data_retention_participant_years',
        'unit_setting' => 'data_retention_participant_unit',
        'entity' => 'Participant',
        'api_entity' => 'Participant',
        'table' => 'civicrm_participant',
        'id_field' => 'id',
        'date_expression' => 'COALESCE(modified_date, register_date)',
        'additional_where' => '1',
      ],
      [
        'amount_setting' => 'data_retention_contribution_years',
        'unit_setting' => 'data_retention_contribution_unit',
        'entity' => 'Contribution',
        'api_entity' => 'Contribution',
        'table' => 'civicrm_contribution',
        'id_field' => 'id',
        'date_expression' => 'COALESCE(receive_date, modified_date, created_date)',
        'additional_where' => '1',
      ],
      [
        'amount_setting' => 'data_retention_membership_years',
        'unit_setting' => 'data_retention_membership_unit',
        'entity' => 'Membership',
        'api_entity' => 'Membership',
        'table' => 'civicrm_membership',
        'id_field' => 'id',
        'date_expression' => 'COALESCE(modified_date, end_date, start_date, join_date)',
        'additional_where' => '1',
      ],
    ];
  }

  protected function calculateCutoffDate($amount, $unit) {
    $interval = $this->createInterval($amount, $unit);
    if ($interval === NULL) {
      return NULL;
    }

    $cutoff = new DateTime('now', new DateTimeZone('UTC'));
    $cutoff->sub($interval);
    return $cutoff;
  }

  protected function createInterval($amount, $unit) {
    $amount = (int) $amount;
    if ($amount <= 0) {
      return NULL;
    }

    $unit = strtolower((string) $unit);
    $spec = NULL;

    switch ($unit) {
      case 'day':
      case 'days':
        $spec = sprintf('P%dD', $amount);
        break;

      case 'week':
      case 'weeks':
        $spec = sprintf('P%dW', $amount);
        break;

      case 'month':
      case 'months':
        $spec = sprintf('P%dM', $amount);
        break;

      case 'year':
      case 'years':
      default:
        $spec = sprintf('P%dY', $amount);
        break;
    }

    try {
      return new DateInterval($spec);
    }
    catch (Exception $e) {
      Civi::log()->error('Data Retention Policy failed to create interval', [
        'amount' => $amount,
        'unit' => $unit,
        'message' => $e->getMessage(),
      ]);
      return NULL;
    }
  }

  protected function shouldCleanCustomData($settings) {
    return (bool) $settings->get('data_retention_clean_orphan_custom_data');
  }

  protected function cleanOrphanCustomData() {
    $sql = "SELECT id, table_name, extends FROM civicrm_custom_group WHERE table_name IS NOT NULL AND extends IS NOT NULL";
    $dao = CRM_Core_DAO::executeQuery($sql);
    $totalDeleted = 0;

    while ($dao->fetch()) {
      $tableName = $dao->table_name;
      $extends = $dao->extends;
      $entityTable = $this->mapExtendsToTable($extends);

      if (!$tableName || !$entityTable) {
        continue;
      }

      if (!CRM_Core_DAO::checkTableExists($tableName) || !CRM_Core_DAO::checkTableExists($entityTable)) {
        continue;
      }

      $sqlDelete = "DELETE orphan FROM {$tableName} AS orphan LEFT JOIN {$entityTable} AS entity ON orphan.entity_id = entity.id WHERE entity.id IS NULL";
      $deleteDao = CRM_Core_DAO::executeQuery($sqlDelete);
      $deleted = (int) $deleteDao->rowCount();

      if ($deleted > 0) {
        $totalDeleted += $deleted;
        $this->logAction('delete_orphan_custom_data', $tableName, NULL, [
          'extends' => $extends,
          'deleted' => $deleted,
        ]);
      }
    }

    if ($totalDeleted === 0) {
      $this->logAction('delete_orphan_custom_data', 'custom_data', NULL, ['deleted' => 0]);
    }

    return $totalDeleted;
  }

  protected function mapExtendsToTable($extends) {
    $map = [
      'Contact' => 'civicrm_contact',
      'Individual' => 'civicrm_contact',
      'Organization' => 'civicrm_contact',
      'Household' => 'civicrm_contact',
      'Activity' => 'civicrm_activity',
      'Contribution' => 'civicrm_contribution',
      'Membership' => 'civicrm_membership',
      'Participant' => 'civicrm_participant',
      'Event' => 'civicrm_event',
      'Case' => 'civicrm_case',
      'Grant' => 'civicrm_grant',
      'Pledge' => 'civicrm_pledge',
      'PledgePayment' => 'civicrm_pledge_payment',
      'Address' => 'civicrm_address',
      'Phone' => 'civicrm_phone',
      'Email' => 'civicrm_email',
      'IM' => 'civicrm_im',
      'OpenID' => 'civicrm_openid',
      'Website' => 'civicrm_website',
      'Relationship' => 'civicrm_relationship',
      'Note' => 'civicrm_note',
      'Campaign' => 'civicrm_campaign',
      'Survey' => 'civicrm_survey',
      'CaseType' => 'civicrm_case_type',
      'GrantApplication' => 'civicrm_grant',
    ];

    return isset($map[$extends]) ? $map[$extends] : NULL;
  }

  protected function purgeAuditLog($settings) {
    $amount = (int) $settings->get('data_retention_audit_log_years');
    $unit = $settings->get('data_retention_audit_log_unit');

    if ($amount <= 0) {
      return 0;
    }

    $cutoff = $this->calculateCutoffDate($amount, $unit);
    if ($cutoff === NULL) {
      return 0;
    }

    $params = [1 => [$cutoff->format('Y-m-d H:i:s'), 'String']];
    $sql = 'DELETE FROM civicrm_data_retention_audit_log WHERE action_date < %1';
    $dao = CRM_Core_DAO::executeQuery($sql, $params);
    $deleted = (int) $dao->rowCount();

    if ($deleted > 0) {
      $this->logAction('purge_audit_log', 'AuditLog', NULL, [
        'deleted' => $deleted,
        'cutoff' => $cutoff->format('Y-m-d H:i:s'),
      ]);
    }

    return $deleted;
  }

  public function rollbackDeletions() {
    $sql = 'SELECT id, entity_type, entity_id, details FROM civicrm_data_retention_audit_log WHERE action = %1 ORDER BY id ASC';
    $dao = CRM_Core_DAO::executeQuery($sql, [1 => ['delete', 'String']]);
    $restored = 0;

    while ($dao->fetch()) {
      $details = CRM_Utils_JSON::decode($dao->details);
      if (!is_array($details)) {
        $this->logAction('rollback_failed', $dao->entity_type, $dao->entity_id, [
          'message' => 'Audit log entry is missing rollback details.',
          'audit_id' => $dao->id,
        ]);
        continue;
      }

      $apiEntity = CRM_Utils_Array::value('api_entity', $details, $dao->entity_type);
      $record = CRM_Utils_Array::value('record', $details);

      if (empty($record) || !is_array($record)) {
        $this->logAction('rollback_failed', $apiEntity, $dao->entity_id, [
          'message' => 'No record snapshot available for rollback.',
          'audit_id' => $dao->id,
        ]);
        continue;
      }

      $params = $this->prepareRecordForCreate($record);
      if (!isset($params['id']) && !empty($dao->entity_id)) {
        $params['id'] = $dao->entity_id;
      }

      $created = $this->attemptRecordRestore($apiEntity, $params);

      if (!$created && isset($params['id'])) {
        $paramsWithoutId = $params;
        unset($paramsWithoutId['id']);
        $created = $this->attemptRecordRestore($apiEntity, $paramsWithoutId);
      }

      if ($created) {
        $restored++;
        $details['rolled_back_at'] = date('c');
        CRM_Core_DAO::executeQuery(
          'UPDATE civicrm_data_retention_audit_log SET action = %1, details = %2 WHERE id = %3',
          [
            1 => ['rolled_back', 'String'],
            2 => [CRM_Utils_JSON::encode($details), 'String'],
            3 => [$dao->id, 'Integer'],
          ]
        );

        $restoredId = CRM_Utils_Array::value('id', $params);
        if (!$restoredId) {
          $restoredId = CRM_Utils_Array::value('id', $details['record'], $dao->entity_id);
        }

        $this->logAction('rollback_restored', $apiEntity, $restoredId, [
          'source_audit_id' => $dao->id,
        ]);
      }
      else {
        $this->logAction('rollback_failed', $apiEntity, $dao->entity_id, [
          'message' => 'Record could not be recreated from audit log.',
          'audit_id' => $dao->id,
        ]);
      }
    }

    if ($restored === 0) {
      $this->logAction('rollback_restored', 'AuditLog', NULL, [
        'message' => 'No deletions were restored during rollback.',
      ]);
    }

    return $restored;
  }

  protected function attemptRecordRestore($apiEntity, array $params) {
    try {
      civicrm_api3($apiEntity, 'create', $params);
      return TRUE;
    }
    catch (CiviCRM_API3_Exception $e) {
      Civi::log()->error('Data Retention Policy failed to restore record', [
        'entity' => $apiEntity,
        'params' => $params,
        'message' => $e->getMessage(),
      ]);
      return FALSE;
    }
  }

  protected function prepareRecordForCreate(array $record) {
    unset($record['is_error'], $record['error_message']);

    if (isset($record['id'])) {
      $record['id'] = (int) $record['id'];
    }

    if (isset($record['is_deleted'])) {
      $record['is_deleted'] = (int) $record['is_deleted'];
    }

    return $record;
  }

  protected function getRecordSnapshot($apiEntity, $id) {
    try {
      $record = civicrm_api3($apiEntity, 'getsingle', ['id' => $id]);
      return $this->prepareRecordForCreate($record);
    }
    catch (CiviCRM_API3_Exception $e) {
      Civi::log()->warning('Data Retention Policy failed to capture snapshot for audit log', [
        'entity' => $apiEntity,
        'id' => $id,
        'message' => $e->getMessage(),
      ]);
      return NULL;
    }
  }

  protected function logAction($action, $entityType, $entityId = NULL, array $context = []) {
    $params = [
      1 => [$action, 'String'],
      2 => [$entityType, 'String'],
      3 => [$entityId, 'Integer'],
      4 => [date('Y-m-d H:i:s'), 'String'],
      5 => [CRM_Utils_JSON::encode($context), 'String'],
    ];

    $sql = 'INSERT INTO civicrm_data_retention_audit_log (action, entity_type, entity_id, action_date, details) VALUES (%1, %2, %3, %4, %5)';
    CRM_Core_DAO::executeQuery($sql, $params);
  }

}
