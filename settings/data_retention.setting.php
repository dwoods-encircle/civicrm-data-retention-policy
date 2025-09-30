<?php

if (!class_exists('CRM_DataRetentionPolicy_ExtensionUtil')) {
  require_once __DIR__ . '/../CRM/DataRetentionPolicy/ExtensionUtil.php';
}

use CRM_DataRetentionPolicy_ExtensionUtil as E;

return [
  'data_retention_contact_years' => [
    'name' => 'data_retention_contact_years',
    'type' => 'Integer',
    'title' => E::ts('Contact retention period (years)'),
    'description' => E::ts('Delete contacts when they have no recorded activity newer than the configured number of years.'),
    'default' => 0,
  ],
  'data_retention_contact_date_source' => [
    'name' => 'data_retention_contact_date_source',
    'type' => 'String',
    'title' => E::ts('Contact retention date source'),
    'description' => E::ts('Choose whether contacts are evaluated using their last activity date or their last login date when determining deletion.'),
    'default' => 'activity',
  ],
  'data_retention_contact_trash_days' => [
    'name' => 'data_retention_contact_trash_days',
    'type' => 'Integer',
    'title' => E::ts('Contact trash retention period (days)'),
    'description' => E::ts('Permanently delete contacts that have remained in the trash for the configured number of days.'),
    'default' => 0,
  ],
  'data_retention_participant_years' => [
    'name' => 'data_retention_participant_years',
    'type' => 'Integer',
    'title' => E::ts('Participant retention period (years)'),
    'description' => E::ts('Delete participant records when their most recent update is older than the configured number of years.'),
    'default' => 0,
  ],
  'data_retention_contribution_years' => [
    'name' => 'data_retention_contribution_years',
    'type' => 'Integer',
    'title' => E::ts('Contribution retention period (years)'),
    'description' => E::ts('Delete contribution records when their receive date is older than the configured number of years.'),
    'default' => 0,
  ],
];
