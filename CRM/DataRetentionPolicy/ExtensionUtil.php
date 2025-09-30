<?php

class CRM_DataRetentionPolicy_ExtensionUtil {

  const SHORT_NAME = 'dataretentionpolicy';

  const LONG_NAME = 'uk.co.encircle.dataretentionpolicy';

  const CLASS_PREFIX = 'CRM_DataRetentionPolicy';

  public static function ts($text, $params = []) {
    return E::ts($text, $params);
  }

}

class E extends CRM_Extension_System {

  public static function ts($text, $params = []) {
    $extensionSystem = CRM_Extension_System::singleton();

    if (method_exists($extensionSystem, 'translate')) {
      return $extensionSystem->translate(
        CRM_DataRetentionPolicy_ExtensionUtil::LONG_NAME,
        $text,
        $params
      );
    }

    if (!is_array($params)) {
      $params = (array) $params;
    }

    if (!array_key_exists('domain', $params)) {
      $params['domain'] = CRM_DataRetentionPolicy_ExtensionUtil::LONG_NAME;
    }

    return ts($text, $params);
  }

}
