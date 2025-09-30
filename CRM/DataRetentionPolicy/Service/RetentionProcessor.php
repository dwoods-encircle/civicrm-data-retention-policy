<?php

class CRM_DataRetentionPolicy_Service_RetentionProcessor {

  public function applyPolicies() {
    $settings = Civi::settings();
    $results = [];

    foreach ($this->getEntityConfigurations() as $settingKey => $config) {
      $amount = (int) $settings->get($settingKey);
      if ($amount <= 0) {
        $results[$config['entity']] = 0;
        continue;
      }
      $cutoff = new DateTime('now', new DateTimeZone('UTC'));
      $modifierPattern = isset($config['modifier']) ? $config['modifier'] : '-%d years';
      $cutoff->modify(sprintf($modifierPattern, $amount));
      $count = $this->deleteExpiredRecords($config, $cutoff);
      $results[$config['entity']] = $count;
    }

    return $results;
  }

  protected function deleteExpiredRecords(array $config, DateTime $cutoff) {
    $ids = $this->getIdsToDelete($config, $cutoff);
    $count = 0;
    foreach ($ids as $id) {
      $params = ['id' => $id];
      if (!empty($config['api_params'])) {
        $params = array_merge($params, $config['api_params']);
      }
      try {
        civicrm_api3($config['api_entity'], 'delete', $params);
        $count++;
      }
      catch (CiviCRM_API3_Exception $e) {
        Civi::log()->error('Data Retention Policy failed to delete record', [
          'entity' => $config['entity'],
          'id' => $id,
          'message' => $e->getMessage(),
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

  protected function getEntityConfigurations() {
    return [
      'data_retention_contact_years' => [
        'entity' => 'Contact',
        'api_entity' => 'Contact',
        'table' => 'civicrm_contact',
        'id_field' => 'id',
        'date_expression' => 'COALESCE(last_activity_date, modified_date, created_date)',
        'additional_where' => 'is_deleted = 0',
        'modifier' => '-%d years',
      ],
      'data_retention_contact_trash_days' => [
        'entity' => 'Contact (trash)',
        'api_entity' => 'Contact',
        'table' => 'civicrm_contact',
        'id_field' => 'id',
        'date_expression' => 'modified_date',
        'additional_where' => 'is_deleted = 1',
        'modifier' => '-%d days',
        'api_params' => ['skip_undelete' => 1],
      ],
      'data_retention_participant_years' => [
        'entity' => 'Participant',
        'api_entity' => 'Participant',
        'table' => 'civicrm_participant',
        'id_field' => 'id',
        'date_expression' => 'COALESCE(modified_date, register_date, created_date)',
        'additional_where' => '1',
        'modifier' => '-%d years',
      ],
      'data_retention_contribution_years' => [
        'entity' => 'Contribution',
        'api_entity' => 'Contribution',
        'table' => 'civicrm_contribution',
        'id_field' => 'id',
        'date_expression' => 'COALESCE(receive_date, modified_date, created_date)',
        'additional_where' => '1',
        'modifier' => '-%d years',
      ],
    ];
  }

}
