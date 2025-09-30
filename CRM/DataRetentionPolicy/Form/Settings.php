<?php

use CRM_DataRetentionPolicy_ExtensionUtil as E;

class CRM_DataRetentionPolicy_Form_Settings extends CRM_Core_Form {

  protected $settingKeys = [
    'data_retention_contact_years' => 'contact',
    'data_retention_contact_date_source' => 'contact',
    'data_retention_contact_trash_days' => 'contact_trash',
    'data_retention_participant_years' => 'participant',
    'data_retention_contribution_years' => 'contribution',
  ];

  public function buildQuickForm() {
    $this->setTitle(E::ts('Data Retention Policy Settings'));

    $definitions = $this->getEntityDefinitions();
    foreach ($definitions as $key => $definition) {
      $inputType = CRM_Utils_Array::value('input_type', $definition, 'text');
      switch ($inputType) {
        case 'select':
          $this->add('select', $key, $definition['label'], CRM_Utils_Array::value('options', $definition, []));
          break;

        default:
          $attributes = CRM_Utils_Array::value('attributes', $definition, ['size' => 4, 'maxlength' => 3]);
          $this->add('text', $key, $definition['label'], $attributes);
          $ruleMessage = !empty($definition['rule_message']) ? $definition['rule_message'] : E::ts('Please enter a whole number or leave blank to disable deletion.');
          $this->addRule($key, $ruleMessage, 'integer');
          break;
      }
    }

    $this->assign('entityDefinitions', $definitions);

    $this->addButtons([
      ['type' => 'submit', 'name' => E::ts('Save'), 'isDefault' => TRUE],
      ['type' => 'cancel', 'name' => E::ts('Cancel')],
    ]);
  }

  public function setDefaultValues() {
    $defaults = [];
    $settings = Civi::settings();
    foreach (array_keys($this->settingKeys) as $setting) {
      $defaults[$setting] = $settings->get($setting);
    }
    return $defaults;
  }

  public function postProcess() {
    $values = $this->exportValues();
    $settings = Civi::settings();

    $definitions = $this->getEntityDefinitions();
    foreach (array_keys($this->settingKeys) as $setting) {
      $definition = CRM_Utils_Array::value($setting, $definitions, []);
      $valueType = CRM_Utils_Array::value('value_type', $definition, 'integer');
      if ($valueType === 'string') {
        $value = CRM_Utils_Array::value($setting, $values);
        $options = CRM_Utils_Array::value('options', $definition, []);
        if (!array_key_exists($value, $options)) {
          $value = CRM_Utils_Array::value('default', $definition, '');
        }
      }
      else {
        $value = CRM_Utils_Array::value($setting, $values);
        $value = is_numeric($value) ? (int) $value : 0;
        if ($value < 0) {
          $value = 0;
        }
      }
      $settings->set($setting, $value);
    }

    CRM_Core_Session::setStatus(E::ts('Data retention policy settings have been saved.'), E::ts('Saved'), 'success');
  }

  protected function getEntityDefinitions() {
    return [
      'data_retention_contact_years' => [
        'label' => E::ts('Contact records (years)'),
        'description' => E::ts('Contacts are deleted when their most recent activity, modification or creation is older than the configured number of years.'),
        'value_type' => 'integer',
      ],
      'data_retention_contact_date_source' => [
        'label' => E::ts('Contact retention date source'),
        'description' => E::ts('Select whether contacts should be evaluated using their last recorded activity or their last login date.'),
        'input_type' => 'select',
        'options' => [
          'activity' => E::ts('Last activity date'),
          'login' => E::ts('Last login date (from CMS account)'),
        ],
        'value_type' => 'string',
        'default' => 'activity',
      ],
      'data_retention_contact_trash_days' => [
        'label' => E::ts('Contacts in trash (days)'),
        'description' => E::ts('Contacts that have already been deleted (moved to the trash) are permanently removed after the configured number of days in the trash.'),
        'value_type' => 'integer',
      ],
      'data_retention_participant_years' => [
        'label' => E::ts('Participant records (years)'),
        'description' => E::ts('Participants are deleted when their most recent modification or registration is older than the configured number of years.'),
        'value_type' => 'integer',
      ],
      'data_retention_contribution_years' => [
        'label' => E::ts('Contribution records (years)'),
        'description' => E::ts('Contributions are deleted when their receive date (or creation date if receive date is empty) is older than the configured number of years.'),
        'value_type' => 'integer',
      ],
    ];
  }

}
