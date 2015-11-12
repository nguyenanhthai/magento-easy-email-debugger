<?php

class Nat_Localmail_Helper_Data extends Mage_Core_Helper_Abstract {

  public function getMode() {
    if (Mage::getStoreConfig('localmail/general/enabled')) {
      return Mage::getStoreConfig('localmail/general/mode');
    }
    return false;
  }
}