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
    return CRM_Extension_System::singleton()->translate(self::LONG_NAME, $text, $params);
  }

}
